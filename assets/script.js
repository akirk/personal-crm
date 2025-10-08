function switchGroup() {
    const selector = document.getElementById('group-selector');
    window.location = selector.value;
}

function createTimeUpdater(timezone, personId, options = {}) {
    const config = {
        useWorkHoursIcon: options.useWorkHoursIcon || false,
        verboseDifference: options.verboseDifference || false,
        noTimezoneMessage: options.noTimezoneMessage || '🕒 Timezone not set',
        ...options
    };

    function updateTime() {
        const timeElement = document.getElementById(`time-${personId}`);

        if (!timeElement) return;

        if (!timezone) {
            timeElement.textContent = config.noTimezoneMessage;
            return;
        }

        try {
            const now = new Date();
            const personTime = new Intl.DateTimeFormat('en-US', {
                timeZone: timezone,
                hour: '2-digit',
                minute: '2-digit',
                hour12: false
            }).format(now);

            const myTime = new Intl.DateTimeFormat('en-US', {
                hour: '2-digit',
                minute: '2-digit',
                hour12: false
            }).format(now);

            // Determine the appropriate icon
            let timeIcon = '🕒';
            if (config.useWorkHoursIcon) {
                const personHour = parseInt(personTime.split(':')[0], 10);
                const isAfterHours = personHour < 8 || personHour >= 17;
                timeIcon = isAfterHours ? '🌙' : '🕒';
            }

            let timeString = `${timeIcon} ${personTime}`;

            if (personTime !== myTime) {
                // Calculate time difference in hours
                const personDate = new Date();
                personDate.setHours(parseInt(personTime.split(':')[0], 10));
                personDate.setMinutes(parseInt(personTime.split(':')[1], 10));

                const myDate = new Date();
                myDate.setHours(parseInt(myTime.split(':')[0], 10));
                myDate.setMinutes(parseInt(myTime.split(':')[1], 10));

                let hourDiff = (personDate.getHours() - myDate.getHours());

                // Handle day boundary crossing
                if (hourDiff > 12) hourDiff -= 24;
                if (hourDiff < -12) hourDiff += 24;

                if (hourDiff !== 0) {
                    const absHours = Math.abs(hourDiff);
                    if (config.verboseDifference) {
                        const direction = hourDiff > 0 ? 'east' : 'west';
                        const hourText = absHours === 1 ? 'hr' : 'hrs';
                        timeString += ` (${absHours} ${hourText} ${direction} of you)`;
                    } else {
                        const direction = hourDiff > 0 ? '+' : '-';
                        timeString += ` (${direction}${absHours} hrs)`;
                    }
                }
            }

            timeElement.textContent = timeString;
        } catch (e) {
            timeElement.textContent = '🕒 Invalid timezone';
        }
    }

    // Update time immediately and then every minute
    updateTime();
    setInterval(updateTime, 60000);
}

// Legacy function for backward compatibility
function createSimpleTimeUpdater(timezone, personId) {
    return createTimeUpdater(timezone, personId, {
        useWorkHoursIcon: true,
        verboseDifference: false,
        noTimezoneMessage: '🕒 No timezone'
    });
}

function initializeDarkMode() {
    const toggle = document.getElementById('dark-mode-toggle');

    if (!toggle) {
        return; // Exit if toggle doesn't exist
    }

    const sunIcon = toggle.querySelector('.sun-icon');
    const sunForcedIcon = toggle.querySelector('.sun-forced-icon');
    const moonIcon = toggle.querySelector('.moon-icon');
    const moonForcedIcon = toggle.querySelector('.moon-forced-icon');

    if (!sunIcon || !sunForcedIcon || !moonIcon || !moonForcedIcon) {
        return; // Exit if icons don't exist
    }

    const getSystemTheme = () => window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';

    function getCycleStep() {
        return parseInt(localStorage.getItem('theme-cycle-step') || '0');
    }

    function setCycleStep(step) {
        localStorage.setItem('theme-cycle-step', step.toString());
    }

    function getCurrentThemeFromCycle() {
        const systemTheme = getSystemTheme();
        const step = getCycleStep();

        if (systemTheme === 'light') {
            // Light system mode cycle: light, temp dark, force light, force dark, temp light, temp dark...
            switch (step % 4) {
                case 0: return { theme: 'light', isForced: false, isTemp: false };
                case 1: return { theme: 'dark', isForced: false, isTemp: true };
                case 2: return { theme: 'light', isForced: true, isTemp: false };
                case 3: return { theme: 'dark', isForced: true, isTemp: false };
            }
        } else {
            // Dark system mode cycle: dark, temp light, force dark, force light, temp dark, temp light...
            switch (step % 4) {
                case 0: return { theme: 'dark', isForced: false, isTemp: false };
                case 1: return { theme: 'light', isForced: false, isTemp: true };
                case 2: return { theme: 'dark', isForced: true, isTemp: false };
                case 3: return { theme: 'light', isForced: true, isTemp: false };
            }
        }
    }

    function updateDisplay() {
        const { theme, isForced } = getCurrentThemeFromCycle();

        // Hide all icons first
        sunIcon.style.display = 'none';
        sunForcedIcon.style.display = 'none';
        moonIcon.style.display = 'none';
        moonForcedIcon.style.display = 'none';

        // Show appropriate icon based on theme and forced state
        if (theme === 'dark') {
            if (isForced) {
                moonForcedIcon.style.display = 'block';
            } else {
                moonIcon.style.display = 'block';
            }
        } else {
            if (isForced) {
                sunForcedIcon.style.display = 'block';
            } else {
                sunIcon.style.display = 'block';
            }
        }

        // Apply the theme
        document.documentElement.style.colorScheme = theme;
    }

    // Initialize: if there's an old theme-override, convert it to cycle system
    const oldOverride = localStorage.getItem('theme-override');
    if (oldOverride && !localStorage.getItem('theme-cycle-step')) {
        // Convert existing override to cycle step 2 (forced mode)
        setCycleStep(2);
        localStorage.removeItem('theme-override');
    }

    updateDisplay();

    toggle.addEventListener('click', () => {
        const currentStep = getCycleStep();
        const nextStep = currentStep + 1;
        setCycleStep(nextStep);
        updateDisplay();
    });

    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
        updateDisplay();
    });
}

