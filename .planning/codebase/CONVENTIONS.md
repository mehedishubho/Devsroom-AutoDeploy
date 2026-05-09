# Coding Conventions

**Analysis Date:** 2026-05-10

## Language & Runtime

**Primary:** PHP 8.0+ (minimum required, per plugin header)
**Secondary:** JavaScript (jQuery, admin-side only), CSS (custom admin styles)

## Naming Patterns

### Files

- **Class files:** `class-{name}.php` using lowercase with hyphens
  - Example: `class-auth-manager.php`, `class-deployment-manager.php`
  - Autoloader in `includes/class-loader.php` maps `Devsroom_AutoDeploy\Core\Auth_Manager` to `core/class-auth-manager.php` (lowercases namespace, replaces underscores with hyphens)
- **Template/partials:** `{name}.php` lowercase with hyphens
  - Example: `dashboard.php`, `repository-form.php`, `deployment-list.php`
- **Entry point:** `devsroom-autodeploy.php` (matches plugin slug)
- **Assets:** `admin.css`, `admin.js` (plain names in `assets/css/` and `assets/js/`)

### Classes

- **Format:** `Snake_Case` with underscores (WordPress convention, NOT PSR StudlyCase)
  - Example: `Auth_Manager`, `Deployment_Manager`, `Backup_Manager`
- **Namespace:** `Devsroom_AutoDeploy\{SubNamespace}`
  - Sub-namespaces: `Core`, `Admin`, `Public`, `Database`, root for includes
  - Example: `Devsroom_AutoDeploy\Core\GitHub_API`, `Devsroom_AutoDeploy\Admin\Settings`
- **File-to-class mapping:** `class-auth-manager.php` contains `Auth_Manager` (autoloader strips prefix `class-`, replaces hyphens with underscores)

### Methods

- **Format:** `snake_case` (WordPress convention)
  - Example: `get_instance()`, `store_pat_token()`, `handle_form_submissions()`
- **Visibility keyword:** Always declared explicitly (`public`, `private`, `protected`)
- **Return types:** PHP 8.0 union types and nullable types used
  - Example: `int|false`, `array|false`, `string|false`, `?Loader`
  - Void return: `: void` on all methods returning nothing
- **Static methods:** Used for factory/singleton pattern (`get_instance()`) and utility methods (`activate()`, `deactivate()`, `create_tables()`)

### Variables & Properties

- **Format:** `snake_case` (WordPress convention)
  - Example: `$plugin_name`, `$commit_hash`, `$webhook_secret`
- **Typed properties:** PHP 8.0 typed properties used consistently
  - Example: `protected string $plugin_name;`, `private static ?Auth_Manager $instance = null;`
- **Default values:** Always provided for class properties
  - Example: `private string $api_url = 'https://api.github.com';`
- **Constants:** `UPPER_SNAKE_CASE` with `define()` in main plugin file
  - Example: `DEVSROOM_AUTODEPLOY_VERSION`, `DEVSROOM_AUTODEPLOY_PATH`, `DEVSROOM_AUTODEPLOY_URL`

### Database Tables

- **Prefix:** `{$wpdb->prefix}devsroom_{table_name}` (using WordPress table prefix + plugin prefix)
- **Names:** Plural snake_case
  - Example: `devsroom_repositories`, `devsroom_auth_tokens`, `devsroom_deployments`, `devsroom_logs`, `devsroom_backups`
- **Options:** `devsroom_autodeploy_{option_name}` prefix for `wp_options` entries
  - Example: `devsroom_autodeploy_polling_interval`, `devsroom_autodeploy_enable_notifications`

## Code Style

### PHP Formatting

- **Braces:** Opening brace on next line for classes and methods (WordPress convention)
  ```php
  class Auth_Manager
  {
      public function get_token(int $token_id): array|false
      {
          // ...
      }
  }
  ```
- **Control structures:** Opening brace on same line
  ```php
  if (! $repository) {
      return false;
  }
  ```
- **Spacing:** Space after `!` in negation: `! $variable` (WordPress style)
- **Arrays:** Use `array()` syntax, NOT `[]` for array literals (WordPress convention)
  ```php
  $data = array(
      'status' => 'active',
      'name'   => 'example',
  );
  ```
- **String interpolation:** Double-quoted strings with `{$var}` or concatenation with `.`
  ```php
  "token {$this->token}"
  $table_name = $wpdb->prefix . 'devsroom_repositories';
  ```

### WordPress Coding Standards Compliance

