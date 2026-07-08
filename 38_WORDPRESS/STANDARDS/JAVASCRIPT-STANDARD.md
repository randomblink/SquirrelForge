Status: Stable

---
# SquirrelForge WordPress JavaScript Standard

## Purpose

This document defines the JavaScript standards SquirrelForge must follow when generating or reviewing JavaScript for WordPress plugins, themes, block editor integrations, and admin interfaces.

The goal is to produce JavaScript that is secure, maintainable, performant, and compatible with the supported WordPress environment.

---

# Design Principles

JavaScript should be:

- Modular
- Predictable
- Maintainable
- Accessible
- Performant
- Progressive
- Easy to debug

---

# Preferred Architecture

SquirrelForge prefers:

- Small focused modules
- One responsibility per file
- Event-driven design
- Minimal global state
- Reusable utility functions

Avoid large monolithic scripts.

---

# File Organization

Typical structure:

```text
assets/
├── js/
│   ├── admin.js
│   ├── public.js
│   ├── editor.js
│   ├── utilities.js
│   └── components/
```

Large projects may organize by feature.

---

# Loading Rules

Scripts must be loaded through WordPress.

Preferred functions:

- `wp_enqueue_script()`
- `wp_register_script()`

Do not hardcode `<script>` tags in templates unless explicitly required.

---

# Scope Rules

Avoid polluting the global namespace.

Prefer:

- ES modules (when supported by the project)
- Closures
- Namespaced objects

Avoid creating unnecessary global variables.

---

# DOM Access

When interacting with the DOM:

- Cache repeated selectors where appropriate.
- Verify elements exist before use.
- Avoid unnecessary DOM queries.
- Prefer event delegation for dynamic content.

---

# WordPress Integration

JavaScript should integrate with WordPress using supported APIs.

Examples include:

- localized script data
- REST API
- Heartbeat API
- Block Editor APIs
- Media Library APIs

Avoid relying on undocumented internal behavior.

---

# AJAX

AJAX requests must:

- include nonce verification
- validate responses
- handle failures gracefully
- avoid duplicate requests

---

# REST API

REST requests should:

- use versioned endpoints
- handle authentication correctly
- validate responses
- retry only when appropriate

---

# Accessibility

Interactive components must support:

- keyboard navigation
- focus management
- screen readers
- visible state changes
- reduced motion preferences where applicable

---

# Performance

JavaScript should:

- load only where required
- defer expensive work
- debounce or throttle frequent events
- minimize layout thrashing
- avoid repeated network requests
- clean up event listeners

---

# Error Handling

JavaScript should:

- fail gracefully
- avoid breaking unrelated functionality
- log meaningful development errors
- avoid exposing sensitive information

---

# Browser Compatibility

Generated code should target the browsers defined by the project requirements.

Polyfills should only be added when necessary.

---

# Security

JavaScript must never:

- trust user input
- expose secrets
- disable security validation
- bypass server-side authorization

Server-side validation is always authoritative.

---

# Documentation

Complex modules should document:

- purpose
- dependencies
- events
- public functions
- expected inputs
- expected outputs

---

# Forbidden Patterns

SquirrelForge must reject JavaScript that:

- creates unnecessary globals
- hardcodes credentials
- performs insecure AJAX requests
- ignores accessibility
- leaks memory
- contains dead code
- duplicates functionality already provided by WordPress

---

# Review Checklist

Verify:

- scripts are properly enqueued
- scope is controlled
- accessibility is maintained
- errors are handled
- requests are secure
- performance is acceptable
- documentation is present where needed

---

# Rule

All generated JavaScript must be modular, secure, accessible, performant, and integrated with WordPress through supported APIs.
