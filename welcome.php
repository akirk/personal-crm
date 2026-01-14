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

if ( ! $is_first_use ) {
	$sections[] = array(
		'id'          => 'browse-crm',
		'title'       => 'Browse Your CRM',
		'description' => 'Jump to your groups or people.',
		'callback'    => __NAMESPACE__ . '\\render_browse_section',
		'priority'    => 1,
		'icon'        => '📋',
	);
}

$sections[] = array(
	'id'          => 'create-first-group',
	'title'       => $is_first_use ? 'Create Your First Group' : 'Create Another Group',
	'description' => $is_first_use ? 'Start by creating a group to organize your contacts.' : 'Add a new group to organize more contacts.',
	'callback'    => __NAMESPACE__ . '\\render_create_group_section',
	'priority'    => 5,
	'icon'        => '+',
);

if ( current_user_can( 'manage_options' ) ) {
	$sections[] = array(
		'id'          => 'import-data',
		'title'       => 'Import Data',
		'description' => 'Restore from a previous export or import data from another CRM.',
		'callback'    => __NAMESPACE__ . '\\render_import_section',
		'priority'    => 50,
		'icon'        => '↑',
		'capability'  => 'manage_options',
	);
}

$sections = apply_filters( 'personal_crm_welcome_sections', $sections, $crm );

usort( $sections, function( $a, $b ) {
	return ( $a['priority'] ?? 10 ) <=> ( $b['priority'] ?? 10 );
} );

function render_browse_section( $crm ) {
	$groups = $crm->storage->get_available_groups();
	$people_count = count( $crm->storage->get_all_people() );
	?>
	<div class="welcome-section-content">
		<div class="browse-links">
			<a href="<?php echo home_url( '/crm/select' ); ?>" class="browse-link">
				<span class="browse-link-icon">📁</span>
				<span class="browse-link-text">
					<strong>Groups</strong>
					<span><?php echo count( $groups ); ?> group<?php echo count( $groups ) !== 1 ? 's' : ''; ?></span>
				</span>
			</a>
			<a href="<?php echo home_url( '/crm/people' ); ?>" class="browse-link">
				<span class="browse-link-icon">👥</span>
				<span class="browse-link-text">
					<strong>People</strong>
					<span><?php echo $people_count; ?> <?php echo $people_count !== 1 ? 'people' : 'person'; ?></span>
				</span>
			</a>
		</div>
	</div>
	<?php
}

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

