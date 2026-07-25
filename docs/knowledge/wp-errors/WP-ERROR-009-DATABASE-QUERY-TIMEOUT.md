# WP-ERROR-009 — WordPress Database Query Timeout

---

# 1. Knowledge Entry

WordPress Database Query Timeout

---

# 2. Metadata

* **Error ID:** `WP-ERROR-009`
* **Title:** WordPress Database Query Timeout
* **Category:** Database
* **Severity:** High
* **Recovery Priority:** High
* **Status:** Production Ready
* **Version:** 1.2

---

# 3. Summary

WordPress successfully establishes a database connection, authenticates, selects the named database, and holds sufficient privileges, but a specific query subsequently fails to complete within an applicable time or resource limit. This is one specific, verified cause within the general connection-failure condition documented by WP-ERROR-018.

---

# 4. Primary Failure Mode

WordPress's database layer (`wpdb`) successfully completes every earlier step in the connection lifecycle — network connection, authentication, database selection, and privilege checks — and issues a specific query, but that query does not complete before an applicable time limit is reached. This condition can be enforced at several distinct layers, each configured independently: the database server's own statement-execution limit, the PHP database driver's own read or query timeout, PHP's own overall script execution-time limit where that limit actually counts the database wait, or a web-server or gateway-level request timeout. A central point of confusion this entry addresses directly: MySQL's own `max_execution_time` system variable and PHP's `max_execution_time` directive in `php.ini` are two entirely unrelated settings that happen to share an identical name — the former limits how long the database server will run a single statement, while the latter limits measured PHP script execution. PHP's measurement is platform-dependent: on Windows it uses real elapsed time and can therefore include a database wait; on non-Windows systems, PHP documents database-query time as excluded, so a blocking database call does not by itself consume that PHP limit. Diagnosis requires identifying which specific layer actually enforced the observed timeout, since each is configured in a different place and points to a different corrective action.

---

# 5. Severity

This entry is classified **High** rather than **Critical**, which is a deliberate departure from every other entry in this cluster, justified as follows:

- By this entry's own definition, the database connection was already fully usable — network connectivity, authentication, database selection, and privileges all succeeded. This entry never covers a condition in which the connection itself is unusable or the site has no functioning request path at all; those conditions belong to WP-ERROR-002, 003, 004, 007, and 008.
- The impact of a query timeout is, by definition, scoped to the specific operation whose query failed to complete — ordinary browsing and unaffected operations continue to function normally, which is a materially narrower blast radius than the full-site outage every other entry in this cluster covers.
- Despite the narrower typical scope, this entry remains **High** rather than **Medium** or **Low** because the affected operation is frequently essential (for example, checkout, search, content saving, or a scheduled task), and because an underlying cause such as lock contention or server resource exhaustion can, in some cases, degrade performance for other concurrent queries as well, not only the one that timed out.

---

# 6. Distinction

This entry applies only when verified evidence establishes that the database connection was fully established, authenticated, and privileged, and that a specific, actually-executing query failed to complete within an applicable time or resource limit — not that the connection itself failed, was rejected, or never reached a state capable of executing a query at all.

It is distinct from:

- **WP-ERROR-002 — WordPress Database Authentication Failure**, **WP-ERROR-007 — Database Connection Limit Exceeded**, and **WP-ERROR-008 — WordPress Database Server Unreachable**: all three occur before a connection is ever usable, so no query could have been issued at all. This entry presumes a connection was fully usable and a specific query was actually issued and executing.
- **WP-ERROR-003 — Database Does Not Exist** and **WP-ERROR-004 — Database Permission Denied**: both presume the failure occurs at or before database selection or a privilege check, before a query is reached. This entry presumes both database selection and privilege checks already succeeded.
- **WP-ERROR-007's `wait_timeout`/`interactive_timeout`**: those settings govern how long the database server holds an *idle* connection open — one with no query actively executing — before reclaiming it. This entry concerns a query that *is* actively executing and fails specifically because of its own duration, a materially different condition from an idle connection being reclaimed.
- **WP-ERROR-018 — WordPress Database Connection Failure**: WP-ERROR-018 owns the general, verified-but-unspecified-cause condition where WordPress cannot establish a database connection, and explicitly identifies a query not completing in time as one specific, verified cause that belongs to this entry once confirmed. This entry owns that specific, verified cause; WP-ERROR-018 owns the general condition and the diagnostic process for narrowing it to a specific cause, along with this entry's relationship to WP-ERROR-013, WP-ERROR-014, and WP-ERROR-016, which this entry does not restate.

