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

    $filter = array();
    if ( ! empty( $_GET['groups'] ) ) {
        $filter['group_ids'] = array_map( 'intval', explode( ',', sanitize_text_field( $_GET['groups'] ) ) );
    }
    if ( ! empty( $_GET['people'] ) ) {
        $filter['person_ids'] = array_map( 'intval', explode( ',', sanitize_text_field( $_GET['people'] ) ) );
    }
    if ( ! empty( $_GET['exclude_personal'] ) ) {
        $filter['exclude_personal'] = true;
    }

    // Collect plugin options (option_xxx parameters)
    $filter['options'] = array();
    foreach ( $_GET as $key => $value ) {
        if ( strpos( $key, 'option_' ) === 0 && $value === '1' ) {
            $option_id = substr( $key, 7 ); // Remove 'option_' prefix
            $filter['options'][ sanitize_key( $option_id ) ] = true;
        }
    }

    $exporter = new ExportImport( $crm );
    $content = $exporter->export_to_jsonl( $filter );

    $suffix = empty( $filter ) ? '' : '-partial';
    $filename = 'personal-crm-export' . $suffix . '-' . date( 'Y-m-d-His' ) . '.jsonl';

    header( 'Content-Type: application/jsonl' );
    header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
    header( 'Content-Length: ' . strlen( $content ) );
    header( 'Cache-Control: no-cache, no-store, must-revalidate' );
    header( 'Pragma: no-cache' );
    header( 'Expires: 0' );

    echo $content;
    exit;
}

// Get data for display
$exporter = new ExportImport( $crm );
$table_counts = $exporter->get_table_counts();
$total_records = array_sum( $table_counts );
$groups_tree = $exporter->get_groups_for_export_selection();
$all_people = $exporter->get_people_for_export_selection();
$ungrouped_people = $exporter->get_ungrouped_people();

