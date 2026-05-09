# Requirements: Devsoom AutoDeploy

**Defined:** 2026-05-10
**Core Value:** Deployments must be fast AND safe — every deploy should complete quickly with zero risk of breaking a live site, even when managing 20+ plugins.

## v2.0 Requirements

Scoped for milestone v2.0 Pipeline Optimization. Safety and Performance categories.

### Safety (Deployment Integrity)

- [ ] **SAFETY-01**: Deployment acquires a per-plugin lock before starting, preventing concurrent deployments to the same repository (database-based lock with stale detection and admin force-unlock)
- [ ] **SAFETY-02**: Deployment uses atomic file swap — deploys to temp directory, verifies, then renames into place instead of deleting the live plugin first (cross-platform: rename on POSIX, copy fallback on Windows)
- [ ] **SAFETY-03**: Post-deploy verification confirms the deployed plugin loads correctly — PHP syntax check on main plugin file, verify plugin header exists, check `is_readable()` on critical files, clear OPcache
- [ ] **SAFETY-04**: Automatic rollback triggers when post-deploy verification fails — restores from backup using atomic swap pattern (not destructive extract-over), validates backup integrity before restore
- [ ] **SAFETY-05**: Error recovery cleans up all partial states — try/finally wrapper on entire deployment, `register_shutdown_function()` for crash cleanup, daily WP-Cron cleanup of orphaned temp directories, `unlink()` return value checking

### Performance (Speed)

- [ ] **PERF-01**: File copy operations use native PHP instead of WP_Filesystem — `rename()` for atomic moves, `stream_copy_to_stream()` for cross-filesystem, `ZipArchive::extractTo()` entry-by-entry for memory safety, WP_Filesystem as fallback only
- [ ] **PERF-02**: Incremental file deployment syncs only changed files via GitHub Compare API — uses API-provided `status` field (added/modified/removed) instead of local hash comparison, handles file deletions, falls back to full archive if >50% files changed or first deploy
- [ ] **PERF-03**: Pipeline steps overlap where independent — backup and GitHub archive download run concurrently using `curl_multi` for HTTP overlap with local backup I/O

## v3.0 Requirements (Deferred)

Deferred to future milestone. Tracked but not in current roadmap.

### Scale (20+ Plugin Management)

- **SCALE-01**: Progress tracking exposes granular pipeline step status (downloading, extracting, scanning, deploying, verifying, rolling_back) via AJAX polling endpoint
- **SCALE-02**: Deployment queue system processes multiple plugin deployments via custom DB table with status field, atomic claim pattern, configurable concurrency, and stuck-item recovery

## Out of Scope

| Feature | Reason |
|---------|--------|
| Multi-site support | Focus on single-site reliability first |
| Git diff integration | GitHub API Compare is sufficient; git binary requires exec() access |
| Docker/container deployments | Out of scope for WordPress plugin context |
| CI/CD pipeline integration | Webhook and manual triggers cover this use case |
| Real-time WebSocket progress | WordPress has no native WebSocket support; AJAX polling is sufficient |
| Blue-green deployment | Temp-dir + rename swap gives atomic safety without two permanent copies |
| Built-in CI/CD (test runners) | Deployment tool deploys what GitHub provides; builds are the repo's responsibility |
| Retry with exponential backoff | Failures are deterministic; log clearly, allow manual retry |
| Database migration during deploy | Deployment handles files only; plugin activation handles its own schema |
| Parallel pipeline steps (full async) | `curl_multi` overlap is sufficient; true PHP async adds prohibitive complexity |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| SAFETY-01 | Phase 1 — Safety Foundation | Pending |
| SAFETY-02 | Phase 1 — Safety Foundation | Pending |
| SAFETY-03 | Phase 1 — Safety Foundation | Pending |
| SAFETY-04 | Phase 1 — Safety Foundation | Pending |
| SAFETY-05 | Phase 1 — Safety Foundation | Pending |
| PERF-01 | Phase 2 — Performance | Pending |
| PERF-02 | Phase 2 — Performance | Pending |
| PERF-03 | Phase 2 — Performance | Pending |

**Coverage:**
- v2.0 requirements: 8 total
- Mapped to phases: 8
- Unmapped: 0 ✓

---
*Requirements defined: 2026-05-10*
*Last updated: 2026-05-10 after initial definition*
