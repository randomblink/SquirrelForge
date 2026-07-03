# SquirrelForge Context Builder

## Purpose

The Context Builder assembles the working context used by the AI Driver to make informed decisions. It gathers, validates, filters, prioritizes, and organizes relevant information from across SquirrelForge so that every decision is based on the most useful, current, and appropriate context.

The Context Builder does not make decisions or execute actions. It prepares structured context for the AI Driver, Action Selector, Planning Layer, and other reasoning components.

---

# Responsibilities

- Build working context.
- Retrieve relevant information.
- Filter unnecessary information.
- Prioritize contextual relevance.
- Merge multiple context sources.
- Detect missing context.
- Maintain context consistency.
- Record context generation activity.
- Support explainability.
- Follow AI governance requirements.

---

# Inputs

The Context Builder receives:

- Structured goals
- User requests
- Memory summaries
- Knowledge retrieval results
- Workflow state
- Platform state
- User preferences
- Active conversations
- Governance policies
- Safety constraints

---

# Outputs

The Context Builder produces:

- Structured working context
- Context summaries
- Context relevance rankings
- Missing context reports
- Planning context
- Decision context
- Explanation context
- Context audit records

---

# Context Building Workflow

1. Receive context request.
2. Identify required information.
3. Retrieve relevant context.
4. Filter unrelated information.
5. Prioritize useful context.
6. Merge context sources.
7. Detect missing information.
8. Generate structured context package.
9. Record audit information.
10. Deliver context to the AI Driver.

---

# Context Sources

The Context Builder gathers information from:

- Working memory
- Long-term memory
- Knowledge Layer
- Workflow Engine
- Observability Layer
- Learning Layer
- User profile and preferences
- Platform configuration
- Execution history
- Governance policies

---

# Context Components

Every context package includes:

- Context ID
- Goal summary
- Relevant memories
- Relevant knowledge
- Current workflow state
- Platform state
- Constraints
- Assumptions
- Confidence level
- Metadata

---

# Context Prioritization

Context is prioritized using:

- Goal relevance
- Recency
- Reliability
- Confidence
- User importance
- Workflow importance
- Governance relevance
- Safety relevance
- Operational impact
- Historical success

---

# Context Validation

Before delivery, the Context Builder verifies:

- Context completeness
- Source validity
- Data freshness
- Consistency
- Duplicate removal
- Governance compliance
- Permission requirements

---

# Missing Context Handling

If essential context is unavailable, the Context Builder must:

- Identify missing information.
- Determine whether safe defaults exist.
- Request retrieval from appropriate layers.
- Notify the AI Driver.
- Avoid inventing critical context.

---

# Integration Responsibilities

The Context Builder coordinates with:

- AI Driver
- Goal Interpreter
- Action Selector
- Memory Layer
- Knowledge Layer
- Planning Layer
- Observability Layer
- Learning Layer
- AI Driver Governance

---

# Data Protection

The Context Builder must:

- Protect confidential information.
- Enforce access permissions.
- Exclude unauthorized data.
- Preserve context integrity.
- Maintain audit records.

---

# Safety Rules

The Context Builder must never:

- Fabricate context.
- Ignore governance restrictions.
- Expose confidential information.
- Merge incompatible contexts.
- Remove required safety information.
- Modify historical records.
- Execute actions directly.

---

# Failure Handling

If context building fails:

- Preserve retrieved information.
- Record context generation failures.
- Identify missing context.
- Notify the AI Driver.
- Escalate persistent failures.
- Maintain audit continuity.
- Deliver partial context only when governance permits.

---

# Audit Requirements

Every context generation operation records:

- Context generation ID
- Timestamp
- Goal ID
- Context sources
- Missing information
- Confidence level
- Governance status
- Final outcome

---

# Success Criteria

The Context Builder succeeds when:

- Relevant information is consistently assembled.
- Context remains accurate and current.
- Irrelevant information is filtered out.
- Missing context is clearly identified.
- Governance requirements are enforced.
- Decision quality is improved.
- Audit records remain complete.
