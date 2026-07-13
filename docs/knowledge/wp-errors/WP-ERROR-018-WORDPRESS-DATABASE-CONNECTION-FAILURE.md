# WP-ERROR-018 — WordPress Database Connection Failure

---

# 1. Knowledge Entry

WordPress Database Connection Failure

---

# 2. Metadata

* **Error ID:** `WP-ERROR-018`
* **Title:** WordPress Database Connection Failure
* **Category:** Database
* **Severity:** Critical
* **Recovery Priority:** Immediate
* **Status:** Production Ready
* **Version:** 1.0

---

# 3. Summary

WordPress attempts to establish a connection to the database server configured in `wp-config.php`, but the connection attempt itself does not succeed — before any authentication outcome, database selection, or query is reached. WordPress handles this specific condition through its own dedicated database-error path rather than an uncaught PHP fatal error.

---

# 4. Primary Failure Mode

WordPress's database layer (`wpdb`) attempts to open a connection to the configured database host and cannot obtain a usable connection handle. The attempt itself — not anything that depends on a connection already existing — is what fails. WordPress anticipates this condition: `wpdb` catches the failed connection attempt and calls its own dedicated database-error handling (`dead_db()`, which invokes `wp_die()` with a specific "Error establishing a database connection" message, optionally overridden by a `wp-content/db-error.php` drop-in), rather than allowing an uncaught PHP fatal error to terminate the request.

---

# 5. Severity

This entry is classified **Critical** because, by definition, it covers a condition that prevents WordPress from operating in any capacity:

- Nearly all WordPress functionality depends on the database; without a connection, no request path can complete meaningfully.
- The condition affects front-end, administrative, AJAX, cron, REST, and WP-CLI paths identically, since all of them depend on the same underlying connection attempt.
- Remediation cannot be deferred, since the site provides no functioning request path while the condition persists.

---

# 6. Distinction

This entry applies only when verified evidence establishes that WordPress's attempt to establish the database connection itself did not succeed — not that a connection succeeded and a later step (selecting the database, checking permissions, locating schema or tables, or executing a query) then failed.

It is distinct from:

- **WP-ERROR-002 — WordPress Database Authentication Failure**: covers the specific, verified case where the database server was reached but rejected the supplied credentials. This entry's boundary ends once a specific cause such as credential rejection has been confirmed; that specific, verified cause belongs to WP-ERROR-002, not this entry.
- **WP-ERROR-003 — Database Does Not Exist** (conceptual reference; no corresponding document currently exists in this repository): presumes the connection to the database server already succeeded, and the failure occurs when selecting a specific, named database that is not present. This is a later step than this entry's boundary.
- **WP-ERROR-004 — Database Permission Denied** (conceptual reference; no corresponding document currently exists in this repository): presumes a successful connection and valid credentials, with the failure occurring because the authenticated user lacks privileges for a specific operation. This is a later step than this entry's boundary.
- **WP-ERROR-005 — Database Schema Missing or Incomplete** (conceptual reference; no corresponding document currently exists in this repository): presumes a successful connection to the correct database, with the failure occurring because expected WordPress tables or structures are absent. This is a later step than this entry's boundary.
- **WP-ERROR-006 — Database Table Corruption** (conceptual reference; no corresponding document currently exists in this repository): presumes a successful connection to intact, present tables, with the failure occurring because a table's own data or structure is damaged. This is a later step than this entry's boundary.
- **WP-ERROR-007 — Database Connection Limit Exceeded** (conceptual reference; no corresponding document currently exists in this repository): covers the specific, verified case where the connection attempt is refused because the database server has reached its maximum permitted connections. This is one specific, verified cause of a connection failure; once confirmed, it belongs to WP-ERROR-007, not this entry's general boundary.
- **WP-ERROR-008 — Database Server Unreachable** (conceptual reference; no corresponding document currently exists in this repository): covers the specific, verified case where the failure is at the network level (the host cannot be reached at all — DNS failure, firewall block, incorrect host or port, or the database process not running). This is one specific, verified cause of a connection failure; once confirmed, it belongs to WP-ERROR-008, not this entry's general boundary.
- **WP-ERROR-009 — Database Query Timeout** (conceptual reference; no corresponding document currently exists in this repository): presumes a successful, established connection, with the failure occurring because a specific query does not complete in time. This is a later step than this entry's boundary and does not concern the connection attempt itself.
- **WP-ERROR-013 — WordPress Bootstrap PHP Fatal Error**: a database connection failure is handled by WordPress's own dedicated `dead_db()` / `wp_die()` path, not by an uncaught PHP fatal error terminating bootstrap. This entry's condition is deliberately anticipated and handled by WordPress itself; it is not, by default, an instance of the general uncaught-fatal-error condition WP-ERROR-013 documents. Where the required database extension itself is unavailable (so that `wpdb` cannot even attempt to call a connection function), that is WP-ERROR-014's territory, and the resulting failure may then present as an uncaught fatal error rather than the handled path this entry documents. Where a site has installed a custom `wp-content/db-error.php` drop-in and that drop-in file is itself defective, the resulting failure is an uncaught fatal error in that drop-in's own code, not the graceful, anticipated handling this entry otherwise documents; evidence shall establish which condition is actually present.
- **WP-ERROR-016 — WordPress Core Files Missing or Corrupted**: a connection failure caused by corrupted or missing `wpdb`-related core files is a distinct, verified condition from a connection failure that occurs because the database server itself, its credentials, or the network path to it is unavailable. Evidence shall establish which is actually present before concluding this entry applies.

