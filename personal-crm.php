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

