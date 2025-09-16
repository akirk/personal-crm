<?php
/**
 * Orbit Team Management Tool
 * 
 * A comprehensive team management dashboard for the Orbit team.
 * Provides overview of team members, 1:1 documents, and team activities.
 */
namespace PersonalCRM;

require_once __DIR__ . '/includes/common.php';
require_once __DIR__ . '/includes/person.php';

$common = Common::get_instance();
$current_team = $common->get_current_team_from_params();

if ( $current_team ) {
	if ( $current_team === $common->get_default_team() && ! isset( $_GET['person'] ) ) {
		// Redirect to root if default team is selected, preserve privacy parameter
		$redirect_params = array();
		if ( isset( $_GET['privacy'] ) && $_GET['privacy'] === '1' ) {
			$redirect_params['privacy'] = '1';
		}
		$redirect_url = './' . ( ! empty( $redirect_params ) ? '?' . http_build_query( $redirect_params ) : '' );
		header( 'Location: ' . $redirect_url );
		exit;
	}
} else {
	$current_team = $common->use_default_team();
	$available_teams = $common->get_available_teams();
	if ( count( $available_teams ) > 1 && ! $current_team ) {
		header( 'Location: ' . $common->build_url( 'select.php' ) );
		exit;
	}
}

// Load team configuration with Person objects
$team_data = $common->load_team_config_with_objects( $current_team );

// Separate deceased people from their original sections
$deceased_people = array();
foreach ( array( 'team_members', 'leadership', 'consultants', 'alumni' ) as $section ) {
	if ( isset( $team_data[$section] ) ) {
		foreach ( $team_data[$section] as $username => $person ) {
			if ( ! empty( $person->deceased ) ) {
				$deceased_people[$username] = $person;
				unset( $team_data[$section][$username] );
			}
		}
	}
}
$team_data['deceased'] = $deceased_people;


// Redirect person pages to person.php
if ( isset( $_GET['person'] ) && ! empty( $_GET['person'] ) ) {
	// Preserve all query parameters
	$redirect_url = 'person.php?' . http_build_query( $_GET );
	header( 'Location: ' . $redirect_url );
	exit;
}

// Only overview action is handled by index.php now
$action = 'overview';

// Get available teams for switcher
$available_teams = $common->get_available_teams();
do_action( 'personal_crm_team_dashboard_init', $team_data, $current_team );

