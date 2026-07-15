# WP-ERROR-041 — WP-CLI Cannot Locate a WordPress Installation

---

# 1. Knowledge Entry

WP-CLI Cannot Locate a WordPress Installation

---

# 2. Metadata

* **Error ID:** `WP-ERROR-041`
* **Title:** WP-CLI Cannot Locate a WordPress Installation
* **Category:** CLI
* **Severity:** Low
* **Recovery Priority:** Low
* **Status:** Production Ready
* **Version:** 1.0

---

# 3. Summary

WP-CLI's own pre-bootstrap discovery process — searching from the current working directory (or the location `--path` specifies) for a WordPress installation it recognizes — fails to find one. WP-CLI halts immediately with its own distinct error message and exits with a non-zero status, without ever invoking `wp-load.php` or executing any WordPress code at all, and with no effect whatsoever on a live site that may exist elsewhere on the same server.

---

# 4. Primary Failure Mode

An operator, or an automated script, invokes a `wp` subcommand from a working directory — or with an explicit `--path` value — that does not correspond to, and is not contained within, an actual WordPress installation's own directory structure as WP-CLI's own detection logic recognizes it. WP-CLI performs its own directory-tree search, distinct from and occurring entirely before WordPress's own `wp-load.php` would ever run its own, simpler existence check, and that search fails to identify an installation root. WP-CLI halts before attempting to load anything WordPress-related: no database connection is attempted, no WordPress hook fires, and any live site that genuinely exists elsewhere is entirely unaffected, since this condition never reaches, and cannot disturb, any already-running web-facing process.

---

# 5. Severity

This entry is classified **Low**, a deliberate departure from this catalog's usual range-based Critical classification and from the High/High exceptions this catalog has previously reasoned (`WP-ERROR-009`, `034`, `036`, `037`), each of which still involves genuine, if non-fatal, site-facing impact (a degraded feature, a rejected upload, reduced performance). This entry's own condition involves **no site-facing impact of any kind**:

- The live site, if one exists, continues serving every request — front-end, administrative, REST, cron — completely normally throughout this condition, since a WP-CLI process that never launches WordPress cannot disturb an already-running web-facing PHP process in any way.
- No data is read, written, or left in an inconsistent state, since no WordPress code ever executes.
- The only actual consequence is that the specific operation the operator or script intended to perform via WP-CLI does not happen — an operational inconvenience, not a defect with any plausible path to a more severe outcome. No manifestation of this entry's own condition includes any site-level impact, unlike every range-based Critical entry in this catalog, which is precisely why this entry departs from that pattern rather than merely selecting a lower point within it.
- The recovery priority is correspondingly Low: the corrective action (supplying the correct path) is simple, well-understood, and never time-pressured by any ongoing site-level consequence.

---

# 6. Distinction

This entry applies only when verified evidence establishes that WP-CLI's own pre-bootstrap discovery process specifically failed to identify a WordPress installation root — not any condition occurring after WordPress itself has actually started executing.

**This entry is not a bootstrap-sequence condition.** `WP-ERROR-013` presumes `wp-load.php` was actually reached and began executing; this entry's own condition means `wp-load.php` was **never reached at all**. `WP-ERROR-013`'s own Section 8 already names "the WP-CLI bootstrap path, which loads `wp-load.php` in the same manner as a web request" as sharing identical code with a web request's own bootstrap sequence — that statement presumes WP-CLI's own separate discovery step already succeeded and `wp-load.php` was actually invoked, which is precisely the step this entry's own condition never reaches.

**This entry is not `wp-config.php` being missing or invalid.** `WP-ERROR-013` Section 16 already conceptually assigns "the case where `wp-config.php` cannot be located at all" to the not-yet-authored `WP-ERROR-010`, and a case where it is located but invalid to `WP-ERROR-011` — both of which presume `wp-load.php`'s own simpler, fixed-location check (the current directory, or one directory up) has actually been reached and run. This entry's own condition is distinct and occurs *earlier*: WP-CLI performs its own separate, more extensive directory-tree search before it ever attempts anything resembling `wp-load.php`'s own check, and this entry's own condition can occur even when a perfectly valid `wp-config.php` exists somewhere on disk, simply because WP-CLI was never told, and could not determine on its own, where to look for it. Once `WP-ERROR-010`/`011` exist as real entries, they would apply once WP-CLI (or a web request) has already correctly reached a specific installation root and `wp-config.php` itself is the problem within that root — a condition this entry does not claim.

**This entry is not a WP-CLI tool-level installation or version problem.** The `wp` binary itself runs successfully and completes its own discovery logic correctly in this entry's own condition; it simply does not find a WordPress installation where it looked. A failure of the `wp` binary itself to execute at all (a missing or incompatible PHP CLI runtime) is a WP-CLI tool-level requirement, not a WordPress-level condition, and is out of scope for this category entirely per `SF-TAXONOMY-009` Section 2.

