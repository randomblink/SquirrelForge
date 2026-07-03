# SquirrelForge Message Broker

## Purpose

The Message Broker routes messages between SquirrelForge components, agents, workflows, services, integrations, and external systems. It ensures that messages are delivered to the correct destination using approved routing rules while preserving message integrity, authorization, observability, governance, and auditability.

The Message Broker does not create message content or determine business logic. It handles secure and reliable message routing.

---

# Responsibilities

- Route messages between components.
- Resolve message destinations.
- Apply routing rules.
- Verify delivery requirements.
- Support synchronous and asynchronous messaging.
- Prevent unauthorized routing.
- Track message delivery status.
- Support retry behavior.
- Record broker activity.
- Enforce communication governance.

---

# Inputs

The Message Broker receives:

- Message routing requests
- Source identifiers
- Destination identifiers
- Message payloads
- Routing rules
- Delivery policies
- Authorization results
- Validation results
- Governance policies
- Platform state

---

# Outputs

The Message Broker produces:

- Routed messages
- Delivery status updates
- Retry requests
- Routing failure reports
- Destination resolution reports
- Archive requests
- Governance review requests
- Message broker audit records

---

# Message Routing Workflow

1. Receive routing request.
2. Validate message routing metadata.
3. Resolve destination.
4. Verify authorization and governance status.
5. Select delivery channel.
6. Route message.
7. Track delivery status.
8. Retry delivery when appropriate.
9. Record audit information.
10. Publish routing outcome.

---

# Supported Routing Types

The Message Broker supports:

- Direct routing
- Topic-based routing
- Queue-based routing
- Broadcast routing
- Request-response routing
- Agent routing
- Service routing
- Workflow routing
- Integration routing
- Governance routing

---

# Message Components

Every routed message includes:

- Message ID
- Source
- Destination
- Message type
- Timestamp
- Correlation ID
- Priority
- Payload reference
- Delivery policy
- Governance status

---

# Delivery States

Messages may progress through:

- Received
- Validated
- Queued
- Routing
- Delivered
- Acknowledged
- Retrying
- Failed
- Expired
- Archived

---

# Delivery Policies

Delivery policies may define:

- Priority
- Retry count
- Retry interval
- Expiration time
- Acknowledgment requirement
- Ordering requirement
- Delivery confirmation
- Failure escalation

---

# Routing Rules

Routing rules may consider:

- Message type
- Source component
- Destination component
- Topic
- Priority
- Workflow ID
- Agent ID
- User permissions
- Security classification
- Governance policy

---

# Integration Responsibilities

The Message Broker coordinates with:

- Communication Manager
- Message Validator
- Event Bus
- Agent Communicator
- Service Messenger
- Notification Manager
- Message Archiver
- Security Layer
- Communication Governance

---

# Data Protection

The Message Broker must:

- Protect message payloads.
- Enforce routing permissions.
- Preserve message integrity.
- Protect confidential metadata.
- Maintain audit records.

---

# Safety Rules

The Message Broker must never:

- Route unauthorized messages.
- Modify message meaning.
- Expose confidential payloads.
- Bypass validation.
- Ignore governance restrictions.
- Suppress critical messages.
- Delete protected message records.

---

# Failure Handling

If message routing fails:

- Preserve message metadata.
- Record routing failure.
- Retry according to policy.
- Notify the Communication Manager.
- Escalate persistent failures.
- Maintain audit continuity.
- Prevent unsafe rerouting.

---

# Audit Requirements

Every broker operation records:

- Broker operation ID
- Timestamp
- Message ID
- Source
- Destination
- Routing rule applied
- Delivery status
- Governance status
- Final outcome

---

# Success Criteria

The Message Broker succeeds when:

- Messages reach the correct destination.
- Unauthorized routing is prevented.
- Delivery status is traceable.
- Retry behavior follows policy.
- Message integrity is preserved.
- Governance requirements are enforced.
- Audit records remain complete.
