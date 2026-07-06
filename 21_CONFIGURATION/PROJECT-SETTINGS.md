# SquirrelForge Project Settings

Version: 1.0.0
Status: Stable
Owner: Project Owner
Depends On: `21_CONFIGURATION/DEFAULTS.md`, `01_RULES`, `14_ENGINE/WORKFLOW-SELECTOR.md`, `23_GOVERNANCE`
Used By: Engine, Execution, Workflows
Last Updated: 2026-07-06

Project settings define project identity, root, technology profile, standards, required workflows, test commands, release policy, and allowed overrides. Each override must state its source and must not weaken mandatory governance or security policy.

Project Settings references and selects; it does not author these. Standards reference the applicable rules already defined in `01_RULES`; required workflows reference the workflows `14_ENGINE/WORKFLOW-SELECTOR.md` already defines, not a competing selection; and release policy references the policy `23_GOVERNANCE` already approves. Project identity, root, technology profile, test commands, and default overrides remain Project Settings' own content.
