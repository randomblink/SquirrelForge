# WP-ERROR-021 — WordPress REST API Route Not Found

---

# 1. Knowledge Entry

WordPress REST API Route Not Found

---

# 2. Metadata

* **Error ID:** `WP-ERROR-021`
* **Title:** WordPress REST API Route Not Found
* **Category:** REST API
* **Severity:** Critical
* **Recovery Priority:** Immediate
* **Status:** Production Ready
* **Version:** 1.1

---

# 3. Summary

A request targeting a WordPress REST API URL — whether the pretty `/wp-json/...` form or the `?rest_route=...` query-string form — fails to match any currently registered route, so no callback is ever selected for execution. WordPress surfaces this specific condition through its own `rest_no_route` error code, returned with HTTP status 404.

---

# 4. Primary Failure Mode

`WP_REST_Server::dispatch()` attempts to match an incoming REST request's path and HTTP method against the full table of routes currently registered via `register_rest_route()` (normally called during the `rest_api_init` action) and finds no matching handler. WordPress returns `rest_no_route` with HTTP status 404; no callback is selected, so the request fails before the request-acceptance stage `WP-ERROR-022` owns and before the callback-execution stage `WP-ERROR-023` owns.

A request that never reaches WordPress's REST dispatcher is not this condition. A missing or unapplied rewrite rule, web-server interception, WAF rule, or other pre-dispatch failure may make a pretty `/wp-json/...` URL unavailable, but it cannot itself produce WordPress's `rest_no_route` result because route/method matching never occurred. Diagnosis uses the query-string form to distinguish that separate reachability failure from this entry's route-table failure.

This entry covers two distinct ways a route can end up unmatched, both producing the identical `rest_no_route` response:

- **The route was never registered at all** — the plugin, theme, or core code responsible for calling `register_rest_route()` for that specific path never ran, or never ran successfully.
- **The route was registered but then removed from the table before matching occurred** — most commonly via the `rest_endpoints` filter, which runs after routes are registered and can add, modify, or `unset()` entries from the table WordPress actually matches against.

Both are distinct from a request that *does* match a still-present route and is only then rejected — for example, by a filter such as `rest_authentication_errors` or a short-circuit on `rest_pre_dispatch` that intercepts an already-identified route before its callback runs. That condition presumes the route itself remains in the table WordPress would otherwise match against; it is `WP-ERROR-022`'s condition, not this entry's, even though both are frequently described informally as "the REST API is disabled." Diagnosis (Section 11) distinguishes the two directly, since colloquial descriptions of "disabling the REST API" span both mechanisms without distinguishing them.

---

# 5. Severity

This entry is classified **Critical**, though its actual impact ranges depending on scope:

