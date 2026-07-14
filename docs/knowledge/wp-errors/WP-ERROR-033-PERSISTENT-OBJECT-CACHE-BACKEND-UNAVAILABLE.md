# WP-ERROR-033 — WordPress Persistent Object Cache Backend Unavailable

---

# 1. Knowledge Entry

WordPress Persistent Object Cache Backend Unavailable

---

# 2. Metadata

* **Error ID:** `WP-ERROR-033`
* **Title:** WordPress Persistent Object Cache Backend Unavailable
* **Category:** Performance
* **Severity:** Critical
* **Recovery Priority:** Immediate
* **Status:** Production Ready
* **Version:** 1.0

---

# 3. Summary

A WordPress site configured to use a persistent, external object-cache backend (commonly Redis or Memcached, via an `object-cache.php` drop-in) experiences a failure of that caching mechanism itself — the backend cannot be connected to, a specific read or write operation against it fails, or another condition prevents the mechanism from operating as intended — and the site's own behavior in response depends entirely on how the specific drop-in in use handles that failure, since no single, guaranteed outcome exists at the WordPress-core level.

---

# 4. Primary Failure Mode

WordPress core's own object-cache API (`wp_cache_get()`, `wp_cache_set()`, and related functions) delegates every call to whichever `WP_Object_Cache` implementation is currently active. Where a site has installed an `object-cache.php` drop-in configured to communicate with an external, persistent backend, that drop-in's own code — not WordPress core — is responsible for establishing and maintaining the connection, translating each `wp_cache_*()` call into the backend's own protocol, and deciding what happens when that communication fails. This entry's condition occurs when the configured backend cannot be reached, a specific operation against it fails, or the drop-in's own integration otherwise stops functioning as intended — and the resulting site behavior bifurcates sharply depending on the specific drop-in's own failure handling: a well-behaved drop-in degrades gracefully, falling back to a plain, non-persistent, single-request cache; a drop-in that does not handle the failure gracefully can instead produce a PHP fatal error on every request, or hang while repeatedly attempting to reconnect.

---

# 5. Severity

This entry is classified **Critical**, with impact ranging sharply depending on the specific drop-in's own failure-handling behavior:

- Where the drop-in degrades gracefully, the impact is a performance degradation, not an outage: every request now misses the persistent cache, database and computation load increases correspondingly, and the site remains functional but slower — a narrower blast radius, closer to `WP-ERROR-009`'s own scoped condition than to a full-site outage.
- Where the drop-in does not handle the failure gracefully, the impact can be a full-site outage: a PHP fatal error on every request, or every request hanging while the drop-in repeatedly attempts and fails to reconnect, leaving no functioning request path at all.
- This entry remains classified at the level of its most severe possible manifestation, consistent with the range-based Critical classification used elsewhere in this catalog (`WP-ERROR-021`, `024`–`032`) — deliberately not following `WP-ERROR-009`'s own considered exception to that pattern, since that entry's own Severity reasoning explicitly excludes any condition where the site has no functioning request path at all, while this entry's own worst-case manifestation can be exactly that.

---

# 6. Distinction

This entry applies only when verified evidence establishes that a site is genuinely configured to use a persistent, external object-cache backend via an `object-cache.php` drop-in, and that the backend itself, or the drop-in's own communication with it, is the verified point of failure — not that a downstream symptom merely correlates with caching being involved somewhere in the request.

**Evidence-quality discipline this entry specifically requires:** every claim in this entry's own text is attributed to the specific layer responsible for it, since the three layers involved have entirely distinct authorship and cannot be assumed to behave uniformly:

