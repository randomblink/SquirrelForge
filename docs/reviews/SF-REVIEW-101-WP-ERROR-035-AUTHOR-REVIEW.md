# SF-REVIEW-101 — WP-ERROR-035 Author Review

# 1. Review Information

**Review ID:** SF-REVIEW-101

**Review Date:** 2026-07-14

**Reviewer:** Class A — Author Review, per **SF-SPEC-012** Section 6.1. Performed by the same authoring process that drafted `WP-ERROR-035`, within the same work-order execution.

**Status:** Complete

Per **SF-SPEC-012** Section 6.1, this Class A review may identify and correct defects but shall not, by itself, authorize a **Production Ready** designation.

This is the third and final planned entry in the Performance category. Per explicit project-owner direction, this review gives particular scrutiny to four named risks: ownership versus PHP Runtime, ownership versus the deployment/update mechanism (`WP-ERROR-032`), the distinction between stale compiled bytecode and stale application data, and whether Recovery drifts into generic PHP/web-server operational guidance unrelated to the verified bytecode-staleness cause.

---

# 2. Artifact Reviewed

`WP-ERROR-035` — WordPress OPcache Stale Bytecode, Version 1.0, at `docs/knowledge/wp-errors/WP-ERROR-035-OPCACHE-STALE-BYTECODE.md`. Status at time of this review: Draft.

---

# 3. Governing Specifications

- **SF-SPEC-001**, **SF-SPEC-012**, **SF-TEMPLATE-004**, **SF-GLOSSARY-001** — same as prior category reviews.
- `SF-TAXONOMY-006` — Caching / Performance Error Taxonomy, Version 1.2, whose Section 3 entry declaration governs this entry.

---

# 4. Review Scope

Per the project owner's own explicit direction, this review evaluates `WP-ERROR-035` against four specific criteria:

1. **Ownership versus PHP Runtime (`WP-ERROR-014`)** — this entry owns bytecode *staleness*, not OPcache *availability*.
2. **Ownership versus the deployment/update mechanism (`WP-ERROR-032`)** — this entry presumes the update mechanism itself already succeeded; its own condition begins only where that one ends.
3. **Stale bytecode versus stale application data** — explicit disambiguation from every data/content-caching entry in this category and elsewhere (`WP-ERROR-021`/`025`/`027`/`030`/`033`/`034`).
4. **No drift into generic operational guidance** — Recovery Procedure content stays scoped to actions that specifically address OPcache invalidation, not general PHP-FPM/server troubleshooting.

---

# 5. Precondition Verification

`WP-ERROR-013`, `014`, `021`, `025`, `027`, `030`, `032`, `033`, and `034` are all Production Ready in this repository, correctly cited with real links. `SF-TAXONOMY-006` re-read at its current Version 1.2 state, confirming this entry was drafted against its final text, unchanged since `WP-ERROR-034`'s own promotion.

---

# 6. Evidence Examined

