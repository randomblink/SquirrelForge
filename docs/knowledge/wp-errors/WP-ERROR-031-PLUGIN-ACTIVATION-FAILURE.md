# WP-ERROR-031 — WordPress Plugin Activation Failure

---

# 1. Knowledge Entry

WordPress Plugin Activation Failure

---

# 2. Metadata

* **Error ID:** `WP-ERROR-031`
* **Title:** WordPress Plugin Activation Failure
* **Category:** Plugin
* **Severity:** Critical
* **Recovery Priority:** Immediate
* **Status:** Production Ready
* **Version:** 1.0

---

# 3. Summary

WordPress fails to bring a regular (non-must-use) plugin into an active state when activation is attempted, through one of three mechanically distinct points in the activation mechanism: WordPress's own pre-activation requirement check refuses to attempt activation at all; a PHP fatal error occurs while WordPress includes the plugin's own file as part of processing the activation request, which WordPress's own activation-time protection detects and responds to by leaving the plugin inactive rather than silently marking a broken plugin active; or the plugin's own `register_activation_hook()` callback itself fails, throws, or halts after the plugin's file has already loaded successfully.

---

# 4. Primary Failure Mode

An administrator, or an automated process such as WP-CLI, attempts to activate a specific regular plugin. WordPress's own activation mechanism — a discrete, deliberately-triggered event distinct from the ordinary bootstrap sequence a normal request runs, since reaching the activation action itself requires WordPress to have already bootstrapped successfully — fails to complete that transition, and the plugin does not end up in an active state. The failure occurs at exactly one of three distinguishable points: before any of the plugin's own code has executed at all (a requirement-gate refusal), during the one-time inclusion of the plugin's own file (an activation-time fatal error), or after that file has already loaded successfully, within the plugin's own explicitly-registered activation callback.

---

# 5. Severity

This entry is classified **Critical**, with impact ranging depending on what the plugin was needed for and how the specific failure point behaves:

- Where WordPress's own requirement gate refuses activation cleanly (a `Requires PHP`, `Requires at least`, or `Requires Plugins` mismatch), the impact is typically narrow — the administrator receives a clear, WordPress-presented explanation, no plugin code ever ran, and the rest of the site is entirely unaffected.
- Where a plugin's own activation-hook callback fails partway through its own setup logic (for example, after creating some database tables but not others, or after writing some default options but not all), the site can be left in an inconsistent intermediate state that is harder to diagnose and recover from than a clean refusal.
- Where activation is attempted as part of an automated deployment pipeline (WP-CLI in a CI/CD context) rather than interactively through `wp-admin`, an activation failure can silently block an entire deployment rather than surface immediately to a human observing the Plugins screen.
- This entry remains classified at the level of its most severe possible manifestation, consistent with the range-based Critical classification used elsewhere in this catalog (`WP-ERROR-021`, `024`–`030`).

---

# 6. Distinction

This entry applies only when verified evidence establishes that a specific, deliberately-triggered attempt to activate a regular plugin did not result in that plugin becoming active, and that the failure occurred at one of the three points Section 4 describes.

**This entry is not a bootstrap-sequence condition.** Reaching the plugin activation action at all — whether through `wp-admin`'s Plugins screen or a WP-CLI `plugin activate` command — requires WordPress to have already completed its own bootstrap sequence successfully; activation is a deliberate, authenticated, post-bootstrap action WordPress's own code performs during an already-running request, not a step in the bootstrap sequence itself. `WP-ERROR-013`'s own Section 6 already excludes "fatal errors that occur only after WordPress has completed bootstrap and begun normal request processing," which this entry's own condition definitionally satisfies — no boundary correction to `WP-ERROR-013` is required to establish this.

**Three internal causes this entry keeps deliberately separate, not blended into a single generic "activation failed" condition, since each has a different diagnostic starting point and a different recovery path:**

