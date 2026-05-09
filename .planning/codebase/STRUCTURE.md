# Codebase Structure

**Analysis Date:** 2026-05-10

## Directory Layout

```
devsoom-autodeploy/                    # Plugin root
├── devsroom-autodeploy.php            # Main plugin entry point (bootstrap)
├── uninstall.php                      # WordPress uninstall handler
├── .gitignore                         # Git ignore rules
├── includes/                          # Plugin infrastructure
│   ├── class-loader.php               # Autoloader + hook registry (singleton)
│   ├── class-main.php                 # Core orchestrator class
│   ├── class-activator.php            # Plugin activation handler
│   └── class-deactivator.php          # Plugin deactivation handler
├── core/                              # Business logic layer
│   ├── class-deployment-manager.php   # Deployment pipeline orchestrator
│   ├── class-github-api.php           # GitHub REST API client
│   ├── class-auth-manager.php         # GitHub PAT/OAuth token management
│   ├── class-backup-manager.php       # Plugin backup/restore via ZipArchive
│   ├── class-security-scanner.php     # File security scanning (basic/advanced)
│   ├── class-logger.php               # Database-backed deployment logger
│   ├── class-notification.php         # Email + admin notice notifications
│   └── class-polling-scheduler.php    # WP-Cron polling and cleanup
├── admin/                             # Admin UI layer
│   ├── class-admin.php                # Menu registration + asset enqueueing
│   ├── class-dashboard.php            # Dashboard page controller
│   ├── class-repository-manager.php   # Repository CRUD + manual deploy
│   ├── class-deployment-view.php      # Deployment history + detail views
│   ├── class-settings.php             # Settings + OAuth callback handler
│   └── partials/                      # PHP template partials
│       ├── dashboard.php              # Dashboard template
│       ├── repository-form.php        # Repository list + add/edit form
│       ├── deployment-list.php        # Deployment history list template
│       ├── deployment-single.php      # Single deployment detail template
│       └── settings.php               # Settings form template
├── public/                            # Public-facing layer
│   └── class-webhook-handler.php      # REST API webhook endpoint
├── database/                          # Database schema layer
│   └── class-schema.php               # Table creation/drop via dbDelta()
└── assets/                            # Static assets
    ├── css/
    │   └── admin.css                  # Admin styles (CSS custom properties)
    └── js/
        └── admin.js                   # Admin JavaScript (jQuery, AJAX)
```

## Directory Purposes

**`includes/` -- Plugin Infrastructure:**
- Purpose: Plugin lifecycle management, class autoloading, WordPress hook wiring
- Contains: Singleton loader, main orchestrator, activator, deactivator
- Key files: `class-main.php` (central wiring), `class-loader.php` (autoload + hook collection)

**`core/` -- Business Logic:**
- Purpose: All domain logic independent of WordPress admin UI
- Contains: Singleton service classes for deployment, GitHub API, auth, backups, scanning, logging, notifications, scheduling
- Key files: `class-deployment-manager.php` (deployment pipeline), `class-github-api.php` (GitHub communication)

**`admin/` -- Admin UI:**
- Purpose: WordPress admin pages, form handling, template rendering
- Contains: Page controller classes and PHP template partials
- Key files: `class-admin.php` (menu/asset registration), `class-repository-manager.php` (CRUD + deploy triggers)

**`admin/partials/` -- Template Partials:**
- Purpose: PHP HTML templates rendered by admin controllers
- Contains: One partial per admin page (dashboard, repositories, deployments, settings)
- Key files: `repository-form.php` (repo management UI), `settings.php` (auth + settings forms)

**`public/` -- Webhook Endpoint:**
- Purpose: REST API route for GitHub webhook reception
- Contains: Single class `Webhook_Handler`
- Key files: `class-webhook-handler.php`

**`database/` -- Schema Management:**
- Purpose: Database table creation and teardown
- Contains: Single class `Schema`
- Key files: `class-schema.php`

**`assets/` -- Static Resources:**
- Purpose: Admin CSS and JavaScript
- Contains: One CSS file, one JS file (both admin-only)
- Key files: `assets/js/admin.js` (AJAX deployment, UI interactions), `assets/css/admin.css` (admin styling)

## Key File Locations

**Entry Points:**
- `devsroom-autodeploy.php`: Plugin bootstrap -- defines constants, requires loader/main, calls `devsroom_autodeploy_run()`
- `uninstall.php`: Clean uninstall -- drops tables, deletes options, removes backups

**Configuration:**
- `includes/class-main.php`: Central plugin configuration -- loads all dependencies, registers all hooks
- `includes/class-activator.php`: Default option values (polling interval, backup retention, notification settings)
- `includes/class-loader.php`: Autoloader maps `Devsroom_AutoDeploy\SubNamespace\Class_Name` to `sub-namespace/class-name.php`

