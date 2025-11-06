<?php
/**
 * Group History Timeline
 */
namespace PersonalCRM;

require_once __DIR__ . '/personal-crm.php';

extract( PersonalCrm::get_globals() );

if ( ! $group_data ) {
    header( 'Location: ' . $crm->build_url( 'group.php' ) );
    exit;
}

$timeline = $crm->compile_group_history( $group_data->id );
$current_members = $group_data->get_members();
$current_count = count( $current_members );

?>
<!DOCTYPE html>
<html <?php echo function_exists( '\wp_app_language_attributes' ) ? \wp_app_language_attributes() : 'lang="en"'; ?>>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="color-scheme" content="light dark">
	<title><?php echo function_exists( '\wp_app_title' ) ? \wp_app_title( $crm->get_group_display_title( $current_group, 'History' ) ) : htmlspecialchars( $crm->get_group_display_title( $current_group, 'History' ) ); ?></title>
	<?php
	if ( function_exists( '\wp_app_enqueue_style' ) ) {
		wp_app_enqueue_style( 'a8c-hr-style', plugin_dir_url( __FILE__ ) . 'assets/style.css' );
	} else {
		echo '<link rel="stylesheet" href="assets/style.css">';
	}
	?>
	<?php if ( function_exists( '\wp_app_head' ) ) \wp_app_head(); ?>
