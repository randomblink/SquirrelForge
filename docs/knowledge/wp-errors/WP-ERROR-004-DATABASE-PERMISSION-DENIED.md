# WP-ERROR-004 — WordPress Database Permission Denied

---

# 1. Knowledge Entry

WordPress Database Permission Denied

---

# 2. Metadata

* **Error ID:** `WP-ERROR-004`
* **Title:** WordPress Database Permission Denied
* **Category:** Database
* **Severity:** Critical
* **Recovery Priority:** Immediate
* **Status:** Production Ready
* **Version:** 1.0

---

# 3. Summary

WordPress reaches the configured database server, authenticates successfully, and the named database exists, but the connecting account's privileges are insufficient for WordPress to operate against it — either because no privileges at all are granted on the database, or because only some of the privileges WordPress requires have been granted. This is one specific, verified cause within the general connection-failure condition documented by WP-ERROR-018.

---

# 4. Primary Failure Mode

WordPress's database layer (`wpdb`) completes a network connection to the configured database server, the server accepts the supplied credentials, and the named database exists — all three steps succeed — but the connecting account lacks sufficient privileges over that database. This entry covers two distinct manifestations of the same underlying cause (an insufficient grant): an account with no privileges at all on the database, which fails at the same database-selection step WP-ERROR-003 covers and produces the same client-visible outcome; and an account with only partial privileges, which succeeds at connecting and selecting the database but fails later, when a specific operation requires a privilege (for example, `INSERT`, `CREATE`, `ALTER`, or `DROP`) that was not granted.

---

# 5. Severity

This entry is classified **Critical**, though its actual impact ranges depending on which manifestation is present:

- Where the account has no privileges at all on the database, the impact is a full outage identical in scope to WP-ERROR-003: no request path can complete, since the database cannot be selected at all.
- Where the account has only partial privileges, ordinary browsing and unaffected operations may continue to function while specific, often essential operations (saving content, installing or updating plugins and themes, running scheduled tasks) fail, which is a narrower but still frequently severe impact.
- In either manifestation, remediation cannot be deferred once identified, since the affected functionality remains unavailable until the account's privileges are corrected.

---

# 6. Distinction

This entry applies only when verified evidence establishes that the database server was reached, credentials were accepted, and the named database exists, but the connecting account's privileges — whether entirely absent for the database or only partially sufficient — prevent WordPress from performing an operation it requires.

It is distinct from:

- **WP-ERROR-002 — WordPress Database Authentication Failure**: the credentials themselves are rejected, before database selection is ever reached. This entry presumes credentials were accepted; the failure here is a subsequent privilege check, not a rejection of who is connecting.
- **WP-ERROR-003 — Database Does Not Exist**: the named database itself does not exist. This is the most important distinction to verify carefully: MySQL/MariaDB's client-visible failure when selecting a database is the same whether the database does not exist or the connecting account has no privileges over it at all — both fail identically at the same step, deliberately, so that an unprivileged connection cannot use the failure itself to confirm whether a given database exists. The two conditions require independent, administrative-level verification to distinguish (for example, an administrator confirming the database's existence directly), not inference from the WordPress-visible symptom alone — the same class of deliberate ambiguity already documented for WP-ERROR-002's credential-rejection message.
- **WP-ERROR-007 — Database Connection Limit Exceeded**: the server refuses the connection itself due to exhausted capacity, before authentication or database selection is reached at all. This entry presumes a connection was successfully granted.
- **WP-ERROR-008 — WordPress Database Server Unreachable**: the network path to the server itself fails, so the server is never reached and neither authentication nor a privilege check is ever attempted.
- **WP-ERROR-009 — Database Query Timeout**: presumes the account's privileges are sufficient and a query is actually executing, with the failure occurring because that query does not complete in time. Privilege sufficiency is not in question there.
- **WP-ERROR-018 — WordPress Database Connection Failure**: WP-ERROR-018 owns the general, verified-but-unspecified-cause condition where WordPress cannot establish a database connection, and explicitly identifies insufficient privileges as one specific, verified cause that belongs to this entry once confirmed. This entry owns that specific, verified cause; WP-ERROR-018 owns the general condition and the diagnostic process for narrowing it to a specific cause, along with this entry's relationship to WP-ERROR-013, WP-ERROR-014, and WP-ERROR-016, which this entry does not restate.

