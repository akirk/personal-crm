<?php
/**
 * Export Page
 *
 * Displays export options and handles JSONL file download.
 */
namespace PersonalCRM;

require_once __DIR__ . '/personal-crm.php';

$crm = PersonalCrm::get_instance();

// Check capability
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'You do not have permission to access this page.' );
}

// Handle export download
if ( isset( $_GET['download'] ) && $_GET['download'] === '1' ) {
    check_admin_referer( 'personal_crm_export', 'nonce' );

    $exporter = new ExportImport( $crm );
    $content = $exporter->export_to_jsonl();

    $filename = 'personal-crm-export-' . date( 'Y-m-d-His' ) . '.jsonl';

    header( 'Content-Type: application/jsonl' );
    header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
    header( 'Content-Length: ' . strlen( $content ) );
    header( 'Cache-Control: no-cache, no-store, must-revalidate' );
    header( 'Pragma: no-cache' );
    header( 'Expires: 0' );

    echo $content;
    exit;
}

// Get table counts for display
$exporter = new ExportImport( $crm );
$table_counts = $exporter->get_table_counts();
$total_records = array_sum( $table_counts );

$download_url = wp_nonce_url(
    home_url( '/crm/admin/export?download=1' ),
    'personal_crm_export',
    'nonce'
);

?>
<!DOCTYPE html>
<html <?php echo function_exists( '\wp_app_language_attributes' ) ? \wp_app_language_attributes() : 'lang="en"'; ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <title><?php echo function_exists( '\wp_app_title' ) ? \wp_app_title( 'Export Data' ) : 'Export Data'; ?></title>
    <?php
    if ( function_exists( '\wp_app_enqueue_style' ) ) {
        wp_app_enqueue_style( 'personal-crm-style', plugin_dir_url( __FILE__ ) . 'assets/style.css' );
    } else {
        echo '<link rel="stylesheet" href="assets/style.css">';
    }
    ?>
    <?php if ( function_exists( '\wp_app_head' ) ) \wp_app_head(); ?>
    <style>
        .export-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        .export-header {
            margin-bottom: 32px;
        }
        .export-header h1 {
            font-size: 1.8em;
            margin-bottom: 8px;
        }
        .export-header p {
            color: light-dark(#666, #999);
            margin: 0;
        }
        .export-card {
            background: light-dark(#fff, #1e1e1e);
            border: 1px solid light-dark(#e0e0e0, #333);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
        }
        .export-card h2 {
            margin: 0 0 16px 0;
            font-size: 1.2em;
        }
        .table-list {
            margin: 0 0 24px 0;
            padding: 0;
            list-style: none;
        }
        .table-list li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid light-dark(#eee, #333);
        }
        .table-list li:last-child {
            border-bottom: none;
        }
        .table-name {
            font-family: monospace;
            font-size: 13px;
            color: light-dark(#444, #ccc);
        }
        .table-count {
            font-size: 13px;
            color: light-dark(#666, #999);
            background: light-dark(#f5f5f5, #2a2a2a);
            padding: 4px 10px;
            border-radius: 12px;
        }
        .export-summary {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px;
            background: light-dark(#f8f8f8, #2a2a2a);
            border-radius: 8px;
            margin-bottom: 24px;
        }
        .export-summary .total {
            font-size: 1.1em;
            font-weight: 500;
        }
        .export-summary .format {
            font-size: 13px;
            color: light-dark(#666, #999);
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
        .export-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .info-box {
            background: light-dark(#f0f7ff, #1a2a3a);
            border-left: 4px solid #0073aa;
            padding: 16px;
            border-radius: 0 8px 8px 0;
            margin-top: 24px;
        }
        .info-box h3 {
            margin: 0 0 8px 0;
            font-size: 14px;
            font-weight: 600;
        }
        .info-box p {
            margin: 0;
            font-size: 13px;
            color: light-dark(#555, #aaa);
        }
        .info-box code {
            background: light-dark(#e8e8e8, #333);
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 12px;
        }
        .back-link {
            margin-top: 24px;
        }
        .back-link a {
            color: light-dark(#666, #999);
            text-decoration: none;
        }
        .back-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body class="wp-app-body">
    <?php if ( function_exists( '\wp_app_body_open' ) ) \wp_app_body_open(); ?>

    <div class="export-container">
        <div class="export-header">
            <h1>Export Data</h1>
            <p>Download all your CRM data as a JSONL file for backup or migration.</p>
        </div>

        <div class="export-card">
            <h2>Data Summary</h2>

            <div class="export-summary">
                <span class="total"><?php echo number_format( $total_records ); ?> total records</span>
                <span class="format">Format: JSONL (JSON Lines)</span>
            </div>

            <ul class="table-list">
                <?php foreach ( $table_counts as $table_name => $count ) : ?>
                    <li>
                        <span class="table-name"><?php echo esc_html( $table_name ); ?></span>
                        <span class="table-count"><?php echo number_format( $count ); ?> <?php echo $count === 1 ? 'record' : 'records'; ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="export-actions">
                <a href="<?php echo esc_url( $download_url ); ?>" class="btn btn-primary">
                    Download Export (.jsonl)
                </a>
            </div>

            <div class="info-box">
                <h3>About JSONL Format</h3>
                <p>
                    JSONL (JSON Lines) stores one JSON object per line, making it easy to process
                    large datasets and maintain data integrity. The export includes all groups, people,
                    relationships, events, notes, and links. To restore, use the Import function on the
                    <a href="<?php echo home_url( '/crm/welcome' ); ?>">Welcome page</a>.
                </p>
            </div>
        </div>

        <div class="back-link">
            <a href="<?php echo home_url( '/crm/' ); ?>">← Back to Dashboard</a>
        </div>
    </div>

    <?php if ( function_exists( '\wp_app_footer' ) ) \wp_app_footer(); ?>
</body>
</html>
