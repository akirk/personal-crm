<?php
/**
 * Group class to represent teams/groups with lazy loading
 */
namespace PersonalCRM;

require_once __DIR__ . '/person.php';
require_once __DIR__ . '/event.php';

if ( class_exists( '\PersonalCRM\Group' ) ) {
	return;
}

class Group implements \ArrayAccess {
	public $id;
	public $slug;
	public $group_name;
	public $type;
	public $parent_id;
	public $activity_url_prefix;
	public $display_icon;
	public $sort_order;
	public $is_default;
	public $links;

	private $storage;
	private $_members = null;
	private $_events = null;
	private $_child_groups = null;

	private static $current_group = null;

	public static function set_current( $group ) {
		self::$current_group = $group;
	}

	public static function get_current() {
		return self::$current_group;
	}

	public static function current_slug() {
		return self::$current_group ? self::$current_group->slug : null;
	}

	public function __construct( $data, $storage ) {
		$this->storage = $storage;
		$this->id = $data['id'];
		$this->slug = $data['slug'];
		$this->group_name = $data['group_name'];
		$this->type = $data['type'] ?? 'team';
		$this->parent_id = $data['parent_id'] ?? null;
		$this->activity_url_prefix = $data['activity_url_prefix'] ?? '';
		$this->display_icon = $data['display_icon'] ?? '';
		$this->sort_order = $data['sort_order'] ?? 0;
		$this->is_default = (bool) ( $data['is_default'] ?? false );
		$this->links = $data['links'] ?? array();
	}

	/**
	 * Get members of this group as Person objects
	 *
	 * @return array Array of Person objects keyed by username
	 */
	public function get_members() {
		if ( $this->_members === null ) {
			$this->_members = $this->storage->get_group_members( $this->id, false );
		}
		return $this->_members;
	}

	/**
	 * Get events for this group as Event objects
	 *
	 * @return array Array of Event objects
	 */
	public function get_events() {
		if ( $this->_events === null ) {
			$this->_events = $this->storage->get_group_events( $this->id );
		}
		return $this->_events;
	}

	/**
	 * Get child groups (subgroups)
	 *
	 * @return array Array of Group instances
	 */
	public function get_child_groups() {
		if ( $this->_child_groups === null ) {
			$children = $this->storage->get_child_groups( $this->id );
			$this->_child_groups = array();
			foreach ( $children as $child_data ) {
				$this->_child_groups[] = new Group( $child_data, $this->storage );
			}
		}
		return $this->_child_groups;
	}

	/**
	 * Get deceased members as Person objects
	 *
	 * @return array Array of deceased Person objects
	 */
	public function get_deceased() {
		$members = $this->get_members();
		return array_filter( $members, function( $member ) {
			return ! empty( $member->deceased );
		} );
	}

	// ArrayAccess implementation for backwards compatibility

	public function offsetExists( $offset ): bool {
		if ( property_exists( $this, $offset ) ) {
			return true;
		}

		switch ( $offset ) {
			case 'members':
			case 'events':
			case 'team_links':
			case 'default':
				return true;
			default:
				$child_groups = $this->get_child_groups();
				foreach ( $child_groups as $child ) {
					if ( $child->slug === $offset ) {
						return true;
					}
				}
				return false;
		}
	}

	public function offsetGet( $offset ): mixed {
		if ( property_exists( $this, $offset ) ) {
			return $this->$offset;
		}

		switch ( $offset ) {
			case 'members':
				return $this->get_members();
			case 'events':
				return $this->get_events();
			case 'team_links':
				return $this->links;
			case 'default':
				return $this->is_default;
			default:
				// Check if it's a child group slug
				$child_groups = $this->get_child_groups();
				foreach ( $child_groups as $child ) {
					if ( $child->slug === $offset ) {
						return $child->get_members();
					}
				}
				return null;
		}
	}

	public function offsetSet( $offset, $value ): void {
		// Read-only for now - modifications should go through storage methods
		throw new \BadMethodCallException( 'Group objects are read-only. Use storage methods to modify groups.' );
	}

	public function offsetUnset( $offset ): void {
		// Read-only for now
		throw new \BadMethodCallException( 'Group objects are read-only. Use storage methods to modify groups.' );
	}
}
