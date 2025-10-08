<?php
/**
 * Team/Group Management Admin Tool
 *
 * A web interface for creating and managing team/group JSON configuration
 */
namespace PersonalCRM;

require_once __DIR__ . '/../personal-crm.php';

// Error handling
ini_set( 'display_errors', 1 );
error_reporting( E_ALL );

// Special logic - check for team creation or person editing before main initialization
$crm = PersonalCrm::get_instance();
$current_group = $crm->get_current_group_from_params();
$route_person = function_exists( 'get_query_var' ) ? get_query_var( 'person' ) : '';
$is_editing_person = ! empty( $route_person );

// Redirect to selector if no group is specified (unless editing person, creating team, or handling POST)
$is_creating_team = isset( $_GET['create_team'] ) && $_GET['create_team'] === 'new';
$is_post_request = $_SERVER['REQUEST_METHOD'] === 'POST';
if ( ! $current_group && ! $is_editing_person && ! $is_creating_team && ! $is_post_request ) {
	header( 'Location: ' . $crm->build_url( 'select.php' ) );
	exit;
}

if ( $current_group === 'team' ) {
	header( 'Location: ' . $crm->build_url( 'select.php' ) );
	exit;
}

extract( PersonalCrm::get_globals() );

$config_file = $current_group ? __DIR__ . '/../' . $current_group . '.json' : null;
$action = $_POST['action'] ?? $_GET['action'] ?? 'dashboard';

// Determine active tab from route or query parameter
$request_uri = $_SERVER['REQUEST_URI'] ?? '';

// If editing a person, skip all group-related logic
if ( strpos( $request_uri, '/person/' ) !== false || $is_editing_person ) {
	$active_tab = 'person_edit';
} elseif ( strpos( $request_uri, '/links' ) !== false ) {
	$active_tab = 'team_links';
} elseif ( strpos( $request_uri, '/events' ) !== false ) {
	$active_tab = 'events';
} elseif ( strpos( $request_uri, '/audit' ) !== false ) {
	$active_tab = 'audit';
} elseif ( strpos( $request_uri, '/members' ) !== false ) {
	$active_tab = 'members';
} else {
	// Check for child group tabs dynamically (handle both full and short slugs)
	$found_person_tab = false;
	if ( $current_group ) {
		$temp_config = $crm->storage->get_group( $current_group );
		if ( $temp_config && ! empty( $temp_config['id'] ) ) {
			$child_groups = $crm->storage->get_child_groups( $temp_config['id'] );
			foreach ( $child_groups as $child ) {
				// Check for full slug
				if ( strpos( $request_uri, '/' . $child['slug'] ) !== false ) {
					$active_tab = $child['slug'];
					$found_person_tab = true;
					break;
				}
				// Check for short slug (without parent prefix)
				$short_slug = $child['slug'];
				if ( strpos( $short_slug, $current_group . '_' ) === 0 ) {
					$short_slug = substr( $short_slug, strlen( $current_group ) + 1 );
					if ( strpos( $request_uri, '/' . $short_slug ) !== false ) {
						$active_tab = $child['slug'];
						$found_person_tab = true;
						break;
					}
				}
			}
		}
	}

	if ( ! $found_person_tab ) {
		$active_tab = $_GET['tab'] ?? 'general';
	}
}
$is_adding_new = isset( $_GET['add'] ) && $_GET['add'] === 'new';

// Check if group exists in database and redirect to selector if not (unless already creating a team or editing a person)
if ( $current_group && ! $crm->storage->group_exists( $current_group ) && ! $is_creating_team && ! $is_editing_person ) {
	header( 'Location: ' . $crm->build_url( 'select.php' ) );
	exit;
}

// Check if we're editing a specific member or event
// Handle wp-app route parameter for admin/person/{person}
$edit_member = $_GET['edit_member'] ?? $route_person;
$edit_event_index = $_GET['edit_event'] ?? '';
$edit_data = null;
$is_editing_event = false;

// Dynamic is_editing_{type} variables
$person_editing_vars = array();

if ( ! empty( $edit_member ) ) {
	// When editing a person, we don't need a current_group since they can belong to multiple groups
	if ( $current_group ) {
		$config = $crm->storage->get_group( $current_group );
	}

	// Check all person types dynamically
	$all_person_types = $current_group ? $crm->storage->get_person_types( $current_group ) : array();
	$all_person_types[] = array( 'type_key' => 'alumni' );

	foreach ( $all_person_types as $ptype ) {
		$type_key = $ptype['type_key'];
		if ( isset( $config[ $type_key ][ $edit_member ] ) ) {
			$edit_data = $config[ $type_key ][ $edit_member ];
			$edit_data['username'] = $edit_member;
			$active_tab = $type_key;
			// Set is_editing_{type} variable dynamically
			$var_name = 'is_editing_' . $type_key;
			$$var_name = true;
			$person_editing_vars[ $type_key ] = true;
			break;
		} else {
			// Initialize as false
			$var_name = 'is_editing_' . $type_key;
			$$var_name = false;
		}
	}
} elseif ( $edit_event_index !== '' && is_numeric( $edit_event_index ) ) {
	$config = $crm->storage->get_group( $current_group );
	if ( isset( $config['events'][ $edit_event_index ] ) ) {
		$edit_data = $config['events'][ $edit_event_index ];
		$edit_data['event_index'] = $edit_event_index;
		$is_editing_event = true;
		$active_tab = 'events';
	}
}

