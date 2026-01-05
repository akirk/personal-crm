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
	$action = sanitize_text_field( $_POST['action'] );

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
			$message = "Assigned to $assigned_count group(s).";
			$message_type = 'success';
		}
	} elseif ( $action === 'create_group' ) {
		$group_name = sanitize_text_field( $_POST['new_group_name'] ?? '' );
		$group_type = sanitize_text_field( $_POST['new_group_type'] ?? 'group' );

		if ( ! empty( $group_name ) ) {
			$group_slug = sanitize_title( $group_name );
			$group_slug = str_replace( '-', '_', $group_slug );

			$result = $crm->storage->create_group( $group_name, $group_slug, $group_type );
			if ( $result ) {
				$message = "Created group: $group_name";
				$message_type = 'success';
			} else {
				$message = "Failed to create group (may already exist)";
				$message_type = 'error';
			}
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
	</style>
</head>
<body class="wp-app-body">
	<?php if ( function_exists( '\wp_app_body_open' ) ) \wp_app_body_open(); ?>

	<div class="container">
		<div class="header">
			<h1>Assign Groups</h1>
			<div class="navigation">
				<a href="<?php echo home_url( '/crm/' ); ?>">← Back to CRM</a>
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
			<div class="empty-state">
				<h2>No People to Assign</h2>
				<p>Everyone has been assigned to at least one group!</p>
				<p>Use the query bar above to load people from a specific group, or pass usernames via URL: <code>?person[]=user1&person[]=user2</code></p>
			</div>
		<?php else : ?>
			<div class="assign-groups-layout">
				<!-- Left Panel: People Queue -->
				<div class="people-panel">
					<div class="progress-indicator">
						Person <?php echo $current_index + 1; ?> of <?php echo count( $people_list ); ?>
					</div>

					<?php if ( $current_person ) : ?>
						<div class="person-card current">
							<div class="person-name"><?php echo esc_html( $current_person->get_display_name_with_nickname() ); ?></div>
							<div class="person-username">@<?php echo esc_html( $current_person->username ); ?></div>
							<?php if ( ! empty( $current_person->location ) ) : ?>
								<div class="person-username">📍 <?php echo esc_html( $current_person->location ); ?></div>
							<?php endif; ?>
							<div class="current-groups">
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
							<?php if ( $current_index > 0 ) : ?>
								<a href="<?php echo esc_url( build_nav_url( $crm, $base_params, $current_index - 1 ) ); ?>">← Previous</a>
							<?php else : ?>
								<a class="disabled">← Previous</a>
							<?php endif; ?>

							<?php if ( $current_index < count( $people_list ) - 1 ) : ?>
								<a href="<?php echo esc_url( build_nav_url( $crm, $base_params, $current_index + 1 ) ); ?>">Skip →</a>
							<?php else : ?>
								<a class="disabled">Skip →</a>
							<?php endif; ?>
						</div>
					<?php endif; ?>

					<!-- Queue preview -->
					<h4 style="margin-top: 24px; margin-bottom: 12px;">Queue</h4>
					<div style="max-height: 300px; overflow-y: auto;">
						<?php foreach ( $people_list as $idx => $person ) : ?>
							<div style="padding: 8px 12px; border-radius: 4px; margin-bottom: 4px; background: <?php echo $idx === $current_index ? 'light-dark(#e8f4fc, #1a3a4a)' : 'transparent'; ?>; display: flex; justify-content: space-between; align-items: center;">
								<span>
									<?php if ( $idx < $current_index ) : ?>
										<span style="color: #28a745;">✓</span>
									<?php elseif ( $idx === $current_index ) : ?>
										<span style="color: #0073aa;">→</span>
									<?php else : ?>
										<span style="opacity: 0.3;">○</span>
									<?php endif; ?>
									<?php echo esc_html( $person->name ); ?>
								</span>
								<?php if ( $idx !== $current_index ) : ?>
									<a href="<?php echo esc_url( build_nav_url( $crm, $base_params, $idx ) ); ?>" style="font-size: 12px;">Jump</a>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>
				</div>

				<!-- Right Panel: Groups Selection -->
				<div class="groups-panel">
					<?php if ( $current_person ) : ?>
						<form method="post" action="<?php echo esc_url( home_url( '/crm/assign-groups' ) . '?' . http_build_query( array_merge( $base_params, array( 'index' => $current_index ) ) ) ); ?>" id="assign-form">
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
									       <?php echo $is_member ? 'title="Already a member"' : ''; ?>>
										<input type="checkbox" name="group_ids[]" value="<?php echo esc_attr( $group['id'] ); ?>"
										       style="display: none;" <?php echo $is_member ? 'disabled' : ''; ?>
										       onchange="updateSelectedSummary()">
										<?php echo esc_html( $group['display_icon'] . ' ' . $group['hierarchical_name'] ); ?>
									</label>
								<?php endforeach; ?>
							</div>

							<div class="selected-groups-summary" id="selected-summary" style="display: none;">
								Selected: <span id="selected-count">0</span> group(s)
							</div>

							<button type="submit" class="assign-button" id="assign-button" disabled>
								Assign & Next →
							</button>
						</form>

						<!-- Create New Group -->
						<details class="create-group-section">
							<summary>+ Create New Group</summary>
							<form method="post" action="<?php echo esc_url( home_url( '/crm/assign-groups' ) . '?' . http_build_query( array_merge( $base_params, array( 'index' => $current_index ) ) ) ); ?>" class="create-group-form">
								<input type="hidden" name="action" value="create_group">
								<input type="text" name="new_group_name" placeholder="Group Name" required>
								<select name="new_group_type">
									<option value="group">Personal (group)</option>
									<option value="team">Work (team)</option>
								</select>
								<button type="submit" style="padding: 10px; background: light-dark(#e8e8e8, #333); border: none; border-radius: 6px; cursor: pointer;">
									Create Group
								</button>
							</form>
						</details>
					<?php else : ?>
						<p>Select a person to assign groups.</p>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>
	</div>

	<?php if ( function_exists( '\wp_app_body_close' ) ) \wp_app_body_close(); ?>

	<script>
		function filterGroups() {
			const search = document.getElementById('groups-search').value.toLowerCase();
			const chips = document.querySelectorAll('.group-chip');
			chips.forEach(chip => {
				const name = chip.dataset.name;
				chip.style.display = name.includes(search) ? '' : 'none';
			});
		}

		function updateSelectedSummary() {
			const checkboxes = document.querySelectorAll('#group-chips input[type="checkbox"]:checked:not(:disabled)');
			const count = checkboxes.length;
			const summary = document.getElementById('selected-summary');
			const countSpan = document.getElementById('selected-count');
			const button = document.getElementById('assign-button');

			if (count > 0) {
				summary.style.display = 'block';
				countSpan.textContent = count;
				button.disabled = false;
			} else {
				summary.style.display = 'none';
				button.disabled = true;
			}

			// Update chip visual state
			document.querySelectorAll('.group-chip').forEach(chip => {
				const checkbox = chip.querySelector('input[type="checkbox"]');
				if (checkbox && checkbox.checked && !checkbox.disabled) {
					chip.classList.add('selected');
				} else {
					chip.classList.remove('selected');
				}
			});
		}

		// Handle chip clicks
		document.querySelectorAll('.group-chip').forEach(chip => {
			chip.addEventListener('click', function(e) {
				if (this.classList.contains('already-member')) {
					e.preventDefault();
					return;
				}
				const checkbox = this.querySelector('input[type="checkbox"]');
				if (checkbox && !checkbox.disabled) {
					checkbox.checked = !checkbox.checked;
					updateSelectedSummary();
				}
			});
		});

		// Auto-advance after successful assignment
		<?php if ( $message_type === 'success' && $current_index < count( $people_list ) - 1 ) : ?>
		setTimeout(function() {
			window.location.href = '<?php echo esc_js( build_nav_url( $crm, $base_params, $current_index + 1 ) ); ?>';
		}, 500);
		<?php endif; ?>

		// Keyboard shortcuts
		document.addEventListener('keydown', function(e) {
			// Don't trigger if user is typing in an input
			if (e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT' || e.target.tagName === 'TEXTAREA') {
				return;
			}

			if (e.key === 'ArrowLeft' && <?php echo $current_index > 0 ? 'true' : 'false'; ?>) {
				window.location.href = '<?php echo esc_js( build_nav_url( $crm, $base_params, max( 0, $current_index - 1 ) ) ); ?>';
			} else if (e.key === 'ArrowRight' && <?php echo $current_index < count( $people_list ) - 1 ? 'true' : 'false'; ?>) {
				window.location.href = '<?php echo esc_js( build_nav_url( $crm, $base_params, $current_index + 1 ) ); ?>';
			} else if (e.key === 'Enter' && !document.getElementById('assign-button').disabled) {
				document.getElementById('assign-form').submit();
			}
		});
	</script>
</body>
</html>
