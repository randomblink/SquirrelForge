Status: Stable

---
# SquirrelForge WordPress Engineering Error Classifications

## Purpose

This document is the WordPress Knowledge layer's catalog of standardized WordPress engineering error classifications. Each entry defines a reproducible failure mode with a stable identifier, its engineering meaning, deterministic detection criteria, diagnosis steps, typical fixes, prevention guidance, evidence requirements, and resolution-verification criteria.

This catalog exists to give diagnostic work (most directly, `38_WORDPRESS/SKILLS/DEBUG-PLUGIN.md`) a shared, reusable vocabulary for classifying WordPress runtime failures — distinct from a specific plugin's own defect, a specific scenario's own evidence, or a general PHP error reference. It is deliberately started with one entry; additional entries are added only as concrete, reproducible classifications are needed, not built out as a speculative framework in advance.

## Rule

An error classification in this catalog is a reusable diagnostic reference, not a record of any specific incident. Scenario-specific defects, fixes, and evidence remain in their own scenario documents; this catalog is cited by them, not merged with them.

---

## WP-PHP-001 — Call to Undefined Function

### Metadata

| Field | Value |
|---|---|
| Error ID | WP-PHP-001 |
| Title | Call to Undefined Function |
| Status | Stable |
| Category | PHP Runtime |
| Subcategory | WordPress Bootstrap / API Availability |
| Severity | Critical |

**Applies To:** WordPress Core, Plugins, Themes, Must-use plugins, WP-CLI, AJAX callbacks, REST callbacks, Cron execution.

### Typical Runtime Message

```text
Fatal error: Uncaught Error:
Call to undefined function function_name()
```

Examples:

```text
Call to undefined function is_plugin_active()
```

```text
Call to undefined function register_block_type()
```

```text
Call to undefined function wp_get_environment_type()
```

### Engineering Meaning

Execution attempted to invoke a PHP function that was unavailable in the current runtime context.

Within WordPress, this commonly means the required API file had not been loaded, execution occurred before the appropriate lifecycle hook, a dependency was unavailable, the installed WordPress version did not provide the function, or the function or namespace reference was incorrect.

### Immediate Cause

PHP attempted to call a function that was not defined in the current execution context.

### Common Engineering Root Causes

- Required WordPress file not loaded
- Missing or incorrect `require_once`
- Function called before WordPress initialization completed
- Admin-only API called when its defining file was unavailable
- Incorrect WordPress hook timing
- Required plugin dependency inactive or unavailable
- Incorrect function name
- Namespace mismatch
- Conditional include not executed
- Unsupported WordPress version
- Test or WP-CLI bootstrap incomplete

### Typical WordPress Contexts

- Plugin activation
- Plugin bootstrap
- Theme initialization
- Must-use plugin loading
- REST endpoint registration or callback execution
- AJAX callbacks
- Cron callbacks
- WP-CLI execution
- Admin-only APIs
- Integration tests with incomplete bootstrap

### Symptoms

- Plugin activation fails
- White Screen of Death
- HTTP 500 response
- Fatal PHP runtime error
- WP-CLI command terminates
- Theme or plugin initialization stops
- AJAX, REST, or cron request fails
- Unit or integration test terminates unexpectedly

### Deterministic Detection

Classify the failure as `WP-PHP-001` when:

1. PHP reports `Call to undefined function`.
2. The missing function name is identifiable.
3. The source call location is identifiable.
4. Execution terminates or the requested operation fails because of the unavailable function.

Do not classify the error solely from a blank page or HTTP 500 response without the underlying PHP message.

### Diagnosis

1. Capture the complete fatal error.
2. Capture the full stack trace.
3. Identify the missing function.
4. Identify the source file and line that called it.
5. Confirm whether the function exists in the installed WordPress version.
6. Identify the core, plugin, theme, or dependency file that defines it.
7. Confirm whether that file was loaded.
8. Inspect the current WordPress hook or bootstrap phase.
9. Check plugin and theme dependency state.
10. Check namespace resolution.
11. Confirm PHP and WordPress versions.
12. Reproduce the failure in the smallest deterministic environment available.

