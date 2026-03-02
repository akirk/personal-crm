<?php
/**
 * Plugin Name: Personal CRM
 * Description: WordPress-based personal CRM tool for managing contacts, teams, and relationships with extensible architecture
 * Version: 1.0.0
 * Author: Alex Kirk
 * Author URI: https://alex.kirk.at/
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: personal-crm
 */

namespace PersonalCRM;

// Define constants
define( 'PERSONAL_CRM_PLUGIN_VERSION', '1.0.0' );
define( 'PERSONAL_CRM_PLUGIN_FILE', __FILE__ );

// Register activation/deactivation hooks at plugin file level
// These must be registered here (not in a class constructor) because
// activation hooks fire BEFORE plugins_loaded
\register_activation_hook( __FILE__, 'PersonalCRM\personal_crm_activate' );
\register_deactivation_hook( __FILE__, 'PersonalCRM\personal_crm_deactivate' );

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
require_once __DIR__ . '/includes/abilities.php';

// Initialize storage and set up PersonalCrm
if ( defined( 'WPINC' ) ) {
    // WordPress context - use WordPress wpdb
    add_action( 'plugins_loaded', function() {
        global $wpdb;
        $storage = new Storage( $wpdb );
        PersonalCrm::set_storage( $storage );
        PersonalCrm::get_instance();
        \PersonalCRM\register_abilities();
    } );
} else {
    $sqlite_file = __DIR__ . '/data/a8c.db';
    $sqlite_wpdb = new \WpApp\sqlite_wpdb( $sqlite_file, '' );
    $storage = new Storage( $sqlite_wpdb );
    PersonalCrm::set_storage( $storage );
}

add_filter( 'my_apps_plugins', function( $apps ) {
    $apps['personal-crm'] = array(
        'name'     => 'Personal CRM',
        'url'      => home_url( '/crm/' ),
        'icon_url' => 'data:image/svg+xml,' . rawurlencode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#2271b1"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>' ),
    );

    // Add saved CRM items (groups and people) to my-apps
    $saved_items = get_option( 'personal_crm_my_apps', array() );
    if ( ! empty( $saved_items ) ) {
        $crm = PersonalCrm::get_instance();
        $storage = $crm->storage;

        foreach ( $saved_items as $item ) {
            $slug = 'crm-' . $item['type'] . '-' . $item['id'];

            if ( $item['type'] === 'group' ) {
                $group = $storage->get_group( $item['id'] );
                if ( $group ) {
                    $icon = $group->display_icon ?: '';
                    $apps[ $slug ] = array(
                        'name'     => $group->group_name,
                        'url'      => home_url( '/crm/group/' . $group->slug ),
                        'emoji'    => $icon ?: '👥',
                    );
                }
            } elseif ( $item['type'] === 'person' ) {
                $person = $storage->get_person( $item['id'] );
                if ( $person ) {
                    $icon_url = '';
                    if ( ! empty( $person->email ) ) {
                        $hash = md5( strtolower( trim( $person->email ) ) );
                        $icon_url = 'https://www.gravatar.com/avatar/' . $hash . '?s=120&d=mp';
                    }
                    $apps[ $slug ] = array(
                        'name'     => $person->name,
                        'url'      => home_url( '/crm/person/' . $person->username ),
                        'icon_url' => $icon_url ?: '',
                        'emoji'    => $icon_url ? '' : '👤',
                    );
                }
            }
        }
    }

    return $apps;
} );

