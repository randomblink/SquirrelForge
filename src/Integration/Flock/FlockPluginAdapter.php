<?php

declare(strict_types=1);

namespace SquirrelForge\Integration\Flock;

use InvalidArgumentException;
use SquirrelForge\Contracts\EngineClientInterface;
use Throwable;

final class FlockPluginAdapter
{
    public const VERSION = '1.0.0';

    /**
     * @var array<int, string>
     */
    private const OPERATIONS = ['submit', 'inspect', 'provide_input', 'cancel', 'result'];

    /**
     * @var array<string, string>
     */
    private const DECISION_STATUSES = [
        'ACCEPTED' => 'COMPLETE',
        'ACCEPTED_WITH_LIMITATIONS' => 'COMPLETE_WITH_LIMITATIONS',
        'REPAIR_REQUIRED' => 'REPAIRING',
        'CLARIFICATION_REQUIRED' => 'WAITING_FOR_INPUT',
        'BLOCKED' => 'BLOCKED',
        'RECOVERY_REQUIRED' => 'RECOVERY_REQUIRED',
        'REJECTED' => 'REJECTED',
    ];

    public function __construct(private readonly EngineClientInterface $engine)
    {
    }

    public function handle(array $request): array
    {
        try {
            $this->validateEnvelope($request);

            return match ($request['operation']) {
                'submit' => $this->submit($request),
                'inspect' => $this->inspect($request),
                'provide_input' => $this->provideInput($request),
                'cancel' => $this->cancel($request),
                'result' => $this->result($request),
            };
        } catch (InvalidArgumentException $exception) {
            return $this->errorResponse($request, 'INVALID_FLOCK_REQUEST', $exception->getMessage(), false);
        } catch (EngineTransportException $exception) {
            return $this->errorResponse(
                $request,
                'TRANSPORT_UNAVAILABLE',
                'The Engine transport is unavailable.',
                true
            );
        } catch (Throwable $exception) {
            return $this->errorResponse(
                $request,
                'INTERNAL_ADAPTER_ERROR',
                'The Flock adapter could not complete the Engine handoff.',
                false
            );
        }
    }

    private function submit(array $request): array
    {
        $this->requireFields($request, ['project_ref', 'idempotency_key']);

        $result = $this->engine->submit(
            $this->payload($request),
            $request['project_ref'],
            $request['actor']['identity_ref'],
            $request['permission_ref'],
            $request['idempotency_key'],
            $request['correlation_id']
        );

        if (isset($result['error'])) {
            return $this->engineErrorResponse($request, $result);
        }

        return $this->response($request, [
            'execution_ref' => $this->requiredString($result, 'execution_ref'),
            'status' => 'ACCEPTED',
            'message' => 'The Engine accepted the request.',
            'authentication_ref' => $result['authentication_ref'] ?? null,
            'authorization_ref' => $result['authorization_ref'] ?? null,
        ]);
    }

    private function inspect(array $request): array
    {
        $executionRef = $this->executionRef($request);
        $result = $this->engine->inspect(
            $executionRef,
            $request['actor']['identity_ref'],
            $request['permission_ref'],
            $request['correlation_id']
        );

        if (isset($result['error'])) {
            return $this->engineErrorResponse($request, $result);
        }

        $decision = $result['validation_decision'] ?? null;

        return $this->response($request, [
            'execution_ref' => $executionRef,
            'status' => $this->mapStatus($result['status'] ?? null, $decision),
            'message' => (string) ($result['message'] ?? 'Engine state retrieved.'),
            'state_ref' => $result['state_ref'] ?? null,
            'validation_record_ref' => $result['validation_record_ref'] ?? null,
            'validation_decision' => $decision,
            'limitations' => $result['limitations'] ?? [],
            'next_action' => $result['next_action'] ?? null,
            'authentication_ref' => $result['authentication_ref'] ?? null,
            'authorization_ref' => $result['authorization_ref'] ?? null,
        ]);
    }

