Status: Stable

---
# SquirrelForge WordPress Role Routing Matrix

## Purpose

This document is the data source for the `Role Manager`. It maps a selected `Skill` to a standard sequence of specialist roles and validation gates.

The `Role Manager` uses this matrix to produce a `WordPress Role Routing Decision`.

---

## Plugin Creation Route

**Skill:**

`38_WORDPRESS/SKILLS/CREATE-PLUGIN.md`

**Required route:**

1. Project Architect
2. ↓
3. Role Manager
4. ↓
5. Plugin Architect
6. ↓
7. PHP Engineer
8. ↓
9. Database Engineer when required
10. ↓
11. REST Engineer when required
12. ↓
13. Block Engineer when required
14. ↓
15. JavaScript Engineer when required
16. ↓
17. CSS Engineer when required
18. ↓
19. Security Engineer
20. ↓
21. Performance Engineer when required
22. ↓
23. QA Engineer
24. ↓
25. Documentation Engineer
26. ↓
27. Release Engineer

## Theme Creation Route

**Skill:**

`38_WORDPRESS/SKILLS/CREATE-THEME.md`

**Required route:**

1. Project Architect
2. ↓
3. Role Manager
4. ↓
5. Theme Architect
6. ↓
7. PHP Engineer
8. ↓
9. Block Engineer when required
10. ↓
11. JavaScript Engineer
12. ↓
13. CSS Engineer
14. ↓
15. Security Engineer
16. ↓
17. Performance Engineer
18. ↓
19. QA Engineer
20. ↓
21. Documentation Engineer
22. ↓
23. Release Engineer

## Block Creation Route

**Skill:**

`38_WORDPRESS/SKILLS/CREATE-BLOCK.md`

**Required route:**

1. Project Architect when project-level decisions are required
2. ↓
3. Role Manager
4. ↓
5. Block Engineer
6. ↓
7. JavaScript Engineer
8. ↓
9. PHP Engineer when dynamic rendering is required
10. ↓
11. REST Engineer when API access is required
12. ↓
13. CSS Engineer
14. ↓
15. Security Engineer
16. ↓
17. Performance Engineer when required
18. ↓
19. QA Engineer
20. ↓
21. Documentation Engineer
22. ↓
23. Release Engineer when the block is part of a release

## REST Endpoint Route

**Skill:**

`38_WORDPRESS/SKILLS/CREATE-REST-ENDPOINT.md`

**Required route:**

1. Role Manager
2. ↓
3. REST Engineer
4. ↓
5. PHP Engineer
6. ↓
7. Database Engineer when required
8. ↓
9. Security Engineer
10. ↓
11. Performance Engineer when required
12. ↓
13. QA Engineer
14. ↓
15. Documentation Engineer
16. ↓
17. Release Engineer when part of a release

## Shortcode Route

**Skill:**

`38_WORDPRESS/SKILLS/CREATE-SHORTCODE.md`

**Required route:**

1. Role Manager
2. ↓
3. PHP Engineer
4. ↓
5. CSS Engineer when presentation styles are required
6. ↓
7. JavaScript Engineer when interaction is required
8. ↓
9. Security Engineer
10. ↓
11. Performance Engineer when required
12. ↓
13. QA Engineer
14. ↓
15. Documentation Engineer

## Widget Route

**Skill:**

`38_WORDPRESS/SKILLS/CREATE-WIDGET.md`

**Required route:**

1. Role Manager
2. ↓
3. PHP Engineer
4. ↓
5. CSS Engineer when required
6. ↓
7. JavaScript Engineer when required
8. ↓
9. Security Engineer
10. ↓
11. QA Engineer
12. ↓
13. Documentation Engineer

## Code Review Route

**Skill:**

`38_WORDPRESS/SKILLS/REVIEW-CODE.md`

**Required route:**

1. Role Manager
2. ↓
3. Relevant Implementation Engineer
4. ↓
5. Security Engineer
6. ↓
7. Performance Engineer when required
8. ↓
9. QA Engineer
10. ↓
11. Documentation Engineer when documentation impact exists

The reviewer must not rely only on the implementation role's self-review.

## Performance Optimization Route

**Skill:**

