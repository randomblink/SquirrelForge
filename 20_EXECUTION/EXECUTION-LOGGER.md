# SquirrelForge Execution Logger

Version: 1.0.0
Status: Stable
Owner: Execution Maintainers
Depends On: `20_EXECUTION/EXECUTION-ENGINE.md`, `20_EXECUTION/WORKFLOW-EXECUTOR.md`, `20_EXECUTION/ACTION-DISPATCHER.md`, `20_EXECUTION/CHECKPOINT-MANAGER.md`, `20_EXECUTION/ROLLBACK-MANAGER.md`
Used By: `20_EXECUTION/EXECUTION-REPORTER.md`, `20_EXECUTION/FAILURE-HANDLER.md`, `20_EXECUTION/RESULT-COLLECTOR.md`, `23_GOVERNANCE`, `17_COORDINATION/FAILURE-RECOVERY.md`
Last Updated: 2026-07-06

Records timestamp, execution and task IDs, actor, action type, sanitized inputs, outcome, duration, checkpoint, and error category, as emitted by the other Execution components above. Secrets and unnecessary personal data must be redacted.

The Logger records what already happened; it does not classify a failure's recovery path (owned by `17_COORDINATION/FAILURE-RECOVERY.md`) or decide validation, artifact, or workflow-completion outcomes (owned by `14_ENGINE/VALIDATION.md`, `20_EXECUTION/WORKFLOW-EXECUTOR.md`, and `20_EXECUTION/EXECUTION-ENGINE.md`). Other Execution components reference its entries as evidence; they do not treat it as a decision authority.