**A related but distinct scenario, not this entry's own condition:** WP-CLI's own discovery process *succeeding*, but locating a different WordPress installation than the operator intended (for example, on a server hosting multiple independent WordPress installations, where an ambiguous working directory happens to resolve to the wrong one). This is a genuine operator-error risk worth naming in Prevention, but it is not a *failure* WP-CLI itself detects or reports — from WP-CLI's own perspective, discovery succeeded correctly.

---

# 7. Scope

**Covered:** A verified condition in which WP-CLI's own pre-bootstrap discovery process fails to identify a WordPress installation root from the current working directory or an explicit `--path`, and WP-CLI exits without invoking any WordPress code.

**Excluded:**

- `wp-config.php` missing or invalid within an installation root WP-CLI (or a web request) has already correctly identified (conceptually `WP-ERROR-010`/`011`).
- Any fatal error or other condition occurring once `wp-load.php` has actually been reached and begun executing (`WP-ERROR-013`).
- The `wp` binary itself failing to execute at all due to a WP-CLI tool-level requirement (its own PHP CLI version or installation), not a WordPress-level condition.
- WP-CLI's own discovery process succeeding but resolving to an unintended installation among multiple present on the same server.
- Any condition affecting an already-running, live WordPress site, since this entry's own condition by definition never reaches or disturbs one.

---

# 8. WordPress Components

Listed as components commonly involved, not as a claim that every installation exercises every one of them:

- WP-CLI's own directory-tree discovery process, which searches upward from the current working directory looking for indicators of a WordPress installation, distinct from and more extensive than `wp-load.php`'s own fixed-location check.
- The `--path` global WP-CLI flag, which, when supplied, directs WP-CLI to treat a specific directory as the installation root directly rather than searching for one.
- `.wp-cli.yml` (or `wp-cli.yml`/`wp-cli.local.yml`), WP-CLI's own configuration file, which can declare a `path` value overriding the default search behavior for commands run from within its own directory tree.
- `wp-load.php` and `wp-config.php`, the specific files whose presence WP-CLI's own discovery logic looks for as markers of an installation root.

---

# 9. Typical Symptoms

- WP-CLI printing "Error: This does not seem to be a WordPress installation.\nPass `--path=`path/to/wordpress`` or run `wp core download`." (exact wording may vary slightly by WP-CLI version) and exiting with a non-zero status.
- No corresponding entry in any WordPress-level log (PHP error log, debug log), since WordPress itself never executed.
- A cron job or CI/CD pipeline step invoking `wp` silently producing no effect — no error visible to a human observer — when the pipeline's own script does not check WP-CLI's own exit status.
- The command completing instantly, with no delay resembling a database connection attempt or plugin/theme loading, a useful signal distinguishing this condition from a genuinely slow or hanging bootstrap.

---

# 10. Common Causes

Causes are grouped by category. Inclusion in this list identifies a category as plausible; it does not assert that any specific cause is present without diagnostic confirmation.

- WP-CLI invoked from a working directory that is not the WordPress installation root and not a subdirectory contained within one.
- A cron job or CI/CD pipeline step invoking `wp` without an explicit `--path`, relying on an assumed working directory that does not actually match the process's real working directory at execution time.
- The WordPress installation itself having been moved, renamed, or not yet fully deployed to the location the operator or script expects.
- A missing, misconfigured, or incorrectly-scoped `.wp-cli.yml` `path` override.
- An incomplete or partial deployment missing `wp-load.php` entirely (for example, a deployment step that copies `wp-content/` before the core files, and WP-CLI is invoked against that intermediate state).

---

# 11. Diagnosis

Verify the following, starting from the least invasive observation and narrowing only once the general shape of the failure is established:

1. **Confirm the exact working directory WP-CLI was invoked from, and whether an explicit `--path` was supplied.** This single fact determines where WP-CLI actually searched.
2. **Confirm a genuine WordPress installation actually exists and is accessible** — a directory containing `wp-load.php` and either `wp-config.php` or `wp-config-sample.php` — at the location the operator or script expected.
3. **Where an installation does exist, compare its exact absolute path against the invoked working directory or `--path` value**, accounting for symlinks, mount points, or containerized-filesystem path differences that can make two paths look equivalent to a human but not to WP-CLI's own resolution logic.
4. **Check for a `.wp-cli.yml` in the invoked directory or any parent directory**, since a `path` override there can redirect WP-CLI's own search in a way that is not obvious from the command line alone.
5. **For an automated or scripted invocation, verify the script's own assumed working directory matches its actual runtime working directory** — a common source of drift when a cron job's own shell environment, or a CI/CD pipeline's own working-directory context, differs from what the script's author assumed during local testing.
6. **Confirm the installation is complete**, not a partial or in-progress deployment state, where an incomplete deployment is suspected.

---

# 12. Recovery Procedure

Recovery shall target the specific, verified cause identified in Diagnosis (Section 11).

Permitted recovery categories, depending on the verified cause, include:

- Re-invoking the intended command with the correct `--path` value pointing directly at the installation root.
- Correcting a working directory assumption in an automated script or cron job so it reliably runs from, or explicitly targets, the correct location.
- Adding or correcting a `.wp-cli.yml` `path` declaration where a persistent, repeated correction is preferable to specifying `--path` on every invocation.
- Where the installation itself is genuinely incomplete or was moved unexpectedly, completing or correcting the deployment before retrying — a separate, deployment-level corrective action outside WP-CLI's own configuration.

