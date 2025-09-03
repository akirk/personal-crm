/**
 * Command-K Panel functionality
 * Provides quick team stats, team browsing, and person search across team management pages
 */
window.CmdK = {
    isOpen: false,
    selectedIndex: 0,
    selectedLinkIndex: -1,
    filteredItems: [],
    allPeople: [],
    teamStats: [],
    allItems: [], // Combined teams and people

    init(peopleData, teamsData) {
        this.allPeople = peopleData || [];
        this.teamStats = teamsData || [];
        
        // Combine teams and people into one searchable array
        this.allItems = [
            // Add teams first (they'll appear at the top)
            ...this.teamStats.map(team => ({
                ...team,
                itemType: 'team',
                searchText: team.name.toLowerCase()
            })),
            // Then add people
            ...this.allPeople.map(person => ({
                ...person,
                itemType: 'person',
                searchText: `${person.name} ${person.username} ${person.role} ${person.type} ${person.team}`.toLowerCase()
            }))
        ];
        
        // Start with just teams for the overview
        this.filteredItems = this.teamStats.map(team => ({
            ...team,
            itemType: 'team',
            searchText: team.name.toLowerCase()
        }));
        this.bindEvents();
    },

    bindEvents() {
        // Global keyboard shortcut
        document.addEventListener('keydown', (e) => {
            if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                e.preventDefault();
                this.toggle();
            }
            
            if (this.isOpen) {
                this.handleKeydown(e);
            }
        });

        // Search input
        const searchInput = document.getElementById('cmd-k-search');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                this.search(e.target.value);
            });
        }

        // Close on overlay click
        const overlay = document.getElementById('cmd-k-overlay');
        if (overlay) {
            overlay.addEventListener('click', (e) => {
                if (e.target.id === 'cmd-k-overlay') {
                    this.close();
                }
            });
        }

        // Prevent panel click from closing
        const panel = document.querySelector('.cmd-k-panel');
        if (panel) {
            panel.addEventListener('click', (e) => {
                e.stopPropagation();
            });
        }
    },

    toggle() {
        if (this.isOpen) {
            this.close();
        } else {
            this.open();
        }
    },

    open() {
        this.isOpen = true;
        this.selectedIndex = 0;
        this.selectedLinkIndex = -1;
        // Show team overview by default
        this.filteredItems = this.teamStats.map(team => ({
            ...team,
            itemType: 'team',
            searchText: team.name.toLowerCase()
        }));
        const overlay = document.getElementById('cmd-k-overlay');
        const searchInput = document.getElementById('cmd-k-search');
        
        if (overlay) {
            overlay.classList.add('show');
        }
        if (searchInput) {
            searchInput.focus();
        }
        this.updatePlaceholder();
        this.renderResults();
    },

    close() {
        this.isOpen = false;
        const overlay = document.getElementById('cmd-k-overlay');
        const searchInput = document.getElementById('cmd-k-search');
        
        if (overlay) {
            overlay.classList.remove('show');
        }
        if (searchInput) {
            searchInput.value = '';
        }
        // Reset to team overview
        this.filteredItems = this.teamStats.map(team => ({
            ...team,
            itemType: 'team',
            searchText: team.name.toLowerCase()
        }));
    },

    updatePlaceholder() {
        const searchInput = document.getElementById('cmd-k-search');
        if (searchInput) {
            searchInput.placeholder = 'Search teams and people...';
        }
    },

    search(query) {
        if (!query.trim()) {
            // Show team overview when no search query
            this.filteredItems = this.teamStats.map(team => ({
                ...team,
                itemType: 'team',
                searchText: team.name.toLowerCase()
            }));
        } else {
            // Search through all items when there's a query
            const searchTerm = query.toLowerCase();
            this.filteredItems = this.allItems.filter(item => 
                item.searchText.includes(searchTerm)
            );
        }
        this.selectedIndex = 0;
        this.selectedLinkIndex = -1;
        this.renderResults();
    },

    handleKeydown(e) {
        switch (e.key) {
            case 'Escape':
                e.preventDefault();
                this.close();
                break;
                
            case 'ArrowDown':
                e.preventDefault();
                const currentItem = this.filteredItems[this.selectedIndex];
                if (this.selectedLinkIndex >= 0 && currentItem && currentItem.itemType === 'person') {
                    // Navigate through links in people mode
                    if (this.selectedLinkIndex < currentItem.links.length - 1) {
                        this.selectedLinkIndex++;
                    }
                } else {
                    // Navigate through items
                    if (this.selectedIndex < this.filteredItems.length - 1) {
                        this.selectedIndex++;
                        this.selectedLinkIndex = -1; // Reset link selection when moving to new item
                    }
                }
                this.renderResults();
                break;
                
            case 'ArrowUp':
                e.preventDefault();
                const currentUpItem = this.filteredItems[this.selectedIndex];
                if (this.selectedLinkIndex >= 0 && currentUpItem && currentUpItem.itemType === 'person') {
                    // Navigate through links in people mode
                    if (this.selectedLinkIndex > 0) {
                        this.selectedLinkIndex--;
                    } else {
                        this.selectedLinkIndex = -1; // Back to person
                    }
                } else {
                    // Navigate through items
                    if (this.selectedIndex > 0) {
                        this.selectedIndex--;
                        this.selectedLinkIndex = -1; // Reset link selection when moving to new item
                    }
                }
                this.renderResults();
                break;
                
            case 'ArrowRight':
                e.preventDefault();
                const currentRightItem = this.filteredItems[this.selectedIndex];
                if (currentRightItem && currentRightItem.itemType === 'person' && currentRightItem.links && currentRightItem.links.length > 0 && this.selectedLinkIndex === -1) {
                    this.selectedLinkIndex = 0;
                    this.renderResults();
                }
                break;
                
            case 'ArrowLeft':
                e.preventDefault();
                if (this.selectedLinkIndex >= 0) {
                    this.selectedLinkIndex = -1;
                    this.renderResults();
                }
                break;
                
            case 'Enter':
                e.preventDefault();
                this.selectCurrent();
                break;
        }
    },

    selectCurrent() {
        const currentItem = this.filteredItems[this.selectedIndex];
        if (!currentItem) return;

        if (currentItem.itemType === 'team') {
            // Navigate to team page
            window.location.href = currentItem.url;
        } else if (currentItem.itemType === 'person') {
            if (this.selectedLinkIndex >= 0 && currentItem.links && currentItem.links[this.selectedLinkIndex]) {
                // Open the selected link
                window.open(currentItem.links[this.selectedLinkIndex].url, '_blank');
            } else {
                // Navigate to person page
                window.location.href = currentItem.url;
            }
        }
        this.close();
    },

    renderResults() {
        const resultsContainer = document.getElementById('cmd-k-results');
        if (!resultsContainer) return;
        
        if (this.filteredItems.length === 0) {
            resultsContainer.innerHTML = `<div class="cmd-k-no-results">No teams or people found</div>`;
            return;
        }

        resultsContainer.innerHTML = this.filteredItems.map((item, index) => {
            const isSelected = index === this.selectedIndex;
            
            if (item.itemType === 'team') {
                // Render team item
                return `
                    <div class="cmd-k-item cmd-k-team ${isSelected ? 'selected' : ''}" data-index="${index}">
                        <div class="cmd-k-item-header">
                            <div class="cmd-k-item-name">
                                <span class="cmd-k-item-icon">🏢</span>
                                ${this.escapeHtml(item.name)}
                                ${item.is_default ? '<span class="cmd-k-default-badge">Default</span>' : ''}
                            </div>
                            <div class="cmd-k-item-stats">${item.total_people} people</div>
                        </div>
                        <div class="cmd-k-team-breakdown">
                            ${item.team_members > 0 ? `<span class="cmd-k-stat">👥 ${item.team_members} members</span>` : ''}
                            ${item.leadership > 0 ? `<span class="cmd-k-stat">👑 ${item.leadership} leaders</span>` : ''}
                            ${item.alumni > 0 ? `<span class="cmd-k-stat">🎓 ${item.alumni} alumni</span>` : ''}
                        </div>
                    </div>
                `;
            } else if (item.itemType === 'person') {
                // Render person item
                const linksHtml = item.links && item.links.length > 0 ? 
                    `<div class="cmd-k-links">
                        ${item.links.map((link, linkIndex) => 
                            `<a href="${this.escapeHtml(link.url)}" class="cmd-k-link ${isSelected && linkIndex === this.selectedLinkIndex ? 'selected' : ''}" target="_blank">${this.escapeHtml(link.text)}</a>`
                        ).join('')}
                    </div>` : '';
                
                const detailsHtml = [
                    item.team ? `<div class="cmd-k-person-detail">🏢 ${this.escapeHtml(item.team)}</div>` : '',
                    item.location ? `<div class="cmd-k-person-detail">📍 ${this.escapeHtml(item.location)}</div>` : '',
                    item.birthday ? `<div class="cmd-k-person-detail">🎂 ${this.escapeHtml(item.birthday)}</div>` : ''
                ].filter(Boolean).join('');
                
                return `
                    <div class="cmd-k-item cmd-k-person ${isSelected ? 'selected' : ''}" data-index="${index}">
                        <div class="cmd-k-item-header">
                            <div class="cmd-k-item-name">
                                <span class="cmd-k-item-icon">👤</span>
                                ${this.escapeHtml(item.name)}
                            </div>
                            <div class="cmd-k-person-role">${this.escapeHtml(item.type)}${item.role ? ' • ' + this.escapeHtml(item.role) : ''}</div>
                        </div>
                        ${detailsHtml ? `<div class="cmd-k-person-info">${detailsHtml}</div>` : ''}
                        ${linksHtml}
                    </div>
                `;
            }
        }).join('');

        // Scroll selected item into view
        this.scrollSelectedIntoView();
    },

    scrollSelectedIntoView() {
        const selectedElement = document.querySelector('.cmd-k-item.selected');
        if (selectedElement) {
            selectedElement.scrollIntoView({ block: 'nearest' });
        }
    },

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};