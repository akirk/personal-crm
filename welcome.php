<?php
/**
 * Welcome Page
 *
 * Onboarding page for new users with extensible sections.
 * Other plugins can register their own sections via the personal_crm_welcome_sections filter.
 */
namespace PersonalCRM;

require_once __DIR__ . '/personal-crm.php';

$crm = PersonalCrm::get_instance();

do_action( 'personal_crm_welcome_page_init', $crm );

$is_first_use = empty( $crm->storage->get_available_groups() );

$sections = array();

$sections[] = array(
	'id'          => 'create-first-group',
	'title'       => 'Create Your First Group',
	'description' => 'Start by creating a group to organize your contacts.',
	'callback'    => __NAMESPACE__ . '\\render_create_group_section',
	'priority'    => 5,
	'icon'        => '+',
);

$sections = apply_filters( 'personal_crm_welcome_sections', $sections, $crm );

usort( $sections, function( $a, $b ) {
	return ( $a['priority'] ?? 10 ) <=> ( $b['priority'] ?? 10 );
} );

function render_create_group_section( $crm ) {
	$create_url = $crm->build_url( 'admin/index.php', array( 'create_group' => 'new' ) );
	?>
	<div class="welcome-section-content">
		<p>Groups help you organize your contacts. Create a group for family, friends, work colleagues, or any category you like.</p>
		<form action="<?php echo esc_url( $create_url ); ?>" method="get" class="create-group-form">
			<input type="hidden" name="create_group" value="new">
			<input type="text" name="name" placeholder="Group name (e.g., Family, Work)" required>
			<button type="submit" class="btn btn-primary">Create Group</button>
		</form>
	</div>
	<?php
}

?>
<!DOCTYPE html>
<html <?php echo function_exists( '\wp_app_language_attributes' ) ? \wp_app_language_attributes() : 'lang="en"'; ?>>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="color-scheme" content="light dark">
	<title><?php echo function_exists( '\wp_app_title' ) ? \wp_app_title( 'Welcome' ) : 'Welcome'; ?></title>
	<?php
	if ( function_exists( '\wp_app_enqueue_style' ) ) {
		wp_app_enqueue_style( 'personal-crm-style', plugin_dir_url( __FILE__ ) . 'assets/style.css' );
	} else {
		echo '<link rel="stylesheet" href="assets/style.css">';
	}
	?>
	<?php if ( function_exists( '\wp_app_head' ) ) \wp_app_head(); ?>
	<style>
		.welcome-container {
			max-width: 800px;
			margin: 0 auto;
			padding: 40px 20px;
		}
		.welcome-header {
			text-align: center;
			margin-bottom: 40px;
		}
		.welcome-header h1 {
			font-size: 2em;
			margin-bottom: 8px;
		}
		.welcome-header p {
			color: light-dark(#666, #999);
			font-size: 1.1em;
		}
		.welcome-sections {
			display: flex;
			flex-direction: column;
			gap: 24px;
		}
		.welcome-section {
			background: light-dark(#fff, #1e1e1e);
			border: 1px solid light-dark(#e0e0e0, #333);
			border-radius: 12px;
			padding: 24px;
		}
		.welcome-section-header {
			display: flex;
			align-items: center;
			gap: 12px;
			margin-bottom: 8px;
		}
		.welcome-section-header .section-icon {
			width: 40px;
			height: 40px;
			background: light-dark(#f0f0f0, #333);
			border-radius: 8px;
			display: flex;
			align-items: center;
			justify-content: center;
			font-size: 1.2em;
		}
		.welcome-section-header h2 {
			margin: 0;
			font-size: 1.3em;
		}
		.welcome-section .section-description {
			color: light-dark(#666, #999);
			margin: 0 0 16px 0;
			padding-left: 52px;
		}
		.welcome-section-content {
			padding-left: 52px;
		}
		.welcome-section-content p {
			margin: 0 0 16px 0;
			color: light-dark(#444, #bbb);
		}
		.create-group-form {
			display: flex;
			gap: 12px;
			flex-wrap: wrap;
		}
		.create-group-form input[type="text"] {
			flex: 1;
			min-width: 200px;
			padding: 12px 16px;
			border: 1px solid light-dark(#ddd, #444);
			border-radius: 8px;
			font-size: 16px;
			background: light-dark(#fff, #2a2a2a);
			color: inherit;
		}
		.create-group-form input[type="text"]:focus {
			outline: none;
			border-color: #0073aa;
		}
		.btn {
			padding: 12px 24px;
			border-radius: 8px;
			font-size: 16px;
			font-weight: 500;
			cursor: pointer;
			text-decoration: none;
			display: inline-flex;
			align-items: center;
			gap: 8px;
			border: none;
		}
		.btn-primary {
			background: #0073aa;
			color: white;
		}
		.btn-primary:hover {
			background: #005a87;
		}
		.btn-secondary {
			background: light-dark(#e8e8e8, #333);
			color: inherit;
		}
		.btn-secondary:hover {
			background: light-dark(#ddd, #444);
		}
		.skip-link {
			text-align: center;
			margin-top: 32px;
		}
		.skip-link a {
			color: light-dark(#666, #999);
			text-decoration: none;
		}
		.skip-link a:hover {
			text-decoration: underline;
		}
	</style>
</head>
<body class="wp-app-body">
	<?php if ( function_exists( '\wp_app_body_open' ) ) \wp_app_body_open(); ?>

	<div class="welcome-container">
		<div class="welcome-header">
			<h1><?php echo $is_first_use ? 'Welcome to Personal CRM' : 'Import & Setup'; ?></h1>
			<p><?php echo $is_first_use ? 'Get started by setting up your first group or importing contacts.' : 'Import contacts or configure your CRM.'; ?></p>
		</div>

		<?php do_action( 'personal_crm_before_welcome_sections', $crm ); ?>

		<div class="welcome-sections">
			<?php foreach ( $sections as $section ) :
				if ( ! empty( $section['capability'] ) && ! current_user_can( $section['capability'] ) ) {
					continue;
				}
			?>
				<div class="welcome-section" id="section-<?php echo esc_attr( $section['id'] ); ?>">
					<div class="welcome-section-header">
						<?php if ( ! empty( $section['icon'] ) ) : ?>
							<span class="section-icon"><?php echo $section['icon']; ?></span>
						<?php endif; ?>
						<h2><?php echo esc_html( $section['title'] ); ?></h2>
					</div>
					<?php if ( ! empty( $section['description'] ) ) : ?>
						<p class="section-description"><?php echo esc_html( $section['description'] ); ?></p>
					<?php endif; ?>
					<?php
					if ( is_callable( $section['callback'] ) ) {
						call_user_func( $section['callback'], $crm );
					}
					?>
				</div>
			<?php endforeach; ?>
		</div>

		<?php do_action( 'personal_crm_after_welcome_sections', $crm ); ?>

		<?php if ( ! $is_first_use ) : ?>
			<div class="skip-link">
				<a href="<?php echo $crm->build_url( 'index.php' ); ?>">← Return to Dashboard</a>
			</div>
		<?php endif; ?>
	</div>

	<?php if ( function_exists( '\wp_app_footer' ) ) \wp_app_footer(); ?>
</body>
</html>
