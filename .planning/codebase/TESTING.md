# Testing Patterns

**Analysis Date:** 2026-05-10

## Current Test Coverage

**Status: No tests exist.**

The codebase contains zero test files. There are no test directories, no test configuration files, no test dependencies, and no CI/CD pipeline configuration.

Specifically absent:
- No `tests/` or `test/` directory
- No `phpunit.xml` or `phpunit.xml.dist` configuration
- No `composer.json` with PHPUnit or any test dependencies
- No `package.json` with JavaScript test runners
- No `.github/workflows/` directory for CI/CD
- No test files matching patterns `*Test.php`, `*test*.php`, `*.spec.js`, `*.test.js`
- No Pest, Codeception, or other PHP test framework configuration

## Test Framework

**Current:** None

**Recommended for this project:**

### PHP Testing: PHPUnit with WordPress test suite

Since this is a WordPress plugin, the standard approach is PHPUnit paired with the WordPress PHPUnit test framework (`wordpress/phpunit` or the older `wordpress-develop` test suite).

### JavaScript Testing: Not critical

The JavaScript in `assets/js/admin.js` is minimal (event handlers, AJAX calls, DOM manipulation). JS testing is low priority for this plugin.

## Recommended Test Setup

### Prerequisites

1. **Composer** -- Not currently in the project. Needed for PHPUnit dependency management.
2. **WordPress test environment** -- Use `wp-cli/wp-cli-tests` or a Docker-based WordPress test instance.
3. **PHP 8.0+** -- Already required by the plugin.

### Composer Configuration

Create `composer.json`:
```json
{
    "name": "devsroom/devsroom-autodeploy",
    "require-dev": {
        "phpunit/phpunit": "^10.0",
        "yoast/phpunit-polyfills": "^2.0"
    },
    "scripts": {
        "test": "phpunit"
    }
}
```

### PHPUnit Configuration

Create `phpunit.xml.dist`:
```xml
<?xml version="1.0"?>
<phpunit
    bootstrap="tests/bootstrap.php"
    colors="true"
    verbose="true"
    testdox="true"
>
    <testsuites>
        <testsuite name="Devsroom AutoDeploy Test Suite">
            <directory suffix="Test.php">tests</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

## Test Directory Structure

**Recommended:**
```
tests/
  bootstrap.php                     # WordPress test environment bootstrap
  Unit/
    Core/
      Auth_Manager_Test.php
      Backup_Manager_Test.php
      Deployment_Manager_Test.php
      GitHub_API_Test.php
      Logger_Test.php
      Notification_Test.php
      Polling_Scheduler_Test.php
      Security_Scanner_Test.php
    Database/
      Schema_Test.php
    Admin/
      Settings_Test.php
      Repository_Manager_Test.php
  Integration/
    Webhook_Handler_Test.php
    Deployment_Workflow_Test.php
```

## Test File Organization

**Naming Convention:**
- Files: `{Class_Name}_Test.php` (matching the class under test)
- Class names: `{Class_Name}_Test` extending `WP_UnitTestCase` or `PHPUnit\Framework\TestCase`
- Location: Mirror the source structure under `tests/Unit/` or `tests/Integration/`

**Example test file:**
```php
<?php
namespace Devsroom_AutoDeploy\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Devsroom_AutoDeploy\Core\Security_Scanner;

class Security_Scanner_Test extends TestCase
{
    private Security_Scanner $scanner;

    protected function setUp(): void
    {
        $this->scanner = new Security_Scanner();
    }

