# Architecture

**Analysis Date:** 2026-05-10

## Pattern Overview

**Overall:** WordPress Plugin Architecture with layered module separation

The plugin follows a classic WordPress plugin pattern with a central bootstrap class (`Main`) that wires together admin, public, and core layers via WordPress hooks. Core business logic uses the Singleton pattern for shared services. The architecture is not MVC -- view rendering happens inside admin classes that directly include PHP partials, while business logic lives in dedicated core classes.

**Key Characteristics:**
- Singleton pattern for all core service classes (`Deployment_Manager`, `Auth_Manager`, `Logger`, `Backup_Manager`, `Notification`, `Polling_Scheduler`, `Loader`)
- WordPress hook system as the primary event bus (actions and filters registered through `Loader`)
- REST API endpoint for webhook ingress (WordPress REST API)
- WP-Cron for scheduled polling and cleanup
- Direct `$wpdb` queries for database access (no ORM)
- PHP namespace `Devsroom_AutoDeploy` with sub-namespaces for `Core`, `Admin`, `Public`, `Database`

## Layers

**Bootstrap / Plugin Entry:**
- Purpose: Define constants, load autoloader, instantiate `Main`, register activation/deactivation hooks
- Location: `devsroom-autodeploy.php`
- Contains: Constants (`DEVSROOM_AUTODEPLOY_VERSION`, `_PATH`, `_URL`, `_BASENAME`, `_FILE`, `_PLUGIN_SLUG`), entry function
- Depends on: `includes/class-loader.php`, `includes/class-main.php`

**Infrastructure Layer (`includes/`):**
- Purpose: Plugin lifecycle, class autoloading, hook registration
- Location: `includes/class-main.php`, `includes/class-loader.php`, `includes/class-activator.php`, `includes/class-deactivator.php`
- Contains: `Main` (orchestrator), `Loader` (singleton hook manager + PSR-4-style autoloader), `Activator`, `Deactivator`
- Depends on: All other layers (loads them via `require_once`)
- Used by: WordPress plugin system via entry file

**Core Business Logic (`core/`):**
- Purpose: All domain logic -- deployment orchestration, GitHub API, auth, backups, security scanning, logging, notifications, polling
- Location: `core/class-deployment-manager.php`, `core/class-github-api.php`, `core/class-auth-manager.php`, `core/class-backup-manager.php`, `core/class-security-scanner.php`, `core/class-logger.php`, `core/class-notification.php`, `core/class-polling-scheduler.php`
- Contains: Singleton service classes
- Depends on: WordPress `$wpdb`, WordPress Filesystem API, PHP `ZipArchive`, PHP `openssl_*` functions
- Used by: `Admin` layer, `Public` webhook handler, WP-Cron callbacks

**Admin UI Layer (`admin/`):**
- Purpose: WordPress admin menu pages, form handling, settings management, rendering partials
- Location: `admin/class-admin.php`, `admin/class-dashboard.php`, `admin/class-repository-manager.php`, `admin/class-deployment-view.php`, `admin/class-settings.php`
- Contains: Page controllers that render `admin/partials/*.php` templates
- Depends on: `Core` layer classes
- Used by: WordPress admin via `admin_menu` hook

**Public / Webhook Layer (`public/`):**
- Purpose: REST API endpoint for receiving GitHub webhooks
- Location: `public/class-webhook-handler.php`
- Contains: `Webhook_Handler` with REST route registration and request handling
- Depends on: `Deployment_Manager`, `GitHub_API`
- Used by: WordPress REST API via `rest_api_init` hook

**Database Layer (`database/`):**
- Purpose: Schema creation and teardown using `dbDelta()`
- Location: `database/class-schema.php`
- Contains: `Schema` with static `create_tables()` and `drop_tables()` methods
- Depends on: WordPress `dbDelta()`, `$wpdb`
- Used by: `Activator`, `Deactivator` (indirectly), `Main::maybe_upgrade_database()`

## Data Flow

**Webhook-Triggered Deployment:**

1. GitHub sends POST to `/wp-json/devsroom-autodeploy/v1/webhook/{secret}`
2. `Webhook_Handler::handle_webhook()` receives `WP_REST_Request`
3. Looks up repository by webhook secret in `devsroom_repositories` table
4. Verifies HMAC-SHA256 signature via `GitHub_API::verify_webhook_signature()`
5. Validates push event targets matching repository + branch
6. Calls `Deployment_Manager::deploy()` with trigger_type `'webhook'`
7. Deployment_Manager fetches auth token, creates deployment record, downloads archive, optionally backs up and scans, then deploys files
8. Result returned as `WP_REST_Response`

**Polling-Triggered Deployment:**

1. WP-Cron fires `devsroom_autodeploy_polling_event` at configured interval
2. `Polling_Scheduler::poll_repositories()` queries active repos with `auto_deploy = 1`
3. For each repo, fetches latest commit via `GitHub_API`
4. If commit hash differs from `last_commit_hash`, calls `Deployment_Manager::deploy()` with trigger_type `'polling'`

**Manual Deployment (Admin UI):**

1. Admin user clicks "Deploy Now" or "Deploy & Activate" on Repositories page
2. `Repository_Manager::trigger_deployment()` handles POST form submission
3. Calls `Deployment_Manager::deploy()` with trigger_type `'manual'`
4. Optionally calls `activate_plugin()` to activate the deployed plugin
5. Redirects back with status query param

**State Management:**
- All state persists in WordPress database tables (5 custom tables)
- No in-memory state between requests beyond singletons within a single PHP process
- Deployment status tracked via `status` enum: `pending`, `backing_up`, `scanning`, `success`, `failed`

