Status: Planning

---
# WP-SCENARIO-008 — WordPress Database Schema Lifecycle Engineering

This is a pre-execution planning document, produced from `38_WORDPRESS/SCENARIO-PLANNING-TEMPLATE.md`. No fixture, test, or production code has been created, modified, or activated as a result of this document. No readiness document has been updated. This document is separate from `38_WORDPRESS/AGENT-SCENARIO-TESTS.md` (which holds the authoritative static scenario definition and, after execution, the Runtime Evidence section) and must not be confused with either.

---

## 1. Scenario Information

| Field | Value |
|---|---|
| Scenario ID | WP-SCENARIO-008 |
| Title | WordPress Database Schema Lifecycle Engineering |
| Status | Planning |
| Capability Category | Database schema lifecycle engineering (creation, versioned upgrade, verification, preservation, idempotence, cleanup) |
| Primary Evidence Category | Runtime validation — structural schema evidence and before/after data evidence, obtained through direct database inspection against a live WordPress installation |
| Planning Version | 1 |
| Applicable Governance Version or Governance Reference | `38_WORDPRESS/EVIDENCE-GOVERNANCE.md` (Status: Stable). That document defines no separate version number as of this writing; it is referenced by file path and Status, not by an invented version string. |
| Prerequisite Scenarios | None. No prior WP-SCENARIO's completion is a technical precondition for executing this scenario. WP-SCENARIO-006 is the closest related scenario and is treated as a Dependency (Section 6) and as the primary comparison point in the Evidence Uniqueness Review (Section 4), not as a blocking prerequisite. |
| Related Capabilities | WP-SCENARIO-006 (Plugin Migration / `MIGRATE-PLUGIN`) — the only existing scenario with direct schema/database evidence. |
| Supersedes | None. |
| Author | SquirrelForge assistant (planning pass) |
| Last Updated | 2026-07-11 |

---

## 2. Readiness Claim

SquirrelForge can engineer, execute, validate, and document deterministic WordPress database schema lifecycle management, including initial schema creation, schema version detection, versioned upgrades, verification of the resulting schema, preservation of existing data, repeatable migrations, and appropriate cleanup behavior.

`dbDelta()` is treated as an implementation mechanism used to satisfy this claim, not as the capability being proved. The capability being proved is the surrounding engineering discipline: correctly deciding when to create, when to upgrade, what to preserve, and how to verify the result — regardless of which WordPress API performs the underlying structural change.

---

## 3. Distinct Contribution

This scenario provides deterministic evidence for evolving persistent WordPress data structures safely over time — specifically, for a table whose structure changes *after* it already exists and already holds live data, across a real version transition, rather than for a table created once from a flat source.

Repository evidence (Section 4) shows that no completed scenario currently establishes all of the following together:

- Initial schema creation
- Schema-version detection
- Controlled versioned upgrades
- Existing-data preservation
- Structural schema verification
- Migration idempotence
- Database-state cleanup

The nearest existing evidence, WP-SCENARIO-006, establishes several of these items individually but not the specific combination this claim requires — most importantly, it does not establish a real structural change applied to an already-populated table across two distinct schema versions, or explicit version-number-driven branching logic deciding whether an upgrade is needed. Section 4 states this comparison precisely, including where the two scenarios' evidence would overlap.

---

## 4. Evidence Uniqueness Review

**What capability or stronger evidence is new?**

The new evidence is a real, versioned, in-place structural evolution of an already-existing, already-populated WordPress database table — adding or changing structure via `dbDelta()` against live rows already in the old schema, gated by explicit schema-version detection (a stored version value compared against a target version to decide whether an upgrade runs at all). No completed scenario currently exercises this specific combination.

**What objective evidence will exist afterward that does not already exist?**

After this scenario: a captured pre-upgrade schema structure, a captured post-upgrade schema structure, a structural diff between them confirming only the planned changes occurred, a stored schema-version value observed transitioning from one specific version to the next specific version (not merely "legacy option present vs. absent," as in WP-SCENARIO-006), and a repeat-execution comparison proving the upgrade routine is idempotent when the stored version already matches the target version.

**If this scenario were removed, which readiness claim would lose direct support?**

The readiness claim in Section 2 — deterministic schema *lifecycle* management, specifically the "versioned upgrade of an existing, populated table" and "schema-version detection" elements — would have no direct runtime evidence. WP-SCENARIO-006 would remain as evidence for one-time options-to-table migration, but not for repeated, version-gated structural evolution of a table that already exists.

