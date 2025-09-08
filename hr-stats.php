<?php
/**
 * HR Feedback Statistics Dashboard
 * 
 * Shows comprehensive statistics about HR feedback across the team.
 */

require_once __DIR__ . '/includes/common.php';
require_once __DIR__ . '/includes/person.php';

$current_team = $_GET['team'] ?? get_default_team();
$privacy_mode = isset( $_GET['privacy'] ) && $_GET['privacy'] === '1';
$performance_filter = $_GET['performance'] ?? null;
$team_data = load_team_config_with_objects( $current_team, $privacy_mode );

// Get all team members who need HR feedback
$team_members = array_filter( $team_data['team_members'], function( $person ) {
    return $person->needs_hr_monthly;
} );

// Get available teams for switcher
$available_teams = get_available_teams();

// Collect all feedback data
$all_feedback = array();
$feedback_stats = array(
    'total_people' => count( $team_members ),
    'total_feedback_entries' => 0,
    'months_with_data' => array(),
    'performance_distribution' => array(
        'high' => 0,
        'good' => 0,
        'low' => 0
    ),
    'completion_rates' => array(),
    'people_with_feedback' => 0
);

foreach ( $team_members as $username => $person ) {
    $feedback_history = get_person_feedback_history( $username );
    if ( ! empty( $feedback_history ) ) {
        $feedback_stats['people_with_feedback']++;
        $all_feedback[ $username ] = $feedback_history;
        
        foreach ( $feedback_history as $month => $feedback ) {
            if ( $month === 'hr_monthly_link' || ! is_array( $feedback ) ) {
                continue;
            }
            
            $feedback_stats['total_feedback_entries']++;
            $feedback_stats['months_with_data'][ $month ] = true;
            
            // Count performance ratings
            $performance = $feedback['performance'] ?? 'good';
            if ( isset( $feedback_stats['performance_distribution'][ $performance ] ) ) {
                $feedback_stats['performance_distribution'][ $performance ]++;
            }
        }
    }
}

// Calculate monthly completion rates
$current_month = get_hr_feedback_month();
$feedback_stats['months_with_data'] = array_keys( $feedback_stats['months_with_data'] );
rsort( $feedback_stats['months_with_data'] ); // Sort newest first

