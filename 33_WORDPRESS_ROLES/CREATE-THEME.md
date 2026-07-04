# SquirrelForge WordPress Create Theme Skill

## Purpose

This Skill defines the controlled workflow for creating a WordPress theme.

It coordinates requirements, knowledge selection, architecture, role routing, specialist implementation, accessibility, security, performance validation, QA, documentation, and release review.

---

## Trigger Conditions

Use this Skill when the request is to:

- create a new WordPress theme
- create a child theme
- create a block theme
- create a classic theme
- create a hybrid theme
- convert an approved design system into a WordPress theme

Do not use this Skill when the task is only:

- debugging an existing theme
- reviewing existing theme code
- refactoring an existing theme
- creating one isolated block

Use the appropriate specialized Skill instead.

---

## Required References

Before execution, consult:

- `38_WORDPRESS/PIPELINE.md`
- `38_WORDPRESS/WORDPRESS-MANAGER.md`
- `38_WORDPRESS/KNOWLEDGE/KNOWLEDGE-MANAGER.md`
- `38_WORDPRESS/KNOWLEDGE/WORDPRESS-CORE.md`
- `38_WORDPRESS/KNOWLEDGE/THEME-HANDBOOK.md`
- `38_WORDPRESS/KNOWLEDGE/BLOCK-EDITOR.md` when applicable
- `38_WORDPRESS/KNOWLEDGE/ACCESSIBILITY.md`
- `38_WORDPRESS/KNOWLEDGE/PERFORMANCE.md`
- `38_WORDPRESS/KNOWLEDGE/SECURITY.md`
- `38_WORDPRESS/STANDARDS/THEME-STANDARD.md`
- `38_WORDPRESS/STANDARDS/ARCHITECTURE-STANDARD.md`
- `38_WORDPRESS/STANDARDS/NAMING-STANDARD.md`
- `38_WORDPRESS/STANDARDS/PHP-STANDARD.md`
- `38_WORDPRESS/STANDARDS/CSS-STANDARD.md`
- `38_WORDPRESS/STANDARDS/JAVASCRIPT-STANDARD.md`
- `38_WORDPRESS/STANDARDS/TESTING-STANDARD.md`
- `38_WORDPRESS/STANDARDS/DOCUMENTATION-STANDARD.md`
- `33_WORDPRESS_ROLES/ROLE-MANAGER.md`
- `33_WORDPRESS_ROLES/ROLE-ROUTING-MATRIX.md`
- `33_WORDPRESS_ROLES/PROJECT-ARCHITECT.md`
- `33_WORDPRESS_ROLES/THEME-ARCHITECT.md`

Additional references must be selected according to project requirements.

---

## Required Input

```text
Theme Creation Request

Theme Name:
Purpose:
Theme Type:
Primary Users:
Target Content:
Required Templates:
Required Template Parts:
Navigation Requirements:
Sidebar Requirements:
Block Requirements:
Pattern Requirements:
Design System:
Typography:
Color System:
Spacing System:
Responsive Requirements:
Accessibility Requirements:
Performance Requirements:
Compatibility Requirements:
Parent Theme:
Distribution Target:
Known Constraints:
```

Missing fields may be resolved during requirements definition.

Critical requirements must not be invented.

### Workflow

#### Stage 1 — Requirements Definition

Convert the request into:

```text
Theme Requirements

Purpose:

Theme Type:

Functional Requirements:

Presentation Requirements:

Template Requirements:

Template Part Requirements:

Navigation Requirements:

Sidebar Requirements:

Block Requirements:

Pattern Requirements:

Design Token Requirements:

Asset Requirements:

Responsive Requirements:

Accessibility Requirements:

Performance Requirements:

Security Requirements:

Compatibility Requirements:

Distribution Requirements:

Acceptance Criteria:

Out of Scope:

Missing Information:
```

If critical information is missing:

`Status: Needs More Information`

Do not proceed to architecture until blocking requirements are resolved.

#### Stage 2 — Knowledge Selection

Use:

`38_WORDPRESS/KNOWLEDGE/KNOWLEDGE-MANAGER.md`

Select required knowledge domains.

Possible selections include:

- WordPress Core
- Theme Handbook
- Block Editor
- Accessibility
- Performance
- Security
- Media
- WooCommerce

Produce:

```text
Knowledge Selection

Task:
Required Knowledge:
Optional Knowledge:
Reason:
```

