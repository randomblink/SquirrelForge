# SquirrelForge Layer Cleanup Auditor

Version: 1.0.0
Status: Stable
Owner: SquirrelForge Maintainers
Depends On: 01_RULES, 02_WORKFLOWS, 03_CHECKLISTS, 14_ENGINE/VALIDATION.md, 23_GOVERNANCE, 27_OBSERVABILITY
Used By: Repository maintainers, layer maintainers, architecture review workflows
Last Updated: 2026-07-08

## Purpose

The Layer Cleanup Auditor performs a complete architectural, structural, metadata, roster, cross-reference, boundary, corruption, validation, and repository-state cleanup pass for one SquirrelForge layer at a time.

Its purpose is to replace slow file-by-file conversational cleanup with a deterministic layer-level process. The skill reads the entire target layer, compares its components with repository-wide authority boundaries, corrects clear defects in one coherent batch, validates the result once, and stops only when an architectural conflict cannot be resolved from existing repository evidence.

## Primary Outcome

A successful run leaves the target layer:

- structurally complete;
- accurately represented by its README roster;
- metadata-standardized;
- free of accidental duplicate authority;
- free of unresolved phantom references;
- explicit about ownership boundaries;
- consistent with authoritative cross-layer owners;
- free of obvious corruption and malformed content;
- whitespace-clean;
- test-validated;
- ready for a single layer-level commit.

## Operating Mode

The skill operates on a whole layer, not one file at a time.

Default sequence:

```text
Target layer
  → inventory
  → metadata audit
  → roster audit
  → authority map
  → duplicate/alias analysis
  → cross-layer boundary audit
  → cross-reference audit
  → batch corrections
  → README reconciliation
  → closing audit
  → diff validation
  → test suite
  → Git safety checks
  → close or stop with blockers
```

Do not stop between files for routine approval. Stop only for an unresolved architectural ambiguity, destructive change requiring explicit authorization, or repository condition that makes writes unsafe.

## Inputs

Required:

- target layer directory;
- repository root;
- current branch;
- repository architecture and neighboring layer specifications.

Optional:

- known authority map additions;
- known deprecated aliases;
- layer-specific closing criteria;
- required test commands;
- explicit commit authorization.

## Outputs

The skill produces:

1. corrected layer files when defects are clear;
2. corrected README roster and boundary documentation;
3. deprecated redirect stubs where duplicate aliases must remain for traceability;
4. a closing audit result;
5. validation and test results;
6. a concise report of architectural decisions, changed files, test results, commit status, and worktree state.

## Non-Goals

This skill does not:

- invent new architecture merely to make wording symmetrical;
- rewrite correct files for style alone;
- create new authority owners without repository evidence;
- silently delete duplicate files when a redirect preserves traceability better;
- bypass Security, Governance, Validation, Testing, Storage, Execution, or Observability owners;
- commit or push unless the active workflow explicitly authorizes those actions;
- remove a Git lock until active Git processes have been checked.

## Standard Metadata Contract

Canonical Markdown component files should use this header shape unless the repository defines a more specific local standard:

```text
# Component Title

Version: 1.0.0
Status: Stable
Owner: <owner>
Depends On: <concrete references or None>
Used By: <concrete references or layer-level consumers>
Last Updated: YYYY-MM-DD
```

Deprecated redirect files use:

```text
# Deprecated Component Name

Version: 1.0.0
Status: Deprecated
Owner: <owner>
Depends On: <canonical component>
Used By: Legacy references only
Last Updated: YYYY-MM-DD
```

A deprecated file must clearly name the canonical replacement and must not retain active ownership language.

## Classification Model

Each file is classified internally before edits.

### KEEP

Use when the file is structurally sound, metadata-complete, roster-consistent, and correctly bounded. Do not rewrite merely for stylistic uniformity.

### FIX

Use when the component role is correct but targeted wording, metadata, dependency, reference, or boundary corrections are required.

### REVIEW

Use when several responsibilities overlap with other owners, the component boundary is materially unclear, or the file requires a broader rewrite while preserving its valid core role.

### DUPLICATE

Use when two files claim substantially the same canonical responsibility. Inspect both for unique responsibilities. Preserve one canonical owner. Convert the duplicate to a Deprecated redirect when traceability or legacy references justify retention.

### BLOCKED

Use only when repository evidence cannot resolve competing authority claims, required files are unavailable, or a destructive change needs explicit authorization.

