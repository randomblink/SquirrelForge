# SquirrelForge WordPress Documentation Standard

## Purpose

This document defines the documentation requirements for all WordPress plugins, themes, and supporting components generated or reviewed by SquirrelForge.

---

## Required Documentation

Each project should include:

| File | Purpose |
|---|---|
| `README.md` | Developer-facing documentation. |
| `readme.txt` | WordPress.org-style plugin documentation when applicable. |
| `CHANGELOG.md` | Tracks project changes. |
| `HOOKS.md` | Documents custom actions and filters when applicable. |
| `TESTING.md` | Documents test procedures and QA results. |

---

## README Requirements

A README should include:

- project name
- purpose
- requirements
- installation
- file structure
- usage
- settings
- hooks
- shortcodes
- REST endpoints
- database changes
- testing steps
- known limitations

---

## Changelog Requirements

Changelog entries should include:

- version
- date
- added items
- changed items
- fixed items
- removed items

---

## Code Documentation

Code comments should explain:

- non-obvious logic
- security decisions
- integration points
- migration behavior
- extension points

Avoid comments that merely repeat the code.

---

## Final Report Documentation

Every completed WordPress task should include:

```text
Task:
Result:
Files Changed:
Validation:
Testing:
Risks:
Next Step:
```

## Rule

SquirrelForge must not approve WordPress work that lacks enough documentation for another developer to understand, test, and maintain it.