Recovery shall not involve broadening filesystem permissions or altering unrelated configuration in an attempt to "make WP-CLI find" an installation, since this entry's own condition is never attributable to a permission or access-control constraint — WP-CLI's own discovery process is a straightforward existence check, not a permission-gated operation.

---

# 13. Validation

Recovery is successful when:

- WP-CLI successfully reaches WordPress bootstrap and completes the originally intended command against the correct, intended installation.
- Where the correction was a `--path` value or `.wp-cli.yml` change, a subsequent invocation from the same context (the same cron schedule, the same pipeline step) succeeds without requiring a one-off manual correction.
- For an automated script or pipeline, the corrected working-directory or path assumption is verified to hold across the actual execution environment, not only in a manual, interactive re-test.

---

# 14. Prevention

- Use an explicit `--path` in any automated, non-interactive WP-CLI invocation (cron jobs, CI/CD pipelines, deployment scripts) rather than relying on an assumed working directory, since automated contexts are the most common source of a working-directory mismatch that a human operator would immediately notice interactively.
- Verify that automated pipelines and scripts check WP-CLI's own exit status explicitly, rather than assuming a command succeeded, so this entry's own condition surfaces as a visible failure rather than a silent no-op.
- On a server hosting multiple independent WordPress installations, always use an explicit `--path` rather than relying on directory-tree discovery, to avoid the related-but-distinct risk (Section 6) of WP-CLI's own discovery succeeding against the wrong installation.
- Maintain a `.wp-cli.yml` at a stable, well-known location for any installation WP-CLI is invoked against routinely, reducing reliance on operators remembering the correct `--path` each time.

---

# 15. Security Considerations

- This entry's own condition involves no WordPress code execution and no change to any WordPress-level state, so it carries no direct security implication of its own.
- Do not respond to this condition by broadening filesystem permissions or disabling access controls as a troubleshooting shortcut; doing so addresses no actual cause of this entry's own condition (Section 12) and introduces unrelated risk.
- Where an automated deployment or security-maintenance script (for example, one intended to keep plugins updated) fails silently due to this condition because its own exit-status check is missing, the resulting risk is the *absence* of an expected maintenance action, not a direct consequence of this entry itself — a reason Prevention recommends explicit exit-status checking for any security-relevant automated WP-CLI usage specifically.

---

# 16. Related Errors

The following are cited as they exist in this repository, or as conceptual distinctions where noted.

1. [WP-ERROR-013 — WordPress Bootstrap PHP Fatal Error](WP-ERROR-013-WORDPRESS-BOOTSTRAP-PHP-FATAL-ERROR.md) — exists in this repository; see Section 6 (Distinction) above for why this entry's own condition, occurring before `wp-load.php` is ever reached, falls entirely outside that entry's own scope.
2. WP-ERROR-010 — WordPress Configuration File Missing — conceptual reference; already named by `WP-ERROR-013` Section 16 as a planned entry, no corresponding document currently exists in this repository; no link is provided.
3. WP-ERROR-011 — WordPress Configuration File Invalid — conceptual reference; already named by `WP-ERROR-013` Section 16 as a planned entry, no corresponding document currently exists in this repository; no link is provided.
4. [WP-ERROR-042 — WP-CLI Multisite Site Context Resolution Failure](WP-ERROR-042-WP-CLI-MULTISITE-SITE-CONTEXT-RESOLUTION-FAILURE.md) — exists in this repository; the second and final planned entry in this category.

---

# 17. Notes

This entry documents the general, verified observable condition of WP-CLI's own pre-bootstrap discovery process failing to identify a WordPress installation, distinguishing it precisely from both an already-conceptually-assigned future territory (`WP-ERROR-010`/`011`) and an existing entry (`WP-ERROR-013`) that presumes bootstrap was actually reached. It is the first entry in the CLI category, drafted directly from `SF-TAXONOMY-009`'s own declared scope, and the first entry in this catalog reasoned to Low severity from first principles rather than selecting a lower point within the catalog's usual range-based Critical pattern — a deliberate departure justified by this entry's own condition having no plausible path to any site-facing impact at all, unlike every prior severity exception this catalog has reasoned.

Consistent with the single-responsibility principle in **SF-SPEC-001** Section 4.3, this entry does not claim ownership of `wp-config.php`'s own existence or validity once an installation root is correctly identified, nor of any condition occurring after WordPress itself has begun executing.

This entry's relationship to a `Production Ready` designation is established via `SF-REVIEW-122` (Class A author review) and `SF-REVIEW-123` (Class B independent review) per **SF-SPEC-001** Section 19, **SF-SPEC-005** Section 5.6, and **SF-SPEC-012**.

No Reference Implementation is currently designated by **SF-SPEC-001**; this entry's relationship to that designation, and to any future `WP-SCENARIO-XXX` runtime evidence, is not asserted here and shall not be assumed until such evidence or designation actually exists.
