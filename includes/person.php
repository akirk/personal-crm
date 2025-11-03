<?php
/**
 * Person class to represent team members and leadership
 */
namespace PersonalCRM;

if ( class_exists( '\PersonalCRM\Person' ) ) {
    return;
}

class Person {
	public $id; // Database ID
	public $name;
	public $nickname; // Preferred nickname or short name
	public $username;
	public $links; // Array of links where key is text and value is URL
	public $role;
	public $birthday; // YYYY-MM-DD format, e.g., '1978-03-15' or MM-DD format '03-15' for backward compatibility
	public $company_anniversary; // YYYY-MM-DD format
	public $partner; // Partner/spouse name
	public $partner_birthday; // Partner's birthday YYYY-MM-DD format, or MM-DD format for year-unknown
	public $kids; // Array of arrays with 'name' and 'birth_year'
	public $notes; // Additional personal notes
	public $location; // Location/town
	public $timezone; // Timezone identifier (e.g., "America/New_York")
	public $github; // GitHub username
	public $github_repos; // Array of GitHub repositories
	public $wordpress; // WordPress.org username
	public $linear; // Linear id
	public $linkedin; // LinkedIn username
	public $website; // Personal website URL
	public $personal_events; // Array of personal events like "return from AFK", "vacation end", etc.
	public $email;
	public $left_company; // Boolean indicating if person has left the company (for alumni)
	public $new_company; // New company name (for alumni)
	public $new_company_website; // New company website (for alumni)
	public $deceased; // Boolean indicating if person is deceased (excludes from birthday reminders)
	public $deceased_date; // Date of passing (YYYY-MM-DD format)
	public $team; // Team/group slug this person belongs to
	public $category; // Category: team_members, leadership, consultants, or alumni
	public $groups; // Array of groups this person belongs to (with id, group_name, slug, etc.)
	private $original_username; // Store original username for data lookups

	public function __construct( $name, $username = '', $links = array(), $role = '' ) {
		$this->id = null; // Will be set by storage layer
		$this->name = $name;
		$this->nickname = ''; // Will be set later
		$this->original_username = $username;
		$this->username = $username;
		$this->links = $links;
		$this->role = $role;
		$this->birthday = '';
		$this->company_anniversary = '';
		$this->partner = '';
		$this->partner_birthday = '';
		$this->kids = array();
		$this->notes = array();
		$this->location = '';
		$this->timezone = '';
		$this->github = '';
		$this->github_repos = array();
		$this->wordpress = '';
		$this->linear = '';
		$this->linkedin = '';
		$this->personal_events = array();
		$this->deceased = 0;
		$this->deceased_date = '';
		$this->groups = array();
	}

	/**
	 * Get display name with nickname
	 */
	public function get_display_name_with_nickname() {
		$name = $this->name;

		if ( ! empty( $this->nickname ) ) {
			// Split name into parts and insert nickname between first and last name
			$name_parts = explode( ' ', $name );
			if ( count( $name_parts ) >= 2 ) {
				// Insert nickname after first name: "John 'Johnny' Smith"
				$first_name = array_shift( $name_parts );
				$last_parts = implode( ' ', $name_parts );
				return $first_name . ' "' . esc_html( $this->nickname ) . '" ' . $last_parts;
			} else {
				// Fallback for single name: "John 'Johnny'"
				return $name . ' "' . esc_html( $this->nickname ) . '"';
			}
		}

		return $name;
	}

	/**
	 * Get username
	 */
	public function get_username() {
		return $this->username;
	}

	/**
	 * Get URL to this person's profile page
	 */
	public function get_profile_url( $additional_params = array() ) {
		$params = array( 'person' => $this->original_username );
		$params = array_merge( $params, $additional_params );
		return PersonalCrm::get_instance()->build_url( 'person.php', $params );
	}

