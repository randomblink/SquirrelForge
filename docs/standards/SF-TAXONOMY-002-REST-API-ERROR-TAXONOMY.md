# SF-TAXONOMY-002 — REST API Error Taxonomy

## Document Information

**Document ID:** SF-TAXONOMY-002

**Title:** REST API Error Taxonomy

**Classification:** Engineering Taxonomy — a lightweight, informally-adopted planning artifact, governed by **SF-SPEC-013 — Knowledge Category Lifecycle Specification** for its role in the category lifecycle, but not itself templated by any `SF-TEMPLATE-XXX` document (none currently exists for this artifact type; see **SF-SPEC-013** Section 2.2). It is not governed by **SF-SPEC-005**'s review process as a knowledge artifact and does not itself require an author/independent review pair to exist, though an independent review of it is planned as a matter of this project's own established practice, not as a normative requirement this document imposes on itself.

**Status:** Frozen — the entry set in Section 3 is fixed until this document is deliberately revised (see Section 6). "Frozen" here is an informal, self-defined term describing this document's own adopted-plan state; it is not a claim of the `Version Frozen` WP-ERROR lifecycle stage defined by **SF-SPEC-001** Section 18, nor of any status in the closed list **SF-SPEC-008** Section 6 defines for versioned engineering artifacts, nor of the category-level `Baseline Certified` designation **SF-SPEC-013** Section 5.5 defines. This document carries a `Version` and `Revision History` for traceability only; it does not present itself as a "versioned engineering artifact" within **SF-SPEC-008**'s own scope (Section 2.1), in the same way `FRAMEWORK-OBSERVATIONS.md` explicitly disclaims being versioned.

**Version:** 1.1

**Owner:** SquirrelForge

---

## 1. Purpose

Declare the REST API category's boundary and complete planned `WP-ERROR` entry set *before* any entry in it is authored, per **SF-SPEC-013** Section 5.1's Category Entry Criteria — the first category in this repository for which that requirement is a normative obligation rather than an informally adopted practice.

This document does not itself contain any `WP-ERROR` knowledge content and is not cited as a `WP-ERROR` entry.

---

## 2. Category Boundary

**REST API** owns failures in WordPress's built-in REST API (`wp-json`) request-handling pipeline, following that pipeline's own natural lifecycle: route resolution, authentication and authorization, and callback execution/response generation.

**Explicitly not owned by REST API:**

- The underlying root cause of a callback failure, where that cause is itself owned by another category — a database query timeout or corruption (Database category), a missing PHP extension (PHP Runtime category), or a specific plugin's own implementation defect (Plugin category). This category owns only the observable, REST-specific manifestation and response-wrapping behavior that results, not the underlying cause.
- Generic `wp-admin` cookie or session authentication unrelated to a REST request specifically — Authentication category (once a taxonomy exists for it).
- A specific third-party REST authentication plugin's own internal implementation defect (a JWT validation bug, an OAuth token-handling error) — Plugin category. This category owns the observable condition "the REST request was denied," not defects in a specific plugin's own mechanism for reaching that decision.
- Browser-enforced cross-origin (CORS) policy failures. The WordPress REST request itself may have completed successfully — route resolved, authentication and authorization succeeded, the callback executed, and a response was returned — with the browser, not WordPress, subsequently refusing to expose that response to the calling script due to a missing or incorrect `Access-Control-Allow-Origin` header. Since the REST pipeline itself did not fail in this case, this condition does not belong to any entry this taxonomy declares; see Section 5 for the full reasoning.
- General web-server routing or rewrite misconfiguration not specific to the REST API's own dependency on permalink structure. The REST-specific consequence of a broken or plain permalink structure — where `/wp-json/` itself fails to resolve while the `?rest_route=` query-string fallback may still function — is owned here (see `WP-ERROR-021`); a web server misconfiguration with no particular bearing on REST routing is not.
- A general WordPress bootstrap failure (a PHP fatal error, a missing extension, or a corrupted core file preventing WordPress from initializing at all) that happens to also prevent REST routing from ever running, as a downstream consequence — Bootstrap, PHP Runtime, or Filesystem category, as applicable. This category presumes WordPress itself bootstraps successfully; it owns only a failure within the REST request-handling pipeline specifically, not every possible reason a request to `/wp-json/` might not be served.
- A route's own registration code failing to run because the file defining it cannot be read at all (for example, a permission-denied condition on a plugin file responsible for calling `register_rest_route()`) — Filesystem category. This category's ownership of a route "never registered" (`WP-ERROR-021`) presumes the registration code itself would execute correctly if reached; it does not extend to a filesystem-level reason that code never loaded in the first place.
- A request blocked before it ever reaches WordPress at all — a web application firewall, a security plugin, or a hosting-level rule rejecting requests to `/wp-json/` outright — Security category (once a taxonomy exists for it). This can present identically to `WP-ERROR-021`'s own symptoms (a 403 or 404 for a REST request) but is a categorically different condition: WordPress's own routing logic is never reached at all, as opposed to being reached and failing to resolve the route.

