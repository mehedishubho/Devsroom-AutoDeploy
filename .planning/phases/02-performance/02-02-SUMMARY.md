---
phase: 02-performance
plan: 02
subsystem: deployment
tags: [incremental-sync, curl-multi, github-compare-api, contents-api, concurrent-io, pitfall-3, pitfall-6, pitfall-11]

# Dependency graph
requires:
  - phase: 02-performance
    plan: 01
    provides: "Memory-safe entry-by-entry ZIP extraction + GitHub Compare API method (compare_commits)"
  - phase: 01-safety-foundation
    plan: 02
    provides: "Atomic swap temp directory pattern (WP_CONTENT_DIR/upgrade/)"
provides:
  - "Incremental file sync via GitHub Compare API with per-file download via Contents API"
  - "Concurrent backup+download via curl_multi overlapping HTTP I/O with local disk I/O"
  - "get_token_for_curl() for curl_multi auth header compatibility"
affects: [02-performance, deployment, incremental-sync, concurrent-io]

# Tech tracking
tech-stack:
  added: []
  patterns: [incremental-sync-compare-api, curl-multi-concurrent-io, contents-api-raw-download]

key-files:
  created: []
  modified:
    - core/class-deployment-manager.php
    - core/class-github-api.php

key-decisions:
  - "Incremental sync uses Compare API-provided file status lists (not local hash comparison) to avoid Pitfall 3 line-ending hash mismatches"
  - "Fallback threshold set at 100 changed files — beyond that, full archive download is more efficient than N individual API calls"
  - "curl_multi used for concurrent HTTP because WordPress HTTP API has no concurrent request support"
  - "Backup runs during curl_multi non-blocking window — overlaps local disk I/O with network I/O"
  - "download_file_content() uses Accept: application/vnd.github.v3.raw to get raw content without base64 decoding"

patterns-established:
  - "Incremental sync: Compare API file classification (added/modified/removed/renamed) → per-file Contents API download → direct write to temp_dir"
  - "Concurrent pipeline: curl_multi_init → add download handle → non-blocking exec → create backup during network wait → wait for completion"
  - "Fallback chain: incremental sync → full archive download → entry-by-entry extraction"

requirements-completed: [PERF-02, PERF-03]

# Metrics
duration: 3min
completed: 2026-05-10
---

# Phase 2 Plan 2: Incremental Sync + Concurrent Backup/Download Summary

**Incremental file sync via GitHub Compare API downloading only changed files, and concurrent backup+download via curl_multi overlapping HTTP I/O with local disk I/O**

## Performance

- **Duration:** 3 min
- **Started:** 2026-05-10T04:21:23Z
- **Completed:** 2026-05-10T04:24:18Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments
- Added `sync_incremental()` to Deployment_Manager that uses GitHub Compare API to detect added/modified/removed/renamed files, downloads only changed files via Contents API, and falls back to full archive when >100 files changed or any download fails
- Added `concurrent_backup_and_download()` using curl_multi to overlap archive download (network I/O) with backup creation (local disk I/O), reducing total pipeline time
- Wired both features into `deploy()` with proper fallback chain: concurrent backup+download → incremental sync → full archive extraction

## Task Commits

Each task was committed atomically:

1. **Task 1: Incremental sync via GitHub Compare API** - `050af4a` (feat)
2. **Task 2: Concurrent backup and download via curl_multi** - `8d34408` (feat)

## Files Created/Modified
- `core/class-deployment-manager.php` — Added `sync_incremental()` (Compare API file classification + per-file download), `concurrent_backup_and_download()` (curl_multi concurrent I/O); rewired `deploy()` with concurrent pipeline + incremental sync path
- `core/class-github-api.php` — Added `download_file_content()` (Contents API with raw Accept header), `get_token_for_curl()` (token accessor for curl_multi auth headers)

## Decisions Made
- Incremental sync uses Compare API-provided file status lists (not local hash comparison) — avoids Pitfall 3 line-ending hash mismatches on Windows servers
- Fallback threshold at 100 changed files — beyond that, N individual API calls cost more than a single archive download
- curl_multi justified because WordPress HTTP API cannot do concurrent requests (per FEATURES.md analysis)
- download_file_content() uses `Accept: application/vnd.github.v3.raw` header — returns raw content directly, avoiding base64 decode overhead

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None

## Known Stubs
None — all methods are fully wired with real GitHub API calls, curl_multi handles, and file I/O operations.

## Threat Flags

| Flag | File | Description |
|------|------|-------------|
| threat_flag: network-endpoint | core/class-github-api.php | download_file_content() makes HTTP requests to GitHub Contents API per changed file — uses authenticated token, HTTPS transport |
| threat_flag: auth-exposure | core/class-github-api.php | get_token_for_curl() exposes raw token for curl_multi headers — necessary because curl_multi bypasses wp_remote_get |
| threat_flag: file-write | core/class-deployment-manager.php | sync_incremental() writes downloaded file contents to temp_dir — paths derived from GitHub Compare API response, written to isolated temp_dir only |

All three flags are within the plan's threat model (T-02-03, T-02-04, T-02-05) with accepted/mitigated dispositions.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Incremental sync is operational — deployments with small diffs will only sync changed files
- Concurrent backup+download reduces pipeline time by overlapping I/O
- Both features fall back gracefully to full archive download on failure
- Phase 2 complete — all PERF requirements (PERF-01, PERF-02, PERF-03) satisfied

## Self-Check: PASSED

- [x] `sync_incremental()` exists in class-deployment-manager.php
- [x] `concurrent_backup_and_download()` exists in class-deployment-manager.php
- [x] `download_file_content()` exists in class-github-api.php
- [x] `get_token_for_curl()` exists in class-github-api.php
- [x] `curl_multi` used in class-deployment-manager.php
- [x] `compare_commits` called in class-deployment-manager.php
- [x] `comparing` status set before incremental attempt
- [x] Both files pass `php -l` syntax check
- [x] Commit 050af4a found in git log
- [x] Commit 8d34408 found in git log
- [x] SUMMARY.md exists at plan path

---
*Phase: 02-performance*
*Completed: 2026-05-10*
