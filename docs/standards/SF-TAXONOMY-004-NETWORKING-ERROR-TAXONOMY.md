# SF-TAXONOMY-004 — Networking Error Taxonomy

## Document Information

**Document ID:** SF-TAXONOMY-004

**Title:** Networking Error Taxonomy

**Classification:** Engineering Taxonomy — a lightweight, informally-adopted planning artifact, governed by **SF-SPEC-013 — Knowledge Category Lifecycle Specification** for its role in the category lifecycle, but not itself templated by any `SF-TEMPLATE-XXX` document (none currently exists for this artifact type). It is not governed by **SF-SPEC-005**'s review process as a knowledge artifact and does not itself require an author/independent review pair, though an independent review of it is planned per this project's established practice (`SF-REVIEW-034`, `045`, `069`), not as a normative requirement this document imposes on itself.

**Status:** Frozen — the entry set in Section 3 is fixed until this document is deliberately revised (see Section 6). "Frozen" here is an informal, self-defined term describing this document's own adopted-plan state; it is not a claim of the `Version Frozen` **SF-SPEC-001** Section 18 lifecycle stage, nor of any **SF-SPEC-008** Section 6 Version Status value, nor of the category-level `Baseline Certified` designation **SF-SPEC-013** Section 5.5 defines. This document carries a `Version` and `Revision History` for traceability only; it does not present itself as a "versioned engineering artifact" within **SF-SPEC-008**'s own scope, the same disclaimer `SF-TAXONOMY-001`/`002`/`003` make.

**Version:** 1.3

**Owner:** SquirrelForge

---

## 1. Purpose

Declare the Networking category's boundary and complete planned `WP-ERROR` entry set *before* any entry in it is authored, per **SF-SPEC-013** Section 5.1's Category Entry Criteria — the Category Work Order for the second candidate in the Knowledge Production Plan's roadmap.

This document does not itself contain any `WP-ERROR` knowledge content and is not cited as a `WP-ERROR` entry.

---

## 2. Category Boundary

**Networking** owns failures in WordPress's own *outbound* network communication — requests WordPress itself initiates to other hosts via its HTTP API (`WP_Http`, `wp_remote_get()`/`wp_remote_post()`/`wp_remote_request()`) — and browser-enforced cross-origin policy failures affecting a response WordPress itself already served correctly. It does not own inbound request handling to WordPress (that is REST API's or Bootstrap's territory, depending on the specific pipeline stage) or the MySQL/MariaDB database connection (Database's territory, regardless of the underlying network cause).

**Explicitly not owned by Networking:**

* **The MySQL/MariaDB database connection itself** — `WP-ERROR-007` (Database Connection Limit Exceeded) and `WP-ERROR-008` (Database Server Unreachable) already own network-layer symptoms (timeout, connection refused, DNS failure) *specifically as they affect the configured database host*. This category's own DNS/connection/timeout entry (`WP-ERROR-028`) covers the identical underlying transport-layer conditions, but only when the affected connection is an *outbound HTTP request WordPress's own HTTP API initiates* — not the database driver's own connection. A request that cannot resolve DNS for the database host is `WP-ERROR-008`'s condition; a request that cannot resolve DNS for a third-party API endpoint is this category's condition. The distinguishing fact is which connection failed, not which underlying network mechanism (DNS, TCP, timeout) is implicated — the two categories can share an identical root cause category (for example, "DNS resolution failure") while owning entirely different, non-overlapping conditions.
* **Inbound REST API request handling** — `WP-ERROR-021`/`022`/`023` already own the complete lifecycle of a request arriving *at* WordPress's own REST API (`wp-json`). This category owns only requests WordPress itself *sends outward*, an entirely different direction of communication using an entirely different code path (`WP_Http` versus `WP_REST_Server`).
* **CORS (Cross-Origin) policy failures** — explicitly claimed by this category, resolving a forward-reference `SF-TAXONOMY-002` Section 5 already made ("It may become its own entry within a future networking or HTTP-layer category, should one be deliberately created"). `WP-ERROR-021`'s own Section 6 excludes CORS from its own scope but defers to `SF-TAXONOMY-002` Section 5's reasoning rather than independently repeating this specific forward-looking promise itself. See `WP-ERROR-030` in Section 3 below. This is listed here under "explicitly not owned by Networking" only in the sense that it is not owned by *any other* category — it is explicitly claimed here, not excluded; the heading groups every boundary clarification this section makes, not only exclusions.
* **A missing or unavailable `curl` PHP extension preventing the HTTP API from functioning at all** — `WP-ERROR-014` (Required PHP Extension Missing) already owns the condition of `curl` (or another required extension) being unavailable to the runtime; its own Section 8 (WordPress Components) already names the HTTP API's dependency on `curl`, with a documented streams-based fallback. This category presumes a working HTTP transport is available (whether `curl`-based or streams-based) and owns only the network-layer behavior of requests made through it, not the runtime-capability question of whether a transport exists at all.
* **Application-layer authentication failures for a third-party service an outbound request targets** (an incorrect API key, an expired OAuth token for an external integration) — Plugin category, since the specific integration's own credential handling is plugin-specific, not a condition of WordPress's own HTTP transport layer. This category owns the *transport* succeeding or failing to reach and communicate with the remote host, not what the remote host's own API decides to do once reached.
* **Reverse-proxy or trusted-proxy misconfiguration affecting WordPress's own self-perception** (incorrect `is_ssl()` detection behind a load balancer, `REMOTE_ADDR`/`X-Forwarded-For` trust handling) — considered and deferred, not claimed by this category's initial entry set; see Section 5.

