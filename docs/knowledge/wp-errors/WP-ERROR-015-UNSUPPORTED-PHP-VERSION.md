# WP-ERROR-015 — Unsupported PHP Version

---

# 1. Knowledge Entry

Unsupported PHP Version

---

# 2. Metadata

* **Error ID:** `WP-ERROR-015`
* **Title:** Unsupported PHP Version
* **Category:** PHP Runtime
* **Severity:** Critical
* **Recovery Priority:** Immediate
* **Status:** Production Ready
* **Version:** 1.0

---

# 3. Summary

WordPress, a required WordPress component, an active plugin, an active theme, or a Composer dependency cannot complete the relevant execution path because the PHP version of the active runtime falls outside the range that component requires — either below the minimum version it declares, or affected by a behavior change, removal, or deprecation introduced in a newer PHP version than the component was written to support.

---

# 4. Primary Failure Mode

The PHP version of the runtime executing a specific request or process falls outside the version range required by WordPress core, a specific WordPress feature, an active plugin, an active theme, or a declared Composer dependency, and that mismatch prevents the affected execution path from completing. The mismatch may take the form of the runtime being below a component's declared minimum PHP version, or the runtime being a newer PHP version whose behavior change, function removal, or syntax removal breaks code the component never updated to accommodate.

---

# 5. Severity

This entry is classified **Critical** because, by definition, it covers only cases where a genuine, verified version-range mismatch prevents a *required* execution path from completing:

- The affected execution path cannot complete at all while the mismatch persists for that path.
- The requiring component has already established, through its own declared requirements or through the concrete failure observed, that the running PHP version is outside its supported range for that path.
- Remediation cannot be deferred for the affected path, since no application-level workaround exists once a component's code depends on PHP behavior the running version does not provide, or provides differently.

A PHP deprecation notice that does not itself prevent execution from completing does not meet this entry's scope and is not, by itself, Critical; see Section 7 (Scope).

---

# 6. Distinction

This entry applies only when verified evidence establishes that a specific execution path failed because the running PHP version falls outside the range a specific component requires or supports.

It is distinct from:

- **WP-ERROR-013 — WordPress Bootstrap PHP Fatal Error**: WP-ERROR-013 owns the general condition of a PHP fatal error terminating WordPress bootstrap, regardless of cause. This entry owns the narrower, verified cause-specific condition in which the fatal error is attributable to a PHP version mismatch. A version mismatch may produce the fatal error WP-ERROR-013 describes, but the two entries do not own the same responsibility.
- **WP-ERROR-014 — Required PHP Extension Missing**: the two conditions are conceptually independent. A fully supported PHP version can still be missing a required extension, and an unsupported PHP version can have every extension the requiring component needs. Evidence shall establish which condition is actually present — a version mismatch and an extension-availability failure can produce similar symptoms (a fatal error citing an unavailable function or class) but require different corrective action.
- **Missing PHP function or class from userland code, unrelated to a version change**: an undefined function or missing class caused by a typo, an incomplete deployment, or code that never existed in any PHP version is not in scope here. This entry requires evidence that the specific function, class, or syntax construct existed and behaved differently in a version the component was written for, and that the difference is what is causing the failure.
- **Extension-provided symbols**: a missing function or class that a PHP extension provides (for example, one belonging to `mysqli`, `curl`, or `mbstring`) is evaluated first against **WP-ERROR-014**; only once extension availability has been confirmed and ruled out as the cause does a version-range mismatch become the applicable explanation.
- **Autoloader or Composer dependency failure**: a missing package class caused by an absent or unautoloaded Composer package is not automatically a PHP version issue. It is in scope only where the verified cause is that a declared Composer `require` constraint on `php` itself (for example, `"php": ">=8.1"`) is unmet by the running version, not where the cause is a missing vendor package.
- **Configuration file errors**: missing `wp-config.php`, invalid configuration values, PHP syntax errors introduced by the site's own code, or other unrelated PHP configuration defects are excluded.
- **Database failures**: database connectivity, authentication, permission, schema, corruption, capacity, and timeout failures are excluded and are unrelated to PHP version.
- **Non-fatal deprecation notices**: a PHP deprecation notice that does not prevent the affected execution path from completing is not, by itself, in scope. It becomes relevant only as a leading indicator that a future PHP version may remove the deprecated behavior entirely — see Section 14 (Prevention) — and only becomes a Critical, in-scope condition once the behavior is actually removed and a path fails as a result.
- **End-of-life PHP version with no functional failure**: an outdated or end-of-life PHP version that currently runs every required execution path successfully is a security and support-lifecycle risk, not a functional failure this entry documents; see Section 15 (Security Considerations) for that distinct concern.

