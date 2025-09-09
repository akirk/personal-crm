<?php
/**
 * Common functions shared between admin.php and index.php
 */
require_once __DIR__ . '/event.php';

/**
 * Get SVG icon for a link based on its text or URL
 */
function get_link_icon( $link_text, $link_url, $size = 16 ) {
	if ( 0 === strpos( $link_url, 'https://linear.app/' ) ) {
		return '<svg xmlns="http://www.w3.org/2000/svg" fill="none" width="' . $size . '" height="' . $size . '" viewBox="0 0 100 100" style="vertical-align: middle; margin-bottom: 4px; margin-right: 4px"><path fill="currentColor" d="M1.22541 61.5228c-.2225-.9485.90748-1.5459 1.59638-.857L39.3342 97.1782c.6889.6889.0915 1.8189-.857 1.5964C20.0515 94.4522 5.54779 79.9485 1.22541 61.5228ZM.00189135 46.8891c-.01764375.2833.08887215.5599.28957165.7606L52.3503 99.7085c.2007.2007.4773.3075.7606.2896 2.3692-.1476 4.6938-.46 6.9624-.9259.7645-.157 1.0301-1.0963.4782-1.6481L2.57595 39.4485c-.55186-.5519-1.49117-.2863-1.648174.4782-.465915 2.2686-.77832 4.5932-.92588465 6.9624ZM4.21093 29.7054c-.16649.3738-.08169.8106.20765 1.1l64.77602 64.776c.2894.2894.7262.3742 1.1.2077 1.7861-.7956 3.5171-1.6927 5.1855-2.684.5521-.328.6373-1.0867.1832-1.5407L8.43566 24.3367c-.45409-.4541-1.21271-.3689-1.54074.1832-.99132 1.6684-1.88843 3.3994-2.68399 5.1855ZM12.6587 18.074c-.3701-.3701-.393-.9637-.0443-1.3541C21.7795 6.45931 35.1114 0 49.9519 0 77.5927 0 100 22.4073 100 50.0481c0 14.8405-6.4593 28.1724-16.7199 37.3375-.3903.3487-.984.3258-1.3542-.0443L12.6587 18.074Z"/></svg> ';
	} elseif ( $link_text === '1:1 doc' ) {
		return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="currentColor" style="vertical-align: middle; margin-bottom: 2px; margin-right: 4px"><path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/><circle cx="9" cy="13" r="1.5"/><circle cx="15" cy="13" r="1.5"/><path d="M9,16H15V18H9V16Z"/></svg>';
	} elseif ( $link_text === 'HR monthly' ) {
		return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="currentColor" style="vertical-align: middle; margin-right: 4px"><path d="M19,3H18V1H16V3H8V1H6V3H5A2,2 0 0,0 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19V5A2,2 0 0,0 19,3M19,19H5V8H19V19M5,6V5H19V6H5Z"/><rect x="7" y="10" width="2" height="2"/><rect x="11" y="10" width="2" height="2"/><rect x="15" y="10" width="2" height="2"/><rect x="7" y="14" width="2" height="2"/><rect x="11" y="14" width="2" height="2"/></svg>';
	} elseif ( $link_text === 'WordPress.org' ) {
		return '<svg fill="currentColor" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"  width="' . $size . '" height="' . $size . '"  viewBox="0 0 512 512" enable-background="new 0 0 512 512" xml:space="preserve" style="vertical-align: middle; margin-bottom: 4px; margin-right: 4px"><g id="5151e0c8492e5103c096af88a51f5fb6"><path display="inline" d="M256,0.5C115.117,0.5,0.5,115.109,0.5,255.992C0.5,396.874,115.117,511.5,256,511.5
		c140.879,0,255.5-114.626,255.5-255.508C511.5,115.109,396.879,0.5,256,0.5z M26.287,255.992c0-33.306,7.145-64.923,19.89-93.488
		l109.582,300.225C79.117,425.502,26.287,346.914,26.287,255.992z M256,485.722c-22.547,0-44.309-3.307-64.898-9.361l68.932-200.274
		l70.604,193.446c0.466,1.135,1.035,2.179,1.646,3.165C308.406,481.102,282.748,485.722,256,485.722z M287.659,148.286
		c13.827-0.724,26.29-2.179,26.29-2.179c12.376-1.464,10.916-19.658-1.468-18.93c0,0-37.207,2.919-61.23,2.919
		c-22.568,0-60.494-2.919-60.494-2.919c-12.388-0.728-13.839,18.198-1.456,18.93c0,0,11.715,1.455,24.095,2.179l35.784,98.063
		l-50.277,150.767l-83.649-248.83c13.84-0.724,26.286-2.179,26.286-2.179c12.372-1.464,10.912-19.658-1.468-18.93
		c0,0-37.198,2.919-61.222,2.919c-4.309,0-9.386-0.108-14.784-0.283C105.141,67.457,175.745,26.274,256,26.274
		c59.8,0,114.251,22.868,155.121,60.315c-0.989-0.058-1.958-0.183-2.978-0.183c-22.563,0-38.574,19.653-38.574,40.771
		c0,18.93,10.92,34.948,22.564,53.874c8.737,15.299,18.938,34.953,18.938,63.355c0,19.657-7.56,42.475-17.479,74.259l-22.917,76.554
		L287.659,148.286z M371.486,454.545l70.163-202.861c13.104-32.77,17.47-58.977,17.47-82.272c0-8.458-0.558-16.31-1.547-23.625
		c17.932,32.715,28.137,70.262,28.137,110.205C485.709,340.738,439.782,414.727,371.486,454.545z"></path></g></svg>';
	} elseif ( $link_text === 'LinkedIn' ) {
		return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="currentColor" style="vertical-align: middle; margin-right: 4px"><path d="M19 3A2 2 0 0 1 21 5V19A2 2 0 0 1 19 21H5A2 2 0 0 1 3 19V5A2 2 0 0 1 5 3H19M18.5 18.5V13.2A3.26 3.26 0 0 0 15.24 9.94C14.39 9.94 13.4 10.46 12.92 11.24V10.13H10.13V18.5H12.92V13.57C12.92 12.8 13.54 12.17 14.31 12.17A1.4 1.4 0 0 1 15.71 13.57V18.5H18.5M6.88 8.56A1.68 1.68 0 0 0 8.56 6.88C8.56 5.95 7.81 5.19 6.88 5.19S5.19 5.95 5.19 6.88A1.69 1.69 0 0 0 6.88 8.56M8.27 18.5V10.13H5.5V18.5H8.27Z"/></svg>';
	} elseif ( $link_text === 'GitHub' ) {
		return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="currentColor" style="vertical-align: middle; margin-right: 4px"><rect width="24" height="24" fill="none"/><path d="M12,2A10,10,0,0,0,8.84,21.5c.5.08.66-.23.66-.5V19.31C6.73,19.91,6.14,18,6.14,18A2.69,2.69,0,0,0,5,16.5c-.91-.62.07-.6.07-.6a2.1,2.1,0,0,1,1.53,1,2.15,2.15,0,0,0,2.91.83,2.16,2.16,0,0,1,.63-1.34C8,16.17,5.62,15.31,5.62,11.5a3.87,3.87,0,0,1,1-2.71,3.58,3.58,0,0,1,.1-2.64s.84-.27,2.75,1a9.63,9.63,0,0,1,5,0c1.91-1.29,2.75-1,2.75-1a3.58,3.58,0,0,1,.1,2.64,3.87,3.87,0,0,1,1,2.71c0,3.82-2.34,4.66-4.57,4.91a2.39,2.39,0,0,1,.69,1.85V21c0,.27.16.59.67.5A10,10,0,0,0,12,2Z"/></svg>';
	}
	return '';
}

