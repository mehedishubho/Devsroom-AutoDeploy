---
phase: 01-safety-foundation
plan: 04
subsystem: admin
tags: [wp-cron, async, deployment, lock-indicator]

# Dependency graph
requires:
  - phase: 01-safety-foundation
    provides: "Deployment locking, atomic file swaps, verification, rollback — lock mechanism exists but is invisible to UI"
provides:
  - "Async manual deployments via wp_schedule_single_event so lock persists across request boundary"
  - "Lock indicator and unlock button visible in repository table during active deployments"
  - "Deploy + Activate async flow"
affects: [admin-ui, deployment-flow]

# Tech tracking
tech-stack:
  added: []
  patterns: [wp-schedule-single-event-async-pattern]

key-files:
  created: []
  modified:
    - admin/class-repository-manager.php
    - admin/partials/repository-form.php
    - includes/class-main.php

key-decisions:
  - "Used wp_schedule_single_event instead of Action Scheduler — already in WP-Cron ecosystem, no new dependency"
  - "Registered async hook in Main::define_core_hooks() instead of Repository_Manager constructor — hook must fire during WP-Cron regardless of admin page load"

patterns-established:
  - "Async deployment pattern: wp_schedule_single_event + WP-Cron callback for operations that must persist state across request boundaries"

requirements-completed: [SAFETY-01]

# Metrics
duration: 2min
completed: 2026-05-10
---

# Phase 01 Plan 04: Async Manual Deployments Summary

**Replaced synchronous deploy() with wp_schedule_single_event so the deployment lock persists across the request boundary, making lock indicator and unlock button visible in the admin UI**

## Performance

- **Duration:** 2 min
- **Started:** 2026-05-10T10:19:47Z
- **Completed:** 2026-05-10T10:21:31Z
- **Tasks:** 1
- **Files modified:** 3

## Accomplishments
- Manual deployments now schedule via wp_schedule_single_event instead of running synchronously
- Lock indicator visible in repository table during active deployment (Test 1 fix)
- Unlock button appears in Actions column when repository is locked (Test 2 fix)
- "Deployment queued" notice shown after triggering manual deploy
- Deploy + Activate flow works via async handler

## Task Commits

Each task was committed atomically:

1. **Task 1: Make manual deployments async with wp_schedule_single_event** - `7db333e` (feat)

**Plan metadata:** (pending)

## Files Created/Modified
- `admin/class-repository-manager.php` - Replaced synchronous deploy() in trigger_deployment() with wp_schedule_single_event(); added handle_async_deployment() WP-Cron callback
- `admin/partials/repository-form.php` - Added "deployment queued" notice for deploy_queued query parameter
- `includes/class-main.php` - Registered devsroom_autodeploy_async_deploy hook in define_core_hooks() so it fires during WP-Cron

## Decisions Made
- Used wp_schedule_single_event instead of Action Scheduler — already in WP-Cron ecosystem, no new dependency needed
- Registered async hook in Main::define_core_hooks() instead of Repository_Manager constructor — hook must fire during WP-Cron regardless of admin page load (Rule 3 deviation from plan's listed files)

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Added hook registration in includes/class-main.php**
- **Found during:** Task 1
- **Issue:** Plan specified only admin/class-repository-manager.php and admin/partials/repository-form.php as modified files, but the devsroom_autodeploy_async_deploy hook must be registered on every WordPress load (not just admin page renders) for WP-Cron to fire it
- **Fix:** Added Repository_Manager import and add_action() call in Main::define_core_hooks()
- **Files modified:** includes/class-main.php
- **Verification:** PHP lint passes, hook registration matches handle_async_deployment signature (10, 2)
- **Committed in:** 7db333e (Task 1 commit)

---

**Total deviations:** 1 auto-fixed (1 blocking)
**Impact on plan:** Necessary for async deployment to work — WP-Cron hooks must be registered before the cron event fires. No scope creep.

## Issues Encountered
None

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Lock indicator and unlock button now functional (Tests 1 and 2 should pass)
- Remaining UAT gap: Test 7 (automatic rollback on bad deploy) is in plan 01-05
- Webhook and polling deployments remain synchronous — unchanged behavior per plan

---
*Phase: 01-safety-foundation*
*Completed: 2026-05-10*

## Self-Check: PASSED

- [x] admin/class-repository-manager.php exists
- [x] admin/partials/repository-form.php exists
- [x] includes/class-main.php exists
- [x] 01-04-SUMMARY.md exists
- [x] Commit 7db333e exists in git log
