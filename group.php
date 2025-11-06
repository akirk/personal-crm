<?php
/**
 * Orbit Team Management Tool
 * 
 * A comprehensive team management dashboard for the Orbit team.
 * Provides overview of team members, 1:1 documents, and team activities.
 */
namespace PersonalCRM;

require_once __DIR__ . '/personal-crm.php';

extract( PersonalCrm::get_globals() );
if ( $current_group && $current_group === $crm->get_default_group() && ! isset( $_GET['person'] ) && isset( $_GET['group'] ) ) {
    header( 'Location: ./' );
    exit;
}

// Only overview action is handled by index.php now
$action = 'overview';
do_action( 'personal_crm_team_dashboard_init', $group_data, $current_group );

?>
<!DOCTYPE html>
<html <?php echo function_exists( '\wp_app_language_attributes' ) ? \wp_app_language_attributes() : 'lang="en"'; ?>>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="color-scheme" content="light dark">
	<title><?php echo function_exists( '\wp_app_title' ) ? \wp_app_title( $crm->get_group_display_title( $current_group, 'Management' ) ) : htmlspecialchars( $crm->get_group_display_title( $current_group, 'Management' ) ); ?></title>
	<?php
	if ( function_exists( '\wp_app_enqueue_style' ) ) {
		wp_app_enqueue_style( 'a8c-hr-style', plugin_dir_url( __FILE__ ) . 'assets/style.css' );
		wp_app_enqueue_style( 'a8c-hr-cmd-k', plugin_dir_url( __FILE__ ) . 'assets/cmd-k.css' );
	} else {
		echo '<link rel="stylesheet" href="assets/style.css">';
		echo '<link rel="stylesheet" href="assets/cmd-k.css">';
	}
	?>
	<?php if ( function_exists( '\wp_app_head' ) ) \wp_app_head(); ?>
