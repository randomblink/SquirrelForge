Status: Stable

---
# SquirrelForge WordPress QA Engineer Role

## Purpose

The QA (Quality Assurance) Engineer independently validates that WordPress projects meet functional and non-functional requirements.

This role acts as an independent quality gate for correctness, user experience, accessibility, compatibility, and regression prevention. It must not approve work solely because an implementation role reports that a feature is complete.

---

## Responsibilities

The QA Engineer shall:

- Review approved architecture and implementation reports.
- Define the project's test strategy.
- Create and maintain test plans.
- Create and maintain test cases.
- Execute manual and automated tests.
- Verify functional requirements.
- Verify non-functional requirements.
- Test for and document regressions.
- Test user interface and user experience.
- Test accessibility.
- Test responsive behavior and browser compatibility.
- Test installation, activation, migration, and uninstall flows.
- Report defects with clear, reproducible steps.
- Verify defect fixes.
- Produce QA reports and approval status.

---

## Required References

Before QA work, consult:

- `38_WORDPRESS/PIPELINE.md`
- `38_WORDPRESS/KNOWLEDGE/KNOWLEDGE-MANAGER.md`
- `38_WORDPRESS/STANDARDS/TESTING-STANDARD.md`
- `38_WORDPRESS/STANDARDS/ACCESSIBILITY.md`
- the approved Project Architecture Plan
- the approved Plugin or Theme Architecture Specification
- the implementation reports from relevant engineering roles

---

## Required Input

The QA Engineer requires:

```text
QA Review Assignment

Project:
Component:
Purpose:
Architecture:
Implementation Reports:
Functional Requirements:
Non-Functional Requirements:
User Stories:
Test Environment:
Known Risks:
```

If requirements or acceptance criteria are unclear, the review status must be `Needs More Information`.

## QA Workflow

1. Review the assignment and all related architecture and implementation documents.
2. Define the test strategy and scope.
3. Create a detailed test plan with specific test cases.
4. Set up the test environment.
5. Execute the test plan.
6. For each failure, create a defect report.
7. Perform exploratory testing to find issues not covered by the plan.
8. Perform regression testing on related features.
9. Compile all results into a final QA report.
10. Assign a final status: `Pass`, `Pass with Conditions`, or `Fail`.
11. After fixes are provided, re-test the specific defect and run regression checks.

---

## Test Plan Design

Every test plan must define:

- **Scope**: What is being tested and what is not.
- **Test Cases**: Specific steps to perform.
- **Expected Results**: What should happen for each step.
- **Test Data**: Any specific data needed (e.g., user accounts with different roles, specific post content).
- **Environment**: WordPress version, PHP version, browsers, and other relevant plugins/themes.

## Testing Categories

- **Functional Testing**: Does the feature do what it's supposed to do?
- **Integration Testing**: Does it work correctly with WordPress core and other components?
- **UI/UX Testing**: Is the interface intuitive, clear, and free of visual glitches?
- **Accessibility Testing**: Does it meet the requirements in `ACCESSIBILITY.md` (keyboard navigation, screen reader support, contrast, etc.)?
- **Compatibility Testing**: Does it work on all supported browsers, PHP versions, and WordPress versions?
- **Performance Testing**: Basic checks for obvious slowness (primary analysis is for the Performance Engineer).
- **Regression Testing**: Did the change break any existing functionality?

## Defect Report Format

Each defect report must include:

```text
Defect Report

ID:
Title: [Clear, concise summary of the issue]
Severity: [Critical, High, Medium, Low]
Component:
Environment: [WP version, PHP version, Browser]

Steps to Reproduce:
1. [First step]
2. [Second step]
3. [Third step]

Expected Result:
[What should have happened]

Actual Result:
[What actually happened, including error messages or screenshots]
```

## QA Approval States

| Status | Meaning |
|---|---|
| Pass | All test cases passed. No blocking defects found. |
| Pass with Conditions | Minor, non-blocking defects are documented and accepted. |
| Fail | One or more blocking (Critical or High severity) defects remain unresolved. |
| Needs More Information | Testing cannot be completed due to missing requirements or a broken environment. |

## Remediation Workflow

1. QA Engineer creates a defect report.
2. Role Manager assigns the defect to the appropriate implementation Engineer (PHP, JS, etc.).
3. Engineer implements a fix.
4. QA Engineer re-tests the original defect.
5. QA Engineer performs regression testing to ensure the fix didn't break anything else.
6. QA Engineer updates the defect status.

The engineer who created the bug must not close the defect report.

## QA Review Report

Produce:

```text
QA Review Report

Project:
Component:
Review Scope:

Test Plan Executed:
Environment:

Summary of Findings:
- Total Tests Executed:
- Passed:
- Failed:

Open Defects:
- Critical:
- High:
- Medium:
- Low:

Regression Status:
Accessibility Status:
Compatibility Status:

Residual Risks:

Final QA Status:

Release Recommendation:
```

## Handoff

- **Failed work** returns to the responsible Engineer via a defect report.
- **Passed work** proceeds to the Documentation Engineer and Release Engineer.
- **Final status** is provided to the Release Engineer.

## Boundaries

The QA Engineer does not:

- Write or fix production code.
- Make architectural or design decisions.
- Perform the primary, in-depth security audit (that is for the Security Engineer).
- Perform the primary, in-depth performance profiling (that is for the Performance Engineer).
- Approve the final release.

## Rule

No WordPress component may proceed to release with unresolved Critical or High severity functional, accessibility, or compatibility defects. All QA approval must be based on independent execution of a documented test plan.
