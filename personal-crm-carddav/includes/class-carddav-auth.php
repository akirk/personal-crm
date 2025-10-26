<?php
/**
 * CardDAV Authentication Handler
 *
 * Handles authentication for CardDAV requests using WordPress users
 *
 * @package Personal_CRM_CardDAV
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Personal_CRM_CardDAV_Auth
 *
 * Handles HTTP Basic Authentication for CardDAV requests
 */
class Personal_CRM_CardDAV_Auth {

	/**
	 * The authenticated user
	 *
	 * @var WP_User|null
	 */
	private static $authenticated_user = null;

	/**
	 * Check if current request is authenticated
	 *
	 * @return bool True if authenticated, false otherwise
	 */
	public static function is_authenticated() {
		if ( self::$authenticated_user !== null ) {
			return true;
		}

		return self::authenticate();
	}

	/**
	 * Authenticate the current request using HTTP Basic Auth
	 *
	 * @return bool True if authentication successful, false otherwise
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

		// Check for PHP_AUTH_USER and PHP_AUTH_PW
		if ( isset( $_SERVER['PHP_AUTH_USER'] ) && isset( $_SERVER['PHP_AUTH_PW'] ) ) {
			$username = $_SERVER['PHP_AUTH_USER'];
			$password = $_SERVER['PHP_AUTH_PW'];
		}
		// Check for Authorization header (alternative method)
		elseif ( isset( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
			$auth = $_SERVER['HTTP_AUTHORIZATION'];
			if ( strpos( $auth, 'Basic ' ) === 0 ) {
				$credentials = base64_decode( substr( $auth, 6 ) );
				if ( $credentials && strpos( $credentials, ':' ) !== false ) {
					list( $username, $password ) = explode( ':', $credentials, 2 );
				}
			}
		}
		// Check for REDIRECT_HTTP_AUTHORIZATION (some server configs)
		elseif ( isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
			$auth = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
			if ( strpos( $auth, 'Basic ' ) === 0 ) {
				$credentials = base64_decode( substr( $auth, 6 ) );
				if ( $credentials && strpos( $credentials, ':' ) !== false ) {
					list( $username, $password ) = explode( ':', $credentials, 2 );
				}
			}
		}

		// If no credentials provided, return false
		if ( empty( $username ) || empty( $password ) ) {
			return false;
		}

		// Authenticate using WordPress
		$user = wp_authenticate( $username, $password );

		if ( is_wp_error( $user ) ) {
			return false;
		}

		// Check if user has permission to access Personal CRM
		if ( ! self::user_can_access_crm( $user ) ) {
			return false;
		}

		self::$authenticated_user = $user;
		return true;
	}

	/**
	 * Get the authenticated user
	 *
	 * @return WP_User|null The authenticated user or null
	 */
	public static function get_authenticated_user() {
		if ( self::$authenticated_user === null ) {
			self::authenticate();
		}

		return self::$authenticated_user;
	}

	/**
	 * Check if a user has permission to access Personal CRM
	 *
	 * @param WP_User $user The user to check
	 * @return bool True if user can access, false otherwise
	 */
	public static function user_can_access_crm( $user ) {
		// Allow administrators
		if ( user_can( $user, 'manage_options' ) ) {
			return true;
		}

		// Allow users who can edit posts (editors, authors)
		if ( user_can( $user, 'edit_posts' ) ) {
			return true;
		}

		/**
		 * Filter to allow custom permission checks
		 *
		 * @param bool    $can_access Default access decision
		 * @param WP_User $user       The user being checked
		 */
		return apply_filters( 'personal_crm_carddav_user_can_access', false, $user );
	}

	/**
	 * Send 401 Unauthorized response
	 */
	public static function send_unauthorized_response() {
		header( 'HTTP/1.1 401 Unauthorized' );
		header( 'WWW-Authenticate: Basic realm="Personal CRM CardDAV"' );
		echo 'Authentication required';
		exit;
	}

	/**
	 * Send 403 Forbidden response
	 */
	public static function send_forbidden_response() {
		header( 'HTTP/1.1 403 Forbidden' );
		echo 'Access denied';
		exit;
	}

	/**
	 * Require authentication for current request
	 *
	 * Sends 401 if not authenticated
	 */
	public static function require_authentication() {
		if ( ! self::is_authenticated() ) {
			self::send_unauthorized_response();
		}
	}
}
