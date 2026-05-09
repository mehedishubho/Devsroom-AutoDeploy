# Devsroom AutoDeploy

Automate WordPress plugin deployments from GitHub repositories with atomic safety, incremental sync, and automatic rollback.

## Description

Devsroom AutoDeploy is a WordPress plugin that automates deployment of other WordPress plugins from GitHub repositories to live WordPress sites. It connects your WordPress plugins to GitHub repos and deploys updates automatically via webhooks, scheduled polling, or manual triggers.

The deployment pipeline is built for safety and speed: atomic file swaps ensure a failed deploy never breaks a live site, post-deploy verification catches broken plugins before they go live, automatic rollback restores the previous version on failure, and incremental sync downloads only the files that changed.

## Features

### Deployment Triggers

- **Webhook Deployment** — Instant deployment when you push to the connected GitHub branch. The plugin receives the push event via a REST API endpoint, validates the HMAC-SHA256 signature, and starts deployment immediately.
- **Polling Deployment** — Scheduled checks for new commits (configurable: hourly, twice daily, daily). If the commit hash differs from the last deployed commit, deployment triggers automatically.
- **Manual Deployment** — One-click "Deploy Now" or "Deploy & Activate" from the admin Repositories page.

### Deployment Safety (v2.0)

- **Atomic File Swaps** — Deploys to a temporary directory first, then renames into place. The live plugin directory is never deleted until the new version is verified and swapped in. If the swap fails, the old version remains intact. Uses `rename()` on POSIX (atomic) with copy fallback for Windows.
- **Post-Deploy Verification** — After every deployment, the plugin verifies: main plugin file exists, PHP syntax is valid (via `token_get_all()`), WordPress plugin header is present, critical files are readable, and OPcache is cleared. If any check fails, deployment is marked as failed.
- **Automatic Rollback** — If post-deploy verification fails, the previous version is automatically restored from the `.old` directory. No manual intervention needed.
- **Deployment Locking** — Per-plugin database-based locking prevents concurrent deployments to the same repository. Locks use atomic SQL updates and auto-expire after 10 minutes (stale lock detection). Admins can force-unlock from the repository page.
- **Error Recovery** — The entire deployment pipeline is wrapped in try/finally for guaranteed cleanup. A shutdown handler catches PHP fatal errors and cleans up temp directories. A daily WP-Cron job removes orphaned temp directories older than 1 hour.

### Performance (v2.0)

- **Incremental File Sync** — Uses the GitHub Compare API to detect exactly which files changed between the last deployed commit and the new one. Only changed files are downloaded and synced. Falls back to full archive download when >100 files changed or on first deploy.
- **Memory-Safe Extraction** — ZIP archives are extracted entry-by-entry using `getStream()` + `stream_copy_to_stream()` instead of `extractTo()`. Prevents memory exhaustion on large plugins (WooCommerce, page builders).
- **Concurrent Pipeline** — Backup creation and GitHub archive download run concurrently using `curl_multi`. The backup writes to local disk while the archive downloads over HTTP simultaneously.

### GitHub Integration

- **Authentication** — Connect via GitHub OAuth 2.0 (with PKCE) or Personal Access Token. Tokens are encrypted at rest with AES-256-CBC.
- **Repository Management** — Add/edit/delete repositories with branch selection, auth token, scan level, and auto-deploy toggle. Supports public and private repositories.
- **Webhook Auto-Setup** — When you connect a repository, the plugin automatically creates a GitHub webhook with HMAC-SHA256 signing.

### Security

- **Security Scanning** — Configurable scanning (none, basic, advanced) for malicious PHP patterns: injection, obfuscation, suspicious function calls.
- **Admin-Only Access** — All admin operations check `manage_options` capability.
- **Nonce Verification** — All form submissions verified with WordPress nonces.
- **HMAC Webhook Verification** — Webhook payloads verified with timing-safe `hash_equals()`.
- **Encrypted Token Storage** — GitHub tokens encrypted with AES-256-CBC before storage.
- **Self-Deployment Prevention** — The plugin cannot deploy to its own directory.
- **Input Sanitization** — All inputs sanitized (`sanitize_text_field`, `sanitize_email`, integer casting).
- **SQL Injection Prevention** — All queries use `$wpdb->prepare()` with parameterized statements.

### Admin Dashboard

- **Dashboard** — Overview of connected repositories, recent deployments, and update status.
- **Repositories** — Manage connected repos, trigger deployments, view lock status, force-unlock.
- **Deployments** — Full deployment history with status tracking (pending, backing_up, scanning, deploying, verifying, success, failed). Per-deployment logs with info/warning/error/debug levels.
- **Settings** — Configure polling interval, backup retention, notification email, scan defaults, GitHub OAuth credentials.

### Notifications

- Email notifications for deployment success, failure, and security alerts.
- Configurable notification email address (defaults to admin email).
- Enable/disable notifications globally.

