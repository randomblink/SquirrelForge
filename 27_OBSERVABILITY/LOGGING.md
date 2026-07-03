# SquirrelForge Logging Manager

## Purpose

The Logging Manager records structured system events generated throughout SquirrelForge, providing a consistent, searchable, and reliable history of workflow activity, execution, integrations, security events, and operational behavior.

---

## Responsibilities

- Record structured log events.
- Classify log severity.
- Preserve event context.
- Associate related events using correlation identifiers.
- Support operational troubleshooting.
- Enforce log retention policies.
- Protect sensitive information.
- Provide searchable log records.

---

## Logging Process

1. Receive log event.
2. Validate event structure.
3. Assign severity level.
4. Attach correlation identifiers.
5. Record timestamp.
6. Store structured log entry.
7. Confirm successful recording.

---

## Log Levels

| Level | Description |
|---|---|
| Trace | Highly detailed diagnostic information |
| Debug | Development and troubleshooting information |
| Info | Normal operational events |
| Warning | Unexpected but recoverable conditions |
| Error | Operation failed |
| Critical | System stability or security at risk |

---

## Log Record

| Field | Description |
|---|---|
| Log ID | Unique identifier |
| Correlation ID | Links related events |
| Component | Originating subsystem |
| Event Type | Category of activity |
| Severity | Trace / Debug / Info / Warning / Error / Critical |
| Timestamp | Event time |
| Message | Human-readable summary |
| Metadata | Structured contextual data |

---

## Logging Guidelines

- Use structured formats whenever possible.
- Include correlation identifiers for related events.
- Record sufficient context for troubleshooting.
- Exclude passwords, secrets, tokens, and sensitive personal information.
- Maintain consistent timestamps.
- Preserve chronological ordering.

---

## Retention Policy

| Log Type | Minimum Retention |
|---|---|
| Operational | 90 days |
| Security | 1 year |
| Audit | Defined by Audit Trail policy |
| Diagnostic | Configurable |
| Debug | Short-term unless promoted |

---

## Rule

Every significant operational event must generate a structured log entry containing sufficient context for troubleshooting, auditing, and operational analysis.
