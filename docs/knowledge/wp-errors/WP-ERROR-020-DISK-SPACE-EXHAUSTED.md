# WP-ERROR-020 — WordPress Disk Space Exhausted

---

# 1. Knowledge Entry

WordPress Disk Space Exhausted

---

# 2. Metadata

* **Error ID:** `WP-ERROR-020`
* **Title:** WordPress Disk Space Exhausted
* **Category:** Filesystem
* **Severity:** Critical
* **Recovery Priority:** Immediate
* **Status:** Production Ready
* **Version:** 1.1

---

# 3. Summary

WordPress attempts to write to a specific path — creating a file, extending an existing one, or creating a directory — and the operating system would otherwise grant the requested access, but the write cannot be completed because the underlying storage lacks a resource the write requires: free bytes on the volume, free inodes on the filesystem, or remaining headroom under an applicable quota.

---

# 4. Primary Failure Mode

WordPress (through direct PHP filesystem calls, or through `WP_Filesystem`) attempts a write, and the write fails specifically because capacity, not access, is the limiting factor — per `SF-TAXONOMY-001` Section 3, this entry's condition is distinct from `WP-ERROR-019`'s precisely because the operating system is willing to grant the requested access; it simply cannot satisfy it. This entry covers three related but mechanistically distinct manifestations of capacity exhaustion, unified because they share the same diagnostic and recovery shape (identify what is actually exhausted, then address either genuine undersized capacity or unexpected accumulation) even though the specific resource exhausted differs:

- **Byte-capacity exhaustion:** the volume itself has no remaining free space, the classic "disk full" condition.
- **Inode exhaustion:** the filesystem has consumed all available inodes — the structures that track individual files and directories — even though free bytes remain, typically from an unusually large number of small files rather than large ones.
- **Quota exhaustion:** an applicable per-user, per-directory, or hosting-account quota has been reached, independent of the underlying volume's own actual free byte or inode capacity.

All three commonly surface through the same underlying OS error (`ENOSPC`, "No space left on device") or a distinctly worded quota message ("Disk quota exceeded"), and diagnosis (Section 11) identifies which one actually applies before recovery is attempted, since the corrective action differs materially between them.

---

# 5. Severity

This entry is classified **Critical**, though its actual impact ranges depending on what capacity is exhausted and where:

- Where the volume hosting bootstrap-critical paths, PHP's own session or temporary-file storage, or error logging is exhausted, the impact can extend well beyond a single WordPress feature — PHP itself can begin failing in unexpected ways when it cannot write its own working files, independent of anything WordPress does.
- Where a narrower, specific path is affected (for example, `wp-content/uploads` alone, on a volume otherwise unaffected), the impact is typically narrower — media uploads or plugin/theme installation and updates fail while ordinary browsing and unaffected functionality continue to work normally.
- This entry remains classified at the level of its most severe possible manifestation, consistent with the range-based Critical classification used elsewhere in this catalog (for example, `WP-ERROR-004`, `WP-ERROR-005`, `WP-ERROR-006`, and `WP-ERROR-019`).

---

# 6. Distinction

This entry applies only when verified evidence establishes that the operating system would otherwise grant the requested access, and that the write specifically fails because of exhausted byte capacity, exhausted inodes, or an exhausted quota — not because access itself is denied, not because the target content is missing or incorrect, and not because a configured size limit rejected the request before any write was attempted.

**Internal distinctions this entry specifically requires:**

