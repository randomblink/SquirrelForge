# SF-REVIEW-150 — WP-ERROR-037 Version 1.1 Independent Review

# 1. Review Information

**Review ID:** SF-REVIEW-150

**Review Date:** 2026-07-15

**Reviewer:** Class B — Independent Review, per **SF-SPEC-012** Section 6.2, conducted after `SF-REVIEW-149`, with preliminary findings recorded before comparison, per this catalog's established independence discipline.

**Status:** Complete

---

# 2. Artifact Reviewed

`WP-ERROR-037` Version 1.1, together with `WP-VERIFICATION-001` and the companion `SF-TAXONOMY-007` Version 1.4 correction.

---

# 3. Governing Specifications

- **SF-SPEC-013** Section 5.6 (Post-Certification Change)
- **SF-SPEC-002** (Runtime Evidence — Sections 4.2 Deterministic Evidence, 4.4 Independent Validation)
- **SF-SPEC-012** Section 6.2, Section 8 (independence requirements)

---

# 4. Preliminary Independent Findings (recorded before comparison with SF-REVIEW-149)

Rather than accept `WP-VERIFICATION-001`'s own account, this review independently re-derived the two underlying WordPress-core facts from the still-available disposable pilot environment:

- **Message text, independently re-verified:** `grep -c "not permitted for security reasons" wp-includes/functions.php` → `0`. Direct read of `wp-includes/functions.php:2912` confirms the real message: `'Sorry, you are not allowed to upload this file type.'`. Matches both `WP-VERIFICATION-001`'s claim and the corrected entry text.
- **Capability logic, independently re-verified:** fresh read of `wp-includes/capabilities.php`, the `unfiltered_upload` case in `map_meta_cap()`: capability granted only if `ALLOW_UNFILTERED_UPLOADS` is defined and true, and (on multisite) the user is a super admin; otherwise `do_not_allow`. Matches both the verification record's claim and the corrected entry text.
- **Determinism (SF-SPEC-002 Section 4.2), independently tested:** re-ran the Cause 1 trigger fresh, with a newly-created fixture file, against the same environment. Result: identical rejection message, identical `Error: No items imported.`, attachment count remained `0` afterward — the same outcome as `WP-VERIFICATION-001`'s own original execution, confirming the evidence is reproducible rather than a one-off artifact of the first run.
- **Independent check of what was *not* changed:** re-read `WP-ERROR-037` Section 6 (Distinction), Section 7 (Scope), Section 10 (Common Causes), Section 12 (Recovery Procedure) in full. Confirmed the two-cause partition, every Distinction bullet, the Scope's Covered/Excluded lists, and every recovery category are textually identical to Version 1.0 except for the corrected capability-mechanism phrasing already accounted for — no undisclosed scope drift introduced alongside the intended correction.
- **Independent check of `SF-TAXONOMY-007`'s companion correction:** re-read the corrected Section 3 table row and the new 1.4 Revision History entry directly. Confirmed the `allow_unfiltered_uploads` (lowercase filter) reference is fully removed and replaced with the real `ALLOW_UNFILTERED_UPLOADS` constant, and independently re-ran `grep -rn "allow_unfiltered_uploads" wp-includes/ wp-admin/` against the pilot environment — zero matches, confirming the removed claim was indeed fabricated/nonexistent, not merely differently-cased.
- **Independent check of `WP-VERIFICATION-001` itself:** re-read the record in full. Its Section 4 (Baseline), Section 9 (Negative Validation), and Section 10 (Cleanup Evidence) are consistent with the commands and outcomes described; nothing in the record overstates what was actually executed. Its Classification (Permanent, Section 12) and Retention Decision (Section 13) are consistent with `SF-SPEC-011` Section 5.1's scheme as applied elsewhere in this catalog's own evidence-adjacent artifacts.

---

# 5. Comparison with SF-REVIEW-149

`SF-REVIEW-149`'s findings (zero defects, correction accurate and complete) are independently corroborated: this review's own fresh source re-verification and fresh re-trigger reached the same factual conclusions through independently re-executed evidence gathering, not by reading `SF-REVIEW-149`'s own account. No discrepancy between the two reviews.

---

# 6. Findings

| Finding ID | Severity | Requirement or Criterion | Observation | Evidence | Required Action | Resolution Status |
|---|---|---|---|---|---|---|
| — | Conforming | Message text accuracy | Independently re-confirmed via fresh source grep. | Section 4 above. | None. | N/A |
| — | Conforming | Capability logic accuracy | Independently re-confirmed via fresh source read. | Section 4 above. | None. | N/A |
| — | Conforming | SF-SPEC-002 §4.2 Determinism | Independently re-triggered; identical outcome to WP-VERIFICATION-001's original execution. | Section 4 above. | None. | N/A |
| — | Conforming | Scope/boundary stability | Independently re-read; no undisclosed drift beyond the intended correction. | Section 4 above. | None. | N/A |
| — | Conforming | SF-TAXONOMY-007 companion correction | Independently re-verified the removed filter claim is genuinely nonexistent in core, and the replacement constant is accurately named. | Section 4 above. | None. | N/A |
| — | Conforming | WP-VERIFICATION-001 internal accuracy | Independently re-read; record's own account matches what was actually executed. | Section 4 above. | None. | N/A |

No Minor, Major, or Critical findings.

---

# 7. Outcome

**Approved.**

**Basis:** every factual claim underlying this correction — the message text, the capability grant mechanism, and the nonexistence of the previously-cited filter — was independently re-derived from primary source and from a fresh re-execution against the disposable pilot environment, not accepted from either `WP-VERIFICATION-001`'s or `SF-REVIEW-149`'s own account. All independently corroborated. The correction is additionally confirmed deterministic, satisfying `SF-SPEC-002` Section 4.2.

---

# 8. Gate Decision

`WP-ERROR-037` Version 1.1 has completed both Class A (`SF-REVIEW-149`) and Class B (this review) with zero outstanding findings. Per `SF-SPEC-013` Section 5.6, the next required step is a new category consistency review for Media (`SF-REVIEW-151`), followed by a new baseline certification (`SF-REVIEW-152`), since a Baseline Certified category's own designation does not automatically carry forward through a revised entry.

---

# 9. Remaining Risks

- Both this review and `SF-REVIEW-149` were conducted by the same class of agent (Claude Code); no reviewer diversity exists yet for this correction.
- This is the first post-certification entry correction in this catalog (Section 1, `SF-REVIEW-149`) — the process itself, though grounded directly in `SF-SPEC-013` Section 5.6's own text, is being executed for the first time.
- The disposable pilot environment (WordPress 7.0.1) remains available in this session's scratchpad but is not part of the repository; a future independent re-verification of this same correction would need to stand up its own environment rather than reuse this one across sessions.
- `WP-VERIFICATION-001` and this correction are scoped to WordPress 7.0.1; neither claims currency against any other WordPress version.

---

# 10. Revision History

| Version | Date | Summary of Changes | Approval Status |
|---|---|---|---|
| 1.0 | 2026-07-15 | Initial independent review of WP-ERROR-037 Version 1.1. Every underlying factual claim independently re-derived from primary WordPress core source and a fresh re-execution against the disposable pilot environment, corroborating SF-REVIEW-149 without relying on its account. Confirmed deterministic per SF-SPEC-002 Section 4.2. No findings. Approved. Category consistency review and baseline re-certification required next per SF-SPEC-013 Section 5.6. | Approved |
