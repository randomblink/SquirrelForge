# SF-REVIEW-121 — SF-TAXONOMY-009 Independent Review

# 1. Review Information

**Review ID:** SF-REVIEW-121

**Review Date:** 2026-07-15

**Reviewer:** Class B — Independent Review, per **SF-SPEC-012** Section 6.2, conducted per this project's established practice of independently reviewing a category's taxonomy before entry authoring begins (`SF-REVIEW-034`, `045`, `069`, `080`, `089`, `096`, `105`, `114`), not as a normative requirement `SF-TAXONOMY-009` itself imposes.

**Status:** Complete

This is the fifth taxonomy drafted using the proactive cross-category ownership sweep discipline, and the first for a category whose own execution context (WP-CLI, not an HTTP request) differs structurally from every prior category. This review gives particular scrutiny to whether the taxonomy's own central claim — that the overwhelming majority of "WP-CLI failure" candidates are already owned by existing entries — is independently verifiable rather than a convenient narrowing, and whether the two entries it does plan are genuinely novel WP-CLI-specific mechanisms rather than restatements of territory covered elsewhere.

---

# 2. Artifact Reviewed

`SF-TAXONOMY-009` — CLI (WP-CLI) Error Taxonomy, Version 1.0, at `docs/standards/SF-TAXONOMY-009-CLI-ERROR-TAXONOMY.md`.

---

# 3. Governing Specifications

- **SF-SPEC-013 — Knowledge Category Lifecycle Specification** (Section 5.1, Category Entry Criteria)
- **SF-SPEC-001 — Error Knowledge Specification** (Section 7, confirming `CLI` is an approved category value)
- **SF-SPEC-004 — Documentation Specification** (internal consistency, cross-references)

---

# 4. Review Scope

This review independently determines whether `SF-TAXONOMY-009` satisfies **SF-SPEC-013** Section 5.1, with particular attention to: (1) whether the claim that WP-CLI is already comprehensively cited as an alternate entry point across this catalog is accurate, re-verified against a sample of the cited entries' own current text rather than accepted from the draft's own account; (2) whether `WP-ERROR-041`'s own boundary against the not-yet-authored `WP-ERROR-010`/`011` and against `WP-ERROR-013` is technically sound; (3) whether `WP-ERROR-042`'s own boundary against the future `Multisite` category is a genuine, defensible distinction rather than an arbitrary line; and (4) whether the "sequential-but-conditional" ownership model is internally consistent.

---

# 5. Evidence Examined

