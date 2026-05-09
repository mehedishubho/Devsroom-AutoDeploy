# Domain Pitfalls

**Domain:** WordPress deployment pipeline (incremental sync, atomic swaps, rollback, concurrency)
**Researched:** 2026-05-10
**Confidence:** HIGH (derived from codebase analysis + deep PHP/WordPress/platform knowledge)

---

## Critical Pitfalls

Mistakes that cause data loss, site breakage, or silent corruption. Each includes the mechanism of failure, how to detect it early, and which project phase should address it.

---

### Pitfall 1: `rename()` Is NOT Atomic on Windows Across Volumes

**What goes wrong:** The project plans to use `rename($temp_dir, $plugin_path)` as an atomic swap. On POSIX (Linux), `rename()` is indeed atomic when source and destination are on the same filesystem. However, on Windows, `rename()` fails with "cross-device link" errors when the temp directory and the plugin directory are on different drives or volumes. This is extremely common on Windows hosting where `sys_get_temp_dir()` returns a path on `C:\` but `WP_PLUGIN_DIR` is on a different drive, or when `WP_CONTENT_DIR` has been mapped to a custom path. Even on the same drive, NTFS `rename()` can fail if any file inside the directory is open by another process (antivirus, PHP opcode cache, IIS worker).

**Why it happens:** PHP's `rename()` delegates to the OS `rename(2)` syscall. On Windows, this becomes `MoveFileExW`, which does not support cross-volume moves without the `MOVEFILE_COPY_ALLOWED` flag. PHP does not set this flag. The current codebase sets `temp_dir` via `get_temp_dir() . 'devsroom-autodeploy-' . $repository_id . '-' . time()` (line 214 of deployment-manager.php), which uses the system temp directory, not the plugin directory's volume.

**Consequences:** Deployment fails silently or throws a PHP warning. The old plugin directory may have already been deleted (current code does this at line 316). The new files remain stranded in temp. The live plugin is gone.

**Prevention:**
1. Always test the return value of `rename()`. It returns `false` on failure and emits `E_WARNING`.
2. Use the same filesystem for temp and target. Create the temp directory inside `WP_CONTENT_DIR` (e.g., `WP_CONTENT_DIR . '/upgrade/'`) rather than `sys_get_temp_dir()`.
3. Implement a fallback: if `rename()` fails, attempt a `copy()` + `unlink()` sequence.
4. On Windows, consider using `rename()` with error suppression (`@rename()`) followed by a manual copy fallback, or use the `wp_filesystem->move()` method which handles cross-volume moves internally.

**Detection (warning signs):**
- Deployments succeed on Linux hosting but fail on Windows/IIS.
- PHP error logs show "rename(...): The process cannot access the file" or "No such file or directory".
- Temp directories accumulate and are never cleaned up.
- After a failed deployment, the plugin directory is empty but temp directory has files.

**Phase:** Phase 1 (Atomic File Operations) -- this must be solved before any swap logic is written. The temp directory strategy must be decided first.

---

### Pitfall 2: Delete-Then-Copy Is Never Safe (Current Codebase Bug)

**What goes wrong:** The existing code at `class-deployment-manager.php` line 314-323 deletes the entire existing plugin directory first (`$wp_filesystem->delete($plugin_path, true)`), then copies new files into a freshly created directory. If the copy operation fails partway through (disk full, timeout, permission error on one file), the old working plugin is gone and the new plugin is partially deployed. The site breaks. This is the single most dangerous pattern in the current codebase and is explicitly called out in CONCERNS.md.

**Why it happens:** It feels simpler to delete first, then copy, because you do not have to handle file deletions of removed files or worry about stale files. But the moment between deletion and copy completion is a window of vulnerability. With the atomic swap approach (temp dir -> rename), this window is eliminated. The catch: the atomic swap approach must handle the case where the plugin directory does not yet exist (first deployment).

**Consequences:** Any transient failure during copy leaves the plugin broken. With 20+ plugins, the probability of at least one deployment failing during a batch approaches certainty. The current code marks the deployment as "success" even when `$wp_filesystem->copy()` fails (the return value is not checked at line 587).

**Prevention:**
1. Never delete the live plugin directory until the replacement is verified.
2. Deploy to a temp path adjacent to the target (`$plugin_path . '-new'`).
3. Verify the new deployment (file count, main plugin file exists, PHP syntax check).
4. Rename old to `$plugin_path . '-old'`, rename new to `$plugin_path`.
5. Only after the rename succeeds, delete `$plugin_path . '-old'`.
6. If rename fails, restore by renaming `$plugin_path . '-old'` back.

**Detection:**
- Deployments report "success" but the plugin is broken on the frontend.
- Plugin files are missing after a deployment.
- The plugin directory exists but is incomplete (some files present, others missing).

**Phase:** Phase 1 (Atomic File Operations) -- the foundational pattern change. Everything else builds on this.

---

### Pitfall 3: Incremental Sync Hash Mismatches Due to Line Endings

**What goes wrong:** When implementing incremental file sync via the GitHub Compare API, the code will compare local files against the GitHub diff to determine which files changed. GitHub API returns file-level diffs with `sha` hashes computed on the blob content. However, the blob hash uses Unix line endings (`\n`), while files on a Windows server may have been stored with `\r\n` line endings. If the local hash computation uses the file contents as-is (with `\r\n`), the hash will never match the GitHub blob hash, causing every file to be treated as "changed" and re-downloaded. This completely negates the benefit of incremental sync.

**Why it happens:** Git normalizes line endings based on `.gitattributes` and `core.autocrlf` settings, but the GitHub blob API returns the raw blob content (which uses whatever is stored in Git). PHP's `file_get_contents()` reads files with whatever line endings are on disk. `hash_file()` or `sha1_file()` operates on the raw bytes. If the WordPress server created or modified files with `\r\n`, the hashes diverge.

**Consequences:** Incremental sync falls back to downloading and replacing every file on every deployment, wasting bandwidth and time. With 20+ plugins, this is the exact problem incremental sync is meant to solve. Worse: the code might log "0 files changed" if hashes do match for unmodified files but miss modified ones due to the mismatch, silently deploying stale code.

**Prevention:**
1. Do NOT compute hashes locally to compare with GitHub blob SHAs. Instead, use the GitHub Compare API response directly: the API returns lists of `added`, `modified`, and `removed` files. Trust these lists rather than re-computing hashes.
2. If local hash comparison is needed (e.g., to verify the downloaded file), normalize line endings before hashing: `str_replace("\r\n", "\n", $content)`.
3. Use the `status` field from each file entry in the compare response (`"added"`, `"modified"`, `"removed"`, `"renamed"`) to determine actions.
4. Store the `last_commit_hash` (already done in the codebase) and use it as the `base` parameter in the compare call.

**Detection:**
- Incremental sync downloads 100% of files despite only 2-3 changing.
- Log entries show "hash mismatch" for files that should be unchanged.
- Deployment time does not decrease after implementing incremental sync.

**Phase:** Phase 2 (Incremental Sync) -- the compare API integration must be designed around API-provided file lists, not local hash comparison.

---

### Pitfall 4: Backup Corruption From Concurrent Directory Iteration

**What goes wrong:** The `Backup_Manager::create_backup()` method (lines 126-149) iterates the plugin directory using `RecursiveIteratorIterator` while adding files to a `ZipArchive`. If another process modifies the directory during iteration (another deployment running concurrently, or a plugin writing cache files), the iterator can encounter a file that was deleted between the directory listing and the `addFile()` call, causing `ZipArchive::addFile()` to fail. The ZIP archive is then incomplete or corrupted, but the code only checks if the file exists after `$zip->close()` (line 152), not whether the ZIP is valid.

**Why it happens:** PHP's `RecursiveIteratorIterator` reads directory entries lazily during iteration. Between the time the iterator yields a file path and the time `addFile()` reads it, another process can delete or modify it. This is a TOCTOU (time-of-check-time-of-use) race condition. The current codebase has zero concurrency protection (no locks, no transient checks).

**Consequences:** The backup ZIP is corrupt or incomplete. When a rollback is attempted, the restored plugin is missing files. The site breaks on rollback -- the safety net itself is broken.

**Prevention:**
1. Acquire a deployment lock BEFORE backup creation. Use a WordPress transient with a unique key per repository ID.
2. Validate the ZIP after creation by opening it and checking the file count matches what was expected.
3. Consider using `ZipArchive::addPattern()` or adding files from a snapshot (copy directory first, then ZIP the snapshot). This is slower but immune to concurrent modification.
4. At minimum, check `$zip->close()` return value (currently unchecked) and `$zip->status` for `ZipArchive::ER_*` error constants.

**Detection:**
- Backup files exist but are smaller than expected.
- `ZipArchive::open()` fails when trying to restore from backup.
- Restored plugin after rollback has fewer files than the original.
- Intermittent backup failures that cannot be reproduced consistently.

**Phase:** Phase 1 (Atomic Operations + Locking) -- backup integrity depends on concurrency control being in place first.

---

### Pitfall 5: WP-Cron Is Fundamentally Unsuitable for Queue Processing

**What goes wrong:** The project plans a "deployment queue system" for 20+ plugins. The natural WordPress approach is to use WP-Cron for this. But WP-Cron is not a real cron daemon -- it fires when a page is loaded (on `init`), not at precise times. Multiple WP-Cron events for the same hook can fire in parallel if requests overlap. There is no built-in locking mechanism. A "queue" built on WP-Cron will: (a) not process items when no traffic hits the site (deployments sit stuck in "pending"), (b) process the same item twice when requests overlap, (c) fail silently when PHP times out mid-queue.

**Why it happens:** WP-Cron uses `wp_next_scheduled()` and `wp_schedule_event()` which store timestamps in options. On `init`, WordPress checks if any scheduled time has passed and fires the hook. If two requests arrive simultaneously, both see the scheduled time has passed, both fire the hook. There is no atomic compare-and-swap. The `wp-cron.php` losemutex check (`if ( (time() - $lock) > 60 )`) only prevents cron from running more than once per minute, but multiple PHP processes can still acquire the lock simultaneously on loaded servers.

**Consequences:** Queue items processed twice (duplicate deployments), queue items never processed (no traffic), queue processing stops mid-way (PHP timeout) with no way to resume.

**Prevention:**
1. Do NOT use WP-Cron for queue processing. Use a custom database table as the queue, with a status field (`pending`, `processing`, `completed`, `failed`).
2. Process the queue using a single scheduled WP-Cron event that claims items by atomically updating their status from `pending` to `processing` with a `WHERE status = 'pending'` clause. The database row-level locking in MySQL ensures only one process claims each item.
3. For each queue item, set a `claimed_at` timestamp and a timeout. If `claimed_at` is older than N minutes and status is still `processing`, treat it as failed and re-queue or mark as timed out.
4. Consider recommending Action Scheduler (used by WooCommerce) for robust queue processing. It solves all these problems and is WordPress-native (no Composer dependency needed -- it can be included as a library). However, the project constraint of "no external libraries" may prevent this. The custom table approach works.
5. Alternatively, process the queue synchronously on the webhook/trigger request. For manual triggers, the admin page can long-poll for progress.

**Detection:**
- Deployments show as "pending" indefinitely on low-traffic sites.
- Same deployment runs twice with overlapping timestamps.
- Queue appears stuck after a PHP fatal error -- items remain in "processing" forever.
- WP-Cron events pile up in the options table.

**Phase:** Phase 3 (Queue System) -- the queue architecture must be decided before implementation. This is a design-level pitfall.

---

### Pitfall 6: GitHub API Rate Limit Exhaustion on Dashboard and Polling

**What goes wrong:** This is already a HIGH-severity issue in the current codebase (CONCERNS.md lines 88-101). The admin menu badge calls `check_for_updates()` on every admin page load, which makes N GitHub API calls for N repositories. With 20 repositories, that is 20 API calls per admin page load. The GitHub API rate limit is 5,000 requests per hour for authenticated users. At 20 repos and moderate admin activity (say 10 admin page loads per hour), that is 200 API calls per hour -- survivable. But during active development or admin-heavy sessions, this can spike to 1,000+ per hour. For unauthenticated API calls (if a token is missing or expired), the limit is 60 per hour, which is exhausted by 3 admin page loads with 20 repos.

Adding incremental sync makes this worse. The GitHub Compare API call (`GET /repos/:owner/:repo/compare/:base...:head`) counts as one API call per repository per deployment. Combined with polling and dashboard checks, the total API calls multiply.

**Why it happens:** No caching of API responses. Each code path that needs repository state independently calls the GitHub API.

**Consequences:** GitHub returns 403 responses. Deployments fail with "API rate limit exceeded". The dashboard shows stale or no data. The polling scheduler silently stops checking for updates.

**Prevention:**
1. Cache all GitHub API responses in WordPress transients with appropriate TTLs.
    - Latest commit hash: 5-minute TTL (balance between freshness and API budget).
    - Repository info: 1-hour TTL (rarely changes).
    - Compare results: 5-minute TTL.
2. Separate the "check for updates" logic from the "display dashboard" logic. The dashboard should always read from cache. Cache refresh should happen on a schedule, not on page load.
3. Batch API calls where possible. The GitHub API does not support batch commit checks, but you can use the `If-Modified-Since` or `If-None-Match` headers (GitHub supports ETags). A 304 response does not count against the rate limit.
4. Store the `X-RateLimit-Remaining` header value and log warnings when it drops below thresholds. Display it in the admin UI.
5. Implement exponential backoff when rate-limited. Do not immediately retry on 403.

**Detection:**
- GitHub API calls return 403 with "API rate limit exceeded" message.
- Dashboard takes 5+ seconds to load (network latency on each API call).
- Deployments fail intermittently, especially after polling runs.
- `X-RateLimit-Remaining` header shows 0.

**Phase:** Phase 2 (Incremental Sync) -- the API caching strategy must be implemented alongside the compare API integration. The existing dashboard API calls should also be cached as a prerequisite.

---

### Pitfall 7: Plugin Activation During Deployment Triggers Fatal Errors

**What goes wrong:** The manual deployment flow supports "Deploy & Activate". The current code activates the plugin after deployment. But if the deployment process replaces an active plugin's files while WordPress has already loaded the old plugin's classes into memory (opcode cache, autoloader), the next request may see a mix of old and new class definitions. More critically, if the new plugin version has a PHP syntax error or incompatible class signature, `activate_plugin()` will trigger a fatal error that can break the WordPress admin (white screen of death) because WordPress loads the plugin during activation to check if it can be activated.

**Why it happens:** `activate_plugin()` calls `include()` on the main plugin file to verify it loads without errors. If the file has a syntax error, PHP fatal errors. WordPress catches this in some cases (since 5.9 with `wp_opcache_invalidate()` calls), but older WordPress versions or certain opcode cache configurations can serve stale cached files.

**Consequences:** White screen of death on the admin. The plugin cannot be deactivated through the UI because the admin is broken. Recovery requires FTP/SSH access to manually rename the plugin directory.

**Prevention:**
1. Before activation, validate the main plugin file exists and passes `php_check_syntax()` or `lstat()` checks. Actually run `php -l` equivalent (PHP has no built-in syntax check function, but you can use `token_get_all()` on the file content and check for parse errors -- or simply `require` it in an isolated scope with output buffering and error handling).
2. Use `wp_opcache_invalidate()` on all changed PHP files after deployment to clear opcode caches.
3. Deactivate before deployment, deploy, verify, then re-activate. This is safer than activating a freshly deployed, unverified plugin.
4. Wrap `activate_plugin()` in error handling (register a shutdown function or use `set_error_handler` temporarily).
5. Consider making activation a separate, optional step that the user can trigger after verifying the deployment.

**Detection:**
- Admin returns a white screen after "Deploy & Activate".
- PHP error log shows "Fatal error: Class X not found" or "Cannot redeclare class Y".
- `is_plugin_active()` returns true but the plugin's functionality is broken.
- OPcache statistics show stale files.

**Phase:** Phase 1 (Atomic Operations) -- the post-deploy verification step should include PHP syntax validation before activation.

---

### Pitfall 8: Temp File Cleanup Fails Silently, Exhausting Disk Space

**What goes wrong:** The `cleanup_temp_dir()` method (lines 598-616) iterates directories and deletes files. If any file within the temp directory has permissions that prevent deletion (e.g., created by a different PHP process running as a different user, or locked by an antivirus scan), `unlink()` fails but the error is silently ignored. The method continues to `rmdir()` the parent directory, which also fails because it is not empty. The temp directory remains, consuming disk space. Over time, with 20+ plugins deploying multiple times per day, temp directories accumulate.

Additionally, if the deployment process crashes (PHP fatal error, memory exhaustion, server kill) before reaching the `cleanup_temp_dir()` call, the temp directory is permanently orphaned. There is no cron job or fallback to clean up orphaned temp directories.

**Why it happens:** PHP's `unlink()` returns `false` on failure but does not throw. The code does not check return values. `rmdir()` fails on non-empty directories. There is no finally-block or register_shutdown_function to guarantee cleanup.

**Prevention:**
1. Wrap the entire deployment in a try/finally block where the `finally` always attempts cleanup.
2. Register a `register_shutdown_function()` at the start of deployment that cleans up the temp directory if the deployment ID is still in a "processing" state.
3. Check `unlink()` return values and log failures.
4. Add a scheduled cleanup WP-Cron event (daily) that scans the temp directory pattern (`devsroom-autodeploy-*`) for directories older than 1 hour and forcibly removes them.
5. Use `wp_tempnam()` or create temp directories inside `WP_CONTENT_DIR` where permissions are guaranteed to be writable, rather than in the system temp directory.

**Detection:**
- `sys_get_temp_dir()` or `WP_CONTENT_DIR/upgrade/` fills with `devsroom-autodeploy-*` directories.
- Disk space alerts on the server.
- Deployment temp directories contain stale files from days ago.
- Deployment logs show cleanup at the end, but the directory still exists.

**Phase:** Phase 1 (Atomic Operations + Error Recovery) -- cleanup is part of the error recovery story.

---

## Moderate Pitfalls

---

### Pitfall 9: Deployment Lock via Transient Can Deadlock

**What goes wrong:** The planned deployment lock will likely use WordPress transients (`set_transient()`). If the deployment process crashes or times out without clearing the transient, subsequent deployments for the same repository will be permanently blocked. The transient TTL is the only fallback, and if it is set too high (e.g., 1 hour), legitimate deployments are blocked for that duration. If set too low (e.g., 30 seconds), long-running deployments lose their lock and concurrent deployments proceed.

**Why it happens:** Transients are stored in `wp_options` (or the object cache). There is no process-level ownership. PHP processes cannot detect that the process that set the lock is dead. The transient value is just a string -- there is no PID or heartbeat to verify the locking process is still alive.

**Prevention:**
1. Store both the lock value and a timestamp. The lock value should include a unique deployment ID. When checking the lock, if the deployment ID in the lock matches a deployment that is already completed or failed, the lock is stale and should be overridden.
2. Use a reasonable TTL (5-10 minutes for deployments) combined with a heartbeat. During long deployments, refresh the transient TTL periodically (every 30 seconds).
3. Implement a force-unlock mechanism in the admin UI. If a deployment is stuck, the admin can manually clear the lock.
4. Alternative: use `flock()` with a lock file instead of transients. `flock()` is automatically released when the PHP process exits (even on crash). However, `flock()` does not work across multiple servers (each server has its own filesystem). For single-server WordPress sites, `flock()` is more reliable.

**Detection:**
- Deployments consistently fail with "deployment already in progress" for the same repository.
- The lock transient exists but the deployment it refers to is in "failed" or "completed" status.
- Admin cannot deploy manually without clearing the lock first.
- Lock TTL is set but the deployment takes longer than the TTL.

**Phase:** Phase 1 (Deployment Locking) -- design the locking mechanism with deadlock prevention from the start.

---

### Pitfall 10: ZipArchive::extractTo Does Not Validate Paths (Zip Slip)

**What goes wrong:** The current code at line 263 calls `$zip->extractTo($temp_dir)` without validating that extracted file paths remain within `$temp_dir`. A maliciously crafted ZIP archive could contain files with paths like `../../wp-config.php` that extract outside the intended directory. GitHub-generated archives are safe, but if the repository is compromised, or if the archive download is intercepted (MITM), the ZIP could be replaced with a crafted one.

**Why it happens:** `ZipArchive::extractTo()` does not perform path validation. It extracts files wherever the path inside the ZIP points to. PHP versions 8.0.2+ have `ZipArchive::registerProgressCallback` but no built-in path validation for extraction.

**Prevention:**
1. After extraction, iterate all extracted files and verify their `realpath()` starts with `$temp_dir`. Delete any files that escaped the target directory.
2. Alternatively, do not use `extractTo()`. Instead, iterate the ZIP entries manually with `ZipArchive::getFromIndex()` and write each file explicitly, validating the path before writing.
3. Verify the downloaded archive hash matches what GitHub reported (GitHub provides `Content-Length` and sometimes `ETag` headers).

**Detection:**
- Files appear outside the temp directory after extraction.
- Unexpected files in WordPress root or wp-content directory.
- Archive file size differs significantly from expected size.

**Phase:** Phase 1 (Atomic Operations) -- security hardening during the extraction step.

---

### Pitfall 11: Incremental Sync Leaves Stale Files That Were Deleted in Git

**What goes wrong:** When implementing incremental sync, it is natural to focus on downloading changed files and adding new ones. But Git also tracks file deletions. If a file was removed in the new commit, the incremental sync must also delete it from the plugin directory. If the sync only adds and modifies files but never removes deleted ones, the deployed plugin accumulates orphaned files from previous versions. These can cause conflicts (old class definitions loaded by an autoloader), security issues (removed security patches still present), or runtime errors (code expecting the new architecture finds old files).

**Why it happens:** The GitHub Compare API returns a `removed` array in its response, but it is easy to overlook this field when implementing sync. The brain naturally focuses on "what changed" not "what was removed". The current full-replace deployment avoids this by deleting everything first.

**Consequences:** Old class files conflict with new ones. PHP autoloader loads stale class definitions. Security vulnerabilities persist because the file that was supposed to be removed is still being loaded. The plugin behaves unpredictably.

**Prevention:**
1. Parse ALL three arrays from the GitHub Compare API response: `files[].status == "added"`, `files[].status == "modified"`, and `files[].status == "removed"`.
2. For removed files, delete them from the target plugin directory.
3. After sync, verify the file count matches expectations (total files in new version = total in old - removed + added).
4. Log all file operations (added, modified, removed) for debugging.

**Detection:**
- After incremental deployment, files that should have been deleted still exist.
- Plugin shows old behavior alongside new features.
- PHP error log shows "Cannot redeclare class" or "Class method not found".
- File count in deployed plugin does not match the Git tree.

**Phase:** Phase 2 (Incremental Sync) -- the sync logic must handle all three operations (add, modify, delete).

---

### Pitfall 12: Large Plugin Deployments Exceed PHP Memory Limit

**What goes wrong:** The current code loads the entire ZIP archive into memory during extraction (via `ZipArchive`). For large plugins (WooCommerce is 50+ MB, some page builder plugins exceed 100 MB), the ZIP extraction can exceed PHP's `memory_limit`. The `download_archive()` method uses `wp_remote_get()` with `'stream' => true`, which writes to disk (good), but `ZipArchive::extractTo()` decompresses into memory before writing to disk. Additionally, the `RecursiveIteratorIterator` used for copying (`copy_directory()`) loads file paths into memory. For a plugin with 10,000+ files, this can consume significant memory.

**Why it happens:** `ZipArchive::extractTo()` is a black box that decompresses in memory. PHP's default `memory_limit` is often 128MB or 256MB on shared hosting. Large ZIP files with many small files (common in WordPress plugins with vendor directories) create overhead far beyond the raw file size due to PHP data structures.

**Consequences:** PHP fatal error: "Allowed memory size of N bytes exhausted". The deployment fails mid-way. The temp directory is partially extracted. If this happens after the old plugin is deleted (current code pattern), the site breaks.

**Prevention:**
1. Use `ZipArchive` entry-by-entry extraction instead of `extractTo()`. Iterate with `getNameIndex()` and extract each file individually with `getStream()`. This processes one file at a time instead of loading everything into memory.
2. Increase the PHP memory limit for the deployment process only: `ini_set('memory_limit', '512M')` at the start of deployment, restoring the original value after.
3. For the `copy_directory()` method, do not collect all file paths into an array. Process files one at a time during iteration.
4. Monitor memory usage during deployment with `memory_get_usage()` and abort gracefully if approaching the limit.

**Detection:**
- Deployments of large plugins fail with PHP fatal errors.
- Error log shows "Allowed memory size exhausted" during deployment.
- Small plugins deploy fine, large ones consistently fail.
- Server monitoring shows memory spikes during deployment.

**Phase:** Phase 1 (Optimized File Operations) -- memory-safe file operations are a prerequisite for reliable deployment.

---

### Pitfall 13: Backup Restore Overwrites Without Verifying the Backup Integrity

**What goes wrong:** The `restore_backup()` method (lines 187-217 of backup-manager.php) deletes the current plugin directory, then extracts the backup ZIP. It does NOT verify the backup ZIP is valid before deleting the current directory. If the backup is corrupted (see Pitfall 4), the restore makes things worse: the working (but problematic) deployment is deleted and replaced with a corrupt backup. There is now no working version at all.

**Why it happens:** The restore path is an emergency operation that is rarely tested. It assumes the backup is valid because it was created successfully. But backup corruption can happen at any time: disk errors, partial writes, concurrent modification during backup creation.

**Prevention:**
1. Before deleting anything during restore, validate the backup ZIP by opening it with `ZipArchive::open()` and checking the file count.
2. Extract to a temp directory first, verify the main plugin file exists and passes PHP syntax check, THEN swap.
3. Always keep the current (possibly broken) state in a separate directory until the restore is verified. Use the same atomic swap pattern as deployment.
4. Implement backup verification as a separate step that can be triggered from the admin UI.

**Detection:**
- Restore operation completes but the plugin is still broken.
- Restored plugin directory has fewer files than expected.
- `ZipArchive::open()` fails during restore attempt.

**Phase:** Phase 1 (Rollback Mechanism) -- rollback must be as safe as deployment, using the same atomic swap pattern.

---

### Pitfall 14: File Permission Drift After Multiple Deployments

**What goes wrong:** Each deployment creates new files and directories. On Linux, the file permissions are determined by the PHP process's umask and the permissions set during `mkdir()` and `copy()`. The current code uses `wp_mkdir_p()` (which creates directories with 0755 permissions) and `$wp_filesystem->copy()` (which typically copies with 0644 for files). However, if the deployment switches to using native PHP `rename()` or `copy()`, the permissions may differ from what WordPress expects. After multiple deployments, file permissions can drift: some files owned by `www-data`, others by the FTP user, some writable by group, others not. WordPress auto-updates or other plugins may fail to write to directories that the deployment created with restrictive permissions.

**Why it happens:** PHP's `rename()` preserves the permissions of the source file. If the temp directory was created with restrictive permissions (e.g., 0700), the renamed directory retains those permissions. `wp_mkdir_p()` uses 0755 by default, but native `mkdir()` uses the current umask. The difference is significant: 0755 allows group/other read+execute, while 0700 restricts to owner only. If PHP runs as `www-data` but WordPress needs the FTP user to access files (common on shared hosting), 0700 breaks access.

**Consequences:** WordPress cannot auto-update plugins. Other plugins cannot write to cache directories. Admin file editor fails with "cannot write". SSH/FTP access to plugin files is denied because the web server user owns them with restrictive permissions.

**Prevention:**
1. After deployment, explicitly set permissions using `chmod()` on the plugin directory. Use WordPress conventions: directories to 0755, files to 0644.
2. Use `wp_mkdir_p()` consistently (which sets 0755) rather than native `mkdir()`.
3. After the atomic rename, walk the directory tree and set correct permissions. This is a one-time cost per deployment.
4. Document the expected permission model and make it configurable for non-standard hosting environments.
5. Test on both Apache (mod_php, running as www-data) and PHP-FPM (running as the site user) configurations.

**Detection:**
- WordPress shows "Could not create directory" during its own auto-updates.
- Plugin cannot write to its own cache/log directories.
- `ls -la` shows files owned by unexpected users or with unexpected permissions.
- FTP/SSH users cannot modify deployed plugin files.

**Phase:** Phase 1 (Atomic Operations) -- permission handling must be part of the post-swap verification.

---

## Minor Pitfalls

---

### Pitfall 15: `get_temp_dir()` Returns Writable but Non-Executable Path

**What goes wrong:** On some hosting environments, the system temp directory (`/tmp`) is mounted with `noexec`. Files extracted there cannot be executed. While PHP files are not "executed" in the traditional sense, some WordPress plugins include binary executables (e.g., image optimization tools). More practically, some security configurations prevent reading files from `/tmp` via web server processes, or `/tmp` has a size limit that large ZIP archives exceed.

**Prevention:** Use `WP_CONTENT_DIR . '/upgrade/'` (WordPress's own upgrade temp directory) instead of `get_temp_dir()`. WordPress core uses this path for plugin updates and it is guaranteed to be writable, executable, and on the same filesystem as the plugins directory.

---

### Pitfall 16: GitHub Archive Structure Varies Between Repos

**What goes wrong:** The current `find_extracted_directory()` method (lines 545-562) assumes the ZIP contains a single top-level directory. GitHub archives always have this structure (`{owner}-{repo}-{sha}/`), but if the user provides a custom archive URL or GitHub changes the format, the code breaks silently (returns `false`, deployment fails). For incremental sync, the file paths from the Compare API are relative to the repository root, not the archive root, so the code must strip the top-level directory prefix.

**Prevention:** Validate the extracted structure before using it. Log the extracted directory name for debugging. For incremental sync, map between the archive's top-level directory and the repository root explicitly.

---

### Pitfall 17: Self-Deployment Check Uses `realpath()` Which Returns `false` for Non-Existent Paths

**What goes wrong:** The self-deployment prevention check at line 175-178 uses `realpath()` on the plugin path. If the plugin directory does not exist yet (first deployment), `realpath()` returns `false`. The comparison `$plugin_path_real === $autodeploy_path_real` becomes `false === "/var/www/..."` which is correctly not equal. But if BOTH paths return `false` (edge case: the AutoDeploy plugin directory is also not found), the comparison becomes `false === false` which is `true`, blocking the deployment incorrectly. This is unlikely but possible in a corrupted WordPress installation.

**Prevention:** Handle the `false` return from `realpath()` explicitly. If the target path does not exist, allow deployment. If the AutoDeploy path cannot be resolved, use a string-based comparison as fallback (compare `wp_normalize_path($plugin_path)` against `wp_normalize_path($autodeploy_plugin_path)`).

---

## Phase-Specific Warnings

| Phase Topic | Likely Pitfall | Mitigation |
|-------------|---------------|------------|
| Atomic file swap design | Cross-volume `rename()` failure on Windows (Pitfall 1) | Use same-volume temp dir; implement copy fallback |
| Atomic file swap design | Delete-before-copy vulnerability (Pitfall 2) | Deploy to temp, verify, swap, then cleanup old |
| Atomic file swap design | Permission drift after swap (Pitfall 14) | Explicit `chmod()` after swap |
| Deployment locking | Transient deadlock on crash (Pitfall 9) | Store lock with deployment ID; add admin force-unlock; use `flock()` |
| Error recovery / cleanup | Orphaned temp files (Pitfall 8) | try/finally cleanup + scheduled cleanup cron |
| Incremental sync API | Line ending hash mismatch (Pitfall 3) | Use API-provided file lists, not local hash comparison |
| Incremental sync logic | Stale files from Git deletions (Pitfall 11) | Handle `removed` files from Compare API response |
| GitHub API integration | Rate limit exhaustion (Pitfall 6) | Cache API responses in transients; use ETags; batch where possible |
| Rollback mechanism | Corrupt backup restore (Pitfall 4, 13) | Validate backup before restore; atomic swap during rollback |
| Rollback mechanism | Backup not blocking deployment on failure | Make backup failure a hard stop when `enable_backup` is true |
| Queue system | WP-Cron unsuitable for queues (Pitfall 5) | Custom DB table with status field; atomic claim pattern |
| Queue system | Queue starvation on low-traffic sites | Alternative trigger: webhook or admin-initiated processing |
| Post-deploy verification | Plugin activation fatal error (Pitfall 7) | PHP syntax check before activation; clear OPcache |
| Post-deploy verification | OPcache serving stale files | Call `wp_opcache_invalidate()` on all changed files |
| Large deployments | Memory exhaustion during ZIP extraction (Pitfall 12) | Entry-by-entry extraction; increase memory limit for deployment |
| Security | Zip slip via malicious archive (Pitfall 10) | Validate extracted paths; verify archive integrity |

## Cross-Cutting Concerns by Phase

**Phase 1 (Atomic Operations + Locking + Rollback):**
This phase has the highest density of critical pitfalls. The atomic swap mechanism must simultaneously solve: cross-platform compatibility (Pitfall 1), the delete-before-copy vulnerability (Pitfall 2), backup integrity (Pitfall 4), rollback safety (Pitfall 13), temp file cleanup (Pitfall 8), permission handling (Pitfall 14), deployment locking (Pitfall 9), and post-deploy verification (Pitfall 7). These are deeply interrelated -- the lock protects the backup, the backup enables the rollback, the rollback depends on the atomic swap, and the atomic swap requires proper cleanup. The implementation order matters: locking first, then atomic swap, then verification, then rollback.

**Phase 2 (Incremental Sync):**
Primary risks are hash comparison failures (Pitfall 3) and stale file handling (Pitfall 11). The GitHub API rate limit (Pitfall 6) becomes more acute with compare API calls. Memory issues (Pitfall 12) may surface with large diffs. The incremental sync must degrade gracefully -- if the compare API fails, fall back to full deployment rather than failing outright.

**Phase 3 (Queue System):**
The queue architecture is the main risk (Pitfall 5). Secondary risks include queue item timeout handling, stuck item recovery, and queue visibility for the admin UI.

## Sources

- Codebase analysis: `core/class-deployment-manager.php`, `core/class-backup-manager.php`, `core/class-polling-scheduler.php`, `core/class-github-api.php`
- Existing concerns: `.planning/codebase/CONCERNS.md` (23 documented issues)
- Architecture reference: `.planning/codebase/ARCHITECTURE.md`
- PHP documentation: `rename()` behavior on Windows vs POSIX, `ZipArchive` class reference, `flock()` semantics
- WordPress core: `wp_mkdir_p()`, `WP_Filesystem` API, `WP_Cron` implementation in `wp-cron.php`, transient API in `wp-includes/option.php`
- GitHub REST API v3: Compare two commits endpoint, rate limiting documentation
- Confidence: HIGH -- pitfalls derived from direct codebase analysis and well-documented PHP/WordPress platform behaviors
