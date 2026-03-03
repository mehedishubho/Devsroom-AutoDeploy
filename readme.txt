=== Devsroom AutoDeploy ===
Contributors: wpmhs
Author URL: https://wpmhs.com/
Plugin URI: https://plugins.devsroom.com/
Tags: deployment, github, automation, webhook, auto-update, git, continuous integration, ci/cd
Requires at least: 6.0
Tested up to: 6.9.1
Stable tag: 1.0.0
Requires PHP: 8.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.txt

Automate WordPress plugin deployments from GitHub repositories to live WordPress websites.

== Description ==

Devsroom AutoDeploy is a WordPress plugin that automates the deployment of other WordPress plugins from GitHub repositories to live WordPress websites. The plugin eliminates the manual process of zipping plugin files and uploading them to live sites.

= Key Features =

* **GitHub Integration**
  * Connect each plugin to a GitHub repository (public or private)
  * Authenticate securely via GitHub OAuth or Personal Access Token
  * Specify the branch to track (e.g., main, dev)
  * Validate repository ownership before deployment

* **Automatic Deployment**
  * Detect new commits in the connected repository automatically or via GitHub webhook
  * Download updated plugin files from GitHub
  * Update/replace the existing plugin on the live WordPress site
  * Optionally create backups of the existing plugin before updating

* **Security Measures**
  * Only admin users can connect repositories and trigger deployments
  * Scan plugin files for malicious code (basic PHP injection checks)
  * Log all deployments with user, date, and status

* **WordPress Admin Panel**
  * Dashboard page for Devsroom AutoDeploy settings
  * Options to:
    * Add/remove GitHub repository for each plugin
    * Select deployment branch
    * Enable/disable automatic deployments
    * Trigger manual deployment
    * View deployment logs

* **Notifications**
  * Notify admin via email or WordPress notifications after successful deployment or on errors

* **Compatibility**
  * Compatible with WordPress 6.x+
  * Lightweight and optimized for performance
  * Works alongside other plugins and themes without conflicts

== Installation ==

1. Upload the `devsroom-autodeploy` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress

== Frequently Asked Questions ==

= What is Devsroom AutoDeploy? =

Devsroom AutoDeploy is a WordPress plugin that automates the deployment of other WordPress plugins from GitHub repositories to live WordPress websites. It eliminates the manual process of zipping plugin files and uploading them to live sites.

= What are the requirements? =

* WordPress 6.0 or higher
* PHP 8.0 or higher
* A GitHub account with access to the repositories you want to deploy

= How does automatic deployment work? =

The plugin supports two automatic deployment methods:

1. **Webhook Deployment** (Instant): When you push to the connected GitHub branch, a webhook triggers deployment automatically.

2. **Polling Deployment** (Scheduled): The plugin checks for updates on a schedule (configurable: hourly, twice daily, daily) and triggers deployment if a new commit is detected.

= Is it secure? =

Yes, the plugin implements several security measures:

* Admin-only access control
* Nonce verification for all forms
* Webhook signature validation
* Encrypted token storage
* Input sanitization and output escaping
* SQL injection prevention via prepared statements
* File path validation to prevent directory traversal
* Optional security scanning for malicious code

= Can I use it with private repositories? =

Yes, you can connect to both public and private GitHub repositories. For private repositories, you'll need to authenticate using GitHub OAuth or a Personal Access Token with the `repo` scope.

= Does it create backups? =

Yes, you can configure the plugin to automatically create backups of the existing plugin before each deployment. You can set backup retention period and maximum backup size in the settings.

= What happens if a deployment fails? =

If a deployment fails, the plugin will:
* Log the error with details
* Send a notification email (if enabled)
* Keep the previous version intact (if backup was created)
* Display the error in the deployment logs

= Can I trigger deployments manually? =

Yes, you can manually trigger deployments from the Repositories page. Simply click the "Deploy Now" button next to any connected repository.

= What security scanning options are available? =

The plugin includes configurable security scanning:

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

Yes, you can connect multiple WordPress plugins to their respective GitHub repositories and manage all deployments from a single dashboard.

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
= 1.0.0 =
Initial release of Devsroom AutoDeploy. No previous version to upgrade from.
