# WP-ERROR-016 — WordPress Core Files Missing or Corrupted

---

# 1. Knowledge Entry

WordPress Core Files Missing or Corrupted

---

# 2. Metadata

* **Error ID:** `WP-ERROR-016`
* **Title:** WordPress Core Files Missing or Corrupted
* **Category:** Filesystem
* **Severity:** Critical
* **Recovery Priority:** Immediate
* **Status:** Production Ready
* **Version:** 1.0

---

# 3. Summary

One or more files that are part of the official WordPress core release — the `wp-admin/` and `wp-includes/` directories and the root-level bootstrap and support files distributed with WordPress — are missing, incomplete, altered from their officially released state, or corrupted, preventing WordPress from completing bootstrap or from operating correctly once bootstrap has completed.

---

# 4. Primary Failure Mode

A file that is part of the official WordPress core distribution for the installed WordPress version is absent, truncated, altered, or otherwise does not match its officially released content, and that condition prevents the affected file from being loaded, parsed, or executed correctly. Depending on which core file is affected and when it is needed, the failure may terminate bootstrap entirely, or may surface only when a specific, less-frequently-used code path is reached during otherwise normal operation.

---

# 5. Severity

This entry is classified **Critical** because, by definition, it covers only cases where core-file integrity has been verified as compromised and that compromise prevents a required execution path from completing:

- WordPress core cannot be assumed to behave correctly, in any respect, once a core file's integrity is in question, since core files interoperate extensively with one another.
- The affected execution path — whether bootstrap itself or a specific later operation — cannot complete while the compromised file remains in that state.
- Remediation cannot be deferred, since no application-level workaround compensates for a corrupted or missing piece of WordPress's own codebase.

