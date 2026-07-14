# SF-REVIEW-072 — WP-ERROR-025 Author Review

# 1. Review Information

**Review ID:** SF-REVIEW-072

**Review Date:** 2026-07-14

**Reviewer:** Class A — Author Review, per **SF-SPEC-012** Section 6.1. Performed by the same authoring process that drafted `WP-ERROR-025`, within the same work-order execution.

**Status:** Complete

Per **SF-SPEC-012** Section 6.1, this Class A review may identify and correct defects but shall not, by itself, authorize a **Production Ready** designation.

---

# 2. Artifact Reviewed

`WP-ERROR-025` — WordPress Authentication Cookie Invalid or Expired, Version 1.0, at `docs/knowledge/wp-errors/WP-ERROR-025-AUTHENTICATION-COOKIE-INVALID-OR-EXPIRED.md`. Status at time of this review: Draft.

---

# 3. Governing Specifications

- **SF-SPEC-001**, **SF-SPEC-012**, **SF-TEMPLATE-004**, **SF-GLOSSARY-001** — same as `SF-REVIEW-070`.
- `SF-TAXONOMY-003` — Authentication Error Taxonomy, Version 1.1 (post-`WP-ERROR-024` promotion), whose Section 2 boundary and Section 3 entry declaration for `WP-ERROR-025` govern this entry.

---

# 4. Review Scope

This review evaluates whether `WP-ERROR-025`, as drafted, satisfies `SF-TAXONOMY-003`'s declared boundary, correctly incorporates the project owner's own explicit requirement (distinguish WordPress authentication cookies from PHP sessions and from arbitrary plugin/theme cookies, keeping scope limited to the former), and satisfies **SF-SPEC-001**'s authoring standards.

---

# 5. Precondition Verification

`WP-ERROR-024` is Production Ready in this repository (`grep "Status:"` returns `Production Ready`), correctly cited with a real link. `WP-ERROR-026` and `027` do not exist (`git log --all --diff-filter=A --name-only -- "*WP-ERROR-026*" "*WP-ERROR-027*"` returns no result); both cited as conceptual references with no link. `SF-TAXONOMY-003` re-read at its current, `WP-ERROR-024`-promotion-corrected state (Version 1.1) to confirm this entry is drafted against the current taxonomy, not an earlier draft.

---

# 6. Evidence Examined

- Full contents of `WP-ERROR-025-AUTHENTICATION-COOKIE-INVALID-OR-EXPIRED.md`, read in full.
- `grep -c '^# [0-9]\+\.'` (17, matching `SF-TEMPLATE-004`).
- `grep -n '\bmust\b' | grep -v "must-use"` — two matches found in descriptive prose (Section 5, Section 9), both corrected to `shall`/`is required to` during this review, before this review's own findings table was finalized. Re-swept after correction: zero remaining.
- `grep -Ein 'TODO|TBD|placeholder|future work|planned|should consider|to be determined|intended to be added'` — three matches: two the deliberate word "planned" describing `WP-ERROR-026`/`027`'s status (Section 16), and one legitimate use of "placeholder" describing `wp-config.php` template values (Section 10) — confirmed accurate description, not drafting language.
- `git diff --check` (clean).
- Link-target verification: `WP-ERROR-024` and `WP-ERROR-022` links independently resolved to existing files.
- `scripts/validate-repo.sh .`, run against the repository with this entry present: initially reported `WP-ERROR-024`'s own Section 16 citation of `WP-ERROR-025` as newly stale — a direct, expected consequence of this entry's own creation, exactly the defect class the validator exists to catch, per `FRAMEWORK-OBSERVATIONS.md`'s 2026-07-14 entry. Corrected in `WP-ERROR-024` (converted to a real link, noting `WP-ERROR-025`'s current `Draft` status explicitly, since a citation link reflects file existence, not review maturity, consistent with how `scripts/validate-repo.sh` Check A itself only tests existence). Re-run after correction: clean.
- Independent verification of technical claims before inclusion, performed against current WordPress core source behavior and documentation: `wp_set_auth_cookie()`/`wp_validate_auth_cookie()` and the three distinct cookie constants (`AUTH_COOKIE`, `SECURE_AUTH_COOKIE`, `LOGGED_IN_COOKIE`) and their distinct scopes; the six secret-key/salt constants and their role in HMAC signature computation; `COOKIEHASH`'s derivation from the site URL; `COOKIE_DOMAIN`/`COOKIEPATH`; `WP_Session_Tokens` as the server-side session-token store enabling per-session revocation independent of cookie validity; `auth_redirect()`'s reauth-redirect behavior; the default two-day/fourteen-day ("Remember Me") session duration; the confirmation that WordPress core does not use PHP native sessions for logged-in state.

