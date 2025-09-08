// HR Reports JavaScript functionality
console.log('test');
// Global variables
let chatHistory = [];
let systemPrompt = '';
let ollamaModel = 'gpt-oss';
let autoSaveTimeout;
let isAutoSaving = false;

// Privacy mode variables
let privacyMode = false;
let originalContent = {
    feedback_to_person: '',
    feedback_to_hr: ''
};
let originalDropdownOptions = [];
const privacyPlaceholders = {
    feedback_to_person: 'This feedback content is hidden in privacy mode. This protects confidential information when someone else might see your screen. The actual content is preserved and will be restored when privacy mode is disabled.',
    feedback_to_hr: 'These HR notes are hidden in privacy mode. This protects confidential information when someone else might see your screen. The actual content is preserved and will be restored when privacy mode is disabled.'
};

// Form functions
function resetForm(currentMonth) {
    if (confirm('Are you sure you want to clear the form? Any unsaved changes will be lost.')) {
        document.getElementById('username').value = '';
        document.getElementById('month').value = currentMonth;
        document.getElementById('performance').value = '';
        document.getElementById('feedback_to_person').innerHTML = '';
        document.getElementById('feedback_to_hr').innerHTML = '';
        document.getElementById('feedback_to_person_html').value = '';
        document.getElementById('feedback_to_hr_html').value = '';
        updatePersonHistory();
    }
}

function addLink(editorId) {
    const editor = document.getElementById(editorId);
    const selection = window.getSelection();

    if (selection.rangeCount > 0) {
        const selectedText = selection.toString();

        if (selectedText) {
            // Text is selected - prompt for URL
            const url = prompt('Enter URL:', 'https://');
            if (url && url.trim()) {
                // Save state for undo
                saveEditorState(editorId);
                document.execCommand('createLink', false, url.trim());
            }
        } else {
            // No text selected - prompt for both text and URL
            const text = prompt('Enter link text:');
            if (text && text.trim()) {
                const url = prompt('Enter URL:', 'https://');
                if (url && url.trim()) {
                    // Save state for undo
                    saveEditorState(editorId);
                    
                    const link = document.createElement('a');
                    link.href = url.trim();
                    link.textContent = text.trim();
                    link.target = '_blank';

                    const range = selection.getRangeAt(0);
                    range.insertNode(link);
                    range.setStartAfter(link);
                    range.collapse(true);
                    selection.removeAllRanges();
                    selection.addRange(range);
                }
            }
        }
        updateHiddenField(editorId);
    }
}

function copyContent(editorId) {
    const editor = document.getElementById(editorId);
    if (!editor) return;
    
    // Get the content - use original content if in privacy mode
    let content = '';
    if (privacyMode && originalContent[editorId]) {
        content = originalContent[editorId];
    } else {
        content = editor.innerHTML;
    }
    
    // Create a temporary element to extract text with preserved formatting
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = content;
    
    try {
        // Try to copy both HTML and plain text to clipboard
        if (navigator.clipboard && window.ClipboardItem) {
            const html = new Blob([content], { type: 'text/html' });
            const text = new Blob([tempDiv.textContent || tempDiv.innerText], { type: 'text/plain' });
            const clipboardItem = new ClipboardItem({
                'text/html': html,
                'text/plain': text
            });
            
            navigator.clipboard.write([clipboardItem]).then(() => {
                showCopyFeedback(editorId, true);
            }).catch(() => {
                // Fallback to plain text only
                fallbackCopy(tempDiv.textContent || tempDiv.innerText, editorId);
            });
        } else {
            // Fallback for older browsers
            fallbackCopy(tempDiv.textContent || tempDiv.innerText, editorId);
        }
    } catch (error) {
        console.error('Copy failed:', error);
        showCopyFeedback(editorId, false);
    }
}

function fallbackCopy(text, editorId) {
    try {
        // Create temporary textarea
        const textarea = document.createElement('textarea');
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        const success = document.execCommand('copy');
        document.body.removeChild(textarea);
        showCopyFeedback(editorId, success);
    } catch (error) {
        showCopyFeedback(editorId, false);
    }
}

