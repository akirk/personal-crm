<?php
/**
 * Plugin Name: A8C HR Management Addon
 * Description: HR management functionality addon for A8C Personal CRM. Provides team management, HR feedback, and reporting features.
 * Version: 1.0.0
 * Author: Alex Kirk
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * License: MIT
 * Text Domain: a8c-hr-addon
 * Depends: A8C Personal CRM Plugin
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'A8C_HR_ADDON_VERSION', '1.0.0' );
define( 'A8C_HR_ADDON_FILE', __FILE__ );
define( 'A8C_HR_ADDON_DIR', plugin_dir_path( __FILE__ ) );
define( 'A8C_HR_ADDON_URL', plugin_dir_url( __FILE__ ) );

class A8cHrAddon {
    private $app;

    public function __construct() {
        add_action( 'plugins_loaded', [ $this, 'init' ] );
        register_activation_hook( __FILE__, [ $this, 'activate' ] );
        register_deactivation_hook( __FILE__, [ $this, 'deactivate' ] );
    }

    public function init() {
        // Check if the main CRM plugin is active
        if ( ! $this->is_main_plugin_active() ) {
            add_action( 'admin_notices', [ $this, 'missing_dependency_notice' ] );
            return;
        }

        // Hook into the main plugin's app creation
        add_action( 'a8c_crm_app_created', [ $this, 'register_hr_routes' ], 10, 1 );

        // Add WP-CLI commands if WP-CLI is available
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            require_once A8C_HR_ADDON_DIR . 'includes/wp-cli-commands.php';
            WP_CLI::add_command( 'hr migrate', 'A8C_HR_Migrate_Command' );
        }

        // Add admin settings page
        add_action( 'admin_menu', [ $this, 'admin_menu' ] );

        // Register settings
        add_action( 'admin_init', [ $this, 'admin_settings' ] );
    }

    /**
     * Check if the main CRM plugin is active and has the required hook
     */
    private function is_main_plugin_active() {
        return function_exists( 'a8c_crm_tool' ) || class_exists( 'A8cCrmTool' );
    }

    /**
     * Register HR-specific routes with the main app
     */
    public function register_hr_routes( $app ) {
        $this->app = $app;

        // HR-specific routes
        $this->app->route( 'hr-stats', A8C_HR_ADDON_DIR . 'templates/hr-stats.php' );
        $this->app->route( 'hr-reports', A8C_HR_ADDON_DIR . 'templates/hr-reports.php' );
        $this->app->route( 'hr-config', A8C_HR_ADDON_DIR . 'templates/hr-config.php' );

        // Add HR menu items for users with manage_options capability
        if ( current_user_can( 'manage_options' ) ) {
            $this->app->add_menu_item( 'hr-stats', 'HR Statistics', home_url( '/crm/hr-stats' ) );
            $this->app->add_menu_item( 'hr-reports', 'HR Reports', home_url( '/crm/hr-reports' ) );
            $this->app->add_menu_item( 'hr-config', 'HR Config', home_url( '/crm/hr-config' ) );
        }
    }

    public function activate() {
        // Flush rewrite rules
        flush_rewrite_rules();

        // Set default options
        add_option( 'a8c_hr_addon_version', A8C_HR_ADDON_VERSION );
        add_option( 'a8c_hr_default_team', '' );
    }

    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    public function missing_dependency_notice() {
        ?>
        <div class="notice notice-error">
            <p><strong>A8C HR Management Addon</strong> requires the A8C Personal CRM plugin to be installed and activated.</p>
        </div>
        <?php
    }

    public function admin_menu() {
        add_options_page(
            'A8C HR Addon Settings',
            'A8C HR Addon',
            'manage_options',
            'a8c-hr-addon-settings',
            [ $this, 'admin_page' ]
        );
    }

    public function admin_settings() {
        register_setting( 'a8c_hr_addon_settings', 'a8c_hr_default_team' );

        add_settings_section(
            'a8c_hr_addon_general',
            'HR Addon Settings',
            [ $this, 'settings_section_callback' ],
            'a8c_hr_addon_settings'
        );

        add_settings_field(
            'default_team',
            'Default Team',
            [ $this, 'default_team_callback' ],
            'a8c_hr_addon_settings',
            'a8c_hr_addon_general'
        );
    }

    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>A8C HR Addon Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'a8c_hr_addon_settings' );
                do_settings_sections( 'a8c_hr_addon_settings' );
                submit_button();
                ?>
            </form>

            <hr>

            <h2>HR Management Features</h2>
            <p>This addon provides the following HR management features:</p>
            <ul>
                <li><strong>Team Management:</strong> Manage team members and track team relationships</li>
                <li><strong>HR Feedback:</strong> Monthly feedback tracking and reporting</li>
                <li><strong>Statistics:</strong> Comprehensive HR statistics and analytics</li>
                <li><strong>Configuration:</strong> HR system configuration and settings</li>
            </ul>
        </div>
        <?php
    }

    public function settings_section_callback() {
        echo '<p>Configure the basic settings for the HR management addon.</p>';
    }

    public function default_team_callback() {
        $default_team = get_option( 'a8c_hr_default_team', '' );

        // Get available teams from the main plugin if possible
        $teams = [];
        if ( function_exists( 'a8c_crm_tool' ) ) {
            $crm_instance = a8c_crm_tool();
            $storage = $crm_instance->get_storage();
            if ( $storage ) {
                $teams = $storage->get_available_teams();
            }
        }

        ?>
        <select name="a8c_hr_default_team" id="default_team">
            <option value="">-- Select Default Team --</option>
            <?php foreach ( $teams as $team_slug ) : ?>
                <?php
                $team_name = $team_slug; // Default to slug if no name available
                if ( function_exists( 'a8c_crm_tool' ) ) {
                    $crm_instance = a8c_crm_tool();
                    $storage = $crm_instance->get_storage();
                    if ( $storage ) {
                        $team_name = $storage->get_team_name( $team_slug ) ?: $team_slug;
                    }
                }
                ?>
                <option value="<?php echo esc_attr( $team_slug ); ?>" <?php selected( $default_team, $team_slug ); ?>>
                    <?php echo esc_html( $team_name ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">Default team to show when accessing HR tools.</p>
        <?php
    }

    /**
     * Get the main app instance from the CRM plugin
     */
    public function get_app() {
        return $this->app;
    }
}

// Initialize the HR addon
new A8cHrAddon();

// Global helper function to get HR addon instance
function a8c_hr_addon() {
    static $instance;
    if ( ! $instance ) {
        $instance = new A8cHrAddon();
    }
    return $instance;
}