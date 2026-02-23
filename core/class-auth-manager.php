<?php

/**
 * GitHub Authentication Manager.
 *
 * @package Devsoom_AutoDeploy
 */

namespace Devsoom_AutoDeploy\Core;

/**
 * Class Auth_Manager
 *
 * Manages GitHub authentication using PAT and OAuth.
 *
 * @since 1.0.0
 */
class Auth_Manager
{

    /**
     * Singleton instance.
     *
     * @var Auth_Manager|null
     */
    private static ?Auth_Manager $instance = null;

    /**
     * GitHub OAuth app ID.
     *
     * @var string
     */
    private string $client_id;

    /**
     * GitHub OAuth app secret.
     *
     * @var string
     */
    private string $client_secret;

    /**
     * Get singleton instance.
     *
     * @return Auth_Manager
     */
    public static function get_instance(): Auth_Manager
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct()
    {
        $this->client_id     = get_option('devsoom_autodeploy_github_client_id', '');
        $this->client_secret = get_option('devsoom_autodeploy_github_client_secret', '');
    }

    /**
     * Store a PAT token.
     *
     * @param int    $user_id   WordPress user ID.
     * @param string $token     GitHub PAT token.
     * @param string $token_name Token display name.
     * @return int|false Token ID or false on failure.
     */
    public function store_pat_token(int $user_id, string $token, string $token_name = ''): int|false
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'devsoom_auth_tokens';

        // Encrypt the token.
        $encrypted_token = $this->encrypt_token($token);

        // Insert into database.
        $result = $wpdb->insert(
            $table_name,
            array(
                'user_id'     => $user_id,
                'auth_method' => 'pat',
                'token'       => $encrypted_token,
                'token_name'  => $token_name ?: 'PAT Token',
                'is_active'   => 1,
            ),
            array('%d', '%s', '%s', '%s', '%d')
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Store an OAuth token.
     *
     * @param int    $user_id       WordPress user ID.
     * @param string $access_token  OAuth access token.
     * @param string $refresh_token OAuth refresh token.
     * @param int    $expires_in    Token expiration time in seconds.
     * @param string $scope         Token scope.
     * @return int|false Token ID or false on failure.
     */
    public function store_oauth_token(int $user_id, string $access_token, string $refresh_token = '', int $expires_in = 0, string $scope = ''): int|false
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'devsoom_auth_tokens';

        // Encrypt tokens.
        $encrypted_access  = $this->encrypt_token($access_token);
        $encrypted_refresh = $refresh_token ? $this->encrypt_token($refresh_token) : '';

        // Calculate expiration time.
        $expires_at = $expires_in > 0 ? date('Y-m-d H:i:s', time() + $expires_in) : null;

        // Insert into database.
        $result = $wpdb->insert(
            $table_name,
            array(
                'user_id'        => $user_id,
                'auth_method'    => 'oauth',
                'token'          => $encrypted_access,
                'refresh_token'  => $encrypted_refresh,
                'expires_at'     => $expires_at,
                'scope'          => $scope,
                'token_name'     => 'OAuth Token',
                'is_active'      => 1,
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d')
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Get a token by ID.
     *
     * @param int $token_id Token ID.
     * @return array|false Token data or false on failure.
     */
    public function get_token(int $token_id): array|false
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'devsoom_auth_tokens';

        $token = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE id = %d AND is_active = 1",
                $token_id
            ),
            ARRAY_A
        );

        if (! $token) {
            return false;
        }

        // Decrypt token.
        $token['token'] = $this->decrypt_token($token['token']);

        if ($token['refresh_token']) {
            $token['refresh_token'] = $this->decrypt_token($token['refresh_token']);
        }

        return $token;
    }

    /**
     * Get all tokens for a user.
     *
     * @param int $user_id WordPress user ID.
     * @return array Array of tokens.
     */
    public function get_user_tokens(int $user_id): array
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'devsoom_auth_tokens';