- Full contents of `SF-TAXONOMY-009`, read in full.
- `SF-SPEC-001` Section 7, independently re-read to confirm `CLI` is an approved category value — confirmed present in the list.
- `WP-ERROR-013`, independently re-read in full: Section 7 (Scope) confirmed to read "...across front-end, administrative, AJAX, cron, REST, and WP-CLI bootstrap paths"; Section 8 (WordPress Components) confirmed to read "The WP-CLI bootstrap path, which loads `wp-load.php` in the same manner as a web request, sharing the same core, must-use plugin, drop-in, plugin, and theme bootstrap code"; Section 16 (Related Errors) confirmed to list `WP-ERROR-010`/`011` as conceptual references, with `WP-ERROR-010`'s own description reading "covers the case where `wp-config.php` cannot be located at all, which prevents bootstrap from ever reaching the point this entry addresses." All three citations independently confirmed accurate, and the boundary this taxonomy draws (`WP-ERROR-041` owns WP-CLI's own separate, tool-level discovery process, occurring *before* `wp-load.php`'s own simpler existence check that `WP-ERROR-010` would conceptually cover) is independently confirmed to be a real, non-overlapping distinction rather than a restatement of `WP-ERROR-010`'s own territory — the two operate at genuinely different points (WP-CLI's own directory-tree search versus `wp-load.php`'s own fixed-location check), and neither a web request nor `wp-load.php` itself performs anything resembling WP-CLI's own broader search.
- A sample of six further WP-CLI citations (`WP-ERROR-016`, `WP-ERROR-003`, `WP-ERROR-004`, `WP-ERROR-031`, `WP-ERROR-032`, `WP-ERROR-026`) independently re-read at the specific cited lines: each confirmed to name WP-CLI as reaching an already-owned mechanism (`wp core verify-checksums`, `wp db query`, `wp plugin activate`/`update`, `wp user list-caps`) with no indication any of them intends WP-CLI's own pre-bootstrap or site-targeting behavior as part of its own scope.
- An independently-constructed full-text sweep — using search terms distinct from the taxonomy's own drafting account (`does not seem to be a WordPress`, `--path`, `installation.{0,20}(discover|detect|locate|find)`, `wp_blogs`, `wp_site`, `domain.mapping`, `primary site`, `network.{0,15}site`) — across every file in `docs/knowledge/wp-errors/` and `docs/standards/SF-TAXONOMY-*.md`. Zero matches describing either candidate condition (WP-CLI's own installation-discovery mechanism, or WP-CLI's own multisite site-targeting mechanism) found anywhere outside this taxonomy's own new text; the Multisite-adjacent matches found (`WP-ERROR-005`/`006`'s own per-site table structure, `WP-ERROR-036`'s own per-site upload quota) are all about database/quota structure, not site-resolution/targeting, and do not conflict with this taxonomy's own `WP-ERROR-042` boundary.
- `find . -iname "*WP-ERROR-041*" -o -iname "*WP-ERROR-042*"`, confirming neither planned ID currently exists.
- `grep -n '\bmust\b'` (excluding `must-use`) against the full document: two matches found during drafting, corrected before this review formally began (both converted to "shall").
- Independent technical verification of WP-CLI's own actual behavior: confirmed WP-CLI performs its own directory-tree search for a WordPress installation (ascending from the current working directory, or using an explicit `--path`) *before* attempting to load anything, distinct from and more extensive than `wp-load.php`'s own simpler same-directory-or-one-level-up check; confirmed WP-CLI requires an explicit `--url` (or a configured default) to establish site context on a Multisite installation, since it has no HTTP Host header to derive one from automatically, and that an unresolved or mismatched `--url` can cause WP-CLI to operate against an unintended site rather than necessarily erroring outright. Both technical claims independently verified as accurate descriptions of WP-CLI's own actual behavior, not asserted uncritically.
- The "sequential-but-conditional" ownership model (Section 4), independently re-derived: `WP-ERROR-042`'s own condition is genuinely unreachable without `WP-ERROR-041`'s own condition first resolving successfully (WP-CLI cannot attempt site-context resolution against an installation it has not found), and is additionally, independently gated on Multisite being active at all — both conditions independently confirmed logically sound and mutually exclusive by construction.

---

# 6. Findings

