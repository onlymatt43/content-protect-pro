/**
 * Content Protect Pro - AI Admin Assistant JavaScript
 * 
 * Handles chat interactions, avatar animations, and AJAX communication.
 * Integrates with OnlyMatt avatar system for visual feedback.
 * 
 * @package ContentProtectPro
 * @since 3.1.0
 */

(function($) {
    'use strict';

    /**
     * AI Assistant Controller
     */
    const CPPAiAssistant = {
        
        /**
         * Matt avatar clips loaded from clips.json
         */
        avatarClips: {},
        
        /**
         * Current avatar state
         */
        avatarState: 'idle',
        
        /**
         * Message history for context
         */
        messageHistory: [],
        
        /**
         * Rate limit tracking
         */
        messageCount: 0,
        lastMessageTime: 0,
        
        /**
         * Initialize the assistant
         */
        init: function() {
            this.cacheElements();
            this.bindEvents();
            this.loadAvatarClips();
            this.initAutoResize();
            
            console.log('CPP AI Assistant initialized');
        },
        
        /**
         * Cache DOM elements
         */
        cacheElements: function() {
            this.$form = $('#cpp-chat-form');
            this.$input = $('#cpp-chat-input');
            this.$messages = $('#cpp-chat-messages');
            this.$sendBtn = $('#cpp-send-message');
            this.$clearBtn = $('#cpp-clear-history');
            this.$charCount = $('#cpp-char-current');
            this.$avatar = $('#cpp-avatar-video');
            this.$loading = $('#cpp-ai-loading');
            this.$statusText = $('.cpp-status-text');
            this.$statusIndicator = $('.cpp-status-indicator');
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Form submission
            this.$form.on('submit', this.handleSendMessage.bind(this));
            
            // Clear history
            this.$clearBtn.on('click', this.handleClearHistory.bind(this));
            
            // Input character count
            this.$input.on('input', this.updateCharCount.bind(this));
            
            // Quick action buttons
            $('.cpp-ai-action-btn').on('click', this.handleQuickAction.bind(this));
            
            // Help topics
            $('.cpp-ai-topic').on('click', this.handleHelpTopic.bind(this));
            
            // Enter key to send (Shift+Enter for new line)
            this.$input.on('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.$form.trigger('submit');
                }
            }.bind(this));
            
            // Avatar video events
            this.$avatar.on('ended', this.onAvatarVideoEnded.bind(this));
        },
        
        /**
         * Load avatar clips from clips.json
         */
        loadAvatarClips: function() {
            if (typeof cppAiVars === 'undefined' || !cppAiVars.avatar_clips_url) {
                console.warn('Avatar clips URL not configured');
                return;
            }
            
            $.getJSON(cppAiVars.avatar_clips_url)
                .done(function(clips) {
                    this.avatarClips = clips;
                    console.log('Avatar clips loaded:', Object.keys(clips).length);
                    
                    // Play idle animation
                    this.playAvatarClip('yocestonlymatt');
                }.bind(this))
                .fail(function() {
                    console.error('Failed to load avatar clips');
                });
        },
        
        /**
         * Play avatar video clip
         * 
         * @param {string} clipName - Clip identifier from clips.json
         */
        playAvatarClip: function(clipName) {
            if (!this.avatarClips[clipName]) {
                console.warn('Avatar clip not found:', clipName);
                return;
            }
            
            const clipPath = cppAiVars.avatar_base_url + 'clips_muted/' + this.avatarClips[clipName];
            
            this.$avatar.find('source').attr('src', clipPath);
            this.$avatar[0].load();
            this.$avatar[0].play()
                .catch(function(error) {
                    console.warn('Avatar playback failed:', error);
                });
            
            this.avatarState = clipName;
        },
        
        /**
         * Handle avatar video ended
         */
        onAvatarVideoEnded: function() {
            // Return to idle state after clip ends
            if (this.avatarState !== 'notalkmove') {
                setTimeout(function() {
                    this.playAvatarClip('notalkmove');
                }.bind(this), 500);
            }
        },
        
        /**
         * Update character count
         */
        updateCharCount: function() {
            const currentLength = this.$input.val().length;
            this.$charCount.text(currentLength);
            
            // Warn when approaching limit
            if (currentLength > 1800) {
                this.$charCount.parent().addClass('cpp-char-warning');
            } else {
                this.$charCount.parent().removeClass('cpp-char-warning');
            }
        },
        
        /**
         * Handle send message
         */
        handleSendMessage: function(e) {
            e.preventDefault();
            
            const message = this.$input.val().trim();
            
            if (!message) {
                return;
            }
            
            // Basic rate limiting (client-side)
            const now = Date.now();
            if (now - this.lastMessageTime < 2000) {
                this.showNotice(cppAiVars.strings.error, 'error');
                return;
            }
            
            this.lastMessageTime = now;
            this.messageCount++;
            
            // Add user message to UI
            this.addMessage(message, 'user');
            
            // Clear input
            this.$input.val('').trigger('input');
            
            // Show thinking state
            this.setThinkingState();
            
            // Send to backend
            this.sendMessage(message);
        },
        
        /**
         * Send message to backend
         * 
         * @param {string} message - User message
         */
        sendMessage: function(message) {
            $.ajax({
                url: cppAiVars.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'cpp_admin_chat',
                    nonce: cppAiVars.nonce,
                    message: message
                },
                success: this.handleMessageResponse.bind(this),
                error: this.handleMessageError.bind(this)
            });
        },
        
        /**
         * Handle successful message response
         */
        handleMessageResponse: function(response) {
            this.setReadyState();
            
            if (!response.success) {
                this.showNotice(response.data.message || cppAiVars.strings.error, 'error');
                return;
            }
            
            // Add AI response to UI
            this.addMessage(response.data.reply, 'assistant', response.data.metadata);
            
            // Play appropriate avatar clip
            if (response.data.avatar_clip) {
                this.playAvatarClip(response.data.avatar_clip);
            }
            
            // Store in history
            this.messageHistory.push({
                user: this.$input.val(),
                assistant: response.data.reply,
                timestamp: response.data.metadata.timestamp
            });
        },
        
        /**
         * Handle message error
         */
        handleMessageError: function(xhr, status, error) {
            this.setReadyState();
            
            let errorMessage = cppAiVars.strings.error;
            
            if (xhr.responseJSON && xhr.responseJSON.data) {
                errorMessage = xhr.responseJSON.data.message;
            }
            
            if (xhr.status === 429) {
                errorMessage = cppAiVars.strings.rate_limit;
            }
            
            this.showNotice(errorMessage, 'error');
            console.error('AJAX error:', status, error);
        },
        
        /**
         * Add message to chat UI
         * 
         * @param {string} text - Message text
         * @param {string} type - 'user' or 'assistant'
         * @param {Object} metadata - Optional metadata (timestamp, tokens, etc.)
         */
        addMessage: function(text, type, metadata) {
            const templateId = type === 'user' 
                ? '#cpp-message-template-user' 
                : '#cpp-message-template-assistant';
            
            const template = $(templateId).html();
            const $message = $(template);
            
            // Set message text (support markdown-style code blocks)
            const formattedText = this.formatMessageText(text);
            $message.find('.cpp-message-text').html(formattedText);
            
            // Set timestamp
            const timestamp = metadata && metadata.timestamp 
                ? this.formatTimestamp(metadata.timestamp)
                : this.formatTimestamp(new Date());
            $message.find('.cpp-message-time').text(timestamp);
            
            // Add metadata badge if present
            if (metadata && metadata.tokens) {
                const $badge = $('<span class="cpp-token-badge"></span>')
                    .text(metadata.tokens + ' tokens')
                    .attr('title', 'Tokens used: ' + metadata.tokens);
                $message.find('.cpp-message-header').append($badge);
            }
            
            // Append and scroll
            this.$messages.append($message);
            this.scrollToBottom();
            
            // Syntax highlighting for code blocks
            this.highlightCodeBlocks($message);
        },
        
        /**
         * Format message text with code blocks and line breaks
         * 
         * @param {string} text - Raw text
         * @return {string} HTML formatted text
         */
        formatMessageText: function(text) {
            // Escape HTML first
            text = $('<div>').text(text).html();
            
            // Convert markdown code blocks ```language\ncode\n```
            text = text.replace(/```(\w+)?\n([\s\S]+?)\n```/g, function(match, lang, code) {
                lang = lang || 'plaintext';
                return '<pre><code class="language-' + lang + '">' + code + '</code></pre>';
            });
            
            // Convert inline code `code`
            text = text.replace(/`([^`]+)`/g, '<code>$1</code>');
            
            // Convert line breaks
            text = text.replace(/\n/g, '<br>');
            
            // Convert bold **text**
            text = text.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
            
            // Convert links [text](url)
            text = text.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>');
            
            return text;
        },
        
        /**
         * Highlight code blocks with syntax highlighting
         * 
         * @param {jQuery} $message - Message element
         */
        highlightCodeBlocks: function($message) {
            $message.find('pre code').each(function() {
                const $code = $(this);
                const language = $code.attr('class').replace('language-', '');
                
                // Add language label
                $code.parent().prepend(
                    '<div class="cpp-code-header">' +
                    '<span class="cpp-code-lang">' + language + '</span>' +
                    '<button class="cpp-copy-code button button-small" title="Copy code">' +
                    '<span class="dashicons dashicons-clipboard"></span>' +
                    '</button>' +
                    '</div>'
                );
                
                // Bind copy button
                $code.parent().find('.cpp-copy-code').on('click', function() {
                    const code = $code.text();
                    navigator.clipboard.writeText(code).then(function() {
                        $(this).html('<span class="dashicons dashicons-yes"></span>');
                        setTimeout(function() {
                            $(this).html('<span class="dashicons dashicons-clipboard"></span>');
                        }.bind(this), 2000);
                    }.bind(this));
                }.bind(this));
            });
        },
        
        /**
         * Format timestamp
         * 
         * @param {string|Date} timestamp
         * @return {string} Formatted time
         */
        formatTimestamp: function(timestamp) {
            const date = timestamp instanceof Date ? timestamp : new Date(timestamp);
            return date.toLocaleTimeString('en-US', { 
                hour: 'numeric', 
                minute: '2-digit',
                hour12: true
            });
        },
        
        /**
         * Set thinking state
         */
        setThinkingState: function() {
            this.$loading.fadeIn(200);
            this.$sendBtn.prop('disabled', true);
            this.$input.prop('disabled', true);
            this.$statusText.text(cppAiVars.strings.thinking);
            this.$statusIndicator.addClass('cpp-status-thinking');
            
            // Play thinking animation
            this.playAvatarClip('notalkmove');
        },
        
        /**
         * Set ready state
         */
        setReadyState: function() {
            this.$loading.fadeOut(200);
            this.$sendBtn.prop('disabled', false);
            this.$input.prop('disabled', false).focus();
            this.$statusText.text('Ready');
            this.$statusIndicator.removeClass('cpp-status-thinking');
        },
        
        /**
         * Scroll chat to bottom
         */
        scrollToBottom: function() {
            this.$messages.animate({
                scrollTop: this.$messages[0].scrollHeight
            }, 300);
        },
        
        /**
         * Handle clear history
         */
        handleClearHistory: function(e) {
            e.preventDefault();
            
            if (!confirm('Clear all chat history? This cannot be undone.')) {
                return;
            }
            
            $.ajax({
                url: cppAiVars.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'cpp_admin_clear_history',
                    nonce: cppAiVars.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Clear UI (keep welcome message)
                        this.$messages.find('.cpp-chat-message:not(.cpp-welcome-message)').remove();
                        this.messageHistory = [];
                        this.showNotice(cppAiVars.strings.cleared, 'success');
                    }
                }.bind(this),
                error: function() {
                    this.showNotice('Failed to clear history', 'error');
                }.bind(this)
            });
        },
        
        /**
         * Handle quick action buttons
         */
        handleQuickAction: function(e) {
            e.preventDefault();
            
            const action = $(e.currentTarget).data('action');
            const messages = {
                'diagnose-videos': 'Why is my video library not showing videos? Can you diagnose the issue?',
                'check-sessions': 'Show me all active sessions and any potential issues.',
                'review-errors': 'What are the most recent errors in my system and how can I fix them?',
                'generate-code': 'I need help writing custom PHP code for Content Protect Pro. What can you help me with?'
            };
            
            if (messages[action]) {
                this.$input.val(messages[action]).trigger('input');
                this.$form.trigger('submit');
            }
        },
        
        /**
         * Handle help topic clicks
         */
        handleHelpTopic: function(e) {
            e.preventDefault();
            
            const topic = $(e.currentTarget).data('topic');
            const messages = {
                'presto-integration': 'How do I properly integrate Presto Player with Content Protect Pro?',
                'session-management': 'Explain how session management works and how to troubleshoot session issues.',
                'gift-codes': 'How does the gift code system work? How do I create and manage codes?',
                'security': 'What are the security best practices I should follow with Content Protect Pro?'
            };
            
            if (messages[topic]) {
                this.$input.val(messages[topic]).trigger('input');
            }
        },
        
        /**
         * Initialize auto-resize for textarea
         */
        initAutoResize: function() {
            this.$input.on('input', function() {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 200) + 'px';
            });
        },
        
        /**
         * Show admin notice
         * 
         * @param {string} message
         * @param {string} type - 'success', 'error', 'warning', 'info'
         */
        showNotice: function(message, type) {
            const $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.wrap > h1').after($notice);
            
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        }
    };

    /**
     * Initialize when DOM ready
     */
    $(document).ready(function() {
        // Only initialize on AI Assistant page
        if ($('#cpp-chat-form').length) {
            CPPAiAssistant.init();
        }
    });

})(jQuery);