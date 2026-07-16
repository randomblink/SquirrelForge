# WP-ERROR-038 — WordPress Image Processing Failure

---

# 1. Knowledge Entry

WordPress Image Processing Failure

---

# 2. Metadata

* **Error ID:** `WP-ERROR-038`
* **Title:** WordPress Image Processing Failure
* **Category:** Media
* **Severity:** Critical
* **Recovery Priority:** Immediate
* **Status:** Production Ready
* **Version:** 1.0

---

# 3. Summary

An image file that has already passed every earlier stage of the upload pipeline — the size gate, the file-type gate, and the filesystem write itself — fails during WordPress's own subsequent image-processing step: `WP_Image_Editor`, backed by a confirmed-present `gd` or `imagick` extension, fails to generate one or more of the site's own configured intermediate sizes, or `wp_generate_attachment_metadata()` fails to produce complete attachment metadata. The original file and its attachment record remain in the Media Library regardless; this entry's condition is specifically that the processing step run against that already-accepted file did not complete successfully.

---

# 4. Primary Failure Mode

Once an image file has been fully accepted — passing the size gate (`WP-ERROR-036`), the file-type gate (`WP-ERROR-037`), and the filesystem write (`WP-ERROR-019`/`020`) — WordPress calls `wp_generate_attachment_metadata()`, which uses `WP_Image_Editor` (backed by whichever of the `gd` or `imagick` extensions is available and selected) to generate the site's own configured intermediate sizes (thumbnail, medium, large, and any custom sizes registered via `add_image_size()`) and to assemble the attachment's own metadata. This entry's condition occurs when that specific step — presuming the required extension is genuinely available, a precondition this entry does not itself own — fails to complete successfully for the file in question. Unlike the two earlier pipeline stages, a failure here does not remove the file from the Media Library at all: the attachment post and its original, full-size file remain exactly as uploaded, while the intermediate sizes and metadata this step was responsible for producing are missing, incomplete, or — in the most severe manifestation — the processing attempt itself terminates PHP execution with a fatal error.

---

# 5. Severity

This entry is classified **Critical**, reasoned from the mechanism's own worst-case behavior rather than inherited from either of its two Media-category siblings:

- At its narrowest, the failure is contained to a single attachment: the original file remains usable, only its own intermediate sizes or metadata are missing or incomplete, and the rest of the site continues to function normally.
- At its most severe, processing a sufficiently large or complex source image can exhaust PHP's own available memory during decoding or resizing, producing a genuine PHP fatal error ("Allowed memory size of *N* bytes exhausted") — a direct, well-documented consequence of how image libraries decode raster data into memory before any resizing can occur, not a hypothetical edge case.
- This entry remains classified at the level of its most severe possible manifestation, consistent with the range-based Critical classification used elsewhere in this catalog (`WP-ERROR-021`, `024`–`033`, `035`) — deliberately not following `WP-ERROR-036`/`037`'s own High/High exception, since this entry's own worst case genuinely can include a fatal error, unlike either of those two entries' own conditions.

---

# 6. Distinction

This entry applies only when verified evidence establishes that an image file already fully accepted by the filesystem-write stage subsequently failed during WordPress's own image-processing step specifically — not that the file was rejected earlier in the pipeline, or that the required image-processing extension is unavailable at all.

**Three internal causes this entry keeps deliberately separate:**

