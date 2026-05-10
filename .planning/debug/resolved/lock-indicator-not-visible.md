---
status: diagnosed
trigger: "Diagnose why the deployment lock indicator and force-unlock button are not visible in the repository table during active deployments."
created: 2026-05-10T15:44:53+06:00
updated: 2026-05-10T15:50:00+06:00
---

## Current Focus

hypothesis: ROOT CAUSE CONFIRMED — All deployments are synchronous. The lock is acquired and released within the same HTTP request. The admin page only renders AFTER deployment completes (and lock is released), so the lock indicator/unlock button are never visible.
test: Traced full data flow from deploy() → acquire_lock() → finally{release_lock()} → redirect → page render
expecting: Lock exists only during synchronous deploy execution; never visible to admin UI
next_action: Return diagnosis

## Symptoms

expected: When a deployment starts for a repository, the repository list table shows a lock icon with "Locked" text next to the status badge. Unlock button appears in Actions column.
actual: User reported: "I didnt see anything" and "I didnt see locked button so no unlock button also"
errors: None reported
reproduction: Tests 1 and 2 in UAT — trigger a manual deployment, then observe the repository table
started: Discovered during UAT on 2026-05-10

## Eliminated

- hypothesis: "locked_at/locked_by columns missing from database"
  evidence: Schema CREATE TABLE includes locked_at (TIMESTAMP NULL DEFAULT NULL) at line 105 and locked_by (BIGINT UNSIGNED NULL DEFAULT NULL) at line 106 in database/class-schema.php. dbDelta() is called on version change via maybe_upgrade_database() (class-main.php:172-179). upgrade_schema() exists as explicit ALTER TABLE fallback (schema.php:251-265) but is dead code — never called. Columns should exist.
  timestamp: 2026-05-10T15:46:00+06:00

- hypothesis: "Template conditional is wrong — checking wrong field"
  evidence: repository-form.php line 265: `if (! empty($repo['locked_at']))` — this is correct. The SELECT * query in get_repositories() (class-repository-manager.php:471) returns all columns. The template checks the right field with the right condition.
  timestamp: 2026-05-10T15:46:30+06:00

- hypothesis: "Lock is released too quickly before page refresh"
  evidence: This is PARTIALLY correct but the real issue is stronger — the lock is released BEFORE the redirect even happens. The page isn't "refreshed too quickly" — it's never rendered while the lock exists. See root cause below.
  timestamp: 2026-05-10T15:47:00+06:00

- hypothesis: "acquire_lock() implementation is broken"
  evidence: acquire_lock() (deployment-manager.php:637-704) uses atomic UPDATE with WHERE locked_at IS NULL. It handles stale locks (10min expiry). Test 3 (Concurrent Deployment Rejection) PASSED, proving the lock mechanism works for preventing concurrent deploys. The lock IS acquired and held during deployment — just never visible to the UI.
  timestamp: 2026-05-10T15:47:30+06:00

## Evidence

- timestamp: 2026-05-10T15:45:30+06:00
  checked: admin/class-repository-manager.php trigger_deployment() method (lines 348-390)
  found: Manual deployment calls `$deployment_manager->deploy()` synchronously, then does `wp_redirect()` and `exit`. The entire deployment runs in the same HTTP request as the page redirect.
  implication: The lock cannot be visible during manual deployments because the deploy completes before the redirect.

- timestamp: 2026-05-10T15:46:00+06:00
  checked: core/class-deployment-manager.php deploy() method (lines 89-585)
  found: Lock acquired at line 180 ($this->acquire_lock()), released in finally block at line 565-567 ($this->release_lock()). The finally block ALWAYS executes before deploy() returns.
  implication: deploy() is atomic with respect to the lock — lock is guaranteed released before the method returns to caller.

- timestamp: 2026-05-10T15:46:30+06:00
  checked: public/class-webhook-handler.php handle_webhook() method (lines 48-163)
  found: Webhook-triggered deployments also call `$deployment_manager->deploy()` synchronously at line 132. The REST response is sent AFTER deploy() returns.
  implication: Even webhook/automated deployments are synchronous — lock is released before the REST response is sent.

- timestamp: 2026-05-10T15:47:00+06:00
  checked: core/class-polling-scheduler.php
  found: Polling scheduler also calls deploy() synchronously within WP-Cron.
  implication: ALL three deployment triggers (manual, webhook, polling) are synchronous.

- timestamp: 2026-05-10T15:47:30+06:00
  checked: admin/partials/repository-form.php template (lines 261-306)
  found: Lock indicator (lines 265-271) and unlock button (lines 296-306) are correctly implemented. They check `! empty($repo['locked_at'])` which is the right condition. The HTML/CSS logic is correct.
  implication: The UI code is fine — the problem is upstream (data never has locked_at set when the page renders).

- timestamp: 2026-05-10T15:48:00+06:00
  checked: admin/class-repository-manager.php render() method (lines 30-46)
  found: The page render flow: handle_force_unlock() → handle_form_submissions() → get_repositories_with_update_status() → include template. For manual deploys, handle_form_submissions() calls trigger_deployment() which calls deploy() synchronously, then redirects. The page is never rendered in the same request as an active deployment.
  implication: The render path is correct, but it's always called AFTER deployment completes.

- timestamp: 2026-05-10T15:48:30+06:00
  checked: Test 3 (Concurrent Deployment Rejection) result
  found: Test 3 PASSED — "If a deployment is already running for a plugin and you try to deploy the same plugin again, the second request fails immediately"
  implication: This confirms the lock mechanism itself works. The second request sees the lock. But no request ever shows the lock in the UI because the first request holds the lock synchronously.

## Resolution

root_cause: "All three deployment triggers (manual via trigger_deployment(), webhook via handle_webhook(), polling via Polling_Scheduler) call deploy() synchronously within the same HTTP request. The lock is acquired at the start of deploy() and released in the finally block before deploy() returns. Since the admin page (repository-form.php) is only rendered after deploy() returns — either via wp_redirect() for manual deploys or after the REST response for webhooks — the lock is ALWAYS released before the page renders. The lock indicator and unlock button template code is correct, but $repo['locked_at'] is always NULL when the page renders because no active lock exists at that point. The lock mechanism works perfectly for preventing concurrent deploys (Test 3 passed), but the UI indicators are architecturally invisible."

fix: ""
verification: ""
files_changed: []
