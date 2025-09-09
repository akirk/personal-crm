<?php
/**
 * Team Management Admin Tool
 * 
 * A web interface for creating and managing team.json configuration
 */

// Include common functions
require_once __DIR__ . '/includes/common.php';

// Error handling
ini_set( 'display_errors', 1 );
error_reporting( E_ALL );

// Get current team from URL parameter or POST (for form submissions)
$current_team = $_POST['team'] ?? $_GET['team'] ?? null;
if ( ! $current_team ) {
	header( 'Location: team-selection.php' );
	exit;
}

$config_file = __DIR__ . '/' . $current_team . '.json';
$action = $_POST['action'] ?? $_GET['action'] ?? 'dashboard';

// Determine active tab and privacy mode
$active_tab = $_GET['tab'] ?? 'general';
$is_adding_new = isset( $_GET['add'] ) && $_GET['add'] === 'new';
$is_creating_team = isset( $_GET['create_team'] ) && $_GET['create_team'] === 'new';

// Check if JSON file exists and redirect to team creation if not (unless already creating a team)
if ( ! file_exists( $config_file ) && ! $is_creating_team ) {
	$create_team_url = build_team_url( 'admin.php', array( 'create_team' => 'new' ) );
	header( 'Location: ' . $create_team_url );
	exit;
}
$privacy_mode = isset( $_GET['privacy'] ) && $_GET['privacy'] === '1';

// Check if we're editing a specific member or event
$edit_member = $_GET['edit_member'] ?? '';
$edit_event_index = $_GET['edit_event'] ?? '';
$edit_data = null;
$is_editing_member = false;
$is_editing_leader = false;
$is_editing_alumni = false;
$is_editing_event = false;


if ( ! empty( $edit_member ) ) {
	$config = load_or_create_config( $config_file );
	if ( isset( $config['team_members'][ $edit_member ] ) ) {
		$edit_data = $config['team_members'][ $edit_member ];
		$edit_data['username'] = $edit_member;
		$is_editing_member = true;
		$active_tab = 'members';
	} elseif ( isset( $config['leadership'][ $edit_member ] ) ) {
		$edit_data = $config['leadership'][ $edit_member ];
		$edit_data['username'] = $edit_member;
		$is_editing_leader = true;
		$active_tab = 'leadership';
	} elseif ( isset( $config['alumni'][ $edit_member ] ) ) {
		$edit_data = $config['alumni'][ $edit_member ];
		$edit_data['username'] = $edit_member;
		$is_editing_alumni = true;
		$active_tab = 'alumni';
	}
} elseif ( $edit_event_index !== '' && is_numeric( $edit_event_index ) ) {
	$config = load_or_create_config( $config_file );
	if ( isset( $config['events'][ $edit_event_index ] ) ) {
		$edit_data = $config['events'][ $edit_event_index ];
		$edit_data['event_index'] = $edit_event_index;
		$is_editing_event = true;
		$active_tab = 'events';
	}
}

/**
 * Load existing configuration or return empty structure
 */
function load_or_create_config( $file_path ) {
	if ( $file_path && file_exists( $file_path ) ) {
		$content = file_get_contents( $file_path );
		$config = json_decode( $content, true );
		if ( json_last_error() === JSON_ERROR_NONE ) {
			return $config;
		}
	}
	
	// Return default structure
	return array(
		'activity_url_prefix' => '',
		'team_name' => '',
		'team_members' => array(),
		'leadership' => array(),
		'alumni' => array(),
		'events' => array()
	);
}


/**
 * Create backup of existing configuration file (max one per minute)
 */
function create_backup( $file_path ) {
	if ( ! file_exists( $file_path ) ) {
		return true; // No file to backup
	}

	// Create backups directory if it doesn't exist
	$backups_dir = __DIR__ . '/backups';
	if ( ! file_exists( $backups_dir ) ) {
		mkdir( $backups_dir, 0755, true );
	}

	// Generate backup filename in backups directory (minute precision only)
	$filename = basename( $file_path );
	$backup_timestamp = date( '-Y-m-d-H-i' ); // No seconds - only minute precision
	$backup_filename = substr( $filename, 0, -4 ) . 'bak' . $backup_timestamp . '.json';
	$backup_path = $backups_dir . '/' . $backup_filename;
	
	// Only create backup if one doesn't already exist for this minute
	if ( file_exists( $backup_path ) ) {
		return true; // Backup for this minute already exists
	}
	
	return copy( $file_path, $backup_path );
}

/**
 * Save configuration to JSON file with backup
 */
