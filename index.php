<?php
/**
 * Orbit Team Management Tool
 * 
 * A comprehensive team management dashboard for the Orbit team.
 * Provides overview of team members, 1:1 documents, and team activities.
 */

// Include common functions and Person class
require_once __DIR__ . '/includes/common.php';
require_once __DIR__ . '/includes/person.php';

// Team redirection logic - handle cases where no team parameter is provided
if ( isset( $_GET['team'] ) ) {
	$current_team = $_GET['team'];
	if ( $current_team === get_default_team() && ! isset( $_GET['person'] ) ) {
		// Redirect to root if default team is selected
		header( 'Location: ./' );
		exit;
	}
} else {
	$current_team = get_default_team();
	$available_teams = get_available_teams();
	if ( count( $available_teams ) > 1 && ! $current_team ) {
		header( 'Location: team-selection.php' );
		exit;
	}
}




// Get privacy mode early for Person object creation
$privacy_mode = isset( $_GET['privacy'] ) && $_GET['privacy'] === '1';

// Load team configuration with Person objects
$team_data = load_team_config_with_objects( $current_team, $privacy_mode );


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
$available_teams = get_available_teams();

// Collect HR feedback statistics for overview (all team members need HR feedback by default)
$team_members_needing_hr = $team_data['team_members'];