/**
 * Render person links with icons
 */
function render_person_links( $links, $icon_size = 12 ) {
	foreach ( $links as $link_text => $link_url ) {
		if ( ! empty( $link_url ) && ! in_array( $link_text, array( 'WordPress.org', 'LinkedIn', 'Matticspace' )) ) {
			echo '<a href="' . htmlspecialchars( $link_url ) . '" target="_blank">';
			echo get_link_icon( $link_text, $link_url, $icon_size );
			echo htmlspecialchars( $link_text );
			echo '</a>';
		}
	}
}

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


/**
 * Get the appropriate month for HR feedback based on current date
 * HR feedback is for the previous month, but starting on the 15th we start feedback for current month
 */
function get_hr_feedback_month() {
	$today = new DateTime();
	$day = (int) $today->format('d');
	
	if ( $day >= 15 ) {
		// On or after 15th: feedback for current month
		return $today->format('Y-m');
	} else {
		// Before 15th: feedback for previous month
		return $today->modify('-1 month')->format('Y-m');
	}
}

/**
 * Build URL with team parameter
 */
function build_team_url( $base_url, $additional_params = array() ) {
	global $current_team;
	$params = array();
	if ( $current_team !== 'team' ) {
		$params['team'] = $current_team;
	}
	$params = array_merge( $params, $additional_params );
	
	if ( ! empty( $params ) ) {
		return $base_url . '?' . http_build_query( $params );
	}
	return $base_url;
}

/**
 * Get all available team files (excluding backup files)
 */
function get_available_teams() {
	$teams = array();
	$json_files = glob( __DIR__ . '/../*.json' );
	
	foreach ( $json_files as $file ) {
		$basename = basename( $file, '.json' );
		// Skip backup files
		if ( $basename !== 'hr-feedback' && strpos( $basename, '.bak' ) === false && strpos( $basename, 'bak-' ) === false ) {
			$teams[] = $basename;
		}
	}
	
	sort( $teams );
	return $teams;
}

