<?php

/**
 * GitHub API Client.
 *
 * @package Devsoom_AutoDeploy
 */

namespace Devsoom_AutoDeploy\Core;

/**
 * Class GitHub_API
 *
 * Handles communication with GitHub REST API.
 *
 * @since 1.0.0
 */
class GitHub_API
{

    /**
     * GitHub API base URL.
     *
     * @var string
     */
    private string $api_url = 'https://api.github.com';

    /**
     * Access token.
     *
     * @var string
     */
    private string $token;

    /**
     * Constructor.
     *
     * @param string $token GitHub access token.
     */
    public function __construct(string $token = '')
    {
        $this->token = $token;
    }

    /**
     * Set the access token.
     *
     * @param string $token GitHub access token.
     * @return void
     */
    public function set_token(string $token): void
    {
        $this->token = $token;
    }

    /**
     * Get authenticated user info.
     *
     * @return array|false User info or false on failure.
     */
    public function get_authenticated_user(): array|false
    {
        return $this->request('GET', '/user');
    }

    /**
     * Get repository information.
     *
     * @param string $owner Repository owner.
     * @param string $repo  Repository name.
     * @return array|false Repository info or false on failure.
     */
    public function get_repository(string $owner, string $repo): array|false
    {
        return $this->request('GET', "/repos/$owner/$repo");
    }

    /**
     * Get branch information.
     *
     * @param string $owner Repository owner.
     * @param string $repo  Repository name.
     * @param string $branch Branch name.
     * @return array|false Branch info or false on failure.
     */
    public function get_branch(string $owner, string $repo, string $branch): array|false
    {
        return $this->request('GET', "/repos/$owner/$repo/branches/$branch");
    }

    /**
     * Get commit history for a branch.
     *
     * @param string $owner Repository owner.
     * @param string $repo  Repository name.
     * @param string $branch Branch name.
     * @param int    $limit  Number of commits to retrieve.
     * @return array|false Array of commits or false on failure.
     */
    public function get_commits(string $owner, string $repo, string $branch, int $limit = 1): array|false
    {
        $commits = $this->request('GET', "/repos/$owner/$repo/commits?sha=$branch&per_page=$limit");

        if (is_array($commits) && isset($commits[0])) {
            return $commits;
        }

        return false;
    }

    /**
     * Get latest commit for a branch.
     *
     * @param string $owner Repository owner.
     * @param string $repo  Repository name.
     * @param string $branch Branch name.
     * @return array|false Commit info or false on failure.
     */
    public function get_latest_commit(string $owner, string $repo, string $branch): array|false
    {
        $commits = $this->get_commits($owner, $repo, $branch, 1);

        if ($commits && isset($commits[0])) {
            return $commits[0];
        }

        return false;
    }

    /**
     * Download repository archive.
     *
     * @param string $owner Repository owner.
     * @param string $repo  Repository name.
     * @param string $branch Branch name.
     * @return string|false Archive URL or false on failure.
     */
    public function get_archive_url(string $owner, string $repo, string $branch): string|false
    {
        return "$this->api_url/repos/$owner/$repo/zipball/$branch";
    }

    /**
     * Download repository archive to a file.
     *
     * @param string $owner    Repository owner.
     * @param string $repo     Repository name.
     * @param string $branch   Branch name.
     * @param string $save_path Path to save the archive.
     * @return bool True on success, false on failure.
     */
    public function download_archive(string $owner, string $repo, string $branch, string $save_path): bool
    {
        $url = $this->get_archive_url($owner, $repo, $branch);

        if (! $url) {
            return false;
        }

        $response = wp_remote_get(
            $url,
            array(
                'headers' => array(
                    'Authorization' => "token {$this->token}",
                    'Accept'        => 'application/vnd.github.v3+json',
                ),
                'timeout'       => 300,
                'stream'        => true,
                'filename'      => $save_path,
            )
        );

        if (is_wp_error($response)) {
            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);

        return 200 === $status_code;
    }