---

## 3. Planned Entries

| Entry | Title | Owns | Status |
|---|---|---|---|
| `WP-ERROR-028` | WordPress Outbound HTTP Request Failure | Transport-layer failure of an outbound request WordPress's own HTTP API (`WP_Http`, `wp_remote_get()`/`wp_remote_post()`/`wp_remote_request()`) initiates to another host: DNS resolution failure, connection refused, connection timeout, or a connection reset occurring during the establishment attempt itself — presuming a working transport (`curl` or streams) is available and the failure is in reaching or communicating with the remote host, not in the remote host's own application-layer response | Existing, Production Ready |
| `WP-ERROR-029` | WordPress Outbound TLS Negotiation Failure | A distinct, verified cause within outbound HTTPS requests: WordPress successfully establishes an outbound network connection, but cannot establish a trusted TLS session — certificate-trust failures (chain validation failure, an expired or not-yet-valid certificate, a hostname mismatch, a local CA trust-store problem, or a TLS-inspecting proxy presenting an untrusted certificate) *or* protocol-level negotiation failures (an unsupported TLS protocol version, no mutually supported cipher suite, or another handshake-level rejection) — distinct from `WP-ERROR-028`'s own connection-layer failures, from `WP-ERROR-014`'s own categorical curl/OpenSSL capability gaps, and from an HTTP-level response received *after* a successful handshake | Existing, Production Ready |
| `WP-ERROR-030` | WordPress CORS (Cross-Origin) Policy Failure | A browser, not WordPress, refuses to expose an already-successfully-completed response to the calling script, due to a missing or incorrect `Access-Control-Allow-Origin` (or related `Access-Control-*`) response header — the request WordPress received and answered (most commonly a REST API request, per `WP-ERROR-021`'s own deferral, but not exclusively) completed correctly; the failure is entirely browser-side policy enforcement based on headers WordPress did or did not send | Planned |

Nothing else is currently planned for this category. Any future addition to this table is a revision to this document, not an ad hoc decision made while authoring an unrelated entry, per **SF-SPEC-013** Section 5.7.

---

## 4. Ownership Model

The three entries divide along two different axes, not a single pipeline or a single "independent conditions" model:

* `WP-ERROR-028` and `WP-ERROR-029` are **sequential stages of the same outbound request**: a connection has to be established (`028`'s own territory) before a TLS handshake can even be attempted (`029`'s own territory, for HTTPS requests specifically). A DNS failure or connection timeout never reaches the TLS stage at all; any TLS-stage failure — whether a certificate-trust problem or a protocol/cipher negotiation problem — presumes the TCP connection itself already succeeded. The two are mutually exclusive by construction, the same way `WP-ERROR-021`–`023` are — an outbound request fails at exactly one of these two stages for exactly one reason at a time, for the subset of requests using HTTPS.
* `WP-ERROR-030` is **conceptually independent of the other two**: it presumes the *opposite* precondition — that WordPress's own request-response cycle (whether that was itself an outbound request or, more commonly, an inbound one WordPress answered) completed successfully. It shares no failure condition with `028`/`029` at all; it is included in this category because it is a networking/HTTP-layer condition in the broad sense, not because it forms a pipeline with the other two.

**Evidentiary basis for this structure:** `WP-ERROR-021`'s own Section 6 already establishes that a CORS failure is definitionally distinct from a request-pipeline failure ("the WordPress REST pipeline completes successfully in the ordinary sense... [CORS] does not fit any of the three stage-owned entries"). This taxonomy inherits that same reasoning rather than re-deriving it, and does not attempt to force `WP-ERROR-030` into a false sequential relationship with `028`/`029` merely because all three share a category value.

---

## 5. Candidates Considered and Rejected

* **Reverse-proxy / trusted-proxy misconfiguration** (incorrect `is_ssl()` detection, `REMOTE_ADDR`/`X-Forwarded-For` trust handling behind a load balancer or CDN): not given an entry in this initial set. This condition is arguably closer to a Configuration-category concern (WordPress's own awareness of its deployment environment) than a Networking-category one (the network communication itself functions correctly; WordPress merely misinterprets signals about it) — deferred rather than rejected outright, pending a decision on whether `Configuration` or `Networking` is the more appropriate eventual owner, which this taxonomy does not resolve unilaterally.
* **Webhook delivery failures** (WordPress or a plugin sending an outbound webhook notification that the receiving endpoint rejects or fails to acknowledge): not given a separate entry. A webhook is, mechanically, an outbound HTTP request like any other covered by `WP-ERROR-028`/`029`; no distinct failure condition beyond those two was identified that would justify a dedicated entry rather than treating a failed webhook delivery as an instance of one of them.
* **Third-party API rate-limiting responses** (a remote service returning HTTP 429 or an equivalent throttling response to a legitimate, correctly-transported WordPress request): not given an entry. The transport itself succeeded — connection established, TLS verified, a response received — and the remote service's own rate-limiting policy is an application-layer decision that specific integration's own Plugin-category documentation should address, not a condition of WordPress's own HTTP transport layer.
* **DNS-over-HTTPS or DNS-level security failures** (DNSSEC validation failures, DNS-over-HTTPS resolver issues): not given a separate entry. These are specific mechanisms by which the general DNS-resolution-failure condition `WP-ERROR-028` already owns might occur; no evidence was found that they require diagnostically distinct treatment from an ordinary DNS resolution failure at this taxonomy's current level of granularity.

---

## 6. Revision History

| Version | Date | Summary of Changes | Status |
|---|---|---|---|
| 1.0 | 2026-07-14 | Initial taxonomy: WP-ERROR-028 (Outbound HTTP Request Failure), WP-ERROR-029 (Outbound SSL/TLS Certificate Verification Failure), forming a two-stage sequential pair; WP-ERROR-030 (CORS Policy Failure), conceptually independent of the other two, resolving the forward-reference SF-TAXONOMY-002 Section 5 made. Explicitly disambiguated from Database (WP-ERROR-007/008, the database connection itself), REST API (inbound request handling), and PHP Runtime (WP-ERROR-014's curl-availability precondition, distinct from this category's own transport-behavior-once-available scope). Reverse-proxy misconfiguration, webhook delivery, third-party rate-limiting, and DNS-security-mechanism failures considered and deferred or rejected, per Section 5. | Frozen |
| 1.1 | 2026-07-14 | WP-ERROR-028 reached Production Ready (SF-REVIEW-081 author review, SF-REVIEW-082 independent review, which qualified the "connection reset" condition to specify it occurs during establishment rather than after). Status column updated from Planned to Existing, Production Ready and the Owns column's wording aligned with the entry's own corrected phrasing, in the same body of work as the promotion, per SF-SPEC-013 Section 5.7. No boundary content changed. | Frozen |
| 1.2 | 2026-07-14 | Widened `WP-ERROR-029` before authoring began, per explicit project-owner direction: retitled from "Outbound SSL/TLS Certificate Verification Failure" to "Outbound TLS Negotiation Failure," and its Owns description expanded from certificate-trust failures alone to also include protocol-level negotiation failures (unsupported TLS protocol version, no mutually supported cipher suite, other handshake-level rejections) and TLS-inspecting-proxy scenarios. The original Version 1.0 scoping was narrower than the category actually needs — a protocol/cipher negotiation failure is not a certificate problem at all, and forcing it into a certificate-scoped entry, or leaving it as an undisclosed gap the way `WP-ERROR-028` Section 7 disclosed its own gaps, would have been worse than widening the entry before it existed. Section 4's Ownership Model updated to describe the TLS stage generically rather than only its certificate-verification instance. This is a Section 5.6 Post-Certification-Change-equivalent correction performed *before* `WP-ERROR-029` exists, not after, so no post-certification process applies — the category is not yet Baseline Certified, and this taxonomy remains revisable without triggering that heavier process. | Frozen |
| 1.3 | 2026-07-14 | WP-ERROR-029 reached Production Ready (SF-REVIEW-083 author review, SF-REVIEW-084 independent review, which refined the entry's own boundary against WP-ERROR-014 for protocol/cipher capability gaps specifically). Status column updated from Planned to Existing, Production Ready and the Owns column's wording extended to name the WP-ERROR-014 boundary explicitly, in the same body of work as the promotion, per SF-SPEC-013 Section 5.7. No further boundary content changed. | Frozen |
