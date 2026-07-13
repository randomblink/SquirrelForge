# WP-ERROR-013 — WordPress Bootstrap PHP Fatal Error

---

# 1. Knowledge Entry

WordPress Bootstrap PHP Fatal Error

---

# 2. Metadata

* **Error ID:** `WP-ERROR-013`
* **Title:** WordPress Bootstrap PHP Fatal Error
* **Category:** Bootstrap
* **Severity:** Critical
* **Recovery Priority:** Immediate
* **Status:** Production Ready
* **Version:** 1.0

---

# 3. Summary

WordPress begins the bootstrap sequence and successfully loads `wp-config.php`, but PHP terminates execution with a fatal error before WordPress completes initialization. Because bootstrap never completes, no request path served by WordPress — front-end, administrative, AJAX, cron, REST, or WP-CLI — can be processed until the failure is corrected.

---

# 4. Primary Failure Mode

A PHP fatal error (for example an uncaught `Error`, an uncaught `Exception`, or a fatal condition such as memory exhaustion or a duplicate declaration) terminates PHP execution after `wp-config.php` has been successfully loaded and parsed, but before WordPress has finished initialization and normal request processing has become available. The failure originates in code executed by the bootstrap sequence itself — WordPress core, a must-use plugin, a drop-in, an active plugin loaded during `plugins_loaded`, or the active theme's `functions.php` — rather than in code that runs only after bootstrap has completed.

---

# 5. Severity

This entry is classified **Critical** because, while the failure persists:

- No request path served by WordPress can complete, including front-end, administrative, AJAX, cron, REST, and WP-CLI paths.
- No WordPress-level workaround exists prior to the point of failure, since the failure occurs before WordPress has finished initializing the systems (such as the fatal-error-protection mechanism) that could otherwise mitigate it. Even where that mechanism has been reached, its administrator-notification path depends on functioning outbound email delivery; it is not a substitute for direct diagnosis.
- The condition affects the entire site rather than a single feature, plugin, or page.
- Remediation cannot be deferred, since the site provides no functioning request path in this state.

---

# 6. Distinction

This entry applies only when PHP terminates execution with a fatal error during the WordPress bootstrap sequence, after `wp-config.php` has already been loaded successfully.

It is distinct from:

- **WP-ERROR-010 — WordPress Configuration File Missing** (conceptual reference; no corresponding document currently exists in this repository): covers the case where `wp-config.php` cannot be located at all, which prevents bootstrap from ever reaching the point this entry addresses.
- **WP-ERROR-011 — WordPress Configuration File Invalid** (conceptual reference; no corresponding document currently exists in this repository): covers cases where `wp-config.php` is located and executes, but its contents are invalid (for example missing required constants or invalid values), a distinct failure at the configuration-evaluation stage rather than a fatal error occurring after that stage has succeeded.
- **WP-ERROR-012 — WordPress Configuration File PHP Syntax Error** (conceptual reference; no corresponding document currently exists in this repository): covers a PHP parse error within `wp-config.php` itself — a compile-time failure that prevents `wp-config.php` from executing at all, distinct from a runtime fatal error occurring after `wp-config.php` has already executed successfully.
- Database connection, authentication, permission, schema, corruption, capacity, and timeout failures. These are runtime conditions distinct in kind from a PHP-language-level fatal error and are outside the scope of this entry.
- PHP warnings, notices, and deprecation messages, none of which terminate execution and none of which fall within this entry's scope.
- HTTP-layer errors reported by a web server without corresponding PHP fatal-error evidence in any available log.
- A blank or empty response with no corroborating PHP fatal-error evidence.
- Fatal errors that occur only after WordPress has completed bootstrap and begun normal request processing — for example, within a plugin's request-handling callback, a REST route handler, or theme template rendering after the `template_redirect` stage.
- Ordinary plugin or theme runtime failures occurring during normal, post-bootstrap request handling.

---

# 7. Scope

**Covered:** A PHP fatal error that terminates execution at any point in the WordPress bootstrap sequence after `wp-config.php` has been successfully loaded, and before WordPress has completed initialization and made normal request processing available — across front-end, administrative, AJAX, cron, REST, and WP-CLI bootstrap paths.

**Excluded:**

- Failure to locate, parse, or validly configure `wp-config.php` itself.
- Any failure occurring only after WordPress bootstrap has completed.
- Database-layer failures of any kind.
- Non-fatal PHP conditions (warnings, notices, deprecations).
- Web-server failures that prevent PHP or WordPress from starting at all (for example, the web server itself failing to invoke PHP).
- Failures whose only evidence is an HTTP status code or a blank response, absent corroborating PHP fatal-error evidence.

