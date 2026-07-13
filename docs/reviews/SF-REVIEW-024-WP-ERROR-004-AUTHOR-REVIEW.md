# SF-REVIEW-024 — WP-ERROR-004 Author Review

# 1. Review Information

**Review ID:** SF-REVIEW-024

**Review Date:** 2026-07-13

**Reviewer:** Class A — Author Review, per **SF-SPEC-012** Section 6.1. Performed by the same authoring process that drafted WP-ERROR-004, within the same work-order execution.

**Status:** Complete

Per **SF-SPEC-012** Section 6.1, this Class A review may identify and correct defects but shall not, by itself, authorize a **Production Ready** designation. For purposes of this review, "Approved" means the artifact is ready to proceed to independent (Class B) review, not that any lifecycle promotion is authorized.

---

# 2. Artifact Reviewed

`WP-ERROR-004` — WordPress Database Permission Denied, Version 1.0, at `docs/knowledge/wp-errors/WP-ERROR-004-DATABASE-PERMISSION-DENIED.md`. Status at time of this review: Draft.

---

# 3. Governing Specifications

- **SF-SPEC-001 — Error Knowledge Specification**
- **SF-SPEC-012 — Engineering Review Independence Specification** (for this review's own classification and authority)
- **SF-TEMPLATE-004 — WP-ERROR Knowledge Template**
- **SF-GLOSSARY-001 — Engineering Terminology**

---

# 4. Review Scope

This review evaluates whether WP-ERROR-004, as drafted, satisfies the governing direction's failure boundary (server reachable, authentication succeeds, database exists, privileges insufficient, distinguished from WP-ERROR-002, 003, 007, 008, 009, and 018) and SF-SPEC-001's authoring standards, and whether it is ready to proceed to independent review. It does not authorize Production Ready.

---

# 5. Review Criteria

- SF-SPEC-001 Section 5 (Required Document Structure), Section 6 (Metadata Standard), Section 7 (Category Standard)
- SF-SPEC-001 Section 4.3 (Single Responsibility), Section 9 (Writing Standard), Section 10 (Scope Standard)
- The governing direction's explicit boundary: server reachable, authentication succeeds, target database exists, access denied because the authenticated account lacks required privileges

---

# 6. Work-Order Basis Note

This entry's governing direction was a recommendation describing the four-condition boundary, rather than a fully itemized formal work order, consistent with the precedent established for WP-ERROR-003 and the user's explicit authorization to self-author missing formal details in that manner. This review evaluates the resulting entry against SF-SPEC-001 and the established boundary in the same manner as every other entry in this catalog.

---

# 7. Precondition Verification

Before authoring, a repository-wide search (file search and full `git log --all --diff-filter=A --name-only` history scan) confirmed that WP-ERROR-009 does not exist, or has ever existed, in this repository. It is cited in this entry as a conceptual reference only, explicitly disclosed as non-existent, with no link. `WP-ERROR-002`, `WP-ERROR-003`, `WP-ERROR-007`, `WP-ERROR-008`, and `WP-ERROR-018` were confirmed to exist and are correctly cited with real links.

---

# 8. Scope-Design Note

Unlike prior entries in this cluster, this entry deliberately covers two distinct manifestations of the same underlying cause — an account with no privileges at all on the database (MySQL/MariaDB error 1044, sharing WP-ERROR-003's database-selection failure point) and an account with partial privileges (errors 1142/1143, a later, operation-specific failure) — within a single document, rather than splitting them into separate entries. This mirrors the precedent set by WP-ERROR-007, which covers both server-wide (`max_connections`) and per-account (`MAX_USER_CONNECTIONS`) connection-limit exhaustion as one cohesive failure mode. This review confirms that treatment is consistent with SF-SPEC-001 Section 4.3 (Single Responsibility), since both manifestations share the same root cause category (an insufficient grant) and only differ in how much has been granted.

---

# 9. Evidence Examined

- Full contents of `WP-ERROR-004-DATABASE-PERMISSION-DENIED.md`, read in full both before and after correction.
- `grep -Ein 'TODO|TBD|placeholder|future work|planned|should consider|to be determined|intended to be added'` (no match).
- `grep -n '\bmust\b' | grep -v "must-use"` (no match).
- `grep -c '^# [0-9]\+\.'` (17, matching SF-TEMPLATE-004).
- `git diff --check` (clean).
- Independent verification of technical claims before inclusion: MySQL/MariaDB error 1044 ("Access denied for user ... to database ...") as the standard error when an account has no privileges on a database, and that this specific client-visible failure is deliberately indistinguishable from a nonexistent database at the `USE`/select-database step; MySQL/MariaDB errors 1142 ("<command> command denied ... for table ...") and 1143 (column-level) as the standard errors for a specific, ungranted command against an otherwise-selectable database; that WordPress Multisite's "Add New Site" action performs `CREATE TABLE` for the new site's tables and is a real, commonly-reported trigger for this exact condition under an account lacking `CREATE`.

---

# 10. Findings

| Finding ID | Severity | Observation | Correction |
|---|---|---|---|
| F-1 | Minor | Diagnosis item 3 directed running `SHOW GRANTS` but did not name a WP-CLI-native alternative, continuing a concrete-tooling gap this catalog has consistently corrected in prior entries (for example, WP-ERROR-003's `wp db query` addition). | Added `wp db query "SHOW GRANTS"` as a WP-CLI-native alternative when direct database-client access is unavailable. |
| F-2 | Minor | Common Causes' hosting-default-account bullet lacked a concrete, searchable real-world trigger for the missing-schema-privilege scenario. | Added WordPress Multisite's "Add New Site" action (`CREATE TABLE` for new site tables) as a concrete example. |
| — | Conforming | Failure boundary matches the governing direction exactly: owns only insufficient privileges after connection, authentication, and database existence are all confirmed; excludes WP-ERROR-002 (credentials rejected, earlier), WP-ERROR-003 (database itself absent, requiring independent administrative verification given the shared client-visible failure), WP-ERROR-007/008 (connection never granted), and WP-ERROR-009 (post-privilege query timeout). | None. |
| — | Conforming | The dual-manifestation scope design (no privileges at all vs. partial privileges) is technically sound and consistent with the single-responsibility precedent set by WP-ERROR-007 (Section 8 above). | None. |
| — | Conforming | The central distinguishing technical fact against WP-ERROR-003 — that MySQL/MariaDB deliberately produces the same client-visible database-selection failure whether the database does not exist or the account has zero privileges over it — is accurate and correctly requires independent, administrative-level verification to resolve, mirroring the established WP-ERROR-002 error-1045 ambiguity pattern. | None. |
| — | Conforming | Recovery and Security Considerations correctly require granting only the specific, minimum privileges confirmed necessary, avoiding broad or server-wide grants as a shortcut. | None. |
| — | Conforming | Structure: all 17 SF-TEMPLATE-004 sections present, in order, sequentially numbered, none empty. | None. |
| — | Conforming | Related Errors: the one conceptual citation (WP-ERROR-009) correctly disclosed as non-existent with no link; WP-ERROR-002, 003, 007, 008, and 018 correctly linked; all six ordered numerically. | None. |

---

# 11. Recommendations

None beyond the corrections already applied.

---

# 12. Outcome

**Approved (for purposes of proceeding to independent review only).**

**Basis:** Both findings were narrow — concrete-tooling and concrete-example gaps — each corrected within this same review. No boundary, distinction, diagnostic-safety, recovery-safety, or structural defect was found. This outcome does not authorize Production Ready; per SF-SPEC-012 Section 6.1, a Class A review cannot do so regardless of its outcome.

WP-ERROR-004 remains `Draft`.

---

# 13. Revision History

| Version | Date | Summary of Changes | Approval Status |
|---|---|---|---|
| 1.0 | 2026-07-13 | Initial author review of WP-ERROR-004. Two Minor findings identified and corrected within this review. Confirmed WP-ERROR-009 does not exist; confirmed WP-ERROR-002, 003, 007, 008, 018 exist and are correctly linked. Confirmed the dual-manifestation scope design is consistent with SF-SPEC-001 single-responsibility, mirroring WP-ERROR-007's precedent. | Approved (Class A; does not authorize Production Ready) |
