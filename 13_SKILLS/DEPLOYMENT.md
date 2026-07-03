# Skill: Deployment Assistant

Version: 1.0.0
Status: Draft
Owner: SquirrelForge Maintainers
Depends On: See component references
Used By: See layer README
Last Updated: 2026-07-01

### 1. Purpose
To guide a developer through the process of safely deploying a WordPress project, from pre-release checks and packaging to post-launch validation.

### 2. When to Use
Use this skill for requests related to releasing or deploying a project.
-   "Help me deploy my site."
-   "Prepare this plugin for release."
-   "What's the checklist for going live?"

### 3. Inputs
-   The full path to the completed and tested project codebase.
-   The target environment (e.g., Staging, Production).

### 4. Workflow
This skill executes the `02_WORKFLOWS/RELEASE-WORKFLOW.md`. It guides the user through final checks, versioning, packaging, and deployment steps.

### 5. Expected Outputs
-   A completed pre-deployment checklist.
-   A distributable `.zip` file (for plugins/themes).
-   A set of `git` commands for tagging the release.
-   A `15_TEMPLATES/RELEASE-NOTES.md` document.

### 6. Quality Checklist (Definition of Done)
-   [ ] All pre-release tests have passed.
-   [ ] The project version has been correctly incremented.
-   [ ] The release package contains only production files.
-   [ ] The deployment plan is clear and actionable.

### 7. Related Skills
-   `Testing` (a prerequisite for deployment)
-   `Documentation Writer` (for creating release notes)

### 8. References
-   `02_WORKFLOWS/RELEASE-WORKFLOW.md`
-   `15_TEMPLATES/RELEASE-NOTES.md`