---

# 7. Scope

**Covered:** A verified condition in which the database connection was successfully established, authenticated, and privileged, and a specific query subsequently failed to complete within an applicable time or resource limit, whether enforced by the database server's own statement-execution limit, the PHP database driver's own timeout, PHP's own script execution-time limit on a platform where database-wait time is counted, or a web-server or gateway-level request timeout.

**Excluded:**

- Network-level unreachability, authentication rejection, connection-limit refusal, the database not existing, or insufficient privileges — all of which occur before a query can be issued at all (see WP-ERROR-002, 003, 004, 007, 008).
- An idle connection being reclaimed by the database server's `wait_timeout`/`interactive_timeout` setting, where no query is actively executing (see WP-ERROR-007).
- A general PHP script-execution timeout whose cause is unrelated to a database query — for example, a slow external HTTP request or an unrelated CPU-bound operation.
- Any condition in which the query in question completes successfully, regardless of how long it takes, as long as no applicable limit was actually exceeded.

---

# 8. WordPress Components

Listed as components commonly involved, not as a claim that every installation exercises every one of them identically:

- The `wpdb` class (`wp-includes/class-wpdb.php`), specifically its query-execution logic (`wpdb::query()`), which surfaces a database-server-side statement-timeout error as a query-level failure — populating the `$wpdb->last_error` property and, when error display is enabled, an inline "WordPress database error" message — distinct from a connection-establishment failure.
- The PHP database driver (`mysqli` or `PDO_MySQL`) and its own timeout settings (for example, `mysqli`'s `MYSQLI_OPT_READ_TIMEOUT`, or PDO's `PDO::ATTR_TIMEOUT`), which can terminate a slow query from the client side independently of the database server's own statement timeout.
- PHP's own script execution-time limit (`max_execution_time` in `php.ini`, or `set_time_limit()`), which is general PHP behavior rather than a database-specific limit and is platform-dependent. PHP documents that Windows measures real elapsed time, so a blocking database query can consume this limit there; non-Windows systems exclude time spent in database queries and other external operations, so a slow query by itself does not consume the PHP limit there. This setting shares an identical name with MySQL's own `max_execution_time` system variable, but the two are entirely unrelated.
- Web-server or gateway-level request-timeout settings (for example, PHP-FPM's `request_terminate_timeout`, or a reverse proxy's read timeout), which can enforce wall-clock limits independently of PHP's own platform-dependent execution-time accounting and can terminate the request due to the same underlying slow query.
- `WP-Cron` and WP-CLI, whose scheduled or command-line query execution is not subject to the same web-request timeout constraints as an ordinary front-end request, and which are a common destination for moving an inherently long-running, legitimate query out of the request path.

---

# 9. Typical Symptoms

