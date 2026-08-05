<?php

declare(strict_types=1);

namespace SquirrelForge\Integration\Http;

use JsonException;
use SquirrelForge\Contracts\AuthorizationManagerInterface;
use SquirrelForge\Engine\TaskRouter;

/**
 * HTTP transport contract for agent-facing operations, per
 * 22_INTERFACES/AGENT-API.md.
 *
 * Only `assign` is real. The spec's other three operations each name a
 * component this codebase does not have: `status` and `cancel` both
 * require `14_ENGINE/STATE-MANAGER.md` to own assignment lifecycle
 * state, and `handoff` requires `17_COORDINATION/HANDOFF-PROTOCOL.md`
 * -- neither exists anywhere in this codebase (17_COORDINATION has zero
 * src/ implementation at all). Rather than invent a lifecycle-state
 * store here that this spec's own Permission Boundary explicitly
 * forbids this class from owning ("must not... own assignment lifecycle
 * state"), those three routes return a real, typed `NOT_IMPLEMENTED`
 * error naming the missing owning component, the same honest-gap
 * convention this codebase uses throughout rather than fabricating a
 * store this class has no authority to own.
 *
 * assign() is a genuine composition, not a re-derivation: it forwards
 * directly to the injected `TaskRouter::route()` and returns exactly
 * what routing decided (`ROUTED`/`BLOCKED`, the selected owner, and
 * rejected candidates) -- this class never selects an agent or performs
 * capability matching itself, upholding "does not select the agent or
 * task owner." `assignmentRequestId` is a fresh per-call correlation
 * identifier, not a durable assignment record: since no component here
 * owns assignment lifecycle state to persist one against, minting an ID
 * that `status()` could later resolve would silently make this class
 * the de facto state owner the spec forbids it from being.
 *
 * Every request requires identity, a permission reference, and a
 * correlation ID per the spec's own Request Requirements, verified via
 * the real injected `AuthorizationManagerInterface::authorize()` --
 * this class never decides authorization itself, only checks that the
 * caller's permission reference is one Authorization Manager actually
 * granted.
 */
final class AgentApiServer
{
    public function __construct(
        private readonly ?TaskRouter $taskRouter = null,
        private readonly ?AuthorizationManagerInterface $authorization = null
    ) {
    }

    /**
     * @param array<string, string> $headers
     */
    public function handle(string $method, string $path, array $headers, ?string $body): HttpTransportResponse
    {
        $method = strtoupper($method);
        $headers = $this->normalizeHeaders($headers);

        if ($path === '/v1/agents/assignments' && $method === 'POST') {
            return $this->assign($headers, $body);
        }

        if (preg_match('#^/v1/agents/assignments/([^/]+)(?:/(handoff|cancel))?$#', $path, $matches) === 1) {
            $assignmentRequestId = rawurldecode($matches[1]);
            $action = $matches[2] ?? null;

            return match ($action) {
                'handoff' => $this->notImplemented('handoff', '17_COORDINATION/HANDOFF-PROTOCOL.md'),
                'cancel' => $this->notImplemented('cancel', '14_ENGINE/STATE-MANAGER.md'),
                default => $method === 'GET'
                    ? $this->notImplemented('status', '14_ENGINE/STATE-MANAGER.md')
                    : $this->error(405, 'METHOD_NOT_ALLOWED', 'The method is not allowed for this Agent API route.'),
            };
        }

        return $this->error(404, 'UNKNOWN_ROUTE', 'The Agent API route is unknown.');
    }

