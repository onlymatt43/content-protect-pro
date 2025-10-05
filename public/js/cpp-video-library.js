/**
 * Enhanced Video Library JavaScript
 * 
 * Handles filtering, search, pagination, and video playback.
 * Follows security patterns with nonce validation.
 * 
 * @package ContentProtectPro
 * @since 3.1.0
 */

(function($) {
    'use strict';

    const CPPVideoLibrary = {
        
        currentPage: 1,
        perPage: 12,
        totalPages: 1,
        
        init: function() {
            this.cacheElements();
            this.bindEvents();
            this.loadVideos(); // Initial load
        },
        
        cacheElements: function() {
            this.$library = $('.cpp-video-library-enhanced');
            this.$grid = $('.cpp-video-grid');
            this.$search = $('#cpp-video-search');
            this.$categoryFilter = $('#cpp-category-filter');
            this.$integrationFilter = $('#cpp-integration-filter');
            this.$pagination = $('.cpp-library-pagination');
            this.$spinner = $('.cpp-loading-spinner');
            
            this.perPage = parseInt(this.$library.data('per-page')) || 12;
        },
        
        bindEvents: function() {
            // Search (debounced)
            this.$search.on('input', this.debounce(this.handleSearch.bind(this), 500));
            
            // Category filter
            this.$categoryFilter.on('change', this.handleFilterChange.bind(this));
            
            // Video card clicks
            this.$grid.on('click', '.cpp-video-card:not(.cpp-video-locked)', this.handleVideoClick.bind(this));
            
            // Pagination (delegated)
            this.$pagination.on('click', '.cpp-page-btn', this.handlePaginationClick.bind(this));
        },
        
        loadVideos: function() {
            this.showLoading();
            
            $.ajax({
                url: cppVars.ajax_url,
                type: 'POST',
                data: {
                    action: 'cpp_load_library_videos',
                    nonce: cppVars.nonce,
                    category: this.$categoryFilter.val(),
                    search: this.$search.val(),
                    page: this.currentPage,
                    per_page: this.perPage,
                    integration_type: this.$integrationFilter.val() || ''
                },
                success: this.handleLoadSuccess.bind(this),
                error: this.handleLoadError.bind(this)
            });
        },
        
        handleLoadSuccess: function(response) {
            this.hideLoading();
            
            if (!response.success) {
                this.showError(response.data.message);
                return;
            }
            
            const data = response.data;
            
            // Update grid
            this.$grid.empty();
            if (data.videos_html.length > 0) {
                data.videos_html.forEach(html => {
                    this.$grid.append(html);
                });
            } else {
                this.$grid.html('<p class="cpp-no-videos">' + cppVars.strings.no_videos + '</p>');
            }
            
            // Update pagination
            this.totalPages = data.total_pages;
            this.renderPagination();
            
            // Trigger analytics
            this.trackEvent('library_loaded', {
                page: this.currentPage,
                total: data.total,
                category: this.$categoryFilter.val(),
                search: this.$search.val()
            });
        },
        
        handleLoadError: function(xhr, status, error) {
            this.hideLoading();
            this.showError(cppVars.strings.load_error);
            console.error('Library load error:', status, error);
        },
        
        handleVideoClick: function(e) {
            e.preventDefault();
            
            const $card = $(e.currentTarget);
            const videoId = $card.data('video-id');
            
            this.openVideoModal(videoId);
        },
        
        openVideoModal: function(videoId) {
            // Show loading modal
            const $modal = this.createModal();
            $modal.find('.cpp-modal-content').html('<div class="spinner is-active"></div>');
            $('body').append($modal);
            $modal.fadeIn();
            
            // Get playback data
            $.ajax({
                url: cppVars.ajax_url,
                type: 'POST',
                data: {
                    action: 'cpp_get_video_playback',
                    nonce: cppVars.nonce,
                    video_id: videoId
                },
                success: (response) => {
                    if (response.success) {
                        const data = response.data;
                        
                        if (data.embed_html) {
                            // Presto Player embed
                            $modal.find('.cpp-modal-content').html(data.embed_html);
                        } else if (data.playback_url) {
                            // Bunny CDN video
                            const videoHtml = '<video controls autoplay style="width:100%;"><source src="' + data.playback_url + '" type="video/mp4"></video>';
                            $modal.find('.cpp-modal-content').html(videoHtml);
                        }
                        
                        // Track analytics
                        this.trackEvent('video_opened', {
                            video_id: videoId,
                            integration_type: data.embed_html ? 'presto' : 'bunny'
                        });
                    } else {
                        $modal.find('.cpp-modal-content').html('<p class="cpp-error">' + response.data.message + '</p>');
                    }
                },
                error: () => {
                    $modal.find('.cpp-modal-content').html('<p class="cpp-error">' + cppVars.strings.playback_error + '</p>');
                }
            });
        },
        
        createModal: function() {
            const $modal = $('<div class="cpp-video-modal"></div>');
            const $overlay = $('<div class="cpp-modal-overlay"></div>');
            const $container = $('<div class="cpp-modal-container"></div>');
            const $close = $('<button class="cpp-modal-close">&times;</button>');
            const $content = $('<div class="cpp-modal-content"></div>');
            
            $container.append($close, $content);
            $modal.append($overlay, $container);
            
            // Close handlers
            $close.on('click', () => {
                $modal.fadeOut(() => $modal.remove());
            });
            $overlay.on('click', () => {
                $modal.fadeOut(() => $modal.remove());
            });
            
            return $modal;
        },
        
        handleSearch: function() {
            this.currentPage = 1;
            this.loadVideos();
        },
        
        handleFilterChange: function() {
            this.currentPage = 1;
            this.loadVideos();
        },
        
        handlePaginationClick: function(e) {
            e.preventDefault();
            
            const $btn = $(e.currentTarget);
            const page = parseInt($btn.data('page'));
            
            if (page && page !== this.currentPage && page > 0 && page <= this.totalPages) {
                this.currentPage = page;
                this.loadVideos();
                
                // Scroll to top of library
                $('html, body').animate({
                    scrollTop: this.$library.offset().top - 100
                }, 300);
            }
        },
        
        renderPagination: function() {
            if (this.totalPages <= 1) {
                this.$pagination.hide();
                return;
            }
            
            this.$pagination.show().empty();
            
            // Previous button
            if (this.currentPage > 1) {
                this.$pagination.append(
                    '<button class="cpp-page-btn cpp-page-prev" data-page="' + (this.currentPage - 1) + '">' +
                    '<span class="dashicons dashicons-arrow-left-alt2"></span> ' + cppVars.strings.prev +
                    '</button>'
                );
            }
            
            // Page numbers (show 5 pages max)
            const startPage = Math.max(1, this.currentPage - 2);
            const endPage = Math.min(this.totalPages, this.currentPage + 2);
            
            for (let i = startPage; i <= endPage; i++) {
                const activeClass = (i === this.currentPage) ? 'cpp-page-active' : '';
                this.$pagination.append(
                    '<button class="cpp-page-btn ' + activeClass + '" data-page="' + i + '">' + i + '</button>'
                );
            }
            
            // Next button
            if (this.currentPage < this.totalPages) {
                this.$pagination.append(
                    '<button class="cpp-page-btn cpp-page-next" data-page="' + (this.currentPage + 1) + '">' +
                    cppVars.strings.next + ' <span class="dashicons dashicons-arrow-right-alt2"></span>' +
                    '</button>'
                );
            }
        },
        
        showLoading: function() {
            this.$spinner.show();
        },
        
        hideLoading: function() {
            this.$spinner.hide();
        },
        
        showError: function(message) {
            this.$grid.html('<p class="cpp-error">' + message + '</p>');
        },
        
        trackEvent: function(eventType, metadata) {
            if (!cppVars.ajax_url) return;
            
            $.ajax({
                url: cppVars.ajax_url,
                type: 'POST',
                data: {
                    action: 'cpp_track_video_event',
                    nonce: cppVars.nonce,
                    event_type: eventType,
                    metadata: JSON.stringify(metadata)
                }
            });
        },
        
        debounce: function(func, wait) {
            let timeout;
            return function() {
                const context = this;
                const args = arguments;
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(context, args), wait);
            };
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        if ($('.cpp-video-library-enhanced').length) {
            CPPVideoLibrary.init();
        }
    });

})(jQuery);