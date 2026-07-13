# WP-ERROR-019 — WordPress Filesystem Permission Denied

---

# 1. Knowledge Entry

WordPress Filesystem Permission Denied

---

# 2. Metadata

* **Error ID:** `WP-ERROR-019`
* **Title:** WordPress Filesystem Permission Denied
* **Category:** Filesystem
* **Severity:** Critical
* **Recovery Priority:** Immediate
* **Status:** Production Ready
* **Version:** 1.0

---

# 3. Summary

A file or directory WordPress needs to read, write, or execute either exists with entirely correct content, or does not yet exist but has an existing ancestor directory, and in either case the operating system denies PHP or the web server process the specific access the operation requires. The underlying content, where present, is not in question; only the operating system's willingness to grant the requested access is.

---

# 4. Primary Failure Mode

WordPress (through direct PHP filesystem calls, or through its own `WP_Filesystem` abstraction) attempts to read, write, create, delete, or execute a specific file or directory, and the operating system refuses the specific operation attempted. This entry covers two distinct manifestations of the same underlying accessibility question, per **SF-TAXONOMY-001** Section 3:

- **Access denied on an existing object:** the target file or directory is present, and its content (where applicable) matches what is expected, but the requesting process lacks the specific permission (read, write, or execute) the operation requires.
- **Creation blocked on a missing object:** the target file or directory does not yet exist, but WordPress would ordinarily be able to create it (for example, a new dated uploads subdirectory, or the `wp-content/upgrade` staging directory used during a plugin or theme installation) — and creation fails because the requesting process lacks write/execute permission on the nearest existing ancestor directory.

