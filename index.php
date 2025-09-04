<?php
/**
 * Orbit Team Management Tool
 * 
 * A comprehensive team management dashboard for the Orbit team.
 * Provides overview of team members, 1:1 documents, and team activities.
 */

// Include common functions and Person class
require_once __DIR__ . '/includes/common.php';
require_once __DIR__ . '/includes/person.php';

// Team redirection logic - handle cases where no team parameter is provided
if ( isset( $_GET['team'] ) ) {
	$current_team = $_GET['team'];
	if ( $current_team === get_default_team() && ! isset( $_GET['person'] ) ) {
		// Redirect to root if default team is selected
		header( 'Location: ./' );
		exit;
	}
} else {
	$current_team = get_default_team();
	$available_teams = get_available_teams();
	if ( count( $available_teams ) > 1 && ! $current_team ) {
		header( 'Location: team-selection.php' );
		exit;
	}
}


/**
 * Get SVG icon for a link based on its text or URL
 */
function get_link_icon( $link_text, $link_url, $size = 16 ) {
	if ( 0 === strpos( $link_url, 'https://linear.app/' ) ) {
		return '<svg xmlns="http://www.w3.org/2000/svg" fill="none" width="' . $size . '" height="' . $size . '" viewBox="0 0 100 100" style="vertical-align: middle; margin-right: 4px"><path fill="#222326" d="M1.22541 61.5228c-.2225-.9485.90748-1.5459 1.59638-.857L39.3342 97.1782c.6889.6889.0915 1.8189-.857 1.5964C20.0515 94.4522 5.54779 79.9485 1.22541 61.5228ZM.00189135 46.8891c-.01764375.2833.08887215.5599.28957165.7606L52.3503 99.7085c.2007.2007.4773.3075.7606.2896 2.3692-.1476 4.6938-.46 6.9624-.9259.7645-.157 1.0301-1.0963.4782-1.6481L2.57595 39.4485c-.55186-.5519-1.49117-.2863-1.648174.4782-.465915 2.2686-.77832 4.5932-.92588465 6.9624ZM4.21093 29.7054c-.16649.3738-.08169.8106.20765 1.1l64.77602 64.776c.2894.2894.7262.3742 1.1.2077 1.7861-.7956 3.5171-1.6927 5.1855-2.684.5521-.328.6373-1.0867.1832-1.5407L8.43566 24.3367c-.45409-.4541-1.21271-.3689-1.54074.1832-.99132 1.6684-1.88843 3.3994-2.68399 5.1855ZM12.6587 18.074c-.3701-.3701-.393-.9637-.0443-1.3541C21.7795 6.45931 35.1114 0 49.9519 0 77.5927 0 100 22.4073 100 50.0481c0 14.8405-6.4593 28.1724-16.7199 37.3375-.3903.3487-.984.3258-1.3542-.0443L12.6587 18.074Z"/></svg> ';
	} elseif ( $link_text === '1:1 doc' ) {
		return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="#222326" style="vertical-align: middle; margin-right: 4px"><path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/><circle cx="9" cy="13" r="1.5"/><circle cx="15" cy="13" r="1.5"/><path d="M9,16H15V18H9V16Z"/></svg>';
	} elseif ( $link_text === 'HR monthly' ) {
		return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="#222326" style="vertical-align: middle; margin-right: 4px"><path d="M19,3H18V1H16V3H8V1H6V3H5A2,2 0 0,0 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19V5A2,2 0 0,0 19,3M19,19H5V8H19V19M5,6V5H19V6H5Z"/><rect x="7" y="10" width="2" height="2"/><rect x="11" y="10" width="2" height="2"/><rect x="15" y="10" width="2" height="2"/><rect x="7" y="14" width="2" height="2"/><rect x="11" y="14" width="2" height="2"/></svg>';
	}
	return '';
}

/**
 * Render person links with icons
 */
function render_person_links( $links, $icon_size = 12 ) {
	foreach ( $links as $link_text => $link_url ) {
		if ( ! empty( $link_url ) ) {
			echo '<a href="' . htmlspecialchars( $link_url ) . '" target="_blank">';
			echo get_link_icon( $link_text, $link_url, $icon_size );
			echo htmlspecialchars( $link_text );
			echo '</a>';
		}
	}
}


