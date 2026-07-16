# WP-ERROR-032 — WordPress Plugin Update Failure

---

# 1. Knowledge Entry

WordPress Plugin Update Failure

---

# 2. Metadata

* **Error ID:** `WP-ERROR-032`
* **Title:** WordPress Plugin Update Failure
* **Category:** Plugin
* **Severity:** Critical
* **Recovery Priority:** Immediate
* **Status:** Production Ready
* **Version:** 1.0

---

# 3. Summary

WordPress's own plugin-update mechanism (`Plugin_Upgrader`) fails to bring an already-installed plugin to a newer version correctly, through one of three mechanically distinct points: a pre-update compatibility check refuses to attempt the update at all, or the update package is downloaded but is corrupt, incomplete, or fails extraction; the multi-step process of replacing the plugin's old files with new ones is interrupted for a reason other than an OS-level permission or capacity constraint, leaving a mixed-version or incomplete file set; or — for an automatic background update specifically — WordPress's own post-update fatal-error detection and rollback mechanism either succeeds or itself fails to restore the plugin's previous, working version.

---

# 4. Primary Failure Mode

An administrator, an automated process such as WP-CLI, or WordPress's own automatic-update mechanism attempts to update a specific, already-installed regular plugin to a newer version, and that update does not complete correctly. Unlike `WP-ERROR-031`'s own activation condition, this entry's condition can leave the plugin in a materially worse state than before the attempt began: an update failure on a plugin that was already active can leave the site attempting to run from an incomplete or mixed-version set of files on the very next request, rather than simply remaining inactive. The failure occurs at exactly one of three distinguishable points: before or during acquisition of the update package (a compatibility-gate refusal, or a corrupt/incomplete download, or an extraction failure), during the filesystem-level replacement of the plugin's old files with new ones, or — for an automatic update specifically — within WordPress's own rollback response to a fatal error it detected immediately after the file swap completed.

---

# 5. Severity

This entry is classified **Critical**, with impact ranging depending on which point the failure occurs at and whether the plugin was already active:

- Where a pre-update compatibility check cleanly refuses the update (an updated `Requires PHP` or `Requires at least` the environment does not meet), the impact is typically narrow — the plugin remains on its prior, working version, and the administrator receives a clear explanation.
- Where the file-swap process is interrupted partway, and the plugin was already active, the impact can be severe and immediate: the very next request may attempt to load a plugin file that no longer exists, is truncated, or belongs to a mismatched combination of old and new files, which can produce a site-wide PHP fatal error rather than a contained, single-plugin failure.
- Where an automatic update's own rollback mechanism itself fails to restore the previous version after detecting a fatal error, the site can be left in the same severe, site-wide-fatal state the rollback mechanism exists specifically to prevent.
- This entry remains classified at the level of its most severe possible manifestation, consistent with the range-based Critical classification used elsewhere in this catalog (`WP-ERROR-021`, `024`–`031`).

---

# 6. Distinction

This entry applies only when verified evidence establishes that a specific, deliberately-triggered or automatically-scheduled attempt to update an already-installed regular plugin did not complete correctly, and that the failure occurred at one of the three points Section 4 describes.

**Three internal causes this entry keeps deliberately separate, matching the taxonomy's own declared scope, not blended into a single generic "update failed" condition:**

1. **Package acquisition failure** — either WordPress's own pre-update compatibility check (evaluating the *new* version's own declared `Requires PHP`/`Requires at least` against the running environment, the same class of check `WP-ERROR-031` Section 6 documents for activation) refuses to attempt the update at all, with no download ever occurring; or the update proceeds, a response to the download request is actually received, and the resulting package is corrupt, incomplete, or fails WordPress's own extraction step. This cause presumes the download request itself either was never made (compatibility refusal) or actually completed and returned a response — it does not include a download that never connects or never receives a response at all, which is `WP-ERROR-028`/`029`'s own territory (see below).
2. **File-swap process interruption** — the update package was successfully acquired and extracted, and WordPress proceeds to replace the plugin's old files with the new ones, but this multi-step process is interrupted before it completes — for example, by a PHP execution-time limit, an externally terminated request, or an unexpected process termination — leaving a mixed-version or incomplete set of files. This cause presumes the interruption is *not* attributable to an OS-level permission or capacity constraint, both of which are excluded (see below); it is specifically the update mechanism's own multi-step process failing to run to completion for an unrelated reason.
3. **Automatic-update rollback outcome** — for an *automatic*, unattended background update specifically (not a manually-triggered "Update Now" action, which has no comparable rollback capability), WordPress's own post-update check detects a fatal error immediately after the file swap and attempts to restore the plugin to its pre-update state. This cause covers both the case where that rollback itself fails to restore a working version, and, where relevant to diagnosis, confirming that it succeeded correctly.

