# WP-ERROR-040 — WordPress Theme Update Failure

---

# 1. Knowledge Entry

WordPress Theme Update Failure

---

# 2. Metadata

* **Error ID:** `WP-ERROR-040`
* **Title:** WordPress Theme Update Failure
* **Category:** Theme
* **Severity:** Critical
* **Recovery Priority:** Immediate
* **Status:** Production Ready
* **Version:** 1.0

---

# 3. Summary

WordPress fails to bring an already-installed theme to a newer version correctly, through one of three mechanically distinct points in WordPress's own theme-update mechanism (`Theme_Upgrader`, sharing the same underlying `WP_Upgrader` machinery and `wp-content/upgrade` staging directory `Plugin_Upgrader` uses): a pre-update compatibility gate refuses the update outright, or a response to the download request is received but the resulting package is corrupt or fails extraction; the multi-step file-swap process replacing the theme's old files with new ones is interrupted before completing, for a reason unrelated to an OS-level permission or capacity constraint; or, for an automatic background update specifically, WordPress's own post-update fatal-error check detects a problem and its rollback mechanism either succeeds or itself fails to restore the theme's prior version.

---

# 4. Primary Failure Mode

A deliberate ("Update Now") or automatically-scheduled attempt to update a specific, already-installed theme does not complete correctly. The failure occurs at one of three points: before any download occurs (a pre-update compatibility refusal) or after a response is received but the package itself is unusable; during the multi-step process of replacing the theme's old files with the new version's files, which is interrupted partway through; or, specific to an automatic background update, during WordPress's own post-update fatal-error detection and rollback attempt. This entry applies equally whether the theme being updated is currently the site's active theme or an inactive, installed-but-unused theme — WordPress's own update mechanism does not distinguish between the two — though the practical consequence of an interrupted file-swap differs sharply between the two cases (see Section 5).

---

# 5. Severity

This entry is classified **Critical**, with impact ranging depending on the specific cause and, distinctly from `WP-ERROR-032`'s own equivalent reasoning, on whether the theme being updated is currently active:

- Where a pre-update compatibility refusal occurs, the impact is narrow — no download is attempted, the theme remains on its prior, working version, and the rest of the site is entirely unaffected regardless of whether the theme is active.
- Where a file-swap interruption leaves an **inactive** theme's files in a mixed-version or incomplete state, the impact is narrow and contained: the theme is unusable until repaired, but the site's actual running configuration is unaffected, since no request loads an inactive theme's files.
- Where a file-swap interruption leaves the site's **currently active** theme's files in a mixed-version or incomplete state, the impact is severe: the same broken, internally inconsistent code is guaranteed to load again on the very next request, commonly producing a site-wide PHP fatal error — the identical risk mechanism `WP-ERROR-032` Section 5 already documents for an already-active plugin. Because a theme update is commonly performed specifically to update the site's live, currently-active theme (unlike a plugin update, where the updated plugin may or may not be active), this worst-case path is reached in practice more routinely for Theme than the equivalent path is for Plugin, though the two entries' own maximum severity is otherwise comparable in kind, not worse in mechanism.
- This entry remains classified at the level of its most severe possible manifestation, consistent with the range-based Critical classification used elsewhere in this catalog (`WP-ERROR-021`, `024`–`030`, `032`–`033`, `035`, `038`–`039`).

---

# 6. Distinction

This entry applies only when verified evidence establishes that a deliberate or automatically-scheduled attempt to update a specific, already-installed theme did not complete correctly, and that the failure occurred at one of the three points Section 4 describes.

**Three internal causes this entry keeps deliberately separate, matching the taxonomy's own declared scope, mirroring `WP-ERROR-032`'s own three-cause structure for plugin update:**

