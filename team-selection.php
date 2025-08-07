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
	header( 'Location: index.php?team=' . urlencode( $available_teams[0] ) );
	exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Selection - Orbit Team Management</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f8f9fa;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        .header {
            margin-bottom: 40px;
        }
        .header h1 {
            margin: 0 0 10px 0;
            color: #333;
            font-size: 28px;
        }
        .header p {
            color: #666;
            font-size: 16px;
            margin: 0;
        }
        .team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        .team-card {
            border: 2px solid #dee2e6;
            border-radius: 8px;
            padding: 30px 20px;
            background: #fff;
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        .team-card:hover {
            border-color: #007cba;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            text-decoration: none;
            color: inherit;
            transform: translateY(-2px);
        }
        .team-card h3 {
            margin: 0 0 10px 0;
            color: #333;
            font-size: 20px;
        }
        .team-card p {
            margin: 0;
            color: #666;
            font-size: 14px;
        }
        .admin-link {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
        }
        .admin-link a {
            display: inline-block;
            padding: 10px 20px;
            background: #28a745;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            transition: background 0.3s;
        }
        .admin-link a:hover {
            background: #218838;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Select a Team</h1>
            <p>Choose which team you'd like to view:</p>
        </div>

        <div class="team-grid">
            <?php foreach ( $available_teams as $team_slug ) : ?>
                <?php $team_name = get_team_name_from_file( $team_slug ); ?>
                <a href="index.php?team=<?php echo urlencode( $team_slug ); ?>" class="team-card">
                    <h3><?php echo htmlspecialchars( $team_name ); ?></h3>
                    <p><?php echo htmlspecialchars( $team_slug ); ?>.json</p>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="admin-link">
            <a href="admin.php?create_team=new">⚙️ Create New Team</a>
        </div>
    </div>
</body>
</html>