**Comparison against actual completed scenarios (repository evidence only):**

| Scenario | Skill | What it actually demonstrated | Overlap with this scenario's claim |
|---|---|---|---|
| WP-SCENARIO-001, WP-SCENARIO-009, WP-SCENARIO-010 | CREATE-PLUGIN | Plugin scaffolding/activation, Settings API, a self-registered CPT + taxonomy, an options-based update lifecycle | None. No schema/table evidence; options-table reads/writes only, not `dbDelta()`-driven table structure. |
| WP-SCENARIO-002 | DEBUG-PLUGIN | Diagnosis and correction of an activation-time crash caused by an unvalidated option value | None. No schema or table involved. |
| WP-SCENARIO-003 | REVIEW-CODE | Static review of a deliberately flawed plugin, without modification | None. No execution, no schema. |
| WP-SCENARIO-004 | REFACTOR-CODE | Behavior-preserving structural extraction of a "God Class" plugin (option-based persistence) | None. Persistence was option-based; no table schema was created or altered. |
| WP-SCENARIO-005 | OPTIMIZE-PERFORMANCE | Resolved a measured N+1 `get_post_meta()` query pattern | None. Read-path performance on existing WordPress core tables; no schema creation, alteration, or versioning. |
| **WP-SCENARIO-006** | **MIGRATE-PLUGIN** | **Custom-table schema creation via `dbDelta()`, `$wpdb->prefix` portability, one-time legacy option-to-table migration, exhaustive record-level fidelity verification, insert/update idempotency, duplicate-run safety, partial-batch resume, a controlled structured-failure path, migration-state tracking (`not_started`/`in_progress`/`complete`/`failed`), database-state rollback, and a clean-install-vs-upgrade-path distinction based on whether a legacy option existed.** | **Material overlap.** Schema creation via `dbDelta()`, `$wpdb->prefix` use, data-preservation verification, idempotency, and cleanup are already demonstrated for a one-time create-and-migrate shape. **Not yet demonstrated:** a second, later schema version applied to the *same, already-populated* table; explicit numeric/semver schema-version detection driving conditional upgrade logic (as opposed to a binary "legacy option present or not" check); and exhaustive *structural* (column/key/index/type/default/nullability/charset/collation) comparison across a version transition, as distinct from record-level data fidelity. |
| WP-SCENARIO-007 | CREATE-REST-ENDPOINT | Capability-gated REST route, live-verified through the real WordPress REST server | None. No schema or persistent-table evidence; the fixture used a single option, not a custom table. |

**Skill-routing consequence, stated plainly:** `38_WORDPRESS/SKILLS/SKILL-ROUTING-MAP.md` (Migration section, "migrate schema versions" example) and `38_WORDPRESS/SKILLS/MIGRATE-PLUGIN.md`'s own Trigger Conditions ("upgrade a database schema," "change database schema") both route this scenario to the **same primary Skill as WP-SCENARIO-006, `MIGRATE-PLUGIN`** — not a new Skill. This scenario is therefore not the first runtime evidence for a new Skill; it would be the **second** bounded runtime execution of `MIGRATE-PLUGIN`, under a materially different and harder request shape (versioned structural evolution of an existing, populated table, rather than a one-time options-to-table migration). This scenario's distinct contribution claim rests on that narrower, more precise basis — materially stronger evidence for an already-demonstrated Skill under meaningfully different conditions (per `EVIDENCE-GOVERNANCE.md`, Section 2, "Distinct contribution," second bullet) — not on introducing a new Skill or a wholly novel capability.

---

## 5. Prerequisites

