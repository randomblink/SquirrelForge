# SF-REVIEW-200 — SF-SPEC-015 Author Review

# 1. Review Information

**Review ID:** SF-REVIEW-200

**Review Date:** 2026-07-17

**Reviewer:** Class A — Author Review, per **SF-SPEC-012** Section 6.1

**Status:** Complete

---

# 2. Artifact Reviewed

`SF-SPEC-015` — Runtime Acquisition and Integrity Verification Specification, Version 1.0.

---

# 3. Governing Artifacts

- **SF-TEMPLATE-001 — Engineering Specification Template**
- **SF-SPEC-002 — Runtime Evidence Specification**
- **SF-SPEC-004 — Documentation Specification**
- **SF-SPEC-005 — Engineering Review Specification**
- **SF-SPEC-008 — Versioning Specification**
- **SF-SPEC-011 — Evidence Governance Specification**
- **SF-SPEC-012 — Engineering Review Independence Specification**

---

# 4. Review Scope

This review checks structural completeness, normative-language precision, source-tier accuracy, ownership boundaries, failure classification, provenance requirements, cache rules, and the deliberate deferral of automation. It also checks that the new specification responds to demonstrated WP-VERIFICATION-009 acquisition failures without treating those failures as runtime evidence.

---

# 5. Evidence and Findings

The official WordPress Release Archive was checked directly. For WordPress 7.0.1 it publishes exact ZIP and tar.gz packages with MD5 and SHA-1 values; it does not publish SHA-256 on that release row. SF-SPEC-015 therefore requires comparison with the strongest checksum WordPress.org actually publishes for the exact archive (SHA-1 in this case) and separately calculates SHA-256 as SquirrelForge's local cache fingerprint. The specification explicitly prohibits presenting that locally calculated fingerprint as an official checksum.

The source tiers preserve a fail-closed hierarchy. Tier 2 does not assume that a Git tag is signed merely because it exists in an official repository; signature verification is a required gate, so an unsigned or unverifiable tag is unavailable rather than silently downgraded. Tier 3 requires a previously verified Tier 1 archive and complete provenance, preventing a random local package from becoming trusted through self-comparison.

Boundary review across the specification library found no existing owner for runtime acquisition, archive provenance, cache admission, or acquisition stop conditions. SF-SPEC-002 begins with an established runtime baseline but does not define how the Core input becomes trusted. SF-SPEC-011 governs retention after classification but does not define acquisition gates. SF-SPEC-006 governs repository validation. SF-SPEC-015 cites those owners rather than restating their requirements.

The document follows SF-TEMPLATE-001 Sections 1–11, contains no bare `must`, no TODO/TBD language, a complete Revision History, and an explicit distinction between specification Production Ready status and a future process implementation's Production Ready status.

No findings requiring correction.

---

# 6. Architecture Boundary Review

`SF-SPEC-015` owns only the prerequisite supply-chain process for WordPress Core runtime inputs. It does not own runtime conclusions, knowledge correction, installation behavior, general evidence retention, or repository validation. No duplicate ownership or problematic dependency cycle was found.

The framework baseline remains historically declared at v2. Creating a Production Ready specification after that declaration does not silently rewrite or broaden `SF-BASELINE-001`; a future Framework Baseline v3 would require its own readiness review and declaration under SF-SPEC-014. No baseline declaration is part of this work.

---

# 7. Outcome

**Approved.**

---

# 8. Gate Decision

This Class A review establishes SF-SPEC-015 as author-reviewed. It does not independently authorize Production Ready; that decision belongs to the Class B review `SF-REVIEW-201`.

---

# 9. Remaining Risks

- No successful acquisition or verified cache exists yet; the specification is governance, not a completed implementation.
- Official signing and checksum mechanisms can change and shall be rechecked when the specification is applied.
- Supporting components may lack independent published integrity material and can still block acquisition under Section 5.9.

---

# 10. Revision History

| Version | Date | Summary | Status |
|---|---|---|---|
| 1.0 | 2026-07-17 | Initial author review; no findings. | Approved — Class A |
