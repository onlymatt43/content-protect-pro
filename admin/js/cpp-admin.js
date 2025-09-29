/**
 * Content Protect Pro - Admin JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Initialize admin functionality
        CPP_Admin.init();
    });

    var CPP_Admin = {
        init: function() {
            this.bindEvents();
            this.initTabs();
            this.initDataTables();
            this.initCharts();
        },

        bindEvents: function() {
            // Delete confirmations
            $(document).on('click', '.cpp-delete-item', this.confirmDelete);
            
            // Form submissions
            $(document).on('submit', '.cpp-admin-form', this.handleFormSubmit);
            
            // Generate gift code
            $(document).on('click', '.cpp-generate-code', this.generateGiftCode);
            
            // Bulk actions
            $(document).on('click', '.cpp-bulk-action', this.handleBulkAction);
            
            // Auto-save settings
            $(document).on('change', '.cpp-auto-save', this.autoSaveSettings);
        },

        initTabs: function() {
            $('.cpp-admin-tabs a').on('click', function(e) {
                e.preventDefault();
                
                var target = $(this).attr('href');
                
                // Update active tab
                $('.cpp-admin-tabs a').removeClass('active');
                $(this).addClass('active');
                
                // Show/hide content
                $('.cpp-tab-content').hide();
                $(target).show();
                
                // Update URL hash
                window.location.hash = target;
            });
            
            // Show initial tab based on hash
            if (window.location.hash) {
                $('.cpp-admin-tabs a[href="' + window.location.hash + '"]').click();
            } else {
                $('.cpp-admin-tabs a:first').click();
            }
        },

        initDataTables: function() {
            if ($.fn.DataTable) {
                $('.cpp-data-table').DataTable({
                    responsive: true,
                    pageLength: 20,
                    order: [[0, 'desc']],
                    language: {
                        search: cpp_admin_ajax.strings.search || 'Search:',
                        lengthMenu: cpp_admin_ajax.strings.show_entries || 'Show _MENU_ entries',
                        info: cpp_admin_ajax.strings.showing_entries || 'Showing _START_ to _END_ of _TOTAL_ entries',
                        paginate: {
                            first: cpp_admin_ajax.strings.first || 'First',
                            last: cpp_admin_ajax.strings.last || 'Last',
                            next: cpp_admin_ajax.strings.next || 'Next',
                            previous: cpp_admin_ajax.strings.previous || 'Previous'
                        }
                    }
                });
            }
        },

        initCharts: function() {
            if (typeof Chart !== 'undefined') {
                this.renderAnalyticsCharts();
            }
        },

        confirmDelete: function(e) {
            if (!confirm(cpp_admin_ajax.strings.confirm_delete)) {
                e.preventDefault();
                return false;
            }
        },

        handleFormSubmit: function(e) {
            var $form = $(this);
            var $submitBtn = $form.find('button[type="submit"]');
            
            // Show loading state
            $submitBtn.prop('disabled', true);
            $submitBtn.html('<span class="cpp-loading"></span> ' + cpp_admin_ajax.strings.loading);
            
            // Don't prevent default - let form submit normally
            // Loading state will be reset on page reload
        },

        generateGiftCode: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $input = $button.siblings('input[name="code"]');
            
            // Show loading
            $button.prop('disabled', true);
            $button.html('<span class="cpp-loading"></span>');
            
            $.ajax({
                url: cpp_admin_ajax.ajax_url,
                method: 'POST',
                data: {
                    action: 'cpp_generate_code',
                    nonce: cpp_admin_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $input.val(response.data.code);
                    } else {
                        alert(response.data.message || cpp_admin_ajax.strings.error);
                    }
                },
                error: function() {
                    alert(cpp_admin_ajax.strings.error);
                },
                complete: function() {
                    $button.prop('disabled', false);
                    $button.html('Generate');
                }
            });
        },

        handleBulkAction: function(e) {
            e.preventDefault();
            
            var $form = $(this).closest('form');
            var action = $form.find('select[name="bulk_action"]').val();
            var selected = $form.find('input[name="items[]"]:checked');
            
            if (!action) {
                alert('Please select an action.');
                return;
            }
            
            if (selected.length === 0) {
                alert('Please select at least one item.');
                return;
            }
            
            if (action === 'delete' && !confirm(cpp_admin_ajax.strings.confirm_delete)) {
                return;
            }
            
            // Submit form
            $form.submit();
        },

        autoSaveSettings: function() {
            var $input = $(this);
            var setting = $input.attr('name');
            var value = $input.is(':checkbox') ? ($input.is(':checked') ? 1 : 0) : $input.val();
            
            // Show saving indicator
            var $indicator = $input.siblings('.cpp-save-indicator');
            if ($indicator.length === 0) {
                $indicator = $('<span class="cpp-save-indicator">Saving...</span>');
                $input.after($indicator);
            }
            $indicator.show();
            
            $.ajax({
                url: cpp_admin_ajax.ajax_url,
                method: 'POST',
                data: {
                    action: 'cpp_save_setting',
                    setting: setting,
                    value: value,
                    nonce: cpp_admin_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $indicator.text('Saved').delay(2000).fadeOut();
                    } else {
                        $indicator.text('Error saving').addClass('error');
                    }
                },
                error: function() {
                    $indicator.text('Error saving').addClass('error');
                }
            });
        },

        renderAnalyticsCharts: function() {
            // Daily activity chart
            var ctx = document.getElementById('cpp-daily-chart');
            if (ctx && window.cppDailyData) {
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: window.cppDailyData.labels,
                        datasets: [{
                            label: 'Daily Activity',
                            data: window.cppDailyData.data,
                            borderColor: '#0073aa',
                            backgroundColor: 'rgba(0, 115, 170, 0.1)',
                            fill: true,
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            }
                        }
                    }
                });
            }
            
            // Event type distribution
            var ctx2 = document.getElementById('cpp-events-chart');
            if (ctx2 && window.cppEventsData) {
                new Chart(ctx2, {
                    type: 'doughnut',
                    data: {
                        labels: window.cppEventsData.labels,
                        datasets: [{
                            data: window.cppEventsData.data,
                            backgroundColor: [
                                '#0073aa',
                                '#00a32a',
                                '#d63638',
                                '#dba617',
                                '#8c8f94'
                            ]
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'right'
                            }
                        }
                    }
                });
            }
        }
    };

})(jQuery);