function save_config( $config, $file_path ) {
	// Create backup first
	if ( ! create_backup( $file_path ) ) {
		return false;
	}

	$json = json_encode( $config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	return file_put_contents( $file_path, $json ) !== false;
}

$message = '';
$error = '';

// Handle form submissions
if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
	$config = load_or_create_config( $config_file ); // Load config for POST operations

	switch ( $action ) {
		case 'save_general':
			$config['team_name'] = sanitize_text_field( $_POST['team_name'] ?? '' );
			$config['activity_url_prefix'] = sanitize_url( $_POST['activity_url_prefix'] ?? '' );
			
			// Handle default team setting
			$is_default = isset( $_POST['is_default'] ) && $_POST['is_default'] === '1';
			
			if ( $is_default ) {
				// If this team is being set as default, clear default from all other teams
				$available_teams = get_available_teams();
				foreach ( $available_teams as $team_slug ) {
					if ( $team_slug !== $current_team ) {
						$other_team_file = __DIR__ . '/' . $team_slug . '.json';
						if ( file_exists( $other_team_file ) ) {
							$other_config = json_decode( file_get_contents( $other_team_file ), true );
							if ( json_last_error() === JSON_ERROR_NONE && isset( $other_config['default'] ) ) {
								unset( $other_config['default'] );
								$other_json = json_encode( $other_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
								file_put_contents( $other_team_file, $other_json );
							}
						}
					}
				}
				$config['default'] = true;
			} else {
				// Remove default flag if unchecked
				if ( isset( $config['default'] ) ) {
					unset( $config['default'] );
				}
			}
			
			if ( save_config( $config, $config_file ) ) {
				$message = 'General settings saved successfully!';
			} else {
				$error = 'Failed to save configuration.';
			}
			break;
			
		case 'save_team_links':
			// Handle team links
			$team_links = array();
			if ( isset( $_POST['team_links'] ) && is_array( $_POST['team_links'] ) ) {
				foreach ( $_POST['team_links'] as $link_data ) {
					$link_text = trim( sanitize_text_field( $link_data['text'] ?? '' ) );
					$link_url = trim( sanitize_url( $link_data['url'] ?? '' ) );
					if ( ! empty( $link_text ) && ! empty( $link_url ) ) {
						$team_links[ $link_text ] = $link_url;
					}
				}
			}
			$config['team_links'] = $team_links;

			if ( save_config( $config, $config_file ) ) {
				$message = 'Team links saved successfully!';
			} else {
				$error = 'Failed to save team links.';
			}
			break;

		case 'edit_member':
		case 'add_member':
			$member_config = get_person_type_config( 'member' );
			$person_data = create_person_data_from_form();
			$result = handle_person_action( $action, $member_config, $person_data );
			if ( isset( $result['error'] ) ) {
				$error = $result['error'];
			} elseif ( isset( $result['message'] ) ) {
				$message = $result['message'];
			}
			break;
			
		case 'edit_leadership':
		case 'add_leadership':
			$leader_config = get_person_type_config( 'leader' );
			$person_data = create_person_data_from_form();
			$result = handle_person_action( $action, $leader_config, $person_data );
			if ( isset( $result['error'] ) ) {
				$error = $result['error'];
			} elseif ( isset( $result['message'] ) ) {
				$message = $result['message'];
			}
			break;
			
		case 'edit_event':
			$event_index = (int) ( $_POST['event_index'] ?? -1 );
			if ( $event_index < 0 || ! isset( $config['events'][ $event_index ] ) ) {
				$error = 'Invalid event.';
				break;
			}
			
			// Process links
			$links = array();
			if ( ! empty( $_POST['event_links'] ) && is_array( $_POST['event_links'] ) ) {
				foreach ( $_POST['event_links'] as $link ) {
					$text = sanitize_text_field( $link['text'] ?? '' );
					$url = sanitize_url( $link['url'] ?? '' );
					if ( ! empty( $text ) && ! empty( $url ) ) {
						$links[ $text ] = $url;
					}
				}
			}

			$event = array(
				'name' => sanitize_text_field( $_POST['event_name'] ?? '' ),
				'start_date' => sanitize_text_field( $_POST['start_date'] ?? '' ),
				'end_date' => sanitize_text_field( $_POST['end_date'] ?? '' ),
				'type' => sanitize_text_field( $_POST['event_type'] ?? 'team' ),
				'location' => sanitize_text_field( $_POST['location'] ?? '' ),
				'description' => sanitize_textarea_field( $_POST['description'] ?? '' ),
				'links' => $links
			);
			
			$config['events'][ $event_index ] = $event;
			if ( save_config( $config, $config_file ) ) {
				$message = 'Event updated successfully!';
				// Reload the edit data to show updated information
				$edit_data = $config['events'][ $event_index ];
				$edit_data['event_index'] = $event_index;
			} else {
				$error = 'Failed to update event.';
			}
			break;
			
		case 'edit_alumni':
			$alumni_config = get_person_type_config( 'alumni' );
			$person_data = create_person_data_from_form();
			$result = handle_person_action( $action, $alumni_config, $person_data );
			if ( isset( $result['error'] ) ) {
				$error = $result['error'];
			} elseif ( isset( $result['message'] ) ) {
				$message = $result['message'];
			}
			break;

		case 'add_event':
			// Process links
			$links = array();
			if ( ! empty( $_POST['event_links'] ) && is_array( $_POST['event_links'] ) ) {
				foreach ( $_POST['event_links'] as $link ) {
					$text = sanitize_text_field( $link['text'] ?? '' );
					$url = sanitize_url( $link['url'] ?? '' );
					if ( ! empty( $text ) && ! empty( $url ) ) {
						$links[ $text ] = $url;
					}
				}
			}

			$event = array(
				'name' => sanitize_text_field( $_POST['event_name'] ?? '' ),
				'start_date' => sanitize_text_field( $_POST['start_date'] ?? '' ),
				'end_date' => sanitize_text_field( $_POST['end_date'] ?? '' ),
				'type' => sanitize_text_field( $_POST['event_type'] ?? 'team' ),
				'location' => sanitize_text_field( $_POST['location'] ?? '' ),
				'description' => sanitize_textarea_field( $_POST['description'] ?? '' ),
				'links' => $links
			);
			
			$config['events'][] = $event;
			if ( save_config( $config, $config_file ) ) {
				$message = 'Event added successfully!';
				// Reload config to get the latest data
				$config = load_or_create_config( $config_file );
			} else {
				$error = 'Failed to save event.';
			}
			break;
			
		case 'delete_member':
			$member_config = get_person_type_config( 'member' );
			$result = handle_person_action( $action, $member_config, array() );
			if ( isset( $result['error'] ) ) {
				$error = $result['error'];
			} elseif ( isset( $result['message'] ) ) {
				$message = $result['message'];
			}
			break;
			
		case 'delete_leader':
			$leader_config = get_person_type_config( 'leader' );
			$result = handle_person_action( $action, $leader_config, array() );
			if ( isset( $result['error'] ) ) {
				$error = $result['error'];
			} elseif ( isset( $result['message'] ) ) {
				$message = $result['message'];
			}
			break;

		case 'delete_alumni':
			$alumni_config = get_person_type_config( 'alumni' );
			$result = handle_person_action( $action, $alumni_config, array() );
			if ( isset( $result['error'] ) ) {
				$error = $result['error'];
			} elseif ( isset( $result['message'] ) ) {
				$message = $result['message'];
			}
			break;

		case 'move_to_alumni':
			$username = $_POST['username'] ?? '';
			$from_section = $_POST['from_section'] ?? '';

			if ( $from_section === 'team_members' && isset( $config['team_members'][ $username ] ) ) {
				$person_data = $config['team_members'][ $username ];
				$person_data['original_section'] = 'team_members'; // Track where they came from
				unset( $config['team_members'][ $username ] );
				$config['alumni'][ $username ] = $person_data;
			} elseif ( $from_section === 'leadership' && isset( $config['leadership'][ $username ] ) ) {
				$person_data = $config['leadership'][ $username ];
				$person_data['original_section'] = 'leadership'; // Track where they came from
				unset( $config['leadership'][ $username ] );
				$config['alumni'][ $username ] = $person_data;
			}

			if ( save_config( $config, $config_file ) ) {
				$message = 'Person moved to alumni successfully!';
			} else {
				$error = 'Failed to move person to alumni.';
			}
			break;

		case 'restore_from_alumni':
			$username = $_POST['username'] ?? '';
			$to_section = $_POST['to_section'] ?? null;

			if ( isset( $config['alumni'][ $username ] ) ) {
				$person_data = $config['alumni'][ $username ];

				// Use original section if available, otherwise fall back to provided section
				$target_section = $person_data['original_section'] ?? $to_section ?? 'team_members';

				// Remove original_section from data before restoring
				unset( $person_data['original_section'] );
				unset( $config['alumni'][ $username ] );

				if ( $target_section === 'leadership' ) {
					$config['leadership'][ $username ] = $person_data;
				} else {
					$config['team_members'][ $username ] = $person_data;
				}
			}

			if ( save_config( $config, $config_file ) ) {
				$message = 'Person restored from alumni successfully!';
			} else {
				$error = 'Failed to restore person from alumni.';
			}
			break;
			
		case 'delete_event':
			$event_index = (int) ( $_POST['event_index'] ?? -1 );
			if ( $event_index >= 0 && isset( $config['events'][ $event_index ] ) ) {
				array_splice( $config['events'], $event_index, 1 );
				if ( save_config( $config, $config_file ) ) {
					$message = 'Event deleted successfully!';
				} else {
					$error = 'Failed to delete event.';
				}
			}
			break;

		case 'create_team':
			$new_team_slug = sanitize_text_field( $_POST['new_team_slug'] ?? '' );
			$new_team_name = sanitize_text_field( $_POST['new_team_name'] ?? '' );
			
			if ( empty( $new_team_slug ) || empty( $new_team_name ) ) {
				$error = 'Team slug and name are required.';
				break;
			}
			
			// Validate slug format
			if ( ! preg_match( '/^[a-z0-9_-]+$/', $new_team_slug ) ) {
				$error = 'Team slug can only contain lowercase letters, numbers, hyphens and underscores.';
				break;
			}
			
			$new_team_file = __DIR__ . '/' . $new_team_slug . '.json';
			
			if ( file_exists( $new_team_file ) ) {
				$error = 'Team with this slug already exists.';
				break;
			}
			
			$new_config = array(
				'activity_url_prefix' => '',
				'team_name' => $new_team_name,
				'team_members' => array(),
				'leadership' => array(),
				'alumni' => array(),
				'events' => array()
			);
			
			// If this is the first team being created, make it the default
			$existing_teams = get_available_teams();
			if ( empty( $existing_teams ) ) {
				$new_config['default'] = true;
			}
			
			if ( save_config( $new_config, $new_team_file ) ) {
				$message = 'Team created successfully!';
				// Redirect to the new team
				$redirect_url = 'admin.php' . ( $new_team_slug !== 'team' ? '?team=' . urlencode( $new_team_slug ) : '' );
				header( 'Location: ' . $redirect_url );
				exit;
			} else {
				$error = 'Failed to create team.';
			}
			break;
	}
}

// Load config for display (after any POST operations)
$config = load_or_create_config( $config_file );

// WordPress-style sanitization functions (simplified versions)
function sanitize_text_field( $str ) {
	return trim( strip_tags( $str ) );
}

function sanitize_url( $url ) {
	return filter_var( trim( $url ), FILTER_SANITIZE_URL );
}

function sanitize_textarea_field( $str ) {
	return trim( strip_tags( $str ) );
}

function mask_date_input( $date, $privacy_mode ) {
	if ( ! $privacy_mode || empty( $date ) ) {
		return $date;
	}
	
	return ''; // Hide the date input value in privacy mode
}

/**
 * Get person type configuration
 */
function get_person_type_config( $person_type ) {
	$configs = array(
		'member' => array(
			'section_key' => 'team_members',
			'form_prefix' => '',
			'form_id' => 'member-form',
			'edit_action' => 'edit_member',
			'add_action' => 'add_member',
			'delete_action' => 'delete_member',
			'edit_text' => 'Update Team Member',
			'add_text' => 'Add Team Member',
			'display_name' => 'Team Member',
			'show_hr_feedback' => true,
			'show_alumni_actions' => true,
		),
		'leader' => array(
			'section_key' => 'leadership',
			'form_prefix' => 'leader-',
			'form_id' => 'leader-form',
			'edit_action' => 'edit_leadership',
			'add_action' => 'add_leadership',
			'delete_action' => 'delete_leader',
			'edit_text' => 'Update Leadership',
			'add_text' => 'Add Leadership',
			'display_name' => 'Leadership',
			'show_hr_feedback' => false,
			'show_alumni_actions' => true,
		),
		'alumni' => array(
			'section_key' => 'alumni',
			'form_prefix' => 'alumni-',
			'form_id' => 'alumni-form',
			'edit_action' => 'edit_alumni',
			'add_action' => null, // Alumni can only be created by moving existing members
			'delete_action' => 'delete_alumni',
			'edit_text' => 'Update Alumni',
			'add_text' => null,
			'display_name' => 'Alumni',
			'show_hr_feedback' => false,
			'show_alumni_actions' => false,
		),
	);

	return $configs[ $person_type ] ?? null;
}

/**
 * Unified person CRUD operations
 */
function handle_person_action( $action, $config, $person_data ) {
	global $config_file;

	$main_config = load_or_create_config( $config_file );

	if ( strpos( $action, 'edit_' ) === 0 ) {
		$username = sanitize_text_field( $_POST['username'] ?? '' );
		$original_username = sanitize_text_field( $_POST['original_username'] ?? '' );

		if ( empty( $username ) ) {
			return array( 'error' => 'Username is required.' );
		}

		// If username changed, remove old entry
		if ( $original_username !== $username ) {
			unset( $main_config[ $config['section_key'] ][ $original_username ] );
		}

		$main_config[ $config['section_key'] ][ $username ] = $person_data;

		if ( save_config( $main_config, $config_file ) ) {
			global $current_team;
			$redirect_url = build_team_url( 'index.php', array( 'person' => $username ) );
			header( 'Location: ' . $redirect_url );
			exit;
		} else {
			return array( 'error' => 'Failed to update ' . strtolower( $config['display_name'] ) . '.' );
		}
	} elseif ( strpos( $action, 'add_' ) === 0 ) {
		$username = sanitize_text_field( $_POST['username'] ?? '' );

		if ( empty( $username ) ) {
			return array( 'error' => 'Username is required.' );
		}

		$main_config[ $config['section_key'] ][ $username ] = $person_data;

		if ( save_config( $main_config, $config_file ) ) {
			global $current_team;
			$redirect_url = build_team_url( 'index.php', array( 'person' => $username ) );
			header( 'Location: ' . $redirect_url );
			exit;
		} else {
			return array( 'error' => 'Failed to save ' . strtolower( $config['display_name'] ) . '.' );
		}
	} elseif ( strpos( $action, 'delete_' ) === 0 ) {
		$username = $_POST['username'] ?? '';
		if ( isset( $main_config[ $config['section_key'] ][ $username ] ) ) {
			unset( $main_config[ $config['section_key'] ][ $username ] );
			if ( save_config( $main_config, $config_file ) ) {
				return array( 'message' => $config['display_name'] . ' deleted successfully!' );
			} else {
				return array( 'error' => 'Failed to delete ' . strtolower( $config['display_name'] ) . '.' );
			}
		}
	}

	return array();
}

/**
 * Create person data array from form input
 */
function create_person_data_from_form() {
	// Parse links data from form
	$links = array();

	// Process all link pairs from the form
	if ( isset( $_POST['link_text'] ) && isset( $_POST['link_url'] ) ) {
		$link_texts = $_POST['link_text'];
		$link_urls = $_POST['link_url'];

		foreach ( $link_texts as $index => $text ) {
			$text = sanitize_text_field( $text );
			$url = sanitize_url( $link_urls[$index] ?? '' );

			if ( ! empty( $text ) && ! empty( $url ) ) {
				$links[$text] = $url;
			}
		}
	}

	// Construct birthday from dropdowns
	$birthday = '';
	$day = sanitize_text_field( $_POST['birthday_day'] ?? '' );
	$month = sanitize_text_field( $_POST['birthday_month'] ?? '' );
	$year = sanitize_text_field( $_POST['birthday_year'] ?? '' );
	
	if ( ! empty( $day ) && ! empty( $month ) ) {
		if ( ! empty( $year ) ) {
			$birthday = $year . '-' . $month . '-' . $day;
		} else {
			$birthday = $month . '-' . $day; // Legacy format for year-unknown
		}
	}

	return array(
		'name' => sanitize_text_field( $_POST['name'] ?? '' ),
		'nickname' => sanitize_text_field( $_POST['nickname'] ?? '' ),
		'role' => sanitize_text_field( $_POST['role'] ?? '' ),
		'github' => sanitize_text_field( $_POST['github'] ?? '' ),
		'linear' => sanitize_text_field( $_POST['linear'] ?? '' ),
		'wordpress' => sanitize_text_field( $_POST['wordpress'] ?? '' ),
		'linkedin' => sanitize_text_field( $_POST['linkedin'] ?? '' ),
		'location' => sanitize_text_field( $_POST['location'] ?? '' ),
		'timezone' => sanitize_text_field( $_POST['timezone'] ?? '' ),
		'links' => $links,
		'birthday' => $birthday,
		'company_anniversary' => sanitize_text_field( $_POST['company_anniversary'] ?? '' ),
		'partner' => sanitize_text_field( $_POST['partner'] ?? '' ),
		'kids' => parse_kids_data( $_POST['kids'] ?? '' ),
		'notes' => sanitize_textarea_field( $_POST['notes'] ?? '' ),
		'github_repos' => isset( $_POST['github_repos'] ) ? array_filter( array_map( 'sanitize_text_field', $_POST['github_repos'] ) ) : array(),
		'personal_events' => parse_personal_events_data( $_POST['personal_events'] ?? array() )
	);
}

/**
 * Parse kids data from form input
 */
function parse_kids_data( $kids_input ) {
	if ( empty( $kids_input ) ) {
		return array();
	}

	$kids = array();
	$lines = explode( "\n", $kids_input );

	foreach ( $lines as $line ) {
		$line = trim( $line );
		if ( empty( $line ) ) {
			continue;
		}

		// Format: "Name (YYYY-MM-DD)" or "Name (MM-DD)" or "Name (YYYY)" or "Name - YYYY" or just "Name"
		if ( preg_match( '/^(.+?)\s*[\(\-]\s*(\d{4}-\d{2}-\d{2})\s*[\)]?\s*$/', $line, $matches ) ) {
			// Full birthday date
			$birth_date = DateTime::createFromFormat( 'Y-m-d', $matches[2] );
			if ( $birth_date ) {
				$kids[] = array(
					'name' => trim( $matches[1] ),
					'birth_year' => (int) $birth_date->format( 'Y' ),
					'birthday' => $matches[2]
				);
			} else {
				$kids[] = array(
					'name' => trim( $matches[1] ),
					'birth_year' => '',
					'birthday' => ''
				);
			}
		} elseif ( preg_match( '/^(.+?)\s*[\(\-]\s*(\d{2}-\d{2})\s*[\)]?\s*$/', $line, $matches ) ) {
			// Month-day format (MM-DD)
			$kids[] = array(
				'name' => trim( $matches[1] ),
				'birth_year' => '',
				'birthday' => $matches[2] // Store as MM-DD
			);
		} elseif ( preg_match( '/^(.+?)\s*[\(\-]\s*(\d{4})\s*[\)]?\s*$/', $line, $matches ) ) {
			// Just birth year
			$kids[] = array(
				'name' => trim( $matches[1] ),
				'birth_year' => (int) $matches[2],
				'birthday' => ''
			);
		} else {
			// Just a name, no birth info
			$kids[] = array(
				'name' => $line,
				'birth_year' => '',
				'birthday' => ''
			);
		}
	}

	return $kids;
}

/**
 * Parse personal events data from form input
 */
function parse_personal_events_data( $events_data ) {
	if ( empty( $events_data ) || ! is_array( $events_data ) ) {
		return array();
	}

	$personal_events = array();

	foreach ( $events_data as $event ) {
		if ( empty( $event['date'] ) || empty( $event['description'] ) ) {
			continue; // Skip incomplete events
		}

		$date = sanitize_text_field( $event['date'] );
		$type = sanitize_text_field( $event['type'] ?? 'other' );
		$description = sanitize_text_field( $event['description'] );

		// Validate date format (YYYY-MM-DD)
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			continue; // Skip invalid dates
		}

		$personal_events[] = array(
			'date' => $date,
			'type' => $type,
			'description' => $description
		);
	}

	return $personal_events;
}

