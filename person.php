<?php
/**
 * Individual Person View
 * 
 * Displays detailed information for a specific team member, leader, or alumni
 */

namespace PersonalCRM;

require_once __DIR__ . '/personal-crm.php';

// Debug: Log entry to person.php
error_log( 'DEBUG: person.php - Starting, REQUEST_URI: ' . $_SERVER['REQUEST_URI'] );

$crm = Common::get_instance();
$current_team = $crm->get_current_team_from_params();
if ( $current_team ) {
	// Check if person parameter exists in either $_GET or route parameters
	$has_person_param = isset( $_GET['person'] ) ||
	                   ( function_exists( 'get_query_var' ) && get_query_var( 'person' ) );

	if ( $current_team === $crm->get_default_team() && ! $has_person_param ) {
		// Redirect to root if default team is selected and no person specified
		error_log( 'DEBUG: person.php - REDIRECTING because current_team === get_default_team() && no person param' );
		header( 'Location: ' . $crm->build_url( 'index.php' ) );
		exit;
	}
} else {
	$current_team = $crm->use_default_team();
	$available_teams = $crm->get_available_teams();
	if ( count( $available_teams ) > 1 && ! $current_team ) {
		header( 'Location: ' . $crm->build_url( 'select.php' ) );
		exit;
	}
}

$privacy_mode = isset( $_GET['privacy'] ) && $_GET['privacy'] === '1';

// Get current person and team from route parameters or query parameters first
// Check $_GET first for backward compatibility
$person = $_GET['person'] ?? null;
$route_team = $_GET['team'] ?? null;

// Check wp-app route parameters using WordPress query vars
if ( empty( $person ) && function_exists( 'get_query_var' ) ) {
	$person = get_query_var( 'person' );
}
if ( empty( $route_team ) && function_exists( 'get_query_var' ) ) {
	$route_team = get_query_var( 'team' );
}

// Debug: Log what we found
error_log( 'DEBUG: person.php - person: ' . $person . ', team: ' . $route_team );

// Override current_team with route team if provided (this takes precedence over query params)
if ( ! empty( $route_team ) ) {
	$current_team = $route_team;
} else if ( ! $current_team ) {
	// Fallback if no current team is set
	$current_team = $crm->get_default_team();
}

// Load team configuration with Person objects
$team_data = $crm->load_team_config_with_objects( $current_team );

if ( empty( $person ) ) {
	// Debug: Check what parameters are available
	error_log( 'DEBUG: person.php - person parameter is empty' );
	error_log( 'DEBUG: $_GET = ' . print_r( $_GET, true ) );
	if ( function_exists( 'wp_app_get_route_param' ) ) {
		error_log( 'DEBUG: wp_app_get_route_param(person) = ' . wp_app_get_route_param( 'person' ) );
		error_log( 'DEBUG: wp_app_get_route_param(team) = ' . wp_app_get_route_param( 'team' ) );
	}

	header( 'Location: ' . $crm->build_url( 'index.php', array( 'privacy' => $privacy_mode ? '1' : '0' ) ) );
	exit;
}

// Load person data for title
$person_data = null;
if ( isset( $team_data['team_members'][ $person ] ) ) {
	$person_data = $team_data['team_members'][ $person ];
} elseif ( isset( $team_data['leadership'][ $person ] ) ) {
	$person_data = $team_data['leadership'][ $person ];
} elseif ( isset( $team_data['consultants'][ $person ] ) ) {
	$person_data = $team_data['consultants'][ $person ];
} elseif ( isset( $team_data['alumni'][ $person ] ) ) {
	$person_data = $team_data['alumni'][ $person ];
}

if ( ! $person_data ) {
	$original_team_data = $crm->load_team_config_with_objects( $current_team );
	foreach ( array( 'team_members', 'leadership', 'consultants', 'alumni' ) as $section ) {
		if ( isset( $original_team_data[$section][ $person ] ) && ! empty( $original_team_data[$section][ $person ]->deceased ) ) {
			$person_data = $original_team_data[$section][ $person ];
			break;
		}
	}
}

