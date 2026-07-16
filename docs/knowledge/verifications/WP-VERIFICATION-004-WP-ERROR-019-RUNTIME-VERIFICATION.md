# WP-VERIFICATION-004 — WP-ERROR-019 Runtime Verification

Structured per `SF-TEMPLATE-005`. Governed by `SF-SPEC-002`, `SF-SPEC-006`, `SF-SPEC-011`, and the post-certification process in `SF-SPEC-013` Section 5.6.

# 1. Evidence Record Identity

**Record ID:** `WP-VERIFICATION-004`
**Date:** 2026-07-16

# 2. Associated Artifact

`WP-ERROR-019` — WordPress Filesystem Permission Denied, Version 1.0 when execution began.

# 3. Objective

Verify the entry's direct-write and inaccessible-ancestor mechanisms, WordPress upload and installer behavior, negative controls, recovery, cleanup, and literal message fidelity against a disposable current WordPress runtime.

**Expected behavior, per WP-ERROR-019 Version 1.0:** denying write access or ancestor traversal should prevent directory creation, produce the documented WordPress/PHP signals, and recover after minimum path-specific access is restored; the entry additionally presented `Installation Failed: Could Not Create Directory.` as a literal WordPress installer message.

# 4. Baseline

- WordPress 7.0.1 (`wp-includes/version.php`: `$wp_version = '7.0.1'`).
- PHP 8.5.7; WP-CLI 2.12.0; SQLite Database Integration 2.2.23.
- Fresh local single-site install outside the repository.
- Test parents existed at mode `0755`; controls were writable.
- `open_basedir` was empty; approximately 35 GiB remained available; effective UID/GID were 501/20.

# 5. Environment

Disposable environment under `/private/tmp/squirrelforge-wp-verification-004`, using the official WordPress 7.0.1 archive, official WP-CLI build, and official SQLite integration plugin. Hospital and Thematic were not accessed or changed. SHA-256 values captured at download: WordPress archive `dc10592da9b580c7525632850e0cced371b13081853ac29afe93b5d5bb00db98`; WP-CLI `ce34ddd838f7351d6759068d09793f26755463b4a4610a5a5c0a97b68220d85c`; SQLite plugin `44be096a14ebcea424b5e4bf764436ec85fb067f74ab47822c4c5346df21591e`.

# 6. Execution Procedure

1. Confirmed writable controls with `wp_is_writable()`, `wp_mkdir_p()`, and `wp_upload_dir()`.
2. Changed an existing target parent from `0755` to `0555`; repeated the same operations.
3. Created an existing nested parent, removed search permission from its ancestor (`0600`), and repeated the upload-directory operation twice from clean baselines.
4. Traced plugin installation from `WP_Upgrader::unpack_package()` through `wp-content/upgrade`, `unzip_file()`, and `WP_Filesystem_Direct::mkdir()`.
5. Installed a disposable fixture plugin with `wp-content/upgrade` at `0555`, restored `0755`, and repeated failure and success controls.
6. Restored every changed mode, confirmed uploads and installation worked, removed the fixture, and deleted the disposable environment.

# 7. Evidence Artifacts

- **Direct denial:** parent `0555`; `wp_is_writable()` false; `wp_mkdir_p()` false; child absent; captured warning `mkdir(): Permission denied` from `wp-includes/functions.php:2091`.
- **Direct control:** parent `0755`; `wp_is_writable()` true; `wp_mkdir_p()` true; child created; no warning.
- **Upload denial:** `wp_upload_dir()` returned `Unable to create directory uploads/2026/07. Is its parent directory writable by the server?`; target absent.
- **Ancestor denial:** inaccessible ancestor `0600`, `is_executable()` false; the same upload error appeared. A restore succeeded, a fresh second denial reproduced identically, and a final restore succeeded.
- **Installer source path:** `WP_Upgrader::unpack_package()` uses `$wp_filesystem->wp_content_dir() . 'upgrade/'`; extraction and direct filesystem directory creation ultimately call `mkdir()`.
- **Installer runtime denial, repeated twice:** `Warning: Could not create directory. ".../wp-content/upgrade/squirrelforge-verification-plugin"` followed by `Error: No plugins installed.`
- **Installer restored control:** `Plugin installed successfully.` / `Success: Installed 1 of 1 plugins.`; fixture then deleted.
- **Browser-interface source composition:** WordPress 7.0.1 `wp-admin/js/updates.js` composes `Installation failed: %s`; the underlying core string is `Could not create directory.`. Therefore the composed English message is `Installation failed: Could not create directory.`, not the title-cased quotation previously used by the repository.

