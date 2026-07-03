# Skill: Project Planner

Version: 1.0.0
Status: Draft
Owner: SquirrelForge Maintainers
Depends On: See component references
Used By: See layer README
Last Updated: 2026-07-01

### 1. Purpose
To transform a vague user idea or high-level goal into a structured, actionable project plan with clear milestones, deliverables, and success criteria.

### 2. When to Use
Use this skill when the user's request is broad and requires planning before development can begin.
-   "I want to build a WordPress plugin."
-   "Help me plan out a new theme."
-   "What's the best way to build a [feature]?"
-   "I have an idea for a site, where do I start?"

### 3. Inputs
-   A high-level project idea or goal (e.g., "An e-commerce site for selling digital art").

### 4. Workflow
1.  **Clarify the Vision:** Ask probing questions to understand the core concept, target audience, and key objectives.
2.  **Define Scope:** Determine what is "in-scope" and "out-of-scope" for the project's first version (MVP).
3.  **Propose Architecture:** Use the `19_REASONING/DECISION-MATRIX.md` to recommend a high-level technical approach (e.g., "Use a Custom Post Type for 'Artworks' and WooCommerce for payments").
4.  **Outline Milestones:** Break the project down into logical, sequential milestones.
5.  **Identify Risks:** List potential technical challenges or dependencies.
6.  **Synthesize Plan:** Compile all information into a formal project plan document using the templates in `11_SKILLS/14_PROJECT_PLANNING/`.

### 5. Expected Outputs
-   A structured **Project Plan** document containing:
    -   **Project Brief:** A high-level summary of the project's goals and scope.
    -   **Milestone Plan:** A breakdown of the project into sequential milestones.
    -   **Risk Assessment:** A list of identified risks and potential mitigation strategies.
    -   **Success Criteria:** A list of measurable conditions that define project success.

### 6. Quality Checklist (Definition of Done)
-   [ ] The project goal is clearly defined.
-   [ ] The scope is agreed upon.
-   [ ] The proposed architecture is sound and follows WordPress best practices.
-   [ ] The user has confirmed the plan before proceeding to development.

### 7. Related Skills
-   `Plugin Development`
-   `Theme Development`
-   `WooCommerce Specialist`

### 8. References
-   `19_REASONING/DECISION-MATRIX.md`
-   `01_RULES/AGENT-BEHAVIOR.md`
