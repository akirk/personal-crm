<?php

namespace PersonalCRM;

/**
 * Register WordPress Abilities API abilities for Personal CRM.
 *
 * Requires the WordPress Abilities API plugin to be installed.
 * Silently no-ops if it is not available.
 */
function register_abilities() {
	if ( ! function_exists( 'wp_register_ability_category' ) || ! function_exists( 'wp_register_ability' ) ) {
		return;
	}

	add_action( 'wp_abilities_api_categories_init', __NAMESPACE__ . '\register_ability_categories' );
	add_action( 'wp_abilities_api_init', __NAMESPACE__ . '\register_crm_abilities' );
}

function register_ability_categories() {
	wp_register_ability_category( 'personal-crm', array(
		'label'       => __( 'Personal CRM', 'personal-crm' ),
		'description' => __( 'Abilities for managing contacts, notes, and meetings in Personal CRM.', 'personal-crm' ),
	) );
}

function register_crm_abilities() {
	wp_register_ability( 'personal-crm/list-people', array(
		'label'       => __( 'List People', 'personal-crm' ),
		'description' => __( 'Returns all people in the CRM with their username, name, email, role, and group memberships. Use this first to find existing contacts before adding notes or meetings.', 'personal-crm' ),
		'category'    => 'personal-crm',
		'output_schema' => array(
			'type'  => 'array',
			'items' => array(
				'type'       => 'object',
				'properties' => array(
					'username' => array( 'type' => 'string', 'description' => 'Unique identifier for the person' ),
					'name'     => array( 'type' => 'string', 'description' => 'Full name' ),
					'email'    => array( 'type' => 'string', 'description' => 'Email address' ),
					'role'     => array( 'type' => 'string', 'description' => 'Job title or role' ),
					'groups'   => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Group names the person belongs to' ),
				),
			),
		),
		'execute_callback'    => __NAMESPACE__ . '\ability_list_people',
		'permission_callback' => __NAMESPACE__ . '\ability_permission_read',
		'meta' => array(
			'show_in_rest'  => true,
			'annotations'   => array(
				'readonly'    => true,
				'destructive' => false,
			),
		),
	) );

	wp_register_ability( 'personal-crm/search-people', array(
		'label'       => __( 'Search People', 'personal-crm' ),
		'description' => __( 'Search for people by name. Returns matches sorted by relevance. Use this to find a specific person when you know part of their name.', 'personal-crm' ),
		'category'    => 'personal-crm',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'query' => array(
					'type'        => 'string',
					'description' => 'Name or partial name to search for',
					'minLength'   => 1,
				),
			),
			'required'             => array( 'query' ),
			'additionalProperties' => false,
		),
		'output_schema' => array(
			'type'  => 'array',
			'items' => array(
				'type'       => 'object',
				'properties' => array(
					'username' => array( 'type' => 'string' ),
					'name'     => array( 'type' => 'string' ),
					'email'    => array( 'type' => 'string' ),
					'role'     => array( 'type' => 'string' ),
				),
			),
		),
		'execute_callback'    => __NAMESPACE__ . '\ability_search_people',
		'permission_callback' => __NAMESPACE__ . '\ability_permission_read',
		'meta' => array(
			'show_in_rest'  => true,
			'annotations'   => array(
				'readonly'    => true,
				'destructive' => false,
			),
		),
	) );

	wp_register_ability( 'personal-crm/get-person', array(
		'label'       => __( 'Get Person', 'personal-crm' ),
		'description' => __( 'Get full details for a specific person by their username. Returns profile fields, group memberships, and recent notes.', 'personal-crm' ),
		'category'    => 'personal-crm',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'username' => array(
					'type'        => 'string',
					'description' => 'The unique username of the person',
					'minLength'   => 1,
				),
			),
			'required'             => array( 'username' ),
			'additionalProperties' => false,
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'username'        => array( 'type' => 'string' ),
				'name'            => array( 'type' => 'string' ),
				'email'           => array( 'type' => 'string' ),
				'role'            => array( 'type' => 'string' ),
				'location'        => array( 'type' => 'string' ),
				'birthday'        => array( 'type' => 'string' ),
				'github'          => array( 'type' => 'string' ),
				'linkedin'        => array( 'type' => 'string' ),
				'wordpress'       => array( 'type' => 'string' ),
				'groups'          => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				'recent_notes'    => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				'personal_events' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
			),
		),
		'execute_callback'    => __NAMESPACE__ . '\ability_get_person',
		'permission_callback' => __NAMESPACE__ . '\ability_permission_read',
		'meta' => array(
			'show_in_rest'  => true,
			'annotations'   => array(
				'readonly'    => true,
				'destructive' => false,
			),
		),
	) );

	wp_register_ability( 'personal-crm/add-person', array(
		'label'       => __( 'Add Person', 'personal-crm' ),
		'description' => __( 'Create a new person in the CRM. A username is auto-generated from the name if not provided. Optionally include an initial note. Returns the username of the created person.', 'personal-crm' ),
		'category'    => 'personal-crm',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'name'     => array( 'type' => 'string', 'description' => 'Full name of the person', 'minLength' => 1 ),
				'username' => array( 'type' => 'string', 'description' => 'Optional unique username. Auto-generated from name if omitted.' ),
				'email'    => array( 'type' => 'string', 'description' => 'Email address' ),
				'role'     => array( 'type' => 'string', 'description' => 'Job title or role' ),
				'location' => array( 'type' => 'string', 'description' => 'City or country' ),
				'note'     => array( 'type' => 'string', 'description' => 'Initial note to add for this person' ),
			),
			'required'             => array( 'name' ),
			'additionalProperties' => false,
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'username' => array( 'type' => 'string', 'description' => 'Username of the created person' ),
				'created'  => array( 'type' => 'boolean', 'description' => 'False if a person with this username already existed' ),
			),
		),
		'execute_callback'    => __NAMESPACE__ . '\ability_add_person',
		'permission_callback' => __NAMESPACE__ . '\ability_permission_write',
		'meta' => array(
			'show_in_rest'  => true,
			'annotations'   => array(
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => false,
			),
		),
	) );

	wp_register_ability( 'personal-crm/add-note', array(
		'label'       => __( 'Add Note', 'personal-crm' ),
		'description' => __( 'Add a text note to an existing person. Notes are timestamped and shown on the person\'s profile page.', 'personal-crm' ),
		'category'    => 'personal-crm',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'username' => array( 'type' => 'string', 'description' => 'Username of the person to add the note to', 'minLength' => 1 ),
				'note'     => array( 'type' => 'string', 'description' => 'The note text', 'minLength' => 1 ),
			),
			'required'             => array( 'username', 'note' ),
			'additionalProperties' => false,
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'success' => array( 'type' => 'boolean' ),
				'note_id' => array( 'type' => 'integer', 'description' => 'ID of the created note' ),
			),
		),
		'execute_callback'    => __NAMESPACE__ . '\ability_add_note',
		'permission_callback' => __NAMESPACE__ . '\ability_permission_write',
		'meta' => array(
			'show_in_rest'  => true,
			'annotations'   => array(
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => false,
			),
		),
	) );

	wp_register_ability( 'personal-crm/add-meeting', array(
		'label'       => __( 'Add Meeting', 'personal-crm' ),
		'description' => __( 'Record that you met with a person on a specific date. Creates a personal event entry on their profile. Use this when processing trip notes or meeting logs.', 'personal-crm' ),
		'category'    => 'personal-crm',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'username'    => array( 'type' => 'string', 'description' => 'Username of the person you met', 'minLength' => 1 ),
				'date'        => array( 'type' => 'string', 'description' => 'Date of meeting in YYYY-MM-DD format', 'pattern' => '^\d{4}-\d{2}-\d{2}$' ),
				'description' => array( 'type' => 'string', 'description' => 'Brief description of the meeting or context', 'minLength' => 1 ),
			),
			'required'             => array( 'username', 'date', 'description' ),
			'additionalProperties' => false,
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'success' => array( 'type' => 'boolean' ),
			),
		),
		'execute_callback'    => __NAMESPACE__ . '\ability_add_meeting',
		'permission_callback' => __NAMESPACE__ . '\ability_permission_write',
		'meta' => array(
			'show_in_rest'  => true,
			'annotations'   => array(
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => false,
			),
		),
	) );
}

