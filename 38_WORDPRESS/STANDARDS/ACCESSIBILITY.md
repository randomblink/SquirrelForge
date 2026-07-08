Status: Stable

---
# SquirrelForge WordPress Accessibility Standard

## Purpose

Defines accessibility requirements for WordPress user interfaces created or modified by SquirrelForge.

## Requirements

Interfaces must provide semantic structure, keyboard access, visible focus states, accessible labels, readable contrast, clear errors, meaningful control text, appropriate alternative text, safe ARIA usage, and predictable interaction behavior.

## Output

This Standard must produce or support:

- accessibility review findings;
- blocking accessibility requirements;
- accessibility remediation notes;
- validation evidence;
- and release readiness status for user-facing interfaces.

## Validation Requirements

Accessibility validation is valid only when:

- semantic structure is appropriate;
- all interactive controls are keyboard accessible;
- focus order and visible focus states are usable;
- labels, names, and instructions are clear;
- color contrast is readable;
- errors and status messages are perceivable;
- alternative text is meaningful where required;
- ARIA is used only when native semantics are insufficient;
- dynamic interactions are predictable;
- and user-facing regressions are checked.

## Handoff Rules

- Accessibility defects route to the implementation owner responsible for the affected UI.
- Security, performance, or compatibility tradeoffs discovered during accessibility review route to the relevant specialist role.
- Documentation-impacting accessibility behavior routes to `33_WORDPRESS_ROLES/DOCUMENTATION-ENGINEER.md`.
- Release-blocking accessibility issues route to `33_WORDPRESS_ROLES/RELEASE-ENGINEER.md`.

## Completion Criteria

This Standard is satisfied only when user-facing WordPress interfaces meet applicable accessibility requirements or documented blockers are recorded with an owner and release decision.

## Rule

Accessibility is a release requirement for user-facing WordPress interfaces.
