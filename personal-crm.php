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

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'PERSONAL_CRM_PLUGIN_VERSION', '1.0.0' );
define( 'PERSONAL_CRM_PLUGIN_FILE', __FILE__ );
define( 'PERSONAL_CRM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PERSONAL_CRM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once PERSONAL_CRM_PLUGIN_DIR . 'includes/polyfills.php';
require_once PERSONAL_CRM_PLUGIN_DIR . 'includes/storage-factory.php';
require_once PERSONAL_CRM_PLUGIN_DIR . 'includes/common.php';
require_once PERSONAL_CRM_PLUGIN_DIR . 'includes/event.php';
require_once PERSONAL_CRM_PLUGIN_DIR . 'includes/person.php';
require_once PERSONAL_CRM_PLUGIN_DIR . 'vendor/autoload.php';

class PersonalCrmTool {
    private $app;
    private $storage;

    public function __construct() {
        add_action( 'plugins_loaded', [ $this, 'init' ] );
        register_activation_hook( __FILE__, [ $this, 'activate' ] );
        register_deactivation_hook( __FILE__, [ $this, 'deactivate' ] );
    }

    public function init() {
        $this->storage = StorageFactory::create( 'wpdb' );

        $common = Common::get_instance( $this->storage );

        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            require_once PERSONAL_CRM_PLUGIN_DIR . 'includes/wp-cli-commands.php';
            \WP_CLI::add_command( 'crm migrate', 'Personal_CRM_Migrate_Command' );
        }

        $this->app = new \WpApp\WpApp(
            PERSONAL_CRM_PLUGIN_DIR,
            'crm',
            [
                'show_masterbar_for_anonymous' => false,
                'show_wp_logo' => true,
                'show_site_name' => true,
                'require_capability' => 'read',  // Require login
                'clear_admin_bar' => false
            ]
        );

        $this->setup_routes();
        $this->setup_menu();

        $this->app->init();
        wp_app_enqueue_style( 'personal-crm-style', plugin_dir_url( __FILE__ ) . 'assets/style.css' );
        wp_app_enqueue_style( 'personal-crm-cmd-k', plugin_dir_url( __FILE__ ) . 'assets/cmd-k.css' );
        wp_app_enqueue_script( 'personal-crm-cmd-k', plugin_dir_url( __FILE__ ) . 'assets/cmd-k.js', [ 'jquery' ], '1.0', true );
        wp_app_enqueue_script( 'personal-crm-script', plugin_dir_url( __FILE__ ) . 'assets/script.js', [ 'jquery' ], '1.0', true );

        // Fire action to allow other plugins to register routes and extend functionality
        do_action( 'personal_crm_loaded', $common );

        // Add admin settings page
        add_action( 'admin_menu', [ $this, 'admin_menu' ] );

        // Register settings
        add_action( 'admin_init', [ $this, 'admin_settings' ] );
    }


    private function setup_routes() {
        // Main dashboard (index.php)
        // Default route handled automatically by wp-app

        // Admin interface (admin.php)
        $this->app->route( 'admin', 'admin.php' );
        $this->app->route( 'admin/{team}', 'admin.php' );
        $this->app->route( 'admin/{team}/links', 'admin.php' );
        $this->app->route( 'admin/{team}/members', 'admin.php' );
        $this->app->route( 'admin/{team}/leadership', 'admin.php' );
        $this->app->route( 'admin/{team}/consultants', 'admin.php' );
        $this->app->route( 'admin/{team}/alumni', 'admin.php' );
        $this->app->route( 'admin/{team}/events', 'admin.php' );
        $this->app->route( 'admin/{team}/audit', 'admin.php' );
        $this->app->route( 'admin/{team}/json', 'admin.php' );
        $this->app->route( 'admin/{team}/person/{person}', 'admin.php' );

        // Finder/Search (finder.php)
        $this->app->route( 'finder', 'finder.php' );
        $this->app->route( 'search', 'finder.php' );

        // Person management (person.php)
        $this->app->route( 'person', 'person.php' );
        $this->app->route( '{team}/{person}', 'person.php' );

        // Events (events.php)
        $this->app->route( 'events', 'events.php' );

        // Audit reports (audit.php)
        $this->app->route( 'audit', 'audit.php' );

        // HR Routes (if HR addon is active)
        if ( is_plugin_active( 'a8c-hr/a8c-hr-addon.php' ) ) {
            $this->app->route( 'hr-stats', '../a8c-hr/hr-stats.php' );
            $this->app->route( 'hr-reports', '../a8c-hr/hr-reports.php' );
            $this->app->route( 'hr-config', '../a8c-hr/hr-config.php' );
        }

        // Import person (import-person.php)
        $this->app->route( 'import-person', 'import-person.php' );

        // Select interface (select.php)
        $this->app->route( 'select', 'select.php' );
    }

    private function setup_menu() {
        // Main navigation - Personal CRM focused
        $this->app->add_menu_item( 'dashboard', 'Dashboard', home_url( '/crm/' ) );
        $this->app->add_menu_item( 'finder', 'Find People', home_url( '/crm/finder' ) );
        $this->app->add_menu_item( 'person', 'People', home_url( '/crm/person' ) );
        $this->app->add_menu_item( 'events', 'Events', home_url( '/crm/events' ) );

        // Admin menu items (only for administrators)
        if ( current_user_can( 'manage_options' ) ) {
            $this->app->add_menu_item( 'admin', 'Admin', home_url( '/crm/admin' ) );
            $this->app->add_menu_item( 'audit', 'Audit', home_url( '/crm/audit' ) );
            $this->app->add_menu_item( 'import-person', 'Import Person', home_url( '/crm/import-person' ) );
            $this->app->add_menu_item( 'select', 'Select Tool', home_url( '/crm/select' ) );
            $this->app->add_menu_item( 'settings', 'Plugin Settings', admin_url( 'options-general.php?page=personal-crm-settings' ) );
        }

        // User-specific items
        if ( is_user_logged_in() ) {
            $current_user = wp_get_current_user();
            $this->app->add_user_menu_item(
                'my-profile',
                'My Profile',
                home_url( '/crm/person/' . $current_user->user_login )
            );
        }
    }

    public function activate() {
        // Create/update database tables if using WpDB storage
        $storage_type = get_option( 'personal_crm_storage_type', 'wpdb' );
        if ( $storage_type === 'wpdb' ) {
            // WpDB storage will create tables automatically
            $storage = StorageFactory::create( 'wpdb' );
        }

        // Flush rewrite rules
        flush_rewrite_rules();

        // Set default options
        add_option( 'personal_crm_storage_type', 'wpdb' );
        add_option( 'personal_crm_default_team', '' );
        add_option( 'personal_crm_version', PERSONAL_CRM_PLUGIN_VERSION );
    }

    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    public function admin_menu() {
        add_options_page(
            'Personal CRM Settings',
            'Personal CRM',
            'manage_options',
            'personal-crm-settings',
            [ $this, 'admin_page' ]
        );
    }

    public function admin_settings() {
        register_setting( 'personal_crm_settings', 'personal_crm_storage_type' );
        register_setting( 'personal_crm_settings', 'personal_crm_default_team' );

        add_settings_section(
            'personal_crm_general',
            'General Settings',
            [ $this, 'settings_section_callback' ],
            'personal_crm_settings'
        );

        add_settings_field(
            'storage_type',
            'Storage Type',
            [ $this, 'storage_type_callback' ],
            'personal_crm_settings',
            'personal_crm_general'
        );

        add_settings_field(
            'default_team',
            'Default Team',
            [ $this, 'default_team_callback' ],
            'personal_crm_settings',
            'personal_crm_general'
        );
    }

    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>Personal CRM Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'personal_crm_settings' );
                do_settings_sections( 'personal_crm_settings' );
                submit_button();
                ?>
            </form>

            <hr>

            <h2>Migration Tools</h2>
            <p>Need to migrate data between storage systems? Access the migration script directly via command line:</p>
            <p><code>php <?php echo PERSONAL_CRM_PLUGIN_DIR; ?>migrate.php --help</code></p>
        </div>
        <?php
    }

    public function settings_section_callback() {
        echo '<p>Configure the basic settings for the Personal CRM.</p>';
    }
}

new PersonalCrmTool();
