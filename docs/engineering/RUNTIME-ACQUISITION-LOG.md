# Runtime Acquisition Log

This log records governed attempts to acquire official WordPress Core inputs for disposable runtime verification. An entry records environment acquisition, not target runtime behavior. A failed entry means the associated verification did not start.

## 2026-07-17 — WP-VERIFICATION-009 / WordPress 7.0.1

### Attempt 1 — WordPress.org and official WP-CLI PHAR path

- Target: WordPress 7.0.1, `en_US`.
- Sources: WordPress.org release archive; official WP-CLI builds path; official WordPress.org SQLite Database Integration package.
- Observation: transfers repeatedly ended before complete packages were available. The WP-CLI PHAR was truncated at differing partial sizes and PHP rejected it with `PharException: ... has a broken signature`. No complete WordPress archive or SQLite package passed integrity testing.
- Disposition: rejected before extraction; incomplete staging directory removed.
- Verification state: WP-VERIFICATION-009 did not start; no runtime conclusion was produced.

### Attempt 2 — Installed WP-CLI acquisition path

- Target: WordPress 7.0.1, `en_US`.
- Source: `wp core download --version=7.0.1` using Local's installed WP-CLI 2.12.0.
- Observation: the command began downloading but produced no WordPress files. `wp core version` subsequently confirmed the target directory was not a WordPress installation.
- Disposition: rejected before extraction or installation; incomplete staging directory removed.
- Verification state: WP-VERIFICATION-009 did not start; no runtime conclusion was produced.

### Attempt 3 — Official WordPress GitHub repository

- Target: official tag `7.0.1`.
- Source: `https://github.com/WordPress/WordPress.git`.
- Observation: transport created only an empty `.git` directory. The checkout contained no commit, `git status` reported “No commits yet,” and the requested tag could not be established as an acquired object.
- Disposition: rejected as incomplete; empty checkout removed.
- Verification state: WP-VERIFICATION-009 did not start; no runtime conclusion was produced.

## Current Acquisition State

- A trusted WordPress 7.0.1 `en_US` cache entry was admitted on 2026-07-17 under `SF-SPEC-015` after the successful attempt recorded below.
- No package from the failed attempts is trusted or retained.
- Hospital, Thematic, and all other existing sites remain excluded.
- The WordPress Core source gate in `SF-SPEC-015` has now passed, but WP-VERIFICATION-009 remains paused at its clean research checkpoint pending a separate decision to accept this cache as its input and begin disposable-runtime creation.

## 2026-07-17 — Successful Tier 1 Cache Admission / WordPress 7.0.1

- Target: WordPress 7.0.1, `en_US`.
- Source tier: Tier 1 — WordPress.org Release Archive.
- Archive: `wordpress-7.0.1.zip`, 31,552,576 bytes.
- Source URL: `https://wordpress.org/wordpress-7.0.1.zip`.
- Official checksum source: the SHA-1 link for the WordPress 7.0.1 ZIP on the official WordPress.org Release Archive.
- Official SHA-1: `a8186485dda36ea1a3a998c145efc946ce9f390e`.
- Calculated SHA-1: `a8186485dda36ea1a3a998c145efc946ce9f390e` — exact match.
- Local cache SHA-256: `f171740cf45b1f5a1bf52194ca914787cd9d8ea078599b430eca951b62b2d000`.
- Pre-extraction archive test: passed with Info-ZIP UnZip 6.00; the archive contained the expected `wordpress/` top-level tree and no extraction was performed.
- Provenance record: valid `runtime.json`, schema version 1.0, admitted with `cache_status` set to `trusted` only after the cached archive's SHA-256 matched the verified source copy.
- Cache location: `/Users/randomblink/WordPressRuntimeCache/wordpress-7.0.1` (outside the SquirrelForge repository and disposable runtime locations).
- Admission decision: accepted as a trusted cached Tier 1 archive under `SF-SPEC-015` Sections 5.3, 5.5, and 5.6.
- Acquisition timestamp: `2026-07-17T14:46:26Z`.
- Verification state: WP-VERIFICATION-009 did not start; no archive extraction, WordPress installation, healthy control, target request, or runtime conclusion occurred.