- Uses `array()` not `[]` for arrays
- Uses `! $var` spacing (space after negation)
- Uses proper spacing in `$wpdb->prepare()` calls
- Uses WordPress i18n functions throughout: `__()`, `esc_html__()`, `esc_attr__()`, `esc_html_e()`
- Text domain: `devsroom-autodeploy`
- Uses `wp_json_encode()` instead of `json_encode()`
- Uses WordPress HTTP API (`wp_remote_get`, `wp_remote_post`, `wp_remote_request`) instead of cURL directly

### JavaScript Style

- **Pattern:** IIFE with jQuery, `'use strict'`
  ```javascript
  (function ($) {
      'use strict';
      $(document).ready(function () {
          // ...
      });
  })(jQuery);
  ```
- **Variable naming:** `camelCase` for JS variables
- **Localization:** Uses `wp_localize_script()` to pass data from PHP
  - Object name: `devsroom_autodeploy`
  - Contains: `ajax_url`, `nonce`, `strings`

### CSS Style

- **Naming:** BEM-like with `devsroom-` prefix namespace
  - `.devsroom-autodeploy` (root wrapper)
  - `.devsroom-section`, `.devsroom-panel`, `.devsroom-toolbar`
  - `.devsroom-autodeploy-stats-grid`, `.devsroom-autodeploy-stat-card`
- **CSS Custom Properties:** Uses `--ds-` prefixed variables for design tokens
  - Example: `--ds-primary`, `--ds-space-4`, `--ds-radius`
- **Responsive:** Media queries at 1200px and 782px breakpoints

## Class Design Patterns

### Singleton Pattern (Core Classes)

All core service classes use a singleton pattern with the identical structure:

```php
private static ?ClassName $instance = null;

public static function get_instance(): ClassName
{
    if (null === self::$instance) {
        self::$instance = new self();
    }
    return self::$instance;
}

private function __construct()
{
    // ...
}
```

Used in: `Loader`, `Auth_Manager`, `Deployment_Manager`, `Backup_Manager`, `Logger`, `Notification`, `Polling_Scheduler`

### New Instance (Non-Singleton)

- `GitHub_API`: Created with `new GitHub_API($token)` -- stateful, holds a token
- `Security_Scanner`: Created with `new Security_Scanner()` -- stateless scanner
- `Admin`, `Dashboard`, `Repository_Manager`, `Deployment_View`, `Settings`: Created per request

### Static Utility Pattern

- `Activator::activate()` and `Deactivator::deactivate()`: Static methods called by WordPress hooks
- `Schema::create_tables()` and `Schema::drop_tables()`: Static entry points, internally instantiate self
- `GitHub_API::verify_webhook_signature()` and `GitHub_API::parse_webhook_payload()`: Pure static utility methods

## Error Handling

### Database Operations

All `$wpdb` operations use prepared statements and format specifiers:

```php
$wpdb->prepare(
    "SELECT * FROM $table_name WHERE id = %d AND is_active = 1",
    $token_id
)
```

Insert/update operations pass explicit format arrays:
```php
$wpdb->insert(
    $table_name,
    array('user_id' => $user_id, 'auth_method' => 'pat'),
    array('%d', '%s')
);
```

### API Error Handling

- `is_wp_error($response)` checks on all `wp_remote_*` calls
- `GitHub_API::request()` returns `false` on all error conditions
- Uses `error_log()` for API failures in `GitHub_API` class
- Structured return arrays with `success` and `message` keys for deployment results

### Return Type Pattern

Methods that can fail consistently return `false` or a result:
- `array|false` -- for lookups that find data or fail
- `int|false` -- for insert operations returning ID or failure
- `bool` -- for operations that succeed or fail
- `string|false` -- for URL/path generation that can fail

## Security Patterns

### Nonce Verification

All form submissions verify nonces before processing:

```php
if (! isset($_POST['devsroom_autodeploy_nonce']) ||
    ! wp_verify_nonce($_POST['devsroom_autodeploy_nonce'], 'devsroom_autodeploy_save_repository')) {
    return;
}
```

AJAX handlers use `check_ajax_referer()`:
```php
check_ajax_referer('devsroom_autodeploy_nonce', 'nonce');
```

### Capability Checks

All admin operations check `current_user_can('manage_options')`.

### Input Sanitization

- Text fields: `sanitize_text_field($_POST['field'] ?? '')`
- Email fields: `sanitize_email($_POST['field'] ?? '')`
- Integer casting: `(int) ($_POST['field'] ?? 0)`
- Checkbox: `isset($_POST['field']) ? 1 : 0`
- Enum validation: `in_array($value, array('allowed', 'values'), true)`
- Error URL params: `sanitize_key(wp_unslash($_GET['error']))`