1. **Requirement-gate refusal** — WordPress's own native pre-activation check (evaluating the plugin's declared `Requires PHP`, `Requires at least`, and `Requires Plugins` header values against the running environment) determines a requirement is unmet and refuses to attempt activation at all. No code belonging to the plugin itself ever executes. This mechanism does not evaluate PHP extension availability; WordPress core has no native plugin-header mechanism for declaring a required PHP extension.
2. **Activation-time fatal error** — the requirement gate is passed (or the plugin declares no requirements), and WordPress proceeds to include the plugin's own main file as part of processing the activation request. A PHP fatal error during that specific include is detected by WordPress's own activation-time protection, which responds by not leaving the plugin recorded as active and presenting an error to the administrator, rather than silently leaving a fatally-broken plugin marked active the way an ordinary, unprotected fatal error would.
3. **Activation-hook callback failure** — the plugin's file has already loaded successfully (cause 2 did not occur), and the specific callback the plugin registered via `register_activation_hook()` — invoked after the file's own successful inclusion — either fails unexpectedly (throws, fatals, or leaves setup logic such as table creation or default-option initialization incomplete), or deliberately and gracefully halts activation itself (for example, a plugin's own callback detecting a missing PHP extension or another unmet custom requirement and self-deactivating with its own explanatory notice, a common pattern for requirements WordPress's own native gate does not check). Both sub-cases are this entry's own condition: the plugin's file loaded correctly, and the observable failure originates specifically within its own registered activation-time logic.

It is distinct from:

- **`WP-ERROR-017` — WordPress Must-Use Plugin Fatal Error**: must-use plugins have no activation event, no deactivation toggle, and no built-in fatal-error protection of any kind — they load unconditionally on every request. This entry's entire condition depends on the presence of a discrete, toggleable activation mechanism and the protection built around it, both of which must-use plugins entirely lack. A regular plugin and a must-use plugin cannot both be the subject of this entry's own condition for the same file.
- **`WP-ERROR-013` — WordPress Bootstrap PHP Fatal Error**: owns fatal errors occurring during the bootstrap sequence of an ordinary request, before WordPress has finished initializing. This entry's own condition occurs only after bootstrap has already completed successfully, during a distinct, deliberately-triggered activation action. See the boundary statement above.
- **`WP-ERROR-014` — Required PHP Extension Missing**: where diagnosis (Section 11) confirms an activation-time fatal error (cause 2) or an activation-hook self-halt (cause 3) traces to a genuinely missing PHP extension, that underlying condition is `WP-ERROR-014`'s own territory. This entry owns the observable fact that activation failed and diagnoses which of the three mechanisms produced that failure; it hands off to `WP-ERROR-014` for the extension-availability root cause and its own recovery once confirmed, the same escalation pattern `WP-ERROR-017` Section 6 already establishes for its own must-use case.
- **`WP-ERROR-015` — Unsupported PHP Version**: where diagnosis confirms cause 1 (requirement-gate refusal) is specifically a `Requires PHP` mismatch, or cause 2/3 traces to PHP-version-specific behavior the plugin's own code does not accommodate, the underlying PHP-version condition and its own recovery (changing the running PHP version) are `WP-ERROR-015`'s own territory. This entry owns the observable fact that activation failed and identifies which requirement or mechanism was responsible; it hands off to `WP-ERROR-015` once a genuine version mismatch is confirmed as the specific cause, rather than duplicating that entry's own diagnostic or recovery content.
- **Plugin update failures**: a plugin that was already active, and remains active, through an update that itself fails or introduces a defect is a distinct lifecycle stage this taxonomy assigns to a separate entry (`WP-ERROR-032`), not this one. This entry presumes the plugin was not already active at the point the failure occurred.
- **A specific plugin's own business-logic defect surfacing during ordinary, post-activation request handling** (Plugin category, per `SF-TAXONOMY-005` Section 2): this entry owns only the activation transition itself. A plugin that activates successfully and then behaves incorrectly during normal use is outside this entry's own scope entirely.
- **Filesystem permission or existence failures preventing the plugin's own file from being read at all** (`WP-ERROR-019 — WordPress Filesystem Permission Denied`, or an undisclosed gap where the file is missing/corrupted rather than permission-denied, per `SF-TAXONOMY-005` Section 2): this entry presumes the plugin's own file can be read; a failure to read it at all, as opposed to a fatal error produced by running its content, is a distinct, filesystem-level condition.

