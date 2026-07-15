# WP-ERROR-036 — WordPress Upload Size Limit Exceeded

---

# 1. Knowledge Entry

WordPress Upload Size Limit Exceeded

---

# 2. Metadata

* **Error ID:** `WP-ERROR-036`
* **Title:** WordPress Upload Size Limit Exceeded
* **Category:** Media
* **Severity:** High
* **Recovery Priority:** High
* **Status:** Production Ready
* **Version:** 1.0

---

# 3. Summary

A file upload is rejected — or never reaches WordPress's own upload-handling code at all — because its size, or the size of the entire request carrying it, exceeds an applicable, currently-configured limit: PHP's own `post_max_size` or `upload_max_filesize` directive, or a further restriction WordPress itself imposes via `wp_max_upload_size()`. The three enforcement points sit at different layers, each with a distinctly different observable signature, and the rejection occurs before any attempt is made to write the file to the filesystem.

---

# 4. Primary Failure Mode

A file submitted for upload exceeds a size limit enforced at one of three distinct points, each occurring before WordPress's own filesystem-write stage is ever reached: PHP's own `post_max_size` directive, which governs the entire HTTP request body and, when exceeded, means WordPress's own code still executes normally but PHP leaves `$_POST`/`$_FILES` empty rather than populating them with the request's own data; PHP's own `upload_max_filesize` directive, which governs a single uploaded file specifically and, when exceeded, is detected by PHP and reported to the running script via a specific error code; or a further, stricter limit WordPress itself computes and enforces via `wp_max_upload_size()` (filterable through `upload_size_limit`, and, on a multisite install, further reduced by a site's own configured upload-space quota), checked against the file's actual size only after PHP has already accepted it. Each of these three points produces a materially different symptom, and correctly identifying which one is responsible is the primary diagnostic task this entry documents.

---

# 5. Severity

This entry is classified **High** rather than **Critical**, the same considered exception this catalog applies to a condition whose own worst-case manifestation never includes a total loss of a functioning request path:

- By this entry's own definition, every manifestation is scoped to file uploads specifically — ordinary browsing, content editing, and every non-upload feature continue to function normally regardless of which of the three causes applies.
- No plausible manifestation of this entry's own condition produces a PHP fatal error or a site-wide outage; each of the three enforcement points is a designed, non-destructive rejection, not a crash.
- This entry is nonetheless **High** rather than **Medium** or **Low**, and Recovery Priority **High** rather than **Immediate**, because upload capability is frequently business-critical (a photography, e-commerce, or media-heavy site can depend on it entirely), and because the `post_max_size` cause specifically (Section 6, cause 1) can present with no actionable error message at all, materially extending diagnosis time relative to a condition that at least reports a clear cause.

---

# 6. Distinction

This entry applies only when verified evidence establishes that a file upload failed specifically because of its own size relative to an applicable, currently-configured limit — not that it failed during the filesystem write itself, was rejected for its file type, or failed during subsequent processing.

**Three internal causes this entry keeps deliberately separate, since each has a distinctly different diagnostic signature:**

1. **`post_max_size` exceeded** — the entire HTTP request body, not only the file itself, exceeds PHP's own `post_max_size` directive. WordPress's own code still executes normally, but PHP leaves `$_POST` and `$_FILES` both empty rather than populating them with the request's own data, with no PHP-level error code available to inspect, since the script never received the data to report an error about in the first place. This is the most diagnostically difficult of the three causes, since the observable symptom is frequently indistinguishable from "no file was selected" without independently checking the request's own size against this specific directive.
2. **`upload_max_filesize` exceeded** — the individual file exceeds this directive specifically, while the overall request may still be within `post_max_size`. PHP populates `$_FILES[...]['error']` with the `UPLOAD_ERR_INI_SIZE` code, which WordPress's own `wp_handle_upload()` detects and translates into a specific, user-facing message naming the directive.
3. **WordPress's own further-restricted limit** — the file passes both PHP-level checks above, but `wp_max_upload_size()` — which returns the *lesser* of `upload_max_filesize` and `post_max_size` by default, and can be narrowed further by the `upload_size_limit` filter or, on a multisite install, by a site's own configured upload-space quota (`fileupload_maxk`) — computes a stricter effective limit the file's actual size still exceeds. This check runs entirely in WordPress's own userland code, after PHP has already fully accepted the file, and produces a WordPress-generated message rather than a PHP one.

It is distinct from:

