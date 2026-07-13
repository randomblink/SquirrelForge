# SF-TAXONOMY-001 — Filesystem Error Taxonomy

## Document Information

**Document ID:** SF-TAXONOMY-001

**Title:** Filesystem Error Taxonomy

**Classification:** Engineering Taxonomy — a lightweight, informally-adopted planning artifact, distinct from an Engineering Specification (`SF-SPEC-XXX`), Template (`SF-TEMPLATE-XXX`), or Glossary. It is not governed by **SF-SPEC-005**'s review process and does not itself require an author/independent review pair; its purpose is to declare a category's planned entry set in advance, not to document WordPress behavior or engineering process.

**Status:** Frozen — the entry set in Section 3 is fixed until this document is deliberately revised (see Section 6). "Frozen" here is an informal, self-defined term describing this document's own adopted-plan state; it is not a claim of the `Version Frozen` WP-ERROR lifecycle stage defined by **SF-SPEC-001** Section 18, nor of any status in the closed list **SF-SPEC-008** Section 6 defines for versioned engineering artifacts. This document carries a `Version` and `Revision History` for traceability only; it does not present itself as a "versioned engineering artifact" within **SF-SPEC-008**'s own scope (Section 2.1), in the same way `FRAMEWORK-OBSERVATIONS.md` explicitly disclaims being versioned. No conflict with either specification's own status vocabulary is intended or created.

**Version:** 1.1

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
- WordPress configuration failures — `wp-config.php` missing, invalid, or containing a PHP syntax error (`WP-ERROR-010`/`011`/`012`, conceptual references; see `WP-ERROR-016` Section 6) are Bootstrap/Configuration-category conditions. `wp-config.php` is a site-specific file that happens to live on the filesystem, but its absence or invalid content is not itself a filesystem-integrity, accessibility, or capacity condition unless the specific, verified cause is one of this category's own three (for example, the file cannot be read due to a permission constraint — `WP-ERROR-019`).
- HTTP or web-server failures — a web-server-level failure to serve a request (for example, a `.htaccess` directive rejected by the server, a misconfigured virtual host, or a `mod_rewrite` routing failure) is a web-server-configuration condition, not a Filesystem one, even though the offending file (`.htaccess`) resides on the filesystem. This category owns whether the file itself can be read/written by WordPress, not whether the web server correctly interprets its contents.
- Authentication or deployment-tool behavior — credential rejection by an FTP, SFTP, or SSH server when WordPress or a deployment tool attempts to authenticate to a remote filesystem is an authentication condition, not a Filesystem one. This category owns the OS-level filesystem outcome once access is attempted (existence, permission, capacity), not whether a deployment mechanism's own credentials were accepted.

---

## 3. Planned Entries

| Entry | Title | Owns | Status |
|---|---|---|---|
| `WP-ERROR-016` | WordPress Core Files Missing or Corrupted | File integrity — content is missing, incomplete, or altered from its expected state | Existing, Production Ready |
| `WP-ERROR-019` | WordPress Filesystem Permission Denied | File accessibility — an existing file or directory cannot be read, written, or executed because the OS denies the requested access, **or** a required path does not exist and cannot be created because of a permission constraint on an ancestor directory | Planned |
| `WP-ERROR-020` | WordPress Disk Space Exhausted | Storage capacity — a write cannot be satisfied because the volume has no free space, **or** because an applicable filesystem quota or inode limit has been reached even though raw byte capacity remains | Planned |

Nothing else is currently planned for this category. Any future addition to this table is a revision to this document (Section 6), not an ad hoc decision made while authoring an unrelated entry.

---

## 4. Ownership Rationale

The three entries above are chosen to be orthogonal — each answers a different question about a filesystem object, and no two can be true of the same observed failure at once:

- **016 (integrity):** For a file that is part of the official WordPress core release, is its content what it's supposed to be?
- **019 (accessibility):** Is the OS willing to grant the requested access — including the access needed to create a path that does not yet exist?
- **020 (capacity):** Given a willing OS and a legitimate write attempt, is there room to complete it — whether measured in raw bytes, quota, or available inodes?

Together these cover the major OS-level filesystem failure classes a WordPress installation can encounter, without overlap between them. `WP-ERROR-020` explicitly excludes PHP- or WordPress-configuration-imposed upload-size limits (`upload_max_filesize`, `post_max_size`, or the `upload_size_limit` filter): those reject an upload before it reaches the filesystem regardless of actual available capacity, and are a PHP Runtime/Configuration condition, not a storage-capacity one.

---

## 5. Candidates Considered and Rejected

Two additional candidates were proposed and deliberately excluded from Section 3, to keep the taxonomy from re-fragmenting a single underlying cause into multiple entries:

- **"Direct Filesystem Method Unavailable" (the WP-CLI/wp-admin FTP-or-SSH credential prompt):** Not an independent failure mode. It is WordPress's own `WP_Filesystem` abstraction selecting a different write transport because its own direct-write capability test failed — a symptom, not a root cause. The underlying cause is ordinarily one of `WP-ERROR-019`'s own causes (an ownership or permission mismatch between the PHP process and the target files, or a target path that does not yet exist and cannot be created for the same reason), occasionally a hosting-imposed restriction. This will be documented as a diagnosis-time symptom within `WP-ERROR-019` rather than promoted to its own entry.
- **"wp-content/uploads Directory Missing or Misconfigured":** Not a cohesive root cause of its own. Depending on the specific case, this is actually a missing-directory condition now explicitly within `WP-ERROR-019`'s own boundary (see Section 3), a permission condition on an existing directory (also `WP-ERROR-019`), a path-configuration mistake in the `UPLOADS` constant (a Configuration-category concern, not Filesystem), a symlink problem (`WP-ERROR-019`), or a capacity condition (`WP-ERROR-020`) — several different underlying causes, spanning more than one category, that happen to surface at the same filesystem location. The uploads directory will be used as a concrete example location within `WP-ERROR-019` and `WP-ERROR-020`'s own content, not given its own entry, unless a dedicated Media/Uploads category is deliberately created later.

---

## 6. Revision History

| Version | Date | Summary of Changes | Status |
|---|---|---|---|
| 1.0 | 2026-07-13 | Initial taxonomy: WP-ERROR-016 (existing) plus WP-ERROR-019 and WP-ERROR-020 (planned). "Direct Filesystem Method Unavailable" and "uploads directory misconfigured" considered and rejected as separate entries, per Section 5. | Frozen |
| 1.1 | 2026-07-13 | Corrected per `SF-REVIEW-034` (independent review): clarified this document's non-versioned status relative to SF-SPEC-008; added three missing Category Boundary exclusions (Configuration, HTTP/web-server, Authentication/deployment-tool behavior); broadened WP-ERROR-019's declared boundary to explicitly include a required-but-missing path blocked by a permission constraint; broadened WP-ERROR-020's declared boundary to include quota/inode exhaustion and explicitly exclude PHP upload-size limits; updated the rejected-candidates reasoning to stay consistent with the broadened boundaries. | Frozen |
