Status: Stable

---
# SquirrelForge WordPress Role Routing Matrix

## Purpose

The Role Routing Matrix maps each WordPress Skill to the specialist roles, validation gates, reports, and routing sequence required for execution.

The Skill Routing Map selects the primary Skill.

The Role Manager then uses this matrix to determine which specialist roles must participate in the selected Skill workflow.

---

## Position in the WordPress System

```text
User Request
↓
WordPress Manager
↓
WordPress Pipeline
↓
Skill Routing Map
↓
Selected Primary Skill
↓
Role Manager
↓
Role Routing Matrix
↓
Required Roles
↓
Conditional Roles
↓
Validation Gates
↓
Expected Reports
↓
Parent Skill
```

---

## Required References

The Role Routing Matrix coordinates with:

- `38_WORDPRESS/WORDPRESS-MANAGER.md`
- `38_WORDPRESS/PIPELINE.md`
- `38_WORDPRESS/SKILLS/SKILL-ROUTING-MAP.md`
- individual Skills in `38_WORDPRESS/SKILLS/`
- `33_WORDPRESS_ROLES/ROLE-MANAGER.md`
- specialist Role documents in `33_WORDPRESS_ROLES/`

---

## Routing Principles

1. Skill selection occurs before Role routing.
2. Every complex task must receive a Role Routing Decision.
3. Required roles cannot be silently omitted.
4. Conditional roles must be added when their trigger conditions exist.
5. Engineers must not approve their own independent validation gates.
6. Security validation must be independent.
7. Performance claims require independent revalidation when performance work is performed.
8. QA validation must remain independent from implementation.
9. Production release work requires Release Review.
10. Supporting Skills return results to the parent Skill.

---

## Role Categories

| Category | Roles |
|---|---|
| Routing | Role Manager |
| Architecture | Project Architect, Plugin Architect, Theme Architect |
| Implementation | PHP Engineer, JavaScript Engineer, CSS Engineer, Database Engineer, REST Engineer, Block Engineer |
| Independent Validation | Security Engineer, Performance Engineer, QA Engineer |
| Completion | Documentation Engineer, Release Engineer |

---

## Primary Skill Routing Matrix

| Skill | Required Core Roles | Conditional Roles | Required Validation |
|---|---|---|---|
| `CREATE-PLUGIN` | Project Architect, Plugin Architect, PHP Engineer, Security Engineer, QA Engineer, Documentation Engineer | Database, REST, Block, JavaScript, CSS, Performance, Release | Security, QA |
| `CREATE-THEME` | Project Architect, Theme Architect, PHP Engineer, CSS Engineer, Security Engineer, Performance Engineer, QA Engineer, Documentation Engineer | Block, JavaScript, Release | Security, Performance, QA |
| `CREATE-BLOCK` | Block Engineer, JavaScript Engineer, CSS Engineer, Security Engineer, QA Engineer, Documentation Engineer | Project Architect, Plugin Architect, Theme Architect, PHP, REST, Database, Performance, Release | Security, QA |
| `CREATE-REST-ENDPOINT` | REST Engineer, PHP Engineer, Security Engineer, QA Engineer, Documentation Engineer | Project Architect, Plugin Architect, Database, JavaScript, Performance, Release | Security, QA |
| `CREATE-SHORTCODE` | PHP Engineer, Security Engineer, QA Engineer, Documentation Engineer | Project Architect, Plugin Architect, Theme Architect, CSS, JavaScript, Performance, Release | Security, QA |
| `CREATE-WIDGET` | PHP Engineer, Security Engineer, QA Engineer, Documentation Engineer | Project Architect, Plugin Architect, Theme Architect, CSS, JavaScript, Performance, Release | Security, QA |
| `MIGRATE-PLUGIN` | Project Architect, Plugin Architect, PHP Engineer, Security Engineer, QA Engineer, Documentation Engineer, Release Engineer | Database, REST, Block, JavaScript, CSS, Performance | Security, QA, Release |
| `REVIEW-CODE` | Relevant Implementation Engineer, Security Engineer, QA Engineer | Project Architect, Plugin Architect, Theme Architect, Performance, Documentation | Security when in scope; QA or test-gap review |
| `REFACTOR-CODE` | Relevant Implementation Engineer, Security Engineer, QA Engineer | Project Architect, Plugin Architect, Theme Architect, additional Engineers, Performance, Documentation, Release | Security, QA |
| `DEBUG-PLUGIN` | Responsible Implementation Engineer, QA Engineer | Additional Engineers, Security, Performance, Documentation, Release | QA; Security when affected |
| `OPTIMIZE-PERFORMANCE` | Performance Engineer, Responsible Implementation Engineer, Performance Engineer Revalidation, QA Engineer | Security, Documentation, Release | Performance Revalidation, QA |
| `CREATE-TESTS` | QA Engineer, Relevant Implementation Engineer | Security Engineer, Performance Engineer, additional technical specialists | QA Test Suite Review |
| `WRITE-DOCUMENTATION` | Documentation Engineer, Relevant Technical Reviewer | QA Engineer, Security Engineer, Release Engineer | Technical Accuracy Review |