- **Byte capacity versus inodes versus quota:** all three are covered by this entry as manifestations of the same underlying question (is there room to complete this write), but they are not interchangeable for diagnosis or recovery. A volume can show ample free bytes (`df -h`) while its inodes are exhausted (`df -i`), typically from an unusually large number of small files; a volume or account can be well within its own physical capacity while a separately enforced quota independently blocks further writes. Diagnosis (Section 11) checks all three rather than concluding capacity is sufficient from a single check.
- **Capacity exhaustion versus a configured upload-size limit:** PHP's `upload_max_filesize` and `post_max_size` directives, and WordPress's own `wp_max_upload_size()` filter, reject an upload based on its declared size *before any write to the filesystem is attempted at all* — the rejection happens regardless of how much actual capacity is available. This is a PHP Runtime/Configuration condition, not this entry's, per `SF-TAXONOMY-001` Section 4; the two are easy to confuse from a symptom alone (both present as "the upload failed"), but a genuine capacity exhaustion always involves an attempted, failed write, while a size-limit rejection never reaches the filesystem at all.
- **Genuine physical exhaustion versus a hosting-imposed quota:** a quota can be reached with substantial physical capacity still available on the underlying volume, and conversely a shared volume can be physically full while a specific account's own quota still shows headroom (because other tenants consumed the shared capacity). Diagnosis shall check both independently rather than assuming one implies the other.

**Distinct from the following related entries:**

- **`WP-ERROR-016` — WordPress Core Files Missing or Corrupted**: presumes content is missing or altered, independent of capacity. This entry presumes the target content, where it already exists, is correct; only remaining capacity to complete a write is in question.
- **`WP-ERROR-019` — WordPress Filesystem Permission Denied**: presumes the operating system denies the requested access regardless of available capacity. This entry presumes the opposite — access would be granted — with the write failing solely because capacity is insufficient. `WP-ERROR-019` Section 6 already documents this same distinction from its own side, and both entries can produce an identical-looking WordPress-level symptom (for example, "The uploaded file could not be moved to wp-content/uploads/...," or the browser-interface message "Installation failed: Could not create directory."); Section 11 below identifies the specific underlying PHP/OS error text that distinguishes the two.
- **Database storage** (see `WP-ERROR-006 — WordPress Database Table Corruption`, Database category): where a database server's own data files share the same physical volume as WordPress's files, exhaustion of that shared volume can be the same underlying root cause behind both an entry in this category and database-engine-reported corruption — but the two remain distinct, separately owned conditions. This entry covers WordPress's own file-level write failing at the filesystem layer; where the same root cause instead manifests as the database server reporting a damaged or inconsistent table (a condition `WP-ERROR-006`'s own Common Causes section already names — "the server running out of disk space in the middle of a write, leaving a table's storage structure partially written"), that specific, verified condition belongs to `WP-ERROR-006`, not this entry, even when the same disk-exhaustion event is the shared root cause of both.
- **PHP/WordPress upload-size limits**: see the internal distinction above. Excluded entirely from this entry's scope, per `SF-TAXONOMY-001` Section 4 — see [WP-ERROR-036](WP-ERROR-036-UPLOAD-SIZE-LIMIT-EXCEEDED.md).

---

# 7. Scope

**Covered:** A verified condition in which the operating system would otherwise grant WordPress's requested filesystem access, but a specific write cannot be completed because the underlying volume lacks free byte capacity, the filesystem lacks free inodes, or an applicable quota has been reached — regardless of which of the three is the specific limiting resource.

**Excluded:**

- Access denial regardless of available capacity (see `WP-ERROR-019`).
- Missing or incorrect content, independent of capacity (see `WP-ERROR-016`).
- A PHP- or WordPress-configuration-imposed upload-size limit rejecting a request before any write to the filesystem is attempted.
- Database-engine-level storage exhaustion or the resulting table corruption, even where the same physical volume and the same root cause (disk exhaustion) are involved (see `WP-ERROR-006`, Database category).
- Any write that completes successfully, regardless of how close to any capacity limit it came.

---

# 8. WordPress Components

Listed as components commonly involved, not as a claim that every incident exercises every one of them identically:

- `WP_Site_Health::get_test_available_updates_disk_space()` (`wp-admin/includes/class-wp-site-health.php`), which checks available disk space via PHP's `disk_free_space()` against `WP_CONTENT_DIR` before a core, plugin, or theme update, and surfaces a "critically low" warning below a 20 MB threshold. This check has documented limitations: some hosting environments disable the `disk_free_space()` PHP function entirely, in which case WordPress reports it "could not determine available disk space for updates" rather than a genuine capacity reading; the check has also historically depended on the `wp-content/upgrade/` directory already existing.
- `wp_handle_upload()` and the underlying `move_uploaded_file()` call (`wp-admin/includes/file.php`), whose failure — for either a capacity or an access reason — produces WordPress's own "The uploaded file could not be moved to wp-content/uploads/&lt;year&gt;/&lt;month&gt;" message.
- The `wp-content/upgrade` staging directory, used during plugin and theme installation and updates; its exhaustion (like its inaccessibility under `WP-ERROR-019`) can produce the browser-interface message "Installation failed: Could not create directory." WordPress's underlying filesystem error is "Could not create directory." and interfaces may present it differently.
- PHP's own `upload_tmp_dir`, the location an uploaded file is first written to by PHP itself before `move_uploaded_file()` relocates it — a distinct location that may reside on a different volume or filesystem than `wp-content`, with independent capacity.
- Disk-backed caching (a page-cache or object-cache plugin persisting cache entries to the filesystem, as distinct from WordPress core's own database-backed transient storage), a common, gradually accumulating consumer of capacity.
- Backup plugins and export/data-portability features (for example, the personal-data export tool) that write archive files to the filesystem, another common accumulating consumer.
- Standard OS/filesystem diagnostic tools: `df -h` (byte-capacity usage), `df -i` (inode usage), `du` (identifying specific large consumers), and `quota`/`repquota` on systems where filesystem or hosting-account quotas are enforced.

---

# 9. Typical Symptoms

- A PHP warning explicitly referencing capacity, such as `fwrite(): Write of <N> bytes failed with errno=28 No space left on device`, or an equivalent `file_put_contents()`/`fopen()` failure naming errno 28, distinct in wording from a generic "Permission denied."
- A distinctly worded "Disk quota exceeded" message, where an applicable filesystem or hosting-account quota, rather than the underlying volume's own physical capacity, is the actual limiting factor.
- WordPress's own "The uploaded file could not be moved to wp-content/uploads/&lt;year&gt;/&lt;month&gt;" message — a symptom this entry shares with `WP-ERROR-019`; Section 11 identifies the specific underlying error text that distinguishes which entry's condition actually applies.
- The browser-interface message "Installation failed: Could not create directory." during plugin or theme installation or update — also shared with `WP-ERROR-019`; WP-CLI presents the underlying error and failed path without the browser-interface prefix.
- WordPress's own Site Health "Available disk space is critically low, less than 20 MB available. Proceed with caution, updates may fail." message, or "Could not determine available disk space for updates" where the hosting environment has disabled `disk_free_space()`.
- `df -h` on the relevant volume reporting at or near 100% byte usage.
- `df -i` on the relevant volume reporting at or near 100% inode usage, even where `df -h` reports available byte capacity.
- A hosting control panel, or `quota`/`repquota` output, reporting the account at or over its configured quota, while the underlying shared volume itself has available capacity.
- The failure appearing gradually, correlating with the steady growth of media uploads, logs, caches, or backups, rather than appearing suddenly.
- A small write succeeding while a larger one fails, consistent with near-exhaustion rather than complete exhaustion.

---

# 10. Common Causes

Causes are grouped by category. Inclusion in this list identifies a category as plausible; it does not assert that any specific cause is present without diagnostic confirmation.

- Genuine, legitimate growth of the media library (`wp-content/uploads`) over time, on storage capacity that was never resized to match actual, sustained growth.
- Unrotated or excessively verbose log files (PHP's own error log, the web server's own logs, or a plugin's debug logging left enabled in production) consuming capacity gradually and continuously.
- Accumulated disk-backed cache entries from a caching plugin lacking an effective expiration or cleanup policy.
- Backup archives retained indefinitely on the same volume as the WordPress installation, rather than moved to separate or remote storage after creation.
- Temporary extraction artifacts left behind in `wp-content/upgrade` by a previous failed or interrupted core, plugin, or theme update, never cleaned up afterward.
- A very large number of small files — for example, an oversized or unexpired disk-backed cache, accumulated PHP session files, or an unprocessed mail/spam queue on a shared hosting environment — exhausting available inodes well before byte capacity is reached.
- A hosting-imposed disk quota, common on shared or managed hosting, reached independently of the underlying physical volume's own actual free capacity.
- A shared volume also hosting the database server's own data files, where database growth independent of WordPress's own file writes consumes capacity WordPress's own file operations then compete for.
- A sudden, anomalous spike in write activity — for example, a compromised site being used to store or distribute unrelated files, or a runaway process (a misconfigured logging setting, an infinite-loop bug) writing far more than intended.

---

# 11. Diagnosis

Verify the following:

1. Confirm this is genuinely a capacity condition — a PHP warning explicitly referencing errno 28/"No space left on device," a distinctly worded quota-exceeded message, or WordPress's own Site Health disk-space warning — rather than an access-denial condition (`WP-ERROR-019`) or missing/incorrect content (`WP-ERROR-016`), since "The uploaded file could not be moved" and "Installation failed: Could not create directory." can be produced by either this entry's condition or `WP-ERROR-019`'s, and the two are distinguished by the specific underlying error text, not by the WordPress-level message alone.
2. Capture the exact PHP or OS-level error text and the specific path and volume referenced, since the precise wording (an explicit errno 28, versus a distinct quota message, versus a generic access denial) indicates which underlying mechanism is actually responsible.
3. Check `df -h` on the volume hosting the affected path as the least invasive first check, to determine whether byte-level capacity is genuinely exhausted.
4. Where `df -h` shows available byte capacity despite the failure, check `df -i` on the same volume to rule out inode exhaustion, since both conditions can surface through the identical `ENOSPC` error.
5. Where both byte and inode capacity appear available at the volume level, check for an applicable filesystem or hosting-account quota (for example, via `quota`/`repquota` where available, or a hosting control panel's own usage reporting), since a quota can restrict an account well below the underlying volume's own actual free capacity.
6. Where WordPress's own Site Health reports it "could not determine available disk space for updates," confirm whether the hosting environment has disabled the `disk_free_space()` PHP function, rather than assuming the absence of a reading itself indicates critically low space.
7. Identify what is actually consuming capacity, using `du` (or an equivalent tool) against the affected volume, distinguishing genuine, expected growth (media uploads) from unexpected accumulation (unrotated logs, retained backups, abandoned upgrade artifacts, an unbounded cache).
8. Where a temporary-upload or cache location, rather than the final destination path, is suspected as the actual point of exhaustion, confirm which specific filesystem or volume that location resides on (for example, PHP's own `upload_tmp_dir`), since it may be a different volume than `wp-content` with independently exhausted capacity.
9. Where the same volume also hosts the database server's own data files, determine whether database growth is contributing materially to the exhaustion, since remediation may need to be coordinated with whoever is responsible for the database rather than addressed through WordPress's own files alone.
10. Determine whether the exhaustion developed suddenly or gradually, since a sudden spike suggests an anomalous event (a compromise, a runaway process) while a gradual trend suggests capacity planning was insufficient for otherwise-legitimate growth.
11. Preserve relevant evidence — exact error text, `df -h`/`df -i`/quota output, and a snapshot of the largest space consumers — before making any change, particularly before deleting anything.
12. Where the engineer performing diagnosis does not control the underlying volume, quota configuration, or hosting environment, escalate to the hosting provider or system administrator rather than attempting an unverified workaround.

---

# 12. Recovery Procedure

Recovery shall identify what is actually consuming or limiting capacity before acting, rather than deleting content indiscriminately to create temporary headroom.

Permitted recovery categories, depending on the verified cause, include:

- Where genuine, legitimate growth (media library, expected data volume) has exhausted a volume sized too small for actual needs, expanding the underlying volume's capacity, rather than relying only on deletion to create temporary headroom that will be exhausted again by the same ongoing growth.
- Where unexpected accumulation (unrotated logs, retained backups, abandoned upgrade artifacts, an unbounded cache) is confirmed as the cause, removing or relocating the specific accumulated content, and correcting the underlying process that failed to bound its own growth (log rotation, backup retention policy, cache expiration), rather than performing only a one-time cleanup that the same unaddressed process will refill.
- Where inode exhaustion from a large number of small files is confirmed, removing or consolidating the specific accumulated files (for example, expired session files, an oversized disk-backed cache, or an unprocessed mail queue), rather than assuming that freeing byte capacity alone resolves an inode-exhaustion condition.
- Where a hosting-imposed quota, rather than genuine physical capacity, is confirmed as the limiting factor, requesting a quota increase from the hosting provider, or reducing usage to remain within the existing quota, as appropriate to the confirmed circumstances.
- Where a shared volume also hosts the database server's own data files and database growth is contributing to the exhaustion, coordinating remediation with whoever is responsible for the database, since this entry's own recovery addresses WordPress's file-level writes, not database storage management.
- Escalating to the hosting provider or system administrator where the engineer performing recovery does not control the underlying volume or quota configuration.

Recovery shall not treat repeatedly deleting arbitrary content as a substitute for identifying the actual cause of unexpected accumulation; doing so risks losing legitimate data (a needed backup, an in-progress export) and leaves the underlying growth process unaddressed, allowing the same condition to recur.

---

# 13. Validation

Recovery is successful when:

- The previously failing write completes successfully, confirmed by reproducing the exact action that previously failed.
- `df -h` and `df -i` on the affected volume report sufficient headroom under both byte and inode capacity, not merely enough to complete the one previously failing operation.
- Where a quota was the cause, current usage is confirmed within the applicable quota with reasonable headroom, not immediately at the new limit.
- No equivalent "No space left on device," "Disk quota exceeded," or WordPress capacity-related message recurs across repeated, fresh operations.
- Where unexpected accumulation was the cause, the underlying process (log rotation, backup retention, cache expiration) has been verifiably corrected, not merely the existing accumulation cleared once.
- No legitimate data was lost as a side effect of the recovery.

---

# 14. Prevention

- Monitor volume-level byte and inode usage proactively (`df -h`/`df -i` or an equivalent monitoring integration), alerting before exhaustion occurs rather than after.
- Configure log rotation, cache expiration, and backup retention policies explicitly, rather than allowing any of them to grow unbounded on the same volume as the WordPress installation.
- Store backups on separate or remote storage rather than retaining them indefinitely on the same volume being backed up.
- Size storage capacity based on realistic projected growth (media library, cache, logs), rather than only current usage at the time of provisioning.
- Where a hosting-imposed quota applies, monitor usage against that specific quota, not only against the underlying volume's own physical capacity, since the two can differ significantly.
- Periodically review what is consuming capacity (via `du` or equivalent) as a routine maintenance task, rather than only in response to a failure.
- Where a shared volume also hosts the database server's own data, monitor and plan capacity for both together, since either can exhaust the shared resource.

---

# 15. Security Considerations

- Treat a sudden, unexplained spike in disk usage as a potential signal of compromise (for example, a site being used to store or distribute unauthorized content) rather than assuming it is always routine growth, particularly where no legitimate cause explains it.
- Avoid exposing internal volume paths, quota values, or capacity details in user-facing error output.
- Do not indiscriminately delete content to free space without first confirming it is not needed as evidence, where a security incident is suspected as the cause of unexpected accumulation.
- Coordinate volume or quota changes through a platform-appropriate process, since such changes can affect other applications sharing the same server or hosting account.

---

# 16. Related Errors

The following are cited as they exist in this repository, or as conceptual distinctions where noted.

1. [WP-ERROR-006 — WordPress Database Table Corruption](WP-ERROR-006-DATABASE-TABLE-CORRUPTION.md) — exists in this repository (Database category); see Section 6 (Distinction) above.
2. [WP-ERROR-016 — WordPress Core Files Missing or Corrupted](WP-ERROR-016-WORDPRESS-CORE-FILES-MISSING-OR-CORRUPTED.md) — exists in this repository; see Section 6 (Distinction) above.
3. [WP-ERROR-019 — WordPress Filesystem Permission Denied](WP-ERROR-019-FILESYSTEM-PERMISSION-DENIED.md) — exists in this repository; see Section 6 (Distinction) above.

---

# 17. Notes

This entry documents the third and final entry `SF-TAXONOMY-001` declares for the Filesystem category, alongside `WP-ERROR-016` (integrity) and `WP-ERROR-019` (accessibility). With this entry's creation, the Filesystem category's planned baseline is complete. Consistent with the single-responsibility principle in **SF-SPEC-001** Section 4.3, this entry covers byte-capacity, inode, and quota exhaustion as one cohesive failure mode, since all three share the same underlying, observable condition — the operating system would grant the requested access but cannot satisfy it — while explicitly excluding PHP/WordPress configuration-imposed upload-size limits, which reject a request before any filesystem write is attempted at all, and database-engine-level storage exhaustion, which is `WP-ERROR-006`'s territory even when a shared physical volume is the same underlying root cause.

This entry's governing direction was `SF-TAXONOMY-001` Version 1.1, whose own boundary for this entry — byte capacity, or quota/inode exhaustion, with PHP upload-size limits explicitly excluded — is applied here without narrowing or widening it. The specific technical grounding (`ENOSPC`/errno 28 and its exact PHP warning wording, `df -h`/`df -i`, filesystem quota behavior and its `EDQUOT` distinction from `ENOSPC`, `WP_Site_Health::get_test_available_updates_disk_space()` and its documented limitations, and WordPress's own "uploaded file could not be moved" and "Could Not Create Directory" messages) was independently verified against current WordPress and OS documentation before inclusion, following this catalog's established practice.

This entry underwent the review sequence required by **SF-SPEC-001** Section 19, **SF-SPEC-005** Section 5.6, and **SF-SPEC-012**: an author (Class A) review at `docs/reviews/SF-REVIEW-037-WP-ERROR-020-AUTHOR-REVIEW.md`, which found no defects, followed by an independent (Class B) review at `docs/reviews/SF-REVIEW-038-WP-ERROR-020-INDEPENDENT-REVIEW.md`, which reached outcome **Approved with Minor Revisions**, identified two Minor findings concerning stale references in `WP-ERROR-019` and `SF-TAXONOMY-001` (corrected separately, following this entry's promotion), and satisfied the Production Ready gate per SF-SPEC-012 Section 12. Its Status was changed to Production Ready on that basis. This document does not itself constitute either review record; see the cited files for full findings, corrections, and gate decisions.

The independent review did not designate this entry as a Reference Implementation. That designation, governed separately by **SF-SPEC-001** Section 22, has not been sought or asserted here.

No Reference Implementation is currently designated by **SF-SPEC-001**; this entry's relationship to that designation, and to any future `WP-SCENARIO-XXX` runtime evidence, is not asserted here and shall not be assumed until such evidence or designation actually exists.

**Version 1.1 (2026-07-16):** quotation-fidelity correction through **SF-SPEC-013** Section 5.6, prompted by `WP-VERIFICATION-004`'s WordPress 7.0.1 installer evidence. The shared installer symptom is now identified as the browser-interface composition "Installation failed: Could not create directory." and distinguished from WP-CLI's presentation of the underlying error. Capacity ownership, diagnosis, and recovery are unchanged; this record did not runtime-trigger capacity exhaustion. Reviewed via `SF-REVIEW-161`/`162`; Filesystem re-certified via `SF-REVIEW-163`/`164`.
