# WP-ERROR-006 — WordPress Database Table Corruption

---

# 1. Knowledge Entry

WordPress Database Table Corruption

---

# 2. Metadata

* **Error ID:** `WP-ERROR-006`
* **Title:** WordPress Database Table Corruption
* **Category:** Database
* **Severity:** Critical
* **Recovery Priority:** Immediate
* **Status:** Production Ready
* **Version:** 1.0

---

# 3. Summary

WordPress reaches the database server, authenticates, selects the configured database, holds sufficient privileges, and the specific table a query targets exists and is correctly identified — but MySQL or MariaDB reports that the table's own data file, index file, or underlying storage structure is damaged or inconsistent, so that a query against it cannot be relied upon to complete or to return a correct result. This is one specific, verified cause within the general connection-failure condition documented by WP-ERROR-018.

---

# 4. Primary Failure Mode

Every earlier step in the connection lifecycle succeeds — network connection, authentication, database selection, and privilege checks — and the table a specific query targets is present under the correct name with the schema definition (its columns, indexes, and primary key) exactly as the running code expects, but the query nonetheless fails because the server itself reports that the table's own storage structure is damaged, inconsistent, or unreadable. What has failed is the physical integrity of the structure implementing the schema, not the schema's own presence or shape.

The specific manifestation and the available remedy differ materially by storage engine, and diagnosis shall identify which engine is actually involved before selecting a remedy:

