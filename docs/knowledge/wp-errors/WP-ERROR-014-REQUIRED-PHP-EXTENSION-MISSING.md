# WP-ERROR-014 — Required PHP Extension Missing

---

# 1. Knowledge Entry

Required PHP Extension Missing

---

# 2. Metadata

* **Error ID:** `WP-ERROR-014`
* **Title:** Required PHP Extension Missing
* **Category:** PHP Runtime
* **Severity:** Critical
* **Recovery Priority:** Immediate
* **Status:** Production Ready
* **Version:** 1.0

---

# 3. Summary

WordPress, a required WordPress component, or an explicitly required application dependency cannot complete the relevant execution path because a required PHP extension is unavailable to the active PHP runtime executing that path. The extension may be absent, disabled, or loaded in a different runtime context than the one handling the failing request.

---

# 4. Primary Failure Mode

A required PHP extension is unavailable to the PHP runtime and SAPI executing a specific request or process, and that unavailability prevents WordPress core, a specific WordPress feature, a plugin, a theme, or an explicitly required Composer dependency from completing the execution path that depends on it. The extension may be not installed, not enabled, disabled in the active configuration, absent from the specific SAPI in use, unavailable after a PHP runtime change, or loaded in one runtime context (for example, CLI) but not another (for example, the web-facing PHP-FPM pool serving the same site).

---

# 5. Severity

This entry is classified **Critical** because, by definition, it covers only cases where a *required* extension is unavailable to a *required* execution path — not cases where an optional or merely recommended extension is absent:

- The affected execution path cannot complete at all while the condition persists; there is no partial degradation for that specific path.
- The requiring component (WordPress core, a specific feature, a plugin, a theme, or a declared Composer dependency) has already established that the extension is mandatory for the path in question.
- Remediation cannot be deferred for the affected path, since no application-level workaround exists once a genuinely required extension is absent from the runtime handling the request.

An unavailable *optional* extension does not meet this entry's scope and is not, by itself, Critical; see Section 7 (Scope).

---

# 6. Distinction

This entry applies only when verified evidence establishes that a specific PHP extension is unavailable to the specific runtime and SAPI executing a request or process that requires it.

It is distinct from:

- **WP-ERROR-013 — WordPress Bootstrap PHP Fatal Error**: WP-ERROR-013 owns the general condition of a PHP fatal error terminating WordPress bootstrap, regardless of cause. This entry owns the narrower, verified cause-specific condition in which the fatal error (or other observed failure) is attributable to a required PHP extension being unavailable. A missing extension may produce the fatal error WP-ERROR-013 describes, but the two entries do not own the same responsibility: WP-ERROR-013 covers the general symptom class, this entry covers one verified, specific cause within it.
- **[WP-ERROR-015 — Unsupported PHP Version](WP-ERROR-015-UNSUPPORTED-PHP-VERSION.md)**: covers a PHP runtime version that is unsupported or incompatible with WordPress, a plugin, or a theme — a version-level condition. This entry covers an *extension* being unavailable within whatever PHP version is running; the version itself may be fully supported. The two conditions are conceptually independent: a supported PHP version can still be missing a required extension, and an unsupported PHP version can have every extension present.
- **Missing PHP function or class from userland code**: an undefined function or missing class defined by a plugin, theme, or the application's own code — not by a PHP extension — is not in scope. This entry requires evidence that the missing symbol is one a PHP extension itself provides (for example, a class or function belonging to `mysqli`, `curl`, `mbstring`, `DOMDocument`, or `ZipArchive`), not a symbol the application was expected to define itself.
- **Autoloader or Composer dependency failure**: a missing package class caused by an absent or unautoloaded Composer package is not automatically a missing PHP extension. It is in scope only where the verified cause is that a declared Composer platform requirement (`ext-*`) is unmet by the PHP runtime, not where the cause is a missing vendor package, a broken autoloader, or an uninstalled dependency.
- **Configuration file errors**: missing `wp-config.php`, invalid configuration values, PHP syntax errors, or other unrelated PHP configuration defects are excluded; these are covered, where applicable, by other WP-ERROR entries.
- **Database failures**: database authentication, selection, permission, schema, corruption, capacity, network, and timeout failures are excluded, unless the immediate verified condition is that the active PHP runtime lacks the database extension (for example `mysqli` or `pdo_mysql`) required even to attempt the connection. Once a connection attempt is made using an available extension, any subsequent failure is a database failure, not an extension-availability failure.
- **Optional capability absence**: an unavailable extension that supports only an optional or recommended feature, where the required execution path completes without it, does not belong to this entry unless the specific application, plugin, or theme explicitly requires that feature and documents it as mandatory.

---

# 7. Scope

