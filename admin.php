<?php
/**
 * Team Management Admin Tool
 * 
 * A web interface for creating and managing team.json configuration
 */

// Error handling
ini_set( 'display_errors', 1 );
error_reporting( E_ALL );

$config_file = __DIR__ . '/team.json';
$action = $_POST['action'] ?? $_GET['action'] ?? 'dashboard';

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
		'events' => array()
	);
}

/**
 * Save configuration to JSON file
 */
function save_config( $config, $file_path ) {
	$json = json_encode( $config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	return file_put_contents( $file_path, $json ) !== false;
}

$config = load_or_create_config( $config_file );
$message = '';
$error = '';

// Handle form submissions
if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
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
			$username = sanitize_text_field( $_POST['username'] ?? '' );
			$original_username = sanitize_text_field( $_POST['original_username'] ?? '' );
			if ( empty( $username ) ) {
				$error = 'Username is required.';
				break;
			}
			
			$member = array(
				'name' => sanitize_text_field( $_POST['name'] ?? '' ),
				'github' => sanitize_text_field( $_POST['github'] ?? '' ),
				'linear' => sanitize_text_field( $_POST['linear'] ?? '' ),
				'town' => sanitize_text_field( $_POST['town'] ?? '' ),
				'one_on_one' => sanitize_url( $_POST['one_on_one'] ?? '' ),
				'hr_feedback' => sanitize_url( $_POST['hr_feedback'] ?? '' ),
				'birthday' => sanitize_text_field( $_POST['birthday'] ?? '' ),
				'company_anniversary' => sanitize_text_field( $_POST['company_anniversary'] ?? '' ),
				'kids' => $config['team_members'][ $original_username ]['kids'] ?? array(), // Preserve kids data
				'notes' => sanitize_textarea_field( $_POST['notes'] ?? '' )
			);
			
			// If username changed, remove old entry
			if ( $original_username !== $username ) {
				unset( $config['team_members'][ $original_username ] );
			}
			
			$config['team_members'][ $username ] = $member;
			if ( save_config( $config, $config_file ) ) {
				$message = 'Team member updated successfully!';
			} else {
				$error = 'Failed to update team member.';
			}
			break;
			
		case 'add_member':
			$username = sanitize_text_field( $_POST['username'] ?? '' );
			if ( empty( $username ) ) {
				$error = 'Username is required.';
				break;
			}
			
			$member = array(
				'name' => sanitize_text_field( $_POST['name'] ?? '' ),
				'github' => sanitize_text_field( $_POST['github'] ?? '' ),
				'linear' => sanitize_text_field( $_POST['linear'] ?? '' ),
				'town' => sanitize_text_field( $_POST['town'] ?? '' ),
				'one_on_one' => sanitize_url( $_POST['one_on_one'] ?? '' ),
				'hr_feedback' => sanitize_url( $_POST['hr_feedback'] ?? '' ),
				'birthday' => sanitize_text_field( $_POST['birthday'] ?? '' ),
				'company_anniversary' => sanitize_text_field( $_POST['company_anniversary'] ?? '' ),
				'kids' => array(),
				'notes' => sanitize_textarea_field( $_POST['notes'] ?? '' )
			);
			
			$config['team_members'][ $username ] = $member;
			if ( save_config( $config, $config_file ) ) {
				$message = 'Team member added successfully!';
			} else {
				$error = 'Failed to save team member.';
			}
			break;
			
		case 'edit_leader':
			$username = sanitize_text_field( $_POST['username'] ?? '' );
			$original_username = sanitize_text_field( $_POST['original_username'] ?? '' );
			if ( empty( $username ) ) {
				$error = 'Username is required.';
				break;
			}
			
			$leader = array(
				'name' => sanitize_text_field( $_POST['name'] ?? '' ),
				'github' => sanitize_text_field( $_POST['github'] ?? '' ),
				'linear' => sanitize_text_field( $_POST['linear'] ?? '' ),
				'town' => sanitize_text_field( $_POST['town'] ?? '' ),
				'role' => sanitize_text_field( $_POST['role'] ?? '' ),
				'one_on_one' => sanitize_url( $_POST['one_on_one'] ?? '' ),
				'birthday' => sanitize_text_field( $_POST['birthday'] ?? '' ),
				'company_anniversary' => sanitize_text_field( $_POST['company_anniversary'] ?? '' ),
				'kids' => $config['leadership'][ $original_username ]['kids'] ?? array(), // Preserve kids data
				'notes' => sanitize_textarea_field( $_POST['notes'] ?? '' )
			);
			
			// If username changed, remove old entry
			if ( $original_username !== $username ) {
				unset( $config['leadership'][ $original_username ] );
			}
			
			$config['leadership'][ $username ] = $leader;
			if ( save_config( $config, $config_file ) ) {
				$message = 'Leader updated successfully!';
			} else {
				$error = 'Failed to update leader.';
			}
			break;
			
		case 'add_leader':
			$username = sanitize_text_field( $_POST['username'] ?? '' );
			if ( empty( $username ) ) {
				$error = 'Username is required.';
				break;
			}
			
			$leader = array(
				'name' => sanitize_text_field( $_POST['name'] ?? '' ),
				'github' => sanitize_text_field( $_POST['github'] ?? '' ),
				'linear' => sanitize_text_field( $_POST['linear'] ?? '' ),
				'town' => sanitize_text_field( $_POST['town'] ?? '' ),
				'role' => sanitize_text_field( $_POST['role'] ?? '' ),
				'one_on_one' => sanitize_url( $_POST['one_on_one'] ?? '' ),
				'birthday' => sanitize_text_field( $_POST['birthday'] ?? '' ),
				'company_anniversary' => sanitize_text_field( $_POST['company_anniversary'] ?? '' ),
				'kids' => array(),
				'notes' => sanitize_textarea_field( $_POST['notes'] ?? '' )
			);
			
			$config['leadership'][ $username ] = $leader;
			if ( save_config( $config, $config_file ) ) {
				$message = 'Leader added successfully!';
			} else {
				$error = 'Failed to save leader.';
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
			$username = $_POST['username'] ?? '';
			if ( isset( $config['team_members'][ $username ] ) ) {
				unset( $config['team_members'][ $username ] );
				if ( save_config( $config, $config_file ) ) {
					$message = 'Team member deleted successfully!';
				} else {
					$error = 'Failed to delete team member.';
				}
			}
			break;
			
		case 'delete_leader':
			$username = $_POST['username'] ?? '';
			if ( isset( $config['leadership'][ $username ] ) ) {
				unset( $config['leadership'][ $username ] );
				if ( save_config( $config, $config_file ) ) {
					$message = 'Leader deleted successfully!';
				} else {
					$error = 'Failed to delete leader.';
				}
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
	}
}

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
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 20px;
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
        }
        .btn:hover {
            background: #005a87;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🛠️ Team Management Admin</h1>
            <p>Manage your team configuration file</p>
        </div>

        <?php if ( $message ) : ?>
            <div class="message success"><?php echo htmlspecialchars( $message ); ?></div>
        <?php endif; ?>

        <?php if ( $error ) : ?>
            <div class="message error"><?php echo htmlspecialchars( $error ); ?></div>
        <?php endif; ?>

        <div class="nav-tabs">
            <a href="#general" class="nav-tab active" onclick="showTab('general')">General Settings</a>
            <a href="#members" class="nav-tab" onclick="showTab('members')">Team Members</a>
            <a href="#leadership" class="nav-tab" onclick="showTab('leadership')">Leadership</a>
            <a href="#events" class="nav-tab" onclick="showTab('events')">Events</a>
            <a href="#json" class="nav-tab" onclick="showTab('json')">View JSON</a>
        </div>

        <!-- General Settings Tab -->
        <div id="general" class="tab-content active">
            <h2>General Settings</h2>
            <form method="post">
                <input type="hidden" name="action" value="save_general">
                
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
        <div id="members" class="tab-content">
            <h2>Team Members (<?php echo count( $config['team_members'] ); ?>)</h2>
            
            <h3 id="member-form-title">Add New Team Member</h3>
            <form method="post" id="member-form">
                <input type="hidden" id="member-action" name="action" value="add_member">
                <input type="hidden" id="original-username" name="original_username">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="username">Username *</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="name">Full Name</label>
                        <input type="text" id="name" name="name">
                    </div>
                    
                    <div class="form-group">
                        <label for="github">GitHub Username</label>
                        <input type="text" id="github" name="github">
                    </div>
                    
                    <div class="form-group">
                        <label for="linear">Linear Username</label>
                        <input type="text" id="linear" name="linear">
                    </div>
                    
                    <div class="form-group">
                        <label for="town">Location</label>
                        <input type="text" id="town" name="town">
                    </div>
                    
                    <div class="form-group">
                        <label for="birthday">Birthday (YYYY-MM-DD)</label>
                        <input type="date" id="birthday" name="birthday">
                    </div>
                    
                    <div class="form-group">
                        <label for="company_anniversary">Company Anniversary (YYYY-MM-DD)</label>
                        <input type="date" id="company_anniversary" name="company_anniversary">
                    </div>
                    
                    <div class="form-group">
                        <label for="one_on_one">1:1 Document URL</label>
                        <input type="url" id="one_on_one" name="one_on_one">
                    </div>
                    
                    <div class="form-group">
                        <label for="hr_feedback">HR Feedback URL</label>
                        <input type="url" id="hr_feedback" name="hr_feedback">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes"></textarea>
                </div>
                
                <button type="submit" class="btn" id="member-submit-btn">Add Team Member</button>
                <button type="button" class="btn" id="member-cancel-btn" onclick="cancelMemberEdit()" style="display: none; background: #6c757d; margin-left: 10px;">Cancel</button>
            </form>

            <h3>Current Team Members</h3>
            <?php if ( ! empty( $config['team_members'] ) ) : ?>
                <div class="person-list">
                    <?php foreach ( $config['team_members'] as $username => $member ) : ?>
                        <div class="person-item">
                            <div class="person-info">
                                <h4><?php echo htmlspecialchars( $member['name'] ); ?></h4>
                                <small>@<?php echo htmlspecialchars( $username ); ?> • <?php echo htmlspecialchars( $member['town'] ); ?></small>
                            </div>
                            <div style="display: flex; gap: 8px;">
                                <button type="button" class="btn" onclick="editMember('<?php echo htmlspecialchars( $username ); ?>')">Edit</button>
                                <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this team member?')">
                                    <input type="hidden" name="action" value="delete_member">
                                    <input type="hidden" name="username" value="<?php echo htmlspecialchars( $username ); ?>">
                                    <button type="submit" class="btn btn-danger">Delete</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <p>No team members added yet.</p>
            <?php endif; ?>
        </div>

        <!-- Leadership Tab -->
        <div id="leadership" class="tab-content">
            <h2>Leadership (<?php echo count( $config['leadership'] ); ?>)</h2>
            
            <h3 id="leader-form-title">Add New Leader</h3>
            <form method="post" id="leader-form">
                <input type="hidden" id="leader-action" name="action" value="add_leader">
                <input type="hidden" id="leader-original-username" name="original_username">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="leader-username">Username *</label>
                        <input type="text" id="leader-username" name="username" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="leader-name">Full Name</label>
                        <input type="text" id="leader-name" name="name">
                    </div>
                    
                    <div class="form-group">
                        <label for="leader-role">Role</label>
                        <input type="text" id="leader-role" name="role" placeholder="e.g., Team Lead, HR">
                    </div>
                    
                    <div class="form-group">
                        <label for="leader-github">GitHub Username</label>
                        <input type="text" id="leader-github" name="github">
                    </div>
                    
                    <div class="form-group">
                        <label for="leader-linear">Linear Username</label>
                        <input type="text" id="leader-linear" name="linear">
                    </div>
                    
                    <div class="form-group">
                        <label for="leader-town">Location</label>
                        <input type="text" id="leader-town" name="town">
                    </div>
                    
                    <div class="form-group">
                        <label for="leader-birthday">Birthday (YYYY-MM-DD)</label>
                        <input type="date" id="leader-birthday" name="birthday">
                    </div>
                    
                    <div class="form-group">
                        <label for="leader-anniversary">Company Anniversary (YYYY-MM-DD)</label>
                        <input type="date" id="leader-anniversary" name="company_anniversary">
                    </div>
                    
                    <div class="form-group">
                        <label for="leader-one-on-one">1:1 Document URL</label>
                        <input type="url" id="leader-one-on-one" name="one_on_one">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="leader-notes">Notes</label>
                    <textarea id="leader-notes" name="notes"></textarea>
                </div>
                
                <button type="submit" class="btn" id="leader-submit-btn">Add Leader</button>
                <button type="button" class="btn" id="leader-cancel-btn" onclick="cancelLeaderEdit()" style="display: none; background: #6c757d; margin-left: 10px;">Cancel</button>
            </form>

            <h3>Current Leadership</h3>
            <?php if ( ! empty( $config['leadership'] ) ) : ?>
                <div class="person-list">
                    <?php foreach ( $config['leadership'] as $username => $leader ) : ?>
                        <div class="person-item">
                            <div class="person-info">
                                <h4><?php echo htmlspecialchars( $leader['name'] ); ?> <small>(<?php echo htmlspecialchars( $leader['role'] ); ?>)</small></h4>
                                <small>@<?php echo htmlspecialchars( $username ); ?> • <?php echo htmlspecialchars( $leader['town'] ); ?></small>
                            </div>
                            <div style="display: flex; gap: 8px;">
                                <button type="button" class="btn" onclick="editLeader('<?php echo htmlspecialchars( $username ); ?>')">Edit</button>
                                <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this leader?')">
                                    <input type="hidden" name="action" value="delete_leader">
                                    <input type="hidden" name="username" value="<?php echo htmlspecialchars( $username ); ?>">
                                    <button type="submit" class="btn btn-danger">Delete</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <p>No leaders added yet.</p>
            <?php endif; ?>
        </div>

        <!-- Events Tab -->
        <div id="events" class="tab-content">
            <h2>Events (<?php echo count( $config['events'] ); ?>)</h2>
            
            <h3 id="event-form-title">Add New Event</h3>
            <form method="post" id="event-form">
                <input type="hidden" id="event-action" name="action" value="add_event">
                <input type="hidden" id="event-index" name="event_index">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="event-name">Event Name *</label>
                        <input type="text" id="event-name" name="event_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="event-start-date">Start Date *</label>
                        <input type="date" id="event-start-date" name="start_date" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="event-end-date">End Date</label>
                        <input type="date" id="event-end-date" name="end_date">
                    </div>
                    
                    <div class="form-group">
                        <label for="event-type">Event Type</label>
                        <select id="event-type" name="event_type" style="width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px;">
                            <option value="team">Team</option>
                            <option value="company">Company</option>
                            <option value="conference">Conference</option>
                            <option value="training">Training</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="event-location">Location</label>
                        <input type="text" id="event-location" name="location">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="event-description">Description</label>
                    <textarea id="event-description" name="description"></textarea>
                </div>
                
                <button type="submit" class="btn" id="event-submit-btn">Add Event</button>
                <button type="button" class="btn" id="event-cancel-btn" onclick="cancelEventEdit()" style="display: none; background: #6c757d; margin-left: 10px;">Cancel</button>
            </form>

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
                                <button type="button" class="btn" onclick="editEvent(<?php echo $index; ?>)">Edit</button>
                                <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this event?')">
                                    <input type="hidden" name="action" value="delete_event">
                                    <input type="hidden" name="event_index" value="<?php echo $index; ?>">
                                    <button type="submit" class="btn btn-danger">Delete</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <p>No events added yet.</p>
            <?php endif; ?>
        </div>

        <!-- JSON View Tab -->
        <div id="json" class="tab-content">
            <h2>Current JSON Configuration</h2>
            <p>This is the current contents of your team.json file:</p>
            <pre style="background: #f8f8f8; padding: 20px; border-radius: 4px; overflow-x: auto;"><?php echo htmlspecialchars( json_encode( $config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre>
            
            <p style="margin-top: 20px;">
                <a href="team.php" class="btn" target="_blank">View Team Dashboard</a>
            </p>
        </div>
    </div>

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
        
        function editMember(username) {
            const member = teamMembers[username];
            if (!member) return;
            
            document.getElementById('member-form-title').textContent = 'Edit Team Member';
            document.getElementById('member-action').value = 'edit_member';
            document.getElementById('original-username').value = username;
            document.getElementById('username').value = username;
            document.getElementById('name').value = member.name || '';
            document.getElementById('github').value = member.github || '';
            document.getElementById('linear').value = member.linear || '';
            document.getElementById('town').value = member.town || '';
            document.getElementById('birthday').value = member.birthday || '';
            document.getElementById('company_anniversary').value = member.company_anniversary || '';
            document.getElementById('one_on_one').value = member.one_on_one || '';
            document.getElementById('hr_feedback').value = member.hr_feedback || '';
            document.getElementById('notes').value = member.notes || '';
            document.getElementById('member-submit-btn').textContent = 'Update Team Member';
            document.getElementById('member-cancel-btn').style.display = 'inline-block';
            
            // Scroll to form
            document.getElementById('member-form').scrollIntoView({ behavior: 'smooth' });
        }
        
        function cancelMemberEdit() {
            document.getElementById('member-form').reset();
            document.getElementById('member-form-title').textContent = 'Add New Team Member';
            document.getElementById('member-action').value = 'add_member';
            document.getElementById('original-username').value = '';
            document.getElementById('member-submit-btn').textContent = 'Add Team Member';
            document.getElementById('member-cancel-btn').style.display = 'none';
        }
        
        function editLeader(username) {
            const leader = leadership[username];
            if (!leader) return;
            
            document.getElementById('leader-form-title').textContent = 'Edit Leader';
            document.getElementById('leader-action').value = 'edit_leader';
            document.getElementById('leader-original-username').value = username;
            document.getElementById('leader-username').value = username;
            document.getElementById('leader-name').value = leader.name || '';
            document.getElementById('leader-role').value = leader.role || '';
            document.getElementById('leader-github').value = leader.github || '';
            document.getElementById('leader-linear').value = leader.linear || '';
            document.getElementById('leader-town').value = leader.town || '';
            document.getElementById('leader-birthday').value = leader.birthday || '';
            document.getElementById('leader-anniversary').value = leader.company_anniversary || '';
            document.getElementById('leader-one-on-one').value = leader.one_on_one || '';
            document.getElementById('leader-notes').value = leader.notes || '';
            document.getElementById('leader-submit-btn').textContent = 'Update Leader';
            document.getElementById('leader-cancel-btn').style.display = 'inline-block';
            
            // Scroll to form
            document.getElementById('leader-form').scrollIntoView({ behavior: 'smooth' });
        }
        
        function cancelLeaderEdit() {
            document.getElementById('leader-form').reset();
            document.getElementById('leader-form-title').textContent = 'Add New Leader';
            document.getElementById('leader-action').value = 'add_leader';
            document.getElementById('leader-original-username').value = '';
            document.getElementById('leader-submit-btn').textContent = 'Add Leader';
            document.getElementById('leader-cancel-btn').style.display = 'none';
        }
        
        function editEvent(index) {
            const event = events[index];
            if (!event) return;
            
            document.getElementById('event-form-title').textContent = 'Edit Event';
            document.getElementById('event-action').value = 'edit_event';
            document.getElementById('event-index').value = index;
            document.getElementById('event-name').value = event.name || '';
            document.getElementById('event-start-date').value = event.start_date || '';
            document.getElementById('event-end-date').value = event.end_date || '';
            document.getElementById('event-type').value = event.type || 'team';
            document.getElementById('event-location').value = event.location || '';
            document.getElementById('event-description').value = event.description || '';
            document.getElementById('event-submit-btn').textContent = 'Update Event';
            document.getElementById('event-cancel-btn').style.display = 'inline-block';
            
            // Scroll to form
            document.getElementById('event-form').scrollIntoView({ behavior: 'smooth' });
        }
        
        function cancelEventEdit() {
            document.getElementById('event-form').reset();
            document.getElementById('event-form-title').textContent = 'Add New Event';
            document.getElementById('event-action').value = 'add_event';
            document.getElementById('event-index').value = '';
            document.getElementById('event-submit-btn').textContent = 'Add Event';
            document.getElementById('event-cancel-btn').style.display = 'none';
        }
    </script>
</body>
</html>