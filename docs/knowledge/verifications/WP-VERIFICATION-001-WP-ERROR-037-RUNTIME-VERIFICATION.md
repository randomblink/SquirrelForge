# WP-VERIFICATION-001 — WP-ERROR-037 Runtime Verification

Structured per `SF-TEMPLATE-005` (Runtime Evidence Record Template). Governed by `SF-SPEC-002` (Runtime Evidence), `SF-SPEC-006` (Repository Validation), `SF-SPEC-011` (Evidence Governance). This is the first record produced under the **Reference Implementation** track (see `docs/engineering/KNOWLEDGE-PRODUCTION-PLAN.md` Section 9 / `SF-METHODOLOGY-001`), and the first `WP-VERIFICATION-XXX` record in this catalog — a new, `WP-ERROR`-scoped identifier series, distinct from `WP-SCENARIO-XXX` (which supports a different portfolio, agent-capability demonstration, governed additionally by `SF-SPEC-003`/`007`'s scenario lifecycle machinery that this series does not use). This record reuses `SF-TEMPLATE-005` directly rather than a newly drafted template, since that template's own Section 2 already generalizes to "`WP-SCENARIO-XXX` or other artifact" — no existing governance gap justified drafting a parallel one.

---

# 1. Evidence Record Identity

**Record ID:** `WP-VERIFICATION-001`

**Date:** 2026-07-15

---

# 2. Associated Scenario or Artifact

`WP-ERROR-037` — WordPress Upload File Type Rejected, at `docs/knowledge/wp-errors/WP-ERROR-037-UPLOAD-FILE-TYPE-REJECTED.md` (Version 1.0 at the time this record's execution began; see Section 8 below for the correction this record's own findings produced).

---

# 3. Objective

Determine whether `WP-ERROR-037`'s own documented claims — its two causes' triggering conditions, the `unfiltered_upload` capability mechanism, and WordPress's own user-facing rejection message — hold up against actual runtime behavior on a real, current WordPress installation, rather than remaining unverified "reasoned knowledge."

**Expected behavior, per the entry as documented (Version 1.0):** a disallowed-extension upload and an extension/content-mismatch upload should each be rejected with the message *"Sorry, this file type is not permitted for security reasons."* A user should be able to bypass the allowed-types restriction only if they hold the `unfiltered_upload` capability, described as available by default and only "uncommon... for security reasons," with `DISALLOW_UNFILTERED_UPLOADS` as the only capability-related constant the entry names.

---

# 4. Baseline

- WordPress version: 7.0.1 (current `latest.zip` release as of 2026-07-15).
- Fresh install, no plugins active beyond the `sqlite-database-integration` drop-in (Section 5).
- One administrator user (`admin`), default capabilities, no roles or capabilities modified.
- `ALLOW_UNFILTERED_UPLOADS` and `DISALLOW_UNFILTERED_UPLOADS` both confirmed undefined at baseline (`wp eval 'var_export(defined(...))'`).
- Media library empty (`wp post list --post_type=attachment --format=count` → `0`).
- `wp-content/uploads/` empty (`find` → no results).
- PHP `fileinfo` extension confirmed loaded (`php -m | grep fileinfo`).
- `wp-content/debug.log` present but containing no WordPress-core-generated entries relevant to this record prior to execution.

---

# 5. Environment

Disposable, local-only environment, isolated from the "Hospital WordPress installation" referenced in `38_WORDPRESS/AGENT-READINESS-REPORT.md` (that installation was explicitly not used for this or any `WP-VERIFICATION-XXX` record without separate, explicit authorization):

- WordPress core 7.0.1, downloaded from `https://wordpress.org/latest.zip`.
- Official **SQLite Database Integration** plugin (`sqlite-database-integration`, version 2.2.23, from the WordPress.org plugin directory), installed as the `wp-content/db.php` drop-in per its own `db.copy` mechanism — avoids requiring a MySQL/MariaDB server, none of which was available in this execution environment.
- WP-CLI (`wp-cli.phar`, latest build from the official `wp-cli/builds` releases).
- PHP 8.5.7 (system PHP), no web server process required for this record — every command executed via WP-CLI directly against the WordPress bootstrap.
- Entire environment created under this session's scratchpad directory, outside the SquirrelForge repository; nothing from the environment itself (WordPress core, the SQLite plugin, WP-CLI) is committed to the repository. Only this evidence record and the resulting corrections to `WP-ERROR-037`/`SF-TAXONOMY-007` are committed.

---

# 6. Execution Procedure

1. Confirmed baseline (Section 4).
2. Created three fixture files: `test-script.php` (PHP source, disallowed extension — targets Cause 1), `fake-image.jpg` (plain-text content saved with a `.jpg` extension — targets Cause 2), `valid-control.jpg` (a genuine, minimal real JPEG — targets neither cause, confirms the environment accepts a legitimate upload rather than rejecting everything indiscriminately).
3. Executed `wp media import <file> --user=admin` against each of the three fixtures in turn, capturing full command output verbatim.
4. Independently searched WordPress core source (`wp-includes/functions.php`, `wp-includes/capabilities.php`, and the full `wp-includes`/`wp-admin` trees) for the exact message text `WP-ERROR-037` documents, and for the `unfiltered_upload` capability's own actual grant logic, rather than relying on the WP-CLI output alone.

---

# 7. Evidence Artifacts

- **Cause 1 trigger (disallowed extension):** `wp media import test-script.php --user=admin` → `Warning: Unable to import file '...test-script.php'. Reason: Sorry, you are not allowed to upload this file type.` / `Error: No items imported.`
- **Cause 2 trigger (extension/content mismatch):** `wp media import fake-image.jpg --user=admin` → identical message: `Warning: Unable to import file '...fake-image.jpg'. Reason: Sorry, you are not allowed to upload this file type.` / `Error: No items imported.`
- **Control (valid upload):** `wp media import valid-control.jpg --user=admin` → `Imported file '...valid-control.jpg' as attachment ID 4.` / `Success: Imported 1 of 1 items.` — confirms the environment and validation pathway accept a genuinely valid file; the two rejections above are not an artifact of a broken environment.
- **Source verification, message text:** `grep -rn "not permitted for security reasons" wp-includes/ wp-admin/` → zero matches anywhere in WordPress core 7.0.1. `grep -rn "not allowed to upload this file type" wp-includes/` → one match, `wp-includes/functions.php:2912`, inside `wp_handle_upload()`'s own error path: `return array( 'error' => __( 'Sorry, you are not allowed to upload this file type.' ) );`.
- **Source verification, capability grant logic:** `wp-includes/capabilities.php`, `map_meta_cap()`, case `'unfiltered_upload'`: the capability is granted only if `defined( 'ALLOW_UNFILTERED_UPLOADS' ) && ALLOW_UNFILTERED_UPLOADS && ( ! is_multisite() || is_super_admin( $user_id ) )`; otherwise `do_not_allow`. Empirically confirmed: `wp eval 'var_export( current_user_can( "unfiltered_upload" ) );' --user=admin` → `false`, on a fresh single-site install with neither `ALLOW_UNFILTERED_UPLOADS` nor `DISALLOW_UNFILTERED_UPLOADS` defined.
- **Source verification, `allow_unfiltered_uploads` (lowercase, as a filter):** `grep -rn "allow_unfiltered_uploads" wp-includes/ wp-admin/` → zero matches. No such filter exists anywhere in WordPress core 7.0.1.

---

# 8. Validation

The objective (Section 3) is achieved: this record demonstrates that `WP-ERROR-037`'s documented *mechanism* is accurate — both causes are real, independently triggerable, and produce a genuine WordPress-level rejection rather than a false claim — but surfaces three specific, source-verified inaccuracies in the surrounding documentation, none of which change the entry's Scope, Distinction, or Diagnosis structure.

**Differences from documentation:**

1. **Message text.** `WP-ERROR-037` Sections 8 and 9 state WordPress's message is *"Sorry, this file type is not permitted for security reasons."* This string does not exist anywhere in WordPress core 7.0.1. The actual message, confirmed both by direct trigger and by source, is *"Sorry, you are not allowed to upload this file type."*
2. **`unfiltered_upload` grant mechanism undocumented.** `WP-ERROR-037` Sections 6, 8, and 11 document only the capability's *revocation* (`DISALLOW_UNFILTERED_UPLOADS`) and describe the capability itself as merely "uncommon by default" — worded as though it is ordinarily available to an Administrator and only unusually revoked. Actual core behavior is stronger: the capability requires an explicit, additional, undocumented-in-this-entry constant (`ALLOW_UNFILTERED_UPLOADS`, defined `true`) to be granted at all; without it, even an unmodified Administrator account lacks the capability by default, with no `DISALLOW_UNFILTERED_UPLOADS` involved. Diagnosis Section 11 step 3 ("attempt the identical upload as a user known to hold the `unfiltered_upload` capability") gives a reader no way to know how such a user would come to hold it.
3. **`SF-TAXONOMY-007`'s own planned-entries table** (line 55, Section 3) additionally names a filter, `allow_unfiltered_uploads` (lowercase), as something the file-type validation is "governed by... where applicable." No such filter exists anywhere in WordPress core. This is a taxonomy-level inaccuracy, not merely an entry-level one, and independently triggers the taxonomy-revision step of `SF-SPEC-013` Section 5.6's post-certification change process.

No defect was found in the entry's or taxonomy's core boundary claims (the two-cause partition, the exclusions in Section 6, the category boundary itself) — only in specific implementation-level details neither had been checked against real WordPress source before this record.

**Required repository changes:** `WP-ERROR-037` (Version 1.0 → 1.1: corrected message text in Sections 8/9, corrected capability-grant mechanism in Sections 6/8/11) and `SF-TAXONOMY-007` (Version 1.3 → 1.4: corrected Section 3 table row). Both processed through **SF-SPEC-013** Section 5.6's post-certification change process — see Section 15 below.

---

# 9. Negative Validation

- Attachment count after both rejected uploads: `0` (confirmed before importing the control file) — neither rejected file was partially accepted or left an orphan database record.
- `wp-content/uploads/` contents after both rejected uploads: empty — neither rejected file was written to the filesystem in any form.
- No PHP fatal error, warning, or notice attributable to WordPress core (as opposed to WP-CLI's own pre-existing PHP 8.5 deprecation noise, unrelated to this record) appeared in `wp-content/debug.log` during either trigger.
- The control upload's success (Section 7) confirms the two rejections are genuine, targeted validation outcomes, not a symptom of a broken or misconfigured environment rejecting every upload indiscriminately.

---

# 10. Cleanup Evidence

- Control attachment (ID 4) deleted: `wp post delete 4 --force` → `Success: Deleted post 4.`
- Attachment count confirmed back to `0` after cleanup.
- `wp-content/uploads/` confirmed empty after cleanup.
- Fixture files (`test-script.php`, `fake-image.jpg`, `valid-control.jpg`) removed from the scratch environment; their content is fully preserved above (Section 6/7) since this record itself is the durable evidence artifact, per `SF-SPEC-002` Section 5.6's "unless artifact preservation is explicitly required" — the record, not the disposable environment, is what is preserved.
- The WordPress installation itself (core, SQLite drop-in, WP-CLI) remains in the session scratchpad for potential reuse by a future `WP-VERIFICATION-XXX` record but is not part of the SquirrelForge repository and is not itself a durable project artifact.

---

# 11. Repository Validation Evidence

Per `SF-SPEC-006`: this record's own creation, and the corrections to `WP-ERROR-037`/`SF-TAXONOMY-007` it supports, are committed together (see the corrected artifacts' own Revision History). `git status --short` confirmed clean immediately before this record's own work began. No temporary artifact from the disposable WordPress environment (Section 5) is present anywhere in the repository working tree — confirmed via `git status --short` showing only the intended, reviewed documentation changes.

---

# 12. Classification

**Permanent**, per `SF-SPEC-011` Section 5.1 — this record is the evidentiary basis for a specific, cited correction to two other permanent catalog artifacts (`WP-ERROR-037`, `SF-TAXONOMY-007`) and is referenced by name from both. It is not disposable or temporary evidence.

---

# 13. Retention Decision

Retain permanently alongside the corrected artifacts it supports. Do not delete or supersede in place; a future re-verification against a later WordPress version should be recorded as a new `WP-VERIFICATION-XXX` record citing this one, per this catalog's established preservation-over-correction-in-place discipline (`SF-SPEC-013` Section 5.8's own principle, applied here to evidence records rather than knowledge entries).

