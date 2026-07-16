# WP-ERROR-035 — WordPress OPcache Stale Bytecode

---

# 1. Knowledge Entry

WordPress OPcache Stale Bytecode

---

# 2. Metadata

* **Error ID:** `WP-ERROR-035`
* **Title:** WordPress OPcache Stale Bytecode
* **Category:** Performance
* **Severity:** Critical
* **Recovery Priority:** Immediate
* **Status:** Production Ready
* **Version:** 1.0

---

# 3. Summary

PHP's opcode cache (OPcache) continues executing a previously-compiled version of one or more PHP files after their source content on disk has genuinely changed — commonly following a plugin, theme, or core update — because OPcache's own invalidation did not occur for the affected process or server, causing WordPress to run logic that no longer matches, and in some cases is directly incompatible with, the deployed source files.

---

# 4. Primary Failure Mode

OPcache is a PHP-runtime-level mechanism, entirely independent of WordPress: it stores compiled bytecode for a PHP file in shared memory the first time that file executes, and, depending on its own configuration, either checks the file's modification time on some later schedule to decide whether to recompile, or never checks at all once compiled. This entry's condition occurs when the source files on disk have genuinely and correctly changed — this entry presumes the deployment or file change itself succeeded — but one or more PHP processes continue executing bytecode compiled from the *previous* version of those files, because OPcache's own configuration or its own per-process/per-server nature prevented invalidation from happening, or from happening consistently, everywhere it needed to.

---

# 5. Severity

This entry is classified **Critical**, reasoned from the mechanism's own worst-case behavior rather than inherited from either sibling entry in this category:

- At its narrowest, this condition means a deployed change (a bug fix, a content change in code, a new feature) silently fails to take effect for some or all requests, while appearing correctly deployed by every other measure (the files on disk are correct; version numbers, changelogs, and deployment logs all show success) — already a serious condition where the deployed change was a security or correctness fix, since the vulnerability or defect remains live in production despite every visible signal indicating it was resolved.
- At its most severe, a partial or inconsistent invalidation — where some files a request depends on have been recompiled from their new versions and others have not — can produce a genuine PHP fatal error: newly-deployed code in one file calling a function, method, or class newly introduced in another file, while OPcache is still serving that second file's own pre-deployment bytecode, which does not yet define it. This is not a hypothetical edge case; it is a direct, structural consequence of OPcache operating per-file rather than atomically across an entire deployment.
- This entry remains classified at the level of its most severe possible manifestation, consistent with the range-based Critical classification used elsewhere in this catalog (`WP-ERROR-021`, `024`–`033`) — deliberately not following `WP-ERROR-034`'s own High/High exception, since this entry's own worst case genuinely can include a fatal error arising directly from the mechanism itself, not merely a performance degradation.

---

# 6. Distinction

This entry applies only when verified evidence establishes that PHP source files on disk have genuinely changed, that the change itself was correctly and completely applied, and that PHP is nonetheless executing bytecode compiled from the file's own prior content — not that the deployment itself was incomplete, that a different caching layer is serving stale content, or that OPcache is unavailable rather than simply stale.

**This entry caches compiled code, not application data — a distinction worth stating explicitly since every other entry in this category, and several entries in other categories, concern data or content staleness instead:**

- `WP-ERROR-021`/`025`/`027`/`030` each concern a caching layer serving stale *rendered output or headers* (a REST response, a cookie, a nonce, CORS headers) to a browser or API client.
- `WP-ERROR-033` concerns a persistent *object-cache backend* — arbitrary data values an application explicitly stores and retrieves.
- `WP-ERROR-034` concerns a *full-page cache* — rendered HTML output stored to avoid re-executing PHP at all.
- This entry concerns none of those. It concerns PHP's own compiled representation of the *source code itself* — the executable logic, not any data that logic produces or consumes. A site can have this entry's own condition while every data-caching layer above is functioning perfectly, and the reverse.

**The boundary against `WP-ERROR-032` (Plugin Update Failure) is the second most important this entry draws, and the two conditions are easily conflated from a symptom alone — both present as "the update doesn't seem to have taken effect."** `WP-ERROR-032` owns the update *mechanism's* own process: whether the new files were correctly acquired and written to disk at all. This entry presumes that process already succeeded completely — the files on disk are correct, current, and internally consistent — and its own condition begins only where `WP-ERROR-032`'s own ends: PHP's own execution of those already-correct files does not yet reflect them. Diagnosis (Section 11) shall confirm the files on disk are actually correct *before* concluding this entry, rather than this one, applies.

