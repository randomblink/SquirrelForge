# Feature Development Workflow

Version: 1.0.0
Status: Draft
Owner: SquirrelForge Maintainers
Depends On: See component references
Used By: See layer README
Last Updated: 2026-07-01

## Purpose

This workflow defines the standard process SquirrelForge follows when implementing a new feature.

## Phase 1 — Understand

Before writing code:

1.  **Read the request completely.**
2.  **Identify the desired outcome.** What does success look like?
3.  **Ask questions** if important requirements are missing or ambiguous.
4.  **Identify affected files** and sections of the codebase.
5.  **Identify possible risks** (e.g., breaking changes, security implications).

**Deliverable:** A clear, confirmed understanding of the feature requirements.

## Phase 2 — Analyze

Review the existing project to inform the implementation strategy.

Look for:

1.  **Existing architecture:** How is similar code structured?
2.  **Similar features:** Can existing patterns be reused?
3.  **Reusable code:** Are there functions or classes that can be leveraged?
4.  **Dependencies:** Will this feature depend on other parts of the code or external libraries?
5.  **WordPress APIs:** Which core APIs should be used (`WP_Query`, Settings API, REST API, etc.)?

**Deliverable:** A high-level implementation plan.

## Phase 3 — Design

Determine the technical specifics of the implementation.

1.  **Files:** Which files will be created or modified?
2.  **Classes & Functions:** What new classes, methods, or functions are needed?
3.  **Hooks:** Which WordPress actions or filters will be used? Will new custom hooks be created?
4.  **Database:** Are there any database changes (new options, custom tables, post meta)?
5.  **User Interface:** What are the UI/UX changes?
6.  **Security:** How will the feature be secured (nonces, capability checks, sanitization, escaping)?
7.  **Accessibility:** How will accessibility be ensured?

**Deliverable:** A detailed technical design.

## Phase 4 — Build

Implement the feature according to the design.

Requirements:

1.  **Follow project standards** and the rules in `01_RULES/WORDPRESS-RULES.md`.
2.  **Keep changes focused** on the feature being built.
3.  **Write readable, self-documenting code.**
4.  **Avoid unnecessary complexity.**

**Deliverable:** A working implementation of the feature.

## Phase 5 — Verify

Review the completed work to ensure quality. Check that all requirements are met and the code is secure, performant, and well-documented.

**Deliverable:** A verified and quality-checked feature.

## Phase 6 — Report

Summarize the work performed, list the files affected, and recommend the next logical step for the user.

**Deliverable:** A comprehensive completion report.