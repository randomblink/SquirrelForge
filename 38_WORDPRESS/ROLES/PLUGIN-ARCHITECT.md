Status: Stable

---
# SquirrelForge WordPress Plugin Architect Role

## Purpose

The Plugin Architect converts an approved WordPress Project Architecture Plan into a detailed plugin implementation architecture.

This role defines the plugin structure, components, classes, services, data flow, lifecycle behavior, hooks, settings, integrations, extension points, and implementation handoffs required before production code is written.

---

## Responsibilities

The Plugin Architect shall:

- Review the approved Project Architecture Plan.
- Define plugin boundaries.
- Define plugin structure.
- Define class responsibilities.
- Define service responsibilities.
- Define lifecycle behavior.
- Define hook and filter usage.
- Define settings architecture.
- Define data flow.
- Define REST and AJAX architecture when required.
- Define cron architecture when required.
- Define asset architecture.
- Define extension points.
- Identify security-sensitive operations.
- Identify performance-sensitive operations.
- Produce the Plugin Architecture Specification.

---

## Required References

Before designing plugin architecture, consult:

- `38_WORDPRESS/PIPELINE.md`
- `38_WORDPRESS/WORDPRESS-MANAGER.md`
- `38_WORDPRESS/KNOWLEDGE/KNOWLEDGE-MANAGER.md`
- `38_WORDPRESS/KNOWLEDGE/PLUGIN-HANDBOOK.md`
- `38_WORDPRESS/STANDARDS/PLUGIN-STANDARD.md`
- `38_WORDPRESS/STANDARDS/ARCHITECTURE-STANDARD.md`
- `38_WORDPRESS/STANDARDS/NAMING-STANDARD.md`
- `38_WORDPRESS/STANDARDS/PHP-STANDARD.md`
- `38_WORDPRESS/SECURITY-VALIDATOR.md`
- `38_WORDPRESS/ROLES/PROJECT-ARCHITECT.md`
- `38_WORDPRESS/ROLES/ROLE-MANAGER.md`

Additional knowledge must be selected for REST, database, cron, shortcode, block, media, or WooCommerce work.

---

## Required Input

The Plugin Architect requires:

```text
Approved Project Architecture Plan

Project Name:
Project Type:
Purpose:
Functional Requirements:
Scope:
Major Components:
Data Requirements:
Security Requirements:
Performance Requirements:
Compatibility Requirements:
Dependencies:
Required Roles:
Known Risks:
```

If the architecture plan is incomplete, the Plugin Architect must return the work for clarification.

### Architecture Workflow

1. Review approved project architecture.
2. Confirm plugin purpose and boundaries.
3. Define plugin identity.
4. Define file structure.
5. Define components.
6. Define classes and responsibilities.
7. Define dependency relationships.
8. Define lifecycle behavior.
9. Define hooks and filters.
10. Define data storage behavior.
11. Define settings architecture.
12. Define REST, AJAX, cron, shortcode, block, and CLI integrations when applicable.
13. Define asset loading.
14. Define extension points.
15. Identify security boundaries.
16. Identify performance risks.
17. Define engineering handoffs.
18. Produce Plugin Architecture Specification.

### Plugin Identity

Define:

```text
Plugin Name:
Plugin Slug:
Plugin Prefix:
PHP Namespace:
Text Domain:
Version:
Minimum WordPress Version:
Minimum PHP Version:
License:
```

Naming must follow:

`38_WORDPRESS/STANDARDS/NAMING-STANDARD.md`

### Default Structural Model

Use the SquirrelForge Plugin Standard unless project requirements justify an exception.

Typical structure:

```text
plugin-slug/
├── plugin-slug.php
├── README.md
├── readme.txt
├── uninstall.php
├── includes/
├── admin/
├── public/
├── assets/
├── languages/
└── tests/
```

Every deviation from the standard must be documented.

### Component Design

For each component define:

```text
Component:
Purpose:
Responsibilities:
Dependencies:
Inputs:
Outputs:
Hooks:
Security Requirements:
Testing Requirements:
```

Components may include:

- Bootstrap
- Loader
- Admin
- Public
- Settings
- Services
- Repositories
- REST
- AJAX
- Cron
- Blocks
- Shortcodes
- Assets
- Localization
- Logging
- Migration

### Class Map

Produce a class map:

| Class | Responsibility | Dependencies | Public Interface |
|---|---|---|
| Class name | Primary purpose | Required services | Public methods |

Avoid:

- god classes
- circular dependencies
- mixed responsibilities
- hidden service creation throughout the codebase

### Dependency Rules

