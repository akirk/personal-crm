<?php
/**
 * Team Events Page
 * 
 * Display all events for the current team.
 */

// Include common functions and Person class
require_once __DIR__ . '/includes/common.php';
require_once __DIR__ . '/includes/person.php';

// Team redirection logic - handle cases where no team parameter is provided
if ( isset( $_GET['team'] ) ) {
	$current_team = $_GET['team'];
} else {
	$current_team = get_default_team();
	$available_teams = get_available_teams();
	if ( count( $available_teams ) > 1 && ! $current_team ) {
		header( 'Location: team-selection.php' );
		exit;
	}
}

// Get privacy mode
$privacy_mode = isset( $_GET['privacy'] ) && $_GET['privacy'] === '1';

// Load team configuration with Person objects
$team_data = load_team_config_with_objects( $current_team, $privacy_mode );

// Process team events only (for past events section)
$team_events = array();

// Separate team events into upcoming and past
$past_team_events = array_filter( $team_data['events'], function( $event ) {
	return $event->is_past();
});

usort( $past_team_events, function( $a, $b ) {
	return $b->date <=> $a->date;
});

// Get all upcoming events (personal + team) using shared function
$all_upcoming_events = get_upcoming_events_for_display( $team_data );

// Group upcoming events by month
$upcoming_by_month = array();
foreach ( $all_upcoming_events as $event ) {
	$month_key = $event->date->format( 'Y-m' );
	$month_label = $event->date->format( 'F Y' );
	if ( ! isset( $upcoming_by_month[ $month_key ] ) ) {
		$upcoming_by_month[ $month_key ] = array(
			'label' => $month_label,
			'events' => array()
		);
	}
	$upcoming_by_month[ $month_key ]['events'][] = $event;
}

// Group past team events by month
$past_by_month = array();
foreach ( $past_team_events as $event ) {
	$month_key = $event->date->format( 'Y-m' );
	$month_label = $event->date->format( 'F Y' );
	if ( ! isset( $past_by_month[ $month_key ] ) ) {
		$past_by_month[ $month_key ] = array(
			'label' => $month_label,
			'events' => array()
		);
	}
	$past_by_month[ $month_key ]['events'][] = $event;
}