---

# 7. Scope

**Covered:** A verified condition in which the PHP version of the runtime executing a specific request or process falls outside the version range required by WordPress core, a specific WordPress feature, an active plugin or theme, or a declared Composer `php` platform constraint, preventing that execution path from completing.

**Excluded:**

- PHP deprecation notices that do not themselves prevent the affected execution path from completing.
- An end-of-life or outdated PHP version that has not caused any verified functional failure.
- Missing functions or classes unrelated to a PHP version difference (typos, incomplete deployments, code that never existed in any version).
- Missing PHP extensions, independent of PHP version (see WP-ERROR-014).
- Missing Composer packages or broken autoloading, where no `php` platform constraint is unmet.
- Missing, invalid, or syntactically broken `wp-config.php`, or other configuration-file defects unrelated to PHP version.
- Database connectivity, authentication, permission, schema, corruption, capacity, and timeout failures.

---

# 8. WordPress Components

Listed as components commonly involved, not as a claim that a single PHP version range applies uniformly across all of them:

- WordPress core itself, which documents a minimum supported PHP version that changes across WordPress core releases; the current minimum for a given WordPress version is authoritative only from WordPress's own current documentation, not from this entry.
- Active plugins and themes, each of which may declare its own `Requires PHP` header, independent of and potentially narrower or wider than WordPress core's own minimum.
- Composer-managed dependencies declaring a `php` version constraint in `composer.json`.
- WP-CLI, which executes under its own PHP CLI runtime and may run a different PHP version than the web-facing runtime for the same site.
- Scheduled-job and hosting control-panel execution contexts, which may run yet another PHP version than either the web-facing runtime or an interactively-invoked CLI.
- Container images, which pin a specific PHP version until the image is rebuilt; a mismatch commonly arises when a component's version requirements change but the image has not been rebuilt, or vice versa.
- Site Health, which reports the running PHP version and flags versions WordPress core no longer recommends.

---

# 9. Typical Symptoms

- A PHP fatal error referencing a function, method, or syntax construct that was removed or changed in the running PHP version. Historical examples include: the curly-brace syntax for string and array offset access, deprecated since PHP 7.4 and removed in PHP 8.0; and the automatic creation of dynamic properties on classes that do not explicitly declare or allow them, deprecated in PHP 8.2. These are cited as real, dated examples of the general phenomenon this entry documents, not as an exhaustive or current list of every version-specific change.
- A WordPress or Site Health notice reporting that the running PHP version is below the recommended or minimum supported version.
- A plugin or theme activation blocked by a `Requires PHP` mismatch notice.
- A Composer install or update failing with a platform requirement error naming the `php` constraint.
- A specific plugin or theme failing after a PHP upgrade, hosting migration, or automatic platform update, while other parts of the site continue to function, when only that component depends on the changed behavior.
- WP-CLI commands behaving differently than web requests for the same site, when the CLI and web PHP versions differ.
- An increase in PHP deprecation notices in logs following a PHP upgrade, without (yet) a corresponding fatal error — a leading indicator rather than, by itself, an in-scope failure.

---

# 10. Common Causes

Causes are grouped by category. Inclusion in this list identifies a category as plausible; it does not assert that any specific cause is present without diagnostic confirmation, and it does not assert a single PHP version range applies to every WordPress installation.

- The running PHP version is below the minimum version required by WordPress core for the installed WordPress version.
- The running PHP version is below the minimum version a specific active plugin or theme declares.
- The running PHP version is newer than the version an active plugin or theme was written and tested for, and that plugin or theme relies on behavior, a function, or a syntax construct that a newer PHP version changed or removed.
- A hosting provider performed an automatic PHP version upgrade without the site owner verifying plugin and theme compatibility first.
- The web-facing runtime and the CLI or scheduled-job runtime are running different PHP versions, so a component that works in one context fails in another.
- A container image pinning an older PHP version than a component's current requirements, or a component's requirements changing without the container image being rebuilt to match.
- A declared Composer `php` platform constraint is narrower than the version actually installed, or the installed version is narrower than the constraint requires.
- Code was written against an earlier PHP version's now-deprecated behavior, which functioned identically for a time but was later fully removed in a subsequent PHP version.

