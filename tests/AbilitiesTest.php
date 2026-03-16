<?php
/**
 * Tests for the Abilities API callbacks in includes/abilities.php
 */

// WordPress constants
if ( ! defined( 'ARRAY_A' ) ) define( 'ARRAY_A', 'ARRAY_A' );
if ( ! defined( 'OBJECT' ) )  define( 'OBJECT', 'OBJECT' );
if ( ! defined( 'ABSPATH' ) ) define( 'ABSPATH', __DIR__ . '/../' );

// WordPress function stubs
if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type = 'mysql' ) { return date( 'Y-m-d H:i:s' ); }
}
if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) { return 'https://example.com' . $path; }
}
if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $cap ) { return true; }
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) { return trim( $str ); }
}
if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $str ) { return trim( $str ); }
}
if ( ! function_exists( 'sanitize_email' ) ) {
	function sanitize_email( $email ) { return $email; }
}
if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( $title ) { return strtolower( preg_replace( '/[^a-z0-9-]/i', '-', $title ) ); }
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data ) { return json_encode( $data ); }
}
if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = '' ) { return $text; }
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback ) {}
}
if ( ! function_exists( 'wp_register_ability_category' ) ) {
	function wp_register_ability_category( $slug, $args ) {}
}
if ( ! function_exists( 'wp_register_ability' ) ) {
	function wp_register_ability( $slug, $args ) {}
}

// WP_Error stub
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public $code;
		public $message;
		public function __construct( $code, $message ) {
			$this->code    = $code;
			$this->message = $message;
		}
	}
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/storage.php';
require_once __DIR__ . '/../includes/personal-crm.php';
require_once __DIR__ . '/../includes/abilities.php';

class AbilitiesTest {
	private $mock_storage;

	public function __construct() {
		$this->mock_storage = $this->create_mock_storage();
		$this->inject_crm_instance( $this->mock_storage );
	}

	private function create_mock_storage() {
		return new class {
			public $people       = array();
			public $notes        = array();
			public $next_note_id = 10;

			public function get_person( $username ) {
				return $this->people[ $username ] ?? null;
			}

			public function get_person_id( $username ) {
				return isset( $this->people[ $username ] ) ? 1 : null;
			}

			public function get_all_people() {
				return array_values( $this->people );
			}

			public function add_person_note( $person_id, $note_text ) {
				global $wpdb;
				$id              = $this->next_note_id++;
				$wpdb->insert_id = $id;
				return $id;
			}

			public function update_person_note( $note_id, $note_text ) {
				if ( isset( $this->notes[ $note_id ] ) ) {
					$this->notes[ $note_id ] = $note_text;
					return 1;
				}
				return false;
			}

			public function delete_person_note( $note_id ) {
				if ( isset( $this->notes[ $note_id ] ) ) {
					unset( $this->notes[ $note_id ] );
					return 1;
				}
				return false;
			}
		};
	}

	/**
	 * Inject a mock PersonalCrm instance via reflection so the singleton
	 * returns it without running the real constructor.
	 */
	private function inject_crm_instance( $storage ) {
		$mock_crm          = new stdClass();
		$mock_crm->storage = $storage;

		$ref      = new ReflectionClass( PersonalCRM\PersonalCrm::class );
		$instance = $ref->getProperty( 'instance' );
		$instance->setValue( null, $mock_crm );
	}

	private function make_person( array $overrides = array() ) {
		$defaults = (object) array(
			'username'        => 'jane-doe',
			'name'            => 'Jane Doe',
			'email'           => 'jane@example.com',
			'role'            => 'Engineer',
			'location'        => 'Vienna',
			'birthday'        => '1990-05-01',
			'github'          => 'janedoe',
			'linkedin'        => 'jane-doe',
			'wordpress'       => 'janedoe',
			'website'         => 'https://janedoe.dev',
			'groups'          => array(),
			'notes'           => array(),
			'personal_events' => array(),
		);
		foreach ( $overrides as $k => $v ) {
			$defaults->$k = $v;
		}
		return $defaults;
	}

	private function assert( $condition, $message ) {
		if ( ! $condition ) {
			throw new Exception( "FAIL: $message" );
		}
	}

	// -------------------------------------------------------------------------

	private function test_get_person_not_found() {
		echo "  get-person: unknown username returns WP_Error... ";
		$result = PersonalCRM\ability_get_person( array( 'username' => 'nobody' ) );
		$this->assert( $result instanceof WP_Error, 'should return WP_Error' );
		$this->assert( $result->code === 'person_not_found', 'wrong error code' );
		echo "✓\n";
	}

	private function test_get_person_basic_fields() {
		echo "  get-person: basic fields including website... ";
		$this->mock_storage->people['jane-doe'] = $this->make_person();

		$result = PersonalCRM\ability_get_person( array( 'username' => 'jane-doe' ) );

		$this->assert( $result['username'] === 'jane-doe', 'username mismatch' );
		$this->assert( $result['name'] === 'Jane Doe', 'name mismatch' );
		$this->assert( $result['website'] === 'https://janedoe.dev', 'website missing or wrong' );
		$this->assert( isset( $result['url'] ), 'url missing' );
		$this->assert( isset( $result['edit_url'] ), 'edit_url missing' );
		echo "✓\n";
	}