---

# 7. Scope

**Covered:** A verified condition in which a deliberate attempt to activate a specific regular (non-must-use) plugin does not result in that plugin becoming active, where the verified cause is WordPress's own requirement-gate refusal, a PHP fatal error during the activation-time inclusion of the plugin's own file, or a failure within the plugin's own `register_activation_hook()` callback.

**Excluded:**

- Must-use plugin fatal errors, which involve no activation mechanism at all (`WP-ERROR-017`).
- PHP fatal errors occurring during the bootstrap sequence of an ordinary, non-activation request (`WP-ERROR-013`).
- A missing PHP extension, independent of the activation mechanism's own behavior in surfacing it (`WP-ERROR-014`).
- An unsupported or incompatible PHP runtime version, independent of the activation mechanism's own behavior in surfacing it (`WP-ERROR-015`).
- Plugin update failures, and any failure occurring to an already-active plugin during or after an update (`WP-ERROR-032`).
- A specific plugin's own business-logic defect during ordinary, post-activation request handling.
- Deactivation, uninstall, or any lifecycle stage other than activation.
- A plugin file that cannot be read at all due to a filesystem permission, existence, or corruption condition, as opposed to a fatal error produced by successfully reading and executing its content.

---

# 8. WordPress Components

Listed as components commonly involved, not as a claim that every installation exercises every one of them:

- The Plugins admin screen (`wp-admin/plugins.php`) and its own activation action, the most common interactive entry point.
- WP-CLI's `wp plugin activate` command, which reaches the same underlying activation mechanism through a distinct, non-interactive entry point.
- Plugin header fields (`Requires PHP`, `Requires at least`, `Requires Plugins`) that a plugin's own main file declares in its top-of-file comment block, which WordPress reads and evaluates before attempting activation.
- WordPress's own activation-time inclusion of the plugin's main file, and its own built-in detection of a fatal error occurring during that specific include.
- `register_activation_hook()`, the function a plugin calls (commonly from its own top-level code) to register a callback WordPress invokes once the plugin's file has already loaded successfully as part of the activation request.
- The "Must-Use" versus ordinary "Plugins" distinction in the admin UI, which visually and mechanically separates the two entirely different loading/activation models `WP-ERROR-017` and this entry each own.

---

# 9. Typical Symptoms

- WordPress presenting a message to the effect that the plugin could not be activated because it triggered a fatal error, with the plugin remaining listed as inactive rather than active.
- The "Activate" link for a specific plugin appearing disabled or grayed out on the Plugins screen, commonly accompanied by a notice naming an unmet PHP version, WordPress version, or required-plugin dependency.
- A WP-CLI `wp plugin activate` command exiting with a non-zero status and an error message, rather than the plugin appearing in `wp plugin list` as active afterward.
- A plugin appearing to activate (no fatal-error notice shown) but leaving evidence of incomplete setup — a missing database table, a missing default option, or an absent scheduled cron event the plugin's own activation hook was expected to create.
- A custom notice, generated by the plugin's own activation-hook logic rather than by WordPress core itself, explaining that a specific requirement (commonly a PHP extension) is not met, followed by the plugin remaining or becoming inactive again.
- Identical activation attempts succeeding on one environment (staging) and failing on another (production), pointing toward an environment-specific requirement gap rather than a defect in the plugin's own code universally.

---

# 10. Common Causes

Causes are grouped by category. Inclusion in this list identifies a category as plausible; it does not assert that any specific one is present without diagnostic confirmation.

