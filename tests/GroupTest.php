<?php

use PersonalCRM\Group;
use PHPUnit\Framework\TestCase;

class GroupTest extends TestCase {
	private function group_data( $overrides = array() ) {
		return array_merge(
			array(
				'id'         => 1,
				'slug'       => 'example_subgroup',
				'group_name' => 'Example Subgroup',
			),
			$overrides
		);
	}

	public function test_subgroup_events_are_included_in_parent_by_default() {
		$group = new Group( $this->group_data(), null );

		$this->assertTrue( $group->include_events_in_parent );
	}

	public function test_subgroup_events_can_be_excluded_from_parent() {
		$group = new Group(
			$this->group_data( array( 'include_events_in_parent' => 0 ) ),
			null
		);

		$this->assertFalse( $group->include_events_in_parent );
	}
}
