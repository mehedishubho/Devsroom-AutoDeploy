# Devsroom AutoDeploy v2.0.0 — Pipeline Optimization

**Release Date:** May 10, 2026
**Milestone:** v2.0 Pipeline Optimization

---

## What's New

v2.0.0 makes the deployment pipeline **production-safe and fast**. Every deploy now uses atomic file swaps (zero downtime), runs post-deploy verification (catches broken plugins before they go live), and automatically rolls back on failure. For large plugins, incremental sync downloads only the files that changed instead of the entire archive.

---

## New Features

### Deployment Locking
- Per-plugin database-based locking prevents concurrent deployments to the same repository
- Atomic SQL acquisition (`UPDATE ... WHERE locked_at IS NULL`) — no race conditions
- Stale locks auto-expire after 10 minutes (covers server crashes)
- Admin force-unlock button on the repository page for manual override
- Lock status visible in repository table with lock icon and tooltip

### Atomic File Swaps
- Replaced dangerous delete-then-copy pattern with rename-based atomic swap
- Plugin files are never missing during deployment — old version stays in place until new version is verified
- Temp directory uses `WP_CONTENT_DIR/upgrade/` (same filesystem as plugins) for reliable rename
- Windows fallback via `copy_directory()` when `rename()` fails
- `.old` directory preserved until verification passes, then cleaned up

### Post-Deploy Verification
- 5-check verification after every deployment:
  1. Main plugin file exists
  2. PHP syntax valid via `token_get_all()` — scans ALL PHP files recursively
  3. WordPress plugin header present
  4. Critical files readable (`is_readable()`)
  5. OPcache cleared (`wp_opcache_invalidate()`)
- Syntax errors in ANY file (not just the main file) trigger failure — catches broken includes, classes, and helpers

### Automatic Rollback
- If post-deploy verification fails, previous version is automatically restored from `.old` directory
- Broken deployment moved to `.failed` then cleaned up
- Windows rollback cleans target directory before restoring (no file merging)
- No manual intervention needed — the plugin handles everything

### Incremental File Sync
- Uses GitHub Compare API to detect exactly which files changed between commits
- Only downloads modified, added, and renamed files — skips unchanged files
- Handles file deletions (removes files that were deleted in the new commit)
- Falls back to full archive download when >100 files changed or on first deploy
- Falls back if any individual file download fails

### Memory-Safe Extraction
- ZIP archives extracted entry-by-entry using `getStream()` + `stream_copy_to_stream()`
- Prevents memory exhaustion on large plugins (WooCommerce, page builders, 1000+ file plugins)
- No special handling needed — same approach regardless of plugin size

### Concurrent Pipeline
- Backup creation (local disk I/O) and GitHub archive download (HTTP) run simultaneously via `curl_multi`
- Reduces total deployment time by overlapping independent operations

### Error Recovery
- Entire deployment pipeline wrapped in `try/finally` for guaranteed cleanup on any code path
- `register_shutdown_function()` catches PHP fatal errors and cleans up temp directories
- Daily WP-Cron job removes orphaned `devsroom-autodeploy-*` temp directories older than 1 hour
- All `unlink()` and `rmdir()` return values checked and logged

### Async Manual Deployments
- "Deploy Now" and "Deploy + Activate" now use `wp_schedule_single_event()` instead of synchronous execution
- Page redirects immediately with "Deployment queued" notice
- Lock indicator and unlock button visible in repository table during active deployments
- Webhook and polling deployments remain synchronous (no change)

---

## Security

- All admin operations check `manage_options` capability
- Form submissions verified with WordPress nonces
- Webhook payloads verified with HMAC-SHA256 timing-safe comparison
- GitHub tokens encrypted at rest with AES-256-CBC
- Self-deployment prevention (cannot overwrite AutoDeploy itself)
- All queries parameterized via `$wpdb->prepare()`
- Input sanitized with `sanitize_text_field()` and `sanitize_email()`

---

## Bug Fixes

- Fixed atomic swap failing on Windows servers due to cross-volume rename
- Fixed plugin file detection using `find_plugin_main_file()` with glob fallback
- Fixed `copy_directory()` now returns `bool` instead of `void` for error checking
- Fixed lock indicator not visible during active deployments (async deployment)
- Fixed unlock button not appearing when repository is locked (async deployment)
- Fixed `verify_deployment()` only checking main plugin file — now scans all PHP files recursively
- Fixed Windows rollback merging broken files with old version — now cleans target before restore

---

## UI Improvements

- Added pipeline visualization with step-by-step progress (locking → backup → compare → download → extract → scan → deploy → verify)
- Added status badges for all pipeline states (locking, comparing, downloading, extracting, deploying, verifying, rolling_back, cancelled)
- Added deployment detail page with pipeline progress, commit info, and expandable logs
- Added repository search/filter on repositories page
- Added pagination on deployments list
- Added lock indicator with tooltip showing lock timestamp
- Added "Deployment queued" notice for async deployments
- Refactored all inline CSS to utility classes for consistency
- Responsive design for mobile/tablet admin screens

---

## Database

- Added `locked_at` (TIMESTAMP) and `locked_by` (BIGINT) columns to `devsroom_repositories`
- Schema upgrade runs safely on existing installations via `upgrade_schema()`

---

## Requirements

- WordPress 6.0 or higher
- PHP 8.0 or higher
- MySQL 5.6+ or MariaDB equivalent
- `openssl` PHP extension (token encryption)
- `zip` PHP extension (archive extraction and backups)
- `curl` PHP extension (concurrent pipeline)
- Outbound HTTPS to `api.github.com`

---

## Upgrade Notes

**From v1.0.x:** The database schema will be upgraded automatically on activation. Existing repositories and deployments are preserved. No manual migration needed.

**Breaking changes:** None. All existing functionality is preserved. v2.0 adds safety and performance layers on top of the existing deployment pipeline.

---

## Commits

<details>
<summary>56 commits across 2 phases</summary>

### Phase 1: Safety Foundation (5 plans)
- `84bad9e` feat: add deployment locking to prevent concurrent deploys
- `9353a96` feat: add admin force-unlock UI for stuck deployment locks
- `3a1621c` feat: replace delete-then-copy with atomic file swap in deploy()
- `59c569e` feat: wrap deploy() with try/finally and register_shutdown_function
- `55d1bf4` feat: add daily WP-Cron cleanup for orphaned temp directories
- `7db333e` feat: make manual deployments async with wp_schedule_single_event
- `7313285` feat: recursive PHP syntax verification and Windows-safe rollback

### Phase 2: Performance (2 plans)
- `a18b4f7` feat: memory-safe ZIP extraction and GitHub Compare API method
- `050af4a` feat: incremental sync via GitHub Compare API
- `8d34408` feat: concurrent backup and download via curl_multi

</details>

---

**Full changelog:** [CHANGELOG.md](./CHANGELOG.md)
**Documentation:** [README.md](./README.md)
