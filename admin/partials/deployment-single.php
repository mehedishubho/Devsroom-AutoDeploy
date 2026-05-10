<?php

/**
 * Single deployment view template.
 *
 * @package Devsroom_AutoDeploy
 */

if (! defined('ABSPATH')) {
    exit;
}

$status_map = array(
    'success'      => 'success',
    'failed'       => 'error',
    'pending'      => 'warning',
    'scanning'     => 'info',
    'backing_up'   => 'info',
    'locking'      => 'locking',
    'comparing'    => 'comparing',
    'downloading'  => 'downloading',
    'extracting'   => 'extracting',
    'deploying'    => 'deploying',
    'verifying'    => 'verifying',
    'rolling_back' => 'rolling_back',
    'cancelled'    => 'cancelled',
);

$trigger_labels = array(
    'webhook' => __('Webhook', 'devsroom-autodeploy'),
    'polling' => __('Polling', 'devsroom-autodeploy'),
    'manual'  => __('Manual', 'devsroom-autodeploy'),
);

$terminal_statuses = array('success', 'failed', 'cancelled');
$is_terminal = in_array($deployment['status'], $terminal_statuses, true);

$pipeline_steps = array(
    'locking'     => array('icon' => 'lock', 'label' => __('Lock', 'devsroom-autodeploy')),
    'backing_up'  => array('icon' => 'backup', 'label' => __('Backup', 'devsroom-autodeploy')),
    'comparing'   => array('icon' => 'search', 'label' => __('Compare', 'devsroom-autodeploy')),
    'downloading' => array('icon' => 'download', 'label' => __('Download', 'devsroom-autodeploy')),
    'extracting'  => array('icon' => 'archive', 'label' => __('Extract', 'devsroom-autodeploy')),
    'scanning'    => array('icon' => 'shield', 'label' => __('Scan', 'devsroom-autodeploy')),
    'deploying'   => array('icon' => 'migrate', 'label' => __('Deploy', 'devsroom-autodeploy')),
    'verifying'   => array('icon' => 'yes-alt', 'label' => __('Verify', 'devsroom-autodeploy')),
);

$completed_steps = array();
$active_step = null;
$failed_step = null;

foreach ($logs as $log) {
    $msg = strtolower($log['message']);
    if (strpos($msg, 'lock') !== false && !in_array('locking', $completed_steps)) $completed_steps[] = 'locking';
    if (strpos($msg, 'backup') !== false && !in_array('backing_up', $completed_steps)) $completed_steps[] = 'backing_up';
    if (strpos($msg, 'compar') !== false && !in_array('comparing', $completed_steps)) $completed_steps[] = 'comparing';
    if (strpos($msg, 'download') !== false && !in_array('downloading', $completed_steps)) $completed_steps[] = 'downloading';
    if (strpos($msg, 'extract') !== false && !in_array('extracting', $completed_steps)) $completed_steps[] = 'extracting';
    if (strpos($msg, 'scan') !== false && !in_array('scanning', $completed_steps)) $completed_steps[] = 'scanning';
    if (strpos($msg, 'deploy') !== false && !in_array('deploying', $completed_steps)) $completed_steps[] = 'deploying';
    if (strpos($msg, 'verif') !== false && !in_array('verifying', $completed_steps)) $completed_steps[] = 'verifying';
}
?>