</head>
<body class="wp-app-body">
	<?php if ( function_exists( '\wp_app_body_open' ) ) \wp_app_body_open(); ?>

	<div class="container">
		<div class="header">
			<div>
				<h1>
					<a href="<?php echo $crm->build_url( 'group.php', array( 'group' => $current_group ) ); ?>" class="back-link" style="color: inherit; text-decoration: none;">← <?php echo htmlspecialchars( $group_data->group_name ); ?></a>
					History
				</h1>
			</div>
		</div>

		<div class="history-timeline">
			<?php if ( empty( $timeline ) ) : ?>
				<div class="empty-state">
					<p>No historical events recorded yet.</p>
					<p>As group members join or leave, and events are added, they will appear here.</p>
				</div>
			<?php else : ?>
				<?php
				$running_count = $current_count;
				foreach ( $timeline as $month_data ) :
					$month_events = $month_data['events'];
				?>
					<div class="month-section">
						<h2 class="month-header"><?php echo htmlspecialchars( $month_data['month_label'] ); ?></h2>

						<?php
						$consolidated_events = array();
						foreach ( $month_events as $event ) {
							$date_key = substr( $event['date'], 0, 10 );
							$type_key = $event['type'];
							$group_key = $event['group_name'] ?? '';

							if ( $event['type'] === 'team_event' ) {
								$consolidated_events[] = array(
									'type'   => 'single',
									'date'   => $date_key,
									'events' => array( $event ),
								);
							} else {
								$consolidation_key = $date_key . '|' . $type_key . '|' . $group_key;
								$found = false;
								foreach ( $consolidated_events as &$consolidated ) {
									if ( isset( $consolidated['key'] ) && $consolidated['key'] === $consolidation_key ) {
										$person_exists = false;
										foreach ( $consolidated['events'] as $existing_event ) {
											if ( $existing_event['username'] === $event['username'] ) {
												$person_exists = true;
												break;
											}
										}
										if ( ! $person_exists ) {
											$consolidated['events'][] = $event;
										}
										$found = true;
										break;
									}
								}
								unset( $consolidated );
								if ( ! $found ) {
									$consolidated_events[] = array(
										'type'   => 'multiple',
										'key'    => $consolidation_key,
										'date'   => $date_key,
										'events' => array( $event ),
									);
								}
							}
						}
						?>

						<?php foreach ( $consolidated_events as $consolidated ) : ?>
							<?php
							$event_date = new \DateTime( $consolidated['date'] );
							$formatted_date = $event_date->format( 'M j' );
							$events = $consolidated['events'];
							$first_event = $events[0];
							$event_count = count( $events );
							?>
							<div class="timeline-event <?php echo htmlspecialchars( $first_event['type'] ); ?>">
								<div class="event-content">
									<?php if ( $first_event['type'] === 'join' ) : ?>
										<?php
										$name_links = array();
										foreach ( $events as $evt ) {
											$person_url = $crm->build_url( 'person.php', array( 'person' => $evt['username'] ) );
											$name_links[] = '<a href="' . htmlspecialchars( $person_url ) . '" class="event-link"><strong>' . htmlspecialchars( $evt['person'] ) . '</strong></a>';
										}
										if ( count( $name_links ) === 1 ) {
											echo $name_links[0];
										} elseif ( count( $name_links ) === 2 ) {
											echo $name_links[0] . ' and ' . $name_links[1];
										} else {
											$last = array_pop( $name_links );
											echo implode( ', ', $name_links ) . ', and ' . $last;
										}
										?>
										<?php if ( ! empty( $first_event['from_group'] ) ) : ?>
											<?php echo count( $events ) > 1 ? 'join' : 'joins'; ?> the group from the <a href="<?php echo htmlspecialchars( $crm->build_url( 'group.php', array( 'group' => $first_event['from_group'] ) ) ); ?>" class="event-link"><strong><?php echo htmlspecialchars( $first_event['from_group'] ); ?></strong></a> group
										<?php elseif ( ! empty( $first_event['group_name'] ) && $first_event['group_name'] !== $group_data->group_name ) : ?>
											<?php echo count( $events ) > 1 ? 'join' : 'joins'; ?> the <a href="<?php echo htmlspecialchars( $crm->build_url( 'group.php', array( 'group' => $first_event['group_name'] ) ) ); ?>" class="event-link"><strong><?php echo htmlspecialchars( $first_event['group_name'] ); ?></strong></a> group
										<?php else : ?>
											<?php echo count( $events ) > 1 ? 'join' : 'joins'; ?> the group
										<?php endif; ?>
										<?php if ( ( empty( $first_event['from_group'] ) || ! empty( $first_event['from_group'] ) ) && $first_event['group_name'] === $group_data->group_name ) : ?>
											<?php
											$before_count = $running_count;
											$running_count -= count( $events );
											$after_count = $running_count;
											?>
											<?php if ( $after_count === 0 ) : ?>
												<span class="team-size-indicator">(created with <?php echo $before_count; ?> <?php echo $before_count === 1 ? 'person' : 'people'; ?>)</span>
											<?php else : ?>
												<span class="team-size-indicator">(grows from <?php echo $after_count; ?> to <?php echo $before_count; ?> <?php echo $before_count === 1 ? 'person' : 'people'; ?>)</span>
											<?php endif; ?>
										<?php endif; ?>

									<?php elseif ( $first_event['type'] === 'leave' ) : ?>
										<?php
										$name_links = array();
										foreach ( $events as $evt ) {
											$person_url = $crm->build_url( 'person.php', array( 'person' => $evt['username'] ) );
											$name_links[] = '<a href="' . htmlspecialchars( $person_url ) . '" class="event-link"><strong>' . htmlspecialchars( $evt['person'] ) . '</strong></a>';
										}
										if ( count( $name_links ) === 1 ) {
											echo $name_links[0];
										} elseif ( count( $name_links ) === 2 ) {
											echo $name_links[0] . ' and ' . $name_links[1];
										} else {
											$last = array_pop( $name_links );
											echo implode( ', ', $name_links ) . ', and ' . $last;
										}
										?>
										<?php if ( ! empty( $first_event['to_group'] ) ) : ?>
											<?php echo count( $events ) > 1 ? 'leave' : 'leaves'; ?> the group to the <a href="<?php echo htmlspecialchars( $crm->build_url( 'group.php', array( 'group' => $first_event['to_group'] ) ) ); ?>" class="event-link"><strong><?php echo htmlspecialchars( $first_event['to_group'] ); ?></strong></a> group
										<?php elseif ( ! empty( $first_event['group_name'] ) && $first_event['group_name'] !== $group_data->group_name ) : ?>
											<?php echo count( $events ) > 1 ? 'leave' : 'leaves'; ?> the <a href="<?php echo htmlspecialchars( $crm->build_url( 'group.php', array( 'group' => $first_event['group_name'] ) ) ); ?>" class="event-link"><strong><?php echo htmlspecialchars( $first_event['group_name'] ); ?></strong></a> group
										<?php else : ?>
											<?php echo count( $events ) > 1 ? 'leave' : 'leaves'; ?> the group
										<?php endif; ?>
										<?php if ( ! empty( $first_event['new_company'] ) && count( $events ) === 1 ) : ?>
											to join
											<?php if ( ! empty( $first_event['new_company_website'] ) ) : ?>
												<a href="<?php echo htmlspecialchars( $first_event['new_company_website'] ); ?>" target="_blank" class="event-link"><?php echo htmlspecialchars( $first_event['new_company'] ); ?></a>
											<?php else : ?>
												<?php echo htmlspecialchars( $first_event['new_company'] ); ?>
											<?php endif; ?>
										<?php endif; ?>
										<?php if ( ( empty( $first_event['to_group'] ) || ! empty( $first_event['to_group'] ) ) && $first_event['group_name'] === $group_data->group_name ) : ?>
											<?php
											$before_count = $running_count;
											$running_count += count( $events );
											$after_count = $running_count;
											?>
											<span class="team-size-indicator">(shrinks from <?php echo $after_count; ?> to <?php echo $before_count; ?> <?php echo $before_count === 1 ? 'person' : 'people'; ?>)</span>
										<?php endif; ?>

									<?php elseif ( $first_event['type'] === 'team_event' ) : ?>
										<strong><?php echo htmlspecialchars( ucfirst( str_replace( '_', ' ', $first_event['event_type'] ) ) ); ?>:</strong>
										<?php echo htmlspecialchars( $first_event['name'] ); ?>
										<?php if ( ! empty( $first_event['location'] ) ) : ?>
											<span class="event-meta">in <?php echo htmlspecialchars( $first_event['location'] ); ?></span>
										<?php endif; ?>
										<?php if ( ! empty( $first_event['description'] ) ) : ?>
											<div class="event-meta"><?php echo htmlspecialchars( $first_event['description'] ); ?></div>
										<?php endif; ?>
									<?php endif; ?>
								</div>
								<div class="event-date"><?php echo htmlspecialchars( $formatted_date ); ?></div>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>

		<footer class="privacy-footer">
			<?php
			do_action( 'personal_crm_footer_links', $group_data, $current_group );
			?>
			<a href="<?php echo $crm->build_url( 'admin/index.php', array( 'group' => $current_group ) ); ?>" class="footer-link">⚙️ Admin Panel</a>
		</footer>
	</div>

	<?php
	if ( function_exists( '\wp_app_body_close' ) ) \wp_app_body_close();
	?>
</body>
</html>
