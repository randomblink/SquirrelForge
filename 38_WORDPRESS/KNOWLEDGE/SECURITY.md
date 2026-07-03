# SquirrelForge WordPress Knowledge: Security

## Knowledge Metadata

| Field | Value |
|---|---|
| Domain | WordPress |
| Topic | Application Security |
| Applies To | Plugins, themes, blocks, REST endpoints, AJAX handlers, scheduled tasks, and integrations |
| Primary Authority | WordPress security APIs and SquirrelForge security policy |
| Security Priority | Critical |
| Review Trigger | WordPress security changes, new vulnerability classes, or platform policy changes |

## Purpose

This document defines the authoritative security knowledge that SquirrelForge must apply when designing, generating, reviewing, or approving WordPress code.

Its purpose is to prevent unauthorized access, cross-site request forgery, cross-site scripting, SQL injection, privilege escalation, unsafe file handling, secret exposure, insecure integrations, and other avoidable vulnerabilities.

## Scope

This guidance covers:

- Authentication and authorization boundaries.
- Capability checks.
- Nonce creation and verification.
- Input validation and sanitization.
- Output escaping.
- Database access.
- REST API and AJAX security.
- File and upload handling.
- Options and secret handling.
- Cron and background processing.
- Error handling and security logging.
- Dependency and supply-chain awareness.

This document establishes minimum controls. Project-specific policies may add stricter requirements but must not weaken them.

## Core Principle

Never trust data merely because it came from a user, administrator, database, API, file, browser, scheduled event, or another plugin.

Every protected operation must answer three questions:

1. **Authorization:** Is this identity allowed to perform the action?
2. **Input Safety:** Is the supplied data valid and sanitized for its intended use?
3. **Output Safety:** Is the resulting data escaped for its destination context?

Nonces verify request intent; they do not replace capability checks.

## Required Practices

### Authorization and Capabilities

- Use `current_user_can()` before privileged operations.
- Select the narrowest capability that represents the action.
- Enforce authorization in the execution handler, not only in the user interface.
- Never infer permission from a hidden field, URL, menu visibility, or nonce alone.
- Apply object-level authorization when an operation targets a specific post, user, term, order, or other resource.

### Nonces and Request Integrity

Use nonces for state-changing browser requests.

- Forms: create with `wp_nonce_field()` and verify with `check_admin_referer()` or `wp_verify_nonce()`.
- AJAX: create with `wp_create_nonce()` and verify with `check_ajax_referer()`.
- URLs: create with `wp_nonce_url()` and verify before mutation.

Reject missing, expired, or invalid nonces. Nonces must use action names specific to the protected operation.

### Validation and Sanitization

Validate values against business rules before use. Sanitize values according to type before storage or processing.

| Data | Typical Handling |
|---|---|
| Plain text | `sanitize_text_field()` |
| Multiline text | `sanitize_textarea_field()` |
| Email | `sanitize_email()` plus validity checks |
| URL for storage | `esc_url_raw()` |
| Key or slug | `sanitize_key()` |
| Positive integer | `absint()` |
| File name | `sanitize_file_name()` |
| Enumerated value | Strict allowlist comparison |

For request superglobals, account for WordPress slashing before sanitization. Sanitization does not replace validation of ranges, ownership, state transitions, or allowed values.

### Output Escaping

Escape late, immediately before output.

| Output Context | Required Handling |
|---|---|
| HTML text | `esc_html()` |
| HTML attribute | `esc_attr()` |
| URL | `esc_url()` |
| JavaScript data | `wp_json_encode()` and safe script APIs |
| Allowed rich HTML | `wp_kses()` or `wp_kses_post()` with an appropriate policy |

Do not use sanitization functions as substitutes for output escaping.

### Database Security

- Use WordPress data APIs when they satisfy the requirement.
- Use `$wpdb->prepare()` for dynamic values in custom SQL.
- Treat identifiers such as table names and sort columns separately; validate them against strict allowlists.
- Never concatenate untrusted values into SQL.
- Apply least-privilege database access.
- Avoid exposing database errors to visitors.

### REST API Security

Every REST route must define:

- A `permission_callback`.
- Argument schemas where practical.
- Validation and sanitization callbacks.
- Object-level authorization for protected resources.
- A deliberate response schema that excludes internal or sensitive fields.

Public endpoints must use an explicit public permission callback rather than omitting authorization design.

### AJAX Security

Every privileged AJAX handler must:

1. Verify the nonce.
2. Verify the required capability.
3. Validate and sanitize input.
4. Perform the authorized operation.
5. Return a structured response through approved WordPress response helpers.