foreach ( $feedback_stats['months_with_data'] as $month ) {
    if ( $month === $current_month ) continue; // Skip current month as it might not be complete
    
    $completed_this_month = 0;
    foreach ( $team_members as $username => $person ) {
        $feedback_history = get_person_feedback_history( $username );
        if ( isset( $feedback_history[ $month ] ) && is_array( $feedback_history[ $month ] ) ) {
            $completed_this_month++;
        }
    }
    
    $completion_rate = $feedback_stats['total_people'] > 0 ? 
        round( ( $completed_this_month / $feedback_stats['total_people'] ) * 100 ) : 0;
    
    $feedback_stats['completion_rates'][ $month ] = array(
        'completed' => $completed_this_month,
        'total' => $feedback_stats['total_people'],
        'percentage' => $completion_rate
    );
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Feedback Statistics - <?php echo htmlspecialchars( $team_data['team_name'] ); ?> Team</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/hr-reports.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <div style="flex-grow: 1;">
                <h1>📊 HR Feedback Statistics</h1>
                <div style="margin-top: 5px;">
                    <a href="<?php echo build_team_url( 'index.php' ); ?>" style="color: #666; text-decoration: none; font-size: 14px;">← Back to Team Overview</a>
                </div>
            </div>
            <div class="navigation" style="display: flex; align-items: center; gap: 10px;">
                <!-- Team Switcher -->
                <select id="team-selector" onchange="switchTeam()" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; background: white;">
                    <?php
                    foreach ( $available_teams as $team_slug ) {
                        $team_display_name = get_team_name_from_file( $team_slug );
                        $selected = $team_slug === $current_team ? 'selected' : '';
                        echo '<option value="' . htmlspecialchars( $team_slug ) . '" ' . $selected . '>' . htmlspecialchars( $team_display_name ) . '</option>';
                    }
                    ?>
                </select>
            </div>
        </div>

        <!-- Overview Stats -->
        <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <div class="stat-card" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h3 style="margin: 0 0 10px 0; color: #333;">Team Members</h3>
                <div style="font-size: 2em; font-weight: bold; color: #007cba;">
                    <?php echo $feedback_stats['total_people']; ?>
                </div>
                <div style="font-size: 0.9em; color: #666;">requiring HR feedback</div>
            </div>

            <div class="stat-card" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h3 style="margin: 0 0 10px 0; color: #333;">Total Feedback</h3>
                <div style="font-size: 2em; font-weight: bold; color: #28a745;">
                    <?php echo $feedback_stats['total_feedback_entries']; ?>
                </div>
                <div style="font-size: 0.9em; color: #666;">feedback entries recorded</div>
            </div>

            <div class="stat-card" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h3 style="margin: 0 0 10px 0; color: #333;">Participation</h3>
                <div style="font-size: 2em; font-weight: bold; color: #17a2b8;">
                    <?php echo $feedback_stats['people_with_feedback']; ?>/<?php echo $feedback_stats['total_people']; ?>
                </div>
                <div style="font-size: 0.9em; color: #666;">people with feedback</div>
            </div>

            <div class="stat-card" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h3 style="margin: 0 0 10px 0; color: #333;">Months Tracked</h3>
                <div style="font-size: 2em; font-weight: bold; color: #6f42c1;">
                    <?php echo count( $feedback_stats['months_with_data'] ); ?>
                </div>
                <div style="font-size: 0.9em; color: #666;">months with data</div>
            </div>
        </div>

        <!-- Performance Distribution -->
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 30px;">
            <h3 style="margin: 0 0 10px 0;">Performance Distribution</h3>
            <p style="font-size: 0.9em; color: #666; margin: 0 0 20px 0;">Click on a performance level to filter individual progress below</p>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
                <?php foreach ( $feedback_stats['performance_distribution'] as $rating => $count ) : ?>
                    <?php
                    $colors = array(
                        'high' => '#28a745',
                        'good' => '#007cba', 
                        'low' => '#dc3545'
                    );
                    $color = $colors[ $rating ] ?? '#6c757d';
                    $percentage = $feedback_stats['total_feedback_entries'] > 0 ? 
                        round( ( $count / $feedback_stats['total_feedback_entries'] ) * 100 ) : 0;
                    ?>
                    <?php
                    $filter_params = $_GET;
                    $filter_params['performance'] = $rating;
                    $filter_url = '?' . http_build_query( $filter_params );
                    $is_active = $performance_filter === $rating;
                    $border_style = $is_active ? '3px solid' : '2px solid';
                    $bg_style = $is_active ? 'background: rgba(' . hexdec( substr( $color, 1, 2 ) ) . ',' . hexdec( substr( $color, 3, 2 ) ) . ',' . hexdec( substr( $color, 5, 2 ) ) . ',0.1);' : '';
                    ?>
                    <a href="<?php echo htmlspecialchars( $filter_url ); ?>" style="text-decoration: none; color: inherit;">
                        <div style="text-align: center; padding: 15px; border: <?php echo $border_style . ' ' . $color; ?>; border-radius: 8px; cursor: pointer; transition: all 0.2s ease; <?php echo $bg_style; ?>">
                            <div style="font-size: 1.5em; font-weight: bold; color: <?php echo $color; ?>;">
                                <?php echo $count; ?>
                            </div>
                            <div style="font-size: 0.9em; text-transform: capitalize; margin: 5px 0;">
                                <?php echo str_replace( '_', ' ', $rating ); ?>
                            </div>
                            <div style="font-size: 0.8em; color: #666;">
                                (<?php echo $percentage; ?>%)
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
                <!-- Clear Filter Button -->
                <?php if ( $performance_filter ) : ?>
                    <?php
                    $clear_params = $_GET;
                    unset( $clear_params['performance'] );
                    $clear_url = '?' . http_build_query( $clear_params );
                    ?>
                    <a href="<?php echo htmlspecialchars( $clear_url ); ?>" style="text-decoration: none; color: inherit;">
                        <div style="text-align: center; padding: 15px; border: 2px solid #6c757d; border-radius: 8px; cursor: pointer; transition: all 0.2s ease;">
                            <div style="font-size: 1.2em; font-weight: bold; color: #6c757d;">
                                Clear
                            </div>
                            <div style="font-size: 0.9em; margin: 5px 0;">
                                Show All
                            </div>
                            <div style="font-size: 0.8em; color: #666;">
                                Reset filter
                            </div>
                        </div>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Monthly Completion Rates -->
        <?php if ( ! empty( $feedback_stats['completion_rates'] ) ) : ?>
            <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 30px;">
                <h3 style="margin: 0 0 20px 0;">Monthly Completion Rates</h3>
                <div style="display: flex; flex-direction: column; gap: 15px;">
                    <?php foreach ( array_slice( $feedback_stats['completion_rates'], 0, 6 ) as $month => $data ) : ?>
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <div style="min-width: 100px; font-weight: 500;">
                                <?php echo date( 'M Y', strtotime( $month . '-01' ) ); ?>
                            </div>
                            <div style="flex: 1; background: #f8f9fa; border-radius: 10px; height: 20px; position: relative; overflow: hidden;">
                                <div style="background: <?php echo $data['percentage'] >= 80 ? '#28a745' : ($data['percentage'] >= 50 ? '#ffc107' : '#dc3545'); ?>; height: 100%; width: <?php echo $data['percentage']; ?>%; transition: width 0.3s ease;"></div>
                            </div>
                            <div style="min-width: 80px; text-align: right;">
                                <strong><?php echo $data['completed']; ?>/<?php echo $data['total']; ?></strong>
                                <span style="color: #666;">(<?php echo $data['percentage']; ?>%)</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Individual Progress -->
        <?php if ( ! empty( $all_feedback ) && ! $privacy_mode ) : ?>
            <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h3 style="margin: 0 0 20px 0;">
                    Individual Progress
                    <?php if ( $performance_filter ) : ?>
                        <span style="font-size: 0.8em; color: #666; font-weight: normal;">
                            (filtered by: <strong><?php echo ucfirst( $performance_filter ); ?></strong> performance)
                        </span>
                    <?php endif; ?>
                </h3>
                <div style="display: grid; gap: 15px;">
                    <?php foreach ( $team_members as $username => $person ) : ?>
                        <?php
                        $feedback_history = get_person_feedback_history( $username );
                        $feedback_count = 0;
                        $recent_performance = 'N/A';
                        
                        if ( ! empty( $feedback_history ) ) {
                            foreach ( $feedback_history as $month => $feedback ) {
                                if ( $month !== 'hr_monthly_link' && is_array( $feedback ) ) {
                                    $feedback_count++;
                                    if ( $recent_performance === 'N/A' ) {
                                        $recent_performance = ucfirst( $feedback['performance'] ?? 'good' );
                                    }
                                }
                            }
                        }
                        
                        // Skip this person if they don't match the performance filter
                        if ( $performance_filter && strtolower( $recent_performance ) !== $performance_filter ) {
                            continue;
                        }
                        
                        $performance_colors = array(
                            'High' => '#28a745',
                            'Good' => '#007cba',
                            'Low' => '#dc3545'
                        );
                        $performance_color = $performance_colors[ $recent_performance ] ?? '#6c757d';
                        ?>
                        <div style="display: flex; align-items: center; padding: 15px; border: 1px solid #dee2e6; border-radius: 8px; gap: 15px;">
                            <div style="flex: 1;">
                                <a href="<?php echo $person->get_profile_url(); ?>" style="color: #007cba; text-decoration: none; font-weight: 500;">
                                    <?php echo htmlspecialchars( $person->get_display_name_with_nickname() ); ?>
                                </a>
                                <div style="font-size: 0.9em; color: #666;">
                                    @<?php echo htmlspecialchars( $person->get_username() ); ?>
                                </div>
                            </div>
                            <div style="text-align: center;">
                                <div style="font-size: 1.2em; font-weight: bold; color: #333;">
                                    <?php echo $feedback_count; ?>
                                </div>
                                <div style="font-size: 0.8em; color: #666;">feedback entries</div>
                            </div>
                            <div style="text-align: center; min-width: 120px;">
                                <span style="padding: 4px 8px; border-radius: 12px; font-size: 0.8em; font-weight: 500; color: white; background: <?php echo $performance_color; ?>;">
                                    <?php echo $recent_performance; ?>
                                </span>
                                <div style="font-size: 0.8em; color: #666; margin-top: 2px;">recent rating</div>
                            </div>
                            <div>
                                <a href="<?php echo build_team_url( 'hr-reports.php', array( 'person' => $username, 'privacy' => $privacy_mode ? '1' : '0' ) ); ?>" 
                                   style="padding: 6px 12px; background: #007cba; color: white; border-radius: 4px; text-decoration: none; font-size: 0.9em;">
                                   View Details →
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php elseif ( $privacy_mode ) : ?>
            <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center;">
                <p style="color: #666; font-style: italic;">Individual progress hidden in privacy mode.</p>
            </div>
        <?php endif; ?>

        <footer style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee; text-align: center; font-size: 14px;">
            <?php if ( $privacy_mode ) : ?>
                <a href="?<?php echo http_build_query( array_merge( $_GET, array( 'privacy' => '0' ) ) ); ?>" style="color: #666; text-decoration: none; margin-right: 15px;">🔒 Privacy Mode ON</a>
            <?php else : ?>
                <a href="?<?php echo http_build_query( array_merge( $_GET, array( 'privacy' => '1' ) ) ); ?>" style="color: #666; text-decoration: none; margin-right: 15px;">🔓 Privacy Mode OFF</a>
            <?php endif; ?>
            <a href="<?php echo build_team_url( 'admin.php' ); ?>" style="color: #666; text-decoration: none; margin-right: 15px;">⚙️ Admin Panel</a>
            <a href="<?php echo build_team_url( 'hr-reports.php' ); ?>" style="color: #666; text-decoration: none;">📝 HR Reports</a>
        </footer>
    </div>

    <script>
        function switchTeam() {
            const selector = document.getElementById('team-selector');
            const selectedTeam = selector.value;
            const urlParams = new URLSearchParams(window.location.search);
            
            if (selectedTeam === 'team') {
                urlParams.delete('team');
            } else {
                urlParams.set('team', selectedTeam);
            }
            
            const newUrl = window.location.pathname + '?' + urlParams.toString();
            window.location.href = newUrl;
        }
    </script>
</body>
</html>