---

## 3. Planned Entries

| Entry | Title | Owns | Status |
|---|---|---|---|
| `WP-ERROR-021` | WordPress REST API Route Not Found | Route resolution — the request fails *before* a callback is selected for execution: the route was never registered, the namespace or path is incorrect, the REST API itself is disabled or filtered off, or rewrite/permalink configuration prevents normal `/wp-json/` resolution (while the `?rest_route=` fallback may still succeed) | Planned |
| `WP-ERROR-022` | WordPress REST API Access Denied | Authentication and authorization — a callback *has* been identified for the route, but the request is rejected before that callback executes, because identity verification (cookie/nonce, Application Passwords, or another authentication mechanism) fails, or the route's own `permission_callback` denies the request — regardless of which specific mechanism WordPress used to reach either outcome | Planned |
| `WP-ERROR-023` | WordPress REST API Response Error | Callback execution and response generation — the callback *has* been selected and execution has begun; every failure from that point forward (an exception or fatal error during execution, an invalid or non-serializable return value, a schema validation failure, or a `WP_Error` generated by the callback itself) belongs here, regardless of that failure's own underlying root cause | Planned |

Nothing else is currently planned for this category. Any future addition to this table is a revision to this document, not an ad hoc decision made while authoring an unrelated entry, per **SF-SPEC-013** Section 5.7.

---

## 4. Ownership Progression

The three entries form a deterministic progression through the REST request lifecycle, and each owns exactly one stage:

```
Incoming REST request
        │
        ▼
  021 — Route resolution
  (can the request reach a callback at all?)
        │
        ▼
  022 — Authentication / authorization
  (is the caller permitted to invoke it?)
        │
        ▼
  023 — Callback execution / response generation
  (did it execute and respond correctly?)
```

A given request fails at exactly one of these three stages, for exactly one reason, at a time. The three entries are therefore mutually exclusive by construction — a consequence of the pipeline's own sequential structure — not merely by convention or by careful wording after the fact.

---

## 5. Candidates Considered and Rejected

Two candidates were proposed and deliberately excluded from Section 3:

- **CORS (Cross-Origin) failures:** Not folded into `WP-ERROR-021` or given its own entry. A browser-side CORS failure is not a route-resolution failure, and is often not a WordPress-side failure of any kind: the route can exist, authentication and authorization can succeed, and the callback can execute and return a valid response — the WordPress REST pipeline completes successfully in the ordinary sense. Only afterward does the browser itself refuse to expose that response to the calling script, enforcing a policy that is entirely its own, based on response headers WordPress did not send. Because the pipeline this taxonomy models did not fail, this condition does not fit any of the three stage-owned entries, and is excluded from this category entirely rather than mischaracterized as a routing problem. It may become its own entry within a future networking or HTTP-layer category, should one be deliberately created; it is not deferred to `WP-ERROR-021` as a "cause" the way some rejected candidates were folded into an existing entry for the Filesystem category, because it does not share that entry's own failure condition at all.
- **Third-party REST authentication plugin defects:** Not a cohesive condition of this category. `WP-ERROR-022` owns the observable condition "the REST request was denied," regardless of which authentication mechanism was in use to reach that decision. It does not own a specific plugin's own defective implementation of that mechanism (for example, a JWT plugin incorrectly validating a malformed token) — that is the plugin's own defect, belonging to the Plugin category, not a condition of WordPress's REST API request-handling pipeline itself.

---

## 6. Revision History

| Version | Date | Summary of Changes | Status |
|---|---|---|---|
| 1.0 | 2026-07-14 | Initial taxonomy: WP-ERROR-021 (Route Not Found), WP-ERROR-022 (Access Denied), WP-ERROR-023 (Response Error), forming a deterministic three-stage progression through the REST request lifecycle. CORS and third-party authentication-plugin defects considered and rejected as separate entries, per Section 5. This is the first taxonomy document produced under SF-SPEC-013's now-normative Section 5.1 requirement, rather than the informally-adopted practice that produced SF-TAXONOMY-001. | Frozen |
| 1.1 | 2026-07-14 | Corrected per independent review (`SF-REVIEW-045`): added three missing Category Boundary exclusions (a general Bootstrap/PHP-Runtime/Filesystem-caused bootstrap failure that incidentally prevents REST routing from running at all; a route's own registration code never loading due to a Filesystem permission failure; a request blocked before reaching WordPress at all by a WAF, security plugin, or hosting rule, presenting identically to WP-ERROR-021's own symptoms but categorically distinct). No entry boundary or rejected-candidate reasoning changed. | Frozen |
