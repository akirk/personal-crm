<?php
/**
 * Individual Person View
 * 
 * Displays detailed information for a specific team member, leader, or alumni
 */

namespace PersonalCRM;

require_once __DIR__ . '/personal-crm.php';

$crm = PersonalCrm::get_instance();
extract( PersonalCrm::get_globals() );

$person = $_GET['person'] ?? null;
$back_group = $_GET['back'] ?? null;

if ( empty( $person ) && function_exists( 'get_query_var' ) ) {
	$person = get_query_var( 'person' );
}
if ( empty( $back_group ) && function_exists( 'get_query_var' ) ) {
	$back_group = get_query_var( 'back' );
}

if ( empty( $person ) ) {
	header( 'Location: ' . $crm->build_url( 'group.php' ) );
	exit;
}

$person_data = $crm->storage->get_person( $person );

if ( ! $person_data ) {
	header( 'Location: ' . $crm->build_url( 'group.php' ) );
	exit;
}

$current_group = null;
$group_data = null;
if ( ! empty( $back_group ) && $crm->storage->group_exists( $back_group ) ) {
	$current_group = $back_group;
	$group_data = $crm->storage->get_group( $current_group );
	$person_data->team = $current_group;
}

// Determine person's role based on category (only set if coming from a specific group)
$is_team_member = ! empty( $person_data->category ) && $person_data->category === 'members';
$is_consultant = ! empty( $person_data->category ) && stripos( $person_data->category, 'consultant' ) !== false;
$is_alumni = ! empty( $person_data->category ) && stripos( $person_data->category, 'alumni' ) !== false;

