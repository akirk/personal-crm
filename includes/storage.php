<?php
/**
 * WordPress Database Storage Class
 *
 * WordPress database storage using wpdb for compatibility with WordPress infrastructure.
 * Works with WordPress wpdb, MySQL wpdb polyfill, or SQLite wpdb subclass.
 */

namespace PersonalCRM;

require_once __DIR__ . '/wpdb-polyfill.php';
require_once __DIR__ . '/sqlite-wpdb.php';

if ( class_exists( '\PersonalCRM\Storage' ) ) {
    return;
}

class Storage {
    private $wpdb;

    public function __construct( $wpdb_instance ) {
        global $wpdb;
        $this->wpdb = $wpdb_instance;

        // Set global wpdb for dbDelta compatibility
        if ( ! $wpdb ) {
            $wpdb = $wpdb_instance;
        }

        $this->init_database();
    }

    /**
     * Initialize WordPress database tables using dbDelta
     */
    private function init_database() {
        if ( ! function_exists( 'dbDelta' ) ) {
            if ( ! defined( 'ABSPATH' ) ) {
                throw new \Exception( 'ABSPATH not defined. WpdbStorage requires WordPress environment.' );
            }
            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        }

        $this->create_tables();
        $this->run_migrations();
    }

    /**
     * Create database tables using WordPress dbDelta
     */
    private function create_tables() {
        $charset_collate = $this->wpdb->get_charset_collate();

        // Teams table
        $sql = "CREATE TABLE {$this->wpdb->prefix}personal_crm_teams (
            slug varchar(100) NOT NULL,
            team_name varchar(255) NOT NULL,
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

        // HR Feedback table
        $sql = "CREATE TABLE {$this->wpdb->prefix}personal_crm_hr_feedback (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            username varchar(100) NOT NULL,
            month varchar(10) NOT NULL,
            feedback_to_person text DEFAULT '',
            feedback_to_hr text DEFAULT '',
            submitted_to_hr tinyint(1) DEFAULT 0,
            draft_complete tinyint(1) DEFAULT 0,
            google_doc_updated tinyint(1) DEFAULT 0,
            not_necessary_reason text DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_hr_feedback_username (username),
            KEY idx_hr_feedback_month (month),
            UNIQUE KEY unique_user_month (username, month)
        ) $charset_collate;";

        dbDelta( $sql );

        // Team links table
        $sql = "CREATE TABLE {$this->wpdb->prefix}personal_crm_team_links (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            team_slug varchar(100) NOT NULL,
            link_name varchar(100) NOT NULL,
            link_url text NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_team_links_team_slug (team_slug),
            UNIQUE KEY unique_team_link (team_slug, link_name)
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
    private function run_migrations() {
        // Create migrations table if it doesn't exist
        $charset_collate = $this->wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$this->wpdb->prefix}personal_crm_migrations (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            migration_name varchar(255) NOT NULL,
            applied_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_migration (migration_name)
        ) $charset_collate;";

