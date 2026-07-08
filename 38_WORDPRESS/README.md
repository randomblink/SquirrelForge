Status: Stable

---
# SquirrelForge WordPress Layer

## Purpose

This directory contains the WordPress-specific knowledge, standards, operating guidance, and domain references SquirrelForge needs to make safe, useful, and accurate decisions for plugins, themes, blocks, admin tools, REST APIs, WooCommerce extensions, and WordPress-powered products.

The WordPress layer supplies domain expertise to the wider SquirrelForge agent architecture. It does not replace the general Agent, Engine, Reasoning, Execution, Security, Testing, or Governance layers.

---

## Layer Boundary

`38_WORDPRESS` is the authoritative WordPress domain layer.

The layer owns:

- WordPress platform knowledge,
- WordPress development standards,
- WordPress-specific security guidance,
- plugin architecture guidance,
- theme architecture guidance,
- block editor guidance,
- REST API guidance,
- WordPress database guidance,
- WooCommerce guidance,
- WordPress performance guidance,
- WordPress accessibility guidance,
- WordPress testing expectations,
- and WordPress-specific operating references.

The layer does not own general-purpose:

- agent identity,
- prompt compilation,
- task planning,
- workflow orchestration,
- permission management,
- action dispatch,
- memory storage,
- generic testing infrastructure,
- observability infrastructure,
- governance policy,
- or AI-provider control.

Those responsibilities remain in their respective architectural layers.

---

## Component Roster

| Component | Responsibility |
|---|---|
| `WORDPRESS-CORE.md` | Defines core WordPress concepts, lifecycle, hooks, files, and execution flow. |
| `PLUGIN-HANDBOOK.md` | Defines plugin development concepts, structure, lifecycle, and best practices. |
| `THEME-HANDBOOK.md` | Defines classic, child, hybrid, and block theme development concepts. |
| `BLOCK-EDITOR.md` | Defines Gutenberg and block editor concepts and block development rules. |
| `SETTINGS-API.md` | Defines settings pages, sections, fields, sanitization, and options. |
| `REST-API.md` | Defines REST route creation, permission callbacks, schemas, and responses. |
| `DATABASE.md` | Defines safe database usage, `$wpdb`, schema changes, and custom tables. |
| `CUSTOM-POST-TYPES.md` | Defines custom post type registration and use cases. |
| `TAXONOMIES.md` | Defines custom taxonomy registration and use cases. |
| `SHORTCODES.md` | Defines shortcode creation, attributes, rendering, and safety. |
| `CRON.md` | Defines WordPress scheduled events, recurrence, unscheduling, and cleanup rules. |
| `MEDIA.md` | Defines uploads, attachments, image handling, validation, and media safety. |
| `SECURITY.md` | Defines WordPress-specific security principles and common failure patterns. |
| `PERFORMANCE.md` | Defines caching, asset loading, query safety, and optimization rules. |
| `ACCESSIBILITY.md` | Defines accessibility expectations for themes, admin pages, blocks, and frontend output. |
| `INTERNATIONALIZATION.md` | Defines translation, localization, escaping, and text-domain rules. |
| `WOOCOMMERCE.md` | Defines WooCommerce extension concepts, hooks, products, checkout, and orders. |
| `CODING-STANDARDS.md` | Defines WordPress PHP, JavaScript, CSS, and documentation standards. |

The roster must reflect files that actually exist in this directory. Missing components must not be presented as completed documentation.

---

## How the Agent Uses This Layer

Before SquirrelForge designs, generates, reviews, modifies, tests, or approves WordPress code, it must load the relevant WordPress references from this layer.

The WordPress layer should help the system:

- choose the correct WordPress API,
- identify the correct extension point,
- avoid modifying WordPress core,
- avoid unsafe shortcuts,
- choose between plugin, theme, block, REST, CLI, or integration approaches,
- explain WordPress-specific design decisions,
- detect missing files,
- detect missing validation,
- detect security risks,
- generate appropriate plugin and theme architecture,
- and create accurate WordPress testing checklists.

