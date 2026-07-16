# SF-REVIEW-073 — WP-ERROR-025 Independent Review

# 1. Review Information

**Review ID:** SF-REVIEW-073

**Review Date:** 2026-07-14

**Reviewer:** Class B — Independent Review, per **SF-SPEC-012** Section 6.2. Conducted in a fresh review pass, beginning from `SF-TAXONOMY-003` and the artifact itself. Preliminary findings and a preliminary outcome were recorded before `SF-REVIEW-072` was re-opened for comparison, per **SF-SPEC-012** Section 8 (Independence).

**Status:** Complete

---

# 2. Artifact Reviewed

`WP-ERROR-025` — WordPress Authentication Cookie Invalid or Expired, Version 1.0, at `docs/knowledge/wp-errors/WP-ERROR-025-AUTHENTICATION-COOKIE-INVALID-OR-EXPIRED.md`. Reviewed in its post-author-review state (as corrected by `SF-REVIEW-072`'s F-1 and F-2).

---

# 3. Governing Specifications

- Same as `SF-REVIEW-072`.

---

# 4. Review Scope

This review independently determines whether `WP-ERROR-025` satisfies `SF-TAXONOMY-003`'s boundary and the project owner's own explicit requirements, and specifically whether every cross-document claim this entry makes about `WP-ERROR-024`'s own scope is actually grounded in `WP-ERROR-024`'s own text, rather than merely being a plausible-sounding assertion.

---

# 5. Independence Requirements Applied

Per **SF-SPEC-012** Section 8: began from `SF-TAXONOMY-003` and the artifact itself; independently re-ran the bare-`must` and drafting-language sweeps rather than accepting `SF-REVIEW-072`'s own report that they were clean; independently re-read `WP-ERROR-024`'s complete text to verify every claim this entry makes about it, rather than trusting the claim on its face; recorded preliminary findings before opening `SF-REVIEW-072`; preserves it unmodified.

---

# 6. Preliminary Independent Findings (recorded before reading SF-REVIEW-072)

Structural checks (bare-`must`, drafting-language, section count, section numbering) independently re-run: zero bare `must`, three drafting-language keyword matches all individually confirmed non-defects (the two "planned" citations and the one accurate "placeholder" usage) — matching the state `SF-REVIEW-072` reports, confirming its own corrections held.

This review independently re-read `WP-ERROR-024`'s complete text specifically to verify Section 6's claim that a browser with cookies disabled "belongs to `WP-ERROR-024`'s own pipeline." `grep -in "test.cookie\|cookies.*disabled\|disabled.*cookie" WP-ERROR-024...md` returns zero matches — `WP-ERROR-024` says nothing about the `TEST_COOKIE`/`wordpress_test_cookie` mechanism anywhere. The claim is not merely uncited; it is not corroborated by `WP-ERROR-024`'s own actual scope at all.

Beyond the citation gap, this review examined whether the underlying technical claim is even correctly attributed. In WordPress core's actual `wp-login.php` flow, the test-cookie check does not block `wp_signon()` from running: `wp_signon()` is called and, on valid credentials, completes successfully — including `wp_set_auth_cookie()` issuing the server-side session token and instructing the browser to set the cookie — *before* `wp-login.php` separately checks whether `$_COOKIE[TEST_COOKIE]` was received back and displays "Cookies are blocked or not supported by your browser" if not. That is: credential verification (`WP-ERROR-024`'s own condition) succeeds; what fails is the browser's ability to retain *any* cookie at all, which is a cookie-persistence condition far closer to this entry's own domain than to `WP-ERROR-024`'s.

| Finding ID | Severity | Observation |
|---|---|---|
| IF-1 | Minor | Section 6's attribution of the cookies-disabled-entirely scenario to `WP-ERROR-024`'s pipeline is both uncorroborated by `WP-ERROR-024`'s own text and, on independent technical review, likely inaccurate — the credential check itself succeeds in this scenario; only cookie persistence fails, which is this entry's own domain. |

**Preliminary Outcome (before reading SF-REVIEW-072): Approved with Minor Revisions.** One Minor finding, correctable by reattributing the scenario rather than asserting it belongs to a sibling entry that does not itself claim it.

---

# 7. Comparison with SF-REVIEW-072

`SF-REVIEW-072` was read only after Section 6 above was finalized.

**Classification of SF-REVIEW-072:** Correctly self-identified as Class A. Retained as valid author-review history.

**Findings independently reproduced:** `SF-REVIEW-072`'s F-1 (bare-`must` correction) and F-2 (the stale `WP-ERROR-024` citation, corrected to a real link) were both independently re-verified as correctly resolved in the artifact this review read.

