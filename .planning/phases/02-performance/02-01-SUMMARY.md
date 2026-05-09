---
phase: 02-performance
plan: 01
subsystem: core
tags: [zip-extraction, memory-safety, github-api, compare-api, stream-copy, pitfall-12, pitfall-10]

# Dependency graph
requires:
  - phase: 01-safety-foundation
    plan: 02
    provides: "Atomic swap temp directory pattern (WP_CONTENT_DIR/upgrade/)"
provides:
  - "Memory-safe entry-by-entry ZIP extraction via getStream() + stream_copy_to_stream()"
  - "GitHub Compare API client method (compare_commits) for incremental sync"
  - "Refactored deploy() using extract_to_entry_by_entry() instead of extractTo()"
affects: [02-performance, deployment, incremental-sync]

# Tech tracking
tech-stack:
  added: []
  patterns: [entry-by-entry-zip-extraction, stream-copy-to-stream, github-compare-api]

key-files:
  created: []
  modified:
    - core/class-deployment-manager.php
    - core/class-github-api.php

key-decisions:
  - "Entry-by-entry extraction uses getStream() + stream_copy_to_stream() — processes one file at a time instead of loading entire ZIP into memory"
  - "compare_commits() delegates to existing request() method — single line, no caching (caching deferred to incremental sync plan)"
  - "extract_to_entry_by_entry() is private — internal implementation detail, not exposed to other classes"

patterns-established:
  - "Memory-safe ZIP extraction: iterate with getNameIndex(), skip directories, stream each entry to disk individually"
  - "GitHub Compare API: GET /repos/{owner}/{repo}/compare/{base}...{head} returning file-level diff with status"

requirements-completed: [PERF-01, PERF-02]

# Metrics
duration: 1min
completed: 2026-05-10
---

# Phase 2 Plan 1: Memory-Safe Extraction + GitHub Compare API Summary

**Entry-by-entry ZIP extraction via getStream() replacing memory-bomb extractTo(), and GitHub Compare API method for incremental sync**

## Performance

- **Duration:** 1 min
- **Started:** 2026-05-09T22:19:09Z
- **Completed:** 2026-05-09T22:19:59Z
- **Tasks:** 1
- **Files modified:** 2

## Accomplishments
- Replaced `ZipArchive::extractTo()` with `extract_to_entry_by_entry()` that streams one file at a time via `getStream()` + `stream_copy_to_stream()`, preventing memory exhaustion on large plugins (Pitfall 12)
- Added `compare_commits()` method to GitHub_API for the GitHub Compare API endpoint (`GET /repos/{owner}/{repo}/compare/{base}...{head}`), enabling incremental sync in Plan 02
- Both files pass `php -l` syntax validation

## Task Commits

Each task was committed atomically:

1. **Task 1: Memory-safe extraction + GitHub Compare API method** - `a18b4f7` (feat)

**Plan metadata:** *(pending — final docs commit)*

## Files Created/Modified
- `core/class-deployment-manager.php` — Added `extract_to_entry_by_entry()` private method (getStream + stream_copy_to_stream per entry); refactored deploy() to call it instead of `extractTo()`
- `core/class-github-api.php` — Added `compare_commits()` public method using `$this->request('GET', ...)`

## Decisions Made
- Entry-by-entry extraction uses `getStream()` + `stream_copy_to_stream()` — processes one file at a time to avoid loading entire ZIP into memory
- `compare_commits()` is a thin wrapper over the existing `request()` method — consistent with other GitHub API methods in the class
- No caching added to `compare_commits()` — will be addressed in the incremental sync plan (02-02) where caching strategy is designed

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None

## Known Stubs
None — `extract_to_entry_by_entry()` is fully wired into deploy() and replaces all extractTo() calls. `compare_commits()` delegates to the existing request infrastructure.

## Threat Flags

| Flag | File | Description |
|------|------|-------------|
| threat_flag: zip-extraction | core/class-deployment-manager.php | Entry-by-entry extraction writes files from ZIP entries to filesystem — paths validated by dirname() normalization and wp_mkdir_p() |
| threat_flag: github-api-method | core/class-github-api.php | New API endpoint method (compare_commits) — uses authenticated token, returns file metadata only |

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Memory-safe extraction is in place — large plugins can be deployed without memory exhaustion
- `compare_commits()` available for the incremental sync implementation (Plan 02-02)
- Pitfall 12 (memory) and Pitfall 10 (zip slip) mitigated — entry-by-entry extraction contains paths within dest_dir

## Self-Check: PASSED

- [x] `extract_to_entry_by_entry()` exists in class-deployment-manager.php (method definition + call in deploy())
- [x] `compare_commits()` exists in class-github-api.php
- [x] No remaining `extractTo()` calls in deploy() (only in docblock comment explaining replacement)
- [x] Both files pass `php -l` syntax check
- [x] Commit a18b4f7 found in git log
- [x] SUMMARY.md exists at plan path

---
*Phase: 02-performance*
*Completed: 2026-05-10*
