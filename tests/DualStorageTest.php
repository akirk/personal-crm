<?php
/**
 * Test for both JSON and SQLite storage backends
 */

require_once __DIR__ . '/../includes/sqlite-wpdb.php';
require_once __DIR__ . '/../includes/storage.php';

class DualStorageTest {
    
    public function run() {
        echo "Running Dual Storage Backend Tests...\n\n";
        
        $this->testJsonStorage();
        $this->testSqliteStorage();
        $this->testStorageFactory();
        
        echo "\nAll dual storage tests passed! ✓\n";
    }
    
    private function testJsonStorage() {
        echo "Testing JSON Storage Backend... ";
        
        // Use temporary directory for testing
        $test_dir = __DIR__ . '/../test_json_data/';
        if ( ! file_exists( $test_dir ) ) {
            mkdir( $test_dir, 0755, true );
        }
        
        $storage = new JsonStorage( $test_dir );
        
        // Test team creation
        $config = $this->getTestConfig();
        $result = $storage->save_team_config( 'json-test-team', $config );
        $this->assert( $result === true, 'JSON team creation failed' );
        
        // Test team retrieval
        $retrieved = $storage->get_team_config( 'json-test-team' );
        $this->assert( $retrieved !== null, 'JSON team retrieval failed' );
        $this->assert( $retrieved['team_name'] === 'JSON Test Team', 'JSON team name mismatch' );
        
        // Test team listing
        $teams = $storage->get_available_teams();
        $this->assert( in_array( 'json-test-team', $teams ), 'JSON team not in listing' );
        
        // Test HR feedback
        $feedback_data = array(
            'feedback_to_person' => 'JSON feedback test',
            'feedback_to_hr' => 'JSON HR test',
            'submitted_to_hr' => 0,
            'draft_complete' => 1
        );
        
        $feedback_result = $storage->save_hr_feedback( 'json-testuser', '2025-01', $feedback_data );
        $this->assert( $feedback_result === true, 'JSON HR feedback save failed' );
        
        $retrieved_feedback = $storage->get_hr_feedback( 'json-testuser', '2025-01' );
        $this->assert( $retrieved_feedback !== null, 'JSON HR feedback retrieval failed' );
        $this->assert( $retrieved_feedback['feedback_to_person'] === 'JSON feedback test', 'JSON HR feedback mismatch' );
        
        // Clean up
        $this->cleanupDirectory( $test_dir );
        
        echo "✓\n";
    }
    
    private function testSqliteStorage() {
        echo "Testing SQLite Storage Backend... ";
        
        // Use temporary database for testing
        $test_db = __DIR__ . '/../test_data/sqlite_test.db';
        if ( file_exists( $test_db ) ) {
            unlink( $test_db );
        }
        
        $storage = new Storage( $test_db );
        
        // Test team creation
        $config = $this->getTestConfig();
        $result = $storage->save_team_config( 'sqlite-test-team', $config );
        $this->assert( $result === true, 'SQLite team creation failed' );
        
        // Test team retrieval
        $retrieved = $storage->get_team_config( 'sqlite-test-team' );
        $this->assert( $retrieved !== null, 'SQLite team retrieval failed' );
        $this->assert( $retrieved['team_name'] === 'JSON Test Team', 'SQLite team name mismatch' );
        
        // Test team listing
        $teams = $storage->get_available_teams();
        $this->assert( in_array( 'sqlite-test-team', $teams ), 'SQLite team not in listing' );
        
        // Test HR feedback
        $feedback_data = array(
            'feedback_to_person' => 'SQLite feedback test',
            'feedback_to_hr' => 'SQLite HR test',
            'submitted_to_hr' => 0,
            'draft_complete' => 1
        );
        
        $feedback_result = $storage->save_hr_feedback( 'sqlite-testuser', '2025-01', $feedback_data );
        $this->assert( $feedback_result !== false, 'SQLite HR feedback save failed' );
        
        $retrieved_feedback = $storage->get_hr_feedback( 'sqlite-testuser', '2025-01' );
        $this->assert( $retrieved_feedback !== null, 'SQLite HR feedback retrieval failed' );
        $this->assert( $retrieved_feedback['feedback_to_person'] === 'SQLite feedback test', 'SQLite HR feedback mismatch' );
        
        // Clean up
        if ( file_exists( $test_db ) ) {
            unlink( $test_db );
        }
        
        echo "✓\n";
    }
    
    private function testStorageFactory() {
        echo "Testing Storage Factory... ";
        
        // Test JSON factory creation
        $json_storage = StorageFactory::create( 'json' );
        $this->assert( $json_storage instanceof JsonStorage, 'Factory failed to create JsonStorage' );
        
        // Test SQLite factory creation
        $sqlite_storage = StorageFactory::create( 'sqlite' );
        $this->assert( $sqlite_storage instanceof Storage, 'Factory failed to create SQLite Storage' );
        
        // Test default creation (should be SQLite)
        $default_storage = StorageFactory::create();
        $this->assert( $default_storage instanceof Storage, 'Factory default failed' );
        
        // Test available types
        $types = StorageFactory::get_available_types();
        $this->assert( in_array( 'json', $types ), 'JSON not in available types' );
        $this->assert( in_array( 'sqlite', $types ), 'SQLite not in available types' );
        
        echo "✓\n";
    }
    
    private function getTestConfig() {
        return array(
            'team_name' => 'JSON Test Team',
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
    }
    
    private function cleanupDirectory( $dir ) {
        $files = glob( $dir . '*' );
        foreach ( $files as $file ) {
            if ( is_file( $file ) ) {
                unlink( $file );
            }
        }
        if ( is_dir( $dir ) ) {
            rmdir( $dir );
        }
    }
    
    private function assert( $condition, $message ) {
        if ( ! $condition ) {
            throw new Exception( "Test failed: $message" );
        }
    }
}

try {
    $test = new DualStorageTest();
    $test->run();
    echo "SUCCESS: All dual storage tests passed!\n";
    exit( 0 );
} catch ( Exception $e ) {
    echo "FAILURE: " . $e->getMessage() . "\n";
    exit( 1 );
}
?>