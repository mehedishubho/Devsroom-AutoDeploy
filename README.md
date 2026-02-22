# Devsoom AutoDeploy

Automate WordPress plugin deployments from GitHub repositories to live WordPress websites.

## Description

Devsoom AutoDeploy is a WordPress plugin that automates the deployment of other WordPress plugins from GitHub repositories to live WordPress websites. The plugin eliminates the manual process of zipping plugin files and uploading them to live sites.

## Features

- **GitHub Integration**
  - Connect each plugin to a GitHub repository (public or private)
  - Authenticate securely via GitHub OAuth or Personal Access Token
  - Specify the branch to track (e.g., main, dev)
  - Validate repository ownership before deployment

- **Automatic Deployment**
  - Detect new commits in the connected repository automatically or via GitHub webhook
  - Download updated plugin files from GitHub
  - Update/replace the existing plugin on the live WordPress site
  - Optionally create backups of the existing plugin before updating

- **Security Measures**
  - Only admin users can connect repositories and trigger deployments
  - Scan plugin files for malicious code (basic PHP injection checks)
  - Log all deployments with user, date, and status

- **WordPress Admin Panel**
  - Dashboard page for Devsoom AutoDeploy settings
  - Options to:
    - Add/remove GitHub repository for each plugin
    - Select deployment branch
    - Enable/disable automatic deployments
    - Trigger manual deployment
    - View deployment logs

- **Notifications**
  - Notify admin via email or WordPress notifications after successful deployment or on errors

- **Compatibility**
  - Compatible with WordPress 6.x+
  - Lightweight and optimized for performance
  - Works alongside other plugins and themes without conflicts

## Requirements

- WordPress 6.0 or higher
- PHP 8.0 or higher

## Installation

1. Upload the `devsoom-autodeploy` folder to the `/wp-content/plugins/` directory
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

### Automatic Deployment

The plugin supports two automatic deployment methods:

1. **Webhook Deployment** (Instant)
   - When you push to the connected GitHub branch, a webhook triggers deployment
   - The plugin validates the webhook signature
   - Deployment starts automatically

2. **Polling Deployment** (Scheduled)
   - The plugin checks for updates on a schedule (configurable: hourly, twice daily, daily)
   - If a new commit is detected, deployment is triggered

### Manual Deployment

1. Go to **AutoDeploy → Repositories**
2. Find the repository you want to deploy
3. Click the "Deploy Now" button
4. Monitor the deployment progress in the Deployments page

### Viewing Deployment History

1. Go to **AutoDeploy → Deployments**
2. View all deployments with status, duration, and timestamps
3. Click on a deployment to view detailed logs

### Security Scanning

The plugin includes configurable security scanning:

- **Basic Scanning**: Checks for common PHP injection patterns
  - `eval()`, `assert()`, `create_function()`
  - `base64_decode()`, `gzinflate()`, `str_rot13()`
  - `system()`, `exec()`, `shell_exec()`, etc.

- **Advanced Scanning**: Includes malware signatures and obfuscated code detection
  - Suspicious variable names
  - Variable function calls
  - Dynamic includes

## Configuration Options

### General Settings

- **Polling Interval**: How often to check for updates (hourly, twice daily, daily)
- **Backup Retention**: How long to keep backups (1-365 days)
- **Maximum Backup Size**: Maximum size of individual backups (1-1000 MB)
- **Enable Notifications**: Send email notifications on deployment events
- **Notification Email**: Email address for notifications (default: admin email)
- **Default Scan Level**: Security scan level for new repositories (none, basic, advanced)

### GitHub OAuth Settings

To use OAuth authentication:

1. Create a GitHub OAuth App
   - Go to GitHub Settings → Developer settings → OAuth Apps
   - Create a new OAuth App
   - Set Authorization callback URL: `https://yoursite.com/wp-admin/admin.php?page=devsoom-autodeploy-settings&oauth_callback=1`
   - Copy Client ID and Client Secret

2. Configure in Plugin
   - Go to **AutoDeploy → Settings → Authentication**
   - Enter Client ID and Client Secret
   - Click "Connect with GitHub OAuth"

### Personal Access Token

To use a Personal Access Token:

1. Go to GitHub Settings → Developer settings → Personal access tokens → Tokens (classic)
2. Generate a new token with `repo` scope
3. Copy the token
4. Go to **AutoDeploy → Settings → Authentication**
5. Add the token with a descriptive name

## Webhook Configuration

When you connect a repository, the plugin automatically creates a GitHub webhook. The webhook URL format is:

```
https://yoursite.com/wp-json/devsoom-autodeploy/v1/webhook/{SECRET}
```

Where `{SECRET}` is a unique secret generated for each repository.

## Troubleshooting

### Deployment Failed

1. Check deployment logs for error messages
2. Verify GitHub repository access
3. Check authentication token is valid and has required scope
4. Ensure plugin directory is writable
5. Check available disk space

### Webhook Not Triggering

1. Verify webhook is created in GitHub repository settings
2. Check webhook URL is accessible
3. Review webhook delivery logs in GitHub
4. Ensure WordPress REST API is enabled

### Security Scan Issues

1. Review scan results in deployment logs
2. Check if flagged code is legitimate
3. Adjust scan level if needed (none, basic, advanced)
4. Contact plugin developer if false positives occur

## Security

The plugin implements several security measures:

- Admin-only access control
- Nonce verification for all forms
- Webhook signature validation
- Encrypted token storage
- Input sanitization and output escaping
- SQL injection prevention via prepared statements
- File path validation to prevent directory traversal

## Support

For support, feature requests, or bug reports, please visit:

- GitHub: https://github.com/devsoom/devsoom-autodeploy
- Website: https://devsoom.com

## Changelog

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

This plugin is licensed under the GPL-2.0+ license.

## Credits

Developed by [Devsoom](https://devsoom.com)
