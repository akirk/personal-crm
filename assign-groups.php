<?php
/**
 * Group Assignment Utility
 *
 * Batch-assign people to groups with an easy workflow.
 * URL: /crm/assign-groups
 *
 * Default behavior: Shows people without any group assignment.
 *
 * Supported query parameters:
 *   - person[]=user1&person[]=user2  Load specific people by username
 *   - query=in-group&group=slug      Load people from a specific group
 */
namespace PersonalCRM;

require_once __DIR__ . '/personal-crm.php';

extract( PersonalCrm::get_globals() );

$message = '';
$message_type = '';

// Handle POST actions
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['action'] ) ) {
	$action  = sanitize_text_field( $_POST['action'] );
	$is_ajax = isset( $_POST['format'] ) && $_POST['format'] === 'json';

	if ( $action === 'assign_groups' ) {
		$person_id = intval( $_POST['person_id'] ?? 0 );
		$group_ids = array_map( 'intval', $_POST['group_ids'] ?? array() );

		if ( $person_id && ! empty( $group_ids ) ) {
			$assigned_count = 0;
			foreach ( $group_ids as $group_id ) {
				if ( $crm->storage->add_person_to_group( $person_id, $group_id ) ) {
					$assigned_count++;
				}
			}
			$message      = "Assigned to $assigned_count group(s).";
			$message_type = 'success';
		}

		if ( $is_ajax ) {
			header( 'Content-Type: application/json' );
			echo wp_json_encode( array( 'success' => $message_type === 'success', 'message' => $message ) );
			exit;
		}
	} elseif ( $action === 'create_group' ) {
		$group_name = sanitize_text_field( $_POST['new_group_name'] ?? '' );
		$group_type = sanitize_text_field( $_POST['new_group_type'] ?? 'group' );
		$person_id  = intval( $_POST['person_id'] ?? 0 );

		if ( ! empty( $group_name ) ) {
			$group_slug = sanitize_title( $group_name );
			$group_slug = str_replace( '-', '_', $group_slug );

			$new_slug = $crm->storage->create_group( $group_name, $group_slug, $group_type );
			if ( $new_slug ) {
				$new_group = $crm->storage->get_group( $new_slug );
				if ( $person_id && $new_group ) {
					$crm->storage->add_person_to_group( $person_id, $new_group->id );
					$message = "Created and assigned group: $group_name";
				} else {
					$message = "Created group: $group_name";
				}
				$message_type = 'success';
			} else {
				$message      = "Failed to create group (may already exist)";
				$message_type = 'error';
			}
		}

		if ( $is_ajax ) {
			header( 'Content-Type: application/json' );
			$response = array( 'success' => $message_type === 'success', 'message' => $message );
			if ( $message_type === 'success' && isset( $new_group ) && $new_group ) {
				$response['group_id'] = $new_group->id;
			}
			echo wp_json_encode( $response );
			exit;
		}
	}
}

// Load people based on query parameters
$people = array();
$query_type = sanitize_text_field( $_GET['query'] ?? '' );
$query_description = '';

if ( ! empty( $_GET['person'] ) ) {
	// Supports PHP array syntax: ?person[]=user1&person[]=user2
	$usernames = is_array( $_GET['person'] )
		? array_map( 'sanitize_text_field', $_GET['person'] )
		: array( sanitize_text_field( $_GET['person'] ) );
	$people = $crm->storage->get_people_by_usernames( $usernames );
	$query_description = count( $people ) . ' people from URL';
} elseif ( $query_type === 'in-group' && ! empty( $_GET['group'] ) ) {
	$group_slug = sanitize_text_field( $_GET['group'] );
	$group_obj = $crm->storage->get_group( $group_slug );
	if ( $group_obj ) {
		$people = $group_obj->get_members();
		$query_description = count( $people ) . ' people in ' . $group_obj->group_name;
	}
} else {
	// Default: show people without groups
	$people = $crm->storage->get_people_without_groups();
	$query_description = count( $people ) . ' people without groups';
}

// Convert to indexed array for navigation
$people_list = array_values( $people );
$people_keys = array_keys( $people );

// Current person index (0-based)
$current_index = intval( $_GET['index'] ?? 0 );
if ( $current_index < 0 ) $current_index = 0;
if ( $current_index >= count( $people_list ) ) $current_index = max( 0, count( $people_list ) - 1 );

$current_person = $people_list[ $current_index ] ?? null;

// Load all groups for selection
$all_groups = $crm->storage->get_all_groups_with_hierarchy();