**New findings absent from SF-REVIEW-072:** IF-1 is new. `SF-REVIEW-072`'s own Section 6 checked the project owner's cookie/session/PHP-session distinction and the general `WP-ERROR-024` boundary, but did not independently re-read `WP-ERROR-024`'s own text to verify the specific test-cookie attribution claim, and did not independently re-derive the actual WordPress core call order for that scenario.

**Unsupported conclusions in SF-REVIEW-072:** its Conforming disposition for the `WP-ERROR-024` boundary edge case ("explicitly addressing it... attributing it correctly to `WP-ERROR-024`'s own pipeline") asserts the attribution is *correct* without having independently verified it against either `WP-ERROR-024`'s own text or actual WordPress core behavior.

**Effect on this review's outcome:** IF-1 requires correcting this entry's own Section 6 and Section 7 (Scope), applied within this review (Section 8 below).

---

# 8. Final Findings and Correction

| Finding ID | Severity | Requirement or Criterion | Observation | Required Action | Resolution Status |
|---|---|---|---|---|---|
| IF-1 | Minor | Principle: Evidence Over Assertion, applied to a cross-document boundary claim | Section 6 attributed the cookies-entirely-disabled scenario to `WP-ERROR-024`'s pipeline without that claim being grounded in `WP-ERROR-024`'s own text, and the attribution does not match WordPress core's actual behavior: `wp_signon()` completes successfully in this scenario (a `WP-ERROR-024` success, not failure); only cookie persistence fails. | Reword Section 6 (and the parallel Section 7 exclusion bullet) to correctly attribute the scenario to this entry's own domain instead — cookie issuance succeeds server-side but never persists to the browser at all, a boundary case of *this* entry rather than of `WP-ERROR-024`. | Resolved |

**Correction applied:** Section 6's `WP-ERROR-024` distinction bullet reworded: the cookies-entirely-disabled scenario is now described as a case where the credential check (`WP-ERROR-024`) succeeds but the resulting cookie can never be retained by the browser at all — explicitly this entry's own condition, at its most extreme (zero persistence rather than expired or invalid persistence), not `WP-ERROR-024`'s. Section 7's corresponding exclusion bullet removed, since the scenario is no longer excluded — it is covered. Re-validated: `WP-ERROR-024`'s own text re-confirmed to make no competing claim about this scenario, so no cross-document conflict remains.

No Major or Critical findings. All other areas remain Conforming as recorded in Section 6.

---

# 9. Outcome

**Approved with Minor Revisions.**

**Basis:** `WP-ERROR-025`'s failure boundary, technical grounding, and the project owner's own explicit cookie/session/PHP-session distinction are sound. The single finding (IF-1) was a cross-document attribution error — asserting a boundary claim about a sibling entry that neither that entry's own text nor actual WordPress core behavior supports — corrected and re-validated within this same review.

---

# 10. Gate Decision

Per **SF-SPEC-001** Section 19, **SF-SPEC-005** Section 5.6, and **SF-SPEC-012**: this review satisfies the required review sequence for `WP-ERROR-025`. Its Status may accordingly be changed from `Draft` to **`Production Ready`** — the twenty-first knowledge entry in this repository and the second in the Authentication category.

---

# 11. Remaining Risks

- This review was conducted by the same class of agent (Claude Code) as the authoring pass and `SF-REVIEW-072`.
- IF-1's underlying technical claim about WordPress core's exact `wp-login.php` call order (`wp_signon()` completing before the test-cookie check) was independently reasoned from documented WordPress behavior rather than verified against a live installation or the exact current core source line-by-line; if a future review finds this order differs in some WordPress version, this entry's own corrected text should be re-examined.
- No runtime scenario or evidence record under **SF-SPEC-002**/**SF-SPEC-003** exists for this entry.
- `SF-TAXONOMY-003`'s own status table still lists `WP-ERROR-025` as `Planned`; per established convention, this shall be updated to `Existing, Production Ready` in the same body of work that promotes this entry, per **SF-SPEC-013** Section 5.7.
- Two of the four planned Authentication entries (`WP-ERROR-026`, `027`) remain unauthored.

---

# 12. Revision History

| Version | Date | Summary of Changes | Approval Status |
|---|---|---|---|
| 1.0 | 2026-07-14 | Initial independent review of WP-ERROR-025. One Minor finding (IF-1: Section 6's attribution of the cookies-disabled scenario to WP-ERROR-024 was uncorroborated and likely technically inaccurate — the credential check succeeds in that scenario, only cookie persistence fails) identified and corrected; scenario reattributed to this entry's own domain. Approved with Minor Revisions; Production Ready gate satisfied — the twenty-first entry in this repository and the second in the Authentication category. | Approved with Minor Revisions — Production Ready gate satisfied |