---

# 7. Scope

**Covered:** A verified condition in which WordPress's attempt to establish a connection to the configured database server does not succeed, prior to authentication outcome, database selection, or query execution, without the specific underlying cause necessarily having been isolated to a narrower, cause-specific condition.

**Excluded:**

- Authentication rejection after a successful connection to the server (see WP-ERROR-002).
- Selection of a nonexistent database after a successful connection (see WP-ERROR-003).
- Insufficient privileges after a successful, authenticated connection (see WP-ERROR-004).
- Missing or incomplete WordPress schema after a successful connection to the correct database (see WP-ERROR-005).
- Corrupted tables after a successful connection to present, intact tables (see WP-ERROR-006).
- Connection refusal specifically due to the server's connection limit, once confirmed as the cause (see WP-ERROR-007).
- Network-level unreachability, once confirmed as the cause (see WP-ERROR-008).
- Query timeouts occurring after a successful, established connection (see WP-ERROR-009).
- General PHP fatal errors unrelated to the database connection attempt (see WP-ERROR-013).
- Corrupted WordPress core files, independent of database connectivity (see WP-ERROR-016).
- A missing PHP database extension that prevents the connection attempt from being made at all (see WP-ERROR-014).

---

# 8. WordPress Components

Listed as components commonly involved, not as a claim that every installation exercises every one of them identically:

- The `wpdb` class (`wp-includes/class-wpdb.php`), specifically its connection-establishment logic.
- WordPress's dedicated database-error handling (`dead_db()` and `wp_die()` in `wp-includes/functions.php`), which presents the connection failure rather than allowing an uncaught fatal error.
- The optional `wp-content/db-error.php` drop-in, which a site may use to customize the connection-failure page.
- The `DB_HOST`, `DB_USER`, `DB_PASSWORD`, and `DB_NAME` constants defined in `wp-config.php`, whose values determine what WordPress attempts to connect to.
- WP-CLI's own database-connectivity commands (for example, `wp db check`), which depend on the same underlying connection attempt as web requests.

---

# 9. Typical Symptoms

- WordPress's own "Error establishing a database connection" message, or the equivalent output of a custom `wp-content/db-error.php` drop-in.
- The failure occurring identically across front-end, administrative, AJAX, cron, REST, and WP-CLI paths, since all depend on the same connection attempt.
- WP-CLI commands that require database access failing with a connection-related message.
- A site that previously connected successfully beginning to fail immediately after a database server restart, network change, credential rotation, or hosting migration.
- Intermittent rather than constant failure, which may indicate a cause such as connection-limit exhaustion (see WP-ERROR-007) rather than a constant, verified network or credential problem.

---

# 10. Common Causes

Causes are grouped by category. Inclusion in this list identifies a category as plausible; it does not assert that any specific cause is present without diagnostic confirmation, and several of these categories correspond to the more specific, verified conditions documented separately once confirmed.

- Network-level unreachability of the database host (see WP-ERROR-008 once confirmed).
- Rejected authentication credentials (see WP-ERROR-002 once confirmed).
- The database server refusing new connections due to its connection limit (for example, MySQL's or MariaDB's `max_connections` setting being reached; see WP-ERROR-007 once confirmed).
- The database server process itself not running, having crashed, or being restarted.
- An incorrect `DB_HOST` value in `wp-config.php` (wrong hostname, socket path, or port) — the value itself is a `wp-config.php` concern, but the resulting connection failure is this entry's concern.
- A required TLS/SSL handshake for the database connection failing due to certificate or configuration mismatch, where encrypted transport is required.
- The database server being in a maintenance or crash-recovery state where the server process itself is not yet ready to accept connections. An ordinary read-only state (for example, a read replica, or a primary temporarily set read-only) is distinct from this: it typically continues to accept connections normally and only rejects write statements afterward, which is a later-stage condition outside this entry's boundary, not a connection-establishment failure.
- Hosting-platform-level connectivity issues, such as a managed database service outage, a network partition between application and database tiers, or a security-group or firewall misconfiguration in a cloud environment.

