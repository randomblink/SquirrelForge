# SquirrelForge Tool Selector

Version: 1.0.0
Status: Stable
Owner: AI Driver Maintainers
Depends On: `21_CONFIGURATION/TOOL-CONFIG.md`, `21_CONFIGURATION/PERMISSIONS.md`, `27_OBSERVABILITY/HEALTH-REPORTER.md`
Used By: `19_REASONING/AI-DRIVER.md`, `20_EXECUTION/ACTION-DISPATCHER.md`
Last Updated: 2026-07-07

## Purpose

The Tool Selector identifies and recommends the most appropriate internal capability, external integration, API, plugin, service, or AI tool required to complete the action selected by `19_REASONING/DECISION-ENGINE.md`.

The Tool Selector evaluates available tools based on capability, availability, permissions, reliability, performance, cost, security, governance, and operational context. It does not invoke tools directly — the selected tool proceeds through `20_EXECUTION/ACTION-DISPATCHER.md` for actual execution.

The Tool Selector makes the runtime selection decision only. It does not register tool configuration (owned by `21_CONFIGURATION/TOOL-CONFIG.md`), grant or deny permissions (owned by `21_CONFIGURATION/PERMISSIONS.md`), or monitor tool health (owned by `27_OBSERVABILITY/HEALTH-REPORTER.md`) — it combines those three inputs to determine which registered, permitted, healthy tool to use for a specific request.

---

## Responsibilities

- Identify candidate tools from `21_CONFIGURATION/TOOL-CONFIG.md`'s registered tools.
- Evaluate tool capabilities against the requested action.
- Verify tool availability via `27_OBSERVABILITY/HEALTH-REPORTER.md`.
- Check permissions and authorization via `21_CONFIGURATION/PERMISSIONS.md`.
- Compare tool performance.
- Balance cost and efficiency.
- Recommend the best tool.
- Support fallback selection.
- Record tool selection activity.

---

## Inputs

The Tool Selector receives:

- Selected action (from `19_REASONING/DECISION-ENGINE.md`)
- Structured goal
- Registered tool configuration (from `21_CONFIGURATION/TOOL-CONFIG.md`)
- Permission decisions (from `21_CONFIGURATION/PERMISSIONS.md`)
- Tool health status (from `27_OBSERVABILITY/HEALTH-REPORTER.md`)
- Integration catalog (from `26_INTEGRATIONS`)
- AI model availability (from `34_AIDRIVER/MODEL-ROUTER.md`)
- Governance policies (from `23_GOVERNANCE/POLICY-ENGINE.md`)

---

## Outputs

The Tool Selector produces:

- Tool selection recommendations
- Ranked tool alternatives
- Tool execution requests (handed to `20_EXECUTION/ACTION-DISPATCHER.md`)
- Fallback recommendations
- Governance review requests
- Tool selection audit records

---

## Tool Selection Workflow

1. Receive action request from `19_REASONING/DECISION-ENGINE.md`.
2. Identify required capabilities.
3. Discover candidate tools registered in `21_CONFIGURATION/TOOL-CONFIG.md`.
4. Verify availability via `27_OBSERVABILITY/HEALTH-REPORTER.md` and permissions via `21_CONFIGURATION/PERMISSIONS.md`.
5. Evaluate candidate tools.
6. Rank alternatives.
7. Select preferred tool.
8. Define fallback options.
9. Record audit information.
10. Hand the selection to `20_EXECUTION/ACTION-DISPATCHER.md` for execution.

---

## Evaluation Criteria

Tool evaluation considers:

- Capability match
- Availability (per `27_OBSERVABILITY/HEALTH-REPORTER.md`)
- Performance
- Latency
- Cost
- Reliability
- Permission status (per `21_CONFIGURATION/PERMISSIONS.md`)
- Governance compliance
- Operational risk

---

## Fallback Strategy

The Tool Selector maintains:

- Primary tool
- Secondary tool
- Emergency fallback
- Manual alternative
- Unsupported capability notification

Fallback selection must preserve governance and safety requirements.

---

## Integration Responsibilities

The Tool Selector coordinates with:

- `19_REASONING/AI-DRIVER.md`
- `19_REASONING/DECISION-ENGINE.md`
- `34_AIDRIVER/MODEL-ROUTER.md`
- `20_EXECUTION/ACTION-DISPATCHER.md`
- `26_INTEGRATIONS`
- `24_SECURITY/AUTHORIZATION-MANAGER.md`
- `34_AIDRIVER/AI-DRIVER-GOVERNANCE.md`

---

## Safety Rules

The Tool Selector must never:

- Recommend a tool not registered in `21_CONFIGURATION/TOOL-CONFIG.md`.
- Ignore permission requirements from `21_CONFIGURATION/PERMISSIONS.md`.
- Select a tool reported unhealthy by `27_OBSERVABILITY/HEALTH-REPORTER.md`.
- Bypass governance policies.
- Execute tools directly.
- Fabricate tool capabilities.

---

## Failure Handling

If tool selection fails:

- Preserve evaluation data.
- Record selection failure.
- Attempt approved fallback selection.
- Notify `19_REASONING/AI-DRIVER.md`.
- Escalate persistent failures.
- Maintain audit continuity.

---

## Audit Requirements

Every tool selection records:

- Tool selection ID
- Timestamp
- Goal ID
- Action ID
- Candidate tools
- Selected tool
- Fallback tools
- Permission status
- Health status
- Final outcome

---

## Success Criteria

The Tool Selector succeeds when:

- The selected tool best satisfies the requested action.
- Only registered, permitted, healthy tools are selected.
- Alternative tools are available when appropriate.
- Cost, performance, and reliability are balanced.
- Audit records remain complete.

---

## Permission Boundary

The Tool Selector may evaluate registered tools against a specific action and select which one to use, including fallback selection.

It must not register tool configuration, grant or deny permissions, monitor health, or execute tools — those remain owned by `21_CONFIGURATION/TOOL-CONFIG.md`, `21_CONFIGURATION/PERMISSIONS.md`, `27_OBSERVABILITY/HEALTH-REPORTER.md`, and `20_EXECUTION/ACTION-DISPATCHER.md` respectively.

---

## Domain Rule

Tool selection applies identically regardless of domain; domain-specific tools are registered and selected through the existing configuration and selection mechanism, not a separate domain-specific selector.

---

## Rule

A tool is usable only when it is registered, permitted, and healthy; the Tool Selector must never select a tool that fails any of those three checks.