        dbDelta( $sql );

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
     * Run a specific migration
     */
    private function run_migration( $migration_name, $migration_callback ) {
        $migration_exists = $this->wpdb->get_var( $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->wpdb->prefix}personal_crm_migrations WHERE migration_name = %s",
            $migration_name
        ) );

        if ( ! $migration_exists ) {
            call_user_func( $migration_callback );

            $this->wpdb->insert(
                $this->table_prefix . 'migrations',
                array( 'migration_name' => $migration_name ),
                array( '%s' )
            );
        }
    }

    /**
     * Get team configuration data
     */
    public function get_team_config( $team_slug ) {
        $team = $this->wpdb->get_row( $this->wpdb->prepare(
            "SELECT * FROM {$this->wpdb->prefix}personal_crm_teams WHERE slug = %s",
            $team_slug
        ), ARRAY_A );

        if ( ! $team ) {
            return null;
        }

        // Get team members by category
        $team_members = $this->get_people_by_category( $team_slug, 'team_members' );
        $leadership = $this->get_people_by_category( $team_slug, 'leadership' );
        $consultants = $this->get_people_by_category( $team_slug, 'consultants' );
        $alumni = $this->get_people_by_category( $team_slug, 'alumni' );

        // Get events
        $events = $this->get_team_events( $team_slug );

        return array(
            'activity_url_prefix' => $team['activity_url_prefix'],
            'team_name' => $team['team_name'],
            'not_managing_team' => (bool) $team['not_managing_team'],
            'team_links' => $this->get_team_links( $team_slug ),
            'type' => $team['type'],
            'default' => (bool) $team['is_default'],
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
    private function get_people_by_category( $team_slug, $category ) {
        $results = $this->wpdb->get_results( $this->wpdb->prepare(
            "SELECT * FROM {$this->wpdb->prefix}personal_crm_people WHERE team_slug = %s AND category = %s ORDER BY name",
            $team_slug, $category
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
     * Get team events
     */
    private function get_team_events( $team_slug ) {
        $results = $this->wpdb->get_results( $this->wpdb->prepare(
            "SELECT * FROM {$this->wpdb->prefix}personal_crm_events WHERE team_slug = %s ORDER BY start_date",
            $team_slug
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
     * Save team configuration data
     */
    public function save_team_config( $team_slug, $config ) {
        // Start transaction
        $this->wpdb->query( 'START TRANSACTION' );

        try {
            // Save/update team info
            $team_data = array(
                'slug' => $team_slug,
                'team_name' => $config['team_name'] ?? '',
                'activity_url_prefix' => $config['activity_url_prefix'] ?? '',
                'not_managing_team' => $config['not_managing_team'] ?? 1,
                'type' => $config['type'] ?? 'team',
                'is_default' => $config['default'] ?? 0,
                'updated_at' => current_time( 'mysql' )
            );

            $existing_team = $this->wpdb->get_var( $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->wpdb->prefix}personal_crm_teams WHERE slug = %s",
                $team_slug
            ) );

            if ( $existing_team ) {
                $this->wpdb->update(
                    $this->table_prefix . 'teams',
                    $team_data,
                    array( 'slug' => $team_slug ),
                    array( '%s', '%s', '%s', '%d', '%s', '%d', '%s' ),
                    array( '%s' )
                );
            } else {
                $team_data['created_at'] = current_time( 'mysql' );
                $this->wpdb->insert(
                    $this->table_prefix . 'teams',
                    $team_data,
                    array( '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s' )
                );
            }

            // Save team links
            $this->save_team_links( $team_slug, $config['team_links'] ?? array() );

            // Clear existing people for this team
            $this->wpdb->delete(
                $this->table_prefix . 'people',
                array( 'team_slug' => $team_slug ),
                array( '%s' )
            );

            // Save people
            $categories = array( 'team_members', 'leadership', 'consultants', 'alumni' );
            foreach ( $categories as $category ) {
                if ( isset( $config[$category] ) && is_array( $config[$category] ) ) {
                    $this->save_people( $team_slug, $category, $config[$category] );
                }
            }

            // Clear existing events for this team
            $this->wpdb->delete(
                $this->table_prefix . 'events',
                array( 'team_slug' => $team_slug ),
                array( '%s' )
            );

            // Save events
            if ( isset( $config['events'] ) && is_array( $config['events'] ) ) {
                $this->save_events( $team_slug, $config['events'] );
            }

            $this->wpdb->query( 'COMMIT' );
            return true;

        } catch ( Exception $e ) {
            $this->wpdb->query( 'ROLLBACK' );
            throw $e;
        }
    }

    /**
     * Save people for a category
     */
    private function save_people( $team_slug, $category, $people ) {
        foreach ( $people as $username => $person ) {
            $person_data = array(
                'username' => $username,
                'team_slug' => $team_slug,
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
                $this->table_prefix . 'people',
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
     * Save events for a team
     */
    private function save_events( $team_slug, $events ) {
        foreach ( $events as $event ) {
            $event_data = array(
                'team_slug' => $team_slug,
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
                $this->table_prefix . 'events',
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
     * Get all available team slugs
     */
    public function get_available_teams() {
        return $this->wpdb->get_col( "SELECT slug FROM {$this->wpdb->prefix}personal_crm_teams ORDER BY slug" );
    }

    /**
     * Get team name by slug
     */
    public function get_team_name( $team_slug ) {
        return $this->wpdb->get_var( $this->wpdb->prepare(
            "SELECT team_name FROM {$this->wpdb->prefix}personal_crm_teams WHERE slug = %s",
            $team_slug
        ) );
    }

    /**
     * Get team type by slug
     */
    public function get_team_type( $team_slug ) {
        $type = $this->wpdb->get_var( $this->wpdb->prepare(
            "SELECT type FROM {$this->wpdb->prefix}personal_crm_teams WHERE slug = %s",
            $team_slug
        ) );
        return $type ?: 'team';
    }

    /**
     * Get default team slug
     */
    public function get_default_team() {
        $slug = $this->wpdb->get_var( "SELECT slug FROM {$this->wpdb->prefix}personal_crm_teams WHERE is_default = 1 LIMIT 1" );

        if ( ! $slug ) {
            // If no default team, return first available team
            $slug = $this->wpdb->get_var( "SELECT slug FROM {$this->wpdb->prefix}personal_crm_teams ORDER BY slug LIMIT 1" );
        }

        return $slug ?: '';
    }

    /**
     * Check if team exists
     */
    public function team_exists( $team_slug ) {
        $count = $this->wpdb->get_var( $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->wpdb->prefix}personal_crm_teams WHERE slug = %s",
            $team_slug
        ) );
        return (bool) $count;
    }

    /**
     * Delete a team and all its data
     */
    public function delete_team( $team_slug ) {
        return $this->wpdb->delete(
            $this->table_prefix . 'teams',
            array( 'slug' => $team_slug ),
            array( '%s' )
        );
    }

    /**
     * Get HR feedback for a person
     */
    public function get_hr_feedback( $username, $month = null ) {
        if ( $month ) {
            return $this->wpdb->get_row( $this->wpdb->prepare(
                "SELECT * FROM {$this->wpdb->prefix}personal_crm_hr_feedback WHERE username = %s AND month = %s",
                $username, $month
            ), ARRAY_A );
        } else {
            $results = $this->wpdb->get_results( $this->wpdb->prepare(
                "SELECT * FROM {$this->wpdb->prefix}personal_crm_hr_feedback WHERE username = %s ORDER BY month DESC",
                $username
            ), ARRAY_A );

            $feedback = array();
            foreach ( $results as $row ) {
                $feedback[$row['month']] = $row;
            }
            return $feedback;
        }
    }

    /**
     * Save HR feedback for a person
     */
    public function save_hr_feedback( $username, $month, $data ) {
        $feedback_data = array(
            'username' => $username,
            'month' => $month,
            'feedback_to_person' => $data['feedback_to_person'] ?? '',
            'feedback_to_hr' => $data['feedback_to_hr'] ?? '',
            'submitted_to_hr' => $data['submitted_to_hr'] ?? 0,
            'draft_complete' => $data['draft_complete'] ?? 0,
            'google_doc_updated' => $data['google_doc_updated'] ?? 0,
            'not_necessary_reason' => $data['not_necessary_reason'] ?? '',
            'updated_at' => current_time( 'mysql' )
        );

        $existing_feedback = $this->wpdb->get_var( $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->wpdb->prefix}personal_crm_hr_feedback WHERE username = %s AND month = %s",
            $username, $month
        ) );

        if ( $existing_feedback ) {
            return $this->wpdb->update(
                $this->table_prefix . 'hr_feedback',
                $feedback_data,
                array( 'username' => $username, 'month' => $month ),
                array( '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s' ),
                array( '%s', '%s' )
            );
        } else {
            $feedback_data['created_at'] = current_time( 'mysql' );
            return $this->wpdb->insert(
                $this->table_prefix . 'hr_feedback',
                $feedback_data,
                array( '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s' )
            );
        }
    }

    /**
     * Get people count from team config
     */
    public function get_team_people_count( $team_slug ) {
        return (int) $this->wpdb->get_var( $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->wpdb->prefix}personal_crm_people WHERE team_slug = %s",
            $team_slug
        ) );
    }

    /**
     * Get all people names from team config for search purposes
     */
    public function get_team_people_names( $team_slug ) {
        return $this->wpdb->get_col( $this->wpdb->prepare(
            "SELECT name FROM {$this->wpdb->prefix}personal_crm_people WHERE team_slug = %s ORDER BY name",
            $team_slug
        ) );
    }

    /**
     * Get all people data from team config for search purposes
     */
    public function get_team_people_data( $team_slug ) {
        $results = $this->wpdb->get_results( $this->wpdb->prepare(
            "SELECT * FROM {$this->wpdb->prefix}personal_crm_people WHERE team_slug = %s ORDER BY name",
            $team_slug
        ), ARRAY_A );

        $people_data = array();
        foreach ( $results as $row ) {
            $username = $row['username'];
            $person_id = $row['id'];

            unset( $row['id'], $row['username'], $row['team_slug'], $row['category'], $row['created_at'], $row['updated_at'] );

            // Convert JSON fields back to arrays and get normalized links
            $row['links'] = $this->get_person_links( $person_id );
            $row['kids'] = json_decode( $row['kids'] ?: '[]', true );
            $row['github_repos'] = json_decode( $row['github_repos'] ?: '[]', true );
            $row['personal_events'] = json_decode( $row['personal_events'] ?: '[]', true );
            $row['notes'] = json_decode( $row['notes'] ?: '[]', true );

            // Convert boolean fields
            $row['left_company'] = (bool) $row['left_company'];
            $row['deceased'] = (bool) $row['deceased'];

            $people_data[$username] = $row;
        }

        return $people_data;
    }

    /**
     * Get team links
     */
    private function get_team_links( $team_slug ) {
        $results = $this->wpdb->get_results( $this->wpdb->prepare(
            "SELECT link_name, link_url FROM {$this->wpdb->prefix}personal_crm_team_links WHERE team_slug = %s ORDER BY link_name",
            $team_slug
        ), ARRAY_A );

        $links = array();
        foreach ( $results as $row ) {
            $links[$row['link_name']] = $row['link_url'];
        }

        return $links;
    }

    /**
     * Save team links
     */
    private function save_team_links( $team_slug, $links ) {
        // Delete existing links
        $this->wpdb->delete(
            $this->table_prefix . 'team_links',
            array( 'team_slug' => $team_slug ),
            array( '%s' )
        );

        // Insert new links
        if ( is_array( $links ) && ! empty( $links ) ) {
            foreach ( $links as $link_name => $link_url ) {
                $this->wpdb->insert(
                    $this->table_prefix . 'team_links',
                    array(
                        'team_slug' => $team_slug,
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
            $this->table_prefix . 'people_links',
            array( 'person_id' => $person_id ),
            array( '%d' )
        );

        // Insert new links
        if ( is_array( $links ) && ! empty( $links ) ) {
            foreach ( $links as $link_name => $link_url ) {
                $this->wpdb->insert(
                    $this->table_prefix . 'people_links',
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
            $this->table_prefix . 'event_links',
            array( 'event_id' => $event_id ),
            array( '%d' )
        );

        // Insert new links
        if ( is_array( $links ) && ! empty( $links ) ) {
            foreach ( $links as $link_name => $link_url ) {
                $this->wpdb->insert(
                    $this->table_prefix . 'event_links',
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
        $migrated_teams = 0;

        foreach ( $json_files as $file ) {
            $basename = basename( $file, '.json' );

            // Skip backup files, hr-feedback file, and composer file
            if ( $basename === 'hr-feedback' || $basename === 'composer' || strpos( $basename, '.bak' ) !== false || strpos( $basename, 'bak-' ) !== false ) {
                continue;
            }

            $content = file_get_contents( $file );
            $config = json_decode( $content, true );

            if ( json_last_error() === JSON_ERROR_NONE && $config ) {
                $this->save_team_config( $basename, $config );
                $migrated_teams++;
            }
        }

        // Migrate HR feedback data
        $feedback_file = $json_dir . 'hr-feedback.json';
        if ( file_exists( $feedback_file ) ) {
            $content = file_get_contents( $feedback_file );
            $feedback_data = json_decode( $content, true );

            if ( json_last_error() === JSON_ERROR_NONE && isset( $feedback_data['feedback'] ) ) {
                foreach ( $feedback_data['feedback'] as $username => $user_feedback ) {
                    foreach ( $user_feedback as $month => $data ) {
                        // Skip "not necessary" entries
                        if ( strpos( $month, '_not_necessary' ) !== false ) {
                            $actual_month = str_replace( '_not_necessary', '', $month );
                            $this->save_hr_feedback( $username, $actual_month, array( 'not_necessary_reason' => $data ) );
                        } else {
                            $this->save_hr_feedback( $username, $month, $data );
                        }
                    }
                }
            }
        }

        return $migrated_teams;
    }

    /**
     * Get wpdb instance for custom queries
     */
    public function get_wpdb() {
        return $this->wpdb;
    }
}