function showCopyFeedback(editorId, success) {
    const button = document.querySelector(`button[onclick="copyContent('${editorId}')"]`);
    if (!button) return;
    
    const originalText = button.innerHTML;
    if (success) {
        button.innerHTML = '✅ Copied!';
        button.style.background = '#d4edda';
        button.style.borderColor = '#c3e6cb';
        button.style.color = '#155724';
    } else {
        button.innerHTML = '❌ Failed';
        button.style.background = '#f8d7da';
        button.style.borderColor = '#f5c6cb';
        button.style.color = '#721c24';
    }
    
    setTimeout(() => {
        button.innerHTML = originalText;
        button.style.background = '';
        button.style.borderColor = '';
        button.style.color = '';
    }, 2000);
}

// Undo/Redo functionality
let editorHistory = {};

function saveEditorState(editorId) {
    const editor = document.getElementById(editorId);
    if (!editorHistory[editorId]) {
        editorHistory[editorId] = {
            states: [],
            currentIndex: -1
        };
    }
    
    const history = editorHistory[editorId];
    const currentContent = editor.innerHTML;
    
    // Only save if content is different
    if (history.states.length === 0 || history.states[history.currentIndex] !== currentContent) {
        // Remove any states after current index (when user made changes after undo)
        history.states = history.states.slice(0, history.currentIndex + 1);
        
        // Add new state
        history.states.push(currentContent);
        history.currentIndex++;
        
        // Limit history to 50 states
        if (history.states.length > 50) {
            history.states.shift();
            history.currentIndex--;
        }
    }
}

function undoEditor(editorId) {
    const editor = document.getElementById(editorId);
    const history = editorHistory[editorId];
    
    if (history && history.currentIndex > 0) {
        history.currentIndex--;
        editor.innerHTML = history.states[history.currentIndex];
        updateHiddenField(editorId);
        return true;
    }
    return false;
}

function redoEditor(editorId) {
    const editor = document.getElementById(editorId);
    const history = editorHistory[editorId];
    
    if (history && history.currentIndex < history.states.length - 1) {
        history.currentIndex++;
        editor.innerHTML = history.states[history.currentIndex];
        updateHiddenField(editorId);
        return true;
    }
    return false;
}

// Smart link pasting
function handleSmartPaste(editor, clipboardData) {
    const selection = window.getSelection();
    const selectedText = selection.toString();
    
    // Get clipboard content
    const clipboardText = clipboardData.getData('text/plain');
    const clipboardHtml = clipboardData.getData('text/html');
    
    // Check if clipboard contains a URL
    const urlRegex = /^https?:\/\/[^\s]+$/;
    let pastedUrl = null;
    
    // Try to extract URL from plain text
    if (urlRegex.test(clipboardText.trim())) {
        pastedUrl = clipboardText.trim();
    } else if (clipboardHtml) {
        // Try to extract URL from HTML (e.g., when copying a link)
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = clipboardHtml;
        const firstLink = tempDiv.querySelector('a[href]');
        if (firstLink) {
            pastedUrl = firstLink.href;
        }
    }
    
    // If we have selected text and pasted content is a URL, make the selected text a link
    if (selectedText && pastedUrl) {
        // Save state for undo
        saveEditorState(editor.id);
        
        // Prevent default paste
        document.execCommand('createLink', false, pastedUrl);
        return true; // Indicate we handled the paste
    }
    
    return false; // Let default paste behavior happen
}

function updateHiddenField(editorId) {
    const editor = document.getElementById(editorId);
    const hiddenField = document.getElementById(editorId + '_html');
    if (hiddenField) {
        // In privacy mode, use original content, not placeholder content
        if (privacyMode && originalContent[editorId]) {
            hiddenField.value = originalContent[editorId];
        } else {
            hiddenField.value = editor.innerHTML;
        }
    }
}

