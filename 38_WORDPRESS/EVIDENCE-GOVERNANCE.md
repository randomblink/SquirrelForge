Status: Stable

---
# SquirrelForge WordPress Evidence Governance

## Charter

> The SquirrelForge WordPress readiness portfolio is a collection of deterministic engineering validations. Each WP-SCENARIO demonstrates a distinct engineering capability under controlled conditions, records objective evidence, documents the methodology used to obtain that evidence, and contributes directly traceable support for one or more readiness claims.

---

## 1. Purpose

This document governs how engineering evidence is planned, produced, evaluated, and preserved across the SquirrelForge WordPress readiness portfolio. It governs evidence, not implementation details — it does not define how to write PHP, register a REST route, or structure a plugin; those responsibilities remain with `38_WORDPRESS`'s Skills, Knowledge, and Standards files.

Scenario numbering (`WP-SCENARIO-001`, `WP-SCENARIO-002`, ...) is an identifier, not a priority or value ranking. A scenario's number reflects when it was defined or executed, not how important its evidence is to the readiness portfolio. A higher number is not more advanced; a lower number is not more foundational. Priority and value are governed separately, under Readiness Impact (Section 5).

---

## 2. Governance Principles

### Deterministic validation

Every scenario must be repeatable under controlled conditions: a fixed, deterministic fixture; a documented environment; a documented procedure. Another reviewer, given the same scenario record, must be able to understand what was done and why the result is trustworthy, without relying on unrecorded steps or non-reproducible state.

### Distinct contribution

Every scenario must make a distinct contribution to the readiness portfolio. That contribution may be:

- a newly demonstrated capability, or
- materially stronger evidence for an existing capability under meaningfully different conditions.

A scenario must not be included merely because it is the next number in sequence. Before a scenario is planned, its distinct contribution must be identified (see Evidence Uniqueness Review, Section 3).

### Evidence before conclusion

Readiness claims must follow objective evidence, not precede it. A planned capability, a mapped Skill, a written procedure, or a static document does not count as runtime evidence by itself. Documentation/routing traceability and runtime execution evidence are distinct categories and must never be conflated: a scenario that only traces routing proves the route exists, not that the resulting code runs correctly.

### Traceability

Every readiness claim must map to at least one completed scenario. Every completed scenario must support at least one readiness claim. Traceability must work in both directions:

- readiness report → supporting scenario evidence, and
- completed scenario → readiness claim supported.

A readiness claim with no supporting scenario, or a completed scenario that supports no readiness claim, is a governance defect and must be corrected or explicitly disclosed as a gap.

### Evidence preservation

Completed scenario evidence is historical and remains valid within the environment, scope, and methodology under which it was produced. It is not silently rewritten when later scenarios adopt an improved method. Methodology improvements apply prospectively — to future scenarios — unless a deliberate revalidation of the earlier scenario is performed and recorded as such.

If an earlier scenario is later found to contain a methodological flaw that materially affects its conclusion, revalidation may be required. Revalidation must be recorded as a distinct, dated event; it must not overwrite the original record without disclosing what changed and why.

### Stable methodology

The governance framework defined in this document must not be changed merely to accommodate one scenario. Framework changes should address a recurring limitation observed across multiple scenarios, or improve future scenarios generally — not resolve a single scenario's convenience.

---

## 3. Standard Scenario Structure

Future WP-SCENARIO plans and execution records must follow this structure.

### Planning

- **Distinct Contribution Statement** — a short, explicit statement of what new capability or materially stronger evidence this scenario will produce, written before fixture or implementation work begins.
- **Evidence Uniqueness Review** — an explicit check, performed before planning proceeds, that this scenario is not merely the next number in sequence. It must answer:
  - What capability or stronger evidence is new?
  - What objective evidence will exist afterward that does not already exist?
  - If this scenario were removed, which readiness claim would lose direct support?
- **Prerequisites** — conditions required to execute the scenario (e.g. a target WordPress installation, a required PHP/WordPress version, an available tool or credential). Prerequisites are checked, not relied upon; the plan must fail closed if a prerequisite is absent.
- **Dependencies** — earlier evidence intentionally relied upon (e.g. a prior scenario's fixture pattern, a prior scenario's verified capability). Dependencies are cited explicitly by scenario ID, so the evidence chain remains traceable.

### Execution

- **Capability** — the specific engineering capability under test (e.g. "secure REST endpoint engineering," not "WordPress work" generally).
- **Method** — the documented procedure actually used to produce evidence: fixture design, harness design, live-execution mechanism, and the order operations occurred in.
- **Evidence** — the objective observations produced: command output, response bodies, status codes, hashes, test results, and any other directly inspectable artifact.
- **Contribution** — the new readiness claim or materially strengthened readiness claim supported by the scenario.