**Covered:** A verified condition in which a PHP extension required by WordPress core, a specific WordPress feature, an active plugin or theme, or a declared Composer platform requirement is unavailable to the PHP runtime and SAPI executing the affected request or process, preventing that execution path from completing.

**Excluded:**

- Absence of an extension that is merely optional or recommended, where the required execution path completes without it.
- General PHP fatal errors whose cause has not been verified as extension unavailability.
- An unsupported or incompatible PHP runtime version, independent of extension availability.
- Missing functions or classes defined by userland (plugin, theme, or application) code rather than by a PHP extension.
- Missing Composer packages or broken autoloading, where no PHP platform requirement (`ext-*`) is unmet.
- Missing, invalid, or syntactically broken `wp-config.php` or other configuration-file defects unrelated to extension availability.
- Database connectivity, authentication, permission, schema, corruption, capacity, and timeout failures, except where the verified immediate cause is the absence of the database extension needed to attempt the connection.

---

# 8. WordPress Components

Listed as components commonly involved, not as a claim that every installation requires every one of them:

- WordPress core's database abstraction layer (`wp-db.php`), which requires a database extension such as `mysqli` or `pdo_mysql` to attempt a connection.
- WordPress core's internationalization and string-handling functions, where multibyte-safe behavior depends on `mbstring`, and where locale-aware formatting in some contexts depends on `intl`.
- The media and image-processing subsystem, which depends on `gd` or the `Imagick` class provided by the `imagick` extension, for image manipulation (resizing, format conversion, thumbnail generation).
- XML-dependent subsystems, including feed parsing (SimplePie), XML-RPC, and portions of REST API and import/export functionality, which may depend on `xml`, `dom`, or `simplexml`.
- The HTTP API, which commonly depends on `curl` (WordPress also supports a streams-based transport fallback where `curl` is unavailable, subject to the requiring component's own configuration).
- Password hashing and cryptographic functions, where certain implementations depend on `openssl` or `sodium`.
- Import/export and packaging features that depend on `zip`.
- Plugin- or theme-declared dependencies, and Composer-managed dependencies declaring `ext-*` platform requirements.
- WP-CLI, which executes under its own PHP CLI runtime and may have different extension availability than the web-facing runtime for the same site.

---

# 9. Typical Symptoms

- A PHP fatal error referencing an undefined function or missing class that corresponds to a specific extension (for example, a class or function belonging to `mysqli`, `DOMDocument`, or `ZipArchive`).
- A WordPress Site Health warning identifying a missing or recommended PHP extension.
- A plugin or theme activation blocked by a requirements-not-met notice naming a specific extension — see [WP-ERROR-031](WP-ERROR-031-PLUGIN-ACTIVATION-FAILURE.md) for the plugin-activation-specific diagnostic entry point, or [WP-ERROR-039](WP-ERROR-039-THEME-ACTIVATION-FAILURE.md) for the theme-switching-specific diagnostic entry point.
- A Composer install or update failing with a platform requirement error naming an `ext-*` requirement.
- A specific WordPress feature failing (for example, image resizing or a specific REST endpoint) while the rest of the site continues to function normally, when the missing extension is required only by that feature.
- WP-CLI commands failing differently than web requests for the same site, when the CLI and web PHP runtimes have different extension availability.
- A working site suddenly failing a specific feature after a PHP version upgrade, hosting migration, or container image change, without any code change having been made.

---

# 10. Common Causes

Causes are grouped by category. Inclusion in this list identifies a category as plausible; it does not assert that any specific cause is present without diagnostic confirmation, and it does not assert that WordPress core requires every extension named here.

- The extension is not installed on the system or in the container image serving the affected runtime.
- The extension is installed but not enabled in the active PHP configuration.
- The extension is explicitly disabled for the specific PHP SAPI handling the request (for example, enabled for CLI but not for the PHP-FPM pool serving web requests, or vice versa).
- The extension is absent from the specific PHP SAPI executing the request due to separate configuration files or separate installations for CLI, PHP-FPM, Apache module PHP, or CGI/FastCGI.
- The extension became unavailable after a PHP runtime change, such as a PHP version upgrade, a hosting migration, or a container image rebuild that did not carry forward the same extension set.
- The extension is loaded in one runtime context (for example, a developer's local CLI environment) but not in another (for example, the production web-facing runtime), leading to a discrepancy between environments.
- The requiring component is WordPress core itself, in which case the requirement is generally not optional.
- The requiring component is a specific WordPress feature (for example, image editing) rather than WordPress core as a whole, in which case only that feature is affected.
- The requiring component is an active plugin or theme, which may declare the extension as a hard requirement in its own documentation or activation checks.
- The requiring component is a Composer-managed dependency declaring an `ext-*` platform requirement that the runtime does not satisfy.
- The extension is merely recommended or optional for the requiring component, in which case its absence does not necessarily prevent the execution path from completing.

---

# 11. Diagnosis

Verify the following:

1. Confirm this is a genuine missing-or-unavailable-extension condition rather than an undefined userland function or class, a missing Composer package with no unmet PHP platform requirement, a `wp-config.php` defect, or a database failure unrelated to extension availability.
2. Capture the exact error message, Site Health warning, or requirement-failure output produced by the affected execution path.
3. Identify the specific PHP extension believed to be unavailable.
4. Identify which component — WordPress core, a specific WordPress feature, an active plugin, an active theme, or a declared Composer dependency — requires that extension, and confirm the requirement is real (for example, by checking the plugin's or theme's own documented requirements, or the `require` section of a `composer.json` for an `ext-*` entry).
5. Determine whether the requirement is mandatory for the execution path that failed, or merely optional or recommended for that component.
6. Identify the PHP SAPI (CLI, PHP-FPM, Apache module, CGI/FastCGI, a scheduled-job runner such as system cron invoking WP-Cron or WP-CLI, or a hosting control panel's own execution context) and PHP version executing the specific failing path. Do not assume it is the same SAPI or version used by any other runtime on the same system.
7. Identify the configuration files loaded by that specific runtime — the primary configuration file and any additional scanned `.ini` files — rather than assuming they match another runtime's configuration.
8. Verify extension availability within that same runtime and configuration context. Do not infer web-runtime extension availability from `php -m` run in an unrelated CLI shell alone; `php -m` reports on the runtime that executed that specific command, which may differ from the runtime serving web requests.
9. Where CLI and web behavior differ, explicitly compare both runtimes' loaded extensions and configuration, since they may be entirely separate PHP installations, versions, or configurations even on the same host.
10. Determine whether the extension is absent, disabled, misconfigured, loaded only in a runtime context other than the one that failed, or loaded but built without the specific capability the requiring component needs (for example, a `gd` build without a specific image format, or a `curl` build without a specific SSL backend or protocol) — presence in a module listing does not by itself confirm every capability a component depends on is present.
11. Preserve relevant evidence — error messages, module listings, and loaded configuration file paths — before making any configuration change.
12. Separate the primary missing-extension condition from secondary errors it may produce downstream (for example, a subsequent unrelated warning triggered only because the primary operation failed).
13. Where the hosting environment does not permit safe inspection or modification of the PHP runtime (for example, restricted shared hosting), escalate to the hosting or platform administrator rather than attempting an unsafe workaround.

If runtime inspection is performed using `phpinfo()` or an equivalent diagnostic script, it shall not be left as a publicly accessible, unrestricted file. Restrict access to it, expose only the minimum information needed, and remove it immediately after use.

```text
# Example only — illustrates checking a specific runtime's loaded extensions;
# does not by itself confirm what the web-facing runtime has loaded.
php -m
```

---

# 12. Recovery Procedure

Recovery shall target the verified runtime and the verified requirement, not merely the visible symptom.

Permitted recovery categories, depending on the verified cause, include:

- Installing the required extension for the specific runtime and SAPI that failed, or reinstalling/rebuilding it with the specific capability the requiring component needs, where the extension is present but was built without that capability.
- Enabling an already-installed extension that is currently disabled for that runtime.
- Correcting the active PHP configuration, including SAPI-specific configuration, so the extension loads for the affected runtime.
- Selecting or configuring the correct PHP runtime or version for the affected execution path, where multiple versions are installed.
- Rebuilding or replacing a container image to include the required extension, where the runtime is container-based.
- Restarting or reloading the relevant PHP or web server process, where the runtime requires it to pick up a configuration change.
- Correcting hosting-provider configuration where the hosting environment, rather than the application, controls extension availability.
- Changing the requiring plugin, theme, or dependency only where diagnosis has established that the extension is not, in fact, truly mandatory for the affected path.
- Escalating to the hosting or platform administrator where the engineer performing diagnosis does not control the runtime.

This entry does not universally prescribe `apt`, `yum`, `dnf`, Homebrew, PECL, direct `.ini` edits, service restarts, container-rebuild commands, or hosting-panel steps as the correct action in every environment; the correct package name, installation method, and service name vary by operating system, distribution, PHP version, hosting provider, container image, and server architecture. Any platform-specific command included in supporting documentation shall be clearly labeled as an illustrative example, not a universal instruction.

Recovery shall not suppress the error by disabling error display or logging, polyfill the extension's native behavior in application code without a documented technical justification, or edit WordPress core to work around the missing extension.

Where a configuration change requires a service restart or reload to take effect, recovery is not complete until the active process is confirmed to be using the corrected configuration, not merely until the configuration file itself has been edited.

---

# 13. Validation

Recovery is successful when:

- The required extension is available to the same PHP runtime and SAPI that originally failed — verified in that same context, not inferred from a different runtime.
- The expected extension version or capability is present, where version-specific behavior is relevant to the requiring component.
- The original execution path completes.
- WordPress completes the relevant initialization or request that was previously affected.
- The requiring plugin, theme, feature, or dependency behaves correctly, not merely that the originally observed error no longer appears.
- CLI and web contexts are validated separately when both were relevant to the original failure; success in one context does not establish success in the other.
- No equivalent missing-extension error appears in relevant logs across repeated, fresh requests or process runs.
- No unintended PHP version or configuration change was introduced as a side effect of the correction.
- No unrelated extension was disabled in the course of correcting this one.
- No diagnostic artifact (for example, a `phpinfo()` script) remains publicly accessible after validation.
- Where a service restart or reload was required, the active running process — not only the edited configuration file — is confirmed to reflect the correction.

---

# 14. Prevention

- Document explicit PHP extension requirements as part of deployment documentation.
- Declare Composer platform requirements (`ext-*`) for dependencies that genuinely require a specific extension.
- Validate the target environment's extension availability as part of deployment, rather than discovering gaps after deployment.
- Check for parity between development, staging, production, CLI, and web runtimes, since these can silently diverge.
- Validate container images for required extensions before promoting them to production use.
- Include PHP-extension verification as part of PHP-upgrade and hosting-migration checklists.
- Use automated health checks (including WordPress's own Site Health) to surface missing extensions proactively.
- Re-test affected features after any PHP runtime, hosting, or container change, rather than assuming prior behavior still holds.

---

# 15. Security Considerations

- Do not publicly expose `phpinfo()` output; it can reveal loaded configuration file paths, installed extension versions, and other environment details useful to an attacker.
- Do not publish loaded configuration-file paths or scanned `.ini` file locations outside of a controlled diagnostic context.
- Do not expose environment variables, credentials, or tokens that may appear in runtime diagnostic output.
- Do not grant broad or unnecessary filesystem permissions merely to make an extension load; extension loading does not require loosening unrelated file or directory permissions.
- Do not disable unrelated security controls (for example, `open_basedir`, `disable_functions` restrictions) as a means of making an extension appear to work.
- Install extensions only from trusted package sources appropriate to the platform in use; do not install untrusted or unverified extension binaries.
- Preserve a recoverable copy of any production PHP configuration file before modifying it.

---

# 16. Related Errors

The following are cited as they exist in this repository, or as conceptual distinctions where noted.

1. [WP-ERROR-013 — WordPress Bootstrap PHP Fatal Error](WP-ERROR-013-WORDPRESS-BOOTSTRAP-PHP-FATAL-ERROR.md) — exists in this repository; see Section 6 (Distinction) above for how the two entries' ownership differs.
2. [WP-ERROR-015 — Unsupported PHP Version](WP-ERROR-015-UNSUPPORTED-PHP-VERSION.md) — exists in this repository; see Section 6 (Distinction) above for how the two conceptually independent conditions are told apart.

---

# 17. Notes

This entry documents the general, verified observable condition of a required PHP extension being unavailable to the runtime that needs it. It does not claim that WordPress core, or any specific plugin or theme, requires every extension named in Section 5 or Section 8 above; those are listed as commonly relevant examples, not universal requirements. Consistent with the single-responsibility principle in **SF-SPEC-001** Section 4.3, cause-specific conditions for individual extensions, individual hosting platforms, or individual plugin/theme requirements may each be documented by a separate, independently created `WP-ERROR` entry without altering this one.

Command examples in Section 11 and Section 12 are illustrative only. Package names, service names, and installation methods vary by operating system, distribution, PHP version, hosting provider, container image, and server architecture, and shall be confirmed for the actual environment before use.

This entry underwent the review sequence required by **SF-SPEC-001** Section 19, **SF-SPEC-005** Section 5.6, and **SF-SPEC-012**: an author (Class A) review at `docs/reviews/SF-REVIEW-006-WP-ERROR-014-AUTHOR-REVIEW.md`, followed by an independent (Class B) review at `docs/reviews/SF-REVIEW-007-WP-ERROR-014-INDEPENDENT-REVIEW.md`, which reached outcome **Approved with Minor Revisions**, applied and re-validated the one required revision, and satisfied the Production Ready gate per SF-SPEC-012 Section 12. Its Status was changed to Production Ready on that basis. This document does not itself constitute either review record; see the cited files for full findings, corrections, and gate decisions.

The independent review did not designate this entry as a Reference Implementation. That designation, governed separately by **SF-SPEC-001** Section 22, has not been sought or asserted here.

No Reference Implementation is currently designated by **SF-SPEC-001**; this entry's relationship to that designation, and to any future `WP-SCENARIO-XXX` runtime evidence, is not asserted here and shall not be assumed until such evidence or designation actually exists.
