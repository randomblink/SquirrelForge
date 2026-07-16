# SF-TEMPLATE-001 — Engineering Specification Template

## 1. Document Information

**Document ID:** SF-TEMPLATE-001

**Title:** Engineering Specification Template

**Classification:** Engineering Template

**Status:** Draft

**Version:** 1.0

**Owner:** SquirrelForge

---

## Instructions for Use

This document is a template. It is not itself an engineering specification and shall not be cited as one.

To use this template, copy its structure into a new document and replace every angle-bracket placeholder (for example, `<DOCUMENT-ID>`) with content specific to the new specification. This template intentionally does not contain a specification number or a worked example, so that no reader could mistake any part of it for a real, existing specification.

Every section listed below is required by the SquirrelForge Engineering Framework unless the governing framework states otherwise for a particular specification type. If a section does not apply to the specification being authored, that section must be addressed deliberately — state explicitly why it does not apply — rather than silently deleted.

---

# 1. Purpose

## 1.1 Objective

`<Describe, in one or two sentences, what engineering subject this specification governs and why that governance is necessary.>`

---

# 2. Scope

## 2.1 Applies To

This specification applies to:

* `<Enumerate the artifacts, activities, or artifact types this specification governs.>`

## 2.2 Exclusions

This specification does not define:

* `<Enumerate excluded subjects.>`

`<For each exclusion, name the specification that owns it, or state that it is intentionally outside the current framework.>`

---

# 3. Specification Boundaries

## 3.1 Owns

* `<List the specific engineering responsibilities this specification, and only this specification, governs normatively.>`

## 3.2 Depends On

* `<SF-SPEC-XXX — Title>`, for `<the specific subject this specification depends on it for>`.

## 3.3 Does Not Define

* `<List responsibilities this specification explicitly does not govern, matching Section 2.2's exclusions.>`

---

# 4. Engineering Principles

## 4.1 `<Principle Name>`

`<State the principle as a single, high-level engineering value this specification is built on. Principles inform the Normative Requirements in Section 5 but are not themselves mandatory requirements.>`

---

# 5. Normative Requirements

The following requirements are mandatory for `<the governed subject>` within the SquirrelForge Engineering Framework.

## 5.1 `<Requirement Name>`

`<State the requirement using "shall." Avoid "should," "may" unless describing genuine optional behavior, and any hedging or drafting language.>`

---

# 6. Quality Criteria

`<The governed subject>` shall be:

* `<Quality attribute, e.g., Deterministic, Traceable, Repeatable>`

---

# 7. Production Ready Definition

`<The governed subject>` shall not be designated **Production Ready** until:

* `<Enumerate the specific conditions, matching the normative requirements above.>`

This section defines what Production Ready means for this specification, referencing the owning section or specification rather than restating content owned elsewhere.

---

# 8. Engineering Review Checklist

Every `<governed subject>` shall satisfy the following checklist before it may be designated Production Ready.

* ☐ `<Checklist item>`

---

# 9. Change Control

This specification shall not be modified to accommodate an individual artifact.

The specification shall be revised only when an engineering improvement benefits the SquirrelForge Engineering Framework as a whole.

All revisions shall:

* Be versioned.
* Be reviewed.
* Be documented.
* Preserve backward compatibility where practical.
* Identify affected engineering artifacts.

---

# 10. Reference Implementations

`<State that Reference Implementations may be designated following successful engineering review, and that none is designated until such an artifact has been verified against every applicable requirement. Do not name a specific artifact unless it has actually been verified — see SF-SPEC-005 and this framework's Reference Implementation Review process.>`

---

# 11. Revision History

| Version | Date | Summary of Changes | Approval Status |
|---|---|---|---|
| `<1.0>` | `<YYYY-MM-DD>` | `<Initial draft.>` | `<Draft — not yet reviewed>` |