Do not use "confidence" as the formal layer name for this fourth item. "Contribution" names what the scenario adds to the portfolio; it is not a subjective confidence rating.

### Validation

- **Evidence Quality** — which categories of evidence (Section 4) are present for this scenario, and why.
- **Cleanup Verification** — confirmation that scenario-owned data, users, options, and files were removed, and that cleanup is safe to rerun.
- **Repository Boundary Verification** — confirmation that only the files the scenario was authorized to touch were actually touched, in both the target WordPress installation and the SquirrelForge repository.

The exact validation methods depend on the capability being demonstrated. A REST endpoint scenario's evidence looks different from a migration scenario's evidence or a performance scenario's evidence. This framework requires appropriate evidence for the capability under test, not identical evidence for every scenario.

---

## 4. Evidence Quality Categories

| Evidence Type | Status |
|---|---|
| Planning review | Present / Not applicable |
| Static analysis | Present / Not applicable |
| Automated validation | Present / Not applicable |
| Runtime validation | Present / Not applicable |
| Security validation | Present / Not applicable |
| Performance validation | Present / Not applicable |
| Cleanup verification | Present / Not applicable |
| Repository boundary verification | Present / Not applicable |

This table is descriptive, not a score. It records which categories of evidence a scenario actually produced and which categories do not apply to that scenario's capability (e.g. a static-review scenario may have no Performance validation row marked Present, and that is not a deficiency). Do not introduce maturity levels, weighted scores, percentages, or capability ratings on top of this table. A category marked "Not applicable" is a scope statement, not a failing grade.

---

## 5. Portfolio Structure

### Portfolio Evidence Inventory

A factual inventory of demonstrated evidence — not an aspirational roadmap. It records what has actually been run and observed, not what is planned or intended.

| Engineering Area | Runtime Evidence | Supporting Scenario(s) | Current Status |
|---|---|---|---|

Allowed status language remains factual, such as "Demonstrated" or "No runtime evidence yet." Do not phrase this table as a plan, a target, or a schedule — it reflects only what has already been produced. This inventory is populated and updated by the scenario-completion process described in `38_WORDPRESS/AGENT-SCENARIO-TESTS.md` and `38_WORDPRESS/AGENT-READINESS-REPORT.md`; this document defines the structure, not the current contents.

### Evidence Traceability

Defines how readiness claims map to completed scenarios, satisfying the Traceability principle (Section 2) in both directions.

| Readiness Claim | Supporting Evidence |
|---|---|

### Readiness Impact

Readiness impact is an internal planning aid only. It may use:

- High
- Medium
- Low

Readiness impact reflects project priority — which unproven claim matters most to resolve next — not evidence strength. A scenario's evidence is either present and traceable or it is not; impact ratings never substitute for or dilute that binary fact. Readiness impact must be kept separate from the permanent evidence conclusions recorded in the Portfolio Evidence Inventory and Evidence Traceability tables above — it may change as project priorities shift, while completed evidence does not.

---

## 6. Governance Review

These are review prompts, not additional governance rules. They exist to make the principles in Section 2 concrete and checkable during a scenario review; they do not impose requirements beyond those principles.

| Governance Principle | Reviewer Prompt |
|---|---|
| Deterministic validation | What information would another reviewer need to reproduce this scenario? |
| Distinct contribution | What does this scenario add that the portfolio did not already demonstrate? |
| Evidence before conclusion | Which objective observations support each conclusion? |
| Traceability | Which readiness claims does this scenario support, and which scenarios support each claim? |
| Evidence preservation | Does this change affect historical evidence or only future scenarios? |
| Stable methodology | Is this framework change generally useful, or does it exist only for one scenario? |

---

## 7. Governance Audiences

| Audience | Primary Interest |
|---|---|
| Scenario author | Planning gate and execution methodology |
| Reviewer | Evidence, contribution, and evidence quality |
| Project maintainer | Portfolio inventory, traceability, and readiness impact |

---

## 8. Change Policy

This document should change infrequently. Changes to it should require:

- a concrete recurring limitation, not a one-off inconvenience,
- evidence that the change improves future scenarios generally, not just the scenario that prompted the change,
- preservation of completed historical evidence — no retroactive rewriting of a completed scenario's record as a side effect of a framework change, and
- explicit documentation of whether the change is prospective (applies to future scenarios only) or requires revalidation of specific prior scenarios, and which ones.

---

## 9. Operating Rule for Future Scenarios

> Future scenario work begins by asking which readiness claim lacks deterministic engineering evidence or materially stronger supporting evidence. The scenario number identifies the record; it does not determine the engineering priority.