	/**
	 * Get upcoming events for this person (within next year)
	 */
	public function get_upcoming_events() {
		$events = array();
		$current_date = new DateTime();
		$current_year = (int) $current_date->format( 'Y' );
		$cutoff_date = clone $current_date;
		$cutoff_date->add( new \DateInterval( 'P1Y' ) )->sub( new \DateInterval( 'P1D' ) ); // 1 year minus 1 day from now

		// Birthday - skip if person is deceased
		if ( ! empty( $this->birthday ) && empty( $this->deceased ) ) {
			$birthday_date = null;
			$birth_date = null;

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
					// Calculate the person's age on this birthday (only if we have birth year)
					$event_data = array();
					if ( $birth_date ) {
						$age = $current_year - (int) $birth_date->format( 'Y' );
						$event_data['age'] = $age;
					}
					$events[] = Event::from_person_event( 'birthday', $birthday_this_year, $this, $event_data );
				} elseif ( $birthday_this_year < $current_date ) {
					// Check next year's birthday
					$birthday_next_year = DateTime::createFromFormat( 'Y-m-d', ( $current_year + 1 ) . '-' . $birthday_this_year->format( 'm-d' ) );
					if ( $birthday_next_year && $birthday_next_year <= $cutoff_date ) {
						// Calculate the person's age on next year's birthday (only if we have birth year)
						$event_data = array();
						if ( $birth_date ) {
							$age = ( $current_year + 1 ) - (int) $birth_date->format( 'Y' );
							$event_data['age'] = $age;
						}
						$events[] = Event::from_person_event( 'birthday', $birthday_next_year, $this, $event_data );
					}
				}
			}
		}

		// Partner birthday
		if ( ! empty( $this->partner_birthday ) && ! empty( $this->partner ) ) {
			$partner_birthday_date = null;
			$partner_birth_date = null;

			// Check if it's full YYYY-MM-DD format
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $this->partner_birthday ) ) {
				$partner_birth_date = DateTime::createFromFormat( 'Y-m-d', $this->partner_birthday );
				if ( $partner_birth_date ) {
					$partner_birthday_this_year = DateTime::createFromFormat( 'Y-m-d', $current_year . '-' . $partner_birth_date->format( 'm-d' ) );
				}
			} elseif ( preg_match( '/^\d{2}-\d{2}$/', $this->partner_birthday ) ) {
				// Year-unknown MM-DD format
				$partner_birthday_this_year = DateTime::createFromFormat( 'Y-m-d', $current_year . '-' . $this->partner_birthday );
			}

			if ( isset( $partner_birthday_this_year ) && $partner_birthday_this_year ) {
				if ( $partner_birthday_this_year >= $current_date && $partner_birthday_this_year <= $cutoff_date ) {
					// Calculate the partner's age on this birthday (only if we have birth year)
					$event_data = array( 'partner_name' => $this->partner );
					if ( $partner_birth_date ) {
						$age = $current_year - (int) $partner_birth_date->format( 'Y' );
						$event_data['age'] = $age;
					}
					$events[] = Event::from_person_event( 'partner_birthday', $partner_birthday_this_year, $this, $event_data );
				} elseif ( $partner_birthday_this_year < $current_date ) {
					// Check next year's partner birthday
					$partner_birthday_next_year = DateTime::createFromFormat( 'Y-m-d', ( $current_year + 1 ) . '-' . $partner_birthday_this_year->format( 'm-d' ) );
					if ( $partner_birthday_next_year && $partner_birthday_next_year <= $cutoff_date ) {
						// Calculate the partner's age on next year's birthday (only if we have birth year)
						$event_data = array( 'partner_name' => $this->partner );
						if ( $partner_birth_date ) {
							$age = ( $current_year + 1 ) - (int) $partner_birth_date->format( 'Y' );
							$event_data['age'] = $age;
						}
						$events[] = Event::from_person_event( 'partner_birthday', $partner_birthday_next_year, $this, $event_data );
					}
				}
			}
		}

		// Company anniversary
		if ( ! empty( $this->company_anniversary ) ) {
			$anniversary_date = DateTime::createFromFormat( 'Y-m-d', $this->company_anniversary );
			if ( $anniversary_date ) {
				$anniversary_this_year = DateTime::createFromFormat( 'Y-m-d', $current_year . '-' . $anniversary_date->format( 'm-d' ) );
				if ( $anniversary_this_year ) {
					// If anniversary already passed this year, look at next year
					if ( $anniversary_this_year < $current_date ) {
						$anniversary_next_year = DateTime::createFromFormat( 'Y-m-d', ( $current_year + 1 ) . '-' . $anniversary_date->format( 'm-d' ) );
						if ( $anniversary_next_year && $anniversary_next_year <= $cutoff_date ) {
							$years = ( $current_year + 1 ) - (int) $anniversary_date->format( 'Y' );
							$events[] = Event::from_person_event( 'anniversary', $anniversary_next_year, $this, array( 'years' => $years ) );
						}
					} else if ( $anniversary_this_year <= $cutoff_date ) {
						$years = $current_year - (int) $anniversary_date->format( 'Y' );
						$events[] = Event::from_person_event( 'anniversary', $anniversary_this_year, $this, array( 'years' => $years ) );
					}
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

		// Allow other plugins to add custom events
		$events = apply_filters( 'personal_crm_person_upcoming_events', $events, $this );

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
	 * Get upcoming events for this person with guaranteed birthday and anniversary inclusion
	 * This method ensures that the person's next birthday and anniversary are always included,
	 * even if they just passed (to always show when they are)
	 */
	public function get_upcoming_events_with_personal_dates() {
		$events = array();
		$current_date = new DateTime();
		$current_year = (int) $current_date->format( 'Y' );
		$cutoff_date = clone $current_date;
		$cutoff_date->add( new \DateInterval( 'P1Y' ) ); // 1 year from now

		// Always include birthday - find the next occurrence (this year or next year) - skip if deceased
		if ( ! empty( $this->birthday ) && empty( $this->deceased ) ) {
			$birthday_date = null;

			// Check if it's full YYYY-MM-DD format
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $this->birthday ) ) {
				$birth_date = DateTime::createFromFormat( 'Y-m-d', $this->birthday );
				if ( $birth_date ) {
					$birthday_this_year = DateTime::createFromFormat( 'Y-m-d', $current_year . '-' . $birth_date->format( 'm-d' ) );
					$birthday_next_year = DateTime::createFromFormat( 'Y-m-d', ( $current_year + 1 ) . '-' . $birth_date->format( 'm-d' ) );
					
					// Use this year's birthday if it hasn't passed yet, otherwise use next year's
					if ( $birthday_this_year >= $current_date ) {
						$age = $current_year - (int) $birth_date->format( 'Y' );
						$events[] = Event::from_person_event( 'birthday', $birthday_this_year, $this, array( 'age' => $age ) );
					} else {
						$age = ( $current_year + 1 ) - (int) $birth_date->format( 'Y' );
						$events[] = Event::from_person_event( 'birthday', $birthday_next_year, $this, array( 'age' => $age ) );
					}
				}
			} elseif ( preg_match( '/^\d{2}-\d{2}$/', $this->birthday ) ) {
				// Legacy MM-DD format
				$birthday_this_year = DateTime::createFromFormat( 'Y-m-d', $current_year . '-' . $this->birthday );
				$birthday_next_year = DateTime::createFromFormat( 'Y-m-d', ( $current_year + 1 ) . '-' . $this->birthday );
				
				if ( $birthday_this_year >= $current_date ) {
					$events[] = Event::from_person_event( 'birthday', $birthday_this_year, $this );
				} else {
					$events[] = Event::from_person_event( 'birthday', $birthday_next_year, $this );
				}
			}
		}

		// Always include company anniversary - find the next occurrence
		if ( ! empty( $this->company_anniversary ) ) {
			$anniversary_date = DateTime::createFromFormat( 'Y-m-d', $this->company_anniversary );
			if ( $anniversary_date ) {
				$anniversary_this_year = DateTime::createFromFormat( 'Y-m-d', $current_year . '-' . $anniversary_date->format( 'm-d' ) );
				$anniversary_next_year = DateTime::createFromFormat( 'Y-m-d', ( $current_year + 1 ) . '-' . $anniversary_date->format( 'm-d' ) );
				
				if ( $anniversary_this_year >= $current_date ) {
					$years = $current_year - (int) $anniversary_date->format( 'Y' );
					$events[] = Event::from_person_event( 'anniversary', $anniversary_this_year, $this, array( 'years' => $years ) );
				} else {
					$years = ( $current_year + 1 ) - (int) $anniversary_date->format( 'Y' );
					$events[] = Event::from_person_event( 'anniversary', $anniversary_next_year, $this, array( 'years' => $years ) );
				}
			}
		}

		// Get other upcoming events (kids' birthdays, personal events) with normal filtering
		$other_events = $this->get_upcoming_events();
		foreach ( $other_events as $event ) {
			// Only add events that are not the person's own birthday or anniversary
			// (we already added those above)
			// But include kids' birthdays (which have kid's name in description, not parent's name)
			$is_own_birthday = $event->type === 'birthday' && $event->person === $this && 
			                  strpos( $event->description, $this->name . "'s" ) === 0;
			$is_own_anniversary = $event->type === 'anniversary' && $event->person === $this;
			
			if ( ! $is_own_birthday && ! $is_own_anniversary ) {
				$events[] = $event;
			}
		}

		// Sort all events by date
		usort( $events, function( $a, $b ) {
			return $a->date <=> $b->date;
		} );

		return $events;
	}

	/**
	 * Get Gravatar URL for the person's email address
	 *
	 * @param int $size Size of the avatar in pixels
	 * @param string $default Default image to use if no Gravatar is found
	 * @param string $rating Maximum rating (g, pg, r, x)
	 * @return string|null Gravatar URL or null if no email
	 */
	public function get_gravatar_url( $size = 80, $default = 'identicon', $rating = 'g' ) {
		if ( empty( $this->email ) ) {
			return null;
		}

		$hash = md5( strtolower( trim( $this->email ) ) );
		$url = "https://www.gravatar.com/avatar/{$hash}?s={$size}&d={$default}&r={$rating}";

		return $url;
	}

	/**
	 * Check if the person has a Gravatar image (not just the default)
	 *
	 * @return bool True if person has a custom Gravatar
	 */
	public function has_gravatar() {
		if ( empty( $this->email ) ) {
			return false;
		}

		$hash = md5( strtolower( trim( $this->email ) ) );
		$url = "https://www.gravatar.com/avatar/{$hash}?d=404";

		// Check if Gravatar returns 404 (no custom avatar) or 200 (has avatar)
		$headers = @get_headers( $url );
		return $headers && strpos( $headers[0], '200' ) !== false;
	}

	/**
	 * Get Linear profile URL
	 *
	 * @return string|null Linear URL or null if no Linear ID
	 */
	public function get_linear_url() {
		if ( empty( $this->linear ) ) {
			return null;
		}
		return 'https://linear.app/a8c/profiles/' . $this->linear;
	}

	/**
	 * Get WordPress.org profile URL
	 *
	 * @return string|null WordPress.org URL or null if no username
	 */
	public function get_wordpress_url() {
		if ( empty( $this->wordpress ) ) {
			return null;
		}
		return 'https://profiles.wordpress.org/' . $this->wordpress;
	}

	/**
	 * Get LinkedIn profile URL
	 *
	 * @return string|null LinkedIn URL or null if no username
	 */
	public function get_linkedin_url() {
		if ( empty( $this->linkedin ) ) {
			return null;
		}
		return 'https://linkedin.com/in/' . $this->linkedin;
	}
}