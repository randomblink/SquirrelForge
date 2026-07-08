Status: Stable

---
# SquirrelForge WordPress Agent Pipeline

## Purpose

This document defines the complete, end-to-end lifecycle for every WordPress development task undertaken by SquirrelForge. It ensures that every request is processed through a consistent, predictable, and quality-gated sequence of steps, from initial analysis to final approval.

## Core Principle

A robust process produces a robust product. By standardizing the workflow, we ensure that every piece of generated code is planned, built, validated, tested, reviewed, and documented according to the project's high standards.

---

## The Agent Pipeline

Every significant development task must pass through these stages in order.

1.  **User Request**
    -   **Input**: A high-level goal from the user (e.g., "Build a contact form plugin").

2.  **Intent Analysis**
    -   **Responsibility**: Deconstruct the user's request into a clear, actionable, and specific engineering task.
    -   **Output**: A structured task definition (e.g., `Task: create_plugin, Type: contact_form`).

3.  **Knowledge Manager**
    -   **Responsibility**: Based on the task definition, select the required set of knowledge and standards documents.
    -   **Output**: A curated context of relevant reference material.

4.  **Requirements Builder**
    -   **Responsibility**: Using the curated knowledge, expand the task into a detailed list of functional and non-functional requirements.
    -   **Output**: A formal requirements specification.

5.  **Plugin/Theme Architect**
    -   **Responsibility**: Design the high-level software architecture based on the requirements.
    -   **Output**: An architectural blueprint, including file structure, class diagrams, and data models.

6.  **Task Planner**
    -   **Responsibility**: Break the architectural blueprint down into a sequence of small, concrete implementation steps.
    -   **Output**: A step-by-step execution plan.

7.  **Code Generator**
    -   **Responsibility**: Execute the plan, generating code for each step while adhering to the loaded knowledge and standards.
    -   **Output**: The initial draft of the source code.

8.  **Security Validator**
    -   **Responsibility**: Statically analyze the generated code for security vulnerabilities.
    -   **Output**: A security validation report. Code with critical failures is rejected and sent back for revision.

9.  **Standards Validator**
    -   **Responsibility**: Statically analyze the code for adherence to architecture, coding, and naming standards.
    -   **Output**: A standards compliance report. Non-compliant code is sent back for revision.

10. **Testing Planner**
    -   **Responsibility**: Generate a checklist of manual and automated tests required to verify the implementation.
    -   **Output**: A formal testing plan.

11. **Code Reviewer**
    -   **Responsibility**: Perform an automated review of the code for common mistakes, anti-patterns, and logic errors that validators might miss.
    -   **Output**: A code review report with recommendations.

12. **Refactoring Advisor**
    -   **Responsibility**: Based on the review, suggest specific, non-critical improvements to the code for clarity, maintainability, or performance.
    -   **Output**: A list of recommended refactoring tasks.

13. **Documentation Generator**
    -   **Responsibility**: Generate all required documentation for the completed code, including PHPDoc, `README.md`, and user guides.
    -   **Output**: The complete documentation set.

14. **Final Approval**
    -   **Responsibility**: The `WORDPRESS-MANAGER` performs a final check to ensure all pipeline stages have passed successfully.
    -   **Output**: An approved, documented, and validated implementation ready for release.

---

## Rule

No code may be considered complete or ready for release until it has successfully passed through every stage of this pipeline. Any failure in a validation, testing, or review stage must be addressed before the pipeline can proceed.