1. **Package acquisition failure** — either WordPress's own pre-update compatibility check (evaluating the *new* version's own declared `Requires PHP`/`Requires at least` against the running environment, the theme-update-time counterpart to the requirement gate `WP-ERROR-039` Section 6 documents for switching) refuses to attempt the update at all, with no download ever occurring; or the update proceeds, a response to the download request is actually received, and the resulting package is corrupt, incomplete, or fails WordPress's own extraction step. This cause presumes a response was actually received; a download that never connects or never receives a response at all is `WP-ERROR-028`/`029`'s own territory (see below).
2. **File-swap process interruption** — the update package was successfully acquired and extracted, and WordPress proceeds to replace the theme's old files with the new ones, but this multi-step process is interrupted before it completes — for example, by a PHP execution-time limit, an externally terminated request, or an unexpected process termination — leaving a mixed-version or incomplete set of files. This cause presumes the interruption is *not* attributable to an OS-level permission or capacity constraint, both of which are excluded (see below).
3. **Automatic-update rollback outcome** — for an *automatic*, unattended background update specifically (available for themes since WordPress 5.6, the same release that extended it to plugins), WordPress's own post-update check detects a fatal error immediately after the file swap and attempts to restore the theme to its pre-update state. This cause covers both the case where that rollback itself fails to restore a working version, and, where relevant to diagnosis, confirming that it succeeded correctly.

It is distinct from:

- **`WP-ERROR-039` — WordPress Theme Activation (Switching) Failure**: owns the switching lifecycle stage, a discrete event distinct from update. A theme can be updated while already active without a fresh switch event occurring, and an update failure does not, by itself, place a theme into or out of the active state — the two entries own genuinely independent lifecycle stages, per `SF-TAXONOMY-008` Section 4.
- **`WP-ERROR-019` — WordPress Filesystem Permission Denied**: where diagnosis (Section 11) confirms the file-swap interruption (cause 2) is attributable to the operating system denying the specific access the update mechanism required — on the `wp-content/upgrade` staging directory, or on the theme's own directory — that condition is `WP-ERROR-019`'s own territory, not this entry's, per `SF-TAXONOMY-008` Section 2. This entry hands off to `WP-ERROR-019` once a genuine OS-level access denial is confirmed as the specific cause.
- **`WP-ERROR-020` — WordPress Disk Space Exhausted**: where diagnosis confirms the file-swap interruption (cause 2), or the extraction failure within cause 1, is attributable to exhausted volume capacity, quota, or inodes, that condition is `WP-ERROR-020`'s own territory, per `SF-TAXONOMY-008` Section 2. This entry hands off to `WP-ERROR-020` once capacity exhaustion is confirmed as the specific cause.
- **`WP-ERROR-028` — WordPress Outbound HTTP Request Failure**: where diagnosis confirms the update's own download request never actually connected to the update-package source at all, that condition is `WP-ERROR-028`'s own territory, per `SF-TAXONOMY-008` Section 2 — that entry's own text already names "plugin/theme/core update checks" as an example of its own condition. This entry's cause 1 presumes a response was actually received; a connection that never completes is excluded.
- **`WP-ERROR-029` — WordPress Outbound TLS Negotiation Failure**: where diagnosis confirms the update's own download connection was established but a secure channel could not be negotiated, that condition is `WP-ERROR-029`'s own territory, per `SF-TAXONOMY-008` Section 2, established through that entry's own general scope rather than an explicit "theme" mention, the same basis `WP-ERROR-032` Section 6 already relies on for the identical relationship in Plugin.
- **`WP-ERROR-015` — Unsupported PHP Version**: where diagnosis confirms cause 1's pre-update compatibility refusal is specifically a `Requires PHP` mismatch, the underlying PHP-version condition and its own recovery are `WP-ERROR-015`'s own territory.
- **`WP-ERROR-013` — WordPress Bootstrap PHP Fatal Error**: a site-wide PHP fatal error occurring on a *subsequent* request, after an interrupted update has already left the currently-active theme's files in a mixed or incomplete state, is a distinct, downstream symptom of this entry's own condition, diagnosed and resolved through this entry — not an independent `WP-ERROR-013` condition to chase separately, the identical resolution model `WP-ERROR-032` Section 6 and `WP-ERROR-039` Section 6 both already establish for their own respective conditions.
- **A specific theme's own new-version code defect that is not itself a mechanism failure** (`SF-TAXONOMY-008` Section 2, first exclusion bullet): the update mechanism can complete every one of its own steps correctly — package acquired, extracted, files swapped, no fatal error detected — and the theme's own new code can still contain a genuine template-rendering or business-logic defect. That is the theme's own defect, outside this entry's own scope entirely.

