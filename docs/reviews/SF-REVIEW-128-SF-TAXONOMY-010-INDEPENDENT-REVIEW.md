# SF-REVIEW-128 — SF-TAXONOMY-010 Independent Review

# 1. Review Information

**Review ID:** SF-REVIEW-128

**Review Date:** 2026-07-15

**Reviewer:** Class B — Independent Review, per **SF-SPEC-012** Section 6.2, conducted per this project's established practice of independently reviewing a category's taxonomy before entry authoring begins (`SF-REVIEW-034`, `045`, `069`, `080`, `089`, `096`, `105`, `114`, `121`), not as a normative requirement `SF-TAXONOMY-010` itself imposes.

**Status:** Complete

This is the sixth taxonomy drafted using the proactive cross-category ownership sweep discipline. This review gives particular scrutiny to the taxonomy's own two load-bearing technical claims — that WP-Cron's own default trigger is a side effect of ordinary traffic with no independent existence, and that the loopback trigger request's own `'blocking' => false` construction means its result is never inspected by WordPress — since both claims, if wrong, would undermine the taxonomy's own central boundary decision (folding traffic-absence into `WP-ERROR-043` rather than excluding it, and drawing a diagnostic-path distinction from `WP-ERROR-028` rather than fully deferring to it).

---

# 2. Artifact Reviewed

`SF-TAXONOMY-010` — Cron (WP-Cron) Error Taxonomy, Version 1.0, at `docs/standards/SF-TAXONOMY-010-CRON-ERROR-TAXONOMY.md`.

---

# 3. Governing Specifications

- **SF-SPEC-013 — Knowledge Category Lifecycle Specification** (Section 5.1, Category Entry Criteria)
- **SF-SPEC-001 — Error Knowledge Specification** (Section 7, confirming `Cron` is an approved category value)
- **SF-SPEC-004 — Documentation Specification** (internal consistency, cross-references)

---

# 4. Review Scope

This review independently determines whether `SF-TAXONOMY-010` satisfies **SF-SPEC-013** Section 5.1, with particular attention to: (1) whether WordPress's own traffic-dependent triggering behavior is accurately described; (2) whether the `spawn_cron()`/loopback-request claim (non-blocking, uninspected result) is technically accurate and correctly distinguishes this category's own diagnostic path from `WP-ERROR-028`'s; (3) whether every cited sibling entry's own boundary language is accurate against its current text; and (4) whether the two-entry, sequentially-gated ownership model is internally consistent.

---

# 5. Evidence Examined