- A specific WordPress operation (a search, a report, an export, a large listing page, or a specific administrative screen) consistently taking a very long time or failing to complete, while ordinary browsing and other operations continue to work normally.
- A database-level statement-timeout error, such as MySQL error `3024` or MariaDB `ER_STATEMENT_TIMEOUT` error `1969` with SQLSTATE `70100`, visible in logs where accessible. Treat the numeric error, symbolic name, and SQLSTATE as the stable identifiers rather than requiring one exact message string: MariaDB documentation records "Query execution was interrupted (max_statement_time exceeded)," while MariaDB 12.3.2 emitted the version-specific text "Query was interrupted: execution time limit 0.2 sec exceeded" in `WP-VERIFICATION-017`.
- PHP's own "Maximum execution time of N seconds exceeded" fatal error, where the underlying cause is a specific slow query and the runtime platform counts database-wait time toward the PHP limit. On non-Windows systems, a database wait alone does not produce this signal because PHP excludes database-query time from its execution-time calculation; an otherwise-identical CPU-bound script can still produce the fatal error.
- A blank page, a gateway timeout response (for example, an HTTP 504), or a truncated response for a specific operation, without WordPress's own generic connection-failure message, since the connection itself was successfully established and only a specific query failed to complete in time.
- The same operation succeeding when run directly against the database outside of the usual web-request timeout constraints (for example, via WP-CLI or a direct database client), suggesting a timeout-configuration or request-path issue rather than the query being universally too slow to ever complete.
- Entries in the database server's slow-query log corresponding to the times and operations reported as failing.

---

# 10. Common Causes

Causes are grouped by category. Inclusion in this list identifies a category as plausible; it does not assert that any specific cause is present without diagnostic confirmation.

- Missing or ineffective database indexes causing a full table scan on a large table.
- A poorly optimized or overly complex query — for example, an inefficient join, an unnecessary subquery, or a plugin issuing a query without an appropriate `WHERE` or `LIMIT` clause.
- Table growth over time (for example, `wp_options` autoloaded-data bloat, or a large `wp_postmeta` or `wp_usermeta` table) making a previously fast query slow as data volume increases.
- Lock contention or blocking from other concurrent queries or transactions holding locks on the same rows or tables.
- A sudden spike in data volume or concurrent load exceeding what a previously-acceptable query plan could handle.
- Database server resource exhaustion (CPU, memory, or I/O contention) slowing queries generally, not only one specific query.
- A plugin or custom code issuing an unusually expensive query — for example, a report, export, or search feature performing a full scan without appropriate indexing.
- A long-running administrative operation (a large data migration, a bulk update, or an `ALTER TABLE` on a large table) competing with ordinary request traffic.
- Aggressively low timeout values configured at the database, driver, PHP, or gateway level relative to the site's actual, legitimate query needs.
- An object-cache backend outage or degradation causing requests that would otherwise have been served from cache to issue a database query instead, increasing overall query volume — see [WP-ERROR-033](WP-ERROR-033-PERSISTENT-OBJECT-CACHE-BACKEND-UNAVAILABLE.md).

---

# 11. Diagnosis

Verify the following:

