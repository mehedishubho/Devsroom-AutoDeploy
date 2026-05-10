---
status: diagnosed
phase: 01-safety-foundation
source: [01-01-SUMMARY.md, 01-02-SUMMARY.md, 01-03-SUMMARY.md]
started: 2026-05-10T14:57:01Z
updated: 2026-05-10T15:43:59Z
---

## Current Test

number: -
name: -
expected: -
awaiting: -

## Tests

### 1. Deployment Lock Indicator
expected: When a deployment starts for a repository, the repository list table shows a lock icon with "Locked" text next to the status badge. The lock indicator includes a tooltip showing when the lock was acquired.
result: issue
reported: "I didnt see anything"
severity: major

### 2. Force Unlock Button
expected: When a repository is locked, an "Unlock" button with an unlock icon appears in the Actions column. Clicking it shows a confirmation dialog, then clears the lock and shows a success notice "Deployment lock cleared."
result: issue
reported: "I didnt see locked button so no unlock button also"
severity: major

### 3. Concurrent Deployment Rejection
expected: If a deployment is already running for a plugin and you try to deploy the same plugin again, the second request fails immediately with a message like "Deployment already in progress for this plugin."
result: pass

### 4. Stale Lock Auto-Expiry
expected: If a deployment lock has been held for more than 10 minutes (e.g., from a server crash), the next deployment attempt detects the stale lock and proceeds normally instead of being blocked.
result: skipped
reason: User hasn't tested this scenario yet

### 5. Successful Deployment with Atomic Swap
expected: Deploying a plugin completes successfully. During deployment, plugin files are never missing — the old version stays in place until the new version is fully ready, then they swap atomically.
result: pass

### 6. Post-Deploy Verification
expected: After a successful deployment, the deployment logs (visible in Deployment Details page) show verification steps: file existence check, PHP syntax check, plugin header validation, readability check, and OPcache invalidation.
result: pass

### 7. Automatic Rollback on Bad Deploy
expected: If a deployment results in a plugin with PHP syntax errors, the verification step catches it, the deployment is automatically rolled back to the previous version, the status shows "Failed", and the error message explains the verification failure.
result: issue
reported: "no"
severity: major

### 8. Error Recovery Cleanup
expected: If a deployment fails partway through (exception or error), temporary files in WP_CONTENT_DIR/upgrade/ are cleaned up. No orphaned devsroom-autodeploy-* directories remain after the failure.
result: skipped
reason: User doesn't understand how to verify this backend behavior

### 9. WP-Cron Orphan Cleanup
expected: A daily WP-Cron event exists that automatically cleans up old temp folders from the plugin. The event is scheduled when you activate the plugin and removed when you deactivate it.
result: skipped
reason: User hasn't tested this scenario yet

## Summary

total: 9
passed: 3
issues: 3
pending: 0
skipped: 3
blocked: 0

## Gaps

- truth: "Lock indicator visible in repository table during active deployment"
  status: failed
  reason: "User reported: I didnt see anything"
  severity: major
  test: 1
  root_cause: "deploy() runs synchronously — lock is acquired and released within the same HTTP request before the page renders. $repo['locked_at'] is always NULL at template render time."
  artifacts:
    - path: "admin/class-repository-manager.php"
      issue: "trigger_deployment() calls deploy() synchronously then redirects — page never renders while lock held"
    - path: "core/class-deployment-manager.php"
      issue: "deploy() acquires lock at line 180, releases in finally block at line 565-567 — lock lifecycle is entirely within one request"
  missing:
    - "Make deployments asynchronous (wp_schedule_single_event or Action Scheduler) so lock persists across request boundary"
  debug_session: ".planning/debug/lock-indicator-not-visible.md"

- truth: "Unlock button appears in Actions column when repository is locked"
  status: failed
  reason: "User reported: I didnt see locked button so no unlock button also"
  severity: major
  test: 2
  root_cause: "Same as Test 1 — deploy() is synchronous, lock released before page renders, so locked_at is always NULL and unlock button conditional never triggers"
  artifacts:
    - path: "admin/class-repository-manager.php"
      issue: "Same synchronous deploy() root cause"
    - path: "admin/partials/repository-form.php"
      issue: "Template conditional (!empty($repo['locked_at'])) is correct but data is always NULL at render time"
  missing:
    - "Same fix as Test 1 — async deployment"
  debug_session: ".planning/debug/lock-indicator-not-visible.md"

- truth: "Deployment with PHP syntax errors auto-rolls back to previous version"
  status: failed
  reason: "User reported: no"
  severity: major
  test: 7
  root_cause: "verify_deployment() only checks the main plugin file ({slug}.php) for PHP syntax errors. Syntax errors in secondary files (core/class-*.php, includes/*.php) pass verification silently — deployment marked success, no rollback triggered."
  artifacts:
    - path: "core/class-deployment-manager.php"
      issue: "verify_deployment() at line 1380 only checks one file via token_get_all(). Subdirectories never scanned."
  missing:
    - "Modify verify_deployment() to recursively scan ALL PHP files using RecursiveIteratorIterator"
    - "In Windows rollback fallback, add cleanup_dir($plugin_path) before copy_directory() for clean restore"
  debug_session: ".planning/debug/rollback-not-working.md"
