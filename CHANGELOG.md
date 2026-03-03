# Changelog

All notable changes to the Devsroom AutoDeploy plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Planned

- GitLab and Bitbucket support
- Deployment rollback functionality
- Multi-environment support (staging, production)
- Slack and Discord notifications
- Deployment analytics and statistics
- Custom deployment scripts support
- Plugin dependency management

## [1.0.0] - 2026-02-23

### Added

- **GitHub Integration**
  - Connect WordPress plugins to GitHub repositories (public and private)
  - GitHub OAuth authentication support
  - Personal Access Token (PAT) authentication support
  - Repository ownership validation
  - Branch selection for deployment tracking

- **Automatic Deployment**
  - Webhook-based instant deployment on push events
  - Scheduled polling for updates (hourly, twice daily, daily)
  - Automatic download and replacement of plugin files
  - Configurable automatic deployment per repository

- **Manual Deployment**
  - One-click manual deployment trigger
  - Real-time deployment progress tracking
  - Deployment status indicators

- **Backup System**
  - Automatic backup creation before deployment
  - Configurable backup retention period (1-365 days)
  - Maximum backup size limits (1-1000 MB)
  - Backup restoration capability

- **Security Features**
  - Admin-only access control
  - Nonce verification for all form submissions
  - Webhook signature validation
  - Encrypted token storage
  - Input sanitization and output escaping
  - SQL injection prevention via prepared statements
  - File path validation to prevent directory traversal
  - Security scanning for malicious code detection
    - Basic scanning: PHP injection patterns (eval, assert, base64_decode, etc.)
    - Advanced scanning: Malware signatures and obfuscated code detection

- **Deployment Management**
  - Complete deployment history and logs
  - Detailed deployment information (commit hash, branch, duration)
  - Deployment status tracking (pending, running, success, failed)
  - Error logging with detailed messages

- **Notification System**
  - Email notifications on deployment events
  - WordPress admin notifications
  - Configurable notification email addresses
  - Success and failure notifications

- **Admin Dashboard**
  - Overview dashboard with statistics
  - Repository management interface
  - Deployment history view
  - Settings page with comprehensive options
  - Responsive design for mobile and desktop

- **GitHub API Integration**
  - Repository information retrieval
  - Commit history fetching
  - File download from repositories
  - Webhook creation and management
  - Branch listing and validation

- **Database Schema**
  - Custom database tables for repositories
  - Deployment logs storage
  - Authentication tokens management
  - Backup records tracking

- **Polling Scheduler**
  - WordPress cron-based polling system
  - Configurable polling intervals
  - Automatic update detection

- **Logger System**
  - Comprehensive logging for all operations
  - Log level management (info, warning, error)
  - Log rotation and cleanup

- **Settings Management**
  - General settings (polling interval, backup options)
  - Authentication settings (OAuth, PAT)
  - Notification preferences
  - Default security scan level configuration

- **Webhook Handler**
  - REST API endpoint for webhook processing
  - Signature verification
  - Automatic deployment triggering

- **Localization Ready**
  - Text domain for translations
  - Language files directory structure

### Security

- All admin actions require `manage_options` capability
- CSRF protection via WordPress nonces
- Webhook secret validation
- Encrypted storage of authentication tokens
- File system permissions validation
- SQL injection prevention via prepared statements
- XSS protection via output escaping

### Requirements

- WordPress 6.0 or higher
- PHP 8.0 or higher
- GitHub account for repository access
- `repo` scope for GitHub Personal Access Tokens

### Documentation

- Comprehensive README.md
- Inline code documentation
- Usage instructions
- Troubleshooting guide
- FAQ section

---

## Version Format

This changelog follows the [Keep a Changelog](https://keepachangelog.com/en/1.0.0/) format:

- **Added** - New features
- **Changed** - Changes in existing functionality
- **Deprecated** - Soon-to-be removed features
- **Removed** - Removed features
- **Fixed** - Bug fixes
- **Security** - Security vulnerabilities or improvements

## Links

- [Plugin Repository](https://github.com/devsroom/devsroom-autodeploy)
- [Official Website](https://devsroom.com)
- [Support](https://devsroom.com/support)
- [Documentation](https://devsroom.com/docs/autodeploy)