1. Confirm this is genuinely a query-timeout condition — occurring after connection establishment, authentication, database selection, and privilege checks have all already succeeded — rather than an earlier-stage failure documented elsewhere in this cluster.
2. Identify exactly which layer enforced the observed timeout: the database server's own statement-execution limit (MySQL error 3024 or MariaDB `ER_STATEMENT_TIMEOUT` error 1969 with SQLSTATE `70100`), the PHP database driver's own read or query timeout, PHP's own overall script execution-time limit on a platform where database-wait time is counted (distinct from MySQL's identically-named setting), or a web-server or gateway-level request timeout — since each is configured in a different place and points to a different corrective action. Record the operating system and SAPI before attributing a slow database call to PHP `max_execution_time`: Windows measures real elapsed time, while non-Windows PHP excludes database-query time. Do not require an exact MariaDB message-text match, because the wording is version-dependent even when the error number, symbolic name, and SQLSTATE identify the same condition.
3. Capture the exact query that was executing when the timeout occurred, using WordPress's own query logging (for example, `SAVEQUERIES` with `WP_DEBUG`), WP-CLI's built-in profiling commands (`wp profile stage` and `wp profile hook`, which break down where execution time is actually spent across a request), or the database server's own slow-query log, rather than assuming which query was responsible.
4. Where the database server's slow-query log is available, review it for the specific query's actual execution time and its query plan (for example, via `EXPLAIN`), to determine whether the query itself is inherently expensive or was delayed by contention from other queries. Confirm the server's `long_query_time` setting, which determines the threshold above which a query is actually recorded in the slow-query log, since a threshold set too high can mean a genuinely slow query never appears there at all.
5. Determine whether the slow query is reproducible in isolation (run directly against the database outside of WordPress) or only under concurrent production load, since the two point toward different causes.
6. Where the database server's own statement timeout killed the query, confirm the connection itself remained usable afterward for subsequent operations, distinguishing this from a connection-level failure (see WP-ERROR-002, WP-ERROR-007, WP-ERROR-008).
7. Where PHP's own overall execution-time limit terminated the request, first confirm that the runtime platform can count database-wait time toward that limit and that the fatal error occurred while the query was responsible, rather than from unrelated CPU-bound PHP work. Then confirm whether the underlying query was still running on the database server afterward, since a PHP-level timeout does not necessarily stop the query's execution server-side; check the server's own process list for a still-running query with a duration exceeding what the terminated PHP request would suggest. If termination is warranted, `KILL QUERY <id>` stops only that executing statement without closing the connection; `KILL <id>` or `KILL CONNECTION <id>` terminates the entire connection and should only be used when that broader action is actually intended.
8. Determine whether the timeout is isolated to a single specific query and feature, or whether queries generally are slow, since the former points toward a specific query's optimization while the latter points toward server-wide resource exhaustion or contention.
9. Confirm the currently configured timeout values at each relevant layer, since an aggressively low value at any one layer can trigger this condition even for a query that would otherwise complete successfully given more time.
10. Preserve relevant evidence — the exact query text, its execution plan, timing data, and the specific layer and configured limit that triggered the timeout — before making any change.
11. Where the engineer performing diagnosis does not control the database server, hosting environment, or gateway configuration, escalate to the database administrator or hosting provider rather than attempting an unverified workaround.

---

# 12. Recovery Procedure

Recovery shall address the underlying cause of the slow query, not merely raise a timeout value to accommodate it.

Permitted recovery categories, depending on the verified cause, include:

- Optimizing the specific slow query where diagnosis confirms it is inherently expensive — for example, adding an appropriate index, rewriting an inefficient join or subquery, or adding a `LIMIT` where the full result set is not actually needed.
- Addressing lock contention or blocking queries at their source — for example, scheduling a long-running administrative operation during low-traffic periods instead of during peak load — where diagnosis confirms contention, rather than the specific query's own logic, is the cause.
- Addressing underlying database server resource exhaustion (CPU, memory, or I/O) where diagnosis confirms general server load, rather than one specific query, is the cause.
- Moving an inherently resource-intensive but legitimate operation (for example, a large data export) to a background or asynchronous process, such as WP-Cron or WP-CLI, rather than expecting it to complete within a typical web-request timeout.
- Terminating a specific, still-running query identified as safe to terminate — using `KILL QUERY <id>`, which stops only that executing statement and leaves the connection open, rather than `KILL <id>`/`KILL CONNECTION <id>`, which terminates the entire connection and should be reserved for cases where closing the connection itself is actually intended — where diagnosis confirms it is no longer needed, rather than leaving it consuming resources indefinitely.
- Increasing the specific timeout value at the layer diagnosis confirms is enforcing an unnecessarily aggressive cutoff, but only after confirming the underlying query is otherwise legitimate and reasonably optimized — not as a substitute for fixing an inherently expensive or unbounded query.
- Escalating to the database administrator or hosting provider where the engineer performing diagnosis does not control the relevant timeout configuration or server resources.

Recovery shall not indiscriminately raise timeout values across the board as a substitute for identifying and addressing the specific slow query or underlying resource constraint; doing so can mask a worsening performance problem and allow a single slow request to consume resources for longer, at the expense of concurrent requests.