---

# 14. Traceability Map

- **Scenario/Artifact:** `WP-ERROR-037` (Section 2).
- **Implementation:** None — this record verifies existing documentation against existing WordPress behavior; no SquirrelForge code was implemented or modified.
- **Validation:** Section 8 above.
- **Documentation:** `WP-ERROR-037` Version 1.1 (corrected Sections 6, 8, 9, 11, 17) and `SF-TAXONOMY-007` Version 1.4 (corrected Section 3 table row), both citing this record. `docs/engineering/KNOWLEDGE-PRODUCTION-PLAN.md` Section 10, covering the Reference Implementation track.

---

# 15. Engineering Review Status

Examined as part of `SF-REVIEW-149` (Class A author review) and `SF-REVIEW-150` (Class B independent review) of the resulting `WP-ERROR-037` Version 1.1 correction; see those records for findings. This record's own evidence artifacts (Section 7) were independently re-derived by `SF-REVIEW-150` from primary source rather than accepted from this record's own account, consistent with this catalog's established independent-review discipline.

---

# 16. Revision History

| Version | Date | Summary of Changes | Approval Status |
|---|---|---|---|
| 1.0 | 2026-07-15 | Initial record. Documents the first Reference Implementation pilot: a disposable local WordPress 7.0.1 + SQLite installation used to runtime-verify WP-ERROR-037's documented mechanism, message text, and unfiltered_upload capability logic. Found the mechanism accurate but the message text, the capability's own grant condition, and a taxonomy-level filter reference all inaccurate against real WordPress core source. | Draft — reviewed via SF-REVIEW-149/150 |
| 1.1 | 2026-07-15 | Structural-only update: retrofitted to the newly-formalized `WP-VERIFICATION-XXX` series convention (`docs/knowledge/verifications/README.md`) — added an explicit "Expected behavior" sub-point to Section 3 and explicit "Differences from documentation" / "Required repository changes" sub-points to Section 8. No factual content changed; corrected two stale cross-references (SF-TAXONOMY-007 version number, KNOWLEDGE-PRODUCTION-PLAN.md section number) found while retrofitting. | Draft — reviewed via SF-REVIEW-149/150 (substance unchanged) |
