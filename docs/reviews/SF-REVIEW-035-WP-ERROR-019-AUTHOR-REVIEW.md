# SF-REVIEW-035 — WP-ERROR-019 Author Review

# 1. Review Information

**Review ID:** SF-REVIEW-035

**Review Date:** 2026-07-13

**Reviewer:** Class A — Author Review, per **SF-SPEC-012** Section 6.1. Performed by the same authoring process that drafted WP-ERROR-019, within the same work-order execution.

**Status:** Complete

Per **SF-SPEC-012** Section 6.1, this Class A review may identify and correct defects but shall not, by itself, authorize a **Production Ready** designation. For purposes of this review, "Approved" means the artifact is ready to proceed to independent review, not that any lifecycle promotion is authorized.

---

# 2. Artifact Reviewed

`WP-ERROR-019` — WordPress Filesystem Permission Denied, Version 1.0, at `docs/knowledge/wp-errors/WP-ERROR-019-FILESYSTEM-PERMISSION-DENIED.md`. Status at time of this review: Draft.

---

# 3. Governing Specifications

- **SF-SPEC-001 — Error Knowledge Specification**
- **SF-SPEC-012 — Engineering Review Independence Specification** (for this review's own classification and authority)
- **SF-TEMPLATE-004 — WP-ERROR Knowledge Template**
- **SF-GLOSSARY-001 — Engineering Terminology**
- **SF-TAXONOMY-001 — Filesystem Error Taxonomy**, Version 1.1 (post-`SF-REVIEW-034` correction), whose Section 3 declaration for this entry ("access denial on existing content, or a missing path blocked by a permission constraint on an ancestor directory") is the governing failure boundary

---

# 4. Review Scope

This review evaluates whether WP-ERROR-019, as drafted, satisfies `SF-TAXONOMY-001`'s declared boundary for this entry without narrowing or widening it, correctly implements the internal distinctions the taxonomy's own corrections (SELinux/AppArmor, read-only mounts, `open_basedir`, the FTP/SSH credential prompt) require, and satisfies SF-SPEC-001's authoring standards, and whether it is ready to proceed to independent review. It does not authorize Production Ready.

---

# 5. Review Criteria

- SF-SPEC-001 Section 5 (Required Document Structure), Section 6 (Metadata Standard), Section 7 (Category Standard — `Filesystem`), Section 8 (Severity Standard)
- SF-SPEC-001 Section 4.3 (Single Responsibility), Section 9 (Writing Standard), Section 10 (Scope Standard)
- `SF-TAXONOMY-001` Section 2 (Category Boundary — the six adjacent-category exclusions it names), Section 3 (this entry's declared boundary), Section 5 (the two rejected candidates this entry must correctly absorb: the FTP/SSH credential prompt as a symptom, and the uploads-directory candidate's causes as distributed across `019`/`020`/Configuration)

---

# 6. Precondition Verification

Before authoring, the status of every related entry was confirmed: `WP-ERROR-016` is Production Ready in this repository, correctly cited with a real link (`grep "Status:"` returns `Production Ready`). `WP-ERROR-020` does not exist, or has ever existed, in this repository (`git log --all --diff-filter=A --name-only -- "*WP-ERROR-020*"`, run during this review, returns no result); it is cited as a conceptual reference only, explicitly disclosed as planned per `SF-TAXONOMY-001` Section 3, with no link. `SF-TAXONOMY-001` itself was re-read at Version 1.1 (its post-`SF-REVIEW-034` corrected state) to confirm this entry is drafted against its current, corrected boundary rather than the earlier, gapped Version 1.0.

---

# 7. Evidence Examined

- Full contents of `WP-ERROR-019-FILESYSTEM-PERMISSION-DENIED.md`, read in full.
- `grep -c '^# [0-9]\+\.'` (17, matching SF-TEMPLATE-004).
- `grep -Ein 'TODO|TBD|placeholder|future work|planned|should consider|to be determined|intended to be added'` — two matches, both the deliberate, accurate word "planned" describing WP-ERROR-020's status per `SF-TAXONOMY-001` Section 3 (Section 16 citation and Section 17 Notes), not unfinished drafting language. No `TODO`/`TBD`/placeholder/`should consider`/`to be determined` match. Confirmed not a defect.
- `grep -n '\bmust\b' | grep -v "must-use"` — zero matches.
- `git diff --check` (clean).
- `git log --all --diff-filter=A --name-only -- "*WP-ERROR-019*"` and `"*WP-ERROR-020*"` (both empty, confirming neither existed before this session and `020` remains correctly unlinked).
- Link-target verification: the sole real link (`WP-ERROR-016-WORDPRESS-CORE-FILES-MISSING-OR-CORRUPTED.md`) resolves to an existing file.
- Independent verification of technical claims before inclusion, performed via current WordPress and PHP documentation: `wp_is_writable()`/`win_is_writable()` as WordPress's own workaround for a documented PHP `is_writable()` unreliability on ACL-based systems; `FS_METHOD`'s valid values (`direct`, `ssh2`, `ftpext`, `ftpsockets`) and `get_filesystem_method()`'s direct-write capability test; `wp_upload_dir()`'s "Unable to create directory ... Is its parent directory writable by the server?" message and the plugin/theme installer's "Installation Failed: Could Not Create Directory." message as real, documented WordPress-generated text; SELinux's `httpd_sys_content_t` versus `httpd_sys_rw_content_t` contexts and the `chcon`/`semanage fcontext` remedy; `open_basedir`'s documented behavior of resolving symlinks before comparison, meaning it cannot be bypassed via a symlink; and standard WordPress-recommended permissions (755 directories, 644 files, with `777` explicitly discouraged).

---

# 8. Findings

| Finding ID | Severity | Observation | Correction |
|---|---|---|---|
| — | Conforming | Failure boundary matches `SF-TAXONOMY-001` Section 3 exactly: covers access denial on existing content and creation blocked on a missing path by an ancestor's permission constraint; excludes missing/incorrect content (`WP-ERROR-016`), capacity exhaustion (`WP-ERROR-020`), and the three category exclusions the taxonomy's own corrected Section 2 names (Configuration, HTTP/web-server, Authentication/deployment-tool), plus PHP Runtime's `open_basedir`. | None. |
| — | Conforming | All five internal distinctions required by the taxonomy's own corrections are explicitly and separately addressed in Section 6: content-vs-access, access-vs-capacity, standard-bits-vs-MAC-layer (SELinux/AppArmor), ownership-vs-read-only-mount, and OS-permission-vs-`open_basedir`. The FTP/SSH credential prompt is explicitly documented as this entry's own symptom (per `SF-TAXONOMY-001` Section 5's rejection reasoning), not a separate condition. | None. |
| — | Conforming | The explicit instruction (via the taxonomy's own Section 5 correction) not to conflate the credential prompt with an independent failure mode is honored: Section 6 and Section 11 (Diagnosis item 9) both treat it strictly as confirmation that `get_filesystem_method()`'s own test failed, redirecting diagnosis to the underlying cause rather than treating the prompt as the condition to resolve. | None. |
| — | Conforming | Recovery Procedure explicitly prohibits mode `777` and disabling SELinux/AppArmor system-wide as shortcuts, consistent with the minimum-necessary-access principle already established for `WP-ERROR-004` in this catalog and explicitly referenced. | None. |
| — | Conforming | Severity classification (`Critical`, with an honestly acknowledged range from full-outage for a bootstrap-critical path to narrower impact for a single directory) mirrors the precedent established for `WP-ERROR-004`, `005`, and `006`. | None. |
| — | Conforming | Structure: all 17 SF-TEMPLATE-004 sections present, in order, sequentially numbered, none empty. | None. |
| — | Conforming | Related Errors: the one real citation (`WP-ERROR-016`) correctly linked; the one conceptual citation (`WP-ERROR-020`) correctly disclosed as planned-but-nonexistent with no link; both ordered numerically. | None. |
| — | Conforming | Technical grounding (PHP warning wording, `wp_is_writable()`, `FS_METHOD`, WordPress's own error message text, SELinux contexts, `open_basedir`'s symlink-resolution behavior) independently verified against current documentation rather than asserted from unverified recall. | None. |

No Minor, Major, or Critical findings. This is the first entry in this catalog whose author review identified zero corrections, which this review attributes to the taxonomy's own boundary having already absorbed the corrections a typical author review would otherwise catch (see `SF-REVIEW-034`), rather than to reduced scrutiny; the evidence in Section 7 reflects the same depth of independent verification applied to every prior entry.

---

# 9. Recommendations

None.

---

# 10. Outcome

**Approved (for purposes of proceeding to independent review only).**

**Basis:** No defect was found. The entry's failure boundary, all five required internal distinctions, technical grounding, structure, and cross-references conform exactly to `SF-TAXONOMY-001`'s own (corrected) declaration for this entry. This outcome does not authorize Production Ready; per SF-SPEC-012 Section 6.1, a Class A review cannot do so regardless of its outcome.

WP-ERROR-019 remains `Draft`.

---

# 11. Revision History

| Version | Date | Summary of Changes | Approval Status |
|---|---|---|---|
| 1.0 | 2026-07-13 | Initial author review of WP-ERROR-019. No findings; zero corrections required. Confirmed WP-ERROR-020 does not exist; confirmed WP-ERROR-016 exists, is Production Ready, and is correctly linked. Confirmed the entry's boundary matches SF-TAXONOMY-001 v1.1 exactly and all five required internal distinctions are explicitly addressed. | Approved (Class A; does not authorize Production Ready) |
