<?php
/**
 * Simple test for SQLite Storage implementation
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/sqlite-wpdb.php';
require_once __DIR__ . '/../includes/storage.php';

class StorageTest {
    private $storage;
    private $test_db_file;
    
    public function __construct() {
        // Use a separate test database
        $this->test_db_file = __DIR__ . '/../data/test.db';
        if ( file_exists( $this->test_db_file ) ) {
            unlink( $this->test_db_file );
        }
        $sqlite_wpdb = new sqlite_wpdb( $this->test_db_file, 'wp_' );
        $this->storage = new PersonalCRM\Storage( $sqlite_wpdb );
    }
    
    public function run() {
        echo "Running SQLite Storage Tests...\n\n";
        
        $this->testTeamCreation();
        $this->testTeamRetrieval();
        $this->testTeamListing();
        $this->testHRFeedback();
        $this->testMigration();
        
        echo "\nAll tests passed! ✓\n";
        
        // Clean up
        if ( file_exists( $this->test_db_file ) ) {
            unlink( $this->test_db_file );
        }
    }
    
    private function testTeamCreation() {
        echo "Testing team creation... ";
        
        $config = array(
            'team_name' => 'Test Team',
            'activity_url_prefix' => 'https://example.com/',
            'not_managing_team' => 0,
            'team_links' => array( 'Website' => 'https://test.com' ),
            'type' => 'team',
            'default' => 0,
            'team_members' => array(
                'testuser' => array(
                    'name' => 'Test User',
                    'role' => 'Developer',
                    'email' => 'test@example.com',
                    'birthday' => '1990-01-01',
                    'links' => array( 'GitHub' => 'https://github.com/testuser' ),
                    'kids' => array(),
                    'github_repos' => array(),
                    'personal_events' => array(),
                    'notes' => array()
                )
            ),
            'leadership' => array(),
            'consultants' => array(),
            'alumni' => array(),
            'events' => array(
                array(
                    'type' => 'meeting',
                    'name' => 'Test Meeting',
                    'description' => 'A test meeting',
                    'start_date' => '2025-01-15',
                    'end_date' => '2025-01-15',
                    'location' => 'Online',
                    'links' => array()
                )
            )
        );
        
        $result = $this->storage->save_group( 'test-team', $config );
        $this->assert( $result === true, 'Team creation failed' );
        
        echo "✓\n";
    }
    
    private function testTeamRetrieval() {
        echo "Testing team retrieval... ";
        
        $config = $this->storage->get_group( 'test-team' );
        $this->assert( $config !== null, 'Team retrieval returned null' );
        $this->assert( $config['team_name'] === 'Test Team', 'Team name mismatch' );
        $this->assert( count( $config['team_members'] ) === 1, 'Team members count mismatch' );
        $this->assert( count( $config['events'] ) === 1, 'Events count mismatch' );
        
        echo "✓\n";
    }
    
    private function testTeamListing() {
        echo "Testing team listing... ";
        
        $teams = $this->storage->get_available_groups();
        $this->assert( in_array( 'test-team', $teams ), 'Test team not found in listing' );
        
        $team_name = $this->storage->get_team_name( 'test-team' );
        $this->assert( $team_name === 'Test Team', 'Team name from storage mismatch' );
        
        echo "✓\n";
    }
    
    private function testHRFeedback() {
        echo "Testing HR feedback... ";
        
        $feedback_data = array(
            'feedback_to_person' => 'Excellent work!',
            'feedback_to_hr' => 'Top performer',
            'submitted_to_hr' => 0,
            'draft_complete' => 1,
            'google_doc_updated' => 0,
            'not_necessary_reason' => ''
        );
        
        $result = $this->storage->save_hr_feedback( 'testuser', '2025-01', $feedback_data );
        $this->assert( $result !== false, 'HR feedback save failed' );
        
        $retrieved = $this->storage->get_hr_feedback( 'testuser', '2025-01' );
        $this->assert( $retrieved !== null, 'HR feedback retrieval failed' );
        $this->assert( $retrieved['feedback_to_person'] === 'Excellent work!', 'HR feedback content mismatch' );
        
        echo "✓\n";
    }
    
    private function testMigration() {
        echo "Testing migration functionality... ";
        
        // Create a test JSON file
        $test_json_dir = __DIR__ . '/../test_data/';
        if ( ! file_exists( $test_json_dir ) ) {
            mkdir( $test_json_dir, 0755, true );
        }
        
        $test_config = array(
            'team_name' => 'Migrated Team',
            'activity_url_prefix' => '',
            'team_members' => array(
                'migrated_user' => array(
                    'name' => 'Migrated User',
                    'role' => 'Tester'
                )
            ),
            'leadership' => array(),
            'consultants' => array(),
            'alumni' => array(),
            'events' => array()
        );
        
        file_put_contents( $test_json_dir . 'migrated-team.json', json_encode( $test_config, JSON_PRETTY_PRINT ) );
        
        $migrated_count = $this->storage->migrate_from_json( $test_json_dir );
        $this->assert( $migrated_count === 1, 'Migration count mismatch' );
        
        $migrated_config = $this->storage->get_group( 'migrated-team' );
        $this->assert( $migrated_config !== null, 'Migrated team not found' );
        $this->assert( $migrated_config['team_name'] === 'Migrated Team', 'Migrated team name mismatch' );
        
        // Clean up test data
        unlink( $test_json_dir . 'migrated-team.json' );
        rmdir( $test_json_dir );
        
        echo "✓\n";
    }
    
    private function assert( $condition, $message ) {
        if ( ! $condition ) {
            throw new Exception( "Test failed: $message" );
        }
    }
}

try {
    $test = new StorageTest();
    $test->run();
    echo "SUCCESS: All SQLite storage tests passed!\n";
    exit( 0 );
} catch ( Exception $e ) {
    echo "FAILURE: " . $e->getMessage() . "\n";
    exit( 1 );
}
?>