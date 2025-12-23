/**
 * Personal CRM Local LLM Client
 *
 * Centralized client for Local LLM integration (Ollama, LM Studio).
 * Handles connection testing, CORS error detection, and provides clear setup instructions.
 *
 * Usage:
 *   const llm = new PersonalCrmLocalLLM({ provider: 'ollama', model: 'llama3.2' });
 *
 *   // Check connection first
 *   const status = await llm.checkConnection();
 *   if (!status.connected) {
 *       // Show status.instructions to user
 *   }
 *
 *   // Stream a chat response
 *   await llm.chatStream(messages, {
 *       onChunk: (text) => { outputDiv.textContent += text; },
 *       onError: (error) => { console.error(error); },
 *       onComplete: () => { console.log('Done'); }
 *   });
 */
(function(window) {
    'use strict';

    var PROVIDERS = {
        ollama: {
            name: 'Ollama',
            baseUrl: 'http://localhost:11434',
            website: 'https://ollama.ai',
            modelsPath: '/api/tags',
            chatPath: '/api/chat',
            modelsKey: 'models',
            getModelName: function(model) { return model.name; }
        },
        lm_studio: {
            name: 'LM Studio',
            baseUrl: 'http://localhost:1234',
            website: 'https://lmstudio.ai',
            modelsPath: '/v1/models',
            chatPath: '/v1/chat/completions',
            modelsKey: 'data',
            getModelName: function(model) { return model.id; }
        }
    };

    function PersonalCrmLocalLLM(options) {
        options = options || {};
        this.provider = options.provider || 'ollama';
        this.providerConfig = PROVIDERS[this.provider] || PROVIDERS.ollama;
        this.baseUrl = options.baseUrl || this.providerConfig.baseUrl;
        this.model = options.model || 'llama3.2';
        this._connectionStatus = null;
    }

    PersonalCrmLocalLLM.PROVIDERS = PROVIDERS;

    PersonalCrmLocalLLM.prototype.getSettingsUrl = function() {
        return (window.personalCrmLocalLLM && window.personalCrmLocalLLM.settingsUrl) || '/wp-admin/options-general.php?page=personal-crm';
    };

    PersonalCrmLocalLLM.prototype.checkConnection = async function() {
        var self = this;
        try {
            var response = await fetch(this.baseUrl + this.providerConfig.modelsPath, {
                method: 'GET',
                signal: AbortSignal.timeout(5000)
            });

            if (!response.ok) {
                this._connectionStatus = {
                    connected: false,
                    error: 'http_error',
                    message: this.providerConfig.name + ' returned HTTP ' + response.status
                };
                return this._connectionStatus;
            }

            var data = await response.json();
            var rawModels = data[this.providerConfig.modelsKey] || [];
            var models = rawModels.map(function(m) {
                return { name: self.providerConfig.getModelName(m) };
            });

            this._connectionStatus = {
                connected: true,
                models: models,
                modelCount: models.length,
                hasSelectedModel: models.some(function(m) {
                    return m.name === self.model || m.name.startsWith(self.model + ':');
                })
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
                    ? 'Browser access blocked (CORS). ' + this.providerConfig.name + ' needs to allow browser requests.'
                    : 'Cannot connect to ' + this.providerConfig.name + '. Is it running?',
                instructions: this._getInstructions(isCors ? 'cors' : 'not_running')
            };

            return this._connectionStatus;
        }
    };

    PersonalCrmLocalLLM.prototype._getInstructions = function(errorType) {
        var instructions = {
            title: this.providerConfig.name + ' Setup Required',
            steps: []
        };

        if (errorType === 'not_running') {
            if (this.provider === 'ollama') {
                instructions.steps = [
                    { text: 'Install Ollama from ollama.ai', url: 'https://ollama.ai' },
                    { text: 'Start Ollama by running:', code: 'ollama serve' },
                    { text: 'Pull a model:', code: 'ollama pull ' + this.model }
                ];
            } else if (this.provider === 'lm_studio') {
                instructions.steps = [
                    { text: 'Install LM Studio from lmstudio.ai', url: 'https://lmstudio.ai' },
                    { text: 'Open LM Studio and download a model' },
                    { text: 'Go to the "Local Server" tab (left sidebar)' },
                    { text: 'Enable "Enable CORS" toggle in server settings' },
                    { text: 'Click "Start Server" (runs on port 1234)' }
                ];
            }
        } else if (errorType === 'cors') {
            instructions.title = this.providerConfig.name + ' CORS Configuration Required';

            if (this.provider === 'ollama') {
                instructions.steps = [
                    { text: 'Stop Ollama if it\'s running' },
                    { text: 'Restart Ollama with browser access enabled:' },
                    { text: 'On macOS/Linux:', code: 'OLLAMA_ORIGINS=* ollama serve' },
                    { text: 'Or set it permanently:', code: 'launchctl setenv OLLAMA_ORIGINS "*"', hint: 'Then restart Ollama' },
                    { text: 'On Windows, set environment variable OLLAMA_ORIGINS to * before starting Ollama' }
                ];
            } else if (this.provider === 'lm_studio') {
                instructions.steps = [
                    { text: 'Open LM Studio' },
                    { text: 'Go to the "Local Server" tab (left sidebar)' },
                    { text: 'Enable the "Enable CORS" toggle' },
                    { text: 'Restart the server (Stop → Start)' }
                ];
            }

            instructions.moreInfo = {
                text: 'See full setup instructions',
                url: this.getSettingsUrl()
            };
        }

        return instructions;
    };

    PersonalCrmLocalLLM.prototype.renderInstructions = function(container, status) {
        status = status || this._connectionStatus;
        if (!status || !status.instructions) return;

        var inst = status.instructions;

        if (typeof container === 'string') {
            container = document.querySelector(container);
        }
        if (!container) return;

        container.replaceChildren();

        var wrapper = document.createElement('div');
        wrapper.className = 'local-llm-instructions';

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

    PersonalCrmLocalLLM.prototype._buildChatRequest = function(messages, stream) {
        if (this.provider === 'lm_studio') {
            return {
                model: this.model,
                messages: messages,
                stream: stream
            };
        }
        return {
            model: this.model,
            messages: messages,
            stream: stream
        };
    };

    PersonalCrmLocalLLM.prototype._parseStreamChunk = function(line) {
        if (this.provider === 'lm_studio') {
            if (line.startsWith('data: ')) {
                line = line.slice(6);
            }
            if (line === '[DONE]') return null;
            try {
                var data = JSON.parse(line);
                if (data.choices && data.choices[0] && data.choices[0].delta && data.choices[0].delta.content) {
                    return data.choices[0].delta.content;
                }
            } catch (e) {
                return null;
            }
        } else {
            try {
                var data = JSON.parse(line);
                if (data.message && data.message.content) {
                    return data.message.content;
                }
            } catch (e) {
                return null;
            }
        }
        return null;
    };

    PersonalCrmLocalLLM.prototype._parseChatResponse = function(data) {
        if (this.provider === 'lm_studio') {
            if (data.choices && data.choices[0] && data.choices[0].message) {
                return data.choices[0].message.content;
            }
        } else {
            if (data.message && data.message.content) {
                return data.message.content;
            }
        }
        return null;
    };

    PersonalCrmLocalLLM.prototype.chatStream = async function(messages, callbacks) {
        callbacks = callbacks || {};
        var onChunk = callbacks.onChunk || function() {};
        var onError = callbacks.onError || function() {};
        var onComplete = callbacks.onComplete || function() {};
        var self = this;

        try {
            var response = await fetch(this.baseUrl + this.providerConfig.chatPath, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(this._buildChatRequest(messages, true))
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
                    var line = lines[i].trim();
                    if (line === '') continue;

                    var content = self._parseStreamChunk(line);
                    if (content) {
                        fullText += content;
                        onChunk(content, fullText);
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
                    : 'Could not connect to ' + this.providerConfig.name + ': ' + error.message,
                instructions: this._getInstructions(isCors ? 'cors' : 'not_running')
            };

            onError(errorInfo);
            return errorInfo;
        }
    };

    PersonalCrmLocalLLM.prototype.chat = async function(messages) {
        try {
            var response = await fetch(this.baseUrl + this.providerConfig.chatPath, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(this._buildChatRequest(messages, false))
            });

            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }

            var data = await response.json();
            var content = this._parseChatResponse(data);

            return {
                success: true,
                message: content,
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

    PersonalCrmLocalLLM.prototype.listModels = async function() {
        var status = await this.checkConnection();
        if (status.connected) {
            return { success: true, models: status.models };
        }
        return { success: false, error: status.error, message: status.message };
    };

    PersonalCrmLocalLLM.injectStyles = function() {
        if (document.getElementById('personal-crm-local-llm-styles')) return;

        var style = document.createElement('style');
        style.id = 'personal-crm-local-llm-styles';
        style.textContent = [
            '.local-llm-instructions {',
            '    padding: 15px;',
            '    background: #fff8e5;',
            '    border-left: 4px solid #ffb900;',
            '    margin: 10px 0;',
            '    border-radius: 0 4px 4px 0;',
            '}',
            '.local-llm-instructions ol {',
            '    margin: 10px 0 0 20px;',
            '    padding: 0;',
            '}',
            '.local-llm-instructions li {',
            '    margin: 8px 0;',
            '}',
            '.local-llm-instructions code {',
            '    background: #f5f5f5;',
            '    padding: 2px 6px;',
            '    border-radius: 3px;',
            '    font-family: monospace;',
            '}',
            '.local-llm-instructions .hint {',
            '    font-size: 12px;',
            '    color: #666;',
            '}'
        ].join('\n');

        document.head.appendChild(style);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', PersonalCrmLocalLLM.injectStyles);
    } else {
        PersonalCrmLocalLLM.injectStyles();
    }

    window.PersonalCrmLocalLLM = PersonalCrmLocalLLM;

})(window);
