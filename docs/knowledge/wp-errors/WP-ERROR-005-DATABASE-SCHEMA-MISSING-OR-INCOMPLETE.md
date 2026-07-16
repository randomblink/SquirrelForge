# WP-ERROR-005 — WordPress Database Schema Missing or Incomplete

---

# 1. Knowledge Entry

WordPress Database Schema Missing or Incomplete

---

# 2. Metadata

* **Error ID:** `WP-ERROR-005`
* **Title:** WordPress Database Schema Missing or Incomplete
* **Category:** Database
* **Severity:** Critical
* **Recovery Priority:** Immediate
* **Status:** Production Ready
* **Version:** 1.0

---

# 3. Summary

WordPress reaches the database server, authenticates, selects the configured database, holds sufficient privileges, and can execute queries, but one or more database structures the currently running code actually expects — a table, column, index, or primary key — is missing, incomplete, or inconsistent with what that code requires. This is one specific, verified cause within the general connection-failure condition documented by WP-ERROR-018.

---

# 4. Primary Failure Mode

Every earlier step in the connection lifecycle succeeds — network connection, authentication, database selection, and privilege checks — and WordPress's database layer (`wpdb`) is fully capable of executing queries, but a specific query fails because the structure it depends on does not actually exist in the form the running code expects. This can mean a required table is entirely absent, a required column is missing from an otherwise-present table, a required index or primary key is missing, or the code's own stored schema-version indicator does not match the schema actually present. The defining test for this entry is whether the *currently installed and active* WordPress core, plugin, or theme code expects the structure to exist — not whether some other, uninstalled or inactive code would want it; the absence of a table belonging to a plugin that was never installed is normal and expected, not a defect this entry documents.

---

# 5. Severity

This entry is classified **Critical**, though its actual impact ranges depending on which structure is affected:

- Where a WordPress core table or column that bootstrap itself depends on (for example, the options table) is missing or incomplete, the impact is a full-site outage, since WordPress cannot complete even its own initialization.
- Where a specific plugin's or theme's own table or column is missing, the impact is typically narrower — that specific feature fails while ordinary core functionality and unaffected plugins continue to work normally.
- This entry remains classified at the level of its most severe possible manifestation (a full-site outage from a missing core structure), consistent with the precedent established for other entries in this cluster whose actual impact can range depending on the specific structure or account involved.

---

# 6. Distinction

This entry applies only when verified evidence establishes that connection, authentication, database selection, and privileges are all sufficient, and that a specific, currently-expected database structure is genuinely missing, incomplete, or inconsistent — not that the connection itself failed, that the structure exists but its data is damaged, or that the absence of a structure is simply expected because the corresponding code is not installed or active.

**Internal distinctions this entry specifically requires:**

- **A table or column that does not exist, versus one that exists but is corrupted:** this entry covers only the former. A table or column that is structurally present but contains damaged, inconsistent, or unreadable data — confirmed via `SHOW CREATE TABLE` matching the expected definition while the data itself is defective — belongs to WP-ERROR-006, not this entry.
- **A missing WordPress core table, versus an intentionally absent optional plugin table:** this entry covers only structures the currently installed and active code actually expects. A table belonging to a plugin that has never been installed, or that was deliberately uninstalled, is not a defect.
- **A schema defect, versus missing application data:** this entry covers only the structure itself — tables, columns, indexes, and primary keys. A structurally complete, correctly defined table that simply contains few or no rows (for example, a freshly installed site) is not this entry's condition.
- **A failed schema migration, versus a query timeout or permission failure that caused the migration to stop:** where a migration is interrupted because of insufficient privileges or because a specific statement exceeds an applicable timeout, the specific triggering cause belongs to WP-ERROR-004 or WP-ERROR-009 respectively. This entry covers the resulting state — an incomplete, partially applied schema left behind afterward — which is a distinct, verifiable condition in its own right, regardless of what originally interrupted the migration.
- **A database-prefix mismatch, versus a genuinely absent schema:** `$table_prefix` in `wp-config.php` not matching the prefix actually used by the database's existing tables produces a client-visible failure ("table doesn't exist") identical to a genuinely missing schema, even though the correct, complete schema exists under a different prefix. Diagnosis shall independently rule out a prefix mismatch before concluding the schema is actually incomplete.
- **A site pointing at the wrong but valid database, versus a database that does not exist:** a site can be configured to connect to a real, reachable, existing database (so WP-ERROR-003 does not apply) that is simply not the database this specific WordPress installation actually needs — for example, an unrelated site's database, or an empty database never intended for this installation. This differs from a prefix mismatch in that the database itself, not merely the prefix, is wrong.

