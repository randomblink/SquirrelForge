# WP-ERROR-003 — WordPress Database Does Not Exist

---

# 1. Knowledge Entry

WordPress Database Does Not Exist

---

# 2. Metadata

* **Error ID:** `WP-ERROR-003`
* **Title:** WordPress Database Does Not Exist
* **Category:** Database
* **Severity:** Critical
* **Recovery Priority:** Immediate
* **Status:** Production Ready
* **Version:** 1.0

---

# 3. Summary

WordPress successfully connects to the configured MySQL or MariaDB server and successfully authenticates, but the specific database named by `DB_NAME` does not exist on that server, so WordPress cannot select a database to operate against. This is one specific, verified cause within the general connection-failure condition documented by WP-ERROR-018.

---

# 4. Primary Failure Mode

WordPress's database layer (`wpdb`) completes a network connection to the configured database server and the server accepts the supplied credentials — both steps succeed — but a distinct, subsequent step fails: `wpdb` attempts to select the specific database named by `DB_NAME`, and the server reports that no database by that name exists. This is a materially later and more specific failure point than connection establishment or authentication: WordPress's own internal handling of this condition is itself distinct, generating a specific "Can't select database" message rather than the generic "Error establishing a database connection" message used when connection establishment itself fails. As with that generic message, whether this specific text is actually visible to a visitor, or only recoverable from server-side or PHP-level logs, depends on the site's debug and error-display configuration; the distinguishing fact for this entry is the internal failure point (database selection, not connection establishment), not a guarantee of what any particular visitor sees.

---

# 5. Severity

This entry is classified **Critical** because, by definition, it covers a verified condition that leaves WordPress without any database to operate against:

- No later step (schema access, permission checks, querying) can be reached without a database first being successfully selected.
- The condition affects front-end, administrative, AJAX, cron, REST, and WP-CLI paths identically, since all depend on the same database-selection step.
- Remediation cannot be deferred, since the site provides no functioning request path while the named database remains unavailable.

---

# 6. Distinction

This entry applies only when verified evidence establishes that the database server was reached, credentials were accepted, and the specific named database itself does not exist on that server — not that the server was unreachable, credentials were rejected, connection capacity was exhausted, or the database exists but is inaccessible to the connecting account for another reason.

It is distinct from:

- **WP-ERROR-002 — WordPress Database Authentication Failure**: presumes the database being selected would otherwise be reachable, with the failure occurring earlier, when the supplied credentials themselves are rejected, before database selection is ever attempted.
- **WP-ERROR-004 — Database Permission Denied** (conceptual reference; no corresponding document currently exists in this repository): presumes the named database does exist, with the failure occurring because the authenticated user lacks privileges over it. This entry's condition is that the database itself is absent, not merely inaccessible to this account.
- **WP-ERROR-007 — Database Connection Limit Exceeded**: the server refuses the connection itself due to exhausted capacity, before authentication or database selection can be reached at all. This entry presumes a connection was successfully granted.
- **WP-ERROR-008 — WordPress Database Server Unreachable**: the network path to the server itself fails, so the server is never reached and neither authentication nor database selection is ever attempted.
- **WP-ERROR-009 — Database Query Timeout** (conceptual reference; no corresponding document currently exists in this repository): presumes a database was already successfully selected, with the failure occurring later because a specific query does not complete in time.
- **WP-ERROR-018 — WordPress Database Connection Failure**: WP-ERROR-018 owns the general, verified-but-unspecified-cause condition where WordPress cannot establish a database connection, and explicitly identifies the named database not existing as one specific, verified cause that belongs to this entry once confirmed. This entry owns that specific, verified cause; WP-ERROR-018 owns the general condition and the diagnostic process for narrowing it to a specific cause, along with this entry's relationship to WP-ERROR-013, WP-ERROR-014, and WP-ERROR-016, which this entry does not restate.

---

# 7. Scope

**Covered:** A verified condition in which the database server was reached, the supplied credentials were accepted, but the specific database named by `DB_NAME` does not exist on that server, preventing WordPress from selecting a database to operate against.

**Excluded:**

