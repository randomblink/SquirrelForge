# SquirrelForge Threat Detector

Version: 1.0.0
Status: Stable
Owner: Security Maintainers
Depends On: `24_SECURITY/SECURITY-GOVERNANCE.md`, `26_INTEGRATIONS`, `37_STORAGE`, `27_OBSERVABILITY`
Used By: `24_SECURITY/INCIDENT-MANAGER.md`, `24_SECURITY/SECURITY-MONITOR.md`, `24_SECURITY/SECURITY-MANAGER.md`
Last Updated: 2026-07-07

## Purpose

The Threat Detector analyzes security-domain signals, telemetry references, event evidence, behavioral patterns, and threat indicators to identify suspicious, malicious, or anomalous behavior that could threaten the confidentiality, integrity, or availability of SquirrelForge.

The Threat Detector detects, correlates evidence, classifies threat severity, and reports threat assessments only. It does not own general monitoring or telemetry collection, execute remediation actions, modify system behavior, classify incidents, or coordinate incident response — confirmed or suspected threats are handed to `24_SECURITY/INCIDENT-MANAGER.md` for incident intake. It is distinct from `24_SECURITY/VULNERABILITY-MANAGEMENT.md`, which tracks static weaknesses through their remediation lifecycle rather than detecting active runtime exploitation or anomalous behavior.

---

## Responsibilities

- Consume security events and observability signal references.
- Detect suspicious activity.
- Identify behavioral anomalies.
- Evaluate threat indicators.
- Correlate related security events.
- Classify detected threats.
- Generate threat assessments and threat findings.
- Notify incident-response owners when threat evidence warrants incident intake.
- Maintain threat-domain records and evidence references.
- Preserve threat intelligence references through owning storage or observability infrastructure.

---

## Threat Sources

The Threat Detector analyzes data from:

- Authentication events (`24_SECURITY/AUTHENTICATION-MANAGER.md`)
- Authorization failures (`24_SECURITY/AUTHORIZATION-MANAGER.md`)
- Policy-violation findings from the component that performed policy evaluation
- `26_INTEGRATIONS` (for example, suspicious API calls)
- Workflow execution (`20_EXECUTION`)
- Agent behavior (`16_AGENTS`)
- `37_STORAGE` (for example, unusual access patterns)
- System logs and monitoring events (`27_OBSERVABILITY`)
- External security intelligence

---

## Threat Detection Workflow

1. Receive security event.
2. Verify event integrity.
3. Correlate related events.
4. Analyze behavioral patterns.
5. Evaluate threat indicators.
6. Classify threat severity.
7. Generate threat assessment.
8. Notify `24_SECURITY/INCIDENT-MANAGER.md` when incident intake is warranted.
9. Record threat-domain evidence references and request observability/audit recording through owning infrastructure.
10. Publish threat status.

---

## Threat Categories

The Threat Detector identifies:

- Unauthorized access attempts
- Privilege escalation
- Credential misuse
- Data exfiltration
- Malicious workflows
- Suspicious integrations
- Policy violations
- Service abuse
- Denial-of-service activity
- Insider threats
- Unknown anomalies

---

## Threat Severity Levels

Each detected threat is classified as:

- Informational
- Low
- Medium
- High
- Critical

Threat severity informs routing and incident intake priority, but it is not incident classification and does not automatically trigger remediation or response.

---

## Detection Methods

The Threat Detector supports:

- Rule-based detection
- Behavioral analysis
- Pattern correlation
- Anomaly detection
- Threshold monitoring
- Event sequencing
- Historical comparison
- Threat intelligence correlation

---

## Safety Rules

The Threat Detector must never:

- Ignore verified threat indicators.
- Suppress critical threat alerts.
- Modify production systems.
- Execute remediation actions.
- Classify incidents or coordinate incident response.
- Own general monitoring, telemetry, logging, storage, or audit infrastructure.
- Bypass governance policies.
- Remove audit, observability, or threat records.

---

## Failure Handling

If threat detection fails:

- Record the detection failure.
- Preserve available event data.
- Notify `24_SECURITY/SECURITY-MONITOR.md`.
- Escalate persistent failures to `24_SECURITY/SECURITY-MANAGER.md` for routing and `24_SECURITY/SECURITY-GOVERNANCE.md` when governance disposition is required.
- Continue analyzing available signal sources.
- Maintain threat-record continuity and request observability recording through owning infrastructure.

---

## Threat Record

Every threat-domain record includes:

- Threat analysis ID
- Timestamp
- Event source
- Threat category
- Severity level
- Detection method
- Supporting evidence
- Incident intake reference, when handed to `24_SECURITY/INCIDENT-MANAGER.md`
- Final outcome

Threat records preserve evidence references. General logs, metrics, traces, dashboards, alerts, and audit infrastructure remain owned by `27_OBSERVABILITY`.

---

## Success Criteria

The Threat Detector succeeds when:

- Known attack patterns are reliably detected.
- Security threats are identified promptly.
- Threat classifications are evidence-based.
- Anomalous behavior is accurately identified.
- Threat findings are generated accurately.
- Incident response receives timely notification.
- Threat-domain history is complete.
- Potential threats are routed promptly.
- Platform security signals remain available through owning observability components.

---

## Permission Boundary

The Threat Detector may consume security signals and observability references, correlate threat evidence, classify threat severity, report threat assessments, and maintain threat-domain records.

It must not own general monitoring or telemetry collection, execute remediation, modify production systems, classify incidents, coordinate incident response mechanics, or own general audit/storage infrastructure — those remain owned by `27_OBSERVABILITY`, remediation owners, execution owners, `24_SECURITY/INCIDENT-MANAGER.md`, and `37_STORAGE` as applicable. It must not track or prioritize static vulnerabilities through a remediation lifecycle — that remains owned by `24_SECURITY/VULNERABILITY-MANAGEMENT.md`.

---

## Domain Rule

Threat detection applies identically regardless of domain; domain-specific threat indicators are surfaced through the owning domain layer, not reimplemented here.

---

## Rule

Every confirmed or suspected threat that may require response must be reported to `24_SECURITY/INCIDENT-MANAGER.md` for incident intake. The Threat Detector classifies threat severity only; it never decides incident classification, response, remediation, or recovery on its own authority.
