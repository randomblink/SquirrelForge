# SF-REVIEW-076 — WP-ERROR-027 Author Review

# 1. Review Information

**Review ID:** SF-REVIEW-076

**Review Date:** 2026-07-14

**Reviewer:** Class A — Author Review, per **SF-SPEC-012** Section 6.1. Performed by the same authoring process that drafted `WP-ERROR-027`, within the same work-order execution.

**Status:** Complete

Per **SF-SPEC-012** Section 6.1, this Class A review may identify and correct defects but shall not, by itself, authorize a **Production Ready** designation.

---

# 2. Artifact Reviewed

`WP-ERROR-027` — WordPress Nonce Verification Failure, Non-REST, Version 1.0, at `docs/knowledge/wp-errors/WP-ERROR-027-NONCE-VERIFICATION-FAILURE-NON-REST.md`. Status at time of this review: Draft.

---

# 3. Governing Specifications

- **SF-SPEC-001**, **SF-SPEC-012**, **SF-TEMPLATE-004**, **SF-GLOSSARY-001** — same as prior Authentication-category reviews.
- `SF-TAXONOMY-003` — Authentication Error Taxonomy, Version 1.3 (post-`WP-ERROR-026` promotion), whose Section 2 boundary and Section 3 entry declaration for `WP-ERROR-027` govern this entry, and whose Section 3 note (all four entries now planned, completing the category) this entry's own promotion will finalize.

---

# 4. Review Scope

This review evaluates whether `WP-ERROR-027`, as drafted, satisfies `SF-TAXONOMY-003`'s declared boundary, correctly incorporates the project owner's own explicit requirements (the "nonce is not authentication/authorization/replay-prevention" framing stated prominently near the top; the nine-cause separation; the generation-versus-verification diagnostic pairing; the prohibition on disabling nonce verification as a recovery), and satisfies **SF-SPEC-001**'s authoring standards.

---

# 5. Precondition Verification

`WP-ERROR-024`, `025`, `026`, and `022` are Production Ready in this repository, correctly cited with real links. This is the fourth and final entry `SF-TAXONOMY-003` plans; no further Authentication entry remains conceptually cited after this one. `SF-TAXONOMY-003` re-read at its current Version 1.3 state to confirm this entry is drafted against the current taxonomy.

---

# 6. Evidence Examined

