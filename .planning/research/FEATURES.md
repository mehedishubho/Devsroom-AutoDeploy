# Feature Landscape

**Domain:** WordPress plugin deployment pipeline optimization
**Researched:** 2026-05-10
**Existing Plugin State:** Full deployment pipeline with sequential backup -> download -> extract -> scan -> delete old -> copy new -> cleanup flow

## Table Stakes

Features users expect from any deployment tool. Missing = deployments are unreliable, slow, or dangerous.

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| Atomic file swaps | Without this, a failed mid-deploy leaves a broken plugin on the live site. Professional tools (Deployer, Capistrano) consider this non-negotiable. The current delete-then-copy approach is inherently unsafe. | Medium | Deploy to temp dir, then rename-swap. PHP `rename()` is atomic on POSIX for same-filesystem moves. Must fall back to copy+delete on Windows or cross-filesystem moves. |
| Automatic rollback on failure | If deployment breaks the plugin, users expect one-click or automatic restoration. The backup system already exists but rollback is manual-only. Without auto-rollback, a failed deploy means an urgent manual fix. | Medium | Leverage existing `Backup_Manager::restore_backup()`. Add automatic trigger when post-swap verification fails. Store rollback status in deployment record. |
| Deployment locking (per-plugin) | Concurrent webhooks targeting the same plugin cause file conflicts, race conditions, and corrupted deployments. This is a data integrity issue, not a nice-to-have. | Medium | Use database-based lock (row-level via `devsroom_repositories` table or dedicated locks table). Reject or queue overlapping requests. Lock must survive across PHP processes since WordPress uses separate processes per request. |
| Post-deploy verification | Deploying files that have PHP syntax errors or missing the main plugin file silently breaks the site. Users expect the tool to catch this before declaring success. | Low | `php -l` syntax check via `exec()` or manual token parsing. Verify main plugin file exists and contains valid plugin header. Check `is_readable()` on critical files. |
| Progress tracking with status updates | Users staring at a spinner with no feedback for 30+ seconds (large repos) assume the tool is broken. Every professional deployment tool shows step-by-step progress. | Medium | Extend deployment status enum to include granular steps: `downloading`, `extracting`, `scanning`, `deploying`, `verifying`, `rolling_back`. Expose via existing logger + AJAX polling endpoint. |
| Clean error recovery | Partial states (orphaned temp files, half-deleted plugin dirs) should never persist. Current cleanup only runs on the happy path. | Low | Wrap deployment steps in try-catch equivalent (early return pattern with cleanup). Add `finally`-style cleanup using a shutdown function or cleanup queue. Clean temp dirs on plugin activation too (orphaned from crashed processes). |

## Differentiators

Features that set this plugin apart from basic WordPress deployment approaches. Not expected by casual users, but valued by professionals managing 20+ plugins.

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| Incremental file deployment (diff-based sync) | For repos with 100+ files, downloading and replacing everything on each deploy wastes bandwidth and time. GitHub Compare API (`GET /repos/{owner}/{repo}/compare/{base}...{head}`) returns exactly which files changed between two commits. Only sync changed files. | High | Use GitHub Compare API to get file list with `status` field (added, modified, removed, renamed). Download individual changed files via Contents API. Delete removed files locally. First deploy still uses full archive (no base commit to compare). Requires storing `last_deployed_commit_hash` per repo (already exists as `last_commit_hash` in repositories table). API rate limit consideration: Compare API counts as 1 request regardless of diff size. |
| Deployment queue system | When 20+ plugins push simultaneously, parallel deployment overwhelms server I/O and memory. A queue processes them sequentially or in controlled parallelism. Prevents resource exhaustion. | High | New `devsroom_deployment_queue` table with status, priority, scheduled_at. WP-Cron worker processes queue entries. Configurable concurrency (default: 1 at a time). Queue dashboard in admin UI. Must integrate with locking mechanism. |
| Parallel pipeline steps | Current pipeline is strictly sequential: backup -> download -> scan -> deploy. Backup and download are I/O-bound and independent. Running them concurrently cuts wall-clock time ~40-50%. | High | PHP does not have native async/concurrency. Options: (1) Use `curl_multi` for concurrent HTTP while backup runs, (2) Split into multi-request pipeline with WP-Cron stepping, (3) Use `proc_open` for background processes. Given WordPress constraints, the most practical approach is `curl_multi` for overlapping HTTP calls and sequential file operations. The "parallel" here means overlapping the GitHub API call with local backup I/O. |
| Optimized file copy operations | Current `copy_directory()` uses `WP_Filesystem::copy()` in a recursive iterator loop. Each file copy goes through WordPress abstraction. For 200+ files this is measurably slower than native PHP. | Low | Replace `WP_Filesystem::copy()` loop with native PHP operations: `rename()` for atomic same-filesystem moves, `stream_copy_to_stream()` for cross-filesystem copies, or `ZipArchive::extractTo()` directly to target path when no incremental diff is needed. Keep WP_Filesystem as fallback only. |

