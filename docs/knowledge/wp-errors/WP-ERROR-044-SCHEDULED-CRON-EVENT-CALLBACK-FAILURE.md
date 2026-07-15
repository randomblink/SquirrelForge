# WP-ERROR-044 — WordPress Scheduled Cron Event Callback Failure

---

# 1. Knowledge Entry

WordPress Scheduled Cron Event Callback Failure

---

# 2. Metadata

* **Error ID:** `WP-ERROR-044`
* **Title:** WordPress Scheduled Cron Event Callback Failure
* **Category:** Cron
* **Severity:** Critical
* **Recovery Priority:** Immediate
* **Status:** Production Ready
* **Version:** 1.0

---

# 3. Summary

WP-Cron's own triggering mechanism successfully reaches and begins processing due events, but a specific scheduled event's own registered callback fails to complete correctly — via a PHP fatal error during its own post-bootstrap execution, the callback exceeding the PHP execution-time limit governing the cron request, or the event having been scheduled with no callback actually registered against its own hook name at all. Because WordPress processes every currently-due event within a single triggered pass sequentially and within one shared PHP process, a severe enough failure in one event's own callback can also prevent other, unrelated due events later in that same pass from executing at all.

---

# 4. Primary Failure Mode

WP-Cron's own trigger mechanism succeeds — distinguishing this entry from `WP-ERROR-043` — and `wp-cron.php` begins iterating through every event currently due, firing each one's own registered action hook in turn within a single PHP process and execution-time budget. A specific event's own callback then fails in one of three ways (Section 6). Because every due event in that pass shares the same process, a fatal error terminating the script outright, or an execution-time-limit exhaustion consuming the entire remaining budget, can prevent any event still pending later in that same pass's own processing order from executing at all during that run — a compounding, cross-event risk distinct from a single event's own isolated failure.

---

# 5. Severity

This entry is classified **Critical**, reasoned from three factors:

- **The same range of plausible impact `WP-ERROR-043` Section 5 already establishes for this category**: a failed callback can be inconsequential or genuinely severe depending on what the specific scheduled task was responsible for.
- **A compounding "blast radius" risk unique to this entry**: because WordPress processes every currently-due event within one shared pass and process, a single severely-failing callback (a fatal error, or an execution-time-limit exhaustion) can silently prevent *other*, entirely unrelated scheduled tasks from executing during that same trigger, not merely the one whose own callback actually failed.
- **Largely persistent invisibility, with one partial exception**: like `WP-ERROR-043`, this entry's own condition produces no error in the ordinary sense, since the loopback trigger request's own result is never inspected by WordPress (`SF-TAXONOMY-010` Section 4). The partial exception is a PHP fatal error specifically: where WordPress's own fatal-error-protection mechanism (introduced in WordPress 5.2) is reachable and an administrator has configured and can receive its own recovery-mode email notification, a fatal-error cause of this entry's own condition may surface that way — but this is neither guaranteed nor the default state of every installation, and the execution-time-limit and missing-callback causes produce no comparable signal at all.
- This entry remains classified at the level of its most severe possible manifestation, consistent with the range-based Critical classification used elsewhere in this catalog (`WP-ERROR-021`, `024`–`030`, `032`–`033`, `035`, `038`–`040`, `042`–`043`).

---

# 6. Distinction

This entry applies only when verified evidence establishes that WP-Cron's own triggering mechanism successfully reached scheduled-event processing, and that a specific event's own callback then failed for one of the causes below.

**Three internal causes this entry keeps deliberately separate, since each has a different diagnostic starting point and a different recovery path:**

1. **Callback fatal error** — the specific event's own registered callback raises an uncaught PHP fatal error during its own execution, occurring strictly *after* `wp-cron.php`'s own shared bootstrap sequence has already completed successfully.
2. **Callback execution-time exhaustion** — the callback performs an operation that exceeds the PHP execution-time limit governing the cron request before completing, terminating the script partway through — and, where other events were still pending later in the same processing pass, preventing them from executing at all during that run.
3. **No callback registered for the scheduled hook** — the event is genuinely and correctly scheduled (`wp_next_scheduled()` confirms it), and WP-Cron's own processing correctly attempts to fire its own registered hook, but no code has actually registered a callback against that exact hook name via `add_action()` — commonly a mismatched or misspelled hook name, or a registration that is conditionally skipped in a way that never actually executes during a cron-triggered request specifically (for example, gated behind an interactive-request check such as `is_admin()`).

