<?php

/**
 * Notification class.
 *
 * @package Devsroom_AutoDeploy
 */

namespace Devsroom_AutoDeploy\Core;

/**
 * Class Notification
 *
 * Handles email and WordPress admin notifications.
 *
 * @since 1.0.0
 */
class Notification
{

    /**
     * Singleton instance.
     *
     * @var Notification|null
     */
    private static ?Notification $instance = null;

    /**
     * Get singleton instance.
     *
     * @return Notification
     */
    public static function get_instance(): Notification
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Send deployment success notification.
     *
     * @param array $deployment Deployment data.
     * @return bool True on success, false on failure.
     */
    public function send_deployment_success(array $deployment): bool
    {
        if (! $this->is_notifications_enabled()) {
            return true;
        }

        $subject = sprintf(
            /* translators: %s: Plugin name */
            __('Devsroom AutoDeploy: %s deployed successfully', 'devsroom-autodeploy'),
            $deployment['plugin_name']
        );

        $message = $this->get_success_message($deployment);

        return $this->send_email($subject, $message);
    }

    /**
     * Send deployment failure notification.
     *
     * @param array $deployment Deployment data.
     * @return bool True on success, false on failure.
     */
    public function send_deployment_failure(array $deployment): bool
    {
        if (! $this->is_notifications_enabled()) {
            return true;
        }

        $subject = sprintf(
            /* translators: %s: Plugin name */
            __('Devsroom AutoDeploy: Deployment failed for %s', 'devsroom-autodeploy'),
            $deployment['plugin_name']
        );

        $message = $this->get_failure_message($deployment);

        return $this->send_email($subject, $message);
    }

    /**
     * Send security scan alert.
     *
     * @param array $scan_result Scan result data.
     * @return bool True on success, false on failure.
     */
    public function send_security_alert(array $scan_result): bool
    {
        if (! $this->is_notifications_enabled()) {
            return true;
        }

        $subject = sprintf(
            /* translators: %s: Plugin name */
            __('Devsroom AutoDeploy: Security scan alert for %s', 'devsroom-autodeploy'),
            $scan_result['plugin_name']
        );

        $message = $this->get_security_alert_message($scan_result);

        return $this->send_email($subject, $message);
    }

    /**
     * Add WordPress admin notice.
     *
     * @param string $message Notice message.
     * @param string $type   Notice type (success, error, warning, info).
     * @return void
     */
    public function add_admin_notice(string $message, string $type = 'info'): void
    {
        add_action(
            'admin_notices',
            function () use ($message, $type) {
                $class = "notice notice-$type is-dismissible";
                printf(
                    '<div class="%1$s"><p>%2$s</p></div>',
                    esc_attr($class),
                    wp_kses_post($message)
                );
            }
        );
    }

    /**
     * Get success message template.
     *
     * @param array $deployment Deployment data.
     * @return string Formatted message.
     */
    private function get_success_message(array $deployment): string
    {
        $message = sprintf(
            /* translators: %s: Plugin name */
            __('The plugin %s has been successfully deployed.', 'devsroom-autodeploy'),
            $deployment['plugin_name']
        ) . "\n\n";

        $message .= __('Deployment Details:', 'devsroom-autodeploy') . "\n";
        $message .= sprintf(
            /* translators: %s: Repository */
            __('Repository: %s', 'devsroom-autodeploy'),
            $deployment['repo_owner'] . '/' . $deployment['repo_name']
        ) . "\n";
        $message .= sprintf(
            /* translators: %s: Branch */
            __('Branch: %s', 'devsroom-autodeploy'),
            $deployment['branch']
        ) . "\n";
        $message .= sprintf(
            /* translators: %s: Commit hash */
            __('Commit: %s', 'devsroom-autodeploy'),
            $deployment['commit_hash']
        ) . "\n";

        if (! empty($deployment['commit_message'])) {
            $message .= sprintf(
                /* translators: %s: Commit message */
                __('Message: %s', 'devsroom-autodeploy'),
                $deployment['commit_message']
            ) . "\n";
        }

        if (! empty($deployment['commit_author'])) {
            $message .= sprintf(
                /* translators: %s: Commit author */
                __('Author: %s', 'devsroom-autodeploy'),
                $deployment['commit_author']
            ) . "\n";
        }

        if (isset($deployment['duration'])) {
            $message .= sprintf(
                /* translators: %s: Duration */
                __('Duration: %s seconds', 'devsroom-autodeploy'),
                $deployment['duration']
            ) . "\n";
        }

        $message .= "\n" . sprintf(
            /* translators: %s: URL */
            __('View deployment logs: %s', 'devsroom-autodeploy'),
            admin_url('admin.php?page=devsroom-autodeploy-deployments')
        );

        return $message;
    }

