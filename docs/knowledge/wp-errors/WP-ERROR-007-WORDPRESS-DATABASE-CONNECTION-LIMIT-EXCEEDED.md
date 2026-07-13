# WP-ERROR-007 — WordPress Database Connection Limit Exceeded

---

# 1. Knowledge Entry

WordPress Database Connection Limit Exceeded

---

# 2. Metadata

* **Error ID:** `WP-ERROR-007`
* **Title:** WordPress Database Connection Limit Exceeded
* **Category:** Database
* **Severity:** Critical
* **Recovery Priority:** Immediate
* **Status:** Production Ready
* **Version:** 1.0

---

# 3. Summary

WordPress reaches the configured MySQL or MariaDB server, and the server actively responds, but it refuses the new connection because an applicable connection-capacity limit — server-wide or specific to the connecting database user account — has already been exhausted. This is one specific, verified cause within the general connection-failure condition documented by WP-ERROR-018.

---

# 4. Primary Failure Mode

WordPress's database layer (`wpdb`) completes a network connection to the configured database server, and the server itself responds — but that response is an explicit protocol-level refusal because the server has no remaining connection capacity to grant, either because the server-wide connection limit has been reached or because the specific database user account WordPress connects as has reached its own, separately enforced per-account limit. This differs from every other cause WP-ERROR-018 defers to a specific entry: the server is reachable and running, and it actively participates in the exchange rather than being silent or absent; it declines the connection specifically because of exhausted capacity, not because of rejected credentials, a missing database, insufficient privileges, or a network-level failure to reach it at all.

---

# 5. Severity

This entry is classified **Critical** because, by definition, it covers a verified condition that prevents WordPress from obtaining any new database connection:

- No later step (authentication, database selection, permission checks, querying) can be reached for a new request without a connection first being granted.
- The condition can affect front-end, administrative, AJAX, cron, REST, and WP-CLI paths simultaneously once capacity is exhausted, since all compete for the same finite connection pool.
- Remediation cannot be deferred, since new requests continue to fail while the underlying capacity remains exhausted, even though already-established connections may continue to function normally.

---

# 6. Distinction

This entry applies only when verified evidence establishes that the database server was reached, responded, and explicitly refused the new connection due to exhausted connection capacity — not that the server was unreachable, rejected credentials, or failed at a later step after a connection was already granted.

It is distinct from:

- **WP-ERROR-002 — WordPress Database Authentication Failure**: presumes connection capacity was available and the server evaluated the supplied credentials, rejecting them specifically. This entry's condition can occur before credentials are meaningfully evaluated at all — a server-wide capacity refusal is a resource decision, not a judgment about the credentials themselves. Even the per-account limit this entry covers is a capacity check against an already-identified account, distinct from that account's password being wrong.
- **WP-ERROR-003 — Database Does Not Exist**: presumes a connection was successfully granted and authenticated, with the failure occurring afterward when selecting a specific, named database that is not present. This is a later step than this entry's boundary.
- **WP-ERROR-004 — Database Permission Denied** (conceptual reference; no corresponding document currently exists in this repository): presumes a connection was successfully granted, authenticated, and a database selected, with the failure occurring because the authenticated user lacks privileges for a specific operation. This is a later step than this entry's boundary.
- **WP-ERROR-008 — WordPress Database Server Unreachable**: the network path to the server itself fails, so the server never receives the connection attempt and never has an opportunity to respond at all. This entry's condition is the opposite in that specific respect: the server is reached, is running, and actively responds — it simply declines to grant the connection because its capacity is exhausted.
- **WP-ERROR-009 — Database Query Timeout** (conceptual reference; no corresponding document currently exists in this repository): presumes a connection was already granted and established, with the failure occurring later because a specific query does not complete in time. This entry's condition prevents the connection from being granted in the first place, before any query could be issued.
- **WP-ERROR-018 — WordPress Database Connection Failure**: WP-ERROR-018 owns the general, verified-but-unspecified-cause condition where WordPress cannot establish a database connection, and explicitly identifies connection-limit exhaustion as one specific, verified cause that belongs to this entry once confirmed. This entry owns that specific, verified cause; WP-ERROR-018 owns the general condition and the diagnostic process for narrowing it to a specific cause, along with this entry's relationship to WP-ERROR-013, WP-ERROR-014, and WP-ERROR-016, which this entry does not restate.

