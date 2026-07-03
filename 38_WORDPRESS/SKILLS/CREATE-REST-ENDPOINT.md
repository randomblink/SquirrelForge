# SquirrelForge Skill: Create WordPress REST Endpoint

## Purpose

This skill defines how SquirrelForge creates secure WordPress REST API endpoints.

---

## Required References

Before creating a REST endpoint, consult:

- `32_WORDPRESS/PIPELINE.md`
- `32_WORDPRESS/KNOWLEDGE/KNOWLEDGE-MANAGER.md`
- `32_WORDPRESS/KNOWLEDGE/REST-API.md`
- `32_WORDPRESS/KNOWLEDGE/SECURITY.md`
- `32_WORDPRESS/STANDARDS/PHP-STANDARD.md`
- `32_WORDPRESS/STANDARDS/NAMING-STANDARD.md`
- `32_WORDPRESS/STANDARDS/TESTING-STANDARD.md`
- `32_WORDPRESS/SECURITY-VALIDATOR.md`

---

## Workflow

1. Identify endpoint purpose.
2. Define namespace and route.
3. Define HTTP method.
4. Define request arguments.
5. Define validation rules.
6. Define sanitization rules.
7. Define permission callback.
8. Define response schema.
9. Generate endpoint code.
10. Validate security.
11. Create tests.
12. Produce final report.

---

## Required Planning Output

```text
REST Endpoint Plan

Namespace:
Route:
Method:
Purpose:
Arguments:
Validation:
Sanitization:
Permission Callback:
Response:
Errors:
Testing:
```
### Security Gates

Every REST endpoint must include:

- versioned namespace
- permission callback
- argument validation
- argument sanitization
- escaped output where rendered
- safe database access
- no leaked sensitive data
### Testing Gates

Verify:

- valid request succeeds
- invalid request fails safely
- unauthorized request is blocked
- malformed input is rejected
- response format is predictable
- sensitive data is not exposed
## Rule

SquirrelForge must reject REST endpoints without explicit permission callbacks unless the endpoint is intentionally public and documented.


Next file:

```text
32_WORDPRESS/SKILLS/CREATE-SHORTCODE.md
```