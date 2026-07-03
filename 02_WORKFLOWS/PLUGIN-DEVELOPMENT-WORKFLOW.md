# Plugin Development Workflow

Version: 1.0.0
Status: Draft
Owner: SquirrelForge Maintainers
Depends On: See component references
Used By: See layer README
Last Updated: 2026-07-01

## Purpose
This workflow defines the standard, end-to-end process SquirrelForge follows to create a new, production-ready WordPress plugin from concept to completion.

### Phase 1 — Planning & Scaffolding

1.  **Define Core Concept:**
    -   What is the plugin's primary purpose?
    -   Who is the target user?
2.  **Establish Identity:**
    -   Determine the Plugin Name (e.g., "Awesome Image Gallery").
    -   Determine the Plugin Slug / Text Domain (e.g., "awesome-image-gallery").
    -   Determine the PHP Namespace (e.g., `AwesomeImageGallery`).
3.  **Scaffold the Project:**
    -   Use the `Standard-Plugin` template to generate the initial file structure.
    -   Populate all template variables with the established identity information.

**Deliverable:** A complete, empty plugin shell with a standard file structure.

### Phase 2 — Core Architecture

1.  **Review Main Plugin File:** Ensure constants, activation/deactivation hooks, and the main class instantiation are correct.
2.  **Configure `composer.json`:** Adjust metadata and define any third-party PHP dependencies.
3.  **Set Up Autoloading:** If using Composer, ensure the PSR-4 autoloader is configured correctly.
4.  **Plan Core Classes:** Design the primary PHP classes that will power the plugin (e.g., `Admin`, `Public`, `Shortcodes`, `API`).

**Deliverable:** A stable, architected foundation for the plugin.

### Phase 3 — Feature Implementation

For each distinct feature the plugin requires:

1.  **Initiate Feature Workflow:** Execute the `FEATURE-DEVELOPMENT.md` workflow.
2.  **Follow All Phases:** Systematically proceed through the Understand, Analyze, Design, Build, Verify, and Report phases for that specific feature.
3.  **Integrate:** Ensure the new feature is properly hooked into the core plugin architecture.
4.  **Repeat:** Continue this process for all required features.

**Deliverable:** A fully functional plugin with all features implemented.

### Phase 4 — Testing & Quality Assurance

1.  **Holistic Review:** Perform a final code review of the entire plugin against `CODE-REVIEW-WORKFLOW.md`.
2.  **Error Checking:** Enable `WP_DEBUG` and ensure the plugin operates without generating any PHP errors, warnings, or notices.
3.  **PHPUnit Tests:** If applicable, write and run unit tests for critical functions.

**Deliverable:** A stable, tested, and high-quality plugin.

### Phase 5 — Documentation & Packaging

1.  **Finalize `readme.txt`:** Write the plugin description, installation steps, FAQ, and changelog.
2.  **Generate Translation File:** Create the `.pot` file so the plugin can be translated.
3.  **Prepare for Distribution:** Remove development files (e.g., `.git`, `.vscode`) and create the final `.zip` file.

**Deliverable:** A distributable `.zip` file and complete documentation.

### Phase 6 — Final Report

1.  **Summarize the Plugin:** Provide a high-level overview of the completed plugin and its features.
2.  **List Deliverables:** Confirm the creation of the plugin `.zip` file.
3.  **Suggest Next Steps:** Recommend actions like "Upload to a test server for final validation" or "Submit to the WordPress.org repository."

**Deliverable:** A comprehensive project completion report.