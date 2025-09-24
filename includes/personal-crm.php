<?php

namespace PersonalCRM;

class PersonalCrm {
    private static $instance = null;
    private static $storage_type = null;
    private $app;
    private $storage;

    public static function set_storage_type( $storage_type ) {
        self::$storage_type = $storage_type;
    }

    public static function get_instance() {
        if ( self::$instance === null ) {
            if ( ! self::$storage_type ) {
                throw new \Exception( 'Please set a storage before initializing' );
            }
            self::$instance = new self();
        }
        return self::$instance;
    }



    public function __construct() {
        register_activation_hook( PERSONAL_CRM_PLUGIN_FILE, [ $this, 'activate' ] );
        register_deactivation_hook( PERSONAL_CRM_PLUGIN_FILE, [ $this, 'deactivate' ] );

        $this->storage = StorageFactory::create( self::$storage_type );

        if ( class_exists( '\WP_CLI' ) ) {
            require_once __DIR__ . '/wp-cli-commands.php';
            \WP_CLI::add_command( 'crm migrate', 'Personal_CRM_Migrate_Command' );
        }

        $this->app = new \WpApp\WpApp(
            __DIR__ . '/../',
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

        wp_app_enqueue_style( 'personal-crm-style', plugin_dir_url( PERSONAL_CRM_PLUGIN_FILE ) . 'assets/style.css' );
        wp_app_enqueue_style( 'personal-crm-cmd-k', plugin_dir_url( PERSONAL_CRM_PLUGIN_FILE ) . 'assets/cmd-k.css' );
        wp_app_enqueue_script( 'personal-crm-cmd-k', plugin_dir_url( PERSONAL_CRM_PLUGIN_FILE ) . 'assets/cmd-k.js', [ 'jquery' ], '1.0', true );
        wp_app_enqueue_script( 'personal-crm-script', plugin_dir_url( PERSONAL_CRM_PLUGIN_FILE ) . 'assets/script.js', [ 'jquery' ], '1.0', true );

        // Fire action to allow other plugins to register routes and extend functionality
        do_action( 'personal_crm_loaded', $this );

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

    public static function get_globals() {
        $crm = self::get_instance();
        $current_team = $crm->get_current_team_from_params();

        if ( ! $current_team ) {
            $current_team = $crm->use_default_team();
            $available_teams = $crm->get_available_teams();
            if ( count( $available_teams ) > 1 && ! $current_team ) {
                header( 'Location: ' . $crm->build_url( 'select.php' ) );
                exit;
            }
        }

        $group = $crm->is_social_group( $current_team ) ? 'group' : 'team';
        $privacy_mode = isset( $_GET['privacy'] ) && $_GET['privacy'] === '1';

        // Load team configuration with Person objects
        $team_data = $crm->load_team_config_with_objects( $current_team );

        // Ensure all expected sections exist as arrays
        $expected_sections = array( 'team_members', 'leadership', 'consultants', 'alumni' );
        foreach ( $expected_sections as $section ) {
            if ( ! isset( $team_data[$section] ) || ! is_array( $team_data[$section] ) ) {
                $team_data[$section] = array();
            }
        }

        // Separate deceased people from their original sections
        $deceased_people = array();
        foreach ( $expected_sections as $section ) {
            foreach ( $team_data[$section] as $username => $person ) {
                if ( ! empty( $person->deceased ) ) {
                    $deceased_people[$username] = $person;
                    unset( $team_data[$section][$username] );
                }
            }
        }
        $team_data['deceased'] = $deceased_people;
        $available_teams = $crm->get_available_teams();

        return compact( 'crm', 'common', 'current_team', 'group', 'privacy_mode', 'team_data', 'available_teams' );
    }

    /**
     * Check if a team is configured as a social group
     */
    public function is_social_group( $team_slug ) {
        if ( empty( $team_slug ) ) {
            return false;
        }
        return $this->get_team_type_from_storage( $team_slug ) === 'group';
    }

    /**
     * Security: Only allow access from localhost
     */
    private function only_allow_access_from_localhost() {
        // Allow CLI access (when running from command line)
        if ( php_sapi_name() === 'cli' ) {
            return;
        }

        $remote_addr = $_SERVER['REMOTE_ADDR'] ?? '';
        $allowed_ips = array( '127.0.0.1', '::1' );

        // Check for localhost access
        if ( ! in_array( $remote_addr, $allowed_ips, true ) ) {
            // Also check HTTP_HOST for local development environments
            $http_host = $_SERVER['HTTP_HOST'] ?? '';
            if ( ! preg_match( '/^(localhost|127\.0\.0\.1)(:[0-9]+)?$/', $http_host ) ) {
                http_response_code( 403 );
                header( 'Content-Type: text/plain' );
                exit;
            }
        }
    }

    /**
     * Get SVG icon for a link based on its text or URL
     */
    public function get_link_icon( $link_text, $link_url, $size = 16 ) {
        if ( 0 === strpos( $link_url, 'https://linear.app/' ) ) {
            return '<svg xmlns="http://www.w3.org/2000/svg" fill="none" width="' . $size . '" height="' . $size . '" viewBox="0 0 100 100" style="vertical-align: middle; margin-bottom: 4px; margin-right: 4px"><path fill="currentColor" d="M1.22541 61.5228c-.2225-.9485.90748-1.5459 1.59638-.857L39.3342 97.1782c.6889.6889.0915 1.8189-.857 1.5964C20.0515 94.4522 5.54779 79.9485 1.22541 61.5228ZM.00189135 46.8891c-.01764375.2833.08887215.5599.28957165.7606L52.3503 99.7085c.2007.2007.4773.3075.7606.2896 2.3692-.1476 4.6938-.46 6.9624-.9259.7645-.157 1.0301-1.0963.4782-1.6481L2.57595 39.4485c-.55186-.5519-1.49117-.2863-1.648174.4782-.465915 2.2686-.77832 4.5932-.92588465 6.9624ZM4.21093 29.7054c-.16649.3738-.08169.8106.20765 1.1l64.77602 64.776c.2894.2894.7262.3742 1.1.2077 1.7861-.7956 3.5171-1.6927 5.1855-2.684.5521-.328.6373-1.0867.1832-1.5407L8.43566 24.3367c-.45409-.4541-1.21271-.3689-1.54074.1832-.99132 1.6684-1.88843 3.3994-2.68399 5.1855ZM12.6587 18.074c-.3701-.3701-.393-.9637-.0443-1.3541C21.7795 6.45931 35.1114 0 49.9519 0 77.5927 0 100 22.4073 100 50.0481c0 14.8405-6.4593 28.1724-16.7199 37.3375-.3903.3487-.984.3258-1.3542-.0443L12.6587 18.074Z"/></svg> ';
        } elseif ( $link_text === '1:1 doc' ) {
            return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="currentColor" style="vertical-align: middle; margin-bottom: 2px; margin-right: 4px"><path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/><circle cx="9" cy="13" r="1.5"/><circle cx="15" cy="13" r="1.5"/><path d="M9,16H15V18H9V16Z"/></svg>';
        } elseif ( $link_text === 'HR monthly' ) {
            return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="currentColor" style="vertical-align: middle; margin-right: 4px"><path d="M19,3H18V1H16V3H8V1H6V3H5A2,2 0 0,0 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19V5A2,2 0 0,0 19,3M19,19H5V8H19V19M5,6V5H19V6H5Z"/><rect x="7" y="10" width="2" height="2"/><rect x="11" y="10" width="2" height="2"/><rect x="15" y="10" width="2" height="2"/><rect x="7" y="14" width="2" height="2"/><rect x="11" y="14" width="2" height="2"/></svg>';
        } elseif ( $link_text === 'WordPress.org' ) {
            return '<svg fill="currentColor" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"  width="' . $size . '" height="' . $size . '"  viewBox="0 0 512 512" enable-background="new 0 0 512 512" xml:space="preserve" style="vertical-align: middle; margin-bottom: 4px; margin-right: 4px"><g id="5151e0c8492e5103c096af88a51f5fb6"><path display="inline" d="M256,0.5C115.117,0.5,0.5,115.109,0.5,255.992C0.5,396.874,115.117,511.5,256,511.5
                c140.879,0,255.5-114.626,255.5-255.508C511.5,115.109,396.879,0.5,256,0.5z M26.287,255.992c0-33.306,7.145-64.923,19.89-93.488
                l109.582,300.225C79.117,425.502,26.287,346.914,26.287,255.992z M256,485.722c-22.547,0-44.309-3.307-64.898-9.361l68.932-200.274
                l70.604,193.446c0.466,1.135,1.035,2.179,1.646,3.165C308.406,481.102,282.748,485.722,256,485.722z M287.659,148.286
                c13.827-0.724,26.29-2.179,26.29-2.179c12.376-1.464,10.916-19.658-1.468-18.93c0,0-37.207,2.919-61.23,2.919
                c-22.568,0-60.494-2.919-60.494-2.919c-12.388-0.728-13.839,18.198-1.456,18.93c0,0,11.715,1.455,24.095,2.179l35.784,98.063
                l-50.277,150.767l-83.649-248.83c13.84-0.724,26.286-2.179,26.286-2.179c12.372-1.464,10.912-19.658-1.468-18.93
                c0,0-37.198,2.919-61.222,2.919c-4.309,0-9.386-0.108-14.784-0.283C105.141,67.457,175.745,26.274,256,26.274
                c59.8,0,114.251,22.868,155.121,60.315c-0.989-0.058-1.958-0.183-2.978-0.183c-22.563,0-38.574,19.653-38.574,40.771
                c0,18.93,10.92,34.948,22.564,53.874c8.737,15.299,18.938,34.953,18.938,63.355c0,19.657-7.56,42.475-17.479,74.259l-22.917,76.554
                L287.659,148.286z M371.486,454.545l70.163-202.861c13.104-32.77,17.47-58.977,17.47-82.272c0-8.458-0.558-16.31-1.547-23.625
                c17.932,32.715,28.137,70.262,28.137,110.205C485.709,340.738,439.782,414.727,371.486,454.545z"></path></g></svg>';
        } elseif ( $link_text === 'LinkedIn' ) {
            return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="currentColor" style="vertical-align: middle; margin-right: 4px"><path d="M19 3A2 2 0 0 1 21 5V19A2 2 0 0 1 19 21H5A2 2 0 0 1 3 19V5A2 2 0 0 1 5 3H19M18.5 18.5V13.2A3.26 3.26 0 0 0 15.24 9.94C14.39 9.94 13.4 10.46 12.92 11.24V10.13H10.13V18.5H12.92V13.57C12.92 12.8 13.54 12.17 14.31 12.17A1.4 1.4 0 0 1 15.71 13.57V18.5H18.5M6.88 8.56A1.68 1.68 0 0 0 8.56 6.88C8.56 5.95 7.81 5.19 6.88 5.19S5.19 5.95 5.19 6.88A1.69 1.69 0 0 0 6.88 8.56M8.27 18.5V10.13H5.5V18.5H8.27Z"/></svg>';
        } elseif ( $link_text === 'GitHub' ) {
            return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="currentColor" style="vertical-align: middle; margin-right: 4px"><rect width="24" height="24" fill="none"/><path d="M12,2A10,10,0,0,0,8.84,21.5c.5.08.66-.23.66-.5V19.31C6.73,19.91,6.14,18,6.14,18A2.69,2.69,0,0,0,5,16.5c-.91-.62.07-.6.07-.6a2.1,2.1,0,0,1,1.53,1,2.15,2.15,0,0,0,2.91.83,2.16,2.16,0,0,1,.63-1.34C8,16.17,5.62,15.31,5.62,11.5a3.87,3.87,0,0,1,1-2.71,3.58,3.58,0,0,1,.1-2.64s.84-.27,2.75,1a9.63,9.63,0,0,1,5,0c1.91-1.29,2.75-1,2.75-1a3.58,3.58,0,0,1,.1,2.64,3.87,3.87,0,0,1,1,2.71c0,3.82-2.34,4.66-4.57,4.91a2.39,2.39,0,0,1,.69,1.85V21c0,.27.16.59.67.5A10,10,0,0,0,12,2Z"/></svg>';
        }
        return '';
    }

    /**
     * Render person links with icons
     */
    public function render_person_links( $links, $icon_size = 12 ) {
        foreach ( $links as $link_text => $link_url ) {
            if ( ! empty( $link_url ) && ! in_array( $link_text, array( 'WordPress.org', 'LinkedIn', 'Matticspace' )) ) {
                echo '<a href="' . esc_url( $link_url ) . '" target="_blank">';
                echo $this->get_link_icon( $link_text, $link_url, $icon_size );
                echo esc_html( $link_text );
                echo '</a>';
            }
        }
    }

    /**
     * Get all available teams
     */
    public function get_available_teams() {
        return $this->storage->get_available_teams();
    }

    /**
     * Get team name from storage
     */
    public function get_team_name_from_file( $team_slug ) {
        $name = $this->storage->get_team_name( $team_slug );
        return $name ?: ucfirst( str_replace( '_', ' ', $team_slug ) );
    }

    /**
     * Get team name from storage (alias for backward compatibility)
     */
    public function get_team_name_from_storage( $team_slug ) {
        return $this->get_team_name_from_file( $team_slug );
    }

    /**
     * Get team type from storage (defaults to 'team')
     */
    public function get_team_type_from_file( $team_slug ) {
        return $this->storage->get_team_type( $team_slug );
    }

    /**
     * Get team type from storage (alias for backward compatibility)
     */
    public function get_team_type_from_storage( $team_slug ) {
        return $this->get_team_type_from_file( $team_slug );
    }

    /**
     * Get people count from team config file
     */
    public function get_team_people_count( $team_slug ) {
        return $this->storage->get_team_people_count( $team_slug );
    }

    /**
     * Get all people names from team config file for search purposes
     */
    public function get_team_people_names( $team_slug ) {
        return $this->storage->get_team_people_names( $team_slug );
    }

    /**
     * Get all people data (username => person data) from team config file for search purposes
     */
    public function get_team_people_data( $team_slug ) {
        return $this->storage->get_team_people_data( $team_slug );
    }

    /**
     * Get display word for team type ('team' -> 'team', 'group' -> 'group')
     */
    public function get_type_display_word( $team_slug ) {
        $type = $this->get_team_type_from_file( $team_slug );
        return ( $type === 'group' ) ? 'group' : 'team';
    }

    /**
     * Get display title with appropriate type word
     */
    public function get_team_display_title( $team_slug, $suffix = '' ) {
        $team_name = $this->get_team_name_from_file( $team_slug );

        if ( empty( $suffix ) ) {
            return $team_name . ' ' . ucfirst( $this->group );
        }

        return $team_name . ' ' . ucfirst( $this->group ) . ' ' . $suffix;
    }

    /**
     * Get current team slug from URL parameters, treating 'group' as synonym for 'team'
     */
    public function get_current_team_from_params() {
        // Check both 'team' and 'group' parameters as synonyms
        $team_param = $_POST['team'] ?? $_GET['team'] ?? null;
        $group_param = $_POST['group'] ?? $_GET['group'] ?? null;

        // Check wp-app route parameters using WordPress query vars
        if ( empty( $team_param ) && function_exists( '\get_query_var' ) ) {
            $team_param = \get_query_var( 'team' );
        }

        $this->current_team = $group_param ?? $team_param ?? null;

        $this->group = $this->get_type_display_word( $this->current_team ); // 'team' or 'group'
        return $this->current_team;
    }
    /**
     * Privacy mode helper functions
     */
    public function mask_name( $full_name, $privacy_mode ) {
        if ( ! $privacy_mode ) {
            return $full_name;
        }

        $parts = explode( ' ', trim( $full_name ) );
        if ( count( $parts ) <= 1 ) {
            return $full_name; // Only first name, no masking needed
        }

        // Return first name + masked last name
        $first_name = $parts[0];
        $last_name_initial = isset( $parts[ count( $parts ) - 1 ] ) ? substr( $parts[ count( $parts ) - 1 ], 0, 1 ) . '.' : '';

        return $first_name . ' ' . $last_name_initial;
    }

    public function mask_username( $username, $privacy_mode ) {
        if ( ! $privacy_mode ) {
            return $username;
        }

        if ( strlen( $username ) <= 3 ) {
            return $username; // Too short to mask meaningfully
        }

        return substr( $username, 0, 3 ) . '...';
    }

    public function mask_date( $date, $privacy_mode, $show_year = false ) {
        if ( ! $privacy_mode || empty( $date ) ) {
            return $date;
        }

        if ( $show_year ) {
            // For birthdays, show just the year
            if ( preg_match( '/^(\d{4})-\d{2}-\d{2}$/', $date, $matches ) ) {
                return $matches[1];
            }
        }

        return '****-**-**';
    }

    /**
     * Get the default team slug (team marked with default: true)
     */
    public function get_default_team() {
        return $this->storage->get_default_team();
    }

    public function use_default_team() {
        $this->current_team = $this->get_default_team();
        return $this->current_team;
    }

    /**
     * Create backup of existing configuration file (max one per minute)
     */
    public function create_backup( $file_path ) {
        if ( ! file_exists( $file_path ) ) {
            return true; // No file to backup
        }
        // Create backups directory if it doesn't exist
        $backups_dir = dirname( $file_path ) . '/backups';
        if ( ! file_exists( $backups_dir ) ) {
            mkdir( $backups_dir, 0755, true );
        }
        // Generate backup filename in backups directory (minute precision only)
        $filename = basename( $file_path );
        $backup_timestamp = date( '-Y-m-d-H-i' ); // No seconds - only minute precision
        $backup_filename = substr( $filename, 0, -4 ) . 'bak' . $backup_timestamp . '.json';
        $backup_path = $backups_dir . '/' . $backup_filename;

        // Only create backup if one doesn't already exist for this minute
        if ( file_exists( $backup_path ) ) {
            return true; // Backup for this minute already exists
        }

        return copy( $file_path, $backup_path );
    }

    /**
     * Load team configuration from storage and convert to Person objects
     */
    public function load_team_config_with_objects( $team_slug = 'team' ) {
        if ( ! $this->storage->team_exists( $team_slug ) ) {
            // Redirect to team creation page
            $create_team_url = $this->$crm->build_url( 'admin.php', array( 'create_team' => 'new' ) );
            header( 'Location: ' . $create_team_url );
            exit;
        }

        $config = $this->storage->get_team_config( $team_slug );

        if ( ! $config ) {
            die( 'Error: Unable to load team configuration' );
        }

        // Convert arrays to Person objects
        $team_members = array();
        foreach ( $config['team_members'] as $username => $member_data ) {
            $team_members[$username] = $this->create_person_from_data( $username, $member_data );
        }

        $leadership = array();
        foreach ( $config['leadership'] as $username => $leader_data ) {
            $leadership[$username] = $this->create_person_from_data( $username, $leader_data );
        }

        $consultants = array();
        foreach ( $config['consultants'] ?? array() as $username => $consultant_data ) {
            $consultants[$username] = $this->create_person_from_data( $username, $consultant_data );
        }

        $alumni = array();
        foreach ( $config['alumni'] ?? array() as $username => $alumni_data ) {
            $alumni[$username] = $this->create_person_from_data( $username, $alumni_data );
        }

        // Sort all collections by name
        uasort( $team_members, function( $a, $b ) {
            return strcasecmp( $a->name, $b->name );
        } );

        uasort( $leadership, function( $a, $b ) {
            return strcasecmp( $a->name, $b->name );
        } );

        uasort( $consultants, function( $a, $b ) {
            return strcasecmp( $a->name, $b->name );
        } );

        uasort( $alumni, function( $a, $b ) {
            return strcasecmp( $a->name, $b->name );
        } );

        // Convert team events to Event objects
        $events = array();
        foreach ( $config['events'] ?? array() as $event_data ) {
            $events[] = Event::from_team_event( $event_data );
        }

        return array(
            'activity_url_prefix' => $config['activity_url_prefix'],
            'team_name' => $config['team_name'],
            'not_managing_team' => $config['not_managing_team'] ?? true,
            'team_links' => $config['team_links'] ?? array(),
            'team_members' => $team_members,
            'leadership' => $leadership,
            'consultants' => $consultants,
            'alumni' => $alumni,
            'events' => $events,
        );
    }

    /**
     * Create Person object from person data array
     */
    public function create_person_from_data( $username, $person_data ) {
        // Handle migration from old format to new format
        $links = array();
        if ( isset( $person_data['links'] ) ) {
            // New format - use links directly
            $links = $person_data['links'];
        } else {
            // Old format - migrate one_on_one and hr_feedback to links
            if ( ! empty( $person_data['one_on_one'] ) ) {
                $links['1:1 doc'] = $person_data['one_on_one'];
            }
            if ( ! empty( $person_data['hr_feedback'] ) ) {
                $links['HR monthly'] = $person_data['hr_feedback'];
            }
        }

        if ( isset( $person_data['linear'] ) && ! empty( $person_data['linear'] ) ) {
            $links['Linear'] = 'https://linear.app/a8c/profiles/' . $person_data['linear'];
        }

        if ( isset( $person_data['wordpress'] ) && ! empty( $person_data['wordpress'] ) ) {
            $links['WordPress.org'] = 'https://profiles.wordpress.org/' . $person_data['wordpress'];
        }

        if ( isset( $person_data['linkedin'] ) && ! empty( $person_data['linkedin'] ) ) {
            $links['LinkedIn'] = 'https://linkedin.com/in/' . $person_data['linkedin'];
        }

        $person = new Person(
            $person_data['name'],
            $username,
            $links,
            $person_data['role'] ?? ''
        );

        // Set properties with empty string defaults
        $string_properties = array( 'email', 'birthday', 'company_anniversary', 'partner', 'partner_birthday', 'timezone', 'github', 'wordpress', 'linkedin', 'website', 'new_company', 'new_company_website', 'deceased_date' );
        foreach ( $string_properties as $property ) {
            $person->$property = $person_data[$property] ?? '';
        }

        // Set properties with array defaults
        $array_properties = array( 'kids', 'github_repos', 'personal_events', 'notes' );
        foreach ( $array_properties as $property ) {
            $person->$property = $person_data[$property] ?? array();
        }

        // Special cases
        $person->nickname = $person_data['nickname'] ?? '';
        $person->location = $person_data['location'] ?? $person_data['town'] ?? ''; // Support both 'location' and legacy 'town'
        $person->left_company = $person_data['left_company'] ?? 0;
        $person->deceased = $person_data['deceased'] ?? 0;

        return $person;
    }

    /**
     * Render upcoming events sidebar section
     *
     * @param array $team_data Team data containing people and events
     * @param int $days_ahead Number of days to look ahead (default: 90 for team page, 365 for person page)
     * @param string $filter_person Show events only for this person (null for all people)
     * @param bool $include_team_events Whether to include team-wide events
     */
    public function render_upcoming_events_sidebar( $team_data, $days_ahead = 90, $filter_person = null, $include_team_events = true ) {
        $current_date = new \DateTime();
        $cutoff_date = clone $current_date;
        $cutoff_date->add( new \DateInterval( 'P' . $days_ahead . 'D' ) );

        $all_events = array();

        // Get all people from team data
        $all_people = array();
        if ( isset( $team_data['team_members'] ) ) {
            $all_people = array_merge( $all_people, $team_data['team_members'] );
        }
        if ( isset( $team_data['leadership'] ) ) {
            $all_people = array_merge( $all_people, $team_data['leadership'] );
        }
        if ( isset( $team_data['consultants'] ) ) {
            $all_people = array_merge( $all_people, $team_data['consultants'] );
        }

        // Get personal events from people (filter if specified)
        foreach ( $all_people as $person ) {
            if ( is_object( $person ) && method_exists( $person, 'get_upcoming_events' ) ) {
                error_log( 'DEBUG: CrmCore - Checking person: ' . $person->username . ', filter_person: ' . ( $filter_person ?: 'null' ) );

                // Skip if filtering by person and this isn't the person
                if ( $filter_person && $person->username !== $filter_person ) {
                    error_log( 'DEBUG: CrmCore - Skipping ' . $person->username . ' (not matching ' . $filter_person . ')' );
                    continue;
                }

                $personal_events = $person->get_upcoming_events();
                error_log( 'DEBUG: CrmCore - Personal events for ' . $person->username . ': ' . count( $personal_events ) );
                $all_events = array_merge( $all_events, $personal_events );
            }
        }

        error_log( 'DEBUG: CrmCore - Total personal events collected: ' . count( $all_events ) );

        // Add team events (if enabled)
        if ( $include_team_events && isset( $team_data['events'] ) ) {
            foreach ( $team_data['events'] as $event ) {
                if ( $event->date >= $current_date && $event->date <= $cutoff_date ) {
                    $all_events[] = $event;
                    error_log( 'DEBUG: CrmCore - Including team event: ' . $event->get_title() );
                }
            }
        }

        error_log( 'DEBUG: CrmCore - Total events after adding team events: ' . count( $all_events ) );

        // Sort all events by date
        usort( $all_events, function( $a, $b ) {
            return $a->date <=> $b->date;
        } );

        // Filter to upcoming events within 30 days
        $upcoming_events = array();
        foreach ( $all_events as $event ) {
            if ( $event->date >= $current_date && $event->date <= $cutoff_date ) {
                $upcoming_events[] = $event;
                error_log( 'DEBUG: CrmCore - Including upcoming event: ' . $event->get_title() . ' on ' . $event->date->format('Y-m-d') );
            } else {
                error_log( 'DEBUG: CrmCore - Excluding event: ' . $event->get_title() . ' on ' . $event->date->format('Y-m-d') . ' (outside 30-day window)' );
            }
        }

        error_log( 'DEBUG: CrmCore - Final upcoming events count: ' . count( $upcoming_events ) );

        $privacy_mode = isset( $_GET['privacy'] ) && $_GET['privacy'] === '1';

        error_log( 'DEBUG: CrmCore - Privacy mode: ' . ( $privacy_mode ? 'true' : 'false' ) );
        error_log( 'DEBUG: CrmCore - About to render events, count: ' . count( $upcoming_events ) );

        if ( ! empty( $upcoming_events ) ) {
            foreach ( $upcoming_events as $event ) {
                $formatted_date = $privacy_mode ? '[Hidden]' : $event->date->format( 'M j, Y' );
                $days_until = $current_date->diff( $event->date )->days;
                $is_past = $event->date < $current_date;
                ?>
                <div class="event-item">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                        <div class="event-date"><?php echo esc_html( $formatted_date ); ?></div>
                        <?php if ( ! $is_past && $days_until <= 120 ) : // Show for events within 120 days ?>
                            <div style="font-size: 11px; color: #999; font-weight: normal;">
                                <?php
                                if ( $privacy_mode ) {
                                    if ( $days_until == 0 ) {
                                        echo 'today';
                                    } else {
                                        echo 'in x days';
                                    }
                                } else {
                                    if ( $days_until == 0 ) {
                                        echo 'today';
                                    } elseif ( $days_until == 1 ) {
                                        echo 'in 1d';
                                    } elseif ( $days_until > 60 ) {
                                        echo 'in ' . floor( $days_until / 30 ) . 'mo';
                                    } else {
                                        echo 'in ' . $days_until . 'd';
                                    }
                                }
                                ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="event-description"><?php
                        // Get the event title
                        $description = $event->get_title();

                        // Apply privacy masking to description if needed
                        if ( $privacy_mode ) {
                            // Replace age numbers with [Hidden]
                            $description = preg_replace( '/\d+(st|nd|rd|th)/', '[Hidden]', $description );
                        }

                        // If filtering by person (on person page), don't show person name
                        if ( $filter_person && $event->person && $event->person->username === $filter_person ) {
                            // Remove person's name from the description since we're on their page
                            $person_name = $event->person->name;
                            $description = str_replace( $person_name . "'s ", '', $description );
                            $description = str_replace( $person_name . ' ', '', $description );
                            echo esc_html( $description );
                        } elseif ( $event->person && ! $filter_person ) {
                            // Show clickable person name for team pages (but apply privacy masking to names)
                            $person_name = $privacy_mode ? '[Hidden]' : $event->person->name;
                            $person_username = $event->person->username;
                            $person_link = $this->build_url( 'person.php', array( 'person' => $person_username ) );

                            // Replace person name with clickable link in the description
                            $clickable_name = '<a href="' . esc_url( $person_link ) . '" class="event-person-link">' . esc_html( $person_name ) . '</a>';
                            $description = str_replace( esc_html( $event->person->name ), $clickable_name, esc_html( $description ) );
                            echo $description;
                        } else {
                            echo esc_html( $description );
                        }
                    ?></div>
                    <span class="event-type <?php echo esc_attr( $event->type ); ?>"><?php echo $event->type === 'partner_birthday' ? 'Birthday' : ucfirst( $event->type ); ?></span>
                    <?php if ( ! empty( $event->location ) ) : ?>
                        <div class="event-location-small">📍 <a href="https://maps.google.com/maps?q=<?php echo urlencode( $event->location ); ?>" target="_blank" class="location-link"><?php echo esc_html( $event->location ); ?></a></div>
                    <?php endif; ?>
                    <?php if ( ! empty( $event->links ) ) : ?>
                        <div class="event-links-small">
                            <?php foreach ( $event->links as $link_text => $link_url ) : ?>
                                <a href="<?php echo esc_url( $link_url ); ?>" target="_blank" class="event-link-small">
                                    <?php echo esc_html( $link_text ); ?> →
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php
            }
        } else {
            echo '<p style="color: #666; font-style: italic; margin: 0;">No upcoming events</p>';
        }
    }

    /**
     * Build URL with team parameter (uses 'group' parameter for group-type teams)
     */
    public function build_url( $base_url, $additional_params = array() ) {
        // If running as WordPress plugin, use WordPress URL structure
        if ( defined( 'WPINC' ) ) {
            // Convert .php file references to plugin routes
            $route = str_replace( '.php', '', $base_url );

            // Handle special cases
            if ( $route === 'index' || $route === '' || $route === './' ) {
                $route = '';
            }

            // Special handling for person URLs - use {team}/{person} format
            if ( $route === 'person' && isset( $additional_params['person'] ) ) {
                $username = $additional_params['person'];
                unset( $additional_params['person'] ); // Remove from query params

                if ( isset( $additional_params['team'] ) ) {
                    $team = $additional_params['team'];
                    unset( $additional_params['team'] ); // Remove team param if present
                } else {
                    $team = $this->current_team;
                }

                $url = home_url( '/crm/' . $team . '/' . $username );
            } elseif ( $route === 'admin' ) {
                $username = '';
                if ( isset( $additional_params['person'] ) ) {
                    $username = $additional_params['person'];
                    unset( $additional_params['person'] ); // Remove from query params
                }
                if ( isset( $additional_params['team'] ) ) {
                    $team = $additional_params['team'];
                    unset( $additional_params['team'] ); // Remove team param if present
                } else {
                    $team = $this->current_team;
                }

                $url = home_url( '/crm/admin/' . $team . '/' . $username );
            } else {
                $url = home_url( '/crm/' . ltrim( $route, '/' ) );
            }

            if ( ! empty( $additional_params ) ) {
                $url .= '?' . http_build_query( $additional_params );
            }

            return $url;
        }

        // Original standalone functionality
        $params = array();
        if ( $this->current_team !== 'team' ) {
            $team_type = $this->get_team_type_from_file( $this->current_team );
            $param_name = ( $team_type === 'group' ) ? 'group' : 'team';
            $params[ $param_name ] = $this->current_team;
        }
        $params = array_merge( $params, $additional_params );

        if ( ! empty( $params ) ) {
            return $base_url . '?' . http_build_query( $params );
        }
        return $base_url;
    }



    // Keep remaining essential functions that exist in the original file
    public function render_cmd_k_panel() {
        ?>
        <div id="cmd-k-overlay" class="cmd-k-overlay">
            <div class="cmd-k-panel">
                <div class="cmd-k-search-container">
                    <input type="text" id="cmd-k-search" class="cmd-k-search" placeholder="Search teams and people..." autocomplete="off" spellcheck="false">
                </div>
                <div id="cmd-k-results" class="cmd-k-results">
                </div>
                <div class="cmd-k-instructions">
                    <span class="cmd-k-kbd">↑↓</span> to navigate • <span class="cmd-k-kbd">Enter</span> to open • <span class="cmd-k-kbd">→</span> to select link • <span class="cmd-k-kbd">Esc</span> to close
                </div>
            </div>
        </div>
        <?php
    }

    public function init_cmd_k_js( $privacy_mode = false ) {
        $available_teams = $this->get_available_teams();
        $json_files = array();

        foreach ( $available_teams as $team_slug ) {
            $json_files[] = array(
                'slug' => $team_slug,
                'name' => $this->get_team_name_from_file( $team_slug )
            );
        }
        ?>
        <script>
            // Initialize Command-K with list of JSON files
            document.addEventListener('DOMContentLoaded', () => {
                if (typeof CmdK !== 'undefined') {
                    const jsonFiles = <?php echo json_encode( $json_files ); ?>;
                    const privacyMode = <?php echo $privacy_mode ? 'true' : 'false'; ?>;
                    CmdK.init(jsonFiles, privacyMode);
                }
            });
        </script>
        <?php
    }

    // Include all the remaining essential functions from the original common.php that are used throughout the system
    // These include event handling, privacy mode functions, etc.

    public function get_all_upcoming_events( $team_data ) {
        $all_events = array();
        $current_date = new \DateTime();
        $cutoff_date = clone $current_date;
        $cutoff_date->add( new \DateInterval( 'P3M' ) ); // 3 months from now

        // Check if alumni events should be included (undocumented parameter)
        $include_alumni = isset( $_GET['alumni'] ) && $_GET['alumni'] === '1';

        // Get personal events from team members, leadership, consultants, and optionally alumni
        $all_people = array_merge( $team_data['team_members'], $team_data['leadership'], $team_data['consultants'] ?? array() );
        if ( $include_alumni ) {
            $all_people = array_merge( $all_people, $team_data['alumni'] );
        }
        foreach ( $all_people as $person ) {
            $personal_events = $person->get_upcoming_events();
            $all_events = array_merge( $all_events, $personal_events );
        }

        // Add team and company events (within 3 months)
        foreach ( $team_data['events'] as $event ) {
            $start_date = \DateTime::createFromFormat( 'Y-m-d', $event->start_date );
            if ( $start_date && $start_date >= $current_date && $start_date <= $cutoff_date ) {
                $end_date = \DateTime::createFromFormat( 'Y-m-d', $event->end_date );
                $duration = '';
                if ( $end_date && $start_date->format( 'Y-m-d' ) !== $end_date->format( 'Y-m-d' ) ) {
                    $duration = ' - ' . $end_date->format( 'M j' );
                }

                $all_events[] = array(
                    'type' => $event->type,
                    'date' => $start_date,
                    'description' => $event->name . $duration,
                    'location' => $event->location ?? '',
                    'details' => $event->description ?? '',
                );
            }
        }

        // Sort all events by date
        usort( $all_events, function( $a, $b ) {
            return $a['date'] <=> $b['date'];
        } );

        return $all_events;
    }

}