<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Support;

use SquirrelForge\Contracts\EngineClientInterface;
use SquirrelForge\Integration\Flock\EngineTransportException;

final class FakeEngineClient implements EngineClientInterface
{
    /**
     * @var array<string, string>
     */
    private array $idempotency = [];

    /**
     * @var array<string, array>
     */
    private array $executions = [];

    public int $submitCalls = 0;

    public bool $transportUnavailable = false;

    public function __construct(private readonly string $decision = 'ACCEPTED')
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
        $this->submitCalls++;

        if ($this->transportUnavailable) {
            throw new EngineTransportException('Transport unavailable.');
        }

        if ($permissionRef === 'permission:denied') {
            return [
                'error' => [
                    'type' => 'UNAUTHORIZED',
                    'message' => 'The supplied permission was denied.',
                    'retryable' => false,
                ],
            ];
        }

        if (isset($this->idempotency[$idempotencyKey])) {
            return ['execution_ref' => $this->idempotency[$idempotencyKey]];
        }

        $executionRef = 'exec_' . (count($this->executions) + 1);
        $this->idempotency[$idempotencyKey] = $executionRef;
        $this->executions[$executionRef] = [
            'status' => 'RUNNING',
            'state_ref' => 'state_' . $executionRef,
            'request' => $request,
            'project_ref' => $projectRef,
            'identity_ref' => $identityRef,
            'correlation_id' => $correlationId,
            'cancelled' => false,
        ];

        return ['execution_ref' => $executionRef];
    }

    public function inspect(
        string $executionRef,
        string $identityRef,
        string $permissionRef,
        string $correlationId
    ): array
    {
        if (!isset($this->executions[$executionRef])) {
            return $this->unknownExecution();
        }

        $execution = $this->executions[$executionRef];

        return [
            'status' => $execution['cancelled'] ? 'CANCELLED' : $execution['status'],
            'state_ref' => $execution['state_ref'],
        ];
    }

    public function provideInput(
        string $executionRef,
        array $input,
        string $identityRef,
        string $permissionRef,
        string $correlationId
    ): array {
        if (!isset($this->executions[$executionRef])) {
            return $this->unknownExecution();
        }

        return ['receipt_ref' => 'input_receipt_' . $executionRef];
    }

    public function cancel(
        string $executionRef,
        string $reason,
        string $identityRef,
        string $permissionRef,
        string $correlationId
    ): array {
        if (!isset($this->executions[$executionRef])) {
            return $this->unknownExecution();
        }

        $this->executions[$executionRef]['cancelled'] = true;

        return ['receipt_ref' => 'cancel_receipt_' . $executionRef, 'cancelled' => true];
    }

    public function result(
        string $executionRef,
        string $identityRef,
        string $permissionRef,
        string $correlationId
    ): array
    {
        if (!isset($this->executions[$executionRef])) {
            return $this->unknownExecution();
        }

        if ($this->executions[$executionRef]['cancelled']) {
            return [
                'status' => 'CANCELLED',
                'state_ref' => $this->executions[$executionRef]['state_ref'],
            ];
        }

        $limitations = $this->decision === 'ACCEPTED_WITH_LIMITATIONS'
            ? ['Optional host callback transport is unavailable.']
            : [];

        return [
            'status' => 'COMPLETE',
            'state_ref' => $this->executions[$executionRef]['state_ref'],
            'result_set_refs' => ['result_set_' . $executionRef],
            'validation_record_ref' => 'validation_' . $executionRef,
            'validation_decision' => $this->decision,
            'report_refs' => ['report_' . $executionRef],
            'limitations' => $limitations,
            'next_action' => $this->decision === 'REJECTED'
                ? 'Correct the rejected result before resubmission.'
                : 'Present the validated result.',
        ];
    }

    private function unknownExecution(): array
    {
        return [
            'error' => [
                'type' => 'UNKNOWN_EXECUTION',
                'message' => 'The execution reference is unknown.',
                'retryable' => false,
            ],
        ];
    }
}
