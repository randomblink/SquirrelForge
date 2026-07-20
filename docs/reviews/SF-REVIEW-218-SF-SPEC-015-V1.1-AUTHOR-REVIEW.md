# SF-REVIEW-218 — SF-SPEC-015 Version 1.1 Author Review

# 1. Review Information

**Review ID:** SF-REVIEW-218

**Review Date:** 2026-07-20

**Reviewer:** Class A — Author Review, per **SF-SPEC-012** Section 6.1

**Status:** Complete

---

# 2. Artifact Reviewed

`SF-SPEC-015` — Runtime Acquisition and Integrity Verification Specification, Version 1.1 (revision of the Version 1.0 baseline established by `SF-REVIEW-200`/`201`).

---

# 3. Governing Artifacts

- **SF-TEMPLATE-001 — Engineering Specification Template**
- **SF-SPEC-002 — Runtime Evidence Specification**
- **SF-SPEC-004 — Documentation Specification**
- **SF-SPEC-005 — Engineering Review Specification**
- **SF-SPEC-008 — Versioning Specification**
- **SF-SPEC-011 — Evidence Governance Specification**
- **SF-SPEC-012 — Engineering Review Independence Specification**
- `SF-SPEC-015` Version 1.0, as the immediately preceding version this revision must remain traceable to (**SF-SPEC-008** Section 4.2).

---

# 4. Review Scope

This review checks that the Version 1.1 revision: (a) is motivated by demonstrated evidence rather than speculation; (b) leaves Sections 5.1–5.8 (WordPress Core acquisition) completely unchanged; (c) does not silently retitle, weaken, or duplicate any other specification's ownership; (d) uses normative "shall" language consistent with the rest of the document; (e) is structurally complete under **SF-TEMPLATE-001** and **SF-SPEC-008**; and (f) does not retroactively alter or resume `WP-VERIFICATION-012`. It does not re-review Sections 1–4 or 5.1–5.8's own content, which are unchanged from the Version 1.0 baseline `SF-REVIEW-200`/`201` already approved.

---

# 5. Evidence and Findings

**Motivating evidence.** `WP-VERIFICATION-012`'s accepted backend specification required acquisition-integrity rigor for three non-WordPress-Core components — Redis 8.8.0, PhpRedis 6.3.0, and Redis Object Cache 2.8.0. Live checks performed during that campaign (recorded in project session evidence, not repository files) found no publisher-published digest for the exact versioned artifact in any of the three cases: `download.redis.io/releases/redis-8.8.0.tar.gz` has no `.sha256`/`.SHA256SUM`/`.asc` sidecar; PECL's REST metadata for PhpRedis 6.3.0 and the `phpredis/phpredis` GitHub release for that tag carry no checksum; and `downloads.wordpress.org`'s plugin package for Redis Object Cache 2.8.0, the WordPress.org plugins API, and the `rhubarbgroup/redis-cache` GitHub release for that tag likewise carry none. Only the rolling `redis-stable.tar.gz` alias published a checksum, which was independently recalculated and matched, and which was confirmed content-identical to `redis-8.8.0.tar.gz` via a full-tree comparison. This is the same class of gap `SF-REVIEW-200` Section 9 (Remaining Risks) had already anticipated for Version 1.0: "Supporting components may lack independent published integrity material and can still block acquisition under Section 5.9." That anticipated risk materialized, and this revision is the specification's response to it — not a reaction to a single download failure, which Section 9 (Change Control) of this specification prohibits as sole justification for revision.

**Scope discipline.** Sections 5.1–5.8 are unmodified in this diff; only Sections 1.1, 2.1, 3.1, 5.9, and 5.12 changed, plus the Revision History. This preserves Version 1.0's WordPress Core Tier 1/2/3 model exactly as `SF-REVIEW-201` certified it. The three existing repository cross-references to specific WordPress-Core subsections (`RUNTIME-ACQUISITION-LOG.md`'s citation of "Sections 5.3, 5.5, and 5.6") remain valid, since no existing subsection was renumbered — the new content was added as `5.9.1`–`5.9.5` beneath the existing `5.9`, and `5.10`–`5.13` retain their original numbers.