### Typical Fixes

- Load the required WordPress file before invoking the function.
- Move execution to the correct WordPress lifecycle hook.
- Correct the function name.
- Correct namespace qualification.
- Validate and load the required plugin dependency.
- Add an explicit version compatibility check.
- Correct incomplete WP-CLI or test bootstrap behavior.
- Replace an unavailable API only when compatibility requirements justify doing so.

### Prevention

- Follow WordPress bootstrap and hook ordering.
- Call APIs only after they are available.
- Validate required plugin dependencies before invocation.
- Test plugin activation paths.
- Test frontend, admin, REST, AJAX, cron, and WP-CLI paths where applicable.
- Maintain supported WordPress-version requirements.
- Use `function_exists()` only for genuine optional compatibility — not to hide a programming error or broken bootstrap.

### Related WordPress APIs and Concepts

- `require_once`
- `function_exists()`
- `did_action()`
- `add_action()`
- `plugins_loaded`
- `init`
- `admin_init`
- `is_plugin_active()`
- Plugin activation hooks
- WordPress bootstrap order
- Dependency validation

### Evidence Classification

**Primary Evidence Category:** Runtime Evidence

**Required Evidence:**

- Complete PHP fatal message
- Missing function name
- Source file and line number
- Stack trace
- WordPress version
- PHP version
- Runtime context or current hook
- Reproduction steps

**Supporting Evidence:**

- Active plugin list
- Active theme
- Dependency state
- Loaded file inspection
- Relevant source excerpt
- Test or runtime command output

### Resolution Verification

Resolution is confirmed when:

- The fatal error no longer occurs.
- The function is available at the point of invocation.
- The original operation completes.
- Plugin activation succeeds when activation was affected.
- Relevant runtime or integration tests pass.
- A regression test covers the failure path when appropriate.
- No new warning, notice, deprecated message, database error, or fatal error is introduced.

### Automation Guidance

A diagnostic agent handling `WP-PHP-001` should:

1. Parse the missing function name from the fatal error.
2. Locate the expected function definition.
3. Determine the expected bootstrap phase or dependency.
4. Compare expected availability with the actual call context.
5. Identify whether the cause is loading order, missing include, version compatibility, dependency state, spelling, or namespace resolution.
6. Recommend the smallest corrective action.
7. Require runtime or regression validation before marking the issue resolved.

### Related Error Codes

- `WP-PHP-002` — Class Not Found
- `WP-PHP-003` — Call to Undefined Method
- `WP-HOOK-001` — API Called Before Required Hook
- `WP-DEP-001` — Required Plugin Dependency Missing

These are planned classifications only. No full entry exists for them in this catalog yet; do not treat the identifiers above as documented until an entry is actually added.

### Engineering Evidence

**Evidence Status: Not Yet Demonstrated.**

No completed WP-SCENARIO in this repository currently demonstrates a "Call to undefined function" fatal deterministically and its correction. The closest existing runtime evidence, WP-SCENARIO-002 (`38_WORDPRESS/AGENT-SCENARIO-TESTS.md`, "Runtime Validation — WP-SCENARIO-002"), diagnosed and corrected a different PHP 8 fatal — an uncaught `TypeError` from `array_key_exists()` being called with a non-array second argument — not a missing function. That scenario is not cited here as evidence for `WP-PHP-001`, since it demonstrates a different classification. This entry will be updated with a scenario identifier, evidence status, and exact supporting section only once a completed scenario actually produces that evidence; this document does not anticipate or assume one.

---

## Rule

A WordPress engineering error classification in this catalog must be added only when its deterministic detection criteria, diagnosis steps, and evidence requirements can be stated precisely enough to be checked, not merely described in general terms.
