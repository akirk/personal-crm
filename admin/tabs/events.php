<?php
/**
 * Events Tab
 * Contains events list, form, and POST handlers
 */
namespace PersonalCRM;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPINC' ) ) {
	exit;
}

/**
 * Handle POST requests for events
 */
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['action'] ) ) {
	$crm = PersonalCrm::get_instance();
	$action = $_POST['action'];

	if ( $action === 'add_event' ) {
		$config = $crm->storage->get_group( $current_group );

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
			$config = $crm->storage->get_group( $current_group );
		} else {
			$error = 'Failed to save event.';
		}
	} elseif ( $action === 'edit_event' ) {
		$config = $crm->storage->get_group( $current_group );
		$event_index = (int) ( $_POST['event_index'] ?? -1 );

		if ( $event_index < 0 || ! isset( $config['events'][ $event_index ] ) ) {
			$error = 'Invalid event.';
		} else {
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
				$edit_data = $config['events'][ $event_index ];
				$edit_data['event_index'] = $event_index;
			} else {
				$error = 'Failed to update event.';
			}
		}
	} elseif ( $action === 'delete_event' ) {
		$config = $crm->storage->get_group( $current_group );
		$event_index = (int) ( $_POST['event_index'] ?? -1 );

		if ( $event_index >= 0 && isset( $config['events'][ $event_index ] ) ) {
			array_splice( $config['events'], $event_index, 1 );
			if ( $crm->storage->save_group( $current_group, $config ) ) {
				$message = 'Event deleted successfully!';
			} else {
				$error = 'Failed to delete event.';
			}
		}
	}
}
?>
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
                                    <a href="<?php echo $crm->build_url( 'admin/index.php', array( 'tab' => 'events', 'edit_event' => $index ) ); ?>" class="btn">Edit</a>
                                    <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this event?')">
                                        <input type="hidden" name="action" value="delete_event">
                                        <input type="hidden" name="event_index" value="<?php echo $index; ?>">
                                        <input type="hidden" name="group" value="<?php echo htmlspecialchars( $current_group ); ?>">
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
                <input type="hidden" name="group" value="<?php echo htmlspecialchars( $current_group ); ?>">
                
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
