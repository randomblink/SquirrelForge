Status: Plan (revised, pending lock — not yet implemented)

---
# WP-SCENARIO-007 — Create REST Endpoint: Execution Plan

This is a pre-execution planning document. No fixture, test, or production code has been created, modified, or activated as a result of this document. No readiness document has been updated. This document is separate from `38_WORDPRESS/AGENT-SCENARIO-TESTS.md` (which holds the authoritative static scenario definition and, after execution, the Runtime Evidence section) and must not be confused with either.

## 1. Authoritative scenario definition

```text
Scenario ID: WP-SCENARIO-007
User Request: Add a REST endpoint that returns private member records to authorized admins.
Expected Primary Skill: CREATE-REST-ENDPOINT
Expected Supporting Skills: None unless part of larger plugin
Expected Required Roles: REST Engineer, PHP Engineer, Security Engineer, QA Engineer, Documentation Engineer
Expected Conditional Roles: Database Engineer, Performance Engineer
Expected Validation Gates: API Contract, Security, QA, Documentation
Expected Reports: REST Route Specification, REST Engineering Report, Security Review Report, QA Report
Pass Criteria: Agent requires permission callback, validation, sanitization, and sensitive-data review.
Result: PASS (after fix — see Gap; a prior pass added CREATE-REST-ENDPOINT.md's Completion Criteria section)
```

The request explicitly names "authorized admins," ruling out `__return_true` from the outset — the fixture below never considers a public permission callback.

## 2. Architecture trace

- **Routing**: `SKILL-ROUTING-MAP.md`, "New REST Endpoint" section — "If the primary deliverable is a REST route or API contract, select `CREATE-REST-ENDPOINT.md`."
- **Skill**: `CREATE-REST-ENDPOINT.md` — 7-stage workflow: REST Architecture (Stage 1) → Role Routing → Implementation (PHP Engineer) → **Security Validation** (Stage 4, blocking gate, "focusing on the permission_callback, argument validation, and sanitization") → Performance Validation (blocking only when required) → QA Validation (blocking) → Documentation. Rule #1: "Mandatory `permission_callback`... For public, read-only endpoints, it can be `__return_true`, but it must be explicitly defined" — **not applicable here**, since this scenario's own request requires admin-only access. Rule #2: define `args` for validation. Rule #3: return `WP_REST_Response`/`WP_Error`, never `echo`/`die()`. Rule #4: versioned namespace.
- **Security**: `SECURITY-VALIDATOR.md`, "REST API Validation" — "REST endpoints must define: permission callback, argument validation, argument sanitization. Endpoints without permission callbacks fail validation."
- **Knowledge**: `REST-API.md` — `register_rest_route()` must be called from `rest_api_init`; `args` schema drives validation/sanitization; "Security Note on `permission_callback`" explicitly requires a capability check for any endpoint beyond a trivial public read.
- **Role routing**: `ROLE-ROUTING-MATRIX.md`, Route 4 — Required: Role Manager, REST Engineer, PHP Engineer, Security Engineer, QA Engineer, Documentation Engineer. Conditional: Project/Plugin Architect (not triggered), Database Engineer (not triggered — a single option lookup, no custom table), JavaScript Engineer (not triggered — no client consumer built), Performance Engineer (not triggered — three static records), Release Engineer (not triggered).

## 3. Deterministic fixture design: `squirrelforge-rest-fixture`

A small plugin (prefix `sfrest_`) registering exactly one REST route under namespace `sfrest/v1`, backed by three deterministic, scenario-owned "member" records seeded into a single option (`sfrest_test_members`) — separately from plugin activation, so activation itself stays a clean, side-effect-free route registration.

### Fixture preparation vs. endpoint execution (explicitly sequenced, not concurrent)

Deterministic fixture *preparation* is defined now, before implementation. Endpoint *execution* (issuing any REST request against the route) does not occur until the endpoint has actually been implemented and activated. Fixture preparation defines:

- **Known seed records** (created after activation, before any request is issued):
  - `1` → `{ name: "Alice Example", email: "alice@example.test", tier: "gold" }`
  - `2` → `{ name: "Bob Example", email: "bob@example.test", tier: "silver" }`
  - `3` → `{ name: "Carol Example", email: "carol@example.test", tier: "bronze" }`