---

# 7. Scope

**Covered:** A verified condition in which a deliberate or automatically-scheduled attempt to update a specific, already-installed theme does not complete correctly, where the verified cause is a pre-update compatibility-gate refusal, a corrupt/incomplete/unextractable downloaded package (once a response was actually received), a non-permission/non-capacity interruption of the file-swap process, or an automatic-update rollback outcome.

**Excluded:**

- Theme activation (switching) failures, a distinct lifecycle stage (`WP-ERROR-039`).
- A download request that never connects to the update-package source at all (`WP-ERROR-028`).
- A download connection that cannot negotiate a secure TLS channel (`WP-ERROR-029`).
- A file-swap interruption attributable to an OS-level permission denial (`WP-ERROR-019`).
- A file-swap or extraction failure attributable to exhausted disk capacity, quota, or inodes (`WP-ERROR-020`).
- A specific theme's own business-logic or template-rendering defect in its new, post-update code, where the update mechanism itself completed every step correctly.
- WordPress core's own update mechanism, a related but distinct mechanism (`Core_Upgrader`) out of scope for this category.
- Plugin update failures, Plugin category's own parallel mechanism (`WP-ERROR-032`).

---

# 8. WordPress Components

Listed as components commonly involved, not as a claim that every installation exercises every one of them:

- `Theme_Upgrader` and the shared `WP_Upgrader` base class, which coordinate downloading, extracting, and installing an updated theme package — the same base class `Plugin_Upgrader` uses.
- The `wp-content/upgrade` staging directory, used to extract and stage an update's own files before they replace the theme's live files — the same staging directory `WP-ERROR-019`/`020` already document from the permission/capacity side, and that `WP-ERROR-032` Section 8 already documents for the plugin case.
- WordPress's own pre-update compatibility evaluation, comparing a theme's new version's own declared `Requires PHP`/`Requires at least` header values against the running environment before offering or attempting the update.
- WordPress's own automatic-update mechanism (available for themes since WordPress 5.6, as distinct from an administrator manually clicking "Update Now"), and its own post-update fatal-error detection and rollback response, available only for that automatic path.
- The Themes admin screen's own update notification and manual "Update Now" action, and WP-CLI's `wp theme update` command, both distinct entry points reaching the same underlying `Theme_Upgrader` mechanism as the automatic path, but without its own rollback capability.
- A brief maintenance-mode window WordPress places some update operations in, the same general mechanism `WP-ERROR-032` Section 8 already describes for the plugin case.

---

# 9. Typical Symptoms

- WordPress presenting a message indicating the update package could not be downloaded, extracted, or installed, with the theme remaining on its prior version.
- The Themes screen's own update notification, or a WP-CLI `wp theme update` command, reporting an incompatibility notice before any download is attempted, when a pre-update compatibility gate refuses the update.
- A site becoming immediately and completely inaccessible (a site-wide PHP fatal error) on the request following an update attempt, where the update targeted the site's own currently-active theme and its files were left in a mixed-version or incomplete state by an interrupted file swap.
- A theme's version number in `wp-admin` or `wp theme list` reflecting neither the pre-update nor the intended post-update version, or files present in the theme's own directory that do not match either version cleanly.
- An email notification WordPress's own automatic-update mechanism sent reporting that an automatic theme update failed or was rolled back.
- Evidence that a previously-active theme's files reverted to their prior version without an administrator manually doing so, following an automatic update — either a successful rollback working as intended, or itself the specific condition under investigation.

---

# 10. Common Causes

Causes are grouped by category. Inclusion in this list identifies a category as plausible; it does not assert that any specific one is present without diagnostic confirmation.