- Full contents of `WP-ERROR-035-OPCACHE-STALE-BYTECODE.md`, read in full.
- `grep -c '^# [0-9]\+\.'` (17, matching `SF-TEMPLATE-004`); section numbering sequential with no gaps or repeats.
- `grep -n '\bmust\b'` (excluding `must-use`) — zero matches.
- `grep -niE 'TBD|TODO|FIXME|placeholder'` — zero matches.
- `git diff --check` (clean).
- Link-target verification: all nine cited entries independently resolved to existing, Production Ready files.
- `scripts/validate-repo.sh .`: initial run reported `WP-ERROR-034`'s own Section 16 citation of this entry as newly stale; corrected in `WP-ERROR-034` (converted to a real link). Re-run after correction: clean.
- **Criterion 1 (PHP Runtime boundary):** independently re-checked Section 6's own `WP-ERROR-014` distinction bullet — "owns the condition of OPcache itself being unavailable... a categorical capability question. This entry presumes OPcache is present and actively functioning" — against `WP-ERROR-014`'s own Section 7 Scope text. Confirmed no overlap: `WP-ERROR-014` covers a missing/disabled extension generally; this entry presumes the extension is present and functioning, addressing only its own invalidation behavior.
- **Criterion 2 (WP-ERROR-032 boundary):** independently re-read `WP-ERROR-032`'s own Section 6/7 in full to confirm this entry's own characterization ("owns the update mechanism's own process... this entry presumes that process already succeeded completely") is accurate and that Section 11 step 1 ("confirm the source files on disk are actually correct and complete first") correctly operationalizes that boundary as a diagnostic precondition rather than merely asserting it.
- **Criterion 3 (stale code vs. stale data):** independently re-verified Section 6's own dedicated subsection distinguishing this entry from all six data/content-caching entries (`WP-ERROR-021`/`025`/`027`/`030`/`033`/`034`) is present, explicit, and accurate against each of those entries' own actual condition, not merely asserted in the abstract.
- **Criterion 4 (no generic operational drift):** independently re-read Section 12 (Recovery Procedure) line by line to confirm every permitted action ties specifically to OPcache invalidation (`opcache_reset()`, a targeted PHP-FPM reload for a *confirmed-affected* process, a deployment-process change, `revalidate_freq` adjustment) rather than generic troubleshooting; confirmed the entry's own explicit prohibition ("Recovery shall not disable OPcache entirely as a general response") and the explicit constraint that a PHP-FPM reload target only "the specific... worker pool(s) or server(s) confirmed still serving stale bytecode," not an unscoped restart.
- Independent technical re-verification of the core Severity claim (Section 5) — that per-file, non-atomic invalidation can produce a genuine cross-file fatal error — assessed as a plausible, well-reasoned structural consequence of how OPcache actually operates (per-file, not per-deployment), not an overstated or hypothetical edge case.

---

# 7. Findings

| Finding ID | Severity | Observation | Correction |
|---|---|---|---|
| — | Conforming | Bare-`must` sweep: zero matches. | None. |
| — | Conforming | Criterion 1 (PHP Runtime boundary): accurately scoped, independently re-verified against `WP-ERROR-014`'s own text. | None. |
| — | Conforming | Criterion 2 (WP-ERROR-032 boundary): the "files correct, execution stale" distinction is stated explicitly and operationalized as an actual diagnostic step (Section 11, step 1), not left as an abstract assertion. | None. |
| — | Conforming | Criterion 3 (stale code vs. stale data): explicit, dedicated disambiguation against all six relevant sibling entries, independently re-verified accurate. | None. |
| — | Conforming | Criterion 4 (no generic operational drift): every Recovery action is scoped specifically to a confirmed OPcache-invalidation cause; the entry explicitly prohibits disabling OPcache generally as a response. | None. |
| — | Conforming | Severity classification (range-based Critical) is reasoned independently from first principles — cross-file bytecode inconsistency producing a fatal error — rather than inherited from either sibling entry, matching the project owner's own praised pattern from `WP-ERROR-033`/`034`. | None. |
| — | Conforming | Structure: all 17 `SF-TEMPLATE-004` sections present, in order, sequentially numbered, none empty. | None. |
| — | Conforming | Cross-document staleness this entry's own creation would cause (`WP-ERROR-034` Section 16's own conceptual-reference citation) was identified and corrected within this same review. | None (already corrected, per Section 6 above). |
| — | Conforming | Security Considerations (Section 15) ties this entry's own condition to a concrete, non-hypothetical risk (a security patch silently failing to take effect) rather than generic caching-security boilerplate. | None. |

No Major or Critical findings.

---

# 8. Recommendations

- None beyond proceeding to independent review.

---

# 9. Outcome

**Approved (for purposes of proceeding to independent review only).**

**Basis:** No defect was found. All four of the project owner's own review criteria — PHP Runtime boundary, `WP-ERROR-032` boundary, stale-code-versus-stale-data disambiguation, and no generic operational drift — independently verified as satisfied. This outcome does not authorize Production Ready.

`WP-ERROR-035` remains `Draft`.

---

# 10. Revision History

| Version | Date | Summary of Changes | Approval Status |
|---|---|---|---|
| 1.0 | 2026-07-14 | Initial author review of WP-ERROR-035. No findings in this entry's own text. Confirmed all nine cited sibling entries exist, are Production Ready, and are correctly linked. Corrected the expected cross-document staleness this entry's own creation caused in WP-ERROR-034's Section 16 (conceptual-reference-to-link conversion). Independently verified all four of the project owner's own review criteria against the cited sibling entries' own full text. | Approved (Class A; does not authorize Production Ready) |
