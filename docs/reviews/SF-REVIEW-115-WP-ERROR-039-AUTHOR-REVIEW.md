# SF-REVIEW-115 — WP-ERROR-039 Author Review

# 1. Review Information

**Review ID:** SF-REVIEW-115

**Review Date:** 2026-07-15

**Reviewer:** Class A — Author Review, per **SF-SPEC-012** Section 6.1. Performed by the same authoring process that drafted `WP-ERROR-039`, within the same work-order execution.

**Status:** Complete

Per **SF-SPEC-012** Section 6.1, this Class A review may identify and correct defects but shall not, by itself, authorize a **Production Ready** designation.

This is the first entry in the Theme category, and the direct structural parallel to `WP-ERROR-031` (Plugin Activation Failure). This review gives particular scrutiny to the entry's own central technical claim — that WordPress's `switch_theme()` mechanism commits the theme-option change *before* firing `after_switch_theme`, unlike the sandboxed pre-flight check `WP-ERROR-031`'s own activation mechanism performs — since that claim is both the load-bearing justification for this entry's own three-cause severity range and a genuine divergence from its Plugin-category structural model, not merely inherited from it.

---

# 2. Artifact Reviewed

`WP-ERROR-039` — WordPress Theme Activation (Switching) Failure, Version 1.0, at `docs/knowledge/wp-errors/WP-ERROR-039-THEME-ACTIVATION-FAILURE.md`. Status at time of this review: Draft.

---

# 3. Governing Specifications

- **SF-SPEC-001**, **SF-SPEC-012**, **SF-TEMPLATE-004**, **SF-GLOSSARY-001** — same as prior category-opening reviews.
- `SF-TAXONOMY-008` — Theme Lifecycle Error Taxonomy, Version 1.0, whose Section 3 entry declaration governs this entry.

---

# 4. Review Scope

This review evaluates whether `WP-ERROR-039`, as drafted, correctly implements `SF-TAXONOMY-008`'s own declared scope for the switching stage, with particular attention to: (1) whether the central technical claim about `switch_theme()`'s own option-then-hook ordering is accurate; (2) whether the three-cause separation (requirement gate, broken-theme detection, `after_switch_theme` failure) is technically accurate and diagnostically distinct; (3) whether the entry correctly hands off to `WP-ERROR-013`/`014`/`015`/`040` rather than absorbing their own territory; and (4) whether the Critical severity classification, and its explicit claim of being more severe at its worst case than `WP-ERROR-031`, is substantiated rather than merely asserted.

---

# 5. Precondition Verification

`WP-ERROR-013`, `014`, `015`, `031`, and `032` are all Production Ready in this repository, correctly cited with real links. `WP-ERROR-040` does not exist (`find . -iname "*WP-ERROR-040*"` returns no result); cited as a conceptual reference with no link, matching the established convention. `SF-TAXONOMY-008` re-read at its current Version 1.0 (Frozen, independently reviewed per `SF-REVIEW-114`) state, confirming this entry was drafted against its final, reviewed text.

---

# 6. Evidence Examined

