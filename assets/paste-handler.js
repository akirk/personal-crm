/**
 * Personal CRM Paste Handler
 *
 * Extensible paste detection system that auto-detects data types
 * and can either fill form fields or save directly via AJAX.
 *
 * Plugins can register their own detectors via:
 *   window.PersonalCRMPasteHandler.registerDetector('phone', { ... })
 */
(function() {
	'use strict';

	const PasteHandler = {
		detectors: {},
		config: {
			username: null,
			ajaxUrl: null,
			nonce: null,
			mode: 'view' // 'view' (AJAX save) or 'edit' (fill form)
		},

		/**
		 * Initialize the paste handler
		 * @param {Object} options - Configuration options
		 */
		init: function(options) {
			this.config = { ...this.config, ...options };
			this.registerBuiltInDetectors();
			this.attachListeners();
		},

		/**
		 * Shared date detection logic
		 */
		detectDate: function(text) {
			text = text.trim();

			// ISO format: YYYY-MM-DD
			if (/^\d{4}-\d{2}-\d{2}$/.test(text)) {
				return { value: text, display: this.formatDateForDisplay(text) };
			}

			// US format: MM/DD/YYYY or M/D/YYYY
			const usMatch = text.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
			if (usMatch) {
				const [, month, day, year] = usMatch;
				const isoDate = `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')}`;
				if (this.isValidDate(isoDate)) {
					return { value: isoDate, display: this.formatDateForDisplay(isoDate) };
				}
			}

			// EU format: DD.MM.YYYY or DD/MM/YYYY
			const euMatch = text.match(/^(\d{1,2})[.\/](\d{1,2})[.\/](\d{4})$/);
			if (euMatch) {
				const [, day, month, year] = euMatch;
				const isoDate = `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')}`;
				if (this.isValidDate(isoDate)) {
					return { value: isoDate, display: this.formatDateForDisplay(isoDate) };
				}
			}

			// Text format: "January 15, 1990" or "15 January 1990"
			const textMatch = this.parseTextDate(text);
			if (textMatch) {
				return { value: textMatch, display: this.formatDateForDisplay(textMatch) };
			}

			// Month-day only: MM-DD or MM/DD (no year)
			const mdMatch = text.match(/^(\d{1,2})[-\/](\d{1,2})$/);
			if (mdMatch) {
				const [, month, day] = mdMatch;
				const mmdd = `${month.padStart(2, '0')}-${day.padStart(2, '0')}`;
				if (parseInt(month) >= 1 && parseInt(month) <= 12 && parseInt(day) >= 1 && parseInt(day) <= 31) {
					return { value: mmdd, display: this.formatMonthDayForDisplay(mmdd) };
				}
			}

			return null;
		},

		/**
		 * Register built-in detectors
		 */
		registerBuiltInDetectors: function() {
			const self = this;

			// Main birthday detector
			this.registerDetector('birthday', {
				label: 'Birthday',
				icon: '🎂',
				fieldId: 'birthday',
				priority: 10,
				detect: (text) => self.detectDate(text),
				formatForSave: (value) => value,
				fillForm: function(value) {
					return self.fillBirthdayForm(value, 'birthday');
				}
			});

			// Partner birthday detector (if partner exists)
			const personData = this.config.personData;
			if (personData && personData.partner) {
				this.registerDetector('partner_birthday', {
					label: `${personData.partner}'s Birthday`,
					icon: '💑',
					fieldId: 'partner_birthday',
					priority: 20,
					detect: (text) => self.detectDate(text),
					formatForSave: (value) => value,
					fillForm: function(value) {
						return self.fillBirthdayForm(value, 'partner_birthday');
					}
				});
			}

			// Child birthday detectors (for each existing child)
			if (personData && personData.kids && personData.kids.length > 0) {
				personData.kids.forEach((kid, index) => {
					const kidName = kid.name || `Child ${index + 1}`;
					this.registerDetector(`child_birthday_${index}`, {
						label: `${kidName}'s Birthday`,
						icon: '👶',
						fieldId: `child_birthday_${index}`,
						priority: 30 + index,
						detect: (text) => self.detectDate(text),
						formatForSave: (value) => value,
						childIndex: index,
						fillForm: null // Children use AJAX save only
					});
				});
			}
		},

		/**
		 * Helper to fill birthday form fields
		 */
		fillBirthdayForm: function(value, prefix) {
			let day = '', month = '', year = '';

			if (/^\d{4}-\d{2}-\d{2}$/.test(value)) {
				const parts = value.split('-');
				year = parts[0];
				month = parts[1];
				day = parts[2];
			} else if (/^\d{2}-\d{2}$/.test(value)) {
				const parts = value.split('-');
				month = parts[0];
				day = parts[1];
			}

			const daySelect = document.querySelector(`select[name="${prefix}_day"]`);
			const monthSelect = document.querySelector(`select[name="${prefix}_month"]`);
			const yearSelect = document.querySelector(`select[name="${prefix}_year"]`);

			if (daySelect) daySelect.value = day;
			if (monthSelect) monthSelect.value = month;
			if (yearSelect) yearSelect.value = year;

			return !!(daySelect || monthSelect || yearSelect);
		},

		/**
		 * Register a custom detector (for plugins)
		 * @param {string} name - Unique detector name
		 * @param {Object} config - Detector configuration
		 */
		registerDetector: function(name, config) {
			this.detectors[name] = {
				name: name,
				label: config.label || name,
				icon: config.icon || '📋',
				fieldId: config.fieldId || name,
				priority: config.priority || 50,
				detect: config.detect,
				formatForSave: config.formatForSave || (v => v),
				fillForm: config.fillForm || null
			};
		},

		/**
		 * Attach paste event listeners
		 */
		attachListeners: function() {
			document.addEventListener('paste', (e) => {
				// Don't intercept if user is focused on an input/textarea
				const activeEl = document.activeElement;
				const isInput = activeEl && (
					activeEl.tagName === 'INPUT' ||
					activeEl.tagName === 'TEXTAREA' ||
					activeEl.isContentEditable
				);

				if (isInput) {
					return; // Let normal paste happen
				}

				const text = (e.clipboardData || window.clipboardData).getData('text');
				if (!text || text.length > 100) {
					return; // Ignore empty or very long pastes
				}

				const matches = this.detectAll(text);
				if (matches.length > 0) {
					e.preventDefault();
					this.showConfirmation(text, matches);
				} else {
					// Show brief feedback that paste was noticed but not recognized
					const preview = text.length > 30 ? text.substring(0, 30) + '…' : text;
					this.showToast(`📋 "${preview}" - not recognized`, 'info');
				}
			});
		},

		/**
		 * Run all detectors on the pasted text
		 * @param {string} text - Pasted text
		 * @returns {Array} Array of matches
		 */
		detectAll: function(text) {
			const matches = [];

			// Sort detectors by priority
			const sortedDetectors = Object.values(this.detectors)
				.sort((a, b) => a.priority - b.priority);

			for (const detector of sortedDetectors) {
				try {
					const result = detector.detect(text);
					if (result) {
						matches.push({
							detector: detector,
							value: result.value,
							display: result.display || result.value
						});
					}
				} catch (err) {
					console.error(`Paste detector "${detector.name}" error:`, err);
				}
			}

			return matches;
		},

		/**
		 * Show confirmation dialog for detected paste
		 * @param {string} originalText - Original pasted text
		 * @param {Array} matches - Detected matches
		 */
		showConfirmation: function(originalText, matches) {
			// Remove any existing dialog
			const existingDialog = document.getElementById('paste-handler-dialog');
			if (existingDialog) {
				existingDialog.remove();
			}

			// Build dialog using DOM methods for security
			const dialog = document.createElement('div');
			dialog.id = 'paste-handler-dialog';
			dialog.className = 'paste-handler-dialog';

			const content = document.createElement('div');
			content.className = 'paste-handler-content';

			// Header
			const header = document.createElement('div');
			header.className = 'paste-handler-header';

			const title = document.createElement('span');
			title.className = 'paste-handler-title';
			title.textContent = 'Paste detected';

			const closeBtn = document.createElement('button');
			closeBtn.type = 'button';
			closeBtn.className = 'paste-handler-close';
			closeBtn.setAttribute('aria-label', 'Close');
			closeBtn.textContent = '×';

			header.appendChild(title);
			header.appendChild(closeBtn);

			// Body
			const body = document.createElement('div');
			body.className = 'paste-handler-body';

			const preview = document.createElement('div');
			preview.className = 'paste-handler-preview';
			preview.textContent = `"${originalText}"`;

			const options = document.createElement('div');
			options.className = 'paste-handler-options';

			matches.forEach((match, index) => {
				const btn = document.createElement('button');
				btn.type = 'button';
				btn.className = 'paste-handler-option';
				btn.dataset.index = index;

				const iconSpan = document.createElement('span');
				iconSpan.className = 'paste-handler-option-icon';
				iconSpan.textContent = match.detector.icon;

				const labelSpan = document.createElement('span');
				labelSpan.className = 'paste-handler-option-label';

				const strong = document.createElement('strong');
				strong.textContent = match.detector.label;

				const valueSpan = document.createElement('span');
				valueSpan.className = 'paste-handler-option-value';
				valueSpan.textContent = match.display;

				labelSpan.appendChild(strong);
				labelSpan.appendChild(valueSpan);

				btn.appendChild(iconSpan);
				btn.appendChild(labelSpan);
				options.appendChild(btn);
			});

			body.appendChild(preview);
			body.appendChild(options);

			content.appendChild(header);
			content.appendChild(body);
			dialog.appendChild(content);

			document.body.appendChild(dialog);

			// Attach event handlers
			closeBtn.addEventListener('click', () => {
				dialog.remove();
			});

			options.querySelectorAll('.paste-handler-option').forEach(btn => {
				btn.addEventListener('click', () => {
					const index = parseInt(btn.dataset.index);
					const match = matches[index];
					this.handleSelection(match);
					dialog.remove();
				});
			});

			// Close on escape
			const handleEscape = (e) => {
				if (e.key === 'Escape') {
					dialog.remove();
					document.removeEventListener('keydown', handleEscape);
				}
			};
			document.addEventListener('keydown', handleEscape);

			// Close on click outside
			dialog.addEventListener('click', (e) => {
				if (e.target === dialog) {
					dialog.remove();
				}
			});
		},

		/**
		 * Handle user selection of a detected field
		 * @param {Object} match - Selected match
		 */
		handleSelection: function(match) {
			if (this.config.mode === 'edit') {
				// Form mode: fill the form field
				if (match.detector.fillForm) {
					const filled = match.detector.fillForm(match.value);
					if (filled) {
						this.showToast(`${match.detector.icon} ${match.detector.label} filled`);
					}
				} else {
					// Default: try to find input by fieldId
					const input = document.getElementById(match.detector.fieldId);
					if (input) {
						input.value = match.value;
						input.dispatchEvent(new Event('change', { bubbles: true }));
						this.showToast(`${match.detector.icon} ${match.detector.label} filled`);
					}
				}
			} else {
				// View mode: save via AJAX
				this.saveField(match.detector.fieldId, match.detector.formatForSave(match.value), match.detector.label, match.detector.icon);
			}
		},

		/**
		 * Save a field via AJAX
		 * @param {string} field - Field name
		 * @param {string} value - Field value
		 * @param {string} label - Human-readable label
		 * @param {string} icon - Icon for toast
		 */
		saveField: function(field, value, label, icon) {
			if (!this.config.username || !this.config.ajaxUrl) {
				console.error('Paste handler not configured for AJAX save');
				return;
			}

			const formData = new FormData();
			formData.append('action', 'personal_crm_quick_update');
			formData.append('username', this.config.username);
			formData.append('field', field);
			formData.append('value', value);
			formData.append('nonce', this.config.nonce || '');

			this.showToast(`${icon} Saving ${label}...`, 'loading');

			fetch(this.config.ajaxUrl, {
				method: 'POST',
				body: formData
			})
			.then(response => response.json())
			.then(data => {
				if (data.success) {
					this.showToast(`${icon} ${label} saved!`, 'success');
					// Reload page to show updated data
					if (data.reload !== false) {
						setTimeout(() => window.location.reload(), 500);
					}
				} else {
					this.showToast(`Failed to save ${label}: ${data.message || 'Unknown error'}`, 'error');
				}
			})
			.catch(err => {
				console.error('Save error:', err);
				this.showToast(`Failed to save ${label}`, 'error');
			});
		},

		/**
		 * Show a toast notification
		 * @param {string} message - Message to show
		 * @param {string} type - Toast type (success, error, loading)
		 */
		showToast: function(message, type = 'success') {
			// Remove existing toast
			const existing = document.getElementById('paste-handler-toast');
			if (existing) {
				existing.remove();
			}

			const toast = document.createElement('div');
			toast.id = 'paste-handler-toast';
			toast.className = `paste-handler-toast paste-handler-toast-${type}`;
			toast.textContent = message;

			document.body.appendChild(toast);

			// Auto-remove after delay (except for loading)
			if (type !== 'loading') {
				setTimeout(() => toast.remove(), 3000);
			}
		},

		// Helper functions
		isValidDate: function(dateStr) {
			const date = new Date(dateStr);
			return date instanceof Date && !isNaN(date);
		},

		formatDateForDisplay: function(isoDate) {
			try {
				const [year, month, day] = isoDate.split('-');
				const date = new Date(year, month - 1, day);
				return date.toLocaleDateString('en-US', {
					year: 'numeric',
					month: 'long',
					day: 'numeric'
				});
			} catch {
				return isoDate;
			}
		},

		formatMonthDayForDisplay: function(mmdd) {
			try {
				const [month, day] = mmdd.split('-');
				const date = new Date(2000, parseInt(month) - 1, parseInt(day));
				return date.toLocaleDateString('en-US', {
					month: 'long',
					day: 'numeric'
				});
			} catch {
				return mmdd;
			}
		},

		parseTextDate: function(text) {
			const months = {
				// English
				'january': '01', 'february': '02', 'march': '03', 'april': '04',
				'may': '05', 'june': '06', 'july': '07', 'august': '08',
				'september': '09', 'october': '10', 'november': '11', 'december': '12',
				'jan': '01', 'feb': '02', 'mar': '03', 'apr': '04',
				'jun': '06', 'jul': '07', 'aug': '08', 'sep': '09',
				'oct': '10', 'nov': '11', 'dec': '12',
				// German
				'januar': '01', 'februar': '02', 'märz': '03', 'maerz': '03',
				'mai': '05', 'juni': '06', 'juli': '07',
				'oktober': '10', 'november': '11', 'dezember': '12'
			};

			// "January 15, 1990" or "January 15 1990"
			const match1 = text.match(/^([a-zäöü]+)\s+(\d{1,2}),?\s+(\d{4})$/i);
			if (match1) {
				const month = months[match1[1].toLowerCase()];
				if (month) {
					return `${match1[3]}-${month}-${match1[2].padStart(2, '0')}`;
				}
			}

			// "15 January 1990" or "15. January 1990" or "16. December 1987"
			const match2 = text.match(/^(\d{1,2})\.?\s+([a-zäöü]+),?\s+(\d{4})$/i);
			if (match2) {
				const month = months[match2[2].toLowerCase()];
				if (month) {
					return `${match2[3]}-${month}-${match2[1].padStart(2, '0')}`;
				}
			}

			return null;
		}
	};

	// Expose globally for plugins to register detectors
	window.PersonalCRMPasteHandler = PasteHandler;
})();
