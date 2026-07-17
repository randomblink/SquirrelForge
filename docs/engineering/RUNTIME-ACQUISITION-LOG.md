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

- No WordPress 7.0.1 cache entry exists.
- No package from these attempts is trusted or retained.
- Hospital, Thematic, and all other existing sites remain excluded.
- WP-VERIFICATION-009 remains paused at its clean research checkpoint until a source passes `SF-SPEC-015`.