/**
 * Check which data points are missing for a person
 */
function get_missing_data_points( $person, $person_type = 'member' ) {
	$missing = array();

	// Core fields
	if ( empty( $person['name'] ) ) {
		$missing[] = 'Name';
	}
	if ( empty( $person['role'] ) ) {
		$missing[] = 'Role';
	}
	if ( empty( $person['location'] ) ) {
		$missing[] = 'Location';
	}
	if ( empty( $person['timezone'] ) ) {
		$missing[] = 'Timezone';
	}

	// Birthday
	if ( empty( $person['birthday'] ) ) {
		$missing[] = 'Birthday';
	}

	// Company anniversary
	if ( empty( $person['company_anniversary'] ) ) {
		$missing[] = 'Company Anniversary';
	}

	// Links - check for key links
	$expected_links = array( '1:1 doc' );

	foreach ( $expected_links as $expected_link ) {
		if ( ! isset( $person['links'][ $expected_link ] ) || empty( $person['links'][ $expected_link ] ) ) {
			$missing[] = $expected_link . ' link';
		}
	}

	// Optional fields that are nice to have
	if ( empty( $person['partner'] ) ) {
		$missing[] = 'Partner (optional)';
	}
	if ( empty( $person['kids'] ) ) {
		$missing[] = 'Kids info (optional)';
	}
	if ( empty( $person['notes'] ) ) {
		$missing[] = 'Notes (optional)';
	}

	return $missing;
}

/**
 * Get completeness score as percentage
 */
function get_completeness_score( $missing_data, $person_type = 'member' ) {
	// Core required fields
	$total_core_fields = 6; // name, role, location, timezone, birthday, company_anniversary
	if ( $person_type === 'member' ) {
		$total_core_fields += 2; // 1:1 doc, HR monthly links
	} else {
		$total_core_fields += 1; // 1:1 doc link
	}

	// Count missing core fields (exclude optional ones)
	$missing_core = 0;
	foreach ( $missing_data as $missing_item ) {
		if ( strpos( $missing_item, 'optional' ) === false ) {
			$missing_core++;
		}
	}

	$completed_core = $total_core_fields - $missing_core;
	$score = round( ( $completed_core / $total_core_fields ) * 100 );

	return max( 0, $score );
}

/**
 * Get all available timezone options
 */
function get_timezone_options() {
	$timezones = array();
	$timezone_identifiers = DateTimeZone::listIdentifiers();

	foreach ( $timezone_identifiers as $timezone ) {
		// Group by region for better organization
		$parts = explode( '/', $timezone );
		$region = $parts[0];
		$location = isset( $parts[1] ) ? str_replace( '_', ' ', $parts[1] ) : '';

		// Create display name
		$display_name = $timezone;
		if ( $location ) {
			$display_name = $region . '/' . $location;
		}

		$timezones[] = array(
			'value' => $timezone,
			'label' => $display_name,
			'region' => $region
		);
	}

	// Sort by region, then by location
	usort( $timezones, function( $a, $b ) {
		$region_compare = strcmp( $a['region'], $b['region'] );
		if ( $region_compare === 0 ) {
			return strcmp( $a['label'], $b['label'] );
		}
		return $region_compare;
	} );

	return $timezones;
}

/**
 * Format kids data for display in form
 */
function format_kids_for_form( $kids ) {
	if ( empty( $kids ) || ! is_array( $kids ) ) {
		return '';
	}

	$lines = array();
	foreach ( $kids as $kid ) {
		if ( ! empty( $kid['birthday'] ) ) {
			$lines[] = $kid['name'] . ' (' . $kid['birthday'] . ')';
		} elseif ( ! empty( $kid['birth_year'] ) ) {
			$lines[] = $kid['name'] . ' (' . $kid['birth_year'] . ')';
		} else {
			$lines[] = $kid['name'];
		}
	}

	return implode( "\n", $lines );
}

/**
 * Render a person form (team member, leader, or alumni)
 */
