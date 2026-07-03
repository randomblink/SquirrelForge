# SquirrelForge Engine API

Version: 1.0.0
Status: Stable
Owner: Architecture Maintainers
Depends On: Engine Components
Used By: User Interface and Integrations
Last Updated: 2026-07-01

`submit(request, projectRef) → executionId`; `inspect(executionId) → state`; `provideInput(executionId, input) → receipt`; `cancel(executionId, reason) → result`; `result(executionId) → report`. Requests are idempotent when supplied an idempotency key.
