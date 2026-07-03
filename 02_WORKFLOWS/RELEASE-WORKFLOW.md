# Release Workflow

Version: 1.0.0
Status: Draft
Owner: SquirrelForge Maintainers
Depends On: See component references
Used By: See layer README
Last Updated: 2026-07-01

## Purpose

This workflow defines the standard process the `AGENT-RELEASE` follows to prepare, package, and deploy a new version of a WordPress project.

### Phase 1 — Pre-Release Validation

1.  **Confirm Quality Gates:** Verify that all required reviews (`Code Review`, `Security`, `Performance`) have been completed and approved.
2.  **Final Test Run:** Ensure all automated tests are passing on the release branch.
3.  **Confirm Documentation:** Verify that all user-facing documentation has been updated to reflect the new changes.

**Deliverable:** A "Go" or "No-Go" decision for the release.

### Phase 2 — Versioning & Changelog

1.  **Increment Version:** Update the version number in the main plugin/theme file and `package.json` according to Semantic Versioning rules.
2.  **Compile Release Notes:** Generate a `15_TEMPLATES/RELEASE-NOTES.md` document using the template, summarizing all new features, fixes, and improvements.
3.  **Update Changelog:** Add a new entry to the project's main changelog file.

**Deliverable:** Updated version numbers and release documentation.

### Phase 3 — Packaging

1.  **Create Build:** Run any necessary build scripts (e.g., `npm run build`) to compile assets.
2.  **Generate Package:** Create a distributable `.zip` file of the project.
3.  **Exclude Dev Files:** Ensure the final package does not contain development files or directories (e.g., `.git`, `node_modules`, source maps).

**Deliverable:** A clean, production-ready `.zip` file.

### Phase 4 — Tagging & Deployment

1.  **Commit Release:** Commit all changes (version bumps, changelog) to the repository.
2.  **Create Git Tag:** Create a new, annotated Git tag for the release version (e.g., `git tag -a v1.2.0 -m "Version 1.2.0"`).
3.  **Push to Remote:** Push the commits and the new tag to the remote repository.
4.  **Deploy:** Provide instructions for deploying the `.zip` package to the target environment.

**Deliverable:** A tagged release in the repository and a deployment plan.

### Phase 5 — Post-Release Validation

1.  **Smoke Test:** Perform a quick check of the live environment to ensure the deployment was successful and the site is operational.
2.  **Monitor Logs:** Check server and application logs for any new errors post-deployment.

**Deliverable:** Confirmation of a successful deployment.