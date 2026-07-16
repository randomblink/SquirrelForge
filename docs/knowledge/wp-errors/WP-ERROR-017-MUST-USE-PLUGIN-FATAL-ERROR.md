# WP-ERROR-017 — Must-Use Plugin Fatal Error

---

# 1. Knowledge Entry

Must-Use Plugin Fatal Error

---

# 2. Metadata

* **Error ID:** `WP-ERROR-017`
* **Title:** Must-Use Plugin Fatal Error
* **Category:** Plugin
* **Severity:** Critical
* **Recovery Priority:** Immediate
* **Status:** Production Ready
* **Version:** 1.0

---

# 3. Summary

A PHP fatal error occurs within code loaded from `wp-content/mu-plugins/` (or a file that code directly requires), and because must-use plugins load automatically, unconditionally, and very early in WordPress bootstrap, the failure affects every request path with no built-in, per-plugin toggle available to isolate it.

---

# 4. Primary Failure Mode

A PHP fatal error terminates execution within code belonging to a must-use plugin — a top-level PHP file in `wp-content/mu-plugins/` that WordPress loads automatically during bootstrap, or a file that such a top-level file directly requires. Because must-use plugins have no activation or deactivation state and are loaded unconditionally on every request, the failure is not confined to a single feature or request type the way an ordinary plugin's fatal error can sometimes be; it affects front-end, administrative, AJAX, cron, REST, and WP-CLI paths alike, and cannot be resolved through WordPress's normal plugin-deactivation mechanisms.

---

# 5. Severity

This entry is classified **Critical** because, by definition, it covers a failure mode with no built-in mitigation:

- Must-use plugins load unconditionally on every request; there is no request path that bypasses them.
- WordPress provides no activation/deactivation toggle for must-use plugins, so the standard recovery technique of deactivating a misbehaving plugin through `wp-admin`, or by removing it from the active-plugins list, does not apply.
- Remediation cannot be deferred, since every request — including administrative requests that might otherwise be used to diagnose or fix the problem — is affected identically.

---

# 6. Distinction

This entry applies only when verified evidence establishes that a PHP fatal error originates from code loaded via the must-use plugin mechanism specifically — a top-level `.php` file in `wp-content/mu-plugins/`, or a file such a file directly requires.

It is distinct from:

- **WP-ERROR-013 — WordPress Bootstrap PHP Fatal Error**: WP-ERROR-013 owns the general condition of a PHP fatal error terminating WordPress bootstrap, regardless of cause. This entry owns the narrower, verified cause-specific condition in which the fatal error is attributable specifically to must-use plugin code. A must-use plugin fatal error may produce the fatal error WP-ERROR-013 describes, but the two entries do not own the same responsibility.
- **WP-ERROR-016 — WordPress Core Files Missing or Corrupted**: must-use plugin files reside in `wp-content/`, are supplied by the site owner or hosting provider, and are not part of the official WordPress core distribution. A corrupted core installation and a defective must-use plugin are conceptually independent conditions; evidence shall establish which is actually present.
- **WP-ERROR-014 — Required PHP Extension Missing**: conceptually independent. Where a must-use plugin's fatal error references a symbol that a PHP extension provides, evidence shall first establish whether that extension is genuinely unavailable to the runtime (WP-ERROR-014's territory) before concluding this entry's own code-defect boundary applies.
- **WP-ERROR-015 — Unsupported PHP Version**: conceptually independent. A must-use plugin can fail on a fully supported PHP version due to a defect in its own code, and a version-incompatible must-use plugin is a distinct condition from this entry's general boundary; evidence shall establish which applies.
- **Ordinary plugin fatal errors involving activation or deactivation** (see [WP-ERROR-031 — WordPress Plugin Activation Failure](WP-ERROR-031-PLUGIN-ACTIVATION-FAILURE.md)): regular plugins in `wp-content/plugins/` have an activation lifecycle — WordPress records which plugins are active, runs activation and deactivation hooks, and, in current WordPress versions, guards plugin activation against a fatal error terminating the request. Must-use plugins have none of this: no activation event, no deactivation hook, and no built-in fatal-error guard around their unconditional loading. A fatal error in an ordinary, inactive-by-default plugin is a distinct condition with a distinct, already-available mitigation path (deactivation) that does not exist for must-use plugins.
- **Network-activated plugins (multisite)**: a network-activated plugin is a regular plugin in `wp-content/plugins/`, activated network-wide through `wp-admin`, with a normal (network-level) activation lifecycle. It is not a must-use plugin and does not load via the `wp-content/mu-plugins/` mechanism this entry documents, despite sometimes being informally conflated with "must-use" plugins.
- **Drop-ins**: files such as `advanced-cache.php`, `db.php`, `object-cache.php`, and `sunrise.php` load via a distinct mechanism — WordPress checks for their exact filename directly in `wp-content/`, rather than automatically loading every file in a directory. Drop-ins are not must-use plugins and are excluded from this entry.
- **Theme runtime failures**: themes load later in the bootstrap sequence, during theme setup, and are unrelated to the must-use plugin loading mechanism.

