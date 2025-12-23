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
 * Generate username from name for social groups
 */
function generate_username_from_name( $name ) {
	if ( empty( $name ) ) {
		return null;
	}

	$username = strtolower( $name );

	// Use intl Transliterator if available for proper UTF-8 transliteration
	if ( class_exists( 'Transliterator' ) ) {
		$transliterator = \Transliterator::create( 'Any-Latin; Latin-ASCII' );
		if ( $transliterator ) {
			$username = $transliterator->transliterate( $username );
		}
	} else {
		// Fallback: common transliterations
		$replacements = array(
			'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
			'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'å' => 'a',
			'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
			'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
			'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ø' => 'o',
			'ú' => 'u', 'ù' => 'u', 'û' => 'u',
			'ñ' => 'n', 'ç' => 'c', 'ý' => 'y', 'ÿ' => 'y',
		);
		$username = strtr( $username, $replacements );
	}

	$username = preg_replace( '/[^a-z0-9\s-]/', '', $username ); // Only allow letters, numbers, spaces, hyphens
	$username = preg_replace( '/\s+/', '-', trim( $username ) ); // Replace spaces with hyphens
	$username = preg_replace( '/-+/', '-', $username ); // Collapse multiple hyphens

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

function get_person_form_value( $field_name, $edit_data = null, $is_editing = false, $error = '' ) {
	// Use global error if no error parameter provided
	if ( empty( $error ) ) {
		global $error;
	}

	// If there's an error, prioritize POST data
	if ( ! empty( $error ) && isset( $_POST[ $field_name ] ) ) {
		$value = sanitize_text_field( $_POST[ $field_name ] );
	} elseif ( $is_editing && $edit_data && property_exists( $edit_data, $field_name ) ) {
		$value = $edit_data->$field_name;
	} else {
		$value = '';
	}

	// Handle special cases
	if ( $field_name === 'location' && empty( $value ) && $is_editing && $edit_data ) {
		// Fallback to 'town' field for backward compatibility
		$value = property_exists( $edit_data, 'town' ) ? $edit_data->town : '';
	}

	return htmlspecialchars( $value );
}

function get_person_checkbox_checked( $field_name, $edit_data = null, $is_editing = false, $error = '' ) {
	// Use global error if no error parameter provided
	if ( empty( $error ) ) {
		global $error;
	}

	// If there's an error, prioritize POST data
	if ( ! empty( $error ) ) {
		return isset( $_POST[ $field_name ] ) ? 'checked' : '';
	} elseif ( $is_editing && $edit_data && property_exists( $edit_data, $field_name ) && ! empty( $edit_data->$field_name ) ) {
		return 'checked';
	}
	return '';
}


/**
 * ==================================================
 * PERSON-RELATED POST HANDLERS
 * ==================================================
 */

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
			$groups = $_POST['groups'] ?? array();

			// Extract current group memberships (no left date)
			$groups_with_dates = array();
			foreach ( $groups as $group_id => $group_data ) {
				if ( ! empty( $group_data['checked'] ) ) {
					$groups_with_dates[ intval( $group_id ) ] = array(
						'joined_date' => ! empty( $group_data['joined_date'] ) ? $group_data['joined_date'] : null,
						'left_date' => null,
					);
				}
			}

			// Add historical group memberships (with both dates)
			$historical_groups_data = $_POST['historical_groups'] ?? array();
			foreach ( $historical_groups_data as $historical ) {
				if ( ! empty( $historical['group_id'] ) && ! empty( $historical['joined_date'] ) && ! empty( $historical['left_date'] ) ) {
					$groups_with_dates[ intval( $historical['group_id'] ) ] = array(
						'joined_date' => $historical['joined_date'],
						'left_date' => $historical['left_date'],
					);
				}
			}

			// Auto-generate username if empty and belongs to a social group
			if ( empty( $username ) ) {
				// Check if any of the selected groups are social groups
				$is_social = false;
				foreach ( array_keys( $groups_with_dates ) as $gid ) {
					$group_data = $crm->storage->get_group_by_id( intval( $gid ) );
					if ( $group_data && $group_data->type === 'group' ) {
						$is_social = true;
						break;
					}
				}
				if ( $is_social ) {
					$name = $person_data['name'] ?? '';
					$username = strtolower( str_replace( ' ', '-', $name ) );
					$username = preg_replace( '/[^a-z0-9-]/', '', $username );
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

			$save_result = $crm->storage->save_person( $username, $person_data, $groups_with_dates );

			if ( $save_result ) {
				// Allow plugins to save additional data
				do_action( 'personal_crm_admin_person_save', $username, $_POST, 'person' );

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
			if ( $person && ! empty( $person->groups ) ) {
				// Remove from this specific group
				$person_id = $person->id;
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

	// Determine the group slug for social group checks
	$form_group_slug = $current_group;
	if ( empty( $form_group_slug ) && ! empty( $default_group_id ) ) {
		$group_data = $crm->storage->get_group_by_id( $default_group_id );
		if ( $group_data ) {
			$form_group_slug = $group_data->slug;
		}
	}

	// Get all groups with hierarchy for datalist autocomplete
	$all_groups = $crm->storage->get_all_groups_with_hierarchy();

	// Get groups this person is currently a member of
	$selected_groups = array();
	$historical_groups = array();
	$person_group_ids = array( $default_group_id );

	if ( $is_editing && ! empty( $edit_data->username ) ) {
		$person = $crm->storage->get_person( $edit_data->username );
		if ( $person && ! empty( $person->groups ) ) {
			foreach ( $person->groups as $group ) {
				$group_data = array(
					'id' => $group['id'],
					'name' => $group['group_name'],
					'icon' => $group['display_icon'] ?: '',
					'parent_id' => $group['parent_id'] ?? null,
					'group_joined_date' => $group['group_joined_date'] ?? null,
					'group_left_date' => $group['group_left_date'] ?? null
				);

				// Separate current vs historical based on left_date
				if ( ! empty( $group['group_left_date'] ) ) {
					$historical_groups[] = $group_data;
				} else {
					$selected_groups[] = $group_data;
					$person_group_ids[] = $group['id'];
				}
			}
		}
	} else {
		// For new person, default to the parent group
		$parent_group_data = $crm->storage->get_group_by_id( $parent_group_id );
		$parent_config = $parent_group_data ? $crm->storage->get_group( $parent_group_data->slug ) : null;
		$selected_groups[] = array(
			'id' => $default_group_id,
			'name' => ( $parent_config ? $parent_config->group_name : '' ) . ' (Members)',
			'icon' => '👥',
			'parent_id' => $parent_group_data ? $parent_group_data->parent_id : null
		);
	}

	// Get suggested groups: parents, siblings, and children of selected groups
	$suggested_groups = array();
	$suggested_group_ids = array();

	if ( ! empty( $selected_groups ) ) {
		// Collect IDs we need to query
		$selected_ids = array_column( $selected_groups, 'id' );
		$parent_ids = array_filter( array_column( $selected_groups, 'parent_id' ) );

		// Build query to fetch only related groups in one go
		$wpdb = $crm->storage->get_wpdb();
		$placeholders_selected = implode( ',', array_fill( 0, count( $selected_ids ), '%d' ) );
		$placeholders_parents = ! empty( $parent_ids ) ? implode( ',', array_fill( 0, count( $parent_ids ), '%d' ) ) : '0';

		// Fetch: parents, siblings (same parent), and children
		$query = $wpdb->prepare(
			"SELECT id, slug, group_name, display_icon, parent_id, sort_order
			FROM {$wpdb->prefix}personal_crm_groups
			WHERE id IN ($placeholders_parents)
			   OR parent_id IN ($placeholders_selected)
			   OR parent_id IN ($placeholders_parents)
			ORDER BY sort_order, group_name",
			array_merge( $parent_ids ?: array(), $selected_ids, $parent_ids ?: array() )
		);

		$related_groups = $wpdb->get_results( $query, ARRAY_A );

		// Index by ID and build parent->children mapping
		$groups_by_id = array();
		$children_by_parent = array();
		foreach ( $related_groups as $group ) {
			$groups_by_id[ $group['id'] ] = $group;
			$parent_id = $group['parent_id'] ?? 0;
			if ( ! isset( $children_by_parent[ $parent_id ] ) ) {
				$children_by_parent[ $parent_id ] = array();
			}
			$children_by_parent[ $parent_id ][] = $group;
		}

		foreach ( $selected_groups as $selected_group ) {
			$group_id = $selected_group['id'];
			$parent_id = $selected_group['parent_id'] ?? null;

			// Get parent group
			if ( $parent_id && isset( $groups_by_id[ $parent_id ] ) ) {
				$parent = $groups_by_id[ $parent_id ];
				if ( ! in_array( $parent['id'], $person_group_ids ) && ! in_array( $parent['id'], $suggested_group_ids ) ) {
					$display_name = $parent['group_name'];
					if ( ! empty( $parent['parent_id'] ) ) {
						$_parent = $crm->storage->get_group_by_id( $parent['parent_id'] );
						if ( $_parent ) {
							$display_name = $_parent->group_name . ' → ' . $parent['group_name'];
						}
					}
					$suggested_groups[] = array(
						'id' => $parent['id'],
						'name' => $display_name,
						'icon' => $parent['display_icon'] ?: '',
						'relationship' => 'parent',
						'related_to' => $selected_group['name']
					);
					$suggested_group_ids[] = $parent['id'];
				}
			}

			// Get sibling groups (same parent)
			$siblings = $children_by_parent[ $parent_id ?? 0 ] ?? array();
			foreach ( $siblings as $sibling ) {
				if ( $sibling['id'] !== $group_id && ! in_array( $sibling['id'], $person_group_ids ) && ! in_array( $sibling['id'], $suggested_group_ids ) ) {
					$display_name = $sibling['group_name'];
					if ( ! empty( $sibling['parent_id'] ) ) {
						$parent = $crm->storage->get_group_by_id( $sibling['parent_id'] );
						if ( $parent ) {
							$display_name = $parent->group_name . ' → ' . $sibling['group_name'];
						}
					}
					$suggested_groups[] = array(
						'id' => $sibling['id'],
						'name' => $display_name,
						'icon' => $sibling['display_icon'] ?: '',
						'relationship' => 'sibling',
						'related_to' => $selected_group['name']
					);
					$suggested_group_ids[] = $sibling['id'];
				}
			}

			// Get child groups
			$children = $children_by_parent[ $group_id ] ?? array();
			foreach ( $children as $child ) {
				if ( ! in_array( $child['id'], $person_group_ids ) && ! in_array( $child['id'], $suggested_group_ids ) ) {
					$display_name = $child['group_name'];
					if ( ! empty( $child['parent_id'] ) ) {
						$parent = $crm->storage->get_group_by_id( $child['parent_id'] );
						if ( $parent ) {
							$display_name = $parent->group_name . ' → ' . $child['group_name'];
						}
					}
					$suggested_groups[] = array(
						'id' => $child['id'],
						'name' => $display_name,
						'icon' => $child['display_icon'] ?: '',
						'relationship' => 'child',
						'related_to' => $selected_group['name']
					);
					$suggested_group_ids[] = $child['id'];
				}
			}
		}
	}

	$action = $is_editing ? 'edit_person' : 'add_person';
	$submit_text = 'Save';
	$form_id = $is_editing ? 'edit-person-form' : 'add-person-form';

?>
<form method="post" action="" id="<?php echo $form_id; ?>">
	<input type="hidden" name="action" value="<?php echo $action; ?>">
	<input type="hidden" name="original_username" value="<?php echo $is_editing ? htmlspecialchars( $edit_data->username ?? '' ) : ''; ?>">
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
			<?php render_birthday_dropdown( 'birthday', $edit_data->birthday ?? '' ); ?>
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
				<?php render_birthday_dropdown( 'partner_birthday', $edit_data->partner_birthday ?? '' ); ?>
			</div>
		</div>

		<div class="form-group">
			<label for="kids">Kids (one per line, formats: "Name YYYY-MM-DD", "Name MM-DD", "Name YYYY" or just "Name")</label>
			<textarea id="kids" name="kids" rows="4" placeholder="Emma 2010-03-15&#10;Jake 12-25&#10;Sam 2012&#10;Alex"><?php echo $is_editing ? htmlspecialchars( format_kids_for_form( $edit_data->kids ?? array() ) ) : ''; ?></textarea>
		</div>
	</details>

	<!-- Company Information -->
	<h4 class="section-heading">Company Information</h4>
	<div class="form-grid">
		<div class="form-group">
			<label for="username">Username<?php if ( $crm->is_social_group( $form_group_slug ) ) { echo ' (auto-generated if empty)'; } else { echo ' *'; } ?></label>
			<input type="text" id="username" name="username" value="<?php echo get_person_form_value( 'username', $edit_data, $is_editing ); ?>"<?php if ( ! $crm->is_social_group( $form_group_slug ) ) { echo ' required'; } ?>>
			<?php if ( $crm->is_social_group( $form_group_slug ) ) : ?>
				<small style="color: #666;">Leave empty to auto-generate from name (e.g., "John Smith" → "john-smith")</small>
			<?php endif; ?>
		</div>

		<?php if ( ! $crm->is_social_group( $form_group_slug ) ) : ?>
		<div class="form-group">
			<label for="role">Role</label>
			<input type="text" id="role" name="role" value="<?php echo get_person_form_value( 'role', $edit_data, $is_editing ); ?>" placeholder="e.g., Developer, Lead, HR">
		</div>
		<?php endif; ?>

		<?php if ( ! $crm->is_social_group( $form_group_slug ) ) : ?>
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
				if ( $is_editing && property_exists( $edit_data, 'links' ) ) {
					$current_links = $edit_data->links;
				}

				if ( ! $crm->is_social_group( $form_group_slug ) && ! isset( $current_links['1:1 doc'] ) ) {
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

	<?php if ( ! $crm->is_social_group( $form_group_slug ) ) : ?>
	<!-- GitHub Repositories -->
	<div class="form-group">
		<label>GitHub Repositories</label>
		<div style="margin: 5px 0 10px 0;">
			<button type="button" onclick="addRepoField()" class="btn-add-repo">+ Add Repository</button>
		</div>

		<div id="repo_fields">
			<?php
			$existing_repos = array();
			if ( $is_editing && ! empty( $edit_data->github_repos ) ) {
				$existing_repos = is_array( $edit_data->github_repos ) ? $edit_data->github_repos : array_filter( array_map( 'trim', explode( ',', $edit_data->github_repos ) ) );
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
		// Optimize: Fetch repos directly from database instead of loading all Person objects
		$wpdb = $crm->storage->get_wpdb();
		$parent_group_data = $crm->storage->get_group_by_id( $parent_group_id );

		$all_repos = array();
		if ( $parent_group_data ) {
			// Get all group IDs (parent + children) to query
			$group_ids = array( $parent_group_id );
			$child_groups_raw = $wpdb->get_results( $wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}personal_crm_groups WHERE parent_id = %d",
				$parent_group_id
			), ARRAY_A );
			foreach ( $child_groups_raw as $child ) {
				$group_ids[] = $child['id'];
			}

			// Fetch github_repos from all people in these groups
			$placeholders = implode( ',', array_fill( 0, count( $group_ids ), '%d' ) );
			$repos_raw = $wpdb->get_col( $wpdb->prepare(
				"SELECT DISTINCT p.github_repos
				FROM {$wpdb->prefix}personal_crm_people p
				INNER JOIN {$wpdb->prefix}personal_crm_people_groups pg ON p.id = pg.person_id
				WHERE pg.group_id IN ($placeholders)
				AND p.github_repos IS NOT NULL
				AND p.github_repos != ''
				AND p.github_repos != '[]'",
				$group_ids
			) );

			// Parse JSON arrays and flatten
			foreach ( $repos_raw as $repos_json ) {
				$repos = json_decode( $repos_json, true );
				if ( is_array( $repos ) ) {
					$all_repos = array_merge( $all_repos, $repos );
				}
			}
			$all_repos = array_unique( array_filter( $all_repos ) );
		}

		$user_repos = array();
		if ( $is_editing && ! empty( $edit_data->github_repos ) ) {
			$user_repos = is_array( $edit_data->github_repos ) ? $edit_data->github_repos : array_filter( array_map( 'trim', explode( ',', $edit_data->github_repos ) ) );
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
			$personal_events = $is_editing && property_exists( $edit_data, 'personal_events' ) ? $edit_data->personal_events : array();
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
	// Check if person is in any alumni group (check all selected groups)
	$is_alumni_group = false;
	foreach ( $selected_groups as $group ) {
		$group_data = $crm->storage->get_group_by_id( $group['id'] );
		if ( $group_data && stripos( $group_data->slug, 'alumni' ) !== false ) {
			$is_alumni_group = true;
			break;
		}
	}
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
			echo (!empty($error) && isset($_POST['deceased'])) || ($is_editing && !empty($edit_data->deceased)) ? '' : 'display: none;';
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
		<label>Currently in these groups:</label>
		<div id="selected-groups-container" class="group-checkboxes" style="margin-top: 10px; margin-bottom: 15px;">
			<?php foreach ( $selected_groups as $group ) : ?>
				<?php
				// Build hierarchical name if this is a child group
				$display_name = $group['name'];
				if ( ! empty( $group['parent_id'] ) ) {
					$parent = $crm->storage->get_group_by_id( $group['parent_id'] );
					if ( $parent ) {
						$display_name = $parent->group_name . ' → ' . $group['name'];
					}
				}
				?>
				<label class="group-checkbox-label selected" data-group-id="<?php echo $group['id']; ?>" style="display: flex; align-items: center; gap: 10px;">
					<input type="checkbox" name="groups[<?php echo $group['id']; ?>][checked]" value="1" checked>
					<?php if ( $group['icon'] ) : ?>
						<span class="group-icon"><?php echo $group['icon']; ?></span>
					<?php endif; ?>
					<span class="group-name"><?php echo htmlspecialchars( $display_name ); ?></span>
					<input type="date" name="groups[<?php echo $group['id']; ?>][joined_date]" value="<?php echo isset( $group['group_joined_date'] ) && $group['group_joined_date'] ? date( 'Y-m-d', strtotime( $group['group_joined_date'] ) ) : ''; ?>" style="margin-left: auto; width: 140px; font-size: 0.9em;" title="Date joined this group (optional)" placeholder="Joined">
				</label>
			<?php endforeach; ?>
		</div>

		<div id="historical-groups-section" style="margin-top: 20px; <?php echo empty( $historical_groups ) ? 'display: none;' : ''; ?>">
			<label style="display: block;">Historical group memberships:</label>
			<div id="historical-groups-container" class="group-checkboxes" style="margin-top: 10px; margin-bottom: 15px; opacity: 0.7;">
				<?php foreach ( $historical_groups as $index => $group ) : ?>
					<?php
					// Build hierarchical name if this is a child group
					$display_name = $group['name'];
					if ( ! empty( $group['parent_id'] ) ) {
						$parent = $crm->storage->get_group_by_id( $group['parent_id'] );
						if ( $parent ) {
							$display_name = $parent->group_name . ' → ' . $group['name'];
						}
					}
					?>
					<div class="historical-group-row" style="display: flex; align-items: center; gap: 10px; padding: 8px; background: light-dark(#f5f5f5, #2a2a2a); border-radius: 8px; margin-bottom: 8px;">
						<input type="hidden" name="historical_groups[<?php echo $index; ?>][group_id]" value="<?php echo $group['id']; ?>">
						<?php if ( $group['icon'] ) : ?>
							<span class="group-icon"><?php echo $group['icon']; ?></span>
						<?php endif; ?>
						<span class="group-name" style="min-width: 150px;"><?php echo htmlspecialchars( $display_name ); ?></span>
						<input type="date" name="historical_groups[<?php echo $index; ?>][joined_date]" value="<?php echo isset( $group['group_joined_date'] ) && $group['group_joined_date'] ? date( 'Y-m-d', strtotime( $group['group_joined_date'] ) ) : ''; ?>" style="width: 140px; font-size: 0.9em;" title="Date joined" placeholder="Joined" required>
						<span>→</span>
						<input type="date" name="historical_groups[<?php echo $index; ?>][left_date]" value="<?php echo isset( $group['group_left_date'] ) && $group['group_left_date'] ? date( 'Y-m-d', strtotime( $group['group_left_date'] ) ) : ''; ?>" style="width: 140px; font-size: 0.9em;" title="Date left" placeholder="Left" required>
						<button type="button" class="remove-historical-membership" data-index="<?php echo $index; ?>" style="margin-left: auto; padding: 4px 8px; font-size: 0.9em;">Remove</button>
					</div>
				<?php endforeach; ?>
			</div>
		</div>

		<details style="margin: 20px 0;">
			<summary class="summary-toggle">Manage group membership</summary>

			<?php if ( ! empty( $suggested_groups ) ) : ?>
				<label>Related groups:</label>
				<div id="suggested-groups-container" class="group-checkboxes suggested" style="margin-top: 10px; margin-bottom: 15px;">
					<?php
					$groups_by_relationship = array();
					foreach ( $suggested_groups as $group ) {
						$groups_by_relationship[ $group['relationship'] ][] = $group;
					}

					foreach ( array( 'parent', 'sibling', 'child' ) as $rel_type ) :
						if ( ! empty( $groups_by_relationship[ $rel_type ] ) ) :
							?>
							<div class="relationship-group <?php echo $rel_type; ?>-groups">
								<div class="relationship-label">
									<?php
									switch ( $rel_type ) {
										case 'parent':
											echo '↑ Parent groups';
											break;
										case 'sibling':
											echo '↔ Peer groups';
											break;
										case 'child':
											echo '↓ Subgroups';
											break;
									}
									?>
								</div>
								<?php foreach ( $groups_by_relationship[ $rel_type ] as $group ) : ?>
									<div class="suggested-group-row" data-group-id="<?php echo $group['id']; ?>" style="display: flex; align-items: center; gap: 10px; padding: 8px; margin-bottom: 8px; border: 1px solid light-dark(#e0e0e0, #444); border-radius: 6px;">
										<?php if ( $group['icon'] ) : ?>
											<span class="group-icon"><?php echo $group['icon']; ?></span>
										<?php endif; ?>
										<span class="group-name" style="flex: 1;"><?php echo htmlspecialchars( $group['name'] ); ?></span>
										<button type="button" class="add-suggested-to-current" data-group-id="<?php echo $group['id']; ?>" style="padding: 4px 8px; font-size: 0.85em;">Add as Current</button>
										<button type="button" class="add-suggested-to-historical" data-group-id="<?php echo $group['id']; ?>" style="padding: 4px 8px; font-size: 0.85em;">Add as Historical</button>
									</div>
								<?php endforeach; ?>
							</div>
						<?php
						endif;
					endforeach;
					?>
				</div>
			<?php endif; ?>

			<label for="add-group-input">Search all groups:</label>
			<div style="display: flex; gap: 10px; align-items: center;">
				<input type="text" id="add-group-input" list="groups-datalist" placeholder="Type to search..." autocomplete="off" style="flex: 1; max-width: 400px;">
				<button type="button" id="add-to-current-btn" style="padding: 6px 12px; font-size: 0.9em;" disabled>Add as Current</button>
				<button type="button" id="add-to-historical-btn" style="padding: 6px 12px; font-size: 0.9em;" disabled>Add as Historical</button>
			</div>
			<datalist id="groups-datalist">
				<?php foreach ( $all_groups as $group ) : ?>
					<option value="<?php echo htmlspecialchars( ( $group['display_icon'] ? $group['display_icon'] . ' ' : '' ) . $group['hierarchical_name'] ); ?>"></option>
				<?php endforeach; ?>
			</datalist>

			<script type="application/json" id="all-groups-data">
				<?php echo json_encode( $all_groups ); ?>
			</script>
		</details>
	</div>

	<h4 class="section-heading">Notes</h4>
	<div class="form-group">

		<?php if ( $is_editing && ! empty( $edit_data->notes ) && is_array( $edit_data->notes ) ) : ?>
			<div class="existing-notes">
				<?php foreach ( $edit_data->notes as $note ) : ?>
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
                $person->username = $edit_member;
                // Default group is the first group they belong to (if any)
                $default_group_id = ! empty( $person->groups ) ? $person->groups[0]['id'] : 0;
                $parent_group_id = $default_group_id;

                // Show back link if person belongs to only one group
                if ( ! empty( $person->groups ) && count( $person->groups ) === 1 ) {
                    $single_group = $person->groups[0];
                    $group_obj = $crm->storage->get_group_by_id( $single_group['id'] );
                    if ( $group_obj ) {
                        echo '<div class="back-link" style="margin-bottom: 15px;">';
                        echo '<a href="' . $crm->build_url( 'admin/index.php', array( 'group' => $group_obj->slug, 'members' => true ) ) . '">';
                        echo '← Back to ' . htmlspecialchars( $single_group['group_name'] ) . '</a>';
                        echo '</div>';
                    }
                }

                echo '<h2>Edit Person: ' . htmlspecialchars( $person->name ?? $edit_member ) . '</h2>';
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
        $group_data = $crm->storage->get_group( $current_group );

        // If current group is a child group, use the parent to build the tabs (for consistency)
        if ( $group_data && ! empty( $group_data->parent_id ) ) {
            $parent_group = $crm->storage->get_group_by_id( $group_data->parent_id );
            if ( $parent_group ) {
                $group_data = $parent_group;
            }
        }

        $menu_group_id = $group_data->id;
        $child_groups = $crm->storage->get_child_groups( $menu_group_id );

        // First tab: Direct members of parent group
        $tabs = array(
            array(
                'slug' => 'members',
                'display_name' => 'Members',
                'display_icon' => '👥',
                'can_add' => 1,
                'sort_order' => 0,
                'group_id' => $menu_group_id
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
                            <a href="<?php echo $crm->build_url( 'admin/index.php', array( 'group' => $slug ) ); ?>" class="edit-group-link">⚙️ Edit Group</a>
                        <?php endif; ?>
                    </h3>
                    <?php if ( $can_add ) : ?>
                        <?php
                        // For 'members' tab, use parent group; for child groups, use their slug
                        $add_link_group = ( $slug === 'members' ) ? $current_group : $slug;
                        ?>
                        <a href="<?php echo $crm->build_url( 'admin/index.php', array( 'group' => $add_link_group, 'members' => true, 'add' => 'new' ) ); ?>" class="btn">+ Add <?php echo htmlspecialchars( $display_name ); ?></a>
                    <?php endif; ?>
                </div>
                <?php
                // Fetch members for this tab's group only (not including children)
                $tab_members = $crm->storage->get_group_members( $tab_group_id, false );
                ?>
                <?php if ( ! empty( $tab_members ) ) : ?>
                    <div class="person-list">
                        <?php
                        $sorted_people = $tab_members;
                        uasort( $sorted_people, function( $a, $b ) {
                            $a_name = is_object( $a ) ? $a->name : $a['name'];
                            $b_name = is_object( $b ) ? $b->name : $b['name'];
                            return strcasecmp( $a_name, $b_name );
                        } );

                        foreach ( $sorted_people as $username => $person ) :
                            // Determine the correct group slug for the edit link
                            $edit_group_slug = ( $slug === 'members' ) ? $current_group : $slug;
                        ?>
                            <div class="person-item">
                                <div class="person-info">
                                    <h4><?php echo htmlspecialchars( is_object( $person ) ? $person->name : $person['name'] ); ?></h4>
                                    <small>@<?php echo htmlspecialchars( $username ); ?> • <?php
                                        $location = is_object( $person ) ? ( $person->location ?? '' ) : ( $person['location'] ?? $person['town'] ?? '' );
                                        echo htmlspecialchars( $location );
                                    ?></small>
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
                <?php render_person_form_new( $tab_group_id, $menu_group_id, $edit_data, $is_editing ); ?>
            <?php elseif ( isset( $_GET['add'] ) && $_GET['add'] === 'new' && $can_add ) : ?>
                <div style="margin-bottom: 20px;">
                    <a href="<?php echo $crm->build_url( 'admin/index.php', array( 'group' => $slug, 'members' => true ) ); ?>" class="back-link-admin">← Back to <?php echo htmlspecialchars( $display_name ); ?></a>
                </div>
                <h3>Add <?php echo htmlspecialchars( $display_name ); ?></h3>
                <?php render_person_form_new( $tab_group_id, $menu_group_id, null, false ); ?>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

<?php do_action( 'personal_crm_admin_people_list_scripts' ); ?>