---

# 7. Scope

**Covered:** A verified condition in which the database server was reached and responded, but declined to grant a new connection because server-wide connection capacity (`max_connections`) or a per-account connection limit (`MAX_USER_CONNECTIONS`) applicable to the connecting database user has already been exhausted.

**Excluded:**

- Network-level unreachability of the database server, where the server never has an opportunity to respond at all (see WP-ERROR-008).
- Authentication rejection of the supplied credentials, once connection capacity was available (see WP-ERROR-002).
- Selection of a nonexistent database, or insufficient privileges, after a connection was already successfully granted (see WP-ERROR-003 and WP-ERROR-004).
- Query timeouts occurring after a connection was already successfully granted and established (see WP-ERROR-009).
- Any condition in which a connection is successfully granted, regardless of what happens afterward.

---

# 8. WordPress Components

Listed as components commonly involved, not as a claim that every installation exercises every one of them identically:

- The `wpdb` class (`wp-includes/class-wpdb.php`), specifically its connection-establishment logic, shared with WP-ERROR-018. By default, `wpdb` opens a new, non-persistent connection for each request rather than reusing a connection across requests, so sustained exhaustion under this entry's condition is typically driven by concurrency (many simultaneous requests) rather than a single long-lived connection.
- The `DB_USER` constant defined in `wp-config.php`, which determines which database account's per-account connection limit (if any) applies.
- `wp-cron.php` and WP-CLI, which open their own database connections independently of, and concurrently with, ordinary web request traffic.
- Any custom database-access code — direct `mysqli`/PDO usage in a plugin, theme, or external integration, or a background worker process — that opens connections outside `wpdb`'s own connection handling and may not release them promptly.
- The database server's own connection-management configuration: the server-wide `max_connections` system variable, the per-account `MAX_USER_CONNECTIONS` resource limit (settable per grant), and the server's reserved connection for a user holding the `SUPER` (or, in MySQL 8, `CONNECTION_ADMIN`) privilege, which remains available one connection beyond `max_connections` specifically so an administrator can connect during exhaustion.
- The web/application tier's own concurrency configuration (for example, PHP-FPM's `pm.max_children`, or an Apache MPM's worker limit), whose maximum concurrent-request count directly bounds how many simultaneous database connections WordPress itself can attempt to hold open.
- Any connection-pooling or proxy layer between WordPress and the database server, which may enforce its own, separate connection limit distinct from the database server's native configuration.

---

# 9. Typical Symptoms

- WordPress's own generic "Error establishing a database connection" message, indistinguishable at the WordPress level from WP-ERROR-018's other specific causes; the specific capacity-exhaustion detail is visible only in server-side or PHP-level logs, not in WordPress's own user-facing message.
- A database-level error such as MySQL/MariaDB error `1040` ("Too many connections"), indicating server-wide `max_connections` exhaustion, or error `1203` ("User ... already has more than ... active connections"), indicating the specific connecting account's `MAX_USER_CONNECTIONS` limit has been reached — visible in logs where accessible.
- The failure occurring intermittently and correlating with traffic spikes, scheduled batch jobs, or specific times of day, rather than constantly, since capacity exhaustion depends on concurrent demand at a given moment.
- New connection attempts failing while requests that already hold an established connection continue to complete normally, since exhaustion blocks new connections rather than terminating existing ones.
- Elevated `Threads_connected` relative to the configured `max_connections`, visible via `SHOW STATUS LIKE 'Threads_connected'` and `SHOW VARIABLES LIKE 'max_connections'` where server access is available.
- Other applications or services sharing the same database server experiencing the same failure at the same time, where applicable, indicating server-wide rather than WordPress-specific exhaustion.
- A hosting control panel or managed-database dashboard reporting a connection-count metric at or near its displayed maximum.

---

# 10. Common Causes

Causes are grouped by category. Inclusion in this list identifies a category as plausible; it does not assert that any specific cause is present without diagnostic confirmation.

