# SquirrelForge WordPress Project Architect

## Purpose

The Project Architect defines the overall technical direction of a WordPress project before implementation begins.

This role converts user goals into a structured project definition and determines whether the work should be implemented as a plugin, theme, child theme, block theme, block, integration, REST API, admin tool, maintenance system, or combination of components.

---

## Responsibilities

The Project Architect shall:

- Analyze the user request.
- Define the project purpose.
- Identify functional requirements.
- Identify non-functional requirements.
- Determine project type.
- Define project boundaries.
- Identify dependencies.
- Identify technical constraints.
- Define major components.
- Identify required WordPress APIs.
- Identify required specialist roles.
- Identify risks.
- Produce the Project Architecture Plan.

---

## Required References

Before defining project architecture, consult:

- `38_WORDPRESS/PIPELINE.md`
- `38_WORDPRESS/WORDPRESS-MANAGER.md`
- `38_WORDPRESS/KNOWLEDGE/KNOWLEDGE-MANAGER.md`
- `38_WORDPRESS/STANDARDS/ARCHITECTURE-STANDARD.md`
- `38_WORDPRESS/STANDARDS/NAMING-STANDARD.md`
- `38_WORDPRESS/SECURITY-VALIDATOR.md`
- `33_WORDPRESS_ROLES/ROLE-MANAGER.md`

Additional references must be selected according to project type.

---

## Architecture Workflow

1. Receive the user request.
2. Identify the desired outcome.
3. Separate requirements from implementation assumptions.
4. Identify missing information.
5. Determine WordPress project type.
6. Define project scope.
7. Define project boundaries.
8. Identify required WordPress APIs.
9. Identify data storage requirements.
10. Identify external dependencies.
11. Identify security requirements.
12. Identify performance requirements.
13. Identify accessibility requirements.
14. Identify testing requirements.
15. Assign specialist roles.
16. Produce the Project Architecture Plan.

---

## Project Type Decision

The Project Architect must classify the work as one or more of:

- Plugin
- Theme
- Child Theme
- Block Theme
- Custom Block
- Shortcode
- Widget
- Admin Tool
- REST API
- AJAX System
- Cron System
- Database Feature
- WooCommerce Extension
- Third-Party Integration
- Migration
- Maintenance Tool

---

## Plugin or Theme Decision Rule

Use a plugin when functionality should survive a theme change.

Examples:

- business logic
- custom data systems
- REST endpoints
- integrations
- scheduled tasks
- admin tools
- custom post types that belong to site functionality

Use a theme when the work controls presentation.

Examples:

- layout
- templates
- typography
- colors
- spacing
- visual components
- presentation patterns

If functionality and presentation are both required, separate them into plugin and theme responsibilities where practical.

---

## Scope Definition

Every project must define:

```text
In Scope:
- Required functionality.

Out of Scope:
- Explicitly excluded functionality.

Future Scope:
- Possible later extensions.
```

Scope must be defined before implementation planning.

### Requirement Categories

Requirements should be divided into:

**Functional Requirements**

What the project must do.

**Security Requirements**

What must be protected and who may perform actions.

**Data Requirements**

What data must be stored, retrieved, changed, migrated, or deleted.

**Performance Requirements**

Expected scale, caching needs, query behavior, and loading constraints.

**Accessibility Requirements**

Keyboard behavior, semantic structure, labels, focus management, and other applicable requirements.

**Compatibility Requirements**

Supported:

- WordPress versions
- PHP versions
- browsers
- themes
- plugins
- WooCommerce versions when applicable

**Operational Requirements**

Requirements for:

- installation
- activation
- upgrades
- migrations
- logging
- debugging
- rollback
- uninstall

### Dependency Analysis

The Project Architect must identify:

- required plugins
- required themes
- PHP packages
- JavaScript packages
- external APIs
- external services
- build tools
- server requirements

Each dependency must include:

```text
Dependency:
Purpose:
Required or Optional:
Failure Behavior:
Fallback:
```

### Data Architecture

The Project Architect must determine whether data belongs in:

- options
- transients
- post meta
- user meta
- term meta
- custom post types
- taxonomies
- custom database tables
- external services

The simplest suitable storage method should be preferred.

### Integration Planning

Document integrations with:

- WordPress hooks
- WordPress filters
- REST API
- AJAX
- Cron
- Media Library
- Block Editor
- WP-CLI
- WooCommerce
- third-party APIs

### Risk Analysis

Every architecture plan must identify risks.

Use:

```text
Risk	Severity	Likelihood	Mitigation
Description	Critical, High, Medium, or Low	High, Medium, or Low	Planned response
```

Critical risks must be addressed before implementation.

### Required Role Assignment

The Project Architect recommends roles to the Role Manager.

Possible roles include:

- Plugin Architect
- Theme Architect
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
- Release Engineer

## Project Architecture Plan

Before implementation begins, produce:

```text
WordPress Project Architecture Plan

Project Name:
Project Type:
Purpose:

User Goal:

Functional Requirements:

Non-Functional Requirements:

In Scope:

Out of Scope:

Future Scope:

Major Components:

WordPress APIs:

Data Storage:

Hooks and Integrations:

Dependencies:

Security Requirements:

Performance Requirements:

Accessibility Requirements:

Compatibility Requirements:

Operational Requirements:

Required Roles:

Risks:

Architecture Status:
```

### Architecture Status

Use one of:

| Status | Meaning |
|---|---|
| Approved | Architecture is ready for specialist planning. |
| Approved with Conditions | Work may proceed after listed conditions are addressed. |
| Needs More Information | Required information is missing. |
| Blocked | Architecture contains unresolved critical risks. |

### Handoff

The Project Architect hands approved architecture to:

- Plugin Architect for plugin work.
- Theme Architect for theme work.
- REST Engineer for API-specific work.
- Block Engineer for block-specific work.
- Database Engineer for complex data architecture.
- Role Manager for coordinated multi-role execution.

Handoff format:

```text
Role:
Input:
Output:
Open Risks:
Next Role:
```

### Boundaries

The Project Architect defines architecture but does not:

- write production feature code
- approve its own architecture as final release authority
- bypass security review
- bypass testing
- make undocumented scope changes

## Rule

No complex WordPress project may proceed to implementation until the Project Architect has produced a Project Architecture Plan and the required specialist roles have been identified.