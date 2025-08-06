<?php
/**
 * Orbit Team Management Tool
 * 
 * A comprehensive team management dashboard for the Orbit team.
 * Provides overview of team members, 1:1 documents, HR feedback preparation, and team activities.
 */

/**
 * Person class to represent team members and leadership
 */
class Person {
	public $name;
	public $username;
	public $one_on_one;
	public $hr_feedback;
	public $role;
	public $birthday; // YYYY-MM-DD format, e.g., '1978-03-15' or MM-DD format '03-15' for backward compatibility
	public $company_anniversary; // YYYY-MM-DD format
	public $kids; // Array of arrays with 'name' and 'birth_year'
	public $notes; // Additional personal notes

	public function __construct( $name, $username = '', $one_on_one = '', $hr_feedback = '', $role = '' ) {
		$this->name = $name;
		$this->username = $username;
		$this->one_on_one = $one_on_one;
		$this->hr_feedback = $hr_feedback;
		$this->role = $role;
		$this->birthday = '';
		$this->company_anniversary = '';
		$this->kids = array();
		$this->notes = '';
	}

	/**
	 * Get upcoming events for this person
	 */
	public function get_upcoming_events() {
		$events = array();
		$current_date = new DateTime();
		$current_year = (int) $current_date->format( 'Y' );

		// Birthday
		if ( ! empty( $this->birthday ) ) {
			$birthday_date = null;
			
			// Check if it's full YYYY-MM-DD format
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $this->birthday ) ) {
				$birth_date = DateTime::createFromFormat( 'Y-m-d', $this->birthday );
				if ( $birth_date ) {
					$birthday_this_year = DateTime::createFromFormat( 'Y-m-d', $current_year . '-' . $birth_date->format( 'm-d' ) );
				}
			} elseif ( preg_match( '/^\d{2}-\d{2}$/', $this->birthday ) ) {
				// Legacy MM-DD format
				$birthday_this_year = DateTime::createFromFormat( 'Y-m-d', $current_year . '-' . $this->birthday );
			}
			
			if ( isset( $birthday_this_year ) && $birthday_this_year ) {
				if ( $birthday_this_year >= $current_date ) {
					$events[] = array(
						'type' => 'birthday',
						'date' => $birthday_this_year,
						'description' => $this->name . '\'s Birthday',
					);
				} else {
					// Next year's birthday
					$birthday_next_year = DateTime::createFromFormat( 'Y-m-d', ( $current_year + 1 ) . '-' . $birthday_this_year->format( 'm-d' ) );
					if ( $birthday_next_year ) {
						$events[] = array(
							'type' => 'birthday',
							'date' => $birthday_next_year,
							'description' => $this->name . '\'s Birthday',
						);
					}
				}
			}
		}

		// Company anniversary
		if ( ! empty( $this->company_anniversary ) ) {
			$anniversary_date = DateTime::createFromFormat( 'Y-m-d', $this->company_anniversary );
			if ( $anniversary_date ) {
				$anniversary_this_year = DateTime::createFromFormat( 'Y-m-d', $current_year . '-' . $anniversary_date->format( 'm-d' ) );
				if ( $anniversary_this_year && $anniversary_this_year >= $current_date ) {
					$years = $current_year - (int) $anniversary_date->format( 'Y' );
					$events[] = array(
						'type' => 'anniversary',
						'date' => $anniversary_this_year,
						'description' => $this->name . '\'s ' . $years . ' Year Anniversary',
					);
				}
			}
		}

		// Sort events by date
		usort( $events, function( $a, $b ) {
			return $a['date'] <=> $b['date'];
		} );

		return $events;
	}

	/**
	 * Get age of kids
	 */
	public function get_kids_ages() {
		$current_year = (int) date( 'Y' );
		$kids_with_ages = array();

		foreach ( $this->kids as $kid ) {
			if ( isset( $kid['birth_year'] ) && ! empty( $kid['birth_year'] ) ) {
				$age = $current_year - (int) $kid['birth_year'];
				$kids_with_ages[] = array(
					'name' => $kid['name'] ?? 'Child',
					'age' => $age,
					'birth_year' => $kid['birth_year'],
				);
			}
		}

		return $kids_with_ages;
	}

	/**
	 * Get person's age if full birthday is available
	 */
	public function get_age() {
		if ( empty( $this->birthday ) ) {
			return null;
		}

		// Only calculate age for full YYYY-MM-DD format
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $this->birthday ) ) {
			$birth_date = DateTime::createFromFormat( 'Y-m-d', $this->birthday );
			$current_date = new DateTime();
			
			if ( $birth_date ) {
				$age = $current_date->diff( $birth_date )->y;
				return $age;
			}
		}

		return null;
	}

	/**
	 * Get formatted birthday display
	 */
	public function get_birthday_display() {
		if ( empty( $this->birthday ) ) {
			return '';
		}

		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $this->birthday ) ) {
			// Full YYYY-MM-DD format
			$birth_date = DateTime::createFromFormat( 'Y-m-d', $this->birthday );
			if ( $birth_date ) {
				$age = $this->get_age();
				$display = $birth_date->format( 'F j, Y' );
				if ( $age !== null ) {
					$display .= ' (age ' . $age . ')';
				}
				return $display;
			}
		} elseif ( preg_match( '/^\d{2}-\d{2}$/', $this->birthday ) ) {
			// Legacy MM-DD format
			$display_date = DateTime::createFromFormat( 'm-d', $this->birthday );
			if ( $display_date ) {
				return $display_date->format( 'F j' );
			}
		}

		return $this->birthday;
	}
}