---

## Route 1 — CREATE-PLUGIN

### Required Roles

- Role Manager
- Project Architect
- Plugin Architect
- PHP Engineer
- Security Engineer
- QA Engineer
- Documentation Engineer

### Conditional Roles

- Database Engineer for custom tables, migrations, complex persistence, high-volume data, or specialized queries.
- REST Engineer for REST routes, API contracts, authenticated operations, or public operations.
- Block Engineer for custom blocks, variations, transforms, or editor extensions.
- JavaScript Engineer for administration, frontend, REST, AJAX, or Block Editor interactions.
- CSS Engineer for administration, frontend, block, or editor presentation.
- Performance Engineer for high-frequency hooks, significant queries, REST, polling, cron, external APIs, large scripts, or editor performance.
- Release Engineer for production release.

### Standard Route

```text
Project Architect
↓
Role Manager
↓
Plugin Architect
↓
PHP Engineer
↓
Database Engineer when required
↓
REST Engineer when required
↓
Block Engineer when required
↓
JavaScript Engineer when required
↓
CSS Engineer when required
↓
Security Engineer
↓
Performance Engineer when required
↓
QA Engineer
↓
Documentation Engineer
↓
Release Engineer when applicable
```

### Expected Reports

- Approved Project Architecture Plan
- Approved Plugin Architecture Specification
- applicable Implementation Reports
- Security Review Report
- Performance Review Report when required
- QA Report
- Documentation Report
- Release Readiness Report when applicable

---

## Route 2 — CREATE-THEME

### Required Roles

- Role Manager
- Project Architect
- Theme Architect
- PHP Engineer
- CSS Engineer
- Security Engineer
- Performance Engineer
- QA Engineer
- Documentation Engineer

### Conditional Roles

- Block Engineer for custom or dynamic blocks, variations, editor extensions, or engineered patterns.
- JavaScript Engineer for navigation, disclosures, modals, filtering, editor interactions, or frontend requests.
- Release Engineer for production release.

### Standard Route

```text
Project Architect
↓
Role Manager
↓
Theme Architect
↓
PHP Engineer
↓
Block Engineer when required
↓
JavaScript Engineer when required
↓
CSS Engineer
↓
Security Engineer
↓
Performance Engineer
↓
QA Engineer
↓
Documentation Engineer
↓
Release Engineer when applicable
```

### Expected Reports

- Approved Project Architecture Plan
- Approved Theme Architecture Specification
- PHP, CSS, and applicable Block or JavaScript Implementation Reports
- Security Review Report
- Performance Review Report
- QA Report
- Documentation Report
- Release Readiness Report when applicable

---

## Route 3 — CREATE-BLOCK

### Required Roles

- Role Manager
- Block Engineer
- JavaScript Engineer
- CSS Engineer
- Security Engineer
- QA Engineer
- Documentation Engineer

### Conditional Roles

- Project Architect when project boundaries change.
- Plugin or Theme Architect when owning architecture changes.
- PHP Engineer for dynamic rendering, server-side data, business rules, or permission-sensitive output.
- REST Engineer for API access.
- Database Engineer for specialized persistence.
- Performance Engineer for large bundles, repeated requests, expensive rendering, or editor performance concerns.
- Release Engineer for production release.