- **Administrator account**: an existing Hospital administrator, looked up read-only via `get_users()` and set current via `wp_set_current_user()` for one process only (the precedent established in WP-SCENARIO-010) — no new administrator account is created.
- **Authenticated low-capability account**: one temporary Subscriber-level user, created specifically to prove the `403` path fires correctly (the precedent established in WP-SCENARIO-003), deleted during cleanup.
- **Expected capabilities**: the route requires `manage_options`; the Subscriber-level account is confirmed to lack it before it is used to test rejection.
- **Plugin activation procedure**: activate the fixture; confirm no throwable; confirm the route is registered (see Section 4, REST Index Verification) — before any seed record exists and before any request is issued.
- **Cleanup procedure**: delete `sfrest_test_members` and any other scenario-owned option; delete the temporary Subscriber-level user; deactivate the fixture; remove temporary harness/capture files (see Section 15).
- **Expected request and response matrix**: see Section 4 (API Contract Record) below — defined now, verified later.

## 4. Endpoint contract

```text
Namespace: sfrest/v1
Route: /members/(?P<id>[\d]+)
HTTP Method: GET (WP_REST_Server::READABLE)

Path Parameter (required, enforced twice — by the URL regex and by args validation):
  id — integer
    validate_callback: is_numeric($param) && (int) $param > 0 && (int) $param <= 999999
    sanitize_callback: absint

Permission Callback (capability-gated, not __return_true):
  if ( ! is_user_logged_in() )       -> WP_Error( 'sfrest_unauthenticated', ..., ['status' => 401] )
  if ( ! current_user_can('manage_options') ) -> WP_Error( 'sfrest_forbidden', ..., ['status' => 403] )
  otherwise -> true

Success: 200, WP_REST_Response( ['id'=>int,'name'=>string,'email'=>string,'tier'=>string], 200 )
Errors:
  401 — not logged in
  403 — logged in, lacking manage_options
  400 — id present but fails validate_callback (0, or > 999999)
  404 — id well-formed but no matching record

Response shape: a single JSON object (not an array), stable key order (id, name, email, tier).
Side effects: none. This is a pure read endpoint; live validation explicitly verifies no write occurs regardless of outcome.
```

**On the URL-regex vs. args-validation distinction**: a request to a non-digit path segment (e.g., `/members/abc`) never reaches the route's callback at all — WordPress's own REST server returns 404 (`rest_no_route`) because the route pattern itself doesn't match. The `validate_callback`/`sanitize_callback` provide a second, independent layer for values that *are* all-digit strings but still invalid (`0`, or an implausibly large id) — this is where the endpoint's own validation logic is actually exercised, tested as a behavior distinct from routing-layer rejection.

### REST Index Verification (required, before any endpoint request is issued)

Immediately after activation, and before any request is made against the route, the following must be recorded directly from WordPress's own REST server (`rest_get_server()->get_routes()` or the REST index), not assumed from source code:

- namespace (`sfrest/v1`)
- exact route pattern (`/members/(?P<id>[\d]+)`)
- supported HTTP methods for this route
- the registered argument schema (confirming `id` appears with its `validate_callback`/`sanitize_callback`/`required` flags)
- presence of a `permission_callback` (confirmed non-null/non-default)
- confirmation that the route is visible in the REST index response

This step exists to separate three distinct failure classes that would otherwise be conflated by only testing end-to-end requests:
- a **registration failure** (the route never appears in the index at all — `register_rest_route()` was never reached, or `rest_api_init` never fired),
- a **routing failure** (the route is registered, but a request to it 404s or matches the wrong pattern),
- a **callback failure** (the route is registered and matched correctly, but the permission, validation, or response logic behaves incorrectly).

Only after this verification passes does live request execution begin.

### API Contract Record

| Request | Expected Status | Expected Body |
|---|---:|---|
| Valid administrator request | 200 | Member record |
| Anonymous request | 401 | Authentication error |
| Authenticated user without required capability | 403 | Authorization error |
| Invalid ID | 400 | Validation error |
| Nonexistent ID | 404 | Not-found error |

This table is the authoritative pre-execution contract. The live validation matrix (Section 9) exercises exactly these five rows, plus the two supplementary checks (unsupported HTTP method; non-digit path segment) that fall outside this table because they are routing-layer behaviors rather than endpoint-contract behaviors.

## 5. Authorization model

- **Anonymous** (no current user): denied, `401`.
- **Authenticated, unauthorized** (temporary Subscriber-level user, deleted at cleanup): denied, `403`.
- **Authenticated, authorized** (existing Hospital administrator, read-only lookup, no new account created): succeeds, `200`.
- `__return_true` is not used anywhere in this fixture.

## 6. Validation and sanitization

