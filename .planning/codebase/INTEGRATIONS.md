# External Integrations

**Analysis Date:** 2026-05-10

## APIs & External Services

**GitHub REST API v3:**
- Base URL: `https://api.github.com`
- Purpose: Repository access, commit history, archive downloads, webhook management.
- SDK/Client: Custom client in `core/class-github-api.php` using WordPress HTTP API (`wp_remote_request`).
- Auth: Bearer token (`Authorization: token {token}`) via PAT or OAuth access token.
- User-Agent header: `Devsroom-AutoDeploy/{version}`.
- Accept header: `application/vnd.github.v3+json`.
- Request timeout: 30 seconds (standard), 300 seconds (archive downloads).

**GitHub REST API Endpoints Used:**
| Endpoint | Method | Purpose | File |
|---|---|---|---|
| `/user` | GET | Validate token / get authenticated user | `core/class-github-api.php` |
| `/repos/{owner}/{repo}` | GET | Get repository info | `core/class-github-api.php` |
| `/repos/{owner}/{repo}/branches/{branch}` | GET | Get branch info | `core/class-github-api.php` |
| `/repos/{owner}/{repo}/commits?sha={branch}&per_page={n}` | GET | Get commit history | `core/class-github-api.php` |
| `/repos/{owner}/{repo}/zipball/{branch}` | GET | Download repository archive | `core/class-github-api.php` |
| `/repos/{owner}/{repo}/hooks` | GET | List repository webhooks | `core/class-github-api.php` |
| `/repos/{owner}/{repo}/hooks` | POST | Create webhook | `core/class-github-api.php` |
| `/repos/{owner}/{repo}/hooks/{id}` | DELETE | Delete webhook | `core/class-github-api.php` |

**GitHub OAuth 2.0 (with PKCE):**
- Authorization URL: `https://github.com/login/oauth/authorize`
- Token exchange URL: `https://github.com/login/oauth/access_token`
- Scopes requested: `repo repo:status`
- PKCE method: S256 (SHA-256 code challenge)
- Redirect URI: `admin.php?page=devsroom-autodeploy-settings&oauth_callback=1`
- Implementation: `core/class-auth-manager.php`
- Client credentials stored in: `devsroom_autodeploy_github_client_id`, `devsroom_autodeploy_github_client_secret` WordPress options.

## Data Storage

**Databases:**
- MySQL/MariaDB via WordPress `$wpdb` abstraction.
- Connection: Inherited from WordPress configuration (`WP_DB_HOST`, `WP_DB_USER`, etc. in `wp-config.php`).
- Client: `$wpdb` global instance with `prepare()` for parameterized queries.
- Schema management: `dbDelta()` via `database/class-schema.php`.

**Custom Database Tables (5 tables):**

`{prefix}devsroom_repositories` - GitHub repository configurations:
| Column | Type | Description |
|---|---|---|
| `id` | bigint(20) unsigned PK AUTO_INCREMENT | Repository ID |
| `plugin_slug` | varchar(255) UNIQUE | WordPress plugin directory slug |
| `repo_owner` | varchar(255) | GitHub repository owner/org |
| `repo_name` | varchar(255) | GitHub repository name |
| `branch` | varchar(100) | Target branch (default: `main`) |
| `auth_method` | enum('pat','oauth') | Authentication type |
| `auth_token_id` | bigint(20) unsigned FK | References `devsroom_auth_tokens.id` |
| `auto_deploy` | tinyint(1) | Enable automatic deployment |
| `webhook_secret` | varchar(100) UNIQUE | HMAC-SHA256 webhook verification secret |
| `enable_backup` | tinyint(1) | Create backup before deploying |
| `scan_level` | enum('none','basic','advanced') | Security scan depth |
| `last_commit_hash` | varchar(100) | Last deployed commit SHA |
| `last_deployed_at` | datetime | Timestamp of last successful deploy |
| `status` | enum('active','paused','error') | Repository status |

