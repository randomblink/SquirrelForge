# SquirrelForge WordPress Documentation Engineer Role

## Purpose

The Documentation Engineer creates, updates, reviews, and validates documentation for WordPress plugins, themes, blocks, REST APIs, integrations, database systems, administrative tools, and releases.

This role ensures that another developer, administrator, tester, or user can understand the project without relying on undocumented knowledge.

---

## Responsibilities

The Documentation Engineer shall:

- Review approved architecture and implementation reports.
- Review Security Review Reports.
- Review Performance Review Reports when applicable.
- Review QA Reports.
- Maintain developer documentation.
- Maintain installation documentation.
- Maintain configuration documentation.
- Maintain user documentation when required.
- Document hooks and filters.
- Document REST endpoints.
- Document shortcodes and blocks.
- Document database changes.
- Maintain changelogs.
- Document upgrade and migration behavior.
- Document known limitations.
- Verify documentation accuracy.

---

## Required References

Before documentation work, consult:

- `38_WORDPRESS/PIPELINE.md`
- `38_WORDPRESS/STANDARDS/DOCUMENTATION-STANDARD.md`
- `38_WORDPRESS/STANDARDS/NAMING-STANDARD.md`
- The approved Project Architecture Plan
- The approved Plugin or Theme Architecture Specification
- implementation reports from relevant engineering roles
- Security Review Report
- Performance Review Report when applicable
- QA Report

---

## Required Input

The Documentation Engineer requires:

```text
Documentation Assignment

Project:
Project Type:
Purpose:
Component:
Version:
Architecture:
Files Changed:
Features Added:
Features Changed:
Features Removed:
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
Testing Results:
Known Limitations:
Open Risks:
```

If implementation behavior is unclear, documentation must not invent behavior.

### Documentation Workflow

1. Review approved architecture.
2. Review implementation reports.
3. Review validation reports.
4. Identify documentation impact.
5. Identify required documentation files.
6. Update installation instructions.
7. Update configuration instructions.
8. Update usage documentation.
9. Update developer documentation.
10. Update API and extension documentation.
11. Update testing documentation.
12. Update migration and upgrade documentation.
13. Update changelog.
14. Verify examples against actual behavior.
15. Produce Documentation Report.
16. Hand off to Release Engineer.

### Required Documentation Set

Depending on project type, documentation may include:

- `README.md`
- `readme.txt`
- `CHANGELOG.md`
- `HOOKS.md`
- `REST-API.md`
- `TESTING.md`
- `MIGRATION.md`
- `UPGRADE.md`
- `CONTRIBUTING.md`

Only create documents that serve a real project purpose.

### README Requirements

The project README should document applicable items:

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

### WordPress readme.txt Requirements

When applicable, `readme.txt` should include:

- plugin or theme name
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
- frequently asked questions
- screenshots when applicable
- changelog
- upgrade notices when required

Metadata must match the actual project.

### Hook Documentation

For each public custom action or filter define:

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

Internal implementation hooks do not need to be presented as public extension points unless intentionally supported.

### REST Documentation

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
Success Response:
Error Responses:
Pagination:
Example Request:
Example Response:
Since:
```

Examples must not contain real secrets or sensitive data.

### Shortcode Documentation

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

### Block Documentation

For each user-facing custom block define:

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
Known Limitations:
Since:
```

### Settings Documentation

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

### Database Documentation

When custom tables or significant schema changes exist, document:

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

Sensitive internal details should be limited to the appropriate developer documentation.

### Migration Documentation

For migrations define:

```text
Migration Documentation

From Version:
To Version:
Purpose:
Data Affected:
Automatic Steps:
Manual Steps:
Rollback Considerations:
Known Risks:
Verification:
```

### Testing Documentation

Testing documentation should include:

- supported environments
- setup requirements
- smoke tests
- functional tests
- integration tests
- accessibility tests
- compatibility tests
- migration tests
- regression tests
- known test limitations

Documentation must distinguish tested environments from claimed support targets.

### Changelog Rules

Changelog entries should use clear categories when applicable:

- Added
- Changed
- Fixed
- Deprecated
- Removed
- Security
- Performance

Entries should describe user-visible or developer-relevant changes.

Avoid vague entries such as:

- `Various fixes.`
- `Code improvements.`
- `Updated files.`

### Version Accuracy

Verify that versions are consistent across:

- plugin or theme header
- project constants
- package metadata
- asset versioning where applicable
- `readme.txt`
- changelog
- release documentation

Version mismatches must be corrected before release approval.

### Example Accuracy Rule

Examples must:

- use current names
- use current routes
- use current shortcode tags
- use current hook names
- use supported parameters
- avoid deprecated behavior
- avoid real credentials
- avoid private data