### Standard Route

```text
Parent Architecture Verification
↓
Role Manager
↓
Block Engineer
↓
JavaScript Engineer
↓
PHP Engineer when required
↓
REST Engineer when required
↓
Database Engineer when required
↓
CSS Engineer
↓
Security Engineer
↓
Performance Engineer when required
↓
QA Engineer
↓
Documentation Engineer
↓
Release Engineer when applicable
```

### Expected Reports

- Block Architecture Specification
- Block, JavaScript, CSS, and applicable PHP, REST, or Database Reports
- Block Compatibility Record when applicable
- Security Review Report
- Performance Review Report when required
- QA Report
- Documentation Report
- Release Readiness Report when applicable

---

## Route 4 — CREATE-REST-ENDPOINT

### Required Roles

- Role Manager
- REST Engineer
- PHP Engineer
- Security Engineer
- QA Engineer
- Documentation Engineer

### Conditional Roles

- Project or Plugin Architect when boundaries change.
- Database Engineer for custom tables, complex queries, specialized filtering, or migrations.
- JavaScript Engineer when client implementation is included.
- Performance Engineer for volume, expensive queries, external APIs, polling, or large payloads.
- Release Engineer for production release.

### Standard Route

```text
Role Manager
↓
REST Engineer
↓
PHP Engineer
↓
Database Engineer when required
↓
JavaScript Engineer when required
↓
Security Engineer
↓
Performance Engineer when required
↓
QA Engineer
↓
Documentation Engineer
↓
Release Engineer when applicable
```

### Expected Reports

- REST Route Specification
- REST and PHP Implementation Reports
- Database or JavaScript Report when required
- Security Review Report
- Performance Review Report when required
- QA Report
- Documentation Report
- REST Compatibility Record when modifying contracts
- Release Readiness Report when applicable

---

## Route 5 — CREATE-SHORTCODE

### Required Roles

- Role Manager
- PHP Engineer
- Security Engineer
- QA Engineer
- Documentation Engineer

### Conditional Roles

- Project, Plugin, or Theme Architect when boundaries change.
- CSS Engineer for presentation.
- JavaScript Engineer for interaction.
- Performance Engineer for expensive or high-frequency rendering.
- Release Engineer for production release.

### Standard Route

```text
Role Manager
↓
PHP Engineer
↓
CSS Engineer when required
↓
JavaScript Engineer when required
↓
Security Engineer
↓
Performance Engineer when required
↓
QA Engineer
↓
Documentation Engineer
↓
Release Engineer when applicable
```

### Expected Reports

- Shortcode Plan
- applicable Implementation Reports
- Security Review Report
- Performance Review Report when required
- QA Report
- Documentation Report
- Release Readiness Report when applicable

---

## Route 6 — CREATE-WIDGET

### Required Roles

- Role Manager
- PHP Engineer
- Security Engineer
- QA Engineer
- Documentation Engineer

### Conditional Roles

- Architect roles when boundaries change.
- CSS Engineer for presentation.
- JavaScript Engineer for interaction.
- Performance Engineer for sensitive rendering or data access.
- Release Engineer for production release.

### Standard Route

```text
Role Manager
↓
PHP Engineer
↓
CSS Engineer when required
↓
JavaScript Engineer when required
↓
Security Engineer
↓
Performance Engineer when required
↓
QA Engineer
↓
Documentation Engineer
↓
Release Engineer when applicable
```

### Expected Reports

- Widget Plan
- applicable Implementation Reports
- Security Review Report
- Performance Review Report when required
- QA Report
- Documentation Report
- Release Readiness Report when applicable

---

## Route 7 — MIGRATE-PLUGIN

### Required Roles

- Role Manager
- Project Architect
- Plugin Architect
- PHP Engineer
- Security Engineer
- QA Engineer
- Documentation Engineer
- Release Engineer

### Conditional Roles

- Database Engineer for persistent-data changes.
- REST Engineer for API contract changes.
- Block Engineer for block compatibility changes.
- JavaScript or CSS Engineer for client or presentation changes.
- Performance Engineer for significant workloads or runtime changes.

