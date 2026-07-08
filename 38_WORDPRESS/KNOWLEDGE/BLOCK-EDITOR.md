Status: Stable

---
# SquirrelForge WordPress Block Editor Knowledge

## Purpose

Defines knowledge for custom blocks, block supports, editor assets, dynamic rendering, serialization, and compatibility.

## Review Areas

Review block registration, metadata, attributes, serialization, dynamic rendering, scripts, styles, REST data usage, block supports, deprecations, transforms, accessibility, and performance.

## Output

This Knowledge file must support:

- block editor implementation guidance;
- block compatibility review;
- block serialization and rendering checks;
- block accessibility and performance review;
- and block deprecation or migration analysis.

## Validation Requirements

Block editor guidance is valid only when:

- block registration and `block.json` metadata are consistent;
- attributes serialize and deserialize safely;
- dynamic rendering preserves frontend output;
- editor assets and frontend assets load only where needed;
- REST data usage is authorized and sanitized;
- accessibility requirements are considered;
- performance impact is reviewed;
- and deprecated block behavior remains backward compatible when required.

## Handoff Rules

- Block implementation issues route to `33_WORDPRESS_ROLES/BLOCK-ENGINEER.md`.
- Accessibility concerns route to the applicable accessibility review owner.
- Performance-sensitive block behavior routes to `33_WORDPRESS_ROLES/PERFORMANCE-ENGINEER.md`.
- Security-sensitive REST, rendering, or data-handling behavior routes to `33_WORDPRESS_ROLES/SECURITY-ENGINEER.md`.

## Completion Criteria

This Knowledge file is complete when block editor work can be reviewed for registration, serialization, rendering, assets, accessibility, performance, and compatibility without relying on unstated assumptions.

## Rule

Block editor work must preserve valid serialization, editor stability, frontend rendering, accessibility, and backward compatibility.
