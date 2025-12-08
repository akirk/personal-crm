<?php
/**
 * Plugin Name: Personal CRM
 * Description: WordPress-based personal CRM tool for managing contacts, teams, and relationships with extensible architecture
 * Version: 1.0.0
 * Author: Alex Kirk
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * License: MIT
 * Text Domain: personal-crm
 */

namespace PersonalCRM;

// Define constants
define( 'PERSONAL_CRM_PLUGIN_VERSION', '1.0.0' );
define( 'PERSONAL_CRM_PLUGIN_FILE', __FILE__ );

// Register activation/deactivation hooks at plugin file level
// These must be registered here (not in a class constructor) because
// activation hooks fire BEFORE plugins_loaded
register_activation_hook( __FILE__, 'PersonalCRM\personal_crm_activate' );
register_deactivation_hook( __FILE__, 'PersonalCRM\personal_crm_deactivate' );

/**
 * Plugin activation callback
 * Must be at plugin file level to run before plugins_loaded
 */
function personal_crm_activate() {
	// Only run activation in WordPress environment
	if ( ! defined( 'WPINC' ) ) {
		return;
	}

	// Load dependencies manually since autoloader may not be ready
	if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
		require_once __DIR__ . '/vendor/autoload.php';
	}

	// Set default options (these don't depend on external classes)
	add_option( 'personal_crm_storage_type', 'wpdb' );
	add_option( 'personal_crm_default_team', '' );
	add_option( 'personal_crm_version', PERSONAL_CRM_PLUGIN_VERSION );

	// Only proceed with WpApp and Storage setup if the classes are available
	if ( ! class_exists( '\WpApp\WpApp' ) ) {
		// WpApp not available - rewrite rules will be set up on first page load
		// when the autoloader is properly initialized
		return;
	}

	// Create/update database tables if Storage class is available
	if ( class_exists( '\WpApp\BaseStorage' ) ) {
		require_once __DIR__ . '/includes/storage.php';
		global $wpdb;
		$storage = new Storage( $wpdb );
		$storage->create_tables();
	}

	// Initialize WpApp to register rewrite rules, then flush them
	$app = new \WpApp\WpApp(
		__DIR__ . '/',
		'crm',
		array(
			'show_masterbar_for_anonymous' => false,
			'show_wp_logo'                 => true,
			'show_site_name'               => true,
			'app_name'                     => 'Personal CRM',
			'require_capability'           => 'read',
			'clear_admin_bar'              => false,
		)
	);

	// Register routes (same as PersonalCrm::setup_routes)
	$app->route( 'admin', 'admin/index.php' );
	$app->route( 'admin/group/{group}', 'admin/index.php' );
	$app->route( 'admin/group/{group}/links', 'admin/index.php' );
	$app->route( 'admin/group/{group}/members', 'admin/index.php' );
	$app->route( 'admin/group/{group}/leadership', 'admin/index.php' );
	$app->route( 'admin/group/{group}/consultants', 'admin/index.php' );
	$app->route( 'admin/group/{group}/alumni', 'admin/index.php' );
	$app->route( 'admin/group/{group}/events', 'admin/index.php' );
	$app->route( 'admin/group/{group}/audit', 'admin/index.php' );
	$app->route( 'admin/person/{person}', 'admin/index.php' );
	$app->route( 'finder', 'finder.php' );
	$app->route( 'search', 'finder.php' );
	$app->route( 'person/{person}', 'person.php' );
	$app->route( 'group/{group}/history', 'group-history.php' );
	$app->route( 'group/{group}', 'group.php' );
	$app->route( 'events', 'events.php' );
	$app->route( 'audit', 'audit.php' );
	$app->route( 'import-person', 'import-person.php' );
	$app->route( 'select', 'index.php' );

	// Initialize to register the rewrite rules
	$app->init();

	// Now flush rewrite rules so WordPress recognizes the new /crm endpoint
	flush_rewrite_rules();
}

/**
 * Plugin deactivation callback
 */
function personal_crm_deactivate() {
	flush_rewrite_rules();
}

// Load Composer autoloader
if ( ! file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    if ( defined( 'WPINC' ) ) {
        add_action( 'admin_notice', 'Personal CRM: Please read the instructions for setup.' );
        return;
    }
    echo 'Please read the instructions for setup.';
    exit;
}
require_once __DIR__ . '/vendor/autoload.php';

// Load DateTime wrappers early (before any other includes)
require_once __DIR__ . '/includes/datetime.php';
require_once __DIR__ . '/includes/time-travel.php';

// Initialize time travel (sets simulated date if as_of parameter is present)
\PersonalCRM\TimeTravel::init();

require_once __DIR__ . '/includes/personal-crm.php';

// Initialize storage and set up PersonalCrm
if ( defined( 'WPINC' ) ) {
    // WordPress context - use WordPress wpdb
    add_action( 'plugins_loaded', function() {
        global $wpdb;
        $storage = new Storage( $wpdb );
        PersonalCrm::set_storage( $storage );
        PersonalCrm::get_instance();
    } );
} else {
    $sqlite_file = __DIR__ . '/data/a8c.db';
    $sqlite_wpdb = new \WpApp\sqlite_wpdb( $sqlite_file, '' );
    $storage = new Storage( $sqlite_wpdb );
    PersonalCrm::set_storage( $storage );
}