/**
 * Load team configuration from JSON file
 */
function load_team_config() {
	$config_file = __DIR__ . '/team.json';
	
	if ( ! file_exists( $config_file ) ) {
		die( 'Error: team.json file not found. Please create the configuration file from the template.' );
	}
	
	$json_content = file_get_contents( $config_file );
	$config = json_decode( $json_content, true );
	
	if ( json_last_error() !== JSON_ERROR_NONE ) {
		die( 'Error: Invalid JSON in team-config.json file: ' . json_last_error_msg() );
	}
	
	// Convert arrays to Person objects
	$team_members = array();
	foreach ( $config['team_members'] as $username => $member_data ) {
		$person = new Person( 
			$member_data['name'], 
			$username,
			$member_data['one_on_one'], 
			$member_data['hr_feedback'] ?? ''
		);
		
		// Set additional properties
		$person->birthday = $member_data['birthday'] ?? '';
		$person->company_anniversary = $member_data['company_anniversary'] ?? '';
		$person->kids = $member_data['kids'] ?? array();
		$person->notes = $member_data['notes'] ?? '';
		
		$team_members[$username] = $person;
	}
	
	$leadership = array();
	foreach ( $config['leadership'] as $username => $leader_data ) {
		$person = new Person( 
			$leader_data['name'], 
			$username,
			$leader_data['one_on_one'], 
			'', // HR feedback not applicable to leadership
			$leader_data['role'] ?? ''
		);
		
		// Set additional properties
		$person->birthday = $leader_data['birthday'] ?? '';
		$person->company_anniversary = $leader_data['company_anniversary'] ?? '';
		$person->kids = $leader_data['kids'] ?? array();
		$person->notes = $leader_data['notes'] ?? '';
		
		$leadership[$username] = $person;
	}
	
	return array(
		'activity_url_prefix' => $config['activity_url_prefix'],
        'team_name' => $config['team_name'],
		'team_members' => $team_members,
		'leadership' => $leadership,
		'events' => $config['events'] ?? array(),
	);
}

// Load team configuration
$team_data = load_team_config();

/**
 * Get all upcoming events (personal + team/company)
 */
function get_all_upcoming_events( $team_data ) {
	$all_events = array();
	$current_date = new DateTime();
	
	// Get personal events from all team members and leadership
	$all_people = array_merge( $team_data['team_members'], $team_data['leadership'] );
	foreach ( $all_people as $person ) {
		$personal_events = $person->get_upcoming_events();
		$all_events = array_merge( $all_events, $personal_events );
	}
	
	// Add team and company events
	foreach ( $team_data['events'] as $event ) {
		$start_date = DateTime::createFromFormat( 'Y-m-d', $event['start_date'] );
		if ( $start_date && $start_date >= $current_date ) {
			$end_date = DateTime::createFromFormat( 'Y-m-d', $event['end_date'] );
			$duration = '';
			if ( $end_date && $start_date->format( 'Y-m-d' ) !== $end_date->format( 'Y-m-d' ) ) {
				$duration = ' - ' . $end_date->format( 'M j' );
			}
			
			$all_events[] = array(
				'type' => $event['type'],
				'date' => $start_date,
				'description' => $event['name'] . $duration,
				'location' => $event['location'] ?? '',
				'details' => $event['description'] ?? '',
			);
		}
	}
	
	// Sort all events by date
	usort( $all_events, function( $a, $b ) {
		return $a['date'] <=> $b['date'];
	} );
	
	return $all_events;
}

