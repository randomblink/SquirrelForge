# WP-ERROR-045 — WordPress Multisite Site Resolution Failure

---

# 1. Knowledge Entry

WordPress Multisite Site Resolution Failure

---

# 2. Metadata

* **Error ID:** `WP-ERROR-045`
* **Title:** WordPress Multisite Site Resolution Failure
* **Category:** Multisite
* **Severity:** Critical
* **Recovery Priority:** Immediate
* **Status:** Production Ready
* **Version:** 1.0

---

# 3. Summary

On a WordPress Multisite installation, the mechanism that determines which specific site within the network an incoming request belongs to — evaluated from the request's own Host header and path, before any site-specific theme or plugin code runs — either fails to resolve any site at all, or resolves to a site other than the one the request genuinely targets. The second manifestation is the more consequential of the two: it can silently serve, or expose an administrative context for, an unintended site, a genuine data-isolation concern on any network hosting more than one independent tenant.

---

# 4. Primary Failure Mode

A request arrives at a Multisite installation carrying a specific Host header and path. Before WordPress determines a theme, loads site-specific plugins, or begins ordinary request processing, its own site-resolution mechanism — core's own `get_site_by_path()` lookup against the network's registered sites, optionally preceded and overridden by a loaded `sunrise.php` drop-in implementing custom domain-mapping logic — attempts to match the request to exactly one registered site. This entry's own condition occurs when that resolution either finds no match at all (producing WordPress's own explicit refusal, or, where a redirect is configured for this case, a redirect that can obscure the underlying failure) or matches a site other than the one the request's own Host header and path genuinely indicate.

---

# 5. Severity

This entry is classified **Critical**, with impact ranging depending on which manifestation occurs:

- Where no site resolves at all, the impact is immediate and visible: the requested site becomes entirely inaccessible via its intended URL, a clear, obvious failure an administrator or visitor would notice immediately.
- Where the *wrong* site resolves, the impact is more severe precisely because it is silent: a visitor, or even an administrator attempting to manage what they believe is a specific site, can be served content, or granted an administrative context, belonging to an entirely different site in the network — with no error of any kind indicating anything is wrong. On any network hosting genuinely independent tenants (separate customers, departments, or organizations expecting isolation from one another), this is a data-isolation and confidentiality concern, not merely a functional one.
- This entry remains classified at the level of its most severe possible manifestation, consistent with the range-based Critical classification used elsewhere in this catalog (`WP-ERROR-021`, `024`–`030`, `032`–`033`, `035`, `038`–`040`, `042`–`044`).

---

# 6. Distinction

This entry applies only when verified evidence establishes that WordPress's own site-resolution mechanism itself — not any condition presuming a site has already been correctly resolved — either failed to resolve a site at all or resolved to the wrong one.

**Two manifestations this entry keeps deliberately together, on the same reasoning `WP-ERROR-043` Section 6 already established for its own four consolidated causes:** both "no site resolved" and "wrong site resolved" stem from the same underlying mechanism (the Host-header-and-path lookup, potentially intercepted by `sunrise.php`), and diagnosing either requires examining the same set of causes — registered domain/path data, `sunrise.php`'s own logic, and any intermediate proxy or DNS configuration affecting what Host header WordPress actually receives. Splitting the two into separate entries would separate two symptoms of the same investigation rather than reflect a genuine difference in mechanism.

It is distinct from:

- **`WP-ERROR-004`/`005` — Database Permission Denied / Schema Missing or Incomplete**: own a new site's own creation failing — either a `CREATE TABLE` privilege failure or the new site's own per-site tables never being fully created. This entry presumes the site in question is already fully and correctly created and registered; its own condition concerns a request failing to be *routed* to an existing, correctly-created site, not a site that was never successfully created in the first place.
- **`WP-ERROR-006` — Database Table Corruption**: owns corruption of a specific site's own per-site tables, or of the network's own shared global tables, once a request has already been correctly routed to that site. This entry's own condition can, in one of its causes, involve corrupted or inconsistent `wp_blogs`/`wp_site` *registration* data specifically — the network's own site-directory data determining which site a request maps to — which is a narrower, resolution-specific case of `WP-ERROR-006`'s own broader condition; diagnosis confirming general table corruption unrelated to resolution itself remains `WP-ERROR-006`'s own territory.
- **`WP-ERROR-024` — WordPress Login Authentication Failure**: owns a user's own login attempt failing because their account does not exist on the specific site they are *attempting* to access, or is flagged as spam — a condition that presumes the site itself was correctly resolved and the user simply chose, or was directed to, the wrong site's own login form. This entry's own condition is the resolution mechanism itself routing a request incorrectly, independent of any user's own login attempt.
- **`WP-ERROR-026` — Capability or Role Authorization Denied**: owns a user's capability or site-membership resolution being incorrect *once the correct site has already been resolved* — this entry's own condition means the site itself was mis-resolved before any capability check would even become relevant.
- **`WP-ERROR-042` — WP-CLI Multisite Site Context Resolution Failure**: owns WP-CLI's own failure to supply a valid site-targeting input (a missing or incorrect `--url`), since WP-CLI has no HTTP Host header to derive one from automatically. That entry explicitly hands off to this one once diagnosis confirms the underlying issue is a genuine defect in Multisite's own resolution mechanism itself, rather than merely an absent or incorrect `--url` value.
- **A network-activated plugin's own fatal error**: already fully owned by `WP-ERROR-031`/`WP-ERROR-013` as applicable, since network activation reuses the identical, ordinary plugin-activation mechanism those entries already own, per `SF-TAXONOMY-011` Section 2.

---

# 7. Scope

**Covered:** A verified condition in which WordPress's own Multisite site-resolution mechanism, evaluated for a genuine HTTP request, either fails to resolve any registered site at all, or resolves to a site other than the one the request's own Host header and path genuinely indicate — due to missing, incorrect, or duplicate/overlapping site registration data, a `sunrise.php` defect or misconfiguration, or a mismatch between the Host header WordPress actually receives and what is registered.

**Excluded:**

- A new site's own creation failing at the database-permission or schema-creation stage (`WP-ERROR-004`/`005`).
- General corruption of a specific site's own per-site tables, or of network-wide global tables, unrelated to site-resolution data specifically (`WP-ERROR-006`).
- A user's own login attempt failing on a correctly-resolved site, including a user's own mistaken choice of site (`WP-ERROR-024`).
- A user's capability or site-membership resolution being incorrect once the correct site has already been resolved (`WP-ERROR-026`).
- WP-CLI's own failure to supply a valid `--url` site-targeting input (`WP-ERROR-042`, which hands off here only once a genuine resolution-mechanism defect is confirmed).
- A network-activated plugin's own fatal error, which uses the ordinary plugin-activation mechanism.

---

# 8. WordPress Components

Listed as components commonly involved, not as a claim that every installation exercises every one of them:

- `ms_site_check()` (`wp-includes/ms-site.php`), invoked early in the bootstrap sequence (via `ms_load_current_site_and_network()`) to determine the current network and site before ordinary request processing begins.
- `get_site_by_path()`, the core lookup function matching a request's domain and path against registered sites' own `domain`/`path` columns.
- The `wp_blogs`/`wp_site` tables, the network's own central site-registration data this lookup is performed against.
- `sunrise.php`, a drop-in loaded when the `SUNRISE` constant is defined `true` in `wp-config.php`, loaded before core's own resolution logic runs specifically so it can implement custom domain-mapping behavior that overrides or supplements the default `wp_blogs`-based lookup.
- The `NOBLOGREDIRECT` constant, which, when defined, redirects a request that resolves to no registered site elsewhere rather than presenting WordPress's own default "site not found" refusal — a configuration that can make a genuine resolution failure look, superficially, like expected behavior.
- Any intermediate reverse proxy, load balancer, or CDN sitting in front of WordPress, which can alter the Host header WordPress itself actually receives relative to what a visitor's browser originally sent.

---

# 9. Typical Symptoms

- WordPress's own network-level refusal (a message indicating the requested site does not exist), distinct from an ordinary themed error page, since it occurs before any theme has been determined for the request.
- A visitor being redirected to an unexpected destination, where `NOBLOGREDIRECT` is configured, without any error being shown at all.
- A visitor, or an administrator attempting to manage a specific site, instead seeing content, branding, or an administrative dashboard belonging to a different site in the same network.
- An administrator's own change or content update, intended for one site, appearing to have no effect on that site — because it was actually applied to a different, incorrectly-resolved site instead.
- The condition appearing only for a specific domain or subset of domains in the network, while other sites in the same network continue to resolve correctly.

---

# 10. Common Causes