        $tokens = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, auth_method, token_name, expires_at, created_at FROM $table_name WHERE user_id = %d AND is_active = 1 ORDER BY created_at DESC",
                $user_id
            ),
            ARRAY_A
        );

        return $tokens ?: array();
    }

    /**
     * Delete a token.
     *
     * @param int $token_id Token ID.
     * @return bool True on success, false on failure.
     */
    public function delete_token(int $token_id): bool
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'devsoom_auth_tokens';

        $result = $wpdb->update(
            $table_name,
            array('is_active' => 0),
            array('id' => $token_id),
            array('%d'),
            array('%d')
        );

        return false !== $result;
    }

    /**
     * Validate a token by making a test API call.
     *
     * @param string $token GitHub token.
     * @return bool True if valid, false otherwise.
     */
    public function validate_token(string $token): bool
    {
        $github_api = new GitHub_API($token);
        $user_info  = $github_api->get_authenticated_user();

        return ! empty($user_info);
    }

    /**
     * Generate OAuth authorization URL.
     *
     * @param int $user_id WordPress user ID.
     * @return string Authorization URL.
     */
    public function get_oauth_authorization_url(int $user_id): string
    {
        $state     = $this->generate_oauth_state($user_id);
        $verifier  = $this->generate_code_verifier();
        $challenge = $this->generate_code_challenge($verifier);

        // Store verifier for later use.
        update_user_meta($user_id, 'devsoom_autodeploy_oauth_verifier', $verifier);

        $params = array(
            'client_id'             => $this->client_id,
            'redirect_uri'          => admin_url('admin.php?page=devsoom-autodeploy-settings&oauth_callback=1'),
            'scope'                 => 'repo repo:status',
            'state'                 => $state,
            'code_challenge'        => $challenge,
            'code_challenge_method' => 'S256',
        );

        return 'https://github.com/login/oauth/authorize?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for access token.
     *
     * @param string $code     Authorization code.
     * @param int    $user_id  WordPress user ID.
     * @return array|false Token data or false on failure.
     */
    public function exchange_code_for_token(string $code, int $user_id): array|false
    {
        $verifier = get_user_meta($user_id, 'devsoom_autodeploy_oauth_verifier', true);

        if (! $verifier) {
            return false;
        }

        $params = array(
            'client_id'         => $this->client_id,
            'client_secret'     => $this->client_secret,
            'code'              => $code,
            'redirect_uri'      => admin_url('admin.php?page=devsoom-autodeploy-settings&oauth_callback=1'),
            'code_verifier'     => $verifier,
        );

        $response = wp_remote_post(
            'https://github.com/login/oauth/access_token',
            array(
                'headers' => array(
                    'Accept' => 'application/json',
                ),
                'body'    => http_build_query($params),
            )
        );

        if (is_wp_error($response)) {
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body['access_token'])) {
            return false;
        }

        // Clear verifier.
        delete_user_meta($user_id, 'devsoom_autodeploy_oauth_verifier');

        return $body;
    }

    /**
     * Refresh an OAuth token.
     *
     * @param int $token_id Token ID.
     * @return array|false New token data or false on failure.
     */
    public function refresh_oauth_token(int $token_id): array|false
    {
        $token = $this->get_token($token_id);

        if (! $token || 'oauth' !== $token['auth_method'] || empty($token['refresh_token'])) {
            return false;
        }

        $params = array(
            'client_id'     => $this->client_id,
            'client_secret' => $this->client_secret,
            'grant_type'    => 'refresh_token',
            'refresh_token' => $token['refresh_token'],
        );

        $response = wp_remote_post(
            'https://github.com/login/oauth/access_token',
            array(
                'headers' => array(
                    'Accept' => 'application/json',
                ),
                'body'    => http_build_query($params),
            )
        );

        if (is_wp_error($response)) {
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body['access_token'])) {
            return false;
        }

        // Update token in database.
        global $wpdb;
        $table_name = $wpdb->prefix . 'devsoom_auth_tokens';

        $encrypted_access = $this->encrypt_token($body['access_token']);
        $encrypted_refresh = isset($body['refresh_token']) ? $this->encrypt_token($body['refresh_token']) : $token['refresh_token'];

        $expires_at = isset($body['expires_in']) ? date('Y-m-d H:i:s', time() + $body['expires_in']) : $token['expires_at'];

        $wpdb->update(
            $table_name,
            array(
                'token'         => $encrypted_access,
                'refresh_token' => $encrypted_refresh,
                'expires_at'    => $expires_at,
            ),
            array('id' => $token_id),
            array('%s', '%s', '%s'),
            array('%d')
        );

        return $body;
    }

    /**
     * Encrypt a token.
     *
     * @param string $token Token to encrypt.
     * @return string Encrypted token.
     */
    private function encrypt_token(string $token): string
    {
        $key = $this->get_encryption_key();
        $iv  = wp_generate_password(16, false, false);

        $encrypted = openssl_encrypt($token, 'AES-256-CBC', $key, 0, $iv);

        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt a token.
     *
     * @param string $encrypted_token Encrypted token.
     * @return string|false Decrypted token or false on failure.
     */
    private function decrypt_token(string $encrypted_token): string|false
    {
        $key = $this->get_encryption_key();
        $data = base64_decode($encrypted_token);

        if (strlen($data) < 16) {
            return false;
        }

        $iv        = substr($data, 0, 16);
        $encrypted = substr($data, 16);

        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }

    /**
     * Get encryption key.
     *
     * @return string Encryption key.
     */
    private function get_encryption_key(): string
    {
        $key = get_option('devsoom_autodeploy_encryption_key');

        if (! $key) {
            $key = wp_generate_password(32, true, true);
            update_option('devsoom_autodeploy_encryption_key', $key);
        }

        return $key;
    }

    /**
     * Generate OAuth state.
     *
     * @param int $user_id WordPress user ID.
     * @return string OAuth state.
     */
    private function generate_oauth_state(int $user_id): string
    {
        $state = wp_generate_password(32, false, false);
        update_user_meta($user_id, 'devsoom_autodeploy_oauth_state', $state);
        return $state;
    }

    /**
     * Verify OAuth state.
     *
     * @param int    $user_id WordPress user ID.
     * @param string $state   OAuth state to verify.
     * @return bool True if valid, false otherwise.
     */
    public function verify_oauth_state(int $user_id, string $state): bool
    {
        $stored_state = get_user_meta($user_id, 'devsoom_autodeploy_oauth_state', true);
        delete_user_meta($user_id, 'devsoom_autodeploy_oauth_state');
        return hash_equals($stored_state, $state);
    }

    /**
     * Generate code verifier for PKCE.
     *
     * @return string Code verifier.
     */
    private function generate_code_verifier(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Generate code challenge from verifier.
     *
     * @param string $verifier Code verifier.
     * @return string Code challenge.
     */
    private function generate_code_challenge(string $verifier): string
    {
        return strtr(rtrim(base64_encode(hash('sha256', $verifier, true)), '='), '+/', '-_');
    }
}
