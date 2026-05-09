# Roadmap: Devsoom AutoDeploy

## Overview

Transform the working sequential deployment pipeline into a production-grade system. Phase 1 makes every deployment safe — no concurrent conflicts, no partial file states, automatic verification and rollback, and guaranteed cleanup. Phase 2 makes deployments fast — native PHP file ops, incremental sync via GitHub Compare API, and concurrent pipeline steps. Safety first, speed second.

## Phases

**Phase Numbering:**
- Integer phases (1, 2, 3): Planned milestone work
- Decimal phases (2.1, 2.2): Urgent insertions (marked with INSERTED)

Decimal phases appear between their surrounding integers in numeric order.

- [ ] **Phase 1: Safety Foundation** - Every deployment is safe — locking prevents conflicts, atomic swaps prevent partial states, verification catches broken deploys, rollback restores automatically, and errors leave no orphaned files
- [ ] **Phase 2: Performance** - Deployments complete faster through native PHP file ops, incremental file sync, and concurrent pipeline steps

## Phase Details

### Phase 1: Safety Foundation
**Goal**: Every deployment is safe — no concurrent conflicts, no partial file states on failure, and automatic recovery when things go wrong
**Depends on**: Nothing (first phase)
**Requirements**: SAFETY-01, SAFETY-02, SAFETY-03, SAFETY-04, SAFETY-05
**Success Criteria** (what must be TRUE):
  1. When a deployment is running for a plugin, a second concurrent deployment request to the same plugin is rejected (and logged), while deployments to different plugins proceed independently
  2. If a deployment fails at any point, the live plugin directory is never left in a broken state — either the new version is fully swapped in or the old version remains intact
  3. After a deployment completes the file swap, the system verifies the plugin is loadable (syntax check, header existence, file readability, OPcache cleared); if verification fails, the previous version is automatically restored from backup
  4. If the deployment process crashes or encounters an unrecoverable error, all temporary files and directories are cleaned up — no orphaned temp dirs remain
**Plans**: TBD

Plans:
- [ ] 01-01: TBD
- [ ] 01-02: TBD
- [ ] 01-03: TBD

### Phase 2: Performance
**Goal**: Deployments complete faster through optimized file operations, incremental syncing, and parallel execution
**Depends on**: Phase 1 (PERF-02 uses atomic swap temp directory from SAFETY-02)
**Requirements**: PERF-01, PERF-02, PERF-03
**Success Criteria** (what must be TRUE):
  1. File operations during deployment use native PHP `rename()` and `stream_copy_to_stream()` instead of WP_Filesystem recursive copy, with `ZipArchive::extractTo()` for memory-safe extraction and WP_Filesystem as fallback only
  2. When only a few files changed between versions, the system syncs only those changed files via GitHub Compare API (handling additions, modifications, and deletions) instead of downloading the full archive — falling back to full download when >50% of files changed or on first deploy
  3. Backup creation and GitHub archive download run concurrently using `curl_multi` for HTTP overlap with local backup I/O, reducing total pipeline time compared to sequential execution
**Plans**: TBD

Plans:
- [ ] 02-01: TBD
- [ ] 02-02: TBD
- [ ] 02-03: TBD

## Progress

**Execution Order:**
Phases execute in numeric order: 1 → 2

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 1. Safety Foundation | 0/3 | Not started | - |
| 2. Performance | 0/3 | Not started | - |