**Core Logic:**
- `core/class-deployment-manager.php`: Deployment pipeline (617 lines, largest file)
- `core/class-auth-manager.php`: Token encryption, OAuth PKCE flow, PAT management
- `core/class-github-api.php`: GitHub REST API wrapper (repos, commits, archives, webhooks)
- `core/class-backup-manager.php`: ZipArchive-based backup/restore with expiration tracking
- `core/class-security-scanner.php`: Regex-based PHP file scanning (basic + advanced patterns)

**Admin Controllers:**
- `admin/class-admin.php`: WordPress admin menu registration, CSS/JS enqueueing
- `admin/class-repository-manager.php`: Repository CRUD, manual deployment, plugin activation
- `admin/class-settings.php`: Settings persistence, OAuth callback handling, PAT management
- `admin/class-deployment-view.php`: Deployment list with filtering + pagination, single deployment detail
- `admin/class-dashboard.php`: Statistics aggregation, recent deployments

**Templates:**
- `admin/partials/dashboard.php`: Dashboard page with stats cards, recent deployments, update status
- `admin/partials/repository-form.php`: Repository list table + add/edit form
- `admin/partials/deployment-list.php`: Paginated deployment history with status filter
- `admin/partials/deployment-single.php`: Single deployment detail with log viewer
- `admin/partials/settings.php`: GitHub OAuth settings, polling config, backup settings, notification settings

**Database Schema:**
- `database/class-schema.php`: Creates 5 custom tables via `dbDelta()`

## Database Tables

All tables use the `{$wpdb->prefix}devsroom_` prefix:

| Table | Purpose | Key Columns |
|-------|---------|-------------|
| `devsroom_repositories` | GitHub repo connections | `plugin_slug` (unique), `repo_owner`, `repo_name`, `branch`, `webhook_secret` (unique), `last_commit_hash`, `status` |
| `devsroom_auth_tokens` | Encrypted GitHub tokens | `user_id`, `auth_method` (pat/oauth), `token` (AES-256-CBC encrypted), `refresh_token`, `expires_at` |
| `devsroom_deployments` | Deployment history | `repository_id`, `commit_hash`, `trigger_type` (webhook/polling/manual), `status`, `scan_result` (JSON), `duration` |
| `devsroom_logs` | Per-deployment log entries | `deployment_id`, `level` (info/warning/error/debug), `message`, `context` (JSON) |
| `devsroom_backups` | Backup file tracking | `repository_id`, `backup_path`, `file_size`, `commit_hash`, `expires_at` |

## WordPress Options

Options stored with `devsroom_autodeploy_` prefix:

| Option | Default | Purpose |
|--------|---------|---------|
| `devsroom_autodeploy_db_version` | plugin version | Tracks schema version for upgrades |
| `devsroom_autodeploy_activated_at` | current time | Activation timestamp |
| `devsroom_autodeploy_polling_interval` | `'hourly'` | WP-Cron recurrence for polling |
| `devsroom_autodeploy_backup_retention_days` | `30` | Days before backups expire |
| `devsroom_autodeploy_enable_notifications` | `true` | Email notification toggle |
| `devsroom_autodeploy_notification_email` | admin_email | Notification recipient |
| `devsroom_autodeploy_max_backup_size_mb` | `100` | Max backup size limit |
| `devsroom_autodeploy_scan_level_default` | `'basic'` | Default security scan level |
| `devsroom_autodeploy_github_client_id` | `''` | GitHub OAuth App client ID |
| `devsroom_autodeploy_github_client_secret` | `''` | GitHub OAuth App client secret |
| `devsroom_autodeploy_encryption_key` | auto-generated | AES-256-CBC key for token encryption |

## Naming Conventions

**Files:**
- PHP class files: `class-{lowercase-hyphenated-name}.php` (e.g., `class-deployment-manager.php`)
- Template partials: `{lowercase-hyphenated-name}.php` (e.g., `deployment-list.php`)
- Assets: `{scope}.{ext}` (e.g., `admin.css`, `admin.js`)

**PHP Classes:**
- Namespaced as `Devsroom_AutoDeploy\{SubNamespace}\{Class_Name}`
- Sub-namespaces: `Core`, `Admin`, `Public` (note: PHP reserved word), `Database`
- Class names: `Upper_Snake_Case` with underscores (e.g., `Deployment_Manager`, `GitHub_API`)

**Database Tables:**
- Prefix: `{$wpdb->prefix}devsroom_`
- Table names: `lowercase` with underscores (e.g., `devsroom_repositories`)

