# SquirrelForge WordPress Refactoring Standard

## Purpose

This document defines the standards and guidelines SquirrelForge must follow when refactoring existing WordPress code. The goal is to improve the internal structure of the code without changing its external behavior, making it more maintainable, readable, and efficient.

---

# Core Principles

- **Improve, Don't Break**: Refactoring should make the code better without introducing new bugs or altering functionality.
- **Small, Safe Steps**: Prefer a series of small, verifiable changes over a single large, risky one.
- **Test-Driven**: Existing tests must pass before and after refactoring. If tests don't exist, a testing plan is a prerequisite for significant refactoring.
- **Clarity is Key**: The primary goal of most refactoring is to make the code easier for the next developer (or agent) to understand.

---

# Triggers for Refactoring

Refactoring should be considered when:

- **Preparing to add a new feature**: Clean up the existing code to make it easier to extend.
- **Fixing a bug**: Refactor the surrounding code to make the fix simpler and more robust.
- **During code review**: Address code smells or maintainability issues identified in the review process.
- **Reducing technical debt**: Systematically improve a component that is known to be complex or difficult to work with.

---

# Common Refactoring Targets (Code Smells)

- **Duplicated Code**: Identical or very similar code blocks in multiple places.
    - **Refactoring**: Extract the duplicate code into a new, shared method or function.
- **Long Method**: A function or method that has grown too large and has too many responsibilities.
    - **Refactoring**: Decompose the method into several smaller, well-named private methods, each with a single responsibility.
- **Large Class (God Class)**: A class that does too much and has too many dependencies.
    - **Refactoring**: Extract related responsibilities into new, smaller, more cohesive classes.
- **Complex Conditionals**: Deeply nested `if/else` or `switch` statements.
    - **Refactoring**: Replace with polymorphism (strategy pattern), guard clauses, or lookup tables.
- **Primitive Obsession**: Using primitive data types (strings, integers) to represent domain concepts (e.g., using a string for a status).
    - **Refactoring**: Introduce a dedicated value object or class to represent the concept.
- **Inappropriate Intimacy**: One class knows too much about the internal details of another.
    - **Refactoring**: Move methods or fields to reduce the coupling and improve encapsulation.

---

# Refactoring Workflow

1.  **Identify Target**: Select a specific piece of code to refactor.
2.  **Ensure Tests Exist**: Verify that there are tests covering the current behavior. If not, create a testing plan first.
3.  **Make a Small Change**: Apply a single, small refactoring (e.g., extract method, rename variable).
4.  **Run Tests**: Run all relevant tests to ensure no behavior has changed.
5.  **Repeat**: Continue making small, tested changes until the desired improvement is achieved.

---

# Forbidden Patterns

- **Refactoring and Adding Features Simultaneously**: This makes it impossible to distinguish between bugs introduced by the refactoring and bugs in the new feature.
- **Large-Scale, Untested Changes**: Rewriting a large component without a safety net of tests.
- **Changing the Public API**: Refactoring should primarily affect the internal structure. Changes to the public API are a breaking change and must be planned separately.
- **"Refactoring" as an Excuse for a Rewrite**: A complete rewrite is not a refactoring; it's a new project.

---

# Agent Rules

1.  **Justify the Refactoring**: Before refactoring, the agent must state *why* the change is being made (e.g., "Refactoring to reduce duplication before adding the new 'export' feature").
2.  **Prioritize Safety**: The agent must confirm that a testing strategy exists before beginning any significant refactoring.
3.  **Log Each Step**: For complex refactorings, the agent should log each small change and the successful test run that followed it.
4.  **Follow Standards**: All refactored code must adhere to the `ARCHITECTURE-STANDARD.md` and other relevant standards.
5.  **Propose, Don't Assume**: The `Refactoring Advisor` in the pipeline should propose refactorings as part of a code review report, not apply them automatically without approval, unless it's part of a bug-fixing task.