- Server-wide `max_connections` exhaustion (MySQL/MariaDB error 1040), where the total number of connections across all accounts and applications sharing the server has reached its configured maximum.
- Per-account `MAX_USER_CONNECTIONS` exhaustion (MySQL/MariaDB error 1203) for the specific database user WordPress connects as, distinct from the server-wide limit.
- Excessive concurrent WordPress requests relative to configured connection capacity — for example, a PHP-FPM or Apache worker-concurrency setting that permits more simultaneous requests than the database's `max_connections` can support.
- Long-running or abandoned database sessions — connections left open idle, or queries running far longer than expected — consuming capacity that would otherwise be available to new requests; the server's own `wait_timeout` (and `interactive_timeout`) setting governs how long such an idle connection is held before the server reclaims it itself, and an excessively high value allows abandoned sessions to persist far longer than necessary.
- Persistent-connection misuse — a non-default configuration causing PHP database connections to remain open across requests longer than intended, accumulating rather than being promptly released.
- Connection leaks in plugins, themes, external integrations, or background/worker processes that open database connections outside `wpdb`'s own connection handling and fail to close them.
- Sudden traffic spikes (for example, a marketing campaign, a viral post, or a bot or scraper surge) exceeding provisioned connection capacity.
- Undersized database infrastructure relative to actual, sustained legitimate demand.
- Administrative or monitoring connections consuming reserved connection capacity intended for such use, reducing what remains available for ordinary application connections.
- Hosting-provider-imposed database connection limits that are lower than the MySQL/MariaDB server configuration itself would otherwise allow, particularly on shared or budget hosting tiers.

---

# 11. Diagnosis

Verify the following:

1. Confirm this is genuinely a connection-capacity refusal — an explicit error such as MySQL/MariaDB 1040 or 1203 — rather than network unreachability, authentication rejection, or a later-stage condition that presumes a connection was already granted.
2. Capture the exact error code and text where accessible, and determine whether it is error 1040 (server-wide) or error 1203 (specific to one database account), since the two point to different scopes and different corrective actions.
3. Where server access is available, check `SHOW VARIABLES LIKE 'max_connections'` against `SHOW STATUS LIKE 'Threads_connected'` to determine current utilization relative to the configured limit. Where error 1203 (per-account) rather than 1040 (server-wide) was observed, separately confirm the specific `MAX_USER_CONNECTIONS` value granted to the connecting account (for example, via `SHOW GRANTS` for that account), since this is a distinct value from `max_connections` and is not visible from the server-wide status check alone.
4. Run `SHOW PROCESSLIST` or `SHOW FULL PROCESSLIST` to inspect currently held connections — identify long-running queries, idle or sleeping connections held open, and the originating host and user for each, noting whether a small number of sources account for a disproportionate share of connections.
5. Compare `Threads_running` (actively executing) against `Threads_connected` (all open); a large gap indicates many idle or leaked connections rather than genuine concurrent work.
6. Determine whether exhaustion is constant or occurs only under traffic spikes or specific scheduled events (cron runs, batch imports, deployments), since intermittent exhaustion under load points toward a capacity or traffic-scaling issue rather than a persistent leak.
7. Where a specific plugin, theme, or external integration is suspected of leaking connections, inspect its own database-handling code or logs for connections opened without a corresponding, timely closure.
8. Confirm whether persistent database connections are in use — a non-default WordPress or hosting configuration — and whether that is contributing to connections remaining open across requests longer than necessary.
9. Compare the configured PHP-FPM or Apache worker-concurrency limit against the database's `max_connections` setting, since an application tier provisioned to run more concurrent requests than the database can accept is a common structural cause.
10. Determine whether the connections consuming capacity are ordinary application connections or administrative/monitoring connections consuming reserved capacity.
11. Where the environment is a managed or hosting-provider database service, consult the provider's connection-metrics dashboard or logs, since the enforced limit may be a hosting-tier restriction distinct from the visible MySQL/MariaDB configuration.
12. Preserve relevant evidence — `PROCESSLIST` snapshots, status-variable values, error text, and timestamps — before making any change, since connection state is transient and will not be reproducible after the fact.
13. Where the engineer performing diagnosis does not control the database server or hosting environment, escalate to the database administrator or hosting provider rather than attempting an unverified workaround.