// Add masterbar menu items to add current person/group to my-apps
// Use wp_app_admin_bar_menu which fires after wp-app adds its menu items
add_action( 'wp_app_admin_bar_menu', function( $wp_admin_bar ) {
    if ( ! is_user_logged_in() ) {
        return;
    }

    $request_uri = $_SERVER['REQUEST_URI'] ?? '';

    // Check if we're on a CRM person page
    if ( preg_match( '#/crm/person/([^/]+)#', $request_uri, $matches ) ) {
        $username = $matches[1];
        $saved_items = get_option( 'personal_crm_my_apps', array() );
        $is_saved = false;
        foreach ( $saved_items as $item ) {
            if ( $item['type'] === 'person' && $item['id'] === $username ) {
                $is_saved = true;
                break;
            }
        }

        $wp_admin_bar->add_node( array(
            'id'    => 'crm-add-to-my-apps',
            'title' => $is_saved ? '★ In My Apps' : '☆ Add to My Apps',
            'href'  => '#',
            'meta'  => array(
                'onclick' => 'personalCrmToggleMyApps("person", "' . esc_js( $username ) . '"); return false;',
            ),
        ) );
    }

    // Check if we're on a CRM group page
    if ( preg_match( '#/crm/group/([^/]+)#', $request_uri, $matches ) ) {
        $group_slug = $matches[1];
        $saved_items = get_option( 'personal_crm_my_apps', array() );
        $is_saved = false;
        foreach ( $saved_items as $item ) {
            if ( $item['type'] === 'group' && $item['id'] === $group_slug ) {
                $is_saved = true;
                break;
            }
        }

        $wp_admin_bar->add_node( array(
            'id'    => 'crm-add-to-my-apps',
            'title' => $is_saved ? '★ In My Apps' : '☆ Add to My Apps',
            'href'  => '#',
            'meta'  => array(
                'onclick' => 'personalCrmToggleMyApps("group", "' . esc_js( $group_slug ) . '"); return false;',
            ),
        ) );
    }
} );

// Enqueue JS for my-apps toggle functionality
add_action( 'wp_head', function() {
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    if ( strpos( $request_uri, '/crm/' ) === false ) {
        return;
    }
    ?>
    <script>
    function personalCrmToggleMyApps(type, id) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
                var response = JSON.parse(xhr.responseText);
                if (response.success) {
                    var menuItem = document.querySelector('#wp-admin-bar-crm-add-to-my-apps .ab-item');
                    if (menuItem) {
                        menuItem.textContent = response.data.is_saved ? '★ In My Apps' : '☆ Add to My Apps';
                    }
                }
            }
        };
        xhr.send('action=personal_crm_toggle_my_apps&type=' + encodeURIComponent(type) + '&id=' + encodeURIComponent(id) + '&nonce=<?php echo esc_js( wp_create_nonce( 'personal_crm_my_apps' ) ); ?>');
    }
    </script>
    <?php
}, 100 );

// AJAX handler to toggle my-apps items
add_action( 'wp_ajax_personal_crm_toggle_my_apps', function() {
    check_ajax_referer( 'personal_crm_my_apps', 'nonce' );

    if ( ! is_user_logged_in() ) {
        wp_send_json_error( 'Not logged in' );
    }

    $type = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '';
    $id = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';

    if ( empty( $type ) || empty( $id ) || ! in_array( $type, array( 'person', 'group' ), true ) ) {
        wp_send_json_error( 'Invalid parameters' );
    }

    $saved_items = get_option( 'personal_crm_my_apps', array() );
    $is_saved = false;
    $found_index = -1;

    foreach ( $saved_items as $index => $item ) {
        if ( $item['type'] === $type && $item['id'] === $id ) {
            $is_saved = true;
            $found_index = $index;
            break;
        }
    }

    if ( $is_saved ) {
        // Remove from my-apps
        array_splice( $saved_items, $found_index, 1 );
        $is_saved = false;
    } else {
        // Add to my-apps
        $saved_items[] = array(
            'type' => $type,
            'id'   => $id,
        );
        $is_saved = true;
    }

    update_option( 'personal_crm_my_apps', $saved_items );

    wp_send_json_success( array( 'is_saved' => $is_saved ) );
} );