// Get privacy mode early for Person object creation
$privacy_mode = isset( $_GET['privacy'] ) && $_GET['privacy'] === '1';

// Load team configuration with Person objects
$team_data = load_team_config_with_objects( $current_team, $privacy_mode );

/**
 * Get all upcoming events (personal + team/company) - within next 3 months
 */
function get_all_upcoming_events( $team_data ) {
	$all_events = array();
	$current_date = new DateTime();
	$cutoff_date = clone $current_date;
	$cutoff_date->add( new DateInterval( 'P3M' ) ); // 3 months from now
	
	// Get personal events from all team members, leadership, and alumni
	$all_people = array_merge( $team_data['team_members'], $team_data['leadership'], $team_data['alumni'] );
	foreach ( $all_people as $person ) {
		$personal_events = $person->get_upcoming_events();
		$all_events = array_merge( $all_events, $personal_events );
	}
	
	// Add team and company events (within 3 months)
	foreach ( $team_data['events'] as $event ) {
		$start_date = DateTime::createFromFormat( 'Y-m-d', $event->start_date );
		if ( $start_date && $start_date >= $current_date && $start_date <= $cutoff_date ) {
			$end_date = DateTime::createFromFormat( 'Y-m-d', $event->end_date );
			$duration = '';
			if ( $end_date && $start_date->format( 'Y-m-d' ) !== $end_date->format( 'Y-m-d' ) ) {
				$duration = ' - ' . $end_date->format( 'M j' );
			}
			
			$all_events[] = array(
				'type' => $event->type,
				'date' => $start_date,
				'description' => $event->name . $duration,
				'location' => $event->location ?? '',
				'details' => $event->description ?? '',
			);
		}
	}
	
	// Sort all events by date
	usort( $all_events, function( $a, $b ) {
		return $a['date'] <=> $b['date'];
	} );
	
	return $all_events;
}


function mask_date( $date, $privacy_mode, $show_year = false ) {
	if ( ! $privacy_mode || empty( $date ) ) {
		return $date;
	}
	
	if ( $show_year ) {
		// For birthdays, show just the year
		if ( preg_match( '/^(\d{4})-\d{2}-\d{2}$/', $date, $matches ) ) {
			return $matches[1];
		}
	}
	
	return '****-**-**';
}

function mask_birthday_display( $person, $privacy_mode ) {
	if ( ! $privacy_mode || empty( $person->birthday ) ) {
		return $person->get_birthday_display();
	}
	
	// Show only age if full birthday is available
	$age = $person->get_age();
	if ( $age !== null ) {
		return 'Age ' . $age;
	}
	
	return '[Hidden]';
}

function mask_event_description( $description, $privacy_mode, $all_people ) {
	if ( ! $privacy_mode ) {
		return $description;
	}
	
	// Mask names in event descriptions
	foreach ( $all_people as $person ) {
		$full_name = $person->name;
		$masked_name = mask_name( $full_name, true );
		$description = str_replace( $full_name, $masked_name, $description );
	}
	
	return $description;
}

// Get current action
$person = $_GET['person'] ?? null;
$action = $person ? 'person' : 'overview';

