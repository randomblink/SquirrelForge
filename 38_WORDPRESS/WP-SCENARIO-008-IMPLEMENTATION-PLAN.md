Status: Planning

---
# WP-SCENARIO-008 — Implementation Design (Database Schema Lifecycle Engineering)

This document converts the approved `38_WORDPRESS/WP-SCENARIO-008-PLAN.md` into a precise, bounded implementation design. It does not execute any part of the scenario. No fixture, test, harness, schema, or database state has been created as a result of writing this document. No production SquirrelForge code was changed. No readiness conclusion, count, or existing scenario's evidence was altered.

No established repository naming convention for implementation-design documents was found (searched for `*IMPLEMENTATION-PLAN*` and `*IMPLEMENTATION-DESIGN*` across the repository; none exist). This file is therefore named per the fallback given in the task: `38_WORDPRESS/WP-SCENARIO-008-IMPLEMENTATION-PLAN.md`.

**Preserving the scenario's actual distinct contribution:** per `WP-SCENARIO-008-PLAN.md` Section 4, this scenario's evidence value is a *real, versioned, in-place structural upgrade against an already-existing, already-populated table*, gated by explicit schema-version detection — not initial schema creation, data-fidelity checking, idempotency, or cleanup in the abstract, all of which WP-SCENARIO-006 already demonstrated for a one-time options-to-table migration. This design keeps that distinction load-bearing throughout: every section below treats "Version 1 already installed and populated, then upgraded to Version 2" as the central event, not "a table gets created."

---

## 1. Scenario Boundary

**In scope:**