---

# 8. WordPress Components

Listed in the order they are reached during bootstrap:

- `index.php` (front-end entry point)
- `wp-blog-header.php` (front-end only)
- `wp-load.php` (loaded by every WordPress bootstrap path: directly by `wp-admin`, `wp-cron.php`, and WP-CLI, and via `wp-blog-header.php` for front-end requests and for REST requests served through WordPress's default routing)
- `wp-config.php` (a precondition for this entry; not itself the failing component)
- `wp-settings.php` (the primary bootstrap file: loads WordPress core class files, sets default constants, and progressively initializes core systems)
- Must-use plugins (loaded early in `wp-settings.php`, before regular plugins)
- Drop-ins (for example `advanced-cache.php`, `db.php`, `object-cache.php`, `sunrise.php`), where present
- Active plugins (loaded during bootstrap; the `plugins_loaded` hook fires once all active plugins have been included)
- The active theme's `functions.php` (loaded as part of theme setup, later in the bootstrap sequence)
- `wp-admin/admin-ajax.php` (the direct entry point for AJAX requests; it loads `wp-load.php` directly, in the same way as `wp-admin` and `wp-cron.php`, and is a distinct request path from an ordinary administrative page load)
- The WP-CLI bootstrap path, which loads `wp-load.php` in the same manner as a web request, sharing the same core, must-use plugin, drop-in, plugin, and theme bootstrap code

---

# 9. Typical Symptoms

- A blank white page with no visible output (commonly described as a "White Screen of Death"), when error display is disabled and no fatal-error page is produced.
- WordPress's own critical-error notice, when the fatal-error-protection mechanism has been reached and is available at the point of failure.
- Raw PHP fatal-error text rendered directly in the response, when `display_errors` is enabled in the PHP configuration.
- An HTTP 500 response, in environments where the web server or PHP handler maps an unhandled PHP fatal error to that status. This mapping is environment-dependent and does not occur universally; some environments return a different status, a custom error page, or no distinguishing status at all.
- WP-CLI commands terminating immediately with a PHP fatal-error message instead of executing the requested command.
- The administrative dashboard becoming inaccessible.
- AJAX requests (`admin-ajax.php`) failing, which may occur independently of whether ordinary administrative page loads are affected, since AJAX has its own direct bootstrap entry point.
- REST API requests failing before reaching REST route initialization.
- Cron requests (`wp-cron.php` or an equivalent trigger) failing without a visible error, since cron requests are not typically viewed in a browser.

---

# 10. Common Causes

Causes are grouped by category. Inclusion in this list identifies a category as plausible; it does not assert that any specific cause is present without diagnostic confirmation.

- Plugin code that executes during bootstrap (for example, top-level code that runs before any WordPress hook fires) and raises an uncaught error or exception.
- Must-use plugin code raising a fatal error, since must-use plugins execute unconditionally during bootstrap.
- Drop-in files (`advanced-cache.php`, `db.php`, `object-cache.php`, `sunrise.php`) executing during early bootstrap and raising a fatal error.
- The active theme's `functions.php` raising a fatal error during theme setup.
- WordPress core files that are missing, incomplete, or corrupted.
- PHP-version incompatibility, such as code that calls a function or uses syntax unsupported by the currently running PHP version.
- A required PHP extension that is missing or disabled.
- A missing PHP function or class, commonly resulting from an unmet extension or dependency requirement. This condition is sometimes searched for as a "class not found" or "function not found" error; the corresponding literal PHP messages are `Class "X" not found` for an undefined class and `Call to undefined function X()` for an undefined function.
- Memory exhaustion during bootstrap (`Allowed memory size ... exhausted`, an abbreviated form of the full PHP message, which includes the configured limit and the size of the failed allocation).
- A duplicate function or class declaration, commonly caused by a duplicated plugin installation or a duplicated file.
- An invalid callable registered and invoked too early in the bootstrap sequence.
- A type error, particularly under PHP 8's stricter type-checking behavior.
- An uncaught exception thrown by bootstrap-loaded code.
- An autoloader or Composer dependency failure within a plugin or theme.
- Environment-specific bootstrap code whose assumptions about the runtime environment are unmet.

---

# 11. Diagnosis

Verify the following:

1. Confirm the failure is a genuine PHP fatal error rather than a warning, notice, deprecation, database error, or HTTP-layer condition without PHP fatal-error evidence.
2. Preserve the current PHP error log and, where present, the WordPress `debug.log`, before making any change to the environment.
3. Locate the fatal-error entry in the PHP error log corresponding to the time of the request. The PHP error log is a server-level log distinct from WordPress's own `debug.log`; a PHP fatal error is always recorded to the PHP error log if PHP error logging is enabled, whether or not WordPress ever reaches the point of configuring its own `debug.log`.

   ```text
   # Example only — the actual log path is environment-specific.
   tail -n 200 /path/to/php-error.log
   ```

4. Where the PHP error log itself is unavailable, misconfigured, or unwritable, confirm PHP's own error-logging configuration (for example the `log_errors` and `error_log` directives) before concluding that no fatal error occurred; an absent or inaccessible log does not indicate an absent failure. Depending on the hosting stack, "the PHP error log" may in practice be the web server's own error log, a separate PHP-FPM pool log, or a hosting-control-panel-specific log rather than one universal file; confirm which applies to the environment at hand. Log rotation may also mean that evidence for an earlier occurrence is no longer present in the current log file.
5. Where WordPress's own `debug.log` is enabled (`WP_DEBUG` and `WP_DEBUG_LOG` set in `wp-config.php`) and WordPress reached far enough into bootstrap to write to it, review it as a secondary source. Do not treat its absence as evidence that no fatal error occurred; WordPress may fail before its own logging is configured.
6. Capture the exact PHP error class and message where available (for example, `Uncaught Error: Call to undefined function ...`, `Uncaught TypeError: ...`, `Allowed memory size ... exhausted`, `Cannot redeclare function ...`).
7. Capture the reported file and line number where available.
8. Capture the stack trace where the environment provides one. Some PHP configurations and logging setups do not produce a stack trace; its absence does not invalidate the rest of the diagnostic procedure.
9. Where multiple fatal-level log entries correspond to a single request, identify the earliest entry as the primary fatal error and treat subsequent entries as secondary symptoms of that same failure unless evidence independently confirms otherwise.
10. Determine, without assuming, which request paths are affected: front-end, administrative, AJAX, cron, REST, and WP-CLI. Test each independently rather than inferring one from another; AJAX requests (`admin-ajax.php`) can fail independently of ordinary administrative page loads, since AJAX has its own direct bootstrap entry point.
11. Where WP-CLI itself fails to bootstrap, do not rely on WP-CLI commands to diagnose the failure; use direct PHP execution or a minimal reproduction script instead, since WP-CLI shares the same bootstrap path.
12. Identify the earliest failing component using the least invasive method available: compare the failure against recent deployments, updates, or file changes (using version control history or backup timestamps) before making any live change.
13. Where isolation of a specific component is warranted by the evidence gathered so far, isolate it by the least destructive means available — for example, renaming a plugin or theme directory at the filesystem level to deactivate it without deleting it — rather than deleting files or database content.
14. Confirm the running PHP version and installed PHP extensions against the stated requirements of WordPress core, the active theme, and active plugins.
15. Where WordPress core corruption is suspected, verify core file integrity against an official WordPress release matching the currently installed version, using WP-CLI only if WP-CLI itself is confirmed to bootstrap successfully.

   ```text
   # Example only — requires a working WP-CLI bootstrap.
   wp core verify-checksums
   ```

This diagnostic procedure does not depend on access to `wp-admin`; every step above can be performed at the filesystem and log level.

---

# 12. Recovery Procedure

Recovery shall correct the verified failing component rather than assume a cause. Recovery actions shall:

- Preserve the original error-state evidence (logs, and a backup or copy of any file about to be changed) before making any change.
- Use the least destructive corrective action supported by the diagnostic evidence: isolate the verified failing component (for example, deactivate a specific plugin, must-use plugin, or drop-in by filesystem rename) rather than removing it outright, unless removal has been confirmed necessary.
- Restore a known-good copy of the affected file or component from backup or version control, in preference to editing it in place.
- Where WordPress core corruption is confirmed, restore the affected core files from an official WordPress release matching the installed version. Editing WordPress core files directly is not a normal repair method and shall not be used as a routine corrective action.
- Where the cause is a PHP-version or extension incompatibility, correct it at the PHP or hosting-environment layer (for example, enabling the required extension or adjusting the running PHP version) rather than by modifying WordPress core to work around the incompatibility.
- Avoid deleting plugins, themes, uploads, configuration files, or database content without diagnostic confirmation that doing so is necessary.
- Avoid correcting the symptom by disabling error display or logging; doing so hides the fatal error rather than resolving it.
- Where the diagnostic evidence does not safely identify the failing component, escalate rather than applying a corrective action based on an unverified guess.

---

# 13. Validation

Recovery is successful when:

- PHP no longer terminates with the original fatal error during bootstrap.
- WordPress completes initialization for each request path that was originally affected.
- Each originally affected request path — front-end, administrative, AJAX, cron, REST, and WP-CLI, as applicable — is independently confirmed to succeed. Successful loading of the front-end homepage alone is not sufficient validation.
- No equivalent fatal error appears in the PHP error log or WordPress `debug.log` across repeated, fresh requests.
- The corrected or restored component behaves as intended, or, where it was intentionally isolated rather than restored, that isolation and the reason for it are recorded rather than left unresolved.
- No unrelated repository, filesystem, configuration, or database change was introduced by the recovery action.

---

# 14. Prevention

- Verify PHP-version and extension compatibility before upgrading PHP, WordPress core, plugins, or themes.
- Test updates in a staging environment before applying them to a production environment.
- Monitor the PHP error log for fatal errors proactively, rather than relying solely on user reports.
- Maintain backups or version control history for WordPress core, must-use plugins, drop-ins, plugins, and themes sufficient to restore a known-good state.
- Restrict changes to must-use plugins and drop-ins to reviewed deployments, since both execute unconditionally during bootstrap.
- Maintain an explicit PHP-version support matrix for active plugins and the active theme.

---

# 15. Security Considerations

- Raw PHP fatal-error output may reveal filesystem paths, installed plugin and theme names and versions, or fragments of source code; `display_errors` should not be enabled in a production environment.
- PHP error logs and WordPress `debug.log` may contain sensitive information incidentally captured in a stack trace or variable dump; log files should have filesystem permissions restricting access to authorized administrators only.
- This failure mode does not, by itself, indicate credential exposure or require credential rotation; credential rotation is warranted only where log content or diagnostic evidence specifically indicates a credential was exposed.
- Diagnostic steps that involve viewing log contents should be performed by authorized personnel using access methods consistent with the environment's existing security controls, rather than by relaxing those controls to obtain access.

---

# 16. Related Errors

The following are cited as conceptual distinctions only. No corresponding `WP-ERROR` document currently exists in this repository for any of them, and no link is provided.

1. WP-ERROR-010 — WordPress Configuration File Missing
2. WP-ERROR-011 — WordPress Configuration File Invalid
3. WP-ERROR-012 — WordPress Configuration File PHP Syntax Error

---

# 17. Notes

This entry documents the general observable condition of a PHP fatal error terminating WordPress bootstrap. Consistent with the single-responsibility principle in **SF-SPEC-001** Section 4.3, it does not claim exclusive ownership of every underlying cause listed in Section 10. Cause-specific conditions — for example a missing PHP extension, an unsupported PHP version, WordPress core corruption, a must-use plugin fatal error, a drop-in fatal error, an autoloader or dependency failure, or a missing required bootstrap file — may each be documented by a separate, independently created `WP-ERROR` entry without altering this one.

Command examples in Section 11 are illustrative only. Log file locations, the availability of WP-CLI, and the specific tooling available for core-integrity verification all vary by hosting environment and shall be confirmed for the actual environment before use.

This entry was first reviewed at `docs/reviews/SF-REVIEW-003-WP-ERROR-013-ENGINEERING-REVIEW.md`, an author self-review conducted in the same session as this entry's authoring (not an independent review in the sense required by **SF-SPEC-005** Section 4.1). It was subsequently reviewed independently at `docs/reviews/SF-REVIEW-004-WP-ERROR-013-INDEPENDENT-VERIFICATION.md`, which re-derived findings directly from the governing specifications and this artifact's text without relying on SF-REVIEW-003's conclusions, identified four additional findings SF-REVIEW-003 did not raise, and reached outcome **Approved with Minor Revisions**. The identified revisions were applied and re-validated within that review; its Status was changed to Production Ready on that basis. This document does not itself constitute either review record; see the cited files for the full findings, corrections, and gate decisions. Both review records are retained; SF-REVIEW-004 supersedes SF-REVIEW-003 only for the purpose of the Production Ready gate.

Neither review designated this entry as a Reference Implementation. That designation, governed separately by **SF-SPEC-001** Section 22, has not been sought or asserted here.

One point disclosed across both reviews: this entry describes WordPress's fatal-error handling conditionally and generically (see Section 5 and Section 9) without naming the specific "Recovery Mode" feature by that term, to avoid asserting internal implementation detail beyond what this entry's authors independently verified. A reader searching specifically for the term "Recovery Mode" may not retrieve this document on that phrase alone. SF-REVIEW-004 additionally notes that this entry now discloses the email-delivery dependency of that mechanism (Section 5), without naming the feature itself.

No Reference Implementation is currently designated by **SF-SPEC-001**; this entry's relationship to that designation, and to any future `WP-SCENARIO-XXX` runtime evidence, is not asserted here and shall not be assumed until such evidence or designation actually exists.
