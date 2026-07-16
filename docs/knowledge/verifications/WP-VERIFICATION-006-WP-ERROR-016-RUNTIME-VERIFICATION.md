# WP-VERIFICATION-006 — WP-ERROR-016 Runtime Verification

Structured per `SF-TEMPLATE-005` and the `WP-VERIFICATION-XXX` series convention. Governed by `SF-SPEC-002`, `SF-SPEC-006`, and `SF-SPEC-011`.

---

# 1. Evidence Record Identity

**Record ID:** `WP-VERIFICATION-006`

**Date:** 2026-07-16

---

# 2. Associated Scenario or Artifact

`WP-ERROR-016` — WordPress Core Files Missing or Corrupted, Version 1.1.

---

# 3. Objective

Determine whether official core checksum verification runs independently of normal WordPress bootstrap, detects missing, altered, and unreadable core files, depends on its documented execution environment, and supports recovery without misclassifying environmental failures as bootstrap failures.

**Expected behavior, per the entry:**

- `wp core verify-checksums` runs on WP-CLI's `before_wp_load` hook and intentionally does not load WordPress.
- A broken WordPress bootstrap therefore does not by itself prevent checksum verification from running.
- Missing, truncated, or altered core files are reported as integrity failures.
- WP-CLI execution, installation location, readable files, correct WordPress version/locale, and retrieval of WordPress.org checksum data remain genuine command dependencies.
- Supplying a verified version and locale can bypass failed automatic detection when `wp-includes/version.php` is damaged.
- Re-downloading the exact official release can restore the core set, after which checksums and the affected WordPress path shall succeed.

---

# 4. Baseline

- Repository: clean `agent/wp-verification-006-research` at `be7777a`; no repository file changed during runtime research.
- WordPress: 7.0.1, newly installed in a disposable directory.
- WP-CLI: 2.12.0 PHAR.
- PHP: 8.2.29 CLI.
- Database: official WordPress SQLite Database Integration 2.2.23, stored in the disposable environment.
- Healthy WordPress control: `wp eval` completed and reported WordPress 7.0.1.
- Healthy checksum controls: automatic version/locale detection and explicit `--version=7.0.1 --locale=en_US` both returned `Success: WordPress installation verifies against checksums.`

Hospital, Thematic, every pre-existing Local site, and every certified repository artifact were excluded from the runtime environment.

---

# 5. Environment

- macOS 26.5.2, Darwin 25.5.0, x86_64.
- Disposable site: `/private/tmp/sf-verification-006/site`.
- WordPress core, WP-CLI, SQLite integration, database, backups, and altered-file fixtures existed only under `/private/tmp`.
- Official WP-CLI package source inside the tested PHAR identifies `Checksum_Core_Command` as `@when before_wp_load`, states that it avoids loading WordPress for security, reads `wp-includes/version.php` for automatic metadata, retrieves published checksums through `WpOrgApi`, and hashes local files with `md5_file()`.
- The WordPress.org checksum service was reached normally for positive controls and was isolated with a loopback refusal proxy for the network-negative control.

Deliberate malicious compromise, storage-device corruption, and an interrupted production deployment were not created. Those are possible causes, not separate execution mechanisms required to verify checksum behavior, and remain source/architecture-classified rather than runtime-verified here.

---

# 6. Execution Procedure

1. Installed WordPress 7.0.1 and the official SQLite integration in a new disposable site; confirmed normal WordPress bootstrap and clean automatic and explicit checksum verification.
2. Removed only `wp-settings.php`, preserving its official copy outside the site. Confirmed normal `wp eval` failed during bootstrap, then ran automatic and explicit checksum verification.
3. Removed `wp-includes/version.php` as well. Compared automatic metadata detection with explicit `--version=7.0.1 --locale=en_US`, then tested invalid version and locale values.
4. Restored both files, made only `wp-includes/version.php` unreadable, ran verification, restored mode `0644`, and re-ran the healthy control.
5. Blocked checksum-service access with `HTTPS_PROXY`/`HTTP_PROXY` directed to refused loopback port 9 and ran explicit verification.
6. Made a disposable copy of the WP-CLI PHAR, truncated that copy, attempted the command, and deleted the damaged copy.
7. Truncated `wp-includes/class-wp.php`, verified the mismatch, and restored its exact official copy.
8. Truncated `wp-settings.php`, verified the mismatch, then executed `wp core download --version=7.0.1 --locale=en_US --force --skip-content` and repeated checksum and WordPress-bootstrap controls.
9. Removed the complete disposable environment after recording evidence and confirmed the research branch remained clean.

---

# 7. Evidence Artifacts