## How It Works

### Deployment Pipeline

```
┌─────────────────────────────────────────────────────────────┐
│                    Deployment Trigger                         │
│  (Webhook push / Polling detected change / Manual click)     │
└──────────────────────────┬──────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────┐
│  1. Acquire Lock                                             │
│     - Atomic UPDATE on devsroom_repositories                 │
│     - Reject if already locked (within 10 min)               │
│     - Auto-clear stale locks older than 10 min               │
└──────────────────────────┬──────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────┐
│  2. Concurrent: Backup + Download                            │
│     - Backup existing plugin to ZIP (local disk I/O)         │
│     - Download GitHub archive (HTTP via curl_multi)          │
│     - Both run simultaneously                                │
└──────────────────────────┬──────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────┐
│  3. Extract to Temp Directory                                │
│     - Entry-by-entry extraction (memory-safe)                │
│     - Temp dir in WP_CONTENT_DIR/upgrade/ (same filesystem)  │
└──────────────────────────┬──────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────┐
│  4. Security Scan (if enabled)                               │
│     - Basic or advanced pattern matching                     │
│     - Results logged to deployment record                    │
└──────────────────────────┬──────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────┐
│  5. Atomic Swap                                              │
│     - Move current plugin to .old                            │
│     - Move new plugin into place                             │
│     - Fallback to copy if rename() fails (Windows)           │
└──────────────────────────┬──────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────┐
│  6. Post-Deploy Verification                                 │
│     ✓ Main plugin file exists                                │
│     ✓ PHP syntax valid (token_get_all)                       │
│     ✓ Plugin header present                                  │
│     ✓ Critical files readable                                │
│     ✓ OPcache cleared                                        │
└──────────────────────────┬──────────────────────────────────┘
                           │
                    ┌──────┴──────┐
                    │             │
                    ▼             ▼
              ┌──────────┐  ┌──────────────┐
              │  PASSED   │  │    FAILED     │
              │           │  │               │
              │ Clean up  │  │ Auto-rollback │
              │ .old dir  │  │ Restore .old  │
              │ Release   │  │ Clean up      │
              │ lock      │  │ Release lock  │
              └──────────┘  └──────────────┘
```

### Incremental Sync (v2.0)

When a deployment triggers, the plugin first checks if incremental sync is possible:

```
Last deployed commit: abc123
New commit: def456

GitHub Compare API: GET /repos/{owner}/{repo}/compare/abc123...def456

Response:
  files: [
    { filename: "src/class-foo.php", status: "modified" },
    { filename: "src/class-bar.php", status: "added" },
    { filename: "src/old-file.php", status: "removed" }
  ]

Action:
  - Download class-foo.php (modified)
  - Download class-bar.php (added)
  - Delete old-file.php (removed)
  - Skip all unchanged files
```

If the comparison returns >100 changed files or any download fails, the plugin falls back to a full archive download.

## Requirements

- WordPress 6.0 or higher
- PHP 8.0 or higher
- MySQL 5.6+ or MariaDB equivalent
- `openssl` PHP extension (for token encryption)
- `zip` PHP extension (for archive extraction and backups)
- Outbound HTTPS connectivity to `api.github.com`

## Installation

1. Upload the `devsroom-autodeploy` folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** menu in WordPress
3. Go to **AutoDeploy → Settings** to configure authentication

## Quick Start

### 1. Add Authentication

**Option A: Personal Access Token (simplest)**

1. Go to GitHub → Settings → Developer settings → Personal access tokens → Tokens (classic)
2. Generate a new token with `repo` scope
3. Go to **AutoDeploy → Settings → Authentication**
4. Add the token with a descriptive name

**Option B: GitHub OAuth**

1. Create a GitHub OAuth App at GitHub → Settings → Developer settings → OAuth Apps
2. Set callback URL: `https://yoursite.com/wp-admin/admin.php?page=devsroom-autodeploy-settings&oauth_callback=1`
3. Copy Client ID and Client Secret
4. Go to **AutoDeploy → Settings → Authentication**
5. Enter credentials and click "Connect with GitHub OAuth"

### 2. Connect a Repository

1. Go to **AutoDeploy → Repositories**
2. Click "Add New Repository"
3. Fill in:
   - **Plugin Slug**: The WordPress plugin directory name (e.g., `my-plugin`)
   - **Repository Owner**: GitHub username or organization (e.g., `my-org`)
   - **Repository Name**: GitHub repository name (e.g., `my-plugin`)
   - **Branch**: Branch to track (default: `main`)
   - **Authentication Token**: Select a saved token
4. Configure options:
   - **Auto Deploy**: Enable automatic deployment on push/polling
   - **Backup**: Create backup before deployment
   - **Security Scan Level**: None, Basic, or Advanced
5. Save — a webhook is automatically created on the GitHub repository

### 3. Deploy

**Automatic (webhook):** Push to the connected branch → deployment starts instantly.

