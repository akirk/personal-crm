<?php
/**
 * Orbit Team Management Tool
 * 
 * A comprehensive team management dashboard for the Orbit team.
 * Provides overview of team members, 1:1 documents, and team activities.
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
	public $partner; // Partner/spouse name
	public $kids; // Array of arrays with 'name' and 'birth_year'
	public $notes; // Additional personal notes
	public $location; // Location/town
	public $timezone; // Timezone identifier (e.g., "America/New_York")

	public function __construct( $name, $username = '', $one_on_one = '', $hr_feedback = '', $role = '' ) {
		$this->name = $name;
		$this->username = $username;
		$this->one_on_one = $one_on_one;
		$this->hr_feedback = $hr_feedback;
		$this->role = $role;
		$this->birthday = '';
		$this->company_anniversary = '';
		$this->partner = '';
		$this->kids = array();
		$this->notes = '';
		$this->location = '';
		$this->timezone = '';
	}

	/**
	 * Get upcoming events for this person (within next 3 months)
	 */
	public function get_upcoming_events() {
		$events = array();
		$current_date = new DateTime();
		$current_year = (int) $current_date->format( 'Y' );
		$cutoff_date = clone $current_date;
		$cutoff_date->add( new DateInterval( 'P3M' ) ); // 3 months from now

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
				if ( $birthday_this_year >= $current_date && $birthday_this_year <= $cutoff_date ) {
					$events[] = array(
						'type' => 'birthday',
						'date' => $birthday_this_year,
						'description' => $this->name . '\'s Birthday',
					);
				} elseif ( $birthday_this_year < $current_date ) {
					// Check next year's birthday
					$birthday_next_year = DateTime::createFromFormat( 'Y-m-d', ( $current_year + 1 ) . '-' . $birthday_this_year->format( 'm-d' ) );
					if ( $birthday_next_year && $birthday_next_year <= $cutoff_date ) {
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
				if ( $anniversary_this_year && $anniversary_this_year >= $current_date && $anniversary_this_year <= $cutoff_date ) {
					$years = $current_year - (int) $anniversary_date->format( 'Y' );
					$events[] = array(
						'type' => 'anniversary',
						'date' => $anniversary_this_year,
						'description' => $this->name . '\'s ' . $years . ' Year Anniversary',
					);
				}
			}
		}

		// Kids' birthdays
		if ( ! empty( $this->kids ) && is_array( $this->kids ) ) {
			foreach ( $this->kids as $kid ) {
				if ( ! empty( $kid['birthday'] ) ) {
					// Full birthday date available
					$kid_birth_date = DateTime::createFromFormat( 'Y-m-d', $kid['birthday'] );
					if ( $kid_birth_date ) {
						$kid_birthday_this_year = DateTime::createFromFormat( 'Y-m-d', $current_year . '-' . $kid_birth_date->format( 'm-d' ) );
						if ( $kid_birthday_this_year >= $current_date && $kid_birthday_this_year <= $cutoff_date ) {
							$age = $current_year - (int) $kid_birth_date->format( 'Y' );
							$events[] = array(
								'type' => 'birthday',
								'date' => $kid_birthday_this_year,
								'description' => $kid['name'] . '\'s ' . $age . 'th Birthday (' . $this->name . '\'s kid)',
							);
						} elseif ( $kid_birthday_this_year < $current_date ) {
							// Check next year's birthday
							$kid_birthday_next_year = DateTime::createFromFormat( 'Y-m-d', ( $current_year + 1 ) . '-' . $kid_birth_date->format( 'm-d' ) );
							if ( $kid_birthday_next_year && $kid_birthday_next_year <= $cutoff_date ) {
								$age = ( $current_year + 1 ) - (int) $kid_birth_date->format( 'Y' );
								$events[] = array(
									'type' => 'birthday',
									'date' => $kid_birthday_next_year,
									'description' => $kid['name'] . '\'s ' . $age . 'th Birthday (' . $this->name . '\'s kid)',
								);
							}
						}
					}
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
	 * Get age of kids with enhanced display
	 */
	public function get_kids_ages() {
		$current_date = new DateTime();
		$current_year = (int) $current_date->format( 'Y' );
		$kids_with_ages = array();

		foreach ( $this->kids as $kid ) {
			if ( ! empty( $kid['birthday'] ) ) {
				// Full birthday available
				$birth_date = DateTime::createFromFormat( 'Y-m-d', $kid['birthday'] );
				if ( $birth_date ) {
					$age = $current_date->diff( $birth_date )->y;
					$next_birthday = DateTime::createFromFormat( 'Y-m-d', $current_year . '-' . $birth_date->format( 'm-d' ) );
					if ( $next_birthday && $next_birthday < $current_date ) {
						$next_birthday = DateTime::createFromFormat( 'Y-m-d', ( $current_year + 1 ) . '-' . $birth_date->format( 'm-d' ) );
					}

					$time_info = '';
					if ( $next_birthday ) {
						$time_info = $this->get_time_until_date( $current_date, $next_birthday );
					}

					$kids_with_ages[] = array(
						'name' => $kid['name'] ?? 'Child',
						'age' => $age,
						'birthday' => $kid['birthday'],
						'birthday_display' => $birth_date->format( 'F j, Y' ),
						'time_to_birthday' => $time_info,
					);
				}
			} elseif ( isset( $kid['birth_year'] ) && ! empty( $kid['birth_year'] ) ) {
				// Only birth year available
				$age = $current_year - (int) $kid['birth_year'];
				$kids_with_ages[] = array(
					'name' => $kid['name'] ?? 'Child',
					'age' => $age,
					'birth_year' => $kid['birth_year'],
				);
			} else {
				// No birth info
				$kids_with_ages[] = array(
					'name' => $kid['name'] ?? 'Child',
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
	 * Get formatted birthday display with time-to-birthday info
	 */
	public function get_birthday_display() {
		if ( empty( $this->birthday ) ) {
			return '';
		}

		$current_date = new DateTime();
		$current_year = (int) $current_date->format( 'Y' );

		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $this->birthday ) ) {
			// Full YYYY-MM-DD format
			$birth_date = DateTime::createFromFormat( 'Y-m-d', $this->birthday );
			if ( $birth_date ) {
				$age = $this->get_age();
				$display = $birth_date->format( 'F j, Y' );
				if ( $age !== null ) {
					$display .= ' (age ' . $age . ')';
				}

				// Add time to next birthday
				$next_birthday = DateTime::createFromFormat( 'Y-m-d', $current_year . '-' . $birth_date->format( 'm-d' ) );
				if ( $next_birthday && $next_birthday < $current_date ) {
					$next_birthday = DateTime::createFromFormat( 'Y-m-d', ( $current_year + 1 ) . '-' . $birth_date->format( 'm-d' ) );
				}

				if ( $next_birthday ) {
					$time_info = $this->get_time_until_date( $current_date, $next_birthday );
					if ( $time_info ) {
						$display .= ' • ' . $time_info;
					}
				}

				return $display;
			}
		} elseif ( preg_match( '/^\d{2}-\d{2}$/', $this->birthday ) ) {
			// Legacy MM-DD format
			$display_date = DateTime::createFromFormat( 'm-d', $this->birthday );
			if ( $display_date ) {
				$display = $display_date->format( 'F j' );

				// Add time to next birthday
				$next_birthday = DateTime::createFromFormat( 'Y-m-d', $current_year . '-' . $this->birthday );
				if ( $next_birthday && $next_birthday < $current_date ) {
					$next_birthday = DateTime::createFromFormat( 'Y-m-d', ( $current_year + 1 ) . '-' . $this->birthday );
				}

				if ( $next_birthday ) {
					$time_info = $this->get_time_until_date( $current_date, $next_birthday );
					if ( $time_info ) {
						$display .= ' • ' . $time_info;
					}
				}

				return $display;
			}
		}

		return $this->birthday;
	}

	/**
	 * Get human-readable time until a date
	 */
	private function get_time_until_date( $from_date, $to_date ) {
		$diff = $from_date->diff( $to_date );

		if ( $diff->days <= 0 ) {
			return 'today';
		} elseif ( $diff->days == 1 ) {
			return 'tomorrow';
		} elseif ( $diff->days <= 7 ) {
			return 'in ' . $diff->days . ' days';
		} elseif ( $diff->days <= 30 ) {
			$weeks = floor( $diff->days / 7 );
			if ( $weeks == 1 ) {
				return 'in 1 week';
			} else {
				return 'in ' . $weeks . ' weeks';
			}
		} else {
			$months = $diff->m + ( $diff->y * 12 );
			if ( $months == 1 ) {
				return 'in 1 month';
			} else {
				return 'in ' . $months . ' months';
			}
		}
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
		$person->role = $member_data['role'] ?? '';
		$person->birthday = $member_data['birthday'] ?? '';
		$person->company_anniversary = $member_data['company_anniversary'] ?? '';
		$person->partner = $member_data['partner'] ?? '';
		$person->kids = $member_data['kids'] ?? array();
		$person->notes = $member_data['notes'] ?? '';
		$person->location = $member_data['location'] ?? $member_data['town'] ?? ''; // Support both 'location' and legacy 'town'
		$person->timezone = $member_data['timezone'] ?? '';
		
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
		$person->partner = $leader_data['partner'] ?? '';
		$person->kids = $leader_data['kids'] ?? array();
		$person->notes = $leader_data['notes'] ?? '';
		$person->location = $leader_data['location'] ?? $leader_data['town'] ?? ''; // Support both 'location' and legacy 'town'
		$person->timezone = $leader_data['timezone'] ?? '';
		
			$leadership[$username] = $person;
	}
	
	$alumni = array();
	foreach ( $config['alumni'] ?? array() as $username => $alumni_data ) {
		$person = new Person( 
			$alumni_data['name'], 
			$username,
			$alumni_data['one_on_one'] ?? '', 
			$alumni_data['hr_feedback'] ?? ''
		);
		
		// Set additional properties
		$person->role = $alumni_data['role'] ?? '';
		$person->birthday = $alumni_data['birthday'] ?? '';
		$person->company_anniversary = $alumni_data['company_anniversary'] ?? '';
		$person->partner = $alumni_data['partner'] ?? '';
		$person->kids = $alumni_data['kids'] ?? array();
		$person->notes = $alumni_data['notes'] ?? '';
		$person->location = $alumni_data['location'] ?? $alumni_data['town'] ?? '';
		$person->timezone = $alumni_data['timezone'] ?? '';
		
		$alumni[$username] = $person;
	}
	
	return array(
		'activity_url_prefix' => $config['activity_url_prefix'],
        'team_name' => $config['team_name'],
		'team_members' => $team_members,
		'leadership' => $leadership,
		'alumni' => $alumni,
		'events' => $config['events'] ?? array(),
	);
}

// Load team configuration
$team_data = load_team_config();

/**
 * Get all upcoming events (personal + team/company) - within next 3 months
 */
function get_all_upcoming_events( $team_data ) {
	$all_events = array();
	$current_date = new DateTime();
	$cutoff_date = clone $current_date;
	$cutoff_date->add( new DateInterval( 'P3M' ) ); // 3 months from now
	
	// Get personal events from all team members, leadership, and alumni
	$all_people = array_merge( $team_data['team_members'], $team_data['leadership'], $team_data['alumni'] );
	foreach ( $all_people as $person ) {
		$personal_events = $person->get_upcoming_events();
		$all_events = array_merge( $all_events, $personal_events );
	}
	
	// Add team and company events (within 3 months)
	foreach ( $team_data['events'] as $event ) {
		$start_date = DateTime::createFromFormat( 'Y-m-d', $event['start_date'] );
		if ( $start_date && $start_date >= $current_date && $start_date <= $cutoff_date ) {
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
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .navigation {
            margin: 0;
        }
        .nav-link {
            display: inline-block;
            margin-left: 10px;
            padding: 8px 16px;
            background: #007cba;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            transition: background 0.3s;
            font-size: 14px;
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
        .section h3 {
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
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .person-row-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            padding: 12px 0;
        }
        .person-row {
            display: flex;
            align-items: center;
            flex-grow: 1;
            text-decoration: none;
            color: inherit;
            transition: background-color 0.2s;
            padding: 8px;
            margin: -8px;
            border-radius: 4px;
        }
        .person-row:hover {
            background-color: #f8f9fa;
            text-decoration: none;
            color: inherit;
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
                <h1><a href="index.php" style="color: inherit; text-decoration: none;"><?php echo htmlspecialchars( $team_data['team_name'] ); ?> Team Overview</a></h1>
                <div class="navigation">
                    <a href="admin.php" class="nav-link">⚙️ Admin Panel</a>
                </div>
            </div>

            <div class="overview-layout">
                <div class="people-section">
                    <div class="section">
                        <h3>Team Members (<?php echo count( $team_data['team_members'] ); ?>)</h3>
                        <ul class="people-list">
                            <?php foreach ( $team_data['team_members'] as $username => $member ) : ?>
                                <li>
                                    <div class="person-row-container">
                                        <a href="?person=<?php echo htmlspecialchars( $username ); ?>" class="person-row">
                                            <div class="person-info">
                                                <div class="person-name"><?php echo htmlspecialchars( $member->name ); ?></div>
                                                <div class="person-username">@<?php echo htmlspecialchars( $username ); ?></div>
                                            </div>
                                        </a>
                                        <div class="person-links">
                                            <a href="<?php echo htmlspecialchars( $member->one_on_one ); ?>" target="_blank">1:1 doc</a>
                                            <a href="<?php echo htmlspecialchars( $member->hr_feedback ); ?>" target="_blank">HR monthly</a>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <div class="section">
                        <h3>Leadership (<?php echo count( $team_data['leadership'] ); ?>)</h3>
                        <ul class="people-list">
                            <?php foreach ( $team_data['leadership'] as $username => $leader ) : ?>
                                <li>
                                    <div class="person-row-container">
                                        <a href="?person=<?php echo htmlspecialchars( $username ); ?>" class="person-row">
                                            <div class="person-info">
                                                <div class="person-name"><?php echo htmlspecialchars( $leader->name ); ?> <span style="color: #666; font-weight: normal;">(<?php echo htmlspecialchars( $leader->role ); ?>)</span></div>
                                                <div class="person-username">@<?php echo htmlspecialchars( $username ); ?></div>
                                            </div>
                                        </a>
                                        <div class="person-links">
                                            <a href="<?php echo htmlspecialchars( $leader->one_on_one ); ?>" target="_blank">1:1</a>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <div class="section">
                        <h3>Alumni (<?php echo count( $team_data['alumni'] ); ?>)</h3>
                        <?php if ( ! empty( $team_data['alumni'] ) ) : ?>
                            <ul class="people-list">
                                <?php foreach ( $team_data['alumni'] as $username => $alumnus ) : ?>
                                    <li>
                                        <div class="person-row-container">
                                            <a href="?person=<?php echo htmlspecialchars( $username ); ?>" class="person-row">
                                                <div class="person-info">
                                                    <div class="person-name"><?php echo htmlspecialchars( $alumnus->name ); ?> <span style="color: #999; font-weight: normal;">(Alumni)</span></div>
                                                    <div class="person-username">@<?php echo htmlspecialchars( $username ); ?></div>
                                                </div>
                                            </a>
                                            <div class="person-links">
                                                <?php if ( ! empty( $alumnus->one_on_one ) ) : ?>
                                                    <a href="<?php echo htmlspecialchars( $alumnus->one_on_one ); ?>" target="_blank">1:1</a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else : ?>
                            <p style="color: #666; font-style: italic;">No alumni yet</p>
                        <?php endif; ?>
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
            $is_alumni = false;
            
            if ( isset( $team_data['team_members'][ $person ] ) ) {
                $person_data = $team_data['team_members'][ $person ];
                $is_team_member = true;
            } elseif ( isset( $team_data['leadership'][ $person ] ) ) {
                $person_data = $team_data['leadership'][ $person ];
            } elseif ( isset( $team_data['alumni'][ $person ] ) ) {
                $person_data = $team_data['alumni'][ $person ];
                $is_alumni = true;
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
                    <h1><a href="index.php" style="color: inherit; text-decoration: none;"><?php echo htmlspecialchars( $person_data->name ); ?></a> 
                        <?php if ( $is_alumni ) : ?>
                            <span style="color: #999; font-size: 18px; font-weight: normal;">(Alumni)</span>
                        <?php endif; ?>
                    </h1>
                    <p style="color: #666;">@<?php echo htmlspecialchars( $person_data->username ); ?></p>
                    <?php if ( ! empty( $person_data->role ) ) : ?>
                        <p><strong><?php echo htmlspecialchars( $person_data->role ); ?></strong></p>
                    <?php endif; ?>
                </div>

                <div class="section">
                    <h2>Quick Links</h2>
                    <div class="links">
                        <a href="admin.php?edit_member=<?php echo htmlspecialchars( $person ); ?>" target="_blank">✏️ Edit Person</a>
                        <?php if ( ! empty( $person_data->one_on_one ) ) : ?>
                            <a href="<?php echo htmlspecialchars( $person_data->one_on_one ); ?>" target="_blank">📄 1:1 Document</a>
                        <?php endif; ?>
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

                <?php if ( ! empty( $upcoming_events ) || ! empty( $person_data->birthday ) || ! empty( $person_data->company_anniversary ) || ! empty( $kids_with_ages ) || ! empty( $person_data->notes ) || ! empty( $person_data->location ) ) : ?>
                    <div class="section">
                        <h2>Personal Details</h2>
                        
                        <?php if ( ! empty( $upcoming_events ) ) : ?>
                            <div style="background: #e8f5e8; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                                <h3 style="border-bottom: 0; margin-top: 0; color: #2d5a2d;">🗓️ Upcoming Events</h3>
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

                        <?php if ( ! empty( $person_data->location ) ) : ?>
                            <p>
                                <strong>🌍 Location:</strong> 
                                <a href="https://maps.google.com/maps?q=<?php echo urlencode( $person_data->location ); ?>" target="_blank" style="color: #007cba; text-decoration: none;"><?php echo htmlspecialchars( $person_data->location ); ?></a>
                                <span id="time-<?php echo htmlspecialchars( $person ); ?>" style="margin-left: 10px; color: #666; font-size: 14px;"></span>
                            </p>
                        <?php endif; ?>

                        <?php if ( ! empty( $person_data->partner ) ) : ?>
                            <p><strong>💑 Partner:</strong> <?php echo htmlspecialchars( $person_data->partner ); ?></p>
                        <?php endif; ?>

                        <?php if ( ! empty( $kids_with_ages ) ) : ?>
                            <div style="margin: 15px 0;">
                                <strong>👨‍👩‍👧‍👦 Family:</strong>
                                <ul style="margin: 5px 0 0 20px;">
                                    <?php foreach ( $kids_with_ages as $kid ) : ?>
                                        <li>
                                            <?php echo htmlspecialchars( $kid['name'] ); ?>
                                            <?php if ( isset( $kid['age'] ) ) : ?>
                                                (<?php echo $kid['age']; ?> years old)
                                            <?php endif; ?>
                                            <?php if ( ! empty( $kid['birthday_display'] ) ) : ?>
                                                - <?php echo htmlspecialchars( $kid['birthday_display'] ); ?>
                                                <?php if ( ! empty( $kid['time_to_birthday'] ) ) : ?>
                                                    • <?php echo htmlspecialchars( $kid['time_to_birthday'] ); ?>
                                                <?php endif; ?>
                                            <?php elseif ( ! empty( $kid['birth_year'] ) ) : ?>
                                                - born <?php echo $kid['birth_year']; ?>
                                            <?php endif; ?>
                                        </li>
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

            <?php } ?>

        <?php else : ?>
            <div class="header">
                <h1>Page Not Found</h1>
                <p><a href="?">← Back to Team Overview</a></p>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if ( $action === 'person' && ! empty( $person_data ) && ( ! empty( $person_data->location ) || ! empty( $person_data->timezone ) ) ) : ?>
    <script>
        function updateTime() {
            const timezone = '<?php echo addslashes( $person_data->timezone ); ?>';
            const timeElement = document.getElementById('time-<?php echo addslashes( $person ); ?>');
            
            if ( !timeElement ) return;
            
            if ( !timezone ) {
                timeElement.textContent = '🕒 Timezone not set';
                return;
            }
            
            try {
                const now = new Date();
                const personTime = new Intl.DateTimeFormat('en-US', {
                    timeZone: timezone,
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false
                }).format(now);
                
                const myTime = new Intl.DateTimeFormat('en-US', {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false
                }).format(now);
                
                let timeString = `🕒 ${personTime}`;
                if ( personTime !== myTime ) {
                    timeString += ` (${myTime} your time)`;
                }
                
                timeElement.textContent = timeString;
            } catch (e) {
                timeElement.textContent = '🕒 Invalid timezone';
            }
        }
        
        // Update time immediately and then every minute
        updateTime();
        setInterval(updateTime, 60000);
    </script>
    <?php endif; ?>
</body>
</html>