- For **MyISAM** (and the compatible **Aria** engine used by MariaDB), the server maintains an internal open-count flag for each table. An unclean termination while the table is open for writing can leave that flag indicating the table was not properly closed, causing the server to mark it "crashed" on next access (MySQL/MariaDB error 1194, `ER_CRASHED_ON_USAGE`) even where no byte of the underlying data is actually damaged. `REPAIR TABLE` is a server-supported, purpose-built remedy for this engine.
- For **InnoDB**, corruption is reported through page-checksum mismatches or page-corruption messages in the server's own error log, not through the same crashed-flag mechanism. InnoDB's own redo log ordinarily replays incomplete transactions automatically during the next server startup, making it materially less prone to the unclean-shutdown-driven condition MyISAM/Aria can experience — but `REPAIR TABLE` is not a supported repair mechanism for this engine: MySQL and MariaDB do not implement genuine repair logic for InnoDB through that statement, and `mysqlcheck` itself (the utility WP-CLI's `wp db repair` and `wp db check` invoke) reports that the storage engine does not support repair when directed at an InnoDB table.

Treating `REPAIR TABLE`, `wp db repair`, or `mysqlcheck --repair` as a universal remedy regardless of engine or confirmed condition is itself a diagnostic and recovery hazard this entry documents explicitly (see Section 12).

---

# 5. Severity

This entry is classified **Critical**, though its actual impact ranges depending on which table is affected:

- Where a WordPress core table that bootstrap itself depends on (for example, the options table) is corrupted, the impact is a full-site outage, since WordPress cannot complete initialization.
- Where a specific plugin's or theme's own table is corrupted, the impact is typically narrower — that specific feature fails while ordinary core functionality and unaffected plugins continue to work normally.
- This entry remains classified at the level of its most severe possible manifestation (a full-site outage from a corrupted core table), consistent with the precedent established for WP-ERROR-004 and WP-ERROR-005, whose actual impact similarly ranges depending on the specific structure affected.

Unlike a missing-schema or permission-denial condition, several of this entry's available remedies are not reliably reversible once applied. That does not change the severity classification itself — severity concerns impact, not recovery risk — but it is the reason Sections 11 and 12 require a verified backup or preserved file copy before any modifying action.

---

# 6. Distinction

This entry applies only when verified evidence establishes that connection, authentication, database selection, privileges, and the schema's own definition are all confirmed sufficient and correct, and that the server itself reports damage or inconsistency in a specific table's data, index, or underlying storage structure — not that the table is merely absent or incomplete, that a query against an intact table is simply slow, or that a table is temporarily inaccessible for a reason unrelated to physical damage.

**Internal distinctions this entry specifically requires:**

- **MyISAM/Aria "crashed" state versus InnoDB corruption:** both are covered by this entry as manifestations of the same underlying condition — a table's storage structure is not reliably readable or writable — but they are not interchangeable for recovery purposes. A MyISAM/Aria table marked crashed is frequently repairable in place via `REPAIR TABLE`; InnoDB corruption is not, and requires either a dump-and-reload of the affected table or, where the server itself cannot start, a forced-recovery startup solely to extract data. Diagnosis shall identify the specific engine (Section 11, item 3) before selecting a remedy.
- **Root cause versus observable condition:** this entry documents the observable, database-level condition — the server reporting a specific table as damaged or inconsistent — regardless of what produced it. An underlying disk, filesystem, or hardware failure is a plausible and common root cause (see Section 10), but this entry's condition is the resulting corruption the database server reports, not the disk or filesystem failure itself; no dedicated Filesystem-category entry documenting the underlying hardware condition currently exists in this repository, and none is asserted here.
- **Genuine corruption versus a transient lock, metadata lock, or deadlock:** a table temporarily unavailable because another transaction or operation holds a lock on it, or because InnoDB has rolled back one side of a deadlock, can present a similar client-visible symptom (a stalled or failed operation against that specific table) without any actual damage to its storage structures. This condition resolves once the blocking transaction or operation completes and is not, by itself, evidence of corruption; where the underlying cause is lock contention rather than physical damage, the resulting failure belongs to WP-ERROR-009, not this entry.
- **Storage-structure damage versus missing or logically incorrect data:** this entry covers only damage to the table's own physical storage structure, confirmed by the database server itself (for example, `CHECK TABLE` reporting other than `OK`, or a crashed/checksum-mismatch message). A table that is structurally intact and passes `CHECK TABLE` but simply contains missing rows, stale values, or logically inconsistent application data (for example, an orphaned foreign reference) is not this entry's condition; the storage layer itself has not reported any defect.
- **Corruption versus intentional table deletion or an incomplete migration:** a table that no longer exists because it was deliberately dropped, or a migration that left a table's structure incomplete, is a structural-absence condition belonging to WP-ERROR-005, not this entry. This entry presumes the table's structure is present and was previously usable; it covers damage to that existing structure, not its absence.

**Distinct from the following related entries:**

- **WP-ERROR-002 — WordPress Database Authentication Failure**, **WP-ERROR-007 — Database Connection Limit Exceeded**, and **WP-ERROR-008 — WordPress Database Server Unreachable**: all three occur before a connection is ever usable, so no specific table could even be addressed. This entry presumes a connection was fully usable and a specific table was actually reached.
- **WP-ERROR-003 — Database Does Not Exist**: the configured database itself does not exist. This entry presumes the database exists and was successfully selected; a specific table within it, not the database itself, is the defect.
- **WP-ERROR-004 — Database Permission Denied**: the connecting account lacks sufficient privileges. This entry presumes privileges are sufficient; the table's own physical integrity, not access to it, is the defect.
- **WP-ERROR-005 — WordPress Database Schema Missing or Incomplete**: presumes the expected table, column, or index is absent or incomplete. This entry presumes the opposite — the expected structure is present and correctly defined — with the failure occurring because that present structure's own data or storage is damaged, not because it fails to exist in the form the code expects.
- **WP-ERROR-009 — Database Query Timeout**: presumes the table is intact and a query against it is actually executing, with the failure occurring only because that query does not complete within an applicable time limit — including where the delay is caused by lock contention or a blocking transaction. This entry presumes the table's own storage structure, not query duration or lock contention, is the defect.
- **WP-ERROR-018 — WordPress Database Connection Failure**: WP-ERROR-018 owns the general, verified-but-unspecified-cause condition where WordPress cannot establish a database connection, and explicitly identifies table corruption as one specific, verified cause that belongs to this entry once confirmed. This entry owns that specific, verified cause; WP-ERROR-018 owns the general condition and the diagnostic process for narrowing it to a specific cause, along with this entry's relationship to WP-ERROR-013, WP-ERROR-014, and WP-ERROR-016, which this entry does not restate.

---

# 7. Scope

**Covered:** A verified condition in which the database connection, authentication, database selection, and privileges are all confirmed sufficient, and the specific table a query targets exists under the correct name with its schema definition intact, but the database server itself reports that the table's own data, index, or underlying storage structure is damaged, inconsistent, or otherwise unreadable — regardless of which storage engine (MyISAM, Aria, or InnoDB) is involved, though the available remedy differs materially by engine, and regardless of the underlying root cause (unclean shutdown, disk or filesystem failure, or an interrupted schema-modifying operation).

**Excluded:**

- Network-level unreachability, authentication rejection, connection-limit refusal, the database not existing, or insufficient privileges — all of which occur before a specific table can even be reached (see WP-ERROR-002, 003, 004, 007, 008).
- A table, column, or index that is missing or incomplete, including one that was intentionally dropped or left incomplete by an interrupted migration (see WP-ERROR-005).
- A query against a structurally intact, uncorrupted table that simply does not complete within an applicable time limit, including where the delay is caused by lock contention or a blocking transaction (see WP-ERROR-009).
- A table temporarily unavailable because of an active lock, metadata lock, or deadlock that resolves once the blocking transaction or operation completes, absent any confirmed physical damage to the table's own storage structure.
- Missing rows or logically incorrect application data in a table that itself passes `CHECK TABLE` with no reported defect.
- The underlying disk, filesystem, or hardware condition that may have caused the corruption, as distinct from the resulting, observable database-level condition this entry documents.

---

# 8. WordPress Components

Listed as components commonly involved, not as a claim that every installation exercises every one of them identically:

- `$wpdb` (`wp-includes/class-wpdb.php`), whose query-execution logic surfaces a server-reported corruption error as a query-level failure, populating `$wpdb->last_error`, distinct from a connection-, authentication-, privilege-, or schema-level failure.
- The storage engine assigned to each table — MyISAM, MariaDB's compatible Aria engine, or InnoDB (WordPress's own default engine for tables created by current versions of `dbDelta()`) — which determines both how corruption is reported and which recovery mechanism actually applies. A single WordPress installation can have tables under more than one engine, particularly where older tables predate a change in WordPress's or a plugin's own default.
- `dbDelta()` (`wp-admin/includes/upgrade.php`), which creates and alters table structures but has no role in detecting or repairing physical storage corruption in an existing table; its concern is schema shape, not storage integrity.
- WP-CLI's `wp db check` and `wp db repair` commands, which invoke the `mysqlcheck` utility with `--check` and `--repair` respectively, using the credentials configured in `wp-config.php`, against every table in the configured database.
- The database server's own `CHECK TABLE` and `REPAIR TABLE` statements, and, for MyISAM/Aria specifically, the offline `myisamchk`/`aria_chk` utilities usable only while the server is stopped or the specific table is not in use.
- For InnoDB specifically, the `innodb_force_recovery` server startup option, which permits a restricted startup sufficient to extract data when corruption otherwise prevents the server from starting normally.
- WordPress Multisite's per-site "blog tables" (prefixed per site, for example `wp_2_options`) as distinct from network-wide global tables (`wp_users`, `wp_blogs`, `wp_site`, and similar), each independently subject to this entry's condition.

