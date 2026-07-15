# SF-REVIEW-135 — SF-TAXONOMY-011 Independent Review

# 1. Review Information

**Review ID:** SF-REVIEW-135

**Review Date:** 2026-07-15

**Reviewer:** Class B — Independent Review, per **SF-SPEC-012** Section 6.2, conducted per this project's established practice of independently reviewing a category's taxonomy before entry authoring begins (`SF-REVIEW-034`, `045`, `069`, `080`, `089`, `096`, `105`, `114`, `121`, `128`), not as a normative requirement `SF-TAXONOMY-011` itself imposes.

**Status:** Complete

This is the seventh taxonomy drafted using the proactive cross-category ownership sweep discipline, and the first to plan exactly one entry — a stronger test of the sweep's own rigor than any prior taxonomy, since a single-entry outcome could plausibly result from insufficient research rather than genuine territorial exhaustion. This review gives particular scrutiny to whether each of the nine claimed pre-existing ownership claims (`WP-ERROR-004`/`005`/`006`/`017`/`024`/`026`/`036`/`037`/`042`) is independently verifiable, and whether the four rejected candidates in Section 5 were genuinely examined rather than dismissed to preserve a narrative of a clean, narrow category.

---

# 2. Artifact Reviewed

`SF-TAXONOMY-011` — Multisite Error Taxonomy, Version 1.0, at `docs/standards/SF-TAXONOMY-011-MULTISITE-ERROR-TAXONOMY.md`.

---

# 3. Governing Specifications

- **SF-SPEC-013 — Knowledge Category Lifecycle Specification** (Section 5.1, Category Entry Criteria)
- **SF-SPEC-001 — Error Knowledge Specification** (Section 7, confirming `Multisite` is an approved category value)
- **SF-SPEC-004 — Documentation Specification** (internal consistency, cross-references)

---

# 4. Review Scope

This review independently determines whether `SF-TAXONOMY-011` satisfies **SF-SPEC-013** Section 5.1, with particular attention to: (1) whether each of the nine cited pre-existing ownership claims is accurate against the named entry's own current text, re-verified rather than accepted from the draft's own account; (2) whether the single planned entry's own consolidation of "no site resolved" and "wrong site resolved" is genuinely justified by the same reasoning `SF-TAXONOMY-010` established, not merely asserted by citation; (3) whether the four rejected candidates in Section 5 reflect genuine analysis; and (4) whether the disclosed `WP-ERROR-031` gap is accurately characterized as observed-but-not-owned rather than silently absorbed or silently ignored.

---

# 5. Evidence Examined

- Full contents of `SF-TAXONOMY-011`, read in full.
- `SF-SPEC-001` Section 7, independently re-read to confirm `Multisite` is an approved category value — confirmed present.
- `SF-TAXONOMY-009` Section 2, independently re-read to verify the exact wording of the forward-reference quotation this taxonomy cites — confirmed the quotation matches `SF-TAXONOMY-009`'s own actual text verbatim.
- `WP-ERROR-004`, independently re-read: confirmed its own text names WordPress Multisite's "Add New Site" action and its `CREATE TABLE` failure under insufficient privilege directly, by name.
- `WP-ERROR-005`, independently re-read: confirmed its own Common Causes list names "Multisite schema differences — a new site added to a network whose own per-site tables were never fully created" verbatim as cited.
- `WP-ERROR-006`, independently re-read: confirmed its own text distinguishes per-site "blog tables" from network-wide global tables as two independently-subject variants of its own condition, as cited.
- `WP-ERROR-017`, independently re-read: confirmed its own text states network-activated plugins use "the ordinary plugin activation mechanism" and are explicitly excluded from its own must-use-plugin scope, as cited — this is the single most load-bearing citation in this taxonomy's own Section 2, since it is what allows this taxonomy to reject a network-activation entry outright rather than merely defer it; independently confirmed sound.
- `WP-ERROR-024` and `WP-ERROR-026`, both independently re-read in full: confirmed each already extensively and specifically addresses multisite-specific conditions (spam-flagged accounts, wrong-site login attempts, network-level capability model, per-site membership) across multiple sections, not merely a passing mention, substantiating this taxonomy's own "already fully owned" characterization rather than a thinner "briefly mentioned" one.
- `WP-ERROR-036` and `WP-ERROR-037`, independently re-read: confirmed each names its own multisite-specific quota/allowed-types behavior directly as a cause, not merely as an aside.
- `WP-ERROR-042`, independently re-read: confirmed its own Section 2 exclusion text is quoted accurately and completely, and that it genuinely reserves rather than merely gestures toward this category's own territory.
- **Consolidation reasoning independently re-derived**: confirmed "no site resolved" and "wrong site resolved" do share a single underlying mechanism (Host-header-driven lookup, `sunrise.php`-interceptable) and that a reader investigating either symptom would need to examine the same causes — genuinely parallel to `WP-ERROR-043`'s own four-cause consolidation, not a superficial citation of that precedent.
- **Rejected candidates independently re-examined**, not merely accepted from the draft's own reasoning: the site-creation candidate's own remaining-territory analysis (after excluding `WP-ERROR-004`/`005`) was independently re-derived and confirmed to leave no genuine, currently-unclaimed remainder; the `switch_to_blog()` stack-imbalance and cross-site-data-leakage candidates were independently assessed as caller-code defects rather than WordPress-mechanism defects, consistent with this catalog's own established plugin-defect exclusion pattern; the network-activation rejection was independently confirmed to rest soundly on `WP-ERROR-017`'s own text rather than an assumption.
- `WP-ERROR-031`, independently re-read: confirmed it does **not** currently name a `Network: true` header or any network-activation-specific requirement-gate cause — the disclosed gap is real, and independently confirmed to be correctly characterized as Plugin category's own future maintenance rather than silently claimed or silently dropped by this taxonomy.
- An independently-constructed full-text sweep — using search terms distinct from the draft's own account (`ms_site_check`, `get_site_by_path`, `no such (site|blog)`, `not registered`, `blog.not.found`) — across every file in `docs/knowledge/wp-errors/` and `docs/standards/SF-TAXONOMY-*.md`. Zero conflicting matches found beyond an unrelated `WP-ERROR-024` string ("username... not registered on this site," a distinct, non-multisite condition).
- `find . -iname "*WP-ERROR-045*"`, confirming the planned ID does not currently exist.
- `grep -n '\bmust\b'` (excluding `must-use`) against the full document: zero matches.