**Distinct from the following related entries:**

- **WP-ERROR-002 — WordPress Database Authentication Failure**, **WP-ERROR-007 — Database Connection Limit Exceeded**, and **WP-ERROR-008 — WordPress Database Server Unreachable**: all three occur before a connection is ever usable, so schema could not even be evaluated. This entry presumes a connection was fully usable.
- **WP-ERROR-003 — Database Does Not Exist**: the configured database itself does not exist at all. This entry presumes the database exists and was successfully selected; its schema, not its existence, is the defect.
- **WP-ERROR-004 — Database Permission Denied**: the connecting account lacks sufficient privileges. This entry presumes privileges are sufficient; the schema itself, not access to it, is the defect.
- **WP-ERROR-006 — WordPress Database Table Corruption**: presumes the expected structure is present, with the failure occurring because its data is damaged or inconsistent. This entry's condition is that the structure itself is absent or incomplete, not that present data is corrupted.
- **WP-ERROR-009 — Database Query Timeout**: presumes the expected schema is fully present and a query against it simply does not complete in time. This entry presumes the schema itself, not query duration, is the defect.
- **WP-ERROR-018 — WordPress Database Connection Failure**: WP-ERROR-018 owns the general, verified-but-unspecified-cause condition where WordPress cannot establish a database connection, and explicitly identifies missing or incomplete schema as one specific, verified cause that belongs to this entry once confirmed. This entry owns that specific, verified cause; WP-ERROR-018 owns the general condition and the diagnostic process for narrowing it to a specific cause, along with this entry's relationship to WP-ERROR-013, WP-ERROR-014, and WP-ERROR-016, which this entry does not restate.

---

# 7. Scope

**Covered:** A verified condition in which the database connection, authentication, database selection, and privileges are all confirmed sufficient, and queries can execute, but one or more tables, columns, indexes, or primary keys the currently installed and active WordPress core, plugin, or theme code expects are missing, incomplete, or otherwise inconsistent with that code's requirements — including where the underlying cause is a table-prefix mismatch or a wrong-but-valid configured database, since both present identically to a genuinely absent schema from WordPress's own perspective.

**Excluded:**

- Network-level unreachability, authentication rejection, connection-limit refusal, the database not existing, or insufficient privileges — all of which occur before schema can even be evaluated (see WP-ERROR-002, 003, 004, 007, 008).
- Query timeouts unrelated to an interrupted schema migration (see WP-ERROR-009).
- A structurally correct, complete schema whose data is corrupted, damaged, or inconsistent (see [WP-ERROR-006](WP-ERROR-006-DATABASE-TABLE-CORRUPTION.md)).
- A structurally correct, complete schema that simply contains little or no application data.
- The absence of a table, column, or index belonging to code that is not currently installed or active.

---

# 8. WordPress Components

Listed as components commonly involved, not as a claim that every installation exercises every one of them identically:

- `$wpdb` (`wp-includes/class-wpdb.php`), whose query-execution logic surfaces a missing table or column as a query-level error, populating `$wpdb->last_error`, distinct from a connection-, authentication-, or privilege-level failure.
- `$wpdb->prefix` and the raw `$table_prefix` value defined in `wp-config.php`, which together determine the actual table names WordPress looks for; in multisite, `$wpdb->prefix` additionally incorporates the specific site's own ID.
- `dbDelta()` (`wp-admin/includes/upgrade.php`), WordPress's schema creation and upgrade function, used by `wp-admin/install.php` during initial setup, by `wp_upgrade()` during a core version upgrade, and commonly by plugins and themes during their own activation and upgrade routines. `dbDelta()` requires a strictly formatted `CREATE TABLE` string to correctly detect needed changes, only adds or modifies structures rather than removing ones no longer expected, and is not wrapped in a single atomic transaction across multiple tables — an interruption partway through can leave a partially applied schema across some but not all affected tables.
- `wp-admin/install.php` and `wp-admin/upgrade.php`, and the `wp_upgrade()` function they invoke, which compare the site's stored `db_version` option against the code's expected version to determine whether a core schema upgrade is needed.
- The `db_version` option stored in the options table (or, for a specific site in a multisite network, that site's own options table), and the analogous, plugin-specific schema-version options individual plugins commonly maintain to track their own upgrade state.
- Plugin and theme activation hooks (`register_activation_hook()`), which commonly trigger a plugin's or theme's own schema-creation logic, and which do not run if a plugin or theme is enabled through means other than WordPress's normal activation flow.
- WordPress Multisite's per-site "blog tables" (prefixed per site, for example `wp_2_options`) as distinct from network-wide global tables (`wp_users`, `wp_blogs`, `wp_site`, and similar), each requiring its own schema-creation or upgrade path.

---

# 9. Typical Symptoms

- A database-level error such as MySQL/MariaDB error `1146` ("Table '<db>.<table>' doesn't exist") or `1054` ("Unknown column '<column>' in '<clause>'"), visible in logs where accessible, referencing a specific WordPress core table, a plugin or theme table, or a specific expected column.
- A specific feature or plugin failing (for example, a plugin's admin page producing a WordPress database error, a setting silently failing to save, or a specific block or widget not functioning) while ordinary core functionality and other features continue to work normally.
- The failure appearing immediately after a plugin or theme installation, activation, or update, a core upgrade, a database restore, or an environment migration.
- `SHOW TABLES` revealing that expected tables are present under a different prefix than `$table_prefix`/`$wpdb->prefix` currently expects, indicating a prefix mismatch rather than a genuinely missing schema.
- The stored `db_version` option, or a plugin's own schema-version option, not matching the version the currently running code expects.
- In a multisite installation, the issue affecting only specific sites' own tables while other sites on the same network function normally, or vice versa.

---

# 10. Common Causes

Causes are grouped by category. Inclusion in this list identifies a category as plausible; it does not assert that any specific cause is present without diagnostic confirmation.

- An interrupted installation or upgrade — `dbDelta()` or `wp_upgrade()` did not complete, for example because the request timed out or a PHP fatal error occurred mid-migration, leaving some but not all expected schema changes applied.
- A partial database import or restore — a backup or restore process that completed for some tables but not others, leaving specific tables or an entire subset absent.
- An incorrect table prefix — `$table_prefix` in `wp-config.php` not matching the prefix actually used by the existing database's tables.
- Multisite schema differences — a new site added to a network whose own per-site tables were never fully created, or a network-wide schema upgrade that updated global tables but not every individual site's own tables.
- A missing custom table — a plugin's or theme's own required table never created, commonly because its activation routine never actually ran, for example because the plugin was uploaded and enabled by direct file or database manipulation rather than through WordPress's normal activation flow.
- Missing columns or indexes — a plugin or core upgrade that was supposed to add a specific column or index via its own upgrade routine, but that routine never ran or failed partway through.
- A failed or skipped migration — the code's own stored schema-version indicator (`db_version` for WordPress core, or a plugin's own custom option) was advanced without the corresponding schema change actually being applied, or a version check was itself bypassed.
- A manual database modification — a table, column, or index directly renamed, dropped, or altered outside of WordPress's own upgrade mechanisms, without a corresponding code change.
- Deploying newer code against an older schema — code updated to a version expecting new tables or columns, deployed to an environment where the corresponding migration was never triggered or completed.
- A site's configured database being a genuinely different, valid database than the one this specific WordPress installation actually needs, distinct from either a missing database or a prefix mismatch against the correct database.

---

# 11. Diagnosis

Verify the following:

1. Confirm this is genuinely a missing- or incomplete-schema condition — MySQL/MariaDB error 1146 or 1054, or an application-level error referencing a specific expected table, column, or index — rather than an earlier-stage failure documented elsewhere in this cluster.
2. Before concluding a table is genuinely absent, rule out a table-prefix mismatch: run `SHOW TABLES` (or WP-CLI's `wp db tables`, which lists tables matching a given prefix pattern) against the connected database and compare the actual prefix of existing tables against `$table_prefix` in `wp-config.php` and the resolved `$wpdb->prefix`, since a mismatch produces an identical "table doesn't exist" symptom to a genuinely missing schema.
3. Where the prefix is confirmed correct, confirm this is genuinely the intended database for this WordPress installation (for example, by checking the `siteurl`/`home` option values against the expected site) rather than a valid but unrelated database mistakenly configured for this site.
4. Compare the actual schema against what the running code expects: use `SHOW CREATE TABLE`, `DESCRIBE`/`SHOW COLUMNS`, and `SHOW INDEX` — or the equivalent `INFORMATION_SCHEMA.TABLES`/`COLUMNS`/`STATISTICS` views for programmatic comparison — to determine exactly which tables, columns, or indexes are missing, rather than assuming the entire schema is absent from a single error.
5. Confirm the stored `db_version` option (for WordPress core) or the relevant plugin's own schema-version option against the version the running code actually expects, to determine whether an upgrade was ever triggered, silently skipped, or advanced without the corresponding change actually applying.
6. Where a specific plugin's or theme's table is missing, confirm whether its activation routine ever actually ran, since a bypassed activation hook is a common cause of a table never being created at all.
7. Where a recent core, plugin, or theme upgrade is suspected, attempt to reproduce the expected migration directly — for example, via WP-CLI's `wp core update-db`, which explicitly triggers WordPress's own core database-upgrade routine — and observe whether it completes successfully or reveals an underlying error.
8. Where the site is a WordPress Multisite installation, confirm whether the affected schema issue is isolated to a specific site's own tables or affects the shared, network-wide tables, since each requires different, individually-triggered per-site or network-wide remediation. Note that `wp core update-db` without its `--network` flag affects only the current or main site; triggering the upgrade across every individual site in the network requires the `--network` flag or the Network Admin "Upgrade Network" screen.
9. Determine whether a query timeout or a permission-denial error accompanies evidence of an interrupted migration, since the resulting incomplete schema is this entry's condition, but the specific reason the migration itself was interrupted may belong to WP-ERROR-004 or WP-ERROR-009.
10. Confirm whether missing rows or empty tables are being mistaken for a schema defect: an empty but structurally complete table, confirmed via `SHOW CREATE TABLE` matching the expected definition, is not this entry's condition.
11. Preserve relevant evidence — the exact error text, the actual schema definitions retrieved, the stored schema-version values, and timestamps — before making any change, particularly before running any tool that will modify the schema.
12. Where the engineer performing diagnosis does not control the database server or lacks a verified, current backup, escalate or obtain a backup before attempting any schema-modifying recovery action.

---

# 12. Recovery Procedure

Recovery shall confirm a current, verified backup of the database exists before making any schema-modifying change, since schema corrections are not always safely reversible.

Permitted recovery categories, depending on the verified cause, include:

- Where a table-prefix mismatch is confirmed as the cause, correcting `$table_prefix` in `wp-config.php` to match the database's actual, existing schema, rather than modifying the schema itself to match an incorrect prefix.
- Where a plugin's or theme's activation routine never ran, triggering it properly — for example, by deactivating and reactivating it through WordPress's normal admin flow, or via WP-CLI's `wp plugin activate` — so its own schema-creation logic executes as designed, rather than manually recreating its tables without understanding its exact expected structure.
- Where a core, plugin, or theme upgrade was interrupted or skipped, triggering the corresponding upgrade routine again — for example, via `wp core update-db` for WordPress core, or `wp core update-db --network` (or the Network Admin "Upgrade Network" screen) to trigger it across every site in a multisite network rather than only the current site — rather than manually issuing ad hoc `ALTER TABLE` statements without understanding the code's actual expected schema.
- Where `dbDelta()` or an equivalent migration routine is used to apply a correction, verifying its output and the resulting schema directly afterward, rather than assuming it applied every expected change.
- Where a manual schema modification caused the inconsistency, restoring the affected structure to match what the running code actually expects, in coordination with whoever made the manual change.
- Where the site is confirmed to be pointed at a wrong, unrelated (but valid) database, correcting the configuration to reference the actual intended database, rather than attempting to recreate that site's schema inside a different database.
- Escalating to the database administrator or a qualified engineer with schema-migration experience where the required correction is complex, affects a large table, or is not fully understood.

Recovery shall not treat `dbDelta()` or any other automated migration routine as guaranteed to safely and completely resolve every schema defect: `dbDelta()` does not drop columns or indexes no longer part of the expected definition, so pre-existing schema drift from previously removed structures will not be cleaned up by running it, and its own strict formatting requirements mean a malformed `CREATE TABLE` string can silently fail to apply an intended change. Recovery shall verify the resulting schema directly rather than assuming a migration tool's completion implies correctness, and shall not apply schema-modifying statements directly against a production database without a verified, current backup.

---

# 13. Validation

Recovery is successful when:

- `SHOW CREATE TABLE`, `DESCRIBE`, and `SHOW INDEX` — or the equivalent `INFORMATION_SCHEMA` views — confirm every previously missing or incomplete table, column, and index now matches what the running code expects.
- The stored `db_version` option, or the relevant plugin's own schema-version option, reflects the version corresponding to the schema actually now present.
- The previously failing operation completes successfully.
- No equivalent "table doesn't exist" or "unknown column" error recurs in logs across repeated, fresh requests.
- Where multisite is involved, the correction has been confirmed across every affected site, not only the site or sites where the issue was first observed.
- No unrelated schema change was introduced as a side effect of the correction.

---

# 14. Prevention

- Verify database migrations completed successfully as a standard step after any core, plugin, or theme update, rather than assuming success from the absence of an immediately visible error.
- Include a schema-verification step in deployment and migration procedures, confirming the actual schema matches what the deployed code version expects, particularly after a database restore or environment migration.
- Install and activate plugins and themes through WordPress's normal administrative flow, or an equivalent properly triggering WP-CLI command, rather than by direct file or database manipulation, so activation-time schema-creation routines actually run.
- In multisite environments, confirm schema changes are verified across every individual site, not only the network's shared tables or the main site.
- Maintain current, verified, and regularly tested database backups before any operation that modifies schema.
- Document the table-prefix configuration for each environment and verify it as part of any environment migration or configuration change.

---

# 15. Security Considerations

- Do not run automated schema-repair or migration tools directly against production without a verified backup and, where feasible, a prior test against a copy of the database.
- Avoid exposing internal schema details (table names, column names, structure) in user-facing error output, since it can reveal internal application structure to an unauthenticated visitor.
- Treat an unexpected schema difference discovered outside of a known migration or deployment as a potential signal of unauthorized or unintended database modification, not only as routine drift, particularly where no legitimate change explains it.
- Coordinate schema changes through a platform-appropriate, auditable process rather than direct, undocumented modification of a production database.

---

# 16. Related Errors

The following are cited as they exist in this repository, or as conceptual distinctions where noted.

1. [WP-ERROR-002 — WordPress Database Authentication Failure](WP-ERROR-002-WORDPRESS-DATABASE-AUTHENTICATION-FAILURE.md) — exists in this repository; see Section 6 (Distinction) above.
2. [WP-ERROR-003 — Database Does Not Exist](WP-ERROR-003-DATABASE-DOES-NOT-EXIST.md) — exists in this repository; see Section 6 (Distinction) above.
3. [WP-ERROR-004 — Database Permission Denied](WP-ERROR-004-DATABASE-PERMISSION-DENIED.md) — exists in this repository; see Section 6 (Distinction) above.
4. [WP-ERROR-006 — WordPress Database Table Corruption](WP-ERROR-006-DATABASE-TABLE-CORRUPTION.md) — exists in this repository; see Section 6 (Distinction) above.
5. [WP-ERROR-007 — Database Connection Limit Exceeded](WP-ERROR-007-WORDPRESS-DATABASE-CONNECTION-LIMIT-EXCEEDED.md) — exists in this repository; see Section 6 (Distinction) above.
6. [WP-ERROR-008 — WordPress Database Server Unreachable](WP-ERROR-008-WORDPRESS-DATABASE-SERVER-UNREACHABLE.md) — exists in this repository; see Section 6 (Distinction) above.
7. [WP-ERROR-009 — Database Query Timeout](WP-ERROR-009-DATABASE-QUERY-TIMEOUT.md) — exists in this repository; see Section 6 (Distinction) above.
8. [WP-ERROR-018 — WordPress Database Connection Failure](WP-ERROR-018-WORDPRESS-DATABASE-CONNECTION-FAILURE.md) — exists in this repository; see Section 6 (Distinction) above.

---

# 17. Notes

This entry documents the specific, verified condition of a missing or incomplete database schema on an otherwise fully reachable, authenticated, selected, and privileged database, as one of several specific causes deferred by the general connection-failure entry, WP-ERROR-018. It does not restate WP-ERROR-018's own boundary, its relationship to WP-ERROR-013, WP-ERROR-014, or WP-ERROR-016, or the general diagnostic process for narrowing an unspecified connection failure to a specific cause; see WP-ERROR-018 for that content. Consistent with the single-responsibility principle in **SF-SPEC-001** Section 4.3, this entry covers the range of ways a required structure can be missing or incomplete (a table, a column, an index, a version-tracking mismatch, a prefix mismatch, or a wrong configured database) as one cohesive failure mode, since all share the same underlying, observable condition — the running code expects a structure that is not actually present as expected; cause-specific conditions for individual plugins' own migration defects may each be documented by a separate, independently created `WP-ERROR` entry without altering this one.

This entry underwent the review sequence required by **SF-SPEC-001** Section 19, **SF-SPEC-005** Section 5.6, and **SF-SPEC-012**: an author (Class A) review at `docs/reviews/SF-REVIEW-028-WP-ERROR-005-AUTHOR-REVIEW.md`, followed by an independent (Class B) review at `docs/reviews/SF-REVIEW-029-WP-ERROR-005-INDEPENDENT-REVIEW.md`, which independently re-verified the non-existence of WP-ERROR-006 and the Production Ready status of WP-ERROR-002, 003, 004, 007, 008, 009, and 018, reached outcome **Approved with Minor Revisions**, applied and re-validated the one required revision, and satisfied the Production Ready gate per SF-SPEC-012 Section 12. Its Status was changed to Production Ready on that basis. This document does not itself constitute either review record; see the cited files for full findings, corrections, and gate decisions.

The independent review did not designate this entry as a Reference Implementation. That designation, governed separately by **SF-SPEC-001** Section 22, has not been sought or asserted here.

No Reference Implementation is currently designated by **SF-SPEC-001**; this entry's relationship to that designation, and to any future `WP-SCENARIO-XXX` runtime evidence, is not asserted here and shall not be assumed until such evidence or designation actually exists.
