# UI Review: Phase 1 — Safety Foundation

**Audited:** 2026-05-10
**Scope:** Admin UI for deployment locking, force-unlock, pipeline status visualization
**Overall Score:** 20/24

---

## Pillar Scores

| Pillar | Score | Summary |
|--------|-------|---------|
| Copywriting | 3/4 | Clear labels, good i18n, minor terminology gaps |
| Visuals | 3/4 | Consistent icons, inline style pollution |
| Color | 4/4 | Excellent token system, strong status palette |
| Typography | 3/4 | Good hierarchy, minor weight inconsistencies |
| Spacing | 4/4 | Consistent scale, responsive breakpoints |
| Experience Design | 3/4 | Solid interactions, crude auto-refresh |

---

## 1. Copywriting — 3/4

**Strengths:**
- All user-facing strings wrapped in `__()` / `esc_html_e()` for i18n — `repository-form.php:33`, `deployment-list.php:51`
- Error messages are specific and actionable: "Invalid plugin slug format. Use only lowercase letters, numbers, and hyphens." — `repository-form.php:61`
- Lock indicator tooltip includes timestamp: "Locked since %s" — `repository-form.php:267`
- Confirmation prompts are clear: "Are you sure you want to force-unlock this repository?" — `repository-form.php:302`

**Findings:**

| Severity | File | Line | Issue |
|----------|------|------|-------|
| Low | repository-form.php | 279 | "Pull Update" vs "Deploy Now" — users may not understand when each appears. Consider tooltip explaining the distinction. |
| Low | repository-form.php | 42 | `force_unlocked` notice says "Deployment lock cleared." — could add context like "You can now retry deployment." |
| Low | dashboard.php | 87 | Empty state CTA says "Connect a GitHub repository" but the form says "Add Repository" — inconsistent framing. |

**Recommendations:**
- Add helper text explaining when "Pull Update" appears vs "Deploy Now"
- Consider adding a brief explanation of what "locking" means in the deployment context for new users

---

## 2. Visuals — 3/4

**Strengths:**
- Dashicons used consistently throughout: lock (`dashicons-lock`), unlock (`dashicons-unlock`), branch (`dashicons-admin-branch`), pipeline step icons
- Pipeline visualization (`deployment-single.php:79-104`) is a strong visual element with clear step icons
- Status badges use pill shape (`border-radius: 999px`) for visual consistency — `admin.css:223`
- Empty states use large dashicons with descriptive copy — `dashboard.php:84-91`

**Findings:**

| Severity | File | Line | Issue |
|----------|------|------|-------|
| Medium | repository-form.php | 146,254,293,303 | Inline `style="font-size: 14px; width: 14px; height: 14px;"` repeated 8+ times. Should be a CSS class. |
| Low | admin.css | 258-296 | v2.0 pipeline status colors use hardcoded hex values instead of CSS variables — breaks token consistency. |
| Low | deployment-single.php | 99 | Pipeline step icons are all generic dashicons. Consider custom SVG icons for deploy-specific steps (extract, verify). |

**Recommendations:**
- Extract inline dashicon sizing to `.devsroom-autodeploy .dashicons-sm { font-size: 14px; width: 14px; height: 14px; }`
- Add CSS variables for v2.0 pipeline status colors: `--ds-locking`, `--ds-comparing`, etc.
- Consider custom icons for pipeline steps to differentiate from generic WordPress UI

---

## 3. Color — 4/4

**Strengths:**
- CSS custom property token system is well-structured — `admin.css:11-42`
- Semantic color naming: `--ds-primary`, `--ds-success`, `--ds-danger`, `--ds-warning`, `--ds-info`
- Light variants for backgrounds: `--ds-success-light`, `--ds-danger-light`, etc.
- Each stat card uses a distinct color accent — `admin.css:173-192`
- Pipeline states have distinct colors: locking (purple), comparing (sky), downloading (blue), extracting (amber), deploying (pink), verifying (teal) — `admin.css:258-296`
- Good contrast: dark text on light backgrounds, white text on colored badges

**Findings:**

| Severity | File | Line | Issue |
|----------|------|------|-------|
| Low | admin.css | 258-296 | Pipeline status colors not in the token system — should add `--ds-locking: #7c3aed` etc. to `:root` |

