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
    jsonFiles: [], // List of available JSON files
    dataLoaded: false,
    privacyMode: false,
    loadingError: null,

    init(jsonFiles, privacyMode = false) {
        this.jsonFiles = jsonFiles || [];
        this.privacyMode = privacyMode;
        this.bindEvents();
    },

    async loadData() {
        if (this.dataLoaded) {
            return;
        }

        try {
            // Load all JSON files in parallel
            const jsonPromises = this.jsonFiles.map(async (file) => {
                try {
                    const response = await fetch(file.slug + '.json');
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    
                    const text = await response.text();
                    let data;
                    try {
                        data = JSON.parse(text);
                    } catch (parseError) {
                        console.error(`JSON parse error in ${file.slug}.json:`, parseError.message);
                        console.error('Raw content preview:', text.substring(0, 200) + '...');
                        throw new Error(`Invalid JSON in ${file.slug}.json: ${parseError.message}`);
                    }
                    
                    return {
                        slug: file.slug,
                        name: file.name,
                        data: data
                    };
                } catch (fileError) {
                    console.error(`Error loading ${file.slug}.json:`, fileError);
                    throw fileError;
                }
            });

            const jsonResults = await Promise.all(jsonPromises);

            // Process team stats
            this.teamStats = jsonResults.map(result => {
                const config = result.data;
                const teamMembersCount = Object.keys(config.team_members || {}).length;
                const leadershipCount = Object.keys(config.leadership || {}).length;
                const alumniCount = Object.keys(config.alumni || {}).length;
                const totalPeople = teamMembersCount + leadershipCount + alumniCount;

                return {
                    slug: result.slug,
                    name: result.name,
                    team_members: teamMembersCount,
                    leadership: leadershipCount,
                    alumni: alumniCount,
                    total_people: totalPeople,
                    is_default: config.default || false,
                    url: this.buildTeamUrl('index.php', {}, result.slug)
                };
            });

            // Process all people
            this.allPeople = [];
            jsonResults.forEach(result => {
                const config = result.data;
                const teamName = result.name;
                const teamSlug = result.slug;

                // Process team members
                Object.entries(config.team_members || {}).forEach(([username, memberData]) => {
                    this.allPeople.push(this.processPerson(username, memberData, 'Team Member', teamName, teamSlug));
                });

                // Process leadership
                Object.entries(config.leadership || {}).forEach(([username, leaderData]) => {
                    this.allPeople.push(this.processPerson(username, leaderData, 'Leadership', teamName, teamSlug));
                });

                // Process alumni
                Object.entries(config.alumni || {}).forEach(([username, alumniData]) => {
                    this.allPeople.push(this.processPerson(username, alumniData, 'Alumni', teamName, teamSlug));
                });
            });

            // Sort people by name
            this.allPeople.sort((a, b) => a.name.localeCompare(b.name));

            // Sort teams by name
            this.teamStats.sort((a, b) => a.name.localeCompare(b.name));

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
                    searchText: `${person.name} ${person.nickname || ''} ${person.username} ${person.role} ${person.type} ${person.team}`.toLowerCase()
                }))
            ];

            this.dataLoaded = true;
        } catch (error) {
            console.error('Failed to load cmd-k data:', error);
            // Set error state for UI display
            this.loadingError = error.message || 'Unknown error occurred';
        }
    },

    processPerson(username, personData, type, teamName, teamSlug) {
        const links = [];
        
        // Process links
        Object.entries(personData.links || {}).forEach(([text, url]) => {
            if (url) {
                links.push({ text, url });
            }
        });

        // Add Linear link if available
        if (personData.linear) {
            links.push({
                text: 'Linear',
                url: `https://linear.app/a8c/profiles/${personData.linear}`
            });
        }

        return {
            username: username,
            name: this.privacyMode ? this.maskName(personData.name || '') : (personData.name || ''),
            nickname: this.privacyMode ? '' : (personData.nickname || ''),
            role: personData.role || '',
            type: type,
            team: teamName,
            team_slug: teamSlug,
            location: personData.location || '',
            birthday: this.getBirthdayDisplay(personData),
            links: links,
            url: this.buildTeamUrl('index.php', {
                person: username,
                privacy: this.privacyMode ? '1' : '0'
            }, teamSlug)
        };
    },

    maskName(fullName) {
        if (!this.privacyMode || !fullName) {
            return fullName;
        }
        
        const parts = fullName.trim().split(' ');
        if (parts.length <= 1) {
            return fullName;
        }

        const firstName = parts[0];
        const lastNameInitial = parts[parts.length - 1].charAt(0) + '.';
        return firstName + ' ' + lastNameInitial;
    },

    getBirthdayDisplay(personData) {
        if (!personData.birthday) {
            return '';
        }

        if (this.privacyMode) {
            // For privacy mode, show age if available
            if (/^\d{4}-\d{2}-\d{2}$/.test(personData.birthday)) {
                const birthDate = new Date(personData.birthday);
                const currentDate = new Date();
                const age = currentDate.getFullYear() - birthDate.getFullYear();
                return 'Age ' + age;
            }
            return '[Hidden]';
        }

        // For non-privacy mode, return formatted display
        if (/^\d{4}-\d{2}-\d{2}$/.test(personData.birthday)) {
            const birthDate = new Date(personData.birthday);
            return birthDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        } else if (/^\d{2}-\d{2}$/.test(personData.birthday)) {
            const [month, day] = personData.birthday.split('-');
            const displayDate = new Date(2000, parseInt(month) - 1, parseInt(day));
            return displayDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        }

        return personData.birthday;
    },

    buildTeamUrl(baseUrl, additionalParams = {}, teamSlug = null) {
        const params = new URLSearchParams();

        if (teamSlug && teamSlug !== 'team') {
            params.set('team', teamSlug);
        }

        Object.entries(additionalParams).forEach(([key, value]) => {
            if (value) {
                params.set(key, value);
            }
        });

        const queryString = params.toString();
        return queryString ? `${baseUrl}?${queryString}` : baseUrl;
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

    async open() {
        this.isOpen = true;
        this.selectedIndex = 0;
        this.selectedLinkIndex = -1;

        const overlay = document.getElementById('cmd-k-overlay');
        const searchInput = document.getElementById('cmd-k-search');
        
        if (overlay) {
            overlay.classList.add('show');
        }
        if (searchInput) {
            searchInput.focus();
        }
        this.updatePlaceholder();

        // Show loading state
        this.filteredItems = [];
        this.renderResults();

        // Load data if not already loaded
        await this.loadData();

        // Show team overview by default
        this.filteredItems = this.teamStats.map(team => ({
            ...team,
            itemType: 'team',
            searchText: team.name.toLowerCase()
        }));
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
        
        if (this.loadingError) {
            resultsContainer.innerHTML = `<div class="cmd-k-error">
                <div style="color: #ef4444; font-weight: bold;">Failed to load data</div>
                <div style="font-size: 12px; margin-top: 8px; color: #6b7280;">${this.escapeHtml(this.loadingError)}</div>
                <div style="font-size: 11px; margin-top: 4px; color: #9ca3af;">Check console for details</div>
            </div>`;
            return;
        }
        
        if (!this.dataLoaded && this.filteredItems.length === 0) {
            resultsContainer.innerHTML = `<div class="cmd-k-loading">Loading data...</div>`;
            return;
        }

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
                                ${this.escapeHtml(item.name)}${item.nickname ? ' "' + this.escapeHtml(item.nickname) + '"' : ''}
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