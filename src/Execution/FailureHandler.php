<?php

declare(strict_types=1);

namespace SquirrelForge\Execution;

use Closure;
use DateTimeImmutable;

/**
 * Receives execution failure reports, correlates and normalizes them
 * into an Execution Failure Record, forwards the record for a recovery
 * decision, and routes only an authorized recovery operation to its
 * owning Execution component, per 20_EXECUTION/FAILURE-HANDLER.md --
 * the fourth real component in 20_EXECUTION, and unusually
 * high-leverage: `ACTION-DISPATCHER.md`, `ROLLBACK-MANAGER.md`,
 * `WORKFLOW-EXECUTOR.md`, and `EXECUTION-ENGINE.md` all name this class
 * as something they report failures to, so building it now removes a
 * blocking dependency from every one of them.
 *
 * "The Failure Handler normalizes and routes; it does not decide
 * recovery" (Purpose) is upheld the same way `IntegrationManager`
 * treats its own `$recoveryRequest` -- the actual recovery decision is
 * a caller-supplied `Closure` (`$recoveryRequest`) standing in for
 * `17_COORDINATION/FAILURE-RECOVERY.md`, since that layer has no code
 * yet and this spec's own boundary forbids this class from selecting a
 * strategy itself regardless. `forward()` never resolves an
 * authorization on its own -- an unauthorized or unrecognized decision
 * terminates at `Not Authorized`, never silently proceeding to route
 * anything.
 *
 * "For Validation, Dependency, and Rule Failure, the Failure Handler
 * preserves the classification its authoritative source already
 * assigned. It does not independently judge validation, dependency, or
 * rule compliance itself" is enforced as a real, checked precondition
 * in `receive()`, not just documentation: a report claiming one of
 * those three Failure Types without a `source_classification_ref` is
 * rejected outright -- this class refuses to fabricate the
 * classification its own spec says it must not independently judge.
 *
 * The "Examples of Authorized Recovery Operations Routed" table names
 * three unambiguous fixed targets (Retry -> Action Dispatcher, Rollback
 * -> Rollback Manager, Terminate -> Execution Engine), captured here as
 * real default routing; `Skip`'s target is genuinely context-dependent
 * ("the component that owns the skipped step") so this class requires
 * the recovery decision to name it explicitly rather than guessing, and
 * `Escalate` is never routed to an Execution component at all -- the
 * table's own text says it goes "back through
 * `17_COORDINATION/FAILURE-RECOVERY.md`'s own escalation path," which
 * is exactly the `$recoveryRequest` closure this class already called,
 * so there is nothing further for `route()` to invoke.
 *
 * "Preserve failure-handling traceability across the intake,
 * forwarding, and routing steps" (Responsibilities) is genuine
 * composition of the already-built `SqliteExecutionLogger`, the same
 * "maintain history through the real, already-built logger" pattern
 * `ExecutionMonitor` established -- both `receive()` and `forward()`
 * record through it when configured.
 */
final class FailureHandler
{
    private const FAILURE_TYPES = [
        'Prerequisite Failure', 'Dispatch Failure', 'Execution Failure',
        'Validation Failure', 'Timeout Failure', 'Dependency Failure', 'Rule Failure',
    ];

    private const EXTERNALLY_CLASSIFIED_TYPES = ['Validation Failure', 'Dependency Failure', 'Rule Failure'];

    private const RECOVERY_OPERATIONS = ['Retry', 'Rollback', 'Skip', 'Escalate', 'Terminate'];

    private const DEFAULT_OPERATION_TARGETS = [
        'Retry' => 'action_dispatcher',
        'Rollback' => 'rollback_manager',
        'Terminate' => 'execution_engine',
    ];

    public function __construct(private readonly ?SqliteExecutionLogger $logger = null)
    {
    }