**Boundary check.** The new Tier A/B/C model is deliberately parallel to, but textually distinct from, the WordPress Core Tier 1/2/3 model in Section 5.1 — different label scheme (letters vs. numbers) makes it unambiguous in cross-references which model a citation means. Section 5.9's opening paragraph and 5.9.1 both explicitly state that Sections 5.1–5.8 are not amended, relaxed, or reinterpreted by this section, addressing the risk that a future reader could mistake the new tiers as loosening WordPress Core's own gate.

**Finding F-1 (Minor, identified and corrected in this review):** the initial draft of Section 5.9 added Tiers A/B/C and their evidence/stop-condition requirements but had no equivalent to Section 5.2's Rejected Sources for supporting components, leaving an asymmetry between the two models. Corrected by adding Section 5.9.5 (Rejected Supporting-Component Sources), adapted from 5.2's structure. Re-verified after correction: 5.9 now has an internal shape (tiers, evidence, stop conditions, rejected sources) that mirrors 5.1–5.2's shape for WordPress Core, without duplicating its wording.

**Template and versioning compliance.** Version, Status, and Document Information fields updated consistently with **SF-SPEC-008** Section 6 (Status set to Draft pending review completion, matching the Section 1.0→1.1 pattern `SF-REVIEW-054`/`055` established for `SF-SPEC-005`). Revision History (Section 11) appends a new row without altering the 1.0 row, per **SF-SPEC-008** Section 5.7. No bare "must," no TODO/TBD language, introduced.

No findings beyond F-1, which was corrected within this review per **SF-SPEC-012** Section 6.1's Class A authority to identify and correct defects.

---

# 6. Architecture Boundary Review

Section 5.9's expansion continues to own only supporting-component acquisition and provenance — the same class of responsibility Section 3.1 already claimed for WordPress Core. It does not assign itself runtime-evidence sufficiency (still **SF-SPEC-002**), evidence retention (still **SF-SPEC-011**), or repository validation (still **SF-SPEC-006**); Section 3.3 (Does Not Define) is unchanged. No new dependency cycle was introduced: 5.9 references Section 4.6 (an existing principle within this same specification) and does not reach into another specification's normative text.

This revision does not touch `SF-BASELINE-001` or any framework baseline declaration, and does not itself resume, alter, or reinterpret `WP-VERIFICATION-012`'s accepted backend specification or its suspended status.

---

# 7. Outcome

**Approved with Minor Revisions.**

* Required revision: Finding F-1 — add Section 5.9.5 (Rejected Supporting-Component Sources). **Resolved within this review.**

---

# 8. Gate Decision

This Class A review establishes `SF-SPEC-015` Version 1.1 as author-reviewed, with its one Minor finding corrected in-place. It does not independently authorize Production Ready; per **SF-SPEC-005** Section 5.6 and **SF-SPEC-012** Section 6.2, that requires a Class B (independent) review, which this revision routes to `SF-REVIEW-219`.

---

# 9. Remaining Risks

- Tier B (content-verified alternate-artifact provenance) has motivating evidence from one component (Redis) but no completed Reference Implementation yet; Section 10 (Reference Implementations) correctly designates none.
- A future ecosystem could publish a digest through a mechanism not contemplated here (for example, a transparency-log-based scheme rather than a simple checksum file); Tier A's "integrity mechanism (checksum, digest, or signature)" wording is intended to be mechanism-agnostic, but this has not been tested against such a case.
- This revision does not decide whether `WP-VERIFICATION-012` should resume under the amended framework or remain blocked under the original wording — that remains the project owner's decision, per the suspension terms already recorded for that campaign.

---

# 10. Revision History

| Version | Date | Summary | Status |
|---|---|---|---|
| 1.0 | 2026-07-20 | Initial author review of SF-SPEC-015 v1.1; one Minor finding (F-1, missing Rejected Supporting-Component Sources) identified and corrected within this review. | Approved with Minor Revisions — Class A |