## Key Abstractions

**Deployment Pipeline (Deployment_Manager):**
- Purpose: Orchestrates the full deployment lifecycle
- Location: `core/class-deployment-manager.php`
- Pattern: Singleton, template method (deploy method defines the pipeline steps)
- Pipeline steps: validate repo -> fetch commit -> check if already deployed -> create record -> backup -> download archive -> extract -> security scan -> copy files -> update status -> notify

**GitHub API Client (GitHub_API):**
- Purpose: HTTP client for GitHub REST API v3
- Location: `core/class-github-api.php`
- Pattern: Instance-based (constructed with a token), uses `wp_remote_request()`
- Provides: repo/branch/commit queries, archive download, webhook CRUD, signature verification

**Authentication (Auth_Manager):**
- Purpose: Manages GitHub PAT and OAuth tokens with AES-256-CBC encryption
- Location: `core/class-auth-manager.php`
- Pattern: Singleton, uses OpenSSL encryption for token storage at rest
- Supports: PAT (manual entry), OAuth 2.0 with PKCE flow

**Loader (Autoloader + Hook Registry):**
- Purpose: PSR-4-style class autoloading and deferred WordPress hook registration
- Location: `includes/class-loader.php`
- Pattern: Singleton, collects hooks into arrays then registers them all in `run()`

## Entry Points

**Plugin Bootstrap (`devsroom-autodeploy.php`):**
- Location: `devsroom-autodeploy.php`
- Triggers: WordPress plugin loading
- Responsibilities: Define constants, require loader and main class, call `devsroom_autodeploy_run()`

**Activation Hook:**
- Location: `includes/class-activator.php`
- Triggers: `register_activation_hook()` in `Main::load_dependencies()`
- Responsibilities: Create DB tables via `Schema::create_tables()`, set default options, record activation timestamp, flush rewrite rules

**Deactivation Hook:**
- Location: `includes/class-deactivator.php`
- Triggers: `register_deactivation_hook()` in `Main::load_dependencies()`
- Responsibilities: Clear WP-Cron events, flush rewrite rules

**Uninstall (`uninstall.php`):**
- Location: `uninstall.php`
- Triggers: WordPress uninstall process
- Responsibilities: Delete all options, drop all custom tables, delete user meta, remove backup directory recursively

**REST API Webhook Endpoint:**
- Location: `public/class-webhook-handler.php`
- Triggers: `rest_api_init` action
- Route: `devsroom-autodeploy/v1/webhook/{secret}` (POST)
- Responsibilities: Receive and validate GitHub push webhooks, trigger deployment

**WP-Cron Events:**
- Location: `core/class-polling-scheduler.php`
- Triggers: `init` action (scheduling), WP-Cron (execution)
- Events: `devsroom_autodeploy_polling_event` (configurable interval), `devsroom_autodeploy_cleanup_event` (daily)

**Admin Menu Pages:**
- Location: `admin/class-admin.php`
- Triggers: `admin_menu` action
- Pages: Dashboard (`devsroom-autodeploy`), Repositories (`devsroom-autodeploy-repositories`), Deployments (`devsroom-autodeploy-deployments`), Settings (`devsroom-autodeploy-settings`)

## Error Handling

**Strategy:** Layered -- each layer handles errors differently

**Patterns:**
- Core classes return `array|false` with `success` boolean and `message` string (no exceptions thrown)
- Database operations check `$wpdb` return values and return `false` on failure
- GitHub API errors logged via `error_log()` and return `false`
- Deployment pipeline logs each step via `Logger` (info/warning/error levels to DB)
- Admin form handlers redirect with query params (`?error=...`, `?saved=true`)
- Webhook handler returns `WP_REST_Response` with appropriate HTTP status codes (401, 400, 500)

## Security Model

**Authentication:**
- Admin pages require `manage_options` WordPress capability
- Form submissions verified with WordPress nonces (`devsroom_autodeploy_nonce`)
- Webhook endpoint uses HMAC-SHA256 signature verification
- Webhook URL contains a random secret per repository (32-char generated by `wp_generate_password`)
- OAuth state parameter stored in user meta and verified with `hash_equals()`
- GitHub tokens encrypted at rest with AES-256-CBC using a site-specific key stored in `wp_options`
- Self-deployment prevention: `Deployment_Manager` rejects deploying to its own plugin directory
- `Repository_Manager` rejects `plugin_slug` matching `DEVSROOM_AUTODEPLOY_PLUGIN_SLUG`

**Authorization:**
- All admin actions check `current_user_can('manage_options')`
- AJAX handlers verify nonces via `check_ajax_referer()`
- REST API webhook uses `__return_true` for permission_callback (public endpoint secured by secret in URL + HMAC signature)

## Cross-Cutting Concerns

**Logging:** Custom database-backed logger (`core/class-logger.php`) with levels (info, warning, error, debug). Logs are per-deployment with JSON context. Cleanup via WP-Cron daily event.

**Validation:** Input sanitized at admin layer (`sanitize_text_field`, `sanitize_email`). SQL parameterized via `$wpdb->prepare()`. Scan levels validated against enum whitelist.

**Internationalization:** Text domain `devsroom-autodeploy`, loaded via `load_plugin_textdomain()`. Translation strings use `__()` and `sprintf()` with translator comments.

**Asset Loading:** CSS/JS loaded only on plugin admin pages (hook suffix check). JS localized with `wp_localize_script()` for AJAX URL, nonce, and translatable strings.

---

*Architecture analysis: 2026-05-10*