/**
 * Save configuration to storage
 */

$message = '';
$error = '';


// Load helper functions
require_once __DIR__ . '/functions.php';

$message = '';
$error = '';

// Handle form submissions
require_once __DIR__ . '/actions.php';

// Load config for display (after any POST operations)
$config = $crm->storage->get_group( $current_group );
if ( ! $config ) {
	// Fallback default config (group should normally be created via create_team)
	$config = array(
		'activity_url_prefix' => '',
		'group_name' => '',
		'alumni' => array(),
		'events' => array(),
		'type' => 'team',
		'default' => false,
		'links' => array(),
	);
	// Initialize arrays for person types from database
	$person_types = $crm->storage->get_person_types( $current_group );
	foreach ( $person_types as $type ) {
		$config[ $type['type_key'] ] = array();
	}
}

// Handle POST requests before any HTML output (to allow redirects)
$people_tab_included = false;
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && $active_tab === 'person_edit' ) {
	// Include people.php early to handle POST, which may redirect
	require __DIR__ . '/tabs/people.php';
	$people_tab_included = true;
	// If we get here, POST was handled but didn't redirect - continue with HTML output
}

// Handle general settings POST
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['action'] ) && $_POST['action'] === 'save_general' ) {
	$group_obj = $crm->storage->get_group( $current_group );

	// Build config array from POST data
	$config_data = array(
		'group_name' => sanitize_text_field( $_POST['team_name'] ?? '' ),
		'activity_url_prefix' => sanitize_url( $_POST['activity_url_prefix'] ?? '' ),
		'type' => sanitize_text_field( $_POST['team_type'] ?? 'team' ),
		'display_icon' => sanitize_text_field( $_POST['display_icon'] ?? '' ),
		'sort_order' => intval( $_POST['sort_order'] ?? 0 ),
	);

	// Handle parent group
	$parent_slug = sanitize_text_field( $_POST['parent_group'] ?? '' );
	if ( ! empty( $parent_slug ) && $parent_slug !== 'none' ) {
		$parent_group = $crm->storage->get_group( $parent_slug );
		$config_data['parent_id'] = $parent_group ? $parent_group->id : null;
	} else {
		$config_data['parent_id'] = null;
	}

	// Handle default flag
	$is_default = isset( $_POST['is_default'] ) && $_POST['is_default'] === '1';
	if ( $is_default ) {
		// Remove default flag from other groups
		$available_groups = $crm->storage->get_available_groups();
		foreach ( $available_groups as $group_slug ) {
			if ( $group_slug !== $current_group ) {
				$other_group = $crm->storage->get_group( $group_slug );
				if ( $other_group && $other_group->is_default ) {
					$other_config = array(
						'group_name' => $other_group->group_name,
						'activity_url_prefix' => $other_group->activity_url_prefix,
						'type' => $other_group->type,
						'display_icon' => $other_group->display_icon,
						'sort_order' => $other_group->sort_order,
						'parent_id' => $other_group->parent_id,
						'default' => false
					);
					$crm->storage->save_group( $other_group->id, $other_config );
				}
			}
		}
		$config_data['default'] = true;
	} else {
		$config_data['default'] = false;
	}

	// Save group and get the new slug (may have changed due to name change)
	$new_slug = $crm->storage->save_group( $group_obj->id, $config_data );

	if ( $new_slug ) {
		// Always redirect after successful save
		header( 'Location: ' . $crm->build_url( 'admin/index.php', array( 'group' => $new_slug, 'tab' => 'general' ) ) );
		exit;
	} else {
		$error = 'Failed to save configuration.';
	}
}

?>
<!DOCTYPE html>
<html <?php echo function_exists( 'wp_app_language_attributes' ) ? wp_app_language_attributes() : 'lang="en"'; ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <title><?php echo function_exists( 'wp_app_title' ) ? wp_app_title( 'Admin' ) : 'Admin'; ?></title>
    <?php
    if ( ! function_exists( 'wp_app_enqueue_style' ) ) {
        echo '<link rel="stylesheet" href="' . plugin_dir_url( __FILE__ ) . 'assets/style.css">';
        echo '<link rel="stylesheet" href="' . plugin_dir_url( __FILE__ ) . 'assets/cmd-k.css">';
    }
    ?>
    <?php if ( function_exists( 'wp_app_head' ) ) wp_app_head(); ?>
