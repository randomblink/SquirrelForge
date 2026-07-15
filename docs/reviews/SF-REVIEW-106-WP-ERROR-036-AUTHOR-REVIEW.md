# SF-REVIEW-106 — WP-ERROR-036 Author Review

# 1. Review Information

**Review ID:** SF-REVIEW-106

**Review Date:** 2026-07-14

**Reviewer:** Class A — Author Review, per **SF-SPEC-012** Section 6.1. Performed by the same authoring process that drafted `WP-ERROR-036`, within the same work-order execution.

**Status:** Complete

Per **SF-SPEC-012** Section 6.1, this Class A review may identify and correct defects but shall not, by itself, authorize a **Production Ready** designation.

This is the first entry in the Media category, and the first entry drafted against a taxonomy (`SF-TAXONOMY-007`) that resolved another taxonomy's own explicit forward-reference (`SF-TAXONOMY-001`'s Media anticipation) rather than only researching adjacent territory from scratch. This review gives particular scrutiny to whether the entry accurately implements the specific "genuine gap" the taxonomy identified, rather than drifting into territory `WP-ERROR-019`/`020` already own.

---

# 2. Artifact Reviewed

`WP-ERROR-036` — WordPress Upload Size Limit Exceeded, Version 1.0, at `docs/knowledge/wp-errors/WP-ERROR-036-UPLOAD-SIZE-LIMIT-EXCEEDED.md`. Status at time of this review: Draft.

---

# 3. Governing Specifications

- **SF-SPEC-001**, **SF-SPEC-012**, **SF-TEMPLATE-004**, **SF-GLOSSARY-001** — same as prior category-opening reviews.
- `SF-TAXONOMY-007` — Media Error Taxonomy, Version 1.0, whose Section 3 entry declaration governs this entry.

---

# 4. Review Scope

This review evaluates whether `WP-ERROR-036`, as drafted, correctly implements `SF-TAXONOMY-007`'s own declared scope: the pre-filesystem-write size-limit gate specifically, with particular attention to (1) whether the three-cause separation (`post_max_size`, `upload_max_filesize`, WordPress's own further restriction) is technically accurate and diagnostically distinct; (2) whether the entry correctly hands off to `WP-ERROR-019`/`020` rather than describing any part of the filesystem-write stage; and (3) whether the deliberate High/High severity classification is substantiated rather than merely asserted.

---

# 5. Precondition Verification

`WP-ERROR-019` and `WP-ERROR-020` are both Production Ready in this repository, correctly cited with real links. `WP-ERROR-037` and `WP-ERROR-038` do not exist (`ls docs/knowledge/wp-errors/ | grep -E "WP-ERROR-037|WP-ERROR-038"` returns no result); both cited as conceptual references with no link, matching the established convention. `SF-TAXONOMY-007` re-read at its current Version 1.0 (Frozen, independently reviewed per `SF-REVIEW-105`) state, confirming this entry was drafted against its final, reviewed text.

---

# 6. Evidence Examined