---

# 7. Scope

**Covered:** A verified condition in which the database server was reached, credentials were accepted, and the named database exists, but the connecting account's privileges are insufficient — whether entirely absent for the database, preventing even database selection, or present only partially, permitting selection and some operations while denying a specific required command — for WordPress to perform an operation it requires.

**Excluded:**

- Network-level unreachability of the database server (see WP-ERROR-008).
- Authentication rejection of the supplied credentials, before database selection is attempted (see WP-ERROR-002).
- Connection refusal due to the server's connection limit, before database selection is attempted (see WP-ERROR-007).
- The named database genuinely not existing, verified independently of the connecting account's own privilege level (see WP-ERROR-003).
- Query timeouts occurring when the account's privileges are sufficient and a query is actually executing (see WP-ERROR-009).
- Any condition in which the connecting account's privileges are sufficient for the operation being performed, regardless of what happens afterward.

---

# 8. WordPress Components

Listed as components commonly involved, not as a claim that every installation exercises every one of them identically:

- The `wpdb` class (`wp-includes/class-wpdb.php`), specifically: its database-selection step, shared with WP-ERROR-003, since an account with no privileges at all on the target database fails at this same step with the same client-visible outcome as a nonexistent database; and its query-execution and error-handling logic (`wpdb::query()`, the `$wpdb->last_error` property, and `wpdb::print_error()`), which surfaces a later, operation-specific privilege denial as an inline "WordPress database error" message when error display is enabled, rather than a bootstrap-blocking failure.
- The database server's own privilege and grant system (for example, MySQL's `GRANT`/`REVOKE` statements and its underlying grant tables), which determines exactly which operations a specific account may perform against a specific database.
- Plugin and theme installation, activation, and update routines, which commonly require `CREATE`, `ALTER`, `DROP`, and `INDEX` privileges for schema changes, distinct from the `SELECT`, `INSERT`, `UPDATE`, and `DELETE` privileges ordinary content operations require.
- WP-CLI's own database commands, which depend on the same underlying grant system as web requests.

---

# 9. Typical Symptoms

- Where the account has no privileges at all on the database: the same WordPress-internal "Can't select database" message and behavior documented in WP-ERROR-003, since MySQL/MariaDB's client-visible failure for selecting a database is the same whether the database does not exist or the account lacks privileges over it — the two are only distinguishable using administrative-level verification, not from the WordPress-visible symptom alone.
- Where the account has only partial privileges: ordinary browsing and unaffected operations continuing to work normally while a specific action fails — for example, a post or comment failing to save, a plugin or theme failing to activate or update, or a scheduled task failing — often accompanied by an inline "WordPress database error: [error text] for query [query]" message when error display is enabled.
- A database-level error such as MySQL/MariaDB error `1044` ("Access denied for user ... to database ..."), `1142` ("<command> command denied to user ... for table ..."), or `1143` (a column-level variant), visible in logs where accessible.
- The same account succeeding for some operations (for example, viewing content) while consistently failing for others (for example, saving content or installing a plugin), rather than failing uniformly across all requests.
- A site that previously worked failing immediately after a security-hardening change, a migration to a new environment, or a database-administrator-initiated privilege revocation.
- `SHOW GRANTS` for the connecting account, run with sufficient administrative access, showing fewer privileges than WordPress's actual requirements for the specific failing operation.

---

# 10. Common Causes

Causes are grouped by category. Inclusion in this list identifies a category as plausible; it does not assert that any specific cause is present without diagnostic confirmation.

