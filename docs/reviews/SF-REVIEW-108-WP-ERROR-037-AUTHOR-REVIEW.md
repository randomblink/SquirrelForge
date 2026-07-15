# SF-REVIEW-108 — WP-ERROR-037 Author Review

# 1. Review Information

**Review ID:** SF-REVIEW-108

**Review Date:** 2026-07-14

**Reviewer:** Class A — Author Review, per **SF-SPEC-012** Section 6.1. Performed by the same authoring process that drafted `WP-ERROR-037`, within the same work-order execution.

**Status:** Complete

Per **SF-SPEC-012** Section 6.1, this Class A review may identify and correct defects but shall not, by itself, authorize a **Production Ready** designation.

Per explicit project-owner direction, this review gives particular scrutiny to four named boundaries: Media versus Security (dangerous-file restrictions), Media versus Filesystem (write failures), Media versus WordPress's own MIME/content-type detection specifically, and Media versus web-server-/WAF-level upload blocking.

---

# 2. Artifact Reviewed

`WP-ERROR-037` — WordPress Upload File Type Rejected, Version 1.0, at `docs/knowledge/wp-errors/WP-ERROR-037-UPLOAD-FILE-TYPE-REJECTED.md`. Status at time of this review: Draft.

---

# 3. Governing Specifications

- **SF-SPEC-001**, **SF-SPEC-012**, **SF-TEMPLATE-004**, **SF-GLOSSARY-001** — same as prior category reviews.
- `SF-TAXONOMY-007` — Media Error Taxonomy, Version 1.1, whose Section 3 entry declaration governs this entry.

---

# 4. Review Scope

This review evaluates `WP-ERROR-037` against the four boundaries the project owner specifically named:

1. **Media versus Security** — this entry owns the file-type gate's own designed, correctly-functioning rejection behavior; it does not claim ownership of investigating a specific file as a deliberate attack or any resulting compromise response.
2. **Media versus Filesystem** — this entry presumes the file has not yet reached the filesystem-write stage; `WP-ERROR-019`/`020` remain untouched.
3. **Media versus WordPress's own MIME/content-type detection specifically** — the entry needs to keep the extension-allowlist check and the content-verification check cleanly separated as two distinct causes, not conflated into one.
4. **Media versus web-server-/WAF-level blocking** — the same class of exclusion `WP-ERROR-036` already established for a request never reaching PHP at all.

---

# 5. Precondition Verification

`WP-ERROR-014`, `019`, `020`, and `036` are all Production Ready in this repository, correctly cited with real links. `WP-ERROR-038` does not exist (`ls docs/knowledge/wp-errors/ | grep "WP-ERROR-038"` returns no result); cited as a conceptual reference with no link. `SF-TAXONOMY-007` re-read at its current Version 1.1 state, confirming this entry was drafted against its final, reviewed text.

---

# 6. Evidence Examined