- The plugin declares a `Requires PHP` version higher than the environment's running PHP version.
- The plugin declares a `Requires at least` WordPress version higher than the site's installed WordPress version.
- The plugin declares a `Requires Plugins` dependency on another plugin that is not installed, not active, or not present at all.
- The plugin's own top-level code (executed unconditionally the moment its file is included, before any hook fires) references a PHP extension, function, or class the running environment does not actually provide.
- The plugin's own top-level code contains a genuine defect (a syntax error introduced by an incomplete deployment, a type error, an unhandled exception) unrelated to any environment-requirement gap.
- The plugin's own `register_activation_hook()` callback attempts a database operation (creating a custom table, seeding default data) that fails partway, for a reason belonging to another category once diagnosed (for example, a database-layer condition).
- The plugin's own `register_activation_hook()` callback deliberately checks for an unmet requirement WordPress's own native gate does not check (most commonly a specific PHP extension) and halts activation itself with a custom notice.
- A partially or incorrectly deployed plugin file (an incomplete upload, a failed extraction, a version-control merge artifact) leaves the plugin's own code syntactically or logically broken at the moment activation is attempted.
- A conflict between the plugin being activated and an already-active plugin (for example, both declaring the same function or class name) surfaces specifically at the moment of activation, when the second plugin's file is first included.

---

# 11. Diagnosis

Verify the following, starting from the least invasive observation and narrowing only once the general shape of the failure is established:

1. **Confirm activation actually failed.** Check the plugin's current state directly (the Plugins screen, or `wp plugin list` via WP-CLI) rather than inferring failure from a symptom alone; a plugin that is active but behaving incorrectly is not this entry's condition (see Section 6).
2. **Capture the exact message WordPress itself presented** at the moment of the failed attempt — the specific wording distinguishes a fatal-error notice, a requirement-mismatch notice (and which requirement it names), and a WP-CLI error message from one another, and each points toward a different one of the three causes in Section 6.
3. **Determine which of the three points in Section 6 the failure occurred at**, before investigating any specific underlying mechanism: whether WordPress refused to attempt activation at all (no plugin code ran; check for a requirement-mismatch notice), whether the plugin's own file failed to load (an activation-time fatal-error notice), or whether the file loaded but the plugin's own activation-hook logic is where the failure occurred (evidence of partial setup, or a custom notice the plugin itself generated rather than WordPress core).
4. **Only once the general point of failure is established, investigate the specific mechanism responsible:**
   - Where a requirement-gate refusal (cause 1) is confirmed, identify which specific requirement — PHP version, WordPress version, or a named plugin dependency — is unmet, and evaluate against `WP-ERROR-015` (PHP version) or, where the requirement is a missing/inactive dependency plugin, resolve that dependency directly rather than treating it as a defect in the plugin being activated.
   - Where an activation-time fatal error (cause 2) is confirmed, capture the exact PHP error class, message, file, and line number from the relevant log, and evaluate whether the referenced symbol belongs to a PHP extension (`WP-ERROR-014`) or reflects a PHP-version-specific behavior change (`WP-ERROR-015`) before concluding the defect is in the plugin's own code independent of the environment.
   - Where an activation-hook callback failure (cause 3) is confirmed, determine whether the callback failed unexpectedly (evidence of partial setup) or deliberately halted itself with its own custom notice (in which case the notice itself commonly identifies the unmet requirement directly).
5. Preserve the exact notice or error text, the plugin's version, and the environment's current PHP/WordPress version and active-plugin list before making any change.
6. Where the same plugin activates successfully in one environment but not another, explicitly compare the two environments' PHP versions, WordPress versions, and active-plugin sets rather than assuming the plugin's own code is universally at fault.
7. Where partial setup from a failed activation-hook callback is suspected, inventory what the plugin's own activation logic was expected to create (tables, options, scheduled events) and verify each independently, since a partially-completed activation can leave inconsistent state that a mere retry does not resolve on its own.
8. Where a function/class name conflict with an already-active plugin is suspected, identify both plugins involved and the specific colliding symbol before assuming either plugin's own code is independently defective.