`{prefix}devsroom_auth_tokens` - Encrypted GitHub credentials:
| Column | Type | Description |
|---|---|---|
| `id` | bigint(20) unsigned PK AUTO_INCREMENT | Token ID |
| `user_id` | bigint(20) unsigned | WordPress user who owns the token |
| `auth_method` | enum('pat','oauth') | Token type |
| `token` | text (AES-256-CBC encrypted) | GitHub access token |
| `token_name` | varchar(255) | Display name |
| `refresh_token` | text (AES-256-CBC encrypted) | OAuth refresh token |
| `expires_at` | datetime | Token expiration |
| `scope` | text | OAuth scope string |
| `is_active` | tinyint(1) | Soft delete flag |

`{prefix}devsroom_deployments` - Deployment history:
| Column | Type | Description |
|---|---|---|
| `id` | bigint(20) unsigned PK AUTO_INCREMENT | Deployment ID |
| `repository_id` | bigint(20) unsigned FK | References `devsroom_repositories.id` |
| `commit_hash` | varchar(100) | Deployed commit SHA |
| `commit_message` | text | Commit message |
| `commit_author` | varchar(255) | Commit author name |
| `trigger_type` | enum('webhook','polling','manual') | How deployment was triggered |
| `status` | enum('pending','success','failed','scanning','backing_up') | Deployment status |
| `backup_path` | varchar(500) | Path to backup ZIP |
| `scan_result` | text (JSON) | Security scan results |
| `error_message` | text | Error details on failure |
| `started_at` | datetime | Deployment start time |
| `completed_at` | datetime | Deployment completion time |
| `duration` | int(11) | Duration in seconds |
| `created_by` | bigint(20) unsigned | WordPress user ID (0 for automated) |

`{prefix}devsroom_logs` - Deployment activity logs:
| Column | Type | Description |
|---|---|---|
| `id` | bigint(20) unsigned PK AUTO_INCREMENT | Log ID |
| `deployment_id` | bigint(20) unsigned FK | References `devsroom_deployments.id` |
| `level` | enum('info','warning','error','debug') | Log severity |
| `message` | text | Log message |
| `context` | text (JSON) | Additional structured data |
| `created_at` | datetime | Timestamp |

`{prefix}devsroom_backups` - Backup file records:
| Column | Type | Description |
|---|---|---|
| `id` | bigint(20) unsigned PK AUTO_INCREMENT | Backup ID |
| `repository_id` | bigint(20) unsigned FK | References `devsroom_repositories.id` |
| `deployment_id` | bigint(20) unsigned FK | References `devsroom_deployments.id` |
| `backup_path` | varchar(500) | Full filesystem path to ZIP file |
| `file_size` | bigint(20) | Size in bytes |
| `commit_hash` | varchar(100) | Commit SHA at backup time |
| `created_at` | datetime | Backup creation timestamp |
| `expires_at` | datetime | Auto-deletion timestamp |

**Table Relationships:**
```
devsroom_auth_tokens (1) ---> (N) devsroom_repositories (via auth_token_id)
devsroom_repositories (1) --> (N) devsroom_deployments (via repository_id)
devsroom_repositories (1) --> (N) devsroom_backups (via repository_id)
devsroom_deployments (1) --> (N) devsroom_logs (via deployment_id)
devsroom_deployments (1) --> (0..1) devsroom_backups (via deployment_id)
```

**File Storage:**
- Backup directory: `{WP_CONTENT_DIR}/devsroom-autodeploy-backups/`
- Protected via `.htaccess` (`Deny from all`) and `index.php` (`// Silence is golden.`).
- Temp directory: `{system_temp_dir}/devsroom-autodeploy-{repo_id}-{timestamp}/` for download/extraction.
- Auto-generated encryption key stored in `devsroom_autodeploy_encryption_key` WordPress option.

**Caching:**
- None. No object cache or transient usage. All data is read directly from the database.

## Authentication & Identity

**GitHub Authentication (dual method):**

1. **Personal Access Token (PAT):**
   - User enters a GitHub PAT via the settings page (`admin/class-settings.php`).
   - Token validated by calling `GET /user` on the GitHub API.
   - Encrypted with AES-256-CBC and stored in `devsroom_auth_tokens` table.
   - Encryption key auto-generated and stored in `devsroom_autodeploy_encryption_key` option.
   - Implementation: `core/class-auth-manager.php` (`store_pat_token()`, `get_token()`).

