# SF-REVIEW-114 — SF-TAXONOMY-008 Independent Review

# 1. Review Information

**Review ID:** SF-REVIEW-114

**Review Date:** 2026-07-15

**Reviewer:** Class B — Independent Review, per **SF-SPEC-012** Section 6.2, conducted per this project's established practice of independently reviewing a category's taxonomy before entry authoring begins (`SF-REVIEW-034`, `045`, `069`, `080`, `089`, `096`, `105`), not as a normative requirement `SF-TAXONOMY-008` itself imposes.

**Status:** Complete

This is the fourth taxonomy drafted using the proactive cross-category ownership sweep discipline established after `WP-ERROR-032`'s own production cycle and validated by `SF-TAXONOMY-006` and `SF-TAXONOMY-007`'s own complete categories (`SF-REVIEW-104`/`113`). This review applies the same standard: every claimed overlap is independently re-verified against the cited entry's own actual text, and the cross-category sweep is independently re-run with freshly-constructed search terms rather than accepted from the draft's own account.

---

# 2. Artifact Reviewed

`SF-TAXONOMY-008` — Theme Lifecycle Error Taxonomy, Version 1.0, at `docs/standards/SF-TAXONOMY-008-THEME-LIFECYCLE-ERROR-TAXONOMY.md`.

---

# 3. Governing Specifications

- **SF-SPEC-013 — Knowledge Category Lifecycle Specification** (Section 5.1, Category Entry Criteria)
- **SF-SPEC-001 — Error Knowledge Specification** (Section 7, confirming `Theme` is an approved category value)
- **SF-SPEC-004 — Documentation Specification** (internal consistency, cross-references)

---

# 4. Review Scope

