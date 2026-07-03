# SquirrelForge WordPress Security Validator

## Purpose

The Security Validator ensures that all WordPress plugins, themes, and supporting components generated or reviewed by SquirrelForge follow WordPress security best practices before they are approved.

The validator is intended to prevent common vulnerabilities including unauthorized access, cross-site scripting (XSS), SQL injection, cross-site request forgery (CSRF), privilege escalation, insecure file handling, and unsafe data storage.

---

# Responsibilities

The Security Validator shall:

- Validate user capability checks.
- Validate nonce creation and verification.
- Validate sanitization of incoming data.
- Validate escaping of outgoing data.
- Validate database access.
- Validate REST API permissions.
- Validate AJAX security.
- Validate file upload safety.
- Validate cron safety.
- Validate option storage.
- Validate API key handling.
- Produce a security report.

---

# Security Validation Workflow

1. Scan generated code.
2. Identify user input.
3. Verify sanitization.
4. Verify escaping.
5. Verify permission checks.
6. Verify nonce usage.
7. Verify SQL safety.
8. Verify REST permissions.
9. Verify upload validation.
10. Produce approval status.

---

# Input Validation Rules

Every value entering the system must be validated.

Examples include:

- Form submissions
- Settings pages
- AJAX requests
- REST requests
- URL parameters
- Cookies
- Uploaded files
- Imported data

---

# Sanitization Rules

Incoming values must be sanitized before use.

Examples include:

| Data Type | Example Function |
|------------|------------------|
| Text | `sanitize_text_field()` |
| Textarea | `sanitize_textarea_field()` |
| Email | `sanitize_email()` |
| URL | `esc_url_raw()` |
| Integer | `(int)` or `absint()` |
| Float | `floatval()` |
| Key | `sanitize_key()` |
| File name | `sanitize_file_name()` |

---

# Output Escaping Rules

All output must be escaped immediately before rendering.

Examples include:

| Output Type | Example Function |
|-------------|------------------|
| HTML | `esc_html()` |
| Attribute | `esc_attr()` |
| URL | `esc_url()` |
| JavaScript | `wp_json_encode()` where appropriate |
| Rich HTML | `wp_kses_post()` |

---

# Nonce Requirements

Whenever data changes occur:

The validator must verify:

- nonce creation
- nonce verification
- rejection of invalid requests

Examples include:

- Settings forms
- Delete actions
- AJAX requests
- Bulk actions

---

# Capability Checks

Administrative operations must verify permissions.

Examples include:

```php
current_user_can()
```

The validator must reject:

- unrestricted admin pages
- unrestricted AJAX endpoints
- unrestricted REST endpoints

---

# Database Rules

Database operations must:

- use `$wpdb->prepare()` where applicable
- validate table names
- sanitize values
- avoid direct SQL concatenation

The validator rejects:

- raw SQL using user input
- unsanitized queries
- dynamic SQL without validation

---

# REST API Validation

REST endpoints must define:

- permission callback
- argument validation
- argument sanitization

Endpoints without permission callbacks fail validation.

---

# AJAX Validation

AJAX handlers must verify:

- nonce
- capability
- sanitization
- response handling

---

# File Upload Validation

Uploads must verify:

- MIME type
- file extension
- upload permissions
- destination path

Executable uploads must never be permitted unless explicitly required and separately approved.

---

# API Credentials

The validator rejects:

- hardcoded passwords
- hardcoded API keys
- hardcoded access tokens
- committed secrets

Secrets should instead come from:

- WordPress options
- environment variables
- secure configuration

---

# Cron Validation

Scheduled tasks must:

- avoid duplicate scheduling
- validate inputs
- handle failures gracefully
- support cleanup during uninstall

---

# Error Handling

Sensitive information must never be displayed to visitors.

Debug information must be limited to development environments.

---

# Logging Rules

Security failures should log:

- validation type
- component
- timestamp
- severity
- remediation recommendation

Sensitive data must never be written to logs.

---

# Security Report

Each validation produces:

- Pass
- Pass with Warnings
- Failed

The report should include:

- issues found
- affected files
- severity
- recommended fixes
- approval status

---

# Critical Failure Conditions

SquirrelForge must reject WordPress code that:

- lacks nonce verification
- lacks capability checks
- outputs unescaped user data
- stores unsanitized input
- exposes secrets
- performs unsafe SQL
- allows unrestricted uploads
- exposes unrestricted REST endpoints
- exposes unrestricted AJAX handlers

---

# Rule

No WordPress code may be approved until all critical security checks pass or documented exceptions have been explicitly reviewed and accepted.
