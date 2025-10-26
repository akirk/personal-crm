<?php
/**
 * CardDAV Authentication with Application Passwords
 *
 * Enhanced authentication supporting WordPress Application Passwords
 *
 * @package Personal_CRM_CardDAV
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Personal_CRM_CardDAV_Auth_Enhanced
 *
 * Supports both regular passwords and Application Passwords
 */
class Personal_CRM_CardDAV_Auth_Enhanced extends Personal_CRM_CardDAV_Auth {

	/**
	 * Authenticate the current request
	 * Enhanced to support Application Passwords
	 *
	 * @return bool True if authentication successful
	 */
	public static function authenticate() {
		// Check if already logged in as WordPress user
		if ( is_user_logged_in() ) {
			self::$authenticated_user = wp_get_current_user();
			return true;
		}

		// Try HTTP Basic Authentication
		$username = null;
		$password = null;

		// Extract credentials from various header formats
		if ( isset( $_SERVER['PHP_AUTH_USER'] ) && isset( $_SERVER['PHP_AUTH_PW'] ) ) {
			$username = $_SERVER['PHP_AUTH_USER'];
			$password = $_SERVER['PHP_AUTH_PW'];
		} elseif ( isset( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
			list( $username, $password ) = self::extract_credentials( $_SERVER['HTTP_AUTHORIZATION'] );
		} elseif ( isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
			list( $username, $password ) = self::extract_credentials( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] );
		}

		if ( empty( $username ) || empty( $password ) ) {
			return false;
		}

		// Try Application Password authentication first (WordPress 5.6+)
		if ( function_exists( 'wp_is_application_passwords_available' ) && wp_is_application_passwords_available() ) {
			$user = self::authenticate_application_password( $username, $password );
			if ( $user && ! is_wp_error( $user ) ) {
				if ( self::user_can_access_crm( $user ) ) {
					self::$authenticated_user = $user;
					return true;
				}
			}
		}

		// Fallback to regular WordPress authentication
		$user = wp_authenticate( $username, $password );

		if ( is_wp_error( $user ) ) {
			return false;
		}

		if ( ! self::user_can_access_crm( $user ) ) {
			return false;
		}

		self::$authenticated_user = $user;
		return true;
	}

	/**
	 * Authenticate using WordPress Application Password
	 *
	 * @param string $username The username
	 * @param string $password The application password
	 * @return WP_User|WP_Error User object on success, error on failure
	 */
	private static function authenticate_application_password( $username, $password ) {
		// Get user by login
		$user = get_user_by( 'login', $username );
		if ( ! $user ) {
			$user = get_user_by( 'email', $username );
		}

		if ( ! $user ) {
			return new \WP_Error( 'invalid_username', 'Invalid username' );
		}

		// Use WordPress Application Passwords API
		$passwords = \WP_Application_Passwords::get_user_application_passwords( $user->ID );

		foreach ( $passwords as $item ) {
			// Check if password matches
			if ( wp_check_password( $password, $item['password'], $user->ID ) ) {
				// Update last used timestamp
				\WP_Application_Passwords::record_application_password_usage( $user->ID, $item['uuid'] );

				return $user;
			}
		}

		return new \WP_Error( 'invalid_application_password', 'Invalid application password' );
	}

	/**
	 * Extract credentials from Authorization header
	 *
	 * @param string $auth_header The authorization header
	 * @return array [username, password]
	 */
	private static function extract_credentials( $auth_header ) {
		if ( strpos( $auth_header, 'Basic ' ) !== 0 ) {
			return array( null, null );
		}

		$credentials = base64_decode( substr( $auth_header, 6 ) );
		if ( ! $credentials || strpos( $credentials, ':' ) === false ) {
			return array( null, null );
		}

		return explode( ':', $credentials, 2 );
	}
}