/**
 * Get team name from config file
 */
function get_team_name_from_file( $team_slug ) {
	$file_path = __DIR__ . '/../' . $team_slug . '.json';
	if ( file_exists( $file_path ) ) {
		$config = json_decode( file_get_contents( $file_path ), true );
		if ( json_last_error() === JSON_ERROR_NONE && isset( $config['team_name'] ) ) {
			return $config['team_name'];
		}
	}
	return ucfirst( str_replace( '_', ' ', $team_slug ) );
}

/**
 * Privacy mode helper functions
 */
function mask_name( $full_name, $privacy_mode ) {
	if ( ! $privacy_mode ) {
		return $full_name;
	}
	
	$parts = explode( ' ', trim( $full_name ) );
	if ( count( $parts ) <= 1 ) {
		return $full_name; // Only first name, no masking needed
	}
	
	// Return first name + masked last name
	$first_name = $parts[0];
	$last_name_initial = isset( $parts[ count( $parts ) - 1 ] ) ? substr( $parts[ count( $parts ) - 1 ], 0, 1 ) . '.' : '';
	
	return $first_name . ' ' . $last_name_initial;
}

function mask_username( $username, $privacy_mode ) {
	if ( ! $privacy_mode ) {
		return $username;
	}
	
	if ( strlen( $username ) <= 3 ) {
		return $username; // Too short to mask meaningfully
	}
	
	return substr( $username, 0, 3 ) . '...';
}

/**
 * Get the default team slug (team marked with default: true)
 */
function get_default_team() {
	$available_teams = get_available_teams();
	if ( count( $available_teams ) === 1 ) {
		return $available_teams[0];
	}

	foreach ( $available_teams as $team_slug ) {
		$file_path = __DIR__ . '/../' . $team_slug . '.json';
		if ( file_exists( $file_path ) ) {
			$config = json_decode( file_get_contents( $file_path ), true );
			if ( json_last_error() === JSON_ERROR_NONE && isset( $config['default'] ) && $config['default'] ) {
				return $team_slug;
			}
		}
	}

	return '';
}

/**
 * Get team statistics for all teams
 */
function get_all_teams_stats() {
	$available_teams = get_available_teams();
	$stats = array();
	
	foreach ( $available_teams as $team_slug ) {
		$file_path = __DIR__ . '/../' . $team_slug . '.json';
		if ( file_exists( $file_path ) ) {
			$config = json_decode( file_get_contents( $file_path ), true );
			if ( json_last_error() === JSON_ERROR_NONE ) {
				$team_members_count = count( $config['team_members'] ?? array() );
				$leadership_count = count( $config['leadership'] ?? array() );
				$alumni_count = count( $config['alumni'] ?? array() );
				$total_people = $team_members_count + $leadership_count + $alumni_count;
				
				$stats[] = array(
					'slug' => $team_slug,
					'name' => $config['team_name'] ?? ucfirst( str_replace( '_', ' ', $team_slug ) ),
					'team_members' => $team_members_count,
					'leadership' => $leadership_count,
					'alumni' => $alumni_count,
					'total_people' => $total_people,
					'is_default' => isset( $config['default'] ) && $config['default'],
					'url' => build_team_url_extended( 'index.php', array(), $team_slug )
				);
			}
		}
	}
	
	// Sort by name
	usort( $stats, function( $a, $b ) {
		return strcasecmp( $a['name'], $b['name'] );
	});
	
	return $stats;
}

/**
 * Get all people from all teams for global search
 */
