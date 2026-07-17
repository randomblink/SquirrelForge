# WP-ERROR-023 — WordPress REST API Response Error

---

# 1. Knowledge Entry

WordPress REST API Response Error

---

# 2. Metadata

* **Error ID:** `WP-ERROR-023`
* **Title:** WordPress REST API Response Error
* **Category:** REST API
* **Severity:** Critical
* **Recovery Priority:** Immediate
* **Status:** Production Ready
* **Version:** 1.2

---

# 3. Summary

A WordPress REST API request has been fully accepted — its route resolved (`WP-ERROR-021`) and its authentication, authorization, and arguments all validated (`WP-ERROR-022`) — and the route's own callback begins executing its business logic, but the resulting response is not a valid, successful result: the callback returns a `WP_Error`, an uncaught exception or PHP fatal error occurs during its execution, it returns a value that cannot be turned into a valid REST response, or PHP output emitted while the callback runs corrupts an otherwise-successful REST body.

---

# 4. Primary Failure Mode

The request has cleared every gate `WP-ERROR-021` and `WP-ERROR-022` own, and the route's own callback begins running its actual business logic. This entry covers four distinct ways that execution or response generation can fail to produce a valid, successful response:

- **The callback's own logic returns a `WP_Error`** — a deliberate, well-behaved error report for a condition the callback itself anticipated and detected. `WP_REST_Server` converts this into a structured JSON error response carrying that `WP_Error`'s own code, message, and data — REST's own designed error-reporting mechanism.
- **The callback throws an uncaught exception, or a PHP fatal error occurs during its execution** (for example, calling a method on `null`, or exhausting available memory). WordPress's own REST dispatch does not guarantee this is caught and converted into a clean JSON error; depending on the specific WordPress version, PHP configuration, and error-display settings, the actual visible symptom can be a broken or entirely non-JSON response — an HTML error page, a blank response, or PHP's own raw fatal-error output — rather than a well-formed REST error. Since WordPress 5.2, the most common source of that HTML page is WordPress's own built-in fatal-error-protection shutdown handler (introduced alongside Recovery Mode): it catches PHP fatal errors globally via a shutdown function and displays a generic "There has been a critical error on this website" message while emailing the site administrator a Recovery Mode link. Because this handler operates at the PHP shutdown level rather than being aware of the specific request it interrupted, it produces this same generic HTML page for a REST API request just as it would for an ordinary page load, rather than a REST-appropriate JSON error.
- **The callback returns, without error, a value that cannot be encoded as JSON** — for example, a resource, a closure, invalid UTF-8, or circular data. `rest_ensure_response()` wraps the return value in a response object without proving that its data is JSON-serializable. During output, `WP_REST_Server::serve_request()` checks the result of `wp_json_encode()`; on an encoding failure, current Core replaces the failed body with a structured `rest_encode_error` response and HTTP 500. The invalid callback value remains the cause, but a malformed, empty, truncated, or apparent HTTP 200 response is not current Core's standard encoding-failure result.
- **The callback completes and returns serializable data, but emitted PHP output corrupts the REST body** — for example, a warning, notice, deprecated-message display, accidental `echo`, or other output emitted while the callback runs. The REST server can already have declared `application/json` and the callback can otherwise return a normal array or response object, yet the emitted HTML or text precedes or interleaves with the JSON body, making the client-visible response invalid JSON. Whether PHP output is displayed is environment-dependent: the verified PHP built-in-server configuration displayed a controlled warning and returned HTTP 200 with an `application/json` content type but an HTML-prefixed, unparsable body. This is distinct from `rest_encode_error`: the callback's returned value is serializable and Core's JSON encoder did not fail.

This entry owns each of these as the observable, REST-specific manifestation, **regardless of its own underlying root cause**: a PHP fatal error's own root cause (a missing extension, a syntax error) belongs to PHP Runtime; a database query timing out or a table being corrupted mid-callback belongs to Database; a filesystem permission issue encountered while the callback attempts a file operation belongs to Filesystem; and a specific plugin's own coding defect producing any of the above belongs to Plugin. This entry documents only how those failures present through the REST interface once they occur during an accepted request's own execution — never why the underlying failure happened.

