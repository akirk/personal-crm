<?php
/**
 * Team/Group Management Admin Tool
 *
 * A web interface for creating and managing team/group JSON configuration
 */
namespace PersonalCRM;

require_once __DIR__ . '/personal-crm.php';

// Error handling
ini_set( 'display_errors', 1 );
error_reporting( E_ALL );

// Special admin.php logic - check for team creation before main initialization
$crm = PersonalCrm::get_instance();
$current_group = $crm->get_current_group_from_params();
if ( ! $current_group && ! ( isset( $_GET['create_team'] ) && $_GET['create_team'] === 'new' ) ) {
	header( 'Location: ' . $crm->build_url( 'select.php' ) );
	exit;
}

extract( PersonalCrm::get_globals() );

$config_file = $current_group ? __DIR__ . '/' . $current_group . '.json' : null;
$action = $_POST['action'] ?? $_GET['action'] ?? 'dashboard';

// Determine active tab from route or query parameter
$request_uri = $_SERVER['REQUEST_URI'] ?? '';
if ( strpos( $request_uri, '/links' ) !== false ) {
	$active_tab = 'team_links';
} elseif ( strpos( $request_uri, '/members' ) !== false ) {
	$active_tab = 'members';
} elseif ( strpos( $request_uri, '/leadership' ) !== false ) {
	$active_tab = 'leadership';
} elseif ( strpos( $request_uri, '/consultants' ) !== false ) {
	$active_tab = 'consultants';
} elseif ( strpos( $request_uri, '/alumni' ) !== false ) {
	$active_tab = 'alumni';
} elseif ( strpos( $request_uri, '/events' ) !== false ) {
	$active_tab = 'events';
} elseif ( strpos( $request_uri, '/audit' ) !== false ) {
	$active_tab = 'audit';
} elseif ( strpos( $request_uri, '/json' ) !== false ) {
	$active_tab = 'json';
} elseif ( strpos( $request_uri, '/person/' ) !== false ) {
	$active_tab = 'members'; // Person editing defaults to members tab
} else {
	$active_tab = $_GET['tab'] ?? 'general';
}
$is_adding_new = isset( $_GET['add'] ) && $_GET['add'] === 'new';
$is_creating_team = isset( $_GET['create_team'] ) && $_GET['create_team'] === 'new';

// Check if group exists in database and redirect to selector if not (unless already creating a team)
if ( $current_group && ! $crm->storage->group_exists( $current_group ) && ! $is_creating_team ) {
	header( 'Location: ' . $crm->build_url( 'select.php' ) );
	exit;
}

// Check if we're editing a specific member or event
// Handle wp-app route parameter for admin/{team}/person/{person}
$route_person = function_exists( 'get_query_var' ) ? get_query_var( 'person' ) : '';
$edit_member = $_GET['edit_member'] ?? $route_person;
$edit_event_index = $_GET['edit_event'] ?? '';
$edit_data = null;
$is_editing_member = false;
$is_editing_leader = false;
$is_editing_consultant = false;
$is_editing_alumni = false;
$is_editing_event = false;

// Initialize $group early with a default value
$group = 'team';

if ( ! empty( $edit_member ) ) {
	$config = $crm->storage->get_group( $current_group );
	$group = ( $config && isset( $config['type'] ) ) ? $config['type'] : 'team';
	if ( $config && isset( $config['team_members'][ $edit_member ] ) ) {
		$edit_data = $config['team_members'][ $edit_member ];
		$edit_data['username'] = $edit_member;
		$is_editing_member = true;
		$active_tab = 'members';
	} elseif ( isset( $config['leadership'][ $edit_member ] ) ) {
		$edit_data = $config['leadership'][ $edit_member ];
		$edit_data['username'] = $edit_member;
		$is_editing_leader = true;
		$active_tab = 'leadership';
	} elseif ( isset( $config['consultants'][ $edit_member ] ) ) {
		$edit_data = $config['consultants'][ $edit_member ];
		$edit_data['username'] = $edit_member;
		$is_editing_consultant = true;
		$active_tab = 'consultants';
	} elseif ( isset( $config['alumni'][ $edit_member ] ) ) {
		$edit_data = $config['alumni'][ $edit_member ];
		$edit_data['username'] = $edit_member;
		$is_editing_alumni = true;
		$active_tab = 'alumni';
	}
} elseif ( $edit_event_index !== '' && is_numeric( $edit_event_index ) ) {
	$config = $crm->storage->get_group( $current_group );
	if ( isset( $config['events'][ $edit_event_index ] ) ) {
		$edit_data = $config['events'][ $edit_event_index ];
		$edit_data['event_index'] = $edit_event_index;
		$is_editing_event = true;
		$active_tab = 'events';
	}
}

/**
 * Save configuration to storage
 */

$message = '';
$error = '';

