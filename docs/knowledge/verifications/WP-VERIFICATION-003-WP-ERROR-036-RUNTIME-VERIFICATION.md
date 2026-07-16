# WP-VERIFICATION-003 — WP-ERROR-036 Runtime Verification

Structured per `SF-TEMPLATE-005` and the `WP-VERIFICATION-XXX` series convention (`docs/knowledge/verifications/README.md`). Governed by `SF-SPEC-002`, `SF-SPEC-006`, `SF-SPEC-011`.

---

# 1. Evidence Record Identity

**Record ID:** `WP-VERIFICATION-003`

**Date:** 2026-07-15

---

# 2. Associated Scenario or Artifact

`WP-ERROR-036` — WordPress Upload Size Limit Exceeded, at `docs/knowledge/wp-errors/WP-ERROR-036-UPLOAD-SIZE-LIMIT-EXCEEDED.md` (Version 1.0 at the time this record's execution began).

---

# 3. Objective

Determine whether all three documented causes of `WP-ERROR-036` behave as described against a real WordPress installation, using genuine HTTP requests rather than WP-CLI's own CLI-sideload path (which does not exercise PHP's own request-time `post_max_size`/`upload_max_filesize` enforcement at all — the reason this record required a different environment shape than `WP-VERIFICATION-001`/`002`).

**Expected behavior, per the entry as documented (Version 1.0):**
- **Cause 1** (`post_max_size` exceeded): WordPress's own code executes normally, but PHP leaves `$_POST`/`$_FILES` both empty, with no PHP-level error code available to inspect from the script's own perspective — though Section 8 separately documents a genuine, checkable PHP error-log warning as the diagnostic signal for this specific case.
- **Cause 2** (`upload_max_filesize` exceeded): PHP populates `$_FILES[...]['error']` with `UPLOAD_ERR_INI_SIZE`, and `wp_handle_upload()` translates this into a specific message naming the directive.
- **Cause 3** (WordPress's own further-restricted limit via `wp_max_upload_size()`): "runs entirely in WordPress's own userland code, after PHP has already fully accepted the file," filterable via `upload_size_limit`, and "on a multisite install, further reduced by a site's own configured upload-space quota" — implying `wp_handle_upload()` itself enforces this computed value regardless of installation type.

---

# 4. Baseline

- WordPress version: 7.0.1, same disposable installation as `WP-VERIFICATION-001`/`002`, re-baselined.
- Single-site installation (not multisite) — relevant to the Cause 3 finding below.
- `wp-content/uploads/` empty; attachment count `0`.
- No custom `upload_size_limit` filter or `fileupload_maxk` option present before this record's own test-specific filter was added.

---

# 5. Environment

Unlike `WP-VERIFICATION-001`/`002` (WP-CLI sideload against the same disposable WordPress 7.0.1 + SQLite installation), this record required standing up a real HTTP server, since PHP's own `post_max_size`/`upload_max_filesize` enforcement happens at the SAPI request-parsing level, before any script (including WP-CLI's own bootstrap, which never performs an HTTP multipart upload) runs:

- Three sequential PHP built-in server (`php -S`) instances, each with different `-d` ini overrides for `upload_max_filesize`/`post_max_size` (built-in server ini overrides apply per-process, requiring separate instances rather than one server reused across configurations).
- A minimal, disposable receiver script (`upload-receiver.php`, and a Cause-3 variant `upload-receiver-cause3.php`), bootstrapping `wp-load.php` plus `wp-admin/includes/file.php`/`media.php`, then calling `wp_handle_upload()` directly against whatever `$_FILES`/`$_POST` PHP itself populated from the real request — genuinely exercises PHP's own SAPI-level enforcement, not a simulated `$_FILES` array. Neither script is part of WordPress core; both were removed at cleanup.
- Real multipart POST requests issued via `curl -F`.

---

# 6. Execution Procedure

1. Confirmed baseline (Section 4).
2. **Cause 2 test:** server with `upload_max_filesize=1K`, `post_max_size=10M`. Uploaded a 5006-byte GIF fixture via `curl -F`. Also ran a control upload (6-byte file, well within both limits) to confirm the environment itself accepts valid uploads.
3. **Cause 1 test:** server with `post_max_size=300` (bytes), `upload_max_filesize=5M`. Uploaded a 2006-byte GIF fixture (individually well under `upload_max_filesize`, but the full multipart request exceeds `post_max_size`).
4. **Cause 3 test:** server with generous PHP directives (`upload_max_filesize=5M`, `post_max_size=10M`), receiver script registering `add_filter( 'upload_size_limit', fn() => 500 )` before calling `wp_handle_upload()`. Uploaded the same 2006-byte GIF fixture (within both PHP directives, but over the filtered WordPress-level limit).
5. Following the surprising Cause 3 result (Section 7), independently traced `wp_max_upload_size()`'s actual usage across WordPress core via direct source search, rather than relying on the entry's own account of where and how it is enforced.
6. Cleaned up all fixtures, receiver scripts, server processes, and the one file `wp_handle_upload()` itself wrote to `wp-content/uploads/` during the Cause 3 test (that function does not create a database attachment record on its own — only `wp_insert_attachment()`/the full media pipeline does — so cleanup here required direct filesystem removal, not `wp post delete`).

