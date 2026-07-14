# WP-ERROR-028 — WordPress Outbound HTTP Request Failure

---

# 1. Knowledge Entry

WordPress Outbound HTTP Request Failure

---

# 2. Metadata

* **Error ID:** `WP-ERROR-028`
* **Title:** WordPress Outbound HTTP Request Failure
* **Category:** Networking
* **Severity:** Critical
* **Recovery Priority:** Immediate
* **Status:** Production Ready
* **Version:** 1.0

---

# 3. Summary

WordPress attempted an outbound HTTP request through the WordPress HTTP API (`WP_Http`, via `wp_remote_get()`/`wp_remote_post()`/`wp_remote_request()`), but no available transport was able to establish a network connection to the target host. This entry is deliberately transport-agnostic: whether the request was attempted via `curl`, PHP streams, or another registered transport is an implementation detail of *how* WordPress tried, not a definition of *what failed*. The failure is that a connection was never established — not that a connection was established and something afterward went wrong.

---

# 4. Primary Failure Mode

A call to `wp_remote_get()`, `wp_remote_post()`, `wp_remote_request()`, or an equivalent `WP_Http`-based function fails to establish a network connection to the request's target host, and `WP_Http::request()` returns a `WP_Error` (`is_wp_error()` evaluates `true`) describing a connection-establishment failure — DNS resolution failure, connection refused, connection timeout, or a connection reset occurring during the establishment attempt itself — before any HTTP request/response exchange with the remote host could begin.

---

# 5. Severity

This entry is classified **Critical**, with impact ranging depending on what the failing outbound request was for:

- Where a single, non-essential integration's own outbound request fails (a single plugin's optional third-party API call), the impact is typically narrow — that one integration's own feature degrades while the rest of the site continues to function normally.
- Where a core WordPress mechanism depends on outbound connectivity (plugin/theme/core update checks, `.org` API communication) or a business-critical integration does (a payment gateway, a transactional email API), the impact can be severe — the affected functionality fails completely, with no application-level workaround, for as long as the connection-level condition persists.
- This entry remains classified at the level of its most severe possible manifestation, consistent with the range-based Critical classification used elsewhere in this catalog (`WP-ERROR-021`, `024`–`027`).

---

# 6. Distinction