?>
<!DOCTYPE html>
<html <?php echo function_exists( '\wp_app_language_attributes' ) ? \wp_app_language_attributes() : 'lang="en"'; ?>>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="color-scheme" content="light dark">
	<title><?php echo function_exists( '\wp_app_title' ) ? \wp_app_title( $common->get_team_display_title( $current_team, 'Management' ) ) : htmlspecialchars( $common->get_team_display_title( $current_team, 'Management' ) ); ?></title>
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
	<?php $common->render_cmd_k_panel(); ?>

	<div class="container">
		<?php if ( $action === 'overview' ) : ?>
			<div class="header">
				<div>
					<h1><a href="<?php echo $common->build_url( 'index.php' ); ?>" style="color: inherit; text-decoration: none;"><?php echo htmlspecialchars( $common->get_team_display_title( $current_team, 'Overview' ) ); ?></a></h1>
				</div>
				<?php if ( ! empty( $team_data['team_links'] ) ) : ?>
					<div class="person-links" style="flex-grow: 1;">
						<?php foreach ( $team_data['team_links'] as $link_text => $link_url ) : ?>
							<a href="<?php echo htmlspecialchars( $link_url ); ?>" target="_blank">
								<?php echo $common->get_link_icon( $link_text, $link_url, 12 ); ?>
								<?php echo htmlspecialchars( $link_text ); ?>
							</a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
				<div class="navigation" style="display: flex; align-items: center; gap: 10px;">
					<select id="team-selector" onchange="switchTeam()">
						<?php
						// Separate teams by type and default status
						$default_teams = array();
						$teams = array();
						$groups = array();
						
						foreach ( $available_teams as $team_slug ) {
							$team_name = $common->get_team_name_from_file( $team_slug );
							$team_type = $common->get_team_type_from_file( $team_slug );
							$is_default = $common->get_default_team() === $team_slug;
							
							$item = array(
								'slug' => $team_slug,
								'name' => $team_name,
								'type' => $team_type
							);
							
							if ( $is_default ) {
								$default_teams[] = $item;
							} elseif ( $team_type === 'group' ) {
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
								<option value="<?php echo htmlspecialchars( $item['slug'] ); ?>" data-type="<?php echo htmlspecialchars( $item['type'] ); ?>" <?php echo $item['slug'] === $current_team ? 'selected' : ''; ?>><?php echo htmlspecialchars( $item['name'] ); ?></option>
							<?php endforeach; ?>
						</optgroup>
						<?php endif; ?>
						
						<?php if ( ! empty( $teams ) ) : ?>
						<optgroup label="Teams">
							<?php foreach ( $teams as $item ) : ?>
								<option value="<?php echo htmlspecialchars( $item['slug'] ); ?>" data-type="<?php echo htmlspecialchars( $item['type'] ); ?>" <?php echo $item['slug'] === $current_team ? 'selected' : ''; ?>><?php echo htmlspecialchars( $item['name'] ); ?></option>
							<?php endforeach; ?>
						</optgroup>
						<?php endif; ?>
						
						<?php if ( ! empty( $groups ) ) : ?>
						<optgroup label="Groups">
							<?php foreach ( $groups as $item ) : ?>
								<option value="<?php echo htmlspecialchars( $item['slug'] ); ?>" data-type="<?php echo htmlspecialchars( $item['type'] ); ?>" <?php echo $item['slug'] === $current_team ? 'selected' : ''; ?>><?php echo htmlspecialchars( $item['name'] ); ?></option>
							<?php endforeach; ?>
						</optgroup>
						<?php endif; ?>
					</select>
				</div>
			</div>

			<div class="overview-layout">
				<div class="people-section">
					<div class="section">
						<h3><?php echo ucfirst( $group ); ?> Members (<?php echo count( $team_data['team_members'] ); ?>)</h3>
						<?php if ( ! empty( $team_data['team_members'] ) ) : ?>
							<ul class="people-list">
								<?php foreach ( $team_data['team_members'] as $username => $member ) : ?>
									<li>
										<div class="person-row-container">
											<a href="<?php echo $member->get_profile_url(); ?>" class="person-row">
												<div class="person-info">
													<div class="person-name"><?php echo htmlspecialchars( $member->get_display_name_with_nickname() ); ?></div>
													<div class="person-username">
														<?php if ( $common->is_social_group( $current_team ) ) : ?>
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
												<?php $common->render_person_links( $member->links ); ?>
												<?php
												do_action( 'personal_crm_person_links', $member, $username, $team_data, $privacy_mode );
												?>
											</div>
										</div>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php else : ?>
							<p class="empty-state-message">No <?php echo $group; ?> members yet. <a href="<?php echo $common->build_url( 'admin.php', array( 'tab' => 'members', 'add' => 'new' ) ); ?>" class="action-link">Add your first <?php echo $group; ?> member →</a></p>
						<?php endif; ?>
					</div>

					<?php if ( $group !== 'group' ) : ?>
					<div class="section">
						<h3>Leadership (<?php echo count( $team_data['leadership'] ); ?>)</h3>
						<?php if ( ! empty( $team_data['leadership'] ) ) : ?>
							<ul class="people-list">
								<?php foreach ( $team_data['leadership'] as $username => $leader ) : ?>
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
												<?php $common->render_person_links( $leader->links ); ?>
												<!-- Leaders don't need monthly HR feedback -->
											</div>
										</div>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php else : ?>
							<p class="empty-state-message">No leadership yet. <a href="<?php echo $common->build_url( 'admin.php', array( 'tab' => 'leadership', 'add' => 'new' ) ); ?>" class="action-link">Add your first leader →</a></p>
						<?php endif; ?>
					</div>

					<div class="section">
						<h3>Consultants (<?php echo count( $team_data['consultants'] ); ?>)</h3>
						<?php if ( ! empty( $team_data['consultants'] ) ) : ?>
							<ul class="people-list">
								<?php foreach ( $team_data['consultants'] as $username => $consultant ) : ?>
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
												<?php $common->render_person_links( $consultant->links ); ?>
											</div>
										</div>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php else : ?>
							<p class="empty-state-message">No consultants yet. <a href="<?php echo $common->build_url( 'admin.php', array( 'tab' => 'consultants', 'add' => 'new' ) ); ?>" class="action-link">Add your first consultant →</a></p>
						<?php endif; ?>
					</div>

					<div class="section">
						<h3>Alumni (<?php echo count( $team_data['alumni'] ); ?>)</h3>
						<?php if ( ! empty( $team_data['alumni'] ) ) : ?>
							<ul class="people-list">
								<?php foreach ( $team_data['alumni'] as $username => $alumnus ) : ?>
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
												<?php $common->render_person_links( $alumnus->links ); ?>
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

					<?php if ( ! empty( $team_data['deceased'] ) ) : ?>
					<div class="section">
						<h3>Deceased (<?php echo count( $team_data['deceased'] ); ?>)</h3>
						<ul class="people-list">
							<?php foreach ( $team_data['deceased'] as $username => $deceased_person ) : ?>
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
											<?php $common->render_person_links( $deceased_person->links ); ?>
										</div>
									</div>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
					<?php endif; ?>
				</div>

				<div class="events-sidebar">
					<a href="<?php echo $common->build_url( 'events.php', array( 'privacy' => $privacy_mode ? '1' : '0' ) ); ?>" class="sidebar-section-link">
						<h3 class="sidebar-section-heading">🗓️ Upcoming Events</h3>
					</a>
					<?php
					$common->render_upcoming_events_sidebar( $team_data );
					?>

					<?php
					do_action( 'personal_crm_dashboard_sidebar', $team_data, $current_team, $privacy_mode );
					?>
				</div>
			</div>


		<?php else : ?>
			<div class="header">
				<h1>Page Not Found</h1>
				<p><a href="?<?php echo $current_team !== 'team' ? 'team=' . urlencode( $current_team ) : ''; ?>">← Back to <?php echo ucfirst( $group ); ?> Overview</a></p>
			</div>
		<?php endif; ?>

		<footer class="privacy-footer">
			<?php if ( $privacy_mode ) : ?>
				<a href="?<?php echo http_build_query( array_merge( $_GET, array( 'privacy' => '0' ) ) ); ?>" class="footer-link">🔒 Privacy Mode ON</a>
			<?php else : ?>
				<a href="?<?php echo http_build_query( array_merge( $_GET, array( 'privacy' => '1' ) ) ); ?>" class="footer-link">🔓 Privacy Mode OFF</a>
			<?php endif; ?>
			<?php if ( ! ( isset( $team_data['not_managing_team'] ) && $team_data['not_managing_team'] ) ) : ?>
				<?php if ( isset( $_GET['hr_view'] ) && $_GET['hr_view'] === 'current' ) : ?>
					<a href="?<?php echo http_build_query( array_diff_key( $_GET, array( 'hr_view' => '' ) ) ); ?>" class="footer-link">📅 HR: Current Month</a>
				<?php else : ?>
					<a href="?<?php echo http_build_query( array_merge( $_GET, array( 'hr_view' => 'current' ) ) ); ?>" class="footer-link">📅 HR: Previous Month</a>
				<?php endif; ?>
			<?php endif; ?>
			<a href="<?php echo $common->build_url( 'admin.php', array( 'team' => $current_team ) ); ?>" class="footer-link">⚙️ Admin Panel</a>
		</footer>
	</div>

	<?php
	if ( ! function_exists( '\wp_app_enqueue_script' ) ) {
		echo '<script src="assets/cmd-k.js"></script>';
		echo '<script src="assets/script.js"></script>';
	}
	if ( function_exists( '\wp_app_body_close' ) ) \wp_app_body_close();
	?>
	<?php $common->init_cmd_k_js( $privacy_mode ); ?>
	<script>
		document.addEventListener('DOMContentLoaded', () => {
			<?php 
			$all_people = array_merge( $team_data['team_members'], $team_data['leadership'], $team_data['consultants'], $team_data['alumni'] );
			foreach ( $all_people as $username => $person_obj ) :
				if ( ! empty( $person_obj->timezone ) && empty( $person_obj->deceased ) ) :
			?>
			createSimpleTimeUpdater('<?php echo addslashes( $person_obj->timezone ); ?>', '<?php echo addslashes( $username ); ?>');
			<?php 
				endif;
			endforeach; 
			?>
		});
		
		function switchTeam() {
			const selector = document.getElementById('team-selector');
			const selectedTeam = selector.value;
			const selectedOption = selector.options[selector.selectedIndex];
			const selectedType = selectedOption.getAttribute('data-type');
			
			const currentUrl = new URL(window.location);
			
			// Remove both team and group parameters first
			currentUrl.searchParams.delete('team');
			currentUrl.searchParams.delete('group');
			
			// Add the appropriate parameter based on the type
			if (selectedType === 'group') {
				currentUrl.searchParams.set('group', selectedTeam);
			} else {
				currentUrl.searchParams.set('team', selectedTeam);
			}
			
			window.location = currentUrl.toString();
		}
	</script>
</body>
</html>