$download_url = add_query_arg(
    'nonce',
    wp_create_nonce( 'personal_crm_export' ),
    home_url( '/crm/admin/export?download=1' )
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

        .export-tree {
            border: 1px solid light-dark(#e0e0e0, #333);
            border-radius: 8px;
            max-height: 400px;
            overflow-y: auto;
            margin-bottom: 24px;
        }
        .tree-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            background: light-dark(#f8f8f8, #2a2a2a);
            border-bottom: 1px solid light-dark(#e0e0e0, #333);
            position: sticky;
            top: 0;
            z-index: 1;
        }
        .tree-header label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            font-weight: 500;
        }
        .tree-actions {
            display: flex;
            gap: 12px;
            font-size: 13px;
        }
        .tree-actions button {
            background: none;
            border: none;
            color: #0073aa;
            cursor: pointer;
            padding: 0;
            font-size: inherit;
        }
        .tree-actions button:hover {
            text-decoration: underline;
        }
        .tree-content {
            padding: 8px 0;
        }
        .tree-group {
            user-select: none;
        }
        .tree-group-header {
            display: flex;
            align-items: center;
            padding: 8px 16px;
            gap: 8px;
            cursor: pointer;
        }
        .tree-group-header:hover {
            background: light-dark(#f5f5f5, #2a2a2a);
        }
        .tree-toggle {
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: light-dark(#666, #999);
            flex-shrink: 0;
            font-size: 12px;
            transition: transform 0.15s;
        }
        .tree-toggle.expanded {
            transform: rotate(90deg);
        }
        .tree-toggle.empty {
            visibility: hidden;
        }
        .tree-checkbox {
            width: 16px;
            height: 16px;
            cursor: pointer;
            flex-shrink: 0;
        }
        .tree-icon {
            flex-shrink: 0;
        }
        .tree-label {
            flex-grow: 1;
            font-size: 14px;
        }
        .tree-count {
            font-size: 12px;
            color: light-dark(#666, #999);
            background: light-dark(#f0f0f0, #333);
            padding: 2px 8px;
            border-radius: 10px;
            flex-shrink: 0;
        }
        .tree-children {
            display: none;
            padding-left: 20px;
        }
        .tree-children.expanded {
            display: block;
        }
        .tree-person {
            display: flex;
            align-items: center;
            padding: 6px 16px 6px 36px;
            gap: 8px;
        }
        .tree-person:hover {
            background: light-dark(#f5f5f5, #2a2a2a);
        }
        .tree-person .tree-label {
            font-size: 13px;
            color: light-dark(#555, #bbb);
        }
        .tree-subgroup {
            padding-left: 20px;
        }
        .selection-summary {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            background: light-dark(#e8f4fc, #1a2a3a);
            border-radius: 8px;
            margin-bottom: 24px;
            font-size: 14px;
        }
        .selection-summary .count {
            font-weight: 500;
        }
        .selection-summary button {
            background: none;
            border: none;
            color: #0073aa;
            cursor: pointer;
            padding: 0;
            font-size: inherit;
        }
        .selection-summary button:hover {
            text-decoration: underline;
        }
        .ungrouped-section {
            border-top: 1px solid light-dark(#e0e0e0, #333);
            margin-top: 8px;
            padding-top: 8px;
        }
        .export-options {
            margin-bottom: 24px;
            padding: 16px;
            background: light-dark(#f8f8f8, #2a2a2a);
            border-radius: 8px;
        }
        .export-option {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            font-size: 14px;
        }
    </style>
</head>
<body class="wp-app-body">
    <?php if ( function_exists( '\wp_app_body_open' ) ) \wp_app_body_open(); ?>

    <div class="export-container">
        <div class="export-header">
            <h1>Export Data</h1>
            <p>Download your CRM data as a JSONL file for backup or migration.</p>
        </div>

        <div class="export-card">
            <h2>Select Data to Export</h2>

            <div class="selection-summary" id="selection-summary">
                <span class="count" id="selection-count">All data selected (<?php echo number_format( $total_records ); ?> records)</span>
                <button type="button" id="clear-selection">Clear selection</button>
            </div>

            <div class="export-tree" id="export-tree">
                <div class="tree-header">
                    <label>
                        <input type="checkbox" id="select-all" class="tree-checkbox" checked>
                        <span>Select All</span>
                    </label>
                    <div class="tree-actions">
                        <button type="button" id="expand-all">Expand all</button>
                        <button type="button" id="collapse-all">Collapse all</button>
                    </div>
                </div>
                <div class="tree-content">
                    <?php
                    $shown_people = array();
                    $child_group_ids = array();
                    foreach ( $groups_tree as $g ) {
                        foreach ( $g['children'] ?? array() as $child ) {
                            $child_group_ids[] = $child['id'];
                        }
                    }
                    ?>
                    <?php foreach ( $groups_tree as $group ) : ?>
                        <?php
                        $has_children = ! empty( $group['children'] );
                        $group_people = array_filter( $all_people, function( $p ) use ( $group, $child_group_ids ) {
                            if ( ! in_array( $group['id'], $p['group_ids'], true ) ) {
                                return false;
                            }
                            foreach ( $p['group_ids'] as $gid ) {
                                if ( in_array( $gid, $child_group_ids, true ) ) {
                                    return false;
                                }
                            }
                            return true;
                        });
                        $has_content = $has_children || ! empty( $group_people );
                        ?>
                        <div class="tree-group" data-group-id="<?php echo esc_attr( $group['id'] ); ?>">
                            <div class="tree-group-header">
                                <span class="tree-toggle <?php echo $has_content ? '' : 'empty'; ?>">▶</span>
                                <input type="checkbox" class="tree-checkbox group-checkbox" data-group-id="<?php echo esc_attr( $group['id'] ); ?>" checked>
                                <?php if ( $group['display_icon'] ) : ?>
                                    <span class="tree-icon"><?php echo esc_html( $group['display_icon'] ); ?></span>
                                <?php endif; ?>
                                <span class="tree-label"><?php echo esc_html( $group['group_name'] ); ?></span>
                                <span class="tree-count"><?php echo (int) $group['member_count']; ?></span>
                            </div>
                            <?php if ( $has_content ) : ?>
                                <div class="tree-children">
                                    <?php foreach ( $group['children'] as $child ) : ?>
                                        <?php
                                        $child_people = array_filter( $all_people, function( $p ) use ( $child, &$shown_people ) {
                                            if ( in_array( $p['id'], $shown_people, true ) ) {
                                                return false;
                                            }
                                            return in_array( $child['id'], $p['group_ids'], true );
                                        });
                                        foreach ( $child_people as $p ) {
                                            $shown_people[] = $p['id'];
                                        }
                                        ?>
                                        <div class="tree-group tree-subgroup" data-group-id="<?php echo esc_attr( $child['id'] ); ?>" data-parent-id="<?php echo esc_attr( $group['id'] ); ?>">
                                            <div class="tree-group-header">
                                                <span class="tree-toggle <?php echo empty( $child_people ) ? 'empty' : ''; ?>">▶</span>
                                                <input type="checkbox" class="tree-checkbox group-checkbox" data-group-id="<?php echo esc_attr( $child['id'] ); ?>" data-parent-id="<?php echo esc_attr( $group['id'] ); ?>" checked>
                                                <?php if ( $child['display_icon'] ) : ?>
                                                    <span class="tree-icon"><?php echo esc_html( $child['display_icon'] ); ?></span>
                                                <?php endif; ?>
                                                <span class="tree-label"><?php echo esc_html( $child['group_name'] ); ?></span>
                                                <span class="tree-count"><?php echo (int) $child['member_count']; ?></span>
                                            </div>
                                            <?php if ( ! empty( $child_people ) ) : ?>
                                                <div class="tree-children">
                                                    <?php foreach ( $child_people as $person ) : ?>
                                                        <div class="tree-person" data-person-id="<?php echo esc_attr( $person['id'] ); ?>">
                                                            <input type="checkbox" class="tree-checkbox person-checkbox" data-person-id="<?php echo esc_attr( $person['id'] ); ?>" data-group-ids="<?php echo esc_attr( implode( ',', $person['group_ids'] ) ); ?>" checked>
                                                            <span class="tree-label"><?php echo esc_html( $person['name'] ?: $person['username'] ); ?></span>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php
                                    $group_people = array_filter( $group_people, function( $p ) use ( &$shown_people ) {
                                        if ( in_array( $p['id'], $shown_people, true ) ) {
                                            return false;
                                        }
                                        $shown_people[] = $p['id'];
                                        return true;
                                    });
                                    ?>
                                    <?php foreach ( $group_people as $person ) : ?>
                                        <div class="tree-person" data-person-id="<?php echo esc_attr( $person['id'] ); ?>">
                                            <input type="checkbox" class="tree-checkbox person-checkbox" data-person-id="<?php echo esc_attr( $person['id'] ); ?>" data-group-ids="<?php echo esc_attr( implode( ',', $person['group_ids'] ) ); ?>" checked>
                                            <span class="tree-label"><?php echo esc_html( $person['name'] ?: $person['username'] ); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                    <?php if ( ! empty( $ungrouped_people ) ) : ?>
                        <div class="ungrouped-section">
                            <div class="tree-group" data-group-id="ungrouped">
                                <div class="tree-group-header">
                                    <span class="tree-toggle">▶</span>
                                    <input type="checkbox" class="tree-checkbox" id="ungrouped-checkbox" checked>
                                    <span class="tree-label" style="font-style: italic;">Ungrouped People</span>
                                    <span class="tree-count"><?php echo count( $ungrouped_people ); ?></span>
                                </div>
                                <div class="tree-children">
                                    <?php foreach ( $ungrouped_people as $person ) : ?>
                                        <div class="tree-person" data-person-id="<?php echo esc_attr( $person['id'] ); ?>">
                                            <input type="checkbox" class="tree-checkbox person-checkbox ungrouped-person" data-person-id="<?php echo esc_attr( $person['id'] ); ?>" checked>
                                            <span class="tree-label"><?php echo esc_html( $person['name'] ?: $person['username'] ); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="export-options">
                <label class="export-option">
                    <input type="checkbox" id="exclude-personal" class="tree-checkbox">
                    <span>Exclude personal data (notes, spouses, children)</span>
                </label>
                <?php
                $export_options = apply_filters( 'personal_crm_export_options', array() );
                foreach ( $export_options as $option_id => $option ) :
                    $label = $option['label'] ?? '';
                    $default = $option['default'] ?? false;
                ?>
                <label class="export-option">
                    <input type="checkbox" id="export-option-<?php echo esc_attr( $option_id ); ?>" class="tree-checkbox export-plugin-option" data-option-id="<?php echo esc_attr( $option_id ); ?>" <?php checked( $default ); ?>>
                    <span><?php echo esc_html( $label ); ?></span>
                </label>
                <?php endforeach; ?>
            </div>

            <div class="export-actions">
                <a href="<?php echo esc_url( $download_url ); ?>" class="btn btn-primary" id="download-btn">
                    Download Export (.jsonl)
                </a>
            </div>

            <div class="info-box">
                <h3>About Exports</h3>
                <p>
                    Exports include selected groups, people, and all their related data (notes, events, links).
                    Parent groups are automatically included to preserve hierarchy. Format: JSONL (JSON Lines).
                    To restore, use the Import function on the <a href="<?php echo home_url( '/crm/welcome' ); ?>">Welcome page</a>.
                </p>
            </div>
        </div>

        <div class="back-link">
            <a href="<?php echo home_url( '/crm/' ); ?>">← Back to Dashboard</a>
        </div>
    </div>

    <script>
    (function() {
        const baseUrl = <?php echo wp_json_encode( $download_url ); ?>;
        const totalRecords = <?php echo (int) $total_records; ?>;

        const selectAllCheckbox = document.getElementById('select-all');
        const downloadBtn = document.getElementById('download-btn');
        const selectionCount = document.getElementById('selection-count');
        const clearBtn = document.getElementById('clear-selection');
        const expandAllBtn = document.getElementById('expand-all');
        const collapseAllBtn = document.getElementById('collapse-all');
        const excludePersonalCheckbox = document.getElementById('exclude-personal');

        // Toggle tree nodes
        document.querySelectorAll('.tree-toggle:not(.empty)').forEach(toggle => {
            toggle.addEventListener('click', (e) => {
                e.stopPropagation();
                const group = toggle.closest('.tree-group');
                const children = group.querySelector('.tree-children');
                if (children) {
                    children.classList.toggle('expanded');
                    toggle.classList.toggle('expanded');
                }
            });
        });

        // Click on header expands/collapses
        document.querySelectorAll('.tree-group-header').forEach(header => {
            header.addEventListener('click', (e) => {
                if (e.target.type === 'checkbox') return;
                const toggle = header.querySelector('.tree-toggle:not(.empty)');
                if (toggle) toggle.click();
            });
        });

        // Expand/collapse all
        expandAllBtn.addEventListener('click', () => {
            document.querySelectorAll('.tree-children').forEach(c => c.classList.add('expanded'));
            document.querySelectorAll('.tree-toggle:not(.empty)').forEach(t => t.classList.add('expanded'));
        });

        collapseAllBtn.addEventListener('click', () => {
            document.querySelectorAll('.tree-children').forEach(c => c.classList.remove('expanded'));
            document.querySelectorAll('.tree-toggle').forEach(t => t.classList.remove('expanded'));
        });

        // Select all checkbox - only affects tree content, not export options
        selectAllCheckbox.addEventListener('change', () => {
            const checked = selectAllCheckbox.checked;
            document.querySelectorAll('.tree-content .tree-checkbox').forEach(cb => {
                cb.checked = checked;
            });
            updateDownloadUrl();
        });

        // Group checkbox changes
        document.querySelectorAll('.group-checkbox').forEach(cb => {
            cb.addEventListener('change', () => {
                const groupId = cb.dataset.groupId;
                const checked = cb.checked;
                const group = cb.closest('.tree-group');

                // Select/deselect all children (subgroups and people)
                group.querySelectorAll('.tree-checkbox').forEach(childCb => {
                    if (childCb !== cb) childCb.checked = checked;
                });

                updateSelectAll();
                updateDownloadUrl();
            });
        });

        // Person checkbox changes
        document.querySelectorAll('.person-checkbox').forEach(cb => {
            cb.addEventListener('change', () => {
                updateSelectAll();
                updateDownloadUrl();
            });
        });

        // Ungrouped checkbox
        const ungroupedCb = document.getElementById('ungrouped-checkbox');
        if (ungroupedCb) {
            ungroupedCb.addEventListener('change', () => {
                const checked = ungroupedCb.checked;
                document.querySelectorAll('.ungrouped-person').forEach(cb => cb.checked = checked);
                updateSelectAll();
                updateDownloadUrl();
            });
        }

        // Clear selection - only clears tree content, not export options
        clearBtn.addEventListener('click', () => {
            selectAllCheckbox.checked = false;
            document.querySelectorAll('.tree-content .tree-checkbox').forEach(cb => cb.checked = false);
            updateDownloadUrl();
        });

        // Exclude personal data option
        excludePersonalCheckbox.addEventListener('change', updateDownloadUrl);

        // Plugin export options
        document.querySelectorAll('.export-plugin-option').forEach(cb => {
            cb.addEventListener('change', updateDownloadUrl);
        });

        function updateSelectAll() {
            const allCheckboxes = document.querySelectorAll('.tree-content .tree-checkbox');
            const allChecked = Array.from(allCheckboxes).every(cb => cb.checked);
            selectAllCheckbox.checked = allChecked;
        }

        function updateDownloadUrl() {
            const selectedGroups = [];
            const selectedPeople = [];
            const allGroups = document.querySelectorAll('.group-checkbox');
            const allPeople = document.querySelectorAll('.person-checkbox');

            let allGroupsChecked = true;
            let allPeopleChecked = true;

            allGroups.forEach(cb => {
                if (cb.checked) {
                    selectedGroups.push(cb.dataset.groupId);
                } else {
                    allGroupsChecked = false;
                }
            });

            allPeople.forEach(cb => {
                if (cb.checked) {
                    selectedPeople.push(cb.dataset.personId);
                } else {
                    allPeopleChecked = false;
                }
            });

            const excludePersonal = excludePersonalCheckbox.checked;

            // If all selected and no exclusions, use base URL (export all)
            if (allGroupsChecked && allPeopleChecked && !excludePersonal) {
                downloadBtn.href = baseUrl;
                selectionCount.textContent = `All data selected (${totalRecords.toLocaleString()} records)`;
                downloadBtn.classList.remove('btn-secondary');
                downloadBtn.classList.add('btn-primary');
                return;
            }

            // Build filtered URL
            const url = new URL(baseUrl);
            if (selectedGroups.length > 0) {
                url.searchParams.set('groups', selectedGroups.join(','));
            }
            if (selectedPeople.length > 0) {
                url.searchParams.set('people', selectedPeople.join(','));
            }
            if (excludePersonal) {
                url.searchParams.set('exclude_personal', '1');
            }

            // Add plugin options
            document.querySelectorAll('.export-plugin-option').forEach(cb => {
                if (cb.checked) {
                    url.searchParams.set('option_' + cb.dataset.optionId, '1');
                }
            });

            downloadBtn.href = url.toString();

            // Update count display
            const groupCount = selectedGroups.length;
            const peopleCount = selectedPeople.length;

            if (groupCount === 0 && peopleCount === 0) {
                selectionCount.textContent = 'Nothing selected';
                downloadBtn.classList.add('btn-secondary');
                downloadBtn.classList.remove('btn-primary');
            } else {
                const parts = [];
                if (groupCount > 0) parts.push(`${groupCount} group${groupCount !== 1 ? 's' : ''}`);
                if (peopleCount > 0) parts.push(`${peopleCount} ${peopleCount !== 1 ? 'people' : 'person'}`);
                selectionCount.textContent = parts.join(', ') + ' selected';
                downloadBtn.classList.remove('btn-secondary');
                downloadBtn.classList.add('btn-primary');
            }
        }
    })();
    </script>

    <?php if ( function_exists( '\wp_app_footer' ) ) \wp_app_footer(); ?>
</body>
</html>
