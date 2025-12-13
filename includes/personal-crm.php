<?php

namespace PersonalCRM;

require_once __DIR__ . '/storage.php';

class PersonalCrm {
    private static $instance = null;
    private static $storage_instance = null;
    public $storage;
    public $app;
    private $current_group;
    private $group;

    public static function set_storage( $storage ) {
        self::$storage_instance = $storage;
    }

    public static function get_instance() {
        if ( self::$instance === null ) {
            if ( ! self::$storage_instance ) {
                throw new \Exception( 'Please set a storage instance before initializing' );
            }
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        $this->storage = self::$storage_instance;

        $this->app = new \WpApp\WpApp(
            __DIR__ . '/../',
            'crm',
            [
                'show_masterbar_for_anonymous' => false,
                'show_wp_logo' => true,
                'show_site_name' => true,
                'app_name' => 'Personal CRM',
                'require_capability' => 'read',  // Require login
                'clear_admin_bar' => false
            ]
        );

        // Note: Activation/deactivation hooks are registered at plugin file level
        // (personal-crm.php) because they fire BEFORE plugins_loaded

        if ( class_exists( '\WP_CLI' ) ) {
            require_once __DIR__ . '/wp-cli-commands.php';
            \WP_CLI::add_command( 'crm migrate', 'Personal_CRM_Migrate_Command' );
        }

        $this->setup_routes();
        $this->setup_menu();

        add_filter( 'personal_crm_sort_people', [ $this, 'default_sort_people' ], 5, 3 );
        add_filter( 'personal_crm_build_url_params', [ 'PersonalCRM\TimeTravel', 'add_to_urls' ], 10, 2 );

        // Only initialize wp-app if we're in WordPress environment or can provide required constants
        if ( defined( 'ABSPATH' ) ) {
            $this->app->init();
        } else {
            // In standalone mode, we need to simulate WordPress environment for wp-app
            // Define required constants for wp-app's DatabaseManager
            if ( ! defined( 'ABSPATH' ) ) {
                define( 'ABSPATH', __DIR__ . '/../../..' . '/' );
            }

            $this->app->init();
        }

        wp_app_enqueue_style( 'personal-crm-style', plugin_dir_url( PERSONAL_CRM_PLUGIN_FILE ) . 'assets/style.css' );
        wp_app_enqueue_style( 'personal-crm-cmd-k', plugin_dir_url( PERSONAL_CRM_PLUGIN_FILE ) . 'assets/cmd-k.css' );
        wp_app_enqueue_script( 'personal-crm-cmd-k', plugin_dir_url( PERSONAL_CRM_PLUGIN_FILE ) . 'assets/cmd-k.js', [ 'jquery' ], '1.0', true );
        wp_app_enqueue_script( 'personal-crm-script', plugin_dir_url( PERSONAL_CRM_PLUGIN_FILE ) . 'assets/script.js', [ 'jquery' ], '1.0', true );
        wp_app_enqueue_script( 'personal-crm-ollama', plugin_dir_url( PERSONAL_CRM_PLUGIN_FILE ) . 'assets/ollama.js', [], '1.0', true );

        // Fire action to allow other plugins to register routes and extend functionality
        do_action( 'personal_crm_loaded', $this );

        // Register AJAX endpoint for cmd-k data
        add_action( 'wp_ajax_personal_crm_get_person_details', [ $this, 'ajax_get_person_details' ] );
        add_action( 'wp_ajax_nopriv_personal_crm_get_person_details', [ $this, 'ajax_get_person_details' ] );

        // WordPress-specific functionality
        if ( defined( 'WPINC' ) ) {
            // Add admin settings page
            add_action( 'admin_menu', [ $this, 'admin_menu' ] );

            // Register settings
            add_action( 'admin_init', [ $this, 'admin_settings' ] );
        }
    }

    private function setup_routes() {
        // Group selector (index.php)
        // Default route handled automatically by wp-app

        // Admin interface (admin/index.php)
        $this->app->route( 'admin', 'admin/index.php' );
        $this->app->route( 'admin/group/{group}', 'admin/index.php' );
        $this->app->route( 'admin/group/{group}/links', 'admin/index.php' );
        $this->app->route( 'admin/group/{group}/members', 'admin/index.php' );
        $this->app->route( 'admin/group/{group}/leadership', 'admin/index.php' );
        $this->app->route( 'admin/group/{group}/consultants', 'admin/index.php' );
        $this->app->route( 'admin/group/{group}/alumni', 'admin/index.php' );
        $this->app->route( 'admin/group/{group}/events', 'admin/index.php' );
        $this->app->route( 'admin/group/{group}/audit', 'admin/index.php' );
        $this->app->route( 'admin/person/{person}', 'admin/index.php' );

        // Finder/Search (finder.php)
        $this->app->route( 'finder', 'finder.php' );
        $this->app->route( 'search', 'finder.php' );

        // Person management (person.php)
        $this->app->route( 'person/{person}', 'person.php' );
        $this->app->route( 'group/{group}/history', 'group-history.php' );
        $this->app->route( 'group/{group}', 'group.php' );

        // Events (events.php)
        $this->app->route( 'events', 'events.php' );

        // Audit reports (audit.php)
        $this->app->route( 'audit', 'audit.php' );

        // Import person (import-person.php)
        $this->app->route( 'import-person', 'import-person.php' );

        // Select interface (index.php)
        $this->app->route( 'select', 'index.php' );
    }

    private function setup_menu() {
        // Main navigation - Personal CRM focused
        $this->app->add_menu_item( 'dashboard', 'Dashboard', home_url( '/crm/' ) );
        $this->app->add_menu_item( 'person', 'People', home_url( '/crm/person' ) );
        $this->app->add_menu_item( 'events', 'Events', home_url( '/crm/events' ) );
        $this->app->add_menu_item( 'select', 'Select Group', home_url( '/crm/select' ) );

        // Admin menu items (only for administrators)
        if ( current_user_can( 'manage_options' ) ) {
            $this->app->add_menu_item( 'admin', 'Admin', home_url( '/crm/admin' ) );
            $this->app->add_menu_item( 'audit', 'Audit', home_url( '/crm/audit' ) );
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

    // Note: activate() and deactivate() methods have been moved to plugin file level
    // (personal-crm.php) as functions personal_crm_activate() and personal_crm_deactivate()
    // because activation hooks fire BEFORE plugins_loaded when this class is instantiated

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
        register_setting( 'personal_crm_settings', 'personal_crm_default_team' );
        register_setting( 'personal_crm_settings', 'personal_crm_ollama_model' );

        add_settings_section(
            'personal_crm_general',
            'General Settings',
            [ $this, 'settings_section_callback' ],
            'personal_crm_settings'
        );

        add_settings_field(
            'default_team',
            'Default Team',
            [ $this, 'default_team_callback' ],
            'personal_crm_settings',
            'personal_crm_general'
        );

        add_settings_section(
            'personal_crm_ollama',
            'Ollama AI Settings',
            [ $this, 'ollama_section_callback' ],
            'personal_crm_settings'
        );

        add_settings_field(
            'ollama_model',
            'Ollama Model',
            [ $this, 'ollama_model_callback' ],
            'personal_crm_settings',
            'personal_crm_ollama'
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
        </div>
        <?php
    }

    public function settings_section_callback() {
        echo '<p>Configure the basic settings for the Personal CRM.</p>';
    }

    public function default_team_callback() {
        $value = get_option( 'personal_crm_default_team', '' );
        $available_groups = $this->storage->get_available_groups();
        ?>
        <select name="personal_crm_default_team" id="personal_crm_default_team">
            <option value="">None</option>
            <?php foreach ( $available_groups as $group_slug ) : ?>
                <?php
                $group_obj = $this->storage->get_group( $group_slug );
                $display_name = $group_obj ? $group_obj->get_hierarchical_name() : ucfirst( str_replace( '_', ' ', $group_slug ) );
                ?>
                <option value="<?php echo esc_attr( $group_slug ); ?>" <?php selected( $value, $group_slug ); ?>>
                    <?php echo esc_html( $display_name ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">Select the default team to use when none is specified.</p>
        <?php
    }

    public function ollama_section_callback() {
        ?>
        <style>
            #ollama-status {
                padding: 12px;
                border-radius: 4px;
                margin-bottom: 15px;
                background: #f0f0f0;
            }
            #ollama-status.connected {
                background: #d4edda;
                border-left: 4px solid #28a745;
            }
            #ollama-status.error {
                background: #f8d7da;
                border-left: 4px solid #dc3545;
            }
            #ollama-instructions {
                display: none;
                padding: 15px;
                background: #fff8e5;
                border-left: 4px solid #ffb900;
                margin-bottom: 15px;
            }
            #ollama-instructions.visible {
                display: block;
            }
            #ollama-instructions ol {
                margin: 10px 0 0 20px;
            }
            #ollama-instructions code {
                background: #f5f5f5;
                padding: 2px 6px;
            }
            #ollama-instructions .hint {
                font-size: 12px;
                color: #666;
            }
            #ollama-models-list {
                margin-top: 10px;
            }
            .ollama-model-tag {
                display: inline-block;
                background: #f0f0f0;
                padding: 4px 8px;
                margin: 2px;
                border-radius: 3px;
                cursor: pointer;
            }
            .ollama-model-tag:hover {
                background: #0073aa;
                color: white;
            }
        </style>
        <p>Configure the Ollama AI integration for AI-powered features.</p>
        <div id="ollama-status">
            <span id="ollama-status-text">Checking Ollama connection...</span>
        </div>
        <div id="ollama-instructions">
            <strong>Ollama Setup Instructions:</strong>
            <ol>
                <li>Install Ollama from <a href="https://ollama.ai" target="_blank">ollama.ai</a></li>
                <li>Start Ollama (it runs on port 11434 by default)</li>
                <li>Allow browser access by setting the environment variable:<br>
                    <code>OLLAMA_ORIGINS=* ollama serve</code><br>
                    <span class="hint">Or add <code>OLLAMA_ORIGINS=*</code> to your environment before starting Ollama.</span>
                </li>
                <li>Pull a model: <code>ollama pull llama3.2</code></li>
            </ol>
        </div>
        <?php
    }

    public function ollama_model_callback() {
        $value = get_option( 'personal_crm_ollama_model', 'llama3.2' );
        ?>
        <input type="text" name="personal_crm_ollama_model" id="personal_crm_ollama_model" value="<?php echo esc_attr( $value ); ?>" class="regular-text">
        <div id="ollama-models-list"></div>
        <script>
        (async function checkOllamaAndLoadModels() {
            const statusDiv = document.getElementById('ollama-status');
            const statusText = document.getElementById('ollama-status-text');
            const instructions = document.getElementById('ollama-instructions');
            const modelsDiv = document.getElementById('ollama-models-list');
            const modelInput = document.getElementById('personal_crm_ollama_model');

            try {
                const response = await fetch('http://localhost:11434/api/tags');
                if (response.ok) {
                    const data = await response.json();
                    const modelCount = data.models ? data.models.length : 0;
                    statusDiv.className = 'connected';
                    statusText.textContent = '✓ Ollama is running (' + modelCount + ' model' + (modelCount !== 1 ? 's' : '') + ' available)';

                    if (data.models && data.models.length > 0) {
                        const label = document.createElement('strong');
                        label.textContent = 'Available models (click to select): ';
                        modelsDiv.appendChild(label);
                        data.models.forEach(model => {
                            const span = document.createElement('span');
                            span.className = 'ollama-model-tag';
                            span.textContent = model.name;
                            span.addEventListener('click', () => {
                                modelInput.value = model.name;
                            });
                            modelsDiv.appendChild(span);
                        });
                    } else {
                        const msg = document.createElement('em');
                        msg.textContent = 'No models installed. Run "ollama pull llama3.2" to install a model.';
                        modelsDiv.appendChild(msg);
                    }
                } else {
                    throw new Error('HTTP ' + response.status);
                }
            } catch (error) {
                statusDiv.className = 'error';
                if (error.message.includes('Failed to fetch') || error.message.includes('NetworkError')) {
                    statusText.textContent = '✗ Cannot connect to Ollama - it may not be running or browser access is blocked (CORS)';
                } else {
                    statusText.textContent = '✗ Cannot connect to Ollama: ' + error.message;
                }
                instructions.className = 'visible';
            }
        })();
        </script>
        <?php
    }

    /**
     * Get the configured Ollama model
     */
    public static function get_ollama_model() {
        return get_option( 'personal_crm_ollama_model', 'llama3.2' );
    }

    public static function get_globals() {
        $crm = self::get_instance();
        $current_group = $crm->get_current_group_from_params();

        // Check if we're on select.php to prevent redirect loops
        $current_script = basename( $_SERVER['PHP_SELF'] );
        $is_select_page = ( $current_script === 'index.php' );

        if ( ! $current_group && ! $is_select_page ) {
            $current_group = $crm->use_default_group();
            $available_groups = $crm->storage->get_available_groups();
            if ( count( $available_groups ) > 1 && ! $current_group ) {
                header( 'Location: ' . $crm->build_url( 'index.php' ) );
                exit;
            }
        }

        // Load group object - members and events will be lazy loaded
        $group_data = $crm->storage->get_group( $current_group );

        // Handle case where group doesn't exist
        if ( ! $group_data && ! $is_select_page ) {
            $available_groups = $crm->storage->get_available_groups();
            if ( ! empty( $available_groups ) ) {
                header( 'Location: ' . $crm->build_url( 'index.php' ) );
                exit;
            }
            // No groups exist at all - will show create team page
            $group_data = null;
        } elseif ( $group_data ) {
            // Set as current group for static access
            Group::set_current( $group_data );
        }

        $available_groups = $crm->storage->get_available_groups();

        return compact( 'crm', 'current_group', 'group_data', 'available_groups' );
    }

    /**
     * Check if a group is configured as a social group
     */
    public function is_social_group( $group_slug ) {
        if ( empty( $group_slug ) ) {
            return false;
        }
        return $this->storage->get_group_type( $group_slug ) === 'group';
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
     * Get display word for group type ('team' -> 'team', 'group' -> 'group')
     */
    public function get_type_display_word( $group_slug ) {
        $type = $this->storage->get_group_type( $group_slug );
        return ( $type === 'group' ) ? 'group' : 'team';
    }

    /**
     * Get display title with appropriate type word
     */
    public function get_group_display_title( $group_slug, $suffix = '' ) {
        $group_name = $this->storage->get_group_name( $group_slug );
        $group_name = $group_name ?: ucfirst( str_replace( '_', ' ', $group_slug ) );

        if ( empty( $suffix ) ) {
            return $group_name . ' ' . ucfirst( $this->group );
        }

        return $group_name . ' ' . ucfirst( $this->group ) . ' ' . $suffix;
    }

    /**
     * Get current group slug from URL parameters, treating 'group' as synonym for 'team'
     */
    public function get_current_group_from_params() {
        $group_param_from_get = $_POST['team'] ?? $_GET['team'] ?? null;
        $group_param_alt = $_POST['group'] ?? $_GET['group'] ?? null;

        if ( empty( $group_param_from_get ) && function_exists( '\get_query_var' ) ) {
            $group_param_from_get = \get_query_var( 'group' );
        }

        $this->current_group = $group_param_alt ?? $group_param_from_get ?? null;

        // Exclude page names from being treated as group slugs
        $reserved_names = array( 'select', 'admin', 'person', 'events' );
        if ( in_array( $this->current_group, $reserved_names, true ) ) {
            $this->current_group = null;
        }

        $this->group = $this->get_type_display_word( $this->current_group );
        return $this->current_group;
    }
    /**
     * Get the default group slug (group marked with default: true)
     */
    public function get_default_group() {
        return $this->storage->get_default_group();
    }

    public function use_default_group() {
        $this->current_group = $this->get_default_group();
        return $this->current_group;
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
     * Load group configuration from storage and convert to Person objects
     */
    /**
     * Get a single person by username and group
     * Returns a Person object with category information
     */
    public function get_person_with_category( $group_slug, $username ) {
        $person_obj = $this->storage->get_person( $username );

        if ( ! $person_obj ) {
            return null;
        }

        $person_obj->team = $group_slug;

        // Get the parent group config
        $config = $this->storage->get_group( $group_slug );
        if ( ! $config ) {
            return null;
        }

        // Check if person is in direct members
        $members = $config->get_members();
        if ( isset( $members[ $username ] ) ) {
            $person_obj->category = 'members';
            $person_obj->category_group = $config->group_name;
        } else {
            // Check if they're in any child groups
            $child_groups = $config->get_child_groups();
            foreach ( $child_groups as $child ) {
                $child_members = $child->get_members();
                if ( isset( $child_members[ $username ] ) ) {
                    $person_obj->category = $child->slug;
                    $person_obj->category_group = $child->group_name;
                    break;
                }
            }
        }

        return $person_obj;
    }

    /**
     * Render upcoming events sidebar section
     *
     * @param array $group_data Group data containing people and events
     * @param int $days_ahead Number of days to look ahead (default: 90 for team page, 365 for person page)
     * @param string $filter_person Show events only for this person (null for all people)
     * @param bool $include_team_events Whether to include team-wide events
     */
    public function render_upcoming_events_sidebar( $group_data, $days_ahead = 90, $filter_person = null, $include_team_events = true ) {
        $current_date = new DateTime();
        $cutoff_date = clone $current_date;
        $cutoff_date->add( new \DateInterval( 'P' . $days_ahead . 'D' ) );

        $all_events = array();

        // If filtering by person but no group data, fetch person directly
        if ( $filter_person && ! $group_data ) {
            $person_obj = $this->storage->get_person( $filter_person );
            if ( $person_obj ) {
                if ( method_exists( $person_obj, 'get_upcoming_events' ) ) {
                    $personal_events = $person_obj->get_upcoming_events();
                    $all_events = array_merge( $all_events, $personal_events );
                }
            }
        } else {
            // Collect all people from direct members and child groups
            $all_people = array();
            if ( $group_data ) {
                $all_people = array_merge( $all_people, $group_data->get_members() );
            }

            // Add people from all child groups
            if ( $group_data ) {
                $child_groups = $group_data->get_child_groups();
                foreach ( $child_groups as $child ) {
                    $all_people = array_merge( $all_people, $child->get_members() );
                }
            }
            foreach ( $all_people as $person ) {
                if ( is_object( $person ) && method_exists( $person, 'get_upcoming_events' ) ) {

                    // Skip if filtering by person and this isn't the person
                    if ( $filter_person && $person->username !== $filter_person ) {
                        continue;
                    }

                    $personal_events = $person->get_upcoming_events();
                    $all_events = array_merge( $all_events, $personal_events );
                }
            }
        }

        // Allow plugins to add or modify events in sidebar context
        $all_events = apply_filters( 'personal_crm_sidebar_all_events', $all_events, $group_data, $filter_person );

        if ( $include_team_events && $group_data ) {
            foreach ( $group_data->get_events() as $event ) {
                $event_start = $event->date;
                $event_end = isset( $event->end_date ) && $event->end_date ? $event->end_date : $event->date;

                // Include event if it's upcoming or currently ongoing
                if ( $event_end >= $current_date && $event_start <= $cutoff_date ) {
                    $all_events[] = $event;
                } else {
                }
            }
        }


        // Sort all events by date
        usort( $all_events, function( $a, $b ) {
            return $a->date <=> $b->date;
        } );

        // Filter to upcoming events within the time window (including ongoing multi-day events)
        $upcoming_events = array();
        foreach ( $all_events as $event ) {
            $event_start = $event->date;
            $event_end = isset( $event->end_date ) && $event->end_date ? $event->end_date : $event->date;

            // Include event if it's upcoming or currently ongoing
            if ( $event_end >= $current_date && $event_start <= $cutoff_date ) {
                $upcoming_events[] = $event;
            } else {
            }
        }


        // Find next event after the cutoff date
        $next_event_after = null;
        foreach ( $all_events as $event ) {
            if ( $event->date > $cutoff_date ) {
                $next_event_after = $event;
                break;
            }
        }

        if ( ! empty( $upcoming_events ) ) {
            foreach ( $upcoming_events as $event ) {
                $formatted_date = $event->date->format( 'M j, Y' );
                $event_date_start = clone $event->date;
                $event_date_start->setTime( 0, 0, 0 );
                $current_date_start = clone $current_date;
                $current_date_start->setTime( 0, 0, 0 );
                $days_until = $current_date_start->diff( $event_date_start )->days;
                $is_past = $event->date < $current_date;
                ?>
                <div class="event-item">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                        <div class="event-date"><?php echo esc_html( $formatted_date ); ?></div>
                        <?php if ( ! $is_past && $days_until <= 120 ) : // Show for events within 120 days ?>
                            <div style="font-size: 11px; color: #999; font-weight: normal;">
                                <?php
                                if ( $days_until == 0 ) {
                                    echo 'today';
                                } elseif ( $days_until == 1 ) {
                                    echo 'in 1d';
                                } elseif ( $days_until > 60 ) {
                                    echo 'in ' . floor( $days_until / 30 ) . 'mo';
                                } else {
                                    echo 'in ' . $days_until . 'd';
                                }
                                ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="event-description"><?php
                        $description = $event->get_title();

                        // If filtering by person (on person page), don't show person name
                        if ( $filter_person && $event->person && $event->person->username === $filter_person ) {
                            // Remove person's name from the description since we're on their page
                            $person_name = $event->person->name;
                            $description = str_replace( $person_name . "'s ", '', $description );
                            $description = str_replace( $person_name . ' ', '', $description );
                            echo esc_html( $description );
                        } elseif ( $event->person && ! $filter_person ) {
                            // Show clickable person name for team pages
                            $person_name = $event->person->name;
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

            // Show next event after the window
            if ( $next_event_after ) {
                $days_until_next = $current_date->diff( $next_event_after->date )->days;
                ?>
                <p style="color: #999; font-size: 12px; margin: 12px 0 0 0; font-style: italic;">
                    Then <?php echo $days_until_next; ?> days until the next event
                </p>
                <?php
            }
        } else {
            if ( $next_event_after ) {
                $days_until_next = $current_date->diff( $next_event_after->date )->days;
                echo '<p class="no-upcoming-events">No upcoming events in the next ' . $days_ahead . ' days<br><span>Next event in ' . $days_until_next . ' days</span></p>';
            } else {
                echo '<p class="no-upcoming-events">No upcoming events</p>';
            }
        }
    }

    /**
     * Default sorting for people - sort by name
     */
    public function default_sort_people( $people, $type, $group_slug ) {
        uasort( $people, function( $a, $b ) {
            return strcasecmp( $a->name, $b->name );
        } );
        return $people;
    }

    /**
     * Build URL with group parameter (uses 'group' parameter for group-type groups)
     */
    public function build_url( $base_url, $additional_params = array() ) {
        if ( $this->app && defined( 'WPINC' ) ) {
            $route = str_replace( '.php', '', $base_url );

            if ( $route === 'group' || $route === '' || $route === './' ) {
                $route = '';
            } elseif ( $route === 'index' ) {
                $route = 'select';
            }

            if ( ( $route === 'person' || $route === '' ) && isset( $additional_params['person'] ) ) {
                $username = $additional_params['person'];
                unset( $additional_params['person'] );

                // Remove group parameter if present (no longer needed with M:N relationships)
                if ( isset( $additional_params['group'] ) ) {
                    unset( $additional_params['group'] );
                }
                if ( isset( $additional_params['team'] ) ) {
                    unset( $additional_params['team'] );
                }

                $url = home_url( '/crm/person/' . $username );
            } elseif ( $route === 'admin/person' && isset( $additional_params['person'] ) ) {
                $username = $additional_params['person'];
                unset( $additional_params['person'] );

                $url = home_url( '/crm/admin/person/' . $username );
            } elseif ( $route === 'admin' || $route === 'admin/index' ) {
                if ( isset( $additional_params['group'] ) ) {
                    $group = $additional_params['group'];
                    unset( $additional_params['group'] );
                } elseif ( isset( $additional_params['team'] ) ) {
                    $group = $additional_params['team'];
                    unset( $additional_params['team'] );
                } else {
                    $group = $this->current_group;
                }

                $suffix = '';
                if ( isset( $additional_params['members'] ) && $additional_params['members'] ) {
                    $suffix = '/members';
                    unset( $additional_params['members'] );
                } elseif ( isset( $additional_params['tab'] ) ) {
                    $tab = $additional_params['tab'];
                    if ( in_array( $tab, array( 'links', 'events', 'audit' ) ) ) {
                        $suffix = '/' . $tab;
                    }
                    unset( $additional_params['tab'] );
                }

                if ( $group ) {
                    $url = home_url( '/crm/admin/group/' . $group . $suffix );
                } else {
                    $url = home_url( '/crm/admin' );
                }
            } elseif ( $route === 'group-history' ) {
                if ( isset( $additional_params['group'] ) ) {
                    $group = $additional_params['group'];
                    unset( $additional_params['group'] );
                } elseif ( isset( $additional_params['team'] ) ) {
                    $group = $additional_params['team'];
                    unset( $additional_params['team'] );
                } else {
                    $group = $this->current_group;
                }

                $url = home_url( '/crm/group/' . $group . '/history' );
            } elseif ( $route === '' && ( isset( $additional_params['group'] ) || isset( $additional_params['team'] ) ) ) {
                // Index page with group parameter
                if ( isset( $additional_params['group'] ) ) {
                    $group = $additional_params['group'];
                    unset( $additional_params['group'] );
                } else {
                    $group = $additional_params['team'];
                    unset( $additional_params['team'] );
                }

                $url = home_url( '/crm/group/' . $group );
            } else {
                $url = home_url( '/crm/' . ltrim( $route, '/' ) );
            }

            // Allow plugins to modify the URL and parameters before adding query string
            $url_data = apply_filters( 'personal_crm_build_url', array( 'url' => $url, 'params' => $additional_params ), $base_url );
            $url = $url_data['url'];
            $additional_params = $url_data['params'];

            $additional_params = apply_filters( 'personal_crm_build_url_params', $additional_params, $base_url );

            if ( ! empty( $additional_params ) ) {
                $url .= '?' . http_build_query( $additional_params );
            }

            return $url;
        }

        $params = array();
        if ( $this->current_group !== 'team' ) {
            $group_type = $this->storage->get_group_type( $this->current_group );
            $param_name = ( $group_type === 'group' ) ? 'group' : 'team';
            $params[ $param_name ] = $this->current_group;
        }
        $params = array_merge( $params, $additional_params );

        $params = apply_filters( 'personal_crm_build_url_params', $params, $base_url );

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

    public function ajax_get_person_details() {
        $username = $_GET['username'] ?? '';
        $group_slug = $_GET['team'] ?? '';

        if ( empty( $username ) || empty( $group_slug ) || ! $this->storage->group_exists( $group_slug ) ) {
            wp_send_json_error( 'Invalid request' );
            return;
        }

        $person = $this->storage->get_person( $group_slug, $username );

        if ( ! $person ) {
            wp_send_json_error( 'Person not found' );
            return;
        }

        wp_send_json_success( array(
            'role' => $person->role ?? '',
            'location' => $person->location ?? '',
            'birthday' => $person->birthday ?? '',
            'links' => $person->links ?? array(),
            'linear' => $person->linear ?? ''
        ) );
    }

    public function init_cmd_k_js() {
        $available_groups = $this->storage->get_available_groups();
        $groups = array();
        $search_index = array();

        foreach ( $available_groups as $group_slug ) {
            $group_name = $this->storage->get_group_name( $group_slug ) ?: ucfirst( str_replace( '_', ' ', $group_slug ) );
            $group_obj = $this->storage->get_group( $group_slug );

            if ( ! $group_obj ) {
                continue;
            }

            // Count members from direct members and child groups
            $members = $group_obj->get_members();
            $members_count = count( $members );
            $total_count = $members_count;
            $child_counts = array();

            $child_groups = $group_obj->get_child_groups();
            foreach ( $child_groups as $child_group ) {
                $child_members = $child_group->get_members();
                $child_count = count( $child_members );
                $child_counts[$child_group->slug] = $child_count;
                $total_count += $child_count;
            }

            $groups[] = array(
                'slug' => $group_slug,
                'name' => $group_name,
                'members' => $members_count,
                'child_counts' => $child_counts,
                'total_people' => $total_count,
                'url' => $this->build_url( 'group.php', array( 'group' => $group_slug ) )
            );

            // Index direct members
            foreach ( $members as $username => $person ) {
                $search_index[] = array(
                    'username' => $username,
                    'name' => $person->name ?? '',
                    'nickname' => $person->nickname ?? '',
                    'team_slug' => $group_slug,
                    'team_name' => $group_name,
                    'type' => 'Member',
                    'url' => $this->build_url( 'person.php', array( 'person' => $username ) )
                );
            }

            // Index child group members
            foreach ( $child_groups as $child_group ) {
                $child_members = $child_group->get_members();
                foreach ( $child_members as $username => $person ) {
                    $search_index[] = array(
                        'username' => $username,
                        'name' => $person->name ?? '',
                        'nickname' => $person->nickname ?? '',
                        'team_slug' => $group_slug,
                        'team_name' => $group_name,
                        'type' => $child_group->group_name,
                        'url' => $this->build_url( 'person.php', array( 'person' => $username ) )
                    );
                }
            }
        }

        $ajax_url = admin_url( 'admin-ajax.php' );
        $base_url = home_url( '/crm/' );
        ?>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                if (typeof CmdK !== 'undefined') {
                    const teams = <?php echo json_encode( $groups ); ?>;
                    const searchIndex = <?php echo json_encode( $search_index ); ?>;
                    const ajaxUrl = <?php echo json_encode( $ajax_url ); ?>;
                    const baseUrl = <?php echo json_encode( $base_url ); ?>;
                    CmdK.init(teams, searchIndex, ajaxUrl, baseUrl);

                    // Trigger privacy mode now that searchIndex is available
                    if (typeof applyPrivacyMode === 'function' && localStorage.getItem('privacyMode') === 'true') {
                        applyPrivacyMode(searchIndex);
                    }
                }
            });
        </script>
        <?php
    }

    // Include all the remaining essential functions from the original common.php that are used throughout the system
    // These include event handling, privacy mode functions, etc.

    /**
     * Get events for calendar display (all events within date range, not just upcoming)
     */
    public function get_calendar_events( $group_data, $start_date, $end_date ) {
        $all_events = array();

        // Collect all people from direct members and child groups
        $all_people = array();
        if ( $group_data ) {
            $all_people = array_merge( $all_people, $group_data->get_members() );
        }

        // Add people from all child groups
        if ( $group_data ) {
            $child_groups = $group_data->get_child_groups();
            foreach ( $child_groups as $child ) {
                $all_people = array_merge( $all_people, $child->get_members() );
            }
        }

        foreach ( $all_people as $person ) {
            if ( is_object( $person ) && method_exists( $person, 'get_upcoming_events' ) ) {
                $personal_events = $person->get_upcoming_events();
                foreach ( $personal_events as $event ) {
                    $event_start = $event->date;
                    $event_end = isset( $event->end_date ) && $event->end_date ? $event->end_date : $event->date;

                    if ( $event_end >= $start_date && $event_start <= $end_date ) {
                        $all_events[] = $event;
                    }
                }
            }
        }

        if ( $group_data ) {
            foreach ( $group_data->get_events() as $event ) {
                $event_start = $event->date;
                $event_end = isset( $event->end_date ) && $event->end_date ? $event->end_date : $event->date;

                if ( $event_end >= $start_date && $event_start <= $end_date ) {
                    $all_events[] = $event;
                }
            }
        }

        usort( $all_events, function( $a, $b ) {
            return $a->date <=> $b->date;
        } );

        return $all_events;
    }

    /**
     * Get upcoming events for display (includes ongoing multi-day events)
     */
    public function get_upcoming_events_for_display( $group_data ) {
        $current_date = new DateTime();
        $cutoff_date = clone $current_date;
        $cutoff_date->add( new \DateInterval( 'P3M' ) );
        $all_events = array();

        // Collect all people from direct members and child groups
        $all_people = array();
        if ( $group_data ) {
            $all_people = array_merge( $all_people, $group_data->get_members() );
        }

        // Add people from all child groups
        if ( $group_data ) {
            $child_groups = $group_data->get_child_groups();
            foreach ( $child_groups as $child ) {
                $all_people = array_merge( $all_people, $child->get_members() );
            }
        }

        foreach ( $all_people as $person ) {
            if ( is_object( $person ) && method_exists( $person, 'get_upcoming_events' ) ) {
                $personal_events = $person->get_upcoming_events();
                $all_events = array_merge( $all_events, $personal_events );
            }
        }

        if ( $group_data ) {
            foreach ( $group_data->get_events() as $event ) {
                $event_start = $event->date;
                $event_end = isset( $event->end_date ) && $event->end_date ? $event->end_date : $event->date;

                if ( $event_end >= $current_date && $event_start <= $cutoff_date ) {
                    $all_events[] = $event;
                }
            }
        }

        usort( $all_events, function( $a, $b ) {
            return $a->date <=> $b->date;
        } );

        return $all_events;
    }

    public function get_all_upcoming_events( $group_data ) {
        $all_events = array();
        $current_date = new DateTime();
        $cutoff_date = clone $current_date;
        $cutoff_date->add( new \DateInterval( 'P3M' ) );

        $include_alumni = isset( $_GET['alumni'] ) && $_GET['alumni'] === '1';

        // Collect all people from direct members
        $all_people = array();
        if ( $group_data ) {
            $all_people = array_merge( $all_people, $group_data->get_members() );
        }

        // Add people from child groups (optionally excluding alumni)
        if ( $group_data ) {
            $child_groups = $group_data->get_child_groups();
            foreach ( $child_groups as $child ) {
                $is_alumni = stripos( $child->slug, 'alumni' ) !== false || stripos( $child->group_name, 'alumni' ) !== false;

                // Skip alumni groups if not explicitly included
                if ( $is_alumni && ! $include_alumni ) {
                    continue;
                }

                $all_people = array_merge( $all_people, $child->get_members() );
            }
        }
        foreach ( $all_people as $person ) {
            $personal_events = $person->get_upcoming_events();
            $all_events = array_merge( $all_events, $personal_events );
        }

        foreach ( $group_data->get_events() as $event ) {
            $start_date = $event->date;
            $end_date = isset( $event->end_date ) && $event->end_date ? $event->end_date : $start_date;

            if ( $end_date >= $current_date && $start_date <= $cutoff_date ) {
                $duration = '';
                if ( $end_date && $start_date->format( 'Y-m-d' ) !== $end_date->format( 'Y-m-d' ) ) {
                    $duration = ' - ' . $end_date->format( 'M j' );
                }

                $all_events[] = array(
                    'type' => $event->type,
                    'date' => $start_date,
                    'description' => $event->description . $duration,
                    'location' => $event->location ?? '',
                    'details' => $event->details ?? '',
                );
            }
        }

        usort( $all_events, function( $a, $b ) {
            $date_a = is_array( $a ) ? $a['date'] : $a->date;
            $date_b = is_array( $b ) ? $b['date'] : $b->date;
            return $date_a <=> $date_b;
        } );

        return $all_events;
    }

    public function format_tenure( $join_date ) {
        if ( empty( $join_date ) ) {
            return '';
        }

        $start = new \DateTime( $join_date );
        $now = new \DateTime();
        $interval = $start->diff( $now );

        $years = $interval->y;
        $months = $interval->m;

        if ( $years > 0 ) {
            if ( $months > 0 ) {
                $year_text = $years === 1 ? 'year' : 'years';
                $month_text = $months === 1 ? 'month' : 'months';
                return "{$years} {$year_text}, {$months} {$month_text}";
            } else {
                $year_text = $years === 1 ? 'year' : 'years';
                return "{$years} {$year_text}";
            }
        } elseif ( $months > 0 ) {
            $month_text = $months === 1 ? 'month' : 'months';
            return "{$months} {$month_text}";
        } else {
            $days = $interval->d;
            if ( $days === 0 ) {
                return 'less than a day';
            }
            $day_text = $days === 1 ? 'day' : 'days';
            return "{$days} {$day_text}";
        }
    }

    /**
     * Compile group history timeline grouped by month
     *
     * @param int $group_id The group ID
     * @return array Array of events grouped by month (YYYY-MM)
     */
    public function compile_group_history( $group_id ) {
        $membership_changes = $this->storage->get_group_membership_history( $group_id, true );
        $team_events = $this->storage->get_group_events( $group_id );

        $main_group = $this->storage->get_group_by_id( $group_id );
        $main_group_name = $main_group->group_name;

        $timeline = array();
        $processed_transitions = array();

        foreach ( $membership_changes as $i => $change ) {
            if ( isset( $processed_transitions[ $i ] ) ) {
                continue;
            }

            $date = new \DateTime( $change['date'] );
            $month_key = $date->format( 'Y-m' );

            if ( ! isset( $timeline[ $month_key ] ) ) {
                $timeline[ $month_key ] = array(
                    'month_key'   => $month_key,
                    'month_label' => $date->format( 'F Y' ),
                    'events'      => array(),
                );
            }

            $display_name = $change['name'];
            if ( ! empty( $change['nickname'] ) ) {
                $display_name .= ' (' . $change['nickname'] . ')';
            }

            $event_data = array(
                'type'        => $change['event_type'],
                'date'        => $change['date'],
                'person'      => $display_name,
                'username'    => $change['username'],
                'group_name'  => $change['group_name'],
            );

            $is_child_group_event = ( $change['group_name'] !== $main_group_name );

            for ( $j = 0; $j < count( $membership_changes ); $j++ ) {
                if ( $i === $j || isset( $processed_transitions[ $j ] ) ) {
                    continue;
                }

                $other = $membership_changes[ $j ];
                if ( $other['username'] !== $change['username'] ) {
                    continue;
                }

                $date_diff = abs( strtotime( $change['date'] ) - strtotime( $other['date'] ) );
                if ( $date_diff > 86400 ) {
                    continue;
                }

                if ( $change['event_type'] === 'join' && $other['event_type'] === 'leave' &&
                     $change['group_name'] === $main_group_name && $other['group_name'] !== $main_group_name ) {
                    $event_data['from_group'] = $other['group_name'];
                    $processed_transitions[ $j ] = true;
                    break;
                } elseif ( $change['event_type'] === 'leave' && $other['event_type'] === 'join' &&
                          $change['group_name'] === $main_group_name && $other['group_name'] !== $main_group_name ) {
                    $event_data['to_group'] = $other['group_name'];
                    $processed_transitions[ $j ] = true;
                    break;
                } elseif ( $change['event_type'] === 'join' && $other['event_type'] === 'leave' &&
                          $change['group_name'] !== $main_group_name && $other['group_name'] === $main_group_name ) {
                    $processed_transitions[ $i ] = true;
                    break;
                } elseif ( $change['event_type'] === 'leave' && $other['event_type'] === 'join' &&
                          $change['group_name'] !== $main_group_name && $other['group_name'] === $main_group_name ) {
                    $processed_transitions[ $i ] = true;
                    break;
                }
            }

            if ( isset( $processed_transitions[ $i ] ) ) {
                continue;
            }

            if ( $change['event_type'] === 'leave' && ! empty( $change['new_company'] ) ) {
                $event_data['new_company'] = $change['new_company'];
                $event_data['new_company_website'] = $change['new_company_website'] ?? null;
            }

            $timeline[ $month_key ]['events'][] = $event_data;
        }

        foreach ( $team_events as $event ) {
            if ( empty( $event->date ) ) {
                continue;
            }

            $month_key = $event->date->format( 'Y-m' );

            if ( ! isset( $timeline[ $month_key ] ) ) {
                $timeline[ $month_key ] = array(
                    'month_key'   => $month_key,
                    'month_label' => $event->date->format( 'F Y' ),
                    'events'      => array(),
                );
            }

            $timeline[ $month_key ]['events'][] = array(
                'type'        => 'team_event',
                'date'        => $event->date->format( 'Y-m-d H:i:s' ),
                'event_type'  => $event->type,
                'name'        => $event->name,
                'description' => $event->details ?? null,
                'location'    => $event->location ?? null,
            );
        }

        krsort( $timeline );

        foreach ( $timeline as &$month_data ) {
            usort( $month_data['events'], function( $a, $b ) {
                return strcmp( $b['date'], $a['date'] );
            } );
        }

        return $timeline;
    }

}