<?php
/**
 * CardDAV Authentication Handler - Application Passwords Only
 *
 * Requires WordPress Application Passwords for authentication.
 * Regular WordPress passwords are NOT accepted for security reasons.
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
 * Handles authentication using ONLY WordPress Application Passwords
 * Regular passwords are rejected for security
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
	 * Authenticate the current request using Application Passwords ONLY
	 *
	 * @return bool True if authentication successful, false otherwise
	 */
	public static function authenticate() {
		// Check if already logged in as WordPress user (browser session)
		if ( is_user_logged_in() ) {
			self::$authenticated_user = wp_get_current_user();
			return true;
		}

		// Extract HTTP Basic Auth credentials
		$username = null;
		$password = null;

		// Check for PHP_AUTH_USER and PHP_AUTH_PW
		if ( isset( $_SERVER['PHP_AUTH_USER'] ) && isset( $_SERVER['PHP_AUTH_PW'] ) ) {
			$username = $_SERVER['PHP_AUTH_USER'];
			$password = $_SERVER['PHP_AUTH_PW'];
		}
		// Check for Authorization header (alternative method)
		elseif ( isset( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
			list( $username, $password ) = self::extract_credentials( $_SERVER['HTTP_AUTHORIZATION'] );
		}
		// Check for REDIRECT_HTTP_AUTHORIZATION (some server configs)
		elseif ( isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
			list( $username, $password ) = self::extract_credentials( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] );
		}

		// If no credentials provided, return false
		if ( empty( $username ) || empty( $password ) ) {
			return false;
		}

		// Check if Application Passwords are available
		if ( ! function_exists( 'wp_is_application_passwords_available' ) || ! wp_is_application_passwords_available() ) {
			// Log that Application Passwords aren't available
			error_log( 'CardDAV: Application Passwords are not available on this WordPress installation' );
			return false;
		}

		// ONLY authenticate using Application Passwords
		$user = self::authenticate_application_password( $username, $password );

		if ( is_wp_error( $user ) || ! $user ) {
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
	 * Authenticate using WordPress Application Password ONLY
	 *
	 * @param string $username The username or email
	 * @param string $password The application password
	 * @return WP_User|WP_Error|false User object on success, error/false on failure
	 */
	private static function authenticate_application_password( $username, $password ) {
		// Get user by login or email
		$user = get_user_by( 'login', $username );
		if ( ! $user ) {
			$user = get_user_by( 'email', $username );
		}

		if ( ! $user ) {
			return false;
		}

		// Get user's application passwords
		$passwords = \WP_Application_Passwords::get_user_application_passwords( $user->ID );

		if ( empty( $passwords ) ) {
			// User has no application passwords created
			return false;
		}

		// Check if the provided password matches any application password
		foreach ( $passwords as $item ) {
			if ( wp_check_password( $password, $item['password'], $user->ID ) ) {
				// Valid application password! Update last used timestamp
				\WP_Application_Passwords::record_application_password_usage( $user->ID, $item['uuid'] );

				return $user;
			}
		}

		// Password didn't match any application password
		return false;
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
	 * Send 401 Unauthorized response with helpful message
	 */
	public static function send_unauthorized_response() {
		header( 'HTTP/1.1 401 Unauthorized' );
		header( 'WWW-Authenticate: Basic realm="Personal CRM CardDAV - Application Password Required"' );

		// Send helpful error message
		$message = "Authentication Required\n\n";
		$message .= "This CardDAV server requires a WordPress Application Password.\n";
		$message .= "Your regular WordPress password will NOT work.\n\n";
		$message .= "To create an Application Password:\n";
		$message .= "1. Log into your WordPress admin panel\n";
		$message .= "2. Go to Users → Profile\n";
		$message .= "3. Scroll to 'Application Passwords'\n";
		$message .= "4. Create a password for 'CardDAV - [Your Device Name]'\n";
		$message .= "5. Use that password in your CardDAV client\n\n";
		$message .= "Documentation: " . admin_url( 'admin.php?page=personal-crm-carddav' );

		echo $message;
		exit;
	}

	/**
	 * Send 403 Forbidden response
	 */
	public static function send_forbidden_response() {
		header( 'HTTP/1.1 403 Forbidden' );
		echo 'Access denied - insufficient permissions';
		exit;
	}

	/**
	 * Require authentication for current request
	 *
	 * Sends 401 with helpful message if not authenticated
	 */
	public static function require_authentication() {
		if ( ! self::is_authenticated() ) {
			self::send_unauthorized_response();
		}
	}

	/**
	 * Check if Application Passwords are available on this site
	 *
	 * @return bool True if available
	 */
	public static function is_application_passwords_available() {
		return function_exists( 'wp_is_application_passwords_available' ) && wp_is_application_passwords_available();
	}

	/**
	 * Get setup instructions for users
	 *
	 * @return string HTML instructions
	 */
	public static function get_setup_instructions() {
		$profile_url = admin_url( 'profile.php#application-passwords-section' );

		ob_start();
		?>
		<div class="carddav-setup-notice">
			<h3>⚠️ Application Password Required</h3>
			<p><strong>Important:</strong> This CardDAV server does NOT accept your regular WordPress password for security reasons.</p>

			<h4>How to Set Up CardDAV:</h4>
			<ol>
				<li>Go to <a href="<?php echo esc_url( $profile_url ); ?>">your WordPress profile</a></li>
				<li>Scroll to the "Application Passwords" section</li>
				<li>Enter a name like: <code>CardDAV - iPhone</code> (or your device name)</li>
				<li>Click "Add New Application Password"</li>
				<li>Copy the generated password (it will only be shown once!)</li>
				<li>Use this password in your CardDAV client</li>
			</ol>

			<h4>Why Application Passwords?</h4>
			<ul>
				<li>✅ Your WordPress admin password stays secure</li>
				<li>✅ Create a separate password for each device</li>
				<li>✅ Revoke access for lost/stolen devices</li>
				<li>✅ See when each device last synced</li>
			</ul>

			<?php if ( ! self::is_application_passwords_available() ) : ?>
				<div class="notice notice-error">
					<p><strong>⚠️ Application Passwords Not Available</strong></p>
					<p>Application Passwords require WordPress 5.6 or higher and HTTPS.</p>
					<p>Please update WordPress or enable SSL to use CardDAV.</p>
				</div>
			<?php endif; ?>
		</div>
		<style>
			.carddav-setup-notice {
				background: #fff;
				border-left: 4px solid #d63638;
				padding: 15px;
				margin: 20px 0;
			}
			.carddav-setup-notice h3 {
				margin-top: 0;
				color: #d63638;
			}
			.carddav-setup-notice code {
				background: #f0f0f0;
				padding: 2px 6px;
				border-radius: 3px;
			}
		</style>
		<?php
		return ob_get_clean();
	}
}
