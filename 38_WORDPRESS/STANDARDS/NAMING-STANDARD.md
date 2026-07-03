# SquirrelForge WordPress Naming Standard

## Purpose

This document defines the mandatory naming conventions for WordPress plugins, themes, PHP symbols, files, hooks, options, database tables, REST APIs, assets, text domains, and documentation generated or reviewed by SquirrelForge.

Consistent naming prevents collisions, improves discovery, supports maintainability, and gives every generated project a predictable structure.

---

# General Naming Rules

Names must be:

- Clear and descriptive.
- Unique within their scope.
- Consistent across code, assets, configuration, and documentation.
- Prefixed or namespaced when exposed to WordPress or the global PHP runtime.
- Stable after public release unless a documented migration is provided.

Avoid unexplained abbreviations, generic global names, inconsistent casing, and temporary labels.

---

# Plugin Slugs

Plugin slugs must use lowercase kebab-case.

Examples:

```text
squirrelforge-forms
squirrelforge-security
```

---

# Theme Slugs

Theme slugs must use lowercase kebab-case.

Examples:

```text
squirrelforge-classic
squirrelforge-block
```

---

# PHP Namespaces

Use a vendor namespace whenever practical.

Pattern:

```text
Vendor\Product
```

Example:

```text
SquirrelForge\Forms
```

---

# PHP Class Names

Classes must use PascalCase.

Examples:

```text
Plugin
Loader
SettingsManager
AdminController
RestController
AssetManager
```

One class per file.

---

# File Names

PHP files:

```text
class-loader.php
class-settings-manager.php
class-rest-controller.php
```

Template files should follow WordPress conventions.

CSS:

```text
admin.css
public.css
editor.css
```

JavaScript:

```text
admin.js
public.js
editor.js
```

---

# Function Names

Functions must always be prefixed.

Pattern:

```text
pluginprefix_function_name()
```

Example:

```text
sf_forms_register_assets()
```

Generic names such as:

```text
save()
init()
setup()
```

must never exist in the global namespace.

---

# Hook Names

Custom hooks should include the project prefix.

Examples:

```text
sf_forms_before_save
sf_forms_after_save
```

---

# Option Names

Pattern:

```text
plugin_prefix_option_name
```

Example:

```text
sf_forms_settings
sf_forms_version
```

---

# Database Tables

Pattern:

```text
{$wpdb->prefix}plugin_slug_table
```

Example:

```text
wp_sf_forms_entries
```

---

# REST Namespaces

Pattern:

```text
plugin-slug/v1
```

Example:

```text
squirrelforge-forms/v1
```

Version changes should create new namespaces rather than breaking existing APIs.

---

# Asset Handles

Pattern:

```text
plugin-slug-admin
plugin-slug-public
plugin-slug-editor
```

Examples:

```text
squirrelforge-forms-admin
squirrelforge-forms-public
```

---

# Text Domains

Text domains should match the plugin or theme slug.

Example:

```text
squirrelforge-forms
```

---

# Image Assets

Use descriptive lowercase filenames.

Examples:

```text
icon-settings.svg
logo-dark.png
banner-1544x500.png
```

Avoid names like:

```text
image1.png
newlogo2.png
```

---

# Documentation Files

Use uppercase with hyphens.

Examples:

```text
README.md
CHANGELOG.md
CONTRIBUTING.md
LICENSE.md
```

---

# Validation Checklist

Verify that:

- Slugs are unique.
- Function names are prefixed.
- Class names use PascalCase.
- Files follow project conventions.
- REST namespaces are versioned.
- Option names use project prefixes.
- Custom hooks use project prefixes.
- Text domains match project slugs.

---

# Rule

Every generated WordPress project must follow this naming standard unless a documented project requirement overrides it.