It is distinct from:

- **`WP-ERROR-031` — WordPress Plugin Activation Failure**: owns the activation lifecycle stage, a discrete event distinct from update. A plugin can be updated while already active without a fresh activation event occurring, and an update failure does not, by itself, place a plugin into or out of an activation state — the two entries own genuinely independent lifecycle stages, per `SF-TAXONOMY-005` Section 4.
- **`WP-ERROR-017` — WordPress Must-Use Plugin Fatal Error**: must-use plugins have no comparable, WordPress-provided update mechanism at all; a must-use plugin file is typically replaced directly on the filesystem by whoever manages it, outside `Plugin_Upgrader` entirely. This entry's condition cannot occur for a must-use plugin.
- **`WP-ERROR-019` — WordPress Filesystem Permission Denied**: where diagnosis (Section 11) confirms the file-swap interruption (cause 2) is attributable to the operating system denying the specific access the update mechanism required — on the `wp-content/upgrade` staging directory, or on the plugin's own directory — that condition is `WP-ERROR-019`'s own territory, not this entry's, per `SF-TAXONOMY-005` Section 2. This entry owns the observable fact that the update's own multi-step process failed and diagnoses which of the three mechanisms produced that failure; it hands off to `WP-ERROR-019` once a genuine OS-level access denial is confirmed as the specific cause.
- **`WP-ERROR-020` — WordPress Disk Space Exhausted**: where diagnosis confirms the file-swap interruption (cause 2), or the extraction failure within cause 1, is attributable to exhausted volume capacity, quota, or inodes, that condition is `WP-ERROR-020`'s own territory, per `SF-TAXONOMY-005` Section 2. This entry hands off to `WP-ERROR-020` once capacity exhaustion is confirmed as the specific cause, rather than duplicating that entry's own diagnostic or recovery content.
- **`WP-ERROR-028` — WordPress Outbound HTTP Request Failure**: where diagnosis confirms the update's own download request never actually connected to the update-package source at all, that condition is `WP-ERROR-028`'s own territory, per `SF-TAXONOMY-005` Section 2 — that entry's own text already names "plugin/theme/core update checks" and communication with `api.wordpress.org` as an example of its own condition. This entry's cause 1 presumes a response was actually received; a connection that never completes is excluded.
- **`WP-ERROR-029` — WordPress Outbound TLS Negotiation Failure**: where diagnosis confirms the update's own download connection was established but a secure channel could not be negotiated, that condition is `WP-ERROR-029`'s own territory, per `SF-TAXONOMY-005` Section 2, for the same reason as `WP-ERROR-028` above.
- **`WP-ERROR-013` — WordPress Bootstrap PHP Fatal Error**: a site-wide PHP fatal error occurring on a *subsequent* request, after an interrupted update has already left the plugin's files in a mixed or incomplete state, is a distinct, downstream symptom of this entry's own condition, diagnosed and resolved through this entry — not an independent `WP-ERROR-013` condition to chase separately once this entry's own root cause is identified.
- **A specific plugin's own new-version code defect that is not itself a mechanism failure** (`SF-TAXONOMY-005` Section 2, first exclusion bullet): the update mechanism can complete every one of its own steps correctly — package acquired, extracted, files swapped, no fatal error detected — and the plugin's own new code can still contain a genuine business-logic defect. That is the plugin's own defect, outside this entry's own scope entirely, regardless of how disruptive its symptoms are. A second, easily-conflated explanation for the identical symptom — the update mechanism succeeded and the new code is itself correct, but PHP is not yet executing it — is [WP-ERROR-035](WP-ERROR-035-OPCACHE-STALE-BYTECODE.md)'s own condition, not a code defect at all; diagnosis shall distinguish the two before concluding either applies.

---

# 7. Scope

