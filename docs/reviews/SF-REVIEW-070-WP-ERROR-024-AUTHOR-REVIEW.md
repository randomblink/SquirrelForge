# SF-REVIEW-070 — WP-ERROR-024 Author Review

# 1. Review Information

**Review ID:** SF-REVIEW-070

**Review Date:** 2026-07-14

**Reviewer:** Class A — Author Review, per **SF-SPEC-012** Section 6.1. Performed by the same authoring process that drafted `WP-ERROR-024`, within the same work-order execution.

**Status:** Complete

Per **SF-SPEC-012** Section 6.1, this Class A review may identify and correct defects but shall not, by itself, authorize a **Production Ready** designation. For purposes of this review, "Approved" means the artifact is ready to proceed to independent review, not that any lifecycle promotion is authorized.

---

# 2. Artifact Reviewed

`WP-ERROR-024` — WordPress Login Authentication Failure, Version 1.0, at `docs/knowledge/wp-errors/WP-ERROR-024-WORDPRESS-LOGIN-AUTHENTICATION-FAILURE.md`. Status at time of this review: Draft.

---

# 3. Governing Specifications

- **SF-SPEC-001 — Error Knowledge Specification**
- **SF-SPEC-012 — Engineering Review Independence Specification** (this review's own classification)
- **SF-TEMPLATE-004 — WP-ERROR Knowledge Template**
- **SF-GLOSSARY-001 — Engineering Terminology**
- **SF-TAXONOMY-003 — Authentication Error Taxonomy**, Version 1.0 (post-`SF-REVIEW-069` correction), whose Section 2 boundary and Section 3 entry declaration for `WP-ERROR-024` govern this entry

---

# 4. Review Scope

This review evaluates whether `WP-ERROR-024`, as drafted, satisfies `SF-TAXONOMY-003`'s declared boundary for this entry without narrowing or widening it, correctly implements the four-way mutual-exclusivity model Section 4 of that taxonomy describes, incorporates the project owner's own explicit refinement (identity verification only; excludes cookies, nonces, capabilities, plugin-imposed lockouts; ends at the "not authenticated" decision), and satisfies **SF-SPEC-001**'s authoring standards.

---

# 5. Precondition Verification

Before authoring, the status of every related entry was confirmed: `WP-ERROR-002` and `WP-ERROR-022` are Production Ready in this repository, correctly cited with real links (`grep "Status:"` returns `Production Ready` for both). `WP-ERROR-025`, `026`, and `027` do not exist in this repository (`git log --all --diff-filter=A --name-only -- "*WP-ERROR-025*" "*WP-ERROR-026*" "*WP-ERROR-027*"`, run during this review, returns no result); all three are cited as conceptual references only, per `SF-TAXONOMY-003` Section 3, with no link. `SF-TAXONOMY-003` itself was re-read at its current, `SF-REVIEW-069`-corrected state to confirm this entry is drafted against the reviewed boundary, not an earlier draft.

---

# 6. Evidence Examined

- Full contents of `WP-ERROR-024-WORDPRESS-LOGIN-AUTHENTICATION-FAILURE.md`, read in full.
- `grep -c '^# [0-9]\+\.'` (17, matching `SF-TEMPLATE-004`).
- `grep -Ein 'TODO|TBD|placeholder|future work|planned|should consider|to be determined|intended to be added'` — three matches, all the deliberate, accurate word "planned" describing `WP-ERROR-025`/`026`/`027`'s status per `SF-TAXONOMY-003` Section 3 (Section 16 citations), not unfinished drafting language. Confirmed not a defect.
- `grep -n '\bmust\b' | grep -v "must-use"` — zero matches.
- `git diff --check` (clean).
- Link-target verification: the two real links (`WP-ERROR-022`, `WP-ERROR-002`) independently resolved to existing files; the three conceptual citations independently confirmed to correctly omit link syntax, per the format `WP-ERROR-013`/`014`/`015`'s own precedent established and `scripts/validate-repo.sh` Check A now enforces mechanically.
- `scripts/validate-repo.sh .`, run against the repository with this entry present: initially reported a false-positive mismatch in Check B (`SF-TAXONOMY-003` correctly listing `WP-ERROR-024` as `Planned` while this entry's own Status is `Draft`, not yet `Production Ready`) — independently diagnosed as a bug in the validator script itself, not in this entry or the taxonomy, by cross-checking `SF-TAXONOMY-002`'s own revision history (its Status column was corrected from `Planned` to `Existing, Production Ready` only at `SF-REVIEW-052`/its own v1.3, tied to the entries' actual Production Ready promotion, not their mere existence as Draft files). Corrected directly in `scripts/validate-repo.sh` (Check B now requires `actual_status == "Production Ready"`, not merely a non-empty status, before flagging a `Planned` row as stale). Re-run after the fix: clean.
- Independent verification of technical claims before inclusion, performed against current WordPress core source behavior and documentation: `wp_authenticate()`'s `authenticate` filter chain and its three default core callbacks (`wp_authenticate_username_password`, `wp_authenticate_email_password`, `wp_authenticate_spam_check`); `wp_check_password()` as the credential-comparison function; `wp_signon()` as the wrapper that only proceeds to cookie issuance after `wp_authenticate()` succeeds; `wp_xmlrpc_server::login()`'s use of the same core pipeline; the `wp_login_failed` action and its standard firing point; the three standard `WP_Error` codes (`invalid_username`, `incorrect_password`, `invalid_email`) and their standard message text.

---

# 7. Findings

| Finding ID | Severity | Observation | Correction |
|---|---|---|---|
| — | Conforming | Failure boundary matches `SF-TAXONOMY-003` Section 3 exactly: credential verification via `wp_authenticate()`/`wp_signon()`, across every entry point converging on that same pipeline (`wp-login.php`, XML-RPC, programmatic `wp_signon()`), ending at the authentication decision itself. | None. |
| — | Conforming | The project owner's own explicit refinement independently re-verified as fully incorporated: Section 6/7 exclude cookies, nonces, and capabilities by name and by cross-reference to `WP-ERROR-025`/`026`/`027`; Section 6 explicitly excludes "account lockouts imposed by plugins" using that exact framing; Section 6's own bolded sentence states the "ends the moment WordPress decides this user is not authenticated" boundary verbatim. | None. |
| — | Conforming | The authentication-versus-authorization distinction the project owner specifically flagged as important is stated explicitly in Section 6's `WP-ERROR-026` bullet ("Authentication answers 'who are you?'; authorization answers 'are you allowed to do this?'"), not left implicit. | None. |
| — | Conforming | The nonce-independence point the project owner specifically flagged ("many developers incorrectly think a nonce is authentication") is addressed in Section 6's `WP-ERROR-027` bullet, stating a user can be fully authenticated, hold a valid session, and possess the correct capability, and still fail nonce verification. | None. |
| — | Conforming | Severity classification (range-based Critical, narrow single-user impact up to full administrative-lockout impact) mirrors the precedent established for `WP-ERROR-021` and explicitly cites it. | None. |
| — | Conforming | Structure: all 17 `SF-TEMPLATE-004` sections present, in order, sequentially numbered, none empty. | None. |
| — | Conforming | Related Errors: the two real citations (`WP-ERROR-002`, `WP-ERROR-022`) correctly linked; the three conceptual citations (`WP-ERROR-025`/`026`/`027`) correctly disclosed as planned-but-nonexistent with no link, matching this catalog's established format. | None. |
| F-1 | Minor | Section 16's three conceptual-reference citations, as first drafted, used markdown link syntax (`[Title](file.md)`) pointing at files that do not yet exist — inconsistent with this catalog's established format for genuinely nonexistent, planned entries (plain text, "no link is provided"), and a broken link `scripts/validate-repo.sh` does not currently check for in this direction (it checks for stale conceptual references to *existing* files, not links to *nonexistent* ones). | Corrected to plain-text citations matching `WP-ERROR-013`/`014`/`015`'s own established format, before this review's own evidence-gathering concluded. |
| — | Conforming | Technical grounding (the `authenticate` filter chain and its three default callbacks, `wp_check_password()`, `wp_signon()`'s cookie-issuance sequencing, `wp_xmlrpc_server::login()`, the `wp_login_failed` action, and the three standard `WP_Error` codes) independently verified against current WordPress core behavior rather than asserted from unverified recall. | None. |

No Major or Critical findings beyond F-1, which was corrected within this review.

---

# 8. Recommendations

- None beyond the validator fix already applied directly (Section 6 above), which is process tooling rather than a recommendation requiring separate follow-through.

---

# 9. Outcome

**Approved (for purposes of proceeding to independent review only).**

**Basis:** One Minor finding (F-1, a link-formatting inconsistency) was found and corrected within this review. The entry's failure boundary, the project owner's own explicit refinements, technical grounding, structure, and cross-references conform to `SF-TAXONOMY-003`'s declaration for this entry. This outcome does not authorize Production Ready; per **SF-SPEC-012** Section 6.1, a Class A review cannot do so regardless of outcome.

`WP-ERROR-024` remains `Draft`.

---

# 10. Revision History

| Version | Date | Summary of Changes | Approval Status |
|---|---|---|---|
| 1.0 | 2026-07-14 | Initial author review of WP-ERROR-024. One Minor finding (F-1: conceptual-reference citations used markdown link syntax for nonexistent files) identified and corrected. Also independently diagnosed and fixed a false-positive bug in scripts/validate-repo.sh Check B, unrelated to this entry's own content, surfaced while validating this entry. Confirmed WP-ERROR-025/026/027 do not exist; confirmed WP-ERROR-002/022 exist, are Production Ready, and are correctly linked. | Approved (Class A; does not authorize Production Ready) |