---

# 11. Diagnosis

Verify the following:

1. Confirm this is genuinely a connection-establishment failure — WordPress's own database-error presentation, or an equivalent low-level connection error — rather than a later-stage database condition (authentication, database selection, permissions, schema, table integrity, or query timeout) that presumes a connection already succeeded.
2. Capture the exact error text available, including any underlying database-driver error code or message visible through logs or `WP_DEBUG`, rather than relying only on WordPress's generic friendly message.
3. Confirm the exact `DB_HOST`, `DB_USER`, and `DB_NAME` values WordPress is using for the connection attempt, without exposing `DB_PASSWORD` in any log, output, or diagnostic artifact.
4. Test connectivity to the database host and port independently of WordPress (for example, using a database client directly from the same server WordPress runs on), to isolate whether the failure is network-level, credential-specific, or specific to WordPress's own connection attempt.
5. Determine whether the failure is constant or intermittent; intermittent failure may indicate connection-limit exhaustion under load rather than a constant network or credential problem.
6. Confirm whether the database server process itself is running and listening on the expected host and port.
7. Where web, administrative, cron, and WP-CLI paths behave differently, determine whether they reach the database server from different network contexts (for example, different outbound IP addresses or firewall rules), since a firewall may permit one context and block another.
8. Preserve relevant evidence — error messages, timestamps, and server-side logs where accessible — before making any change.
9. Where a custom `wp-content/db-error.php` drop-in is present, confirm it is not itself obscuring diagnostic information that would otherwise be available.
10. Based on the evidence gathered, determine which specific, verified cause applies — network unreachability, credential rejection, connection-limit exhaustion, or another cause — and proceed under the corresponding cause-specific entry rather than continuing to treat the condition as general once it has been identified.
11. Where the engineer performing diagnosis does not control the database server or hosting network, escalate to the database administrator or hosting provider rather than attempting an unverified workaround.

---

# 12. Recovery Procedure

Recovery shall target the verified cause of the connection failure, not merely the visible symptom.

Permitted recovery categories, depending on the verified cause, include:

- Correcting `DB_HOST`, `DB_USER`, `DB_NAME`, or `DB_PASSWORD` in `wp-config.php` where diagnosis confirms one of these values is incorrect for the intended database server.
- Restoring the database server process to a running, reachable state, or correcting the network path to it (DNS, firewall, security group), where the engineer performing recovery controls that infrastructure.
- Escalating to the database administrator, hosting provider, or platform team where the engineer performing diagnosis does not control the database server or its network path.
- Addressing connection-limit exhaustion at its source (for example, closing leaked connections or adjusting server-side limits) once confirmed as the cause, per WP-ERROR-007.
- Correcting rejected credentials once confirmed as the cause, per WP-ERROR-002.

Recovery shall not suppress or hide the connection failure — for example, through a `db-error.php` customization that removes diagnostic information needed by administrators — without correcting the underlying cause. Recovery shall not weaken database authentication or access controls (for example, granting overly broad host-based access, or disabling password requirements) merely to make a connection succeed.

---

# 13. Validation

Recovery is successful when:

- WordPress establishes a database connection successfully across front-end, administrative, AJAX, cron, REST, and WP-CLI paths, each independently confirmed.
- The connection remains stable under normal request load over time, not merely on a single successful attempt, since some causes (such as connection-limit exhaustion) may only manifest under concurrent load.
- No equivalent connection failure recurs in logs across repeated, fresh requests.
- The specific underlying cause identified during diagnosis has been verifiably addressed, not merely that a connection attempt happened to succeed once.
- No unrelated configuration, credential, or network change was introduced as a side effect of recovery.

---

# 14. Prevention

- Monitor database connectivity and connection-pool health proactively, rather than relying solely on user reports of a broken site.
- Keep `DB_HOST` and credential values documented and synchronized correctly across development, staging, and production environments.
- Coordinate planned database server maintenance or restarts with awareness of dependent WordPress sites.
- Monitor for connection exhaustion under load, and size connection limits appropriately for expected traffic.
- Test database connectivity after any network, hosting, or database-infrastructure change, rather than assuming prior configuration still applies.