---

# 13. Validation

Recovery is successful when:

- The previously timing-out operation completes successfully within a reasonable time, confirmed by reproducing the exact action that previously failed, under conditions comparable to when the failure occurred, including realistic concurrent load where contention was a suspected cause.
- The query's execution plan and timing, confirmed via `EXPLAIN` or the slow-query log, show the expected improvement where a query-optimization fix was applied.
- No equivalent timeout error recurs in logs across repeated, fresh attempts at the same and related operations.
- Any timeout value that was increased was based on a confirmed, legitimate need, and was not relied upon as the only corrective action.
- No previously-terminated query remains unexpectedly still running on the database server after the fix is applied.

---

# 14. Prevention

- Monitor the database server's slow-query log proactively, rather than discovering an expensive query only when a user reports a timeout, and confirm `long_query_time` is set low enough to actually capture queries relevant to the site's own performance expectations.
- Review query plans (`EXPLAIN`) for new or modified features, particularly those involving large tables, as part of development and code review, rather than only after a production timeout occurs.
- Schedule long-running administrative operations (large migrations, bulk updates, schema changes) during low-traffic periods, and monitor their impact on concurrent query performance.
- Document the timeout values configured at each layer (database statement timeout, driver timeout, PHP `max_execution_time`, and any gateway or proxy timeout), together with the operating system and SAPI that determine PHP's time accounting, so a future diagnosis does not need to rediscover which layer can actually enforce a given cutoff.
- Move inherently long-running, legitimate operations to background or asynchronous processing rather than expecting them to complete within an ordinary web request's timeout budget.
- Periodically review table growth and indexing strategy as data volume increases, rather than assuming a query that was once fast will remain fast indefinitely.

---

# 15. Security Considerations

- Do not raise timeout values so high that a single slow or maliciously crafted query can consume database resources indefinitely, potentially enabling a denial-of-service condition against concurrent legitimate requests.
- Treat an unexplained, newly appearing pattern of query timeouts as a potential signal of a resource-exhaustion attempt (for example, a deliberately expensive search or export request submitted repeatedly) rather than assuming it is always routine performance degradation.
- Avoid exposing raw query text or execution plans in user-facing error output, since it can reveal internal schema details and query logic to an unauthenticated visitor.
- Coordinate any change to shared database server timeout settings through a platform-appropriate process, since such changes affect every application sharing that server, not only WordPress.

---

# 16. Related Errors

The following are cited as they exist in this repository, or as conceptual distinctions where noted.

1. [WP-ERROR-002 — WordPress Database Authentication Failure](WP-ERROR-002-WORDPRESS-DATABASE-AUTHENTICATION-FAILURE.md) — exists in this repository; see Section 6 (Distinction) above.
2. [WP-ERROR-003 — Database Does Not Exist](WP-ERROR-003-DATABASE-DOES-NOT-EXIST.md) — exists in this repository; see Section 6 (Distinction) above.
3. [WP-ERROR-004 — Database Permission Denied](WP-ERROR-004-DATABASE-PERMISSION-DENIED.md) — exists in this repository; see Section 6 (Distinction) above.
4. [WP-ERROR-007 — Database Connection Limit Exceeded](WP-ERROR-007-WORDPRESS-DATABASE-CONNECTION-LIMIT-EXCEEDED.md) — exists in this repository; see Section 6 (Distinction) above.
5. [WP-ERROR-008 — WordPress Database Server Unreachable](WP-ERROR-008-WORDPRESS-DATABASE-SERVER-UNREACHABLE.md) — exists in this repository; see Section 6 (Distinction) above.
6. [WP-ERROR-018 — WordPress Database Connection Failure](WP-ERROR-018-WORDPRESS-DATABASE-CONNECTION-FAILURE.md) — exists in this repository; see Section 6 (Distinction) above.

---

# 17. Notes