    private function provideInput(array $request): array
    {
        $executionRef = $this->executionRef($request);
        $payload = $this->payload($request);

        if ($payload === []) {
            throw new InvalidArgumentException('provide_input requires a non-empty payload.');
        }

        $result = $this->engine->provideInput(
            $executionRef,
            $payload,
            $request['actor']['identity_ref'],
            $request['permission_ref'],
            $request['correlation_id']
        );

        if (isset($result['error'])) {
            return $this->engineErrorResponse($request, $result);
        }

        return $this->response($request, [
            'execution_ref' => $executionRef,
            'status' => 'ACCEPTED',
            'message' => 'External input was accepted for Engine processing.',
            'result_refs' => isset($result['receipt_ref']) ? [$result['receipt_ref']] : [],
            'authentication_ref' => $result['authentication_ref'] ?? null,
            'authorization_ref' => $result['authorization_ref'] ?? null,
        ]);
    }

    private function cancel(array $request): array
    {
        $executionRef = $this->executionRef($request);
        $reason = $this->payload($request)['reason'] ?? null;

        if (!is_string($reason) || trim($reason) === '') {
            throw new InvalidArgumentException('cancel requires a non-empty payload.reason.');
        }

        $result = $this->engine->cancel(
            $executionRef,
            $reason,
            $request['actor']['identity_ref'],
            $request['permission_ref'],
            $request['correlation_id']
        );

        if (isset($result['error'])) {
            return $this->engineErrorResponse($request, $result);
        }

        return $this->response($request, [
            'execution_ref' => $executionRef,
            'status' => ($result['cancelled'] ?? false) === true ? 'CANCELLED' : 'ACCEPTED',
            'message' => ($result['cancelled'] ?? false) === true
                ? 'The Engine confirmed cancellation.'
                : 'The cancellation request was received but is not yet confirmed.',
            'result_refs' => isset($result['receipt_ref']) ? [$result['receipt_ref']] : [],
            'authentication_ref' => $result['authentication_ref'] ?? null,
            'authorization_ref' => $result['authorization_ref'] ?? null,
        ]);
    }

    private function result(array $request): array
    {
        $executionRef = $this->executionRef($request);
        $result = $this->engine->result(
            $executionRef,
            $request['actor']['identity_ref'],
            $request['permission_ref'],
            $request['correlation_id']
        );

        if (isset($result['error'])) {
            return $this->engineErrorResponse($request, $result);
        }

        $decision = $result['validation_decision'] ?? null;
        $status = $this->mapStatus($result['status'] ?? null, $decision);

        if (in_array($status, ['COMPLETE', 'COMPLETE_WITH_LIMITATIONS'], true) && $decision === null) {
            return $this->errorResponse(
                $request,
                'RESPONSE_SCHEMA_INVALID',
                'A successful terminal result requires an authoritative validation decision.',
                false,
                $executionRef
            );
        }

        return $this->response($request, [
            'execution_ref' => $executionRef,
            'status' => $status,
            'message' => (string) ($result['message'] ?? 'Engine result retrieved.'),
            'state_ref' => $result['state_ref'] ?? null,
            'result_refs' => $result['result_set_refs'] ?? [],
            'validation_record_ref' => $result['validation_record_ref'] ?? null,
            'validation_decision' => $decision,
            'report_refs' => $result['report_refs'] ?? [],
            'limitations' => $result['limitations'] ?? [],
            'next_action' => $result['next_action'] ?? null,
            'authentication_ref' => $result['authentication_ref'] ?? null,
            'authorization_ref' => $result['authorization_ref'] ?? null,
        ]);
    }

    private function validateEnvelope(array $request): void
    {
        $this->requireFields($request, ['adapter_version', 'operation', 'request_id', 'permission_ref', 'correlation_id']);

        if ($request['adapter_version'] !== self::VERSION) {
            throw new InvalidArgumentException('Unsupported Flock adapter version.');
        }

        if (!in_array($request['operation'], self::OPERATIONS, true)) {
            throw new InvalidArgumentException('Unsupported Flock operation.');
        }

        if (!isset($request['actor']) || !is_array($request['actor'])) {
            throw new InvalidArgumentException('actor must be an object-like array.');
        }

        $this->requireFields($request['actor'], ['identity_ref']);

        if (in_array($request['operation'], ['submit', 'provide_input', 'cancel'], true)) {
            $this->requireFields($request, ['idempotency_key']);
        }
    }

