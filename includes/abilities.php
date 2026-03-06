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
		'description' => __( 'Returns all people in the CRM with their username, name, email, role, and group memberships.', 'personal-crm' ),
		'category'    => 'personal-crm',
		'output_schema' => array(
			'type'  => 'array',
			'items' => array(
				'type'       => 'object',
				'properties' => array(
					'username' => array( 'type' => 'string', 'description' => 'Unique identifier used in all other abilities' ),
					'name'     => array( 'type' => 'string', 'description' => 'Full display name' ),
					'email'    => array( 'type' => 'string' ),
					'role'     => array( 'type' => 'string' ),
					'groups'   => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				),
			),
		),
		'execute_callback'    => __NAMESPACE__ . '\ability_list_people',
		'permission_callback' => __NAMESPACE__ . '\ability_permission_read',
		'meta' => array(
			'show_in_rest' => true,
			'annotations'  => array(
				'instructions' => 'Use when matching multiple names at once (e.g. bulk trip notes). For a single name lookup use search-people instead, which is faster. Match names from the source text against the "name" field. When a name is ambiguous and multiple people could match, call get-person on each candidate and compare their recent_notes and role to decide.',
				'readonly'     => true,
				'destructive'  => false,
			),
		),
	) );

	wp_register_ability( 'personal-crm/search-people', array(
		'label'       => __( 'Search People', 'personal-crm' ),
		'description' => __( 'Search for a person by name, first name, last name, or nickname. Returns ranked matches.', 'personal-crm' ),
		'category'    => 'personal-crm',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'query' => array(
					'type'        => 'string',
					'description' => 'Full or partial name to search for (e.g. "Jane", "Smith", "Jane Smith")',
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
					'username'      => array( 'type' => 'string' ),
					'name'          => array( 'type' => 'string' ),
					'email'         => array( 'type' => 'string' ),
					'role'          => array( 'type' => 'string' ),
					'match_quality' => array( 'type' => 'string', 'description' => 'exact, full, first, last, or nickname' ),
				),
			),
		),
		'execute_callback'    => __NAMESPACE__ . '\ability_search_people',
		'permission_callback' => __NAMESPACE__ . '\ability_permission_read',
		'meta' => array(
			'show_in_rest' => true,
			'annotations'  => array(
				'instructions' => 'Always call this before add-person to check if the person already exists. If multiple results are returned for an ambiguous name (e.g. first name only), call get-person on each to inspect role and recent_notes, then pick the best match. Only call add-person when this returns an empty array.',
				'readonly'     => true,
				'destructive'  => false,
			),
		),
	) );

	wp_register_ability( 'personal-crm/get-person', array(
		'label'       => __( 'Get Person', 'personal-crm' ),
		'description' => __( 'Get full details for a specific person by their username, including profile fields, group memberships, recent notes, and personal events.', 'personal-crm' ),
		'category'    => 'personal-crm',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'username' => array( 'type' => 'string', 'description' => 'The unique username of the person', 'minLength' => 1 ),
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
				'recent_notes'    => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Up to 5 most recent notes' ),
				'personal_events' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ), 'description' => 'Past meetings and events' ),
			),
		),
		'execute_callback'    => __NAMESPACE__ . '\ability_get_person',
		'permission_callback' => __NAMESPACE__ . '\ability_permission_read',
		'meta' => array(
			'show_in_rest' => true,
			'annotations'  => array(
				'instructions' => 'Use to disambiguate between multiple search results: compare role and recent_notes to determine which person matches your source text. Also use before adding notes or meetings to check whether the same information was already recorded (look at personal_events dates and recent_notes content).',
				'readonly'     => true,
				'destructive'  => false,
			),
		),
	) );

	wp_register_ability( 'personal-crm/list-groups', array(
		'label'       => __( 'List Groups', 'personal-crm' ),
		'description' => __( 'Returns all groups in the CRM with their slug, name, type, and member count.', 'personal-crm' ),
		'category'    => 'personal-crm',
		'output_schema' => array(
			'type'  => 'array',
			'items' => array(
				'type'       => 'object',
				'properties' => array(
					'slug'         => array( 'type' => 'string', 'description' => 'Unique identifier for the group' ),
					'name'         => array( 'type' => 'string' ),
					'type'         => array( 'type' => 'string', 'description' => 'e.g. team, social, family' ),
					'icon'         => array( 'type' => 'string' ),
					'member_count' => array( 'type' => 'integer' ),
					'parent_slug'  => array( 'type' => 'string', 'description' => 'Slug of parent group, or empty if top-level' ),
				),
			),
		),
		'execute_callback'    => __NAMESPACE__ . '\ability_list_groups',
		'permission_callback' => __NAMESPACE__ . '\ability_permission_read',
		'meta' => array(
			'show_in_rest' => true,
			'annotations'  => array(
				'instructions' => 'Use when the source text mentions an event, conference, or organization (e.g. "WordCamp Vienna", "team offsite"). Find the matching group and mention its name in meeting notes for context. Groups are not directly attached to personal meetings — they provide context only.',
				'readonly'     => true,
				'destructive'  => false,
			),
		),
	) );

	wp_register_ability( 'personal-crm/create-group', array(
		'label'       => __( 'Create Group', 'personal-crm' ),
		'description' => __( 'Create a new group in the CRM. Returns the slug of the created group, which can be used to assign people to it.', 'personal-crm' ),
		'category'    => 'personal-crm',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'name' => array( 'type' => 'string', 'description' => 'Display name of the group', 'minLength' => 1 ),
				'type' => array(
					'type'        => 'string',
					'description' => 'Group type: "team" for work groups, "social" for friend/family circles',
					'enum'        => array( 'team', 'social' ),
					'default'     => 'social',
				),
				'icon'      => array( 'type' => 'string', 'description' => 'Optional emoji icon, e.g. "🏠"' ),
				'parent_slug' => array( 'type' => 'string', 'description' => 'Slug of parent group to nest under (optional)' ),
			),
			'required'             => array( 'name' ),
			'additionalProperties' => false,
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'slug'    => array( 'type' => 'string', 'description' => 'Use this slug in assign-person-to-group' ),
				'created' => array( 'type' => 'boolean', 'description' => 'False if a group with this slug already existed' ),
			),
		),
		'execute_callback'    => __NAMESPACE__ . '\ability_create_group',
		'permission_callback' => __NAMESPACE__ . '\ability_permission_write',
		'meta' => array(
			'show_in_rest' => true,
			'annotations'  => array(
				'instructions' => 'Call list-groups first to check whether the group already exists. Only call this if no matching group is found. Use type "social" for family/friend/neighbourhood groups, "team" for work/project groups. The returned slug is what you pass to assign-person-to-group.',
				'readonly'     => false,
				'destructive'  => false,
				'idempotent'   => false,
			),
		),
	) );

	wp_register_ability( 'personal-crm/assign-person-to-group', array(
		'label'       => __( 'Assign Person to Group', 'personal-crm' ),
		'description' => __( 'Add an existing person to a group. Safe to call even if they are already a member.', 'personal-crm' ),
		'category'    => 'personal-crm',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'username'   => array( 'type' => 'string', 'description' => 'Username of the person', 'minLength' => 1 ),
				'group_slug' => array( 'type' => 'string', 'description' => 'Slug of the group (from list-groups or create-group)', 'minLength' => 1 ),
				'joined_date' => array( 'type' => 'string', 'description' => 'Optional join date in YYYY-MM-DD format', 'pattern' => '^\d{4}-\d{2}-\d{2}$' ),
			),
			'required'             => array( 'username', 'group_slug' ),
			'additionalProperties' => false,
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'success'       => array( 'type' => 'boolean' ),
				'already_member' => array( 'type' => 'boolean', 'description' => 'True if the person was already in the group' ),
			),
		),
		'execute_callback'    => __NAMESPACE__ . '\ability_assign_person_to_group',
		'permission_callback' => __NAMESPACE__ . '\ability_permission_write',
		'meta' => array(
			'show_in_rest' => true,
			'annotations'  => array(
				'instructions' => 'Use after create-group (or with a slug from list-groups) to add a person to a group. This is idempotent — calling it again for an existing member is safe and returns already_member: true. Use search-people or add-person first to obtain the username.',
				'readonly'     => false,
				'destructive'  => false,
				'idempotent'   => true,
			),
		),
	) );

	wp_register_ability( 'personal-crm/add-person', array(
		'label'       => __( 'Add Person', 'personal-crm' ),
		'description' => __( 'Create a new person in the CRM. Username is auto-generated from name if not provided. Returns the username and whether the person was newly created.', 'personal-crm' ),
		'category'    => 'personal-crm',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'name'     => array( 'type' => 'string', 'description' => 'Full name', 'minLength' => 1 ),
				'username' => array( 'type' => 'string', 'description' => 'Optional. Auto-generated from name if omitted.' ),
				'email'    => array( 'type' => 'string', 'description' => 'Email address' ),
				'role'     => array( 'type' => 'string', 'description' => 'Job title or role' ),
				'location' => array( 'type' => 'string', 'description' => 'City or country' ),
				'note'     => array( 'type' => 'string', 'description' => 'Initial note to attach' ),
			),
			'required'             => array( 'name' ),
			'additionalProperties' => false,
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'username' => array( 'type' => 'string', 'description' => 'Use this in subsequent add-note and add-meeting calls' ),
				'created'  => array( 'type' => 'boolean', 'description' => 'False means a person with this username already existed — use the returned username to add notes, do not retry' ),
			),
		),
		'execute_callback'    => __NAMESPACE__ . '\ability_add_person',
		'permission_callback' => __NAMESPACE__ . '\ability_permission_write',
		'meta' => array(
			'show_in_rest' => true,
			'annotations'  => array(
				'instructions' => 'Only call this after search-people returned no results. If created is false, someone with that username already exists — use the returned username to add notes instead of calling add-person again.',
				'readonly'     => false,
				'destructive'  => false,
				'idempotent'   => false,
			),
		),
	) );

	wp_register_ability( 'personal-crm/add-note', array(
		'label'       => __( 'Add Note', 'personal-crm' ),
		'description' => __( 'Add a timestamped text note to an existing person.', 'personal-crm' ),
		'category'    => 'personal-crm',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'username' => array( 'type' => 'string', 'description' => 'Username of the person', 'minLength' => 1 ),
				'note'     => array( 'type' => 'string', 'description' => 'The note text', 'minLength' => 1 ),
			),
			'required'             => array( 'username', 'note' ),
			'additionalProperties' => false,
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'success' => array( 'type' => 'boolean' ),
				'note_id' => array( 'type' => 'integer' ),
			),
		),
		'execute_callback'    => __NAMESPACE__ . '\ability_add_note',
		'permission_callback' => __NAMESPACE__ . '\ability_permission_write',
		'meta' => array(
			'show_in_rest' => true,
			'annotations'  => array(
				'instructions' => 'Use for context, background, or observations without a specific meeting date. When you physically met someone, prefer add-meeting (which records the date in their timeline) and optionally also call add-note for detailed context.',
				'readonly'     => false,
				'destructive'  => false,
				'idempotent'   => false,
			),
		),
	) );

	wp_register_ability( 'personal-crm/add-meeting', array(
		'label'       => __( 'Add Meeting', 'personal-crm' ),
		'description' => __( 'Record a meeting with a person on a specific date. Adds a dated personal event to their profile timeline.', 'personal-crm' ),
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
			'show_in_rest' => true,
			'annotations'  => array(
				'instructions' => 'Preferred over add-note when you physically met someone — the date is recorded in their event timeline. Before calling, check get-person personal_events to avoid adding a duplicate entry for the same date. You can also call add-note alongside this for longer context.',
				'readonly'     => false,
				'destructive'  => false,
				'idempotent'   => false,
			),
		),
	) );

	wp_register_ability( 'personal-crm/import-meetings', array(
		'label'       => __( 'Import Meetings', 'personal-crm' ),
		'description' => __( 'Batch-import meetings from resolved trip notes in a single call. Each entry is either an existing person (provide username) or a new person (provide name). Adds a meeting event and optional note for each entry. Returns a per-entry summary.', 'personal-crm' ),
		'category'    => 'personal-crm',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'meetings' => array(
					'type'        => 'array',
					'description' => 'List of resolved meeting entries',
					'minItems'    => 1,
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'username'    => array( 'type' => 'string', 'description' => 'Username of existing person. Provide this OR name, not both.' ),
							'name'        => array( 'type' => 'string', 'description' => 'Full name for a new person (when username is absent).' ),
							'email'       => array( 'type' => 'string', 'description' => 'Email for new person (optional)' ),
							'role'        => array( 'type' => 'string', 'description' => 'Role/title for new person (optional)' ),
							'location'    => array( 'type' => 'string', 'description' => 'Location for new person (optional)' ),
							'date'        => array( 'type' => 'string', 'description' => 'Meeting date in YYYY-MM-DD format', 'pattern' => '^\d{4}-\d{2}-\d{2}$' ),
							'description' => array( 'type' => 'string', 'description' => 'Short meeting description for the event timeline entry' ),
							'note'        => array( 'type' => 'string', 'description' => 'Longer note to attach (optional, supplements description)' ),
						),
						'required' => array( 'date', 'description' ),
					),
				),
			),
			'required'             => array( 'meetings' ),
			'additionalProperties' => false,
		),
		'output_schema' => array(
			'type'  => 'array',
			'items' => array(
				'type'       => 'object',
				'properties' => array(
					'username' => array( 'type' => 'string' ),
					'name'     => array( 'type' => 'string' ),
					'action'   => array( 'type' => 'string', 'description' => 'created, updated, or error' ),
					'error'    => array( 'type' => 'string', 'description' => 'Error message if action is error' ),
				),
			),
		),
		'execute_callback'    => __NAMESPACE__ . '\ability_import_meetings',
		'permission_callback' => __NAMESPACE__ . '\ability_permission_write',
		'meta' => array(
			'show_in_rest' => true,
			'annotations'  => array(
				'instructions' => 'Use this for efficient batch processing after you have resolved all names via search-people or list-people. For each entry: provide "username" if the person exists, or "name" (plus optional email/role) if they are new. The "description" becomes the timeline event; "note" is an optional longer text note. Check the returned "action" for each entry — entries with action "error" were not saved.',
				'readonly'     => false,
				'destructive'  => false,
				'idempotent'   => false,
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
	$crm    = PersonalCrm::get_instance();
	$people = $crm->storage->get_all_people();
	$result = array();

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
	global $wpdb;

	$raw   = $input['query'];
	$words = preg_split( '/\s+/', trim( $raw ), -1, PREG_SPLIT_NO_EMPTY );
	$full  = '%' . $wpdb->esc_like( $raw ) . '%';
	$first = '%' . $wpdb->esc_like( $words[0] ) . '%';
	$last  = count( $words ) > 1 ? '%' . $wpdb->esc_like( end( $words ) ) . '%' : null;

	// Fetch candidates matching full query, first word, last word, or nickname.
	// Use UNION to keep the query readable while allowing nickname search.
	$sql = $wpdb->prepare(
		"SELECT username, name, email, role, nickname,
		        CASE
		            WHEN LOWER(name) = LOWER(%s)        THEN 'exact'
		            WHEN name LIKE %s                   THEN 'full'
		            WHEN name LIKE %s                   THEN 'first'
		            WHEN nickname LIKE %s               THEN 'nickname'
		            ELSE 'last'
		        END AS match_quality
		 FROM {$wpdb->prefix}personal_crm_people
		 WHERE name LIKE %s OR name LIKE %s OR nickname LIKE %s",
		$raw, $full, $first, $full,
		$full, $first, $full
	);

	if ( $last ) {
		$sql = $wpdb->prepare(
			"SELECT username, name, email, role, nickname,
			        CASE
			            WHEN LOWER(name) = LOWER(%s)    THEN 'exact'
			            WHEN name LIKE %s               THEN 'full'
			            WHEN name LIKE %s               THEN 'first'
			            WHEN name LIKE %s               THEN 'last'
			            WHEN nickname LIKE %s           THEN 'nickname'
			            ELSE 'last'
			        END AS match_quality
			 FROM {$wpdb->prefix}personal_crm_people
			 WHERE name LIKE %s OR name LIKE %s OR name LIKE %s OR nickname LIKE %s",
			$raw, $full, $first, $last, $full,
			$full, $first, $last, $full
		);
	}

	$rows = $wpdb->get_results( $sql, ARRAY_A );
	if ( ! $rows ) {
		return array();
	}

	// Sort: exact → full → first → last → nickname, then alphabetically.
	$order = array( 'exact' => 0, 'full' => 1, 'first' => 2, 'last' => 3, 'nickname' => 4 );
	usort( $rows, function( $a, $b ) use ( $order ) {
		$diff = ( $order[ $a['match_quality'] ] ?? 5 ) - ( $order[ $b['match_quality'] ] ?? 5 );
		return $diff !== 0 ? $diff : strcmp( $a['name'], $b['name'] );
	} );

	return array_map( function( $row ) {
		return array(
			'username'      => $row['username'],
			'name'          => $row['name'],
			'email'         => $row['email'],
			'role'          => $row['role'],
			'match_quality' => $row['match_quality'],
		);
	}, $rows );
}

function ability_list_groups() {
	global $wpdb;

	$groups = $wpdb->get_results(
		"SELECT g.id, g.slug, g.group_name, g.type, g.display_icon, g.parent_id,
		        (SELECT COUNT(*) FROM {$wpdb->prefix}personal_crm_people_groups pg WHERE pg.group_id = g.id) AS member_count
		 FROM {$wpdb->prefix}personal_crm_groups g
		 ORDER BY COALESCE(g.parent_id, 0), g.sort_order, g.group_name",
		ARRAY_A
	);

	if ( ! $groups ) {
		return array();
	}

	$slug_by_id = array();
	foreach ( $groups as $g ) {
		$slug_by_id[ $g['id'] ] = $g['slug'];
	}

	return array_map( function( $g ) use ( $slug_by_id ) {
		return array(
			'slug'         => $g['slug'],
			'name'         => $g['group_name'],
			'type'         => $g['type'] ?? '',
			'icon'         => $g['display_icon'] ?? '',
			'member_count' => (int) $g['member_count'],
			'parent_slug'  => $g['parent_id'] ? ( $slug_by_id[ $g['parent_id'] ] ?? '' ) : '',
		);
	}, $groups );
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

function ability_create_group( $input ) {
	$crm     = PersonalCrm::get_instance();
	$storage = $crm->storage;

	$name = sanitize_text_field( $input['name'] );
	$type = sanitize_text_field( $input['type'] ?? 'social' );
	$icon = sanitize_text_field( $input['icon'] ?? '' );

	// Build base slug from name.
	$base_slug = sanitize_title( $name );
	$base_slug = str_replace( '-', '_', $base_slug );

	// Handle optional parent.
	$parent_id = null;
	if ( ! empty( $input['parent_slug'] ) ) {
		$parent = $storage->get_group( sanitize_text_field( $input['parent_slug'] ) );
		if ( $parent ) {
			$parent_id = $parent->id;
			$base_slug = $parent->slug . '_' . $base_slug;
		}
	}

	// Check if it already exists.
	$existing = $storage->get_group( $base_slug );
	if ( $existing ) {
		return array( 'slug' => $base_slug, 'created' => false );
	}

	$config = array(
		'group_name'          => $name,
		'type'                => $type,
		'display_icon'        => $icon,
		'parent_id'           => $parent_id,
		'activity_url_prefix' => '',
		'sort_order'          => 0,
		'default'             => 0,
	);

	$slug = $storage->save_group( null, $config );

	if ( ! $slug ) {
		return new \WP_Error( 'save_failed', __( 'Failed to create group.', 'personal-crm' ) );
	}

	return array( 'slug' => $slug, 'created' => true );
}

function ability_assign_person_to_group( $input ) {
	$crm     = PersonalCrm::get_instance();
	$storage = $crm->storage;

	$username   = $input['username'];
	$group_slug = $input['group_slug'];

	$person_id = $storage->get_person_id( $username );
	if ( ! $person_id ) {
		return new \WP_Error( 'person_not_found', sprintf( __( 'No person found with username "%s".', 'personal-crm' ), $username ) );
	}

	$group = $storage->get_group( $group_slug );
	if ( ! $group ) {
		return new \WP_Error( 'group_not_found', sprintf( __( 'No group found with slug "%s".', 'personal-crm' ), $group_slug ) );
	}

	global $wpdb;
	$existing = $wpdb->get_var( $wpdb->prepare(
		"SELECT id FROM {$wpdb->prefix}personal_crm_people_groups WHERE person_id = %d AND group_id = %d",
		$person_id, $group->id
	) );

	if ( $existing ) {
		return array( 'success' => true, 'already_member' => true );
	}

	$joined_date = ! empty( $input['joined_date'] ) ? $input['joined_date'] : 'default';
	$result      = $storage->add_person_to_group( $person_id, $group->id, $joined_date );

	if ( $result === false ) {
		return new \WP_Error( 'save_failed', __( 'Failed to assign person to group.', 'personal-crm' ) );
	}

	return array( 'success' => true, 'already_member' => false );
}

function ability_add_person( $input ) {
	$crm     = PersonalCrm::get_instance();
	$storage = $crm->storage;

	$name     = sanitize_text_field( $input['name'] );
	$username = ! empty( $input['username'] )
		? sanitize_text_field( $input['username'] )
		: generate_crm_username( $name, $storage );

	if ( $storage->get_person( $username ) ) {
		return array( 'username' => $username, 'created' => false );
	}

	$person_data = build_empty_person_data( $name, $input );
	$result      = $storage->save_person( $username, $person_data );

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
	$person  = $storage->get_person( $input['username'] );

	if ( ! $person ) {
		return new \WP_Error( 'person_not_found', sprintf( __( 'No person found with username "%s".', 'personal-crm' ), $input['username'] ) );
	}

	$result = save_meeting_event( $storage, $person, $input['date'], $input['description'] );

	if ( $result === false ) {
		return new \WP_Error( 'save_failed', __( 'Failed to save meeting.', 'personal-crm' ) );
	}

	return array( 'success' => true );
}

function ability_import_meetings( $input ) {
	$crm     = PersonalCrm::get_instance();
	$storage = $crm->storage;
	$results = array();

	foreach ( $input['meetings'] as $entry ) {
		$username = ! empty( $entry['username'] ) ? sanitize_text_field( $entry['username'] ) : null;
		$action   = 'updated';

		// Create new person if no username was given.
		if ( ! $username ) {
			if ( empty( $entry['name'] ) ) {
				$results[] = array(
					'username' => '',
					'name'     => '',
					'action'   => 'error',
					'error'    => 'Each entry must have either "username" or "name".',
				);
				continue;
			}

			$name     = sanitize_text_field( $entry['name'] );
			$username = generate_crm_username( $name, $storage );

			if ( ! $storage->get_person( $username ) ) {
				$person_data = build_empty_person_data( $name, $entry );
				if ( $storage->save_person( $username, $person_data ) === false ) {
					$results[] = array(
						'username' => $username,
						'name'     => $name,
						'action'   => 'error',
						'error'    => 'Failed to create person.',
					);
					continue;
				}
				$action = 'created';
			}
		}

		$person = $storage->get_person( $username );
		if ( ! $person ) {
			$results[] = array(
				'username' => $username,
				'name'     => $entry['name'] ?? '',
				'action'   => 'error',
				'error'    => sprintf( 'No person found with username "%s".', $username ),
			);
			continue;
		}

		// Add the meeting event.
		if ( ! empty( $entry['date'] ) && ! empty( $entry['description'] ) ) {
			$saved = save_meeting_event( $storage, $person, $entry['date'], $entry['description'] );
			if ( $saved === false ) {
				$results[] = array(
					'username' => $username,
					'name'     => $person->name,
					'action'   => 'error',
					'error'    => 'Failed to save meeting event.',
				);
				continue;
			}
			// Re-fetch so subsequent note saves have fresh state.
			$person = $storage->get_person( $username );
		}

		// Attach optional longer note.
		if ( ! empty( $entry['note'] ) ) {
			$person_id = $storage->get_person_id( $username );
			if ( $person_id ) {
				$storage->add_person_note( $person_id, sanitize_textarea_field( $entry['note'] ) );
			}
		}

		$results[] = array(
			'username' => $username,
			'name'     => $person->name,
			'action'   => $action,
			'error'    => '',
		);
	}

	return $results;
}

/**
 * Append a meeting personal_event to a person and save.
 *
 * @return int|false wpdb result.
 */
function save_meeting_event( $storage, $person, $date, $description ) {
	$events   = $person->personal_events ?? array();
	$events[] = array(
		'date'        => sanitize_text_field( $date ),
		'type'        => 'other',
		'description' => sanitize_text_field( $description ),
	);

	$person_data                    = person_object_to_data( $person );
	$person_data['personal_events'] = $events;

	$groups_with_dates = array();
	if ( ! empty( $person->groups ) ) {
		foreach ( $person->groups as $group ) {
			$groups_with_dates[ $group['id'] ] = array(
				'joined_date' => ! empty( $group['group_joined_date'] ) ? substr( $group['group_joined_date'], 0, 10 ) : null,
				'left_date'   => ! empty( $group['group_left_date'] ) ? substr( $group['group_left_date'], 0, 10 ) : null,
			);
		}
	}

	return $storage->save_person( $person->username, $person_data, $groups_with_dates );
}

/**
 * Convert a person object to the array format expected by save_person().
 */
function person_object_to_data( $person ) {
	return array(
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
		'personal_events'     => $person->personal_events ?? array(),
		'links'               => $person->links ?? array(),
	);
}

/**
 * Build a blank person data array for a new contact.
 */
function build_empty_person_data( $name, $input ) {
	return array(
		'name'                => $name,
		'nickname'            => '',
		'role'                => sanitize_text_field( $input['role'] ?? '' ),
		'email'               => sanitize_email( $input['email'] ?? '' ),
		'location'            => sanitize_text_field( $input['location'] ?? '' ),
		'timezone'            => '',
		'birthday'            => '',
		'company_anniversary' => '',
		'partner'             => '',
		'partner_birthday'    => '',
		'github'              => '',
		'linear'              => '',
		'wordpress'           => '',
		'linkedin'            => '',
		'website'             => '',
		'new_company'         => '',
		'new_company_website' => '',
		'deceased_date'       => '',
		'left_company'        => 0,
		'deceased'            => 0,
		'kids'                => array(),
		'github_repos'        => array(),
		'personal_events'     => array(),
		'links'               => array(),
	);
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