**Covered:** A verified condition in which a deliberate or automatically-scheduled attempt to update a specific, already-installed regular (non-must-use) plugin does not complete correctly, where the verified cause is a pre-update compatibility-gate refusal, a corrupt/incomplete/unextractable downloaded package (once a response was actually received), a non-permission/non-capacity interruption of the file-swap process, or an automatic-update rollback outcome.

**Excluded:**

- Must-use plugin fatal errors, which involve no update mechanism at all (`WP-ERROR-017`).
- Plugin activation failures, a distinct lifecycle stage (`WP-ERROR-031`).
- A download request that never connects to the update-package source at all (`WP-ERROR-028`).
- A download connection that cannot negotiate a secure TLS channel (`WP-ERROR-029`).
- A file-swap interruption attributable to an OS-level permission denial (`WP-ERROR-019`).
- A file-swap or extraction failure attributable to exhausted disk capacity, quota, or inodes (`WP-ERROR-020`).
- A specific plugin's own business-logic defect in its new, post-update code, where the update mechanism itself completed every step correctly.
- WordPress core's own update mechanism, a related but distinct mechanism (`Core_Upgrader`) out of scope for this category.
- Theme update failures, Theme category's own parallel mechanism ([WP-ERROR-040](WP-ERROR-040-THEME-UPDATE-FAILURE.md)).

---

# 8. WordPress Components

Listed as components commonly involved, not as a claim that every installation exercises every one of them:

- `Plugin_Upgrader` and the shared `WP_Upgrader` base class, which coordinate downloading, extracting, and installing an updated plugin package.
- The `wp-content/upgrade` staging directory, used to extract and stage an update's own files before they replace the plugin's live files — the same staging directory `WP-ERROR-019`/`020` already document from the permission/capacity side.
- WordPress's own pre-update compatibility evaluation, comparing a plugin's new version's own declared `Requires PHP`/`Requires at least` header values against the running environment before offering or attempting the update — the update-time counterpart to the activation-time gate `WP-ERROR-031` documents.
- WordPress's own automatic-update mechanism (scheduled, unattended background updates, as distinct from an administrator manually clicking "Update Now"), and its own post-update fatal-error detection and rollback response, available only for that automatic path.
- The Plugins admin screen's own update notification and manual "Update Now" action, and WP-CLI's `wp plugin update` command, both distinct entry points reaching the same underlying `Plugin_Upgrader` mechanism as the automatic path, but without its own rollback capability.
- A brief maintenance-mode window WordPress places some update operations in, intended to reduce the risk of a visitor's request being served mid-update; the specific update contexts (single-plugin, bulk, core) that trigger this window are not asserted here beyond this general description.

---

# 9. Typical Symptoms

- WordPress presenting a message indicating the update package could not be downloaded, extracted, or installed, with the plugin remaining on its prior version.
- The Plugins screen's own "Update Now" action, or a WP-CLI `wp plugin update` command, appearing disabled or reporting an incompatibility notice before any download is attempted, when a pre-update compatibility gate refuses the update.
- A site becoming immediately and completely inaccessible (a site-wide PHP fatal error) on the request following an update attempt, where the previously-active plugin's own files were left in a mixed-version or incomplete state by an interrupted file swap.
- A plugin's version number in `wp-admin` or `wp plugin list` reflecting neither the pre-update nor the intended post-update version, or files present in the plugin's own directory that do not match either version cleanly.
- An email notification WordPress's own automatic-update mechanism sent reporting that an automatic update failed or was rolled back, where automatic updates are enabled for the affected plugin.
- Evidence that a previously-active plugin reverted to its prior version without an administrator manually doing so, following an automatic update — either a successful rollback working as intended, or itself the specific condition under investigation.
- A partial or orphaned `wp-content/upgrade` staging directory left behind from a previous, incomplete update attempt.

---

# 10. Common Causes

Causes are grouped by category. Inclusion in this list identifies a category as plausible; it does not assert that any specific one is present without diagnostic confirmation.