**Recommendations:**
- Migrate pipeline status colors into the design token block for consistency

---

## 4. Typography — 3/4

**Strengths:**
- Clear hierarchy: h1 (28px/700), h2 (20px/600), h3 (16px/600) — `admin.css:198-210`
- Monospace for code elements: `SFMono-Regular, Consolas, Liberation Mono, Menlo` — `admin.css:740`
- Uppercase with letter-spacing for labels/badges: `font-size: 11px; text-transform: uppercase; letter-spacing: 0.03em` — `admin.css:226`
- Consistent base: `14px / 1.5` — `admin.css:40-41`

**Findings:**

| Severity | File | Line | Issue |
|----------|------|------|-------|
| Low | admin.css | 163 | Stat values use `font-weight: 800` — aggressive for numbers, consider 700 |
| Low | admin.css | Various | Text sizes use 11px, 12px, 13px, 14px — could consolidate to a type scale |
| Low | admin.css | 713 | Detail labels use `font-size: 12px` uppercase — slightly small for accessibility |

**Recommendations:**
- Define type scale tokens: `--ds-text-xs: 11px`, `--ds-text-sm: 13px`, `--ds-text-base: 14px`
- Consider bumping detail labels to 13px for better readability

---

## 5. Spacing — 4/4

**Strengths:**
- Consistent spacing scale: `--ds-space-1: 8px` through `--ds-space-6: 40px` — `admin.css:12-17`
- Proper use of `gap` in flex/grid layouts throughout
- Panel padding consistent at `--ds-space-4` (24px) — `admin.css:83`
- Responsive breakpoints at 1200px, 782px, 480px with appropriate layout adjustments — `admin.css:970-1037`
- Stats grid uses `minmax(180px, 1fr)` for flexible columns — `admin.css:130`
- Detail grid uses `repeat(auto-fit, minmax(200px, 1fr))` — `admin.css:704`

**Findings:**

| Severity | File | Line | Issue |
|----------|------|------|-------|
| Low | admin.css | 867-868 | Lock indicator padding (`2px 8px`) is tighter than status badges (`4px 10px`) — minor inconsistency |

**Recommendations:**
- Align lock indicator padding with status badge padding for visual consistency

---

## 6. Experience Design — 3/4

**Strengths:**
- Confirmation dialogs on all destructive actions (delete, deploy, force-unlock) — `admin.js:13-26`, `repository-form.php:289,302`
- Pipeline visualization with staggered animation — `admin.js:156-161`
- Copy-to-clipboard for commit hashes with "Copied!" feedback — `admin.js:77-95`
- Repository search/filter with real-time filtering — `admin.js:127-135`
- Tab navigation for settings page — `admin.js:138-147`
- Log context toggle for expandable details — `admin.js:150-153`
- Auto-refresh for in-progress deployments — `admin.js:70-74`
- Dismissible notices — `admin.js:164-168`

**Findings:**

| Severity | File | Line | Issue |
|----------|------|------|-------|
| Medium | admin.js | 70-74 | Auto-refresh uses `location.reload()` every 15s — full page reload is jarring. Consider AJAX polling or WebSocket. |
| Medium | admin.js | 164-168 | Auto-dismiss notices after 5s may hide important success/error messages before user reads them. |
| Low | admin.js | 29-59 | AJAX deploy handler exists but isn't wired to any button (no `.ajax-deploy` class used in templates). Dead code. |
| Low | repository-form.php | 275-307 | Actions column has 4+ inline forms — visually crowded on narrow screens. Consider a dropdown menu. |
| Low | deployment-single.php | 250-254 | Log context row uses `style="display: none;"` inline — should use CSS class. |

**Recommendations:**
- Replace full-page auto-refresh with AJAX polling for deployment status updates
- Increase notice auto-dismiss to 8-10s or remove auto-dismiss for error notices
- Remove dead `.ajax-deploy` code or wire it to a button
- Consider a "More actions" dropdown for repository row actions

---

## Summary

The admin UI is well-built with a strong design token system, consistent spacing, and good color choices. The main areas for improvement are:
1. Inline style cleanup (dashicon sizing)
2. Auto-refresh mechanism (replace full reload with AJAX)
3. Pipeline status colors should join the token system
4. Actions column density on repository table

## UI REVIEW COMPLETE
