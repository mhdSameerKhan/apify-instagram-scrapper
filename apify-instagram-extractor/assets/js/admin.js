/**
 * Apify Instagram Extractor - Admin JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        initTokenManager();
        initSettings();
        initLogsAndCache();
    });

    /**
     * Helper: Show Toast Notification
     */
    function showToast(message, isError) {
        $('.apify-toast').remove();
        var $toast = $('<div class="apify-toast">' + message + '</div>');
        if (isError) {
            $toast.css('background', '#b91c1c');
        }
        $('body').append($toast);
        setTimeout(function() {
            $toast.fadeOut(300, function() { $(this).remove(); });
        }, 3500);
    }

    /**
     * API Keys & Rotation Manager
     */
    function initTokenManager() {
        // Modal Handlers
        $('#apify-btn-add-token-modal').on('click', function() {
            $('#apify-modal-add-token').fadeIn(200);
        });

        $('.apify-modal-close').on('click', function() {
            $('#apify-modal-add-token').fadeOut(200);
        });

        $(window).on('click', function(e) {
            if ($(e.target).hasClass('apify-modal')) {
                $('.apify-modal').fadeOut(200);
            }
        });

        // Add Token Form Submit
        $('#apify-form-add-token').on('submit', function(e) {
            e.preventDefault();

            var token = $.trim($('#apify-new-token-str').val());
            var label = $.trim($('#apify-new-token-label').val());
            var limit = $.trim($('#apify-new-token-limit').val());
            var $btn  = $(this).find('button[type="submit"]');

            if (!token) return;

            $btn.prop('disabled', true).text(apifyInstaAdmin.strings.saving);

            $.ajax({
                url: apifyInstaAdmin.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'apify_insta_save_token',
                    nonce: apifyInstaAdmin.nonce,
                    token: token,
                    label: label,
                    limit: limit
                },
                success: function(res) {
                    $btn.prop('disabled', false).text('Save & Verify Token');
                    if (res.success) {
                        showToast(res.data.message);
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        alert(res.data.message);
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).text('Save & Verify Token');
                    alert('Error connecting to WordPress server.');
                }
            });
        });

        // Quick Inline Token Form Submit (Empty State)
        $('#apify-quick-add-token-form').on('submit', function(e) {
            e.preventDefault();

            var token = $.trim($('#apify-quick-token-input').val());
            var $btn  = $(this).find('button[type="submit"]');

            if (!token) return;

            $btn.prop('disabled', true).text(apifyInstaAdmin.strings.saving);

            $.ajax({
                url: apifyInstaAdmin.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'apify_insta_save_token',
                    nonce: apifyInstaAdmin.nonce,
                    token: token,
                    label: 'Primary Account',
                    limit: 1800
                },
                success: function(res) {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> Save & Connect Primary Key');
                    if (res.success) {
                        showToast(res.data.message);
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        alert(res.data.message);
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> Save & Connect Primary Key');
                    alert('Error connecting to WordPress server.');
                }
            });
        });

        // Test Token Connection
        $(document).on('click', '.apify-btn-test-token', function() {
            var $btn  = $(this);
            var token = $btn.data('token');

            $btn.prop('disabled', true).text(apifyInstaAdmin.strings.testing);

            $.ajax({
                url: apifyInstaAdmin.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'apify_insta_test_token',
                    nonce: apifyInstaAdmin.nonce,
                    token: token
                },
                success: function(res) {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-yes-alt"></span> Test Connection');
                    if (res.success) {
                        showToast(res.data.message);
                    } else {
                        alert(res.data.message);
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-yes-alt"></span> Test Connection');
                    alert('Network error testing token.');
                }
            });
        });

        // Toggle Token Status (Pause / Resume)
        $(document).on('click', '.apify-btn-toggle-token', function() {
            var $btn = $(this);
            var id   = $btn.data('id');

            $.ajax({
                url: apifyInstaAdmin.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'apify_insta_toggle_token',
                    nonce: apifyInstaAdmin.nonce,
                    id: id
                },
                success: function(res) {
                    if (res.success) {
                        location.reload();
                    }
                }
            });
        });

        // Reset Monthly Usage Count
        $(document).on('click', '.apify-btn-reset-usage', function() {
            if (!confirm(apifyInstaAdmin.strings.confirmReset)) return;

            var id = $(this).data('id');
            $.ajax({
                url: apifyInstaAdmin.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'apify_insta_reset_usage',
                    nonce: apifyInstaAdmin.nonce,
                    id: id
                },
                success: function(res) {
                    if (res.success) {
                        showToast(res.data.message);
                        setTimeout(function() { location.reload(); }, 600);
                    }
                }
            });
        });

        // Delete Token
        $(document).on('click', '.apify-btn-delete-token', function() {
            if (!confirm(apifyInstaAdmin.strings.confirmDelete)) return;

            var id = $(this).data('id');
            $.ajax({
                url: apifyInstaAdmin.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'apify_insta_delete_token',
                    nonce: apifyInstaAdmin.nonce,
                    id: id
                },
                success: function(res) {
                    if (res.success) {
                        showToast(res.data.message);
                        setTimeout(function() { location.reload(); }, 600);
                    }
                }
            });
        });

        // Copy Masked Token to Clipboard
        $(document).on('click', '.apify-btn-reveal-token', function() {
            var fullToken = $(this).data('token');
            navigator.clipboard.writeText(fullToken).then(function() {
                showToast(apifyInstaAdmin.strings.copied || 'Token copied to clipboard!');
            });
        });

        // Radio Strategy selection styling
        $('.apify-radio-card input[type="radio"]').on('change', function() {
            $('.apify-radio-card').removeClass('selected');
            $(this).closest('.apify-radio-card').addClass('selected');
        });

        // Save Strategy Form
        $('#apify-strategy-form').on('submit', function(e) {
            e.preventDefault();
            var strategy = $('input[name="rotation_strategy"]:checked').val();

            $.ajax({
                url: apifyInstaAdmin.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'apify_insta_save_settings',
                    nonce: apifyInstaAdmin.nonce,
                    rotation_strategy: strategy
                },
                success: function(res) {
                    if (res.success) {
                        showToast(res.data.message);
                    }
                }
            });
        });
    }

    /**
     * General Settings
     */
    function initSettings() {
        $('#apify-general-settings-form').on('submit', function(e) {
            e.preventDefault();

            var data = $(this).serialize();
            data += '&action=apify_insta_save_settings&nonce=' + apifyInstaAdmin.nonce;

            $.ajax({
                url: apifyInstaAdmin.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: data,
                success: function(res) {
                    if (res.success) {
                        showToast(res.data.message);
                    }
                }
            });
        });
    }

    /**
     * Logs and Cache Handlers
     */
    function initLogsAndCache() {
        // Flush Cache
        $('#apify-btn-flush-cache').on('click', function() {
            if (!confirm(apifyInstaAdmin.strings.confirmFlush)) return;

            var $btn = $(this);
            $btn.prop('disabled', true);

            $.ajax({
                url: apifyInstaAdmin.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'apify_insta_flush_cache',
                    nonce: apifyInstaAdmin.nonce
                },
                success: function(res) {
                    $btn.prop('disabled', false);
                    if (res.success) {
                        showToast(res.data.message);
                        $('#apify-cached-count').text('0');
                    }
                }
            });
        });

        // Clear Logs
        $('#apify-btn-clear-logs').on('click', function() {
            if (!confirm(apifyInstaAdmin.strings.confirmClearLog)) return;

            $.ajax({
                url: apifyInstaAdmin.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'apify_insta_clear_logs',
                    nonce: apifyInstaAdmin.nonce
                },
                success: function(res) {
                    if (res.success) {
                        showToast(res.data.message);
                        setTimeout(function() { location.reload(); }, 600);
                    }
                }
            });
        });
    }

})(jQuery);
