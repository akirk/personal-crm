<?php
/**
 * Team Events Page
 *
 * Display all events for the current team.
 */
namespace PersonalCRM;

require_once __DIR__ . '/personal-crm.php';

// For events page, we need to handle initialization differently to avoid wp-app routing conflicts
$crm = PersonalCrm::get_instance();
$current_group = $crm->get_current_group_from_params();
// If no team specified, use default team or first available team
if ( ! $current_group ) {
    $current_group = $crm->get_default_group();
    if ( ! $current_group ) {
        $available_teams = $crm->storage->get_available_groups();
        if ( ! empty( $available_teams ) ) {
            $current_group = $available_teams[0]; // Use first available team instead of redirecting
        }
    }
}


// Events-specific logic
$all_teams_mode = $current_group === 'all-teams';

// Load team configuration with Person objects
if ( $all_teams_mode ) {
    // For all-teams mode, we'll load this later after we set up the parameters
    $group_data = null;
} else {
    $group_data = $crm->storage->get_group( $current_group );
}

$available_teams = $crm->storage->get_available_groups();

// Get calendar view parameters
$view_mode = isset( $_GET['view'] ) ? $_GET['view'] : 'list';
$calendar_month = isset( $_GET['cal_month'] ) ? intval( $_GET['cal_month'] ) : date( 'n' );
$calendar_year = isset( $_GET['cal_year'] ) ? intval( $_GET['cal_year'] ) : date( 'Y' );

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

// Handle all-teams mode data loading
if ( $all_teams_mode ) {
	// Load events from all teams (lazy loaded)
	$all_teams_groups = array();
	foreach ( $available_teams as $team_slug ) {
		$all_teams_groups[$team_slug] = $crm->storage->get_group( $team_slug );
	}
	// Create a combined team_data structure
	$group_data = array(
		'team_name' => 'All Groups',
		'events' => array(),
		'people' => array(),
		'team_members' => array(),
		'leadership' => array(),
		'consultants' => array(),
		'alumni' => array(),
		'deceased' => array()
	);

	// Combine all events and people from all teams
	foreach ( $all_teams_groups as $team_slug => $group_obj ) {
		$group_data['events'] = array_merge( $group_data['events'], $group_obj->get_events() );
		$group_data['team_members'] = array_merge( $group_data['team_members'], $group_obj->get_members() );
		// Child groups would need to be loaded separately if needed
		foreach ( $group_obj->get_child_groups() as $child_group ) {
			$section_name = str_replace( $team_slug . '_', '', $child_group->slug );
			if ( ! isset( $group_data[$section_name] ) ) {
				$group_data[$section_name] = array();
			}
			$group_data[$section_name] = array_merge( $group_data[$section_name], $child_group->get_members() );
		}
	}
}

// Process team events only (for past events section)
$team_events = array();

// Separate team events into upcoming and past
$past_team_events = array_filter( $group_data['events'], function( $event ) {
	return $event->is_past();
});

usort( $past_team_events, function( $a, $b ) {
	return $b->date <=> $a->date;
});

// Get events for display - different logic for list vs calendar views
if ( $view_mode === 'list' ) {
    // For list view, show upcoming events only
    $all_upcoming_events = $crm->get_upcoming_events_for_display( $group_data );
} else {
    // For calendar views, show events within a broader date range around the selected month
    $month_start = new \DateTime( "$calendar_year-$calendar_month-01" );
    $month_end = clone $month_start;
    $month_end->modify( 'last day of this month' );

    // Expand range to include previous and next month to show events that span months
    $range_start = clone $month_start;
    $range_start->modify( 'first day of previous month' );
    $range_end = clone $month_end;
    $range_end->modify( 'last day of next month' );

    $all_upcoming_events = $crm->get_calendar_events( $group_data, $range_start, $range_end );
}

