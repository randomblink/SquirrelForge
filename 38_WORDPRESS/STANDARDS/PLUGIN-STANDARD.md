# SquirrelForge WordPress Plugin Standard

## Purpose

This document defines the default plugin architecture SquirrelForge should use when generating WordPress plugins.

This standard turns WordPress plugin knowledge into a consistent SquirrelForge implementation pattern.

---

## Default Plugin Structure

```text
plugin-name/
├── plugin-name.php
├── README.md
├── readme.txt
├── uninstall.php
├── includes/
│   ├── class-plugin.php
│   ├── class-loader.php
│   ├── class-activator.php
│   ├── class-deactivator.php
│   ├── class-settings-manager.php
│   ├── class-asset-manager.php
│   └── class-i18n.php
├── admin/
│   ├── class-admin-controller.php
│   ├── views/
│   ├── css/
│   └── js/
├── public/
│   ├── class-public-controller.php
│   ├── css/
│   └── js/
├── languages/
├── assets/
└── tests/
Required Root Files
File	Purpose
plugin-name.php	Main bootstrap file.
README.md	Developer documentation.
readme.txt	WordPress plugin metadata.
uninstall.php	Safe cleanup behavior.
Required Classes
Class	Purpose
Plugin	Main runtime controller.
Loader	Registers actions and filters.
Activator	Runs activation setup.
Deactivator	Runs deactivation cleanup.
SettingsManager	Registers and validates settings.
AssetManager	Handles asset registration and enqueueing.
I18n	Loads translation files.
AdminController	Handles admin screens.
PublicController	Handles frontend behavior.
Bootstrap Requirements

The main plugin file must:

include a plugin header
block direct access
define version constants
define path and URL constants
load required class files
register activation hook
register deactivation hook
initialize the main plugin class
Security Requirements

Every plugin must follow:

32_WORDPRESS/SECURITY-VALIDATOR.md
32_WORDPRESS/KNOWLEDGE/SECURITY.md
32_WORDPRESS/STANDARDS/NAMING-STANDARD.md

Critical requirements:

sanitize incoming data
escape outgoing data
check capabilities before admin actions
verify nonces before state-changing actions
prepare SQL queries
avoid hardcoded secrets
Asset Requirements

Assets must be registered and enqueued through WordPress APIs.

Frontend assets must not load in admin unless required.

Admin assets must not load globally unless required.

Settings Requirements

Settings must use WordPress settings APIs where practical.

Each setting must define:

name
default value
sanitization callback
capability requirement
display location
Lifecycle Requirements

Activation may:

create default options
create custom tables
schedule cron events
set version markers

Deactivation may:

unschedule cron events
remove temporary behavior
flush rewrite rules only when needed

Uninstall may:

remove options
remove custom tables
clear scheduled events

User data must not be deleted on deactivation.

Documentation Requirements

Each plugin must document:

purpose
installation
file structure
hooks
settings
shortcodes
REST endpoints
database changes
testing steps
Testing Requirements

Before approval, verify:

plugin activates without fatal errors
plugin deactivates without fatal errors
settings save correctly
admin pages respect capabilities
frontend output is escaped
assets load only where needed
uninstall behavior is safe
no WordPress core files are modified
Rule

SquirrelForge must generate plugins using this standard unless a project-specific architecture is explicitly documented and approved.
