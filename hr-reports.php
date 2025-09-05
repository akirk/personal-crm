<?php
/**
 * HR Monthly Reports Tool
 * 
 * Tool for creating and managing monthly HR feedback reports with local LLM feedback.
 */

require_once __DIR__ . '/includes/common.php';
require_once __DIR__ . '/includes/person.php';

$current_team = $_GET['team'] ?? get_default_team();
$team_data = load_team_config_with_objects( $current_team, false );

// Handle AJAX config request
if ( isset( $_GET['get_config'] ) ) {
    header( 'Content-Type: application/json' );
    echo json_encode( array(
        'system_prompt' => get_system_prompt(),
        'ollama_model' => get_ollama_model()
    ) );
    exit;
}

// Handle form submissions
$message = '';
$chat_response = '';

if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    if ( isset( $_POST['action'] ) && $_POST['action'] === 'save_feedback' ) {
        // Use the HTML content from hidden fields
        $_POST['feedback_to_person'] = $_POST['feedback_to_person_html'] ?? '';
        $_POST['feedback_to_hr'] = $_POST['feedback_to_hr_html'] ?? '';
        $result = save_feedback( $_POST );
        $message = $result['message'];
    } elseif ( isset( $_POST['action'] ) && $_POST['action'] === 'save_hr_link' ) {
        $result = save_hr_google_doc_link( $_POST );
        $message = $result['message'];
    } elseif ( isset( $_POST['action'] ) && $_POST['action'] === 'get_feedback_assessment' ) {
        $chat_response = get_llm_feedback_assessment( $_POST['feedback_text'] );
    }
}

/**
 * Create backup of existing file (max one per minute)
 */
function create_backup( $file_path ) {
    if ( ! file_exists( $file_path ) ) {
        return true; // No file to backup
    }

    // Create backups directory if it doesn't exist
    $backups_dir = __DIR__ . '/backups';
    if ( ! file_exists( $backups_dir ) ) {
        mkdir( $backups_dir, 0755, true );
    }

    // Generate backup filename in backups directory (minute precision only)
    $filename = basename( $file_path );
    $backup_timestamp = date( '-Y-m-d-H-i' ); // No seconds - only minute precision
    $backup_filename = substr( $filename, 0, -4 ) . 'bak' . $backup_timestamp . '.json';
    $backup_path = $backups_dir . '/' . $backup_filename;
    
    // Only create backup if one doesn't already exist for this minute
    if ( file_exists( $backup_path ) ) {
        return true; // Backup for this minute already exists
    }

    return copy( $file_path, $backup_path );
}

/**
 * Save HR Google Doc link for a person
 */
function save_hr_google_doc_link( $data ) {
    $username = $data['username'] ?? '';
    $hr_monthly_link = $data['hr_monthly_link'] ?? '';
    
    if ( empty( $username ) ) {
        return array( 'success' => false, 'message' => 'Username is required.' );
    }
    
    $feedback_file = __DIR__ . '/hr-feedback.json';
    $feedback_data = array();
    
    if ( file_exists( $feedback_file ) ) {
        $content = file_get_contents( $feedback_file );
        $feedback_data = json_decode( $content, true ) ?: array();
    }
    
    if ( ! isset( $feedback_data['feedback'] ) ) {
        $feedback_data['feedback'] = array();
    }
    
    if ( ! isset( $feedback_data['feedback'][$username] ) ) {
        $feedback_data['feedback'][$username] = array();
    }
    
    // Update the hr_monthly_link for this user
    $feedback_data['feedback'][$username]['hr_monthly_link'] = $hr_monthly_link;
    
    // Create backup before saving
    create_backup( $feedback_file );
    
    $success = file_put_contents( $feedback_file, json_encode( $feedback_data, JSON_PRETTY_PRINT ) );
    
    if ( $success ) {
        if ( empty( $hr_monthly_link ) ) {
            return array( 'success' => true, 'message' => 'HR Google Doc link removed successfully!' );
        } else {
            return array( 'success' => true, 'message' => 'HR Google Doc link saved successfully!' );
        }
    } else {
        return array( 'success' => false, 'message' => 'Failed to save HR Google Doc link.' );
    }
}

/**
 * Save feedback to JSON file
 */