### Standard Route

```text
Project Architect
↓
Role Manager
↓
Plugin Architect
↓
Database Engineer when required
↓
PHP Engineer
↓
REST Engineer when required
↓
Block Engineer when required
↓
JavaScript Engineer when required
↓
CSS Engineer when required
↓
Security Engineer
↓
Performance Engineer when required
↓
QA Engineer
↓
Documentation Engineer
↓
Release Engineer
```

### Expected Reports

- Current-State Record and Migration Requirements
- Approved Migration Architecture and Target Plugin Specification
- Migration Strategy and applicable Implementation Reports
- Security and applicable Performance Review Reports
- QA Report and Migration Verification Record
- Documentation Report
- Release Readiness Report

---

## Route 8 — REVIEW-CODE

### Required Roles

- Role Manager
- relevant Implementation Engineer
- Security Engineer
- QA Engineer

### Conditional Roles

- Relevant Architect when architecture is in scope.
- Additional Engineers for languages and components reviewed.
- Performance Engineer for performance-sensitive behavior.
- Documentation Engineer for documentation accuracy or impact.
- Release Engineer when the review's objective is release or deployment readiness.

### Standard Route

```text
Role Manager
↓
Relevant Architect when required
↓
Relevant Implementation Engineer
↓
Additional Specialist Reviewers
↓
Security Engineer
↓
Performance Engineer when required
↓
QA Engineer
↓
Documentation Engineer when required
```

### Expected Reports

- Code Review Scope and Knowledge Selection
- specialist review findings
- Security Review Report
- Performance Review Report when required
- test-gap analysis or QA review
- WordPress Code Review Report

---

## Route 9 — REFACTOR-CODE

### Required Roles

- Role Manager
- responsible Implementation Engineer
- Security Engineer
- QA Engineer

### Conditional Roles

- Architect roles when structural boundaries change.
- Additional Engineers across technical domains.
- Performance Engineer for sensitive paths.
- Documentation Engineer for architecture or public extension changes.
- Release Engineer for production release.

### Standard Route

```text
Role Manager
↓
Relevant Architect when required
↓
Responsible Implementation Engineer
↓
Additional Specialist Engineers when required
↓
Security Engineer
↓
Performance Engineer when required
↓
QA Engineer
↓
Documentation Engineer when required
↓
Release Engineer when applicable
```

### Expected Reports

- Behavior Baseline, Refactor Scope, and Architecture Impact Assessment
- Refactor Plan and Regression Plan
- applicable Implementation and Compatibility Records
- Security and applicable Performance Reports
- QA Report
- Documentation and Release Reports when applicable
- Refactor Final Report

---

## Route 10 — DEBUG-PLUGIN

### Required Roles

- Role Manager
- responsible Implementation Engineer
- QA Engineer

### Conditional Roles

- PHP, JavaScript, Database, REST, or Block Engineer according to defect domain.
- Security Engineer when security boundaries are involved.
- Performance Engineer for performance-related defects.
- Documentation Engineer when behavior, configuration, or troubleshooting changes.
- Release Engineer for production release.

### Standard Route

```text
Role Manager
↓
Responsible Implementation Engineer
↓
Additional Specialist Engineer when required
↓
Security Engineer when required
↓
Performance Engineer when required
↓
QA Engineer
↓
Documentation Engineer when required
↓
Release Engineer when applicable
```

### Expected Reports

- reproduction evidence and confirmed cause
- applicable Implementation Report
- Security or Performance Report when required
- QA and regression results
- Documentation Report when required
- Debug Final Report

---

## Route 11 — OPTIMIZE-PERFORMANCE

### Required Roles

- Role Manager
- Performance Engineer
- responsible Implementation Engineer
- Performance Engineer Revalidation
- QA Engineer

### Conditional Roles

The implementation owner may be PHP, Database, REST, JavaScript, CSS, or Block Engineer.

Add Security Engineer when security controls or private-data caching are affected, Documentation Engineer when operational behavior changes, Release Engineer for production release, and Theme Architect or Plugin Architect when the performance work requires template, block, or structural boundary changes rather than a bounded implementation fix.