- **WordPress core's own behavior** is limited to defining the `wp_cache_*()` function signatures and providing a built-in, non-persistent, single-request `WP_Object_Cache` fallback used only when no `object-cache.php` drop-in is present at all. Core itself makes no promise about what happens when a *configured* external backend becomes unreachable, because core's own code is never the layer actually talking to that backend.
- **The `object-cache.php` drop-in's own behavior** determines the actual consequence of a backend failure: whether the failure is caught and results in graceful degradation, an admin notice, a PHP fatal error, or a hang. This behavior varies by which specific drop-in is installed and how it is configured, and shall be verified against that specific drop-in's own documented behavior rather than assumed from another drop-in's behavior or from a general expectation of "WordPress" behavior.
- **The specific backend's own behavior** (for example, Redis's own authentication and out-of-memory handling, or Memcached's own per-item size limit and eviction policy) determines *why* a given operation failed, and requires the backend's own diagnostic tools to investigate, not WordPress's own logs alone.

**Three internal causes this entry keeps deliberately separate, not blended into a single generic "cache is down" condition:**

1. **Connection failure** — the drop-in cannot establish or maintain a connection to the configured backend at all (the backend process is not running, a network path is blocked, authentication to the backend itself is rejected).
2. **Operation failure** — a connection exists, but a specific read or write operation against the backend fails (for example, a value exceeding Memcached's own default per-item size limit silently fails to store; a write is rejected because Redis is out of memory and its own configured policy, for example `noeviction`, declines to evict existing data to make room, rather than an active eviction policy freeing space to allow the write to proceed).
3. **Other mechanism-level failure** — the drop-in itself fails to initialize correctly for a reason other than backend communication (for example, a required PHP client library or extension the drop-in depends on is unavailable), preventing the caching mechanism from operating as intended even before any communication with the backend is attempted.

It is distinct from:

- **`WP-ERROR-021` — WordPress REST API Route Not Found**: a caching layer serving a stale, previously-cached REST 404 response remains that entry's own condition. This entry owns the object-cache backend's own operational state, not the downstream consequence of any specific cached response being stale.
- **`WP-ERROR-025` — WordPress Authentication Cookie Invalid or Expired**: a caching layer serving one user's authentication cookie to another remains that entry's own condition, including its own explicit security-incident-level treatment. This entry does not own that symptom merely because an object cache was involved somewhere in the request path.
- **`WP-ERROR-027` — WordPress Nonce Verification Failure, Non-REST**: cached markup serving a stale nonce remains that entry's own condition. This entry does not own that symptom either.
- **`WP-ERROR-030` — WordPress CORS (Cross-Origin) Policy Failure**: a caching layer replaying stale CORS headers computed for a different origin remains that entry's own condition.
- **`WP-ERROR-009` — WordPress Database Query Timeout**: an object-cache backend outage can increase database load — every request that would have been served from cache now issues a database query instead — and that increased load can contribute to a specific query subsequently timing out. This entry describes that relationship as a *consequence*, not a condition it diagnoses: once the observed failure is that a specific database query exceeded its own applicable timeout, ownership of diagnosing and resolving *that* condition transfers to `WP-ERROR-009` in full, including determining which layer enforced the timeout and why. This entry's own diagnosis (Section 11) stops at confirming the object-cache backend's own unavailability as a verified, independent condition; it does not attempt to diagnose timeout behavior itself.
- **`WP-ERROR-014` — Required PHP Extension Missing**: where diagnosis confirms cause 3 (the drop-in itself fails to initialize) traces to a missing PHP extension the backend's own client library requires (for example, the `redis` or `memcached` PECL extension), that underlying condition is `WP-ERROR-014`'s own territory once confirmed. This entry owns the observable fact that the caching mechanism is not operating and diagnoses which of the three causes is responsible; it hands off to `WP-ERROR-014` for the extension-availability root cause and its own recovery, the same escalation pattern established elsewhere in this catalog.
- **A specific caching plugin's own business-logic defect unrelated to backend communication** (Plugin category, per `SF-TAXONOMY-005` Section 2's own reasoning): this entry owns the mechanism's own communication with its configured backend, not an unrelated defect in a caching plugin's own broader feature set.

---

# 7. Scope

