# WP-VERIFICATION-005 — WP-ERROR-020 Runtime Verification

Structured per `SF-TEMPLATE-005` and the `WP-VERIFICATION-XXX` series convention. Governed by `SF-SPEC-002`, `SF-SPEC-006`, and `SF-SPEC-011`.

---

# 1. Evidence Record Identity

**Record ID:** `WP-VERIFICATION-005`

**Date:** 2026-07-16

---

# 2. Associated Scenario or Artifact

`WP-ERROR-020` — WordPress Disk Space Exhausted, Version 1.1 when research began and Version 1.2 when verification completed.

---

# 3. Objective

Determine whether genuine destination-volume capacity exhaustion produces the filesystem, Site Health, media-upload, update-staging, diagnostic, recovery, and cleanup behavior documented by WP-ERROR-020.

**Expected behavior, per the entry:**

- A write to a volume with no free bytes fails with an OS/PHP capacity error rather than an access error.
- Site Health reports critically low update space below 20 MB.
- A media upload can surface `The uploaded file could not be moved to wp-content/uploads/<year>/<month>.`.
- Plugin installation can fail to create `wp-content/upgrade`.
- Permission denial can produce the same WordPress-level upload symptom but a different underlying system error.
- Restoring space permits successful retries without residual failed-operation artifacts.
- Inode and quota exhaustion are separately diagnosed and shall not be claimed as runtime-verified without a filesystem that can faithfully enforce them.

---

# 4. Baseline

- Repository: clean `main` at `87e9358` before research; no repository file changed during the initial runtime phase.
- WordPress: 7.0.1, newly installed in a disposable directory.
- PHP: 8.5.7 CLI and built-in HTTP server.
- Database: official WordPress SQLite Database Integration 2.2.23, with its database stored outside the volume under test so database capacity could not confound the result.
- Destination volume: disposable 96 MiB HFS+ sparse image mounted as `/Volumes/SFVERIFY005`; only the test site's `wp-content` was placed there.
- Initial volume state: 19 MiB used, 77 MiB available, 20% capacity; 560 inodes used and approximately 4.3 billion reported free.
- Healthy controls: `wp_upload_dir()` created the dated upload path; a direct 12-byte write succeeded and was removed; an authenticated REST media upload returned HTTP 201.

Hospital, Thematic, and every pre-existing Local site were excluded from the environment.

---

# 5. Environment

- macOS 26.5.2, Darwin 25.5.0, x86_64.
- Native `hdiutil` disposable sparse image; native HFS+ filesystem.
- WordPress core and WP-CLI downloaded only into `/private/tmp`.
- PHP built-in server bound to `127.0.0.1:8075`.
- WordPress core files and SQLite database on the host scratch volume; `wp-content` symlinked to the isolated test volume.
- Debug and server logs written outside the test volume so a full destination volume could not suppress the evidence log itself.

This macOS/HFS+ environment exposed no practical fixed inode ceiling for the 96 MiB volume and no active per-user/project quota. Inode exhaustion and quota exhaustion were therefore not simulated. macOS `write(2)` and `mkdir(2)` documentation independently identifies `ENOSPC` for exhausted blocks/inodes and `EDQUOT` for exhausted block/inode quota, supporting source classification without elevating those mechanisms to runtime-verified status.

---

# 6. Execution Procedure

1. Established the healthy baseline and removed its media fixture.
2. Filled the isolated volume with zero-data files until `dd` returned `No space left on device` and `df` reported 0 available blocks and 100% capacity.
3. Attempted a direct 4096-byte `file_put_contents()` in the dated WordPress upload path.
4. Executed `WP_Site_Health::get_test_available_updates_disk_space()` against the full `WP_CONTENT_DIR` volume.
5. Issued a real authenticated HTTP media upload through the WordPress REST media endpoint.
6. Attempted installation of a disposable local plugin archive, exercising `WP_Upgrader::unpack_package()` and `wp-content/upgrade`.
7. Removed only the two capacity-filler files and confirmed free-space restoration and absence of failed-operation artifacts.
8. As a negative control, temporarily changed only the disposable dated upload directory to mode `0555`, repeated the direct write and REST upload, and restored mode `0755`.
9. Compared the size-limit distinction with permanent `WP-VERIFICATION-003` evidence and current WordPress 7.0.1 source. This exposed a stale live claim in WP-ERROR-020 Version 1.1; runtime verification paused immediately.
10. Completed the controlled correction in commit `961c19f` before concluding this record: WP-ERROR-020 Version 1.2, SF-TAXONOMY-001 Version 1.4, SF-TAXONOMY-007 Version 1.6, and reviews `SF-REVIEW-169`–`174`.
11. With normal capacity restored, repeated media upload and plugin installation successfully, deleted their fixtures, terminated the server, detached the test volume, and removed the complete environment.

---

# 7. Evidence Artifacts