## Repository Authority Map

The following map is a baseline. Always verify concrete repository files before relying on it, and extend it as later layers are standardized.

### Engine and State

- `14_ENGINE/VALIDATION.md` owns platform validation orchestration and authoritative validation semantics.
- `14_ENGINE/STATE-MANAGER.md` owns authoritative workflow/task execution-state semantics where defined by the Engine architecture.

### Memory

- `18_MEMORY` owns active memory lifecycle, memory retrieval, retention, and promotion decisions according to its component boundaries.
- Other layers may consume memory references or promoted memory-derived records without absorbing active memory ownership.

### Reasoning

- `19_REASONING/RISK-ASSESSOR.md` owns general risk assessment.
- Reasoning components own their defined decision, rule, strategy, explanation, and self-assessment responsibilities without becoming execution owners.

### Execution

- `20_EXECUTION` owns execution, action dispatch, execution results, and execution-domain behavior according to its component boundaries.
- Other layers coordinate requests and consume execution references; they do not silently execute retries, rollback, recovery, remediation, or business actions.

### Configuration

- `21_CONFIGURATION` owns declarative platform configuration artifacts defined by that layer.
- `21_CONFIGURATION/PERMISSIONS.md` is the declarative permission-policy source where referenced by Security authorization.

### Governance

- `23_GOVERNANCE/POLICY-ENGINE.md` owns governance-policy evaluation where applicable.
- `23_GOVERNANCE/QUALITY-GATES.md` owns governance quality-gate assessment.
- `23_GOVERNANCE/VERSIONING.md` owns general platform version-policy semantics.
- Domain layers may define domain policy records, evidence, or version records without becoming general governance authorities.

### Security

- `24_SECURITY/SECURITY-GOVERNANCE.md` owns security-domain policy definition, approvals, exceptions, and security risk-acceptance decisions.
- `24_SECURITY/AUTHENTICATION-MANAGER.md` owns platform identity authentication, sessions, MFA, and authentication-token semantics.
- `24_SECURITY/AUTHORIZATION-MANAGER.md` owns runtime grant/deny/conditional access decisions.
- `24_SECURITY/IDENTITY-MANAGER.md` owns authoritative identity records and identity lifecycle.
- `24_SECURITY/ENCRYPTION-MANAGER.md` owns cryptographic operations, not secret/key lifecycle.
- `24_SECURITY/INCIDENT-MANAGER.md` owns security-incident lifecycle records and response coordination, not general system recovery.

### Knowledge

- `25_KNOWLEDGE` owns knowledge-domain catalog, validation, citation, version, graph, embeddings, semantic-search, and document-reference responsibilities according to the cleaned component boundaries.
- It does not own active memory or raw storage infrastructure.

### Integrations

- `26_INTEGRATIONS` owns integration coordination, connector interfaces, external protocol adaptation, approved handoffs, and integration-domain status/evidence references according to its cleaned component boundaries.
- It does not own platform Security, Secrets, Execution, Recovery, Storage, or Observability infrastructure.

### Observability

- `27_OBSERVABILITY` owns general telemetry collection, logs, metrics, traces, alerting, dashboards, diagnostics, health reporting, and audit-trail infrastructure according to its canonical component files.
- Domain components may emit events and produce domain findings or evidence references; they do not create parallel observability infrastructure.

### Runtime Configuration

- `28_RUNTIME-CONFIG` owns runtime configuration records and resolution, environment overlays, feature-flag configuration, policy-configuration references, configuration-domain validation, secret lifecycle, and configuration-domain history according to its cleaned boundaries.
- `28_RUNTIME-CONFIG/SECRETS-MANAGER.md` owns secret and key lifecycle, storage references, rotation, revocation, and secret metadata as defined by that file.

### Testing

- `29_TESTING` owns test planning, test-category execution/specification, and test evidence/reporting.
- Testing evidence is consumed by Validation, Governance quality gates, and release/deployment owners; Testing does not absorb their authority.

### Storage

- `37_STORAGE` owns persistence infrastructure, raw document/file persistence, and storage lifecycle according to its component boundaries.
- Domain repositories and registries own domain records and references, not raw persistence infrastructure.

## Authority Analysis Rules

For every responsibility statement, ask:

1. What object or decision is being owned?
2. Is the component creating the authoritative record, consuming it, coordinating it, or merely reporting it?
3. Is another repository component already the canonical owner?
4. Does the wording accidentally turn coordination into authority?
5. Does the wording accidentally turn evidence consumption into validation authority?
6. Does the wording accidentally turn event emission into observability ownership?
7. Does the wording accidentally turn status tracking into authoritative workflow state?
8. Does the wording accidentally turn a domain decision into general governance or security authority?

## High-Risk Authority Verbs

The following verbs require ownership review when their object is vague:

- manage;
- govern;
- enforce;
- validate;
- verify;
- approve;
- authorize;
- authenticate;
- monitor;
- audit;
- record;
- store;
- recover;
- retry;
- rollback;
- remediate;
- route;
- prioritize;
- classify;
- activate;
- publish;
- deploy;
- execute.

Do not remove these verbs mechanically. Clarify the object and owner. For example:

- `validate knowledge trust` may belong to a Knowledge Validator;
- `validate platform completion` may belong to Engine Validation;
- `record incident lifecycle` may belong to Incident Manager;
- `record general telemetry` belongs to Observability infrastructure;
- `route an approved protocol handoff` may belong to an adapter;
- `select business integration routing` may belong to Integration Manager.

## Evidence and Reference Rule

When a component depends on another owner's decision, prefer explicit reference semantics:

- consumes validation-result references;
- records authorization-decision references;
- emits observability event references;
- attaches evidence references;
- records storage references;
- consumes approved policy references;
- reports normalized status references.

Do not use reference wording to hide real authority. If a component truly owns a domain record, say so explicitly.

## README Roster Audit

For the target layer:

1. enumerate all Markdown files at layer root;
2. separate `README.md` from component files;
3. identify canonical, deprecated, and auxiliary files;
4. compare actual files with README roster entries;
5. remove nonexistent components from the roster;
6. add omitted real components;
7. mark deprecated aliases accurately;
8. ensure component descriptions match cleaned ownership boundaries;
9. ensure README flow does not assign authority differently from component files;
10. ensure README layer boundary names major excluded authorities.

README closure requires exact roster reconciliation, not approximate narrative coverage.

## Duplicate and Alias Procedure

When overlap is suspected:

1. compare both files completely;
2. identify unique responsibilities in each;
3. decide whether responsibilities can be cleanly separated;
4. if separation is real, rewrite both boundaries explicitly;
5. if one is redundant, select the canonical file using repository references and clearer architecture;
6. convert the redundant file to a Deprecated redirect when preservation is useful;
7. update README roster descriptions and cross-references;
8. verify the deprecated file contains no active authority language.

## Cross-Reference Audit

Check all explicit component and layer references.

A cross-reference is acceptable when:

- the target exists;
- the target actually owns the referenced responsibility;
- dependency direction is architecturally coherent;
- no circular ownership claim is introduced.

Flag:

- nonexistent files;
- stale renamed components;
- phantom managers, handlers, engines, registries, or services;
- references to deprecated aliases when canonical files should be used;
- dependencies added merely because a word appears in prose;
- broad layer dependencies where a concrete component reference is required for authority clarity.

## Boundary Audit Categories

Every layer pass checks for overlap with these categories where relevant:

- identity and authentication;
- runtime authorization;
- security policy and exceptions;
- general governance and policy evaluation;
- risk assessment;
- platform validation;
- quality gates;
- execution and action dispatch;
- retry, recovery, rollback, and remediation execution;
- authoritative workflow/task state;
- memory lifecycle;
- knowledge lifecycle;
- integration routing and protocol adaptation;
- secrets and key lifecycle;
- cryptographic operations;
- storage and persistence;
- telemetry, logs, metrics, traces, alerts, dashboards, diagnostics, health, and audit infrastructure;
- testing evidence versus acceptance/release authority;
- deployment and release authority.

## Layer-Specific Ownership Matrix

Before editing, construct an internal matrix with one row per component:

```text
Component | Owns | Consumes | Produces | Must Not Own | Canonical Cross-Layer Owners
```

Use the matrix to detect:

- two components owning the same decision;
- manager/specialist overlap;
- registry/validator overlap;
- monitoring/observability overlap;
- domain audit/general audit overlap;
- status tracking/state authority overlap;
- coordinator/executor overlap.

The matrix is an analysis artifact. It need not be committed unless the layer architecture benefits from it.

## Manager Component Rule

A layer manager normally:

- receives domain requests;
- checks request structure and prerequisite references;
- coordinates or routes work to specialist owners;
- aggregates domain status and evidence references;
- reports results to callers.

A manager should not silently absorb every specialist responsibility merely because it coordinates the layer.

## Registry Component Rule

A registry normally owns:

- stable identifiers;
- catalog records;
- metadata;
- lifecycle-reference fields;
- references to authoritative specialist results.

A registry does not independently perform specialist validation, policy decisions, graph inference, execution, or raw storage unless explicitly defined otherwise.

## Validator Component Rule

A domain validator may own domain-specific assessment criteria and results.

It must distinguish those results from:

- platform validation orchestration;
- governance-policy evaluation;
- security authorization;
- quality-gate acceptance;
- release approval.

## Monitor Component Rule

A domain monitor may:

- consume authoritative telemetry/event references;
- correlate domain signals;
- interpret domain thresholds;
- produce domain findings and posture/status reports.

It does not automatically own general telemetry collection, logging, metrics, tracing, dashboards, alerting infrastructure, audit infrastructure, incident lifecycle, recovery, or execution state.

## Audit Component Rule

A domain audit component may own domain audit records, configuration-change history, or evidence aggregation only when explicitly defined.

It must not duplicate `27_OBSERVABILITY/AUDIT-TRAIL.md` or storage infrastructure. Clarify whether it creates domain records, consumes audit events, or provides evidence references.

## Testing Layer Rule

For `29_TESTING` and any testing-related component:

- `TEST-PLANNER.md` owns test planning, scope, suite selection, ordering, prerequisites, and test-plan records; it does not execute tests or approve releases.
- Unit, integration, system, regression, and smoke test components own their test-category definitions, execution coordination or test results as specified; they do not own global validation or quality-gate acceptance.
- `TEST-REPORTING.md` aggregates test results, coverage/status summaries, failure references, and test evidence; it does not approve release, certify governance gates, or own observability infrastructure.
- Testing produces evidence. `14_ENGINE/VALIDATION.md`, `23_GOVERNANCE/QUALITY-GATES.md`, and release/deployment owners consume that evidence for their own decisions.

## Corruption and Malformation Scan

Inspect for:

- duplicated paragraphs;
- truncated sections;
- accidental pasted terminal output;
- malformed Markdown tables;
- broken code fences;
- impossible headings;
- repeated headers;
- contradictory status declarations;
- stale filenames;
- placeholder text presented as final architecture;
- merged content from unrelated components.

Do not treat unusual wording alone as corruption. Require concrete structural evidence.

## Rewrite Rules

When a correction is justified:

1. preserve the component's valid core responsibility;
2. state owned objects and decisions explicitly;
3. state consumed authoritative inputs explicitly;
4. state produced records, decisions, findings, or references explicitly;
5. add a boundary section when ambiguity is likely;
6. use concrete cross-references where authority depends on another component;
7. remove duplicate authority rather than merely adding disclaimers after conflicting claims;
8. keep specialist ownership with specialists;
9. keep managers coordinative unless architecture explicitly says otherwise;
10. avoid speculative dependencies.

## Minimal-Change Rule

Speed does not justify unnecessary churn.

Leave a file unchanged when:

- metadata is already correct;
- roster references are correct;
- authority is clear;
- cross-references resolve;
- no corruption exists;
- wording does not create a real architectural conflict.

## Batch Execution Procedure

### Phase 1 — Repository Safety

Before edits:

- confirm repository root;
- inspect `git status`;
- confirm expected branch;
- identify pre-existing changes;
- do not mix unrelated work into the layer batch.

If a Git lock exists, inspect active Git processes before removing a stale lock.

### Phase 2 — Inventory

- list target-layer files;
- count Markdown files;
- identify README;
- identify canonical components;
- identify deprecated aliases;
- inspect metadata headers;
- inspect current statuses.

### Phase 3 — Whole-Layer Read

Read all layer files before rewriting. Do not make architecture decisions from README alone.

### Phase 4 — Ownership Matrix

Build the internal matrix and compare against the repository authority map.

### Phase 5 — Defect Classification

Classify each file KEEP, FIX, REVIEW, DUPLICATE, or BLOCKED.

Do not require user approval between routine classifications.

### Phase 6 — Batch Cleanup

Apply all clear corrections coherently across component files and README.

When one correction changes a canonical owner, update reciprocal references in the same layer pass.

