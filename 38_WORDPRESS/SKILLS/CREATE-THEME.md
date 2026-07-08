Status: Stable

---
# SquirrelForge Skill: Create WordPress Theme

## Purpose

This skill defines how SquirrelForge creates a production-ready WordPress theme through the specialist roles in `33_WORDPRESS_ROLES` and the mandatory lifecycle in `38_WORDPRESS/PIPELINE.md`.

It supports classic, child, block, and hybrid themes while preserving the boundary between presentation and plugin-level business logic.

---

## Required References

Before planning, consult:

- `38_WORDPRESS/PIPELINE.md`
- `38_WORDPRESS/KNOWLEDGE/KNOWLEDGE-MANAGER.md`
- `38_WORDPRESS/KNOWLEDGE/THEME-HANDBOOK.md`
- `38_WORDPRESS/KNOWLEDGE/SECURITY.md`
- `38_WORDPRESS/STANDARDS/THEME-STANDARD.md`
- `38_WORDPRESS/STANDARDS/NAMING-STANDARD.md`
- `38_WORDPRESS/STANDARDS/PHP-STANDARD.md`
- `38_WORDPRESS/STANDARDS/CSS-STANDARD.md`
- `38_WORDPRESS/STANDARDS/JAVASCRIPT-STANDARD.md`
- `38_WORDPRESS/STANDARDS/TESTING-STANDARD.md`
- `33_WORDPRESS_ROLES/ROLE-MANAGER.md`

The Knowledge Manager must add block editor, accessibility, performance, integration, and project-specific references required by the selected theme type.

---

## Role Assignment

| Role | Responsibility |
|---|---|
| [`Role Manager`](../../33_WORDPRESS_ROLES/ROLE-MANAGER.md) | Classifies the request, assigns roles, records handoffs, and enforces pipeline progression. |
| [`Project Architect`](../../33_WORDPRESS_ROLES/PROJECT-ARCHITECT.md) | Defines purpose, scope, audience, requirements, compatibility, dependencies, risks, and theme boundaries. |
| [`Theme Architect`](../../33_WORDPRESS_ROLES/THEME-ARCHITECT.md) | Selects theme type and defines templates, parts, patterns, `theme.json`, supports, assets, responsive behavior, and handoffs. |
| [`PHP Engineer`](../../33_WORDPRESS_ROLES/PHP-ENGINEER.md) | Implements setup, supports, hooks, menus, sidebars, rendering helpers, and approved PHP behavior. |
| [`CSS Engineer`](../../33_WORDPRESS_ROLES/CSS-ENGINEER.md) | Implements design tokens, layout, responsive behavior, editor parity, focus states, and visual accessibility. |
| [`JavaScript Engineer`](../../33_WORDPRESS_ROLES/JAVASCRIPT-ENGINEER.md) | Implements required frontend and editor interactions. |
| [`Block Engineer`](../../33_WORDPRESS_ROLES/BLOCK-ENGINEER.md) | Participates for block or hybrid themes requiring blocks, patterns, editor behavior, or block metadata. |
| [`Security Engineer`](../../33_WORDPRESS_ROLES/SECURITY-ENGINEER.md) | Independently validates escaping, input handling, permissions, integrations, files, and security boundaries. |
| [`Performance Engineer`](../../33_WORDPRESS_ROLES/PERFORMANCE-ENGINEER.md) | Validates assets, rendering, fonts, images, queries, and measured frontend performance. |
| [`QA Engineer`](../../33_WORDPRESS_ROLES/QA-ENGINEER.md) | Defines and executes functional, responsive, accessibility, compatibility, and regression testing. |
| [`Documentation Engineer`](../../33_WORDPRESS_ROLES/DOCUMENTATION-ENGINEER.md) | Produces installation, customization, architecture, template, and release documentation. |
| [`Release Engineer`](../../33_WORDPRESS_ROLES/RELEASE-ENGINEER.md) | Confirms all gates, versions the theme, validates packaging, and approves the artifact. |

---

## Pipeline Mapping

This skill executes `38_WORDPRESS/PIPELINE.md` in order.