# 8. Validation

The failure mechanism, taxonomy ownership, diagnostic guidance, and recovery procedure are confirmed. Direct write denial and ancestor traversal denial are deterministic and share the documented user-facing upload error. The installer traverses the documented staging path.

**Difference from documentation:** literal quotation fidelity only. The repository used `Installation Failed: Could Not Create Directory.` as an exact WordPress message. Current browser-interface source composes `Installation failed: Could not create directory.`; WP-CLI reports the underlying `Could not create directory.` plus its path. This requires narrowly correcting live literal claims while preserving historical quotations.

**Required repository changes:** revise the live literal claims in `WP-ERROR-019` and the shared symptom text in `WP-ERROR-020`; replace the live literal quotation in `SF-TAXONOMY-005` with behavioral wording; preserve historical reviews and revision-history text; execute the applicable `SF-SPEC-013` Section 5.6 review and re-certification chains.

| Attribute | Status |
|---|---|
| Failure mechanism | Unchanged |
| Taxonomy ownership | Unchanged |
| Diagnostic guidance | Unchanged |
| Recovery procedure | Unchanged |
| Runtime evidence | Expanded |
| Documentation fidelity | Corrected |

Read-only mounts were not runtime-tested on macOS; source and architecture show the same failed filesystem-call path, but this record does not elevate that to runtime verification. SELinux/AppArmor were not emulated and require a Linux reference environment.

# 9. Negative Validation

- `open_basedir` empty; capacity exhaustion ruled out by more than 35 GiB free.
- Writable sibling/control paths succeeded.
- Denied targets did not exist after failure.
- No database, Hospital, or Thematic state participated in the denial.
- The test does not claim runtime verification of read-only mounts, SELinux, AppArmor, or capacity exhaustion.

# 10. Cleanup Evidence

All altered directories restored to `0755`; upload creation succeeded after restoration; the fixture plugin was absent; the complete temporary environment was deleted; Git showed only the intended documentation work after the evidence freeze.

# 11. Repository Validation Evidence

After the complete review chain existed, `scripts/validate-repo.sh .` passed all checks, the complete PHPUnit suite passed (146 tests, 338 assertions), every PHP file under `src/` and `tests/` passed `php -l`, and `git diff --check` passed.

# 12. Classification

**Permanent.** This record is the fixed evidence supporting the Version 1.1 quotation-fidelity corrections.

# 13. Retention Decision

Retain permanently. A later WordPress-version re-verification must be a new record.

# 14. Traceability Map

- `WP-ERROR-019` Version 1.1: direct subject and correction.
- `WP-ERROR-020` Version 1.1: shared live literal quotation corrected; capacity behavior not runtime-tested.
- `SF-TAXONOMY-005` Version 1.4: live quotation replaced with behavioral wording; ownership unchanged.

# 15. Engineering Review Status

Reviewed through `SF-REVIEW-159`–`167` as applicable to the revised entries and affected certified categories.

# 16. Revision History

| Version | Date | Summary | Approval Status |
|---|---|---|---|
| 1.0 | 2026-07-16 | Initial record. Confirms WP-ERROR-019's central behavior and recovery; identifies one literal-quotation fidelity defect and explicitly limits unexecuted platform-specific mechanisms. | Draft — reviewed through SF-REVIEW-159–167 |