function get_all_people_from_all_teams( $privacy_mode = false ) {
	$available_teams = get_available_teams();
	$all_people = array();
	
	foreach ( $available_teams as $team_slug ) {
		$file_path = __DIR__ . '/../' . $team_slug . '.json';
		if ( file_exists( $file_path ) ) {
			$config = json_decode( file_get_contents( $file_path ), true );
			if ( json_last_error() === JSON_ERROR_NONE ) {
				$team_name = $config['team_name'] ?? ucfirst( str_replace( '_', ' ', $team_slug ) );
				
				// Team members
				foreach ( $config['team_members'] ?? array() as $username => $member_data ) {
					$links_js = array();
					foreach ( $member_data['links'] ?? array() as $text => $url ) {
						$links_js[] = array(
							'text' => $text,
							'url' => $url
						);
					}
					
					// Handle Linear links
					if ( isset( $member_data['linear'] ) && ! empty( $member_data['linear'] ) ) {
						$links_js[] = array(
							'text' => 'Linear',
							'url' => 'https://linear.app/a8c/profiles/' . $member_data['linear']
						);
					}
					
					$all_people[] = array(
						'username' => $username,
						'name' => mask_name( $member_data['name'] ?? '', $privacy_mode ),
						'nickname' => $privacy_mode ? '' : ( $member_data['nickname'] ?? '' ),
						'role' => $member_data['role'] ?? '',
						'type' => 'Team Member',
						'team' => $team_name,
						'team_slug' => $team_slug,
						'location' => $member_data['location'] ?? '',
						'birthday' => get_birthday_display_for_js( $member_data, $privacy_mode ),
						'links' => $links_js,
						'url' => build_team_url_extended( 'index.php', array( 'person' => $username, 'privacy' => $privacy_mode ? '1' : '0' ), $team_slug )
					);
				}
				
				// Leadership
				foreach ( $config['leadership'] ?? array() as $username => $leader_data ) {
					$links_js = array();
					foreach ( $leader_data['links'] ?? array() as $text => $url ) {
						$links_js[] = array(
							'text' => $text,
							'url' => $url
						);
					}
					
					$all_people[] = array(
						'username' => $username,
						'name' => mask_name( $leader_data['name'] ?? '', $privacy_mode ),
						'nickname' => $privacy_mode ? '' : ( $leader_data['nickname'] ?? '' ),
						'role' => $leader_data['role'] ?? '',
						'type' => 'Leadership',
						'team' => $team_name,
						'team_slug' => $team_slug,
						'location' => $leader_data['location'] ?? '',
						'birthday' => get_birthday_display_for_js( $leader_data, $privacy_mode ),
						'links' => $links_js,
						'url' => build_team_url_extended( 'index.php', array( 'person' => $username, 'privacy' => $privacy_mode ? '1' : '0' ), $team_slug )
					);
				}
				
				// Alumni
				foreach ( $config['alumni'] ?? array() as $username => $alumni_data ) {
					$links_js = array();
					foreach ( $alumni_data['links'] ?? array() as $text => $url ) {
						$links_js[] = array(
							'text' => $text,
							'url' => $url
						);
					}
					
					$all_people[] = array(
						'username' => $username,
						'name' => mask_name( $alumni_data['name'] ?? '', $privacy_mode ),
						'nickname' => $privacy_mode ? '' : ( $alumni_data['nickname'] ?? '' ),
						'role' => $alumni_data['role'] ?? '',
						'type' => 'Alumni',
						'team' => $team_name,
						'team_slug' => $team_slug,
						'location' => $alumni_data['location'] ?? '',
						'birthday' => get_birthday_display_for_js( $alumni_data, $privacy_mode ),
						'links' => $links_js,
						'url' => build_team_url_extended( 'index.php', array( 'person' => $username, 'privacy' => $privacy_mode ? '1' : '0' ), $team_slug )
					);
				}
			}
		}
	}
	
	// Sort by name
	usort( $all_people, function( $a, $b ) {
		return strcasecmp( $a['name'], $b['name'] );
	});
	
	return $all_people;
}

/**
 * Helper function to build URL with different team (extended version)
 */
function build_team_url_extended( $base_url, $additional_params = array(), $team_slug = null ) {
	$target_team = $team_slug;
	
	$params = array();
	if ( $target_team && $target_team !== 'team' ) {
		$params['team'] = $target_team;
	}
	$params = array_merge( $params, $additional_params );
	
	if ( ! empty( $params ) ) {
		return $base_url . '?' . http_build_query( $params );
	}
	return $base_url;
}

/**
 * Helper function to get birthday display for JavaScript
 */
function get_birthday_display_for_js( $person_data, $privacy_mode ) {
	if ( empty( $person_data['birthday'] ) ) {
		return '';
	}
	
	if ( $privacy_mode ) {
		// For privacy mode, just show age if available
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $person_data['birthday'] ) ) {
			$birth_date = DateTime::createFromFormat( 'Y-m-d', $person_data['birthday'] );
			$current_date = new DateTime();
			if ( $birth_date ) {
				$age = $current_date->diff( $birth_date )->y;
				return 'Age ' . $age;
			}
		}
		return '[Hidden]';
	}
	
	// For non-privacy mode, return formatted display
	if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $person_data['birthday'] ) ) {
		$birth_date = DateTime::createFromFormat( 'Y-m-d', $person_data['birthday'] );
		if ( $birth_date ) {
			return $birth_date->format( 'M j, Y' );
		}
	} elseif ( preg_match( '/^\d{2}-\d{2}$/', $person_data['birthday'] ) ) {
		$display_date = DateTime::createFromFormat( 'm-d', $person_data['birthday'] );
		if ( $display_date ) {
			return $display_date->format( 'M j' );
		}
	}
	
	return $person_data['birthday'];
}

/**
 * Load team configuration from JSON file and convert to Person objects
 */
