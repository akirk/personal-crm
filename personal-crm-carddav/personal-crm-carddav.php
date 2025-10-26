<?php
/**
 * Plugin Name: Personal CRM CardDAV Integration
 * Plugin URI: https://github.com/akirk/personal-crm
 * Description: Adds CardDAV server capability to the Personal CRM plugin, allowing synchronization with CardDAV-compatible clients.
 * Version: 1.0.0
 * Author: Personal CRM Contributors
 * Requires PHP: 7.4
 * Requires at least: 5.0
 * Text Domain: personal-crm-carddav
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'PERSONAL_CRM_CARDDAV_VERSION', '1.0.0' );
define( 'PERSONAL_CRM_CARDDAV_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PERSONAL_CRM_CARDDAV_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Require the main plugin class
require_once PERSONAL_CRM_CARDDAV_PLUGIN_DIR . 'includes/class-carddav-server.php';
require_once PERSONAL_CRM_CARDDAV_PLUGIN_DIR . 'includes/class-vcard-converter.php';

// Load authentication class
// By default, uses Application Passwords ONLY for security
// To allow regular passwords (NOT recommended), define: PERSONAL_CRM_CARDDAV_ALLOW_REGULAR_PASSWORDS
if ( defined( 'PERSONAL_CRM_CARDDAV_ALLOW_REGULAR_PASSWORDS' ) && PERSONAL_CRM_CARDDAV_ALLOW_REGULAR_PASSWORDS ) {
	require_once PERSONAL_CRM_CARDDAV_PLUGIN_DIR . 'includes/class-carddav-auth.php';
} else {
	require_once PERSONAL_CRM_CARDDAV_PLUGIN_DIR . 'includes/class-carddav-auth-secure.php';
}

require_once PERSONAL_CRM_CARDDAV_PLUGIN_DIR . 'includes/class-carddav-integration.php';

/**
 * Initialize the CardDAV integration plugin
 */
function personal_crm_carddav_init() {
	// Check if Personal CRM plugin is active
	if ( ! class_exists( 'PersonalCrm' ) ) {
		add_action( 'admin_notices', 'personal_crm_carddav_missing_dependency_notice' );
		return;
	}

	// Initialize the CardDAV integration
	Personal_CRM_CardDAV_Integration::get_instance();
}
add_action( 'plugins_loaded', 'personal_crm_carddav_init', 20 ); // Priority 20 to ensure Personal CRM loads first

/**
 * Display admin notice if Personal CRM is not active
 */
function personal_crm_carddav_missing_dependency_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<strong><?php esc_html_e( 'Personal CRM CardDAV Integration', 'personal-crm-carddav' ); ?>:</strong>
			<?php esc_html_e( 'The Personal CRM plugin must be installed and activated for the CardDAV integration to work.', 'personal-crm-carddav' ); ?>
		</p>
	</div>
	<?php
}

/**
 * Plugin activation hook
 */
function personal_crm_carddav_activate() {
	// Check if Personal CRM is active
	if ( ! class_exists( 'PersonalCrm' ) ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die(
			esc_html__( 'Personal CRM CardDAV Integration requires the Personal CRM plugin to be installed and activated.', 'personal-crm-carddav' ),
			esc_html__( 'Plugin Activation Error', 'personal-crm-carddav' ),
			array( 'back_link' => true )
		);
	}

	// Flush rewrite rules to register CardDAV endpoints
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'personal_crm_carddav_activate' );

/**
 * Plugin deactivation hook
 */
function personal_crm_carddav_deactivate() {
	// Flush rewrite rules to remove CardDAV endpoints
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'personal_crm_carddav_deactivate' );
