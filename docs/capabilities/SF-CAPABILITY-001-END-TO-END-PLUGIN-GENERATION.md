# SF-CAPABILITY-001 — End-to-End Plugin Generation

**Status:** Runtime demonstrated

## Purpose

Demonstrate that SquirrelForge can generate a complete WordPress plugin from an empty specification and prepare it for WordPress validation.

## Scope

This capability covers creation of a plugin folder, a valid plugin entry point, a Settings page protected by `manage_options`, escaped output, and a `readme.txt` file. Runtime activation and behavior require a separate recorded WordPress test.

## Procedure

1. Define the plugin name, slug, behavior, capability requirement, and required files.
2. Create the plugin directory and main PHP file.
3. Add a valid WordPress plugin header and `ABSPATH` protection.
4. Register the Settings page with `add_options_page()` and `manage_options`.
5. Escape displayed output with WordPress escaping functions.
6. Add `readme.txt` with installation and version information.
7. Run PHP syntax validation and inspect the generated file set.
8. Separately run the plugin through WordPress activation, page display, and deactivation controls when runtime evidence is required.

## Limitations

This demonstration does not prove compatibility with every WordPress or PHP version, administrator configuration, or hosting environment.

## Deliverables

- Complete plugin folder.
- Main plugin PHP file with valid header.
- `readme.txt`.
- Static validation results.
- Where performed, a separate runtime acceptance record covering detection, activation, Settings-page output, deactivation, and site health.

## Demonstration record

The SquirrelForge Hello plugin was generated under the Atheist local WordPress site and passed PHP syntax validation. Runtime acceptance was then performed in the same local WordPress installation: the plugin was detected, activated successfully, its Settings page was opened and reviewed, the expected greeting was confirmed, and it was deactivated successfully. WordPress remained healthy after deactivation.

Runtime acceptance was user-performed and recorded here from the reported result; it was not independently repeated by a separate reviewer.
