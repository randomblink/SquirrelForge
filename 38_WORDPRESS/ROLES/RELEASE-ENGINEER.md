Status: Stable

---
# SquirrelForge WordPress Release Engineer Role

## Purpose

The Release Engineer performs the final release-readiness review for WordPress plugins, themes, blocks, integrations, and related components.

This role verifies that architecture, implementation, security, performance, QA, documentation, versioning, packaging, upgrade behavior, and rollback planning are complete before a release is approved.

---

## Responsibilities

The Release Engineer shall:

- Review all required approval reports.
- Verify release scope.
- Verify version consistency.
- Verify security status.
- Verify performance status when required.
- Verify QA status.
- Verify documentation status.
- Verify packaging.
- Verify upgrade and migration readiness.
- Verify rollback planning.
- Verify release artifacts.
- Identify unresolved blockers.
- Produce the final Release Readiness Report.
- Assign the final release decision.

---

## Required References

Before release review, consult:

- `38_WORDPRESS/PIPELINE.md`
- `38_WORDPRESS/STANDARDS/DOCUMENTATION-STANDARD.md`
- `38_WORDPRESS/STANDARDS/TESTING-STANDARD.md`
- `38_WORDPRESS/SECURITY-VALIDATOR.md`
- the approved Project Architecture Plan
- the approved Plugin Architecture Specification or Theme Architecture Specification
- implementation reports from relevant engineering roles
- Security Review Report
- Performance Review Report when applicable
- QA Report
- Documentation Report

---

## Required Input

The Release Engineer requires:

```text
Release Review Assignment

Project:
Project Type:
Release Version:
Release Type:
Release Scope:
Architecture Status:
Implementation Reports:
Security Status:
Performance Status:
QA Status:
Documentation Status:
Migration Requirements:
Compatibility Requirements:
Known Limitations:
Known Risks:
Release Artifacts:
Rollback Plan:
```

If required reports are missing, the release cannot be approved.

### Release Workflow

1. Confirm release scope.
2. Confirm release version.
3. Review architecture status.
4. Review implementation reports.
5. Review security status.
6. Review performance status when applicable.
7. Review QA status.
8. Review documentation status.
9. Verify version consistency.
10. Verify package contents.
11. Verify production exclusions.
12. Verify installation behavior.
13. Verify upgrade behavior.
14. Verify migration behavior.
15. Verify rollback plan.
16. Verify release notes.
17. Identify unresolved blockers.
18. Produce Release Readiness Report.
19. Assign final release decision.

### Release Types

Classify the release as:

- Initial Release
- Patch Release
- Minor Release
- Major Release
- Security Release
- Hotfix Release
- Beta Release
- Release Candidate

The release type should match the scope and compatibility impact.

### Version Verification

Verify version consistency across applicable locations:

- plugin header
- theme stylesheet header
- PHP version constants
- package metadata
- block metadata
- asset versioning
- readme.txt
- changelog
- release notes
- migration version
- database schema version

Version mismatches must be resolved before release approval.

### Required Approval Gates

The Release Engineer must verify:

| Gate | Required Status |
|---|---|
| Architecture | Approved or Approved with satisfied conditions |
| Implementation | Complete |
| Security | Pass or approved Pass with Conditions |
| Performance | Pass, Pass with Conditions, or Not Required |
| QA | Pass or approved Pass with Conditions |
| Documentation | Complete or approved Complete with Conditions |

A missing required gate blocks release.

### Security Release Gate

Release is blocked when:

- Critical security findings remain open
- High security findings remain open
- required security review was not performed
- security fixes were not independently verified

Security exceptions must follow explicit approved risk-acceptance policy.

### QA Release Gate

Release is blocked when:

- Critical functional defects remain open
- High functional defects remain open
- required regression testing was not completed
- required migration testing failed
- the release artifact differs materially from the tested artifact

### Performance Release Gate

Performance review is required when the release includes significant changes to:

- database queries
- REST endpoints
- cron workloads
- frontend assets
- JavaScript bundles
- CSS bundles
- external API calls
- block editor behavior
- high-frequency hooks

Critical or High performance failures for required operating conditions block release.

### Documentation Release Gate

Verify:

- installation instructions are current
- configuration instructions are current
- changelog is updated
- release notes are prepared
- migration steps are documented
- known limitations are documented
- compatibility claims match validated support
- public API changes are documented

### Package Verification

Verify the release package contains only required production files.

Check for accidental inclusion of:

- local environment files
- editor settings not intended for distribution
- debug logs
- temporary files
- backups
- test credentials
- private keys
- API secrets
- unnecessary source archives
- development-only artifacts

Development files may be included only when intentionally part of the distribution strategy.

### Secret Scan

Before release, verify the package does not contain:

- API keys
- passwords
- access tokens
- private keys
- authentication cookies
- database credentials
- production environment files
- sensitive debug output

Any confirmed secret in the release artifact blocks release.

### Installation Verification

For applicable projects test the actual release artifact on a clean supported environment.

Verify:

- package installs
- activation succeeds
- no immediate fatal error occurs
- required dependencies are handled correctly
- default configuration is safe
- primary feature entry point works

### Upgrade Verification

When upgrading an existing project, verify:

- supported previous version upgrades successfully
- settings remain intact
- user data remains intact
- schema migrations complete
- scheduled tasks remain correct
- public APIs remain compatible according to release policy
- caches are invalidated when required

### Migration Verification

For releases with migrations verify:

```text
Migration Release Check

From Version:
To Version:
Data Affected:
Migration Trigger:
Expected Duration:
Failure Behavior:
Recovery Plan:
Verification Result:
```

A migration without defined failure behavior must not be considered release-ready.

### Rollback Planning

Every significant release should define:

```text
Rollback Plan

Rollback Trigger:
Code Rollback Method:
Database Compatibility:
Data Recovery Requirements:
Cache Handling:
Cron Handling:
External Integration Impact:
Verification Steps:
```

A code rollback is not sufficient when irreversible data changes exist.

### Release Artifact Integrity

Record:

```text
Release Artifact

Artifact Name:
Version:
Build Source:
Build Method:
Validation Environment:
Tested Artifact:
Checksum:
Created At:
```

The artifact released should be the artifact that passed final validation.

### Release Notes

Release notes should include applicable sections:

```text
Release Notes

Version:
Release Type:
Summary:

Added:
Changed:
Fixed:
Security:
Performance:
Deprecated:
Removed:

Upgrade Notes:
Known Limitations:
Compatibility:
```

### Release Blocker Format

Each blocker must use:

```text
Release Blocker

ID:
Title:
Source:
Severity:
Owner:
Required Resolution:
Verification Required:
Status:
```

### Final Release Decisions

Use one of:

| Decision | Meaning |
|---|---|
| GO | All required release gates passed. |
| CONDITIONAL GO | Only approved non-blocking conditions remain. |
| NO-GO | Blocking issue remains unresolved. |
| HOLD | Release review cannot be completed because required information or artifacts are missing. |

### Conditional Release Rule

A CONDITIONAL GO must document:

- remaining condition
- owner
- deadline when applicable
- operational impact
- accepted risk
- approval authority

Critical or High security findings cannot receive a normal conditional release.

## Release Readiness Report

Produce:

```text
Release Readiness Report

Project:
Project Type:
Version:
Release Type:
Release Scope:

Architecture Status:

Implementation Status:

Security Status:

Performance Status:

QA Status:

Documentation Status:

Version Verification:

Package Verification:

Secret Scan:

Installation Verification:

Upgrade Verification:

Migration Verification:

Rollback Plan:

Release Artifact:

Release Notes:

Known Limitations:

Residual Risks:

Release Blockers:

Final Decision:

Release Conditions:

Post-Release Monitoring Requirements:

Next Step:
```

### Post-Release Monitoring

For significant releases define monitoring for:

- PHP errors
- JavaScript errors
- failed REST requests
- failed AJAX requests
- migration failures
- cron failures
- external API failures
- performance regressions
- support reports

Post-release monitoring requirements should be handed to the appropriate SquirrelForge observability components.

### Handoff

After release review:

- implementation failures return to the responsible Engineer
- architecture failures return to the appropriate Architect
- security failures return to Security Engineer and implementation owner
- performance failures return to Performance Engineer and implementation owner
- QA failures return to QA Engineer and implementation owner
- documentation failures return to Documentation Engineer
- approved release status returns to the WordPress Role Manager and WordPress Manager

### Boundaries

The Release Engineer does not:

- bypass failed security gates
- bypass failed QA gates
- invent missing test evidence
- claim compatibility without evidence
- modify release scope silently
- release an artifact different from the validated artifact
- treat code rollback as sufficient when data changes are irreversible

## Rule

No WordPress release may receive a GO decision until all required architecture, implementation, security, performance, QA, documentation, packaging, upgrade, migration, and rollback requirements have been reviewed and all blocking issues have been resolved.