Both manifestations share the same diagnostic and recovery shape: the question is never "does this content need to be restored or recreated correctly" (that is `WP-ERROR-016`'s territory for core files), but "is the operating system, as currently configured, willing to grant WordPress's request." The specific mechanism denying access varies — standard Unix ownership and mode bits, a mandatory access control layer such as SELinux or AppArmor, or a filesystem mounted or remounted read-only — and diagnosis (Section 11) identifies which one actually applies before recovery is attempted.

---

# 5. Severity

This entry is classified **Critical**, though its actual impact ranges depending on which path is affected:

- Where a directory or file bootstrap itself depends on being readable (for example, `wp-config.php`, or the `wp-admin`/`wp-includes` directories) is inaccessible, the impact is a full-site outage.
- Where a narrower, specific path is affected (for example, `wp-content/uploads`, `wp-content/upgrade`, or a single plugin's own subdirectory), the impact is typically narrower — that specific feature (media uploads, plugin/theme installation or updates) fails while ordinary core functionality and unaffected features continue to work normally.
- This entry remains classified at the level of its most severe possible manifestation (a full-site outage from an inaccessible bootstrap-critical path), consistent with the precedent established for the range-based Critical classification used elsewhere in this catalog (for example, `WP-ERROR-004`, `WP-ERROR-005`, and `WP-ERROR-006`).

---

# 6. Distinction

This entry applies only when verified evidence establishes that a specific file or directory's content, where present, is correct, and that the operating system — not PHP's own runtime configuration, not a web server's own content-interpretation logic, and not an authentication step to a remote transport — is denying the specific access an operation requires.

**Internal distinctions this entry specifically requires:**

- **Access denial versus missing/incorrect content:** a file or directory that cannot be read because it does not exist, or because its content does not match what is expected, is not this entry's condition (see `WP-ERROR-016` for core files). This entry presumes any existing content is correct; only access to it, or to the ancestor needed to create it, is in question.
- **Access denial versus capacity exhaustion:** a write that fails because the volume, an applicable quota, or available inodes are exhausted is `WP-ERROR-020`'s condition, not this entry's, even though both can produce a superficially similar "the write failed" symptom. Diagnosis (Section 11) distinguishes the two directly, since a permission-denied error and a capacity-exhaustion error are typically distinguishable by their exact PHP or OS-level error text.
- **Standard OS permission bits versus a mandatory access control layer:** a path can have entirely correct, permissive standard Unix ownership and mode bits while a separate layer — SELinux (for example, a directory labeled `httpd_sys_content_t` where write access requires `httpd_sys_rw_content_t`) or an AppArmor profile — independently denies the same access. Diagnosis shall check both layers rather than concluding standard permission bits are sufficient evidence of the actual access WordPress will receive.
- **Ownership/permission denial versus a read-only mount:** a filesystem mounted read-only — deliberately (for example, an immutable container image layer) or automatically (for example, an OS remounting a filesystem read-only after detecting a disk error) — denies every write regardless of ownership or mode bits, including to the root user. This is a distinct condition from an ownership or mode-bit mismatch and requires a different corrective action (addressing the mount or the underlying disk condition, not `chmod`/`chown`).
- **Genuine OS-level permission denial versus PHP's own `open_basedir` restriction:** PHP's `open_basedir` directive can refuse access to a path entirely outside of, and prior to, any OS-level permission check — including a path reached through a symlink, since PHP resolves symlinks before comparing against `open_basedir`. This produces a distinctly worded PHP-level warning ("... open_basedir restriction in effect ...") rather than a generic OS "Permission denied," but where a hosting environment suppresses detailed error output, the two can be difficult to distinguish without checking the server's own PHP configuration directly. `open_basedir` is a PHP Runtime/Configuration condition, not a Filesystem one, per **SF-TAXONOMY-001** Section 2; this entry's Diagnosis (Section 11) rules it out explicitly rather than assuming every access failure is OS-level.
- **A genuine access-denial condition versus its own downstream symptom:** WordPress's `WP_Filesystem` abstraction tests direct filesystem write capability and, when that test fails, falls back to prompting for FTP or SSH credentials in `wp-admin` before permitting file-modifying operations (plugin/theme installation, updates, the file editor). This credential prompt is a symptom of this entry's own condition — the direct-write capability test failing because of exactly the access-denial causes this entry documents — not a separate failure mode, consistent with `SF-TAXONOMY-001` Section 5's determination that "Direct Filesystem Method Unavailable" does not warrant its own entry.

**Distinct from the following related entries and categories:**

- **`WP-ERROR-016` — WordPress Core Files Missing or Corrupted**: presumes core-file content itself is missing, incomplete, or altered. This entry presumes any existing content is correct; access to it, not its integrity, is the defect. `WP-ERROR-016`'s own Section 6 already documents this same distinction from its own side.
- **`WP-ERROR-020` — WordPress Disk Space Exhausted** (conceptual reference; no corresponding document currently exists in this repository, per `SF-TAXONOMY-001` Section 3): presumes the operating system is willing to grant the requested access, with the failure occurring because no capacity remains to complete the write. This entry presumes capacity is not the issue; access itself is denied regardless of available space.
- **WordPress configuration failures** — **WP-ERROR-010 — WordPress Configuration File Missing**, **WP-ERROR-011 — WordPress Configuration File Invalid**, and **WP-ERROR-012 — WordPress Configuration File PHP Syntax Error** (each a conceptual reference; no corresponding document currently exists in this repository): `wp-config.php` is a site-specific configuration file whose absence or invalid/malformed content is a Bootstrap/Configuration-category condition, not this entry's. Where `wp-config.php` exists with correct content but cannot be read because of a permission constraint, that specific access failure is this entry's condition; the file's own content or existence is not.
- **HTTP or web-server failures**: a web server correctly reading a file (for example, `.htaccess`) but misinterpreting or rejecting its directives is a web-server-configuration condition, not this entry's. This entry owns whether WordPress/PHP can read or write the file itself, not whether the web server correctly acts on its contents once read.
- **Authentication or deployment-tool behavior**: an FTP, SFTP, or SSH server rejecting the credentials WordPress or a deployment tool supplies to a *remote* filesystem is an authentication condition, occurring before any OS-level filesystem permission on the target system is even evaluated. This entry owns the OS-level outcome once a filesystem operation is actually attempted on the target system, not whether a remote transport's own authentication succeeded.
- **PHP Runtime configuration**: PHP's own `open_basedir` restriction, and other PHP ini-level settings, are excluded per the internal distinction above and per `SF-TAXONOMY-001` Section 2's Category Boundary.

---

# 7. Scope

**Covered:** A verified condition in which a file or directory exists with correct content but the operating system denies WordPress the specific read, write, or execute access an operation requires, or in which a required path does not yet exist and cannot be created because of a permission constraint on an existing ancestor directory — regardless of the specific mechanism enforcing the denial (standard Unix ownership/mode bits, a mandatory access control layer such as SELinux or AppArmor, or a read-only mount).

**Excluded:**

- A file or directory that is missing or whose content is incomplete or altered, independent of access (see `WP-ERROR-016`).
- A write that fails because of exhausted volume capacity, an applicable quota, or exhausted inodes, where the operating system would otherwise grant the requested access (see `WP-ERROR-020`, conceptual reference).
- `wp-config.php` being absent, syntactically invalid, or containing incorrect configuration values, independent of whether it can be read.
- A web server correctly reading but misinterpreting or rejecting a file's own directives (for example, `.htaccess` content).
- Authentication rejection by a remote FTP, SFTP, or SSH server, prior to any OS-level filesystem operation on the target system.
- PHP's own `open_basedir` restriction, or any other PHP runtime/configuration-level access restriction.
- The `WP_Filesystem` FTP/SSH credential prompt as an independent condition — it is a symptom of this entry's own condition, documented within Diagnosis (Section 11), not a separately scoped failure.

---

# 8. WordPress Components

Listed as components commonly involved, not as a claim that every incident exercises every one of them identically:

- The `WP_Filesystem` abstraction (`wp-admin/includes/class-wp-filesystem-base.php` and its `direct`, `ftpext`, and `ssh2` implementations), which WordPress uses for file-modifying operations initiated through `wp-admin` (plugin/theme installation and updates, the file editor, core updates).
- `get_filesystem_method()` (`wp-admin/includes/file.php`), which determines which `WP_Filesystem` implementation to use by testing direct-write capability against a temporary file, unless overridden by the `FS_METHOD` constant in `wp-config.php` (valid values: `direct`, `ssh2`, `ftpext`, `ftpsockets`) or the `filesystem_method` filter.
- `request_filesystem_credentials()` (`wp-admin/includes/file.php`), which presents the FTP/SSH credential-entry form in `wp-admin` when `get_filesystem_method()` determines the direct method is unavailable — the symptom documented in Section 6's internal distinctions.
- `wp_is_writable()` and `win_is_writable()` (`wp-includes/functions.php`), WordPress's own writability-check wrapper, used because PHP's native `is_writable()` is documented to behave unreliably against ACL-based permission systems (historically, Windows Server); `wp_is_writable()` delegates to `win_is_writable()`, which verifies writability by actually attempting a file operation rather than solely interpreting OS-reported ACL state.
- `wp_upload_dir()` and `wp_mkdir_p()` (`wp-includes/functions.php`), responsible for resolving and creating the uploads directory structure, and the source of WordPress's own "Unable to create directory wp-content/uploads/&lt;year&gt;/&lt;month&gt;. Is its parent directory writable by the server?" message.
- The `wp-content/upgrade` staging directory, used during plugin and theme installation and updates, and the source of WordPress's own "Installation Failed: Could Not Create Directory." message when it cannot be created or written to.
- Site Health (**Tools → Site Health → Info** tab), WordPress's own built-in diagnostic listing the writable/not-writable status of key directories (`wp-content`, `wp-content/plugins`, `wp-content/themes`, `wp-content/uploads`, and others).
- The web server process's effective user and group (for example, `www-data`, `apache`, `nginx`, or a PHP-FPM pool's configured user), distinct from the file-owning user a deployment or SFTP account typically creates files as — the relationship between the two determines the outcome of standard Unix ownership and mode-bit checks.
- OS-level access-control layers beyond standard Unix permission bits: SELinux contexts (for example, `httpd_sys_content_t` versus the writable `httpd_sys_rw_content_t`), AppArmor profiles, and POSIX ACLs, any of which can deny access independently of otherwise-permissive standard permission bits.
- PHP's own `open_basedir` ini directive, which can independently restrict path access at the PHP runtime level, distinct from any OS-level permission (see Section 6).

---

# 9. Typical Symptoms

- A PHP warning referencing the specific failed operation and path, such as `Warning: fopen(<path>): failed to open stream: Permission denied`, `Warning: mkdir(): Permission denied`, or `Warning: unlink(<path>): Permission denied`, visible in logs or on-screen where `WP_DEBUG`/error display is enabled.
- WordPress's own "Unable to create directory wp-content/uploads/&lt;year&gt;/&lt;month&gt;. Is its parent directory writable by the server?" message, typically encountered during a media upload.
- WordPress's own "Installation Failed: Could Not Create Directory." message, typically encountered during plugin or theme installation or update.
- The FTP/SSH credential-entry form appearing in `wp-admin` when attempting to install, update, or edit a plugin or theme, or perform a core update, where it did not previously appear.
- Site Health (Tools → Site Health → Info tab) reporting one or more key directories as "Not writable."
- A specific feature (media uploads, a specific plugin's own file-writing behavior, plugin/theme installation or update, the built-in file editor) failing while ordinary browsing and unaffected features continue to work normally, where the affected feature depends on a specific, narrower path.
- A full-site outage with a PHP fatal error referencing an inability to read `wp-config.php` or a core file/directory, where the inaccessible path is one bootstrap itself depends on.
- The failure appearing immediately after a migration, a restore from backup, a change in the SFTP/deployment account used to upload files, a containerization or platform change, or a security-hardening pass that altered ownership, permissions, or SELinux/AppArmor policy.
- The same operation succeeding when run as a different OS user (for example, via a script run directly by an administrator) but failing when performed by WordPress itself, indicating the specific web server process's own effective user lacks the access an administrator's own session has.

---

# 10. Common Causes

Causes are grouped by category. Inclusion in this list identifies a category as plausible; it does not assert that any specific cause is present without diagnostic confirmation.

- Files or directories owned by a different user than the one the web server process runs as (for example, files uploaded via an SFTP account distinct from the `www-data`/`apache`/PHP-FPM pool user), with no group-level access bridging the two.
- Mode bits more restrictive than the operation requires (for example, a directory without execute permission for the web server's user or group, preventing WordPress from traversing into it even where read permission is present).
- A migration, restore, or deployment process that copied files without preserving, or without correctly re-establishing, the ownership and permissions the target environment's web server process requires.
- A security-hardening pass that tightened permissions or ownership without accounting for the specific paths WordPress's own operation (uploads, plugin/theme installation and updates, caching) legitimately needs to write to.
- SELinux enforcing a context (for example, `httpd_sys_content_t`) that permits reading but not writing, on a path (typically under `wp-content`) that requires the writable `httpd_sys_rw_content_t` context or an equivalent policy adjustment, on systems where SELinux is enabled and enforcing.
- An AppArmor profile restricting the web server or PHP-FPM process's filesystem access more narrowly than the site's own legitimate needs, on systems where AppArmor is enabled and enforcing.
- A filesystem or volume mounted read-only, deliberately (for example, an immutable container image layer, or a read-only bind mount) or automatically (for example, an operating system remounting a filesystem read-only after detecting a disk-level error).
- A containerized or orchestrated deployment where the container's filesystem is deliberately read-only except for specific declared writable volumes, and `wp-content` (or a subdirectory of it) is not among them.
- PHP's `open_basedir` directive configured more narrowly than the paths WordPress legitimately needs to access, including where a symlink points outside the configured `open_basedir` value.
- A hosting-platform default configuration that provisions accounts with more restrictive permissions than a specific WordPress operation requires, particularly on shared hosting.

---

# 11. Diagnosis

Verify the following:

1. Confirm this is genuinely an access-denial condition — a PHP "Permission denied" warning, WordPress's own "Unable to create directory" or "Could not create directory" message, or Site Health reporting a path as "Not writable" — rather than missing/incorrect content, capacity exhaustion, or a PHP `open_basedir` restriction.
2. Capture the exact error text and the specific path referenced, since the wording (a generic "Permission denied" versus an explicit "open_basedir restriction in effect" versus a capacity-related message) itself indicates which underlying condition is actually present.
3. Where WordPress's own admin interface is reachable, check Site Health (Tools → Site Health → Info tab) as a first, non-invasive check of which specific paths are currently reported writable or not writable.
4. Where WP-CLI is available, invoke `wp_is_writable()` directly against the specific path in question (for example, via `wp eval`) rather than relying on PHP's native `is_writable()` alone, since WordPress's own function accounts for known unreliability in `is_writable()` on ACL-based systems.
5. Inspect standard Unix ownership and mode bits on the specific path and its parent directories (for example, via `ls -la`), and identify the effective user and group the web server or PHP-FPM process actually runs as, to determine whether standard permission bits alone account for the denial.
6. Where standard permission bits appear sufficient but access is still denied, check for a mandatory access control layer: SELinux context (`ls -Z`) or enforcement status, and AppArmor profile status, since either can independently deny access that standard Unix permissions would otherwise allow.
7. Where the environment is containerized or the failure follows a platform-level change, confirm whether the affected filesystem or volume is mounted read-only (for example, via `mount` or the container/orchestration platform's own volume configuration), since this denies all writes regardless of ownership or mode bits.
8. Rule out PHP's own `open_basedir` restriction by checking its configured value (for example, `phpinfo()` or `ini_get('open_basedir')`) against the actual, symlink-resolved path being accessed, particularly where the specific PHP warning text explicitly names `open_basedir` rather than reading as a generic permission denial.
9. Where the FTP/SSH credential prompt has appeared in `wp-admin`, treat it as confirmation that `get_filesystem_method()`'s own direct-write capability test failed, and proceed to diagnose the underlying access-denial cause via the steps above rather than treating the prompt itself as the condition to resolve.
10. Determine the scope of the affected path: a single narrow directory (for example, `wp-content/uploads`, `wp-content/upgrade`, or one plugin's own subdirectory) points toward a targeted fix, while `wp-content` broadly, or a bootstrap-critical path (`wp-config.php`, `wp-admin`, `wp-includes`), points toward a broader ownership, deployment, or platform-level cause.
11. Preserve relevant evidence — exact error text, `ls -la`/`ls -Z` output, mount status, and timestamps — before making any change.
12. Where the engineer performing diagnosis does not control server-level ownership, SELinux/AppArmor policy, or mount configuration, escalate to the hosting provider or system administrator rather than attempting an unverified workaround.

---

# 12. Recovery Procedure

Recovery shall grant the specific, minimum access the affected operation actually requires, scoped to the specific path confirmed by diagnosis, rather than broadening access more than necessary as a shortcut.

Permitted recovery categories, depending on the verified cause, include:

- Correcting ownership so the web server or PHP-FPM process's effective user (or a group it belongs to) matches the affected path's owner or group, where diagnosis confirms an ownership mismatch is the cause.
- Correcting mode bits to the minimum necessary for the operation (commonly 755 for directories and 644 for files, or a stricter posture such as 750/640 where the hosting environment supports and requires it), rather than granting broader access than confirmed necessary.
- Where SELinux is confirmed as the blocking layer, applying the correct context to the specific affected path (for example, `httpd_sys_rw_content_t` for `wp-content`, using `chcon` for an immediate change and `semanage fcontext` to persist it across relabeling), rather than disabling SELinux enforcement system-wide.
- Where AppArmor is confirmed as the blocking layer, adjusting the specific profile governing the web server or PHP-FPM process to permit the confirmed-necessary access, rather than disabling the profile entirely.
- Where a read-only mount is confirmed, addressing its root cause — remounting a deliberately read-only volume as read-write where that was not actually intended, or resolving the underlying disk-level condition that caused an automatic read-only remount — rather than treating a forced read-only remount as something to simply override without understanding why it occurred.
- Where `open_basedir` is confirmed as the actual restriction, correcting its configured value to include the legitimate path, rather than making unnecessary ownership or permission changes in response to a condition that was never an OS-level permission issue.
- Where the FTP/SSH credential prompt is present, resolving the underlying ownership/permission/SELinux/mount cause per the above, rather than supplying credentials as the only corrective action — doing so allows that specific `wp-admin` operation to proceed but leaves the underlying condition unaddressed for other code paths that rely on direct filesystem access.
- Escalating to the hosting provider or system administrator where the engineer performing recovery does not control server-level ownership, SELinux/AppArmor policy, or mount configuration.

Recovery shall not set mode `777` (world-writable) on any file or directory as a shortcut to resolving an access-denial condition; doing so grants write access to every user on a shared system, not only the web server process, and is not a proportionate response to a permission mismatch that a correctly scoped ownership or mode-bit change resolves without that exposure. Recovery shall not disable SELinux or AppArmor enforcement system-wide, or globally widen `open_basedir`, to resolve an access requirement scoped to a specific path.

---

# 13. Validation

Recovery is successful when:

- The previously failing operation completes successfully, confirmed by reproducing the exact action that previously failed.
- Site Health (or `wp_is_writable()`, where WP-CLI is available) reports the previously affected path as writable.
- No equivalent "Permission denied," "Unable to create directory," or "Could not create directory" message recurs in logs or on-screen across repeated, fresh attempts.
- The permissions and ownership actually set are the minimum necessary for the confirmed operation (for example, standard 755/644, not `777`), confirmed via direct inspection (`ls -la`/`ls -Z`) rather than assumed from the fix having worked once.
- Where SELinux, AppArmor, or a read-only mount was the confirmed cause, the fix persists across a filesystem relabel, policy reload, or reboot, not only until the next one.
- The FTP/SSH credential prompt no longer appears for the previously affected operation, confirming the underlying direct-write capability, not merely the specific symptom, has been restored.
- No unrelated file, directory, or configuration was altered as a side effect of the recovery.

---

# 14. Prevention

- Document the ownership and permission model a given hosting environment requires (which user the web server/PHP-FPM process runs as, and how deployment or SFTP-uploaded files should be owned or grouped to match) as part of environment setup, rather than discovering it only after a failure.
- Include a writability verification step (for example, via Site Health) as part of any migration, restore, or deployment procedure, rather than assuming permissions and ownership carried forward correctly.
- Where security hardening tightens permissions, explicitly verify the specific paths WordPress's own legitimate operations need (uploads, plugin/theme installation and updates, caching) remain accessible, rather than hardening uniformly without accounting for them.
- On SELinux- or AppArmor-enforcing systems, define and document the specific policy or context WordPress requires as part of the environment's own configuration management, rather than resolving denials ad hoc after each occurrence.
- Avoid ad hoc, undocumented mode-bit changes (particularly toward `777`) as a habitual troubleshooting shortcut; prefer identifying and correcting the specific ownership or mode-bit mismatch each time.
- Where a container or orchestration platform is used, explicitly declare which paths are writable volumes as part of the deployment definition, rather than discovering an unintended read-only path only when a write fails.

---

# 15. Security Considerations

- Never use mode `777` as a remedy; it grants write access to every user on the system, not only the process that legitimately needs it, and is a common, well-documented WordPress hardening mistake.
- Avoid exposing full server filesystem paths in user-facing error output, since it can reveal internal directory structure to an unauthenticated visitor.
- Treat an unexpected widening of permissions, or an unexplained ownership change, as a potential signal of unauthorized access or tampering, not only as routine misconfiguration, particularly where no legitimate deployment or hardening change explains it.
- Coordinate SELinux, AppArmor, or mount-level changes through a platform-appropriate, auditable process, since such changes can affect every application sharing that server, not only WordPress.
- Where credentials are supplied to WordPress's own `WP_Filesystem` FTP/SSH fallback, ensure they are transmitted and stored consistent with the site's own credential-handling practices, and are not logged in plaintext.

---

# 16. Related Errors

The following are cited as they exist in this repository, or as conceptual distinctions where noted.

1. WP-ERROR-010 — WordPress Configuration File Missing (conceptual reference; no corresponding document currently exists in this repository; no link is provided).
2. WP-ERROR-011 — WordPress Configuration File Invalid (conceptual reference; no corresponding document currently exists in this repository; no link is provided).
3. WP-ERROR-012 — WordPress Configuration File PHP Syntax Error (conceptual reference; no corresponding document currently exists in this repository; no link is provided).
4. [WP-ERROR-016 — WordPress Core Files Missing or Corrupted](WP-ERROR-016-WORDPRESS-CORE-FILES-MISSING-OR-CORRUPTED.md) — exists in this repository; see Section 6 (Distinction) above.
5. WP-ERROR-020 — WordPress Disk Space Exhausted (conceptual reference; planned per `SF-TAXONOMY-001` Section 3, no corresponding document currently exists in this repository; no link is provided).

---

# 17. Notes

This entry documents the specific, verified condition of OS-level filesystem access denial — on both existing and not-yet-created paths — as one of the three entries `SF-TAXONOMY-001` declares for the Filesystem category, alongside `WP-ERROR-016` (integrity) and the planned `WP-ERROR-020` (capacity). It does not restate `WP-ERROR-016`'s own boundary or content. Consistent with the single-responsibility principle in **SF-SPEC-001** Section 4.3, this entry covers both manifestations of access denial (on existing objects, and on ancestor-blocked creation of missing ones) and every enforcement mechanism (standard Unix permissions, SELinux/AppArmor, read-only mounts) as one cohesive failure mode, since all share the same underlying, observable condition — the operating system, not content or capacity, is refusing the requested access; the `WP_Filesystem` FTP/SSH credential prompt is documented within this entry as a symptom, per `SF-TAXONOMY-001` Section 5, rather than as a separate entry.

This entry's governing direction was `SF-TAXONOMY-001` Version 1.1 (post-`SF-REVIEW-034` correction), whose own boundary for this entry — access denial on existing content, or on a missing path blocked by a permission constraint on an ancestor directory — is applied here without narrowing or widening it. The specific technical grounding (PHP warning wording, `wp_is_writable()`/`win_is_writable()`, `FS_METHOD` and `get_filesystem_method()`, `wp_upload_dir()`'s and the plugin/theme installer's own error messages, SELinux `httpd_sys_rw_content_t`, and `open_basedir`'s symlink-resolution behavior) was independently verified against current WordPress and PHP documentation before inclusion, following this catalog's established practice.

This entry underwent the review sequence required by **SF-SPEC-001** Section 19, **SF-SPEC-005** Section 5.6, and **SF-SPEC-012**: an author (Class A) review at `docs/reviews/SF-REVIEW-035-WP-ERROR-019-AUTHOR-REVIEW.md`, which found no defects, followed by an independent (Class B) review at `docs/reviews/SF-REVIEW-036-WP-ERROR-019-INDEPENDENT-REVIEW.md`, which reached outcome **Approved with Minor Revisions**, applied and re-validated the one required revision, and satisfied the Production Ready gate per SF-SPEC-012 Section 12. Its Status was changed to Production Ready on that basis. This document does not itself constitute either review record; see the cited files for full findings, corrections, and gate decisions.

The independent review did not designate this entry as a Reference Implementation. That designation, governed separately by **SF-SPEC-001** Section 22, has not been sought or asserted here.

No Reference Implementation is currently designated by **SF-SPEC-001**; this entry's relationship to that designation, and to any future `WP-SCENARIO-XXX` runtime evidence, is not asserted here and shall not be assumed until such evidence or designation actually exists.
