# Phase 1 Research: Safety Foundation

**Phase:** 01-safety-foundation
**Researched:** 2026-05-10
**Status:** Complete

## Technical Approach

### SAFETY-01: Deployment Locking

**Approach:** Database-based per-plugin lock using `devsroom_repositories` table.

**Why database, not transient:** Transients can be evicted by object cache implementations and have no atomic compare-and-swap. Database `UPDATE ... WHERE locked_at IS NULL` is atomic at the MySQL level (row-level lock during UPDATE). This matches the CONTEXT.md decision D-04.

**Implementation:**
- Add `locked_at` TIMESTAMP NULL and `locked_by` INT NULL columns to `devsroom_repositories`
- Schema upgrade in `Main::maybe_upgrade_database()` using `dbDelta()` or direct `ALTER TABLE`
- Lock acquisition: `$wpdb->query($wpdb->prepare("UPDATE {$table} SET locked_at = NOW(), locked_by = %d WHERE id = %d AND locked_at IS NULL", $deployment_id, $repo_id))` — check `rows_affected === 1`
- Lock release: `$wpdb->update($table, array('locked_at' => null, 'locked_by' => null), array('id' => $repo_id))`
- Stale detection: `WHERE locked_at < NOW() - INTERVAL 10 MINUTE` (per D-02: 10-minute TTL)
- Force unlock: Admin action button on repository page (per D-03), nonce + capability check

**Pitfall addressed:** Pitfall 9 (transient deadlock) — avoided by using database columns instead of transients.

**Per-repo isolation:** Lock is per-repository row, so deployments to different plugins proceed independently (success criteria 1).

### SAFETY-02: Atomic File Swap

**Approach:** Deploy to temp directory, verify, then rename-swap.

**Temp directory strategy:** Use `WP_CONTENT_DIR . '/upgrade/devsroom-autodeploy-' . $repo_id . '-' . time()` (same filesystem as plugins directory). NOT `sys_get_temp_dir()` — this avoids Pitfall 1 (cross-volume rename failure on Windows).

**Swap sequence:**
1. Extract archive to temp dir
2. Find extracted subdirectory (GitHub archives have `{owner}-{repo}-{sha}/` prefix)
3. Verify new deployment (syntax check, plugin header)
4. `rename($plugin_path, $plugin_path . '.old')` — move current aside
5. `rename($temp_dir_inner, $plugin_path)` — swap new into place
6. `cleanup_dir($plugin_path . '.old')` — remove old after success

**Windows fallback:** If `rename()` returns false, use `copy()` + `unlink()` sequence. Check return values on every operation.

**First deployment:** If `$plugin_path` doesn't exist, skip step 4, just rename temp to target.

**Pitfall addressed:** Pitfall 2 (delete-then-copy) — old directory is never deleted until new one is verified and swapped in.

### SAFETY-03: Post-Deploy Verification

**Approach:** Multi-check verification after file swap, before declaring success.

**Checks (in order):**
1. Main plugin file exists: `file_exists($plugin_path . '/' . $plugin_slug . '.php')`
2. PHP syntax check: `token_get_all(file_get_contents($main_file))` wrapped in try/catch for `ParseError`
3. Plugin header exists: Read first 8KB, check for `Plugin Name:` regex match
4. Critical files readable: `is_readable()` on main file + key directories
5. OPcache cleared: `wp_opcache_invalidate($main_file, true)` if function exists

**Integration point:** `Deployment_Manager::deploy()` after the file swap (replacing current line 314-323 logic).

**Pitfall addressed:** Pitfall 7 (activation fatal error) — syntax check catches broken plugins before they're declared live.

### SAFETY-04: Automatic Rollback

**Approach:** If verification fails, restore from `.old` directory using atomic swap pattern.

**Rollback sequence:**
1. `rename($plugin_path, $plugin_path . '.failed')` — move broken deployment aside
2. `rename($plugin_path . '.old', $plugin_path)` — restore previous version
3. Update deployment status to `failed`
4. Log rollback event via `Logger`
5. `cleanup_dir($plugin_path . '.failed')` — remove failed deployment

**Backup integration:** If `.old` directory doesn't exist (first deployment rollback), use `Backup_Manager::restore_backup()` with atomic swap: extract backup to temp, verify, rename in. Do NOT use existing `restore_backup()` directly — it does delete-then-extract (Pitfall 13).