function save_feedback( $data ) {
    $username = $data['username'] ?? '';
    $month = $data['month'] ?? '';
    $performance = $data['performance'] ?? '';
    $feedback_to_person = $data['feedback_to_person'] ?? '';
    $feedback_to_hr = $data['feedback_to_hr'] ?? '';
    
    if ( empty( $username ) || empty( $month ) || empty( $performance ) ) {
        return array( 'success' => false, 'message' => 'Required fields missing.' );
    }
    
    $feedback_file = __DIR__ . '/hr-feedback.json';
    $feedback_data = array();
    
    if ( file_exists( $feedback_file ) ) {
        $content = file_get_contents( $feedback_file );
        $feedback_data = json_decode( $content, true ) ?: array();
    }
    
    if ( ! isset( $feedback_data['feedback'] ) ) {
        $feedback_data['feedback'] = array();
    }
    
    if ( ! isset( $feedback_data['feedback'][$username] ) ) {
        $feedback_data['feedback'][$username] = array();
    }
    
    // Preserve existing timestamps and submitted status if updating
    $existing = $feedback_data['feedback'][$username][$month] ?? array();
    $created_at = $existing['created_at'] ?? date( 'Y-m-d H:i:s' );
    $submitted_status = $existing['submitted'] ?? false;
    
    $feedback_data['feedback'][$username][$month] = array(
        'performance' => $performance,
        'feedback_to_person' => $feedback_to_person, // Store as HTML
        'feedback_to_hr' => $feedback_to_hr, // Store as HTML
        'created_at' => $created_at,
        'updated_at' => date( 'Y-m-d H:i:s' ),
        'submitted' => $submitted_status,
        // Checklist items
        'draft_complete' => isset( $data['draft_complete'] ) && $data['draft_complete'] === '1',
        'google_doc_updated' => isset( $data['google_doc_updated'] ) && $data['google_doc_updated'] === '1',
        'submitted_to_hr' => isset( $data['submitted_to_hr'] ) && $data['submitted_to_hr'] === '1'
    );
    
    // Create backup before saving
    create_backup( $feedback_file );
    
    $success = file_put_contents( $feedback_file, json_encode( $feedback_data, JSON_PRETTY_PRINT ) );
    
    if ( $success ) {
        return array( 'success' => true, 'message' => 'Feedback saved successfully!' );
    } else {
        return array( 'success' => false, 'message' => 'Failed to save feedback.' );
    }
}

/**
 * Load existing feedback for a person and month
 */
function load_feedback( $username, $month ) {
    $feedback_file = __DIR__ . '/hr-feedback.json';
    
    if ( ! file_exists( $feedback_file ) ) {
        return array();
    }
    
    $content = file_get_contents( $feedback_file );
    $feedback_data = json_decode( $content, true ) ?: array();
    
    $feedback = $feedback_data['feedback'][$username][$month] ?? array();
    
    // Include HR monthly link from user level
    if ( isset( $feedback_data['feedback'][$username]['hr_monthly_link'] ) ) {
        $feedback['hr_monthly_link'] = $feedback_data['feedback'][$username]['hr_monthly_link'];
    }
    
    return $feedback;
}


/**
 * Get all feedback for a person
 */
function get_person_feedback_history( $username ) {
    $feedback_file = __DIR__ . '/hr-feedback.json';
    
    if ( ! file_exists( $feedback_file ) ) {
        return array();
    }
    
    $content = file_get_contents( $feedback_file );
    $feedback_data = json_decode( $content, true ) ?: array();
    
    return $feedback_data['feedback'][$username] ?? array();
}

/**
 * Get overview data for all team members for a specific month
 */
function get_monthly_overview( $team_data, $month ) {
    $feedback_file = __DIR__ . '/hr-feedback.json';
    $overview = array();
    
    $feedback_data = array();
    if ( file_exists( $feedback_file ) ) {
        $content = file_get_contents( $feedback_file );
        $feedback_data = json_decode( $content, true ) ?: array();
    }
    
    // Process team members
    foreach ( $team_data['team_members'] as $username => $member ) {
        if ( ! $member->needs_hr_monthly ) {
            continue; // Skip members who don't need HR feedback
        }
        
        $user_feedback = $feedback_data['feedback'][$username] ?? array();
        $monthly_feedback = $user_feedback[$month] ?? null;
        $hr_monthly_link = $user_feedback['hr_monthly_link'] ?? '';
        
        $overview[$username] = array(
            'person' => $member,
            'feedback' => $monthly_feedback,
            'hr_monthly_link' => $hr_monthly_link,
            'status' => $member->get_monthly_feedback_status( $month ),
        );
    }
    
    return $overview;
}