function load_team_config_with_objects( $team_slug = 'team', $privacy_mode = false ) {
	$config_file = __DIR__ . '/../' . $team_slug . '.json';

	if ( ! file_exists( $config_file ) ) {
		// Redirect to team creation page
		$create_team_url = 'admin.php?create_team=new' . ( $team_slug !== 'team' ? '&team=' . urlencode( $team_slug ) : '' );
		header( 'Location: ' . $create_team_url );
		exit;
	}

	$json_content = file_get_contents( $config_file );
	$config = json_decode( $json_content, true );

	if ( json_last_error() !== JSON_ERROR_NONE ) {
		die( 'Error: Invalid JSON in team-config.json file: ' . json_last_error_msg() );
	}

	// Convert arrays to Person objects
	$team_members = array();
	foreach ( $config['team_members'] as $username => $member_data ) {
		// Handle migration from old format to new format
		$links = array();
		if ( isset( $member_data['links'] ) ) {
			// New format - use links directly
			$links = $member_data['links'];
		} else {
			// Old format - migrate one_on_one and hr_feedback to links
			if ( ! empty( $member_data['one_on_one'] ) ) {
				$links['1:1 doc'] = $member_data['one_on_one'];
			}
			if ( ! empty( $member_data['hr_feedback'] ) ) {
				$links['HR monthly'] = $member_data['hr_feedback'];
			}
		}

		if ( isset( $member_data['linear'] ) && ! empty( $member_data['linear'] ) ) {
			$links['Linear'] = 'https://linear.app/a8c/profiles/' . $member_data['linear'];
		}

		if ( isset( $member_data['wordpress'] ) && ! empty( $member_data['wordpress'] ) ) {
			$links['WordPress.org'] = 'https://profiles.wordpress.org/' . $member_data['wordpress'];
		}

		if ( isset( $member_data['linkedin'] ) && ! empty( $member_data['linkedin'] ) ) {
			$links['LinkedIn'] = 'https://linkedin.com/in/' . $member_data['linkedin'];
		}

		$person = new Person(
			$member_data['name'],
			$username,
			$links,
			$member_data['role'] ?? '',
			$privacy_mode
		);

		// Set additional properties
		$person->nickname = $privacy_mode ? '' : ( $member_data['nickname'] ?? '' );
		$person->birthday = $member_data['birthday'] ?? '';
		$person->company_anniversary = $member_data['company_anniversary'] ?? '';
		$person->partner = $member_data['partner'] ?? '';
		$person->kids = $member_data['kids'] ?? array();
		$person->notes = $member_data['notes'] ?? '';
		$person->location = $member_data['location'] ?? $member_data['town'] ?? ''; // Support both 'location' and legacy 'town'
		$person->timezone = $member_data['timezone'] ?? '';
		$person->github = $member_data['github'] ?? '';
		$person->github_repos = $member_data['github_repos'] ?? array();
		$person->wordpress = $member_data['wordpress'] ?? '';
		$person->linkedin = $member_data['linkedin'] ?? '';
		$person->personal_events = $member_data['personal_events'] ?? array();

		$team_members[$username] = $person;
	}

	$leadership = array();
	foreach ( $config['leadership'] as $username => $leader_data ) {
		// Handle migration from old format to new format
		$links = array();
		if ( isset( $leader_data['links'] ) ) {
			// New format - use links directly
			$links = $leader_data['links'];
		} else {
			// Old format - migrate one_on_one to links (no hr_feedback for leaders)
			if ( ! empty( $leader_data['one_on_one'] ) ) {
				$links['1:1 doc'] = $leader_data['one_on_one'];
			}
		}

		if ( isset( $leader_data['wordpress'] ) && ! empty( $leader_data['wordpress'] ) ) {
			$links['WordPress.org'] = 'https://profiles.wordpress.org/' . $leader_data['wordpress'];
		}

		if ( isset( $leader_data['linkedin'] ) && ! empty( $leader_data['linkedin'] ) ) {
			$links['LinkedIn'] = 'https://linkedin.com/in/' . $leader_data['linkedin'];
		}

		$person = new Person(
			$leader_data['name'],
			$username,
			$links,
			$leader_data['role'] ?? '',
			$privacy_mode
		);

		// Set additional properties
		$person->nickname = $privacy_mode ? '' : ( $leader_data['nickname'] ?? '' );
		$person->birthday = $leader_data['birthday'] ?? '';
		$person->company_anniversary = $leader_data['company_anniversary'] ?? '';
		$person->partner = $leader_data['partner'] ?? '';
		$person->kids = $leader_data['kids'] ?? array();
		$person->notes = $leader_data['notes'] ?? '';
		$person->location = $leader_data['location'] ?? $leader_data['town'] ?? ''; // Support both 'location' and legacy 'town'
		$person->timezone = $leader_data['timezone'] ?? '';
		$person->github = $leader_data['github'] ?? '';
		$person->github_repos = $leader_data['github_repos'] ?? array();
		$person->wordpress = $leader_data['wordpress'] ?? '';
		$person->linkedin = $leader_data['linkedin'] ?? '';
		$person->personal_events = $leader_data['personal_events'] ?? array();

		$leadership[$username] = $person;
	}

	$alumni = array();
	foreach ( $config['alumni'] ?? array() as $username => $alumni_data ) {
		// Handle migration from old format to new format
		$links = array();
		if ( isset( $alumni_data['links'] ) ) {
			// New format - use links directly
			$links = $alumni_data['links'];
		} else {
			// Old format - migrate one_on_one and hr_feedback to links
			if ( ! empty( $alumni_data['one_on_one'] ) ) {
				$links['1:1 doc'] = $alumni_data['one_on_one'];
			}
			if ( ! empty( $alumni_data['hr_feedback'] ) ) {
				$links['HR monthly'] = $alumni_data['hr_feedback'];
			}
		}

		if ( isset( $alumni_data['wordpress'] ) && ! empty( $alumni_data['wordpress'] ) ) {
			$links['WordPress.org'] = 'https://profiles.wordpress.org/' . $alumni_data['wordpress'];
		}

		if ( isset( $alumni_data['linkedin'] ) && ! empty( $alumni_data['linkedin'] ) ) {
			$links['LinkedIn'] = 'https://linkedin.com/in/' . $alumni_data['linkedin'];
		}

		$person = new Person(
			$alumni_data['name'],
			$username,
			$links,
			$alumni_data['role'] ?? '',
			$privacy_mode
		);

		// Set additional properties
		$person->nickname = $privacy_mode ? '' : ( $alumni_data['nickname'] ?? '' );
		$person->birthday = $alumni_data['birthday'] ?? '';
		$person->company_anniversary = $alumni_data['company_anniversary'] ?? '';
		$person->partner = $alumni_data['partner'] ?? '';
		$person->kids = $alumni_data['kids'] ?? array();
		$person->notes = $alumni_data['notes'] ?? '';
		$person->location = $alumni_data['location'] ?? $alumni_data['town'] ?? '';
		$person->timezone = $alumni_data['timezone'] ?? '';
		$person->github = $alumni_data['github'] ?? '';
		$person->github_repos = $alumni_data['github_repos'] ?? array();
		$person->wordpress = $alumni_data['wordpress'] ?? '';
		$person->linkedin = $alumni_data['linkedin'] ?? '';
		$person->personal_events = $alumni_data['personal_events'] ?? array();

		$alumni[$username] = $person;
	}

	// Sort all collections by name
	uasort( $team_members, function( $a, $b ) {
		return strcasecmp( $a->name, $b->name );
	} );

	uasort( $leadership, function( $a, $b ) {
		return strcasecmp( $a->name, $b->name );
	} );

	uasort( $alumni, function( $a, $b ) {
		return strcasecmp( $a->name, $b->name );
	} );

	// Convert team events to Event objects
	$events = array();
	foreach ( $config['events'] ?? array() as $event_data ) {
		$events[] = Event::from_team_event( $event_data );
	}

	return array(
		'activity_url_prefix' => $config['activity_url_prefix'],
        'team_name' => $config['team_name'],
		'team_links' => $config['team_links'] ?? array(),
		'team_members' => $team_members,
		'leadership' => $leadership,
		'alumni' => $alumni,
		'events' => $events,
	);
}

