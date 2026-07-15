# SF-TAXONOMY-007 — Media Error Taxonomy

## Document Information

**Document ID:** SF-TAXONOMY-007

**Title:** Media Error Taxonomy

**Classification:** Engineering Taxonomy — a lightweight, informally-adopted planning artifact, governed by **SF-SPEC-013 — Knowledge Category Lifecycle Specification** for its role in the category lifecycle, but not itself templated by any `SF-TEMPLATE-XXX` document (none currently exists for this artifact type). It is not governed by **SF-SPEC-005**'s review process as a knowledge artifact and does not itself require an author/independent review pair, though an independent review of it is planned per this project's established practice (`SF-REVIEW-034`, `045`, `069`, `080`, `089`, `096`), not as a normative requirement this document imposes on itself.

**Status:** Frozen — the entry set in Section 3 is fixed until this document is deliberately revised (see Section 6). "Frozen" here is an informal, self-defined term describing this document's own adopted-plan state; it is not a claim of the `Version Frozen` **SF-SPEC-001** Section 18 lifecycle stage, nor of any **SF-SPEC-008** Section 6 Version Status value, nor of the category-level `Baseline Certified` designation **SF-SPEC-013** Section 5.5 defines. This document carries a `Version` and `Revision History` for traceability only; it does not present itself as a "versioned engineering artifact" within **SF-SPEC-008**'s own scope, the same disclaimer `SF-TAXONOMY-001`–`006` make.

**Version:** 1.2

**Owner:** SquirrelForge

---

## 1. Purpose

Declare the `Media` category's boundary and complete planned `WP-ERROR` entry set *before* any entry in it is authored, per **SF-SPEC-013** Section 5.1's Category Entry Criteria — the fifth candidate in the Knowledge Production Plan's roadmap, and the category `SF-TAXONOMY-001` itself explicitly anticipated: "Media-library application behavior beyond the underlying filesystem operation itself — no category currently owns this; out of scope until one is deliberately created," and, more specifically, that the uploads directory "will be used as a concrete example location within `WP-ERROR-019` and `WP-ERROR-020`'s own content, not given its own entry, unless a dedicated Media/Uploads category is deliberately created later." This taxonomy is that category, and this document resolves both forward-references.

This document does not itself contain any `WP-ERROR` knowledge content and is not cited as a `WP-ERROR` entry.

---

## 2. Category Boundary

**Media** owns the application-level stages of WordPress's own upload and media-processing pipeline: the size-limit and file-type gates a submitted file shall pass *before* WordPress ever attempts to write it to the filesystem, and the image-processing stage (thumbnail and intermediate-size generation) that runs *after* an accepted file has already been successfully written. It does not own the OS-level filesystem write itself, which two existing entries already fully claim, and it does not own the underlying PHP-runtime capability question of whether an image-processing extension exists at all, which a third existing entry already fully claims.

Per the same proactive research discipline established for `SF-TAXONOMY-005`/`006`, this category's own boundary was researched against every existing entry with a plausible claim before being declared, not designed from first principles in isolation:

