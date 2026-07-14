# WP-ERROR-029 — WordPress Outbound TLS Negotiation Failure

---

# 1. Knowledge Entry

WordPress Outbound TLS Negotiation Failure

---

# 2. Metadata

* **Error ID:** `WP-ERROR-029`
* **Title:** WordPress Outbound TLS Negotiation Failure
* **Category:** Networking
* **Severity:** Critical
* **Recovery Priority:** Immediate
* **Status:** Production Ready
* **Version:** 1.0

---

# 3. Summary

WordPress successfully establishes an outbound network connection, but cannot establish a trusted TLS session required for HTTPS communication. The underlying network connection this entry presumes was already confirmed working (`WP-ERROR-028`'s own condition, resolved favorably); the failure is entirely within the TLS handshake itself — whether because the remote host's own certificate cannot be trusted, or because the client and server cannot agree on a mutually acceptable protocol version or cipher suite.

---

# 4. Primary Failure Mode

An outbound HTTPS request, made through WordPress's own HTTP API (`WP_Http`, via `wp_remote_get()`/`wp_remote_post()`/`wp_remote_request()`), successfully completes a TCP connection to the target host, but the subsequent TLS handshake fails — either because certificate verification rejects the remote host's presented certificate, or because the client and server cannot negotiate a mutually supported protocol version or cipher suite — and `WP_Http::request()` returns a `WP_Error` describing the TLS-layer failure, before any HTTP request/response exchange occurs.

---

# 5. Severity

This entry is classified **Critical**, with impact ranging depending on what the failing request was for and how widespread the underlying cause is:

- Where a single remote host's own certificate is misconfigured (expired, wrong hostname), the impact is typically narrow — only requests to that specific host fail, while every other outbound HTTPS request continues to succeed.
- Where the underlying cause is systemic on WordPress's own side (an outdated local CA bundle, an OpenSSL build that has dropped support for a protocol version many remote hosts still require), every outbound HTTPS request across every integration can fail simultaneously, even though each remote host's own certificate and configuration may be entirely correct.
- This entry remains classified at the level of its most severe possible manifestation, consistent with the range-based Critical classification used elsewhere in this catalog (`WP-ERROR-021`, `024`–`028`).

---

# 6. Distinction

This entry applies only when verified evidence establishes that the underlying network connection was genuinely established — not that the connection itself failed — and that the failure is specifically within the TLS handshake: certificate trust, or protocol/cipher negotiation.

**The primary boundary, stated precisely:** `WP-ERROR-028` owns "no network connection"; this entry owns "network connection exists, secure channel cannot be established." The two are mutually exclusive by construction — a TLS handshake cannot even be attempted until the underlying TCP connection has already succeeded, and a request that never reaches this entry's own condition necessarily belongs to `WP-ERROR-028` instead.

**Eight causes this entry deliberately keeps separate, not blended into a single generic "TLS failed" condition:**

1. **Certificate chain cannot be validated** — the presented certificate's own issuing chain does not resolve to a trusted root, independent of expiry or hostname.
2. **Certificate expired or not yet valid** — the certificate's own validity window does not cover the current date, a time-based condition distinct from chain trust.
3. **Hostname mismatch** — the certificate is otherwise validly chained and within its validity window, but does not cover the hostname actually being requested.
4. **Unsupported TLS protocol version** — the client and server cannot agree on a TLS protocol version either supports (for example, a remote host offering only TLS 1.0/1.1 against a client whose OpenSSL build has dropped support for those versions) — a protocol-negotiation failure, not a certificate-trust failure.
5. **No mutually supported cipher suite** — the client and server both support a common protocol version but cannot agree on a cipher suite within it.
6. **Local CA trust store problems** — WordPress's own bundled CA certificate store (`wp-includes/certificates/ca-bundle.crt`), or the underlying system's own trust store where a transport relies on it instead, is outdated, corrupted, or missing, causing an otherwise genuinely valid remote certificate to be rejected.
7. **TLS inspection or proxy presenting an untrusted certificate** — a corporate, hosting-provider, or security-appliance TLS-inspecting proxy sits between WordPress and the intended remote host, presenting its own certificate in place of the remote host's — a condition that presents identically to an ordinary certificate-trust failure but has an entirely different cause and remedy.
8. **Client/server TLS negotiation failure for other protocol-level reasons** — a residual category for a verified TLS handshake rejection that does not fit causes 1–7 specifically (for example, a malformed Server Name Indication extension, or a server-side TLS implementation defect), to be diagnosed on its own merits rather than forced into one of the eight named causes above without evidence.

It is distinct from:

- **`WP-ERROR-028` — WordPress Outbound HTTP Request Failure**: owns the condition of no network connection being established at all. This entry presumes the opposite.
- **An HTTP 4xx/5xx response received after a successful TLS handshake**: the connection and the secure channel both succeeded; only the remote application's own HTTP-level response was an error or unexpected status. This is not currently owned by any entry in this catalog — `WP-ERROR-028` Section 7 already discloses this as a known gap, and this entry does not claim it either.
- **A browser's own certificate warning, unrelated to any WordPress-initiated outbound request**: a visitor's browser rejecting the *site's own* inbound-facing certificate when loading the WordPress site itself is an entirely different condition (the site's own certificate, evaluated by a visitor's browser) from WordPress, as a client, evaluating a *remote* host's certificate during an outbound request. This entry owns only the latter.
- **`WP-ERROR-014` — Required PHP Extension Missing**: owns the condition of `curl`/OpenSSL support being unavailable to the PHP runtime at all — a precondition for TLS to be attempted in the first place. This entry presumes TLS *is* available as a capability and the negotiation itself, once attempted, fails. This boundary requires particular care for causes 4–5 above (protocol version, cipher suite), since `WP-ERROR-014`'s own Section 11 (Diagnosis) explicitly names "a `curl` build without a specific SSL backend or protocol" as an example within its own territory. The distinction is scope, not mechanism: `WP-ERROR-014` owns a *categorical* gap — WordPress's own `curl`/OpenSSL build cannot negotiate a given protocol or cipher with *any* remote host, verified as an environment-wide limitation — while this entry owns the *observable, request-specific* negotiation failure as the correct diagnostic entry point, including cases that, once fully root-caused, turn out to be that same categorical gap. Where diagnosis (Section 11) confirms the limitation is categorical rather than specific to one remote host's own unusual requirements, remediation escalates to `WP-ERROR-014`, the same pattern `WP-ERROR-028` Section 6 already establishes for transport availability generally.

---

# 7. Scope

**Covered:** A verified condition in which an outbound HTTPS request's underlying network connection succeeds, but the TLS handshake fails — a certificate-trust failure (any of causes 1–3, 6–7 in Section 6) or a protocol/cipher negotiation failure (causes 4–5, or the residual case 8) — before any HTTP request/response exchange occurs.

**Excluded:**

- DNS resolution failure (`WP-ERROR-028`).
- TCP connection failure — refused, timed out, or reset during establishment (`WP-ERROR-028`).
- An HTTP 4xx/5xx response received after a successful TLS handshake — the secure channel was established correctly; only the remote application's own response was an error. Not currently owned by any entry in this catalog; see Section 6.
- Browser certificate warnings unrelated to a WordPress-initiated outbound request (a visitor's own browser evaluating the site's own inbound-facing certificate).
- A missing or unavailable `curl`/OpenSSL capability preventing TLS from being attempted at all (`WP-ERROR-014`).
- Browser-enforced CORS policy, which presumes the underlying request/response cycle already completed successfully (`WP-ERROR-030`).

---

# 8. WordPress Components

Listed as components commonly involved, not as a claim that every installation exercises every one of them:

- `WP_Http` (`wp-includes/class-wp-http.php`) and its transports (`WP_Http_Curl`, `WP_Http_Streams`), which each independently implement TLS verification via the underlying PHP `curl` extension (`CURLOPT_SSL_VERIFYPEER`/`CURLOPT_SSL_VERIFYHOST`) or PHP stream SSL context options (`verify_peer`/`verify_peer_name`), respectively.
- The `sslverify` request argument (default `true`) accepted by `wp_remote_get()`/`wp_remote_post()`/`wp_remote_request()`, and the `https_ssl_verify` filter, both of which control whether certificate verification is enforced for a given request.
- WordPress core's own bundled CA certificate store, `wp-includes/certificates/ca-bundle.crt`, used by both core transports as the trusted root list unless the environment is configured to rely on the system's own trust store instead.
- The `https_local_ssl_verify`/related transport-selection filters and the general request-args filter chain, through which a plugin or theme could alter TLS-verification behavior for specific requests.
- The PHP `curl` extension's own OpenSSL (or other TLS library) build, whose own supported protocol-version and cipher-suite set determines what this entry's causes 4–5 can even attempt to negotiate — a system-level capability distinct from whether `curl` itself is present at all (`WP-ERROR-014`'s own concern).

---

# 9. Typical Symptoms

- `is_wp_error( $response )` evaluates `true`, with a message describing an SSL/TLS-specific failure ("SSL certificate problem," "certificate has expired," "unable to get local issuer certificate," "SSL: no alternative certificate subject name matches target host name," or a protocol/cipher-negotiation-specific message such as "SSL routines... unsupported protocol" or "no ciphers available").
- The identical outbound request succeeds when tested from a different server or environment, isolating the cause to something specific to the originating WordPress server's own TLS configuration or trust store, rather than the remote host.
- The condition began abruptly at a specific, identifiable time correlating with a remote host's own certificate renewal, a server-side OpenSSL/PHP upgrade or downgrade, or a hosting-provider infrastructure change — rather than having always been present.
- A specific integration fails while every other outbound HTTPS request continues to succeed, suggesting a remote-host-specific cause (that host's own certificate or protocol support) rather than a systemic, WordPress-side one.
- Every outbound HTTPS request fails simultaneously across every integration, suggesting a systemic, WordPress-side cause (an outdated local CA bundle, a dropped protocol version) rather than any individual remote host's own misconfiguration.

---

# 10. Common Causes

Causes are grouped by the same eight-way separation Section 6 establishes. Inclusion in this list identifies a cause as plausible; it does not assert that any specific one is present without diagnostic confirmation.

- The remote host's own certificate chain is incomplete or misconfigured on the server side (a missing intermediate certificate), preventing chain validation even though the leaf certificate itself is genuinely valid.
- The remote host's own certificate has expired, or (less commonly) is not yet within its validity start date, due to a missed renewal or a clock/configuration error on the remote host's side.
- The remote host's own certificate does not cover the specific hostname being requested (a certificate issued for a different subdomain, or a wildcard certificate misapplied).
- The remote host has been reconfigured to require only newer TLS protocol versions than WordPress's own server-side OpenSSL build supports, or, less commonly, only older versions a modern OpenSSL build has since dropped.
- The remote host's own configured cipher-suite set shares no member with the WordPress server's own OpenSSL build's supported set, following a security-hardening change on either side.
- WordPress's own bundled CA certificate store (`ca-bundle.crt`) is outdated relative to a newly-issued or newly-rotated root/intermediate certificate the remote host now presents, or the environment's own system trust store (where relied upon) is similarly outdated.
- A corporate network, hosting-provider security appliance, or intentional TLS-inspecting proxy between the WordPress server and the remote host intercepts the connection and presents its own certificate, which fails verification against the remote host's own expected chain.
- A verified TLS-layer rejection that does not match any of the above — logged and diagnosed on its own merits (Section 6, cause 8) rather than force-fit into a named cause without evidence.

---

# 11. Diagnosis

Diagnose from the broadest, least-invasive check to the most specific:

1. **Confirm the request is genuinely HTTPS.** Verify the actual URL scheme being requested — an `http://` request never attempts TLS at all, and a report of "TLS failure" for a plain-HTTP request indicates a different condition entirely (most likely `WP-ERROR-028`, or a misunderstanding of what failed).
2. **Confirm the underlying network connection is established**, ruling out `WP-ERROR-028`'s own condition before investigating TLS specifically — a connection that never completed cannot have a TLS-layer failure at all, and the two are easy to conflate from an error message alone without checking which stage actually failed.
3. **Inspect the presented certificate and hostname directly**, independent of WordPress (`openssl s_client -connect host:443 -servername host`, or an equivalent tool), to determine the certificate's own chain, validity window, and covered hostname(s) — rather than inferring these from the `WP_Error` message alone, which may not distinguish precisely between causes 1–3.
4. **Verify the local (WordPress-side) trust store.** Confirm whether WordPress's own bundled `ca-bundle.crt` is current, and whether the environment is configured to use it or the system trust store instead; test whether the same remote host's certificate validates successfully using the system's own `curl`/OpenSSL directly on the same server, isolating whether the trust-store problem is WordPress-specific or system-wide.
5. **Verify protocol and cipher compatibility** between the WordPress server's own OpenSSL build and the remote host, using a direct tool (`openssl s_client` with explicit `-tls1_2`/`-tls1_3` flags, or `nmap --script ssl-enum-ciphers`) rather than assuming compatibility from the generic error message alone.
6. **Investigate proxies or TLS interception only after the above** — confirm whether any network path between the WordPress server and the remote host passes through a TLS-inspecting proxy or security appliance, and whether the certificate actually observed in step 3 belongs to the expected remote host or to an intercepting device instead.
7. Preserve relevant evidence — the exact `WP_Error` message, the remote host and URL, the certificate details observed via direct inspection, and whether the condition is host-specific or systemic — before making any change to CA bundles, `sslverify` settings, or network configuration.

```text
# Example only — illustrates inspecting a remote host's presented certificate
# directly, independent of WordPress's own HTTP API.
openssl s_client -connect example.com:443 -servername example.com </dev/null 2>/dev/null | openssl x509 -noout -dates -subject -issuer
```

---

# 12. Recovery Procedure

Recovery shall target the specific, verified cause among the eight Section 6 distinguishes, rather than broadly disabling TLS verification.

**Disabling certificate verification (`sslverify => false`, or an equivalent `https_ssl_verify` filter override) is not a permitted recovery action for this entry**, except as a strictly temporary, explicitly time-boxed diagnostic step to confirm TLS is genuinely the failing layer — never as a standing production fix, since doing so defeats the security purpose TLS verification exists for, for every future request to that host, not only the one being diagnosed.

Permitted recovery categories, depending on the verified cause, include:

- Escalating to the remote host's own operator where their certificate chain, expiry, or hostname coverage is genuinely misconfigured — this is not a WordPress-side defect to work around.
- Updating WordPress's own bundled CA certificate store, or the system trust store where relied upon, where verification confirms it is genuinely outdated relative to a legitimately-issued remote certificate.
- Coordinating a protocol-version or cipher-suite compatibility fix with the remote host's own operator, where negotiation genuinely fails due to no mutually supported option — on whichever side (WordPress's OpenSSL build, or the remote host's own configuration) is actually out of step with current, secure practice.
- Identifying and, where legitimate, explicitly trusting a known, deliberate TLS-inspecting proxy's own certificate — only where that interception is an intentional, sanctioned part of the network's own security architecture, not as a workaround for an unexplained interception.
- Where the residual "other protocol reasons" cause is confirmed, escalating to whichever party's own TLS implementation is producing the non-standard behavior.

---

# 13. Validation

Recovery is successful when:

- The specific, previously-failing outbound HTTPS request completes its TLS handshake successfully and proceeds to an HTTP request/response exchange.
- Certificate verification remains fully enabled (`sslverify` at its default, no unconditional `https_ssl_verify` override left in place) — recovery did not, at any point beyond a strictly temporary diagnostic step, leave verification disabled.
- Where a CA bundle or trust-store update was applied, it is confirmed effective for the specific previously-failing host, and does not itself introduce a new failure for any other, previously-working outbound HTTPS request.
- Where a protocol/cipher compatibility fix was applied, it is confirmed to use current, secure protocol versions and cipher suites, not a downgrade to a legacy option merely to restore connectivity.
- Where a TLS-inspecting proxy's certificate was deliberately trusted, that trust is scoped as narrowly as the legitimate network architecture requires, not applied as a blanket trust of any intercepting certificate.

---

# 14. Prevention

- Keep WordPress's own bundled CA certificate store current via routine core updates, and monitor for environments configured to rely on a system trust store that itself requires independent maintenance.
- Monitor certificate expiry for business-critical outbound integrations proactively, the same way inbound-facing site certificates are typically monitored.
- Track deprecated TLS protocol versions and cipher suites on both the WordPress server's own OpenSSL build and any critical remote integration, coordinating upgrades before either side unilaterally drops support the other still requires.
- Document any known, sanctioned TLS-inspecting proxy in the network path, so a future diagnosis does not misattribute its presence to a genuine remote-host certificate problem.
- Test outbound HTTPS connectivity to business-critical integrations explicitly after any server-side OpenSSL, PHP, or hosting-environment change.

---

# 15. Security Considerations

- Disabling certificate verification, even temporarily, removes WordPress's own protection against a man-in-the-middle substituting a malicious endpoint for the intended remote host; any temporary use for diagnosis shall be reverted immediately and confirmed reverted as part of Validation (Section 13).
- A certificate observed during diagnosis that does not match the expected remote host at all — as opposed to matching it but failing a specific check (expiry, hostname) — may indicate a genuine, unsanctioned interception rather than an ordinary misconfiguration, and should be escalated with corresponding urgency rather than treated as routine troubleshooting.
- Trusting a TLS-inspecting proxy's certificate is a deliberate reduction in end-to-end trust for the affected traffic; it shall be applied only where that interception is a known, sanctioned part of the network architecture, and documented as such.
- Do not downgrade to a legacy, insecure TLS protocol version or cipher suite merely to restore connectivity with a remote host that has not kept its own configuration current; escalate instead.

---

# 16. Related Errors

The following are cited as they exist in this repository, or as conceptual distinctions where noted.

1. [WP-ERROR-028 — WordPress Outbound HTTP Request Failure](WP-ERROR-028-WORDPRESS-OUTBOUND-HTTP-REQUEST-FAILURE.md) — exists in this repository; see Section 6 (Distinction) above for how this entry begins exactly where connection establishment is confirmed.
2. WP-ERROR-030 — WordPress CORS (Cross-Origin) Policy Failure (conceptual reference; planned per `SF-TAXONOMY-004` Section 3, no corresponding document currently exists in this repository; no link is provided) — see Section 7 (Scope) above.
3. [WP-ERROR-014 — Required PHP Extension Missing](WP-ERROR-014-REQUIRED-PHP-EXTENSION-MISSING.md) — exists in this repository; see Section 6 (Distinction) above for the boundary between TLS capability existing and TLS negotiation succeeding once attempted.

---

# 17. Notes

This entry documents the general, verified observable condition of an outbound HTTPS request's TLS handshake failing after its underlying network connection succeeded. Per explicit direction, it deliberately separates certificate-trust causes from protocol/cipher-negotiation causes rather than treating "TLS failed" as one undifferentiated condition, since the corrective action and responsible party differ materially between them.

This entry inherits, rather than re-derives, the disclosed gap `WP-ERROR-028` Section 7 already named: an HTTP-level error response received after a successful TLS handshake remains unowned by any entry in this catalog.

`SF-TAXONOMY-004` was widened (Version 1.1 → 1.2) immediately before this entry was authored, per explicit project-owner direction: the taxonomy's original Version 1.0 scoping for this entry ("SSL/TLS Certificate Verification Failure") was narrower than the category actually needs, since a protocol/cipher negotiation failure is not a certificate problem at all. This entry is drafted against the corrected, Version 1.2 scope.

This is the second of three entries `SF-TAXONOMY-004` plans for the Networking category.

This entry underwent the review sequence required by **SF-SPEC-001** Section 19, **SF-SPEC-005** Section 5.6, and **SF-SPEC-012**: an author (Class A) review at `docs/reviews/SF-REVIEW-083-WP-ERROR-029-AUTHOR-REVIEW.md`, which found no defects, followed by an independent (Class B) review at `docs/reviews/SF-REVIEW-084-WP-ERROR-029-INDEPENDENT-REVIEW.md`, which reached outcome **Approved with Minor Revisions**, refined the boundary against `WP-ERROR-014` for protocol/cipher capability gaps specifically, and satisfied the Production Ready gate per SF-SPEC-012 Section 12. Its Status was changed to Production Ready on that basis. This document does not itself constitute either review record; see the cited files for full findings, corrections, and gate decisions.

No Reference Implementation is currently designated by **SF-SPEC-001**; this entry's relationship to that designation, and to any future `WP-SCENARIO-XXX` runtime evidence, is not asserted here and shall not be assumed until such evidence or designation actually exists.