// Calendar helper functions
function build_calendar_url( $params = array() ) {
    global $current_group, $all_teams_mode;
    $base_params = array( 'team' => $all_teams_mode ? 'all-teams' : $current_group );
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
    $first_day = new \DateTime( "$year-$month-01" );
    $last_day = new \DateTime( $first_day->format( 'Y-m-t' ) );

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
$available_teams = $crm->storage->get_available_groups();

?>
<!DOCTYPE html>
<html <?php echo function_exists( 'wp_app_language_attributes' ) ? wp_app_language_attributes() : 'lang="en"'; ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <title><?php echo function_exists( 'wp_app_title' ) ? wp_app_title( htmlspecialchars( $group_data['group_name'] ) . ' Events' ) : htmlspecialchars( $group_data['group_name'] ) . ' Events'; ?></title>
    <?php
    if ( function_exists( 'wp_app_enqueue_style' ) ) {
        wp_app_enqueue_style( 'a8c-hr-style', plugin_dir_url( __FILE__ ) . 'assets/style.css' );
        wp_app_enqueue_style( 'a8c-hr-cmd-k', plugin_dir_url( __FILE__ ) . 'assets/cmd-k.css' );
    } else {
        echo '<link rel="stylesheet" href="' . plugin_dir_url( __FILE__ ) . 'assets/style.css">';
        echo '<link rel="stylesheet" href="' . plugin_dir_url( __FILE__ ) . 'assets/cmd-k.css">';
    }
    ?>
    <?php if ( function_exists( 'wp_app_head' ) ) wp_app_head(); ?>
</head>
<body class="wp-app-body">
    <?php if ( function_exists( 'wp_app_body_open' ) ) wp_app_body_open(); ?>
    <?php $crm->render_cmd_k_panel(); ?>

    <div class="container">
        <div class="header">
            <div class="header-container">
                <h1>
                    <?php if ( $all_teams_mode ) : ?>
                        <a href="<?php echo $crm->build_url( 'select.php' ); ?>" class="title-link"><?php echo htmlspecialchars( $group_data['group_name'] ); ?> Events</a>
                    <?php else : ?>
                        <a href="<?php echo $crm->build_url( 'index.php' ); ?>" class="title-link"><?php echo htmlspecialchars( $group_data['group_name'] ); ?> Events</a>
                    <?php endif; ?>
                </h1>
                <div class="back-nav">
                    <?php if ( $all_teams_mode ) : ?>
                        <a href="<?php echo $crm->build_url( 'select.php' ); ?>">← Back to Group Selection</a>
                    <?php else : ?>
                    <a href="<?php echo $crm->build_url( 'index.php' ); ?>">← Back to <?php echo $group_data['group_name']; ?> Overview</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="nav-container">
                <select id="group-selector" onchange="switchGroup()">
                    <?php
                    // Add "All Groups" option at the top
                    $selected_all = $all_teams_mode ? 'selected' : '';
                    ?>
                    <option value="all-teams" <?php echo $selected_all; ?>>All Groups</option>

                    <?php
                    // Separate teams by type and default status (same structure as index.php)
                    $default_teams = array();
                    $teams = array();
                    $groups = array();

                    foreach ( $available_teams as $team_slug ) {
                        $team_name = $crm->storage->get_group_name( $team_slug );
                        $team_type = $crm->storage->get_group_type( $team_slug );
                        $is_default = $crm->get_default_group() === $team_slug;

                        $item = array(
                            'slug' => $team_slug,
                            'name' => $team_name,
                            'type' => $team_type
                        );

                        if ( $is_default ) {
                            $default_teams[] = $item;
                        } elseif ( $team_type === 'group' ) {
                            $groups[] = $item;
                        } else {
                            $teams[] = $item;
                        }
                    }

                    // Sort each group by name
                    usort( $default_teams, fn($a, $b) => strcasecmp( $a['name'], $b['name'] ) );
                    usort( $teams, fn($a, $b) => strcasecmp( $a['name'], $b['name'] ) );
                    usort( $groups, fn($a, $b) => strcasecmp( $a['name'], $b['name'] ) );
                    ?>

                    <?php if ( ! empty( $default_teams ) ) : ?>
                    <optgroup label="Default">
                        <?php foreach ( $default_teams as $item ) : ?>
                            <option value="<?php echo htmlspecialchars( $crm->build_ul( $item['slug'] ) ); ?>" data-type="<?php echo htmlspecialchars( $item['type'] ); ?>" <?php echo ! $all_teams_mode && $item['slug'] === $current_group ? 'selected' : ''; ?>><?php echo htmlspecialchars( $item['name'] ); ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                    <?php endif; ?>

                    <?php if ( ! empty( $teams ) ) : ?>
                    <optgroup label="Groups">
                        <?php foreach ( $teams as $item ) : ?>
                            <option value="<?php echo htmlspecialchars( $item['slug'] ); ?>" data-type="<?php echo htmlspecialchars( $item['type'] ); ?>" <?php echo ! $all_teams_mode && $item['slug'] === $current_group ? 'selected' : ''; ?>><?php echo htmlspecialchars( $item['name'] ); ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                    <?php endif; ?>

                    <?php if ( ! empty( $groups ) ) : ?>
                    <optgroup label="Groups">
                        <?php foreach ( $groups as $item ) : ?>
                            <option value="<?php echo htmlspecialchars( $item['slug'] ); ?>" data-type="<?php echo htmlspecialchars( $item['type'] ); ?>" <?php echo ! $all_teams_mode && $item['slug'] === $current_group ? 'selected' : ''; ?>><?php echo htmlspecialchars( $item['name'] ); ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                    <?php endif; ?>
                </select>
                
                <?php if ( ! $all_teams_mode ) : ?>
                    <a href="<?php echo $crm->build_url( 'admin/index.php', array( 'tab' => 'events', 'add' => 'new' ) ); ?>" class="nav-link green">+ Add Event</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- View Mode Toggle -->
        <div class="view-controls">
            <div class="view-toggle">
                <a href="<?php echo build_calendar_url( array( 'view' => 'list' ) ); ?>"
                   class="view-btn <?php echo $view_mode === 'list' ? 'active' : ''; ?>">📅 Upcoming Events</a>
                <a href="<?php echo build_calendar_url( array( 'view' => 'month-monday', 'cal_month' => $calendar_month, 'cal_year' => $calendar_year ) ); ?>"
                   class="view-btn <?php echo $view_mode === 'month-monday' ? 'active' : ''; ?>">Month (Mon)</a>
                <a href="<?php echo build_calendar_url( array( 'view' => 'month-sunday', 'cal_month' => $calendar_month, 'cal_year' => $calendar_year ) ); ?>"
                   class="view-btn <?php echo $view_mode === 'month-sunday' ? 'active' : ''; ?>">Month (Sun)</a>
            </div>
            <?php if ( ! empty( $all_upcoming_events ) ) : ?>
                <div class="events-count">
                    <?php
                    if ( $view_mode === 'list' ) {
                        echo count( $all_upcoming_events ) . ' upcoming event' . ( count( $all_upcoming_events ) !== 1 ? 's' : '' );
                    } else {
                        // For calendar view, show events for the current month
                        echo count( $all_upcoming_events ) . ' event' . ( count( $all_upcoming_events ) !== 1 ? 's' : '' ) . ' in range';
                    }
                    ?>
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
                <a href="<?php echo build_calendar_url( array( 'view' => $view_mode, 'cal_month' => $prev_month, 'cal_year' => $prev_year ) ); ?>"
                   class="nav-arrow">← Previous</a>
                <h3><?php echo $month_names[$calendar_month] . ' ' . $calendar_year; ?></h3>
                <a href="<?php echo build_calendar_url( array( 'view' => $view_mode, 'cal_month' => $next_month, 'cal_year' => $next_year ) ); ?>"
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
                                                	echo '<a href="' . $crm->build_url( 'index.php', array( 'person' => $event->person->username ) ) . '" class="event-person-link">' . htmlspecialchars( $event->get_title() ) . '</a>';
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
                                                foreach ( $group_data['events'] as $index => $team_event ) {
                                                    if ( $team_event->description === $event->description && 
                                                         $team_event->date->format( 'Y-m-d' ) === $event->date->format( 'Y-m-d' ) ) {
                                                        $event_index = $index;
                                                        break;
                                                    }
                                                }
                                                if ( $event_index !== null ) : ?>
                                                    <div class="event-edit-container">
                                                        <a href="<?php echo $crm->build_url( 'admin/index.php', array( 'tab' => 'events', 'edit_event' => $event_index ) ); ?>"
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
                        <a href="<?php echo $crm->build_url( 'admin/index.php', array( 'tab' => 'events' ) ); ?>" class="nav-link inline">
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
                                            <a href="<?php echo $crm->build_url( 'index.php', array( 'person' => $event->person->username ) ); ?>"
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
                    <h2 class="section-title">📋 Past Events</h2>
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
                                            foreach ( $group_data['events'] as $index => $team_event ) {
                                                if ( $team_event->description === $event->description && 
                                                     $team_event->date->format( 'Y-m-d' ) === $event->date->format( 'Y-m-d' ) ) {
                                                    $event_index = $index;
                                                    break;
                                                }
                                            }
                                            if ( $event_index !== null ) : ?>
                                                <div style="font-size: 12px; margin-top: 4px;">
                                                    <a href="<?php echo $crm->build_url( 'admin/index.php', array( 'tab' => 'events', 'edit_event' => $event_index ) ); ?>"
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
            <a href="#" id="privacy-toggle" onclick="togglePrivacyMode(); return false;">
                <span id="privacy-status">🔓 Privacy Mode OFF</span>
            </a>
            <a href="<?php echo $crm->build_url( 'admin/index.php', array( 'group' => $current_group ) ); ?>">⚙️ Admin Panel</a>
        </footer>
    </div>
    
    <?php
    if ( function_exists( 'wp_app_enqueue_script' ) ) {
        wp_app_enqueue_script( 'a8c-hr-cmd-k-js', plugin_dir_url( __FILE__ ) . 'assets/cmd-k.js' );
        wp_app_enqueue_script( 'a8c-hr-script-js', plugin_dir_url( __FILE__ ) . 'assets/script.js' );
    } else {
        echo '<script src="' . plugin_dir_url( __FILE__ ) . 'assets/cmd-k.js"></script>';
        echo '<script src="' . plugin_dir_url( __FILE__ ) . 'assets/script.js"></script>';
    }
    ?>
    <?php $crm->init_cmd_k_js(); ?>
</body>
</html>