/**
 * Display name with nickname
 */
function display_name_with_nickname( $person, $privacy_mode = false ) {
	$name = $privacy_mode ? mask_name( $person->name, $privacy_mode ) : $person->name;

	if ( ! empty( $person->nickname ) && ! $privacy_mode ) {
		return $name . ' "' . htmlspecialchars( $person->nickname ) . '"';
	}

	return $name;
}

/**
 * Get upcoming events (within next 3 months) for display
 * Handles both Person objects (from index.php) and raw array data (from events.php)
 */
function get_upcoming_events_for_display( $team_data ) {
	$all_events = array();
	$current_date = new DateTime();
	$cutoff_date = clone $current_date;
	$cutoff_date->add( new DateInterval( 'P3M' ) ); // 3 months from now

	// Check if we have Person objects or raw arrays
	$all_people = array_merge( $team_data['team_members'], $team_data['leadership'], $team_data['alumni'] );

	// Only process personal events if we have Person objects (not raw arrays)
	if ( ! empty( $all_people ) ) {
		$first_person = reset( $all_people );
		if ( is_object( $first_person ) && method_exists( $first_person, 'get_upcoming_events' ) ) {
			// We have Person objects
			foreach ( $all_people as $person ) {
				$personal_events = $person->get_upcoming_events();
				$all_events = array_merge( $all_events, $personal_events );
			}
		}
	}

	// Add team and company events (within 3 months)
	foreach ( $team_data['events'] as $event ) {
		// Events are now Event objects from load_team_config_with_objects
		$start_date = $event->date;
		if ( $start_date && $start_date >= $current_date && $start_date <= $cutoff_date ) {
			$all_events[] = $event;
		}
	}

	// Sort all events by date
	usort( $all_events, function( $a, $b ) {
		return $a->date <=> $b->date;
	} );

	return $all_events;
}

/**
 * Render upcoming events sidebar content
 */
