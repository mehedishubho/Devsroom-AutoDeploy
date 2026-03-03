<?php

/**
 * Webhook Handler class.
 *
 * @package Devsroom_AutoDeploy
 */

namespace Devsroom_AutoDeploy\Public;

use Devsroom_AutoDeploy\Core\Deployment_Manager;
use Devsroom_AutoDeploy\Core\GitHub_API;

/**
 * Class Webhook_Handler
 *
 * Handles GitHub webhook requests.
 *
 * @since 1.0.0
 */
class Webhook_Handler
{

    /**
     * Register REST API routes.
     *
     * @return void
     */
    public function register_routes(): void
    {
        register_rest_route(
            'devsroom-autodeploy/v1',
            '/webhook/(?P<secret>[a-zA-Z0-9]+)',
            array(
                'methods'             => 'POST',
                'callback'            => array($this, 'handle_webhook'),
                'permission_callback' => '__return_true',
            )
        );
    }

    /**
     * Handle webhook request.
     *
     * @param \WP_REST_Request $request REST request object.
     * @return \WP_REST_Response REST response.
     */
    public function handle_webhook(\WP_REST_Request $request): \WP_REST_Response
    {
        $secret = $request->get_param('secret');
        $payload = $request->get_body();
        $signature = $request->get_header('X-Hub-Signature-256');

        // Find repository by webhook secret.
        $repository = $this->get_repository_by_secret($secret);

        if (! $repository) {
            return new \WP_REST_Response(
                array(
                    'success' => false,
                    'message' => 'Invalid webhook secret.',
                ),
                401
            );
        }

        // Verify signature.
        if (! GitHub_API::verify_webhook_signature($payload, $signature, $secret)) {
            return new \WP_REST_Response(
                array(
                    'success' => false,
                    'message' => 'Invalid webhook signature.',
                ),
                401
            );
        }

        // Parse payload.
        $data = GitHub_API::parse_webhook_payload($payload);

        if (! $data) {
            return new \WP_REST_Response(
                array(
                    'success' => false,
                    'message' => 'Invalid webhook payload.',
                ),
                400
            );
        }

        // Check event type.
        $event = $request->get_header('X-GitHub-Event');

        if ('push' !== $event) {
            return new \WP_REST_Response(
                array(
                    'success' => true,
                    'message' => 'Event ignored (only push events are processed).',
                ),
                200
            );
        }

        // Check if repository and branch match.
        $repo_full_name = $data['repository']['full_name'] ?? '';
        $ref = $data['ref'] ?? '';

        if ($repo_full_name !== $repository['repo_owner'] . '/' . $repository['repo_name']) {
            return new \WP_REST_Response(
                array(
                    'success' => false,
                    'message' => 'Repository does not match.',
                ),
                400
            );
        }

        $branch = str_replace('refs/heads/', '', $ref);

        if ($branch !== $repository['branch']) {
            return new \WP_REST_Response(
                array(
                    'success' => true,
                    'message' => 'Branch does not match (ignored).',
                ),
                200
            );
        }

        // Trigger deployment.
        $deployment_manager = Deployment_Manager::get_instance();
        $result = $deployment_manager->deploy($repository['id'], 'webhook', 0);

        if ($result['success']) {
            if (isset($result['skipped']) && $result['skipped']) {
                return new \WP_REST_Response(
                    array(
                        'success' => true,
                        'message' => $result['message'],
                    ),
                    200
                );
            }

            return new \WP_REST_Response(
                array(
                    'success'       => true,
                    'message'       => $result['message'],
                    'deployment_id' => $result['deployment_id'],
                    'commit_hash'   => $result['commit_hash'],
                ),
                200
            );
        }

        return new \WP_REST_Response(
            array(
                'success' => false,
                'message' => $result['message'],
            ),
            500
        );
    }

    /**
     * Get repository by webhook secret.
     *
     * @param string $secret Webhook secret.
     * @return array|false Repository data or false on failure.
     */
    private function get_repository_by_secret(string $secret): array|false
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'devsroom_repositories';

        $repository = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE webhook_secret = %s AND status = 'active'",
                $secret
            ),
            ARRAY_A
        );

        return $repository ?: false;
    }
}
