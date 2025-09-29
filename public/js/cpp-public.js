/**
 * Content Protect Pro - Public JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        CPP_Public.init();
    });

    var CPP_Public = {
        init: function() {
            this.bindEvents();
            this.initVideoPlayers();
        },

        bindEvents: function() {
            // Gift code form submission
            $(document).on('submit', '#cpp-giftcode-form', this.handleGiftCodeSubmission);
            
            // Video player initialization
            $(document).on('click', '.cpp-video-play', this.initVideoPlayer);
            
            // Auto-submit on enter
            $(document).on('keypress', '.cpp-giftcode input', function(e) {
                if (e.which === 13) {
                    $(this).closest('form').submit();
                }
            });
        },

        handleGiftCodeSubmission: function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var $input = $form.find('#cpp-giftcode');
            var $button = $form.find('button[type="submit"]');
            var $message = $form.find('#cpp-giftcode-message');
            var code = $input.val().trim();
            
            if (!code) {
                CPP_Public.showMessage($message, 'error', 'Please enter a gift code.');
                return;
            }
            
            // Show loading state
            $button.prop('disabled', true);
            $button.html('<span class="cpp-loading-spinner"></span> ' + cpp_public_ajax.strings.loading);
            
            // Hide previous messages
            $message.hide();
            
            $.ajax({
                url: cpp_public_ajax.ajax_url,
                method: 'POST',
                data: {
                    action: 'cpp_validate_giftcode',
                    code: code,
                    nonce: cpp_public_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        CPP_Public.showMessage($message, 'success', response.data.message);
                        
                        // Clear form
                        $input.val('');
                        
                        // Redirect if specified
                        if (response.data.redirect_url) {
                            setTimeout(function() {
                                window.location.href = response.data.redirect_url;
                            }, 1500);
                        } else {
                            // Refresh page to show newly accessible content
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        }
                    } else {
                        CPP_Public.showMessage($message, 'error', response.data.message);
                    }
                },
                error: function() {
                    CPP_Public.showMessage($message, 'error', cpp_public_ajax.strings.error);
                },
                complete: function() {
                    $button.prop('disabled', false);
                    $button.html('Validate Code');
                }
            });
        },

        initVideoPlayers: function() {
            $('.cpp-video-container').each(function() {
                var $container = $(this);
                var videoId = $container.data('video-id');
                
                if (!videoId) return;
                
                CPP_Public.loadVideoPlayer($container, videoId);
            });
        },

        loadVideoPlayer: function($container, videoId) {
            var $player = $container.find('[id^="cpp-video-"]');
            
            // Show loading
            $player.html('<div class="cpp-video-overlay"><span class="cpp-loading-spinner"></span> Loading video...</div>');
            
            $.ajax({
                url: cpp_public_ajax.ajax_url,
                method: 'POST',
                data: {
                    action: 'cpp_get_video_token',
                    video_id: videoId,
                    nonce: cpp_public_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        CPP_Public.renderVideoPlayer($player, videoId, response.data.token);
                    } else {
                        $player.html('<div class="cpp-video-overlay">Error loading video: ' + response.data.message + '</div>');
                    }
                },
                error: function() {
                    $player.html('<div class="cpp-video-overlay">Error loading video. Please try again.</div>');
                }
            });
        },

        renderVideoPlayer: function($player, videoId, token) {
            // This is a placeholder for video player rendering
            // In a real implementation, you would integrate with your video service
            
            var videoHtml = '<video controls preload="metadata">';
            videoHtml += '<source src="/path/to/video/' + videoId + '?token=' + token + '" type="video/mp4">';
            videoHtml += '<p>Your browser does not support the video tag.</p>';
            videoHtml += '</video>';
            
            $player.html(videoHtml);
            
            // Initialize video player with additional features
            var video = $player.find('video')[0];
            if (video) {
                video.addEventListener('loadstart', function() {
                    CPP_Public.trackVideoEvent('video_load_start', videoId);
                });
                
                video.addEventListener('play', function() {
                    CPP_Public.trackVideoEvent('video_play', videoId);
                });
                
                video.addEventListener('pause', function() {
                    CPP_Public.trackVideoEvent('video_pause', videoId);
                });
                
                video.addEventListener('ended', function() {
                    CPP_Public.trackVideoEvent('video_ended', videoId);
                });
            }
        },

        trackVideoEvent: function(eventType, videoId) {
            $.ajax({
                url: cpp_public_ajax.ajax_url,
                method: 'POST',
                data: {
                    action: 'cpp_track_video_event',
                    event_type: eventType,
                    video_id: videoId,
                    nonce: cpp_public_ajax.nonce
                }
            });
        },

        showMessage: function($messageEl, type, message) {
            $messageEl.removeClass('success error info')
                     .addClass(type)
                     .html(message)
                     .show();
            
            // Auto-hide success messages
            if (type === 'success') {
                setTimeout(function() {
                    $messageEl.fadeOut();
                }, 5000);
            }
        },

        // Utility function for form validation
        validateGiftCode: function(code) {
            if (!code || code.length < 4) {
                return {
                    valid: false,
                    message: 'Gift code must be at least 4 characters long.'
                };
            }
            
            // Basic format validation (alphanumeric)
            if (!/^[A-Za-z0-9]+$/.test(code)) {
                return {
                    valid: false,
                    message: 'Gift code can only contain letters and numbers.'
                };
            }
            
            return { valid: true };
        }
    };

    // Expose some functions globally for shortcode usage
    window.CPP_Public = CPP_Public;

})(jQuery);