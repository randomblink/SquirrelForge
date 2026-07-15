# SF-REVIEW-107 — WP-ERROR-036 Independent Review

# 1. Review Information

**Review ID:** SF-REVIEW-107

**Review Date:** 2026-07-14

**Reviewer:** Class B — Independent Review, per **SF-SPEC-012** Section 6.2. Conducted in a fresh review pass, beginning from `SF-TAXONOMY-007` and the artifact itself. Preliminary findings and a preliminary outcome were recorded before `SF-REVIEW-106` was re-opened for comparison, per **SF-SPEC-012** Section 8 (Independence).

**Status:** Complete

---

# 2. Artifact Reviewed

`WP-ERROR-036` — WordPress Upload Size Limit Exceeded, Version 1.0, at `docs/knowledge/wp-errors/WP-ERROR-036-UPLOAD-SIZE-LIMIT-EXCEEDED.md`. Reviewed in its post-author-review state (as it stood after `SF-REVIEW-106`, which corrected one Minor structural finding).

---

# 3. Governing Specifications

- Same as `SF-REVIEW-106`.

---

# 4. Review Scope

This review independently determines whether `WP-ERROR-036` satisfies `SF-TAXONOMY-007`'s Version 1.0 boundary, with particular attention to two things a fresh reading is best positioned to catch: (1) whether Section 4/6's own description of PHP's `post_max_size` behavior is precisely accurate — specifically, whether "PHP itself discards the request before WordPress's code runs at all" correctly describes what actually happens, re-derived independently rather than accepted at face value; and (2) whether `WP-ERROR-020`'s own pre-existing exclusion bullet for this condition now warrants a cross-reference to this entry, applying the same test `SF-REVIEW-091`/`098`/`100` have each applied to a structurally identical situation.

---

# 5. Independence Requirements Applied

Per **SF-SPEC-012** Section 8: began from `SF-TAXONOMY-007` and the artifact itself; independently re-derived PHP's own actual request-lifecycle behavior when `post_max_size` is exceeded, rather than accepting the entry's own phrasing; independently re-read `WP-ERROR-020`'s complete Section 6/7 text to test the cross-reference question against the established precedent; recorded preliminary findings before opening `SF-REVIEW-106`; preserves it unmodified.

---

# 6. Preliminary Independent Findings (recorded before reading SF-REVIEW-106)

Structural checks (bare-`must`, drafting-language, section count, section numbering, link resolution) independently re-run: clean.

This review independently re-derived PHP's own actual behavior when `post_max_size` is exceeded, to test Section 4's claim that "PHP itself discards the request before WordPress's code runs at all." This overstates what actually happens: PHP does *not* skip executing the script. The script — and therefore WordPress's own bootstrap and request-handling code — runs normally; PHP simply does not populate `$_POST`/`$_FILES` with the request's own data, and separately emits a warning to its own error log (commonly worded to the effect of "POST Content-Length of X bytes exceeds the limit of Y bytes") that the running script itself has no built-in way to detect via its own superglobals or a thrown exception. The practical consequence the entry describes (empty superglobals, no PHP-level error code, indistinguishable from "no file selected" to the running script) is accurate; the specific claim that WordPress's own code "runs" or "runs at all" is what needs correcting, not the practical diagnostic conclusion.

This review also independently identified that the specific PHP error-log warning above is a genuine, checkable diagnostic signal this entry does not currently mention anywhere in Section 8 (Components) or Section 11 (Diagnosis) — despite being exactly the kind of evidence that would distinguish cause 1 (`post_max_size`, which produces this specific PHP-level log entry) from the excluded web-server/gateway-level condition (which would never produce a PHP-level log entry at all, since PHP is never invoked in that case). Its absence is a real gap in an otherwise carefully diagnostic entry.

This review also independently re-read `WP-ERROR-020`'s complete Section 6/7 to test whether its own pre-existing exclusion bullet ("PHP/WordPress upload-size limits: see the internal distinction above. Excluded entirely from this entry's scope, per `SF-TAXONOMY-001` Section 4.") now warrants a cross-reference to this entry, the same class of gap `SF-REVIEW-091`/`098`/`100` each found and corrected in a sibling entry during this project's own recent history. The bullet describes exactly this entry's own condition without naming it.

| Finding ID | Severity | Observation |
|---|---|---|
| IF-1 | Minor | Section 4's claim that "PHP itself discards the request before WordPress's code runs at all" overstates the mechanism — WordPress's own code does execute normally; only the request's own data is missing from the superglobals. |
| IF-2 | Minor | Section 8/11 do not mention PHP's own error-log warning for a `post_max_size` violation, a genuine, checkable signal distinguishing cause 1 from the excluded web-server-level condition. |
| IF-3 | Minor | `WP-ERROR-020`'s own pre-existing exclusion bullet describes this entry's own condition without citing it by ID. |

**Preliminary Outcome (before reading SF-REVIEW-106): Approved with Minor Revisions.** Three Minor findings — two technical-precision/completeness gaps within this entry, one cross-document completeness gap in a sibling entry.

---

# 7. Comparison with SF-REVIEW-106

`SF-REVIEW-106` was read only after Section 6 above was finalized.

**Classification of SF-REVIEW-106:** Correctly self-identified as Class A. Retained as valid author-review history.

**Findings independently reproduced:** none of `SF-REVIEW-106`'s Conforming dispositions are disputed; all independently re-confirmed, including its own account of the `post_max_size`-versus-`upload_max_filesize` asymmetry, which this review re-derived independently and confirms is accurate in its practical conclusion even though the specific "discards the request... runs at all" phrasing needed correcting (IF-1).

