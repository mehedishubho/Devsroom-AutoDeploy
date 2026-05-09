# Phase 1: Safety Foundation - Context

**Gathered:** 2026-05-10
**Status:** Ready for planning

<domain>
## Phase Boundary

Make every deployment safe. This phase delivers: per-plugin deployment locking (prevent concurrent conflicts), atomic file swaps (no more delete-then-copy), post-deploy verification (catch broken deploys), automatic rollback on failure, and error recovery (no orphaned temp files). The existing deployment pipeline in `Deployment_Manager::deploy()` is the primary integration point. The existing `Backup_Manager` provides the restore foundation.

This phase does NOT add new features — it makes the existing deployment pipeline safe and resilient.

</domain>

<decisions>
## Implementation Decisions

### Lock conflict behavior
- **D-01:** Reject with message when a deployment is already running for the same plugin. The new request gets an immediate failure response (e.g., "Deployment already in progress for this plugin"). User can retry later.
- **D-02:** TTL-based expiry for stale locks. Default: 10 minutes. Simple, no admin intervention needed for normal operation.
- **D-03:** Admin force unlock button on the repository page. Allows manual override when a lock is stuck (e.g., server crash during deploy).
- **D-04:** Lock stored as database columns (`locked_at` TIMESTAMP, `locked_by` INT referencing deployment ID) on the `devsroom_repositories` table. Atomic acquisition via `UPDATE repos SET locked_at = NOW(), locked_by = deployment_id WHERE id = X AND locked_at IS NULL`. Release via `UPDATE repos SET locked_at = NULL, locked_by = NULL WHERE id = X`.

### Agent's Discretion
- **Verification depth:** Agent decides how thorough post-deploy verification should be (syntax check, plugin header, readability, OPcache). Research recommends: syntax check via `token_get_all()`, verify main plugin file exists with valid plugin header, check `is_readable()` on critical files, call `wp_opcache_invalidate()`.
- **Rollback strategy:** Agent decides whether to use existing `Backup_Manager::restore_backup()` or keep old directory as fallback. Research recommends: atomic swap pattern (old dir renamed to `.old`, new dir renamed in, `.old` deleted after success). If rename fails, restore by renaming `.old` back.
- **Error recovery scope:** Agent decides cleanup strategy. Research recommends: try/finally wrapper on entire deployment, `register_shutdown_function()` for crash cleanup, daily WP-Cron cleanup of orphaned temp directories matching `devsroom-autodeploy-*` pattern.

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Deployment pipeline (current implementation)
- `.planning/codebase/ARCHITECTURE.md` — Data flow for webhook, polling, and manual deployments; pipeline steps in `Deployment_Manager`
- `.planning/codebase/CONVENTIONS.md` — Singleton pattern, error handling patterns, return type conventions, logging framework
- `.planning/codebase/CONCERNS.md` — Lines 65-77: No atomic deployment, backup failure doesn't block deploy, no concurrency protection

### Safety research
- `.planning/research/FEATURES.md` — Dependency chain (locking → atomic swaps → verification → rollback), technical implementation notes for each feature
- `.planning/research/PITFALLS.md` — Pitfalls 1-4 and 7-9 directly affect this phase: `rename()` on Windows (Pitfall 1), delete-then-copy bug (Pitfall 2), backup corruption from concurrent iteration (Pitfall 4), plugin activation during deploy (Pitfall 7), temp file cleanup (Pitfall 8), transient deadlock (Pitfall 9)

### Database schema
- `database/class-schema.php` — Current table definitions; `devsroom_repositories` table needs `locked_at` and `locked_by` columns added

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `Backup_Manager::create_backup()` and `restore_backup()` — ZIP-based backup/restore, ready to use for rollback
- `Logger` — Per-deployment logging with info/warning/error/debug levels; use for lock events, verification results, rollback events
- `Deployment_Manager::deploy()` — Main pipeline to wrap with locking, atomic swap, verification, and error recovery

### Established Patterns
- Singleton pattern on all core services — new `Deployment_Lock` class (or methods on `Deployment_Manager`) should follow same pattern
- `array|false` return type pattern — lock acquisition should return `array|false`
- `$wpdb->prepare()` for all queries — lock queries must use prepared statements
- WordPress nonces + capability checks for admin force unlock action

### Integration Points
- `Deployment_Manager::deploy()` line 89 — lock acquisition point (before any work begins)
- `Deployment_Manager::deploy()` line 314-323 — the delete-then-copy section that atomic swap replaces
- `admin/class-repository-manager.php` — add force unlock action handler
- `admin/partials/repository-form.php` — add force unlock button UI
- `database/class-schema.php` — add `locked_at` and `locked_by` columns to `devsroom_repositories`

</code_context>

<specifics>
## Specific Ideas

- Research warns that `rename()` is NOT atomic on Windows and fails across volumes (Pitfall 1). Must use `WP_CONTENT_DIR . '/upgrade/'` as temp dir (same filesystem as plugins), not `sys_get_temp_dir()`. Must implement copy fallback for Windows.
- Research warns that backup corruption from concurrent directory iteration (Pitfall 4) means the lock MUST be acquired BEFORE backup creation.
- Research warns that `ZipArchive::extractTo()` has no path validation (Pitfall 10). Validate extracted paths after extraction.
- The existing `Backup_Manager::restore_backup()` deletes current dir first then extracts — same dangerous pattern. Rollback should use atomic swap too.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 01-safety-foundation*
*Context gathered: 2026-05-10*