---

# 15. Security Considerations

- Do not expose `DB_PASSWORD` or other database credentials in logs, error messages, or a customized `db-error.php` page.
- Do not weaken database authentication or access controls as a shortcut to resolving a connection failure; address the verified root cause instead.
- Where a custom `db-error.php` page is used, avoid revealing internal hostnames, IP addresses, or other infrastructure details to unauthenticated visitors.
- Coordinate credential rotation through a platform-appropriate process where credential compromise, rather than simple misconfiguration, is suspected as the cause of authentication rejection.

---

# 16. Related Errors

The following are cited as conceptual distinctions only unless a repository link is noted.

1. [WP-ERROR-002 — WordPress Database Authentication Failure](WP-ERROR-002-WORDPRESS-DATABASE-AUTHENTICATION-FAILURE.md) — exists in this repository; see Section 6 (Distinction) above.
2. WP-ERROR-003 — Database Does Not Exist (conceptual reference; no corresponding document currently exists in this repository; no link is provided).
3. WP-ERROR-004 — Database Permission Denied (conceptual reference; no corresponding document currently exists in this repository; no link is provided).
4. WP-ERROR-005 — Database Schema Missing or Incomplete (conceptual reference; no corresponding document currently exists in this repository; no link is provided).
5. WP-ERROR-006 — Database Table Corruption (conceptual reference; no corresponding document currently exists in this repository; no link is provided).
6. WP-ERROR-007 — Database Connection Limit Exceeded (conceptual reference; no corresponding document currently exists in this repository; no link is provided).
7. WP-ERROR-008 — Database Server Unreachable (conceptual reference; no corresponding document currently exists in this repository; no link is provided).
8. WP-ERROR-009 — Database Query Timeout (conceptual reference; no corresponding document currently exists in this repository; no link is provided).
9. [WP-ERROR-013 — WordPress Bootstrap PHP Fatal Error](WP-ERROR-013-WORDPRESS-BOOTSTRAP-PHP-FATAL-ERROR.md) — exists in this repository; see Section 6 (Distinction) above.
10. [WP-ERROR-014 — Required PHP Extension Missing](WP-ERROR-014-REQUIRED-PHP-EXTENSION-MISSING.md) — exists in this repository; see Section 6 (Distinction) above.
11. [WP-ERROR-016 — WordPress Core Files Missing or Corrupted](WP-ERROR-016-WORDPRESS-CORE-FILES-MISSING-OR-CORRUPTED.md) — exists in this repository; see Section 6 (Distinction) above.

---

# 17. Notes

This entry documents the general, verified observable condition of a database connection attempt failing, and the specific, dedicated way WordPress handles that condition (`dead_db()` / `wp_die()`, optionally customized by `wp-content/db-error.php`) rather than as an uncaught PHP fatal error. It does not claim to own the specific, verified causes of a connection failure once they have been isolated; those are reserved for separate, cause-specific entries named in Section 6. WP-ERROR-002 now exists in this repository; WP-ERROR-007, WP-ERROR-008, and the remaining causes named in Section 6 do not yet exist. Consistent with the single-responsibility principle in **SF-SPEC-001** Section 4.3, this entry owns the general condition and the diagnostic process for narrowing it to a specific cause, not the full remediation detail for every possible specific cause.

This entry underwent the review sequence required by **SF-SPEC-001** Section 19, **SF-SPEC-005** Section 5.6, and **SF-SPEC-012**: an author (Class A) review at `docs/reviews/SF-REVIEW-014-WP-ERROR-018-AUTHOR-REVIEW.md`, followed by an independent (Class B) review at `docs/reviews/SF-REVIEW-015-WP-ERROR-018-INDEPENDENT-REVIEW.md`, which independently re-verified the non-existence of WP-ERROR-002 through 009, reached outcome **Approved with Minor Revisions**, applied and re-validated the one required revision, and satisfied the Production Ready gate per SF-SPEC-012 Section 12. Its Status was changed to Production Ready on that basis. This document does not itself constitute either review record; see the cited files for full findings, corrections, and gate decisions.

The independent review did not designate this entry as a Reference Implementation. That designation, governed separately by **SF-SPEC-001** Section 22, has not been sought or asserted here.

No Reference Implementation is currently designated by **SF-SPEC-001**; this entry's relationship to that designation, and to any future `WP-SCENARIO-XXX` runtime evidence, is not asserted here and shall not be assumed until such evidence or designation actually exists.
