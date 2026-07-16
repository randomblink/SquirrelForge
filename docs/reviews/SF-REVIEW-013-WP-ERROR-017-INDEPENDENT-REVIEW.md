# SF-REVIEW-013 — WP-ERROR-017 Independent Review

# 1. Review Information

**Review ID:** SF-REVIEW-013

**Review Date:** 2026-07-13

**Reviewer:** Class B — Independent Review, per **SF-SPEC-012** Section 6.2. Conducted in a fresh review context, beginning from the governing specifications and the artifact itself. Preliminary findings and a preliminary outcome were recorded before `SF-REVIEW-012` was read, per **SF-SPEC-012** Section 8 (Independence).

**Status:** Complete

---

# 2. Artifact Reviewed

`WP-ERROR-017` — Must-Use Plugin Fatal Error, Version 1.0, at `docs/knowledge/wp-errors/WP-ERROR-017-MUST-USE-PLUGIN-FATAL-ERROR.md`. Reviewed in its post-author-review-correction state.

---

# 3. Governing Specifications

- **SF-SPEC-001 — Error Knowledge Specification**
- **SF-SPEC-012 — Engineering Review Independence Specification** (governs this review's own independence requirements and classification)
- **SF-TEMPLATE-004 — WP-ERROR Knowledge Template**
- **SF-GLOSSARY-001 — Engineering Terminology**
- The governing work order's failure boundary (must-use plugin code specifically, distinguished from WP-ERROR-013, WP-ERROR-016, ordinary plugin fatals, theme failures, WP-ERROR-014, WP-ERROR-015, network-activated plugins, and drop-ins), used as the review criteria

---

# 4. Review Scope

This review independently determines whether WP-ERROR-017 satisfies the governing work order's requirements and SF-SPEC-001's authoring standards, and is eligible to advance from `Draft` to `Production Ready` under **SF-SPEC-001** Section 19 and **SF-SPEC-005** Section 5.6, applying the reviewer-class framework defined by **SF-SPEC-012**.

---

# 5. Independence Requirements Applied

Per **SF-SPEC-012** Section 8: began from the governing specifications and the artifact itself (Section 6 below); recorded preliminary findings before opening `SF-REVIEW-012`; reached conclusions independently; discloses limitations in Section 9; preserves `SF-REVIEW-012` unmodified; records disagreement, where any exists, rather than silently adopting the author review's framing.

---

# 6. Preliminary Independent Findings (recorded before reading SF-REVIEW-012)

A fresh, full read of WP-ERROR-017 was performed against SF-SPEC-001's requirements and the work order's explicit required characteristics. Areas checked with no finding: metadata (correct ID, title, `Plugin` category — approved per SF-SPEC-001 §7 — Critical, Immediate, Draft, 1.0); failure boundary (owns only verified must-use plugin code defects; excludes WP-ERROR-013's general symptom class, WP-ERROR-016's core-file condition, WP-ERROR-014's extension condition, WP-ERROR-015's version condition, ordinary activatable plugins, network-activated plugins, drop-ins, and theme failures); the core technical claims (flat, non-recursive directory loading in alphabetical order; no activation/deactivation lifecycle or hook; the read-only "Must-Use" Plugins-screen tab; the distinct loading mechanisms of network-activated plugins and drop-ins) were independently verified as accurate; the two isolation techniques (single-file move vs. whole-directory rename) are presented in correct least-invasive-first order; security treatment correctly requires investigating the compromise vector, not just removing a suspicious file; structure (17 sequential SF-TEMPLATE-004 sections, none empty, no drafting language, no bare "must" outside "must-use").

One finding was identified independently:

| Finding ID | Severity | Observation |
|---|---|---|
| IF-1 | Minor | Common Causes did not mention Composer-managed WordPress deployments (for example, Bedrock-style project structures), where a single must-use plugin file commonly bootstraps a Composer autoloader — a real, increasingly common pattern in professional WordPress deployments where a missing or misconfigured `vendor/autoload.php` is itself a distinct, common cause of exactly this failure mode. |

**Preliminary Outcome (before reading SF-REVIEW-012): Approved with Minor Revisions.** One Minor, non-architectural finding; does not change the owned failure mode; correctable without redesign.

---

# 7. Comparison with SF-REVIEW-012

`SF-REVIEW-012` was read only after Section 6 above was finalized.

**Classification of SF-REVIEW-012:** Correctly self-identified as Class A — Author Review. Retained as valid author-review history, not treated as independent verification.

**Findings independently reproduced:** SF-REVIEW-012's F-1 (missing literal "flat" term) and F-2 ("malicious code" vs. "malware" consistency) were both already corrected in the artifact this review read, so neither was reproduced as an open finding. Both corrections were independently re-verified as technically sound and consistent with the library's established conventions (the same "malware" terminology fix previously applied in WP-ERROR-016).

**New findings absent from SF-REVIEW-012:** IF-1 (Composer/Bedrock-style bootstrap failure as a common cause) is new.

**Unsupported conclusions in SF-REVIEW-012:** None identified. Every finding and Conforming determination was traceable to specific evidence.

**Corrections made previously that are technically valid:** Both of SF-REVIEW-012's corrections are independently confirmed technically valid, with no regression.

**Effect on this review's outcome:** None. The preliminary outcome (Approved with Minor Revisions, based on IF-1) is carried forward unchanged, per the instruction not to alter the independent outcome to match the earlier review.

---

# 8. Final Findings and Correction

| Finding ID | Severity | Requirement or Criterion | Observation | Required Action | Resolution Status |
|---|---|---|---|---|---|
| IF-1 | Minor | SF-SPEC-001 §9 (technical accuracy/completeness) | Common Causes lacked coverage of Composer/Bedrock-style must-use plugin bootstrapping as a distinct, common cause. | Add a Common Causes bullet on Composer autoloader bootstrap failure in must-use plugins. | Resolved |

**Correction applied:** Added to Common Causes: "In Composer-managed WordPress deployments (for example, Bedrock-style project structures), a single top-level must-use plugin file that bootstraps a Composer autoloader failing because the expected `vendor/autoload.php` is missing, was not deployed, or points to a path that does not match the current deployment."

Re-validated: drafting-language sweep (no match), bare-`must` sweep (no match outside "must-use"), section-numbering sweep (17, sequential), `git diff --check` (clean).

No Major or Critical findings. All other areas remain Conforming as recorded in Section 6.

---

# 9. Remaining Risks

- This review was conducted by the same class of agent (Claude Code) as the authoring pass and SF-REVIEW-012, though as a distinct pass beginning from the specification and artifact rather than from SF-REVIEW-012's conclusions. A reviewer from a genuinely separate party was not used. Disclosed consistent with the precedent set in SF-REVIEW-004, SF-REVIEW-007, SF-REVIEW-009, and SF-REVIEW-011.
- No Reference Implementation exists under SF-SPEC-001 for WP-ERROR entries to compare this one against.

---

# 10. Outcome

**Approved with Minor Revisions.**

**Basis:** WP-ERROR-017 is fundamentally sound. Its failure boundary, required distinctions from all seven neighboring/related conditions named in the work order, technical accuracy, diagnostic safety, recovery safety, validation sufficiency, prevention guidance, security considerations, structure, and normative language all conform to the governing work order and SF-SPEC-001 without further correction. The single finding raised (IF-1) was narrow, non-architectural, did not change the owned failure mode, and was corrected and re-validated within this same review.

---

# 11. Production Ready Gate Decision

This review satisfies the Production Ready gate defined by **SF-SPEC-001** Section 19 and **SF-SPEC-005** Section 5.6 for `WP-ERROR-017`, per the Class B review authority defined by **SF-SPEC-012** Section 6.2 and Section 12. The outcome is Approved with Minor Revisions; the one required revision has been completed and re-validated within this review. `WP-ERROR-017`'s Status may accordingly be changed from `Draft` to `Production Ready`.

This gate decision does not designate `WP-ERROR-017` as a Reference Implementation under SF-SPEC-001 Section 22.

---

# 12. Revision History

| Version | Date | Summary of Changes | Approval Status |
|---|---|---|---|
| 1.0 | 2026-07-13 | Initial independent review of WP-ERROR-017. One new Minor finding identified independently of SF-REVIEW-012, corrected, and re-validated. | Approved with Minor Revisions — Production Ready gate satisfied |