- `WP-ERROR-019` (Filesystem Permission Denied) and `WP-ERROR-020` (Disk Space Exhausted), both predating this taxonomy, already explicitly document `wp_upload_dir()`, `wp_mkdir_p()`, `wp_handle_upload()`, and `move_uploaded_file()` as their own Components, and already name "media uploads" directly in their own Typical Symptoms and Severity sections. This category does not claim the OS-level access or capacity dimension of an upload's own filesystem write; it hands off to those two entries once a specific failure is confirmed to be permission- or capacity-related.
- `WP-ERROR-020` itself already explicitly excludes "PHP/WordPress upload-size limits" (`upload_max_filesize`, `post_max_size`, WordPress's own `wp_max_upload_size()` filter) from its own scope, on the stated basis that a size-limit rejection "happens regardless of how much actual capacity is available" and "never reaches the filesystem at all" — describing it as "a PHP Runtime/Configuration condition." Independent verification during this taxonomy's own drafting found that no existing entry, including `WP-ERROR-014`/`015` (PHP Runtime), actually documents this condition — it is a genuine, previously disclosed-but-unowned gap. This category claims it. See Section 3.
- `WP-ERROR-014` (Required PHP Extension Missing) already explicitly documents "the media and image-processing subsystem, which depends on `gd` or the `Imagick` class provided by the `imagick` extension, for image manipulation (resizing, format conversion, thumbnail generation)" as its own Component, and already names "image resizing" as an example symptom of a missing extension. This category does not claim the categorical, environment-wide question of whether the required extension is available at all; it owns the observable image-processing failure once that extension is confirmed present, and hands off to `WP-ERROR-014` where diagnosis confirms the extension itself is the actual cause.
- No existing entry documents WordPress's own file-type/MIME validation gate (`wp_check_filetype_and_ext()`, the `upload_mimes` filter) at all — a second genuine, previously-unclaimed gap this category claims.
- REST API requests targeting the `wp/v2/media` endpoint are not given separate treatment here: a route-not-found, access-denied, or malformed-response condition on that endpoint is already fully owned by `WP-ERROR-021`/`022`/`023` respectively, the same generic REST pipeline every other endpoint uses. This category does not duplicate that ownership for the media-specific route.

**Explicitly not owned by Media:**

* **The OS-level filesystem write of an uploaded file** (permission or capacity) — `WP-ERROR-019`/`020`'s own territory, per the research above.
* **Whether a required image-processing PHP extension (`gd`, `imagick`) is available to the runtime at all** — `WP-ERROR-014`'s own territory; this category presumes the extension is present and owns only its own observable behavior once invoked.
* **A REST API request for a media endpoint failing at the routing, authentication/authorization, or response-formation stage** — `WP-ERROR-021`/`022`/`023`'s own territory, the same generic pipeline every REST endpoint uses.
* **A specific media/gallery/CDN-offload plugin's own business-logic defect** — Plugin category, per the same reasoning `SF-TAXONOMY-005` Section 2 and `SF-TAXONOMY-006` Section 2 already establish for a specific plugin's own implementation defect generally.
* **Database-level storage or corruption of attachment metadata**, once written — Database category's own territory (`WP-ERROR-006` for corruption, `WP-ERROR-009` for query performance), regardless of the fact that the metadata in question happens to describe a media file.
* **Video or audio file handling beyond basic upload acceptance** — WordPress core performs no server-side transcoding, thumbnail generation, or format validation specific to video/audio the way it does for images; out of scope for this category's initial planned set, since no core mechanism exists to document.

---

## 3. Planned Entries

| Entry | Title | Owns | Status |
|---|---|---|---|
| `WP-ERROR-036` | WordPress Upload Size Limit Exceeded | A verified condition in which an uploaded file is rejected because its size exceeds an applicable, currently-configured limit — PHP's own `upload_max_filesize` or `post_max_size` directive, or WordPress's own `wp_max_upload_size()` filter narrowing the effective limit further — before any filesystem write is attempted at all. Resolves the specific gap `WP-ERROR-020` Section 6 already disclosed and explicitly excluded from its own scope. | Existing, Production Ready |
| `WP-ERROR-037` | WordPress Upload File Type Rejected | A verified condition in which an otherwise size-acceptable uploaded file is rejected because WordPress's own file-type validation (`wp_check_filetype_and_ext()`, governed by the `upload_mimes` filter and, where applicable, `allow_unfiltered_uploads`) determines the file's extension, or the mismatch between its extension and actual content, is not permitted — before the file reaches the filesystem-write stage `WP-ERROR-019`/`020` own. | Existing, Production Ready |
| `WP-ERROR-038` | WordPress Image Processing Failure | A verified condition in which an image file has already been successfully accepted and written to the filesystem, but WordPress's own post-upload image-processing stage (`WP_Image_Editor`, backed by a confirmed-present `gd` or `imagick` extension) fails to generate one or more intermediate sizes or the attachment's own metadata (`wp_generate_attachment_metadata()`) — presuming the required extension is available, and owning the observable processing failure rather than the extension's own availability. | Planned |

Nothing else is currently planned for this category. Any future addition to this table is a revision to this document, not an ad hoc decision made while authoring an unrelated entry, per **SF-SPEC-013** Section 5.7.

---

## 4. Ownership Model

Unlike `SF-TAXONOMY-006`'s three independent mechanisms, this category's three entries form a **sequential pipeline**, closer in structure to `SF-TAXONOMY-004`'s own `WP-ERROR-028`/`029` pair or the REST API category's own three-stage model:

1. **`WP-ERROR-036`** — the size-limit gate, evaluated first, before any other check.
2. **`WP-ERROR-037`** — the file-type gate, evaluated after the size-limit gate passes.
3. *(Not owned by this category: the filesystem write itself — `WP-ERROR-019`/`020` — occurs after both gates above pass.)*
4. **`WP-ERROR-038`** — image processing, occurring only after a file has already been successfully written to the filesystem, and only for image files specifically.

A single upload attempt fails at exactly one of these stages (or, for the two stages this category does not own, at the filesystem-write stage between them). The three entries are mutually exclusive by construction: a file that fails the size-limit gate never reaches the file-type gate; a file that fails the file-type gate never reaches the filesystem write; a file that fails the filesystem write never reaches image processing, which additionally cannot apply at all to a file that was never successfully written in the first place.

**Evidentiary basis for this structure:** both `WP-ERROR-036` and `WP-ERROR-037` resolve gaps that existing entries already disclosed or left implicitly unclaimed (Section 2), rather than requiring new boundary research from first principles — `WP-ERROR-020`'s own explicit exclusion for upload-size limits directly names the condition `WP-ERROR-036` now claims, and no existing entry's own text contradicts `WP-ERROR-037`'s claim to file-type validation. `WP-ERROR-038` follows the same categorical-capability-versus-observable-behavior pattern already established between `WP-ERROR-014` and each of `WP-ERROR-029`/`033`/`035`.

---

## 5. Candidates Considered and Rejected

* **A dedicated "Media Library REST API" entry:** not given an entry. The `wp/v2/media` REST endpoint uses the same generic routing, authentication/authorization, and response-formation pipeline every other REST endpoint uses, already fully owned by `WP-ERROR-021`/`022`/`023`. No condition specific to the media endpoint, distinct from what those three entries already document generically, was identified.
* **A dedicated "Attachment Metadata Corruption" entry:** not given an entry. Once attachment metadata is written, its own corruption or loss is a database-storage condition indistinguishable in kind from any other row's corruption — `WP-ERROR-006`'s own territory. No media-specific mechanism was identified that would justify a separate entry over the general database-corruption condition already covered.
* **A "Video/Audio Processing Failure" entry, parallel to `WP-ERROR-038`:** not given an entry. WordPress core performs no server-side transcoding or format validation specific to video/audio files; a video or audio file that passes the size-limit and file-type gates is simply stored as-is, with no further core-provided processing stage to fail. Deferred, not rejected outright, should a plugin-independent, core-level video/audio processing mechanism be introduced in a future WordPress version.
* **A "CDN / External Media Offload" entry:** not given an entry. Offloading media storage to an external service (S3, a CDN) is exclusively a third-party plugin's own implementation, with no WordPress-core mechanism to document — Plugin category territory for any specific plugin's own defect, per Section 2.
* **A "Media Library UI/Admin-Screen Failure" entry:** not given an entry. No evidence was found of a UI-specific failure mode distinct from the underlying upload/processing conditions `WP-ERROR-036`–`038` already cover; a broken Media Library screen is, on investigation, consistently traceable to one of those three conditions or to a generic JavaScript/asset-loading issue outside this category's own scope.

---

## 6. Revision History

| Version | Date | Summary of Changes | Status |
|---|---|---|---|
| 1.0 | 2026-07-14 | Initial taxonomy. Resolves the forward-reference `SF-TAXONOMY-001` Section 2/5 made to a future "dedicated Media/Uploads category." Research found two genuine, previously-unclaimed gaps: PHP/WordPress upload-size-limit rejection (already disclosed and excluded by `WP-ERROR-020` Section 6, but never claimed by any entry) and WordPress's own file-type/MIME validation gate (never mentioned by any existing entry). Plans three entries — `WP-ERROR-036` (Upload Size Limit Exceeded), `WP-ERROR-037` (Upload File Type Rejected), `WP-ERROR-038` (Image Processing Failure) — dividing the category as a sequential pipeline (size gate → type gate → [filesystem write, not owned] → image processing), mutually exclusive by construction. A dedicated Media REST API entry, an attachment-metadata-corruption entry, a video/audio-processing entry, a CDN-offload entry, and a Media-Library-UI entry each considered and deferred or rejected, per Section 5. | Frozen |
| 1.1 | 2026-07-14 | WP-ERROR-036 reached Production Ready (SF-REVIEW-106 author review, one Minor structural finding corrected; SF-REVIEW-107 independent review, which corrected a mechanism-level phrasing overstatement and a missing diagnostic signal within the entry itself, and a cross-document completeness gap in WP-ERROR-020's own exclusion bullet, rather than finding a defect in this taxonomy). Status column updated from Planned to Existing, Production Ready. No boundary content changed; this entry required no revision to this taxonomy. | Frozen |
| 1.2 | 2026-07-14 | WP-ERROR-037 reached Production Ready (SF-REVIEW-108 author review, no findings; SF-REVIEW-109 independent review, which corrected a completeness gap in the entry's own unfiltered_upload/DISALLOW_UNFILTERED_UPLOADS coverage, rather than finding a defect in this taxonomy). Status column updated from Planned to Existing, Production Ready. No boundary content changed; this entry required no revision to this taxonomy. | Frozen |