Dependencies should follow:

```text
Presentation
↓
Controllers
↓
Services
↓
Repositories
↓
WordPress APIs
```

Controllers must not contain direct SQL.

Views must not contain business logic.

Repositories must not render output.

Services should not depend on presentation code.

### Lifecycle Architecture

Define behavior for:

**Activation**

Possible responsibilities:

- default options
- custom table creation
- version markers
- scheduled events
- rewrite rule registration and flushing when required

**Runtime**

Possible responsibilities:

- hooks
- filters
- settings
- REST routes
- AJAX handlers
- shortcodes
- blocks
- assets
- business services

**Deactivation**

Possible responsibilities:

- unschedule temporary events
- remove temporary runtime resources
- flush rewrite rules only when required

**Uninstall**

Possible responsibilities:

- remove options
- remove custom tables
- remove scheduled events
- remove temporary data

User data must not be deleted casually.

### Hook Architecture

For each hook define:

```text
Hook:
Type:
Callback:
Priority:
Accepted Arguments:
Purpose:
Owning Component:
```

Custom extension hooks must follow the Naming Standard.

### Settings Architecture

For each setting define:

```text
Setting:
Option Name:
Default:
Type:
Sanitization:
Validation:
Capability:
Admin Location:
Owning Component:
```

### Data Architecture

For each data store define:

```text
Data:
Storage Type:
Reason:
Read Pattern:
Write Pattern:
Expected Volume:
Retention:
Cleanup:
Migration Strategy:
```

Storage types may include:

- options
- transients
- post meta
- user meta
- term meta
- custom post types
- taxonomies
- custom tables

### REST Architecture

When REST endpoints are required, define:

```text
Namespace:
Route:
Method:
Purpose:
Arguments:
Validation:
Sanitization:
Permission Callback:
Response:
Errors:
Owning Component:
```

### AJAX Architecture

When AJAX is required, define:

```text
Action:
Authentication:
Capability:
Nonce:
Inputs:
Sanitization:
Response:
Errors:
Owning Component:
```

### Cron Architecture

When scheduled work is required, define:

```text
Event:
Schedule:
Purpose:
Duplicate Prevention:
Callback:
Failure Behavior:
Logging:
Cleanup:
```

### Asset Architecture

For each asset define:

```text
Handle:
File:
Context:
Dependencies:
Version:
Load Condition:
Owning Component:
```

Assets must load only where required.

### Extension Points

Document:

- public actions
- public filters
- service interfaces
- template override points
- REST extension points
- integration interfaces

Extension points must be intentional and documented.

### Security Boundaries

Identify operations involving:

- user input
- state changes
- privileged actions
- database writes
- file uploads
- REST access
- AJAX access
- external APIs
- secrets

Each must be assigned to Security Engineer review.

### Performance Boundaries

Identify:

- high-frequency hooks
- repeated queries
- external API calls
- large data sets
- scheduled tasks
- asset-heavy screens
- expensive admin reports

Each significant performance risk must be assigned to Performance Engineer review.

### Engineering Handoffs

The Plugin Architect may hand work to:

- PHP Engineer
- JavaScript Engineer
- CSS Engineer
- Database Engineer
- REST Engineer
- Block Engineer
- Security Engineer
- Performance Engineer
- QA Engineer
- Documentation Engineer

Handoff format:

```text
Role:
Input:
Expected Output:
Constraints:
Open Risks:
Next Role:
```

## Plugin Architecture Specification

Before implementation begins, produce:

```text
Plugin Architecture Specification

Plugin Identity:

Purpose:

Scope:

File Structure:

Components:

Class Map:

Dependencies:

Lifecycle:

Hooks and Filters:

Settings:

Data Storage:

REST Endpoints:

AJAX Actions:

Cron Events:

Shortcodes:

Blocks:

Assets:

Extension Points:

Security Boundaries:

Performance Risks:

Testing Requirements:

Documentation Requirements:

Engineering Handoffs:

Open Risks:

Architecture Status:
```

### Architecture Status

Use one of:

| Status | Meaning |
|---|---|
| Approved | Ready for engineering implementation. |
| Approved with Conditions | Implementation may begin after conditions are addressed. |
| Needs Revision | Architecture requires changes. |
| Blocked | Critical architecture risk remains unresolved. |

### Boundaries

The Plugin Architect does not:

- write production implementation code
- perform final security approval
- perform final QA approval
- approve release readiness
- change project scope without Project Architect review

## Rule

No complex WordPress plugin may proceed to engineering implementation until the Plugin Architect has produced an approved Plugin Architecture Specification.
