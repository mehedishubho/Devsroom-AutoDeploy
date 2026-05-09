# Phase 1: Safety Foundation - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-05-10
**Phase:** 1-Safety Foundation
**Areas discussed:** Lock conflict behavior

---

## Lock conflict behavior

### Conflict behavior

| Option | Description | Selected |
|--------|-------------|----------|
| Reject with message | New request gets rejected immediately with a message. User can retry later. | ✓ |
| Queue it | New request gets queued and runs automatically after the current deploy finishes. Needs a queue table. | |
| Allow force override | New request replaces the running deployment. Dangerous — could corrupt files mid-swap. | |

**User's choice:** Reject with message (Recommended)
**Notes:** Simple, no queue infrastructure needed for v2.0. Queue is deferred to v3.0 (SCALE-02).

### Stale lock detection

| Option | Description | Selected |
|--------|-------------|----------|
| TTL-based expiry | Lock expires automatically after N minutes (default: 10). Simple, no admin intervention needed. | ✓ |
| Status-based detection | Lock stores deployment ID. If deployment is marked 'failed' or 'success', lock is stale. | |
| Both combined | TTL expiry + status check. Best of both. | |

**User's choice:** TTL-based expiry (Recommended)
**Notes:** Simpler implementation. Status-based detection adds complexity without significant benefit since TTL handles the crash case.

### Admin force unlock

| Option | Description | Selected |
|--------|-------------|----------|
| Yes, add force unlock | Add a 'Force Unlock' button on the repository page. | ✓ |
| No, automatic only | Rely entirely on automatic stale detection. | |

**User's choice:** Yes, add force unlock
**Notes:** Safety valve for edge cases. Admin should always have an override for operational issues.

### Lock storage

| Option | Description | Selected |
|--------|-------------|----------|
| Database columns | Add locked_at and locked_by columns to devsroom_repositories. Atomic UPDATE with WHERE. | ✓ |
| WordPress transients | Simpler but riskier: transients can be evicted, no atomic claim. | |
| File-based flock() | Auto-released on PHP crash. Doesn't work across servers. | |

**User's choice:** Database columns (Recommended)
**Notes:** Research flagged transients as deadlock-prone. Database columns survive across PHP processes and support atomic acquisition.

---

## Agent's Discretion

- Verification depth
- Rollback strategy
- Error recovery scope

## Deferred Ideas

None — discussion stayed within phase scope