A core file difference that does not affect any executed code path (for example, a change to a file that is present but never loaded in the site's configuration) does not meet this entry's scope; see Section 7 (Scope).

---

# 6. Distinction

This entry applies only when verified evidence establishes that a specific WordPress core file is missing, incomplete, altered, or corrupted, and that this condition is the cause of an observed failure.

It is distinct from:

- **WP-ERROR-010 — WordPress Configuration File Missing** (conceptual reference; no corresponding document currently exists in this repository): `wp-config.php` is a site-specific configuration file that the site owner creates; it is not part of the official WordPress core distribution and is not verified by core-file integrity checking. Its absence is a distinct, earlier-stage condition from this entry.
- **WP-ERROR-011 — WordPress Configuration File Invalid** (conceptual reference; no corresponding document currently exists in this repository): concerns the content of `wp-config.php`, not the integrity of WordPress's own distributed files.
- **WP-ERROR-012 — WordPress Configuration File PHP Syntax Error** (conceptual reference; no corresponding document currently exists in this repository): concerns a syntax defect in the site-specific `wp-config.php`, not in a WordPress core file.
- **WP-ERROR-013 — WordPress Bootstrap PHP Fatal Error**: WP-ERROR-013 owns the general condition of a PHP fatal error terminating WordPress bootstrap, regardless of cause. This entry owns the narrower, verified cause-specific condition in which the fatal error, or another observed failure, is attributable to a core file being missing or corrupted. Core-file corruption may produce the fatal error WP-ERROR-013 describes, but the two entries do not own the same responsibility.
- **WP-ERROR-014 — Required PHP Extension Missing**: conceptually independent. A fully intact WordPress core installation can still be missing a required PHP extension, and a corrupted core installation can have every extension present. Evidence shall establish which condition is actually present before concluding this entry applies.
- **WP-ERROR-015 — Unsupported PHP Version**: conceptually independent. A fully supported PHP version can run atop a corrupted core installation, and an unsupported-version failure can occur on a perfectly intact core installation.
- **Plugin, theme, must-use plugin, and drop-in files**: files under `wp-content/` are not part of the official WordPress core distribution and are not in scope for this entry, regardless of whether they are missing or corrupted. A corrupted plugin, theme, must-use plugin, or drop-in is a related but distinct condition that may be documented by a separate `WP-ERROR` entry. WP-CLI provides a separate, parallel capability for verifying installed plugins from the WordPress.org repository (`wp plugin verify-checksums`), distinct from the core-file verification this entry documents.
- **Filesystem permission failures on an otherwise intact file**: a core file that exists, is unaltered, and matches its official content, but cannot be read due to filesystem permissions, produces similar symptoms (the code cannot be loaded) but is not the condition this entry documents; the corrective action (permission repair) differs from restoring file content.
- **Database corruption**: corruption or damage to the WordPress database is a distinct condition from corruption of the core file set on disk, and is excluded from this entry.

---

# 7. Scope

**Covered:** A verified condition in which one or more files belonging to the official WordPress core distribution for the installed version are missing, incomplete, altered from their officially released content, or corrupted, and this condition prevents an execution path — bootstrap or a later operation — from completing correctly.

**Excluded:**

- Missing, invalid, or syntactically broken `wp-config.php` (a site-specific file, not a core file).
- General PHP fatal errors whose cause has not been verified as core-file integrity.
- Missing PHP extensions, independent of core-file integrity (see WP-ERROR-014).
- An unsupported or incompatible PHP runtime version, independent of core-file integrity (see WP-ERROR-015).
- Missing or corrupted plugin, theme, must-use plugin, or drop-in files under `wp-content/`.
- A core file that differs from its official release but is never loaded or executed by the site's actual configuration.
- Filesystem permission failures on files whose content is otherwise intact.
- Database corruption, as distinct from filesystem-level core-file corruption.

---

# 8. WordPress Components

Listed as components commonly involved, not as a claim that every corruption incident affects every one of them:

- The `wp-admin/` directory, containing the administrative interface's code.
- The `wp-includes/` directory, containing the majority of WordPress's core functions, classes, and libraries, including files depended upon by both `wp-admin/` and the front end.
- Root-level bootstrap and support files distributed with WordPress core (for example `index.php`, `wp-blog-header.php`, `wp-load.php`, `wp-settings.php`, `wp-login.php`, `wp-cron.php`, `xmlrpc.php`, `wp-config-sample.php`).
- WP-CLI's own core-integrity verification capability (`wp core verify-checksums`), which depends on WP-CLI itself being able to bootstrap far enough to run.
- Any file-integrity monitoring or security-scanning tooling in use on the site, where present.

---

# 9. Typical Symptoms

- A PHP fatal error referencing a class, function, or file within `wp-admin/` or `wp-includes/` that cannot be found, loaded, or parsed.
- A White Screen of Death or an administrative-dashboard-only failure, depending on which core files are affected.
- WordPress operating correctly for most requests but failing on a specific, less-frequently-exercised code path, when only a narrowly-scoped core file is affected.
- `wp core verify-checksums` (where WP-CLI itself can bootstrap) reporting one or more core files that do not match the official checksums for the installed WordPress version.
- A site that previously worked normally beginning to fail immediately after a compromised deployment, an interrupted file transfer, a failed WordPress update, or unauthorized filesystem access.
- Visibly altered front-end or admin behavior consistent with injected code (for example, unexpected redirects or unexpected output), where the underlying cause is a maliciously altered core file rather than a legitimate application defect.

---

# 10. Common Causes

Causes are grouped by category. Inclusion in this list identifies a category as plausible; it does not assert that any specific cause is present without diagnostic confirmation.

- An interrupted or incomplete file transfer during deployment, migration, or a WordPress core update, leaving one or more core files partially written.
- Accidental deletion or modification of core files by a site administrator or developer, including well-intentioned but unsupported manual edits to WordPress core.
- Unauthorized filesystem access resulting in deliberate alteration of core files (for example, injected malware, a backdoor, or a defacement), typically following compromise of credentials, a vulnerable plugin or theme, or the hosting environment itself.
- Filesystem-level corruption unrelated to any application-level cause (for example, a failing storage device, an interrupted disk operation, or a filesystem error during a host-level incident).
- A WordPress core update that did not complete successfully, leaving a mixture of file versions that do not correspond to any single, coherent WordPress release.
- Restoration from an incomplete, corrupted, or mismatched backup that did not include a fully intact core file set.

---

# 11. Diagnosis

Verify the following:

1. Confirm this is a genuine core-file integrity condition rather than a `wp-config.php` defect, a missing PHP extension, an unsupported PHP version, a plugin/theme/must-use-plugin/drop-in issue, a filesystem permission issue on an otherwise intact file, or database corruption.
2. Capture the exact fatal error or other observed failure, including any referenced file path, class, or function.
3. Determine the precise installed WordPress version, since core-file verification depends on comparing against the checksums for that exact version.
4. Where WP-CLI can bootstrap, run core-file verification against the official checksums for the installed version, and record which specific files, if any, are reported as different from the official release.

   ```text
   # Example only — requires a working WP-CLI bootstrap for the affected installation.
   wp core verify-checksums
   ```

5. Where WP-CLI cannot bootstrap (for example, because the corruption itself prevents bootstrap), obtain a fresh, official copy of WordPress at the exact same version, and compare the installed `wp-admin/` and `wp-includes/` directories and root-level core files against it directly, rather than assuming WP-CLI verification is the only available method.
6. For each file identified as different from the official release, determine whether it is missing entirely, truncated, or altered in content.
7. Preserve the current state of the affected files — copies of the altered or corrupted files, and their filesystem timestamps and permissions — before making any change, particularly where malicious alteration is suspected.
8. Where alteration appears deliberate or malicious rather than accidental, treat this as a potential security incident: identify how the alteration became possible (for example, compromised credentials, a vulnerable plugin or theme, or exposed administrative access) in addition to identifying which files were altered.
9. Separate the primary core-file integrity condition from secondary errors it may produce downstream.
10. Confirm whether the affected files are limited to `wp-admin/`, `wp-includes/`, and root-level core files, or whether `wp-content/` (plugins, themes, uploads) is also affected; corruption limited to `wp-content/` is outside this entry's scope even if discovered during the same investigation.
11. Where the hosting environment does not permit direct filesystem inspection or modification, escalate to the hosting or platform administrator rather than attempting an unsafe workaround.

---

# 12. Recovery Procedure

Recovery shall target the verified affected files and the verified cause, not merely the visible symptom.

Permitted recovery categories, depending on the verified cause, include:

- Restoring the affected core files from an official WordPress release matching the exact installed version, rather than from an assumed-compatible or newer version.
- Where WP-CLI can bootstrap, using it to re-download and reinstall the official core file set for the installed version, then re-verifying with `wp core verify-checksums`.
- Where the cause was an interrupted deployment, migration, or update, completing that operation correctly rather than only patching the specific files currently causing visible failure.
- Where alteration was deliberate or malicious, closing the vector that allowed the alteration (for example, rotating compromised credentials, updating or removing a vulnerable plugin or theme, or correcting exposed administrative access) in addition to restoring the affected files; restoring files alone, without addressing the vector, leaves the site subject to immediate re-compromise.
- Escalating to the hosting or platform administrator where the engineer performing diagnosis does not control the filesystem, or where the scope of compromise suggests a host-level incident beyond a single site.

Editing WordPress core files directly is not a normal repair method and shall not be used as a routine corrective action; recovery shall replace affected files with their official, unaltered equivalents rather than hand-editing them back into a plausible-looking state. Recovery shall not merely suppress the visible symptom (for example, by disabling error display) without correcting the underlying file integrity.

---

# 13. Validation

Recovery is successful when:

- Core-file verification (`wp core verify-checksums` or an equivalent direct comparison against an official release) reports no remaining differences for the installed WordPress version.
- The originally affected execution path — bootstrap or the specific later operation — completes correctly.
- No equivalent core-file-related fatal error appears in relevant logs across repeated, fresh requests.
- Where the cause was deliberate alteration, the vector that allowed the alteration has been verified closed, not merely assumed closed.
- No unrelated file, plugin, theme, or configuration was altered or lost in the course of restoring core files.
- Where a broader compromise was suspected, the scope of the investigation was not limited to the files that happened to cause a visible symptom; the full core file set was verified, not only the files known to have failed visibly.

---

# 14. Prevention

- Verify core-file integrity as a routine part of deployment and release processes, not only in response to an observed failure.
- Restrict write permissions on `wp-admin/` and `wp-includes/` to the minimum necessary for legitimate WordPress core updates.
- Avoid manual edits to WordPress core files; make necessary customizations through plugins, themes, or supported extension points instead.
- Apply WordPress core updates through official channels (WordPress's own update mechanism, WP-CLI, or an equivalent trusted deployment pipeline) rather than ad hoc file copying.
- Maintain backups that include a complete, verifiable core file set, and periodically test restoration from those backups.
- Monitor for unauthorized filesystem changes using file-integrity monitoring where available, particularly for installations with a history of compromise attempts.
- Maintain WordPress core files under version control where practical, as an independent means of making unauthorized or unexpected changes visible, complementary to file-integrity monitoring and routine checksum verification.

---

# 15. Security Considerations

- Treat unexplained core-file alteration as a potential security incident until ruled out, not merely as a file-integrity inconvenience to be silently repaired.
- Do not restore affected files without also investigating how the alteration occurred; restoring files while leaving a compromised credential, plugin, or theme in place invites immediate re-compromise.
- Preserve a copy of the altered files before overwriting them, since they may be needed as evidence for further investigation.
- Do not expose diagnostic output (for example, detailed file-comparison results or file paths) publicly during investigation.
- Coordinate credential rotation and further security review through a platform-appropriate process where compromise is confirmed or suspected, rather than treating file restoration alone as sufficient remediation.

---

# 16. Related Errors

The following are cited as conceptual distinctions only unless a repository link is noted.

1. WP-ERROR-010 — WordPress Configuration File Missing (conceptual reference; no corresponding document currently exists in this repository; no link is provided).
2. WP-ERROR-011 — WordPress Configuration File Invalid (conceptual reference; no corresponding document currently exists in this repository; no link is provided).
3. WP-ERROR-012 — WordPress Configuration File PHP Syntax Error (conceptual reference; no corresponding document currently exists in this repository; no link is provided).
4. [WP-ERROR-013 — WordPress Bootstrap PHP Fatal Error](WP-ERROR-013-WORDPRESS-BOOTSTRAP-PHP-FATAL-ERROR.md) — exists in this repository; see Section 6 (Distinction) above.
5. [WP-ERROR-014 — Required PHP Extension Missing](WP-ERROR-014-REQUIRED-PHP-EXTENSION-MISSING.md) — exists in this repository; see Section 6 (Distinction) above.
6. [WP-ERROR-015 — Unsupported PHP Version](WP-ERROR-015-UNSUPPORTED-PHP-VERSION.md) — exists in this repository; see Section 6 (Distinction) above.

---

# 17. Notes

This entry documents the general, verified observable condition of WordPress core-file integrity being compromised. It does not claim that every deployment, update, or migration will encounter this condition, and it does not claim malicious alteration is the most common cause; accidental and infrastructure-level causes are equally in scope. Consistent with the single-responsibility principle in **SF-SPEC-001** Section 4.3, cause-specific conditions (for example, a specific known malware pattern, or corruption tied to a specific hosting platform's known incident) may each be documented by a separate, independently created `WP-ERROR` entry without altering this one.

Command examples in Section 11 are illustrative only and depend on WP-CLI being available and able to bootstrap for the affected installation.

This entry underwent the review sequence required by **SF-SPEC-001** Section 19, **SF-SPEC-005** Section 5.6, and **SF-SPEC-012**: an author (Class A) review at `docs/reviews/SF-REVIEW-010-WP-ERROR-016-AUTHOR-REVIEW.md`, followed by an independent (Class B) review at `docs/reviews/SF-REVIEW-011-WP-ERROR-016-INDEPENDENT-REVIEW.md`, which reached outcome **Approved with Minor Revisions**, applied and re-validated the one required revision, and satisfied the Production Ready gate per SF-SPEC-012 Section 12. Its Status was changed to Production Ready on that basis. This document does not itself constitute either review record; see the cited files for full findings, corrections, and gate decisions.

The independent review did not designate this entry as a Reference Implementation. That designation, governed separately by **SF-SPEC-001** Section 22, has not been sought or asserted here.

No Reference Implementation is currently designated by **SF-SPEC-001**; this entry's relationship to that designation, and to any future `WP-SCENARIO-XXX` runtime evidence, is not asserted here and shall not be assumed until such evidence or designation actually exists.
