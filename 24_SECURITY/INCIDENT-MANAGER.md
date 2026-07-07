# SquirrelForge Incident Manager

Version: 1.0.0
Status: Stable
Owner: Security Maintainers
Depends On: `24_SECURITY/THREAT-DETECTOR.md`, `24_SECURITY/SECURITY-MONITOR.md`, `23_GOVERNANCE/POLICY-ENGINE.md`
Used By: `24_SECURITY/SECURITY-MANAGER.md`, `24_SECURITY/SECURITY-GOVERNANCE.md`
Last Updated: 2026-07-07

## Purpose

The Incident Manager coordinates the response to confirmed or suspected security incidents within SquirrelForge. It owns security-incident intake, incident classification, response coordination, incident-record lifecycle, incident communications, evidence-reference handling, and post-incident review while ensuring that coordinated actions comply with governance, security policies, and audit requirements.

The Incident Manager coordinates security-incident response only. It does not detect or classify threats (owned by `24_SECURITY/THREAT-DETECTOR.md`), define security policies or approve exceptions (owned by `24_SECURITY/SECURITY-GOVERNANCE.md`), execute recovery, rollback, remediation, or workflow failure handling directly (owned by `17_COORDINATION/FAILURE-RECOVERY.md`, `20_EXECUTION/FAILURE-HANDLER.md`, `20_EXECUTION/ROLLBACK-MANAGER.md`, and the assigned remediation owner), validate recovery itself (owned by `14_ENGINE/VALIDATION.md` and the validating specialist), own authoritative workflow/task state (owned by `14_ENGINE/STATE-MANAGER.md`), or own general observability/audit infrastructure (owned by `27_OBSERVABILITY`).

---

## Responsibilities

- Receive incident notifications.
- Classify security incidents.
- Coordinate incident investigation.
- Coordinate authorized containment actions.
- Coordinate evidence preservation requests.
- Coordinate authorized security-specific recovery requests.
- Manage incident communications.
- Maintain the incident-domain record lifecycle.
- Conduct post-incident reviews.
- Attach validation, recovery, observability, and audit evidence references from owning components.

---

## Incident Sources

The Incident Manager is activated by incident notifications, escalated alerts, or incident candidates from:

- `24_SECURITY/THREAT-DETECTOR.md`
- `24_SECURITY/SECURITY-MONITOR.md`
- `24_SECURITY/AUTHENTICATION-MANAGER.md`
- `24_SECURITY/AUTHORIZATION-MANAGER.md`
- `23_GOVERNANCE/POLICY-ENGINE.md`
- `26_INTEGRATIONS`
- `37_STORAGE`
- `20_EXECUTION`
- Manual security reports
- External security notifications

---

## Incident Response Workflow

1. Receive incident notification.
2. Verify incident authenticity.
3. Attach any threat assessment supplied by `24_SECURITY/THREAT-DETECTOR.md`.
4. Classify the security incident independently from the threat assessment.
5. Open or update the incident-domain record.
6. Coordinate investigation and evidence-preservation requests.
7. Coordinate only authorized containment, recovery, rollback, or remediation requests through the owning components.
8. Attach validation evidence supplied by `14_ENGINE/VALIDATION.md`, testing, execution, recovery, or specialist owners.
9. Conduct post-incident review.
10. Request observability, audit, and reporting records through the owning infrastructure.
11. Publish incident-domain status.

---

## Incident Categories

The Incident Manager coordinates responses for:

- Unauthorized access
- Credential compromise
- Data breach
- Malware or malicious activity
- Policy violations
- Service disruption
- Insider threats
- Integration compromise
- Workflow abuse
- Security configuration failures

---

## Incident Severity Levels

Incidents are classified as:

- Informational
- Low
- Medium
- High
- Critical

Severity determines escalation, communication, and response priorities.

Threat severity from `24_SECURITY/THREAT-DETECTOR.md` is an input to incident intake. It is not the same as incident classification. The Incident Manager classifies the incident after reviewing threat evidence, affected assets, operational impact, containment status, and governance requirements.

---

## Response Activities

The Incident Manager coordinates:

- Investigation
- Authorized containment
- Authorized eradication or remediation requests
- Authorized security-specific recovery coordination
- Evidence-reference preservation
- Validation evidence attachment
- Incident-domain documentation
- Lessons learned
- Preventive recommendations

---

## Safety Rules

The Incident Manager must never:

- Destroy or modify forensic evidence.
- Ignore verified incidents.
- Override governance policies.
- Conceal security events.
- Remove audit, observability, state, validation, or incident records.
- Execute recovery, rollback, remediation, or workflow failure handling directly.
- Perform unauthorized containment or recovery actions.
- Treat incident status as authoritative workflow/task state.

---

## Failure Handling

If incident response fails:

- Preserve all available evidence.
- Preserve incident-domain state and correlation references.
- Request failure recording through `24_SECURITY/SECURITY-MONITOR.md` and `27_OBSERVABILITY`.
- Escalate unresolved incidents.
- Coordinate additional containment only when authorized by the owning component or governance decision.
- Maintain incident-record continuity.

---

## Incident Record

Every incident-domain record should include:

- Incident ID
- Timestamp
- Incident category
- Severity level
- Threat assessment reference, when supplied by `24_SECURITY/THREAT-DETECTOR.md`
- Detection or notification source
- Investigation status
- Authorized containment references
- Authorized recovery, rollback, or remediation references
- Validation evidence references
- Final resolution
- Post-incident review reference

The Incident Manager owns the incident-domain record lifecycle. It may request logging, audit, metrics, tracing, dashboard, alerting, or archival through the owning observability and storage infrastructure, but it must not replace that infrastructure.

---

## Success Criteria

The Incident Manager succeeds when:

- Incidents are investigated promptly.
- Authorized containment actions are coordinated effectively.
- Evidence references are preserved and traceable.
- Recovery validation evidence is attached from the owning validation or recovery component.
- Lessons learned are documented.
- Incident-domain history is complete.
- Security incidents remain traceable throughout their incident lifecycle without replacing authoritative workflow/task state.

---

## Permission Boundary

The Incident Manager may receive security-incident notifications, classify incidents, coordinate investigation, coordinate authorized containment and security-specific recovery requests, maintain incident-domain records, attach validation and evidence references, manage incident communications, and conduct post-incident review.

It must not detect or classify threats itself, define security policy, approve exceptions, execute recovery, perform rollback, remediate vulnerabilities, handle generic workflow failure recovery, independently validate recovery, own authoritative workflow/task state, or own general observability/audit infrastructure — those remain owned by `24_SECURITY/THREAT-DETECTOR.md`, `24_SECURITY/SECURITY-GOVERNANCE.md`, `17_COORDINATION/FAILURE-RECOVERY.md`, `20_EXECUTION/FAILURE-HANDLER.md`, `20_EXECUTION/ROLLBACK-MANAGER.md`, assigned remediation owners, `14_ENGINE/VALIDATION.md`, `14_ENGINE/STATE-MANAGER.md`, and `27_OBSERVABILITY` respectively.

---

## Domain Rule

Incident response coordination applies identically regardless of domain; domain-specific incident sources route through this component rather than maintaining separate domain-specific incident handling.

---

## Rule

Every confirmed security incident must be tracked in an incident-domain record from notification to post-incident review. No incident may be closed without a recorded resolution, required owner evidence references, and any required validation or governance disposition.
