// HR Reports JavaScript functionality
console.log('test');
// Global variables
let chatHistory = [];
let systemPrompt = '';
let ollamaModel = 'gpt-oss';
let autoSaveTimeout;
let isAutoSaving = false;

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
                document.execCommand('createLink', false, url.trim());
            }
        } else {
            // No text selected - prompt for both text and URL
            const text = prompt('Enter link text:');
            if (text && text.trim()) {
                const url = prompt('Enter URL:', 'https://');
                if (url && url.trim()) {
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

function updateHiddenField(editorId) {
    const editor = document.getElementById(editorId);
    const hiddenField = document.getElementById(editorId + '_html');
    if (hiddenField) {
        hiddenField.value = editor.innerHTML;
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

function analyzeCurrentFeedback() {
    const feedbackDiv = document.getElementById('feedback_to_person');
    const feedbackText = feedbackDiv.textContent || feedbackDiv.innerText || '';

    if (feedbackText.trim() === '') {
        addChatMessage('ai', 'I don\'t see any feedback text to analyze. Please write some feedback first, then click "Analyze Current" again.');
        return;
    }

    const analysisPrompt = `Please analyze this HR feedback: "${feedbackText}"`;
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

    // Add loading message
    const loadingId = addLoadingMessage();

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

        // Remove loading message and add AI response container
        removeLoadingMessage(loadingId);
        const aiMessageId = addChatMessage('ai', '', true);

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
                        updateChatMessage(aiMessageId, result);
                    }
                } catch (e) {
                    continue;
                }
            }
        }

        chatHistory.push({ role: 'assistant', content: result });

    } catch (error) {
        removeLoadingMessage(loadingId);
        addChatMessage('ai', `Sorry, I couldn't connect to the AI service. Error: ${error.message}`);
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
    console.log('initAutoSave function called');
    const form = document.querySelector('.hr-form');
    console.log('Found form:', form);
    if (!form) {
        console.log('No form found with class .hr-form');
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
    console.log('Found checkboxes:', checkboxes.length);
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function () {
            console.log('Checkbox changed:', this.name, this.checked);
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
    console.log('scheduleAutoSave called');
    clearTimeout(autoSaveTimeout);

    // Show saving indicator
    showSaveStatus('saving');

    autoSaveTimeout = setTimeout(() => {
        performAutoSave();
    }, 1000); // Save 1 second after last change
}

function performAutoSave() {
    console.log('performAutoSave called');
    if (isAutoSaving) return;
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
        saveMessage.style.color = '#666';
        if (saveCheckmark) saveCheckmark.style.display = 'none';
    } else if (status === 'saved') {
        saveMessage.textContent = 'Saved';
        saveMessage.style.color = '#28a745';
        if (saveCheckmark) saveCheckmark.style.display = 'inline';

        // Hide checkmark after 2 seconds
        setTimeout(() => {
            saveMessage.textContent = 'Changes are saved automatically';
            saveMessage.style.color = '#666';
            if (saveCheckmark) saveCheckmark.style.display = 'none';
        }, 2000);
    } else if (status === 'error') {
        saveMessage.textContent = 'Save failed - please refresh';
        saveMessage.style.color = '#dc3545';
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

// Initialize everything when DOM loads
document.addEventListener('DOMContentLoaded', function () {
    console.log('DOMContentLoaded fired');

    // Initialize rich editors
    const editors = document.querySelectorAll('.rich-editor');
    editors.forEach(function (editor) {
        // Update hidden field on input
        editor.addEventListener('input', function () {
            updateHiddenField(editor.id);
            // Auto-uncheck "google doc updated" when feedback text is modified
            uncheckGoogleDocUpdated();
        });

        // Handle paste events to preserve rich text and auto-link URLs
        editor.addEventListener('paste', function (e) {
            e.preventDefault();

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

                // Remove most HTML tags except links
                cleanedContent = cleanedContent.replace(/<(?!a\s|\/a)[^>]+>/gi, ' ');
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
    console.log('About to initialize auto-save');
    initAutoSave();
});