if ( ! $person_data ) {
	header( 'Location: ' . $crm->build_url( 'index.php', array( 'privacy' => $privacy_mode ? '1' : '0' ) ) );
	exit;
}

// Determine person type
$is_team_member = isset( $team_data['team_members'][ $person ] );
$is_consultant = isset( $team_data['consultants'][ $person ] );
$is_alumni = isset( $team_data['alumni'][ $person ] );

?>
<!DOCTYPE html>
<html <?php echo function_exists( 'wp_app_language_attributes' ) ? wp_app_language_attributes() : 'lang="en"'; ?>>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="color-scheme" content="light dark">
	<title><?php
	$page_title = esc_html( $person_data->get_display_name_with_nickname() ) . ' - ' . esc_html( $crm->get_team_display_title( $current_team ) );
	echo function_exists( 'wp_app_title' ) ? wp_app_title( $page_title ) : $page_title;
	?></title>
	<?php
	if ( function_exists( 'wp_app_enqueue_style' ) ) {
		wp_app_enqueue_style( 'a8c-hr-style', plugin_dir_url( __FILE__ ) . 'assets/style.css' );
		wp_app_enqueue_style( 'a8c-hr-cmd-k', plugin_dir_url( __FILE__ ) . 'assets/cmd-k.css' );
	} else {
		echo '<link rel="stylesheet" href="assets/style.css">';
		echo '<link rel="stylesheet" href="assets/cmd-k.css">';
	}
	?>
	<?php if ( function_exists( 'wp_app_head' ) ) wp_app_head(); ?>
