<?php
/**
 * Plugin Name: A8C Personal CRM
 * Description: WordPress-based personal CRM tool for managing contacts, teams, and relationships with extensible architecture
 * Version: 1.0.0
 * Author: Alex Kirk
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * License: MIT
 * Text Domain: a8c-personal-crm
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'A8C_CRM_PLUGIN_VERSION', '1.0.0' );
define( 'A8C_CRM_PLUGIN_FILE', __FILE__ );
define( 'A8C_CRM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'A8C_CRM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Include storage classes
require_once A8C_CRM_PLUGIN_DIR . 'includes/storage-factory.php';

// Load wp-app framework
require_once A8C_CRM_PLUGIN_DIR . 'vendor/autoload.php';
use WpApp\WpApp;

class A8cCrmTool {
    private $app;
    private $storage;

    public function __construct() {
        add_action( 'plugins_loaded', [ $this, 'init' ] );
        register_activation_hook( __FILE__, [ $this, 'activate' ] );
        register_deactivation_hook( __FILE__, [ $this, 'deactivate' ] );
    }

    public function init() {
        // Initialize storage (default to WpDB for WordPress integration)
        $storage_type = get_option( 'a8c_crm_storage_type', 'wpdb' );
        $this->storage = StorageFactory::create( $storage_type );

        // Add WP-CLI commands if WP-CLI is available
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            require_once A8C_CRM_PLUGIN_DIR . 'includes/wp-cli-commands.php';
            WP_CLI::add_command( 'crm migrate', 'A8C_CRM_Migrate_Command' );
        }

        // Initialize wp-app framework (use root directory as templates)
        $this->app = new WpApp(
            A8C_CRM_PLUGIN_DIR,
            'crm',
            [
                'show_masterbar_for_anonymous' => false,
                'show_wp_logo' => true,
                'show_site_name' => true,
                'require_capability' => 'read',  // Require login
                'clear_admin_bar' => false
            ]
        );

        // Setup routes
        $this->setup_routes();

        // Setup menu items
        $this->setup_menu();

        // Initialize the app
        $this->app->init();

        // Fire action to allow other plugins to register routes and extend functionality
        do_action( 'a8c_crm_app_created', $this->app );

        // Set up WordPress integration helpers
        add_action( 'wp_loaded', [ $this, 'setup_helpers' ] );

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

        // Finder/Search (finder.php)
        $this->app->route( 'finder', 'finder.php' );
        $this->app->route( 'search', 'finder.php' );

        // Person management (person.php)
        $this->app->route( 'person', 'person.php' );
        $this->app->route( 'person/{username}', 'person.php' );

        // Events (events.php)
        $this->app->route( 'events', 'events.php' );

        // Audit reports (audit.php)
        $this->app->route( 'audit', 'audit.php' );

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
            $this->app->add_menu_item( 'settings', 'Plugin Settings', admin_url( 'options-general.php?page=a8c-crm-settings' ) );
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
        $storage_type = get_option( 'a8c_crm_storage_type', 'wpdb' );
        if ( $storage_type === 'wpdb' ) {
            // WpDB storage will create tables automatically
            $storage = StorageFactory::create( 'wpdb' );
        }

        // Flush rewrite rules
        flush_rewrite_rules();

        // Set default options
        add_option( 'a8c_crm_storage_type', 'wpdb' );
        add_option( 'a8c_crm_default_team', '' );
        add_option( 'a8c_crm_version', A8C_CRM_PLUGIN_VERSION );
    }

    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    public function admin_menu() {
        add_options_page(
            'A8C Personal CRM Settings',
            'A8C Personal CRM',
            'manage_options',
            'a8c-crm-settings',
            [ $this, 'admin_page' ]
        );
    }

    public function admin_settings() {
        register_setting( 'a8c_crm_settings', 'a8c_crm_storage_type' );
        register_setting( 'a8c_crm_settings', 'a8c_crm_default_team' );

        add_settings_section(
            'a8c_crm_general',
            'General Settings',
            [ $this, 'settings_section_callback' ],
            'a8c_crm_settings'
        );

        add_settings_field(
            'storage_type',
            'Storage Type',
            [ $this, 'storage_type_callback' ],
            'a8c_crm_settings',
            'a8c_crm_general'
        );

        add_settings_field(
            'default_team',
            'Default Team',
            [ $this, 'default_team_callback' ],
            'a8c_crm_settings',
            'a8c_crm_general'
        );
    }

    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>A8C Personal CRM Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'a8c_crm_settings' );
                do_settings_sections( 'a8c_crm_settings' );
                submit_button();
                ?>
            </form>

            <hr>

            <h2>Migration Tools</h2>
            <p>Need to migrate data between storage systems? Access the migration script directly via command line:</p>
            <p><code>php <?php echo A8C_CRM_PLUGIN_DIR; ?>migrate.php --help</code></p>
        </div>
        <?php
    }

    public function settings_section_callback() {
        echo '<p>Configure the basic settings for the A8C Personal CRM.</p>';
    }

    public function storage_type_callback() {
        $storage_type = get_option( 'a8c_crm_storage_type', 'wpdb' );
        $types = StorageFactory::get_available_types();
        ?>
        <select name="a8c_crm_storage_type" id="storage_type">
            <?php foreach ( $types as $type ) : ?>
                <option value="<?php echo esc_attr( $type ); ?>" <?php selected( $storage_type, $type ); ?>>
                    <?php echo esc_html( ucfirst( $type ) ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">Choose the storage backend for HR data.</p>
        <?php
    }

    public function default_team_callback() {
        $default_team = get_option( 'a8c_crm_default_team', '' );
        $teams = $this->storage ? $this->storage->get_available_teams() : [];
        ?>
        <select name="a8c_crm_default_team" id="default_team">
            <option value="">-- Select Default Team --</option>
            <?php foreach ( $teams as $team_slug ) : ?>
                <option value="<?php echo esc_attr( $team_slug ); ?>" <?php selected( $default_team, $team_slug ); ?>>
                    <?php echo esc_html( $this->storage->get_team_name( $team_slug ) ?: $team_slug ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">Default team to show when accessing the CRM tool.</p>
        <?php
    }

    /**
     * Get storage instance
     */
    public function get_storage() {
        return $this->storage;
    }

    /**
     * Get app instance
     */
    public function get_app() {
        return $this->app;
    }

    /**
     * Set up WordPress integration helpers
     */
    public function setup_helpers() {
        // Set global variables that existing templates can use
        global $a8c_hr_plugin_storage;
        $a8c_hr_plugin_storage = $this->storage;

        // WordPress-compatible URL builder
        if ( ! function_exists( 'build_wp_plugin_url' ) ) {
            function build_wp_plugin_url( $page, $params = array() ) {
                // Convert .php file references to plugin routes
                $route = str_replace( '.php', '', $page );

                // Handle special cases
                if ( $route === 'index' || $route === '' ) {
                    $route = '';
                }

                $url = home_url( '/hr/' . ltrim( $route, '/' ) );

                if ( ! empty( $params ) ) {
                    $url .= '?' . http_build_query( $params );
                }

                return $url;
            }
        }
    }
}

// Initialize the plugin
new A8cCrmTool();

// Global helper function to get plugin instance
function a8c_crm_tool() {
    static $instance;
    if ( ! $instance ) {
        $instance = new A8cCrmTool();
    }
    return $instance;
}