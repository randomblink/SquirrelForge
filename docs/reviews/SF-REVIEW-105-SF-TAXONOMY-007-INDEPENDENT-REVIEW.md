# SF-REVIEW-105 — SF-TAXONOMY-007 Independent Review

# 1. Review Information

**Review ID:** SF-REVIEW-105

**Review Date:** 2026-07-14

**Reviewer:** Class B — Independent Review, per **SF-SPEC-012** Section 6.2, conducted per this project's established practice of independently reviewing a category's taxonomy before entry authoring begins (`SF-REVIEW-034`, `045`, `069`, `080`, `089`, `096`), not as a normative requirement `SF-TAXONOMY-007` itself imposes.

**Status:** Complete

This is the third taxonomy drafted using the proactive cross-category ownership sweep discipline established after `WP-ERROR-032`'s own production cycle and validated by `SF-TAXONOMY-006`'s own complete category (`SF-REVIEW-103` Section 8). This review applies the same standard: every claimed overlap is independently re-verified against the cited entry's own actual text, and the cross-category sweep is independently re-run rather than accepted from the draft's own account.

---

# 2. Artifact Reviewed

`SF-TAXONOMY-007` — Media Error Taxonomy, Version 1.0, at `docs/standards/SF-TAXONOMY-007-MEDIA-ERROR-TAXONOMY.md`.

---

# 3. Governing Specifications

- **SF-SPEC-013 — Knowledge Category Lifecycle Specification** (Section 5.1, Category Entry Criteria)
- **SF-SPEC-001 — Error Knowledge Specification** (Section 7, confirming `Media` is an approved category value)
- **SF-SPEC-004 — Documentation Specification** (internal consistency, cross-references)

---

# 4. Review Scope

This review independently determines whether `SF-TAXONOMY-007` satisfies **SF-SPEC-013** Section 5.1, with particular attention to: (1) whether `SF-TAXONOMY-001`'s own forward-reference to a future Media category is accurately quoted and genuinely resolved; (2) whether the two claimed "genuine gap" territories (upload-size-limit rejection, file-type/MIME validation) are actually unclaimed anywhere in the repository, re-verified by an independent full-text sweep rather than trusting the draft's own account; and (3) whether the `WP-ERROR-014`/`019`/`020` boundaries are drawn accurately against those entries' own current text.

---

# 5. Evidence Examined

- Full contents of `SF-TAXONOMY-007`, read in full.
- `SF-SPEC-001` Section 7, independently re-read to confirm `Media` is an approved category value.
- `SF-TAXONOMY-001` Section 2 and Section 5, independently re-read in full to verify the exact wording of both forward-reference quotations this taxonomy cites — confirmed both quotations ("Media-library application behavior beyond the underlying filesystem operation itself... no category currently owns this" and "will be used as a concrete example location within `WP-ERROR-019` and `WP-ERROR-020`'s own content, not given its own entry, unless a dedicated Media/Uploads category is deliberately created later") match `SF-TAXONOMY-001`'s own actual text verbatim, not paraphrased or overstated.
- `WP-ERROR-019` and `WP-ERROR-020`, independently re-read in full to verify the claimed Components/Symptoms citations (`wp_upload_dir()`, `wp_mkdir_p()`, `wp_handle_upload()`, `move_uploaded_file()`, "media uploads" named directly) are accurate, and specifically to independently re-read `WP-ERROR-020` Section 6's own upload-size-limit exclusion language in full context rather than the single sentence this taxonomy quotes.
- `WP-ERROR-014`, independently re-read in full to verify its own Component citation of "the media and image-processing subsystem... `gd`... `Imagick`... image resizing, format conversion, thumbnail generation" is accurate and current.
- `WP-ERROR-021`/`022`/`023`, independently spot-checked to confirm each owns a generic REST pipeline stage with no media-specific carve-out or exclusion that would contradict this taxonomy's own "no separate Media REST entry needed" conclusion.
- An independent, fresh full-text sweep — not a re-run of the draft's own search terms, but an independently constructed one — for `post_max_size`, `upload_max_filesize`, `wp_max_upload_size`, `filetype`, `file type`, `MIME`, `wp_check_filetype_and_ext`, `upload_mimes`, `WP_Image_Editor`, `wp_generate_attachment_metadata`, `attachment`, and `thumbnail` across every file in `docs/knowledge/wp-errors/` and `docs/standards/SF-TAXONOMY-*.md`. Confirmed: the only matches for the two "genuine gap" search terms (upload-size-limit directives; file-type/MIME validation) are `WP-ERROR-020` and `SF-TAXONOMY-001` itself (both already accounted for by this taxonomy's own Section 2), with no additional, unnamed conflicting entry found.
- `ls docs/knowledge/wp-errors/ | grep -E "WP-ERROR-036|037|038"`, confirming none of the three planned IDs currently exist.
- `grep -n '\bmust\b'` (excluding `must-use`) and a drafting-language sweep against the full document.
- Independent technical verification of the four WordPress function/class names this taxonomy cites (`wp_check_filetype_and_ext()`, `WP_Image_Editor`, `wp_generate_attachment_metadata()`, `wp_max_upload_size()`) against current WordPress core naming, rather than accepted uncritically.

