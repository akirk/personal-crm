<?php
/**
 * SQLite Database Storage Class
 * 
 * SQLite database storage for better performance, concurrent access, and data integrity.
 */

require_once __DIR__ . '/storage-interface.php';

class Storage implements StorageInterface {
    private $db;
    private $db_file;
    
    public function __construct( $db_file = null ) {
        if ( $db_file === null ) {
            $db_file = __DIR__ . '/../data/a8c.db';
        }
        $this->db_file = $db_file;
        $this->init_database();
    }
    
    /**
     * Initialize SQLite database and create tables if they don't exist
     */
    private function init_database() {
        // Create data directory if it doesn't exist
        $data_dir = dirname( $this->db_file );
        if ( ! file_exists( $data_dir ) ) {
            mkdir( $data_dir, 0755, true );
        }
        
        $this->db = new SQLite3( $this->db_file );
        $this->db->enableExceptions( true );
        
        // Create teams table
        $this->db->exec( "
            CREATE TABLE IF NOT EXISTS teams (
                slug TEXT PRIMARY KEY,
                team_name TEXT NOT NULL,
                activity_url_prefix TEXT DEFAULT '',
                not_managing_team INTEGER DEFAULT 1,
                team_links TEXT DEFAULT '{}',
                type TEXT DEFAULT 'team',
                is_default INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        " );
        
        // Create people table  
        $this->db->exec( "
            CREATE TABLE IF NOT EXISTS people (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL,
                team_slug TEXT NOT NULL,
                category TEXT NOT NULL, -- 'team_members', 'leadership', 'consultants', 'alumni'
                name TEXT NOT NULL,
                nickname TEXT DEFAULT '',
                role TEXT DEFAULT '',
                email TEXT DEFAULT '',
                birthday TEXT DEFAULT '',
                company_anniversary TEXT DEFAULT '',
                partner TEXT DEFAULT '',
                partner_birthday TEXT DEFAULT '',
                location TEXT DEFAULT '',
                timezone TEXT DEFAULT '',
                github TEXT DEFAULT '',
                wordpress TEXT DEFAULT '',
                linkedin TEXT DEFAULT '',
                website TEXT DEFAULT '',
                new_company TEXT DEFAULT '',
                new_company_website TEXT DEFAULT '',
                deceased_date TEXT DEFAULT '',
                left_company INTEGER DEFAULT 0,
                deceased INTEGER DEFAULT 0,
                links TEXT DEFAULT '{}',
                kids TEXT DEFAULT '[]',
                github_repos TEXT DEFAULT '[]',
                personal_events TEXT DEFAULT '[]',
                notes TEXT DEFAULT '[]',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (team_slug) REFERENCES teams(slug) ON DELETE CASCADE,
                UNIQUE(username, team_slug)
            )
        " );
        
        // Create events table
        $this->db->exec( "
            CREATE TABLE IF NOT EXISTS events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                team_slug TEXT NOT NULL,
                type TEXT NOT NULL,
                name TEXT NOT NULL,
                description TEXT DEFAULT '',
                start_date TEXT NOT NULL,
                end_date TEXT DEFAULT '',
                location TEXT DEFAULT '',
                links TEXT DEFAULT '{}',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (team_slug) REFERENCES teams(slug) ON DELETE CASCADE
            )
        " );
        
        // Create hr_feedback table
        $this->db->exec( "
            CREATE TABLE IF NOT EXISTS hr_feedback (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL,
                month TEXT NOT NULL,
                feedback_to_person TEXT DEFAULT '',
                feedback_to_hr TEXT DEFAULT '',
                submitted_to_hr INTEGER DEFAULT 0,
                draft_complete INTEGER DEFAULT 0,
                google_doc_updated INTEGER DEFAULT 0,
                not_necessary_reason TEXT DEFAULT '',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(username, month)
            )
        " );
        
        // Create indexes for better performance
        $this->db->exec( "CREATE INDEX IF NOT EXISTS idx_people_team_slug ON people(team_slug)" );
        $this->db->exec( "CREATE INDEX IF NOT EXISTS idx_people_username ON people(username)" );
        $this->db->exec( "CREATE INDEX IF NOT EXISTS idx_events_team_slug ON events(team_slug)" );
        $this->db->exec( "CREATE INDEX IF NOT EXISTS idx_hr_feedback_username ON hr_feedback(username)" );
        $this->db->exec( "CREATE INDEX IF NOT EXISTS idx_hr_feedback_month ON hr_feedback(month)" );
    }
    
    /**
     * Get team configuration data
     */
    public function get_team_config( $team_slug ) {
        $stmt = $this->db->prepare( "SELECT * FROM teams WHERE slug = ?" );
        $stmt->bindValue( 1, $team_slug, SQLITE3_TEXT );
        $result = $stmt->execute();
        $team = $result->fetchArray( SQLITE3_ASSOC );
        
        if ( ! $team ) {
            return null;
        }
        
        // Get team members
        $team_members = $this->get_people_by_category( $team_slug, 'team_members' );
        $leadership = $this->get_people_by_category( $team_slug, 'leadership' );
        $consultants = $this->get_people_by_category( $team_slug, 'consultants' );
        $alumni = $this->get_people_by_category( $team_slug, 'alumni' );
        
        // Get events
        $events = $this->get_team_events( $team_slug );
        
        return array(
            'activity_url_prefix' => $team['activity_url_prefix'],
            'team_name' => $team['team_name'],
            'not_managing_team' => $team['not_managing_team'],
            'team_links' => json_decode( $team['team_links'], true ) ?: array(),
            'type' => $team['type'],
            'default' => $team['is_default'],
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
        $stmt = $this->db->prepare( "SELECT * FROM people WHERE team_slug = ? AND category = ? ORDER BY name" );
        $stmt->bindValue( 1, $team_slug, SQLITE3_TEXT );
        $stmt->bindValue( 2, $category, SQLITE3_TEXT );
        $result = $stmt->execute();
        
        $people = array();
        while ( $row = $result->fetchArray( SQLITE3_ASSOC ) ) {
            $username = $row['username'];
            unset( $row['id'], $row['username'], $row['team_slug'], $row['category'], $row['created_at'], $row['updated_at'] );
            
            // Convert JSON fields back to arrays
            $row['links'] = json_decode( $row['links'], true ) ?: array();
            $row['kids'] = json_decode( $row['kids'], true ) ?: array();
            $row['github_repos'] = json_decode( $row['github_repos'], true ) ?: array();
            $row['personal_events'] = json_decode( $row['personal_events'], true ) ?: array();
            $row['notes'] = json_decode( $row['notes'], true ) ?: array();
            
            $people[$username] = $row;
        }
        
        return $people;
    }
    
    /**
     * Get team events
     */
    private function get_team_events( $team_slug ) {
        $stmt = $this->db->prepare( "SELECT * FROM events WHERE team_slug = ? ORDER BY start_date" );
        $stmt->bindValue( 1, $team_slug, SQLITE3_TEXT );
        $result = $stmt->execute();
        
        $events = array();
        while ( $row = $result->fetchArray( SQLITE3_ASSOC ) ) {
            unset( $row['id'], $row['team_slug'], $row['created_at'], $row['updated_at'] );
            $row['links'] = json_decode( $row['links'], true ) ?: array();
            $events[] = $row;
        }
        
        return $events;
    }
    
    /**
     * Save team configuration data
     */
    public function save_team_config( $team_slug, $config ) {
        $this->db->exec( 'BEGIN TRANSACTION' );
        
        try {
            // Save/update team info
            $stmt = $this->db->prepare( "
                INSERT OR REPLACE INTO teams 
                (slug, team_name, activity_url_prefix, not_managing_team, team_links, type, is_default, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            " );
            $stmt->bindValue( 1, $team_slug, SQLITE3_TEXT );
            $stmt->bindValue( 2, $config['team_name'] ?? '', SQLITE3_TEXT );
            $stmt->bindValue( 3, $config['activity_url_prefix'] ?? '', SQLITE3_TEXT );
            $stmt->bindValue( 4, $config['not_managing_team'] ?? 1, SQLITE3_INTEGER );
            $stmt->bindValue( 5, json_encode( $config['team_links'] ?? array() ), SQLITE3_TEXT );
            $stmt->bindValue( 6, $config['type'] ?? 'team', SQLITE3_TEXT );
            $stmt->bindValue( 7, $config['default'] ?? 0, SQLITE3_INTEGER );
            $stmt->execute();
            
            // Clear existing people for this team
            $stmt = $this->db->prepare( "DELETE FROM people WHERE team_slug = ?" );
            $stmt->bindValue( 1, $team_slug, SQLITE3_TEXT );
            $stmt->execute();
            
            // Save people
            $categories = array( 'team_members', 'leadership', 'consultants', 'alumni' );
            foreach ( $categories as $category ) {
                if ( isset( $config[$category] ) && is_array( $config[$category] ) ) {
                    $this->save_people( $team_slug, $category, $config[$category] );
                }
            }
            
            // Clear existing events for this team
            $stmt = $this->db->prepare( "DELETE FROM events WHERE team_slug = ?" );
            $stmt->bindValue( 1, $team_slug, SQLITE3_TEXT );
            $stmt->execute();
            
            // Save events
            if ( isset( $config['events'] ) && is_array( $config['events'] ) ) {
                $this->save_events( $team_slug, $config['events'] );
            }
            
            $this->db->exec( 'COMMIT' );
            return true;
            
        } catch ( Exception $e ) {
            $this->db->exec( 'ROLLBACK' );
            throw $e;
        }
    }
    
    /**
     * Save people for a category
     */
    private function save_people( $team_slug, $category, $people ) {
        $stmt = $this->db->prepare( "
            INSERT INTO people 
            (username, team_slug, category, name, nickname, role, email, birthday, company_anniversary, 
             partner, partner_birthday, location, timezone, github, wordpress, linkedin, website, 
             new_company, new_company_website, deceased_date, left_company, deceased, 
             links, kids, github_repos, personal_events, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        " );
        
        foreach ( $people as $username => $person ) {
            $stmt->bindValue( 1, $username, SQLITE3_TEXT );
            $stmt->bindValue( 2, $team_slug, SQLITE3_TEXT );
            $stmt->bindValue( 3, $category, SQLITE3_TEXT );
            $stmt->bindValue( 4, $person['name'] ?? '', SQLITE3_TEXT );
            $stmt->bindValue( 5, $person['nickname'] ?? '', SQLITE3_TEXT );
            $stmt->bindValue( 6, $person['role'] ?? '', SQLITE3_TEXT );
            $stmt->bindValue( 7, $person['email'] ?? '', SQLITE3_TEXT );
            $stmt->bindValue( 8, $person['birthday'] ?? '', SQLITE3_TEXT );
            $stmt->bindValue( 9, $person['company_anniversary'] ?? '', SQLITE3_TEXT );
            $stmt->bindValue( 10, $person['partner'] ?? '', SQLITE3_TEXT );
            $stmt->bindValue( 11, $person['partner_birthday'] ?? '', SQLITE3_TEXT );
            $stmt->bindValue( 12, $person['location'] ?? '', SQLITE3_TEXT );
            $stmt->bindValue( 13, $person['timezone'] ?? '', SQLITE3_TEXT );
            $stmt->bindValue( 14, $person['github'] ?? '', SQLITE3_TEXT );
            $stmt->bindValue( 15, $person['wordpress'] ?? '', SQLITE3_TEXT );
            $stmt->bindValue( 16, $person['linkedin'] ?? '', SQLITE3_TEXT );
            $stmt->bindValue( 17, $person['website'] ?? '', SQLITE3_TEXT );
            $stmt->bindValue( 18, $person['new_company'] ?? '', SQLITE3_TEXT );
            $stmt->bindValue( 19, $person['new_company_website'] ?? '', SQLITE3_TEXT );
            $stmt->bindValue( 20, $person['deceased_date'] ?? '', SQLITE3_TEXT );
            $stmt->bindValue( 21, $person['left_company'] ?? 0, SQLITE3_INTEGER );
            $stmt->bindValue( 22, $person['deceased'] ?? 0, SQLITE3_INTEGER );
            $stmt->bindValue( 23, json_encode( $person['links'] ?? array() ), SQLITE3_TEXT );
            $stmt->bindValue( 24, json_encode( $person['kids'] ?? array() ), SQLITE3_TEXT );
            $stmt->bindValue( 25, json_encode( $person['github_repos'] ?? array() ), SQLITE3_TEXT );
            $stmt->bindValue( 26, json_encode( $person['personal_events'] ?? array() ), SQLITE3_TEXT );
            $stmt->bindValue( 27, json_encode( $person['notes'] ?? array() ), SQLITE3_TEXT );
            $stmt->execute();
        }
    }
    
    /**
     * Save events for a team
     */
    private function save_events( $team_slug, $events ) {
        $stmt = $this->db->prepare( "
            INSERT INTO events 
            (team_slug, type, name, description, start_date, end_date, location, links)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        " );
        
        foreach ( $events as $event ) {
            $stmt->bindValue( 1, $team_slug, SQLITE3_TEXT );
            $stmt->bindValue( 2, $event['type'] ?? 'event', SQLITE3_TEXT );
            $stmt->bindValue( 3, $event['name'] ?? '', SQLITE3_TEXT );
            $stmt->bindValue( 4, $event['description'] ?? '', SQLITE3_TEXT );
            $stmt->bindValue( 5, $event['start_date'] ?? '', SQLITE3_TEXT );
            $stmt->bindValue( 6, $event['end_date'] ?? '', SQLITE3_TEXT );
            $stmt->bindValue( 7, $event['location'] ?? '', SQLITE3_TEXT );
            $stmt->bindValue( 8, json_encode( $event['links'] ?? array() ), SQLITE3_TEXT );
            $stmt->execute();
        }
    }
    
    /**
     * Get all team slugs
     */
    public function get_available_teams() {
        $result = $this->db->query( "SELECT slug FROM teams ORDER BY slug" );
        $teams = array();
        while ( $row = $result->fetchArray( SQLITE3_ASSOC ) ) {
            $teams[] = $row['slug'];
        }
        return $teams;
    }
    
    /**
     * Get team name by slug
     */
    public function get_team_name( $team_slug ) {
        $stmt = $this->db->prepare( "SELECT team_name FROM teams WHERE slug = ?" );
        $stmt->bindValue( 1, $team_slug, SQLITE3_TEXT );
        $result = $stmt->execute();
        $row = $result->fetchArray( SQLITE3_ASSOC );
        return $row ? $row['team_name'] : null;
    }
    
    /**
     * Get team type by slug
     */
    public function get_team_type( $team_slug ) {
        $stmt = $this->db->prepare( "SELECT type FROM teams WHERE slug = ?" );
        $stmt->bindValue( 1, $team_slug, SQLITE3_TEXT );
        $result = $stmt->execute();
        $row = $result->fetchArray( SQLITE3_ASSOC );
        return $row ? $row['type'] : 'team';
    }
    
    /**
     * Get default team slug
     */
    public function get_default_team() {
        $result = $this->db->query( "SELECT slug FROM teams WHERE is_default = 1 LIMIT 1" );
        $row = $result->fetchArray( SQLITE3_ASSOC );
        
        if ( $row ) {
            return $row['slug'];
        }
        
        // If no default team, return first available team
        $result = $this->db->query( "SELECT slug FROM teams ORDER BY slug LIMIT 1" );
        $row = $result->fetchArray( SQLITE3_ASSOC );
        return $row ? $row['slug'] : '';
    }
    
    /**
     * Get HR feedback for a person
     */
    public function get_hr_feedback( $username, $month = null ) {
        if ( $month ) {
            $stmt = $this->db->prepare( "SELECT * FROM hr_feedback WHERE username = ? AND month = ?" );
            $stmt->bindValue( 1, $username, SQLITE3_TEXT );
            $stmt->bindValue( 2, $month, SQLITE3_TEXT );
        } else {
            $stmt = $this->db->prepare( "SELECT * FROM hr_feedback WHERE username = ? ORDER BY month DESC" );
            $stmt->bindValue( 1, $username, SQLITE3_TEXT );
        }
        
        $result = $stmt->execute();
        
        if ( $month ) {
            return $result->fetchArray( SQLITE3_ASSOC ) ?: null;
        } else {
            $feedback = array();
            while ( $row = $result->fetchArray( SQLITE3_ASSOC ) ) {
                $feedback[$row['month']] = $row;
            }
            return $feedback;
        }
    }
    
    /**
     * Save HR feedback for a person
     */
    public function save_hr_feedback( $username, $month, $data ) {
        $stmt = $this->db->prepare( "
            INSERT OR REPLACE INTO hr_feedback 
            (username, month, feedback_to_person, feedback_to_hr, submitted_to_hr, 
             draft_complete, google_doc_updated, not_necessary_reason, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        " );
        
        $stmt->bindValue( 1, $username, SQLITE3_TEXT );
        $stmt->bindValue( 2, $month, SQLITE3_TEXT );
        $stmt->bindValue( 3, $data['feedback_to_person'] ?? '', SQLITE3_TEXT );
        $stmt->bindValue( 4, $data['feedback_to_hr'] ?? '', SQLITE3_TEXT );
        $stmt->bindValue( 5, $data['submitted_to_hr'] ?? 0, SQLITE3_INTEGER );
        $stmt->bindValue( 6, $data['draft_complete'] ?? 0, SQLITE3_INTEGER );
        $stmt->bindValue( 7, $data['google_doc_updated'] ?? 0, SQLITE3_INTEGER );
        $stmt->bindValue( 8, $data['not_necessary_reason'] ?? '', SQLITE3_TEXT );
        
        return $stmt->execute();
    }
    
    /**
     * Check if team exists
     */
    public function team_exists( $team_slug ) {
        $stmt = $this->db->prepare( "SELECT 1 FROM teams WHERE slug = ? LIMIT 1" );
        $stmt->bindValue( 1, $team_slug, SQLITE3_TEXT );
        $result = $stmt->execute();
        return (bool) $result->fetchArray( SQLITE3_ASSOC );
    }
    
    /**
     * Delete a team and all its data
     */
    public function delete_team( $team_slug ) {
        $stmt = $this->db->prepare( "DELETE FROM teams WHERE slug = ?" );
        $stmt->bindValue( 1, $team_slug, SQLITE3_TEXT );
        return $stmt->execute();
    }
    
    /**
     * Migrate data from JSON files to SQLite
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
     * Get database connection for custom queries
     */
    public function get_db() {
        return $this->db;
    }
    
    /**
     * Get people count from team config
     */
    public function get_team_people_count( $team_slug ) {
        $stmt = $this->db->prepare( "SELECT COUNT(*) as count FROM people WHERE team_slug = ?" );
        $stmt->bindValue( 1, $team_slug, SQLITE3_TEXT );
        $result = $stmt->execute();
        $row = $result->fetchArray( SQLITE3_ASSOC );
        return $row ? (int) $row['count'] : 0;
    }
    
    /**
     * Get all people names from team config for search purposes
     */
    public function get_team_people_names( $team_slug ) {
        $stmt = $this->db->prepare( "SELECT name FROM people WHERE team_slug = ? ORDER BY name" );
        $stmt->bindValue( 1, $team_slug, SQLITE3_TEXT );
        $result = $stmt->execute();
        
        $names = array();
        while ( $row = $result->fetchArray( SQLITE3_ASSOC ) ) {
            $names[] = $row['name'];
        }
        
        return $names;
    }
    
    /**
     * Get all people data from team config for search purposes
     */
    public function get_team_people_data( $team_slug ) {
        $stmt = $this->db->prepare( "SELECT * FROM people WHERE team_slug = ? ORDER BY name" );
        $stmt->bindValue( 1, $team_slug, SQLITE3_TEXT );
        $result = $stmt->execute();
        
        $people_data = array();
        while ( $row = $result->fetchArray( SQLITE3_ASSOC ) ) {
            $username = $row['username'];
            unset( $row['id'], $row['username'], $row['team_slug'], $row['category'], $row['created_at'], $row['updated_at'] );
            
            // Convert JSON fields back to arrays
            $row['links'] = json_decode( $row['links'], true ) ?: array();
            $row['kids'] = json_decode( $row['kids'], true ) ?: array();
            $row['github_repos'] = json_decode( $row['github_repos'], true ) ?: array();
            $row['personal_events'] = json_decode( $row['personal_events'], true ) ?: array();
            $row['notes'] = json_decode( $row['notes'], true ) ?: array();
            
            $people_data[$username] = $row;
        }
        
        return $people_data;
    }

    /**
     * Close database connection
     */
    public function close() {
        if ( $this->db ) {
            $this->db->close();
        }
    }
}