**WordPress Hooks (custom):**
- WP-Cron events: `devsroom_autodeploy_{event_name}` (e.g., `devsroom_autodeploy_polling_event`)
- AJAX actions: `devsroom_autodeploy_{action_name}` (e.g., `devsroom_autodeploy_dismiss_recent_deployments`)
- Nonces: `devsroom_autodeploy_nonce`
- REST route namespace: `devsroom-autodeploy/v1`

**Admin Page Slugs:**
- `devsroom-autodeploy` (Dashboard)
- `devsroom-autodeploy-repositories` (Repositories)
- `devsroom-autodeploy-deployments` (Deployments)
- `devsroom-autodeploy-settings` (Settings)

## Where to Add New Code

**New Core Service (e.g., Slack notifications):**
- Create `core/class-slack-notifier.php` with namespace `Devsroom_AutoDeploy\Core`
- Use Singleton pattern with `get_instance()` static method
- Add `require_once` in `includes/class-main.php` inside `load_dependencies()`
- Wire any hooks in `Main::define_core_hooks()` or `define_admin_hooks()`

**New Admin Page (e.g., activity log page):**
- Create `admin/class-activity-page.php` with namespace `Devsroom_AutoDeploy\Admin`
- Add `require_once` in `Main::load_dependencies()`
- Add `add_submenu_page()` call in `Admin::add_plugin_menu()`
- Add display method in `Admin` class
- Create template in `admin/partials/activity-page.php`

**New REST API Endpoint:**
- Add method to existing `public/class-webhook-handler.php` or create new handler in `public/`
- Register route in `register_routes()` method
- Wire `rest_api_init` in `Main::define_public_hooks()`

**New Database Table:**
- Add `create_{table_name}_table()` method in `database/class-schema.php`
- Call it from `Schema::create_tables()`
- Add table name to `Schema::drop_tables()` array
- Add drop logic in `uninstall.php`

**New WP-Cron Event:**
- Register action in `Polling_Scheduler::__construct()`
- Schedule in `Polling_Scheduler::schedule()`
- Clear in `Polling_Scheduler::clear_schedule()` and `Deactivator::deactivate()`

**New Admin Asset:**
- CSS: Add to `assets/css/admin.css` (single file)
- JS: Add to `assets/js/admin.js` (single file, jQuery IIFE wrapper)
- Both only load on `devsroom-autodeploy` admin pages

**New Partial Template:**
- Create file in `admin/partials/{name}.php`
- Include from admin controller using `include DEVSROOM_AUTODEPLOY_PATH . 'admin/partials/{name}.php'`
- Variables are passed from the controller scope (no explicit data passing)

## Special Directories

**`{WP_CONTENT_DIR}/devsroom-autodeploy-backups/`:**
- Purpose: Stores plugin backup ZIP files
- Created at: Runtime when first backup is needed (`Backup_Manager::ensure_backup_directory()`)
- Protected with `.htaccess` (`Deny from all`) and `index.php`
- Generated: Yes (created by plugin)
- Committed: No (gitignored)

**`{sys_temp_dir()}/devsroom-autodeploy-{id}-{timestamp}/`:**
- Purpose: Temporary directory for downloading and extracting GitHub archives during deployment
- Created at: Deployment time by `Deployment_Manager::deploy()`
- Cleaned up: Immediately after each deployment (`cleanup_temp_dir()`)
- Generated: Yes
- Committed: No

**`languages/`:**
- Purpose: WordPress translation files (text domain: `devsroom-autodeploy`)
- Referenced but directory may be empty -- translations loaded via `load_plugin_textdomain()`
- Generated: By translation tools
- Committed: Yes (when translations exist)

## Bootstrap Sequence

The plugin loads in this order:

1. WordPress loads `devsroom-autodeploy.php`
2. Constants defined (`DEVSROOM_AUTODEPLOY_VERSION`, `_PATH`, `_URL`, `_BASENAME`, `_FILE`, `_PLUGIN_SLUG`)
3. `includes/class-loader.php` required -- `Loader::get_instance()` initializes autoloader via `spl_autoload_register()`
4. `includes/class-main.php` required
5. `devsroom_autodeploy_run()` called -- creates `new Main()`, calls `$plugin->run()`
6. `Main::__construct()` calls:
   - `load_dependencies()` -- `require_once` all class files, register activation/deactivation hooks
   - `set_locale()` -- hooks `load_plugin_textdomain` to `plugins_loaded`
   - `define_admin_hooks()` -- creates `Admin`, registers `admin_menu`, `admin_enqueue_scripts`
   - `define_public_hooks()` -- creates `Webhook_Handler`, hooks `rest_api_init`
   - `define_core_hooks()` -- hooks polling scheduler to `init`, schema upgrade to `plugins_loaded`
7. `Main::run()` calls `Loader::run()` which registers all collected hooks with WordPress

---

*Structure analysis: 2026-05-10*
