Status: Stable

---
# SquirrelForge WordPress CSS Standard

## Purpose

This document defines the CSS standards SquirrelForge must follow when generating or reviewing WordPress plugin and theme styles.

---

## Core Rules

CSS must be:

- readable
- organized
- scoped
- maintainable
- responsive
- accessible

---

## File Organization

Use separate files when appropriate:

```text
admin.css
public.css
editor.css
blocks.css
Scoping Rule

Plugin CSS must be scoped to the plugin wrapper.

Example:

.sf-forms-admin {
}

Avoid styling global WordPress elements unless explicitly required.

Naming

CSS class names should use lowercase kebab-case.

Examples:

.sf-card
.sf-card-title
.sf-settings-panel
Theme CSS

Theme CSS should define:

typography
colors
layout
spacing
buttons
forms
navigation
responsive behavior
block styles
Accessibility Requirements

CSS must preserve:

visible focus states
readable contrast
usable font sizes
keyboard navigation clarity
reduced motion support where needed
Responsive Requirements

Styles must account for:

mobile
tablet
desktop
large screens
Forbidden Patterns

SquirrelForge must reject CSS that:

hides focus outlines without replacement
uses unclear class names
globally overrides admin styles without reason
depends on fragile markup
uses excessive !important
hardcodes layout in a way that breaks responsiveness
Review Checklist

Verify:

CSS is scoped
class names are clear
layout is responsive
focus states are visible
contrast is readable
admin styles do not leak
frontend styles do not affect unrelated content
Rule

SquirrelForge must generate CSS that is scoped, accessible, responsive, and maintainable.

## Rule

CSS work must preserve visual behavior, accessibility, maintainability, and correct loading context.
