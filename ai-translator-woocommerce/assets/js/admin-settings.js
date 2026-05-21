/**
 * AI Translator - Admin Settings JavaScript
 */
(function($) {
    'use strict';

    var AITWC_Admin = {

        init: function() {
            this.bindEvents();
            this.showActiveProvider();
        },

        bindEvents: function() {
            // Provider selection toggle
            $('#aitwc_ai_provider').on('change', this.showActiveProvider);

            // Test API connection
            $('.aitwc-test-api').on('click', this.testConnection);

            // Batch translate
            $('#aitwc-start-batch').on('click', this.startBatchTranslate);

            // Clear cache
            $('#aitwc-clear-cache').on('click', this.clearCache);
        },

        showActiveProvider: function() {
            var provider = $('#aitwc_ai_provider').val();
            $('.aitwc-provider-settings').removeClass('active');
            $('.aitwc-provider-settings[data-provider="' + provider + '"]').addClass('active');
        },

        testConnection: function() {
            var $btn = $(this);
            var provider = $btn.data('provider');

            $btn.prop('disabled', true).text('Testing...');
            $btn.siblings('.aitwc-test-result').remove();

            $.ajax({
                url: aitwc_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'aitwc_test_api',
                    nonce: aitwc_admin.nonce,
                    provider: provider
                },
                success: function(response) {
                    var cls = response.success ? 'success' : 'error';
                    var msg = response.success
                        ? 'Connected! Translation: ' + response.data.translation
                        : 'Error: ' + response.data.message;
                    $btn.after('<span class="aitwc-test-result ' + cls + '">' + msg + '</span>');
                },
                error: function() {
                    $btn.after('<span class="aitwc-test-result error">Network error</span>');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Test Connection');
                }
            });
        },

        startBatchTranslate: function() {
            var $btn = $(this);
            var targetLang = $('#aitwc-batch-lang').val();
            var postType = $('#aitwc-batch-type').val();
            var batchSize = $('#aitwc-batch-size').val();

            $btn.prop('disabled', true).text('Translating...');
            $('#aitwc-batch-progress').show();
            $('#aitwc-batch-status').text('Starting batch translation...');

            $.ajax({
                url: aitwc_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'aitwc_batch_translate',
                    nonce: aitwc_admin.nonce,
                    target_lang: targetLang,
                    post_type: postType,
                    batch_size: batchSize
                },
                timeout: 300000, // 5 minutes
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        var percent = data.total > 0 ? Math.round((data.success / data.total) * 100) : 100;
                        $('.aitwc-progress-fill').css('width', percent + '%');
                        $('#aitwc-batch-status').text(
                            'Completed! ' + data.success + ' translated, ' + 
                            data.failed + ' failed out of ' + data.total + ' items.'
                        );

                        if (data.errors && data.errors.length > 0) {
                            var errorList = '<br><strong>Errors:</strong><ul>';
                            data.errors.forEach(function(err) {
                                errorList += '<li>Post #' + err.post_id + ': ' + err.error + '</li>';
                            });
                            errorList += '</ul>';
                            $('#aitwc-batch-status').append(errorList);
                        }
                    } else {
                        $('#aitwc-batch-status').text('Error: ' + response.data.message);
                    }
                },
                error: function(xhr, status) {
                    if (status === 'timeout') {
                        $('#aitwc-batch-status').text('Request timed out. Try a smaller batch size.');
                    } else {
                        $('#aitwc-batch-status').text('Network error occurred.');
                    }
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Start Batch Translation');
                }
            });
        },

        clearCache: function() {
            if (!confirm('Are you sure you want to clear ALL cached translations? This cannot be undone.')) {
                return;
            }

            var $btn = $(this);
            $btn.prop('disabled', true);

            $.ajax({
                url: aitwc_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'aitwc_clear_cache',
                    nonce: aitwc_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                },
                complete: function() {
                    $btn.prop('disabled', false);
                }
            });
        }
    };

    $(document).ready(function() {
        AITWC_Admin.init();
    });

})(jQuery);
