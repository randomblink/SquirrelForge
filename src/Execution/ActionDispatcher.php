<?php

declare(strict_types=1);

namespace SquirrelForge\Execution;

use Closure;
use Throwable;

/**
 * Translates an approved workflow step into an executable action and
 * performs the technical dispatch to the target `14_ENGINE/TASK-ROUTER.md`
 * has already selected, confirming receipt and recording the result,
 * per 20_EXECUTION/ACTION-DISPATCHER.md -- the fifth real component in
 * 20_EXECUTION.
 *
 * "Receive a workflow action from `WORKFLOW-EXECUTOR.md`" and "read the
 * already-selected execution target from `TASK-ROUTER.md`'s routing
 * record" are both input relationships, not call dependencies -- the
 * same "Depends On names a producer, not code I call" reasoning
 * `ExecutionLogger`/`ExecutionMonitor` already established for this
 * layer -- so this class is buildable before `WORKFLOW-EXECUTOR.md`
 * exists: `$action['target_ref']` arrives caller-supplied, already
 * resolved by Task Router, and this class never selects or reroutes it
 * itself (the Purpose's own "does not decide task ownership").
 *
 * The actual technical dispatch mechanics are a caller-supplied
 * `Closure` (`$dispatchTarget`) -- an Agent Architect, a Validation
 * Engine, and every other row in the spec's own Action Types table are
 * reached completely differently, so inventing one dispatch protocol
 * here would fabricate a choice this spec never makes, the same
 * reasoning behind every provider-facing `Closure` already established
 * across this codebase. Omitting it is a real dry run landing at
 * `Dispatched` -- the Dispatch Record's own named "sent, no outcome
 * known yet" status -- without fabricating an execution result.
 *
 * "Verify execution prerequisites" (Responsibilities) and "report a
 * dispatch failure to `FAILURE-HANDLER.md`" are genuine composition of
 * the just-built `FailureHandler`, not two separate mechanisms: an
 * unmet prerequisite or missing required field reports as `FailureHandler`'s
 * own named `Prerequisite Failure` type, a thrown/unreachable dispatch
 * reports as `Dispatch Failure` ("could not be routed"), and a dispatch
 * that ran but reported its own failure reports as `Execution Failure`
 * ("ran but did not complete successfully") -- the exact three-way
 * split `FAILURE-HANDLER.md`'s own Failure Types table draws between
 * these causes. This class "acts on the authorized instruction it
 * receives back" (Purpose) by forwarding `$recoveryRequest`/`$route`
 * straight through to `FailureHandler::forward()` rather than deciding
 * recovery itself.
 *
 * "Record the dispatch" (Responsibilities) composes the already-built
 * `SqliteExecutionLogger` directly, distinct from whatever
 * `FailureHandler` separately logs about the failure-routing side --
 * two different actors recording two different aspects of the same
 * event, not a duplicate of the same fact.
 */
final class ActionDispatcher
{
    private const POST_DISPATCH_STATUSES = ['Dispatched', 'Running', 'Complete', 'Failed'];

    public function __construct(
        private readonly ?SqliteExecutionLogger $logger = null,
        private readonly ?FailureHandler $failureHandler = null
    ) {
    }