- Missing/malformed `id` (non-digit): rejected at the routing layer (404, before the endpoint's own code runs).
- `id` present but `0` or `> 999999`: rejected by `validate_callback` (400) — distinct from routing-layer rejection.
- Accepted `id` is `absint()`-sanitized before being used as an array key lookup.
- Validation (is it a valid identifier at all?) and sanitization (coerce to a safe integer) are implemented as two separate callback responsibilities, not conflated into one function.

## 7. Response handling

- All success and error paths return `WP_REST_Response` or `WP_Error` — no `echo`/`die()` anywhere.
- Exact status codes: `200`, `400`, `401`, `403`, `404` — any unexpected `500` during live execution would itself be a finding, not a designed outcome.
- Response bodies contain only the four defined fields per record — no internal paths, SQL, stack traces, or unrelated user data.
- Response data is inherently JSON-serializable; stability confirmed by comparing repeated live requests' JSON output byte-for-byte.

## 8. Functional test plan (fake bootstrap, written before final implementation claims)

A `register_rest_route()` fake records the full args array for inspection; a minimal `WP_REST_Request`/`WP_REST_Response`/`WP_Error` double is provided. Tests:
1. Route registration recorded with correct namespace, route pattern, method, permission callback, and `args` schema present.
2. Calling the callback directly (fake request with a valid `id`) while permission-context is "admin" → `200` with the exact expected record fields.
3. Calling with an `id` matching no record → `404` with code `sfrest_member_not_found`.
4. Invoking the `id` `validate_callback` directly with `0`, a value `> 999999`, and a valid mid-range value → correctly returns false/false/true respectively.
5. Invoking the `sanitize_callback` directly with a numeric string → confirms `absint()`-equivalent coercion.
6. Invoking the permission callback under three stubbed contexts (logged out / logged in non-admin / logged in admin) → confirms `401` `WP_Error` / `403` `WP_Error` / `true` respectively.
7. Confirming the scenario-owned `sfrest_test_members` option is never written to by the callback (read-only, both on success and on rejection paths).
8. Confirming response field order and shape match the defined contract exactly.

Note (disclosed in advance): "missing required parameter" as a *unit-level* test isn't directly reachable, since the route's own regex makes an absent/malformed `id` a 404 at the WordPress routing layer before any endpoint code runs — verified live instead (Section 9), not asserted at the unit level where it cannot actually occur.

## 9. Live runtime validation

1. Activate the fixture; confirm no throwable.
2. **REST Index Verification** (see Section 4) — confirm namespace, route pattern, methods, argument schema, and permission-callback presence directly from the REST server, before any request is issued.
3. Seed the three deterministic member records into `sfrest_test_members`.
4. Exercise via `rest_do_request()` in fresh, independent contexts, against the API Contract Record (Section 4):
   - Valid administrator request → `200`, member record.
   - Anonymous request → `401`, authentication error.
   - Authenticated, unauthorized (Subscriber) request → `403`, authorization error.
   - Invalid ID (`0` and an oversized id, each its own case) → `400`, validation error.
   - Nonexistent ID (e.g., `999`) → `404`, not-found error.
5. Supplementary routing-layer checks (outside the API Contract Record, recorded separately): an unsupported method (e.g., `POST`) — status observed and recorded exactly as returned, not assumed in advance; a non-digit path segment (`/members/abc`) — expected `404` at the routing layer.
6. Confirm rejected requests never alter `sfrest_test_members` or create any other option.
7. Confirm the successful request also creates no side effect (pure read).
8. Confirm zero PHP warnings, notices, deprecations, or errors at every step.
9. Repeat the successful request twice and confirm byte-for-byte identical JSON output.

## 10. Security validation

- Permission callback is invoked and enforced for every request path, confirmed live.
- Capability check (`current_user_can('manage_options')`) runs server-side in PHP.
- `id` is the only user-controlled input; regex-constrained, `validate_callback`-checked, and `absint()`-sanitized before use — no SQL anywhere in this fixture.
- No stored or reflected unsafe output — fixed, scenario-owned scalar fields only.
- No sensitive data leakage beyond the four intentionally-exposed fields.
- No unrestricted exposure — `__return_true` is never used.
- No privilege escalation via parameter manipulation — `id` only selects which record to view; it has no path to bypass the permission check, which runs independently and first.

## 11. Implementation boundary

One bounded REST capability — a single GET route. No unrelated feature. No unrelated refactor. No changes to any other plugin, theme, WordPress core, or real Hospital content. No relaxation of the permission callback, validation, or sanitization for test convenience.

## 12. Classification criteria

**PASS** requires: the full contract defined before implementation; `401`/`403`/`200` all independently proven live for anonymous/unauthorized/authorized contexts; `400`/`404` validation and not-found behavior proven; sanitization proven; rejected requests confirmed to create zero side effects; the successful request confirmed to create zero side effects and to be stable/repeatable; automated tests and live REST execution agree on every behavior each can independently exercise; zero PHP errors/warnings/notices/deprecations; cleanup and repository boundaries confirmed.

**PARTIAL** applies only if the core authorization/validation/response contract is fully proven, but one secondary/non-essential item is incomplete. A missing or overly-permissive permission callback, any unauthorized success, any accepted invalid input, or any side effect from a rejected request is never PARTIAL — those are FAIL.

**FAIL** applies if: the permission callback is missing or effectively permissive; unauthorized access succeeds; invalid `id` values are accepted; status codes materially obscure what happened; a rejected request alters state; the response leaks data beyond the four defined fields; any test only passes because authentication, a capability check, or validation was weakened/bypassed; any unrelated file or data changes.

**NOT EXECUTABLE** applies only if an external environment limitation prevents even activating the fixture and exercising one authenticated request (none anticipated).

## 13. Required reports

REST Route Specification, REST Engineering Report, Security Review Report, QA Report, plus this session's established API Contract Record and Compatibility Record pattern — produced after execution.

## 14. Metrics to be recorded (values captured only after execution)

| Metric | To be recorded |
|---|---|
| Routes registered | 1 |
| HTTP methods tested | GET (registered) + at least 1 unsupported method probe |
| Authorization contexts tested | 3 (anonymous, unauthorized-authenticated, authorized) |
| Valid requests | measured |
| Rejected requests | measured |
| Status codes observed | 200, 401, 403, 400, 404, plus the unsupported-method probe's actual result |
| Functional test/assertion counts | measured at implementation time |
| Files inspected | 1 (the fixture's own route-registration file) |
| Files added/changed | measured |
| Lines added/removed | measured |
| Scenario-owned records created/removed | 3 created, 3 removed |
| PHP errors observed | expected 0 |
| Unauthorized accesses permitted | expected 0 |
| Observable contract deviations | expected 0 |

## 15. Cleanup and repository boundaries

Remove `sfrest_test_members` and any other scenario-owned option; delete the temporary Subscriber-level test user; remove the temporary harness script and capture files from the scratchpad directory; deactivate the fixture; verify CSHD and all seven prior validation/fixture plugins untouched; verify SquirrelForge `git status`/`git diff --check`/`composer test` clean before and after; verify no routing/Skill/Role/knowledge/scenario-definition/readiness file touched.

## 16. Required execution order

1. Revise the plan.
2. Lock the revised plan.
3. Prepare the deterministic fixture.
4. Begin implementation.
5. Run unit tests.
6. Verify endpoint registration through the REST index.
7. Execute the live REST validation matrix.
8. Clean up all temporary data, users, options, and artifacts.
9. Verify repository boundaries.
10. Write runtime execution evidence.
11. Commit only after every validation and cleanup check passes.

This document represents step 1 (revise) of that order. Step 2 (lock) occurs upon your review and approval. No step from 3 onward has begun.

## 17. Capability progression

| Scenario | Demonstrated capability |
|---|---|
| WP-SCENARIO-001 | Plugin creation |
| WP-SCENARIO-002 | Runtime debugging |
| WP-SCENARIO-003 | Static code review |
| WP-SCENARIO-004 | Behavior-preserving refactoring |
| WP-SCENARIO-005 | Performance optimization |
| WP-SCENARIO-006 | Plugin Migration |
| WP-SCENARIO-007 | Secure REST endpoint engineering |

The WP-SCENARIO-006 row uses the exact capability label recorded in its completed execution report — `38_WORDPRESS/AGENT-READINESS-REPORT.md`, Capability Summary section, the line beginning "Plugin Migration: Operational — WP-SCENARIO-006." No other label (e.g. "Database migration," "Data migration") appears anywhere in that report for this scenario.

---

## GO / NO-GO Recommendation: **GO** (pending lock)

This scenario remains fully bounded and deterministic. The additions in this revision — REST index verification as a distinct pre-request step, an explicit API Contract Record, an explicit fixture-preparation/endpoint-execution sequencing boundary, and a capability-progression table using the verified WP-SCENARIO-006 label rather than an inferred one — strengthen the evidence plan without changing its scope. No naming collision or environment blocker exists. Awaiting lock before implementation begins.
