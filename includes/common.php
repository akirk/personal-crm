<?php
/**
 * Common functions shared between admin.php and index.php
 */
require_once __DIR__ . '/event.php';


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
		$person->needs_hr_monthly = $member_data['needs_hr_monthly'] ?? false;

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
		$person->needs_hr_monthly = $leader_data['needs_hr_monthly'] ?? false;

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
		$person->needs_hr_monthly = $alumni_data['needs_hr_monthly'] ?? false;

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
function render_upcoming_events_sidebar( $upcoming_events, $privacy_mode = false, $limit = 10 ) {
	if ( empty( $upcoming_events ) ) {
		echo '<p style="color: #666; font-style: italic; margin: 0;">No upcoming events</p>';
		return;
	}

	$displayed_events = array_slice( $upcoming_events, 0, $limit );
	$all_people = array(); // We would need this for masking, but for now keeping it simple

	foreach ( $displayed_events as $event ) {
		?>
		<div class="event-item">
			<div class="event-date"><?php echo $privacy_mode && in_array( $event->type, array( 'birthday', 'anniversary' ) ) ? '[Hidden]' : $event->date->format( 'M j, Y' ); ?></div>
			<div class="event-description"><?php 
				$description = $event->description;
				// Remove age information from birthday descriptions when in privacy mode
				if ( $privacy_mode && $event->type === 'birthday' && strpos( $description, '(turning ' ) !== false ) {
					$description = preg_replace( '/\s*\(turning \d+\)/', '', $description );
				}
				echo htmlspecialchars( $description ); 
			?></div>
			<span class="event-type <?php echo htmlspecialchars( $event->type ); ?>"><?php echo ucfirst( $event->type ); ?></span>
			<?php if ( ! empty( $event->location ) ) : ?>
				<div style="font-size: 12px; color: #666; margin-top: 4px;">📍 <?php echo htmlspecialchars( $event->location ); ?></div>
			<?php endif; ?>
			<?php if ( ! empty( $event->links ) ) : ?>
				<div style="font-size: 12px; margin-top: 4px;">
					<?php foreach ( $event->links as $link_text => $link_url ) : ?>
						<a href="<?php echo htmlspecialchars( $link_url ); ?>" target="_blank" style="color: #007cba; text-decoration: none; margin-right: 8px;">
							<?php echo htmlspecialchars( $link_text ); ?> →
						</a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}