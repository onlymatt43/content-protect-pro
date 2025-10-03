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
            // cache modal reference used by many helpers
            var $modal = jQuery('#cpp-video-modal');
            // If modal does not exist, create it dynamically and append to body
            if (!$modal.length) {
                try {
                    var modalHtml = '<div id="cpp-video-modal" class="cpp-modal" aria-hidden="true">'
                                  + '<div class="cpp-modal-overlay"></div>'
                                  + '<div class="cpp-modal-content" role="dialog" aria-modal="true">'
                                  + '<div class="cpp-modal-header"><h3 id="cpp-modal-title"></h3>'
                                  + '<button class="cpp-modal-close" type="button" aria-label="Close">&times;</button></div>'
                                  + '<div class="cpp-modal-body"><div id="cpp-modal-video-container" class="cpp-modal-inner"></div></div>'
                                  + '</div></div>';
                    jQuery(document.body).append(modalHtml);
                    $modal = jQuery('#cpp-video-modal');
                } catch (e) {
                    // noop
                }
            }
            // If the modal exists but is inserted inside a page-builder/container that
            // applies transforms, a fixed-position modal may be clipped or only cover
            // the container (producing the "white bar"). Move it to document.body so
            // fixed positioning covers the full viewport.
            try {
                if ($modal.length && $modal.parent().get(0) !== document.body) {
                    $modal.appendTo(document.body);
                }
            } catch (e) {
                // noop - if DOM operations fail, continue gracefully
            }
            window.__cpp_modal = $modal;
            window.__cpp_modal.attr('aria-hidden', 'true'); // Modal is hidden until content is ready
            this.initVideoPlayers();
            this.bindEvents();
        },

        bindEvents: function() {
            // Gift code form submission
            $(document).on('submit', '#cpp-giftcode-form', this.handleGiftCodeSubmission);
            // Inline gift code forms (content and video protection)
            $(document).on('submit', '.cpp-giftcode-form, .cpp-video-giftcode-form', CPP_Public.handleInlineGiftCodeForm);
            
            // Video player initialization
            // Ensure a valid function reference is bound; use object method explicitly
            $(document).on('click', '.cpp-video-play', CPP_Public.initVideoPlayer);
            
            // Auto-submit on enter: submit the closest form when Enter is pressed
            $(document).on('keypress', '.cpp-giftcode input', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    $(this).closest('form').trigger('submit');
                }
            });

            // Search handler for gallery
            $(document).on('input', '#cpp-video-search', function() {
                var searchTerm = jQuery(this).val().toLowerCase();
                jQuery('.cpp-video-item').each(function() {
                    var title = jQuery(this).find('h4').text().toLowerCase();
                    var description = jQuery(this).find('p').text().toLowerCase();
                    if (title.indexOf(searchTerm) !== -1 || description.indexOf(searchTerm) !== -1) {
                        jQuery(this).show();
                    } else {
                        jQuery(this).hide();
                    }
                });
            });

            // Filter buttons
            $(document).on('click', '.cpp-filter-btn', function() {
                jQuery('.cpp-filter-btn').removeClass('active');
                jQuery(this).addClass('active');
                var filter = jQuery(this).data('filter');
                if (filter === 'all') {
                    jQuery('.cpp-video-item').show();
                } else {
                    jQuery('.cpp-video-item').each(function() {
                        var integration = jQuery(this).data('integration');
                        if (integration === filter) jQuery(this).show(); else jQuery(this).hide();
                    });
                }
            });
        },

        // Click handler to initialize a specific video player on demand
        initVideoPlayer: function(e) {
            if (e && typeof e.preventDefault === 'function') {
                e.preventDefault();
            }

            var $trigger = $(this);
            // Support buttons/links with data-video-id or nested inside containers
            var videoId = $trigger.data('video-id');
            var $container = $trigger.closest('.cpp-video-container, .cpp-video-player');

            if (!videoId && $container.length) {
                videoId = $container.data('video-id');
            }

            if (!$container.length) {
                // As a fallback, try to locate by id pattern
                var idMatch = ($trigger.attr('href') || '').match(/cpp-video-(\w+)/);
                if (idMatch && idMatch[1]) {
                    videoId = videoId || idMatch[1];
                    $container = $('#cpp-video-' + idMatch[1]).closest('.cpp-video-container, .cpp-video-player');
                }
            }

            if (!$container.length || !videoId) {
                if (window.console && console.warn) {
                    console.warn('[CPP] Unable to initialize video player: missing container or videoId');
                }
                return;
            }

            // If there is an overlay/access form, hide it and show the player area
            $container.find('.cpp-video-access-form, .cpp-access-overlay').hide();
            $container.find('.cpp-video-player-container').show();

            CPP_Public.loadVideoPlayer($container, videoId);
            // ensure modal is hidden when initializing an inline player
            (window.__cpp_modal || jQuery('#cpp-video-modal')).attr('aria-hidden', 'true');
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

        // Handle inline gift code forms for protected content and videos
        handleInlineGiftCodeForm: function(e) {
            e.preventDefault();

            var $form = $(this);
            var $container = $form.closest('.cpp-protected-content, .cpp-video-player');
            var $button = $form.find('button[type="submit"]');
            var $message = $container.find('.cpp-access-message, .cpp-video-message').first();
            var codeInput = $form.find('input[name="gift_code"]');
            var code = codeInput.val().trim();

            if (!code) {
                if ($message.length) {
                    CPP_Public.showMessage($message, 'error', cpp_public_ajax.strings ? (cpp_public_ajax.strings.invalid_code || 'Invalid gift code.') : 'Invalid gift code.');
                }
                return;
            }

            // Show loading state
            $button.prop('disabled', true);
            var origText = $button.text();
            $button.html('<span class="cpp-loading-spinner"></span> ' + (cpp_public_ajax.strings ? cpp_public_ajax.strings.loading : 'Loading...'));
            if ($message.length) $message.hide();

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
                        if ($message.length) CPP_Public.showMessage($message, 'success', response.data.message || 'Access granted.');

                        // Clear input
                        codeInput.val('');

                        // Reveal content or initialize video
                        if ($container.hasClass('cpp-protected-content')) {
                            $container.find('.cpp-access-form').hide();
                            $container.find('.cpp-protected-content-inner').show();
                        } else if ($container.hasClass('cpp-video-player')) {
                            // Hide overlay and init player
                            $container.find('.cpp-video-access-form, .cpp-access-overlay').hide();
                            $container.find('.cpp-video-player-container').show();
                            var videoId = $container.data('video-id');
                            if (videoId) {
                                CPP_Public.loadVideoPlayer($container, videoId);
                            }
                        }
                    } else {
                        if ($message.length) CPP_Public.showMessage($message, 'error', response.data && response.data.message ? response.data.message : (cpp_public_ajax.strings ? cpp_public_ajax.strings.error : 'An error occurred.'));
                    }
                },
                error: function() {
                    if ($message.length) CPP_Public.showMessage($message, 'error', cpp_public_ajax.strings ? cpp_public_ajax.strings.error : 'An error occurred.');
                },
                complete: function() {
                    $button.prop('disabled', false).text(origText);
                }
            });
        },

        initVideoPlayers: function() {
            // Initialize players rendered via [cpp_protected_video] (already validated server-side)
            $('.cpp-video-container').each(function() {
                var $container = $(this);
                var videoId = $container.data('video-id');
                if (!videoId) return;
                CPP_Public.loadVideoPlayer($container, videoId);
            });
        },

        loadVideoPlayer: function($container, videoId) {
            var $player = $container.find('[id^="cpp-video-"]');
            if (!$player.length) {
                // Fallback to generic player container used by protection manager
                $player = $container.find('.cpp-video-player-container');
            }
            if (!$player.length) {
                // Last resort: treat container itself as player area
                $player = $container;
            }

            // Show loading (use localized string if available)
            var loadingText = (window.cpp_public_ajax && cpp_public_ajax.strings && cpp_public_ajax.strings.loading) ? cpp_public_ajax.strings.loading : 'Loading...';
            $player.html('<div class="cpp-video-overlay"><span class="cpp-loading-spinner"></span> ' + loadingText + '</div>');
            
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
                        var meta = $.extend({}, response.data || {});
                        CPP_Public.renderVideoPlayer($player, videoId, response.data.token, meta);
                    } else {
                        $player.html('<div class="cpp-video-overlay">Error loading video: ' + response.data.message + '</div>');
                    }
                },
                error: function() {
                    $player.html('<div class="cpp-video-overlay">Error loading video. Please try again.</div>');
                }
            });
        },

        renderVideoPlayer: function($player, videoId, token, meta) {
            meta = meta || {};

            // If server provided overlay image or purchase URL for this session/code, store on container
            try {
                if (meta.overlay_image) {
                    $player.data('overlay-image', meta.overlay_image);
                }
                if (meta.purchase_url) {
                    $player.data('purchase-url', meta.purchase_url);
                }
            } catch (e) {}

            // If server returned a full Presto embed, inject it.
            if (meta.provider === 'presto' && meta.presto_embed) {
                $player.html(meta.presto_embed);
                CPP_Public.trackVideoEvent('video_load_start', videoId);
                return;
            }

            // DRM path removed (using token-based HLS only)

            // Direct URL provider
            if (meta.provider === 'direct' && meta.url) {
                var directHtml = '<video controls playsinline preload="metadata" style="width:100%;height:100%">';
                directHtml += '<source src="' + meta.url + '" type="' + (meta.mime || 'video/mp4') + '">';
                directHtml += '<p>Your browser does not support the video tag.</p>';
                directHtml += '</video>';
                $player.html(directHtml);
                var directEl = $player.find('video')[0];
                if (directEl) {
                    directEl.addEventListener('loadstart', function() { CPP_Public.trackVideoEvent('video_load_start', videoId); });
                    directEl.addEventListener('play', function() { CPP_Public.trackVideoEvent('video_play', videoId); });
                    directEl.addEventListener('pause', function() { CPP_Public.trackVideoEvent('video_pause', videoId); });
                    directEl.addEventListener('ended', function() { CPP_Public.trackVideoEvent('video_ended', videoId); });
                }
                return;
            }

            // If server returned a Bunny signed HLS URL, render an HTML5 video with HLS.
            if (meta.provider === 'bunny' && meta.signed_url) {
                var hlsUrl = meta.signed_url;
                var videoHtml = '<video controls playsinline preload="metadata" style="width:100%;height:100%"></video>';
                $player.html(videoHtml);
                var videoEl = $player.find('video')[0];

                // Basic HLS support: Safari plays HLS natively; others may need hls.js (optional here)
                if (window.Hls && window.Hls.isSupported()) {
                    var hls = new Hls();
                    hls.loadSource(hlsUrl);
                    hls.attachMedia(videoEl);
                    // store reference for later cleanup if session expires
                    videoEl._cpp_hls = hls;
                } else if (videoEl.canPlayType('application/vnd.apple.mpegURL')) {
                    videoEl.src = hlsUrl;
                } else {
                    // Fallback: simple source tag; for best results, load hls.js in theme when needed
                    var source = document.createElement('source');
                    source.src = hlsUrl;
                    source.type = meta.mime || 'application/x-mpegURL';
                    videoEl.appendChild(source);
                }

                // Attach tracking
                videoEl.addEventListener('loadstart', function() { CPP_Public.trackVideoEvent('video_load_start', videoId); });
                videoEl.addEventListener('play', function() { CPP_Public.trackVideoEvent('video_play', videoId); });
                videoEl.addEventListener('pause', function() { CPP_Public.trackVideoEvent('video_pause', videoId); });
                videoEl.addEventListener('ended', function() { CPP_Public.trackVideoEvent('video_ended', videoId); });
                // Start session polling while this video element is active
                CPP_Public.SessionMonitor.watch(videoEl, videoId);
                return;
            }

            // Legacy fallback: tokenized MP4 URL (custom implementation)
            var videoHtml = '<video controls preload="metadata">';
            videoHtml += '<source src="/path/to/video/' + videoId + '?token=' + (token || '') + '" type="video/mp4">';
            videoHtml += '<p>Your browser does not support the video tag.</p>';
            videoHtml += '</video>';
            
            $player.html(videoHtml);

            var video = $player.find('video')[0];
            if (video) {
                video.addEventListener('loadstart', function() { CPP_Public.trackVideoEvent('video_load_start', videoId); });
                video.addEventListener('play', function() { CPP_Public.trackVideoEvent('video_play', videoId); });
                video.addEventListener('pause', function() { CPP_Public.trackVideoEvent('video_pause', videoId); });
                video.addEventListener('ended', function() { CPP_Public.trackVideoEvent('video_ended', videoId); });
                // Start session polling while this video element is active
                CPP_Public.SessionMonitor.watch(video, videoId);
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
        ,
        /*
         * Modal preview helpers for the gallery. These use the localized
         * `cpp_public_ajax` object (ajax_url + nonce) provided by PHP.
         */
        openVideoModal: function(videoId) {
            if (!videoId) return;
            var $modal = window.__cpp_modal || jQuery('#cpp-video-modal');
            var $container = jQuery('#cpp-modal-video-container');
            var $title = jQuery('#cpp-modal-title');

            $title.text('');
            $container.html('<div class="cpp-loading-spinner"></div>');
            // keep modal hidden until we have content to show to avoid an empty white bar
            $modal.attr('aria-hidden', 'true');

            var previousActive = document.activeElement;
            jQuery.ajax({
                url: (window.cpp_public_ajax && cpp_public_ajax.ajax_url) ? cpp_public_ajax.ajax_url : '/wp-admin/admin-ajax.php',
                method: 'POST',
                data: {
                    action: 'cpp_get_video_preview',
                    video_id: videoId,
                    nonce: (window.cpp_public_ajax && cpp_public_ajax.nonce) ? cpp_public_ajax.nonce : ''
                },
                success: function(resp) {
                    if (resp && resp.success && resp.data) {
                        $title.text(resp.data.title || '');
                        var html = resp.data.html || '';
                        if (html.trim() !== '') {
                                            $container.html(html);
                                            // Ensure modal is attached to body and force viewport-fixed inline styles to
                                            // avoid page-builder transforms clipping the fixed element (the "white bar").
                                            try {
                                                if ($modal.length && $modal.parent().get(0) !== document.body) {
                                                    $modal.appendTo(document.body);
                                                }
                                                $modal.css({
                                                    position: 'fixed',
                                                    top: 0,
                                                    left: 0,
                                                    right: 0,
                                                    bottom: 0,
                                                    display: 'flex',
                                                    'z-index': 99999
                                                });
                                                $modal.find('.cpp-modal-overlay').css({ position: 'absolute', top: 0, left: 0, right: 0, bottom: 0, background: 'rgba(0,0,0,0.65)' });
                                            } catch (e) {
                                                // noop
                                            }
                                            // show modal now that we have content
                                            $modal.attr('aria-hidden', 'false');
                                            try { document.body.classList.add('cpp-modal-open'); document.body.style.overflow = 'hidden'; } catch (e) {}
                                            // focus first focusable element inside modal
                                            try {
                                                var focusable = $modal.find('button, a, input, [tabindex]:not([tabindex="-1"])').filter(':visible').first();
                                                if (focusable && focusable.length) focusable.get(0).focus();
                                            } catch (e) {}
                                            // store previous active element for restore
                                            $modal.data('cpp-prev-active', previousActive);
                        } else {
                            $container.html('<div class="cpp-modal-error">' + (resp && resp.data && resp.data.message ? resp.data.message : 'Aucun aperçu disponible.') + '</div>');
                            $modal.attr('aria-hidden', 'false');
                        }
                    } else {
                            $container.html('<div class="cpp-modal-error">' + (resp && resp.data && resp.data.message ? resp.data.message : 'Unable to load preview.') + '</div>');
                                $modal.attr('aria-hidden', 'false');
                                try { document.body.classList.add('cpp-modal-open'); document.body.style.overflow = 'hidden'; } catch (e) {}
                    }
                },
                error: function() {
                    $container.html('<div class="cpp-modal-error">Unable to load preview.</div>');
                    try {
                        $modal.css({ position: 'fixed', top: 0, left: 0, right: 0, bottom: 0, display: 'flex', 'z-index': 99999 });
                        $modal.find('.cpp-modal-overlay').css({ position: 'absolute', top: 0, left: 0, right: 0, bottom: 0, background: 'rgba(0,0,0,0.65)' });
                    } catch (e) {}
                    $modal.attr('aria-hidden', 'false');
                    try { document.body.classList.add('cpp-modal-open'); document.body.style.overflow = 'hidden'; } catch (e) {}
                    try {
                        var focusable2 = $modal.find('button, a, input, [tabindex]:not([tabindex="-1"])').filter(':visible').first();
                        if (focusable2 && focusable2.length) focusable2.get(0).focus();
                    } catch (e) {}
                }
            });
        },

        closeVideoModal: function() {
            var $modal = window.__cpp_modal || jQuery('#cpp-video-modal');
            var $container = jQuery('#cpp-modal-video-container');
            $container.empty();
            // Use aria-hidden to let scoped CSS hide the modal (avoids jumpy rendering under builders)
            $modal.attr('aria-hidden', 'true');
            try { document.body.classList.remove('cpp-modal-open'); } catch (e) {}
            // restore body scroll
            try { document.body.style.overflow = ''; } catch (e) {}
            // Also hide the modal display in case inline styles were applied
            try { $modal.css('display', 'none'); } catch (e) {}
            // restore focus
            try {
                var prev = $modal.data('cpp-prev-active');
                if (prev && typeof prev.focus === 'function') prev.focus();
            } catch (e) {}
        }
    };

    // Session Monitor: polls server for remaining session seconds and stops video when expired
    CPP_Public.SessionMonitor = (function() {
        var interval = null;
        var currentVideo = null;
        var currentVideoId = null;

        function poll() {
            if (!currentVideo) return;
            jQuery.ajax({
                url: cpp_public_ajax.ajax_url,
                method: 'POST',
                data: {
                    action: 'cpp_get_session_remaining',
                    nonce: cpp_public_ajax.nonce
                },
                success: function(resp) {
                    if (resp && resp.success && resp.data) {
                        var data = resp.data;
                        if (!data.valid || (data.remaining_seconds !== undefined && parseInt(data.remaining_seconds, 10) <= 0)) {
                            // Time's up - pause and overlay purchase message
                                    try { currentVideo.pause(); } catch (e) {}
                                    // Provider-specific hard stop: destroy hls.js if present
                                    try {
                                        if (currentVideo._cpp_hls && typeof currentVideo._cpp_hls.destroy === 'function') {
                                            currentVideo._cpp_hls.destroy();
                                            currentVideo._cpp_hls = null;
                                        }
                                    } catch (e) {}
                                    // Presto Player embed cleanup: try Presto API if available, otherwise remove embed DOM
                                    try {
                                        // Presto Player typically exposes a global prestoPlayers or window.ppPlayers map in some setups.
                                        if (window.presto_player && typeof window.presto_player.pause === 'function') {
                                            window.presto_player.pause();
                                        } else if (window.ppPlayers && window.ppPlayers[currentVideoId] && typeof window.ppPlayers[currentVideoId].pause === 'function') {
                                            window.ppPlayers[currentVideoId].pause();
                                        } else {
                                            // fallback: remove video source element and replace with a static poster to prevent further playback
                                            try {
                                                var $cv = jQuery(currentVideo);
                                                $cv.removeAttr('src');
                                                $cv.find('source').remove();
                                                currentVideo.load && currentVideo.load();
                                            } catch (e) {}
                                        }
                                    } catch (e) {}
                                    showSessionExpiredOverlay(currentVideo, currentVideoId);
                            stop();
                        }
                    }
                }
            });
        }

        function start() {
            stop();
            interval = setInterval(poll, 5000); // poll every 5s
        }

        function stop() {
            if (interval) { clearInterval(interval); interval = null; }
        }

        function watch(videoEl, videoId) {
            // Stop watching previous
            stop();
            currentVideo = videoEl;
            currentVideoId = videoId;
            start();
        }

        function showSessionExpiredOverlay(videoEl, videoId) {
            var $v = jQuery(videoEl).closest('.cpp-video-player-container');
            if (!$v || !$v.length) {
                $v = jQuery(videoEl).parent();
            }

            // Determine overlay image: per-video meta (if available on the player container) or global default
            var overlayImage = '';
            try {
                var $playerContainer = $v;
                if ($playerContainer && $playerContainer.data('overlay-image')) {
                    overlayImage = $playerContainer.data('overlay-image');
                } else if (window.cpp_public_ajax && cpp_public_ajax.overlay_image) {
                    overlayImage = cpp_public_ajax.overlay_image;
                }
            } catch (e) { overlayImage = ''; }

            var title = (cpp_public_ajax.strings && cpp_public_ajax.strings.overlay_expired) ? cpp_public_ajax.strings.overlay_expired : 'Session expired';
            var prompt = (cpp_public_ajax.strings && cpp_public_ajax.strings.overlay_prompt) ? cpp_public_ajax.strings.overlay_prompt : 'Your session has ended. Purchase more minutes to continue watching.';
            var buyText = (cpp_public_ajax.strings && cpp_public_ajax.strings.overlay_buy) ? cpp_public_ajax.strings.overlay_buy : 'Buy more minutes';

            var overlay = '<div class="cpp-session-expired-overlay">'
                        + '<div class="cpp-session-expired-backdrop"' + (overlayImage ? ' style="background-image:url(' + overlayImage + ');"' : '') + '></div>'
                        + '<div class="cpp-session-expired-content">'
                        + '<h3>' + title + '</h3>'
                        + '<p>' + prompt + '</p>'
                        + '<a class="button cpp-buy-more" href="' + ( (typeof $v.data('purchase-url') !== 'undefined' && $v.data('purchase-url')) ? $v.data('purchase-url') : '/premium-signup') + '">' + buyText + '</a>'
                        + '</div></div>';

            try {
                // remove existing overlays
                $v.find('.cpp-session-expired-overlay').remove();
                $v.css('position', 'relative');
                $v.append(overlay);
            } catch (e) {
                // noop
            }
        }

        return {
            watch: watch,
            stop: stop
        };
    })();

    // Expose some functions globally for shortcode usage
    window.CPP_Public = CPP_Public;

    // Also expose quick global helpers for backward compatibility with inline scripts
    window.openVideoModal = function(videoId) { return window.CPP_Public.openVideoModal(videoId); };
    window.closeVideoModal = function() { return window.CPP_Public.closeVideoModal(); };

    // Bind gallery links to open modal (safe to double-bind; inline script may also exist)
    jQuery(document).ready(function($) {
        $(document).on('click', '.cpp-video-link', function(e) {
            var $item = $(this).closest('.cpp-video-item');
            var vid = $item.data('video-id');
            if (vid) {
                e.preventDefault();
                window.openVideoModal(vid);
            }
        });

        // Close modal on ESC
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' || e.keyCode === 27) {
                window.closeVideoModal();
            }
        });
        
        // Close modal via overlay or close button (supports Breakdance styling)
        $(document).on('click', '.cpp-modal-overlay, .cpp-modal-close', function(e) {
            e.preventDefault();
            window.closeVideoModal();
        });
    });

})(jQuery);