</head>
<body class="wp-app-body">
	<?php if ( function_exists( '\wp_app_body_open' ) ) \wp_app_body_open(); ?>
	<?php $crm->render_cmd_k_panel(); ?>

	<div class="container">
		<?php if ( $action === 'overview' && $group_data ) : ?>
			<div class="header">
				<div>
					<h1><a href="<?php echo $crm->build_url( 'group.php' ); ?>" style="color: inherit; text-decoration: none;"><?php echo htmlspecialchars( $crm->get_group_display_title( $current_group, 'Overview' ) ); ?></a></h1>
				</div>
				<?php if ( ! empty( $group_data->links ) ) : ?>
					<div class="person-links" style="flex-grow: 1;">
						<?php foreach ( $group_data->links as $link_text => $link_url ) : ?>
							<a href="<?php echo htmlspecialchars( $link_url ); ?>" target="_blank">
								<?php echo $crm->get_link_icon( $link_text, $link_url, 12 ); ?>
								<?php echo htmlspecialchars( $link_text ); ?>
							</a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
				<?php
				// Allow other plugins to add header content
				do_action( 'personal_crm_header_content', $group_data, $current_group );
				?>
				<div class="navigation" style="display: flex; align-items: center; gap: 10px;">
					<select id="group-selector" onchange="switchGroup()">
						<?php
						// Separate teams by type and default status (only top-level groups)
						$default_teams = array();
						$teams = array();
						$groups = array();

						foreach ( $available_groups as $group_slug ) {
							$group_obj = $crm->storage->get_group( $group_slug );

							// Skip child groups
							if ( $group_obj && $group_obj->parent_id ) {
								continue;
							}

							$group_name = $crm->storage->get_group_name( $group_slug );
							$group_type = $crm->storage->get_group_type( $group_slug );
							$is_default = $crm->get_default_group() === $group_slug;

							$item = array(
								'slug' => $group_slug,
								'name' => $group_name,
								'type' => $group_type
							);

							if ( $is_default ) {
								$default_teams[] = $item;
							} elseif ( $group_type === 'group' ) {
								$groups[] = $item;
							} else {
								$teams[] = $item;
							}
						}

						// Sort each group by name
						usort( $default_teams, fn($a, $b) => strcasecmp( $a['name'], $b['name'] ) );
						usort( $teams, fn($a, $b) => strcasecmp( $a['name'], $b['name'] ) );
						usort( $groups, fn($a, $b) => strcasecmp( $a['name'], $b['name'] ) );
						?>

						<?php if ( ! empty( $default_teams ) ) : ?>
						<optgroup label="Default">
							<?php foreach ( $default_teams as $item ) : ?>
								<option value="<?php echo htmlspecialchars( $crm->build_url( 'group.php', array( 'group' => $item['slug'] ) ) ); ?>" data-type="<?php echo htmlspecialchars( $item['type'] ); ?>" <?php echo $item['slug'] === $current_group ? 'selected' : ''; ?>><?php echo htmlspecialchars( $item['name'] ); ?></option>
							<?php endforeach; ?>
						</optgroup>
						<?php endif; ?>

						<?php if ( ! empty( $teams ) ) : ?>
						<optgroup label="Groups">
							<?php foreach ( $teams as $item ) : ?>
								<option value="<?php echo htmlspecialchars( $crm->build_url( 'group.php', array( 'group' => $item['slug'] ) ) ); ?>" data-type="<?php echo htmlspecialchars( $item['type'] ); ?>" <?php echo $item['slug'] === $current_group ? 'selected' : ''; ?>><?php echo htmlspecialchars( $item['name'] ); ?></option>
							<?php endforeach; ?>
						</optgroup>
						<?php endif; ?>

						<?php if ( ! empty( $groups ) ) : ?>
						<optgroup label="Groups">
							<?php foreach ( $groups as $item ) : ?>
								<option value="<?php echo htmlspecialchars( $crm->build_url( 'group.php', array( 'group' => $item['slug'] ) ) ); ?>" data-type="<?php echo htmlspecialchars( $item['type'] ); ?>" <?php echo $item['slug'] === $current_group ? 'selected' : ''; ?>><?php echo htmlspecialchars( $item['name'] ); ?></option>
							<?php endforeach; ?>
						</optgroup>
						<?php endif; ?>
					</select>
				</div>
			</div>

			<div class="overview-layout">
				<div class="people-section">
					<div class="section">
						<?php $members = $group_data->get_members(); ?>
						<h3>Members (<?php echo count( $members ); ?>)</h3>
						<?php if ( ! empty( $members ) ) : ?>
							<ul class="people-list">
								<?php foreach ( $members as $username => $member ) : ?>
									<?php
									$member_group_join_date = null;
									if ( ! empty( $member->groups ) ) {
										foreach ( $member->groups as $group ) {
											if ( $group['id'] == $group_data->id ) {
												$member_group_join_date = $group['group_joined_date'] ?? null;
												break;
											}
										}
									}
									$tenure = $member_group_join_date ? $crm->format_tenure( $member_group_join_date ) : '';
									?>
									<li>
										<div class="person-row-container">
											<a href="<?php echo $member->get_profile_url( array( 'back' => $current_group ) ); ?>" class="person-row">
												<div class="person-info">
													<div class="person-name">
														<?php echo htmlspecialchars( $member->get_display_name_with_nickname() ); ?>
														<?php if ( $tenure ) : ?>
															<small style="font-weight: normal; opacity: 0.7; margin-left: 8px;">(<?php echo htmlspecialchars( $tenure ); ?>)</small>
														<?php endif; ?>
													</div>
													<div class="person-username">
														<?php if ( $crm->is_social_group( $current_group ) ) : ?>
															<?php if ( ! empty( $member->location ) ) : ?>
																📍 <?php echo htmlspecialchars( $member->location ); ?>
															<?php endif; ?>
														<?php else : ?>
															@<?php echo htmlspecialchars( $member->get_username() ); ?>
														<?php endif; ?>
														<?php if ( ! empty( $member->timezone ) || ! empty( $member->location ) ) : ?>
															<span id="time-<?php echo htmlspecialchars( $username ); ?>" class="timezone-display"></span>
														<?php endif; ?>
													</div>
												</div>
											</a>
											<div class="person-links">
												<?php $crm->render_person_links( $member->links ); ?>
												<?php
												do_action( 'personal_crm_person_links', $member, $username, $group_data );
												?>
											</div>
										</div>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php else : ?>
							<p class="empty-state-message">No members yet. <a href="<?php echo $crm->build_url( 'admin/index.php', array( 'group' => $group_data->slug, 'members' => true, 'add' => 'new' ) ); ?>" class="action-link">Add your first member →</a></p>
						<?php endif; ?>
					</div>

					<?php
					// Render child groups (leadership, consultants, alumni, etc.)
					if ( ! $crm->is_social_group( $current_group ) ) :
						$child_groups_objs = $group_data->get_child_groups();
						foreach ( $child_groups_objs as $child_group_obj ) :
							$child_slug = $child_group_obj->slug;
							$child_members = $child_group_obj->get_members();
					?>
					<div class="section">
						<h3><?php echo htmlspecialchars( $child_group_obj->display_icon . ' ' . $child_group_obj->group_name ); ?> (<?php echo count( $child_members ); ?>)</h3>
						<?php if ( ! empty( $child_members ) ) : ?>
							<ul class="people-list">
								<?php foreach ( $child_members as $username => $leader ) : ?>
									<?php
									$leader_group_join_date = null;
									if ( ! empty( $leader->groups ) ) {
										foreach ( $leader->groups as $group ) {
											if ( $group['id'] == $child_group_obj->id ) {
												$leader_group_join_date = $group['group_joined_date'] ?? null;
												break;
											}
										}
									}
									$leader_tenure = $leader_group_join_date ? $crm->format_tenure( $leader_group_join_date ) : '';
									?>
									<li>
										<div class="person-row-container">
											<a href="<?php echo $leader->get_profile_url( array( 'back' => $current_group ) ); ?>" class="person-row">
												<div class="person-info">
													<div class="person-name">
														<?php echo htmlspecialchars( $leader->get_display_name_with_nickname() ); ?>
														<?php if ( $leader_tenure ) : ?>
															<small style="font-weight: normal; opacity: 0.7; margin-left: 8px;">(<?php echo htmlspecialchars( $leader_tenure ); ?>)</small>
														<?php endif; ?>
														<?php if ( ! empty( $leader->role ) ) : ?>
															<span class="person-role">(<?php echo htmlspecialchars( $leader->role ); ?>)</span>
														<?php endif; ?>
													</div>
													<div class="person-username">
														@<?php echo htmlspecialchars( $leader->get_username() ); ?>
														<?php if ( ! empty( $leader->timezone ) || ! empty( $leader->location ) ) : ?>
															<span id="time-<?php echo htmlspecialchars( $username ); ?>" class="timezone-display"></span>
														<?php endif; ?>
													</div>
												</div>
											</a>
											<?php if ( stripos( $child_group_obj->group_name, 'alumni' ) !== false && ! empty( $leader->new_company ) ) : ?>
												<div class="alumni-company-display">
													<span class="alumni-company-text">
														Now at:
														<?php if ( ! empty( $leader->new_company_website ) ) : ?>
															<a href="<?php echo htmlspecialchars( $leader->new_company_website ); ?>" target="_blank" class="company-link"><?php echo htmlspecialchars( $leader->new_company ); ?></a>
														<?php else : ?>
															<?php echo htmlspecialchars( $leader->new_company ); ?>
														<?php endif; ?>
													</span>
												</div>
											<?php endif; ?>
											<div class="person-links">
												<?php $crm->render_person_links( $leader->links ); ?>
												<?php
												do_action( 'personal_crm_person_links', $leader, $username, $child_group_obj );
												?>
											</div>
										</div>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php else : ?>
							<p class="empty-state-message">No <?php echo strtolower( $child_group['group_name'] ); ?> yet.</p>
						<?php endif; ?>
					</div>
					<?php endforeach; endif; ?>

					<?php $deceased = $group_data->get_deceased(); ?>
					<?php if ( ! empty( $deceased ) ) : ?>
					<div class="section">
						<h3>Deceased (<?php echo count( $deceased ); ?>)</h3>
						<ul class="people-list">
							<?php foreach ( $deceased as $username => $deceased_person ) : ?>
								<li>
									<div class="person-row-container">
										<a href="<?php echo $deceased_person->get_profile_url( array( 'back' => $current_group ) ); ?>" class="person-row">
											<div class="person-info">
												<div class="person-name"><?php echo htmlspecialchars( $deceased_person->get_display_name_with_nickname() ); ?> <span style="color: #666; font-weight: normal;">†</span></div>
												<div class="person-username">
													@<?php echo htmlspecialchars( $deceased_person->get_username() ); ?>
												</div>
											</div>
										</a>
										<div class="person-links">
											<?php $crm->render_person_links( $deceased_person->links ); ?>
										</div>
									</div>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
					<?php endif; ?>
				</div>

				<div class="events-sidebar">
					<a href="<?php echo $crm->build_url( 'events.php' ); ?>" class="sidebar-section-link">
						<h3 class="sidebar-section-heading">🗓️ Upcoming Events</h3>
					</a>
					<?php
					$crm->render_upcoming_events_sidebar( $group_data );
					?>

					<?php
					do_action( 'personal_crm_dashboard_sidebar', $group_data, $current_group );
					?>
				</div>
			</div>


		<?php elseif ( ! $group_data ) : ?>
			<div class="header">
				<h1>No Groups Found</h1>
				<p>No groups have been created yet. <a href="<?php echo $crm->build_url( 'admin/index.php', array( 'create_team' => 'new' ) ); ?>">Create your first group →</a></p>
			</div>
		<?php else : ?>
			<div class="header">
				<h1>Page Not Found</h1>
				<p><a href="?<?php echo $current_group !== 'team' ? 'team=' . urlencode( $current_group ) : ''; ?>">← Back to Overview</a></p>
			</div>
		<?php endif; ?>

		<footer class="privacy-footer">
			<a href="#" id="privacy-toggle" onclick="togglePrivacyMode(); return false;">
				<span id="privacy-status">🔓 Privacy Mode OFF</span>
			</a>
			<?php
			// Allow other plugins to add footer links
			do_action( 'personal_crm_footer_links', $group_data, $current_group );
			?>
			<a href="<?php echo $crm->build_url( 'admin/index.php', array( 'group' => $current_group ) ); ?>" class="footer-link">⚙️ Admin Panel</a>
		</footer>
	</div>

	<?php
	if ( ! function_exists( '\wp_app_enqueue_script' ) ) {
		echo '<script src="assets/cmd-k.js"></script>';
		echo '<script src="assets/script.js"></script>';
	}
	if ( function_exists( '\wp_app_body_close' ) ) \wp_app_body_close();
	?>
	<?php $crm->init_cmd_k_js(); ?>
	<script>
		document.addEventListener('DOMContentLoaded', () => {
			<?php
			if ( $group_data ) :
				// Collect all people from direct members and child groups
				$all_people = $group_data->get_members();
				foreach ( $group_data->get_child_groups() as $child_group_obj ) {
					$all_people = array_merge( $all_people, $child_group_obj->get_members() );
				}

				foreach ( $all_people as $username => $person_obj ) :
					if ( ! empty( $person_obj->timezone ) && empty( $person_obj->deceased ) ) :
			?>
			createSimpleTimeUpdater('<?php echo addslashes( $person_obj->timezone ); ?>', '<?php echo addslashes( $username ); ?>');
			<?php
					endif;
				endforeach;
			endif;
			?>
		});
	</script>
</body>
</html>