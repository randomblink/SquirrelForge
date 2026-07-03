# SquirrelForge WordPress Layer

## Purpose

This directory defines the WordPress-specific rules, workflows, validators, and generators required for SquirrelForge to operate as a WordPress development Agent for plugins and themes.

The WordPress Layer translates general agent reasoning into safe WordPress development behavior.

---

## Component Roster

| Component | Responsibility |
|---|---|
| `WORDPRESS-MANAGER.md` | Coordinates all WordPress development operations. |
| `FILE-STRUCTURE-RULES.md` | Defines required plugin and theme file structures. |
| `SECURITY-VALIDATOR.md` | Checks WordPress security requirements. |
| `PLUGIN-ARCHITECT.md` | Designs plugin architecture and required files. |
| `THEME-ARCHITECT.md` | Designs theme architecture and template structure. |
| `CODE-GENERATOR.md` | Generates WordPress-safe plugin and theme code. |
| `TESTING-CHECKLIST.md` | Defines manual and automated WordPress testing steps. |
| `HOOK-REGISTRY.md` | Tracks actions, filters, shortcodes, blocks, REST routes, and cron hooks. |

---

## Rule

SquirrelForge must not generate, modify, or approve WordPress code unless it passes the WordPress Layer safety, structure, and testing requirements.