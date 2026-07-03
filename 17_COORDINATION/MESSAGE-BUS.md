# SquirrelForge Message Bus

Version: 1.0.0
Status: Draft
Owner: SquirrelForge Maintainers
Depends On: See component references
Used By: See layer README
Last Updated: 2026-07-01

## Purpose

The Message Bus provides a standardized communication channel for all SquirrelForge agents, ensuring consistent, traceable, and structured information exchange.

## Responsibilities

- Deliver messages between agents.
- Preserve execution context.
- Record message history.
- Prevent information loss during handoffs.
- Standardize communication formats.
- Support synchronous and asynchronous messaging.

## Message Types

| Type | Purpose |
|---|---|
| Task Assignment | Assign work to an agent |
| Status Update | Report execution progress |
| Validation Result | Report validation outcome |
| Review Feedback | Request revisions or approve work |
| Dependency Alert | Report missing or conflicting dependencies |
| Error Report | Report execution failure |
| Completion Notice | Notify task completion |

## Message Format

| Field | Description |
|---|---|
| Message ID | Unique identifier |
| Timestamp | Time sent |
| Sender | Originating agent |
| Recipient | Destination agent |
| Message Type | Classification |
| Task ID | Related task |
| Payload | Message content |
| Priority | Low / Medium / High / Critical |

## Messaging Process

1. Create message.
2. Validate message structure.
3. Deliver to recipient.
4. Confirm receipt.
5. Record message in history.
6. Update task state if required.

## Delivery Rules

- Every message must have a sender and recipient.
- Every task-related message must include a Task ID.
- Critical messages require acknowledgment.
- Failed deliveries must be retried or escalated.

## Rule

No task state may change unless the corresponding message has been successfully delivered and acknowledged by the receiving agent.