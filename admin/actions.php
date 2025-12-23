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
			$from_group_id = intval( $_POST['from_group_id'] ?? 0 );

			if ( empty( $username ) || empty( $from_group_id ) ) {
				$error = 'Username and group ID are required.';
				break;
			}

			// Find alumni subgroup for this team
			$parent_config = $crm->storage->get_group( $current_group );
			$parent_group_id = $parent_config->id;
			$child_groups = $crm->storage->get_child_groups( $parent_group_id );
			$alumni_group_id = null;
			foreach ( $child_groups as $child ) {
				if ( stripos( $child['slug'], 'alumni' ) !== false || stripos( $child['group_name'], 'alumni' ) !== false ) {
					$alumni_group_id = $child['id'];
					break;
				}
			}

			if ( ! $alumni_group_id ) {
				$error = 'Alumni group not found. Please create an alumni subgroup first.';
				break;
			}

			// Get person
			$person = $crm->storage->get_person( $username );
			if ( ! $person ) {
				$error = 'Person not found.';
				break;
			}

			// Remove from current group and add to alumni
			$crm->storage->remove_person_from_group( $person->id, $from_group_id );
			$crm->storage->add_person_to_group( $person->id, $alumni_group_id );

			$message = 'Person moved to alumni successfully!';
			break;

		case 'restore_from_alumni':
			$username = $_POST['username'] ?? '';
			$to_group_id = intval( $_POST['to_group_id'] ?? 0 );
			$alumni_group_id = intval( $_POST['alumni_group_id'] ?? 0 );

			if ( empty( $username ) || empty( $to_group_id ) || empty( $alumni_group_id ) ) {
				$error = 'Username, target group ID, and alumni group ID are required.';
				break;
			}

			// Get person
			$person = $crm->storage->get_person( $username );
			if ( ! $person ) {
				$error = 'Person not found.';
				break;
			}

			// Remove from alumni and add back to original group
			$crm->storage->remove_person_from_group( $person->id, $alumni_group_id );
			$crm->storage->add_person_to_group( $person->id, $to_group_id );

			$message = 'Person restored from alumni successfully!';
			break;

		case 'move_to_team':
			$username = $_POST['username'] ?? '';
			$from_group_id = intval( $_POST['from_group_id'] ?? 0 );
			$target_team_slug = $_POST['target_team'] ?? '';

			if ( empty( $target_team_slug ) || $target_team_slug === $current_group ) {
				$error = 'Please select a different team to move to.';
				break;
			}

			if ( empty( $username ) || empty( $from_group_id ) ) {
				$error = 'Username and group ID are required.';
				break;
			}

			// Get person
			$person = $crm->storage->get_person( $username );
			if ( ! $person ) {
				$error = 'Person not found.';
				break;
			}

			// Get target team
			$target_config = $crm->storage->get_group( $target_team_slug );
			if ( ! $target_config ) {
				$error = 'Target team not found.';
				break;
			}

			$target_group_id = $target_config->id;

			// Move person: remove from current group, add to target group
			$crm->storage->remove_person_from_group( $person->id, $from_group_id );
			$crm->storage->add_person_to_group( $person->id, $target_group_id );

			$redirect_url = $crm->build_url( 'person.php', array( 'person' => $username ) );
			header( 'Location: ' . $redirect_url );
			exit;
			break;

		case 'create_group':
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

			$new_slug = $crm->storage->create_group(
				$new_team_name,
				$new_team_slug,
				$new_team_type
			);

			if ( ! $new_slug ) {
				$error = 'Failed to create group. Slug may already exist.';
				break;
			}

			$message = ucfirst( $new_team_type ) . ' created successfully!';
			header( 'Location: ' . $crm->build_url( 'group.php', array( 'group' => $new_slug ) ) );
			exit;
			break;

		case 'add_note':
			$username = sanitize_text_field( $_POST['username'] ?? '' );
			$new_note = sanitize_textarea_field( $_POST['new_note'] ?? '' );

			if ( ! empty( $username ) && ! empty( $new_note ) ) {
				$person_id = $crm->storage->get_person_id( $username );

				if ( $person_id ) {
					if ( $crm->storage->add_person_note( $person_id, $new_note ) ) {
						$message = 'Note added successfully!';
						$redirect_params = array( 'person' => $username );
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
			$note_id = intval( $_POST['note_id'] ?? 0 );
			$edit_note_text = sanitize_textarea_field( $_POST['edit_note_text'] ?? '' );

			if ( ! empty( $username ) && $note_id > 0 && ! empty( $edit_note_text ) ) {
				if ( $crm->storage->update_person_note( $note_id, $edit_note_text ) ) {
					$message = 'Note updated successfully!';
					$redirect_params = array( 'person' => $username );
					if ( isset( $_POST['notes_view'] ) ) $redirect_params['notes_view'] = $_POST['notes_view'];
					header( 'Location: ' . $crm->build_url( 'person.php', $redirect_params ) );
					exit;
				} else {
					$error = 'Failed to update note.';
				}
			} else {
				$error = 'Username, note ID, and note text are required.';
			}
			break;

		case 'delete_note':
			if ( ! empty( $_POST['username'] ) && isset( $_POST['note_id'] ) ) {
				$username = sanitize_text_field( $_POST['username'] );
				$note_id = intval( $_POST['note_id'] );

				if ( $crm->storage->delete_person_note( $note_id ) ) {
					$message = 'Note deleted successfully!';
					$redirect_params = array( 'person' => $username );
					if ( isset( $_POST['notes_view'] ) ) $redirect_params['notes_view'] = $_POST['notes_view'];
					header( 'Location: ' . $crm->build_url( 'person.php', $redirect_params ) );
					exit;
				} else {
					$error = 'Failed to delete note.';
				}
			} else {
				$error = 'Username and note ID are required.';
			}
			break;

	}
}