---

# 12. Recovery Procedure

Recovery shall target the specific, verified cause identified in Diagnosis (Section 11), not merely retry activation without addressing it.

Permitted recovery categories, depending on the verified cause, include:

- Where a requirement-gate refusal is confirmed, satisfying the actual unmet requirement — updating the running PHP version (per `WP-ERROR-015`'s own recovery procedure), updating WordPress core, or installing and activating a required dependency plugin — rather than attempting to bypass WordPress's own requirement check.
- Where an activation-time fatal error traces to a missing PHP extension, resolving that per `WP-ERROR-014`'s own recovery procedure.
- Where an activation-time fatal error traces to a genuine defect in the plugin's own code (a bad deployment, a version-control artifact), correcting or re-deploying the plugin's own file from a known-good source.
- Where an activation-hook callback failed partway through its own setup, either allowing the plugin's own retry-safe activation logic to complete cleanly on a subsequent attempt (where the plugin's own code is idempotent), or manually completing or reverting the partial setup before retrying, depending on what the specific plugin's own documented behavior supports.
- Where a function/class name conflict with another active plugin is confirmed, resolving the conflict by updating one of the two plugins to a version that avoids the collision, or deactivating the conflicting plugin, rather than attempting to force both to coexist unmodified.
- Where the plugin's own activation-hook logic is confirmed to be genuinely defective independent of the environment, escalating to the plugin's own maintainer or update channel, consistent with this catalog's treatment of a specific plugin's own business-logic defect as outside this entry's own corrective scope.

Recovery shall not suppress the failure by disabling error display or logging, and shall not force activation past a requirement-gate refusal by editing WordPress core or bypassing the check, since doing so removes a protection WordPress itself is deliberately providing.

---

# 13. Validation

Recovery is successful when:

- The plugin activates without producing the original fatal error, requirement-mismatch notice, or activation-hook failure.
- The plugin's own activation-hook setup (tables, default options, scheduled events, as applicable to that specific plugin) is confirmed complete, not merely that the plugin now shows as "active."
- Where the cause was a requirement-gate refusal, the corrected requirement (PHP version, WordPress version, or dependency plugin) is confirmed independently, not merely inferred from activation now succeeding.
- No previously-active plugin was disturbed or deactivated as a side effect of resolving this plugin's own activation failure.
- Where a function/class name conflict was resolved, both the plugin being activated and the previously-active plugin it conflicted with continue to function correctly together.
- No equivalent activation failure recurs on a fresh attempt.

---

# 14. Prevention

- Declare accurate `Requires PHP`, `Requires at least`, and `Requires Plugins` header values for any plugin under direct site-owner control, so WordPress's own requirement gate can refuse activation cleanly rather than allowing a fatal error to occur.
- Test plugin activation in a staging environment that matches production's PHP version, WordPress version, and active-plugin set before activating in production.
- Maintain an explicit PHP-version and dependency-requirement matrix for the site's own plugin set, consistent with the prevention practice `WP-ERROR-015` already recommends generally.
- Where a plugin's own activation-hook logic performs setup that is not naturally idempotent (for example, table creation that fails if run twice), verify it handles a retry-after-partial-failure scenario safely before relying on it in production.
- Avoid activating a newly-installed or newly-updated plugin directly in production without first confirming, in staging, that it does not collide with an already-active plugin's own declared function or class names.

---

# 15. Security Considerations

