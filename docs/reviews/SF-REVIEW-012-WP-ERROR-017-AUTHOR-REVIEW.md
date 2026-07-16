# SF-REVIEW-012 — WP-ERROR-017 Author Review

# 1. Review Information

**Review ID:** SF-REVIEW-012

**Review Date:** 2026-07-13

**Reviewer:** Class A — Author Review, per **SF-SPEC-012** Section 6.1. Performed by the same authoring process that drafted WP-ERROR-017, within the same work-order execution.

**Status:** Complete

Per **SF-SPEC-012** Section 6.1, this Class A review may identify and correct defects but shall not, by itself, authorize a **Production Ready** designation. For purposes of this review, "Approved" means the artifact is ready to proceed to independent (Class B) review, not that any lifecycle promotion is authorized.

---

# 2. Artifact Reviewed

`WP-ERROR-017` — Must-Use Plugin Fatal Error, Version 1.0, at `docs/knowledge/wp-errors/WP-ERROR-017-MUST-USE-PLUGIN-FATAL-ERROR.md`. Status at time of this review: Draft.

---

# 3. Governing Specifications

- **SF-SPEC-001 — Error Knowledge Specification**
- **SF-SPEC-012 — Engineering Review Independence Specification** (for this review's own classification and authority)
- **SF-TEMPLATE-004 — WP-ERROR Knowledge Template**
- **SF-GLOSSARY-001 — Engineering Terminology**

---

# 4. Review Scope

This review evaluates whether WP-ERROR-017, as drafted, satisfies the governing work order's failure boundary (must-use plugin code specifically, distinguished from WP-ERROR-013, WP-ERROR-016, ordinary plugin fatals, theme failures, WP-ERROR-014, WP-ERROR-015, network-activated plugins, and drop-ins) and SF-SPEC-001's authoring standards, and whether it is ready to proceed to independent review. It does not authorize Production Ready.

---

# 5. Review Criteria

- SF-SPEC-001 Section 5 (Required Document Structure), Section 6 (Metadata Standard), Section 7 (Category Standard)
- SF-SPEC-001 Section 4.3 (Single Responsibility), Section 9 (Writing Standard), Section 10 (Scope Standard)
- The work order's explicit required characteristics: automatic loading, no activation/deactivation state, flat-directory loading behavior, recovery/isolation techniques, diagnostic methods, validation procedures, prevention practices

---

# 6. Evidence Examined

- Full contents of `WP-ERROR-017-MUST-USE-PLUGIN-FATAL-ERROR.md`, read in full both before and after correction.
- `grep -in "flat\|malware"` (no match pre-correction; two matches post-correction).
- `grep -n '\bmust\b' | grep -v "must-use"` (no bare "must" pre- or post-correction).
- `grep -Ein 'TODO|TBD|placeholder|future work|planned|should consider|to be determined|intended to be added'` (no match).
- `grep -c '^# [0-9]\+\.'` (17, matching SF-TEMPLATE-004).
- `git diff --check` (clean).
- Independent verification of the cited WordPress mechanics before inclusion: must-use plugins load via a flat, non-recursive directory glob in alphabetical order, before regular active plugins; no activation/deactivation lifecycle or hook exists for them; a read-only "Must-Use" tab exists on the Plugins screen; network-activated plugins and drop-ins use distinct loading mechanisms and are correctly excluded.

---

# 7. Findings

| Finding ID | Severity | Observation | Correction |
|---|---|---|---|
| F-1 | Minor | The work order explicitly named "flat-directory loading behavior" as a required characteristic to document, but the literal word "flat" never appeared anywhere in the drafted entry, even though the underlying mechanism was fully and correctly explained. | Added an explicit sentence in Section 8 naming this "a flat-directory loading behavior" alongside the existing explanation. |
| F-2 | Minor | Section 15 (Security Considerations) used "malicious code" where the established, already-reviewed convention from `WP-ERROR-016` (per `SF-REVIEW-010`/`SF-REVIEW-011`) is to use the more searchable literal term "malware" for this exact category of concern. | Changed "malicious code" to "malware" in Section 15, for consistency with the established corpus convention. |
| — | Conforming | Failure boundary matches the work order exactly: owns only verified must-use plugin code defects; excludes WP-ERROR-013's general symptom class, WP-ERROR-016's core-file condition, ordinary activatable-plugin fatals, theme failures, WP-ERROR-014's extension condition, WP-ERROR-015's version condition, network-activated plugins, and drop-ins — the last two added as explicit distinctions beyond the work order's own list, since both are commonly confused with must-use plugins in practice. | None. |
| — | Conforming | The absence of any activation/deactivation lifecycle, and the resulting absence of any built-in fatal-error protection (unlike ordinary plugin activation, which WordPress does guard), is stated accurately and is the correct basis for this entry's Critical severity and distinct recovery approach. | None. |
| — | Conforming | The two isolation techniques (moving a single suspect file out of the flat-loaded directory vs. renaming the entire must-use plugin directory) are presented in correct least-invasive-first order, consistent with SF-SPEC-001 §12's Diagnosis Standard. | None. |
| — | Conforming | Security Considerations correctly identifies must-use plugins as a known malware persistence location and requires investigating the compromise vector, not just removing the file — consistent with the analogous treatment in WP-ERROR-016. | None. |
| — | Conforming | Structure: all 17 SF-TEMPLATE-004 sections present, in order, sequentially numbered, none empty. | None. |
| — | Conforming | Related Errors: all four citations (WP-ERROR-013, 014, 015, 016) are real, existing repository documents, correctly linked and ordered numerically. | None. |

---

# 8. Recommendations

None beyond the corrections already applied.

---

# 9. Outcome

**Approved (for purposes of proceeding to independent review only).**

**Basis:** Both findings were narrow searchability/consistency gaps, corrected within this same review. No boundary, distinction, diagnostic-safety, recovery-safety, or structural defect was found. This outcome does not authorize Production Ready; per SF-SPEC-012 Section 6.1, a Class A review cannot do so regardless of its outcome.

WP-ERROR-017 remains `Draft`.

---

# 10. Revision History

| Version | Date | Summary of Changes | Approval Status |
|---|---|---|---|
| 1.0 | 2026-07-13 | Initial author review of WP-ERROR-017. Two Minor findings identified and corrected within this review. | Approved (Class A; does not authorize Production Ready) |