---

# 5. Severity

This entry is classified **Critical**, though its actual impact ranges depending on scope:

- Where a widely-used endpoint's callback fails on every invocation (for example, the Block Editor's own post-saving endpoint), the impact can approach a full-site outage for that entire class of functionality.
- Where a narrow, specific endpoint's own callback fails only under a specific, uncommon condition, the impact is typically much narrower — that specific action fails under that specific condition while ordinary REST functionality and browsing continue to work normally.
- This entry remains classified at the level of its most severe possible manifestation, consistent with the range-based Critical classification used elsewhere in this catalog (for example, `WP-ERROR-004`, `005`, `006`, `019`, `020`, `021`, and `022`).

---

# 6. Distinction

This entry applies only when verified evidence establishes that a request was fully accepted and its callback began executing its own business logic, and that the resulting response is not valid or successful — not that no route was ever matched, that the request was rejected before the callback ran, or that a returned result is simply data the caller did not expect (for example, an empty result set from a query that legitimately matched nothing, which is a valid, successful response, not an error).

**Internal distinctions this entry specifically requires:**

- **A deliberate `WP_Error` versus an uncaught exception or fatal error:** both are this entry's condition, but they differ materially in how they present and how they are diagnosed. A `WP_Error` is REST's own designed, well-behaved error path — visible directly in the HTTP response as a structured JSON body with its own code and message. An uncaught exception or fatal error is not guaranteed to produce any structured response at all; the visible symptom can be a broken, non-JSON, or entirely blank response, with the actual diagnostic detail available only in server-side logs, not the HTTP response itself.
- **An invalid/non-serializable return value versus the other two:** this is a distinct response-generation failure — the callback does not throw and does not return a `WP_Error`, but `WP_REST_Server::serve_request()` detects that the resulting data cannot be JSON-encoded and returns `rest_encode_error` with HTTP 500.
- **Response corruption after successful callback execution versus a valid success:** a callback can return serializable data successfully while PHP output emitted during execution corrupts the body before clients receive it. This is this entry's condition because the REST response is not valid, even though the callback's own return value is valid. The underlying warning, notice, deprecated-message display, or accidental output remains a PHP Runtime, Plugin, Theme, or custom-code cause; this entry owns only its REST-specific manifestation.
- **A genuine failure versus a valid but merely-unexpected successful response:** a callback that completes normally and returns a well-formed, successful response — even one containing no results, an empty array, or data the caller did not anticipate — is not this entry's condition. This entry requires an actual failure in the callback's own execution or in producing a valid response, not a caller's own surprise at otherwise-correct data.

**Distinct from the following related entries and categories:**

- **`WP-ERROR-021` — WordPress REST API Route Not Found**: presumes no route was ever matched, so no callback was ever reached. This entry presumes the opposite.
- **`WP-ERROR-022` — WordPress REST API Access Denied**: presumes the request was rejected before the callback's own business logic began running — including, per `SF-TAXONOMY-002` Section 4's explicit decision, a failure of the request's own argument/schema validation. This entry begins exactly where `WP-ERROR-022` ends: only once the callback's own business logic has actually started executing.
- **Database category** (for example, `WP-ERROR-006 — WordPress Database Table Corruption`, `WP-ERROR-009 — Database Query Timeout`): where a callback's own database query times out, or encounters table corruption, mid-execution, the underlying condition belongs to the respective Database entry. This entry owns only the resulting REST-specific response — how that underlying failure presents to a REST client — not the diagnosis or recovery of the database condition itself.
- **PHP Runtime category**: where a callback's own PHP fatal error traces to a missing extension, an unsupported PHP version, or another PHP-runtime-level cause, that cause belongs to the PHP Runtime category. This entry owns only the resulting broken or malformed REST response, not the underlying runtime defect.
- **Filesystem category** (for example, `WP-ERROR-019 — WordPress Filesystem Permission Denied`, `WP-ERROR-020 — WordPress Disk Space Exhausted`): where a callback's own file operation fails due to a permission or capacity condition, that condition belongs to the respective Filesystem entry. This entry owns only the resulting REST-specific manifestation.
- **Plugin category**: where a specific plugin's own coding defect is the reason its own callback throws, fatals, or returns an unserializable value, that defect belongs to the Plugin category. This entry owns the REST response-generation failure, not the underlying code defect that produced it.