---

# 7. Scope

**Covered:** A verified condition in which a PHP fatal error originates from a top-level `.php` file in `wp-content/mu-plugins/`, or a file such a file directly requires, and that failure is attributable to a defect in that code rather than to a missing PHP extension, an unsupported PHP version, or corrupted WordPress core files.

**Excluded:**

- Fatal errors in ordinary, activatable plugins under `wp-content/plugins/`.
- Fatal errors in network-activated plugins (multisite), which use the ordinary plugin activation mechanism.
- Fatal errors in drop-in files, which load via exact-filename matching in `wp-content/`, not the must-use plugin directory glob.
- Fatal errors in theme code.
- Missing PHP extensions, independent of the must-use plugin mechanism (see WP-ERROR-014).
- An unsupported or incompatible PHP runtime version, independent of the must-use plugin mechanism (see WP-ERROR-015).
- Corrupted WordPress core files (see WP-ERROR-016).
- A `.php` file present in a subdirectory of `wp-content/mu-plugins/` that WordPress does not automatically load (see Section 8).

---

# 8. WordPress Components

Listed as components commonly involved, not as a claim that every installation uses every one of them:

- The `wp-content/mu-plugins/` directory (or the location configured by the `WPMU_PLUGIN_DIR` constant, where changed from the default).
- WordPress's must-use plugin loading logic in `wp-settings.php`, which automatically requires every top-level `.php` file directly inside the must-use plugin directory, in alphabetical filename order, before loading regular active plugins. This is a flat-directory loading behavior: WordPress does not recurse into subdirectories of the must-use plugin directory, so only files placed directly inside it are loaded automatically.
- The "Must-Use" tab on the Plugins admin screen, which lists installed must-use plugins read-only, without activate, deactivate, or delete actions.
- Any code a top-level must-use plugin file explicitly requires from a subdirectory, since WordPress itself does not automatically load files in subdirectories of the must-use plugin directory.

---

# 9. Typical Symptoms

- A PHP fatal error that occurs identically across front-end, administrative, AJAX, cron, REST, and WP-CLI requests, since must-use plugins load unconditionally on every one of them.
- A White Screen of Death with no working `wp-admin` fallback, since the standard technique of deactivating all plugins through `wp-admin`, or renaming the `wp-content/plugins/` directory, does not affect must-use plugins.
- The "Must-Use" tab on the Plugins screen displaying the plugin, with no way to deactivate it from that screen.
- A site that previously worked normally beginning to fail immediately after a must-use plugin file was added, edited, or updated — including by a hosting provider that manages its own platform-required must-use plugins.
- A fatal error referencing a function, class, or constant expected to be defined by another must-use plugin file, when that file has not yet loaded due to alphabetical load order, or was moved, renamed, or removed.

---

# 10. Common Causes

Causes are grouped by category. Inclusion in this list identifies a category as plausible; it does not assert that any specific cause is present without diagnostic confirmation.

- A defect introduced directly into a must-use plugin file by a site administrator or developer, with no activation-time safety net to catch it before it affects live traffic.
- A load-order dependency between two or more must-use plugin files, where one file expects a function, class, or constant defined by another that has not yet loaded, since WordPress loads them in alphabetical filename order rather than a declared dependency order.
- A hosting provider or managed-WordPress platform updating its own platform-required must-use plugin in a way that introduces a defect or a new dependency the environment does not satisfy.
- A must-use plugin file that was only partially deployed or edited, leaving it syntactically or logically incomplete.
- A must-use plugin relying on a PHP extension or PHP version the runtime does not actually provide, which is a defect in the plugin's own assumptions even though the proximate technical cause belongs to WP-ERROR-014 or WP-ERROR-015 once confirmed.
- Code mistakenly placed in a subdirectory of `wp-content/mu-plugins/` under the assumption that WordPress loads it automatically, when in fact only top-level files in that directory are loaded automatically.
- In Composer-managed WordPress deployments (for example, Bedrock-style project structures), a single top-level must-use plugin file that bootstraps a Composer autoloader failing because the expected `vendor/autoload.php` is missing, was not deployed, or points to a path that does not match the current deployment.

