<?php
/**
 * Person class to represent team members and leadership
 */
require_once __DIR__ . '/event.php';
class Person {
	public $name;
	public $nickname; // Preferred nickname or short name
	public $username;
	public $links; // Array of links where key is text and value is URL
	public $role;
	public $birthday; // YYYY-MM-DD format, e.g., '1978-03-15' or MM-DD format '03-15' for backward compatibility
	public $company_anniversary; // YYYY-MM-DD format
	public $partner; // Partner/spouse name
	public $kids; // Array of arrays with 'name' and 'birth_year'
	public $notes; // Additional personal notes
	public $location; // Location/town
	public $timezone; // Timezone identifier (e.g., "America/New_York")
	public $privacy_mode; // Whether privacy mode is enabled for this person
	public $github; // GitHub username
	public $github_repos; // Array of GitHub repositories
	public $wordpress; // WordPress.org username
	public $linkedin; // LinkedIn username
	public $personal_events; // Array of personal events like "return from AFK", "vacation end", etc.

	private $original_username; // Store original username for data lookups

	public function __construct( $name, $username = '', $links = array(), $role = '', $privacy_mode = false ) {
		// Apply privacy masking at the Person level
		$this->name = $privacy_mode ? $this->mask_name( $name ) : $name;
		$this->nickname = $privacy_mode ? '' : ''; // Will be set later
		$this->original_username = $username; // Always store the original username for data lookups
		$this->username = $privacy_mode ? $this->mask_username( $username ) : $username;
		$this->links = $links;
		$this->role = $role;
		$this->birthday = '';
		$this->company_anniversary = '';
		$this->partner = '';
		$this->kids = array();
		$this->notes = '';
		$this->location = '';
		$this->timezone = '';
		$this->privacy_mode = $privacy_mode;
		$this->github = '';
		$this->github_repos = array();
		$this->wordpress = '';
		$this->linkedin = '';
		$this->personal_events = array();
	}

	/**
	 * Mask a full name for privacy mode
	 */
	private function mask_name( $full_name ) {
		$parts = explode( ' ', trim( $full_name ) );
		if ( count( $parts ) <= 1 ) {
			return $full_name; // Only first name, no masking needed
		}

		// Return first name + masked last name
		$first_name = $parts[0];
		$last_name_initial = isset( $parts[ count( $parts ) - 1 ] ) ? substr( $parts[ count( $parts ) - 1 ], 0, 1 ) . '.' : '';

		return $first_name . ' ' . $last_name_initial;
	}

	/**
	 * Mask a username for privacy mode
	 */
	private function mask_username( $username ) {
		if ( strlen( $username ) <= 3 ) {
			return $username; // Too short to mask meaningfully
		}

		return substr( $username, 0, 3 ) . '...';
	}

	/**
	 * Get display name with nickname
	 */
	public function get_display_name_with_nickname() {
		$name = $this->name; // Already masked in constructor if privacy mode is on

		if ( ! empty( $this->nickname ) && ! $this->privacy_mode ) {
			// Split name into parts and insert nickname between first and last name
			$name_parts = explode( ' ', $name );
			if ( count( $name_parts ) >= 2 ) {
				// Insert nickname after first name: "John 'Johnny' Smith"
				$first_name = array_shift( $name_parts );
				$last_parts = implode( ' ', $name_parts );
				return $first_name . ' "' . htmlspecialchars( $this->nickname ) . '" ' . $last_parts;
			} else {
				// Fallback for single name: "John 'Johnny'"
				return $name . ' "' . htmlspecialchars( $this->nickname ) . '"';
			}
		}

		return $name;
	}

	/**
	 * Get username (automatically handles privacy mode)
	 */
	public function get_username() {
		return $this->username; // Already masked in constructor if privacy mode is on
	}

	/**
	 * Get URL to this person's profile page
	 */
	public function get_profile_url( $additional_params = array() ) {
		global $current_team;
		$params = array( 'person' => $this->original_username );
		
		if ( $current_team !== 'team' ) {
			$params['team'] = $current_team;
		}
		
		if ( $this->privacy_mode ) {
			$params['privacy'] = '1';
		}
		
		$params = array_merge( $params, $additional_params );
		return 'index.php?' . http_build_query( $params );
	}

	/**
	 * Get URL to edit this person in admin
	 */
	public function get_edit_url( $additional_params = array() ) {
		global $current_team;
		$params = array( 'edit_member' => $this->original_username );
		
		if ( $current_team !== 'team' ) {
			$params['team'] = $current_team;
		}
		
		if ( $this->privacy_mode ) {
			$params['privacy'] = '1';
		}
		
		$params = array_merge( $params, $additional_params );
		return 'admin.php?' . http_build_query( $params );
	}