// AI Chat functionality
function toggleAIChat() {
    const sidebar = document.getElementById('ai-chat-sidebar');
    const toggleBtn = document.getElementById('ai-chat-toggle');

    if (sidebar.style.display === 'none') {
        sidebar.style.display = 'flex';
        toggleBtn.textContent = '💬 Close AI Chat';
        document.body.style.marginRight = '400px';

        // Auto-analyze current feedback when opening chat
        setTimeout(() => {
            analyzeCurrentFeedback();
            document.getElementById('chat-input').focus();
        }, 100);
    } else {
        sidebar.style.display = 'none';
        toggleBtn.textContent = '💬 Open AI Chat Assistant';
        document.body.style.marginRight = '0';
    }
}

async function analyzeCurrentFeedback() {
    const feedbackDiv = document.getElementById('feedback_to_person');
    const feedbackText = feedbackDiv.textContent || feedbackDiv.innerText || '';

    if (feedbackText.trim() === '') {
        addChatMessage('ai', 'I don\'t see any feedback text to analyze. Please write some feedback first, then click "Analyze Current" again.');
        return;
    }

    // Get current username and month for fetching previous feedback
    const username = document.getElementById('username')?.value;
    const month = document.getElementById('month')?.value;
    
    let analysisPrompt = `Please analyze this HR feedback: "${feedbackText}"`;
    
    // Try to fetch previous month's feedback for comparison
    if (username && month) {
        try {
            const urlParams = new URLSearchParams({
                get_previous_feedback: '1',
                username: username,
                month: month
            });
            
            const response = await fetch(window.location.href.split('?')[0] + '?' + urlParams);
            const previousData = await response.json();
            
            if (previousData && previousData.feedback) {
                const previousMonth = previousData.month;
                const previousFeedback = previousData.feedback.feedback_to_person || '';
                const previousPerformance = previousData.feedback.performance || '';
                
                // Strip HTML tags from previous feedback for cleaner comparison
                const previousFeedbackText = previousFeedback.replace(/<[^>]*>/g, '');
                
                analysisPrompt = `Please analyze this HR feedback and assess the improvements compared to the previous month:

**CURRENT FEEDBACK (${month}):**
"${feedbackText}"

**PREVIOUS FEEDBACK (${previousMonth}) for comparison:**
"${previousFeedbackText}"
Previous performance rating: ${previousPerformance}

Please provide:
1. Analysis of the current feedback quality
2. Comparison with the previous month's feedback 
3. What improvements were made in the writing/approach
4. What aspects remained consistent or regressed
5. Suggestions for further enhancement

Focus on improvements in specificity, actionability, tone, and structure between the versions.`;
            }
        } catch (error) {
            console.log('Could not fetch previous feedback for comparison:', error);
            // Fall back to regular analysis without comparison
        }
    }
    
    sendChatMessageToAI(analysisPrompt, false);
}

async function sendChatMessage() {
    const input = document.getElementById('chat-input');
    const message = input.value.trim();

    if (!message) return;

    input.value = '';
    addChatMessage('user', message);

    await sendChatMessageToAI(message, true);
}

async function sendChatMessageToAI(message, showUserMessage = true) {
    // Only show user message if it's a regular chat (not auto-analysis)
    if (showUserMessage) {
        addChatMessage('user', message);
    }

    // Create AI message container with loading content immediately
    const aiMessageId = addChatMessage('ai', '🤔 Thinking<div class="typing-dots"><span></span><span></span><span></span></div>', true);
    let hasReceivedContent = false;

    try {
        chatHistory.push({ role: 'user', content: message });

        const response = await fetch('http://localhost:11434/api/chat', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                model: ollamaModel,
                messages: [
                    { role: 'system', content: systemPrompt },
                    ...chatHistory
                ],
                stream: true
            })
        });

        if (!response.ok) {
            throw new Error('HTTP ' + response.status);
        }

        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let result = '';

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
                        // Clear loading content on first response
                        if (!hasReceivedContent) {
                            result = ''; // Clear any existing content
                            hasReceivedContent = true;
                        }
                        result += data.message.content;
                        updateChatMessage(aiMessageId, result);
                    }
                } catch (e) {
                    continue;
                }
            }
        }

        chatHistory.push({ role: 'assistant', content: result });

    } catch (error) {
        // Update the existing AI message with error content
        updateChatMessage(aiMessageId, `Sorry, I couldn't connect to the AI service. Error: ${error.message}`);
    }
}

