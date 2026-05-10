# UI Review: Phase 2 — Performance

**Audited:** 2026-05-10
**Scope:** Admin UI support for memory-safe extraction, GitHub Compare API, incremental sync, concurrent backup/download
**Overall Score:** 19/24

---

## Pillar Scores

| Pillar | Score | Summary |
|--------|-------|---------|
| Copywriting | 3/4 | Status labels adequate, missing sync-mode transparency |
| Visuals | 3/4 | Status badges work, no incremental sync indicator |
| Color | 4/4 | Token system holds, pipeline colors distinct |
| Typography | 3/4 | Unchanged from Phase 1 |
| Spacing | 4/4 | Unchanged from Phase 1 |
| Experience Design | 2/4 | No visibility into incremental vs full sync |

---

## Context

Phase 2 added three backend capabilities with **no direct UI changes**:
1. Memory-safe entry-by-entry ZIP extraction (`extract_to_entry_by_entry()`)
2. Incremental sync via GitHub Compare API (`sync_incremental()`)
3. Concurrent backup+download via curl_multi (`concurrent_backup_and_download()`)

The UI supports these through existing status badges (`comparing`, `downloading`, `extracting`) and pipeline visualization. This audit evaluates how well the existing UI surfaces Phase 2 behavior to users.

---

## 1. Copywriting — 3/4

**Strengths:**
- Pipeline status labels are descriptive: "Compare", "Download", "Extract" — `deployment-single.php:41-43`
- Status badge text uses `ucfirst(str_replace('_', ' ', ...))` for readable labels — `deployment-list.php:133`
- Log messages from deployment pipeline are clear and timestamped

**Findings:**

| Severity | File | Line | Issue |
|----------|------|------|-------|
| Medium | deployment-single.php | — | No indication of incremental vs full sync mode. User can't tell if deployment downloaded 3 files or 300. |
| Medium | deployment-list.php | — | No column or badge showing sync type (incremental/full). Duration column doesn't explain why some deploys are faster. |
| Low | dashboard.php | — | "Success Rate" stat doesn't contextualize what counts as success. No tooltip explaining the metric. |

**Recommendations:**
- Add a "Sync Mode" field to deployment detail: "Incremental (12 files)" or "Full archive"
- Show file count in deployment list or detail: "Changed files: 5" for incremental syncs
- Add tooltip to Success Rate explaining the calculation

---

## 2. Visuals — 3/4

**Strengths:**
- Status badges for `comparing`, `downloading`, `extracting` are visually distinct — `admin.css:263-276`
- Pipeline visualization includes all Phase 2 steps — `deployment-single.php:38-47`
- Pipeline step icons match the operation: search (compare), download, archive (extract)

**Findings:**

| Severity | File | Line | Issue |
|----------|------|------|-------|
| Medium | deployment-single.php | — | No visual distinction between incremental and full archive paths in pipeline. Both show identical steps. |
| Low | deployment-single.php | — | Pipeline doesn't show "concurrent" indicator when backup+download overlap. User can't see the optimization. |
| Low | admin.css | 263-276 | `comparing` and `downloading` badge colors are very similar (sky-700 vs blue-600) — may be hard to distinguish at a glance. |

**Recommendations:**
- Add a sync mode indicator badge in deployment detail: "Incremental" or "Full Archive"
- Differentiate `comparing` (sky) from `downloading` (blue) more clearly — consider using a different hue
- Show a "concurrent" indicator when backup overlaps with download

---

## 3. Color — 4/4

**Strengths:**
- All Phase 2 status colors are within the established palette
- `comparing` (#0369a1 on #e0f2fe), `downloading` (#2563eb on #dbeafe), `extracting` (#d97706 on #fef3c7) — good contrast ratios
- Pipeline step states (success/active/failed) use token colors consistently

**Findings:**

| Severity | File | Line | Issue |
|----------|------|------|-------|
| Low | admin.css | 263-267 | `comparing` and `downloading` are both blue-family — consider making `comparing` use a different hue for faster visual scanning |

**Recommendations:**
- Consider `--ds-comparing: #6366f1` (indigo) to differentiate from `--ds-downloading: #2563eb` (blue)

---

## 4. Typography — 3/4

No Phase 2 changes. Assessment inherited from Phase 1.

**Findings:** Same as Phase 1 — stat values at 800 weight, scattered text sizes.

---

## 5. Spacing — 4/4

No Phase 2 changes. Assessment inherited from Phase 1.

**Findings:** Same as Phase 1 — minor lock indicator padding inconsistency.

---

## 6. Experience Design — 2/4

**Strengths:**
- Pipeline visualization correctly shows Phase 2 steps (compare → download → extract)
- Auto-refresh (15s) will eventually show completion of incremental syncs
- Log context toggle allows drilling into deployment details

**Findings:**

| Severity | File | Line | Issue |
|----------|------|------|-------|
| High | deployment-single.php | — | No way for user to see which files were synced incrementally. The Compare API returns file-level diffs but none of this is exposed in the UI. |
| High | deployment-single.php | — | No progress indication during multi-file download. If 50 files are being downloaded one-by-one, user sees only "Downloading" badge with no progress. |
| Medium | deployment-list.php | — | Duration column doesn't explain variance. An incremental sync taking 2s vs a full archive taking 30s look the same — no context for why. |
| Medium | admin.js | 70-74 | Auto-refresh at 15s intervals may miss fast incremental syncs entirely. User refreshes and sees "Success" without understanding what happened. |
| Low | deployment-single.php | — | Fallback chain (incremental → full archive) is invisible. If incremental sync fails and falls back to full archive, user sees no indication. |

**Recommendations:**
- **High priority:** Add a "Changed Files" section to deployment detail showing files from Compare API response
- **High priority:** Add a progress indicator for multi-file downloads (e.g., "Downloading 12/47 files...")
- Add a "Sync Mode" field showing whether incremental or full archive was used
- Log fallback events visibly: "Incremental sync failed (102 files changed), falling back to full archive"
- Consider shorter refresh interval (5-10s) or AJAX polling for active deployments

---

## Summary

Phase 2's backend improvements (incremental sync, concurrent I/O, memory-safe extraction) are functionally solid but **invisible to the user**. The UI shows the same pipeline steps regardless of whether 3 files or 300 were synced. The highest-impact improvements would be:
1. Expose Compare API file diffs in deployment detail
2. Show sync mode (incremental vs full) and file count
3. Add download progress indicator for multi-file syncs

## UI REVIEW COMPLETE