---

# 6. Findings

| Finding ID | Severity | Requirement or Criterion | Observation | Evidence | Required Action | Resolution Status |
|---|---|---|---|---|---|---|
| — | Conforming | SF-SPEC-013 §5.1, bullet 1 (boundary) | Section 2 declares a clear positive boundary (the two application-level gates before the filesystem write, and image processing after it) with explicit, evidence-cited exclusions. | Section 2. | None. |
| — | Conforming | `SF-TAXONOMY-001` forward-reference accuracy | Both quotations independently re-verified verbatim against `SF-TAXONOMY-001`'s own current text; neither is paraphrased in a way that overstates or understates the original promise. | `SF-TAXONOMY-001`, Section 5 above. | None. |
| — | Conforming | `WP-ERROR-019`/`020` boundary accuracy | All four cited Components (`wp_upload_dir()`, `wp_mkdir_p()`, `wp_handle_upload()`, `move_uploaded_file()`) and the "media uploads" Typical-Symptoms language independently confirmed present in both entries' own current text. | `WP-ERROR-019`/`020`, Section 5 above. | None. |
| — | Conforming | "Genuine gap" claim 1: upload-size-limit rejection | Independently re-read `WP-ERROR-020` Section 6 in full context (not only the quoted sentence): the exclusion is deliberate and explicit, and the independent full-text sweep confirms no entry anywhere in the repository actually documents this condition as its own — including `WP-ERROR-014`/`015`, despite `WP-ERROR-020`'s own text characterizing it as "a PHP Runtime/Configuration condition." The gap is real, not merely asserted. | `WP-ERROR-020` full text, independent sweep, Section 5 above. | None. |
| — | Conforming | "Genuine gap" claim 2: file-type/MIME validation | Independent full-text sweep confirms zero matches for file-type/MIME-validation terminology anywhere in the repository outside this taxonomy's own new text. The gap is real. | Independent sweep, Section 5 above. | None. |
| — | Conforming | `WP-ERROR-014` boundary accuracy | The cited Component language ("the media and image-processing subsystem... `gd`... `Imagick`...") independently confirmed present verbatim in `WP-ERROR-014`'s own current text. | `WP-ERROR-014`, Section 5 above. | None. |
| — | Conforming | REST API boundary (no separate Media REST entry) | Independently spot-checked `WP-ERROR-021`/`022`/`023`: each owns its own pipeline stage generically, with no media-specific exclusion or carve-out that would contradict this taxonomy's conclusion that the generic pipeline already fully covers the `wp/v2/media` endpoint. | `WP-ERROR-021`/`022`/`023`, Section 5 above. | None. |
| — | Conforming | SF-SPEC-013 §5.1, bullet 2 (planned entries, ownership) | Three entries, each with a one-line ownership statement in Section 3's table. | Section 3. | None. |
| — | Conforming | Ownership Model (Section 4) internal consistency | Independently re-derived: the sequential-pipeline model (size gate → type gate → [filesystem write, not owned] → image processing) is logically sound and correctly distinguished from `SF-TAXONOMY-006`'s own independent-mechanisms model rather than forced into a false similarity. Mutual exclusivity by construction independently confirmed: a file cannot simultaneously fail two gates, and image processing is definitionally unreachable for a file that failed an earlier stage. | Section 4. | None. |
| — | Conforming | SF-SPEC-013 §5.1, bullet 3 (rejected/deferred candidates, reasoning) | Five candidates addressed (Media REST API, attachment-metadata corruption, video/audio processing, CDN offload, Media Library UI), each with specific reasoning distinguishing rejection from deferral. | Section 5. | None. |
| — | Conforming | `SF-SPEC-001` Section 7 conformance | `Media` independently confirmed present in the approved category-value list. | `SF-SPEC-001` Section 7. | None. |
| — | Conforming | ID availability | `WP-ERROR-036` through `038` independently confirmed to not currently exist in the repository. | `ls` sweep, Section 5 above. | None. |
| — | Conforming | Technical accuracy | All four cited WordPress function/class names (`wp_check_filetype_and_ext()`, `WP_Image_Editor`, `wp_generate_attachment_metadata()`, `wp_max_upload_size()`) independently verified as real, current WordPress core symbols, not asserted uncritically. | Section 5 above. | None. |
| — | Conforming | Structural sweep | Zero bare `must` outside `must-use`/legitimate use (one instance found and corrected during drafting, before this review formally began); zero drafting-language matches. | Section 5 above. | None. |

