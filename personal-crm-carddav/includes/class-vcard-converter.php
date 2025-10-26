<?php
/**
 * vCard Converter Class
 *
 * Converts Person objects from Personal CRM to vCard 4.0 format
 *
 * @package Personal_CRM_CardDAV
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Personal_CRM_VCard_Converter
 *
 * Handles conversion between Person objects and vCard format
 */
class Personal_CRM_VCard_Converter {

	/**
	 * Convert a Person object to vCard 4.0 format
	 *
	 * @param Person $person The person object to convert
	 * @return string vCard formatted string
	 */
	public static function person_to_vcard( $person ) {
		$vcard = array();

		// vCard 4.0 header
		$vcard[] = 'BEGIN:VCARD';
		$vcard[] = 'VERSION:4.0';

		// UID - using username as unique identifier
		$vcard[] = 'UID:' . self::escape( $person->username );

		// Full name (FN) - required field
		$display_name = $person->get_display_name_with_nickname();
		$vcard[] = 'FN:' . self::escape( $display_name );

		// Structured name (N) - Last;First;Middle;Prefix;Suffix
		// Since we have a simple name field, we'll parse it
		$name_parts = self::parse_name( $person->name );
		$vcard[] = sprintf(
			'N:%s;%s;;;',
			self::escape( $name_parts['last'] ),
			self::escape( $name_parts['first'] )
		);

		// Nickname
		if ( ! empty( $person->nickname ) ) {
			$vcard[] = 'NICKNAME:' . self::escape( $person->nickname );
		}

		// Email
		if ( ! empty( $person->email ) ) {
			$vcard[] = 'EMAIL;TYPE=work:' . self::escape( $person->email );
		}

		// Role/Title
		if ( ! empty( $person->role ) ) {
			$vcard[] = 'TITLE:' . self::escape( $person->role );
		}

		// Organization
		$vcard[] = 'ORG:' . self::escape( get_bloginfo( 'name' ) );

		// Birthday
		if ( ! empty( $person->birthday ) ) {
			try {
				$birthday = new DateTime( $person->birthday );
				$vcard[] = 'BDAY:' . $birthday->format( 'Ymd' );
			} catch ( Exception $e ) {
				// Invalid date, skip
			}
		}

		// Anniversary (company anniversary)
		if ( ! empty( $person->company_anniversary ) ) {
			try {
				$anniversary = new DateTime( $person->company_anniversary );
				$vcard[] = 'ANNIVERSARY:' . $anniversary->format( 'Ymd' );
			} catch ( Exception $e ) {
				// Invalid date, skip
			}
		}

		// Timezone
		if ( ! empty( $person->timezone ) ) {
			$vcard[] = 'TZ:' . self::escape( $person->timezone );
		}

		// Geographic location
		if ( ! empty( $person->location ) ) {
			$vcard[] = 'ADR;TYPE=work:;;' . self::escape( $person->location ) . ';;;;';
		}

		// URLs
		if ( ! empty( $person->website ) ) {
			$vcard[] = 'URL;TYPE=home:' . self::escape( $person->website );
		}

		if ( ! empty( $person->github ) ) {
			$vcard[] = 'URL;TYPE=github:https://github.com/' . self::escape( $person->github );
		}

		if ( ! empty( $person->linkedin ) ) {
			$vcard[] = 'URL;TYPE=linkedin:' . self::escape( $person->linkedin );
		}

		if ( ! empty( $person->wordpress ) ) {
			$vcard[] = 'URL;TYPE=wordpress:https://profiles.wordpress.org/' . self::escape( $person->wordpress );
		}

		// Additional links
		if ( ! empty( $person->links ) && is_array( $person->links ) ) {
			foreach ( $person->links as $link ) {
				if ( ! empty( $link['url'] ) ) {
					$type = ! empty( $link['name'] ) ? $link['name'] : 'other';
					$vcard[] = 'URL;TYPE=' . self::escape( $type ) . ':' . self::escape( $link['url'] );
				}
			}
		}

		// Categories/Tags - include team/group membership
		$categories = array();
		if ( ! empty( $person->category ) ) {
			$categories[] = $person->category;
		}
		if ( ! empty( $person->team ) ) {
			$categories[] = $person->team;
		}
		if ( ! empty( $categories ) ) {
			$vcard[] = 'CATEGORIES:' . implode( ',', array_map( array( self::class, 'escape' ), $categories ) );
		}

		// Notes - combine all notes
		$notes = array();

		if ( ! empty( $person->partner ) ) {
			$notes[] = 'Partner: ' . $person->partner;
			if ( ! empty( $person->partner_birthday ) ) {
				$notes[] = 'Partner Birthday: ' . $person->partner_birthday;
			}
		}

		if ( ! empty( $person->kids ) && is_array( $person->kids ) ) {
			foreach ( $person->kids as $kid ) {
				if ( is_array( $kid ) && ! empty( $kid['name'] ) ) {
					$kid_info = 'Child: ' . $kid['name'];
					if ( ! empty( $kid['birthday'] ) ) {
						$kid_info .= ' (Birthday: ' . $kid['birthday'] . ')';
					}
					$notes[] = $kid_info;
				}
			}
		}

		if ( ! empty( $person->notes ) && is_array( $person->notes ) ) {
			foreach ( $person->notes as $note ) {
				if ( is_array( $note ) && ! empty( $note['note'] ) ) {
					$notes[] = $note['note'];
				} elseif ( is_string( $note ) ) {
					$notes[] = $note;
				}
			}
		}

		if ( ! empty( $notes ) ) {
			$combined_notes = implode( "\n\n", $notes );
			$vcard[] = 'NOTE:' . self::escape( $combined_notes );
		}

		// Photo/Avatar - use Gravatar if available
		if ( ! empty( $person->email ) && $person->has_gravatar() ) {
			$gravatar_url = $person->get_gravatar_url( 200 );
			$vcard[] = 'PHOTO;MEDIATYPE=image/jpeg:' . self::escape( $gravatar_url );
		}

		// Extended vCard fields (phone numbers, additional emails, etc.)
		if ( ! empty( $person->vcard_data ) && is_array( $person->vcard_data ) ) {
			foreach ( $person->vcard_data as $field_name => $field_data ) {
				// Handle multiple values
				if ( isset( $field_data[0] ) && is_array( $field_data[0] ) ) {
					// Multiple values
					foreach ( $field_data as $item ) {
						$value = $item['value'] ?? '';
						$type = $item['type'] ?? '';

						if ( ! empty( $value ) ) {
							if ( ! empty( $type ) ) {
								$vcard[] = $field_name . ';TYPE=' . self::escape( $type ) . ':' . self::escape( $value );
							} else {
								$vcard[] = $field_name . ':' . self::escape( $value );
							}
						}
					}
				} else {
					// Single value
					$value = $field_data['value'] ?? '';
					$type = $field_data['type'] ?? '';

					if ( ! empty( $value ) ) {
						if ( ! empty( $type ) ) {
							$vcard[] = $field_name . ';TYPE=' . self::escape( $type ) . ':' . self::escape( $value );
						} else {
							$vcard[] = $field_name . ':' . self::escape( $value );
						}
					}
				}
			}
		}

		// Custom fields using X- prefix
		if ( ! empty( $person->linear ) ) {
			$vcard[] = 'X-LINEAR:' . self::escape( $person->linear );
		}

		if ( ! empty( $person->left_company ) ) {
			$vcard[] = 'X-LEFT-COMPANY:TRUE';
			if ( ! empty( $person->new_company ) ) {
				$vcard[] = 'X-NEW-COMPANY:' . self::escape( $person->new_company );
			}
			if ( ! empty( $person->new_company_website ) ) {
				$vcard[] = 'X-NEW-COMPANY-WEBSITE:' . self::escape( $person->new_company_website );
			}
		}

		if ( ! empty( $person->deceased ) ) {
			$vcard[] = 'X-DECEASED:TRUE';
			if ( ! empty( $person->deceased_date ) ) {
				$vcard[] = 'X-DECEASED-DATE:' . self::escape( $person->deceased_date );
			}
		}

		// Revision timestamp
		$vcard[] = 'REV:' . gmdate( 'Ymd\THis\Z' );

		// vCard footer
		$vcard[] = 'END:VCARD';

		return implode( "\r\n", $vcard ) . "\r\n";
	}