---

# 11. Diagnosis

Verify the following:

1. Confirm the fatal error originates from a file in `wp-content/mu-plugins/` (or a file such a file directly requires), rather than from a regular plugin, a network-activated plugin, a drop-in, a theme, a missing PHP extension, an unsupported PHP version, or corrupted core files.
2. Capture the exact fatal error, including the referenced file, class, function, and line number where available.
3. List every top-level `.php` file actually present directly in `wp-content/mu-plugins/`, since only those files are automatically loaded; a relevant file located in a subdirectory is not loaded unless another loaded file explicitly requires it.
4. Determine the alphabetical load order of the top-level must-use plugin files, since WordPress loads them in that order; where the fatal error suggests a missing dependency, confirm whether the defining file is expected to load earlier or later than the file that failed.
5. Preserve the current state of the must-use plugin directory and the affected file's content before making any change.
6. Determine whether the failing symbol belongs to a PHP extension (in which case evaluate against WP-ERROR-014) or is affected by a PHP version difference (in which case evaluate against WP-ERROR-015) before concluding the defect is in the must-use plugin's own code.
7. Where isolation is warranted by the evidence gathered so far, isolate the specific suspected file by moving it out of `wp-content/mu-plugins/` entirely, or into a subdirectory of it, since WordPress's automatic loading does not recurse into subdirectories; this disables only that file rather than every must-use plugin.
8. Where isolating a single file does not resolve the failure, or where multiple must-use plugin files are suspected, confirm the diagnosis by temporarily renaming the entire `wp-content/mu-plugins/` directory, which disables all must-use plugins at once; treat this as a broader diagnostic step, not a first-line action, since it disables every must-use plugin the site relies on.
9. Separate the primary must-use plugin fatal error from secondary errors it may produce downstream.
10. Where a must-use plugin is supplied and managed by the hosting provider or platform rather than by the site owner, identify it as such before modifying or removing it, since the platform may restore, expect, or depend on it.

---

# 12. Recovery Procedure

Recovery shall target the verified defective must-use plugin file, not merely the visible symptom.

Permitted recovery categories, depending on the verified cause, include:

- Correcting the defect directly in the affected must-use plugin file, since must-use plugins have no separate "update via `wp-admin`" mechanism and are maintained by directly editing or replacing the file.
- Restoring a previous, known-good version of the specific file from backup or version control, in preference to attempting to patch a defect in place without a reference to compare against.
- Where the must-use plugin is supplied by a hosting provider or platform, coordinating the fix through that provider rather than editing their file directly, since a platform-managed file may be overwritten or expected to remain in a specific state.
- Where the cause is a load-order dependency between must-use plugin files, correcting the dependency explicitly (for example, by consolidating dependent logic into a single file, or by having one file `require` the other directly) rather than relying on alphabetical filename ordering to remain correct.
- Where diagnosis confirmed the actual cause is a missing PHP extension or an unsupported PHP version rather than a defect in the must-use plugin's own code, addressing that condition instead, per WP-ERROR-014 or WP-ERROR-015 as applicable.

Recovery shall not leave the affected file isolated (moved to a subdirectory, or the entire must-use plugin directory renamed) as a permanent fix; isolation is a diagnostic and stabilization technique, not a substitute for correcting the underlying defect. Recovery shall not suppress the error by disabling error display or logging without correcting the underlying defect.

---

# 13. Validation

Recovery is successful when:

- The corrected must-use plugin file no longer produces the original fatal error.
- Front-end, administrative, AJAX, cron, REST, and WP-CLI paths are each independently confirmed to succeed, since must-use plugin failures are not confined to a single request type by nature.
- Any must-use plugin file temporarily isolated during diagnosis has been restored to its normal, loaded location, or intentionally and permanently removed with that decision documented, rather than left in an ambiguous isolated state.
- No equivalent fatal error appears in relevant logs across repeated, fresh requests.
- Where a load-order dependency was corrected, the fix does not merely happen to work under the current alphabetical ordering but is structurally independent of file naming.
- No unrelated must-use plugin, regular plugin, theme, or configuration was altered or lost in the course of recovery.

