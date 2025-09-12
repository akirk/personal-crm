<?php
/**
 * HR Feedback Statistics Dashboard
 * 
 * Shows comprehensive statistics about HR feedback across the team.
 */

require_once __DIR__ . '/includes/common.php';
require_once __DIR__ . '/includes/person.php';

$current_team = get_current_team_from_params();
if ( ! $current_team ) {
	$current_team = get_default_team();
}
$privacy_mode = isset( $_GET['privacy'] ) && $_GET['privacy'] === '1';
$performance_filter = $_GET['performance'] ?? null;
$team_data = load_team_config_with_objects( $current_team, $privacy_mode );

// Check if this team is not managing and redirect to main page
if ( isset( $team_data['not_managing_team'] ) && $team_data['not_managing_team'] ) {
	$redirect_url = 'index.php' . ( $current_team !== 'team' ? '?team=' . urlencode( $current_team ) : '' );
	header( 'Location: ' . $redirect_url );
	exit;
}

// Get all team members (they all need HR feedback by default, unless marked as "not necessary")
$team_members = $team_data['team_members'];

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

// Ensure current month is included when hr_view=current is set
if ( isset( $_GET['hr_view'] ) && $_GET['hr_view'] === 'current' ) {
	$current_month_actual = ( new DateTime() )->format('Y-m');
	$feedback_stats['months_with_data'][ $current_month_actual ] = true;
}

$feedback_stats['months_with_data'] = array_keys( $feedback_stats['months_with_data'] );
rsort( $feedback_stats['months_with_data'] ); // Sort newest first

