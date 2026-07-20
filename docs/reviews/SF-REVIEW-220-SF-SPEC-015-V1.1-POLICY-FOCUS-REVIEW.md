# SF-REVIEW-220 — SF-SPEC-015 Version 1.1 Policy-Focus Review

# 1. Review Information

**Review ID:** SF-REVIEW-220

**Review Date:** 2026-07-20

**Reviewer:** Class B — Independent Review, per **SF-SPEC-012** Section 6.2. An additional review per Section 10 ("Additional reviews may be performed when appropriate"), requested by the project owner as a deliberate final pass focused on the policy content itself before the amendment is committed.

**Status:** Complete

---

# 2. Artifact Reviewed

`SF-SPEC-015` — Runtime Acquisition and Integrity Verification Specification, Version 1.1, in its post-`SF-REVIEW-218`/`219` state (already Class A/B reviewed and authorized Production Ready).

---

# 3. Governing Artifacts

- **SF-SPEC-015** itself, Sections 5.9 and 5.12 as revised.
- `SF-REVIEW-218` (Class A) and `SF-REVIEW-219` (Class B), preserved and not superseded by this review — per **SF-SPEC-012** Section 10, this review documents additional findings rather than replacing either record.
- **SF-SPEC-012** Section 8, for the independence conduct of this review, and Section 9, for conflict-of-interest disclosure.

---

# 4. Review Scope

Unlike `SF-REVIEW-218`/`219`, this review does not re-derive the revision's diff scope or re-check template/versioning compliance — both already established. It evaluates only the policy content of Section 5.9 against five questions the project owner posed directly: generality, determinism, evidence burden, boundary explicitness, and WordPress Core compatibility. A finding outside these five questions is out of scope for this review, per **SF-SPEC-005** Section 5.2.

---

# 5. Findings by Question

**Generality — does Tier B describe a general rule, or does it encode the Redis case?**

Finding (Minor, corrected): the pre-review wording of 5.9.2's third Tier B condition read "a complete comparison between the alternate artifact's extracted contents and the exact requested artifact's extracted contents." "Extracted contents" implicitly assumes both artifacts are archives requiring extraction — true for the motivating Redis case (`tar.gz`), but not general. A supporting component distributed as a single binary or a non-archive file has no "extracted contents" to compare, even though the same content-equivalence reasoning applies to it. Corrected to compare "contents — their fully extracted trees, where the artifacts are archives, or their raw bytes, where an artifact is not an archive," removing the archive assumption without weakening the requirement.

**Determinism — would two independent reviewers reach the same tier classification from the same evidence?**

Finding (Minor, corrected): the pre-review text allowed an "explained difference" with only one example given ("for example, a top-level directory name") and no closed definition. Two reviewers could reasonably disagree on whether some other difference (for example, differing file timestamps, or a whitespace difference introduced by re-packaging) is "explainable." This is a real reproducibility gap: the same underlying comparison result could be classified as Tier-B-qualifying by one reviewer and disqualifying by another. Corrected by bounding "explained difference" to a closed category — top-level directory/archive name, file modification timestamps, and file/directory permission bits — and stating explicitly that any difference in file content, file count, or path structure beyond that category is unexplained and disqualifying. This makes the classification a mechanical check against a fixed list rather than an open judgment call.

**Evidence burden — are the required records for Tier B sufficient to reproduce and audit the determination?**

Finding (Minor, corrected): 5.9.3's Tier B evidence bullet required recording the comparison method and result, but not the specific tool and version used to perform it. Section 5.5 (WordPress Core provenance record) already requires "Verification tool names and versions" for Core acquisition; Tier B's evidence requirement was less rigorous than the Core standard it was modeled on, without stated justification. A different comparison tool (for example, one that ignores whitespace or line-ending differences by default) could reach a different result on the same inputs, so tool/version identity is necessary for a later auditor to actually reproduce the check, not merely read its stated outcome. Corrected by adding "the specific tool(s) and version(s) used to perform it" to the 5.9.3 bullet.

**Boundaries — is it explicit that Tier B is unavailable when equivalence cannot be demonstrated?**

No finding. 5.9.2's Tier B conditions are stated as a conjunctive "only when all of the following hold," and 5.9.4's stop conditions independently list "A Tier B equivalence comparison finds an unexplained difference" as a stop trigger. Since Tier A is definitionally unavailable whenever Tier B is being attempted (Tier A requires a digest for the exact artifact, which is why Tier B is being considered at all), failing any Tier B condition leaves only Tier C (stop) available by elimination. This chain was already explicit before this review and required no correction.

**Compatibility — could any language unintentionally weaken the existing WordPress Core Tier 1 requirements?**

No finding. Confirmed independently via `git diff` against the Version 1.0 baseline (reproducing `SF-REVIEW-219`'s method rather than trusting its stated result): Sections 5.1–5.8 contain zero changed lines across both the original 1.1 revision and this review's own two corrections — every edit in this review's diff falls within 5.9.2 and 5.9.3 only. Three separate passages state the boundary explicitly and were not altered: Section 1.1's added paragraph, Section 5.9's own opening line, and Section 5.9.1's applicability statement all say Sections 5.1–5.8 are unamended and remain exclusive for WordPress Core. No interpretive path was found by which Tier B could be read as applicable to a WordPress Core acquisition.

---

# 6. Outcome

**Approved with Minor Revisions.**

* Required revision: Generality finding — generalize 5.9.2's Tier B comparison beyond archives. **Resolved within this review.**
* Required revision: Determinism finding — bound "explained difference" to a closed category. **Resolved within this review.**
* Required revision: Evidence-burden finding — require tool/version identity for the Tier B comparison in 5.9.3. **Resolved within this review.**

---

# 7. Gate Decision

The three Minor findings were corrected in place, do not touch Sections 5.1–5.8, and do not alter `SF-REVIEW-218`/`219`'s conclusions about scope discipline or Core compatibility — they refine Tier B's own precision. `SF-SPEC-015` Version 1.1's Production Ready status, authorized by `SF-REVIEW-219`, is reconfirmed rather than revoked: Production Ready describes the specification text as it now stands after this review's corrections, consistent with how `SF-REVIEW-218` itself corrected Finding F-1 within a review that still concluded Approved.

---

# 8. Remaining Risks

- Same limitation as `SF-REVIEW-201` and `SF-REVIEW-219`: this review and the two before it were performed within the same session, not by a separately constituted reviewing party.
- The "raw bytes" comparison path for non-archive artifacts (added by this review's Generality correction) has no motivating case yet — Redis was archive-shaped. This extends the rule ahead of a demonstrated instance, which is normally something this project prefers to avoid absent evidence; it is accepted here because the alternative (leaving Tier B archive-only) is a known, not hypothetical, gap the moment any non-archive supporting component needs it, and the correction narrows rather than widens what Tier B permits.
- Tier B overall still has one full end-to-end motivating case (Redis); this review changed its wording but did not add a second case.

---

# 9. Revision History

| Version | Date | Summary | Status |
|---|---|---|---|
| 1.0 | 2026-07-20 | Policy-focused supplementary review against five owner-specified questions (generality, determinism, evidence burden, boundaries, Core compatibility); three Minor findings identified and corrected in place; Production Ready reconfirmed. | Approved with Minor Revisions — Class B |