</head>
<body class="wp-app-body">
	<?php if ( function_exists( 'wp_app_body_open' ) ) wp_app_body_open(); ?>
	<?php $crm->render_cmd_k_panel(); ?>

	<div class="container">
		<div class="person-header">
			<div>
				<h1 class="person-title">
					<?php echo esc_html( $person_data->get_display_name_with_nickname() ); ?>
					<?php if ( ! empty( $person_data->deceased ) ) : ?>
						<span style="color: #666; font-weight: normal; margin-left: 8px;">†</span>
					<?php endif; ?>
					<span class="person-subtitle">
						@<?php echo esc_html( $person_data->get_username() ); ?>
						<?php if ( ! empty( $person_data->role ) ) : ?>
							• <?php echo esc_html( $person_data->role ); ?>
						<?php endif; ?>
						<?php if ( $is_consultant ) : ?>
							• Consultant
						<?php elseif ( $is_alumni ) : ?>
							• Alumni
						<?php elseif ( ! empty( $person_data->deceased ) ) : ?>
							• Deceased
						<?php endif; ?>
					</span>
				</h1>
				<div class="back-nav">
					<a href="<?php echo $crm->build_url( 'index.php', $privacy_mode ? array( 'privacy' => '1' ) : array() ); ?>">← Back to <?php echo $team_data['team_name'], ' ', ucfirst( $group ); ?> Overview</a>
				</div>
			</div>

			<?php if ( $is_team_member && ! ( isset( $team_data['not_managing_team'] ) && $team_data['not_managing_team'] ) ) : ?>
				<div class="person-tabs">
					<a href="<?php echo $crm->build_url( 'person.php', array( 'person' => $person, 'privacy' => $privacy_mode ? '1' : '0' ) ); ?>"
					   class="tab-link active">👤 Member Overview</a>
						<?php do_action( 'personal_crm_person_tabs', $person_data ); ?>
				</div>
			<?php endif; ?>
		</div>

		<?php
		// Get upcoming events and personal details
		$kids_with_ages = $person_data->get_kids_ages();
		?>

		<div class="overview-layout">
			<div class="people-section">
				<?php if ( ! empty( $person_data->birthday ) || ! empty( $person_data->company_anniversary ) || ! empty( $kids_with_ages ) || ! empty( $person_data->notes ) || ! empty( $person_data->location ) || ! empty( $person_data->partner ) || ! empty( $person_data->partner_birthday ) ) : ?>
					<div class="section section-with-avatar">
						<?php $gravatar_url = $person_data->get_gravatar_url( 100 ); ?>
						<?php if ( $gravatar_url ) : ?>
							<div class="person-avatar-section">
								<?php if ( $privacy_mode ) : ?>
									<img src="<?php echo esc_url( $gravatar_url ); ?>"
										 alt="<?php echo esc_attr( $person_data->get_display_name_with_nickname() ); ?>"
										 class="gravatar-large privacy-blur"
										 width="100"
										 height="100">
								<?php else : ?>
									<img src="<?php echo esc_url( $gravatar_url ); ?>"
										 alt="<?php echo esc_attr( $person_data->get_display_name_with_nickname() ); ?>"
										 class="gravatar-large"
										 width="100"
										 height="100">
								<?php endif; ?>
								<?php if ( ! empty( $person_data->role ) ) : ?>
									<div class="person-name-badge"><?php echo esc_html( $person_data->get_display_name_with_nickname() ); ?></div>
								<?php endif; ?>
							</div>
						<?php endif; ?>
						<h2>Personal Details</h2>

						<?php if ( ! empty( $person_data->role ) ) : ?>
							<p><strong>💼 Role:</strong> <?php echo esc_html( $person_data->role ); ?></p>
						<?php endif; ?>

						<?php if ( ! empty( $person_data->birthday ) ) : ?>
							<?php
							// Calculate age from birthday - different logic for deceased people
							$age_display = '';
							$is_deceased = ! empty( $person_data->deceased );
							$deceased_date = null;
							
							if ( $is_deceased && ! empty( $person_data->deceased_date ) ) {
								$deceased_date = \DateTime::createFromFormat( 'Y-m-d', $person_data->deceased_date );
							}
							
							if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $person_data->birthday ) ) {
								$birth_date = \DateTime::createFromFormat( 'Y-m-d', $person_data->birthday );
								if ( $birth_date ) {
									if ( $is_deceased && $deceased_date ) {
										// For deceased people, show birth-death dates with age at death
										$age_at_death = $deceased_date->diff( $birth_date )->y;
										if ( $privacy_mode ) {
											$age_display = $birth_date->format( 'F Y' ) . ' - ' . $deceased_date->format( 'F Y' ) . ' (aged ' . $age_at_death . ')';
										} else {
											$age_display = $birth_date->format( 'F j, Y' ) . ' - ' . $deceased_date->format( 'F j, Y' ) . ' (aged ' . $age_at_death . ')';
										}
									} else {
										// For living people, calculate current age
										$current_date = new \DateTime();
										$age = $current_date->diff( $birth_date )->y;
										if ( $privacy_mode ) {
											$age_display = $birth_date->format( 'F' );
										} else {
											$age_display = $age . ' (born ' . $birth_date->format( 'F j, Y' ) . ')';
										}
									}
								}
							} elseif ( preg_match( '/^\d{2}-\d{2}$/', $person_data->birthday ) ) {
								// Legacy MM-DD format - limited calculation
								if ( $privacy_mode ) {
									$display_date = \DateTime::createFromFormat( 'm-d', $person_data->birthday );
									if ( $display_date ) {
										$age_display = $display_date->format( 'F' );
									}
								} else {
									$display_date = \DateTime::createFromFormat( 'm-d', $person_data->birthday );
									if ( $display_date ) {
										if ( $is_deceased && $deceased_date ) {
											$age_display = 'Birthday ' . $display_date->format( 'F j' ) . ' - passed away ' . $deceased_date->format( 'F j, Y' );
										} else {
											$age_display = 'Birthday ' . $display_date->format( 'F j' );
										}
									}
								}
							}
							?>
							<?php if ( $age_display ) : ?>
								<p><strong>🎂 <?php echo $privacy_mode ? 'Birthday:' : ($is_deceased ? 'Life span:' : 'Age:'); ?></strong> <?php echo esc_html( $age_display ); ?></p>
							<?php endif; ?>
						<?php endif; ?>


						<?php if ( ! empty( $person_data->company_anniversary ) && ! ( $is_alumni && ! empty( $person_data->left_company ) ) ) : ?>
							<?php
							$anniversary_date = \DateTime::createFromFormat( 'Y-m-d', $person_data->company_anniversary );
							if ( $anniversary_date ) {
								$current_date = new \DateTime();
								$years_at_company = $current_date->diff( $anniversary_date )->y;
								
								if ( $privacy_mode ) {
									echo '<p><strong>🏢 Years at Company:</strong> ' . $years_at_company . ' years</p>';
								} else {
									if ( $years_at_company == 0 ) {
										// First year - show start date
										echo '<p><strong>🏢 Years at Company:</strong> Started ' . esc_html( $anniversary_date->format( 'F j, Y' ) ) . ' (less than 1 year)</p>';
									} else {
										// Multiple years - show time at company and start date
										echo '<p><strong>🏢 Years at Company:</strong> ' . $years_at_company . ' years (started ' . esc_html( $anniversary_date->format( 'F j, Y' ) ) . ')</p>';
									}
								}
							}
							?>
						<?php endif; ?>

						<?php if ( $is_alumni && ! empty( $person_data->left_company ) ) : ?>
							<div class="alumni-company-info">
								<?php if ( ! empty( $person_data->new_company ) ) : ?>
									<p>
										<strong>🆕 New Company:</strong>
										<?php if ( ! empty( $person_data->new_company_website ) ) : ?>
											<a href="<?php echo esc_url( $person_data->new_company_website ); ?>" target="_blank" class="company-link">
												<?php echo esc_html( $person_data->new_company ); ?>
											</a>
										<?php else : ?>
											<?php echo esc_html( $person_data->new_company ); ?>
										<?php endif; ?>
									</p>
								<?php else : ?>
									<p><strong>🏢 Company Status:</strong> Has left the company</p>
								<?php endif; ?>
							</div>
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
								<a href="https://maps.google.com/maps?q=<?php echo urlencode( $privacy_mode ? $display_location : $person_data->location ); ?>" target="_blank" class="location-link"><?php echo esc_html( $display_location ); ?></a>
								<?php if ( empty( $person_data->deceased ) ) : ?>
									<span id="time-<?php echo esc_attr( $person ); ?>" class="timezone-display"></span>
								<?php endif; ?>
							</p>
						<?php endif; ?>

						<?php if ( ! empty( $person_data->partner ) && ! $privacy_mode ) : ?>
							<p><strong>💑 Partner:</strong> <?php echo esc_html( $person_data->partner ); ?>
							<?php if ( ! empty( $person_data->partner_birthday ) ) : ?>
								<?php
								$partner_age_display = '';
								if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $person_data->partner_birthday ) ) {
									// Full date format - can calculate age
									$birth_date = \DateTime::createFromFormat( 'Y-m-d', $person_data->partner_birthday );
									if ( $birth_date ) {
										$current_date = new \DateTime();
										$age = $current_date->diff( $birth_date )->y;
										$partner_age_display = $age . ' (born ' . $birth_date->format( 'F j, Y' ) . ')';
									}
								} elseif ( preg_match( '/^\d{2}-\d{2}$/', $person_data->partner_birthday ) ) {
									// Year-unknown format - can't calculate exact age
									$display_date = \DateTime::createFromFormat( 'm-d', $person_data->partner_birthday );
									if ( $display_date ) {
										$partner_age_display = 'Birthday ' . $display_date->format( 'F j' );
									}
								}
								?>
								<?php if ( $partner_age_display ) : ?>
									(<?php echo $partner_age_display; ?>)
								<?php endif; ?>
							<?php endif; ?>
							</p>
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
										<span class="child-badge" title="<?php echo esc_attr( $tooltip ); ?>">
											<?php echo esc_html( $kid['name'] ); ?>
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
					$has_website = ! empty( $person_data->website );
					$has_linear = ! empty( $person_data->links['Linear'] ?? '' );
					$has_repos = ! empty( $person_data->github_repos );
					$has_any_accounts = $has_github || $has_wordpress || $has_linkedin || $has_website || $has_linear;
					
					// Check if person has notes (needed for Quick Links)
					$has_notes = ! empty( $person_data->notes ) && is_array( $person_data->notes ) && count( $person_data->notes ) > 0;
					?>

					<?php if ( $has_repos ) : ?>
						<div class="section">
							<h2>GitHub Repositories</h2>
							<?php
							$repos = is_array( $person_data->github_repos ) ? $person_data->github_repos : array_filter( array_map( 'trim', explode( ',', $person_data->github_repos ) ) );
							if ( ! empty( $repos ) ) :
							?>
								<div class="github-repo-grid">
									<?php if ( $privacy_mode ) : ?>
										<?php for ( $i = 0; $i < count( $repos ); $i++ ) : ?>
											<div class="github-repo-card">
												<span class="github-repo-link privacy-hidden">
													📦 [Repository <?php echo $i + 1; ?>]
												</span>
											</div>
										<?php endfor; ?>
									<?php else : ?>
										<?php foreach ( $repos as $repo ) : ?>
											<div class="github-repo-card">
												<a href="https://github.com/<?php echo esc_attr( $repo ); ?>" target="_blank" class="github-repo-link">
													📦 <?php echo esc_html( $repo ); ?>
												</a>
												<?php if ( $has_github ) : ?>
													<a href="https://github.com/<?php echo esc_attr( $repo ); ?>/pulls/<?php echo esc_attr( $person_data->github ); ?>" target="_blank" class="github-pr-link">PRs</a>
												<?php endif; ?>
											</div>
										<?php endforeach; ?>
									<?php endif; ?>
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
					$has_activity_links = $is_team_member && ! empty( $person_data->username ) && isset( $team_data['activity_url_prefix'] ) && ! $crm->is_social_group( $current_team );
					$has_add_note_link = ! $privacy_mode && ! $has_notes;
					?>

					<?php if ( $has_other_links || $has_activity_links || $has_add_note_link ) : ?>
						<div class="section">
							<h2>Quick Links</h2>

						<div class="quick-links-container">
							<?php if ( $has_other_links ) : ?>
									<?php foreach ( $filtered_links as $link_text => $link_url ) : ?>
										<?php if ( ! empty( $link_url ) ) : ?>
											<a href="<?php echo esc_url( str_replace( '$username', $person, $link_url ) ); ?>" target="_blank" class="quick-link">
												<?php echo $crm->get_link_icon( $link_text, $link_url, 18); ?>
												<?php echo esc_html( $link_text ); ?>
											</a>
										<?php endif; ?>
									<?php endforeach; ?>
							<?php endif; ?>

							<?php if ( ! $privacy_mode && ! $has_notes ) : ?>
								<a href="#" onclick="toggleAddNoteForm(); return false;" class="quick-link">
									<span style="width: 18px; height: 18px; display: inline-flex; align-items: center; justify-content: center; margin-right: 8px; font-size: 14px;">📝</span>
									Add note
								</a>
							<?php endif; ?>

							<?php if ( $is_team_member && ! empty( $person_data->username ) && isset( $team_data['activity_url_prefix'] ) && $group !== 'group' ) : ?>
								<?php
								$last_month = date( 'Y-m', strtotime( 'last month') );
								$start_date = $last_month . '-01';
								$end_date = date( 'Y-m-d', strtotime( $start_date ) );
								$activity_url_month = $team_data['activity_url_prefix'] . '&member=' . urlencode( $person_data->username ) . "&start={$start_date}&end={$end_date}";
								$activity_url_week = $team_data['activity_url_prefix'] . '&member=' . urlencode( $person_data->username );
								?>
									<a href="<?php echo esc_url( $activity_url_month ); ?>" target="_blank" class="activity-link-month">
										📊 Activity (Month)
									</a>
									<a href="<?php echo esc_url( $activity_url_week ); ?>" target="_blank" class="activity-link-week">
										📊 Activity (Week)
									</a>
							<?php endif; ?>
							</div>
						</div>
					<?php endif; ?>

					<?php if ( ! $privacy_mode ) : ?>
						<?php 
						$view_mode = $_GET['notes_view'] ?? 'compiled'; 
						?>
						<?php if ( $has_notes ) : ?>
							<div class="notes-section">
								<div class="notes-header">
									<strong>📝 Notes:</strong>
									<div class="notes-controls">
										<?php if ( $view_mode === 'chronological' ) : ?>
											<a href="<?php echo $crm->build_url( 'person.php', array( 'person' => $person, 'notes_view' => 'compiled', 'privacy' => $privacy_mode ? '1' : '0' ) ); ?>"
											   class="timeline-toggle">← Compiled view</a>
										<?php else : ?>
											<a href="<?php echo $crm->build_url( 'person.php', array( 'person' => $person, 'notes_view' => 'chronological', 'privacy' => $privacy_mode ? '1' : '0' ) ); ?>"
											   class="timeline-toggle">Timeline view →</a>
										<?php endif; ?>
										<button type="button" onclick="toggleAddNoteForm()" class="add-note-btn">Add note</button>
									</div>
								</div>
								
								<?php if ( $view_mode === 'chronological' ) : ?>
									<div class="notes-chronological">
										<?php foreach ( $person_data->notes as $note_index => $note ) : ?>
											<div class="note">
												<div class="note-header">
													<small class="note-date"><?php echo esc_html( $note['date'] ); ?></small>
													<button type="button" onclick="toggleEditNote(<?php echo $note_index; ?>)" class="edit-note-btn">✏️ Edit</button>
												</div>
												<div class="note-display" id="note-display-<?php echo $note_index; ?>">
													<p class="note-text"><?php echo nl2br( esc_html( $note['text'] ) ); ?></p>
												</div>
												<form method="post" action="<?php echo $crm->build_url( 'admin.php' ); ?>" class="edit-note-form" id="edit-note-form-<?php echo $note_index; ?>" style="display: none;">
													<input type="hidden" name="action" value="edit_note">
													<input type="hidden" name="username" value="<?php echo esc_attr( $person ); ?>">
													<input type="hidden" name="note_index" value="<?php echo $note_index; ?>">
													<input type="hidden" name="return_to_person" value="1">
													<?php if ( $privacy_mode ) : ?>
														<input type="hidden" name="privacy" value="1">
													<?php endif; ?>
													<?php if ( isset( $_GET['notes_view'] ) ) : ?>
														<input type="hidden" name="notes_view" value="<?php echo esc_attr( $_GET['notes_view'] ); ?>">
													<?php endif; ?>
													<textarea name="edit_note_text" rows="3" required><?php echo esc_textarea( $note['text'] ); ?></textarea>
													<div class="form-actions">
														<button type="submit">Save Changes</button>
														<button type="button" onclick="toggleEditNote(<?php echo $note_index; ?>)" class="cancel-btn">Cancel</button>
													</div>
												</form>
											</div>
										<?php endforeach; ?>
									</div>
								<?php else : ?>
									<div class="notes-compiled">
										<?php 
										$compiled_text = '';
										foreach ( $person_data->notes as $note ) {
											$compiled_text .= $note['text'] . "\n\n";
										}
										?>
										<p class="notes-content"><?php echo nl2br( esc_html( trim( $compiled_text ) ) ); ?></p>
									</div>
								<?php endif; ?>
								
								<form method="post" action="<?php echo $crm->build_url( 'admin.php' ); ?>" class="add-note-form" id="add-note-form" style="display: none;">
									<input type="hidden" name="action" value="add_note">
									<input type="hidden" name="username" value="<?php echo esc_attr( $person ); ?>">
									<input type="hidden" name="return_to_person" value="1">
									<?php if ( $privacy_mode ) : ?>
										<input type="hidden" name="privacy" value="1">
									<?php endif; ?>
									<?php if ( isset( $_GET['notes_view'] ) ) : ?>
										<input type="hidden" name="notes_view" value="<?php echo esc_attr( $_GET['notes_view'] ); ?>">
									<?php endif; ?>
									<textarea name="new_note" placeholder="Add a new note..." rows="3" required></textarea>
									<div class="form-actions">
										<button type="submit">Save Note</button>
										<button type="button" onclick="toggleAddNoteForm()" class="cancel-btn">Cancel</button>
									</div>
								</form>
							</div>
						<?php endif; ?>
						
						<?php if ( ! $privacy_mode && ! $has_notes ) : ?>
							<!-- Hidden form for adding notes when no notes exist -->
							<form method="post" action="<?php echo $crm->build_url( 'admin.php' ); ?>" class="add-note-form" id="add-note-form" style="display: none;">
								<input type="hidden" name="action" value="add_note">
								<input type="hidden" name="username" value="<?php echo esc_attr( $person ); ?>">
								<input type="hidden" name="return_to_person" value="1">
								<?php if ( $privacy_mode ) : ?>
									<input type="hidden" name="privacy" value="1">
								<?php endif; ?>
								<div class="notes-section">
									<div class="notes-header">
										<strong>📝 Add your first note:</strong>
									</div>
									<textarea name="new_note" placeholder="Add a new note..." rows="3" required></textarea>
									<div class="form-actions">
										<button type="submit">Save Note</button>
										<button type="button" onclick="toggleAddNoteForm()" class="cancel-btn">Cancel</button>
									</div>
								</div>
							</form>
						<?php endif; ?>
					<?php endif; ?>

					<?php if ( $has_any_accounts ) : ?>
						<div class="section">
							<h2>Online Profiles</h2>
							<div class="external-account-links">
								<?php if ( $has_github ) : ?>
									<a href="https://github.com/<?php echo esc_attr( $person_data->github ); ?>" target="_blank" class="external-link github">
										<?php echo $crm->get_link_icon('GitHub', 'https://github.com/' . $person_data->github, 16); ?>
										GitHub
									</a>
								<?php endif; ?>

								<?php if ( $has_linkedin ) : ?>
									<a href="https://linkedin.com/in/<?php echo esc_attr( $person_data->linkedin ); ?>" target="_blank" class="external-link linkedin">
										<?php echo $crm->get_link_icon('LinkedIn', 'https://linkedin.com/in/' . $person_data->linkedin, 16); ?>
										LinkedIn
									</a>
								<?php endif; ?>

								<?php if ( $has_website ) : ?>
									<a href="<?php echo esc_url( $person_data->website ); ?>" target="_blank" class="external-link website">
										<?php echo $crm->get_link_icon('Website', $person_data->website, 16); ?>
										Website
									</a>
								<?php endif; ?>


								<?php if ( $has_wordpress ) : ?>
									<a href="https://profiles.wordpress.org/<?php echo esc_attr( $person_data->wordpress ); ?>" target="_blank" class="external-link wordpress">
										<?php echo $crm->get_link_icon('WordPress.org', 'https://profiles.wordpress.org/' . $person_data->wordpress, 16); ?>
										WordPress.org
									</a>
								<?php endif; ?>

								<?php if ( $has_linear ) : ?>
									<a href="<?php echo esc_url( $person_data->links['Linear'] ); ?>" target="_blank" class="external-link linear">
										<?php echo $crm->get_link_icon('Linear', $person_data->links['Linear'], 16); ?>
										Linear
									</a>
								<?php endif; ?>
							</div>
						</div>
					<?php endif; ?>

					<?php endif; ?>
			</div>

			<div class="events-sidebar">
				<a href="<?php echo $crm->build_url( 'events.php', array( 'privacy' => $privacy_mode ? '1' : '0' ) ); ?>" class="sidebar-section-link">
					<h3 class="sidebar-section-heading">🗓️ Upcoming Events</h3>
				</a>
				<?php
				$crm->render_upcoming_events_sidebar( $team_data, 365, $person, false );
				?>

				<?php
				// HR plugin can hook into this action to provide feedback history
				do_action( 'personal_crm_person_sidebar', $person_data, $is_team_member );
				?>
				</div>
			</div>
		</div>

		<footer class="privacy-footer">
			<?php if ( $privacy_mode ) : ?>
				<a href="?<?php echo http_build_query( array_merge( $_GET, array( 'privacy' => '0' ) ) ); ?>">🔒 Privacy Mode ON</a>
			<?php else : ?>
				<a href="?<?php echo http_build_query( array_merge( $_GET, array( 'privacy' => '1' ) ) ); ?>">🔓 Privacy Mode OFF</a>
			<?php endif; ?>
			<a href="<?php echo $crm->build_url( 'admin.php' ); ?>">⚙️ Admin Panel</a>
			<a href="/crm/admin/<?php echo $current_team; ?>/person/<?php echo $person; ?>/">✏️ Edit Person</a>
		</footer>
	</div>

	<?php
	if ( function_exists( 'wp_app_enqueue_script' ) ) {
		wp_app_enqueue_script( 'a8c-hr-cmd-k-js', plugin_dir_url( __FILE__ ) . 'assets/cmd-k.js' );
		wp_app_enqueue_script( 'a8c-hr-script-js', plugin_dir_url( __FILE__ ) . 'assets/script.js' );
	} else {
		echo '<script src="assets/cmd-k.js"></script>';
		echo '<script src="assets/script.js"></script>';
	}
	?>
	<?php $crm->init_cmd_k_js( $privacy_mode ); ?>
	<script>
		document.addEventListener('DOMContentLoaded', () => {
			<?php if ( ! empty( $person_data ) && ( ! empty( $person_data->location ) || ! empty( $person_data->timezone ) ) && empty( $person_data->deceased ) ) : ?>
			createTimeUpdater('<?php echo addslashes( $person_data->timezone ); ?>', '<?php echo addslashes( $person ); ?>', {
				verboseDifference: true
			});
			<?php endif; ?>

			// Keyboard shortcut: Press 'e' to edit person
			document.addEventListener('keydown', (event) => {
				// Only trigger if not typing in an input field
				if (event.target.tagName.toLowerCase() !== 'input' && 
					event.target.tagName.toLowerCase() !== 'textarea' && 
					!event.target.isContentEditable) {
					
					if (event.key.toLowerCase() === 'e') {
						event.preventDefault();
						window.location.href = '<?php echo addslashes( $person_data->get_edit_url() ); ?>';
					}
				}
			});
		});
		
		// Toggle add note form
		function toggleAddNoteForm() {
			const form = document.getElementById('add-note-form');
			const isVisible = form.style.display !== 'none';
			
			if (isVisible) {
				form.style.display = 'none';
				form.querySelector('textarea').value = ''; // Clear form when hiding
			} else {
				form.style.display = 'block';
				form.querySelector('textarea').focus(); // Focus textarea when showing
			}
		}

		// Toggle edit note form
		function toggleEditNote(noteIndex) {
			const display = document.getElementById('note-display-' + noteIndex);
			const form = document.getElementById('edit-note-form-' + noteIndex);
			const isEditing = form.style.display !== 'none';

			if (isEditing) {
				form.style.display = 'none';
				display.style.display = 'block';
			} else {
				form.style.display = 'block';
				display.style.display = 'none';
				form.querySelector('textarea').focus(); // Focus textarea when showing
			}
		}
	</script>
</body>
</html>