<?php

declare(strict_types=1);

namespace SquirrelForge\Execution;

use Closure;

/**
 * Observes active workflow execution, tracks per-action progress,
 * detects failures or stalls, and reports execution health, per
 * 20_EXECUTION/EXECUTION-MONITOR.md -- the third real component in
 * 20_EXECUTION.
 *
 * This spec's own "Depends On" (`WORKFLOW-EXECUTOR.md`,
 * `EXECUTION-ENGINE.md`, `FAILURE-HANDLER.md`) names components this
 * class reports *to*, not code it must call -- the Responsibilities are
 * literally "report... to WORKFLOW-EXECUTOR.md" and "report... to
 * EXECUTION-ENGINE.md", an output relationship, the same "$dispatch is
 * optional, never invented by this class on its own" shape
 * `WebhookManager::receiveInbound()` and `26_INTEGRATIONS/INTEGRATION-MONITOR.md`'s
 * `IntegrationMonitor` already establish for this codebase: `track()`
 * interprets caller-supplied signals into a real finding, and an
 * optional `$reportFailure` closure is invoked only when that finding
 * is genuinely `Failed`/`Stalled` -- this class never fabricates a
 * report to a component it never calls directly.
 *
 * The Execution Status and Health Signal vocabularies are real, disjoint
 * concerns this class keeps separate: Health Signals describe *what was
 * observed* (Progress/Delay/Timeout/Error/Dependency Block/Completion),
 * Execution Status is *the resulting classification* `track()` derives
 * from them with a real, documented precedence (Error > Timeout >
 * Dependency Block > Completion > Delay/Progress > Queued) -- a
 * contradictory input (e.g. both `error` and `completion_signal`) is
 * resolved by Error outranking Completion, since a reported error means
 * the action did not genuinely finish, regardless of what else it
 * reported.
 *
 * `Retrying` and `Escalated` are deliberately not producible by
 * `track()` at all: both are described in the spec's own Execution
 * Status table as states reached "under an authorization received back
 * through `FAILURE-HANDLER.md`" -- information this class's own
 * caller-supplied progress signals cannot contain, since recovery
 * authorization is `17_COORDINATION/FAILURE-RECOVERY.md`'s authority,
 * routed through the still-uncoded `FAILURE-HANDLER.md`. `markRetrying()`
 * is a separate, explicit method requiring a non-empty `authorized_by`
 * reference -- the same "refuse outright without explicit authorization"
 * discipline `SqliteCheckpointManager::skip()` already established for
 * this layer -- so this class never infers an authorization it was
 * never actually given.
 *
 * "Maintain execution history" (Responsibilities) is genuine composition
 * of the just-built `SqliteExecutionLogger` rather than a second,
 * parallel history mechanism: every `track()` observation is recorded
 * through it when configured, which also means every observation's
 * signals get that logger's own built-in secret redaction for free.
 * "Confirm that an action has signaled completion, without collecting or
 * registering its result (owned by `RESULT-COLLECTOR.md`)" is upheld
 * literally: `track()` never returns or stores the action's actual
 * output/result value, only the fact that a `completion_signal` was
 * observed.
 */
final class ExecutionMonitor
{
    private const AUTONOMOUS_STATUSES = ['Queued', 'Running', 'Waiting', 'Complete', 'Failed', 'Stalled'];

    private const REPORTABLE_STATUSES = ['Failed', 'Stalled'];

    public function __construct(private readonly ?SqliteExecutionLogger $logger = null)
    {
    }

