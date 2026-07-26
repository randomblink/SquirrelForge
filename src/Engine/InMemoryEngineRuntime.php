<?php

declare(strict_types=1);

namespace SquirrelForge\Engine;

use Closure;
use SquirrelForge\Contracts\EngineRuntimeInterface;

final class InMemoryEngineRuntime implements EngineRuntimeInterface
{
    /**
     * @var array<string, string>
     */
    private array $idempotency = [];

    /**
     * @var array<string, array>
     */
    private array $executions = [];

    private int $sequence = 0;

    public function __construct(private readonly ?Closure $validationDecision = null)
    {
    }

    public function submit(
        array $request,
        string $projectRef,
        string $identityRef,
        string $permissionRef,
        string $idempotencyKey,
        string $correlationId
    ): array {
        if ($permissionRef === 'permission:denied') {
            return $this->error('UNAUTHORIZED', 'The supplied permission reference was denied.', false);
        }

        if (isset($this->idempotency[$idempotencyKey])) {
            return ['execution_ref' => $this->idempotency[$idempotencyKey]];
        }

        $executionRef = 'exec_' . (++$this->sequence);
        $decision = $this->decisionFor($request);
        $validationRef = 'validation_' . $executionRef;
        $stateRef = 'state_' . $executionRef;
        $resultSetRef = 'result_set_' . $executionRef;
        $reportRef = 'report_' . $executionRef;
        $completedAt = gmdate(DATE_RFC3339);
        $accepted = in_array($decision, ['ACCEPTED', 'ACCEPTED_WITH_LIMITATIONS'], true);
        $limitations = $decision === 'ACCEPTED_WITH_LIMITATIONS'
            ? ['The deterministic reference workflow completed with declared limitations.']
            : [];

        $this->idempotency[$idempotencyKey] = $executionRef;
        $this->executions[$executionRef] = [
            'execution_ref' => $executionRef,
            'request' => $request,
            'project_ref' => $projectRef,
            'identity_ref' => $identityRef,
            'permission_ref' => $permissionRef,
            'correlation_id' => $correlationId,
            'state_ref' => $stateRef,
            'status' => $accepted ? 'COMPLETE' : 'FAILED',
            'cancelled' => false,
            'inputs' => [],
            'validation_record' => [
                'schema_version' => '1.0.0',
                'validation_id' => $validationRef,
                'request_id' => (string) ($request['request_id'] ?? $executionRef),
                'correlation_id' => $correlationId,
                'subject' => [
                    'type' => 'execution_result',
                    'refs' => [$resultSetRef],
                    'version_ref' => $executionRef,
                ],
                'started_at' => $completedAt,
                'completed_at' => $completedAt,
                'decision' => $decision,
                'items' => [
                    [
                        'item_id' => 'validation_item_' . $executionRef,
                        'stage' => 'ACCEPTANCE',
                        'requirement_ref' => 'deterministic_reference_workflow',
                        'description' => 'The deterministic reference workflow result meets its configured decision.',
                        'required' => true,
                        'blocking' => true,
                        'owner_ref' => 'in_memory_engine_runtime',
                        'method' => 'deterministic_decision',
                        'expected' => 'Configured validation decision is applied without reinterpretation.',
                        'evidence_refs' => [$resultSetRef],
                        'status' => $accepted ? 'PASSED' : 'FAILED',
                        'attempt' => 1,
                        'failure_code' => $accepted ? null : 'DETERMINISTIC_REJECTION',
                        'observed' => $decision,
                        'invalidates' => [],
                    ],
                ],
                'summary' => [
                    'total' => 1,
                    'passed' => $accepted ? 1 : 0,
                    'failed' => $accepted ? 0 : 1,
                    'unavailable' => 0,
                    'waived' => 0,
                    'stale' => 0,
                    'not_required' => 0,
                    'cancelled' => 0,
                ],
                'limitations' => $limitations,
                'residual_risks' => [],
                'next_action' => $accepted
                    ? 'Return the validated deterministic result.'
                    : 'Repair the rejected result before resubmission.',
            ],
            'result' => [
                'execution_ref' => $executionRef,
                'state_ref' => $stateRef,
                'status' => $accepted ? 'COMPLETE' : 'FAILED',
                'result_set_refs' => [$resultSetRef],
                'validation_record_ref' => $validationRef,
                'validation_decision' => $decision,
                'report_refs' => [$reportRef],
                'limitations' => $limitations,
                'next_action' => $accepted
                    ? 'Return the validated deterministic result.'
                    : 'Repair the rejected result before resubmission.',
            ],
        ];

        return ['execution_ref' => $executionRef];
    }