// Get available teams for switcher
$available_teams = get_available_teams();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <title><?php echo htmlspecialchars( $team_data['team_name'] ); ?> Events</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/cmd-k.css">
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

    <!-- Command-K Panel -->
    <div id="cmd-k-overlay" class="cmd-k-overlay">
        <div class="cmd-k-panel">
            <div class="cmd-k-search-container">
                <input type="text" id="cmd-k-search" class="cmd-k-search" placeholder="Search teams and people..." autocomplete="off" spellcheck="false">
            </div>
            <div id="cmd-k-results" class="cmd-k-results">
                <!-- Results will be populated here -->
            </div>
            <div class="cmd-k-instructions">
                <span class="cmd-k-kbd">↑↓</span> to navigate • <span class="cmd-k-kbd">Enter</span> to open • <span class="cmd-k-kbd">→</span> to select link • <span class="cmd-k-kbd">Esc</span> to close
            </div>
        </div>
    </div>

    <div class="container">
        <div class="header">
            <div class="header-container">
                <h1><a href="<?php echo build_team_url( 'index.php' ); ?>" class="title-link"><?php echo htmlspecialchars( $team_data['team_name'] ); ?> Events</a></h1>
                <div class="back-nav">
                    <a href="<?php echo build_team_url( 'index.php' ); ?>">← Back to Team Overview</a>
                </div>
            </div>
            <div class="nav-container">
                <!-- Team Switcher -->
                <select id="team-selector" onchange="switchTeam()">
                    <?php
                    foreach ( $available_teams as $team_slug ) {
                        $team_display_name = get_team_name_from_file( $team_slug );
                        $selected = $team_slug === $current_team ? 'selected' : '';
                        echo '<option value="' . htmlspecialchars( $team_slug ) . '" ' . $selected . '>' . htmlspecialchars( $team_display_name ) . '</option>';
                    }
                    ?>
                </select>
                
                <a href="<?php echo build_team_url( 'admin.php', array( 'tab' => 'events', 'add' => 'new' ) ); ?>" class="nav-link green">+ Add Event</a>
            </div>
        </div>

        <!-- Upcoming Events Section -->
        <div class="section">
            <div class="section-header">
                <h2 class="section-title">📅 Upcoming Events</h2>
                <?php if ( ! empty( $all_upcoming_events ) ) : ?>
                    <span class="count-text"><?php echo count( $all_upcoming_events ); ?> event<?php echo count( $all_upcoming_events ) !== 1 ? 's' : ''; ?></span>
                <?php endif; ?>
            </div>

            <?php if ( ! empty( $upcoming_by_month ) ) : ?>
                <div class="content-grid">
                    <?php foreach ( $upcoming_by_month as $month_data ) : ?>
                        <div class="month-card">
                            <h3 class="month-heading"><?php echo htmlspecialchars( $month_data['label'] ); ?></h3>
                            <div class="events-month-grid">
                                <?php foreach ( $month_data['events'] as $event ) : ?>
                                    <div class="event-row" style="border-left: 4px solid <?php echo $event->get_color(); ?>;">
                                        <div class="event-date-col">
                                            <?php echo $event->date->format( 'M j' ); ?>
                                        </div>
                                        <div class="event-content-col">
                                            <div class="event-title">
                                                <?php 
                                                // For events with a person, link to the person
                                                if ( $event->has_person() && in_array( $event->type, array( 'birthday', 'anniversary' ) ) ) {
                                                	echo '<a href="' . build_team_url( 'index.php', array( 'person' => $event->person->username ) ) . '" class="event-person-link">' . htmlspecialchars( $event->get_title() ) . '</a>';
                                                } else {
                                            		echo htmlspecialchars( $event->get_title() );
                                                }
                                                ?>
                                                <span class="event-type <?php echo $event->type; ?>">
                                                    <?php echo ucfirst( $event->type ); ?>
                                                </span>
                                            </div>
                                            <?php if ( ! empty( $event->location ) ) : ?>
                                                <div class="event-location">📍 <?php echo htmlspecialchars( $event->location ); ?></div>
                                            <?php endif; ?>
                                            <?php if ( ! empty( $event->links ) ) : ?>
                                                <div style="font-size: 13px; margin-bottom: 4px;">
                                                    <?php foreach ( $event->links as $link_text => $link_url ) : ?>
                                                        <a href="<?php echo htmlspecialchars( $link_url ); ?>" target="_blank" 
                                                           style="color: #007cba; text-decoration: none; margin-right: 12px;">
                                                            <?php echo htmlspecialchars( $link_text ); ?> →
                                                        </a>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php 
                                            // Add edit link for team events (not personal events)
                                            if ( ! $event->has_person() ) {
                                                // Find the event index in the original events array for editing
                                                $event_index = null;
                                                foreach ( $team_data['events'] as $index => $team_event ) {
                                                    if ( $team_event->description === $event->description && 
                                                         $team_event->date->format( 'Y-m-d' ) === $event->date->format( 'Y-m-d' ) ) {
                                                        $event_index = $index;
                                                        break;
                                                    }
                                                }
                                                if ( $event_index !== null ) : ?>
                                                    <div class="event-edit-container">
                                                        <a href="<?php echo build_team_url( 'admin.php', array( 'tab' => 'events', 'edit_event' => $event_index ) ); ?>" 
                                                           class="event-edit-link">
                                                            ✏️ Edit
                                                        </a>
                                                    </div>
                                                <?php endif;
                                            }
                                            ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <div class="no-content-card">
                    <h3>No upcoming events</h3>
                    <p>There are no scheduled events for this team.</p>
                    <a href="<?php echo build_team_url( 'admin.php', array( 'tab' => 'events' ) ); ?>" class="nav-link inline">
                        Add an Event →
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Past Events Section (Team Events Only, Last 3 Years) -->
        <?php if ( ! empty( $past_by_month ) ) : ?>
            <div class="section" style="margin-top: 40px;">
                <div class="section-header">
                    <h2 class="section-title">📋 Past Team Events</h2>
                    <span class="count-text"><?php echo count( $past_team_events ); ?> event<?php echo count( $past_team_events ) !== 1 ? 's' : ''; ?> (last 3 years)</span>
                </div>

                <div class="content-grid">
                    <?php foreach ( $past_by_month as $month_data ) : ?>
                        <div class="past-month-card">
                            <h3 class="past-month-heading"><?php echo htmlspecialchars( $month_data['label'] ); ?></h3>
                            <div class="events-month-grid">
                                <?php foreach ( $month_data['events'] as $event ) : ?>
                                    <div class="past-event-row" style="border-left: 4px solid <?php echo $event->get_color(); ?>;">
                                        <div class="event-date-col">
                                            <?php echo $event->date->format( 'M j' ); ?>
                                        </div>
                                        <div class="event-content-col">
                                            <div class="event-title past">
                                                <?php echo htmlspecialchars( $event->description ); ?>
                                                <span class="event-type <?php echo $event->type; ?>">
                                                    <?php echo ucfirst( $event->type ); ?>
                                                </span>
                                            </div>
                                            <?php if ( ! empty( $event->location ) ) : ?>
                                                <div class="event-location">📍 <?php echo htmlspecialchars( $event->location ); ?></div>
                                            <?php endif; ?>
                                            <?php if ( ! empty( $event->links ) ) : ?>
                                                <div style="font-size: 13px; margin-bottom: 4px;">
                                                    <?php foreach ( $event->links as $link_text => $link_url ) : ?>
                                                        <a href="<?php echo htmlspecialchars( $link_url ); ?>" target="_blank" 
                                                           style="color: #007cba; text-decoration: none; margin-right: 12px;">
                                                            <?php echo htmlspecialchars( $link_text ); ?> →
                                                        </a>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php 
                                            // Add edit link for past team events
                                            $event_index = null;
                                            foreach ( $team_data['events'] as $index => $team_event ) {
                                                if ( $team_event->description === $event->description && 
                                                     $team_event->date->format( 'Y-m-d' ) === $event->date->format( 'Y-m-d' ) ) {
                                                    $event_index = $index;
                                                    break;
                                                }
                                            }
                                            if ( $event_index !== null ) : ?>
                                                <div style="font-size: 12px; margin-top: 4px;">
                                                    <a href="<?php echo build_team_url( 'admin.php', array( 'tab' => 'events', 'edit_event' => $event_index ) ); ?>" 
                                                       style="color: #666; text-decoration: none; font-size: 11px;">
                                                        ✏️ Edit
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Footer with admin/privacy links -->
        <footer class="privacy-footer">
            <?php if ( $privacy_mode ) : ?>
                <a href="?<?php echo http_build_query( array_merge( $_GET, array( 'privacy' => '0' ) ) ); ?>">🔒 Privacy Mode ON</a>
            <?php else : ?>
                <a href="?<?php echo http_build_query( array_merge( $_GET, array( 'privacy' => '1' ) ) ); ?>">🔓 Privacy Mode OFF</a>
            <?php endif; ?>
            <a href="<?php echo build_team_url( 'admin.php' ); ?>">⚙️ Admin Panel</a>
        </footer>
    </div>
    
    <script src="assets/cmd-k.js"></script>
    <script src="assets/script.js"></script>
    <script>
        // Initialize functionality when DOM is ready
        document.addEventListener('DOMContentLoaded', () => {
            // Initialize Command-K with data
            const peopleData = <?php echo json_encode( get_all_people_from_all_teams( $privacy_mode ) ); ?>;
            const teamsData = <?php echo json_encode( get_all_teams_stats() ); ?>;
            initializeCommandK(peopleData, teamsData);
            
            // Initialize dark mode
            initializeDarkMode();
        });
        
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
        
        // Team switching functionality
        function switchTeam() {
            const selector = document.getElementById('team-selector');
            const selectedTeam = selector.value;
            const currentUrl = new URL(window.location);
            currentUrl.searchParams.set('team', selectedTeam);
            window.location = currentUrl.toString();
        }
    </script>
</body>
</html>