function initializeCommandK(peopleData, teamsData) {
    if (typeof CmdK !== 'undefined') {
        CmdK.init(peopleData, teamsData);
    }
}

document.addEventListener('DOMContentLoaded', initializeDarkMode);

// ===========================================
// Privacy Mode (Client-Side)
// ===========================================

/**
 * Privacy mode implementation - masks names and sensitive data client-side
 */
function initPrivacyMode() {
    const isEnabled = localStorage.getItem('privacyMode') === 'true';

    // Update toggle UI
    updatePrivacyToggleUI(isEnabled);

    // Apply privacy transformations if enabled
    if (isEnabled) {
        applyPrivacyMode();
    }
}

/**
 * Apply privacy transformations to the page
 */
function applyPrivacyMode() {
    // Wait for searchIndex to be available from cmd-k initialization
    const checkSearchIndex = setInterval(() => {
        if (typeof searchIndex !== 'undefined') {
            clearInterval(checkSearchIndex);
            maskNames(searchIndex);
            blurAvatars();
        }
    }, 100);

    // Failsafe: stop checking after 5 seconds
    setTimeout(() => clearInterval(checkSearchIndex), 5000);
}

/**
 * Mask last names in the DOM using person data from search index
 */
function maskNames(peopleData) {
    if (!peopleData || !Array.isArray(peopleData)) {
        return;
    }

    // Build a map of full names to masked names
    const nameMap = new Map();

    peopleData.forEach(person => {
        if (!person.name) return;

        const parts = person.name.trim().split(' ');
        if (parts.length > 1) {
            const firstName = parts[0];
            const lastInitial = parts[parts.length - 1].charAt(0) + '.';
            const maskedName = firstName + ' ' + lastInitial;
            nameMap.set(person.name, maskedName);
        }
    });

    // Walk the DOM and replace names in text nodes
    const walk = (node) => {
        if (node.nodeType === Node.TEXT_NODE) {
            let text = node.textContent;
            let modified = false;

            nameMap.forEach((maskedName, fullName) => {
                if (text.includes(fullName)) {
                    text = text.replace(new RegExp(fullName.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g'), maskedName);
                    modified = true;
                }
            });

            if (modified) {
                node.textContent = text;
            }
        } else if (node.nodeType === Node.ELEMENT_NODE) {
            // Skip script and style elements
            if (node.tagName !== 'SCRIPT' && node.tagName !== 'STYLE') {
                // Process value attributes for input elements
                if (node.tagName === 'INPUT' && node.value) {
                    nameMap.forEach((maskedName, fullName) => {
                        if (node.value.includes(fullName)) {
                            node.value = node.value.replace(new RegExp(fullName.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g'), maskedName);
                        }
                    });
                }
                // Recurse into child nodes
                node.childNodes.forEach(child => walk(child));
            }
        }
    };

    walk(document.body);
}

/**
 * Add blur effect to avatar images
 */
function blurAvatars() {
    const avatars = document.querySelectorAll('.gravatar-large, .person-avatar img, img[alt*="avatar"]');
    avatars.forEach(avatar => {
        avatar.classList.add('privacy-blur');
    });
}

/**
 * Toggle privacy mode on/off
 */
function togglePrivacyMode() {
    const currentState = localStorage.getItem('privacyMode') === 'true';
    const newState = !currentState;

    localStorage.setItem('privacyMode', newState.toString());

    // Reload page to apply/remove privacy transformations
    window.location.reload();
}

/**
 * Update the privacy toggle button UI
 */
function updatePrivacyToggleUI(isEnabled) {
    const toggle = document.getElementById('privacy-toggle');
    const status = document.getElementById('privacy-status');

    if (status) {
        status.textContent = isEnabled ? '🔒 Privacy Mode ON' : '🔓 Privacy Mode OFF';
    }
}

// Initialize privacy mode when DOM is ready
document.addEventListener('DOMContentLoaded', initPrivacyMode);