# WP-ERROR-043 — WordPress Scheduled Cron Event Not Triggered

---

# 1. Knowledge Entry

WordPress Scheduled Cron Event Not Triggered

---

# 2. Metadata

* **Error ID:** `WP-ERROR-043`
* **Title:** WordPress Scheduled Cron Event Not Triggered
* **Category:** Cron
* **Severity:** Critical
* **Recovery Priority:** Immediate
* **Status:** Production Ready
* **Version:** 1.0

---

# 3. Summary

WordPress's own scheduled-event triggering mechanism ("WP-Cron") fails to reach the point of processing due events at all, so no scheduled task — from routine housekeeping to a scheduled post publish, a security scan, or an e-commerce order-processing step — executes at its intended time. Because none of this entry's own causes produce a visible error anywhere in WordPress's own ordinary response, this condition is characteristically silent and can persist unnoticed for an extended period, discovered only once a downstream effect is separately missed.

---

# 4. Primary Failure Mode

WordPress's own default trigger has no independent existence apart from ordinary site traffic: on a qualifying page load, WordPress checks whether any scheduled event is due and, if so, issues an asynchronous, non-blocking loopback request to `wp-cron.php` to process it — a request WordPress itself never inspects the result of. This entry's own condition occurs when that entire triggering path fails to reach actual event processing, for one of several distinguishable reasons (Section 6): the site simply receives no qualifying traffic to trigger the check at all; the loopback request itself fails to establish a connection; a site owner has disabled the default mechanism (`DISABLE_WP_CRON`) without a working replacement actually in place; or WordPress's own internal locking mechanism, intended to prevent overlapping concurrent runs, blocks a legitimate new attempt because a prior run's own lock was never released.

---

# 5. Severity

This entry is classified **Critical**, reasoned from two compounding factors rather than any single manifestation:

- **The range of what can be scheduled.** A missed cron event can be trivially inconsequential (a routine transient cleanup) or genuinely severe (a security plugin's own scheduled malware scan never running, a scheduled post central to a time-sensitive campaign never publishing, an e-commerce plugin's own order-processing or inventory-sync step silently not executing). This entry documents the general triggering mechanism regardless of which specific event is affected, so its own classification reflects the most severe plausible manifestation, consistent with the range-based Critical classification used elsewhere in this catalog (`WP-ERROR-021`, `024`–`030`, `032`–`033`, `035`, `038`–`040`, `042`).
- **The characteristic invisibility of this entry's own condition**, a compounding factor distinct from severity classification elsewhere in this catalog: none of this entry's own four causes produces any error WordPress itself surfaces anywhere — not in a browser, not in a standard log, not via any mechanism an administrator would ordinarily encounter passively. A site can operate for an extended period with this entry's own condition fully present before anyone notices, precisely because nothing about it announces itself. This is the same characteristic `WP-ERROR-013` Section 9 already notes for a bootstrap fatal error occurring specifically via the cron path ("cron requests are not typically viewed in a browser"), generalized here to this entry's own broader triggering condition.
- The Immediate recovery priority reflects the corrective action's own urgency once discovered, not a claim that discovery itself happens promptly — the diagnostic irony this entry's own Diagnosis and Prevention sections address directly.

---

# 6. Distinction

This entry applies only when verified evidence establishes that WordPress's own scheduled-event triggering mechanism failed to reach the point of processing due events at all, for one of the causes Section 4 describes.