---

# 11. Diagnosis

Verify the following:

1. Confirm this is a genuine PHP-version-range condition rather than a missing PHP extension (see WP-ERROR-014), an unrelated userland defect, a missing Composer package, a `wp-config.php` defect, or a database failure.
2. Capture the exact fatal error, deprecation notice, or requirement-failure message produced by the affected execution path.
3. Identify the PHP version and SAPI (CLI, PHP-FPM, Apache module, CGI/FastCGI, a scheduled-job runner, a hosting control panel's own execution context, or a container image) executing the specific failing path. Do not assume it is the same version used by any other runtime on the same system.
4. Identify which component — WordPress core, a specific WordPress feature, an active plugin, an active theme, or a declared Composer dependency — declares the version requirement that is not met, and confirm the requirement is real (for example, by checking the plugin's or theme's own `Requires PHP` header, WordPress core's current documented minimum, or the `require` section of a `composer.json` for a `php` constraint).
5. Determine whether the failure is caused by the running version being below a declared minimum, or by the running version being newer than what the component supports and removing or changing behavior the component depends on.
6. Where CLI and web behavior differ, explicitly compare the PHP version reported by each runtime, since they may be entirely separate PHP installations even on the same host.
7. Distinguish a non-fatal deprecation notice from an actual fatal error; a deprecation notice alone does not establish that the affected execution path has failed.
8. Preserve relevant evidence — the exact error or notice text, the PHP version reported by each relevant runtime, and any relevant plugin/theme/Composer version-requirement declarations — before making any runtime change.
9. Separate the primary version-mismatch condition from secondary errors it may produce downstream.
10. Where more than one component's declared version requirements conflict (for example, one plugin requiring a PHP version higher than another plugin supports), identify the conflict explicitly rather than resolving it by an arbitrary choice.
11. Where the hosting environment does not permit changing the PHP version, or changed it automatically without the site owner's control, escalate to the hosting or platform administrator rather than attempting an unsafe workaround.

```text
# Example only — reports the version of the runtime that executed this specific command;
# does not by itself confirm the PHP version of the web-facing runtime for the same site.
php -v
```

---

# 12. Recovery Procedure

Recovery shall target the verified runtime and the verified version requirement, not merely the visible symptom.

Permitted recovery categories, depending on the verified cause, include:

- Changing the running PHP version for the specific runtime and SAPI that failed, to a version within the range required by the affected component, including rebuilding or replacing a container image where the runtime is container-based.
- Where multiple components have conflicting version requirements, resolving the conflict deliberately (for example, by updating the component with the narrower or outdated requirement) rather than picking a version arbitrarily.
- Updating the specific plugin, theme, or dependency to a version that supports the currently running PHP version, where an update exists and the extension/version conflict is on the component's side rather than the runtime's.
- Testing the intended PHP version in a staging environment before applying it to production, particularly following a hosting-initiated automatic upgrade.
- Escalating to the hosting or platform administrator where the engineer performing diagnosis does not control the runtime's PHP version.

This entry does not universally prescribe a specific PHP version as the correct target; the correct version depends on the current, specific requirements of WordPress core, the active plugins and themes, and any Composer dependencies in the installation being diagnosed, and these requirements change over time as WordPress and its ecosystem evolve.

Recovery shall not suppress the failure by disabling error display or logging, nor by silencing deprecation notices as a substitute for addressing the underlying incompatibility, nor by editing WordPress core to work around version-specific behavior.

---

# 13. Validation

Recovery is successful when:

- The affected execution path completes on the corrected PHP version.
- WordPress completes the relevant initialization or request that was previously affected.
- The requiring plugin, theme, feature, or dependency behaves correctly, not merely that the originally observed error no longer appears.
- CLI and web contexts are validated separately when both were relevant to the original failure; success in one context does not establish success in the other.
- No equivalent version-related fatal error appears in relevant logs across repeated, fresh requests or process runs.
- No other previously-working component was broken by the version change (a common regression risk when multiple components have different version tolerances).
- Deprecation notices newly visible on the corrected version, if any, are logged and tracked rather than suppressed, since they may indicate a future compatibility risk even though they are not, by themselves, the condition this entry documents.

---

# 14. Prevention

- Track the official support and end-of-life schedule for PHP releases, and WordPress core's current documented minimum and recommended PHP versions.
- Declare `Requires PHP` in plugin and theme headers, and declare an accurate `php` constraint in Composer-managed dependencies.
- Test against the target PHP version in a staging environment before applying a PHP upgrade to production, including hosting-initiated automatic upgrades where the hosting provider allows a preview or staging window.
- Monitor PHP deprecation notices in logs as an early-warning system for behavior that a future PHP major version may remove entirely.
- Check for parity between development, staging, production, CLI, and scheduled-job PHP versions, since these can silently diverge.
- Re-test affected features after any PHP version, hosting, or container change, rather than assuming prior behavior still holds.

---

# 15. Security Considerations

- An end-of-life PHP version may no longer receive security patches, independent of whether it currently runs every required execution path successfully; this is a distinct risk from the functional failure this entry primarily documents, and shall not be assumed absent merely because no functional failure has yet been observed.
- Do not suppress error display or deprecation notices in a way that hides a version-incompatibility signal from administrators, even where it is also hidden from public output.
- Do not disable unrelated security controls to force a component to appear compatible with a PHP version it does not actually support.
- Preserve a recoverable copy or documented rollback path for the PHP runtime configuration before changing the active PHP version in a production environment.
- Coordinate PHP version changes through a platform-appropriate administrative process rather than an ad hoc, undocumented change to a shared or production runtime.

---

# 16. Related Errors

The following are cited as conceptual distinctions only unless a repository link is noted.

1. [WP-ERROR-013 — WordPress Bootstrap PHP Fatal Error](WP-ERROR-013-WORDPRESS-BOOTSTRAP-PHP-FATAL-ERROR.md) — exists in this repository; see Section 6 (Distinction) above for how the two entries' ownership differs.
2. [WP-ERROR-014 — Required PHP Extension Missing](WP-ERROR-014-REQUIRED-PHP-EXTENSION-MISSING.md) — exists in this repository; see Section 6 (Distinction) above for how the two conceptually independent conditions are told apart.

---

# 17. Notes

This entry documents the general, verified observable condition of a PHP version-range mismatch preventing a required execution path from completing. It does not claim a single PHP version, minimum, or range applies uniformly to every WordPress installation; the applicable range is always specific to the WordPress version, plugins, themes, and dependencies actually in use, and changes over time as the ecosystem evolves. Consistent with the single-responsibility principle in **SF-SPEC-001** Section 4.3, cause-specific conditions for individual PHP version transitions (for example, a specific removed function or a specific major-version migration) may each be documented by a separate, independently created `WP-ERROR` entry without altering this one.

Command examples in Section 11 are illustrative only and shall be confirmed for the actual environment before use.

This entry underwent the review sequence required by **SF-SPEC-001** Section 19, **SF-SPEC-005** Section 5.6, and **SF-SPEC-012**: an author (Class A) review at `docs/reviews/SF-REVIEW-008-WP-ERROR-015-AUTHOR-REVIEW.md`, followed by an independent (Class B) review at `docs/reviews/SF-REVIEW-009-WP-ERROR-015-INDEPENDENT-REVIEW.md`, which reached outcome **Approved with Minor Revisions**, applied and re-validated the one required revision, and satisfied the Production Ready gate per SF-SPEC-012 Section 12. Its Status was changed to Production Ready on that basis. This document does not itself constitute either review record; see the cited files for full findings, corrections, and gate decisions.

The independent review did not designate this entry as a Reference Implementation. That designation, governed separately by **SF-SPEC-001** Section 22, has not been sought or asserted here.

No Reference Implementation is currently designated by **SF-SPEC-001**; this entry's relationship to that designation, and to any future `WP-SCENARIO-XXX` runtime evidence, is not asserted here and shall not be assumed until such evidence or designation actually exists.