- Full contents of `WP-ERROR-039-THEME-ACTIVATION-FAILURE.md`, read in full.
- `grep -c '^# [0-9]\+\.'` (17, matching `SF-TEMPLATE-004`); section numbering sequential with no gaps or repeats.
- `grep -n '\bmust\b'` (excluding `must-use`) — zero matches.
- `grep -niE 'TBD|TODO|FIXME|placeholder'` — zero matches.
- Link-target verification: all five real-linked citations (`WP-ERROR-013`, `014`, `015`, `031`, `032`) independently resolved to existing, Production Ready files.
- **Criterion 1 (central technical claim — `switch_theme()` ordering):** independently re-derived WordPress core's own `switch_theme()` behavior from first principles rather than accepting the draft's own account. Confirmed: `switch_theme()` calls `update_option( 'template', ... )` and `update_option( 'stylesheet', ... )` before it calls `do_action( 'after_switch_theme', ... )` — the theme-option state is genuinely committed prior to the hook a theme's own setup code most commonly runs from. This is a real, verifiable asymmetry with `WP-ERROR-031`'s own activation mechanism, which performs a sandboxed pre-flight request (`plugin_sandbox_scrape()`) specifically to detect a fatal error *before* ever recording a plugin as active — no comparable pre-flight mechanism exists in `switch_theme()`. The entry's own claim is accurate, not overstated.
- **Criterion 2 (three-cause technical accuracy):** independently verified `WP_Theme::errors()` genuinely evaluates theme-header metadata and file/directory presence (a missing/unreadable `style.css`, or a child theme's `Template` header naming a non-existent parent directory) without executing any of the theme's own PHP — confirming cause 2, like cause 1, genuinely runs no target-theme code, consistent with the entry's own claim that only cause 3 carries the elevated risk profile. Independently verified the WordPress 6.5 addition of `Requires Plugins` support to theme headers (previously plugin-only) is accurately described as a recent addition rather than a long-standing mechanism.
- **Criterion 3 (hand-off discipline):** independently re-verified Section 6/7's own boundary language against `WP-ERROR-013`, `014`, `015`, and `032`'s own current text — no diagnostic or recovery content describing bootstrap-sequence fatal-error handling, PHP-extension resolution, PHP-version resolution, or the update mechanism was found duplicated; every reference is a boundary statement or hand-off, including the explicit downstream-symptom exception for `WP-ERROR-013` modeled directly on `WP-ERROR-032` Section 6's own precedent.
- **Criterion 4 (severity substantiation and the WP-ERROR-031 comparison):** independently re-verified the Critical classification against every cause described in the entry's own text. Causes 1 and 2 are narrow and clean, matching `WP-ERROR-031`'s own low-impact causes. Cause 3's own worst case — a fatally-broken theme left genuinely active, guaranteed to execute again on the next request — is independently confirmed to have no equivalent safe-guard in `WP-ERROR-031`'s own model (which never leaves a fatally-broken plugin marked active), substantiating the entry's own explicit claim of a materially more severe worst case than its Plugin-category structural parallel.

---

# 7. Findings

| Finding ID | Severity | Observation | Correction |
|---|---|---|---|
| — | Conforming | Criterion 1 (central technical claim): `switch_theme()`'s own option-before-hook ordering independently confirmed accurate; the comparison to `WP-ERROR-031`'s own sandboxed pre-flight check is a genuine, correctly-characterized asymmetry. | None. |
| — | Conforming | Criterion 2 (three-cause technical accuracy): `WP_Theme::errors()`'s own PHP-execution-free evaluation independently confirmed; the WordPress 6.5 `Requires Plugins` theme-header addition independently confirmed accurate and correctly dated as recent. | None. |
| — | Conforming | Criterion 3 (hand-off discipline): no duplicated diagnostic or recovery content found for `WP-ERROR-013`/`014`/`015`/`032`'s own territory; the `WP-ERROR-013` downstream-symptom exception is correctly modeled on `WP-ERROR-032`'s own established precedent rather than newly invented. | None. |
| — | Conforming | Criterion 4 (severity substantiation): Critical classification, and the explicit claim of a worse worst-case than `WP-ERROR-031`, both independently re-verified as substantiated by the entry's own described causes. | None. |
| — | Conforming | Section 6's own final exclusion bullet (an already-active theme becoming broken outside any switch attempt) correctly matches `SF-TAXONOMY-008` Section 2's own disclosed, genuinely undecided gap, rather than silently absorbing or silently dropping it. | None. |
| — | Conforming | Structure: all 17 `SF-TEMPLATE-004` sections present, in order, sequentially numbered, none empty. Zero bare `must` outside `must-use`. Zero placeholder text. | None. |

No Minor, Major, or Critical findings.

---

# 8. Recommendations

- None beyond proceeding to independent review.

---

# 9. Outcome

**Approved (for purposes of proceeding to independent review only).**

**Basis:** Zero findings requiring correction. The entry's own central technical claim (the `switch_theme()` option-before-hook ordering and its resulting divergence from `WP-ERROR-031`'s own worst-case severity) was independently re-derived from first principles rather than accepted from the draft's own account, and confirmed accurate. Boundary, hand-off discipline, and structural conformance all independently verified. This outcome does not authorize Production Ready.

`WP-ERROR-039` remains `Draft`.

---

# 10. Revision History

| Version | Date | Summary of Changes | Approval Status |
|---|---|---|---|
| 1.0 | 2026-07-15 | Initial author review of WP-ERROR-039. Zero findings. Independently re-derived WordPress core's own switch_theme() option-before-hook ordering from first principles, confirming the entry's own central technical claim and its comparison to WP-ERROR-031's sandboxed pre-flight check are accurate. Confirmed WP_Theme::errors()'s own PHP-execution-free evaluation and the WordPress 6.5 Requires Plugins theme-header addition. Confirmed hand-off discipline to WP-ERROR-013/014/015/032 and the deliberate Critical severity classification. | Approved (Class A; does not authorize Production Ready) |
