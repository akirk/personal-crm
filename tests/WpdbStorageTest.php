<?php
/**
 * WpDB Storage Test
 *
 * Tests the WpdbStorage implementation
 */

// Define WordPress constants if not available
if ( ! defined( 'ARRAY_A' ) ) {
    define( 'ARRAY_A', 'ARRAY_A' );
}
if ( ! defined( 'OBJECT' ) ) {
    define( 'OBJECT', 'OBJECT' );
}
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/../' );
}

// Mock WordPress functions if not available
if ( ! function_exists( 'current_time' ) ) {
    function current_time( $type = 'mysql' ) {
        return date( 'Y-m-d H:i:s' );
    }
}

if ( ! function_exists( 'wp_json_encode' ) ) {
    function wp_json_encode( $data ) {
        return json_encode( $data );
    }
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/storage.php';

class WpdbStorageTest {
    private $storage;
    private $mock_wpdb;

    public function __construct() {
        $this->mock_wpdb = $this->create_mock_wpdb();
        $this->storage = new PersonalCRM\Storage( $this->mock_wpdb );
    }

    /**
     * Create a mock wpdb instance for testing
     */
    private function create_mock_wpdb() {
        return new class {
            public $prefix = 'wp_';
            public $insert_id = 1;

            private $data = array();
            private $last_query = '';

            public function get_charset_collate() {
                return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci';
            }

            public function prepare( $query, ...$args ) {
                return vsprintf( str_replace( '%s', "'%s'", $query ), $args );
            }

            public function get_var( $query ) {
                $this->last_query = $query;

                // Mock responses for specific queries
                if ( strpos( $query, 'COUNT(*)' ) !== false ) {
                    return '1';
                }
                if ( strpos( $query, 'SELECT slug' ) !== false ) {
                    return 'test-team';
                }
                if ( strpos( $query, 'SELECT team_name' ) !== false ) {
                    return 'Test Team';
                }
                if ( strpos( $query, 'SELECT type' ) !== false ) {
                    return 'team';
                }

                return null;
            }

            public function get_row( $query, $output_type = OBJECT ) {
                $this->last_query = $query;

                if ( strpos( $query, 'teams' ) !== false ) {
                    $data = array(
                        'slug' => 'test-team',
                        'team_name' => 'Test Team',
                        'activity_url_prefix' => '',
                        'not_managing_team' => 1,
                        'type' => 'team',
                        'is_default' => 0
                    );

                    return $output_type === ARRAY_A ? $data : (object) $data;
                }

                return null;
            }

            public function get_results( $query, $output_type = OBJECT ) {
                $this->last_query = $query;
                return array();
            }

            public function get_col( $query ) {
                $this->last_query = $query;
                return array( 'test-team' );
            }

            public function insert( $table, $data, $format = null ) {
                $this->data[$table][] = $data;
                return true;
            }

            public function update( $table, $data, $where, $format = null, $where_format = null ) {
                return true;
            }

            public function delete( $table, $where, $where_format = null ) {
                return true;
            }

            public function query( $query ) {
                $this->last_query = $query;
                return true;
            }

            public function get_last_query() {
                return $this->last_query;
            }
        };
    }

    /**
     * Test basic team operations
     */
    public function test_team_operations() {
        echo "Testing team operations...\n";

        // Test team exists
        $exists = $this->storage->team_exists( 'test-team' );
        assert( $exists === true, 'Team should exist' );

        // Test get team name
        $name = $this->storage->get_team_name( 'test-team' );
        assert( $name === 'Test Team', 'Team name should be "Test Team"' );

        // Test get team type
        $type = $this->storage->get_team_type( 'test-team' );
        assert( $type === 'team', 'Team type should be "team"' );

        // Test get available teams
        $teams = $this->storage->get_available_groups();
        assert( is_array( $teams ), 'Available teams should return an array' );

        echo "✓ Team operations tests passed\n";
    }

    /**
     * Test team configuration
     */
    public function test_team_config() {
        echo "Testing team configuration...\n";

        $test_config = array(
            'team_name' => 'Test Team',
            'activity_url_prefix' => 'https://example.com',
            'not_managing_team' => 0,
            'type' => 'team',
            'default' => false,
            'team_links' => array(
                'Website' => 'https://example.com'
            ),
            'team_members' => array(
                'testuser' => array(
                    'name' => 'Test User',
                    'email' => 'test@example.com',
                    'role' => 'Developer',
                    'kids' => array(),
                    'github_repos' => array(),
                    'personal_events' => array(),
                    'notes' => array(),
                    'links' => array()
                )
            ),
            'leadership' => array(),
            'consultants' => array(),
            'alumni' => array(),
            'events' => array(
                array(
                    'type' => 'meeting',
                    'name' => 'Team Meeting',
                    'description' => 'Weekly team sync',
                    'start_date' => '2024-01-01',
                    'end_date' => '2024-01-01',
                    'location' => 'Online',
                    'links' => array()
                )
            )
        );

        // Test save team config
        $result = $this->storage->save_group( 'test-team', $test_config );
        assert( $result === true, 'Save team config should return true' );

        // Test get team config
        $config = $this->storage->get_group( 'test-team' );
        assert( $config !== null, 'Team config should not be null' );
        assert( isset( $config['team_name'] ), 'Config should have team_name' );

        echo "✓ Team configuration tests passed\n";
    }

    /**
     * Test HR feedback operations
     */
    public function test_hr_feedback() {
        echo "Testing HR feedback operations...\n";

        $feedback_data = array(
            'feedback_to_person' => 'Great work this month!',
            'feedback_to_hr' => 'Employee is performing well',
            'submitted_to_hr' => 1,
            'draft_complete' => 1,
            'google_doc_updated' => 0,
            'not_necessary_reason' => ''
        );

        // Test save HR feedback
        $result = $this->storage->save_hr_feedback( 'testuser', '2024-01', $feedback_data );
        assert( $result !== false, 'Save HR feedback should succeed' );

        // Test get HR feedback
        $feedback = $this->storage->get_hr_feedback( 'testuser', '2024-01' );
        // Note: This will return null with our mock, but the method should not throw errors
        assert( $feedback === null, 'Get HR feedback should return null with mock data' );

        echo "✓ HR feedback tests passed\n";
    }

    /**
     * Test people count and names methods
     */
    public function test_people_methods() {
        echo "Testing people methods...\n";

        // Test get team people count
        $count = $this->storage->get_team_people_count( 'test-team' );
        assert( is_int( $count ), 'People count should be an integer' );

        // Test get team people names
        $names = $this->storage->get_team_people_names( 'test-team' );
        assert( is_array( $names ), 'People names should return an array' );

        // Test get team people data
        $people_data = $this->storage->get_team_people_data( 'test-team' );
        assert( is_array( $people_data ), 'People data should return an array' );

        echo "✓ People methods tests passed\n";
    }

    /**
     * Run all tests
     */
    public function run_tests() {
        echo "Running WpDB Storage Tests...\n\n";

        try {
            $this->test_team_operations();
            $this->test_team_config();
            $this->test_hr_feedback();
            $this->test_people_methods();

            echo "\n✅ All WpDB Storage tests passed!\n";
            return true;
        } catch ( Exception $e ) {
            echo "\n❌ Test failed: " . $e->getMessage() . "\n";
            return false;
        } catch ( AssertionError $e ) {
            echo "\n❌ Assertion failed: " . $e->getMessage() . "\n";
            return false;
        }
    }
}


// Run tests if called directly
if ( basename( __FILE__ ) === basename( $_SERVER['SCRIPT_NAME'] ) ) {
    $test = new WpdbStorageTest();
    $success = $test->run_tests();
    exit( $success ? 0 : 1 );
}