- The plugin's new version declares a `Requires PHP` or `Requires at least` value the running environment does not meet, and WordPress's own pre-update compatibility check refuses to attempt the update.
- The update-package source (commonly the WordPress.org plugin repository, or a third-party update endpoint for a commercially licensed plugin) returns a response, but the response body is corrupt, incomplete, or not a valid archive — for example, an interrupted transfer that nonetheless completed the HTTP request/response cycle, or a server-side error page returned with a `200` status instead of the expected package.
- WordPress's own extraction step fails against an otherwise successfully-downloaded archive, due to an unsupported or unexpected archive format or internal structure.
- A PHP execution-time limit is reached partway through the file-swap step, particularly for a plugin with an unusually large number of files, terminating the update process before every file has been replaced.
- The web server or hosting platform terminates the request performing the update (a timeout, a deployment/restart event, a resource limit) partway through the file-swap step.
- An automatic background update's own post-update fatal-error check correctly detects a genuine incompatibility in the new version and attempts rollback, which itself then fails — for example, because the same interruption class affecting cause 2 also affects the rollback's own file operations.
- A third-party or commercially licensed plugin's own update endpoint (not the WordPress.org repository) is temporarily unavailable, misconfigured, or returns an unexpected response format WordPress's own update mechanism does not handle as gracefully as it handles the WordPress.org repository's own well-defined format.
- A previous, incomplete update attempt left the `wp-content/upgrade` staging directory in a state that interferes with a subsequent attempt.

---

# 11. Diagnosis

Verify the following, starting from the least invasive observation and narrowing only once the general shape of the failure is established:

1. **Confirm the update actually failed to complete correctly.** Check the plugin's current installed version directly (the Plugins screen, or `wp plugin list` via WP-CLI) and confirm whether the site is currently functioning; a plugin that updated successfully and now behaves differently due to a genuine defect in its own new code is not this entry's condition (see Section 6).
2. **Capture the exact message WordPress itself presented**, or, where the failure manifests as a site-wide fatal error on a subsequent request rather than a message at update time, capture that fatal error's own details per `WP-ERROR-013`'s own Diagnosis Section 11 steps 2–8, while treating the update itself — not an independent bootstrap condition — as the primary object of this diagnosis.
3. **Determine which of the three points in Section 6 the failure occurred at**, before investigating any specific underlying mechanism: whether a pre-update compatibility notice appeared before any download was attempted (cause 1a); whether a download response was received but the resulting package or its extraction failed (cause 1b) as opposed to the download never connecting or completing at all; whether the plugin's own files are now in a mixed-version or incomplete state consistent with an interrupted file swap (cause 2); or whether evidence indicates an automatic update's own rollback was attempted (cause 3).
4. **Only once the general point of failure is established, investigate the specific mechanism responsible:**
   - Where a pre-update compatibility refusal (cause 1a) is confirmed, identify which specific requirement is unmet and evaluate against `WP-ERROR-015` (PHP version), consistent with `WP-ERROR-031`'s own equivalent step for activation.
   - Where a download or extraction failure (cause 1b) is confirmed, first rule out that the download request itself ever failed to connect or to negotiate TLS — evaluate against `WP-ERROR-028`/`029` — before concluding the failure is in the package's own integrity or WordPress's own extraction step.
   - Where a file-swap interruption (cause 2) is confirmed by mixed-version or incomplete files, first rule out an OS-level permission denial (evaluate against `WP-ERROR-019`) and exhausted capacity (evaluate against `WP-ERROR-020`) before concluding the interruption is attributable to an execution-time limit, an externally terminated request, or another cause unrelated to access or capacity.
   - Where an automatic-update rollback outcome (cause 3) is under diagnosis, confirm from available logs or email notifications whether a rollback was attempted at all, and if so, whether it succeeded, before assuming the current broken state is the direct result of the original update failure rather than of the rollback attempt itself.