---

# 14. Prevention

- Keep must-use plugin code minimal and thoroughly tested before deployment, since no activation-time safety net exists to catch a fatal error before it affects live traffic.
- Avoid relying on alphabetical filename ordering for load-order dependencies between multiple must-use plugin files; use a single loader file with explicit `require` statements where ordering matters.
- Maintain must-use plugin files under version control, and treat changes to them with the same care as changes to WordPress core, given the absence of any deactivation safety net.
- Document which must-use plugins are platform-managed (supplied by a hosting provider) versus site-managed, to avoid inadvertently modifying or removing a platform-required file.
- Test must-use plugin changes in a staging environment before applying them to production, since a defect surfaces identically and immediately across every request path in production.

---

# 15. Security Considerations

- Must-use plugins are a known persistence location for malware, since they load unconditionally, are not easily deactivated through `wp-admin`, and are less commonly reviewed by site administrators than the regular Plugins list. Unexplained or unrecognized files appearing in `wp-content/mu-plugins/` shall be treated as a potential security incident, not merely a file-integrity inconvenience.
- Do not restore or "clean" a suspicious must-use plugin file without also investigating how it was introduced; removing the file alone, while leaving a compromised credential, plugin, or theme in place, invites immediate re-compromise.
- Preserve a copy of a suspicious file before removing it, since it may be needed as evidence for further investigation.
- Do not expose diagnostic output publicly during investigation.
- Coordinate credential rotation and further security review through a platform-appropriate process where compromise is confirmed or suspected.

---

# 16. Related Errors

The following are cited as they exist in this repository, or as conceptual distinctions where noted.

1. [WP-ERROR-013 — WordPress Bootstrap PHP Fatal Error](WP-ERROR-013-WORDPRESS-BOOTSTRAP-PHP-FATAL-ERROR.md) — see Section 6 (Distinction) above.
2. [WP-ERROR-014 — Required PHP Extension Missing](WP-ERROR-014-REQUIRED-PHP-EXTENSION-MISSING.md) — see Section 6 (Distinction) above.
3. [WP-ERROR-015 — Unsupported PHP Version](WP-ERROR-015-UNSUPPORTED-PHP-VERSION.md) — see Section 6 (Distinction) above.
4. [WP-ERROR-016 — WordPress Core Files Missing or Corrupted](WP-ERROR-016-WORDPRESS-CORE-FILES-MISSING-OR-CORRUPTED.md) — see Section 6 (Distinction) above.

---

# 17. Notes

This entry documents the general, verified observable condition of a fatal error originating from must-use plugin code, owing its Critical severity and distinct recovery approach to the absence of any activation, deactivation, or built-in fatal-error protection for that specific loading mechanism. It does not claim must-use plugins are inherently unsafe or that every installation uses them; many WordPress installations have no must-use plugins at all. Consistent with the single-responsibility principle in **SF-SPEC-001** Section 4.3, cause-specific conditions (for example, a specific known malicious must-use plugin pattern, or a defect tied to a specific hosting platform's own must-use plugin) may each be documented by a separate, independently created `WP-ERROR` entry without altering this one.

This entry underwent the review sequence required by **SF-SPEC-001** Section 19, **SF-SPEC-005** Section 5.6, and **SF-SPEC-012**: an author (Class A) review at `docs/reviews/SF-REVIEW-012-WP-ERROR-017-AUTHOR-REVIEW.md`, followed by an independent (Class B) review at `docs/reviews/SF-REVIEW-013-WP-ERROR-017-INDEPENDENT-REVIEW.md`, which reached outcome **Approved with Minor Revisions**, applied and re-validated the one required revision, and satisfied the Production Ready gate per SF-SPEC-012 Section 12. Its Status was changed to Production Ready on that basis. This document does not itself constitute either review record; see the cited files for full findings, corrections, and gate decisions.

The independent review did not designate this entry as a Reference Implementation. That designation, governed separately by **SF-SPEC-001** Section 22, has not been sought or asserted here.

No Reference Implementation is currently designated by **SF-SPEC-001**; this entry's relationship to that designation, and to any future `WP-SCENARIO-XXX` runtime evidence, is not asserted here and shall not be assumed until such evidence or designation actually exists.