2. **OAuth 2.0 with PKCE:**
   - Authorization URL: `https://github.com/login/oauth/authorize`
   - Token exchange: `https://github.com/login/oauth/access_token`
   - PKCE flow: Code verifier stored in user meta, S256 challenge sent with authorization request.
   - CSRF protection: Random state stored in user meta, verified on callback.
   - Supports token refresh via `refresh_token` grant.
   - Implementation: `core/class-auth-manager.php` (`get_oauth_authorization_url()`, `exchange_code_for_token()`, `refresh_oauth_token()`).

**WordPress Authentication:**
- Admin pages require `manage_options` capability.
- Nonce verification on all form submissions (`wp_verify_nonce`).
- AJAX endpoints verify `check_ajax_referer`.
- REST API webhook endpoint is publicly accessible (auth via webhook secret + HMAC signature).

## Webhooks & Callbacks

**Incoming Webhook (GitHub -> WordPress):**
- Endpoint: `POST /wp-json/devsroom-autodeploy/v1/webhook/{secret}`
- Registered in: `public/class-webhook-handler.php` via `register_rest_route()`.
- Permission callback: `__return_true` (public endpoint, auth via HMAC).
- Expected headers:
  - `X-Hub-Signature-256: sha256={hmac_hash}` - HMAC-SHA256 signature of the payload body.
  - `X-GitHub-Event: push` - Event type. Only `push` events are processed.
- Expected payload (JSON): GitHub push event payload with `repository.full_name`, `ref` (e.g., `refs/heads/main`).
- Verification: HMAC-SHA256 using `hash_equals()` for timing-safe comparison.
- Flow:
  1. Extract `secret` from URL path parameter.
  2. Look up repository in database by `webhook_secret` (must be `active` status).
  3. Verify HMAC-SHA256 signature against payload body + webhook secret.
  4. Parse JSON payload, verify repository full_name and branch match.
  5. Trigger deployment via `Deployment_Manager::deploy()` with `trigger_type='webhook'`.
- Responses:
  - `200` + `{"success": true}` - Deploy triggered or no-op (branch mismatch, already up-to-date).
  - `400` + `{"success": false}` - Invalid payload, repository mismatch.
  - `401` + `{"success": false}` - Invalid secret or signature.
  - `500` + `{"success": false}` - Deployment failure.

**Outgoing Webhooks (WordPress -> GitHub):**
- Webhook creation: `POST /repos/{owner}/{repo}/hooks` during repository setup in `admin/class-repository-manager.php`.
- Webhook deletion: `DELETE /repos/{owner}/{repo}/hooks/{id}` during repository deletion.
- Webhook config: `content_type: json`, `insecure_ssl: 0`, events: `['push']`.

## WordPress Hooks (Actions & Filters)

**Actions Registered:**

| Hook | Component | Callback | File | Purpose |
|---|---|---|---|---|
| `plugins_loaded` | Main | `load_plugin_textdomain()` | `includes/class-main.php` | Load i18n translations |
| `plugins_loaded` | Main | `maybe_upgrade_database()` | `includes/class-main.php` | Run schema migrations on version change |
| `admin_menu` | Admin | `add_plugin_menu()` | `admin/class-admin.php` | Register admin menu pages |
| `admin_enqueue_scripts` | Admin | `enqueue_styles()` | `admin/class-admin.php` | Load admin CSS on plugin pages |
| `admin_enqueue_scripts` | Admin | `enqueue_scripts()` | `admin/class-admin.php` | Load admin JS on plugin pages |
| `rest_api_init` | Webhook_Handler | `register_routes()` | `public/class-webhook-handler.php` | Register webhook REST route |
| `init` | Polling_Scheduler | `schedule()` | `core/class-polling-scheduler.php` | Ensure WP-Cron events are scheduled |
| `admin_notices` | Notification | (closure) | `core/class-notification.php` | Display admin notices |
| `wp_ajax_devsroom_autodeploy_dismiss_recent_deployments` | Dashboard | `ajax_dismiss_recent_deployments()` | `admin/class-dashboard.php` | AJAX handler for dismissing notices |
| `devsroom_autodeploy_polling_event` | Polling_Scheduler | `poll_repositories()` | `core/class-polling-scheduler.php` | WP-Cron polling action |
| `devsroom_autodeploy_cleanup_event` | Polling_Scheduler | `cleanup()` | `core/class-polling-scheduler.php` | WP-Cron cleanup action |