5. Preserve the current state of the plugin's own directory, relevant logs, and any WordPress-generated notification before making any further change.
6. Where the site is currently inaccessible due to a downstream bootstrap fatal error (per Section 6's own cross-reference to `WP-ERROR-013`), prioritize restoring a working state — per this entry's own Recovery Procedure — before completing a full root-cause investigation, consistent with `WP-ERROR-013`'s own filesystem-level diagnostic approach that does not depend on `wp-admin` access.
7. Where an update was performed via WP-CLI or an automated deployment pipeline, check the pipeline's own logs and exit status directly, since a failure may not be visible through `wp-admin` if the pipeline itself never re-checks the site's resulting state.
8. Inventory the plugin's own directory contents against the expected file manifest for both the prior and intended versions where feasible, to determine precisely how far the interrupted file-swap actually progressed.

---

# 12. Recovery Procedure

Recovery shall target the specific, verified cause identified in Diagnosis (Section 11), prioritizing restoration of a working state where the site is currently inaccessible.

Permitted recovery categories, depending on the verified cause, include:

- Where the site is currently inaccessible due to a mixed-version or incomplete file set (cause 2), restoring the plugin's own directory to a complete, known-good state — either the complete prior version (from backup or a fresh download of that version) or the complete new version (by re-running or manually completing the update) — rather than leaving a partial file set in place while investigating further.
- Where a pre-update compatibility refusal is confirmed, satisfying the actual unmet requirement per `WP-ERROR-015`'s own recovery procedure, or deferring the update until the environment is updated.
- Where a download/extraction failure traces to a genuine connectivity or TLS condition, resolving that per `WP-ERROR-028`'s or `WP-ERROR-029`'s own recovery procedure, then retrying the update.
- Where a file-swap interruption traces to a permission or capacity condition, resolving that per `WP-ERROR-019`'s or `WP-ERROR-020`'s own recovery procedure, then retrying the update.
- Where a file-swap interruption traces to an execution-time limit or an externally terminated request unrelated to permission or capacity, addressing that specific resource or infrastructure constraint (for example, adjusting a PHP execution-time limit for update operations, or performing the update via WP-CLI outside a request-lifetime-constrained context) before retrying.
- Where an automatic-update rollback failed to restore a working version, manually restoring the plugin's own prior, known-good version from backup or version control, then investigating why the rollback mechanism itself did not succeed.
- Removing an orphaned or incomplete `wp-content/upgrade` staging directory left behind by a previous failed attempt, where diagnosis confirms it is interfering with a subsequent one.

Recovery shall not leave a mixed-version or incomplete plugin file set in place on a live, publicly accessible site while further diagnosis is performed; restoring a complete, consistent state (either version) takes priority over completing root-cause analysis, consistent with `WP-ERROR-013`'s own general recovery priority for a site-wide-impacting condition.

---

# 13. Validation

Recovery is successful when:

- The plugin's own directory contains a complete, internally consistent set of files matching exactly one version (either the restored prior version or the successfully completed new version), not a mixture of both.
- The site is confirmed accessible and functioning across the request paths the original failure affected, not only that the update process itself reports success.
- Where the cause was a pre-update compatibility refusal, the corrected requirement is confirmed independently before the update is retried and confirmed to complete.
- Where the cause was a download, extraction, permission, or capacity condition, that underlying condition's own validation criteria (per `WP-ERROR-028`/`029`/`019`/`020` as applicable) are independently satisfied, not merely that a subsequent update attempt happened to succeed.
- No orphaned staging artifact remains in `wp-content/upgrade` that could interfere with a future update.
- Where an automatic-update rollback was involved, the plugin's current version and functioning state are explicitly confirmed, not merely assumed from the absence of a further failure notification.
- No unrelated plugin, theme, or configuration was altered or lost in the course of recovery.

---

# 14. Prevention

- Test plugin updates in a staging environment matching production's PHP version, WordPress version, and active-plugin set before applying them to production, particularly for plugins not sourced from the WordPress.org repository.
- Maintain current backups, taken before a significant update, sufficient to restore a plugin's own prior version quickly if an update fails destructively.
- Monitor automatic-update failure and rollback notifications actively, rather than discovering a failed automatic update only when a site-wide outage is separately reported.
- Where a hosting environment imposes a restrictive PHP execution-time limit, verify it is sufficient for the specific plugins in use, particularly any with an unusually large number of files, before relying on the default update flow.
- Avoid interrupting an in-progress update (closing a browser tab, restarting a server, redeploying a container) once it has begun, since doing so is a common, avoidable cause of the file-swap interruption this entry documents.
- Periodically check for, and clear, orphaned artifacts in `wp-content/upgrade` left behind by any prior failed update attempt.

---

# 15. Security Considerations

- Do not disable WordPress's own pre-update compatibility check or automatic-update rollback mechanism as a troubleshooting shortcut; both exist specifically to prevent an incompatible or broken update from being left in place.
- Verify the source and integrity of a plugin update obtained from outside the official WordPress.org repository before applying it, particularly for a commercially licensed plugin whose own update endpoint this entry's diagnostic procedure does not itself vouch for the trustworthiness of.
- Where an update is performed via WP-CLI in an automated deployment pipeline, ensure a failed update causes the pipeline to fail visibly and block further deployment steps, rather than proceeding as though the update succeeded.
- An interrupted update leaving a site in a broken, publicly visible state is itself an availability concern; prioritize restoring a working state (per Section 12) before treating this as solely a data-integrity investigation.
- Preserve a copy of a plugin's own directory state before attempting recovery where the cause of the original interruption is not yet fully understood, in case further investigation is needed.

---

# 16. Related Errors

The following are cited as they exist in this repository, or as conceptual distinctions where noted.

1. [WP-ERROR-031 — WordPress Plugin Activation Failure](WP-ERROR-031-PLUGIN-ACTIVATION-FAILURE.md) — exists in this repository; see Section 6 (Distinction) above for how the two lifecycle stages remain independent.
2. [WP-ERROR-017 — WordPress Must-Use Plugin Fatal Error](WP-ERROR-017-MUST-USE-PLUGIN-FATAL-ERROR.md) — exists in this repository; see Section 6 (Distinction) above for why must-use plugins cannot be the subject of this entry's own condition.
3. [WP-ERROR-019 — WordPress Filesystem Permission Denied](WP-ERROR-019-FILESYSTEM-PERMISSION-DENIED.md) — exists in this repository; see Section 6 (Distinction) above for the diagnose-then-hand-off relationship.
4. [WP-ERROR-020 — WordPress Disk Space Exhausted](WP-ERROR-020-DISK-SPACE-EXHAUSTED.md) — exists in this repository; see Section 6 (Distinction) above for the diagnose-then-hand-off relationship.
5. [WP-ERROR-028 — WordPress Outbound HTTP Request Failure](WP-ERROR-028-WORDPRESS-OUTBOUND-HTTP-REQUEST-FAILURE.md) — exists in this repository; see Section 6 (Distinction) above for the diagnose-then-hand-off relationship.
6. [WP-ERROR-029 — WordPress Outbound TLS Negotiation Failure](WP-ERROR-029-OUTBOUND-TLS-NEGOTIATION-FAILURE.md) — exists in this repository; see Section 6 (Distinction) above for the diagnose-then-hand-off relationship.
7. [WP-ERROR-013 — WordPress Bootstrap PHP Fatal Error](WP-ERROR-013-WORDPRESS-BOOTSTRAP-PHP-FATAL-ERROR.md) — exists in this repository; see Section 6 (Distinction) above for how a downstream bootstrap fatal error remains this entry's own condition to diagnose and resolve, not an independent one.
8. [WP-ERROR-015 — Unsupported PHP Version](WP-ERROR-015-UNSUPPORTED-PHP-VERSION.md) — exists in this repository; see Section 6 (Distinction) above for the diagnose-then-hand-off relationship for cause 1a.

---

# 17. Notes

This entry documents the general, verified observable condition of WordPress's own plugin-update mechanism failing to bring an already-installed plugin to a newer version correctly, distinguishing the three mechanically distinct points — package acquisition, file-swap execution, and automatic-update rollback — at which that failure can occur. It is the third and final planned entry in the Plugin category, per `SF-TAXONOMY-005` Section 3.

This entry's own drafting required a correction to `SF-TAXONOMY-005` itself before authoring began (Version 1.1 → 1.2): the taxonomy's original text did not account for `WP-ERROR-019`/`020`/`028`/`029` already explicitly claiming the permission, capacity, connection, and TLS dimensions of an update's own failure. This entry was drafted against the corrected Version 1.2 boundary, which narrows this entry to the update mechanism's own process as the diagnostic entry point, handing off to those four entries once a specific root cause is confirmed.

Consistent with the single-responsibility principle in **SF-SPEC-001** Section 4.3, this entry does not claim ownership of the underlying permission, capacity, connectivity, TLS, or PHP-version conditions its own causes may trace to, nor of a specific plugin's own business-logic defect once the update mechanism itself has completed successfully.

This entry reached `Production Ready` via `SF-REVIEW-092` (Class A author review; no findings) and `SF-REVIEW-093` (Class B independent review; one Minor finding — IF-1, a cross-document completeness gap in `WP-ERROR-013`'s own Common Causes list, corrected within that same review) per **SF-SPEC-001** Section 19, **SF-SPEC-005** Section 5.6, and **SF-SPEC-012**.

No Reference Implementation is currently designated by **SF-SPEC-001**; this entry's relationship to that designation, and to any future `WP-SCENARIO-XXX` runtime evidence, is not asserted here and shall not be assumed until such evidence or designation actually exists.
