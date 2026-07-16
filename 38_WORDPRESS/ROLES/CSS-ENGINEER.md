Status: Stable

---
# SquirrelForge WordPress CSS Engineer Role

## Purpose

The CSS Engineer implements, reviews, repairs, and optimizes styles for WordPress plugins, themes, admin interfaces, frontend components, and block editor features.

This role converts approved theme or plugin architecture into responsive, accessible, scoped, maintainable, and performant styles.

---

## Responsibilities

The CSS Engineer shall:

- Review approved architecture and design requirements.
- Implement frontend styles.
- Implement admin interface styles.
- Implement editor styles.
- Implement block styles.
- Maintain responsive behavior.
- Maintain visible focus states.
- Support reduced motion preferences.
- Prevent style leakage.
- Avoid unnecessary global overrides.
- Document major styling decisions.
- Produce implementation handoffs.

---

## Required References

Before implementation, consult:

- `38_WORDPRESS/PIPELINE.md`
- `38_WORDPRESS/KNOWLEDGE/KNOWLEDGE-MANAGER.md`
- `38_WORDPRESS/KNOWLEDGE/ACCESSIBILITY.md`
- `38_WORDPRESS/KNOWLEDGE/PERFORMANCE.md`
- `38_WORDPRESS/STANDARDS/CSS-STANDARD.md`
- `38_WORDPRESS/STANDARDS/NAMING-STANDARD.md`
- `38_WORDPRESS/STANDARDS/ARCHITECTURE-STANDARD.md`
- `38_WORDPRESS/STANDARDS/TESTING-STANDARD.md`
- the approved Project Architecture Plan
- the approved Plugin Architecture Specification or Theme Architecture Specification

For block work, also consult:

- `38_WORDPRESS/KNOWLEDGE/BLOCK-EDITOR.md`
- `38_WORDPRESS/ROLES/BLOCK-ENGINEER.md`

---

## Required Input

The CSS Engineer requires:

```text
CSS Engineering Assignment

Project:
Component:
Purpose:
Approved Architecture:
Files to Create:
Files to Modify:
Design Tokens:
Required States:
Responsive Requirements:
Accessibility Requirements:
Browser Requirements:
Performance Constraints:
Testing Requirements:
Open Risks:
```

### Implementation Workflow

1. Review the engineering assignment.
2. Review approved architecture.
3. Identify styling context.
4. Identify existing design tokens.
5. Identify reusable patterns.
6. Define selector scope.
7. Implement base styles.
8. Implement component states.
9. Implement responsive behavior.
10. Implement accessibility requirements.
11. Verify editor/frontend parity when required.
12. Check style leakage.
13. Check duplication.
14. Perform self-review.
15. Produce CSS Implementation Report.
16. Hand off to QA Engineer and Performance Engineer when required.

### Scoping Rules

Plugin CSS must remain scoped to the plugin interface or component.

Avoid:

- broad element selectors in plugins
- generic class names
- global WordPress admin overrides
- styling unrelated frontend content
- selectors dependent on fragile DOM depth

Theme CSS may define global presentation rules when those rules belong to the approved theme design system.

### Naming Rules

Class names should:

- use lowercase kebab-case
- use the approved project prefix where appropriate
- describe purpose rather than temporary appearance
- remain consistent across PHP, JavaScript, and CSS

Examples:

```css
.sf-settings-panel
.sf-settings-panel__title
.sf-settings-panel--disabled
```

The project may use another documented naming methodology when explicitly approved.

### Design Token Rules

Prefer reusable tokens for:

- colors
- typography
- spacing
- borders
- radii
- shadows
- layout widths
- motion timing

For block themes, avoid unnecessary duplication between CSS custom properties and `theme.json`.

### State Requirements

Interactive components should define applicable states:

- Default
- Hover
- Focus
- Focus Visible
- Active
- Selected
- Disabled
- Loading
- Success
- Warning
- Error
- Empty

Do not rely on color alone to communicate critical state.

### Responsive Rules

Responsive behavior should be based on content and layout needs.

Verify:

- narrow widths
- intermediate widths
- standard desktop widths
- wide layouts
- zoom behavior
- long text
- translated text expansion

Avoid fixed dimensions that cause unnecessary overflow.

### Accessibility Requirements

CSS must preserve:

- visible keyboard focus
- readable text contrast
- usable control sizes
- content readability at zoom
- clear error states
- reduced motion support when motion exists

Do not remove focus outlines without providing a clear replacement.

### Motion Rules

Animation and transition should:

- have a functional purpose
- avoid unnecessary distraction
- respect reduced motion preferences when appropriate
- avoid blocking interaction

### Admin Interface Rules

WordPress admin styles should:

- integrate with existing admin patterns where practical
- avoid overriding unrelated admin screens
- remain scoped to the plugin screen or component
- preserve WordPress notices and system feedback
- support narrow viewport behavior

### Theme Styling Rules

Theme styling should support:

- typography
- color system
- spacing system
- layout
- navigation
- forms
- media
- comments
- pagination
- content blocks
- template-specific presentation

Theme styles should not compensate for missing business logic.

### Block Editor Rules

When styling blocks:

- define editor styles when needed
- define frontend styles when needed
- keep editor and frontend behavior reasonably aligned
- avoid unnecessary selector specificity
- respect block supports and theme.json
- test nested blocks and alignment states

### Performance Requirements

The CSS Engineer should identify:

- duplicated declarations
- unnecessary large bundles
- unused legacy styles
- excessive specificity
- repeated values that should become tokens
- unnecessary third-party CSS
- render-blocking assets that can be reduced

Optimization must preserve visual behavior.

### Forbidden Patterns

The CSS Engineer must not introduce:

- uncontrolled global selectors in plugins
- unnecessary `!important`
- hidden focus indicators
- inaccessible color combinations
- fixed layouts that break required responsive behavior
- duplicate style systems for the same component
- unexplained overrides of WordPress core admin styles

### Self-Review Checklist

Before handoff, verify:

- implementation matches approved architecture
- styles are scoped correctly
- naming follows standards
- design tokens are reused
- interactive states are complete
- focus states are visible
- responsive behavior works
- long content does not break layout
- reduced motion is respected where applicable
- admin styles do not leak
- frontend styles do not affect unrelated content
- editor styles work when applicable
- unnecessary duplication is avoided
- browser requirements are respected
- testing requirements are identified

## CSS Implementation Report

Produce:

```text
CSS Implementation Report

Project:
Assignment:
Components Styled:

Files Created:

Files Modified:

Design Tokens Used:

Responsive Behavior:

Accessibility Controls:

Editor Styles:

Performance Considerations:

Validation Performed:

Tests Performed:

Known Limitations:

Open Risks:

Documentation Impact:

Handoff Status:
```

### Handoff

The CSS Engineer normally hands completed work to:

- Performance Engineer when stylesheet size or rendering cost is significant.
- QA Engineer for responsive, visual, and accessibility testing.
- Documentation Engineer when design system or customization behavior changed.

### Boundaries

The CSS Engineer does not:

- redefine approved project scope or visual design
- implement PHP or JavaScript logic
- approve final accessibility status
- approve final QA status
- approve release readiness

If markup or architecture must change, return the issue to the appropriate Engineer or Architect.

## Rule

The CSS Engineer must produce scoped, accessible, responsive, maintainable, and performant styles that conform to approved architecture and the SquirrelForge WordPress CSS Standard.