// AJAX handler for quick field updates (used by paste handler)
add_action( 'wp_ajax_personal_crm_quick_update', function() {
    check_ajax_referer( 'personal_crm_quick_update', 'nonce' );

    if ( ! is_user_logged_in() ) {
        wp_send_json_error( array( 'message' => 'Not logged in' ) );
    }

    $username = isset( $_POST['username'] ) ? sanitize_text_field( wp_unslash( $_POST['username'] ) ) : '';
    $field = isset( $_POST['field'] ) ? sanitize_text_field( wp_unslash( $_POST['field'] ) ) : '';
    $value = isset( $_POST['value'] ) ? sanitize_text_field( wp_unslash( $_POST['value'] ) ) : '';

    if ( empty( $username ) || empty( $field ) ) {
        wp_send_json_error( array( 'message' => 'Missing required parameters' ) );
    }

    // Date sanitizer function
    $date_sanitizer = function( $val ) {
        if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $val ) || preg_match( '/^\d{2}-\d{2}$/', $val ) ) {
            return $val;
        }
        return '';
    };

    // Define allowed fields that can be quick-updated
    // Other plugins can filter this to add their own fields
    $allowed_fields = apply_filters( 'personal_crm_quick_update_fields', array(
        'birthday' => array( 'sanitize' => $date_sanitizer ),
        'partner_birthday' => array( 'sanitize' => $date_sanitizer ),
        'email' => array( 'sanitize' => 'sanitize_email' ),
    ) );

    // Check if it's a child birthday field (child_birthday_0, child_birthday_1, etc.)
    $is_child_birthday = preg_match( '/^child_birthday_(\d+)$/', $field, $child_matches );

    if ( ! isset( $allowed_fields[ $field ] ) && ! $is_child_birthday ) {
        wp_send_json_error( array( 'message' => 'Field not allowed for quick update' ) );
    }

    // Sanitize the value
    if ( $is_child_birthday ) {
        $sanitized_value = $date_sanitizer( $value );
    } else {
        $sanitizer = $allowed_fields[ $field ]['sanitize'] ?? 'sanitize_text_field';
        $sanitized_value = is_callable( $sanitizer ) ? $sanitizer( $value ) : sanitize_text_field( $value );
    }

    if ( $value !== '' && $sanitized_value === '' ) {
        wp_send_json_error( array( 'message' => 'Invalid value format' ) );
    }

    // Get the CRM instance and update the person
    $crm = \PersonalCRM\PersonalCrm::get_instance();
    $person = $crm->storage->get_person( $username );

    if ( ! $person ) {
        wp_send_json_error( array( 'message' => 'Person not found' ) );
    }

    // Build person data array with the updated field
    $person_data = array(
        'name' => $person->name,
        'nickname' => $person->nickname ?? '',
        'role' => $person->role ?? '',
        'email' => $person->email ?? '',
        'birthday' => $person->birthday ?? '',
        'company_anniversary' => $person->company_anniversary ?? '',
        'partner' => $person->partner ?? '',
        'partner_birthday' => $person->partner_birthday ?? '',
        'location' => $person->location ?? '',
        'timezone' => $person->timezone ?? '',
        'github' => $person->github ?? '',
        'linear' => $person->linear ?? '',
        'wordpress' => $person->wordpress ?? '',
        'linkedin' => $person->linkedin ?? '',
        'website' => $person->website ?? '',
        'new_company' => $person->new_company ?? '',
        'new_company_website' => $person->new_company_website ?? '',
        'deceased_date' => $person->deceased_date ?? '',
        'left_company' => $person->left_company ?? 0,
        'deceased' => $person->deceased ?? 0,
        'kids' => $person->kids ?? array(),
        'github_repos' => $person->github_repos ?? array(),
        'personal_events' => $person->personal_events ?? array(),
        'links' => $person->links ?? array(),
    );

    // Update the specific field
    if ( $is_child_birthday ) {
        // Update child's birthday in the kids array
        $child_index = (int) $child_matches[1];
        if ( isset( $person_data['kids'][ $child_index ] ) ) {
            $person_data['kids'][ $child_index ]['birthday'] = $sanitized_value;
        } else {
            wp_send_json_error( array( 'message' => 'Child not found' ) );
        }
    } else {
        $person_data[ $field ] = $sanitized_value;
    }

    // Allow plugins to modify the data before saving
    $person_data = apply_filters( 'personal_crm_quick_update_before_save', $person_data, $field, $sanitized_value, $username );

    // Get current groups to preserve them
    $groups_with_dates = array();
    if ( ! empty( $person->groups ) ) {
        foreach ( $person->groups as $group ) {
            $groups_with_dates[ $group['id'] ] = array(
                'joined_date' => ! empty( $group['group_joined_date'] ) ? substr( $group['group_joined_date'], 0, 10 ) : null,
                'left_date' => ! empty( $group['group_left_date'] ) ? substr( $group['group_left_date'], 0, 10 ) : null,
            );
        }
    }

    // Save the updated person data
    $result = $crm->storage->save_person( $username, $person_data, $groups_with_dates );

    if ( $result !== false ) {
        do_action( 'personal_crm_quick_update_saved', $username, $field, $sanitized_value );
        wp_send_json_success( array( 'message' => 'Updated successfully' ) );
    } else {
        wp_send_json_error( array( 'message' => 'Failed to save' ) );
    }
} );

