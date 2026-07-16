# SF-BASELINE-001 — Framework Baseline v2 Declaration Record

## Document Information

**Document ID:** SF-BASELINE-001

**Title:** Framework Baseline v2 Declaration Record

**Classification:** Governance Declaration

**Status:** Declared (an informal descriptor for this record's own state, not a **SF-SPEC-008** Section 6 Version Status value — this document does not present itself as a "versioned engineering artifact" within that specification's scope, the same disclaimer `SF-TAXONOMY-001`/`002` make for the same reason)

**Version:** 1.0

**Owner:** SquirrelForge

---

# 1. Baseline Identifier

**Framework Baseline v2.**

Per **SF-SPEC-014** Section 5.7, this is the first Framework Baseline declared under that specification, numbered v2 by continuity with usage already present in this session's own records (`SF-REVIEW-060`, `061`, `064`) rather than because a formal "Framework Baseline v1" was ever declared under any specification — see **SF-SPEC-014** Section 5.1.

---

# 2. Declaration Date

2026-07-14.

---

# 3. Approving Authority

The SquirrelForge project owner, by explicit direction given in this engineering session. The underlying engineering and review work was performed by the Claude Code process conducting this session; per **SF-SPEC-012**, no Class C (human engineering) review has been performed on any artifact this declaration covers. The decision to declare — distinct from the technical readiness assessment `SF-REVIEW-064` performed — is the project owner's own and is recorded here as such, not asserted by the authoring process on its own authority.

---

# 4. Evidence Reviewed

`SF-REVIEW-064` — Framework Baseline v2 Readiness Review (Reassessment Under `SF-SPEC-014`). Outcome: **Approved**, zero findings.

`SF-REVIEW-064` evaluated repository state as of commit `67fad6f`. One further change was made between that commit and this declaration: `SF-SPEC-014` itself was revised (Version 1.0 → 1.1, `SF-REVIEW-065`/`066`, commit `aa97731`) to define this Declaration Record as its own artifact type, per project-owner direction given while `SF-REVIEW-064`'s outcome awaited a declaration decision. That change is procedural and self-referential to `SF-SPEC-014` alone (`git diff --stat 67fad6f..aa97731` touches only that file and its two new review records); it does not alter the Status, Version, or Revision History of any other specification, any `WP-ERROR` entry, any taxonomy, or any category's certification state that `SF-REVIEW-064` verified. `SF-REVIEW-064`'s substantive findings accordingly remain valid evidence for this declaration.

---

# 5. Scope of the Freeze

This declaration covers the SquirrelForge Engineering Framework as committed at `aa97731` (2026-07-14):

* 14 `SF-SPEC-XXX` specifications — `SF-SPEC-004`, `005`, `013`, `014` Production Ready; `SF-SPEC-001`, `002`, `003`, `006`–`012` Draft (library-complete, not individually Production-Ready-reviewed — `SF-REVIEW-064` Section 7).
* `SF-GLOSSARY-001` and 5 `SF-TEMPLATE-XXX` documents (Draft).
* 2 `SF-TAXONOMY-XXX` documents (Filesystem, REST API).
* 19 `WP-ERROR-XXX` knowledge entries across 6 categories; 4 categories (Database, Filesystem, REST API, PHP Runtime) `Baseline Certified` under **SF-SPEC-013**; 2 (Bootstrap, Plugin) single-entry, not certified, disclosed as accepted limitations.
* `scripts/validate-repo.sh`, all three checks clean as of `SF-REVIEW-064`.
* `docs/engineering/FRAMEWORK-OBSERVATIONS.md`, all four entries classified closed or accepted limitation under **SF-SPEC-014** Section 5.5.

---

# 6. Future Change Governance

Per **SF-SPEC-014** Section 5.7, a subsequent Framework Baseline (v3 and beyond) requires a new Framework Baseline Readiness Review satisfying Section 5.4 in full, followed by a new Declaration Record explicitly identifying this baseline as the one it supersedes. This record is not edited or removed by that future declaration, per Section 4.4 (Preservation).

Framework changes made after this declaration are not thereby prohibited, but are no longer the default mode of work this repository's engineering activity is expected to consist of: ordinary engineering work from this point forward is authoring and reviewing `WP-ERROR` entries and knowledge-category baselines against this platform; a change to the specification library itself is exceptional governance work requiring explicit justification and its own review, not routine iteration.

---

# 7. Revision History

| Version | Date | Summary of Changes | Approval Status |
|---|---|---|---|
| 1.0 | 2026-07-14 | Initial declaration of Framework Baseline v2, citing `SF-REVIEW-064` as evidence, per **SF-SPEC-014** Section 5.6. | Declared |
