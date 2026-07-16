# SF-REVIEW-010 — WP-ERROR-016 Author Review

# 1. Review Information

**Review ID:** SF-REVIEW-010

**Review Date:** 2026-07-13

**Reviewer:** Class A — Author Review, per **SF-SPEC-012** Section 6.1. Performed by the same authoring process that drafted WP-ERROR-016, within the same work-order execution.

**Status:** Complete

Per **SF-SPEC-012** Section 6.1, this Class A review may identify and correct defects but shall not, by itself, authorize a **Production Ready** designation. For purposes of this review, "Approved" means the artifact is ready to proceed to independent (Class B) review, not that any lifecycle promotion is authorized.

---

# 2. Artifact Reviewed

`WP-ERROR-016` — WordPress Core Files Missing or Corrupted, Version 1.0, at `docs/knowledge/wp-errors/WP-ERROR-016-WORDPRESS-CORE-FILES-MISSING-OR-CORRUPTED.md`. Status at time of this review: Draft.

---

# 3. Governing Specifications

- **SF-SPEC-001 — Error Knowledge Specification**
- **SF-SPEC-012 — Engineering Review Independence Specification** (for this review's own classification and authority)
- **SF-TEMPLATE-004 — WP-ERROR Knowledge Template**
- **SF-GLOSSARY-001 — Engineering Terminology**

---

# 4. Review Scope

This review evaluates whether WP-ERROR-016, as drafted, satisfies the governing work order's failure boundary (core-file integrity, distinguished from WP-ERROR-010 through WP-ERROR-015), required distinctions, and SF-SPEC-001's authoring quality standards, and whether it is ready to proceed to independent review. It does not authorize Production Ready.

---

# 5. Review Criteria

- SF-SPEC-001 Section 5 (Required Document Structure), Section 6 (Metadata Standard), Section 7 (Category Standard)
- SF-SPEC-001 Section 4.3 (Single Responsibility), Section 9 (Writing Standard), Section 10 (Scope Standard)
- The work order's explicit boundary: WordPress core file integrity, distinguished from WP-ERROR-010 (config missing), WP-ERROR-011 (config invalid), WP-ERROR-012 (config syntax error), WP-ERROR-013 (general bootstrap fatal), WP-ERROR-014 (extension missing), and WP-ERROR-015 (unsupported version)

---

# 6. Evidence Examined

- Full contents of `WP-ERROR-016-WORDPRESS-CORE-FILES-MISSING-OR-CORRUPTED.md`, read in full both before and after correction.
- `grep -in "malware"` (no match pre-correction; one match post-correction, plus one in Notes).
- `grep -n '\bmust\b' | grep -v "must-use"` (no bare "must" pre- or post-correction; all three raw matches were "must-use").
- `grep -Ein 'TODO|TBD|placeholder|future work|planned|should consider|to be determined|intended to be added'` (no match).
- `grep -c '^# [0-9]\+\.'` (17, matching SF-TEMPLATE-004).
- `git diff --check` (clean).
- Independent verification that `wp core verify-checksums` checks only WordPress core files (not `wp-content/`), and that `wp plugin verify-checksums` is a separate, real WP-CLI command for plugin integrity — confirmed accurate before citing it.

---

# 7. Findings

| Finding ID | Severity | Observation | Correction |
|---|---|---|---|
| F-1 | Minor | The high-value search term "malware" — likely the single most common term an engineer would search for this exact scenario — was never used; the entry only used "malicious code," "backdoor," and "defacement." | Changed "injected malicious code" to "injected malware" in Common Causes. |
| F-2 | Minor | The boundary exclusion of `wp-content/` (plugins, themes, drop-ins) did not acknowledge that a parallel, separate WP-CLI capability (`wp plugin verify-checksums`) exists for verifying plugin integrity, which would help a reader who reaches this entry while actually needing that different capability. | Added a sentence to Section 6 (Distinction) noting the separate `wp plugin verify-checksums` capability, without redefining or absorbing it. |
| F-3 | Minor | Recovery Procedure's prohibition on directly editing WordPress core files used softer, non-normative phrasing ("is not appropriate") inconsistent with the established normative convention used for the identical principle in WP-ERROR-013 ("shall not be used as a routine corrective action"). | Reworded to match the established phrasing exactly: "is not a normal repair method and shall not be used as a routine corrective action." |
| — | Conforming | Failure boundary matches the work order exactly: owns only verified core-file integrity conditions; excludes `wp-config.php` conditions (WP-ERROR-010/011/012), the general bootstrap-fatal symptom class (WP-ERROR-013), extension availability (WP-ERROR-014), PHP version (WP-ERROR-015), `wp-content/` files, filesystem permissions on intact files, and database corruption. | None. |
| — | Conforming | `wp core verify-checksums` is correctly described as depending on WP-CLI itself being able to bootstrap, with a manual-comparison fallback for cases where it cannot — consistent with the same principle already established in WP-ERROR-013 and WP-ERROR-014 for other diagnostic tooling. | None. |
| — | Conforming | Security Considerations correctly treats file restoration as insufficient on its own when alteration was malicious, requiring the compromise vector to be addressed — distinct from WP-ERROR-014/015, which have no comparable security-incident dimension, appropriately reflecting this entry's own specific risk profile. | None. |
| — | Conforming | Structure: all 17 SF-TEMPLATE-004 sections present, in order, sequentially numbered, none empty. | None. |
| — | Conforming | Related Errors: WP-ERROR-010/011/012 cited as conceptual-only (no link, matching their non-existence in this repository); WP-ERROR-013/014/015 linked as real, existing repository documents; all six ordered numerically. | None. |

---

# 8. Recommendations

None beyond the corrections already applied.

---

# 9. Outcome

**Approved (for purposes of proceeding to independent review only).**

**Basis:** All three findings were narrow — a searchability gap, a boundary-clarity gap, and a normative-language consistency gap — each corrected within this same review. No boundary, distinction, diagnostic-safety, recovery-safety, or structural defect was found. This outcome does not authorize Production Ready; per SF-SPEC-012 Section 6.1, a Class A review cannot do so regardless of its outcome.

WP-ERROR-016 remains `Draft`.

---

# 10. Revision History

| Version | Date | Summary of Changes | Approval Status |
|---|---|---|---|
| 1.0 | 2026-07-13 | Initial author review of WP-ERROR-016. Three Minor findings identified and corrected within this review. | Approved (Class A; does not authorize Production Ready) |
