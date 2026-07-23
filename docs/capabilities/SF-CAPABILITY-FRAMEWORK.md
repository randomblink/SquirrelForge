# SF-CAPABILITY-FRAMEWORK — Capability Document Framework

**Status:** Draft

## 1. Purpose

Capability documents describe a distinct, reusable SquirrelForge workflow and the evidence needed to assess it. They provide a consistent place to record what the workflow does, where it applies, how it is performed, what it cannot establish, and what deliverables it produces.

Capabilities are descriptive. They do not create a separate governance system, replace specifications, or expand the authority of an underlying workflow.

## 2. When to create a capability

Create a capability record when SquirrelForge has a distinct workflow that is useful to describe independently of a single implementation, incident, or verification case. The record should identify whether the capability is merely defined, statically implemented, or supported by runtime evidence.

## 3. Required structure

Each capability should contain, as applicable:

- Purpose
- Scope
- Procedure
- Limitations
- Deliverables
- Status and evidence disposition

Additional sections may clarify inputs, outputs, safety boundaries, or validation criteria when those details materially improve reproducibility.

## 4. Evidence expectations

Capability records must distinguish among:

- Directly demonstrated behavior.
- Static or structural validation.
- Procedure defined but not executed.
- Claims supported by external or source documentation.
- Unavailable or untested behavior.

Runtime claims require preserved evidence and the normal independent review process. A syntactically valid artifact does not, by itself, prove that WordPress or another target system accepted and executed it.

## 5. Governance integration

Capabilities follow SquirrelForge's existing document lifecycle, review identifiers, repository validation, and publication practices. They do not introduce a second review taxonomy or a capability-specific approval authority. Any capability that changes a governed knowledge entry remains subject to that entry's existing correction and baseline processes.

## 6. Versioning and status

Capability records should carry a clear status such as `Draft`, `Defined`, `Static validation complete`, `Runtime demonstrated`, or `Retired`. Status must describe the evidence actually available and must not imply runtime acceptance when only static validation exists.