// Prepare data for client-side rendering
$people_data = array();
foreach ( $people_list as $person ) {
	$active_group_ids    = array();
	$active_group_labels = array();
	foreach ( $person->groups as $g ) {
		if ( empty( $g['group_left_date'] ) ) {
			$active_group_ids[]    = (int) $g['id'];
			$active_group_labels[] = $g['display_icon'] . ' ' . $g['group_name'];
		}
	}
	$people_data[] = array(
		'id'           => (int) $person->id,
		'username'     => $person->username,
		'name'         => $person->get_display_name_with_nickname(),
		'short_name'   => $person->name,
		'location'     => $person->location ?? '',
		'url'          => $crm->build_url( 'person.php', array( 'person' => $person->username ) ),
		'group_ids'    => $active_group_ids,
		'group_labels' => $active_group_labels,
	);
}

// Build base URL for navigation (preserve query params)
$base_params = array();
if ( ! empty( $_GET['person'] ) ) {
	$base_params['person'] = is_array( $_GET['person'] )
		? array_map( 'sanitize_text_field', $_GET['person'] )
		: array( sanitize_text_field( $_GET['person'] ) );
}
if ( ! empty( $_GET['query'] ) ) {
	$base_params['query'] = $_GET['query'];
}
if ( ! empty( $_GET['group'] ) ) {
	$base_params['group'] = $_GET['group'];
}

function build_nav_url( $crm, $base_params, $index ) {
	$params = $base_params;
	$params['index'] = $index;
	return home_url( '/crm/assign-groups' ) . '?' . http_build_query( $params );
}