- Full contents of `SF-TAXONOMY-010`, read in full.
- `SF-SPEC-001` Section 7, independently re-read to confirm `Cron` is an approved category value — confirmed present.
- `WP-ERROR-013`, independently re-read: Section 8 confirmed to list `wp-cron.php` as sharing `wp-load.php`'s own bootstrap code identically with every other entry point; Section 9 confirmed to separately note "Cron requests (`wp-cron.php` or an equivalent trigger) failing without a visible error, since cron requests are not typically viewed in a browser" as one of its own Typical Symptoms — a symptom-visibility observation about `WP-ERROR-013`'s own condition when it happens to occur via cron, not a claim on WP-Cron's own scheduling mechanism, correctly left unclaimed by this taxonomy.
- `WP-ERROR-028`, independently re-read in full: Section 9 confirmed to read "WP-Cron-triggered outbound requests (scheduled tasks that call external services) fail without any visible front-end symptom, surfacing only in logs or in the scheduled task's own downstream effects not occurring" — confirmed this concerns a scheduled task's own outbound call, not WordPress's self-triggering loopback request, matching this taxonomy's own careful distinction between the two. Section 7 (Scope) and Section 4 (Primary Failure Mode) independently re-read to confirm `WP-ERROR-028`'s own condition is defined by `WP_Http::request()` returning an inspectable `WP_Error` — directly supporting this taxonomy's own central claim that a call whose result is never inspected (the loopback trigger) presents a genuinely different diagnostic posture.
- `WP-ERROR-009`, independently re-read: confirmed its own Section 8 and Section 14 name `WP-Cron` as a common, legitimate destination for a long-running query moved out of the ordinary request path, accurately cited.
- `WP-ERROR-014`, independently re-read: Section 11 confirmed to read "a scheduled-job runner such as system cron invoking WP-Cron or WP-CLI" verbatim. `WP-ERROR-015`, independently re-read: its own equivalent text reads only "a scheduled-job runner," without `WP-ERROR-014`'s own further elaboration — confirming this taxonomy's own (corrected) text accurately distinguishes the two rather than overstating `WP-ERROR-015`'s own citation to match `WP-ERROR-014`'s.
- `SF-TAXONOMY-005` Section 5, independently re-read: confirmed its own "Plugin Deactivation Failure" analysis names "orphaned cron events" as an example of a plugin-specific cleanup gap, accurately cited.
- Independent technical verification of WordPress's own `spawn_cron()` behavior: confirmed it issues its loopback trigger request via `wp_remote_post()` with `'blocking' => false` and a short timeout, meaning the calling code does not wait for, inspect, or report the request's own outcome — substantiating this taxonomy's own central diagnostic-asymmetry claim.
- Independent technical verification of WordPress's own default cron-triggering behavior: confirmed it is checked on qualifying page loads via the `wp_loaded` hook path (through `wp-cron.php` being called by `spawn_cron()`, itself invoked from `wp-includes/cron.php`'s own hook into ordinary request processing), with no independent, traffic-free existence — substantiating this taxonomy's own central "side effect of traffic" claim.
- An independently-constructed full-text sweep — using search terms distinct from the taxonomy's own drafting account (`cron.lock`, `concurrent.{0,15}(trigger|cron)`, `traffic.{0,15}(dependent|driven)`, `pseudo.cron`, `alternate.wp.cron`, `wp_remote_post`) — across every file in `docs/knowledge/wp-errors/` and `docs/standards/SF-TAXONOMY-*.md`. The only matches outside this taxonomy's own new text are `WP-ERROR-028`/`029`'s own generic `wp_remote_post()` component listings, already accounted for by this taxonomy's own Section 2; no additional, unnamed conflicting entry found.
- `find . -iname "*WP-ERROR-043*" -o -iname "*WP-ERROR-044*"`, confirming neither planned ID currently exists.
- `grep -n '\bmust\b'` (excluding `must-use`) against the full document: zero matches.

---

# 6. Findings