function ability_permission_read() {
	return current_user_can( 'read' );
}

function ability_permission_write() {
	return current_user_can( 'edit_posts' );
}

function ability_list_people() {
	$crm     = PersonalCrm::get_instance();
	$storage = $crm->storage;
	$people  = $storage->get_all_people();
	$result  = array();

	foreach ( $people as $person ) {
		$groups = array();
		if ( ! empty( $person->groups ) ) {
			foreach ( $person->groups as $group ) {
				$groups[] = $group['group_name'];
			}
		}
		$result[] = array(
			'username' => $person->username,
			'name'     => $person->name,
			'email'    => $person->email ?? '',
			'role'     => $person->role ?? '',
			'groups'   => $groups,
		);
	}

	return $result;
}

function ability_search_people( $input ) {
	$crm     = PersonalCrm::get_instance();
	$storage = $crm->storage;
	global $wpdb;

	$query   = '%' . $wpdb->esc_like( $input['query'] ) . '%';
	$results = $wpdb->get_results( $wpdb->prepare(
		"SELECT username, name, email, role FROM {$wpdb->prefix}personal_crm_people WHERE name LIKE %s ORDER BY name",
		$query
	), ARRAY_A );

	return $results ?: array();
}

function ability_get_person( $input ) {
	$crm    = PersonalCrm::get_instance();
	$person = $crm->storage->get_person( $input['username'] );

	if ( ! $person ) {
		return new \WP_Error( 'person_not_found', sprintf( __( 'No person found with username "%s".', 'personal-crm' ), $input['username'] ) );
	}

	$groups = array();
	if ( ! empty( $person->groups ) ) {
		foreach ( $person->groups as $group ) {
			$groups[] = $group['group_name'];
		}
	}

	$recent_notes = array();
	if ( ! empty( $person->notes ) ) {
		foreach ( array_slice( $person->notes, 0, 5 ) as $note ) {
			$recent_notes[] = $note['note_text'];
		}
	}

	return array(
		'username'        => $person->username,
		'name'            => $person->name,
		'email'           => $person->email ?? '',
		'role'            => $person->role ?? '',
		'location'        => $person->location ?? '',
		'birthday'        => $person->birthday ?? '',
		'github'          => $person->github ?? '',
		'linkedin'        => $person->linkedin ?? '',
		'wordpress'       => $person->wordpress ?? '',
		'groups'          => $groups,
		'recent_notes'    => $recent_notes,
		'personal_events' => $person->personal_events ?? array(),
	);
}

