<?php
/**
 * Event class to represent team events, personal events, etc.
 */
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

	public function __construct( $type, $date, $description, $person = null ) {
		$this->type = $type;
		$this->description = $description;
		$this->location = '';
		$this->details = '';
		$this->links = array();
		$this->date = $date;
		$this->person = $person;
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

	public function is_past() {
		return $this->date < new DateTime();
	}

	public function get_title() {
		// Add duration info if it's a multi-day event
		$duration = '';
		if ( ! empty( $this->end_date ) && $this->date !== $this->end_date ) {
			$duration = ' → ' . $this->end_date->format( 'M j' );
		}

		return $this->description . $duration;
	}

	public function get_color() {
		$colors = array(
			'birthday' => '#e74c3c',
			'anniversary' => '#9b59b6',
			'team' => '#3498db',
			'company' => '#2ecc71',
			'conference' => '#f39c12',
			'training' => '#1abc9c',
			'other' => '#95a5a6'
		);

		return isset( $colors[$this->type] ) ? $colors[$this->type] : '#95a5a6';
	}
	/**
	 * Create Event from team event data
	 */
	public static function from_team_event( $event_data ) {
		$start_date = DateTime::createFromFormat( 'Y-m-d', $event_data['start_date'] );
		$event = new self( $event_data['type'], $start_date, $event_data['name'] );
		
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
					$description = $additional_info['kid_name'] . "'s Birthday";
					
					// Add age information if not in privacy mode and we have age
					if ( ! $privacy_mode && isset( $additional_info['age'] ) ) {
						$description .= ' (turning ' . $additional_info['age'] . ')';
					}
					
					// Add parent info if not in privacy mode
					if ( ! $privacy_mode ) {
						$description .= ' (' . $person->name . "'s kid)";
					}
				} else {
					// Regular person birthday
					$description = $person->name . "'s Birthday";
					
					// Add age information if not in privacy mode and we have birth year
					if ( ! $privacy_mode && isset( $additional_info['age'] ) ) {
						$description .= ' (turning ' . $additional_info['age'] . ')';
					}
				}
				break;
				
			case 'anniversary':
				$description = $person->name . "'s Anniversary";
				
				// Add years information if not in privacy mode
				if ( ! $privacy_mode && isset( $additional_info['years'] ) ) {
					$description .= ' (' . $additional_info['years'] . ' years)';
				}
				break;
				
			default:
				// For other event types, use person's name + type
				$description = $person->name . "'s " . ucfirst( $type );
		}
		
		return new self( $type, $date, $description, $person );
	}
}