---

# 9. Typical Symptoms

- A database-level error such as MySQL/MariaDB error `1194` ("Table '%s' is marked as crashed and should be repaired") or `1195` ("Table '%s' is marked as crashed and last (automatic?) repair failed"), typically associated with MyISAM/Aria tables.
- A database-level error such as MySQL/MariaDB error `1034` ("Incorrect key file for table '%s'; try to repair it") or `1035` ("Old key file for table '%s'; repair it!"); despite the "key file" wording historically associated with MyISAM, either can also be reported against other engines, including InnoDB.
- For InnoDB specifically, a page-checksum mismatch or page-corruption message in the database server's own error log, rather than a client-visible "marked as crashed" message.
- `CHECK TABLE` returning a status other than `OK` for the specific affected table.
- A specific feature or plugin failing (for example, a plugin's admin page producing a WordPress database error, or a specific query consistently failing) while ordinary core functionality and other features continue to work normally, where the affected table belongs to that plugin rather than to WordPress core.
- The failure appearing suddenly after an unclean server shutdown, a forced process termination, a host crash, a power loss, or the disk reaching capacity, rather than developing gradually.
- The database server itself failing to start normally, with its own error log referencing a specific table, index, or tablespace, where corruption is severe enough to affect server startup rather than only a single table's accessibility during otherwise normal operation.
- The same symptom recurring shortly after an apparently successful repair, which can indicate an unaddressed underlying disk or hardware condition rather than a one-off event.

---

# 10. Common Causes

Causes are grouped by category. Inclusion in this list identifies a category as plausible; it does not assert that any specific cause is present without diagnostic confirmation.

- An unclean server termination (a killed process, an out-of-memory condition, a host crash, or a power loss) while a table was open for writing — particularly significant for MyISAM/Aria tables, whose internal open-count flag is left indicating an unclean close; InnoDB is comparatively more resilient to this specific cause because its redo log ordinarily replays incomplete transactions automatically on the next startup, though corruption can still result if that recovery process is itself interrupted or the underlying files are already damaged.
- An underlying disk, filesystem, or hardware failure — a failing disk, a degraded RAID member, filesystem-level corruption, or a memory error occurring during a write — directly damaging the bytes of a MyISAM `.MYD`/`.MYI` file or an InnoDB tablespace file, independent of anything the database server itself did.
- The server running out of disk space in the middle of a write, leaving a table's storage structure partially written.
- Copying a table's underlying files directly (for example, as an ad hoc backup method) while the database server has the table open and is actively writing to it, rather than through a consistent backup mechanism (a logical dump, or a file-level copy taken only while the server is stopped or the relevant tables are properly locked and flushed).
- Restoring or moving a MyISAM/Aria table's underlying files onto a database server version or platform incompatible with the one that created them, without a proper logical dump and reload.
- An `ALTER TABLE`, `OPTIMIZE TABLE`, or `REPAIR TABLE` operation itself interrupted mid-operation (for example, the server or the client process terminated while it was running), leaving the table in a partially rebuilt, inconsistent state.
- A storage-engine-level defect, rather than an environmental cause — rare, but not excluded by this entry's boundary.

---

# 11. Diagnosis

Verify the following:

1. Confirm this is genuinely a table-corruption condition — MySQL/MariaDB error 1194 or 1195, error 1034 or 1035, or an InnoDB-specific checksum-mismatch or page-corruption message in the server's own error log — rather than an earlier-stage or unrelated condition documented elsewhere in this cluster.
2. Run `CHECK TABLE` (or WP-CLI's `wp db check`, which invokes `mysqlcheck --check` using WordPress's own configured credentials) as the least invasive diagnostic step, since it detects without modifying. Do not proceed to `REPAIR TABLE` or any other modifying operation before this step has been performed and its output recorded.
3. Identify the specific storage engine of the affected table (`SHOW TABLE STATUS`, or the `ENGINE` column of `INFORMATION_SCHEMA.TABLES`) before selecting a remedy, since MyISAM/Aria and InnoDB require materially different recovery paths.
4. Review the database server's own error log for the specific corruption message and its timestamp, rather than relying solely on WordPress's own inline "WordPress database error" presentation, since the server log frequently records detail — for example, the specific page or index affected — that WordPress's own error surface does not.
5. Rule out that the observed symptom is a transient lock, metadata lock, or deadlock rather than genuine corruption: check `SHOW PROCESSLIST` and, where InnoDB is involved, `SHOW ENGINE INNODB STATUS` for active locks or a recent deadlock before concluding the table itself is damaged, since a table temporarily blocked by another transaction can present a similar stalled or failed operation without any actual damage to its storage structures.
6. Rule out WP-ERROR-005: confirm via `SHOW CREATE TABLE` that the table's own definition (columns, indexes, primary key) matches what the running code expects. This entry's condition is damage to the data or index content of an otherwise correctly defined table, not a defect in the definition itself.
7. Where corruption is confirmed, determine whether it is isolated to one table (consistent with an interrupted operation or a single damaged region of storage) or affects multiple, unrelated tables (consistent with a broader underlying disk, filesystem, or hardware failure), since the latter indicates the corrective action shall extend beyond the specific table first noticed.
8. Investigate for an underlying disk, filesystem, or hardware cause — operating-system and hardware logs, disk health/SMART status, and filesystem-level error reporting — particularly where corruption recurs after a table has already been repaired, since repairing the table without addressing a failing underlying disk will not prevent recurrence.
9. For InnoDB specifically, confirm whether the database server itself can start and operate normally. Where it cannot, this indicates a more serious condition than a single damaged table and may require a server-level forced-recovery procedure rather than a table-level repair.
10. Preserve a verified, current backup or a file-level copy of the affected table's underlying files before making any change, particularly before running any repair, force-recovery, or other modifying operation, since several of the available remedies are not reliably reversible.
11. Where the engineer performing diagnosis does not control the database server or its underlying storage, or lacks a verified, current backup, escalate to the database administrator, hosting provider, or a qualified data-recovery specialist before attempting any modifying operation.

---

# 12. Recovery Procedure

Recovery shall confirm a verified, current backup or a preserved file-level copy of the affected table's underlying files exists before performing any modifying or repair operation, since several available remedies are not reliably reversible and can themselves discard otherwise-recoverable data.

Recovery shall select the specific remedy appropriate to the confirmed storage engine and condition; it shall not treat `REPAIR TABLE`, `wp db repair`, or `mysqlcheck --repair` as a universal remedy applicable regardless of engine or confirmed condition.

Permitted recovery categories, depending on the verified cause and engine, include:

- Where `CHECK TABLE` or the server's error log confirms a MyISAM or Aria table is marked crashed (error 1194/1195) and the underlying files are not confirmed damaged at the disk or filesystem level, `REPAIR TABLE` is a server-supported, purpose-built remedy for this specific engine and condition. WP-CLI's `wp db repair` invokes `mysqlcheck --repair` against every table in the configured database by default, not only the specific affected table; where repairing only the specific affected table is preferred — particularly during an active incident, to avoid unnecessary action against unaffected tables — issue `REPAIR TABLE <table_name>;` directly, or `wp db query "REPAIR TABLE <table_name>"` through WP-CLI, rather than the database-wide `wp db repair`. Because `REPAIR TABLE` can itself discard rows it cannot reconcile, verify the table's row count and application-visible content against a backup or known-good expectation afterward, rather than treating `REPAIR TABLE`'s own successful completion as confirmation that no data was lost.
- Where MyISAM/Aria `REPAIR TABLE` itself fails (error 1195) or the corruption is more severe, escalating to the offline `myisamchk`/`aria_chk` utility, run only while the server is stopped or the specific table is not in use, before considering the table unrecoverable through in-place repair.
- For InnoDB, `REPAIR TABLE` and `wp db repair` shall not be relied upon as a repair mechanism: this engine does not implement genuine repair logic for that statement, and `mysqlcheck` itself reports that the storage engine does not support repair when directed at an InnoDB table. Where the database server itself is otherwise running normally and the affected table remains accessible, the appropriate remedy is to dump the affected table's data (for example, via `mysqldump`) and reload it into a newly created table, rather than attempting an in-place repair this engine does not provide.
- Where InnoDB corruption prevents the database server itself from starting normally, using `innodb_force_recovery`, starting at the lowest level (1) and incrementing only as far as actually needed to bring the server up sufficiently to dump the affected data, per the server's own documented recovery levels. Restore the setting to `0` before resuming normal write operations; a server running under a nonzero `innodb_force_recovery` value is not suitable for ordinary use. Values of 4 or higher risk further, irreversible damage to data files and shall be treated as a last resort, attempted only after a byte-level copy of the affected data files has been preserved.
- Where diagnosis confirms an underlying disk, filesystem, or hardware failure as the root cause, addressing that underlying condition (for example, replacing failing storage or correcting a filesystem-level error) in addition to restoring the specific affected table, since repairing the table alone does not prevent recurrence from an unaddressed hardware or filesystem defect.
- Where the affected table's data cannot be reliably reconciled through repair or dump-and-reload, or the confirmed extent of loss is unacceptable, restoring the affected table — or the full database, where multiple tables are affected — from a verified, current backup, preferred over continuing an uncertain in-place repair.
- Escalating to the database administrator, hosting provider, or a qualified data-recovery specialist where the corruption is extensive, the affected table is business-critical, or the engineer performing recovery lacks confidence in the reversibility of the specific operation being considered.

Recovery shall not run `REPAIR TABLE`, `myisamchk`/`aria_chk` in force mode, or any nonzero `innodb_force_recovery` value without a verified, current backup or a preserved file-level copy of the affected files already in hand.

---

# 13. Validation

Recovery is successful when:

- `CHECK TABLE` (or `wp db check`) reports the previously affected table as `OK`, with no equivalent crashed, key-file, or checksum-mismatch message recurring.
- The previously failing WordPress operation completes successfully, confirmed by reproducing the exact action that previously failed.
- The affected table's row count and application-visible content have been confirmed against a backup or known-good expectation, where available, ruling out silent data loss introduced by the repair process itself.
- No equivalent corruption message recurs in the database server's own error log across repeated, fresh operations against the affected table.
- Where `innodb_force_recovery` was used, it has been restored to `0` and the server has been confirmed to start and operate normally under standard settings before resuming production use.
- Where an underlying disk, filesystem, or hardware cause was identified, that condition has been confirmed addressed, not merely the table repaired.
- No unrelated table, schema element, or configuration was altered as a side effect of the recovery.

---

# 14. Prevention

- Prefer InnoDB over MyISAM for new tables where a choice exists, since InnoDB's own crash-recovery mechanism (redo log replay on startup) makes it materially less prone to the unclean-shutdown-driven "crashed" condition MyISAM/Aria tables can experience, without asserting InnoDB is immune to corruption from disk or hardware failure.
- Maintain regular, verified, and tested backups — both logical (for example, `mysqldump`) and, where appropriate, file-level — so that restoring from a known-good copy remains available if in-place repair proves insufficient.
- Take file-level backups only while the database server is stopped, or through a mechanism that guarantees a consistent copy (a proper snapshot or dump tool), rather than by copying a table's underlying files while the server has it open and is actively writing to it.
- Monitor the database server's own error log for early corruption or checksum-related signals proactively, rather than discovering the condition only when a specific WordPress operation is reported broken.
- Monitor underlying disk, filesystem, and hardware health (for example, SMART status and RAID array state) proactively, since undetected hardware degradation is a common root cause of recurring corruption.
- Shut down the database server only through its own supported procedure, avoiding abrupt termination, so tables are closed cleanly.
- Avoid interrupting a running `ALTER TABLE`, `OPTIMIZE TABLE`, or `REPAIR TABLE` operation once started.

---

# 15. Security Considerations

- Do not expose internal table names, storage engine details, file paths, or raw error-log content in user-facing error output, since it can reveal internal application and infrastructure structure to an unauthenticated visitor.
- Preserve a copy of the affected files and relevant logs before any repair action, both to support recovery and because repeated or unexplained corruption isolated to specific tables can indicate unauthorized filesystem-level access or tampering rather than routine hardware or software failure; treat that possibility as a security consideration rather than assuming it is always routine.
- Coordinate any operation requiring elevated server access or a server restart (`myisamchk`/`aria_chk`, `innodb_force_recovery`) through a platform-appropriate, auditable process rather than ad hoc action directly against a production server.
- Do not restore from a backup whose integrity or provenance cannot be verified.
- Avoid granting broader filesystem or server access than the specific recovery operation requires, consistent with the minimum-necessary-privilege principle already documented for this cluster's own permission-related entry (WP-ERROR-004).

---

# 16. Related Errors

The following are cited as they exist in this repository, or as conceptual distinctions where noted.

1. [WP-ERROR-002 — WordPress Database Authentication Failure](WP-ERROR-002-WORDPRESS-DATABASE-AUTHENTICATION-FAILURE.md) — exists in this repository; see Section 6 (Distinction) above.
2. [WP-ERROR-003 — Database Does Not Exist](WP-ERROR-003-DATABASE-DOES-NOT-EXIST.md) — exists in this repository; see Section 6 (Distinction) above.
3. [WP-ERROR-004 — Database Permission Denied](WP-ERROR-004-DATABASE-PERMISSION-DENIED.md) — exists in this repository; see Section 6 (Distinction) above.
4. [WP-ERROR-005 — WordPress Database Schema Missing or Incomplete](WP-ERROR-005-DATABASE-SCHEMA-MISSING-OR-INCOMPLETE.md) — exists in this repository; see Section 6 (Distinction) above.
5. [WP-ERROR-007 — Database Connection Limit Exceeded](WP-ERROR-007-WORDPRESS-DATABASE-CONNECTION-LIMIT-EXCEEDED.md) — exists in this repository; see Section 6 (Distinction) above.
6. [WP-ERROR-008 — WordPress Database Server Unreachable](WP-ERROR-008-WORDPRESS-DATABASE-SERVER-UNREACHABLE.md) — exists in this repository; see Section 6 (Distinction) above.
7. [WP-ERROR-009 — Database Query Timeout](WP-ERROR-009-DATABASE-QUERY-TIMEOUT.md) — exists in this repository; see Section 6 (Distinction) above.
8. [WP-ERROR-018 — WordPress Database Connection Failure](WP-ERROR-018-WORDPRESS-DATABASE-CONNECTION-FAILURE.md) — exists in this repository; see Section 6 (Distinction) above.

---

# 17. Notes

This entry documents the specific, verified condition of a corrupted table's storage structure on an otherwise fully reachable, authenticated, selected, and privileged database whose schema definition is itself intact, as one of several specific causes deferred by the general connection-failure entry, WP-ERROR-018. It does not restate WP-ERROR-018's own boundary, its relationship to WP-ERROR-013, WP-ERROR-014, or WP-ERROR-016, or the general diagnostic process for narrowing an unspecified connection failure to a specific cause; see WP-ERROR-018 for that content. Consistent with the single-responsibility principle in **SF-SPEC-001** Section 4.3, this entry covers both the MyISAM/Aria "crashed" manifestation and InnoDB's own, materially different corruption and recovery model as one cohesive failure mode, since both share the same underlying, observable condition — a table's own storage structure is damaged or inconsistent, distinct from its schema's presence or shape.

This entry's governing direction specified the failure boundary, eight required distinctions (MyISAM/Aria versus InnoDB; root cause versus observable condition; missing rows or logically incorrect data; filesystem/disk failure as underlying cause versus corruption as the observable condition; crashed/repairable MyISAM versus InnoDB requiring engine-specific recovery; a transient lock, deadlock, or unavailable table; a failed database server connection; and intentional table deletion or an incomplete migration), and an explicit constraint against treating `REPAIR TABLE` or `wp db repair` as a universal remedy. The specific technical grounding — MySQL/MariaDB error codes 1194, 1195, 1034, and 1035; MyISAM's open-count "crashed" flag mechanism; InnoDB's redo-log crash recovery, its lack of genuine `REPAIR TABLE` support, and the documented `innodb_force_recovery` levels and their risks; and WP-CLI's `wp db check`/`wp db repair` commands and the `mysqlcheck` utility they invoke — was independently verified against current MySQL, MariaDB, and WP-CLI documentation before inclusion, following this catalog's established practice.

With this entry, WP-ERROR-002, 003, 004, 005, 006, 007, 008, 009, and 018 — the full set of specific causes and the general condition WP-ERROR-018 defers to them — now exist in this repository.

This entry completes the eight-entry database cluster (WP-ERROR-002 through 009, plus the general-condition entry WP-ERROR-018). Cross-references in WP-ERROR-005 and WP-ERROR-018, both of which cite this entry conceptually pending its creation, are updated separately following this entry's promotion, per this catalog's established practice of updating sibling cross-references in a dedicated commit distinct from the one introducing the new entry. A cluster-level consistency review across all eight entries is conducted separately following that cross-reference update.

This entry underwent the review sequence required by **SF-SPEC-001** Section 19, **SF-SPEC-005** Section 5.6, and **SF-SPEC-012**: an author (Class A) review at `docs/reviews/SF-REVIEW-030-WP-ERROR-006-AUTHOR-REVIEW.md`, followed by an independent (Class B) review at `docs/reviews/SF-REVIEW-031-WP-ERROR-006-INDEPENDENT-REVIEW.md`, which reached outcome **Approved with Minor Revisions**, applied and re-validated the one required revision, and satisfied the Production Ready gate per SF-SPEC-012 Section 12. Its Status was changed to Production Ready on that basis. This document does not itself constitute either review record; see the cited files for full findings, corrections, and gate decisions.

The independent review did not designate this entry as a Reference Implementation. That designation, governed separately by **SF-SPEC-001** Section 22, has not been sought or asserted here.

No Reference Implementation is currently designated by **SF-SPEC-001**; this entry's relationship to that designation, and to any future `WP-SCENARIO-XXX` runtime evidence, is not asserted here and shall not be assumed until such evidence or designation actually exists.
