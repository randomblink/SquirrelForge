# WP-VERIFICATION-007 — WP-ERROR-021 Runtime Verification

Structured per `SF-TEMPLATE-005` and the `WP-VERIFICATION-XXX` series convention. Governed by `SF-SPEC-002`, `SF-SPEC-006`, and `SF-SPEC-011`.

---

# 1. Evidence Record Identity

**Record ID:** `WP-VERIFICATION-007`

**Date:** 2026-07-16

---

# 2. Associated Scenario or Artifact

`WP-ERROR-021` — WordPress REST API Route Not Found, Version 1.1.

---

# 3. Objective

Determine whether WordPress returns the documented `rest_no_route` HTTP 404 only after REST path-and-method matching finds no handler, and whether route removal, rewrite-independent access, downstream failures, pre-WordPress interception, WP-CLI inspection, recovery, and cleanup agree with the corrected ownership boundary.

**Expected behavior, per the entry:**

- A nonexistent route, incorrect namespace/path, or unsupported HTTP method returns `rest_no_route` with HTTP 404 after REST dispatch is reached.
- Removing a registered route through `rest_endpoints` produces the same route-matching result.
- A valid `?rest_route=...` request succeeding while a pretty `/wp-json/...` request is intercepted before WordPress proves a separate reachability failure, not `rest_no_route`.
- A matched route denied before its callback and a matched route whose callback returns an error remain distinguishable downstream controls for WP-ERROR-022 and WP-ERROR-023.
- Core WP-CLI has no built-in REST route-list command; `wp eval` can inspect `rest_get_server()->get_routes()`.
- Restoring the route table and normal request path permits successful repeated requests.

---

# 4. Baseline

- Repository: clean `agent/wp-verification-007-research` at `44995fd`; no repository file changed during runtime research.
- WordPress: 7.0.1, newly installed in a disposable directory.
- WP-CLI: current official PHAR, Version 2.12.0.
- PHP: 8.2.29 CLI and built-in HTTP server.
- Database: official WordPress SQLite Database Integration 2.2.23.
- Permalinks: `/%postname%/`, with WordPress rewrite rules flushed.
- Healthy route control: `GET /wp-json/sf/v1/echo` returned HTTP 200 and `{"result":"route-callback-ran"}`.

Hospital, Thematic, every pre-existing Local site, and every certified repository artifact were excluded from the environment.

---

# 5. Environment

- macOS 26.5.2, Darwin 25.5.0, x86_64.
- Disposable site and all fixtures existed under `/private/tmp/sf-verification-007`.
- A disposable must-use plugin registered three routes on `rest_api_init`: a public GET success route, a GET route denied by `permission_callback`, and a GET route whose callback returned `WP_Error` with status 500.
- The plugin's `rest_endpoints` filter removed only the success route when the request carried `sf_remove_route=1`.
- A PHP router represented successful front-controller delivery to WordPress. A separate explicit interception control returned a generic text 404 before WordPress for pretty paths while allowing `/index.php` or `/` query-string requests through.
- HTTP evidence was collected with an API client (`curl`) including status, headers, content type, and body. PHP server request logs corroborated whether the request was handled by the WordPress path or the interception control.

The explicit interception control verifies the execution boundary but is not represented as Apache, Nginx, or WAF runtime execution. Product-specific rewrite and security-layer behavior remains source/architecture-verified and requires a faithful disposable server for future execution.

---

# 6. Execution Procedure

1. Installed the disposable site, configured pretty permalinks, loaded the isolated route fixture, and established successful pretty and query-string controls.
2. Requested a nonexistent route and an incorrect namespace/path with GET.
3. Sent POST to the GET-only success route; separately sent HEAD to confirm Core's documented HEAD-to-GET fallback.
4. Removed the success route from the request-visible route map through `rest_endpoints`, testing both pretty and query-string forms.
5. Requested the permission-denied route and callback-failure route as downstream negative controls.
6. Confirmed the API index listed namespace `sf/v1` and the fixture routes.
7. Used `wp cli has-command rest` and `wp eval` to test core command availability and inspect the registered route/method table.
8. Ran a pre-WordPress interception control: allowed `/?rest_route=/sf/v1/echo` to WordPress but returned a generic plain-text 404 for `/wp-json/sf/v1/echo` before WordPress.
9. Removed the route-removal trigger, repeated the success request, and independently confirmed the route was present in the WP-CLI-inspected table.
10. Stopped both local servers and removed the complete disposable environment.

An initial query-string request used `/index.php` while the first temporary server's document root was one directory above the site, producing that server's own 404. The harness path was corrected to `/?rest_route=...`; all reported query-string conclusions use the corrected request. This setup observation is retained for transparency and is not WordPress evidence.

---

# 7. Evidence Artifacts

