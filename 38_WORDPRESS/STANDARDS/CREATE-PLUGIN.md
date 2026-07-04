# SquirrelForge Skill: Create WordPress Plugin

## Purpose

This skill defines the workflow SquirrelForge must follow when creating a WordPress plugin from a user request.

---

## Required References

Before creating a plugin, consult:

- `38_WORDPRESS/PIPELINE.md`
- `38_WORDPRESS/WORDPRESS-MANAGER.md`
- `38_WORDPRESS/KNOWLEDGE/KNOWLEDGE-MANAGER.md`
- `38_WORDPRESS/KNOWLEDGE/PLUGIN-HANDBOOK.md`
- `38_WORDPRESS/STANDARDS/PLUGIN-STANDARD.md`
- `38_WORDPRESS/STANDARDS/NAMING-STANDARD.md`
- `38_WORDPRESS/STANDARDS/PHP-STANDARD.md`
- `38_WORDPRESS/STANDARDS/ARCHITECTURE-STANDARD.md`
- `38_WORDPRESS/STANDARDS/TESTING-STANDARD.md`
- `38_WORDPRESS/SECURITY-VALIDATOR.md`

---

## Workflow

1. Identify plugin purpose.
2. Define plugin name, slug, prefix, and text domain.
3. Identify required features.
4. Select relevant WordPress knowledge documents.
5. Create plugin architecture.
6. Create file structure.
7. Define classes and responsibilities.
8. Define hooks, filters, shortcodes, REST routes, AJAX actions, cron tasks, and settings.
9. Generate code according to standards.
10. Validate security.
11. Validate naming and architecture.
12. Create testing checklist.
13. Create documentation.
14. Produce final report.

---

## Required Planning Output

Before code generation, produce:

```text
Plugin Plan

Name:
Slug:
Prefix:
Text Domain:
Purpose:
Features:
Required Files:
Classes:
Hooks:
Settings:
Data Storage:
Security Requirements:
Testing Requirements:
```

### Required Plugin Files

Default required files:

```text
plugin-slug/
├── plugin-slug.php
├── README.md
├── readme.txt
├── uninstall.php
├── includes/
│   ├── class-plugin.php
│   ├── class-loader.php
│   ├── class-activator.php
│   ├── class-deactivator.php
│   ├── class-settings-manager.php
│   ├── class-asset-manager.php
│   └── class-i18n.php
├── admin/
│   ├── class-admin-controller.php
│   ├── views/
│   ├── css/
│   └── js/
├── public/
│   ├── class-public-controller.php
│   ├── css/
│   └── js/
├── languages/
├── assets/
└── tests/
```

### Security Gates

The plugin must pass checks for:

- direct access protection
- capability checks
- nonce checks
- input sanitization
- output escaping
- prepared SQL
- REST permission callbacks
- AJAX permission checks
- upload validation if uploads exist
- no hardcoded secrets

### Testing Gates

Verify:

- plugin activates
- plugin deactivates
- uninstall does not destroy data unless intended
- admin pages load
- settings save
- frontend output works
- assets load correctly
- permissions are enforced
- invalid input is handled
- PHP fatal errors are absent

### Final Report Format

```text
Task:
Plugin:
Files Created:
References Consulted:
Security Validation:
Standards Validation:
Testing:
Risks:
Next Step:
```

## Rule

SquirrelForge must not create plugin code until the Plugin Plan is complete.