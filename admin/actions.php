<?php
namespace PersonalCRM;

/**
 * Global POST action handlers
 * Tab-specific handlers are now in their respective tab files
 */

if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
	$crm = PersonalCrm::get_instance();
	$config = $crm->storage->get_group( $current_group );

	switch ( $action ) {
		case 'move_to_alumni':
			$username = $_POST['username'] ?? '';
			$from_section = $_POST['from_section'] ?? '';

			if ( $from_section === 'team_members' && isset( $config['team_members'][ $username ] ) ) {
				$person_data = $config['team_members'][ $username ];
				$person_data['original_section'] = 'team_members';
				unset( $config['team_members'][ $username ] );
				$config['alumni'][ $username ] = $person_data;
			} elseif ( $from_section === 'leadership' && isset( $config['leadership'][ $username ] ) ) {
				$person_data = $config['leadership'][ $username ];
				$person_data['original_section'] = 'leadership';
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
				$target_section = $person_data['original_section'] ?? $to_section ?? 'team_members';
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

			$person_data = null;
			if ( $from_section === 'team_members' && isset( $config['team_members'][ $username ] ) ) {
				$person_data = $config['team_members'][ $username ];
				unset( $config['team_members'][ $username ] );
			} elseif ( $from_section === 'leadership' && isset( $config['leadership'][ $username ] ) ) {
				$person_data = $config['leadership'][ $username ];
				unset( $config['leadership'][ $username ] );
			}

			if ( $person_data ) {
				$target_config = $crm->storage->get_group( $target_team );
				$target_config['team_members'][ $username ] = $person_data;
				$has_members = ! empty( $config['team_members'] ) || ! empty( $config['leadership'] );
				$current_saved = $crm->storage->save_group( $current_group, $config );
				$target_saved = $crm->storage->save_group( $target_team, $target_config );

				if ( $current_saved && $target_saved ) {
					$redirect_url = $crm->build_url( 'index.php', array( 'team' => $target_team, 'person' => $username ) );
					header( 'Location: ' . $redirect_url );
					exit;
				} else {
					$error = 'Failed to move person to target team.';
				}
			} else {
				$error = 'Person not found in current team.';
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

			if ( ! preg_match( '/^[a-z0-9_-]+$/', $new_team_slug ) ) {
				$error = 'Slug can only contain lowercase letters, numbers, hyphens and underscores.';
				break;
			}

			$existing_groups = $crm->storage->get_available_groups();
			if ( in_array( $new_team_slug, $existing_groups, true ) ) {
				$error = 'A ' . $new_team_type . ' with this slug already exists.';
				break;
			}

			$new_config = array(
				'activity_url_prefix' => '',
				'group_name' => $new_team_name,
				'type' => $new_team_type,
				'team_members' => array(),
				'leadership' => array(),
				'alumni' => array(),
				'events' => array()
			);

			if ( empty( $existing_groups ) ) {
				$new_config['default'] = true;
			}

			if ( $crm->storage->save_group( $new_team_slug, $new_config ) ) {
				$message = ucfirst( $new_team_type ) . ' created successfully!';
				$redirect_url = 'admin/index.php' . ( $new_team_slug !== 'team' ? '?team=' . urlencode( $new_team_slug ) : '' );
				header( 'Location: ' . $redirect_url );
				exit;
			} else {
				$error = 'Failed to create team.';
			}
			break;

		case 'add_note':
			$username = sanitize_text_field( $_POST['username'] ?? '' );
			$group_slug = sanitize_text_field( $_POST['group'] ?? $current_group );
			$new_note = sanitize_textarea_field( $_POST['new_note'] ?? '' );

			if ( ! empty( $username ) && ! empty( $new_note ) && ! empty( $group_slug ) ) {
				$person_data = $crm->storage->get_person( $group_slug, $username );

				if ( $person_data ) {
					if ( ! isset( $person_data['notes'] ) || ! is_array( $person_data['notes'] ) ) {
						$person_data['notes'] = array();
					}

					$person_data['notes'][] = array(
						'date' => date( 'Y-m-d H:i' ),
						'text' => $new_note
					);

					$config = $crm->storage->get_group( $group_slug );
					$category = null;
					foreach ( array( 'team_members', 'leadership', 'consultants', 'alumni' ) as $type ) {
						if ( isset( $config[$type][$username] ) ) {
							$category = $type;
							break;
						}
					}

					if ( $category ) {
						if ( $crm->storage->save_person( $group_slug, $username, $category, $person_data ) ) {
							$message = 'Note added successfully!';
							$redirect_params = array( 'person' => $username );
							if ( isset( $_POST['privacy'] ) ) $redirect_params['privacy'] = '1';
							if ( isset( $_POST['notes_view'] ) ) $redirect_params['notes_view'] = $_POST['notes_view'];
							header( 'Location: ' . $crm->build_url( 'person.php', $redirect_params ) );
							exit;
						} else {
							$error = 'Failed to save note.';
						}
					} else {
						$error = 'Could not determine person category.';
					}
				} else {
					$error = 'Person not found.';
				}
			} else {
				$error = 'Username, group, and note are required.';
			}
			break;

		case 'edit_note':
			$username = sanitize_text_field( $_POST['username'] ?? '' );
			$group_slug = sanitize_text_field( $_POST['group'] ?? $current_group );
			$note_index = intval( $_POST['note_index'] ?? -1 );
			$edit_note_text = sanitize_textarea_field( $_POST['edit_note_text'] ?? '' );

			if ( ! empty( $username ) && $note_index >= 0 && ! empty( $edit_note_text ) && ! empty( $group_slug ) ) {
				$person_data = $crm->storage->get_person( $group_slug, $username );

				if ( $person_data && isset( $person_data['notes'] ) && is_array( $person_data['notes'] ) && isset( $person_data['notes'][$note_index] ) ) {
					$person_data['notes'][$note_index]['text'] = $edit_note_text;

					$config = $crm->storage->get_group( $group_slug );
					$category = null;
					foreach ( array( 'team_members', 'leadership', 'consultants', 'alumni' ) as $type ) {
						if ( isset( $config[$type][$username] ) ) {
							$category = $type;
							break;
						}
					}

					if ( $category ) {
						if ( $crm->storage->save_person( $group_slug, $username, $category, $person_data ) ) {
							$message = 'Note updated successfully!';
							$redirect_params = array( 'person' => $username );
							if ( isset( $_POST['privacy'] ) ) $redirect_params['privacy'] = '1';
							if ( isset( $_POST['notes_view'] ) ) $redirect_params['notes_view'] = $_POST['notes_view'];
							header( 'Location: ' . $crm->build_url( 'person.php', $redirect_params ) );
							exit;
						} else {
							$error = 'Failed to save note.';
						}
					} else {
						$error = 'Could not determine person category.';
					}
				} else {
					$error = 'Person or note not found.';
				}
			} else {
				$error = 'Username, group, note index, and note text are required.';
			}
			break;

		case 'delete_note':
			if ( ! empty( $_POST['username'] ) && isset( $_POST['note_index'] ) ) {
				$username = sanitize_text_field( $_POST['username'] );
				$group_slug = sanitize_text_field( $_POST['group'] ?? $current_group );
				$note_index = intval( $_POST['note_index'] );

				$person_data = $crm->storage->get_person( $group_slug, $username );
				if ( $person_data && isset( $person_data['notes'][ $note_index ] ) ) {
					array_splice( $person_data['notes'], $note_index, 1 );

					$category = null;
					$config = $crm->storage->get_group( $group_slug );
					if ( isset( $config['team_members'][ $username ] ) ) {
						$category = 'team_members';
					} elseif ( isset( $config['leadership'][ $username ] ) ) {
						$category = 'leadership';
					} elseif ( isset( $config['consultants'][ $username ] ) ) {
						$category = 'consultants';
					} elseif ( isset( $config['alumni'][ $username ] ) ) {
						$category = 'alumni';
					}

					if ( $category ) {
						if ( $crm->storage->save_person( $group_slug, $username, $category, $person_data ) ) {
							header( 'Location: ' . $crm->build_url( 'person.php', array(
								'group' => $group_slug,
								'person' => $username,
								'privacy' => $privacy_mode ? '1' : '0',
							) ) );
							exit;
						} else {
							$error = 'Failed to delete note.';
						}
					} else {
						$error = 'Could not determine person category.';
					}
				} else {
					$error = 'Person or note not found.';
				}
			} else {
				$error = 'Username, group, and note index are required.';
			}
			break;

	}
}
