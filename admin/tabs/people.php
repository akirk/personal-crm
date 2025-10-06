<?php
/**
 * People Tab - Members, Leadership, Consultants, Alumni
 * Contains all person-related functions, POST handlers, and UI
 */
namespace PersonalCRM;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPINC' ) ) {
	exit;
}

/**
 * ==================================================
 * PERSON-RELATED HELPER FUNCTIONS
 * ==================================================
 */

/**
 * Render birthday dropdown fields (day, month, year)
 * Reusable helper to avoid 116 lines of duplication
 */
function render_birthday_dropdown( $field_prefix, $value, $privacy_mode ) {
	if ( $privacy_mode ) {
		?>
		<input type="hidden" name="<?php echo $field_prefix; ?>_day" value="">
		<input type="hidden" name="<?php echo $field_prefix; ?>_month" value="">
		<input type="hidden" name="<?php echo $field_prefix; ?>_year" value="">
		<p class="text-muted italic-text">Hidden in privacy mode</p>
		<?php
		return;
	}

	// Parse existing date
	$day = $month = $year = '';
	if ( ! empty( $value ) ) {
		if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches ) ) {
			$year = $matches[1];
			$month = $matches[2];
			$day = $matches[3];
		} elseif ( preg_match( '/^(\d{2})-(\d{2})$/', $value, $matches ) ) {
			$month = $matches[1];
			$day = $matches[2];
		}
		// Ensure 2 digits with leading zero
		if ( ! empty( $month ) && strlen( $month ) === 1 ) {
			$month = '0' . $month;
		}
		if ( ! empty( $day ) && strlen( $day ) === 1 ) {
			$day = '0' . $day;
		}
	}
	?>
	<div style="display: flex; gap: 10px; align-items: center;">
		<select name="<?php echo $field_prefix; ?>_day" class="form-select">
			<option value="">Day</option>
			<?php for ( $d = 1; $d <= 31; $d++ ) : ?>
				<option value="<?php echo sprintf( '%02d', $d ); ?>" <?php echo (string) $day === sprintf( '%02d', $d ) ? 'selected' : ''; ?>>
					<?php echo $d; ?>
				</option>
			<?php endfor; ?>
		</select>

		<select name="<?php echo $field_prefix; ?>_month" class="form-select">
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

		<select name="<?php echo $field_prefix; ?>_year" class="form-select">
			<option value="">Year (optional)</option>
			<?php for ( $y = 1900; $y <= date( 'Y' ); $y++ ) : ?>
				<option value="<?php echo $y; ?>" <?php echo $year === (string) $y ? 'selected' : ''; ?>>
					<?php echo $y; ?>
				</option>
			<?php endfor; ?>
		</select>
	</div>
	<small class="form-helper-text">
		Year is optional - leave empty if unknown
	</small>
	<?php
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
	global $group, $current_group;
	$crm = PersonalCrm::get_instance();

	$configs = array(
		'member' => array(
			'section_key' => 'team_members',
			'form_prefix' => '',
			'form_id' => 'member-form',
			'edit_action' => 'edit_member',
			'add_action' => 'add_member',
			'delete_action' => 'delete_member',
			'edit_text' => 'Update ' . $crm->get_type_display_word( $current_group ) . ' Member',
			'add_text' => 'Add ' . $crm->get_type_display_word( $current_group ) . ' Member',
			'display_name' => $crm->get_type_display_word( $current_group ) . ' Member',
			'show_hr_feedback' => true,
			'show_alumni_actions' => ! $crm->is_social_group( $current_group ),
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
			'show_alumni_actions' => ! $crm->is_social_group( $current_group ),
		),
		'consultants' => array(
			'section_key' => 'consultants',
			'form_prefix' => 'consultant-',
			'form_id' => 'consultant-form',
			'edit_action' => 'edit_consultants',
			'add_action' => 'add_consultants',
			'delete_action' => 'delete_consultant',
			'edit_text' => 'Update Consultant',
			'add_text' => 'Add Consultant',
			'display_name' => 'Consultant',
			'show_hr_feedback' => false,
			'show_alumni_actions' => false,
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
 * Generate username from name for social groups
 */
function generate_username_from_name( $name ) {
	if ( empty( $name ) ) {
		return null;
	}

	// Convert to lowercase, remove special characters, replace spaces with hyphens
	$username = strtolower( $name );
	$username = iconv( 'UTF-8', 'ASCII//TRANSLIT', $username ); // Remove accents
	$username = preg_replace( '/[^a-z0-9\s]/', '', $username );
	$username = preg_replace( '/\s+/', '-', trim( $username ) );

	return $username;
}

/**
 * Create person data array from form input
 */
function create_person_data_from_form() {
	global $current_group, $crm;
	$is_social_group = $crm->is_social_group( $current_group );

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

	// Construct partner birthday from dropdowns
	$partner_birthday = '';
	$partner_day = sanitize_text_field( $_POST['partner_birthday_day'] ?? '' );
	$partner_month = sanitize_text_field( $_POST['partner_birthday_month'] ?? '' );
	$partner_year = sanitize_text_field( $_POST['partner_birthday_year'] ?? '' );

	if ( ! empty( $partner_day ) && ! empty( $partner_month ) ) {
		if ( ! empty( $partner_year ) ) {
			$partner_birthday = $partner_year . '-' . $partner_month . '-' . $partner_day;
		} else {
			$partner_birthday = $partner_month . '-' . $partner_day; // Legacy format for year-unknown
		}
	}

	// Handle username with auto-generation for social groups
	$username = sanitize_text_field( $_POST['username'] ?? '' );
	if ( $is_social_group && empty( $username ) && ! empty( $_POST['name'] ) ) {
		$username = generate_username_from_name( $_POST['name'] );
	}

	$data = array(
		'name' => sanitize_text_field( $_POST['name'] ?? '' ),
		'nickname' => sanitize_text_field( $_POST['nickname'] ?? '' ),
		'username' => $username,
		'github' => sanitize_text_field( $_POST['github'] ?? '' ),
		'linear' => sanitize_text_field( $_POST['linear'] ?? '' ),
		'wordpress' => sanitize_text_field( $_POST['wordpress'] ?? '' ),
		'linkedin' => sanitize_text_field( $_POST['linkedin'] ?? '' ),
		'website' => sanitize_text_field( $_POST['website'] ?? '' ),
		'email' => filter_var( $_POST['email'] ?? '', FILTER_SANITIZE_EMAIL ),
		'location' => sanitize_text_field( $_POST['location'] ?? '' ),
		'timezone' => sanitize_text_field( $_POST['timezone'] ?? '' ),
		'links' => $links,
		'birthday' => $birthday,
		'partner' => sanitize_text_field( $_POST['partner'] ?? '' ),
		'partner_birthday' => $partner_birthday,
		'kids' => parse_kids_data( $_POST['kids'] ?? '' ),
		'notes' => create_notes_from_form(),
		'personal_events' => parse_personal_events_data( $_POST['personal_events'] ?? array() ),
		'deceased' => !empty( $_POST['deceased'] ) ? 1 : 0,
		'deceased_date' => sanitize_text_field( $_POST['deceased_date'] ?? '' ),
	);

	// Only add business-specific fields for teams (not social groups)
	if ( ! $is_social_group ) {
		$data['role'] = sanitize_text_field( $_POST['role'] ?? '' );
		$data['company_anniversary'] = sanitize_text_field( $_POST['company_anniversary'] ?? '' );
		$data['github_repos'] = isset( $_POST['github_repos'] ) ? array_filter( array_map( 'sanitize_text_field', $_POST['github_repos'] ) ) : array();
	} else {
		// For social groups, ensure these fields are empty arrays/strings
		$data['role'] = '';
		$data['company_anniversary'] = '';
		$data['github_repos'] = array();
	}

	// Add alumni-specific fields if present
	if ( isset( $_POST['left_company'] ) ) {
		$data['left_company'] = !empty( $_POST['left_company'] ) ? 1 : 0;
	}
	if ( isset( $_POST['new_company'] ) ) {
		$data['new_company'] = sanitize_text_field( $_POST['new_company'] ?? '' );
	}
	if ( isset( $_POST['new_company_website'] ) ) {
		$data['new_company_website'] = sanitize_text_field( $_POST['new_company_website'] ?? '' );
	}

	return $data;
}

/**
 * Create notes array from form input
 */
function create_notes_from_form() {
	global $config_file;
	$notes = array();

	// Get existing notes if we're editing
	if ( isset( $_POST['action'] ) && in_array( $_POST['action'], array( 'edit_member', 'edit_leadership', 'edit_consultants', 'edit_alumni' ) ) && isset( $_POST['username'] ) ) {
		global $current_group;
		$crm = PersonalCrm::get_instance();
		$team_data = $crm->storage->get_group( $current_group );
		$username = sanitize_text_field( $_POST['username'] );

		// Check all person types for existing data
		foreach ( array( 'team_members', 'leadership', 'consultants', 'alumni' ) as $type ) {
			if ( isset( $team_data[$type][$username] ) ) {
				$existing_data = $team_data[$type][$username];
				if ( ! empty( $existing_data['notes'] ) && is_array( $existing_data['notes'] ) ) {
					$notes = $existing_data['notes'];
				}
				break;
			}
		}
	}

	// Add new note if provided
	$new_note = sanitize_textarea_field( $_POST['new_note'] ?? '' );
	if ( ! empty( $new_note ) ) {
		$notes[] = array(
			'date' => date( 'Y-m-d H:i' ),
			'text' => $new_note
		);
	}

	return $notes;
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

		// Format: "Name YYYY-MM-DD", "Name MM-DD", "Name YYYY", or just "Name"
		if ( preg_match( '/^(.+?)\s+(\d{4}-\d{2}-\d{2})\s*$/', $line, $matches ) ) {
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
		} elseif ( preg_match( '/^(.+?)\s+(\d{2}-\d{2})\s*$/', $line, $matches ) ) {
			// Month-day format (MM-DD)
			$kids[] = array(
				'name' => trim( $matches[1] ),
				'birth_year' => '',
				'birthday' => $matches[2] // Store as MM-DD
			);
		} elseif ( preg_match( '/^(.+?)\s+(\d{4})\s*$/', $line, $matches ) ) {
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
 * Get all available timezone options
 */
function get_timezone_options() {
	$timezones = array();
	$timezone_identifiers = \DateTimeZone::listIdentifiers();

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
			$lines[] = $kid['name'] . ' ' . $kid['birthday'];
		} elseif ( ! empty( $kid['birth_year'] ) ) {
			$lines[] = $kid['name'] . ' ' . $kid['birth_year'];
		} else {
			$lines[] = $kid['name'];
		}
	}

	return implode( "\n", $lines );
}

function get_person_form_value( $field_name, $edit_data = array(), $is_editing = false, $error = '' ) {
	$privacy_mode = isset( $_GET['privacy'] ) && $_GET['privacy'] === '1';
	$crm = PersonalCrm::get_instance();


	// Use global error if no error parameter provided
	if ( empty( $error ) ) {
		global $error;
	}

	// If there's an error, prioritize POST data
	if ( ! empty( $error ) && isset( $_POST[ $field_name ] ) ) {
		$value = sanitize_text_field( $_POST[ $field_name ] );
	} elseif ( $is_editing && isset( $edit_data[ $field_name ] ) ) {
		$value = $edit_data[ $field_name ];
	} else {
		$value = '';
	}

	// Handle special cases
	if ( $field_name === 'name' && $privacy_mode && ! empty( $value ) ) {
		$value = $crm->mask_name( $value, true );
	} elseif ( $field_name === 'location' && empty( $value ) && $is_editing ) {
		// Fallback to 'town' field for backward compatibility
		$value = $edit_data['town'] ?? '';
	} elseif ( $field_name === 'company_anniversary' && $privacy_mode ) {
		$value = mask_date_input( $value, $privacy_mode );
	}

	return htmlspecialchars( $value );
}

function get_person_checkbox_checked( $field_name, $edit_data = array(), $is_editing = false, $error = '' ) {
	// Use global error if no error parameter provided
	if ( empty( $error ) ) {
		global $error;
	}

	// If there's an error, prioritize POST data
	if ( ! empty( $error ) ) {
		return isset( $_POST[ $field_name ] ) ? 'checked' : '';
	} elseif ( $is_editing && ! empty( $edit_data[ $field_name ] ) ) {
		return 'checked';
	}
	return '';
}

/**
 * Unified person CRUD operations
 */
function handle_person_action( $action, $config, $person_data ) {
	global $config_file, $current_group;
	$crm = PersonalCrm::get_instance();

	$main_config = $crm->storage->get_group( $current_group );

	if ( strpos( $action, 'edit_' ) === 0 ) {
		$username = $person_data['username'] ?? '';
		$original_username = sanitize_text_field( $_POST['original_username'] ?? '' );

		if ( empty( $username ) ) {
			return array( 'error' => 'Username is required.' );
		}

		// If username changed, delete old entry first
		if ( $original_username !== $username && ! empty( $original_username ) ) {
			global $wpdb;
			$wpdb->delete(
				$wpdb->prefix . 'personal_crm_people',
				array( 'username' => $original_username, 'team_slug' => $current_group ),
				array( '%s', '%s' )
			);
		}

		// Save the person directly without loading entire group
		$result = $crm->storage->save_person( $current_group, $username, $config['section_key'], $person_data );

		if ( $result !== false ) {
			// Allow plugins to save additional data
			do_action( 'personal_crm_admin_person_save', $username, $_POST, $config['section_key'] );

			// Redirect to person view using proper route pattern {team}/{person}
			$redirect_url = '/crm/' . sanitize_key( $current_group ) . '/' . sanitize_key( $username );
			header( 'Location: ' . $redirect_url );
			exit;
		} else {
			return array( 'error' => 'Failed to update ' . strtolower( $config['display_name'] ) . '.' );
		}
	} elseif ( strpos( $action, 'add_' ) === 0 ) {
		$username = $person_data['username'] ?? '';

		if ( empty( $username ) ) {
			return array( 'error' => 'Username is required.' );
		}

		// Save the person directly without loading entire group
		$result = $crm->storage->save_person( $current_group, $username, $config['section_key'], $person_data );

		if ( $result !== false ) {
			// Allow plugins to save additional data
			do_action( 'personal_crm_admin_person_save', $username, $_POST, $config['section_key'] );

			// Redirect back to the appropriate admin section after adding
			$section = '';
			if ( $action === 'add_member' ) {
				$section = 'members';
			} elseif ( $action === 'add_leadership' ) {
				$section = 'leadership';
			} elseif ( $action === 'add_consultants' ) {
				$section = 'consultants';
			}
			$redirect_url = '/crm/admin/' . $current_group . '/' . $section . '/';
			header( 'Location: ' . $redirect_url );
			exit;
		} else {
			return array( 'error' => 'Failed to save ' . strtolower( $config['display_name'] ) . '.' );
		}
	} elseif ( strpos( $action, 'delete_' ) === 0 ) {
		$username = $_POST['username'] ?? '';
		if ( isset( $main_config[ $config['section_key'] ][ $username ] ) ) {
			unset( $main_config[ $config['section_key'] ][ $username ] );
			if ( $crm->storage->save_group( $current_group, $main_config ) ) {
				return array( 'message' => $config['display_name'] . ' deleted successfully!' );
			} else {
				return array( 'error' => 'Failed to delete ' . strtolower( $config['display_name'] ) . '.' );
			}
		}
	}

	return array();
}

/**
 * ==================================================
 * PERSON-RELATED POST HANDLERS
 * ==================================================
 */

// Handle POST requests for person operations
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['action'] ) ) {
	$action = $_POST['action'];

	switch ( $action ) {
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

		case 'edit_consultants':
		case 'add_consultants':
			$consultant_config = get_person_type_config( 'consultants' );
			$person_data = create_person_data_from_form();
			$result = handle_person_action( $action, $consultant_config, $person_data );
			if ( isset( $result['error'] ) ) {
				$error = $result['error'];
			} elseif ( isset( $result['message'] ) ) {
				$message = $result['message'];
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

		case 'delete_member':
			$member_config = get_person_type_config( 'member' );
			$result = handle_person_action( $action, $member_config, array() );
			if ( isset( $result['error'] ) ) {
				$error = $result['error'];
			} elseif ( isset( $result['message'] ) ) {
				$message = $result['message'];
			}
			break;

		case 'delete_consultant':
			$consultant_config = get_person_type_config( 'consultants' );
			$result = handle_person_action( $action, $consultant_config, array() );
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

			if ( $crm->storage->save_group( $current_group, $config ) ) {
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

			if ( $crm->storage->save_group( $current_group, $config ) ) {
				$message = 'Person restored from alumni successfully!';
			} else {
				$error = 'Failed to restore person from alumni.';
			}
			break;

		case 'move_to_team':
			$username = $_POST['username'] ?? '';
			$from_section = $_POST['from_section'] ?? '';
			$target_team = $_POST['target_team'] ?? '';
			$delete_if_empty = isset( $_POST['delete_if_empty'] );

			if ( empty( $target_team ) || $target_team === $current_group ) {
				$error = 'Please select a different team to move to.';
				break;
			}

			// Get person data from current team
			$person_data = null;
			if ( $from_section === 'team_members' && isset( $config['team_members'][ $username ] ) ) {
				$person_data = $config['team_members'][ $username ];
				unset( $config['team_members'][ $username ] );
			} elseif ( $from_section === 'leadership' && isset( $config['leadership'][ $username ] ) ) {
				$person_data = $config['leadership'][ $username ];
				unset( $config['leadership'][ $username ] );
			}

			if ( $person_data ) {
				// Load target team config
				$target_config = $crm->storage->get_group( $target_team );

				// Add person to target team (default to team_members)
				$target_config['team_members'][ $username ] = $person_data;

				// Check if current team will be empty (excluding alumni)
				$has_members = ! empty( $config['team_members'] ) || ! empty( $config['leadership'] );

				// Save both configs
				$current_saved = $crm->storage->save_group( $current_group, $config );
				$target_saved = $crm->storage->save_group( $target_team, $target_config );

				if ( $current_saved && $target_saved ) {
					// Delete current team if requested and it's empty
					if ( $delete_if_empty && ! $has_members && $current_group !== 'team' ) {
						global $config_file;
						if ( unlink( $config_file ) ) {
							// Redirect to person in new team after deleting current team
							$redirect_url = $crm->build_url( 'index.php', array( 'team' => $target_team, 'person' => $username ) );
							header( 'Location: ' . $redirect_url );
							exit;
						} else {
							$message = "Person moved successfully but could not delete empty team file.";
						}
					} else {
						// Redirect to person in new team
						$redirect_url = $crm->build_url( 'index.php', array( 'team' => $target_team, 'person' => $username ) );
						header( 'Location: ' . $redirect_url );
						exit;
					}
				} else {
					$error = 'Failed to move person to target team.';
				}
			} else {
				$error = 'Person not found in current team.';
			}
			break;
	}
}

/**
 * ==================================================
 * PERSON FORM RENDERER
 * ==================================================
 */

/**
 * Render a person form (team member, leader, or alumni)
 */
function render_person_form( $type, $edit_data = null, $is_editing = false ) {
	global $group, $current_group;

	$crm = PersonalCrm::get_instance();
	$privacy_mode = isset( $_GET['privacy'] ) && $_GET['privacy'] === '1';

	$config = get_person_type_config( $type );
	if ( ! $config ) {
		return;
	}

	if ( ! $is_editing && $config['add_action'] === null ) {
		return;
	}

	$form_id = $config['form_id'];
	$action = $is_editing ? $config['edit_action'] : $config['add_action'];
	$submit_text = 'Save';
	$prefix = $config['form_prefix'];
	$show_alumni_actions = $config['show_alumni_actions'];

?>
<form method="post" action="" id="<?php echo $form_id; ?>">
	<input type="hidden" name="action" value="<?php echo $action; ?>">
	<input type="hidden" name="original_username" value="<?php echo $is_editing ? htmlspecialchars( $edit_data['username'] ?? '' ) : ''; ?>">
	<input type="hidden" name="group" value="<?php echo htmlspecialchars( $current_group ?? '' ); ?>">
	<button type="submit" class="btn btn-primary" style="float: right; margin-top: -3em"><?php echo $submit_text; ?></button>

	<!-- Personal Information -->
	<h4 class="section-heading">Personal Information</h4>
	<div class="form-grid">
		<div class="form-group">
			<label for="<?php echo $prefix; ?>name">Full Name<?php echo $privacy_mode ? ' (Privacy Mode - Last name will be masked)' : ''; ?></label>
			<input type="text" id="<?php echo $prefix; ?>name" name="name" value="<?php echo get_person_form_value( 'name', $edit_data, $is_editing ); ?>"<?php echo $privacy_mode ? ' placeholder="First name visible only"' : ''; ?> autofocus>
		</div>

		<div class="form-group">
			<label for="<?php echo $prefix; ?>nickname">Nickname <small class="optional-label">(optional)</small></label>
			<input type="text" id="<?php echo $prefix; ?>nickname" name="nickname" value="<?php echo get_person_form_value( 'nickname', $edit_data, $is_editing ); ?>" placeholder="e.g., Mike, Lizzy, DJ">
		</div>

		<div class="form-group">
			<label for="<?php echo $prefix; ?>location">Location</label>
			<input type="text" id="<?php echo $prefix; ?>location" name="location" value="<?php echo get_person_form_value( 'location', $edit_data, $is_editing ); ?>">
		</div>

		<div class="form-group">
			<label for="<?php echo $prefix; ?>timezone">Timezone</label>
			<div class="timezone-input">
				<input type="text"
					   id="<?php echo $prefix; ?>timezone"
					   name="timezone"
					   value="<?php echo get_person_form_value( 'timezone', $edit_data, $is_editing ); ?>"
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
			<?php render_birthday_dropdown( 'birthday', $edit_data['birthday'] ?? '', $privacy_mode ); ?>
		</div>
	</div>

	<!-- Family Information -->
	<details style="margin: 20px 0;">
		<summary class="summary-toggle">Family Information</summary>

		<div class="form-grid">
			<div class="form-group">
				<label for="<?php echo $prefix; ?>partner">Partner</label>
				<input type="text" id="<?php echo $prefix; ?>partner" name="partner" value="<?php echo get_person_form_value( 'partner', $edit_data, $is_editing ); ?>">
			</div>

			<div class="form-group">
				<label>Partner Birthday<?php echo $privacy_mode ? ' (Hidden in Privacy Mode)' : ''; ?></label>
				<?php render_birthday_dropdown( 'partner_birthday', $edit_data['partner_birthday'] ?? '', $privacy_mode ); ?>
			</div>
		</div>

		<div class="form-group">
			<label for="<?php echo $prefix; ?>kids">Kids (one per line, formats: "Name YYYY-MM-DD", "Name MM-DD", "Name YYYY" or just "Name")</label>
			<textarea id="<?php echo $prefix; ?>kids" name="kids" rows="4" placeholder="Emma 2010-03-15&#10;Jake 12-25&#10;Sam 2012&#10;Alex"><?php echo $is_editing ? htmlspecialchars( format_kids_for_form( $edit_data['kids'] ?? array() ) ) : ''; ?></textarea>
		</div>
	</details>

	<!-- Company Information -->
	<h4 class="section-heading">Company Information</h4>
	<div class="form-grid">
		<div class="form-group">
			<label for="<?php echo $prefix; ?>username">Username *<?php if ( $crm->is_social_group( $current_group ) ) echo ' (auto-generated if empty)'; ?></label>
			<input type="text" id="<?php echo $prefix; ?>username" name="username" value="<?php echo get_person_form_value( 'username', $edit_data, $is_editing ); ?>" <?php echo $crm->is_social_group( $current_group ) ? '' : 'required'; ?>>
			<?php if ( $crm->is_social_group( $current_group ) ) : ?>
				<small style="color: #666;">Leave empty to auto-generate from name (e.g., "John Smith" → "john.smith")</small>
			<?php endif; ?>
		</div>

		<?php if ( ! $crm->is_social_group( $current_group ) ) : ?>
		<div class="form-group">
			<label for="<?php echo $prefix; ?>role">Role</label>
			<input type="text" id="<?php echo $prefix; ?>role" name="role" value="<?php echo get_person_form_value( 'role', $edit_data, $is_editing ); ?>" placeholder="e.g., Developer, Lead, HR">
		</div>
		<?php endif; ?>

		<?php if ( ! $crm->is_social_group( $current_group ) ) : ?>
		<div class="form-group">
			<label for="<?php echo $prefix; ?>company_anniversary">Company Anniversary<?php echo $privacy_mode ? ' (Hidden in Privacy Mode)' : ''; ?></label>
			<input type="date" id="<?php echo $prefix; ?>company_anniversary" name="company_anniversary" value="<?php echo get_person_form_value( 'company_anniversary', $edit_data, $is_editing ); ?>"<?php echo $privacy_mode ? ' placeholder="Hidden for privacy"' : ''; ?>>
		</div>
		<?php endif; ?>


		<!-- Links Section -->
		<div class="form-group" style="grid-column: 1 / -1;">
			<label>Links</label>
			<div id="<?php echo $prefix; ?>links-container">
				<?php
				$current_links = array();
				if ( $is_editing && isset( $edit_data['links'] ) ) {
					$current_links = $edit_data['links'];
				}

				if ( ! $crm->is_social_group( $current_group ) && ! isset( $current_links['1:1 doc'] ) ) {
					$current_links['1:1 doc'] = '';
				}

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
	<h4 class="section-heading">Online Profiles</h4>
	<div class="form-grid">
		<div class="form-group">
			<label for="<?php echo $prefix; ?>github">GitHub Username</label>
			<input type="text" id="<?php echo $prefix; ?>github" name="github" value="<?php echo get_person_form_value( 'github', $edit_data, $is_editing ); ?>">
		</div>

		<div class="form-group">
			<label for="<?php echo $prefix; ?>linear">Linear Username</label>
			<input type="text" id="<?php echo $prefix; ?>linear" name="linear" value="<?php echo get_person_form_value( 'linear', $edit_data, $is_editing ); ?>">
		</div>

		<div class="form-group">
			<label for="<?php echo $prefix; ?>wordpress">WordPress.org Username</label>
			<input type="text" id="<?php echo $prefix; ?>wordpress" name="wordpress" value="<?php echo get_person_form_value( 'wordpress', $edit_data, $is_editing ); ?>">
		</div>

		<div class="form-group">
			<label for="<?php echo $prefix; ?>linkedin">LinkedIn Username</label>
			<input type="text" id="<?php echo $prefix; ?>linkedin" name="linkedin" value="<?php echo get_person_form_value( 'linkedin', $edit_data, $is_editing ); ?>">
		</div>

		<div class="form-group">
			<label for="<?php echo $prefix; ?>website">Website URL</label>
			<input type="text" id="<?php echo $prefix; ?>website" name="website" value="<?php echo get_person_form_value( 'website', $edit_data, $is_editing ); ?>">
		</div>

		<div class="form-group">
			<label for="<?php echo $prefix; ?>email">Email Address</label>
			<input type="email" id="<?php echo $prefix; ?>email" name="email" value="<?php echo get_person_form_value( 'email', $edit_data, $is_editing ); ?>">
		</div>
	</div>

	<?php if ( ! $crm->is_social_group( $current_group ) ) : ?>
	<!-- GitHub Repositories -->
	<div class="form-group">
		<label>GitHub Repositories</label>
		<div style="margin: 5px 0 10px 0;">
			<button type="button" onclick="addRepoField('<?php echo $prefix; ?>')" class="btn-add-repo">+ Add Repository</button>
		</div>

		<div id="<?php echo $prefix; ?>repo_fields">
			<?php
			$existing_repos = array();
			if ( $is_editing && ! empty( $edit_data['github_repos'] ) ) {
				$existing_repos = is_array( $edit_data['github_repos'] ) ? $edit_data['github_repos'] : array_filter( array_map( 'trim', explode( ',', $edit_data['github_repos'] ) ) );
			}

			if ( empty( $existing_repos ) ) {
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
		global $config_file;
		$team_config = $crm->storage->get_group( $current_group );
		$all_repos = array();
		$all_people = array_merge( $team_config['team_members'] ?? array(), $team_config['leadership'] ?? array(), $team_config['alumni'] ?? array() );
		foreach ( $all_people as $person ) {
			if ( ! empty( $person['github_repos'] ) ) {
				$person_repos = is_array( $person['github_repos'] ) ? $person['github_repos'] : array_filter( array_map( 'trim', explode( ',', $person['github_repos'] ?? '' ) ) );
				$all_repos = array_merge( $all_repos, $person_repos );
			}
		}
		$all_repos = array_unique( array_filter( $all_repos ) );

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
	<?php endif; ?>

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

			if (container.children.length === 0) {
				addRepoField('<?php echo $prefix; ?>');
			}
		}

		function addRepoToField(prefix, repo) {
			const container = document.getElementById(prefix + 'repo_fields');
			const inputs = container.querySelectorAll('input[name="github_repos[]"]');

			for (let input of inputs) {
				if (input.value.trim() === repo) {
					return;
				}
			}

			for (let input of inputs) {
				if (input.value.trim() === '') {
					input.value = repo;
					return;
				}
			}

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
		var personalEventIndex = <?php echo $is_editing && ! empty( $personal_events ) ? count( $personal_events ) : 0; ?>;

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

	<?php if ( $type === 'alumni' ) : ?>
	<!-- Alumni-specific fields -->
	<h4 class="section-heading">Alumni Information</h4>
	<div class="form-grid">
		<div class="form-group" style="grid-column: 1 / -1;">
			<label style="display: flex; align-items: center; gap: 8px; margin-bottom: 5px; font-weight: 600;">
				<input type="checkbox" id="<?php echo $prefix; ?>left_company" name="left_company" value="1" <?php echo get_person_checkbox_checked( 'left_company', $edit_data, $is_editing ); ?> style="width: auto;">
				<span>Has left the company</span>
			</label>
			<small class="text-small-muted" style="margin-left: 20px;">
				Check if this person is no longer with the company
			</small>
		</div>

		<div class="form-group">
			<label for="<?php echo $prefix; ?>new_company">New Company</label>
			<input type="text" id="<?php echo $prefix; ?>new_company" name="new_company" value="<?php echo get_person_form_value( 'new_company', $edit_data, $is_editing ); ?>" placeholder="e.g., Google, Microsoft, etc.">
		</div>

		<div class="form-group">
			<label for="<?php echo $prefix; ?>new_company_website">New Company Website</label>
			<input type="url" id="<?php echo $prefix; ?>new_company_website" name="new_company_website" value="<?php echo get_person_form_value( 'new_company_website', $edit_data, $is_editing ); ?>" placeholder="https://example.com">
		</div>
	</div>
	<?php endif; ?>

	<!-- Deceased Status -->
	<div class="form-group">
		<label style="display: flex; align-items: center; gap: 8px; margin-bottom: 5px; font-weight: 400; color: #666;">
			<input type="checkbox" id="<?php echo $prefix; ?>deceased" name="deceased" value="1" <?php echo get_person_checkbox_checked( 'deceased', $edit_data, $is_editing ); ?> style="width: auto;" onchange="toggleDeceasedDate()">
			<span>Mark as deceased</span>
		</label>
		<small class="text-small-muted" style="margin-left: 20px; color: #888;">
			This will remove them from birthday reminders
		</small>

		<div id="deceased-date-container" style="margin-top: 10px; margin-left: 20px; <?php
			global $error;
			echo (!empty($error) && isset($_POST['deceased'])) || ($is_editing && !empty($edit_data['deceased'])) ? '' : 'display: none;';
		?>">
			<label for="<?php echo $prefix; ?>deceased_date">Date of passing:</label>
			<input type="date" id="<?php echo $prefix; ?>deceased_date" name="deceased_date" value="<?php echo get_person_form_value( 'deceased_date', $edit_data, $is_editing ); ?>">
			<small class="text-small-muted" style="display: block; margin-top: 5px;">
				Optional - leave empty if unknown
			</small>
		</div>
	</div>

	<script>
	function toggleDeceasedDate() {
		const checkbox = document.getElementById('<?php echo $prefix; ?>deceased');
		const container = document.getElementById('deceased-date-container');
		const dateInput = document.getElementById('<?php echo $prefix; ?>deceased_date');

		if (checkbox.checked) {
			container.style.display = 'block';
		} else {
			container.style.display = 'none';
			dateInput.value = '';
		}
	}
	</script>

	<div class="form-group">
		<label>Notes</label>

		<?php if ( $is_editing && ! empty( $edit_data['notes'] ) && is_array( $edit_data['notes'] ) ) : ?>
			<div class="existing-notes">
				<?php foreach ( $edit_data['notes'] as $note ) : ?>
					<div class="note">
						<small class="note-date"><?php echo htmlspecialchars( $note['date'] ); ?></small>
						<p class="note-text"><?php echo nl2br( htmlspecialchars( $note['text'] ) ); ?></p>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<label for="<?php echo $prefix; ?>new_note">Add new note</label>
		<textarea id="<?php echo $prefix; ?>new_note" name="new_note" placeholder="Add what you learned today..."></textarea>
	</div>

	<?php
	do_action( 'personal_crm_admin_person_form_fields', $edit_data, $is_editing, $prefix );
	?>

	<button type="submit" class="btn"><?php echo $submit_text; ?></button>
</form>

<?php if ( $is_editing && $show_alumni_actions ) : ?>
	<div class="divider-section">
		<h4 class="text-muted" style="margin-bottom: 10px;">Alumni Actions</h4>
		<form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to move this person to alumni? They will be removed from active team lists.')">
			<input type="hidden" name="action" value="move_to_alumni">
			<input type="hidden" name="username" value="<?php echo htmlspecialchars( $edit_data['username'] ?? '' ); ?>">
			<input type="hidden" name="from_section" value="<?php echo $config['section_key']; ?>">
			<input type="hidden" name="group" value="<?php echo htmlspecialchars( $current_group ); ?>">
			<button type="submit" class="btn btn-warning">Move to Alumni</button>
		</form>
	</div>
<?php endif; ?>
<?php
}

/**
 * ==================================================
 * PERSON LIST RENDERER (HTML)
 * ==================================================
 */
?>
        <!-- Team Members Tab -->
        <div id="members" class="tab-content <?php echo $active_tab === 'members' ? 'active' : ''; ?>">
            <?php if ( $is_editing_member ) : ?>
                <h2>Edit <?php echo ucfirst( $group ); ?> Member: <?php echo htmlspecialchars( $crm->mask_name( $edit_data['name'] ?? $edit_data['username'], $privacy_mode ) ); ?></h2>
            <?php elseif ( isset( $_GET['add'] ) && $_GET['add'] === 'new' ) : ?>
            <?php else : ?>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3 style="margin: 0;">Current <?php echo ucfirst( $group ); ?> Members</h3>
                    <?php
                    $add_params = array( 'tab' => 'members', 'add' => 'new' );
                    if ( $privacy_mode ) $add_params['privacy'] = '1';
                    ?>
                    <a href="/crm/admin/<?php echo $current_group; ?>/members/?add=new" class="btn">+ Add New <?php echo ucfirst( $group ); ?> Member</a>
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
                                    <h4><?php echo htmlspecialchars( $crm->mask_name( $member['name'], $privacy_mode ) ); ?></h4>
                                    <small>@<?php echo htmlspecialchars( $crm->mask_username( $username, $privacy_mode ) ); ?> • <?php echo htmlspecialchars( $member['location'] ?? $member['town'] ?? '' ); ?></small>
                                </div>
                                <div style="display: flex; gap: 8px;">
                                    <?php
                                    $edit_params = array( 'edit_member' => $username );
                                    if ( $privacy_mode ) $edit_params['privacy'] = '1';
                                    ?>
                                    <a href="/crm/admin/<?php echo $current_group; ?>/person/<?php echo $username; ?>/" class="btn">Edit</a>
                                    <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this team member?')">
                                        <input type="hidden" name="action" value="delete_member">
                                        <input type="hidden" name="username" value="<?php echo htmlspecialchars( $username ); ?>">
                                        <input type="hidden" name="group" value="<?php echo htmlspecialchars( $current_group ); ?>">
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
                    <a href="/crm/admin/<?php echo $current_group; ?>/members/" class="back-link-admin">← Back to <?php echo ucfirst( $group ); ?> Members</a>
                </div>
                <h3>Add New <?php echo ucfirst( $group ); ?> Member</h3>
                <?php render_person_form( 'member', null, false ); ?>
            <?php endif; ?>
        </div>

        <!-- Leadership Tab -->
        <div id="leadership" class="tab-content <?php echo $active_tab === 'leadership' ? 'active' : ''; ?>">
            <?php if ( $is_editing_leader ) : ?>
                <h2>Edit Leader: <?php echo htmlspecialchars( $crm->mask_name( $edit_data['name'] ?? $edit_data['username'], $privacy_mode ) ); ?></h2>
            <?php elseif ( isset( $_GET['add'] ) && $_GET['add'] === 'new' ) : ?>
            <?php else : ?>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3 style="margin: 0;">Leadership</h3>
                    <?php
                    $add_params = array( 'tab' => 'leadership', 'add' => 'new' );
                    if ( $privacy_mode ) $add_params['privacy'] = '1';
                    ?>
                    <a href="/crm/admin/<?php echo $current_group; ?>/leadership/?add=new" class="btn">+ Add Leader</a>
                </div>
                <?php if ( ! empty( $config['leadership'] ) ) : ?>
                    <div class="person-list">
                        <?php
                        $sorted_leaders = $config['leadership'];
                        uasort( $sorted_leaders, function( $a, $b ) {
                            return strcasecmp( $a['name'], $b['name'] );
                        } );

                        foreach ( $sorted_leaders as $username => $leader ) : ?>
                            <div class="person-item">
                                <div class="person-info">
                                    <h4><?php echo htmlspecialchars( $crm->mask_name( $leader['name'], $privacy_mode ) ); ?></h4>
                                    <small>@<?php echo htmlspecialchars( $crm->mask_username( $username, $privacy_mode ) ); ?> • <?php echo htmlspecialchars( $leader['location'] ?? $leader['town'] ?? '' ); ?></small>
                                </div>
                                <div style="display: flex; gap: 8px;">
                                    <a href="/crm/admin/<?php echo $current_group; ?>/person/<?php echo $username; ?>/" class="btn">Edit</a>
                                    <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this leader?')">
                                        <input type="hidden" name="action" value="delete_leader">
                                        <input type="hidden" name="username" value="<?php echo htmlspecialchars( $username ); ?>">
                                        <input type="hidden" name="group" value="<?php echo htmlspecialchars( $current_group ); ?>">
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
                    <a href="/crm/admin/<?php echo $current_group; ?>/leadership/" class="back-link-admin">← Back to Leadership</a>
                </div>
                <h3>Add New Leader</h3>
                <?php render_person_form( 'leader', null, false ); ?>
            <?php endif; ?>
        </div>

        <!-- Consultants Tab -->
        <div id="consultants" class="tab-content <?php echo $active_tab === 'consultants' ? 'active' : ''; ?>">
            <?php if ( $is_editing_consultant ) : ?>
                <h2>Edit Consultant: <?php echo htmlspecialchars( $crm->mask_name( $edit_data['name'] ?? $edit_data['username'], $privacy_mode ) ); ?></h2>
            <?php elseif ( isset( $_GET['add'] ) && $_GET['add'] === 'new' ) : ?>
            <?php else : ?>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3 style="margin: 0;">Consultants</h3>
                    <?php
                    $add_params = array( 'tab' => 'consultants', 'add' => 'new' );
                    if ( $privacy_mode ) $add_params['privacy'] = '1';
                    ?>
                    <a href="/crm/admin/<?php echo $current_group; ?>/consultants/?add=new" class="btn">+ Add Consultant</a>
                </div>
                <?php if ( ! empty( $config['team_consultants'] ) ) : ?>
                    <div class="person-list">
                        <?php
                        $sorted_consultants = $config['team_consultants'];
                        uasort( $sorted_consultants, function( $a, $b ) {
                            return strcasecmp( $a['name'], $b['name'] );
                        } );

                        foreach ( $sorted_consultants as $username => $consultant ) : ?>
                            <div class="person-item">
                                <div class="person-info">
                                    <h4><?php echo htmlspecialchars( $crm->mask_name( $consultant['name'], $privacy_mode ) ); ?></h4>
                                    <small>@<?php echo htmlspecialchars( $crm->mask_username( $username, $privacy_mode ) ); ?> • <?php echo htmlspecialchars( $consultant['location'] ?? $consultant['town'] ?? '' ); ?></small>
                                </div>
                                <div style="display: flex; gap: 8px;">
                                    <a href="/crm/admin/<?php echo $current_group; ?>/person/<?php echo $username; ?>/" class="btn">Edit</a>
                                    <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this consultant?')">
                                        <input type="hidden" name="action" value="delete_consultant">
                                        <input type="hidden" name="username" value="<?php echo htmlspecialchars( $username ); ?>">
                                        <input type="hidden" name="group" value="<?php echo htmlspecialchars( $current_group ); ?>">
                                        <button type="submit" class="btn btn-danger">Delete</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <p>No consultants added yet.</p>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ( $is_editing_consultant ) : ?>
                <?php render_person_form( 'consultant', $edit_data, $is_editing_consultant ); ?>
            <?php elseif ( isset( $_GET['add'] ) && $_GET['add'] === 'new' ) : ?>
                <div style="margin-bottom: 20px;">
                    <a href="/crm/admin/<?php echo $current_group; ?>/consultants/" class="back-link-admin">← Back to Consultants</a>
                </div>
                <h3>Add New Consultant</h3>
                <?php render_person_form( 'consultant', null, false ); ?>
            <?php endif; ?>
        </div>

        <!-- Alumni Tab -->
        <div id="alumni" class="tab-content <?php echo $active_tab === 'alumni' ? 'active' : ''; ?>">
            <?php if ( $is_editing_alumni ) : ?>
                <h2>Edit Alumni: <?php echo htmlspecialchars( $crm->mask_name( $edit_data['name'] ?? $edit_data['username'], $privacy_mode ) ); ?></h2>
            <?php elseif ( isset( $_GET['add'] ) && $_GET['add'] === 'new' ) : ?>
            <?php else : ?>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3 style="margin: 0;">Alumni</h3>
                    <?php
                    $add_params = array( 'tab' => 'alumni', 'add' => 'new' );
                    if ( $privacy_mode ) $add_params['privacy'] = '1';
                    ?>
                    <a href="/crm/admin/<?php echo $current_group; ?>/alumni/?add=new" class="btn">+ Add to Alumni</a>
                </div>
                <?php if ( ! empty( $config['alumni'] ) ) : ?>
                    <div class="person-list">
                        <?php
                        $sorted_alumni = $config['alumni'];
                        uasort( $sorted_alumni, function( $a, $b ) {
                            return strcasecmp( $a['name'], $b['name'] );
                        } );

                        foreach ( $sorted_alumni as $username => $alumnus ) : ?>
                            <div class="person-item">
                                <div class="person-info">
                                    <h4><?php echo htmlspecialchars( $crm->mask_name( $alumnus['name'], $privacy_mode ) ); ?></h4>
                                    <small>@<?php echo htmlspecialchars( $crm->mask_username( $username, $privacy_mode ) ); ?> • <?php echo htmlspecialchars( $alumnus['location'] ?? $alumnus['town'] ?? '' ); ?></small>
                                </div>
                                <div style="display: flex; gap: 8px;">
                                    <a href="/crm/admin/<?php echo $current_group; ?>/person/<?php echo $username; ?>/" class="btn">Edit</a>
                                    <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this alumni entry?')">
                                        <input type="hidden" name="action" value="delete_alumni">
                                        <input type="hidden" name="username" value="<?php echo htmlspecialchars( $username ); ?>">
                                        <input type="hidden" name="group" value="<?php echo htmlspecialchars( $current_group ); ?>">
                                        <button type="submit" class="btn btn-danger">Delete</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <p>No alumni added yet.</p>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ( $is_editing_alumni ) : ?>
                <?php render_person_form( 'alumni', $edit_data, $is_editing_alumni ); ?>
            <?php elseif ( isset( $_GET['add'] ) && $_GET['add'] === 'new' ) : ?>
                <div style="margin-bottom: 20px;">
                    <a href="/crm/admin/<?php echo $current_group; ?>/alumni/" class="back-link-admin">← Back to Alumni</a>
                </div>
                <h3>Add to Alumni</h3>
                <?php render_person_form( 'alumni', null, false ); ?>
            <?php endif; ?>
        </div>
