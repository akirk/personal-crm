<?php
/**
 * WordPress Database Storage Class
 *
 * WordPress database storage using wpdb for compatibility with WordPress infrastructure.
 * Works with WordPress wpdb, MySQL wpdb polyfill, or SQLite wpdb subclass.
 */

namespace PersonalCRM;

// Database classes and BaseStorage are now in wp-app
// They're loaded via wp-app's autoload system

require_once __DIR__ . '/group.php';

if ( class_exists( '\PersonalCRM\Storage' ) ) {
    return;
}

class Storage extends \WpApp\BaseStorage {

    /**
     * Get migrations table name
     */
    protected function get_migrations_table_name() {
        return $this->wpdb->prefix . 'personal_crm_migrations';
    }

    /**
     * Create database tables using WordPress dbDelta
     */
    protected function create_tables() {
        $charset_collate = $this->wpdb->get_charset_collate();

        // Groups table
        $sql = "CREATE TABLE {$this->wpdb->prefix}personal_crm_groups (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            slug varchar(100) NOT NULL,
            parent_id bigint(20) unsigned DEFAULT NULL,
            group_name varchar(255) NOT NULL,
            activity_url_prefix varchar(255) DEFAULT '',
            type varchar(50) DEFAULT 'team',
            display_icon varchar(10) DEFAULT '',
            sort_order int DEFAULT 0,
            is_default tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_slug (slug),
            KEY idx_parent_id (parent_id),
            KEY idx_team_type (type),
            KEY idx_team_default (is_default),
            KEY idx_sort_order (parent_id, sort_order)
        ) $charset_collate;";

        dbDelta( $sql );