---

# 7. Findings

| Finding ID | Severity | Observation | Correction |
|---|---|---|---|
| F-1 | Minor | Two bare `must` instances found in descriptive prose (Section 5, Section 9), inconsistent with this catalog's exclusive `shall` normative-language convention even outside strictly normative statements. | Both corrected (`shall`, `is required to`) before this review's findings table was finalized. Re-swept: zero remaining. |
| F-2 | Minor | `WP-ERROR-024`'s own Section 16 citation of this entry became stale the moment this entry's file was created, first surfaced by `scripts/validate-repo.sh`. | Corrected in `WP-ERROR-024`: citation converted to a real link, with `WP-ERROR-025`'s current `Draft` status explicitly noted (a link reflects existence, not maturity). | 
| — | Conforming | Failure boundary matches `SF-TAXONOMY-003` Section 3 exactly: session persistence, presuming prior successful authentication, ending at cookie/session validation specifically. | None. |
| — | Conforming | The project owner's own explicit requirement — distinguishing WordPress authentication cookies from PHP sessions and from arbitrary plugin/theme cookies — is independently re-verified as its own dedicated, prominent callout within Section 6 ("What this entry means by 'authentication cookie' — and what it explicitly does not mean"), not merely mentioned in passing. | None. |
| — | Conforming | The `WP-ERROR-024` boundary edge case identified during this entry's drafting (a browser with cookies entirely disabled failing WordPress's own test-cookie check at login, before `wp_signon()` ever completes) is explicitly addressed in Section 6, attributing it correctly to `WP-ERROR-024`'s own pipeline rather than silently leaving a gap between the two entries. | None. |
| — | Conforming | Severity classification (range-based Critical, narrow single-session impact up to a site-wide re-authentication-loop scenario) mirrors and explicitly cites the precedent established for `WP-ERROR-021`/`024`. | None. |
| — | Conforming | Structure: all 17 `SF-TEMPLATE-004` sections present, in order, sequentially numbered, none empty. | None. |
| — | Conforming | Technical grounding independently verified against current WordPress core behavior rather than asserted from unverified recall. | None. |

No Major or Critical findings.

---

# 8. Recommendations

- None beyond proceeding to independent review.

---

# 9. Outcome

**Approved (for purposes of proceeding to independent review only).**

**Basis:** Two Minor findings (F-1, bare-`must` language; F-2, a stale sibling citation this entry's own creation caused) were identified and corrected within this review. The entry's failure boundary, the project owner's own explicit cookie/session/PHP-session distinction, technical grounding, structure, and cross-references conform to `SF-TAXONOMY-003`'s declaration. This outcome does not authorize Production Ready.

`WP-ERROR-025` remains `Draft`.

---

# 10. Revision History

| Version | Date | Summary of Changes | Approval Status |
|---|---|---|---|
| 1.0 | 2026-07-14 | Initial author review of WP-ERROR-025. Two Minor findings (F-1: bare-must language; F-2: WP-ERROR-024's own citation of this entry, made stale by this entry's own creation) identified and corrected. Confirmed WP-ERROR-024 exists, is Production Ready, and is correctly linked; confirmed WP-ERROR-026/027 do not exist. | Approved (Class A; does not authorize Production Ready) |