**Covered:** A verified condition in which a site configured to use a persistent, external object-cache backend via an `object-cache.php` drop-in experiences a connection failure to that backend, a specific read or write operation failure against it, or another mechanism-level failure preventing the drop-in from operating as intended — regardless of whether the resulting site-level consequence is a graceful performance degradation or a full-site outage.

**Excluded:**

- Any site with no `object-cache.php` drop-in present at all, since WordPress core's own built-in, non-persistent cache has no external backend to fail — there is no condition for this entry to describe in that configuration.
- A caching layer serving a stale REST API 404 response (`WP-ERROR-021`).
- A caching layer serving one user's authentication cookie to another (`WP-ERROR-025`).
- A caching layer serving a stale nonce embedded in cached markup (`WP-ERROR-027`).
- A caching layer replaying stale CORS headers computed for a different origin (`WP-ERROR-030`).
- A specific database query exceeding its own applicable timeout, even where increased load from a cache outage contributed to it (`WP-ERROR-009`).
- A missing PHP extension the backend's own client library requires, independent of the caching mechanism's own behavior in surfacing it (`WP-ERROR-014`).
- A specific caching plugin's own business-logic defect unrelated to backend communication.
- Full-page (page-level) caching, a mechanistically distinct caching layer this taxonomy assigns to a separate entry (`WP-ERROR-034`).

---

# 8. WordPress Components

Listed as components commonly involved, not as a claim that every installation exercises every one of them:

- WordPress core's own object-cache function definitions (`wp_cache_get()`, `wp_cache_set()`, `wp_cache_add()`, `wp_cache_delete()`, and related functions in `wp-includes/cache.php`), and its own built-in, non-persistent `WP_Object_Cache` fallback class, used only when no `object-cache.php` drop-in is present.
- The `object-cache.php` drop-in itself, loaded early in WordPress's own bootstrap sequence via the same drop-in mechanism `WP-ERROR-013`'s own Section 8 already documents for drop-ins generally — a third-party implementation, not part of WordPress core, responsible for all communication with the configured backend.
- Core's own `wp_cache_add_non_persistent_groups()` and `wp_cache_add_global_groups()`, which determine which cache groups a well-behaved drop-in should never attempt to send to the external backend at all, independent of whether that backend is currently reachable.
- A specific backend and its own required PHP client library or extension — commonly Redis (typically via the `redis` PECL extension, or a PHP-only client library some drop-ins bundle instead) or Memcached (typically via the `memcached` or `memcache` PECL extension) — each with its own distinct connection, authentication, and failure characteristics the drop-in's own code is responsible for handling.
- Site Health's own reporting of whether a persistent object cache is currently active, where the installed WordPress version's own Site Health implementation includes this check; the specific version this was introduced in is not asserted here.

---

# 9. Typical Symptoms

- A PHP fatal error, or a hang, occurring on every request, where the underlying cause traces to the `object-cache.php` drop-in's own attempt to communicate with an unreachable backend.
- A gradual, site-wide increase in page-load time and database query count, with no corresponding change in traffic or content, where the underlying cause is the persistent cache silently no longer being used.
- An admin notice or dashboard warning, generated by the specific drop-in in use, reporting that it cannot connect to the configured backend — where that drop-in provides one; not every drop-in does.
- Site Health reporting that persistent object caching is not currently active, on a site the administrator believes is configured to use one.
- A specific, previously-fast operation that relies heavily on cached data (for example, an options-heavy admin screen, or a plugin that caches expensive computed values) becoming slow specifically, while other operations degrade more gradually.
- An increase in database load or a subsequent database query timeout (`WP-ERROR-009`) correlating with the point the object-cache backend became unavailable, as a downstream consequence rather than this entry's own directly observed symptom.
- The backend's own monitoring or logs (Redis's own log, Memcached's own stats output) showing the service is not running, is refusing connections, or is rejecting operations, even where WordPress's own symptoms alone do not make the cause obvious.

---

# 10. Common Causes

Causes are grouped by category. Inclusion in this list identifies a category as plausible; it does not assert that any specific one is present without diagnostic confirmation.