<div class="wrap devsroom-autodeploy">
    <h1 class="devsroom-page-head">
        <span class="dashicons dashicons-visibility"></span>
        <?php esc_html_e('Deployment Details', 'devsroom-autodeploy'); ?>
    </h1>

    <a href="<?php echo esc_url(admin_url('admin.php?page=devsroom-autodeploy-deployments')); ?>" class="ds-back-link">
        <span class="dashicons dashicons-arrow-left-alt2"></span>
        <?php esc_html_e('Back to Deployments', 'devsroom-autodeploy'); ?>
    </a>

    <div class="devsroom-section devsroom-panel">
        <h2><?php esc_html_e('Pipeline Progress', 'devsroom-autodeploy'); ?></h2>
        <div class="ds-pipeline">
            <?php
            $step_keys = array_keys($pipeline_steps);
            foreach ($pipeline_steps as $step_key => $step_info) :
                $step_index = array_search($step_key, $step_keys);
                $is_completed = in_array($step_key, $completed_steps);
                $is_active = (!$is_terminal && $step_index === count($completed_steps));
                $is_failed = ($deployment['status'] === 'failed' && $is_active);
                $is_rolling = ($deployment['status'] === 'rolling_back' && $step_key === 'verifying');

                $step_class = '';
                if ($is_completed) $step_class = 'ds-pipeline-step--success';
                elseif ($is_active && !$is_failed) $step_class = 'ds-pipeline-step--active';
                elseif ($is_failed || $is_rolling) $step_class = 'ds-pipeline-step--failed';
            ?>
                <?php if ($step_index > 0) : ?>
                    <div class="ds-pipeline-connector<?php echo $is_completed ? ' ds-pipeline-connector--success' : ''; ?>"></div>
                <?php endif; ?>
                <div class="ds-pipeline-step <?php echo esc_attr($step_class); ?>">
                    <div class="ds-pipeline-step-icon">
                        <span class="dashicons dashicons-<?php echo esc_attr($step_info['icon']); ?>"></span>
                    </div>
                    <div class="ds-pipeline-step-label"><?php echo esc_html($step_info['label']); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="devsroom-section devsroom-panel">
        <h2><?php esc_html_e('Deployment Information', 'devsroom-autodeploy'); ?></h2>

        <div class="ds-detail-grid">
            <div class="ds-detail-item">
                <div class="ds-detail-item-label"><?php esc_html_e('Plugin', 'devsroom-autodeploy'); ?></div>
                <div class="ds-detail-item-value"><strong><?php echo esc_html($deployment['plugin_slug']); ?></strong></div>
            </div>

            <div class="ds-detail-item">
                <div class="ds-detail-item-label"><?php esc_html_e('Repository', 'devsroom-autodeploy'); ?></div>
                <div class="ds-detail-item-value">
                    <a href="<?php echo esc_url('https://github.com/' . $deployment['repo_owner'] . '/' . $deployment['repo_name']); ?>" target="_blank">
                        <?php echo esc_html($deployment['repo_owner'] . '/' . $deployment['repo_name']); ?>
                    </a>
                </div>
            </div>

            <div class="ds-detail-item">
                <div class="ds-detail-item-label"><?php esc_html_e('Branch', 'devsroom-autodeploy'); ?></div>
                <div class="ds-detail-item-value">
                    <span class="ds-branch-tag">
                        <span class="dashicons dashicons-admin-branch"></span>
                        <?php echo esc_html($deployment['branch']); ?>
                    </span>
                </div>
            </div>

            <div class="ds-detail-item">
                <div class="ds-detail-item-label"><?php esc_html_e('Status', 'devsroom-autodeploy'); ?></div>
                <div class="ds-detail-item-value">
                    <span class="status-badge status-<?php echo esc_attr($status_map[$deployment['status']] ?? 'info'); ?>">
                        <?php echo esc_html(ucfirst(str_replace('_', ' ', $deployment['status']))); ?>
                    </span>
                </div>
            </div>

            <div class="ds-detail-item">
                <div class="ds-detail-item-label"><?php esc_html_e('Commit', 'devsroom-autodeploy'); ?></div>
                <div class="ds-detail-item-value">
                    <code><?php echo esc_html($deployment['commit_hash']); ?></code>
                    <button class="copy-to-clipboard button button-small ds-ml-1" data-copy="<?php echo esc_attr($deployment['commit_hash']); ?>">
                        <?php esc_html_e('Copy', 'devsroom-autodeploy'); ?>
                    </button>
                </div>
            </div>

            <div class="ds-detail-item">
                <div class="ds-detail-item-label"><?php esc_html_e('Commit Message', 'devsroom-autodeploy'); ?></div>
                <div class="ds-detail-item-value"><?php echo esc_html($deployment['commit_message']); ?></div>
            </div>

            <div class="ds-detail-item">
                <div class="ds-detail-item-label"><?php esc_html_e('Commit Author', 'devsroom-autodeploy'); ?></div>
                <div class="ds-detail-item-value"><?php echo esc_html($deployment['commit_author']); ?></div>
            </div>

            <div class="ds-detail-item">
                <div class="ds-detail-item-label"><?php esc_html_e('Trigger', 'devsroom-autodeploy'); ?></div>
                <div class="ds-detail-item-value">
                    <?php echo esc_html($trigger_labels[$deployment['trigger_type']] ?? $deployment['trigger_type']); ?>
                </div>
            </div>

            <div class="ds-detail-item">
                <div class="ds-detail-item-label"><?php esc_html_e('Duration', 'devsroom-autodeploy'); ?></div>
                <div class="ds-detail-item-value">
                    <?php echo $deployment['duration'] ? esc_html($deployment['duration']) . ' ' . esc_html__('seconds', 'devsroom-autodeploy') : '—'; ?>
                </div>
            </div>

            <div class="ds-detail-item">
                <div class="ds-detail-item-label"><?php esc_html_e('Started', 'devsroom-autodeploy'); ?></div>
                <div class="ds-detail-item-value">
                    <?php echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $deployment['started_at'])); ?>
                </div>
            </div>

            <div class="ds-detail-item">
                <div class="ds-detail-item-label"><?php esc_html_e('Completed', 'devsroom-autodeploy'); ?></div>
                <div class="ds-detail-item-value">
                    <?php echo $deployment['completed_at'] ? esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $deployment['completed_at'])) : '—'; ?>
                </div>
            </div>

            <?php if (! empty($deployment['backup_path'])) : ?>
                <div class="ds-detail-item">
                    <div class="ds-detail-item-label"><?php esc_html_e('Backup Path', 'devsroom-autodeploy'); ?></div>
                    <div class="ds-detail-item-value"><code><?php echo esc_html($deployment['backup_path']); ?></code></div>
                </div>
            <?php endif; ?>
        </div>

        <?php if (! empty($deployment['error_message'])) : ?>
            <hr class="ds-section-divider">
            <div class="notice notice-error ds-m-0">
                <p><strong><?php esc_html_e('Error:', 'devsroom-autodeploy'); ?></strong> <?php echo esc_html($deployment['error_message']); ?></p>
            </div>
        <?php endif; ?>
    </div>

    <div class="devsroom-section devsroom-panel">
        <h2><?php esc_html_e('Deployment Logs', 'devsroom-autodeploy'); ?></h2>

        <?php if (empty($logs)) : ?>
            <div class="ds-empty-state">
                <span class="dashicons dashicons-media-text"></span>
                <h3><?php esc_html_e('No logs yet', 'devsroom-autodeploy'); ?></h3>
                <p><?php esc_html_e('Logs will appear here as the deployment progresses.', 'devsroom-autodeploy'); ?></p>
            </div>
        <?php else : ?>
            <div class="devsroom-table-wrap">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 140px;"><?php esc_html_e('Time', 'devsroom-autodeploy'); ?></th>
                            <th style="width: 80px;"><?php esc_html_e('Level', 'devsroom-autodeploy'); ?></th>
                            <th><?php esc_html_e('Message', 'devsroom-autodeploy'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log) : ?>
                            <tr>
                                <td>
                                    <span class="text-muted">
                                        <?php echo esc_html(mysql2date(get_option('time_format'), $log['created_at'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="log-level log-<?php echo esc_attr($log['level']); ?>">
                                        <?php echo esc_html(strtoupper($log['level'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="ds-log-message"><?php echo esc_html($log['message']); ?></span>
                                    <?php if (! empty($log['context'])) : ?>
                                        <a href="#" class="ds-log-context-toggle toggle-log-context">
                                            <?php esc_html_e('details', 'devsroom-autodeploy'); ?>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if (! empty($log['context'])) : ?>
                                <tr class="log-context-row" style="display: none;">
                                    <td colspan="3">
                                        <pre class="ds-log-context-json"><?php echo esc_html(is_string($log['context']) ? $log['context'] : wp_json_encode($log['context'], JSON_PRETTY_PRINT)); ?></pre>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