---

# 7. Evidence Artifacts

- **Cause 2 trigger:** `$_FILES['file'] = { error: 1, size: 0, tmp_name: '' }`; `wp_handle_upload()` → `{"error":"The uploaded file exceeds the upload_max_filesize directive in php.ini."}` — matches the entry's own documented message exactly.
- **Cause 2 control:** valid 6-byte file → `$_FILES['file']['error'] = 0`, `wp_handle_upload()` returned a real file path/URL — confirms the rejection above is genuine, not an environment defect.
- **Cause 1 trigger:** `$_POST` and `$_FILES` both empty (`{"post_empty":true,"files_empty":true,"files_raw":[]}`), HTTP 200, no exception — WordPress's own code executed normally with nothing to inspect, exactly as documented.
- **Cause 1 PHP error-log signal:** the server process's own stderr recorded `PHP Warning: PHP Request Startup: POST Content-Length of 2207 bytes exceeds the limit of 300 bytes in Unknown on line 0` — matches the entry's own Section 8 claim ("commonly worded to the effect of 'POST Content-Length of *N* bytes exceeds the limit of *N* bytes'") closely enough to confirm the claim, including the specific numeric values (2207 bytes = the real multipart request size, 300 = the configured limit).
- **Cause 3 trigger:** `wp_max_upload_size()` correctly returned `500` (confirming the filter was live), `$_FILES['file']['error'] = 0`, `size = 2006` — yet `wp_handle_upload()` **succeeded**, writing the file to `wp-content/uploads/2026/07/moderate.gif` and returning a normal success result, not a WordPress-generated size-limit message.
- **Source verification, `wp_max_upload_size()` usage:** `grep -n "wp_max_upload_size" wp-admin/includes/file.php wp-admin/includes/media.php wp-includes/functions.php` → exactly one call site, `wp-admin/includes/media.php:2128`, inside `media_upload_form()` — used only to render the "Maximum upload file size" display text in the legacy media-upload UI. Zero references anywhere in `wp-admin/includes/file.php`, where `wp_handle_upload()`/`_wp_handle_upload()` are actually defined.
- **Source verification, the real WordPress-level enforcement mechanism:** `grep -rn "upload_is_user_over_quota|fileupload_maxk" wp-admin/includes/ms.php` located `check_upload_size( $file )` (`wp-admin/includes/ms.php`), which checks `filesize( $file['tmp_name'] ) > ( KB_IN_BYTES * get_site_option( 'fileupload_maxk', 1500 ) )` directly — not via `wp_max_upload_size()` at all — and separately calls `upload_is_user_over_quota()`.
- **Source verification, the hook wiring:** `grep -n "add_filter.*check_upload_size" wp-admin/includes/ms-admin-filters.php` → `add_filter( 'wp_handle_upload_prefilter', 'check_upload_size' )`. `grep -n -B5 "ms-admin-filters" wp-admin/includes/admin.php` → this file is `require_once`'d only inside `if ( is_multisite() ) { ... }` — confirming `check_upload_size()` is never registered at all on a single-site installation, which is exactly why the Cause 3 test above succeeded despite the narrowed `upload_size_limit` filter.

---

# 8. Validation

The objective (Section 3) is achieved. Causes 1 and 2 are **confirmed accurate** in every respect tested, including the specific message text and the specific PHP error-log wording. Cause 3, as documented, is **substantially inaccurate**.

**Differences from documentation:**