- Full contents of `WP-ERROR-027-NONCE-VERIFICATION-FAILURE-NON-REST.md`, read in full.
- `grep -c '^# [0-9]\+\.'` (17, matching `SF-TEMPLATE-004`).
- `grep -n '\bmust\b' | grep -v "must-use"` — one match found in descriptive prose (Section 10), corrected to "are required to" during this review. Re-swept: zero remaining.
- `grep -Ein 'TODO|TBD|placeholder|future work|planned|should consider|to be determined|intended to be added'` — zero matches (this entry, unlike its three siblings, cites no further conceptual/planned entry, since it is the category's own final planned entry).
- `git diff --check` (clean).
- Link-target verification: `WP-ERROR-024`, `025`, `026`, `022` links independently resolved to existing files.
- `scripts/validate-repo.sh .`: initially reported all three siblings' (`WP-ERROR-024`, `025`, `026`) own Section 16 citations of this entry as newly stale, the same expected class of defect this entry's own creation causes, now observed a third time. Corrected in all three (converted to real links, this entry's current `Draft` status noted). Re-run after correction: clean.
- Independent verification of technical claims before inclusion, performed against current WordPress core behavior and documentation: `wp_create_nonce()`/`wp_verify_nonce()`'s hash inputs (action, user ID, session token, tick); the two-tick (~12–24 hour) rolling validity window and the `nonce_life` filter; `wp_verify_nonce()`'s actual return values (`1`, `2`, `false` — never literal `true`) and the resulting strict-equality misuse gotcha; `check_admin_referer()`'s additional `Referer`-header check and `wp_nonce_ays()`; `check_ajax_referer()`'s `admin-ajax.php` usage; the explicit, deliberate claim that a WordPress nonce is not single-use in the classical CSRF-token sense.

---

# 7. Findings

| Finding ID | Severity | Observation | Correction |
|---|---|---|---|
| F-1 | Minor | One bare `must` instance found in descriptive prose (Section 10), inconsistent with this catalog's exclusive `shall`/required-phrasing convention. | Corrected ("are required to"). Re-swept: zero remaining. |
| F-2 | Minor | The three sibling entries' (`WP-ERROR-024`, `025`, `026`) own Section 16 citations of this entry became stale the moment this entry's file was created, first surfaced by `scripts/validate-repo.sh`. | Corrected in all three: citations converted to real links, this entry's current `Draft` status explicitly noted. |
| — | Conforming | Failure boundary matches `SF-TAXONOMY-003` Section 3 exactly: request-origin/freshness verification, presuming authentication and authorization are already valid, non-REST. | None. |
| — | Conforming | The project owner's own "a nonce is not authentication, authorization, or replay prevention" statement independently re-verified as the lead sentence of Section 3 (Summary), bolded and placed first, exactly as directed — not buried later in the document. | None. |
| — | Conforming | All nine causes the project owner specifically requested be kept separate (missing field/header; wrong action string; wrong field name; expired tick; wrong user/auth-state; cached stale nonce; cross-action reuse; misinterpreted return value; premature verification) independently confirmed present in Section 10 as nine distinct, individually-worded bullets. | None. |
| — | Conforming | The generation-versus-verification diagnostic pairing the project owner specifically requested is independently re-verified as Section 11's own explicit structure: step 3 is broken into five sub-checks (action string, field name, user context, timing, transport), each framed as a two-sided comparison rather than a one-sided inspection. | None. |
| — | Conforming | The prohibition on "disable nonce verification" is independently re-verified as an explicit, prominent statement in Section 12, structurally identical in placement and emphasis to `WP-ERROR-026`'s own Administrator-elevation prohibition. | None. |
| — | Conforming | Severity classification (range-based Critical) mirrors and explicitly cites the precedent established for `WP-ERROR-021`/`024`/`025`/`026`. | None. |
| — | Conforming | Structure: all 17 `SF-TEMPLATE-004` sections present, in order, sequentially numbered, none empty. | None. |
| — | Conforming | Technical grounding (nonce hash inputs, tick-based validity window, actual `wp_verify_nonce()` return values, the single-use misconception explicitly addressed) independently verified against current WordPress core behavior rather than asserted from unverified recall. | None. |

No Major or Critical findings.

---

# 8. Recommendations

- None beyond proceeding to independent review. Once this entry reaches Production Ready, `SF-TAXONOMY-003`'s own Section 3 will have all four planned entries at `Existing, Production Ready`, completing the category's initial baseline and making it eligible for a category consistency review and baseline certification, per **SF-SPEC-013** Section 5.3/5.4, matching the pattern already exercised for Database, Filesystem, REST API, and PHP Runtime.

---

# 9. Outcome

**Approved (for purposes of proceeding to independent review only).**

**Basis:** Two Minor findings (F-1, bare-`must` language; F-2, stale sibling citations this entry's own creation caused) were identified and corrected. The entry's failure boundary, the project owner's own explicit nonce-nature statement, nine-cause separation, generation/verification diagnostic pairing, and disable-verification prohibition, technical grounding, structure, and cross-references all conform to `SF-TAXONOMY-003`'s declaration and the project owner's explicit direction. This outcome does not authorize Production Ready.

`WP-ERROR-027` remains `Draft`.

---

# 10. Revision History

| Version | Date | Summary of Changes | Approval Status |
|---|---|---|---|
| 1.0 | 2026-07-14 | Initial author review of WP-ERROR-027. Two Minor findings (F-1: bare-must language; F-2: three sibling entries' own citations of this entry, made stale by this entry's own creation) identified and corrected. Confirmed WP-ERROR-024/025/026/022 exist, are Production Ready, and are correctly linked. | Approved (Class A; does not authorize Production Ready) |
