// Team Management JavaScript functionality
function switchTeam() {
    const selector = document.getElementById('team-selector');
    const selectedTeam = selector.value;
    const currentUrl = new URL(window.location);
    currentUrl.searchParams.set('team', selectedTeam);
    // Remove person parameter when switching teams
    currentUrl.searchParams.delete('person');
    window.location = currentUrl.toString();
}

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
            let timeIcon;
            if (isAfterHours) {
                timeIcon = '🌙'; // Moon for after hours
            } else {
                timeIcon = '🕒'; // Regular clock for work hours
            }

            timeElement.textContent = `${timeIcon} ${personTime}`;
        } catch (e) {
            timeElement.textContent = '🕒 Invalid timezone';
        }
    }

    // Update time immediately and then every minute
    updateTime();
    setInterval(updateTime, 60000);
}

function initializeDarkMode() {
    const toggle = document.getElementById('dark-mode-toggle');
    const sunIcon = toggle.querySelector('.sun-icon');
    const moonIcon = toggle.querySelector('.moon-icon');
    const autoIcon = toggle.querySelector('.auto-icon');
    
    if (!toggle || !sunIcon || !moonIcon || !autoIcon) {
        return; // Exit if elements don't exist
    }
    
    const getSystemTheme = () => window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    
    function getCurrentTheme() {
        const storedTheme = localStorage.getItem('theme-override');
        if (storedTheme) {
            return storedTheme;
        }
        return getSystemTheme();
    }
    
    function updateDisplay(showSystemIcon = false) {
        const currentTheme = getCurrentTheme();
        const hasOverride = !!localStorage.getItem('theme-override');
        
        // Hide all icons first
        sunIcon.style.display = 'none';
        moonIcon.style.display = 'none';
        autoIcon.style.display = 'none';
        
        if (showSystemIcon && !hasOverride) {
            // Only show computer icon when explicitly requested AND in system mode
            autoIcon.style.display = 'block';
        } else {
            // Always show current theme icon (sun/moon) based on what theme is actually applied
            if (currentTheme === 'dark') {
                moonIcon.style.display = 'block';
            } else {
                sunIcon.style.display = 'block';
            }
        }
        
        // Apply the theme
        document.documentElement.style.colorScheme = currentTheme;
    }
    
    updateDisplay();
    
    toggle.addEventListener('click', () => {
        const systemTheme = getSystemTheme();
        const hasOverride = !!localStorage.getItem('theme-override');
        
        if (!hasOverride) {
            const oppositeOfSystem = systemTheme === 'dark' ? 'light' : 'dark';
            localStorage.setItem('theme-override', oppositeOfSystem);
            updateDisplay();
        } else {
            const storedOverride = localStorage.getItem('theme-override');
            const oppositeOfSystem = systemTheme === 'dark' ? 'light' : 'dark';
            
            if (storedOverride === oppositeOfSystem) {
                localStorage.setItem('theme-override', systemTheme);
                updateDisplay();
            } else {
                localStorage.removeItem('theme-override');
                updateDisplay(true); // Show computer icon
            }
        }
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