- **`WP-ERROR-019` — WordPress Filesystem Permission Denied** and **`WP-ERROR-020` — WordPress Disk Space Exhausted**: both presume the file has already passed every size check and PHP is actually attempting to write it to the filesystem. This entry's own condition is resolved, one way or another, before that write is ever attempted — `WP-ERROR-020` Section 6 already documents this same distinction from its own side, explicitly excluding this entry's own condition from its own scope.
- **`WP-ERROR-037` — WordPress Upload File Type Rejected**: a distinct, later gate in the same pipeline (per `SF-TAXONOMY-007` Section 4), evaluated only once this entry's own size checks have already passed.
- **`WP-ERROR-038` — WordPress Image Processing Failure**: presumes the file has already been fully accepted and written to the filesystem; this entry's own condition never reaches that point.
- **A web-server- or gateway-level request-size limit** (for example, nginx's own `client_max_body_size`, or a hosting platform's own upload-size cap) rejecting the request before it ever reaches PHP at all: this is a distinct, server-configuration-level condition this entry does not own, though it can present with a symptom similar to cause 1 above (an empty or generically-failed upload with no PHP-level evidence); diagnosis (Section 11) distinguishes the two by checking whether the request reached PHP at all.

---

# 7. Scope

**Covered:** A verified condition in which a file upload is rejected, or never reaches WordPress's own code at all, specifically because of its own size (or the size of the request carrying it) relative to `post_max_size`, `upload_max_filesize`, or a further limit `wp_max_upload_size()` computes and enforces.

**Excluded:**

- Any failure occurring after the file has already passed every size check (filesystem write, file-type validation, image processing — `WP-ERROR-019`/`020`/`037`/`038`).
- A web-server- or gateway-level request-size limit rejecting the request before PHP ever receives it.
- A file that is genuinely small enough to pass every applicable limit but fails for an unrelated reason.

---

# 8. WordPress Components

Listed as components commonly involved, not as a claim that every installation exercises every one of them:

- PHP's own `post_max_size` and `upload_max_filesize` `php.ini` directives, and the distinct failure signature each produces when exceeded.
- `$_FILES[...]['error']`, and specifically the `UPLOAD_ERR_INI_SIZE` (1) constant PHP populates when `upload_max_filesize` is exceeded — the specific, structured signal cause 2 relies on, which cause 1 (`post_max_size`) never produces, since the request itself is discarded before this array is populated at all.
- `wp_max_upload_size()` (`wp-includes/functions.php`), which computes the effective upload limit as the lesser of `upload_max_filesize` and `post_max_size`, filterable via the `upload_size_limit` filter.
- `wp_handle_upload()` and `_wp_handle_upload()` (`wp-admin/includes/file.php`), which translate a PHP-level upload error code into a specific, user-facing WordPress message, and which separately check the file's actual size against `wp_max_upload_size()`'s own computed value for cause 3.
- On a multisite installation, the per-site upload-space quota (`fileupload_maxk` site option, and the network-level "Site Upload Space" setting), which can narrow the effective limit further than `upload_max_filesize`/`post_max_size` alone would suggest.
- PHP's own error-log warning for an exceeded `post_max_size` (commonly worded to the effect of "POST Content-Length of *N* bytes exceeds the limit of *N* bytes"), which — unlike the excluded web-server/gateway-level condition, which never invokes PHP at all — provides a genuine, checkable signal that PHP itself, specifically, is where the rejection occurred.
- The Media Library's own upload UI, which commonly displays WordPress's own current maximum upload size to the user before an upload is even attempted — a value that reflects `wp_max_upload_size()`'s own computation and can itself be a useful, non-invasive diagnostic reference point.

---

# 9. Typical Symptoms

- An upload attempt that appears to silently fail — no file is added to the Media Library, and no specific error message is shown, or the interface behaves as though no file was selected at all — consistent with cause 1 (`post_max_size`).
- A specific, clear WordPress message naming `upload_max_filesize` directly (for example, "The uploaded file exceeds the upload_max_filesize directive in php.ini") — consistent with cause 2.
- A WordPress-generated message about the file exceeding the maximum upload size, where the file itself is smaller than `upload_max_filesize` and the request smaller than `post_max_size` — consistent with cause 3, and worth checking against any custom `upload_size_limit` filter or multisite quota in effect.
- The identical file uploading successfully via WP-CLI or a direct database/filesystem import, while failing consistently through the web-based upload interface — pointing toward a web-request-path-specific limit (any of the three causes, or the excluded web-server-level condition) rather than a condition intrinsic to the file itself.
- The Media Library's own displayed "Maximum upload file size" value being smaller than the administrator expects, indicating the effective limit is lower than `upload_max_filesize` alone would suggest.
- The failure correlating with a specific file size threshold reproducible across multiple, otherwise-unrelated files, rather than with any particular file's own content or type.

---