	/**
	 * Convert vCard data to Person data array
	 *
	 * @param string $vcard_data The vCard formatted string
	 * @return array Person data array that can be saved to storage
	 */
	public static function vcard_to_person_data( $vcard_data ) {
		$person_data = array();
		$extended_data = array(); // Store fields that don't map to Person schema
		$lines = explode( "\n", str_replace( "\r\n", "\n", $vcard_data ) );

		$in_vcard = false;
		$current_property = null;
		$notes = array();

		foreach ( $lines as $line ) {
			$line = trim( $line );

			if ( $line === 'BEGIN:VCARD' ) {
				$in_vcard = true;
				continue;
			}

			if ( $line === 'END:VCARD' ) {
				break;
			}

			if ( ! $in_vcard ) {
				continue;
			}

			// Handle line folding (continuation lines start with space or tab)
			if ( preg_match( '/^[ \t]/', $line ) && $current_property ) {
				$current_property .= ltrim( $line );
				continue;
			}

			$current_property = $line;

			// Parse the property
			if ( strpos( $line, ':' ) === false ) {
				continue;
			}

			list( $property, $value ) = explode( ':', $line, 2 );

			// Remove parameters (everything after semicolon in property name)
			$property_parts = explode( ';', $property );
			$property_name = $property_parts[0];

			$value = self::unescape( $value );

			switch ( $property_name ) {
				case 'UID':
					$person_data['username'] = $value;
					break;

				case 'FN':
					// Use FN as name if N is not present
					if ( empty( $person_data['name'] ) ) {
						$person_data['name'] = $value;
					}
					break;

				case 'N':
					// N format: Last;First;Middle;Prefix;Suffix
					$name_parts = explode( ';', $value );
					$first = isset( $name_parts[1] ) ? trim( $name_parts[1] ) : '';
					$last = isset( $name_parts[0] ) ? trim( $name_parts[0] ) : '';
					$person_data['name'] = trim( $first . ' ' . $last );
					break;

				case 'NICKNAME':
					$person_data['nickname'] = $value;
					break;

				case 'EMAIL':
					$person_data['email'] = $value;
					break;

				case 'TITLE':
					$person_data['role'] = $value;
					break;

				case 'BDAY':
					// Parse date in various formats
					$person_data['birthday'] = self::parse_date( $value );
					break;

				case 'ANNIVERSARY':
					$person_data['company_anniversary'] = self::parse_date( $value );
					break;

				case 'TZ':
					$person_data['timezone'] = $value;
					break;

				case 'ADR':
					// ADR format: POBox;Extended;Street;City;Region;PostalCode;Country
					$addr_parts = explode( ';', $value );
					$location_parts = array_filter( $addr_parts );
					if ( ! empty( $location_parts ) ) {
						$person_data['location'] = implode( ', ', $location_parts );
					}
					break;

				case 'URL':
					// Parse URL type from parameters
					$url_type = self::get_parameter_value( $property, 'TYPE' );

					if ( $url_type === 'github' || strpos( $value, 'github.com' ) !== false ) {
						// Extract GitHub username from URL
						if ( preg_match( '#github\.com/([^/]+)#', $value, $matches ) ) {
							$person_data['github'] = $matches[1];
						}
					} elseif ( $url_type === 'linkedin' || strpos( $value, 'linkedin.com' ) !== false ) {
						$person_data['linkedin'] = $value;
					} elseif ( $url_type === 'wordpress' || strpos( $value, 'wordpress.org' ) !== false ) {
						// Extract WordPress.org username from URL
						if ( preg_match( '#wordpress\.org/([^/]+)#', $value, $matches ) ) {
							$person_data['wordpress'] = $matches[1];
						}
					} elseif ( $url_type === 'home' || empty( $url_type ) ) {
						$person_data['website'] = $value;
					} else {
						// Store as custom link
						if ( ! isset( $person_data['links'] ) ) {
							$person_data['links'] = array();
						}
						$person_data['links'][] = array(
							'name' => $url_type,
							'url'  => $value,
						);
					}
					break;

				case 'NOTE':
					$notes[] = $value;
					break;

				case 'X-LINEAR':
					$person_data['linear'] = $value;
					break;

				case 'X-LEFT-COMPANY':
					$person_data['left_company'] = ( strtoupper( $value ) === 'TRUE' );
					break;

				case 'X-NEW-COMPANY':
					$person_data['new_company'] = $value;
					break;

				case 'X-NEW-COMPANY-WEBSITE':
					$person_data['new_company_website'] = $value;
					break;

				case 'X-DECEASED':
					$person_data['deceased'] = ( strtoupper( $value ) === 'TRUE' );
					break;

				case 'X-DECEASED-DATE':
					$person_data['deceased_date'] = $value;
					break;

				// Extended vCard fields that don't map to Person schema
				case 'TEL':
				case 'IMPP':
				case 'LANG':
				case 'GENDER':
				case 'SOUND':
				case 'SOURCE':
					$field_type = self::get_parameter_value( $property, 'TYPE' );
					self::add_extended_field( $extended_data, $property_name, $value, $field_type );
					break;

				default:
					// Store any other unknown fields as extended data
					if ( strpos( $property_name, 'X-' ) === 0 && ! in_array( $property_name, array( 'X-LINEAR', 'X-LEFT-COMPANY', 'X-NEW-COMPANY', 'X-NEW-COMPANY-WEBSITE', 'X-DECEASED', 'X-DECEASED-DATE' ), true ) ) {
						$field_type = self::get_parameter_value( $property, 'TYPE' );
						self::add_extended_field( $extended_data, $property_name, $value, $field_type );
					}
					break;
			}
		}

		// Combine notes
		if ( ! empty( $notes ) ) {
			$person_data['notes'] = array_map(
				function( $note ) {
					return array( 'note' => $note );
				},
				$notes
			);
		}

		// Add extended fields
		if ( ! empty( $extended_data ) ) {
			$person_data['vcard_data'] = $extended_data;
		}

		return $person_data;
	}

