# Devsoom AutoDeploy — Pipeline Optimization

## What This Is

A WordPress plugin that automates deploying plugins from GitHub repositories to a WordPress site. It supports webhook-triggered, polling-based, and manual deployments with backup, security scanning, and notification. The current deployment pipeline works but needs a comprehensive overhaul to handle 20+ plugins reliably at scale — making deployments faster (incremental sync), safer (atomic swaps, auto-rollback), and more resilient (parallel steps, better error recovery).

## Core Value

Deployments must be fast AND safe — every deploy should complete quickly with zero risk of breaking a live site, even when managing 20+ plugins.

## Requirements

### Validated

- ✓ GitHub repository management (add/edit/delete repos with branch, auth, scan settings) — existing
- ✓ PAT and OAuth 2.0 authentication with encrypted token storage — existing
- ✓ Webhook-triggered deployments via GitHub push events with HMAC-SHA256 verification — existing
- ✓ Polling-based deployments via WP-Cron at configurable intervals — existing
- ✓ Manual deployments from admin UI with deploy & activate option — existing
- ✓ Full backup before deployment (zip archive of existing plugin directory) — existing
- ✓ Security scanning at basic/advanced levels with issue alerting — existing
- ✓ Deployment history with status tracking (pending, backing_up, scanning, success, failed) — existing
- ✓ Per-deployment logging with info/warning/error/debug levels — existing
- ✓ Email notifications for deployment success, failure, and security alerts — existing
- ✓ Admin dashboard with repository, deployment, and settings pages — existing
- ✓ Self-deployment prevention (cannot overwrite the AutoDeploy plugin itself) — existing
- ✓ Database schema with 5 tables (repositories, auth_tokens, deployments, logs, backups) — existing
- ✓ Backup retention and cleanup via WP-Cron daily event — existing

### Active

- [ ] Incremental file deployment — only sync changed files instead of downloading and replacing the full archive every time
- [ ] Atomic file swaps — deploy to a temp directory then rename-swap to avoid partial/broken deploys on failure
- [ ] Automatic rollback on failure — restore from backup immediately if any post-swap verification fails
- [ ] Parallel pipeline steps — backup and GitHub archive download should run concurrently, not sequentially
- [ ] Deployment locking — prevent concurrent deploys to the same plugin (queue or reject overlapping requests)
- [ ] Progress tracking with real-time status — expose pipeline step progress (downloading, extracting, scanning, deploying) for UI feedback
- [ ] Post-deploy verification — confirm the plugin loads correctly after deployment (check for PHP syntax errors, verify main plugin file exists)
- [ ] Optimized file copy operations — replace slow recursive WP_Filesystem copy with native PHP stream copy or rename where possible
- [ ] Better error recovery — clean up partial states, resume or retry failed downloads, don't leave orphaned temp files
- [ ] Deployment queue system — for 20+ plugins, queue deploys and process them without overwhelming the server

### Out of Scope

- Multi-site support — focus on single-site reliability first
- Git diff integration — too complex, incremental via GitHub API compare is sufficient
- Docker/container-based deployments — out of scope for WordPress plugin context
- Plugin marketplace/distribution features — this is a deployment tool, not a package manager
- CI/CD pipeline integration — webhook and manual triggers cover this use case

## Context

**Current state:** The plugin is fully functional with all basic deployment features working. The pipeline is sequential (backup → download full zip → extract → scan → delete old → copy new → cleanup). This works for a handful of plugins but shows its limits at scale:
- Every deployment downloads the full repository archive regardless of what changed
- File operations use slow recursive WP_Filesystem copy instead of native PHP operations
- No deployment locking means concurrent webhooks can cause conflicts
- If the copy fails mid-way, the old plugin is already deleted with no automatic recovery
- No post-deploy verification — a broken plugin silently replaces a working one
- Sequential steps waste time when backup and download could run in parallel

**Technical environment:** PHP 8.0+, WordPress 6.0+, MySQL/MariaDB, no Composer/npm dependencies. All WordPress-native APIs.

**Codebase map:** Available at `.planning/codebase/` with full analysis of architecture, stack, integrations, conventions, testing, and concerns.

## Constraints

- **Tech stack:** Must remain pure WordPress-native PHP — no Composer dependencies, no external libraries
- **PHP version:** Minimum PHP 8.0 compatibility required
- **WordPress version:** Minimum WordPress 6.0 compatibility required
- **Database:** Must use existing `$wpdb` pattern, no ORM introduction
- **Singleton pattern:** Existing singleton services should be preserved unless refactoring is essential
- **Security:** All existing security measures (nonces, HMAC verification, token encryption, self-deploy prevention) must be maintained
- **Backward compatibility:** Existing database schema must be extended, not replaced (users already have data)

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Incremental sync via GitHub Compare API | Avoids full downloads; GitHub API supports comparing commits and returning file diffs | — Pending |
| Atomic swap via temp dir + rename | Rename is instant and atomic on POSIX; fallback to copy on Windows | — Pending |
| Deployment queue via custom DB table | Avoids PHP process-level locking; survives across requests | — Pending |
| Native PHP file ops instead of WP_Filesystem | WP_Filesystem adds abstraction overhead; direct PHP is faster and we control the environment | — Pending |

## Evolution

This document evolves at phase transitions and milestone boundaries.

**After each phase transition** (via `/gsd-transition`):
1. Requirements invalidated? → Move to Out of Scope with reason
2. Requirements validated? → Move to Validated with phase reference
3. New requirements emerged? → Add to Active
4. Decisions to log? → Add to Key Decisions
5. "What This Is" still accurate? → Update if drifted

**After each milestone** (via `/gsd-complete-milestone`):
1. Full review of all sections
2. Core Value check — still the right priority?
3. Audit Out of Scope — reasons still valid?
4. Update Context with current state

---
*Last updated: 2026-05-10 after initialization*
