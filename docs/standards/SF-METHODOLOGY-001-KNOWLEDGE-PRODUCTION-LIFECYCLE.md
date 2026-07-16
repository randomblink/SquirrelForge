# SF-METHODOLOGY-001 — Knowledge Production Lifecycle

## Document Information

**Document ID:** SF-METHODOLOGY-001

**Title:** Knowledge Production Lifecycle

**Classification:** Engineering Methodology — a narrative synthesis of the process that actually produced this catalog, distinct from an Engineering Specification (`SF-SPEC-XXX`). It introduces no new normative "shall" requirement of its own. Wherever it describes a required step, that requirement is owned and defined by **SF-SPEC-013** (category lifecycle), **SF-SPEC-001** (entry content), **SF-SPEC-005** (review process), **SF-SPEC-012** (review independence), or **SF-SPEC-006** (repository validation), cited accordingly rather than restated as if this document were the authority. Wherever it describes a practice that has proven valuable in this project's own history but is not currently a formal requirement in any of those specifications, it says so explicitly (Section 5) rather than implying normative force it does not have. It is reviewed per this project's own established practice (author plus independent review) but does not claim the `SF-SPEC-008` version-lifecycle vocabulary (`Production Ready`, `Version Frozen`) in the sense that specification defines for a governed engineering artifact — the same disclaimer `SF-TAXONOMY-001`–`012` each make about their own, different, informal status.

**Status:** Draft — author-reviewed, see `SF-REVIEW-147`

**Version:** 1.0

**Owner:** SquirrelForge

---

# 1. Purpose

This project has, as of 2026-07-15, completed the full category lifecycle fourteen times (Database, Filesystem, REST API, PHP Runtime, Authentication, Networking, Plugin, Performance, Media, Theme, CLI, Cron, Multisite, Email), certified all fourteen, and additionally completed two "negative research" episodes (Site Health, Filesystem post-certification) that concluded no further work was warranted. `SF-SPEC-013` already defines the normative requirements this entire process satisfies, each clause grounded in a specific evidentiary citation from the Database and Filesystem categories' own completed lifecycles (`SF-SPEC-013` Section 5, throughout). What does not yet exist anywhere is a single, chronological, practitioner-facing account of the process as it was actually lived — including the practices that emerged *after* `SF-SPEC-013` was written, proved themselves across repeated application, and are not yet formally required anywhere.

This document exists to close that gap: to explain, in one place, how a category moves from an idea to a certified baseline, why each stage exists (grounded in the specific defect or discovery that motivated it, not asserted in the abstract), and what standard new work is expected to meet — so that a future practitioner, or this project's own continuation a year from now, can reconstruct the reasoning without re-deriving it from a hundred-plus review records and a long conversation history.

---

# 2. Relationship to the Specification Layer

This document does not replace, narrow, or extend any specification's own normative authority. Where a step below is required, the citation names the specification that requires it. Two things follow from this:

* If this document and a cited specification ever appear to disagree, the specification governs. This document should be corrected, not the other way around.
* Section 5 (Emergent Practices) exists specifically because some of what this project now treats as standard practice — most consequentially, the proactive cross-category ownership sweep — is not yet written into `SF-SPEC-013` as a requirement, despite having been applied without exception across every taxonomy since its own origin episode (Section 5.1). This document discloses that gap rather than silently treating an emergent practice as if it already carried specification-level force. Whether to formalize it into `SF-SPEC-013` itself is a decision this document does not make; it is named here as a candidate, consistent with `SF-SPEC-013` Section 16's own change-control process.

---

# 3. The Ten-Stage Lifecycle

Each stage names the artifact(s) it produces, the specification that requires it (where one does), and a citation to a real instance of it in this catalog's own history.

## 3.1 Candidate Identification

A category begins as an entry in `docs/engineering/KNOWLEDGE-PRODUCTION-PLAN.md`'s own roadmap (an informal, freely-editable planning document, explicitly not governed by `SF-SPEC-005` review), reaching one of two states:

* **Roadmap-driven:** the category was already an approved `SF-SPEC-001` Section 7 category value awaiting its turn (every category through Cron).
* **Evidence-driven:** a prior taxonomy's own research explicitly reserved the territory as a forward reference (`SF-TAXONOMY-009` Section 2 reserving Multisite's own site-resolution territory for what became `SF-TAXONOMY-011`; `SF-TAXONOMY-003` Section 5 deferring the password-reset delivery portion pending "Email category boundaries," resolved by `SF-TAXONOMY-012`).