**WP-Cron Scheduled Events:**

| Event Name | Default Interval | Purpose |
|---|---|---|
| `devsroom_autodeploy_polling_event` | `hourly` (configurable) | Check GitHub repos for new commits |
| `devsroom_autodeploy_cleanup_event` | `daily` | Delete expired logs and backups |

**Activation Hook:**
- `register_activation_hook()` -> `Activator::activate()` in `includes/class-activator.php`.
  - Creates database tables via `Schema::create_tables()`.
  - Sets default options.
  - Records activation timestamp.
  - Flushes rewrite rules.

**Deactivation Hook:**
- `register_deactivation_hook()` -> `Deactivator::deactivate()` in `includes/class-deactivator.php`.
  - Clears scheduled WP-Cron events.
  - Flushes rewrite rules.

**Uninstall:**
- `uninstall.php` runs on plugin deletion.
  - Deletes all plugin options.
  - Deletes all user meta (`devsroom_autodeploy_oauth_state`, `devsroom_autodeploy_oauth_verifier`).
  - Drops all custom database tables.
  - Recursively deletes backup directory and files.

## Monitoring & Observability

**Error Tracking:**
- `error_log()` calls in `core/class-github-api.php` for GitHub API request failures.
- No external error tracking service.

**Logs:**
- Custom logging via `core/class-logger.php` writing to `devsroom_logs` database table.
- Four log levels: `info`, `warning`, `error`, `debug`.
- Logs are associated with specific `deployment_id` for traceability.
- Log context stored as JSON in the `context` column.
- Automatic cleanup of logs older than 30 days via WP-Cron.

**Notifications:**
- Email notifications via `wp_mail()` in `core/class-notification.php`.
- Three notification types:
  1. Deployment success - includes repo, branch, commit, duration.
  2. Deployment failure - includes repo, branch, error message.
  3. Security scan alert - includes scan results and issue details.
- Recipient: `devsroom_autodeploy_notification_email` option, falls back to WordPress `admin_email`.
- Toggle: `devsroom_autodeploy_enable_notifications` option.
- WordPress admin notices via `admin_notices` hook for in-dashboard alerts.

## Security Scanning

**Built-in Security Scanner:**
- Implementation: `core/class-security-scanner.php`.
- Runs during deployment (after download, before file copy).
- Two scan levels:
  - `basic` - Checks for `eval()`, `exec()`, `shell_exec()`, `base64_decode()`, `system()`, `curl_exec()`, and other dangerous functions.
  - `advanced` - Adds obfuscation patterns, variable function calls, dynamic includes, backtick execution, `GLOBALS` access, and malware signature matching.
- Scans all `.php` files recursively in the downloaded archive.
- Results stored as JSON in `devsroom_deployments.scan_result` column.
- Issues trigger email notification via `Notification::send_security_alert()`.

## CI/CD & Deployment

**Hosting:**
- WordPress plugin, deployed manually or via the plugin's own deployment mechanism.

**CI Pipeline:**
- None detected. No CI/CD configuration files present.

## Environment Configuration

**Required env vars / config:**
- Standard WordPress configuration (`wp-config.php`) with database credentials.
- GitHub OAuth App credentials (configured via Settings admin page, stored in WordPress options):
  - `devsroom_autodeploy_github_client_id`
  - `devsroom_autodeploy_github_client_secret`
- Or GitHub Personal Access Token (entered via Settings page).

**Secrets location:**
- GitHub tokens encrypted (AES-256-CBC) in `devsroom_auth_tokens` database table.
- Encryption key stored in `devsroom_autodeploy_encryption_key` WordPress option.
- GitHub OAuth client secret stored in `devsroom_autodeploy_github_client_secret` WordPress option.
- PKCE code verifier stored in user meta `devsroom_autodeploy_oauth_verifier`.
- Webhook secrets stored in plaintext in `devsroom_repositories.webhook_secret` column.

---

*Integration audit: 2026-05-10*
