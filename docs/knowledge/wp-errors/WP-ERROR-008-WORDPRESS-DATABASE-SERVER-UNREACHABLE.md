# WP-ERROR-008 — WordPress Database Server Unreachable

---

# 1. Knowledge Entry

WordPress Database Server Unreachable

---

# 2. Metadata

* **Error ID:** `WP-ERROR-008`
* **Title:** WordPress Database Server Unreachable
* **Category:** Database
* **Severity:** Critical
* **Recovery Priority:** Immediate
* **Status:** Production Ready
* **Version:** 1.0

---

# 3. Summary

WordPress's attempt to reach the configured database server fails at the network level, before any authentication attempt, connection-limit response, or other server-level reply can occur. The database server is never actually contacted — no TCP handshake completes and no protocol-level exchange with the database server takes place. This is one specific, verified cause within the general connection-failure condition documented by WP-ERROR-018.

---

# 4. Primary Failure Mode

WordPress's database layer (`wpdb`) attempts to open a network connection to the host named in `DB_HOST`, but the underlying network path itself fails before the database server has any opportunity to respond: the hostname cannot be resolved to an address, no host is listening at the resolved address and port, packets to the host are dropped or blocked in transit, or no valid network route to the host exists at all. This differs from every other cause WP-ERROR-018 defers to a specific entry: the database server itself is never reached, so it never has the chance to accept the connection, refuse it due to a resource limit, or evaluate credentials. The distinguishing fact is where the failure occurs — at the network layer, prior to any exchange with the database server process — not merely that a connection could not be used.

---

# 5. Severity

This entry is classified **Critical** because, by definition, it covers a verified network-level failure that leaves WordPress unable to reach the database server at all:

- No later step (authentication, database selection, permission checks, querying) can be reached without a network path to the database server first existing.
- The condition affects front-end, administrative, AJAX, cron, REST, and WP-CLI paths identically, since all depend on the same underlying network attempt.
- Remediation cannot be deferred, since the site provides no functioning request path while the condition persists.

---

# 6. Distinction

This entry applies only when verified evidence establishes that the network path to the configured database host itself failed — not that the host was reached and then refused the connection, rejected credentials, or failed at a later step.

It is distinct from:

- **WP-ERROR-002 — WordPress Database Authentication Failure**: presumes the database server was actually reached over the network, and the failure occurs afterward when the server actively rejects the supplied credentials. This entry's condition ends before that point is ever reached — the server has no opportunity to evaluate credentials it never received a connection attempt for.
- **WP-ERROR-003 — Database Does Not Exist**: presumes successful network connectivity and successful authentication, with the failure occurring afterward when selecting a specific, named database that is not present. This is a much later step than this entry's boundary.
- **WP-ERROR-004 — Database Permission Denied**: presumes successful network connectivity, successful authentication, and a selected database, with the failure occurring because the authenticated user lacks privileges for a specific operation. This is a much later step than this entry's boundary.
- **WP-ERROR-007 — Database Connection Limit Exceeded**: the database server is reached over the network — a TCP connection to it succeeds — and the server itself then actively refuses the new connection at the protocol level because it has reached its maximum permitted connections. This is a materially different failure point from this entry's condition: WP-ERROR-007 involves a real response from a reachable, running server process; this entry involves no response from the server at all, because the network path to it never completes.
- **WP-ERROR-009 — Database Query Timeout** (conceptual reference; no corresponding document currently exists in this repository): presumes successful network connectivity, successful authentication, and an established connection, with the failure occurring later when a specific query does not complete in time. This is unrelated to network reachability.
- **WP-ERROR-018 — WordPress Database Connection Failure**: WP-ERROR-018 owns the general, verified-but-unspecified-cause condition where WordPress cannot establish a database connection, and explicitly identifies network-level unreachability as one specific, verified cause that belongs to this entry once confirmed. This entry owns that specific, verified cause; WP-ERROR-018 owns the general condition and the diagnostic process for narrowing it to a specific cause, along with this entry's relationship to WP-ERROR-013, WP-ERROR-014, and WP-ERROR-016, which this entry does not restate.

---

# 7. Scope

