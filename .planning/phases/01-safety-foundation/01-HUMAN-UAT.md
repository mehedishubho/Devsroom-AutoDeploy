---
status: partial
phase: 01-safety-foundation
source: [01-VERIFICATION.md]
started: 2026-05-10T16:25:00Z
updated: 2026-05-10T16:25:00Z
---

## Current Test

[awaiting human testing]

## Tests

### 1. Concurrent Deployment Rejection
expected: Trigger two simultaneous deploys to the same repository. The second request should fail immediately with a message about deployment already in progress.
result: [pending]

### 2. Rollback on Verification Failure
expected: Deploy a plugin with a PHP syntax error in a secondary file (e.g., core/class-foo.php). Deployment should fail with "PHP syntax errors found" and automatically roll back to the previous version.
result: [pending]

### 3. Windows rename() Fallback
expected: On Windows, if rename() fails during rollback, the fallback should clean the target directory before copying from .old. No broken files should remain after rollback.
result: [pending]

### 4. Shutdown Handler on Fatal Error
expected: If a PHP fatal error occurs mid-deployment, the shutdown handler marks the deployment as failed and cleans up temp directories.
result: [pending]

### 5. Visual Lock Indicator and Unlock Button
expected: During an active deployment, the repository table shows a lock icon with "Locked" text. An "Unlock" button appears in the Actions column.
result: [pending]

### 6. Async Manual Deployment Flow
expected: Clicking "Deploy Now" redirects immediately with a "Deployment queued" notice. The deployment runs in the background via WP-Cron.
result: [pending]

### 7. Valid Plugin Deployment
expected: Deploying a valid plugin (no syntax errors) succeeds without false positives from the recursive syntax scanning.
result: [pending]

### 8. Deploy + Activate Async Flow
expected: Clicking "Deploy + Activate" queues the deployment and activation. After deployment completes, the plugin is automatically activated.
result: [pending]

## Summary

total: 8
passed: 0
issues: 0
pending: 8
skipped: 0
blocked: 0

## Gaps

[none yet]
