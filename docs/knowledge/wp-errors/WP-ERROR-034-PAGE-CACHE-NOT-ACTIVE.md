# WP-ERROR-034 — WordPress Page Cache Not Active

---

# 1. Knowledge Entry

WordPress Page Cache Not Active

---

# 2. Metadata

* **Error ID:** `WP-ERROR-034`
* **Title:** WordPress Page Cache Not Active
* **Category:** Performance
* **Severity:** High
* **Recovery Priority:** High
* **Status:** Production Ready
* **Version:** 1.0

---

# 3. Summary

A WordPress site intended to serve full-page-cached responses is instead serving every request without page-level caching at all, because the mechanism itself — the `WP_CACHE` constant and the `advanced-cache.php` drop-in a caching plugin provides — was never engaged, is administratively disabled despite appearing configured, or is engaged but unable to write cache data, rather than because the mechanism is functioning correctly but serving stale content.

---

# 4. Primary Failure Mode

WordPress core defines no full-page-caching implementation of its own. It provides only a single, narrow hook for one to exist: if the `WP_CACHE` constant is defined `true` (commonly in `wp-config.php`), core attempts to include `wp-content/advanced-cache.php` very early in the bootstrap sequence — before most of WordPress, including plugins, has loaded — using the same early drop-in-loading mechanism `WP-ERROR-013`'s own Section 8 already documents for drop-ins generally. Everything about how page caching actually works — where cached responses are stored, how a cache key is computed, when a cached entry is considered stale, and which requests are eligible for caching at all — is entirely the responsibility of whichever caching plugin supplies `advanced-cache.php`; core supplies no built-in fallback implementation of its own for this mechanism, unlike its own built-in object cache. This entry's condition occurs when that mechanism is not actually functioning: never engaged in the first place, present but administratively turned off, or engaged but unable to write cache data — in every case, every request is served at least as poorly as it would be with no caching plugin installed at all: for causes 1 and 2, functionally identically; for cause 3, potentially worse, where the drop-in's own repeated, failing write attempts impose additional per-request overhead beyond simple inactivity — a performance-degradation condition rather than a correctness one.

---

# 5. Severity

This entry is classified **High** rather than **Critical**, a deliberate departure from most entries in this catalog, mirroring the same considered exception `WP-ERROR-009` already establishes for a comparable reason:

- By this entry's own definition, the site remains fully functional throughout — every request is still served, correctly, by WordPress's own ordinary, uncached request-handling path. This entry never covers a condition in which the site has no functioning request path at all.
- The direct, immediate consequence is degraded performance under load that page caching was specifically intended to absorb, not an outage.
- This entry is nonetheless **High** rather than **Medium** or **Low**, and Recovery Priority **High** rather than **Immediate**, because an inactive page cache removes a load-bearing performance safeguard: a traffic spike the cache was specifically provisioned to absorb can, in its absence, cascade into a genuine outage-capable condition elsewhere in this catalog (increased database load contributing to `WP-ERROR-009`, or connection exhaustion contributing to `WP-ERROR-007`) — but diagnosing and resolving *that* cascading condition, once it actually occurs, belongs to the entry that owns it, not to this one.
- Unlike `WP-ERROR-033`'s own worst-case manifestation (a badly-behaved drop-in producing a PHP fatal error on every request), no plausible manifestation of this entry's own condition includes the mechanism itself causing a fatal error or outage — the complete absence of page caching is, by construction, functionally identical to never having installed a caching plugin at all, which is not itself a failure state for an ordinary WordPress site.

---

# 6. Distinction

This entry applies only when verified evidence establishes that the full-page-cache mechanism itself is not functioning — never engaged, administratively disabled, or unable to write — not that the mechanism is functioning and populating a cache, but that cache is serving stale or incorrect content.

**Evidence-quality discipline this entry specifically requires**, mirroring `WP-ERROR-033`'s own established practice:

- **WordPress core's own behavior** is limited to checking the `WP_CACHE` constant and, where true, attempting to include `advanced-cache.php` during early bootstrap. Core provides no built-in page-caching implementation of its own to fall back to — unlike the object cache, there is no core-level default behavior for this entry to describe once the drop-in is absent; the mechanism simply does not exist for that request.
- **The `advanced-cache.php` drop-in's own behavior**, supplied entirely by whichever caching plugin is in use (for example, WP Super Cache, W3 Total Cache, WP Rocket, or a hosting-provided caching layer), determines the storage mechanism, cache-key scheme, expiration logic, and cacheability rules — all of which vary by plugin and shall be verified against that specific plugin's own documented behavior, not assumed generally.
- **A specific caching plugin's own internal enable/disable setting** is frequently a separate, independent toggle from the `WP_CACHE` constant itself — many caching plugins write `WP_CACHE` into `wp-config.php` during their own setup, but a plugin-level "caching on/off" setting can subsequently disable actual caching while leaving both the constant and the drop-in file in place, which is a distinct condition from either being absent.

**Three internal causes this entry keeps deliberately separate:**

1. **Mechanism never engaged** — `WP_CACHE` is not defined as `true` at all, or `advanced-cache.php` is missing, so WordPress never attempts to load a page-caching mechanism for any request, regardless of whether a caching plugin is installed and active as a WordPress plugin in the ordinary sense (`WP-ERROR-031`'s own, separate condition).
2. **Mechanism present but administratively disabled** — the drop-in loads without error, but the specific caching plugin's own internal configuration indicates caching is currently turned off, so the drop-in executes as a functional no-op on every request.
3. **Mechanism engaged but unable to write cache data** — the drop-in attempts to populate its own cache storage (commonly a directory under `wp-content/cache/`, though the specific location varies by plugin) and that write consistently fails, so no cache entry is ever successfully created despite the mechanism otherwise being correctly configured.

It is distinct from:

- **`WP-ERROR-021`/`025`/`027`/`030`**: each already owns a specific symptom of a page cache *functioning* but serving stale or incorrect content. This entry presumes the opposite — the cache is not populating or not being consulted at all, not that it is serving something wrong.
- **`WP-ERROR-031` — WordPress Plugin Activation Failure**: owns a caching plugin's own activation as an ordinary WordPress plugin (the discrete, toggleable `activate_plugin()` event). This entry presumes that activation, where relevant, already succeeded; its own condition is specifically that the *page-caching mechanism* (`WP_CACHE`/`advanced-cache.php`) is not functioning, which is a separate, later-stage condition a caching plugin can fail to establish even while fully, successfully active as a plugin.
- **`WP-ERROR-013` — WordPress Bootstrap PHP Fatal Error**: owns a PHP fatal error occurring during `advanced-cache.php`'s own early-bootstrap inclusion. This entry presumes the drop-in loads without a fatal error; its own condition is that the drop-in, once loaded, does not actually cache anything.
- **`WP-ERROR-019` — WordPress Filesystem Permission Denied** and **`WP-ERROR-020` — WordPress Disk Space Exhausted**: where diagnosis confirms cause 3 (unable to write cache data) traces to an OS-level permission or capacity constraint on the cache storage location, that underlying condition is `WP-ERROR-019`'s or `WP-ERROR-020`'s own territory once confirmed. This entry owns the observable fact that the mechanism is not populating a cache and diagnoses which of the three causes is responsible; it hands off to `WP-ERROR-019`/`020` for the filesystem-level root cause and its own recovery.
- **`WP-ERROR-033` — WordPress Persistent Object Cache Backend Unavailable**: a mechanistically distinct caching layer (object-level data caching via an external backend) this taxonomy assigns to a separate entry. A site can have a fully functioning object cache and a completely inactive page cache, or the reverse, independently of one another.

---

# 7. Scope

**Covered:** A verified condition in which a WordPress site's own full-page-cache mechanism (`WP_CACHE` and `advanced-cache.php`) is not functioning — never engaged, present but administratively disabled, or engaged but unable to write cache data — resulting in every request being served without page-level caching, regardless of whether a caching plugin is installed or active as an ordinary WordPress plugin.

**Excluded:**

- A functioning page cache serving stale content manifesting through any of `WP-ERROR-021`/`025`/`027`/`030`'s own specific symptoms.
- A caching plugin's own activation failure as an ordinary WordPress plugin (`WP-ERROR-031`).
- A PHP fatal error occurring during `advanced-cache.php`'s own early-bootstrap inclusion (`WP-ERROR-013`).
- An OS-level permission or capacity constraint preventing cache-file writes, independent of the mechanism's own configuration (`WP-ERROR-019`/`020`).
- Object-level caching via an external persistent backend, a mechanistically distinct layer (`WP-ERROR-033`).
- A downstream database query timeout or connection exhaustion that is a *consequence* of increased load from an inactive page cache, once that specific downstream condition is the observed failure (`WP-ERROR-009`/`007`).
- A specific caching plugin's own business-logic defect unrelated to the general mechanism (Plugin category).

---

# 8. WordPress Components

Listed as components commonly involved, not as a claim that every installation exercises every one of them:

- The `WP_CACHE` constant, checked by WordPress core in `wp-settings.php` during early bootstrap, with no built-in default behavior of its own beyond deciding whether to attempt including `advanced-cache.php` at all.
- The `advanced-cache.php` drop-in, loaded via the same early-bootstrap drop-in mechanism `WP-ERROR-013`'s own Section 8 documents for drop-ins generally — supplied entirely by a third-party caching plugin or hand-rolled code, never by WordPress core itself.
- The specific caching plugin's own admin settings, which commonly include an independent enable/disable toggle for actual caching behavior, separate from whether `WP_CACHE`/`advanced-cache.php` are present at all.
- The caching plugin's own storage location (commonly, though not universally, a directory under `wp-content/cache/`), which the plugin's own drop-in code writes to and reads from.
- Site Health's own general performance-related checks, where the installed WordPress version's own Site Health implementation reports on caching status; the specific scope and version this was introduced in is not asserted here.

---

# 9. Typical Symptoms

- Every page load, across the entire site, taking noticeably longer than expected, with no corresponding change in traffic or content, correlating with the point a caching plugin was deactivated, uninstalled, or reconfigured.
- HTTP response headers a specific caching plugin normally adds to indicate a cache hit (commonly a custom header naming the plugin, or an `X-Cache` style header) being absent from every response, where the administrator expects to see them.
- A caching plugin's own admin screen reporting that caching is not currently active, or that `WP_CACHE` is not defined, even though the plugin itself is installed and active.
- The `wp-content/cache/` directory (or the specific caching plugin's own storage location) remaining empty or not growing, despite ordinary site traffic that should be populating it.
- A caching plugin's own admin screen showing a setup or configuration warning (for example, that its own `advanced-cache.php` could not be written or is out of date relative to the plugin's own version).
- A migration or restore that carried over the WordPress database and `wp-content/plugins/` but not the full `wp-content/` tree, or not `wp-config.php`'s own custom constants, leaving `WP_CACHE` undefined or `advanced-cache.php` missing on the new environment.
- Increased database load or an increase in `WP-ERROR-009`/`007`-class conditions correlating with the point page caching stopped functioning, as a downstream consequence rather than this entry's own directly observed symptom.

---

# 10. Common Causes

Causes are grouped by category. Inclusion in this list identifies a category as plausible; it does not assert that any specific one is present without diagnostic confirmation.

- `WP_CACHE` was never defined, or was removed from `wp-config.php` during a manual edit, a migration, or a restore that did not carry the constant forward.
- `advanced-cache.php` was deleted, not carried forward during a migration, or never successfully written in the first place — commonly because the caching plugin's own attempt to write it during setup failed due to a filesystem permission constraint.
- A caching plugin was deactivated or uninstalled without its own cleanup routine correctly removing `WP_CACHE` and `advanced-cache.php`, leaving a stale, non-functional drop-in in place that a later, different caching plugin's own setup did not detect or correctly replace.
- Two caching plugins were installed sequentially without the first being fully deactivated and its own drop-in removed, leaving `advanced-cache.php` in a state that does not match the currently active plugin's own expectations.
- The caching plugin's own internal enable/disable setting is turned off, independent of `WP_CACHE`/`advanced-cache.php` both being present.
- The caching plugin's own storage location has a filesystem permission or capacity constraint preventing cache-file writes, once confirmed as the specific cause (see `WP-ERROR-019`/`020`).
- A staging or development environment's own configuration (for example, a caching plugin deliberately disabled to ease debugging) was inadvertently carried into production, or the reverse.
- A hosting-provider-level caching layer was assumed to be handling page caching, while no WordPress-side mechanism was ever separately configured, leaving a gap once the hosting-level layer changed or was removed.

---

# 11. Diagnosis

Verify the following, starting from the least invasive observation and narrowing only once the general shape of the failure is established:

1. **Confirm page caching is genuinely not active**, as opposed to active but serving stale content (`WP-ERROR-021`/`025`/`027`/`030`'s own territory) — check for the presence and freshness of cache-hit response headers, and inspect the cache storage location directly for recent activity, rather than assuming from performance alone.
2. **Confirm whether `WP_CACHE` is currently defined as `true`** in the active `wp-config.php`, and whether `wp-content/advanced-cache.php` currently exists — the two most basic preconditions, checked directly rather than inferred from a caching plugin's own admin screen alone, since that screen's own reporting can itself be stale or incorrect.
3. **Determine which of the three causes in Section 6 applies**, before investigating any specific underlying mechanism: whether the mechanism was never engaged at all (cause 1), whether it is present but the specific plugin's own setting has it turned off (cause 2), or whether it is engaged but consistently failing to write (cause 3).
4. **Only once the general point of failure is established, investigate the specific mechanism responsible:**
   - Where cause 1 is confirmed, determine whether a caching plugin is even installed and active as an ordinary WordPress plugin at all (distinct from `WP-ERROR-031`'s own condition, which concerns activation itself failing) — a caching plugin that is not active as a plugin will never have written or maintained `WP_CACHE`/`advanced-cache.php` in the first place.
   - Where cause 2 is confirmed, consult the specific caching plugin's own admin settings and documentation directly, since the location and meaning of its own enable/disable toggle varies by plugin.
   - Where cause 3 is confirmed, test writability of the specific cache storage location directly (for example, `wp_is_writable()` via WP-CLI, consistent with `WP-ERROR-019`'s own established diagnostic approach) before evaluating against `WP-ERROR-019`/`020`.
5. Where more than one caching plugin has been installed over time, inventory which one(s) actually wrote the current `advanced-cache.php`, since its own internal signature or version marker commonly identifies its author, rather than assuming it belongs to whichever plugin is currently active.
6. Where a downstream database query timeout (`WP-ERROR-009`) or connection-exhaustion condition (`WP-ERROR-007`) is also present, treat the inactive page cache as a contributing factor to note, not as this entry's own diagnostic target for that condition; hand off to the respective entry's own diagnostic procedure.
7. Preserve relevant evidence — the exact `wp-config.php` and `advanced-cache.php` state, the caching plugin's own settings, and any recent deployment or plugin-management history — before making any change.

---

# 12. Recovery Procedure

Recovery shall target the specific, verified cause identified in Diagnosis (Section 11), not merely reinstall or reactivate a caching plugin without addressing the underlying gap.

Permitted recovery categories, depending on the verified cause, include:

- Restoring `WP_CACHE` in `wp-config.php` and/or `advanced-cache.php` where confirmed missing, using the specific caching plugin's own supported re-setup process rather than manually recreating the drop-in file, since its own internal content is plugin-specific and not portable between plugins.
- Enabling the specific caching plugin's own internal caching setting where confirmed disabled (cause 2).
- Resolving the underlying filesystem permission or capacity constraint per `WP-ERROR-019`'s or `WP-ERROR-020`'s own recovery procedure where confirmed as the cause of a write failure (cause 3), then confirming the cache actually begins populating afterward.
- Fully removing a stale, non-functional `advanced-cache.php` left behind by a previously uninstalled caching plugin, before installing or reconfiguring a replacement, rather than allowing the new plugin's own setup to interact with leftover, mismatched drop-in content.
- Explicitly configuring a WordPress-side page-caching mechanism where diagnosis reveals none was ever actually established, rather than continuing to assume a hosting-level layer alone is sufficient.

Recovery shall not treat reinstalling or reactivating a caching plugin as sufficient on its own without confirming the cache actually begins populating afterward; a plugin can report itself as active while the underlying `WP_CACHE`/`advanced-cache.php` mechanism remains unestablished.

---

# 13. Validation

Recovery is successful when:

- The page-caching mechanism is confirmed actively populating its own cache storage, verified directly (cache-hit response headers present, cache storage location showing recent, growing activity), not only that a caching plugin reports itself as "active."
- `WP_CACHE` is confirmed `true` and `advanced-cache.php` is confirmed present and free of the specific defect diagnosed.
- Where the cause was a filesystem permission or capacity constraint, that underlying condition's own validation criteria (per `WP-ERROR-019`/`020`) are independently satisfied.
- Site-wide page-load performance is confirmed improved under conditions comparable to when the original degradation was observed.
- Any downstream database load or query-timeout pattern that was a consequence of the inactive cache no longer recurs once the cache is confirmed populating.
- No unrelated plugin, configuration, or cached content from a different, no-longer-active caching plugin was left in a conflicting state.

---

# 14. Prevention

- Verify page-caching status explicitly (cache-hit headers, cache storage activity) as part of any migration, restore, or deployment procedure, rather than assuming `wp-config.php` constants and `wp-content/` drop-ins carried forward correctly.
- Fully deactivate and remove a caching plugin's own drop-in before installing a replacement, rather than allowing two caching mechanisms' own files to coexist in an undefined state.
- Document which specific caching plugin, and which specific setting within it, controls whether page caching is actually active, so a future diagnosis does not need to rediscover this for the plugin in current use.
- Include page-caching verification in routine site-health monitoring, rather than discovering an inactive cache only when a traffic spike causes a downstream, more severe condition.
- Where a hosting-provider-level caching layer is relied upon instead of, or in addition to, a WordPress-side mechanism, document that decision explicitly, so its own removal or reconfiguration is recognized as affecting page-caching status.

---

# 15. Security Considerations

- Where a page cache is restored or reconfigured, confirm it correctly excludes personalized or sensitive responses (a logged-in user's own admin-bar-bearing page, a page containing a nonce, a cart or checkout page) from caching, consistent with the exclusion discipline `WP-ERROR-025`/`027` already establish from their own side of this same general concern.
- Do not treat an inactive page cache as solely a performance inconvenience where its absence also removes a mitigating layer against a resource-exhaustion attempt (a deliberately expensive or repeated request pattern) a functioning cache would otherwise have absorbed.
- Verify the source and integrity of any caching plugin, and any `advanced-cache.php` it installs, before restoring it, since this file executes on every single request very early in bootstrap and is a high-value target for a malicious modification.
- Avoid granting overly broad filesystem write permissions to the cache storage location as a shortcut to resolving a write failure; scope the fix to the minimum access the specific caching plugin's own documented requirements call for, per `WP-ERROR-019`'s own established discipline.

---

# 16. Related Errors

The following are cited as they exist in this repository, or as conceptual distinctions where noted.

1. [WP-ERROR-021 — WordPress REST API Route Not Found](WP-ERROR-021-REST-API-ROUTE-NOT-FOUND.md) — exists in this repository; see Section 6 (Distinction) above.
2. [WP-ERROR-025 — WordPress Authentication Cookie Invalid or Expired](WP-ERROR-025-AUTHENTICATION-COOKIE-INVALID-OR-EXPIRED.md) — exists in this repository; see Section 6 (Distinction) above.
3. [WP-ERROR-027 — WordPress Nonce Verification Failure, Non-REST](WP-ERROR-027-NONCE-VERIFICATION-FAILURE-NON-REST.md) — exists in this repository; see Section 6 (Distinction) above.
4. [WP-ERROR-030 — WordPress CORS (Cross-Origin) Policy Failure](WP-ERROR-030-CORS-CROSS-ORIGIN-POLICY-FAILURE.md) — exists in this repository; see Section 6 (Distinction) above.
5. [WP-ERROR-031 — WordPress Plugin Activation Failure](WP-ERROR-031-PLUGIN-ACTIVATION-FAILURE.md) — exists in this repository; see Section 6 (Distinction) above.
6. [WP-ERROR-013 — WordPress Bootstrap PHP Fatal Error](WP-ERROR-013-WORDPRESS-BOOTSTRAP-PHP-FATAL-ERROR.md) — exists in this repository; see Section 6 (Distinction) above.
7. [WP-ERROR-019 — WordPress Filesystem Permission Denied](WP-ERROR-019-FILESYSTEM-PERMISSION-DENIED.md) — exists in this repository; see Section 6 (Distinction) above for the diagnose-then-hand-off relationship.
8. [WP-ERROR-020 — WordPress Disk Space Exhausted](WP-ERROR-020-DISK-SPACE-EXHAUSTED.md) — exists in this repository; see Section 6 (Distinction) above for the diagnose-then-hand-off relationship.
9. [WP-ERROR-033 — WordPress Persistent Object Cache Backend Unavailable](WP-ERROR-033-PERSISTENT-OBJECT-CACHE-BACKEND-UNAVAILABLE.md) — exists in this repository; see Section 6 (Distinction) above.
10. [WP-ERROR-009 — WordPress Database Query Timeout](WP-ERROR-009-DATABASE-QUERY-TIMEOUT.md) — exists in this repository; see Section 6/7 above for the dependency/consequence relationship.
11. [WP-ERROR-035 — WordPress OPcache Stale Bytecode](WP-ERROR-035-OPCACHE-STALE-BYTECODE.md) — a mechanistically distinct caching layer this taxonomy assigns to a separate entry, not further distinguished here since no overlap was identified during drafting.

---

# 17. Notes

This entry documents the general, verified observable condition of WordPress's own full-page-cache mechanism not functioning, distinguishing the three mechanically distinct causes — never engaged, administratively disabled, and unable to write — at which that failure can occur. It is the second entry drafted against `SF-TAXONOMY-006`.

This entry deliberately does not assert any core-level default behavior for page caching, since — unlike the object cache `WP-ERROR-033` documents, where WordPress core provides its own built-in, non-persistent fallback implementation — core provides no page-caching implementation of its own at all; the entire mechanism, once `WP_CACHE` is set, is third-party code. This is a stronger asymmetry than `WP-ERROR-033`'s own evidence-quality correction addressed, and is stated explicitly in Section 4 and Section 6 to avoid the same class of overstatement `SF-REVIEW-096`/`098` each corrected elsewhere in this category.

Consistent with the single-responsibility principle in **SF-SPEC-001** Section 4.3, this entry does not claim ownership of any specific downstream symptom entries already document, the plugin-activation event a caching plugin also depends on, the underlying filesystem condition its own cause 3 may trace to, or a database/connection condition that is a consequence rather than this entry's own condition.

This entry reached `Production Ready` via `SF-REVIEW-099` (Class A author review; no findings) and `SF-REVIEW-100` (Class B independent review; one Minor finding — IF-1, a precision gap in this entry's own "no worse than baseline" impact framing for cause 3, corrected within that same review) per **SF-SPEC-001** Section 19, **SF-SPEC-005** Section 5.6, and **SF-SPEC-012**.

No Reference Implementation is currently designated by **SF-SPEC-001**; this entry's relationship to that designation, and to any future `WP-SCENARIO-XXX` runtime evidence, is not asserted here and shall not be assumed until such evidence or designation actually exists.