**Three internal causes this entry keeps deliberately separate:**

1. **Invalidation disabled with no compensating reset** — `opcache.validate_timestamps` is configured `off` (a common, deliberate production-hardening setting, since it removes the per-request cost of checking every file's modification time), and the deployment process that changed the source files did not separately reset or restart the affected PHP process(es) as this setting requires it to.
2. **A revalidation-window race** — `opcache.validate_timestamps` is enabled, but `opcache.revalidate_freq`'s own configured interval means a file changed very recently has not yet been re-checked, a narrower and more transient version of the same underlying gap.
3. **Per-process or per-server inconsistency** — OPcache's own shared memory is scoped to a single PHP-FPM worker pool or a single server; a reset or restart performed on one process or server does not affect any other, so a multi-worker or multi-server/load-balanced deployment can serve a mix of pre- and post-deployment bytecode simultaneously, from different requests, until every process or server has independently been cycled or reset.

It is distinct from:

- **`WP-ERROR-032` — WordPress Plugin Update Failure**: owns the update mechanism's own process completing correctly. This entry presumes it did. See the boundary statement above.
- **`WP-ERROR-014` — Required PHP Extension Missing**: owns the condition of OPcache itself being unavailable — the extension not loaded, or disabled entirely — a categorical capability question. This entry presumes OPcache is present and actively functioning; its own condition is that OPcache is doing exactly what it is configured to do, and what it is configured to do does not include invalidating a specific, now-stale entry.
- **`WP-ERROR-013` — WordPress Bootstrap PHP Fatal Error**: where this entry's own worst-case manifestation (Section 5) produces a fatal error, `WP-ERROR-013`'s own diagnostic procedure for capturing and locating a PHP fatal error remains the correct starting point for that specific symptom; this entry owns identifying *why* the fatal error occurred (stale, inconsistent bytecode) once `WP-ERROR-013`'s own procedure has located it, not the general fatal-error-capture process itself.
- **`WP-ERROR-021`/`025`/`027`/`030`/`033`/`034`**: each concerns a data- or content-caching layer, not compiled code. See the boundary statement above.
- **A specific plugin's own business-logic defect that a deployment correctly introduced** (Plugin category): this entry presumes the newly-deployed code is itself correct; its own condition is that PHP is not yet executing that correct code at all.

---

# 7. Scope

**Covered:** A verified condition in which PHP source files have genuinely and completely changed on disk, and one or more PHP processes continue executing OPcache-compiled bytecode reflecting the file's own prior content, due to OPcache's own invalidation configuration or its own per-process/per-server scope — regardless of whether the observable consequence is silently-ineffective deployed logic or a direct PHP fatal error from cross-file inconsistency.

**Excluded:**

- An update mechanism that did not correctly or completely write the new files to disk in the first place (`WP-ERROR-032`).
- OPcache being unavailable, disabled entirely, or the extension missing (`WP-ERROR-014`).
- A PHP fatal error whose own capture and location, as opposed to its root cause once located, is this entry's own condition (`WP-ERROR-013`).
- Any data- or content-caching layer — a REST response, an authentication cookie, a nonce, CORS headers, an object-cache value, or a full-page-cached response (`WP-ERROR-021`/`025`/`027`/`030`/`033`/`034`).
- A specific plugin's own business-logic defect in newly, correctly deployed code.
- OPcache's own memory or file-slot capacity being exhausted in a way that prevents *new* files from being cached at all, as opposed to serving a stale *existing* entry — a related but distinct condition not currently owned by any entry in this catalog.

---

# 8. WordPress Components

Listed as components commonly involved, not as a claim that every installation exercises every one of them:

- The Zend OPcache PHP extension itself (`opcache.so`/`opcache.dll`), bundled with PHP and commonly, though not universally, enabled by default in current PHP distributions.
- `opcache.validate_timestamps` and `opcache.revalidate_freq`, the two ini directives governing whether and how often OPcache checks a cached file's own modification time against its compiled copy.
- `opcache_reset()`, `opcache_invalidate()`, and `opcache_compile_file()`, the PHP functions available for programmatically clearing or refreshing specific or all cached entries, where a deployment process has access to invoke them (for example, via a small PHP script executed as part of deployment, since these functions operate on the specific PHP process's own OPcache and are not directly accessible from a shell command alone) — critically, a CLI-invoked script (including WP-CLI) commonly resets a *different*, separate OPcache instance than the one serving web requests, since PHP CLI and a web-facing PHP-FPM pool frequently do not share OPcache's own shared memory at all; a deployment script intended to clear the web-facing cache shall trigger it through that same web-facing context, not via CLI.
- `opcache_get_status()`, which reports OPcache's own current state, including per-file cache-hit information and whether timestamp validation is active, useful for confirming the currently effective configuration directly rather than assuming it from documentation alone.
- PHP-FPM's own worker-pool architecture, in which each pool (and, in some configurations, each individual worker process) maintains its own independent OPcache shared-memory segment, distinct from any other pool or process on the same or a different server.
- Deployment tooling (a CI/CD pipeline, a hosting platform's own deployment hook) that may or may not include an explicit OPcache reset or PHP-FPM reload as one of its own steps.

---

# 9. Typical Symptoms

- A deployed change (a bug fix, updated logic, a security patch) verifiably present in the source files on disk, but not reflected in the site's own observed behavior, on some or all requests.
- A PHP fatal error referencing an undefined function, method, or class that a recent deployment did introduce, in a context where the file defining it and the file calling it were both part of the same deployment — a signature specifically suggesting inconsistent invalidation between files rather than a genuine code defect.
- Inconsistent behavior across otherwise-identical requests — the same URL, requested repeatedly, sometimes reflecting pre-deployment and sometimes post-deployment behavior — pointing toward per-process or per-server invalidation inconsistency (cause 3) rather than a uniform, site-wide staleness.
- The condition resolving on its own, without any further deployment action, after a period of time consistent with `opcache.revalidate_freq`'s own configured interval, or after an unrelated event that happens to restart the affected PHP processes (a server reboot, a routine PHP-FPM restart, a container redeploy).
- `opcache_get_status()`'s own reported state showing `opcache.validate_timestamps` is disabled, or a specific file's own cached timestamp predating its actual, current modification time on disk.
- The condition being reproducible immediately after a deployment and specifically on a subset of application servers or worker processes in a load-balanced or multi-worker environment, while other servers or workers already reflect the change correctly.

---

# 10. Common Causes

Causes are grouped by category. Inclusion in this list identifies a category as plausible; it does not assert that any specific one is present without diagnostic confirmation.

- `opcache.validate_timestamps` deliberately set to `0` in production for performance, with the deployment process not including a corresponding OPcache reset or PHP-FPM reload step.
- A deployment process that reloads PHP-FPM on some, but not all, application servers in a load-balanced environment, or that reloads some worker pools but not others on the same server.
- `opcache.revalidate_freq` configured to a long interval, delaying automatic detection of a genuinely changed file even with timestamp validation enabled.
- A container-based or immutable-infrastructure deployment that replaces application files by mounting a new image or volume, where the running PHP-FPM process's own OPcache was populated from the *previous* mount and is never automatically informed that the underlying files changed, since no local filesystem write actually occurred from the running process's own perspective.
- A hosting platform's own PHP-FPM restart or reload behavior differing from what the deployment tooling assumes (for example, a graceful reload that allows in-flight workers to finish their current OPcache state before cycling, extending the window before every worker reflects the change).
- A manual file edit made directly on a production server, bypassing whatever deployment tooling would otherwise have included an OPcache reset step.

---

# 11. Diagnosis

Verify the following, starting from the least invasive observation and narrowing only once the general shape of the failure is established:

1. **Confirm the source files on disk are actually correct and complete first**, before concluding this entry's own condition applies — compare the deployed files' own content or checksums directly against what the deployment was intended to produce, per `WP-ERROR-032`'s own diagnostic approach where relevant, since this entry's own condition presumes that process already succeeded.
2. **Confirm the observed behavior is genuinely inconsistent with the files on disk**, not merely unexpected — reproduce the specific request and compare its actual output or behavior against what the current, on-disk source would be expected to produce if executed directly.
3. **Query `opcache_get_status()`** (directly, or via a small diagnostic script executed in the same PHP context as the affected requests) to confirm OPcache is active, to check whether `opcache.validate_timestamps` is enabled, and to inspect the specific file's own cached timestamp against its actual, current filesystem modification time.
4. **Determine which of the three causes in Section 6 applies**: whether timestamp validation is disabled entirely (cause 1), whether it is enabled but the revalidation interval has not yet elapsed (cause 2), or whether the condition is inconsistent across requests in a way that points to per-process or per-server scope (cause 3).
5. **Where inconsistency across requests is observed (cause 3)**, identify which specific server(s) or worker pool(s) are affected, testing each independently where infrastructure access permits, rather than assuming a single, uniform cause across the entire environment.
6. **Where a PHP fatal error is present**, apply `WP-ERROR-013`'s own diagnostic procedure to capture and locate it first, then evaluate whether the specific error (commonly an undefined function, method, or class) is consistent with cross-file bytecode inconsistency — a symbol that genuinely exists in the current, on-disk source of the file that should define it, but is reported as undefined by the process that failed.
7. Preserve relevant evidence — `opcache_get_status()`'s own output, the specific deployment's own timeline, and which server(s)/process(es) were and were not affected — before making any change.
8. Where the engineer performing diagnosis does not control PHP-FPM configuration, server infrastructure, or deployment tooling, escalate to the hosting provider or infrastructure/DevOps team rather than attempting an unverified workaround.

---

# 12. Recovery Procedure

Recovery shall specifically address OPcache invalidation for the affected process(es); it shall not substitute generic PHP or web-server troubleshooting unrelated to the verified bytecode-staleness cause.

Permitted recovery categories, depending on the verified cause, include:

- Invoking `opcache_reset()` (directly, or via a deployment-triggered script executed in the same PHP context as the affected requests) to immediately clear the stale cache for the specific process(es) confirmed affected — executed through the same web-facing PHP-FPM context serving the affected requests, not via a CLI-based script such as WP-CLI, per Section 8's own caution.
- Reloading or restarting the specific PHP-FPM worker pool(s) or server(s) confirmed still serving stale bytecode, where a targeted reset is not available or sufficient, rather than restarting infrastructure unrelated to the confirmed cause.
- Adding an explicit OPcache reset or PHP-FPM reload step to the deployment process itself, where diagnosis confirms cause 1 (disabled validation with no compensating step) as a systemic, recurring gap rather than a one-time oversight.
- Adjusting `opcache.revalidate_freq` to a shorter interval, only where diagnosis confirms cause 2 is a recurring, material problem and the resulting performance trade-off (more frequent timestamp checks) has been deliberately accepted, not as a default response to every occurrence.
- Ensuring a load-balanced or multi-worker deployment's own tooling reloads every affected process or server, not a subset, where diagnosis confirms cause 3.

Recovery shall not disable OPcache entirely as a general response to this condition; doing so removes a legitimate, significant performance optimization to solve what is, once diagnosed, a specific and narrowly correctable invalidation gap.

---

# 13. Validation

Recovery is successful when:

- The specific, previously-stale behavior is confirmed resolved, reproducing the exact request or condition that previously exhibited it.
- `opcache_get_status()` confirms the affected file's own cached timestamp now matches its current, on-disk modification time, on every server or worker process previously confirmed affected — not only the first one checked.
- Where a fatal error was the original symptom, it no longer occurs across repeated, fresh requests.
- Where a deployment-process change was made (adding a reset/reload step), a subsequent, real deployment is confirmed to correctly invalidate OPcache across every affected process or server without manual intervention.
- No unrelated PHP-FPM configuration, server, or deployment-tooling change was introduced as a side effect of the recovery.

---

# 14. Prevention

- Include an explicit OPcache reset or PHP-FPM reload as a standard, automated step in the deployment process itself, rather than relying on `opcache.validate_timestamps` to eventually catch up.
- Where `opcache.validate_timestamps` is deliberately disabled in production for performance, document that decision explicitly alongside the corresponding deployment-process requirement it creates, so the dependency is not lost to institutional knowledge.
- For load-balanced or multi-worker environments, verify the deployment process reloads every affected server and worker pool, not a representative subset, and include this as an explicit check in deployment validation.
- Monitor for the specific symptom pattern of inconsistent behavior across otherwise-identical requests immediately following a deployment, as an early, mechanism-specific signal distinct from a general post-deployment smoke test.
- Where container-based or immutable-infrastructure deployment is in use, confirm explicitly whether a fresh container/process naturally starts with an empty OPcache (commonly true) or could inherit a stale one from an underlying image layer or persisted volume (environment-specific; verify rather than assume).

---

# 15. Security Considerations

- Treat a security patch that appears correctly deployed in source but does not appear to have taken effect as a priority investigation target for this entry's own condition specifically, since OPcache staleness is a plausible, easily-overlooked explanation for a fix silently failing to apply in production.
- Do not conclude a security fix is ineffective, or reintroduce a workaround for an already-patched vulnerability, without first ruling out this entry's own condition via `opcache_get_status()`, since doing so can mask that the fix is actually present and correct, merely not yet running.
- Coordinate any change to shared PHP-FPM or OPcache configuration through a platform-appropriate process where the affected infrastructure is shared across multiple applications, not only WordPress.

---

# 16. Related Errors

The following are cited as they exist in this repository, or as conceptual distinctions where noted.

1. [WP-ERROR-032 — WordPress Plugin Update Failure](WP-ERROR-032-PLUGIN-UPDATE-FAILURE.md) — exists in this repository; see Section 6 (Distinction) above for the boundary between this entry and that one.
2. [WP-ERROR-014 — Required PHP Extension Missing](WP-ERROR-014-REQUIRED-PHP-EXTENSION-MISSING.md) — exists in this repository; see Section 6 (Distinction) above.
3. [WP-ERROR-013 — WordPress Bootstrap PHP Fatal Error](WP-ERROR-013-WORDPRESS-BOOTSTRAP-PHP-FATAL-ERROR.md) — exists in this repository; see Section 6 (Distinction) above.
4. [WP-ERROR-021 — WordPress REST API Route Not Found](WP-ERROR-021-REST-API-ROUTE-NOT-FOUND.md), [WP-ERROR-025 — WordPress Authentication Cookie Invalid or Expired](WP-ERROR-025-AUTHENTICATION-COOKIE-INVALID-OR-EXPIRED.md), [WP-ERROR-027 — WordPress Nonce Verification Failure, Non-REST](WP-ERROR-027-NONCE-VERIFICATION-FAILURE-NON-REST.md), and [WP-ERROR-030 — WordPress CORS (Cross-Origin) Policy Failure](WP-ERROR-030-CORS-CROSS-ORIGIN-POLICY-FAILURE.md) — exist in this repository; see Section 6 (Distinction) above for why each concerns data/content caching, not compiled code.
5. [WP-ERROR-033 — WordPress Persistent Object Cache Backend Unavailable](WP-ERROR-033-PERSISTENT-OBJECT-CACHE-BACKEND-UNAVAILABLE.md) and [WP-ERROR-034 — WordPress Page Cache Not Active](WP-ERROR-034-PAGE-CACHE-NOT-ACTIVE.md) — exist in this repository; see Section 6 (Distinction) above for the same data-versus-code distinction.

---

# 17. Notes

This entry documents the general, verified observable condition of PHP's opcode cache continuing to serve previously-compiled bytecode after its own source files have genuinely changed, distinguishing the three mechanically distinct causes — disabled validation, a revalidation-window race, and per-process/per-server inconsistency — at which that condition can occur. It is the third and final planned entry in the Performance category, per `SF-TAXONOMY-006` Section 3.

This entry occupies a different layer than its two Performance-category siblings: `WP-ERROR-033` and `WP-ERROR-034` both concern caching of *data or content* WordPress itself manages; this entry concerns PHP's own caching of *compiled source code*, a runtime-level mechanism entirely independent of, and unaware of, WordPress. Its severity was reasoned independently from both siblings, per Section 5, rather than inherited from either.

Consistent with the single-responsibility principle in **SF-SPEC-001** Section 4.3, this entry does not claim ownership of the update mechanism's own success or failure, OPcache's own availability as a PHP capability, the general PHP-fatal-error capture process, or any data/content-caching layer's own condition.

This entry reached `Production Ready` via `SF-REVIEW-101` (Class A author review; no findings) and `SF-REVIEW-102` (Class B independent review; two Minor findings — IF-1, a CLI-versus-FPM OPcache scoping clarification within this entry, and IF-2, a cross-document completeness gap in `WP-ERROR-032`'s own code-defect exclusion bullet — both corrected within that same review) per **SF-SPEC-001** Section 19, **SF-SPEC-005** Section 5.6, and **SF-SPEC-012**.

No Reference Implementation is currently designated by **SF-SPEC-001**; this entry's relationship to that designation, and to any future `WP-SCENARIO-XXX` runtime evidence, is not asserted here and shall not be assumed until such evidence or designation actually exists.