**Four internal causes this entry keeps deliberately together, not split into separate entries, because each presents identically to an observer — "my scheduled events aren't running," with no distinguishing symptom of its own** (`SF-TAXONOMY-010` Section 4's own central boundary decision):

1. **Absence of qualifying traffic** — WordPress's own default trigger is a side effect of ordinary page-load traffic; a site receiving no visits (a staging environment, a genuinely low-traffic production site, or a site served entirely from a full-page cache that never executes PHP for most requests) never reaches the check that would attempt to fire due events at all.
2. **Loopback request connection failure** — the self-referential request WordPress issues to trigger `wp-cron.php` fails to establish a connection (DNS resolution failure, connection refused, or the request blocked by a firewall, security plugin, or WordPress's own `WP_HTTP_BLOCK_EXTERNAL_HTTP`/SSRF-protection mechanism without an exception for the site's own domain).
3. **`DISABLE_WP_CRON` set without a working replacement** — a site owner has disabled WordPress's own default trigger (commonly on the recommendation to improve performance by moving cron processing to a real, scheduled system job) but the intended replacement was never actually configured, was configured against the wrong URL, or has itself silently stopped running.
4. **A stuck or contended cron lock** — WordPress's own internal locking mechanism (governed by the `WP_CRON_LOCK_TIMEOUT` constant, default 60 seconds) exists to prevent two simultaneous page loads from both triggering an overlapping cron run; a prior run that crashed or hung without releasing this lock can block a subsequent, legitimate trigger attempt until the lock naturally expires.

It is distinct from:

- **`WP-ERROR-013` — WordPress Bootstrap PHP Fatal Error**: owns a PHP fatal error occurring during `wp-cron.php`'s own bootstrap sequence, before scheduled-event processing would even begin — a condition presuming the trigger mechanism itself *did* successfully reach `wp-cron.php` and begin executing it. This entry's own condition means the trigger never reached that point at all (causes 1, 3, 4) or the loopback request never even connected (cause 2) — neither overlaps `WP-ERROR-013`'s own territory.
- **`WP-ERROR-028` — WordPress Outbound HTTP Request Failure**: owns the connection-establishment mechanics underlying cause 2 above, once diagnosis has traced this entry's own condition specifically to the loopback request's own connection attempt. This entry owns the observable, cron-specific consequence and its own distinct diagnostic path — necessary because the loopback request is issued non-blocking and its own result is never inspected by WordPress, unlike `WP-ERROR-028`'s own condition, which is always defined by an inspectable `WP_Error` a caller failed to check. This entry hands off to `WP-ERROR-028` only once a connection-level failure is independently confirmed as cause 2's own specific root cause, per `SF-TAXONOMY-010` Section 4.
- **`WP-ERROR-044` — WordPress Scheduled Cron Event Callback Failure**: owns a specific scheduled event's own callback failing *after* triggering has already succeeded and event processing has actually begun. This entry presumes triggering itself never reached that point; `WP-ERROR-044` presumes it did.
- **A specific plugin's own scheduling defect** (for example, repeatedly calling `wp_schedule_event()` without checking `wp_next_scheduled()` first, producing duplicate stacked events) — Cron category's own first exclusion (`SF-TAXONOMY-010` Section 2), a plugin-specific business-logic defect, not a failure of WordPress's own triggering mechanism, which behaves exactly as documented when called this way.

---

# 7. Scope

**Covered:** A verified condition in which WordPress's own scheduled-event triggering mechanism fails to reach the point of processing due events at all, where the verified cause is an absence of qualifying site traffic, a loopback-request connection failure, `DISABLE_WP_CRON` being set without a working replacement, or a stuck/contended internal cron lock.

**Excluded:**

- A PHP fatal error occurring during `wp-cron.php`'s own bootstrap, once the trigger mechanism did reach that point (`WP-ERROR-013`).
- The connection-establishment mechanics of the loopback request itself, once confirmed as the specific root cause of cause 2 (`WP-ERROR-028`).
- A specific scheduled event's own callback failing once triggering has already succeeded (`WP-ERROR-044`).
- A specific plugin's own scheduling defect (duplicate/stacked event registration).
- Any WordPress mechanism a cron-triggered task's own callback merely reaches once it has actually begun executing normally.

---

# 8. WordPress Components

Listed as components commonly involved, not as a claim that every installation exercises every one of them:

- `wp-cron.php`, the file WordPress's own trigger mechanism requests to process due events.
- `spawn_cron()` (`wp-includes/cron.php`), which issues the non-blocking loopback request to `wp-cron.php` and manages the internal locking mechanism, both described above.
- `wp_next_scheduled()`, `wp_get_scheduled_event()`, `wp_schedule_event()`, `wp_schedule_single_event()`, and `wp_clear_scheduled_hook()`, the public scheduling API.
- The `DISABLE_WP_CRON` constant, which, when set `true` in `wp-config.php`, disables WordPress's own default traffic-driven trigger entirely, presuming an external, real cron replacement is configured to request `wp-cron.php` directly on a fixed schedule.
- The `ALTERNATE_WP_CRON` constant, a lesser-used alternative to the default loopback-request trigger, which uses a redirect-based mechanism instead — a distinct trigger implementation from the one this entry's own cause 2 describes, worth checking for specifically since its own failure modes differ from a straightforward connection failure.
- The `WP_CRON_LOCK_TIMEOUT` constant and WordPress's own internal `doing_cron` transient, which together implement the locking mechanism cause 4 describes.
- Full-page caching layers (a CDN, a reverse proxy, or a page-caching plugin), which can serve cached responses without ever executing PHP, silently starving WordPress's own default trigger of the execution it depends on.

---

# 9. Typical Symptoms

- A scheduled post failing to publish at its intended time, remaining in a "Scheduled" state past its own publish date.
- `wp_next_scheduled()` (or WP-CLI's `wp cron event list`) showing one or more events with a scheduled timestamp in the past that never fired.
- Plugin-dependent scheduled maintenance — a transient cleanup, a cache-warming pass, a scheduled report or digest — simply not happening, discovered only when its own downstream effect is separately noticed missing.
- No corresponding entry in any WordPress-level log, PHP error log, or visible browser output, since none of this entry's own four causes produces one.
- A hosting environment's own access logs showing genuinely minimal or zero traffic during the period in question, where cause 1 (traffic absence) is suspected.

---

# 10. Common Causes

Causes are grouped by category. Inclusion in this list identifies a category as plausible; it does not assert that any specific cause is present without diagnostic confirmation.

- A staging, development, or genuinely low-traffic production site receiving too few visits for WordPress's own default trigger to fire with any useful regularity.
- A full-page cache (a CDN, reverse proxy, or caching plugin) serving nearly all requests from cache without ever invoking PHP, preventing WordPress's own trigger check from ever running.
- A firewall, security plugin, or hosting-level restriction blocking the site from making an outbound request to itself, causing the loopback trigger request to fail to connect.
- WordPress's own `WP_HTTP_BLOCK_EXTERNAL_HTTP`/SSRF-protection mechanism blocking the loopback request because the site's own resolved IP address falls within a range that mechanism treats as private or restricted by default, without an explicit exception configured.
- `DISABLE_WP_CRON` set `true` following a performance-optimization tutorial or recommendation, with the intended replacement system cron job never actually configured, configured against an incorrect URL, or silently failing on the hosting platform's own side.
- A prior cron run that crashed, timed out, or was forcibly terminated without releasing WordPress's own internal lock, blocking a subsequent legitimate trigger attempt until the lock naturally expires.

---

# 11. Diagnosis

Verify the following, starting from the least invasive observation and narrowing only once the general shape of the failure is established:

1. **Confirm the event is genuinely scheduled and overdue**, via `wp_next_scheduled()`/`wp_get_scheduled_event()` or WP-CLI's `wp cron event list`, rather than assuming from an absent downstream effect alone that scheduling itself is the problem.
2. **Check the value of `DISABLE_WP_CRON` in `wp-config.php`.** Where it is `true`, verify a real, working system cron job (or hosting-platform equivalent) is actually configured to request `wp-cron.php` on a fixed schedule — inspect the actual crontab or hosting control panel directly, rather than assuming a previously-configured job is still present and functioning.
3. **Where `DISABLE_WP_CRON` is not set (WordPress's own default trigger should be active), verify the site is actually receiving qualifying traffic** during the period in question, via access logs or an analytics tool.
4. **Check whether a full-page cache is serving requests without invoking PHP**, which would starve the default trigger regardless of actual visitor traffic — inspect cache-layer configuration and response headers for cache-hit indicators.
5. **Attempt to trigger `wp-cron.php` directly** (a direct request via `curl` or a browser, or WP-CLI's `wp cron event run --due-now`) to test whether the file itself executes correctly when reached directly, isolating whether the problem is in reaching `wp-cron.php` at all versus something failing once it is reached.
6. **Where the loopback request itself is suspected (cause 2), escalate to `WP-ERROR-028`'s own diagnostic procedure** once a connection-level failure is the specific, confirmed suspicion — checking DNS resolution for the site's own domain, any firewall or WAF rule affecting self-originated requests, and `WP_HTTP_BLOCK_EXTERNAL_HTTP`/SSRF-protection configuration.
7. **Where a stuck lock is suspected (cause 4), check for evidence of an abnormally-persisted internal lock** exceeding `WP_CRON_LOCK_TIMEOUT`'s own configured duration, and consider whether a prior cron run's own excessive execution time or an abrupt process termination is the underlying reason.
8. Preserve the exact `wp_next_scheduled()`/`wp cron event list` output, the `DISABLE_WP_CRON` value, and any relevant access-log excerpt before making any corrective change.

---

# 12. Recovery Procedure

Recovery shall target the specific, verified cause identified in Diagnosis (Section 11).

Permitted recovery categories, depending on the verified cause, include:

- Where traffic absence (cause 1) is confirmed and no full-page cache is implicated, configuring a real, external system cron job (with `DISABLE_WP_CRON` set `true`) to request `wp-cron.php` on a fixed, reliable schedule, rather than continuing to depend on unpredictable traffic.
- Where a full-page cache is starving the default trigger, either excluding `wp-cron.php` itself from the cache layer so it always executes PHP directly, or — the generally preferable approach for any cached site — configuring a real external system cron job independent of cache behavior entirely.
- Where a loopback connection failure (cause 2) is confirmed, resolving the underlying connectivity issue per `WP-ERROR-028`'s own recovery procedure — correcting a firewall or security-plugin rule blocking self-requests, or adding an appropriate exception to `WP_HTTP_BLOCK_EXTERNAL_HTTP`/SSRF-protection configuration for the site's own domain.
- Where `DISABLE_WP_CRON` is set without a working replacement (cause 3), either correcting the external cron job's own configuration (URL, schedule, authentication) or removing the constant to revert to WordPress's own default trigger, whichever better fits the site's own traffic and reliability requirements.
- Where a stuck lock (cause 4) is confirmed and has already exceeded its own configured timeout without self-clearing, forcibly triggering a fresh cron run (for example, via WP-CLI) as an immediate corrective step, while separately investigating why the prior run failed to release its own lock in the first place.

Recovery shall not disable or bypass WordPress's own internal locking mechanism as a general troubleshooting shortcut, since it exists specifically to prevent overlapping, concurrent cron runs from producing duplicate execution of the same scheduled event.

---

# 13. Validation

Recovery is successful when:

- Subsequent scheduled events are confirmed to fire at or near their intended times going forward, across more than one occurrence — not merely that a single, manually-triggered run succeeded once.
- Where the recovery was configuring a real system cron job, that job's own execution is independently confirmed (via its own logs or a monitoring mechanism) to be running on schedule, not merely assumed from the absence of further missed events.
- Where the cause was a full-page cache starving the trigger, the corrected configuration is confirmed to no longer prevent `wp-cron.php` from executing, whether via a cache exclusion or an independent system cron.
- No duplicate or overlapping execution of the same scheduled event is observed as an unintended side effect of the recovery itself.

---

# 14. Prevention

- For any site with unpredictable, low, or heavily-cached traffic, configure a real, external system cron job with `DISABLE_WP_CRON` set `true`, rather than relying on WordPress's own default, traffic-dependent trigger — the single most effective prevention for this entry's own most common cause.
- Monitor cron health actively (a dedicated monitoring plugin or an external uptime/cron-monitoring service) rather than relying on noticing a missed downstream effect, given this entry's own condition is characteristically silent.
- Where a full-page cache is in use, explicitly verify `wp-cron.php` itself is excluded from caching, or that an independent system cron job bypasses the cache layer entirely.
- Periodically verify a configured external cron replacement is still actually running, since a hosting-platform change, a credential expiration, or an unrelated infrastructure migration can silently stop it without any WordPress-visible symptom.

---

# 15. Security Considerations

- An unrestricted, publicly-accessible `wp-cron.php` can be requested directly by anyone, which is occasionally exploited to repeatedly trigger resource-intensive scheduled tasks as a low-grade denial-of-service vector; some security-hardening guidance recommends blocking public access to `wp-cron.php` at the web-server level while relying exclusively on a real system cron job (`DISABLE_WP_CRON` set `true`) as the sole trigger — a legitimate security/reliability tradeoff this entry does not prescribe one resolution for, but that diagnosis should account for when investigating an environment where public access has been deliberately restricted.
- Do not loosen `WP_HTTP_BLOCK_EXTERNAL_HTTP` or SSRF-protection configuration more broadly than necessary when resolving a loopback connectivity cause; scope any exception specifically to the site's own domain rather than disabling the protection mechanism generally.

---

# 16. Related Errors

The following are cited as they exist in this repository, or as conceptual distinctions where noted.

1. [WP-ERROR-013 — WordPress Bootstrap PHP Fatal Error](WP-ERROR-013-WORDPRESS-BOOTSTRAP-PHP-FATAL-ERROR.md) — exists in this repository; see Section 6 (Distinction) above for why this entry's own condition, occurring before or during the trigger attempt itself, falls outside that entry's own scope.
2. [WP-ERROR-028 — WordPress Outbound HTTP Request Failure](WP-ERROR-028-WORDPRESS-OUTBOUND-HTTP-REQUEST-FAILURE.md) — exists in this repository; see Section 6 (Distinction) above for the diagnose-then-hand-off relationship and the diagnostic-asymmetry this entry's own condition presents.
3. [WP-ERROR-044 — WordPress Scheduled Cron Event Callback Failure](WP-ERROR-044-SCHEDULED-CRON-EVENT-CALLBACK-FAILURE.md) — exists in this repository; see Section 6 (Distinction) above.

---

# 17. Notes

This entry documents the general, verified observable condition of WordPress's own scheduled-event triggering mechanism failing to reach event processing at all, deliberately keeping together four causes that present identically from an observer's own perspective rather than splitting them into separately-diagnosable entries. It is the first entry in the Cron category, drafted directly from `SF-TAXONOMY-010`'s own declared scope.

Consistent with the single-responsibility principle in **SF-SPEC-001** Section 4.3, this entry does not claim ownership of the loopback request's own connection-establishment mechanics (`WP-ERROR-028`) once confirmed as a specific root cause, nor of a specific scheduled event's own callback failure once triggering has already succeeded (`WP-ERROR-044`).

This entry's relationship to a `Production Ready` designation is established via `SF-REVIEW-129` (Class A author review) and `SF-REVIEW-130` (Class B independent review) per **SF-SPEC-001** Section 19, **SF-SPEC-005** Section 5.6, and **SF-SPEC-012**.

No Reference Implementation is currently designated by **SF-SPEC-001**; this entry's relationship to that designation, and to any future `WP-SCENARIO-XXX` runtime evidence, is not asserted here and shall not be assumed until such evidence or designation actually exists.