It is distinct from:

- **`WP-ERROR-013` — WordPress Bootstrap PHP Fatal Error**: owns a PHP fatal error occurring during `wp-cron.php`'s own shared bootstrap sequence, before scheduled-event processing has begun at all — the same "post-bootstrap, this category's own territory" boundary `WP-ERROR-013` Section 6 already draws generically ("fatal errors that occur only after WordPress has completed bootstrap and begun normal request processing — for example, within a plugin's request-handling callback"), applied here to a scheduled event's own callback specifically. Cause 1 of this entry occurs strictly after that shared bootstrap sequence, within a specific event's own callback execution.
- **`WP-ERROR-043` — WordPress Scheduled Cron Event Not Triggered**: owns the triggering mechanism's own failure to reach scheduled-event processing at all. This entry presumes triggering succeeded; `WP-ERROR-043` presumes it never reached this point.
- **A specific plugin's own business-logic defect that is not itself a triggering or execution-mechanics failure** — a callback that completes fully, without a fatal error or timeout, but simply performs its own intended task incorrectly (Cron category's own first exclusion, `SF-TAXONOMY-010` Section 2). This entry owns only the mechanics of the callback failing to run to completion at all, not the correctness of what it does once it does run.
- **A scheduled task's own outbound HTTP call to a third-party service failing, once the callback has already begun executing normally** — already `WP-ERROR-028`'s own territory, per `SF-TAXONOMY-010` Section 2's own exclusion; this entry's own condition concerns the callback itself failing to run to completion, not a downstream call it makes once actually executing.

---

# 7. Scope

**Covered:** A verified condition in which WP-Cron's own triggering mechanism has already successfully reached scheduled-event processing, and a specific event's own registered callback fails to complete correctly — a PHP fatal error occurring during the callback's own post-bootstrap execution, the callback exceeding the PHP execution-time limit governing the cron request, or no callback actually being registered against the event's own scheduled hook name — including the compounding risk this failure poses to other, unrelated due events processed in the same pass.

**Excluded:**

- A PHP fatal error occurring during `wp-cron.php`'s own shared bootstrap sequence, before event processing begins (`WP-ERROR-013`).
- The triggering mechanism itself failing to reach event processing at all (`WP-ERROR-043`).
- A callback that completes without error or timeout but performs its own intended task incorrectly — a specific plugin's own business-logic defect.
- A scheduled task's own outbound HTTP call to a third-party service failing, once the callback has already begun executing normally (`WP-ERROR-028`).
- A missing PHP extension or unsupported PHP version underlying cause 1, once verified (`WP-ERROR-014`/`015`).

---

# 8. WordPress Components

Listed as components commonly involved, not as a claim that every installation exercises every one of them:

- `wp_cron()` (`wp-includes/cron.php`), which iterates every currently-due event and fires each one's own registered hook in turn, within a single PHP process and execution-time budget shared across the entire pass.
- `do_action()`/`do_action_ref_array()`, the hook-execution mechanism WordPress uses to invoke each event's own registered callback.
- The PHP execution-time limit (`max_execution_time`) governing the `wp-cron.php` request specifically, which may be configured differently from the limit governing an ordinary web request.
- WordPress's own fatal-error-protection mechanism (introduced in WordPress 5.2) and its recovery-mode email notification, a partial, non-guaranteed visibility mechanism for cause 1 specifically.
- `wp_schedule_event()`/`wp_schedule_single_event()` and `add_action()`, whose own hook-name agreement cause 3 depends on.
- `wp_clear_scheduled_hook()`, useful for removing a stale scheduled event that references a since-removed callback.
- WP-CLI's `wp cron event run <hook>`, useful for directly and immediately triggering a specific event's own callback in isolation for diagnostic purposes.

---

# 9. Typical Symptoms

- A specific scheduled task's own expected effect not occurring, while other, unrelated scheduled tasks continue running normally — the distinguishing symptom separating this entry's own condition from `WP-ERROR-043`'s own total-triggering-failure symptom, where nothing runs at all.
- Where WordPress's own fatal-error-protection recovery-mode notification is configured and reachable, an email alert whose own backtrace names a scheduled-event callback specifically.
- A recurring scheduled event (`wp_schedule_event()`) continuing to show a fresh `wp_next_scheduled()` timestamp on each pass, yet its own expected effect never actually occurring — suggesting the event itself keeps firing but its own callback keeps failing on each attempt.
- A one-time scheduled event (`wp_schedule_single_event()`) never re-attempting after a single failure, since, unlike a recurring event, it is not automatically rescheduled once fired.
- Multiple due events scheduled for approximately the same trigger pass all failing to produce their expected effects together, suggesting a shared-pass blast-radius effect (Section 4) rather than each being independently broken.