    public function inspect(string $executionRef): array
    {
        $execution = $this->execution($executionRef);

        if (isset($execution['error'])) {
            return $execution;
        }

        return [
            'execution_ref' => $executionRef,
            'state_ref' => $execution['state_ref'],
            'status' => $execution['cancelled'] ? 'CANCELLED' : $execution['status'],
            'validation_record_ref' => $execution['cancelled']
                ? null
                : $execution['validation_record']['validation_id'],
            'validation_decision' => $execution['cancelled']
                ? null
                : $execution['validation_record']['decision'],
            'limitations' => $execution['cancelled']
                ? []
                : $execution['validation_record']['limitations'],
            'next_action' => $execution['cancelled']
                ? 'No further execution is permitted.'
                : $execution['validation_record']['next_action'],
        ];
    }

    public function provideInput(
        string $executionRef,
        array $input,
        string $identityRef,
        string $permissionRef,
        string $correlationId
    ): array {
        if ($permissionRef === 'permission:denied') {
            return $this->error('UNAUTHORIZED', 'The supplied permission reference was denied.', false);
        }

        if (!isset($this->executions[$executionRef])) {
            return $this->unknownExecution();
        }

        if ($this->executions[$executionRef]['cancelled']) {
            return $this->error('ENGINE_REJECTED', 'A cancelled execution cannot accept input.', false);
        }

        $receiptRef = 'input_receipt_' . $executionRef . '_' . (count($this->executions[$executionRef]['inputs']) + 1);
        $this->executions[$executionRef]['inputs'][] = [
            'receipt_ref' => $receiptRef,
            'input' => $input,
            'identity_ref' => $identityRef,
            'correlation_id' => $correlationId,
        ];

        return ['receipt_ref' => $receiptRef];
    }

    public function cancel(
        string $executionRef,
        string $reason,
        string $identityRef,
        string $permissionRef,
        string $correlationId
    ): array {
        if ($permissionRef === 'permission:denied') {
            return $this->error('UNAUTHORIZED', 'The supplied permission reference was denied.', false);
        }

        if (!isset($this->executions[$executionRef])) {
            return $this->unknownExecution();
        }

        $this->executions[$executionRef]['cancelled'] = true;
        $this->executions[$executionRef]['status'] = 'CANCELLED';

        return [
            'receipt_ref' => 'cancel_receipt_' . $executionRef,
            'cancelled' => true,
            'reason' => $reason,
        ];
    }

    public function result(string $executionRef): array
    {
        $execution = $this->execution($executionRef);

        if (isset($execution['error'])) {
            return $execution;
        }

        if ($execution['cancelled']) {
            return [
                'execution_ref' => $executionRef,
                'state_ref' => $execution['state_ref'],
                'status' => 'CANCELLED',
                'result_set_refs' => [],
                'validation_record_ref' => null,
                'validation_decision' => null,
                'report_refs' => [],
                'limitations' => [],
                'next_action' => 'No further execution is permitted.',
            ];
        }

        return $execution['result'];
    }

    public function validation(string $executionRef): array
    {
        $execution = $this->execution($executionRef);

        return isset($execution['error']) ? $execution : $execution['validation_record'];
    }

    private function decisionFor(array $request): string
    {
        if ($this->validationDecision === null) {
            return 'ACCEPTED';
        }

        $decision = ($this->validationDecision)($request);
        $allowed = [
            'ACCEPTED',
            'ACCEPTED_WITH_LIMITATIONS',
            'REPAIR_REQUIRED',
            'CLARIFICATION_REQUIRED',
            'BLOCKED',
            'RECOVERY_REQUIRED',
            'REJECTED',
        ];

        return is_string($decision) && in_array($decision, $allowed, true)
            ? $decision
            : 'REJECTED';
    }

    private function execution(string $executionRef): array
    {
        return $this->executions[$executionRef] ?? $this->unknownExecution();
    }

    private function unknownExecution(): array
    {
        return $this->error('UNKNOWN_EXECUTION', 'The execution reference is unknown.', false);
    }

    private function error(string $type, string $message, bool $retryable): array
    {
        return [
            'error' => [
                'type' => $type,
                'message' => $message,
                'retryable' => $retryable,
            ],
        ];
    }
}