No Major or Critical findings.

---

# 7. Outcome

**Approved.**

**Basis:** `SF-TAXONOMY-007` satisfies every element of **SF-SPEC-013** Section 5.1. Its two central "genuine gap" claims — that upload-size-limit rejection and file-type/MIME validation are both currently unclaimed by any existing entry — were independently re-verified by a freshly-constructed full-text sweep, not merely accepted from the draft's own account, and both `SF-TAXONOMY-001`'s own forward-reference quotations and every cited sibling entry's own boundary language were independently confirmed accurate against current repository state.

---

# 8. Gate Decision

Per **SF-SPEC-013** Section 5.1, entry authoring for the Media category (`WP-ERROR-036` through `038`) may now begin — this taxonomy exists, declares the category's boundary with independently-verified accuracy against every entry it claims already occupies adjacent territory, resolves a genuine, previously-open forward-reference from `SF-TAXONOMY-001`, enumerates every planned entry, documents rejected/deferred candidates, and has been independently reviewed per this project's established practice, including the proactive cross-category ownership sweep this project's own recent history (`SF-TAXONOMY-005`/`006`) established as standard.

---

# 9. Remaining Risks

- This review was conducted by the same class of agent (Claude Code) as the authoring pass.
- This is the first taxonomy in this catalog to resolve a different category's own explicit forward-reference promise from the outset (`SF-TAXONOMY-001`'s own Media anticipation) rather than only discovering adjacent territory through research; if drafting `WP-ERROR-036`–`038` reveals `SF-TAXONOMY-001`'s own text requires updating now that the forward-reference is resolved (mirroring how `WP-ERROR-021`/`022` were updated once `WP-ERROR-030` resolved the CORS forward-reference), that should surface as a finding during entry authoring rather than being silently deferred.
- The sequential-pipeline ownership model (Section 4) is a design choice not yet tested against real entries; if drafting reveals the three stages are harder to keep cleanly separated in practice than this taxonomy assumes — particularly the boundary between `WP-ERROR-037`'s own file-type rejection and a filesystem-level failure that happens to occur for a similarly-invalid file — that should surface as a finding in that entry's own author review rather than being silently absorbed.
- The deferred video/audio-processing candidate (Section 5) remains genuinely deferred, not resolved, pending any future core-level mechanism that does not currently exist.

---

# 10. Revision History

| Version | Date | Summary of Changes | Approval Status |
|---|---|---|---|
| 1.0 | 2026-07-14 | Initial independent review of SF-TAXONOMY-007. Independently re-verified both SF-TAXONOMY-001 forward-reference quotations and both "genuine gap" claims (upload-size-limit rejection, file-type/MIME validation) via a freshly-constructed full-text sweep, finding no additional conflict. Independently confirmed the WP-ERROR-014/019/020/021/022/023 boundary language against each entry's own current text, and verified all four cited WordPress function/class names as real and current. No findings. Approved. Entry authoring for WP-ERROR-036 through 038 may begin. | Approved |