**New findings absent from SF-REVIEW-106:** all three findings are new. `SF-REVIEW-106`'s own Section 6 confirmed the asymmetry's *practical* accuracy but did not test the specific mechanism-level phrasing describing *why* it holds. It also did not check `WP-ERROR-020` for a needed cross-reference.

**Effect on this review's outcome:** IF-1/IF-2 require corrections within `WP-ERROR-036` itself. IF-3 requires a correction to `WP-ERROR-020`. None requires any revision to `SF-TAXONOMY-007`.

---

# 8. Final Findings and Correction

| Finding ID | Severity | Requirement or Criterion | Observation | Required Action | Resolution Status |
|---|---|---|---|---|---|
| IF-1 | Minor | Technical accuracy (Principle: Evidence Over Assertion) | "PHP itself discards the request before WordPress's code runs at all" overstates the mechanism. | Reword to state that WordPress's own code executes normally, but with `$_POST`/`$_FILES` left empty, rather than implying execution itself is skipped. | Resolved |
| IF-2 | Minor | Completeness (diagnostic evidence) | No mention of PHP's own error-log warning for a `post_max_size` violation, a genuine signal distinguishing cause 1 from the excluded web-server-level condition. | Add this specific log signature to Section 8 and reference it as a diagnostic step in Section 11. | Resolved |
| IF-3 | Minor | Cross-document completeness (established `SF-REVIEW-091`/`098`/`100` pattern) | `WP-ERROR-020`'s own pre-existing exclusion bullet described this entry's own condition without citing it. | Update `WP-ERROR-020`'s exclusion bullet to cite this entry by real link. | Resolved |

**Corrections applied:**
- `WP-ERROR-036` Section 4 revised: "...PHP's own `post_max_size` directive, which governs the entire HTTP request body; when exceeded, WordPress's own code still executes normally, but PHP leaves `$_POST` and `$_FILES` both empty rather than populating them with the request's own data..."
- `WP-ERROR-036` Section 6 cause 1 revised with the same correction, and Section 8 gained a new bullet: "PHP's own error-log warning for an exceeded `post_max_size` (commonly worded to the effect of 'POST Content-Length of *N* bytes exceeds the limit of *N* bytes'), which — unlike the excluded web-server/gateway-level condition, which never invokes PHP at all — provides a genuine, checkable signal that PHP itself, specifically, is where the rejection occurred." Section 11 step 3 updated to reference checking for this specific log entry.
- `WP-ERROR-020`'s own exclusion bullet updated: "**PHP/WordPress upload-size limits**: see the internal distinction above. Excluded entirely from this entry's scope, per `SF-TAXONOMY-001` Section 4 — see [WP-ERROR-036](WP-ERROR-036-UPLOAD-SIZE-LIMIT-EXCEEDED.md)."

No Major or Critical findings. All other areas remain Conforming as recorded in Section 6.

---

# 9. Outcome

**Approved with Minor Revisions.**

**Basis:** `WP-ERROR-036`'s own boundary, three-cause separation, and hand-off discipline are all sound. Three Minor findings — a mechanism-level phrasing overstatement, a missing diagnostic signal, and a cross-document completeness gap in `WP-ERROR-020` — were all corrected within this same review and did not require any revision to `SF-TAXONOMY-007`.

---

# 10. Gate Decision

Per **SF-SPEC-001** Section 19, **SF-SPEC-005** Section 5.6, and **SF-SPEC-012**: this review satisfies the required review sequence for `WP-ERROR-036`. Its Status may accordingly be changed from `Draft` to **`Production Ready`** — the thirty-second knowledge entry in this repository and the first in the Media category.

---

# 11. Remaining Risks

- This review was conducted by the same class of agent (Claude Code) as the authoring pass and `SF-REVIEW-106`.
- No runtime scenario or evidence record under **SF-SPEC-002**/**SF-SPEC-003** exists for this entry.
- `SF-TAXONOMY-007`'s own status table still lists `WP-ERROR-036` as `Planned`; shall be updated to `Existing, Production Ready` in the same body of work that promotes this entry, per **SF-SPEC-013** Section 5.7.
- Two planned Media entries (`WP-ERROR-037`, `038`) remain unauthored; `SF-TAXONOMY-007` Section 4's sequential-pipeline ownership model remains only partially tested until both are drafted.
- IF-2's added PHP error-log wording ("POST Content-Length of *N* bytes exceeds the limit of *N* bytes") is cited as a general, commonly-observed pattern, not verified against every PHP version/SAPI's own exact wording, consistent with this catalog's general practice of hedging log-text claims it cannot fully verify.

---

# 12. Revision History

| Version | Date | Summary of Changes | Approval Status |
|---|---|---|---|
| 1.0 | 2026-07-14 | Initial independent review of WP-ERROR-036. Three Minor findings: IF-1 (an overstated description of PHP's own request-handling behavior under post_max_size, corrected), IF-2 (a missing, genuine diagnostic signal — PHP's own error-log warning — added), and IF-3 (WP-ERROR-020's own pre-existing exclusion bullet updated to cite this entry). Approved with Minor Revisions; Production Ready gate satisfied — the thirty-second entry in this repository and the first in the Media category. Confirmed SF-TAXONOMY-007 required no revision. | Approved with Minor Revisions — Production Ready gate satisfied |
