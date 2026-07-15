# WP-ERROR-037 — WordPress Upload File Type Rejected

---

# 1. Knowledge Entry

WordPress Upload File Type Rejected

---

# 2. Metadata

* **Error ID:** `WP-ERROR-037`
* **Title:** WordPress Upload File Type Rejected
* **Category:** Media
* **Severity:** High
* **Recovery Priority:** High
* **Status:** Production Ready
* **Version:** 1.0

---

# 3. Summary

An uploaded file that has already passed every applicable size check is rejected because WordPress's own file-type validation determines its extension is not permitted, or that its actual content does not match what its extension claims — a distinct, later gate in the same upload pipeline `WP-ERROR-036` owns the earlier stage of, evaluated before the file ever reaches the filesystem-write stage `WP-ERROR-019`/`020` own.

---

# 4. Primary Failure Mode

WordPress's own file-type validation, centered on `wp_check_filetype_and_ext()`, evaluates an uploaded file at two distinct points before it may be accepted: first, whether the file's own extension appears in the currently-applicable allowed-types list (`get_allowed_mime_types()`, filterable via `upload_mimes`, and itself varying by installation type and by the uploading user's own capability); and second — where the environment's own capability supports it — whether the file's actual content genuinely matches the type its extension claims, rejecting a mismatch even where the extension alone would have been permitted. This entry's condition occurs when either check determines the file is not acceptable, and is a deliberate, designed rejection rather than a malfunction: WordPress is refusing to accept a specific file, not failing to process one it should have accepted.

---

# 5. Severity

This entry is classified **High** rather than **Critical**, the same considered exception already established for `WP-ERROR-036`'s own condition:

- Every manifestation is scoped to the specific upload attempt; ordinary browsing, content editing, and every non-upload feature continue to function normally regardless of which cause applies.
- No plausible manifestation of this entry's own condition produces a PHP fatal error or a site-wide outage; the rejection is a designed, non-destructive gate operating exactly as intended, not a defect.
- This entry is nonetheless **High** rather than **Medium** or **Low**, and Recovery Priority **High** rather than **Immediate**, for the same reason `WP-ERROR-036` is: upload capability is frequently business-critical, and an overly restrictive or misconfigured file-type gate can block an entire class of legitimate content (for example, every SVG or every video format a site's own workflow depends on) rather than a single file.

---

# 6. Distinction

This entry applies only when verified evidence establishes that a file already within any applicable size limit was rejected specifically because of its own type — not because of its size, because the filesystem write itself failed, or because subsequent image processing failed on an already-accepted file.

**Two internal causes this entry keeps deliberately separate, since each points toward a different corrective action:**

1. **Extension not in the allowed-types list** — the file's own extension does not appear in the currently-applicable, filtered allowed-types list at all. This list is not a single, fixed set: it can be narrowed on a multisite installation relative to a single-site default, and it is itself broader for a user holding the `unfiltered_upload` capability (uncommon by default, particularly on multisite, for security reasons) than for one who does not — the *same* file can be accepted for one user and rejected for another on the identical installation, a diagnostic detail worth confirming directly rather than assuming the allowed-types list is uniform across users.
2. **Extension allowed, but content-type verification finds a mismatch** — the extension itself is permitted, but WordPress's own additional content-verification step — checking the file's actual content against its claimed type, using PHP's `fileinfo` extension or an image-specific detection function where available — determines the two do not genuinely match, and rejects the file as a spoofing/mismatch prevention measure even though the extension alone would have passed cause 1's own check.

It is distinct from:

- **`WP-ERROR-036` — WordPress Upload Size Limit Exceeded**: the earlier gate in the same pipeline (per `SF-TAXONOMY-007` Section 4). This entry presumes the file already passed every applicable size check.
- **`WP-ERROR-019` — WordPress Filesystem Permission Denied** and **`WP-ERROR-020` — WordPress Disk Space Exhausted**: both presume the file has already passed this entry's own type validation and PHP is actually attempting to write it to the filesystem. This entry's own condition is resolved before that write is ever attempted.
- **`WP-ERROR-038` — WordPress Image Processing Failure**: presumes the file has already passed this entry's own validation and been fully accepted and written to the filesystem; this entry's own condition never reaches that point.
- **`WP-ERROR-014` — Required PHP Extension Missing**: where the `fileinfo` extension (or another dependency the content-verification step in cause 2 relies on) is unavailable, WordPress's own validation degrades gracefully to extension-only checking (cause 1) rather than failing outright — this is a designed fallback, not a malfunction requiring `WP-ERROR-014`'s own attention, and this entry does not hand off to it for that specific scenario. `WP-ERROR-014` remains the correct entry only where the missing extension itself, independent of this specific graceful degradation, is the actual condition under investigation.
- **A web-server- or WAF-level rule blocking the request based on file type or content before it ever reaches WordPress's own code**: a distinct, server- or security-appliance-configuration-level condition this entry does not own, though it can present with a symptom similar to this entry's own condition (an upload rejected for reasons related to its type); diagnosis (Section 11) distinguishes the two by confirming the request actually reached WordPress's own validation code.
- **A malicious file upload, and any resulting compromise investigation** (Security category, once a taxonomy exists for it): this entry documents the file-type gate's own designed, correctly-functioning rejection behavior — the mechanism working as intended to refuse a disallowed file type. It does not own investigating whether a *specific* rejected (or, more seriously, an accepted) file was a deliberate attack, nor the broader incident-response process that follows a confirmed compromise; Section 15 carries the same security-considerations discipline established throughout this catalog without claiming that broader ownership.

---

# 7. Scope

**Covered:** A verified condition in which an uploaded file, already within any applicable size limit, is rejected because WordPress's own file-type validation determines its extension is not in the currently-applicable allowed-types list, or that its actual content does not match its claimed type.

**Excluded:**

- The earlier size-limit gate (`WP-ERROR-036`).
- Any failure occurring after the file has already passed this entry's own validation (filesystem write, image processing — `WP-ERROR-019`/`020`/`038`).
- A web-server- or WAF-level rule rejecting the request based on file type before it ever reaches WordPress's own code.
- `fileinfo` (or another content-verification dependency) being unavailable, where WordPress's own validation degrades gracefully to extension-only checking rather than failing.
- Investigating whether a specific file was a deliberate attack, or any resulting compromise response.

---

# 8. WordPress Components

Listed as components commonly involved, not as a claim that every installation exercises every one of them:

- `wp_check_filetype_and_ext()` (`wp-includes/functions.php`), the primary validation function, combining extension checking with content-type verification where available.
- `get_allowed_mime_types()` and the `upload_mimes` filter, which together determine the currently-applicable, filterable allowed-types list — itself sensitive to whether the installation is multisite and to the uploading user's own capabilities.
- The `unfiltered_upload` capability, which, where granted, permits a user to bypass the standard allowed-types restriction — uncommon by default, particularly on multisite, for security reasons.
- The `DISALLOW_UNFILTERED_UPLOADS` `wp-config.php` constant, which, where defined `true`, revokes the `unfiltered_upload` capability network- or site-wide regardless of role, including from an Administrator who would otherwise hold it — a common, deliberate security-hardening measure.
- PHP's own `fileinfo` extension, and image-specific detection functions, which WordPress's own content-verification step (cause 2) relies on where available, degrading gracefully to extension-only checking where they are not.
- WordPress's own "Sorry, this file type is not permitted for security reasons." message, the most common user-facing symptom of this entry's own condition.

---

# 9. Typical Symptoms

- WordPress's own "Sorry, this file type is not permitted for security reasons." message, or an equivalent rejection, for a file within any applicable size limit.
- A specific file type (SVG, a particular video or audio format, an executable-adjacent extension) consistently failing to upload across multiple, otherwise-unrelated files sharing that same extension.
- The identical file uploading successfully for one user (commonly an Administrator) but failing for another, on the same installation — pointing toward a capability-dependent allowed-types difference (cause 1) rather than a condition intrinsic to the file itself.
- A file whose extension matches an evidently permitted type (for example, `.jpg`) still being rejected — pointing toward cause 2, a content-type mismatch, and worth independently verifying the file's own actual content genuinely matches its extension before assuming the rejection is a WordPress defect.
- The same file type uploading successfully on one environment (staging) but failing on another (production), where the two environments' own `upload_mimes` filtering, multisite configuration, or `fileinfo` extension availability differ.

---

# 10. Common Causes

Causes are grouped by category. Inclusion in this list identifies a category as plausible; it does not assert that any specific one is present without diagnostic confirmation.

- A file type genuinely absent from WordPress core's own default allowed-types list (for example, certain less-common formats not included by default) with no plugin or custom code adding it via the `upload_mimes` filter.
- A plugin or custom code having previously added a file type via `upload_mimes`, but that code being deactivated, removed, or updated in a way that no longer applies the same filter.
- A multisite installation's own narrower default allowed-types behavior, relative to what a single-site installation with the same active plugins would permit.
- The uploading user lacking the `unfiltered_upload` capability a previously-successful upload (by a different, more-privileged user) relied on.
- `DISALLOW_UNFILTERED_UPLOADS` being defined `true` — commonly added during a security-hardening pass — revoking the `unfiltered_upload` capability from every user regardless of role, narrowing the allowed-types list uniformly across the installation.
- A file whose extension does not genuinely match its own content — for example, a file renamed to appear as one type while its actual content is another — correctly triggering cause 2's own content-verification step, whether the mismatch was accidental (a corrupted export, an incorrect renaming) or deliberate.
- The `fileinfo` PHP extension being unavailable in the current environment, narrowing validation to extension-only checking and producing a different, generally more permissive outcome than an environment where content verification is also active.

---

# 11. Diagnosis

Verify the following, starting from the least invasive observation and narrowing only once the general shape of the failure is established:

1. **Confirm the failure is genuinely type-related**, as opposed to the earlier size gate (`WP-ERROR-036`) or a later filesystem-level failure (`WP-ERROR-019`/`020`) — check the exact message presented and confirm the file is within any applicable size limit.
2. **Confirm the request is actually reaching WordPress's own validation code** before concluding this entry's own condition applies, rather than a web-server- or WAF-level rule rejecting the request beforehand — check web-server-level logs for the specific request, since the two can present identically to the end user.
3. **Determine which of the two causes applies**: attempt the identical upload as a user known to hold the `unfiltered_upload` capability, where feasible, to isolate whether the rejection is capability-dependent (cause 1) or occurs regardless of the uploading user (pointing toward either a genuine allowed-types absence, cause 1, or a content mismatch, cause 2). Where the identical upload fails even for a user expected to hold `unfiltered_upload` (commonly an Administrator), confirm whether `DISALLOW_UNFILTERED_UPLOADS` is defined `true` in the active `wp-config.php`, which revokes the capability regardless of role.
4. **Where cause 1 is suspected, confirm the specific file extension's own current status** in `get_allowed_mime_types()`'s own effective, filtered output for the specific installation and user involved, rather than assuming the default WordPress core list applies unmodified.
5. **Where cause 2 is suspected, independently verify the file's own actual content** against its claimed type (for example, via a file-inspection utility outside WordPress) before concluding the rejection is a WordPress defect, since a genuine mismatch — accidental or deliberate — is the condition this specific check exists to catch.
6. **Confirm whether the `fileinfo` PHP extension is available** in the current environment, since its absence changes which of the two causes is even reachable — content verification (cause 2) cannot occur at all without it.
7. Preserve relevant evidence — the exact file, its own extension and actual content type, the uploading user's own capabilities, and the exact message presented — before making any change.
8. Where the engineer performing diagnosis does not control the relevant filter callbacks, multisite configuration, or PHP environment, escalate to the site owner or hosting provider rather than attempting an unverified workaround.

---

# 12. Recovery Procedure

Recovery shall target the specific, verified cause identified in Diagnosis (Section 11), and shall not broaden accepted file types beyond what the site's own legitimate needs actually require.

Permitted recovery categories, depending on the verified cause, include:

- Adding a genuinely required file type to the allowed-types list via the `upload_mimes` filter, scoped to the specific type(s) actually needed, rather than broadly disabling type validation.
- Granting the `unfiltered_upload` capability to a specific, trusted role or user where diagnosis confirms a legitimate, ongoing need for file types outside the standard allowed list — applied deliberately and narrowly, not as a general convenience measure, given the security implications of bypassing type validation (Section 15).
- Correcting a genuinely mismatched file (re-exporting, re-saving in its own claimed format) where diagnosis confirms cause 2 and the mismatch is accidental rather than a deliberate attempt to disguise the file's own actual content.
- Installing or re-enabling the `fileinfo` PHP extension where its absence is unintentionally narrowing validation to extension-only checking, and content verification is actually desired.
- Restoring a previously-active `upload_mimes` filter callback where diagnosis confirms a plugin deactivation or update removed one a site's own workflow depends on.

Recovery shall not disable file-type validation entirely, or grant `unfiltered_upload` broadly, as a general response to this condition; both remove a deliberate security control rather than addressing the specific, verified gap in the allowed-types list or content-verification behavior.

---

# 13. Validation

Recovery is successful when:

- The specific file, or a file of the same genuinely-required type, uploads successfully through the same path that previously failed.
- The corrected allowed-types list, capability grant, or `fileinfo` availability is confirmed to apply only as narrowly as the verified, legitimate need requires — not broadened beyond it.
- Where a content mismatch was the cause, the corrected file's own actual content is confirmed to genuinely match its claimed type, not merely that the upload no longer fails.
- No previously-restricted file type was inadvertently permitted as a side effect of the recovery.

---

# 14. Prevention

- Document every file type a site's own legitimate workflow requires beyond WordPress core's own default allowed-types list, and add each deliberately via `upload_mimes` rather than granting broad `unfiltered_upload` access as a shortcut.
- Restrict the `unfiltered_upload` capability to specifically the roles or users with a genuine, ongoing need for it, consistent with the general principle of granting the minimum access a role's own responsibilities require.
- Verify `fileinfo` extension availability, and effective `upload_mimes` filtering, as part of any environment migration or hosting change, rather than assuming both carried forward unchanged.
- Test file uploads across every user role a site's own content workflow actually depends on, not only as an Administrator, since the effective allowed-types list can differ by role.

---

# 15. Security Considerations

- WordPress's own file-type validation is a deliberate security control preventing an uploaded file from being interpreted as executable code (for example, a `.php` file disguised with an image extension); do not disable or weaken it as a troubleshooting shortcut, and treat a request to do so as warranting scrutiny of the underlying reason before complying.
- Granting the `unfiltered_upload` capability, or broadly expanding the allowed-types list, meaningfully increases the attack surface available to any user who can upload files; scope any such change to the minimum specific type(s) and role(s) actually required, per Section 12.
- A confirmed content-type mismatch (cause 2) that appears deliberate, rather than accidental, is worth treating as a potential probing or attack attempt, not solely as routine upload troubleshooting — see the Security-category exclusion in Section 6 for where that broader investigation belongs.
- Do not restore or "clean" a file confirmed to genuinely mismatch its own claimed type without first understanding why the mismatch exists, consistent with this catalog's established discipline for suspicious file content (`WP-ERROR-017` Section 15).

---

# 16. Related Errors

The following are cited as they exist in this repository, or as conceptual distinctions where noted.

1. [WP-ERROR-036 — WordPress Upload Size Limit Exceeded](WP-ERROR-036-UPLOAD-SIZE-LIMIT-EXCEEDED.md) — exists in this repository; see Section 6 (Distinction) above.
2. [WP-ERROR-019 — WordPress Filesystem Permission Denied](WP-ERROR-019-FILESYSTEM-PERMISSION-DENIED.md) and [WP-ERROR-020 — WordPress Disk Space Exhausted](WP-ERROR-020-DISK-SPACE-EXHAUSTED.md) — exist in this repository; see Section 6 (Distinction) above.
3. [WP-ERROR-014 — Required PHP Extension Missing](WP-ERROR-014-REQUIRED-PHP-EXTENSION-MISSING.md) — exists in this repository; see Section 6 (Distinction) above for why this entry does not hand off to it for the graceful-degradation scenario specifically.
4. WP-ERROR-038 — WordPress Image Processing Failure (conceptual reference; planned per `SF-TAXONOMY-007` Section 3, no corresponding document currently exists in this repository; no link is provided) — see Section 6 (Distinction) above.

---

# 17. Notes

This entry documents the general, verified observable condition of WordPress's own file-type validation rejecting an uploaded file, distinguishing the two mechanically distinct causes — an extension absent from the applicable allowed-types list, and a content-type mismatch detected by additional verification — at which that rejection can occur. It is the second entry drafted against `SF-TAXONOMY-007`.

This entry deliberately frames its own condition as the file-type gate's own designed, correctly-functioning rejection behavior, not a malfunction — the mechanism working as intended to refuse a disallowed or mismatched file. It explicitly does not extend that framing into ownership of a broader security-incident investigation, consistent with how this catalog has consistently treated the Security category as a still-taxonomy-less forward reference elsewhere.

Consistent with the single-responsibility principle in **SF-SPEC-001** Section 4.3, this entry does not claim ownership of the earlier size-limit gate, the later filesystem-write or image-processing stages, the underlying PHP-extension-availability question for its own graceful-degradation scenario, or any web-server-/WAF-level blocking that can present with a similar symptom.

This entry reached `Production Ready` via `SF-REVIEW-108` (Class A author review; no findings) and `SF-REVIEW-109` (Class B independent review; one Minor finding — IF-1, a completeness gap in this entry's own `unfiltered_upload`/`DISALLOW_UNFILTERED_UPLOADS` coverage, corrected within that same review) per **SF-SPEC-001** Section 19, **SF-SPEC-005** Section 5.6, and **SF-SPEC-012**.

No Reference Implementation is currently designated by **SF-SPEC-001**; this entry's relationship to that designation, and to any future `WP-SCENARIO-XXX` runtime evidence, is not asserted here and shall not be assumed until such evidence or designation actually exists.