    public function test_scan_file_detects_eval(): void
    {
        // ...
    }
}
```

## Test Categories & Priority

### High Priority -- Unit Tests (No WordPress dependency)

These classes contain pure logic that can be tested without WordPress:

1. **`Security_Scanner`** (`core/class-security-scanner.php`)
   - Test `scan_file()` with malicious and clean PHP files
   - Test `scan_directory()` with fixture directories
   - Test each pattern category (basic, advanced, malware)
   - Test `generate_report()` output format
   - Test `find_line_number()` accuracy

2. **`GitHub_API`** (`core/class-github-api.php`)
   - Test `verify_webhook_signature()` with known payloads
   - Test `parse_webhook_payload()` with valid and invalid JSON
   - Test `get_archive_url()` URL construction
   - Mock HTTP calls for `request()` method testing

### Medium Priority -- Integration Tests (Require WordPress)

1. **`Auth_Manager`** (`core/class-auth-manager.php`)
   - Test token encryption/decryption roundtrip
   - Test `store_pat_token()` and `get_token()` database operations
   - Test `delete_token()` soft delete behavior
   - Test `verify_oauth_state()` state management
   - Test `generate_code_verifier()` and `generate_code_challenge()` PKCE flow

2. **`Logger`** (`core/class-logger.php`)
   - Test `info()`, `warning()`, `error()`, `debug()` write to database
   - Test `get_deployment_logs()` retrieval and context parsing
   - Test `cleanup_old_logs()` deletes only old entries

3. **`Backup_Manager`** (`core/class-backup-manager.php`)
   - Test `create_backup()` creates valid ZIP archive
   - Test `restore_backup()` correctly extracts files
   - Test `cleanup_expired_backups()` respects retention
   - Test `get_directory_size()` accuracy

4. **`Schema`** (`database/class-schema.php`)
   - Test `create_tables()` creates all five tables
   - Test `drop_tables()` removes all tables
   - Test schema upgrade path (version change detection)

5. **`Deployment_Manager`** (`core/class-deployment-manager.php`)
   - Test `deploy()` full workflow with mocked dependencies
   - Test self-protection (refuses to deploy to own directory)
   - Test already-up-to-date skip logic
   - Test backup-before-deploy conditional behavior
   - Test cleanup of temp directories

### Lower Priority -- Admin/UI Tests

1. **`Repository_Manager`** (`admin/class-repository-manager.php`)
   - Test `save_repository()` validation and sanitization
   - Test `delete_repository()` cleanup (database + GitHub webhooks)
   - Test `activate_plugin_by_slug()` with various plugin structures

2. **`Settings`** (`admin/class-settings.php`)
   - Test `save_settings()` option storage
   - Test OAuth callback handling flow

3. **`Webhook_Handler`** (`public/class-webhook-handler.php`)
   - Test signature verification
   - Test branch mismatch handling
   - Test push event filtering

4. **`Deployment_View`** (`admin/class-deployment-view.php`)
   - Test pagination calculations
   - Test status filtering with allowed values

## Mocking Strategy

### What to Mock

- **`wp_remote_*` HTTP calls:** Mock `GitHub_API::request()` to return fixture data instead of hitting GitHub
- **Database operations:** For unit tests, mock `$wpdb` or use in-memory SQLite
- **Filesystem operations:** Use `vfsStream` for testing file-based operations in `Backup_Manager` and `Deployment_Manager`
- **WordPress functions:** Use `Brain\Monkey` or the WordPress test framework's function mocking

### What NOT to Mock

- `Security_Scanner` pattern matching -- test the actual regex patterns
- `GitHub_API::verify_webhook_signature()` -- test actual HMAC computation
- Token encryption/decryption in `Auth_Manager` -- test actual OpenSSL operations
- Schema creation -- use actual `dbDelta()` via WordPress test framework

### Example Mock Pattern for GitHub API

```php
public function test_get_latest_commit_returns_commit_data(): void
{
    $api = $this->getMockBuilder(GitHub_API::class)
        ->setConstructorArgs(['fake-token'])
        ->onlyMethods(['request'])
        ->getMock();

    $api->expects($this->once())
        ->method('request')
        ->with('GET', '/repos/owner/repo/commits?sha=main&per_page=1')
        ->willReturn([['sha' => 'abc123', 'commit' => ['message' => 'test']]]);

    $result = $api->get_latest_commit('owner', 'repo', 'main');
    $this->assertEquals('abc123', $result['sha']);
}
```

## Fixtures and Test Data

### Recommended Fixtures Directory

```
tests/
  fixtures/
    security-samples/
      malicious-eval.php          # Contains eval() for scanner testing
      malicious-base64.php        # Contains base64_decode() for scanner testing
      clean-plugin.php            # Clean file that should pass all scans
    webhook-payloads/
      push-event.json             # Valid GitHub push event payload
      invalid.json                # Malformed JSON
    github-responses/
      commits.json                # GitHub API commits response
      repository.json             # GitHub API repository response
