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
function render_birthday_dropdown( $field_prefix, $value ) {
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

/**
 * Get person type configuration
 */
function get_person_type_config( $person_type ) {
	global $group, $current_group;
	$crm = PersonalCrm::get_instance();

	$person_types = $crm->storage->get_person_types( $current_group );

	$configs = array();
	foreach ( $person_types as $type ) {
		$type_key = $type['type_key'];
		$form_prefix = $type_key === 'team_members' ? '' : $type_key . '-';

		$configs[ $type_key ] = array(
			'section_key' => $type_key,
			'form_prefix' => $form_prefix,
			'form_id' => $type_key . '-form',
			'edit_action' => 'edit_' . $type_key,
			'add_action' => $type['can_add'] ? 'add_' . $type_key : null,
			'delete_action' => 'delete_' . $type_key,
			'edit_text' => 'Update ' . $type['display_name'],
			'add_text' => $type['can_add'] ? 'Add ' . $type['display_name'] : null,
			'display_name' => $type['display_name'],
			'display_icon' => $type['display_icon'],
		);
	}

	$configs['alumni'] = array(
		'section_key' => 'alumni',
		'form_prefix' => 'alumni-',
		'form_id' => 'alumni-form',
		'edit_action' => 'edit_alumni',
		'add_action' => null,
		'delete_action' => 'delete_alumni',
		'edit_text' => 'Update Alumni',
		'add_text' => null,
		'display_name' => 'Alumni',
		'display_icon' => '🎓',
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
	if ( ! $crm ) {
		$crm = \PersonalCrm\PersonalCrm::get_instance();
	}
	$is_social_group = $current_group ? $crm->is_social_group( $current_group ) : false;

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
	if ( $field_name === 'location' && empty( $value ) && $is_editing ) {
		// Fallback to 'town' field for backward compatibility
		$value = $edit_data['town'] ?? '';
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

			// Redirect to person view
			$redirect_url = $crm->build_url( 'person.php', array( 'person' => $username ) );
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
_POST['action'] . ')' : 'NO' ) );

// Handle POST requests for person operations
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['action'] ) ) {
	$action = $_POST['action'];

	// Handle standard person actions first (add_person, edit_person, delete_person)
	if ( in_array( $action, array( 'add_person', 'edit_person', 'delete_person', 'move_to_alumni' ), true ) ) {
		switch ( $action ) {

		case 'add_person':
		case 'edit_person':
			$person_data = create_person_data_from_form();
			$username = sanitize_text_field( $_POST['username'] ?? '' );
			$original_username = sanitize_text_field( $_POST['original_username'] ?? '' );
			$group_ids = $_POST['group_ids'] ?? array();

			// Auto-generate username if empty and belongs to a social group
			if ( empty( $username ) ) {
				// Check if any of the selected groups are social groups
				$is_social = false;
				foreach ( $group_ids as $gid ) {
					$group_data = $crm->storage->get_group_by_id( intval( $gid ) );
					if ( $group_data && isset( $group_data['type'] ) && $group_data['type'] === 'group' ) {
						$is_social = true;
						break;
					}
				}
				if ( $is_social ) {
					$name = $person_data['name'] ?? '';
					$username = strtolower( str_replace( ' ', '.', $name ) );
					$username = preg_replace( '/[^a-z0-9._-]/', '', $username );
				}
			}

			if ( empty( $username ) ) {
				$error = 'Username is required.';
				break;
			}

			// If editing and username changed, update the username
			if ( $action === 'edit_person' && ! empty( $original_username ) && $original_username !== $username ) {
				// Delete old person
				$crm->storage->delete_person( $original_username );
			}

			// Convert group_ids to integers
			$group_ids = array_map( 'intval', array_filter( $group_ids ) );

			$save_result = $crm->storage->save_person( $username, $person_data, $group_ids );

			if ( $save_result ) {
				$message = $action === 'edit_person' ? 'Person updated successfully!' : 'Person added successfully!';
				// Redirect to person view
				$redirect_url = $crm->build_url( 'person.php', array( 'person' => $username ) );
				header( 'Location: ' . $redirect_url );
				exit;
			} else {
				$error = 'Failed to save person.';
			}
			break;

		case 'delete_person':
			$username = sanitize_text_field( $_POST['username'] ?? '' );
			$group_id = intval( $_POST['group_id'] ?? 0 );

			if ( empty( $username ) || empty( $group_id ) ) {
				$error = 'Username and group ID are required.';
				break;
			}

			// Get person to check if they belong to other groups
			$person = $crm->storage->get_person( $username );
			if ( $person && ! empty( $person['groups'] ) ) {
				// Remove from this specific group
				$person_id = $person['id'];
				$crm->storage->remove_person_from_group( $person_id, $group_id );

				// Check if person is in any other groups
				$remaining_groups = $crm->storage->get_person_groups( $person_id );
				if ( empty( $remaining_groups ) ) {
					// Delete person entirely if not in any groups
					$crm->storage->delete_person( $username );
				}

				$message = 'Person removed successfully!';
			} else {
				$error = 'Person not found.';
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
							// Redirect to person after deleting current team
							$redirect_url = $crm->build_url( 'person.php', array( 'person' => $username ) );
							header( 'Location: ' . $redirect_url );
							exit;
						} else {
							$message = "Person moved successfully but could not delete empty team file.";
						}
					} else {
						// Redirect to person
						$redirect_url = $crm->build_url( 'person.php', array( 'person' => $username ) );
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
	} elseif ( preg_match( '/^(edit|add|delete)_(.+)$/', $action, $matches ) ) {
		// Dynamic person type handler - matches edit_{type}, add_{type}, delete_{type}
		$operation = $matches[1];
		$type_key = $matches[2];

		// Get person type config
		$type_config = get_person_type_config( $type_key );
		if ( $type_config ) {
			// Handle based on operation
			if ( $operation === 'delete' ) {
				$result = handle_person_action( $action, $type_config, array() );
			} else {
				$person_data = create_person_data_from_form();
				$result = handle_person_action( $action, $type_config, $person_data );
			}

			// Set message/error
			if ( isset( $result['error'] ) ) {
				$error = $result['error'];
			} elseif ( isset( $result['message'] ) ) {
				$message = $result['message'];
			}
		} else {
			$error = 'Invalid person type.';
		}
	}
}

/**
 * ==================================================
 * PERSON FORM RENDERER
 * ==================================================
 */

/**
 * NEW: Render person form with group membership (replaces category-based forms)
 */
function render_person_form_new( $default_group_id, $parent_group_id, $edit_data = null, $is_editing = false ) {
	global $current_group;

	$crm = PersonalCrm::get_instance();

	// Get all groups with hierarchy for datalist autocomplete
	$all_groups = $crm->storage->get_all_groups_with_hierarchy();

	// Get groups this person is currently a member of
	$selected_groups = array();
	$person_group_ids = array( $default_group_id );

	if ( $is_editing && ! empty( $edit_data['username'] ) ) {
		$person = $crm->storage->get_person( $edit_data['username'] );
		if ( $person && ! empty( $person['groups'] ) ) {
			$person_group_ids = array_column( $person['groups'], 'id' );
			foreach ( $person['groups'] as $group ) {
				$selected_groups[] = array(
					'id' => $group['id'],
					'name' => $group['group_name'],
					'icon' => $group['display_icon'] ?: ''
				);
			}
		}
	} else {
		// For new person, default to the current group
		$parent_config = $crm->storage->get_group( $current_group );
		$selected_groups[] = array(
			'id' => $default_group_id,
			'name' => ( $parent_config['group_name'] ?? 'Team' ) . ' (Team Members)',
			'icon' => '👥'
		);
	}

	$action = $is_editing ? 'edit_person' : 'add_person';
	$submit_text = 'Save';
	$form_id = $is_editing ? 'edit-person-form' : 'add-person-form';

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
			<label for="name">Full Name</label>
			<input type="text" id="name" name="name" value="<?php echo get_person_form_value( 'name', $edit_data, $is_editing ); ?>" autofocus>
		</div>

		<div class="form-group">
			<label for="nickname">Nickname <small class="optional-label">(optional)</small></label>
			<input type="text" id="nickname" name="nickname" value="<?php echo get_person_form_value( 'nickname', $edit_data, $is_editing ); ?>" placeholder="e.g., Mike, Lizzy, DJ">
		</div>

		<div class="form-group">
			<label for="location">Location</label>
			<input type="text" id="location" name="location" value="<?php echo get_person_form_value( 'location', $edit_data, $is_editing ); ?>">
		</div>

		<div class="form-group">
			<label for="timezone">Timezone</label>
			<div class="timezone-input">
				<input type="text"
					   id="timezone"
					   name="timezone"
					   value="<?php echo get_person_form_value( 'timezone', $edit_data, $is_editing ); ?>"
					   placeholder="e.g., America/New_York or type city like 'madrid'"
					   autocomplete="off">
				<div id="timezone-suggestions" class="timezone-suggestions"></div>
			</div>
			<script type="application/json" id="timezone-data">
				<?php echo json_encode( get_timezone_options() ); ?>
			</script>
		</div>

		<div class="form-group">
			<label>Birthday</label>
			<?php render_birthday_dropdown( 'birthday', $edit_data['birthday'] ?? '' ); ?>
		</div>
	</div>

	<!-- Family Information -->
	<details style="margin: 20px 0;">
		<summary class="summary-toggle">Family Information</summary>

		<div class="form-grid">
			<div class="form-group">
				<label for="partner">Partner</label>
				<input type="text" id="partner" name="partner" value="<?php echo get_person_form_value( 'partner', $edit_data, $is_editing ); ?>">
			</div>

			<div class="form-group">
				<label>Partner Birthday</label>
				<?php render_birthday_dropdown( 'partner_birthday', $edit_data['partner_birthday'] ?? '' ); ?>
			</div>
		</div>

		<div class="form-group">
			<label for="kids">Kids (one per line, formats: "Name YYYY-MM-DD", "Name MM-DD", "Name YYYY" or just "Name")</label>
			<textarea id="kids" name="kids" rows="4" placeholder="Emma 2010-03-15&#10;Jake 12-25&#10;Sam 2012&#10;Alex"><?php echo $is_editing ? htmlspecialchars( format_kids_for_form( $edit_data['kids'] ?? array() ) ) : ''; ?></textarea>
		</div>
	</details>

	<!-- Company Information -->
	<h4 class="section-heading">Company Information</h4>
	<div class="form-grid">
		<div class="form-group">
			<label for="username">Username *<?php if ( $crm->is_social_group( $current_group ) ) echo ' (auto-generated if empty)'; ?></label>
			<input type="text" id="username" name="username" value="<?php echo get_person_form_value( 'username', $edit_data, $is_editing ); ?>" <?php echo $crm->is_social_group( $current_group ) ? '' : 'required'; ?>>
			<?php if ( $crm->is_social_group( $current_group ) ) : ?>
				<small style="color: #666;">Leave empty to auto-generate from name (e.g., "John Smith" → "john.smith")</small>
			<?php endif; ?>
		</div>

		<?php if ( ! $crm->is_social_group( $current_group ) ) : ?>
		<div class="form-group">
			<label for="role">Role</label>
			<input type="text" id="role" name="role" value="<?php echo get_person_form_value( 'role', $edit_data, $is_editing ); ?>" placeholder="e.g., Developer, Lead, HR">
		</div>
		<?php endif; ?>

		<?php if ( ! $crm->is_social_group( $current_group ) ) : ?>
		<div class="form-group">
			<label for="company_anniversary">Company Anniversary</label>
			<input type="date" id="company_anniversary" name="company_anniversary" value="<?php echo get_person_form_value( 'company_anniversary', $edit_data, $is_editing ); ?>">
		</div>
		<?php endif; ?>


		<!-- Links Section -->
		<div class="form-group" style="grid-column: 1 / -1;">
			<label>Links</label>
			<div id="links-container">
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
			<button type="button" onclick="addLink('')" class="btn-add">+ Add Link</button>
		</div>
	</div>

	<!-- Usernames -->
	<h4 class="section-heading">Online Profiles</h4>
	<div class="form-grid">
		<div class="form-group">
			<label for="github">GitHub Username</label>
			<input type="text" id="github" name="github" value="<?php echo get_person_form_value( 'github', $edit_data, $is_editing ); ?>">
		</div>

		<div class="form-group">
			<label for="linear">Linear Username</label>
			<input type="text" id="linear" name="linear" value="<?php echo get_person_form_value( 'linear', $edit_data, $is_editing ); ?>">
		</div>

		<div class="form-group">
			<label for="wordpress">WordPress.org Username</label>
			<input type="text" id="wordpress" name="wordpress" value="<?php echo get_person_form_value( 'wordpress', $edit_data, $is_editing ); ?>">
		</div>

		<div class="form-group">
			<label for="linkedin">LinkedIn Username</label>
			<input type="text" id="linkedin" name="linkedin" value="<?php echo get_person_form_value( 'linkedin', $edit_data, $is_editing ); ?>">
		</div>

		<div class="form-group">
			<label for="website">Website URL</label>
			<input type="text" id="website" name="website" value="<?php echo get_person_form_value( 'website', $edit_data, $is_editing ); ?>">
		</div>

		<div class="form-group">
			<label for="email">Email Address</label>
			<input type="email" id="email" name="email" value="<?php echo get_person_form_value( 'email', $edit_data, $is_editing ); ?>">
		</div>
	</div>

	<?php if ( ! $crm->is_social_group( $current_group ) ) : ?>
	<!-- GitHub Repositories -->
	<div class="form-group">
		<label>GitHub Repositories</label>
		<div style="margin: 5px 0 10px 0;">
			<button type="button" onclick="addRepoField()" class="btn-add-repo">+ Add Repository</button>
		</div>

		<div id="repo_fields">
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

		// Merge all people from all person types dynamically
		$all_people = array();
		$person_types = $crm->storage->get_person_types( $current_group );
		foreach ( $person_types as $type ) {
			$all_people = array_merge( $all_people, $team_config[ $type['type_key'] ] ?? array() );
		}
		// Also include alumni
		$all_people = array_merge( $all_people, $team_config['alumni'] ?? array() );
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
						<button type="button" onclick="addRepoToField('<?php echo htmlspecialchars( $repo, ENT_QUOTES ); ?>')"
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
		function addRepoField() {
			const container = document.getElementById('repo_fields');
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
				addRepoField();
			}
		}

		function addRepoToField(repo) {
			const container = document.getElementById('repo_fields');
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

			addRepoField();
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

	<?php
	// Check if default group is an alumni group
	$default_group = null;
	foreach ( $selected_groups as $ag ) {
		if ( $ag['id'] == $default_group_id ) {
			$default_group = $ag;
			break;
		}
	}
	$is_alumni_group = $default_group && stripos( $default_group['name'], 'alumni' ) !== false;
	?>
	<?php if ( $is_alumni_group ) : ?>
	<!-- Alumni-specific fields -->
	<h4 class="section-heading">Alumni Information</h4>
	<div class="form-grid">
		<div class="form-group" style="grid-column: 1 / -1;">
			<label style="display: flex; align-items: center; gap: 8px; margin-bottom: 5px; font-weight: 600;">
				<input type="checkbox" id="left_company" name="left_company" value="1" <?php echo get_person_checkbox_checked( 'left_company', $edit_data, $is_editing ); ?> style="width: auto;">
				<span>Has left the company</span>
			</label>
			<small class="text-small-muted" style="margin-left: 20px;">
				Check if this person is no longer with the company
			</small>
		</div>

		<div class="form-group">
			<label for="new_company">New Company</label>
			<input type="text" id="new_company" name="new_company" value="<?php echo get_person_form_value( 'new_company', $edit_data, $is_editing ); ?>" placeholder="e.g., Google, Microsoft, etc.">
		</div>

		<div class="form-group">
			<label for="new_company_website">New Company Website</label>
			<input type="url" id="new_company_website" name="new_company_website" value="<?php echo get_person_form_value( 'new_company_website', $edit_data, $is_editing ); ?>" placeholder="https://example.com">
		</div>
	</div>
	<?php endif; ?>

	<!-- Deceased Status -->
	<div class="form-group">
		<label style="display: flex; align-items: center; gap: 8px; margin-bottom: 5px; font-weight: 400; color: #666;">
			<input type="checkbox" id="deceased" name="deceased" value="1" <?php echo get_person_checkbox_checked( 'deceased', $edit_data, $is_editing ); ?> style="width: auto;" onchange="toggleDeceasedDate()">
			<span>Mark as deceased</span>
		</label>
		<small class="text-small-muted" style="margin-left: 20px; color: #888;">
			This will remove them from birthday reminders
		</small>

		<div id="deceased-date-container" style="margin-top: 10px; margin-left: 20px; <?php
			global $error;
			echo (!empty($error) && isset($_POST['deceased'])) || ($is_editing && !empty($edit_data['deceased'])) ? '' : 'display: none;';
		?>">
			<label for="deceased_date">Date of passing:</label>
			<input type="date" id="deceased_date" name="deceased_date" value="<?php echo get_person_form_value( 'deceased_date', $edit_data, $is_editing ); ?>">
			<small class="text-small-muted" style="display: block; margin-top: 5px;">
				Optional - leave empty if unknown
			</small>
		</div>
	</div>

	<script>
	function toggleDeceasedDate() {
		const checkbox = document.getElementById('deceased');
		const container = document.getElementById('deceased-date-container');
		const dateInput = document.getElementById('deceased_date');

		if (checkbox.checked) {
			container.style.display = 'block';
		} else {
			container.style.display = 'none';
			dateInput.value = '';
		}
	}
	</script>

	<!-- Group Membership -->
	<h4 class="section-heading">Group Membership</h4>
	<div class="form-group">
		<label>Currently selected groups:</label>
		<div id="selected-groups-container" style="margin-top: 10px; margin-bottom: 15px;">
			<?php foreach ( $selected_groups as $group ) : ?>
				<label style="display: block; margin-bottom: 8px;" data-group-id="<?php echo $group['id']; ?>">
					<input type="checkbox" name="group_ids[]" value="<?php echo $group['id']; ?>" checked>
					<?php echo htmlspecialchars( ( $group['icon'] ? $group['icon'] . ' ' : '' ) . $group['name'] ); ?>
				</label>
			<?php endforeach; ?>
		</div>

		<label for="add-group-input">Add group:</label>
		<input type="text" id="add-group-input" list="groups-datalist" placeholder="Search for group..." autocomplete="off" style="width: 100%; max-width: 400px;">
		<datalist id="groups-datalist">
			<?php foreach ( $all_groups as $group ) : ?>
				<option value="<?php echo htmlspecialchars( ( $group['display_icon'] ? $group['display_icon'] . ' ' : '' ) . $group['hierarchical_name'] ); ?>"></option>
			<?php endforeach; ?>
		</datalist>

		<script type="application/json" id="all-groups-data">
			<?php echo json_encode( $all_groups ); ?>
		</script>
	</div>

	<h4 class="section-heading">Notes</h4>
	<div class="form-group">

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

		<label for="new_note">Add new note</label>
		<textarea id="new_note" name="new_note" placeholder="Add what you learned today..."></textarea>
	</div>

	<?php
	do_action( 'personal_crm_admin_person_form_fields', $edit_data, $is_editing );
	?>

	<button type="submit" class="btn"><?php echo $submit_text; ?></button>
</form>

<?php
}

/**
 * ==================================================
 * PERSON LIST RENDERER (HTML)
 * ==================================================
 */
?>
        <?php
        // Person editing (M:N relationship - person doesn't belong to a single group)
        if ( ! empty( $edit_member ) ) {
            $person = $crm->storage->get_person( $edit_member );
            if ( $person ) {
                $person['username'] = $edit_member;
                // Default group is the first group they belong to (if any)
                $default_group_id = ! empty( $person['groups'] ) ? $person['groups'][0]['id'] : 0;
                $parent_group_id = $default_group_id;
                echo '<h2>Edit Person: ' . htmlspecialchars( $person['name'] ?? $edit_member ) . '</h2>';
                render_person_form_new( $default_group_id, $parent_group_id, $person, true );
            } else {
                echo '<p>Person not found.</p>';
            }
            return;
        }

        // Group-based person list view (only when not editing a specific person)
        if ( empty( $current_group ) ) {
            echo '<p>Please select a group to view people.</p>';
            return;
        }

        // Generate tabs for direct members + child groups
        $parent_config = $crm->storage->get_group( $current_group );
        $parent_group_id = $parent_config['id'];
        $child_groups = $crm->storage->get_child_groups( $parent_group_id );

        // First tab: Direct members of parent group
        $tabs = array(
            array(
                'slug' => 'members',
                'display_name' => 'Team Members',
                'display_icon' => '👥',
                'can_add' => 1,
                'sort_order' => 0,
                'group_id' => $parent_group_id
            )
        );

        // Add child group tabs
        foreach ( $child_groups as $child ) {
            // Use short slug in URL (remove parent prefix)
            $url_slug = $child['slug'];
            if ( strpos( $url_slug, $current_group . '_' ) === 0 ) {
                $url_slug = substr( $url_slug, strlen( $current_group ) + 1 );
            }

            $tabs[] = array(
                'slug' => $child['slug'],
                'url_slug' => $url_slug,
                'display_name' => $child['group_name'],
                'display_icon' => $child['display_icon'] ?: '',
                'can_add' => 1,
                'sort_order' => $child['sort_order'],
                'group_id' => $child['id']
            );
        }

        foreach ( $tabs as $tab ) :
            $slug = $tab['slug'];
            $url_slug = $tab['url_slug'] ?? $slug;
            $display_name = $tab['display_name'];
            $display_icon = $tab['display_icon'];
            $can_add = $tab['can_add'];
            $tab_group_id = $tab['group_id'];

            // Check if editing someone in this group
            $is_editing = false;
            if ( ! empty( $edit_member ) && isset( $config[ $slug ] ) && isset( $config[ $slug ][ $edit_member ] ) ) {
                $is_editing = true;
                $edit_data = $config[ $slug ][ $edit_member ];
                $edit_data['username'] = $edit_member;
            }
        ?>
        <!-- <?php echo $display_name; ?> Tab -->
        <div id="<?php echo $slug; ?>" class="tab-content <?php echo $active_tab === $slug ? 'active' : ''; ?>">
            <?php if ( $is_editing ) : ?>
                <h2>Edit <?php echo htmlspecialchars( $display_name ); ?>: <?php echo htmlspecialchars( $edit_data['name'] ?? $edit_data['username'] ); ?></h2>
            <?php elseif ( isset( $_GET['add'] ) && $_GET['add'] === 'new' ) : ?>
            <?php else : ?>
                <div class="tab-header">
                    <h3>
                        <?php echo htmlspecialchars( ( $display_icon ? $display_icon . ' ' : '' ) . $display_name ); ?>
                        <?php if ( $slug !== 'members' ) : ?>
                            <a href="/crm/admin/<?php echo $slug; ?>/" class="edit-group-link">⚙️ Edit Group</a>
                        <?php endif; ?>
                    </h3>
                    <?php if ( $can_add ) : ?>
                        <a href="/crm/admin/<?php echo $current_group; ?>/<?php echo $url_slug; ?>/?add=new" class="btn">+ Add <?php echo htmlspecialchars( $display_name ); ?></a>
                    <?php endif; ?>
                </div>
                <?php if ( ! empty( $config[ $slug ] ) ) : ?>
                    <div class="person-list">
                        <?php
                        $sorted_people = $config[ $slug ];
                        uasort( $sorted_people, function( $a, $b ) {
                            return strcasecmp( $a['name'], $b['name'] );
                        } );

                        foreach ( $sorted_people as $username => $person ) :
                            // Determine the correct group slug for the edit link
                            $edit_group_slug = ( $slug === 'members' ) ? $current_group : $slug;
                        ?>
                            <div class="person-item">
                                <div class="person-info">
                                    <h4><?php echo htmlspecialchars( $person['name'] ); ?></h4>
                                    <small>@<?php echo htmlspecialchars( $username ); ?> • <?php echo htmlspecialchars( $person['location'] ?? $person['town'] ?? '' ); ?></small>
                                </div>
                                <div style="display: flex; gap: 8px;">
                                    <a href="<?php echo $crm->build_url( 'admin/person.php', array( 'person' => $username ) ); ?>" class="btn">Edit</a>
                                    <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this person?')">
                                        <input type="hidden" name="action" value="delete_person">
                                        <input type="hidden" name="username" value="<?php echo htmlspecialchars( $username ); ?>">
                                        <input type="hidden" name="group_id" value="<?php echo $tab_group_id; ?>">
                                        <input type="hidden" name="group" value="<?php echo htmlspecialchars( $current_group ); ?>">
                                        <button type="submit" class="btn btn-danger">Delete</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <p>No <?php echo strtolower( $display_name ); ?> added yet.</p>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ( $is_editing ) : ?>
                <?php render_person_form_new( $tab_group_id, $parent_group_id, $edit_data, $is_editing ); ?>
            <?php elseif ( isset( $_GET['add'] ) && $_GET['add'] === 'new' && $can_add ) : ?>
                <div style="margin-bottom: 20px;">
                    <a href="/crm/admin/<?php echo $current_group; ?>/<?php echo $slug; ?>/" class="back-link-admin">← Back to <?php echo htmlspecialchars( $display_name ); ?></a>
                </div>
                <h3>Add <?php echo htmlspecialchars( $display_name ); ?></h3>
                <?php render_person_form_new( $tab_group_id, $parent_group_id, null, false ); ?>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
