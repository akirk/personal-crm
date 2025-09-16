<?php
/**
 * HR Monthly Reports Tool
 * 
 * Tool for creating and managing monthly HR feedback reports with local LLM feedback.
 */

require_once __DIR__ . '/includes/common.php';
require_once __DIR__ . '/includes/person.php';

$current_team = get_current_team_from_params();
if ( ! $current_team ) {
	$current_team = get_default_team();
}
$privacy_mode = isset( $_GET['privacy'] ) && $_GET['privacy'] === '1';
$team_data = load_team_config_with_objects( $current_team, $privacy_mode );

// Handle AJAX config request
if ( isset( $_GET['get_config'] ) ) {
    header( 'Content-Type: application/json' );
    echo json_encode( array(
        'system_prompt' => get_system_prompt(),
        'ollama_model' => get_ollama_model()
    ) );
    exit;
}

// Handle AJAX request for previous month feedback
if ( isset( $_GET['get_previous_feedback'] ) && isset( $_GET['username'] ) && isset( $_GET['month'] ) ) {
    header( 'Content-Type: application/json' );
    $previous_feedback = get_previous_month_feedback( $_GET['username'], $_GET['month'] );
    echo json_encode( $previous_feedback );
    exit;
}

$message = '';
$chat_response = '';

if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    if ( isset( $_POST['action'] ) && $_POST['action'] === 'save_feedback' ) {
        // Check if this is a "not necessary" selection
        $performance = $_POST['performance'] ?? '';
        if ( strpos( $performance, 'not_necessary_' ) === 0 ) {
            // Extract the reason from the performance value
            $reason = str_replace( 'not_necessary_', '', $performance );
            $_POST['reason'] = $reason;
            $_POST['action'] = 'set_not_necessary';
            $result = set_feedback_not_necessary( $_POST );
            $message = $result['message'];
            // Debug: Add more details to the message
            if ( $result['success'] ) {
                $message = "✅ " . $message . " (Reason: " . $reason . ")";
            } else {
                $message = "❌ " . $message;
            }
        } else {
            // Regular feedback submission
            // Use the HTML content from hidden fields
            $_POST['feedback_to_person'] = $_POST['feedback_to_person_html'] ?? '';
            $_POST['feedback_to_hr'] = $_POST['feedback_to_hr_html'] ?? '';
            $result = save_feedback( $_POST );
            $message = $result['message'];
        }
    } elseif ( isset( $_POST['action'] ) && $_POST['action'] === 'save_hr_link' ) {
        $result = save_hr_google_doc_link( $_POST );
        $message = $result['message'];
    } elseif ( isset( $_POST['action'] ) && $_POST['action'] === 'get_feedback_assessment' ) {
        $chat_response = get_llm_feedback_assessment( $_POST['feedback_text'] );
    } elseif ( isset( $_POST['action'] ) && $_POST['action'] === 'set_not_necessary' ) {
        $result = set_feedback_not_necessary( $_POST );
        $message = $result['message'];
    } elseif ( isset( $_POST['action'] ) && $_POST['action'] === 'remove_not_necessary' ) {
        $result = remove_feedback_not_necessary( $_POST );
        $message = $result['message'];
    }
}


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
    
    create_backup( $feedback_file );
    
    $success = file_put_contents( $feedback_file, json_encode( $feedback_data, JSON_PRETTY_PRINT ) );
    
    if ( $success ) {
        return array( 'success' => true, 'message' => 'Feedback saved successfully!' );
    } else {
        return array( 'success' => false, 'message' => 'Failed to save feedback.' );
    }
}

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

function get_previous_month_feedback( $username, $current_month ) {
    $feedback_file = __DIR__ . '/hr-feedback.json';

    if ( ! file_exists( $feedback_file ) ) {
        return null;
    }

    $content = file_get_contents( $feedback_file );
    $feedback_data = json_decode( $content, true ) ?: array();

    if ( ! isset( $feedback_data['feedback'][$username] ) ) {
        return null;
    }

    $user_feedback = $feedback_data['feedback'][$username];

    // Get all month keys and sort them in descending order
    $months = array();
    foreach ( $user_feedback as $key => $value ) {
        if ( is_array( $value ) && $key !== 'hr_monthly_link' ) {
            $months[] = $key;
        }
    }
    rsort( $months );

    // Find the previous month
    $current_index = array_search( $current_month, $months );
    if ( $current_index !== false && isset( $months[$current_index + 1] ) ) {
        $previous_month = $months[$current_index + 1];
        return array(
            'month' => $previous_month,
            'feedback' => $user_feedback[$previous_month]
        );
    }

    return null;
}





