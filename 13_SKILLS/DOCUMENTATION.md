# Skill: Documentation Writer

Version: 1.0.0
Status: Draft
Owner: SquirrelForge Maintainers
Depends On: See component references
Used By: See layer README
Last Updated: 2026-07-01

### 1. Purpose
To create clear, comprehensive, and user-friendly documentation for a WordPress project, feature, or codebase.

### 2. When to Use
Use this skill for any request related to creating documentation.
-   "Write the docs for this plugin."
-   "Create a `README.md` file for my project."
-   "Document this function with PHPDoc."

### 3. Inputs
-   The full path to the project or file to be documented.
-   The target audience (e.g., End-Users, Developers).

### 4. Workflow
This skill executes the `02_WORKFLOWS/DOCUMENTATION-WORKFLOW.md`. It analyzes the code and requirements to generate user guides, developer documentation, or inline code comments.

### 5. Expected Outputs
-   A well-structured Markdown file (e.g., `README.md`, `CONTRIBUTING.md`, user guide) based on templates from `15_TEMPLATES/`.
-   PHPDoc blocks added to functions, classes, and methods in the source code.
-   A final report summarizing the documentation created.

### 6. Quality Checklist (Definition of Done)
-   [ ] The documentation is accurate and reflects the current state of the project.
-   [ ] The language is clear, concise, and appropriate for the target audience.
-   [ ] All major features and functions are documented.
-   [ ] The documentation is well-formatted and easy to read.

### 7. Related Skills
-   `Plugin Developer` (documentation is a key part of the development lifecycle)
-   `Theme Developer`

### 8. References
-   `02_WORKFLOWS/DOCUMENTATION-WORKFLOW.md`
-   `15_TEMPLATES/`
-   WordPress PHPDoc Standards