Causes are grouped by category. Inclusion in this list identifies a category as plausible; it does not assert that any specific cause is present without diagnostic confirmation.

- A newly-added or newly-mapped domain not yet correctly registered in the network's own `wp_blogs`/`wp_site` data.
- A bug or an outdated internal mapping table or cache within a custom `sunrise.php`, causing it to resolve a domain to the wrong site.
- `sunrise.php` failing to load at all despite `SUNRISE` being defined `true` (a typo in the expected file location, or the file itself missing), causing WordPress to fall back to plain `wp_blogs`-based lookup for a domain a site owner expected `sunrise.php`'s own override to handle.
- Corrupted or duplicate `wp_blogs`/`wp_site` rows, where two or more registrations unintentionally overlap on domain and path.
- A DNS change, or a reverse proxy/CDN configuration change, altering the Host header WordPress actually receives so it no longer matches what is registered, even though the visitor's own original request was correctly addressed.
- A site's own domain or path changed at the Network Admin level without a corresponding update to external DNS or reverse-proxy configuration, or vice versa.

---

# 11. Diagnosis

Verify the following, starting from the least invasive observation and narrowing only once the general shape of the failure is established:

1. **Confirm the exact domain and path involved**, and whether the condition is a total resolution failure (no site at all) or a wrong-site resolution, since the two point toward different starting points.
2. **Directly query `wp_blogs`/`wp_site` for the domain and path in question**, confirming exactly one, correctly-configured matching registration exists — not merely assuming registration is correct because the site was previously working.
3. **Check for duplicate or overlapping domain/path registrations** across the network's own site list, which can cause resolution to nondeterministically favor the wrong entry.
4. **Confirm whether `SUNRISE` is defined and `sunrise.php` actually exists at its expected location and loads without error** — where it is expected to be in use, its absence or failure to load silently falls back to plain core resolution, which may not match a domain-mapped site's own intended behavior.
5. **Where `sunrise.php` is confirmed loading, examine its own mapping logic or cache directly**, isolating it from core's own `wp_blogs` lookup to determine which layer is responsible for an incorrect result.
6. **Verify the actual Host header WordPress itself receives**, not merely what a visitor's browser originally sent, where a reverse proxy, load balancer, or CDN sits in front of the installation — a mismatch introduced at that layer can mimic a resolution-mechanism defect that does not actually exist in WordPress's own configuration.
7. **Check whether `NOBLOGREDIRECT` is configured**, since a resolution failure masked by a redirect can appear to be "working," just incorrectly, rather than visibly failing.
8. **Test resolution directly, bypassing any client-side or intermediate caching**, to rule out a stale cached response masking or mimicking a resolution problem that has already been corrected, or vice versa.

---

# 12. Recovery Procedure

Recovery shall target the specific, verified cause identified in Diagnosis (Section 11).

Permitted recovery categories, depending on the verified cause, include:

- Adding or correcting the affected domain/path's own registration in `wp_blogs`/`wp_site` directly.
- Correcting a `sunrise.php` defect, or its own outdated mapping data or cache, where it is confirmed as the specific responsible layer.
- Restoring `sunrise.php` to its expected, loadable location where it was found missing or misplaced despite `SUNRISE` being defined.
- Removing or correcting a duplicate or overlapping site registration, retaining only the single, correct entry for the affected domain and path.
- Aligning DNS or reverse-proxy/CDN configuration with the network's own registered domain data, or updating the network's own registration to match an intentional DNS or proxy change, whichever reflects the actual intended configuration.
- Clearing any relevant object or site-lookup caching after applying a correction, since a stale cached resolution result can otherwise persist the incorrect behavior even after the underlying registration data is fixed.

---

# 13. Validation

Recovery is successful when:

- The specific domain and path in question resolve consistently to the correct, intended site across repeated, cache-busted requests — not merely once.
- No other, previously-working site in the network was inadvertently affected by the correction, particularly where the fix involved removing a duplicate registration or modifying `sunrise.php`'s own shared logic.
- Where the cause involved a reverse proxy, CDN, or DNS layer, the corrected Host-header behavior is confirmed at that layer directly, not only inferred from WordPress's own subsequent behavior.
- Where a wrong-site resolution was involved, any content or administrative action mistakenly applied to the unintended site during the failure window is identified and, where appropriate, corrected or reverted.

---

# 14. Prevention

