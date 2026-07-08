Status: Stable

---
# Skill: Write Documentation

## Purpose

This document defines the end-to-end process SquirrelForge must follow to generate comprehensive, accurate, and user-friendly documentation for a WordPress project.

## Core Principle

Documentation is a deliverable, not an afterthought. Good documentation makes software maintainable, usable, and trustworthy. The process must be systematic and based on the final, validated state of the code.

---

## Required Inputs

- A reference to the completed and validated code for a plugin, theme, or component.
- The final reports from the validation and testing stages of the pipeline.

## Expected Outputs

- A complete set of documentation files, including `README.md`, `CHANGELOG.md`, and `readme.txt` (if applicable).
- Fully documented source code with PHPDoc blocks.

---

## Workflow

This skill is typically one of the final stages in the master `PIPELINE.md`.

1.  **Intent Analysis**:
    -   Deconstruct the request: `Task: write_documentation, Target: my-plugin`.

2.  **Knowledge Selection**:
    -   The `Knowledge Manager` selects `DOCUMENTATION-STANDARD.md` and the relevant coding standards.

3.  **Code & Artifact Analysis**:
    -   Scan the final codebase to identify all public-facing elements: hooks, filters, shortcodes, REST endpoints, classes, and public methods.
    -   Analyze the project's version history or task log to build a changelog.

4.  **Documentation Generation**:
    -   **PHPDoc**: Generate or update PHPDoc blocks for every class, method, and function, ensuring all `@param` and `@return` tags are correct.
    -   **README.md**: Generate a `README.md` file based on the standard template, populating sections like "Features," "Installation," and "Usage" from the analyzed code.
    -   **CHANGELOG.md**: Generate a `CHANGELOG.md` file, organizing changes by version and type (`Added`, `Fixed`, etc.).
    -   **readme.txt**: If the project is a plugin for WordPress.org, generate a `readme.txt` file in the correct format.

5.  **Validation**:
    -   The `Standards Validator` checks the generated documentation for compliance with `DOCUMENTATION-STANDARD.md` (e.g., presence of required files, correct formats).

6.  **Final Report**:
    -   The final task report is updated to confirm that documentation has been generated and validated.

---

## Agent Rules

1.  **Document the Final State**: The agent must generate documentation based on the final, approved version of the code, not an intermediate draft.
2.  **Follow the Standard**: All generated documentation must strictly adhere to the formats and requirements defined in `DOCUMENTATION-STANDARD.md`.
3.  **Be Comprehensive**: The agent must ensure that all key aspects of the project (installation, usage, hooks, etc.) are documented in the `README.md`.
4.  **Be Accurate**: PHPDoc blocks must accurately reflect the parameters, return types, and purpose of the code they describe.

## Rule

WordPress documentation work must explain usage, configuration, behavior, limitations, and release impact.
