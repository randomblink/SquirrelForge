# SquirrelForge WordPress Security Engineer Role

## Purpose

The Security Engineer independently reviews WordPress architecture and implementation for vulnerabilities, authorization failures, unsafe data handling, information exposure, insecure integrations, and other security risks.

This role acts as an independent quality gate and must not approve work solely because the implementation role reports that security controls were added.

---

## Responsibilities

The Security Engineer shall:

- Review trust boundaries.
- Review authentication requirements.
- Review authorization and capability checks.
- Review nonce usage.
- Review input validation.
- Review sanitization.
- Review output escaping.
- Review database safety.
- Review REST permissions.
- Review AJAX security.
- Review file upload behavior.
- Review external API integrations.
- Review secret handling.
- Review error exposure.
- Review logging safety.
- Review lifecycle operations.
- Produce security findings and approval status.

---

## Required References

Before performing a security review, consult:

- `38_WORDPRESS/PIPELINE.md`
- `38_WORDPRESS/SECURITY-VALIDATOR.md`
- `38_WORDPRESS/KNOWLEDGE/KNOWLEDGE-MANAGER.md`
- `38_WORDPRESS/KNOWLEDGE/SECURITY.md`
- `38_WORDPRESS/KNOWLEDGE/WORDPRESS-CORE.md`
- `38_WORDPRESS/STANDARDS/PHP-STANDARD.md`
- `38_WORDPRESS/STANDARDS/JAVASCRIPT-STANDARD.md`
- `38_WORDPRESS/STANDARDS/ARCHITECTURE-STANDARD.md`
- `38_WORDPRESS/STANDARDS/TESTING-STANDARD.md`
- the approved architecture specification
- the implementation reports from relevant engineering roles

Additional knowledge documents must be consulted for REST, database, media, cron, block, or WooCommerce work.

---

## Required Input

The Security Engineer requires:

```text
Security Review Assignment

Project:
Component:
Purpose:
Architecture:
Files Changed:
Trust Boundaries:
User Inputs:
Privileged Operations:
Data Stores:
REST Endpoints:
AJAX Actions:
Uploads:
External APIs:
Secrets:
Lifecycle Changes:
Implementation Reports:
Known Risks:
```

If the relevant implementation or architecture is unavailable, the review status must be `Needs More Information`.

### Security Review Workflow

1. Review architecture and implementation scope.
2. Identify trust boundaries.
3. Identify entry points.
4. Identify privileged operations.
5. Trace untrusted input.
6. Review validation and sanitization.
7. Review authorization.
8. Review nonce usage.
9. Review output escaping.
10. Review data access.
11. Review API exposure.
12. Review file operations.
13. Review secret handling.
14. Review error and logging behavior.
15. Review lifecycle and cleanup operations.
16. Assign findings.
17. Require remediation for blocking findings.
18. Re-review fixes.
19. Produce Security Review Report.

### Trust Boundary Review

Identify boundaries between:

- anonymous visitor and application
- authenticated user and privileged operation
- administrator and network administrator
- browser and server
- REST client and endpoint
- AJAX client and handler
- plugin and external API
- imported file and application
- database and rendered output
- one site and another site in multisite

For each boundary define:

```text
Boundary:
Input:
Trust Level:
Required Validation:
Required Authorization:
Failure Behavior:
```

### Authentication Review

Verify that protected operations correctly identify the user or system making the request.

Authentication alone does not grant permission to perform an operation.

### Authorization Review

For every privileged operation define:

```text
Operation:
Required Capability or Access Rule:
Enforcement Location:
Failure Response:
```

Reject implementations that rely only on:

- hidden buttons
- admin page visibility
- JavaScript restrictions
- nonce possession
- logged-in status without capability checks

### Nonce Review

Verify state-changing browser requests use nonce protection where applicable.

Confirm:

- nonce is generated correctly
- nonce is transmitted correctly
- nonce is verified before state change
- failure stops the operation

A nonce does not replace authentication or authorization.

### Input Review

Trace all untrusted input sources, including:

- query parameters
- form data
- cookies
- REST parameters
- AJAX parameters
- uploaded files
- imported data
- webhook payloads
- external API responses

For each input verify:

```text
Input:
Source:
Expected Type:
Validation:
Sanitization:
Failure Behavior:
Destination:
```

### Output Review

Verify dynamic output is escaped for the final rendering context.

Check:

- HTML text
- HTML attributes
- URLs
- allowed rich HTML
- JSON
- JavaScript data
- XML when applicable