function render_person_form( $type, $edit_data = null, $is_editing = false ) {
	global $privacy_mode;
	
	$config = get_person_type_config( $type );
	if ( ! $config ) {
		return; // Invalid type
	}

	// Don't render form if trying to add when add_action is not allowed
	if ( ! $is_editing && $config['add_action'] === null ) {
		return;
	}

	$form_id = $config['form_id'];
	$action = $is_editing ? $config['edit_action'] : $config['add_action'];
	$submit_text = 'Save';
	$prefix = $config['form_prefix'];
	$show_hr_feedback = $config['show_hr_feedback'];
	$show_alumni_actions = $config['show_alumni_actions'];
?>
<form method="post" id="<?php echo $form_id; ?>">
	<input type="hidden" name="action" value="<?php echo $action; ?>">
	<input type="hidden" name="original_username" value="<?php echo $is_editing ? htmlspecialchars( $edit_data['username'] ?? '' ) : ''; ?>">
	<?php global $current_team; if ( $current_team !== 'team' ) : ?>
		<input type="hidden" name="team" value="<?php echo htmlspecialchars( $current_team ); ?>">
	<?php endif; ?>
	<button type="submit" class="btn" style="float: right; margin-top: -3em"><?php echo $submit_text; ?></button>

	<!-- Personal Information -->
	<h4 class="section-heading">Personal Information</h4>
	<div class="form-grid">
		<div class="form-group">
			<label for="<?php echo $prefix; ?>name">Full Name<?php echo $privacy_mode ? ' (Privacy Mode - Last name will be masked)' : ''; ?></label>
			<input type="text" id="<?php echo $prefix; ?>name" name="name" value="<?php echo $is_editing ? htmlspecialchars( $privacy_mode ? mask_name( $edit_data['name'] ?? '', true ) : ( $edit_data['name'] ?? '' ) ) : ''; ?>"<?php echo $privacy_mode ? ' placeholder="First name visible only"' : ''; ?> autofocus>
		</div>

		<div class="form-group">
			<label for="<?php echo $prefix; ?>nickname">Nickname <small class="optional-label">(optional)</small></label>
			<input type="text" id="<?php echo $prefix; ?>nickname" name="nickname" value="<?php echo $is_editing ? htmlspecialchars( $edit_data['nickname'] ?? '' ) : ''; ?>" placeholder="e.g., Mike, Lizzy, DJ">
		</div>

		<div class="form-group">
			<label for="<?php echo $prefix; ?>location">Location</label>
			<input type="text" id="<?php echo $prefix; ?>location" name="location" value="<?php echo $is_editing ? htmlspecialchars( $edit_data['location'] ?? $edit_data['town'] ?? '' ) : ''; ?>">
		</div>

		<div class="form-group">
			<label for="<?php echo $prefix; ?>timezone">Timezone</label>
			<div class="timezone-input">
				<input type="text"
					   id="<?php echo $prefix; ?>timezone"
					   name="timezone"
					   value="<?php echo $is_editing ? htmlspecialchars( $edit_data['timezone'] ?? '' ) : ''; ?>"
					   placeholder="e.g., America/New_York or type city like 'madrid'"
					   autocomplete="off">
				<div id="<?php echo $prefix; ?>timezone-suggestions" class="timezone-suggestions"></div>
			</div>
			<script type="application/json" id="<?php echo $prefix; ?>timezone-data">
				<?php echo json_encode( get_timezone_options() ); ?>
			</script>
		</div>

		<div class="form-group">
			<label>Birthday<?php echo $privacy_mode ? ' (Hidden in Privacy Mode)' : ''; ?></label>
			<?php if ( ! $privacy_mode ) : ?>
				<?php
				// Parse existing birthday data
				$day = $month = $year = '';
				if ( $is_editing && ! empty( $edit_data['birthday'] ) ) {
					if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $edit_data['birthday'], $matches ) ) {
						// Full YYYY-MM-DD format
						$year = $matches[1];
						$month = $matches[2];
						$day = $matches[3];
					} elseif ( preg_match( '/^(\d{2})-(\d{2})$/', $edit_data['birthday'], $matches ) ) {
						// MM-DD format (legacy)
						$month = $matches[1];
						$day = $matches[2];
					}
					// Ensure month is always 2 digits with leading zero
					if ( ! empty( $month ) && strlen( $month ) === 1 ) {
						$month = '0' . $month;
					}
					// Ensure day is always 2 digits with leading zero
					if ( ! empty( $day ) && strlen( $day ) === 1 ) {
						$day = '0' . $day;
					}
				}
				?>
				<div style="display: flex; gap: 10px; align-items: center;">
					<select name="birthday_day" class="form-select">
						<option value="">Day</option>
						<?php for ( $d = 1; $d <= 31; $d++ ) : ?>
							<option value="<?php echo sprintf( '%02d', $d ); ?>" <?php echo (string) $day === sprintf( '%02d', $d ) ? 'selected' : ''; ?>>
								<?php echo $d; ?>
							</option>
						<?php endfor; ?>
					</select>
					
					<select name="birthday_month" class="form-select">
						<option value="">Month</option>
						<?php 
						$months = array(
							'01' => 'January', '02' => 'February', '03' => 'March', '04' => 'April',
							'05' => 'May', '06' => 'June', '07' => 'July', '08' => 'August', 
							'09' => 'September', '10' => 'October', '11' => 'November', '12' => 'December'
						);
						foreach ( $months as $num => $name ) : ?>
							<option value="<?php echo $num; ?>" <?php echo (string) $month === (string) $num ? 'selected' : ''; ?>>
								<?php echo $num; ?> - <?php echo $name; ?>
							</option>
						<?php endforeach; ?>
					</select>
					
					<select name="birthday_year" class="form-select">
						<option value="">Year (optional)</option>
						<?php for ( $y = date( 'Y' ) - 80; $y <= date( 'Y' ) - 16; $y++ ) : ?>
							<option value="<?php echo $y; ?>" <?php echo $year === (string) $y ? 'selected' : ''; ?>>
								<?php echo $y; ?>
							</option>
						<?php endfor; ?>
					</select>
				</div>
				<small class="form-helper-text">
					Year is optional - leave empty if unknown
				</small>
			<?php else : ?>
				<input type="hidden" name="birthday_day" value="">
				<input type="hidden" name="birthday_month" value="">
				<input type="hidden" name="birthday_year" value="">
				<p class="text-muted italic-text">Hidden in privacy mode</p>
			<?php endif; ?>
		</div>
	</div>

	<!-- Family Information -->
	<details style="margin: 20px 0;">
		<summary class="summary-toggle">Family Information</summary>

		<div class="form-grid">
			<div class="form-group">
				<label for="<?php echo $prefix; ?>partner">Partner</label>
				<input type="text" id="<?php echo $prefix; ?>partner" name="partner" value="<?php echo $is_editing ? htmlspecialchars( $edit_data['partner'] ?? '' ) : ''; ?>">
			</div>
		</div>

		<div class="form-group">
			<label for="<?php echo $prefix; ?>kids">Kids (one per line, formats: "Name (YYYY-MM-DD)", "Name (MM-DD)", "Name (YYYY)" or just "Name")</label>
			<textarea id="<?php echo $prefix; ?>kids" name="kids" rows="4" placeholder="Emma (2010-03-15)&#10;Jake (12-25)&#10;Sam (2012)&#10;Alex"><?php echo $is_editing ? htmlspecialchars( format_kids_for_form( $edit_data['kids'] ?? array() ) ) : ''; ?></textarea>
		</div>
	</details>

	<!-- Company Information -->
	<h4 class="section-heading">Company Information</h4>
	<div class="form-grid">
		<div class="form-group">
			<label for="<?php echo $prefix; ?>username">Username *</label>
			<input type="text" id="<?php echo $prefix; ?>username" name="username" value="<?php echo $is_editing ? htmlspecialchars( $edit_data['username'] ?? '' ) : ''; ?>" required>
		</div>

		<div class="form-group">
			<label for="<?php echo $prefix; ?>role">Role</label>
			<input type="text" id="<?php echo $prefix; ?>role" name="role" value="<?php echo $is_editing ? htmlspecialchars( $edit_data['role'] ?? '' ) : ''; ?>" placeholder="e.g., Developer, Team Lead, HR">
		</div>

		<div class="form-group">
			<label for="<?php echo $prefix; ?>company_anniversary">Company Anniversary<?php echo $privacy_mode ? ' (Hidden in Privacy Mode)' : ''; ?></label>
			<input type="date" id="<?php echo $prefix; ?>company_anniversary" name="company_anniversary" value="<?php echo $is_editing ? htmlspecialchars( mask_date_input( $edit_data['company_anniversary'] ?? '', $privacy_mode ) ) : ''; ?>"<?php echo $privacy_mode ? ' placeholder="Hidden for privacy"' : ''; ?>>
		</div>


		<!-- Links Section -->
		<div class="form-group" style="grid-column: 1 / -1;">
			<label>Links</label>
			<div id="<?php echo $prefix; ?>links-container">
				<?php
				// Prepare links for display
				$current_links = array();
				if ( $is_editing && isset( $edit_data['links'] ) ) {
					$current_links = $edit_data['links'];
				}

				// Ensure 1:1 doc link is always present for all person types
				if ( ! isset( $current_links['1:1 doc'] ) ) {
					$current_links['1:1 doc'] = '';
				}

				// Add one empty row if no links exist
				if ( empty( $current_links ) ) {
					$current_links[''] = '';
				}

				foreach ( $current_links as $link_text => $link_url ) :
				?>
					<div class="link-row" style="display: flex; gap: 10px; margin-bottom: 10px; align-items: center;">
						<input type="text" name="link_text[]" placeholder="Link text (e.g., '1:1 doc')" value="<?php echo htmlspecialchars( $link_text ); ?>" style="flex: 1;">
						<input type="url" name="link_url[]" placeholder="URL" value="<?php echo htmlspecialchars( $link_url ); ?>" style="flex: 2;">
						<button type="button" onclick="removeLink(this)" class="btn-remove">Remove</button>
					</div>
				<?php endforeach; ?>
			</div>
			<button type="button" onclick="addLink('<?php echo $prefix; ?>')" class="btn-add">+ Add Link</button>
		</div>
	</div>

	<!-- Usernames -->
	<h4 class="section-heading">External Accounts</h4>
	<div class="form-grid">
		<div class="form-group">
			<label for="<?php echo $prefix; ?>github">GitHub Username</label>
			<input type="text" id="<?php echo $prefix; ?>github" name="github" value="<?php echo $is_editing ? htmlspecialchars( $edit_data['github'] ?? '' ) : ''; ?>">
		</div>

		<div class="form-group">
			<label for="<?php echo $prefix; ?>linear">Linear Username</label>
			<input type="text" id="<?php echo $prefix; ?>linear" name="linear" value="<?php echo $is_editing ? htmlspecialchars( $edit_data['linear'] ?? '' ) : ''; ?>">
		</div>

		<div class="form-group">
			<label for="<?php echo $prefix; ?>wordpress">WordPress.org Username</label>
			<input type="text" id="<?php echo $prefix; ?>wordpress" name="wordpress" value="<?php echo $is_editing ? htmlspecialchars( $edit_data['wordpress'] ?? '' ) : ''; ?>">
		</div>

		<div class="form-group">
			<label for="<?php echo $prefix; ?>linkedin">LinkedIn Username</label>
			<input type="text" id="<?php echo $prefix; ?>linkedin" name="linkedin" value="<?php echo $is_editing ? htmlspecialchars( $edit_data['linkedin'] ?? '' ) : ''; ?>">
		</div>
	</div>

	<!-- GitHub Repositories -->
	<div class="form-group">
		<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
			<label>GitHub Repositories</label>
			<button type="button" onclick="addRepoField('<?php echo $prefix; ?>')" class="btn-add-small">+ Add Repository</button>
		</div>
		
		<div id="<?php echo $prefix; ?>repo_fields">
			<?php
			// Get existing repos for editing
			$existing_repos = array();
			if ( $is_editing && ! empty( $edit_data['github_repos'] ) ) {
				$existing_repos = is_array( $edit_data['github_repos'] ) ? $edit_data['github_repos'] : array_filter( array_map( 'trim', explode( ',', $edit_data['github_repos'] ) ) );
			}
			
			if ( empty( $existing_repos ) ) {
				// Show one empty field for new entries
				?>
				<div class="repo-field" style="display: flex; gap: 8px; margin-bottom: 8px;">
					<input type="text" name="github_repos[]" placeholder="org/repo-name" style="width: 300px;">
					<button type="button" onclick="removeRepoField(this)" class="btn-remove-small">×</button>
				</div>
				<?php
			} else {
				foreach ( $existing_repos as $repo ) :
				?>
					<div class="repo-field" style="display: flex; gap: 8px; margin-bottom: 8px;">
						<input type="text" name="github_repos[]" value="<?php echo htmlspecialchars( $repo ); ?>" placeholder="org/repo-name" style="width: 300px;">
						<button type="button" onclick="removeRepoField(this)" class="btn-remove-small">×</button>
					</div>
				<?php
				endforeach;
			}
			?>
		</div>
		
		<?php
		// Get all repos used across the team for tag display
		global $config_file;
		$team_config = load_or_create_config( $config_file );
		$all_repos = array();
		$all_people = array_merge( $team_config['team_members'] ?? array(), $team_config['leadership'] ?? array(), $team_config['alumni'] ?? array() );
		foreach ( $all_people as $person ) {
			if ( ! empty( $person['github_repos'] ) ) {
				$person_repos = is_array( $person['github_repos'] ) ? $person['github_repos'] : array_filter( array_map( 'trim', explode( ',', $person['github_repos'] ?? '' ) ) );
				$all_repos = array_merge( $all_repos, $person_repos );
			}
		}
		$all_repos = array_unique( array_filter( $all_repos ) );
		
		// Filter out repos the current user already has
		$user_repos = array();
		if ( $is_editing && ! empty( $edit_data['github_repos'] ) ) {
			$user_repos = is_array( $edit_data['github_repos'] ) ? $edit_data['github_repos'] : array_filter( array_map( 'trim', explode( ',', $edit_data['github_repos'] ) ) );
		}
		$available_repos = array_diff( $all_repos, $user_repos );
		sort( $available_repos );
		
		if ( ! empty( $available_repos ) ) :
		?>
			<div style="margin-top: 12px;">
				<small class="text-small-muted">Potential repositories (click to add):</small>
				<div style="margin-top: 4px; display: flex; flex-wrap: wrap; gap: 4px;">
					<?php foreach ( $available_repos as $repo ) : ?>
						<button type="button" onclick="addRepoToField('<?php echo $prefix; ?>', '<?php echo htmlspecialchars( $repo, ENT_QUOTES ); ?>')" 
								class="event-type-tag">
							<?php echo htmlspecialchars( $repo ); ?>
						</button>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>
	</div>

	<script>
		function addRepoField(prefix) {
			const container = document.getElementById(prefix + 'repo_fields');
			const div = document.createElement('div');
			div.className = 'repo-field';
			div.style.display = 'flex';
			div.style.gap = '8px';
			div.style.marginBottom = '8px';
			
			div.innerHTML = '<input type="text" name="github_repos[]" placeholder="org/repo-name" style="width: 300px;">' +
							'<button type="button" onclick="removeRepoField(this)" class="btn-remove-small">×</button>';
			
			container.appendChild(div);
		}
		
		function removeRepoField(button) {
			const container = button.closest('.repo-field').parentNode;
			button.closest('.repo-field').remove();
			
			// Ensure at least one field remains
			if (container.children.length === 0) {
				addRepoField('<?php echo $prefix; ?>');
			}
		}
		
		function addRepoToField(prefix, repo) {
			// Find an empty field or create a new one
			const container = document.getElementById(prefix + 'repo_fields');
			const inputs = container.querySelectorAll('input[name="github_repos[]"]');
			
			// Check if repo already exists
			for (let input of inputs) {
				if (input.value.trim() === repo) {
					return; // Already exists
				}
			}
			
			// Find empty field
			for (let input of inputs) {
				if (input.value.trim() === '') {
					input.value = repo;
					return;
				}
			}
			
			// No empty field found, add new one
			addRepoField(prefix);
			const newInputs = container.querySelectorAll('input[name="github_repos[]"]');
			newInputs[newInputs.length - 1].value = repo;
		}
	</script>

	<!-- Personal Events -->
	<h4 class="section-heading" style="margin-top: 30px;">Personal Events</h4>
	<div class="form-group">
		<div id="personal-events-container">
			<?php 
			$personal_events = $is_editing && isset( $edit_data['personal_events'] ) ? $edit_data['personal_events'] : array();
			if ( ! empty( $personal_events ) ) :
				foreach ( $personal_events as $index => $event ) :
			?>
				<div class="personal-event-row">
					<input type="date" name="personal_events[<?php echo $index; ?>][date]" value="<?php echo htmlspecialchars( $event['date'] ?? '' ); ?>" style="flex: 0 0 150px;">
					<input type="hidden" name="personal_events[<?php echo $index; ?>][type]" value="other">
					<input type="text" name="personal_events[<?php echo $index; ?>][description]" value="<?php echo htmlspecialchars( $event['description'] ?? '' ); ?>" placeholder="Event description" style="flex: 1;">
					<button type="button" onclick="removePersonalEvent(this)" class="btn-remove-personal">×</button>
				</div>
			<?php 
				endforeach;
			endif;
			?>
		</div>
		<button type="button" onclick="addPersonalEvent()" class="btn-add-personal">+ Add Personal Event</button>
	</div>

	<script>
		let personalEventIndex = <?php echo $is_editing && ! empty( $personal_events ) ? count( $personal_events ) : 0; ?>;

		function addPersonalEvent() {
			const container = document.getElementById('personal-events-container');
			const eventRow = document.createElement('div');
			eventRow.className = 'personal-event-row';
			
			eventRow.innerHTML = `
				<input type="date" name="personal_events[${personalEventIndex}][date]" style="flex: 0 0 150px;">
				<input type="hidden" name="personal_events[${personalEventIndex}][type]" value="other">
				<input type="text" name="personal_events[${personalEventIndex}][description]" placeholder="Event description" style="flex: 1;">
				<button type="button" onclick="removePersonalEvent(this)" class="btn-remove-personal">×</button>
			`;
			
			container.appendChild(eventRow);
			personalEventIndex++;
		}

		function removePersonalEvent(button) {
			button.parentElement.remove();
		}
	</script>

	<div class="form-group">
		<label for="<?php echo $prefix; ?>notes">Notes</label>
		<textarea id="<?php echo $prefix; ?>notes" name="notes"><?php echo $is_editing ? htmlspecialchars( $edit_data['notes'] ?? '' ) : ''; ?></textarea>
	</div>

	<button type="submit" class="btn"><?php echo $submit_text; ?></button>
</form>

<?php if ( $is_editing && $show_alumni_actions ) : ?>
	<div class="divider-section">
		<h4 class="text-muted" style="margin-bottom: 10px;">Alumni Actions</h4>
		<form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to move this person to alumni? They will be removed from active team lists.')">
			<input type="hidden" name="action" value="move_to_alumni">
			<input type="hidden" name="username" value="<?php echo htmlspecialchars( $edit_data['username'] ?? '' ); ?>">
			<input type="hidden" name="from_section" value="<?php echo $config['section_key']; ?>">
			<?php if ( $current_team !== 'team' ) : ?>
				<input type="hidden" name="team" value="<?php echo htmlspecialchars( $current_team ); ?>">
			<?php endif; ?>
			<button type="submit" class="btn btn-warning">
				📚 Move to Alumni
			</button>
		</form>
		<p class="text-small-muted" style="margin: 5px 0 0 0;">
			This will move the person to alumni status while preserving all their data.
		</p>
	</div>