This entry applies only when verified evidence establishes that a connection was never established at all — not that a connection was established and a subsequent stage (TLS negotiation, the HTTP exchange itself, or interpreting the remote host's own response) failed instead.

**This entry is transport-agnostic by design.** WordPress's HTTP API selects from whichever transports are registered and available (`curl`, PHP streams, or another) at the time of the request; which one was actually used is a diagnostic detail (Section 11), not part of this entry's own definition. A connection-establishment failure is the same condition regardless of which transport attempted and failed to establish it.

**Connection versus protocol — what a successful connection does not, by itself, imply:** establishing a TCP connection to a remote host does not mean TLS negotiation will succeed, that HTTP was successfully spoken once connected, or that any response was ever received. This entry owns only the connection-establishment stage; each of those later stages is either owned by a different entry or, in some cases, not yet owned by any entry in this catalog (see Section 7).

**Connection timeout versus read/response timeout — a distinction this entry deliberately keeps sharp:** a *connection timeout* is the transport failing to establish the socket connection itself within the configured time limit (`http_request_timeout`, filterable, commonly a 5-second default) — this entry's own condition. A *read timeout* (also called a response timeout) is the connection having already been established, a request already sent, and the remote host failing to respond in time — this is a distinct condition this entry does not cover, and this catalog does not currently own it under any entry; see Section 7.

It is distinct from:

- **`WP-ERROR-029` — WordPress Outbound SSL/TLS Certificate Verification Failure**: presumes a TCP connection was already established, and the failure is specifically in the TLS handshake/certificate-verification stage that follows. This entry ends, and `WP-ERROR-029` begins, exactly at the point a connection is confirmed established.
- **`WP-ERROR-021`/`022`/`023` — WordPress REST API entries**: own WordPress's own *inbound* REST API request-handling pipeline — a fundamentally different direction of communication, using `WP_REST_Server` rather than `WP_Http`. This entry owns only requests WordPress itself *initiates outward*.
- **`WP-ERROR-007`/`008` — Database entries**: own network-layer symptoms (timeout, unreachability) specifically as they affect WordPress's own configured MySQL/MariaDB connection, regardless of whether the underlying mechanism (DNS, TCP timeout) is identical in kind to this entry's own. The database connection is not made through `WP_Http` at all; it uses an entirely separate driver-level connection (`mysqli`, `PDO`, or the driver `WP-ERROR-002`/`007`/`008` document), per `SF-TAXONOMY-004` Section 2.
- **`WP-ERROR-014` — Required PHP Extension Missing**: owns the condition of no working transport being *available at all* (for example, `curl` unavailable with no functioning streams fallback). This entry presumes at least one transport is available and capable, and the failure is in that transport's own attempt to reach the remote host — not in whether a transport exists to attempt it with.

---

# 7. Scope

**Covered:** A verified condition in which WordPress's own HTTP API attempts an outbound request and no available transport succeeds in establishing a network connection to the target host — DNS resolution failure, connection refused, connection timeout (the connection-establishment attempt itself timing out), or a connection reset occurring during the establishment attempt itself, before any request/response exchange begins.

**Excluded:**

- TLS handshake or certificate verification failure, presuming the underlying connection succeeded (`WP-ERROR-029`).
- Read/response timeout, or a reset of an already-established connection during data transfer — in both cases, the connection was established and a request sent, but something failed afterward rather than during establishment. **This catalog does not currently own this condition under any entry.** It is disclosed here as a known gap rather than silently absorbed into this entry's own scope; a future revision to `SF-TAXONOMY-004` may add a dedicated entry for it if evidence shows it warrants distinct treatment from this entry's own connection-establishment condition.
- An HTTP-level error status code returned by the remote host once a connection and exchange *did* succeed (a third-party API returning `500` or `404`) — the connection and protocol exchange both succeeded; only the remote application's own response was an error. **This catalog does not currently own this condition under any entry either**, for the same reason.
- WordPress's own inbound REST API request handling (`WP-ERROR-021`/`022`/`023`).
- The MySQL/MariaDB database connection (`WP-ERROR-007`/`008`).
- No transport being available at all, as opposed to an available transport failing to connect (`WP-ERROR-014`).
- Browser-enforced CORS policy, which presumes WordPress's own request/response cycle already completed successfully (`WP-ERROR-030`).

---

# 8. WordPress Components

Listed as components commonly involved, not as a claim that every installation exercises every one of them, and not privileging any one transport as more central to this entry's own definition than another:

- `WP_Http` (`wp-includes/class-wp-http.php`), the core abstraction all outbound requests route through, and its `request()` method, which selects and delegates to an available, capable transport.
- `wp_remote_get()`/`wp_remote_post()`/`wp_remote_head()`/`wp_remote_request()` (`wp-includes/http.php`), the public functions most plugin, theme, and core code calls rather than using `WP_Http` directly.
- The registered transports — `WP_Http_Curl` and `WP_Http_Streams` are WordPress core's own two, selected based on availability and capability at request time; a request that fails to connect via one available transport has, by this entry's own transport-agnostic definition, still failed regardless of which transport attempted it.
- `wp_safe_remote_get()`/`wp_safe_remote_post()` and the underlying SSRF-protection logic (`WP_Http::is_ip_public()` and related filters), which deliberately blocks requests to private/reserved IP ranges by default — a request blocked by this mechanism fails at connection-establishment for a different, deliberate reason than an ordinary network failure, and diagnosis (Section 11) should distinguish the two.
- The `WP_HTTP_BLOCK_EXTERNAL_HTTP` constant and `WP_ACCESSIBLE_HOSTS` allowlist, a WordPress-level (not network-level) mechanism that, when enabled, blocks all outbound requests except to explicitly allowlisted hosts — a request blocked here never reaches any transport's own connection attempt at all.
- The `http_request_timeout` filter, governing the connection-timeout duration this entry's own condition is measured against.
- `is_wp_error()`, the standard check every caller is expected to apply to a `WP_Http`-based function's return value before assuming success.

---

# 9. Typical Symptoms

- `is_wp_error( $response )` evaluates `true`, with the `WP_Error`'s own message describing a connection-level failure ("Could not resolve host," "Connection timed out," "Connection refused," "Failed to connect").
- A plugin's own integration feature fails silently or with a generic error, when its own code does not properly check `is_wp_error()` and instead attempts to use a `WP_Error` object as if it were a successful response array.
- WordPress core's own update-check, plugin/theme browsing, or `.org`-communicating features fail or show stale data, when the underlying outbound request to `api.wordpress.org` or a similar endpoint cannot connect.
- The condition reproduces consistently for every request to a specific host, or intermittently across many different hosts — the former suggests a host-specific or DNS-specific cause, the latter suggests a broader network, firewall, or `WP_HTTP_BLOCK_EXTERNAL_HTTP` cause.
- WP-Cron-triggered outbound requests (scheduled tasks that call external services) fail without any visible front-end symptom, surfacing only in logs or in the scheduled task's own downstream effects not occurring.

---

# 10. Common Causes

Causes are grouped by category. Inclusion in this list identifies a category as plausible; it does not assert that any specific cause is present without diagnostic confirmation.

- The requested URL itself is malformed, uses an unsupported scheme, or resolves to an unintended hostname due to a configuration or code error — distinct from the target host, once correctly identified, being unreachable.
- DNS resolution failure for the target hostname — the hostname does not resolve at all, resolves only via a DNS server the WordPress server cannot reach, or resolution times out.
- `WP_HTTP_BLOCK_EXTERNAL_HTTP` is defined and enabled, and the target host is not present in `WP_ACCESSIBLE_HOSTS` — a deliberate, WordPress-level block that produces the same symptom as a genuine network failure but has an entirely different, configuration-based cause and fix.
- A firewall, security group, or hosting-provider network policy blocks outbound traffic on the required port from the WordPress server's own network location.
- The remote host is genuinely down, unreachable, or actively refusing connections (including deliberately rate-limiting or blocking the WordPress server's own outbound IP address).
- `wp_safe_remote_get()`/`wp_safe_remote_post()`'s own SSRF protection blocks the request because the resolved target IP falls within a private or reserved range — a deliberate security behavior, not a network defect, easily mistaken for one if this mechanism is not known to be in effect.
- The connection genuinely times out within the configured `http_request_timeout` window — the remote host may be slow to accept connections specifically (distinct from being slow to respond after connecting, which is the excluded read-timeout condition).
- A local outage or misconfiguration in the WordPress server's own hosting environment (network interface issue, outbound proxy misconfiguration) prevents any outbound connection from that server, regardless of target.

---

# 11. Diagnosis

Diagnose from the broadest, least-invasive check to the most specific, confirming each layer before investigating the next:

1. **Confirm the URL being requested.** Verify the exact URL the failing call is actually attempting — via debug logging, a filter on the relevant hook, or direct code inspection — rather than assuming it matches what documentation or configuration suggests it should be. A malformed or unintended URL produces a connection failure for reasons unrelated to network conditions.
2. **Confirm the request was not blocked by WordPress's own configuration** before investigating network-layer causes at all: check whether `WP_HTTP_BLOCK_EXTERNAL_HTTP` is defined and enabled, and if so, whether the target host is present in `WP_ACCESSIBLE_HOSTS`. This is a WordPress-level block, not a network condition, and ruling it out first avoids misdiagnosing a deliberate configuration as a network defect.
3. **Confirm DNS resolution** for the target hostname independently of WordPress (`dig`/`nslookup`/`host` from the same server, or an equivalent hosting-provider diagnostic), rather than inferring it from the `WP_Error` message alone.
4. **Confirm outbound routing and firewall configuration** allow traffic from the WordPress server's own network location to the target host and port — check security groups, hosting-provider network policies, and any local firewall rules, before assuming the remote host itself is at fault.
5. **Confirm the remote host is actually reachable** — using a connectivity test independent of WordPress's own HTTP API (`curl`/`telnet`/an equivalent tool run directly on the server) to the same host and port, to establish whether the condition is specific to WordPress's own request or reproduces at the network level generally.
6. **Only once the above are confirmed**, investigate transport implementation details — which transport (`curl`, streams) actually attempted the request, whether it is correctly configured, and whether a transport-specific quirk (a `curl` build missing a required feature, a streams-specific limitation) is the remaining explanation once every broader cause has been ruled out.
7. Distinguish a connection timeout from a read timeout explicitly (Section 6): confirm, via logging or a packet capture where available, whether the failure occurred *before* any bytes were exchanged (this entry's own condition) or *after* a connection was established and a request sent (the excluded, currently-unowned read-timeout condition).
8. Where `wp_safe_remote_get()`/`wp_safe_remote_post()` is in use, confirm whether the target resolves to a private or reserved IP range that SSRF protection would deliberately block, before concluding the condition is an ordinary network failure.
9. Preserve relevant evidence — the exact URL, the exact `WP_Error` message and code, the transport that attempted the request, and whether the condition is host-specific or general — before making any network, firewall, or configuration change.

```text
# Example only — illustrates checking WP_HTTP_BLOCK_EXTERNAL_HTTP directly;
# exact usage depends on the WP-CLI version and site configuration.
wp eval 'var_dump( defined("WP_HTTP_BLOCK_EXTERNAL_HTTP") ? WP_HTTP_BLOCK_EXTERNAL_HTTP : null, defined("WP_ACCESSIBLE_HOSTS") ? WP_ACCESSIBLE_HOSTS : null );'
```

---

# 12. Recovery Procedure

Recovery shall target the specific, verified layer identified in Diagnosis (Section 11) — the URL, a WordPress-level block, DNS, routing/firewall, or remote-host reachability — rather than broadly loosening network or security controls.

Permitted recovery categories, depending on the verified cause, include:

- Correcting a malformed or unintended request URL.
- Adding a genuinely required, legitimate host to `WP_ACCESSIBLE_HOSTS`, where `WP_HTTP_BLOCK_EXTERNAL_HTTP` is intentionally enabled and the block is correctly functioning as designed but the specific host was simply omitted.
- Correcting DNS configuration or escalating to the DNS provider, where resolution is genuinely failing.
- Correcting firewall or security-group rules to permit the specific, required outbound traffic, scoped as narrowly as the legitimate need requires.
- Escalating to the hosting provider or network administrator where the engineer performing diagnosis does not control the relevant network layer.
- Escalating to the remote service's own operator where the remote host is confirmed down or actively blocking the WordPress server's own outbound IP, rather than attempting a WordPress-side workaround for a condition outside WordPress's own control.
- Increasing `http_request_timeout` only where diagnosis has confirmed the remote host is reachable but genuinely slow to accept connections, not as a blanket response to an unconfirmed cause.

This entry does not prescribe disabling `wp_safe_remote_get()`'s own SSRF protection, or broadly disabling `WP_HTTP_BLOCK_EXTERNAL_HTTP` site-wide, as a substitute for correctly diagnosing and narrowly addressing the actual verified cause.

---

# 13. Validation

Recovery is successful when:

- The specific, previously-failing outbound request completes and returns a non-`WP_Error` response (or, where the goal was only to re-establish connectivity, the request reaches the point of establishing a connection and beginning an HTTP exchange, distinct from whatever the remote host's own response content may be).
- The correction is confirmed effective from the actual WordPress server's own network location, not only from an unrelated diagnostic machine.
- Where a firewall or `WP_ACCESSIBLE_HOSTS` change was made, it is confirmed scoped only to the specific, legitimate host and port required, without unnecessarily broadening outbound access.
- Where the condition was intermittent, the correction is confirmed effective across multiple attempts over time, not only a single successful retry.
- No unrelated outbound integration was disturbed by the correction (a firewall or `WP_ACCESSIBLE_HOSTS` change did not inadvertently narrow access another integration depends on).

---

# 14. Prevention

- Document every external host WordPress is expected to reach outbound, particularly where `WP_HTTP_BLOCK_EXTERNAL_HTTP` is enabled, so `WP_ACCESSIBLE_HOSTS` can be maintained deliberately rather than reactively.
- Monitor outbound connectivity to business-critical integrations (payment gateways, transactional email, core update checks) proactively, rather than discovering failures only when a user-facing feature breaks.
- Include outbound network connectivity checks in deployment and migration validation, particularly after a hosting or network-provider change.
- Log `WP_Error` results from outbound requests with enough detail (URL, error message, timestamp) to distinguish connection-establishment failures from other conditions without needing to reproduce the issue live.
- Where SSRF protection (`wp_safe_remote_get()`) is relied upon, keep it enabled and address legitimate connectivity needs through explicit, narrow configuration rather than disabling the protection.

---

# 15. Security Considerations

- `wp_safe_remote_get()`/`wp_safe_remote_post()`'s SSRF protection exists specifically to prevent WordPress itself being used to reach internal/private network resources via an attacker-supplied URL; recovery shall not disable this protection as a diagnostic shortcut.
- `WP_HTTP_BLOCK_EXTERNAL_HTTP`, where intentionally enabled, is a deliberate security control limiting WordPress's own outbound attack surface; recovery shall add only the specific, verified-legitimate host required, not disable the mechanism broadly.
- Repeated, unexplained outbound connection failures to unfamiliar hosts may indicate compromised code (a plugin or theme attempting unauthorized outbound communication) rather than an ordinary network defect; diagnosis should include confirming the requesting code's own legitimacy where the target host was not expected.
- Do not loosen firewall rules beyond the specific host and port a verified, legitimate integration requires, even temporarily, as a troubleshooting shortcut.

---

# 16. Related Errors

The following are cited as they exist in this repository, or as conceptual distinctions where noted.

1. [WP-ERROR-029 — WordPress Outbound TLS Negotiation Failure](WP-ERROR-029-OUTBOUND-TLS-NEGOTIATION-FAILURE.md) — exists in this repository (currently `Draft`); see Section 6 (Distinction) above for how this entry ends exactly where connection establishment is confirmed.
2. [WP-ERROR-030 — WordPress CORS (Cross-Origin) Policy Failure](WP-ERROR-030-CORS-CROSS-ORIGIN-POLICY-FAILURE.md) — see Section 7 (Scope) above.
3. [WP-ERROR-007 — WordPress Database Connection Limit Exceeded](WP-ERROR-007-WORDPRESS-DATABASE-CONNECTION-LIMIT-EXCEEDED.md) and [WP-ERROR-008 — WordPress Database Server Unreachable](WP-ERROR-008-WORDPRESS-DATABASE-SERVER-UNREACHABLE.md) — exist in this repository; see Section 6 (Distinction) above for why the database connection is excluded regardless of shared underlying network mechanisms.
4. [WP-ERROR-014 — Required PHP Extension Missing](WP-ERROR-014-REQUIRED-PHP-EXTENSION-MISSING.md) — exists in this repository; see Section 6 (Distinction) above for the boundary between transport availability and transport connection behavior.

---

# 17. Notes

This entry documents the general, verified observable condition of WordPress's own outbound HTTP API failing to establish a network connection. It is deliberately written to be transport-agnostic — `curl` and streams are diagnostic details (Section 11), never the entry's own definition — consistent with this catalog's broader discipline of classifying by what failed, not by which implementation detail happened to be involved.

This entry explicitly discloses two conditions this catalog does not yet own under any entry: read/response timeout (distinct from the connection timeout this entry covers) and an HTTP-level error status returned by a remote host once a connection and exchange did succeed. Both are named directly in Section 7 rather than silently folded into this entry's own scope, so a future taxonomy revision can address them deliberately if evidence shows they warrant it.

This is the first of three entries `SF-TAXONOMY-004` plans for the Networking category, and establishes the "connection not established" baseline `WP-ERROR-029` builds on directly.

This entry underwent the review sequence required by **SF-SPEC-001** Section 19, **SF-SPEC-005** Section 5.6, and **SF-SPEC-012**: an author (Class A) review at `docs/reviews/SF-REVIEW-081-WP-ERROR-028-AUTHOR-REVIEW.md`, which found no defects, followed by an independent (Class B) review at `docs/reviews/SF-REVIEW-082-WP-ERROR-028-INDEPENDENT-REVIEW.md`, which reached outcome **Approved with Minor Revisions**, qualified the "connection reset" condition to specify it occurs during establishment rather than after, and satisfied the Production Ready gate per SF-SPEC-012 Section 12. Its Status was changed to Production Ready on that basis. This document does not itself constitute either review record; see the cited files for full findings, corrections, and gate decisions.

No Reference Implementation is currently designated by **SF-SPEC-001**; this entry's relationship to that designation, and to any future `WP-SCENARIO-XXX` runtime evidence, is not asserted here and shall not be assumed until such evidence or designation actually exists.