---

## Architecture Integration

WordPress work should flow through the wider SquirrelForge system rather than bypassing it.

```text
User Request
   ↓
Agent and Engine
   ↓
Rules and Context
   ↓
WordPress Domain Knowledge
   ↓
Reasoning and Risk Assessment
   ↓
Workflow Selection and Planning
   ↓
Permission Review
   ↓
Execution
   ↓
WordPress-Specific Validation
   ↓
Testing
   ↓
Observability
   ↓
Response and Memory Update
```

The WordPress layer informs the process. General orchestration and execution remain owned by the broader architecture.

---

## Knowledge Priority

When WordPress guidance conflicts, priority should be evaluated in this order:

1. Platform safety and system security requirements
2. Current verified WordPress behavior and supported APIs
3. SquirrelForge governance and security rules
4. Project-specific requirements and constraints
5. WordPress coding standards
6. Performance and maintainability requirements
7. Convenience

Project-specific requirements may customize implementation choices, but they must not silently override higher-priority safety or security requirements.

---

## Evidence Rule

WordPress behavior changes over time.

When a task depends on version-specific behavior, deprecated APIs, current compatibility, current coding standards, or current platform requirements, the agent must verify the relevant version and source before treating the information as authoritative.

Stored knowledge is context, not proof that current behavior is unchanged.

---

## WordPress Core Protection Rule

SquirrelForge must not directly modify WordPress core as a normal development method.

Custom behavior should normally be implemented through supported extension mechanisms such as:

- plugins,
- must-use plugins,
- themes,
- child themes,
- hooks,
- filters,
- blocks,
- REST APIs,
- WP-CLI commands,
- or supported integration APIs.

Exceptional requests to modify core must be treated as high risk and require explicit justification and review.

---

## Validation Rule

WordPress work must be validated according to the type of change.

Relevant validation may include:

- PHP syntax checks,
- WordPress coding standards,
- static analysis,
- unit tests,
- integration tests,
- plugin activation tests,
- theme activation tests,
- REST API tests,
- block build tests,
- browser tests,
- accessibility tests,
- responsive checks,
- performance checks,
- security review,
- and manual verification.

The agent must not claim that a validation step passed unless it was actually performed.

---

## Document Placement Rule

A document belongs in `38_WORDPRESS` when its primary responsibility is WordPress-specific knowledge or WordPress-specific operating guidance.

A document belongs elsewhere when its primary responsibility is general agent behavior or system infrastructure.

Examples:

- General agent boot lifecycle → Agent or Core layer
- General prompt compilation → AI Driver or Engine layer
- General workflow execution → Execution layer
- General test orchestration → Testing layer
- WordPress plugin architecture → WordPress layer
- WordPress theme architecture → WordPress layer
- WordPress REST permission guidance → WordPress layer
- WordPress deployment checklist → WordPress-specific guidance coordinated with Workflow, Testing, Security, and Governance layers

This rule prevents duplicated systems and conflicting ownership.

---

## Maintenance Rule

The WordPress layer must be maintained as a practical engineering reference, not vague documentation.

Each document should:

- define its scope,
- identify safe patterns,
- identify unsafe patterns,
- explain decision criteria,
- identify validation expectations,
- reference related architectural layers where appropriate,
- avoid duplicating general system responsibilities,
- and remain consistent with the current repository architecture.

---

## Completion Criteria

The WordPress layer is structurally healthy when:

- its README accurately describes the layer boundary,
- the component roster matches actual files,
- WordPress-specific guidance is separated from general agent infrastructure,
- cross-layer responsibilities are clearly referenced,
- stale layer-number references are removed,
- version-sensitive claims are verifiable,
- and the layer provides practical guidance for WordPress development work.

---

## Rule

> `38_WORDPRESS` is the WordPress domain layer for SquirrelForge. It supplies WordPress expertise to the agent system but does not duplicate the general agent runtime, orchestration, reasoning, execution, testing, security, or governance systems.
