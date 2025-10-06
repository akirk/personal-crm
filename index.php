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
    // Redirect to root if default team is selected, preserve privacy parameter
    $redirect_params = array();
    if ( isset( $_GET['privacy'] ) && $_GET['privacy'] === '1' ) {
        $redirect_params['privacy'] = '1';
    }
    $redirect_url = './' . ( ! empty( $redirect_params ) ? '?' . http_build_query( $redirect_params ) : '' );
    header( 'Location: ' . $redirect_url );
    exit;
}


// Redirect person pages to person.php
if ( isset( $_GET['person'] ) && ! empty( $_GET['person'] ) ) {
	// Preserve all query parameters
	$redirect_url = 'person.php?' . http_build_query( $_GET );
	header( 'Location: ' . $redirect_url );
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
		<?php if ( $action === 'overview' ) : ?>
			<div class="header">
				<div>
					<h1><a href="<?php echo $crm->build_url( 'index.php' ); ?>" style="color: inherit; text-decoration: none;"><?php echo htmlspecialchars( $crm->get_group_display_title( $current_group, 'Overview' ) ); ?></a></h1>
				</div>
				<?php if ( ! empty( $group_data['links'] ) ) : ?>
					<div class="person-links" style="flex-grow: 1;">
						<?php foreach ( $group_data['links'] as $link_text => $link_url ) : ?>
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
						// Separate teams by type and default status
						$default_teams = array();
						$teams = array();
						$groups = array();

						foreach ( $available_groups as $group_slug ) {
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
								<option value="<?php echo htmlspecialchars( $crm->build_url( $item['slug'] ) ); ?>" data-type="<?php echo htmlspecialchars( $item['type'] ); ?>" <?php echo $item['slug'] === $current_group ? 'selected' : ''; ?>><?php echo htmlspecialchars( $item['name'] ); ?></option>
							<?php endforeach; ?>
						</optgroup>
						<?php endif; ?>

						<?php if ( ! empty( $teams ) ) : ?>
						<optgroup label="Teams">
							<?php foreach ( $teams as $item ) : ?>
								<option value="<?php echo htmlspecialchars( $crm->build_url( $item['slug'] ) ); ?>" data-type="<?php echo htmlspecialchars( $item['type'] ); ?>" <?php echo $item['slug'] === $current_group ? 'selected' : ''; ?>><?php echo htmlspecialchars( $item['name'] ); ?></option>
							<?php endforeach; ?>
						</optgroup>
						<?php endif; ?>

						<?php if ( ! empty( $groups ) ) : ?>
						<optgroup label="Groups">
							<?php foreach ( $groups as $item ) : ?>
								<option value="<?php echo htmlspecialchars( $crm->build_url( $item['slug'] ) ); ?>" data-type="<?php echo htmlspecialchars( $item['type'] ); ?>" <?php echo $item['slug'] === $current_group ? 'selected' : ''; ?>><?php echo htmlspecialchars( $item['name'] ); ?></option>
							<?php endforeach; ?>
						</optgroup>
						<?php endif; ?>
					</select>
				</div>
			</div>

			<div class="overview-layout">
				<div class="people-section">
					<div class="section">
						<h3><?php echo ucfirst( $group ); ?> Members (<?php echo count( $group_data['team_members'] ); ?>)</h3>
						<?php if ( ! empty( $group_data['team_members'] ) ) : ?>
							<ul class="people-list">
								<?php foreach ( $group_data['team_members'] as $username => $member ) : ?>
									<li>
										<div class="person-row-container">
											<a href="<?php echo $member->get_profile_url(); ?>" class="person-row">
												<div class="person-info">
													<div class="person-name"><?php echo htmlspecialchars( $member->get_display_name_with_nickname() ); ?></div>
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
												do_action( 'personal_crm_person_links', $member, $username, $current_group, $group_data );
												?>
											</div>
										</div>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php else : ?>
							<p class="empty-state-message">No <?php echo $group; ?> members yet. <a href="<?php echo $crm->build_url( 'admin/index.php', array( 'tab' => 'members', 'add' => 'new' ) ); ?>" class="action-link">Add your first <?php echo $group; ?> member →</a></p>
						<?php endif; ?>
					</div>

					<?php if ( $group !== 'group' ) : ?>
					<div class="section">
						<h3>Leadership (<?php echo count( $group_data['leadership'] ); ?>)</h3>
						<?php if ( ! empty( $group_data['leadership'] ) ) : ?>
							<ul class="people-list">
								<?php foreach ( $group_data['leadership'] as $username => $leader ) : ?>
									<li>
										<div class="person-row-container">
											<a href="<?php echo $leader->get_profile_url(); ?>" class="person-row">
												<div class="person-info">
													<div class="person-name"><?php echo htmlspecialchars( $leader->get_display_name_with_nickname() ); ?> <span class="person-role">(<?php echo htmlspecialchars( $leader->role ); ?>)</span></div>
													<div class="person-username">
														@<?php echo htmlspecialchars( $leader->get_username() ); ?>
														<?php if ( ! empty( $leader->timezone ) || ! empty( $leader->location ) ) : ?>
															<span id="time-<?php echo htmlspecialchars( $username ); ?>" class="timezone-display"></span>
														<?php endif; ?>
													</div>
												</div>
											</a>
											<div class="person-links">
												<?php $crm->render_person_links( $leader->links ); ?>
												<!-- Leaders don't need monthly HR feedback -->
											</div>
										</div>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php else : ?>
							<p class="empty-state-message">No leadership yet. <a href="<?php echo $crm->build_url( 'admin/index.php', array( 'tab' => 'leadership', 'add' => 'new' ) ); ?>" class="action-link">Add your first leader →</a></p>
						<?php endif; ?>
					</div>

					<div class="section">
						<h3>Consultants (<?php echo count( $group_data['consultants'] ); ?>)</h3>
						<?php if ( ! empty( $group_data['consultants'] ) ) : ?>
							<ul class="people-list">
								<?php foreach ( $group_data['consultants'] as $username => $consultant ) : ?>
									<li>
										<div class="person-row-container">
											<a href="<?php echo $consultant->get_profile_url(); ?>" class="person-row">
												<div class="person-info">
													<div class="person-name"><?php echo htmlspecialchars( $consultant->get_display_name_with_nickname() ); ?> <span class="person-role">(<?php echo htmlspecialchars( $consultant->role ); ?>)</span></div>
													<div class="person-username">
														@<?php echo htmlspecialchars( $consultant->get_username() ); ?>
														<?php if ( ! empty( $consultant->timezone ) || ! empty( $consultant->location ) ) : ?>
															<span id="time-<?php echo htmlspecialchars( $username ); ?>" class="timezone-display"></span>
														<?php endif; ?>
													</div>
												</div>
											</a>
											<div class="person-links">
												<?php $crm->render_person_links( $consultant->links ); ?>
											</div>
										</div>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php else : ?>
							<p class="empty-state-message">No consultants yet. <a href="<?php echo $crm->build_url( 'admin/index.php', array( 'tab' => 'consultants', 'add' => 'new' ) ); ?>" class="action-link">Add your first consultant →</a></p>
						<?php endif; ?>
					</div>

					<div class="section">
						<h3>Alumni (<?php echo count( $group_data['alumni'] ); ?>)</h3>
						<?php if ( ! empty( $group_data['alumni'] ) ) : ?>
							<ul class="people-list">
								<?php foreach ( $group_data['alumni'] as $username => $alumnus ) : ?>
									<li>
										<div class="person-row-container">
											<a href="<?php echo $alumnus->get_profile_url(); ?>" class="person-row">
												<div class="person-info">
													<div class="person-name"><?php echo htmlspecialchars( $alumnus->get_display_name_with_nickname() ); ?> <span style="color: #999; font-weight: normal;">(Alumni)</span></div>
													<div class="person-username">
														@<?php echo htmlspecialchars( $alumnus->get_username() ); ?>
														<?php if ( ! empty( $alumnus->timezone ) || ! empty( $alumnus->location ) ) : ?>
															<span id="time-<?php echo htmlspecialchars( $username ); ?>" class="timezone-display"></span>
														<?php endif; ?>
													</div>
												</div>
											</a>
											<?php if ( ! empty( $alumnus->new_company ) ) : ?>
												<div class="alumni-company-display">
													<span class="alumni-company-text">
														Now at:
														<?php if ( ! empty( $alumnus->new_company_website ) ) : ?>
															<a href="<?php echo htmlspecialchars( $alumnus->new_company_website ); ?>" target="_blank" class="company-link"><?php echo htmlspecialchars( $alumnus->new_company ); ?></a>
														<?php else : ?>
															<?php echo htmlspecialchars( $alumnus->new_company ); ?>
														<?php endif; ?>
													</span>
												</div>
											<?php endif; ?>
											<div class="person-links">
												<?php $crm->render_person_links( $alumnus->links ); ?>
											</div>
										</div>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php else : ?>
							<p class="empty-state-message">No alumni yet. Alumni are created by moving existing <?php echo $group; ?> members or leaders.</p>
						<?php endif; ?>
					</div>
					<?php endif; ?>

					<?php if ( ! empty( $group_data['deceased'] ) ) : ?>
					<div class="section">
						<h3>Deceased (<?php echo count( $group_data['deceased'] ); ?>)</h3>
						<ul class="people-list">
							<?php foreach ( $group_data['deceased'] as $username => $deceased_person ) : ?>
								<li>
									<div class="person-row-container">
										<a href="<?php echo $deceased_person->get_profile_url(); ?>" class="person-row">
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
					<a href="<?php echo $crm->build_url( 'events.php', array( 'privacy' => $privacy_mode ? '1' : '0' ) ); ?>" class="sidebar-section-link">
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


		<?php else : ?>
			<div class="header">
				<h1>Page Not Found</h1>
				<p><a href="?<?php echo $current_group !== 'team' ? 'team=' . urlencode( $current_group ) : ''; ?>">← Back to <?php echo ucfirst( $group ); ?> Overview</a></p>
			</div>
		<?php endif; ?>

		<footer class="privacy-footer">
			<?php if ( $privacy_mode ) : ?>
				<a href="?<?php echo http_build_query( array_merge( $_GET, array( 'privacy' => '0' ) ) ); ?>" class="footer-link">🔒 Privacy Mode ON</a>
			<?php else : ?>
				<a href="?<?php echo http_build_query( array_merge( $_GET, array( 'privacy' => '1' ) ) ); ?>" class="footer-link">🔓 Privacy Mode OFF</a>
			<?php endif; ?>
			<?php
			// Allow other plugins to add footer links
			do_action( 'personal_crm_footer_links', $group_data, $current_group );
			?>
			<a href="<?php echo $crm->build_url( 'admin/index.php', array( 'team' => $current_group ) ); ?>" class="footer-link">⚙️ Admin Panel</a>
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
			$all_people = array_merge( $group_data['team_members'], $group_data['leadership'], $group_data['consultants'], $group_data['alumni'] );
			foreach ( $all_people as $username => $person_obj ) :
				if ( ! empty( $person_obj->timezone ) && empty( $person_obj->deceased ) ) :
			?>
			createSimpleTimeUpdater('<?php echo addslashes( $person_obj->timezone ); ?>', '<?php echo addslashes( $username ); ?>');
			<?php 
				endif;
			endforeach; 
			?>
		});
	</script>
</body>
</html>