	private function test_get_person_notes_are_objects() {
		echo "  get-person: recent_notes returns objects with id/text/date... ";
		$this->mock_storage->people['jane-doe'] = $this->make_person( array(
			'notes' => array(
				array( 'id' => 1, 'text' => 'First note',  'date' => '2026-01-01 10:00:00' ),
				array( 'id' => 2, 'text' => 'Second note', 'date' => '2026-01-02 10:00:00' ),
			),
		) );

		$result = PersonalCRM\ability_get_person( array( 'username' => 'jane-doe' ) );
		$notes  = $result['recent_notes'];

		$this->assert( count( $notes ) === 2, 'wrong note count' );
		$this->assert( $notes[0]['id'] === 1, 'note id wrong' );
		$this->assert( $notes[0]['text'] === 'First note', 'note text wrong' );
		$this->assert( $notes[0]['date'] === '2026-01-01 10:00:00', 'note date wrong' );
		echo "✓\n";
	}

	private function test_get_person_notes_limit() {
		echo "  get-person: notes_limit caps returned notes... ";
		$notes = array();
		for ( $i = 1; $i <= 8; $i++ ) {
			$notes[] = array( 'id' => $i, 'text' => "Note $i", 'date' => '2026-01-01 00:00:00' );
		}
		$this->mock_storage->people['jane-doe'] = $this->make_person( array( 'notes' => $notes ) );

		$default = PersonalCRM\ability_get_person( array( 'username' => 'jane-doe' ) );
		$this->assert( count( $default['recent_notes'] ) === 5, 'default limit should be 5' );

		$limited = PersonalCRM\ability_get_person( array( 'username' => 'jane-doe', 'notes_limit' => 3 ) );
		$this->assert( count( $limited['recent_notes'] ) === 3, 'notes_limit=3 should return 3' );

		$all = PersonalCRM\ability_get_person( array( 'username' => 'jane-doe', 'notes_limit' => -1 ) );
		$this->assert( count( $all['recent_notes'] ) === 8, 'notes_limit=-1 should return all' );
		echo "✓\n";
	}

	private function test_add_note_success() {
		echo "  add-note: success returns note_id... ";
		global $wpdb;
		$wpdb            = new stdClass();
		$wpdb->insert_id = 0;

		$this->mock_storage->people['jane-doe'] = $this->make_person();

		$result = PersonalCRM\ability_add_note( array( 'username' => 'jane-doe', 'note' => 'Hello world' ) );

		$this->assert( $result['success'] === true, 'success should be true' );
		$this->assert( isset( $result['note_id'] ), 'note_id should be set' );
		echo "✓\n";
	}

	private function test_add_note_person_not_found() {
		echo "  add-note: unknown username returns WP_Error... ";
		$result = PersonalCRM\ability_add_note( array( 'username' => 'nobody', 'note' => 'Hi' ) );
		$this->assert( $result instanceof WP_Error, 'should return WP_Error' );
		echo "✓\n";
	}

	private function test_edit_note_success() {
		echo "  edit-note: updates existing note... ";
		$this->mock_storage->notes[42] = 'Old text';

		$result = PersonalCRM\ability_edit_note( array( 'note_id' => 42, 'note' => 'New text' ) );
		$this->assert( $result['success'] === true, 'success should be true' );
		$this->assert( $this->mock_storage->notes[42] === 'New text', 'note text not updated' );
		echo "✓\n";
	}

	private function test_edit_note_not_found() {
		echo "  edit-note: missing note_id returns WP_Error... ";
		$result = PersonalCRM\ability_edit_note( array( 'note_id' => 999, 'note' => 'Whatever' ) );
		$this->assert( $result instanceof WP_Error, 'should return WP_Error' );
		echo "✓\n";
	}

	private function test_delete_note_success() {
		echo "  delete-note: removes existing note... ";
		$this->mock_storage->notes[55] = 'To be deleted';

		$result = PersonalCRM\ability_delete_note( array( 'note_id' => 55 ) );
		$this->assert( $result['success'] === true, 'success should be true' );
		$this->assert( ! isset( $this->mock_storage->notes[55] ), 'note should be gone' );
		echo "✓\n";
	}

	private function test_delete_note_not_found() {
		echo "  delete-note: missing note_id returns WP_Error... ";
		$result = PersonalCRM\ability_delete_note( array( 'note_id' => 999 ) );
		$this->assert( $result instanceof WP_Error, 'should return WP_Error' );
		echo "✓\n";
	}

	private function test_list_people() {
		echo "  list-people: returns all people with expected keys... ";
		$this->mock_storage->people = array(
			'jane-doe' => $this->make_person(),
			'john-doe' => $this->make_person( array( 'username' => 'john-doe', 'name' => 'John Doe' ) ),
		);

		$result = PersonalCRM\ability_list_people();
		$this->assert( count( $result ) === 2, 'should return 2 people' );
		$this->assert( isset( $result[0]['username'] ), 'username key missing' );
		$this->assert( isset( $result[0]['name'] ), 'name key missing' );
		$this->assert( isset( $result[0]['groups'] ), 'groups key missing' );
		echo "✓\n";
	}

	// -------------------------------------------------------------------------

	public function run() {
		echo "Running Abilities Tests...\n\n";

		$this->test_get_person_not_found();
		$this->test_get_person_basic_fields();
		$this->test_get_person_notes_are_objects();
		$this->test_get_person_notes_limit();
		$this->test_add_note_success();
		$this->test_add_note_person_not_found();
		$this->test_edit_note_success();
		$this->test_edit_note_not_found();
		$this->test_delete_note_success();
		$this->test_delete_note_not_found();
		$this->test_list_people();

		echo "\nAll abilities tests passed! ✓\n";
	}
}

try {
	$test = new AbilitiesTest();
	$test->run();
	exit( 0 );
} catch ( Exception $e ) {
	echo $e->getMessage() . "\n";
	exit( 1 );
}
