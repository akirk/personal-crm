<?php
/**
 * Event class to represent team events, personal events, etc.
 */
if ( class_exists( 'Event' ) ) {
    return;
}

class Event {
	public $type;
	public $description;
	public $name;
	public $location;
	public $details;
	public $links;
	public DateTime $date;
	public ?DateTime $end_date = null;
	public ?Person $person = null; // Optional Person object
	public $privacy_mode = false; // Privacy mode for masking sensitive data

	public function __construct( $type, $date, $description, $person = null, $privacy_mode = false ) {
		$this->type = $type;
		$this->description = $description;
		$this->location = '';
		$this->details = '';
		$this->links = array();
		$this->date = $date;
		$this->person = $person;
		$this->privacy_mode = $privacy_mode;
	}

	/**
	 * Set event location
	 */
	public function set_location( $location ) {
		$this->location = $location;
		return $this;
	}

	/**
	 * Set event details/description
	 */
	public function set_details( $details ) {
		$this->details = $details;
		return $this;
	}

	/**
	 * Set event links
	 */
	public function set_links( $links ) {
		$this->links = $links;
		return $this;
	}

	/**
	 * Set end date for multi-day events
	 */
	public function set_end_date( $end_date ) {
		return $this->end_date = $end_date;
	}

	/**
	 * Set event name (different from description)
	 */
	public function set_name( $name ) {
		$this->name = $name;
		return $this;
	}

	/**
	 * Check if this event belongs to a person
	 */
	public function has_person() {
		return $this->person !== null;
	}

	/**
	 * Get person name if available
	 */
	public function get_person_name() {
		return $this->person ? $this->person->name : null;
	}

	/**
	 * Get person username if available
	 */
	public function get_person_username() {
		return $this->person ? $this->person->username : null;
	}

	/**
	 * Get privacy-aware display description
	 */
	public function get_display_description() {
		// Simply return the description as-is since the Person object already handles
		// masking its own name in the constructor when privacy_mode is enabled
		return $this->description;
	}

	/**
	 * Get privacy-aware display date
	 */
	public function get_display_date() {
		if ( ! $this->privacy_mode ) {
			return $this->date->format( 'M j' );
		}

		// For privacy mode, only show month/day for birthdays and anniversaries
		// Hide exact dates for other personal events
		if ( in_array( $this->type, ['birthday', 'anniversary'] ) ) {
			return $this->date->format( 'M j' );
		}

		return '***';
	}

	/**
	 * Get privacy-aware full date display
	 */
	public function get_display_full_date() {
		if ( ! $this->privacy_mode ) {
			return $this->date->format( 'M j, Y' );
		}

		// For privacy mode, mask the year for personal events
		if ( in_array( $this->type, ['birthday', 'anniversary'] ) ) {
			return $this->date->format( 'M j' ) . ', ****';
		}

		return '*** **, ****';
	}

	public function is_past() {
		return $this->date < new DateTime();
	}

	public function get_title() {
		// Use privacy-aware description
		$description = $this->get_display_description();

		// Add duration info if it's a multi-day event
		$duration = '';
		if ( ! empty( $this->end_date ) && $this->date !== $this->end_date ) {
			if ( $this->privacy_mode ) {
				$duration = ' → ***';
			} else {
				$duration = ' → ' . $this->end_date->format( 'M j' );
			}
		}

		return $description . $duration;
	}

	/**
	 * Get title without person name (for displaying on person's own page)
	 */
	public function get_title_without_person_name() {
		// Start with privacy-aware description
		$description = $this->get_display_description();

		// Handle birthday events - remove the person's name prefix
		if ( $this->person && $this->type === 'birthday' ) {
			// Check for emoji prefix + person's name
			$person_prefix_with_emoji = $this->person->name . "'s ";
			if ( strpos( $description, $person_prefix_with_emoji ) === 0 ) {
				$description = substr( $description, strlen( $person_prefix_with_emoji ) );
			} else {
				// Fallback for old format without emojis
				$person_prefix = $this->person->name . "'s ";
				if ( strpos( $description, $person_prefix ) === 0 ) {
					$description = substr( $description, strlen( $person_prefix ) );
				}
			}
		}

		// Handle anniversary events - remove the person's name prefix
		if ( $this->person && $this->type === 'anniversary' ) {
			// Check for emoji prefix + person's name
			$person_prefix_with_emoji = $this->person->name . "'s ";
			if ( strpos( $description, $person_prefix_with_emoji ) === 0 ) {
				$description = substr( $description, strlen( $person_prefix_with_emoji ) );
			} else {
				// Fallback for old format without emojis
				$person_prefix = $this->person->name . "'s ";
				if ( strpos( $description, $person_prefix ) === 0 ) {
					$description = substr( $description, strlen( $person_prefix ) );
				}
			}
		}

		// If this is a personal event and has a person, remove the person's name from the description
		if ( $this->person && $this->type === 'other' ) {
			// Use actual name (not masked) for removal since we're on the person's own page
			$person_prefix = $this->person->name . ': ';
			if ( strpos( $this->description, $person_prefix ) === 0 ) {
				$clean_description = substr( $this->description, strlen( $person_prefix ) );
				// For privacy mode, we still need to mask any other names that might be in the description
				if ( $this->privacy_mode ) {
					// This is a simplified approach - in practice you might want more sophisticated name masking
					$description = $clean_description;
				} else {
					$description = $clean_description;
				}
			}
		}

		// Add duration info if it's a multi-day event
		$duration = '';
		if ( ! empty( $this->end_date ) && $this->date !== $this->end_date ) {
			if ( $this->privacy_mode ) {
				$duration = ' → ***';
			} else {
				$duration = ' → ' . $this->end_date->format( 'M j' );
			}
		}

		return $description . $duration;
	}