- The backend service itself (Redis, Memcached, or another persistent cache server) is not running, was stopped, or crashed.
- A network path between the web server/PHP process and the backend is blocked (a firewall rule, a security-group change, a container network misconfiguration) after previously working.
- Authentication to the backend is rejected — for example, a Redis `requirepass` value that changed without the drop-in's own configuration being updated to match.
- The backend is reachable but refusing new operations due to its own resource exhaustion — for example, Redis configured with a `maxmemory` limit and a policy (such as `noeviction`) that declines to evict existing data for the specific operation attempted, or Memcached rejecting a value exceeding its own default per-item size limit.
- The drop-in's own configuration (host, port, socket path, or credentials) does not match the backend's own current, actual configuration, following a migration, a credential rotation, or an infrastructure change.
- The PHP client library or extension the drop-in depends on (for example, the `redis` or `memcached` PECL extension) is missing, disabled, or was removed during a PHP version change or hosting migration.
- The backend was intentionally taken offline for maintenance without the drop-in being temporarily disabled or reconfigured to tolerate the outage gracefully.
- A container orchestration or auto-scaling event replaced the backend's own network address without the drop-in's own configuration being updated (for example, a static IP or hostname configured where the actual backend now resolves elsewhere).

---

# 11. Diagnosis

Verify the following, starting from the least invasive observation and narrowing only once the general shape of the failure is established:

1. **Confirm the site is genuinely configured to use a persistent object cache at all.** Check for the presence of `wp-content/object-cache.php`, or Site Health's own reporting where available; a site with no such drop-in cannot be experiencing this entry's own condition, regardless of how the symptoms otherwise present.
2. **Confirm the caching mechanism is genuinely failing**, as opposed to functioning correctly but simply being under load; distinguish a hard failure (connection refused, operations erroring) from a performance characteristic of the backend itself operating within its own normal, if slower, parameters.
3. **Determine which of the three causes in Section 6 applies**, before investigating any specific underlying mechanism: whether the drop-in cannot connect to the backend at all (cause 1), whether a connection exists but specific operations fail (cause 2), or whether the drop-in itself fails to initialize for a reason unrelated to backend communication (cause 3).
4. **Only once the general point of failure is established, investigate the specific mechanism responsible:**
   - Where a connection failure (cause 1) is confirmed, test connectivity to the backend directly, independent of WordPress — for example, the backend's own command-line client (`redis-cli PING`, or Memcached's own `stats` command via `nc` or `telnet`) — rather than relying solely on WordPress's own symptoms to infer the backend's own state.
   - Where an operation failure (cause 2) is confirmed, consult the specific backend's own logs or diagnostic output for the exact rejection reason (an out-of-memory condition, an oversized value, an authentication failure on a specific command) rather than assuming a uniform "backend down" explanation.
   - Where a mechanism-level initialization failure (cause 3) is confirmed, determine whether the required PHP client library or extension is actually present and loaded for the specific SAPI/runtime in use, and evaluate against `WP-ERROR-014` before concluding the drop-in's own code is at fault.
5. **Consult the specific `object-cache.php` drop-in's own documentation for its actual failure-handling behavior** before assuming a specific site-level consequence (graceful degradation versus fatal error) applies generally; this varies by drop-in and is not a WordPress-core-level guarantee (see Section 6).
6. Where a downstream database query timeout (`WP-ERROR-009`) is also present, treat the object-cache outage as a contributing factor to note, not as this entry's own diagnostic target for the timeout itself; hand off to `WP-ERROR-009`'s own diagnostic procedure for the timeout specifically.
7. Preserve relevant evidence — the drop-in's own error output, the backend's own logs, and the exact configuration (host, port, credentials, relevant limits) in use — before making any change.
8. Where the engineer performing diagnosis does not control the backend infrastructure (a managed Redis/Memcached service, a hosting-provided caching layer), escalate to the hosting provider or infrastructure team rather than attempting an unverified workaround.

---

# 12. Recovery Procedure