	/**
	 * Add a field to extended data
	 *
	 * @param array  &$extended_data The extended data array (by reference)
	 * @param string $field_name     The field name
	 * @param string $value          The field value
	 * @param string $type           The field type
	 */
	private static function add_extended_field( &$extended_data, $field_name, $value, $type ) {
		$field_entry = array(
			'value' => $value,
			'type'  => $type ?: '',
		);

		if ( isset( $extended_data[ $field_name ] ) ) {
			// Convert to array if it's not already
			if ( ! isset( $extended_data[ $field_name ][0] ) ) {
				$extended_data[ $field_name ] = array( $extended_data[ $field_name ] );
			}
			$extended_data[ $field_name ][] = $field_entry;
		} else {
			$extended_data[ $field_name ] = $field_entry;
		}
	}

	/**
	 * Parse a name into first and last components
	 *
	 * @param string $name The full name
	 * @return array Array with 'first' and 'last' keys
	 */
	private static function parse_name( $name ) {
		$parts = explode( ' ', trim( $name ), 2 );

		return array(
			'first' => isset( $parts[0] ) ? $parts[0] : '',
			'last'  => isset( $parts[1] ) ? $parts[1] : '',
		);
	}

	/**
	 * Escape special characters in vCard values
	 *
	 * @param string $value The value to escape
	 * @return string Escaped value
	 */
	private static function escape( $value ) {
		// vCard escaping rules:
		// Backslash, semicolon, comma, and newline must be escaped
		$value = str_replace( '\\', '\\\\', $value );
		$value = str_replace( ',', '\\,', $value );
		$value = str_replace( ';', '\\;', $value );
		$value = str_replace( "\n", '\\n', $value );
		$value = str_replace( "\r", '', $value );

		return $value;
	}

