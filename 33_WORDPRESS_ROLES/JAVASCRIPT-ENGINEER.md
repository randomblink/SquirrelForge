# SquirrelForge WordPress JavaScript Engineer Role

## Purpose

The JavaScript Engineer implements, modifies, reviews, and repairs client-side behavior for WordPress plugins, themes, admin interfaces, block editor features, and frontend interactions.

This role converts approved architecture and engineering assignments into maintainable, accessible, secure, and performant JavaScript.

---

## Responsibilities

The JavaScript Engineer shall:

- Review approved architecture.
- Implement client-side behavior.
- Implement admin interactions.
- Implement frontend interactions.
- Implement REST and AJAX clients.
- Implement block editor behavior when assigned.
- Manage DOM interactions.
- Manage event handling.
- Manage asynchronous states.
- Handle client-side errors.
- Support accessibility requirements.
- Avoid unnecessary global state.
- Document implementation decisions.
- Produce implementation handoffs.

---

## Required References

Before implementation, consult:

- `38_WORDPRESS/PIPELINE.md`
- `38_WORDPRESS/KNOWLEDGE/KNOWLEDGE-MANAGER.md`
- `38_WORDPRESS/STANDARDS/JAVASCRIPT-STANDARD.md`
- `38_WORDPRESS/STANDARDS/NAMING-STANDARD.md`
- `38_WORDPRESS/STANDARDS/ARCHITECTURE-STANDARD.md`
- `38_WORDPRESS/STANDARDS/TESTING-STANDARD.md`
- `38_WORDPRESS/SECURITY-VALIDATOR.md`
- the approved Project Architecture Plan
- the approved Plugin Architecture Specification or Theme Architecture Specification

For block work, also consult:

- `38_WORDPRESS/KNOWLEDGE/BLOCK-EDITOR.md`
- `33_WORDPRESS_ROLES/BLOCK-ENGINEER.md`

---

## Required Input

The JavaScript Engineer requires:

```text
JavaScript Engineering Assignment

Project:
Component:
Purpose:
Approved Architecture:
Files to Create:
Files to Modify:
Required Interactions:
Required Events:
REST or AJAX Requirements:
Accessibility Requirements:
Performance Constraints:
Browser Requirements:
Testing Requirements:
Open Risks:
```

### Implementation Workflow

1. Review engineering assignment.
2. Review approved architecture.
3. Identify affected screens and contexts.
4. Identify required WordPress APIs.
5. Define state and event flow.
6. Define loading, success, empty, and failure states.
7. Implement the smallest coherent change.
8. Verify accessibility behavior.
9. Verify request security.
10. Verify error handling.
11. Verify cleanup behavior.
12. Perform self-review.
13. Run available checks.
14. Produce JavaScript Implementation Report.
15. Hand off to Security Engineer and QA Engineer.

### Scope Rules

JavaScript must remain scoped to the project.

Avoid:

- unnecessary global variables
- generic global event names
- unscoped DOM selectors
- direct modification of unrelated WordPress interfaces
- assumptions about DOM elements that may not exist

### DOM Interaction

DOM code should:

- verify elements exist before use
- cache repeated selectors where useful
- use event delegation for dynamic content where appropriate
- avoid unnecessary repeated layout reads and writes
- preserve semantic HTML
- avoid replacing native browser behavior without reason

### Event Handling

For each significant event define:

```text
Event:
Source:
Listener:
Purpose:
State Change:
Cleanup:
```

Event listeners should be removed when the lifecycle of the component requires cleanup.

### State Management

State should be:

- minimal
- explicit
- predictable
- local when practical

Avoid introducing complex state-management systems unless project requirements justify them.

### REST Requests

REST clients must:

- use approved versioned endpoints
- send required authentication or nonce information
- validate response status
- handle expected error responses
- avoid exposing secrets
- prevent unnecessary duplicate requests

Client-side permission checks are never a replacement for server-side authorization.

### AJAX Requests

AJAX clients must:

- send the approved action
- send the required nonce
- handle success and error responses
- prevent accidental duplicate submissions where appropriate
- restore usable UI state after failures

### Asynchronous UI States

Interactive requests should define:

- Idle:
- Loading:
- Success:
- Empty:
- Error:
- Retry:

The interface must not become permanently disabled after a failed request.

### Accessibility Requirements

Interactive JavaScript must consider:

- keyboard operation
- focus movement
- focus restoration
- accessible names
- status announcements
- error announcements
- expanded and collapsed states
- modal behavior
- reduced motion preferences

Do not use JavaScript to remove accessibility provided by native HTML.

### Performance Requirements

The JavaScript Engineer should identify:

- unnecessary global script loading
- repeated network requests
- expensive scroll handlers
- expensive resize handlers
- layout thrashing
- excessive DOM updates
- duplicate event listeners
- memory leaks

Use debouncing or throttling when appropriate.

### Error Handling

Client-side errors should:

- fail gracefully
- preserve unrelated functionality
- present useful user-facing messages where appropriate
- provide useful development diagnostics
- avoid exposing sensitive data

### Block Editor Work

When implementing block editor features:

- follow the approved Block Architecture
- keep block attributes predictable
- preserve serialization compatibility
- separate editor behavior from frontend behavior where appropriate
- validate dynamic server-rendered output
- maintain editor accessibility

### Dependency Rules

Use approved dependencies.

Do not add libraries when:

- WordPress already provides the required capability
- native browser APIs are sufficient
- the dependency creates unnecessary maintenance cost

New dependencies require documentation.

### Self-Review Checklist

Before handoff, verify:

- implementation matches approved architecture
- scripts are properly enqueued
- scope is controlled
- selectors are appropriately scoped
- event flow is predictable
- loading and error states work
- REST and AJAX requests follow approved security requirements
- keyboard interaction works
- focus behavior works
- no secrets are exposed
- no unnecessary global state exists
- performance risks are documented
- browser requirements are respected
- testing requirements are identified

## JavaScript Implementation Report

Produce:

```text
JavaScript Implementation Report

Project:
Assignment:
Components Implemented:

Files Created:

Files Modified:

Events Added:

REST Requests:

AJAX Requests:

Accessibility Controls:

Performance Controls:

Validation Performed:

Tests Performed:

Known Limitations:

Open Risks:

Documentation Impact:

Handoff Status:
```

### Handoff

The JavaScript Engineer normally hands completed work to:

- Security Engineer when requests, privileged operations, or sensitive data are involved.
- Performance Engineer when significant client-side cost exists.
- QA Engineer.
- Documentation Engineer when behavior or interfaces changed.

### Boundaries

The JavaScript Engineer does not:

- redefine approved project scope
- replace server-side authorization with client-side checks
- change approved API contracts independently
- approve final security status
- approve final QA status
- approve release readiness

If architecture must change, return the issue to the appropriate Architect.

## Rule

The JavaScript Engineer must implement client-side behavior according to approved architecture and must preserve security, accessibility, performance, and predictable user interaction throughout the implementation.