Status: Stable

---
# SquirrelForge WordPress Testing Standard

## Purpose

This document defines the testing standards required before SquirrelForge approves any WordPress plugin, theme, block, or supporting component.

Testing verifies that generated code is functionally correct, secure, performant, maintainable, and compliant with WordPress standards.

---

## Relationship to the General Testing Layer

`29_TESTING` defines SquirrelForge's platform-wide test categories (Unit, Integration, System, Smoke, Regression), test planning, and test reporting. This standard does not replace, redefine, or duplicate those categories — it defines the WordPress-specific validation dimensions and checklists that WordPress work must additionally satisfy, and states how each dimension maps onto the general categories.

WordPress work must satisfy both layers: `29_TESTING` owns how tests are planned, executed, and reported; this standard owns which WordPress-specific dimensions and checklists must be covered. Satisfying one does not substitute for the other.

### Mapping Table

| General Term (`29_TESTING`) | WordPress Term (this standard) | Relationship | Owner | When It Applies |
|---|---|---|---|---|
| `UNIT-TESTS.md` | (no dedicated Level; supports Level 3) | Nested — WordPress functional correctness is partly verified through general unit tests of isolated PHP logic | `29_TESTING/UNIT-TESTS.md` | Isolated WordPress functions, classes, or services |
| `INTEGRATION-TESTS.md` | Level 4 — Integration Testing | Equivalent — WordPress's integration dimension (Core, themes, plugins, WooCommerce, REST, Media Library, Cron, WP-CLI) is the WordPress-scoped instance of general integration testing | `29_TESTING/INTEGRATION-TESTS.md` owns execution; this standard owns which WordPress boundaries must be covered | Any WordPress work crossing a real interface or component boundary |
| `SYSTEM-TESTS.md` | (no dedicated Level; supported by the Plugin/Theme Testing Checklists) | Nested — the Plugin and Theme Testing Checklists' end-to-end flows (clean install, activation, homepage render, and similar) are WordPress-scoped system-test scenarios | `29_TESTING/SYSTEM-TESTS.md` | End-to-end WordPress scenarios in a representative environment |
| `SMOKE-TESTS.md` | (no dedicated Level; used as an execution strategy) | Overlapping, not equivalent — a WordPress activation/deactivation smoke check gates further testing; it does not replace Level 2 or Level 3 | `29_TESTING/SMOKE-TESTS.md` | Before deeper validation of a candidate plugin, theme, or build |
| `REGRESSION-TESTS.md` | Level 6 — Regression Testing | Equivalent — same concept, applied to WordPress-specific behavior (existing settings, migrations, saved data, compatibility) | `29_TESTING/REGRESSION-TESTS.md` owns execution; this standard owns which WordPress behavior must be re-verified | After any change to existing WordPress behavior |
| (no equivalent — quality gate, not a test category) | Level 1 — Static Validation | Distinct — a WordPress code-quality gate (syntax, structure, naming, documentation completeness), not a `29_TESTING` test category | This standard | Every WordPress code change, before functional testing |
| (no equivalent — quality gate, not a test category) | Level 2 — Security Validation | Distinct — a WordPress security quality gate owned by `38_WORDPRESS/SECURITY-VALIDATOR.md`; it may be exercised through Unit or Integration test cases but is not itself a `29_TESTING` category | `38_WORDPRESS/SECURITY-VALIDATOR.md` | Every WordPress change touching input, output, permissions, database access, uploads, or secrets |
| Evidence from Unit, Integration, and System Tests | Level 3 — Functional Testing | Overlapping — Functional Testing is a validation dimension satisfied jointly by Unit, Integration, and System test evidence, not a separate execution category | This standard defines the dimension; `29_TESTING` components supply the evidence | Every WordPress feature |
| (no equivalent — distinct from System Tests) | Level 5 — User Experience Testing | Distinct from System Tests even when both occur in a browser — System Tests verify end-to-end functional and data-flow correctness; User Experience Testing verifies usability, accessibility, and presentation | This standard | Any WordPress work with an admin or frontend UI surface |

### Clarifying Examples

