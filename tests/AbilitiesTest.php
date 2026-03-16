<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/storage.php';
require_once __DIR__ . '/../includes/personal-crm.php';
require_once __DIR__ . '/../includes/abilities.php';

class AbilitiesTest extends TestCase {
	private $mock_storage;

	protected function setUp(): void {
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

	private function inject_crm_instance( $storage ) {
		$mock_crm          = new stdClass();
		$mock_crm->storage = $storage;
		PersonalCRM\PersonalCrm::set_instance( $mock_crm );
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

	// -------------------------------------------------------------------------

	public function test_get_person_not_found() {
		$result = PersonalCRM\ability_get_person( array( 'username' => 'nobody' ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'person_not_found', $result->code );
	}

	public function test_get_person_basic_fields() {
		$this->mock_storage->people['jane-doe'] = $this->make_person();

		$result = PersonalCRM\ability_get_person( array( 'username' => 'jane-doe' ) );

		$this->assertSame( 'jane-doe', $result['username'] );
		$this->assertSame( 'Jane Doe', $result['name'] );
		$this->assertSame( 'https://janedoe.dev', $result['website'] );
		$this->assertArrayHasKey( 'url', $result );
		$this->assertArrayHasKey( 'edit_url', $result );
	}

	public function test_get_person_notes_are_objects() {
		$this->mock_storage->people['jane-doe'] = $this->make_person( array(
			'notes' => array(
				array( 'id' => 1, 'text' => 'First note',  'date' => '2026-01-01 10:00:00' ),
				array( 'id' => 2, 'text' => 'Second note', 'date' => '2026-01-02 10:00:00' ),
			),
		) );

		$notes = PersonalCRM\ability_get_person( array( 'username' => 'jane-doe' ) )['recent_notes'];

		$this->assertCount( 2, $notes );
		$this->assertSame( 1, $notes[0]['id'] );
		$this->assertSame( 'First note', $notes[0]['text'] );
		$this->assertSame( '2026-01-01 10:00:00', $notes[0]['date'] );
	}

	public function test_get_person_notes_default_limit() {
		$notes = array();
		for ( $i = 1; $i <= 8; $i++ ) {
			$notes[] = array( 'id' => $i, 'text' => "Note $i", 'date' => '2026-01-01 00:00:00' );
		}
		$this->mock_storage->people['jane-doe'] = $this->make_person( array( 'notes' => $notes ) );

		$result = PersonalCRM\ability_get_person( array( 'username' => 'jane-doe' ) );
		$this->assertCount( 5, $result['recent_notes'] );
	}

	public function test_get_person_notes_custom_limit() {
		$notes = array();
		for ( $i = 1; $i <= 8; $i++ ) {
			$notes[] = array( 'id' => $i, 'text' => "Note $i", 'date' => '2026-01-01 00:00:00' );
		}
		$this->mock_storage->people['jane-doe'] = $this->make_person( array( 'notes' => $notes ) );

		$result = PersonalCRM\ability_get_person( array( 'username' => 'jane-doe', 'notes_limit' => 3 ) );
		$this->assertCount( 3, $result['recent_notes'] );
	}

	public function test_get_person_notes_limit_minus_one_returns_all() {
		$notes = array();
		for ( $i = 1; $i <= 8; $i++ ) {
			$notes[] = array( 'id' => $i, 'text' => "Note $i", 'date' => '2026-01-01 00:00:00' );
		}
		$this->mock_storage->people['jane-doe'] = $this->make_person( array( 'notes' => $notes ) );

		$result = PersonalCRM\ability_get_person( array( 'username' => 'jane-doe', 'notes_limit' => -1 ) );
		$this->assertCount( 8, $result['recent_notes'] );
	}

	public function test_add_note_success() {
		global $wpdb;
		$wpdb            = new stdClass();
		$wpdb->insert_id = 0;

		$this->mock_storage->people['jane-doe'] = $this->make_person();

		$result = PersonalCRM\ability_add_note( array( 'username' => 'jane-doe', 'note' => 'Hello world' ) );

		$this->assertTrue( $result['success'] );
		$this->assertArrayHasKey( 'note_id', $result );
	}

	public function test_add_note_person_not_found() {
		$result = PersonalCRM\ability_add_note( array( 'username' => 'nobody', 'note' => 'Hi' ) );
		$this->assertInstanceOf( WP_Error::class, $result );
	}

	public function test_edit_note_success() {
		$this->mock_storage->notes[42] = 'Old text';

		$result = PersonalCRM\ability_edit_note( array( 'note_id' => 42, 'note' => 'New text' ) );

		$this->assertTrue( $result['success'] );
		$this->assertSame( 'New text', $this->mock_storage->notes[42] );
	}

	public function test_edit_note_not_found() {
		$result = PersonalCRM\ability_edit_note( array( 'note_id' => 999, 'note' => 'Whatever' ) );
		$this->assertInstanceOf( WP_Error::class, $result );
	}

	public function test_delete_note_success() {
		$this->mock_storage->notes[55] = 'To be deleted';

		$result = PersonalCRM\ability_delete_note( array( 'note_id' => 55 ) );

		$this->assertTrue( $result['success'] );
		$this->assertArrayNotHasKey( 55, $this->mock_storage->notes );
	}

	public function test_delete_note_not_found() {
		$result = PersonalCRM\ability_delete_note( array( 'note_id' => 999 ) );
		$this->assertInstanceOf( WP_Error::class, $result );
	}

	public function test_list_people_returns_all_with_expected_keys() {
		$this->mock_storage->people = array(
			'jane-doe' => $this->make_person(),
			'john-doe' => $this->make_person( array( 'username' => 'john-doe', 'name' => 'John Doe' ) ),
		);

		$result = PersonalCRM\ability_list_people();

		$this->assertCount( 2, $result );
		$this->assertArrayHasKey( 'username', $result[0] );
		$this->assertArrayHasKey( 'name', $result[0] );
		$this->assertArrayHasKey( 'groups', $result[0] );
	}
}
