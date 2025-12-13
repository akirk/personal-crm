/**
 * Personal CRM Ollama Client
 *
 * Centralized client for Ollama AI integration. Handles connection testing,
 * CORS error detection, and provides clear setup instructions to users.
 *
 * Usage:
 *   const ollama = new PersonalCrmOllama({ model: 'llama3.2' });
 *
 *   // Check connection first
 *   const status = await ollama.checkConnection();
 *   if (!status.connected) {
 *       // Show status.instructions to user
 *   }
 *
 *   // Stream a chat response
 *   await ollama.chatStream(messages, {
 *       onChunk: (text) => { outputDiv.textContent += text; },
 *       onError: (error) => { console.error(error); },
 *       onComplete: () => { console.log('Done'); }
 *   });
 */
(function(window) {
    'use strict';

    var DEFAULT_BASE_URL = 'http://localhost:11434';

    function PersonalCrmOllama(options) {
        options = options || {};
        this.baseUrl = options.baseUrl || DEFAULT_BASE_URL;
        this.model = options.model || 'llama3.2';
        this._connectionStatus = null;
    }

    PersonalCrmOllama.prototype.getSettingsUrl = function() {
        return (window.personalCrmOllama && window.personalCrmOllama.settingsUrl) || '/wp-admin/options-general.php?page=personal-crm';
    };

    PersonalCrmOllama.prototype.checkConnection = async function() {
        try {
            var response = await fetch(this.baseUrl + '/api/tags', {
                method: 'GET',
                signal: AbortSignal.timeout(5000)
            });

            if (!response.ok) {
                this._connectionStatus = {
                    connected: false,
                    error: 'http_error',
                    message: 'Ollama returned HTTP ' + response.status
                };
                return this._connectionStatus;
            }

            var data = await response.json();
            var models = data.models || [];

            this._connectionStatus = {
                connected: true,
                models: models,
                modelCount: models.length,
                hasSelectedModel: models.some(function(m) {
                    return m.name === this.model || m.name.startsWith(this.model + ':');
                }.bind(this))
            };

            return this._connectionStatus;

        } catch (error) {
            var isCors = error.message.includes('Failed to fetch') ||
                         error.name === 'TypeError' ||
                         error.message.includes('NetworkError');

            this._connectionStatus = {
                connected: false,
                error: isCors ? 'cors' : 'connection',
                message: isCors
                    ? 'Browser access blocked (CORS). Ollama needs to allow browser requests.'
                    : 'Cannot connect to Ollama. Is it running?',
                instructions: this._getInstructions(isCors ? 'cors' : 'not_running')
            };

            return this._connectionStatus;
        }
    };

    PersonalCrmOllama.prototype._getInstructions = function(errorType) {
        var instructions = {
            title: 'Ollama Setup Required',
            steps: []
        };

        if (errorType === 'not_running') {
            instructions.steps = [
                { text: 'Install Ollama from ollama.ai', url: 'https://ollama.ai' },
                { text: 'Start Ollama by running:', code: 'ollama serve' },
                { text: 'Pull a model:', code: 'ollama pull ' + this.model }
            ];
        } else if (errorType === 'cors') {
            instructions.title = 'Ollama CORS Configuration Required';
            instructions.steps = [
                { text: 'Stop Ollama if it\'s running' },
                { text: 'Restart Ollama with browser access enabled:' },
                { text: 'On macOS/Linux:', code: 'OLLAMA_ORIGINS=* ollama serve' },
                { text: 'Or set it permanently:', code: 'launchctl setenv OLLAMA_ORIGINS "*"', hint: 'Then restart Ollama' },
                { text: 'On Windows, set environment variable OLLAMA_ORIGINS to * before starting Ollama' }
            ];
            instructions.moreInfo = {
                text: 'See full setup instructions',
                url: this.getSettingsUrl()
            };
        }

        return instructions;
    };

    PersonalCrmOllama.prototype.renderInstructions = function(container, status) {
        status = status || this._connectionStatus;
        if (!status || !status.instructions) return;

        var inst = status.instructions;

        if (typeof container === 'string') {
            container = document.querySelector(container);
        }
        if (!container) return;

        container.replaceChildren();

        var wrapper = document.createElement('div');
        wrapper.className = 'ollama-instructions';

        var title = document.createElement('strong');
        title.textContent = inst.title;
        wrapper.appendChild(title);

        var ol = document.createElement('ol');

        for (var i = 0; i < inst.steps.length; i++) {
            var step = inst.steps[i];
            var li = document.createElement('li');

            if (step.url) {
                var link = document.createElement('a');
                link.href = step.url;
                link.target = '_blank';
                link.textContent = step.text;
                li.appendChild(link);
            } else {
                li.appendChild(document.createTextNode(step.text));
            }

            if (step.code) {
                li.appendChild(document.createElement('br'));
                var code = document.createElement('code');
                code.textContent = step.code;
                li.appendChild(code);
            }

            if (step.hint) {
                li.appendChild(document.createElement('br'));
                var hint = document.createElement('span');
                hint.className = 'hint';
                hint.textContent = step.hint;
                li.appendChild(hint);
            }

            ol.appendChild(li);
        }

        wrapper.appendChild(ol);

        if (inst.moreInfo) {
            var p = document.createElement('p');
            var infoLink = document.createElement('a');
            infoLink.href = inst.moreInfo.url;
            infoLink.textContent = inst.moreInfo.text;
            p.appendChild(infoLink);
            wrapper.appendChild(p);
        }

        container.appendChild(wrapper);
    };

    PersonalCrmOllama.prototype.chatStream = async function(messages, callbacks) {
        callbacks = callbacks || {};
        var onChunk = callbacks.onChunk || function() {};
        var onError = callbacks.onError || function() {};
        var onComplete = callbacks.onComplete || function() {};

        try {
            var response = await fetch(this.baseUrl + '/api/chat', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    model: this.model,
                    messages: messages,
                    stream: true
                })
            });

            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }

            var reader = response.body.getReader();
            var decoder = new TextDecoder();
            var fullText = '';

            while (true) {
                var result = await reader.read();
                if (result.done) break;

                var chunk = decoder.decode(result.value);
                var lines = chunk.split('\n');

                for (var i = 0; i < lines.length; i++) {
                    var line = lines[i];
                    if (line.trim() === '') continue;
                    try {
                        var data = JSON.parse(line);
                        if (data.message && data.message.content) {
                            fullText += data.message.content;
                            onChunk(data.message.content, fullText);
                        }
                    } catch (e) {
                        continue;
                    }
                }
            }

            onComplete(fullText);
            return { success: true, text: fullText };

        } catch (error) {
            var isCors = error.message.includes('Failed to fetch') || error.name === 'TypeError';
            var errorInfo = {
                success: false,
                error: isCors ? 'cors' : 'connection',
                message: isCors
                    ? 'Browser access blocked. See setup instructions.'
                    : 'Could not connect to Ollama: ' + error.message,
                instructions: this._getInstructions(isCors ? 'cors' : 'not_running')
            };

            onError(errorInfo);
            return errorInfo;
        }
    };

    PersonalCrmOllama.prototype.chat = async function(messages) {
        try {
            var response = await fetch(this.baseUrl + '/api/chat', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    model: this.model,
                    messages: messages,
                    stream: false
                })
            });

            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }

            var data = await response.json();
            return {
                success: true,
                message: data.message.content,
                data: data
            };

        } catch (error) {
            var isCors = error.message.includes('Failed to fetch') || error.name === 'TypeError';
            return {
                success: false,
                error: isCors ? 'cors' : 'connection',
                message: error.message,
                instructions: this._getInstructions(isCors ? 'cors' : 'not_running')
            };
        }
    };

    PersonalCrmOllama.prototype.listModels = async function() {
        var status = await this.checkConnection();
        if (status.connected) {
            return { success: true, models: status.models };
        }
        return { success: false, error: status.error, message: status.message };
    };

    // CSS for instructions (can be overridden by themes)
    PersonalCrmOllama.injectStyles = function() {
        if (document.getElementById('personal-crm-ollama-styles')) return;

        var style = document.createElement('style');
        style.id = 'personal-crm-ollama-styles';
        style.textContent = [
            '.ollama-instructions {',
            '    padding: 15px;',
            '    background: #fff8e5;',
            '    border-left: 4px solid #ffb900;',
            '    margin: 10px 0;',
            '    border-radius: 0 4px 4px 0;',
            '}',
            '.ollama-instructions ol {',
            '    margin: 10px 0 0 20px;',
            '    padding: 0;',
            '}',
            '.ollama-instructions li {',
            '    margin: 8px 0;',
            '}',
            '.ollama-instructions code {',
            '    background: #f5f5f5;',
            '    padding: 2px 6px;',
            '    border-radius: 3px;',
            '    font-family: monospace;',
            '}',
            '.ollama-instructions .hint {',
            '    font-size: 12px;',
            '    color: #666;',
            '}'
        ].join('\n');

        document.head.appendChild(style);
    };

    // Auto-inject styles when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', PersonalCrmOllama.injectStyles);
    } else {
        PersonalCrmOllama.injectStyles();
    }

    // Export
    window.PersonalCrmOllama = PersonalCrmOllama;

})(window);
