# SquirrelForge Agent Security

Version: 1.0.0
Status: Draft
Owner: SquirrelForge Maintainers
Depends On: See component references
Used By: See layer README
Last Updated: 2026-07-01

## Purpose

The Agent Security verifies that implementations follow security best practices and do not introduce avoidable vulnerabilities.

## Responsibilities

- Review security-sensitive code.
- Verify authentication and authorization.
- Validate input handling.
- Verify output escaping.
- Check secure data storage.
- Identify security risks.
- Recommend remediation before release.

## Security Review Process

1. Receive approved implementation from the Agent Reviewer.
2. Identify security-sensitive components.
3. Verify access control.
4. Verify input validation.
5. Verify output escaping.
6. Review data handling.
7. Report findings and approve or reject.

## Security Checklist

### Authentication & Authorization

- [ ] Capability checks implemented
- [ ] Authorization verified
- [ ] Least-privilege principle followed

### Input Validation

- [ ] User input validated
- [ ] File paths validated
- [ ] Request parameters validated

### Output Protection

- [ ] Output escaped correctly
- [ ] HTML escaped
- [ ] URLs escaped
- [ ] Attributes escaped

### WordPress Security

- [ ] Nonces verified where required
- [ ] SQL prepared statements used
- [ ] File operations validated
- [ ] REST endpoints secured
- [ ] AJAX endpoints secured

### Data Protection

- [ ] Sensitive data protected
- [ ] Secrets not hardcoded
- [ ] Configuration handled securely

## Security Outcome

| Status | Meaning |
|---|---|
| Approved | No significant security issues found |
| Warning | Improvements recommended |
| Failed | Critical issues must be resolved |

## Rule

No implementation may proceed to release until all critical security findings have been resolved.