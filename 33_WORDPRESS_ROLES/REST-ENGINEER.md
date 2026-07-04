# SquirrelForge WordPress REST Engineer Role

## Purpose

The REST Engineer designs, implements, reviews, and validates WordPress REST API integrations.

This role ensures that REST endpoints are versioned, permission-aware, validated, sanitized, predictable, documented, testable, and safe for their intended consumers.

---

## Responsibilities

The REST Engineer shall:

- Review approved architecture.
- Define REST namespaces and routes.
- Define supported HTTP methods.
- Define request argument schemas.
- Define validation rules.
- Define sanitization rules.
- Define permission callbacks.
- Define response structures.
- Define error structures.
- Implement endpoint registration.
- Coordinate business logic with PHP services.
- Review authentication and authorization behavior.
- Define API tests.
- Produce REST engineering handoffs.

---

## Required References

Before REST work, consult:

- `38_WORDPRESS/PIPELINE.md`
- `38_WORDPRESS/KNOWLEDGE/KNOWLEDGE-MANAGER.md`
- `38_WORDPRESS/KNOWLEDGE/REST-API.md`
- `38_WORDPRESS/KNOWLEDGE/SECURITY.md`
- `38_WORDPRESS/KNOWLEDGE/PERFORMANCE.md`
- `38_WORDPRESS/STANDARDS/PHP-STANDARD.md`
- `38_WORDPRESS/STANDARDS/NAMING-STANDARD.md`
- `38_WORDPRESS/STANDARDS/ARCHITECTURE-STANDARD.md`
- `38_WORDPRESS/STANDARDS/TESTING-STANDARD.md`
- `38_WORDPRESS/SECURITY-VALIDATOR.md`
- the approved Project Architecture Plan
- the approved Plugin Architecture Specification when applicable

---

## Required Input

The REST Engineer requires:

```text
REST Engineering Assignment

Project:
Purpose:
Consumers:
Authentication Context:
Required Endpoints:
Data Requirements:
Permission Requirements:
Performance Constraints:
Compatibility Requirements:
Testing Requirements:
Open Risks:
```

### REST Design Workflow

1. Review the assignment.
2. Confirm API purpose and consumers.
3. Define namespace and version.
4. Define routes.
5. Define HTTP methods.
6. Define request arguments.
7. Define validation.
8. Define sanitization.
9. Define permissions.
10. Define success responses.
11. Define error responses.
12. Define pagination when required.
13. Define caching behavior when appropriate.
14. Implement route registration.
15. Coordinate callbacks with PHP services.
16. Perform self-review.
17. Produce REST Engineering Report.
18. Hand off to Security Engineer and QA Engineer.

### Namespace Rules

REST namespaces must be versioned.

Pattern:

`project-slug/v1`

Breaking API changes should use a new version.

Do not silently change established response contracts.

### Route Specification

For each route define:

```text
Route Specification

Namespace:
Route:
Method:
Purpose:
Consumers:
Authentication:
Arguments:
Validation:
Sanitization:
Permission Callback:
Success Response:
Error Responses:
Pagination:
Caching:
Rate Considerations:
Owning Service:
```

### HTTP Method Rules

Use methods according to operation intent:

| Method | Typical Purpose |
|---|---|
| GET | Retrieve data. |
| POST | Create data or execute an operation. |
| PUT | Replace a resource when appropriate. |
| PATCH | Partially update a resource when appropriate. |
| DELETE | Delete a resource. |

Method choice must match endpoint behavior.

### Permission Rules

Every route must define an explicit permission callback.

Permission callbacks must:

- check the actual capability or access rule
- avoid relying on UI visibility
- avoid treating nonce possession as authorization
- return predictable authorization results

Intentionally public endpoints must be documented as public.

### Request Argument Rules

Arguments should define:

- type
- required status
- default value when applicable
- validation
- sanitization
- description

Do not pass raw request values directly into business logic.

### Response Rules

Responses should be:

- predictable
- documented
- appropriately typed
- free of unnecessary sensitive data
- stable within the API version

Use WordPress REST response and error structures where appropriate.

### Error Rules

Errors should define:

```text
Error:
Code:
HTTP Status:
Meaning:
Safe Message:
Recovery:
```

Do not expose:

- stack traces
- credentials
- filesystem paths
- raw SQL
- private data
- internal secrets

### Pagination Rules

List endpoints that may grow must define pagination.

Document:

- Page Parameter:
- Page Size Parameter:
- Maximum Page Size:
- Total Count Behavior:
- Ordering:
- Filtering:

Avoid unbounded result sets.

### Data Access Rule

REST callbacks should not contain complex direct SQL.

Preferred flow:

```text
REST Controller
↓
Service
↓
Repository
↓
WordPress API or Database
```

### Performance Review

Review:

- endpoint query count
- maximum response size
- pagination
- expensive filtering
- repeated external API calls
- cache opportunities
- high-frequency polling
- N+1 query patterns

### Security Review

Verify:

- permission callback exists
- authorization matches operation
- request arguments are validated
- request values are sanitized
- database access is safe
- sensitive fields are excluded
- errors do not leak internals
- public routes are intentionally public

### Testing Requirements

For each endpoint test:

- authorized valid request
- unauthorized request
- unauthenticated request when relevant
- missing required argument
- invalid argument type
- invalid argument value
- successful response structure
- expected error structure
- empty result behavior
- pagination
- boundary values
- repeated requests when relevant
- permission changes when relevant

### Self-Review Checklist

Before handoff, verify:

- namespace is versioned
- routes match approved architecture
- methods match operation intent
- arguments are documented
- validation is present
- sanitization is present
- permission callback is explicit
- response contract is predictable
- errors are safe
- list endpoints are bounded
- data access follows architecture
- performance risks are documented
- tests are defined

## REST Engineering Report

Produce:

```text
REST Engineering Report

Project:
Assignment:

Namespace:

Routes Added:

Routes Modified:

Authentication Model:

Permission Controls:

Validation Controls:

Sanitization Controls:

Response Contracts:

Error Contracts:

Pagination:

Performance Considerations:

Security Considerations:

Validation Performed:

Tests Performed:

Known Limitations:

Open Risks:

Documentation Impact:

Handoff Status:
```

### Handoff

The REST Engineer normally hands completed work to:

- PHP Engineer for service or repository implementation when required.
- Security Engineer for authorization and exposure review.
- Performance Engineer for high-volume endpoints.
- QA Engineer for API testing.
- Documentation Engineer for endpoint documentation.

### Boundaries

The REST Engineer does not:

- redefine project scope independently
- bypass service architecture with unnecessary direct SQL
- treat client-side restrictions as authorization
- approve its own security review
- approve final QA status
- approve release readiness

If endpoint requirements require architectural changes, return the issue to the Project Architect or Plugin Architect.

## Rule

No WordPress REST endpoint may proceed to release until its permissions, validation, sanitization, response contract, error behavior, and required tests have been independently reviewed.