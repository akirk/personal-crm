document.addEventListener('DOMContentLoaded', function() {
	// Group membership management
	const groupMembershipContainer = document.getElementById('selected-groups-container');
	if (!groupMembershipContainer) {
		return; // Not on person edit page
	}

	const allGroupsDataElement = document.getElementById('all-groups-data');
	if (!allGroupsDataElement) {
		return;
	}

	const allGroupsData = JSON.parse(allGroupsDataElement.textContent);
	const groupsById = {};
	const groupsByName = {};
	allGroupsData.forEach(g => {
		groupsById[g.id] = g;
		const displayName = (g.display_icon ? g.display_icon + ' ' : '') + g.hierarchical_name;
		groupsByName[displayName] = g;
	});

	// Store selected group for button actions
	let selectedGroupForAdd = null;

	const addGroupInput = document.getElementById('add-group-input');
	const addToCurrentBtn = document.getElementById('add-to-current-btn');
	const addToHistoricalBtn = document.getElementById('add-to-historical-btn');

	if (addGroupInput) {
		// Prevent form submission on Enter key
		addGroupInput.addEventListener('keydown', function(e) {
			if (e.key === 'Enter') {
				e.preventDefault();
			}
		});

		addGroupInput.addEventListener('change', function(e) {
			const selectedName = e.target.value.trim();

			if (!selectedName || !groupsByName[selectedName]) {
				selectedGroupForAdd = null;
				if (addToCurrentBtn) addToCurrentBtn.disabled = true;
				if (addToHistoricalBtn) addToHistoricalBtn.disabled = true;
				return;
			}

			selectedGroupForAdd = groupsByName[selectedName];

			// Check if already added
			const existing = document.querySelector(`#selected-groups-container label[data-group-id="${selectedGroupForAdd.id}"]`);
			if (existing) {
				alert('This group is already in current memberships');
				e.target.value = '';
				selectedGroupForAdd = null;
				if (addToCurrentBtn) addToCurrentBtn.disabled = true;
				if (addToHistoricalBtn) addToHistoricalBtn.disabled = true;
				return;
			}

			// Enable buttons
			if (addToCurrentBtn) addToCurrentBtn.disabled = false;
			if (addToHistoricalBtn) addToHistoricalBtn.disabled = false;
		});
	}

	// Handle "Add to Current" button
	if (addToCurrentBtn) {
		addToCurrentBtn.addEventListener('click', function(e) {
			e.preventDefault();
			if (!selectedGroupForAdd) return;

			const group = selectedGroupForAdd;
			const container = document.getElementById('selected-groups-container');

			const label = document.createElement('label');
			label.className = 'group-checkbox-label selected';
			label.style.display = 'flex';
			label.style.alignItems = 'center';
			label.style.gap = '10px';
			label.setAttribute('data-group-id', group.id);

			const checkbox = document.createElement('input');
			checkbox.type = 'checkbox';
			checkbox.name = `groups[${group.id}][checked]`;
			checkbox.value = '1';
			checkbox.checked = true;

			const icon = group.display_icon ? document.createElement('span') : null;
			if (icon) {
				icon.className = 'group-icon';
				icon.textContent = group.display_icon;
			}

			const nameSpan = document.createElement('span');
			nameSpan.className = 'group-name';
			nameSpan.textContent = group.hierarchical_name;

			const dateInput = document.createElement('input');
			dateInput.type = 'date';
			dateInput.name = `groups[${group.id}][joined_date]`;
			dateInput.style.marginLeft = 'auto';
			dateInput.style.width = '140px';
			dateInput.style.fontSize = '0.9em';
			dateInput.title = 'Date joined this group (optional)';
			dateInput.placeholder = 'Joined';

			label.appendChild(checkbox);
			if (icon) label.appendChild(icon);
			label.appendChild(nameSpan);
			label.appendChild(dateInput);
			container.appendChild(label);

			// Clear and disable buttons
			addGroupInput.value = '';
			if (addToCurrentBtn) addToCurrentBtn.disabled = true;
			if (addToHistoricalBtn) addToHistoricalBtn.disabled = true;
			selectedGroupForAdd = null;
		});
	}

	// Handle "Add to Historical" button
	if (addToHistoricalBtn) {
		addToHistoricalBtn.addEventListener('click', function(e) {
			e.preventDefault();
			if (!selectedGroupForAdd) return;

			const group = selectedGroupForAdd;
			const container = document.getElementById('historical-groups-container');
			const section = document.getElementById('historical-groups-section');
			const index = Date.now();

			// Show the historical section if it was hidden
			if (section) {
				section.style.display = '';
			}

			const row = document.createElement('div');
			row.className = 'historical-group-row';
			row.style.display = 'flex';
			row.style.alignItems = 'center';
			row.style.gap = '10px';
			row.style.padding = '8px';
			row.style.background = 'light-dark(#f5f5f5, #2a2a2a)';
			row.style.borderRadius = '8px';
			row.style.marginBottom = '8px';

			const hiddenInput = document.createElement('input');
			hiddenInput.type = 'hidden';
			hiddenInput.name = `historical_groups[${index}][group_id]`;
			hiddenInput.value = group.id;

			const icon = group.display_icon ? document.createElement('span') : null;
			if (icon) {
				icon.className = 'group-icon';
				icon.textContent = group.display_icon;
			}

			const nameSpan = document.createElement('span');
			nameSpan.className = 'group-name';
			nameSpan.style.minWidth = '150px';
			nameSpan.textContent = group.hierarchical_name;

			const joinedInput = document.createElement('input');
			joinedInput.type = 'date';
			joinedInput.name = `historical_groups[${index}][joined_date]`;
			joinedInput.style.width = '140px';
			joinedInput.style.fontSize = '0.9em';
			joinedInput.title = 'Date joined';
			joinedInput.placeholder = 'Joined';
			joinedInput.required = true;

			const arrow = document.createElement('span');
			arrow.textContent = '→';

			const leftInput = document.createElement('input');
			leftInput.type = 'date';
			leftInput.name = `historical_groups[${index}][left_date]`;
			leftInput.style.width = '140px';
			leftInput.style.fontSize = '0.9em';
			leftInput.title = 'Date left';
			leftInput.placeholder = 'Left';
			leftInput.required = true;

			const removeBtn = document.createElement('button');
			removeBtn.type = 'button';
			removeBtn.className = 'remove-historical-membership';
			removeBtn.style.marginLeft = 'auto';
			removeBtn.style.padding = '4px 8px';
			removeBtn.style.fontSize = '0.9em';
			removeBtn.textContent = 'Remove';

			row.appendChild(hiddenInput);
			if (icon) row.appendChild(icon);
			row.appendChild(nameSpan);
			row.appendChild(joinedInput);
			row.appendChild(arrow);
			row.appendChild(leftInput);
			row.appendChild(removeBtn);

			container.appendChild(row);

			// Clear and disable buttons
			addGroupInput.value = '';
			if (addToCurrentBtn) addToCurrentBtn.disabled = true;
			if (addToHistoricalBtn) addToHistoricalBtn.disabled = true;
			selectedGroupForAdd = null;
		});
	}

	// Handle removal of historical group memberships
	document.addEventListener('click', function(e) {
		if (e.target.classList.contains('remove-historical-membership')) {
			e.preventDefault();
			const row = e.target.closest('.historical-group-row');
			if (row && confirm('Remove this historical membership?')) {
				row.remove();

				// Hide the section if no more historical memberships
				const container = document.getElementById('historical-groups-container');
				const section = document.getElementById('historical-groups-section');
				if (container && section && container.children.length === 0) {
					section.style.display = 'none';
				}
			}
		}

		// Handle "Add to Current" for suggested groups
		if (e.target.classList.contains('add-suggested-to-current')) {
			e.preventDefault();
			const groupId = parseInt(e.target.getAttribute('data-group-id'));
			const group = groupsById[groupId];
			if (!group) return;

			// Check if already added
			const existing = document.querySelector(`#selected-groups-container label[data-group-id="${group.id}"]`);
			if (existing) {
				alert('This group is already in current memberships');
				return;
			}

			// Add to current memberships
			const container = document.getElementById('selected-groups-container');
			const label = document.createElement('label');
			label.className = 'group-checkbox-label selected';
			label.style.display = 'flex';
			label.style.alignItems = 'center';
			label.style.gap = '10px';
			label.setAttribute('data-group-id', group.id);

			const checkbox = document.createElement('input');
			checkbox.type = 'checkbox';
			checkbox.name = `groups[${group.id}][checked]`;
			checkbox.value = '1';
			checkbox.checked = true;

			const icon = group.display_icon ? document.createElement('span') : null;
			if (icon) {
				icon.className = 'group-icon';
				icon.textContent = group.display_icon;
			}

			const nameSpan = document.createElement('span');
			nameSpan.className = 'group-name';
			nameSpan.textContent = group.hierarchical_name;

			const dateInput = document.createElement('input');
			dateInput.type = 'date';
			dateInput.name = `groups[${group.id}][joined_date]`;
			dateInput.style.marginLeft = 'auto';
			dateInput.style.width = '140px';
			dateInput.style.fontSize = '0.9em';
			dateInput.title = 'Date joined this group (optional)';
			dateInput.placeholder = 'Joined';

			label.appendChild(checkbox);
			if (icon) label.appendChild(icon);
			label.appendChild(nameSpan);
			label.appendChild(dateInput);
			container.appendChild(label);

			// Remove the suggested group row
			const suggestedRow = e.target.closest('.suggested-group-row');
			if (suggestedRow) suggestedRow.remove();
		}

		// Handle "Add to Historical" for suggested groups
		if (e.target.classList.contains('add-suggested-to-historical')) {
			e.preventDefault();
			const groupId = parseInt(e.target.getAttribute('data-group-id'));
			const group = groupsById[groupId];
			if (!group) return;

			const container = document.getElementById('historical-groups-container');
			const section = document.getElementById('historical-groups-section');
			const index = Date.now();

			// Show the historical section if it was hidden
			if (section) {
				section.style.display = '';
			}

			const row = document.createElement('div');
			row.className = 'historical-group-row';
			row.style.display = 'flex';
			row.style.alignItems = 'center';
			row.style.gap = '10px';
			row.style.padding = '8px';
			row.style.background = 'light-dark(#f5f5f5, #2a2a2a)';
			row.style.borderRadius = '8px';
			row.style.marginBottom = '8px';

			const hiddenInput = document.createElement('input');
			hiddenInput.type = 'hidden';
			hiddenInput.name = `historical_groups[${index}][group_id]`;
			hiddenInput.value = group.id;

			const icon = group.display_icon ? document.createElement('span') : null;
			if (icon) {
				icon.className = 'group-icon';
				icon.textContent = group.display_icon;
			}

			const nameSpan = document.createElement('span');
			nameSpan.className = 'group-name';
			nameSpan.style.minWidth = '150px';
			nameSpan.textContent = group.hierarchical_name;

			const joinedInput = document.createElement('input');
			joinedInput.type = 'date';
			joinedInput.name = `historical_groups[${index}][joined_date]`;
			joinedInput.style.width = '140px';
			joinedInput.style.fontSize = '0.9em';
			joinedInput.title = 'Date joined';
			joinedInput.placeholder = 'Joined';
			joinedInput.required = true;

			const arrow = document.createElement('span');
			arrow.textContent = '→';

			const leftInput = document.createElement('input');
			leftInput.type = 'date';
			leftInput.name = `historical_groups[${index}][left_date]`;
			leftInput.style.width = '140px';
			leftInput.style.fontSize = '0.9em';
			leftInput.title = 'Date left';
			leftInput.placeholder = 'Left';
			leftInput.required = true;

			const removeBtn = document.createElement('button');
			removeBtn.type = 'button';
			removeBtn.className = 'remove-historical-membership';
			removeBtn.style.marginLeft = 'auto';
			removeBtn.style.padding = '4px 8px';
			removeBtn.style.fontSize = '0.9em';
			removeBtn.textContent = 'Remove';

			row.appendChild(hiddenInput);
			if (icon) row.appendChild(icon);
			row.appendChild(nameSpan);
			row.appendChild(joinedInput);
			row.appendChild(arrow);
			row.appendChild(leftInput);
			row.appendChild(removeBtn);

			container.appendChild(row);

			// Remove the suggested group row
			const suggestedRow = e.target.closest('.suggested-group-row');
			if (suggestedRow) suggestedRow.remove();
		}
	});

});