---

# 10. Common Causes

Causes are grouped by category. Inclusion in this list identifies a category as plausible; it does not assert that any specific cause is present without diagnostic confirmation.

- A plugin's own scheduled callback containing a genuine PHP-level defect (an undefined function or class, a type error) that manifests specifically under cron-triggered conditions — for example, global state or a logged-in-user context an ordinary request would have, that a cron-triggered request does not.
- A callback performing a resource-intensive operation (a large export, bulk data processing, an unthrottled loop) that exceeds the PHP execution-time limit governing the cron request.
- A plugin registering a scheduled event via `wp_schedule_event()` but never calling `add_action()` for the exact matching hook name, due to a mismatch, a misspelling, or a registration conditionally gated behind a check (such as `is_admin()`) that never passes during a cron-triggered request.
- A plugin deactivated, uninstalled, or updated in a way that removed the function or class a previously-scheduled event's own callback depends on, while that scheduled event itself remains pending.
- Insufficient PHP memory for a specific event's own callback, producing a fatal "Allowed memory size exhausted" error partway through its own execution.

---

# 11. Diagnosis

Verify the following, starting from the least invasive observation and narrowing only once the general shape of the failure is established:

1. **Confirm triggering itself is not the problem** by verifying other, unrelated scheduled events are executing normally at their own expected times — ruling out `WP-ERROR-043`'s own condition before investigating this entry's own.
2. **Identify the specific affected event and its own exact registered hook name**, via `wp_next_scheduled()`/`wp_get_scheduled_event()` or WP-CLI's `wp cron event list`.
3. **Attempt to trigger the specific event directly** via WP-CLI's `wp cron event run <hook>`, observing its own behavior in isolation and capturing any fatal-error or execution-time-limit message directly, rather than waiting for or relying on the next ordinary trigger.
4. **Where a fatal error is captured, apply `WP-ERROR-013`'s own general diagnostic principles** (evaluating whether the failure traces to a missing PHP extension, per `WP-ERROR-014`, or a PHP-version mismatch, per `WP-ERROR-015`) to this entry's own post-bootstrap context specifically.
5. **Where no fatal error is observed but the event's own expected effect still does not occur, verify a callback is actually registered against the event's own exact hook name** — inspecting the responsible plugin's own source, or using a hook-inspection tool, rather than assuming registration succeeded because scheduling itself did.
6. **Where an execution-time-limit exhaustion is suspected, review the callback's own expected running time against the environment's configured `max_execution_time` for cron-context requests specifically**, which may differ from the limit governing an ordinary web request.
7. **Where multiple due events from the same triggered pass are all failing to produce their expected effects, investigate whether an earlier event in the pass's own processing order is the actual root cause** (the blast-radius pattern, Section 4) before treating each affected event as independently broken.
8. Preserve the specific event's own hook name, any captured fatal-error or timeout evidence, and the affected plugin's own version before making any corrective change.

---

# 12. Recovery Procedure

Recovery shall target the specific, verified cause identified in Diagnosis (Section 11).

Permitted recovery categories, depending on the verified cause, include:

- Where a genuine PHP-level defect in a plugin's own callback is confirmed, correcting or updating the responsible plugin, or escalating to its own maintainer where it is not under direct control.
- Where an execution-time-limit exhaustion is confirmed, optimizing the callback's own resource usage, increasing the execution-time limit specifically for cron-context requests where the hosting environment permits it, or restructuring the task to process in smaller batches across multiple triggered runs rather than a single long-running callback.
- Where a missing or mismatched hook registration is confirmed, correcting the plugin's own code, or escalating to its own maintainer where it is not under direct control.
- Where a stale event references a since-removed callback (following an uninstalled or updated plugin), clearing the orphaned scheduled event via `wp_clear_scheduled_hook()` rather than leaving it to fail indefinitely on every future trigger.
- Where a blast-radius pattern (Section 4) is confirmed, prioritizing correction of the specific root-cause event first, since other affected events may resume normal execution once it no longer consumes the entire available execution budget or terminates the shared process outright.

---

# 13. Validation

Recovery is successful when:

- The specific event's own expected effect is confirmed occurring correctly at its next scheduled occurrence, not merely that a single, manually-triggered test run succeeded once.
- Where a blast-radius pattern was involved, other previously-affected due events in the same pass are confirmed to resume executing normally.
- No new fatal error, execution-time-limit exhaustion, or missing-callback condition recurs for the corrected event.
- Where the correction involved clearing an orphaned scheduled event, no residual reference to the removed callback remains scheduled.

---

# 14. Prevention

- Design cron callbacks to be idempotent and resilient to partial execution, so a retry after a prior failure does not itself produce an inconsistent state.
- Keep individual cron tasks well within typical execution-time budgets, breaking a large operation into smaller batches processed across multiple triggered runs rather than one large, monolithic callback.
- Verify hook-name consistency between `wp_schedule_event()`/`wp_schedule_single_event()` and the corresponding `add_action()` registration during code review, and confirm the registration is not conditionally gated in a way that skips it during a cron-triggered request specifically.
- Clear a plugin's own scheduled events as part of its own uninstall or deactivation routine, so no orphaned event remains pending against a callback that no longer exists.
- Where WordPress's own fatal-error-protection recovery-mode email notification is available, ensure it is configured to reach an administrator who will actually act on it, given this entry's own otherwise-largely-invisible failure mode.

---

# 15. Security Considerations

- A security-relevant scheduled task (a malware scan, a credential-rotation routine, a security plugin's own periodic check) that silently stops completing due to this entry's own condition can leave a site in a degraded security posture with no visible indication; verifying that security-relevant scheduled tasks are actually completing, not merely that they appear scheduled, should be treated as part of ordinary security monitoring.
- Do not disable WordPress's own fatal-error-protection mechanism as a troubleshooting shortcut for a cron-triggered fatal error; doing so removes the one partial visibility mechanism this entry's own fatal-error cause has.

---

# 16. Related Errors

The following are cited as they exist in this repository, or as conceptual distinctions where noted.

1. [WP-ERROR-013 — WordPress Bootstrap PHP Fatal Error](WP-ERROR-013-WORDPRESS-BOOTSTRAP-PHP-FATAL-ERROR.md) — exists in this repository; see Section 6 (Distinction) above for the post-bootstrap boundary between the two entries.
2. [WP-ERROR-043 — WordPress Scheduled Cron Event Not Triggered](WP-ERROR-043-SCHEDULED-CRON-EVENT-NOT-TRIGGERED.md) — exists in this repository; see Section 6 (Distinction) above for how the two lifecycle stages remain independent.
3. [WP-ERROR-014 — Required PHP Extension Missing](WP-ERROR-014-REQUIRED-PHP-EXTENSION-MISSING.md) — exists in this repository; see Section 6 (Distinction) above for the diagnose-then-hand-off relationship for cause 1.
4. [WP-ERROR-015 — Unsupported PHP Version](WP-ERROR-015-UNSUPPORTED-PHP-VERSION.md) — exists in this repository; see Section 6 (Distinction) above for the diagnose-then-hand-off relationship for cause 1.
5. [WP-ERROR-028 — WordPress Outbound HTTP Request Failure](WP-ERROR-028-WORDPRESS-OUTBOUND-HTTP-REQUEST-FAILURE.md) — exists in this repository; see Section 6 (Distinction) above.

---

# 17. Notes

This entry documents the general, verified observable condition of a specific WP-Cron event's own callback failing to complete correctly once triggering has already succeeded, distinguishing three mechanically distinct causes and explicitly naming the compounding "blast radius" risk a severe failure in one event's callback poses to other, unrelated due events processed in the same pass. It is the second and final planned entry in the Cron category, per `SF-TAXONOMY-010` Section 3.

Consistent with the single-responsibility principle in **SF-SPEC-001** Section 4.3, this entry does not claim ownership of the underlying PHP-extension or PHP-version conditions cause 1 may trace to (`WP-ERROR-014`/`015`), nor of a specific plugin's own business-logic defect once its callback has already run to completion without error.

This entry's relationship to a `Production Ready` designation is established via `SF-REVIEW-131` (Class A author review) and `SF-REVIEW-132` (Class B independent review) per **SF-SPEC-001** Section 19, **SF-SPEC-005** Section 5.6, and **SF-SPEC-012**.

No Reference Implementation is currently designated by **SF-SPEC-001**; this entry's relationship to that designation, and to any future `WP-SCENARIO-XXX` runtime evidence, is not asserted here and shall not be assumed until such evidence or designation actually exists.