**Covered:** A verified condition in which the network path between WordPress's hosting environment and the configured database host fails before the database server process has any opportunity to respond — including DNS resolution failure, an unreachable or offline host, a blocked or filtered network path, an unavailable or closed port, a failed route, or a failed intermediary (a reverse proxy, SSH tunnel, or connection-pooling proxy sitting between WordPress and the database server).

**Excluded:**

- Authentication rejection after the database server was actually reached (see WP-ERROR-002).
- Connection refusal due to the server's connection limit, which presumes the server itself was reached (see WP-ERROR-007).
- Selection of a nonexistent database after successful network connectivity and authentication (see WP-ERROR-003).
- Insufficient privileges after successful network connectivity, authentication, and database selection (see WP-ERROR-004).
- Query timeouts after a successful, established, authenticated connection (see WP-ERROR-009).
- Any condition in which the database server process actually receives and responds to a connection attempt, regardless of how it responds.

---

# 8. WordPress Components

Listed as components commonly involved, not as a claim that every installation exercises every one of them identically:

- The `wpdb` class (`wp-includes/class-wpdb.php`), specifically its connection-establishment logic, shared with WP-ERROR-018.
- The `DB_HOST` constant defined in `wp-config.php`, whose value determines the hostname, IP address, port, or socket path WordPress attempts to reach.
- The PHP database driver's (mysqli or PDO_MySQL) underlying socket and DNS-resolution behavior, which surfaces network-level failures as distinct error conditions (for example, `getaddrinfo failed`, `Connection refused`, `Connection timed out`, or `No route to host`) rather than an authentication or protocol-level error.
- The server or container host's own DNS resolver configuration, `/etc/hosts` file, and network routing.
- Firewall rules, cloud security groups, and network access-control lists governing traffic between the web tier and the database host.
- Container and orchestration platform networking (for example, Docker's internal DNS-based service discovery, or Kubernetes Services and NetworkPolicies), where WordPress and the database run as separate networked services.
- Any intermediary between WordPress and the database server, such as an SSH tunnel, a cloud provider's connection proxy (for example, a managed Cloud SQL proxy), or a connection pooler, whose own failure presents identically to the database server itself being unreachable.
- WP-CLI's own database-connectivity commands, which depend on the same underlying network attempt as web requests.

---

# 9. Typical Symptoms

- WordPress's own generic "Error establishing a database connection" message, indistinguishable at the WordPress level from WP-ERROR-018's other specific causes; the specific network-level detail is visible only in server-side or PHP-level logs, not in WordPress's own user-facing message.
- A PHP or mysqli-level error rather than a database-protocol error, such as `php_network_getaddresses: getaddrinfo failed` (DNS resolution failure), `Connection refused` (the host was reached but nothing is listening on the target port), `Connection timed out` (packets are not receiving any response, typically indicating a firewall silently dropping traffic or a routing failure), or `No route to host` (no valid network path exists to the target address).
- The corresponding MySQL/MariaDB client error codes surfaced by the PHP database driver: error 2002 ("Can't connect to local MySQL server through socket") for a failed Unix-socket connection, or error 2003 ("Can't connect to MySQL server on '<host>' (<errno>)") for a failed TCP/IP connection, where `<errno>` is the underlying OS-level socket error.
- The failure occurring identically across front-end, administrative, AJAX, cron, REST, and WP-CLI paths, since all depend on the same network attempt.
- A site that previously connected successfully beginning to fail immediately after a DNS change, a firewall or security-group change, a database server migration to a new host or IP address, a hosting or infrastructure migration, or a container/orchestration redeployment.
- The same network destination failing when tested independently of WordPress (for example, with a direct TCP connectivity check to the configured host and port), confirming the failure is not specific to WordPress's own connection attempt.
- A failure that occurs only from certain runtime contexts (for example, from a scheduled cron job or a hosting-panel-managed WP-CLI environment) but not others, indicating the network path differs by context rather than the database server itself being down.

---

# 10. Common Causes

Causes are grouped by category. Inclusion in this list identifies a category as plausible; it does not assert that any specific cause is present without diagnostic confirmation.

- An incorrect `DB_HOST` value in `wp-config.php` — a mistyped hostname, a stale IP address left over from a prior migration, or an incorrect port appended to the hostname.
- DNS resolution failure — the hostname in `DB_HOST` cannot be resolved to an address at all, due to a deleted or changed DNS record, an unreachable or misconfigured DNS resolver, or a stale DNS cache.
- The database server itself being offline — the server process not running, having crashed, or the underlying host having been stopped, rebooted, or decommissioned.
- A firewall, cloud security group, or network access-control list blocking traffic between the web tier and the database host, whether through an explicit deny rule or the absence of a required allow rule.
- The target port being unavailable — nothing listening on the expected port because the database service uses a different port than configured, or because the service failed to bind to the expected interface.
- Container or orchestration networking failures — an incorrect service name used for internal DNS-based service discovery, a container or pod not yet ready when WordPress attempts to connect, or a network policy blocking traffic between the WordPress and database containers or pods.
- Routing failures — no valid network path exists between the web tier and the database host, for example due to a misconfigured VPC peering connection, an incorrect subnet route, or a VPN connection being down.
- A failed intermediary between WordPress and the database server — an SSH tunnel that has disconnected, a managed cloud database proxy that has stopped or lost its own credentials, or a connection pooler that is itself unreachable — presenting identically to the database server being unreachable even though the database server itself may be healthy.
- `DB_HOST` set to `localhost`, which most MySQL and MariaDB client libraries interpret as an instruction to connect through a local Unix domain socket rather than over TCP/IP, combined with the expected socket file being absent, moved, or at a nonstandard path — a failure that resembles network unreachability even though no actual network is involved; using `127.0.0.1` instead forces a genuine TCP/IP connection and avoids this specific ambiguity.

---

# 11. Diagnosis

Verify the following:

1. Confirm this is genuinely a network-level unreachability failure — a DNS-resolution error, connection-refused error, connection-timeout error, or no-route-to-host error — rather than a later-stage condition (authentication rejection, connection-limit refusal, database selection, permissions, or query timeout) that presumes the database server was actually reached.
2. Capture the exact underlying error text where accessible (for example, `getaddrinfo failed`, `Connection refused`, `Connection timed out`, or `No route to host`), since WordPress's own generic message does not distinguish this cause from others WP-ERROR-018 documents, and the specific error text indicates which stage of network connectivity failed.
3. Confirm the exact `DB_HOST` value WordPress is using, including whether it is a hostname, an IP address, `localhost`, or a Unix socket path, since each is resolved differently and fails for different reasons.
4. Where `DB_HOST` is `localhost`, confirm whether the connection is actually expected to use a Unix domain socket, and whether the expected socket file exists at the path the PHP database driver expects; this is a distinct check from DNS or TCP connectivity.
5. Test DNS resolution of the configured hostname independently of WordPress, to confirm whether the hostname resolves to an address at all.
6. Test raw TCP connectivity to the exact resolved address and port independently of WordPress (for example, from the same host WordPress runs on, using a basic network connectivity tool), to isolate whether the failure is genuinely at the network layer rather than specific to WordPress's own connection attempt.
7. Where raw TCP connectivity fails without an immediate connection-refused response, trace the network path to the configured host and port (for example, using a standard route-tracing utility) to localize the failure to a specific hop or network segment, particularly to identify which team or provider to escalate to when the full path is not under the diagnosing engineer's control.
8. Distinguish between a fast, explicit connection-refused response (indicating the host was reached but nothing is listening on the target port) and a slow connection-timeout with no response at all (indicating packets are being silently dropped by a firewall, security group, or a routing failure), since these point toward different corrective actions.
8. Confirm whether the database server process itself is running and actively listening on the expected interface and port, where the engineer performing diagnosis controls that server.
9. Confirm firewall, security-group, and network access-control rules permit the required traffic between the web tier and the database host, on both the originating and receiving sides.
10. In containerized or orchestrated environments, confirm that internal service discovery resolves the expected service name, that the database container or pod is running and ready, and that no network policy blocks the required traffic.
11. Where an intermediary (an SSH tunnel, a cloud database proxy, or a connection pooler) sits between WordPress and the database server, confirm that intermediary itself is running, connected, and correctly configured, since its failure presents identically to the database server itself being unreachable.
12. Determine whether this is a recent regression (previously working, now failing) or a new configuration; a recent regression points toward a DNS change, a firewall or security-group change, a network migration, or an infrastructure redeployment, while a new configuration points toward a simple setup error.
13. Where web, administrative, cron, and WP-CLI paths behave differently, determine whether they reach the database host from different network contexts (for example, different outbound IP addresses, different container networks, or different firewall rules), since a firewall or network policy may permit one context and block another.
14. Preserve relevant evidence — error messages, timestamps, and any network-level diagnostic output — before making any change.
15. Where the engineer performing diagnosis does not control the network path, firewall, or hosting infrastructure, escalate to the network administrator, database administrator, or hosting provider rather than attempting an unverified workaround.

---

# 12. Recovery Procedure

Recovery shall target the verified cause of the network-level unreachability, not merely the visible symptom.

Permitted recovery categories, depending on the verified cause, include:

- Correcting an incorrect `DB_HOST` value in `wp-config.php` where diagnosis confirms the hostname, IP address, or port is wrong.
- Correcting a `localhost`-versus-Unix-socket misconfiguration where diagnosis confirms it as the cause — either restoring the expected socket file to the path the PHP database driver expects, or changing `DB_HOST` from `localhost` to `127.0.0.1` to force a genuine TCP/IP connection instead.
- Restoring DNS resolution — correcting or restoring a DNS record, or resolving a DNS resolver failure — where diagnosis confirms a DNS-resolution failure, in coordination with whoever administers DNS for the affected domain or network.
- Restarting or restoring the database server process where diagnosis confirms it is offline, and the engineer performing recovery controls that server.
- Correcting firewall, security-group, or network access-control rules to permit the required traffic on the correct port, where diagnosis confirms a blocking rule, in coordination with the network or security administrator where the engineer performing recovery does not control that infrastructure.
- Correcting routing configuration (for example, a VPC peering connection, a subnet route, or a VPN connection) where diagnosis confirms a routing failure, in coordination with the network administrator.
- Correcting container or orchestration networking (service names, network policies, or startup ordering so WordPress does not attempt to connect before the database service is ready) where diagnosis confirms a container or orchestration-level cause.
- Restoring or reconfiguring a failed intermediary (an SSH tunnel, a cloud database proxy, or a connection pooler) where diagnosis confirms the intermediary itself, rather than the database server, is the unreachable component.
- Escalating to the network administrator, database administrator, or hosting provider where the engineer performing diagnosis does not control the relevant network infrastructure.

Recovery shall not open firewall, security-group, or network access-control rules more broadly than necessary to restore connectivity — for example, exposing the database port to unrestricted public access — merely to resolve an unreachability condition when a narrower, correctly scoped rule is available.

---

# 13. Validation

Recovery is successful when:

- WordPress establishes a network connection to the database server successfully across front-end, administrative, AJAX, cron, REST, and WP-CLI paths, each independently confirmed.
- Raw DNS resolution and TCP connectivity to the configured host and port succeed independently of WordPress, confirmed separately from WordPress's own successful connection.
- The connection remains reachable consistently over time and across repeated attempts, not merely on a single successful attempt, since some causes (an intermittent routing issue, a flapping DNS resolver) may not manifest on every attempt.
- No equivalent network-level connection failure recurs in logs across repeated, fresh requests.
- The specific underlying cause identified during diagnosis (DNS, firewall or security group, offline server, routing, container networking, or a failed intermediary) has been verifiably addressed, not merely that an attempt happened to succeed once.
- No firewall, security-group, or network access-control rule remains broader than necessary as a leftover result of diagnosis or recovery.

---

# 14. Prevention

- Monitor DNS resolution and raw TCP connectivity to the database endpoint proactively, independently of application-level monitoring, so a network-level failure is detected before or alongside application errors.
- Keep `DB_HOST` and network architecture (firewall rules, security groups, routing, DNS records) documented and synchronized with actual infrastructure, particularly after any migration.
- Test connectivity from every runtime context (web, cron, WP-CLI, any hosting-panel-managed scheduled task) after any network, firewall, DNS, or infrastructure change, rather than assuming all contexts share the same network path.
- In containerized or orchestrated environments, use startup or readiness health checks so WordPress does not attempt to connect before the database service is actually ready to accept connections.
- Maintain a documented escalation path to the network or infrastructure team for connectivity issues the engineer diagnosing a WordPress-level symptom does not have the access or authority to resolve directly.

---

# 15. Security Considerations

- Do not open firewall, security-group, or network access-control rules more broadly than necessary (for example, exposing the database port to the public internet) as a shortcut to restoring connectivity; grant the narrowest network access that is actually correct.
- Do not disable firewalls or network security controls entirely as a diagnostic shortcut in a production environment.
- Avoid exposing internal hostnames, IP addresses, or network topology details in diagnostic output, logs accessible to unauthorized users, or a customized database-error page.
- Coordinate firewall, security-group, and routing changes through a platform-appropriate change-management process rather than as ad hoc, undocumented changes to shared network infrastructure.

---

# 16. Related Errors

The following are cited as they exist in this repository, or as conceptual distinctions where noted.

1. [WP-ERROR-002 — WordPress Database Authentication Failure](WP-ERROR-002-WORDPRESS-DATABASE-AUTHENTICATION-FAILURE.md) — exists in this repository; see Section 6 (Distinction) above.
2. [WP-ERROR-003 — Database Does Not Exist](WP-ERROR-003-DATABASE-DOES-NOT-EXIST.md) — exists in this repository; see Section 6 (Distinction) above.
3. [WP-ERROR-004 — Database Permission Denied](WP-ERROR-004-DATABASE-PERMISSION-DENIED.md) — exists in this repository; see Section 6 (Distinction) above.
4. [WP-ERROR-007 — Database Connection Limit Exceeded](WP-ERROR-007-WORDPRESS-DATABASE-CONNECTION-LIMIT-EXCEEDED.md) — exists in this repository; see Section 6 (Distinction) above.
5. WP-ERROR-009 — Database Query Timeout (conceptual reference; no corresponding document currently exists in this repository; no link is provided).
6. [WP-ERROR-018 — WordPress Database Connection Failure](WP-ERROR-018-WORDPRESS-DATABASE-CONNECTION-FAILURE.md) — exists in this repository; see Section 6 (Distinction) above.

---

# 17. Notes

This entry documents the specific, verified condition of network-level unreachability to the configured database server, as one of several specific causes deferred by the general connection-failure entry, WP-ERROR-018. It does not restate WP-ERROR-018's own boundary, its relationship to WP-ERROR-013, WP-ERROR-014, or WP-ERROR-016, or the general diagnostic process for narrowing an unspecified connection failure to a specific cause; see WP-ERROR-018 for that content. Consistent with the single-responsibility principle in **SF-SPEC-001** Section 4.3, cause-specific conditions for individual DNS providers, individual cloud networking platforms, or individual container-orchestration systems may each be documented by a separate, independently created `WP-ERROR` entry without altering this one.

This entry underwent the review sequence required by **SF-SPEC-001** Section 19, **SF-SPEC-005** Section 5.6, and **SF-SPEC-012**: an author (Class A) review at `docs/reviews/SF-REVIEW-018-WP-ERROR-008-AUTHOR-REVIEW.md`, followed by an independent (Class B) review at `docs/reviews/SF-REVIEW-019-WP-ERROR-008-INDEPENDENT-REVIEW.md`, which independently re-verified the non-existence of WP-ERROR-003, 004, 007, and 009, reached outcome **Approved with Minor Revisions**, applied and re-validated the one required revision, and satisfied the Production Ready gate per SF-SPEC-012 Section 12. Its Status was changed to Production Ready on that basis. This document does not itself constitute either review record; see the cited files for full findings, corrections, and gate decisions.

The independent review did not designate this entry as a Reference Implementation. That designation, governed separately by **SF-SPEC-001** Section 22, has not been sought or asserted here.

No Reference Implementation is currently designated by **SF-SPEC-001**; this entry's relationship to that designation, and to any future `WP-SCENARIO-XXX` runtime evidence, is not asserted here and shall not be assumed until such evidence or designation actually exists.