        // People table
        $sql = "CREATE TABLE {$this->wpdb->prefix}personal_crm_people (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            username varchar(100) NOT NULL,
            name varchar(255) NOT NULL,
            nickname varchar(255) DEFAULT '',
            role varchar(255) DEFAULT '',
            email varchar(255) DEFAULT '',
            birthday varchar(20) DEFAULT '',
            company_anniversary varchar(20) DEFAULT '',
            partner varchar(255) DEFAULT '',
            partner_birthday varchar(20) DEFAULT '',
            location varchar(255) DEFAULT '',
            timezone varchar(100) DEFAULT '',
            github varchar(100) DEFAULT '',
            `linear` varchar(100) DEFAULT '',
            wordpress varchar(100) DEFAULT '',
            linkedin varchar(100) DEFAULT '',
            website varchar(255) DEFAULT '',
            new_company varchar(255) DEFAULT '',
            new_company_website varchar(255) DEFAULT '',
            deceased_date varchar(20) DEFAULT '',
            left_company tinyint(1) DEFAULT 0,
            deceased tinyint(1) DEFAULT 0,
            kids longtext,
            github_repos longtext,
            personal_events longtext,
            notes longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_username (username),
            KEY idx_people_username (username)
        ) $charset_collate;";

        dbDelta( $sql );

        // Events table
        $sql = "CREATE TABLE {$this->wpdb->prefix}personal_crm_events (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            group_id bigint(20) unsigned NOT NULL,
            type varchar(50) NOT NULL,
            name varchar(255) NOT NULL,
            description text DEFAULT '',
            start_date varchar(20) NOT NULL,
            end_date varchar(20) DEFAULT '',
            location varchar(255) DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_events_group_id (group_id),
            KEY idx_events_type (type),
            KEY idx_events_start_date (start_date)
        ) $charset_collate;";

        dbDelta( $sql );

        // Group links table
        $sql = "CREATE TABLE {$this->wpdb->prefix}personal_crm_group_links (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            group_id bigint(20) unsigned NOT NULL,
            link_name varchar(100) NOT NULL,
            link_url text NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_group_links_group_id (group_id),
            UNIQUE KEY unique_group_link (group_id, link_name)
        ) $charset_collate;";

        dbDelta( $sql );

        // People links table
        $sql = "CREATE TABLE {$this->wpdb->prefix}personal_crm_people_links (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            person_id bigint(20) unsigned NOT NULL,
            link_name varchar(100) NOT NULL,
            link_url text NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_people_links_person_id (person_id),
            UNIQUE KEY unique_person_link (person_id, link_name)
        ) $charset_collate;";

        dbDelta( $sql );

        // Event links table
        $sql = "CREATE TABLE {$this->wpdb->prefix}personal_crm_event_links (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_id bigint(20) unsigned NOT NULL,
            link_name varchar(100) NOT NULL,
            link_url text NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_event_links_event_id (event_id),
            UNIQUE KEY unique_event_link (event_id, link_name)
        ) $charset_collate;";

        dbDelta( $sql );

        // People-Groups junction table (M:N relationship)
        $sql = "CREATE TABLE {$this->wpdb->prefix}personal_crm_people_groups (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            person_id bigint(20) unsigned NOT NULL,
            group_id bigint(20) unsigned NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_person_group (person_id, group_id),
            KEY idx_person_groups_person (person_id),
            KEY idx_person_groups_group (group_id)
        ) $charset_collate;";

        dbDelta( $sql );

        // Notes table
        $sql = "CREATE TABLE {$this->wpdb->prefix}personal_crm_notes (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            person_id bigint(20) unsigned NOT NULL,
            note_text longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_notes_person_id (person_id),
            KEY idx_notes_created_at (created_at)
        ) $charset_collate;";

        dbDelta( $sql );

        // Person types table (DEPRECATED - will be removed in migration)
        $sql = "CREATE TABLE {$this->wpdb->prefix}personal_crm_person_types (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            group_slug varchar(100) NOT NULL,
            type_key varchar(50) NOT NULL,
            display_name varchar(100) NOT NULL,
            display_icon varchar(10) DEFAULT '',
            can_add tinyint(1) DEFAULT 1,
            sort_order int DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_person_types_group (group_slug),
            KEY idx_person_types_sort (group_slug, sort_order),
            UNIQUE KEY unique_group_type (group_slug, type_key)
        ) $charset_collate;";

        dbDelta( $sql );
    }

    /**
     * Run database schema migrations
     */
    protected function run_migrations() {
        // Run migrations
        $this->run_migration( 'add_linear_column_to_people', function() {
            // Check if linear column already exists
            $columns = $this->wpdb->get_results( "DESCRIBE {$this->wpdb->prefix}personal_crm_people" );
            $has_linear_column = false;

            foreach ( $columns as $column ) {
                if ( $column->Field === 'linear' ) {
                    $has_linear_column = true;
                    break;
                }
            }

            if ( ! $has_linear_column ) {
                $this->wpdb->query( "ALTER TABLE {$this->wpdb->prefix}personal_crm_people ADD COLUMN `linear` varchar(100) DEFAULT ''" );
            }
        } );

        $this->run_migration( 'populate_default_person_types', function() {
            $groups = $this->wpdb->get_col( "SELECT slug FROM {$this->wpdb->prefix}personal_crm_groups" );

            $default_types = array(
                array(
                    'type_key' => 'team_members',
                    'display_name' => 'Members',
                    'display_icon' => '👥',
                    'can_add' => 1,
                    'sort_order' => 1
                ),
                array(
                    'type_key' => 'leadership',
                    'display_name' => 'Leaders',
                    'display_icon' => '👑',
                    'can_add' => 1,
                    'sort_order' => 2
                ),
                array(
                    'type_key' => 'consultants',
                    'display_name' => 'Consultants',
                    'display_icon' => '🤝',
                    'can_add' => 1,
                    'sort_order' => 3
                )
            );

            foreach ( $groups as $group_slug ) {
                foreach ( $default_types as $type ) {
                    $existing = $this->wpdb->get_var( $this->wpdb->prepare(
                        "SELECT id FROM {$this->wpdb->prefix}personal_crm_person_types WHERE group_slug = %s AND type_key = %s",
                        $group_slug, $type['type_key']
                    ) );

                    if ( ! $existing ) {
                        $this->wpdb->insert(
                            $this->wpdb->prefix . 'personal_crm_person_types',
                            array(
                                'group_slug' => $group_slug,
                                'type_key' => $type['type_key'],
                                'display_name' => $type['display_name'],
                                'display_icon' => $type['display_icon'],
                                'can_add' => $type['can_add'],
                                'sort_order' => $type['sort_order']
                            )
                        );
                    }
                }
            }
        } );

        $this->run_migration( 'convert_person_types_to_subgroups', function() {
            // Step 1: Check if groups table needs migration
            $columns = $this->wpdb->get_results( "DESCRIBE {$this->wpdb->prefix}personal_crm_groups" );
            $has_id_column = false;
            $has_parent_id = false;

            foreach ( $columns as $column ) {
                if ( $column->Field === 'id' && $column->Key === 'PRI' ) {
                    $has_id_column = true;
                }
                if ( $column->Field === 'parent_id' ) {
                    $has_parent_id = true;
                }
            }

            if ( ! $has_id_column ) {
                // Step 1a: Alter groups table - add id as primary key
                // First check which columns already exist
                $has_display_icon = false;
                $has_sort_order = false;
                foreach ( $columns as $column ) {
                    if ( $column->Field === 'display_icon' ) $has_display_icon = true;
                    if ( $column->Field === 'sort_order' ) $has_sort_order = true;
                }

                // Build ALTER statement based on what's needed
                $alter_parts = array();
                $alter_parts[] = "DROP PRIMARY KEY";
                $alter_parts[] = "ADD COLUMN id bigint(20) unsigned NOT NULL AUTO_INCREMENT FIRST";
                if ( ! $has_parent_id ) {
                    $alter_parts[] = "ADD COLUMN parent_id bigint(20) unsigned DEFAULT NULL AFTER slug";
                }
                if ( ! $has_display_icon ) {
                    $alter_parts[] = "ADD COLUMN display_icon varchar(10) DEFAULT '' AFTER type";
                }
                if ( ! $has_sort_order ) {
                    $alter_parts[] = "ADD COLUMN sort_order int DEFAULT 0 AFTER display_icon";
                }
                $alter_parts[] = "ADD PRIMARY KEY (id)";
                $alter_parts[] = "ADD UNIQUE KEY unique_slug (slug)";
                $alter_parts[] = "ADD KEY idx_parent_id (parent_id)";
                $alter_parts[] = "ADD KEY idx_sort_order (parent_id, sort_order)";

                $this->wpdb->query( "ALTER TABLE {$this->wpdb->prefix}personal_crm_groups " . implode( ', ', $alter_parts ) );
            }

            // Step 2: Create subgroups from person_types (except team_members)
            $person_types = $this->wpdb->get_results(
                "SELECT * FROM {$this->wpdb->prefix}personal_crm_person_types
                 WHERE type_key != 'team_members'
                 ORDER BY group_slug, sort_order",
                ARRAY_A
            );

            $subgroup_map = array(); // Maps [parent_slug][type_key] => subgroup_id

            foreach ( $person_types as $type ) {
                $parent_slug = $type['group_slug'];

                // Get parent group id
                $parent_id = $this->wpdb->get_var( $this->wpdb->prepare(
                    "SELECT id FROM {$this->wpdb->prefix}personal_crm_groups WHERE slug = %s",
                    $parent_slug
                ) );

                if ( ! $parent_id ) continue;

                // Create subgroup slug and name
                $subgroup_slug = $parent_slug . '_' . $type['type_key'];
                $parent_name = $this->wpdb->get_var( $this->wpdb->prepare(
                    "SELECT group_name FROM {$this->wpdb->prefix}personal_crm_groups WHERE slug = %s",
                    $parent_slug
                ) );
                $subgroup_name = $parent_name . ' ' . $type['display_name'];

                // Check if subgroup already exists
                $existing_id = $this->wpdb->get_var( $this->wpdb->prepare(
                    "SELECT id FROM {$this->wpdb->prefix}personal_crm_groups WHERE slug = %s",
                    $subgroup_slug
                ) );

                if ( ! $existing_id ) {
                    // Insert subgroup
                    $this->wpdb->insert(
                        $this->wpdb->prefix . 'personal_crm_groups',
                        array(
                            'slug' => $subgroup_slug,
                            'parent_id' => $parent_id,
                            'group_name' => $subgroup_name,
                            'type' => 'subgroup',
                            'display_icon' => $type['display_icon'],
                            'sort_order' => $type['sort_order']
                        ),
                        array( '%s', '%d', '%s', '%s', '%s', '%d' )
                    );
                    $subgroup_id = $this->wpdb->insert_id;
                } else {
                    $subgroup_id = $existing_id;
                }

                $subgroup_map[ $parent_slug ][ $type['type_key'] ] = $subgroup_id;
            }

            // Step 3: Populate people_groups junction table
            // Check if people table still has team_slug and category columns
            $people_columns = $this->wpdb->get_results( "DESCRIBE {$this->wpdb->prefix}personal_crm_people" );
            $has_team_slug = false;
            $has_category = false;

            foreach ( $people_columns as $col ) {
                if ( $col->Field === 'team_slug' ) $has_team_slug = true;
                if ( $col->Field === 'category' ) $has_category = true;
            }

            if ( $has_team_slug && $has_category ) {
                $people = $this->wpdb->get_results(
                    "SELECT id, username, team_slug, category FROM {$this->wpdb->prefix}personal_crm_people",
                    ARRAY_A
                );

                foreach ( $people as $person ) {
                    $person_id = $person['id'];
                    $team_slug = $person['team_slug'];
                    $category = $person['category'];

                    // Get parent group id
                    $parent_id = $this->wpdb->get_var( $this->wpdb->prepare(
                        "SELECT id FROM {$this->wpdb->prefix}personal_crm_groups WHERE slug = %s",
                        $team_slug
                    ) );

                    if ( ! $parent_id ) continue;

                    if ( $category === 'team_members' ) {
                        // Add to parent group directly
                        $group_id = $parent_id;
                    } else {
                        // Add to subgroup
                        if ( isset( $subgroup_map[ $team_slug ][ $category ] ) ) {
                            $group_id = $subgroup_map[ $team_slug ][ $category ];
                        } else {
                            // Fallback to parent if subgroup doesn't exist
                            $group_id = $parent_id;
                        }
                    }

                    // Insert into people_groups if not exists
                    $existing = $this->wpdb->get_var( $this->wpdb->prepare(
                        "SELECT id FROM {$this->wpdb->prefix}personal_crm_people_groups
                         WHERE person_id = %d AND group_id = %d",
                        $person_id, $group_id
                    ) );

                    if ( ! $existing ) {
                        $this->wpdb->insert(
                            $this->wpdb->prefix . 'personal_crm_people_groups',
                            array(
                                'person_id' => $person_id,
                                'group_id' => $group_id
                            ),
                            array( '%d', '%d' )
                        );
                    }
                }

                // Step 4: Check for username conflicts and make unique
                $duplicates = $this->wpdb->get_results(
                    "SELECT username, COUNT(*) as cnt
                     FROM {$this->wpdb->prefix}personal_crm_people
                     GROUP BY username
                     HAVING cnt > 1",
                    ARRAY_A
                );

                foreach ( $duplicates as $dup ) {
                    $username = $dup['username'];
                    $people_with_username = $this->wpdb->get_results( $this->wpdb->prepare(
                        "SELECT id, team_slug FROM {$this->wpdb->prefix}personal_crm_people WHERE username = %s ORDER BY id",
                        $username
                    ), ARRAY_A );

                    // Keep first one, rename others
                    $counter = 2;
                    array_shift( $people_with_username ); // Skip first

                    foreach ( $people_with_username as $person ) {
                        $new_username = $username . '_' . $person['team_slug'];
                        // If still duplicate, add counter
                        while ( $this->wpdb->get_var( $this->wpdb->prepare(
                            "SELECT id FROM {$this->wpdb->prefix}personal_crm_people WHERE username = %s",
                            $new_username
                        ) ) ) {
                            $new_username = $username . '_' . $person['team_slug'] . '_' . $counter;
                            $counter++;
                        }

                        $this->wpdb->update(
                            $this->wpdb->prefix . 'personal_crm_people',
                            array( 'username' => $new_username ),
                            array( 'id' => $person['id'] ),
                            array( '%s' ),
                            array( '%d' )
                        );
                    }
                }

                // Step 5: Alter people table - remove team_slug and category
                $this->wpdb->query( "ALTER TABLE {$this->wpdb->prefix}personal_crm_people
                    DROP KEY unique_user_team,
                    DROP KEY idx_people_team_slug,
                    DROP KEY idx_people_category,
                    DROP COLUMN team_slug,
                    DROP COLUMN category,
                    ADD UNIQUE KEY unique_username (username)" );
            }

            // Step 6: Drop person_types table
            $this->wpdb->query( "DROP TABLE IF EXISTS {$this->wpdb->prefix}personal_crm_person_types" );
        } );

        $this->run_migration( 'migrate_notes_to_separate_table', function() {
            // Get all people with notes
            $people = $this->wpdb->get_results(
                "SELECT id, notes FROM {$this->wpdb->prefix}personal_crm_people WHERE notes IS NOT NULL AND notes != '' AND notes != '[]'",
                ARRAY_A
            );

            foreach ( $people as $person ) {
                $person_id = $person['id'];
                $notes_json = $person['notes'];
                $notes = json_decode( $notes_json, true );

                if ( is_array( $notes ) && ! empty( $notes ) ) {
                    foreach ( $notes as $note ) {
                        if ( isset( $note['text'] ) && ! empty( $note['text'] ) ) {
                            $created_at = isset( $note['date'] ) ? $note['date'] : current_time( 'mysql' );

                            $this->wpdb->insert(
                                $this->wpdb->prefix . 'personal_crm_notes',
                                array(
                                    'person_id' => $person_id,
                                    'note_text' => $note['text'],
                                    'created_at' => $created_at,
                                    'updated_at' => $created_at
                                ),
                                array( '%d', '%s', '%s', '%s' )
                            );
                        }
                    }
                }
            }
        } );

        $this->run_migration( 'convert_events_team_slug_to_group_id', function() {
            // Check if we need to migrate
            $columns = $this->wpdb->get_results( "DESCRIBE {$this->wpdb->prefix}personal_crm_events" );
            $has_team_slug = false;
            $has_group_id = false;

            foreach ( $columns as $column ) {
                if ( $column->Field === 'team_slug' ) {
                    $has_team_slug = true;
                }
                if ( $column->Field === 'group_id' ) {
                    $has_group_id = true;
                }
            }

            if ( $has_team_slug && ! $has_group_id ) {
                // Add group_id column
                $this->wpdb->query( "ALTER TABLE {$this->wpdb->prefix}personal_crm_events ADD COLUMN group_id bigint(20) unsigned DEFAULT NULL AFTER id" );

                // Populate group_id from team_slug
                $this->wpdb->query( "
                    UPDATE {$this->wpdb->prefix}personal_crm_events e
                    INNER JOIN {$this->wpdb->prefix}personal_crm_groups g ON e.team_slug = g.slug
                    SET e.group_id = g.id
                " );

                // Drop team_slug column and its index
                $this->wpdb->query( "ALTER TABLE {$this->wpdb->prefix}personal_crm_events DROP INDEX idx_events_team_slug" );
                $this->wpdb->query( "ALTER TABLE {$this->wpdb->prefix}personal_crm_events DROP COLUMN team_slug" );

                // Add index on group_id
                $this->wpdb->query( "ALTER TABLE {$this->wpdb->prefix}personal_crm_events ADD INDEX idx_events_group_id (group_id)" );
            }
        } );

        $this->run_migration( 'convert_group_links_group_slug_to_group_id', function() {
            // Check if we need to migrate
            $columns = $this->wpdb->get_results( "DESCRIBE {$this->wpdb->prefix}personal_crm_group_links" );
            $has_group_slug = false;
            $has_group_id = false;

            foreach ( $columns as $column ) {
                if ( $column->Field === 'group_slug' ) {
                    $has_group_slug = true;
                }
                if ( $column->Field === 'group_id' ) {
                    $has_group_id = true;
                }
            }

            if ( $has_group_slug && ! $has_group_id ) {
                // Add group_id column
                $this->wpdb->query( "ALTER TABLE {$this->wpdb->prefix}personal_crm_group_links ADD COLUMN group_id bigint(20) unsigned DEFAULT NULL AFTER id" );

                // Populate group_id from group_slug
                $this->wpdb->query( "
                    UPDATE {$this->wpdb->prefix}personal_crm_group_links l
                    INNER JOIN {$this->wpdb->prefix}personal_crm_groups g ON l.group_slug = g.slug
                    SET l.group_id = g.id
                " );

                // Drop unique constraint that includes group_slug
                $this->wpdb->query( "ALTER TABLE {$this->wpdb->prefix}personal_crm_group_links DROP INDEX unique_group_link" );
                // Drop index on group_slug
                $this->wpdb->query( "ALTER TABLE {$this->wpdb->prefix}personal_crm_group_links DROP INDEX idx_group_links_group_slug" );
                // Drop group_slug column
                $this->wpdb->query( "ALTER TABLE {$this->wpdb->prefix}personal_crm_group_links DROP COLUMN group_slug" );

                // Add new index and unique constraint
                $this->wpdb->query( "ALTER TABLE {$this->wpdb->prefix}personal_crm_group_links ADD INDEX idx_group_links_group_id (group_id)" );
                $this->wpdb->query( "ALTER TABLE {$this->wpdb->prefix}personal_crm_group_links ADD UNIQUE KEY unique_group_link (group_id, link_name)" );
            }
        } );
    }

    /**
     * ==================================================
     * GROUP HIERARCHY METHODS
     * ==================================================
     */

    /**
     * Get child groups of a parent group
     */
    public function get_child_groups( $group_id ) {
        return $this->wpdb->get_results( $this->wpdb->prepare(
            "SELECT * FROM {$this->wpdb->prefix}personal_crm_groups
             WHERE parent_id = %d
             ORDER BY sort_order, group_name",
            $group_id
        ), ARRAY_A );
    }

    /**
     * Get all descendant groups recursively
     */
    public function get_all_descendant_groups( $group_id ) {
        $descendants = array();
        $children = $this->get_child_groups( $group_id );

        foreach ( $children as $child ) {
            $descendants[] = $child;
            $descendants = array_merge( $descendants, $this->get_all_descendant_groups( $child['id'] ) );
        }

        return $descendants;
    }

    /**
     * Get parent group
     */
    public function get_parent_group( $group_id ) {
        return $this->wpdb->get_row( $this->wpdb->prepare(
            "SELECT * FROM {$this->wpdb->prefix}personal_crm_groups
             WHERE id = (SELECT parent_id FROM {$this->wpdb->prefix}personal_crm_groups WHERE id = %d)",
            $group_id
        ), ARRAY_A );
    }

    /**
     * ==================================================
     * PEOPLE-GROUPS M:N METHODS
     * ==================================================
     */

    /**
     * Get members of a group (optionally including child groups)
     */
    public function get_group_members( $group_id, $include_children = true ) {
        $group_ids = array( $group_id );

        if ( $include_children ) {
            $descendants = $this->get_all_descendant_groups( $group_id );
            foreach ( $descendants as $desc ) {
                $group_ids[] = $desc['id'];
            }
        }

        $placeholders = implode( ',', array_fill( 0, count( $group_ids ), '%d' ) );

        $results = $this->wpdb->get_results( $this->wpdb->prepare(
            "SELECT DISTINCT p.*
             FROM {$this->wpdb->prefix}personal_crm_people p
             INNER JOIN {$this->wpdb->prefix}personal_crm_people_groups pg ON p.id = pg.person_id
             WHERE pg.group_id IN ($placeholders)
             ORDER BY p.name",
            $group_ids
        ), ARRAY_A );

        $people = array();
        foreach ( $results as $person ) {
            $username = $person['username'];
            $people[ $username ] = $this->format_person_data( $person );
        }

        return $people;
    }

    /**
     * Add person to group
     */
    public function add_person_to_group( $person_id, $group_id ) {
        // Check if already exists
        $existing = $this->wpdb->get_var( $this->wpdb->prepare(
            "SELECT id FROM {$this->wpdb->prefix}personal_crm_people_groups
             WHERE person_id = %d AND group_id = %d",
            $person_id, $group_id
        ) );

        if ( $existing ) {
            return true; // Already exists
        }

        return $this->wpdb->insert(
            $this->wpdb->prefix . 'personal_crm_people_groups',
            array(
                'person_id' => $person_id,
                'group_id' => $group_id
            ),
            array( '%d', '%d' )
        );
    }

    /**
     * Remove person from group
     */
    public function remove_person_from_group( $person_id, $group_id ) {
        return $this->wpdb->delete(
            $this->wpdb->prefix . 'personal_crm_people_groups',
            array(
                'person_id' => $person_id,
                'group_id' => $group_id
            ),
            array( '%d', '%d' )
        );
    }

    /**
     * Get all groups a person belongs to
     */
    public function get_person_groups( $person_id ) {
        return $this->wpdb->get_results( $this->wpdb->prepare(
            "SELECT g.*
             FROM {$this->wpdb->prefix}personal_crm_groups g
             INNER JOIN {$this->wpdb->prefix}personal_crm_people_groups pg ON g.id = pg.group_id
             WHERE pg.person_id = %d
             ORDER BY g.group_name",
            $person_id
        ), ARRAY_A );
    }

    /**
     * Move person from one group to another
     */
    public function move_person( $person_id, $from_group_id, $to_group_id ) {
        $this->remove_person_from_group( $person_id, $from_group_id );
        return $this->add_person_to_group( $person_id, $to_group_id );
    }

    /**
     * Format person data for API output (remove internal fields)
     */
    private function format_person_data( $row ) {
        // Remove database specific fields
        unset( $row['id'], $row['created_at'], $row['updated_at'] );

        // Decode JSON fields
        $row['kids'] = ! empty( $row['kids'] ) ? json_decode( $row['kids'], true ) : array();
        $row['github_repos'] = ! empty( $row['github_repos'] ) ? json_decode( $row['github_repos'], true ) : array();
        $row['personal_events'] = ! empty( $row['personal_events'] ) ? json_decode( $row['personal_events'], true ) : array();
        $row['notes'] = ! empty( $row['notes'] ) ? json_decode( $row['notes'], true ) : array();

        // Get links
        $person_id = $this->wpdb->get_var( $this->wpdb->prepare(
            "SELECT id FROM {$this->wpdb->prefix}personal_crm_people WHERE username = %s",
            $row['username']
        ) );
        $row['links'] = $person_id ? $this->get_person_links( $person_id ) : array();

        return $row;
    }

    /**
     * Get team configuration data
     */
    public function get_group( $group_slug ) {
        $group_row = $this->wpdb->get_row( $this->wpdb->prepare(
            "SELECT * FROM {$this->wpdb->prefix}personal_crm_groups WHERE slug = %s",
            $group_slug
        ), ARRAY_A );

        if ( ! $group_row ) {
            return null;
        }

        // Load links eagerly (cheap to load)
        $group_row['links'] = $this->get_group_links( $group_slug );

        // Return Group instance - members and events will be lazy loaded
        return new Group( $group_row, $this );
    }

    /**
     * Get group by ID
     */
    public function get_group_by_id( $group_id ) {
        $group = $this->wpdb->get_row( $this->wpdb->prepare(
            "SELECT * FROM {$this->wpdb->prefix}personal_crm_groups WHERE id = %d",
            $group_id
        ), ARRAY_A );

        return $group;
    }

    /**
     * Get people by category
     */
    private function get_people_by_category( $group_slug, $category ) {
        $results = $this->wpdb->get_results( $this->wpdb->prepare(
            "SELECT * FROM {$this->wpdb->prefix}personal_crm_people WHERE team_slug = %s AND category = %s ORDER BY name",
            $group_slug, $category
        ), ARRAY_A );

        $people = array();
        foreach ( $results as $row ) {
            $username = $row['username'];
            $person_id = $row['id'];

            // Remove database specific fields
            unset( $row['id'], $row['username'], $row['team_slug'], $row['category'], $row['created_at'], $row['updated_at'] );

            // Get normalized links
            $row['links'] = $this->get_person_links( $person_id );

            // Convert JSON fields back to arrays
            $row['kids'] = json_decode( $row['kids'] ?: '[]', true );
            $row['github_repos'] = json_decode( $row['github_repos'] ?: '[]', true );
            $row['personal_events'] = json_decode( $row['personal_events'] ?: '[]', true );
            $row['notes'] = json_decode( $row['notes'] ?: '[]', true );

            // Convert boolean fields
            $row['left_company'] = (bool) $row['left_company'];
            $row['deceased'] = (bool) $row['deceased'];

            $people[$username] = $row;
        }

        return $people;
    }

    /**
     * Get group events by group ID
     *
     * @return Event[] Array of Event objects
     */
    public function get_group_events( $group_id ) {
        $results = $this->wpdb->get_results( $this->wpdb->prepare(
            "SELECT * FROM {$this->wpdb->prefix}personal_crm_events WHERE group_id = %d ORDER BY start_date",
            $group_id
        ), ARRAY_A );

        $events = array();
        foreach ( $results as $row ) {
            $row['links'] = $this->get_event_links( $row['id'] );
            $events[] = Event::from_team_event( $row );
        }

        return $events;
    }

    /**
     * Save a new event
     */
    public function save_event( $group_id, $event_data ) {
        $event_record = array(
            'group_id' => $group_id,
            'type' => $event_data['type'] ?? 'event',
            'name' => $event_data['name'] ?? '',
            'description' => $event_data['description'] ?? '',
            'start_date' => $event_data['start_date'] ?? '',
            'end_date' => $event_data['end_date'] ?? '',
            'location' => $event_data['location'] ?? '',
            'created_at' => current_time( 'mysql' ),
            'updated_at' => current_time( 'mysql' )
        );

        $result = $this->wpdb->insert(
            $this->wpdb->prefix . 'personal_crm_events',
            $event_record,
            array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
        );

        if ( $result ) {
            $event_id = $this->wpdb->insert_id;

            // Save event links if provided
            if ( ! empty( $event_data['links'] ) ) {
                $this->save_event_links( $event_id, $event_data['links'] );
            }

            return $event_id;
        }

        return false;
    }

    /**
     * Update an existing event
     */
    public function update_event( $event_id, $event_data ) {
        $event_record = array(
            'type' => $event_data['type'] ?? 'event',
            'name' => $event_data['name'] ?? '',
            'description' => $event_data['description'] ?? '',
            'start_date' => $event_data['start_date'] ?? '',
            'end_date' => $event_data['end_date'] ?? '',
            'location' => $event_data['location'] ?? '',
            'updated_at' => current_time( 'mysql' )
        );

        $result = $this->wpdb->update(
            $this->wpdb->prefix . 'personal_crm_events',
            $event_record,
            array( 'id' => $event_id ),
            array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
            array( '%d' )
        );

        // Update event links
        if ( isset( $event_data['links'] ) ) {
            // Delete existing links
            $this->wpdb->delete(
                $this->wpdb->prefix . 'personal_crm_event_links',
                array( 'event_id' => $event_id ),
                array( '%d' )
            );

            // Save new links
            if ( ! empty( $event_data['links'] ) ) {
                $this->save_event_links( $event_id, $event_data['links'] );
            }
        }

        return $result !== false;
    }

    /**
     * Delete an event
     */
    public function delete_event( $event_id ) {
        // Delete event links first
        $this->wpdb->delete(
            $this->wpdb->prefix . 'personal_crm_event_links',
            array( 'event_id' => $event_id ),
            array( '%d' )
        );

        // Delete the event
        $result = $this->wpdb->delete(
            $this->wpdb->prefix . 'personal_crm_events',
            array( 'id' => $event_id ),
            array( '%d' )
        );

        return $result !== false;
    }

    /**
     * Get a single event by ID
     */
    public function get_event( $event_id ) {
        $event = $this->wpdb->get_row( $this->wpdb->prepare(
            "SELECT * FROM {$this->wpdb->prefix}personal_crm_events WHERE id = %d",
            $event_id
        ), ARRAY_A );

        if ( $event ) {
            $event['links'] = $this->get_event_links( $event_id );
        }

        return $event;
    }

    /**
     * Save group configuration data
     */
    public function save_group( $group_id, $config ) {
        $group_name = $config['group_name'] ?? $config['team_name'] ?? '';

        // Generate slug from name
        $base_slug = sanitize_title( $group_name );
        $base_slug = str_replace( '-', '_', $base_slug );

        // Add parent prefix if this is a child group
        $parent_id = $config['parent_id'] ?? null;
        if ( ! empty( $parent_id ) ) {
            $parent = $this->get_group_by_id( $parent_id );
            if ( $parent ) {
                $new_slug = $parent['slug'] . '_' . $base_slug;
            } else {
                $new_slug = $base_slug;
            }
        } else {
            $new_slug = $base_slug;
        }

        // Get current slug for this group
        $current_slug = null;
        if ( $group_id ) {
            $current_slug = $this->wpdb->get_var( $this->wpdb->prepare(
                "SELECT slug FROM {$this->wpdb->prefix}personal_crm_groups WHERE id = %d",
                $group_id
            ) );
        }

        // Ensure slug uniqueness (but allow keeping the same slug for updates)
        if ( $new_slug !== $current_slug ) {
            $slug_counter = 1;
            $original_slug = $new_slug;
            $existing_slugs = $this->get_available_groups();
            while ( in_array( $new_slug, $existing_slugs, true ) ) {
                $new_slug = $original_slug . '_' . $slug_counter;
                $slug_counter++;
            }
        }

        $group_data = array(
            'slug' => $new_slug,
            'group_name' => $group_name,
            'activity_url_prefix' => $config['activity_url_prefix'] ?? '',
            'type' => $config['type'] ?? 'team',
            'parent_id' => $parent_id,
            'display_icon' => $config['display_icon'] ?? '',
            'sort_order' => $config['sort_order'] ?? 0,
            'is_default' => $config['default'] ?? 0,
            'updated_at' => current_time( 'mysql' )
        );

        // If slug changed, update child groups
        if ( $group_id && $current_slug && $new_slug !== $current_slug ) {
            $child_groups = $this->get_child_groups( $group_id );
            foreach ( $child_groups as $child ) {
                // Check if child slug starts with old parent slug
                if ( strpos( $child['slug'], $current_slug . '_' ) === 0 ) {
                    // Update child slug to use new parent slug
                    $child_suffix = substr( $child['slug'], strlen( $current_slug . '_' ) );
                    $new_child_slug = $new_slug . '_' . $child_suffix;

                    $this->wpdb->update(
                        $this->wpdb->prefix . 'personal_crm_groups',
                        array( 'slug' => $new_child_slug ),
                        array( 'id' => $child['id'] ),
                        array( '%s' ),
                        array( '%d' )
                    );
                }
            }
        }

        if ( $group_id ) {
            // Update existing group
            $this->wpdb->update(
                $this->wpdb->prefix . 'personal_crm_groups',
                $group_data,
                array( 'id' => $group_id ),
                array( '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%s' ),
                array( '%d' )
            );
        } else {
            // Insert new group
            $group_data['created_at'] = current_time( 'mysql' );
            $this->wpdb->insert(
                $this->wpdb->prefix . 'personal_crm_groups',
                $group_data,
                array( '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%s', '%s' )
            );
            $group_id = $this->wpdb->insert_id;
        }

        return $new_slug;
    }

    /**
     * Create a new group
     */
    public function create_group( $group_name, $group_slug, $group_type = 'team' ) {
        $existing_groups = $this->get_available_groups();
        if ( in_array( $group_slug, $existing_groups, true ) ) {
            return false;
        }

        $config = array(
            'group_name' => $group_name,
            'parent_id' => null,
            'activity_url_prefix' => '',
            'type' => $group_type,
            'display_icon' => '',
            'sort_order' => 0,
            'default' => empty( $existing_groups ) ? 1 : 0
        );

        return $this->save_group( null, $config );
    }

    /**
     * Save a single person (NEW: no group/category context)
     */
    public function save_person( $username, $person_data, $group_ids = array() ) {
        // Check if person already exists
        $existing_id = $this->wpdb->get_var( $this->wpdb->prepare(
            "SELECT id FROM {$this->wpdb->prefix}personal_crm_people WHERE username = %s",
            $username
        ) );

        $data = array(
            'username' => $username,
            'name' => $person_data['name'] ?? '',
            'nickname' => $person_data['nickname'] ?? '',
            'role' => $person_data['role'] ?? '',
            'email' => $person_data['email'] ?? '',
            'birthday' => $person_data['birthday'] ?? '',
            'company_anniversary' => $person_data['company_anniversary'] ?? '',
            'partner' => $person_data['partner'] ?? '',
            'partner_birthday' => $person_data['partner_birthday'] ?? '',
            'location' => $person_data['location'] ?? '',
            'timezone' => $person_data['timezone'] ?? '',
            'github' => $person_data['github'] ?? '',
            'linear' => $person_data['linear'] ?? '',
            'wordpress' => $person_data['wordpress'] ?? '',
            'linkedin' => $person_data['linkedin'] ?? '',
            'website' => $person_data['website'] ?? '',
            'new_company' => $person_data['new_company'] ?? '',
            'new_company_website' => $person_data['new_company_website'] ?? '',
            'deceased_date' => $person_data['deceased_date'] ?? '',
            'left_company' => $person_data['left_company'] ?? 0,
            'deceased' => $person_data['deceased'] ?? 0,
            'kids' => wp_json_encode( $person_data['kids'] ?? array() ),
            'github_repos' => wp_json_encode( $person_data['github_repos'] ?? array() ),
            'personal_events' => wp_json_encode( $person_data['personal_events'] ?? array() ),
            'updated_at' => current_time( 'mysql' )
        );

        $format = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s' );

        if ( $existing_id ) {
            $result = $this->wpdb->update(
                $this->wpdb->prefix . 'personal_crm_people',
                $data,
                array( 'id' => $existing_id ),
                $format,
                array( '%d' )
            );
            $person_id = $existing_id;
        } else {
            $data['created_at'] = current_time( 'mysql' );
            $result = $this->wpdb->insert(
                $this->wpdb->prefix . 'personal_crm_people',
                $data,
                array_merge( $format, array( '%s' ) )
            );
            $person_id = $this->wpdb->insert_id;
        }

        // Update group memberships if provided
        if ( ! empty( $group_ids ) && $person_id ) {
            // Remove existing memberships
            $this->wpdb->delete(
                $this->wpdb->prefix . 'personal_crm_people_groups',
                array( 'person_id' => $person_id ),
                array( '%d' )
            );

            // Add new memberships
            foreach ( $group_ids as $group_id ) {
                $this->add_person_to_group( $person_id, $group_id );
            }
        }

        // Handle links
        if ( isset( $person_data['links'] ) && $person_id ) {
            $this->save_person_links( $person_id, $person_data['links'] );
        }

        return $result;
    }

    /**
     * Delete a person entirely from the database
     */
    public function delete_person( $username ) {
        $person_id = $this->wpdb->get_var( $this->wpdb->prepare(
            "SELECT id FROM {$this->wpdb->prefix}personal_crm_people WHERE username = %s",
            $username
        ) );

        if ( ! $person_id ) {
            return false;
        }

        // Delete from people_groups junction table
        $this->wpdb->delete(
            $this->wpdb->prefix . 'personal_crm_people_groups',
            array( 'person_id' => $person_id ),
            array( '%d' )
        );

        // Delete from people table
        $result = $this->wpdb->delete(
            $this->wpdb->prefix . 'personal_crm_people',
            array( 'id' => $person_id ),
            array( '%d' )
        );

        return $result !== false;
    }

    /**
     * DEPRECATED: Legacy save_person for backwards compatibility
     * New code should use save_person($username, $person_data, $group_ids)
     */
    private function save_person_legacy( $group_slug, $username, $category, $person_data ) {
        // Get group_id from slug
        $group_id = $this->wpdb->get_var( $this->wpdb->prepare(
            "SELECT id FROM {$this->wpdb->prefix}personal_crm_groups WHERE slug = %s",
            $group_slug
        ) );

        if ( ! $group_id ) {
            return false;
        }

        // If category is not 'team_members', find subgroup
        if ( $category !== 'team_members' ) {
            $subgroup_slug = $group_slug . '_' . $category;
            $subgroup_id = $this->wpdb->get_var( $this->wpdb->prepare(
                "SELECT id FROM {$this->wpdb->prefix}personal_crm_groups WHERE slug = %s",
                $subgroup_slug
            ) );
            $group_id = $subgroup_id ?: $group_id;
        }

        return $this->save_person( $username, $person_data, array( $group_id ) );
    }

    /**
     * Save people for a category
     */
    private function save_people( $group_slug, $category, $people ) {
        foreach ( $people as $username => $person ) {
            $person_data = array(
                'username' => $username,
                'team_slug' => $group_slug,
                'category' => $category,
                'name' => $person['name'] ?? '',
                'nickname' => $person['nickname'] ?? '',
                'role' => $person['role'] ?? '',
                'email' => $person['email'] ?? '',
                'birthday' => $person['birthday'] ?? '',
                'company_anniversary' => $person['company_anniversary'] ?? '',
                'partner' => $person['partner'] ?? '',
                'partner_birthday' => $person['partner_birthday'] ?? '',
                'location' => $person['location'] ?? '',
                'timezone' => $person['timezone'] ?? '',
                'github' => $person['github'] ?? '',
                'linear' => $person['linear'] ?? '',
                'wordpress' => $person['wordpress'] ?? '',
                'linkedin' => $person['linkedin'] ?? '',
                'website' => $person['website'] ?? '',
                'new_company' => $person['new_company'] ?? '',
                'new_company_website' => $person['new_company_website'] ?? '',
                'deceased_date' => $person['deceased_date'] ?? '',
                'left_company' => $person['left_company'] ?? 0,
                'deceased' => $person['deceased'] ?? 0,
                'kids' => wp_json_encode( $person['kids'] ?? array() ),
                'github_repos' => wp_json_encode( $person['github_repos'] ?? array() ),
                'personal_events' => wp_json_encode( $person['personal_events'] ?? array() ),
                'created_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' )
            );

            $format = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s' );

            $person_id = $this->wpdb->insert(
                $this->wpdb->prefix . 'personal_crm_people',
                $person_data,
                $format
            );

            if ( $person_id ) {
                $person_id = $this->wpdb->insert_id;
                $this->save_person_links( $person_id, $person['links'] ?? array() );
            }
        }
    }

    /**
     * Save events for a group
     */
    private function save_events( $group_slug, $events ) {
        foreach ( $events as $event ) {
            $event_data = array(
                'team_slug' => $group_slug,
                'type' => $event['type'] ?? 'event',
                'name' => $event['name'] ?? '',
                'description' => $event['description'] ?? '',
                'start_date' => $event['start_date'] ?? '',
                'end_date' => $event['end_date'] ?? '',
                'location' => $event['location'] ?? '',
                'created_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' )
            );

            $event_id = $this->wpdb->insert(
                $this->wpdb->prefix . 'personal_crm_events',
                $event_data,
                array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
            );

            if ( $event_id ) {
                $event_id = $this->wpdb->insert_id;
                $this->save_event_links( $event_id, $event['links'] ?? array() );
            }
        }
    }

    /**
     * Get all available group slugs
     */
    public function get_available_groups( $top_level_only = false ) {
        if ( $top_level_only ) {
            return $this->wpdb->get_col( "SELECT slug FROM {$this->wpdb->prefix}personal_crm_groups WHERE parent_id IS NULL ORDER BY sort_order, slug" );
        }
        return $this->wpdb->get_col( "SELECT slug FROM {$this->wpdb->prefix}personal_crm_groups ORDER BY sort_order, slug" );
    }

    /**
     * Get all groups with hierarchical display names for autocomplete
     */
    public function get_all_groups_with_hierarchy() {
        $all_groups = $this->wpdb->get_results(
            "SELECT id, slug, group_name, display_icon, parent_id, sort_order
             FROM {$this->wpdb->prefix}personal_crm_groups
             ORDER BY sort_order, group_name",
            ARRAY_A
        );

        $groups_by_id = array();
        foreach ( $all_groups as $group ) {
            $groups_by_id[ $group['id'] ] = $group;
        }

        // Organize groups by parent
        $top_level = array();
        $children_by_parent = array();

        foreach ( $all_groups as $group ) {
            if ( $group['parent_id'] ) {
                if ( ! isset( $children_by_parent[ $group['parent_id'] ] ) ) {
                    $children_by_parent[ $group['parent_id'] ] = array();
                }
                $children_by_parent[ $group['parent_id'] ][] = $group;
            } else {
                $top_level[] = $group;
            }
        }

        // Build result with parents followed by their children
        $result = array();
        foreach ( $top_level as $parent_group ) {
            // Add parent
            $result[] = array(
                'id' => $parent_group['id'],
                'slug' => $parent_group['slug'],
                'hierarchical_name' => $parent_group['group_name'],
                'display_icon' => $parent_group['display_icon'] ?: '',
            );

            // Add children
            if ( isset( $children_by_parent[ $parent_group['id'] ] ) ) {
                foreach ( $children_by_parent[ $parent_group['id'] ] as $child_group ) {
                    $result[] = array(
                        'id' => $child_group['id'],
                        'slug' => $child_group['slug'],
                        'hierarchical_name' => $parent_group['group_name'] . ' → ' . $child_group['group_name'],
                        'display_icon' => $child_group['display_icon'] ?: '',
                    );
                }
            }
        }

        return $result;
    }

    /**
     * Get group name by slug
     */
    public function get_group_name( $group_slug ) {
        return $this->wpdb->get_var( $this->wpdb->prepare(
            "SELECT group_name FROM {$this->wpdb->prefix}personal_crm_groups WHERE slug = %s",
            $group_slug
        ) );
    }

    /**
     * Get group name by ID
     */
    public function get_group_name_by_id( $group_id ) {
        return $this->wpdb->get_var( $this->wpdb->prepare(
            "SELECT group_name FROM {$this->wpdb->prefix}personal_crm_groups WHERE id = %d",
            $group_id
        ) );
    }

    /**
     * Get group type by slug
     */
    public function get_group_type( $group_slug ) {
        $type = $this->wpdb->get_var( $this->wpdb->prepare(
            "SELECT type FROM {$this->wpdb->prefix}personal_crm_groups WHERE slug = %s",
            $group_slug
        ) );
        return $type ?: 'team';
    }

    /**
     * Get default group slug
     */
    public function get_default_group() {
        $slug = $this->wpdb->get_var( "SELECT slug FROM {$this->wpdb->prefix}personal_crm_groups WHERE is_default = 1 LIMIT 1" );

        if ( ! $slug ) {
            $slug = $this->wpdb->get_var( "SELECT slug FROM {$this->wpdb->prefix}personal_crm_groups ORDER BY slug LIMIT 1" );
        }

        return $slug ?: '';
    }

    /**
     * Check if group exists
     */
    public function group_exists( $group_slug ) {
        $count = $this->wpdb->get_var( $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->wpdb->prefix}personal_crm_groups WHERE slug = %s",
            $group_slug
        ) );
        return (bool) $count;
    }

    /**
     * Delete a group and all its data
     */
    public function delete_group( $group_slug ) {
        return $this->wpdb->delete(
            $this->wpdb->prefix . 'personal_crm_groups',
            array( 'slug' => $group_slug ),
            array( '%s' )
        );
    }

    /**
     * Get HR feedback for a person
     * @deprecated Moved to A8cTeamStorage in a8c-team plugin
     */
    public function get_hr_feedback( $username, $month = null ) {
        trigger_error( 'get_hr_feedback() is deprecated. Use A8cTeamStorage from a8c-team plugin.', E_USER_DEPRECATED );
        return array();
    }

    /**
     * Save HR feedback for a person
     * @deprecated Moved to A8cTeamStorage in a8c-team plugin
     */
    public function save_hr_feedback( $username, $month, $data ) {
        trigger_error( 'save_hr_feedback() is deprecated. Use A8cTeamStorage from a8c-team plugin.', E_USER_DEPRECATED );
        return false;
    }

    /**
     * Get people count from group config
     */
    public function get_group_people_count( $group_slug ) {
        return (int) $this->wpdb->get_var( $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->wpdb->prefix}personal_crm_people WHERE team_slug = %s",
            $group_slug
        ) );
    }

    /**
     * Get all people names from group config for search purposes
     */
    public function get_group_people_names( $group_slug ) {
        $group_id = $this->wpdb->get_var( $this->wpdb->prepare(
            "SELECT id FROM {$this->wpdb->prefix}personal_crm_groups WHERE slug = %s",
            $group_slug
        ) );

        if ( ! $group_id ) {
            return array();
        }

        $results = $this->wpdb->get_results( $this->wpdb->prepare(
            "SELECT p.username, p.name
             FROM {$this->wpdb->prefix}personal_crm_people p
             INNER JOIN {$this->wpdb->prefix}personal_crm_people_groups pg ON p.id = pg.person_id
             WHERE pg.group_id = %d
             ORDER BY p.name",
            $group_id
        ), ARRAY_A );

        // Return as associative array username => data
        $people = array();
        foreach ( $results as $row ) {
            $people[$row['username']] = array(
                'name' => $row['name']
            );
        }

        return $people;
    }

    /**
     * Get a single person from a group
     */
    /**
     * Get person by username (NEW: no group context needed)
     */
    public function get_person( $username ) {
        $row = $this->wpdb->get_row( $this->wpdb->prepare(
            "SELECT * FROM {$this->wpdb->prefix}personal_crm_people WHERE username = %s",
            $username
        ), ARRAY_A );

        if ( ! $row ) {
            return null;
        }

        $person_id = $row['id'];

        $row['links'] = $this->get_person_links( $person_id );
        $row['notes'] = $this->get_person_notes( $person_id );
        $row['kids'] = json_decode( $row['kids'] ?: '[]', true );
        $row['github_repos'] = json_decode( $row['github_repos'] ?: '[]', true );
        $row['personal_events'] = json_decode( $row['personal_events'] ?: '[]', true );

        $row['left_company'] = (bool) $row['left_company'];
        $row['deceased'] = (bool) $row['deceased'];

        // Add groups this person belongs to
        $row['groups'] = $this->get_person_groups( $person_id );

        return $row;
    }

    /**
     * Save or update a group link
     */
    public function save_group_link( $group_id, $link_name, $link_url ) {
        // Check if link already exists
        $existing = $this->wpdb->get_var( $this->wpdb->prepare(
            "SELECT id FROM {$this->wpdb->prefix}personal_crm_group_links WHERE group_id = %d AND link_name = %s",
            $group_id,
            $link_name
        ) );

        if ( $existing ) {
            // Update existing link
            return $this->wpdb->update(
                $this->wpdb->prefix . 'personal_crm_group_links',
                array( 'link_url' => $link_url ),
                array( 'id' => $existing ),
                array( '%s' ),
                array( '%d' )
            ) !== false;
        } else {
            // Insert new link
            return $this->wpdb->insert(
                $this->wpdb->prefix . 'personal_crm_group_links',
                array(
                    'group_id' => $group_id,
                    'link_name' => $link_name,
                    'link_url' => $link_url,
                    'created_at' => current_time( 'mysql' )
                ),
                array( '%d', '%s', '%s', '%s' )
            ) !== false;
        }
    }

    /**
     * Delete a group link
     */
    public function delete_group_link( $group_id, $link_name ) {
        return $this->wpdb->delete(
            $this->wpdb->prefix . 'personal_crm_group_links',
            array(
                'group_id' => $group_id,
                'link_name' => $link_name
            ),
            array( '%d', '%s' )
        ) !== false;
    }

    /**
     * Get all links for a group
     */
    public function get_group_links_by_id( $group_id ) {
        $results = $this->wpdb->get_results( $this->wpdb->prepare(
            "SELECT link_name, link_url FROM {$this->wpdb->prefix}personal_crm_group_links WHERE group_id = %d ORDER BY link_name",
            $group_id
        ), ARRAY_A );

        $links = array();
        foreach ( $results as $row ) {
            $links[$row['link_name']] = $row['link_url'];
        }

        return $links;
    }

    /**
     * Get group links (DEPRECATED: use get_group_links_by_id instead)
     */
    private function get_group_links( $group_slug ) {
        // Get group_id from slug
        $group_id = $this->wpdb->get_var( $this->wpdb->prepare(
            "SELECT id FROM {$this->wpdb->prefix}personal_crm_groups WHERE slug = %s",
            $group_slug
        ) );

        if ( ! $group_id ) {
            return array();
        }

        return $this->get_group_links_by_id( $group_id );
    }

    /**
     * Save group links (DEPRECATED: batch operation, use save_group_link instead)
     */
    private function save_group_links( $group_slug, $links ) {
        // Get group_id from slug
        $group_id = $this->wpdb->get_var( $this->wpdb->prepare(
            "SELECT id FROM {$this->wpdb->prefix}personal_crm_groups WHERE slug = %s",
            $group_slug
        ) );

        if ( ! $group_id ) {
            return;
        }

        // Delete all existing links for this group
        $this->wpdb->delete(
            $this->wpdb->prefix . 'personal_crm_group_links',
            array( 'group_id' => $group_id ),
            array( '%d' )
        );

        // Insert new links
        if ( is_array( $links ) && ! empty( $links ) ) {
            foreach ( $links as $link_name => $link_url ) {
                $this->wpdb->insert(
                    $this->wpdb->prefix . 'personal_crm_group_links',
                    array(
                        'group_id' => $group_id,
                        'link_name' => $link_name,
                        'link_url' => $link_url,
                        'created_at' => current_time( 'mysql' )
                    ),
                    array( '%d', '%s', '%s', '%s' )
                );
            }
        }
    }

    /**
     * Get person links
     */
    private function get_person_links( $person_id ) {
        $results = $this->wpdb->get_results( $this->wpdb->prepare(
            "SELECT link_name, link_url FROM {$this->wpdb->prefix}personal_crm_people_links WHERE person_id = %d ORDER BY link_name",
            $person_id
        ), ARRAY_A );

        $links = array();
        foreach ( $results as $row ) {
            $links[$row['link_name']] = $row['link_url'];
        }

        return $links;
    }

    /**
     * Get person notes
     */
    private function get_person_notes( $person_id ) {
        $results = $this->wpdb->get_results( $this->wpdb->prepare(
            "SELECT id, note_text, created_at FROM {$this->wpdb->prefix}personal_crm_notes WHERE person_id = %d ORDER BY created_at DESC",
            $person_id
        ), ARRAY_A );

        $notes = array();
        foreach ( $results as $row ) {
            $notes[] = array(
                'id' => $row['id'],
                'text' => $row['note_text'],
                'date' => $row['created_at']
            );
        }

        return $notes;
    }

    /**
     * Get person ID from username
     */
    public function get_person_id( $username ) {
        return $this->wpdb->get_var( $this->wpdb->prepare(
            "SELECT id FROM {$this->wpdb->prefix}personal_crm_people WHERE username = %s",
            $username
        ) );
    }

    /**
     * Add a note to a person
     */
    public function add_person_note( $person_id, $note_text ) {
        return $this->wpdb->insert(
            $this->wpdb->prefix . 'personal_crm_notes',
            array(
                'person_id' => $person_id,
                'note_text' => $note_text,
                'created_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' )
            ),
            array( '%d', '%s', '%s', '%s' )
        );
    }

    /**
     * Update a note
     */
    public function update_person_note( $note_id, $note_text ) {
        return $this->wpdb->update(
            $this->wpdb->prefix . 'personal_crm_notes',
            array(
                'note_text' => $note_text,
                'updated_at' => current_time( 'mysql' )
            ),
            array( 'id' => $note_id ),
            array( '%s', '%s' ),
            array( '%d' )
        );
    }

    /**
     * Delete a note
     */
    public function delete_person_note( $note_id ) {
        return $this->wpdb->delete(
            $this->wpdb->prefix . 'personal_crm_notes',
            array( 'id' => $note_id ),
            array( '%d' )
        );
    }

    /**
     * Save person links
     */
    private function save_person_links( $person_id, $links ) {
        // Delete existing links
        $this->wpdb->delete(
            $this->wpdb->prefix . 'personal_crm_people_links',
            array( 'person_id' => $person_id ),
            array( '%d' )
        );

        // Insert new links
        if ( is_array( $links ) && ! empty( $links ) ) {
            foreach ( $links as $link_name => $link_url ) {
                $this->wpdb->insert(
                    $this->wpdb->prefix . 'personal_crm_people_links',
                    array(
                        'person_id' => $person_id,
                        'link_name' => $link_name,
                        'link_url' => $link_url,
                        'created_at' => current_time( 'mysql' )
                    ),
                    array( '%d', '%s', '%s', '%s' )
                );
            }
        }
    }

    /**
     * Get event links
     */
    private function get_event_links( $event_id ) {
        $results = $this->wpdb->get_results( $this->wpdb->prepare(
            "SELECT link_name, link_url FROM {$this->wpdb->prefix}personal_crm_event_links WHERE event_id = %d ORDER BY link_name",
            $event_id
        ), ARRAY_A );

        $links = array();
        foreach ( $results as $row ) {
            $links[$row['link_name']] = $row['link_url'];
        }

        return $links;
    }

    /**
     * Save event links
     */
    private function save_event_links( $event_id, $links ) {
        // Delete existing links
        $this->wpdb->delete(
            $this->wpdb->prefix . 'personal_crm_event_links',
            array( 'event_id' => $event_id ),
            array( '%d' )
        );

        // Insert new links
        if ( is_array( $links ) && ! empty( $links ) ) {
            foreach ( $links as $link_name => $link_url ) {
                $this->wpdb->insert(
                    $this->wpdb->prefix . 'personal_crm_event_links',
                    array(
                        'event_id' => $event_id,
                        'link_name' => $link_name,
                        'link_url' => $link_url,
                        'created_at' => current_time( 'mysql' )
                    ),
                    array( '%d', '%s', '%s', '%s' )
                );
            }
        }
    }

    /**
     * Migrate data from JSON files to WordPress database
     */
    public function migrate_from_json( $json_dir = null ) {
        if ( $json_dir === null ) {
            $json_dir = __DIR__ . '/../';
        }

        $json_files = glob( $json_dir . '*.json' );
        $migrated_groups = 0;

        foreach ( $json_files as $file ) {
            $basename = basename( $file, '.json' );

            if ( $basename === 'hr-feedback' || $basename === 'composer' || strpos( $basename, '.bak' ) !== false || strpos( $basename, 'bak-' ) !== false ) {
                continue;
            }

            $content = file_get_contents( $file );
            $config = json_decode( $content, true );

            if ( json_last_error() === JSON_ERROR_NONE && $config ) {
                $this->save_group( $basename, $config );
                $migrated_groups++;
            }
        }

        return $migrated_groups;
    }

    /**
     * Get wpdb instance for custom queries
     */
    public function get_wpdb() {
        return $this->wpdb;
    }
}