## Anti-Features

Features to explicitly NOT build. These would add complexity without proportional value, or conflict with the plugin's constraints.

| Anti-Feature | Why Avoid | What to Do Instead |
|--------------|-----------|-------------------|
| Git binary integration (running `git pull`, `git clone`) | Requires `exec()` or `proc_open()` access which many shared hosts disable. Adds server dependency on git being installed. Violates the "pure WordPress-native PHP" constraint. | Use GitHub REST API exclusively. The API provides everything needed (archive download, file contents, commit comparison). |
| Real-time WebSocket progress | WordPress has no native WebSocket support. Adding it requires a standalone PHP server process or third-party service, which contradicts the "no external dependencies" constraint. | AJAX polling with short intervals (1-2 seconds) against a REST endpoint. Store progress in deployment record. Good enough for the admin UI use case. |
| Blue-green deployment (two complete plugin copies) | WordPress loads plugins from a fixed path. Symlinks work on some hosts but not all (Windows IIS, some shared hosts). The plugin slug must resolve to a real directory. | Temp-dir + rename swap gives atomic safety without requiring two permanent copies. On Windows, fall back to copy which is still safe (old dir stays until new copy completes). |
| Built-in CI/CD pipeline (test runners, build steps) | This is a deployment tool, not a CI/CD platform. Running tests, building assets, or linting code is the repository's responsibility (GitHub Actions). The plugin should deploy what GitHub provides, not transform it. | Support pre-built artifacts only. If users need build steps, they should use GitHub Actions to produce a build artifact branch or release, then deploy from that. |
| Retry with exponential backoff for deployments | Deployment failures are typically deterministic (auth expired, branch deleted, plugin path locked). Retrying the same failing deploy wastes resources and confuses users. | Log the error clearly, allow manual retry via UI button. For transient network issues, retry once immediately (not with backoff). |
| Database migration execution during deployment | Running `dbDelta()` or custom SQL during plugin deployment is extremely dangerous. A failed migration can break both the plugin AND the WordPress site. | Deployment handles files only. Plugin activation handles its own schema. The deploy tool should never touch the database schema of the plugin being deployed. |
| Multi-site support | Adds significant complexity (switch_to_blog, network vs site-level plugins, different path structures). The plugin is not architected for it. | Focus on single-site reliability first. Multi-site can be a future milestone with its own research phase. |

## Feature Dependencies

```
Atomic Swaps (foundation)
  |
  +--> Post-deploy Verification (needs swap to verify before/after)
  |      |
  |      +--> Automatic Rollback (needs verification result to trigger)
  |
  +--> Incremental Deployment (needs temp dir pattern from atomic swap)
  |
  +--> Optimized File Copy (independent, can be done anytime)

Deployment Locking (independent foundation)
  |
  +--> Deployment Queue (needs locking to enforce concurrency limits)

Progress Tracking (independent, can be done anytime)

Parallel Pipeline Steps (independent but complex, lowest priority)
```

**Dependency chain (must implement in order):**
1. Deployment Locking -- independent, foundational for safety
2. Atomic Swaps -- transforms the deployment pattern from destructive to safe
3. Post-deploy Verification -- builds on atomic swap to validate before finalizing
4. Automatic Rollback -- uses verification result + existing backup system
5. Incremental Deployment -- uses atomic swap temp dir + GitHub Compare API
6. Optimized File Copy -- independent optimization, can slot in anytime
7. Progress Tracking -- independent, improves UX for all steps above
8. Deployment Queue -- needs locking, builds on top of everything
9. Parallel Pipeline Steps -- most complex, lowest ROI