| Finding ID | Severity | Requirement or Criterion | Observation | Evidence | Required Action | Resolution Status |
|---|---|---|---|---|---|---|
| — | Conforming | SF-SPEC-013 §5.1, bullet 1 (boundary) | Section 2 declares a clear positive boundary (WP-CLI's own pre-WordPress, tool-level mechanisms) with an unusually extensive, independently-verified set of exclusions. | Section 2. | None. |
| — | Conforming | Central claim: WP-CLI already comprehensively cited elsewhere | Independently re-verified a sample of six further citations beyond `WP-ERROR-013`; all confirmed accurate. The taxonomy's own narrow scope is independently confirmed to be a genuine consequence of prior entries' own citation practice, not a convenient or unverified narrowing. | Section 5 above. | None. |
| — | Conforming | `WP-ERROR-013`/`010`/`011` boundary accuracy | All three citations independently confirmed accurate against `WP-ERROR-013`'s own current text; the distinction between WP-CLI's own discovery process and `wp-load.php`'s own simpler check is real and non-overlapping. | `WP-ERROR-013`, Section 5 above. | None. |
| — | Conforming | Future `Multisite` category boundary (`WP-ERROR-042`) | Independently re-derived: WP-CLI's own failure to supply a valid site-targeting input is a genuinely different condition from Multisite's own resolution mechanism failing given valid input, and no existing entry claims either side of this distinction. | Section 5 above. | None. |
| — | Conforming | Technical accuracy of both candidate mechanisms | WP-CLI's own installation-discovery search and its own `--url`-based site-targeting requirement independently verified as accurate descriptions of actual WP-CLI behavior. | Section 5 above. | None. |
| — | Conforming | Ownership Model (Section 4) internal consistency | The "sequential-but-conditional" model independently re-derived and confirmed logically sound; a structurally new but well-reasoned departure from this catalog's prior independent-mechanisms and pipeline models, not a forced fit. | Section 4. | None. |
| — | Conforming | SF-SPEC-013 §5.1, bullet 2 (planned entries, ownership) | Two entries, each with a one-line ownership statement in Section 3's table. | Section 3. | None. |
| — | Conforming | SF-SPEC-013 §5.1, bullet 3 (rejected/deferred candidates, reasoning) | Five candidates addressed (generic catch-all, tool-level requirements, capability/`--user` design, remote-transport connectivity, error-reporting conventions), each with specific reasoning distinguishing rejection from genuine deferral. | Section 5. | None. |
| — | Conforming | `SF-SPEC-001` Section 7 conformance | `CLI` independently confirmed present in the approved category-value list. | `SF-SPEC-001` Section 7, Section 5 above. | None. |
| — | Conforming | ID availability | `WP-ERROR-041` and `WP-ERROR-042` independently confirmed to not currently exist in the repository. | `find` sweep, Section 5 above. | None. |
| — | Conforming | Independent cross-category sweep (fresh terms) | Zero conflicting claims found anywhere in the repository for either candidate condition, using search terms independently constructed rather than reused from the draft. | Independent sweep, Section 5 above. | None. |
| — | Conforming | Structural sweep | Zero bare `must` outside `must-use` at time of this review (two instances found and corrected during drafting, before this review formally began); zero drafting-language matches. | Section 5 above. | None. |

No Major or Critical findings.

---

# 7. Outcome

**Approved.**

**Basis:** `SF-TAXONOMY-009` satisfies every element of **SF-SPEC-013** Section 5.1. Its central claim — that this category's own genuinely available territory is unusually narrow because the catalog has already comprehensively claimed WP-CLI as an alternate entry point elsewhere — was independently re-verified against a sample of cited entries rather than accepted from the draft's own account, and found accurate. Both planned entries were independently confirmed to describe genuinely novel, currently-unclaimed WP-CLI-specific mechanisms, correctly bounded against both an existing entry (`WP-ERROR-013`) and a not-yet-authored future territory (`WP-ERROR-010`/`011`, and the future `Multisite` category).

---

# 8. Gate Decision

Per **SF-SPEC-013** Section 5.1, entry authoring for the CLI category (`WP-ERROR-041` and `WP-ERROR-042`) may now begin — this taxonomy exists, declares the category's boundary with independently-verified accuracy against every entry it claims already occupies adjacent territory, resolves no pending forward-reference but correctly anticipates two not-yet-authored territories (Configuration, Multisite) without overreaching into either, enumerates both planned entries, documents rejected/deferred candidates, and has been independently reviewed per this project's established practice, including the proactive cross-category ownership sweep this project's own recent history established as standard.

---

# 9. Remaining Risks

- This review was conducted by the same class of agent (Claude Code) as the authoring pass.
- This is the first taxonomy in this catalog whose own category ultimately plans only two entries by deliberate, well-reasoned design rather than incomplete research; if entry authoring reveals a third genuinely novel WP-CLI-specific mechanism this taxonomy's own research missed, that would be a real finding for the entry's own review to surface, not evidence this taxonomy was rushed.
- The "sequential-but-conditional" ownership model (Section 4) is a design choice not yet tested against real entries; if drafting `WP-ERROR-041` or `WP-ERROR-042` reveals the boundary between them is harder to keep cleanly separated in practice than this taxonomy assumes, that should surface as a finding in that entry's own author review rather than being silently absorbed.
- The deferred `--ssh=`/`--http=` remote-transport candidate (Section 5) remains genuinely undecided, not resolved.
- Both of this taxonomy's own exclusions against not-yet-authored territory (`WP-ERROR-010`/`011`, and the future `Multisite` category) are forward-looking claims that cannot be fully tested until those categories are eventually taxonomized; unlike this catalog's prior forward-reference resolutions (Media resolving Filesystem's own promise, Theme resolving Plugin's own promise), this is the first taxonomy to *create* two new forward-references rather than resolve an existing one, worth tracking when Configuration and Multisite are eventually taken up.

---

# 10. Revision History

| Version | Date | Summary of Changes | Approval Status |
|---|---|---|---|
| 1.0 | 2026-07-15 | Initial independent review of SF-TAXONOMY-009. Independently re-verified the taxonomy's own central claim (WP-CLI already comprehensively owned as an alternate entry point elsewhere) against a sample of seven citing entries, confirmed the WP-ERROR-013/010/011 boundary and the future-Multisite boundary are both genuine, non-overlapping distinctions, independently verified WP-CLI's own installation-discovery and multisite --url behavior as technically accurate, and ran an independently-constructed full-text sweep that found zero conflicting claims. No findings requiring correction beyond two bare-must instances already fixed during drafting. Approved. Entry authoring for WP-ERROR-041 and WP-ERROR-042 may begin. | Approved |