    /**
     * @param array<string, string> $headers
     */
    private function assign(array $headers, ?string $body): HttpTransportResponse
    {
        $required = $this->requiredHeaders($headers);

        if ($required !== null) {
            return $required;
        }

        $payload = $this->decode($body);

        if ($payload instanceof HttpTransportResponse) {
            return $payload;
        }

        if (!isset($payload['task'], $payload['requirements'])
            || !is_array($payload['task']) || !is_array($payload['requirements'])
            || !isset($payload['task']['task_id'], $payload['requirements']['required_capability'])) {
            return $this->error(422, 'INVALID_ASSIGNMENT_REQUEST', 'task.task_id and requirements.required_capability are required.');
        }

        $authorization = $this->authorize($headers, 'agent.assign', $payload['task']['task_id']);

        if ($authorization instanceof HttpTransportResponse) {
            return $authorization;
        }

        if ($this->taskRouter === null) {
            return $this->withAuthorization($this->error(409, 'ASSIGNMENT_REJECTED', 'Task Router is not configured.'), $authorization);
        }

        $route = $this->taskRouter->route($payload['task'], $payload['requirements'], $payload['context'] ?? []);

        return $this->withAuthorization($this->json(200, [
            'result' => [
                'assignment_request_id' => 'assignment_request_' . bin2hex(random_bytes(12)),
                'task_id' => $route['task_id'],
                'status' => $route['status'],
                'owner' => $route['owner'],
                'rejected_candidates' => $route['rejected_candidates'],
                'rationale' => $route['rationale'],
            ],
            'error' => null,
            'correlation_id' => $headers['x-correlation-id'],
        ]), $authorization);
    }

    private function notImplemented(string $operation, string $owningSpec): HttpTransportResponse
    {
        return $this->error(
            501,
            'NOT_IMPLEMENTED',
            sprintf('The "%s" operation has no owning implementation yet; it belongs to %s.', $operation, $owningSpec)
        );
    }

    /**
     * @return array|HttpTransportResponse
     */
    private function decode(?string $body): array|HttpTransportResponse
    {
        if ($body === null || trim($body) === '') {
            return $this->error(400, 'INVALID_ASSIGNMENT_REQUEST', 'A JSON request body is required.');
        }

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->error(400, 'INVALID_ASSIGNMENT_REQUEST', 'The request body is not valid JSON.');
        }

        return is_array($decoded)
            ? $decoded
            : $this->error(400, 'INVALID_ASSIGNMENT_REQUEST', 'The request body must be an object-like JSON value.');
    }

    /**
     * @param array<string, string> $headers
     */
    private function requiredHeaders(array $headers): ?HttpTransportResponse
    {
        foreach (['x-squirrelforge-identity-ref', 'x-squirrelforge-permission-ref', 'x-correlation-id'] as $header) {
            if (!isset($headers[$header]) || trim($headers[$header]) === '') {
                $type = str_contains($header, 'identity') || str_contains($header, 'permission') ? 'UNAUTHORIZED' : 'INVALID_ASSIGNMENT_REQUEST';

                return $this->error($type === 'UNAUTHORIZED' ? 401 : 400, $type, sprintf('Required header %s is missing.', $header));
            }
        }

        return null;
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, mixed>|HttpTransportResponse
     */
    private function authorize(array $headers, string $operation, string $resourceRef): array|HttpTransportResponse
    {
        if ($this->authorization === null) {
            return $this->error(403, 'UNAUTHORIZED', 'Authorization Manager is not configured; rejecting by default.');
        }

        $decision = $this->authorization->authorize(
            $headers['x-squirrelforge-identity-ref'],
            $headers['x-squirrelforge-permission-ref'],
            $operation,
            $resourceRef,
            $headers['x-correlation-id']
        );

        if (($decision['authorized'] ?? false) !== true) {
            return $this->error(403, 'UNAUTHORIZED', (string) ($decision['rationale'] ?? 'Authorization was denied.'));
        }

        return $decision;
    }

    /**
     * @param array<string, mixed> $decision
     */
    private function withAuthorization(HttpTransportResponse $response, array $decision): HttpTransportResponse
    {
        $headers = $response->headers;
        $headers['x-squirrelforge-authorization-ref'] = (string) $decision['authorization_ref'];

        return new HttpTransportResponse($response->status, $headers, $response->body);
    }

    private function error(int $status, string $type, string $message): HttpTransportResponse
    {
        return $this->json($status, [
            'error' => ['type' => $type, 'message' => $message, 'retryable' => false],
            'result' => null,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function json(int $status, array $payload): HttpTransportResponse
    {
        return new HttpTransportResponse($status, ['content-type' => 'application/json'], json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $name => $value) {
            $normalized[strtolower($name)] = $value;
        }

        return $normalized;
    }
}