	public function get_color() {
		$colors = array(
			'birthday' => '#e74c3c',
			'partner_birthday' => '#e74c3c',
			'anniversary' => '#9b59b6',
			'team' => '#3498db',
			'company' => '#2ecc71',
			'conference' => '#f39c12',
			'training' => '#1abc9c',
			'sabbatical' => '#34495e',
			'other' => '#95a5a6'
		);

		return isset( $colors[$this->type] ) ? $colors[$this->type] : '#95a5a6';
	}

	/**
	 * Get ordinal number (1st, 2nd, 3rd, 4th, etc.)
	 */
	private static function get_ordinal_number( $number ) {
		$formatter = new NumberFormatter( 'en_US', NumberFormatter::ORDINAL );
		return $formatter->format( $number );
	}
	/**
	 * Create Event from team event data
	 */
	public static function from_team_event( $event_data, $privacy_mode = false ) {
		$start_date = DateTime::createFromFormat( 'Y-m-d', $event_data['start_date'] );
		$event = new self( $event_data['type'], $start_date, $event_data['name'], null, $privacy_mode );
		
		$event->set_location( $event_data['location'] ?? '' );
		$event->set_details( $event_data['description'] ?? '' );
		$event->set_links( $event_data['links'] ?? array() );
		
		if ( ! empty( $event_data['end_date'] ) ) {
			$event->set_end_date(  DateTime::createFromFormat( 'Y-m-d', $event_data['end_date'] ) );
		}
		
		return $event;
	}

	/**
	 * Create Event from person birthday/anniversary
	 */
	public static function from_person_event( $type, $date, $person, $additional_info = array() ) {
		$description = '';
		$privacy_mode = $person->privacy_mode ?? false;
		
		switch ( $type ) {
			case 'birthday':
				// Handle kid's birthday differently
				if ( isset( $additional_info['kid_name'] ) ) {
					if ( ! $privacy_mode && isset( $additional_info['age'] ) ) {
						$age = $additional_info['age'];
						$ordinal = self::get_ordinal_number( $age );
						$description = $additional_info['kid_name'] . "'s " . $ordinal . " Birthday 🎈";
					} elseif ( ! $privacy_mode ) {
						$description = $additional_info['kid_name'] . "'s Birthday 🎈";
					} else {
						// Privacy mode - hide kid's name
						$description = "Child's Birthday 🎈";
					}
					
					// Add parent info if not in privacy mode
					if ( ! $privacy_mode ) {
						$description .= ' (' . $person->name . "'s kid)";
					}
				} else {
					// Regular person birthday
					if ( ! $privacy_mode && isset( $additional_info['age'] ) ) {
						$age = $additional_info['age'];
						$ordinal = self::get_ordinal_number( $age );
						$description = $person->name . "'s " . $ordinal . " Birthday 🎈";
					} else {
						$description = $person->name . "'s Birthday 🎈";
					}
				}
				break;
				
			case 'anniversary':
				if ( ! $privacy_mode && isset( $additional_info['years'] ) ) {
					$years = $additional_info['years'];
					$ordinal = self::get_ordinal_number( $years );
					$description = $person->name . "'s " . $ordinal . " Anniversary 🎉";
				} else {
					$description = $person->name . "'s Anniversary 🎉";
				}
				break;
				
			case 'partner_birthday':
				if ( ! $privacy_mode && isset( $additional_info['partner_name'] ) ) {
					$partner_name = $additional_info['partner_name'];
					if ( isset( $additional_info['age'] ) ) {
						$age = $additional_info['age'];
						$ordinal = self::get_ordinal_number( $age );
						$description = $partner_name . "'s " . $ordinal . " Birthday 🎈 (" . $person->name . "'s partner)";
					} else {
						$description = $partner_name . "'s Birthday 🎈 (" . $person->name . "'s partner)";
					}
				} else {
					$description = "Partner's Birthday 🎈";
				}
				break;
				
			case 'other':
				// For personal events, use the custom description with person's name
				if ( isset( $additional_info['description'] ) ) {
					$description = $person->name . ': ' . $additional_info['description'];
				} else {
					$description = $person->name . "'s Other Event";
				}
				break;

			default:
				// For other event types, use person's name + type
				$description = $person->name . "'s " . ucfirst( $type );
		}
		
		return new self( $type, $date, $description, $person, $privacy_mode );
	}
}