```

### Test Data Patterns

For database-dependent tests, use WordPress test framework's factory:
```php
$this->factory->user->create(['role' => 'administrator']);
```

For deployment tests, create temporary directories with known plugin structures.

## Run Commands

**After setup, tests would run via:**

```bash
composer install                    # Install PHPUnit
vendor/bin/phpunit                  # Run all tests
vendor/bin/phpunit tests/Unit       # Run unit tests only
vendor/bin/phpunit tests/Integration # Run integration tests only
vendor/bin/phpunit --filter=test_scan_file_detects_eval  # Run single test
```

## Coverage

**Current:** 0%

**Recommended target:**
- Core classes (`Security_Scanner`, `Auth_Manager`, `Logger`, `GitHub_API`): 80%+ line coverage
- Database schema (`Schema`): 70%+ line coverage
- Admin handlers (`Repository_Manager`, `Settings`): 60%+ line coverage
- Deployment workflow integration: Key paths covered

**View Coverage (after setup):**
```bash
vendor/bin/phpunit --coverage-text          # Terminal output
vendor/bin/phpunit --coverage-html coverage/ # HTML report
```

## Bootstrap File

The test bootstrap (`tests/bootstrap.php`) would need to:

1. Load Composer autoloader
2. Load WordPress test framework
3. Load the plugin's main file
4. Set up database tables via `Schema::create_tables()`

```php
<?php
// tests/bootstrap.php

require_once __DIR__ . '/../vendor/autoload.php';

// WordPress test framework setup (varies by environment)
// Option A: Use wp-env (Docker-based)
// Option B: Use installed WordPress test suite

require_once DEVSROOM_AUTODEPLOY_PATH . 'devsroom-autodeploy.php';
```

## CI/CD Pipeline

**Current:** None. No `.github/workflows/`, no `.gitlab-ci.yml`, no other CI configuration.

**Recommended GitHub Actions workflow:**

```yaml
# .github/workflows/tests.yml
name: Tests
on: [push, pull_request]
jobs:
  phpunit:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        php-version: ['8.0', '8.1', '8.2', '8.3']
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: root
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php-version }}
      - run: composer install
      - run: vendor/bin/phpunit
```

## Critical Test Gaps

### Untested Security Logic

- **`Security_Scanner`** (`core/class-security-scanner.php`): No regex patterns are verified to actually catch malicious code or avoid false positives. This is the most critical gap since false negatives mean malware deployment and false positives block legitimate plugins.

- **`GitHub_API::verify_webhook_signature()`** (`core/class-github-api.php`): HMAC verification has no tests. A bug here could allow unauthorized deployments.

- **`Auth_Manager` token encryption** (`core/class-auth-manager.php`): AES-256-CBC encryption has no roundtrip tests. A bug here could cause token data loss.

### Untested Deployment Workflow

- **Full deploy pipeline** (`core/class-deployment-manager.php`): The entire download-extract-scan-copy-deploy pipeline has no integration test. Any failure in this chain goes undetected until production.

### Untested Input Validation

- **`Repository_Manager::save_repository()`** (`admin/class-repository-manager.php`): Input sanitization and validation has no tests. Plugin slug validation, scan level enum checks, and auth token validation are all unverified.

- **`Settings::save_settings()`** (`admin/class-settings.php`): Settings persistence has no tests.

### Untested Edge Cases

- Concurrent deployments to the same repository
- Deployment when disk space is low
- Deployment when the GitHub API returns rate-limit errors
- Backup restore after a failed deployment
- OAuth token refresh when the refresh token has expired

---

*Testing analysis: 2026-05-10*
