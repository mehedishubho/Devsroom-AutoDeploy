=== Devsroom AutoDeploy ===
Contributors: wpmhs
Author URL: https://wpmhs.com/
Plugin URI: https://plugins.devsroom.com/
Tags: deployment, github, automation, webhook, auto-update, git, continuous integration, ci/cd, atomic-deploy, rollback
Requires at least: 6.0
Tested up to: 6.9.1
Stable tag: 2.0.0
Requires PHP: 8.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.txt

Automate WordPress plugin deployments from GitHub repositories with atomic safety, incremental sync, and automatic rollback.

== Description ==

Devsroom AutoDeploy is a WordPress plugin that automates the deployment of other WordPress plugins from GitHub repositories to live WordPress websites. It eliminates the manual process of zipping plugin files and uploading them to live sites.

The deployment pipeline is built for safety and speed: atomic file swaps ensure a failed deploy never breaks a live site, post-deploy verification catches broken plugins before they go live, automatic rollback restores the previous version on failure, and incremental sync downloads only the files that changed.

= Key Features =

* **GitHub Integration**
  * Connect each plugin to a GitHub repository (public or private)
  * Authenticate securely via GitHub OAuth or Personal Access Token
  * Specify the branch to track (e.g., main, dev)
  * Automatic webhook creation on repository connection

* **Deployment Triggers**
  * Webhook deployment (instant on push)
  * Polling deployment (scheduled checks: hourly, twice daily, daily)
  * Manual deployment from admin UI ("Deploy Now" or "Deploy & Activate")

* **Atomic File Swaps**
  * Deploys to a temporary directory first, then renames into place
  * Live plugin directory is never deleted until new version is verified
  * Uses `rename()` on Linux (atomic) with copy fallback for Windows
  * If the swap fails, the old version remains intact

* **Post-Deploy Verification**
  * Main plugin file existence check
  * PHP syntax validation via `token_get_all()`
  * WordPress plugin header verification
  * Critical file readability check
  * OPcache invalidation

* **Automatic Rollback**
  * If post-deploy verification fails, previous version is automatically restored
  * No manual intervention needed
  * Broken deployment is cleaned up automatically

* **Deployment Locking**
  * Per-plugin database-based locking prevents concurrent deployments
  * Atomic SQL lock acquisition (no race conditions)
  * Stale locks auto-expire after 10 minutes
  * Admin force-unlock button on repository page

* **Incremental File Sync**
  * Uses GitHub Compare API to detect changed files
  * Only changed files are downloaded (not the full archive)
  * Handles additions, modifications, and file deletions
  * Falls back to full archive on >100 changes or first deploy

* **Memory-Safe Extraction**
  * Entry-by-entry ZIP extraction prevents memory exhaustion
  * Safe for large plugins (WooCommerce, page builders)
  * Uses `getStream()` + `stream_copy_to_stream()` instead of `extractTo()`

* **Concurrent Pipeline**
  * Backup creation and archive download run simultaneously
  * Uses `curl_multi` for HTTP overlap with local disk I/O
  * Reduces total deployment time

* **Error Recovery**
  * Entire pipeline wrapped in try/finally for guaranteed cleanup
  * Shutdown handler catches PHP fatal errors
  * Daily WP-Cron cleanup of orphaned temp directories
  * All filesystem operations check return values

* **Security Measures**
  * Admin-only access control
  * Nonce verification for all forms
  * HMAC-SHA256 webhook signature validation
  * AES-256-CBC encrypted token storage
  * Input sanitization and output escaping
  * SQL injection prevention via prepared statements
  * Self-deployment prevention
  * Configurable security scanning (basic and advanced)

* **Notifications**
  * Email notifications for deployment success, failure, and security alerts
  * Configurable notification email address

* **WordPress Admin Panel**
  * Dashboard with repository overview and recent deployments
  * Repository management with lock status and force-unlock
  * Deployment history with per-deployment logs
  * Settings for polling, backups, notifications, and authentication

== Installation ==

1. Upload the `devsroom-autodeploy` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to **AutoDeploy → Settings** to configure authentication

== Frequently Asked Questions ==

= What is Devsroom AutoDeploy? =

Devsroom AutoDeploy is a WordPress plugin that automates the deployment of other WordPress plugins from GitHub repositories to live WordPress websites. It features atomic file swaps, incremental sync, post-deploy verification, and automatic rollback.

= What are the requirements? =

* WordPress 6.0 or higher
* PHP 8.0 or higher
* `openssl` PHP extension (for token encryption)
* `zip` PHP extension (for archive extraction and backups)
* A GitHub account with access to the repositories you want to deploy

= How does automatic deployment work? =

The plugin supports two automatic deployment methods:

1. **Webhook Deployment** (Instant): When you push to the connected GitHub branch, a webhook triggers deployment automatically.

