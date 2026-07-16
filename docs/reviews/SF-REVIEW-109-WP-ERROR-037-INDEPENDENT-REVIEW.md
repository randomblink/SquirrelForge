# SF-REVIEW-109 — WP-ERROR-037 Independent Review

# 1. Review Information

**Review ID:** SF-REVIEW-109

**Review Date:** 2026-07-14

**Reviewer:** Class B — Independent Review, per **SF-SPEC-012** Section 6.2. Conducted in a fresh review pass, beginning from `SF-TAXONOMY-007` and the artifact itself. Preliminary findings and a preliminary outcome were recorded before `SF-REVIEW-108` was re-opened for comparison, per **SF-SPEC-012** Section 8 (Independence).

**Status:** Complete

---

# 2. Artifact Reviewed

`WP-ERROR-037` — WordPress Upload File Type Rejected, Version 1.0, at `docs/knowledge/wp-errors/WP-ERROR-037-UPLOAD-FILE-TYPE-REJECTED.md`. Reviewed in its post-author-review state (as it stood after `SF-REVIEW-108`, which recorded no corrections to this entry's own text).

---

# 3. Governing Specifications

- Same as `SF-REVIEW-108`.

---

# 4. Review Scope

This review independently determines whether `WP-ERROR-037` satisfies `SF-TAXONOMY-007`'s Version 1.1 boundary and the project owner's own four named boundaries, with particular attention to one thing a fresh reading is best positioned to catch: whether the entry's own treatment of the `unfiltered_upload` capability is complete, re-derived independently against current WordPress core behavior rather than accepted at face value.

---

# 5. Independence Requirements Applied

Per **SF-SPEC-012** Section 8: began from `SF-TAXONOMY-007` and the artifact itself; independently re-derived WordPress's own `unfiltered_upload` capability-grant mechanics from first principles, specifically to test whether the entry's own Section 6/8/10 coverage of it is complete; recorded preliminary findings before opening `SF-REVIEW-108`; preserves it unmodified.

---

# 6. Preliminary Independent Findings (recorded before reading SF-REVIEW-108)

Structural checks (bare-`must`, drafting-language, section count, section numbering, link resolution) independently re-run: clean.

This review independently re-derived how the `unfiltered_upload` capability is actually granted and revoked in current WordPress core, to test whether the entry's own coverage (Section 6 cause 1, Section 8, Section 10) is complete. The entry correctly describes the capability itself and its default availability (or lack thereof, particularly on multisite), but omits `DISALLOW_UNFILTERED_UPLOADS` — a real, documented `wp-config.php` constant that, when defined `true`, revokes the capability network- or site-wide *regardless of role*, including from Administrators who would otherwise hold it. This is a concrete, commonly-recommended security-hardening measure, and its addition or removal is a plausible, verifiable cause this entry's own Common Causes list (Section 10) does not currently name, despite the entry otherwise treating the capability-dependent nature of this gate as a first-class diagnostic concern (Section 11, step 3).

| Finding ID | Severity | Observation |
|---|---|---|
| IF-1 | Minor | The entry's own treatment of the `unfiltered_upload` capability does not mention `DISALLOW_UNFILTERED_UPLOADS`, a real `wp-config.php` constant that revokes the capability regardless of role — a concrete, plausible, and currently-unlisted cause. |

**Preliminary Outcome (before reading SF-REVIEW-108): Approved with Minor Revisions.** One Minor finding, a completeness gap in an otherwise well-covered mechanism.

---

# 7. Comparison with SF-REVIEW-108

`SF-REVIEW-108` was read only after Section 6 above was finalized.

**Classification of SF-REVIEW-108:** Correctly self-identified as Class A. Retained as valid author-review history.

**Findings independently reproduced:** none of `SF-REVIEW-108`'s Conforming dispositions are disputed; all independently re-confirmed, including its own account of all four project-owner-named boundaries.

**New findings absent from SF-REVIEW-108:** IF-1 is new. `SF-REVIEW-108`'s own Section 6 confirmed the capability-dependent nature of the gate was correctly identified as a diagnostic concern, but did not independently re-derive the capability-grant mechanism deeply enough to notice the missing constant.

**Effect on this review's outcome:** IF-1 requires additions to `WP-ERROR-037` Section 6, Section 8, Section 10, and Section 11. It does not require any revision to `SF-TAXONOMY-007`.

---

# 8. Final Findings and Correction

| Finding ID | Severity | Requirement or Criterion | Observation | Required Action | Resolution Status |
|---|---|---|---|---|---|
| IF-1 | Minor | Completeness (Principle: Evidence Over Assertion) | `DISALLOW_UNFILTERED_UPLOADS` was not mentioned anywhere in the entry, despite being a concrete, commonly-recommended mechanism directly relevant to cause 1. | Add `DISALLOW_UNFILTERED_UPLOADS` to Section 8 (Components), Section 10 (Common Causes), and Section 11 (Diagnosis step 3). | Resolved |

**Corrections applied:**
- `WP-ERROR-037` Section 8 gained a new bullet: "The `DISALLOW_UNFILTERED_UPLOADS` `wp-config.php` constant, which, where defined `true`, revokes the `unfiltered_upload` capability network- or site-wide regardless of role, including from an Administrator who would otherwise hold it — a common, deliberate security-hardening measure."
- `WP-ERROR-037` Section 10 gained a new bullet: "`DISALLOW_UNFILTERED_UPLOADS` being defined `true` — commonly added during a security-hardening pass — revoking the `unfiltered_upload` capability from every user regardless of role, narrowing the allowed-types list uniformly across the installation."
- `WP-ERROR-037` Section 11 step 3 extended: "...where the identical upload fails even for a user expected to hold `unfiltered_upload` (commonly an Administrator), confirm whether `DISALLOW_UNFILTERED_UPLOADS` is defined `true` in the active `wp-config.php`, which revokes the capability regardless of role."

No Major or Critical findings. All other areas remain Conforming as recorded in Section 6.

---

# 9. Outcome

**Approved with Minor Revisions.**

**Basis:** `WP-ERROR-037`'s own boundary, two-cause separation, and all four project-owner-named boundaries are all sound and independently re-verified. The single finding (IF-1) was a completeness gap in the entry's own `unfiltered_upload` coverage, corrected within this same review, and did not require any revision to `SF-TAXONOMY-007`.

---

# 10. Gate Decision

Per **SF-SPEC-001** Section 19, **SF-SPEC-005** Section 5.6, and **SF-SPEC-012**: this review satisfies the required review sequence for `WP-ERROR-037`. Its Status may accordingly be changed from `Draft` to **`Production Ready`** — the thirty-third knowledge entry in this repository and the second in the Media category.

---

# 11. Remaining Risks

- This review was conducted by the same class of agent (Claude Code) as the authoring pass and `SF-REVIEW-108`.
- No runtime scenario or evidence record under **SF-SPEC-002**/**SF-SPEC-003** exists for this entry.
- `SF-TAXONOMY-007`'s own status table still lists `WP-ERROR-037` as `Planned`; shall be updated to `Existing, Production Ready` in the same body of work that promotes this entry, per **SF-SPEC-013** Section 5.7.
- One planned Media entry (`WP-ERROR-038`) remains unauthored; `SF-TAXONOMY-007` Section 4's sequential-pipeline ownership model remains untested against the full three-entry set until it is drafted.
- IF-1's added `DISALLOW_UNFILTERED_UPLOADS` claim is cited as a real, documented WordPress constant based on independent technical assessment, not verified against a live WordPress installation as part of this review.

---

# 12. Revision History

| Version | Date | Summary of Changes | Approval Status |
|---|---|---|---|
| 1.0 | 2026-07-14 | Initial independent review of WP-ERROR-037. One Minor finding (IF-1: the entry's own unfiltered_upload coverage omitted DISALLOW_UNFILTERED_UPLOADS, a real, concrete revocation mechanism, added to Section 8/10/11) identified and corrected. Approved with Minor Revisions; Production Ready gate satisfied — the thirty-third entry in this repository and the second in the Media category. Confirmed SF-TAXONOMY-007 required no revision. | Approved with Minor Revisions — Production Ready gate satisfied |