/**
 * Simple HTML sanitizer - only allows links
 */
function sanitize_html( $html ) {
    // Allow only <a> tags with href and target attributes
    $allowed_tags = '<a>';
    $clean_html = strip_tags( $html, $allowed_tags );
    
    // Additional safety: ensure all links have target="_blank"
    $clean_html = preg_replace( '/<a\s+([^>]*?)href="([^"]*?)"([^>]*?)>/i', '<a href="$2" target="_blank">', $clean_html );
    
    return $clean_html;
}

/**
 * Get system prompt from JSON file
 */
function get_system_prompt() {
    $feedback_file = __DIR__ . '/hr-feedback.json';
    
    if ( ! file_exists( $feedback_file ) ) {
        return "You are an HR feedback assessment assistant. Your role is to help managers improve their feedback quality.";
    }
    
    $content = file_get_contents( $feedback_file );
    $data = json_decode( $content, true ) ?: array();
    
    return $data['system_prompt'] ?? "You are an HR feedback assessment assistant. Your role is to help managers improve their feedback quality.";
}

/**
 * Get Ollama model from JSON file
 */
function get_ollama_model() {
    $feedback_file = __DIR__ . '/hr-feedback.json';
    
    if ( ! file_exists( $feedback_file ) ) {
        return "llama3.2";
    }
    
    $content = file_get_contents( $feedback_file );
    $data = json_decode( $content, true ) ?: array();
    
    return $data['ollama_model'] ?? "llama3.2";
}

/**
 * Call Ollama LLM for feedback assessment
 */
function get_llm_feedback_assessment( $feedback_text ) {
    if ( empty( $feedback_text ) ) {
        return 'Please provide feedback text to analyze.';
    }
    
    $system_prompt = get_system_prompt();
    
    $data = array(
        'model' => 'llama3.2',
        'messages' => array(
            array( 'role' => 'system', 'content' => $system_prompt ),
            array( 'role' => 'user', 'content' => $feedback_text )
        ),
        'stream' => false
    );
    
    $ch = curl_init();
    curl_setopt( $ch, CURLOPT_URL, 'http://localhost:11434/api/chat' );
    curl_setopt( $ch, CURLOPT_POST, true );
    curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( $data ) );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $ch, CURLOPT_HTTPHEADER, array( 'Content-Type: application/json' ) );
    curl_setopt( $ch, CURLOPT_TIMEOUT, 30 );
    
    $response = curl_exec( $ch );
    $http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
    curl_close( $ch );
    
    if ( $http_code === 200 && $response ) {
        $result = json_decode( $response, true );
        return $result['message']['content'] ?? 'No response received from LLM.';
    } else {
        return 'Error: Could not connect to Ollama. Make sure Ollama is running on localhost:11434 with llama3.2 model available.';
    }
}


// Get current person and month from URL params
$selected_person = $_GET['person'] ?? '';
$selected_month = $_GET['month'] ?? get_hr_feedback_month();
$view_mode = $_GET['view'] ?? 'form'; // 'form' or 'overview'
$privacy_mode = isset( $_GET['privacy'] ) && $_GET['privacy'] === '1';

