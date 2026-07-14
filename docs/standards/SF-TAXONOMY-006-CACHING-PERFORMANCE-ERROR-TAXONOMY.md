# SF-TAXONOMY-006 — Caching / Performance Error Taxonomy

## Document Information

**Document ID:** SF-TAXONOMY-006

**Title:** Caching / Performance Error Taxonomy

**Classification:** Engineering Taxonomy — a lightweight, informally-adopted planning artifact, governed by **SF-SPEC-013 — Knowledge Category Lifecycle Specification** for its role in the category lifecycle, but not itself templated by any `SF-TEMPLATE-XXX` document (none currently exists for this artifact type). It is not governed by **SF-SPEC-005**'s review process as a knowledge artifact and does not itself require an author/independent review pair, though an independent review of it is planned per this project's established practice (`SF-REVIEW-034`, `045`, `069`, `080`, `089`), not as a normative requirement this document imposes on itself.

**Status:** Frozen — the entry set in Section 3 is fixed until this document is deliberately revised (see Section 6). "Frozen" here is an informal, self-defined term describing this document's own adopted-plan state; it is not a claim of the `Version Frozen` **SF-SPEC-001** Section 18 lifecycle stage, nor of any **SF-SPEC-008** Section 6 Version Status value, nor of the category-level `Baseline Certified` designation **SF-SPEC-013** Section 5.5 defines. This document carries a `Version` and `Revision History` for traceability only; it does not present itself as a "versioned engineering artifact" within **SF-SPEC-008**'s own scope, the same disclaimer `SF-TAXONOMY-001`–`005` make.

**Version:** 1.2

**Owner:** SquirrelForge

---

## 1. Purpose

Declare the `Performance` category's boundary and complete planned `WP-ERROR` entry set *before* any entry in it is authored, per **SF-SPEC-013** Section 5.1's Category Entry Criteria — the fourth candidate in the Knowledge Production Plan's roadmap, and, per that plan's own note, a category bordering `WP-ERROR-009` (Database Query Timeout) closely enough to need an explicit boundary statement of its own.

This document does not itself contain any `WP-ERROR` knowledge content and is not cited as a `WP-ERROR` entry.

---

## 2. Category Boundary