- Where every expected endpoint is absent from the route table (for example, through a wholesale `rest_endpoints` removal), the impact can be a full-site outage for any feature depending on it — most notably the Block Editor, which cannot load or save content without a working REST API, and any headless or decoupled front end for which the REST API *is* the entire site. A site-wide rewrite failure can cause similarly broad impact, but it is a distinct pre-dispatch reachability condition rather than this entry's `rest_no_route` mechanism.
- Where only a single, specific custom endpoint is affected (for example, one plugin's own registration failing), the impact is typically narrower — that specific feature fails while WordPress's own built-in endpoints and ordinary browsing continue to work normally.
- This entry remains classified at the level of its most severe possible manifestation, consistent with the range-based Critical classification used elsewhere in this catalog (for example, `WP-ERROR-004`, `005`, `006`, `019`, and `020`).

---

# 6. Distinction

This entry applies only when verified evidence establishes that a request targeting a REST API URL failed to match any currently registered route — not that a route was matched and then rejected, that a matched route's callback failed during execution, or that the request never reached WordPress's own routing logic at all.

**Internal distinctions this entry specifically requires:**

- **Genuine route removal versus post-match interception:** a `rest_endpoints`-filter removal (or an equivalent mechanism preventing a route from ever entering the match table) is this entry's condition. A `rest_authentication_errors` denial, or a `rest_pre_dispatch`/`rest_request_before_callbacks` short-circuit that intercepts a request *after* a route has already been identified, is not — the route itself remains registered and reachable; only the request directed at it is being rejected, which is `WP-ERROR-022`'s condition. Both are commonly described as "the REST API is disabled," but only the former genuinely produces `rest_no_route`.
- **REST-specific 404 versus WordPress's own generic 404:** WordPress's ordinary "post/page not found" 404 handling (`WP_Query`'s own template-resolution logic for a normal front-end URL) is an entirely separate code path from `WP_REST_Server`'s own `rest_no_route` response. A REST request and an ordinary content request can both return HTTP 404, but only the REST response carrying `rest_no_route` is this entry's condition; the status code alone is insufficient.

**Distinct from the following related entries and categories:**

- **`WP-ERROR-022` — WordPress REST API Access Denied**: presumes a route was matched and a callback identified, with the request then rejected before that callback's own business logic runs. This entry presumes no route was ever matched at all.
- **`WP-ERROR-023` — WordPress REST API Response Error**: presumes a matched route's callback began executing and failed during or after that execution. This entry never reaches that point.
- **Bootstrap, PHP Runtime, or Filesystem categories:** a general WordPress bootstrap failure, a missing PHP extension, or a filesystem permission condition preventing a route's own registration code from ever loading are the respective other category's condition, per `SF-TAXONOMY-002` Section 2. This entry presumes WordPress itself bootstraps successfully and the REST infrastructure has the opportunity to run; it owns only the resulting `rest_no_route` condition within that pipeline, not every possible upstream reason a request to `/wp-json/` might not be served.
- **Security category** (once a taxonomy exists for it): a request blocked before it ever reaches WordPress at all — a web application firewall, a security plugin operating at the network/server layer, or a hosting-level rule — can present identically to this entry's own symptoms (a 403 or 404 for a REST request) but is a categorically different condition, per `SF-TAXONOMY-002` Section 2: WordPress's own routing logic is never reached at all, as opposed to being reached and failing to resolve the route.

---

# 7. Scope

**Covered:** A verified condition in which a request targeting a WordPress REST API URL — in either the pretty (`/wp-json/...`) or query-string (`?rest_route=...`) form — fails to match any currently registered route before a callback is selected, whether because the route was never registered or because it was removed from the match table (for example, via the `rest_endpoints` filter) before the request was processed.

**Excluded:**

- A matched route whose request is subsequently rejected before its callback runs (see [WP-ERROR-022](WP-ERROR-022-REST-API-ACCESS-DENIED.md)).
- A matched route whose callback began executing and then failed (see [WP-ERROR-023](WP-ERROR-023-REST-API-RESPONSE-ERROR.md)).
- WordPress's own generic, non-REST 404 handling for ordinary content URLs.
- A general WordPress bootstrap failure, missing PHP extension, or filesystem permission condition preventing route-registration code from ever running (Bootstrap, PHP Runtime, or Filesystem category, as applicable).
- A request blocked before reaching WordPress at all by a web application firewall, security plugin, or hosting-level rule (Security category, once a taxonomy exists for it).
- A pretty `/wp-json/...` request that fails because rewrite/permalink or web-server routing never populated `rest_route` and therefore never invoked WordPress's REST dispatcher; successful `?rest_route=...` access distinguishes this pre-dispatch reachability condition from a missing route.
- Browser-enforced cross-origin (CORS) policy failures, which presume the WordPress REST pipeline itself already completed successfully (excluded from this category entirely; see [WP-ERROR-030 — WordPress CORS (Cross-Origin) Policy Failure](WP-ERROR-030-CORS-CROSS-ORIGIN-POLICY-FAILURE.md), which resolves the forward-reference `SF-TAXONOMY-002` Section 5 originally made).

---

# 8. WordPress Components

Listed as components commonly involved, not as a claim that every incident exercises every one of them identically:

- `register_rest_route()` (`wp-includes/rest-api.php`) and the `rest_api_init` action, where routes are normally registered.
- `WP_REST_Server::dispatch()` and `WP_REST_Server::get_routes()` (`wp-includes/class-wp-rest-server.php`), responsible for matching an incoming request's path and method against the registered route table and producing the `rest_no_route` error, HTTP status 404, when no match exists.
- `WP_REST_Server::get_index()`, which handles the bare API root (`GET /wp-json/`) independently of a specific namespace and returns an index of registered namespaces and routes. Its success is the most fundamental signal that the request reached REST serving; it is a diagnostic control, not itself evidence that a particular route matched.
- The `rest_endpoints` filter, which can add, modify, or `unset()` entries in the route table after registration but before matching, and is the mechanism by which a route can be genuinely removed from what `dispatch()` will ever match.
- The `rest_route` public query variable and the `index.php?rest_route=` request form, WordPress's rewrite-independent path into `WP_REST_Server`, available regardless of the site's permalink structure.
- The permalink/rewrite-rule infrastructure (`WP_Rewrite`; Settings → Permalinks), used diagnostically to determine whether the pretty `/wp-json/` request reached REST routing at all. A Plain structure or missing/unapplied rewrite can prevent that URL form from populating `rest_route`; the query-string form remains the rewrite-independent control. Such a pre-dispatch failure is not itself `rest_no_route`.
- The REST API discovery signal WordPress adds to front-end output — the `Link` HTTP response header and the `<link rel="https://api.w.org/">` HTML tag — pointing to the site's own current REST API root (`/wp-json/` or `?rest_route=/`, depending on permalink structure), useful as a diagnostic signal of which URL form the site itself currently expects.
- WP-CLI's core `wp rewrite structure` and `wp rewrite flush` commands, for permalink/rewrite diagnostics. Listing registered REST routes is not a core WP-CLI capability; it requires the optional `wp-cli/restful` package, or direct inspection via `wp eval` calling `rest_get_server()->get_routes()`.

---

# 9. Typical Symptoms

- An HTTP 404 response with a JSON body identifying the `rest_no_route` error code and the message "No route was found matching the URL and request method."
- The Block Editor (Gutenberg) failing to load content, failing to save, or showing an "Updating failed" notice, since it depends heavily on the REST API for its own normal operation.
- A headless or decoupled front end (a separate application consuming the REST API) failing to load any content at all, where the REST API constitutes the entire site's own data source.
- The site's own REST API discovery signal (`Link` header or `<link rel="https://api.w.org/">`) pointing to a URL form inconsistent with the one the calling application is actually requesting.
- The caller's failure appearing immediately after a permalink structure change, a plugin deactivation or failed update, or a migration to a new environment. A permalink-correlated pretty-URL failure requires the query-string control before it can be classified as this entry.
- A specific custom endpoint returning `rest_no_route` while WordPress's own built-in endpoints (for example, `/wp/v2/posts`) continue to function normally — indicating the cause is specific to the plugin or code responsible for the one missing route, not the REST API infrastructure as a whole.
- Conversely, requests that do reach the REST dispatcher but return `rest_no_route` for every expected endpoint — including WordPress's own built-in ones — indicating wholesale route-table removal or registration failure rather than one specific plugin. A generic 404 for the pretty URL form is not equivalent and shall first be tested through `?rest_route=`.

---

# 10. Common Causes

Causes are grouped by category. Inclusion in this list identifies a category as plausible; it does not assert that any specific cause is present without diagnostic confirmation.

- A plugin or theme responsible for a specific route failing to run its own `rest_api_init` callback — because it is inactive, was recently deactivated, or encountered a PHP error during its own registration logic that prevented `register_rest_route()` from ever executing for that route.
- A `rest_endpoints` filter (in a security plugin, a custom must-use plugin, or theme code) removing one or more routes from the registration table, whether intentionally (to reduce attack surface) or as a side effect of unrelated code.
- A typo, or a version mismatch, in the requested namespace or path (for example, requesting `/wp/v3/posts` when only `/wp/v2/posts` is registered, or a plugin's own endpoint namespace changing between versions without the calling application being updated to match).
- A caching layer or CDN continuing to serve a stale, previously-cached 404 response for a REST URL after the underlying cause has already been corrected.
- A migration or environment change that changed the site's base URL, route-providing plugin state, or namespace/version without updating a headless front end's configured request path to match.

A Plain permalink structure or corrupted, unflushed, or unapplied rewrite rule may prevent the pretty `/wp-json/` URL from reaching REST dispatch while `?rest_route=` continues to work. That is an important diagnostic distinction and can explain the caller's symptom, but it is not a cause of `rest_no_route`; it is outside this entry's owned failure mechanism.

---

# 11. Diagnosis

Verify the following:

1. Confirm this is genuinely a route-not-found condition — an HTTP 404 response carrying the `rest_no_route` error code and message — rather than a later-stage denial (`WP-ERROR-022`), a callback execution failure (`WP-ERROR-023`), or WordPress's own unrelated, generic 404 for ordinary content.
2. Capture the exact request URL, HTTP method, and the full JSON error response, since a plain HTTP 404 without the `rest_no_route` code may indicate the request never reached WordPress's own REST routing at all (a web-server- or security-layer block, outside this entry's scope).
3. Check whether the bare API root itself (`GET /wp-json/`, or `?rest_route=/` under a Plain permalink structure) resolves at all, as the most fundamental, least invasive check available: the REST server handles the index independently of any specific namespace, and its response lists registered namespaces. If the query-string root succeeds, its listed `namespaces` array indicates whether the specific namespace of interest is registered. Failure of the pretty root alone does not establish this entry.
4. Test the identical request using the `?rest_route=` query-string form in place of the pretty `/wp-json/...` form (for example, `example.com/?rest_route=/wp/v2/posts`). Success there while the pretty form fails proves the route exists and rules out this entry; investigate permalink/rewrite or web-server routing instead.
5. If Step 4 isolates a pretty-URL reachability failure, confirm the configured permalink structure (`wp option get permalink_structure`) and inspect or flush rewrite rules as a separate diagnosis. Do not classify its generic 404 as `rest_no_route`.
6. Test whether WordPress's own built-in REST endpoints (for example, `/wp-json/wp/v2/types`) succeed while only a specific custom endpoint fails, to isolate whether the cause is REST API infrastructure-wide or specific to the plugin or theme responsible for the one failing route.
7. Where a specific plugin's own endpoint is suspected, confirm the plugin is active and did not encounter a PHP error during its own `rest_api_init` callback, by checking PHP error logs around the time of the failing request, since a failed registration callback can silently leave a route missing from the table without producing a visible fatal error elsewhere.
8. Where a security plugin, firewall, or custom code is present, check for a `rest_endpoints` filter or an equivalent route-removal mechanism, by temporarily deactivating the suspected plugin and retesting, rather than assuming its presence or absence without confirmation.
9. Where WP-CLI is available, inspect the actual registered route table directly (for example, via `wp eval 'echo json_encode( array_keys( rest_get_server()->get_routes() ) ) ;'`), rather than assuming which routes are registered from documentation or source code alone.
10. Confirm the site's own REST API discovery signal (the `Link` HTTP response header, or the `<link rel="https://api.w.org/">` tag on a front-end page) to determine which URL form WordPress itself currently expects, particularly where a headless front end's own configured API base URL is suspected of being stale.
11. Rule out a caching layer or CDN serving a stale 404 response, by testing directly against the origin server or with cache-busting parameters.
12. Preserve relevant evidence — the exact request, the full response, and timestamps — before making any change.
13. Where the engineer performing diagnosis does not control the specific plugin, theme, or infrastructure responsible for the missing route, escalate to whoever does rather than attempting an unverified workaround.

---

# 12. Recovery Procedure

Recovery shall target the verified cause of the missing route. The `?rest_route=` form is a diagnostic control, not a permanent substitute or evidence that a route is missing.

Permitted recovery categories, depending on the verified cause, include:

- Where a specific plugin or theme's own registration code failed to run, reactivating it through WordPress's normal administrative flow, or correcting the PHP error preventing its `rest_api_init` callback from completing, rather than manually re-registering its routes elsewhere without understanding why the original registration failed.
- Where a `rest_endpoints` filter or equivalent mechanism is confirmed to be removing the route, correcting or removing that filter, in coordination with whoever added it where its original intent is unclear, rather than assuming its removal was accidental without confirming.
- Where a namespace or path mismatch between the caller and the currently registered route is confirmed, correcting the calling application's request to match the currently registered route, or restoring the expected route if a recent change unintentionally removed or renamed it.
- Where a caching layer or CDN is serving a stale 404, purging the relevant cache, rather than only fixing the underlying cause and assuming callers will immediately see the corrected response.
- Escalating to whoever controls the specific plugin, theme, or infrastructure responsible, where the engineer performing recovery does not have that access.

Where the query-string control proves the route exists and only the pretty URL fails, leave this entry's recovery path and correct the separately diagnosed permalink/rewrite or web-server reachability condition—for example, by selecting the intended permalink structure and safely regenerating the applicable rules.

Recovery shall not disable security plugins or firewall rules wholesale as a diagnostic shortcut in a production environment; isolate and correct the specific rule or filter responsible instead.

---

# 13. Validation

Recovery is successful when:

- The previously failing request now returns a successful response from the expected route, confirmed by reproducing the exact request that previously failed.
- The query-string (`?rest_route=...`) form reaches the expected route; the pretty (`/wp-json/...`) form also succeeds where the site's permalink and server configuration are intended to support it. A remaining pretty-only failure is recorded as a separate reachability defect, not a recurring `rest_no_route` result.
- WordPress's own built-in REST endpoints continue to function normally, confirming no unrelated route was affected by the fix.
- No equivalent `rest_no_route` error recurs across repeated, fresh requests to the same and related endpoints.
- Where a caching layer was involved, the corrected response is confirmed to actually reach the caller, not only the origin server.

---

# 14. Prevention

- Include a REST API smoke test (confirming key endpoints return successful responses in both URL forms) as part of routine deployment and migration verification, rather than discovering a missing route only when a dependent feature fails.
- Document which permalink structure a site's REST-dependent features (a headless front end, the Block Editor) require, and verify it as part of environment setup.
- Review PHP error logs after plugin or theme updates for errors occurring during `rest_api_init`, rather than assuming a successful update also means REST routes registered correctly.
- Where a `rest_endpoints` filter or similar route-modification code is intentionally used, document its purpose and scope clearly, so a future investigation does not need to rediscover why a specific route is missing.
- Monitor key REST endpoints — both custom and WordPress's own built-in ones — as part of ordinary uptime and health monitoring, particularly for headless or API-dependent sites.

---

# 15. Security Considerations

- Do not expose internal implementation details (full file paths, stack traces) in a `rest_no_route` or other REST error response.
- Removing routes via a `rest_endpoints` filter is a legitimate way to reduce a site's attack surface (for example, restricting the users endpoint's enumeration behavior); document any such intentional removal clearly so it is not later mistaken for a defect during diagnosis.
- Coordinate changes to security-plugin REST-blocking rules through a controlled process, since overly broad blocking can silently break legitimate functionality (the Block Editor, official mobile apps, other first-party integrations) that itself depends on the REST API.
- Treat an unexplained, newly appearing pattern of `rest_no_route` errors as a potential signal of a compromised or maliciously altered plugin (one that removed or renamed routes as part of a broader compromise) rather than assuming it is always routine misconfiguration, particularly where no legitimate change explains it.

---

# 16. Related Errors

The following are cited as they exist in this repository, or as conceptual distinctions where noted.

1. [WP-ERROR-022 — WordPress REST API Access Denied](WP-ERROR-022-REST-API-ACCESS-DENIED.md) — exists in this repository; see Section 6 (Distinction) above.
2. [WP-ERROR-023 — WordPress REST API Response Error](WP-ERROR-023-REST-API-RESPONSE-ERROR.md) — exists in this repository; see Section 6 (Distinction) above.

---

# 17. Notes

This entry documents the first of three entries `SF-TAXONOMY-002` declares for the REST API category, owning the route-resolution stage of the REST request lifecycle. It does not restate `WP-ERROR-022`'s or `WP-ERROR-023`'s own boundaries; see `SF-TAXONOMY-002` for the complete, governing three-stage progression. Consistent with the single-responsibility principle in **SF-SPEC-001** Section 4.3, this entry covers both ways a route can end up unmatched (never registered, or removed from the table before matching) as one cohesive failure mode, since both share the same underlying, observable condition and the same `rest_no_route` response — while explicitly excluding a request that intercepts an already-matched route before its callback runs, which remains `WP-ERROR-022`'s condition regardless of how colloquially similar the two are described.

This entry's governing direction was `SF-TAXONOMY-002` Version 1.2 (post-`SF-REVIEW-045` correction and the subsequent argument-validation placement decision), whose own boundary for this entry — the request fails before a callback is selected — is applied here without narrowing or widening it; the argument/schema-validation placement decision recorded in that document's Section 4 does not affect this entry, since it concerns the boundary between `WP-ERROR-022` and `WP-ERROR-023`, both of which presume a route has already been found. The specific technical grounding (the `rest_no_route` error code and message, `register_rest_route()`/`rest_api_init`, the `rest_endpoints` filter's role in route removal, the `rest_route` query variable's independence from permalink structure, and the REST API's own discovery `Link` header/tag) was independently verified against current WordPress documentation before inclusion, following this catalog's established practice.

This entry underwent the review sequence required by **SF-SPEC-001** Section 19, **SF-SPEC-005** Section 5.6, and **SF-SPEC-012**: an author (Class A) review at `docs/reviews/SF-REVIEW-046-WP-ERROR-021-AUTHOR-REVIEW.md`, which found no defects, followed by an independent (Class B) review at `docs/reviews/SF-REVIEW-047-WP-ERROR-021-INDEPENDENT-REVIEW.md`, which reached outcome **Approved with Minor Revisions**, added a diagnostic step checking the bare API root's own resolution, and satisfied the Production Ready gate per SF-SPEC-012 Section 12. Its Status was changed to Production Ready on that basis. This document does not itself constitute either review record; see the cited files for full findings, corrections, and gate decisions. This is the first entry authored and promoted entirely under the `SF-SPEC-013` category lifecycle, from an already-existing, independently reviewed taxonomy through to Production Ready.

The independent review did not designate this entry as a Reference Implementation. That designation, governed separately by **SF-SPEC-001** Section 22, has not been sought or asserted here.

No Reference Implementation is currently designated by **SF-SPEC-001**; this entry's relationship to that designation, and to any future `WP-SCENARIO-XXX` runtime evidence, is not asserted here and shall not be assumed until such evidence or designation actually exists.

**Version 1.1 (2026-07-16):** post-certification correction through **SF-SPEC-013** Section 5.6, prompted by the source gate preceding `WP-VERIFICATION-007`. Corrected the conflation between WordPress's `rest_no_route` result—produced only after REST route/method matching—and a pretty URL that never reaches REST dispatch because rewrite or server routing failed. Rewrite comparison remains required diagnostic evidence but is no longer an owned cause of this entry. Also corrected Section 6's reversed “latter” reference. Reviewed via `SF-REVIEW-183`/`184`; REST API re-certified via `SF-REVIEW-185`/`186` as Knowledge Baseline v2.