function ability_add_person( $input ) {
	$crm     = PersonalCrm::get_instance();
	$storage = $crm->storage;

	$name     = sanitize_text_field( $input['name'] );
	$username = isset( $input['username'] ) ? sanitize_text_field( $input['username'] ) : generate_crm_username( $name, $storage );

	$existing = $storage->get_person( $username );
	if ( $existing ) {
		return array( 'username' => $username, 'created' => false );
	}

	$person_data = array(
		'name'            => $name,
		'nickname'        => '',
		'role'            => sanitize_text_field( $input['role'] ?? '' ),
		'email'           => sanitize_email( $input['email'] ?? '' ),
		'location'        => sanitize_text_field( $input['location'] ?? '' ),
		'timezone'        => '',
		'birthday'        => '',
		'company_anniversary' => '',
		'partner'         => '',
		'partner_birthday' => '',
		'github'          => '',
		'linear'          => '',
		'wordpress'       => '',
		'linkedin'        => '',
		'website'         => '',
		'new_company'     => '',
		'new_company_website' => '',
		'deceased_date'   => '',
		'left_company'    => 0,
		'deceased'        => 0,
		'kids'            => array(),
		'github_repos'    => array(),
		'personal_events' => array(),
		'links'           => array(),
	);

	$result = $storage->save_person( $username, $person_data );

	if ( $result === false ) {
		return new \WP_Error( 'save_failed', __( 'Failed to create person.', 'personal-crm' ) );
	}

	if ( ! empty( $input['note'] ) ) {
		$person_id = $storage->get_person_id( $username );
		if ( $person_id ) {
			$storage->add_person_note( $person_id, sanitize_textarea_field( $input['note'] ) );
		}
	}

	return array( 'username' => $username, 'created' => true );
}

