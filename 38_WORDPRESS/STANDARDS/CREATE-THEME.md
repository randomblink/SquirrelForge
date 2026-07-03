# Skill: Create Theme

## Purpose

This document defines the end-to-end process SquirrelForge must follow to create a new, production-ready WordPress theme from a user request.

## Core Principle

Creating a theme is a systematic process focused on presentation, layout, and user experience. The process ensures the final product is compliant, accessible, secure, and performant.

---

## Required Inputs

- A user request describing the desired theme's purpose, style (e.g., classic, block, minimalist, portfolio), and key features.

## Expected Outputs

- A complete, installable WordPress theme in a ZIP archive.
- A full set of source code adhering to all project standards.
- A final report detailing the build process, validation results, and testing plan.

---

## Workflow

This skill follows the master `PIPELINE.md`.

1.  **Intent Analysis**:
    -   Deconstruct the user request into a structured task: `Task: create_theme, Type: block_theme, Style: minimalist_blog`.

2.  **Knowledge Selection**:
    -   The `Knowledge Manager` is invoked with the task type.
    -   It selects required documents, such as `THEME-HANDBOOK.md`, `BLOCK-EDITOR.md` (if a block theme), `ACCESSIBILITY.md`, and `CODING-STANDARDS.md`.

3.  **Requirements Builder**:
    -   Generate functional requirements (e.g., "Must have a dark mode toggle," "Must support a primary navigation menu," "Must include templates for single posts and pages").
    -   Generate non-functional requirements (e.g., "Must be responsive," "Must pass Theme Check," "Must have accessible color contrast").

4.  **Architecture Planning**:
    -   The `Theme Architect` receives the requirements.
    -   It designs the theme's architecture, defining the file structure (`theme.json`, `templates/`, `parts/`), template hierarchy, and asset organization.

5.  **Implementation Planning**:
    -   Break the architecture down into a concrete, step-by-step plan (e.g., "1. Define color palette in `theme.json`. 2. Create `header.html` template part. 3. Assemble `index.html` template...").

6.  **Code Generation**:
    -   Execute the plan, generating the `theme.json`, HTML templates, and any necessary PHP or JS files.

7.  **Security Validation**:
    -   The `Security Validator` scans the code, focusing on output escaping in any PHP templates and sanitization of any customizer options.
    -   **Gate**: The pipeline halts if any critical security issues are found.

8.  **Standards Validation**:
    -   The `Standards Validator` checks the code against all relevant standards, including a check with the official Theme Check rules.
    -   **Gate**: The pipeline halts if there are major violations.

9.  **Testing Plan**:
    -   The `Testing Planner` generates a `TESTING.md` file with a checklist for manual verification (e.g., "Does the homepage render correctly?", "Is the navigation menu working on mobile?").

10. **Code Review**:
    -   The `Code Reviewer` performs a final logical pass, checking for accessibility issues, responsiveness problems, and other UX concerns.

11. **Documentation Update**:
    -   The `Documentation Generator` creates the `README.md`, `CHANGELOG.md`, and populates the `style.css` header.

12. **Final Approval**:
    -   The `WordPress Manager` reviews all reports. If all gates pass, it approves the build, packages the theme files into a ZIP, and generates the final report.

---

## Agent Rules

1.  **Follow the Pipeline**: This skill must be executed by following the `PIPELINE.md` stages in order.
2.  **Gate Enforcement**: A failure at any validation or review stage must halt the process and trigger a revision cycle.
3.  **Strict Separation**: The agent must not generate plugin-territory functionality (like CPTs) within the theme. It should propose a companion plugin instead.