---

# 12. Recovery Procedure

Recovery shall target the verified cause of the connection-capacity exhaustion, not merely relieve the symptom by increasing limits without understanding why capacity was exhausted.

Permitted recovery categories, depending on the verified cause, include:

- Correcting the source of leaked or abandoned connections — fixing a plugin, theme, or external integration that fails to close connections it opens, or that opens more connections than necessary.
- Terminating specific long-running or abandoned sessions identified as safe to terminate (for example, via `KILL` on a specific thread identified in `PROCESSLIST`), only after confirming the session is not performing necessary, in-progress work.
- Adjusting application-tier concurrency (PHP-FPM or Apache worker limits) to remain within the database's actual connection capacity, where diagnosis confirms the application tier is over-provisioned relative to the database.
- Correcting persistent-connection misconfiguration where diagnosis confirms connections are being held open longer than necessary.
- Increasing `max_connections` or a specific account's `MAX_USER_CONNECTIONS`, but only after confirming the underlying demand is legitimate and sustained rather than a leak or misconfiguration, and only where the underlying infrastructure has sufficient memory and file-descriptor capacity to support the increase safely.
- Scaling or upgrading undersized database infrastructure where diagnosis confirms genuine, sustained demand exceeds current capacity.
- Escalating to the hosting provider to request a connection-limit increase where the limit is enforced at the hosting-platform level rather than in MySQL/MariaDB configuration directly under the engineer's control.

Recovery shall not increase `max_connections`, `MAX_USER_CONNECTIONS`, or any hosting-tier connection limit as the sole or automatic response without first determining whether the underlying cause is a leak, a misconfiguration, or a concurrency mismatch that a limit increase would not actually resolve and could instead mask. Recovery shall be coordinated with the database administrator before changing server-wide connection settings, since such changes affect every application sharing that database server, not only WordPress.

---

# 13. Validation

Recovery is successful when:

- `Threads_connected` remains comfortably below `max_connections` under both normal and realistic peak load, confirmed over time rather than from a single snapshot.
- The specific underlying cause identified during diagnosis (a leak, a misconfiguration, an undersized capacity, or a genuine traffic increase) has been verifiably addressed, not merely that a connection attempt happened to succeed once.
- No equivalent connection-limit error (1040 or 1203) recurs in logs across repeated, fresh requests, including under peak-load conditions comparable to when the failure previously occurred.
- `PROCESSLIST` no longer shows an abnormal accumulation of idle or long-running connections from the previously identified source.
- Any limit increase that was made is based on confirmed, legitimate, sustained demand, and was not relied upon as the only corrective action.

---

# 14. Prevention

- Monitor `Threads_connected` relative to `max_connections` proactively, alerting before exhaustion actually occurs rather than after.
- Right-size `max_connections` and any per-account `MAX_USER_CONNECTIONS` limits relative to actual PHP-FPM or Apache worker concurrency and expected traffic, documenting the relationship and reviewing it periodically as traffic or infrastructure changes.
- Review plugin, theme, and integration code for proper connection lifecycle handling (closing what is opened) as part of code review, particularly for custom database-access code operating outside `wpdb`'s own connection handling.
- Avoid persistent database connections in configurations with high request concurrency, unless the underlying pooling behavior is well understood, deliberately configured, and actively monitored.
- Set `wait_timeout` and `interactive_timeout` to values appropriate for actual application behavior, rather than an excessively high default, so idle abandoned connections are reclaimed by the server in a reasonable time.
- Plan database capacity in advance for known traffic spikes (for example, scheduled marketing campaigns or high-traffic events) rather than reacting after exhaustion occurs.
- Maintain a documented escalation path with the hosting provider or infrastructure team for connection-capacity increases that are outside the engineer's own control.

---

# 15. Security Considerations