- The database user account granted no privileges at all on the target database — MySQL/MariaDB error 1044.
- A partially privileged account — for example, a read-only account (`SELECT` only, common for reporting, analytics, or replica accounts, or as an overly cautious hosting default) — lacking `INSERT`, `UPDATE`, `DELETE`, `CREATE`, `ALTER`, `DROP`, or `INDEX` privileges WordPress requires for normal operation, producing MySQL/MariaDB error 1142 when a specific operation requiring an ungranted privilege is attempted.
- Privileges granted for a different host pattern than the one the connection actually originates from, so the grant lookup resolves to no matching grant at all — a variant of the host-scoped grant mismatch already documented for WP-ERROR-002, manifesting here as privilege denial rather than credential rejection.
- A migration or environment-promotion process that copied the database and `wp-config.php` but did not correspondingly recreate the necessary grants for the new environment's database user.
- A hosting-provider default account configuration that grants fewer privileges than WordPress requires — for example, an account provisioned with only `SELECT`, `INSERT`, and `UPDATE`, but not `CREATE`, `ALTER`, or `DROP`, which WordPress needs for plugin and theme installation, updates, and schema changes. A frequently-reported concrete manifestation is WordPress Multisite's "Add New Site" action, which performs `CREATE TABLE` for the new site's tables and fails immediately under an account lacking `CREATE`.
- An intentional security-hardening measure (for example, revoking `DROP` or `ALTER` from the WordPress database account as a defense-in-depth practice) that inadvertently blocks a legitimate WordPress operation such as a plugin or theme update, or multisite table creation.
- A privilege revocation performed as part of an incident response or account cleanup that was not fully reconciled with WordPress's actual operational needs afterward.
- Database views, stored routines, or other database objects that a plugin or custom code depends on, requiring privileges (for example, `SHOW VIEW` or `EXECUTE`) that were not included when the account's grants were originally defined.

---

# 11. Diagnosis

Verify the following:

