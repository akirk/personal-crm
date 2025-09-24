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

if ( ! defined( 'WPINC' ) ) {
    // Only load polyfills in standalone mode
    require_once __DIR__ . '/includes/polyfills.php';
}
require_once __DIR__ . '/includes/storage-factory.php';
require_once __DIR__ . '/includes/personal-crm.php';

PersonalCrm::set_storage_type( defined( 'WPINC' ) ? 'wpdb' : 'sqlite' );
if ( defined( 'WPINC' ) ) {
    add_action( 'plugins_loaded', [ PersonalCrm::class, 'get_instance' ] );
}