---

# 7. Scope

**Covered:** A verified condition in which a REST request has been fully accepted (route resolved, authenticated, authorized, and its arguments validated) and the callback's own business logic began executing, but the result is a `WP_Error`, an uncaught exception or PHP fatal error during execution, a value that cannot be turned into a valid REST response, or a body corrupted by PHP output emitted during otherwise-successful callback execution — regardless of the underlying root cause of that failure.

**Excluded:**

- No route matched at all (see `WP-ERROR-021`).
- A request rejected before the callback's own business logic began running, including an argument/schema validation failure (see `WP-ERROR-022`).
- A callback that completes successfully and returns a valid, well-formed response, even where that response contains no results or data the caller did not anticipate.
- The underlying root cause of a callback's own failure, where that cause is owned by another category (Database, PHP Runtime, Filesystem, or Plugin) — this entry owns only the resulting REST-specific manifestation, not the underlying condition's own diagnosis or recovery.
- Browser-enforced cross-origin (CORS) policy failures, excluded from this category entirely per `SF-TAXONOMY-002` Section 5.

---

# 8. WordPress Components

Listed as components commonly involved, not as a claim that every incident exercises every one of them identically:

- `WP_REST_Server::dispatch()` and its own callback-invocation logic (`wp-includes/class-wp-rest-server.php`), responsible for actually calling the route's registered callback once a request has been accepted, and for converting its return value into an HTTP response.
- `WP_Error` (`wp-includes/class-wp-error.php`), the mechanism a callback uses to report a deliberate, anticipated failure; `WP_REST_Server` recognizes a returned `WP_Error` and converts it into a structured JSON error response.
- `rest_ensure_response()` (`wp-includes/rest-api.php`), which normalizes a callback's return value — passing a `WP_Error` through unchanged, using a `WP_REST_Response`/`WP_HTTP_Response` directly, or wrapping other serializable data (arrays, plain values) into a new `WP_REST_Response` — but which does not itself guarantee the wrapped content is actually JSON-serializable.
- PHP's own exception and fatal-error handling, which governs what happens when a callback's own code throws or triggers a fatal condition; whether this is caught gracefully or surfaces as a broken, non-JSON response depends on the specific WordPress version and the surrounding PHP environment, not a single guaranteed behavior.
- `wp_debug_mode()` (`wp-includes/load.php`) and PHP's `display_errors` setting, which can determine whether warnings, notices, or other PHP output are emitted into a REST response. WordPress's early REST-request display-error check is explicitly optimistic because `REST_REQUEST` is often not defined at that point; exact output behavior must therefore be verified in the relevant server and PHP configuration.
- WordPress's own built-in fatal-error-protection shutdown handler (`wp-includes/class-wp-fatal-error-handler.php`, introduced in WordPress 5.2 alongside Recovery Mode), which catches PHP fatal errors globally and displays a generic "There has been a critical error on this website" HTML message while emailing the site administrator a Recovery Mode link. This handler is not REST-aware and produces the same generic HTML page for a REST request as it would for an ordinary page load.
- `wp_json_encode()` (and the underlying PHP `json_encode()`), whose failure state is checked by `WP_REST_Server::serve_request()`; current Core converts a detected encoding failure into `rest_encode_error` with HTTP 500.
- PHP's own error log (and `WP_DEBUG_LOG` output, where enabled), frequently the only place a fatal error or uncaught exception during a REST callback's own execution is actually recorded, since the HTTP response itself may not carry useful diagnostic detail once execution has failed this severely.
- WP-CLI's `wp eval`, usable to invoke a registered callback function directly with equivalent arguments outside of an actual HTTP request, isolating whether a failure is reproducible independent of the REST transport layer itself.

---

# 9. Typical Symptoms

