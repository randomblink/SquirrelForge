# SquirrelForge WordPress Write Documentation Skill

## Purpose

This Skill defines the controlled workflow for creating and maintaining accurate documentation for WordPress plugins, themes, blocks, REST APIs, migrations, integrations, tests, and releases.

Documentation must describe actual implementation and validated behavior. It must not invent functionality, compatibility, test results, security guarantees, or operational behavior.

---

## Trigger Conditions

Use this Skill when the request is to:

- create project documentation
- update project documentation
- create a README
- create or update `readme.txt`
- create API documentation
- document hooks and filters
- document REST endpoints
- document blocks
- document shortcodes
- document settings
- document database structures
- document migrations
- create testing documentation
- create upgrade documentation
- create release notes
- update a changelog

---

## Required References

Before execution, consult:

- `38_WORDPRESS/PIPELINE.md`
- `38_WORDPRESS/WORDPRESS-MANAGER.md`
- `38_WORDPRESS/STANDARDS/DOCUMENTATION-STANDARD.md`
- `38_WORDPRESS/STANDARDS/NAMING-STANDARD.md`
- applicable knowledge documents
- approved architecture documents
- implementation reports
- Security Review Report when applicable
- Performance Review Report when applicable
- QA Report when available
- `33_WORDPRESS_ROLES/ROLE-MANAGER.md`
- `33_WORDPRESS_ROLES/ROLE-ROUTING-MATRIX.md`
- `33_WORDPRESS_ROLES/DOCUMENTATION-ENGINEER.md`

---

## Required Input

```text
Documentation Request

Project:
Project Type:
Version:
Documentation Purpose:
Target Audience:
Architecture:
Features:
Files Changed:
Public APIs:
Hooks:
Filters:
REST Endpoints:
AJAX Actions:
Shortcodes:
Blocks:
Settings:
Database Changes:
Migration Requirements:
Security Requirements:
Compatibility Requirements:
Testing Evidence:
Known Limitations:
Release Target:
```

If implementation behavior is unclear, documentation must not invent it.

## Workflow

#### Stage 1 — Documentation Scope

Produce:

```text
Documentation Scope

Project:
Project Type:
Version:
Target Audience:

Documents Required:

Documents to Update:

Features in Scope:

APIs in Scope:

Migration Information:

Testing Information:

Release Information:

Known Evidence Gaps:

Out of Scope:
```

#### Stage 2 — Source Verification

Documentation must be based on authoritative project evidence.

Review applicable:

- requirements
- architecture plans
- implementation reports
- actual public interfaces
- actual configuration
- Security Review Report
- Performance Review Report
- QA Report
- migration records
- compatibility records
- release records

When sources conflict, documentation work must stop for the conflicting section until the responsible role resolves the discrepancy.

#### Stage 3 — Role Routing

Use:

`33_WORDPRESS_ROLES/ROLE-MANAGER.md`
`33_WORDPRESS_ROLES/ROLE-ROUTING-MATRIX.md`

Standard route:

1. Role Manager
2. ↓
3. Documentation Engineer
4. ↓
5. Relevant Engineer for technical accuracy review
6. ↓
7. QA Engineer for testing claims
8. ↓
9. Security Engineer for security-sensitive documentation when required
10. ↓
11. Release Engineer when part of a release

Produce:

```text
WordPress Role Routing Decision

Task:
Selected Skill: WRITE-DOCUMENTATION
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

#### Stage 4 — Documentation Plan

Produce:

```text
Documentation Plan

Document:
Audience:
Purpose:
Source Evidence:
Sections Required:
Technical Reviewer:
Validation Required:
Version:
Status:
```

#### Stage 5 — README Documentation

When applicable, document:

- project name
- purpose
- features
- requirements
- installation
- activation
- configuration
- usage
- project structure
- extension points
- development setup
- testing
- known limitations
- support information

The README must match the actual project.

#### Stage 6 — WordPress readme.txt

When applicable, verify:

- project name
- contributors
- tags
- minimum WordPress version
- tested WordPress version
- minimum PHP version
- stable version
- license
- short description
- full description
- installation
- FAQ
- screenshots
- changelog
- upgrade notices

Metadata must match validated project information.

#### Stage 7 — Hook Documentation

For each public action or filter define:

```text
Hook Documentation

Hook:
Type:
Since:
Purpose:
Parameters:
Return Value:
Example:
Owning Component:
Stability:
```

Internal implementation hooks must not be presented as supported public extension points unless intentionally exposed.

#### Stage 8 — REST Documentation

For each public or integration-facing endpoint define:

```text
REST Endpoint Documentation

Namespace:
Route:
Method:
Purpose:
Authentication:
Required Capability:
Arguments:
Validation Rules:
Success Response:
Error Responses:
Pagination:
Filtering:
Ordering:
Example Request:
Example Response:
Since:
Known Limitations:
```

Examples must not contain real secrets, credentials, tokens, or private data.

#### Stage 9 — Shortcode Documentation

For each shortcode define:

```text
Shortcode Documentation

Tag:
Purpose:
Attributes:
Defaults:
Example:
Output:
Limitations:
Since:
```

#### Stage 10 — Block Documentation

For each user-facing block define:

```text
Block Documentation