function get_system_prompt() {
    $feedback_file = __DIR__ . '/hr-feedback.json';
    
    if ( ! file_exists( $feedback_file ) ) {
        return "You are an HR feedback assessment assistant. Your role is to help managers improve their feedback quality.";
    }
    
    $content = file_get_contents( $feedback_file );
    $data = json_decode( $content, true ) ?: array();
    
    return $data['system_prompt'] ?? "You are an HR feedback assessment assistant. Your role is to help managers improve their feedback quality.";
}

function get_ollama_model() {
    $feedback_file = __DIR__ . '/hr-feedback.json';
    
    if ( ! file_exists( $feedback_file ) ) {
        return "llama3.2";
    }
    
    $content = file_get_contents( $feedback_file );
    $data = json_decode( $content, true ) ?: array();
    
    return $data['ollama_model'] ?? "llama3.2";
}

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


$selected_person = $_GET['person'] ?? '';
$selected_month = $_GET['month'] ?? get_hr_feedback_month();

$existing_feedback = array();
if ( $selected_person && $selected_month ) {
    $existing_feedback = load_feedback( $selected_person, $selected_month );
}

?>
<!DOCTYPE html>
<html <?php echo function_exists( 'wp_app_language_attributes' ) ? wp_app_language_attributes() : 'lang="en"'; ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <title><?php
        $title_part = '';
        if ( $selected_person ) {
            $person = $team_data['team_members'][$selected_person] ?? $team_data['leadership'][$selected_person] ?? null;
            $title_part = htmlspecialchars( $person ? $person->get_display_name_with_nickname() : $selected_person );
        } else {
            $title_part = htmlspecialchars( $team_data['team_name'] );
        }
        $full_title = $title_part . ' - HR Monthly Report';
        echo function_exists( 'wp_app_title' ) ? wp_app_title( $full_title ) : $full_title;
    ?></title>
    <?php
    if ( function_exists( 'wp_app_enqueue_style' ) ) {
        wp_app_enqueue_style( 'a8c-hr-style', 'assets/style.css' );
        wp_app_enqueue_style( 'a8c-hr-reports', 'assets/hr-reports.css' );
        wp_app_enqueue_style( 'a8c-hr-cmd-k', 'assets/cmd-k.css' );
    } else {
        echo '<link rel="stylesheet" href="assets/style.css">';
        echo '<link rel="stylesheet" href="assets/hr-reports.css">';
        echo '<link rel="stylesheet" href="assets/cmd-k.css">';
    }
    ?>
    <?php if ( function_exists( 'wp_app_head' ) ) wp_app_head(); ?>