Recovery shall target the specific, verified cause identified in Diagnosis (Section 11), not merely restart services indiscriminately.

Permitted recovery categories, depending on the verified cause, include:

- Restoring the backend service itself where it is confirmed stopped or crashed, and investigating why it stopped before considering the incident closed.
- Correcting a network path, firewall rule, or security-group configuration confirmed to be blocking legitimate access to the backend.
- Correcting the drop-in's own configuration (host, port, socket path, or credentials) to match the backend's own actual, current configuration.
- Addressing backend-side resource exhaustion — for example, adjusting Redis's own `maxmemory`/eviction policy, or identifying and reducing an oversized value being written to Memcached — where diagnosis confirms this as the cause of an operation failure.
- Installing or re-enabling the required PHP client library or extension where diagnosis confirms cause 3, per `WP-ERROR-014`'s own recovery procedure once that condition is confirmed.
- Temporarily removing or disabling the `object-cache.php` drop-in, forcing a return to WordPress core's own built-in, non-persistent cache, where the backend cannot be restored promptly and a graceful, if slower, functioning site is preferable to leaving a badly-behaving drop-in in place.
- Escalating to the hosting provider or infrastructure team where the backend is managed outside the engineer's own direct control.

Recovery shall not leave a drop-in installed that is confirmed to be producing a PHP fatal error or hang on every request while investigation continues; removing or disabling the drop-in to restore a functioning, if degraded, site takes priority over completing root-cause analysis, consistent with this catalog's general recovery-priority approach for a site-wide-impacting condition.

---

# 13. Validation

Recovery is successful when:

- The specific, previously-failing connection or operation against the backend is confirmed to succeed, tested directly against the backend where feasible, not only inferred from WordPress's own behavior improving.
- The site's own request handling returns to its prior, expected performance characteristics, not only that the immediate fatal error or hang has stopped.
- Where the drop-in was temporarily removed or disabled as an interim recovery step, it has been correctly restored once the underlying backend condition is confirmed resolved, or that decision to leave it removed is deliberate and documented rather than left ambiguous.
- Site Health, or the specific drop-in's own status reporting where available, confirms the persistent object cache is active and functioning.
- No downstream database query timeout (`WP-ERROR-009`) that was a consequence of this condition continues to recur once the object-cache backend itself is confirmed restored.
- No unrelated infrastructure, configuration, or service was altered as a side effect of the recovery.

---

# 14. Prevention

