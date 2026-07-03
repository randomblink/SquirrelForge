# SquirrelForge Action Selector

## Purpose

The Action Selector evaluates possible actions and recommends the next best action for the AI Driver. It considers user goals, platform state, available resources, risks, governance policies, and execution history to determine the most appropriate course of action.

The Action Selector does not execute actions or bypass platform controls. It produces ranked recommendations that are subsequently validated, governed, and executed through the appropriate SquirrelForge layers.

---

# Responsibilities

- Evaluate candidate actions.
- Rank possible solutions.
- Balance benefits and risks.
- Consider execution constraints.
- Recommend the next best action.
- Support multi-step planning.
- Avoid unnecessary actions.
- Record action selection activity.
- Support explainability.
- Follow AI governance requirements.

---

# Inputs

The Action Selector receives:

- Structured goals
- Planning results
- Platform context
- Memory summaries
- Knowledge results
- Workflow state
- Available tools
- Resource availability
- Governance policies
- Safety constraints

---

# Outputs

The Action Selector produces:

- Selected action
- Ranked action alternatives
- Action rationale
- Planning requests
- Tool selection requests
- Validation requests
- Governance review requests
- Action selection audit records

---

# Action Selection Workflow

1. Receive structured goal.
2. Gather current execution context.
3. Identify candidate actions.
4. Evaluate each candidate.
5. Compare expected outcomes.
6. Assess risks and constraints.
7. Rank available actions.
8. Select the preferred action.
9. Record audit information.
10. Submit action for validation.

---

# Supported Action Types

The Action Selector may recommend:

- Planning actions
- Research actions
- Tool actions
- Workflow actions
- User clarification
- Memory retrieval
- Knowledge retrieval
- Validation actions
- Automation requests
- Completion actions

---

# Evaluation Criteria

Candidate actions are evaluated using:

- Goal alignment
- Expected success probability
- Risk level
- Resource requirements
- Time requirements
- User impact
- Platform state
- Security implications
- Governance compliance
- Observability support

---

# Action Ranking

Each candidate action is evaluated for:

- Benefit
- Cost
- Complexity
- Urgency
- Dependency requirements
- Safety
- Reliability
- Reversibility
- Explainability
- Overall suitability

---

# Multi-Step Reasoning

The Action Selector supports:

- Sequential planning
- Incremental execution
- Branch evaluation
- Conditional actions
- Recovery actions
- Alternative strategies
- Goal refinement
- Task continuation

---

# Integration Responsibilities

The Action Selector coordinates with:

- AI Driver
- Goal Interpreter
- Tool Selector
- Context Builder
- Planning Layer
- Execution Layer
- Safety systems
- AI Driver Governance

---

# Data Protection

The Action Selector must:

- Protect decision context.
- Preserve evaluation evidence.
- Enforce governance policies.
- Protect confidential operational data.
- Maintain audit integrity.

---

# Safety Rules

The Action Selector must never:

- Recommend unauthorized actions.
- Ignore governance requirements.
- Bypass safety controls.
- Recommend unsafe shortcuts.
- Hide alternative options when relevant.
- Fabricate evaluation evidence.
- Execute actions directly.

---

# Failure Handling

If action selection fails:

- Preserve candidate evaluations.
- Record selection failure.
- Return the safest available option or request clarification.
- Notify the AI Driver.
- Escalate persistent failures.
- Maintain audit continuity.

---

# Audit Requirements

Every action selection records:

- Action selection ID
- Timestamp
- Goal ID
- Candidate actions
- Selected action
- Ranking criteria
- Governance status
- Final outcome

---

# Success Criteria

The Action Selector succeeds when:

- The recommended action best supports the user's goal.
- Risks are appropriately balanced.
- Alternative actions are considered.
- Recommendations remain explainable.
- Governance requirements are enforced.
- Safety constraints are respected.
- Audit records remain complete.
