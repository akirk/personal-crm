<?php
/**
 * HR Configuration Management
 * 
 * Tool for managing HR feedback system configuration including system prompt and model settings.
 */

require_once __DIR__ . '/includes/common.php';
require_once __DIR__ . '/includes/person.php';

$current_team = $_GET['team'] ?? get_default_team();
$team_data = load_team_config_with_objects( $current_team, false );

// Handle form submissions
$message = '';

if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    if ( isset( $_POST['action'] ) && $_POST['action'] === 'save_config' ) {
        $result = save_hr_config( $_POST );
        $message = $result['message'];
    }
}

/**
 * Save HR configuration to JSON file
 */
function save_hr_config( $data ) {
    $system_prompt = $data['system_prompt'] ?? '';
    $ollama_model = $data['ollama_model'] ?? 'llama3.2';
    
    if ( empty( $system_prompt ) || empty( $ollama_model ) ) {
        return array( 'success' => false, 'message' => 'System prompt and model are required.' );
    }
    
    $feedback_file = __DIR__ . '/hr-feedback.json';
    $feedback_data = array();
    
    if ( file_exists( $feedback_file ) ) {
        $content = file_get_contents( $feedback_file );
        $feedback_data = json_decode( $content, true ) ?: array();
    }
    
    // Preserve existing feedback data
    if ( ! isset( $feedback_data['feedback'] ) ) {
        $feedback_data['feedback'] = array();
    }
    
    $feedback_data['system_prompt'] = $system_prompt;
    $feedback_data['ollama_model'] = $ollama_model;
    $feedback_data['updated_at'] = date( 'Y-m-d H:i:s' );
    
    $success = file_put_contents( $feedback_file, json_encode( $feedback_data, JSON_PRETTY_PRINT ) );
    
    if ( $success ) {
        return array( 'success' => true, 'message' => 'Configuration saved successfully!' );
    } else {
        return array( 'success' => false, 'message' => 'Failed to save configuration.' );
    }
}

/**
 * Load current configuration
 */
function load_hr_config() {
    $feedback_file = __DIR__ . '/hr-feedback.json';
    
    if ( ! file_exists( $feedback_file ) ) {
        return array(
            'system_prompt' => 'You are an HR feedback assessment assistant. Your role is to help managers improve their feedback quality.',
            'ollama_model' => 'llama3.2'
        );
    }
    
    $content = file_get_contents( $feedback_file );
    $data = json_decode( $content, true ) ?: array();
    
    return array(
        'system_prompt' => $data['system_prompt'] ?? 'You are an HR feedback assessment assistant. Your role is to help managers improve their feedback quality.',
        'ollama_model' => $data['ollama_model'] ?? 'llama3.2'
    );
}