    /**
     * Get failure message template.
     *
     * @param array $deployment Deployment data.
     * @return string Formatted message.
     */
    private function get_failure_message(array $deployment): string
    {
        $message = sprintf(
            /* translators: %s: Plugin name */
            __('The deployment of %s has failed.', 'devsroom-autodeploy'),
            $deployment['plugin_name']
        ) . "\n\n";

        $message .= __('Deployment Details:', 'devsroom-autodeploy') . "\n";
        $message .= sprintf(
            /* translators: %s: Repository */
            __('Repository: %s', 'devsroom-autodeploy'),
            $deployment['repo_owner'] . '/' . $deployment['repo_name']
        ) . "\n";
        $message .= sprintf(
            /* translators: %s: Branch */
            __('Branch: %s', 'devsroom-autodeploy'),
            $deployment['branch']
        ) . "\n";

        if (! empty($deployment['error_message'])) {
            $message .= "\n" . __('Error:', 'devsroom-autodeploy') . "\n";
            $message .= $deployment['error_message'] . "\n";
        }

        $message .= "\n" . sprintf(
            /* translators: %s: URL */
            __('View deployment logs: %s', 'devsroom-autodeploy'),
            admin_url('admin.php?page=devsroom-autodeploy-deployments')
        );

        return $message;
    }

    /**
     * Get security alert message template.
     *
     * @param array $scan_result Scan result data.
     * @return string Formatted message.
     */
    private function get_security_alert_message(array $scan_result): string
    {
        $message = sprintf(
            /* translators: %s: Plugin name */
            __('Security scan detected issues in %s.', 'devsroom-autodeploy'),
            $scan_result['plugin_name']
        ) . "\n\n";

        $message .= __('Scan Results:', 'devsroom-autodeploy') . "\n";
        $message .= sprintf(
            /* translators: %d: Number of files scanned */
            __('Files Scanned: %d', 'devsroom-autodeploy'),
            $scan_result['scanned']
        ) . "\n";
        $message .= sprintf(
            /* translators: %d: Number of issues found */
            __('Issues Found: %d', 'devsroom-autodeploy'),
            $scan_result['errors']
        ) . "\n";

        if (! empty($scan_result['issues'])) {
            $message .= "\n" . __('Issues:', 'devsroom-autodeploy') . "\n";
            foreach ($scan_result['issues'] as $issue) {
                $message .= sprintf(
                    "[%s] %s\n",
                    strtoupper($issue['type']),
                    $issue['message']
                );
                if (isset($issue['file'])) {
                    $message .= sprintf("File: %s\n", $issue['file']);
                }
                if (isset($issue['line']) && $issue['line'] > 0) {
                    $message .= sprintf("Line: %d\n", $issue['line']);
                }
                $message .= "\n";
            }
        }

        return $message;
    }

    /**
     * Send email notification.
     *
     * @param string $subject Email subject.
     * @param string $message Email message.
     * @return bool True on success, false on failure.
     */
    private function send_email(string $subject, string $message): bool
    {
        $to = $this->get_notification_email();

        if (empty($to)) {
            return false;
        }

        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
        );

        return wp_mail($to, $subject, $message, $headers);
    }

    /**
     * Check if notifications are enabled.
     *
     * @return bool True if enabled, false otherwise.
     */
    private function is_notifications_enabled(): bool
    {
        return (bool) get_option('devsroom_autodeploy_enable_notifications', true);
    }

    /**
     * Get notification email address.
     *
     * @return string Email address.
     */
    private function get_notification_email(): string
    {
        $email = get_option('devsroom_autodeploy_notification_email', '');

        if (empty($email)) {
            $email = get_option('admin_email');
        }

        return $email;
    }
}
