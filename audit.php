<?php
/**
 * Audit Page - Shows missing data points for team members
 * 
 * Helps identify which people are missing key information
 */

// Include common functions
require_once __DIR__ . '/includes/common.php';

// Get current team from URL parameter
$current_team = $_GET['team'] ?? get_default_team();
$privacy_mode = isset( $_GET['privacy'] ) && $_GET['privacy'] === '1';

// Load team configuration
$team_data = load_team_config( $current_team );

/**
 * Check which data points are missing for a person
 */
function get_missing_data_points( $person, $person_type = 'member' ) {
	$missing = array();
	
	// Core fields
	if ( empty( $person->name ) ) {
		$missing[] = 'Name';
	}
	if ( empty( $person->role ) ) {
		$missing[] = 'Role';
	}
	if ( empty( $person->location ) ) {
		$missing[] = 'Location';
	}
	if ( empty( $person->timezone ) ) {
		$missing[] = 'Timezone';
	}
	
	// Birthday
	if ( empty( $person->birthday ) ) {
		$missing[] = 'Birthday';
	}
	
	// Company anniversary
	if ( empty( $person->company_anniversary ) ) {
		$missing[] = 'Company Anniversary';
	}
	
	// Links - check for key links
	$expected_links = array();
	if ( $person_type === 'member' ) {
		$expected_links = array( '1:1 doc', 'HR monthly' );
	} elseif ( $person_type === 'leader' || $person_type === 'alumni' ) {
		$expected_links = array( '1:1 doc' );
	}
	
	foreach ( $expected_links as $expected_link ) {
		if ( ! isset( $person->links[ $expected_link ] ) || empty( $person->links[ $expected_link ] ) ) {
			$missing[] = $expected_link . ' link';
		}
	}
	
	// Optional fields that are nice to have
	if ( empty( $person->partner ) ) {
		$missing[] = 'Partner (optional)';
	}
	if ( empty( $person->kids ) ) {
		$missing[] = 'Kids info (optional)';
	}
	if ( empty( $person->notes ) ) {
		$missing[] = 'Notes (optional)';
	}
	
	return $missing;
}

/**
 * Get completeness score as percentage
 */
function get_completeness_score( $missing_data, $person_type = 'member' ) {
	// Core required fields
	$total_core_fields = 6; // name, role, location, timezone, birthday, company_anniversary
	if ( $person_type === 'member' ) {
		$total_core_fields += 2; // 1:1 doc, HR monthly links
	} else {
		$total_core_fields += 1; // 1:1 doc link
	}
	
	// Count missing core fields (exclude optional ones)
	$missing_core = 0;
	foreach ( $missing_data as $missing_item ) {
		if ( strpos( $missing_item, 'optional' ) === false ) {
			$missing_core++;
		}
	}
	
	$completed_core = $total_core_fields - $missing_core;
	$score = round( ( $completed_core / $total_core_fields ) * 100 );
	
	return max( 0, $score );
}

// Get audit data for all people
$audit_data = array();

// Team members
foreach ( $team_data['team_members'] as $username => $member ) {
	$missing = get_missing_data_points( $member, 'member' );
	$score = get_completeness_score( $missing, 'member' );
	$audit_data[] = array(
		'type' => 'Team Member',
		'name' => $member->name,
		'username' => $username,
		'missing' => $missing,
		'score' => $score,
		'person' => $member
	);
}

// Leadership
foreach ( $team_data['leadership'] as $username => $leader ) {
	$missing = get_missing_data_points( $leader, 'leader' );
	$score = get_completeness_score( $missing, 'leader' );
	$audit_data[] = array(
		'type' => 'Leadership',
		'name' => $leader->name,
		'username' => $username,
		'missing' => $missing,
		'score' => $score,
		'person' => $leader
	);
}

// Alumni
foreach ( $team_data['alumni'] as $username => $alumnus ) {
	$missing = get_missing_data_points( $alumnus, 'alumni' );
	$score = get_completeness_score( $missing, 'alumni' );
	$audit_data[] = array(
		'type' => 'Alumni',
		'name' => $alumnus->name,
		'username' => $username,
		'missing' => $missing,
		'score' => $score,
		'person' => $alumnus
	);
}

// Sort by completeness score (lowest first to prioritize fixes)
usort( $audit_data, function( $a, $b ) {
	if ( $a['score'] === $b['score'] ) {
		return strcasecmp( $a['name'], $b['name'] );
	}
	return $a['score'] <=> $b['score'];
} );

// Get available teams for switcher
$available_teams = get_available_teams();