| Pipeline Stage | Responsible Role(s) | Theme Activity | Required Output or Gate |
|---|---|---|---|
| Intent Analysis | Role Manager | Convert the request into a structured theme task and select specialist roles. | Role Assignment and Handoff Plan |
| Knowledge Selection | Role Manager, Knowledge Manager | Load theme, security, standards, accessibility, block, and performance references. | References Consulted Record |
| Requirements Builder | Project Architect | Define functional, presentation, accessibility, compatibility, and performance acceptance criteria. | Theme Requirements |
| Architecture Planning | Project Architect | Establish scope, dependencies, risks, and theme-versus-plugin boundaries. | Approved Project Architecture Plan |
| Implementation Planning | Theme Architect | Define theme type, identity, files, templates, parts, patterns, tokens, supports, assets, and engineering assignments. | Approved Theme Architecture Specification |
| Code Generation | PHP, CSS, JavaScript, and Block Engineers | Implement the approved specification and record deviations for architect approval. | Engineering Implementation Reports |
| Security Validation | Security Engineer | Review output, input, settings, integrations, files, and trust boundaries. | Security Report **(BLOCKING GATE)** |
| Standards Validation | Theme Architect and relevant reviewers | Verify theme, naming, PHP, CSS, JavaScript, accessibility, and documentation standards. | Standards Report **(BLOCKING GATE)** |
| Testing Plan | QA Engineer | Define template, responsive, accessibility, browser, editor, activation, and regression tests. | Approved Test Plan |
| Code Review | Theme Architect, Security Engineer, Performance Engineer | Review correctness, boundaries, maintainability, rendering, and asset behavior. | Code Review Report |
| Refactoring | Assigned Engineers | Apply approved improvements without changing accepted requirements or public behavior. | Refactoring Report or `Not Required` rationale |
| Documentation Update | Documentation Engineer | Create README, changelog, installation, customization, template, menu, sidebar, and asset guidance. | Documentation Report |
| Final Approval | QA Engineer, Security Engineer, Release Engineer | Execute tests, verify all gates, inspect package contents, and approve the installable artifact. | Approved Theme Package **(FINAL GATE)** |

No stage may be skipped silently. A non-applicable stage requires a recorded rationale approved by the Role Manager.

---

## Role Handoff Format

```text
Role:
Input:
Requirements Covered:
Output:
Decisions:
Open Risks:
Deviations:
Validation Status:
Next Role:
```

The receiving role must verify the handoff before beginning work. Missing requirements, risk, decision, or validation information blocks progression.

---

## Theme Boundary Rules

Themes may own:

- Templates, template parts, and patterns.
- Layout and responsive behavior.
- Typography, colors, spacing, and design tokens.
- Menus, sidebars, editor styles, and theme supports.
- Presentation-specific assets and helpers.

Themes must not own critical business logic or durable data behavior that must survive a theme change. Custom post types, persistent workflows, integrations, and reusable application features should normally live in a plugin.

---

## Gate and Remediation Rules

- A failed security gate returns work to the responsible engineer and may require Theme Architect review.
- A failed standards gate returns work to the relevant architect or engineer.
- QA defects are assigned by the Role Manager and require independent re-testing.
- Architectural remediation returns to the Project or Theme Architect before implementation resumes.
- Every remediation must repeat all gates whose reviewed scope changed.
- The Release Engineer must block packaging when required evidence is missing, failed, stale, or outside the final change scope.

---

## Required Outputs

- Role Assignment and Handoff Plan.
- References Consulted Record.
- Theme Requirements.
- Project Architecture Plan.
- Theme Architecture Specification.
- Theme source and production assets.
- Engineering Implementation Reports.
- Security, standards, code review, performance, and QA reports.
- README, changelog, and customization documentation.
- Versioned installable package.

## Completion Criteria

- [ ] Required roles were assigned before implementation.
- [ ] Requirements and both architecture plans are approved.
- [ ] Theme type and theme-versus-plugin boundaries are documented.
- [ ] Implementation matches the approved specification.
- [ ] Required templates and fallback states render correctly.
- [ ] Responsive behavior and editor parity are verified.
- [ ] Accessibility requirements pass independent QA.
- [ ] Security and standards validation pass.
- [ ] Performance and code review requirements pass.
- [ ] Functional, compatibility, and regression tests pass.
- [ ] Documentation is complete and accurate.
- [ ] The Release Engineer approves the installable package.

## Rule

SquirrelForge must create WordPress themes through the roles in `33_WORDPRESS_ROLES` and the ordered lifecycle in `38_WORDPRESS/PIPELINE.md`; a theme is not complete until architecture, implementation, independent validation, documentation, testing, and final release evidence are approved.