1. **Corrupt or malformed source image data** — the file passed the file-type gate's own extension and content-type checks (`WP-ERROR-037`), but the actual image data within it is corrupted, truncated, or otherwise malformed in a way the specific editor library (`gd` or `imagick`) cannot successfully decode, even though the file is genuinely of the claimed type.
2. **Memory exhaustion during processing** — PHP's own available memory is exhausted while decoding or resizing the image, most commonly for an unusually large or high-resolution source file; WordPress's own `wp_raise_memory_limit( 'image' )` mechanism (filterable via `image_memory_limit`) attempts to raise the limit specifically for this context, but can itself be insufficient for a sufficiently large source image or a restrictive hosting-imposed hard ceiling.
3. **A specific format or capability the present `gd`/`imagick` build does not support** — the extension itself is genuinely available (this entry's own precondition), but the specific *build* in use lacks support for the source image's own particular format or a required operation.

It is distinct from:

- **`WP-ERROR-036` — WordPress Upload Size Limit Exceeded** and **`WP-ERROR-037` — WordPress Upload File Type Rejected**: both earlier gates in the same pipeline (per `SF-TAXONOMY-007` Section 4). This entry presumes the file already passed both.
- **`WP-ERROR-019` — WordPress Filesystem Permission Denied** and **`WP-ERROR-020` — WordPress Disk Space Exhausted**: where diagnosis confirms a specific intermediate-size file fails to *write* — as opposed to failing to *generate* — due to an OS-level permission or capacity constraint, that is a second, later instance of the same filesystem-write condition those two entries already own, distinct from this entry's own image-processing condition; this entry hands off to them for that specific root cause.
- **`WP-ERROR-014` — Required PHP Extension Missing**: owns the categorical, environment-wide question of whether `gd`/`imagick` is available to the runtime at all. This entry presumes the extension is present and owns the observable processing failure once invoked. This boundary requires the same particular care `WP-ERROR-029` Section 6 already establishes for an analogous overlap: `WP-ERROR-014`'s own Diagnosis Section 11 step 10 explicitly names "a `gd` build without a specific image format" as an example within its own territory — language that describes essentially the same condition as this entry's own cause 3. The distinction is scope, not mechanism: `WP-ERROR-014` owns a *categorical* gap — the runtime's own `gd`/`imagick` build cannot process a given format or operation *at all*, verified as an environment-wide limitation affecting every image of that kind — while this entry owns the *observable, file-specific* processing failure as the correct diagnostic entry point, including cases that, once fully root-caused, turn out to be that same categorical gap. Where diagnosis (Section 11) confirms the limitation is categorical rather than specific to one unusual source file, remediation escalates to `WP-ERROR-014`, the same escalation pattern already established for `WP-ERROR-029` and, informally, for `WP-ERROR-033`/`035`.
- **A source image's own content being technically valid but visually or semantically unexpected** (wrong colors, an unintended crop, an animated GIF losing its animation in a generated intermediate size, a characteristic that has historically applied to GD-based resizing specifically and has evolved across WordPress versions and editor implementations, rather than a single, fixed behavior): these are documented, working-as-designed WordPress behaviors, not failures this entry documents.

---

# 7. Scope

**Covered:** A verified condition in which an image file already fully accepted by the filesystem-write stage fails during WordPress's own subsequent image-processing step — failing to generate one or more configured intermediate sizes, failing to produce complete attachment metadata, or producing a PHP fatal error during the attempt — due to corrupt source data, memory exhaustion, or a build-specific format/capability limitation, presuming the required extension is genuinely available.

**Excluded:**

- The earlier size-limit and file-type gates (`WP-ERROR-036`/`037`).
- An intermediate-size file failing specifically to *write* due to an OS-level permission or capacity constraint (`WP-ERROR-019`/`020`).
- The categorical unavailability of the required `gd`/`imagick` extension itself (`WP-ERROR-014`).
- A technically successful processing result that is merely visually or semantically unexpected (a documented behavior, not a failure).

---

# 8. WordPress Components

Listed as components commonly involved, not as a claim that every installation exercises every one of them:

- `wp_generate_attachment_metadata()` (`wp-admin/includes/image.php`), the primary function responsible for generating an attachment's own intermediate sizes and metadata after upload.
- `WP_Image_Editor` and its two concrete implementations, `WP_Image_Editor_GD` and `WP_Image_Editor_Imagick`, selected via `wp_get_image_editor()` and the filterable `wp_image_editors` priority order.
- `add_image_size()`, through which a theme or plugin registers additional, custom intermediate sizes beyond WordPress core's own defaults (thumbnail, medium, large), each independently subject to this entry's own condition.
- `wp_raise_memory_limit( 'image' )` and the `image_memory_limit` filter, WordPress's own mechanism for requesting additional memory specifically for image-editing operations, distinct from PHP's own general `memory_limit` directive.
- The Media Library's own admin-screen display, which shows a broken-image placeholder or generic file icon for an attachment whose intermediate sizes are missing or incomplete, while the attachment itself remains present.

---

# 9. Typical Symptoms

- An uploaded image appearing in the Media Library with a broken-image placeholder or generic file icon instead of a thumbnail, while the original, full-size file remains accessible directly.
- A PHP fatal error referencing memory exhaustion, occurring specifically during or immediately after an image upload, for an unusually large or high-resolution source file.
- One or more, but not all, of a site's own configured intermediate sizes being missing for a specific attachment, while others generated successfully — pointing toward a size-specific resource or capability limit rather than a uniform processing failure.
- The identical source file processing successfully on one environment (a local development environment with a different `gd`/`imagick` build, or more available memory) but failing on another (production).
- A specific image format (for example, a newer or less common one) consistently failing to process across multiple, otherwise-unrelated files sharing that format, while other formats process normally — pointing toward cause 3 and warranting evaluation against `WP-ERROR-014`.
- The failure correlating with source file size or resolution crossing a specific threshold, reproducible across multiple otherwise-unrelated images — pointing toward memory exhaustion (cause 2).

---

# 10. Common Causes

Causes are grouped by category. Inclusion in this list identifies a category as plausible; it does not assert that any specific one is present without diagnostic confirmation.

- A source image file that is subtly corrupted or truncated — for example, from an interrupted transfer during a migration or import that predates the upload pipeline this entry's own siblings own.
- A source image with an unusually high resolution or uncompressed size, common with modern camera and phone output, exceeding what the current PHP `memory_limit` (even after `wp_raise_memory_limit()`'s own attempt to increase it) can accommodate.
- A restrictive, hosting-imposed hard ceiling on PHP memory that `wp_raise_memory_limit()`'s own request cannot exceed, regardless of what the site's own configuration requests.
- A `gd` or `imagick` build genuinely lacking support for the source image's own specific format (for example, certain builds lacking WebP or AVIF support) or a required operation, once confirmed categorical via `WP-ERROR-014`'s own diagnostic procedure.
- A theme or plugin registering an unusually large number of custom intermediate sizes via `add_image_size()`, multiplying the memory and processing cost of a single upload and increasing the likelihood of exhaustion for any given source file.
- A concurrent, high-volume bulk import or migration processing many images in rapid succession, cumulatively straining available memory or processing time in a way a single, isolated upload would not.

---

# 11. Diagnosis

Verify the following, starting from the least invasive observation and narrowing only once the general shape of the failure is established:

1. **Confirm the file was genuinely accepted by the filesystem-write stage** — the attachment exists in the Media Library and the original file is present and intact on disk — before concluding this entry's own condition applies, as opposed to an earlier pipeline stage (`WP-ERROR-036`/`037`) or the write itself (`WP-ERROR-019`/`020`).
2. **Capture the exact symptom**: a missing thumbnail/placeholder icon with no PHP fatal error (pointing toward causes 1 or 3), or a PHP fatal error specifically during or immediately after the upload (pointing toward cause 2, and requiring `WP-ERROR-013`'s own fatal-error capture procedure to locate precisely).
3. **Where a PHP fatal error is present, confirm it specifically references memory exhaustion** and correlates with the source file's own size or resolution, rather than assuming every post-upload fatal error is this entry's own cause 2 — apply `WP-ERROR-013`'s own diagnostic procedure to confirm the exact error before attributing it here.
4. **Independently verify the source file's own integrity** outside WordPress (for example, opening it in an independent image-viewing or editing tool) before concluding cause 1 (corruption) applies, since a file that opens correctly elsewhere but still fails WordPress's own processing points toward cause 2 or 3 instead.
5. **Where a specific format or operation is suspected (cause 3), confirm whether the limitation is categorical** — reproducible across every file of that format, using a minimal, independently-verified test file, not only the original file under investigation — before evaluating against `WP-ERROR-014`; a limitation specific to the one file under investigation points toward cause 1 (corruption) instead.
6. **Where an intermediate size is missing specifically due to a write failure rather than a generation failure**, distinguish the two by checking whether the specific expected file exists at all on disk with partial or zero content (pointing toward `WP-ERROR-019`/`020`) versus never being attempted (pointing toward this entry's own cause 1 or 2 having already stopped processing before that size was reached).
7. Preserve relevant evidence — the exact source file (or a copy), the specific missing sizes, the exact error where one exists, and the current `gd`/`imagick` build and available memory — before making any change.
8. Where the engineer performing diagnosis does not control PHP memory configuration or the installed image-library build, escalate to the hosting provider rather than attempting an unverified workaround.

---

# 12. Recovery Procedure

Recovery shall target the specific, verified cause identified in Diagnosis (Section 11).

Permitted recovery categories, depending on the verified cause, include:

- Replacing a confirmed-corrupted source file with a known-good copy and re-triggering attachment metadata regeneration (for example, via a metadata-regeneration tool or by re-uploading), where cause 1 is confirmed.
- Increasing the available PHP memory for image-processing contexts — via `wp_raise_memory_limit()`'s own filter, or the underlying PHP `memory_limit` directive where the hosting environment permits — scoped to what the site's own actual image sizes require, where cause 2 is confirmed and the current ceiling is genuinely insufficient rather than merely close.
- Converting or pre-processing unusually large source images before upload, or adjusting the number and size of configured intermediate sizes (`add_image_size()`), where raising memory further is not feasible and diagnosis confirms the resource demand itself is the limiting factor.
- Escalating to the hosting provider where a hard-imposed memory ceiling cannot be raised through site-level configuration alone.
- Where cause 3 is confirmed categorical, addressing the underlying `gd`/`imagick` build per `WP-ERROR-014`'s own recovery procedure (installing a build with the required format/capability support) rather than attempting to work around it at the WordPress level.
- Regenerating missing or incomplete metadata and intermediate sizes for the specific, already-affected attachment once the underlying cause is corrected, rather than assuming the fix alone retroactively repairs a previously-failed upload.

---

# 13. Validation

Recovery is successful when:

- The specific attachment's own intermediate sizes and metadata are confirmed complete, not merely that a subsequent, different upload succeeds.
- Where a PHP fatal error was the original symptom, it no longer occurs for a comparable source file (of similar size/resolution) across repeated, fresh upload attempts.
- Where memory was increased, the increase is confirmed sufficient for the site's own actual, legitimate image-size needs, not only the specific file used during testing.
- Where a build limitation (cause 3) was addressed, the specific format or operation previously failing now succeeds, confirmed against more than one file of that format.
- No unrelated attachment, PHP configuration, or image-library setting was altered as a side effect of the recovery.

---

# 14. Prevention

- Document the site's own actual, legitimate maximum source-image size and resolution, and provision PHP memory for image-processing contexts accordingly rather than relying solely on `wp_raise_memory_limit()`'s own default request.
- Verify the installed `gd`/`imagick` build's own supported formats against the site's own actual content needs (particularly newer formats such as WebP or AVIF) before depending on them in production.
- Limit the number and size of custom intermediate sizes registered via `add_image_size()` to what the site's own theme and workflow genuinely require, since each additional size multiplies the processing and memory cost of every upload.
- Validate source image file integrity as part of any bulk import or migration process, rather than discovering corruption only when WordPress's own processing fails.
- Monitor for image-processing-related PHP fatal errors proactively in production logs, rather than relying solely on an administrator noticing a missing thumbnail.

---

# 15. Security Considerations

- Image-processing libraries have historically been a source of security vulnerabilities when parsing maliciously-crafted files (memory-corruption or resource-exhaustion issues in the underlying `gd`/`imagick` library itself, distinct from this entry's own ordinary resource-exhaustion condition); keep the PHP runtime and its image extensions updated as part of routine maintenance, not only when a functional problem is observed.
- Do not disable memory limits entirely as a general response to processing failures; doing so removes a boundary that also limits the resource impact a maliciously crafted or resource-exhaustion-inducing image can impose.
- Treat a repeated pattern of processing failures specifically correlated with uploads from a single user or source as worth a brief check for a deliberate resource-exhaustion attempt, not only as routine capacity planning.

---

# 16. Related Errors

The following are cited as they exist in this repository, or as conceptual distinctions where noted.

1. [WP-ERROR-036 — WordPress Upload Size Limit Exceeded](WP-ERROR-036-UPLOAD-SIZE-LIMIT-EXCEEDED.md) and [WP-ERROR-037 — WordPress Upload File Type Rejected](WP-ERROR-037-UPLOAD-FILE-TYPE-REJECTED.md) — exist in this repository; see Section 6 (Distinction) above.
2. [WP-ERROR-019 — WordPress Filesystem Permission Denied](WP-ERROR-019-FILESYSTEM-PERMISSION-DENIED.md) and [WP-ERROR-020 — WordPress Disk Space Exhausted](WP-ERROR-020-DISK-SPACE-EXHAUSTED.md) — exist in this repository; see Section 6 (Distinction) above for the diagnose-then-hand-off relationship at this later pipeline stage.
3. [WP-ERROR-014 — Required PHP Extension Missing](WP-ERROR-014-REQUIRED-PHP-EXTENSION-MISSING.md) — exists in this repository; see Section 6 (Distinction) above for the categorical-versus-observable boundary, the same pattern already established for `WP-ERROR-029`.
4. [WP-ERROR-013 — WordPress Bootstrap PHP Fatal Error](WP-ERROR-013-WORDPRESS-BOOTSTRAP-PHP-FATAL-ERROR.md) — exists in this repository; see Section 11 (Diagnosis) above for the fatal-error-capture relationship.

---

# 17. Notes

This entry documents the general, verified observable condition of WordPress's own image-processing step failing for an already-accepted file, distinguishing the three mechanically distinct causes — corrupt source data, memory exhaustion, and a build-specific format limitation — at which that failure can occur. It is the third and final entry drafted against `SF-TAXONOMY-007`, completing the category's own planned three-entry sequential pipeline.

This entry's own boundary against `WP-ERROR-014` was drawn with the same care `WP-ERROR-029` Section 6 already established for an analogous overlap, identified proactively during this entry's own drafting rather than discovered only during review: `WP-ERROR-014`'s own Diagnosis text already names "a `gd` build without a specific image format" as its own territory, requiring this entry's own cause 3 to be scoped as the observable, file-specific entry point rather than the categorical capability question.

Consistent with the single-responsibility principle in **SF-SPEC-001** Section 4.3, this entry does not claim ownership of the earlier pipeline stages, the categorical PHP-extension-availability question, the general fatal-error-capture process, or a technically successful but visually unexpected processing result.

This entry reached `Production Ready` via `SF-REVIEW-110` (Class A author review; no findings) and `SF-REVIEW-111` (Class B independent review; one Minor finding — IF-1, a precision qualifier for the animated-GIF illustrative example, corrected within that same review) per **SF-SPEC-001** Section 19, **SF-SPEC-005** Section 5.6, and **SF-SPEC-012**.

No Reference Implementation is currently designated by **SF-SPEC-001**; this entry's relationship to that designation, and to any future `WP-SCENARIO-XXX` runtime evidence, is not asserted here and shall not be assumed until such evidence or designation actually exists.
