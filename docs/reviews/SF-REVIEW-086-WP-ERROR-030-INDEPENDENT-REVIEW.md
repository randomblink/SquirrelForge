# SF-REVIEW-086 — WP-ERROR-030 Independent Review

# 1. Review Information

**Review ID:** SF-REVIEW-086

**Review Date:** 2026-07-14

**Reviewer:** Class B — Independent Review, per **SF-SPEC-012** Section 6.2. Conducted in a fresh review pass, beginning from `SF-TAXONOMY-004` and the artifact itself. Preliminary findings and a preliminary outcome were recorded before `SF-REVIEW-085` was re-opened for comparison, per **SF-SPEC-012** Section 8 (Independence).

**Status:** Complete

---

# 2. Artifact Reviewed

`WP-ERROR-030` — WordPress CORS (Cross-Origin) Policy Failure, Version 1.0, at `docs/knowledge/wp-errors/WP-ERROR-030-CORS-CROSS-ORIGIN-POLICY-FAILURE.md`. Reviewed in its post-author-review state (as it stood after `SF-REVIEW-085`, which recorded no corrections).

---

# 3. Governing Specifications

- Same as `SF-REVIEW-085`.

---

# 4. Review Scope

This review independently determines whether `WP-ERROR-030` satisfies `SF-TAXONOMY-004`'s Version 1.3 boundary, with particular attention to two things a fresh reading is best positioned to catch that an author pass can miss: (1) whether the entry's own framing of "WordPress's relationship to CORS enforcement" is stated with full technical precision, not just directionally correct; and (2) whether `WP-ERROR-022`'s own text now stays consistent with this entry's two-directional relationship claim, re-read fresh rather than assumed from the author's own account of it.

---

# 5. Independence Requirements Applied

Per **SF-SPEC-012** Section 8: began from `SF-TAXONOMY-004` and the artifact itself; independently re-read `WP-ERROR-022`'s complete Section 6 and Section 7 text (not only the single exclusion bullet the author review cites) to test the two-directional relationship claim against `WP-ERROR-022`'s own account of its boundary; independently re-derived WordPress core's actual CORS-emission mechanics from the entry's own cited function names rather than accepting the entry's prose description at face value; recorded preliminary findings before opening `SF-REVIEW-085`; preserves it unmodified.

---

# 6. Preliminary Independent Findings (recorded before reading SF-REVIEW-085)

Structural checks (bare-`must`, drafting-language, section count, section numbering, link resolution) independently re-run: clean.

This review independently traced the entry's own cited mechanism (`rest_send_cors_headers()`, gated by `get_http_origin()` and `is_allowed_http_origin()`) against Section 3 (Summary)'s own prose claim that "WordPress itself never rejects, blocks, or even necessarily becomes aware that a request was cross-origin." The traced mechanism contradicts this specific claim: `rest_send_cors_headers()` calls `get_http_origin()` to read the `Origin` request header on every REST response for which one is present, precisely *in order to* decide whether to conditionally reflect it back via `Access-Control-Allow-Origin` — this is origin-*aware* logic by construction, not origin-blind logic that merely happens not to reject anything. "Never... becomes aware" overstates what Section 8 (WordPress Components) of this same entry already correctly documents two sections later: `get_http_origin()` and `is_allowed_http_origin()` are named there as the specific functions determining what a request receives. The two sections are in tension — Section 3 claims WordPress need not be origin-aware; Section 8 documents the origin-aware mechanism that makes Section 3's own boundary work at all. What is accurate, and what Section 3 should say instead, is narrower: WordPress does not *reject* or *block* a cross-origin request based on its origin — it always executes the request's own handler regardless of origin — but it does read the `Origin` header as an input to its own conditional header-emission decision.

This review also independently re-read `WP-ERROR-022`'s complete Section 6 and Section 7 to test the two-directional relationship claim. No contradiction was found: `WP-ERROR-022`'s own Section 7 exclusion bullet (as corrected in this same work order, prior to this review, to cite `WP-ERROR-030` by link) states the CORS exclusion without asserting anything inconsistent with this entry's own two-directional account. `WP-ERROR-022`'s Section 6 does not itself restate the relationship, which is not a defect — the detailed relationship is this entry's own content to carry, and `WP-ERROR-022` is not required to duplicate it, the same asymmetric-ownership pattern already established between other sibling entries in this catalog (for example, `WP-ERROR-021` does not restate everything `WP-ERROR-022` says about the boundary between them).

| Finding ID | Severity | Observation |
|---|---|---|
| IF-1 | Minor | Section 3 (Summary)'s claim that WordPress "never... becomes aware that a request was cross-origin" overstates the boundary; WordPress's own CORS-emission mechanism (Section 8 of this same entry) is origin-aware by construction, reading the `Origin` header to decide what to emit, even though it never rejects or blocks the request itself on that basis. |

