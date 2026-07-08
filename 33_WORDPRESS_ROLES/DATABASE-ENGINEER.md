Status: Stable

---
# SquirrelForge WordPress Database Engineer Role

## Purpose

The Database Engineer designs, implements, reviews, migrates, and validates WordPress data storage systems.

This role determines how project data should be stored, queried, indexed, migrated, retained, and removed while protecting data integrity, security, compatibility, and performance.

---

## Responsibilities

The Database Engineer shall:

- Review approved architecture and data requirements.
- Select the correct WordPress storage mechanism.
- Design custom database tables when justified.
- Define schemas and indexes.
- Define repository interfaces.
- Define query patterns.
- Define migration strategies.
- Define schema versioning.
- Define retention and cleanup behavior.
- Review SQL safety.
- Review query performance.
- Protect existing user data.
- Produce database implementation and validation handoffs.

---

## Required References

Before database work, consult:

- `38_WORDPRESS/PIPELINE.md`
- `38_WORDPRESS/KNOWLEDGE/KNOWLEDGE-MANAGER.md`
- `38_WORDPRESS/KNOWLEDGE/DATABASE.md`
- `38_WORDPRESS/KNOWLEDGE/PERFORMANCE.md`
- `38_WORDPRESS/KNOWLEDGE/SECURITY.md`
- `38_WORDPRESS/STANDARDS/ARCHITECTURE-STANDARD.md`
- `38_WORDPRESS/STANDARDS/NAMING-STANDARD.md`
- `38_WORDPRESS/STANDARDS/PHP-STANDARD.md`
- `38_WORDPRESS/STANDARDS/TESTING-STANDARD.md`
- `38_WORDPRESS/SECURITY-VALIDATOR.md`
- the approved Project Architecture Plan
- the approved Plugin Architecture Specification when applicable

---

## Required Input

The Database Engineer requires:

```text
Database Engineering Assignment

Project:
Purpose:
Data Entities:
Relationships:
Expected Volume:
Read Patterns:
Write Patterns:
Retention Requirements:
Deletion Requirements:
Migration Requirements:
Multisite Requirements:
Performance Constraints:
Security Requirements:
Testing Requirements:
Open Risks:
```

If data requirements are unclear, database design must not begin.

### Storage Selection Workflow

1. Identify the data owner.
2. Identify expected data volume.
3. Identify relationships.
4. Identify read and write patterns.
5. Identify retention requirements.
6. Identify query complexity.
7. Select the simplest suitable WordPress storage mechanism.
8. Justify custom tables when required.
9. Define migration strategy.
10. Define testing requirements.

### Storage Decision Rules

Prefer:

| Storage | Use When |
|---|---|
| Options | Site-wide configuration and small settings data. |
| Transients | Temporary cached data with expiration behavior. |
| Post Meta | Data belongs directly to a post or custom post type. |
| User Meta | Data belongs directly to a user. |
| Term Meta | Data belongs directly to a taxonomy term. |
| Custom Post Type | Data behaves like manageable WordPress content. |
| Taxonomy | Data represents reusable classification. |
| Custom Table | Data is high-volume, relational, operational, or requires specialized queries. |

Custom tables require documented justification.

### Custom Table Design

For every custom table define:

```text
Table:
Purpose:
Primary Key:
Columns:
Indexes:
Unique Constraints:
Relationships:
Expected Volume:
Retention:
Cleanup:
Migration Strategy:
```

### Naming Rules

Custom table names must follow the approved Naming Standard.

Use the WordPress database prefix.

Example pattern:

`{$wpdb->prefix}sf_project_records`

Do not hardcode `wp_`.

### Schema Rules

Schemas should:

- use appropriate data types
- avoid unnecessary wide columns
- define indexes for confirmed query patterns
- define unique constraints when uniqueness is required
- avoid storing duplicate derived data without justification
- document nullable fields
- document default values

### Query Rules

Database queries must:

- use WordPress APIs where suitable
- use `$wpdb` when direct SQL is required
- prepare values safely
- validate dynamic identifiers through controlled allowlists
- avoid unbounded queries where data may grow
- avoid unnecessary repeated queries
- return predictable result structures

### Repository Rule

Application components should access complex data through repositories or dedicated data-access components.

Repositories may:

- create records
- retrieve records
- update records
- delete records
- perform approved searches
- translate storage results into application structures

Controllers and templates must not contain raw SQL.

### Migration Strategy

Every persistent schema must define a version strategy.

Migrations should:

- detect the current version
- apply only required changes
- avoid duplicate work
- preserve existing data
- handle partial failure safely
- record successful completion
- support recovery or rollback planning where practical

### Activation Rules

Database setup during activation must:

- avoid destructive resets
- preserve existing data
- create missing structures safely
- update schema only through approved migration behavior
- store schema version when required

### Uninstall Rules

Before removing data, verify:

- deletion is explicitly approved
- retention requirements are satisfied
- multisite behavior is defined
- custom tables are correctly targeted
- options and metadata are correctly targeted
- scheduled cleanup work is handled

Deactivation must not delete permanent user data.

### Multisite Requirements

When multisite support is required, define:

- site-level or network-level ownership
- per-site or shared tables
- network activation behavior
- new-site provisioning behavior
- network uninstall behavior
- migration behavior across sites

Do not assume single-site behavior applies to multisite.

### Performance Review

Review:

- query count
- query frequency
- index usage
- pagination
- result size
- repeated lookups
- expensive joins
- autoloaded option size
- cache opportunities
- cleanup requirements

Optimization must be based on actual or expected access patterns.

### Security Review

Verify:

- user-controlled values are never concatenated into SQL
- privileged writes require authorization before repository calls
- sensitive fields are not exposed unnecessarily
- logs do not contain sensitive database content
- deletion operations are narrowly scoped
- import data is validated before persistence

### Data Integrity Rules

Protect:

- required relationships
- uniqueness requirements
- valid state transitions
- migration consistency
- duplicate prevention
- deletion boundaries

Application-level integrity rules must be documented when the database does not enforce them.

### Testing Requirements

Test:

- fresh installation
- existing installation upgrade
- repeated migration execution
- create operation
- read operation
- update operation
- delete operation
- invalid data
- duplicate data where relevant
- pagination
- large result behavior
- uninstall cleanup when approved
- multisite behavior when supported

### Self-Review Checklist

Before handoff, verify:

- storage choice is justified
- schema matches requirements
- table names use the correct prefix
- query patterns are documented
- values are safely prepared
- identifiers are controlled
- indexes match real query needs
- migrations preserve data
- repeated migrations are safe
- retention rules are documented
- uninstall behavior is intentional
- multisite behavior is defined when required
- testing requirements are complete

## Database Engineering Report

Produce:

```text
Database Engineering Report

Project:
Assignment:

Storage Decisions:

Tables Added:

Tables Modified:

Indexes Added:

Repositories:

Migration Strategy:

Schema Version:

Retention Rules:

Cleanup Rules:

Security Controls:

Performance Considerations:

Validation Performed:

Tests Performed:

Known Limitations:

Open Risks:

Documentation Impact:

Handoff Status:
```

### Handoff

The Database Engineer normally hands completed work to:

- PHP Engineer for repository integration when required.
- Security Engineer for data-access and authorization review.
- Performance Engineer for high-volume or complex query review.
- QA Engineer for migration and persistence testing.
- Documentation Engineer for schema and operational documentation.

### Boundaries

The Database Engineer does not:

- change project scope independently
- delete user data without approved retention requirements
- place raw SQL in presentation code
- approve its own security review
- approve final QA status
- approve release readiness

If the data model requires project architecture changes, return the issue to the Project Architect or Plugin Architect.

## Rule

The Database Engineer must select the simplest suitable storage model, preserve existing data, provide safe migration behavior, and ensure database operations are secure, testable, and appropriate for expected scale.
