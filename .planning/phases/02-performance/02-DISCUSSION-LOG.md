# Phase 2: Performance - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-05-10
**Phase:** 02-performance
**Areas discussed:** Memory-safe extraction approach

---

## Memory-safe extraction approach

| Option | Description | Selected |
|--------|-------------|----------|
| Entry-by-entry via getStream() | Process one file at a time using ZipArchive::getStream() + stream_copy_to_stream(). Prevents memory exhaustion. Slightly slower but safe for any plugin size. | ✓ |
| Chunked extractTo() | Use ZipArchive::extractTo() but in chunks by directory level. Simpler code but less memory-safe. | |
| You decide | Let the agent choose the best approach based on research. | |

**User's choice:** Entry-by-entry via getStream() (Recommended)
**Notes:** Standard approach regardless of plugin size — no special handling for very large plugins.

## Large plugins

| Option | Description | Selected |
|--------|-------------|----------|
| Standard entry-by-entry | Same approach regardless of size. getStream() processes one file at a time so memory stays constant. | ✓ |
| Progress reporting | Track extraction progress (files extracted / total) for admin UI feedback. | |
| You decide | Let the agent determine if special handling is needed. | |

**User's choice:** Standard entry-by-entry (Recommended)
**Notes:** No special handling needed for very large plugins.

## Agent's Discretion

- Incremental sync threshold
- Concurrent pipeline scope
- Incremental sync failure handling

## Deferred Ideas

None