### Standard Route

```text
Role Manager
↓
Performance Engineer Baseline
↓
Responsible Implementation Engineer
↓
Performance Engineer Revalidation
↓
Security Engineer when required
↓
QA Engineer
↓
Documentation Engineer when required
↓
Release Engineer when applicable
```

### Expected Reports

- Performance Scope, Baseline, and Bottleneck Record
- Performance Optimization Plan
- applicable Implementation Report
- Cache Plan when applicable
- Performance Result
- Security Review Report when required
- QA and Documentation Reports when required
- Performance Optimization Final Report

---

## Route 12 — CREATE-TESTS

### Required Roles

- Role Manager
- QA Engineer
- relevant Implementation Engineer
- QA Engineer Final Review

### Conditional Roles

- PHP Engineer for PHP tests.
- Database Engineer for database and migration tests.
- REST Engineer for endpoint tests.
- JavaScript Engineer for browser and editor tests.
- Block Engineer for block tests.
- Security Engineer for security requirements.
- Performance Engineer for performance requirements.

### Standard Route

```text
Role Manager
↓
QA Engineer Test Strategy
↓
Relevant Implementation Engineer
↓
Security Engineer when required
↓
Performance Engineer when required
↓
Test Implementation
↓
Test Execution
↓
QA Engineer Final Test Suite Review
```

### Expected Reports

- Test Scope, Risk Analysis, Strategy, and Cases
- Characterization Records when applicable
- Security or Performance Test Requirements when applicable
- Compatibility Matrix when applicable
- Test Execution Records
- Test Suite Review
- Create Tests Final Report

---

## Route 13 — WRITE-DOCUMENTATION

### Required Roles

- Role Manager
- Documentation Engineer
- relevant Technical Reviewer

### Conditional Roles

- QA Engineer for testing, compatibility, accessibility, migration, or workflow claims.
- Security Engineer for security-sensitive setup or operational instructions.
- Release Engineer for release preparation.

### Standard Route

```text
Role Manager
↓
Documentation Engineer
↓
Relevant Technical Reviewer
↓
QA Engineer when claims require verification
↓
Security Engineer when security-sensitive
↓
Release Engineer when applicable
```

### Expected Reports

- Documentation Scope and Plan
- created or updated documentation
- technical accuracy review
- QA claim verification when required
- security documentation review when required
- version consistency result
- Documentation Final Report

---

## Conditional Role Trigger Matrix

### Database Engineer

Required for custom tables, schema changes, migrations, high-volume data, specialized queries, indexes, or complex persistence.

### REST Engineer

Required for new routes, contract changes, permission callbacks, API versioning, compatibility changes, or complex REST integrations.

### Block Engineer

Required for custom or dynamic blocks, variations, transforms, compatibility, or editor extensions.

### JavaScript Engineer

Required for browser or editor interaction, REST or AJAX clients, significant state, or accessible interactive behavior.

### CSS Engineer

Required for frontend or administration presentation, responsive behavior, editor or block styling, or interaction states.

### Security Engineer

Required when work affects authentication, authorization, capabilities, nonces, validation, sanitization, escaping, SQL, REST or AJAX permissions, uploads, secrets, integrations, private data, errors, or lifecycle security.

Security Engineer is mandatory for new plugin, theme, block, REST, shortcode, widget, migration, refactor, and production-sensitive code workflows.

### Performance Engineer

Required for measured optimization, high-frequency hooks, expensive queries, REST latency, polling, cron, external APIs, large assets, editor performance, or significant migrations.

### QA Engineer

Required for new functionality, fixes, refactoring, migrations, performance changes, production releases, test-suite review, and behavior or compatibility validation.

### Documentation Engineer

Required when public functionality, APIs, hooks, routes, shortcodes, blocks, settings, migration, upgrade, operations, or release documentation changes.

### Release Engineer

Required for production-readiness requests, distributed packages, version releases, deployed migrations, or production artifact approval.

---

## Complexity Classification

### Simple

One bounded component, no architecture or persistent-data change, no public contract change, limited files, and low integration complexity.

Simple does not mean validation can be skipped.

### Moderate

