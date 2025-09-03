<?php
/**
 * Common functions shared between admin.php and index.php
 */

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
		if ( strpos( $basename, '.bak' ) === false && strpos( $basename, 'bak-' ) === false ) {
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