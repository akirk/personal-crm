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

// Simple time zone display functionality for overview pages (no "your time" comparison)
function createSimpleTimeUpdater(timezone, personId) {
    function updateTime() {
        const timeElement = document.getElementById(`time-${personId}`);
        
        if (!timeElement) return;
        
        if (!timezone) {
            timeElement.textContent = '🕒 No timezone';
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
            
            // Get the hour to determine if it's after hours
            const personHour = parseInt(personTime.split(':')[0], 10);
            const isAfterHours = personHour < 8 || personHour >= 17;
            
            // Use different icons based on time of day
            let timeIcon, timeColor;
            if (isAfterHours) {
                timeIcon = '🌙'; // Moon for after hours
                timeColor = '#999'; // Gray for after hours
            } else {
                timeIcon = '🕒'; // Regular clock for work hours
                timeColor = '#666'; // Normal color
            }
            
            timeElement.textContent = `${timeIcon} ${personTime}`;
            timeElement.style.color = timeColor;
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