Multiple files or technical layers, limited integration work, REST, AJAX, block, or database involvement, or meaningful compatibility requirements.

### Complex

Plugin- or theme-scale systems, multiple specialist domains, architecture decisions, persistent data, migrations, integrations, public APIs, or significant security or performance risks.

### Critical

Sensitive data, destructive migration potential, large production migration, major public API transition, high-volume operations, severe security exposure, or significant rollback limitations.

Critical work requires explicit risk tracking and must not bypass required gates.

---

## Required Role Routing Decision

```text
WordPress Role Routing Decision

Task:
Selected Skill:
Project Type:
Complexity:
Required Roles:
Optional Roles:
Role Sequence:
Required Gates:
Conditional Gates:
Expected Reports:
Known Risks:
Routing Status:
```

### Routing Status Rules

| Status | Use When |
|---|---|
| Ready | Skill, roles, sequence, gates, and reports are defined. |
| Ready with Conditions | Routing is known but explicit resolvable conditions remain. |
| Needs More Information | Missing information could change Skill, architecture, roles, security, migration, or compatibility. |
| Blocked | Architecture, access, dependencies, source state, or failed gates prevent safe work. |

Implementation-blocking conditions must be resolved before implementation begins.

---

## Supporting Skill Routing

When supporting Skills are selected, the Role Manager creates one coordinated plan and avoids unnecessary duplicate scheduling.

```text
Primary Skill:
CREATE-PLUGIN

Supporting Skills:
CREATE-REST-ENDPOINT
CREATE-BLOCK
CREATE-TESTS
WRITE-DOCUMENTATION
```

A coordinated route may schedule a role once, but must preserve every responsibility and report required by the primary and supporting Skills.

### Duplicate Role Rule

A role selected by multiple Skills should normally be scheduled once. Its scope must cover all contributing workflows.

### Gate Consolidation Rule

Validation gates may be consolidated only when:

- the same independent validator performs the work
- all required scopes and evidence are explicit
- every required report or report section is produced
- no supporting Skill loses validation coverage

Consolidation must not become gate removal.

---

## Failure Routing

```text
Gate Failure
↓
Record Finding or Defect
↓
Identify Responsible Role
↓
Return Work to Responsible Role
↓
Apply Fix
↓
Return to Independent Validator
↓
Revalidate Failed Area
↓
Run Required Regression Tests
↓
Resume Workflow
```

---

## Architecture Change Routing

```text
Implementation Role
↓
Role Manager
↓
Relevant Specialist Architect
↓
Project Architect when boundaries change
↓
Architecture Revision
↓
Role Routing Review
↓
Assignment Update
↓
Implementation Resumes
```

---

## Skill Transition Routing

```text
Current Role
↓
Parent Skill
↓
Skill Transition Contract
↓
Skill Routing Map
↓
New or Supporting Skill Selected
↓
Role Manager
↓
Role Routing Matrix
↓
Updated Execution Plan
```

Roles must not silently expand the workflow.

---

## Independence Rules

```text
Implementation ≠ Validation
Engineer Self-Review ≠ Security Review
Developer Testing ≠ QA Validation
Automated Tests ≠ Complete QA
Optimization Change ≠ Performance Revalidation
Documentation Completion ≠ Release Approval
Completed Code ≠ Production Readiness
```

---

## Completion Criteria

Role routing is complete only when:

- the selected Skill is known
- required roles are identified
- conditional triggers are evaluated
- role sequence is defined
- required and conditional gates are identified
- expected reports are identified
- known risks are recorded
- Routing Status is assigned

A routed workflow is complete only when:

- required roles completed their work
- expected reports exist
- required gates passed
- failed gates were remediated and independently revalidated
- required regression testing passed
- documentation completed when required
- release review completed when applicable
- results returned to the parent Skill

## Rule

The SquirrelForge WordPress Role Routing Matrix is the authoritative mapping between selected WordPress Skills and specialist role execution.

It must provide explicit routing for every Skill in the Skill Routing Map, evaluate conditional role triggers, preserve independent validation, prevent silent gate removal, coordinate supporting Skills, and return complete validated results to the parent WordPress workflow.
