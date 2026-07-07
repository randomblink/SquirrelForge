# SquirrelForge Incident Manager

Version: 1.0.0
Status: Stable
Owner: Security Maintainers
Depends On: `24_SECURITY/THREAT-DETECTOR.md`, `24_SECURITY/SECURITY-MONITOR.md`, `23_GOVERNANCE/POLICY-ENGINE.md`
Used By: `24_SECURITY/SECURITY-MANAGER.md`, `24_SECURITY/SECURITY-GOVERNANCE.md`
Last Updated: 2026-07-06

## Purpose

The Incident Manager coordinates the response to confirmed or suspected security incidents within SquirrelForge. It manages investigation, containment, recovery, communication, evidence preservation, and post-incident analysis while ensuring that all actions comply with governance, security policies, and audit requirements.

The Incident Manager coordinates security-incident response only. It does not detect threats (owned by `24_SECURITY/THREAT-DETECTOR.md`), define security policies (owned by `24_SECURITY/SECURITY-GOVERNANCE.md`), or perform generic workflow failure recovery (owned by `17_COORDINATION/FAILURE-RECOVERY.md` and `20_EXECUTION/ROLLBACK-MANAGER.md`) — its recovery coordination is scoped specifically to security incidents.

---

## Responsibilities

- Receive incident notifications.
- Classify security incidents.
- Coordinate incident investigation.
- Initiate containment procedures.
- Support evidence preservation.
- Coordinate recovery activities.
- Manage incident communications.
- Record incident history.
- Conduct post-incident reviews.
- Maintain incident audit records.

---

## Incident Sources

The Incident Manager is activated by escalated alerts from:

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
3. Classify incident severity.
4. Initiate investigation.
5. Coordinate containment actions.
6. Preserve evidence.
7. Support recovery procedures.
8. Conduct post-incident review.
9. Record audit information.
10. Publish incident status.

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

---

## Response Activities

The Incident Manager coordinates:

- Investigation
- Containment
- Eradication
- Recovery
- Verification
- Documentation
- Lessons learned
- Preventive recommendations

---

## Safety Rules

The Incident Manager must never:

- Destroy or modify forensic evidence.
- Ignore verified incidents.
- Override governance policies.
- Conceal security events.
- Remove audit records.
- Perform unauthorized recovery actions.

---

## Failure Handling

If incident response fails:

- Preserve all available evidence.
- Record the failure.
- Notify `24_SECURITY/SECURITY-MONITOR.md`.
- Escalate unresolved incidents.
- Continue containment where possible.
- Maintain audit continuity.

---

## Audit Requirements

Every incident response records:

- Incident ID
- Timestamp
- Incident category
- Severity level
- Detection source
- Investigation status
- Containment actions
- Recovery status
- Final resolution
- Post-incident review reference

---

## Success Criteria

The Incident Manager succeeds when:

- Incidents are investigated promptly.
- Containment actions are coordinated effectively.
- Evidence is preserved.
- Recovery is validated.
- Lessons learned are documented.
- Audit history is complete.
- Security incidents remain fully traceable throughout their lifecycle.

---

## Permission Boundary

The Incident Manager may classify confirmed or suspected security incidents and coordinate investigation, containment, security-specific recovery, and post-incident review.

It must not detect threats itself, define security policy, or perform generic (non-security) workflow failure recovery — those remain owned by `24_SECURITY/THREAT-DETECTOR.md`, `24_SECURITY/SECURITY-GOVERNANCE.md`, and `17_COORDINATION/FAILURE-RECOVERY.md`/`20_EXECUTION/ROLLBACK-MANAGER.md` respectively.

---

## Domain Rule

Incident response coordination applies identically regardless of domain; domain-specific incident sources route through this component rather than maintaining separate domain-specific incident handling.

---

## Rule

Every confirmed security incident must be tracked from notification to post-incident review; no incident may be closed without a recorded resolution and audit trail.