// Handle form submissions
if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
	$config = $crm->storage->get_group( $current_group ); // Load config for POST operations

	switch ( $action ) {
		case 'save_general':
			$config['group_name'] = sanitize_text_field( $_POST['team_name'] ?? '' );
			$config['activity_url_prefix'] = sanitize_url( $_POST['activity_url_prefix'] ?? '' );
			$config['type'] = sanitize_text_field( $_POST['team_type'] ?? 'team' );
			
			// Handle default team setting
			$is_default = isset( $_POST['is_default'] ) && $_POST['is_default'] === '1';
			
			if ( $is_default ) {
				// If this team is being set as default, clear default from all other teams
				$available_groups = $crm->storage->get_available_groups();
				foreach ( $available_groups as $group_slug ) {
					if ( $group_slug !== $current_group ) {
						$other_team_file = __DIR__ . '/' . $group_slug . '.json';
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
			
			// Handle not managing team setting
			if ( $config['type'] === 'group' ) {
				// Groups are always not managed (no HR feedback, etc.)
				$config['not_managing_team'] = true;
			} else {
				$not_managing_team = isset( $_POST['not_managing_team'] ) && $_POST['not_managing_team'] === '1';

				if ( $not_managing_team ) {
					// Remove not managing team flag if unchecked
					if ( isset( $config['not_managing_team'] ) ) {
						unset( $config['not_managing_team'] );
					}
				} else {
					$config['not_managing_team'] = false;
				}
			}

			if ( $crm->storage->save_group( $current_group, $config ) ) {
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

			if ( $crm->storage->save_group( $current_group, $config ) ) {
				$message = 'Links saved successfully!';
			} else {
				$error = 'Failed to save links.';
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
			if ( $crm->storage->save_group( $current_group, $config ) ) {
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
			if ( $crm->storage->save_group( $current_group, $config ) ) {
				$message = 'Event added successfully!';
				// Reload config to get the latest data
				$config = $crm->storage->get_group( $current_group );
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
			
		case 'delete_event':
			$event_index = (int) ( $_POST['event_index'] ?? -1 );
			if ( $event_index >= 0 && isset( $config['events'][ $event_index ] ) ) {
				array_splice( $config['events'], $event_index, 1 );
				if ( $crm->storage->save_group( $current_group, $config ) ) {
					$message = 'Event deleted successfully!';
				} else {
					$error = 'Failed to delete event.';
				}
			}
			break;

		case 'create_team':
			$new_team_slug = sanitize_text_field( $_POST['new_team_slug'] ?? '' );
			$new_team_name = sanitize_text_field( $_POST['new_team_name'] ?? '' );
			$new_team_type = sanitize_text_field( $_POST['new_team_type'] ?? 'team' );
			
			if ( empty( $new_team_slug ) || empty( $new_team_name ) ) {
				$error = 'Slug and name are required.';
				break;
			}
			
			// Validate slug format
			if ( ! preg_match( '/^[a-z0-9_-]+$/', $new_team_slug ) ) {
				$error = 'Slug can only contain lowercase letters, numbers, hyphens and underscores.';
				break;
			}
			
			$new_team_file = __DIR__ . '/' . $new_team_slug . '.json';
			
			if ( file_exists( $new_team_file ) ) {
				$error = 'A ' . $new_team_type . ' with this slug already exists.';
				break;
			}
			
			$new_config = array(
				'activity_url_prefix' => '',
				'team_name' => $new_team_name,
				'type' => $new_team_type,
				'team_members' => array(),
				'leadership' => array(),
				'alumni' => array(),
				'events' => array()
			);
			
			// Groups are always not managed (no HR feedback, etc.)
			if ( $new_team_type === 'group' ) {
				$new_config['not_managing_team'] = true;
			}

			// If this is the first team being created, make it the default
			$existing_teams = $crm->storage->get_available_groups();
			if ( empty( $existing_teams ) ) {
				$new_config['default'] = true;
			}
			
			if ( $crm->storage->save_group( $new_team_file, $new_config ) ) {
				$message = ucfirst( $new_team_type ) . ' created successfully!';
				// Redirect to the new team
				$redirect_url = 'admin.php' . ( $new_team_slug !== 'team' ? '?team=' . urlencode( $new_team_slug ) : '' );
				header( 'Location: ' . $redirect_url );
				exit;
			} else {
				$error = 'Failed to create team.';
			}
			break;
			
		case 'add_note':
			$username = sanitize_text_field( $_POST['username'] ?? '' );
			$new_note = sanitize_textarea_field( $_POST['new_note'] ?? '' );
			
			if ( ! empty( $username ) && ! empty( $new_note ) ) {
				// Find the person in any section and add the note
				$person_found = false;
				foreach ( array( 'team_members', 'leadership', 'consultants', 'alumni' ) as $type ) {
					if ( isset( $config[$type][$username] ) ) {
						// Initialize notes array if it doesn't exist or isn't an array
						if ( ! isset( $config[$type][$username]['notes'] ) || ! is_array( $config[$type][$username]['notes'] ) ) {
							$config[$type][$username]['notes'] = array();
						}
						
						// Add the new note
						$config[$type][$username]['notes'][] = array(
							'date' => date( 'Y-m-d H:i' ),
							'text' => $new_note
						);
						
						$person_found = true;
						break;
					}
				}
				
				if ( $person_found ) {
					$json = json_encode( $config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
					if ( file_put_contents( $config_file, $json ) !== false ) {
						$message = 'Note added successfully!';
						// Redirect back to person.php
						$redirect_params = array( 'person' => $username );
						if ( isset( $_POST['privacy'] ) ) $redirect_params['privacy'] = '1';
						if ( isset( $_POST['notes_view'] ) ) $redirect_params['notes_view'] = $_POST['notes_view'];
						header( 'Location: ' . $crm->build_url( 'person.php', $redirect_params ) );
						exit;
					} else {
						$error = 'Failed to save note.';
					}
				} else {
					$error = 'Person not found.';
				}
			} else {
				$error = 'Username and note are required.';
			}
			break;
			
		case 'edit_note':
			$username = sanitize_text_field( $_POST['username'] ?? '' );
			$note_index = intval( $_POST['note_index'] ?? -1 );
			$edit_note_text = sanitize_textarea_field( $_POST['edit_note_text'] ?? '' );
			
			if ( ! empty( $username ) && $note_index >= 0 && ! empty( $edit_note_text ) ) {
				// Find the person in any section and edit the note
				$person_found = false;
				foreach ( array( 'team_members', 'leadership', 'consultants', 'alumni' ) as $type ) {
					if ( isset( $config[$type][$username] ) ) {
						// Check if notes array exists and has the specified index
						if ( isset( $config[$type][$username]['notes'] ) && 
							 is_array( $config[$type][$username]['notes'] ) && 
							 isset( $config[$type][$username]['notes'][$note_index] ) ) {
							
							// Update the note text (keep original date)
							$config[$type][$username]['notes'][$note_index]['text'] = $edit_note_text;
							$person_found = true;
							break;
						}
					}
				}
				
				if ( $person_found ) {
					$json = json_encode( $config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
					if ( file_put_contents( $config_file, $json ) !== false ) {
						$message = 'Note updated successfully!';
						// Redirect back to person.php
						$redirect_params = array( 'person' => $username );
						if ( isset( $_POST['privacy'] ) ) $redirect_params['privacy'] = '1';
						if ( isset( $_POST['notes_view'] ) ) $redirect_params['notes_view'] = $_POST['notes_view'];
						header( 'Location: ' . $crm->build_url( 'person.php', $redirect_params ) );
						exit;
					} else {
						$error = 'Failed to save note.';
					}
				} else {
					$error = 'Person or note not found.';
				}
			} else {
				$error = 'Username, note index, and note text are required.';
			}
			break;
			
	}
}

// Load config for display (after any POST operations)
$config = $crm->storage->get_group( $current_group ) ?: array(
	'activity_url_prefix' => '',
	'group_name' => '',
	'team_members' => array(),
	'leadership' => array(),
	'consultants' => array(),
	'alumni' => array(),
	'events' => array(),
	'type' => 'team',
	'default' => false,
	'not_managing' => true,
	'links' => array(),
);

// Set group type label for UI
$group = ( isset( $config['type'] ) && $config['type'] ) ? $config['type'] : 'team';

// WordPress-style sanitization functions (simplified versions)
function mask_date_input( $date, $privacy_mode ) {
	if ( ! $privacy_mode || empty( $date ) ) {
		return $date;
	}
	
	return ''; // Hide the date input value in privacy mode
}

/**
 * Check if current team is a social group (vs business team)
 */

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
 * Unified person CRUD operations
 */
function handle_person_action( $action, $config, $person_data ) {
	global $config_file, $current_group;

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
			$redirect_url = '/crm/' . $current_group . '/' . $username;
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
	$is_social_group = common->is_social_group( $current_group );

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
 * Check which data points are missing for a person
 */
function get_missing_data_points( $person, $person_type = 'member', $group_slug = null ) {
	$missing = array();
	$is_social_group = PersonalCrm::get_instance()->is_social_group( $group_slug );

	// Core fields (required)
	if ( empty( $person['name'] ) ) {
		$missing[] = array( 'field' => 'Name', 'priority' => 'required' );
	}

	// Role is only required for business teams, not social groups
	if ( ! $is_social_group && empty( $person['role'] ) ) {
		$missing[] = array( 'field' => 'Role', 'priority' => 'required' );
	}
	if ( empty( $person['location'] ) ) {
		$missing[] = array( 'field' => 'Location', 'priority' => 'required' );
	}
	if ( empty( $person['timezone'] ) ) {
		$missing[] = array( 'field' => 'Timezone', 'priority' => 'required' );
	}

	// Birthday (not required for consultants)
	if ( empty( $person['birthday'] ) && $person_type !== 'consultants' ) {
		$missing[] = array( 'field' => 'Birthday', 'priority' => 'required' );
	}

	// Company anniversary is only required for business teams, not social groups
	if ( ! $is_social_group && empty( $person['company_anniversary'] ) ) {
		$missing[] = array( 'field' => 'Company Anniversary', 'priority' => 'required' );
	}

	// Links - check for key links (1:1 doc not required for consultants or social groups)
	$expected_links = array();
	if ( $person_type !== 'consultants' && ! $is_social_group ) {
		$expected_links[] = '1:1 doc';
	}

	foreach ( $expected_links as $expected_link ) {
		if ( ! isset( $person['links'][ $expected_link ] ) || empty( $person['links'][ $expected_link ] ) ) {
			$missing[] = array( 'field' => $expected_link . ' link', 'priority' => 'required' );
		}
	}

	// Recommended fields - likely to be filled out for most people
	if ( empty( $person['email'] ) ) {
		$missing[] = array( 'field' => 'Email address', 'priority' => 'recommended' );
	}
	if ( empty( $person['website'] ) ) {
		$missing[] = array( 'field' => 'Website', 'priority' => 'recommended' );
	}
	if ( empty( $person['wordpress'] ) ) {
		$missing[] = array( 'field' => 'WordPress.org profile', 'priority' => 'recommended' );
	}
	if ( empty( $person['linkedin'] ) ) {
		$missing[] = array( 'field' => 'LinkedIn profile', 'priority' => 'recommended' );
	}
	if ( empty( $person['partner'] ) ) {
		$missing[] = array( 'field' => 'Partner', 'priority' => 'recommended' );
	}
	
	// Optional fields - often rightfully stay empty
	if ( empty( $person['kids'] ) ) {
		$missing[] = array( 'field' => 'Kids info', 'priority' => 'optional' );
	}
	if ( empty( $person['notes'] ) || ( is_array( $person['notes'] ) && count( $person['notes'] ) === 0 ) ) {
		$missing[] = array( 'field' => 'Notes', 'priority' => 'optional' );
	}

	return $missing;
}

/**
 * Get completeness score as percentage
 */
function get_completeness_score( $missing_data, $person_type = 'member', $group_slug = null ) {
	$is_social_group = PersonalCrm::get_instance()->is_social_group( $group_slug );

	// Count total fields by priority
	if ( $is_social_group ) {
		$total_required = 4; // name, location, timezone, birthday (no role, company_anniversary, 1:1 doc)
	} else {
		$total_required = 7; // name, role, location, timezone, birthday, company_anniversary, 1:1 doc
	}
	$total_recommended = 3; // wordpress.org, linkedin, partner
	$total_optional = 2; // kids, notes
	
	// Count missing fields by priority
	$missing_required = 0;
	$missing_recommended = 0;
	$missing_optional = 0;
	
	foreach ( $missing_data as $missing_item ) {
		if ( is_array( $missing_item ) ) {
			switch ( $missing_item['priority'] ) {
				case 'required':
					$missing_required++;
					break;
				case 'recommended':
					$missing_recommended++;
					break;
				case 'optional':
					$missing_optional++;
					break;
			}
		} else {
			// Backwards compatibility - treat string items as required if not marked optional
			if ( strpos( $missing_item, 'optional' ) === false ) {
				$missing_required++;
			} else {
				$missing_recommended++;
			}
		}
	}
	
	// Calculate weighted score
	// Required fields: 70% weight
	// Recommended fields: 25% weight  
	// Optional fields: 5% weight
	$required_score = ( ( $total_required - $missing_required ) / $total_required ) * 70;
	$recommended_score = ( ( $total_recommended - $missing_recommended ) / $total_recommended ) * 25;
	$optional_score = ( ( $total_optional - $missing_optional ) / $total_optional ) * 5;
	
	$total_score = $required_score + $recommended_score + $optional_score;
	
	return max( 0, round( $total_score ) );
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
 * Render a person form (team member, leader, or alumni)
 */
function render_person_form( $type, $edit_data = null, $is_editing = false ) {
	global $group;
	$crm = PersonalCrm::get_instance();
	$privacy_mode = isset( $_GET['privacy'] ) && $_GET['privacy'] === '1';

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
	$show_alumni_actions = $config['show_alumni_actions'];

	// Helper function to get form field values (POST data if error, edit data if editing, empty if new)

	// Helper function for checkbox fields

?>
<form method="post" action="" id="<?php echo $form_id; ?>">
	<input type="hidden" name="action" value="<?php echo $action; ?>">
	<input type="hidden" name="original_username" value="<?php echo $is_editing ? htmlspecialchars( $edit_data['username'] ?? '' ) : ''; ?>">
	<?php global $current_group; if ( $current_group !== 'team' ) : ?>
		<input type="hidden" name="team" value="<?php echo htmlspecialchars( $current_group ); ?>">
	<?php endif; ?>
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
				<input type="text" id="<?php echo $prefix; ?>partner" name="partner" value="<?php echo get_person_form_value( 'partner', $edit_data, $is_editing ); ?>">
			</div>

			<div class="form-group">
				<label>Partner Birthday<?php echo $privacy_mode ? ' (Hidden in Privacy Mode)' : ''; ?></label>
				<?php if ( ! $privacy_mode ) : ?>
					<?php
					// Parse existing partner birthday data for editing
					$partner_day = '';
					$partner_month = '';
					$partner_year = '';
					if ( $is_editing && ! empty( $edit_data['partner_birthday'] ) ) {
						$partner_birthday_value = $edit_data['partner_birthday'];
						if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $partner_birthday_value, $matches ) ) {
							// Full date format: YYYY-MM-DD
							$partner_year = $matches[1];
							$partner_month = $matches[2];
							$partner_day = $matches[3];
						} elseif ( preg_match( '/^(\d{2})-(\d{2})$/', $partner_birthday_value, $matches ) ) {
							// Year-unknown format: MM-DD
							$partner_month = $matches[1];
							$partner_day = $matches[2];
						}
					}
					?>
					<div style="display: flex; gap: 10px; align-items: center;">
						<select name="partner_birthday_day" class="form-select">
							<option value="">Day</option>
							<?php for ( $d = 1; $d <= 31; $d++ ) : ?>
								<option value="<?php echo sprintf( '%02d', $d ); ?>" <?php echo (string) $partner_day === sprintf( '%02d', $d ) ? 'selected' : ''; ?>>
									<?php echo $d; ?>
								</option>
							<?php endfor; ?>
						</select>

						<select name="partner_birthday_month" class="form-select">
							<option value="">Month</option>
							<?php
							$months = array(
								'01' => 'January', '02' => 'February', '03' => 'March', '04' => 'April',
								'05' => 'May', '06' => 'June', '07' => 'July', '08' => 'August',
								'09' => 'September', '10' => 'October', '11' => 'November', '12' => 'December'
							);
							foreach ( $months as $num => $name ) : ?>
								<option value="<?php echo $num; ?>" <?php echo (string) $partner_month === (string) $num ? 'selected' : ''; ?>>
									<?php echo $num; ?> - <?php echo $name; ?>
								</option>
							<?php endforeach; ?>
						</select>

						<select name="partner_birthday_year" class="form-select">
							<option value="">Year (optional)</option>
							<?php for ( $y = 1900; $y <= date( 'Y' ); $y++ ) : ?>
								<option value="<?php echo $y; ?>" <?php echo $partner_year === (string) $y ? 'selected' : ''; ?>>
									<?php echo $y; ?>
								</option>
							<?php endfor; ?>
						</select>
					</div>
					<small class="form-helper-text">
						Year is optional - leave empty if unknown
					</small>
				<?php else : ?>
					<input type="hidden" name="partner_birthday_day" value="">
					<input type="hidden" name="partner_birthday_month" value="">
					<input type="hidden" name="partner_birthday_year" value="">
					<p class="text-muted italic-text">Hidden in privacy mode</p>
				<?php endif; ?>
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
				// Prepare links for display
				$current_links = array();
				if ( $is_editing && isset( $edit_data['links'] ) ) {
					$current_links = $edit_data['links'];
				}

				// Ensure 1:1 doc link is always present for business teams (not social groups)
				if ( ! $crm->is_social_group( $current_group ) && ! isset( $current_links['1:1 doc'] ) ) {
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
			dateInput.value = ''; // Clear the date when unchecked
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
	// Allow plugins to add custom fields
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
			<?php if ( $current_group !== 'team' ) : ?>
				<input type="hidden" name="team" value="<?php echo htmlspecialchars( $current_group ); ?>">
			<?php endif; ?>
			<button type="submit" class="btn btn-warning">
				📚 Move to Alumni
			</button>
		</form>
		<p class="text-small-muted" style="margin: 5px 0 0 0;">
			This will move the person to alumni status while preserving all their data.
		</p>
	</div>

	<!-- Move to Another <?php echo ucfirst( $group ); ?> -->
	<?php if ( $is_editing && ( $type === 'member' || $type === 'leader' ) ) : ?>
		<?php 
		$available_groups = $crm->storage->get_available_groups();
		$other_teams = array_filter( $available_groups, function( $team ) use ( $current_group ) {
			return $team !== $current_group;
		});
		?>
		<?php if ( ! empty( $other_teams ) ) : ?>
			<div class="divider-section">
				<h4 class="text-muted" style="margin-bottom: 10px;">Move to Another <?php echo ucfirst( $group ); ?></h4>
				<form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to move this person to another team?')">
					<input type="hidden" name="action" value="move_to_team">
					<input type="hidden" name="username" value="<?php echo htmlspecialchars( $edit_data['username'] ?? '' ); ?>">
					<input type="hidden" name="from_section" value="<?php echo $config['section_key']; ?>">
					
					<div style="margin-bottom: 10px;">
						<label for="target_team" style="display: block; margin-bottom: 5px; font-weight: bold;">Target <?php echo ucfirst( $group ); ?>:</label>
						<select name="target_team" id="target_team" required style="padding: 8px; border: 1px solid #ddd; border-radius: 4px; margin-right: 10px;">
							<option value="">Select a <?php echo $group; ?>...</option>
							<?php foreach ( $other_teams as $group_slug ) : ?>
								<?php
								$team_name = $crm->storage->get_group_name( $group_slug );
								
								// Load target team config to get member count
								$target_team_config = $crm->storage->get_group( $group_slug );
								$member_count = count( $target_team_config['team_members'] ?? array() ) + count( $target_team_config['leadership'] ?? array() );
								
								$display_name = $team_name . ' (' . $member_count . ' members)';
								?>
								<option value="<?php echo htmlspecialchars( $group_slug ); ?>">
									<?php echo htmlspecialchars( $display_name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					
					<?php 
					// Check if moving this person would leave current team empty (excluding alumni)
					global $config_file;
					$team_config = $crm->storage->get_group( $current_group );
					$current_username = $edit_data['username'] ?? '';
					$temp_members = $team_config['team_members'] ?? array();
					$temp_leadership = $team_config['leadership'] ?? array();
					
					// Remove this person from the appropriate section
					if ( isset( $temp_members[ $current_username ] ) ) {
						unset( $temp_members[ $current_username ] );
					}
					if ( isset( $temp_leadership[ $current_username ] ) ) {
						unset( $temp_leadership[ $current_username ] );
					}
					
					$would_be_empty = empty( $temp_members ) && empty( $temp_leadership );
					$has_alumni = ! empty( $team_config['alumni'] );
					?>
					
					<?php if ( $would_be_empty && ! $has_alumni && $current_group !== 'team' ) : ?>
						<div style="margin-bottom: 10px;">
							<label>
								<input type="checkbox" name="delete_if_empty" checked style="margin-right: 5px;">
								Delete current team if no members remain
							</label>
							<p class="text-small-muted" style="margin: 5px 0 0 20px;">
								This person is the last active member. Check to delete the team file.
							</p>
						</div>
					<?php endif; ?>
					
					<button type="submit" class="btn btn-primary">
						🔄 Move to <?php echo ucfirst( $group ); ?>
					</button>
				</form>
				<p class="text-small-muted" style="margin: 5px 0 0 0;">
					This will move the person to the selected team as a regular member.
				</p>
			</div>
		<?php endif; ?>
	<?php endif; ?>
<?php endif; ?>
<?php
}

?>
<!DOCTYPE html>
<html <?php echo function_exists( 'wp_app_language_attributes' ) ? wp_app_language_attributes() : 'lang="en"'; ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <title><?php echo function_exists( 'wp_app_title' ) ? wp_app_title( ucfirst( $group ?? 'Team' ) . ' Management Admin' ) : ucfirst( $group ?? 'Team' ) . ' Management Admin'; ?></title>
    <?php
    if ( ! function_exists( 'wp_app_enqueue_style' ) ) {
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
            <div class="header-content">
                <h1><a href="<?php echo $crm->build_url( 'admin.php' ); ?>" style="color: inherit; text-decoration: none;"><?php echo ucfirst( $group ?? 'Team' ); ?> Management Admin</a></h1>
            </div>
            <div class="navigation">
                <div class="group-switcher" style="display: inline-block; margin-right: 10px;">
                    <?php
                    $available_groups = $crm->storage->get_available_groups();
                    if ( $available_groups ) :
                    	?>
                    <select id="group-selector" onchange="switchGroup()">
                        <?php
                        foreach ( $available_groups as $group_slug ) {
                            $team_display_name = $crm->storage->get_group_name( $group_slug );
                            $selected = $group_slug === $current_group ? 'selected' : '';
                            echo '<option value="' . htmlspecialchars( $crm->build_url( $group_slug ) ) . '" ' . $selected . '>' . htmlspecialchars( $team_display_name ) . '</option>';
                        }
                        ?>
                    </select>
                <?php endif; ?>
                    <a href="<?php echo $crm->build_url( 'admin.php', array( 'create_team' => 'new' ) ); ?>" class="nav-link" style="font-size: 12px; padding: 6px 12px; margin-left: 5px;">+ New Team</a>
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
            <!-- Create New Page -->
            <div style="margin-bottom: 20px;">
                <a href="<?php echo $crm->build_url( 'admin.php' ); ?>" class="back-link-admin">← Back to Admin Dashboard</a>
            </div>
            
            <h2>Create New</h2>
            <form method="post">
                <input type="hidden" name="action" value="create_team">
                <?php if ( $current_group && $current_group !== 'team' ) : ?>
                    <input type="hidden" name="team" value="<?php echo htmlspecialchars( $current_group ); ?>">
                <?php endif; ?>
                <div class="form-group">
                    <label for="new_team_name">Name *</label>
                    <input type="text" id="new_team_name" name="new_team_name" required placeholder="e.g., Marketing Team" autofocus>
                </div>
                <div class="form-group">
                    <label for="new_team_slug">Slug *</label>
                    <input type="text" id="new_team_slug" name="new_team_slug" required placeholder="e.g., marketing" pattern="[a-z0-9_-]+" value="<?php echo ( $current_group && $current_group !== 'team' ) ? htmlspecialchars( $current_group ) : ''; ?>">
                    <small class="text-small-muted">Only lowercase letters, numbers, hyphens, and underscores allowed. This will be used as the filename.</small>
                </div>
                <div class="form-group">
                    <label for="new_team_type">Type</label>
                    <select id="new_team_type" name="new_team_type">
                        <option value="team">Team (work/business context)</option>
                        <option value="group">Group (personal/social context)</option>
                    </select>
                    <small class="text-small-muted">Choose "Group" for personal friends/acquaintances, or "Team" for work/business contexts.</small>
                </div>
                <div style="margin-top: 20px;">
                    <button type="submit" class="btn">Create</button>
                    <a href="<?php echo $crm->build_url( 'admin.php' ); ?>" class="btn btn-secondary" style="margin-left: 10px;">Cancel</a>
                </div>
            </form>
        <?php else : ?>

        <div class="nav-tabs">
            <a href="/crm/admin/<?php echo $current_group; ?>/" class="nav-tab <?php echo $active_tab === 'general' ? 'active' : ''; ?>">General</a>
            <a href="/crm/admin/<?php echo $current_group; ?>/links/" class="nav-tab <?php echo $active_tab === 'team_links' ? 'active' : ''; ?>">Links</a>
            <?php if ( $group === 'group' ) : ?>
                <a href="/crm/admin/<?php echo $current_group; ?>/members/" class="nav-tab <?php echo $active_tab === 'members' ? 'active' : ''; ?>">👥 Members (<?php echo count( $config['team_members'] ); ?>)</a>
            <?php else : ?>
            <div class="nav-dropdown">
                <span class="nav-tab nav-dropdown-trigger <?php echo in_array( $active_tab, array( 'members', 'leadership', 'consultants', 'alumni' ) ) ? 'active' : ''; ?>">
                    People (<?php echo count( $config['team_members'] ) + count( $config['leadership'] ) + count( $config['consultants'] ?? array() ) + count( $config['alumni'] ?? array() ); ?>) ▾
                </span>
                <div class="nav-dropdown-menu">
                    <a href="/crm/admin/<?php echo $current_group; ?>/members/" class="nav-dropdown-item <?php echo $active_tab === 'members' ? 'active' : ''; ?>">👥 Members (<?php echo count( $config['team_members'] ); ?>)</a>
                    <a href="/crm/admin/<?php echo $current_group; ?>/leadership/" class="nav-dropdown-item <?php echo $active_tab === 'leadership' ? 'active' : ''; ?>">👑 Leaders (<?php echo count( $config['leadership'] ); ?>)</a>
                    <a href="/crm/admin/<?php echo $current_group; ?>/consultants/" class="nav-dropdown-item <?php echo $active_tab === 'consultants' ? 'active' : ''; ?>">🤝 Consultants (<?php echo count( $config['consultants'] ?? array() ); ?>)</a>
                    <a href="/crm/admin/<?php echo $current_group; ?>/alumni/" class="nav-dropdown-item <?php echo $active_tab === 'alumni' ? 'active' : ''; ?>">🎓 Alumni (<?php echo count( $config['alumni'] ?? array() ); ?>)</a>
                </div>
            </div>
            <?php endif; ?>
            <a href="/crm/admin/<?php echo $current_group; ?>/events/" class="nav-tab <?php echo $active_tab === 'events' ? 'active' : ''; ?>">Events</a>
            <a href="/crm/admin/<?php echo $current_group; ?>/audit/" class="nav-tab <?php echo $active_tab === 'audit' ? 'active' : ''; ?>">Audit</a>
            <a href="/crm/admin/<?php echo $current_group; ?>/json/" class="nav-tab <?php echo $active_tab === 'json' ? 'active' : ''; ?>">JSON</a>
        </div>
        
        <style>
        .nav-dropdown {
            position: relative;
            display: inline-block;
            vertical-align: top;
            margin: 0;
        }
        
        .nav-dropdown-trigger {
            cursor: pointer;
            display: inline-block;
            vertical-align: top;
            margin: 0;
            line-height: inherit;
        }
        
        .nav-dropdown-menu {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            background: white;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            min-width: 180px;
            z-index: 1000;
        }
        
        .nav-dropdown:hover .nav-dropdown-menu {
            display: block;
        }
        
        .nav-dropdown-item {
            display: block;
            padding: 8px 12px;
            color: inherit;
            text-decoration: none;
            border-bottom: 1px solid #eee;
        }
        
        .nav-dropdown-item:last-child {
            border-bottom: none;
        }
        
        .nav-dropdown-item:hover {
            background-color: #f5f5f5;
        }
        
        .nav-dropdown-item.active {
            background-color: #007cba;
            color: white;
        }
        
        @media (prefers-color-scheme: dark) {
            .nav-dropdown-menu {
                background: #2c3338;
                border-color: #50575e;
            }
            
            .nav-dropdown-item:hover {
                background-color: #3c434a;
            }
            
            .nav-dropdown-item {
                border-color: #50575e;
            }
        }
        </style>

        <!-- General Settings Tab -->
        <div id="general" class="tab-content <?php echo $active_tab === 'general' ? 'active' : ''; ?>">
            <h2>General Settings</h2>
            <form method="post">
                <input type="hidden" name="action" value="save_general">
                <?php if ( $current_group !== 'team' ) : ?>
                    <input type="hidden" name="team" value="<?php echo htmlspecialchars( $current_group ); ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="team_name">Name</label>
                    <input type="text" id="team_name" name="team_name" value="<?php echo htmlspecialchars( $config['group_name'] ); ?>" required autofocus>
                </div>
                
                <div class="form-group">
                    <label for="activity_url_prefix">Activity URL Prefix</label>
                    <input type="url" id="activity_url_prefix" name="activity_url_prefix" value="<?php echo htmlspecialchars( $config['activity_url_prefix'] ); ?>">
                </div>
                
                <div class="form-group">
                    <label for="team_type">Type</label>
                    <select id="team_type" name="team_type">
                        <option value="team" <?php echo ( ! isset( $config['type'] ) || $config['type'] === 'team' ) ? 'selected' : ''; ?>>Team (work/business context)</option>
                        <option value="group" <?php echo ( isset( $config['type'] ) && $config['type'] === 'group' ) ? 'selected' : ''; ?>>Group (personal/social context)</option>
                    </select>
                    <small class="text-small-muted">Choose "Group" for personal friends/acquaintances, or "Team" for work/business contexts.</small>
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

                <?php if ( $group !== 'group' ) : ?>
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 5px; font-weight: 600;">
                        <input type="checkbox" id="not_managing_team" name="not_managing_team" value="1" <?php echo isset( $config['not_managing'] ) && $config['not_managing'] ? 'checked' : ''; ?> style="width: auto;">
                        <span>Not managing this <?php echo $group; ?></span>
                    </label>
                    <small class="text-small-muted" style="margin-left: 20px;">
                        Check this if you are not currently responsible for HR feedbacks and <?php echo $group; ?> management.
                    </small>
                </div>
                <?php endif; ?>

                
                <button type="submit" class="btn">Save General Settings</button>
            </form>
        </div>

        <!-- Links Tab -->
        <div id="team_links" class="tab-content <?php echo $active_tab === 'team_links' ? 'active' : ''; ?>">
            <h2>Links</h2>
            <p class="text-muted" style="margin-bottom: 20px;">These links will appear on the front page next to the headline.</p>

            <form method="post">
                <input type="hidden" name="action" value="save_team_links">
                <?php if ( $current_group !== 'team' ) : ?>
                    <input type="hidden" name="team" value="<?php echo htmlspecialchars( $current_group ); ?>">
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

                <button type="submit" class="btn">Save Links</button>
            </form>
        </div>

        <!-- <?php echo ucfirst( $group ); ?> Members Tab -->
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
                                        <?php if ( $current_group !== 'team' ) : ?>
                                            <input type="hidden" name="team" value="<?php echo htmlspecialchars( $current_group ); ?>">
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
                    <a href="/crm/admin/<?php echo $current_group; ?>/members/" class="back-link-admin">← Back to <?php echo ucfirst( $group ); ?> Members</a>
                </div>
                <h3>Add New <?php echo ucfirst( $group ); ?> Member</h3>
                <?php render_person_form( 'member', null, false ); ?>
            <?php endif; ?>
        </div>

        <?php if ( $group !== 'group' ) : ?>
        <!-- Leadership Tab -->
        <div id="leadership" class="tab-content <?php echo $active_tab === 'leadership' ? 'active' : ''; ?>">
            <?php if ( $is_editing_leader ) : ?>
                <h2>Edit Leadership: <?php echo htmlspecialchars( $crm->mask_name( $edit_data['name'] ?? $edit_data['username'], $privacy_mode ) ); ?></h2>
            <?php else : ?>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3 style="margin: 0;">Current Leadership</h3>
                    <?php
                    $add_params = array( 'tab' => 'leadership', 'add' => 'new' );
                    if ( $privacy_mode ) $add_params['privacy'] = '1';
                    ?>
                    <a href="<?php echo $crm->build_url( 'admin.php', $add_params ); ?>" class="btn">+ Add New Leader</a>
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
                                    <h4><?php echo htmlspecialchars( $crm->mask_name( $leader['name'], $privacy_mode ) ); ?> <small>(<?php echo htmlspecialchars( $leader['role'] ); ?>)</small></h4>
                                    <small>@<?php echo htmlspecialchars( $crm->mask_username( $username, $privacy_mode ) ); ?> • <?php echo htmlspecialchars( $leader['location'] ?? $leader['town'] ?? '' ); ?></small>
                                </div>
                                <div style="display: flex; gap: 8px;">
                                    <?php
                                    $edit_params = array( 'edit_member' => $username );
                                    if ( $privacy_mode ) $edit_params['privacy'] = '1';
                                    ?>
                                    <a href="/crm/admin/<?php echo $current_group; ?>/person/<?php echo $username; ?>/" class="btn">Edit</a>
                                    <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this leader?')">
                                        <input type="hidden" name="action" value="delete_leader">
                                        <input type="hidden" name="username" value="<?php echo htmlspecialchars( $username ); ?>">
                                        <?php if ( $current_group !== 'team' ) : ?>
                                            <input type="hidden" name="team" value="<?php echo htmlspecialchars( $current_group ); ?>">
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
                    <a href="<?php echo $crm->build_url( 'admin.php', $back_params ); ?>" class="back-link-admin">← Back to Leadership</a>
                </div>
                <h3>Add New Leader</h3>
                <?php render_person_form( 'leader', null, false ); ?>
            <?php endif; ?>
        </div>

        <!-- Consultants Tab -->
        <div id="consultants" class="tab-content <?php echo $active_tab === 'consultants' ? 'active' : ''; ?>">
            <?php if ( $is_editing_consultant ) : ?>
                <h2>Edit Consultant: <?php echo htmlspecialchars( $crm->mask_name( $edit_data['name'] ?? $edit_data['username'], $privacy_mode ) ); ?></h2>
                <?php render_person_form( 'consultants', $edit_data, $is_editing_consultant ); ?>
            <?php elseif ( isset( $_GET['add'] ) && $_GET['add'] === 'new' ) : ?>
                <div style="margin-bottom: 20px;">
                    <?php
                    $back_params = array( 'tab' => 'consultants' );
                    if ( $privacy_mode ) $back_params['privacy'] = '1';
                    ?>
                    <a href="<?php echo $crm->build_url( 'admin.php', $back_params ); ?>" class="btn">← Back to Consultants</a>
                </div>
                <h3>Add New Consultant</h3>
                <?php render_person_form( 'consultants', null, false ); ?>
            <?php else : ?>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3>Current Consultants</h3>
                    <a href="<?php echo $crm->build_url( 'admin.php', array( 'tab' => 'consultants', 'add' => 'new' ) ); ?>" class="btn-add">+ Add Consultant</a>
                </div>
                <?php if ( ! empty( $config['consultants'] ) ) : ?>
                    <div class="person-list">
                        <?php
                        // Sort consultants by name
                        $sorted_consultants = $config['consultants'];
                        uasort( $sorted_consultants, function( $a, $b ) {
                            return strcasecmp( $a['name'] ?? '', $b['name'] ?? '' );
                        });
                        ?>
                        <?php foreach ( $sorted_consultants as $username => $consultant ) : ?>
                            <div class="person-item">
                                <div class="person-info">
                                    <strong><?php echo htmlspecialchars( $crm->mask_name( $consultant['name'], $privacy_mode ) ); ?></strong>
                                    <span class="person-meta">@<?php echo htmlspecialchars( $crm->mask_username( $username, $privacy_mode ) ); ?></span>
                                    <?php if ( ! empty( $consultant['role'] ) ) : ?>
                                        <span class="person-role"><?php echo htmlspecialchars( $consultant['role'] ); ?></span>
                                    <?php endif; ?>
                                    <?php if ( ! empty( $consultant['location'] ) ) : ?>
                                        <span class="person-location"><?php echo htmlspecialchars( $consultant['location'] ); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="person-actions">
                                    <?php
                                    $edit_params = array( 'edit_member' => $username );
                                    if ( $privacy_mode ) $edit_params['privacy'] = '1';
                                    ?>
                                    <a href="/crm/admin/<?php echo $current_group; ?>/person/<?php echo $username; ?>/" class="btn">Edit</a>
                                    <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this consultant?')">
                                        <input type="hidden" name="action" value="delete_consultant">
                                        <input type="hidden" name="username" value="<?php echo htmlspecialchars( $username ); ?>">
                                        <?php if ( $current_group !== 'team' ) : ?>
                                            <input type="hidden" name="team" value="<?php echo htmlspecialchars( $current_group ); ?>">
                                        <?php endif; ?>
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
        </div>

        <!-- Alumni Tab -->
        <div id="alumni" class="tab-content <?php echo $active_tab === 'alumni' ? 'active' : ''; ?>">
            <?php if ( $is_editing_alumni ) : ?>
                <h2>Edit Alumni: <?php echo htmlspecialchars( $crm->mask_name( $edit_data['name'] ?? $edit_data['username'], $privacy_mode ) ); ?></h2>
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
                                    <h4><?php echo htmlspecialchars( $crm->mask_name( $alumni_member['name'], $privacy_mode ) ); ?> <small>(Alumni)</small></h4>
                                    <small>@<?php echo htmlspecialchars( $crm->mask_username( $username, $privacy_mode ) ); ?> • <?php echo htmlspecialchars( $alumni_member['location'] ?? $alumni_member['town'] ?? '' ); ?></small>
                                </div>
                                <div style="display: flex; gap: 8px;">
                                    <?php
                                    $edit_params = array( 'edit_member' => $username );
                                    if ( $privacy_mode ) $edit_params['privacy'] = '1';
                                    ?>
                                    <a href="/crm/admin/<?php echo $current_group; ?>/person/<?php echo $username; ?>/" class="btn">Edit</a>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="action" value="restore_from_alumni">
                                        <input type="hidden" name="username" value="<?php echo htmlspecialchars( $username ); ?>">
                                        <?php if ( $current_group !== 'team' ) : ?>
                                            <input type="hidden" name="team" value="<?php echo htmlspecialchars( $current_group ); ?>">
                                        <?php endif; ?>
                                        <?php
                                        $original_section = $alumni_member['original_section'] ?? 'team_members'; // Default to team_members for legacy data
                                        $display_name = $original_section === 'leadership' ? 'Leadership' : ucfirst( $group ) . ' Member';
                                        ?>
                                        <button type="submit" class="btn" onclick="return confirm('Are you sure you want to restore this person to their original position (<?php echo $display_name; ?>)?')">Restore to <?php echo $display_name; ?></button>
                                    </form>
                                    <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this alumni member?')">
                                        <input type="hidden" name="action" value="delete_alumni">
                                        <input type="hidden" name="username" value="<?php echo htmlspecialchars( $username ); ?>">
                                        <?php if ( $current_group !== 'team' ) : ?>
                                            <input type="hidden" name="team" value="<?php echo htmlspecialchars( $current_group ); ?>">
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
        <?php endif; ?>

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
                                    <a href="<?php echo $crm->build_url( 'admin.php', array( 'tab' => 'events', 'edit_event' => $index ) ); ?>" class="btn">Edit</a>
                                    <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this event?')">
                                        <input type="hidden" name="action" value="delete_event">
                                        <input type="hidden" name="event_index" value="<?php echo $index; ?>">
                                        <?php if ( $current_group !== 'team' ) : ?>
                                            <input type="hidden" name="team" value="<?php echo htmlspecialchars( $current_group ); ?>">
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
                <?php if ( $current_group !== 'team' ) : ?>
                    <input type="hidden" name="team" value="<?php echo htmlspecialchars( $current_group ); ?>">
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
                            <option value="team" <?php echo $is_editing_event && ($edit_data['type'] ?? '') === 'team' ? 'selected' : ''; ?>>Meetup</option>
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

            // Process all person types using unified approach
            $person_type_mappings = array(
                'team_members' => array('type_name' => ucfirst( $group ) . ' Member', 'audit_type' => 'member'),
                'leadership' => array('type_name' => 'Leadership', 'audit_type' => 'leader'),
                'consultants' => array('type_name' => 'Consultant', 'audit_type' => 'consultants'),
                'alumni' => array('type_name' => 'Alumni', 'audit_type' => 'alumni'),
            );
            
            foreach ( $person_type_mappings as $section_key => $type_info ) {
                foreach ( $config[ $section_key ] ?? array() as $username => $person ) {
                    $missing = get_missing_data_points( $person, $type_info['audit_type'], $current_group );
                    $score = get_completeness_score( $missing, $type_info['audit_type'], $current_group );
                    $audit_data[] = array(
                        'type' => $type_info['type_name'],
                        'name' => $person['name'],
                        'username' => $username,
                        'missing' => $missing,
                        'score' => $score,
                        'person' => $person
                    );
                }
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
                    <option value="Team Member"><?php echo ucfirst( $group ); ?> Members</option>
                    <option value="Leadership">Leadership</option>
                    <option value="Consultant">Consultants</option>
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
                                    <?php echo htmlspecialchars( $crm->mask_name( $item['name'], $privacy_mode ) ); ?>
                                </div>
                                <div class="text-small-muted">
                                    @<?php echo htmlspecialchars( $crm->mask_username( $item['username'], $privacy_mode ) ); ?>
                                </div>
                            </td>
                            <td class="table-cell" style="font-weight: normal; white-space: nowrap;"><?php echo htmlspecialchars( $item['type'] ); ?></td>
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
                                    <?php 
                                    $required_fields = array();
                                    $recommended_fields = array();
                                    $optional_fields = array();
                                    
                                    foreach ( $item['missing'] as $missing_item ) {
                                        if ( is_array( $missing_item ) ) {
                                            switch ( $missing_item['priority'] ) {
                                                case 'required':
                                                    $required_fields[] = $missing_item['field'];
                                                    break;
                                                case 'recommended':
                                                    $recommended_fields[] = $missing_item['field'];
                                                    break;
                                                case 'optional':
                                                    $optional_fields[] = $missing_item['field'];
                                                    break;
                                            }
                                        } else {
                                            // Backwards compatibility
                                            if ( strpos( $missing_item, 'optional' ) !== false ) {
                                                $recommended_fields[] = str_replace( ' (optional)', '', $missing_item );
                                            } else {
                                                $required_fields[] = $missing_item;
                                            }
                                        }
                                    }
                                    ?>
                                    
                                    <?php if ( ! empty( $required_fields ) ) : ?>
                                        <?php foreach ( $required_fields as $field ) : ?>
                                            <span class="link-danger" title="Required field"><?php echo htmlspecialchars( $field ); ?></span><?php echo ( $field !== end( $required_fields ) || ! empty( $recommended_fields ) || ! empty( $optional_fields ) ) ? ', ' : ''; ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    
                                    <?php if ( ! empty( $recommended_fields ) ) : ?>
                                        <?php foreach ( $recommended_fields as $field ) : ?>
                                            <span class="link-warning" title="Recommended field - likely to be filled out"><?php echo htmlspecialchars( $field ); ?></span><?php echo ( $field !== end( $recommended_fields ) || ! empty( $optional_fields ) ) ? ', ' : ''; ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    
                                    <?php if ( ! empty( $optional_fields ) ) : ?>
                                        <?php foreach ( $optional_fields as $field ) : ?>
                                            <span class="link-secondary" title="Optional field - may rightfully stay empty"><?php echo htmlspecialchars( $field ); ?></span><?php echo $field !== end( $optional_fields ) ? ', ' : ''; ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td class="table-cell" style="font-weight: normal; white-space: nowrap;">
                                <a href="/crm/admin/<?php echo $current_group; ?>/person/<?php echo $item['username']; ?>/" class="link-primary text-small" style="margin-right: 8px;">✏️ Edit</a>
                                <a href="<?php echo $crm->build_url( 'index.php', array( 'person' => $item['username'] ) ); ?>" class="link-primary text-small" target="_blank">👁️ View</a>
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
                <a href="<?php echo $crm->build_url( 'index.php' ); ?>" class="btn" target="_blank">View Dashboard</a>
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
        <a href="<?php echo $crm->build_url( 'index.php' ); ?>" class="text-muted" style="text-decoration: none;">👥 Overview</a>
    </footer>
    
    <?php
    if ( function_exists( 'wp_app_enqueue_script' ) ) {
        wp_app_enqueue_script( 'a8c-hr-cmd-k-js', plugin_dir_url( __FILE__ ) . 'assets/cmd-k.js' );
        wp_app_enqueue_script( 'a8c-hr-script-js', plugin_dir_url( __FILE__ ) . 'assets/script.js' );
    } else {
        echo '<script src="' . plugin_dir_url( __FILE__ ) . 'assets/cmd-k.js"></script>';
        echo '<script src="' . plugin_dir_url( __FILE__ ) . 'assets/script.js"></script>';
    }
	if ( function_exists( '\wp_app_body_close' ) ) \wp_app_body_close();
    ?>
    <?php $crm->init_cmd_k_js(); ?>
</body>
</html>