$hr_monthly_stats = array();
if ( ! empty( $team_members_needing_hr ) ) {
	$all_months = array();
	
	// Collect all feedback months from all team members
	foreach ( $team_members_needing_hr as $username => $person ) {
		$feedback_history = get_person_feedback_history( $username );
		if ( ! empty( $feedback_history ) ) {
			foreach ( $feedback_history as $month => $feedback ) {
				if ( $month !== 'hr_monthly_link' && is_array( $feedback ) ) {
					$all_months[ $month ] = true;
				}
			}
		}
	}
	
	// Sort months newest first and take the 6 most recent
	$sorted_months = array_keys( $all_months );
	rsort( $sorted_months );
	$recent_months = array_slice( $sorted_months, 0, 6 );
	
	// Calculate completion stats for each recent month
	foreach ( $recent_months as $month ) {
		$completed = 0;
		$not_necessary = 0;
		
		foreach ( $team_members_needing_hr as $username => $person ) {
			$feedback_history = get_person_feedback_history( $username );
			
			// Check if person has "not necessary" status for this month
			if ( isset( $feedback_history[ $month . '_not_necessary' ] ) ) {
				$not_necessary++;
			} elseif ( isset( $feedback_history[ $month ] ) && is_array( $feedback_history[ $month ] ) ) {
				$completed++;
			}
		}
		
		// Calculate total people who actually needed to submit feedback
		$people_needing_feedback = count( $team_members_needing_hr ) - $not_necessary;
		
		$hr_monthly_stats[ $month ] = array(
			'completed' => $completed,
			'total' => $people_needing_feedback,
			'not_necessary' => $not_necessary
		);
	}
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo htmlspecialchars( $team_data['team_name'] ) . ' Team Management'; ?></title>
	<link rel="stylesheet" href="assets/style.css">
	<link rel="stylesheet" href="assets/cmd-k.css">
</head>
<body>
	<!-- Command-K Panel -->
	<div id="cmd-k-overlay" class="cmd-k-overlay">
		<div class="cmd-k-panel">
			<div class="cmd-k-search-container">
				<input type="text" id="cmd-k-search" class="cmd-k-search" placeholder="Search teams and people..." autocomplete="off" spellcheck="false">
			</div>
			<div id="cmd-k-results" class="cmd-k-results">
				<!-- Results will be populated here -->
			</div>
			<div class="cmd-k-instructions">
				<span class="cmd-k-kbd">↑↓</span> to navigate • <span class="cmd-k-kbd">Enter</span> to open • <span class="cmd-k-kbd">→</span> to select link • <span class="cmd-k-kbd">Esc</span> to close
			</div>
		</div>
	</div>

	<div class="container">
		<?php if ( $action === 'overview' ) : ?>
			<div class="header">
				<div style="flex-grow: 1;">
					<h1><a href="<?php echo build_team_url( 'index.php' ); ?>" style="color: inherit; text-decoration: none;"><?php echo htmlspecialchars( $team_data['team_name'] ); ?> Team Overview</a></h1>
				</div>
				<div class="navigation" style="display: flex; align-items: center; gap: 10px;">
					<!-- Team Switcher -->
					<select id="team-selector" onchange="switchTeam()" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; background: white;">
						<?php
						foreach ( $available_teams as $team_slug ) {
							$team_display_name = get_team_name_from_file( $team_slug );
							$selected = $team_slug === $current_team ? 'selected' : '';
							echo '<option value="' . htmlspecialchars( $team_slug ) . '" ' . $selected . '>' . htmlspecialchars( $team_display_name ) . '</option>';
						}
						?>
					</select>
				</div>
			</div>

			<div class="overview-layout">
				<div class="people-section">
					<div class="section">
						<h3>Team Members (<?php echo count( $team_data['team_members'] ); ?>)</h3>
						<?php if ( ! empty( $team_data['team_members'] ) ) : ?>
							<ul class="people-list">
								<?php foreach ( $team_data['team_members'] as $username => $member ) : ?>
									<li>
										<div class="person-row-container">
											<a href="<?php echo $member->get_profile_url(); ?>" class="person-row">
												<div class="person-info">
													<div class="person-name"><?php echo htmlspecialchars( $member->get_display_name_with_nickname() ); ?></div>
													<div class="person-username">
														@<?php echo htmlspecialchars( $member->get_username() ); ?>
														<?php if ( ! empty( $member->timezone ) || ! empty( $member->location ) ) : ?>
															<span id="time-<?php echo htmlspecialchars( $username ); ?>" style="margin-left: 8px; color: #666; font-size: 12px;"></span>
														<?php endif; ?>
													</div>
												</div>
											</a>
											<div class="person-links">
												<?php render_person_links( $member->links ); ?>
												<?php
												// Check if person is marked as "not necessary" for current month
												$current_month = get_hr_feedback_month();
												$person_feedback = get_person_feedback_history( $username );
												$not_necessary_key = $current_month . '_not_necessary';
												$is_not_necessary = isset( $person_feedback[ $not_necessary_key ] );
												
												if ( $is_not_necessary ) {
													$not_necessary_reason = $person_feedback[ $not_necessary_key ];
													$reason_display = str_replace( '_', ' ', $not_necessary_reason );
													$reason_display = ucwords( $reason_display );
												} else {
													// All team members need HR feedback by default
													$feedback_status = $member->get_monthly_feedback_status();
												}
												?>
												<?php
												$hr_params = array(
													'person' => $username,
													'month' => $current_month,
													'privacy' => $privacy_mode ? '1' : '0'
												);
												?>
												<?php if ( $is_not_necessary ) : ?>
													<span class="feedback-status-link not-necessary" title="<?php echo htmlspecialchars( $reason_display ); ?>">➖ Not needed</span>
												<?php elseif ( $feedback_status['status'] === 'submitted' ) : ?>
													<a href="<?php echo build_team_url( 'hr-reports.php', $hr_params ); ?>" class="feedback-status-link submitted">✅ Submitted</a>
												<?php elseif ( $feedback_status['status'] === 'ready-for-review' ) : ?>
													<a href="<?php echo build_team_url( 'hr-reports.php', $hr_params ); ?>" class="feedback-status-link review">📤 Ready for review</a>
												<?php elseif ( $feedback_status['status'] === 'draft-finalized' ) : ?>
													<a href="<?php echo build_team_url( 'hr-reports.php', $hr_params ); ?>" class="feedback-status-link draft-finalized">📋 Draft finalized</a>
												<?php elseif ( $feedback_status['status'] === 'started' ) : ?>
													<a href="<?php echo build_team_url( 'hr-reports.php', $hr_params ); ?>" class="feedback-status-link draft">📝 Started</a>
												<?php else : ?>
													<a href="<?php echo build_team_url( 'hr-reports.php', $hr_params ); ?>" class="feedback-status-link none">🔴 Not started</a>
												<?php endif; ?>
											</div>
										</div>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php else : ?>
							<p style="color: #666; font-style: italic;">No team members yet. <a href="<?php echo build_team_url( 'admin.php', array( 'tab' => 'members', 'add' => 'new' ) ); ?>" style="color: #007cba; text-decoration: none;">Add your first team member →</a></p>
						<?php endif; ?>
					</div>

					<div class="section">
						<h3>Leadership (<?php echo count( $team_data['leadership'] ); ?>)</h3>
						<?php if ( ! empty( $team_data['leadership'] ) ) : ?>
							<ul class="people-list">
								<?php foreach ( $team_data['leadership'] as $username => $leader ) : ?>
									<li>
										<div class="person-row-container">
											<a href="<?php echo $leader->get_profile_url(); ?>" class="person-row">
												<div class="person-info">
													<div class="person-name"><?php echo htmlspecialchars( $leader->get_display_name_with_nickname() ); ?> <span style="color: #666; font-weight: normal;">(<?php echo htmlspecialchars( $leader->role ); ?>)</span></div>
													<div class="person-username">
														@<?php echo htmlspecialchars( $leader->get_username() ); ?>
														<?php if ( ! empty( $leader->timezone ) || ! empty( $leader->location ) ) : ?>
															<span id="time-<?php echo htmlspecialchars( $username ); ?>" style="margin-left: 8px; color: #666; font-size: 12px;"></span>
														<?php endif; ?>
													</div>
												</div>
											</a>
											<div class="person-links">
												<?php render_person_links( $leader->links ); ?>
												<!-- Leaders don't need monthly HR feedback -->
											</div>
										</div>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php else : ?>
							<p style="color: #666; font-style: italic;">No leadership yet. <a href="<?php echo build_team_url( 'admin.php', array( 'tab' => 'leadership', 'add' => 'new' ) ); ?>" style="color: #007cba; text-decoration: none;">Add your first leader →</a></p>
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
															<span id="time-<?php echo htmlspecialchars( $username ); ?>" style="margin-left: 8px; color: #666; font-size: 12px;"></span>
														<?php endif; ?>
													</div>
												</div>
											</a>
											<div class="person-links">
												<?php render_person_links( $alumnus->links ); ?>
											</div>
										</div>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php else : ?>
							<p style="color: #666; font-style: italic;">No alumni yet. Alumni are created by moving existing team members or leaders.</p>
						<?php endif; ?>
					</div>
				</div>

				<div class="events-sidebar">
					<a href="<?php echo build_team_url( 'events.php', array( 'privacy' => $privacy_mode ? '1' : '0' ) ); ?>" style="color: inherit; text-decoration: none; display: block; margin-bottom: 15px;">
						<h3 style="margin: 0; color: #007cba; cursor: pointer; transition: color 0.2s;">🗓️ Upcoming Events →</h3>
					</a>
					<?php
					render_upcoming_events_sidebar( null, 6 );
					?>

					<!-- HR Feedback Overview -->
					<?php if ( ! empty( $team_members_needing_hr ) ) : ?>
						<div style="margin-top: 30px;">
							<a href="<?php echo build_team_url( 'hr-stats.php', array( 'privacy' => $privacy_mode ? '1' : '0' ) ); ?>" style="color: inherit; text-decoration: none; display: block; margin-bottom: 15px;">
								<h3 style="margin: 0; color: #007cba; cursor: pointer; transition: color 0.2s;">📊 HR Feedback Overview →</h3>
							</a>
							<?php if ( ! empty( $hr_monthly_stats ) ) : ?>
								<div style="display: flex; flex-direction: column; gap: 8px;">
									<?php foreach ( $hr_monthly_stats as $month => $stats ) : ?>
										<div style="font-size: 0.9em;">
											<strong><?php echo date( 'M Y', strtotime( $month . '-01' ) ); ?>:</strong>
											<?php echo $stats['completed']; ?> of <?php echo $stats['total']; ?> submitted
											<?php if ( isset( $stats['not_necessary'] ) && $stats['not_necessary'] > 0 ) : ?>
												<span style="color: #666; font-size: 0.85em;">(<?php echo $stats['not_necessary']; ?> not needed)</span>
											<?php endif; ?>
										</div>
									<?php endforeach; ?>
								</div>
							<?php else : ?>
								<p style="color: #666; font-style: italic; font-size: 0.9em;">No HR feedback data available yet.</p>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</div>
			</div>


		<?php else : ?>
			<div class="header">
				<h1>Page Not Found</h1>
				<p><a href="?<?php echo $current_team !== 'team' ? 'team=' . urlencode( $current_team ) : ''; ?>">← Back to Team Overview</a></p>
			</div>
		<?php endif; ?>

		<!-- Footer with admin/privacy links -->
		<footer style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee; text-align: center; font-size: 14px;">
			<?php if ( $privacy_mode ) : ?>
				<a href="?<?php echo http_build_query( array_merge( $_GET, array( 'privacy' => '0' ) ) ); ?>" style="color: #666; text-decoration: none; margin-right: 15px;">🔒 Privacy Mode ON</a>
			<?php else : ?>
				<a href="?<?php echo http_build_query( array_merge( $_GET, array( 'privacy' => '1' ) ) ); ?>" style="color: #666; text-decoration: none; margin-right: 15px;">🔓 Privacy Mode OFF</a>
			<?php endif; ?>
			<a href="<?php echo build_team_url( 'admin.php' ); ?>" style="color: #666; text-decoration: none;">⚙️ Admin Panel</a>
		</footer>
	</div>

	<script src="assets/cmd-k.js"></script>
	<script src="assets/script.js"></script>
	<script>
		// Initialize functionality when DOM is ready
		document.addEventListener('DOMContentLoaded', () => {
			// Initialize Command-K with data
			const peopleData = <?php echo json_encode( get_all_people_from_all_teams( $privacy_mode ) ); ?>;
			const teamsData = <?php echo json_encode( get_all_teams_stats() ); ?>;
			initializeCommandK(peopleData, teamsData);

			// Initialize timezone displays for overview page (no "your time" comparison)
			<?php 
			$all_people = array_merge( $team_data['team_members'], $team_data['leadership'], $team_data['alumni'] );
			foreach ( $all_people as $username => $person_obj ) :
				if ( ! empty( $person_obj->timezone ) ) :
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