    /**
     * Failure Handling Process steps 1-5: receives an execution failure
     * report and normalizes it into an Execution Failure Record.
     *
     * @param array{
     *     execution_ref?: ?string,
     *     workflow_step_ref?: ?string,
     *     action_ref?: ?string,
     *     checkpoint_ref?: ?string,
     *     reporting_component?: ?string,
     *     failure_type?: ?string,
     *     observed_condition?: ?string,
     *     evidence_references?: array<int, string>,
     *     source_classification_ref?: ?string,
     *     monitor_finding_ref?: ?string,
     *     logger_ref?: ?string
     * } $report
     * @return array{outcome: string, failure_record: ?array<string, mixed>, error: ?string}
     */
    public function receive(array $report): array
    {
        $executionRef = $report['execution_ref'] ?? null;
        $reportingComponent = $report['reporting_component'] ?? null;
        $failureType = $report['failure_type'] ?? null;
        $observedCondition = $report['observed_condition'] ?? null;

        if (!$this->presentAndNonEmpty($executionRef) || !$this->presentAndNonEmpty($reportingComponent) || !$this->presentAndNonEmpty($observedCondition)) {
            return ['outcome' => 'invalid', 'failure_record' => null, 'error' => 'A failure report requires a non-empty execution_ref, reporting_component, and observed_condition.'];
        }

        if (!is_string($failureType) || !in_array($failureType, self::FAILURE_TYPES, true)) {
            return ['outcome' => 'invalid', 'failure_record' => null, 'error' => sprintf('"%s" is not one of this spec\'s named Failure Types.', (string) ($failureType ?? ''))];
        }

        if (in_array($failureType, self::EXTERNALLY_CLASSIFIED_TYPES, true) && !$this->presentAndNonEmpty($report['source_classification_ref'] ?? null)) {
            return [
                'outcome' => 'invalid',
                'failure_record' => null,
                'error' => sprintf('"%s" originates outside Execution and requires a source_classification_ref; it must not be independently judged here.', $failureType),
            ];
        }

        $failureId = 'failure_' . bin2hex(random_bytes(12));
        $record = [
            'failure_id' => $failureId,
            'execution_ref' => $executionRef,
            'workflow_step_ref' => $report['workflow_step_ref'] ?? null,
            'action_ref' => $report['action_ref'] ?? null,
            'checkpoint_ref' => $report['checkpoint_ref'] ?? null,
            'reporting_component' => $reportingComponent,
            'failure_type' => $failureType,
            'observed_condition' => $observedCondition,
            'evidence_references' => is_array($report['evidence_references'] ?? null) ? $report['evidence_references'] : [],
            'source_classification_ref' => $report['source_classification_ref'] ?? null,
            'monitor_finding_ref' => $report['monitor_finding_ref'] ?? null,
            'logger_ref' => $report['logger_ref'] ?? null,
            'timestamp' => (new DateTimeImmutable())->format(DATE_ATOM),
            'recovery_record_ref' => null,
        ];

        $this->logger?->record([
            'execution_id' => $executionRef,
            'task_id' => $report['action_ref'] ?? null,
            'actor' => $reportingComponent,
            'action_type' => 'failure_intake',
            'outcome' => $failureType,
            'error_category' => $failureType,
            'checkpoint_id' => $report['checkpoint_ref'] ?? null,
        ]);

        return ['outcome' => 'normalized', 'failure_record' => $record, 'error' => null];
    }

    /**
     * Failure Handling Process steps 6-9: forwards the normalized record
     * for a recovery decision, then routes only an authorized operation
     * to its owning Execution component.
     *
     * @param array<string, mixed> $failureRecord from receive().
     * @param Closure $recoveryRequest (array $failureRecord): array{authorized?: bool, operation?: ?string, target_component?: ?string, recovery_record_ref?: ?string, reason?: ?string} the real FAILURE-RECOVERY.md decision.
     * @param ?Closure $route (string $operation, string $targetComponent, array $failureRecord): mixed the real hand-off to the owning Execution component. Never invoked for Escalate or when unauthorized.
     * @return array{outcome: string, operation: ?string, target_component: ?string, recovery_record_ref: ?string, error: ?string}
     */
    public function forward(array $failureRecord, Closure $recoveryRequest, ?Closure $route = null): array
    {
        $decision = $recoveryRequest($failureRecord);
        $recoveryRecordRef = $decision['recovery_record_ref'] ?? null;

        if (($decision['authorized'] ?? false) !== true) {
            return $this->outcome('not_authorized', $failureRecord, null, null, $recoveryRecordRef, $decision['reason'] ?? 'Recovery was not authorized.');
        }

        $operation = $decision['operation'] ?? null;

        if (!is_string($operation) || !in_array($operation, self::RECOVERY_OPERATIONS, true)) {
            return $this->outcome('invalid_operation', $failureRecord, null, null, $recoveryRecordRef, sprintf('"%s" is not one of this spec\'s named Recovery Operations.', (string) ($operation ?? '')));
        }

        if ($operation === 'Escalate') {
            return $this->outcome('escalated', $failureRecord, $operation, null, $recoveryRecordRef, null);
        }

        $targetComponent = $decision['target_component'] ?? self::DEFAULT_OPERATION_TARGETS[$operation] ?? null;

        if (!$this->presentAndNonEmpty($targetComponent)) {
            return $this->outcome('not_routable', $failureRecord, $operation, null, $recoveryRecordRef, sprintf('Authorized operation "%s" has no target_component and no default routing target.', $operation));
        }

        if ($route !== null) {
            $route($operation, $targetComponent, $failureRecord);
        }

        return $this->outcome('routed', $failureRecord, $operation, $targetComponent, $recoveryRecordRef, null);
    }

    private function presentAndNonEmpty(mixed $value): bool
    {
        return is_string($value) && $value !== '';
    }

    /**
     * @param array<string, mixed> $failureRecord
     * @return array{outcome: string, operation: ?string, target_component: ?string, recovery_record_ref: ?string, error: ?string}
     */
    private function outcome(string $outcome, array $failureRecord, ?string $operation, ?string $targetComponent, ?string $recoveryRecordRef, ?string $error): array
    {
        $this->logger?->record([
            'execution_id' => $failureRecord['execution_ref'] ?? 'unknown',
            'task_id' => $failureRecord['action_ref'] ?? null,
            'actor' => 'failure_handler',
            'action_type' => 'failure_routing',
            'outcome' => $outcome,
            'error_category' => $error !== null ? $outcome : null,
            'checkpoint_id' => $failureRecord['checkpoint_ref'] ?? null,
        ]);

        return [
            'outcome' => $outcome,
            'operation' => $operation,
            'target_component' => $targetComponent,
            'recovery_record_ref' => $recoveryRecordRef,
            'error' => $error,
        ];
    }
}