Name:
Purpose:
Category:
Controls:
Inspector Settings:
Toolbar Actions:
Inner Blocks:
Variations:
Styles:
Frontend Behavior:
Dynamic Behavior:
Compatibility Notes:
Known Limitations:
Since:
```

#### Stage 11 — Settings Documentation

For each user-facing setting define:

```text
Setting Documentation

Setting:
Location:
Purpose:
Default:
Allowed Values:
Required Capability:
Effect:
Reset Behavior:
```

Do not expose internal security details unnecessarily.

#### Stage 12 — Database Documentation

When custom tables or significant schema changes exist, define:

```text
Database Documentation

Table:
Purpose:
Schema Version:
Primary Key:
Important Columns:
Indexes:
Retention:
Cleanup:
Migration Behavior:
```

#### Stage 13 — Migration Documentation

For migrations define:

```text
Migration Documentation

From Version:
To Version:
Purpose:
Data Affected:
Automatic Steps:
Manual Steps:
Expected Duration:
Failure Behavior:
Recovery Steps:
Rollback Considerations:
Known Risks:
Verification:
```

Do not claim rollback support when rollback is unsafe or unsupported.

#### Stage 14 — Testing Documentation

Document:

- supported environments
- test setup
- smoke tests
- functional tests
- integration tests
- security tests
- accessibility tests
- compatibility tests
- migration tests
- regression tests
- known test limitations

Distinguish:

`Supported Target`

from:

`Actually Tested Environment`

Do not claim testing that did not occur.

#### Stage 15 — Changelog

Use applicable categories:

- Added
- Changed
- Fixed
- Deprecated
- Removed
- Security
- Performance

Avoid vague entries such as:

- `Various fixes.`
- `Code improvements.`
- `Updated files.`

Changelog entries should describe user-visible or developer-relevant changes.

#### Stage 16 — Upgrade Documentation

When applicable, document:

```text
Upgrade Documentation

Source Versions:
Target Version:
Backup Requirements:
Automatic Upgrade Behavior:
Manual Steps:
Migration Behavior:
Compatibility Changes:
Deprecated Behavior:
Removed Behavior:
Verification Steps:
Rollback Limitations:
```

#### Stage 17 — Release Notes

When applicable, produce:

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

Release notes must match the actual release scope.

#### Stage 18 — Technical Accuracy Review

The relevant implementation Engineer verifies:

- names
- paths
- APIs
- parameters
- defaults
- examples
- configuration
- lifecycle behavior
- extension points

Technical review does not replace QA validation of testing claims.

#### Stage 19 — QA Claim Verification

Use `QA Engineer` when documentation includes claims about:

- supported workflows
- compatibility
- migration success
- tested environments
- regression behavior
- accessibility behavior

Unsupported claims must be removed or clearly qualified.

#### Stage 20 — Security Documentation Review

Use `Security Engineer` when documentation includes:

- authentication setup
- authorization requirements
- secret configuration
- webhook setup
- external integration credentials
- security-sensitive operational procedures

Documentation must not expose secrets or encourage unsafe configuration.

### Version Consistency

Verify version consistency across applicable locations:

- plugin header
- theme stylesheet header
- PHP constants
- package metadata
- block metadata
- asset versioning
- `readme.txt`
- changelog
- migration version
- database schema version
- release notes

Version mismatches must be recorded and resolved.

### Example Accuracy Rule

Examples must:

- use current names
- use current routes
- use current shortcode tags
- use current hook names
- use supported parameters
- avoid deprecated behavior unless documenting migration
- avoid real credentials
- avoid private data

Unverified examples must not be presented as confirmed working examples.

### Documentation Status

Use:

| Status | Meaning |
|---|---|
| Complete | Required documentation is accurate and current. |
| Complete with Conditions | Minor gaps remain and are documented. |
| Incomplete | Required documentation is missing or inaccurate. |
| Needs More Information | Implementation evidence is insufficient. |

### Documentation Final Report

Produce:

```text
Documentation Final Report

Project:
Project Type:
Version:

Documentation Scope:

Role Routing Status:

Roles Used:

Files Created:

Files Updated:

Features Documented:

Hooks Documented:

REST Endpoints Documented:

Shortcodes Documented:

Blocks Documented:

Settings Documented:

Database Changes Documented:

Migration Documentation:

Testing Documentation:

Upgrade Documentation:

Release Notes:

Changelog Updated:

Technical Review Status:

QA Claim Verification:

Security Documentation Review:

Version Consistency:

Known Limitations:

Open Documentation Issues:

Final Documentation Status:

Recommended Next Step:
```

### Completion Criteria

The `Write Documentation` Skill is complete only when:

- documentation scope is defined
- authoritative source evidence is reviewed
- role routing is complete
- required documents are created or updated
- technical accuracy is reviewed
- testing claims are verified when applicable
- security-sensitive documentation is reviewed when required
- version references are consistent
- known limitations are documented
- final documentation status is assigned

---

## Rule

The Write Documentation Skill must describe actual implementation and validated behavior. It must not invent functionality, testing evidence, compatibility claims, security guarantees, migration behavior, or rollback support.