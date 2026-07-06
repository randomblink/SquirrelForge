# SquirrelForge Defaults

Version: 1.0.0
Status: Stable
Owner: Configuration Maintainers
Depends On: None
Used By: `21_CONFIGURATION/PROJECT-SETTINGS.md`, `21_CONFIGURATION/PERMISSIONS.md`, `14_ENGINE/VALIDATION.md`, `17_COORDINATION/FAILURE-RECOVERY.md`, `20_EXECUTION/EXECUTION-LOGGER.md`, `14_ENGINE` planning components
Last Updated: 2026-07-06

Default policy: deterministic planning where possible, least privilege, no destructive action without authorization, bounded retries, validation after each material phase, structured event logging, and project-local output unless configured otherwise.

These are baseline default values, not enforcement. Least privilege and authorization for destructive actions are enforced by `21_CONFIGURATION/PERMISSIONS.md`; validation after each material phase is performed by `14_ENGINE/VALIDATION.md`; retry limits are enforced by `17_COORDINATION/FAILURE-RECOVERY.md`; structured event logging is performed by `20_EXECUTION/EXECUTION-LOGGER.md`; deterministic planning is applied by the Engine's planning components. `21_CONFIGURATION/PROJECT-SETTINGS.md` may override a default, but an override must state its source and must not weaken mandatory governance or security policy.
