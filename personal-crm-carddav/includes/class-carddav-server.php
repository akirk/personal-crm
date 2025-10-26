<?php
/**
 * CardDAV Server Class
 *
 * Implements the CardDAV protocol for serving contacts
 *
 * @package Personal_CRM_CardDAV
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Personal_CRM_CardDAV_Server
 *
 * Handles CardDAV protocol requests
 */
class Personal_CRM_CardDAV_Server {

	/**
	 * The storage instance from Personal CRM
	 *
	 * @var object
	 */
	private $storage;

	/**
	 * The base URL for CardDAV
	 *
	 * @var string
	 */
	private $base_url;

	/**
	 * The current address book
	 *
	 * @var string
	 */
	private $current_addressbook;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->base_url = home_url( '/carddav' );
	}

	/**
	 * Set the storage instance
	 *
	 * @param object $storage The storage instance
	 */
	public function set_storage( $storage ) {
		$this->storage = $storage;
	}

	/**
	 * Handle CardDAV request
	 *
	 * @param string $path The request path
	 */
	public function handle_request( $path = '' ) {
		// Require authentication
		Personal_CRM_CardDAV_Auth::require_authentication();

		$method = $_SERVER['REQUEST_METHOD'];

		// Parse the path to determine the resource
		$path_parts = array_filter( explode( '/', $path ) );

		// Determine what's being requested
		$resource_type = $this->determine_resource_type( $path_parts );

		// Route to appropriate handler
		switch ( $method ) {
			case 'OPTIONS':
				$this->handle_options();
				break;

			case 'PROPFIND':
				$this->handle_propfind( $resource_type, $path_parts );
				break;

			case 'REPORT':
				$this->handle_report( $resource_type, $path_parts );
				break;

			case 'GET':
				$this->handle_get( $resource_type, $path_parts );
				break;

			case 'PUT':
				$this->handle_put( $resource_type, $path_parts );
				break;

			case 'DELETE':
				$this->handle_delete( $resource_type, $path_parts );
				break;

			case 'MKCOL':
				// Address book creation - not supported in this implementation
				$this->send_response( 405, 'Method Not Allowed' );
				break;

			default:
				$this->send_response( 501, 'Not Implemented' );
				break;
		}
	}

	/**
	 * Determine the type of resource being requested
	 *
	 * @param array $path_parts The path parts
	 * @return string The resource type: 'root', 'addressbook', 'contact'
	 */
	private function determine_resource_type( $path_parts ) {
		if ( empty( $path_parts ) ) {
			return 'root';
		}

		// First part is the address book (group slug)
		if ( count( $path_parts ) === 1 ) {
			$this->current_addressbook = $path_parts[0];
			return 'addressbook';
		}

		// Second part is the contact (username.vcf)
		if ( count( $path_parts ) >= 2 ) {
			$this->current_addressbook = $path_parts[0];
			return 'contact';
		}

		return 'unknown';
	}

	/**
	 * Handle OPTIONS request
	 */
	private function handle_options() {
		header( 'Allow: OPTIONS, PROPFIND, REPORT, GET, PUT, DELETE' );
		header( 'DAV: 1, 2, 3, addressbook' );
		header( 'DAV: extended-mkcol' );
		$this->send_response( 200, 'OK' );
	}

	/**
	 * Handle PROPFIND request
	 *
	 * @param string $resource_type The resource type
	 * @param array  $path_parts    The path parts
	 */
	private function handle_propfind( $resource_type, $path_parts ) {
		// Parse the request body to get requested properties
		$input = file_get_contents( 'php://input' );
		$depth = isset( $_SERVER['HTTP_DEPTH'] ) ? $_SERVER['HTTP_DEPTH'] : '0';

		// Parse XML request
		$requested_props = $this->parse_propfind_request( $input );

		$xml = new SimpleXMLElement( '<?xml version="1.0" encoding="utf-8"?><d:multistatus xmlns:d="DAV:" xmlns:card="urn:ietf:params:xml:ns:carddav" xmlns:cs="http://calendarserver.org/ns/"></d:multistatus>' );

		switch ( $resource_type ) {
			case 'root':
				$this->propfind_root( $xml, $requested_props, $depth );
				break;

			case 'addressbook':
				$this->propfind_addressbook( $xml, $requested_props, $depth, $this->current_addressbook );
				break;

			case 'contact':
				$username = $this->extract_username( $path_parts );
				$this->propfind_contact( $xml, $requested_props, $this->current_addressbook, $username );
				break;
		}

		$this->send_xml_response( $xml, 207 );
	}

	/**
	 * Handle PROPFIND for root (list address books)
	 *
	 * @param SimpleXMLElement $xml             The XML response
	 * @param array            $requested_props Requested properties
	 * @param string           $depth           Depth header value
	 */
	private function propfind_root( $xml, $requested_props, $depth ) {
		// Add root collection properties
		$response = $xml->addChild( 'response', null, 'DAV:' );
		$response->addChild( 'href', $this->base_url . '/', 'DAV:' );

		$propstat = $response->addChild( 'propstat', null, 'DAV:' );
		$prop = $propstat->addChild( 'prop', null, 'DAV:' );

		$prop->addChild( 'displayname', 'Personal CRM', 'DAV:' );
		$prop->addChild( 'resourcetype', null, 'DAV:' )->addChild( 'collection', null, 'DAV:' );

		$propstat->addChild( 'status', 'HTTP/1.1 200 OK', 'DAV:' );

		// If depth is 1, list address books
		if ( $depth === '1' ) {
			$groups = $this->storage->get_available_groups();

			foreach ( $groups as $group ) {
				$this->add_addressbook_response( $xml, $group, $requested_props );
			}
		}
	}

	/**
	 * Handle PROPFIND for an address book
	 *
	 * @param SimpleXMLElement $xml             The XML response
	 * @param array            $requested_props Requested properties
	 * @param string           $depth           Depth header value
	 * @param string           $group_slug      The group slug
	 */
	private function propfind_addressbook( $xml, $requested_props, $depth, $group_slug ) {
		$group = $this->storage->get_group( $group_slug );

		if ( ! $group ) {
			$this->send_response( 404, 'Not Found' );
			return;
		}

		// Add address book properties
		$this->add_addressbook_response( $xml, $group, $requested_props );

		// If depth is 1, list contacts
		if ( $depth === '1' ) {
			$members = $this->storage->get_group_members( $group->id, true );

			foreach ( $members as $person ) {
				$this->add_contact_response( $xml, $group_slug, $person, $requested_props );
			}
		}
	}

	/**
	 * Handle PROPFIND for a specific contact
	 *
	 * @param SimpleXMLElement $xml             The XML response
	 * @param array            $requested_props Requested properties
	 * @param string           $group_slug      The group slug
	 * @param string           $username        The username
	 */
	private function propfind_contact( $xml, $requested_props, $group_slug, $username ) {
		$person = $this->storage->get_person( $username );

		if ( ! $person ) {
			$this->send_response( 404, 'Not Found' );
			return;
		}

		$this->add_contact_response( $xml, $group_slug, $person, $requested_props );
	}

	/**
	 * Add an address book response to the XML
	 *
	 * @param SimpleXMLElement $xml             The XML response
	 * @param object           $group           The group object
	 * @param array            $requested_props Requested properties
	 */
	private function add_addressbook_response( $xml, $group, $requested_props ) {
		$response = $xml->addChild( 'response', null, 'DAV:' );
		$href = $this->base_url . '/' . $group->slug . '/';
		$response->addChild( 'href', $href, 'DAV:' );

		$propstat = $response->addChild( 'propstat', null, 'DAV:' );
		$prop = $propstat->addChild( 'prop', null, 'DAV:' );

		// Add requested properties
		$prop->addChild( 'displayname', $group->group_name, 'DAV:' );

		$resourcetype = $prop->addChild( 'resourcetype', null, 'DAV:' );
		$resourcetype->addChild( 'collection', null, 'DAV:' );
		$resourcetype->addChild( 'addressbook', null, 'urn:ietf:params:xml:ns:carddav' );

		// Add CardDAV specific properties
		$prop->addChild( 'getcontenttype', 'text/vcard; charset=utf-8', 'DAV:' );

		$supported_report_set = $prop->addChild( 'supported-report-set', null, 'DAV:' );
		$supported_report = $supported_report_set->addChild( 'supported-report', null, 'DAV:' );
		$supported_report->addChild( 'report', null, 'DAV:' )->addChild( 'addressbook-multiget', null, 'urn:ietf:params:xml:ns:carddav' );

		$supported_report = $supported_report_set->addChild( 'supported-report', null, 'DAV:' );
		$supported_report->addChild( 'report', null, 'DAV:' )->addChild( 'addressbook-query', null, 'urn:ietf:params:xml:ns:carddav' );

		// Add sync token support
		$prop->addChild( 'sync-token', $this->get_sync_token( $group->slug ), 'DAV:' );

		$propstat->addChild( 'status', 'HTTP/1.1 200 OK', 'DAV:' );
	}

	/**
	 * Add a contact response to the XML
	 *
	 * @param SimpleXMLElement $xml             The XML response
	 * @param string           $group_slug      The group slug
	 * @param object           $person          The person object
	 * @param array            $requested_props Requested properties
	 */
	private function add_contact_response( $xml, $group_slug, $person, $requested_props ) {
		$response = $xml->addChild( 'response', null, 'DAV:' );
		$href = $this->base_url . '/' . $group_slug . '/' . $person->username . '.vcf';
		$response->addChild( 'href', $href, 'DAV:' );

		$propstat = $response->addChild( 'propstat', null, 'DAV:' );
		$prop = $propstat->addChild( 'prop', null, 'DAV:' );

		// Add requested properties
		if ( in_array( 'getetag', $requested_props, true ) || empty( $requested_props ) ) {
			$etag = Personal_CRM_VCard_Converter::generate_etag( $person );
			$prop->addChild( 'getetag', $etag, 'DAV:' );
		}

		if ( in_array( 'getcontenttype', $requested_props, true ) || empty( $requested_props ) ) {
			$prop->addChild( 'getcontenttype', 'text/vcard; charset=utf-8', 'DAV:' );
		}

		if ( in_array( 'address-data', $requested_props, true ) ) {
			$vcard = Personal_CRM_VCard_Converter::person_to_vcard( $person );
			$prop->addChild( 'address-data', htmlspecialchars( $vcard ), 'urn:ietf:params:xml:ns:carddav' );
		}

		$resourcetype = $prop->addChild( 'resourcetype', null, 'DAV:' );

		$propstat->addChild( 'status', 'HTTP/1.1 200 OK', 'DAV:' );
	}

	/**
	 * Handle REPORT request
	 *
	 * @param string $resource_type The resource type
	 * @param array  $path_parts    The path parts
	 */
	private function handle_report( $resource_type, $path_parts ) {
		$input = file_get_contents( 'php://input' );

		// Parse the report request
		$xml_request = simplexml_load_string( $input );

		if ( ! $xml_request ) {
			$this->send_response( 400, 'Bad Request' );
			return;
		}

		// Determine report type
		$report_type = $xml_request->getName();

		$xml = new SimpleXMLElement( '<?xml version="1.0" encoding="utf-8"?><d:multistatus xmlns:d="DAV:" xmlns:card="urn:ietf:params:xml:ns:carddav"></d:multistatus>' );

		switch ( $report_type ) {
			case 'addressbook-multiget':
				$this->handle_addressbook_multiget( $xml, $xml_request, $path_parts );
				break;

			case 'addressbook-query':
				$this->handle_addressbook_query( $xml, $xml_request, $path_parts );
				break;

			case 'sync-collection':
				$this->handle_sync_collection( $xml, $xml_request, $path_parts );
				break;

			default:
				$this->send_response( 501, 'Not Implemented' );
				return;
		}

		$this->send_xml_response( $xml, 207 );
	}

	/**
	 * Handle addressbook-multiget REPORT
	 *
	 * @param SimpleXMLElement $xml         The XML response
	 * @param SimpleXMLElement $xml_request The request XML
	 * @param array            $path_parts  The path parts
	 */
	private function handle_addressbook_multiget( $xml, $xml_request, $path_parts ) {
		$group_slug = $this->current_addressbook;

		// Get requested properties
		$requested_props = array( 'address-data', 'getetag' );

		// Get requested hrefs
		$namespaces = $xml_request->getNamespaces( true );
		$xml_request->registerXPathNamespace( 'd', 'DAV:' );
		$hrefs = $xml_request->xpath( '//d:href' );

		foreach ( $hrefs as $href_element ) {
			$href = (string) $href_element;
			$username = $this->extract_username_from_href( $href );

			if ( $username ) {
				$person = $this->storage->get_person( $username );
				if ( $person ) {
					$this->add_contact_response( $xml, $group_slug, $person, $requested_props );
				}
			}
		}
	}

	/**
	 * Handle addressbook-query REPORT
	 *
	 * @param SimpleXMLElement $xml         The XML response
	 * @param SimpleXMLElement $xml_request The request XML
	 * @param array            $path_parts  The path parts
	 */
	private function handle_addressbook_query( $xml, $xml_request, $path_parts ) {
		$group_slug = $this->current_addressbook;
		$group = $this->storage->get_group( $group_slug );

		if ( ! $group ) {
			return;
		}

		// Get requested properties
		$requested_props = array( 'address-data', 'getetag' );

		// Get all members (simplified - in a full implementation, would parse filters)
		$members = $this->storage->get_group_members( $group->id, true );

		foreach ( $members as $person ) {
			$this->add_contact_response( $xml, $group_slug, $person, $requested_props );
		}
	}

	/**
	 * Handle sync-collection REPORT
	 *
	 * @param SimpleXMLElement $xml         The XML response
	 * @param SimpleXMLElement $xml_request The request XML
	 * @param array            $path_parts  The path parts
	 */
	private function handle_sync_collection( $xml, $xml_request, $path_parts ) {
		// Simplified sync implementation - returns all contacts
		// In a full implementation, would track changes and return only modified contacts
		$this->handle_addressbook_query( $xml, $xml_request, $path_parts );

		// Add sync-token to response
		$sync_token = $xml->addChild( 'sync-token', $this->get_sync_token( $this->current_addressbook ), 'DAV:' );
	}

	/**
	 * Handle GET request
	 *
	 * @param string $resource_type The resource type
	 * @param array  $path_parts    The path parts
	 */
	private function handle_get( $resource_type, $path_parts ) {
		if ( $resource_type !== 'contact' ) {
			$this->send_response( 404, 'Not Found' );
			return;
		}

		$username = $this->extract_username( $path_parts );
		$person = $this->storage->get_person( $username );

		if ( ! $person ) {
			$this->send_response( 404, 'Not Found' );
			return;
		}

		$vcard = Personal_CRM_VCard_Converter::person_to_vcard( $person );
		$etag = Personal_CRM_VCard_Converter::generate_etag( $person );

		header( 'Content-Type: text/vcard; charset=utf-8' );
		header( 'ETag: ' . $etag );
		echo $vcard;
		exit;
	}

	/**
	 * Handle PUT request (create/update contact)
	 *
	 * @param string $resource_type The resource type
	 * @param array  $path_parts    The path parts
	 */
	private function handle_put( $resource_type, $path_parts ) {
		if ( $resource_type !== 'contact' ) {
			$this->send_response( 405, 'Method Not Allowed' );
			return;
		}

		$username = $this->extract_username( $path_parts );
		$vcard_data = file_get_contents( 'php://input' );

		// Convert vCard to person data
		$person_data = Personal_CRM_VCard_Converter::vcard_to_person_data( $vcard_data );

		// Ensure username matches
		if ( empty( $person_data['username'] ) ) {
			$person_data['username'] = $username;
		}

		// Get the group ID
		$group = $this->storage->get_group( $this->current_addressbook );
		if ( ! $group ) {
			$this->send_response( 404, 'Address book not found' );
			return;
		}

		// Check if person exists
		$existing_person = $this->storage->get_person( $username );
		$status_code = $existing_person ? 204 : 201;

		// Save the person
		$this->storage->save_person( $username, $person_data, array( $group->id ) );

		/**
		 * Action fired after a contact is saved via CardDAV
		 *
		 * @param string $username    The username
		 * @param array  $person_data The person data
		 * @param string $group_slug  The group slug
		 */
		do_action( 'personal_crm_carddav_contact_saved', $username, $person_data, $this->current_addressbook );

		// Get the updated person for ETag
		$person = $this->storage->get_person( $username );
		$etag = Personal_CRM_VCard_Converter::generate_etag( $person );

		header( 'ETag: ' . $etag );
		$this->send_response( $status_code, $status_code === 201 ? 'Created' : 'No Content' );
	}

	/**
	 * Handle DELETE request
	 *
	 * @param string $resource_type The resource type
	 * @param array  $path_parts    The path parts
	 */
	private function handle_delete( $resource_type, $path_parts ) {
		if ( $resource_type !== 'contact' ) {
			$this->send_response( 405, 'Method Not Allowed' );
			return;
		}

		$username = $this->extract_username( $path_parts );
		$person = $this->storage->get_person( $username );

		if ( ! $person ) {
			$this->send_response( 404, 'Not Found' );
			return;
		}

		// Delete the person
		$this->storage->delete_person( $username );

		/**
		 * Action fired after a contact is deleted via CardDAV
		 *
		 * @param string $username   The username
		 * @param string $group_slug The group slug
		 */
		do_action( 'personal_crm_carddav_contact_deleted', $username, $this->current_addressbook );

		$this->send_response( 204, 'No Content' );
	}

	/**
	 * Parse PROPFIND request XML
	 *
	 * @param string $xml_string The XML string
	 * @return array Requested properties
	 */
	private function parse_propfind_request( $xml_string ) {
		if ( empty( $xml_string ) ) {
			return array();
		}

		$xml = simplexml_load_string( $xml_string );
		if ( ! $xml ) {
			return array();
		}

		$namespaces = $xml->getNamespaces( true );
		$props = array();

		// Register namespaces
		$xml->registerXPathNamespace( 'd', 'DAV:' );
		$xml->registerXPathNamespace( 'card', 'urn:ietf:params:xml:ns:carddav' );

		// Check for allprop
		$allprop = $xml->xpath( '//d:allprop' );
		if ( ! empty( $allprop ) ) {
			return array(); // Empty array means all props
		}

		// Get specific props
		$prop_elements = $xml->xpath( '//d:prop/*' );
		foreach ( $prop_elements as $prop ) {
			$props[] = $prop->getName();
		}

		return $props;
	}

	/**
	 * Extract username from path parts
	 *
	 * @param array $path_parts The path parts
	 * @return string|null The username or null
	 */
	private function extract_username( $path_parts ) {
		if ( count( $path_parts ) < 2 ) {
			return null;
		}

		$filename = $path_parts[ count( $path_parts ) - 1 ];
		return str_replace( '.vcf', '', $filename );
	}

	/**
	 * Extract username from href
	 *
	 * @param string $href The href string
	 * @return string|null The username or null
	 */
	private function extract_username_from_href( $href ) {
		// Extract filename from href
		$parts = explode( '/', trim( $href, '/' ) );
		$filename = end( $parts );

		return str_replace( '.vcf', '', $filename );
	}

	/**
	 * Get sync token for an address book
	 *
	 * @param string $group_slug The group slug
	 * @return string The sync token
	 */
	private function get_sync_token( $group_slug ) {
		// Simple implementation using current timestamp
		// In a full implementation, would track actual changes
		return 'http://example.com/ns/sync/' . time();
	}

	/**
	 * Send XML response
	 *
	 * @param SimpleXMLElement $xml         The XML element
	 * @param int              $status_code The HTTP status code
	 */
	private function send_xml_response( $xml, $status_code = 200 ) {
		$status_text = $this->get_status_text( $status_code );

		header( "HTTP/1.1 {$status_code} {$status_text}" );
		header( 'Content-Type: application/xml; charset=utf-8' );

		echo $xml->asXML();
		exit;
	}

	/**
	 * Send HTTP response
	 *
	 * @param int    $status_code The HTTP status code
	 * @param string $message     The response message
	 */
	private function send_response( $status_code, $message = '' ) {
		header( "HTTP/1.1 {$status_code} {$message}" );

		if ( $status_code !== 204 && $status_code !== 201 ) {
			echo $message;
		}

		exit;
	}

	/**
	 * Get HTTP status text
	 *
	 * @param int $code The status code
	 * @return string The status text
	 */
	private function get_status_text( $code ) {
		$status_texts = array(
			200 => 'OK',
			201 => 'Created',
			204 => 'No Content',
			207 => 'Multi-Status',
			400 => 'Bad Request',
			401 => 'Unauthorized',
			403 => 'Forbidden',
			404 => 'Not Found',
			405 => 'Method Not Allowed',
			501 => 'Not Implemented',
		);

		return isset( $status_texts[ $code ] ) ? $status_texts[ $code ] : 'Unknown';
	}
}
