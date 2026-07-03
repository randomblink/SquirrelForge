# SquirrelForge Resilience Layer

## Purpose

This directory defines how SquirrelForge detects failures, recovers from them, and keeps operating -- or degrades safely -- when infrastructure, services, or dependencies fail.

The Resilience Layer covers the full spectrum from routine retries up through disaster recovery: detecting failures, choosing and executing a recovery strategy, healing automatically where safe, eliminating single points of failure, and preserving essential capability during major disruptions. Recovery must fail closed: an unclear or unverified outcome is never treated as success.

---

## Component Roster

| Component | Responsibility |
|---|---|
| `RESILIENCE-MANAGER.md` | Coordinates all resilience operations: detection, recovery, rollback, self-healing, redundancy, and failover. |
| `FAILURE-DETECTOR.md` | Identifies operational failures, degraded conditions, and anomalies across workflows, agents, and integrations. |
| `RECOVERY-MANAGER.md` | Evaluates and coordinates the appropriate recovery strategy once a failure has been detected. |
| `RETRY-MANAGER.md` | Applies standardized retry policies, backoff strategies, and failover procedures for failed integration operations. |
| `ROLLBACK-MANAGER.md` | Restores previously verified, known-good states when changes must be safely reversed. |
| `SELF-HEALING-ENGINE.md` | Automatically performs approved corrective actions for recoverable failures, with minimal disruption. |
| `REDUNDANCY-MANAGER.md` | Coordinates redundant services, replicated resources, and standby components to remove single points of failure. |
| `FAILOVER-COORDINATOR.md` | Manages controlled transitions from failed or degraded resources to verified healthy redundant ones. |
| `DISASTER-RECOVERY.md` | Coordinates restoration after catastrophic failures that exceed normal recovery capability. |
| `BUSINESS-CONTINUITY.md` | Ensures essential platform capability continues during major operational disruptions. |
| `RESILIENCE-GOVERNANCE.md` | Establishes the policies and oversight governing all resilience activity. |

---

## Resilience Principles

- Failure detection and classification must happen before any recovery action is attempted.
- Retries and rollbacks are bounded, policy-controlled, and idempotency-protected -- never indefinite.
- Automated self-healing only acts within pre-approved, safe corrective actions.
- Recovery fails closed: a timeout, missing acknowledgment, or indeterminate result is not success.
- Every recovery action remains observable, governed, and auditable.

---

## Rule

No recovery, rollback, self-healing action, or failover may be considered complete until the Resilience Manager and applicable health checks have verified the resulting state; an unverified outcome must be treated as still failed.