?>
<!DOCTYPE html>
<html <?php echo function_exists( 'wp_app_language_attributes' ) ? wp_app_language_attributes() : 'lang="en"'; ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <title><?php echo function_exists( 'wp_app_title' ) ? wp_app_title( htmlspecialchars( $team_data['team_name'] ) . ' Team Audit' ) : htmlspecialchars( $team_data['team_name'] ) . ' Team Audit'; ?></title>
    <?php
    if ( function_exists( 'wp_app_enqueue_style' ) ) {
        wp_app_enqueue_style( 'a8c-hr-cmd-k', 'assets/cmd-k.css' );
    } else {
        echo '<link rel="stylesheet" href="assets/cmd-k.css">';
    }
    ?>
    <?php if ( function_exists( 'wp_app_head' ) ) wp_app_head(); ?>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f8f9fa;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .navigation {
            margin: 0;
        }
        .nav-link {
            display: inline-block;
            margin-left: 10px;
            padding: 8px 16px;
            background: #007cba;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            transition: background 0.3s;
            font-size: 14px;
        }
        .nav-link:hover {
            background: #005a87;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #007cba;
            margin-bottom: 5px;
        }
        .stat-label {
            color: #666;
            font-size: 14px;
        }
        .audit-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .audit-table th,
        .audit-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        .audit-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        .audit-table tr:hover {
            background: #f8f9fa;
        }
        .score-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 12px;
            min-width: 40px;
            text-align: center;
        }
        .score-excellent { background: #d4edda; color: #155724; }
        .score-good { background: #d1ecf1; color: #0c5460; }
        .score-fair { background: #fff3cd; color: #856404; }
        .score-poor { background: #f8d7da; color: #721c24; }
        .missing-items {
            font-size: 13px;
        }
        .missing-core {
            color: #dc3545;
            font-weight: 500;
        }
        .missing-optional {
            color: #6c757d;
        }
        .person-name {
            font-weight: 600;
        }
        .person-type {
            font-size: 12px;
            color: #666;
            margin-left: 8px;
        }
        .edit-link {
            color: #007cba;
            text-decoration: none;
            font-size: 12px;
        }
        .edit-link:hover {
            text-decoration: underline;
        }
        .filters {
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .filter-label {
            margin-right: 15px;
            font-weight: 600;
        }
        .filter-select {
            padding: 6px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            margin-right: 15px;
        }
    </style>
</head>
<body class="wp-app-body">
    <?php if ( function_exists( 'wp_app_body_open' ) ) wp_app_body_open(); ?>
    <?php render_cmd_k_panel(); ?>
    <div class="container">
        <div class="header">
            <div style="flex-grow: 1;">
                <h1><a href="<?php echo build_team_url( 'audit.php' ); ?>" style="color: inherit; text-decoration: none;">📊 <?php echo htmlspecialchars( $team_data['team_name'] ); ?> Team Audit</a></h1>
                <p style="color: #666; margin: 5px 0 0 0; font-size: 14px;">Identify missing data points and improve team profiles</p>
            </div>
            <div class="navigation" style="display: flex; align-items: center; gap: 10px;">
                <!-- Team Switcher -->
                <select id="team-selector" onchange="switchTeam()">
                    <?php
                    foreach ( $available_teams as $team_slug ) {
                        $team_display_name = get_team_name_from_file( $team_slug );
                        $selected = $team_slug === $current_team ? 'selected' : '';
                        echo '<option value="' . htmlspecialchars( $team_slug ) . '" ' . $selected . '>' . htmlspecialchars( $team_display_name ) . '</option>';
                    }
                    ?>
                </select>
                
                <?php if ( $privacy_mode ) : ?>
                    <a href="?<?php echo http_build_query( array_merge( $_GET, array( 'privacy' => '0' ) ) ); ?>" class="nav-link" style="background: #28a745;">🔒 Privacy Mode ON</a>
                <?php else : ?>
                    <a href="?<?php echo http_build_query( array_merge( $_GET, array( 'privacy' => '1' ) ) ); ?>" class="nav-link" style="background: #dc3545;">🔓 Privacy Mode OFF</a>
                <?php endif; ?>
                <a href="<?php echo build_team_url( 'index.php' ); ?>" class="nav-link">👥 Team Overview</a>
                <a href="<?php echo build_team_url( 'admin.php' ); ?>" class="nav-link">⚙️ Admin Panel</a>
            </div>
        </div>

        <?php
        // Calculate statistics
        $total_people = count( $audit_data );
        $complete_profiles = count( array_filter( $audit_data, function( $item ) { return $item['score'] >= 90; } ) );
        $needs_attention = count( array_filter( $audit_data, function( $item ) { return $item['score'] < 70; } ) );
        $avg_score = $total_people > 0 ? round( array_sum( array_column( $audit_data, 'score' ) ) / $total_people ) : 0;
        ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_people; ?></div>
                <div class="stat-label">Total People</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $avg_score; ?>%</div>
                <div class="stat-label">Average Completeness</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $complete_profiles; ?></div>
                <div class="stat-label">Complete Profiles (90%+)</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $needs_attention; ?></div>
                <div class="stat-label">Needs Attention (&lt;70%)</div>
            </div>
        </div>

        <div class="filters">
            <span class="filter-label">Filter by:</span>
            <select class="filter-select" id="type-filter" onchange="filterTable()">
                <option value="">All Types</option>
                <option value="Team Member">Team Members</option>
                <option value="Leadership">Leadership</option>
                <option value="Alumni">Alumni</option>
            </select>
            <select class="filter-select" id="score-filter" onchange="filterTable()">
                <option value="">All Scores</option>
                <option value="poor">Poor (&lt;50%)</option>
                <option value="fair">Fair (50-79%)</option>
                <option value="good">Good (80-89%)</option>
                <option value="excellent">Excellent (90%+)</option>
            </select>
        </div>

        <table class="audit-table" id="audit-table">
            <thead>
                <tr>
                    <th>Person</th>
                    <th>Type</th>
                    <th>Completeness</th>
                    <th>Missing Data Points</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $audit_data as $item ) : ?>
                    <tr data-type="<?php echo htmlspecialchars( $item['type'] ); ?>" data-score="<?php echo $item['score']; ?>">
                        <td>
                            <div class="person-name">
                                <?php echo htmlspecialchars( mask_name( $item['name'], $privacy_mode ) ); ?>
                            </div>
                            <div style="font-size: 12px; color: #666;">
                                @<?php echo htmlspecialchars( mask_username( $item['username'], $privacy_mode ) ); ?>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars( $item['type'] ); ?></td>
                        <td>
                            <span class="score-badge <?php
                                if ( $item['score'] >= 90 ) echo 'score-excellent';
                                elseif ( $item['score'] >= 80 ) echo 'score-good';
                                elseif ( $item['score'] >= 50 ) echo 'score-fair';
                                else echo 'score-poor';
                            ?>"><?php echo $item['score']; ?>%</span>
                        </td>
                        <td class="missing-items">
                            <?php if ( empty( $item['missing'] ) ) : ?>
                                <span style="color: #28a745; font-weight: 500;">✅ Complete</span>
                            <?php else : ?>
                                <?php foreach ( $item['missing'] as $missing_item ) : ?>
                                    <span class="<?php echo strpos( $missing_item, 'optional' ) !== false ? 'missing-optional' : 'missing-core'; ?>">
                                        <?php echo htmlspecialchars( $missing_item ); ?>
                                    </span><?php echo $missing_item !== end( $item['missing'] ) ? ', ' : ''; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?php echo build_team_url( 'admin.php', array( 'edit_member' => $item['username'] ) ); ?>" class="edit-link">✏️ Edit</a>
                            <a href="<?php echo build_team_url( 'index.php', array( 'person' => $item['username'] ) ); ?>" class="edit-link" style="margin-left: 8px;">👁️ View</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script>
        // Team switching functionality
        function switchTeam() {
            const selector = document.getElementById('team-selector');
            const selectedTeam = selector.value;
            const currentUrl = new URL(window.location);
            currentUrl.searchParams.set('team', selectedTeam);
            window.location = currentUrl.toString();
        }

        // Filter functionality
        function filterTable() {
            const typeFilter = document.getElementById('type-filter').value;
            const scoreFilter = document.getElementById('score-filter').value;
            const table = document.getElementById('audit-table');
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const type = row.getAttribute('data-type');
                const score = parseInt(row.getAttribute('data-score'));
                
                let showRow = true;
                
                // Filter by type
                if (typeFilter && type !== typeFilter) {
                    showRow = false;
                }
                
                // Filter by score range
                if (scoreFilter) {
                    if (scoreFilter === 'poor' && score >= 50) showRow = false;
                    if (scoreFilter === 'fair' && (score < 50 || score >= 80)) showRow = false;
                    if (scoreFilter === 'good' && (score < 80 || score >= 90)) showRow = false;
                    if (scoreFilter === 'excellent' && score < 90) showRow = false;
                }
                
                row.style.display = showRow ? '' : 'none';
            }
        }
    </script>
    <script src="assets/cmd-k.js"></script>
    <script src="assets/script.js"></script>
    <?php init_cmd_k_js( $privacy_mode ); ?>
</body>
</html>