- Treat DNS or reverse-proxy/CDN Host-header configuration and the network's own `wp_blogs`/`wp_site` registration as a single, synchronized change-management unit — a change to one without the corresponding change to the other is a common, avoidable cause of this entry's own condition.
- Test any change to `sunrise.php` in a staging environment before deploying it to a production network, given its own role running before ordinary request processing and its potential to affect every site in the network simultaneously.
- Periodically audit the network's own site registrations for duplicate or overlapping domain/path combinations, particularly on a large or fast-growing network.
- Where `NOBLOGREDIRECT` is configured, periodically verify it is not silently masking a genuine resolution failure that would otherwise be visibly obvious.

---

# 15. Security Considerations

- A wrong-site resolution silently exposing one tenant's own content or administrative context to another is a genuine data-isolation and confidentiality concern on any network hosting independent tenants, and shall be investigated and remediated with the same urgency this catalog applies to any other unintended cross-tenant exposure, echoing the precedent `WP-ERROR-026` Section 15 already establishes for unexpected cross-site access.
- Do not treat a `NOBLOGREDIRECT`-masked resolution failure as benign simply because it produces no visible error; verify what is actually being served for an unmatched request, since a permissive fallback destination could itself expose unintended content.
- Verify the integrity and provenance of any custom `sunrise.php` in use, particularly on a network where it was not authored or is not actively maintained by the current site owner, since it runs before nearly all of WordPress's own security-relevant initialization and has correspondingly broad influence over which site's content or context is ultimately served.

---

# 16. Related Errors

The following are cited as they exist in this repository, or as conceptual distinctions where noted.

1. [WP-ERROR-004 — WordPress Database Permission Denied](WP-ERROR-004-DATABASE-PERMISSION-DENIED.md) — exists in this repository; see Section 6 (Distinction) above.
2. [WP-ERROR-005 — WordPress Database Schema Missing or Incomplete](WP-ERROR-005-DATABASE-SCHEMA-MISSING-OR-INCOMPLETE.md) — exists in this repository; see Section 6 (Distinction) above.
3. [WP-ERROR-006 — WordPress Database Table Corruption](WP-ERROR-006-DATABASE-TABLE-CORRUPTION.md) — exists in this repository; see Section 6 (Distinction) above.
4. [WP-ERROR-024 — WordPress Login Authentication Failure](WP-ERROR-024-WORDPRESS-LOGIN-AUTHENTICATION-FAILURE.md) — exists in this repository; see Section 6 (Distinction) above.
5. [WP-ERROR-026 — WordPress Capability or Role Authorization Denied](WP-ERROR-026-CAPABILITY-OR-ROLE-AUTHORIZATION-DENIED.md) — exists in this repository; see Section 6 (Distinction) above.
6. [WP-ERROR-042 — WP-CLI Multisite Site Context Resolution Failure](WP-ERROR-042-WP-CLI-MULTISITE-SITE-CONTEXT-RESOLUTION-FAILURE.md) — exists in this repository; see Section 6 (Distinction) above for the reciprocal hand-off relationship.

---

# 17. Notes

This entry documents the general, verified observable condition of WordPress's own Multisite site-resolution mechanism failing to route a request to the correct site, deliberately keeping its two manifestations — no site resolved, and the wrong site resolved — together on the same consolidation precedent `WP-ERROR-043` already established for this catalog. It is the sole planned entry in the Multisite category, per `SF-TAXONOMY-011` Section 3, resolving the territory `WP-ERROR-042` explicitly reserved.

Consistent with the single-responsibility principle in **SF-SPEC-001** Section 4.3, this entry does not claim ownership of site creation's own database-level failure modes (`WP-ERROR-004`/`005`), general per-site or network-wide table corruption unrelated to resolution data specifically (`WP-ERROR-006`), or a user's own capability/membership resolution once the correct site has already been determined (`WP-ERROR-026`).

This entry's relationship to a `Production Ready` designation is established via `SF-REVIEW-136` (Class A author review) and `SF-REVIEW-137` (Class B independent review) per **SF-SPEC-001** Section 19, **SF-SPEC-005** Section 5.6, and **SF-SPEC-012**.

No Reference Implementation is currently designated by **SF-SPEC-001**; this entry's relationship to that designation, and to any future `WP-SCENARIO-XXX` runtime evidence, is not asserted here and shall not be assumed until such evidence or designation actually exists.
