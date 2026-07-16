# SF-REVIEW-140 — SF-TAXONOMY-012 Independent Review

# 1. Review Information

**Review ID:** SF-REVIEW-140

**Review Date:** 2026-07-15

**Reviewer:** Class B — Independent Review, per **SF-SPEC-012** Section 6.2, conducted per this project's established practice of independently reviewing a category's taxonomy before entry authoring begins (`SF-REVIEW-034`, `045`, `069`, `080`, `089`, `096`, `105`, `114`, `121`, `128`, `135`), not as a normative requirement `SF-TAXONOMY-012` itself imposes.

**Status:** Complete

This is the eighth taxonomy drafted using the proactive cross-category ownership sweep discipline. This review gives particular scrutiny to two claims: (1) the taxonomy's own central technical claim that PHPMailer's SMTP transport does not use `WP_Http` and therefore does not overlap `WP-ERROR-028`/`029`, since a superficially plausible but incorrect claim here would be a serious defect; and (2) the taxonomy's own discovery and resolution of a cross-taxonomy dependency with `SF-TAXONOMY-003`'s own deferred password-reset candidate, since this is the first taxonomy in this catalog to resolve part of another *deferred candidate* rather than a fully-scoped forward-reference.

---

# 2. Artifact Reviewed

`SF-TAXONOMY-012` — Email Error Taxonomy, Version 1.0, at `docs/standards/SF-TAXONOMY-012-EMAIL-ERROR-TAXONOMY.md`.

---

# 3. Governing Specifications

- **SF-SPEC-013 — Knowledge Category Lifecycle Specification** (Section 5.1, Category Entry Criteria)
- **SF-SPEC-001 — Error Knowledge Specification** (Section 7, confirming `Email` is an approved category value)
- **SF-SPEC-004 — Documentation Specification** (internal consistency, cross-references)

---

# 4. Review Scope

This review independently determines whether `SF-TAXONOMY-012` satisfies **SF-SPEC-013** Section 5.1, with particular attention to: (1) whether PHPMailer's SMTP transport genuinely does not use `WP_Http`, independently verified rather than accepted from the draft's own account; (2) whether the `SF-TAXONOMY-003`/`WP-ERROR-024` cross-taxonomy resolution is accurately characterized — specifically, whether it is correctly scoped to the delivery-failure portion only, leaving the key-generation/validation portion genuinely open rather than silently absorbed; (3) whether the two-entry, independent-mechanisms model is soundly reasoned; and (4) whether the deferred message-validation candidate is honestly characterized as undecided rather than either claimed or dropped.

---

# 5. Evidence Examined

- Full contents of `SF-TAXONOMY-012`, read in full.
- `SF-SPEC-001` Section 7, independently re-read to confirm `Email` is an approved category value — confirmed present.
- Independent technical verification of PHPMailer's own SMTP implementation: confirmed PHPMailer's `SMTP` class communicates directly via PHP socket functions (`fsockopen()`/`stream_socket_client()`), implementing the SMTP protocol's own command/response exchange itself, entirely independent of `WP_Http`, `wp_remote_get()`, or any WordPress HTTP API function. This is independently confirmed accurate, not a plausible-but-wrong claim — genuinely different from every other "outbound network activity" this catalog has previously examined (which, without exception until now, has routed through `WP_Http`).
- `WP-ERROR-028`, independently re-read: Section 7 (Scope) confirmed to define its own condition specifically as `WP_Http`-based, with no language broad enough to be read as covering an unrelated, non-`WP_Http` protocol such as raw SMTP.
- `WP-ERROR-028`'s own Section 6 citation this taxonomy quotes (regarding `WP-ERROR-007`/`008`'s own separate database-driver connection) independently re-verified verbatim against `WP-ERROR-028`'s own current text — confirmed accurate after this taxonomy's own correction (initially misattributed to `WP-ERROR-004`, corrected before this review formally began).
- `WP-ERROR-024`, independently re-read in full: confirmed its own Section 6 (or equivalent) explicitly names "Password-reset or lost-password recovery flow failures... a related but distinct mechanism, not currently covered by any entry in this category; see `SF-TAXONOMY-003` Section 5."
- `SF-TAXONOMY-003` Section 5, independently re-read in full: confirmed its own "Password-reset / lost-password recovery flow" bullet is quoted accurately and completely by this taxonomy, including the specific phrase "Email category boundaries... to disambiguate the delivery-failure portion" this taxonomy cites as anticipating its own eventual resolution.
- **Cross-taxonomy resolution scope, independently re-derived:** confirmed this taxonomy's own Section 2 resolves *only* the delivery-failure portion (once `wp_mail()` is actually called for a reset email, `WP-ERROR-046`/`047` own what happens next) and explicitly, correctly leaves the reset key's own generation and validation mechanics unclaimed — this taxonomy does not overreach into claiming the whole of `SF-TAXONOMY-003`'s own deferred candidate, only the portion its own boundary actually covers.
- A full-text sweep — independently re-run using the same core terms (`wp_mail`, `PHPMailer`, `SMTP`, `sendmail`) plus additional terms distinct from the draft's own account (`mailer`, `MTA`, `fsockopen`, `transactional.email`) — across every file in `docs/knowledge/wp-errors/` and `docs/standards/SF-TAXONOMY-*.md`. Confirmed zero conflicting claims on this category's own core territory; the only matches found (`WP-ERROR-024`'s own reset-flow deferral, `WP-ERROR-028`'s own "transactional email" impact-severity examples) are both already correctly accounted for in this taxonomy's own Section 2.
- `find . -iname "*WP-ERROR-046*" -o -iname "*WP-ERROR-047*"`, confirming neither planned ID currently exists.
- `grep -n '\bmust\b'` (excluding `must-use`) against the full document: zero matches.
- Independent reasoning check on the deferred message-validation candidate (Section 5): confirmed the taxonomy's own text neither claims this territory for either planned entry nor silently drops it, consistent with the honest disclosure standard this catalog has applied to comparable gaps (`WP-ERROR-028`'s own read-timeout disclosure, `SF-TAXONOMY-009`'s own `--ssh=` remote-transport disclosure).