?>
<!DOCTYPE html>
<html <?php echo function_exists( 'wp_app_language_attributes' ) ? wp_app_language_attributes() : 'lang="en"'; ?>>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="color-scheme" content="light dark">
	<title><?php
	$page_title = esc_html( $person_data->get_display_name_with_nickname() );
	if ( $current_group ) {
		$page_title .= ' - ' . esc_html( $crm->get_group_display_title( $current_group ) );
	}
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
						<?php if ( ! $is_team_member && ! empty( $person_data->category_group ) ) : ?>
							• <?php echo esc_html( $person_data->category_group ); ?>
						<?php elseif ( ! empty( $person_data->deceased ) ) : ?>
							• Deceased
						<?php endif; ?>
					</span>
				</h1>
				<?php if ( $current_group && $group_data ) : ?>
					<div class="back-nav">
						<a href="<?php echo $crm->build_url( 'group.php', array( 'group' => $current_group ) ); ?>">← Back to <?php echo htmlspecialchars( $group_data->group_name ); ?> Overview</a>
					</div>
				<?php elseif ( ! empty( $person_data->groups ) ) : ?>
					<?php
					// If person belongs to exactly one current group and no back group specified, show link to that group
					$current_groups_only = array_filter( $person_data->groups, function( $group ) {
						return empty( $group['group_left_date'] );
					} );

					if ( count( $current_groups_only ) === 1 ) {
						$single_group = reset( $current_groups_only );
						$single_group_data = $crm->storage->get_group_by_id( $single_group['id'] );
						if ( $single_group_data ) :
						?>
							<div class="back-nav">
								<a href="<?php echo $crm->build_url( 'group.php', array( 'group' => $single_group_data->slug ) ); ?>">← Back to <?php echo htmlspecialchars( $single_group['group_name'] ); ?> Overview</a>
							</div>
						<?php
						endif;
					}
					?>
				<?php endif; ?>
			</div>

			<?php do_action( 'personal_crm_person_header_tabs', $person_data, $is_team_member, $current_group, $group_data ); ?>
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
								<img src="<?php echo esc_url( $gravatar_url ); ?>"
									 alt="<?php echo esc_attr( $person_data->get_display_name_with_nickname() ); ?>"
									 class="gravatar-large"
									 width="100"
									 height="100">
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
								$deceased_date = DateTime::createFromFormat( 'Y-m-d', $person_data->deceased_date );
							}
							
							if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $person_data->birthday ) ) {
								$birth_date = DateTime::createFromFormat( 'Y-m-d', $person_data->birthday );
								if ( $birth_date ) {
									if ( $is_deceased && $deceased_date ) {
										// For deceased people, show birth-death dates with age at death
										$age_at_death = $deceased_date->diff( $birth_date )->y;
										$age_display = $birth_date->format( 'F j, Y' ) . ' - ' . $deceased_date->format( 'F j, Y' ) . ' (aged ' . $age_at_death . ')';
									} else {
										// For living people, calculate current age
										$current_date = new DateTime();
										$age = $current_date->diff( $birth_date )->y;
										$age_display = $age . ' (born ' . $birth_date->format( 'F j, Y' ) . ')';
									}
								}
							} elseif ( preg_match( '/^\d{2}-\d{2}$/', $person_data->birthday ) ) {
								// Legacy MM-DD format - limited calculation
								$display_date = DateTime::createFromFormat( 'm-d', $person_data->birthday );
								if ( $display_date ) {
									if ( $is_deceased && $deceased_date ) {
										$age_display = 'Birthday ' . $display_date->format( 'F j' ) . ' - passed away ' . $deceased_date->format( 'F j, Y' );
									} else {
										$age_display = 'Birthday ' . $display_date->format( 'F j' );
									}
								}
							}
							?>
							<?php if ( $age_display ) : ?>
								<p><strong>🎂 <?php echo $is_deceased ? 'Life span:' : 'Age:'; ?></strong> <?php echo esc_html( $age_display ); ?></p>
							<?php endif; ?>
						<?php endif; ?>


						<?php if ( ! empty( $person_data->company_anniversary ) && ! ( $is_alumni && ! empty( $person_data->left_company ) ) ) : ?>
							<?php
							$anniversary_date = DateTime::createFromFormat( 'Y-m-d', $person_data->company_anniversary );
							if ( $anniversary_date ) {
								$current_date = new DateTime();
								$years_at_company = $current_date->diff( $anniversary_date )->y;

								if ( $years_at_company == 0 ) {
									// First year - show start date
									echo '<p><strong>🏢 Years at Company:</strong> Started ' . esc_html( $anniversary_date->format( 'F j, Y' ) ) . ' (less than 1 year)</p>';
								} else {
									// Multiple years - show time at company and start date
									echo '<p><strong>🏢 Years at Company:</strong> ' . $years_at_company . ' years (started ' . esc_html( $anniversary_date->format( 'F j, Y' ) ) . ')</p>';
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
								<a href="https://maps.google.com/maps?q=<?php echo urlencode( $person_data->location ); ?>" target="_blank" class="location-link"><?php echo esc_html( $person_data->location ); ?></a>
								<?php if ( empty( $person_data->deceased ) ) : ?>
									<span id="time-<?php echo esc_attr( $person ); ?>" class="timezone-display"></span>
								<?php endif; ?>
							</p>
						<?php endif; ?>

						<?php if ( ! empty( $person_data->partner ) ) : ?>
							<p><strong>💑 Partner:</strong> <?php echo esc_html( $person_data->partner ); ?>
							<?php if ( ! empty( $person_data->partner_birthday ) ) : ?>
								<?php
								$partner_age_display = '';
								if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $person_data->partner_birthday ) ) {
									// Full date format - can calculate age
									$birth_date = DateTime::createFromFormat( 'Y-m-d', $person_data->partner_birthday );
									if ( $birth_date ) {
										$current_date = new DateTime();
										$age = $current_date->diff( $birth_date )->y;
										$partner_age_display = $age . ' (born ' . $birth_date->format( 'F j, Y' ) . ')';
									}
								} elseif ( preg_match( '/^\d{2}-\d{2}$/', $person_data->partner_birthday ) ) {
									// Year-unknown format - can't calculate exact age
									$display_date = DateTime::createFromFormat( 'm-d', $person_data->partner_birthday );
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
							</p>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $person_data->groups ) && is_array( $person_data->groups ) ) : ?>
					<?php
					// Separate current and historical memberships
					$current_groups = array();
					$historical_groups = array();
					foreach ( $person_data->groups as $group ) {
						if ( ! empty( $group['group_left_date'] ) ) {
							$historical_groups[] = $group;
						} else {
							$current_groups[] = $group;
						}
					}
					?>
					<div class="section">
						<h2>Groups</h2>
						<div class="teams-list">
							<?php foreach ( $current_groups as $group ) : ?>
								<?php
								// Build hierarchical name if this is a child group
								$display_name = $group['group_name'];
								if ( ! empty( $group['parent_id'] ) ) {
									$parent = $crm->storage->get_group_by_id( $group['parent_id'] );
									if ( $parent ) {
										$display_name = $parent->group_name . ' → ' . $group['group_name'];
									}
								}
								?>
								<a href="<?php echo esc_url( $crm->build_url( 'group.php', array( 'group' => $group['slug'] ) ) ); ?>" class="group-badge">
									<?php echo htmlspecialchars( $display_name ); ?>
									<?php if ( ! empty( $group['group_joined_date'] ) ) : ?>
										<?php $tenure = $crm->format_tenure( $group['group_joined_date'] ); ?>
										<?php if ( $tenure ) : ?>
											<small style="font-weight: normal; opacity: 0.8;"> · <?php echo htmlspecialchars( $tenure ); ?></small>
										<?php endif; ?>
									<?php endif; ?>
								</a>
							<?php endforeach; ?>
							<?php foreach ( $historical_groups as $group ) : ?>
								<?php
								// Build hierarchical name if this is a child group
								$display_name = $group['group_name'];
								if ( ! empty( $group['parent_id'] ) ) {
									$parent = $crm->storage->get_group_by_id( $group['parent_id'] );
									if ( $parent ) {
										$display_name = $parent->group_name . ' → ' . $group['group_name'];
									}
								}

								$start_year = ! empty( $group['group_joined_date'] ) ? date( 'Y', strtotime( $group['group_joined_date'] ) ) : '?';
								$end_year = ! empty( $group['group_left_date'] ) ? date( 'Y', strtotime( $group['group_left_date'] ) ) : '?';
								$date_range = $start_year === $end_year ? $start_year : $start_year . '–' . $end_year;

								// Build tooltip: joindate - leavedate (duration)
								$tooltip = '';
								if ( ! empty( $group['group_joined_date'] ) && ! empty( $group['group_left_date'] ) ) {
									$join_date = date( 'M j, Y', strtotime( $group['group_joined_date'] ) );
									$leave_date = date( 'M j, Y', strtotime( $group['group_left_date'] ) );

									$start = new DateTime( $group['group_joined_date'] );
									$end = new DateTime( $group['group_left_date'] );
									$interval = $start->diff( $end );
									$duration_parts = array();
									if ( $interval->y > 0 ) {
										$duration_parts[] = $interval->y . ( $interval->y === 1 ? ' year' : ' years' );
									}
									if ( $interval->m > 0 ) {
										$duration_parts[] = $interval->m . ( $interval->m === 1 ? ' month' : ' months' );
									}
									$duration = ! empty( $duration_parts ) ? implode( ', ', $duration_parts ) : '0 months';

									$tooltip = $join_date . ' - ' . $leave_date . ' (' . $duration . ')';
								}
								?>
								<a href="<?php echo esc_url( $crm->build_url( 'group.php', array( 'group' => $group['slug'] ) ) ); ?>" class="group-badge group-badge-historical" title="<?php echo htmlspecialchars( $tooltip ); ?>">
									<?php echo htmlspecialchars( $display_name ); ?>
									<small style="font-weight: normal; opacity: 0.8;"> · <?php echo htmlspecialchars( $date_range ); ?></small>
								</a>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $person_data->birthday ) || ! empty( $person_data->company_anniversary ) || ! empty( $kids_with_ages ) || ! empty( $person_data->notes ) || ! empty( $person_data->location ) || ! empty( $person_data->partner ) || ! empty( $person_data->partner_birthday ) ) : ?>
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
					$has_activity_links = $is_team_member && ! empty( $person_data->username ) && ! empty( $group_data->activity_url_prefix ) && ! $crm->is_social_group( $current_group );
					$has_add_note_link = ! $has_notes;
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

							<a href="#" onclick="toggleAddNoteForm(); return false;" class="quick-link">
								<span style="width: 18px; height: 18px; display: inline-flex; align-items: center; justify-content: center; margin-right: 8px; font-size: 14px;">📝</span>
								Add note
							</a>

							<?php
							// Allow plugins to add quick links
							do_action( 'personal_crm_person_quick_links', $person_data, $is_team_member, $group_data );
							?>
						</div>
						</div>
					<?php endif; ?>

					
						<?php 
						$view_mode = $_GET['notes_view'] ?? 'compiled'; 
						?>
						<?php if ( $has_notes ) : ?>
							<div class="notes-section">
								<div class="notes-header">
									<strong>📝 Notes:</strong>
									<div class="notes-controls">
										<?php if ( $view_mode === 'chronological' ) : ?>
											<a href="<?php echo $crm->build_url( 'person.php', array( 'person' => $person, 'notes_view' => 'compiled' ) ); ?>"
											   class="timeline-toggle">← Compiled view</a>
										<?php else : ?>
											<a href="<?php echo $crm->build_url( 'person.php', array( 'person' => $person, 'notes_view' => 'chronological' ) ); ?>"
											   class="timeline-toggle">Timeline view →</a>
										<?php endif; ?>
										<button type="button" onclick="toggleAddNoteForm()" class="add-note-btn">Add note</button>
									</div>
								</div>
								
								<?php if ( $view_mode === 'chronological' ) : ?>
									<div class="notes-chronological">
										<?php foreach ( $person_data->notes as $note ) : ?>
										<?php $note_id = $note['id']; ?>
											<div class="note">
												<div class="note-header">
													<small class="note-date"><?php echo esc_html( $note['date'] ); ?></small>
													<div class="note-actions">
														<button type="button" onclick="toggleEditNote(<?php echo $note_id; ?>)" class="edit-note-btn">✏️ Edit</button>
														<button type="button" onclick="deleteNote(<?php echo $note_id; ?>)" class="delete-note-btn">🗑️ Delete</button>
													</div>
												</div>
												<div class="note-display" id="note-display-<?php echo $note_id; ?>">
													<p class="note-text"><?php echo nl2br( esc_html( $note['text'] ) ); ?></p>
												</div>
												<form method="post" action="<?php echo $crm->build_url( 'admin/person.php', array( 'person' => $person ) ); ?>" class="edit-note-form" id="edit-note-form-<?php echo $note_id; ?>" style="display: none;">
													<input type="hidden" name="action" value="edit_note">
													<input type="hidden" name="username" value="<?php echo esc_attr( $person ); ?>">
													<input type="hidden" name="note_id" value="<?php echo $note_id; ?>">
													<input type="hidden" name="return_to_person" value="1">
													<?php if ( isset( $_GET['notes_view'] ) ) : ?>
														<input type="hidden" name="notes_view" value="<?php echo esc_attr( $_GET['notes_view'] ); ?>">
													<?php endif; ?>
													<textarea name="edit_note_text" rows="3" required><?php echo esc_textarea( $note['text'] ); ?></textarea>
													<div class="form-actions">
														<button type="submit">Save Changes</button>
														<button type="button" onclick="toggleEditNote(<?php echo $note_id; ?>)" class="cancel-btn">Cancel</button>
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
							</div>
						<?php endif; ?>

						<form method="post" action="<?php echo $crm->build_url( 'admin/person.php', array( 'person' => $person ) ); ?>" class="add-note-form" id="add-note-form" style="display: none;">
							<input type="hidden" name="action" value="add_note">
							<input type="hidden" name="username" value="<?php echo esc_attr( $person ); ?>">
							<input type="hidden" name="return_to_person" value="1">
							<?php if ( isset( $_GET['notes_view'] ) ) : ?>
								<input type="hidden" name="notes_view" value="<?php echo esc_attr( $_GET['notes_view'] ); ?>">
							<?php endif; ?>
							<textarea name="new_note" placeholder="Add a new note..." rows="3" required></textarea>
							<div class="form-actions">
								<button type="submit">Save Note</button>
								<button type="button" onclick="toggleAddNoteForm()" class="cancel-btn">Cancel</button>
							</div>
						</form>

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
				<a href="<?php echo $crm->build_url( 'events.php' ); ?>" class="sidebar-section-link">
					<h3 class="sidebar-section-heading">🗓️ Upcoming Events</h3>
				</a>
				<?php
				$crm->render_upcoming_events_sidebar( $group_data, 365, $person, false );
				?>

				<?php
				do_action( 'personal_crm_person_sidebar', $person_data, $is_team_member );
				?>
				</div>
			</div>
		</div>

		<footer class="privacy-footer">
			<a href="#" id="privacy-toggle" onclick="togglePrivacyMode(); return false;">
				<span id="privacy-status">🔓 Privacy Mode OFF</span>
			</a>
			<?php
			// Allow other plugins to add footer links
			if ( $current_group && $group_data ) {
				do_action( 'personal_crm_footer_links', $group_data, $current_group );
			}
			?>
			<a href="<?php echo $crm->build_url( 'admin/index.php', array( 'group' => $current_group ) ); ?>">⚙️ Admin Panel</a>
			<a href="<?php echo $crm->build_url( 'admin/person.php', array( 'person' => $person ) ); ?>" id="edit-person-link">✏️ Edit Person</a>
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
						const editLink = document.getElementById('edit-person-link');
						if (editLink) {
							window.location.href = editLink.href;
						}
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

		// Delete note with confirmation
		function deleteNote(noteIndex) {
			if (confirm('Are you sure you want to delete this note?')) {
				const form = document.createElement('form');
				form.method = 'post';
				form.action = '<?php echo addslashes( $crm->build_url( 'admin/person.php', array( 'person' => $person ) ) ); ?>';

				const fields = {
					'action': 'delete_note',
					'username': '<?php echo addslashes( $person ); ?>',
					'note_id': noteIndex,
					'return_to_person': '1'
				};

				<?php if ( isset( $_GET['notes_view'] ) ) : ?>
				fields.notes_view = '<?php echo addslashes( $_GET['notes_view'] ); ?>';
				<?php endif; ?>

				for (const key in fields) {
					const input = document.createElement('input');
					input.type = 'hidden';
					input.name = key;
					input.value = fields[key];
					form.appendChild(input);
				}

				document.body.appendChild(form);
				form.submit();
			}
		}
	</script>
</body>
</html>