- Network-level unreachability of the database server (see WP-ERROR-008).
- Authentication rejection of the supplied credentials, before database selection is attempted (see WP-ERROR-002).
- Connection refusal due to the server's connection limit, before database selection is attempted (see WP-ERROR-007).
- A named database that does exist, but to which the authenticated user lacks sufficient privileges (see WP-ERROR-004).
- Query timeouts occurring after a database was already successfully selected (see WP-ERROR-009).
- Any condition in which the configured database is successfully selected, regardless of what happens afterward.

---

# 8. WordPress Components

Listed as components commonly involved, not as a claim that every installation exercises every one of them identically:

- The `wpdb` class (`wp-includes/class-wpdb.php`), specifically its database-selection step, which is a distinct operation from — and occurs after — its connection-establishment logic shared with WP-ERROR-018. Where this specific step fails, `wpdb` presents a dedicated "Can't select database" message rather than the generic "Error establishing a database connection" message used when connection establishment itself fails.
- The `DB_NAME` constant defined in `wp-config.php`, whose value determines which specific database `wpdb` attempts to select.
- The database server's own database catalog (for example, MySQL's `information_schema.SCHEMATA`, or the result of `SHOW DATABASES`), which determines whether a given name actually exists on the server.
- WP-CLI's own database-connectivity commands, which depend on the same underlying database-selection step as web requests.
- Hosting-platform or containerized-environment mechanisms that supply `DB_NAME` dynamically (for example, via an environment variable), where present.

---

# 9. Typical Symptoms

- A distinct WordPress-internal message — "Can't select database" — rather than the generic "Error establishing a database connection" message WP-ERROR-002, WP-ERROR-007, WP-ERROR-008, and WP-ERROR-018 share, since this entry's failure occurs after connection and authentication already succeeded, at a separate database-selection step. As with those entries' own generic message, whether this specific text is visible to a visitor or only recoverable from logs depends on the site's debug and error-display configuration.
- A database-level error such as MySQL/MariaDB error `1049` ("Unknown database '<name>'"), visible in logs where accessible.
- The same credentials succeeding when used to connect without specifying a database (for example, to run `SHOW DATABASES`), confirming that connectivity and authentication are not the issue.
- The failure occurring identically across front-end, administrative, AJAX, cron, REST, and WP-CLI paths, since all depend on the same database-selection step.
- A site that previously worked failing immediately after a database rename, a database drop, a restore performed under a different name, or a migration to a new environment where the corresponding database was not created or restored.
- `SHOW DATABASES`, run with the same credentials, not listing the name configured in `DB_NAME`, or listing a similarly named database that differs only in case or a small typo.

---

# 10. Common Causes

Causes are grouped by category. Inclusion in this list identifies a category as plausible; it does not assert that any specific cause is present without diagnostic confirmation.

- An incorrect `DB_NAME` value in `wp-config.php` — a typo, a stale value left over from a prior environment, or a value that does not match the actual database name.
- A database renamed or dropped on the server side without `wp-config.php` being updated to match.
- A migration, staging, or production environment mismatch, where `DB_NAME` differs between environments and `wp-config.php` was not updated for the target environment.
- A hosting migration or restore process that deployed the WordPress files but did not create or restore the actual database, or created it under a different name than `wp-config.php` expects (for example, an auto-generated database name from a hosting control panel).
- A database accidentally deleted — by an administrator, a cleanup script, or an automated pruning process — while `wp-config.php` still references it.
- A shared configuration deployed to a new environment before the corresponding database has actually been provisioned there.
- A case-sensitivity mismatch in `DB_NAME`, since database names are commonly case-sensitive on Linux-based database servers (where a database name maps to a directory on disk) even when the rest of the configuration was copied correctly.
- A managed hosting or containerized environment where `DB_NAME` is dynamically supplied via an environment variable, and a misconfigured or missing variable results in an empty or incorrect value.

---

# 11. Diagnosis

Verify the following:

1. Confirm this is genuinely a "database does not exist" condition — WordPress's own "Can't select database" message, or the underlying MySQL/MariaDB error 1049 ("Unknown database") — rather than a connection failure, authentication rejection, or connection-limit refusal that occurs before database selection is even attempted.
2. Confirm connectivity and authentication succeed independently of database selection — for example, by connecting with the same credentials without specifying a database, or running `SHOW DATABASES` — to isolate that the failure is specific to database selection rather than an earlier stage.
3. Confirm the exact `DB_NAME` value configured in `wp-config.php` (for example, via WP-CLI's `wp config get DB_NAME`, where WP-CLI is available).
4. Run `SHOW DATABASES` (or an equivalent listing) using the same credentials, to determine whether the named database actually exists on the server, and if not, whether a similarly named database exists, indicating a typo, a case mismatch, or a rename. Where WP-CLI is available and its own configured connection is functional, `wp db query` can run this same check through WordPress's own connection rather than requiring separate database-client access.
5. Where case sensitivity may be a factor, confirm the exact case of the actual database name on the server against `DB_NAME`'s case, since database name case-sensitivity depends on the specific server's operating system and configuration (for example, `lower_case_table_names`).
6. Determine whether this is a regression (previously working, now failing) or a new environment; a regression points toward an accidental drop, rename, or migration mismatch, while a new environment points toward the database never having been created or provisioned.
7. Where a recent migration, restore, or deployment occurred, confirm whether the database itself was actually created or restored as part of that process, distinct from the WordPress files having been deployed.
8. Where `DB_NAME` is supplied via an environment variable or a hosting-platform injection mechanism, confirm that mechanism is actually supplying the correct value rather than an empty or stale one.
9. Confirm whether the connecting database user account has privileges to see or use the intended database, distinguishing a genuinely nonexistent database from one that exists but is invisible to this account due to privilege restrictions — a condition belonging to WP-ERROR-004 once confirmed, not this entry.
10. Preserve relevant evidence — error messages, timestamps, and the exact configured and actual database names — before making any change.
11. Where the engineer performing diagnosis does not control the database server or hosting environment, escalate to the database administrator or hosting provider rather than attempting an unverified workaround.

---

# 12. Recovery Procedure

Recovery shall first determine whether the named database is expected to contain existing site data before choosing a corrective action, since creating a new, empty database under the configured name is not a substitute for restoring one that previously held data.

Permitted recovery categories, depending on the verified cause, include:

- Correcting `DB_NAME` in `wp-config.php` where diagnosis confirms it does not match the actual, intended database name (a typo, a stale value, or a case mismatch).
- Creating the missing database (for example, via WP-CLI's `wp db create`, or an equivalent hosting-provider or database-client action) where diagnosis confirms the intended database genuinely has never held site data — appropriate for a new installation, not as a way to silently resolve the loss of an existing site's data.
- Restoring the database from backup where diagnosis confirms an existing, populated database was accidentally dropped or otherwise lost, rather than creating an empty database in its place.
- Correcting a misconfigured or missing environment variable, or a hosting-platform injection mechanism, where diagnosis confirms that is the source of an incorrect `DB_NAME` value.
- Escalating to the database administrator or hosting provider where the engineer performing diagnosis does not control database creation, restoration, or the hosting environment's configuration mechanism.

Recovery shall not create a new, empty database under the configured name as a substitute for restoring an existing site's actual data; doing so would silently discard the site's content rather than recover it.

---

# 13. Validation

Recovery is successful when:

- WordPress successfully selects the configured database and completes normal front-end, administrative, AJAX, cron, REST, and WP-CLI requests, each independently confirmed.
- The database contains the expected WordPress tables and, where applicable, the expected existing site data — not merely an empty database that happens to share the configured name.
- No equivalent "Unknown database" or "Can't select database" error recurs in logs across repeated, fresh requests.
- The specific underlying cause identified during diagnosis (a misconfiguration, an accidental deletion, or an environment mismatch) has been verifiably addressed.
- No unrelated configuration change was introduced as a side effect of recovery.

---

# 14. Prevention

- Keep `DB_NAME` synchronized with the actual, intended database name across development, staging, and production environments, particularly where environment-specific configuration or automated deployment is used.
- Include a verification step confirming the target database exists, and contains the expected data where applicable, as part of any deployment, migration, or environment-provisioning procedure, rather than assuming database creation succeeded.
- Maintain and test database backups so an accidentally dropped database can be restored rather than discovered only when WordPress fails.
- Where `DB_NAME` is supplied via an environment variable or a hosting-platform mechanism, monitor for that mechanism failing to supply a value, rather than assuming it always succeeds.
- Document the case-sensitivity behavior of the specific database server and operating system in use, to avoid case-mismatch errors during manual configuration.

---

# 15. Security Considerations

- Do not create a new, empty database under the configured name without first confirming whether the site's existing data is recoverable, since doing so can itself cause irreversible data loss by obscuring that a restore was actually needed.
- Do not grant the connecting database user broader privileges (for example, privileges over all databases on the server) as a shortcut to working around a database-selection failure; grant privileges scoped to the specific, correct database only.
- Avoid exposing the configured or attempted database name in user-facing error output, since it can reveal internal naming conventions to an unauthenticated visitor.
- Coordinate database restoration through a controlled, verified backup-restore process rather than an ad hoc copy, to avoid restoring stale, incomplete, or tampered data.

---

# 16. Related Errors

The following are cited as they exist in this repository, or as conceptual distinctions where noted.

1. [WP-ERROR-002 — WordPress Database Authentication Failure](WP-ERROR-002-WORDPRESS-DATABASE-AUTHENTICATION-FAILURE.md) — exists in this repository; see Section 6 (Distinction) above.
2. WP-ERROR-004 — Database Permission Denied (conceptual reference; no corresponding document currently exists in this repository; no link is provided).
3. [WP-ERROR-007 — Database Connection Limit Exceeded](WP-ERROR-007-WORDPRESS-DATABASE-CONNECTION-LIMIT-EXCEEDED.md) — exists in this repository; see Section 6 (Distinction) above.
4. [WP-ERROR-008 — WordPress Database Server Unreachable](WP-ERROR-008-WORDPRESS-DATABASE-SERVER-UNREACHABLE.md) — exists in this repository; see Section 6 (Distinction) above.
5. WP-ERROR-009 — Database Query Timeout (conceptual reference; no corresponding document currently exists in this repository; no link is provided).
6. [WP-ERROR-018 — WordPress Database Connection Failure](WP-ERROR-018-WORDPRESS-DATABASE-CONNECTION-FAILURE.md) — exists in this repository; see Section 6 (Distinction) above.

---

# 17. Notes

This entry documents the specific, verified condition of a named database not existing on an otherwise reachable, authenticated database server, as one of several specific causes deferred by the general connection-failure entry, WP-ERROR-018. It does not restate WP-ERROR-018's own boundary, its relationship to WP-ERROR-013, WP-ERROR-014, or WP-ERROR-016, or the general diagnostic process for narrowing an unspecified connection failure to a specific cause; see WP-ERROR-018 for that content. Consistent with the single-responsibility principle in **SF-SPEC-001** Section 4.3, cause-specific conditions for individual hosting-platform database-provisioning mechanisms or individual migration tools' database-creation defects may each be documented by a separate, independently created `WP-ERROR` entry without altering this one.

This entry's governing work order directed authoring using the categories and technical boundary it described, without a separate, itemized Technical Coverage list; the concrete technical grounding included here (MySQL/MariaDB error 1049, the `wpdb` database-selection step distinct from connection establishment, and the "Can't select database" message) was independently identified and verified during authoring, consistent with the level of technical specificity established across this catalog.

This entry underwent the review sequence required by **SF-SPEC-001** Section 19, **SF-SPEC-005** Section 5.6, and **SF-SPEC-012**: an author (Class A) review at `docs/reviews/SF-REVIEW-022-WP-ERROR-003-AUTHOR-REVIEW.md`, followed by an independent (Class B) review at `docs/reviews/SF-REVIEW-023-WP-ERROR-003-INDEPENDENT-REVIEW.md`, which independently re-verified the non-existence of WP-ERROR-004 and WP-ERROR-009, reached outcome **Approved with Minor Revisions**, applied and re-validated the one required revision, and satisfied the Production Ready gate per SF-SPEC-012 Section 12. Its Status was changed to Production Ready on that basis. This document does not itself constitute either review record; see the cited files for full findings, corrections, and gate decisions.

The independent review did not designate this entry as a Reference Implementation. That designation, governed separately by **SF-SPEC-001** Section 22, has not been sought or asserted here.

No Reference Implementation is currently designated by **SF-SPEC-001**; this entry's relationship to that designation, and to any future `WP-SCENARIO-XXX` runtime evidence, is not asserted here and shall not be assumed until such evidence or designation actually exists.