# 10. Common Causes

Causes are grouped by category. Inclusion in this list identifies a category as plausible; it does not assert that any specific one is present without diagnostic confirmation.

- A hosting environment's own default `post_max_size` or `upload_max_filesize` values being lower than the site's actual, legitimate upload needs (a common default on shared or budget hosting).
- `post_max_size` configured lower than, or equal to, `upload_max_filesize` — a misconfiguration, since the request body needs to accommodate the file plus other form data, meaning `post_max_size` should always exceed `upload_max_filesize`; where it does not, even a file just under the `upload_max_filesize` limit can still trigger cause 1.
- A plugin or theme applying the `upload_size_limit` filter to impose a stricter limit than the PHP-level directives alone would allow, without the site owner being aware the filter is active.
- A multisite installation's own per-site upload-space quota being reached or configured more restrictively than the network-wide default, independent of the PHP-level directives.
- A PHP configuration change (a hosting migration, a PHP version upgrade, a container image rebuild) that reset `post_max_size`/`upload_max_filesize` to a new environment's own defaults, narrower than a previous environment's configuration.
- A `.htaccess`, `php.ini`, or `.user.ini` override that was expected to raise the effective limit but is not actually being applied — for example, placed in the wrong directory, or overridden by a stricter value set at a higher configuration scope the hosting environment does not permit overriding.

---

# 11. Diagnosis

Verify the following, starting from the least invasive observation and narrowing only once the general shape of the failure is established:

1. **Confirm the failure is genuinely size-related**, as opposed to a file-type rejection (`WP-ERROR-037`) or a filesystem-level failure (`WP-ERROR-019`/`020`) — check the exact message presented, where one exists, and the file's own actual size relative to the Media Library's own displayed maximum upload size.
2. **Capture the exact symptom presentation**: whether the upload fails silently with no specific message (pointing toward cause 1), whether a specific message names `upload_max_filesize` (cause 2), or whether a WordPress-generated size-exceeded message appears for a file smaller than `upload_max_filesize` (cause 3).
3. **Where the failure is silent (cause 1 suspected), confirm the request is actually reaching PHP at all** before concluding `post_max_size` is the cause — check the PHP error log specifically for the "POST Content-Length... exceeds the limit..." warning (Section 8), a genuine, checkable signal that PHP itself received and rejected the request, as distinct from a web-server- or gateway-level size limit rejecting the request before PHP ever receives it, which can present identically to WordPress but never produces this specific PHP-level log entry.
4. **Directly compare the file's actual size against the currently configured `post_max_size` and `upload_max_filesize`** (via `phpinfo()`, `ini_get()`, or a hosting control panel's own PHP configuration display), confirming which specific directive, if either, the file's own size actually exceeds.
5. **Where both PHP-level directives are confirmed sufficient for the file's own size, evaluate cause 3**: check for an active `upload_size_limit` filter (for example, by temporarily deactivating plugins one at a time, or inspecting active filter callbacks via a debugging tool) and, on a multisite installation, the specific site's own configured upload-space quota.
6. Where a `.htaccess`, `php.ini`, or `.user.ini` override was intended to raise the limit, confirm it is actually being applied by checking the currently *effective* configuration directly (`phpinfo()` or `ini_get()`), not merely the override file's own stated values, since a misplaced or overridden directive silently fails to take effect.
7. Preserve relevant evidence — the exact file size, the exact message or absence of one, and the currently effective configuration at every relevant layer — before making any change.
8. Where the engineer performing diagnosis does not control PHP configuration or the hosting environment, escalate to the hosting provider rather than attempting an unverified workaround.

---

# 12. Recovery Procedure

Recovery shall target the specific, verified cause identified in Diagnosis (Section 11), scoped to the site's own actual, legitimate upload needs.

Permitted recovery categories, depending on the verified cause, include:

- Raising `upload_max_filesize` and, correspondingly, `post_max_size` (ensuring the latter remains larger than the former) to accommodate the site's own actual, legitimate maximum file size, via the hosting environment's own supported configuration method (`php.ini`, `.htaccess`, `.user.ini`, or a hosting control panel, as the specific environment supports).
- Removing or adjusting a plugin's own `upload_size_limit` filter callback where diagnosis confirms it is imposing an unintended or overly restrictive limit.
- Adjusting a multisite site's own upload-space quota where diagnosis confirms it, rather than the PHP-level directives, is the binding constraint.
- Correcting a misplaced or ineffective configuration override so the intended limit actually takes effect, confirmed via direct inspection of the effective, running configuration rather than assumed from the override file's own stated values.
- Escalating to the hosting provider where the engineer performing recovery does not control the relevant configuration layer.