**Pitfall addressed:** Pitfall 13 (backup restore without verification) — rollback uses atomic swap, not destructive extract-over.

### SAFETY-05: Error Recovery

**Approach:** Three-layer cleanup strategy.

**Layer 1 — try/finally wrapper:**
Wrap the entire `deploy()` method body in try/finally. The finally block always attempts:
- Release deployment lock (if acquired)
- Cleanup temp directory (if created)
- Cleanup `.old` directory (if created)
- Update deployment status to `failed` (if still in progress)

**Layer 2 — `register_shutdown_function()`:**
Register at start of deployment. The shutdown function checks if the deployment is still in a non-terminal state (`pending`, `backing_up`, `scanning`, etc.) and marks it `failed` + cleans up temp files. Handles PHP fatal errors and timeouts.

**Layer 3 — WP-Cron daily cleanup:**
Add `devsroom_autodeploy_cleanup_orphaned` cron event. Scans `WP_CONTENT_DIR . '/upgrade/'` for directories matching `devsroom-autodeploy-*` older than 1 hour. Forcibly removes them. Schedule in `Activator::activate()`, clear in `Deactivator::deactivate()`.

**`unlink()` return checking:** Every `unlink()`, `rmdir()`, and filesystem operation checks return value and logs failures.

**Pitfall addressed:** Pitfall 8 (orphaned temp files) — three-layer cleanup ensures temp directories are always removed.

## Key Implementation Decisions

### File modifications

| File | Changes |
|------|---------|
| `database/class-schema.php` | Add `locked_at`, `locked_by` columns to `devsroom_repositories` |
| `core/class-deployment-manager.php` | Wrap `deploy()` with locking, atomic swap, verification, rollback, error recovery |
| `admin/class-repository-manager.php` | Add force unlock action handler |
| `admin/partials/repository-form.php` | Add force unlock button |
| `includes/class-main.php` | Register cleanup cron hook, add shutdown function registration |
| `core/class-polling-scheduler.php` | Add orphaned temp dir cleanup cron event |

### Implementation order

The dependency chain from FEATURES.md research:
1. **Locking** (independent foundation) — SAFETY-01
2. **Atomic swap** (transforms deployment pattern) — SAFETY-02
3. **Verification** (builds on swap to validate) — SAFETY-03
4. **Rollback** (uses verification result) — SAFETY-04
5. **Error recovery** (wraps everything) — SAFETY-05

### Existing code integration

- `Deployment_Manager::deploy()` is the primary integration point (617 lines)
- Lines 89+ for lock acquisition
- Lines 314-323 for the delete-then-copy section to replace with atomic swap
- `Backup_Manager::create_backup()` and `restore_backup()` for backup/restore
- `Logger` for lock/verification/rollback event logging

## Validation Architecture

### Verification strategy

Each safety feature can be verified independently:
- **Locking:** Attempt concurrent deployment to same repo → second should fail. Deploy to different repo → should succeed.
- **Atomic swap:** Deploy a plugin, kill process mid-deploy → old version should remain intact.
- **Verification:** Deploy a plugin with intentional syntax error → should fail verification and not go live.
- **Rollback:** Deploy a broken plugin → should auto-rollback to previous version.
- **Error recovery:** Deploy then check temp dirs are cleaned up. Check WP-Cron cleanup exists.

### Test scenarios

1. Happy path: Deploy → lock acquired → backup → download → extract → swap → verify → success → lock released
2. Lock conflict: Two concurrent deploys to same plugin → second rejected
3. Stale lock: Lock older than 10 min → stale detection allows new deployment
4. Verify failure: Syntax error in deployed plugin → rollback to previous
5. Crash recovery: Orphaned temp dirs cleaned by daily cron

## Sources

- `.planning/research/FEATURES.md` — Feature landscape and dependency chain
- `.planning/research/PITFALLS.md` — Pitfalls 1, 2, 4, 7, 8, 9, 10, 13, 14 directly applicable
- `.planning/codebase/ARCHITECTURE.md` — Deployment pipeline data flow
- `.planning/codebase/CONVENTIONS.md` — Singleton pattern, error handling, return types
- `.planning/codebase/CONCERNS.md` — Lines 65-77 (deployment safety concerns)
- `core/class-deployment-manager.php` — Primary integration target
- `database/class-schema.php` — Schema modification target
