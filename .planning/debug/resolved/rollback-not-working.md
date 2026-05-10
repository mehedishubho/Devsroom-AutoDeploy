---
status: diagnosed
trigger: "Diagnose why automatic rollback on failed deployment (PHP syntax errors) is not working."
created: 2026-05-10T15:45:00+06:00
updated: 2026-05-10T16:00:00+06:00
---

## Current Focus

hypothesis: CONFIRMED — verify_deployment() only checks the MAIN plugin file for PHP syntax errors. Syntax errors in any other PHP file pass verification silently, deployment succeeds, no rollback triggers.
test: Traced full verify_deployment() code path — token_get_all runs on line 1408 on $main_file only (line 1380). No recursive scanning.
expecting: Root cause confirmed
next_action: Return diagnosis

## Symptoms

expected: Deployment with PHP syntax errors auto-rolls back to previous version
actual: User reported: no
errors: None reported
reproduction: Test 7 in UAT
started: Discovered during UAT on 2026-05-10

## Eliminated

- hypothesis: verify_deployment() is not called after atomic swap
  evidence: Line 473 explicitly calls verify_deployment() after copy_directory completes and directory-emptiness check passes
  timestamp: 2026-05-10T15:50:00+06:00

- hypothesis: token_get_all() is not used for syntax checking
  evidence: Line 1408 uses token_get_all($code, TOKEN_PARSE) which throws PhpToken\ParseError on syntax errors
  timestamp: 2026-05-10T15:50:00+06:00

- hypothesis: Rollback code path is unreachable (early returns skip it)
  evidence: Lines 476-504 are directly after verify_deployment() call, no early returns between them. Rollback block at line 476 checks !verification['success'] and returns after rollback.
  timestamp: 2026-05-10T15:50:00+06:00

- hypothesis: .old directory is cleaned up before rollback can use it
  evidence: .old is only cleaned up at line 508-509 (success path) or by the finally block (line 575). In rollback path, .old is used at lines 485-490 BEFORE any cleanup.
  timestamp: 2026-05-10T15:50:00+06:00

- hypothesis: Security scanner catches syntax errors
  evidence: Security_Scanner (class-security-scanner.php) has no token_get_all, syntax, or lint references. It scans for malicious patterns, not syntax errors.
  timestamp: 2026-05-10T15:58:00+06:00

## Evidence

- timestamp: 2026-05-10T15:50:00+06:00
  checked: verify_deployment() method (lines 1373-1455)
  found: Only checks ONE file — `$plugin_path . '/' . $plugin_slug . '.php'` (line 1380). Falls back to find_plugin_main_file() which also scans only root-level PHP files (glob pattern `*.php`). token_get_all() syntax check runs on this single file only (line 1408).
  implication: If syntax error is in ANY file other than the main plugin file (e.g., core/class-foo.php, includes/helper.php), verification PASSES and no rollback triggers. This is the PRIMARY ROOT CAUSE.

- timestamp: 2026-05-10T15:51:00+06:00
  checked: Rollback code (lines 476-504) on Windows
  found: On Windows, @rename($plugin_path, $failed_path) at line 482 can fail (files locked by web server). When it fails, $plugin_path still exists. Then @rename($old_path, $plugin_path) at line 486 also fails (target exists). Falls to copy_directory($old_path, $plugin_path) which OVERWRITES but doesn't clean target first — broken files not in .old version remain.
  implication: Even when rollback IS triggered, Windows fallback produces a merged (broken+old) directory instead of a clean restore. This is a SECONDARY ISSUE.

- timestamp: 2026-05-10T15:52:00+06:00
  checked: Security scan section (lines 369-393)
  found: Security scan checks $extracted_dir (temp) for malicious patterns. On failure, sends alert notification but does NOT block deployment and does NOT trigger rollback.
  implication: Security scan failure is informational only — not related to syntax verification.

- timestamp: 2026-05-10T15:53:00+06:00
  checked: find_plugin_main_file() method (lines 1345-1360)
  found: Scans only root-level PHP files: glob($plugin_path . '/*.php'). Does NOT scan subdirectories.
  implication: Even the fallback file-finding logic only looks at root files — subdirectory PHP files are completely unverified.

- timestamp: 2026-05-10T15:58:00+06:00
  checked: Security_Scanner class (class-security-scanner.php)
  found: No references to token_get_all, syntax, or PHP lint. Only scans for malicious code patterns (eval, base64_decode, etc.).
  implication: Security scanner cannot compensate for missing syntax verification.

## Resolution

root_cause: verify_deployment() at line 1380 only checks the main plugin file (`$plugin_path . '/' . $plugin_slug . '.php'`) for PHP syntax errors via token_get_all() at line 1408. A realistic "bad deploy" with syntax errors in any secondary PHP file (core/*, includes/*, lib/*, etc.) passes verification silently — deployment is marked "success" and no rollback triggers. The method should recursively scan ALL PHP files in the plugin directory. Additionally, the Windows rollback fallback at line 488 uses copy_directory() without first cleaning the target directory, so even when rollback IS triggered, the broken files may remain merged with old files.
fix: 
verification: 
files_changed: []