2. **Polling Deployment** (Scheduled): The plugin checks for updates on a schedule (configurable: hourly, twice daily, daily) and triggers deployment if a new commit is detected.

= What happens if a deployment fails? =

The plugin has multiple safety layers:

1. **Atomic swap** — the live plugin is never deleted until the new version is verified
2. **Post-deploy verification** — syntax, header, readability, and OPcache checks
3. **Automatic rollback** — if verification fails, the previous version is restored automatically
4. **Error recovery** — try/finally and shutdown handlers clean up temp files on any failure

= How does incremental sync work? =

When deploying, the plugin uses the GitHub Compare API to detect exactly which files changed between the last deployed commit and the new one. Only changed files are downloaded and synced. If >100 files changed or any download fails, it falls back to downloading the full archive.

= Can I use it with private repositories? =

Yes, you can connect to both public and private GitHub repositories. For private repositories, you'll need to authenticate using GitHub OAuth or a Personal Access Token with the `repo` scope.

= Does it create backups? =

Yes, you can configure the plugin to automatically create backups of the existing plugin before each deployment. You can set backup retention period and maximum backup size in the settings.

= What is deployment locking? =

Deployment locking prevents concurrent deployments to the same plugin. If a deployment is already running (e.g., from a webhook) and another request arrives (e.g., manual trigger), the second request is rejected with a message. Locks auto-expire after 10 minutes to handle crashed deployments. Admins can also force-unlock from the repository page.

= Can I trigger deployments manually? =

Yes, you can manually trigger deployments from the Repositories page. Click "Deploy Now" to deploy only, or "Deploy & Activate" to deploy and activate the plugin immediately.

= What security scanning options are available? =

* **None**: No scanning
* **Basic**: Checks for common PHP injection patterns (eval(), assert(), base64_decode(), system(), exec(), etc.)
* **Advanced**: Includes malware signatures and obfuscated code detection (suspicious variable names, variable function calls, dynamic includes)

= How do I set up GitHub OAuth? =

1. Create a GitHub OAuth App in GitHub Settings → Developer settings → OAuth Apps
2. Set Authorization callback URL: `https://yoursite.com/wp-admin/admin.php?page=devsroom-autodeploy-settings&oauth_callback=1`
3. Copy Client ID and Client Secret
4. Go to AutoDeploy → Settings → Authentication
5. Enter Client ID and Client Secret
6. Click "Connect with GitHub OAuth"

= How do I set up a Personal Access Token? =

1. Go to GitHub Settings → Developer settings → Personal access tokens → Tokens (classic)
2. Generate a new token with `repo` scope
3. Copy the token
4. Go to AutoDeploy → Settings → Authentication
5. Add the token with a descriptive name

= Can I deploy multiple plugins? =

Yes, you can connect multiple WordPress plugins to their respective GitHub repositories and manage all deployments from a single dashboard. Each plugin gets its own deployment lock, so deploying one plugin doesn't block others.

= Does it work with GitHub Enterprise? =

Currently, the plugin is designed for GitHub.com. GitHub Enterprise support may be added in future versions based on demand.

= Will it work with other Git hosting services? =

Currently, the plugin only supports GitHub. Support for other Git hosting services (GitLab, Bitbucket, etc.) may be added in future versions.

== Screenshots ==

1. Dashboard overview showing connected repositories and recent deployments
2. Repository connection form with GitHub authentication options
3. Deployment history with status indicators and detailed logs
4. Settings page with polling, backup, and notification options
5. Security scanning configuration and results

== Changelog ==

= 2.0.0 =
* Deployment Locking — Per-plugin database-based locking with stale detection and admin force-unlock
* Atomic File Swaps — Rename-based swap replacing delete-then-copy pattern
* Post-Deploy Verification — 5-check verification (syntax, header, readability, OPcache)
* Automatic Rollback — Previous version restored on verification failure
* Incremental Sync — GitHub Compare API downloads only changed files
* Memory-Safe Extraction — Entry-by-entry ZIP extraction prevents memory exhaustion
* Concurrent Pipeline — Backup and download run simultaneously via curl_multi
* Error Recovery — try/finally, shutdown handler, daily orphan cleanup cron

= 1.0.0 =
* Initial release
* GitHub OAuth and PAT authentication
* Automatic deployment via webhooks and polling
* Manual deployment trigger
* Configurable backups
* Security scanning (basic and advanced)
* Deployment logging
* Email and admin notifications
* Full admin dashboard

== Upgrade Notice ==

= 2.0.0 =
Major safety and performance upgrade. All deployments now use atomic file swaps, post-deploy verification, and automatic rollback. Incremental sync reduces bandwidth usage. Recommended for all users.

= 1.0.0 =
Initial release of Devsroom AutoDeploy. No previous version to upgrade from.