function addChatMessage(type, content, isStreaming = false) {
    const messagesDiv = document.getElementById('ai-chat-messages');
    const messageId = 'msg-' + Date.now();

    const messageDiv = document.createElement('div');
    messageDiv.className = type + '-message';
    messageDiv.id = messageId;

    const contentDiv = document.createElement('div');
    contentDiv.className = 'message-content';
    contentDiv.innerHTML = type === 'ai' ? markdownToHtml(content) : content;

    messageDiv.appendChild(contentDiv);
    messagesDiv.appendChild(messageDiv);
    messagesDiv.scrollTop = messagesDiv.scrollHeight;

    return messageId;
}

function updateChatMessage(messageId, content) {
    const messageDiv = document.getElementById(messageId);
    if (messageDiv) {
        const contentDiv = messageDiv.querySelector('.message-content');
        contentDiv.innerHTML = markdownToHtml(content);

        const messagesDiv = document.getElementById('ai-chat-messages');
        messagesDiv.scrollTop = messagesDiv.scrollHeight;
    }
}

function addLoadingMessage() {
    const messagesDiv = document.getElementById('ai-chat-messages');
    const loadingId = 'loading-' + Date.now();

    const loadingDiv = document.createElement('div');
    loadingDiv.className = 'ai-message';
    loadingDiv.id = loadingId;

    const contentDiv = document.createElement('div');
    contentDiv.className = 'message-content message-loading';
    contentDiv.innerHTML = '🤔 Thinking<div class="typing-dots"><span></span><span></span><span></span></div>';

    loadingDiv.appendChild(contentDiv);
    messagesDiv.appendChild(loadingDiv);
    messagesDiv.scrollTop = messagesDiv.scrollHeight;

    return loadingId;
}

function removeLoadingMessage(loadingId) {
    const loadingDiv = document.getElementById(loadingId);
    if (loadingDiv) {
        loadingDiv.remove();
    }
}

function clearChat() {
    if (confirm('Clear the entire chat conversation?')) {
        chatHistory = [];
        const messagesDiv = document.getElementById('ai-chat-messages');
        messagesDiv.innerHTML = '';
    }
}

function updatePersonHistory() {
    const username = document.getElementById('username').value;
    const month = document.getElementById('month').value;

    if (username && month) {
        window.location.href = '?person=' + encodeURIComponent(username) + '&month=' + encodeURIComponent(month);
    }
}

// Simple markdown to HTML converter
function markdownToHtml(markdown) {
    let html = markdown
        // Headers
        .replace(/^### (.*$)/gim, '<h3>$1</h3>')
        .replace(/^## (.*$)/gim, '<h2>$1</h2>')
        .replace(/^# (.*$)/gim, '<h1>$1</h1>')
        // Bold
        .replace(/\*\*(.*?)\*\*/gim, '<strong>$1</strong>')
        .replace(/__(.*?)__/gim, '<strong>$1</strong>')
        // Italic
        .replace(/\*(.*?)\*/gim, '<em>$1</em>')
        .replace(/_(.*?)_/gim, '<em>$1</em>')
        // Code
        .replace(/`(.*?)`/gim, '<code>$1</code>')
        // Links
        .replace(/\[([^\]]+)\]\(([^)]+)\)/gim, '<a href="$2" target="_blank">$1</a>')
        // Lists
        .replace(/^\* (.+$)/gim, '<li>$1</li>')
        .replace(/^- (.+$)/gim, '<li>$1</li>')
        .replace(/^\d+\. (.+$)/gim, '<li>$1</li>')
        // Line breaks
        .replace(/\n/gim, '<br>');

    // Wrap lists
    html = html.replace(/(<li>.*<\/li>)/gims, '<ul>$1</ul>');

    return html;
}

// Configuration loading
async function loadConfig() {
    try {
        const response = await fetch('?get_config=1');
        if (response.ok) {
            const config = await response.json();
            systemPrompt = config.system_prompt || systemPrompt;
            ollamaModel = config.ollama_model || ollamaModel;
        }
    } catch (error) {
        console.log('Could not load config, using defaults');
    }
}

// Ollama integration with streaming
async function assessWithOllama(feedbackText) {
    if (!feedbackText || feedbackText.trim() === '') {
        return 'No feedback text to analyze.';
    }

    try {
        const response = await fetch('http://localhost:11434/api/chat', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                model: ollamaModel,
                messages: [
                    { role: 'system', content: systemPrompt },
                    { role: 'user', content: feedbackText }
                ],
                stream: true
            })
        });

        if (!response.ok) {
            throw new Error('HTTP ' + response.status);
        }

        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let result = '';

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
                        result += data.message.content;
                        // Update the display in real-time
                        updateStreamingResponse(result);
                    }
                } catch (e) {
                    // Skip invalid JSON lines
                    continue;
                }
            }
        }

        return result || 'No response received from LLM.';

    } catch (error) {
        return 'Error: Could not connect to Ollama. Make sure Ollama is running on localhost:11434 with ' + ollamaModel + ' model available. (' + error.message + ')';
    }
}