1. **Causes 1 and 2: none.** Both match documented behavior exactly, including exact message/log text.
2. **Cause 3: the documented mechanism does not exist as described.** The entry states `wp_max_upload_size()` — filterable via `upload_size_limit` — is enforced by WordPress's own userland code "after PHP has already fully accepted the file," implying this applies generically (with multisite additionally narrowing it further). Verified instead: `wp_max_upload_size()` is used in exactly one place in WordPress core, purely to render UI display text, never for enforcement. The *only* genuine server-side enforcement beyond the two PHP-level directives is `check_upload_size()` (`wp-admin/includes/ms.php`), which: (a) is **multisite-only** — never registered on a single-site installation at all, confirmed via the `is_multisite()`-gated `require_once` in `wp-admin/includes/admin.php`; (b) checks `fileupload_maxk` **directly** via `get_site_option()`, not through `wp_max_upload_size()`'s own computed or filtered value; (c) separately enforces a distinct condition, `upload_is_user_over_quota()` (total space used), that the entry does not mention at all. On a single-site installation, narrowing `upload_size_limit` via a filter has **no enforcement effect whatsoever** — a file within both PHP directives will be accepted regardless of how low that filter sets `wp_max_upload_size()`'s return value.

**Required repository changes:** `WP-ERROR-036` requires a substantive correction to Cause 3 across Sections 4, 6, 8, 10, and 11 — not a wording fix, but a rewrite of what mechanism actually exists, since the entry currently describes enforcement that does not occur on the majority of WordPress installations (every single-site install). `SF-TAXONOMY-007` requires checking for the same claim in its own Section 3 summary table.

---

# 9. Negative Validation

- Cause 1 and Cause 2 triggers created no attachment record and no file on disk — confirmed via `wp post list` and `find` before the Cause 3 test began.
- The Cause 3 test's file (`moderate.gif`) was written to disk by `wp_handle_upload()` (expected, since that function succeeded) but did **not** produce an attachment database record, since `wp_handle_upload()` alone does not call `wp_insert_attachment()` — the file-only result is itself consistent with having tested `wp_handle_upload()` specifically, as the entry's own Section 8 names it, and not a broader pipeline function.
- No PHP fatal error occurred during any of the three tests.
- The Cause 2 control (valid small file) succeeded normally, confirming the Cause 1/2 rejections were genuine and not an environment artifact.

---

# 10. Cleanup Evidence

- All three `php -S` server processes terminated (`kill`, verified via absent PID files afterward).
- `upload-receiver.php`, `upload-receiver-cause3.php`, `ini-check.php` removed from the WordPress installation directory.
- The Cause 3 test's orphaned file (`wp-content/uploads/2026/07/moderate.gif`) removed directly via filesystem (no attachment record existed to delete via `wp post delete`).
- Attachment count confirmed `0`; `wp-content/uploads/` confirmed empty.
- All local fixture files removed.

---

# 11. Repository Validation Evidence

`git status --short` clean before this record's own work began. No temporary artifact from the pilot environment (server logs, receiver scripts, fixtures) present anywhere in the repository working tree.

---

# 12. Classification

**Permanent**, per `SF-SPEC-011` Section 5.1.

---

# 13. Retention Decision

Retain permanently alongside the corrected `WP-ERROR-036`. A future re-verification against a later WordPress version (which could plausibly change this exact mechanism, given how narrowly-scoped and easy to overlook it is) should be a new `WP-VERIFICATION-XXX` record citing this one.

---

# 14. Traceability Map

- **Scenario/Artifact:** `WP-ERROR-036` (Section 2).
- **Implementation:** None.
- **Validation:** Section 8 above.
- **Documentation:** `WP-ERROR-036` Version 1.1 (corrected Sections 4, 6, 7, 8, 9, 10, 11, 12, 14, 17) and `SF-TAXONOMY-007` Version 1.5 (corrected Section 3 table row), both citing this record. `docs/engineering/KNOWLEDGE-PRODUCTION-PLAN.md` Section 10.

---

# 15. Engineering Review Status

Examined as part of `SF-REVIEW-155` (Class A author review) and `SF-REVIEW-156` (Class B independent review) of the resulting `WP-ERROR-036` Version 1.1 correction; category re-certified via `SF-REVIEW-157` (consistency) and `SF-REVIEW-158` (baseline, Media Knowledge Baseline v3).

---

# 16. Revision History

| Version | Date | Summary of Changes | Approval Status |
|---|---|---|---|
| 1.0 | 2026-07-15 | Initial record. Third Reference Implementation pilot, first to require a real HTTP server rather than WP-CLI sideload. Causes 1 and 2 confirmed fully accurate, including exact message and log text. Cause 3 found substantially inaccurate: the documented wp_max_upload_size()/upload_size_limit enforcement does not exist in WordPress core at all -- it is display-only. The only genuine WordPress-level enforcement beyond the two PHP directives is a separate, multisite-only mechanism (check_upload_size(), checking fileupload_maxk directly) never registered on a single-site installation. | Draft — reviewed via SF-REVIEW-155/156 |
