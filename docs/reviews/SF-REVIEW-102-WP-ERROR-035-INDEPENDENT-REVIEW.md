# SF-REVIEW-102 — WP-ERROR-035 Independent Review

# 1. Review Information

**Review ID:** SF-REVIEW-102

**Review Date:** 2026-07-14

**Reviewer:** Class B — Independent Review, per **SF-SPEC-012** Section 6.2. Conducted in a fresh review pass, beginning from `SF-TAXONOMY-006` and the artifact itself. Preliminary findings and a preliminary outcome were recorded before `SF-REVIEW-101` was re-opened for comparison, per **SF-SPEC-012** Section 8 (Independence).

**Status:** Complete

This review completes the Performance category's own planned-entry set. Per the project owner's own explicit framing, a clean result here — no taxonomy revision required for the third consecutive entry — would be the strongest evidence in this catalog that a proactive cross-category ownership sweep, performed during a taxonomy's own drafting, reliably prevents the class of defect `WP-ERROR-032`'s own production cycle exposed in `SF-TAXONOMY-005`.

---

# 2. Artifact Reviewed

`WP-ERROR-035` — WordPress OPcache Stale Bytecode, Version 1.0, at `docs/knowledge/wp-errors/WP-ERROR-035-OPCACHE-STALE-BYTECODE.md`. Reviewed in its post-author-review state (as it stood after `SF-REVIEW-101`, which recorded no corrections to this entry's own text).

---

# 3. Governing Specifications

- Same as `SF-REVIEW-101`.

---

# 4. Review Scope

This review independently determines whether `WP-ERROR-035` satisfies `SF-TAXONOMY-006`'s Version 1.2 boundary and the project owner's own four review criteria, with particular attention to two things a fresh reading is best positioned to catch: (1) whether Section 8's own description of `opcache_reset()` as executable "via a small PHP script executed as part of deployment" is complete enough to avoid a real, practically important operational trap — invoking it from the wrong PHP context; and (2) whether `WP-ERROR-032`'s own text, which already excludes "a specific plugin's own new-version code defect" as a reason an update might appear ineffective, should now also name this entry's own condition as a second, distinct reason for that same symptom.

---

# 5. Independence Requirements Applied

Per **SF-SPEC-012** Section 8: began from `SF-TAXONOMY-006` and the artifact itself; independently re-derived, from first principles, how OPcache's own shared-memory scoping actually interacts with PHP-FPM versus PHP CLI to test whether this entry's own Section 8/12 guidance is complete; independently re-read `WP-ERROR-032`'s complete Section 6 (not only its own account of the boundary) to test whether its existing "plugin's own new-version code defect" exclusion should be extended; recorded preliminary findings before opening `SF-REVIEW-101`; preserves it unmodified.

---

# 6. Preliminary Independent Findings (recorded before reading SF-REVIEW-101)

Structural checks (bare-`must`, drafting-language, section count, section numbering, link resolution) independently re-run: clean.

This review independently re-derived OPcache's own actual scoping behavior between PHP-FPM and PHP CLI to test Section 8's own claim that `opcache_reset()` is available "via a small PHP script executed as part of deployment... in the same PHP context as the affected requests." This claim is directionally correct but the entry does not state the specific, practically important trap it implies: PHP CLI and a web-facing PHP-FPM pool commonly maintain *entirely separate* OPcache instances (CLI OPcache is frequently disabled by default via `opcache.enable_cli`, and even where enabled, a CLI process does not share the FPM pool's own shared-memory segment). A deployment script that invokes `opcache_reset()` via WP-CLI or another CLI-based tool — a very natural, common choice for a deployment automation step — will typically *not* reset the OPcache actually serving web requests at all, silently leaving this entry's own condition unresolved despite the deployment process appearing to have taken a corrective action. This is not a hypothetical: it is one of the most common real-world mistakes made when attempting to script an OPcache reset.

This review also independently re-read `WP-ERROR-032`'s complete Section 6 to test whether its existing exclusion for "a specific plugin's own new-version code defect that is not itself a mechanism failure" should be extended. That exclusion currently names only one explanation for "the update mechanism succeeded but something still seems wrong" — a genuine business-logic bug in the new code. This entry documents a second, entirely distinct explanation for the identical symptom class (the update mechanism succeeded, the new code is correct, but PHP is not yet executing it), which `WP-ERROR-032`'s own text does not currently name or distinguish from the first.

| Finding ID | Severity | Observation |
|---|---|---|
| IF-1 | Minor | Section 8's description of invoking `opcache_reset()` "as part of deployment" does not warn that a CLI-based deployment script (including WP-CLI) commonly resets a different, separate OPcache instance than the one serving web requests, a common and practically important operational trap. |
| IF-2 | Minor | `WP-ERROR-032`'s own "plugin's own new-version code defect" exclusion names only one of two distinct explanations for "the update mechanism succeeded but something still seems wrong" — it does not distinguish a genuine code defect from this entry's own condition (correct new code, not yet executing). |

**Preliminary Outcome (before reading SF-REVIEW-101): Approved with Minor Revisions.** Two Minor findings — one a completeness gap within this entry's own operational guidance, one a cross-document completeness gap in a sibling entry.

---

# 7. Comparison with SF-REVIEW-101

`SF-REVIEW-101` was read only after Section 6 above was finalized.

**Classification of SF-REVIEW-101:** Correctly self-identified as Class A. Retained as valid author-review history.

**Findings independently reproduced:** none of `SF-REVIEW-101`'s Conforming dispositions are disputed; all independently re-confirmed, including its own account of the four review criteria and its own technical assessment of the Severity reasoning.

**New findings absent from SF-REVIEW-101:** both IF-1 and IF-2 are new. `SF-REVIEW-101`'s own Section 6 confirmed Section 8/12's own scoping language was internally consistent but did not test it against the specific CLI-versus-FPM OPcache scoping trap. It also did not extend the `WP-ERROR-032` cross-reference check beyond confirming the boundary was *accurately stated*, without testing whether `WP-ERROR-032`'s own text should be *extended* to name this entry.

**Effect on this review's outcome:** IF-1 requires a clarifying addition within `WP-ERROR-035` itself. IF-2 requires extending `WP-ERROR-032` Section 6's existing exclusion bullet. Neither requires any revision to `SF-TAXONOMY-006`.

---

# 8. Final Findings and Correction

| Finding ID | Severity | Requirement or Criterion | Observation | Required Action | Resolution Status |
|---|---|---|---|---|---|
| IF-1 | Minor | Completeness (Principle: Evidence Over Assertion) | Section 8 did not warn that a CLI-invoked `opcache_reset()` commonly does not affect the OPcache instance serving web requests. | Add an explicit warning to Section 8 and Section 12 that a deployment script shall invoke `opcache_reset()` (or trigger a reload) through the same PHP-FPM pool serving web requests, not via CLI, since the two commonly maintain separate OPcache instances entirely. | Resolved |
| IF-2 | Minor | Cross-document completeness (established `SF-REVIEW-091`/`093`/`098` pattern) | `WP-ERROR-032`'s own "plugin's own new-version code defect" exclusion did not distinguish a genuine code defect from this entry's own condition, both of which present as "the update succeeded but something still seems wrong." | Extend `WP-ERROR-032` Section 6's existing exclusion bullet to name this entry as a second, distinct explanation for the same symptom class. | Resolved |

**Corrections applied:**
- `WP-ERROR-035` Section 8's `opcache_reset()`/`opcache_invalidate()`/`opcache_compile_file()` bullet extended: "...since these functions operate on the specific PHP process's own OPcache and are not directly accessible from a shell command alone — critically, a CLI-invoked script (including WP-CLI) commonly resets a *different*, separate OPcache instance than the one serving web requests, since PHP CLI and a web-facing PHP-FPM pool frequently do not share OPcache's own shared memory at all; a deployment script intended to clear the web-facing cache shall trigger it through that same web-facing context, not via CLI."
- `WP-ERROR-035` Section 12 gained a clarifying sentence on this same point, cross-referencing Section 8.
- `WP-ERROR-032` Section 6's "plugin's own new-version code defect" bullet extended to read: "...That is the plugin's own defect, outside this entry's own scope entirely, regardless of how disruptive its symptoms are. A second, easily-conflated explanation for the identical symptom — the update mechanism succeeded and the new code is itself correct, but PHP is not yet executing it — is `WP-ERROR-035`'s own condition, not a code defect at all; diagnosis shall distinguish the two before concluding either applies."

No Major or Critical findings. All other areas remain Conforming as recorded in Section 6.

---

# 9. Outcome

**Approved with Minor Revisions.**

**Basis:** `WP-ERROR-035`'s own boundary, three-way cause separation, first-principles severity reasoning, and its explicit stale-code-versus-stale-data disambiguation are all sound and independently re-verified. The two findings (IF-1, a practically important operational-completeness gap within this entry; IF-2, a cross-document completeness gap in `WP-ERROR-032`) were both corrected within this same review and did not require any revision to `SF-TAXONOMY-006`.

---

# 10. Gate Decision

Per **SF-SPEC-001** Section 19, **SF-SPEC-005** Section 5.6, and **SF-SPEC-012**: this review satisfies the required review sequence for `WP-ERROR-035`. Its Status may accordingly be changed from `Draft` to **`Production Ready`** — the thirty-first knowledge entry in this repository and the third and final planned entry in the Performance category.

**Taxonomy completeness result:** `SF-TAXONOMY-006` required no revision to support this entry's authoring, review, or promotion — the third consecutive entry in this category to reach Production Ready without a taxonomy change. This is the complete evidentiary result the project owner asked this category to produce: every planned Performance entry has now passed through authoring, author review, and independent review under a single, unrevised taxonomy version (since its own pre-authoring Version 1.0), with every finding either entry-level or sibling-cross-reference in nature, never an ownership conflict the taxonomy itself failed to anticipate.

---

# 11. Remaining Risks

- This review was conducted by the same class of agent (Claude Code) as the authoring pass and `SF-REVIEW-101`.
- No runtime scenario or evidence record under **SF-SPEC-002**/**SF-SPEC-003** exists for this entry.
- `SF-TAXONOMY-006`'s own status table still lists `WP-ERROR-035` as `Planned`; shall be updated to `Existing, Production Ready` in the same body of work that promotes this entry, per **SF-SPEC-013** Section 5.7.
- All three planned Performance entries are now Production Ready; the category's own consistency review and baseline certification (the next steps per the established `SF-REVIEW-087`/`088`, `094`/`095` pattern) have not yet been performed and remain necessary before the category can be considered complete.
- IF-1's CLI-versus-FPM OPcache scoping claim, while a well-established general PHP operational characteristic, remains unverified against any one specific hosting environment's own actual configuration, which can vary (some environments do configure CLI and FPM to share OPcache deliberately, though this is not the common default).

---

# 12. Revision History

| Version | Date | Summary of Changes | Approval Status |
|---|---|---|---|
| 1.0 | 2026-07-14 | Initial independent review of WP-ERROR-035. Two Minor findings: IF-1 (Section 8/12 didn't warn that a CLI-invoked opcache_reset() commonly misses the web-facing OPcache instance, corrected within this entry) and IF-2 (WP-ERROR-032's own code-defect exclusion didn't distinguish itself from this entry's own condition, corrected within that entry). Approved with Minor Revisions; Production Ready gate satisfied — the thirty-first entry in this repository and the third and final planned entry in the Performance category. Confirmed SF-TAXONOMY-006 required no revision — the third consecutive clean pass, completing the evidentiary result the project owner asked this category to produce. | Approved with Minor Revisions — Production Ready gate satisfied |