- **Healthy route:** pretty and query-string GET returned HTTP 200, JSON, and `{"result":"route-callback-ran"}`.
- **Nonexistent route:** HTTP 404 with `{"code":"rest_no_route","message":"No route was found matching the URL and request method.","data":{"status":404}}`.
- **Incorrect namespace/path:** the same HTTP 404/code/message/data.
- **Incorrect method:** POST to the GET-only route returned the same HTTP 404/code/message/data. HEAD returned HTTP 200 with `Allow: GET`, confirming Core's GET fallback rather than a mismatch.
- **Route removal:** with `/sf/v1/echo` unset through `rest_endpoints`, both pretty and corrected query-string requests returned the same `rest_no_route` HTTP 404. Removing the trigger restored HTTP 200.
- **Access-denied control:** the matched protected route returned HTTP 403 with code `sf_access_denied`, not `rest_no_route`; its callback success value was not returned.
- **Callback-failure control:** the accepted route returned HTTP 500 with code `sf_callback_failure`, not `rest_no_route`.
- **API index:** query-string root returned HTTP 200 and listed namespace `sf/v1` plus `/sf/v1/echo`, `/protected`, and `/failure` routes.
- **WP-CLI:** `wp cli has-command rest --path=...` exited 1 with no command, while `wp eval` reported `route_present=yes` and `methods=GET` from `rest_get_server()->get_routes()`.
- **Pre-WordPress interception:** query-string GET returned the route's HTTP 200 JSON result; the identical pretty path returned HTTP 404, `Content-Type: text/plain`, and `Static server: path not routed to WordPress.` It contained no `rest_no_route` code or WordPress JSON envelope.
- **Recovery:** final query-string request returned HTTP 200 and WP-CLI reported `route_restored=yes`.

---

# 8. Validation

The runtime matrix confirms WP-ERROR-021 Version 1.1. WordPress produced the exact `rest_no_route` 404 only when REST path-and-method matching ran without a handler. `rest_endpoints` removal participated in that same mechanism. Permission denial and callback failure remained distinct downstream results. The pre-WordPress control demonstrated why a generic pretty-path 404 is not sufficient evidence for this entry.

**Differences from documentation:**

1. No discrepancy was found against WP-ERROR-021 Version 1.1 or SF-TAXONOMY-002 Version 1.5.
2. Apache, Nginx, and a real WAF were not executed; their product-specific behavior remains explicitly source/architecture-verified.
3. The temporary server's initial `/index.php` document-root mistake was a harness error, corrected before evidence was concluded; it does not alter the entry.

**Required repository changes:** None to WP-ERROR-021, SF-TAXONOMY-002, the REST API baseline, or any other certified knowledge. This change adds only this verification record, its reviews, and applicable verification-status navigation.

---

# 9. Negative Validation

- A 403 permission result and a 500 callback result proved HTTP failure alone does not establish route-not-found ownership.
- A generic plain-text 404 without WordPress's JSON code proved status 404 alone does not establish `rest_no_route`.
- Query-string success during pretty-path interception proved the route remained registered and callable.
- HEAD success proved not every method differing from the registered literal GET is unsupported; Core intentionally falls back from HEAD to GET.
- Route inspection independently showed the route existed before and after the request-scoped removal trigger.
- No browser CORS policy, authentication plugin, database failure, bootstrap failure, existing website, Hospital installation, or Thematic installation participated.

---

# 10. Cleanup Evidence

- The request-scoped route-removal trigger left no persistent route change.
- Final HTTP request succeeded and final WP-CLI inspection reported the route present.
- Both localhost PHP servers were terminated.
- The disposable WordPress site, SQLite database, route fixture, router controls, WP-CLI PHAR, and downloaded archives were removed from `/private/tmp`.
- No `sf-verification-007*` path remained.
- The repository branch remained clean after runtime research.

---

# 11. Repository Validation Evidence

After the complete verification and review artifact set existed, `scripts/validate-repo.sh .` passed, the complete PHPUnit suite passed (146 tests, 338 assertions), every PHP file under `src/` and `tests/` passed `php -l`, Markdown links passed, and `git diff --check` passed.

---

# 12. Classification

**Permanent.** This record fixes runtime evidence for WP-ERROR-021 Version 1.1's route/method matching, route removal, downstream distinctions, pre-dispatch boundary, recovery, and explicit server-platform limits.

---

# 13. Retention Decision

Retain permanently. Future faithful Apache, Nginx, CDN, or WAF execution shall be recorded separately rather than retroactively broadening this record.

---

# 14. Traceability Map

- `WP-ERROR-021` Version 1.1: direct subject.
- `SF-TAXONOMY-002` Version 1.5: corrected category ownership.
- Correction commit `da51e91`: route-matching/rewrite ownership correction completed before runtime research.
- `SF-REVIEW-183`–`186`: correction reviews and REST API Knowledge Baseline v2.
- Verification reviews: `SF-REVIEW-187` and `SF-REVIEW-188`.

---

# 15. Engineering Review Status

Reviewed via `SF-REVIEW-187` (Class A) and `SF-REVIEW-188` (Class B). Both approved with no open findings.

---

# 16. Revision History

| Version | Date | Summary | Approval Status |
|---|---|---|---|
| 1.0 | 2026-07-16 | Initial record. Confirms route/method mismatch, route removal, downstream negative controls, pre-dispatch distinction, WP-CLI inspection, recovery, cleanup, and platform limits. | Reviewed via SF-REVIEW-187/188 |