- **Capacity trigger:** `dd: /Volumes/SFVERIFY005/capacity-filler.bin: No space left on device`; after the tail fill, `df -k` reported 0 available blocks and 100% capacity.
- **Direct write:** `file_put_contents(...): Failed to open stream: No space left on device`; return value `false`; target absent.
- **Site Health:** `disk_free_space(WP_CONTENT_DIR)` returned `0`; status `critical`; description `Available disk space is critically low, less than 20 MB available. Proceed with caution, updates may fail.`
- **Media upload:** HTTP 500; code `rest_upload_sideload_error`; message `The uploaded file could not be moved to wp-content/uploads/2026/07.`; no attachment or destination file created.
- **Plugin staging:** `Warning: Could not create directory. "/private/tmp/sf-verification-005/site/wp-content/upgrade"` followed by `Error: No plugins installed.`; no fixture plugin installed.
- **Permission negative control:** direct write failed with `Permission denied`, not `No space left on device`; REST returned the same HTTP 500/code/message as the capacity trigger. This confirms the WordPress-level symptom alone does not determine ownership.
- **Size-limit correction evidence:** WP-VERIFICATION-003 already proves `wp_max_upload_size()`/`upload_size_limit` are advisory display mechanisms and that multisite-only `check_upload_size()` is the genuine WordPress-level enforcement path beyond PHP's multipart request parsing. WordPress 7.0.1 source and the current negative-control observations corroborated that distinction.
- **Recovery controls:** after space restoration, authenticated REST upload returned HTTP 201; the disposable plugin installed successfully; both fixtures were then deleted.
- **Preserved supporting archive:** `/private/tmp/sf-verification-005-research-evidence.tgz` contains the small response and log artifacts retained from the disposable environment. It is supporting local evidence, not a repository dependency.

---

# 8. Validation

Byte-capacity exhaustion, its direct PHP/OS signal, Site Health behavior, WordPress media symptom, plugin update-staging path, permission distinction, and recovery are confirmed against WordPress 7.0.1. Inode exhaustion and real quota enforcement remain source-verified/platform-deferred, not runtime-verified.

**Differences from documentation:**

1. Against WP-ERROR-020 Version 1.1, the byte-capacity behavior itself had no discrepancy.
2. Version 1.1's size-limit distinction incorrectly said `wp_max_upload_size()` enforced rejection. This contradicted permanent WP-VERIFICATION-003 evidence and was corrected before this verification completed.
3. Against corrected WP-ERROR-020 Version 1.2, no remaining discrepancy was found in the mechanisms exercised or source-classified here.

**Required repository changes:** The demonstrated knowledge correction was completed separately in commit `961c19f`. No further taxonomy or knowledge-entry change is required by this completed verification. This commit adds only this record, its reviews, and applicable verification-status navigation.

---

# 9. Negative Validation

- Permission denial and capacity exhaustion produced the same WordPress REST upload symptom but distinct underlying PHP errors: `Permission denied` versus `No space left on device`.
- The full-volume state retained approximately 4.3 billion reported free inodes, demonstrating that the byte trigger did not also exhaust inodes.
- The SQLite database and logs were outside the test volume, excluding database failure and lost-log artifacts as explanations.
- No existing website, database, repository file, Hospital installation, or Thematic installation participated.
- Inode and quota behavior is not represented as locally executed.

---

# 10. Cleanup Evidence

- Both capacity-filler files removed; the volume returned to 19 MiB used, 77 MiB available, 20% capacity.
- Upload directory permission restored to `0755`.
- Failed capacity and permission targets were absent.
- Recovery upload and fixture plugin were deleted after their successful controls.
- Local PHP server terminated.
- `/dev/disk6` detached and `/Volumes/SFVERIFY005` absent.
- WordPress, WP-CLI, downloaded packages, sparse image, and complete disposable site removed from `/private/tmp`.
- Only the small supporting evidence archive named in Section 7 was retained.

---

# 11. Repository Validation Evidence

After the complete verification and review artifact set existed, `scripts/validate-repo.sh .` passed, the complete PHPUnit suite passed (146 tests, 338 assertions), every PHP file under `src/` and `tests/` passed `php -l`, and `git diff --check` passed.

---

# 12. Classification

**Permanent.** This record is the fixed evidence for WP-ERROR-020 Version 1.2's byte-capacity verification and the explicit platform limitations on inode/quota execution.

---

# 13. Retention Decision

Retain permanently. A quota-capable or faithfully inode-limited Linux execution shall be recorded as a new verification rather than rewriting this record.

---

# 14. Traceability Map

- `WP-ERROR-020` Version 1.2: direct subject, corrected before completion.
- `WP-VERIFICATION-003`: permanent source/runtime evidence for the size-limit negative distinction.
- Correction commit `961c19f`: WP-ERROR-020 Version 1.2, SF-TAXONOMY-001 Version 1.4, SF-TAXONOMY-007 Version 1.6, `SF-REVIEW-169`–`174`.
- Verification reviews: `SF-REVIEW-175` and `SF-REVIEW-176`.

---

# 15. Engineering Review Status

Reviewed via `SF-REVIEW-175` (Class A) and `SF-REVIEW-176` (Class B). Both approved with no open findings.

---

# 16. Revision History

| Version | Date | Summary | Approval Status |
|---|---|---|---|
| 1.0 | 2026-07-16 | Initial record. Confirms genuine byte-capacity exhaustion, Site Health, upload, installer staging, diagnostic distinction, recovery, and cleanup; records inode/quota platform limits; documents the correction pause and separate correction commit. | Reviewed via SF-REVIEW-175/176 |
