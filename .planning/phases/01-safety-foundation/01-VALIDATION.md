---
phase: 01-safety-foundation
plans: [04, 05]
status: pending
created: 2026-05-10T16:07:01+06:00
---

# Phase 1 Validation: Test Scenarios

**Purpose:** Maps test scenarios from RESEARCH.md Validation Architecture to automated verify commands for Plans 04 and 05 (gap closure plans).

## Plan 04: Async Deployment & Lock Visibility

**Requirements:** SAFETY-01 (lock indicator visibility)

### Scenario 1: Lock indicator visible during active deployment

**Automated verify:**
```bash
# Verify wp_schedule_single_event is used in trigger_deployment()
grep -n "wp_schedule_single_event" admin/class-repository-manager.php
# Verify handle_async_deployment method exists
grep -n "handle_async_deployment" admin/class-repository-manager.php
# Verify devsroom_autodeploy_async_deploy hook registered
grep -n "devsroom_autodeploy_async_deploy" admin/class-repository-manager.php
```

**Manual verify:**
1. Trigger manual "Deploy Now" from admin UI
2. After redirect, repository table shows lock icon with "Locked" text
3. Refresh page while deployment running — lock indicator still visible
4. After deployment completes — lock indicator disappears

### Scenario 2: Unlock button appears for locked repositories

**Automated verify:**
```bash
# Verify lock indicator conditional in template uses locked_at
grep -n "locked_at" admin/partials/repository-form.php
# Verify unlock button exists in template
grep -n "Unlock\|force_unlock" admin/partials/repository-form.php
```

**Manual verify:**
1. Lock indicator visible → unlock button visible in Actions column
2. Click unlock → lock cleared, success notice shown

### Scenario 3: Deploy + Activate async flow

**Automated verify:**
```bash
# Verify activate_after parameter in handle_async_deployment
grep -n "activate_after" admin/class-repository-manager.php
# Verify activate_plugin_by_slug called in async handler
grep -n "activate_plugin_by_slug" admin/class-repository-manager.php
```

**Manual verify:**
1. Click "Deploy + Activate" button
2. Plugin deploys asynchronously and activates after completion

### Scenario 4: "Deployment queued" notice after redirect

**Automated verify:**
```bash
# Verify deploy_queued query parameter handling in template
grep -n "deploy_queued" admin/partials/repository-form.php
```

**Manual verify:**
1. Trigger deploy → redirect shows "Deployment queued" notice
2. Trigger deploy+activate → redirect shows "Deployment queued. The plugin will be deployed and activated shortly."

## Plan 05: Recursive Syntax Verification & Windows Rollback

**Requirements:** SAFETY-03 (post-deploy verification), SAFETY-04 (automatic rollback)

### Scenario 5: Syntax error in secondary file triggers rollback

**Automated verify:**
```bash
# Verify RecursiveIteratorIterator used in verify_deployment
grep -n "RecursiveIteratorIterator" core/class-deployment-manager.php
# Verify RecursiveDirectoryIterator used
grep -n "RecursiveDirectoryIterator" core/class-deployment-manager.php
# Verify token_get_all used in recursive scan loop
grep -n "token_get_all" core/class-deployment-manager.php
# Verify syntax_errors array and early return on failure
grep -n "syntax_errors" core/class-deployment-manager.php
```

**Manual verify:**
1. Deploy plugin with PHP syntax error in secondary file (e.g., core/class-foo.php missing semicolon)
2. Deployment fails with "PHP syntax errors found" message
3. Previous version automatically restored (rollback)
4. Deployment status shows "Failed" with verification error

### Scenario 6: Main file syntax check still works (backward compatibility)

**Automated verify:**
```bash
# Verify main file syntax check still present (existing Check 2)
grep -n "token_get_all.*main_file\|token_get_all.*plugin_slug" core/class-deployment-manager.php
```

**Manual verify:**
1. Deploy plugin with syntax error only in main file → still fails (existing behavior)

### Scenario 7: Valid plugin deploys successfully (no false positives)

**Automated verify:**
```bash
# Verify no unconditional failure in recursive scan
grep -n "syntax_all.*true" core/class-deployment-manager.php
```

**Manual verify:**
1. Deploy a valid plugin → success, no false positive verification failure

### Scenario 8: Windows rollback cleanup

**Automated verify:**
```bash
# Verify cleanup_dir called before copy_directory in Windows fallback
grep -B2 "copy_directory.*old_path\|copy_directory.*plugin_path" core/class-deployment-manager.php
# Verify cleanup_dir($plugin_path) appears before copy_directory in rollback section
grep -n "cleanup_dir.*plugin_path" core/class-deployment-manager.php
```

**Manual verify:**
1. On Windows: deploy fails → rollback cleans target directory before restoring from .old
2. No broken files remain after rollback
