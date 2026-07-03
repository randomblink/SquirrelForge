# SquirrelForge Request Router

## Purpose

The Request Router determines the appropriate destination and execution path for approved integration requests. It selects the correct service, connector, endpoint, protocol, and routing strategy while ensuring compliance with governance, authentication, availability, and operational policies.

The Request Router routes requests only. It does not execute external operations or modify request content.

---

# Responsibilities

- Receive approved integration requests.
- Select the appropriate target service.
- Determine the optimal routing path.
- Choose the correct connector.
- Verify service availability.
- Enforce routing policies.
- Forward requests to the API Gateway or Connector Manager.
- Record routing decisions.
- Support failover routing.
- Maintain routing history.

---

# Routing Inputs

The Request Router receives:

- Approved integration requests
- Service catalog information
- Connector availability
- Authentication status
- Governance policies
- Routing rules
- Health monitoring data
- Retry instructions

---

# Routing Workflow

1. Receive integration request.
2. Validate routing requirements.
3. Identify target service.
4. Verify governance approval.
5. Confirm authentication readiness.
6. Check service availability.
7. Select the optimal connector or endpoint.
8. Route the request.
9. Record the routing decision.
10. Notify the Integration Monitor.

---

# Routing Criteria

Routing decisions consider:

- Target service
- Service health
- Connector status
- Authentication requirements
- Supported protocol
- Endpoint availability
- Load distribution
- Failover policy
- Governance restrictions
- Operational priority

---

# Routing Strategies

The Request Router supports:

- Direct routing
- Connector-based routing
- API Gateway routing
- Primary/secondary failover
- Load-balanced routing
- Priority-based routing
- Policy-driven routing

---

# Routing States

Each routing operation is classified as:

- Pending
- Validated
- Routed
- Deferred
- Retried
- Failed
- Completed

---

# Safety Rules

The Request Router must never:

- Route to unauthorized services.
- Ignore governance restrictions.
- Bypass authentication.
- Route to unhealthy endpoints.
- Modify request payloads.
- Circumvent approved connectors.

---

# Failure Handling

If routing fails:

- Preserve the original request.
- Record the routing failure.
- Notify the Retry Manager.
- Notify the Integration Monitor.
- Attempt failover when permitted.
- Escalate unresolved routing failures.

---

# Audit Requirements

Every routing decision records:

- Routing ID
- Timestamp
- Request ID
- Target service
- Selected connector
- Selected endpoint
- Routing strategy
- Authentication status
- Governance status
- Final routing outcome

---

# Success Criteria

The Request Router succeeds when:

- Every request reaches the correct destination.
- Routing policies are consistently enforced.
- Authentication requirements are satisfied.
- Healthy services are preferred.
- Failover operates correctly when needed.
- Routing history is complete.
- All routing decisions remain fully auditable.