- Monitor the backend service's own availability and resource utilization directly (via its own monitoring tools), rather than relying solely on WordPress-level symptoms to first reveal an outage.
- Verify the specific `object-cache.php` drop-in in use has documented, tested graceful-degradation behavior before relying on it in production, rather than assuming all drop-ins behave the same way on a backend failure.
- Keep the drop-in's own configuration (host, port, credentials) under the same change-management discipline as any other infrastructure credential, particularly across a migration or credential rotation.
- Size backend resource limits (Redis `maxmemory`, Memcached's own item-size and total-memory configuration) deliberately, based on the site's own actual cache usage, rather than accepting default values that may not match production needs.
- Confirm the required PHP client library or extension is present and version-matched across every relevant runtime (web, CLI, cron) before relying on a persistent object cache in production.
- Include object-cache backend availability as an explicit check in any infrastructure migration or scaling procedure, so a changed backend address or credential is caught before it causes a production failure.

---

# 15. Security Considerations

- Do not configure a backend (particularly Redis, which has no authentication enabled by default in many default configurations) to be reachable from outside the environment WordPress itself runs in; an exposed, unauthenticated cache backend can allow unauthorized read or write access to cached data, which may include sensitive computed values depending on what the site caches.
- Where credentials are used to authenticate to the backend, manage and rotate them with the same discipline as any other infrastructure credential, and do not log them in plaintext during diagnosis.
- Do not disable authentication to the backend as a troubleshooting shortcut to resolve a connection failure; diagnose the actual credential or configuration mismatch instead.
- Treat an unexplained backend outage as worth a brief check for unauthorized access or resource exhaustion by an unrelated process sharing the same infrastructure, not only as routine service failure, particularly where the backend is shared across multiple applications.
- Coordinate any change to shared backend infrastructure (a managed Redis/Memcached service used by more than one application) through a platform-appropriate process, since a change made for WordPress's own benefit can affect other consumers of the same service.

---

# 16. Related Errors

The following are cited as they exist in this repository, or as conceptual distinctions where noted.

1. [WP-ERROR-021 — WordPress REST API Route Not Found](WP-ERROR-021-REST-API-ROUTE-NOT-FOUND.md) — exists in this repository; see Section 6 (Distinction) above.
2. [WP-ERROR-025 — WordPress Authentication Cookie Invalid or Expired](WP-ERROR-025-AUTHENTICATION-COOKIE-INVALID-OR-EXPIRED.md) — exists in this repository; see Section 6 (Distinction) above.
3. [WP-ERROR-027 — WordPress Nonce Verification Failure, Non-REST](WP-ERROR-027-NONCE-VERIFICATION-FAILURE-NON-REST.md) — exists in this repository; see Section 6 (Distinction) above.
4. [WP-ERROR-030 — WordPress CORS (Cross-Origin) Policy Failure](WP-ERROR-030-CORS-CROSS-ORIGIN-POLICY-FAILURE.md) — exists in this repository; see Section 6 (Distinction) above.
5. [WP-ERROR-009 — WordPress Database Query Timeout](WP-ERROR-009-DATABASE-QUERY-TIMEOUT.md) — exists in this repository; see Section 6 (Distinction) above for the dependency/consequence relationship.
6. [WP-ERROR-014 — Required PHP Extension Missing](WP-ERROR-014-REQUIRED-PHP-EXTENSION-MISSING.md) — exists in this repository; see Section 6 (Distinction) above for the diagnose-then-hand-off relationship.
7. [WP-ERROR-034 — WordPress Page Cache Not Active](WP-ERROR-034-PAGE-CACHE-NOT-ACTIVE.md) — see Section 7 (Scope) above.

---

# 17. Notes

This entry documents the general, verified observable condition of a persistent object-cache backend failing, or the specific drop-in's own communication with it failing, distinguishing the three mechanically distinct causes — connection, operation, and mechanism-level initialization — at which that failure can occur. It is the first entry drafted against `SF-TAXONOMY-006`, whose own drafting incorporated a proactive cross-category ownership sweep specifically because four existing entries in three other categories already claim adjacent, symptom-specific caching territory (see Section 6).

This entry deliberately does not assert a single, guaranteed WordPress-core-level behavior for what happens when the configured backend fails, since that behavior is the specific `object-cache.php` drop-in's own responsibility, not a core guarantee — the same evidence-quality correction `SF-TAXONOMY-006`'s own independent review (`SF-REVIEW-096`, Finding IF-1) already applied to this entry's own taxonomy-level description before this entry was drafted.

Consistent with the single-responsibility principle in **SF-SPEC-001** Section 4.3, this entry does not claim ownership of any specific downstream symptom four other entries already document, nor of the underlying PHP-extension condition its own cause 3 may trace to, nor of a database query timeout that is a consequence rather than this entry's own condition.

This entry reached `Production Ready` via `SF-REVIEW-097` (Class A author review; one Minor structural finding corrected) and `SF-REVIEW-098` (Class B independent review; two Minor findings — IF-1, a Redis eviction-policy precision correction within this entry, and IF-2, a cross-document completeness gap in `WP-ERROR-009`'s own Common Causes list — both corrected within that same review) per **SF-SPEC-001** Section 19, **SF-SPEC-005** Section 5.6, and **SF-SPEC-012**.

No Reference Implementation is currently designated by **SF-SPEC-001**; this entry's relationship to that designation, and to any future `WP-SCENARIO-XXX` runtime evidence, is not asserted here and shall not be assumed until such evidence or designation actually exists.
