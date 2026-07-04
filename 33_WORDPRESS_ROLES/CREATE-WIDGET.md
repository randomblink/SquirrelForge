# SquirrelForge WordPress Create Widget Skill

## Purpose

This Skill defines the controlled workflow for creating a safe WordPress widget or sidebar feature.

---

## Workflow

1. Identify widget purpose.
2. Define widget name, ID, class, and settings.
3. Verify parent plugin or theme architecture.
4. Route roles through the Role Manager.
5. Implement widget PHP.
6. Add CSS or JavaScript only when required.
7. Validate security.
8. Run QA.
9. Document usage.
10. Produce final report.

---

## Standard Route

```text
Role Manager
↓
PHP Engineer
↓
CSS Engineer when required
↓
JavaScript Engineer when required
↓
Security Engineer
↓
QA Engineer
↓
Documentation Engineer
```
### Required Planning Output
```text
Widget Plan

Name:
ID:
Class:
Purpose:
Owning Plugin or Theme:
Settings:
Sanitization:
Frontend Output:
Assets:
Security Requirements:
Testing:
```
### Security Gates

Every widget must:

- sanitize saved settings
- escape frontend output
- avoid exposing private data
- avoid unsafe raw HTML unless explicitly allowed and filtered
### Testing Gates

Verify:

- widget registers
- widget appears in widget area
- settings save
- frontend output renders
- invalid input fails safely
- output is escaped
## Rule

SquirrelForge must not create a widget that stores unsanitized settings or outputs unescaped dynamic data.