    private function requireFields(array $values, array $fields): void
    {
        foreach ($fields as $field) {
            if (!array_key_exists($field, $values) || !is_string($values[$field]) || trim($values[$field]) === '') {
                throw new InvalidArgumentException(sprintf('%s is required and must be a non-empty string.', $field));
            }
        }
    }

    private function executionRef(array $request): string
    {
        $this->requireFields($request, ['execution_ref']);

        return $request['execution_ref'];
    }

    private function payload(array $request): array
    {
        $payload = $request['payload'] ?? [];

        if (!is_array($payload)) {
            throw new InvalidArgumentException('payload must be an object-like array.');
        }

        return $payload;
    }

    private function requiredString(array $values, string $field): string
    {
        $this->requireFields($values, [$field]);

        return $values[$field];
    }

    private function mapStatus(mixed $engineStatus, mixed $decision): string
    {
        if (is_string($decision) && isset(self::DECISION_STATUSES[$decision])) {
            return self::DECISION_STATUSES[$decision];
        }

        return match ($engineStatus) {
            'REQUESTED', 'PENDING' => 'ACCEPTED',
            'RUNNING', 'VALIDATION', 'VALIDATION_PENDING' => 'RUNNING',
            'WAITING', 'CLARIFICATION_REQUIRED' => 'WAITING_FOR_INPUT',
            'BLOCKED' => 'BLOCKED',
            'RECOVERY_REQUIRED' => 'RECOVERY_REQUIRED',
            'FAILED' => 'FAILED',
            'CANCELLED' => 'CANCELLED',
            default => 'RUNNING',
        };
    }

    private function engineErrorResponse(array $request, array $result): array
    {
        $error = $result['error'];

        if (!is_array($error)) {
            return $this->errorResponse($request, 'RESPONSE_SCHEMA_INVALID', 'Engine error payload is invalid.', false);
        }

        return $this->errorResponse(
            $request,
            (string) ($error['type'] ?? 'ENGINE_REJECTED'),
            (string) ($error['message'] ?? 'The Engine rejected the request.'),
            ($error['retryable'] ?? false) === true,
            isset($request['execution_ref']) && is_string($request['execution_ref'])
                ? $request['execution_ref']
                : null,
            is_string($result['authorization_ref'] ?? null)
                ? $result['authorization_ref']
                : (is_string($error['authorization_ref'] ?? null) ? $error['authorization_ref'] : null),
            is_string($result['authentication_ref'] ?? null)
                ? $result['authentication_ref']
                : (is_string($error['authentication_ref'] ?? null) ? $error['authentication_ref'] : null)
        );
    }

    private function errorResponse(
        array $request,
        string $type,
        string $message,
        bool $retryable,
        ?string $executionRef = null,
        ?string $authorizationRef = null,
        ?string $authenticationRef = null
    ): array {
        return $this->response($request, [
            'execution_ref' => $executionRef,
            'status' => in_array($type, ['UNAUTHENTICATED', 'UNAUTHORIZED'], true) ? 'REJECTED' : 'FAILED',
            'message' => $message,
            'authentication_ref' => $authenticationRef,
            'authorization_ref' => $authorizationRef,
            'error' => [
                'type' => $type,
                'message' => $message,
                'retryable' => $retryable,
                'authentication_ref' => $authenticationRef,
                'authorization_ref' => $authorizationRef,
            ],
        ]);
    }

    private function response(array $request, array $overrides): array
    {
        return array_replace([
            'adapter_version' => self::VERSION,
            'request_id' => is_string($request['request_id'] ?? null) ? $request['request_id'] : null,
            'correlation_id' => is_string($request['correlation_id'] ?? null) ? $request['correlation_id'] : null,
            'trace_id' => is_string($request['trace_id'] ?? null) ? $request['trace_id'] : null,
            'execution_ref' => null,
            'status' => 'FAILED',
            'message' => '',
            'state_ref' => null,
            'authentication_ref' => null,
            'authorization_ref' => null,
            'result_refs' => [],
            'validation_record_ref' => null,
            'validation_decision' => null,
            'report_refs' => [],
            'limitations' => [],
            'next_action' => null,
            'error' => null,
        ], $overrides);
    }
}
