<?php
namespace PersonalCRM;

/**
 * Shared helper functions used across multiple tabs
 *
 * Person-specific functions have been moved to tabs/people.php
 * This file now only contains functions used by multiple tabs
 */

/**
 * Check which data points are missing for a person
 * Used by: people.php (forms) and audit.php (completeness tracking)
 */
function get_missing_data_points( $person, $person_type = 'member', $group_slug = null ) {
	$missing = array();
	$is_social_group = PersonalCrm::get_instance()->is_social_group( $group_slug );

	// Core fields (required)
	if ( empty( $person->name ) ) {
		$missing[] = array( 'field' => 'Name', 'priority' => 'required' );
	}

	// Role is only required for business teams, not social groups
	if ( ! $is_social_group && empty( $person->role ) ) {
		$missing[] = array( 'field' => 'Role', 'priority' => 'required' );
	}
	if ( empty( $person->location ) ) {
		$missing[] = array( 'field' => 'Location', 'priority' => 'required' );
	}
	if ( empty( $person->timezone ) ) {
		$missing[] = array( 'field' => 'Timezone', 'priority' => 'required' );
	}

	// Birthday (not required for consultants)
	if ( empty( $person->birthday ) && $person_type !== 'consultants' ) {
		$missing[] = array( 'field' => 'Birthday', 'priority' => 'required' );
	}

	// Company anniversary is only required for business teams, not social groups
	if ( ! $is_social_group && empty( $person->company_anniversary ) ) {
		$missing[] = array( 'field' => 'Company Anniversary', 'priority' => 'required' );
	}

	// Links - check for key links (1:1 doc not required for consultants or social groups)
	$expected_links = array();
	if ( $person_type !== 'consultants' && ! $is_social_group ) {
		$expected_links[] = '1:1 doc';
	}

	foreach ( $expected_links as $expected_link ) {
		if ( ! isset( $person->links[ $expected_link ] ) || empty( $person->links[ $expected_link ] ) ) {
			$missing[] = array( 'field' => $expected_link . ' link', 'priority' => 'required' );
		}
	}

	// Recommended fields - likely to be filled out for most people
	if ( empty( $person->email ) ) {
		$missing[] = array( 'field' => 'Primary email address', 'priority' => 'recommended' );
	}
	if ( empty( $person->website ) ) {
		$missing[] = array( 'field' => 'Website', 'priority' => 'recommended' );
	}
	if ( empty( $person->wordpress ) ) {
		$missing[] = array( 'field' => 'WordPress.org profile', 'priority' => 'recommended' );
	}
	if ( empty( $person->linkedin ) ) {
		$missing[] = array( 'field' => 'LinkedIn profile', 'priority' => 'recommended' );
	}
	if ( empty( $person->partner ) ) {
		$missing[] = array( 'field' => 'Partner', 'priority' => 'recommended' );
	}

	// Optional fields - often rightfully stay empty
	if ( empty( $person->kids ) ) {
		$missing[] = array( 'field' => 'Kids info', 'priority' => 'optional' );
	}
	if ( empty( $person->notes ) || ( is_array( $person->notes ) && count( $person->notes ) === 0 ) ) {
		$missing[] = array( 'field' => 'Notes', 'priority' => 'optional' );
	}

	return $missing;
}

/**
 * Get completeness score as percentage
 * Used by: people.php (forms) and audit.php (completeness tracking)
 */
function get_completeness_score( $missing_data, $person_type = 'member', $group_slug = null ) {
	$is_social_group = PersonalCrm::get_instance()->is_social_group( $group_slug );

	// Count total fields by priority
	if ( $is_social_group ) {
		$total_required = 4; // name, location, timezone, birthday (no role, company_anniversary, 1:1 doc)
	} else {
		$total_required = 7; // name, role, location, timezone, birthday, company_anniversary, 1:1 doc
	}
	$total_recommended = 3; // wordpress.org, linkedin, partner
	$total_optional = 2; // kids, notes

	// Count missing fields by priority
	$missing_required = 0;
	$missing_recommended = 0;
	$missing_optional = 0;

	foreach ( $missing_data as $missing_item ) {
		if ( is_array( $missing_item ) ) {
			switch ( $missing_item['priority'] ) {
				case 'required':
					$missing_required++;
					break;
				case 'recommended':
					$missing_recommended++;
					break;
				case 'optional':
					$missing_optional++;
					break;
			}
		} else {
			// Backwards compatibility - treat string items as required if not marked optional
			if ( strpos( $missing_item, 'optional' ) === false ) {
				$missing_required++;
			} else {
				$missing_recommended++;
			}
		}
	}

	// Calculate weighted score
	// Required fields: 70% weight
	// Recommended fields: 25% weight
	// Optional fields: 5% weight
	$required_score = ( ( $total_required - $missing_required ) / $total_required ) * 70;
	$recommended_score = ( ( $total_recommended - $missing_recommended ) / $total_recommended ) * 25;
	$optional_score = ( ( $total_optional - $missing_optional ) / $total_optional ) * 5;

	$total_score = $required_score + $recommended_score + $optional_score;

	return max( 0, round( $total_score ) );
}