	/**
	 * Get upcoming events for this person (within next year)
	 */
	public function get_upcoming_events() {
		$events = array();
		$current_date = new DateTime();
		$current_year = (int) $current_date->format( 'Y' );
		$cutoff_date = clone $current_date;
		$cutoff_date->add( new DateInterval( 'P1Y' ) )->sub( new DateInterval( 'P1D' ) ); // 1 year minus 1 day from now

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
					// Calculate the person's age on this birthday
					$age = $current_year - (int) $birth_date->format( 'Y' );
					$events[] = Event::from_person_event( 'birthday', $birthday_this_year, $this, array( 'age' => $age ) );
				} elseif ( $birthday_this_year < $current_date ) {
					// Check next year's birthday
					$birthday_next_year = DateTime::createFromFormat( 'Y-m-d', ( $current_year + 1 ) . '-' . $birthday_this_year->format( 'm-d' ) );
					if ( $birthday_next_year && $birthday_next_year <= $cutoff_date ) {
						// Calculate the person's age on next year's birthday
						$age = ( $current_year + 1 ) - (int) $birth_date->format( 'Y' );
						$events[] = Event::from_person_event( 'birthday', $birthday_next_year, $this, array( 'age' => $age ) );
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
					$events[] = Event::from_person_event( 'anniversary', $anniversary_this_year, $this, array( 'years' => $years ) );
				}
			}
		}

		// Kids' birthdays
		if ( ! empty( $this->kids ) && is_array( $this->kids ) ) {
			foreach ( $this->kids as $kid ) {
				if ( ! empty( $kid['birthday'] ) ) {
					// Check if it's a full birthday date (YYYY-MM-DD)
					if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $kid['birthday'] ) ) {
						// Full birthday date available
						$kid_birth_date = DateTime::createFromFormat( 'Y-m-d', $kid['birthday'] );
						if ( $kid_birth_date ) {
							$kid_birthday_this_year = DateTime::createFromFormat( 'Y-m-d', $current_year . '-' . $kid_birth_date->format( 'm-d' ) );
							if ( $kid_birthday_this_year >= $current_date && $kid_birthday_this_year <= $cutoff_date ) {
								$age = $current_year - (int) $kid_birth_date->format( 'Y' );
								$events[] = Event::from_person_event( 'birthday', $kid_birthday_this_year, $this, array( 'kid_name' => $kid['name'], 'age' => $age ) );
							} elseif ( $kid_birthday_this_year < $current_date ) {
								// Check next year's birthday
								$kid_birthday_next_year = DateTime::createFromFormat( 'Y-m-d', ( $current_year + 1 ) . '-' . $kid_birth_date->format( 'm-d' ) );
								if ( $kid_birthday_next_year && $kid_birthday_next_year <= $cutoff_date ) {
									$age = ( $current_year + 1 ) - (int) $kid_birth_date->format( 'Y' );
									$events[] = Event::from_person_event( 'birthday', $kid_birthday_next_year, $this, array( 'kid_name' => $kid['name'], 'age' => $age ) );
								}
							}
						}
					} elseif ( preg_match( '/^\d{2}-\d{2}$/', $kid['birthday'] ) ) {
						// Month-day format (MM-DD) - no birth year known
						$kid_birthday_this_year = DateTime::createFromFormat( 'Y-m-d', $current_year . '-' . $kid['birthday'] );
						if ( $kid_birthday_this_year ) {
							if ( $kid_birthday_this_year >= $current_date && $kid_birthday_this_year <= $cutoff_date ) {
								$events[] = Event::from_person_event( 'birthday', $kid_birthday_this_year, $this, array( 'kid_name' => $kid['name'] ) );
							} elseif ( $kid_birthday_this_year < $current_date ) {
								// Check next year's birthday
								$kid_birthday_next_year = DateTime::createFromFormat( 'Y-m-d', ( $current_year + 1 ) . '-' . $kid['birthday'] );
								if ( $kid_birthday_next_year && $kid_birthday_next_year <= $cutoff_date ) {
									$events[] = Event::from_person_event( 'birthday', $kid_birthday_next_year, $this, array( 'kid_name' => $kid['name'] ) );
								}
							}
						}
					}
				}
			}
		}

		// Personal events (return from AFK, vacation end, etc.)
		if ( ! empty( $this->personal_events ) && is_array( $this->personal_events ) ) {
			foreach ( $this->personal_events as $personal_event ) {
				if ( ! empty( $personal_event['date'] ) && ! empty( $personal_event['description'] ) ) {
					$event_date = DateTime::createFromFormat( 'Y-m-d', $personal_event['date'] );
					if ( $event_date && $event_date >= $current_date && $event_date <= $cutoff_date ) {
						$events[] = Event::from_person_event( 
							$personal_event['type'] ?? 'personal', 
							$event_date, 
							$this, 
							array( 'description' => $personal_event['description'] )
						);
					}
				}
			}
		}

		// Sort events by date
		usort( $events, function( $a, $b ) {
			return $a->date <=> $b->date;
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
				// Check if it's a full birthday date (YYYY-MM-DD)
				if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $kid['birthday'] ) ) {
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
				} elseif ( preg_match( '/^\d{2}-\d{2}$/', $kid['birthday'] ) ) {
					// Month-day format (MM-DD) - no birth year known
					$next_birthday = DateTime::createFromFormat( 'Y-m-d', $current_year . '-' . $kid['birthday'] );
					if ( $next_birthday && $next_birthday < $current_date ) {
						$next_birthday = DateTime::createFromFormat( 'Y-m-d', ( $current_year + 1 ) . '-' . $kid['birthday'] );
					}

					$time_info = '';
					$birthday_display = '';
					if ( $next_birthday ) {
						$time_info = $this->get_time_until_date( $current_date, $next_birthday );
						$birthday_display = $next_birthday->format( 'F j' );
					}

					$kids_with_ages[] = array(
						'name' => $kid['name'] ?? 'Child',
						'birthday' => $kid['birthday'],
						'birthday_display' => $birthday_display,
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

		// Handle privacy mode
		if ( $this->privacy_mode ) {
			// Show only age if full birthday is available
			$age = $this->get_age();
			if ( $age !== null ) {
				return 'Age ' . $age;
			}
			return '[Hidden]';
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
		// Hide time calculations in privacy mode
		if ( $this->privacy_mode ) {
			return '';
		}

		$diff = $from_date->diff( $to_date );

		if ( $diff->days <= 0 ) {
			return 'today';
		} elseif ( $diff->days == 1 ) {
			return 'tomorrow';
		} elseif ( $diff->days <= 7 ) {
			return 'in ' . $diff->days . 'd';
		} elseif ( $diff->days <= 30 ) {
			$weeks = floor( $diff->days / 7 );
			if ( $weeks == 1 ) {
				return 'in 1w';
			} else {
				return 'in ' . $weeks . 'w';
			}
		} else {
			$months = $diff->m + ( $diff->y * 12 );
			if ( $months == 1 ) {
				return 'in 1mo';
			} else {
				return 'in ' . $months . 'mo';
			}
		}
	}


	/**
	 * Get monthly feedback status for this person
	 */
	public function get_monthly_feedback_status( $month = null ) {
		$feedback_file = __DIR__ . '/../hr-feedback.json';

		if ( ! file_exists( $feedback_file ) ) {
			return array(
				'status' => 'not-started',
				'updated' => null,
				'text' => 'Feedback not started',
				'css_class' => 'not-started'
			);
		}

		$content = file_get_contents( $feedback_file );
		$feedback_data = json_decode( $content, true ) ?: array();

		$target_month = $month ?: get_hr_feedback_month();
		$feedback = $feedback_data['feedback'][$this->original_username][$target_month] ?? null;

		if ( ! $feedback ) {
			return array(
				'status' => 'not-started',
				'updated' => null,
				'text' => 'Feedback not started',
				'css_class' => 'not-started'
			);
		}

		// Deduce status from checklist todos
		$submitted_to_hr = $feedback['submitted_to_hr'] ?? false;
		$draft_complete = $feedback['draft_complete'] ?? false;
		$google_doc_updated = $feedback['google_doc_updated'] ?? false;
		$has_feedback_content = !empty($feedback['feedback_to_person']) || !empty($feedback['feedback_to_hr']);

		// 5. Submitted
		if ( $submitted_to_hr ) {
			return array(
				'status' => 'submitted',
				'updated' => $feedback['updated_at'],
				'text' => '✅ Submitted',
				'css_class' => 'completed'
			);
		}

		// 4. Google doc updated = Ready for review
		if ( $google_doc_updated ) {
			return array(
				'status' => 'ready-for-review',
				'updated' => $feedback['updated_at'],
				'text' => '📤 Ready for review',
				'css_class' => 'review'
			);
		}

		// 3. First draft finalized
		if ( $draft_complete ) {
			return array(
				'status' => 'draft-finalized',
				'updated' => $feedback['updated_at'],
				'text' => '📋 Draft finalized',
				'css_class' => 'draft-finalized'
			);
		}

		// 2. Feedback started (has content but draft not marked complete)
		if ( $has_feedback_content ) {
			return array(
				'status' => 'started',
				'updated' => $feedback['updated_at'],
				'text' => '📝 Started',
				'css_class' => 'draft'
			);
		}

		// Fallback to legacy status field for backwards compatibility
		if ( isset( $feedback['status'] ) ) {
			if ( $feedback['status'] === 'submitted' ) {
				return array(
					'status' => 'submitted',
					'updated' => $feedback['submitted_at'] ?? $feedback['updated_at'],
					'text' => 'Submitted',
					'css_class' => 'completed'
				);
			} elseif ( $feedback['status'] === 'review' ) {
				return array(
					'status' => 'ready-for-review',
					'updated' => $feedback['review_at'] ?? $feedback['updated_at'],
					'text' => 'Ready for review',
					'css_class' => 'review'
				);
			}
		}

		// 1. Default fallback - feedback not started
		return array(
			'status' => 'not-started',
			'updated' => null,
			'text' => 'Feedback not started',
			'css_class' => 'not-started'
		);
	}
}