`38_WORDPRESS/SKILLS/OPTIMIZE-PERFORMANCE.md`

**Required route:**

1. Role Manager
2. ↓
3. Performance Engineer
4. ↓
5. Responsible Implementation Engineer
6. ↓
7. Performance Engineer Revalidation
8. ↓
9. Security Engineer when security controls are affected
10. ↓
11. QA Engineer
12. ↓
13. Documentation Engineer when operational behavior changes

Performance optimization must begin with measurement and end with validation.

## Refactoring Route

**Skill:**

`38_WORDPRESS/SKILLS/REFACTOR-CODE.md`

**Required route:**

1. Role Manager
2. ↓
3. Relevant Architect when structural changes are significant
4. ↓
5. Relevant Implementation Engineer
6. ↓
7. Security Engineer
8. ↓
9. Performance Engineer when required
10. ↓
11. QA Engineer
12. ↓
13. Documentation Engineer
14. ↓
15. Release Engineer when part of a release

## Plugin Debugging Route

**Skill:**

`38_WORDPRESS/SKILLS/DEBUG-PLUGIN.md`

**Required route:**

1. Role Manager
2. ↓
3. QA Engineer (for defect triage)
4. ↓
5. Relevant Implementation Engineer (for root cause analysis & fix)
6. ↓
7. Security Engineer (when security boundaries are affected)
8. ↓
9. QA Engineer (for fix verification & regression)
10. ↓
11. Documentation Engineer (when behavior changes)

## Database Change Route

For significant database work:

1. Project Architect when data ownership changes
2. ↓
3. Plugin Architect
4. ↓
5. Database Engineer
6. ↓
7. PHP Engineer
8. ↓
9. Security Engineer
10. ↓
11. Performance Engineer
12. ↓
13. QA Engineer
14. ↓
15. Documentation Engineer
16. ↓
17. Release Engineer

## Migration Route

**Skill:**

`38_WORDPRESS/SKILLS/MIGRATE-PLUGIN.md`

**Required route:**

1. Project Architect
2. ↓
3. Role Manager
4. ↓
5. Plugin Architect
6. ↓
7. Database Engineer when persistent data changes
8. ↓
9. PHP Engineer
10. ↓
11. Other Implementation Engineers as required
12. ↓
13. Security Engineer
14. ↓
15. Performance Engineer when required
16. ↓
17. QA Engineer
18. ↓
19. Documentation Engineer
20. ↓
21. Release Engineer

Migration work must include upgrade and rollback planning.

## Documentation Route

**Skill:**

`38_WORDPRESS/SKILLS/WRITE-DOCUMENTATION.md`

**Required route:**

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

## Test Creation Route

**Skill:**

`38_WORDPRESS/SKILLS/CREATE-TESTS.md`

**Required route:**

1. Role Manager
2. ↓
3. QA Engineer
4. ↓
5. Relevant Implementation Engineer for technical context
6. ↓
7. Security Engineer for security test requirements
8. ↓
9. Performance Engineer for performance test requirements
10. ↓
11. QA Engineer Final Test Plan Approval

---

## Conditional Role Rules

A role may be added when the task crosses its responsibility boundary.

Examples:

| Condition | Add Role |
|---|---|
| Custom table or complex persistence | Database Engineer |
| REST endpoint or REST client contract | REST Engineer |
| Custom block or editor extension | Block Engineer |
| Client-side interaction | JavaScript Engineer |
| Custom styling or responsive layout | CSS Engineer |
| Security boundary | Security Engineer |
| High-frequency or expensive operation | Performance Engineer |
| User-visible or operational behavior change | Documentation Engineer |
| Production release | Release Engineer |

## Mandatory Roles

For production WordPress releases, the following independent gates are mandatory:

- Security Engineer
- QA Engineer
- Documentation Engineer
- Release Engineer

Performance Engineer is mandatory when meaningful performance-sensitive changes exist.

## Independent Review Rule

Independent review must be assigned when a task changes security boundaries, stored data, public output, release behavior, or user-facing workflows.

The implementing role must not be the only validating role for production-impacting work.

## Rule

The Skill routing matrix must map each WordPress task to required roles, gates, and handoffs.