- Do not increase connection limits or grant broader administrative connection privileges as an undisciplined shortcut without first understanding why capacity was exhausted, since doing so can mask abuse as ordinary demand.
- A rapid, unexplained connection-limit exhaustion may indicate a deliberate or accidental denial-of-service condition rather than routine capacity growth; distinguish between the two before concluding the cause is ordinary traffic growth.
- Avoid exposing `PROCESSLIST`-derived diagnostic detail (query text, connecting hosts) in logs or dashboards accessible to unauthorized users, since `PROCESSLIST` output can reveal sensitive query content.
- Reserve administrative connection capacity appropriately so that a connection-exhaustion event does not also prevent the database administrator from connecting to diagnose or resolve it.

---

# 16. Related Errors

The following are cited as they exist in this repository, or as conceptual distinctions where noted.

1. [WP-ERROR-002 — WordPress Database Authentication Failure](WP-ERROR-002-WORDPRESS-DATABASE-AUTHENTICATION-FAILURE.md) — exists in this repository; see Section 6 (Distinction) above.
2. [WP-ERROR-003 — Database Does Not Exist](WP-ERROR-003-DATABASE-DOES-NOT-EXIST.md) — exists in this repository; see Section 6 (Distinction) above.
3. WP-ERROR-004 — Database Permission Denied (conceptual reference; no corresponding document currently exists in this repository; no link is provided).
4. [WP-ERROR-008 — WordPress Database Server Unreachable](WP-ERROR-008-WORDPRESS-DATABASE-SERVER-UNREACHABLE.md) — exists in this repository; see Section 6 (Distinction) above.
5. WP-ERROR-009 — Database Query Timeout (conceptual reference; no corresponding document currently exists in this repository; no link is provided).
6. [WP-ERROR-018 — WordPress Database Connection Failure](WP-ERROR-018-WORDPRESS-DATABASE-CONNECTION-FAILURE.md) — exists in this repository; see Section 6 (Distinction) above.

---

# 17. Notes

This entry documents the specific, verified condition of database connection-capacity exhaustion, as one of several specific causes deferred by the general connection-failure entry, WP-ERROR-018. It does not restate WP-ERROR-018's own boundary, its relationship to WP-ERROR-013, WP-ERROR-014, or WP-ERROR-016, or the general diagnostic process for narrowing an unspecified connection failure to a specific cause; see WP-ERROR-018 for that content. Consistent with the single-responsibility principle in **SF-SPEC-001** Section 4.3, cause-specific conditions for individual connection-pooling products, individual managed-database platforms' proxy layers, or individual plugins' specific connection-leak defects may each be documented by a separate, independently created `WP-ERROR` entry without altering this one.

This entry's governing work order described required content using category labels ("Operational Considerations," "References") that do not correspond to section names in **SF-TEMPLATE-004**. Per that work order's own instruction to use "the existing WP-ERROR template," and consistent with **SF-SPEC-012**'s Framework Governance requirement not to modify any template, this entry uses SF-TEMPLATE-004's exact 17 sections; the concurrency-relationship and concrete-terminology content the work order described under those two labels is incorporated into WordPress Components, Common Causes, Diagnosis, and Prevention above rather than under new section headers.

This entry underwent the review sequence required by **SF-SPEC-001** Section 19, **SF-SPEC-005** Section 5.6, and **SF-SPEC-012**: an author (Class A) review at `docs/reviews/SF-REVIEW-020-WP-ERROR-007-AUTHOR-REVIEW.md`, followed by an independent (Class B) review at `docs/reviews/SF-REVIEW-021-WP-ERROR-007-INDEPENDENT-REVIEW.md`, which independently re-verified the non-existence of WP-ERROR-003, 004, and 009, reached outcome **Approved with Minor Revisions**, applied and re-validated the one required revision, and satisfied the Production Ready gate per SF-SPEC-012 Section 12. Its Status was changed to Production Ready on that basis. This document does not itself constitute either review record; see the cited files for full findings, corrections, and gate decisions.

The independent review did not designate this entry as a Reference Implementation. That designation, governed separately by **SF-SPEC-001** Section 22, has not been sought or asserted here.

No Reference Implementation is currently designated by **SF-SPEC-001**; this entry's relationship to that designation, and to any future `WP-SCENARIO-XXX` runtime evidence, is not asserted here and shall not be assumed until such evidence or designation actually exists.