- Full contents of `WP-ERROR-036-UPLOAD-SIZE-LIMIT-EXCEEDED.md`, read in full.
- `grep -c '^# [0-9]\+\.'` (17, matching `SF-TEMPLATE-004`); section numbering sequential with no gaps or repeats.
- `grep -n '\bmust\b'` (excluding `must-use`) — one match found and corrected during this same review pass (a descriptive "must accommodate" reworded to "needs to accommodate"), zero remaining.
- `grep -niE 'TBD|TODO|FIXME|placeholder'` — zero matches.
- `git diff --check` (clean).
- Link-target verification: `WP-ERROR-019`, `020` links independently resolved to existing, Production Ready files.
- **Criterion 1 (three-cause technical accuracy):** independently re-derived PHP's own `post_max_size`/`upload_max_filesize` behavior from first principles to test the entry's own claims. Confirmed: when `post_max_size` is exceeded, PHP discards the request body before script execution, leaving `$_POST`/`$_FILES` empty with no error code populated — genuinely distinct from `upload_max_filesize` being exceeded, where PHP does populate `$_FILES[...]['error']` with `UPLOAD_ERR_INI_SIZE` for the running script to detect. This asymmetry is the load-bearing technical claim behind keeping causes 1 and 2 separate, and is accurately described.
- **Criterion 2 (WP-ERROR-019/020 hand-off):** independently re-verified Section 6/7's own boundary language against `WP-ERROR-020` Section 6's own exclusion statement (already quoted accurately in `SF-TAXONOMY-007` itself, re-confirmed here against the entry's own text) — no diagnostic or recovery content describing the filesystem-write stage was found; every reference to `WP-ERROR-019`/`020` is a boundary statement or hand-off, not a duplication.
- **Criterion 3 (severity substantiation):** independently re-verified the High/High classification against every cause and symptom described in the entry's own text; confirmed no plausible manifestation described anywhere includes a fatal error or full-site outage, consistent with the stated reasoning and with the precedent `WP-ERROR-009`/`034` already established for the same class of exception.
- Independent verification of the multisite-specific claim (`fileupload_maxk` site option, network-level "Site Upload Space" setting) as a real, current WordPress multisite mechanism rather than asserted uncritically.

---

# 7. Findings

| Finding ID | Severity | Observation | Correction |
|---|---|---|---|
| — | Minor | Bare-`must` sweep found one instance ("the request body must accommodate"). | Reworded to "needs to accommodate." |
| — | Conforming | Criterion 1 (three-cause technical accuracy): the `post_max_size`-versus-`upload_max_filesize` asymmetry (silent failure with no error code, versus a populated `UPLOAD_ERR_INI_SIZE` code) is independently confirmed accurate and is the correct basis for keeping the two causes diagnostically separate. | None. |
| — | Conforming | Criterion 2 (WP-ERROR-019/020 hand-off): no filesystem-write-stage content duplicated; every reference is a boundary statement. | None. |
| — | Conforming | Criterion 3 (severity substantiation): High/High classification independently re-verified as consistent with every described manifestation, no fatal-error or outage scenario present anywhere in the entry's own text. | None. |
| — | Conforming | The web-server/gateway-level request-size-limit exclusion (Section 6, final bullet) is a genuine, correctly-scoped boundary — a real adjacent condition with a similar symptom to cause 1, correctly excluded rather than silently absorbed. | None. |
| — | Conforming | Structure: all 17 `SF-TEMPLATE-004` sections present, in order, sequentially numbered, none empty. | None. |
| — | Conforming | Multisite-specific claim (`fileupload_maxk`, network Site Upload Space setting) independently verified as accurate. | None. |

No Major or Critical findings.

---

# 8. Recommendations

- None beyond proceeding to independent review.

---

# 9. Outcome

**Approved (for purposes of proceeding to independent review only).**

**Basis:** One Minor structural finding (a bare `must`) was found and corrected within this same review. The entry's boundary, three-cause technical accuracy, hand-off discipline to `WP-ERROR-019`/`020`, and deliberate severity classification all independently verified as conforming to `SF-TAXONOMY-007`'s own declared scope. This outcome does not authorize Production Ready.

`WP-ERROR-036` remains `Draft`.

---

# 10. Revision History

| Version | Date | Summary of Changes | Approval Status |
|---|---|---|---|
| 1.0 | 2026-07-14 | Initial author review of WP-ERROR-036. One Minor structural finding (a bare `must`) corrected. Confirmed WP-ERROR-019/020 exist, are Production Ready, and are correctly linked. Confirmed WP-ERROR-037/038 do not exist. Independently re-derived the technical asymmetry between post_max_size and upload_max_filesize behavior from first principles rather than accepting the draft's own claim, and independently verified the deliberate High/High severity classification against every manifestation described. | Approved (Class A; does not authorize Production Ready) |