	/**
	 * Unescape special characters in vCard values
	 *
	 * @param string $value The value to unescape
	 * @return string Unescaped value
	 */
	private static function unescape( $value ) {
		$value = str_replace( '\\n', "\n", $value );
		$value = str_replace( '\\;', ';', $value );
		$value = str_replace( '\\,', ',', $value );
		$value = str_replace( '\\\\', '\\', $value );

		return $value;
	}

	/**
	 * Parse a date from vCard format to Y-m-d
	 *
	 * @param string $date The date string
	 * @return string|null Formatted date or null
	 */
	private static function parse_date( $date ) {
		// Remove any non-digit characters
		$date = preg_replace( '/[^0-9]/', '', $date );

		if ( strlen( $date ) === 8 ) {
			// Format: YYYYMMDD
			return substr( $date, 0, 4 ) . '-' . substr( $date, 4, 2 ) . '-' . substr( $date, 6, 2 );
		}

		return null;
	}

	/**
	 * Get a parameter value from a property string
	 *
	 * @param string $property The property string (e.g., "URL;TYPE=github")
	 * @param string $param_name The parameter name to extract
	 * @return string|null The parameter value or null
	 */
	private static function get_parameter_value( $property, $param_name ) {
		if ( preg_match( '/' . preg_quote( $param_name, '/' ) . '=([^;:]+)/i', $property, $matches ) ) {
			return strtolower( trim( $matches[1] ) );
		}

		return null;
	}

	/**
	 * Generate ETag for a person
	 *
	 * @param Person $person The person object
	 * @return string ETag hash
	 */
	public static function generate_etag( $person ) {
		$vcard = self::person_to_vcard( $person );
		return '"' . md5( $vcard ) . '"';
	}
}