</head>
<body class="wp-app-body">
    <?php if ( function_exists( 'wp_app_body_open' ) ) wp_app_body_open(); ?>
    <?php render_cmd_k_panel(); ?>
    <?php render_dark_mode_toggle(); ?>

    <div class="container">
        <?php if ( $selected_person ) : ?>
            <?php
            $person = $team_data['team_members'][$selected_person] ?? $team_data['leadership'][$selected_person] ?? null;
            if ( $person ) :
            ?>
            <div class="person-header">
                <div>
                    <h1 class="person-title">
                        <?php echo htmlspecialchars( $person->get_display_name_with_nickname() ); ?>
                        <span class="person-subtitle">
                            @<?php echo htmlspecialchars( $person->get_username() ); ?>
                            <?php if ( ! empty( $person->role ) ) : ?>
                                • <?php echo htmlspecialchars( $person->role ); ?>
                            <?php endif; ?>
                            <?php
                            $is_alumni = isset( $team_data['alumni'][$selected_person] );
                            if ( $is_alumni ) :
                            ?>
                                • Alumni
                            <?php endif; ?>
                        </span>
                    </h1>
                    <div class="back-nav">
                        <a href="<?php echo build_team_url( 'index.php' ); ?>">← Back to Team Overview</a>
                    </div>
                </div>

                <div class="person-tabs">
                    <a href="<?php echo build_team_url( 'index.php', array( 'person' => $selected_person, 'privacy' => $privacy_mode ? '1' : '0' ) ); ?>"
                       class="tab-link">👤 Member Overview</a>
                    <a href="<?php echo build_team_url( 'hr-reports.php', array( 'person' => $selected_person, 'month' => get_hr_feedback_month(), 'privacy' => $privacy_mode ? '1' : '0' ) ); ?>"
                       class="tab-link active">📝 HR Feedback</a>
                </div>
            </div>
            <?php endif; ?>
        <?php else : ?>
            <div class="header">
                <h1 class="person-title">HR Monthly Reports</h1>
                <div class="back-nav">
                    <a href="<?php echo build_team_url( 'index.php' ); ?>">← Back to Team Overview</a>
                </div>
                <p class="text-muted">Select a team member to manage their feedback</p>
            </div>
        <?php endif; ?>

        <?php if ( $message ) : ?>
            <div class="message <?php echo strpos( strtolower( $message ), 'success' ) !== false || strpos( $message, '✅' ) !== false ? 'success' : 'error'; ?>">
                <?php echo htmlspecialchars( $message ); ?>
            </div>
        <?php endif; ?>


        <form method="post" class="hr-form" id="hr-form">
            <input type="hidden" name="action" value="save_feedback" id="form-action">
            
            <div class="form-row-with-checklist">
                <div class="form-column">
                    <div class="form-group">
                        <label for="username"><?php echo ucfirst( $group ); ?> Member:</label>
                        <select name="username" id="username" required onchange="updatePersonHistory()">
                            <option value="">Select a <?php echo $group; ?> member...</option>
                            <?php foreach ( $team_data['team_members'] as $username => $member ) : ?>
                                <option value="<?php echo htmlspecialchars( $username ); ?>" <?php echo $selected_person === $username ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars( $member->get_display_name_with_nickname() ); ?> (@<?php echo htmlspecialchars( $member->get_username() ); ?>)
                                </option>
                            <?php endforeach; ?>
                            <?php foreach ( $team_data['leadership'] as $username => $leader ) : ?>
                                <option value="<?php echo htmlspecialchars( $username ); ?>" <?php echo $selected_person === $username ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars( $leader->get_display_name_with_nickname() ); ?> (@<?php echo htmlspecialchars( $leader->get_username() ); ?>) - <?php echo htmlspecialchars( $leader->role ); ?>
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
                                <div class="activity-link-container">
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
                        <select name="performance" id="performance" class="performance-select" required onchange="toggleFeedbackFields()">
                            <option value="">Select performance level...</option>
                            <option value="high" <?php echo ( $existing_feedback['performance'] ?? '' ) === 'high' ? 'selected' : ''; ?>>High</option>
                            <option value="good" <?php echo ( $existing_feedback['performance'] ?? '' ) === 'good' ? 'selected' : ''; ?>>Good</option>
                            <option value="low" <?php echo ( $existing_feedback['performance'] ?? '' ) === 'low' ? 'selected' : ''; ?>>Low</option>
                            <optgroup label="Not Necessary">
                                <?php
                                // Check if current selection is "not necessary"
                                $person_feedback = $selected_person ? get_person_feedback_history( $selected_person ) : array();
                                $not_necessary_key = $selected_month . '_not_necessary';
                                $current_not_necessary = isset( $person_feedback[ $not_necessary_key ] ) ? $person_feedback[ $not_necessary_key ] : false;
                                ?>
                                <option value="not_necessary_sabbatical" <?php echo $current_not_necessary === 'sabbatical' ? 'selected' : ''; ?>>Not necessary - Sabbatical</option>
                                <option value="not_necessary_extended_leave" <?php echo $current_not_necessary === 'extended_leave' ? 'selected' : ''; ?>>Not necessary - Extended leave</option>
                                <option value="not_necessary_medical_leave" <?php echo $current_not_necessary === 'medical_leave' ? 'selected' : ''; ?>>Not necessary - Medical leave</option>
                                <option value="not_necessary_started_mid_month" <?php echo $current_not_necessary === 'started_mid_month' ? 'selected' : ''; ?>>Not necessary - Started mid-month</option>
                                <option value="not_necessary_between_roles" <?php echo $current_not_necessary === 'between_roles' ? 'selected' : ''; ?>>Not necessary - Between roles</option>
                                <option value="not_necessary_parental_leave" <?php echo $current_not_necessary === 'parental_leave' ? 'selected' : ''; ?>>Not necessary - Parental leave</option>
                                <option value="not_necessary_not_on_team" <?php echo $current_not_necessary === 'not_on_team' ? 'selected' : ''; ?>>Not necessary - Not on the team</option>
                                <option value="not_necessary_other" <?php echo $current_not_necessary === 'other' ? 'selected' : ''; ?>>Not necessary - Other</option>
                            </optgroup>
                        </select>
                    </div>
                </div>
                
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
                    <button type="button" class="editor-btn" onclick="copyContent('feedback_to_person')" title="Copy Content">📋 Copy</button>
                    <small class="text-small-muted">Select text and click Link, or paste URL directly</small>
                </div>
                <div class="rich-editor" contenteditable="true" spellcheck="true" id="feedback_to_person" data-placeholder="Write the feedback that will be shared with the team member..." required><?php echo $existing_feedback['feedback_to_person'] ?? ''; ?></div>
                <textarea name="feedback_to_person_html" id="feedback_to_person_html" class="hidden"></textarea>
                
                <div class="ai-chat-trigger-container">
                    <button type="button" class="btn btn-secondary" onclick="toggleAIChat()" id="ai-chat-toggle">
                        💬 Open AI Chat Assistant
                    </button>
                </div>
            </div>


            <?php if ( ! $privacy_mode ) : ?>
            <div class="auto-save-indicator" id="save-status" style="text-align: center; margin: 20px 0; font-size: 14px; color: #666;">
                <div>
                    <span id="save-message">Changes are saved automatically</span>
                    <span id="save-checkmark" style="display: none; color: #28a745; margin-left: 5px;">✅</span>
                </div>
            </div>
            <?php endif; ?>


            <div class="form-group">
                <label for="feedback_to_hr">Internal Notes for HR:</label>
                <div class="editor-toolbar">
                    <button type="button" class="editor-btn" onclick="addLink('feedback_to_hr')" title="Add Link">🔗 Link</button>
                    <button type="button" class="editor-btn" onclick="copyContent('feedback_to_hr')" title="Copy Content">📋 Copy</button>
                    <small class="text-small-muted">Select text and click Link, or paste URL directly</small>
                </div>
                <div class="rich-editor" contenteditable="true" spellcheck="true" id="feedback_to_hr" data-placeholder="Write internal notes that will only be seen by HR..."><?php echo $existing_feedback['feedback_to_hr'] ?? ''; ?></div>
                <textarea name="feedback_to_hr_html" id="feedback_to_hr_html" class="hidden"></textarea>
            </div>
        </form>


        <div id="ai-chat-sidebar" class="ai-chat-sidebar" style="display: none;">
            <div class="ai-chat-header">
                <h3>💬 Ollama</h3>
                <div class="chat-controls">
                    <button type="button" class="btn btn-secondary btn-small" onclick="analyzeCurrentFeedback()">🔍 Analyze</button>
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
                <div class="current-draft-section">
                    <h3>📝 Current Month Draft - <?php echo date( 'F Y', strtotime( $current_month . '-01' ) ); ?></h3>
                    <div class="feedback-item current-draft">
                        <div class="feedback-header">
                            <span>Draft - Last updated: <?php echo $current_draft['updated_at']; ?></span>
                            <?php
                            $performance_class = $current_draft['performance'];
                            $performance_text = ucfirst( $current_draft['performance'] );
                            if ( $privacy_mode ) {
                                $performance_text = 'HIGH/GOOD/LOW';
                                $performance_class = 'privacy';
                            }
                            ?>
                            <span class="performance-badge performance-<?php echo $performance_class; ?>"><?php echo $performance_text; ?></span>
                        </div>
                        <div>
                            <strong>To Person:</strong><br>
                            <div class="feedback-quote">
                                <?php echo sanitize_html( $current_draft['feedback_to_person'] ) ?: nl2br( htmlspecialchars( $current_draft['feedback_to_person'] ) ); ?>
                            </div>
                        </div>
                        <?php if ( ! empty( $current_draft['feedback_to_hr'] ) ) : ?>
                            <div class="hr-notes-section">
                                <strong>HR Notes:</strong><br>
                                <div class="feedback-quote">
                                    <?php echo sanitize_html( $current_draft['feedback_to_hr'] ) ?: nl2br( htmlspecialchars( $current_draft['feedback_to_hr'] ) ); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="draft-actions">
                            <button type="button" class="btn btn-secondary" onclick="editCurrentDraft()">✏️ Continue Editing</button>
                            <button type="button" class="btn" onclick="submitCurrentDraft()">✅ Submit Final</button>
                        </div>
                    </div>
                </div>
                
                <div class="ai-assessment-section">
                    <h4>🤖 AI Assessment</h4>
                    <div id="ai-assessment" class="ai-assessment-card">
                        <div id="assessment-loading" class="loading-text">Analyzing feedback...</div>
                        <div id="assessment-content" class="hidden"></div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ( $past_feedback ) : ?>
                <div class="history-section">
                    <h3>📋 Submitted Feedback History for <?php echo htmlspecialchars( $team_data['team_members'][$selected_person]->get_display_name_with_nickname() ?? $team_data['leadership'][$selected_person]->get_display_name_with_nickname() ?? $selected_person ); ?></h3>
                    
                    <?php foreach ( $past_feedback as $month => $feedback ) : ?>
                        <?php
                        $feedback_text = sanitize_html( $feedback['feedback_to_person'] ) ?: htmlspecialchars( $feedback['feedback_to_person'] );
                        $feedback_plain = strip_tags( $feedback_text );
                        $teaser = strlen( $feedback_plain ) > 150 ? substr( $feedback_plain, 0, 150 ) . '...' : $feedback_plain;
                        ?>
                        <div class="feedback-item clickable-feedback <?php echo ( $feedback['submitted_to_hr'] ?? false ) ? 'submitted' : 'draft'; ?>" onclick="toggleFeedbackDetails('<?php echo $month; ?>')">
                            <div class="feedback-header">
                                <span><?php echo date( 'F Y', strtotime( $month . '-01' ) ); ?></span>
                                <?php
                                $performance_class = $feedback['performance'];
                                $performance_text = ucfirst( $feedback['performance'] );
                                if ( $privacy_mode ) {
                                    $performance_text = 'HIGH/GOOD/LOW';
                                    $performance_class = 'privacy';
                                }
                                ?>
                                <span class="performance-badge performance-<?php echo $performance_class; ?>"><?php echo $performance_text; ?></span>
                                <?php if ( $feedback['submitted_to_hr'] ?? false ) : ?>
                                    <span class="status-badge submitted">✅ Submitted</span>
                                <?php else : ?>
                                    <span class="status-badge draft">📝 Draft</span>
                                <?php endif; ?>
                                <span class="expand-toggle" id="toggle-<?php echo $month; ?>">▼ Expand</span>
                            </div>

                            <div class="feedback-teaser" id="teaser-<?php echo $month; ?>">
                                <div class="feedback-comment">
                                    <?php echo htmlspecialchars( $teaser ); ?>
                                </div>
                            </div>

                            <div class="feedback-full-content hidden" id="content-<?php echo $month; ?>">
                                <div class="feedback-content-section">
                                    <strong>To Person:</strong><br>
                                    <div class="feedback-quote">
                                        <?php echo $feedback_text; ?>
                                    </div>
                                </div>
                                <?php if ( ! empty( $feedback['feedback_to_hr'] ) ) : ?>
                                    <div class="hr-notes-divider">
                                        <strong>HR Notes:</strong><br>
                                        <div class="draft-warning">
                                            <?php echo sanitize_html( $feedback['feedback_to_hr'] ) ?: nl2br( htmlspecialchars( $feedback['feedback_to_hr'] ) ); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <div class="feedback-timestamps">
                                    Created: <?php echo $feedback['created_at']; ?>
                                    <?php if ( $feedback['updated_at'] !== $feedback['created_at'] ) : ?>
                                        | Updated: <?php echo $feedback['updated_at']; ?>
                                    <?php endif; ?>
                                    <?php if ( $feedback['submitted'] ?? false ) : ?>
                                        | Submitted: <?php echo $feedback['submitted_at']; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <p>No feedback history found for this person.</p>
            <?php endif; ?>
        <?php endif; ?>

        <div class="ai-info-card">
            <div class="ai-info-text">
                💡 <strong>Add historical feedback:</strong> Select any previous month to add older feedback entries
                <div style="margin-top: 8px;">
                    <strong>Quick select:</strong>
                    <div class="quick-select-links">
                    <?php
                    // Get existing feedback for selected person to check which months already have feedback
                    $existing_months = array();
                    if ( $selected_person ) {
                        $person_feedback = get_person_feedback_history( $selected_person );
                        $existing_months = array_keys( array_filter( $person_feedback, function( $key ) {
                            return $key !== 'hr_monthly_link';
                        }, ARRAY_FILTER_USE_KEY ) );
                    }

                    $current_date = new DateTime();
                    $months_shown = 0;
                    for ( $i = 1; $i <= 12 && $months_shown < 3; $i++ ) { // Check up to 12 months back to find 3 without feedback
                        $past_month = clone $current_date;
                        $past_month->modify( "-{$i} month" );
                        $month_value = $past_month->format( 'Y-m' );

                        // Only show if no feedback exists for this month
                        if ( ! in_array( $month_value, $existing_months ) ) {
                            $month_display = $past_month->format( 'M Y' );
                            $url_params = array_merge( $_GET, array( 'month' => $month_value ) );
                            $quick_url = '?' . http_build_query( $url_params );
                            ?>
                            <a href="<?php echo htmlspecialchars( $quick_url ); ?>"
                               class="copy-button">
                                <?php echo $month_display; ?>
                            </a>
                            <?php
                            $months_shown++;
                        }
                    }

                    if ( $months_shown === 0 ) {
                        echo '<span class="no-months-message">All recent months have feedback</span>';
                    }
                    ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="privacy-footer">
        <?php if ( $privacy_mode ) : ?>
            <a href="?<?php echo http_build_query( array_merge( $_GET, array( 'privacy' => '0' ) ) ); ?>" class="footer-link">🔒 Privacy Mode ON</a>
        <?php else : ?>
            <a href="?<?php echo http_build_query( array_merge( $_GET, array( 'privacy' => '1' ) ) ); ?>" class="footer-link">🔓 Privacy Mode OFF</a>
        <?php endif; ?>
        <a href="<?php echo build_team_url( 'admin.php' ); ?>" class="footer-link">⚙️ Admin Panel</a>
        <a href="<?php echo build_team_url( 'hr-config.php' ); ?>" class="footer-link">🤖 Ollama Settings</a>
        <?php if ( $selected_person ) : ?>
            | <a href="<?php echo build_team_url( 'admin.php', array( 'tab' => 'team', 'edit_person' => $selected_person ) ); ?>" class="footer-link">✏️ Edit Person</a>
        <?php endif; ?>
    </footer>

    <script>
        // Pass PHP variable to JavaScript - use HR feedback month, not current calendar month
        window.currentMonth = '<?php echo get_hr_feedback_month(); ?>';

        // Function to toggle feedback details
        function toggleFeedbackDetails(month) {
            const teaser = document.getElementById('teaser-' + month);
            const content = document.getElementById('content-' + month);
            const toggle = document.getElementById('toggle-' + month);

            if (content.style.display === 'none') {
                // Show full content
                teaser.style.display = 'none';
                content.style.display = 'block';
                toggle.innerHTML = '▲ Collapse';
            } else {
                // Show teaser
                teaser.style.display = 'block';
                content.style.display = 'none';
                toggle.innerHTML = '▼ Expand';
            }
        }
    </script>
    <script>
        function toggleFeedbackFields() {
            const performance = document.getElementById('performance').value;
            const isNotNecessary = performance.startsWith('not_necessary_');
            
            // Get specific feedback-related elements
            const feedbackToPersonGroup = document.getElementById('feedback_to_person')?.closest('.form-group');
            const feedbackToHrGroup = document.getElementById('feedback_to_hr')?.closest('.form-group');
            const progressChecklist = document.querySelector('.progress-checklist');
            const autoSaveIndicator = document.querySelector('.auto-save-indicator');
            const aiChatSection = document.getElementById('ai-chat-sidebar');
            
            // Hide/show feedback fields (but keep autoSaveIndicator visible for messages)
            const elementsToToggle = [feedbackToPersonGroup, feedbackToHrGroup, progressChecklist];
            elementsToToggle.forEach(element => {
                if (element) {
                    element.style.display = isNotNecessary ? 'none' : '';
                }
            });
            
            // Update form requirements
            const feedbackToPersonDiv = document.getElementById('feedback_to_person');
            const feedbackToHrDiv = document.getElementById('feedback_to_hr');
            
            if (isNotNecessary) {
                if (feedbackToPersonDiv) feedbackToPersonDiv.removeAttribute('required');
                if (feedbackToHrDiv) feedbackToHrDiv.removeAttribute('required');
            } else {
                if (feedbackToPersonDiv) feedbackToPersonDiv.setAttribute('required', '');
                if (feedbackToHrDiv) feedbackToHrDiv.setAttribute('required', '');
            }
        }
        
        // Run on page load
        document.addEventListener('DOMContentLoaded', function() {
            toggleFeedbackFields();
        });
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
    <script src="assets/cmd-k.js"></script>
    <script src="assets/script.js"></script>
    <?php init_cmd_k_js( $privacy_mode ); ?>
</body>
</html>