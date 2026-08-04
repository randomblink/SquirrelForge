Status: Stable

---
# SquirrelForge WordPress Internationalization Knowledge

## Purpose

Defines knowledge for translation readiness, localization, text-domain usage, and locale-safe output across plugins, themes, and blocks.

## Review Areas

Review text-domain declaration and consistency, translation function usage, string extraction readiness, escaping of translated output, date/number/currency locale handling, RTL layout support, and `.pot`/`.po`/`.mo` file handling.

## Output

This Knowledge file must support:

- internationalization review notes;
- translation-readiness classification;
- text-domain and loading corrections;
- locale-safe formatting recommendations;
- and internationalization validation handoff.

## Validation Requirements

Internationalization guidance is valid only when:

- all user-facing strings are wrapped in the correct translation function (`__()`, `_e()`, `_x()`, `_n()`, etc.) with a literal, static text domain matching the plugin or theme slug;
- translation functions are never called with dynamically concatenated or variable-only strings;
- translated output is still escaped for its rendering context (e.g. `esc_html__()`, `esc_attr__()`) rather than escaped and translated separately in a way that drops either protection;
- dates, numbers, and currency are formatted using locale-aware WordPress functions rather than hardcoded formats;
- layout and CSS remain usable under RTL locales where applicable;
- and translation files are loaded at the correct hook with a text domain matching the declared header.

## Handoff Rules

- Missing or incorrect translation function usage routes to the relevant `38_WORDPRESS/ROLES/PHP-ENGINEER.md` or `38_WORDPRESS/ROLES/JAVASCRIPT-ENGINEER.md` implementation owner.
- Escaping regressions discovered during internationalization review route to `38_WORDPRESS/ROLES/SECURITY-ENGINEER.md`.
- RTL and layout issues route to `38_WORDPRESS/ROLES/CSS-ENGINEER.md`.
- Text-domain and `.pot` file documentation route to `38_WORDPRESS/ROLES/DOCUMENTATION-ENGINEER.md`.

## Completion Criteria

This Knowledge file is complete when internationalization work can be reviewed for text-domain correctness, translation function usage, locale-safe formatting, and RTL support.

## Rule

Translatable strings must use the correct translation function with a static, matching text domain, and translated output must still be escaped for its rendering context.