</head>
<body class="wp-app-body">
    <?php if ( function_exists( 'wp_app_body_open' ) ) wp_app_body_open(); ?>
    <?php $crm->render_cmd_k_panel(); ?>

    <div class="container">
        <div class="header">
            <div class="header-content">
                <h1><a href="<?php echo $crm->build_url( 'admin/index.php' ); ?>" style="color: inherit; text-decoration: none;">Admin</a></h1>
            </div>
            <div class="navigation">
                <div class="group-switcher" style="display: inline-block; margin-right: 10px;">
                    <?php
                    $available_groups = $crm->storage->get_available_groups( true );
                    if ( $available_groups ) :
                    	?>
                    <select id="group-selector" onchange="switchGroup()">
                        <?php
                        foreach ( $available_groups as $group_slug ) {
                            $team_display_name = $crm->storage->get_group_name( $group_slug );
                            $selected = $group_slug === $current_group ? 'selected' : '';
                            echo '<option value="' . htmlspecialchars( $crm->build_url( 'admin/index.php', array( 'group' => $group_slug ) ) ) . '" ' . $selected . '>' . htmlspecialchars( $team_display_name ) . '</option>';
                        }
                        ?>
                    </select>
                <?php endif; ?>
                    <a href="<?php echo $crm->build_url( 'admin/index.php', array( 'create_team' => 'new' ) ); ?>" class="nav-link" style="font-size: 12px; padding: 6px 12px; margin-left: 5px;">+ New Group</a>
                </div>

            </div>
        </div>

        <?php if ( $message ) : ?>
            <div class="message success"><?php echo htmlspecialchars( $message ); ?></div>
        <?php endif; ?>

        <?php if ( $error ) : ?>
            <div class="message error"><?php echo htmlspecialchars( $error ); ?></div>
        <?php endif; ?>

        <?php
        // Show parent navigation link if this is a child group
        if ( $current_group && ! $is_creating_team ) {
            $current_group_config = $crm->storage->get_group( $current_group );
            if ( $current_group_config && ! empty( $current_group_config['parent_id'] ) ) {
                $parent_group = $crm->storage->get_group_by_id( $current_group_config['parent_id'] );
                if ( $parent_group ) {
                    ?>
                    <div style="margin-bottom: 20px;">
                        <a href="/crm/admin/group/<?php echo htmlspecialchars( $parent_group['slug'] ); ?>/" class="back-link-admin">← Back to <?php echo htmlspecialchars( $parent_group['group_name'] ); ?> Admin</a>
                    </div>
                    <?php
                }
            }
        }
        ?>

        <?php if ( $is_creating_team ) : ?>
            <!-- Create New Page -->
            <div style="margin-bottom: 20px;">
                <a href="<?php echo $crm->build_url( 'admin/index.php' ); ?>" class="back-link-admin">← Back to Admin Dashboard</a>
            </div>

            <h2>Create New</h2>
            <form method="post">
                <input type="hidden" name="action" value="create_team">
                <?php if ( $current_group ) : ?>
                    <input type="hidden" name="group" value="<?php echo htmlspecialchars( $current_group ); ?>">
                <?php endif; ?>
                <div class="form-group">
                    <label for="new_team_name">Name *</label>
                    <input type="text" id="new_team_name" name="new_team_name" required placeholder="e.g., Marketing" autofocus>
                </div>
                <div class="form-group">
                    <label for="new_team_slug">Slug *</label>
                    <input type="text" id="new_team_slug" name="new_team_slug" required placeholder="e.g., marketing" pattern="[a-z0-9_-]+" value="<?php echo $current_group ? htmlspecialchars( $current_group ) : ''; ?>">
                    <small class="text-small-muted">Only lowercase letters, numbers, hyphens, and underscores allowed. This will be used as the filename.</small>
                </div>
                <div class="form-group">
                    <label for="new_team_type">Type</label>
                    <select id="new_team_type" name="new_team_type">
                        <option value="team">Work (business context)</option>
                        <option value="group">Personal (social context)</option>
                    </select>
                    <small class="text-small-muted">Choose "Personal" for friends/acquaintances, or "Work" for business contexts.</small>
                </div>
                <div style="margin-top: 20px;">
                    <button type="submit" class="btn">Create</button>
                    <a href="<?php echo $crm->build_url( 'admin/index.php' ); ?>" class="btn btn-secondary" style="margin-left: 10px;">Cancel</a>
                </div>
            </form>
        <?php else : ?>

        <div class="nav-tabs">
            <a href="/crm/admin/<?php echo $current_group; ?>/" class="nav-tab <?php echo $active_tab === 'general' ? 'active' : ''; ?>">General</a>
            <a href="/crm/admin/<?php echo $current_group; ?>/links/" class="nav-tab <?php echo $active_tab === 'team_links' ? 'active' : ''; ?>">Links</a>
            <?php
            // Build navigation tabs for direct members + child groups
            $parent_config = $crm->storage->get_group( $current_group );
            $parent_group_id = $parent_config['id'];
            $child_groups = $crm->storage->get_child_groups( $parent_group_id );

            $nav_tabs = array(
                array(
                    'slug' => 'members',
                    'display_name' => 'Members',
                    'display_icon' => '👥',
                    'count' => count( $parent_config['members'] ?? array() )
                )
            );

            foreach ( $child_groups as $child ) {
                // Use short slug in URL (remove parent prefix)
                $url_slug = $child['slug'];
                if ( strpos( $url_slug, $current_group . '_' ) === 0 ) {
                    $url_slug = substr( $url_slug, strlen( $current_group ) + 1 );
                }

                $nav_tabs[] = array(
                    'slug' => $child['slug'], // Full slug for matching
                    'url_slug' => $url_slug,  // Short slug for URL
                    'display_name' => $child['group_name'],
                    'display_icon' => $child['display_icon'] ?: '',
                    'count' => count( $crm->storage->get_group_members( $child['id'], false ) )
                );
            }

            $all_tab_slugs = array_column( $nav_tabs, 'slug' );
            $total_people = array_sum( array_column( $nav_tabs, 'count' ) );
            ?>
            <?php if ( $crm->is_social_group( $current_group ) ) : ?>
                <?php
                $first_tab = $nav_tabs[0] ?? null;
                if ( $first_tab ) :
                ?>
                <a href="/crm/admin/<?php echo $current_group; ?>/<?php echo $first_tab['slug']; ?>/" class="nav-tab <?php echo $active_tab === $first_tab['slug'] ? 'active' : ''; ?>"><?php echo $first_tab['display_icon']; ?> <?php echo htmlspecialchars( $first_tab['display_name'] ); ?> (<?php echo $first_tab['count']; ?>)</a>
                <?php endif; ?>
            <?php else : ?>
            <div class="nav-dropdown">
                <span class="nav-tab nav-dropdown-trigger <?php echo in_array( $active_tab, $all_tab_slugs ) ? 'active' : ''; ?>">
                    People (<?php echo $total_people; ?>) ▾
                </span>
                <div class="nav-dropdown-menu">
                    <?php foreach ( $nav_tabs as $tab ) : ?>
                        <a href="/crm/admin/<?php echo $current_group; ?>/<?php echo $tab['url_slug'] ?? $tab['slug']; ?>/" class="nav-dropdown-item <?php echo $active_tab === $tab['slug'] ? 'active' : ''; ?>"><?php echo $tab['display_icon']; ?> <?php echo htmlspecialchars( $tab['display_name'] ); ?> (<?php echo $tab['count']; ?>)</a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <a href="/crm/admin/<?php echo $current_group; ?>/events/" class="nav-tab <?php echo $active_tab === 'events' ? 'active' : ''; ?>">Events</a>
            <a href="/crm/admin/<?php echo $current_group; ?>/audit/" class="nav-tab <?php echo $active_tab === 'audit' ? 'active' : ''; ?>">Audit</a>
        </div>

        <style>
        .nav-dropdown {
            position: relative;
            display: inline-block;
            vertical-align: top;
            margin: 0;
        }

        .nav-dropdown-trigger {
            cursor: pointer;
            display: inline-block;
            vertical-align: top;
            margin: 0;
            line-height: inherit;
        }

        .nav-dropdown-menu {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            background: white;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            min-width: 180px;
            z-index: 1000;
        }

        .nav-dropdown:hover .nav-dropdown-menu {
            display: block;
        }

        .nav-dropdown-item {
            display: block;
            padding: 8px 12px;
            color: inherit;
            text-decoration: none;
            border-bottom: 1px solid #eee;
        }

        .nav-dropdown-item:last-child {
            border-bottom: none;
        }

        .nav-dropdown-item:hover {
            background-color: #f5f5f5;
        }

        .nav-dropdown-item.active {
            background-color: #007cba;
            color: white;
        }

        @media (prefers-color-scheme: dark) {
            .nav-dropdown-menu {
                background: #2c3338;
                border-color: #50575e;
            }

            .nav-dropdown-item:hover {
                background-color: #3c434a;
            }

            .nav-dropdown-item {
                border-color: #50575e;
            }
        }

        .tab-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .tab-header h3 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .edit-group-link {
            font-size: 0.85em;
            font-weight: normal;
            text-decoration: none;
            color: #007cba;
            padding: 4px 10px;
            border-radius: 3px;
            transition: background-color 0.2s;
        }

        .edit-group-link:hover {
            background-color: rgba(0, 124, 186, 0.1);
        }

        @media (prefers-color-scheme: dark) {
            .edit-group-link {
                color: #72aee6;
            }

            .edit-group-link:hover {
                background-color: rgba(114, 174, 230, 0.1);
            }
        }
        </style>

        <script>
        // Links management functions (must be before tabs are loaded)
        function addLink(prefix) {
            const container = document.getElementById(prefix + 'links-container');
            const newRow = document.createElement('div');
            newRow.className = 'link-row';
            newRow.style.cssText = 'display: flex; gap: 10px; margin-bottom: 10px; align-items: center;';
            newRow.innerHTML = `
                <input type="text" name="link_text[]" placeholder="Link text (e.g., 'Project docs')" style="flex: 1;">
                <input type="url" name="link_url[]" placeholder="URL" style="flex: 2;">
                <button type="button" onclick="removeLink(this)" class="btn-remove">Remove</button>
            `;
            container.appendChild(newRow);
        }

        function removeLink(button) {
            const row = button.parentNode;
            const container = row.parentNode;

            // Don't remove if it's the last row - just clear it instead
            if (container.children.length === 1) {
                const inputs = row.querySelectorAll('input');
                inputs.forEach(input => input.value = '');
            } else {
                row.remove();
            }
        }
        </script>

        <?php
        // Include appropriate tab file based on active tab
        switch ( $active_tab ) {
            case 'general':
                require __DIR__ . '/tabs/general.php';
                break;
            case 'team_links':
                require __DIR__ . '/tabs/links.php';
                break;
            case 'events':
                require __DIR__ . '/tabs/events.php';
                break;
            case 'audit':
                require __DIR__ . '/tabs/audit.php';
                break;
            case 'person_edit':
                // Person editing without group context - just load people.php
                if ( ! $people_tab_included ) {
                    require __DIR__ . '/tabs/people.php';
                }
                break;
            default:
                // Check if it's a people tab (direct members or child group)
                $parent_config = $crm->storage->get_group( $current_group );
                $parent_group_id = $parent_config['id'];
                $child_groups = $crm->storage->get_child_groups( $parent_group_id );

                $is_person_tab = false;
                if ( $active_tab === 'members' ) {
                    $is_person_tab = true;
                } else {
                    foreach ( $child_groups as $child ) {
                        if ( $active_tab === $child['slug'] ) {
                            $is_person_tab = true;
                            break;
                        }
                    }
                }
                if ( $is_person_tab ) {
                    require __DIR__ . '/tabs/people.php';
                }
                break;
        }
        ?>

        <?php endif; ?>

    <script>
        <?php
        // Export person data as JavaScript constants
        if ( isset( $config['members'] ) ) {
            echo "const members = " . json_encode( $config['members'] ) . ";\n        ";
        }

        // Export child groups
        if ( ! empty( $config['id'] ) ) {
            $child_groups = $crm->storage->get_child_groups( $config['id'] );
            foreach ( $child_groups as $child ) {
                $js_var_name = str_replace( array( '-', '_' ), '', ucwords( $child['slug'], '-_' ) );
                $js_var_name = lcfirst( $js_var_name );
                if ( isset( $config[ $child['slug'] ] ) ) {
                    echo "const {$js_var_name} = " . json_encode( $config[ $child['slug'] ] ) . ";\n        ";
                }
            }
        }
        ?>
        const events = <?php echo json_encode( array_values( $config['events'] ?? array() ) ); ?>;
        
        function showTab(tabName) {
            // Hide all tab content
            const contents = document.querySelectorAll('.tab-content');
            contents.forEach(content => content.classList.remove('active'));
            
            // Remove active class from all tabs
            const tabs = document.querySelectorAll('.nav-tab');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            // Show selected tab content
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to clicked tab
            event.target.classList.add('active');
        }

        // Team links functionality
        let teamLinkIndex = <?php echo count( $config['team_links'] ?? array() ); ?>;

        document.addEventListener('DOMContentLoaded', function() {
            // Function to get next available index
            function getNextTeamLinkIndex() {
                const container = document.getElementById('team-links-container');
                const existingRows = container.querySelectorAll('.team-link-row');
                let maxIndex = -1;
                
                existingRows.forEach(row => {
                    const textInput = row.querySelector('input[type="text"]');
                    if (textInput && textInput.name) {
                        const match = textInput.name.match(/team_links\[(\d+)\]\[text\]/);
                        if (match) {
                            maxIndex = Math.max(maxIndex, parseInt(match[1]));
                        }
                    }
                });
                
                return maxIndex + 1;
            }
            
            // Add link button
            const addButton = document.getElementById('add-team-link');
            if (addButton) {
                addButton.addEventListener('click', function() {
                    const container = document.getElementById('team-links-container');
                    const currentIndex = getNextTeamLinkIndex();
                    const linkRow = document.createElement('div');
                    linkRow.className = 'team-link-row';
                    linkRow.style.cssText = 'display: flex; gap: 10px; margin-bottom: 10px; align-items: center;';

                    linkRow.innerHTML = `
                        <input type="text" name="team_links[${currentIndex}][text]" value="" placeholder="Link text (e.g., Linear)" style="flex: 0 0 150px;">
                        <input type="url" name="team_links[${currentIndex}][url]" value="" placeholder="https://..." style="flex: 1;">
                        <button type="button" class="remove-team-link btn-remove-personal">Remove</button>
                    `;

                    container.appendChild(linkRow);

                    // Add event listener to the new remove button
                    linkRow.querySelector('.remove-team-link').addEventListener('click', function() {
                        linkRow.remove();
                    });
                });
            }

            // Remove link buttons
            document.querySelectorAll('.remove-team-link').forEach(button => {
                button.addEventListener('click', function() {
                    this.parentElement.remove();
                });
            });
        });
        
        // All editing is now handled server-side via URL parameters
        
        // Enhanced timezone autocomplete functionality
        function initTimezoneAutocomplete() {
            const timezoneInputs = document.querySelectorAll('input[id$="timezone"]');
            
            timezoneInputs.forEach(function(input) {
                const prefix = input.id.replace('timezone', '');
                const suggestionsDiv = document.getElementById(prefix + 'timezone-suggestions');
                const dataScript = document.getElementById(prefix + 'timezone-data');

                if (!suggestionsDiv || !dataScript) return;

                const timezones = JSON.parse(dataScript.textContent);
                let selectedIndex = -1;
                let currentSuggestions = [];

                function showSuggestions(filteredTimezones) {
                    currentSuggestions = filteredTimezones;
                    selectedIndex = -1;

                    if (filteredTimezones.length === 0) {
                        suggestionsDiv.style.display = 'none';
                        return;
                    }

                    suggestionsDiv.innerHTML = '';
                    filteredTimezones.forEach(function(tz, index) {
                        const div = document.createElement('div');
                        div.className = 'timezone-suggestion';
                        div.textContent = tz.value + ' (' + tz.label + ')';
                        div.addEventListener('click', function() {
                            input.value = tz.value;
                            suggestionsDiv.style.display = 'none';
                        });
                        suggestionsDiv.appendChild(div);
                    });

                    suggestionsDiv.style.display = 'block';
                }

                function filterTimezones(query) {
                    if (!query) return [];

                    const lowerQuery = query.toLowerCase();
                    return timezones.filter(function(tz) {
                        return tz.value.toLowerCase().includes(lowerQuery) ||
                               tz.label.toLowerCase().includes(lowerQuery);
                    }).slice(0, 10); // Limit to 10 suggestions
                }

                input.addEventListener('input', function() {
                    const query = input.value.trim();
                    const filteredTimezones = filterTimezones(query);
                    showSuggestions(filteredTimezones);
                });

                input.addEventListener('keydown', function(e) {
                    if (suggestionsDiv.style.display === 'none') return;

                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        selectedIndex = Math.min(selectedIndex + 1, currentSuggestions.length - 1);
                        updateSelection();
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        selectedIndex = Math.max(selectedIndex - 1, -1);
                        updateSelection();
                    } else if (e.key === 'Enter') {
                        e.preventDefault();
                        if (selectedIndex >= 0 && currentSuggestions[selectedIndex]) {
                            input.value = currentSuggestions[selectedIndex].value;
                            suggestionsDiv.style.display = 'none';
                        } else if (currentSuggestions.length === 1) {
                            input.value = currentSuggestions[0].value;
                            suggestionsDiv.style.display = 'none';
                        } else {
                            // Validate if entered value is a valid timezone
                            const isValid = timezones.some(function(tz) {
                                return tz.value === input.value;
                            });
                            if (!isValid && input.value.trim()) {
                                // Try to find exact match or close match
                                const exactMatch = timezones.find(function(tz) {
                                    return tz.value.toLowerCase() === input.value.toLowerCase();
                                });
                                if (exactMatch) {
                                    input.value = exactMatch.value;
                                } else {
                                    // Clear invalid input
                                    input.value = '';
                                    alert('Please select a valid timezone from the suggestions.');
                                }
                            }
                            suggestionsDiv.style.display = 'none';
                        }
                    } else if (e.key === 'Escape') {
                        suggestionsDiv.style.display = 'none';
                    }
                });

                function updateSelection() {
                    const suggestions = suggestionsDiv.querySelectorAll('.timezone-suggestion');
                    suggestions.forEach(function(suggestion, index) {
                        if (index === selectedIndex) {
                            suggestion.classList.add('selected');
                        } else {
                            suggestion.classList.remove('selected');
                        }
                    });
                }

                // Hide suggestions when clicking outside
                document.addEventListener('click', function(e) {
                    if (!input.contains(e.target) && !suggestionsDiv.contains(e.target)) {
                        suggestionsDiv.style.display = 'none';
                    }
                });

                // Auto-select and validate on blur
                input.addEventListener('blur', function() {
                    setTimeout(function() {
                        if (input.value.trim()) {
                            // First, check if there's exactly one matching suggestion
                            const filteredTimezones = filterTimezones(input.value.trim());
                            if (filteredTimezones.length === 1) {
                                input.value = filteredTimezones[0].value;
                                suggestionsDiv.style.display = 'none';
                                return;
                            }

                            // Then validate if it's already a valid timezone
                            const isValid = timezones.some(function(tz) {
                                return tz.value === input.value;
                            });
                            if (!isValid) {
                                input.value = '';
                            }
                        }
                        suggestionsDiv.style.display = 'none';
                    }, 150); // Delay to allow click on suggestion
                });
            });
        }

       // Auto-generate slug from team name
        document.addEventListener('DOMContentLoaded', function() {
            const teamNameInput = document.getElementById('new_team_name');
            const teamSlugInput = document.getElementById('new_team_slug');
            
            if (teamNameInput && teamSlugInput) {
                teamNameInput.addEventListener('input', function() {
                    // Auto-generate slug if slug field is empty or matches previous auto-generated value
                    let slug = this.value
                        .toLowerCase()
                        .replace(/[^a-z0-9\s-]/g, '') // Remove special characters except spaces and hyphens
                        .replace(/\s+/g, '-') // Replace spaces with hyphens
                        .replace(/-+/g, '-') // Replace multiple hyphens with single hyphen
                        .replace(/^-|-$/g, ''); // Remove leading/trailing hyphens
                    teamSlugInput.value = slug;
                });
            }
        });

        // Event links management
        let eventLinkCounter = 0;
        function addEventLink() {
            const container = document.getElementById('event-links-container');
            const newRow = document.createElement('div');
            newRow.className = 'link-row';
            newRow.style.cssText = 'display: flex; gap: 10px; margin-bottom: 10px; align-items: center;';
            newRow.innerHTML = `
                <input type="text" name="event_links[${eventLinkCounter}][text]" 
                       placeholder="Link text (e.g., Zoom, Agenda)" 
                       class="form-select" style="flex: 0 0 200px;">
                <input type="url" name="event_links[${eventLinkCounter}][url]" 
                       placeholder="URL" 
                       class="form-select" style="flex: 1;">
                <button type="button" onclick="removeEventLink(this)" 
                        class="btn-large-remove">Remove</button>
            `;
            container.appendChild(newRow);
            
            // Setup rich text parsing for the new inputs
            const textInput = newRow.querySelector('input[type="text"]');
            const urlInput = newRow.querySelector('input[type="url"]');
            setupRichTextLinkParsing(textInput, urlInput);
            
            eventLinkCounter++;
        }

        function removeEventLink(button) {
            button.parentNode.remove();
        }

        // Rich text link parser
        function parseRichTextLink(pastedText) {
            // Try to extract URL from various formats
            const urlRegex = /(https?:\/\/[^\s]+)/gi;
            const matches = pastedText.match(urlRegex);
            
            if (matches && matches.length > 0) {
                // Extract the URL
                const url = matches[0];
                // Extract text by removing the URL and cleaning up
                let text = pastedText.replace(url, '').trim();
                
                // Remove common markdown link formatting if present
                text = text.replace(/^\[|\]$/g, ''); // Remove [ and ]
                text = text.replace(/^\(|\)$/g, ''); // Remove ( and )
                
                // If no meaningful text remains, try to get domain from URL
                if (!text || text.length < 2) {
                    try {
                        const urlObj = new URL(url);
                        text = urlObj.hostname.replace('www.', '');
                    } catch (e) {
                        text = 'Link';
                    }
                }
                
                return { text: text, url: url };
            }
            
            // Check if the entire pasted content looks like a URL
            if (urlRegex.test(pastedText.trim())) {
                try {
                    const urlObj = new URL(pastedText.trim());
                    return { 
                        text: urlObj.hostname.replace('www.', ''),
                        url: pastedText.trim()
                    };
                } catch (e) {
                    return null;
                }
            }
            
            return null;
        }

        function setupRichTextLinkParsing(textInput, urlInput) {
            textInput.addEventListener('paste', async function(e) {
                e.preventDefault();
                
                try {
                    // Try to read from clipboard with rich text support
                    if (navigator.clipboard && navigator.clipboard.read) {
                        const clipboardItems = await navigator.clipboard.read();
                        
                        for (const clipboardItem of clipboardItems) {
                            // Try HTML format first (rich text)
                            if (clipboardItem.types.includes('text/html')) {
                                const htmlBlob = await clipboardItem.getType('text/html');
                                const htmlText = await htmlBlob.text();
                                
                                // Parse HTML for links
                                const tempDiv = document.createElement('div');
                                tempDiv.innerHTML = htmlText;
                                const links = tempDiv.querySelectorAll('a[href]');
                                
                                if (links.length > 0 && !urlInput.value) {
                                    // Use the first link found
                                    const link = links[0];
                                    const linkText = link.textContent.trim();
                                    const linkUrl = link.href;
                                    
                                    textInput.value = linkText || linkUrl;
                                    urlInput.value = linkUrl;
                                    
                                    // Add visual feedback
                                    urlInput.classList.add('success-highlight');
                                    setTimeout(() => {
                                        urlInput.style.background = '';
                                    }, 1500);
                                    return;
                                }
                            }
                            
                            // Fallback to plain text
                            if (clipboardItem.types.includes('text/plain')) {
                                const textBlob = await clipboardItem.getType('text/plain');
                                const plainText = await textBlob.text();
                                
                                const parsed = parseRichTextLink(plainText);
                                if (parsed && !urlInput.value) {
                                    textInput.value = parsed.text;
                                    urlInput.value = parsed.url;
                                    
                                    // Add visual feedback
                                    urlInput.classList.add('success-highlight');
                                    setTimeout(() => {
                                        urlInput.style.background = '';
                                    }, 1500);
                                    return;
                                }
                                
                                // If no URL found, just paste the text normally
                                textInput.value = plainText;
                            }
                        }
                    } else {
                        // Fallback for older browsers - use clipboardData
                        const clipboardData = e.clipboardData || window.clipboardData;
                        if (clipboardData) {
                            // Try HTML first
                            let htmlData = clipboardData.getData('text/html');
                            if (htmlData) {
                                const tempDiv = document.createElement('div');
                                tempDiv.innerHTML = htmlData;
                                const links = tempDiv.querySelectorAll('a[href]');
                                
                                if (links.length > 0 && !urlInput.value) {
                                    const link = links[0];
                                    const linkText = link.textContent.trim();
                                    const linkUrl = link.href;
                                    
                                    textInput.value = linkText || linkUrl;
                                    urlInput.value = linkUrl;
                                    
                                    // Add visual feedback
                                    urlInput.classList.add('success-highlight');
                                    setTimeout(() => {
                                        urlInput.style.background = '';
                                    }, 1500);
                                    return;
                                }
                            }
                            
                            // Fallback to plain text
                            const plainText = clipboardData.getData('text/plain') || clipboardData.getData('text');
                            if (plainText) {
                                const parsed = parseRichTextLink(plainText);
                                if (parsed && !urlInput.value) {
                                    textInput.value = parsed.text;
                                    urlInput.value = parsed.url;
                                    
                                    // Add visual feedback
                                    urlInput.classList.add('success-highlight');
                                    setTimeout(() => {
                                        urlInput.style.background = '';
                                    }, 1500);
                                } else {
                                    textInput.value = plainText;
                                }
                            }
                        }
                    }
                } catch (error) {
                    console.log('Clipboard API failed, falling back to normal paste:', error);
                    // Allow default paste behavior
                    textInput.focus();
                    document.execCommand('paste');
                }
            });
        }

        // Initialize event link handlers
        document.addEventListener('DOMContentLoaded', function() {
            const addEventLinkBtn = document.getElementById('add-event-link-btn');
            if (addEventLinkBtn) {
                addEventLinkBtn.addEventListener('click', addEventLink);
            }

            // Initialize counter based on existing links
            const existingLinks = document.querySelectorAll('#event-links-container .link-row');
            eventLinkCounter = existingLinks.length;

            // Add remove handlers to existing links
            document.querySelectorAll('.remove-link-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    removeEventLink(this);
                });
            });

            // Setup rich text parsing for existing link inputs
            document.querySelectorAll('#event-links-container .link-row').forEach(row => {
                const textInput = row.querySelector('input[type="text"]');
                const urlInput = row.querySelector('input[type="url"]');
                if (textInput && urlInput) {
                    setupRichTextLinkParsing(textInput, urlInput);
                }
            });
        });

        // Initialize timezone autocomplete when page loads
        document.addEventListener('DOMContentLoaded', initTimezoneAutocomplete);

        // Filter functionality for audit table
        function filterAuditTable() {
            const typeFilter = document.getElementById('type-filter').value;
            const scoreFilter = document.getElementById('score-filter').value;
            const table = document.getElementById('audit-table');
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const type = row.getAttribute('data-type');
                const score = parseInt(row.getAttribute('data-score'));

                let showRow = true;

                // Filter by type
                if (typeFilter && type !== typeFilter) {
                    showRow = false;
                }

                // Filter by score range
                if (scoreFilter) {
                    if (scoreFilter === 'poor' && score >= 50) showRow = false;
                    if (scoreFilter === 'fair' && (score < 50 || score >= 80)) showRow = false;
                    if (scoreFilter === 'good' && (score < 80 || score >= 90)) showRow = false;
                    if (scoreFilter === 'excellent' && score < 90) showRow = false;
                }

                row.style.display = showRow ? '' : 'none';
            }
        }
    </script>
    
    <!-- Footer with admin/privacy links -->
    <footer class="footer-admin">
        <a href="#" id="privacy-toggle" onclick="togglePrivacyMode(); return false;" class="text-muted" style="text-decoration: none; margin-right: 15px;">
            <span id="privacy-status">🔓 Privacy Mode OFF</span>
        </a>
        <a href="<?php echo $crm->build_url( 'index.php' ); ?>" class="text-muted" style="text-decoration: none;">👥 Overview</a>
    </footer>
    
    <?php
    if ( function_exists( 'wp_app_enqueue_script' ) ) {
        wp_app_enqueue_script( 'personal-crm-admin-js', plugin_dir_url( dirname( __FILE__ ) ) . 'assets/admin.js' );
        wp_app_enqueue_script( 'personal-crm-script-js', plugin_dir_url( dirname( __FILE__ ) ) . 'assets/script.js' );
    } else {
        echo '<script src="' . plugin_dir_url( dirname( __FILE__ ) ) . 'assets/admin.js"></script>';
        echo '<script src="' . plugin_dir_url( dirname( __FILE__ ) ) . 'assets/script.js"></script>';
    }
	if ( function_exists( '\wp_app_body_close' ) ) \wp_app_body_close();
    ?>
    <?php $crm->init_cmd_k_js(); ?>
</body>
</html>
