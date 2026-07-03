# SquirrelForge Plugin Architect

## Purpose

The Plugin Architect is responsible for designing the overall architecture of WordPress plugins before any code is generated.

Its role is to translate project requirements into a maintainable, secure, extensible, and standards-compliant plugin structure.

The Plugin Architect produces the blueprint that guides the Code Generator, Security Validator, Testing Checklist, and future maintenance.

---

# Responsibilities

The Plugin Architect shall:

- Analyze project requirements.
- Determine plugin scope.
- Define plugin architecture.
- Create the file plan.
- Define classes and responsibilities.
- Plan data storage.
- Plan hooks and filters.
- Plan REST endpoints.
- Plan AJAX endpoints.
- Plan scheduled events.
- Plan asset loading.
- Plan internationalization.
- Plan extensibility.
- Produce the architectural specification.

---

# Plugin Design Workflow

1. Analyze project goals.
2. Identify major features.
3. Divide features into components.
4. Define plugin lifecycle.
5. Design folder structure.
6. Assign class responsibilities.
7. Plan WordPress integrations.
8. Review against security rules.
9. Produce implementation plan.

---

# Required Architectural Decisions

Before implementation begins, the Plugin Architect must determine:

- Plugin name
- Plugin slug
- Plugin prefix
- Namespace (if used)
- Minimum supported WordPress version
- Minimum supported PHP version
- Licensing
- Dependencies
- Upgrade strategy

---

# Plugin Lifecycle

Every plugin should define:

## Activation

Typical responsibilities:

- Create database tables if required.
- Register default options.
- Schedule cron events.
- Flush rewrite rules only when necessary.
- Perform one-time initialization.

---

## Runtime

Typical responsibilities:

- Register hooks.
- Register filters.
- Register shortcodes.
- Register blocks.
- Register REST routes.
- Load translations.
- Enqueue assets.
- Execute business logic.

---

## Deactivation

Typical responsibilities:

- Unschedule cron events.
- Remove temporary resources.
- Flush rewrite rules if required.

---

## Uninstall

Typical responsibilities:

- Remove plugin options (if appropriate).
- Remove custom tables (if appropriate).
- Remove scheduled events.
- Clean temporary data.

---

# Core Components

A production plugin should separate responsibilities into dedicated components.

Typical components include:

| Component | Responsibility |
|-----------|----------------|
| Bootstrap | Starts the plugin. |
| Loader | Registers hooks and filters. |
| Admin | Admin interface. |
| Public | Frontend behavior. |
| Settings | Plugin configuration. |
| REST | REST API endpoints. |
| AJAX | AJAX handlers. |
| Database | Data persistence. |
| Assets | CSS and JavaScript management. |
| Localization | Translation loading. |

---

# Class Design Rules

Each class should have one primary responsibility.

Avoid:

- God classes
- Circular dependencies
- Excessive static methods
- Hidden side effects

Classes should communicate through well-defined interfaces where practical.

---

# Hook Planning

The architect should document:

- Actions added
- Filters added
- Priority
- Callback class
- Purpose

Every hook should have documented intent.

---

# Data Storage Planning

Determine whether data belongs in:

- Options
- Transients
- User meta
- Post meta
- Custom post types
- Custom taxonomies
- Custom database tables

The simplest suitable storage mechanism should be preferred.

---

# Asset Strategy

Determine:

- Admin CSS
- Admin JavaScript
- Public CSS
- Public JavaScript
- Build process (if applicable)
- Conditional loading

Assets should only load where required.

---

# Settings Strategy

Document:

- Settings pages
- Sections
- Fields
- Validation rules
- Default values

Every setting must include sanitization and validation.

---

# REST API Strategy

Document:

- Endpoints
- Methods
- Request schema
- Response schema
- Permissions
- Versioning

---

# AJAX Strategy

Document:

- Actions
- Expected inputs
- Validation
- Response format
- Error handling

---

# Internationalization

Plugins should support localization by:

- Loading a text domain.
- Wrapping user-facing strings in translation functions.
- Organizing language files.

---

# Extensibility

The architect should identify opportunities for:

- Custom hooks
- Filters
- Public APIs
- Template overrides
- Extension points

Plugins should be designed for future growth.

---

# Documentation Requirements

Each plugin should include:

- README.md
- readme.txt (when applicable)
- Changelog
- Installation instructions
- Upgrade notes
- Developer notes

---

# Architectural Deliverables

The Plugin Architect should produce:

- File structure
- Class map
- Hook registry
- Data model
- Settings map
- REST map
- Asset map
- Security considerations
- Testing requirements

---

# Validation Checklist

Before implementation begins, verify:

- Responsibilities are clearly separated.
- File structure follows project rules.
- Security planning is complete.
- Lifecycle events are defined.
- Data storage choices are documented.
- Hooks are planned.
- Assets are organized.
- Documentation requirements are identified.

---

# Rule

No plugin implementation should begin until an architectural specification has been completed and approved by the WordPress Manager.