<?php
/**
 * People List Page
 *
 * Display all people across all groups with search/filter.
 */
namespace PersonalCRM;

require_once __DIR__ . '/personal-crm.php';

$crm = PersonalCrm::get_instance();

// Get filter parameters
$search = isset( $_GET['search'] ) ? sanitize_text_field( $_GET['search'] ) : '';
$filter_group = isset( $_GET['group'] ) ? sanitize_text_field( $_GET['group'] ) : '';
$filter_no_group = isset( $_GET['no_group'] ) && $_GET['no_group'] === '1';
$sort_by = isset( $_GET['sort'] ) ? sanitize_text_field( $_GET['sort'] ) : 'first';

// Load all people
if ( $filter_no_group ) {
	$people = $crm->storage->get_people_without_groups();
} elseif ( ! empty( $filter_group ) ) {
	$group = $crm->storage->get_group( $filter_group );
	$people = $group ? $group->get_members() : array();
} else {
	$people = $crm->storage->get_all_people();
}

// Apply search filter
if ( ! empty( $search ) ) {
	$people = array_filter( $people, function( $person ) use ( $search ) {
		$search_lower = strtolower( $search );
		return strpos( strtolower( $person->name ), $search_lower ) !== false
			|| strpos( strtolower( $person->username ), $search_lower ) !== false
			|| strpos( strtolower( $person->location ?? '' ), $search_lower ) !== false;
	});
}

// Helper to get sort key for a person
function get_sort_key( $person, $sort_by ) {
	if ( $sort_by === 'last' ) {
		$parts = explode( ' ', trim( $person->name ) );
		return count( $parts ) > 1 ? end( $parts ) : $parts[0];
	}
	return trim( $person->name );
}

// Sort by first or last name
usort( $people, function( $a, $b ) use ( $sort_by ) {
	return strcasecmp( get_sort_key( $a, $sort_by ), get_sort_key( $b, $sort_by ) );
});

// Group people by first letter
$grouped_people = array();
$available_letters = array();
foreach ( $people as $person ) {
	$sort_key = get_sort_key( $person, $sort_by );
	$letter = strtoupper( mb_substr( $sort_key, 0, 1 ) );
	if ( ! ctype_alpha( $letter ) ) {
		$letter = '#';
	}
	if ( ! isset( $grouped_people[ $letter ] ) ) {
		$grouped_people[ $letter ] = array();
		$available_letters[] = $letter;
	}
	$grouped_people[ $letter ][] = $person;
}

// Get all groups for filter dropdown
$all_groups = $crm->storage->get_all_groups_with_hierarchy();

// Build filter URL helper
function build_filter_url( $params = array() ) {
	$base = home_url( '/crm/people' );
	return empty( $params ) ? $base : $base . '?' . http_build_query( $params );
}