This review independently determines whether `SF-TAXONOMY-008` satisfies **SF-SPEC-013** Section 5.1, with particular attention to: (1) whether `SF-TAXONOMY-005`'s own forward-reference to Theme as an "exact structural parallel" to Plugin is accurately quoted and genuinely resolved; (2) whether the boundary claims against `WP-ERROR-013`, `019`, `020`, `028`, `029`, and `014`/`015` are drawn accurately against each entry's own current text; (3) whether the two-entry, lifecycle-stage model (narrower than Plugin's three-stage model) is soundly reasoned rather than an unexplained asymmetry; and (4) whether an independently-constructed full-text sweep, using different search terms than the draft's own, surfaces any conflicting claim the draft missed.

---

# 5. Evidence Examined

- Full contents of `SF-TAXONOMY-008`, read in full.
- `SF-SPEC-001` Section 7, independently re-read to confirm `Theme` is an approved category value — confirmed present in the list (`Bootstrap`, `Configuration`, `PHP Runtime`, `Database`, `Filesystem`, `Plugin`, `Theme`, `REST API`, `Authentication`, `Security`, `Performance`, `Deployment`, `CLI`, `Networking`, `Media`, `Cron`, `Email`, and others).
- `SF-TAXONOMY-005` Section 2, independently re-read in full to verify the exact wording of the forward-reference quotation this taxonomy cites — confirmed the quotation ("Theme category, an exact structural parallel WordPress itself maintains (a distinct `Theme_Upgrader` class and separate activation code path) but a conceptually independent category from Plugin, per `SF-SPEC-001` Section 7's own separate `Theme` category value") matches `SF-TAXONOMY-005`'s own actual text verbatim.
- `WP-ERROR-013`, independently re-read in full to verify both cited passages: Section 4 (Primary Failure Mode) independently confirmed to read "...WordPress core, a must-use plugin, a drop-in, an active plugin loaded during `plugins_loaded`, or the active theme's `functions.php` — rather than in code that runs only after bootstrap has completed," and Section 10 (Common Causes) independently confirmed to list "The active theme's `functions.php` raising a fatal error during theme setup" as its own fourth cause. Both citations are accurate and not overstated.
- `WP-ERROR-019` and `WP-ERROR-020`, independently re-read to verify the claimed shared `wp-content/upgrade` staging-directory language — confirmed both already say "plugin/theme installation and updates" and "plugin or theme installation, activation, and update routines" (or materially equivalent phrasing) in multiple sections of each entry's own current text, predating this taxonomy.
- `WP-ERROR-028`, independently re-read to confirm its own Section 5/9 language names "plugin/theme/core update checks" directly, by name, as its own territory.
- `WP-ERROR-029`, independently re-read to confirm it does **not** explicitly name "theme update" anywhere in its own text the way `WP-ERROR-028` does — its own Section 7 Scope instead states a general condition ("an outbound HTTPS request's underlying network connection succeeds, but the TLS handshake fails"), with no context-specific qualifier at all. This taxonomy's own citation of `WP-ERROR-029` therefore rests on the same general-scope extension `WP-ERROR-032` Section 6 already used for Plugin ("for the same reason as `WP-ERROR-028` above"), not on an explicit "theme" mention within `WP-ERROR-029` itself — an accurate basis, but one this review flags as a precision distinction the taxonomy's own text does not currently draw (see Section 6, Finding T-1).
- `WP-ERROR-014` and `WP-ERROR-015`, independently re-read in full: both entries already scope themselves generically to "an active plugin or theme" throughout Sections 3–14. Independently confirmed each entry's own Section 9 (Typical Symptoms) reads "A plugin or theme activation blocked by a requirements-not-met notice naming a specific extension — see `WP-ERROR-031` for the plugin-activation-specific diagnostic entry point" (`WP-ERROR-014`) and the `Requires PHP`-mismatch equivalent (`WP-ERROR-015`), each citing only `WP-ERROR-031` by name — confirming this taxonomy's own note that the future theme-activation entry's own review should extend these citations, per the general-completeness pattern already established for `WP-ERROR-017`/`013` elsewhere in this catalog.
- `WP-ERROR-032`, independently re-read to confirm its own Section 7 (Scope, Excluded) explicitly and by name already excludes "Theme update failures, Theme category's own parallel mechanism," predating and directly anticipating this taxonomy.
- An independently-constructed full-text sweep — using search terms distinct from the draft's own account (`stylesheet`, `parent theme`, `broken theme`, `WP_Theme`, `after_switch_theme`, `theme.json`, `block theme`, `full site editing`, `customizer`) — across every file in `docs/knowledge/wp-errors/` and `docs/standards/SF-TAXONOMY-*.md`. Zero matches found anywhere outside this taxonomy's own new text; no existing entry claims any part of this territory.
- `find . -iname "*WP-ERROR-039*" -o -iname "*WP-ERROR-040*"`, confirming neither planned ID currently exists.
- `grep -n '\bmust\b'` (excluding `must-use`) against the full document: zero matches.
- Independent technical verification of the WordPress mechanism names this taxonomy cites (`Theme_Upgrader`, `WP_Upgrader`, `WP_Theme::errors()`, `after_switch_theme`, the `Template` header's parent-theme resolution, and the `Requires PHP`/`Requires at least`/`Requires Plugins` theme headers introduced in WordPress 6.5) against current WordPress core behavior, rather than accepted uncritically.

---

# 6. Findings

| Finding ID | Severity | Requirement or Criterion | Observation | Evidence | Required Action | Resolution Status |
|---|---|---|---|---|---|---|
| T-1 | Minor | Precision (`WP-ERROR-029` citation basis) | Section 2's `WP-ERROR-029` exclusion bullet states the TLS-negotiation stage of a theme-update download is "already fully owned by... `WP-ERROR-029`," without noting that, unlike `WP-ERROR-028` (which explicitly names "theme" update checks), `WP-ERROR-029` establishes this only through its own general, context-agnostic scope — the identical basis `WP-ERROR-032` Section 6 already used for Plugin, not a defect specific to this taxonomy, but worth naming precisely rather than implying an equally explicit citation exists in both sibling entries. | `WP-ERROR-029` Section 7 (Scope), Section 5 above. | Optional: a future revision could add a one-clause precision note; not required to unblock entry authoring, since the underlying ownership conclusion is correct either way. | Disclosed, not corrected — does not block the Gate Decision. |
| — | Conforming | SF-SPEC-013 §5.1, bullet 1 (boundary) | Section 2 declares a clear positive boundary (the switching and update mechanisms) with explicit, evidence-cited exclusions. | Section 2. | None. |
| — | Conforming | `SF-TAXONOMY-005` forward-reference accuracy | The Theme forward-reference quotation independently re-verified verbatim against `SF-TAXONOMY-005`'s own current text. | `SF-TAXONOMY-005`, Section 5 above. | None. |
| — | Conforming | `WP-ERROR-013` boundary accuracy | Both cited passages (Section 4, Section 10) independently confirmed present verbatim in `WP-ERROR-013`'s own current text. | `WP-ERROR-013`, Section 5 above. | None. |
| — | Conforming | `WP-ERROR-019`/`020` boundary accuracy | The shared `wp-content/upgrade` staging-directory language independently confirmed present in both entries' own current text, already covering "theme" alongside "plugin." | `WP-ERROR-019`/`020`, Section 5 above. | None. |
| — | Conforming | `WP-ERROR-028` boundary accuracy | The "plugin/theme/core update checks" language independently confirmed present verbatim in `WP-ERROR-028`'s own current text. | `WP-ERROR-028`, Section 5 above. | None. |
| — | Conforming | `WP-ERROR-014`/`015` boundary accuracy and completeness-gap note | Both entries' own generic "plugin or theme" scoping independently confirmed accurate; the taxonomy's own note that each entry's Typical Symptoms section currently cites only `WP-ERROR-031` independently confirmed accurate as a completeness gap for the future entry to close, not a boundary conflict requiring correction now. | `WP-ERROR-014`/`015`, Section 5 above. | None (correctly deferred to entry-authoring stage). |
| — | Conforming | `WP-ERROR-032` reciprocal exclusion | Independently confirmed `WP-ERROR-032`'s own Scope Section already excludes "Theme update failures, Theme category's own parallel mechanism" by name, predating this taxonomy and confirming the symmetric relationship this taxonomy's own Section 2 describes. | `WP-ERROR-032`, Section 5 above. | None. |
| — | Conforming | SF-SPEC-013 §5.1, bullet 2 (planned entries, ownership) | Two entries, each with a one-line ownership statement in Section 3's table. | Section 3. | None. |
| — | Conforming | Ownership Model (Section 4) internal consistency, including the two-vs-three-stage asymmetry with Plugin | Independently re-derived: WordPress genuinely has no must-use-theme-equivalent mechanism (exactly one active theme at a time, no unconditional-load toggle-free path), so the two-entry model is the complete set the actual mechanism space supports, not an unexplained truncation of Plugin's three-entry model. The switching/update split is mutually exclusive by construction, mirroring `WP-ERROR-031`/`032`'s own relationship. | Section 4. | None. |
| — | Conforming | SF-SPEC-013 §5.1, bullet 3 (rejected/deferred candidates, reasoning) | Six candidates addressed (deactivation, deletion/uninstall, child/parent-theme as a separate entry, Customizer live-preview, template hierarchy, block-theme/`theme.json`), each with specific reasoning distinguishing rejection, folding, or genuine deferral. | Section 5. | None. |
| — | Conforming | `SF-SPEC-001` Section 7 conformance | `Theme` independently confirmed present in the approved category-value list. | `SF-SPEC-001` Section 7, Section 5 above. | None. |
| — | Conforming | ID availability | `WP-ERROR-039` and `WP-ERROR-040` independently confirmed to not currently exist in the repository. | `find` sweep, Section 5 above. | None. |
| — | Conforming | Independent cross-category sweep (fresh terms) | Zero conflicting claims found anywhere in the repository for `stylesheet`, `parent theme`, `broken theme`, `WP_Theme`, `after_switch_theme`, `theme.json`, `block theme`, `full site editing`, or `customizer`. | Independent sweep, Section 5 above. | None. |
| — | Conforming | Technical accuracy | `Theme_Upgrader`/`WP_Upgrader`, `WP_Theme::errors()`, `after_switch_theme`, the `Template` header's parent-theme resolution, and the WordPress 6.5 theme `Requires PHP`/`Requires at least`/`Requires Plugins` headers independently verified as real, current WordPress core mechanisms, not asserted uncritically. | Section 5 above. | None. |
| — | Conforming | Structural sweep | Zero bare `must` outside `must-use`; zero drafting-language matches. | Section 5 above. | None. |

No Major or Critical findings.

---

# 7. Outcome

**Approved.**

**Basis:** `SF-TAXONOMY-008` satisfies every element of **SF-SPEC-013** Section 5.1. Its central structural claim — that Theme's actual mechanism space supports exactly two lifecycle-stage entries rather than a diminished mirror of Plugin's three — was independently re-derived and confirmed sound, not merely accepted from the draft's own account, and every cited sibling entry's own boundary language was independently confirmed accurate against current repository state. One Minor finding (T-1) was disclosed: the `WP-ERROR-029` exclusion rests on that entry's own general scope rather than an explicit "theme" mention, the identical basis `WP-ERROR-032` already used for the same relationship in Plugin. This is a precision note, not a defect, and does not block entry authoring.

---

# 8. Gate Decision

Per **SF-SPEC-013** Section 5.1, entry authoring for the Theme category (`WP-ERROR-039` and `WP-ERROR-040`) may now begin — this taxonomy exists, declares the category's boundary with independently-verified accuracy against every entry it claims already occupies adjacent territory, resolves `SF-TAXONOMY-005`'s own explicit forward-reference promise, enumerates every planned entry, documents rejected/deferred candidates, and has been independently reviewed per this project's established practice, including the proactive cross-category ownership sweep this project's own recent history (`SF-TAXONOMY-006`/`007`) established as standard.

---

# 9. Remaining Risks

- This review was conducted by the same class of agent (Claude Code) as the authoring pass.
- The `WP-ERROR-014`/`015` cross-reference completeness gap (Section 5, Section 6) is expected to be closed by `WP-ERROR-039`'s own independent review, per this catalog's established pattern (`WP-ERROR-031` fixing `WP-ERROR-017`'s own text, `WP-ERROR-032` fixing `WP-ERROR-013`'s own Common Causes). If it is not closed there, that would itself be a finding for the eventual Theme category consistency review to catch.
- The two-entry, lifecycle-stage model (Section 4) is a design choice not yet tested against real entries; if drafting `WP-ERROR-039` reveals the "broken theme" detection cause and the requirement-gate refusal cause are harder to keep cleanly distinguished in practice than this taxonomy assumes, that should surface as a finding in that entry's own author review rather than being silently absorbed.
- The deferred block-theme/`theme.json` candidate (Section 5) remains genuinely undecided, not resolved, pending further evidence that it constitutes a distinct, catalog-worthy failure mode rather than an instance of Section 2's own runtime/rendering exclusion.
- This is the fourth consecutive taxonomy (after Plugin's own mid-production correction, then Performance and Media cleanly) to pass its own ownership sweep during drafting without requiring a boundary correction; per this project's own scope discipline, that strengthens but does not yet generalize the claim beyond this process, this repository, and four categories under a single author/reviewer.

---

# 10. Revision History

| Version | Date | Summary of Changes | Approval Status |
|---|---|---|---|
| 1.0 | 2026-07-15 | Initial independent review of SF-TAXONOMY-008. Independently re-verified the SF-TAXONOMY-005 forward-reference quotation and every cited sibling-entry boundary (WP-ERROR-013/019/020/028/029/014/015/032) against each entry's own current text, including an independently-constructed full-text sweep using fresh search terms that found zero conflicting claims. One Minor finding (T-1) disclosed: the WP-ERROR-029 exclusion rests on general scope rather than an explicit "theme" mention, the same basis WP-ERROR-032 already used for Plugin — not a defect, does not block the gate. Approved. Entry authoring for WP-ERROR-039 and WP-ERROR-040 may begin. | Approved |
