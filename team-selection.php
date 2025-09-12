<?php
/**
 * Team Selection Screen
 * 
 * Allows users to select which team they want to view when multiple teams exist
 */

// Include common functions
require_once __DIR__ . '/includes/common.php';

$available_teams = get_available_teams();

// If there's only one team or no teams, redirect appropriately
if ( empty( $available_teams ) ) {
	header( 'Location: admin.php?create_team=new' );
	exit;
} elseif ( count( $available_teams ) === 1 ) {
	header( 'Location: ./' );
	exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <title>Team Selection - Orbit Team Management</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/cmd-k.css">
</head>
<body>
    <?php render_cmd_k_panel(); ?>
    <?php render_dark_mode_toggle(); ?>

    <div class="team-selection-container">
        <div class="team-selection-header">
            <h1>Select a Team</h1>
            <p>Choose which team you'd like to view:</p>
        </div>

        <div class="team-grid">
            <?php foreach ( $available_teams as $team_slug ) : ?>
                <?php 
                $team_name = get_team_name_from_file( $team_slug ); 
                $team_type = get_team_type_from_file( $team_slug );
                $param_name = ( $team_type === 'group' ) ? 'group' : 'team';
                ?>
                <a href="index.php<?php if ( get_default_team() !== $team_slug ) echo '?' . $param_name . '=' . urlencode( $team_slug ); ?>" class="team-card">
                    <h3><?php echo htmlspecialchars( $team_name ); ?></h3>
                    <p><?php echo htmlspecialchars( $team_slug ); ?>.json</p>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="admin-link-section">
            <a href="admin.php?create_team=new">⚙️ Create New Team</a>
        </div>
    </div>
    
    <script src="assets/cmd-k.js"></script>
    <script src="assets/script.js"></script>
    <?php init_cmd_k_js(); ?>
</body>
</html>