    /**
     * @param array{
     *     action_id?: ?string,
     *     workflow_ref?: ?string,
     *     action_type?: ?string,
     *     target_ref?: ?string,
     *     prerequisites?: array<int, array{name: string, met: bool}>
     * } $action
     * @param ?Closure $dispatchTarget (string $targetRef, array $action): array{status?: ?string, result?: mixed, error?: ?string} the real technical dispatch to the already-selected target. Omitting it leaves the result at `Dispatched` without fabricating an execution outcome.
     * @param ?Closure $recoveryRequest forwarded verbatim to FailureHandler::forward() when a failure occurs and both this and a FailureHandler are configured.
     * @param ?Closure $route forwarded verbatim to FailureHandler::forward().
     * @return array{status: string, action_id: string, workflow_ref: ?string, target_ref: ?string, result: mixed, error: ?string, recovery: ?array<string, mixed>}
     */
    public function dispatch(array $action, ?Closure $dispatchTarget = null, ?Closure $recoveryRequest = null, ?Closure $route = null): array
    {
        $actionId = $this->presentAndNonEmpty($action['action_id'] ?? null) ? $action['action_id'] : 'action_' . bin2hex(random_bytes(12));
        $workflowRef = $action['workflow_ref'] ?? null;
        $actionType = $action['action_type'] ?? null;
        $targetRef = $action['target_ref'] ?? null;

        if (!$this->presentAndNonEmpty($workflowRef) || !$this->presentAndNonEmpty($actionType) || !$this->presentAndNonEmpty($targetRef)) {
            return $this->fail($actionId, $workflowRef, $targetRef, 'Prerequisite Failure', 'Action is missing a required workflow_ref, action_type, or target_ref.', $recoveryRequest, $route);
        }

        $unmetPrerequisite = $this->firstUnmetPrerequisite($action['prerequisites'] ?? []);

        if ($unmetPrerequisite !== null) {
            return $this->fail($actionId, $workflowRef, $targetRef, 'Prerequisite Failure', sprintf('Required prerequisite "%s" was not met.', $unmetPrerequisite), $recoveryRequest, $route);
        }

        if ($dispatchTarget === null) {
            $this->record($actionId, $workflowRef, 'Dispatched');

            return $this->outcome('Dispatched', $actionId, $workflowRef, $targetRef, null, null, null);
        }

        try {
            $outcome = $dispatchTarget($targetRef, $action);
        } catch (Throwable $e) {
            return $this->fail($actionId, $workflowRef, $targetRef, 'Dispatch Failure', $e->getMessage(), $recoveryRequest, $route);
        }

        $error = $outcome['error'] ?? null;
        $status = $outcome['status'] ?? null;

        if ($error !== null || $status === 'Failed') {
            return $this->fail($actionId, $workflowRef, $targetRef, 'Execution Failure', $error ?? 'The dispatch target reported failure with no error detail.', $recoveryRequest, $route);
        }

        if (!is_string($status) || !in_array($status, self::POST_DISPATCH_STATUSES, true)) {
            $status = 'Complete';
        }

        $this->record($actionId, $workflowRef, $status);

        return $this->outcome($status, $actionId, $workflowRef, $targetRef, $outcome['result'] ?? null, null, null);
    }

    /**
     * @param array<int, array{name: string, met: bool}> $prerequisites
     */
    private function firstUnmetPrerequisite(array $prerequisites): ?string
    {
        foreach ($prerequisites as $prerequisite) {
            if (($prerequisite['met'] ?? false) !== true) {
                return $prerequisite['name'] ?? 'unnamed prerequisite';
            }
        }

        return null;
    }

    private function fail(
        string $actionId,
        ?string $workflowRef,
        ?string $targetRef,
        string $failureType,
        string $reason,
        ?Closure $recoveryRequest,
        ?Closure $route
    ): array {
        $this->record($actionId, $workflowRef, 'Failed');

        $recovery = null;

        if ($this->failureHandler !== null) {
            $received = $this->failureHandler->receive([
                'execution_ref' => $workflowRef ?? 'unknown',
                'action_ref' => $actionId,
                'reporting_component' => 'action_dispatcher',
                'failure_type' => $failureType,
                'observed_condition' => $reason,
            ]);

            if ($received['outcome'] === 'normalized' && $recoveryRequest !== null) {
                $recovery = $this->failureHandler->forward($received['failure_record'], $recoveryRequest, $route);
            }
        }

        return $this->outcome('Failed', $actionId, $workflowRef, $targetRef, null, $reason, $recovery);
    }

    private function record(string $actionId, ?string $workflowRef, string $status): void
    {
        $this->logger?->record([
            'execution_id' => $workflowRef ?? 'unknown',
            'task_id' => $actionId,
            'actor' => 'action_dispatcher',
            'action_type' => 'dispatch',
            'outcome' => $status,
        ]);
    }

    private function presentAndNonEmpty(mixed $value): bool
    {
        return is_string($value) && $value !== '';
    }

    /**
     * @return array{status: string, action_id: string, workflow_ref: ?string, target_ref: ?string, result: mixed, error: ?string, recovery: ?array<string, mixed>}
     */
    private function outcome(string $status, string $actionId, ?string $workflowRef, ?string $targetRef, mixed $result, ?string $error, ?array $recovery): array
    {
        return [
            'status' => $status,
            'action_id' => $actionId,
            'workflow_ref' => $workflowRef,
            'target_ref' => $targetRef,
            'result' => $result,
            'error' => $error,
            'recovery' => $recovery,
        ];
    }
}
