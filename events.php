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
$all_teams_mode = false;
$current_team = get_current_team_from_params();
if ( $current_team ) {
	$all_teams_mode = $current_team === 'all-teams';
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

// Get calendar view parameters
$view_mode = isset( $_GET['view'] ) ? $_GET['view'] : 'list';
$calendar_month = isset( $_GET['month'] ) ? intval( $_GET['month'] ) : date( 'n' );
$calendar_year = isset( $_GET['year'] ) ? intval( $_GET['year'] ) : date( 'Y' );

// Validate view mode
if ( ! in_array( $view_mode, array( 'list', 'month-monday', 'month-sunday' ) ) ) {
    $view_mode = 'list';
}

// Validate calendar month/year
if ( $calendar_month < 1 || $calendar_month > 12 ) {
    $calendar_month = date( 'n' );
}
if ( $calendar_year < 2000 || $calendar_year > 2100 ) {
    $calendar_year = date( 'Y' );
}

// Load team configuration with Person objects
if ( $all_teams_mode ) {
	// Load events from all teams
	$all_teams_data = array();
	$available_teams = get_available_teams();
	foreach ( $available_teams as $team_slug ) {
		$all_teams_data[$team_slug] = load_team_config_with_objects( $team_slug, $privacy_mode );
	}
	// Create a combined team_data structure
	$team_data = array(
		'team_name' => 'All Teams',
		'events' => array(),
		'people' => array(),
		'team_members' => array(),
		'leadership' => array(),
		'alumni' => array()
	);
	
	// Combine all events and people from all teams
	foreach ( $all_teams_data as $team_slug => $data ) {
		$team_data['events'] = array_merge( $team_data['events'], $data['events'] ?? array() );
		$team_data['people'] = array_merge( $team_data['people'], $data['people'] ?? array() );
		$team_data['team_members'] = array_merge( $team_data['team_members'], $data['team_members'] ?? array() );
		$team_data['leadership'] = array_merge( $team_data['leadership'], $data['leadership'] ?? array() );
		$team_data['alumni'] = array_merge( $team_data['alumni'], $data['alumni'] ?? array() );
	}
} else {
	$team_data = load_team_config_with_objects( $current_team, $privacy_mode );
}

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

// Calendar helper functions
function build_calendar_url( $params = array() ) {
    global $current_team, $all_teams_mode;
    $base_params = array( 'team' => $all_teams_mode ? 'all-teams' : $current_team );
    if ( isset( $_GET['privacy'] ) ) {
        $base_params['privacy'] = $_GET['privacy'];
    }
    return '?' . http_build_query( array_merge( $base_params, $params ) );
}

function get_events_for_date( $events, $date ) {
    $target_date = $date->format( 'Y-m-d' );
    $matching_events = array();

    foreach ( $events as $event ) {
        $event_date = $event->date->format( 'Y-m-d' );

        // Check if event has end_date for multi-day support
        if ( isset( $event->end_date ) && $event->end_date ) {
            $end_date = $event->end_date->format( 'Y-m-d' );
            if ( $target_date >= $event_date && $target_date <= $end_date ) {
                $matching_events[] = $event;
            }
        } else {
            // Single day event
            if ( $event_date === $target_date ) {
                $matching_events[] = $event;
            }
        }
    }

    return $matching_events;
}

function generate_calendar_grid( $year, $month, $events, $week_starts_monday = true ) {
    $first_day = new DateTime( "$year-$month-01" );
    $last_day = new DateTime( $first_day->format( 'Y-m-t' ) );

    // Calculate first day of calendar grid
    $first_day_of_week = (int) $first_day->format( 'w' );
    if ( $week_starts_monday ) {
        $first_day_of_week = $first_day_of_week === 0 ? 6 : $first_day_of_week - 1;
    }

    $calendar_start = clone $first_day;
    $calendar_start->modify( "-$first_day_of_week days" );

    // Generate 6 weeks (42 days) to ensure consistent grid
    $calendar = array();
    $current_date = clone $calendar_start;

    for ( $week = 0; $week < 6; $week++ ) {
        $calendar[$week] = array();
        for ( $day = 0; $day < 7; $day++ ) {
            $is_current_month = $current_date->format( 'n' ) == $month;
            $is_today = $current_date->format( 'Y-m-d' ) === date( 'Y-m-d' );
            $day_events = get_events_for_date( $events, $current_date );

            $calendar[$week][$day] = array(
                'date' => clone $current_date,
                'day' => $current_date->format( 'j' ),
                'is_current_month' => $is_current_month,
                'is_today' => $is_today,
                'events' => $day_events
            );

            $current_date->modify( '+1 day' );
        }
    }

    return $calendar;
}

// Generate calendar data if in calendar view
$calendar_grid = null;
if ( $view_mode !== 'list' ) {
    $week_starts_monday = $view_mode === 'month-monday';
    $calendar_grid = generate_calendar_grid( $calendar_year, $calendar_month, $all_upcoming_events, $week_starts_monday );
}

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
    <?php render_dark_mode_toggle(); ?>

    <?php render_cmd_k_panel(); ?>

    <div class="container">
        <div class="header">
            <div class="header-container">
                <h1>
                    <?php if ( $all_teams_mode ) : ?>
                        <a href="team-selection.php" class="title-link"><?php echo htmlspecialchars( $team_data['team_name'] ); ?> Events</a>
                    <?php else : ?>
                        <a href="<?php echo build_team_url( 'index.php' ); ?>" class="title-link"><?php echo htmlspecialchars( $team_data['team_name'] ); ?> Events</a>
                    <?php endif; ?>
                </h1>
                <div class="back-nav">
                    <?php if ( $all_teams_mode ) : ?>
                        <a href="team-selection.php">← Back to Team Selection</a>
                    <?php else : ?>
                    <a href="<?php echo build_team_url( 'index.php', array( 'privacy' => $privacy_mode ? '1' : '0' ) ); ?>">← Back to <?php echo $team_data['team_name'], ' ', ucfirst( $group ); ?> Overview</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="nav-container">
                <!-- Team Switcher -->
                <select id="team-selector" onchange="switchTeam()">
                    <?php
                    // Add "All Teams" option
                    $selected_all = $all_teams_mode ? 'selected' : '';
                    echo '<option value="all-teams" ' . $selected_all . '>All Teams</option>';
                    
                    foreach ( $available_teams as $team_slug ) {
                        $team_display_name = get_team_name_from_file( $team_slug );
                        $selected = ! $all_teams_mode && $team_slug === $current_team ? 'selected' : '';
                        echo '<option value="' . htmlspecialchars( $team_slug ) . '" ' . $selected . '>' . htmlspecialchars( $team_display_name ) . '</option>';
                    }
                    ?>
                </select>
                
                <?php if ( ! $all_teams_mode ) : ?>
                    <a href="<?php echo build_team_url( 'admin.php', array( 'tab' => 'events', 'add' => 'new' ) ); ?>" class="nav-link green">+ Add Event</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- View Mode Toggle -->
        <div class="view-controls">
            <div class="view-toggle">
                <a href="<?php echo build_calendar_url( array( 'view' => 'list' ) ); ?>"
                   class="view-btn <?php echo $view_mode === 'list' ? 'active' : ''; ?>">📅 Upcoming Events</a>
                <a href="<?php echo build_calendar_url( array( 'view' => 'month-monday', 'month' => $calendar_month, 'year' => $calendar_year ) ); ?>"
                   class="view-btn <?php echo $view_mode === 'month-monday' ? 'active' : ''; ?>">Month (Mon)</a>
                <a href="<?php echo build_calendar_url( array( 'view' => 'month-sunday', 'month' => $calendar_month, 'year' => $calendar_year ) ); ?>"
                   class="view-btn <?php echo $view_mode === 'month-sunday' ? 'active' : ''; ?>">Month (Sun)</a>
            </div>
            <?php if ( ! empty( $all_upcoming_events ) ) : ?>
                <div class="events-count">
                    <?php echo count( $all_upcoming_events ); ?> upcoming event<?php echo count( $all_upcoming_events ) !== 1 ? 's' : ''; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Calendar Navigation -->
        <?php if ( $view_mode !== 'list' ) :
            // Calculate previous/next month
            $prev_month = $calendar_month === 1 ? 12 : $calendar_month - 1;
            $prev_year = $calendar_month === 1 ? $calendar_year - 1 : $calendar_year;
            $next_month = $calendar_month === 12 ? 1 : $calendar_month + 1;
            $next_year = $calendar_month === 12 ? $calendar_year + 1 : $calendar_year;

            $month_names = array( 1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June',
                                 7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December' );
        ?>
            <div class="calendar-navigation">
                <a href="<?php echo build_calendar_url( array( 'view' => $view_mode, 'month' => $prev_month, 'year' => $prev_year ) ); ?>"
                   class="nav-arrow">← Previous</a>
                <h3><?php echo $month_names[$calendar_month] . ' ' . $calendar_year; ?></h3>
                <a href="<?php echo build_calendar_url( array( 'view' => $view_mode, 'month' => $next_month, 'year' => $next_year ) ); ?>"
                   class="nav-arrow">Next →</a>
            </div>
        <?php endif; ?>

        <!-- Upcoming Events Section -->
        <?php if ( $view_mode === 'list' ) : ?>
        <div class="section">
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
                                                if ( $event->has_person() && in_array( $event->type, array( 'birthday', 'anniversary', 'sabbatical', 'other' ) ) ) {
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
                                                           class="event-link-main">
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
                    <?php if ( $all_teams_mode ) : ?>
                        <p>There are no scheduled events across all teams.</p>
                    <?php else : ?>
                        <p>There are no scheduled events for this team.</p>
                        <a href="<?php echo build_team_url( 'admin.php', array( 'tab' => 'events' ) ); ?>" class="nav-link inline">
                            Add an Event →
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Calendar View -->
        <?php if ( $view_mode !== 'list' && $calendar_grid ) :
            $week_starts_monday = $view_mode === 'month-monday';
            $day_headers = $week_starts_monday ?
                array( 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun' ) :
                array( 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat' );
        ?>
        <div class="section calendar-section">
            <div class="calendar-grid">
                <!-- Day headers -->
                <?php foreach ( $day_headers as $day_header ) : ?>
                    <div class="calendar-header"><?php echo $day_header; ?></div>
                <?php endforeach; ?>

                <!-- Calendar days -->
                <?php foreach ( $calendar_grid as $week ) : ?>
                    <?php foreach ( $week as $day ) : ?>
                        <div class="calendar-day <?php echo ! $day['is_current_month'] ? 'other-month' : ''; ?> <?php echo $day['is_today'] ? 'today' : ''; ?>"<?php if ( ! $day['is_current_month'] ) : ?> onclick="navigateToMonth('<?php echo $day['date']->format( 'Y' ); ?>', '<?php echo $day['date']->format( 'n' ); ?>')" style="cursor: pointer;"<?php endif; ?>>
                            <div class="calendar-day-number"><?php echo $day['day']; ?></div>

                            <?php if ( ! empty( $day['events'] ) ) : ?>
                                <div class="calendar-events">
                                    <?php
                                    $max_visible = 3;
                                    $event_count = 0;
                                    foreach ( $day['events'] as $event ) :
                                        if ( $event_count >= $max_visible ) break;
                                        $event_title = $event->has_person() && in_array( $event->type, array( 'birthday', 'anniversary' ) ) ?
                                                      $event->get_title() : $event->description;

                                        // Handle multi-day event display
                                        if ( isset( $event->end_date ) && $event->end_date ) {
                                            $current_date_str = $day['date']->format( 'Y-m-d' );
                                            $start_date_str = $event->date->format( 'Y-m-d' );
                                            $end_date_str = $event->end_date->format( 'Y-m-d' );

                                            // Normalize dates to midnight for proper comparison
                                            $current_midnight = clone $day['date'];
                                            $current_midnight->setTime( 0, 0, 0 );
                                            $start_midnight = clone $event->date;
                                            $start_midnight->setTime( 0, 0, 0 );
                                            
                                            // Calculate days from start (0-based)
                                            $days_diff = $current_midnight->diff( $start_midnight )->days;

                                            // Only modify the title if it's NOT the first day (days_diff > 0)
                                            if ( $days_diff > 0 ) {
                                                $day_number = $days_diff + 1;

                                                if ( $current_date_str === $end_date_str ) {
                                                    $event_title = "Last day";
                                                } else {
                                                    $event_title = "Day " . $day_number;
                                                }

                                                // Add location if available
                                                if ( ! empty( $event->location ) ) {
                                                    // Extract just the town/city from location
                                                    $location_parts = explode( ',', $event->location );
                                                    $town = trim( $location_parts[0] );
                                                    $event_title = $town . ' - ' . $event_title;
                                                }
                                            }
                                            // First day keeps the original event title unchanged
                                        }

                                        // For continuation days, use original title in tooltip
                                        $original_title = $event->has_person() && in_array( $event->type, array( 'birthday', 'anniversary', 'sabbatical', 'other' ) ) ?
                                                         $event->get_title() : $event->description;
                                        $tooltip = $original_title . ( ! empty( $event->location ) ? ' - ' . $event->location : '' );
                                    ?>
                                        <?php if ( $event->has_person() && in_array( $event->type, array( 'birthday', 'anniversary', 'sabbatical', 'other' ) ) ) : ?>
                                            <a href="<?php echo build_team_url( 'index.php', array( 'person' => $event->person->username ) ); ?>"
                                               class="calendar-event <?php echo $event->type; ?>"
                                               title="<?php echo htmlspecialchars( $tooltip ); ?>"
                                               style="text-decoration: none; display: block;">
                                                <?php echo htmlspecialchars( $event_title ); ?>
                                            </a>
                                        <?php else : ?>
                                            <div class="calendar-event <?php echo $event->type; ?>" title="<?php echo htmlspecialchars( $tooltip ); ?>">
                                                <?php echo htmlspecialchars( $event_title ); ?>
                                                <?php if ( ! empty( $event->links ) ) :
                                                    // For multi-day events, spread links across days
                                                    $show_links = array();
                                                    if ( isset( $event->end_date ) && $event->end_date ) {
                                                        $current_date_str = $day['date']->format( 'Y-m-d' );
                                                        $start_date_str = $event->date->format( 'Y-m-d' );

                                                        // Normalize dates to midnight for proper comparison
                                                        $current_midnight = clone $day['date'];
                                                        $current_midnight->setTime( 0, 0, 0 );
                                                        $start_midnight = clone $event->date;
                                                        $start_midnight->setTime( 0, 0, 0 );
                                                        $days_diff = $current_midnight->diff( $start_midnight )->days;

                                                        if ( $days_diff > 0 ) {
                                                            $day_number = $days_diff + 1;
                                                            $link_index = $day_number - 2; // Day 2 = index 0, Day 3 = index 1, etc.

                                                            $links_array = array_values( $event->links );
                                                            $link_keys = array_keys( $event->links );

                                                            if ( isset( $links_array[$link_index] ) ) {
                                                                $show_links[$link_keys[$link_index]] = $links_array[$link_index];
                                                            }
                                                        }
                                                    } else {
                                                        // Single day event - show all links
                                                        $show_links = $event->links;
                                                    }

                                                    if ( ! empty( $show_links ) ) : ?>
                                                    <div style="font-size: 11px; margin-top: 2px;">
                                                        <?php foreach ( $show_links as $link_text => $link_url ) : ?>
                                                            <a href="<?php echo htmlspecialchars( $link_url ); ?>" target="_blank"
                                                               style="color: white; text-decoration: underline; font-size: 10px; display: block;"
                                                               title="<?php echo htmlspecialchars( $link_text ); ?>">
                                                                <?php echo htmlspecialchars( $link_text ); ?> →
                                                            </a>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php
                                        $event_count++;
                                    endforeach;

                                    if ( count( $day['events'] ) > $max_visible ) : ?>
                                        <div class="calendar-event-more">
                                            <?php echo count( $day['events'] ) - $max_visible; ?> more
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Past Events Section (Team Events Only, Last 3 Years) - Only show in list view -->
        <?php if ( $view_mode === 'list' && ! empty( $past_by_month ) ) : ?>
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
                                                           class="event-link-main">
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
    <?php init_cmd_k_js( $privacy_mode ); ?>
    <script>
        
        // Team switching functionality
        function switchTeam() {
            const selector = document.getElementById('team-selector');
            const selectedTeam = selector.value;
            const currentUrl = new URL(window.location);
            currentUrl.searchParams.set('team', selectedTeam);
            window.location = currentUrl.toString();
        }
        
        // Calendar month navigation functionality
        function navigateToMonth(year, month) {
            const currentUrl = new URL(window.location);
            currentUrl.searchParams.set('month', month);
            currentUrl.searchParams.set('year', year);
            window.location = currentUrl.toString();
        }
    </script>
</body>
</html>