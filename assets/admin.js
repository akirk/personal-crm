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

	const addGroupInput = document.getElementById('add-group-input');
	if (addGroupInput) {
		addGroupInput.addEventListener('change', function(e) {
			const selectedName = e.target.value.trim();

			if (!selectedName || !groupsByName[selectedName]) {
				return;
			}

			const group = groupsByName[selectedName];

			// Check if already added
			const existing = document.querySelector(`#selected-groups-container label[data-group-id="${group.id}"]`);
			if (existing) {
				// Just check the checkbox if it exists but is unchecked
				const checkbox = existing.querySelector('input[type="checkbox"]');
				if (checkbox && !checkbox.checked) {
					checkbox.checked = true;
				}
				e.target.value = '';
				return;
			}

			// Add the group
			const container = document.getElementById('selected-groups-container');

			const label = document.createElement('label');
			label.style.display = 'block';
			label.style.marginBottom = '8px';
			label.setAttribute('data-group-id', group.id);

			const checkbox = document.createElement('input');
			checkbox.type = 'checkbox';
			checkbox.name = 'group_ids[]';
			checkbox.value = group.id;
			checkbox.checked = true;

			const text = document.createTextNode(' ' + (group.display_icon ? group.display_icon + ' ' : '') + group.hierarchical_name);

			label.appendChild(checkbox);
			label.appendChild(text);
			container.appendChild(label);

			// Clear the input
			e.target.value = '';
		});
	}
});