- A custom notice a plugin's own activation-hook callback generates (for example, describing a missing requirement) may reveal installed plugin names, versions, or partial file paths; this is ordinarily benign but should not be treated as a substitute for restricting `wp-admin` access to authorized administrators.
- Do not bypass WordPress's own requirement gate or activation-time fatal-error protection as a troubleshooting shortcut; both exist specifically to prevent a broken or incompatible plugin from being left in an active state.
- Where activation is attempted via WP-CLI in an automated deployment pipeline, ensure activation failures cause the pipeline itself to fail visibly rather than being silently ignored, since a plugin that fails to activate may leave a site running without an expected security-relevant feature (for example, a firewall or authentication plugin).
- Verify the source and integrity of a plugin's own files before activation where the plugin was obtained from outside the official WordPress.org repository, since this entry's own diagnostic procedure does not itself establish that a plugin's code is trustworthy, only that its activation mechanically succeeded or failed.

---

# 16. Related Errors

The following are cited as they exist in this repository.

1. [WP-ERROR-017 — WordPress Must-Use Plugin Fatal Error](WP-ERROR-017-MUST-USE-PLUGIN-FATAL-ERROR.md) — exists in this repository; see Section 6 (Distinction) above for how the two entries' ownership differs by the presence or absence of an activation mechanism entirely.
2. [WP-ERROR-013 — WordPress Bootstrap PHP Fatal Error](WP-ERROR-013-WORDPRESS-BOOTSTRAP-PHP-FATAL-ERROR.md) — exists in this repository; see Section 6 (Distinction) above for why this entry's own condition, occurring only after bootstrap has already completed, falls outside that entry's own scope without requiring any correction to it.
3. [WP-ERROR-014 — Required PHP Extension Missing](WP-ERROR-014-REQUIRED-PHP-EXTENSION-MISSING.md) — exists in this repository; see Section 6 (Distinction) above for the diagnose-then-hand-off relationship.
4. [WP-ERROR-015 — Unsupported PHP Version](WP-ERROR-015-UNSUPPORTED-PHP-VERSION.md) — exists in this repository; see Section 6 (Distinction) above for the diagnose-then-hand-off relationship. That entry's own Section 9 (Typical Symptoms) already names "a plugin or theme activation blocked by a `Requires PHP` mismatch notice" as one of its own possible symptoms; this entry is now the more specific diagnostic entry point for that particular symptom.
5. [WP-ERROR-019 — WordPress Filesystem Permission Denied](WP-ERROR-019-FILESYSTEM-PERMISSION-DENIED.md) — exists in this repository; see Section 6 (Distinction) above for the boundary between a plugin file that cannot be read at all and one that is read but fails during execution.
6. WP-ERROR-032 — WordPress Plugin Update Failure (conceptual reference; planned per `SF-TAXONOMY-005` Section 3, no corresponding document currently exists in this repository; no link is provided) — see Section 6 (Distinction) above.

---

# 17. Notes

This entry documents the general, verified observable condition of a regular plugin failing to reach an active state when activation is deliberately attempted, distinguishing the three mechanically distinct points — requirement gate, activation-time file-include, and activation-hook callback — at which that failure can occur. It is the second entry in the Plugin category, drafted directly from `SF-TAXONOMY-005`'s own declared scope for `WP-ERROR-031` rather than from a fresh boundary discussion, following the project owner's own explicit direction to test whether that taxonomy is complete enough to support entry authoring without requiring a taxonomy revision.

Consistent with the single-responsibility principle in **SF-SPEC-001** Section 4.3, this entry does not claim ownership of the underlying PHP-extension or PHP-version conditions its own causes may trace to (`WP-ERROR-014`/`015`), nor of a specific plugin's own business-logic defect once activation itself has succeeded.

This entry reached `Production Ready` via `SF-REVIEW-090` (Class A author review; no findings) and `SF-REVIEW-091` (Class B independent review; one Minor finding — IF-1, a cross-document completeness gap in `WP-ERROR-017`'s own text, corrected within that same review) per **SF-SPEC-001** Section 19, **SF-SPEC-005** Section 5.6, and **SF-SPEC-012**.

No Reference Implementation is currently designated by **SF-SPEC-001**; this entry's relationship to that designation, and to any future `WP-SCENARIO-XXX` runtime evidence, is not asserted here and shall not be assumed until such evidence or designation actually exists.
