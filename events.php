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
    <title><?php echo htmlspecialchars( $team_data['team_name'] ); ?> Events</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/cmd-k.css">
</head>
<body>
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
            <div style="flex-grow: 1;">
                <h1><a href="<?php echo build_team_url( 'index.php' ); ?>" style="color: inherit; text-decoration: none;"><?php echo htmlspecialchars( $team_data['team_name'] ); ?> Events</a></h1>
                <p style="color: #666;">All team events, meetings, and activities</p>
            </div>
            <div class="navigation" style="display: flex; align-items: center; gap: 10px;">
                <!-- Team Switcher -->
                <select id="team-selector" onchange="switchTeam()" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; background: white;">
                    <?php
                    foreach ( $available_teams as $team_slug ) {
                        $team_display_name = get_team_name_from_file( $team_slug );
                        $selected = $team_slug === $current_team ? 'selected' : '';
                        echo '<option value="' . htmlspecialchars( $team_slug ) . '" ' . $selected . '>' . htmlspecialchars( $team_display_name ) . '</option>';
                    }
                    ?>
                </select>
                
                <a href="<?php echo build_team_url( 'admin.php', array( 'tab' => 'events', 'add' => 'new' ) ); ?>" class="nav-link" style="background: #28a745;">+ Add Event</a>
                <a href="<?php echo build_team_url( 'index.php' ); ?>" class="nav-link">← Back to Team</a>
            </div>
        </div>

        <!-- Upcoming Events Section -->
        <div class="section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="margin: 0;">📅 Upcoming Events</h2>
                <?php if ( ! empty( $all_upcoming_events ) ) : ?>
                    <span style="color: #666; font-size: 14px;"><?php echo count( $all_upcoming_events ); ?> event<?php echo count( $all_upcoming_events ) !== 1 ? 's' : ''; ?></span>
                <?php endif; ?>
            </div>

            <?php if ( ! empty( $upcoming_by_month ) ) : ?>
                <div style="display: grid; gap: 20px;">
                    <?php foreach ( $upcoming_by_month as $month_data ) : ?>
                        <div style="background: white; border: 1px solid #ddd; border-radius: 8px; padding: 20px;">
                            <h3 style="margin: 0 0 15px 0; color: #333; border-bottom: 2px solid #007cba; padding-bottom: 8px;"><?php echo htmlspecialchars( $month_data['label'] ); ?></h3>
                            <div style="display: grid; gap: 12px;">
                                <?php foreach ( $month_data['events'] as $event ) : ?>
                                    <div class="event-row" style="display: flex; align-items: flex-start; gap: 15px; padding: 12px; background: #f8f9fa; border-radius: 6px; border-left: 4px solid <?php echo $event->get_color(); ?>;">
                                        <div style="flex: 0 0 80px; font-size: 14px; color: #666; font-weight: 500;">
                                            <?php echo $event->date->format( 'M j' ); ?>
                                        </div>
                                        <div style="flex: 1;">
                                            <div style="font-weight: 600; color: #333; margin-bottom: 4px;">
                                                <?php 
                                                // For events with a person, link to the person
                                                if ( $event->has_person() && in_array( $event->type, array( 'birthday', 'anniversary' ) ) ) {
                                                	echo '<a href="' . build_team_url( 'index.php', array( 'person' => $event->person->username ) ) . '" style="color: #007cba; text-decoration: none;">' . htmlspecialchars( $event->get_title() ) . '</a>';
                                                } else {
                                            		echo htmlspecialchars( $event->get_title() );
                                                }
                                                ?>
                                                <span style="background: <?php echo $event->get_color(); ?>; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px; margin-left: 8px;">
                                                    <?php echo ucfirst( $event->type ); ?>
                                                </span>
                                            </div>
                                            <?php if ( ! empty( $event->location ) ) : ?>
                                                <div style="font-size: 13px; color: #666; margin-bottom: 4px;">📍 <?php echo htmlspecialchars( $event->location ); ?></div>
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
                                                    <div style="font-size: 12px; margin-top: 4px;">
                                                        <a href="<?php echo build_team_url( 'admin.php', array( 'tab' => 'events', 'edit_event' => $event_index ) ); ?>" 
                                                           style="color: #666; text-decoration: none; font-size: 11px;">
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
                <div style="text-align: center; padding: 40px; background: #f8f9fa; border-radius: 8px; color: #666;">
                    <h3 style="margin-bottom: 10px;">No upcoming events</h3>
                    <p>There are no scheduled events for this team.</p>
                    <a href="<?php echo build_team_url( 'admin.php', array( 'tab' => 'events' ) ); ?>" class="nav-link" style="margin-top: 15px; display: inline-block;">
                        Add an Event →
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Past Events Section (Team Events Only, Last 3 Years) -->
        <?php if ( ! empty( $past_by_month ) ) : ?>
            <div class="section" style="margin-top: 40px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2 style="margin: 0;">📋 Past Team Events</h2>
                    <span style="color: #666; font-size: 14px;"><?php echo count( $past_team_events ); ?> event<?php echo count( $past_team_events ) !== 1 ? 's' : ''; ?> (last 3 years)</span>
                </div>

                <div style="display: grid; gap: 20px;">
                    <?php foreach ( $past_by_month as $month_data ) : ?>
                        <div style="background: white; border: 1px solid #ddd; border-radius: 8px; padding: 20px; opacity: 0.85;">
                            <h3 style="margin: 0 0 15px 0; color: #666; border-bottom: 2px solid #999; padding-bottom: 8px;"><?php echo htmlspecialchars( $month_data['label'] ); ?></h3>
                            <div style="display: grid; gap: 12px;">
                                <?php foreach ( $month_data['events'] as $event ) : ?>
                                    <div class="event-row" style="display: flex; align-items: flex-start; gap: 15px; padding: 12px; background: #f1f1f1; border-radius: 6px; border-left: 4px solid <?php echo $event->get_color(); ?>;">
                                        <div style="flex: 0 0 80px; font-size: 14px; color: #666; font-weight: 500;">
                                            <?php echo $event->date->format( 'M j' ); ?>
                                        </div>
                                        <div style="flex: 1;">
                                            <div style="font-weight: 600; color: #555; margin-bottom: 4px;">
                                                <?php echo htmlspecialchars( $event->description ); ?>
                                                <span style="background: <?php echo $event->get_color(); ?>; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px; margin-left: 8px;">
                                                    <?php echo ucfirst( $event->type ); ?>
                                                </span>
                                            </div>
                                            <?php if ( ! empty( $event->location ) ) : ?>
                                                <div style="font-size: 13px; color: #666; margin-bottom: 4px;">📍 <?php echo htmlspecialchars( $event->location ); ?></div>
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
        <footer style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee; text-align: center; font-size: 14px;">
            <?php if ( $privacy_mode ) : ?>
                <a href="?<?php echo http_build_query( array_merge( $_GET, array( 'privacy' => '0' ) ) ); ?>" style="color: #666; text-decoration: none; margin-right: 15px;">🔒 Privacy Mode ON</a>
            <?php else : ?>
                <a href="?<?php echo http_build_query( array_merge( $_GET, array( 'privacy' => '1' ) ) ); ?>" style="color: #666; text-decoration: none; margin-right: 15px;">🔓 Privacy Mode OFF</a>
            <?php endif; ?>
            <a href="<?php echo build_team_url( 'admin.php' ); ?>" style="color: #666; text-decoration: none;">⚙️ Admin Panel</a>
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
        });
        
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