foreach ( $feedback_stats['months_with_data'] as $month ) {
    // Skip current month as it might not be complete, unless hr_view=current is set
    if ( $month === $current_month && ! ( isset( $_GET['hr_view'] ) && $_GET['hr_view'] === 'current' ) ) continue;
    
    $completed_this_month = 0;
    $not_necessary_this_month = 0;
    
    foreach ( $team_members as $username => $person ) {
        $feedback_history = get_person_feedback_history( $username );
        
        // Check if person has "not necessary" status for this month
        if ( isset( $feedback_history[ $month . '_not_necessary' ] ) ) {
            $not_necessary_this_month++;
        } elseif ( isset( $feedback_history[ $month ] ) && is_array( $feedback_history[ $month ] ) ) {
            $completed_this_month++;
        }
    }
    
    // Calculate total people who actually needed to submit feedback
    $people_needing_feedback = $feedback_stats['total_people'] - $not_necessary_this_month;
    $completion_rate = $people_needing_feedback > 0 ? 
        round( ( $completed_this_month / $people_needing_feedback ) * 100 ) : 0;
    
    $feedback_stats['completion_rates'][ $month ] = array(
        'completed' => $completed_this_month,
        'total' => $people_needing_feedback,
        'not_necessary' => $not_necessary_this_month,
        'percentage' => $completion_rate
    );
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <title><?php echo htmlspecialchars( $team_data['team_name'] ); ?> HR Feedbacks</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/hr-reports.css">
    <link rel="stylesheet" href="assets/cmd-k.css">
</head>
<body>
    <?php render_cmd_k_panel(); ?>
    <?php render_dark_mode_toggle(); ?>

    <div class="container">
        <div class="header">
            <div style="flex-grow: 1;">
                <h1>📊 <?php echo htmlspecialchars( $team_data['team_name'] ); ?> HR Feedbacks</h1>
                <div style="margin-top: 5px;">
                    <a href="<?php echo build_team_url( 'index.php' ); ?>" style="color: #666; text-decoration: none; font-size: 14px;">← Back to Team Overview</a>
                </div>
            </div>
            <div class="navigation" style="display: flex; align-items: center; gap: 10px;">
                <?php
                // Build array of teams that are managing HR feedback
                $managing_teams = array();
                foreach ( $available_teams as $team_slug ) {
                    $team_config = load_team_config_with_objects( $team_slug, false );
                    if ( ! ( isset( $team_config['not_managing_team'] ) && $team_config['not_managing_team'] ) ) {
                        $managing_teams[] = $team_slug;
                    }
                }

                // Only show team selector if there are multiple managing teams
                if ( count( $managing_teams ) > 1 ) :
                ?>
                    <select id="team-selector" onchange="switchTeam()">
                        <?php
                        foreach ( $managing_teams as $team_slug ) {
                            $team_display_name = get_team_name_from_file( $team_slug );
                            $selected = $team_slug === $current_team ? 'selected' : '';
                            echo '<option value="' . htmlspecialchars( $team_slug ) . '" ' . $selected . '>' . htmlspecialchars( $team_display_name ) . '</option>';
                        }
                        ?>
                    </select>
                <?php endif; ?>
            </div>
        </div>

        <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <div class="stat-card">
                <h3 style="margin: 0 0 10px 0; color: #333;"><?php echo ucfirst( $group ); ?> Members</h3>
                <div class="stat-number">
                    <?php echo $feedback_stats['total_people']; ?>
                </div>
                <div style="font-size: 0.9em; color: #666;">requiring HR feedback</div>
            </div>

            <div class="stat-card">
                <h3 style="margin: 0 0 10px 0; color: #333;">Total Feedback</h3>
                <div style="font-size: 2em; font-weight: bold; color: #28a745;">
                    <?php echo $feedback_stats['total_feedback_entries']; ?>
                </div>
                <div style="font-size: 0.9em; color: #666;">feedback entries recorded</div>
            </div>

            <div class="stat-card">
                <h3 style="margin: 0 0 10px 0; color: #333;">Participation</h3>
                <div style="font-size: 2em; font-weight: bold; color: #17a2b8;">
                    <?php echo $feedback_stats['people_with_feedback']; ?>/<?php echo $feedback_stats['total_people']; ?>
                </div>
                <div style="font-size: 0.9em; color: #666;">people with feedback</div>
            </div>

            <div class="stat-card">
                <h3 style="margin: 0 0 10px 0; color: #333;">Months Tracked</h3>
                <div style="font-size: 2em; font-weight: bold; color: #6f42c1;">
                    <?php echo count( $feedback_stats['months_with_data'] ); ?>
                </div>
                <div style="font-size: 0.9em; color: #666;">months with data</div>
            </div>
        </div>

        <div class="stat-card" style="margin-bottom: 30px;">
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
            <div class="stat-card" style="margin-bottom: 30px;">
                <h3 style="margin: 0 0 20px 0;">Monthly Completion Rates</h3>
                <div style="display: flex; flex-direction: column; gap: 15px;">
                    <?php foreach ( array_slice( $feedback_stats['completion_rates'], 0, 6 ) as $month => $data ) : ?>
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <div style="min-width: 100px; font-weight: 500;">
                                <?php echo date( 'M Y', strtotime( $month . '-01' ) ); ?>
                            </div>
                            <div class="progress-bar-bg">
                                <div style="background: <?php echo $data['percentage'] >= 80 ? '#28a745' : ($data['percentage'] >= 50 ? '#ffc107' : '#dc3545'); ?>; height: 100%; width: <?php echo $data['percentage']; ?>%; transition: width 0.3s ease;"></div>
                            </div>
                            <div style="min-width: 120px; text-align: right;">
                                <strong><?php echo $data['completed']; ?>/<?php echo $data['total']; ?></strong>
                                <span style="color: #666;">(<?php echo $data['percentage']; ?>%)</span>
                                <?php if ( isset( $data['not_necessary'] ) && $data['not_necessary'] > 0 ) : ?>
                                    <div style="font-size: 0.8em; color: #666;">
                                        <?php echo $data['not_necessary']; ?> not needed
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Individual Progress -->
        <?php if ( ! empty( $all_feedback ) && ! $privacy_mode ) : ?>
            <div class="stat-card">
                <h3 style="margin: 0 0 20px 0;">
                    Individual Progress
                    <?php if ( $performance_filter ) : ?>
                        <span style="font-size: 0.8em; color: #666; font-weight: normal;">
                            (filtered by: <strong><?php echo ucfirst( $performance_filter ); ?></strong> performance, sorted by date)
                        </span>
                    <?php endif; ?>
                </h3>
                <div style="display: grid; gap: 15px;">
                    <?php 
                    // Pre-process team members for filtering and sorting
                    $processed_members = array();
                    
                    foreach ( $team_members as $username => $person ) :
                        $feedback_history = get_person_feedback_history( $username );
                        $feedback_count = 0;
                        $recent_performance = 'N/A';
                        $recent_10_performances = array();
                        $matching_month = null;
                        $display_performance = 'N/A';
                        
                        if ( ! empty( $feedback_history ) ) {
                            foreach ( $feedback_history as $month => $feedback ) {
                                if ( $month !== 'hr_monthly_link' && is_array( $feedback ) ) {
                                    $feedback_count++;
                                    $performance = $feedback['performance'] ?? 'good';
                                    
                                    // Store the most recent 10 performance ratings
                                    if ( count( $recent_10_performances ) < 10 ) {
                                        $recent_10_performances[] = $performance;
                                    }
                                    
                                    // If filtering, find the most recent month with matching performance
                                    if ( $performance_filter && $performance === $performance_filter && ! $matching_month ) {
                                        $matching_month = $month;
                                        $display_performance = ucfirst( $performance );
                                    }
                                    
                                    // Keep track of the most recent for display when not filtering
                                    if ( $recent_performance === 'N/A' ) {
                                        $recent_performance = ucfirst( $performance );
                                    }
                                }
                            }
                        }
                        
                        // Skip this person if they don't match the performance filter
                        // Check if any of their recent 10 performances match the filter
                        if ( $performance_filter ) {
                            $has_matching_performance = in_array( $performance_filter, $recent_10_performances );
                            if ( ! $has_matching_performance ) {
                                continue;
                            }
                        }
                        
                        // Determine what to show in the performance badge
                        if ( $performance_filter && $matching_month ) {
                            $badge_text = $display_performance;
                            $badge_subtext = date( 'M Y', strtotime( $matching_month . '-01' ) );
                        } else {
                            $badge_text = $recent_performance;
                            $badge_subtext = 'recent rating';
                        }
                        
                        $performance_colors = array(
                            'High' => '#28a745',
                            'Good' => '#007cba',
                            'Low' => '#dc3545'
                        );
                        $performance_color = $performance_colors[ $badge_text ] ?? '#6c757d';
                        
                        // Store the processed member data
                        $processed_members[] = array(
                            'username' => $username,
                            'person' => $person,
                            'feedback_count' => $feedback_count,
                            'badge_text' => $badge_text,
                            'badge_subtext' => $badge_subtext,
                            'performance_color' => $performance_color,
                            'matching_month' => $matching_month,
                            'sort_date' => $matching_month ? strtotime( $matching_month . '-01' ) : 0
                        );
                    endforeach;
                    
                    // Sort the processed members
                    if ( $performance_filter ) {
                        // When filtering, sort by matching month (newest first)
                        usort( $processed_members, function( $a, $b ) {
                            return $b['sort_date'] - $a['sort_date'];
                        });
                    }
                    // When not filtering, members are already in the original order (by name)
                    
                    // Now display the sorted members
                    foreach ( $processed_members as $member_data ) :
                        $username = $member_data['username'];
                        $person = $member_data['person'];
                        $feedback_count = $member_data['feedback_count'];
                        $badge_text = $member_data['badge_text'];
                        $badge_subtext = $member_data['badge_subtext'];
                        $performance_color = $member_data['performance_color'];
                        ?>
                        <div style="display: flex; align-items: center; padding: 15px; border: 1px solid #dee2e6; border-radius: 8px; gap: 15px;">
                            <div style="flex: 1;">
                                <a href="<?php echo $person->get_profile_url(); ?>" class="person-profile-link">
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
                                    <?php echo $badge_text; ?>
                                </span>
                                <div style="font-size: 0.8em; color: #666; margin-top: 2px;"><?php echo $badge_subtext; ?></div>
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
            <div class="stat-card" style="text-align: center;">
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
    <script src="assets/cmd-k.js"></script>
    <script src="assets/script.js"></script>
    <?php init_cmd_k_js( $privacy_mode ); ?>
</body>
</html>