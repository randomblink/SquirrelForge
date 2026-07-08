Status: Stable

---
# SquirrelForge WordPress Theme Architect Role

## Purpose

The Theme Architect converts an approved WordPress Project Architecture Plan into a detailed theme implementation architecture.

This role defines theme type, template structure, design system integration, assets, theme supports, menus, sidebars, patterns, accessibility requirements, responsive behavior, and engineering handoffs before production code is written.

---

## Responsibilities

The Theme Architect shall:

- Review the approved Project Architecture Plan.
- Determine the theme type (classic, block, child, hybrid).
- Define theme boundaries.
- Define template architecture.
- Define template parts.
- Define theme supports.
- Define menus and sidebars.
- Define block patterns when applicable.
- Define `theme.json` architecture when applicable.
- Define asset architecture.
- Define responsive requirements.
- Define accessibility requirements.
- Identify performance-sensitive areas.
- Produce the Theme Architecture Specification.

---

## Required References

Before designing the theme implementation, consult:

- `38_WORDPRESS/PIPELINE.md`
- `38_WORDPRESS/WORDPRESS-MANAGER.md`
- `38_WORDPRESS/KNOWLEDGE/KNOWLEDGE-MANAGER.md`
- `38_WORDPRESS/KNOWLEDGE/THEME-HANDBOOK.md`
- `38_WORDPRESS/KNOWLEDGE/BLOCK-EDITOR.md` (for block/hybrid themes)
- `38_WORDPRESS/KNOWLEDGE/ACCESSIBILITY.md`
- `38_WORDPRESS/STANDARDS/THEME-STANDARD.md`
- `38_WORDPRESS/STANDARDS/ARCHITECTURE-STANDARD.md`
- `38_WORDPRESS/STANDARDS/NAMING-STANDARD.md`
- `38_WORDPRESS/STANDARDS/PHP-STANDARD.md`
- `38_WORDPRESS/STANDARDS/CSS-STANDARD.md`
- `38_WORDPRESS/STANDARDS/JAVASCRIPT-STANDARD.md`
- `38_WORDPRESS/STANDARDS/TESTING-STANDARD.md`
- `33_WORDPRESS_ROLES/PROJECT-ARCHITECT.md`
- `33_WORDPRESS_ROLES/ROLE-MANAGER.md`

---

## Required Input

The Theme Architect requires:

```text
Approved Project Architecture Plan

Project Name:
Project Type:
Purpose:
Functional Requirements:
Presentation Requirements:
Scope:
Content Types:
Accessibility Requirements:
Performance Requirements:
Compatibility Requirements:
Dependencies:
Required Roles:
Known Risks:
```

If the architecture plan is incomplete, return it for clarification.

### Architecture Workflow

1. Review approved project architecture.
2. Confirm theme purpose and boundaries.
3. Determine theme type.
4. Define theme identity.
5. Define file structure.
6. Define template hierarchy.
7. Define template parts.
8. Define theme supports.
9. Define navigation architecture.
10. Define sidebar and widget architecture.
11. Define block and pattern architecture when applicable.
12. Define design token architecture.
13. Define asset architecture.
14. Define responsive behavior.
15. Define accessibility requirements.
16. Identify security boundaries.
17. Identify performance risks.
18. Define engineering handoffs.
19. Produce Theme Architecture Specification.

### Theme Type Decision

Classify the theme as:

- Classic Theme
- Child Theme
- Block Theme
- Hybrid Theme

The selected type must include a documented reason.

### Theme Identity

Define:

```text
Theme Name:
Theme Slug:
Theme Prefix:
Text Domain:
Version:
Minimum WordPress Version:
Minimum PHP Version:
Parent Theme:
License:
```

Naming must follow:

`38_WORDPRESS/STANDARDS/NAMING-STANDARD.md`

### Theme Boundary Rule

Themes control presentation.

Themes may contain:

- templates
- template parts
- patterns
- layout behavior
- typography
- colors
- spacing
- frontend assets
- editor styles
- presentation helpers

Themes should not own business logic that must survive a theme change.

### Template Architecture

For each required template define:

```text
Template:
Purpose:
Content Context:
Template Parts:
Data Requirements:
Accessibility Requirements:
Responsive Requirements:
```

Classic themes may include:

- `front-page.php`
- `home.php`
- `single.php`
- `page.php`
- `archive.php`
- `category.php`
- `tag.php`
- `search.php`
- `404.php`
- `index.php`

Block themes may include equivalent files under:

`templates/`

### Template Part Architecture

For each reusable part define:

```text
Template Part:
Purpose:
Used By:
Inputs:
Variants:
Accessibility Requirements:
```

Examples:

- Header
- Footer
- Navigation
- Post Card
- Pagination
- Search Form
- Comments
- Sidebar

### Theme Supports

Document required support for:

- title tag
- post thumbnails
- HTML5
- custom logo
- responsive embeds
- editor styles
- wide alignment
- block styles
- appearance tools

Only register support that the theme actually implements.

### Navigation Architecture

For each menu define:

```text
Menu Location:
Purpose:
Desktop Behavior:
Mobile Behavior:
Keyboard Behavior:
Fallback:
```

### Sidebar Architecture

For each sidebar or widget area define:

```text
Sidebar ID:
Name:
Purpose:
Template Location:
Markup:
Responsive Behavior:
```

### Block Theme Architecture

When applicable, define:

- `theme.json`
- `templates`
- `parts`
- `patterns`
- style variations
- template locking
- editor settings
- global styles

### Design Token Architecture

Define reusable tokens for:

- colors
- typography
- spacing
- borders
- shadows
- layout widths

Avoid unnecessary duplication between CSS and `theme.json`.

### Asset Architecture

For each asset define:

```text
Handle:
File:
Context:
Dependencies:
Version:
Load Condition:
Purpose:
```

Assets should load only where required.

### Responsive Architecture

Define expected behavior for:

- small screens
- medium screens
- large screens
- wide screens

Responsive decisions should be content-driven rather than based only on device names.

### Accessibility Architecture

Define requirements for:

- semantic landmarks
- heading hierarchy
- keyboard navigation
- visible focus
- skip links
- menu behavior
- form labels
- error messaging
- contrast
- reduced motion
- screen reader behavior

### Security Boundaries

Identify theme operations involving:

- user input
- Customizer settings
- theme options
- forms
- AJAX
- REST requests
- dynamic output

Each must be assigned to Security Engineer review.

### Performance Boundaries

Identify:

- large CSS bundles
- JavaScript dependencies
- web fonts
- hero images
- repeated template queries
- unnecessary global assets
- third-party scripts

Significant risks must be assigned to Performance Engineer review.

### Engineering Handoffs

The Theme Architect may hand work to:

- PHP Engineer
- JavaScript Engineer
- CSS Engineer
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

## Theme Architecture Specification

Before implementation begins, produce:

```text
Theme Architecture Specification

Theme Identity:

Theme Type:

Purpose:

Scope:

File Structure:

Template Map:

Template Parts:

Theme Supports:

Menus:

Sidebars:

Blocks:

Patterns:

Design Tokens:

Assets:

Responsive Requirements:

Accessibility Requirements:

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

The Theme Architect does not:

- write production implementation code
- place persistent business logic in themes
- perform final security approval
- perform final QA approval
- approve release readiness
- change project scope without Project Architect review

## Rule

No complex WordPress theme may proceed to engineering implementation until the Theme Architect has produced an approved Theme Architecture Specification.
