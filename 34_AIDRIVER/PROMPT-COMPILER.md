# SquirrelForge Prompt Compiler

## Purpose

The Prompt Compiler constructs the final model-ready prompt used by the AI Driver. It combines system instructions, user requests, structured goals, context, memory, knowledge, tool definitions, safety rules, governance policies, output requirements, and model-specific formatting into a complete prompt package.

The Prompt Compiler does not reason, choose actions, execute tools, or select models. It prepares governed, consistent, optimized, and auditable prompts for delivery to the selected AI model.

---

# Responsibilities

- Assemble prompt components.
- Apply prompt templates.
- Inject structured goals.
- Inject relevant context.
- Include memory summaries.
- Include retrieved knowledge.
- Include approved tool definitions.
- Apply safety and governance constraints.
- Optimize token usage.
- Format prompts for model requirements.
- Validate prompt completeness.
- Record prompt compilation activity.

---

# Inputs

The Prompt Compiler receives:

- User request
- Structured goal
- Decision context
- Memory summaries
- Knowledge retrieval results
- Workflow state
- Platform state
- Tool definitions
- Output requirements
- Safety policies
- Governance policies
- Model capabilities
- Token limits

---

# Outputs

The Prompt Compiler produces:

- Compiled prompt
- Prompt package
- Prompt metadata
- Token estimate
- Context budget report
- Prompt validation status
- Prompt provenance record
- Prompt audit record

---

# Prompt Compilation Workflow

1. Receive prompt compilation request.
2. Identify target task type.
3. Load the correct prompt template.
4. Gather required prompt components.
5. Insert system instructions.
6. Insert safety and governance rules.
7. Insert user request and structured goal.
8. Insert relevant context.
9. Insert memory summaries.
10. Insert retrieved knowledge.
11. Insert approved tool definitions.
12. Insert output requirements.
13. Apply model-specific formatting.
14. Optimize token usage.
15. Validate compiled prompt.
16. Produce prompt package.
17. Record audit information.

---

# Prompt Lifecycle

Every prompt progresses through:

1. Requested
2. Template selected
3. Context assembled
4. Memory included
5. Knowledge included
6. Tools included
7. Safety applied
8. Governance applied
9. Model formatting applied
10. Token budget checked
11. Validated
12. Delivered
13. Archived

---

# Prompt Components

A compiled prompt may contain:

- System instructions
- Platform identity
- User request
- Structured goal
- Task type
- Relevant context
- Memory summary
- Retrieved knowledge
- Workflow state
- Tool definitions
- Safety constraints
- Governance policies
- Output format
- Completion criteria

---

# Template Management

Prompt templates define:

- Required sections
- Section order
- Formatting rules
- Task-specific instructions
- Model-specific instructions
- Tool-use instructions
- Structured output requirements
- Safety requirements
- Governance requirements

Templates must be versioned, reviewed, and governed.

---

# Context Assembly

Context assembly includes:

- Current user request
- Conversation context
- Active workflow state
- Platform state
- Relevant operational constraints
- Relevant preferences
- Known assumptions
- Missing information markers

The Prompt Compiler must include only context relevant to the current goal.

---

# Memory Injection

Memory injection may include:

- User preferences
- Prior decisions
- Historical outcomes
- Relevant project facts
- Long-term memory summaries
- Working memory summaries

Memory must be filtered for relevance, permission, and safety.

---

# Knowledge Injection

Knowledge injection may include:

- Retrieved documents
- Knowledge base results
- Reference summaries
- Rule documents
- Policy documents
- Technical documentation
- Verified facts

Knowledge must preserve source traceability where possible.

---

# Tool Definition Injection

Tool definitions may include:

- Tool name
- Tool purpose
- Required inputs
- Expected outputs
- Permission requirements
- Safety limits
- Failure behavior
- Usage constraints

Only approved and available tools may be included.

---

# Governance and Safety Injection

Every compiled prompt must include applicable:

- Safety rules
- Governance policies
- Permission limits
- Data protection requirements
- Audit requirements
- Execution boundaries
- Prohibited actions
- Escalation requirements

Safety and governance instructions must take priority over task instructions.

---

# Model-Specific Transformations

The Prompt Compiler adapts prompts based on:

- Model provider
- Context window
- Tool-calling format
- Structured output support
- Reasoning mode
- Function schema requirements
- System message support
- Multimodal capability
- Local or cloud execution requirements

---

# Token Budget Management

The Prompt Compiler manages token usage by:

- Estimating prompt size
- Reserving response space
- Prioritizing critical context
- Compressing low-priority context
- Removing duplicates
- Summarizing long references
- Splitting oversized requests when appropriate

---

# Context Window Optimization

When prompt content exceeds available space, the Prompt Compiler prioritizes:

1. Safety instructions
2. Governance policies
3. User request
4. Structured goal
5. Current workflow state
6. Required tool definitions
7. High-relevance context
8. High-relevance memory
9. High-relevance knowledge
10. Optional background context

---

# Prompt Optimization Techniques

Supported techniques include:

- Deduplication
- Summarization
- Context compression
- Section reordering
- Removal of irrelevant context
- Reference linking
- Token budgeting
- Template simplification
- Model-specific formatting

---

# Prompt Validation

Before delivery, the Prompt Compiler validates:

- Required sections are present.
- User intent is preserved.
- Safety rules are included.
- Governance policies are included.
- Tool definitions are authorized.
- Token usage is within limits.
- No prohibited information is included.
- Output requirements are clear.
- Prompt format matches model requirements.

---

# Structured Output Compilation

When structured output is required, the Prompt Compiler includes:

- Output schema
- Required fields
- Allowed values
- Formatting rules
- Validation expectations
- Error handling instructions

---

# Prompt Versioning

Prompt versioning tracks:

- Template version
- Policy version
- Safety rule version
- Tool definition version
- Model formatting version
- Compilation timestamp
- Change history

---

# Prompt Provenance

Prompt provenance records:

- Source request
- Goal ID
- Context sources
- Memory sources
- Knowledge sources
- Tool definitions included
- Governance policies applied
- Safety policies applied
- Template used

---

# Prompt Caching

Prompt caching may be used for:

- Repeated task templates
- Stable system instructions
- Stable governance instructions
- Stable tool definitions
- Reusable knowledge summaries

Cached prompt components must be invalidated when policies, tools, templates, or permissions change.

---

# Performance Metrics

The Prompt Compiler may track:

- Compilation time
- Prompt size
- Token estimate
- Context utilization
- Template usage
- Validation failure rate
- Prompt rejection rate
- Model response quality signals

---

# Integration Responsibilities

The Prompt Compiler coordinates with:

- AI Driver
- Goal Interpreter
- Context Builder
- Tool Selector
- Model Router
- Memory Layer
- Knowledge Layer
- Security Layer
- Observability Layer
- AI Safety Gate
- AI Driver Governance

---

# Data Protection

The Prompt Compiler must:

- Protect confidential information.
- Remove secrets and credentials.
- Enforce access permissions.
- Exclude unauthorized memory.
- Exclude unauthorized knowledge.
- Protect prompt provenance records.
- Maintain audit integrity.

---

# Safety Rules

The Prompt Compiler must never:

- Omit required safety rules.
- Omit required governance policies.
- Include unauthorized context.
- Leak confidential information.
- Modify user intent.
- Include unavailable tools.
- Ignore token limits.
- Send unvalidated prompts to a model.

---

# Failure Handling

If prompt compilation fails:

- Preserve compilation inputs.
- Record the failure.
- Identify the failed component.
- Notify the AI Driver.
- Return a blocked compilation state.
- Escalate persistent failures.
- Maintain audit continuity.
- Prevent incomplete prompts from reaching the model.

---

# Audit Requirements

Every prompt compilation records:

- Prompt compilation ID
- Timestamp
- Goal ID
- Template version
- Model target
- Context sources
- Memory sources
- Knowledge sources
- Tool definitions included
- Token estimate
- Validation status
- Governance status
- Final outcome

---

# Success Criteria

The Prompt Compiler succeeds when:

- Prompts are complete and model-ready.
- User intent is preserved.
- Relevant context is included.
- Irrelevant context is removed.
- Safety and governance are always included.
- Token usage remains within limits.
- Confidential information is protected.
- Prompt provenance remains traceable.
- Audit records remain complete.