<?php endif; ?>
<?php
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <title>Team Management Admin</title>
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

    <div class="container">
        <div class="header">
            <div class="header-content">
                <h1><a href="admin.php" style="color: inherit; text-decoration: none;">Team Management Admin</a></h1>
            </div>
            <div class="navigation">
                <!-- Team Switcher -->
                <div class="team-switcher" style="display: inline-block; margin-right: 10px;">
                    <?php
                    $available_teams = get_available_teams();
                    if ( $available_teams ) :
                    	?>
                    <select id="team-selector" onchange="switchTeam()">
                        <?php
                        foreach ( $available_teams as $team_slug ) {
                            $team_display_name = get_team_name_from_file( $team_slug );
                            $selected = $team_slug === $current_team ? 'selected' : '';
                            echo '<option value="' . htmlspecialchars( $team_slug ) . '" ' . $selected . '>' . htmlspecialchars( $team_display_name ) . '</option>';
                        }
                        ?>
                    </select>
                <?php endif; ?>
                    <a href="<?php echo build_team_url( 'admin.php', array( 'create_team' => 'new' ) ); ?>" class="nav-link" style="font-size: 12px; padding: 6px 12px; margin-left: 5px;">+ New Team</a>
                </div>
                
            </div>
        </div>

        <?php if ( $message ) : ?>
            <div class="message success"><?php echo htmlspecialchars( $message ); ?></div>
        <?php endif; ?>

        <?php if ( $error ) : ?>
            <div class="message error"><?php echo htmlspecialchars( $error ); ?></div>
        <?php endif; ?>

        <?php if ( $is_creating_team ) : ?>
            <!-- Create New Team Page -->
            <div style="margin-bottom: 20px;">
                <a href="<?php echo build_team_url( 'admin.php' ); ?>" class="back-link-admin">← Back to Admin Dashboard</a>
            </div>
            
            <h2>Create New Team</h2>
            <form method="post">
                <input type="hidden" name="action" value="create_team">
                <?php if ( $current_team !== 'team' ) : ?>
                    <input type="hidden" name="team" value="<?php echo htmlspecialchars( $current_team ); ?>">
                <?php endif; ?>
                <div class="form-group">
                    <label for="new_team_name">Team Name *</label>
                    <input type="text" id="new_team_name" name="new_team_name" required placeholder="e.g., Marketing Team" autofocus>
                </div>
                <div class="form-group">
                    <label for="new_team_slug">Team Slug *</label>
                    <input type="text" id="new_team_slug" name="new_team_slug" required placeholder="e.g., marketing-team" pattern="[a-z0-9_-]+" value="<?php echo $current_team !== 'team' ? htmlspecialchars( $current_team ) : ''; ?>">
                    <small class="text-small-muted">Only lowercase letters, numbers, hyphens, and underscores allowed. This will be used as the filename.</small>
                </div>
                <div style="margin-top: 20px;">
                    <button type="submit" class="btn">Create Team</button>
                    <a href="<?php echo build_team_url( 'admin.php' ); ?>" class="btn btn-secondary" style="margin-left: 10px;">Cancel</a>
                </div>
            </form>
        <?php else : ?>

        <div class="nav-tabs">
            <a href="<?php echo build_team_url( 'admin.php', array( 'tab' => 'general' ) ); ?>" class="nav-tab <?php echo $active_tab === 'general' ? 'active' : ''; ?>">General Settings</a>
            <a href="<?php echo build_team_url( 'admin.php', array( 'tab' => 'team_links' ) ); ?>" class="nav-tab <?php echo $active_tab === 'team_links' ? 'active' : ''; ?>">Team Links (<?php echo count( $config['team_links'] ?? array() ); ?>)</a>
            <a href="<?php echo build_team_url( 'admin.php', array( 'tab' => 'members' ) ); ?>" class="nav-tab <?php echo $active_tab === 'members' ? 'active' : ''; ?>">Team Members (<?php echo count( $config['team_members'] ); ?>)</a>
            <a href="<?php echo build_team_url( 'admin.php', array( 'tab' => 'leadership' ) ); ?>" class="nav-tab <?php echo $active_tab === 'leadership' ? 'active' : ''; ?>">Leadership (<?php echo count( $config['leadership'] ); ?>)</a>
            <a href="<?php echo build_team_url( 'admin.php', array( 'tab' => 'alumni' ) ); ?>" class="nav-tab <?php echo $active_tab === 'alumni' ? 'active' : ''; ?>">Alumni (<?php echo count( $config['alumni'] ?? array() ); ?>)</a>
            <a href="<?php echo build_team_url( 'admin.php', array( 'tab' => 'events' ) ); ?>" class="nav-tab <?php echo $active_tab === 'events' ? 'active' : ''; ?>">Events (<?php echo count( $config['events'] ); ?>)</a>
            <a href="<?php echo build_team_url( 'admin.php', array( 'tab' => 'audit' ) ); ?>" class="nav-tab <?php echo $active_tab === 'audit' ? 'active' : ''; ?>">📊 Audit</a>
            <a href="<?php echo build_team_url( 'admin.php', array( 'tab' => 'json' ) ); ?>" class="nav-tab <?php echo $active_tab === 'json' ? 'active' : ''; ?>">View JSON</a>
        </div>

        <!-- General Settings Tab -->
        <div id="general" class="tab-content <?php echo $active_tab === 'general' ? 'active' : ''; ?>">
            <h2>General Settings</h2>
            <form method="post">
                <input type="hidden" name="action" value="save_general">
                <?php if ( $current_team !== 'team' ) : ?>
                    <input type="hidden" name="team" value="<?php echo htmlspecialchars( $current_team ); ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="team_name">Team Name</label>
                    <input type="text" id="team_name" name="team_name" value="<?php echo htmlspecialchars( $config['team_name'] ); ?>" required autofocus>
                </div>
                
                <div class="form-group">
                    <label for="activity_url_prefix">Activity URL Prefix</label>
                    <input type="url" id="activity_url_prefix" name="activity_url_prefix" value="<?php echo htmlspecialchars( $config['activity_url_prefix'] ); ?>">
                </div>
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 5px; font-weight: 600;">
                        <input type="checkbox" id="is_default" name="is_default" value="1" <?php echo isset( $config['default'] ) && $config['default'] ? 'checked' : ''; ?> style="width: auto;">
                        <span>Set as default team</span>
                    </label>
                    <small class="text-small-muted" style="margin-left: 20px;">
                        When users visit the site without specifying a team, they'll be redirected to this team automatically.
                    </small>
                </div>

                
                <button type="submit" class="btn">Save General Settings</button>
            </form>
        </div>

        <!-- Team Links Tab -->
        <div id="team_links" class="tab-content <?php echo $active_tab === 'team_links' ? 'active' : ''; ?>">
            <h2>Team Links</h2>
            <p class="text-muted" style="margin-bottom: 20px;">These links will appear on the front page next to the team headline.</p>

            <form method="post">
                <input type="hidden" name="action" value="save_team_links">
                <?php if ( $current_team !== 'team' ) : ?>
                    <input type="hidden" name="team" value="<?php echo htmlspecialchars( $current_team ); ?>">
                <?php endif; ?>

                <div class="form-group">
                    <div id="team-links-container">
                        <?php
                        $team_links = $config['team_links'] ?? array();
                        $link_index = 0;
                        if ( ! empty( $team_links ) ) :
                            foreach ( $team_links as $link_text => $link_url ) : ?>
                                <div class="team-link-row" style="display: flex; gap: 10px; margin-bottom: 10px; align-items: center;">
                                    <input type="text" name="team_links[<?php echo $link_index; ?>][text]" value="<?php echo htmlspecialchars( $link_text ); ?>" placeholder="Link text (e.g., Linear)" style="flex: 0 0 150px;">
                                    <input type="url" name="team_links[<?php echo $link_index; ?>][url]" value="<?php echo htmlspecialchars( $link_url ); ?>" placeholder="https://..." style="flex: 1;">
                                    <button type="button" class="remove-team-link btn-remove-personal">Remove</button>
                                </div>
                            <?php
                            $link_index++;
                            endforeach;
                        else : ?>
                            <div class="team-link-row" style="display: flex; gap: 10px; margin-bottom: 10px; align-items: center;">
                                <input type="text" name="team_links[0][text]" value="" placeholder="Link text (e.g., Linear)" style="flex: 0 0 150px;">
                                <input type="url" name="team_links[0][url]" value="" placeholder="https://..." style="flex: 1;">
                                <button type="button" class="remove-team-link btn-remove-personal">Remove</button>
                            </div>
                        <?php endif; ?>
                    </div>
                    <button type="button" id="add-team-link" class="btn btn-add-personal">+ Add Link</button>
                </div>

                <button type="submit" class="btn">Save Team Links</button>
            </form>
        </div>

        <!-- Team Members Tab -->
        <div id="members" class="tab-content <?php echo $active_tab === 'members' ? 'active' : ''; ?>">
            <?php if ( $is_editing_member ) : ?>
                <h2>Edit Team Member: <?php echo htmlspecialchars( mask_name( $edit_data['name'] ?? $edit_data['username'], $privacy_mode ) ); ?></h2>
            <?php elseif ( isset( $_GET['add'] ) && $_GET['add'] === 'new' ) : ?>
            <?php else : ?>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3 style="margin: 0;">Current Team Members</h3>
                    <?php
                    $add_params = array( 'tab' => 'members', 'add' => 'new' );
                    if ( $privacy_mode ) $add_params['privacy'] = '1';
                    ?>
                    <a href="<?php echo build_team_url( 'admin.php', $add_params ); ?>" class="btn">+ Add New Team Member</a>
                </div>
                <?php if ( ! empty( $config['team_members'] ) ) : ?>
                    <div class="person-list">
                        <?php
                        // Sort members by name
                        $sorted_members = $config['team_members'];
                        uasort( $sorted_members, function( $a, $b ) {
                            return strcasecmp( $a['name'], $b['name'] );
                        } );

                        foreach ( $sorted_members as $username => $member ) : ?>
                            <div class="person-item">
                                <div class="person-info">
                                    <h4><?php echo htmlspecialchars( mask_name( $member['name'], $privacy_mode ) ); ?></h4>
                                    <small>@<?php echo htmlspecialchars( mask_username( $username, $privacy_mode ) ); ?> • <?php echo htmlspecialchars( $member['location'] ?? $member['town'] ?? '' ); ?></small>
                                </div>
                                <div style="display: flex; gap: 8px;">
                                    <?php
                                    $edit_params = array( 'edit_member' => $username );
                                    if ( $privacy_mode ) $edit_params['privacy'] = '1';
                                    ?>
                                    <a href="<?php echo build_team_url( 'admin.php', $edit_params ); ?>" class="btn">Edit</a>
                                    <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this team member?')">
                                        <input type="hidden" name="action" value="delete_member">
                                        <input type="hidden" name="username" value="<?php echo htmlspecialchars( $username ); ?>">
                                        <?php if ( $current_team !== 'team' ) : ?>
                                            <input type="hidden" name="team" value="<?php echo htmlspecialchars( $current_team ); ?>">
                                        <?php endif; ?>
                                        <button type="submit" class="btn btn-danger">Delete</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <p>No team members added yet.</p>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ( $is_editing_member ) : ?>
                <?php render_person_form( 'member', $edit_data, $is_editing_member ); ?>
            <?php elseif ( isset( $_GET['add'] ) && $_GET['add'] === 'new' ) : ?>
                <div style="margin-bottom: 20px;">
                    <?php
                    $back_params = array( 'tab' => 'members' );
                    if ( $privacy_mode ) $back_params['privacy'] = '1';
                    ?>
                    <a href="<?php echo build_team_url( 'admin.php', $back_params ); ?>" class="back-link-admin">← Back to Team Members</a>
                </div>
                <h3>Add New Team Member</h3>
                <?php render_person_form( 'member', null, false ); ?>
            <?php endif; ?>
        </div>

        <!-- Leadership Tab -->
        <div id="leadership" class="tab-content <?php echo $active_tab === 'leadership' ? 'active' : ''; ?>">
            <?php if ( $is_editing_leader ) : ?>
                <h2>Edit Leadership: <?php echo htmlspecialchars( mask_name( $edit_data['name'] ?? $edit_data['username'], $privacy_mode ) ); ?></h2>
            <?php else : ?>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3 style="margin: 0;">Current Leadership</h3>
                    <?php
                    $add_params = array( 'tab' => 'leadership', 'add' => 'new' );
                    if ( $privacy_mode ) $add_params['privacy'] = '1';
                    ?>
                    <a href="<?php echo build_team_url( 'admin.php', $add_params ); ?>" class="btn">+ Add New Leader</a>
                </div>
                <?php if ( ! empty( $config['leadership'] ) ) : ?>
                    <div class="person-list">
                        <?php
                        // Sort leadership by name
                        $sorted_leadership = $config['leadership'];
                        uasort( $sorted_leadership, function( $a, $b ) {
                            return strcasecmp( $a['name'], $b['name'] );
                        } );

                        foreach ( $sorted_leadership as $username => $leader ) : ?>
                            <div class="person-item">
                                <div class="person-info">
                                    <h4><?php echo htmlspecialchars( mask_name( $leader['name'], $privacy_mode ) ); ?> <small>(<?php echo htmlspecialchars( $leader['role'] ); ?>)</small></h4>
                                    <small>@<?php echo htmlspecialchars( mask_username( $username, $privacy_mode ) ); ?> • <?php echo htmlspecialchars( $leader['location'] ?? $leader['town'] ?? '' ); ?></small>
                                </div>
                                <div style="display: flex; gap: 8px;">
                                    <?php
                                    $edit_params = array( 'edit_member' => $username );
                                    if ( $privacy_mode ) $edit_params['privacy'] = '1';
                                    ?>
                                    <a href="<?php echo build_team_url( 'admin.php', $edit_params ); ?>" class="btn">Edit</a>
                                    <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this leader?')">
                                        <input type="hidden" name="action" value="delete_leader">
                                        <input type="hidden" name="username" value="<?php echo htmlspecialchars( $username ); ?>">
                                        <?php if ( $current_team !== 'team' ) : ?>
                                            <input type="hidden" name="team" value="<?php echo htmlspecialchars( $current_team ); ?>">
                                        <?php endif; ?>
                                        <button type="submit" class="btn btn-danger">Delete</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <p>No leaders added yet.</p>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ( $is_editing_leader ) : ?>
                <?php render_person_form( 'leader', $edit_data, $is_editing_leader ); ?>
            <?php elseif ( isset( $_GET['add'] ) && $_GET['add'] === 'new' ) : ?>
                <div style="margin-bottom: 20px;">
                    <?php
                    $back_params = array( 'tab' => 'leadership' );
                    if ( $privacy_mode ) $back_params['privacy'] = '1';
                    ?>
                    <a href="<?php echo build_team_url( 'admin.php', $back_params ); ?>" class="back-link-admin">← Back to Leadership</a>
                </div>
                <h3>Add New Leader</h3>
                <?php render_person_form( 'leader', null, false ); ?>
            <?php endif; ?>
        </div>

        <!-- Alumni Tab -->
        <div id="alumni" class="tab-content <?php echo $active_tab === 'alumni' ? 'active' : ''; ?>">
            <?php if ( $is_editing_alumni ) : ?>
                <h2>Edit Alumni: <?php echo htmlspecialchars( mask_name( $edit_data['name'] ?? $edit_data['username'], $privacy_mode ) ); ?></h2>
            <?php else : ?>

                <h3>Current Alumni</h3>
                <?php if ( ! empty( $config['alumni'] ) ) : ?>
                    <div class="person-list">
                        <?php
                        // Sort alumni by name
                        $sorted_alumni = $config['alumni'];
                        uasort( $sorted_alumni, function( $a, $b ) {
                            return strcasecmp( $a['name'], $b['name'] );
                        } );

                        foreach ( $sorted_alumni as $username => $alumni_member ) : ?>
                            <div class="person-item">
                                <div class="person-info">
                                    <h4><?php echo htmlspecialchars( mask_name( $alumni_member['name'], $privacy_mode ) ); ?> <small>(Alumni)</small></h4>
                                    <small>@<?php echo htmlspecialchars( mask_username( $username, $privacy_mode ) ); ?> • <?php echo htmlspecialchars( $alumni_member['location'] ?? $alumni_member['town'] ?? '' ); ?></small>
                                </div>
                                <div style="display: flex; gap: 8px;">
                                    <?php
                                    $edit_params = array( 'edit_member' => $username );
                                    if ( $privacy_mode ) $edit_params['privacy'] = '1';
                                    ?>
                                    <a href="<?php echo build_team_url( 'admin.php', $edit_params ); ?>" class="btn">Edit</a>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="action" value="restore_from_alumni">
                                        <input type="hidden" name="username" value="<?php echo htmlspecialchars( $username ); ?>">
                                        <?php if ( $current_team !== 'team' ) : ?>
                                            <input type="hidden" name="team" value="<?php echo htmlspecialchars( $current_team ); ?>">
                                        <?php endif; ?>
                                        <?php
                                        $original_section = $alumni_member['original_section'] ?? 'team_members'; // Default to team_members for legacy data
                                        $display_name = $original_section === 'leadership' ? 'Leadership' : 'Team Member';
                                        ?>
                                        <button type="submit" class="btn" onclick="return confirm('Are you sure you want to restore this person to their original position (<?php echo $display_name; ?>)?')">Restore to <?php echo $display_name; ?></button>
                                    </form>
                                    <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this alumni member?')">
                                        <input type="hidden" name="action" value="delete_alumni">
                                        <input type="hidden" name="username" value="<?php echo htmlspecialchars( $username ); ?>">
                                        <?php if ( $current_team !== 'team' ) : ?>
                                            <input type="hidden" name="team" value="<?php echo htmlspecialchars( $current_team ); ?>">
                                        <?php endif; ?>
                                        <button type="submit" class="btn btn-danger">Delete</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <p>No alumni members yet.</p>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ( $is_editing_alumni ) : ?>
                <?php render_person_form( 'alumni', $edit_data, $is_editing_alumni ); ?>
            <?php else : ?>
                <p class="text-muted italic-text" style="margin-top: 20px;">
                    Alumni members can only be created by moving existing team members or leaders to alumni status.
                    Use the "📚 Move to Alumni" button when editing a team member or leader.
                </p>
            <?php endif; ?>
        </div>

        <!-- Events Tab -->
        <div id="events" class="tab-content <?php echo $active_tab === 'events' ? 'active' : ''; ?>">
            <?php if ( $is_editing_event ) : ?>
                <h2>Edit Event: <?php echo htmlspecialchars( $edit_data['name'] ?? 'Event' ); ?></h2>
            <?php endif; ?>

            <?php if ( ! $is_editing_event ) : ?>
                <h3>Current Events</h3>
                <?php if ( ! empty( $config['events'] ) ) : ?>
                    <div class="person-list">
                        <?php foreach ( $config['events'] as $index => $event ) : ?>
                            <div class="person-item">
                                <div class="person-info">
                                    <h4><?php echo htmlspecialchars( $event['name'] ); ?></h4>
                                    <small><?php echo htmlspecialchars( $event['start_date'] ); ?> • <?php echo htmlspecialchars( $event['location'] ); ?> • <?php echo ucfirst( $event['type'] ); ?></small>
                                    <?php if ( ! empty( $event['links'] ) ) : ?>
                                        <div class="person-links" style="margin-top: 8px;">
                                            <?php foreach ( $event['links'] as $link_text => $link_url ) : ?>
                                                <a href="<?php echo htmlspecialchars( $link_url ); ?>" target="_blank" class="link-primary text-small" style="margin-right: 10px;">
                                                    <?php echo htmlspecialchars( $link_text ); ?> →
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div style="display: flex; gap: 8px;">
                                    <a href="<?php echo build_team_url( 'admin.php', array( 'tab' => 'events', 'edit_event' => $index ) ); ?>" class="btn">Edit</a>
                                    <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this event?')">
                                        <input type="hidden" name="action" value="delete_event">
                                        <input type="hidden" name="event_index" value="<?php echo $index; ?>">
                                        <?php if ( $current_team !== 'team' ) : ?>
                                            <input type="hidden" name="team" value="<?php echo htmlspecialchars( $current_team ); ?>">
                                        <?php endif; ?>
                                        <button type="submit" class="btn btn-danger">Delete</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <p>No events added yet.</p>
                <?php endif; ?>
            <?php endif; ?>
            
            <h3 id="event-form-title"><?php echo $is_editing_event ? 'Edit Event' : 'Add New Event'; ?></h3>
            <form method="post" id="event-form">
                <input type="hidden" id="event-action" name="action" value="<?php echo $is_editing_event ? 'edit_event' : 'add_event'; ?>">
                <input type="hidden" id="event-index" name="event_index" value="<?php echo $is_editing_event ? htmlspecialchars( $edit_data['event_index'] ) : ''; ?>">
                <?php if ( $current_team !== 'team' ) : ?>
                    <input type="hidden" name="team" value="<?php echo htmlspecialchars( $current_team ); ?>">
                <?php endif; ?>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="event-name">Event Name *</label>
                        <input type="text" id="event-name" name="event_name" value="<?php echo $is_editing_event ? htmlspecialchars( $edit_data['name'] ?? '' ) : ''; ?>" required autofocus>
                    </div>
                    
                    <div class="form-group">
                        <label for="event-start-date">Start Date *</label>
                        <input type="date" id="event-start-date" name="start_date" value="<?php echo $is_editing_event ? htmlspecialchars( $edit_data['start_date'] ?? '' ) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="event-end-date">End Date</label>
                        <input type="date" id="event-end-date" name="end_date" value="<?php echo $is_editing_event ? htmlspecialchars( $edit_data['end_date'] ?? '' ) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="event-type">Event Type</label>
                        <select id="event-type" name="event_type" class="form-select" style="width: 100%;">
                            <option value="team" <?php echo $is_editing_event && ($edit_data['type'] ?? '') === 'team' ? 'selected' : ''; ?>>Team Meetup</option>
                            <option value="company" <?php echo $is_editing_event && ($edit_data['type'] ?? '') === 'company' ? 'selected' : ''; ?>>Company Meetup</option>
                            <option value="conference" <?php echo $is_editing_event && ($edit_data['type'] ?? '') === 'conference' ? 'selected' : ''; ?>>Conference</option>
                            <option value="training" <?php echo $is_editing_event && ($edit_data['type'] ?? '') === 'training' ? 'selected' : ''; ?>>Training</option>
                            <option value="other" <?php echo $is_editing_event && ($edit_data['type'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="event-location">Location</label>
                        <input type="text" id="event-location" name="location" value="<?php echo $is_editing_event ? htmlspecialchars( $edit_data['location'] ?? '' ) : ''; ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="event-description">Description</label>
                    <textarea id="event-description" name="description"><?php echo $is_editing_event ? htmlspecialchars( $edit_data['description'] ?? '' ) : ''; ?></textarea>
                </div>
                
                <div class="form-group">
                    <div class="event-links-section">
                        <label style="font-weight: bold; margin-bottom: 10px; display: block;" class="text-dark">🔗 Event Links</label>
                        <p class="text-small-muted" style="margin-bottom: 15px;">Add links for Zoom calls, agendas, documents, etc. You can paste rich text links and they'll be auto-parsed.</p>
                        
                        <div id="event-links-container" style="margin-bottom: 15px;">
                            <?php if ( $is_editing_event && ! empty( $edit_data['links'] ) ) : ?>
                                <?php $link_index = 0; foreach ( $edit_data['links'] as $link_text => $link_url ) : ?>
                                    <div class="link-row" style="display: flex; gap: 10px; margin-bottom: 10px; align-items: center;">
                                        <input type="text" name="event_links[<?php echo $link_index; ?>][text]" 
                                               value="<?php echo htmlspecialchars( $link_text ); ?>" 
                                               placeholder="Link text (e.g., Zoom, Agenda)" 
                                               class="form-select" style="flex: 0 0 200px;">
                                        <input type="url" name="event_links[<?php echo $link_index; ?>][url]" 
                                               value="<?php echo htmlspecialchars( $link_url ); ?>" 
                                               placeholder="URL" 
                                               class="form-select" style="flex: 1;">
                                        <button type="button" class="remove-link-btn" 
                                                class="btn-large-remove">Remove</button>
                                    </div>
                                <?php $link_index++; endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <button type="button" id="add-event-link-btn" 
                                class="btn-large-primary">
                            + Add Link
                        </button>
                    </div>
                </div>
                
                <button type="submit" class="btn" id="event-submit-btn"><?php echo $is_editing_event ? 'Update Event' : 'Add Event'; ?></button>
            </form>
        </div>

        <!-- Audit Tab -->
        <div id="audit" class="tab-content <?php echo $active_tab === 'audit' ? 'active' : ''; ?>">
            <h2>📊 Data Completeness Audit</h2>
            <p class="text-muted" style="margin-bottom: 20px;">Identify missing data points and improve team profiles</p>

            <?php
            // Get audit data for all people
            $audit_data = array();

            // Team members
            foreach ( $config['team_members'] as $username => $member ) {
                $missing = get_missing_data_points( $member, 'member' );
                $score = get_completeness_score( $missing, 'member' );
                $audit_data[] = array(
                    'type' => 'Team Member',
                    'name' => $member['name'],
                    'username' => $username,
                    'missing' => $missing,
                    'score' => $score,
                    'person' => $member
                );
            }

            // Leadership
            foreach ( $config['leadership'] as $username => $leader ) {
                $missing = get_missing_data_points( $leader, 'leader' );
                $score = get_completeness_score( $missing, 'leader' );
                $audit_data[] = array(
                    'type' => 'Leadership',
                    'name' => $leader['name'],
                    'username' => $username,
                    'missing' => $missing,
                    'score' => $score,
                    'person' => $leader
                );
            }

            // Alumni
            foreach ( $config['alumni'] ?? array() as $username => $alumnus ) {
                $missing = get_missing_data_points( $alumnus, 'alumni' );
                $score = get_completeness_score( $missing, 'alumni' );
                $audit_data[] = array(
                    'type' => 'Alumni',
                    'name' => $alumnus['name'],
                    'username' => $username,
                    'missing' => $missing,
                    'score' => $score,
                    'person' => $alumnus
                );
            }

            // Sort by completeness score (lowest first to prioritize fixes)
            usort( $audit_data, function( $a, $b ) {
                if ( $a['score'] === $b['score'] ) {
                    return strcasecmp( $a['name'], $b['name'] );
                }
                return $a['score'] <=> $b['score'];
            } );

            // Calculate statistics
            $total_people = count( $audit_data );
            $complete_profiles = count( array_filter( $audit_data, function( $item ) { return $item['score'] >= 90; } ) );
            $needs_attention = count( array_filter( $audit_data, function( $item ) { return $item['score'] < 70; } ) );
            $avg_score = $total_people > 0 ? round( array_sum( array_column( $audit_data, 'score' ) ) / $total_people ) : 0;
            ?>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
                <div class="admin-section">
                    <div class="stat-number"><?php echo $total_people; ?></div>
                    <div class="stat-label">Total People</div>
                </div>
                <div class="admin-section">
                    <div class="stat-number"><?php echo $avg_score; ?>%</div>
                    <div class="stat-label">Average Completeness</div>
                </div>
                <div class="admin-section">
                    <div class="stat-number"><?php echo $complete_profiles; ?></div>
                    <div class="stat-label">Complete Profiles (90%+)</div>
                </div>
                <div class="admin-section">
                    <div class="stat-number"><?php echo $needs_attention; ?></div>
                    <div class="stat-label">Needs Attention (&lt;70%)</div>
                </div>
            </div>

            <div class="events-tab-section">
                <span style="margin-right: 15px; font-weight: 600;">Filter by:</span>
                <select class="form-select-small" style="margin-right: 15px;" id="type-filter" onchange="filterAuditTable()">
                    <option value="">All Types</option>
                    <option value="Team Member">Team Members</option>
                    <option value="Leadership">Leadership</option>
                    <option value="Alumni">Alumni</option>
                </select>
                <select class="form-select-small" id="score-filter" onchange="filterAuditTable()">
                    <option value="">All Scores</option>
                    <option value="poor">Poor (&lt;50%)</option>
                    <option value="fair">Fair (50-79%)</option>
                    <option value="good">Good (80-89%)</option>
                    <option value="excellent">Excellent (90%+)</option>
                </select>
            </div>

            <table style="width: 100%; border-collapse: collapse; margin-top: 20px;" id="audit-table">
                <thead>
                    <tr class="table-header-row">
                        <th class="table-cell">Person</th>
                        <th class="table-cell">Type</th>
                        <th class="table-cell">Completeness</th>
                        <th class="table-cell">Missing Data Points</th>
                        <th class="table-cell">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $audit_data as $item ) : ?>
                        <tr class="table-row" data-type="<?php echo htmlspecialchars( $item['type'] ); ?>" data-score="<?php echo $item['score']; ?>">
                            <td class="table-cell" style="font-weight: normal;">
                                <div style="font-weight: 600;">
                                    <?php echo htmlspecialchars( mask_name( $item['name'], $privacy_mode ) ); ?>
                                </div>
                                <div class="text-small-muted">
                                    @<?php echo htmlspecialchars( mask_username( $item['username'], $privacy_mode ) ); ?>
                                </div>
                            </td>
                            <td class="table-cell" style="font-weight: normal;"><?php echo htmlspecialchars( $item['type'] ); ?></td>
                            <td class="table-cell" style="font-weight: normal;">
                                <span class="<?php
                                    if ( $item['score'] >= 90 ) echo 'score-excellent';
                                    elseif ( $item['score'] >= 80 ) echo 'score-good';
                                    elseif ( $item['score'] >= 50 ) echo 'score-fair';
                                    else echo 'score-poor';
                                ?>" style="display: inline-block; padding: 4px 8px; border-radius: 12px; font-weight: 600; font-size: 12px; min-width: 40px; text-align: center;"><?php echo $item['score']; ?>%</span>
                            </td>
                            <td class="table-cell" style="font-weight: normal; font-size: 13px;">
                                <?php if ( empty( $item['missing'] ) ) : ?>
                                    <span class="link-success">✅ Complete</span>
                                <?php else : ?>
                                    <?php foreach ( $item['missing'] as $missing_item ) : ?>
                                        <span class="<?php echo strpos( $missing_item, 'optional' ) !== false ? 'link-secondary' : 'link-danger'; ?>">
                                            <?php echo htmlspecialchars( $missing_item ); ?>
                                        </span><?php echo $missing_item !== end( $item['missing'] ) ? ', ' : ''; ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                            <td class="table-cell" style="font-weight: normal;">
                                <a href="<?php echo build_team_url( 'admin.php', array( 'edit_member' => $item['username'] ) ); ?>" class="link-primary text-small" style="margin-right: 8px;">✏️ Edit</a>
                                <a href="<?php echo build_team_url( 'index.php', array( 'person' => $item['username'] ) ); ?>" class="link-primary text-small" target="_blank">👁️ View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- JSON View Tab -->
        <div id="json" class="tab-content <?php echo $active_tab === 'json' ? 'active' : ''; ?>">
            <p>This is the current contents of your team.json file:</p>
            <pre class="config-preview"><?php echo htmlspecialchars( json_encode( $config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre>
            
            <p style="margin-top: 20px;">
                <a href="<?php echo build_team_url( 'index.php' ); ?>" class="btn" target="_blank">View Team Dashboard</a>
            </p>
        </div>
        
        <?php endif; ?>

    <script>
        const teamMembers = <?php echo json_encode( $config['team_members'] ); ?>;
        const leadership = <?php echo json_encode( $config['leadership'] ); ?>;
        const events = <?php echo json_encode( array_values( $config['events'] ) ); ?>;
        
        function showTab(tabName) {
            // Hide all tab content
            const contents = document.querySelectorAll('.tab-content');
            contents.forEach(content => content.classList.remove('active'));
            
            // Remove active class from all tabs
            const tabs = document.querySelectorAll('.nav-tab');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            // Show selected tab content
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to clicked tab
            event.target.classList.add('active');
        }

        // Team links functionality
        let teamLinkIndex = <?php echo count( $config['team_links'] ?? array() ); ?>;

        document.addEventListener('DOMContentLoaded', function() {
            // Function to get next available index
            function getNextTeamLinkIndex() {
                const container = document.getElementById('team-links-container');
                const existingRows = container.querySelectorAll('.team-link-row');
                let maxIndex = -1;
                
                existingRows.forEach(row => {
                    const textInput = row.querySelector('input[type="text"]');
                    if (textInput && textInput.name) {
                        const match = textInput.name.match(/team_links\[(\d+)\]\[text\]/);
                        if (match) {
                            maxIndex = Math.max(maxIndex, parseInt(match[1]));
                        }
                    }
                });
                
                return maxIndex + 1;
            }
            
            // Add link button
            const addButton = document.getElementById('add-team-link');
            if (addButton) {
                addButton.addEventListener('click', function() {
                    const container = document.getElementById('team-links-container');
                    const currentIndex = getNextTeamLinkIndex();
                    const linkRow = document.createElement('div');
                    linkRow.className = 'team-link-row';
                    linkRow.style.cssText = 'display: flex; gap: 10px; margin-bottom: 10px; align-items: center;';

                    linkRow.innerHTML = `
                        <input type="text" name="team_links[${currentIndex}][text]" value="" placeholder="Link text (e.g., Linear)" style="flex: 0 0 150px;">
                        <input type="url" name="team_links[${currentIndex}][url]" value="" placeholder="https://..." style="flex: 1;">
                        <button type="button" class="remove-team-link btn-remove-personal">Remove</button>
                    `;

                    container.appendChild(linkRow);

                    // Add event listener to the new remove button
                    linkRow.querySelector('.remove-team-link').addEventListener('click', function() {
                        linkRow.remove();
                    });
                });
            }

            // Remove link buttons
            document.querySelectorAll('.remove-team-link').forEach(button => {
                button.addEventListener('click', function() {
                    this.parentElement.remove();
                });
            });
        });
        
        // All editing is now handled server-side via URL parameters
        
        // Enhanced timezone autocomplete functionality
        function initTimezoneAutocomplete() {
            const timezoneInputs = document.querySelectorAll('input[id$="timezone"]');
            
            timezoneInputs.forEach(function(input) {
                const prefix = input.id.replace('timezone', '');
                const suggestionsDiv = document.getElementById(prefix + 'timezone-suggestions');
                const dataScript = document.getElementById(prefix + 'timezone-data');

                if (!suggestionsDiv || !dataScript) return;

                const timezones = JSON.parse(dataScript.textContent);
                let selectedIndex = -1;
                let currentSuggestions = [];

                function showSuggestions(filteredTimezones) {
                    currentSuggestions = filteredTimezones;
                    selectedIndex = -1;

                    if (filteredTimezones.length === 0) {
                        suggestionsDiv.style.display = 'none';
                        return;
                    }

                    suggestionsDiv.innerHTML = '';
                    filteredTimezones.forEach(function(tz, index) {
                        const div = document.createElement('div');
                        div.className = 'timezone-suggestion';
                        div.textContent = tz.value + ' (' + tz.label + ')';
                        div.addEventListener('click', function() {
                            input.value = tz.value;
                            suggestionsDiv.style.display = 'none';
                        });
                        suggestionsDiv.appendChild(div);
                    });

                    suggestionsDiv.style.display = 'block';
                }

                function filterTimezones(query) {
                    if (!query) return [];

                    const lowerQuery = query.toLowerCase();
                    return timezones.filter(function(tz) {
                        return tz.value.toLowerCase().includes(lowerQuery) ||
                               tz.label.toLowerCase().includes(lowerQuery);
                    }).slice(0, 10); // Limit to 10 suggestions
                }

                input.addEventListener('input', function() {
                    const query = input.value.trim();
                    const filteredTimezones = filterTimezones(query);
                    showSuggestions(filteredTimezones);
                });

                input.addEventListener('keydown', function(e) {
                    if (suggestionsDiv.style.display === 'none') return;

                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        selectedIndex = Math.min(selectedIndex + 1, currentSuggestions.length - 1);
                        updateSelection();
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        selectedIndex = Math.max(selectedIndex - 1, -1);
                        updateSelection();
                    } else if (e.key === 'Enter') {
                        e.preventDefault();
                        if (selectedIndex >= 0 && currentSuggestions[selectedIndex]) {
                            input.value = currentSuggestions[selectedIndex].value;
                            suggestionsDiv.style.display = 'none';
                        } else if (currentSuggestions.length === 1) {
                            input.value = currentSuggestions[0].value;
                            suggestionsDiv.style.display = 'none';
                        } else {
                            // Validate if entered value is a valid timezone
                            const isValid = timezones.some(function(tz) {
                                return tz.value === input.value;
                            });
                            if (!isValid && input.value.trim()) {
                                // Try to find exact match or close match
                                const exactMatch = timezones.find(function(tz) {
                                    return tz.value.toLowerCase() === input.value.toLowerCase();
                                });
                                if (exactMatch) {
                                    input.value = exactMatch.value;
                                } else {
                                    // Clear invalid input
                                    input.value = '';
                                    alert('Please select a valid timezone from the suggestions.');
                                }
                            }
                            suggestionsDiv.style.display = 'none';
                        }
                    } else if (e.key === 'Escape') {
                        suggestionsDiv.style.display = 'none';
                    }
                });

                function updateSelection() {
                    const suggestions = suggestionsDiv.querySelectorAll('.timezone-suggestion');
                    suggestions.forEach(function(suggestion, index) {
                        if (index === selectedIndex) {
                            suggestion.classList.add('selected');
                        } else {
                            suggestion.classList.remove('selected');
                        }
                    });
                }

                // Hide suggestions when clicking outside
                document.addEventListener('click', function(e) {
                    if (!input.contains(e.target) && !suggestionsDiv.contains(e.target)) {
                        suggestionsDiv.style.display = 'none';
                    }
                });

                // Auto-select and validate on blur
                input.addEventListener('blur', function() {
                    setTimeout(function() {
                        if (input.value.trim()) {
                            // First, check if there's exactly one matching suggestion
                            const filteredTimezones = filterTimezones(input.value.trim());
                            if (filteredTimezones.length === 1) {
                                input.value = filteredTimezones[0].value;
                                suggestionsDiv.style.display = 'none';
                                return;
                            }

                            // Then validate if it's already a valid timezone
                            const isValid = timezones.some(function(tz) {
                                return tz.value === input.value;
                            });
                            if (!isValid) {
                                input.value = '';
                            }
                        }
                        suggestionsDiv.style.display = 'none';
                    }, 150); // Delay to allow click on suggestion
                });
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
        
        // Auto-generate slug from team name
        document.addEventListener('DOMContentLoaded', function() {
            const teamNameInput = document.getElementById('new_team_name');
            const teamSlugInput = document.getElementById('new_team_slug');
            
            if (teamNameInput && teamSlugInput) {
                teamNameInput.addEventListener('input', function() {
                    // Auto-generate slug if slug field is empty or matches previous auto-generated value
                    let slug = this.value
                        .toLowerCase()
                        .replace(/[^a-z0-9\s-]/g, '') // Remove special characters except spaces and hyphens
                        .replace(/\s+/g, '-') // Replace spaces with hyphens
                        .replace(/-+/g, '-') // Replace multiple hyphens with single hyphen
                        .replace(/^-|-$/g, ''); // Remove leading/trailing hyphens
                    teamSlugInput.value = slug;
                });
            }
        });
        
        // Links management functions
        function addLink(prefix) {
            const container = document.getElementById(prefix + 'links-container');
            const newRow = document.createElement('div');
            newRow.className = 'link-row';
            newRow.style.cssText = 'display: flex; gap: 10px; margin-bottom: 10px; align-items: center;';
            newRow.innerHTML = `
                <input type="text" name="link_text[]" placeholder="Link text (e.g., 'Project docs')" style="flex: 1;">
                <input type="url" name="link_url[]" placeholder="URL" style="flex: 2;">
                <button type="button" onclick="removeLink(this)" class="btn-remove">Remove</button>
            `;
            container.appendChild(newRow);
        }

        function removeLink(button) {
            const row = button.parentNode;
            const container = row.parentNode;

            // Don't remove if it's the last row - just clear it instead
            if (container.children.length === 1) {
                const inputs = row.querySelectorAll('input');
                inputs.forEach(input => input.value = '');
            } else {
                row.remove();
            }
        }

        // Event links management
        let eventLinkCounter = 0;
        function addEventLink() {
            const container = document.getElementById('event-links-container');
            const newRow = document.createElement('div');
            newRow.className = 'link-row';
            newRow.style.cssText = 'display: flex; gap: 10px; margin-bottom: 10px; align-items: center;';
            newRow.innerHTML = `
                <input type="text" name="event_links[${eventLinkCounter}][text]" 
                       placeholder="Link text (e.g., Zoom, Agenda)" 
                       class="form-select" style="flex: 0 0 200px;">
                <input type="url" name="event_links[${eventLinkCounter}][url]" 
                       placeholder="URL" 
                       class="form-select" style="flex: 1;">
                <button type="button" onclick="removeEventLink(this)" 
                        class="btn-large-remove">Remove</button>
            `;
            container.appendChild(newRow);
            
            // Setup rich text parsing for the new inputs
            const textInput = newRow.querySelector('input[type="text"]');
            const urlInput = newRow.querySelector('input[type="url"]');
            setupRichTextLinkParsing(textInput, urlInput);
            
            eventLinkCounter++;
        }

        function removeEventLink(button) {
            button.parentNode.remove();
        }

        // Rich text link parser
        function parseRichTextLink(pastedText) {
            // Try to extract URL from various formats
            const urlRegex = /(https?:\/\/[^\s]+)/gi;
            const matches = pastedText.match(urlRegex);
            
            if (matches && matches.length > 0) {
                // Extract the URL
                const url = matches[0];
                // Extract text by removing the URL and cleaning up
                let text = pastedText.replace(url, '').trim();
                
                // Remove common markdown link formatting if present
                text = text.replace(/^\[|\]$/g, ''); // Remove [ and ]
                text = text.replace(/^\(|\)$/g, ''); // Remove ( and )
                
                // If no meaningful text remains, try to get domain from URL
                if (!text || text.length < 2) {
                    try {
                        const urlObj = new URL(url);
                        text = urlObj.hostname.replace('www.', '');
                    } catch (e) {
                        text = 'Link';
                    }
                }
                
                return { text: text, url: url };
            }
            
            // Check if the entire pasted content looks like a URL
            if (urlRegex.test(pastedText.trim())) {
                try {
                    const urlObj = new URL(pastedText.trim());
                    return { 
                        text: urlObj.hostname.replace('www.', ''),
                        url: pastedText.trim()
                    };
                } catch (e) {
                    return null;
                }
            }
            
            return null;
        }

        function setupRichTextLinkParsing(textInput, urlInput) {
            textInput.addEventListener('paste', async function(e) {
                e.preventDefault();
                
                try {
                    // Try to read from clipboard with rich text support
                    if (navigator.clipboard && navigator.clipboard.read) {
                        const clipboardItems = await navigator.clipboard.read();
                        
                        for (const clipboardItem of clipboardItems) {
                            // Try HTML format first (rich text)
                            if (clipboardItem.types.includes('text/html')) {
                                const htmlBlob = await clipboardItem.getType('text/html');
                                const htmlText = await htmlBlob.text();
                                
                                // Parse HTML for links
                                const tempDiv = document.createElement('div');
                                tempDiv.innerHTML = htmlText;
                                const links = tempDiv.querySelectorAll('a[href]');
                                
                                if (links.length > 0 && !urlInput.value) {
                                    // Use the first link found
                                    const link = links[0];
                                    const linkText = link.textContent.trim();
                                    const linkUrl = link.href;
                                    
                                    textInput.value = linkText || linkUrl;
                                    urlInput.value = linkUrl;
                                    
                                    // Add visual feedback
                                    urlInput.classList.add('success-highlight');
                                    setTimeout(() => {
                                        urlInput.style.background = '';
                                    }, 1500);
                                    return;
                                }
                            }
                            
                            // Fallback to plain text
                            if (clipboardItem.types.includes('text/plain')) {
                                const textBlob = await clipboardItem.getType('text/plain');
                                const plainText = await textBlob.text();
                                
                                const parsed = parseRichTextLink(plainText);
                                if (parsed && !urlInput.value) {
                                    textInput.value = parsed.text;
                                    urlInput.value = parsed.url;
                                    
                                    // Add visual feedback
                                    urlInput.classList.add('success-highlight');
                                    setTimeout(() => {
                                        urlInput.style.background = '';
                                    }, 1500);
                                    return;
                                }
                                
                                // If no URL found, just paste the text normally
                                textInput.value = plainText;
                            }
                        }
                    } else {
                        // Fallback for older browsers - use clipboardData
                        const clipboardData = e.clipboardData || window.clipboardData;
                        if (clipboardData) {
                            // Try HTML first
                            let htmlData = clipboardData.getData('text/html');
                            if (htmlData) {
                                const tempDiv = document.createElement('div');
                                tempDiv.innerHTML = htmlData;
                                const links = tempDiv.querySelectorAll('a[href]');
                                
                                if (links.length > 0 && !urlInput.value) {
                                    const link = links[0];
                                    const linkText = link.textContent.trim();
                                    const linkUrl = link.href;
                                    
                                    textInput.value = linkText || linkUrl;
                                    urlInput.value = linkUrl;
                                    
                                    // Add visual feedback
                                    urlInput.classList.add('success-highlight');
                                    setTimeout(() => {
                                        urlInput.style.background = '';
                                    }, 1500);
                                    return;
                                }
                            }
                            
                            // Fallback to plain text
                            const plainText = clipboardData.getData('text/plain') || clipboardData.getData('text');
                            if (plainText) {
                                const parsed = parseRichTextLink(plainText);
                                if (parsed && !urlInput.value) {
                                    textInput.value = parsed.text;
                                    urlInput.value = parsed.url;
                                    
                                    // Add visual feedback
                                    urlInput.classList.add('success-highlight');
                                    setTimeout(() => {
                                        urlInput.style.background = '';
                                    }, 1500);
                                } else {
                                    textInput.value = plainText;
                                }
                            }
                        }
                    }
                } catch (error) {
                    console.log('Clipboard API failed, falling back to normal paste:', error);
                    // Allow default paste behavior
                    textInput.focus();
                    document.execCommand('paste');
                }
            });
        }

        // Initialize event link handlers
        document.addEventListener('DOMContentLoaded', function() {
            const addEventLinkBtn = document.getElementById('add-event-link-btn');
            if (addEventLinkBtn) {
                addEventLinkBtn.addEventListener('click', addEventLink);
            }

            // Initialize counter based on existing links
            const existingLinks = document.querySelectorAll('#event-links-container .link-row');
            eventLinkCounter = existingLinks.length;

            // Add remove handlers to existing links
            document.querySelectorAll('.remove-link-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    removeEventLink(this);
                });
            });

            // Setup rich text parsing for existing link inputs
            document.querySelectorAll('#event-links-container .link-row').forEach(row => {
                const textInput = row.querySelector('input[type="text"]');
                const urlInput = row.querySelector('input[type="url"]');
                if (textInput && urlInput) {
                    setupRichTextLinkParsing(textInput, urlInput);
                }
            });
        });

        // Initialize timezone autocomplete when page loads
        document.addEventListener('DOMContentLoaded', initTimezoneAutocomplete);

        // Filter functionality for audit table
        function filterAuditTable() {
            const typeFilter = document.getElementById('type-filter').value;
            const scoreFilter = document.getElementById('score-filter').value;
            const table = document.getElementById('audit-table');
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const type = row.getAttribute('data-type');
                const score = parseInt(row.getAttribute('data-score'));

                let showRow = true;

                // Filter by type
                if (typeFilter && type !== typeFilter) {
                    showRow = false;
                }

                // Filter by score range
                if (scoreFilter) {
                    if (scoreFilter === 'poor' && score >= 50) showRow = false;
                    if (scoreFilter === 'fair' && (score < 50 || score >= 80)) showRow = false;
                    if (scoreFilter === 'good' && (score < 80 || score >= 90)) showRow = false;
                    if (scoreFilter === 'excellent' && score < 90) showRow = false;
                }

                row.style.display = showRow ? '' : 'none';
            }
        }
    </script>
    
    <!-- Footer with admin/privacy links -->
    <footer class="footer-admin">
        <?php
        $current_params = $_GET;
        if ( $privacy_mode ) {
            $current_params['privacy'] = '0';
            echo '<a href="?' . http_build_query( $current_params ) . '" class="text-muted" style="text-decoration: none; margin-right: 15px;">🔒 Privacy Mode ON</a>';
        } else {
            $current_params['privacy'] = '1';
            echo '<a href="?' . http_build_query( $current_params ) . '" class="text-muted" style="text-decoration: none; margin-right: 15px;">🔓 Privacy Mode OFF</a>';
        }
        ?>
        <a href="<?php echo build_team_url( 'index.php' ); ?>" class="text-muted" style="text-decoration: none;">👥 Team Overview</a>
    </footer>
    
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