Recovery shall not raise `post_max_size`/`upload_max_filesize` far beyond the site's own actual, legitimate needs as an indiscriminate precaution; doing so increases the resource impact a single large or abusive request can impose without a corresponding legitimate benefit.

---

# 13. Validation

Recovery is successful when:

- The specific file, or a file of comparable size, uploads successfully through the same web-based path that previously failed.
- The Media Library's own displayed maximum upload size reflects the corrected, intended limit.
- Where a configuration override was corrected, `phpinfo()`/`ini_get()` confirms the *effective*, running configuration matches the intended value, not only that the override file's own content was changed.
- No previously-working upload of a smaller file was disturbed by the change.
- Where a multisite quota was adjusted, the change is confirmed effective for the specific site involved, not only at the network level.

---

# 14. Prevention

- Document the site's own actual, legitimate maximum upload size requirement, and configure `upload_max_filesize`/`post_max_size` deliberately to accommodate it, rather than accepting a hosting environment's own default without verification.
- Ensure `post_max_size` is always configured larger than `upload_max_filesize`, since a request smaller than the latter can still fail if the former is misconfigured to be equal or smaller.
- Document any `upload_size_limit` filter a plugin or custom code applies, so a future diagnosis does not need to rediscover its existence.
- Re-verify effective upload-size configuration after any hosting migration, PHP version upgrade, or container image change, rather than assuming it carried forward unchanged.
- On a multisite installation, review per-site upload-space quotas periodically as content accumulates, rather than only when a site owner first reports a failure.

---

# 15. Security Considerations

- Do not raise upload-size limits far beyond legitimate need as a blanket policy; an excessively high limit increases the resource cost a single malicious or accidental large upload can impose, and can contribute to disk-capacity exhaustion (`WP-ERROR-020`) more quickly than a deliberately scoped limit would.
- Coordinate any change to shared PHP configuration (`php.ini` at a level affecting other applications) through a platform-appropriate process where the hosting environment is shared.
- Treat a sudden, unexplained pattern of upload-size-limit rejections as worth a brief check for an attempted resource-exhaustion pattern (repeated large-file upload attempts), not only as routine capacity planning.

---

# 16. Related Errors

The following are cited as they exist in this repository, or as conceptual distinctions where noted.

1. [WP-ERROR-019 — WordPress Filesystem Permission Denied](WP-ERROR-019-FILESYSTEM-PERMISSION-DENIED.md) — exists in this repository; see Section 6 (Distinction) above.
2. [WP-ERROR-020 — WordPress Disk Space Exhausted](WP-ERROR-020-DISK-SPACE-EXHAUSTED.md) — exists in this repository; that entry's own Section 6 already excludes this entry's own condition, anticipating it.
3. WP-ERROR-037 — WordPress Upload File Type Rejected (conceptual reference; planned per `SF-TAXONOMY-007` Section 3, no corresponding document currently exists in this repository; no link is provided) — see Section 6 (Distinction) above.
4. WP-ERROR-038 — WordPress Image Processing Failure (conceptual reference; planned per `SF-TAXONOMY-007` Section 3, no corresponding document currently exists in this repository; no link is provided) — see Section 6 (Distinction) above.

---

# 17. Notes

This entry documents the general, verified observable condition of a file upload being rejected due to its own size, distinguishing the three mechanically distinct enforcement points — `post_max_size`, `upload_max_filesize`, and WordPress's own further-restricted `wp_max_upload_size()` — at which that rejection can occur. It is the first entry drafted against `SF-TAXONOMY-007`, resolving the specific, previously-disclosed gap `WP-ERROR-020` Section 6 already excluded from its own scope without any entry yet claiming it.

Consistent with the single-responsibility principle in **SF-SPEC-001** Section 4.3, this entry does not claim ownership of the filesystem-write stage, the file-type validation stage, or the image-processing stage that follow it in the same pipeline, nor of a web-server-level request-size limit that can present with a similar symptom to its own cause 1.

This entry reached `Production Ready` via `SF-REVIEW-106` (Class A author review; one Minor structural finding corrected) and `SF-REVIEW-107` (Class B independent review; three Minor findings — a mechanism-level phrasing overstatement, a missing diagnostic signal, and a cross-document completeness gap in `WP-ERROR-020` — all corrected within that same review) per **SF-SPEC-001** Section 19, **SF-SPEC-005** Section 5.6, and **SF-SPEC-012**.

No Reference Implementation is currently designated by **SF-SPEC-001**; this entry's relationship to that designation, and to any future `WP-SCENARIO-XXX` runtime evidence, is not asserted here and shall not be assumed until such evidence or designation actually exists.