- A well-formed JSON error response with a specific, custom error code and message — the callback's own deliberate `WP_Error` report — distinct from the generic codes (`rest_forbidden`, `rest_no_route`) WordPress's own infrastructure produces at earlier stages.
- A broken or entirely non-JSON response — an HTML error page, a blank response body, or raw PHP fatal-error output — where an uncaught exception or PHP fatal error occurred during the callback's own execution.
- An HTTP 500 status with little or no useful detail in the response body itself, with the actual cause visible only in the server's own PHP error log.
- A structured JSON response with code `rest_encode_error` and HTTP 500 after the callback returned data that `wp_json_encode()` could not represent.
- An HTTP response declared as JSON but beginning with HTML or plain-text PHP warning, notice, deprecated-message, or accidental output before an otherwise-valid JSON payload, making the complete body unparsable by a REST client.
- The failure reproducing consistently for the same request parameters, or only under specific conditions (a particular record, a particular size of data, a particular concurrent load) that correspond to the underlying root cause (a specific database row's own corruption, a resource limit, a particular file's own permissions).
- The same endpoint succeeding for some requests and failing for others, where the difference correlates with a resource the callback depends on (a specific database row, a specific file, a specific external service) rather than with authentication, authorization, or the request's own well-formedness.

---

# 10. Common Causes

Causes are grouped by category. Inclusion in this list identifies a category as plausible; it does not assert that any specific cause is present without diagnostic confirmation. Each cause below is a REST-layer manifestation; its own underlying diagnosis and recovery belong to the category named.

- A database query issued by the callback timing out, or encountering a corrupted table, mid-execution (Database category — see `WP-ERROR-009`, `WP-ERROR-006`).
- A PHP fatal error during the callback's own execution traceable to a missing extension, an unsupported PHP version, or another PHP-runtime-level condition (PHP Runtime category).
- A filesystem permission or capacity condition encountered while the callback attempts a file read, write, or upload operation (Filesystem category — see `WP-ERROR-019`, `WP-ERROR-020`).
- A specific plugin's own coding defect — an unhandled edge case, an incorrect assumption about input data, or a bug introduced by a recent update — causing the callback to throw, fatal, or return invalid data (Plugin category).
- A callback returning a resource, a closure, or another inherently non-serializable value, rather than an array, object, or dedicated response object WordPress's REST layer can encode.
- A callback constructing a response containing invalid UTF-8 or a circular reference, causing JSON encoding to fail and current Core to return `rest_encode_error` with HTTP 500.
- A callback, plugin, theme, or PHP-runtime configuration causing a warning, notice, deprecated-message, or other output to be displayed while an accepted REST callback executes. The underlying output cause belongs to its originating category; this entry owns only the resulting invalid REST body.
- An external service or API the callback depends on becoming unavailable or returning an unexpected result mid-execution, with the callback itself not handling that condition gracefully.

---

# 11. Diagnosis

Verify the following:

1. Confirm this is genuinely a callback-execution or response-generation failure — a request that was fully accepted (per `WP-ERROR-021`/`WP-ERROR-022`) but produced an invalid, malformed, or unexpectedly erroring response — rather than a route-not-found condition or a rejection before the callback ran.
2. As the broadest, least invasive check, confirm the request actually was accepted: verify the response does not carry a `WP-ERROR-021`-style `rest_no_route` code or a `WP-ERROR-022`-style `rest_forbidden`/`rest_cookie_invalid_nonce`/`rest_invalid_param` code, since a response bearing one of those codes belongs to an earlier stage, not this entry, regardless of any superficial resemblance to an execution failure.
3. Determine whether the response is a callback-supplied `WP_Error`, Core's response-generation `rest_encode_error`, a broken/non-JSON response caused by an uncaught exception or fatal error, or an otherwise-successful JSON payload corrupted by emitted PHP output. These point toward materially different paths: anticipated callback logic, a return value that failed JSON encoding, an uncaught exception/fatal error, or warning/notice/deprecated-message/accidental output respectively.
4. Where a well-formed `WP_Error` response is present, capture its own specific error code, message, and any `data` payload, since a custom endpoint's own `WP_Error` can carry a code specific to that endpoint's own logic, not a standard, WordPress-wide code.
5. Where the response is broken or non-JSON, check the PHP error log (and `WP_DEBUG_LOG` output, where enabled) for a fatal error or uncaught exception coinciding with the exact request's timestamp, since the HTTP response itself may carry no useful diagnostic detail once execution has failed this severely.
6. Determine which underlying category the actual root cause belongs to — Database, PHP Runtime, Filesystem, or Plugin — by examining what the callback was actually doing at the point of failure (a query, a file operation, a call into a missing extension, or a defect specific to one plugin's own code), since this entry's own recovery is limited to the REST-specific manifestation, not the underlying fix, which belongs to the respective category's own diagnosis process.
7. Where the callback completes without throwing or returning a `WP_Error` but the response is `rest_encode_error` HTTP 500, inspect what the callback returned for data WordPress cannot encode as JSON (a resource, a closure, invalid UTF-8, or a circular reference). A malformed or empty response without that code requires separate investigation rather than being attributed to an ordinary Core-detected encoding failure.
8. Where the callback returns serializable data but the complete body is not valid JSON, inspect the raw body prefix and PHP error log for displayed warnings, notices, deprecated messages, or accidental output. Record `WP_DEBUG`, `WP_DEBUG_DISPLAY`, PHP `display_errors`, server/SAPI, and the exact response headers before attributing the result to output contamination; this behavior is configuration-dependent rather than a universal Core response.
9. Reproduce the failure directly — for example, via WP-CLI's `wp eval` invoking the registered callback function outside of an actual HTTP request — to confirm it is reproducible independent of the REST transport layer itself, and to isolate it from any request-specific variable (headers, authentication state) that is not actually relevant to the failure. Since most REST callbacks accept a single `WP_REST_Request` object as their parameter rather than discrete arguments, this requires constructing an equivalent `WP_REST_Request` (with the same route, parameters, and method) to pass to the callback, not simply calling it with loose arguments.
10. Preserve relevant evidence — the exact request, the full (even if malformed) response, PHP error logs, relevant display configuration, and timestamps — before making any change.
11. Where the engineer performing diagnosis does not control the specific plugin or the underlying resource (the database, the filesystem, PHP configuration) the callback depends on, escalate to whoever does, or proceed under the appropriate category's own diagnosis process, rather than attempting an unverified workaround at the REST layer alone.

---

# 12. Recovery Procedure

Recovery shall address the underlying root cause through the category that actually owns it; this entry's own recovery is limited to ensuring the REST layer itself reports the resulting condition cleanly, not to fixing the underlying database, filesystem, PHP runtime, or plugin defect directly.

Permitted recovery categories, depending on the verified cause, include:

- Where the callback's own logic correctly returns a `WP_Error` for a legitimate, anticipated error condition, confirming the `WP_Error`'s own code and message accurately reflect the actual condition, and correcting only the callback's own error-reporting if it is misleading, rather than treating the underlying condition itself as this entry's own responsibility to fix.
- Where an uncaught exception or PHP fatal error traces to a Database, PHP Runtime, Filesystem, or Plugin condition, addressing that underlying condition through its own category's recovery procedure, and separately ensuring the callback itself fails gracefully in the interim — for example, wrapping the specific operation in a try/catch that returns a proper `WP_Error` rather than allowing an uncaught exception to break the JSON response — so REST clients receive an interpretable error while the underlying cause is being addressed.
- Where a non-serializable return value is confirmed, correcting the callback to return a value WordPress's REST layer can actually encode — an array, a `WP_REST_Response`, or another serializable value — rather than a resource, closure, or otherwise unencodable type.
- Where displayed PHP output corrupts an otherwise-valid response, correcting the warning, notice, deprecated call, or accidental output at its source and configuring production PHP error display so diagnostic output is logged rather than emitted into REST bodies. Do not suppress a warning without determining and addressing its cause; the underlying correction belongs to the category that owns that cause.
- Escalating to whoever controls the specific plugin, or to the appropriate specialist for the underlying category (a database administrator, a hosting provider, and so on), where the engineer performing recovery does not have the access or expertise the actual root cause requires.

Recovery shall not wrap every possible failure in a generic try/catch that silently swallows exceptions as an adequate fix; a caught exception shall still be surfaced to the REST client as a clear, accurate `WP_Error`, not suppressed into an artificially successful-looking response.

---

# 13. Validation

Recovery is successful when:

- The previously failing request now returns a valid, well-formed REST response — either a genuine success or a clean callback-supplied error with an accurate, specific code and message — and no `rest_encode_error`, broken, or non-JSON response recurs.
- Where the underlying root cause was owned by another category (Database, PHP Runtime, Filesystem, or Plugin), that category's own validation criteria are independently confirmed satisfied, in addition to confirming the REST-layer response itself is now well-formed.
- No equivalent uncaught exception, PHP fatal error, `rest_encode_error`, displayed-PHP-output corruption, or other malformed response recurs across repeated, fresh requests to the same and related endpoints.
- The response's own error code and message, where applicable, accurately and specifically describe the actual condition, rather than a generic or misleading one.

---

# 14. Prevention

- Encourage REST endpoint callbacks to return `WP_Error` for every anticipated failure condition rather than allowing an exception to propagate uncaught, and to wrap calls into code that can throw (a database operation, a third-party library, an external service call) in a try/catch that converts the result into a proper `WP_Error`.
- Validate that a callback's own return value is always a type WordPress's REST layer can encode, particularly when returning data assembled from external sources or complex objects.
- In non-production testing, treat displayed PHP warnings, notices, and deprecated messages during REST requests as response-integrity failures even when the callback returns success; in production, log diagnostic output rather than displaying it to clients while still correcting its underlying cause.
- Include realistic failure-condition testing (a temporarily unavailable database, a missing file, an external service returning an error) as part of routine testing for custom endpoints, not only well-formed, successful requests.
- Monitor PHP error logs specifically for fatal errors and uncaught exceptions occurring during REST requests, since a broken REST response may not itself surface a useful error to the calling client or a typical site monitor.
- Document, for each custom endpoint, which underlying resources (database tables, filesystem paths, external services) its own callback depends on, so a future investigation can quickly identify which category's own diagnosis process actually applies.

---

# 15. Security Considerations

- Do not enable full PHP error display (stack traces, file paths) in a production REST response, since a broken or verbose error response can reveal internal implementation details to an unauthenticated or minimally-privileged caller.
- Ensure a caught exception's own message, when surfaced in a `WP_Error`, does not itself leak sensitive detail (a database connection string, an internal file path, or another secret) that a well-behaved `WP_Error` would not otherwise expose.
- Treat a sudden, unexplained pattern of callback-execution failures as a potential signal of an attempted exploit — a request specifically crafted to trigger an unhandled condition — rather than assuming it is always routine instability, particularly where no legitimate change explains it.
- Coordinate any change to a shared underlying resource (the database, the filesystem, PHP configuration) through that resource's own category-appropriate, auditable process; this entry's own recovery does not extend to that resource directly.

---

# 16. Related Errors

The following are cited as they exist in this repository, or as conceptual distinctions where noted.

1. [WP-ERROR-006 — WordPress Database Table Corruption](WP-ERROR-006-DATABASE-TABLE-CORRUPTION.md) — exists in this repository (Database category); see Section 6 (Distinction) above.
2. [WP-ERROR-009 — Database Query Timeout](WP-ERROR-009-DATABASE-QUERY-TIMEOUT.md) — exists in this repository (Database category); see Section 6 (Distinction) above.
3. [WP-ERROR-019 — WordPress Filesystem Permission Denied](WP-ERROR-019-FILESYSTEM-PERMISSION-DENIED.md) — exists in this repository (Filesystem category); see Section 6 (Distinction) above.
4. [WP-ERROR-020 — WordPress Disk Space Exhausted](WP-ERROR-020-DISK-SPACE-EXHAUSTED.md) — exists in this repository (Filesystem category); see Section 6 (Distinction) above.
5. [WP-ERROR-021 — WordPress REST API Route Not Found](WP-ERROR-021-REST-API-ROUTE-NOT-FOUND.md) — exists in this repository; see Section 6 (Distinction) above.
6. [WP-ERROR-022 — WordPress REST API Access Denied](WP-ERROR-022-REST-API-ACCESS-DENIED.md) — exists in this repository; see Section 6 (Distinction) above.

---

# 17. Notes

This entry documents the third and final entry `SF-TAXONOMY-002` declares for the REST API category, owning the callback-execution and response-generation stage of the REST request lifecycle. With this entry's creation, the REST API category's planned baseline is complete. It does not restate `WP-ERROR-021`'s or `WP-ERROR-022`'s own boundaries. Consistent with the single-responsibility principle in **SF-SPEC-001** Section 4.3, this entry documents the response-failure classes an accepted request can produce: a deliberate `WP_Error`, an uncaught exception/fatal error, a non-serializable return value, or body corruption caused by PHP output emitted during otherwise-successful callback execution. It explicitly and consistently excludes every underlying root cause — Database, PHP Runtime, Filesystem, Plugin, Theme, or custom code — each of which retains its own separate ownership regardless of how the failure happens to present through the REST interface.

This entry's governing direction was `SF-TAXONOMY-002` Version 1.2, whose own boundary for this entry — everything from the point the callback's own business logic begins running — is applied here without narrowing or widening it, and whose explicit warning against this entry becoming a "catch-all" for any failure that happens to occur during a REST request is honored throughout Sections 6, 7, and 10 by consistently attributing each cause to its owning category rather than absorbing it. The Diagnosis section applies the broad-before-narrow ordering established across this category by `SF-REVIEW-047` and reinforced by `SF-REVIEW-049` from its own first draft, rather than requiring an independent review to add it. The specific technical grounding includes `WP_Error`'s role in `WP_REST_Server`, `rest_ensure_response()`'s normalization limits, `WP_REST_Server::serve_request()`'s `rest_encode_error` HTTP 500 fallback after JSON encoding fails, and the documented gap between returning `WP_Error` and unreliable handling of an uncaught exception or fatal error.

This entry underwent the review sequence required by **SF-SPEC-001** Section 19, **SF-SPEC-005** Section 5.6, and **SF-SPEC-012**: an author (Class A) review at `docs/reviews/SF-REVIEW-050-WP-ERROR-023-AUTHOR-REVIEW.md`, which found no defects, followed by an independent (Class B) review at `docs/reviews/SF-REVIEW-051-WP-ERROR-023-INDEPENDENT-REVIEW.md`, which reached outcome **Approved with Minor Revisions** — naming WordPress's own fatal-error-protection shutdown handler explicitly and correcting the `wp eval` reproduction step's precision — and satisfied the Production Ready gate per SF-SPEC-012 Section 12. Its Status was changed to Production Ready on that basis. This document does not itself constitute either review record; see the cited files for full findings, corrections, and gate decisions. With this entry's promotion, the REST API category's three-entry planned baseline (`WP-ERROR-021`, `022`, `023`) is complete.

The independent review did not designate this entry as a Reference Implementation. That designation, governed separately by **SF-SPEC-001** Section 22, has not been sought or asserted here.

No Reference Implementation is currently designated by **SF-SPEC-001**; this entry's relationship to that designation, and to any future `WP-SCENARIO-XXX` runtime evidence, is not asserted here and shall not be assumed until such evidence or designation actually exists.

---

## Revision History

| Version | Date | Summary | Approval Status |
|---|---|---|---|
| 1.0 | 2026-07-14 | Initial Production Ready entry. | Approved via SF-REVIEW-050/051 |
| 1.1 | 2026-07-17 | Post-certification correction prompted by the WP-VERIFICATION-009 source gate. Corrected the response-generation path for non-serializable callback data: current Core returns structured `rest_encode_error` with HTTP 500 rather than normally leaving a malformed, empty, truncated, or apparent HTTP 200 response. Ownership and underlying-cause boundaries are unchanged. | Reviewed via SF-REVIEW-195/196; REST API re-certified via SF-REVIEW-197/198 |
| 1.2 | 2026-07-17 | Post-certification correction prompted by WP-VERIFICATION-009 runtime evidence. Added the configuration-dependent response-corruption path in which PHP output emitted during otherwise-successful callback execution makes the REST body invalid JSON. Taxonomy ownership and underlying-cause boundaries are unchanged. | Reviewed via SF-REVIEW-204/205; REST API re-certified via SF-REVIEW-206/207 |