---

# 6. Findings

| Finding ID | Severity | Requirement or Criterion | Observation | Evidence | Required Action | Resolution Status |
|---|---|---|---|---|---|---|
| — | Conforming | SF-SPEC-013 §5.1, bullet 1 (boundary) | Section 2 declares a clear positive boundary with an unusually extensive, independently-verified set of exclusions. | Section 2. | None. |
| — | Conforming | `SF-TAXONOMY-009` forward-reference accuracy | Quotation independently re-verified verbatim against `SF-TAXONOMY-009`'s own current text. | Section 5 above. | None. |
| — | Conforming | All nine pre-existing ownership claims | Each independently re-verified accurate against the named entry's own current text, several substantiated by extensive, multi-section coverage rather than a single passing mention. | Section 5 above. | None. |
| — | Conforming | Single-entry consolidation justification | Independently re-derived and confirmed genuinely parallel to `WP-ERROR-043`'s own established precedent, not a superficial citation. | Section 5 above. | None. |
| — | Conforming | Four rejected candidates (Section 5) | Each independently re-examined and confirmed to reflect genuine analysis, not dismissal to preserve a narrow-category narrative. | Section 5 above. | None. |
| — | Conforming | Disclosed `WP-ERROR-031` gap | Independently confirmed real and correctly characterized as observed-but-not-owned. | `WP-ERROR-031`, Section 5 above. | None. |
| — | Conforming | `SF-SPEC-001` Section 7 conformance | `Multisite` independently confirmed present in the approved category-value list. | Section 5 above. | None. |
| — | Conforming | ID availability | `WP-ERROR-045` independently confirmed to not currently exist. | `find` sweep, Section 5 above. | None. |
| — | Conforming | Independent cross-category sweep (fresh terms) | Zero conflicting claims found beyond one unrelated string match, correctly distinguished. | Independent sweep, Section 5 above. | None. |
| — | Conforming | Structural sweep | Zero bare `must` outside `must-use`; zero drafting-language matches. | Section 5 above. | None. |

No Major or Critical findings.

---

# 7. Outcome

**Approved.**

**Basis:** `SF-TAXONOMY-011` satisfies every element of **SF-SPEC-013** Section 5.1. Its unusual single-entry outcome was independently stress-tested rather than accepted at face value: all nine pre-existing ownership claims were independently re-verified as accurate and substantial (not thin, passing mentions), and all four rejected candidates were independently re-examined and confirmed to reflect genuine territorial analysis rather than a convenient narrative. The single planned entry's own two-manifestation consolidation is genuinely, not superficially, parallel to this catalog's own established precedent.

---

# 8. Gate Decision

Per **SF-SPEC-013** Section 5.1, entry authoring for the Multisite category (`WP-ERROR-045`) may now begin — this taxonomy exists, declares the category's boundary with independently-verified accuracy against every entry it claims already occupies adjacent territory, resolves `SF-TAXONOMY-009`'s own explicit forward-reference, enumerates its one planned entry, documents rejected candidates with genuine reasoning, and has been independently reviewed per this project's established practice, including the proactive cross-category ownership sweep this project's own recent history established as standard.

---

# 9. Remaining Risks

- This review was conducted by the same class of agent (Claude Code) as the authoring pass.
- This is the first single-entry taxonomy in this catalog; if drafting `WP-ERROR-045` reveals a second, genuinely distinct mechanism this taxonomy's own research missed, that would be a real finding for the entry's own review to surface, not evidence this taxonomy was under-researched — the same caveat `SF-REVIEW-121` recorded for CLI's own narrow, two-entry outcome.
- The disclosed `WP-ERROR-031` gap (no `Network: true` requirement-gate cause) remains genuinely unaddressed by any entry in this catalog, unchanged by this review; it belongs to Plugin category's own future maintenance, not this taxonomy's own scope.
- This is the sixth consecutive taxonomy (after Plugin's own mid-production correction, then Performance, Media, Theme, CLI, Cron cleanly) to pass its own ownership sweep during drafting without requiring a boundary correction; per this project's own scope discipline, this strengthens but does not yet generalize the claim beyond this process, this repository, and six categories under a single author/reviewer.

---

# 10. Revision History

| Version | Date | Summary of Changes | Approval Status |
|---|---|---|---|
| 1.0 | 2026-07-15 | Initial independent review of SF-TAXONOMY-011. Independently re-verified all nine cited pre-existing ownership claims (WP-ERROR-004/005/006/017/024/026/036/037/042) against each entry's own current text, confirming substantial rather than thin coverage. Independently re-derived the single planned entry's own two-manifestation consolidation as genuinely parallel to WP-ERROR-043's own precedent. Independently re-examined all four rejected candidates and confirmed genuine analysis. Confirmed the disclosed WP-ERROR-031 gap is real and correctly scoped as not owned by this taxonomy. An independently-constructed full-text sweep found zero conflicting claims. No findings. Approved. Entry authoring for WP-ERROR-045 may begin. | Approved |