    /**
     * Monitoring Process steps 1-6: interprets caller-supplied progress
     * signals for one running action into an Execution Status, the
     * Health Signals that produced it, and (step 7) reports a
     * Failed/Stalled finding onward when `$reportFailure` is supplied.
     *
     * @param array{
     *     queued?: bool,
     *     completion_signal?: bool,
     *     error?: ?string,
     *     dependency_blocked?: bool,
     *     elapsed_ms?: ?float,
     *     timeout_ms?: ?float,
     *     progress_ratio?: ?float,
     *     expected_progress_ratio?: ?float,
     *     progress_tolerance?: float
     * } $signals
     * @param ?Closure $reportFailure (array $finding): mixed the real hand-off to FAILURE-HANDLER.md. Invoked only when status resolves to Failed or Stalled.
     * @return array{status: string, health_signals: array<int, string>, execution_id: string, action_ref: string, escalated: bool}
     */
    public function track(string $executionId, string $actionRef, array $signals = [], ?Closure $reportFailure = null): array
    {
        [$status, $healthSignals] = $this->classify($signals);
        $escalated = false;

        if (in_array($status, self::REPORTABLE_STATUSES, true) && $reportFailure !== null) {
            $reportFailure(['execution_id' => $executionId, 'action_ref' => $actionRef, 'status' => $status, 'health_signals' => $healthSignals]);
            $escalated = true;
        }

        $this->logger?->record([
            'execution_id' => $executionId,
            'task_id' => $actionRef,
            'actor' => 'execution_monitor',
            'action_type' => 'monitor_tick',
            'inputs' => $signals,
            'outcome' => $status,
            'error_category' => $escalated ? 'reported_to_failure_handler' : null,
        ]);

        return ['status' => $status, 'health_signals' => $healthSignals, 'execution_id' => $executionId, 'action_ref' => $actionRef, 'escalated' => $escalated];
    }

    /**
     * Records that an action has resumed under an authorization
     * received back through FAILURE-HANDLER.md -- refused outright
     * without a non-empty authorization reference, since this class has
     * no other way to know a retry was genuinely authorized.
     *
     * @return array{outcome: string, execution_id: string, action_ref: string, error: ?string}
     */
    public function markRetrying(string $executionId, string $actionRef, string $authorizedBy): array
    {
        if ($authorizedBy === '') {
            return ['outcome' => 'unauthorized', 'execution_id' => $executionId, 'action_ref' => $actionRef, 'error' => 'Marking an action as Retrying requires a non-empty authorized_by reference.'];
        }

        $this->logger?->record([
            'execution_id' => $executionId,
            'task_id' => $actionRef,
            'actor' => $authorizedBy,
            'action_type' => 'monitor_retry_authorized',
            'outcome' => 'Retrying',
        ]);

        return ['outcome' => 'recorded', 'execution_id' => $executionId, 'action_ref' => $actionRef, 'error' => null];
    }

    /**
     * @param array<string, mixed> $signals
     * @return array{0: string, 1: array<int, string>}
     */
    private function classify(array $signals): array
    {
        $healthSignals = [];

        if (($signals['error'] ?? null) !== null) {
            return ['Failed', ['Error']];
        }

        $elapsed = $signals['elapsed_ms'] ?? null;
        $timeout = $signals['timeout_ms'] ?? null;

        if (is_numeric($elapsed) && is_numeric($timeout) && (float) $elapsed > (float) $timeout) {
            return ['Stalled', ['Timeout']];
        }

        if (($signals['dependency_blocked'] ?? false) === true) {
            return ['Waiting', ['Dependency Block']];
        }

        if (($signals['completion_signal'] ?? false) === true) {
            return ['Complete', ['Completion']];
        }

        $progress = $signals['progress_ratio'] ?? null;
        $expected = $signals['expected_progress_ratio'] ?? null;
        $tolerance = $signals['progress_tolerance'] ?? 0.1;

        if (is_numeric($progress) && is_numeric($expected) && (float) $progress < (float) $expected - $tolerance) {
            $healthSignals[] = 'Delay';
        } elseif (is_numeric($progress)) {
            $healthSignals[] = 'Progress';
        }

        if ($healthSignals !== []) {
            return ['Running', $healthSignals];
        }

        if (($signals['queued'] ?? false) === true || $signals === []) {
            return ['Queued', []];
        }

        return ['Running', []];
    }
}
