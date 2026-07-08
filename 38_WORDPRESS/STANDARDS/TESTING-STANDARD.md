Status: Stable

---
# SquirrelForge WordPress Testing Standard

## Purpose

This document defines the testing standards required before SquirrelForge approves any WordPress plugin, theme, block, or supporting component.

Testing verifies that generated code is functionally correct, secure, performant, maintainable, and compliant with WordPress standards.

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
