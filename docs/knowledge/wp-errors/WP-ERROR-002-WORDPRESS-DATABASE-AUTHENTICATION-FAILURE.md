# WP-ERROR-002 — WordPress Database Authentication Failure

---

# 1. Knowledge Entry

WordPress Database Authentication Failure

---

# 2. Metadata

* **Error ID:** `WP-ERROR-002`
* **Title:** WordPress Database Authentication Failure
* **Category:** Database
* **Severity:** Critical
* **Recovery Priority:** Immediate
* **Status:** Production Ready
* **Version:** 1.0

---

# 3. Summary

WordPress reaches the configured database server, but the server rejects the supplied database credentials during authentication, so no usable authenticated connection is established. This is one specific, verified cause within the general connection-failure condition documented by WP-ERROR-018.

---

# 4. Primary Failure Mode

WordPress's database layer (`wpdb`) reaches the configured database server over the network, but the server's authentication step rejects the supplied `DB_USER` and `DB_PASSWORD` combination, so an authenticated connection is never established. This differs from a failure to reach the server at all: the network path to the server succeeds, and the server actively responds with an authentication rejection rather than being unreachable, refusing the connection outright due to resource limits, or accepting the credentials and failing at a later step.

---

# 5. Severity

This entry is classified **Critical** because, by definition, it covers a verified authentication rejection that leaves WordPress without any usable database connection:

- No later step (selecting a database, checking permissions, querying) can be reached without a successfully authenticated connection first.
- The condition affects front-end, administrative, AJAX, cron, REST, and WP-CLI paths identically, since all depend on the same authentication attempt.
- Remediation cannot be deferred, since the site provides no functioning request path while the condition persists.

---

# 6. Distinction

This entry applies only when verified evidence establishes that the database server was reached and actively rejected the supplied credentials — not that the server was unreachable, refused the connection for another reason, or accepted the credentials and failed at a later step.

It is distinct from:

- **WP-ERROR-003 — Database Does Not Exist**: presumes successful authentication, with the failure occurring afterward when selecting a specific, named database that is not present. This entry's boundary ends once authentication itself has succeeded.
- **WP-ERROR-004 — Database Permission Denied**: presumes successful authentication with valid, accepted credentials, with the failure occurring because the authenticated user lacks privileges for a specific operation. This is a distinct condition from the credentials themselves being rejected.
- **WP-ERROR-007 — Database Connection Limit Exceeded**: the server refuses the connection outright due to a resource limit, before authentication is necessarily even attempted. This is a distinct cause from an authentication rejection.
- **WP-ERROR-008 — Database Server Unreachable**: the network path to the server itself fails, so the server is never reached and no authentication attempt occurs at all. This is an earlier-stage condition than this entry's boundary.
- **WP-ERROR-009 — Database Query Timeout** (conceptual reference; no corresponding document currently exists in this repository): presumes successful authentication and an established connection, with the failure occurring later when a specific query does not complete in time.
- **WP-ERROR-018 — WordPress Database Connection Failure**: WP-ERROR-018 owns the general, verified-but-unspecified-cause condition where WordPress cannot establish a database connection, and explicitly identifies authentication rejection as one specific, verified cause that belongs to this entry once confirmed. This entry owns that specific, verified cause; WP-ERROR-018 owns the general condition and the diagnostic process for narrowing it to a specific cause, along with this entry's relationship to WP-ERROR-013, WP-ERROR-014, and WP-ERROR-016, which this entry does not restate.
- **WordPress user-account authentication (unrelated)**: `DB_USER` and `DB_PASSWORD` in `wp-config.php` are database server credentials, entirely unrelated to WordPress user accounts such as an administrator's `wp-admin` login. A WordPress user password reset, lockout, or login failure has no bearing on this entry's condition, and this entry has no bearing on WordPress user accounts.

---

# 7. Scope

