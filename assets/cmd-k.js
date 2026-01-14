/**
 * Command-K Panel functionality
 * Provides quick team stats, team browsing, and person search across team management pages
 */
window.CmdK = {
    isOpen: false,
    selectedIndex: 0,
    selectedLinkIndex: -1,
    filteredItems: [],
    teams: [],
    searchIndex: [],
    pages: [],
    privacyMode: false,
    searchTimeout: null,
    detailsTimeout: null,
    currentSearchQuery: '',
    personDetailsCache: {},

    init(teams, searchIndex, ajaxUrl = '', baseUrl = '', pages = []) {
        this.teams = teams || [];
        this.searchIndex = searchIndex || [];
        this.pages = pages || [];
        this.ajaxUrl = ajaxUrl;
        this.baseUrl = baseUrl || '/crm/';
        this.bindEvents();
    },

    search(query) {
        if (this.searchTimeout) {
            clearTimeout(this.searchTimeout);
        }

        if (!query.trim()) {
            this.currentSearchQuery = '';
            const pageItems = this.pages.map(page => ({
                name: page.name,
                icon: page.icon || '📄',
                itemType: 'page',
                url: page.url
            }));
            const teamItems = this.teams.map(team => ({
                slug: team.slug,
                name: team.name,
                team_members: team.team_members,
                leadership: team.leadership,
                alumni: team.alumni,
                total_people: team.total_people,
                itemType: 'team',
                url: team.url
            }));
            this.filteredItems = [...pageItems, ...teamItems];
            this.selectedIndex = 0;
            this.selectedLinkIndex = -1;
            this.renderResults();
            return;
        }

        // Debounce search - 50ms for instant feel
        this.searchTimeout = setTimeout(() => {
            this.currentSearchQuery = query;
            const searchLower = query.toLowerCase();

            // Search pages
            const matchingPages = this.pages
                .filter(page => page.name.toLowerCase().includes(searchLower))
                .map(page => ({
                    name: page.name,
                    icon: page.icon || '📄',
                    itemType: 'page',
                    url: page.url
                }));

            // Search teams
            const matchingTeams = this.teams
                .filter(team => team.name.toLowerCase().includes(searchLower))
                .map(team => ({
                    slug: team.slug,
                    name: team.name,
                    team_members: team.team_members,
                    leadership: team.leadership,
                    alumni: team.alumni,
                    total_people: team.total_people,
                    itemType: 'team',
                    url: team.url
                }));

            // Search people in index
            const matchingPeople = this.searchIndex.filter(person => {
                const searchText = `${person.name} ${person.nickname} ${person.username}`.toLowerCase();
                return searchText.includes(searchLower);
            }).map(person => ({
                username: person.username,
                name: person.name,
                nickname: person.nickname,
                type: person.type,
                team: person.team_name,
                team_slug: person.team_slug,
                role: person.role || '',
                location: person.location || '',
                itemType: 'person',
                url: person.url,
                detailsLoading: false
            }));

            this.filteredItems = [...matchingPages, ...matchingTeams, ...matchingPeople];
            this.selectedIndex = 0;
            this.selectedLinkIndex = -1;
            this.renderResults();

            // Debounce lazy-load details for people
            if (this.detailsTimeout) {
                clearTimeout(this.detailsTimeout);
            }

            this.detailsTimeout = setTimeout(() => {
                this.loadPersonDetails(matchingPeople);
            }, 300);
        }, 50);
    },

    async loadPersonDetails(people) {
        if (people.length === 0) return;

        const detailsPromises = people.map(async (person) => {
            const cacheKey = `${person.team_slug}:${person.username}`;

            // Check cache first
            if (this.personDetailsCache[cacheKey]) {
                return { person, details: this.personDetailsCache[cacheKey] };
            }

            try {
                const response = await fetch(
                    this.ajaxUrl + '?action=personal_crm_get_person_details&username=' +
                    encodeURIComponent(person.username) + '&team=' + encodeURIComponent(person.team_slug)
                );

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const result = await response.json();
                if (!result.success) {
                    throw new Error('Failed to load details');
                }

                // Cache the details
                this.personDetailsCache[cacheKey] = result.data;
                return { person, details: result.data };
            } catch (error) {
                console.error(`Error loading details for ${person.username}:`, error);
                return { person, details: null };
            }
        });

        const results = await Promise.all(detailsPromises);

        // Update filteredItems with loaded details
        results.forEach(({ person, details }) => {
            if (details) {
                const index = this.filteredItems.findIndex(
                    item => item.itemType === 'person' &&
                           item.username === person.username &&
                           item.team_slug === person.team_slug
                );

                if (index !== -1) {
                    this.filteredItems[index] = {
                        ...this.filteredItems[index],
                        role: details.role,
                        location: details.location,
                        birthday: this.getBirthdayDisplay({ birthday: details.birthday }),
                        links: this.processLinks(details.links, details.linear),
                        detailsLoading: false
                    };
                }
            }
        });

        this.renderResults();
    },

    processLinks(links, linear) {
        const processed = [];

        if (links) {
            Object.entries(links).forEach(([text, url]) => {
                if (url) {
                    processed.push({ text, url });
                }
            });
        }

        if (linear) {
            processed.push({
                text: 'Linear',
                url: `https://linear.app/a8c/profiles/${linear}`
            });
        }

        return processed;
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
            name: personData.name || '',
            nickname: personData.nickname || '',
            role: personData.role || '',
            type: type,
            team: teamName,
            team_slug: teamSlug,
            location: personData.location || '',
            birthday: this.getBirthdayDisplay(personData),
            links: links,
            url: this.buildPersonUrl(teamSlug, username)
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

    buildTeamUrl(page, additionalParams = {}, teamSlug = null) {
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
        const url = this.baseUrl + (page !== 'index.php' ? page : '');
        return queryString ? `${url}?${queryString}` : url;
    },

    buildPersonUrl(teamSlug, username) {
        // Use the route format: /crm/person/{person}
        return `${this.baseUrl}person/${username}`;
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

        // Handle clicks on result items
        const resultsContainer = document.getElementById('cmd-k-results');
        if (resultsContainer) {
            resultsContainer.addEventListener('click', (e) => {
                const item = e.target.closest('.cmd-k-item');
                if (item) {
                    const index = parseInt(item.dataset.index, 10);
                    if (!isNaN(index)) {
                        this.selectedIndex = index;
                        this.selectedLinkIndex = -1;
                        this.selectCurrent();
                    }
                }
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
        this.currentSearchQuery = '';

        const overlay = document.getElementById('cmd-k-overlay');
        const searchInput = document.getElementById('cmd-k-search');

        if (overlay) {
            overlay.classList.add('show');
        }
        if (searchInput) {
            searchInput.focus();
        }
        this.updatePlaceholder();

        // Show pages and teams by default (instant - no loading)
        const pageItems = this.pages.map(page => ({
            name: page.name,
            icon: page.icon || '📄',
            itemType: 'page',
            url: page.url
        }));
        const teamItems = this.teams.map(team => ({
            slug: team.slug,
            name: team.name,
            team_members: team.team_members,
            leadership: team.leadership,
            alumni: team.alumni,
            total_people: team.total_people,
            itemType: 'team',
            url: team.url
        }));
        this.filteredItems = [...pageItems, ...teamItems];
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

        if (this.searchTimeout) {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = null;
        }

        if (this.detailsTimeout) {
            clearTimeout(this.detailsTimeout);
            this.detailsTimeout = null;
        }

        this.currentSearchQuery = '';
        const closedPageItems = this.pages.map(page => ({
            name: page.name,
            icon: page.icon || '📄',
            itemType: 'page',
            url: page.url
        }));
        const closedTeamItems = this.teams.map(team => ({
            slug: team.slug,
            name: team.name,
            team_members: team.team_members,
            leadership: team.leadership,
            alumni: team.alumni,
            total_people: team.total_people,
            itemType: 'team',
            url: team.url
        }));
        this.filteredItems = [...closedPageItems, ...closedTeamItems];
    },

    updatePlaceholder() {
        const searchInput = document.getElementById('cmd-k-search');
        if (searchInput) {
            searchInput.placeholder = 'Search teams and people...';
        }
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

        if (currentItem.itemType === 'person' && this.selectedLinkIndex >= 0 && currentItem.links && currentItem.links[this.selectedLinkIndex]) {
            window.open(currentItem.links[this.selectedLinkIndex].url, '_blank');
        } else {
            window.location.href = currentItem.url;
        }
        this.close();
    },

    renderResults() {
        const resultsContainer = document.getElementById('cmd-k-results');
        if (!resultsContainer) return;

        if (this.filteredItems.length === 0) {
            resultsContainer.innerHTML = `<div class="cmd-k-no-results">No results found</div>`;
            return;
        }

        let html = this.filteredItems.map((item, index) => {
            const isSelected = index === this.selectedIndex;

            if (item.itemType === 'page' || item.itemType === 'team') {
                const icon = item.icon || '🏢';
                const statsHtml = item.itemType === 'team' ? `<div class="cmd-k-item-stats">${item.total_people || 0} people</div>` : '';
                const breakdownHtml = item.itemType === 'team' ? `
                        <div class="cmd-k-team-breakdown">
                            ${item.team_members > 0 ? `<span class="cmd-k-stat">👥 ${item.team_members} members</span>` : ''}
                            ${item.leadership > 0 ? `<span class="cmd-k-stat">👑 ${item.leadership} leaders</span>` : ''}
                            ${item.alumni > 0 ? `<span class="cmd-k-stat">🎓 ${item.alumni} alumni</span>` : ''}
                        </div>` : '';
                return `
                    <div class="cmd-k-item cmd-k-${item.itemType} ${isSelected ? 'selected' : ''}" data-index="${index}">
                        <div class="cmd-k-item-header">
                            <div class="cmd-k-item-name">
                                <span class="cmd-k-item-icon">${icon}</span>
                                ${this.escapeHtml(item.name)}
                                ${item.is_default ? '<span class="cmd-k-default-badge">Default</span>' : ''}
                            </div>
                            ${statsHtml}
                        </div>
                        ${breakdownHtml}
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

        resultsContainer.innerHTML = html;

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