- Clean SquirrelForge repository state (confirmed at the start of each phase of this scenario, not assumed).
- A documented WordPress version for the target installation (the Hospital installation's version, as recorded at the time of execution — not assumed from a prior scenario's record, since the installation may have changed).
- A documented PHP version for the runtime used (likewise recorded fresh at execution time).
- A documented database engine and version (MySQL, via Local's site-specific socket, as used by every prior runtime scenario in this portfolio).
- A deterministic WordPress runtime, bootstrapped the same way as every prior runtime scenario (`wp-load.php`, Local's site-matched PHP binary, no WP-CLI — WP-CLI has been confirmed absent from this target in every prior runtime scenario, most recently WP-SCENARIO-007).
- Operational testing and runtime-validation facilities: a working `composer test` baseline for SquirrelForge itself, and the established fake-WordPress-bootstrap PHPUnit pattern used by every prior fixture plugin in this portfolio.
- A known initial database state for the fixture's own schema-relevant tables: no scenario-owned table and no scenario-owned option present before execution begins (to be confirmed at the start of the implementation phase, exactly as every prior scenario has confirmed a clean starting state before creating its own fixture).
- Isolation from unrelated plugins or schema changes: the scenario's fixture must use its own table name and its own schema-version option, and must not alter any table or option belonging to WordPress core, another plugin, or a prior scenario's validation/fixture plugin (WP-SCENARIO-001, 002, 003, 004, 005, 006, 007, 009, 010's fixtures, per the boundary confirmations already recorded for each of them).

None of the above are claimed as already satisfied by this document. This plan states what must be confirmed before implementation begins; confirming them is implementation-phase work, not planning-phase work, and has not occurred as part of writing this plan.

---

## 6. Dependencies

Skills:

- `38_WORDPRESS/SKILLS/MIGRATE-PLUGIN.md` — the primary Skill this scenario routes through (Trigger Conditions: "upgrade a database schema," "change database schema"; Required Reference: `38_WORDPRESS/KNOWLEDGE/DATABASE.md`; Database Engineer role trigger: "schema creation," "schema alteration").
- `38_WORDPRESS/SKILLS/SKILL-ROUTING-MAP.md` — Migration section, "migrate schema versions" routing example.

Knowledge:

- `38_WORDPRESS/KNOWLEDGE/DATABASE.md` — `$wpdb`, schema-change, and custom-table guidance.

Roles:

- `38_WORDPRESS/ROLES/ROLE-ROUTING-MATRIX.md`, Route 7 (`MIGRATE-PLUGIN`) — Required Roles: Project Architect, Plugin Architect, PHP Engineer, Security Engineer, QA Engineer, Documentation Engineer, Release Engineer; Conditional Roles include Database Engineer, triggered here by schema creation/alteration.
- `38_WORDPRESS/ROLES/DATABASE-ENGINEER.md`.

Standards and testing facilities:

- `38_WORDPRESS/STANDARDS/TESTING-STANDARD.md` — Level 6 (Regression Testing), mapped to `29_TESTING/REGRESSION-TESTS.md`, specifically named there as covering "existing settings, migrations, saved data, compatibility" after any change to existing WordPress behavior — directly applicable to a versioned schema upgrade.
- `29_TESTING/REGRESSION-TESTS.md` and `29_TESTING/INTEGRATION-TESTS.md` — general test-category owners `TESTING-STANDARD.md` maps onto.

Governance and readiness records:

- `38_WORDPRESS/EVIDENCE-GOVERNANCE.md` — governs this plan's structure and the distinct-contribution/traceability obligations above.
- `38_WORDPRESS/AGENT-SCENARIO-TESTS.md` — holds WP-SCENARIO-006's "Runtime Validation" block, the specific record this plan compares against in Section 4.
- `38_WORDPRESS/AGENT-READINESS-REPORT.md` — holds the current Capability Summary and Runtime Execution Readiness conclusions this scenario, if executed, would eventually extend (not modified by this document).

Fixture/harness pattern dependency (methodology, not a file dependency):

- The fake-WordPress-bootstrap PHPUnit pattern and the fresh-PHP-process, `wp-load.php`-bootstrapped live-execution harness pattern used by WP-SCENARIO-001 through WP-SCENARIO-007, WP-SCENARIO-009, and WP-SCENARIO-010, all recorded in `38_WORDPRESS/AGENT-SCENARIO-TESTS.md`'s Runtime Evidence section.

No dependency listed above was invented; each is an existing repository file or an existing recorded methodology.

---

## 7. Risk Assessment

| Risk | Detection Method | Planned Mitigation | Evidence Expected |
|---|---|---|---|
| Accidental data loss | Before/after row-level comparison of all pre-existing fixture rows, by primary key, across the upgrade | Upgrade routine only adds/alters columns; never drops or truncates existing rows; a captured pre-upgrade data snapshot is compared byte-for-byte (for unchanged fields) against the post-upgrade state | Before/after data comparison record showing zero unintended loss |
| Partial migration | Direct post-upgrade schema inspection confirming every planned column/key/index exists, not just some | Upgrade routine structured as a single `dbDelta()` call against a complete target schema definition, not incremental ad hoc `ALTER` statements that could partially apply | Post-upgrade schema inspection matching the complete frozen specification |
| Incorrect schema-version detection | Direct inspection of the stored version value before and after, and of the branch actually taken (upgrade run vs. skipped) | Version comparison uses an explicit, testable function comparing stored vs. target version, unit-tested against below/equal/above cases before live execution | Unit test results plus a live-observed version-state transition record |
| Non-idempotent execution | Repeat the upgrade routine after it has already completed; compare full schema and data state before and after the repeat | Upgrade routine's own version check must make a no-op the correct behavior once the stored version already equals the target version; `dbDelta()` itself is intended to be idempotent for identical schema definitions, but the surrounding version-branch logic is what is actually under test here | Idempotence execution record showing zero changes on repeat |
| `dbDelta()` formatting or parsing limitations | Direct post-`dbDelta()` schema inspection against the exact formatting rules `dbDelta()` requires (two spaces after `PRIMARY KEY`, specific `KEY`/`UNIQUE KEY` syntax) | Schema definition strings authored and reviewed against documented `dbDelta()` formatting requirements before first execution; any formatting-caused partial failure is treated as a defect to fix, not a result to accept | Schema inspection confirming all intended columns/keys were actually created, not silently skipped |
| Character-set or collation mismatch | Direct inspection of the created/altered table's charset and collation against `$wpdb->get_charset_collate()` | Schema definitions use `$wpdb->get_charset_collate()`, matching the established pattern from WP-SCENARIO-006 | Structural inspection record showing charset/collation match |
| Column-definition mismatch | Direct inspection of each column's type, default, and nullability against the frozen specification | Frozen specification defined in this plan before implementation; post-upgrade inspection checked field-by-field against it | Column-level structural comparison record |
| Missing or duplicate indexes | Direct inspection of the table's actual index list against the frozen specification | Index list defined in the frozen specification; post-upgrade inspection enumerates actual indexes and compares | Index inspection record |
| Runtime warnings or errors | Strict error/warning/notice/deprecation capture around every phase, consistent with every prior runtime scenario in this portfolio | No suppression of PHP error reporting during execution; any warning or error is treated as a finding, not discarded | Runtime error-capture record for every execution phase |
| Incomplete cleanup | Direct post-cleanup inspection for the scenario-owned table's absence and the scenario-owned version option's absence, plus a cleanup-rerun idempotency check (established pattern from WP-SCENARIO-002 onward) | Cleanup procedure defined and rehearsed as its own phase, separate from and after all validation phases | Cleanup verification record, including a safe rerun |
| Repository-boundary violations | `git status --short` and `git diff --check` for SquirrelForge before and after every phase; confirmation that only the fixture's own table/option and the documented SquirrelForge files were touched | Fixture confined to its own plugin directory in the Hospital installation; no other prior scenario's fixture touched | Repository-boundary verification record for SquirrelForge and confirmation that all eight prior fixture plugins remain untouched |

---

## 8. Evidence Traceability Matrix

| Claim Element | Planned Evidence | Validation Method | Expected Artifact or Record | PASS Condition | Relevant FAIL Condition |
|---|---|---|---|---|---|
| Initial schema creation | Direct post-creation schema inspection | Live inspection of the created table's structure via the database engine, against the frozen initial-schema specification | Initial schema inspection record | Table exists with exactly the specified columns, keys, indexes, types, defaults, nullability, charset, and collation | Missing or unexpectedly recreated table; any structural mismatch |
| Schema-version detection | Stored version value read before and after each execution; branch taken recorded | Direct inspection of the stored version option/value and of which code branch executed | Schema-version transition record | Stored version correctly reflects "none," then the initial version, then the upgraded version, at the correct points | Incorrect version detection or stored version |
| Versioned upgrade | Direct post-upgrade schema inspection compared against pre-upgrade schema inspection | Structural diff between captured pre- and post-upgrade schema states | Pre-upgrade and post-upgrade schema inspection plus schema comparison | Upgrade completes; resulting structure matches the frozen post-upgrade specification exactly | Partial or failed upgrade; unexpected structural difference |
| Resulting-schema verification | Field-by-field comparison of columns, keys, indexes, types, defaults, nullability, charset, and collation | Direct inspection against the frozen specification, not visual/approximate comparison | Schema comparison record | Every field matches the frozen specification | Incorrect types, defaults, nullability, charset, collation, or indexes |
| Existing-data preservation | Before/after comparison of pre-existing fixture rows, by primary key and by field | Row-level comparison, not row-count comparison alone | Pre-upgrade fixture-data record and post-upgrade data-preservation comparison | All pre-existing rows and their pre-existing field values remain intact except for explicitly planned transformations; newly introduced fields carry the expected value or default | Unexpected data mutation, corruption, or loss |
| Repeat execution | Full schema and data state captured before and after re-running the upgrade routine once already complete | Direct comparison of both captures | Idempotence execution and comparison record | Repeating the migration causes no additional structural or data change | Repeat execution alters the final state |
| Runtime stability | Error/warning/notice/deprecation capture at every phase | Direct inspection of captured runtime output | Runtime warning/error record | Zero PHP warnings, notices, deprecations, or errors at any step | Any PHP warning or error |
| Cleanup | Post-cleanup inspection for absence of the scenario-owned table and version option; a safe rerun | Direct inspection, plus a second cleanup invocation | Cleanup verification record | Table and version option both absent after cleanup; rerun completes safely with nothing left to clean | Cleanup failure or residual scenario-owned state |
| Repository-boundary preservation | `git status --short` / `git diff --check` for SquirrelForge; confirmation of untouched prior fixtures | Direct command output inspection | Repository-boundary verification record | SquirrelForge tree clean except for the intended documentation commit; all prior fixtures untouched | Repository-boundary violation |

---

## 9. PASS Criteria

- The expected table is created exactly once during initial activation. "Exactly once" is evidenced by a captured creation timestamp or an explicit before/after existence check across a controlled re-activation attempt that confirms no destructive drop-and-recreate occurred on the second attempt — not merely by observing that one table currently exists, which cannot by itself distinguish idempotent creation from a prior destructive recreation.
- The initial schema (columns, keys, indexes, types, defaults, nullability, charset, collation) matches the frozen specification exactly, confirmed by direct structural inspection.
- The existing stored schema version is detected correctly at each check (none present, matches initial version, matches upgraded version), confirmed by direct inspection of the branch taken, not merely the final stored value.
- The versioned upgrade completes successfully against a table that already contains pre-existing fixture rows.
- The resulting columns, keys, indexes, types, defaults, nullability, charset, and collation match the frozen post-upgrade specification, confirmed field-by-field.
- Existing fixture rows and their values remain intact after the upgrade, except for explicitly planned transformations defined in the frozen specification before implementation.
- Newly introduced fields on existing rows receive the expected value or default defined in the frozen specification.
- Repeating the upgrade routine after it has already completed causes no additional structural or data change, confirmed by a full before/after comparison of the repeat, not an assumption of `dbDelta()`'s general idempotence.
- The stored schema version is correct at the end of the scenario.
- No PHP warnings, notices, deprecations, or errors occur at any phase.
- Temporary runtime and database artifacts (the scenario-owned table, the scenario-owned version option, any temporary harness script) are removed, and cleanup is confirmed safe to rerun.
- SquirrelForge repository boundaries remain intact throughout (verified by `git status --short` / `git diff --check` at each phase boundary) and all prior scenarios' fixtures remain untouched.
- The SquirrelForge working tree is clean after final documentation and commit.

---

## 10. FAIL Criteria

- The expected table is missing, or evidence shows it was unexpectedly dropped and recreated rather than idempotently confirmed.
- Any column, key, or index is missing, duplicated, or does not match the frozen specification.
- Any column's type, default, nullability, charset, or collation does not match the frozen specification.
- Schema-version detection or the stored version value is incorrect at any checked point.
- The upgrade is partial or fails to complete against the pre-populated table.
- Any pre-existing data is unexpectedly mutated, corrupted, or lost.
- Repeating the upgrade alters the final structural or data state in any way.
- Any PHP warning or error occurs at any phase.
- Cleanup fails to remove scenario-owned state, or a cleanup rerun is unsafe.
- Any file, table, or option outside this scenario's declared boundary is altered.
- Evidence is missing for any element of the Evidence Traceability Matrix (Section 8) — an unproven claim element is treated as a FAIL for that element, not silently omitted from the result.

PASS and FAIL criteria above are frozen before implementation and cannot be weakened after execution begins. A later planning revision to these criteria must be an explicit, versioned, justified revision, completed and recorded before execution resumes — never a live adjustment made in response to an inconvenient result.

---

## 11. Execution Strategy

This section is planning-level only. No phase below has been performed as part of writing this plan.

1. **Planning** — this document.
2. **Implementation** — author the fixture plugin's schema-creation and versioned-upgrade logic, matching the frozen specification (not yet written; to be authored in the implementation phase).
3. **Baseline preparation** — confirm clean repository state, clean starting database state, and record the WordPress/PHP/database versions in use at that time.
4. **Initial schema creation** — activate the fixture and create the initial table version.
5. **Pre-upgrade fixture-data insertion** — insert deterministic fixture rows into the initial-version table.
6. **Versioned upgrade** — trigger the schema-version-gated upgrade routine against the already-populated table.
7. **Structural validation** — inspect and compare pre- and post-upgrade schema structure.
8. **Data-preservation validation** — compare pre- and post-upgrade data, row-by-row and field-by-field.
9. **Idempotence validation** — re-run the upgrade routine and confirm no further change occurs.
10. **Runtime-error validation** — confirm zero warnings/errors/notices/deprecations across all prior phases.
11. **Cleanup** — remove the scenario-owned table, version option, and any temporary harness files; confirm cleanup is safe to rerun.
12. **Documentation** — record the resulting evidence in the established scenario-completion pattern (not performed in this task).
13. **Commit and repository verification** — confirm repository boundaries and commit only the documentation produced by that later phase.

---

## 12. Validation Requirements

- Confirm the initial schema, once created, matches the frozen initial specification in full — not partially, not approximately.
- Confirm the stored schema-version value and the branch taken at each check are both correct, independent of each other (a correct final value reached via an incorrect branch is not sufficient).
- Confirm the post-upgrade schema matches the frozen post-upgrade specification in full.
- Confirm every pre-existing data row and field is unchanged except where the frozen specification explicitly plans a transformation.
- Confirm a repeated execution of the upgrade routine, once already complete, produces no further structural or data change.
- Confirm zero PHP warnings, notices, deprecations, or errors occurred at any phase.
- Confirm cleanup removes all scenario-owned state and that a second cleanup invocation completes safely with nothing left to remove.
- Confirm no file, table, or option outside the declared scenario boundary was altered, in both the target WordPress installation and the SquirrelForge repository.

These requirements are independent of which specific artifact names or storage locations are used to satisfy them; that mapping belongs to Section 14.

---

## 13. Evidence Requirements

Independent evidence is required for each of the following, meaning each item's evidence must be obtained through its own direct inspection rather than inferred from another item's result:

- **Runtime behavior** — direct capture of PHP warnings/notices/errors/deprecations across every phase.
- **Initial structure** — direct database-level inspection of the table immediately after creation.
- **Pre-upgrade state** — direct database-level capture of fixture row data before the upgrade runs.
- **Post-upgrade structure** — direct database-level inspection of the table immediately after the upgrade.
- **Before-and-after data comparison** — a direct row-by-row, field-by-field comparison, not a row-count comparison alone.
- **Migration repeatability** — a direct comparison of full state before and after a repeated execution of the upgrade routine.
- **Version-state transitions** — direct inspection of the stored version value and the branch taken at each checked point.
- **Runtime warnings and errors** — as above, captured independently at each phase rather than assumed absent.
- **Cleanup** — direct inspection confirming absence of scenario-owned table/option after cleanup, plus a rerun.
- **Repository status** — direct `git status --short` / `git diff --check` output for SquirrelForge.
- **Final commit** — the actual commit hash and `git show --stat --oneline` output, once a later documentation phase produces a commit.

Machine-readable evidence (command output, structured inspection results, hashes) is required in preference to screenshots wherever it is available, consistent with this portfolio's established practice.

---

## 14. Expected Evidence Inventory

The following artifacts are declared as expected outputs of a future implementation/execution phase. None of them exist yet; this section is a declaration, not a creation step, consistent with this task's planning-only scope.

- Approved planning document (this file, once reviewed).
- Implementation diff (fixture plugin source, not yet written).
- Test output (a focused fake-bootstrap PHPUnit suite, not yet written).
- Runtime execution record (a future execution log against the live Hospital installation).
- Initial schema inspection.
- Pre-upgrade schema inspection.
- Post-upgrade schema inspection.
- Schema comparison (structural diff between initial/pre- and post-upgrade states).
- Pre-upgrade fixture-data record.
- Post-upgrade data-preservation comparison.
- Schema-version transition record.
- Idempotence execution and comparison.
- Runtime warning/error record.
- Cleanup verification.
- Repository-boundary verification.
- Final scenario report.
- Commit hash.
- Final repository status.

Artifact filenames and storage locations remain to be finalized during implementation planning, except where an existing repository convention already determines them (for example, the established pattern of a `wp-content/plugins/squirrelforge-<scenario>-fixture/` directory in the Hospital installation, and a corresponding `38_WORDPRESS/AGENT-SCENARIO-TESTS.md` "Runtime Validation" block, both used by every completed runtime scenario to date).

---

## 15. Portfolio Contribution

If completed successfully, this scenario would add **database schema lifecycle engineering** — specifically, versioned structural evolution of an already-existing, already-populated table, with explicit schema-version detection — as a new evidence category in the portfolio's evidence inventory. It would extend WP-SCENARIO-006's existing `MIGRATE-PLUGIN` runtime evidence to a second, materially harder request shape, rather than introduce runtime evidence for a new Skill.

This section states an intended contribution only. It does not claim this evidence already exists, and no readiness report or scenario-test record has been or will be updated by this planning document.

---

## 16. Governance Compliance

This plan follows `38_WORDPRESS/EVIDENCE-GOVERNANCE.md` as follows:

- **Deterministic validation** (Section 2) — the scenario's fixture, versions, and procedure are to be fully documented before execution, per the Prerequisites (Section 5) and Execution Strategy (Section 11) above, so another reviewer could reproduce it.
- **Distinct contribution** (Section 2) — Section 3 and Section 4 above state the specific, narrow gap this scenario closes and explicitly identify where it overlaps with WP-SCENARIO-006's existing evidence, rather than claiming blanket novelty.
- **Evidence before conclusion** (Section 2) — this plan makes no claim that the scenario has passed, is validated, or has produced evidence; Sections 9–10 define frozen criteria to be checked later, not conclusions reached now.
- **Traceability** (Section 2) — Section 8's Evidence Traceability Matrix maps every element of the readiness claim (Section 2) to planned evidence and a PASS/FAIL condition, in both directions, as required.
- **Evidence preservation** (Section 2) — this plan does not alter, reinterpret, or rewrite WP-SCENARIO-006's existing recorded evidence; Section 4's comparison cites it factually and does not change its record.
- **Stable methodology** (Section 2) — this plan does not propose any change to `EVIDENCE-GOVERNANCE.md` or to the reusable `SCENARIO-PLANNING-TEMPLATE.md`; both are used as already defined.

`EVIDENCE-GOVERNANCE.md` does not designate any scenario — first or otherwise — as a "reference implementation" of its framework, and contains no section granting that status. This plan therefore does not describe WP-SCENARIO-008 as a reference implementation. Whether any scenario should be formally designated as a reference implementation of the governance framework is a possible future governance decision, not one this plan makes or assumes.

---

## 17. Scenario Classification

**Primary Discipline:**
Database Engineering

**Secondary Disciplines:**

- WordPress Plugin Architecture
- Runtime Validation
- Test Engineering
- Persistent Data Management
- Evidence Governance

---

## 18. Reference Documents

- `38_WORDPRESS/EVIDENCE-GOVERNANCE.md`
- `38_WORDPRESS/SCENARIO-PLANNING-TEMPLATE.md`
- `38_WORDPRESS/AGENT-READINESS-REPORT.md`
- `38_WORDPRESS/AGENT-SCENARIO-TESTS.md`
- `38_WORDPRESS/SKILLS/MIGRATE-PLUGIN.md`
- `38_WORDPRESS/SKILLS/SKILL-ROUTING-MAP.md`
- `38_WORDPRESS/KNOWLEDGE/DATABASE.md`
- `38_WORDPRESS/ROLES/ROLE-ROUTING-MATRIX.md`
- `38_WORDPRESS/ROLES/DATABASE-ENGINEER.md`
- `38_WORDPRESS/STANDARDS/TESTING-STANDARD.md`
- `29_TESTING/REGRESSION-TESTS.md`
- `29_TESTING/INTEGRATION-TESTS.md`

---

## GO / NO-GO Recommendation: **GO** (planning only, pending review)

This scenario is bounded, deterministic, and its distinct contribution is precisely and narrowly stated relative to WP-SCENARIO-006's existing evidence. No implementation, fixture, test, or runtime work has occurred. Awaiting review before any further phase begins.