$current_config = load_hr_config();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Configuration - <?php echo htmlspecialchars( $team_data['team_name'] ); ?></title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/cmd-k.css">
    <style>
        .config-form { max-width: 800px; margin: 0 auto; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group select, .form-group textarea { 
            width: 100%; 
            padding: 8px; 
            border: 1px solid #ddd; 
            border-radius: 4px; 
            font-family: inherit;
        }
        .form-group textarea { min-height: 300px; resize: vertical; font-family: 'Monaco', 'Menlo', 'Consolas', monospace; font-size: 13px; }
        .btn { 
            background: #007cba; 
            color: white; 
            padding: 10px 20px; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
        }
        .btn:hover { background: #005a87; }
        .btn-secondary { background: #666; }
        .btn-secondary:hover { background: #444; }
        .message { 
            padding: 10px; 
            border-radius: 4px; 
            margin-bottom: 20px; 
        }
        .message.success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .message.error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .config-info {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border-left: 4px solid #007cba;
        }
        .model-suggestions {
            margin-top: 5px;
            font-size: 12px;
            color: #666;
        }
        .model-suggestions span {
            display: inline-block;
            background: #f0f0f0;
            padding: 2px 6px;
            border-radius: 3px;
            margin-right: 5px;
            margin-top: 3px;
            cursor: pointer;
        }
        .model-suggestions span:hover {
            background: #007cba;
            color: white;
        }
        .model-item {
            display: inline-block;
            background: #f0f0f0;
            padding: 4px 8px;
            border-radius: 3px;
            margin: 2px 5px 2px 0;
            cursor: pointer;
            font-size: 12px;
            border: 1px solid #ddd;
        }
        .model-item:hover {
            background: #007cba;
            color: white;
            border-color: #005a87;
        }
        .model-item.current {
            background: #28a745;
            color: white;
            border-color: #1e7e34;
        }
        .test-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin-top: 20px;
        }
        .test-result {
            margin-top: 10px;
            padding: 10px;
            border-radius: 4px;
            background: white;
            border-left: 4px solid #4a90e2;
            white-space: pre-wrap;
        }
    </style>
</head>
<body>
    <?php render_cmd_k_panel(); ?>
    <div class="container">
        <div class="back-link">
            <a href="<?php echo build_team_url( 'hr-reports.php' ); ?>">← Back to HR Reports</a>
        </div>

        <div class="header">
            <h1>⚙️ HR System Configuration</h1>
            <p>Configure the AI system prompt and model for HR feedback assessment</p>
        </div>

        <?php if ( $message ) : ?>
            <div class="message <?php echo strpos( $message, 'success' ) !== false ? 'success' : 'error'; ?>">
                <?php echo htmlspecialchars( $message ); ?>
            </div>
        <?php endif; ?>

        <div class="config-info">
            <strong>📝 Configuration Info:</strong><br>
            Changes to the system prompt and model will affect all future AI feedback assessments. 
            The system prompt guides how the AI analyzes and provides feedback suggestions.
        </div>

        <form method="post" class="config-form">
            <input type="hidden" name="action" value="save_config">
            
            <div class="form-group">
                <label for="ollama_model">Ollama Model:</label>
                <div style="display: flex; gap: 10px; align-items: flex-start;">
                    <input type="text" name="ollama_model" id="ollama_model" value="<?php echo htmlspecialchars( $current_config['ollama_model'] ); ?>" required style="flex: 1;">
                    <button type="button" class="btn btn-secondary" onclick="loadAvailableModels()" id="load-models-btn">🔄 Load Available Models</button>
                </div>
                <div id="available-models" class="model-suggestions">
                    <div id="models-loading" style="display: none; color: #666; font-style: italic;">Loading available models...</div>
                    <div id="models-list"></div>
                </div>
            </div>

            <div class="form-group">
                <label for="system_prompt">System Prompt:</label>
                <textarea name="system_prompt" id="system_prompt" placeholder="Enter the system prompt that will guide the AI's feedback analysis..." required><?php echo htmlspecialchars( $current_config['system_prompt'] ); ?></textarea>
            </div>

            <div class="form-group">
                <button type="submit" class="btn">💾 Save Configuration</button>
                <button type="button" class="btn btn-secondary" onclick="resetToDefaults()">🔄 Reset to Defaults</button>
                <button type="button" class="btn btn-secondary" onclick="testConfiguration()">🧪 Test Configuration</button>
            </div>
        </form>


        <div class="test-section" id="test-section" style="display: none;">
            <h3>🧪 Configuration Test</h3>
            <p>Testing connection to Ollama with current configuration...</p>
            <div id="test-result" class="test-result"></div>
        </div>
    </div>

    <script>
        function setModel(model) {
            document.getElementById('ollama_model').value = model;
            
            // Update current model highlighting
            document.querySelectorAll('.model-item').forEach(item => {
                if (item.textContent === model) {
                    item.classList.add('current');
                } else {
                    item.classList.remove('current');
                }
            });
        }

        async function loadAvailableModels() {
            const loadingDiv = document.getElementById('models-loading');
            const modelsListDiv = document.getElementById('models-list');
            const loadBtn = document.getElementById('load-models-btn');
            const currentModel = document.getElementById('ollama_model').value;

            loadingDiv.style.display = 'block';
            modelsListDiv.innerHTML = '';
            loadBtn.disabled = true;
            loadBtn.textContent = '⏳ Loading...';

            try {
                const response = await fetch('http://localhost:11434/api/tags', {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                    }
                });

                if (response.ok) {
                    const data = await response.json();
                    
                    if (data.models && data.models.length > 0) {
                        let modelsHtml = '<strong>Available models:</strong><br>';
                        
                        data.models.forEach(modelInfo => {
                            const modelName = modelInfo.name;
                            const isCurrent = modelName === currentModel;
                            const currentClass = isCurrent ? ' current' : '';
                            const sizeInfo = modelInfo.size ? ` (${formatBytes(modelInfo.size)})` : '';
                            
                            modelsHtml += `<span class="model-item${currentClass}" onclick="setModel('${modelName}')">${modelName}${sizeInfo}</span>`;
                        });
                        
                        modelsListDiv.innerHTML = modelsHtml;
                    } else {
                        modelsListDiv.innerHTML = '<span style="color: #856404;">No models found. Run <code>ollama pull &lt;model-name&gt;</code> to install models.</span>';
                    }
                } else {
                    throw new Error('HTTP ' + response.status);
                }
            } catch (error) {
                modelsListDiv.innerHTML = '<span style="color: #721c24;">❌ Could not connect to Ollama. Make sure it\'s running on localhost:11434</span>';
            } finally {
                loadingDiv.style.display = 'none';
                loadBtn.disabled = false;
                loadBtn.textContent = '🔄 Load Available Models';
            }
        }

        function formatBytes(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
        }

        function resetToDefaults() {
            if (confirm('Are you sure you want to reset to default configuration? This will overwrite your current settings.')) {
                document.getElementById('ollama_model').value = 'llama3.2';
                document.getElementById('system_prompt').value = 'You are an HR feedback assessment assistant. Your role is to help managers improve their feedback quality.\n\nAnalyze the following feedback and provide:\n1. Strengths of the feedback\n2. Areas for improvement\n3. Suggestions for making it more constructive and actionable\n4. Overall assessment of tone and clarity\n\nFocus on:\n- Specificity vs vagueness\n- Constructive vs destructive language\n- Actionable suggestions vs general statements\n- Balance between positive and developmental feedback\n- Professional tone and clarity\n\nBe helpful and constructive in your assessment. Use markdown formatting for better readability.';
            }
        }

        async function testConfiguration() {
            const testSection = document.getElementById('test-section');
            const testResult = document.getElementById('test-result');
            const model = document.getElementById('ollama_model').value;
            const systemPrompt = document.getElementById('system_prompt').value;

            testSection.style.display = 'block';
            testResult.textContent = 'Testing connection with streaming...';

            try {
                const response = await fetch('http://localhost:11434/api/chat', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        model: model,
                        messages: [
                            { role: 'system', content: systemPrompt },
                            { role: 'user', content: 'Test message: "Good work on the project this month."' }
                        ],
                        stream: true
                    })
                });

                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }

                testResult.textContent = '✅ SUCCESS: Connection established. Streaming response:\n\n';
                
                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                let fullResponse = '';

                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;

                    const chunk = decoder.decode(value);
                    const lines = chunk.split('\n');

                    for (const line of lines) {
                        if (line.trim() === '') continue;
                        
                        try {
                            const data = JSON.parse(line);
                            if (data.message?.content) {
                                fullResponse += data.message.content;
                                testResult.textContent = '✅ SUCCESS: Connection established. Streaming response:\n\n' + fullResponse;
                            }
                        } catch (e) {
                            continue;
                        }
                    }
                }

                if (!fullResponse) {
                    testResult.textContent = '⚠️ WARNING: Connection established but no content received from model.';
                }

            } catch (error) {
                testResult.textContent = '❌ ERROR: Could not connect to Ollama.\n\nDetails: ' + error.message + '\n\nMake sure:\n- Ollama is running on localhost:11434\n- The model "' + model + '" is available\n- Run: ollama pull ' + model;
            }
        }
    </script>
    <script src="assets/cmd-k.js"></script>
    <script src="assets/script.js"></script>
    <?php init_cmd_k_js( $privacy_mode ); ?>
</body>
</html>