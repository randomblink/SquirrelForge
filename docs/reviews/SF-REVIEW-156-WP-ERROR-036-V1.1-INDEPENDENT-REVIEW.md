# SF-REVIEW-156 — WP-ERROR-036 Version 1.1 Independent Review

# 1. Review Information

**Review ID:** SF-REVIEW-156

**Review Date:** 2026-07-15

**Reviewer:** Class B — Independent Review, per **SF-SPEC-012** Section 6.2, with preliminary findings recorded before comparison with `SF-REVIEW-155`.

**Status:** Complete

---

# 2. Artifact Reviewed

`WP-ERROR-036` Version 1.1, together with `WP-VERIFICATION-003` and the companion `SF-TAXONOMY-007` Version 1.5 correction.

---

# 3. Governing Specifications

- **SF-SPEC-013** Section 5.6
- **SF-SPEC-002** Sections 4.2, 4.4
- **SF-SPEC-012** Sections 6.2, 8

---

# 4. Preliminary Independent Findings (recorded before comparison with SF-REVIEW-155)

Rather than accept the verification record's or `SF-REVIEW-155`'s account, this review independently re-derived every underlying claim:

- **`wp_max_upload_size()` usage, independently re-grepped fresh:** exactly one call site, `wp-admin/includes/media.php:2128`, inside `media_upload_form()` — confirmed purely a display-text usage by direct re-read of the surrounding function.
- **`check_upload_size()` hook registration, independently re-read fresh:** `wp-admin/includes/ms-admin-filters.php` line 11, `add_filter( 'wp_handle_upload_prefilter', 'check_upload_size' )`.
- **Multisite gating, independently re-read fresh:** `wp-admin/includes/admin.php` lines 96-99, `ms-admin-filters.php` is `require_once`'d only inside `if ( is_multisite() ) { ... }` — confirming `check_upload_size()` is never registered on a single-site installation.
- **Independent live re-trigger, a fourth execution of this specific condition overall** (after the record's own trigger and this review's fresh server): a newly-constructed fixture, a freshly-started server process, and a *different* filtered `upload_size_limit` value (100 bytes, versus the record's own 500) — `wp_max_upload_size()` correctly returned `100`, yet `wp_handle_upload()` again succeeded unconditionally, writing the file and returning a normal result. Confirms the finding is not an artifact of the specific filter value chosen.
- **Independent re-read of Causes 1/2's preserved text:** confirmed byte-for-byte identical to Version 1.0 in every section, corroborating `SF-REVIEW-155`'s claim that the correction did not incidentally alter already-confirmed-accurate content.
- **Independent re-read of all nine corrected sections** (4, 6, 7, 8, 9, 10, 11, 12, 14, 17) for internal consistency: no passage found still implying generic, single-site `wp_max_upload_size()` enforcement.

---

# 5. Comparison with SF-REVIEW-155

`SF-REVIEW-155`'s findings are independently corroborated. This review's own fourth independent execution (a different filter value, a fresh fixture, a fresh server process) reached the identical outcome, strengthening the determinism claim beyond what either the verification record or the author review alone established.

---

# 6. Findings

| Finding ID | Severity | Requirement or Criterion | Observation | Evidence | Required Action | Resolution Status |
|---|---|---|---|---|---|---|
| — | Conforming | Source-claim accuracy | Independently re-derived from fresh reads; matches the correction exactly. | Section 4 above. | None. | N/A |
| — | Conforming | SF-SPEC-002 §4.2 Determinism | Fourth independent execution (distinct filter value) reached the identical outcome. | Section 4 above. | None. | N/A |
| — | Conforming | Causes 1/2 preservation | Independently confirmed unmodified. | Section 4 above. | None. | N/A |
| — | Conforming | Internal consistency | Independently re-read all nine corrected sections; no stale passage found. | Section 4 above. | None. | N/A |

No Minor, Major, or Critical findings.

---

# 7. Outcome

**Approved.**

**Basis:** every claim independently re-derived from primary source and a fresh, differently-parameterized live re-execution, reaching the same conclusion as both `WP-VERIFICATION-003` and `SF-REVIEW-155` without relying on either account.

---

# 8. Gate Decision

`WP-ERROR-036` Version 1.1 complete. Per **SF-SPEC-013** Section 5.6, proceeds to a new Media category consistency review (`SF-REVIEW-157`) and baseline re-certification (`SF-REVIEW-158`).

---

# 9. Remaining Risks

- Same-agent authorship/review limitation.
- Scoped to WordPress 7.0.1 and single-site; the multisite side of Cause 3 (`check_upload_size()`'s own actual enforcement) was verified by source reading only, not by a live multisite trigger — no `WP-VERIFICATION-XXX` record in this catalog has yet tested a multisite installation directly.

---

# 10. Revision History

| Version | Date | Summary of Changes | Approval Status |
|---|---|---|---|
| 1.0 | 2026-07-15 | Initial independent review of WP-ERROR-036 Version 1.1. Independently re-derived every claim from fresh source reads and a fourth, differently-parameterized live re-execution. No findings. Approved. Proceeds to category consistency review and re-certification. | Approved |
