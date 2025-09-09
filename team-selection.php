<?php
/**
 * Team Selection Screen
 * 
 * Allows users to select which team they want to view when multiple teams exist
 */

// Include common functions
require_once __DIR__ . '/includes/common.php';

$available_teams = get_available_teams();

// If there's only one team or no teams, redirect appropriately
if ( empty( $available_teams ) ) {
	header( 'Location: admin.php?create_team=new' );
	exit;
} elseif ( count( $available_teams ) === 1 ) {
	header( 'Location: ./' );
	exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <title>Team Selection - Orbit Team Management</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <!-- Dark Mode Toggle -->
    <button id="dark-mode-toggle" type="button" aria-label="Toggle dark mode">
        <svg class="sun-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="5"></circle>
            <line x1="12" y1="1" x2="12" y2="3"></line>
            <line x1="12" y1="21" x2="12" y2="23"></line>
            <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
            <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
            <line x1="1" y1="12" x2="3" y2="12"></line>
            <line x1="21" y1="12" x2="23" y2="12"></line>
            <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
            <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
        </svg>
        <svg class="moon-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
        </svg>
    </button>

    <div class="team-selection-container">
        <div class="team-selection-header">
            <h1>Select a Team</h1>
            <p>Choose which team you'd like to view:</p>
        </div>

        <div class="team-grid">
            <?php foreach ( $available_teams as $team_slug ) : ?>
                <?php $team_name = get_team_name_from_file( $team_slug ); ?>
                <a href="index.php<?php if ( get_default_team() !== $team_slug ) echo '?team=' . urlencode( $team_slug ); ?>" class="team-card">
                    <h3><?php echo htmlspecialchars( $team_name ); ?></h3>
                    <p><?php echo htmlspecialchars( $team_slug ); ?>.json</p>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="admin-link-section">
            <a href="admin.php?create_team=new">⚙️ Create New Team</a>
        </div>
    </div>
    
    <script>
        // Dark mode functionality
        function initializeDarkMode() {
            const toggle = document.getElementById('dark-mode-toggle');
            const sunIcon = toggle.querySelector('.sun-icon');
            const moonIcon = toggle.querySelector('.moon-icon');
            
            // Get saved theme or default to system preference
            let currentTheme = localStorage.getItem('theme');
            if (!currentTheme) {
                currentTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            }
            
            function updateTheme(theme) {
                if (theme === 'dark') {
                    document.documentElement.style.colorScheme = 'dark';
                    sunIcon.style.display = 'block';
                    moonIcon.style.display = 'none';
                } else {
                    document.documentElement.style.colorScheme = 'light';
                    sunIcon.style.display = 'none';
                    moonIcon.style.display = 'block';
                }
                localStorage.setItem('theme', theme);
            }
            
            // Set initial theme
            updateTheme(currentTheme);
            
            // Toggle theme on click
            toggle.addEventListener('click', () => {
                const newTheme = currentTheme === 'light' ? 'dark' : 'light';
                currentTheme = newTheme;
                updateTheme(newTheme);
            });
            
            // Listen for system theme changes
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
                if (!localStorage.getItem('theme')) {
                    const systemTheme = e.matches ? 'dark' : 'light';
                    currentTheme = systemTheme;
                    updateTheme(systemTheme);
                }
            });
        }
        
        // Initialize when DOM is ready
        document.addEventListener('DOMContentLoaded', initializeDarkMode);
    </script>
    
</body>
</html>