    /**
     * Get repository webhooks.
     *
     * @param string $owner Repository owner.
     * @param string $repo  Repository name.
     * @return array|false Array of webhooks or false on failure.
     */
    public function get_webhooks(string $owner, string $repo): array|false
    {
        return $this->request('GET', "/repos/$owner/$repo/hooks");
    }

    /**
     * Create a webhook.
     *
     * @param string $owner   Repository owner.
     * @param string $repo    Repository name.
     * @param string $url     Webhook URL.
     * @param string $secret  Webhook secret.
     * @param array  $events  Events to trigger on.
     * @return array|false Webhook info or false on failure.
     */
    public function create_webhook(string $owner, string $repo, string $url, string $secret, array $events = array('push')): array|false
    {
        $data = array(
            'name'   => 'web',
            'active' => true,
            'events' => $events,
            'config' => array(
                'url'          => $url,
                'content_type' => 'json',
                'secret'       => $secret,
                'insecure_ssl' => '0',
            ),
        );

        return $this->request('POST', "/repos/$owner/$repo/hooks", $data);
    }

    /**
     * Delete a webhook.
     *
     * @param string $owner     Repository owner.
     * @param string $repo      Repository name.
     * @param int    $webhook_id Webhook ID.
     * @return bool True on success, false on failure.
     */
    public function delete_webhook(string $owner, string $repo, int $webhook_id): bool
    {
        $result = $this->request('DELETE', "/repos/$owner/$repo/hooks/$webhook_id");

        return false !== $result;
    }

    /**
     * Make a request to GitHub API.
     *
     * @param string $method HTTP method.
     * @param string $endpoint API endpoint.
     * @param array  $data    Request data.
     * @return array|false Response data or false on failure.
     */
    private function request(string $method, string $endpoint, array $data = array()): array|false
    {
        $url = $this->api_url . $endpoint;

        $headers = array(
            'Accept'        => 'application/vnd.github.v3+json',
            'User-Agent'    => 'Devsoom-AutoDeploy/' . DEVSOMM_AUTODEPLOY_VERSION,
        );

        if ($this->token) {
            $headers['Authorization'] = "token {$this->token}";
        }

        $args = array(
            'method'  => $method,
            'headers' => $headers,
            'timeout' => 30,
        );

        if (! empty($data)) {
            $args['body'] = wp_json_encode($data);
            $headers['Content-Type'] = 'application/json';
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            error_log('Devsoom AutoDeploy: GitHub API request failed - ' . $response->get_error_message());
            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body        = wp_remote_retrieve_body($response);

        // Handle non-2xx responses.
        if ($status_code < 200 || $status_code >= 300) {
            $error_data = json_decode($body, true);
            $message    = $error_data['message'] ?? "GitHub API Error: $status_code";
            error_log("Devsoom AutoDeploy: GitHub API error - $message");
            return false;
        }

        $decoded = json_decode($body, true);
        return $decoded;
    }

    /**
     * Validate webhook signature.
     *
     * @param string $payload Webhook payload.
     * @param string $signature Webhook signature.
     * @param string $secret   Webhook secret.
     * @return bool True if valid, false otherwise.
     */
    public static function verify_webhook_signature(string $payload, string $signature, string $secret): bool
    {
        if (! $signature || ! $secret) {
            return false;
        }

        $parts = explode('=', $signature, 2);

        if (count($parts) !== 2) {
            return false;
        }

        list($algorithm, $hash) = $parts;

        if ('sha256' !== $algorithm) {
            return false;
        }

        $expected_hash = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected_hash, $hash);
    }

    /**
     * Parse webhook payload.
     *
     * @param string $payload Raw webhook payload.
     * @return array|false Parsed payload or false on failure.
     */
    public static function parse_webhook_payload(string $payload): array|false
    {
        $data = json_decode($payload, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }

        return $data;
    }
}
