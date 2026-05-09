# Technology Stack

**Analysis Date:** 2026-05-10

## Languages

**Primary:**
- PHP 8.0+ - All backend logic, WordPress plugin code. Minimum version enforced via plugin header (`Requires PHP: 8.0`).
  - Uses PHP 8.0 union types (`array|false`), named arguments, typed properties, and `?ClassName` nullable types.
  - Namespace: `Devsroom_AutoDeploy\` across all classes.

**Secondary:**
- JavaScript (ES5/ES6) - Admin UI interactions in `assets/js/admin.js`. Uses jQuery wrapper pattern.
- CSS - Admin styling in `assets/css/admin.css`.

## Runtime

**Environment:**
- WordPress 6.0+ - Minimum version enforced via plugin header (`Requires at least: 6.0`).
- PHP 8.0+ with `openssl` extension (for AES-256-CBC token encryption in `core/class-auth-manager.php`).
- PHP `ZipArchive` extension (for archive extraction and backup creation in `core/class-deployment-manager.php` and `core/class-backup-manager.php`).

**Package Manager:**
- Not applicable - This is a WordPress plugin with no Composer or npm dependency management. All dependencies are WordPress core APIs.

## Frameworks

**Core:**
- WordPress Plugin API - Hooks (actions/filters), REST API, WP-Cron, `WP_Filesystem`, `$wpdb` database abstraction.
- No external PHP frameworks. Pure WordPress-native implementation.

**Frontend:**
- jQuery - Loaded as dependency for admin scripts via `wp_enqueue_script`.
- WordPress Admin UI patterns - `add_menu_page`, `add_submenu_page`, admin notices.

## Key Dependencies

**WordPress APIs Used:**
- `$wpdb` - Direct database queries via `wpdb->insert()`, `wpdb->update()`, `wpdb->delete()`, `wpdb->get_row()`, `wpdb->get_results()`, `wpdb->get_var()`, `wpdb->prepare()`.
- `dbDelta()` - Schema creation and migration (`database/class-schema.php`).
- `wp_remote_request()` / `wp_remote_get()` / `wp_remote_post()` - HTTP client for GitHub API calls.
- `WP_Filesystem` - File operations during deployment (copy, delete directories).
- `WP_REST_Server` - REST API endpoint registration via `register_rest_route()`.
- `wp_schedule_event()` / `wp_clear_scheduled_hook()` - WP-Cron for polling and cleanup.
- `wp_mail()` - Email notifications for deployment events.
- `wp_enqueue_script()` / `wp_enqueue_style()` - Asset loading.
- `WP_REST_Request` / `WP_REST_Response` - REST API request/response handling.

**PHP Extensions Required:**
- `openssl` - Token encryption (`openssl_encrypt`, `openssl_decrypt` with AES-256-CBC).
- `zip` / `ZipArchive` - Archive extraction and backup creation.
- `json` - `json_decode`, `json_encode` for API response parsing and log context serialization.
- `hash` - HMAC-SHA256 for webhook signature verification (`hash_hmac`, `hash_equals`).
- `random_bytes()` - OAuth state and PKCE code verifier generation.
- `SPL` - `RecursiveIteratorIterator`, `RecursiveDirectoryIterator` for file traversal.

**No External Libraries:**
- The plugin has zero third-party Composer/npm dependencies.
- All HTTP communication uses WordPress HTTP API (`wp_remote_*`).
- All encryption uses PHP native `openssl_*` functions.

## Database Technology

**Engine:**
- MySQL / MariaDB (via WordPress `$wpdb`).
- Uses `dbDelta()` for schema management.
- All tables use the `$wpdb->prefix` convention (default `wp_`).

**Tables Created (5 total):**
- `{prefix}devsroom_repositories` - Repository configurations.
- `{prefix}devsroom_auth_tokens` - Encrypted GitHub credentials (PAT/OAuth).
- `{prefix}devsroom_deployments` - Deployment history and status.
- `{prefix}devsroom_logs` - Deployment log entries.
- `{prefix}devsroom_backups` - Backup file records with expiration.

## Configuration

**WordPress Options (stored in `wp_options` table):**
- `devsroom_autodeploy_db_version` - Tracks schema version for migrations.
- `devsroom_autodeploy_activated_at` - Plugin activation timestamp.
- `devsroom_autodeploy_polling_interval` - WP-Cron interval (default: `hourly`).
- `devsroom_autodeploy_backup_retention_days` - Backup expiry in days (default: 30).
- `devsroom_autodeploy_enable_notifications` - Email notification toggle (default: true).
- `devsroom_autodeploy_notification_email` - Notification recipient email.
- `devsroom_autodeploy_max_backup_size_mb` - Max backup file size (default: 100).
- `devsroom_autodeploy_scan_level_default` - Security scan level (default: `basic`).
- `devsroom_autodeploy_github_client_id` - GitHub OAuth App Client ID.
- `devsroom_autodeploy_github_client_secret` - GitHub OAuth App Client Secret.
- `devsroom_autodeploy_encryption_key` - Auto-generated AES-256-CBC encryption key.

**WordPress User Meta:**
- `devsroom_autodeploy_oauth_state` - Temporary OAuth state token for CSRF protection.
- `devsroom_autodeploy_oauth_verifier` - PKCE code verifier for OAuth flow.

**Constants (defined in `devsroom-autodeploy.php`):**
- `DEVSROOM_AUTODEPLOY_VERSION` - Plugin version string (`1.0.0`).
- `DEVSROOM_AUTODEPLOY_PATH` - Absolute filesystem path to plugin directory.
- `DEVSROOM_AUTODEPLOY_URL` - URL to plugin directory.
- `DEVSROOM_AUTODEPLOY_BASENAME` - Plugin basename for WordPress hooks.
- `DEVSROOM_AUTODEPLOY_FILE` - Absolute path to main plugin file.
- `DEVSROOM_AUTODEPLOY_PLUGIN_SLUG` - Plugin directory name (`devsroom-autodeploy`).

## Platform Requirements

**Development:**
- PHP 8.0+ with `openssl`, `zip`, `json` extensions.
- WordPress 6.0+ development environment.
- Git for version control.
- A GitHub account with a Personal Access Token or OAuth App for testing.

**Production:**
- PHP 8.0+ with `openssl` and `zip` extensions.
- WordPress 6.0+.
- MySQL/MariaDB (WordPress compatible version).
- Web server with write access to `WP_CONTENT_DIR` for backup storage.
- Outbound HTTPS connectivity to `api.github.com` and `github.com`.
- WP-Cron enabled (or external cron triggering WordPress cron events).

## Build / Tooling

**No build system:**
- No Composer, Webpack, Vite, or other build tools.
- PHP files are loaded directly via `require_once` and `spl_autoload_register`.
- CSS and JS are served as-is from `assets/` directory.
- The autoloader in `includes/class-loader.php` converts class names to file paths using a convention: namespace `\Devsroom_AutoDeploy\Namespace\Class_Name` maps to `{plugin-path}/namespace/class-name.php`.

---

*Stack analysis: 2026-05-10*
