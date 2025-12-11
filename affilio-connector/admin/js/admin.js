/**
 * Affilio Connector Admin JavaScript
 */

(function($) {
    'use strict';

    /**
     * Copy to clipboard functionality
     */
    function initCopyButtons() {
        $('.affilio-copy-btn').on('click', function() {
            const $btn = $(this);
            const targetId = $btn.data('target');
            const $input = $('#' + targetId);

            if (!$input.length) return;

            // Copy to clipboard
            const text = $input.val();

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function() {
                    showCopySuccess($btn);
                }).catch(function() {
                    fallbackCopy($input, $btn);
                });
            } else {
                fallbackCopy($input, $btn);
            }
        });
    }

    /**
     * Fallback copy method for older browsers
     */
    function fallbackCopy($input, $btn) {
        $input.select();
        $input[0].setSelectionRange(0, 99999);

        try {
            document.execCommand('copy');
            showCopySuccess($btn);
        } catch (err) {
            console.error('Copy failed:', err);
        }

        // Deselect
        window.getSelection().removeAllRanges();
    }

    /**
     * Show copy success feedback
     */
    function showCopySuccess($btn) {
        const originalHtml = $btn.html();

        $btn.addClass('copied');
        $btn.html('<span class="dashicons dashicons-yes"></span>');

        setTimeout(function() {
            $btn.removeClass('copied');
            $btn.html(originalHtml);
        }, 1500);
    }

    /**
     * Regenerate token functionality
     */
    function initRegenerateToken() {
        $('#affilio-regenerate-token').on('click', function() {
            if (!confirm(affilioConnector.strings.regenerateConfirm)) {
                return;
            }

            const $btn = $(this);
            $btn.addClass('loading');
            $btn.find('.dashicons').removeClass('dashicons-update').addClass('dashicons-update-alt');

            $.ajax({
                url: affilioConnector.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'affilio_regenerate_token',
                    nonce: affilioConnector.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#affilio-api-token').val(response.data.token);
                        showNotification(response.data.message, 'success');
                    } else {
                        showNotification(response.data.message, 'error');
                    }
                },
                error: function() {
                    showNotification('Une erreur est survenue.', 'error');
                },
                complete: function() {
                    $btn.removeClass('loading');
                    $btn.find('.dashicons').removeClass('dashicons-update-alt').addClass('dashicons-update');
                }
            });
        });
    }

    /**
     * Test connection functionality
     */
    function initTestConnection() {
        $('#affilio-test-connection').on('click', function() {
            const $btn = $(this);
            const $result = $('#affilio-test-result');

            $btn.addClass('loading');
            $btn.find('.dashicons').removeClass('dashicons-yes-alt').addClass('dashicons-update');
            $result.removeClass('success error').text('');

            $.ajax({
                url: affilioConnector.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'affilio_test_connection',
                    nonce: affilioConnector.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $result.addClass('success').text(response.data.message);
                    } else {
                        $result.addClass('error').text(response.data.message);
                    }
                },
                error: function() {
                    $result.addClass('error').text('Erreur de connexion au serveur.');
                },
                complete: function() {
                    $btn.removeClass('loading');
                    $btn.find('.dashicons').removeClass('dashicons-update').addClass('dashicons-yes-alt');
                }
            });
        });
    }

    /**
     * Show notification (simple inline for now)
     */
    function showNotification(message, type) {
        const $notice = $('<div class="notice notice-' + (type === 'success' ? 'success' : 'error') + ' is-dismissible"><p>' + message + '</p></div>');

        $('.affilio-admin-wrap h1').after($notice);

        // Auto dismiss after 5 seconds
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);

        // Make dismissible
        $notice.on('click', '.notice-dismiss', function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        });
    }

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        initCopyButtons();
        initRegenerateToken();
        initTestConnection();
    });

})(jQuery);
