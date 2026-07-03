# SquirrelForge WordPress File Structure Rules

## Purpose

The File Structure Rules define the required folder and file patterns for WordPress plugins, themes, child themes, blocks, and supporting assets created or reviewed by SquirrelForge.

## Responsibilities

- Define required WordPress plugin files.
- Define required WordPress theme files.
- Define optional but recommended folders.
- Prevent disorganized or unsafe file layouts.
- Ensure generated code has predictable locations.
- Support inspection, testing, and future maintenance.

---

## Standard Plugin Structure

A SquirrelForge WordPress plugin should use this structure unless the project requires a smaller layout:

```text
plugin-name/
├── plugin-name.php
├── README.md
├── readme.txt
├── uninstall.php
├── includes/
│   ├── class-plugin-name.php
│   ├── class-activator.php
│   ├── class-deactivator.php
│   └── class-loader.php
├── admin/
│   ├── class-admin.php
│   ├── views/
│   ├── css/
│   └── js/
├── public/
│   ├── class-public.php
│   ├── css/
│   └── js/
├── languages/
├── assets/
└── tests/
Required Plugin Files
File	Required	Purpose
plugin-name.php	Yes	Main plugin bootstrap file.
README.md	Yes	Developer-facing project documentation.
readme.txt	Recommended	WordPress.org-style plugin metadata.
uninstall.php	Recommended	Cleanup when plugin is deleted.
includes/class-plugin-name.php	Recommended	Main plugin controller class.
includes/class-loader.php	Recommended	Registers actions and filters.
includes/class-activator.php	Recommended	Activation setup.
includes/class-deactivator.php	Recommended	Deactivation cleanup.
Standard Theme Structure

A SquirrelForge WordPress theme should use this structure unless the project requires a block theme layout:

theme-name/
├── style.css
├── functions.php
├── index.php
├── README.md
├── screenshot.png
├── header.php
├── footer.php
├── sidebar.php
├── single.php
├── page.php
├── archive.php
├── search.php
├── 404.php
├── template-parts/
├── assets/
│   ├── css/
│   ├── js/
│   └── images/
├── inc/
└── languages/
Required Theme Files
File	Required	Purpose
style.css	Yes	Theme stylesheet and theme header metadata.
functions.php	Yes	Theme setup, assets, supports, hooks.
index.php	Yes	Fallback template.
README.md	Yes	Developer-facing documentation.
screenshot.png	Recommended	WordPress admin theme preview.
Block Theme Structure

A block theme should use this structure:

theme-name/
├── style.css
├── functions.php
├── theme.json
├── index.php
├── templates/
│   ├── index.html
│   ├── single.html
│   ├── page.html
│   └── archive.html
├── parts/
│   ├── header.html
│   └── footer.html
├── patterns/
├── assets/
└── README.md
Required Block Theme Files
File	Required	Purpose
style.css	Yes	Theme header metadata.
theme.json	Yes	Global styles and settings.
templates/index.html	Yes	Main block template.
functions.php	Recommended	Theme setup and enqueue logic.
README.md	Yes	Developer-facing documentation.
Naming Rules
Plugin folders must use lowercase kebab-case.
Theme folders must use lowercase kebab-case.
PHP class files should use class-name.php or class-component-name.php.
PHP class names should be prefixed with the project namespace or project prefix.
Functions must use a unique project prefix.
Assets should be grouped by type.
Admin-only code must not be mixed with public frontend code unless the project is intentionally simple.
Forbidden Patterns

SquirrelForge must not approve WordPress projects that:

Place all code in one large file without a reason.
Mix admin, public, and core logic without separation.
Use generic function names without a prefix.
Omit the main plugin file or theme metadata.
Store generated files in unclear folders.
Put secrets, API keys, or credentials in committed files.
Modify WordPress core files.
Depend on production-only paths.
Lack a clear README.
Validation Checklist

Before approving a WordPress project structure, confirm:

The project has the required root files.
Folder names are predictable.
Admin and public logic are separated.
Assets are organized.
Language files have a location.
Tests or manual QA files have a location.
Activation, deactivation, and uninstall responsibilities are clear for plugins.
Theme templates are present for the intended theme type.
No WordPress core files are modified.

Next file after this:

```text
32_WORDPRESS/SECURITY-VALIDATOR.md