1. Confirm this is genuinely a privilege-denial condition — MySQL/MariaDB error 1044, 1142, or 1143 — rather than a connection failure, authentication rejection, or the database genuinely not existing.
2. Where the failure prevents database selection entirely, resembling WP-ERROR-003, do not assume the database does not exist: MySQL/MariaDB deliberately produces the same client-visible failure whether the target database does not exist or the connecting account has no privileges over it. Independently confirm, using an account known to have administrative visibility (for example, a database administrator's own connection, or `SHOW DATABASES` run with different, sufficiently privileged credentials), whether the database actually exists before concluding this entry applies rather than WP-ERROR-003.
3. Where server access is available, run `SHOW GRANTS` for the specific connecting account (matching both username and host) to determine exactly which privileges, if any, are actually granted on the target database. Where direct database-client access is not available but WP-CLI is, `wp db query "SHOW GRANTS"` runs this same check through WordPress's own configured connection.
4. Capture the exact error text and code, noting which specific command (`INSERT`, `CREATE`, `ALTER`, `DROP`, and so on) or table the error identifies as denied, since this indicates exactly which privilege is missing rather than a blanket absence of access.
5. Determine whether the failure blocks WordPress entirely (consistent with no privileges on the database at all) or only specific operations while ordinary browsing and other functionality continue working (consistent with a partially privileged account), since these point toward different scopes of correction.
6. Where WordPress surfaces an inline "WordPress database error" message rather than failing at bootstrap, confirm this is being triggered by the specific denied operation rather than an unrelated query defect.
7. Confirm the exact host portion of the account's grant (`'user'@'host'`) matches the host the connection actually originates from, since a grant scoped to a different host behaves as if no privileges exist at all for the actual connecting host.
8. Where a recent migration, environment promotion, or security-hardening change occurred, determine whether it altered or failed to carry forward the account's grants.
9. Confirm whether the affected operation corresponds to a WordPress core requirement (for example, `CREATE TABLE` during plugin activation, or `ALTER TABLE` during a core or plugin database upgrade) or an unusual operation a specific plugin performs, since this determines which specific privilege needs to be granted.
10. Preserve relevant evidence — the exact error text, the account's current grants, and timestamps — before making any change.
11. Where the engineer performing diagnosis does not control the database server or its grants, escalate to the database administrator or hosting provider rather than attempting an unverified workaround.

---

# 12. Recovery Procedure

Recovery shall grant the specific, minimum privileges WordPress actually requires for the failing operation, scoped to the specific database, rather than granting broad or server-wide privileges as a shortcut.

Permitted recovery categories, depending on the verified cause, include:

- Granting the specific privileges confirmed missing (for example, `CREATE`, `ALTER`, `DROP`, or `INDEX` for schema-related operations, or `INSERT`, `UPDATE`, `DELETE` for content operations), scoped to the specific database only.
- Correcting a host-scoped grant mismatch by granting privileges for the exact host the connection actually originates from, in coordination with the database administrator, mirroring the approach used for WP-ERROR-002's own host-mismatch recovery.
- Where a migration or environment promotion failed to carry forward necessary grants, recreating the correct grants for the new environment as part of completing that migration, rather than treating it as a one-off manual fix disconnected from the migration process.
- Where an intentional security-hardening measure is the cause, evaluating whether the specific WordPress operation genuinely requires the revoked privilege, and, where it is a one-time operation such as a major version upgrade, granting the privilege temporarily and reviewing whether it should be revoked again afterward, rather than leaving broad privileges permanently in place.
- Escalating to the database administrator or hosting provider where the engineer performing diagnosis does not control the account's grants.

Recovery shall not grant privileges more broadly than the specific operation requires — for example, granting all privileges, or access across all databases on the server — as an undisciplined shortcut to resolving a specific denied command; grant only the specific privilege or privileges confirmed necessary, scoped to the specific database.

Privilege changes shall be made using `GRANT` statements, which take effect immediately without further action. Direct edits to the server's underlying grant tables, bypassing `GRANT`, require an explicit `FLUSH PRIVILEGES` before taking effect at all, including for new connections; omitting this step after such an edit can leave the failure unchanged despite the grant appearing correct.

---

# 13. Validation

Recovery is successful when:

- The previously denied operation completes successfully, confirmed by reproducing the exact action that previously failed, not merely by confirming the connection succeeds.
- `SHOW GRANTS` for the account reflects only the specific, intended privileges added, without unintended broader access.
- No equivalent "Access denied" or "<command> command denied" error recurs in logs across repeated, fresh attempts at the same and related operations.
- Ordinary WordPress operations (front-end, administrative, content operations, and plugin or theme management as applicable) continue to function normally after the change.
- Any privilege granted temporarily for a one-time operation has been reviewed and, where appropriate, revoked again afterward.

---

# 14. Prevention

- Document the complete set of database privileges WordPress and its installed plugins and themes actually require, and provision new environments' database accounts against that documented set rather than an assumed or default minimal grant.
- Include a verification step confirming the database account's grants are sufficient as part of any migration, environment-promotion, or security-hardening procedure, rather than assuming grants carried forward automatically.
- Where security hardening intentionally restricts privileges, document the restriction and the process for temporarily restoring it when a legitimate operation requires it, so the restriction does not present as an unexplained failure later.
- Periodically review the WordPress database account's actual grants against what is genuinely required, removing privileges no longer needed and confirming privileges still required remain present.
- Monitor logs for privilege-denial errors proactively, rather than discovering them only when a user reports a specific broken feature.

---

# 15. Security Considerations

- Do not grant broader privileges than the specific, confirmed requirement — for example, all privileges, or privileges across all databases on the server — as a shortcut to resolving a specific denied command.
- Treat an unexpected, newly appearing privilege-denial error as a potential signal of an unauthorized or unintended grant change, not only as routine misconfiguration, particularly where no legitimate migration or hardening change explains it.
- Where a privilege is granted temporarily for a specific one-time operation, such as a major version upgrade requiring schema changes, revoke it again afterward rather than leaving it permanently in place.
- Coordinate privilege changes through a platform-appropriate, auditable process rather than ad hoc, undocumented grants directly on the production database server.

---

# 16. Related Errors

The following are cited as they exist in this repository, or as conceptual distinctions where noted.

1. [WP-ERROR-002 — WordPress Database Authentication Failure](WP-ERROR-002-WORDPRESS-DATABASE-AUTHENTICATION-FAILURE.md) — exists in this repository; see Section 6 (Distinction) above.
2. [WP-ERROR-003 — Database Does Not Exist](WP-ERROR-003-DATABASE-DOES-NOT-EXIST.md) — exists in this repository; see Section 6 (Distinction) above.
3. [WP-ERROR-007 — Database Connection Limit Exceeded](WP-ERROR-007-WORDPRESS-DATABASE-CONNECTION-LIMIT-EXCEEDED.md) — exists in this repository; see Section 6 (Distinction) above.
4. [WP-ERROR-008 — WordPress Database Server Unreachable](WP-ERROR-008-WORDPRESS-DATABASE-SERVER-UNREACHABLE.md) — exists in this repository; see Section 6 (Distinction) above.
5. [WP-ERROR-009 — Database Query Timeout](WP-ERROR-009-DATABASE-QUERY-TIMEOUT.md) — exists in this repository; see Section 6 (Distinction) above.
6. [WP-ERROR-018 — WordPress Database Connection Failure](WP-ERROR-018-WORDPRESS-DATABASE-CONNECTION-FAILURE.md) — exists in this repository; see Section 6 (Distinction) above.

---

# 17. Notes

This entry documents the specific, verified condition of insufficient database privileges on an otherwise reachable, authenticated, existing database, as one of several specific causes deferred by the general connection-failure entry, WP-ERROR-018. It does not restate WP-ERROR-018's own boundary, its relationship to WP-ERROR-013, WP-ERROR-014, or WP-ERROR-016, or the general diagnostic process for narrowing an unspecified connection failure to a specific cause; see WP-ERROR-018 for that content. Consistent with the single-responsibility principle in **SF-SPEC-001** Section 4.3, this entry covers both the no-privileges-at-all and partial-privileges manifestations of an insufficient grant as one cohesive failure mode, since both share the same underlying cause category; cause-specific conditions for individual hosting-platform default-grant configurations or individual plugins' unusual privilege requirements may each be documented by a separate, independently created `WP-ERROR` entry without altering this one.

This entry's governing direction was a recommendation describing the four-condition boundary (server reachable, authentication succeeds, database exists, privileges insufficient) rather than a fully itemized formal work order; per the user's explicit authorization established for WP-ERROR-003, the missing formal details (technical grounding, section requirements) were self-authored following this catalog's established practice.

This entry underwent the review sequence required by **SF-SPEC-001** Section 19, **SF-SPEC-005** Section 5.6, and **SF-SPEC-012**: an author (Class A) review at `docs/reviews/SF-REVIEW-024-WP-ERROR-004-AUTHOR-REVIEW.md`, followed by an independent (Class B) review at `docs/reviews/SF-REVIEW-025-WP-ERROR-004-INDEPENDENT-REVIEW.md`, which independently re-verified the non-existence of WP-ERROR-009, reached outcome **Approved with Minor Revisions**, applied and re-validated the one required revision, and satisfied the Production Ready gate per SF-SPEC-012 Section 12. Its Status was changed to Production Ready on that basis. This document does not itself constitute either review record; see the cited files for full findings, corrections, and gate decisions.

The independent review did not designate this entry as a Reference Implementation. That designation, governed separately by **SF-SPEC-001** Section 22, has not been sought or asserted here.

No Reference Implementation is currently designated by **SF-SPEC-001**; this entry's relationship to that designation, and to any future `WP-SCENARIO-XXX` runtime evidence, is not asserted here and shall not be assumed until such evidence or designation actually exists.