?>
<!DOCTYPE html>
<html <?php echo function_exists( '\wp_app_language_attributes' ) ? \wp_app_language_attributes() : 'lang="en"'; ?>>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="color-scheme" content="light dark">
	<title><?php echo function_exists( '\wp_app_title' ) ? \wp_app_title( 'Assign Groups' ) : 'Assign Groups'; ?></title>
	<?php
	if ( function_exists( '\wp_app_enqueue_style' ) ) {
		wp_app_enqueue_style( 'personal-crm-style', plugin_dir_url( __FILE__ ) . 'assets/style.css' );
	} else {
		echo '<link rel="stylesheet" href="assets/style.css">';
	}
	?>
	<?php if ( function_exists( '\wp_app_head' ) ) \wp_app_head(); ?>
	<style>
		.assign-groups-layout {
			display: grid;
			grid-template-columns: 1fr 350px;
			gap: 24px;
			margin-top: 20px;
		}
		.query-bar {
			display: flex;
			gap: 12px;
			align-items: center;
			flex-wrap: wrap;
			padding: 16px;
			background: light-dark(#f5f5f5, #2a2a2a);
			border-radius: 8px;
			margin-bottom: 20px;
		}
		.query-bar a.query-button {
			padding: 8px 16px;
			background: light-dark(#fff, #333);
			border: 1px solid light-dark(#ddd, #444);
			border-radius: 6px;
			text-decoration: none;
			color: inherit;
			font-size: 14px;
		}
		.query-bar a.query-button:hover {
			background: light-dark(#e8e8e8, #444);
		}
		.query-bar a.query-button.active {
			background: #0073aa;
			color: white;
			border-color: #0073aa;
		}
		.query-bar select {
			padding: 8px 12px;
			border-radius: 6px;
			border: 1px solid light-dark(#ddd, #444);
			background: light-dark(#fff, #333);
			color: inherit;
		}
		.people-panel {
			background: light-dark(#fff, #1e1e1e);
			border-radius: 8px;
			padding: 20px;
			border: 1px solid light-dark(#e0e0e0, #333);
		}
		.groups-panel {
			background: light-dark(#fff, #1e1e1e);
			border-radius: 8px;
			padding: 20px;
			border: 1px solid light-dark(#e0e0e0, #333);
			position: sticky;
			top: 20px;
		}
		.person-card {
			padding: 16px;
			background: light-dark(#f9f9f9, #252525);
			border-radius: 8px;
			margin-bottom: 16px;
		}
		.person-card.current {
			border: 2px solid #0073aa;
			background: light-dark(#f0f7fc, #1a2a3a);
		}
		.person-card .person-name {
			font-size: 18px;
			font-weight: 600;
			margin-bottom: 4px;
		}
		.person-card .person-name a {
			color: inherit;
			text-decoration: none;
		}
		.person-card .person-name a:hover {
			text-decoration: underline;
		}
		.person-card .person-username {
			color: light-dark(#666, #999);
			font-size: 14px;
		}
		.person-card .current-groups {
			margin-top: 8px;
			font-size: 13px;
			color: light-dark(#666, #999);
		}
		.person-card .current-groups span {
			display: inline-block;
			background: light-dark(#e8e8e8, #333);
			padding: 2px 8px;
			border-radius: 4px;
			margin-right: 4px;
			margin-top: 4px;
		}
		.navigation-buttons {
			display: flex;
			gap: 12px;
			margin-top: 16px;
		}
		.navigation-buttons a, .navigation-buttons button {
			padding: 10px 20px;
			border-radius: 6px;
			text-decoration: none;
			font-size: 14px;
			cursor: pointer;
			border: none;
		}
		.navigation-buttons a {
			background: light-dark(#e8e8e8, #333);
			color: inherit;
		}
		.navigation-buttons a:hover {
			background: light-dark(#ddd, #444);
		}
		.navigation-buttons a.disabled {
			opacity: 0.5;
			pointer-events: none;
		}
		.group-chips {
			display: flex;
			flex-wrap: wrap;
			gap: 8px;
			margin-bottom: 16px;
		}
		.group-chip {
			padding: 8px 14px;
			background: light-dark(#f0f0f0, #333);
			border: 2px solid transparent;
			border-radius: 6px;
			cursor: pointer;
			font-size: 14px;
			transition: all 0.15s;
		}
		.group-chip:hover {
			background: light-dark(#e0e0e0, #444);
		}
		.group-chip.selected {
			background: #0073aa;
			color: white;
			border-color: #005a87;
		}
		.group-chip.already-member {
			opacity: 0.5;
			cursor: not-allowed;
			text-decoration: line-through;
		}
		.groups-search {
			width: 100%;
			padding: 10px 12px;
			border: 1px solid light-dark(#ddd, #444);
			border-radius: 6px;
			margin-bottom: 16px;
			background: light-dark(#fff, #2a2a2a);
			color: inherit;
		}
		.create-group-section {
			margin-top: 20px;
			padding-top: 16px;
			border-top: 1px solid light-dark(#e0e0e0, #333);
		}
		.create-group-section summary {
			cursor: pointer;
			font-weight: 500;
			color: light-dark(#666, #999);
		}
		.create-group-form {
			margin-top: 12px;
			display: flex;
			flex-direction: column;
			gap: 8px;
		}
		.create-group-form input, .create-group-form select {
			padding: 8px 12px;
			border: 1px solid light-dark(#ddd, #444);
			border-radius: 6px;
			background: light-dark(#fff, #2a2a2a);
			color: inherit;
		}
		.assign-button {
			width: 100%;
			padding: 14px;
			background: #0073aa;
			color: white;
			border: none;
			border-radius: 6px;
			font-size: 16px;
			font-weight: 500;
			cursor: pointer;
			margin-top: 16px;
		}
		.assign-button:hover {
			background: #005a87;
		}
		.assign-button:disabled {
			background: #999;
			cursor: not-allowed;
		}
		.selected-groups-summary {
			margin-top: 12px;
			padding: 12px;
			background: light-dark(#f0f7fc, #1a2a3a);
			border-radius: 6px;
			font-size: 14px;
		}
		.message {
			padding: 12px 16px;
			border-radius: 6px;
			margin-bottom: 16px;
		}
		.message.success {
			background: light-dark(#d4edda, #1a3a1a);
			color: light-dark(#155724, #90ee90);
		}
		.message.error {
			background: light-dark(#f8d7da, #3a1a1a);
			color: light-dark(#721c24, #ff9090);
		}
		.empty-state {
			text-align: center;
			padding: 40px;
			color: light-dark(#666, #999);
		}
		.progress-indicator {
			font-size: 14px;
			color: light-dark(#666, #999);
			margin-bottom: 12px;
		}
		.queue-item {
			padding: 8px 12px;
			border-radius: 4px;
			margin-bottom: 4px;
			display: flex;
			justify-content: space-between;
			align-items: center;
		}
		.queue-item--current { background: light-dark(#e8f4fc, #1a3a4a); }
		.queue-item--assigned .queue-status { color: #28a745; }
		.queue-item--past .queue-status { opacity: 0.4; }
		.queue-item--future .queue-status { opacity: 0.3; }
		.queue-item a {
			color: inherit;
			text-decoration: none;
		}
		.queue-item a:hover { text-decoration: underline; }
		.assign-skip-link {
			display: block;
			text-align: center;
			margin-top: 10px;
			font-size: 13px;
			color: light-dark(#888, #777);
			text-decoration: none;
			cursor: pointer;
		}
		.assign-skip-link:hover { text-decoration: underline; }
		.create-suggestion {
			display: none;
			align-items: center;
			gap: 8px;
			padding: 8px 12px;
			margin-bottom: 16px;
			background: light-dark(#f0f7fc, #1a2a3a);
			border: 1px dashed light-dark(#aad4ed, #3a5a7a);
			border-radius: 6px;
			font-size: 14px;
			cursor: pointer;
		}
		.create-suggestion:hover { background: light-dark(#e0eef8, #1e3347); }
		.create-suggestion-label { flex: 1; }
		.create-suggestion-type {
			padding: 4px 6px;
			border: 1px solid light-dark(#ccc, #555);
			border-radius: 4px;
			background: light-dark(#fff, #2a2a2a);
			color: inherit;
			font-size: 12px;
			cursor: pointer;
		}
	</style>
</head>
<body class="wp-app-body">
	<?php if ( function_exists( '\wp_app_body_open' ) ) \wp_app_body_open(); ?>

	<div class="container">
		<div class="header">
			<h1>Assign Groups</h1>
			<div class="navigation">
				<a href="<?php echo home_url( '/crm/' ); ?>" class="nav-link">← Back to CRM</a>
			</div>
		</div>

		<?php if ( $message ) : ?>
			<div class="message <?php echo esc_attr( $message_type ); ?>"><?php echo esc_html( $message ); ?></div>
		<?php endif; ?>

		<!-- Query Bar -->
		<div class="query-bar">
			<span>Load people:</span>
			<a href="<?php echo home_url( '/crm/assign-groups' ); ?>"
			   class="query-button <?php echo empty( $_GET['person'] ) && $query_type !== 'in-group' ? 'active' : ''; ?>">
				Without Groups
			</a>
			<select onchange="if(this.value) window.location.href=this.value">
				<option value="">From Group...</option>
				<?php foreach ( $all_groups as $group ) : ?>
					<option value="<?php echo esc_url( home_url( '/crm/assign-groups?query=in-group&group=' . $group['slug'] ) ); ?>">
						<?php echo esc_html( $group['display_icon'] . ' ' . $group['hierarchical_name'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<?php if ( $query_description ) : ?>
				<span style="margin-left: auto; color: light-dark(#666, #999);">
					<?php echo esc_html( $query_description ); ?>
				</span>
			<?php endif; ?>
		</div>

		<?php if ( empty( $people_list ) ) : ?>
			<?php
			// Get recently assigned people (those who have at least one group)
			global $wpdb;
			$recently_assigned = $wpdb->get_results(
				"SELECT p.*, MAX(pg.created_at) as last_assigned
				 FROM {$wpdb->prefix}personal_crm_people p
				 INNER JOIN {$wpdb->prefix}personal_crm_people_groups pg ON p.id = pg.person_id
				 GROUP BY p.id
				 ORDER BY last_assigned DESC
				 LIMIT 20",
				ARRAY_A
			);
			$recently_assigned_people = array();
			foreach ( $recently_assigned as $row ) {
				$recently_assigned_people[] = $crm->storage->get_person( $row['username'] );
			}
			?>
			<div class="empty-state" style="padding: 20px;">
				<h2 style="margin-bottom: 8px;">All Done!</h2>
				<p style="margin-bottom: 24px;">Everyone has been assigned to at least one group.</p>
			</div>
			<?php if ( ! empty( $recently_assigned_people ) ) : ?>
				<div class="people-panel" style="margin-top: 20px;">
					<h3 style="margin-bottom: 16px;">Recently Assigned People</h3>
					<div style="max-height: 400px; overflow-y: auto;">
						<?php foreach ( $recently_assigned_people as $person ) : ?>
							<div class="person-card">
								<div class="person-name">
									<a href="<?php echo esc_url( $crm->build_url( 'person.php', array( 'person' => $person->username ) ) ); ?>">
										<?php echo esc_html( $person->get_display_name_with_nickname() ); ?>
									</a>
								</div>
								<div class="person-username">@<?php echo esc_html( $person->username ); ?></div>
								<div class="current-groups">
									<?php foreach ( $person->groups as $group ) : ?>
										<?php if ( empty( $group['group_left_date'] ) ) : ?>
											<span><?php echo esc_html( $group['display_icon'] . ' ' . $group['group_name'] ); ?></span>
										<?php endif; ?>
									<?php endforeach; ?>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>
		<?php else : ?>
			<div class="assign-groups-layout">
				<!-- Left Panel: People Queue -->
				<div class="people-panel">
					<div class="progress-indicator" id="progress-indicator">
						Person <?php echo $current_index + 1; ?> of <?php echo count( $people_list ); ?>
					</div>

					<?php if ( $current_person ) : ?>
						<div class="person-card current">
							<div class="person-name"><a id="current-person-name-link" href="<?php echo esc_url( $crm->build_url( 'person.php', array( 'person' => $current_person->username ) ) ); ?>"><?php echo esc_html( $current_person->get_display_name_with_nickname() ); ?></a></div>
							<div class="person-username" id="current-person-username">@<?php echo esc_html( $current_person->username ); ?></div>
							<div class="person-username" id="current-person-location"<?php echo empty( $current_person->location ) ? ' style="display:none"' : ''; ?>>📍 <?php echo esc_html( $current_person->location ?? '' ); ?></div>
							<div class="current-groups" id="current-person-groups">
								Current groups:
								<?php if ( empty( $current_person->groups ) ) : ?>
									<em>None</em>
								<?php else : ?>
									<?php foreach ( $current_person->groups as $group ) : ?>
										<?php if ( empty( $group['group_left_date'] ) ) : ?>
											<span><?php echo esc_html( $group['display_icon'] . ' ' . $group['group_name'] ); ?></span>
										<?php endif; ?>
									<?php endforeach; ?>
								<?php endif; ?>
							</div>
						</div>

						<div class="navigation-buttons">
							<a id="nav-prev" <?php if ( $current_index > 0 ) : ?>href="<?php echo esc_url( build_nav_url( $crm, $base_params, $current_index - 1 ) ); ?>"<?php else : ?>class="disabled"<?php endif; ?>>← Previous</a>
							<a id="nav-next" <?php if ( $current_index < count( $people_list ) - 1 ) : ?>href="<?php echo esc_url( build_nav_url( $crm, $base_params, $current_index + 1 ) ); ?>"<?php else : ?>class="disabled"<?php endif; ?>>Skip →</a>
						</div>
					<?php endif; ?>

					<!-- Queue preview -->
					<h4 style="margin-top: 24px; margin-bottom: 12px;">Queue</h4>
					<div id="people-queue" style="max-height: 300px; overflow-y: auto;">
						<?php foreach ( $people_list as $idx => $person ) : ?>
							<div class="queue-item <?php echo $idx === $current_index ? 'queue-item--current' : ( $idx < $current_index ? 'queue-item--past' : 'queue-item--future' ); ?>" data-index="<?php echo $idx; ?>">
								<span>
									<span class="queue-status"><?php echo $idx === $current_index ? '→' : ( $idx < $current_index ? '–' : '○' ); ?></span>
									<a href="<?php echo esc_url( $crm->build_url( 'person.php', array( 'person' => $person->username ) ) ); ?>"><?php echo esc_html( $person->name ); ?></a>
								</span>
								<?php if ( $idx !== $current_index ) : ?>
									<a href="#" data-jump-index="<?php echo $idx; ?>" style="font-size: 12px;">Jump</a>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>
				</div>

				<!-- Right Panel: Groups Selection -->
				<div class="groups-panel">
					<?php if ( $current_person ) : ?>
						<form method="post" action="<?php echo home_url( '/crm/assign-groups' ) . '?' . http_build_query( array_merge( $base_params, array( 'index' => $current_index ) ) ); ?>" id="assign-form">
							<input type="hidden" name="action" value="assign_groups">
							<input type="hidden" name="person_id" value="<?php echo esc_attr( $current_person->id ); ?>">

							<h3>Select Groups</h3>
							<input type="text" class="groups-search" placeholder="Filter groups..." id="groups-search" oninput="filterGroups()">

							<div class="group-chips" id="group-chips">
								<?php
								$current_group_ids = array();
								foreach ( $current_person->groups as $g ) {
									if ( empty( $g['group_left_date'] ) ) {
										$current_group_ids[] = $g['id'];
									}
								}
								?>
								<?php foreach ( $all_groups as $group ) : ?>
									<?php $is_member = in_array( $group['id'], $current_group_ids ); ?>
									<label class="group-chip <?php echo $is_member ? 'already-member' : ''; ?>"
									       data-name="<?php echo esc_attr( strtolower( $group['hierarchical_name'] ) ); ?>"
									       data-group-id="<?php echo esc_attr( $group['id'] ); ?>"
									       <?php echo $is_member ? 'title="Already a member"' : ''; ?>>
										<input type="checkbox" name="group_ids[]" value="<?php echo esc_attr( $group['id'] ); ?>"
										       style="display: none;" <?php echo $is_member ? 'disabled' : ''; ?>
										       onchange="updateSelectedSummary()">
										<?php echo esc_html( $group['display_icon'] . ' ' . $group['hierarchical_name'] ); ?>
									</label>
								<?php endforeach; ?>
							</div>

							<div class="create-suggestion" id="create-suggestion">
								<span class="create-suggestion-label" id="create-suggestion-label"></span>
								<select class="create-suggestion-type" id="create-suggestion-type">
									<option value="group">Personal</option>
									<option value="team">Work</option>
								</select>
							</div>

							<div class="selected-groups-summary" id="selected-summary" style="display: none;">
								Selected: <span id="selected-count">0</span> group(s)
							</div>

							<button type="submit" class="assign-button" id="assign-button" disabled>
								Assign & Next →
							</button>
							<?php if ( $current_index < count( $people_list ) - 1 ) : ?>
								<a class="assign-skip-link" id="assign-skip-link">Skip without assigning →</a>
							<?php endif; ?>
						</form>
					<?php else : ?>
						<p>Select a person to assign groups.</p>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>
	</div>

	<?php if ( function_exists( '\wp_app_body_close' ) ) \wp_app_body_close(); ?>

	<script>
		const crmPeople = <?php echo wp_json_encode( $people_data ); ?>;
		let currentIndex = <?php echo $current_index; ?>;
		const assignedInSession = new Set();

		function buildNavUrl(index) {
			const url = new URL(window.location.href);
			url.searchParams.set('index', index);
			return url.toString();
		}

		function filterGroups() {
			const search = document.getElementById('groups-search').value.toLowerCase().trim();
			document.querySelectorAll('.group-chip').forEach(chip => {
				chip.style.display = chip.dataset.name.includes(search) ? '' : 'none';
			});

			const suggestion = document.getElementById('create-suggestion');
			const label = document.getElementById('create-suggestion-label');
			if (!suggestion) return;

			if (!search) {
				suggestion.style.display = 'none';
				return;
			}

			const exactMatch = Array.from(document.querySelectorAll('.group-chip')).some(
				chip => chip.dataset.name === search
			);
			if (exactMatch) {
				suggestion.style.display = 'none';
			} else {
				const displayName = search.charAt(0).toUpperCase() + search.slice(1);
				label.textContent = '➕ Create "' + displayName + '"';
				suggestion.style.display = 'flex';
			}
		}

		async function createGroupFromSearch() {
			const searchInput = document.getElementById('groups-search');
			const typeSelect = document.getElementById('create-suggestion-type');
			const name = searchInput.value.trim();
			if (!name) return;

			const suggestion = document.getElementById('create-suggestion');
			suggestion.style.opacity = '0.5';
			suggestion.style.pointerEvents = 'none';

			const person = crmPeople[currentIndex];
			const formData = new FormData();
			formData.set('action', 'create_group');
			formData.set('format', 'json');
			formData.set('new_group_name', name);
			formData.set('new_group_type', typeSelect ? typeSelect.value : 'group');
			formData.set('person_id', person.id);

			try {
				var response = await fetch(buildNavUrl(currentIndex), { method: 'POST', body: formData });
				var data = await response.json();

				if (data.success && data.group_id) {
					const displayName = name.charAt(0).toUpperCase() + name.slice(1);
					const groupType = typeSelect ? typeSelect.value : 'group';
					const icon = groupType === 'team' ? '👥' : '👤';
					const label = icon + ' ' + displayName;

					const chip = document.createElement('label');
					chip.className = 'group-chip selected';
					chip.dataset.name = name.toLowerCase();
					chip.dataset.groupId = data.group_id;

					const checkbox = document.createElement('input');
					checkbox.type = 'checkbox';
					checkbox.name = 'group_ids[]';
					checkbox.value = data.group_id;
					checkbox.checked = true;
					checkbox.style.display = 'none';
					checkbox.addEventListener('change', updateSelectedSummary);
					chip.appendChild(checkbox);
					chip.appendChild(document.createTextNode(label));
					chip.addEventListener('click', function(e) {
						if (this.classList.contains('already-member')) { e.preventDefault(); return; }
						var cb = this.querySelector('input[type="checkbox"]');
						if (cb && !cb.disabled) { cb.checked = !cb.checked; updateSelectedSummary(); }
					});

					document.getElementById('group-chips').appendChild(chip);

					person.group_ids.push(parseInt(data.group_id));
					person.group_labels.push(label);

					searchInput.value = '';
					suggestion.style.display = 'none';
					updateSelectedSummary();
				}
			} catch (err) {
				// silently restore on failure
			}

			suggestion.style.opacity = '';
			suggestion.style.pointerEvents = '';
		}

		function updateSelectedSummary() {
			const checkboxes = document.querySelectorAll('#group-chips input[type="checkbox"]:checked:not(:disabled)');
			const count = checkboxes.length;
			const summary = document.getElementById('selected-summary');
			const button = document.getElementById('assign-button');

			summary.style.display = count > 0 ? 'block' : 'none';
			const countSpan = document.getElementById('selected-count');
			if (countSpan) countSpan.textContent = count;
			button.disabled = count === 0;

			document.querySelectorAll('.group-chip').forEach(chip => {
				const checkbox = chip.querySelector('input[type="checkbox"]');
				chip.classList.toggle('selected', !!(checkbox && checkbox.checked && !checkbox.disabled));
			});
		}

		function setCurrentGroupsDisplay(person) {
			const groupsEl = document.getElementById('current-person-groups');
			if (!groupsEl) return;
			while (groupsEl.firstChild) groupsEl.removeChild(groupsEl.firstChild);
			groupsEl.appendChild(document.createTextNode('Current groups: '));
			if (person.group_ids.length === 0) {
				const em = document.createElement('em');
				em.textContent = 'None';
				groupsEl.appendChild(em);
			} else {
				person.group_labels.forEach(function(label) {
					const span = document.createElement('span');
					span.textContent = label;
					groupsEl.appendChild(span);
				});
			}
		}

		function updateQueueItem(index) {
			const item = document.querySelector('.queue-item[data-index="' + index + '"]');
			if (!item) return;
			const isCurrent = index === currentIndex;
			const isAssigned = assignedInSession.has(index);
			item.className = 'queue-item ' + (isCurrent ? 'queue-item--current' : isAssigned ? 'queue-item--assigned' : index < currentIndex ? 'queue-item--past' : 'queue-item--future');
			const status = item.querySelector('.queue-status');
			if (status) status.textContent = isCurrent ? '→' : isAssigned ? '✓' : index < currentIndex ? '–' : '○';
		}

		function navigateTo(index, pushState) {
			if (index < 0 || index >= crmPeople.length) return;
			var prevIndex = currentIndex;
			currentIndex = index;
			var person = crmPeople[index];

			if (pushState !== false) history.pushState({ index: index }, '', buildNavUrl(index));

			var nameLink = document.getElementById('current-person-name-link');
			if (nameLink) { nameLink.textContent = person.name; nameLink.href = person.url; }

			var usernameEl = document.getElementById('current-person-username');
			if (usernameEl) usernameEl.textContent = '@' + person.username;

			var locationEl = document.getElementById('current-person-location');
			if (locationEl) {
				locationEl.style.display = person.location ? '' : 'none';
				locationEl.textContent = person.location ? '📍 ' + person.location : '';
			}

			setCurrentGroupsDisplay(person);

			var progressEl = document.getElementById('progress-indicator');
			if (progressEl) progressEl.textContent = 'Person ' + (index + 1) + ' of ' + crmPeople.length;

			var prevBtn = document.getElementById('nav-prev');
			if (prevBtn) {
				if (index > 0) { prevBtn.href = buildNavUrl(index - 1); prevBtn.classList.remove('disabled'); }
				else { prevBtn.removeAttribute('href'); prevBtn.classList.add('disabled'); }
			}
			var nextBtn = document.getElementById('nav-next');
			if (nextBtn) {
				if (index < crmPeople.length - 1) { nextBtn.href = buildNavUrl(index + 1); nextBtn.classList.remove('disabled'); }
				else { nextBtn.removeAttribute('href'); nextBtn.classList.add('disabled'); }
			}
			var skipLink = document.getElementById('assign-skip-link');
			if (skipLink) skipLink.style.display = index < crmPeople.length - 1 ? '' : 'none';

			var formAction = buildNavUrl(index);
			var assignForm = document.getElementById('assign-form');
			if (assignForm) {
				assignForm.action = formAction;
				var personIdInput = assignForm.querySelector('input[name="person_id"]');
				if (personIdInput) personIdInput.value = person.id;
			}
			var createForm = document.querySelector('.create-group-form');
			if (createForm) {
				createForm.action = formAction;
				var createPersonId = createForm.querySelector('input[name="person_id"]');
				if (createPersonId) createPersonId.value = person.id;
			}

			var memberGroupIds = new Set(person.group_ids);
			document.querySelectorAll('.group-chip').forEach(function(chip) {
				var groupId = parseInt(chip.dataset.groupId);
				var checkbox = chip.querySelector('input[type="checkbox"]');
				chip.classList.remove('selected', 'already-member');
				chip.title = '';
				chip.style.display = '';
				if (checkbox) { checkbox.checked = false; checkbox.disabled = false; }
				if (memberGroupIds.has(groupId)) {
					chip.classList.add('already-member');
					chip.title = 'Already a member';
					if (checkbox) checkbox.disabled = true;
				}
			});

			var searchInput = document.getElementById('groups-search');
			if (searchInput) searchInput.value = '';
			var suggestion = document.getElementById('create-suggestion');
			if (suggestion) suggestion.style.display = 'none';

			updateQueueItem(prevIndex);
			updateQueueItem(index);
			var currentItem = document.querySelector('.queue-item[data-index="' + index + '"]');
			if (currentItem) currentItem.scrollIntoView({ block: 'nearest' });

			updateSelectedSummary();
		}

		async function submitAssignment(e) {
			e.preventDefault();
			var form = document.getElementById('assign-form');
			var button = document.getElementById('assign-button');

			var selectedGroupIds = Array.from(
				form.querySelectorAll('input[name="group_ids[]"]:checked:not(:disabled)')
			).map(function(cb) { return parseInt(cb.value); });

			if (selectedGroupIds.length === 0) return;

			button.disabled = true;
			button.textContent = 'Saving…';

			var formData = new FormData(form);
			formData.set('format', 'json');

			try {
				var response = await fetch(form.action, { method: 'POST', body: formData });
				var data = await response.json();

				if (data.success) {
					var person = crmPeople[currentIndex];
					selectedGroupIds.forEach(function(gid) {
						if (!person.group_ids.includes(gid)) {
							person.group_ids.push(gid);
							var chip = document.querySelector('.group-chip[data-group-id="' + gid + '"]');
							if (chip) person.group_labels.push(chip.textContent.trim());
						}
					});
					assignedInSession.add(currentIndex);
					if (currentIndex < crmPeople.length - 1) {
						navigateTo(currentIndex + 1);
					} else {
						window.location.reload();
					}
				} else {
					button.disabled = false;
					button.textContent = 'Assign & Next →';
				}
			} catch (err) {
				button.disabled = false;
				button.textContent = 'Assign & Next →';
			}
		}

		document.querySelectorAll('.group-chip').forEach(function(chip) {
			chip.addEventListener('click', function(e) {
				if (this.classList.contains('already-member')) { e.preventDefault(); return; }
				var checkbox = this.querySelector('input[type="checkbox"]');
				if (checkbox && !checkbox.disabled) { checkbox.checked = !checkbox.checked; updateSelectedSummary(); }
			});
		});

		document.getElementById('create-suggestion') && document.getElementById('create-suggestion').addEventListener('click', function(e) {
			if (!e.target.closest('select')) createGroupFromSearch();
		});

		document.getElementById('people-queue') && document.getElementById('people-queue').addEventListener('click', function(e) {
			var jumpLink = e.target.closest('[data-jump-index]');
			if (jumpLink) { e.preventDefault(); navigateTo(parseInt(jumpLink.dataset.jumpIndex)); }
		});

		document.getElementById('nav-prev') && document.getElementById('nav-prev').addEventListener('click', function(e) {
			if (!this.classList.contains('disabled')) { e.preventDefault(); navigateTo(currentIndex - 1); }
		});
		document.getElementById('nav-next') && document.getElementById('nav-next').addEventListener('click', function(e) {
			if (!this.classList.contains('disabled')) { e.preventDefault(); navigateTo(currentIndex + 1); }
		});
		document.getElementById('assign-skip-link') && document.getElementById('assign-skip-link').addEventListener('click', function(e) {
			e.preventDefault(); navigateTo(currentIndex + 1);
		});

		document.getElementById('assign-form') && document.getElementById('assign-form').addEventListener('submit', submitAssignment);

		document.querySelector('.create-group-section') && document.querySelector('.create-group-section').addEventListener('toggle', function() {
			if (this.open) { var inp = this.querySelector('input[name="new_group_name"]'); if (inp) inp.focus(); }
		});

		document.getElementById('groups-search') && document.getElementById('groups-search').addEventListener('keydown', function(e) {
			if (e.key === 'Enter') {
				e.preventDefault();
				var suggestion = document.getElementById('create-suggestion');
				if (suggestion && suggestion.style.display === 'flex') {
					createGroupFromSearch();
				}
			}
		});

		document.addEventListener('keydown', function(e) {
			if (e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT' || e.target.tagName === 'TEXTAREA') return;
			if (e.key === 'ArrowLeft') navigateTo(currentIndex - 1);
			else if (e.key === 'ArrowRight') navigateTo(currentIndex + 1);
			else if (e.key === 'Enter') {
				var btn = document.getElementById('assign-button');
				if (btn && !btn.disabled) document.getElementById('assign-form').dispatchEvent(new Event('submit', { cancelable: true }));
			}
		});

		window.addEventListener('popstate', function(e) {
			if (e.state && typeof e.state.index === 'number') navigateTo(e.state.index, false);
		});
	</script>
</body>
</html>