**Performance** (referred to in this project's own informal roadmap as "Caching / Performance") owns failures in WordPress's own caching *mechanisms* — the object-cache API's own connection to a persistent external backend, the full-page-cache drop-in mechanism's own activation and operation, and PHP opcode caching's own invalidation behavior — where the observable failure is that the mechanism itself is not functioning as designed. It does not own the downstream, symptom-specific consequence of a caching layer serving stale or incorrect content where that specific consequence already has its own dedicated owner elsewhere in this catalog.

This distinction is the single most important one this taxonomy makes, and required more research than any prior taxonomy in this catalog to establish accurately: four existing, Production-Ready entries in three other categories already explicitly claim a *specific* caching-related symptom as their own condition, each predating this taxonomy:

- `WP-ERROR-021` (REST API) already names "a caching layer or CDN continuing to serve a stale, previously-cached 404 response" as its own Common Cause, with its own dedicated diagnosis step and recovery action.
- `WP-ERROR-025` (Authentication) already names a caching layer serving one user's `Set-Cookie` header to another as its own condition, explicitly flagged as both a functional defect and a session-hijacking-equivalent security exposure within that entry's own Section 15.
- `WP-ERROR-027` (Authentication) already names "cached markup serving a stale nonce" as its own Common Cause, with its own dedicated diagnosis, recovery, and prevention content.
- `WP-ERROR-030` (Networking) already names a caching layer replaying stale CORS headers computed for a different origin as its own Common Cause, with its own dedicated diagnosis step and recovery action.

None of these four entries defers to, or forward-references, a future Caching/Performance entry — each documents its own caching-related cause completely, using the same principle this catalog has applied consistently elsewhere (Database versus Networking's shared DNS/timeout mechanisms, distinguished by *which connection* failed, not merged into one mega-entry): staleness affecting a specific, already-owned symptom belongs to the entry that owns that symptom, not to a generic caching entry, regardless of whether a caching layer is the shared underlying mechanism across all four.

**Explicitly not owned by Performance:**

* **A caching layer serving a stale REST API 404 response** — already fully owned by `WP-ERROR-021`. See above.
* **A caching layer serving one user's authentication cookie to another** — already fully owned by `WP-ERROR-025`, including that entry's own security-incident-level treatment. See above.
* **A caching layer serving a stale, expired, or wrong-user nonce embedded in cached markup** — already fully owned by `WP-ERROR-027`. See above.
* **A caching layer replaying stale CORS headers computed for a different origin** — already fully owned by `WP-ERROR-030`. See above.
* **Database query performance itself** — `WP-ERROR-009` (Database Query Timeout) owns a specific query failing to complete within an applicable time limit, regardless of whether caching could have avoided the need for that query in the first place. This category presumes a query was actually issued and is diagnosed on its own terms by `WP-ERROR-009`; it does not re-litigate query performance from the caching side. Where a caching mechanism this category owns (the object-cache backend, specifically) is itself unavailable and that unavailability is the *reason* a query had to be issued at all, `WP-ERROR-009` remains the entry documenting that specific query's own timeout, while this category documents the object-cache backend's own unavailability as a distinct, separately diagnosable condition — the two are complementary, not competing, per Section 4.
* **Disk-backed cache capacity or permission conditions** — `WP-ERROR-019` (Filesystem Permission Denied) and `WP-ERROR-020` (Disk Space Exhausted) already explicitly name disk-backed caching (a page-cache or object-cache plugin persisting entries to the filesystem) as a contributor to their own respective conditions. This category does not claim the OS-level access or capacity dimension of a disk-backed cache; it owns whether the caching mechanism itself is functioning, handing off to `WP-ERROR-019`/`020` where a specific cache-write failure is confirmed to be a permission or capacity constraint rather than a mechanism-level defect.
* **`wp_options` autoloaded-data bloat, including from the Transients API's own database fallback** — already named as a specific cause within `WP-ERROR-009`'s own Common Causes. This category does not re-claim the query-performance consequence of transient data accumulating in the database; it owns the Transients API's own functional behavior (whether a value can be stored and retrieved at all), not the downstream query-performance effect of how much data has accumulated.
* **A PHP fatal error during ordinary bootstrap, even where a drop-in (`advanced-cache.php`, `object-cache.php`) is the proximate cause** — `WP-ERROR-013` (Bootstrap PHP Fatal Error) already explicitly owns fatal errors originating from drop-in files during bootstrap. This category presumes the caching mechanism loads without a fatal error and its own condition is a functional or performance one, not a bootstrap-terminating one; where a caching drop-in itself fatals during bootstrap, that remains `WP-ERROR-013`'s condition (a cause-specific split, per that entry's own Section 17 disclaimer, remains possible in principle but is not part of this taxonomy's initial planned set).
* **A missing PHP extension or unsupported PHP version affecting a caching backend's own client library** (for example, the `redis` or `memcached` PHP extension being unavailable) — PHP Runtime category (`WP-ERROR-014`/`015`), once verified as the underlying cause; this category owns the caching mechanism's own observable unavailability, not the underlying PHP-runtime condition once correctly identified.
* **A specific caching plugin's own business-logic defect** (an incorrect cache-key generation scheme, a plugin-specific bug unrelated to the general mechanism) — Plugin category, per the same reasoning `SF-TAXONOMY-005` Section 2 already establishes for "a specific plugin's own implementation defect" generally.
* **CDN-level or reverse-proxy-level caching configuration and behavior, independent of any WordPress-side mechanism** — considered and deferred; see Section 5.

---

## 3. Planned Entries

| Entry | Title | Owns | Status |
|---|---|---|---|
| `WP-ERROR-033` | WordPress Persistent Object Cache Backend Unavailable | A verified condition in which WordPress is configured to use a persistent, external object-cache backend (for example, Redis or Memcached, via an `object-cache.php` drop-in), and that backend becomes unreachable or fails to respond. The observable consequence depends on how the specific drop-in in use handles that failure: a well-behaved drop-in catches the connection failure and falls back to WordPress's own built-in, non-persistent, single-request-only object cache — a functional performance degradation (every request now misses the persistent cache) rather than a fatal error — while a drop-in that does not handle the failure gracefully can instead produce a PHP fatal error or a hang. This entry owns the verified condition that the configured backend is unreachable, covering both observable consequences, rather than presuming WordPress core itself guarantees graceful degradation — no such guarantee exists at the core level, since core's own built-in object cache has no external backend to begin with; graceful fallback, where it occurs, is the specific drop-in's own behavior. | Existing, Production Ready |
| `WP-ERROR-034` | WordPress Page Cache Not Active | A verified condition in which WordPress's own full-page-caching mechanism (the `WP_CACHE` constant and the `advanced-cache.php` drop-in) is configured but not actually functioning — the drop-in is missing, fails to load, or never populates a cache — resulting in every request being served without page-level caching at all, a performance-degradation condition distinct from a functioning cache serving stale content (which is `WP-ERROR-021`/`025`/`027`/`030`'s own territory, per Section 2, depending on which symptom the staleness manifests through). | Existing, Production Ready |
| `WP-ERROR-035` | WordPress OPcache Stale Bytecode | A verified condition in which PHP's opcode cache (OPcache) continues serving previously-compiled bytecode for a file whose source has since changed — commonly following a plugin, theme, or core update — because OPcache's own invalidation (`opcache.validate_timestamps`, `opcache.revalidate_freq`, or a manual reset) did not occur, causing WordPress to execute logic that no longer matches the deployed source files on disk. | Planned |

Nothing else is currently planned for this category. Any future addition to this table is a revision to this document, not an ad hoc decision made while authoring an unrelated entry, per **SF-SPEC-013** Section 5.7.

---

## 4. Ownership Model

The three entries divide by **caching mechanism**, not by a pipeline or a shared precondition chain:

* `WP-ERROR-033` owns the **object-cache backend connectivity** mechanism — a failed external persistence layer and the specific drop-in's own behavior (graceful degradation or not) in response.
* `WP-ERROR-034` owns the **full-page-cache activation** mechanism — whether the caching layer is actually operating at all, as opposed to operating but serving wrong content.
* `WP-ERROR-035` owns the **opcode-cache invalidation** mechanism — a PHP-level, not a WordPress-application-level, caching concern, but one this category claims because its symptoms (stale application behavior following a deployment) are diagnostically adjacent to the other two and commonly confused with them.

These three are **independent mechanisms with no shared precondition**: a persistent object-cache backend being unreachable, a page cache never populating, and OPcache serving stale bytecode can each occur with or without either of the other two present, and diagnosing one does not presuppose ruling out the others first, unlike `SF-TAXONOMY-004`'s own sequential `WP-ERROR-028`/`029` pair.

**Evidentiary basis for the category's own narrow scope:** unlike every prior category in this catalog, this one's boundary was constrained primarily by *existing* claims rather than by first-principles design — four Production-Ready entries in three other categories already fully document the specific, symptom-level manifestation of caching-related staleness within their own domains (Section 2). This taxonomy's own three entries were selected specifically because they document conditions none of those four, nor any other existing entry, currently claims: the caching *mechanisms'* own operational state (connected/not, active/not, valid/stale), independent of any specific downstream content served incorrectly as a result.

---

## 5. Candidates Considered and Rejected

* **A generic "Full-Page Cache Serving Stale Content" entry:** not given an entry. This is precisely the territory `WP-ERROR-021`/`025`/`027`/`030` already divide among themselves by the specific symptom staleness manifests through (a stale REST 404, a leaked auth cookie, a stale nonce, stale CORS headers). A generic entry attempting to own "stale cache" in the abstract would either duplicate those four entries' own content or create an ownership ambiguity between itself and each of them for every future stale-cache symptom discovered — the same class of ambiguity this taxonomy's own research specifically set out to avoid, per `SF-TAXONOMY-005`'s own recent experience correcting a real, undetected overlap (`SF-REVIEW-089`'s scope gap, disclosed in `FRAMEWORK-OBSERVATIONS.md`).
* **A "Transients API Failure" entry, separate from Object Cache Backend Unavailable:** not given a separate entry. The Transients API's own storage mechanism, when no persistent external object cache is configured, is the `wp_options` table itself — a condition already substantially covered by `WP-ERROR-009` (query performance) and general database-layer entries once a transient-backed query is slow or fails; when a persistent object cache *is* configured, transients are stored through the same object-cache API `WP-ERROR-033` already owns. No conceptually independent failure mode was identified that both of those do not already cover between them.
* **A dedicated "CDN Configuration Failure" entry:** not given an entry. A CDN operates largely outside WordPress's own code and configuration surface; where a CDN's own caching behavior affects a specific WordPress-owned symptom, `WP-ERROR-021`/`025`/`027`/`030` already treat "a caching layer or CDN" as a single, undifferentiated cause category rather than distinguishing WordPress-side caching from CDN-side caching. Introducing a CDN-specific entry would require re-litigating that already-settled boundary in four other entries; deferred rather than rejected outright, should CDN-specific conditions distinct from generic caching staleness prove common enough to warrant it.
* **A "Cache Stampede / Thundering Herd" entry:** considered and deferred. A cache stampede (many concurrent requests simultaneously regenerating the same expired cache entry, overwhelming the database or origin) is a genuine, distinct performance condition, but is more naturally a *consequence* of `WP-ERROR-033`'s or `WP-ERROR-034`'s own condition (the cache not being available or not being active) than an independent mechanism — deferred as a potential future cause-specific split from one of those two entries rather than planned as its own entry now, consistent with this catalog's general practice of not pre-emptively splitting an unconfirmed cause out of a broader entry.
* **A "Heartbeat API Performance Impact" entry:** not given an entry. The Heartbeat API's own request frequency can be a genuine performance concern on some installations, but it is a scheduled-polling behavior WordPress's own admin/editor interface uses, not a caching mechanism at all, and no evidence was found that it shares this category's own boundary rather than belonging to a future, more general "admin-area performance" scope this taxonomy does not attempt to define.