### Output Escaping

All template output uses appropriate escaping functions:
- `esc_html()` for text content
- `esc_attr()` for HTML attributes
- `esc_url()` for URLs
- `wp_kses_post()` for HTML content in admin notices
- `esc_html__()` for translated strings

### Token Encryption

GitHub tokens are encrypted before storage using AES-256-CBC:
```php
openssl_encrypt($token, 'AES-256-CBC', $key, 0, $iv)
```
Encryption key stored in `devsroom_autodeploy_encryption_key` option.

### Webhook Signature Verification

HMAC-SHA256 signature verification using `hash_equals()` for timing-safe comparison:
```php
hash_equals($expected_hash, $hash)
```

### Self-Protection

Deployment manager prevents deploying to its own plugin directory:
```php
if ($plugin_path_real === $autodeploy_path_real) {
    // Block deployment
}
```

## Logging

### Framework

Custom `Logger` class at `core/class-logger.php` -- singleton, writes to database table `devsroom_logs`.

### Log Levels

Four levels: `info`, `warning`, `error`, `debug`

### Usage Pattern

```php
$this->logger->info($deployment_id, 'Deployment started', array(
    'trigger_type' => $trigger_type,
    'commit_hash'  => $commit_hash,
));
```

- First argument: deployment ID (int)
- Second argument: message (string)
- Third argument: context (associative array, stored as JSON)

### Fallback Logging

`GitHub_API` uses `error_log()` for API failures since the custom logger requires a deployment ID context.

## Import Organization

### PHP Use Statements

Placed after namespace declaration, grouped by sub-namespace:

```php
namespace Devsroom_AutoDeploy\Admin;

use Devsroom_AutoDeploy\Core\Auth_Manager;
use Devsroom_AutoDeploy\Core\Deployment_Manager;
use Devsroom_AutoDeploy\Core\GitHub_API;
```

### Require Statements

Only in `includes/class-main.php` `load_dependencies()` method. Uses `require_once` with `DEVSROOM_AUTODEPLOY_PATH` constant. The autoloader handles most class loading, but `load_dependencies()` ensures all files are loaded for hook registration.

## Comment/Documentation Standards

### File Headers

Every PHP file has a DocBlock with package annotation:
```php
/**
 * GitHub API Client.
 *
 * @package Devsroom_AutoDeploy
 */
```

### Class DocBlocks

Every class has a DocBlock with description and `@since` tag:
```php
/**
 * Class GitHub_API
 *
 * Handles communication with GitHub REST API.
 *
 * @since 1.0.0
 */
```

### Method DocBlocks

Every method has a DocBlock with:
- Description
- `@param` tags with types and descriptions
- `@return` tag with type and description

```php
/**
 * Get a token by ID.
 *
 * @param int $token_id Token ID.
 * @return array|false Token data or false on failure.
 */
```

### Property DocBlocks

Every class property has a DocBlock with `@var` type:
```php
/**
 * GitHub API base URL.
 *
 * @var string
 */
private string $api_url = 'https://api.github.com';
```

## Template Conventions

### Direct Access Guard

All partial templates start with:
```php
if (! defined('ABSPATH')) {
    exit;
}
```

### Variable Passing

Templates receive variables from parent class `render()` methods via `include` (variable scope sharing). No explicit variable passing mechanism -- relies on PHP include scope.

### Status Mapping

Status-to-CSS-class mapping arrays are defined inline in templates:
```php
$status_classes = array(
    'success' => 'success',
    'failed'  => 'error',
    'pending' => 'warning',
    'scanning' => 'info',
    'backing_up' => 'info',
);
```
This pattern appears identically in `dashboard.php`, `deployment-list.php`, and `deployment-single.php`.

## Module Design

### Exports

Classes export via namespace -- no barrel files or explicit export patterns. The autoloader handles class discovery.

### Hook Registration

Hooks are registered through the `Loader` class:
```php
$this->loader->add_action('admin_menu', $plugin_admin, 'add_plugin_menu');
```

For REST routes and cron events, hooks are registered directly via WordPress functions (`add_action`, `register_rest_route`).

### Redirect-After-POST Pattern

All form submissions follow WordPress redirect-after-POST pattern:
```php
wp_redirect(admin_url('admin.php?page=devsroom-autodeploy-repositories&saved=true'));
exit;
```
Status messages passed via URL query parameters, displayed on page load.

---

*Convention analysis: 2026-05-10*