?>
<!DOCTYPE html>
<html <?php echo function_exists( 'wp_app_language_attributes' ) ? wp_app_language_attributes() : 'lang="en"'; ?>>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="color-scheme" content="light dark">
	<title><?php echo function_exists( 'wp_app_title' ) ? wp_app_title( 'People' ) : 'People'; ?></title>
	<?php
	if ( function_exists( 'wp_app_enqueue_style' ) ) {
		wp_app_enqueue_style( 'personal-crm-style', plugin_dir_url( __FILE__ ) . 'assets/style.css' );
	} else {
		echo '<link rel="stylesheet" href="' . plugin_dir_url( __FILE__ ) . 'assets/style.css">';
	}
	?>
	<?php if ( function_exists( 'wp_app_head' ) ) wp_app_head(); ?>
	<style>
		.filter-bar {
			display: flex;
			gap: 12px;
			align-items: center;
			flex-wrap: wrap;
			padding: 16px;
			background: light-dark(#f5f5f5, #2a2a2a);
			border-radius: 8px;
			margin-bottom: 20px;
		}
		.filter-bar input[type="search"] {
			flex: 1;
			min-width: 200px;
			padding: 10px 14px;
			border: 1px solid light-dark(#ddd, #444);
			border-radius: 6px;
			background: light-dark(#fff, #333);
			color: inherit;
			font-size: 14px;
		}
		.filter-bar select {
			padding: 10px 14px;
			border: 1px solid light-dark(#ddd, #444);
			border-radius: 6px;
			background: light-dark(#fff, #333);
			color: inherit;
			font-size: 14px;
		}
		.filter-bar a.filter-button {
			padding: 10px 16px;
			background: light-dark(#fff, #333);
			border: 1px solid light-dark(#ddd, #444);
			border-radius: 6px;
			text-decoration: none;
			color: inherit;
			font-size: 14px;
		}
		.filter-bar a.filter-button:hover {
			background: light-dark(#e8e8e8, #444);
		}
		.filter-bar a.filter-button.active {
			background: #0073aa;
			color: white;
			border-color: #0073aa;
		}
		.sort-controls {
			display: flex;
			gap: 8px;
			align-items: center;
			margin-left: auto;
		}
		.sort-controls span {
			font-size: 13px;
			color: light-dark(#666, #999);
		}
		.sort-controls a {
			padding: 6px 12px;
			border-radius: 4px;
			text-decoration: none;
			font-size: 13px;
			color: light-dark(#333, #ccc);
			background: light-dark(#e8e8e8, #333);
		}
		.sort-controls a.active {
			background: #0073aa;
			color: white;
		}
		.contact-list {
			background: light-dark(#fff, #1e1e1e);
			border: 1px solid light-dark(#e0e0e0, #333);
			border-radius: 8px;
			overflow: hidden;
		}
		.contact-row {
			display: flex;
			align-items: center;
			padding: 12px 16px;
			text-decoration: none;
			color: inherit;
			border-bottom: 1px solid light-dark(#eee, #333);
			transition: background 0.15s;
		}
		.contact-row:last-child {
			border-bottom: none;
		}
		.contact-row:hover {
			background: light-dark(#f8f8f8, #252525);
		}
		.contact-name {
			flex: 1;
			min-width: 0;
		}
		.contact-name .name {
			font-weight: 600;
			font-size: 15px;
		}
		.contact-name .username {
			font-size: 13px;
			color: light-dark(#666, #999);
		}
		.contact-location {
			width: 180px;
			font-size: 13px;
			color: light-dark(#666, #999);
			text-align: right;
			padding-left: 16px;
		}
		.contact-groups {
			width: 200px;
			display: flex;
			flex-wrap: wrap;
			gap: 4px;
			justify-content: flex-end;
			padding-left: 16px;
		}
		.contact-groups .group-tag {
			font-size: 11px;
			padding: 2px 8px;
			background: light-dark(#f0f0f0, #333);
			border-radius: 4px;
			color: light-dark(#666, #999);
			white-space: nowrap;
		}
		@media (max-width: 768px) {
			.contact-location, .contact-groups {
				display: none;
			}
		}
		.contact-wrapper {
			display: flex;
			gap: 16px;
		}
		.alphabet-sidebar {
			position: sticky;
			top: 20px;
			display: flex;
			flex-direction: column;
			gap: 2px;
			padding: 8px 4px;
			background: light-dark(#f5f5f5, #2a2a2a);
			border-radius: 8px;
			align-self: flex-start;
		}
		.alphabet-sidebar a {
			display: flex;
			align-items: center;
			justify-content: center;
			width: 28px;
			height: 24px;
			font-size: 12px;
			font-weight: 600;
			text-decoration: none;
			color: light-dark(#666, #999);
			border-radius: 4px;
			transition: background 0.15s, color 0.15s;
		}
		.alphabet-sidebar a:hover {
			background: light-dark(#e0e0e0, #444);
			color: light-dark(#333, #fff);
		}
		.alphabet-sidebar a.available {
			color: light-dark(#333, #ddd);
		}
		.alphabet-sidebar a.disabled {
			color: light-dark(#ccc, #555);
			pointer-events: none;
		}
		.contact-list-container {
			flex: 1;
			min-width: 0;
		}
		.letter-divider {
			display: flex;
			align-items: center;
			padding: 8px 16px;
			background: light-dark(#f0f0f0, #252525);
			font-weight: 600;
			font-size: 14px;
			color: light-dark(#666, #999);
			position: sticky;
			top: 0;
			z-index: 1;
		}
		@media (max-width: 768px) {
			.alphabet-sidebar {
				display: none;
			}
		}
		.results-count {
			color: light-dark(#666, #999);
			font-size: 14px;
			margin-bottom: 16px;
		}
		.no-results {
			text-align: center;
			padding: 60px 20px;
			color: light-dark(#666, #999);
		}
		.no-results h2 {
			margin-bottom: 8px;
		}
	</style>
</head>
<body class="wp-app-body">
	<?php if ( function_exists( 'wp_app_body_open' ) ) wp_app_body_open(); ?>

	<div class="container">
		<div class="header">
			<h1>People</h1>
			<div class="navigation">
				<a href="<?php echo home_url( '/crm/' ); ?>">← Back to CRM</a>
			</div>
		</div>

		<form class="filter-bar" method="get" action="<?php echo home_url( '/crm/people' ); ?>">
			<input type="search" name="search" placeholder="Search by name, username, or location..."
			       value="<?php echo esc_attr( $search ); ?>">
			<input type="hidden" name="sort" value="<?php echo esc_attr( $sort_by ); ?>">

			<select name="group" onchange="this.form.submit()">
				<option value="">All Groups</option>
				<?php foreach ( $all_groups as $group ) : ?>
					<option value="<?php echo esc_attr( $group['slug'] ); ?>"
					        <?php selected( $filter_group, $group['slug'] ); ?>>
						<?php echo esc_html( $group['display_icon'] . ' ' . $group['hierarchical_name'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<a href="<?php echo build_filter_url( array( 'no_group' => '1', 'sort' => $sort_by ) ); ?>"
			   class="filter-button <?php echo $filter_no_group ? 'active' : ''; ?>">
				Without Groups
			</a>

			<?php if ( ! empty( $search ) || ! empty( $filter_group ) || $filter_no_group ) : ?>
				<a href="<?php echo build_filter_url( array( 'sort' => $sort_by ) ); ?>" class="filter-button">Clear</a>
			<?php endif; ?>

			<div class="sort-controls">
				<span>Sort:</span>
				<?php
				$sort_params = array();
				if ( ! empty( $search ) ) $sort_params['search'] = $search;
				if ( ! empty( $filter_group ) ) $sort_params['group'] = $filter_group;
				if ( $filter_no_group ) $sort_params['no_group'] = '1';
				?>
				<a href="<?php echo build_filter_url( array_merge( $sort_params, array( 'sort' => 'first' ) ) ); ?>"
				   class="<?php echo $sort_by === 'first' ? 'active' : ''; ?>">First Name</a>
				<a href="<?php echo build_filter_url( array_merge( $sort_params, array( 'sort' => 'last' ) ) ); ?>"
				   class="<?php echo $sort_by === 'last' ? 'active' : ''; ?>">Last Name</a>
			</div>
		</form>

		<div class="results-count">
			<?php
			echo count( $people ) . ' ' . ( count( $people ) === 1 ? 'person' : 'people' );
			if ( $filter_no_group ) {
				echo ' without groups';
			} elseif ( ! empty( $filter_group ) ) {
				$group_name = $crm->storage->get_group_name( $filter_group );
				echo ' in ' . esc_html( $group_name );
			}
			if ( ! empty( $search ) ) {
				echo ' matching "' . esc_html( $search ) . '"';
			}
			?>
		</div>

		<?php if ( empty( $people ) ) : ?>
			<div class="no-results">
				<h2>No people found</h2>
				<p>Try adjusting your search or filters.</p>
			</div>
		<?php else : ?>
			<div class="contact-wrapper">
				<nav class="alphabet-sidebar">
					<?php
					$all_letters = array_merge( range( 'A', 'Z' ), array( '#' ) );
					foreach ( $all_letters as $letter ) :
						$is_available = in_array( $letter, $available_letters, true );
					?>
						<a href="#letter-<?php echo $letter === '#' ? 'num' : $letter; ?>"
						   class="<?php echo $is_available ? 'available' : 'disabled'; ?>"><?php echo $letter; ?></a>
					<?php endforeach; ?>
				</nav>

				<div class="contact-list-container">
					<div class="contact-list">
						<?php foreach ( $grouped_people as $letter => $group_members ) : ?>
							<div class="letter-divider" id="letter-<?php echo $letter === '#' ? 'num' : $letter; ?>">
								<?php echo esc_html( $letter ); ?>
							</div>
							<?php foreach ( $group_members as $person ) : ?>
								<a href="<?php echo $crm->build_url( 'person.php', array( 'person' => $person->username ) ); ?>"
								   class="contact-row">
									<div class="contact-name">
										<div class="name"><?php echo esc_html( $person->get_display_name_with_nickname() ); ?></div>
										<div class="username">@<?php echo esc_html( $person->username ); ?></div>
									</div>
									<div class="contact-location">
										<?php if ( ! empty( $person->location ) ) : ?>
											📍 <?php echo esc_html( $person->location ); ?>
										<?php endif; ?>
									</div>
									<div class="contact-groups">
										<?php if ( ! empty( $person->groups ) ) : ?>
											<?php
											$active_groups = array_filter( $person->groups, fn( $g ) => empty( $g['group_left_date'] ) );
											foreach ( array_slice( $active_groups, 0, 2 ) as $group ) :
											?>
												<span class="group-tag"><?php echo esc_html( $group['display_icon'] . ' ' . $group['group_name'] ); ?></span>
											<?php endforeach; ?>
											<?php if ( count( $active_groups ) > 2 ) : ?>
												<span class="group-tag">+<?php echo count( $active_groups ) - 2; ?></span>
											<?php endif; ?>
										<?php endif; ?>
									</div>
								</a>
							<?php endforeach; ?>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
		<?php endif; ?>
	</div>

	<script>
	document.querySelectorAll('.alphabet-sidebar a.available').forEach(link => {
		link.addEventListener('click', function(e) {
			e.preventDefault();
			const target = document.querySelector(this.getAttribute('href'));
			if (target) {
				target.scrollIntoView({ behavior: 'smooth', block: 'start' });
			}
		});
	});
	</script>

	<?php if ( function_exists( 'wp_app_body_close' ) ) wp_app_body_close(); ?>
</body>
</html>