### Phase 7 — Metadata and Roster Reconciliation

- standardize headers;
- set status accurately;
- update dates;
- reconcile README roster exactly;
- reconcile deprecated aliases;
- ensure README boundary agrees with component files.

### Phase 8 — Cross-Reference and Phantom Dependency Audit

Verify concrete references and remove or replace nonexistent component dependencies.

### Phase 9 — Boundary Scan

Search for high-risk authority verbs and inspect hits semantically. Explicit exclusions are not defects.

### Phase 10 — Closing Validation

Run, at minimum:

```bash
git diff --check -- <TARGET_LAYER>
composer test
git status
```

Also perform:

- metadata/header audit;
- exact roster audit;
- duplicate/deprecated redirect audit;
- cross-reference audit;
- phantom dependency audit;
- boundary conflict audit;
- corruption/malformed-content scan.

### Phase 11 — Git Closure

Before declaring the layer closed:

- confirm intended files only are changed or staged;
- confirm no `.git/index.lock` remains;
- confirm no active Git process owns a lock;
- confirm tests passed;
- confirm diff check passed;
- commit only when authorized;
- after push, verify local HEAD, remote tracking ref, and clean worktree.

## Suggested Shell Audit Commands

These commands are examples and may be adapted to the repository environment.

### Inventory

```bash
find <TARGET_LAYER> -maxdepth 1 -type f -print | sort
```

### Status metadata

```bash
grep -H '^Status:' <TARGET_LAYER>/*.md
```

### Standard metadata fields

```bash
for f in <TARGET_LAYER>/*.md; do
  for key in 'Version:' 'Status:' 'Owner:' 'Depends On:' 'Used By:' 'Last Updated:'; do
    grep -q "^$key" "$f" || echo "$f missing $key"
  done
done
```

### Boundary-risk vocabulary

```bash
grep -RniE '\b(manage|govern|enforce|validate|verify|approve|authorize|authenticate|monitor|audit|recover|retry|rollback|remediate|route|prioritize|classify|activate|publish|deploy|execute)\b' <TARGET_LAYER>
```

Review hits semantically. Do not mechanically rewrite intentional exclusions.

### Git lock safety

```bash
ps aux | grep '[g]it'
find .git -maxdepth 1 -name '*.lock' -print
```

Remove a lock only after confirming it is stale.

## Stop Conditions

Stop and report BLOCKED when:

- two canonical components claim the same authority and repository evidence does not identify the owner;
- resolving the conflict requires deleting or renaming public architecture without authorization;
- required cross-layer owner files are missing;
- repository state contains unrelated changes that would be overwritten;
- a Git lock is active;
- tests fail for reasons plausibly caused by the cleanup and the failure cannot be resolved safely;
- the requested cleanup would require inventing architecture rather than clarifying existing architecture.

## Completion Gate

A layer is closed only when all applicable conditions pass:

- [ ] actual files inventoried;
- [ ] README roster exactly reconciled;
- [ ] metadata headers complete;
- [ ] statuses accurate;
- [ ] duplicate files resolved or explicitly deprecated;
- [ ] ownership matrix has no unresolved duplicate authority;
- [ ] cross-layer boundaries are explicit where needed;
- [ ] concrete cross-references resolve;
- [ ] phantom dependencies removed or corrected;
- [ ] corruption scan passed;
- [ ] `git diff --check` passed;
- [ ] project tests passed;
- [ ] intended repository state verified;
- [ ] no unsafe Git lock condition exists;
- [ ] commit/push state reported accurately.

## Closing Report Format

Use a concise report:

```text
<LAYER> is cleaned, audited, and tested.

Files:
- <count and changed-file summary>

Key outcomes:
- <major ownership decisions>
- <duplicates/deprecations>
- <cross-layer boundary corrections>

Validation:
- Metadata/header audit: passed
- Roster audit: passed
- Cross-reference/phantom dependency audit: passed
- Boundary audit: passed
- Corruption scan: passed
- git diff --check: passed
- composer test: <result>

Commit:
- <hash and subject, or "Not committed">

Working tree:
- <clean or exact remaining changes>
```

## Final Rule

The Layer Cleanup Auditor must optimize for architectural correctness and batch efficiency together: read the whole layer, resolve clear issues coherently, validate once, and avoid file-by-file approval loops. A layer may be declared closed only when its real roster, metadata, ownership boundaries, cross-references, validation results, and repository state all agree.