- A deterministic fixture plugin (`squirrelforge-schema-lifecycle-fixture`, matching this portfolio's established `squirrelforge-<capability>-fixture` naming convention used by WP-SCENARIO-003, 004, 005, and 007).
- An initial schema version (Version 1) for one custom table.
- An existing, populated instance of that table (three deterministic fixture rows inserted before any upgrade).
- An explicit stored schema-version value, distinct from any prior scenario's version option.
- A version-aware migration gate that only upgrades when the stored version is behind the target version.
- One structural upgrade (Version 1 → Version 2) applied via `dbDelta()` against the already-populated table.
- Data-preservation checks comparing pre- and post-upgrade row state.
- Idempotence checks proving a second natural evaluation of the gate changes nothing.
- Runtime-error capture at every phase.
- Cleanup of all scenario-owned runtime/database state.
- Documentation and final commit verification, following this portfolio's established pattern.

**Out of scope:**

- Any change to production SquirrelForge behavior (`src/`, `tests/`, or any file outside `38_WORDPRESS`).
- General migration-framework redesign (this scenario proves one bounded upgrade path, not a reusable migration engine).
- Multiple sequential historical migrations (only Version 1 → Version 2 is in scope; a hypothetical Version 3 is not).
- A rollback engine (WP-SCENARIO-006 already demonstrated rollback for its own request shape; this scenario does not repeat or extend that claim).
- Destructive column removal (Version 2 only adds columns/indexes; it never drops a Version 1 column).
- Multisite.
- WooCommerce.
- REST APIs (WP-SCENARIO-007 already covers REST evidence; this scenario's fixture exposes no route).
- Performance benchmarking (WP-SCENARIO-005 already covers that claim; this scenario measures correctness, not speed).
- Repository reorganization of any kind.

---

## 2. Exact Schema Transition

**Table name:** `{$wpdb->prefix}sfschema_items` — the `sfschema_` prefix is scenario-specific and distinct from every prior fixture's prefix (`sfrest_` for WP-SCENARIO-007, `sfmig_` for WP-SCENARIO-006, `sfperf_` for WP-SCENARIO-005, `sfctv_` for WP-SCENARIO-009), consistent with this portfolio's established one-prefix-per-scenario convention. `$wpdb->prefix` is resolved at runtime, matching WP-SCENARIO-006's precedent and `38_WORDPRESS/KNOWLEDGE/DATABASE.md`'s canonical `dbDelta()` example.

**Stored schema-version option name:** `sfschema_db_version` — an integer option, distinct from WP-SCENARIO-006's `sfmig_*` option family.

**Version identifiers:** plain integers, `1` and `2`. Integers are chosen over a semver string because the only comparison this scenario needs is "is the stored value behind the target," which an integer `<` comparison expresses unambiguously; no third-party compatibility requirement calls for semver here.

**Target version constant:** `SFSCHEMA_TARGET_VERSION = 2`, defined in the fixture's main file.

### Version 1 (frozen)

```sql
CREATE TABLE {$wpdb->prefix}sfschema_items (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    item_key   VARCHAR(191)   NOT NULL,
    item_value TEXT           NOT NULL,
    created_at DATETIME       NOT NULL DEFAULT '1970-01-01 00:00:00',
    PRIMARY KEY  (id),
    UNIQUE KEY item_key (item_key)
) {$wpdb->get_charset_collate()};
```

Frozen decisions and justification:

- `id BIGINT UNSIGNED ... AUTO_INCREMENT`, `PRIMARY KEY (id)` — matches WordPress's own core table convention (e.g. `wp_posts.ID`) for an auto-incrementing surrogate key; `BIGINT UNSIGNED` rather than `mediumint` (used in `DATABASE.md`'s toy example) because it is the more defensible general-purpose choice and costs nothing at this row count.
- `item_key VARCHAR(191) NOT NULL` — `191` matches WordPress core's own indexed-`VARCHAR` convention (e.g. `wp_options.option_name`), chosen specifically because it is the longest `VARCHAR` length that stays under InnoDB's 767-byte index-prefix limit when the table uses `utf8mb4` (4 bytes/char × 191 = 764 bytes) — the same reason WordPress core itself uses 191, not 255, for indexed text columns under `utf8mb4`.
- `item_value TEXT NOT NULL` — a required text field per the plan; `TEXT` (not `VARCHAR`) because the fixture data (Section 3) deliberately includes a long value that a bounded `VARCHAR` might truncate.
- `created_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00'` — `DATETIME`, not `TIMESTAMP`, to avoid `TIMESTAMP`'s automatic timezone conversion and auto-update side effects, which would work against this scenario's requirement for frozen, non-current values. The default is `1970-01-01 00:00:00`, not the classic WordPress-Codex zero-date default (`0000-00-00 00:00:00`), because MySQL's default strict SQL mode (in effect on the Hospital installation's MySQL 8.4.0, per WP-SCENARIO-006's recorded environment) rejects zero dates; `1970-01-01 00:00:00` is a valid, deterministic placeholder that will never collide with the fixture's real frozen timestamps (Section 3), all of which are dated 2020 or later.
- `UNIQUE KEY item_key (item_key)` — justified because `item_key` is designed as a stable, human-readable identifier per fixture row (analogous to `wp_options.option_name`), and uniqueness is exactly the property that makes "no duplicate rows" (a required data-preservation check, Section 9) independently verifiable at the schema level, not only by manual inspection.
- Charset/collation: `{$wpdb->get_charset_collate()}`, matching `DATABASE.md`'s canonical example and WP-SCENARIO-006's precedent — never a hardcoded charset string.

### Version 2 (frozen)

Retains every Version 1 column and key unchanged, and adds `status` and `updated_at` plus one new index. `dbDelta()` takes a complete target schema, not an incremental `ALTER TABLE` statement, so the additions below are expressed as the full Version 2 target definition `dbDelta()` will be given (Section 5 details exactly which additive statements `dbDelta()` itself will emit from this):

```sql
CREATE TABLE {$wpdb->prefix}sfschema_items (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    item_key   VARCHAR(191)   NOT NULL,
    item_value TEXT           NOT NULL,
    created_at DATETIME       NOT NULL DEFAULT '1970-01-01 00:00:00',
    status     VARCHAR(20)    NOT NULL DEFAULT 'active',
    updated_at DATETIME       NULL,
    PRIMARY KEY  (id),
    UNIQUE KEY item_key (item_key),
    KEY status_idx (status)
) {$wpdb->get_charset_collate()};
```

Frozen decisions and justification:

- `status VARCHAR(20) NOT NULL DEFAULT 'active'` — a short, bounded string is sufficient for a small closed set of status values; `20` characters comfortably fits `'active'` and any realistic status word this fixture will ever use, with headroom, without being large enough to invite unbounded input. The frozen default is the literal string `'active'`.
- `updated_at DATETIME NULL` — nullable, not deterministically initialized to a fabricated timestamp. Justification: the three Version 1 rows (Section 3) were never actually "updated" before the schema itself changed to have an `updated_at` concept; inventing a synthetic timestamp for them would misrepresent history that does not exist. `NULL` correctly and honestly represents "no update has occurred since this field was introduced," and is also the value MySQL itself assigns to existing rows for a newly added nullable column with no explicit `DEFAULT`, which makes "existing rows show `updated_at IS NULL` after the upgrade" a clean, unambiguous, independently checkable PASS condition (Section 9).
- `KEY status_idx (status)` — the one new named index the plan requires; justified because `status` is the one Version 2 field designed to be queried by value (an access pattern that does not exist for any Version 1 field), so it is the field that actually benefits from its own index, unlike `updated_at`, which is not filtered on in this scenario.
- Column order: `status` is added directly after `created_at`, before no reordering of Version 1 columns; column order does matter for the frozen structural comparison (Section 8), so the post-upgrade specification pins it exactly as written above, and the structural-verification design (Section 8) checks order along with everything else via `SHOW CREATE TABLE` (which is order-sensitive).

Every column's SQL type, length, nullability, default, and every key are frozen as written above before implementation begins, per `WP-SCENARIO-008-PLAN.md`'s frozen-criteria rule (Section 10).

---

## 3. Fixture Data

Three deterministic pre-upgrade rows, chosen to exercise distinct content shapes so that truncation, mutation, or row loss would be easy to detect:

| item_key | item_value | created_at |
|---|---|---|
| `sfschema_item_alpha` | `Alpha plain text value.` | `2020-01-01 00:00:00` |
| `sfschema_item_bravo` | `Bravo value with "quotes", punctuation! And unicode: café, naïve, 日本語, 🎉.` | `2021-06-15 12:30:00` |
| `sfschema_item_charlie` | A single deterministic string of exactly 1,500 characters, built by repeating the fixed literal `"The quick brown fox jumps over the lazy dog. "` (46 characters) as many whole times as fit within 1,500 characters, then truncating the final repetition to land on exactly 1,500 characters — a fixed, reproducible value, not a randomly generated one | `2022-12-31 23:59:59` |

No row uses a random value or `NOW()`/`CURRENT_TIMESTAMP`; all three `created_at` values are hardcoded, distinct calendar dates, chosen so that a byte-for-byte comparison of this column before and after the upgrade is unambiguous.

**Expected Version 2 values for every one of these three rows, immediately after the upgrade:**

- `status` = `'active'` (the Version 2 column default; MySQL backfills existing rows with a column's `DEFAULT` when the column is added `NOT NULL DEFAULT`).
- `updated_at` = `NULL` (MySQL backfills existing rows with `NULL` for a newly added nullable column with no explicit default, as justified in Section 2).

Any row observed with a `status` other than `'active'`, or a non-`NULL` `updated_at`, immediately after the upgrade (before any scenario-specific logic deliberately changes them, which this scenario's design does not do) is itself a FAIL signal under `WP-SCENARIO-008-PLAN.md`'s FAIL criteria ("Any column's ... default ... does not match the frozen specification").

---

## 4. Migration Trigger and Gate

**How Version 1 is installed:** `register_activation_hook( __FILE__, 'sfschema_activate' )`. `sfschema_activate()` runs `dbDelta()` against the Version 1 `CREATE TABLE` statement (Section 2) and, only if the table did not already contain a stored version, sets `sfschema_db_version` to `1`. This mirrors `DATABASE.md`'s canonical activation-hook pattern and WP-SCENARIO-006's precedent for initial schema creation.

**How fixture rows are inserted:** a harness-invoked function, `sfschema_seed_fixture_rows()`, calls `$wpdb->insert()` once per row in Section 3, using prepared parameterization (never raw SQL string interpolation), immediately after activation and before any upgrade is triggered. This function is not hooked to anything automatically; it is deliberately a directly-callable harness action, since seeding fixture data is scenario setup, not production plugin behavior.

**How the stored schema version is set to Version 1:** performed inside `sfschema_activate()` itself, described above — the version is set to `1` only as part of, and only immediately after, successful Version 1 table creation, never before it and never as a separate step that could run out of order.

**How the Version 2 code is introduced or activated:** the Version 2 target schema and the upgrade routine are both present in the fixture's source from the moment the fixture is authored (there is no separate "install Version 2 code later" step in this design — unlike a real-world plugin update, this scenario does not model shipping new plugin code after the fact, since that would only be a packaging/deployment detail, not new schema-lifecycle evidence). What changes between "Version 1 installed" and "Version 2 installed" is the *stored version value* and the *actual table structure*, not the plugin's own code, which is fixed from authorship.

**How the migration reads and compares the stored version, and under which condition it runs:** `sfschema_maybe_upgrade()`, registered on the `plugins_loaded` action (`add_action( 'plugins_loaded', 'sfschema_maybe_upgrade' )`), reads `sfschema_db_version` via `get_option()` and compares it against `SFSCHEMA_TARGET_VERSION` (`2`) with a strict `<` comparison. If the stored value is `< 2`, the upgrade runs. If the stored value is already `2` (or, defensively, anything `>= 2`), the function returns immediately without touching the database.

`plugins_loaded` is chosen deliberately over relying solely on `register_activation_hook()`, because an activation hook only fires at the moment a plugin is activated — it would never re-fire if the plugin's on-disk code changed without a deactivate/reactivate cycle, which is exactly the ambiguity the task instructs this design to avoid. `plugins_loaded` fires on every request in which the plugin is active (including every fresh `wp-load.php` bootstrap this portfolio's harness already uses), so it gives a naturally repeatable, deterministic re-evaluation point without requiring the harness to deactivate and reactivate the plugin between checks — deactivating/reactivating would itself be a confounding action this scenario does not need. Because `activate_plugin()` executes the newly-activated plugin's file directly and does not itself re-fire `plugins_loaded` for that same plugin within the same request, `sfschema_activate()` (the activation hook) is what performs Version 1 creation, while `sfschema_maybe_upgrade()` (on `plugins_loaded`) is what performs the *upgrade* on the next and every subsequent process — a clean division that matches how real-world WordPress plugins (e.g. version-gated upgraders shipped by many production plugins) perform schema upgrades outside the activation moment.

**When the stored schema version is updated:** only inside `sfschema_maybe_upgrade()`, only after both (a) `dbDelta()` has been called against the Version 2 target schema and (b) `sfschema_verify_v2_structure()` (Section 8) has independently confirmed, via direct `INFORMATION_SCHEMA` inspection, that the resulting structure matches the frozen Version 2 specification exactly. The stored version is **not** advanced before that verification succeeds — this is a hard design rule, not an incidental detail, directly satisfying the plan's requirement that "the stored version must not be advanced before successful migration and verification."

**What happens if schema validation fails:** `sfschema_maybe_upgrade()` records the failure into a dedicated option, `sfschema_last_upgrade_result` (holding a structured array with a status string and a description of what failed), and leaves `sfschema_db_version` unchanged at its pre-upgrade value. The function does not throw, `die()`, or otherwise interrupt the surrounding `plugins_loaded` action; it fails safely and observably. Under the frozen schema in Section 2, this scenario does not expect this branch to be exercised in the successful-path execution described in Section 13 — `dbDelta()` performing a strictly additive column/index change is a well-understood, low-risk operation — but the branch exists, is deterministic, and is available for a future implementation phase to exercise directly (e.g. by calling the verification function against a deliberately wrong expected schema) if a synthetic-failure demonstration is later judged worthwhile; the frozen PASS/FAIL criteria in the approved plan do not require one.

**What happens on repeat execution after Version 2 is already installed:** `sfschema_maybe_upgrade()` reads `sfschema_db_version`, finds it already equal to `2`, and returns immediately. No `dbDelta()` call, no `INFORMATION_SCHEMA` query, no write of any kind occurs on this path. This is the idempotence path exercised in Section 10.

---

## 5. `dbDelta()` Role

`dbDelta()` is the mechanism that performs the structural change; it is not itself the capability being proved. The capability being proved is the surrounding engineering discipline described throughout this document: correct version detection, correct gating, and independent verification of the result — `dbDelta()` is one implementation detail inside that discipline, exactly as `WP-SCENARIO-008-PLAN.md` Section 2 states.

**Structural changes `dbDelta()` will perform:** for the Version 1 → Version 2 transition, `dbDelta()` is given the complete Version 2 target `CREATE TABLE` statement (Section 2). Because the table already exists with the Version 1 structure, `dbDelta()`'s own comparison logic will emit and execute only the additive statements needed to reach the target: `ADD COLUMN status ...`, `ADD COLUMN updated_at ...`, and `ADD KEY status_idx (status)`. It will not touch `id`, `item_key`, `item_value`, `created_at`, the primary key, or the existing unique key, since those already match the target definition exactly.

**Exact SQL-formatting constraints that must be respected** (per `dbDelta()`'s well-documented parsing sensitivity, and matching `DATABASE.md`'s own canonical example):

- Exactly two spaces between `PRIMARY KEY` and the following opening parenthesis (`PRIMARY KEY  (id)`), matching `DATABASE.md`'s own example verbatim.
- Each column definition on its own line.
- `KEY` and `UNIQUE KEY` clauses use the exact keyword casing and spacing `dbDelta()`'s regex-based parser expects (`UNIQUE KEY item_key (item_key)`, `KEY status_idx (status)`).
- No trailing comma after the final line inside the parentheses.
- The `$charset_collate` variable is appended directly after the closing parenthesis, exactly as in `DATABASE.md`'s example.
- The SQL string is built once, as a single canonical "current target schema" string per version, not as hand-written incremental `ALTER TABLE` statements — this is what allows the same `sfschema_maybe_upgrade()` call path to be safe whether the table is currently at Version 1 or does not yet reflect Version 2's new columns.

**How the implementation will verify the actual resulting structure rather than assuming `dbDelta()` succeeded:** `dbDelta()`'s own return value (an array of strings describing what it did) is logged as supplementary evidence, but is never treated as proof of success by itself. The authoritative check is `sfschema_verify_v2_structure()` (Section 8), which independently re-queries `INFORMATION_SCHEMA.COLUMNS` and `INFORMATION_SCHEMA.STATISTICS` for the live table immediately after the `dbDelta()` call and compares the actual result, field-by-field, against the frozen Version 2 specification. Only that independent re-query — not `dbDelta()`'s self-reported output — gates whether `sfschema_db_version` is advanced.

**Changes that require explicit SQL or explicit post-migration initialization:** none of Version 2's additions require anything beyond what `dbDelta()` performs from the target schema string; MySQL's own `ADD COLUMN ... DEFAULT 'active'` and `ADD COLUMN ... NULL` semantics already backfill existing rows correctly (Section 3), so no separate `UPDATE` statement is planned or needed.

**How duplicate indexes or repeated structural changes will be detected:** `sfschema_verify_v2_structure()`'s `INFORMATION_SCHEMA.STATISTICS` query (Section 8) enumerates the table's actual index list by name; the verification asserts the exact expected index set (`PRIMARY`, `item_key`, `status_idx`) with no additional or duplicate entries. Because `dbDelta()` is idempotent for an already-matching target schema (it compares existing structure against the target string and only emits changes for genuine differences), and because `sfschema_maybe_upgrade()` only ever calls `dbDelta()` when the stored version is behind target (Section 4), the repeat-execution path (Section 10) never calls `dbDelta()` a second time at all — eliminating the main realistic source of a duplicate-index risk by design, not merely by hoping `dbDelta()` handles it gracefully.

---

## 6. File Design

No file below is created by this task. All paths follow this portfolio's established conventions (a `wp-content/plugins/squirrelforge-<capability>-fixture/` directory in the Hospital installation, `composer.json`/`phpunit.xml`/`tests/` alongside it, and a documentation update inside the existing `38_WORDPRESS` files).

| Path | Purpose | Temporary / Permanent | Category | Cleanup Expectation |
|---|---|---|---|---|
| `wp-content/plugins/squirrelforge-schema-lifecycle-fixture/squirrelforge-schema-lifecycle-fixture.php` (Hospital install) | The fixture plugin itself: constants, `sfschema_activate()`, `sfschema_maybe_upgrade()`, `sfschema_verify_v1_structure()`/`sfschema_verify_v2_structure()`, `sfschema_seed_fixture_rows()`, cleanup helpers | Permanent (left in place, inactive, after cleanup — matching the precedent set by every prior fixture plugin's source file, e.g. WP-SCENARIO-007's `squirrelforge-rest-fixture.php`) | Fixture / production-shaped code, not part of the SquirrelForge repository | Plugin deactivated; its own runtime data (table, options) removed; the file itself is not deleted |
| `wp-content/plugins/squirrelforge-schema-lifecycle-fixture/composer.json` (Hospital install) | Dev-only PHPUnit dependency declaration | Permanent | Test scaffolding | Left in place alongside the fixture file |
| `wp-content/plugins/squirrelforge-schema-lifecycle-fixture/phpunit.xml` (Hospital install) | Test runner configuration | Permanent | Test scaffolding | Left in place |
| `wp-content/plugins/squirrelforge-schema-lifecycle-fixture/tests/bootstrap.php` (Hospital install) | Fake-WordPress bootstrap for the pure-logic unit tests (Section 7). Narrower in scope than prior scenarios' bootstraps: it can meaningfully fake `get_option()`/`update_option()` and the version-comparison function, but it cannot meaningfully fake `dbDelta()` or real MySQL structural behavior — those are exercised only by live execution (Section 8), never by this bootstrap | Permanent | Test scaffolding | Left in place |
| `wp-content/plugins/squirrelforge-schema-lifecycle-fixture/tests/SchemaLifecycleTest.php` (Hospital install) | Focused unit tests for the version-comparison/gating logic and the fixture-row data helpers, run against the fake bootstrap | Permanent | Test | Left in place |
| Temporary harness scripts (e.g. `sfschema_setup.php`, `sfschema_upgrade_check.php`, `sfschema_repeat_check.php`, `sfschema_cleanup.php`), created in the scratchpad directory during a future implementation phase | One-shot fresh-PHP-process scripts that bootstrap `wp-load.php` and drive each phase of Section 13 | Temporary | Runtime harness | Deleted immediately after the phase that used them, per this portfolio's established practice |
| `38_WORDPRESS/AGENT-SCENARIO-TESTS.md` (SquirrelForge repository) | Future "Runtime Validation — WP-SCENARIO-008" evidence block, added only after all execution/validation/cleanup phases pass | Permanent | Documentation | Not applicable (documentation, not runtime state) |
| `38_WORDPRESS/AGENT-READINESS-REPORT.md` (SquirrelForge repository) | Future Capability Summary / Runtime Execution Evidence update | Permanent | Documentation | Not applicable |
| `38_WORDPRESS/AGENT-READINESS-CHECKLIST.md` (SquirrelForge repository) | Future verification-paragraph update, matching the pattern used for every completed runtime scenario to date | Permanent | Documentation | Not applicable |

None of the documentation files in the last three rows are touched by this implementation-design task; they are listed because Section 13's final phases will eventually touch them, and this table is required to declare every file expected across the scenario's full lifecycle, not only this task's own output.

---

## 7. Test and Validation Design

| # | Step | Starting State | Action | Expected Result | Failure Signal | Planned Evidence Record |
|---|---|---|---|---|---|---|
| 1 | Version 1 installation | Fixture plugin inactive; no `{$wpdb->prefix}sfschema_items` table; no `sfschema_db_version` option | Activate the fixture plugin | Table created with exactly the Version 1 structure; `sfschema_db_version` = `1` | Table missing, malformed, or version not set to `1` | Initial schema inspection (Section 8) |
| 2 | Version 1 schema inspection | Table just created | Run the structural-inspection queries (Section 8) | Structure matches the frozen Version 1 specification exactly | Any structural mismatch | Initial schema inspection record |
| 3 | Fixture-data insertion | Version 1 table, empty | Call `sfschema_seed_fixture_rows()` | Exactly three rows present, matching Section 3 exactly | Row count ≠ 3; any value mismatch | Pre-upgrade fixture-data record |
| 4 | Version detection (pre-upgrade) | `sfschema_db_version` = `1`; target = `2` | Inspect the stored version and confirm the gate would evaluate "upgrade needed" | Stored version read as `1`; comparison correctly identifies it as behind target | Incorrect read or incorrect comparison result | Schema-version transition record (pre-upgrade entry) |
| 5 | Version 2 migration | Fresh process; `plugins_loaded` fires naturally | No harness action beyond starting a fresh process | `sfschema_maybe_upgrade()` runs, calls `dbDelta()`, structure reaches Version 2, `sfschema_db_version` becomes `2` only after verification succeeds | Upgrade does not run; runs but fails verification; or version advances before verification | Runtime execution record; schema-version transition record (post-upgrade entry) |
| 6 | Version 2 structural inspection | Upgrade just completed | Run the structural-inspection queries (Section 8) against the post-upgrade table | Structure matches the frozen Version 2 specification exactly | Any structural mismatch | Post-upgrade schema inspection |
| 7 | Existing-row preservation | Post-upgrade table | Compare the three original rows' `id`, `item_key`, `item_value`, `created_at` against the pre-upgrade record | All four fields identical for all three rows | Any field difference; row missing; extra row present | Post-upgrade data-preservation comparison |
| 8 | New-field initialization | Post-upgrade table | Inspect `status` and `updated_at` for all three original rows | `status` = `'active'`; `updated_at` = `NULL`, for all three | Any row with a different `status` or a non-`NULL` `updated_at` | Post-upgrade data-preservation comparison (same record as #7) |
| 9 | Stored-version transition | Full sequence from #1 through #6 | Record the observed version at each checked point | Sequence reads: absent → `1` → `2`, in that order, with no skipped or out-of-order value | Any unexpected value or ordering | Schema-version transition record |
| 10 | Repeat migration | `sfschema_db_version` already `2` | Start another fresh process (natural `plugins_loaded` re-evaluation) | No `dbDelta()` call; no structural or data change; version remains `2` | Any change of any kind on this run | Idempotence execution and comparison |
| 11 | Duplicate-column/index prevention | Post-repeat state | Re-run the structural-inspection queries (Section 8) | Exactly the Version 2 column and index sets, no duplicates, no additional entries | Any duplicate or additional column/index | Idempotence execution and comparison (structural half) |
| 12 | PHP warning/error absence | Every phase above | Capture PHP warnings/notices/deprecations/errors and `$wpdb->last_error` at every phase | Zero at every phase | Any non-empty capture | Runtime warning/error record |
| 13 | Cleanup | Scenario fully validated | Run the cleanup procedure (Section 12) | Table dropped; `sfschema_db_version` and `sfschema_last_upgrade_result` absent; plugin deactivated | Any scenario-owned artifact remaining | Cleanup verification record |
| 14 | Repository boundary | Throughout | `git status --short` / `git diff --check` for SquirrelForge at each phase boundary; confirm all nine prior fixtures untouched | Clean except for the eventual documentation commit; all prior fixtures unaffected | Any unexpected SquirrelForge change; any prior fixture altered | Repository-boundary verification record |

---

## 8. Structural Verification

All structural verification uses direct `INFORMATION_SCHEMA` inspection and `SHOW CREATE TABLE`, never visual comparison and never an assumption that `dbDelta()`'s own return value is sufficient (Section 5).

**Table existence:**

```sql
SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$wpdb->prefix}sfschema_items';
```

**Columns, types, lengths, nullability, defaults:**

```sql
SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE, COLUMN_DEFAULT, EXTRA
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$wpdb->prefix}sfschema_items'
ORDER BY ORDINAL_POSITION;
```

**Primary key and secondary indexes:**

```sql
SELECT INDEX_NAME, COLUMN_NAME, NON_UNIQUE, SEQ_IN_INDEX
FROM INFORMATION_SCHEMA.STATISTICS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$wpdb->prefix}sfschema_items'
ORDER BY INDEX_NAME, SEQ_IN_INDEX;
```

**Charset and collation, plus a full structural snapshot in one call:**

```sql
SHOW CREATE TABLE {$wpdb->prefix}sfschema_items;
```

`SHOW CREATE TABLE` is used as the single, holistic cross-check (it independently reflects columns, types, keys, `AUTO_INCREMENT`, charset, and collation in one normalized statement) in addition to, not instead of, the three targeted `INFORMATION_SCHEMA` queries above — using both means a discrepancy the `INFORMATION_SCHEMA` queries might not individually surface (e.g. an unexpected `ENGINE` or an unexpected column-order artifact) is still caught.

**Normalization, to avoid false differences from environment-specific formatting:**

- The `AUTO_INCREMENT=<n>` value inside `SHOW CREATE TABLE`'s output is stripped/masked before comparison — it changes with row count and is not a structural property.
- `INFORMATION_SCHEMA.COLUMNS` and `.STATISTICS` result sets are explicitly `ORDER BY`'d (as shown above) before comparison, so result ordering from the database engine itself cannot cause a false mismatch.
- Data-type name comparisons are lowercased before comparison, since MySQL may report type synonyms in varying case across versions.
- Whitespace and line-ending differences in the raw `SHOW CREATE TABLE` string are normalized (collapsed to single spaces, trimmed) before a byte-for-byte comparison, so formatting differences between MySQL versions do not register as a structural difference.
- All comparisons run against the same live MySQL instance for both "before" and "after" captures within a single scenario execution, so cross-environment differences are not a concern for this scenario's own PASS/FAIL determination (only for reproducibility by a future reviewer on a different environment, which the recorded MySQL version, Section 5 of the plan's Prerequisites, addresses).

---

## 9. Data-Preservation Verification

Deterministic before-and-after comparison, ordered and machine-readable:

```sql
SELECT id, item_key, item_value, created_at, status, updated_at
FROM {$wpdb->prefix}sfschema_items
ORDER BY id ASC;
```

(The `status`/`updated_at` columns do not exist yet at the pre-upgrade capture; the pre-upgrade capture selects only `id, item_key, item_value, created_at ORDER BY id ASC`.)

The design proves:

- **Same row count** — `COUNT(*)` before and after both equal `3`.
- **Same primary keys** — the ordered `id` list before and after is identical (no row was dropped or recreated with a new auto-increment value).
- **Same original column values** — `item_key`, `item_value`, and `created_at` are compared field-by-field, per row, between the pre- and post-upgrade captures; a SHA-256 hash of the canonical (ordered, whitespace-normalized) JSON encoding of these three fields across all rows is additionally computed before and after, as a fast single-value equality check layered on top of the field-by-field comparison — mirroring this portfolio's established practice of using SHA-256 hashes as tamper-evidence, applied here to fixture *data* rather than fixture *source code*.
- **Expected values in newly introduced columns** — `status = 'active'` and `updated_at IS NULL` checked for every row (Section 3).
- **No duplicate rows** —
  ```sql
  SELECT item_key, COUNT(*) FROM {$wpdb->prefix}sfschema_items
  GROUP BY item_key HAVING COUNT(*) > 1;
  ```
  expected to return zero rows, both before and after.
- **No unexpected row recreation** — the `id` values themselves (not just their count) are compared before and after; an unexpected recreation would surface as a changed `id` for an otherwise-matching `item_key`, which the ordered `id`-list comparison above would catch even if row count and content were coincidentally still correct.

---

## 10. Idempotence Verification

A second natural evaluation of `sfschema_maybe_upgrade()` (a further fresh process, per Section 4) must prove more than continued table existence:

- **The migration gate correctly skips Version 2 re-application** — verified by confirming zero `dbDelta()` invocation occurred on this run (no structural change is observed at all, see next point) and that `sfschema_db_version` is read as already `2` at the start of this process.
- **No schema metadata changes** — the full `SHOW CREATE TABLE` snapshot (Section 8) captured immediately after the second run is compared, normalized as in Section 8, byte-for-byte against the snapshot captured immediately after the first (real) upgrade. They must be identical.
- **No new indexes** — the `INFORMATION_SCHEMA.STATISTICS` index enumeration (Section 8) is compared the same way; the index set must be unchanged.
- **No row mutations** — the full data capture (Section 9) is repeated and compared field-by-field, and via the same SHA-256 hash, against the capture taken immediately after the first upgrade; both must match exactly.
- **No new rows** — row count remains `3`.
- **No version change** — `sfschema_db_version` remains `2` before and after this second run.
- **No warnings or errors** — captured per Section 11, expected empty for this run exactly as for every other phase.

Before-and-after comparisons for the repeat run are therefore: post-upgrade-1 structural snapshot vs. post-upgrade-2 structural snapshot; post-upgrade-1 data snapshot (+ hash) vs. post-upgrade-2 data snapshot (+ hash); and the stored version value at both points — three independent equality checks, not one.

---

## 11. Error Capture

Every phase in Section 13 runs inside a fresh PHP process with strict error visibility enabled (matching this portfolio's established practice since WP-SCENARIO-001): PHP's own error reporting is left at its default strict level (never suppressed with `@` or a raised `error_reporting()` floor), a custom error handler captures warnings/notices/deprecations into a per-phase record (explicitly recording "0 captured" rather than the absence of a check when nothing occurs), and any uncaught `Throwable` during a phase is caught by the harness script's own top-level `try`/`catch` and recorded as a phase failure rather than allowed to silently terminate the process. `$wpdb->last_error` is inspected after every database-touching call (`dbDelta()`, `$wpdb->insert()`, and every direct query in Sections 8–9) and is itself part of the runtime warning/error record — a non-empty `$wpdb->last_error` is a failure signal even if PHP itself raised no warning. The absence of visible browser/console output is never treated as evidence of stability; every phase produces an explicit, inspected capture, since this scenario's execution model (fresh `wp-load.php` processes, no browser) has no "browser output" to rely on in the first place.

---

## 12. Cleanup Design

**Removed (disposable runtime/database state):**

- All rows in, then the table itself: `{$wpdb->prefix}sfschema_items` — dropped entirely (`DROP TABLE`), since the table is wholly scenario-owned.
- The `sfschema_db_version` option.
- The `sfschema_last_upgrade_result` option, if it was ever written (expected not to be, per Section 4, but removed unconditionally as a matter of thoroughness).
- Temporary harness scripts in the scratchpad directory — deleted immediately after the phase that used them, not batched to the end.
- No temporary WordPress users or content are created by this scenario at all: unlike WP-SCENARIO-003's Subscriber-level fixture user or WP-SCENARIO-007's low-capability test account, this scenario has no authorization/permission dimension to its claim — it is a pure schema/data structural claim, verified entirely through direct database inspection during ordinary `wp-load.php` bootstraps, never through an authenticated request path. There is therefore nothing of that kind to clean up.

**Kept (intentional permanent documentation/scaffolding, distinct from disposable runtime state):**

- The fixture plugin's own PHP file, `composer.json`, `phpunit.xml`, and `tests/` directory — left in place, inactive, matching this portfolio's precedent for every prior fixture (their source code is not deleted at cleanup; only their runtime data is).
- The eventual documentation additions to `38_WORDPRESS/AGENT-SCENARIO-TESTS.md`, `AGENT-READINESS-REPORT.md`, and `AGENT-READINESS-CHECKLIST.md` — these are the intended permanent evidence record, not disposable runtime state, and are explicitly out of scope for this task.

**Verification of cleanup completeness:**

```sql
SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$wpdb->prefix}sfschema_items';
-- expected: 0

SELECT option_name FROM {$wpdb->prefix}options WHERE option_name LIKE 'sfschema\_%';
-- expected: no rows
```

plus a cleanup-rerun idempotency check — invoking the cleanup routine a second time after the first cleanup has already completed, and confirming it completes without error and with nothing further removed (matching the established pattern used from WP-SCENARIO-002 onward, and explicitly required by `WP-SCENARIO-008-PLAN.md`'s PASS criteria).

---

## 13. Execution Order

A frozen, numbered sequence with explicit stop gates. No later phase may begin if the immediately preceding gate did not pass.

1. Confirm SquirrelForge repository identity, clean tree, and baseline `composer test`.
2. Confirm the Hospital installation's current WordPress/PHP/MySQL versions and confirm no `sfschema_*` table, option, or plugin state is already present.
   **STOP GATE — Baseline validation.** Do not proceed if the repository is not clean or if any `sfschema_*` state already exists.
3. Author the fixture plugin, `composer.json`, `phpunit.xml`, `tests/bootstrap.php`, and the focused unit-test file (Section 6), and run the focused unit suite against the fake bootstrap.
4. Activate the fixture plugin (Version 1 installation) and run the Version 1 structural-inspection queries (Section 8).
   **STOP GATE — Version 1 installation.** Do not proceed if the structure does not match the frozen Version 1 specification exactly.
5. Call `sfschema_seed_fixture_rows()` and capture the pre-upgrade data snapshot (Section 9).
   **STOP GATE — Fixture-data capture.** Do not proceed if row count ≠ 3 or any inserted value does not match Section 3 exactly.
6. Start a fresh process to let `plugins_loaded` naturally trigger `sfschema_maybe_upgrade()`; capture the runtime execution record.
   **STOP GATE — Migration.** Do not proceed if the upgrade did not run, ran but failed verification, or advanced the stored version before verification succeeded.
7. Run the Version 2 structural-inspection queries (Section 8) and compare against the frozen Version 2 specification.
   **STOP GATE — Structural verification.** Do not proceed if any column, key, type, default, nullability, charset, or collation differs from the frozen specification.
8. Run the data-preservation comparison (Section 9): row count, ordered `id`s, `item_key`/`item_value`/`created_at` field equality and hash, `status`/`updated_at` expected values, and the no-duplicate-`item_key` check.
   **STOP GATE — Data-preservation verification.** Do not proceed if any pre-existing value changed unexpectedly, any row is missing or duplicated, or any new-field value differs from Section 3's expectation.
9. Start a second fresh process (natural repeat evaluation) and capture the post-repeat structural and data snapshots (Sections 8–9), comparing them against the post-upgrade (step 7–8) captures.
   **STOP GATE — Repeatability verification.** Do not proceed if any structural or data difference, any new row, or any version change is observed on the repeat run.
10. Run the full focused unit suite again and the full SquirrelForge suite (`composer test`), confirming both remain green, and confirm the runtime warning/error record (Section 11) is empty across every phase so far.
11. Run the cleanup procedure (Section 12) and the cleanup-rerun idempotency check.
    **STOP GATE — Cleanup.** Do not proceed if any scenario-owned table, option, or harness file remains, or if the cleanup rerun is unsafe.
12. Write the documentation update (a future "Runtime Validation — WP-SCENARIO-008" block in `AGENT-SCENARIO-TESTS.md`, plus the corresponding `AGENT-READINESS-REPORT.md`/`AGENT-READINESS-CHECKLIST.md` updates), following the exact pattern established for WP-SCENARIO-006 and WP-SCENARIO-007.
    **STOP GATE — Documentation.** Do not proceed to commit if the documentation does not accurately and fully reflect every gate's actual result.
13. Confirm `git status --short` / `git diff --check` for SquirrelForge show only the intended documentation files, then commit.
    **STOP GATE — Final repository verification.** Do not commit if any unintended file appears in the diff.

None of the above steps have been performed as part of writing this implementation-design document.

---

## 14. Evidence Mapping

Every PASS criterion from `WP-SCENARIO-008-PLAN.md` Section 9, mapped to its implementation component, validation step, evidence artifact, and failure signal:

| PASS Criterion (from the approved plan) | Implementation Component | Validation Step (this document) | Evidence Artifact | Failure Signal |
|---|---|---|---|---|
| Expected table created exactly once | `sfschema_activate()` + `plugins_loaded` gate never re-creating an existing table | Section 13, steps 4 and 9 (repeat run performs no `dbDelta()` at all when already at target) | Initial schema inspection; idempotence structural comparison | Evidence of a drop-and-recreate (e.g. a changed internal table identifier or reset `AUTO_INCREMENT` after step 9) |
| Initial schema matches the frozen specification | Version 1 `CREATE TABLE` string (Section 2) | Section 13, step 4 | Initial schema inspection record | Any structural mismatch |
| Existing version detected correctly | `sfschema_maybe_upgrade()`'s `get_option()` read and `<` comparison | Section 13, steps 6 and 9 | Schema-version transition record | Incorrect stored value or incorrect branch taken |
| Versioned upgrade completes successfully | `sfschema_maybe_upgrade()` + `dbDelta()` against the Version 2 target string | Section 13, step 6 | Runtime execution record | Upgrade does not run, or runs but fails |
| Resulting columns/keys/types/defaults/nullability/charset/collation match the frozen specification | Version 2 `CREATE TABLE` string (Section 2); `sfschema_verify_v2_structure()` | Section 13, step 7 | Post-upgrade schema inspection; schema comparison | Any field mismatch |
| Existing fixture rows and values remain intact except for planned transformations | `dbDelta()`'s additive-only nature; no `UPDATE`/`DELETE` on existing columns anywhere in the design | Section 13, step 8 | Post-upgrade data-preservation comparison | Any unplanned data change |
| Newly introduced fields receive expected values or defaults | MySQL's own column-add backfill semantics (Section 3) | Section 13, step 8 | Post-upgrade data-preservation comparison | `status` ≠ `'active'` or `updated_at` not `NULL` for any original row |
| Repeating the migration causes no additional structural or data change | `plugins_loaded` gate's `>= target` no-op branch | Section 13, step 9 | Idempotence execution and comparison | Any structural or data difference on repeat |
| Stored schema version is correct | Verification-gated version advancement (Section 4) | Section 13, steps 6 and 9 | Schema-version transition record | Incorrect final stored value |
| No PHP warnings or errors occur | Strict error capture at every phase (Section 11) | Section 13, every step | Runtime warning/error record | Any non-empty capture |
| Temporary runtime and database artifacts are removed | Cleanup procedure (Section 12) | Section 13, step 11 | Cleanup verification record | Any residual scenario-owned state |
| Repository boundaries remain intact | `git status --short` / `git diff --check`; untouched prior fixtures | Section 13, steps 1, 2, and 13 | Repository-boundary verification record | Any unintended repository change; any prior fixture altered |
| Working tree is clean after final documentation and commit | Final commit step (Section 16) | Section 13, step 13 | `git status --short` post-commit | Non-empty status after commit |

No PASS criterion from the approved plan lacks a mapped implementation component, validation step, and evidence artifact above.

---

## 15. Risks and Open Questions

All schema details, trigger behavior, data expectations, and cleanup behavior are resolved in Sections 2–13 above and are not left open. The following are the only genuine unresolved items, each requiring live evidence during a future implementation phase rather than a design decision:

1. **Exact live WordPress/PHP/MySQL versions at execution time.** These must be recorded fresh when implementation begins (per `WP-SCENARIO-008-PLAN.md`'s Prerequisites), not assumed from WP-SCENARIO-007's most recent recorded values, since the Hospital installation could have changed in the interim.
2. **Whether `dbDelta()` parses and applies the exact frozen Version 2 SQL string correctly on the first attempt against this specific live MySQL version**, without any adjustment to the formatting described in Section 5. This is a live-execution question, not a design ambiguity — Section 5 already specifies every formatting constraint known to matter; only actually running it against the real Hospital MySQL instance can confirm no unexpected parsing edge case exists.
3. **Whether all nine prior fixture plugins (WP-SCENARIO-001, 002, 003, 004, 005, 006, 007, 009, 010) remain present, inactive, and unaffected at the time this scenario's implementation begins.** This must be reconfirmed live immediately before implementation, not assumed from their historical completion records.

No other open question is deferred to implementation; every other decision this scenario requires has been made and frozen above.

---

## 16. Commit Strategy

Determined from actual repository precedent, not invented:

- WP-SCENARIO-007's history shows a **multi-commit pattern across the SquirrelForge repository**, each commit scoped to one phase's own permanent artifact: a plan-lock commit (`3aaa2f32a5d8eaeb6d3bf7e04760e38dab19f85e`, "Add WP scenario 007 secure REST endpoint plan") occurred first; fixture-preparation and implementation/automated-test phases produced **no commit at all**; live execution and cleanup produced **no commit**; and a single final documentation commit (`b438297d9dd745ed6b9cdb53330cf0a8ec43b84d`, "Document WP scenario 007 REST endpoint evidence") followed, containing only `AGENT-SCENARIO-TESTS.md`, `AGENT-READINESS-REPORT.md`, and `AGENT-READINESS-CHECKLIST.md` — never the fixture plugin, its tests, or any harness script. The same pattern holds for WP-SCENARIO-003 through WP-SCENARIO-006's completions (each ending in one "Update readiness docs + commit" step, per this portfolio's own task history).
- This precedent exists because the fixture plugin, its tests, and its harness scripts live entirely inside the Hospital WordPress installation (`$HOME/Local Sites/hospital/...`), which is **outside the SquirrelForge git repository** — there is no "implementation commit" in the git sense for that code, because it was never a candidate for committing to this repository at all.
- **This scenario follows the same pattern:** this implementation-design document itself is committed now, as its own distinct commit (mirroring the plan-lock commit's role for the planning document), per this task's own commit instruction. No commit will occur for the fixture plugin, its tests, or any harness script at any point, because none of those are SquirrelForge-repository artifacts. A single further commit, containing only the documentation update described in Section 6 and Section 13 (step 12), is expected once — and only once — every stop gate in Section 13 has passed.

---

## 17. Harness Isolation Addendum (post-implementation, confirmed defect and fix)

This addendum records a harness-only defect discovered during the first live-execution attempt, and its fix. It does not revise Sections 1–16 above, and it changes none of the frozen schema, migration logic, fixture data, or PASS/FAIL criteria. It exists because a live execution attempt found that Section 4's original suppression design (`SFSCHEMA_SUPPRESS_AUTO_UPGRADE`) was incomplete, and a follow-up diagnostic investigation determined exactly why and what closes the gap.

### Confirmed cross-process WP-Cron interference

A dedicated investigation (temporary, non-production instrumentation; no fixture or harness behavior changed at the time) proved, with captured backtraces from two controlled conditions, that:

- Every harness script boots `wp-load.php`, which runs WordPress's normal shutdown sequence. That sequence can call `spawn_cron()`, which issues a real, separate, non-blocking HTTP request to the Hospital site's own live `wp-cron.php` — a **different PHP process** from the harness script that triggered it.
- With WP-Cron enabled and the live site reachable (the default, unmodified condition), that separate process was directly observed, via full backtrace, executing `sfschema_maybe_upgrade() → sfschema_migrate_to_v2() → dbDelta()`, issuing the exact three Version 2 `ALTER TABLE` statements against a table that a moment earlier had been independently verified as pure Version 1.
- That same separate process's logged activity stopped immediately after the third `ALTER TABLE`, before the verifier's `INFORMATION_SCHEMA` queries or either `update_option()` call — which is exactly why the resulting state showed Version 2 columns already present while `sfschema_db_version` remained `1` and `sfschema_last_upgrade_result` was absent.
- With `DISABLE_WP_CRON` defined for the harness's own processes (identical install/inspect sequence otherwise), zero cron-triggered processes appeared, and the Version 1 structure remained pure across a fresh-process re-inspection.

One controlled condition reproducibly caused the mutation; the other reliably prevented it.

### Why `SFSCHEMA_SUPPRESS_AUTO_UPGRADE` alone cannot isolate this

`SFSCHEMA_SUPPRESS_AUTO_UPGRADE` is a PHP constant, meaningful only inside the single PHP process where it is defined. The `wp-cron.php` request that `spawn_cron()` issues is a **new, independent HTTP request**, handled by a separate PHP-FPM worker process that boots WordPress from scratch. It has no way to inherit a constant defined in the harness script's own process — there is no shared memory or IPC between them. A per-process suppression flag can only ever protect the process that defines it; it cannot protect against a second process the first one causes to exist. Closing this gap requires preventing the second process from being spawned at all, not adding more in-process guards.

### Selected isolation mechanism

Define `DISABLE_WP_CRON` as `true` before any harness script requires `wp-load.php`. WordPress's own `wp_cron()`/`spawn_cron()` logic checks this constant and, when true, never issues the loopback request in the first place — the correct point of prevention, matching WordPress's own documented mechanism for disabling pseudo-cron.

Implemented in exactly one shared location: `harness/_harness-common.php`'s `sfschema_harness_bootstrap()` function, which every one of the 15 numbered harness scripts already calls before requiring `wp-load.php` (confirmed: `wp-load.php` is required in exactly one place in the entire harness, inside this function). No script duplicates the definition; no script bypasses this shared helper.

### Affected harness phases

All of them. `DISABLE_WP_CRON` is defined unconditionally inside `sfschema_harness_bootstrap()`, independent of the `$suppress_auto_upgrade` parameter, so it applies identically to every phase: baseline check, defensive cleanup, Version 1 installation, Version 1/Version 2 structural capture, fixture-data seeding, pre/post-upgrade data capture, the real migration-trigger bootstrap (Phase 7/script 08), the repeat-migration bootstrap (Phase 11/script 12), the error summary, and final cleanup verification. Defining `DISABLE_WP_CRON` does not suppress or alter scripts 08's and 12's own intended real `plugins_loaded` firing within their own process — that still fires normally, exactly as the frozen migration gate (Section 4) requires. It only prevents that same process from additionally spawning a second, independent background request that could interfere with a later phase.

### Validation requirements

- Confirm `DISABLE_WP_CRON` is defined, with value `true`, before `wp-load.php` is required, in the shared bootstrap only.
- Confirm every numbered harness script calls the shared bootstrap rather than requiring `wp-load.php` directly.
- Confirm `SFSCHEMA_SUPPRESS_AUTO_UPGRADE` behavior is unchanged where it was already required (scripts other than 08 and 12).
- Confirm, via a bounded runtime check (clean → install Version 1 → verify immediately → re-inspect from a fresh process), that no `wp-cron.php` process appears and the Version 1 structure remains unmutated across that fresh-process inspection.
- This addendum authorizes only the isolation fix described above. It does not authorize proceeding into the full WP-SCENARIO-008 live-evidence execution; that remains a separate, later task.

---

## GO / NO-GO Recommendation: **GO** (implementation design only, pending review)

This design is fully bounded, every schema/trigger/data/cleanup decision is frozen, and every PASS criterion from the approved plan has mapped evidence. No implementation, fixture, test, or runtime work has occurred. Awaiting review before any further phase begins.