This entry documents the specific, verified condition of a query failing to complete within an applicable time or resource limit on an otherwise fully established, authenticated, and privileged connection, as one of several specific causes deferred by the general connection-failure entry, WP-ERROR-018. It does not restate WP-ERROR-018's own boundary, its relationship to WP-ERROR-013, WP-ERROR-014, or WP-ERROR-016, or the general diagnostic process for narrowing an unspecified connection failure to a specific cause; see WP-ERROR-018 for that content. Consistent with the single-responsibility principle in **SF-SPEC-001** Section 4.3, this entry covers the four distinct enforcement layers (database server, driver, PHP, gateway) that can each independently produce a query-timeout condition as one cohesive failure mode, since all four share the same underlying trigger — a specific query not completing in time; cause-specific conditions for individual query-optimization defects in specific plugins may each be documented by a separate, independently created `WP-ERROR` entry without altering this one.

This entry's Severity (`High`) and Recovery Priority (`High`) deliberately depart from the `Critical`/`Immediate` classification used by every other entry in this cluster. This is a considered engineering judgment, not an oversight: this entry's own Scope excludes any condition in which the connection itself is unusable, so the condition it documents is inherently narrower in blast radius than a full-site outage, even though it can still affect essential functionality.

This entry's governing direction was a recommendation describing the failure boundary (connection established, authenticated, privileged, but a specific query exceeds an applicable timeout) rather than a fully itemized formal work order; per the pattern established for WP-ERROR-003 and WP-ERROR-004, the missing formal details (technical grounding, section requirements) were self-authored following this catalog's established practice.

This entry underwent the review sequence required by **SF-SPEC-001** Section 19, **SF-SPEC-005** Section 5.6, and **SF-SPEC-012**: an author (Class A) review at `docs/reviews/SF-REVIEW-026-WP-ERROR-009-AUTHOR-REVIEW.md`, followed by an independent (Class B) review at `docs/reviews/SF-REVIEW-027-WP-ERROR-009-INDEPENDENT-REVIEW.md`, which reached outcome **Approved with Minor Revisions**, applied and re-validated the one required revision, and satisfied the Production Ready gate per SF-SPEC-012 Section 12. Its Status was changed to Production Ready on that basis. This document does not itself constitute either review record; see the cited files for full findings, corrections, and gate decisions.

The independent review did not designate this entry as a Reference Implementation. That designation, governed separately by **SF-SPEC-001** Section 22, has not been sought or asserted here.

No Reference Implementation is currently designated by **SF-SPEC-001**; this entry's relationship to that designation, and to any future `WP-SCENARIO-XXX` runtime evidence, is not asserted here and shall not be assumed until such evidence or designation actually exists.

---

## Revision History

| Version | Date | Summary | Approval Status |
|---|---|---|---|
| 1.0 | 2026-07-13 | Initial Production Ready entry. | Approved via SF-REVIEW-026/027 |
| 1.1 | 2026-07-25 | Post-certification correction prompted by WP-VERIFICATION-017 runtime evidence. Replaced a single supposedly universal MariaDB error-1969 quotation with stable identifiers (`ER_STATEMENT_TIMEOUT`, `1969`, SQLSTATE `70100`) and documented that message wording varies by MariaDB version. Failure ownership, severity, diagnosis order, and recovery guidance are unchanged. | Reviewed via SF-REVIEW-232/233; Database re-certified via SF-REVIEW-234/235 |
| 1.2 | 2026-07-25 | Post-certification portability correction prompted by WP-VERIFICATION-017 Case 03 feasibility evidence and PHP's official execution-time documentation. Qualified PHP `max_execution_time` throughout: Windows counts real elapsed time and can include a database wait, while non-Windows PHP excludes database-query time. Database ownership and the database-server, driver, and gateway timeout boundaries are unchanged. | Reviewed via SF-REVIEW-236/237; Database re-certified via SF-REVIEW-238/239 |