- **Healthy bootstrap:** `wordpress_bootstrap=ok version=7.0.1`.
- **Healthy checksums:** automatic and explicit commands returned `Success: WordPress installation verifies against checksums.`
- **Broken bootstrap:** removing `wp-settings.php` caused PHP `Failed opening required .../wp-settings.php` and exit 255 during `wp eval`.
- **Checksum operation during broken bootstrap:** with the same file absent, both checksum commands ran, reported `Warning: File doesn't exist: wp-settings.php`, and concluded `Error: WordPress installation doesn't verify against checksums.` Exit 1 represented the detected integrity failure, not failure to execute the command.
- **Automatic metadata dependency:** with `wp-includes/version.php` absent, automatic detection stopped with `Error: This does not seem to be a WordPress install.` The explicit command still ran and reported both missing core files.
- **Invalid metadata:** explicit version `0.0` and an invalid locale each returned `Error: Couldn't get checksums from WordPress.org.`
- **Filesystem readability:** mode `000` on `wp-includes/version.php` produced `md5_file(...): Failed to open stream: Permission denied`, a checksum mismatch warning for that file, and exit 1. Restoring mode `0644` restored success.
- **Checksum-service network dependency:** refused proxy access produced `Failed to get url 'https://api.wordpress.org/core/checksums/1.0/?version=7.0.1&locale=en_US': cURL error 7 ... Couldn't connect to server.`
- **WP-CLI execution dependency:** the truncated disposable PHAR stopped with a PHP parse error and exit 255 before any installation or WordPress-bootstrap assessment.
- **Altered core content:** truncating `wp-includes/class-wp.php` produced `Warning: File doesn't verify against checksum: wp-includes/class-wp.php` and exit 1.
- **Recovery:** after truncating `wp-settings.php`, `wp core download --force --skip-content` downloaded WordPress 7.0.1, verified the archive MD5, and succeeded. Checksum verification then succeeded, followed by `post_recovery_bootstrap=ok version=7.0.1`.

---

# 8. Validation

The runtime matrix confirms WP-ERROR-016 Version 1.1's corrected execution model. Checksum verification ran without WordPress bootstrap, identified missing and altered core files, surfaced an unreadable-file cause, required checksum-service access, and could use explicit version/locale values when automatic detection was unavailable. A damaged WP-CLI executable failed before inspecting WordPress. Recovery restored both integrity and normal bootstrap.

**Differences from documentation:**

1. No discrepancy was found against WP-ERROR-016 Version 1.1.
2. The command's exit 1 after detecting a missing or altered file is an integrity-negative result, not evidence that WordPress bootstrap was attempted.
3. The local runtime did not exercise malicious compromise, physical filesystem corruption, or an interrupted production deployment; the record does not elevate those possible causes to runtime-verified status.

**Required repository changes:** None to WP-ERROR-016, SF-TAXONOMY-001, the Filesystem baseline, or any other certified knowledge. This change adds only this verification record, its reviews, and applicable verification-status navigation.

---

# 9. Negative Validation

- The same absent `wp-settings.php` caused normal WordPress bootstrap to fail while checksum verification still executed, isolating bootstrap from the pre-load command.
- Permission denial produced an explicit `Permission denied` filesystem signal, distinct from missing/altered content.
- Refused checksum-service access produced a cURL connection error before local integrity could be concluded, distinct from bootstrap and from a checksum mismatch.
- Invalid version/locale values failed checksum retrieval, not WordPress bootstrap.
- A damaged WP-CLI PHAR failed to parse before the command could inspect the installation.
- The SQLite database remained healthy and was not involved in checksum comparison.
- No existing website, repository file, Hospital installation, or Thematic installation participated.

---

# 10. Cleanup Evidence

- Every changed core file was restored from its preserved official copy or by the official forced core download.
- `wp-includes/version.php` mode was restored to `0644`.
- The damaged WP-CLI copy was deleted; the original disposable PHAR remained intact through the final control.
- Final checksum verification succeeded.
- Final WordPress bootstrap succeeded and reported Version 7.0.1.
- The disposable site, database, downloads, backups, and test fixtures were removed from `/private/tmp`.
- The repository branch remained clean after runtime research.

---

# 11. Repository Validation Evidence

After the complete verification and review artifact set existed, `scripts/validate-repo.sh .` passed, the complete PHPUnit suite passed (146 tests, 338 assertions), every PHP file under `src/` and `tests/` passed `php -l`, Markdown links passed, and `git diff --check` passed.

---

# 12. Classification

**Permanent.** This record fixes the runtime evidence for WP-ERROR-016 Version 1.1's pre-load checksum model, environmental dependency boundaries, and recovery path.

---

# 13. Retention Decision

Retain permanently. Any future execution involving a real malicious compromise, failing storage device, or interrupted deployment shall be recorded separately rather than retroactively broadening this record.

---

# 14. Traceability Map

- `WP-ERROR-016` Version 1.1: direct subject.
- Correction commit `3530fb2`: pre-load execution-model correction completed before this verification record.
- `SF-REVIEW-177`/`178`: correction reviews.
- `SF-REVIEW-179`/`180`: Filesystem consistency review and Knowledge Baseline v4 certification.
- Verification reviews: `SF-REVIEW-181` and `SF-REVIEW-182`.

---

# 15. Engineering Review Status

Reviewed via `SF-REVIEW-181` (Class A) and `SF-REVIEW-182` (Class B). Both approved with no open findings.

---

# 16. Revision History

| Version | Date | Summary | Approval Status |
|---|---|---|---|
| 1.0 | 2026-07-16 | Initial record. Confirms pre-load checksum execution, missing/altered/readability detection, environmental dependency boundaries, exact-release recovery, cleanup, and source-classified limitations. | Reviewed via SF-REVIEW-181/182 |
