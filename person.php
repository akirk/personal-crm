<?php
/**
 * Individual Person View
 * 
 * Displays detailed information for a specific team member, leader, or alumni
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

// Get current person
$person = $_GET['person'] ?? null;

if ( empty( $person ) ) {
	header( 'Location: ' . build_team_url( 'index.php' ) );
	exit;
}

// Load person data for title
$person_data = null;
if ( isset( $team_data['team_members'][ $person ] ) ) {
	$person_data = $team_data['team_members'][ $person ];
} elseif ( isset( $team_data['leadership'][ $person ] ) ) {
	$person_data = $team_data['leadership'][ $person ];
} elseif ( isset( $team_data['alumni'][ $person ] ) ) {
	$person_data = $team_data['alumni'][ $person ];
}

if ( ! $person_data ) {
	header( 'Location: ' . build_team_url( 'index.php' ) );
	exit;
}

// Determine person type
$is_team_member = isset( $team_data['team_members'][ $person ] );
$is_alumni = isset( $team_data['alumni'][ $person ] );

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo htmlspecialchars( $person_data->get_display_name_with_nickname() ) . ' - ' . htmlspecialchars( $team_data['team_name'] ) . ' Team'; ?></title>
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
		<div class="header" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">
			<div>
				<h1 style="margin: 0; font-size: 24px;">
					<?php echo htmlspecialchars( $person_data->get_display_name_with_nickname() ); ?>
					<span style="color: #666; font-size: 16px; font-weight: normal;">
						@<?php echo htmlspecialchars( $person_data->get_username() ); ?>
						<?php if ( ! empty( $person_data->role ) ) : ?>
							• <?php echo htmlspecialchars( $person_data->role ); ?>
						<?php endif; ?>
						<?php if ( $is_alumni ) : ?>
							• Alumni
						<?php endif; ?>
					</span>
				</h1>
				<div style="margin-top: 5px;">
					<a href="<?php echo build_team_url( 'index.php' ); ?>" style="color: #666; text-decoration: none; font-size: 14px;">← Back to Team Overview</a>
				</div>
			</div>

			<!-- Tab Navigation -->
			<div class="person-tabs">
				<a href="<?php echo build_team_url( 'person.php', array( 'person' => $person, 'privacy' => $privacy_mode ? '1' : '0' ) ); ?>"
				   class="tab-link active">👤 Member Overview</a>
				<?php if ( $is_team_member ) : ?>
					<?php
					$feedback_status = $person_data->get_monthly_feedback_status();
					$status_class = '';
					$status_icon = '';
					switch ( $feedback_status['status'] ) {
						case 'submitted':
							$status_class = 'tab-status-submitted';
							$status_icon = '✅';
							break;
						case 'ready-for-review':
							$status_class = 'tab-status-review';
							$status_icon = '📤';
							break;
						case 'draft-finalized':
							$status_class = 'tab-status-draft-finalized';
							$status_icon = '📋';
							break;
						case 'started':
							$status_class = 'tab-status-draft';
							$status_icon = '📝';
							break;
						default:
							$status_class = 'tab-status-none';
							$status_icon = '🔴';
					}
					?>
					<a href="<?php echo build_team_url( 'hr-reports.php', array( 'person' => $person, 'month' => get_hr_feedback_month(), 'privacy' => $privacy_mode ? '1' : '0' ) ); ?>"
					   class="tab-link <?php echo $status_class; ?>">
					   <?php echo $status_icon; ?> HR Feedback
					</a>
				<?php endif; ?>
			</div>
		</div>

		<?php
		// Get upcoming events and personal details
		$kids_with_ages = $person_data->get_kids_ages();
		?>

		<div class="overview-layout">
			<div class="people-section">
				<?php if ( ! empty( $person_data->birthday ) || ! empty( $person_data->company_anniversary ) || ! empty( $kids_with_ages ) || ! empty( $person_data->notes ) || ! empty( $person_data->location ) ) : ?>
					<div class="section">
						<h2>Personal Details</h2>

						<?php if ( ! empty( $person_data->birthday ) ) : ?>
							<p><strong>🎂 Birthday:</strong> <?php echo htmlspecialchars( $person_data->get_birthday_display() ); ?></p>
						<?php endif; ?>

						<?php if ( ! empty( $person_data->company_anniversary ) ) : ?>
							<?php
							$anniversary_date = DateTime::createFromFormat( 'Y-m-d', $person_data->company_anniversary );
							if ( $anniversary_date ) {
								$current_date = new DateTime();
								$years_at_company = $current_date->diff( $anniversary_date )->y;
								
								// Calculate next anniversary
								$current_year = (int) $current_date->format( 'Y' );
								$next_anniversary_year = $current_year;
								if ( $anniversary_date > $current_date || $anniversary_date->format( 'm-d' ) < $current_date->format( 'm-d' ) ) {
									$next_anniversary_year = $current_year + 1;
								}
								$next_anniversary = DateTime::createFromFormat( 'Y-m-d', $next_anniversary_year . '-' . $anniversary_date->format( 'm-d' ) );
								
								if ( $privacy_mode ) {
									echo '<p><strong>🏢 Company:</strong> ' . $years_at_company . ' years (next anniversary hidden)</p>';
								} else {
									if ( $years_at_company == 0 ) {
										// First year - show when they will complete their first year
										echo '<p><strong>🏢 Company:</strong> Started ' . htmlspecialchars( $anniversary_date->format( 'F j, Y' ) ) . ' • First anniversary ' . htmlspecialchars( $next_anniversary->format( 'F j, Y' ) ) . '</p>';
									} else {
										// Multiple years - show time at company and next anniversary
										echo '<p><strong>🏢 Company:</strong> ' . $years_at_company . ' years • Next anniversary ' . htmlspecialchars( $next_anniversary->format( 'F j, Y' ) ) . '</p>';
									}
								}
							}
							?>
						<?php endif; ?>

						<?php if ( ! empty( $person_data->location ) ) : ?>
							<p>
								<strong>🌍 Location:</strong>
								<a href="https://maps.google.com/maps?q=<?php echo urlencode( $person_data->location ); ?>" target="_blank" style="color: #007cba; text-decoration: none;"><?php echo htmlspecialchars( $person_data->location ); ?></a>
								<span id="time-<?php echo htmlspecialchars( $person ); ?>" style="margin-left: 10px; color: #666; font-size: 14px;"></span>
							</p>
						<?php endif; ?>

						<?php if ( ! empty( $person_data->partner ) ) : ?>
							<p><strong>💑 Partner:</strong> <?php echo htmlspecialchars( $person_data->partner ); ?></p>
						<?php endif; ?>

						<?php if ( ! empty( $kids_with_ages ) ) : ?>
							<p>
								<strong>👨‍👩‍👧‍👦 Children:</strong>
								<?php if ( $privacy_mode ) : ?>
									<?php echo count( $kids_with_ages ); ?> child<?php echo count( $kids_with_ages ) !== 1 ? 'ren' : ''; ?>
								<?php else : ?>
									<?php foreach ( $kids_with_ages as $kid ) : ?>
										<?php 
										// Build tooltip text with available birth data
										$tooltip = '';
										if ( isset( $kid['age'] ) ) {
											$tooltip .= 'Age: ' . $kid['age'] . ' years';
										}
										if ( ! empty( $kid['birth_year'] ) ) {
											if ( $tooltip ) $tooltip .= ' • ';
											$tooltip .= 'Born: ' . $kid['birth_year'];
										}
										if ( ! empty( $kid['birthday'] ) ) {
											if ( $tooltip ) $tooltip .= ' • ';
											$tooltip .= 'Birthday: ' . $kid['birthday'];
										}
										if ( ! $tooltip ) {
											$tooltip = 'No birth data available';
										}
										?>
										<span style="background: #f0f0f0; color: #333; padding: 3px 8px; border-radius: 12px; font-size: 13px; margin-left: 6px; cursor: help;" title="<?php echo htmlspecialchars( $tooltip ); ?>">
											<?php echo htmlspecialchars( $kid['name'] ); ?>
											<?php if ( isset( $kid['age'] ) ) : ?>
												(<?php echo $kid['age']; ?>y)
											<?php elseif ( ! empty( $kid['birth_year'] ) ) : ?>
												(born <?php echo $kid['birth_year']; ?>)
											<?php endif; ?>
										</span>
									<?php endforeach; ?>
								<?php endif; ?>
							</p>
						<?php endif; ?>
					</div>

					<?php
					// Check if person has any external accounts or GitHub repos
					$has_github = ! empty( $person_data->github );
					$has_wordpress = ! empty( $person_data->wordpress );
					$has_linkedin = ! empty( $person_data->linkedin );
					$has_linear = ! empty( $person_data->links['Linear'] ?? '' );
					$has_repos = ! empty( $person_data->github_repos );
					$has_any_accounts = $has_github || $has_wordpress || $has_linkedin || $has_linear;
					?>

					<?php if ( $has_any_accounts ) : ?>
						<div class="section">
							<h2>External Accounts</h2>
							<div style="display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 15px;">
								<?php if ( $has_github ) : ?>
									<a href="https://github.com/<?php echo htmlspecialchars( $person_data->github ); ?>" target="_blank" style="display: inline-flex; align-items: center; padding: 6px 12px; background: #24292e; color: white; border-radius: 6px; text-decoration: none; font-size: 14px; font-weight: 500;">
										<?php echo str_replace('#222326', 'currentColor', get_link_icon('GitHub', 'https://github.com/' . $person_data->github, 16)); ?>
										GitHub
									</a>
								<?php endif; ?>

								<?php if ( $has_linkedin ) : ?>
									<a href="https://linkedin.com/in/<?php echo htmlspecialchars( $person_data->linkedin ); ?>" target="_blank" style="display: inline-flex; align-items: center; padding: 6px 12px; background: #0077b5; color: white; border-radius: 6px; text-decoration: none; font-size: 14px; font-weight: 500;">
										<?php echo str_replace('#222326', 'currentColor', get_link_icon('LinkedIn', 'https://linkedin.com/in/' . $person_data->linkedin, 16)); ?>
										LinkedIn
									</a>
								<?php endif; ?>

								<?php if ( $has_wordpress ) : ?>
									<a href="https://profiles.wordpress.org/<?php echo htmlspecialchars( $person_data->wordpress ); ?>" target="_blank" style="display: inline-flex; align-items: center; padding: 6px 12px; background: #21759b; color: white; border-radius: 6px; text-decoration: none; font-size: 14px; font-weight: 500;">
										<?php echo str_replace('#222326', 'currentColor', get_link_icon('WordPress.org', 'https://profiles.wordpress.org/' . $person_data->wordpress, 16)); ?>
										WordPress.org
									</a>
								<?php endif; ?>

								<?php if ( $has_linear ) : ?>
									<a href="<?php echo htmlspecialchars( $person_data->links['Linear'] ); ?>" target="_blank" style="display: inline-flex; align-items: center; padding: 6px 12px; background: #5e6ad2; color: white; border-radius: 6px; text-decoration: none; font-size: 14px; font-weight: 500;">
										<?php echo str_replace('#222326', 'currentColor', get_link_icon('Linear', $person_data->links['Linear'], 16)); ?>
										Linear
									</a>
								<?php endif; ?>
							</div>
						</div>
					<?php endif; ?>

					<?php if ( $has_repos ) : ?>
						<div class="section">
							<h2>GitHub Repositories</h2>
							<?php
							$repos = is_array( $person_data->github_repos ) ? $person_data->github_repos : array_filter( array_map( 'trim', explode( ',', $person_data->github_repos ) ) );
							if ( ! empty( $repos ) ) :
							?>
								<div style="display: flex; flex-wrap: wrap; gap: 8px;">
									<?php foreach ( $repos as $repo ) : ?>
										<div style="display: flex; align-items: center; gap: 8px; padding: 8px 12px; background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 6px;">
											<a href="https://github.com/<?php echo htmlspecialchars( $repo ); ?>" target="_blank" style="color: #333; text-decoration: none; font-weight: 500;">
												📦 <?php echo htmlspecialchars( $repo ); ?>
											</a>
											<?php if ( $has_github ) : ?>
												<a href="https://github.com/<?php echo htmlspecialchars( $repo ); ?>/pulls/<?php echo htmlspecialchars( $person_data->github ); ?>" target="_blank" style="color: #007cba; text-decoration: none; font-size: 12px; padding: 2px 6px; background: #e3f2fd; border-radius: 3px;">PRs</a>
											<?php endif; ?>
										</div>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
						</div>
					<?php endif; ?>

					<?php
					// Filter out external account links from regular links
					$filtered_links = array();
					if ( ! empty( $person_data->links ) ) {
						foreach ( $person_data->links as $link_text => $link_url ) {
							if ( ! in_array( $link_text, array( 'Linear', 'WordPress.org', 'LinkedIn' ) ) ) {
								$filtered_links[$link_text] = $link_url;
							}
						}
					}
					$has_other_links = ! empty( $filtered_links );
					$has_activity_links = $is_team_member && ! empty( $person_data->username ) && isset( $team_data['activity_url_prefix'] );
					?>

					<?php if ( $has_other_links || $has_activity_links ) : ?>
						<div class="section">
							<h2>Quick Links</h2>

						<div style="display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 15px;">
							<?php if ( $has_other_links ) : ?>
									<?php foreach ( $filtered_links as $link_text => $link_url ) : ?>
										<?php if ( ! empty( $link_url ) ) : ?>
											<a href="<?php echo htmlspecialchars( str_replace( '$username', $person, $link_url ) ); ?>" target="_blank" style="display: inline-flex; align-items: center; padding: 8px 16px; background: #007cba; color: white; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 500; box-shadow: 0 2px 4px rgba(0,0,0,0.1); transition: all 0.2s;">
												<?php echo str_replace('#222326', 'currentColor', get_link_icon( $link_text, $link_url, 18)); ?>
												<?php echo htmlspecialchars( $link_text ); ?>
											</a>
										<?php endif; ?>
									<?php endforeach; ?>
							<?php endif; ?>

							<?php if ( $is_team_member && ! empty( $person_data->username ) && isset( $team_data['activity_url_prefix'] ) ) : ?>
								<?php
								$last_month = date( 'Y-m', strtotime( 'last month') );
								$start_date = $last_month . '-01';
								$end_date = date( 'Y-m-d', strtotime( $start_date ) );
								$activity_url_month = $team_data['activity_url_prefix'] . '&member=' . urlencode( $person_data->username ) . "&start={$start_date}&end={$end_date}";
								$activity_url_week = $team_data['activity_url_prefix'] . '&member=' . urlencode( $person_data->username );
								?>
									<a href="<?php echo htmlspecialchars( $activity_url_month ); ?>" target="_blank" style="display: inline-flex; align-items: center; padding: 8px 16px; background: #28a745; color: white; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 500; box-shadow: 0 2px 4px rgba(0,0,0,0.1); transition: all 0.2s;">
										📊 Activity (Month)
									</a>
									<a href="<?php echo htmlspecialchars( $activity_url_week ); ?>" target="_blank" style="display: inline-flex; align-items: center; padding: 8px 16px; background: #17a2b8; color: white; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 500; box-shadow: 0 2px 4px rgba(0,0,0,0.1); transition: all 0.2s;">
										📊 Activity (Week)
									</a>
							<?php endif; ?>
							</div>
						</div>
					<?php endif; ?>

					<?php if ( ! empty( $person_data->notes ) ) : ?>
						<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 15px;">
							<strong>📝 Notes:</strong>
							<p style="margin: 10px 0 0 0;"><?php echo nl2br( htmlspecialchars( $person_data->notes ) ); ?></p>
						</div>
					<?php endif; ?>
					<?php endif; ?>
			</div>

			<!-- Sidebar with HR Feedbacks and Events -->
			<div class="events-sidebar">
					<?php
					// Get HR feedback history for this person first
					$feedback_history = get_person_feedback_history( $person );
					$current_month = get_hr_feedback_month();
					// Include feedback from all months that have data (including current month if submitted)
					$past_feedback = array_filter( $feedback_history, function( $feedback, $month ) use ( $current_month ) {
						if ( $month === 'hr_monthly_link' || ! is_array( $feedback ) ) {
							return false;
						}
						// Include current month only if it's submitted to HR
						if ( $month === $current_month ) {
							return isset( $feedback['submitted_to_hr'] ) && $feedback['submitted_to_hr'];
						}
						// Include all past months
						return true;
					}, ARRAY_FILTER_USE_BOTH );
					krsort( $past_feedback ); // Sort by month descending
					
					// Show HR Feedbacks section if there's past feedback OR if they're a team member (all team members need HR feedback)
					$show_hr_section = ! empty( $past_feedback ) || $is_team_member;
					?>
					<?php if ( $show_hr_section ) : ?>
						<a href="<?php echo build_team_url( 'hr-reports.php', array( 'person' => $person, 'month' => get_hr_feedback_month(), 'privacy' => $privacy_mode ? '1' : '0' ) ); ?>"
						   style="color: inherit; text-decoration: none; display: block; margin-bottom: 15px;">
							<h3 style="margin-top: 0; color: #333; border-bottom: 2px solid #007cba; padding-bottom: 8px;">📝 HR Feedbacks</h3>
						</a>
					<?php

					if ( ! empty( $past_feedback ) ) {
						$count = 0;
						foreach ( $past_feedback as $month => $feedback ) {
							if ( $count >= 10 ) break; // Show only last 10 feedback entries
							$feedback_text = sanitize_html( $feedback['feedback_to_person'] ) ?: htmlspecialchars( $feedback['feedback_to_person'] );
							$feedback_plain = strip_tags( $feedback_text );
							$teaser = strlen( $feedback_plain ) > 100 ? substr( $feedback_plain, 0, 100 ) . '...' : $feedback_plain;

							// Use existing performance badge classes
							$performance_rating = $feedback['performance'] ?? 'good';
							$performance_text = ucfirst( $performance_rating );
							?>
							<details style="margin-bottom: 15px;">
								<summary style="cursor: pointer; padding: 8px 0; border-bottom: 1px solid #eee; list-style: none; position: relative;">
									<span style="font-weight: 500; color: #333;"><?php echo date( 'M Y', strtotime( $month . '-01' ) ); ?></span>
									<span class="performance-badge performance-<?php echo $performance_rating; ?>" style="margin-left: 10px;">
										<?php echo $performance_text; ?>
									</span>
									<span style="position: absolute; right: 0; color: #999; font-size: 14px;">▶</span>
								</summary>
								<?php if ( ! $privacy_mode ) : ?>
									<div style="padding: 12px 0 0 0; font-size: 13px; color: #666; line-height: 1.4;">
										<?php echo $feedback_text; ?>
									</div>
								<?php else : ?>
									<div style="padding: 12px 0 0 0; font-size: 13px; color: #666; font-style: italic;">
										[Feedback content hidden in privacy mode]
									</div>
								<?php endif; ?>
							</details>
							<?php
							$count++;
						}

						if ( count( $past_feedback ) > 10 ) {
							?>
							<div style="text-align: center; margin-top: 10px;">
								<a href="<?php echo build_team_url( 'hr-reports.php', array( 'person' => $person, 'privacy' => $privacy_mode ? '1' : '0' ) ); ?>"
								   style="color: #007cba; text-decoration: none; font-size: 12px;">
									View all <?php echo count( $past_feedback ); ?> feedback entries →
								</a>
							</div>
							<?php
						}
					} else {
						?>
						<div style="background: #f8f9fa; padding: 12px; border-radius: 6px; text-align: center;">
							<p style="margin: 0; color: #666; font-size: 13px; font-style: italic;">
								No feedback history yet
							</p>
						</div>
						<?php
					}
					?>
					<?php endif; ?>

					<!-- Upcoming Events -->
					<h3 style="<?php echo $show_hr_section ? 'margin-top: 30px;' : 'margin-top: 0;'; ?> color: #333; border-bottom: 2px solid #007cba; padding-bottom: 8px;">🗓️ Upcoming Events</h3>
					<?php render_upcoming_events_sidebar( $person ); ?>
				</div>
			</div>
		</div>

		<!-- Footer with admin/privacy links -->
		<footer style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee; text-align: center; font-size: 14px;">
			<?php if ( $privacy_mode ) : ?>
				<a href="?<?php echo http_build_query( array_merge( $_GET, array( 'privacy' => '0' ) ) ); ?>" style="color: #666; text-decoration: none; margin-right: 15px;">🔒 Privacy Mode ON</a>
			<?php else : ?>
				<a href="?<?php echo http_build_query( array_merge( $_GET, array( 'privacy' => '1' ) ) ); ?>" style="color: #666; text-decoration: none; margin-right: 15px;">🔓 Privacy Mode OFF</a>
			<?php endif; ?>
			<a href="<?php echo build_team_url( 'admin.php' ); ?>" style="color: #666; text-decoration: none; margin-right: 15px;">⚙️ Admin Panel</a>
			<a href="<?php echo $person_data->get_edit_url(); ?>" style="color: #666; text-decoration: none;">✏️ Edit Person</a>
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

			// Initialize timezone display for person pages
			<?php if ( ! empty( $person_data ) && ( ! empty( $person_data->location ) || ! empty( $person_data->timezone ) ) ) : ?>
			createTimeUpdater('<?php echo addslashes( $person_data->timezone ); ?>', '<?php echo addslashes( $person ); ?>');
			<?php endif; ?>
		});
	</script>
</body>
</html>