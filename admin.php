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
$current_team = $_POST['team'] ?? $_GET['team'] ?? 'team';

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
} elseif ( ! empty( $edit_event_index ) && is_numeric( $edit_event_index ) ) {
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
	if ( file_exists( $file_path ) ) {
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
 * Create backup of existing configuration file
 */
function create_backup( $file_path ) {
	if ( ! file_exists( $file_path ) ) {
		return true; // No file to backup
	}

	$backup_path = substr( $file_path, 0, -4 ) . 'bak' . date( '-Y-m-d-H-i-s' ) . '.json';
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
			if ( save_config( $config, $config_file ) ) {
				$message = 'General settings saved successfully!';
			} else {
				$error = 'Failed to save configuration.';
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
			
			$event = array(
				'name' => sanitize_text_field( $_POST['event_name'] ?? '' ),
				'start_date' => sanitize_text_field( $_POST['start_date'] ?? '' ),
				'end_date' => sanitize_text_field( $_POST['end_date'] ?? '' ),
				'type' => sanitize_text_field( $_POST['event_type'] ?? 'team' ),
				'location' => sanitize_text_field( $_POST['location'] ?? '' ),
				'description' => sanitize_textarea_field( $_POST['description'] ?? '' )
			);
			
			$config['events'][ $event_index ] = $event;
			if ( save_config( $config, $config_file ) ) {
				$message = 'Event updated successfully!';
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
			$event = array(
				'name' => sanitize_text_field( $_POST['event_name'] ?? '' ),
				'start_date' => sanitize_text_field( $_POST['start_date'] ?? '' ),
				'end_date' => sanitize_text_field( $_POST['end_date'] ?? '' ),
				'type' => sanitize_text_field( $_POST['event_type'] ?? 'team' ),
				'location' => sanitize_text_field( $_POST['location'] ?? '' ),
				'description' => sanitize_textarea_field( $_POST['description'] ?? '' )
			);
			
			$config['events'][] = $event;
			if ( save_config( $config, $config_file ) ) {
				$message = 'Event added successfully!';
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
	return array(
		'name' => sanitize_text_field( $_POST['name'] ?? '' ),
		'role' => sanitize_text_field( $_POST['role'] ?? '' ),
		'github' => sanitize_text_field( $_POST['github'] ?? '' ),
		'linear' => sanitize_text_field( $_POST['linear'] ?? '' ),
		'location' => sanitize_text_field( $_POST['location'] ?? '' ),
		'timezone' => sanitize_text_field( $_POST['timezone'] ?? '' ),
		'one_on_one' => sanitize_url( $_POST['one_on_one'] ?? '' ),
		'hr_feedback' => sanitize_url( $_POST['hr_feedback'] ?? '' ),
		'birthday' => sanitize_text_field( $_POST['birthday'] ?? '' ),
		'company_anniversary' => sanitize_text_field( $_POST['company_anniversary'] ?? '' ),
		'partner' => sanitize_text_field( $_POST['partner'] ?? '' ),
		'kids' => parse_kids_data( $_POST['kids'] ?? '' ),
		'notes' => sanitize_textarea_field( $_POST['notes'] ?? '' )
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

		// Format: "Name (YYYY-MM-DD)" or "Name (YYYY)" or "Name - YYYY" or just "Name"
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
	$submit_text = $is_editing ? $config['edit_text'] : $config['add_text'];
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

	<!-- Personal Information -->
	<h4 style="margin: 20px 0 10px 0; color: #333; border-bottom: 1px solid #ddd; padding-bottom: 5px;">Personal Information</h4>
	<div class="form-grid">
		<div class="form-group">
			<label for="<?php echo $prefix; ?>name">Full Name<?php echo $privacy_mode ? ' (Privacy Mode - Last name will be masked)' : ''; ?></label>
			<input type="text" id="<?php echo $prefix; ?>name" name="name" value="<?php echo $is_editing ? htmlspecialchars( $privacy_mode ? mask_name( $edit_data['name'] ?? '', true ) : ( $edit_data['name'] ?? '' ) ) : ''; ?>"<?php echo $privacy_mode ? ' placeholder="First name visible only"' : ''; ?>>
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
			<label for="<?php echo $prefix; ?>birthday">Birthday<?php echo $privacy_mode ? ' (Hidden in Privacy Mode)' : ''; ?></label>
			<input type="date" id="<?php echo $prefix; ?>birthday" name="birthday" value="<?php echo $is_editing ? htmlspecialchars( mask_date_input( $edit_data['birthday'] ?? '', $privacy_mode ) ) : ''; ?>"<?php echo $privacy_mode ? ' placeholder="Hidden for privacy"' : ''; ?>>
		</div>
	</div>

	<!-- Family Information -->
	<details style="margin: 20px 0;">
		<summary style="cursor: pointer; font-weight: 600; color: #666; padding: 8px 0; margin-bottom: 10px;">Family Information</summary>

		<div class="form-grid">
			<div class="form-group">
				<label for="<?php echo $prefix; ?>partner">Partner</label>
				<input type="text" id="<?php echo $prefix; ?>partner" name="partner" value="<?php echo $is_editing ? htmlspecialchars( $edit_data['partner'] ?? '' ) : ''; ?>">
			</div>
		</div>

		<div class="form-group">
			<label for="<?php echo $prefix; ?>kids">Kids (one per line, formats: "Name (YYYY-MM-DD)" or "Name (YYYY)" or just "Name")</label>
			<textarea id="<?php echo $prefix; ?>kids" name="kids" rows="4" placeholder="Emma (2010-03-15)&#10;Jake (2012)&#10;Sam"><?php echo $is_editing ? htmlspecialchars( format_kids_for_form( $edit_data['kids'] ?? array() ) ) : ''; ?></textarea>
		</div>
	</details>

	<!-- Company Information -->
	<h4 style="margin: 20px 0 10px 0; color: #333; border-bottom: 1px solid #ddd; padding-bottom: 5px;">Company Information</h4>
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

		<div class="form-group">
			<label for="<?php echo $prefix; ?>one_on_one">1:1 Document URL</label>
			<input type="url" id="<?php echo $prefix; ?>one_on_one" name="one_on_one" value="<?php echo $is_editing ? htmlspecialchars( $edit_data['one_on_one'] ?? '' ) : ''; ?>">
		</div>

		<?php if ( $show_hr_feedback ) : ?>
		<div class="form-group">
			<label for="<?php echo $prefix; ?>hr_feedback">HR Feedback URL</label>
			<input type="url" id="<?php echo $prefix; ?>hr_feedback" name="hr_feedback" value="<?php echo $is_editing ? htmlspecialchars( $edit_data['hr_feedback'] ?? '' ) : ''; ?>">
		</div>
		<?php endif; ?>
	</div>

	<!-- Usernames -->
	<h4 style="margin: 20px 0 10px 0; color: #333; border-bottom: 1px solid #ddd; padding-bottom: 5px;">External Accounts</h4>
	<div class="form-grid">
		<div class="form-group">
			<label for="<?php echo $prefix; ?>github">GitHub Username</label>
			<input type="text" id="<?php echo $prefix; ?>github" name="github" value="<?php echo $is_editing ? htmlspecialchars( $edit_data['github'] ?? '' ) : ''; ?>">
		</div>

		<div class="form-group">
			<label for="<?php echo $prefix; ?>linear">Linear Username</label>
			<input type="text" id="<?php echo $prefix; ?>linear" name="linear" value="<?php echo $is_editing ? htmlspecialchars( $edit_data['linear'] ?? '' ) : ''; ?>">
		</div>
	</div>

	<div class="form-group">
		<label for="<?php echo $prefix; ?>notes">Notes</label>
		<textarea id="<?php echo $prefix; ?>notes" name="notes"><?php echo $is_editing ? htmlspecialchars( $edit_data['notes'] ?? '' ) : ''; ?></textarea>
	</div>

	<button type="submit" class="btn"><?php echo $submit_text; ?></button>

	<?php if ( $is_editing && $show_alumni_actions ) : ?>
		<div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;">
			<h4 style="color: #666; margin-bottom: 10px;">Alumni Actions</h4>
			<form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to move this person to alumni? They will be removed from active team lists.')">
				<input type="hidden" name="action" value="move_to_alumni">
				<input type="hidden" name="username" value="<?php echo htmlspecialchars( $edit_data['username'] ?? '' ); ?>">
				<input type="hidden" name="from_section" value="<?php echo $config['section_key']; ?>">
				<?php if ( $current_team !== 'team' ) : ?>
					<input type="hidden" name="team" value="<?php echo htmlspecialchars( $current_team ); ?>">
				<?php endif; ?>
				<button type="submit" class="btn" style="background: #f0ad4e; border-color: #eea236;">
					📚 Move to Alumni
				</button>
			</form>
			<p style="font-size: 12px; color: #666; margin: 5px 0 0 0;">
				This will move the person to alumni status while preserving all their data.
			</p>
		</div>
	<?php endif; ?>
</form>
<?php
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Management Admin</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f1f1f1;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.13);
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .header p {
            margin: 0;
            color: #666;
            font-size: 14px;
        }
        .header-content {
            flex-grow: 1;
        }
        .navigation {
            margin: 0;
        }
        .nav-link {
            display: inline-block;
            margin-left: 10px;
            padding: 8px 16px;
            background: #0073aa;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            transition: background 0.3s;
            font-size: 14px;
        }
        .nav-link:hover {
            background: #005a87;
        }
        .nav-tabs {
            display: flex;
            border-bottom: 1px solid #ddd;
            margin-bottom: 30px;
        }
        .nav-tab {
            padding: 10px 20px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-bottom: none;
            cursor: pointer;
            text-decoration: none;
            color: #333;
        }
        .nav-tab:hover, .nav-tab.active {
            background: white;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .form-group textarea {
            height: 80px;
            resize: vertical;
        }
        .btn {
            background: #0073aa;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
            font-family: inherit;
        }
        .btn:hover {
            background: #005a87;
            text-decoration: none;
            color: white;
        }
        .btn-danger {
            background: #dc3545;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        .message {
            padding: 10px 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .person-list {
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .person-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .person-item:last-child {
            border-bottom: none;
        }
        .person-info h4 {
            margin: 0 0 5px 0;
        }
        .person-info small {
            color: #666;
        }
        .timezone-input {
            position: relative;
        }
        .timezone-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-top: none;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }
        .timezone-suggestion {
            padding: 8px 12px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
        }
        .timezone-suggestion:hover,
        .timezone-suggestion.selected {
            background: #f0f8ff;
        }
        .timezone-suggestion:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-content">
                <h1><a href="index.php" style="color: inherit; text-decoration: none;">🛠️ Team Management Admin</a></h1>
                <p>Manage your team configuration file</p>
            </div>
            <div class="navigation">
                <!-- Team Switcher -->
                <div class="team-switcher" style="display: inline-block; margin-right: 10px;">
                    <select id="team-selector" onchange="switchTeam()" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; background: white;">
                        <?php
                        $available_teams = get_available_teams();
                        foreach ( $available_teams as $team_slug ) {
                            $team_display_name = get_team_name_from_file( $team_slug );
                            $selected = $team_slug === $current_team ? 'selected' : '';
                            echo '<option value="' . htmlspecialchars( $team_slug ) . '" ' . $selected . '>' . htmlspecialchars( $team_display_name ) . '</option>';
                        }
                        ?>
                    </select>
                    <a href="<?php echo build_team_url( 'admin.php', array( 'create_team' => 'new' ) ); ?>" class="nav-link" style="font-size: 12px; padding: 6px 12px; margin-left: 5px;">+ New Team</a>
                </div>
                
                <?php
                $current_params = $_GET;
                if ( $privacy_mode ) {
                    $current_params['privacy'] = '0';
                    echo '<a href="?' . http_build_query( $current_params ) . '" class="nav-link" style="background: #28a745;">🔒 Privacy Mode ON</a>';
                } else {
                    $current_params['privacy'] = '1';
                    echo '<a href="?' . http_build_query( $current_params ) . '" class="nav-link" style="background: #dc3545;">🔓 Privacy Mode OFF</a>';
                }
                ?>
                <a href="<?php echo build_team_url( 'index.php' ); ?>" class="nav-link">📊 Team Overview</a>
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
                <a href="<?php echo build_team_url( 'admin.php' ); ?>" style="color: #666; text-decoration: none; font-size: 14px;">← Back to Admin Dashboard</a>
            </div>
            
            <h2>Create New Team</h2>
            <form method="post">
                <input type="hidden" name="action" value="create_team">
                <?php if ( $current_team !== 'team' ) : ?>
                    <input type="hidden" name="team" value="<?php echo htmlspecialchars( $current_team ); ?>">
                <?php endif; ?>
                <div class="form-group">
                    <label for="new_team_name">Team Name *</label>
                    <input type="text" id="new_team_name" name="new_team_name" required placeholder="e.g., Marketing Team">
                </div>
                <div class="form-group">
                    <label for="new_team_slug">Team Slug *</label>
                    <input type="text" id="new_team_slug" name="new_team_slug" required placeholder="e.g., marketing-team" pattern="[a-z0-9_-]+" value="<?php echo $current_team !== 'team' ? htmlspecialchars( $current_team ) : ''; ?>">
                    <small style="color: #666; font-size: 12px;">Only lowercase letters, numbers, hyphens, and underscores allowed. This will be used as the filename.</small>
                </div>
                <div style="margin-top: 20px;">
                    <button type="submit" class="btn">Create Team</button>
                    <a href="<?php echo build_team_url( 'admin.php' ); ?>" class="btn" style="background: #6c757d; margin-left: 10px;">Cancel</a>
                </div>
            </form>
        <?php else : ?>

        <div class="nav-tabs">
            <a href="<?php echo build_team_url( 'admin.php', array( 'tab' => 'general' ) ); ?>" class="nav-tab <?php echo $active_tab === 'general' ? 'active' : ''; ?>">General Settings</a>
            <a href="<?php echo build_team_url( 'admin.php', array( 'tab' => 'members' ) ); ?>" class="nav-tab <?php echo $active_tab === 'members' ? 'active' : ''; ?>">Team Members (<?php echo count( $config['team_members'] ); ?>)</a>
            <a href="<?php echo build_team_url( 'admin.php', array( 'tab' => 'leadership' ) ); ?>" class="nav-tab <?php echo $active_tab === 'leadership' ? 'active' : ''; ?>">Leadership (<?php echo count( $config['leadership'] ); ?>)</a>
            <a href="<?php echo build_team_url( 'admin.php', array( 'tab' => 'alumni' ) ); ?>" class="nav-tab <?php echo $active_tab === 'alumni' ? 'active' : ''; ?>">Alumni (<?php echo count( $config['alumni'] ?? array() ); ?>)</a>
            <a href="<?php echo build_team_url( 'admin.php', array( 'tab' => 'events' ) ); ?>" class="nav-tab <?php echo $active_tab === 'events' ? 'active' : ''; ?>">Events (<?php echo count( $config['events'] ); ?>)</a>
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
                    <input type="text" id="team_name" name="team_name" value="<?php echo htmlspecialchars( $config['team_name'] ); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="activity_url_prefix">Activity URL Prefix</label>
                    <input type="url" id="activity_url_prefix" name="activity_url_prefix" value="<?php echo htmlspecialchars( $config['activity_url_prefix'] ); ?>">
                </div>
                
                <button type="submit" class="btn">Save General Settings</button>
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
                        <?php foreach ( $config['team_members'] as $username => $member ) : ?>
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
                    <a href="<?php echo build_team_url( 'admin.php', $back_params ); ?>" style="color: #666; text-decoration: none; font-size: 14px;">← Back to Team Members</a>
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
                        <?php foreach ( $config['leadership'] as $username => $leader ) : ?>
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
                    <a href="<?php echo build_team_url( 'admin.php', $back_params ); ?>" style="color: #666; text-decoration: none; font-size: 14px;">← Back to Leadership</a>
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
                        <?php foreach ( $config['alumni'] as $username => $alumni_member ) : ?>
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
                <p style="color: #666; font-style: italic; margin-top: 20px;">
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
                        <input type="text" id="event-name" name="event_name" value="<?php echo $is_editing_event ? htmlspecialchars( $edit_data['name'] ?? '' ) : ''; ?>" required>
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
                        <select id="event-type" name="event_type" style="width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px;">
                            <option value="team" <?php echo $is_editing_event && ($edit_data['type'] ?? '') === 'team' ? 'selected' : ''; ?>>Team</option>
                            <option value="company" <?php echo $is_editing_event && ($edit_data['type'] ?? '') === 'company' ? 'selected' : ''; ?>>Company</option>
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
                
                <button type="submit" class="btn" id="event-submit-btn"><?php echo $is_editing_event ? 'Update Event' : 'Add Event'; ?></button>
            </form>
        </div>

        <!-- JSON View Tab -->
        <div id="json" class="tab-content <?php echo $active_tab === 'json' ? 'active' : ''; ?>">
            <p>This is the current contents of your team.json file:</p>
            <pre style="background: #f8f8f8; padding: 20px; border-radius: 4px; overflow-x: auto;"><?php echo htmlspecialchars( json_encode( $config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre>
            
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
        
        // Initialize timezone autocomplete when page loads
        document.addEventListener('DOMContentLoaded', initTimezoneAutocomplete);
    </script>
</body>
</html>
