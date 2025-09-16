<?php
/**
 * JSON File Storage Class
 * 
 * Original JSON-based storage implementation restored and refactored into a class
 */

namespace PersonalCRM;

require_once __DIR__ . '/storage-interface.php';

if ( class_exists( '\PersonalCRM\JsonStorage' ) ) {
    return;
}

class JsonStorage implements StorageInterface {
    private $json_dir;
    
    public function __construct( $json_dir = null ) {
        if ( $json_dir === null ) {
            $json_dir = __DIR__ . '/../';
        }
        $this->json_dir = rtrim( $json_dir, '/' ) . '/';
    }
    
    /**
     * Get team configuration data
     */
    public function get_team_config( $team_slug ) {
        $file_path = $this->json_dir . $team_slug . '.json';
        
        if ( ! file_exists( $file_path ) ) {
            return null;
        }
        
        $content = file_get_contents( $file_path );
        $config = json_decode( $content, true );
        
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return null;
        }
        
        return $config;
    }
    
    /**
     * Save team configuration data
     */
    public function save_team_config( $team_slug, $config ) {
        $file_path = $this->json_dir . $team_slug . '.json';
        
        // Create backup first
        $this->create_backup( $file_path );
        
        // Sort events by date before saving
        if ( isset( $config['events'] ) && is_array( $config['events'] ) ) {
            usort( $config['events'], function( $a, $b ) {
                $dateA = $a['start_date'] ?? '';
                $dateB = $b['start_date'] ?? '';
                return strcmp( $dateA, $dateB );
            } );
        }
        
        $json = json_encode( $config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
        return file_put_contents( $file_path, $json ) !== false;
    }
    
    /**
     * Get all available team slugs
     */
    public function get_available_teams() {
        $teams = array();
        $json_files = glob( $this->json_dir . '*.json' );
        
        foreach ( $json_files as $file ) {
            $basename = basename( $file, '.json' );
            
            // Skip backup files, hr-feedback file, and composer file
            if ( $basename === 'hr-feedback' || $basename === 'composer' || strpos( $basename, '.bak' ) !== false || strpos( $basename, 'bak-' ) !== false ) {
                continue;
            }
            
            $teams[] = $basename;
        }
        
        sort( $teams );
        return $teams;
    }
    
    /**
     * Get team name by slug
     */
    public function get_team_name( $team_slug ) {
        $config = $this->get_team_config( $team_slug );
        
        if ( $config && isset( $config['team_name'] ) ) {
            return $config['team_name'];
        }
        
        return ucfirst( str_replace( '_', ' ', $team_slug ) );
    }
    
    /**
     * Get team type by slug
     */
    public function get_team_type( $team_slug ) {
        $config = $this->get_team_config( $team_slug );
        
        if ( $config && isset( $config['type'] ) ) {
            return $config['type'];
        }
        
        return 'team'; // Default to 'team' if not specified
    }
    
    /**
     * Get default team slug
     */
    public function get_default_team() {
        $available_teams = $this->get_available_teams();
        
        if ( count( $available_teams ) === 1 ) {
            return $available_teams[0];
        }
        
        foreach ( $available_teams as $team_slug ) {
            $config = $this->get_team_config( $team_slug );
            if ( $config && isset( $config['default'] ) && $config['default'] ) {
                return $team_slug;
            }
        }
        
        return '';
    }
    
    /**
     * Check if team exists
     */
    public function team_exists( $team_slug ) {
        $file_path = $this->json_dir . $team_slug . '.json';
        return file_exists( $file_path );
    }
    
    /**
     * Delete a team and all its data
     */
    public function delete_team( $team_slug ) {
        $file_path = $this->json_dir . $team_slug . '.json';
        
        if ( file_exists( $file_path ) ) {
            return unlink( $file_path );
        }
        
        return true; // Team doesn't exist, consider deletion successful
    }
    
    /**
     * Get HR feedback for a person
     */
    public function get_hr_feedback( $username, $month = null ) {
        $feedback_file = $this->json_dir . 'hr-feedback.json';
        
        if ( ! file_exists( $feedback_file ) ) {
            return $month ? null : array();
        }
        
        $content = file_get_contents( $feedback_file );
        $feedback_data = json_decode( $content, true ) ?: array();
        
        if ( ! isset( $feedback_data['feedback'][ $username ] ) ) {
            return $month ? null : array();
        }
        
        $user_feedback = $feedback_data['feedback'][ $username ];
        
        if ( $month ) {
            return $user_feedback[ $month ] ?? null;
        }
        
        return $user_feedback;
    }
    
    /**
     * Save HR feedback for a person
     */
    public function save_hr_feedback( $username, $month, $data ) {
        $feedback_file = $this->json_dir . 'hr-feedback.json';
        
        // Load existing data
        $feedback_data = array( 'feedback' => array() );
        if ( file_exists( $feedback_file ) ) {
            $content = file_get_contents( $feedback_file );
            $existing_data = json_decode( $content, true );
            if ( json_last_error() === JSON_ERROR_NONE && $existing_data ) {
                $feedback_data = $existing_data;
            }
        }
        
        // Initialize user feedback array if not exists
        if ( ! isset( $feedback_data['feedback'][ $username ] ) ) {
            $feedback_data['feedback'][ $username ] = array();
        }
        
        // Add updated timestamp
        $data['updated_at'] = date( 'Y-m-d H:i:s' );
        
        // Save the feedback data
        $feedback_data['feedback'][ $username ][ $month ] = $data;
        
        // Create backup first
        $this->create_backup( $feedback_file );
        
        // Save to file
        $json_content = json_encode( $feedback_data, JSON_PRETTY_PRINT );
        return file_put_contents( $feedback_file, $json_content ) !== false;
    }
    
    /**
     * Create backup of existing file (max one per minute)
     */
    private function create_backup( $file_path ) {
        if ( ! file_exists( $file_path ) ) {
            return true; // No file to backup
        }
        
        // Create backups directory if it doesn't exist
        $backups_dir = dirname( $file_path ) . '/backups';
        if ( ! file_exists( $backups_dir ) ) {
            mkdir( $backups_dir, 0755, true );
        }
        
        // Generate backup filename in backups directory (minute precision only)
        $filename = basename( $file_path );
        $backup_timestamp = date( '-Y-m-d-H-i' ); // No seconds - only minute precision
        $backup_filename = substr( $filename, 0, -4 ) . 'bak' . $backup_timestamp . '.json';
        $backup_path = $backups_dir . '/' . $backup_filename;
        
        // Only create backup if one doesn't already exist for this minute
        if ( file_exists( $backup_path ) ) {
            return true; // Backup for this minute already exists
        }
        
        return copy( $file_path, $backup_path );
    }
    
    /**
     * Get people count from team config
     */
    public function get_team_people_count( $team_slug ) {
        $config = $this->get_team_config( $team_slug );
        
        if ( ! $config ) {
            return 0;
        }
        
        $count = 0;
        
        // Count team members
        if ( isset( $config['team_members'] ) && is_array( $config['team_members'] ) ) {
            $count += count( $config['team_members'] );
        }
        
        // Count leadership
        if ( isset( $config['leadership'] ) && is_array( $config['leadership'] ) ) {
            $count += count( $config['leadership'] );
        }
        
        // Count consultants
        if ( isset( $config['consultants'] ) && is_array( $config['consultants'] ) ) {
            $count += count( $config['consultants'] );
        }
        
        return $count;
    }
    
    /**
     * Get all people names from team config for search purposes
     */
    public function get_team_people_names( $team_slug ) {
        $config = $this->get_team_config( $team_slug );
        
        if ( ! $config ) {
            return array();
        }
        
        $names = array();
        
        // Get names from team members
        if ( isset( $config['team_members'] ) && is_array( $config['team_members'] ) ) {
            foreach ( $config['team_members'] as $person ) {
                if ( isset( $person['name'] ) ) {
                    $names[] = $person['name'];
                }
            }
        }
        
        // Get names from leadership
        if ( isset( $config['leadership'] ) && is_array( $config['leadership'] ) ) {
            foreach ( $config['leadership'] as $person ) {
                if ( isset( $person['name'] ) ) {
                    $names[] = $person['name'];
                }
            }
        }
        
        // Get names from consultants
        if ( isset( $config['consultants'] ) && is_array( $config['consultants'] ) ) {
            foreach ( $config['consultants'] as $person ) {
                if ( isset( $person['name'] ) ) {
                    $names[] = $person['name'];
                }
            }
        }
        
        return $names;
    }
    
    /**
     * Get all people data (username => person data) from team config for search purposes
     */
    public function get_team_people_data( $team_slug ) {
        $config = $this->get_team_config( $team_slug );
        
        if ( ! $config ) {
            return array();
        }
        
        $people_data = array();
        
        // Get data from team members
        if ( isset( $config['team_members'] ) && is_array( $config['team_members'] ) ) {
            foreach ( $config['team_members'] as $username => $person ) {
                if ( isset( $person['name'] ) ) {
                    $people_data[$username] = $person;
                }
            }
        }
        
        // Get data from leadership
        if ( isset( $config['leadership'] ) && is_array( $config['leadership'] ) ) {
            foreach ( $config['leadership'] as $username => $person ) {
                if ( isset( $person['name'] ) ) {
                    $people_data[$username] = $person;
                }
            }
        }
        
        // Get data from consultants
        if ( isset( $config['consultants'] ) && is_array( $config['consultants'] ) ) {
            foreach ( $config['consultants'] as $username => $person ) {
                if ( isset( $person['name'] ) ) {
                    $people_data[$username] = $person;
                }
            }
        }
        
        return $people_data;
    }
}