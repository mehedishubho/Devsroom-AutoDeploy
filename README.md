# Devsroom AutoDeploy

Automate WordPress plugin deployments from GitHub repositories to live WordPress websites.

## Description

Devsroom AutoDeploy is a WordPress plugin that automates deployment of other WordPress plugins from GitHub repositories to live WordPress websites. The plugin eliminates the manual process of zipping plugin files and uploading them to live sites.

## Features

- **GitHub Integration**
  - Connect each plugin to a GitHub repository (public or private)
  - Authenticate securely via GitHub OAuth or Personal Access Token
  - Specify branch to track (e.g., main, dev)
  - Validate repository ownership before deployment

- **Automatic Deployment**
  - Detect new commits in connected repository automatically or via GitHub webhook
  - Download updated plugin files from GitHub
  - Update/replace existing plugin on live WordPress site
  - Optionally create backups of existing plugin before updating

- **Security Measures**
  - Only admin users can connect repositories and trigger deployments
  - Scan plugin files for malicious code (basic PHP injection checks)
  - Log all deployments with user, date, and status

- **WordPress Admin Panel**
  - Dashboard page for Devsroom AutoDeploy settings
  - Options to:
    - Add/remove GitHub repository for each plugin
    - Select deployment branch
    - Enable/disable automatic deployments
    - Trigger manual deployment
    - View deployment logs
  - Configure options:
    - Auto Deploy: Enable automatic deployment
    - Backup: Create backup before deployment
    - Security Scan Level: None, Basic, or Advanced

- **Notifications**
  - Notify admin via email or WordPress notifications after successful deployment or on errors

- **Compatibility**
  - Compatible with WordPress 6.x+
  - Lightweight and optimized for performance
  - Works alongside other plugins and themes without conflicts

## Requirements

- WordPress 6.0 or higher
- PHP 8.0 or higher
- MySQL 5.6 or higher (or MariaDB equivalent)

## Installation

1. Upload `devsroom-autodeploy` folder to `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress

## Usage

### Initial Setup

1. **Add Authentication Token**
   - Go to **AutoDeploy → Settings**
   - Add a GitHub Personal Access Token or connect via OAuth
   - Token must have `repo` scope

2. **Connect a Repository**
   - Go to **AutoDeploy → Repositories**
   - Click "Add New Repository"
   - Fill in the required fields:
     - Plugin Slug: The WordPress plugin directory name
     - Repository Owner: GitHub username or organization
     - Repository Name: GitHub repository name
     - Branch: Branch to track (default: main)
     - Authentication Token: Select a saved token
   - Configure options:
     - Auto Deploy: Enable automatic deployment
     - Backup: Create backup before deployment
     - Security Scan Level: None, Basic, or Advanced

3. **Automatic Deployment**
   The plugin supports two automatic deployment methods:

   **Webhook Deployment** (Instant)
   - When you push to the connected GitHub branch, a webhook triggers deployment
   - The plugin validates the webhook signature
   - Deployment starts automatically

   **Polling Deployment** (Scheduled)
   - The plugin checks for updates on a schedule (configurable: hourly, twice daily, daily)
   - If a new commit is detected, deployment is triggered
   - Deployment starts automatically

4. **Manual Deployment**
   - Go to **AutoDeploy → Repositories**
   - Find the repository you want to deploy
   - Click "Deploy Now" button
   - Monitor deployment progress in Deployments page

5. **Viewing Deployment History**
   - Go to **AutoDeploy → Deployments**
   - View all deployments with status, duration, and timestamps
   - Click on a deployment to view detailed logs

6. **Security Scanning**
   The plugin includes configurable security scanning:
   - **Basic Scanning**: Checks for common PHP injection patterns
   - `eval()`, `assert()`, `create_function()`
   - `base64_decode()`, `gzinflate()`, `str_rot13()`
   - `system()`, `exec()`, `shell_exec()`, etc.
   - **Advanced Scanning**: Includes malware signatures and obfuscated code detection
   - Suspicious variable names
   - Variable function calls
   - Dynamic includes

7. **Configuration Options**

   ### General Settings
   - **Polling Interval**: How often to check for updates (hourly, twice daily, daily)
   - **Backup Retention**: How long to keep backups (1-365 days)
   - **Maximum Backup Size**: Maximum size of individual backups (1-1000 MB)
   - **Enable Notifications**: Send email notifications on deployment events
   - **Notification Email**: Email address for notifications (default: admin email)
   - **Default Scan Level**: Security scan level for new repositories (none, basic, advanced)

8. **GitHub OAuth Settings**
   To use OAuth authentication:
   1. Create a GitHub OAuth App
   2. Go to GitHub Settings → Developer settings → OAuth Apps
   3. Create a new OAuth App
   4. Set Authorization callback URL: `https://yoursite.com/wp-admin/admin.php?page=devsroom-autodeploy-settings&oauth_callback=1`
   5. Copy Client ID and Client Secret
   6. Configure in Plugin
   - Go to **AutoDeploy → Settings → Authentication**
   - Enter Client ID and Client Secret
   - Click "Connect with GitHub OAuth"

9. **Personal Access Token**
   To use a Personal Access Token:
   1. Go to GitHub Settings → Developer settings → Personal access tokens → Tokens (classic)
   2. Generate a new token with `repo` scope
   3. Copy the token
   4. Go to **AutoDeploy → Settings → Authentication**
   - Add token with a descriptive name

10. **Webhook Configuration**
    When you connect a repository, the plugin automatically creates a GitHub webhook. The webhook URL format is:

```
https://yoursite.com/wp-json/devsroom-autodeploy/v1/webhook/{SECRET}
```

Where `{SECRET}` is a unique secret generated for each repository.

11. **Troubleshooting**

### Deployment Failed

- Check deployment logs for error messages
- Verify GitHub repository access
- Check authentication token is valid and has required scope
- Ensure plugin directory is writable
- Check available disk space

### Webhook Not Triggering

- Verify webhook is created in GitHub repository settings
- Check webhook URL is accessible
- Review webhook delivery logs in GitHub

### Security Scan Issues

- Review scan results in deployment logs
- Check if flagged code is legitimate
- Adjust scan level if needed (none, basic, advanced)
- Contact plugin developer if false positives occur

12. **Security**
    The plugin implements several security measures:

- Admin-only access control
- Nonce verification for all forms
- Webhook signature validation
- Encrypted token storage
- Input sanitization and output escaping
- SQL injection prevention via prepared statements
- File path validation to prevent directory traversal

13. **Support**
    For support, feature requests, or bug reports, please visit:

- GitHub: https://github.com/devsroom/devsroom-autodeploy
- Website: https://devsroom.com

14. **Changelog**

### 1.0

- Initial release
- GitHub OAuth and PAT authentication
- Automatic deployment via webhooks and polling
- Manual deployment trigger
- Configurable backups
- Security scanning (basic and advanced)
- Deployment logging
- Email and admin notifications
- Full admin dashboard
- Responsive design

15. **License**
    This plugin is licensed under GPL-2.0+ license.

16. **Credits**
    Developed by [Devsroom](https://devsroom.com)