function updateStreamingResponse(content) {
    const contentDiv = document.getElementById('live-assessment-content');
    if (contentDiv && contentDiv.style.display !== 'none') {
        contentDiv.innerHTML = markdownToHtml(content);
    }
}

// Auto-assess current draft
async function autoAssessCurrentDraft() {
    const assessmentDiv = document.getElementById('ai-assessment');
    const loadingDiv = document.getElementById('assessment-loading');
    const contentDiv = document.getElementById('assessment-content');

    if (!assessmentDiv) return;

    // Get current draft content from the displayed draft
    const currentDraftSection = document.querySelector('.current-draft-section');
    if (!currentDraftSection) return;

    // Extract text content from the draft display
    const feedbackDiv = currentDraftSection.querySelector('[style*="background: #f9f9f9"]');
    if (!feedbackDiv) return;

    const feedbackText = feedbackDiv.textContent || feedbackDiv.innerText || '';

    if (feedbackText.trim()) {
        loadingDiv.style.display = 'block';
        contentDiv.style.display = 'none';

        const assessment = await assessWithOllama(feedbackText);

        loadingDiv.style.display = 'none';
        contentDiv.textContent = assessment;
        contentDiv.style.display = 'block';
    }
}

// Draft action functions
function editCurrentDraft() {
    // Scroll to form and populate with current draft data
    const form = document.querySelector('.hr-form');
    form.scrollIntoView({ behavior: 'smooth' });

    // Form should already be populated via PHP
}

// Auto-save functionality
function initAutoSave() {
    const form = document.querySelector('.hr-form');
    if (!form) {
        return;
    }
    const inputs = form.querySelectorAll('input, select, textarea, .rich-editor');

    inputs.forEach(input => {
        const eventType = input.classList.contains('rich-editor') ? 'input' : 'change';
        input.addEventListener(eventType, function () {
            // Auto-uncheck "google doc updated" when any form content changes
            // (except for the checkboxes themselves)
            if (input.type !== 'checkbox') {
                uncheckGoogleDocUpdated();
            }
            scheduleAutoSave();
        });
    });

    // Specifically handle checklist checkboxes
    const checkboxes = form.querySelectorAll('input[type="checkbox"]');
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function () {
            // Update visual state immediately
            const span = this.nextElementSibling;
            if (span && span.tagName === 'SPAN') {
                span.className = this.checked ? 'completed' : '';
            }
            scheduleAutoSave();
        });
    });
}

function scheduleAutoSave() {
    clearTimeout(autoSaveTimeout);

    // Show saving indicator
    showSaveStatus('saving');

    autoSaveTimeout = setTimeout(() => {
        performAutoSave();
    }, 1000); // Save 1 second after last change
}

