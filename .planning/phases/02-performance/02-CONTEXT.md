# Phase 2: Performance - Context

**Gathered:** 2026-05-10
**Status:** Ready for planning

<domain>
## Phase Boundary

Make deployments faster. This phase delivers: memory-safe entry-by-entry ZIP extraction (replacing memory-bomb `extractTo()`), incremental file sync via GitHub Compare API (download only changed files), and concurrent pipeline steps (backup + download overlap via `curl_multi`). The deployment pipeline in `Deployment_Manager::deploy()` is the primary integration point. Phase 1's atomic swap temp directory pattern (`WP_CONTENT_DIR/upgrade/`) is reused.

This phase does NOT add safety features — it makes the existing safe pipeline fast.

</domain>

<decisions>
## Implementation Decisions

### Memory-safe extraction
- **D-01:** Use `ZipArchive::getStream()` + `stream_copy_to_stream()` for entry-by-entry extraction. Process one file at a time to prevent memory exhaustion on large plugins. Standard approach regardless of plugin size — no special handling for very large plugins (1000+ files, 100MB+).

### Agent's Discretion
- **Incremental sync threshold:** Agent decides at what percentage of changed files to fall back to full archive download.
- **Concurrent pipeline scope:** Agent decides which pipeline steps can overlap beyond backup+download.
- **Incremental sync failure handling:** Agent decides retry vs fallback behavior when individual file downloads fail.

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Deployment pipeline (current implementation)
- `.planning/codebase/ARCHITECTURE.md` — Data flow for webhook, polling, and manual deployments; pipeline steps in `Deployment_Manager`
- `.planning/codebase/CONVENTIONS.md` — Singleton pattern, error handling patterns, return type conventions, logging framework
- `.planning/codebase/CONCERNS.md` — Performance concerns: full archive download, sequential pipeline, WP_Filesystem overhead

### Performance research
- `.planning/research/FEATURES.md` — Technical implementation notes for incremental sync, concurrent pipeline, memory-safe extraction
- `.planning/research/PITFALLS.md` — Pitfall 12 (memory exhaustion from extractTo()), Pitfall 10 (zip slip path validation), Pitfall 3 (line-ending hash mismatch)

### Phase 1 context (dependencies)
- `.planning/phases/01-safety-foundation/01-CONTEXT.md` — Atomic swap temp dir pattern, lock mechanism, verification pipeline

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `Deployment_Manager::deploy()` — Main pipeline to optimize; currently uses `ZipArchive::extractTo()` (memory-unsafe)
- `GitHub_API::request()` — HTTP client for GitHub REST API; can be extended for Compare API
- `Backup_Manager::create_backup()` — ZIP-based backup; can run concurrently with download
- `curl_multi` — PHP extension available for concurrent HTTP requests

### Established Patterns
- Singleton pattern on all core services
- `array|false` return type pattern
- `$wpdb->prepare()` for all queries
- `wp_remote_request()` for HTTP (but `curl_multi` justified for concurrent requests per research)

### Integration Points
- `Deployment_Manager::deploy()` — extraction happens after archive download
- `GitHub_API` — add `compare_commits()` method for Compare API
- `WP_CONTENT_DIR/upgrade/` — temp directory from Phase 1, same filesystem for atomic rename

</code_context>

<specifics>
## Specific Ideas

- Research warns that `ZipArchive::extractTo()` loads entire ZIP into memory on some PHP configurations (Pitfall 12). Entry-by-entry via `getStream()` is the safe alternative.
- Research warns that GitHub Compare API returns file-level diffs with `status` field (added/modified/removed/renamed) — use this instead of local hash comparison to avoid Pitfall 3 (line-ending hash mismatches on Windows).
- Research warns that `curl_multi` is justified because WordPress HTTP API has no concurrent request support. Use for overlapping backup I/O with download I/O.
- The existing temp directory pattern from Phase 1 (`WP_CONTENT_DIR/upgrade/`) ensures same-filesystem rename for atomic swap.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 02-performance*
*Context gathered: 2026-05-10*
