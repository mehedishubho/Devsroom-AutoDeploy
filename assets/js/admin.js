/**
 * Devsoom AutoDeploy Admin Scripts
 *
 * @package Devsoom_AutoDeploy
 */

(function ($) {
    'use strict';

    // Document ready
    $(document).ready(function () {

        // Confirm delete actions
        $('.devsoom-autodeploy').on('click', '[name*="delete"]', function (e) {
            if (!confirm(devsoom_autodeploy.strings.confirm_delete)) {
                e.preventDefault();
                return false;
            }
        });

        // Confirm deploy actions
        $('.devsoom-autodeploy').on('click', '[name="devsoom_autodeploy_deploy_now"]', function (e) {
            if (!confirm(devsoom_autodeploy.strings.confirm_deploy)) {
                e.preventDefault();
                return false;
            }
        });

        // AJAX deployment trigger
        $('.devsoom-autodeploy').on('click', '.ajax-deploy', function (e) {
            e.preventDefault();

            var $button = $(this);
            var repositoryId = $button.data('repository-id');

            $button.prop('disabled', true).text(devsoom_autodeploy.strings.deploying);

            $.ajax({
                url: devsoom_autodeploy.ajax_url,
                type: 'POST',
                data: {
                    action: 'devsoom_autodeploy_deploy',
                    nonce: devsoom_autodeploy.nonce,
                    repository_id: repositoryId
                },
                success: function (response) {
                    if (response.success) {
                        alert(devsoom_autodeploy.strings.success);
                        location.reload();
                    } else {
                        alert(devsoom_autodeploy.strings.error + ': ' + response.data.message);
                        $button.prop('disabled', false).text($button.data('original-text'));
                    }
                },
                error: function (xhr, status, error) {
                    alert(devsoom_autodeploy.strings.error + ': ' + error);
                    $button.prop('disabled', false).text($button.data('original-text'));
                }
            });
        });

        // Store original button text
        $('.devsoom-autodeploy button[data-original-text]').each(function () {
            var $button = $(this);
            if (!$button.data('original-text')) {
                $button.data('original-text', $button.text());
            }
        });

        // Auto-refresh for pending deployments
        if ($('.status-pending, .status-scanning, .status-backing_up').length > 0) {
            setInterval(function () {
                location.reload();
            }, 30000); // Refresh every 30 seconds
        }

        // Copy webhook URL to clipboard
        $('.copy-webhook-url').on('click', function (e) {
            e.preventDefault();

            var $button = $(this);
            var url = $button.data('url');

            navigator.clipboard.writeText(url).then(function () {
                $button.text('Copied!');
                setTimeout(function () {
                    $button.text('Copy URL');
                }, 2000);
            }).catch(function (err) {
                console.error('Failed to copy: ', err);
            });
        });

        // Toggle advanced options
        $('.toggle-advanced').on('click', function (e) {
            e.preventDefault();
            var $button = $(this);
            var $target = $($button.data('target'));

            $target.slideToggle(200, function () {
                if ($target.is(':visible')) {
                    $button.text('Hide Advanced Options');
                } else {
                    $button.text('Show Advanced Options');
                }
            });
        });

        // Token visibility toggle
        $('.toggle-token-visibility').on('click', function (e) {
            e.preventDefault();

            var $button = $(this);
            var $input = $($button.data('target'));

            if ($input.attr('type') === 'password') {
                $input.attr('type', 'text');
                $button.text('Hide');
            } else {
                $input.attr('type', 'password');
                $button.text('Show');
            }
        });

        // Status filter handling
        $('.status-filter').on('change', function () {
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

                if (text.indexOf(value) > -1) {
                    $row.show();
                } else {
                    $row.hide();
                }
            });
        });

        // Deployment details modal
        $('.view-deployment-details').on('click', function (e) {
            e.preventDefault();

            var $button = $(this);
            var deploymentId = $button.data('deployment-id');

            // Load deployment details via AJAX
            $.ajax({
                url: devsoom_autodeploy.ajax_url,
                type: 'POST',
                data: {
                    action: 'devsoom_autodeploy_deployment_details',
                    nonce: devsoom_autodeploy.nonce,
                    deployment_id: deploymentId
                },
                success: function (response) {
                    if (response.success) {
                        // Show modal with details
                        $('#deployment-modal .modal-content').html(response.data.html);
                        $('#deployment-modal').fadeIn(200);
                    }
                }
            });
        });

        // Close modal
        $('.close-modal, .modal-overlay').on('click', function () {
            $('#deployment-modal').fadeOut(200);
        });

        // Close modal on escape key
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape') {
                $('#deployment-modal').fadeOut(200);
            }
        });

        // Initialize tooltips
        if ($.fn.tooltip) {
            $('[data-tooltip]').tooltip({
                position: {
                    my: 'center bottom-10',
                    at: 'center top'
                },
                tooltipClass: 'devsoom-autodeploy-tooltip'
            });
        }
    });

})(jQuery);