**Preliminary Outcome (before reading SF-REVIEW-085): Approved with Minor Revisions.** One Minor finding, an internal precision gap between this entry's own Section 3 and Section 8, not a factual error affecting any other entry.

---

# 7. Comparison with SF-REVIEW-085

`SF-REVIEW-085` was read only after Section 6 above was finalized.

**Classification of SF-REVIEW-085:** Correctly self-identified as Class A. Retained as valid author-review history.

**Findings independently reproduced:** none of `SF-REVIEW-085`'s Conforming dispositions are disputed; all independently re-confirmed, including its own account of the proactive cross-document staleness corrections (`WP-ERROR-028`/`029` Section 16; `WP-ERROR-021`/`022` exclusion bullets), which this review separately re-verified by re-reading all four affected files directly rather than accepting the author review's own account of them.

**New findings absent from SF-REVIEW-085:** IF-1 is new. `SF-REVIEW-085`'s own Section 6 lists Section 3's browser-enforces framing as Conforming but did not independently trace the specific mechanism named in Section 8 against Section 3's own "never becomes aware" language.

**Effect on this review's outcome:** IF-1 requires narrowing Section 3's own claim, applied within this review (Section 8 below).

---

# 8. Final Findings and Correction

| Finding ID | Severity | Requirement or Criterion | Observation | Required Action | Resolution Status |
|---|---|---|---|---|---|
| IF-1 | Minor | Technical accuracy (Glossary §4.1) | Section 3's "never... becomes aware that a request was cross-origin" overstates the boundary against Section 8's own documentation of `get_http_origin()`/`is_allowed_http_origin()` as the mechanism deciding header emission. | Narrow Section 3's claim to what is actually true: WordPress never rejects or blocks a request based on its origin — the request's own handler always executes regardless — but WordPress's own header-emission logic does read the `Origin` header as an input to that decision. | Resolved |

**Correction applied:** `WP-ERROR-030` Section 3's final clause of the second sentence changed from "WordPress itself never rejects, blocks, or even necessarily becomes aware that a request was cross-origin" to "WordPress itself never rejects or blocks a request on the basis of its origin — the request's own handler executes identically regardless — though WordPress's own header-emission logic does read the `Origin` request header as an input to deciding what, if anything, to send back."

No Major or Critical findings. All other areas remain Conforming as recorded in Section 6.

---

# 9. Outcome

**Approved with Minor Revisions.**

**Basis:** `WP-ERROR-030`'s primary boundary, six-item exclusion list, dual-role framing, and two-directional `WP-ERROR-022` relationship are all sound and independently re-verified, including a fresh re-read of `WP-ERROR-022`'s own text rather than reliance on the author review's account. The single finding (IF-1) was an internal precision gap between this entry's own Section 3 and Section 8, corrected and re-validated within this same review.

---

# 10. Gate Decision

Per **SF-SPEC-001** Section 19, **SF-SPEC-005** Section 5.6, and **SF-SPEC-012**: this review satisfies the required review sequence for `WP-ERROR-030`. Its Status may accordingly be changed from `Draft` to **`Production Ready`** — the twenty-sixth knowledge entry in this repository and the third in the Networking category.

---

# 11. Remaining Risks

- This review was conducted by the same class of agent (Claude Code) as the authoring pass and `SF-REVIEW-085`.
- No runtime scenario or evidence record under **SF-SPEC-002**/**SF-SPEC-003** exists for this entry.
- `SF-TAXONOMY-004`'s own status table still lists `WP-ERROR-030` as `Planned`; shall be updated to `Existing, Production Ready` in the same body of work that promotes this entry, per **SF-SPEC-013** Section 5.7.
- With all three planned Networking entries now Production Ready, `SF-TAXONOMY-004` Section 4's two-axis ownership model (sequential pair plus one independent entry) is fully instantiated but has not yet been tested by a dedicated category consistency review, the same review type `SF-REVIEW-078` performed for Authentication before that category's own baseline certification.
- IF-1's corrected Section 3 language remains untested against a reader unfamiliar with `get_http_origin()`/`is_allowed_http_origin()` who has not also read Section 8 — the two sections are now consistent with each other but a reader encountering Section 3 alone still receives an incomplete picture until reaching Section 8.

---

# 12. Revision History

| Version | Date | Summary of Changes | Approval Status |
|---|---|---|---|
| 1.0 | 2026-07-14 | Initial independent review of WP-ERROR-030. One Minor finding (IF-1: Section 3's "never becomes aware that a request was cross-origin" overstated the boundary against Section 8's own documented origin-aware header-emission mechanism) identified and corrected. Approved with Minor Revisions; Production Ready gate satisfied — the twenty-sixth entry in this repository and the third in the Networking category. | Approved with Minor Revisions — Production Ready gate satisfied |