---

## 6. Revision History

| Version | Date | Summary of Changes | Status |
|---|---|---|---|
| 1.0 | 2026-07-14 | Initial taxonomy. Establishes that four existing, Production-Ready entries (`WP-ERROR-021`, `025`, `027`, `030`) already fully own the specific, symptom-level manifestation of caching-related staleness within their own respective domains, and that this category's own territory is accordingly narrow: the caching *mechanisms'* own operational state, not any specific downstream content served incorrectly as a result. Plans three entries — `WP-ERROR-033` (Persistent Object Cache Backend Unavailable), `WP-ERROR-034` (Page Cache Not Active), `WP-ERROR-035` (OPcache Stale Bytecode) — dividing the category by caching mechanism rather than a pipeline or symptom-based model. A generic "stale cache" entry, a separate Transients API entry, a CDN-specific entry, a cache-stampede entry, and a Heartbeat API entry each considered and deferred or rejected, per Section 5. | Frozen |
| 1.1 | 2026-07-14 | WP-ERROR-033 reached Production Ready (SF-REVIEW-097 author review, one Minor structural finding corrected; SF-REVIEW-098 independent review, which corrected a Redis eviction-policy precision issue within the entry itself and a cross-document completeness gap in WP-ERROR-009's own Common Causes list, rather than finding a defect in this entry's own boundary or in this taxonomy). Status column updated from Planned to Existing, Production Ready. No boundary content changed; this entry required no revision to this taxonomy — the first direct evidence that the proactive cross-category ownership sweep performed during this taxonomy's own drafting (Version 1.0) prevented the class of defect WP-ERROR-032's own production cycle exposed in SF-TAXONOMY-005. | Frozen |
| 1.2 | 2026-07-14 | WP-ERROR-034 reached Production Ready (SF-REVIEW-099 author review, no findings; SF-REVIEW-100 independent review, which corrected a precision gap in the entry's own "no worse than baseline" impact framing for its own cause 3, rather than finding a defect in this taxonomy). Status column updated from Planned to Existing, Production Ready. No boundary content changed; this entry required no revision to this taxonomy — the second consecutive clean pass in this category, matching the project owner's own stated evidentiary bar. | Frozen |