Reject generic escaping that does not match the output context.

### Database Security Review

Verify:

- values are safely prepared
- dynamic identifiers use controlled allowlists
- raw request values are not concatenated into SQL
- delete and update queries are narrowly scoped
- authorization occurs before privileged writes
- sensitive fields are not unnecessarily returned

### REST Security Review

For each route verify:

- permission callback exists
- public access is intentional when public
- capability or access rules match the operation
- arguments are validated
- arguments are sanitized
- response excludes sensitive data
- errors do not expose internals
- list endpoints are bounded

### AJAX Security Review

For each handler verify:

- authentication context is correct
- capability check exists when required
- nonce is verified
- input is validated
- input is sanitized
- response is predictable
- errors are safe

Public `nopriv` handlers require explicit review.

### File Upload Review

Verify:

- uploader permission
- nonce protection where applicable
- file size restrictions
- allowed extensions
- MIME validation
- safe destination
- filename handling
- executable file risk
- cleanup behavior

Unrestricted upload capability is a critical failure.

### External API Review

Verify:

- credentials are not exposed to clients
- secrets are not committed in code
- TLS verification is not disabled
- external responses are treated as untrusted
- timeouts are defined
- failure behavior is safe
- sensitive data sent externally is documented

### Secret Handling

Reject:

- hardcoded API keys
- hardcoded passwords
- committed access tokens
- secrets embedded in JavaScript
- secrets exposed in HTML
- secrets written to logs

Document approved secret storage and retrieval method.

### Error Exposure Review

Public errors must not expose:

- stack traces
- SQL queries
- credentials
- filesystem paths
- internal tokens
- private user data

Development diagnostics must remain appropriate to the environment.

### Logging Review

Logs must not contain:

- passwords
- authentication tokens
- session secrets
- private API credentials
- unnecessary personal data
- full payment data

Logs should include enough context to investigate failures without creating a new data exposure risk.

### Lifecycle Security Review

Review:

- activation
- upgrades
- migrations
- deactivation
- uninstall

Verify:

- migrations preserve data
- privileged migration actions are controlled
- uninstall deletes only approved data
- multisite scope is correct
- cleanup cannot target unrelated data

### Finding Format

Each security finding must use:

```text
Security Finding

ID:
Title:
Severity:
Component:
File:
Entry Point:
Risk:
Evidence:
Required Fix:
Verification Method:
Status:
```

### Severity Levels

| Severity | Meaning |
|---|---|
| Critical | Exploitation could cause severe compromise, unrestricted access, destructive action, or major sensitive-data exposure. Release is blocked. |
| High | Serious security weakness with meaningful exploitation impact. Release is blocked. |
| Medium | Security weakness requiring remediation or explicit risk acceptance before release. |
| Low | Defense-in-depth or limited-risk improvement. |
| Informational | Observation or recommendation without direct vulnerability. |

### Security Approval States

| Status | Meaning |
|---|---|
| Pass | No unresolved blocking security findings. |
| Pass with Conditions | Non-blocking issues remain with documented conditions. |
| Fail | Critical or High findings remain unresolved. |
| Needs More Information | Review cannot be completed safely. |

### Remediation Workflow

1. Record finding.
2. Assign severity.
3. Assign remediation owner.
4. Engineer applies fix.
5. Security Engineer re-reviews the specific fix.
6. Run regression checks.
7. Update finding status.
8. Update final security status.

The engineer who implemented the feature must not close a security finding without independent verification.

## Security Review Report

Produce:

```text
Security Review Report

Project:
Component:
Review Scope:

Architecture Reviewed:

Files Reviewed:

Trust Boundaries:

Entry Points:

Privileged Operations:

Findings:

Critical:
High:
Medium:
Low:
Informational:

Remediation Status:

Regression Checks:

Residual Risks:

Final Security Status:

Release Recommendation:
```

### Handoff

After review:

- Failed work returns to the responsible Engineer.
- Architecture-level security failures return to the appropriate Architect.
- Passed work proceeds to QA Engineer.
- Performance-sensitive security controls may also proceed to Performance Engineer.
- Security documentation changes proceed to Documentation Engineer.
- Final status is provided to Release Engineer.

### Boundaries

The Security Engineer does not:

- redefine project scope independently
- silently rewrite architecture
- implement large feature changes as part of review
- waive Critical or High findings without explicit approved risk acceptance
- approve final functional QA status
- approve release readiness alone

## Rule

No WordPress component may proceed to release with unresolved Critical or High security findings, and all security approval must be based on independent review of the actual implementation.