// Get current action
$person = $_GET['person'] ?? null;
$action = $person ? 'person' : 'overview';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars( $team_data['team_name'] ); ?> Team Management</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f8f9fa;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .overview-layout {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            align-items: start;
        }
        @media (max-width: 768px) {
            .overview-layout {
                grid-template-columns: 1fr;
            }
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e9ecef;
        }
        .navigation {
            margin-bottom: 30px;
            text-align: center;
        }
        .nav-link {
            display: inline-block;
            margin: 0 10px;
            padding: 10px 20px;
            background: #007cba;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            transition: background 0.3s;
        }
        .nav-link:hover {
            background: #005a87;
        }
        .team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .person-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            background: #fff;
            transition: box-shadow 0.3s;
        }
        .person-card:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .person-card h3 {
            margin-top: 0;
            color: #333;
        }
        .links {
            margin-top: 15px;
        }
        .links a {
            display: inline-block;
            margin: 5px 10px 5px 0;
            padding: 8px 15px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            color: #495057;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
        }
        .links a:hover {
            background: #e9ecef;
        }
        .section {
            margin-bottom: 30px;
        }
        .section h2 {
            border-bottom: 2px solid #007cba;
            padding-bottom: 10px;
            color: #333;
        }
        .back-link {
            margin-bottom: 20px;
        }
        .back-link a {
            color: #007cba;
            text-decoration: none;
        }
        .back-link a:hover {
            text-decoration: underline;
        }
        .hr-feedback-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
        .activity-link {
            background: #28a745;
            color: white;
            padding: 10px 20px;
            border-radius: 4px;
            text-decoration: none;
            display: inline-block;
            margin-top: 10px;
        }
        .activity-link:hover {
            background: #218838;
        }
        .people-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .people-list li {
            padding: 12px 0;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .people-list li:last-child {
            border-bottom: none;
        }
        .person-info {
            flex-grow: 1;
        }
        .person-name {
            font-weight: 600;
            color: #333;
            margin: 0 0 4px 0;
        }
        .person-username {
            color: #666;
            font-size: 14px;
            margin: 0;
        }
        .person-links {
            display: flex;
            gap: 8px;
        }
        .person-links a {
            padding: 6px 12px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            color: #495057;
            text-decoration: none;
            border-radius: 4px;
            font-size: 13px;
            transition: background 0.2s;
        }
        .person-links a:hover {
            background: #e9ecef;
        }
        .events-sidebar {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            position: sticky;
            top: 20px;
        }
        .events-sidebar h3 {
            margin-top: 0;
            color: #333;
            border-bottom: 2px solid #007cba;
            padding-bottom: 8px;
        }
        .event-item {
            padding: 12px 0;
            border-bottom: 1px solid #dee2e6;
        }
        .event-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }
        .event-date {
            font-weight: 600;
            color: #007cba;
            font-size: 14px;
        }
        .event-description {
            margin: 4px 0 0 0;
            color: #333;
            font-size: 14px;
        }
        .event-type {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            margin-top: 4px;
        }
        .event-type.birthday {
            background: #ffe6e6;
            color: #d63384;
        }
        .event-type.anniversary {
            background: #e6f3ff;
            color: #0066cc;
        }
        .event-type.team {
            background: #e6ffe6;
            color: #28a745;
        }
        .event-type.company {
            background: #fff3e6;
            color: #fd7e14;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ( $action === 'overview' ) : ?>
            <div class="header">
                <h1><?php echo htmlspecialchars( $team_data['team_name'] ); ?> Team Overview</h1>
            </div>

            <div class="overview-layout">
                <div class="people-section">
                    <div class="section">
                        <h2>Team Members (<?php echo count( $team_data['team_members'] ); ?>)</h2>
                        <ul class="people-list">
                            <?php foreach ( $team_data['team_members'] as $username => $member ) : ?>
                                <li>
                                    <div class="person-info">
                                        <div class="person-name"><?php echo htmlspecialchars( $member->name ); ?></div>
                                        <div class="person-username">@<?php echo htmlspecialchars( $username ); ?></div>
                                    </div>
                                    <div class="person-links">
                                        <a href="?person=<?php echo htmlspecialchars( $username ); ?>">Details</a>
                                        <a href="<?php echo htmlspecialchars( $member->one_on_one ); ?>" target="_blank">1:1</a>
                                        <a href="<?php echo htmlspecialchars( $member->hr_feedback ); ?>" target="_blank">HR</a>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <div class="section">
                        <h2>Leadership</h2>
                        <ul class="people-list">
                            <?php foreach ( $team_data['leadership'] as $username => $leader ) : ?>
                                <li>
                                    <div class="person-info">
                                        <div class="person-name"><?php echo htmlspecialchars( $leader->name ); ?> <span style="color: #666; font-weight: normal;">(<?php echo htmlspecialchars( $leader->role ); ?>)</span></div>
                                        <div class="person-username">@<?php echo htmlspecialchars( $username ); ?></div>
                                    </div>
                                    <div class="person-links">
                                        <a href="?person=<?php echo htmlspecialchars( $username ); ?>">Details</a>
                                        <a href="<?php echo htmlspecialchars( $leader->one_on_one ); ?>" target="_blank">1:1</a>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>

                <div class="events-sidebar">
                    <h3>🗓️ Upcoming Events</h3>
                    <?php
                    $upcoming_events = get_all_upcoming_events( $team_data );
                    if ( ! empty( $upcoming_events ) ) :
                        $displayed_events = array_slice( $upcoming_events, 0, 10 ); // Show first 10 events
                        foreach ( $displayed_events as $event ) :
                    ?>
                        <div class="event-item">
                            <div class="event-date"><?php echo $event['date']->format( 'M j, Y' ); ?></div>
                            <div class="event-description"><?php echo htmlspecialchars( $event['description'] ); ?></div>
                            <span class="event-type <?php echo htmlspecialchars( $event['type'] ); ?>"><?php echo ucfirst( $event['type'] ); ?></span>
                            <?php if ( ! empty( $event['location'] ) ) : ?>
                                <div style="font-size: 12px; color: #666; margin-top: 4px;">📍 <?php echo htmlspecialchars( $event['location'] ); ?></div>
                            <?php endif; ?>
                        </div>
                    <?php 
                        endforeach;
                    else : 
                    ?>
                        <p style="color: #666; font-style: italic; margin: 0;">No upcoming events</p>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ( $action === 'person' && ! empty( $person ) ) : ?>
            <?php
            $person_data = null;
            $is_team_member = false;
            
            if ( isset( $team_data['team_members'][ $person ] ) ) {
                $person_data = $team_data['team_members'][ $person ];
                $is_team_member = true;
            } elseif ( isset( $team_data['leadership'][ $person ] ) ) {
                $person_data = $team_data['leadership'][ $person ];
            }
            
            if ( ! $person_data ) {
                echo '<p>Person not found.</p>';
                echo '<a href="?">← Back to Overview</a>';
            } else {
            ?>
                <div class="back-link">
                    <a href="?">← Back to Team Overview</a>
                </div>

                <div class="header">
                    <h1><?php echo htmlspecialchars( $person_data->name ); ?></h1>
                    <p style="color: #666;">@<?php echo htmlspecialchars( $person_data->username ); ?></p>
                    <?php if ( ! empty( $person_data->role ) ) : ?>
                        <p><strong><?php echo htmlspecialchars( $person_data->role ); ?></strong></p>
                    <?php endif; ?>
                </div>

                <div class="section">
                    <h2>Quick Links</h2>
                    <div class="links">
                        <a href="<?php echo htmlspecialchars( $person_data->one_on_one ); ?>" target="_blank">📄 1:1 Document</a>
                        <?php if ( $is_team_member ) : ?>
                            <a href="<?php echo htmlspecialchars( $person_data->hr_feedback ); ?>" target="_blank">📋 HR Feedback Template</a>
                            <?php if ( ! empty( $person_data->username ) ) : ?>
                                <?php
                                $last_month = date( 'Y-m', strtotime( 'last month') );
                                $start_date = $last_month . '-01';
                                $end_date = date( 'Y-m-t', strtotime( $start_date ) );
                                $activity_url = $team_data['activity_url_prefix'] . '&member=' . urlencode( $person_data->username ) . "&start={$start_date}&end={$end_date}";
                                ?>
                                <a href="<?php echo htmlspecialchars( $activity_url ); ?>" target="_blank" class="activity-link">📊 Team Activity (Last Month)</a>
                            <?php else : ?>
                                <span style="color: #666; font-style: italic;">Team Activity (Username not configured)</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <?php
                // Get upcoming events and personal details
                $upcoming_events = $person_data->get_upcoming_events();
                $kids_with_ages = $person_data->get_kids_ages();
                ?>

                <?php if ( ! empty( $upcoming_events ) || ! empty( $person_data->birthday ) || ! empty( $person_data->company_anniversary ) || ! empty( $kids_with_ages ) || ! empty( $person_data->notes ) ) : ?>
                    <div class="section">
                        <h2>Personal Details</h2>
                        
                        <?php if ( ! empty( $upcoming_events ) ) : ?>
                            <div style="background: #e8f5e8; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                                <h3 style="margin-top: 0; color: #2d5a2d;">🗓️ Upcoming Events</h3>
                                <ul style="margin-bottom: 0;">
                                    <?php foreach ( $upcoming_events as $event ) : ?>
                                        <li><?php echo htmlspecialchars( $event['description'] ); ?> - <?php echo $event['date']->format( 'F j, Y' ); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if ( ! empty( $person_data->birthday ) ) : ?>
                            <p><strong>🎂 Birthday:</strong> <?php echo htmlspecialchars( $person_data->get_birthday_display() ); ?></p>
                        <?php endif; ?>

                        <?php if ( ! empty( $person_data->company_anniversary ) ) : ?>
                            <?php
                            $anniversary_date = DateTime::createFromFormat( 'Y-m-d', $person_data->company_anniversary );
                            if ( $anniversary_date ) {
                                $years_at_company = (int) date( 'Y' ) - (int) $anniversary_date->format( 'Y' );
                                echo '<p><strong>🏢 Company Anniversary:</strong> ' . htmlspecialchars( $anniversary_date->format( 'F j, Y' ) ) . ' (' . $years_at_company . ' years)</p>';
                            }
                            ?>
                        <?php endif; ?>

                        <?php if ( ! empty( $kids_with_ages ) ) : ?>
                            <div style="margin: 15px 0;">
                                <strong>👨‍👩‍👧‍👦 Family:</strong>
                                <ul style="margin: 5px 0 0 20px;">
                                    <?php foreach ( $kids_with_ages as $kid ) : ?>
                                        <li><?php echo htmlspecialchars( $kid['name'] ); ?> (<?php echo $kid['age']; ?> years old, born <?php echo $kid['birth_year']; ?>)</li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if ( ! empty( $person_data->notes ) ) : ?>
                            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 15px;">
                                <strong>📝 Notes:</strong>
                                <p style="margin: 10px 0 0 0;"><?php echo nl2br( htmlspecialchars( $person_data->notes ) ); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ( $is_team_member ) : ?>
                    <div class="hr-feedback-section">
                        <h2>HR Feedback Preparation</h2>
                        <p>Use this section to prepare monthly HR feedback for <?php echo htmlspecialchars( $person_data->name ); ?>.</p>
                        
                        <h3>Quick Checklist:</h3>
                        <ul>
                            <li>Review recent 1:1 meeting notes</li>
                            <li>Check team activity metrics for the month</li>
                            <li>Identify key achievements and contributions</li>
                            <li>Note any areas for growth or development</li>
                            <li>Review goal progress and alignment</li>
                        </ul>

                        <h3>Resources:</h3>
                        <div class="links">
                            <a href="<?php echo htmlspecialchars( $person_data->one_on_one ); ?>" target="_blank">Recent 1:1 Notes</a>
                            <a href="<?php echo htmlspecialchars( $person_data->hr_feedback ); ?>" target="_blank">HR Feedback Template</a>
                            <?php if ( ! empty( $person_data->username ) ) : ?>
                                <?php
                                $prev_month = date( 'Y-m', strtotime( '-1 month' ) );
                                $prev_start = $prev_month . '-01';
                                $prev_end = date( 'Y-m-t', strtotime( $prev_start ) );
                                $prev_activity_url = $team_data['activity_url_prefix'] . '&member=' . urlencode( $person_data->username ) . "&start={$prev_start}&end={$prev_end}";
                                ?>
                                <a href="<?php echo htmlspecialchars( $prev_activity_url ); ?>" target="_blank">Previous Month Activity</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

            <?php } ?>

        <?php else : ?>
            <div class="header">
                <h1>Page Not Found</h1>
                <p><a href="?">← Back to Team Overview</a></p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>