---

# 6. Findings

| Finding ID | Severity | Requirement or Criterion | Observation | Evidence | Required Action | Resolution Status |
|---|---|---|---|---|---|---|
| — | Conforming | SF-SPEC-013 §5.1, bullet 1 (boundary) | Section 2 declares a clear positive boundary with independently-verified exclusions, including a corrected citation. | Section 2. | None. |
| — | Conforming | Central technical claim (SMTP does not use `WP_Http`) | Independently verified accurate via PHPMailer's own actual socket-level implementation, not accepted uncritically. | Section 5 above. | None. |
| — | Conforming | `WP-ERROR-028` boundary accuracy | Scope confirmed `WP_Http`-specific; the quoted database-driver citation confirmed accurate against `WP-ERROR-028`'s own current text after this taxonomy's own pre-review correction. | `WP-ERROR-028`, Section 5 above. | None. |
| — | Conforming | Cross-taxonomy resolution (`SF-TAXONOMY-003`/`WP-ERROR-024`) | Independently re-verified the quotation is accurate and complete, and that this taxonomy's own resolution is correctly scoped to the delivery-failure portion only, not overreaching into the still-open key-generation/validation portion. | `WP-ERROR-024`, `SF-TAXONOMY-003`, Section 5 above. | None. |
| — | Conforming | Ownership Model (Section 4) internal consistency | The two-entry, independent-mechanisms model independently re-derived and confirmed sound: local `mail()` and SMTP are genuinely mutually exclusive per-installation configurations. | Section 4. | None. |
| — | Conforming | SF-SPEC-013 §5.1, bullet 2 (planned entries, ownership) | Two entries, each with a one-line ownership statement in Section 3's table. | Section 3. | None. |
| — | Conforming | SF-SPEC-013 §5.1, bullet 3 (rejected/deferred candidates, reasoning) | Three candidates addressed (message validation, mail deliverability, notification business-logic), each with specific reasoning distinguishing genuine deferral from outright rejection. | Section 5. | None. |
| — | Conforming | `SF-SPEC-001` Section 7 conformance | `Email` independently confirmed present in the approved category-value list. | Section 5 above. | None. |
| — | Conforming | ID availability | `WP-ERROR-046` and `WP-ERROR-047` independently confirmed to not currently exist. | `find` sweep, Section 5 above. | None. |
| — | Conforming | Independent cross-category sweep (fresh terms) | Zero conflicting claims found beyond two already-correctly-accounted-for matches. | Independent sweep, Section 5 above. | None. |
| — | Conforming | Structural sweep | Zero bare `must` outside `must-use`; zero drafting-language matches. | Section 5 above. | None. |

No Major or Critical findings.

---

# 7. Outcome

**Approved.**

**Basis:** `SF-TAXONOMY-012` satisfies every element of **SF-SPEC-013** Section 5.1. Its own central technical claim — that PHPMailer's SMTP transport is genuinely independent of `WP_Http` and therefore does not overlap `WP-ERROR-028`/`029` despite superficial similarity — was independently verified rather than accepted at face value, and confirmed accurate. Its own discovery and resolution of a cross-taxonomy dependency with `SF-TAXONOMY-003`'s deferred password-reset candidate was independently re-verified as correctly and narrowly scoped, not an overreach into territory this taxonomy does not actually own.

---

# 8. Gate Decision

Per **SF-SPEC-013** Section 5.1, entry authoring for the Email category (`WP-ERROR-046` and `WP-ERROR-047`) may now begin — this taxonomy exists, declares the category's boundary with independently-verified accuracy against every entry and sibling taxonomy it references, enumerates both planned entries, documents rejected and deferred candidates with genuine reasoning, and has been independently reviewed per this project's established practice, including the proactive cross-category ownership sweep this project's own recent history established as standard.

---

# 9. Remaining Risks

- This review was conducted by the same class of agent (Claude Code) as the authoring pass.
- The deferred message-validation/composition-failure candidate (Section 5) remains genuinely undecided, not resolved; a future revision to this taxonomy could still carve it out if evidence accumulates.
- The reset-key generation/validation portion of `SF-TAXONOMY-003`'s own deferred candidate remains genuinely unclaimed by any taxonomy in this catalog, unchanged by this review — a future Authentication-category revision, not this taxonomy's own, would be required to close it.
- This is the seventh consecutive taxonomy (after Plugin's own mid-production correction, then Performance, Media, Theme, CLI, Cron, Multisite cleanly) to pass its own ownership sweep during drafting without requiring a boundary correction; per this project's own scope discipline, this strengthens but does not yet generalize the claim beyond this process, this repository, and seven categories under a single author/reviewer.

---

# 10. Revision History

| Version | Date | Summary of Changes | Approval Status |
|---|---|---|---|
| 1.0 | 2026-07-15 | Initial independent review of SF-TAXONOMY-012. Independently verified PHPMailer's SMTP transport is genuinely independent of WP_Http, substantiating this taxonomy's own central claim that it does not overlap WP-ERROR-028/029. Independently re-verified the cross-taxonomy resolution of SF-TAXONOMY-003's own deferred password-reset candidate is accurate and correctly scoped to the delivery-failure portion only. An independently-constructed full-text sweep found zero conflicting claims. No findings. Approved. Entry authoring for WP-ERROR-046 and WP-ERROR-047 may begin. | Approved |