**Covered:** A verified condition in which the database server was reached and actively rejected the `DB_USER` / `DB_PASSWORD` credentials WordPress supplied, before any database selection, permission check, or query was reached.

**Excluded:**

- Network-level unreachability of the database server (see WP-ERROR-008).
- Connection refusal due to the server's connection limit (see WP-ERROR-007).
- Selection of a nonexistent database after successful authentication (see WP-ERROR-003).
- Insufficient privileges after successful, accepted authentication (see WP-ERROR-004).
- Query timeouts after a successful, established, authenticated connection (see WP-ERROR-009).
- WordPress user-account authentication (login, password resets, session cookies), which is unrelated to database credentials.

---

# 8. WordPress Components

Listed as components commonly involved, not as a claim that every installation exercises every one of them identically:

- The `wpdb` class (`wp-includes/class-wpdb.php`), specifically its connection-establishment logic, shared with WP-ERROR-018.
- The `DB_USER`, `DB_PASSWORD`, and `DB_HOST` constants defined in `wp-config.php`, whose values are presented to the database server for authentication.
- The database server's own user-and-host authentication and privilege system (for example, MySQL's or MariaDB's `'user'@'host'` grant model, where a grant is scoped to both a username and the specific host or host pattern the connection originates from).
- The database server's configured default authentication plugin or method (for example, MySQL 8's `caching_sha2_password`), which the connecting PHP database client and driver need to support.
- WP-CLI's own database-connectivity commands, which depend on the same underlying authentication attempt as web requests.

---

# 9. Typical Symptoms

- WordPress's own generic "Error establishing a database connection" message, indistinguishable at the WordPress level from WP-ERROR-018's other specific causes; the specific authentication-rejection detail is visible only in server-side or PHP-level logs, not in WordPress's own user-facing message.
- A database-level error such as `Access denied for user '<user>'@'<host>' (using password: YES)` (MySQL/MariaDB error 1045), or the `(using password: NO)` variant where `DB_PASSWORD` is empty, visible in logs where accessible.
- The failure occurring identically across front-end, administrative, AJAX, cron, REST, and WP-CLI paths, since all depend on the same authentication attempt.
- A site that previously connected successfully beginning to fail immediately after a database password rotation, a user-grant change, or a database server migration or upgrade.
- The same credentials failing when tested directly against the database server with a separate client, confirming the rejection is not specific to WordPress's own connection attempt.

---

# 10. Common Causes

Causes are grouped by category. Inclusion in this list identifies a category as plausible; it does not assert that any specific cause is present without diagnostic confirmation.

- `DB_USER` or `DB_PASSWORD` in `wp-config.php` containing an incorrect, mistyped, or outdated value, including case-sensitivity mismatches, since both are case-sensitive.
- A database password rotated on the server side (for example, as part of routine credential rotation) without `wp-config.php` being updated to match.
- A grant that exists for the correct username and password, but is scoped to a different host than the one WordPress's connection actually originates from — a common condition in split web/database-server hosting, load-balanced environments, or containerized deployments where the connecting host or IP differs from what was originally granted.
- `DB_PASSWORD` left empty or unset in `wp-config.php` when the database user actually requires a password.
- A database server upgrade (for example, to a MySQL version defaulting to `caching_sha2_password`) that the connecting PHP database client, driver, or PHP version does not fully support, producing an authentication failure that resembles a credential problem but is actually a client/server authentication-method incompatibility.
- Special characters in `DB_PASSWORD` mishandled during copy-paste, environment-variable substitution, or entry through a hosting control panel's credential field.
- The database user account itself having been renamed, removed, or had its authentication method changed independently of `wp-config.php`.

---

# 11. Diagnosis

Verify the following:

1. Confirm this is genuinely an authentication rejection — the database server was reached and responded with a credential-related refusal — rather than network unreachability, a connection-limit refusal, a later-stage condition after successful authentication, or an unrelated WordPress user-account issue.
2. Capture the exact underlying database error text where accessible (for example, an `Access denied for user '<user>'@'<host>'` message), since WordPress's own generic message does not distinguish this cause from others WP-ERROR-018 documents. This error text alone does not indicate whether the username itself is wrong or the password is wrong for an existing username: MySQL and MariaDB deliberately return the same "Access denied" message in both cases, to avoid revealing which specific username exists. Determining which requires independent verification (for example, an administrator confirming the account exists) rather than inference from the error text.
3. Confirm the exact `DB_USER` and `DB_HOST` values WordPress is using, without exposing `DB_PASSWORD` in any log, output, or diagnostic artifact.
4. Test the same credentials directly against the database server using a separate client from the same host WordPress runs on, to confirm whether the rejection is specific to WordPress's own connection attempt or reproducible independently of it.
5. Confirm that a grant exists for the exact host the connection is originating from, not only for the correct username and password, since database grants are commonly scoped by host as well as by user.
6. Determine whether this is a recent regression (previously working, now failing) or a new configuration; a recent regression points toward credential rotation, a grant change, or a server migration, while a new configuration points toward a simple setup error.
7. Where a database server upgrade or migration recently occurred, confirm whether the server's default authentication plugin or method changed, and whether the connecting PHP database client and driver support it.
8. Confirm `DB_PASSWORD` does not contain characters that may have been altered by copy-paste, environment-variable handling, or a hosting control panel's credential-entry field.
9. Preserve relevant evidence — error messages and timestamps — before making any change.
10. Where the engineer performing diagnosis does not control the database server or its user grants, escalate to the database administrator or hosting provider rather than attempting an unverified workaround.

---

# 12. Recovery Procedure

Recovery shall target the verified cause of the authentication rejection, not merely the visible symptom.

Permitted recovery categories, depending on the verified cause, include:

- Correcting `DB_USER` or `DB_PASSWORD` in `wp-config.php` where diagnosis confirms one of these values is incorrect or outdated.
- Resynchronizing a rotated database password between the database server and `wp-config.php`, where diagnosis confirms a rotation caused the mismatch.
- Correcting or adding the appropriate host-scoped grant for the actual connecting host, where diagnosis confirms a host mismatch, in coordination with the database administrator where the engineer performing recovery does not control the database server.
- Updating the connecting PHP database client, driver, or PHP version to support the server's authentication method, or adjusting the specific database user's authentication method in coordination with the database administrator, where diagnosis confirms a client/server authentication-method incompatibility.
- Escalating to the database administrator, hosting provider, or platform team where the engineer performing diagnosis does not control the database server or its user accounts.

Recovery shall not grant overly broad host-based access (for example, a wildcard host grant) merely to resolve a host-mismatch rejection when a narrower, correct grant is available. Recovery shall not weaken or remove authentication requirements as a shortcut to making a connection succeed.

---

# 13. Validation

Recovery is successful when:

- WordPress establishes an authenticated database connection successfully across front-end, administrative, AJAX, cron, REST, and WP-CLI paths, each independently confirmed.
- The same corrected credentials and grant continue to succeed consistently, not merely on a single attempt.
- No equivalent authentication-rejection error recurs in logs across repeated, fresh requests.
- The specific underlying cause identified during diagnosis (credential value, host-grant mismatch, or authentication-method incompatibility) has been verifiably addressed, not merely that an attempt happened to succeed once.
- No unrelated credential, grant, or configuration change was introduced as a side effect of recovery.

---

# 14. Prevention

- Include updating `wp-config.php` as an explicit step in any database credential-rotation procedure, rather than treating the two as independent tasks.
- Keep database grants' host specifications documented and synchronized with actual infrastructure, particularly where web and database tiers run on separate or changing hosts.
- Test database connectivity after any database server upgrade or migration, particularly where a major version change may alter default authentication behavior.
- Use a password manager or secrets-management process when setting `DB_PASSWORD`, to reduce copy-paste and transcription errors.
- Monitor for repeated authentication failures, which may indicate a configuration drift or, in some cases, an unauthorized access attempt rather than routine misconfiguration.

