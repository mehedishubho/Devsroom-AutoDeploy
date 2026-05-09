/**
 * Devsroom AutoDeploy Admin Scripts
 *
 * @package Devsroom_AutoDeploy
 */

(function ($) {
    'use strict';

    $(document).ready(function () {

        // Confirm delete actions
        $('.devsroom-autodeploy').on('click', '[name*="delete"]', function (e) {
            if (!confirm(devsroom_autodeploy.strings.confirm_delete)) {
                e.preventDefault();
                return false;
            }
        });

        // Confirm deploy actions
        $('.devsroom-autodeploy').on('click', '[name="devsroom_autodeploy_deploy_now"], [name="devsroom_autodeploy_deploy_activate"]', function (e) {
            if (!confirm(devsroom_autodeploy.strings.confirm_deploy)) {
                e.preventDefault();
                return false;
            }
        });

        // AJAX deployment trigger
        $('.devsroom-autodeploy').on('click', '.ajax-deploy', function (e) {
            e.preventDefault();

            var $button = $(this);
            var repositoryId = $button.data('repository-id');

            $button.prop('disabled', true).text(devsroom_autodeploy.strings.deploying);

            $.ajax({
                url: devsroom_autodeploy.ajax_url,
                type: 'POST',
                data: {
                    action: 'devsroom_autodeploy_deploy',
                    nonce: devsroom_autodeploy.nonce,
                    repository_id: repositoryId
                },
                success: function (response) {
                    if (response.success) {
                        showNotice('success', devsroom_autodeploy.strings.success);
                        setTimeout(function () { location.reload(); }, 1000);
                    } else {
                        showNotice('error', devsroom_autodeploy.strings.error + ': ' + response.data.message);
                        $button.prop('disabled', false).text($button.data('original-text'));
                    }
                },
                error: function (xhr, status, error) {
                    showNotice('error', devsroom_autodeploy.strings.error + ': ' + error);
                    $button.prop('disabled', false).text($button.data('original-text'));
                }
            });
        });

        // Store original button text
        $('.devsroom-autodeploy button[data-original-text]').each(function () {
            var $button = $(this);
            if (!$button.data('original-text')) {
                $button.data('original-text', $button.text());
            }
        });

        // Auto-refresh for pending deployments
        if ($('.status-pending, .status-scanning, .status-backing_up, .status-locking, .status-comparing, .status-downloading, .status-extracting, .status-deploying, .status-verifying, .status-rolling_back').length > 0) {
            setInterval(function () {
                location.reload();
            }, 15000);
        }

        // Copy to clipboard
        $('.devsroom-autodeploy').on('click', '.copy-to-clipboard', function (e) {
            e.preventDefault();
            var $button = $(this);
            var text = $button.data('copy');
            var originalText = $button.text();

            navigator.clipboard.writeText(text).then(function () {
                $button.text(devsroom_autodeploy.strings.copied || 'Copied!');
                setTimeout(function () { $button.text(originalText); }, 2000);
            }).catch(function () {
                var $temp = $('<textarea>');
                $('body').append($temp);
                $temp.val(text).select();
                document.execCommand('copy');
                $temp.remove();
                $button.text(devsroom_autodeploy.strings.copied || 'Copied!');
                setTimeout(function () { $button.text(originalText); }, 2000);
            });
        });

        // Token visibility toggle
        $('.devsroom-autodeploy').on('click', '.toggle-token-visibility', function (e) {
            e.preventDefault();
            var $button = $(this);
            var $input = $($button.data('target'));

            if ($input.attr('type') === 'password') {
                $input.attr('type', 'text');
                $button.find('.dashicons').removeClass('dashicons-visibility').addClass('dashicons-hidden');
            } else {
                $input.attr('type', 'password');
                $button.find('.dashicons').removeClass('dashicons-hidden').addClass('dashicons-visibility');
            }
        });

        // Status filter handling
        $('.devsroom-autodeploy').on('change', '.status-filter', function () {
            var status = $(this).val();
            var url = new URL(window.location.href);

            if (status) {
                url.searchParams.set('status', status);
            } else {
                url.searchParams.delete('status');
            }

            window.location.href = url.toString();
        });

        // Repository search/filter
        $('#repository-search').on('keyup', function () {
            var value = $(this).val().toLowerCase();

            $('.repository-row').each(function () {
                var $row = $(this);
                var text = $row.text().toLowerCase();
                $row.toggle(text.indexOf(value) > -1);
            });
        });

        // Settings tab navigation
        $('.devsroom-autodeploy .nav-tab').on('click', function (e) {
            e.preventDefault();
            var target = $(this).attr('href');

            $('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');

            $('.tab-content').removeClass('active');
            $(target).addClass('active');
        });

        // Log context toggle
        $('.devsroom-autodeploy').on('click', '.toggle-log-context', function (e) {
            e.preventDefault();
            $(this).closest('tr').next('.log-context-row').toggle();
        });

        // Pipeline step animation
        $('.ds-pipeline-step').each(function (index) {
            var $step = $(this);
            setTimeout(function () {
                $step.addClass('ds-pipeline-step--visible');
            }, index * 100);
        });

        // Auto-dismiss notices after 5 seconds
        setTimeout(function () {
            $('.devsroom-autodeploy .notice.is-dismissible .notice-dismiss').each(function () {
                $(this).trigger('click');
            });
        }, 5000);

        // Initialize tooltips
        if ($.fn.tooltip) {
            $('[data-tooltip]').tooltip({
                position: { my: 'center bottom-10', at: 'center top' },
                tooltipClass: 'devsroom-autodeploy-tooltip'
            });
        }
    });

    // Utility: show a floating notice
    function showNotice(type, message) {
        var $notice = $('<div class="notice notice-' + type + ' is-dismissible ds-floating-notice"><p>' + message + '</p></div>');
        $('.devsroom-autodeploy').prepend($notice);
        setTimeout(function () {
            $notice.fadeOut(300, function () { $(this).remove(); });
        }, 4000);
    }

})(jQuery);