function performAutoSave() {
    if (isAutoSaving) return;
    
    // Don't save when privacy mode is active
    if (isPrivacyModeActive()) {
        showSaveStatus('saved'); // Show as saved but don't actually save
        return;
    }
    
    isAutoSaving = true;

    // Update hidden fields for rich editors
    document.querySelectorAll('.rich-editor').forEach(editor => {
        updateHiddenField(editor.id);
    });

    const form = document.querySelector('.hr-form');
    const formData = new FormData(form);
    formData.set('action', 'save_feedback');

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
        .then(response => response.text())
        .then(data => {
            showSaveStatus('saved');
            isAutoSaving = false;
        })
        .catch(error => {
            console.error('Auto-save failed:', error);
            showSaveStatus('error');
            isAutoSaving = false;
        });
}

function showSaveStatus(status) {
    const saveMessage = document.getElementById('save-message');
    const saveCheckmark = document.getElementById('save-checkmark');

    if (!saveMessage) return;

    if (status === 'saving') {
        saveMessage.textContent = 'Saving...';
        saveMessage.style.color = '#999';
        saveMessage.style.opacity = '0.7';
        if (saveCheckmark) saveCheckmark.style.display = 'none';
    } else if (status === 'saved') {
        saveMessage.textContent = '';
        if (saveCheckmark) {
            saveCheckmark.style.display = 'inline';
            saveCheckmark.style.color = '#28a745';
        }

        // Hide checkmark after 1 second
        setTimeout(() => {
            if (saveCheckmark) saveCheckmark.style.display = 'none';
        }, 1000);
    } else if (status === 'error') {
        saveMessage.textContent = 'Save failed - please refresh';
        saveMessage.style.color = '#dc3545';
        saveMessage.style.opacity = '1';
        if (saveCheckmark) saveCheckmark.style.display = 'none';
    }
}

// Auto-uncheck "google doc updated" when feedback content is modified
function uncheckGoogleDocUpdated() {
    const googleDocCheckbox = document.querySelector('input[name="google_doc_updated"]');
    if (googleDocCheckbox && googleDocCheckbox.checked) {
        googleDocCheckbox.checked = false;
        // Update visual state
        const span = googleDocCheckbox.nextElementSibling;
        if (span && span.tagName === 'SPAN') {
            span.className = '';
        }
        // Trigger auto-save to save the unchecked state
        scheduleAutoSave();
    }
}

// Privacy mode functions
function togglePrivacyMode() {
    const editors = ['feedback_to_person', 'feedback_to_hr'];
    
    // Handle text editors
    editors.forEach(editorId => {
        const editor = document.getElementById(editorId);
        if (!editor) return;
        
        if (privacyMode) {
            // Store original content and replace with placeholder
            originalContent[editorId] = editor.innerHTML;
            editor.innerHTML = privacyPlaceholders[editorId];
            editor.style.fontStyle = 'italic';
            editor.style.color = '#666';
            editor.style.backgroundColor = '#f8f9fa';
            // Keep editor editable in privacy mode
        } else {
            // Restore original content
            editor.innerHTML = originalContent[editorId];
            editor.style.fontStyle = '';
            editor.style.color = '';
            editor.style.backgroundColor = '';
        }
        
        // Update hidden field
        updateHiddenField(editorId);
    });
    
    // Handle dropdown redaction
    toggleDropdownPrivacy();
}

function toggleDropdownPrivacy() {
    const dropdown = document.getElementById('username');
    if (!dropdown) return;
    
    if (privacyMode) {
        // Store original options if not already stored
        if (originalDropdownOptions.length === 0) {
            Array.from(dropdown.options).forEach(option => {
                originalDropdownOptions.push({
                    value: option.value,
                    text: option.textContent,
                    selected: option.selected
                });
            });
        }
        
        // Redact option text while preserving values
        Array.from(dropdown.options).forEach((option, index) => {
            if (option.value && option.value !== '') {
                // Generate redacted name like "Person A", "Person B", etc.
                const letter = String.fromCharCode(65 + (index - 1)); // Start from A (skip empty option)
                option.textContent = `Person ${letter}`;
            }
        });
    } else {
        // Restore original option text
        originalDropdownOptions.forEach((originalOption, index) => {
            if (dropdown.options[index]) {
                dropdown.options[index].textContent = originalOption.text;
            }
        });
    }
}

// Prevent saving when in privacy mode
function isPrivacyModeActive() {
    return privacyMode;
}