- Full contents of `WP-ERROR-037-UPLOAD-FILE-TYPE-REJECTED.md`, read in full.
- `grep -c '^# [0-9]\+\.'` (17, matching `SF-TEMPLATE-004`); section numbering sequential with no gaps or repeats.
- `grep -n '\bmust\b'` (excluding `must-use`) — zero matches.
- `grep -niE 'TBD|TODO|FIXME|placeholder'` — zero matches.
- `git diff --check` (clean).
- Link-target verification: `WP-ERROR-014`, `019`, `020`, `036` links independently resolved to existing, Production Ready files.
- `scripts/validate-repo.sh .`: initial run reported `WP-ERROR-036`'s own Section 16 citation of this entry as newly stale (the "no link" conceptual-reference framing); corrected in `WP-ERROR-036` (converted to a real link). Re-run after correction: clean.
- **Criterion 1 (Media vs. Security):** independently re-checked Section 6's own dedicated exclusion bullet ("A malicious file upload, and any resulting compromise investigation... this entry documents the file-type gate's own designed, correctly-functioning rejection behavior... It does not own investigating whether a *specific* rejected (or... accepted) file was a deliberate attack"). Confirmed this entry claims only the mechanism's own behavior, consistent with how `WP-ERROR-017` Section 15 and other entries in this catalog treat "potential security incident" as a Section 15 concern, not an ownership claim.
- **Criterion 2 (Media vs. Filesystem):** independently re-verified no content in Section 8–14 describes the filesystem-write stage itself; every `WP-ERROR-019`/`020` reference is a boundary statement.
- **Criterion 3 (extension-allowlist vs. content-verification):** independently re-verified Section 6's own two-cause structure keeps the extension-allowlist check (cause 1) and the content-type-verification check (cause 2) fully separate, each with its own distinct diagnostic step in Section 11, rather than blending them into a single generic "wrong type" condition.
- **Criterion 4 (Media vs. web-server/WAF blocking):** independently re-verified Section 6's own exclusion bullet mirrors `WP-ERROR-036`'s own established pattern (a request that never reaches WordPress's own code presents identically but is excluded), and that Section 11 step 2 operationalizes this as an actual diagnostic step rather than only an abstract boundary statement.
- Independent technical re-verification of the `unfiltered_upload` capability claim, the multisite-specific narrower-default claim, and the `fileinfo`-extension graceful-degradation claim, each checked for plausibility against current WordPress core behavior rather than accepted uncritically.

---

# 7. Findings

| Finding ID | Severity | Observation | Correction |
|---|---|---|---|
| — | Conforming | Bare-`must` sweep: zero matches. | None. |
| — | Conforming | Criterion 1 (Media vs. Security): the entry claims only the mechanism's own designed behavior, explicitly declining broader security-incident ownership. | None. |
| — | Conforming | Criterion 2 (Media vs. Filesystem): no filesystem-write-stage content duplicated; every reference is a boundary statement. | None. |
| — | Conforming | Criterion 3 (extension-allowlist vs. content-verification): the two causes are kept fully distinct throughout, each with its own diagnostic and recovery path. | None. |
| — | Conforming | Criterion 4 (Media vs. web-server/WAF blocking): mirrors `WP-ERROR-036`'s own established exclusion pattern and is operationalized as an actual diagnostic step. | None. |
| — | Conforming | The capability-dependent allowed-types nuance (`unfiltered_upload`) is a genuine, non-obvious WordPress behavior correctly identified and given its own diagnostic step (Section 11, step 3) rather than assumed uniform across users. | None. |
| — | Conforming | The "do not disable file-type validation as a shortcut" anti-pattern is explicitly and prominently prohibited in Section 12, matching this catalog's established pattern for security-relevant mechanisms. | None. |
| — | Conforming | Structure: all 17 `SF-TEMPLATE-004` sections present, in order, sequentially numbered, none empty. | None. |
| — | Conforming | Cross-document staleness this entry's own creation would cause (`WP-ERROR-036` Section 16's own conceptual-reference citation) was identified and corrected within this same review. | None (already corrected, per Section 6 above). |

No Major or Critical findings.

---

# 8. Recommendations

- None beyond proceeding to independent review.

---

# 9. Outcome

**Approved (for purposes of proceeding to independent review only).**

**Basis:** No defect was found. All four of the project owner's own named boundaries independently verified as correctly drawn, and the entry's own two-cause separation is technically sound and diagnostically distinct. This outcome does not authorize Production Ready.

`WP-ERROR-037` remains `Draft`.

---

# 10. Revision History

| Version | Date | Summary of Changes | Approval Status |
|---|---|---|---|
| 1.0 | 2026-07-14 | Initial author review of WP-ERROR-037. No findings in this entry's own text. Confirmed WP-ERROR-014/019/020/036 exist, are Production Ready, and are correctly linked. Confirmed WP-ERROR-038 does not exist. Corrected the expected cross-document staleness this entry's own creation caused in WP-ERROR-036's Section 16. Independently verified all four of the project owner's own named boundaries (Media vs. Security, Filesystem, MIME/content-type detection, web-server/WAF blocking) against the entry's own text and the cited sibling entries' own text. | Approved (Class A; does not authorize Production Ready) |