| Finding ID | Severity | Requirement or Criterion | Observation | Evidence | Required Action | Resolution Status |
|---|---|---|---|---|---|---|
| — | Conforming | SF-SPEC-013 §5.1, bullet 1 (boundary) | Section 2 declares a clear positive boundary (WP-Cron's own triggering and event-processing machinery) with extensive, independently-verified exclusions. | Section 2. | None. |
| — | Conforming | `WP-ERROR-013` boundary accuracy | Both cited passages independently confirmed present and accurate; the Section 9 symptom-visibility note correctly identified as not a claim on this taxonomy's own territory. | `WP-ERROR-013`, Section 5 above. | None. |
| — | Conforming | `WP-ERROR-028` boundary accuracy and diagnostic-asymmetry claim | The Section 9 citation confirmed accurate and correctly distinguished from the loopback-request condition; the diagnostic-asymmetry claim (inspectable `WP_Error` versus a deliberately uninspected, non-blocking call) independently verified as technically accurate. | `WP-ERROR-028`, Section 5 above. | None. |
| — | Conforming | `WP-ERROR-009` boundary accuracy | Independently confirmed accurate. | `WP-ERROR-009`, Section 5 above. | None. |
| — | Conforming | `WP-ERROR-014`/`015` boundary accuracy and precision | `WP-ERROR-014`'s own fuller citation and `WP-ERROR-015`'s own narrower one independently confirmed accurate; this taxonomy's own text (as corrected before this review) does not overstate either. | `WP-ERROR-014`/`015`, Section 5 above. | None. |
| — | Conforming | `SF-TAXONOMY-005` boundary accuracy | Independently confirmed accurate. | Section 5 above. | None. |
| — | Conforming | Central technical claim 1 (traffic-dependence) | Independently verified: WP-Cron's own default trigger has no existence independent of qualifying page-load traffic. | Section 5 above. | None. |
| — | Conforming | Central technical claim 2 (loopback diagnostic asymmetry) | Independently verified: `spawn_cron()`'s own non-blocking, uninspected construction is accurate and substantiates the taxonomy's own refusal to fully defer to `WP-ERROR-028`. | Section 5 above. | None. |
| — | Conforming | Ownership Model (Section 4) internal consistency | Independently re-derived: the sequentially-gated, two-stage model (triggering, then event-processing) is logically sound; `WP-ERROR-044`'s own condition is genuinely unreachable without `WP-ERROR-043`'s own condition first not applying. | Section 4. | None. |
| — | Conforming | SF-SPEC-013 §5.1, bullet 2 (planned entries, ownership) | Two entries, each with a one-line ownership statement in Section 3's table. | Section 3. | None. |
| — | Conforming | SF-SPEC-013 §5.1, bullet 3 (rejected/deferred candidates, reasoning) | Four candidates addressed (duplicate/stacked events, missed scheduled post, system-cron misconfiguration, cron-lock deadlock), each with specific reasoning distinguishing rejection from folding. | Section 5. | None. |
| — | Conforming | `SF-SPEC-001` Section 7 conformance | `Cron` independently confirmed present in the approved category-value list. | Section 5 above. | None. |
| — | Conforming | ID availability | `WP-ERROR-043` and `WP-ERROR-044` independently confirmed to not currently exist. | `find` sweep, Section 5 above. | None. |
| — | Conforming | Independent cross-category sweep (fresh terms) | Zero conflicting claims found beyond already-accounted-for `wp_remote_post()` component listings. | Independent sweep, Section 5 above. | None. |
| — | Conforming | Structural sweep | Zero bare `must` outside `must-use`; zero drafting-language matches. | Section 5 above. | None. |

No Major or Critical findings.

---

# 7. Outcome

**Approved.**

**Basis:** `SF-TAXONOMY-010` satisfies every element of **SF-SPEC-013** Section 5.1. Its two central technical claims — WP-Cron's own traffic-dependence, and the loopback trigger request's own deliberately uninspected, non-blocking construction — were independently re-derived from first principles rather than accepted from the draft's own account, and both confirmed accurate. Every cited sibling entry's own boundary language was independently confirmed accurate against current repository state, including a precision correction (`WP-ERROR-014` versus `015`'s own differing SAPI-example wording) already applied before this review formally began.

---

# 8. Gate Decision

Per **SF-SPEC-013** Section 5.1, entry authoring for the Cron category (`WP-ERROR-043` and `WP-ERROR-044`) may now begin — this taxonomy exists, declares the category's boundary with independently-verified accuracy against every entry it claims already occupies adjacent territory, enumerates both planned entries, documents rejected/deferred candidates, and has been independently reviewed per this project's established practice, including the proactive cross-category ownership sweep this project's own recent history established as standard.

---

# 9. Remaining Risks

- This review was conducted by the same class of agent (Claude Code) as the authoring pass.
- The central decision to fold traffic-absence into `WP-ERROR-043` rather than treating it as a separate condition (Section 4) is a design choice not yet tested against a real entry; if drafting reveals the traffic-absence cause and the loopback-connectivity cause are harder to keep diagnostically distinct in practice than this taxonomy assumes, that should surface as a finding in that entry's own author review.
- The rejected duplicate/stacked-event candidate (Section 5) remains a genuinely common real-world WordPress operational issue even though it is correctly excluded here as a plugin-specific defect; this taxonomy's own reasoning for excluding it should be re-examined if evidence later suggests WordPress's own scheduling API itself, not merely calling code, contributes to the pattern.
- This is the fifth consecutive taxonomy (after Plugin's own mid-production correction, then Performance, Media, Theme, CLI cleanly) to pass its own ownership sweep during drafting without requiring a boundary correction; per this project's own scope discipline, this strengthens but does not yet generalize the claim beyond this process, this repository, and five categories under a single author/reviewer.

---

# 10. Revision History

| Version | Date | Summary of Changes | Approval Status |
|---|---|---|---|
| 1.0 | 2026-07-15 | Initial independent review of SF-TAXONOMY-010. Independently re-derived and confirmed both central technical claims (WP-Cron's own traffic-dependence; the loopback trigger request's own deliberately uninspected, non-blocking construction) from first principles. Independently re-verified every cited sibling-entry boundary (WP-ERROR-009/013/014/015/028, SF-TAXONOMY-005) against each entry's own current text, including confirming a precision correction between WP-ERROR-014's and WP-ERROR-015's own differing SAPI-example wording. An independently-constructed full-text sweep found zero conflicting claims. No findings. Approved. Entry authoring for WP-ERROR-043 and WP-ERROR-044 may begin. | Approved |