// Store content before entering privacy mode
function storeCurrentContent() {
    const editors = ['feedback_to_person', 'feedback_to_hr'];
    editors.forEach(editorId => {
        const editor = document.getElementById(editorId);
        if (editor && !privacyMode) {
            originalContent[editorId] = editor.innerHTML;
        }
    });
    
    // Store dropdown options if not already stored
    const dropdown = document.getElementById('username');
    if (dropdown && originalDropdownOptions.length === 0) {
        Array.from(dropdown.options).forEach(option => {
            originalDropdownOptions.push({
                value: option.value,
                text: option.textContent,
                selected: option.selected
            });
        });
    }
}

// Initialize everything when DOM loads
document.addEventListener('DOMContentLoaded', function () {

    // Initialize rich editors
    const editors = document.querySelectorAll('.rich-editor');
    editors.forEach(function (editor) {
        // Update hidden field on input
        editor.addEventListener('input', function () {
            updateHiddenField(editor.id);
            // Auto-uncheck "google doc updated" when feedback text is modified
            uncheckGoogleDocUpdated();
        });

        // Handle paste events with smart link functionality
        editor.addEventListener('paste', function (e) {
            // Check for smart paste first
            if (handleSmartPaste(editor, e.clipboardData)) {
                e.preventDefault();
                updateHiddenField(editor.id);
                uncheckGoogleDocUpdated();
                return;
            }

            e.preventDefault();

            // Save state for undo
            saveEditorState(editor.id);

            // Try to get HTML content first (preserves links from Google Docs, etc.)
            let htmlContent = '';
            let textContent = '';

            if (e.clipboardData) {
                htmlContent = e.clipboardData.getData('text/html');
                textContent = e.clipboardData.getData('text/plain');
            }

            if (htmlContent) {
                // Clean and sanitize the HTML content
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = htmlContent;

                // Remove unwanted styling but keep links
                const links = tempDiv.querySelectorAll('a');
                links.forEach(link => {
                    link.removeAttribute('style');
                    link.removeAttribute('class');
                    link.setAttribute('target', '_blank');
                    
                    // Fix HTTP to HTTPS
                    const href = link.getAttribute('href');
                    if (href && href.startsWith('http://')) {
                        link.setAttribute('href', href.replace('http://', 'https://'));
                    }
                    
                    // Move whitespace from inside link to outside
                    const originalText = link.textContent;
                    const trimmedText = originalText.trim();
                    const leadingSpace = originalText.match(/^\s*/)[0];
                    const trailingSpace = originalText.match(/\s*$/)[0];
                    
                    // Set clean link text
                    link.textContent = trimmedText;
                    
                    // Add whitespace outside the link
                    if (leadingSpace) {
                        link.parentNode.insertBefore(document.createTextNode(leadingSpace), link);
                    }
                    if (trailingSpace) {
                        link.parentNode.insertBefore(document.createTextNode(trailingSpace), link.nextSibling);
                    }
                });

                // Remove all other styling attributes
                const allElements = tempDiv.querySelectorAll('*');
                allElements.forEach(element => {
                    if (element.tagName.toLowerCase() !== 'a') {
                        element.removeAttribute('style');
                        element.removeAttribute('class');
                    }
                });

                // Get the cleaned text content but preserve links
                let cleanedContent = tempDiv.innerHTML;

                // Remove most HTML tags except links, but preserve link content
                cleanedContent = cleanedContent.replace(/<(?!a\s|\/a)[^>]+>/gi, '');
                // Clean up whitespace but preserve single spaces around link text
                cleanedContent = cleanedContent.replace(/\s+/g, ' ').trim();

                // Insert the cleaned content
                const selection = window.getSelection();
                if (selection.rangeCount > 0) {
                    const range = selection.getRangeAt(0);
                    range.deleteContents();

                    const tempContainer = document.createElement('div');
                    tempContainer.innerHTML = cleanedContent;

                    // Convert to document fragment to preserve order
                    const fragment = document.createDocumentFragment();
                    while (tempContainer.firstChild) {
                        fragment.appendChild(tempContainer.firstChild);
                    }
                    range.insertNode(fragment);
                }
            } else if (textContent) {
                // Fallback to plain text with auto-linking
                const selection = window.getSelection();
                if (selection.rangeCount > 0) {
                    const range = selection.getRangeAt(0);
                    range.deleteContents();

                    // Auto-link URLs in plain text
                    const urlRegex = /(https?:\/\/[^\s]+)/g;
                    const linkedText = textContent.replace(urlRegex, '<a href="$1" target="_blank">$1</a>');

                    if (linkedText !== textContent) {
                        const tempDiv = document.createElement('div');
                        tempDiv.innerHTML = linkedText;

                        // Convert to document fragment to preserve order
                        const fragment = document.createDocumentFragment();
                        while (tempDiv.firstChild) {
                            fragment.appendChild(tempDiv.firstChild);
                        }
                        range.insertNode(fragment);
                    } else {
                        range.insertNode(document.createTextNode(textContent));
                    }
                }
            }

            updateHiddenField(editor.id);
            // Auto-uncheck "google doc updated" when content is pasted
            uncheckGoogleDocUpdated();
            // Trigger autosave
            scheduleAutoSave();
        });

        // Add keyboard shortcuts for undo/redo
        editor.addEventListener('keydown', function (e) {
            // Undo: Cmd+Z (Mac) or Ctrl+Z (Windows/Linux)
            if ((e.metaKey || e.ctrlKey) && e.key === 'z' && !e.shiftKey) {
                e.preventDefault();
                if (undoEditor(editor.id)) {
                    uncheckGoogleDocUpdated();
                }
            }
            // Redo: Cmd+Shift+Z (Mac) or Ctrl+Y (Windows/Linux)
            else if (((e.metaKey || e.ctrlKey) && e.key === 'z' && e.shiftKey) || 
                     (e.ctrlKey && e.key === 'y')) {
                e.preventDefault();
                if (redoEditor(editor.id)) {
                    uncheckGoogleDocUpdated();
                }
            }
        });

        // Save editor state on input for undo functionality
        editor.addEventListener('input', function () {
            // Debounce saving state to avoid too many saves during typing
            clearTimeout(editor.saveStateTimeout);
            editor.saveStateTimeout = setTimeout(() => {
                saveEditorState(editor.id);
            }, 500);
        });

        // Show placeholder
        if (editor.innerHTML.trim() === '') {
            editor.classList.add('empty');
        }

        editor.addEventListener('focus', function () {
            if (editor.classList.contains('empty')) {
                editor.classList.remove('empty');
            }
        });

        editor.addEventListener('blur', function () {
            if (editor.innerHTML.trim() === '') {
                editor.classList.add('empty');
            }
        });

        // Initialize hidden field
        updateHiddenField(editor.id);
    });

    // Auto-update URL when month changes
    const monthSelect = document.getElementById('month');
    if (monthSelect) {
        monthSelect.addEventListener('change', updatePersonHistory);
    }

    // Enter key to send message in chat
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey && document.getElementById('chat-input') === document.activeElement) {
            e.preventDefault();
            sendChatMessage();
        }
    });

    // Load config
    loadConfig();

    // Initialize auto-save
    initAutoSave();
    
    // Initialize privacy mode from URL parameter
    const urlParams = new URLSearchParams(window.location.search);
    const privacyModeFromUrl = urlParams.get('privacy') === '1';
    
    // Store initial content when page loads
    storeCurrentContent();
    
    // Set privacy mode based on URL parameter
    if (privacyModeFromUrl && !privacyMode) {
        privacyMode = true;
        togglePrivacyMode();
    }
    
    // Initialize JavaScript privacy mode checkbox
    const privacyCheckbox = document.getElementById('privacy-mode-checkbox');
    if (privacyCheckbox) {
        // Set checkbox state based on current privacy mode
        privacyCheckbox.checked = privacyMode;
        
        privacyCheckbox.addEventListener('change', function() {
            // Store content before toggling (if turning on privacy mode)
            if (this.checked && !privacyMode) {
                storeCurrentContent();
            }
            privacyMode = this.checked;
            togglePrivacyMode();
        });
    }
});