function render_import_section( $crm ) {
	$import_result = null;

	// Handle import form submission
	if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['action'] ) && $_POST['action'] === 'import_data' ) {
		if ( ! wp_verify_nonce( $_POST['import_nonce'], 'personal_crm_import' ) ) {
			$import_result = array( 'success' => false, 'error' => 'Security check failed.' );
		} elseif ( ! isset( $_FILES['import_file'] ) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK ) {
			$import_result = array( 'success' => false, 'error' => 'File upload failed. Please try again.' );
		} else {
			$file_path = $_FILES['import_file']['tmp_name'];
			$mode = sanitize_text_field( $_POST['import_mode'] ?? 'replace' );

			$importer = new ExportImport( $crm );
			$result = $importer->import_from_jsonl( $file_path, $mode );

			if ( is_wp_error( $result ) ) {
				$import_result = array( 'success' => false, 'error' => $result->get_error_message() );
			} else {
				$total = array_sum( $result['counts'] );
				$skipped = array_sum( $result['skipped'] ?? array() );
				$import_result = array( 'success' => true, 'count' => $total, 'skipped' => $skipped, 'mode' => $result['mode'] );
			}
		}
	}
	?>
	<div class="welcome-section-content">
		<?php if ( $import_result ) : ?>
			<?php if ( $import_result['success'] ) : ?>
				<div class="import-notice import-success">
					Successfully imported <?php echo number_format( $import_result['count'] ); ?> records
					(<?php echo $import_result['mode'] === 'replace' ? 'replaced all data' : 'merged with existing'; ?>).
					<?php if ( ! empty( $import_result['skipped'] ) ) : ?>
						<?php echo number_format( $import_result['skipped'] ); ?> records skipped (duplicates or missing references).
					<?php endif; ?>
				</div>
			<?php else : ?>
				<div class="import-notice import-error">
					Import failed: <?php echo esc_html( $import_result['error'] ); ?>
				</div>
			<?php endif; ?>
		<?php endif; ?>

		<p>Upload a JSONL export file to restore your data. You can export your current data from the <a href="<?php echo home_url( '/crm/admin/export' ); ?>">Export page</a>.</p>

		<form method="post" enctype="multipart/form-data" class="import-form">
			<?php wp_nonce_field( 'personal_crm_import', 'import_nonce' ); ?>
			<input type="hidden" name="action" value="import_data">

			<div class="import-options">
				<label class="import-mode-option">
					<input type="radio" name="import_mode" value="replace" checked>
					<span class="option-label">Replace all data</span>
					<span class="option-desc">Clear existing data and import fresh (recommended for restoring backups)</span>
				</label>
				<label class="import-mode-option">
					<input type="radio" name="import_mode" value="merge">
					<span class="option-label">Merge with existing</span>
					<span class="option-desc">Add new records, update existing ones by matching unique keys</span>
				</label>
			</div>

			<div class="file-input-wrapper">
				<input type="file" name="import_file" accept=".jsonl" required>
			</div>

			<button type="submit" class="btn btn-secondary">Import Data</button>
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
		wp_app_enqueue_style( 'personal-crm-cmd-k', plugin_dir_url( __FILE__ ) . 'assets/cmd-k.css' );
	} else {
		echo '<link rel="stylesheet" href="assets/style.css">';
		echo '<link rel="stylesheet" href="assets/cmd-k.css">';
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
		.browse-links {
			display: flex;
			gap: 16px;
			flex-wrap: wrap;
		}
		.browse-link {
			display: flex;
			align-items: center;
			gap: 12px;
			padding: 16px 20px;
			background: light-dark(#f8f8f8, #2a2a2a);
			border: 1px solid light-dark(#e0e0e0, #444);
			border-radius: 8px;
			text-decoration: none;
			color: inherit;
			transition: border-color 0.15s, background 0.15s;
			min-width: 180px;
		}
		.browse-link:hover {
			border-color: #0073aa;
			background: light-dark(#f0f7fc, #1a2a3a);
		}
		.browse-link-icon {
			font-size: 1.5em;
		}
		.browse-link-text {
			display: flex;
			flex-direction: column;
		}
		.browse-link-text strong {
			font-size: 15px;
		}
		.browse-link-text span {
			font-size: 13px;
			color: light-dark(#666, #999);
		}
		/* Import section styles */
		.import-form {
			display: flex;
			flex-direction: column;
			gap: 16px;
		}
		.import-options {
			display: flex;
			flex-direction: column;
			gap: 12px;
		}
		.import-mode-option {
			display: flex;
			flex-direction: column;
			padding: 12px 16px;
			background: light-dark(#f8f8f8, #2a2a2a);
			border: 1px solid light-dark(#e0e0e0, #444);
			border-radius: 8px;
			cursor: pointer;
			transition: border-color 0.15s;
		}
		.import-mode-option:has(input:checked) {
			border-color: #0073aa;
			background: light-dark(#f0f7fc, #1a2a3a);
		}
		.import-mode-option input {
			position: absolute;
			opacity: 0;
			pointer-events: none;
		}
		.option-label {
			font-weight: 500;
			margin-bottom: 4px;
		}
		.import-mode-option:has(input:checked) .option-label::before {
			content: "✓ ";
			color: #0073aa;
		}
		.option-desc {
			font-size: 13px;
			color: light-dark(#666, #999);
		}
		.file-input-wrapper {
			padding: 16px;
			background: light-dark(#f8f8f8, #2a2a2a);
			border: 2px dashed light-dark(#ddd, #444);
			border-radius: 8px;
			text-align: center;
		}
		.file-input-wrapper input[type="file"] {
			font-size: 14px;
		}
		.import-notice {
			padding: 12px 16px;
			border-radius: 8px;
			margin-bottom: 16px;
		}
		.import-success {
			background: light-dark(#d4edda, #1a3a2a);
			color: light-dark(#155724, #7dcea0);
			border: 1px solid light-dark(#c3e6cb, #2a5a4a);
		}
		.import-error {
			background: light-dark(#f8d7da, #3a1a2a);
			color: light-dark(#721c24, #e07080);
			border: 1px solid light-dark(#f5c6cb, #5a2a3a);
		}
	</style>
</head>
<body class="wp-app-body">
	<?php if ( function_exists( '\wp_app_body_open' ) ) \wp_app_body_open(); ?>
	<?php $crm->render_cmd_k_panel(); ?>

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

	<script src="<?php echo plugin_dir_url( __FILE__ ) . 'assets/cmd-k.js'; ?>"></script>
	<?php $crm->init_cmd_k_js(); ?>
	<?php if ( function_exists( '\wp_app_footer' ) ) \wp_app_footer(); ?>
</body>
</html>