Not every roadmap candidate becomes a category. Site Health and the Filesystem post-certification question were both researched to a documented "no" (`KNOWLEDGE-PRODUCTION-PLAN.md` Section 3, entries dated 2026-07-15) rather than left indefinitely open or silently dropped — a legitimate, evidenced outcome in its own right (Section 5.7).

## 3.2 Proactive Ownership Sweep

Before a taxonomy's boundary is drafted, a full-text search across every existing entry is performed for terminology the new category's own boundary touches even implicitly — not only the categories a first draft happens to name. This is the single practice most responsible for this project's own recent track record (Section 5.1) and is performed *during* drafting, not deferred to a later discovery.

**This is not currently a formal `SF-SPEC-013` requirement.** `SF-SPEC-013` Section 5.1 requires a taxonomy to declare "what it explicitly excludes from every other category value it could plausibly be confused with" — close in spirit, but written before the sweep discipline existed and silent on the specific, proactive, full-text-search methodology this project has applied without exception since. See Section 5.1 below for the origin episode and Section 2's own disclosure of this gap.

## 3.3 Taxonomy Creation (`SF-TAXONOMY-XXX`)

Required by `SF-SPEC-013` Section 5.1: a category shall not begin producing entries until a dedicated taxonomy document exists declaring its boundary, enumerating every planned entry with a one-line ownership statement, and documenting any candidate considered and rejected with specific reasoning. In this project's own practice, the taxonomy also records the ownership sweep's own findings directly in its Category Boundary section (for example, `SF-TAXONOMY-011` Section 2's own nine-entry citation list for Multisite), and its own Revision History carries forward as each planned entry reaches Production Ready — the taxonomy remains the authoritative status record throughout the category's life, per `SF-SPEC-013` Section 5.7.

A taxonomy may conclude a category's genuinely available territory is narrow (`SF-TAXONOMY-009`, two entries for CLI) or a single entry (`SF-TAXONOMY-011`, Multisite) rather than being expected to reach some target count — a taxonomy that comes back narrow because the sweep did its job is not a weaker outcome than one with several entries (`SF-REVIEW-121` Section 9, made explicit for CLI).

## 3.4 Taxonomy Independent Review

A Class B independent review (`SF-SPEC-012` Section 6.2) re-verifies every claim the taxonomy makes against the cited entries' own actual current text, and independently re-runs the ownership sweep with freshly-constructed search terms rather than accepting the draft's own account (established practice since `SF-REVIEW-096`, formalized in method across every subsequent taxonomy review — `SF-REVIEW-105`, `114`, `121`, `128`, `135`, `140`). This review is not itself required by name in `SF-SPEC-013`, which is silent on taxonomy review specifically (`SF-SPEC-013` Section 2.2's own exclusion), but has been performed for every taxonomy in this project without exception, per this project's own established practice.

## 3.5 Entry Authoring

Drafted directly from the frozen taxonomy's own declared scope, following the seventeen-section structure `SF-TEMPLATE-004` defines and the completeness requirements `SF-SPEC-001` establishes for an individual `WP-ERROR` entry (Section 2.2's own exclusion from this document's scope). Where a category plans more than one entry, later entries are drafted against the taxonomy's *current*, not original, state — reflecting every correction an earlier entry's own review already applied.

## 3.6 Class A (Author) Review

Required by `SF-SPEC-012` Section 6.1: performed by the same authoring process, within the same work-order execution. May identify and correct defects but does not, by itself, authorize Production Ready. A clean pass with zero findings is a valid, complete outcome (`SF-REVIEW-142` Section 8, made explicit for `WP-ERROR-046`) — not evidence the review was insufficiently rigorous.

## 3.7 Class B (Independent) Review

Required by `SF-SPEC-012` Section 6.2, with independence requirements from `SF-SPEC-012` Section 8: preliminary findings are recorded *before* comparing against the Class A review's own findings, so independence is genuine rather than a formality (established across every entry review pair in this catalog, for example `SF-REVIEW-130` Section 4/5's own explicit before/after structure). This is the review layer that has most consistently caught cross-document completeness gaps this project's `docs/engineering/FRAMEWORK-OBSERVATIONS.md` now tracks as the "hub entry" pattern (Section 5.2 below) — not because Class A review is deficient, but because a completeness check against a sibling entry's own current text requires exactly the fresh-eyes independence `SF-SPEC-012` Section 8 exists to guarantee.

## 3.8 Category Consistency Review

Required by `SF-SPEC-013` Section 5.3: performed only once every entry the taxonomy declares has reached Production Ready, treating the complete set as one system — mutual exclusivity, terminology consistency, cross-reference symmetry, and sequential-authoring staleness (a later entry's own creation making an earlier sibling's forward citation stale, caught mechanically by `scripts/validate-repo.sh` Check A). For a single-entry category, this review's own scope adapts to emphasize external consistency — cross-taxonomy forward-reference closure, Distinction-citation accuracy — since there is no sibling within the category for internal symmetry checks (`SF-REVIEW-138`, the first instance of this adapted scope, for Multisite).

## 3.9 Baseline Certification

Required by `SF-SPEC-013` Section 5.4: independently re-verifies, against current repository state rather than any prior review's own report, that every planned entry exists and is Production Ready, that boundaries remain mutually exclusive, that cross-references resolve, that the taxonomy's own status record is accurate, that no open `FRAMEWORK-OBSERVATIONS.md` entry blocks the category, that repository validation (`SF-SPEC-006`) has been applied, and that the working tree is clean before and after. An Approved outcome with zero findings is the expected result once the immediately preceding consistency review has already found and corrected anything that would otherwise surface here (`SF-REVIEW-053`/`079`/`088`/`095`/`104`/`113`/`120`/`127`/`134`/`139`/`146` each independently establish this pattern) — this is evidence the review layers are functioning as intended (Section 4.3's own layered-review principle, `SF-SPEC-013` Section 4.3), not that the certification pass is redundant. Certification produces the category's own **Baseline Certified** designation (`SF-SPEC-013` Section 5.5, Section 7), a category-level status distinct from any individual entry's own `Production Ready`.

A category may be certified with a single planned entry, where the taxonomy itself reached that conclusion through genuine research rather than by accident — `SF-REVIEW-139`, the first such certification, explicitly distinguishes this from Bootstrap's own long-standing, uncertified single-entry state (no taxonomy ever declared Bootstrap's own entry set complete; see `docs/engineering/KNOWLEDGE-PRODUCTION-PLAN.md` Section 1's own note).

## 3.10 Post-Certification Change Conditions

Governed by `SF-SPEC-013` Section 5.6: once a category is Baseline Certified, any change to its entries or taxonomy proceeds only through a documented taxonomy revision, the standard authoring and review sequence for the new or revised entry, a new consistency review, and a new baseline certification — never an ad hoc edit. `SF-SPEC-013` Section 15 itself disclosed, at its own time of writing, that this requirement was derived prospectively from principle, since no real post-certification change episode had yet occurred. The Filesystem post-certification research (2026-07-15, `docs/engineering/KNOWLEDGE-PRODUCTION-PLAN.md` Section 3) is this project's first real test of the *other* side of that requirement — establishing, through direct verification of WordPress core's own actual source rather than assumption, that a plausible-seeming candidate did **not** meet the evidentiary bar to reopen a certified category at all. No formal `SF-REVIEW` record exists for this episode, since it concluded before any taxonomy revision was drafted — the research and its conclusion are recorded directly in the production plan, the same informal artifact candidate identification (Section 3.1) uses.

---

# 4. Governing Specifications Referenced

This document depends on, and does not restate the content of:

* **SF-SPEC-001 — Error Knowledge Specification**, for individual `WP-ERROR` entry content requirements and the approved category-value list.
* **SF-SPEC-004 — Documentation Specification**, for cross-reference and formatting conventions this document itself follows.
* **SF-SPEC-005 — Engineering Review Specification**, for review process, findings structure, and outcomes.
* **SF-SPEC-006 — Repository Validation Specification**, for the validation criteria a baseline certification applies.
* **SF-SPEC-008 — Versioning Specification**, for the version-lifecycle vocabulary this document explicitly declines to claim for itself (Document Information, above).
* **SF-SPEC-012 — Engineering Review Independence Specification**, for reviewer classes and independence requirements.
* **SF-SPEC-013 — Knowledge Category Lifecycle Specification**, the primary normative source this entire document narrates and cross-references throughout Section 3.

---

# 5. Emergent Practices Not Yet Formalized

Each of the following has been applied consistently across this project's own recent history but is not currently a normative requirement in `SF-SPEC-013` or any other specification. Each is disclosed here, with its own origin episode and evidentiary basis, as a candidate for future formalization — a decision this document does not itself make.

## 5.1 Proactive Cross-Category Ownership Sweep

**Origin:** During Plugin Lifecycle's second entry (`WP-ERROR-032`), research surfaced two genuine, previously-undetected ownership conflicts with Filesystem and Networking that `SF-TAXONOMY-005`'s own independent review (`SF-REVIEW-089`) had missed, because that review verified only claims the taxonomy's own text named directly rather than searching proactively for terminology its boundary touched implicitly. `SF-TAXONOMY-005` required a mid-production correction (Version 1.1 → 1.2, before `WP-ERROR-032` was authored) as a direct result.

**Validation:** the fix — performing the same proactive, full-text sweep *during* taxonomy drafting rather than discovering conflicts reactively — was applied to every subsequent taxonomy (Performance onward) and produced seven consecutive categories (Performance, Media, Theme, CLI, Cron, Multisite, Email) whose taxonomies required zero boundary revision after their own initial drafting. This is evidence-backed for this process, this repository, seven categories, one author/reviewer — not evidence the methodology is proven in general, a distinction this project has been careful to preserve at every re-application (`SF-REVIEW-103` Section 8, `SF-REVIEW-113` Section 8, and every subsequent consistency review's own "Second Confirmation" section through `SF-REVIEW-145`).

## 5.2 Hub-Entry Cross-Reference Maintenance

**Origin:** `WP-ERROR-013` (Bootstrap PHP Fatal Error) and, later, `WP-ERROR-014`/`015` (PHP Runtime) and `WP-ERROR-028` (Outbound HTTP Request Failure) were observed to require a cross-reference addition nearly every time a new, more specific entry documented a condition adjacent to what they already own generically — a Common Causes bullet, a Distinction-section boundary statement, or a Typical Symptoms citation extension.

**Characterization:** `docs/engineering/FRAMEWORK-OBSERVATIONS.md`'s own entry on this pattern (last updated during the Cron cycle) tracks eight confirmed instances across three hub entries as of 2026-07-15. Critically, every one of the eight was caught by the *new* entry's own independent review, before that entry reached Production Ready — a perfect catch rate, evidence the review process is functioning as intended rather than exhibiting a detection gap (Section 5.4 below states the general principle this specific finding established).

## 5.3 Related Errors Wording-Drift Detection

**Origin:** Four separate entries (`WP-ERROR-017`, `031`, `035`, `038`) each carried a non-standard Section 16 intro sentence, in every case caught only by a category-level consistency review comparing entries side by side — never by that entry's own author or independent review.

**Resolution:** unlike the hub-entry pattern (Section 5.2), this *is* a detection gap — the same defect escaping the review layer meant to catch it, four separate times. It was promoted from disclosed observation to a mechanical check (`scripts/validate-repo.sh` Check D) once the fourth instance was confirmed, and has since caught zero further drift on every subsequent entry authored against it.

## 5.4 The Detection-Location Rule of Thumb

**Origin:** the explicit contrast between Section 5.2 (a recurring pattern, always caught by the correct review layer) and Section 5.3 (a recurring pattern, repeatedly *not* caught by the correct review layer) before it was formalized as a mechanical check.

**The rule:** a recurring pattern's own instance count alone does not determine whether the process needs to change — *where* it was caught does. A pattern consistently caught by the review layer meant to catch it is evidence that layer is working, and should be disclosed and monitored, not acted on. A pattern that escapes the review layer meant to catch it, repeatedly, is a genuine gap, and should be corrected — automated where the check is mechanical (string comparison), or made an explicit checklist step where it requires semantic judgment a script cannot perform.

## 5.5 Deliberate, Tracked Deferral

**Origin:** during the Email category's own first entry (`WP-ERROR-046`), a stale cross-reference in `WP-ERROR-024` needed correcting, but doing so completely required a sibling entry (`WP-ERROR-047`) that did not yet exist. Rather than an incomplete partial fix or a silently-dropped TODO, the independent review (`SF-REVIEW-142`) explicitly named the exact condition required for the correction and logged it in that review's own Remaining Risks. The next entry's own independent review (`SF-REVIEW-144`) closed it exactly as planned once the condition was met.

**Distinguishing feature:** this differs from the hub-entry pattern (Section 5.2) in kind — it is not a gap discovered after the fact, but a fix proactively scheduled for the moment it can be done completely, with the trigger condition written down so it is not lost.

## 5.6 Severity Reasoned From First Principles, Not Inherited

Every entry's severity classification is reasoned from that entry's own described causes and manifestations, not copied from a sibling sharing the same category. This has produced deliberate departures in both directions within a single category: `WP-ERROR-041` (Low, the first entry in this catalog to depart below the catalog's usual range-based Critical pattern, since its own condition has no plausible path to any site-facing impact at all) alongside `WP-ERROR-042` (Critical, in the same CLI category, reasoned explicitly in contrast rather than inherited); `WP-ERROR-046` and `WP-ERROR-047` sharing Critical but with an explicitly opposite visibility argument for each.

## 5.7 Negative Research as a Legitimate Outcome

**Origin:** Site Health and the Filesystem post-certification question were both investigated to a documented conclusion that no new taxonomy or entry was warranted, rather than left as an open roadmap item or silently abandoned.

**Characterization:** a mature taxonomy does not expand indefinitely; it accumulates documented reasons *not* to expand, with the same evidentiary rigor applied to a "no" as to a "yes." The Filesystem episode specifically demonstrates verifying an uncertain technical claim against WordPress core's own actual source before committing to either conclusion, rather than presenting an unresolved uncertainty as a decision for someone else to guess at.

---

# 6. Boundary

This document:

* Does not itself authorize any category-lifecycle transition — every transition this document narrates is authorized by the specification that governs it (`SF-SPEC-013` throughout Section 3).
* Does not redefine or narrow any requirement `SF-SPEC-001`, `005`, `006`, `012`, or `013` already owns.
* Is descriptive of this project's own actual history, not prescriptive of a process no category has yet followed — every claim in Section 3 and Section 5 cites a specific taxonomy, entry, or review record it is grounded in, the same evidentiary-citation discipline `SF-SPEC-013` Section 5 established for its own normative requirements.
* Makes no claim that the practices in Section 5 generalize beyond this process, this repository, and the categories cited as evidence for each — consistent with this project's own established scope discipline throughout every category consistency review's own "Second Confirmation" section.

---

# 7. Remaining Limitations

* This document was authored by the same process it describes, and its own account of that process is therefore self-referential in the same way `SF-SPEC-013`'s own Section 10 (Reference Implementations) disclosed for its own normative requirements.
* Section 3.10 (Post-Certification Change Conditions) is now informed by exactly one real episode (Filesystem, 2026-07-15), and that episode concluded with no change — the *positive* case (a post-certification change that actually proceeds through all four of `SF-SPEC-013` Section 5.6's own steps) remains, as `SF-SPEC-013` Section 15 itself already disclosed, untested in practice.
* Section 5's own seven emergent practices are each evidenced by this project's own history alone, authored and reviewed by a single class of agent throughout (Claude Code) — the same scope caveat every category consistency review in this catalog has applied to its own "Second Confirmation" findings.
* Whether any of Section 5's practices should be formally incorporated into `SF-SPEC-013` itself — most consequentially, the proactive ownership sweep (Section 5.1) — is a decision this document surfaces but does not make.

---

# 8. Revision History

| Version | Date | Summary of Changes | Approval Status |
|---|---|---|---|
| 1.0 | 2026-07-15 | Initial document, authored after fourteen categories independently completed the full lifecycle this document narrates, plus two negative-research episodes (Site Health, Filesystem post-certification). Every Section 3 stage and Section 5 emergent practice cites the specific taxonomy, entry, or review record it is grounded in, following `SF-SPEC-013` Section 5's own evidentiary-citation discipline. Explicitly discloses that Section 5.1 (the proactive ownership sweep) is not yet a formal `SF-SPEC-013` requirement despite universal application since its own origin episode, rather than silently treating it as already normative. | Draft — author-reviewed, see `SF-REVIEW-147` |
