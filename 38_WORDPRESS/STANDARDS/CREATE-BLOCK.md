# Skill: Create Block

## Purpose

This document defines the end-to-end process SquirrelForge must follow to create a new, production-ready custom block for the WordPress Block Editor (Gutenberg).

## Core Principle

Creating a block is a JavaScript-heavy task that requires a modern build process and strict adherence to both WordPress and React best practices. The process ensures the final block is functional, user-friendly, and well-integrated with the editor.

---

## Required Inputs

- A user request describing the block's purpose, appearance, and settings.

## Expected Outputs

- A complete, functional custom block, typically scaffolded within a new or existing plugin.
- All necessary PHP, JavaScript (ESNext/JSX), and CSS/Sass files.
- A final report detailing the build process and testing plan.

---

## Workflow

This skill follows the master `PIPELINE.md`.

1.  **Intent Analysis**:
    -   Deconstruct the request: `Task: create_block, Name: "Team Member", Attributes: [name, title, photo]`.

2.  **Knowledge Selection**:
    -   The `Knowledge Manager` selects `BLOCK-EDITOR.md`, `JAVASCRIPT-STANDARD.md`, `CODING-STANDARDS.md`, `ACCESSIBILITY.md`, and `SECURITY.md`.

3.  **Requirements Builder**:
    -   Generate functional requirements (e.g., "Must have an editable name and title," "Must include an image selector for the photo," "Must have a color picker for the background").
    -   Generate non-functional requirements (e.g., "Must render correctly on the frontend," "Must be accessible").

4.  **Architecture Planning**:
    -   Define the block's architecture using a `block.json` file. This includes the name, title, category, icon, attributes, and script/style handles.
    -   Plan the file structure (`src/index.js`, `src/edit.js`, `src/save.js`, `src/editor.scss`, `style.scss`).

5.  **Implementation Planning**:
    -   Break the architecture down into a concrete plan (e.g., "1. Create `block.json`. 2. Implement the `edit` component in `edit.js` with InspectorControls. 3. Implement the `save` component in `save.js`. 4. Write PHP to register the block type from `block.json`...").

6.  **Code Generation**:
    -   Generate the PHP, JSX, and Sass files. This step assumes a modern JavaScript build process (`@wordpress/scripts`) is in place to compile the assets.

7.  **Security Validation**:
    -   The `Security Validator` scans the PHP registration code and any server-side rendering callbacks for security issues (e.g., escaping attributes).

8.  **Standards Validation**:
    -   The `Standards Validator` checks the code against all relevant standards, including JavaScript and CSS standards.

9.  **Testing Plan**:
    -   The `Testing Planner` generates a `TESTING.md` file with a checklist for manual verification (e.g., "Can you add the block to the editor?", "Do the block's controls work?", "Does the block save and render correctly on the frontend?").

10. **Code Review**:
    -   The `Code Reviewer` performs a final logical pass, checking for React best practices, accessibility in the editor, and overall user experience.

11. **Documentation Update**:
    -   The `Documentation Generator` creates or updates the `README.md` to include information about the new block.

12. **Final Approval**:
    -   The `WordPress Manager` reviews all reports. If all gates pass, it approves the build and generates the final report.

---

## Agent Rules

1.  **Use `block.json`**: The agent must use the `block.json` metadata file as the canonical source of truth for defining a block.
2.  **Separate Edit and Save**: The agent should generate separate `edit.js` and `save.js` components to maintain a clear separation of concerns.
3.  **Assume a Build Step**: The agent's process assumes a modern JavaScript build tool (like `@wordpress/scripts`) will be used to compile the source JSX and Sass into browser-ready assets.