## MVP Recommendation

**Phase 1 (Safety - Must Have First):**
1. Deployment Locking -- prevents data corruption from concurrent deploys
2. Atomic Swaps -- eliminates the "broken site on failed deploy" scenario
3. Post-deploy Verification -- catches broken plugins before declaring success
4. Automatic Rollback -- auto-recovers when verification fails

**Phase 2 (Speed):**
5. Optimized File Copy -- immediate speed win with low effort
6. Incremental Deployment -- major bandwidth and time savings for large repos

**Phase 3 (Scale):**
7. Progress Tracking -- essential UX for longer deployments
8. Deployment Queue -- needed for 20+ plugin management

**Defer:**
- Parallel Pipeline Steps: HIGH complexity, MEDIUM value. The overlap between backup and download saves only a few seconds. Only pursue if profiling shows backup+download as dominant time cost.

## Technical Implementation Notes

### GitHub Compare API for Incremental Deployment
- Endpoint: `GET /repos/{owner}/{repo}/compare/{base}...{head}`
- Returns `files` array with each file's `status` (added, removed, modified, renamed), `filename`, `sha`
- Also returns `patch` content but better to download raw file via Contents API for binary safety
- For changed files, download individual file content: `GET /repos/{owner}/{repo}/contents/{path}?ref={sha}`
- Rate limit: 1 API call for comparison + N calls for changed files. Still cheaper than full archive for small diffs.
- Fallback: If comparison returns too many changes (>50% of files), fall back to full archive download.

### Atomic Swap on WordPress/PHP
- `rename($temp_dir, $plugin_path)` is atomic on POSIX when source and target are on same filesystem
- On Windows: `rename()` works across directories but is not guaranteed atomic. Use `rename()` with fallback to copy.
- The old plugin must be moved aside first: `rename($plugin_path, $plugin_path . '.old')` then `rename($temp_dir, $plugin_path)` then `unlink($plugin_path . '.old')`
- If the second rename fails, restore from `.old` -- this is the rollback path.

### Deployment Locking Strategy
- Database-based lock using `devsroom_repositories` table: add `locked_at` and `locked_by` columns
- Lock acquisition: atomic `UPDATE repos SET locked_at = NOW(), locked_by = deployment_id WHERE id = X AND locked_at IS NULL`
- Lock release: `UPDATE repos SET locked_at = NULL, locked_by = NULL WHERE id = X`
- Stale lock detection: if `locked_at` is older than configurable threshold (default: 10 minutes), force-release
- Do NOT use PHP `flock()` -- unreliable across WordPress processes and doesn't survive request boundaries

### Progress Tracking Granularity
Extended status enum values for deployment record:
- `pending` (existing) -- created, waiting to start
- `locking` -- acquiring deployment lock
- `backing_up` (existing) -- creating backup
- `comparing` -- fetching diff from GitHub Compare API (incremental mode)
- `downloading` -- downloading archive or changed files
- `extracting` -- extracting archive to temp dir
- `scanning` (existing) -- running security scan
- `deploying` -- atomic swap in progress
- `verifying` -- post-deploy verification checks
- `rolling_back` -- rollback in progress after failed verification
- `success` (existing) -- deployment completed
- `failed` (existing) -- deployment failed
- `cancelled` -- deployment cancelled (e.g., lock acquisition failed)

## Sources

- GitHub REST API endpoints for commits (Compare API): https://docs.github.com/en/rest/commits/commits?apiVersion=2022-11-28 -- HIGH confidence (official docs, verified)
- GitHub REST API endpoints for repository contents: https://docs.github.com/en/rest/repos/contents?apiVersion=2022-11-28 -- HIGH confidence (official docs, verified)
- Deployer PHP deployment tool patterns (atomic symlink swaps, release dirs): Training data -- MEDIUM confidence (well-established patterns, but specific version docs unavailable due to rate limiting)
- Capistrano deployment patterns (atomic releases, rollback): Training data -- MEDIUM confidence (foundational deployment patterns widely documented)
- PHP `rename()` atomicity behavior: Training data (PHP manual) -- HIGH confidence (well-documented PHP behavior)
- Existing codebase analysis: `.planning/codebase/ARCHITECTURE.md`, `.planning/codebase/STACK.md`, all core PHP files -- HIGH confidence (direct source reading)