---

# 15. Security Considerations

- Do not expose `DB_PASSWORD` or other database credentials in logs, error messages, or diagnostic output.
- Do not grant overly broad host-based access (for example, a wildcard host) as a shortcut to resolving a host-mismatch authentication failure; grant the narrowest host scope that is actually correct.
- Coordinate credential rotation through a platform-appropriate, secure process rather than transmitting credentials over an insecure channel.
- Repeated or unexpected authentication failures may indicate a credential-stuffing or brute-force attempt against the database rather than routine misconfiguration; distinguish between the two before concluding the cause is simple misconfiguration.

---

# 16. Related Errors

The following are cited as they exist in this repository, or as conceptual distinctions where noted.

1. [WP-ERROR-003 — Database Does Not Exist](WP-ERROR-003-DATABASE-DOES-NOT-EXIST.md) — exists in this repository; see Section 6 (Distinction) above.
2. [WP-ERROR-004 — Database Permission Denied](WP-ERROR-004-DATABASE-PERMISSION-DENIED.md) — exists in this repository; see Section 6 (Distinction) above.
3. [WP-ERROR-007 — Database Connection Limit Exceeded](WP-ERROR-007-WORDPRESS-DATABASE-CONNECTION-LIMIT-EXCEEDED.md) — exists in this repository; see Section 6 (Distinction) above.
4. [WP-ERROR-008 — Database Server Unreachable](WP-ERROR-008-WORDPRESS-DATABASE-SERVER-UNREACHABLE.md) — exists in this repository; see Section 6 (Distinction) above.
5. WP-ERROR-009 — Database Query Timeout (conceptual reference; no corresponding document currently exists in this repository; no link is provided).
6. [WP-ERROR-018 — WordPress Database Connection Failure](WP-ERROR-018-WORDPRESS-DATABASE-CONNECTION-FAILURE.md) — exists in this repository; see Section 6 (Distinction) above.

---

# 17. Notes

This entry documents the specific, verified condition of database authentication being rejected, as one of several specific causes deferred by the general connection-failure entry, WP-ERROR-018. It does not restate WP-ERROR-018's own boundary, its relationship to WP-ERROR-013, WP-ERROR-014, or WP-ERROR-016, or the general diagnostic process for narrowing an unspecified connection failure to a specific cause; see WP-ERROR-018 for that content. Consistent with the single-responsibility principle in **SF-SPEC-001** Section 4.3, cause-specific conditions for individual authentication-plugin migrations or individual hosting platforms' credential-management systems may each be documented by a separate, independently created `WP-ERROR` entry without altering this one.

This entry underwent the review sequence required by **SF-SPEC-001** Section 19, **SF-SPEC-005** Section 5.6, and **SF-SPEC-012**: an author (Class A) review at `docs/reviews/SF-REVIEW-016-WP-ERROR-002-AUTHOR-REVIEW.md`, followed by an independent (Class B) review at `docs/reviews/SF-REVIEW-017-WP-ERROR-002-INDEPENDENT-REVIEW.md`, which independently re-verified the non-existence of WP-ERROR-003, 004, 007, 008, and 009, reached outcome **Approved with Minor Revisions**, applied and re-validated the one required revision, and satisfied the Production Ready gate per SF-SPEC-012 Section 12. Its Status was changed to Production Ready on that basis. This document does not itself constitute either review record; see the cited files for full findings, corrections, and gate decisions.

The independent review did not designate this entry as a Reference Implementation. That designation, governed separately by **SF-SPEC-001** Section 22, has not been sought or asserted here.

No Reference Implementation is currently designated by **SF-SPEC-001**; this entry's relationship to that designation, and to any future `WP-SCENARIO-XXX` runtime evidence, is not asserted here and shall not be assumed until such evidence or designation actually exists.