function render_upcoming_events_sidebar( $upcoming_events_or_person = null, $privacy_mode = null, $limit = 6, $current_person = null ) {
	// Handle different call signatures for backward compatibility
	if ( is_array( $upcoming_events_or_person ) ) {
		// Old signature: render_upcoming_events_sidebar( $upcoming_events, $privacy_mode, $limit, $current_person )
		$upcoming_events = $upcoming_events_or_person;
		if ( $privacy_mode === null ) {
			$privacy_mode = isset( $_GET['privacy'] ) && $_GET['privacy'] === '1';
		}
	} elseif ( is_string( $upcoming_events_or_person ) || $upcoming_events_or_person === null ) {
		// New signature: render_upcoming_events_sidebar( $current_person, $limit )
		$current_person = $upcoming_events_or_person;
		if ( is_numeric( $privacy_mode ) ) {
			$limit = $privacy_mode; // Second param was actually limit in new signature
		}
		$privacy_mode = isset( $_GET['privacy'] ) && $_GET['privacy'] === '1';
		
		// Get upcoming events based on whether we have a specific person or need team-wide events
		if ( $current_person ) {
			// Get events for specific person
			global $team_data;
			$person_data = null;
			if ( isset( $team_data['team_members'][ $current_person ] ) ) {
				$person_data = $team_data['team_members'][ $current_person ];
			} elseif ( isset( $team_data['leadership'][ $current_person ] ) ) {
				$person_data = $team_data['leadership'][ $current_person ];
			} elseif ( isset( $team_data['alumni'][ $current_person ] ) ) {
				$person_data = $team_data['alumni'][ $current_person ];
			}
			
			$upcoming_events = $person_data ? $person_data->get_upcoming_events_with_personal_dates() : array();
		} else {
			// Get team-wide events
			global $team_data;
			$upcoming_events = get_upcoming_events_for_display( $team_data );
		}
	} else {
		$upcoming_events = array();
	}
	if ( empty( $upcoming_events ) ) {
		echo '<p style="color: #666; font-style: italic; margin: 0;">No upcoming events</p>';
		return;
	}

	$displayed_events = array_slice( $upcoming_events, 0, $limit );
	$all_people = array(); // We would need this for masking, but for now keeping it simple

	foreach ( $displayed_events as $event ) {
		// Calculate days until event
		$today = new DateTime();
		$today->setTime( 0, 0, 0 ); // Reset to start of day for accurate comparison
		$event_date = clone $event->date;
		$event_date->setTime( 0, 0, 0 );
		$days_until = $today->diff( $event_date )->days;
		$is_past = $event_date < $today;
		?>
		<div class="event-item">
			<div style="display: flex; justify-content: space-between; align-items: flex-start;">
				<div class="event-date"><?php echo $privacy_mode && in_array( $event->type, array( 'birthday', 'anniversary' ) ) ? '[Hidden]' : $event->date->format( 'M j, Y' ); ?></div>
				<?php if ( ! $is_past && $days_until <= 120 ) : // Show for events within 30 days ?>
					<div style="font-size: 11px; color: #999; font-weight: normal;">
						<?php
						if ( $days_until == 0 ) {
							echo 'today';
						} elseif ( $days_until == 1 ) {
							echo 'in 1d';
						} elseif ( $days_until > 60 ) {
							echo 'in ' . floor( $days_until / 30 ) . 'mo';
						} else {
							echo 'in ' . $days_until . 'd';
						}
						?>
					</div>
				<?php endif; ?>
			</div>
			<div class="event-description"><?php 
				// Use title without person name if this event belongs to the current person
				if ( $current_person && $event->person && $event->person->username === $current_person ) {
					// Since we're viewing this person's own events, remove their name
					$description = $event->get_title_without_person_name();
					echo htmlspecialchars( $description );
				} else {
					$description = $event->description;
					
					// Remove age information from birthday descriptions when in privacy mode
					if ( $privacy_mode && $event->type === 'birthday' && strpos( $description, '(turning ' ) !== false ) {
						$description = preg_replace( '/\s*\(turning \d+\)/', '', $description );
					}
					
					// If this event has a person and we're not on that person's page, make the person name clickable
					if ( $event->person && ! $privacy_mode ) {
						$person_name = $event->person->name;
						$person_username = $event->person->username;
						$person_link = build_team_url( 'person.php', array( 'person' => $person_username ) );
						
						// Replace person name with clickable link in the description
						$clickable_name = '<a href="' . htmlspecialchars( $person_link ) . '" class="event-person-link">' . htmlspecialchars( $person_name ) . '</a>';
						$description = str_replace( htmlspecialchars( $person_name ), $clickable_name, htmlspecialchars( $description ) );
						echo $description;
					} else {
						echo htmlspecialchars( $description );
					}
				}
			?></div>
			<span class="event-type <?php echo htmlspecialchars( $event->type ); ?>"><?php echo ucfirst( $event->type ); ?></span>
			<?php if ( ! empty( $event->location ) ) : ?>
				<div class="event-location-small">📍 <a href="https://maps.google.com/maps?q=<?php echo urlencode( $event->location ); ?>" target="_blank" class="location-link"><?php echo htmlspecialchars( $event->location ); ?></a></div>
			<?php endif; ?>
			<?php if ( ! empty( $event->links ) ) : ?>
				<div class="event-links-small">
					<?php foreach ( $event->links as $link_text => $link_url ) : ?>
						<a href="<?php echo htmlspecialchars( $link_url ); ?>" target="_blank" class="event-link-small">
							<?php echo htmlspecialchars( $link_text ); ?> →
						</a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}

/**
 * Get all feedback for a person
 */
function get_person_feedback_history( $username ) {
    $feedback_file = __DIR__ . '/../hr-feedback.json';

    if ( ! file_exists( $feedback_file ) ) {
        return array();
    }

    $content = file_get_contents( $feedback_file );
    $feedback_data = json_decode( $content, true ) ?: array();

    return $feedback_data['feedback'][$username] ?? array();
}

/**
 * Simple HTML sanitizer - only allows links
 */
function sanitize_html( $html ) {
    // Allow only <a> tags with href and target attributes
    $allowed_tags = '<a>';
    $clean_html = strip_tags( $html, $allowed_tags );

    // Additional security: ensure href attributes don't contain javascript
    $clean_html = preg_replace('/javascript:/i', '', $clean_html);

    return $clean_html;
}

/**
 * Set a person as "not necessary" for HR feedback for a specific month
 */
function set_feedback_not_necessary( $data ) {
    $username = preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $data['username'] ?? '' ) );
    $month = preg_replace( '/[^0-9\-]/', '', $data['month'] ?? '' );
    $reason = trim( strip_tags( $data['reason'] ?? '' ) );

    if ( empty( $username ) || empty( $month ) || empty( $reason ) ) {
        return array( 'success' => false, 'message' => 'Missing required fields.' );
    }

    $feedback_file = __DIR__ . '/../hr-feedback.json';
    
    // Load existing data
    $content = file_get_contents( $feedback_file );
    $feedback_data = json_decode( $content, true ) ?: array( 'feedback' => array() );
    
    if ( ! isset( $feedback_data['feedback'][ $username ] ) ) {
        $feedback_data['feedback'][ $username ] = array();
    }
    
    // Set the "not necessary" status
    $feedback_data['feedback'][ $username ][ $month . '_not_necessary' ] = $reason;
    
    // Remove any existing regular feedback for this month
    if ( isset( $feedback_data['feedback'][ $username ][ $month ] ) ) {
        unset( $feedback_data['feedback'][ $username ][ $month ] );
    }
    
    // Save the data
    $json_content = json_encode( $feedback_data, JSON_PRETTY_PRINT );
    if ( file_put_contents( $feedback_file, $json_content ) !== false ) {
        return array( 'success' => true, 'message' => 'Successfully marked as not necessary.' );
    } else {
        return array( 'success' => false, 'message' => 'Failed to save data.' );
    }
}