Unauthenticated AJAX actions must be explicitly justified and rate or abuse controls considered.

### Files and Uploads

- Verify upload authorization.
- Validate file type and extension using server-side controls.
- Use WordPress filesystem and upload APIs where appropriate.
- Generate safe destination paths and file names.
- Prevent path traversal and executable uploads.
- Do not trust browser-provided MIME types or names.
- Protect private files from direct public access.

### Secrets and Sensitive Configuration

- Never commit passwords, API keys, tokens, signing keys, or private certificates.
- Do not expose secrets in HTML, REST responses, logs, exceptions, or diagnostics.
- Use approved environment or secret-management facilities.
- Encrypt sensitive stored values when policy requires it.
- Provide rotation and revocation procedures.

### Scheduled and Background Work

- Prevent duplicate scheduling.
- Validate persisted job inputs before execution.
- Recheck authorization or trusted execution context where appropriate.
- Apply timeouts, retry limits, idempotency, and failure logging.
- Remove project-owned schedules during deactivation or uninstall when required.

### Errors, Logging, and Diagnostics

- Show generic failure messages to untrusted users.
- Restrict detailed diagnostics to authorized development or operational contexts.
- Log security-relevant failures with timestamp, component, severity, correlation data, and remediation guidance.
- Redact credentials, tokens, personal information, and sensitive payloads.
- Preserve evidence required for investigation without exposing it publicly.

## Standard Workflow

1. Identify assets, entry points, identities, trust boundaries, and protected operations.
2. Classify data and required permissions.
3. Map each input to validation and sanitization rules.
4. Map each output to its escaping context.
5. Verify capability and nonce controls for state changes.
6. Review database, REST, AJAX, file, cron, and integration behavior.
7. Scan for committed secrets and unsafe dependencies.
8. Test authorized, unauthorized, malformed, replayed, and failure cases.
9. Record findings, severity, affected files, and remediation.
10. Approve only when critical controls pass or a documented exception is accepted by authorized governance.

## Security Requirements

- Deny protected operations by default.
- Apply least privilege to users, services, integrations, and data access.
- Keep security checks close to the protected operation.
- Use WordPress core security APIs instead of custom cryptography or request-token schemes unless formally reviewed.
- Preserve backward compatibility only when it does not preserve a known vulnerability.
- Treat third-party code and external responses as untrusted.
- Ensure security controls remain observable and auditable.
- Never weaken controls to simplify implementation or improve apparent performance.

## Validation Checklist

- [ ] Every privileged operation checks an appropriate capability.
- [ ] Every state-changing browser request verifies a specific nonce.
- [ ] Every input has explicit validation and sanitization.
- [ ] Every output is escaped for its exact context.
- [ ] Dynamic SQL values use safe query preparation.
- [ ] Dynamic identifiers use strict allowlists.
- [ ] REST routes define deliberate permission callbacks and argument handling.
- [ ] AJAX handlers enforce nonce, capability, and input controls.
- [ ] Uploads validate authorization, type, extension, name, and destination.
- [ ] File paths cannot escape approved directories.
- [ ] Secrets are absent from source, output, logs, and diagnostics.
- [ ] Scheduled work is idempotent, bounded, and removable.
- [ ] Error messages do not disclose sensitive details.
- [ ] Security events produce safe, actionable records.
- [ ] Dependencies and integrations have been reviewed for trust and update risk.

## Common Failure Conditions

SquirrelForge must reject WordPress code that:

- Omits capability checks for privileged actions.
- Uses nonces as authorization.
- Omits nonce verification for state-changing browser requests.
- Trusts or stores raw request input.
- Outputs unescaped dynamic data.
- Concatenates untrusted data into SQL.
- Registers unrestricted REST or AJAX operations without explicit justification.
- Permits unsafe or executable uploads.
- Accepts user-controlled file paths without containment checks.
- Commits or exposes credentials and tokens.
- Displays stack traces, SQL errors, secrets, or protected paths to visitors.
- Disables security controls to resolve compatibility or performance problems.

## Related Knowledge

- `SETTINGS-API.md`
- `../SECURITY-VALIDATOR.md`
- `../CODING-STANDARDS.md`
- `../DATABASE.md`
- `../REST-API.md`
- `../PLUGIN-HANDBOOK.md`
- `../THEME-HANDBOOK.md`

## Rule

No WordPress component may be approved until its authorization, request integrity, input handling, output escaping, data access, secret handling, failure behavior, and security observability have passed validation.
