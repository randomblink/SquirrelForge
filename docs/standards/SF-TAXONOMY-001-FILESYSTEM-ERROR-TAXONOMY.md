# SF-TAXONOMY-001 — Filesystem Error Taxonomy

## Document Information

**Document ID:** SF-TAXONOMY-001

**Title:** Filesystem Error Taxonomy

**Classification:** Engineering Taxonomy — a lightweight, informally-adopted planning artifact, distinct from an Engineering Specification (`SF-SPEC-XXX`), Template (`SF-TEMPLATE-XXX`), or Glossary. It is not governed by **SF-SPEC-005**'s review process and does not itself require an author/independent review pair; its purpose is to declare a category's planned entry set in advance, not to document WordPress behavior or engineering process.

**Status:** Frozen — the entry set in Section 3 is fixed until this document is deliberately revised (see Section 6). "Frozen" here means the same thing it does informally elsewhere in this catalog: a declared, stable state that a future revision may change, but that isn't casually reopened.

**Version:** 1.0

**Owner:** SquirrelForge

---

## 1. Purpose

Declare the Filesystem category's boundary and complete planned `WP-ERROR` entry set *before* any entry in it is authored, so a future completeness check (of the kind performed for the Database category in `SF-REVIEW-033`) can be judged against a document written in advance, rather than reconstructed after the fact from conceptual placeholders scattered across other entries.

This document does not itself contain any `WP-ERROR` knowledge content and is not cited as a `WP-ERROR` entry.

---

## 2. Category Boundary

**Filesystem** owns failures involving the operating system's filesystem itself and WordPress's interaction with it: file/directory existence and integrity, OS-level access permission, and storage capacity.

**Explicitly not owned by Filesystem:**

- Plugin-specific assets — Plugin category.
- Theme-specific assets — Theme category.
- Media-library application behavior beyond the underlying filesystem operation itself — no category currently owns this; out of scope until one is deliberately created.
- PHP runtime configuration (memory limits, required extensions) — PHP Runtime category.
- Database storage — Database category.

---

## 3. Planned Entries

| Entry | Title | Owns | Status |
|---|---|---|---|
| `WP-ERROR-016` | WordPress Core Files Missing or Corrupted | File integrity — content is missing, incomplete, or altered from its expected state | Existing, Production Ready |
| `WP-ERROR-019` | WordPress Filesystem Permission Denied | File accessibility — content is present and correct, but the OS denies the requested access | Planned |
| `WP-ERROR-020` | WordPress Disk Space Exhausted | Storage capacity — a write cannot be satisfied because the volume has no free space | Planned |

Nothing else is currently planned for this category. Any future addition to this table is a revision to this document (Section 6), not an ad hoc decision made while authoring an unrelated entry.

---

## 4. Ownership Rationale

The three entries above are chosen to be orthogonal — each answers a different question about a filesystem object, and no two can be true of the same observed failure at once:

- **016 (integrity):** Is the content what it's supposed to be?
- **019 (accessibility):** Given correct content, is the OS willing to grant the requested access to it?
- **020 (capacity):** Given a willing OS and a legitimate write attempt, is there room to complete it?

Together these cover the major OS-level filesystem failure classes a WordPress installation can encounter, without overlap between them.

---

## 5. Candidates Considered and Rejected

Two additional candidates were proposed and deliberately excluded from Section 3, to keep the taxonomy from re-fragmenting a single underlying cause into multiple entries:

- **"Direct Filesystem Method Unavailable" (the WP-CLI/wp-admin FTP-or-SSH credential prompt):** Not an independent failure mode. It is WordPress's own `WP_Filesystem` abstraction selecting a different write transport because its own direct-write capability test failed — a symptom, not a root cause. The underlying cause is ordinarily one of `WP-ERROR-019`'s own causes (an ownership or permission mismatch between the PHP process and the target files), occasionally a hosting-imposed restriction. This will be documented as a diagnosis-time symptom within `WP-ERROR-019` rather than promoted to its own entry.
- **"wp-content/uploads Directory Missing or Misconfigured":** Not a cohesive root cause of its own. Depending on the specific case, this is actually a missing-directory condition, a permission condition (`WP-ERROR-019`), a path-configuration mistake, a symlink problem, or a capacity condition (`WP-ERROR-020`) — five different underlying causes that happen to surface at the same filesystem location. The uploads directory will be used as a concrete example location within `WP-ERROR-019` and `WP-ERROR-020`'s own content, not given its own entry, unless a dedicated Media/Uploads category is deliberately created later.

---

## 6. Revision History

| Version | Date | Summary of Changes | Status |
|---|---|---|---|
| 1.0 | 2026-07-13 | Initial taxonomy: WP-ERROR-016 (existing) plus WP-ERROR-019 and WP-ERROR-020 (planned). "Direct Filesystem Method Unavailable" and "uploads directory misconfigured" considered and rejected as separate entries, per Section 5. | Frozen |