**Automatic (polling):** The plugin checks for new commits on schedule → deployment starts if changes detected.

**Manual:** Go to **AutoDeploy → Repositories** → click "Deploy Now" or "Deploy & Activate".

### 4. Monitor

Go to **AutoDeploy → Deployments** to view:
- Deployment status (pending, backing_up, scanning, deploying, verifying, success, failed)
- Per-deployment logs with timestamps and context
- Deployment duration and commit hash

## Configuration

### General Settings

| Setting | Description | Default |
|---------|-------------|---------|
| Polling Interval | How often to check for updates | Hourly |
| Backup Retention | Days to keep backups (1-365) | 30 |
| Maximum Backup Size | Max size per backup in MB (1-1000) | 100 |
| Enable Notifications | Send email on deployment events | Enabled |
| Notification Email | Email for notifications | Admin email |
| Default Scan Level | Security scan for new repos | Basic |

### Webhook URL Format

When you connect a repository, the plugin creates a GitHub webhook at:

```
https://yoursite.com/wp-json/devsroom-autodeploy/v1/webhook/{SECRET}
```

Where `{SECRET}` is a unique 32-character secret generated per repository.

## Database Schema

The plugin creates 5 custom tables:

| Table | Purpose |
|-------|---------|
| `{prefix}devsroom_repositories` | Repository configurations, branch, auth, lock state |
| `{prefix}devsroom_auth_tokens` | Encrypted GitHub credentials (PAT/OAuth) |
| `{prefix}devsroom_deployments` | Deployment history and status |
| `{prefix}devsroom_logs` | Per-deployment log entries |
| `{prefix}devsroom_backups` | Backup file records with expiration |

## Architecture

```
devsroom-autodeploy.php          # Plugin entry point
├── includes/
│   ├── class-main.php           # Orchestrator, dependency loading
│   ├── class-loader.php         # Autoloader + hook registry
│   ├── class-activator.php      # DB schema, cron scheduling
│   └── class-deactivator.php    # Cron cleanup
├── core/
│   ├── class-deployment-manager.php  # Deployment pipeline (locking, atomic swap, verification, rollback)
│   ├── class-github-api.php          # GitHub REST API client
│   ├── class-auth-manager.php        # Token management (PAT + OAuth)
│   ├── class-backup-manager.php      # Backup creation and restoration
│   ├── class-security-scanner.php    # PHP pattern scanning
│   ├── class-logger.php              # Per-deployment logging
│   ├── class-notification.php        # Email notifications
│   └── class-polling-scheduler.php   # WP-Cron polling + orphan cleanup
├── admin/
│   ├── class-admin.php              # Menu registration, asset loading
│   ├── class-dashboard.php          # Dashboard page
│   ├── class-repository-manager.php # Repository CRUD + force-unlock
│   ├── class-deployment-view.php    # Deployment detail page
│   ├── class-settings.php           # Settings page
│   └── partials/                    # PHP templates
├── public/
│   └── class-webhook-handler.php    # REST API webhook endpoint
└── database/
    └── class-schema.php             # Table creation via dbDelta()
```

## Changelog

### 2.0.0

- **Deployment Locking** — Per-plugin database-based locking prevents concurrent deployments. Stale locks auto-expire after 10 minutes. Admin force-unlock button on repository page.
- **Atomic File Swaps** — Replaced delete-then-copy with rename-based atomic swap. Temp directory uses `WP_CONTENT_DIR/upgrade/` (same filesystem). Windows copy fallback included.
- **Post-Deploy Verification** — 5-check verification after every deployment: file existence, PHP syntax (`token_get_all`), plugin header, file readability, OPcache invalidation.
- **Automatic Rollback** — If verification fails, previous version is automatically restored from `.old` directory. Broken deployment moved to `.failed` then cleaned up.
- **Incremental Sync** — GitHub Compare API integration downloads only changed files. Handles additions, modifications, and deletions. Falls back to full archive on >100 changes or first deploy.
- **Memory-Safe Extraction** — Entry-by-entry ZIP extraction using `getStream()` + `stream_copy_to_stream()`. Prevents memory exhaustion on large plugins.
- **Concurrent Pipeline** — Backup and archive download run simultaneously via `curl_multi`. Reduces total deployment time.
- **Error Recovery** — `try/finally` wrapper on entire deployment. `register_shutdown_function()` for PHP fatal error cleanup. Daily WP-Cron cleanup of orphaned temp directories.

### 1.0.0

- Initial release
- GitHub OAuth and PAT authentication
- Automatic deployment via webhooks and polling
- Manual deployment trigger
- Configurable backups
- Security scanning (basic and advanced)
- Deployment logging
- Email and admin notifications
- Full admin dashboard

## License

GPL-2.0+ — see [LICENSE](http://www.gnu.org/licenses/gpl-2.0.txt) for details.

## Credits

Developed by [Devsroom](https://devsroom.com)