function ability_add_note( $input ) {
	$crm       = PersonalCrm::get_instance();
	$storage   = $crm->storage;
	$person_id = $storage->get_person_id( $input['username'] );

	if ( ! $person_id ) {
		return new \WP_Error( 'person_not_found', sprintf( __( 'No person found with username "%s".', 'personal-crm' ), $input['username'] ) );
	}

	$result = $storage->add_person_note( $person_id, sanitize_textarea_field( $input['note'] ) );

	if ( $result === false ) {
		return new \WP_Error( 'save_failed', __( 'Failed to save note.', 'personal-crm' ) );
	}

	global $wpdb;
	return array( 'success' => true, 'note_id' => $wpdb->insert_id );
}

function ability_add_meeting( $input ) {
	$crm     = PersonalCrm::get_instance();
	$storage = $crm->storage;
	$username = $input['username'];

	$person = $storage->get_person( $username );
	if ( ! $person ) {
		return new \WP_Error( 'person_not_found', sprintf( __( 'No person found with username "%s".', 'personal-crm' ), $username ) );
	}

	$events   = $person->personal_events ?? array();
	$events[] = array(
		'date'        => sanitize_text_field( $input['date'] ),
		'type'        => 'other',
		'description' => sanitize_text_field( $input['description'] ),
	);

	$person_data = array(
		'name'                => $person->name,
		'nickname'            => $person->nickname ?? '',
		'role'                => $person->role ?? '',
		'email'               => $person->email ?? '',
		'birthday'            => $person->birthday ?? '',
		'company_anniversary' => $person->company_anniversary ?? '',
		'partner'             => $person->partner ?? '',
		'partner_birthday'    => $person->partner_birthday ?? '',
		'location'            => $person->location ?? '',
		'timezone'            => $person->timezone ?? '',
		'github'              => $person->github ?? '',
		'linear'              => $person->linear ?? '',
		'wordpress'           => $person->wordpress ?? '',
		'linkedin'            => $person->linkedin ?? '',
		'website'             => $person->website ?? '',
		'new_company'         => $person->new_company ?? '',
		'new_company_website' => $person->new_company_website ?? '',
		'deceased_date'       => $person->deceased_date ?? '',
		'left_company'        => $person->left_company ?? 0,
		'deceased'            => $person->deceased ?? 0,
		'kids'                => $person->kids ?? array(),
		'github_repos'        => $person->github_repos ?? array(),
		'personal_events'     => $events,
		'links'               => $person->links ?? array(),
	);

	$groups_with_dates = array();
	if ( ! empty( $person->groups ) ) {
		foreach ( $person->groups as $group ) {
			$groups_with_dates[ $group['id'] ] = array(
				'joined_date' => ! empty( $group['group_joined_date'] ) ? substr( $group['group_joined_date'], 0, 10 ) : null,
				'left_date'   => ! empty( $group['group_left_date'] ) ? substr( $group['group_left_date'], 0, 10 ) : null,
			);
		}
	}

	$result = $storage->save_person( $username, $person_data, $groups_with_dates );

	if ( $result === false ) {
		return new \WP_Error( 'save_failed', __( 'Failed to save meeting.', 'personal-crm' ) );
	}

	return array( 'success' => true );
}

/**
 * Generate a unique username from a display name.
 */
function generate_crm_username( $name, $storage ) {
	$base = strtolower( $name );

	if ( class_exists( 'Transliterator' ) ) {
		$transliterator = \Transliterator::create( 'Any-Latin; Latin-ASCII' );
		if ( $transliterator ) {
			$base = $transliterator->transliterate( $base );
		}
	}

	$base = preg_replace( '/[^a-z0-9\s-]/', '', $base );
	$base = preg_replace( '/\s+/', '-', trim( $base ) );
	$base = preg_replace( '/-+/', '-', $base );

	$username = $base;
	$suffix   = 2;
	while ( $storage->get_person_id( $username ) ) {
		$username = $base . '-' . $suffix;
		$suffix++;
	}

	return $username;
}
