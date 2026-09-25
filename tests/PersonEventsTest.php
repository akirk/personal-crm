<?php

use PersonalCRM\Person;
use PHPUnit\Framework\TestCase;

class PersonEventsTest extends TestCase {
	private function create_person() {
		$person = new Person( 'Example Person', 'example' );
		$person->birthday = date( 'Y-m-d', strtotime( '+10 days -30 years' ) );
		$person->company_anniversary = date( 'Y-m-d', strtotime( '+10 days -5 years' ) );

		return $person;
	}

	public function test_current_alumni_membership_excludes_company_anniversary() {
		$person = $this->create_person();
		$person->groups = array(
			array(
				'slug'            => 'example_alumni',
				'group_name'      => 'Alumni',
				'group_left_date' => null,
			),
		);

		$event_types = array_column( $person->get_upcoming_events(), 'type' );

		$this->assertContains( 'birthday', $event_types );
		$this->assertNotContains( 'anniversary', $event_types );
	}

	public function test_historical_alumni_membership_does_not_exclude_company_anniversary() {
		$person = $this->create_person();
		$person->groups = array(
			array(
				'slug'            => 'example_alumni',
				'group_name'      => 'Alumni',
				'group_left_date' => '2025-01-01 00:00:00',
			),
		);

		$event_types = array_column( $person->get_upcoming_events(), 'type' );

		$this->assertContains( 'anniversary', $event_types );
	}

	public function test_personal_dates_variant_excludes_alumni_anniversary() {
		$person = $this->create_person();
		$person->groups = array(
			array(
				'slug'            => 'example_alumni',
				'group_name'      => 'Alumni',
				'group_left_date' => null,
			),
		);

		$event_types = array_map(
			function( $event ) {
				return $event->type;
			},
			$person->get_upcoming_events_with_personal_dates()
		);

		$this->assertContains( 'birthday', $event_types );
		$this->assertNotContains( 'anniversary', $event_types );
	}
}