// Load existing feedback if available
$existing_feedback = array();
if ( $selected_person && $selected_month ) {
    $existing_feedback = load_feedback( $selected_person, $selected_month );
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars( $_GET['person'] ?? $team_data['team_name'] ); ?> - HR Monthly Report</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/hr-reports.css">
</head>
<body>
    <div class="container">
        <div class="back-link">
            <a href="<?php echo build_team_url( 'index.php' ); ?>">← Back to Team Overview</a>
        </div>

        <div class="header">
            <h1>HR Monthly Reports</h1>
            <div class="header-nav">
                <a href="<?php echo build_team_url( 'hr-reports.php', array( 'view' => 'overview', 'month' => $selected_month, 'privacy' => $privacy_mode ? '1' : '0' ) ); ?>"
                   class="btn <?php echo $view_mode === 'overview' || $privacy_mode ? 'btn-primary' : 'btn-secondary'; ?>">📋 Overview</a>
                <a href="<?php echo build_team_url( 'hr-config.php' ); ?>" class="btn btn-secondary">⚙️ Configure Ollama Settings</a>
            </div>
        </div>

        <?php if ( $message ) : ?>
            <div class="message <?php echo strpos( $message, 'success' ) !== false ? 'success' : 'error'; ?>">
                <?php echo htmlspecialchars( $message ); ?>
            </div>
        <?php endif; ?>

        <?php if ( $view_mode === 'overview' || ( $privacy_mode && empty( $selected_person ) ) ) : ?>
            <!-- Overview Mode -->
            <?php
            $overview_data = get_monthly_overview( $team_data, $selected_month );
            ?>
            
            <div class="overview-section">
                <div class="overview-header">
                    <h2>📋 Monthly Reports Overview - <?php echo date( 'F Y', strtotime( $selected_month . '-01' ) ); ?>
                    <?php
                    if ( $privacy_mode ) {
                        echo ' <small style="color: #666;">(Privacy Mode - Content Hidden)</small>';
                    } else {
                        $current_feedback_month = get_hr_feedback_month();
                        if ( $selected_month === $current_feedback_month ) {
                            $today = new DateTime();
                            $day = (int) $today->format('d');
                            if ( $day >= 15 ) {
                                echo ' <small>(Current Month)</small>';
                            } else {
                                echo ' <small>(Previous Month)</small>';
                            }
                        }
                    }
                    ?>
                    </h2>
                    <div class="month-selector">
                        <label for="overview_month">Month:</label>
                        <input type="month" id="overview_month" value="<?php echo htmlspecialchars( $selected_month ); ?>" 
                               onchange="window.location.href='<?php echo build_team_url( 'hr-reports.php', array( 'view' => 'overview', 'month' => '', 'privacy' => $privacy_mode ? '1' : '0' ) ); ?>'.replace('month=', 'month=' + this.value)">
                    </div>
                </div>

                <?php if ( empty( $overview_data ) ) : ?>
                    <div class="no-data">
                        <p>No team members require HR feedback for this month, or no team data available.</p>
                    </div>
                <?php else : ?>
                    <div class="overview-grid">
                        <?php foreach ( $overview_data as $username => $data ) : 
                            $person = $data['person'];
                            $feedback = $data['feedback'];
                            $status_info = $data['status'];
                            $hr_monthly_link = $data['hr_monthly_link'];
                        ?>
                            <div class="overview-card <?php echo $status_info['css_class']; ?>">
                                <div class="card-header">
                                    <h3><?php echo htmlspecialchars( $person->name ); ?></h3>
                                    <span class="username">@<?php echo htmlspecialchars( $username ); ?></span>
                                </div>
                                
                                <div class="card-status">
                                    <span class="status-badge <?php echo $status_info['css_class']; ?>">
                                        <?php echo $status_info['text']; ?>
                                    </span>
                                </div>

                                <?php if ( $feedback ) : ?>
                                    <div class="card-details">
                                        <div class="performance-rating">
                                            <span class="performance-badge performance-<?php echo $feedback['performance']; ?>">
                                                <?php echo ucfirst( $feedback['performance'] ); ?> Performance
                                            </span>
                                        </div>
                                        
                                        <div class="progress-indicators">
                                            <?php
                                            $indicators = [
                                                'draft_complete' => ['✅', '📋', 'First Draft Finalized'],
                                                'google_doc_updated' => ['✅', '📤', 'Ready for Review'],
                                                'submitted_to_hr' => ['✅', '📥', 'Submitted to HR']
                                            ];
                                            
                                            foreach ( $indicators as $key => $icons ) :
                                                $completed = isset( $feedback[$key] ) && $feedback[$key];
                                            ?>
                                                <span class="indicator <?php echo $completed ? 'completed' : 'pending'; ?>" 
                                                      title="<?php echo $icons[2]; ?>">
                                                    <?php echo $completed ? $icons[0] : $icons[1]; ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                        
                                        <div class="card-meta">
                                            <small>Updated: <?php echo $feedback['updated_at']; ?></small>
                                        </div>
                                    </div>
                                <?php else : ?>
                                    <div class="card-details">
                                        <p class="no-feedback">No feedback created yet</p>
                                    </div>
                                <?php endif; ?>

                                <div class="card-actions">
                                    <a href="<?php echo build_team_url( 'hr-reports.php', array( 'view' => 'form', 'person' => $username, 'month' => $selected_month, 'privacy' => $privacy_mode ? '1' : '0' ) ); ?>"
                                       class="btn btn-small btn-primary">
                                        <?php echo $feedback ? '✏️ Edit' : '📝 Create'; ?>
                                    </a>
                                    <?php if ( $hr_monthly_link ) : ?>
                                        <a href="<?php echo htmlspecialchars( $hr_monthly_link ); ?>" 
                                           target="_blank" class="btn btn-small btn-secondary">📄 Google Doc</a>
                                    <?php endif; ?>
                                    <?php if ( isset( $team_data['activity_url_prefix'] ) ) :
                                        $month_start = date( 'Y-m-d', strtotime( $selected_month . '-01' ) );
                                        $month_end = date( 'Y-m-t', strtotime( $selected_month . '-01' ) );
                                        $activity_url = $team_data['activity_url_prefix'] . '&member=' . urlencode( $username ) . '&start=' . $month_start . '&end=' . $month_end;
                                    ?>
                                        <a href="<?php echo htmlspecialchars( $activity_url ); ?>"
                                           target="_blank" class="btn btn-small btn-secondary">📊 Activity</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="overview-summary">
                        <?php
                        $total = count( $overview_data );
                        $completed = 0;
                        $draft = 0;
                        $not_started = 0;
                        
                        foreach ( $overview_data as $data ) {
                            $status = $data['status']['css_class'];
                            if ( $status === 'completed' ) {
                                $completed++;
                            } elseif ( $status === 'draft' || $status === 'draft-finalized' || $status === 'review' ) {
                                $draft++;
                            } else {
                                $not_started++;
                            }
                        }
                        ?>
                        <h3>Summary</h3>
                        <div class="summary-stats">
                            <div class="stat">
                                <span class="stat-number"><?php echo $completed; ?></span>
                                <span class="stat-label">Completed</span>
                            </div>
                            <div class="stat">
                                <span class="stat-number"><?php echo $draft; ?></span>
                                <span class="stat-label">In Progress</span>
                            </div>
                            <div class="stat">
                                <span class="stat-number"><?php echo $not_started; ?></span>
                                <span class="stat-label">Not Started</span>
                            </div>
                            <div class="stat">
                                <span class="stat-number"><?php echo $total; ?></span>
                                <span class="stat-label">Total</span>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

        <?php else : ?>
            <!-- Form Mode -->

        <form method="post" class="hr-form">
            <input type="hidden" name="action" value="save_feedback">
            
            <div class="form-row-with-checklist">
                <div class="form-column">
                    <div class="form-group">
                        <label for="username">Team Member:</label>
                        <select name="username" id="username" required onchange="updatePersonHistory()">
                            <option value="">Select a team member...</option>
                            <?php foreach ( $team_data['team_members'] as $username => $member ) : ?>
                                <option value="<?php echo htmlspecialchars( $username ); ?>" <?php echo $selected_person === $username ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars( $member->name ); ?> (@<?php echo htmlspecialchars( $username ); ?>)
                                </option>
                            <?php endforeach; ?>
                            <?php foreach ( $team_data['leadership'] as $username => $leader ) : ?>
                                <option value="<?php echo htmlspecialchars( $username ); ?>" <?php echo $selected_person === $username ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars( $leader->name ); ?> (@<?php echo htmlspecialchars( $username ); ?>) - <?php echo htmlspecialchars( $leader->role ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="month">Month:</label>
                        <input type="month" name="month" id="month" value="<?php echo htmlspecialchars( $selected_month ); ?>" required>
                        <?php if ( $selected_person && $selected_month ) : ?>
                            <?php
                            $member = $team_data['team_members'][$selected_person] ?? $team_data['leadership'][$selected_person] ?? null;
                            if ( $member && isset( $team_data['activity_url_prefix'] ) ) :
                                $month_start = date( 'Y-m-d', strtotime( $selected_month . '-01' ) );
                                $month_end = date( 'Y-m-t', strtotime( $selected_month . '-01' ) );
                                $activity_url = $team_data['activity_url_prefix'] . '&member=' . urlencode( $selected_person ) . '&start=' . $month_start . '&end=' . $month_end;
                            ?>
                                <div style="margin-top: 8px;">
                                    <a href="<?php echo htmlspecialchars( $activity_url ); ?>"
                                       target="_blank"
                                       class="btn btn-secondary btn-small">
                                        📊 View Activity for <?php echo date( 'F Y', strtotime( $selected_month . '-01' ) ); ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="performance">Performance Evaluation:</label>
                        <select name="performance" id="performance" class="performance-select" required>
                            <option value="">Select performance level...</option>
                            <option value="high" <?php echo ( $existing_feedback['performance'] ?? '' ) === 'high' ? 'selected' : ''; ?>>High</option>
                            <option value="good" <?php echo ( $existing_feedback['performance'] ?? '' ) === 'good' ? 'selected' : ''; ?>>Good</option>
                            <option value="low" <?php echo ( $existing_feedback['performance'] ?? '' ) === 'low' ? 'selected' : ''; ?>>Low</option>
                        </select>
                    </div>
                </div>
                
                <!-- Progress Checklist (Right Side) -->
                <div class="progress-checklist">
                    <h4>📋 Progress</h4>
                    <div class="checklist-compact">
                        <?php
                        $checklist_items = array(
                            'draft_complete' => 'First draft complete',
                        );
                        
                        // Add Google doc item if link exists
                        if (!empty($existing_feedback['hr_monthly_link'])) {
                            $checklist_items['google_doc_updated'] = '<a href="' . htmlspecialchars($existing_feedback['hr_monthly_link']) . '" target="_blank">Updated Google doc</a>';
                        }
                        
                        $checklist_items['submitted_to_hr'] = 'Submitted to HR';

                        foreach ($checklist_items as $item_key => $item_label) :
                            $is_checked = isset($existing_feedback[$item_key]) && $existing_feedback[$item_key];
                        ?>
                        <label class="checklist-item">
                            <input type="checkbox" name="<?php echo $item_key; ?>" value="1" <?php echo $is_checked ? 'checked' : ''; ?>>
                            <span class="<?php echo $is_checked ? 'completed' : ''; ?>"><?php echo $item_label; ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="feedback_to_person">Feedback to Person:</label>
                <div class="editor-toolbar">
                    <button type="button" class="editor-btn" onclick="addLink('feedback_to_person')" title="Add Link">🔗 Link</button>
                    <small style="color: #666; margin-left: 10px;">Select text and click Link, or paste URL directly</small>
                </div>
                <div class="rich-editor" contenteditable="true" id="feedback_to_person" data-placeholder="Write the feedback that will be shared with the team member..." required><?php echo $existing_feedback['feedback_to_person'] ?? ''; ?></div>
                <textarea name="feedback_to_person_html" id="feedback_to_person_html" style="display: none;"></textarea>
                
                <!-- AI Chat Trigger -->
                <div style="margin-top: 10px;">
                    <button type="button" class="btn btn-secondary" onclick="toggleAIChat()" id="ai-chat-toggle">
                        💬 Open AI Chat Assistant
                    </button>
                </div>
            </div>


            <!-- Auto-save indicator with privacy toggle -->
            <div class="auto-save-indicator" id="save-status" style="text-align: center; margin: 20px 0; font-size: 14px; color: #666;">
                <div style="display: flex; align-items: center; justify-content: center; gap: 20px;">
                    <div>
                        <span id="save-message">Changes are saved automatically</span>
                        <span id="save-checkmark" style="display: none; color: #28a745; margin-left: 5px;">✅</span>
                    </div>
                    <div style="border-left: 1px solid #ddd; padding-left: 20px;">
                        <label style="cursor: pointer; font-size: 13px; color: #666;">
                            <input type="checkbox" id="privacy-mode-checkbox" style="margin-right: 6px; transform: scale(0.9);">
                            🔐 Privacy Mode
                        </label>
                    </div>
                </div>
            </div>


            <div class="form-group">
                <label for="feedback_to_hr">Internal Notes for HR:</label>
                <div class="editor-toolbar">
                    <button type="button" class="editor-btn" onclick="addLink('feedback_to_hr')" title="Add Link">🔗 Link</button>
                    <small style="color: #666; margin-left: 10px;">Select text and click Link, or paste URL directly</small>
                </div>
                <div class="rich-editor" contenteditable="true" id="feedback_to_hr" data-placeholder="Write internal notes that will only be seen by HR..."></div>
                <textarea name="feedback_to_hr_html" id="feedback_to_hr_html" style="display: none;"></textarea>
            </div>
        </form>


        <!-- AI Chat Sidebar -->
        <div id="ai-chat-sidebar" class="ai-chat-sidebar" style="display: none;">
            <div class="ai-chat-header">
                <h3>💬 Ollama</h3>
                <div class="chat-controls">
                    <button type="button" class="btn btn-secondary btn-small" onclick="analyzeCurrentFeedback()">🔍 Analyze Current</button>
                    <button type="button" class="btn btn-secondary btn-small" onclick="clearChat()">🗑️ Clear</button>
                    <button type="button" class="btn btn-secondary btn-small" onclick="toggleAIChat()">✕</button>
                </div>
            </div>
            
            <div id="ai-chat-messages" class="ai-chat-messages">
                <!-- Messages will be added here -->
            </div>
            
            <div class="ai-chat-input">
                <textarea id="chat-input" placeholder="Ask me anything about your feedback or HR best practices..." rows="3"></textarea>
                <button type="button" class="btn" onclick="sendChatMessage()" id="send-btn">Send</button>
            </div>
        </div>

        <!-- Current Draft / Feedback History -->
        <?php if ( $selected_person ) : ?>
            <?php
            $history = get_person_feedback_history( $selected_person );
            $current_month = date( 'Y-m' );
            $current_draft = $history[$current_month] ?? null;
            // Filter out the current month and non-feedback entries (like hr_monthly_link)
            $past_feedback = array_filter( $history, function( $feedback, $month ) use ( $current_month ) {
                return $month !== $current_month && $month !== 'hr_monthly_link' && is_array( $feedback );
            }, ARRAY_FILTER_USE_BOTH );
            krsort( $past_feedback ); // Sort past feedback by month descending
            ?>
            
            <?php if ( $current_draft && ! ( $current_draft['submitted'] ?? false ) ) : ?>
                <!-- Current Month Draft in Edit Mode -->
                <div class="current-draft-section">
                    <h3>📝 Current Month Draft - <?php echo date( 'F Y', strtotime( $current_month . '-01' ) ); ?></h3>
                    <div class="feedback-item current-draft">
                        <div class="feedback-header">
                            <span>Draft - Last updated: <?php echo $current_draft['updated_at']; ?></span>
                            <span class="performance-badge performance-<?php echo $current_draft['performance']; ?>"><?php echo ucfirst( $current_draft['performance'] ); ?></span>
                        </div>
                        <div>
                            <strong>To Person:</strong><br>
                            <div style="background: #f9f9f9; padding: 10px; border-radius: 4px; margin-top: 5px;">
                                <?php echo sanitize_html( $current_draft['feedback_to_person'] ) ?: nl2br( htmlspecialchars( $current_draft['feedback_to_person'] ) ); ?>
                            </div>
                        </div>
                        <?php if ( ! empty( $current_draft['feedback_to_hr'] ) ) : ?>
                            <div style="margin-top: 15px;">
                                <strong>HR Notes:</strong><br>
                                <div style="background: #f9f9f9; padding: 10px; border-radius: 4px; margin-top: 5px;">
                                    <?php echo sanitize_html( $current_draft['feedback_to_hr'] ) ?: nl2br( htmlspecialchars( $current_draft['feedback_to_hr'] ) ); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="draft-actions" style="margin-top: 15px; text-align: right;">
                            <button type="button" class="btn btn-secondary" onclick="editCurrentDraft()">✏️ Continue Editing</button>
                            <button type="button" class="btn" onclick="submitCurrentDraft()">✅ Submit Final</button>
                        </div>
                    </div>
                </div>
                
                <!-- AI Assessment Section -->
                <div class="ai-assessment-section">
                    <h4>🤖 AI Assessment</h4>
                    <div id="ai-assessment" class="chat-response" style="min-height: 60px; background: #f0f8ff; border-left-color: #4a90e2;">
                        <div id="assessment-loading" style="color: #666; font-style: italic;">Analyzing feedback...</div>
                        <div id="assessment-content" style="display: none;"></div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ( $past_feedback ) : ?>
                <div class="history-section">
                    <h3>📋 Submitted Feedback History for <?php echo htmlspecialchars( $team_data['team_members'][$selected_person]->name ?? $team_data['leadership'][$selected_person]->name ?? $selected_person ); ?></h3>
                    
                    <?php foreach ( $past_feedback as $month => $feedback ) : ?>
                        <div class="feedback-item <?php echo ( $feedback['submitted'] ?? false ) ? 'submitted' : 'draft'; ?>">
                            <div class="feedback-header">
                                <span><?php echo date( 'F Y', strtotime( $month . '-01' ) ); ?></span>
                                <span class="performance-badge performance-<?php echo $feedback['performance']; ?>"><?php echo ucfirst( $feedback['performance'] ); ?></span>
                                <?php if ( $feedback['submitted'] ?? false ) : ?>
                                    <span class="status-badge submitted">✅ Submitted</span>
                                <?php else : ?>
                                    <span class="status-badge draft">📝 Draft</span>
                                <?php endif; ?>
                            </div>
                            <div>
                                <strong>To Person:</strong><br>
                                <?php echo sanitize_html( $feedback['feedback_to_person'] ) ?: nl2br( htmlspecialchars( $feedback['feedback_to_person'] ) ); ?>
                            </div>
                            <?php if ( ! empty( $feedback['feedback_to_hr'] ) ) : ?>
                                <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #ddd;">
                                    <strong>HR Notes:</strong><br>
                                    <?php echo sanitize_html( $feedback['feedback_to_hr'] ) ?: nl2br( htmlspecialchars( $feedback['feedback_to_hr'] ) ); ?>
                                </div>
                            <?php endif; ?>
                            <div style="margin-top: 10px; color: #666; font-size: 12px;">
                                Created: <?php echo $feedback['created_at']; ?>
                                <?php if ( $feedback['updated_at'] !== $feedback['created_at'] ) : ?>
                                    | Updated: <?php echo $feedback['updated_at']; ?>
                                <?php endif; ?>
                                <?php if ( $feedback['submitted'] ?? false ) : ?>
                                    | Submitted: <?php echo $feedback['submitted_at']; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <p>No feedback history found for this person.</p>
            <?php endif; ?>
        <?php endif; ?>
        
        <?php endif; // End form mode ?>
    </div>

    <!-- Footer with privacy mode toggle -->
    <footer style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee; text-align: center; font-size: 14px;">
        <?php if ( $privacy_mode ) : ?>
            <a href="?<?php echo http_build_query( array_merge( $_GET, array( 'privacy' => '0' ) ) ); ?>" style="color: #666; text-decoration: none; margin-right: 15px;">🔒 Privacy Mode ON</a>
        <?php else : ?>
            <a href="?<?php echo http_build_query( array_merge( $_GET, array( 'privacy' => '1' ) ) ); ?>" style="color: #666; text-decoration: none; margin-right: 15px;">🔓 Privacy Mode OFF</a>
        <?php endif; ?>
        <a href="<?php echo build_team_url( 'admin.php' ); ?>" style="color: #666; text-decoration: none;">⚙️ Admin Panel</a>
        <?php if ( $selected_person ) : ?>
            | <a href="<?php echo build_team_url( 'admin.php', array( 'tab' => 'team', 'edit_person' => $selected_person ) ); ?>" style="color: #666; text-decoration: none;">✏️ Edit Person</a>
        <?php endif; ?>
    </footer>

    <script>
        // Pass PHP variable to JavaScript - use HR feedback month, not current calendar month
        window.currentMonth = '<?php echo get_hr_feedback_month(); ?>';
    </script>
    <script src="assets/hr-reports.js"></script>
    <script>
        // Override resetForm to use the current month after the main script loads
        function resetForm() {
            if (confirm('Are you sure you want to clear the form? Any unsaved changes will be lost.')) {
                document.getElementById('username').value = '';
                document.getElementById('month').value = window.currentMonth;
                document.getElementById('performance').value = '';
                document.getElementById('feedback_to_person').innerHTML = '';
                document.getElementById('feedback_to_hr').innerHTML = '';
                document.getElementById('feedback_to_person_html').value = '';
                document.getElementById('feedback_to_hr_html').value = '';
                updatePersonHistory();
            }
        }
    </script>

    <style>
        .performance-badge {
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 12px;
            text-transform: uppercase;
        }
        .performance-high { background: #cce5ff; color: #004085; }
        .performance-good { background: #d4edda; color: #155724; }
        .performance-low { background: #f8d7da; color: #721c24; }
    </style>
</body>
</html>