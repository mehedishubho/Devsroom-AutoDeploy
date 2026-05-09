# Devsoom AutoDeploy

## What This Is

A WordPress plugin that automates deploying plugins from GitHub repositories to a WordPress site. Supports webhook-triggered, polling-based, and manual deployments with atomic file swaps, incremental sync, post-deploy verification, automatic rollback, and concurrent pipeline execution. Production-grade safety and performance for managing 20+ plugins.

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
- ✓ Deployment locking — per-plugin DB-based lock with stale detection and admin force-unlock — v2.0
- ✓ Atomic file swaps — rename-based swap with Windows fallback, no more delete-then-copy — v2.0
- ✓ Post-deploy verification — syntax check, plugin header, readability, OPcache — v2.0
- ✓ Automatic rollback on failure — restores previous version on verification failure — v2.0
- ✓ Optimized file operations — native PHP rename/copy, memory-safe entry-by-entry ZIP extraction — v2.0
- ✓ Incremental file deployment — GitHub Compare API, sync only changed files — v2.0
- ✓ Parallel pipeline steps — concurrent backup + download via curl_multi — v2.0
- ✓ Better error recovery — try/finally, shutdown handler, daily orphan cleanup cron — v2.0

### Active

- [ ] Progress tracking with real-time status — expose pipeline step progress (downloading, extracting, scanning, deploying) for UI feedback
- [ ] Deployment queue system — for 20+ plugins, queue deploys and process them without overwhelming the server

### Out of Scope

- Multi-site support — focus on single-site reliability first
- Git diff integration — too complex, incremental via GitHub API compare is sufficient
- Docker/container-based deployments — out of scope for WordPress plugin context
- Plugin marketplace/distribution features — this is a deployment tool, not a package manager
- CI/CD pipeline integration — webhook and manual triggers cover this use case

## Context

**Current state:** v2.0 Pipeline Optimization shipped. The deployment pipeline now uses atomic file swaps (rename-based with Windows fallback), post-deploy verification (syntax, header, readability, OPcache), automatic rollback on failure, incremental sync via GitHub Compare API, concurrent backup+download via curl_multi, and database-based deployment locking with stale detection. Error recovery wraps everything in try/finally with shutdown handler fallback and daily orphan cleanup.

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
| Incremental sync via GitHub Compare API | Avoids full downloads; API-provided file lists avoid line-ending hash mismatch issues | ✓ Good — v2.0 |
| Atomic swap via temp dir + rename | Rename is instant and atomic on POSIX; copy fallback for Windows | ✓ Good — v2.0 |
| Database columns for locking | Survives across PHP processes; atomic UPDATE with WHERE; stale detection via TIMESTAMP | ✓ Good — v2.0 |
| Native PHP file ops instead of WP_Filesystem | WP_Filesystem adds overhead; direct PHP is faster; memory-safe entry-by-entry extraction | ✓ Good — v2.0 |
| TTL-based stale lock expiry (10 min) | Simple, no admin intervention needed; force-unlock button as safety valve | ✓ Good — v2.0 |
| Entry-by-entry ZIP extraction | Prevents memory exhaustion on large plugins (Pitfall 12); getStream() + stream_copy_to_stream() | ✓ Good — v2.0 |
| curl_multi for concurrent pipeline | Bypasses WordPress HTTP API for true concurrent HTTP; justified for backup+download overlap | ✓ Good — v2.0 |
| try/finally + register_shutdown_function | Three-layer cleanup: normal path, exception path, fatal error path | ✓ Good — v2.0 |

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
*Last updated: 2026-05-10 after v2.0 Pipeline Optimization milestone*