- Unit tests can support Functional and Regression validation; they are not themselves Level 3 or Level 6.
- Integration tests can satisfy both the general Integration category and Level 4 — Integration Testing at once; they are the same activity, not two separate test runs.
- Smoke tests are an execution strategy that gates further testing. A passing smoke test does not satisfy Level 2 (Security Validation) or Level 3 (Functional Testing).
- Static analysis (Level 1) and Security Validation (Level 2) are WordPress quality gates, not PHPUnit test types; they may run alongside `29_TESTING` categories but are not one of them.
- User Experience Testing (Level 5) is distinct from System Tests even when both occur in a browser: System Tests verify behavior, User Experience Testing verifies usability and presentation.
- Regression is required under both vocabularies: `29_TESTING/REGRESSION-TESTS.md` owns how regression tests are executed; Level 6 owns which WordPress-specific behavior must be covered.

---

## Testing Philosophy

Every generated project should be verified at multiple levels:

1. Static Review
2. Security Validation
3. Functional Testing
4. Integration Testing
5. User Acceptance Testing
6. Regression Testing

Testing should occur before deployment, not after.

---

## Testing Levels

Testing levels define the minimum validation depth required for WordPress work.

Each level must be selected according to task risk, affected surface area, and production impact.

Higher-risk changes require multiple validation levels before completion.

## Level 1 — Static Validation

Verify:

- PHP syntax
- JavaScript syntax
- CSS syntax
- File structure
- Naming conventions
- Documentation completeness

---

## Level 2 — Security Validation

Verify:

- input sanitization
- output escaping
- nonce validation
- capability checks
- prepared SQL
- upload validation
- REST permissions
- AJAX permissions
- secret management

Reference:

```
SECURITY-VALIDATOR.md
```

---

## Level 3 — Functional Testing

Verify the requested functionality behaves correctly.

Examples:

- settings save correctly
- forms submit correctly
- REST endpoints return expected responses
- shortcodes render correctly
- blocks function correctly
- widgets behave correctly

---

## Level 4 — Integration Testing

Verify interaction with:

- WordPress Core
- Themes
- Other plugins
- WooCommerce
- REST API
- Media Library
- Cron
- WP-CLI (when applicable)

---

## Level 5 — User Experience Testing

Verify:

- navigation
- responsive layout
- accessibility
- error messages
- loading indicators
- admin usability

---

## Level 6 — Regression Testing

Confirm existing functionality still works after changes.

Verify:

- previous features
- existing settings
- migrations
- saved data
- compatibility

---

## Plugin Testing Checklist

Verify:

- activation succeeds
- deactivation succeeds
- uninstall behaves correctly
- admin pages load
- settings persist
- assets load correctly
- cron events register
- cron events clean up
- REST endpoints work
- AJAX requests work
- permissions are enforced

---

## Theme Testing Checklist

Verify:

- theme activates
- homepage renders
- archives render
- search works
- menus function
- widgets display
- responsive layout
- block editor compatibility
- accessibility

---

## Performance Testing

Verify:

- assets load only when required
- database queries are efficient
- caching is effective
- no duplicate requests
- no unnecessary cron jobs
- acceptable page load impact

---

## Browser Testing

Test supported browsers defined by project requirements.

Minimum expectation:

- Chrome
- Firefox
- Safari
- Edge

---

## Error Testing

Verify:

- invalid input
- permission failures
- expired nonces
- REST failures
- AJAX failures
- missing dependencies

Applications should fail gracefully.

---

## Logging

Testing should record:

- test name
- result
- timestamp
- environment
- tester
- notes

---

## Approval States

| Status | Meaning |
|--------|---------|
| Pass | Ready for approval |
| Pass with Warnings | Minor issues documented |
| Fail | Must not ship |

---

## Final Testing Report

Each completed task should produce:

```text
Testing Summary

Environment:
WordPress Version:
PHP Version:
Theme:
Plugin Version:

Static Validation:
Security Validation:
Functional Tests:
Integration Tests:
Performance Tests:
Accessibility Tests:

Issues Found:

Final Status:
```

---

## Rule

No WordPress project may be approved until all required testing levels have been completed or explicitly waived with documented justification.