- The theme's new version declares a `Requires PHP` or `Requires at least` value the running environment does not meet, and WordPress's own pre-update compatibility check refuses to attempt the update.
- The update-package source (commonly the WordPress.org theme repository, or a third-party update endpoint for a commercially licensed theme) returns a response, but the response body is corrupt, incomplete, or not a valid archive.
- WordPress's own extraction step fails against an otherwise successfully-downloaded archive, due to an unsupported or unexpected archive format or internal structure.
- A PHP execution-time limit is reached partway through the file-swap step, particularly for a theme with an unusually large number of files (bundled fonts, images, or a large template library), terminating the update process before every file has been replaced.
- The web server or hosting platform terminates the request performing the update partway through the file-swap step.
- An automatic background update's own post-update fatal-error check correctly detects a genuine incompatibility in the new version and attempts rollback, which itself then fails.
- A third-party or commercially licensed theme's own update endpoint (not the WordPress.org repository) is temporarily unavailable, misconfigured, or returns an unexpected response format.
- A previous, incomplete update attempt left the `wp-content/upgrade` staging directory in a state that interferes with a subsequent attempt.

---

# 11. Diagnosis

Verify the following, starting from the least invasive observation and narrowing only once the general shape of the failure is established:

1. **Confirm the update actually failed to complete correctly.** Check the theme's current installed version directly (the Themes screen, or `wp theme list` via WP-CLI) and confirm whether the site is currently functioning; a theme that updated successfully and now renders differently due to a genuine defect in its own new code is not this entry's condition (see Section 6).
2. **Determine first whether the affected theme is, or was, the site's currently active theme.** This single fact determines whether the worst-case severity path (Section 5) applies and shapes the recovery priority (Section 12).
3. **Capture the exact message WordPress itself presented**, or, where the failure manifests as a site-wide fatal error on a subsequent request rather than a message at update time, capture that fatal error's own details per `WP-ERROR-013`'s own Diagnosis Section 11 steps 2–8, while treating the update itself — not an independent bootstrap condition — as the primary object of this diagnosis.
4. **Determine which of the three points in Section 6 the failure occurred at**: whether a pre-update compatibility notice appeared before any download was attempted (cause 1a); whether a download response was received but the resulting package or its extraction failed (cause 1b), as opposed to the download never connecting or completing at all; whether the theme's own files are now in a mixed-version or incomplete state consistent with an interrupted file swap (cause 2); or whether evidence indicates an automatic update's own rollback was attempted (cause 3).
5. **Only once the general point of failure is established, investigate the specific mechanism responsible:**
   - Where a pre-update compatibility refusal (cause 1a) is confirmed, identify which specific requirement is unmet and evaluate against `WP-ERROR-015`.
   - Where a download or extraction failure (cause 1b) is confirmed, first rule out that the download request itself ever failed to connect or negotiate TLS — evaluate against `WP-ERROR-028`/`029` — before concluding the failure is in the package's own integrity or WordPress's own extraction step.
   - Where a file-swap interruption (cause 2) is confirmed by mixed-version or incomplete files, first rule out an OS-level permission denial (evaluate against `WP-ERROR-019`) and exhausted capacity (evaluate against `WP-ERROR-020`) before concluding the interruption is attributable to an execution-time limit or another unrelated cause.
   - Where an automatic-update rollback outcome (cause 3) is under diagnosis, confirm from available logs or email notifications whether a rollback was attempted at all, and if so, whether it succeeded.
6. Preserve the current state of the theme's own directory, relevant logs, and any WordPress-generated notification before making any further change.
7. Where the site is currently inaccessible due to a downstream bootstrap fatal error affecting the currently-active theme, prioritize restoring a working state per this entry's own Recovery Procedure before completing a full root-cause investigation.
8. Inventory the theme's own directory contents against the expected file manifest for both the prior and intended versions where feasible, to determine precisely how far an interrupted file-swap actually progressed.

---

# 12. Recovery Procedure

Recovery shall target the specific, verified cause identified in Diagnosis (Section 11), prioritizing restoration of a working state where the site is currently inaccessible because the affected theme was active.

Permitted recovery categories, depending on the verified cause, include:

- Where the site is currently inaccessible due to a mixed-version or incomplete file set on the active theme (cause 2), restoring the theme's own directory to a complete, known-good state — either the complete prior version or the complete new version — rather than leaving a partial file set in place while investigating further. Where `wp-admin` itself is inaccessible due to the resulting fatal error, this may require direct filesystem access or WP-CLI, including switching to a different, known-good theme (per `WP-ERROR-039`'s own recovery mechanism) as an immediate mitigation while the affected theme's own files are repaired.
- Where a pre-update compatibility refusal is confirmed, satisfying the actual unmet requirement per `WP-ERROR-015`'s own recovery procedure, or deferring the update until the environment is updated.
- Where a download/extraction failure traces to a genuine connectivity or TLS condition, resolving that per `WP-ERROR-028`'s or `WP-ERROR-029`'s own recovery procedure, then retrying the update.
- Where a file-swap interruption traces to a permission or capacity condition, resolving that per `WP-ERROR-019`'s or `WP-ERROR-020`'s own recovery procedure, then retrying the update.
- Where a file-swap interruption traces to an execution-time limit or an externally terminated request unrelated to permission or capacity, addressing that specific resource or infrastructure constraint before retrying.
- Where an automatic-update rollback failed to restore a working version, manually restoring the theme's own prior, known-good version from backup or version control, then investigating why the rollback mechanism itself did not succeed.
- Removing an orphaned or incomplete `wp-content/upgrade` staging directory left behind by a previous failed attempt, where diagnosis confirms it is interfering with a subsequent one.

Recovery shall not leave a mixed-version or incomplete theme file set in place on a live, publicly accessible site while further diagnosis is performed, particularly where that theme is the site's active theme; restoring a complete, consistent state (either version, or switching to a known-good alternative theme) takes priority over completing root-cause analysis.

---

# 13. Validation

Recovery is successful when:

- The theme's own directory contains a complete, internally consistent set of files matching exactly one version, not a mixture of both.
- The site is confirmed accessible and functioning across the request paths the original failure affected, not only that the update process itself reports success.
- Where the cause was a pre-update compatibility refusal, the corrected requirement is confirmed independently before the update is retried and confirmed to complete.
- Where the cause was a download, extraction, permission, or capacity condition, that underlying condition's own validation criteria (per `WP-ERROR-028`/`029`/`019`/`020` as applicable) are independently satisfied.
- No orphaned staging artifact remains in `wp-content/upgrade` that could interfere with a future update.
- Where an automatic-update rollback was involved, the theme's current version and functioning state are explicitly confirmed.
- No unrelated plugin, theme, or configuration was altered or lost in the course of recovery.

---

# 14. Prevention

- Test theme updates in a staging environment matching production's PHP version, WordPress version, and active-plugin set before applying them to production, particularly for the site's own currently-active theme and for themes not sourced from the WordPress.org repository.
- Maintain current backups, taken before a significant update, sufficient to restore a theme's own prior version quickly if an update fails destructively — treating this as higher-priority specifically when the theme being updated is the site's currently active theme, given the elevated likelihood of reaching this entry's own worst-case severity path (Section 5).
- Monitor automatic-update failure and rollback notifications actively, rather than discovering a failed automatic update only when a site-wide outage is separately reported.
- Where a hosting environment imposes a restrictive PHP execution-time limit, verify it is sufficient for the specific theme in use, particularly a theme with a large number of bundled files, before relying on the default update flow.
- Avoid interrupting an in-progress update once it has begun, since doing so is a common, avoidable cause of the file-swap interruption this entry documents.
- Periodically check for, and clear, orphaned artifacts in `wp-content/upgrade` left behind by any prior failed update attempt.

---

# 15. Security Considerations

- Do not disable WordPress's own pre-update compatibility check or automatic-update rollback mechanism as a troubleshooting shortcut; both exist specifically to prevent an incompatible or broken update from being left in place.
- Verify the source and integrity of a theme update obtained from outside the official WordPress.org repository before applying it, particularly for a commercially licensed theme whose own update endpoint this entry's diagnostic procedure does not itself vouch for the trustworthiness of.
- Where an update is performed via WP-CLI in an automated deployment pipeline, ensure a failed update causes the pipeline to fail visibly and block further deployment steps, rather than proceeding as though the update succeeded.
- An interrupted update leaving the site's active theme in a broken, publicly visible state is itself an availability concern; prioritize restoring a working state (per Section 12) before treating this as solely a data-integrity investigation.
- Preserve a copy of the theme's own directory state before attempting recovery where the cause of the original interruption is not yet fully understood, in case further investigation is needed.