// Get available teams for switcher
$available_teams = get_available_teams();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars( $team_data['team_name'] ); ?> Team Management</title>
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
        <?php if ( $action === 'overview' ) : ?>
            <div class="header">
                <div style="flex-grow: 1;">
                    <h1><a href="<?php echo build_team_url( 'index.php' ); ?>" style="color: inherit; text-decoration: none;"><?php echo htmlspecialchars( $team_data['team_name'] ); ?> Team Overview</a></h1>
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
                </div>
            </div>

            <div class="overview-layout">
                <div class="people-section">
                    <div class="section">
                        <h3>Team Members (<?php echo count( $team_data['team_members'] ); ?>)</h3>
                        <?php if ( ! empty( $team_data['team_members'] ) ) : ?>
                            <ul class="people-list">
                                <?php foreach ( $team_data['team_members'] as $username => $member ) : ?>
                                    <li>
                                        <div class="person-row-container">
                                            <a href="<?php echo build_team_url( 'index.php', array( 'person' => $username, 'privacy' => $privacy_mode ? '1' : '0' ) ); ?>" class="person-row">
                                                <div class="person-info">
                                                    <div class="person-name"><?php echo htmlspecialchars( display_name_with_nickname( $member, $privacy_mode ) ); ?></div>
                                                    <div class="person-username">@<?php echo htmlspecialchars( mask_username( $username, $privacy_mode ) ); ?></div>
                                                </div>
                                            </a>
                                            <div class="person-links">
                                                <?php render_person_links( $member->links ); ?>
                                            </div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else : ?>
                            <p style="color: #666; font-style: italic;">No team members yet. <a href="<?php echo build_team_url( 'admin.php', array( 'tab' => 'members', 'add' => 'new' ) ); ?>" style="color: #007cba; text-decoration: none;">Add your first team member →</a></p>
                        <?php endif; ?>
                    </div>

                    <div class="section">
                        <h3>Leadership (<?php echo count( $team_data['leadership'] ); ?>)</h3>
                        <?php if ( ! empty( $team_data['leadership'] ) ) : ?>
                            <ul class="people-list">
                                <?php foreach ( $team_data['leadership'] as $username => $leader ) : ?>
                                    <li>
                                        <div class="person-row-container">
                                            <a href="<?php echo build_team_url( 'index.php', array( 'person' => $username, 'privacy' => $privacy_mode ? '1' : '0' ) ); ?>" class="person-row">
                                                <div class="person-info">
                                                    <div class="person-name"><?php echo htmlspecialchars( display_name_with_nickname( $leader, $privacy_mode ) ); ?> <span style="color: #666; font-weight: normal;">(<?php echo htmlspecialchars( $leader->role ); ?>)</span></div>
                                                    <div class="person-username">@<?php echo htmlspecialchars( mask_username( $username, $privacy_mode ) ); ?></div>
                                                </div>
                                            </a>
                                            <div class="person-links">
                                                <?php render_person_links( $leader->links ); ?>
                                            </div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else : ?>
                            <p style="color: #666; font-style: italic;">No leadership yet. <a href="<?php echo build_team_url( 'admin.php', array( 'tab' => 'leadership', 'add' => 'new' ) ); ?>" style="color: #007cba; text-decoration: none;">Add your first leader →</a></p>
                        <?php endif; ?>
                    </div>

                    <div class="section">
                        <h3>Alumni (<?php echo count( $team_data['alumni'] ); ?>)</h3>
                        <?php if ( ! empty( $team_data['alumni'] ) ) : ?>
                            <ul class="people-list">
                                <?php foreach ( $team_data['alumni'] as $username => $alumnus ) : ?>
                                    <li>
                                        <div class="person-row-container">
                                            <a href="<?php echo build_team_url( 'index.php', array( 'person' => $username, 'privacy' => $privacy_mode ? '1' : '0' ) ); ?>" class="person-row">
                                                <div class="person-info">
                                                    <div class="person-name"><?php echo htmlspecialchars( display_name_with_nickname( $alumnus, $privacy_mode ) ); ?> <span style="color: #999; font-weight: normal;">(Alumni)</span></div>
                                                    <div class="person-username">@<?php echo htmlspecialchars( mask_username( $username, $privacy_mode ) ); ?></div>
                                                </div>
                                            </a>
                                            <div class="person-links">
                                                <?php render_person_links( $alumnus->links ); ?>
                                            </div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else : ?>
                            <p style="color: #666; font-style: italic;">No alumni yet. Alumni are created by moving existing team members or leaders.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="events-sidebar">
                    <a href="<?php echo build_team_url( 'events.php', array( 'privacy' => $privacy_mode ? '1' : '0' ) ); ?>" style="color: inherit; text-decoration: none; display: block; margin-bottom: 15px;">
                        <h3 style="margin: 0; color: #007cba; cursor: pointer; transition: color 0.2s;">🗓️ Upcoming Events →</h3>
                    </a>
                    <?php
                    $upcoming_events = get_upcoming_events_for_display( $team_data );
                    render_upcoming_events_sidebar( $upcoming_events, $privacy_mode );
                    ?>
                </div>
            </div>

        <?php elseif ( $action === 'person' && ! empty( $person ) ) : ?>
            <?php
            $person_data = null;
            $is_team_member = false;
            $is_alumni = false;
            
            if ( isset( $team_data['team_members'][ $person ] ) ) {
                $person_data = $team_data['team_members'][ $person ];
                $is_team_member = true;
            } elseif ( isset( $team_data['leadership'][ $person ] ) ) {
                $person_data = $team_data['leadership'][ $person ];
            } elseif ( isset( $team_data['alumni'][ $person ] ) ) {
                $person_data = $team_data['alumni'][ $person ];
                $is_alumni = true;
            }
            
            if ( ! $person_data ) {
                echo '<p>Person not found.</p>';
                echo '<a href="?">← Back to Overview</a>';
            } else {
            ?>
                <div class="back-link">
                    <a href="<?php echo build_team_url( 'index.php' ); ?>">← Back to Team Overview</a>
                </div>

                <div class="header" style="flex-wrap: wrap;">
                    <div>
                        <h1><a href="<?php echo build_team_url( 'index.php' ); ?>" style="color: inherit; text-decoration: none;"><?php echo htmlspecialchars( display_name_with_nickname( $person_data, $privacy_mode ) ); ?></a>
                            <?php if ( $is_alumni ) : ?>
                                <span style="color: #999; font-size: 18px; font-weight: normal;">(Alumni)</span>
                            <?php endif; ?>
                        </h1>
                        <p style="color: #666;">@<?php echo htmlspecialchars( mask_username( $person_data->username, $privacy_mode ) ); ?></p>
                        <?php if ( ! empty( $person_data->role ) ) : ?>
                            <p><strong><?php echo htmlspecialchars( $person_data->role ); ?></strong></p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="section">
                    <h2>Quick Links</h2>
                    <div class="links">
                        <a href="<?php echo build_team_url( 'admin.php', array( 'edit_member' => $person ) ); ?>">✏️ Edit Person</a>
                        <?php render_person_links( $person_data->links, 16 ); ?>
                        <?php if ( $is_team_member && ! empty( $person_data->username ) ) : ?>
                            <?php
                            $last_month = date( 'Y-m', strtotime( 'last month') );
                            $start_date = $last_month . '-01';
                            $end_date = date( 'Y-m-d', strtotime( $start_date ) );
                            $activity_url = $team_data['activity_url_prefix'] . '&member=' . urlencode( $person_data->username ) . "&start={$start_date}&end={$end_date}";
                            ?>
                            📊 Team Activity <a href="<?php echo htmlspecialchars( $activity_url ); ?>" target="_blank" class="activity-link">Last Month</a>
                            <?php
                            $activity_url = $team_data['activity_url_prefix'] . '&member=' . urlencode( $person_data->username );
                            ?>
                            <a href="<?php echo htmlspecialchars( $activity_url ); ?>" target="_blank" class="activity-link">Last Week</a>
                        <?php elseif ( $is_team_member ) : ?>
                            <span style="color: #666; font-style: italic;">Team Activity (Username not configured)</span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php
                // Get upcoming events and personal details
                $upcoming_events = $person_data->get_upcoming_events();
                $kids_with_ages = $person_data->get_kids_ages();
                ?>

                <?php if ( ! empty( $upcoming_events ) || ! empty( $person_data->birthday ) || ! empty( $person_data->company_anniversary ) || ! empty( $kids_with_ages ) || ! empty( $person_data->notes ) || ! empty( $person_data->location ) ) : ?>
                    <div class="section">
                        <h2>Personal Details</h2>
                        
                        <?php if ( ! empty( $upcoming_events ) ) : ?>
                            <div style="background: #e8f5e8; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                                <h3 style="border-bottom: 0; margin-top: 0; color: #2d5a2d;">🗓️ Upcoming Events</h3>
                                <ul style="margin-bottom: 0;">
                                    <?php foreach ( $upcoming_events as $event ) : ?>
                                        <li><?php echo htmlspecialchars( mask_event_description( $event->description, $privacy_mode, array( $person_data ) ) ); ?> - <?php echo $privacy_mode && in_array( $event->type, array( 'birthday', 'anniversary' ) ) ? '[Hidden]' : $event->date->format( 'F j, Y' ); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if ( ! empty( $person_data->birthday ) ) : ?>
                            <p><strong>🎂 Birthday:</strong> <?php echo htmlspecialchars( mask_birthday_display( $person_data, $privacy_mode ) ); ?></p>
                        <?php endif; ?>

                        <?php if ( ! empty( $person_data->company_anniversary ) ) : ?>
                            <?php
                            $anniversary_date = DateTime::createFromFormat( 'Y-m-d', $person_data->company_anniversary );
                            if ( $anniversary_date ) {
                                if ( $privacy_mode ) {
                                    $years_at_company = (int) date( 'Y' ) - (int) $anniversary_date->format( 'Y' );
                                    echo '<p><strong>🏢 Company Anniversary:</strong> ' . $years_at_company . ' years (date hidden)</p>';
                                } else {
                                    $years_at_company = (int) date( 'Y' ) - (int) $anniversary_date->format( 'Y' );
                                    echo '<p><strong>🏢 Company Anniversary:</strong> ' . htmlspecialchars( $anniversary_date->format( 'F j, Y' ) ) . ' (' . $years_at_company . ' years)</p>';
                                }
                            }
                            ?>
                        <?php endif; ?>

                        <?php if ( ! empty( $person_data->location ) ) : ?>
                            <p>
                                <strong>🌍 Location:</strong> 
                                <a href="https://maps.google.com/maps?q=<?php echo urlencode( $person_data->location ); ?>" target="_blank" style="color: #007cba; text-decoration: none;"><?php echo htmlspecialchars( $person_data->location ); ?></a>
                                <span id="time-<?php echo htmlspecialchars( $person ); ?>" style="margin-left: 10px; color: #666; font-size: 14px;"></span>
                            </p>
                        <?php endif; ?>

                        <?php if ( ! empty( $person_data->partner ) ) : ?>
                            <p><strong>💑 Partner:</strong> <?php echo htmlspecialchars( $person_data->partner ); ?></p>
                        <?php endif; ?>

                        <?php if ( ! empty( $kids_with_ages ) ) : ?>
                            <div style="margin: 15px 0;">
                                <strong>👨‍👩‍👧‍👦 Children:</strong>
                                <?php if ( $privacy_mode ) : ?>
                                    <?php echo count( $kids_with_ages ); ?> child<?php echo count( $kids_with_ages ) !== 1 ? 'ren' : ''; ?>
                                <?php else : ?>
                                    <ul style="margin: 5px 0 0 20px;">
                                        <?php foreach ( $kids_with_ages as $kid ) : ?>
                                            <li>
                                                <?php echo htmlspecialchars( $kid['name'] ); ?>
                                                <?php if ( isset( $kid['age'] ) ) : ?>
                                                    (<?php echo $kid['age']; ?> years old)
                                                <?php endif; ?>
                                                <?php if ( ! empty( $kid['birthday_display'] ) ) : ?>
                                                    - <?php echo htmlspecialchars( $kid['birthday_display'] ); ?>
                                                    <?php if ( ! empty( $kid['time_to_birthday'] ) ) : ?>
                                                        • <?php echo htmlspecialchars( $kid['time_to_birthday'] ); ?>
                                                    <?php endif; ?>
                                                <?php elseif ( ! empty( $kid['birth_year'] ) ) : ?>
                                                    - born <?php echo $kid['birth_year']; ?>
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ( ! empty( $person_data->notes ) ) : ?>
                            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 15px;">
                                <strong>📝 Notes:</strong>
                                <p style="margin: 10px 0 0 0;"><?php echo nl2br( htmlspecialchars( $person_data->notes ) ); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            <?php } ?>

        <?php else : ?>
            <div class="header">
                <h1>Page Not Found</h1>
                <p><a href="?<?php echo $current_team !== 'team' ? 'team=' . urlencode( $current_team ) : ''; ?>">← Back to Team Overview</a></p>
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
            <?php if ( $action === 'person' ) : ?>
                | <a href="<?php echo build_team_url( 'admin.php', array( 'tab' => 'team', 'edit_person' => $person ) ); ?>" style="color: #666; text-decoration: none;">✏️ Edit Person</a>
            <?php endif; ?>
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
            
            // Initialize timezone display for person pages
            <?php if ( $action === 'person' && ! empty( $person_data ) && ( ! empty( $person_data->location ) || ! empty( $person_data->timezone ) ) ) : ?>
            createTimeUpdater('<?php echo addslashes( $person_data->timezone ); ?>', '<?php echo addslashes( $person ); ?>');
            <?php endif; ?>
        });
    </script>
</body>
</html>