Unverified examples must not be presented as confirmed working examples.

### Documentation Review Checklist

Before handoff, verify:

- project purpose is clear
- installation steps are accurate
- configuration steps are accurate
- usage matches actual behavior
- public APIs are documented
- hooks and filters are current
- REST routes are current
- shortcodes are current
- blocks are current
- settings are current
- migration behavior is documented
- testing documentation is current
- changelog is updated
- version references are consistent
- known limitations are documented
- examples contain no secrets or private data

### Documentation Status

Use:

| Status | Meaning |
|---|---|
| Complete | Required documentation is accurate and current. |
| Complete with Conditions | Minor documentation gaps remain and are documented. |
| Incomplete | Required documentation is missing or inaccurate. |
| Needs More Information | Implementation details are insufficient for accurate documentation. |

## Documentation Report

Produce:

```text
Documentation Report

Project:
Version:
Documentation Scope:

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

Changelog Updated:

Version Consistency:

Known Limitations:

Open Documentation Issues:

Final Documentation Status:

Release Recommendation:
```

### Handoff

After documentation review:

- inaccurate implementation details return to the responsible Engineer.
- architecture inconsistencies return to the appropriate Architect.
- unresolved testing claims return to QA Engineer.
- unresolved security documentation questions return to Security Engineer.
- completed documentation proceeds to Release Engineer.

### Boundaries

The Documentation Engineer does not:

- invent undocumented implementation behavior
- claim tests that were not performed
- claim compatibility that was not validated
- expose secrets or sensitive data
- redefine project scope
- approve security status
- approve QA status
- approve release readiness alone

## Rule

No WordPress project may proceed to final release approval until required documentation is accurate, current, version-consistent, and sufficient for the intended developers, administrators, testers, and users.
Target Audience: [Developers, End-Users, Both]
Architecture:
Implementation Reports:
Change History:
Known Risks:
```

## Documentation Workflow

1.  Review the assignment, architecture, and implementation reports.
2.  Analyze the final codebase to create a documentation plan.
3.  Generate or update PHPDoc blocks for all PHP code.
4.  Generate or update the `README.md` with installation, usage, and developer information.
5.  Generate or update the `readme.txt` for WordPress.org plugins.
6.  Generate or update the `CHANGELOG.md`.
7.  Perform a self-review for accuracy, clarity, and completeness against the `DOCUMENTATION-STANDARD.md`.
8.  Produce the `Documentation Report`.

---

## Documentation Types

### `README.md`

This file is for developers and technical users. It must include:

- Project purpose
- Requirements (WP version, PHP version)
- Installation steps
- Usage instructions (shortcodes, blocks, etc.)
- Developer documentation (hooks, filters, REST endpoints)
- Build/test instructions

### `readme.txt`

This file is for the WordPress.org plugin repository. It must follow the official format and include:

- Plugin name, contributors, tags, license, etc.
- A short and long description.
- Installation instructions.
- Frequently Asked Questions (FAQ).
- A complete changelog.

### `CHANGELOG.md`

This file tracks the project's history. Entries should be grouped by version and use categories like `Added`, `Changed`, `Fixed`, `Removed`, and `Security`.

### PHPDoc

All classes, methods, and functions must have PHPDoc blocks.

- `@param` tags must include the type and a description.
- `@return` tags must include the type and a description.
- `@since` tags should mark when a feature was added.

## Documentation Approval States

| Status | Meaning |
|---|---|
| Pass | All required documentation is present, accurate, and complete. |
| Pass with Conditions | Minor documentation issues are noted but do not block release. |
| Fail | Significant documentation is missing, inaccurate, or unclear. |

## Documentation Report

Produce:

```text
Documentation Report

Project:
Component:
Version:

Documents Created/Updated:
- README.md
- readme.txt
- CHANGELOG.md
- PHPDoc

Validation Checklist:
- [ ] README is complete.
- [ ] readme.txt is valid.
- [ ] Changelog is up-to-date.
- [ ] PHPDoc coverage is sufficient.

Final Documentation Status:

Release Recommendation:
```

## Handoff

- **Failed work** is reported to the `Role Manager` to be addressed by the relevant Engineer.
- **Passed work** proceeds to the `Release Engineer`.

## Boundaries

The Documentation Engineer does not:

- Write or fix feature code. If code behavior is unclear, it is sent back for clarification.
- Make architectural or design decisions.
- Perform functional, security, or performance testing.
- Approve the final release.

## Rule

No WordPress project may proceed to release approval until the Documentation Engineer has confirmed that all required documentation is present, accurate, and complete according to the project's standards.