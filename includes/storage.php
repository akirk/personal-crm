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
            slug varchar(100) NOT NULL,
            group_name varchar(255) NOT NULL,
            activity_url_prefix varchar(255) DEFAULT '',
            not_managing_team tinyint(1) DEFAULT 1,
            type varchar(50) DEFAULT 'team',
            is_default tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (slug),
            KEY idx_team_type (type),
            KEY idx_team_default (is_default)
        ) $charset_collate;";

        dbDelta( $sql );

        // People table
        $sql = "CREATE TABLE {$this->wpdb->prefix}personal_crm_people (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            username varchar(100) NOT NULL,
            team_slug varchar(100) NOT NULL,
            category varchar(50) NOT NULL,
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
            KEY idx_people_team_slug (team_slug),
            KEY idx_people_username (username),
            KEY idx_people_category (category),
            UNIQUE KEY unique_user_team (username, team_slug)
        ) $charset_collate;";

        dbDelta( $sql );

        // Events table
        $sql = "CREATE TABLE {$this->wpdb->prefix}personal_crm_events (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            team_slug varchar(100) NOT NULL,
            type varchar(50) NOT NULL,
            name varchar(255) NOT NULL,
            description text DEFAULT '',
            start_date varchar(20) NOT NULL,
            end_date varchar(20) DEFAULT '',
            location varchar(255) DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_events_team_slug (team_slug),
            KEY idx_events_type (type),
            KEY idx_events_start_date (start_date)
        ) $charset_collate;";

        dbDelta( $sql );

        // Group links table
        $sql = "CREATE TABLE {$this->wpdb->prefix}personal_crm_group_links (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            group_slug varchar(100) NOT NULL,
            link_name varchar(100) NOT NULL,
            link_url text NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_group_links_group_slug (group_slug),
            UNIQUE KEY unique_group_link (group_slug, link_name)
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
    }

    /**
     * Get team configuration data
     */
    public function get_group( $group_slug ) {
        $group = $this->wpdb->get_row( $this->wpdb->prepare(
            "SELECT * FROM {$this->wpdb->prefix}personal_crm_groups WHERE slug = %s",
            $group_slug
        ), ARRAY_A );

        if ( ! $group ) {
            return null;
        }

        // Get group members by category
        $team_members = $this->get_people_by_category( $group_slug, 'team_members' );
        $leadership = $this->get_people_by_category( $group_slug, 'leadership' );
        $consultants = $this->get_people_by_category( $group_slug, 'consultants' );
        $alumni = $this->get_people_by_category( $group_slug, 'alumni' );

        // Get events
        $events = $this->get_group_events( $group_slug );

        return array(
            'activity_url_prefix' => $group['activity_url_prefix'],
            'group_name' => $group['group_name'],
            'not_managing' => (bool) $group['not_managing_team'],
            'links' => $this->get_group_links( $group_slug ),
            'type' => $group['type'] ?: 'team',  // Default to 'team' if null
            'default' => (bool) $group['is_default'],
            'team_members' => $team_members,
            'leadership' => $leadership,
            'consultants' => $consultants,
            'alumni' => $alumni,
            'events' => $events
        );
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
     * Get group events
     */
    private function get_group_events( $group_slug ) {
        $results = $this->wpdb->get_results( $this->wpdb->prepare(
            "SELECT * FROM {$this->wpdb->prefix}personal_crm_events WHERE team_slug = %s ORDER BY start_date",
            $group_slug
        ), ARRAY_A );

        $events = array();
        foreach ( $results as $row ) {
            $event_id = $row['id'];
            unset( $row['id'], $row['team_slug'], $row['created_at'], $row['updated_at'] );
            $row['links'] = $this->get_event_links( $event_id );
            $events[] = $row;
        }

        return $events;
    }

    /**
     * Save group configuration data
     */
    public function save_group( $group_slug, $config ) {
        // Sort events by date before saving
        if ( isset( $config['events'] ) && is_array( $config['events'] ) ) {
            usort( $config['events'], function( $a, $b ) {
                $dateA = $a['start_date'] ?? '';
                $dateB = $b['start_date'] ?? '';
                return strcmp( $dateA, $dateB );
            } );
        }

        $this->wpdb->query( 'START TRANSACTION' );

        try {
            $group_data = array(
                'slug' => $group_slug,
                'group_name' => $config['team_name'] ?? '',
                'activity_url_prefix' => $config['activity_url_prefix'] ?? '',
                'not_managing_team' => $config['not_managing_team'] ?? 1,
                'type' => $config['type'] ?? 'team',
                'is_default' => $config['default'] ?? 0,
                'updated_at' => current_time( 'mysql' )
            );

                $existing_group = $this->wpdb->get_var( $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->wpdb->prefix}personal_crm_groups WHERE slug = %s",
                $group_slug
            ) );

            if ( $existing_group ) {
                $this->wpdb->update(
                    $this->wpdb->prefix . 'personal_crm_groups',
                    $group_data,
                    array( 'slug' => $group_slug ),
                    array( '%s', '%s', '%s', '%d', '%s', '%d', '%s' ),
                    array( '%s' )
                );
            } else {
                $group_data['created_at'] = current_time( 'mysql' );
                $this->wpdb->insert(
                    $this->wpdb->prefix . 'personal_crm_groups',
                    $group_data,
                    array( '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s' )
                );
            }

            $this->save_group_links( $group_slug, $config['team_links'] ?? array() );

                $this->wpdb->delete(
                $this->wpdb->prefix . 'personal_crm_people',
                array( 'team_slug' => $group_slug ),
                array( '%s' )
            );

            $categories = array( 'team_members', 'leadership', 'consultants', 'alumni' );
            foreach ( $categories as $category ) {
                if ( isset( $config[$category] ) && is_array( $config[$category] ) ) {
                    $this->save_people( $group_slug, $category, $config[$category] );
                }
            }

                $this->wpdb->delete(
                $this->wpdb->prefix . 'personal_crm_events',
                array( 'team_slug' => $group_slug ),
                array( '%s' )
            );

            if ( isset( $config['events'] ) && is_array( $config['events'] ) ) {
                $this->save_events( $group_slug, $config['events'] );
            }

            $this->wpdb->query( 'COMMIT' );
            return true;

        } catch ( Exception $e ) {
            $this->wpdb->query( 'ROLLBACK' );
            throw $e;
        }
    }

    /**
     * Save a single person
     */
    public function save_person( $group_slug, $username, $category, $person_data ) {
        // Check if person already exists
        $existing = $this->wpdb->get_var( $this->wpdb->prepare(
            "SELECT id FROM {$this->wpdb->prefix}personal_crm_people WHERE username = %s AND team_slug = %s",
            $username, $group_slug
        ) );

        $data = array(
            'username' => $username,
            'team_slug' => $group_slug,
            'category' => $category,
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
            'notes' => wp_json_encode( $person_data['notes'] ?? array() ),
            'updated_at' => current_time( 'mysql' )
        );

        $format = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s' );

        if ( $existing ) {
            return $this->wpdb->update(
                $this->wpdb->prefix . 'personal_crm_people',
                $data,
                array( 'username' => $username, 'team_slug' => $group_slug ),
                $format,
                array( '%s', '%s' )
            );
        } else {
            $data['created_at'] = current_time( 'mysql' );
            return $this->wpdb->insert(
                $this->wpdb->prefix . 'personal_crm_people',
                $data,
                array_merge( $format, array( '%s' ) )
            );
        }
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
                'notes' => wp_json_encode( $person['notes'] ?? array() ),
                'created_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' )
            );

            $format = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' );

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
    public function get_available_groups() {
        return $this->wpdb->get_col( "SELECT slug FROM {$this->wpdb->prefix}personal_crm_groups ORDER BY slug" );
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
        return $this->wpdb->get_col( $this->wpdb->prepare(
            "SELECT name FROM {$this->wpdb->prefix}personal_crm_people WHERE team_slug = %s ORDER BY name",
            $group_slug
        ) );
    }

    /**
     * Get a single person from a group
     */
    public function get_person( $group_slug, $username ) {
        $row = $this->wpdb->get_row( $this->wpdb->prepare(
            "SELECT * FROM {$this->wpdb->prefix}personal_crm_people WHERE team_slug = %s AND username = %s",
            $group_slug, $username
        ), ARRAY_A );

        if ( ! $row ) {
            return null;
        }

        $person_id = $row['id'];
        unset( $row['id'], $row['username'], $row['team_slug'], $row['category'], $row['created_at'], $row['updated_at'] );

        $row['links'] = $this->get_person_links( $person_id );
        $row['kids'] = json_decode( $row['kids'] ?: '[]', true );
        $row['github_repos'] = json_decode( $row['github_repos'] ?: '[]', true );
        $row['personal_events'] = json_decode( $row['personal_events'] ?: '[]', true );
        $row['notes'] = json_decode( $row['notes'] ?: '[]', true );

        $row['left_company'] = (bool) $row['left_company'];
        $row['deceased'] = (bool) $row['deceased'];

        return $row;
    }

    /**
     * Get group links
     */
    private function get_group_links( $group_slug ) {
        $results = $this->wpdb->get_results( $this->wpdb->prepare(
            "SELECT link_name, link_url FROM {$this->wpdb->prefix}personal_crm_group_links WHERE group_slug = %s ORDER BY link_name",
            $group_slug
        ), ARRAY_A );

        $links = array();
        foreach ( $results as $row ) {
            $links[$row['link_name']] = $row['link_url'];
        }

        return $links;
    }

    /**
     * Save group links
     */
    private function save_group_links( $group_slug, $links ) {
        $this->wpdb->delete(
            $this->wpdb->prefix . 'personal_crm_group_links',
            array( 'group_slug' => $group_slug ),
            array( '%s' )
        );

        if ( is_array( $links ) && ! empty( $links ) ) {
            foreach ( $links as $link_name => $link_url ) {
                $this->wpdb->insert(
                    $this->wpdb->prefix . 'personal_crm_group_links',
                    array(
                        'group_slug' => $group_slug,
                        'link_name' => $link_name,
                        'link_url' => $link_url,
                        'created_at' => current_time( 'mysql' )
                    ),
                    array( '%s', '%s', '%s', '%s' )
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