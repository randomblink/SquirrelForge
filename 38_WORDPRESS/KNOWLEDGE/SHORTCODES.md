Status: Stable

---
# SquirrelForge WordPress Shortcodes Knowledge

## Purpose

Defines knowledge for shortcode creation, rendering, attributes, escaping, compatibility, and documentation.

## Review Areas

Review naming, defaults, validation, sanitization, output escaping, nested behavior, asset requirements, caching, accessibility, and documentation.

## Output

This Knowledge file must support:

- shortcode implementation guidance;
- shortcode attribute and rendering review;
- shortcode security review;
- compatibility notes;
- and shortcode documentation requirements.

## Validation Requirements

Shortcode guidance is valid only when:

- shortcode names avoid collisions;
- attributes have defaults and validation;
- input is sanitized before use;
- output is escaped for the final rendering context;
- nested shortcode behavior is defined when supported;
- rendering avoids unsafe side effects;
- required assets are loaded intentionally;
- accessibility impact is reviewed;
- and user-facing behavior is documented.

## Handoff Rules

- Shortcode implementation issues route to the relevant PHP implementation role.
- Security-sensitive rendering, attributes, or database use route to `33_WORDPRESS_ROLES/SECURITY-ENGINEER.md`.
- Accessibility concerns route to the applicable accessibility review owner.
- Documentation changes route to `33_WORDPRESS_ROLES/DOCUMENTATION-ENGINEER.md`.

## Completion Criteria

This Knowledge file is complete when shortcode work can be reviewed for naming, attributes, sanitization, escaping, rendering behavior, accessibility, and documentation.

## Rule

Shortcodes must sanitize attributes, escape output for the final context, and avoid unsafe side effects during rendering.