#### Stage 3 — Project Architecture

Use:

`33_WORDPRESS_ROLES/PROJECT-ARCHITECT.md`

Produce:

`Approved Project Architecture Plan`

The plan must establish:

- project boundaries
- theme responsibility
- persistent functionality boundaries
- content assumptions
- plugin dependencies
- accessibility requirements
- performance requirements
- compatibility requirements
- specialist domains

#### Stage 4 — Theme Architecture

Use:

`33_WORDPRESS_ROLES/THEME-ARCHITECT.md`

Produce:

`Approved Theme Architecture Specification`

The specification must define applicable items:

- theme identity
- theme type
- file structure
- template map
- template parts
- theme supports
- menus
- sidebars
- blocks
- patterns
- design tokens
- assets
- responsive requirements
- accessibility requirements
- security boundaries
- performance risks
- testing requirements
- documentation requirements
- engineering handoffs

Implementation must not begin before architecture is approved.

#### Stage 5 — Role Routing

Use:

`33_WORDPRESS_ROLES/ROLE-MANAGER.md`

and:

`33_WORDPRESS_ROLES/ROLE-ROUTING-MATRIX.md`

The standard theme route is:

1. Project Architect
2. ↓
3. Role Manager
4. ↓
5. Theme Architect
6. ↓
7. PHP Engineer
8. ↓
9. Block Engineer when required
10. ↓
11. JavaScript Engineer when required
12. ↓
13. CSS Engineer
14. ↓
15. Security Engineer
16. ↓
17. Performance Engineer
18. ↓
19. QA Engineer
20. ↓
21. Documentation Engineer
22. ↓
23. Release Engineer

Produce:

```text
WordPress Role Routing Decision

Task:
Selected Skill: CREATE-THEME
Project Type: Theme
Complexity:
Required Roles:
Optional Roles:
Role Sequence:
Required Gates:
Conditional Gates:
Expected Reports:
Known Risks:
Routing Status:
```

Implementation may proceed only when `Routing Status` is `Ready`, or when all blocking conditions attached to `Ready with Conditions` have been resolved.

#### Stage 6 — Implementation Planning

Create assignments for every selected implementation role.

Possible assignments:

- PHP Engineering Assignment
- Block Engineering Assignment
- JavaScript Engineering Assignment
- CSS Engineering Assignment

Each assignment must identify:

- project
- component
- purpose
- approved architecture
- files to create
- files to modify
- required interfaces
- dependencies
- accessibility requirements
- security requirements
- performance constraints
- compatibility requirements
- testing requirements
- open risks

#### Stage 7 — Theme Foundation Implementation

The `PHP Engineer` implements applicable server-side theme foundations.

Typical responsibilities include:

- theme setup
- theme supports
- menu registration
- sidebar registration
- template helpers
- asset enqueueing
- localization
- approved presentation helpers
- theme lifecycle behavior where applicable

The `PHP Engineer` must produce:

`PHP Implementation Report`

Business logic that must survive a theme change must not be placed in the theme.

#### Stage 8 — Block and Editor Implementation

Use `Block Engineer` when the theme includes:

- custom blocks
- block variations
- block styles
- block patterns requiring engineering
- editor integrations
- dynamic blocks
- controlled inner block structures

Required output:

`Block Engineering Report`

For block themes, verify applicable architecture for:

- `theme.json`
- `templates`
- `template parts`
- `patterns`
- `style variations`
- `global styles`
- `editor settings`
- `template locking`

#### Stage 9 — JavaScript Implementation

Use `JavaScript Engineer` when the theme requires client-side behavior.

Possible work includes:

- navigation interaction
- accessible disclosure controls
- modal behavior
- filtering
- frontend requests
- block editor interaction
- progressive enhancement

Required output:

`JavaScript Implementation Report`

Native HTML and CSS behavior should be preferred when sufficient.

#### Stage 10 — CSS Implementation

Use `CSS Engineer` for theme presentation implementation.

Required areas may include:

- design tokens
- typography
- color system
- spacing
- layout
- navigation
- forms
- content
- media
- comments
- pagination
- blocks
- template-specific presentation
- responsive behavior
- editor styles
- focus states
- reduced motion behavior

Required output:

`CSS Implementation Report`

The `CSS Engineer` must verify editor and frontend consistency when required.

#### Stage 11 — Security Validation

Use:

- `38_WORDPRESS/SECURITY-VALIDATOR.md`
- `33_WORDPRESS_ROLES/SECURITY-ENGINEER.md`

Review applicable areas:

- dynamic output escaping
- Customizer or settings input
- forms
- AJAX
- REST requests
- dynamic block rendering
- URL handling
- allowed HTML
- data exposure
- external integrations
- error exposure

Required output:

`Security Review Report`

The Skill must stop when `Final Security Status` is:

- `Fail`
- `Needs More Information`

Security failures return to the responsible implementation role.

After remediation, the `Security Engineer` must independently revalidate the fix.

#### Stage 12 — Performance Validation

Use:

`33_WORDPRESS_ROLES/PERFORMANCE-ENGINEER.md`

Review:

- stylesheet size
- JavaScript bundle size
- global asset loading
- conditional asset loading
- web fonts
- images
- repeated template queries
- dynamic block rendering
- third-party scripts
- editor bundle cost
- frontend rendering cost

Required output:

`Performance Review Report`

Performance claims must be based on measurement whenever practical.

#### Stage 13 — QA Validation

Use:

`33_WORDPRESS_ROLES/QA-ENGINEER.md`

QA must test applicable behavior including:

- activation
- homepage
- posts
- pages
- archives
- categories
- tags
- search
- 404 page
- menus
- mobile navigation
- sidebars
- comments
- forms
- pagination
- media
- blocks
- patterns
- editor behavior
- frontend rendering
- keyboard navigation
- visible focus
- zoom
- responsive behavior
- browser compatibility
- WordPress compatibility
- regression behavior

Required output:

`QA Report`

The Skill must stop when `Final QA Status` is:

- `Fail`
- `Blocked`
- `Needs More Information`

Defects return to the responsible Engineer and must be retested.

#### Stage 14 — Documentation

Use:

`33_WORDPRESS_ROLES/DOCUMENTATION-ENGINEER.md`

Update applicable documentation:

- `README.md`
- `readme.txt`
- `CHANGELOG.md`
- installation instructions
- theme setup instructions
- menu documentation
- sidebar documentation
- block documentation
- pattern documentation
- customization documentation
- development documentation
- testing documentation
- known limitations

Required output:

`Documentation Report`

Documentation must describe actual validated behavior.

#### Stage 15 — Release Review

Use:

`33_WORDPRESS_ROLES/RELEASE-ENGINEER.md`

Verify:

- architecture status
- implementation reports
- security status
- performance status
- QA status
- documentation status
- version consistency
- package contents
- secret scan
- clean installation
- activation
- upgrade behavior
- parent-theme requirements when applicable
- rollback planning
- release artifact integrity

Required output:

`Release Readiness Report`

The final decision must be one of:

- `GO`
- `CONDITIONAL GO`
- `NO-GO`
- `HOLD`

### Required Handoff Contract

Every role transition must use:

```text
Role Handoff

From Role:
To Role:
Project:
Task:
Input:
Work Completed:
Output:
Validation Performed:
Open Risks:
Blocking Issues:
Required Next Action:
```

Incomplete handoffs must be rejected.

### Failure Routing

When a gate fails:

```text
Gate Failure
↓
Identify Responsible Engineer
↓
Return Finding or Defect
↓
Apply Fix
↓
Independent Revalidation
↓
Regression Testing
↓
Resume Skill Workflow
```

A failed gate must not be skipped.

### Theme Creation Final Report

Produce:

```text
Theme Creation Final Report

Theme:
Purpose:
Theme Type:

Requirements Status:

Knowledge Used:

Project Architecture Status:

Theme Architecture Status:

Role Routing Status:

Roles Used:

Files Created:

Files Modified:

Implementation Reports:

Security Status:

Performance Status:

QA Status:

Documentation Status:

Release Status:

Known Limitations:

Residual Risks:

Final Result:

Next Step:
```

### Completion Criteria

The `Create Theme` Skill is complete only when:

- requirements are defined
- knowledge is selected
- architecture is approved
- role routing is complete
- required implementation work is complete
- implementation reports exist
- security validation passed
- performance validation passed
- QA validation passed
- documentation is complete
- release review passed when production release is intended

## Rule

The Create Theme Skill must use the WordPress Pipeline, Role Manager, Role Routing Matrix, specialist engineering roles, independent validation gates, QA, documentation, and release review as one controlled workflow. It must not jump directly from a theme request to code generation.