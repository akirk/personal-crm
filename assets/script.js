// Team Management JavaScript functionality

// Team switching functionality
function switchTeam() {
    const selector = document.getElementById('team-selector');
    const selectedTeam = selector.value;
    const currentUrl = new URL(window.location);
    currentUrl.searchParams.set('team', selectedTeam);
    // Remove person parameter when switching teams
    currentUrl.searchParams.delete('person');
    window.location = currentUrl.toString();
}

// Time zone display functionality
function createTimeUpdater(timezone, personId) {
    function updateTime() {
        const timeElement = document.getElementById(`time-${personId}`);
        
        if (!timeElement) return;
        
        if (!timezone) {
            timeElement.textContent = '🕒 Timezone not set';
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
            
            let timeString = `🕒 ${personTime}`;
            if (personTime !== myTime) {
                timeString += ` (${myTime} your time)`;
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

// Initialize Command-K functionality
function initializeCommandK(peopleData, teamsData) {
    if (typeof CmdK !== 'undefined') {
        CmdK.init(peopleData, teamsData);
    }
}