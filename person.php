<?php
/**
 * Individual Person View
 * 
 * Displays detailed information for a specific team member, leader, or alumni
 */

require_once __DIR__ . '/includes/common.php';
require_once __DIR__ . '/includes/person.php';
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
	<meta name="color-scheme" content="light dark">
	<title><?php echo htmlspecialchars( $person_data->get_display_name_with_nickname() ) . ' - ' . htmlspecialchars( $team_data['team_name'] ) . ' Team'; ?></title>
	<link rel="stylesheet" href="assets/style.css">
	<link rel="stylesheet" href="assets/cmd-k.css">
</head>
<body>
	<?php render_cmd_k_panel(); ?>

	<?php render_dark_mode_toggle(); ?>

	<div class="container">
		<div class="person-header">
			<div>
				<h1 class="person-title">
					<?php echo htmlspecialchars( $person_data->get_display_name_with_nickname() ); ?>
					<span class="person-subtitle">
						@<?php echo htmlspecialchars( $person_data->get_username() ); ?>
						<?php if ( ! empty( $person_data->role ) ) : ?>
							• <?php echo htmlspecialchars( $person_data->role ); ?>
						<?php endif; ?>
						<?php if ( $is_alumni ) : ?>
							• Alumni
						<?php endif; ?>
					</span>
				</h1>
				<div class="back-nav">
					<a href="<?php echo build_team_url( 'index.php' ); ?>">← Back to Team Overview</a>
				</div>
			</div>

			<div class="person-tabs">
				<a href="<?php echo build_team_url( 'person.php', array( 'person' => $person, 'privacy' => $privacy_mode ? '1' : '0' ) ); ?>"
				   class="tab-link active">👤 Member Overview</a>
				<?php if ( $is_team_member && ! ( isset( $team_data['not_managing_team'] ) && $team_data['not_managing_team'] ) ) : ?>
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
				<?php if ( ! empty( $person_data->birthday ) || ! empty( $person_data->company_anniversary ) || ! empty( $kids_with_ages ) || ! empty( $person_data->notes ) || ! empty( $person_data->location ) || ! empty( $person_data->partner ) ) : ?>
					<div class="section">
						<h2>Personal Details</h2>

						<?php if ( ! empty( $person_data->birthday ) ) : ?>
							<?php
							// Calculate age from birthday
							$age_display = '';
							if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $person_data->birthday ) ) {
								$birth_date = DateTime::createFromFormat( 'Y-m-d', $person_data->birthday );
								if ( $birth_date ) {
									$current_date = new DateTime();
									$age = $current_date->diff( $birth_date )->y;
									if ( $privacy_mode ) {
										$age_display = '[Hidden]';
									} else {
										$age_display = $age . ' (born ' . $birth_date->format( 'F j, Y' ) . ')';
									}
								}
							} elseif ( preg_match( '/^\d{2}-\d{2}$/', $person_data->birthday ) ) {
								// Legacy MM-DD format - can't calculate exact age
								if ( $privacy_mode ) {
									$age_display = '[Hidden]';
								} else {
									$display_date = DateTime::createFromFormat( 'm-d', $person_data->birthday );
									if ( $display_date ) {
										$age_display = 'Birthday ' . $display_date->format( 'F j' );
									}
								}
							}
							?>
							<?php if ( $age_display ) : ?>
								<p><strong>🎂 Age:</strong> <?php echo htmlspecialchars( $age_display ); ?></p>
							<?php endif; ?>
						<?php endif; ?>

						<?php if ( ! empty( $person_data->company_anniversary ) ) : ?>
							<?php
							$anniversary_date = DateTime::createFromFormat( 'Y-m-d', $person_data->company_anniversary );
							if ( $anniversary_date ) {
								$current_date = new DateTime();
								$years_at_company = $current_date->diff( $anniversary_date )->y;
								
								if ( $privacy_mode ) {
									echo '<p><strong>🏢 Years at Company:</strong> ' . $years_at_company . ' years</p>';
								} else {
									if ( $years_at_company == 0 ) {
										// First year - show start date
										echo '<p><strong>🏢 Years at Company:</strong> Started ' . htmlspecialchars( $anniversary_date->format( 'F j, Y' ) ) . ' (less than 1 year)</p>';
									} else {
										// Multiple years - show time at company and start date
										echo '<p><strong>🏢 Years at Company:</strong> ' . $years_at_company . ' years (started ' . htmlspecialchars( $anniversary_date->format( 'F j, Y' ) ) . ')</p>';
									}
								}
							}
							?>
						<?php endif; ?>

						<?php if ( ! empty( $person_data->location ) ) : ?>
							<p>
								<strong>🌍 Location:</strong>
								<?php
								$display_location = $person_data->location;
								if ( $privacy_mode ) {
									// Extract country from location (assume country is last part after comma)
									$location_parts = array_map( 'trim', explode( ',', $person_data->location ) );
									$display_location = end( $location_parts ); // Get the last part (country)
								}
								?>
								<a href="https://maps.google.com/maps?q=<?php echo urlencode( $privacy_mode ? $display_location : $person_data->location ); ?>" target="_blank" class="location-link"><?php echo htmlspecialchars( $display_location ); ?></a>
								<span id="time-<?php echo htmlspecialchars( $person ); ?>" class="timezone-display"></span>
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
										<span class="child-badge" title="<?php echo htmlspecialchars( $tooltip ); ?>">
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

					<?php if ( $has_repos ) : ?>
						<div class="section">
							<h2>GitHub Repositories</h2>
							<?php
							$repos = is_array( $person_data->github_repos ) ? $person_data->github_repos : array_filter( array_map( 'trim', explode( ',', $person_data->github_repos ) ) );
							if ( ! empty( $repos ) ) :
							?>
								<div class="github-repo-grid">
									<?php foreach ( $repos as $repo ) : ?>
										<div class="github-repo-card">
											<a href="https://github.com/<?php echo htmlspecialchars( $repo ); ?>" target="_blank" class="github-repo-link">
												📦 <?php echo htmlspecialchars( $repo ); ?>
											</a>
											<?php if ( $has_github ) : ?>
												<a href="https://github.com/<?php echo htmlspecialchars( $repo ); ?>/pulls/<?php echo htmlspecialchars( $person_data->github ); ?>" target="_blank" class="github-pr-link">PRs</a>
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

						<div class="quick-links-container">
							<?php if ( $has_other_links ) : ?>
									<?php foreach ( $filtered_links as $link_text => $link_url ) : ?>
										<?php if ( ! empty( $link_url ) ) : ?>
											<a href="<?php echo htmlspecialchars( str_replace( '$username', $person, $link_url ) ); ?>" target="_blank" class="quick-link">
												<?php echo get_link_icon( $link_text, $link_url, 18); ?>
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
									<a href="<?php echo htmlspecialchars( $activity_url_month ); ?>" target="_blank" class="activity-link-month">
										📊 Activity (Month)
									</a>
									<a href="<?php echo htmlspecialchars( $activity_url_week ); ?>" target="_blank" class="activity-link-week">
										📊 Activity (Week)
									</a>
							<?php endif; ?>
							</div>
						</div>
					<?php endif; ?>

					<?php if ( ! empty( $person_data->notes ) ) : ?>
						<div class="notes-section">
							<strong>📝 Notes:</strong>
							<p class="notes-content"><?php echo nl2br( htmlspecialchars( $person_data->notes ) ); ?></p>
						</div>
					<?php endif; ?>

					<?php if ( $has_any_accounts ) : ?>
						<div class="section">
							<h2>External Accounts</h2>
							<div class="external-account-links">
								<?php if ( $has_github ) : ?>
									<a href="https://github.com/<?php echo htmlspecialchars( $person_data->github ); ?>" target="_blank" class="external-link github">
										<?php echo get_link_icon('GitHub', 'https://github.com/' . $person_data->github, 16); ?>
										GitHub
									</a>
								<?php endif; ?>

								<?php if ( $has_linkedin ) : ?>
									<a href="https://linkedin.com/in/<?php echo htmlspecialchars( $person_data->linkedin ); ?>" target="_blank" class="external-link linkedin">
										<?php echo get_link_icon('LinkedIn', 'https://linkedin.com/in/' . $person_data->linkedin, 16); ?>
										LinkedIn
									</a>
								<?php endif; ?>

								<?php if ( $has_wordpress ) : ?>
									<a href="https://profiles.wordpress.org/<?php echo htmlspecialchars( $person_data->wordpress ); ?>" target="_blank" class="external-link wordpress">
										<?php echo get_link_icon('WordPress.org', 'https://profiles.wordpress.org/' . $person_data->wordpress, 16); ?>
										WordPress.org
									</a>
								<?php endif; ?>

								<?php if ( $has_linear ) : ?>
									<a href="<?php echo htmlspecialchars( $person_data->links['Linear'] ); ?>" target="_blank" class="external-link linear">
										<?php echo get_link_icon('Linear', $person_data->links['Linear'], 16); ?>
										Linear
									</a>
								<?php endif; ?>
							</div>
						</div>
					<?php endif; ?>

					<?php endif; ?>
			</div>

			<div class="events-sidebar">
				<a href="<?php echo build_team_url( 'events.php', array( 'privacy' => $privacy_mode ? '1' : '0' ) ); ?>" class="sidebar-section-link">
					<h3 class="sidebar-section-heading">🗓️ Upcoming Events</h3>
				</a>
				<?php render_upcoming_events_sidebar( $person ); ?>

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
				// but don't show if the team is not managing HR feedback
				$show_hr_section = ( ! empty( $past_feedback ) || $is_team_member ) && ! ( isset( $team_data['not_managing_team'] ) && $team_data['not_managing_team'] );
				?>
				<?php if ( $show_hr_section ) : ?>
					<div style="margin-top: 30px">
					<a href="<?php echo build_team_url( 'hr-reports.php', array( 'person' => $person, 'month' => get_hr_feedback_month(), 'privacy' => $privacy_mode ? '1' : '0' ) ); ?>" class="hr-feedback-header">
						<h3 class="sidebar-section-heading">📝 HR Feedbacks</h3>
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
							if ( $privacy_mode ) {
								// Use generic performance text for privacy
								$performance_text = 'HIGH/GOOD/LOW';
								$performance_rating = 'privacy'; // Use neutral styling
							} else {
								$performance_text = ucfirst( $performance_rating );
							}
							?>
							<details class="hr-feedback-details">
								<summary class="hr-feedback-summary">
									<span class="hr-feedback-month"><?php echo date( 'M Y', strtotime( $month . '-01' ) ); ?></span>
									<span class="performance-badge performance-<?php echo $performance_rating; ?>">
										<?php echo $performance_text; ?>
									</span>
									<span class="hr-feedback-arrow">▶</span>
								</summary>
								<?php if ( ! $privacy_mode ) : ?>
									<div class="hr-feedback-content">
										<?php echo $feedback_text; ?>
									</div>
								<?php else : ?>
									<div class="hr-feedback-privacy">
										[Feedback content hidden in privacy mode]
									</div>
								<?php endif; ?>
							</details>
							<?php
							$count++;
						}

						if ( count( $past_feedback ) > 10 ) {
							?>
							<div class="hr-feedback-all-link">
								<a href="<?php echo build_team_url( 'hr-reports.php', array( 'person' => $person, 'privacy' => $privacy_mode ? '1' : '0' ) ); ?>">
									View all <?php echo count( $past_feedback ); ?> feedback entries →
								</a>
							</div>
							<?php
						}
					} else {
						?>
						<div class="hr-no-feedback">
							<p>
								No feedback history yet
							</p>
						</div>
						<?php
					}
					?>
					</div>
				<?php endif; ?>
				</div>
			</div>
		</div>

		<footer class="privacy-footer">
			<?php if ( $privacy_mode ) : ?>
				<a href="?<?php echo http_build_query( array_merge( $_GET, array( 'privacy' => '0' ) ) ); ?>">🔒 Privacy Mode ON</a>
			<?php else : ?>
				<a href="?<?php echo http_build_query( array_merge( $_GET, array( 'privacy' => '1' ) ) ); ?>">🔓 Privacy Mode OFF</a>
			<?php endif; ?>
			<a href="<?php echo build_team_url( 'admin.php' ); ?>">⚙️ Admin Panel</a>
			<a href="<?php echo $person_data->get_edit_url(); ?>">✏️ Edit Person</a>
		</footer>
	</div>

	<script src="assets/cmd-k.js"></script>
	<script src="assets/script.js"></script>
	<?php init_cmd_k_js( $privacy_mode ); ?>
	<script>
		document.addEventListener('DOMContentLoaded', () => {
			<?php if ( ! empty( $person_data ) && ( ! empty( $person_data->location ) || ! empty( $person_data->timezone ) ) ) : ?>
			createTimeUpdater('<?php echo addslashes( $person_data->timezone ); ?>', '<?php echo addslashes( $person ); ?>');
			<?php endif; ?>
		});
	</script>
</body>
</html>