---

# 16. Related Errors

The following are cited as they exist in this repository, or as conceptual distinctions where noted.

1. [WP-ERROR-039 — WordPress Theme Activation (Switching) Failure](WP-ERROR-039-THEME-ACTIVATION-FAILURE.md) — exists in this repository; see Section 6 (Distinction) above for how the two lifecycle stages remain independent.
2. [WP-ERROR-019 — WordPress Filesystem Permission Denied](WP-ERROR-019-FILESYSTEM-PERMISSION-DENIED.md) — exists in this repository; see Section 6 (Distinction) above for the diagnose-then-hand-off relationship.
3. [WP-ERROR-020 — WordPress Disk Space Exhausted](WP-ERROR-020-DISK-SPACE-EXHAUSTED.md) — exists in this repository; see Section 6 (Distinction) above for the diagnose-then-hand-off relationship.
4. [WP-ERROR-028 — WordPress Outbound HTTP Request Failure](WP-ERROR-028-WORDPRESS-OUTBOUND-HTTP-REQUEST-FAILURE.md) — exists in this repository; see Section 6 (Distinction) above for the diagnose-then-hand-off relationship.
5. [WP-ERROR-029 — WordPress Outbound TLS Negotiation Failure](WP-ERROR-029-OUTBOUND-TLS-NEGOTIATION-FAILURE.md) — exists in this repository; see Section 6 (Distinction) above for the diagnose-then-hand-off relationship.
6. [WP-ERROR-015 — Unsupported PHP Version](WP-ERROR-015-UNSUPPORTED-PHP-VERSION.md) — exists in this repository; see Section 6 (Distinction) above for the diagnose-then-hand-off relationship for cause 1a.
7. [WP-ERROR-013 — WordPress Bootstrap PHP Fatal Error](WP-ERROR-013-WORDPRESS-BOOTSTRAP-PHP-FATAL-ERROR.md) — exists in this repository; see Section 6 (Distinction) above for how a downstream bootstrap fatal error remains this entry's own condition to diagnose and resolve, not an independent one.
8. [WP-ERROR-032 — WordPress Plugin Update Failure](WP-ERROR-032-PLUGIN-UPDATE-FAILURE.md) — exists in this repository; the direct structural parallel this entry mirrors, sharing the same `WP_Upgrader` mechanism and `wp-content/upgrade` staging directory.

---

# 17. Notes

This entry documents the general, verified observable condition of WordPress's own theme-update mechanism failing to bring an already-installed theme to a newer version correctly, distinguishing the three mechanically distinct points — package acquisition, file-swap execution, and automatic-update rollback — at which that failure can occur, the direct structural parallel to `WP-ERROR-032`'s own equivalent structure for plugins. It is the second and final planned entry in the Theme category, per `SF-TAXONOMY-008` Section 3.

Consistent with the single-responsibility principle in **SF-SPEC-001** Section 4.3, this entry does not claim ownership of the underlying filesystem, networking, or PHP-version conditions its own causes may trace to (`WP-ERROR-019`/`020`/`028`/`029`/`015`), nor of a specific theme's own template-rendering or business-logic defect once an update itself has succeeded.

This entry's relationship to a `Production Ready` designation is established via `SF-REVIEW-117` (Class A author review) and `SF-REVIEW-118` (Class B independent review) per **SF-SPEC-001** Section 19, **SF-SPEC-005** Section 5.6, and **SF-SPEC-012**.

No Reference Implementation is currently designated by **SF-SPEC-001**; this entry's relationship to that designation, and to any future `WP-SCENARIO-XXX` runtime evidence, is not asserted here and shall not be assumed until such evidence or designation actually exists.