/**
 * Remove "not necessary" status for a person for a specific month
 */
function remove_feedback_not_necessary( $data ) {
    $username = preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $data['username'] ?? '' ) );
    $month = preg_replace( '/[^0-9\-]/', '', $data['month'] ?? '' );

    if ( empty( $username ) || empty( $month ) ) {
        return array( 'success' => false, 'message' => 'Missing required fields.' );
    }

    $feedback_file = __DIR__ . '/../hr-feedback.json';
    
    // Load existing data
    $content = file_get_contents( $feedback_file );
    $feedback_data = json_decode( $content, true ) ?: array( 'feedback' => array() );
    
    if ( isset( $feedback_data['feedback'][ $username ][ $month . '_not_necessary' ] ) ) {
        unset( $feedback_data['feedback'][ $username ][ $month . '_not_necessary' ] );
        
        // Save the data
        $json_content = json_encode( $feedback_data, JSON_PRETTY_PRINT );
        if ( file_put_contents( $feedback_file, $json_content ) !== false ) {
            return array( 'success' => true, 'message' => 'Successfully removed not necessary status.' );
        } else {
            return array( 'success' => false, 'message' => 'Failed to save data.' );
        }
    }
    
    return array( 'success' => true, 'message' => 'Status was not set.' );
}

/**
 * Render the dark mode toggle button with all three icons
 */
function render_dark_mode_toggle( $aria_label = 'Toggle dark mode' ) {
    ?>
    <button id="dark-mode-toggle" type="button" aria-label="<?php echo htmlspecialchars( $aria_label ); ?>">
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
        <svg class="auto-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
            <line x1="8" y1="21" x2="16" y2="21"></line>
            <line x1="12" y1="17" x2="12" y2="21"></line>
        </svg>
    </button>
    <?php
}