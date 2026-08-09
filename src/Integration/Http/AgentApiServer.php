<?php

declare(strict_types=1);

namespace SquirrelForge\Integration\Http;

use JsonException;
use SquirrelForge\Contracts\AuthorizationManagerInterface;
use SquirrelForge\Coordination\SqliteHandoffProtocol;
use SquirrelForge\Engine\SqliteStateManager;
use SquirrelForge\Engine\TaskRouter;

/**
 * HTTP transport contract for agent-facing operations, per
 * 22_INTERFACES/AGENT-API.md.
 *
 * `status`, `cancel`, and `handoff` compose the two components a
 * second, independent audit pass found this codebase had built but
 * never wired in here: `14_ENGINE/STATE-MANAGER.md` (as
 * `SqliteStateManager`, commit `4d497c8`) and
 * `17_COORDINATION/HANDOFF-PROTOCOL.md` (as `SqliteHandoffProtocol`,
 * commit `b30fdf2`) -- both landed after this class's own `NOT_IMPLEMENTED`
 * responses were written, and neither had been connected since. When
 * either dependency is omitted, the route still returns the same real,
 * typed `NOT_IMPLEMENTED` error this class always has -- composing
 * them is additive, never a required upgrade for existing callers.
 *
 * `assign()` now genuinely initializes a state record for a successful
 * route (`SqliteStateManager::initialize()` + `assignOwner()` +
 * `recordTaskState('ROUTED')`), keyed by the same
 * `assignment_request_id` the caller already receives -- this is what
 * gives `status()` something real to answer from. A `BLOCKED` route
 * never initializes state, since nothing was actually assigned.
 *
 * `status()` is genuinely read-only, per "never mutates state": it
 * only calls `SqliteStateManager::currentState()`, never a mutating
 * method.
 *
 * `cancel()` and `handoff()` both stay inside this spec's own explicit
 * boundary that a request "acknowledg[es]... not that cancellation
 * occurred" / does not "execute handoff mechanics" itself: `cancel()`
 * records a real blocker via `SqliteStateManager::recordBlocker()`
 * rather than ever transitioning task state to a cancelled/terminal
 * one, and `handoff()` hands the request to the real
 * `SqliteHandoffProtocol::initiate()` and returns exactly what that
 * component decided, never deciding acceptance itself.
 */
final class AgentApiServer
{
    public function __construct(
        private readonly ?TaskRouter $taskRouter = null,
        private readonly ?AuthorizationManagerInterface $authorization = null,
        private readonly ?SqliteStateManager $stateManager = null,
        private readonly ?SqliteHandoffProtocol $handoffProtocol = null
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

            if ($action === 'handoff' && $method === 'POST') {
                return $this->handoff($assignmentRequestId, $headers, $body);
            }

            if ($action === 'cancel' && $method === 'POST') {
                return $this->cancel($assignmentRequestId, $headers, $body);
            }

            if ($action === null && $method === 'GET') {
                return $this->status($assignmentRequestId, $headers);
            }

            return $this->error(405, 'METHOD_NOT_ALLOWED', 'The method is not allowed for this Agent API route.');
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
        $assignmentRequestId = 'assignment_request_' . bin2hex(random_bytes(12));
        $taskId = $payload['task']['task_id'];

        if ($route['status'] === 'ROUTED' && $route['owner'] !== null && $this->stateManager !== null) {
            $this->stateManager->initialize($assignmentRequestId, $taskId);
            $this->stateManager->assignOwner($assignmentRequestId, $taskId, $route['owner']);
            $this->stateManager->recordTaskState($assignmentRequestId, $taskId, 'ROUTED');
        }

        return $this->withAuthorization($this->json(200, [
            'result' => [
                'assignment_request_id' => $assignmentRequestId,
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

    /**
     * Read-only: "Returns the current assignment state as tracked by
     * TASK-ROUTER.md's routing states and STATE-MANAGER.md... never
     * mutates state."
     *
     * @param array<string, string> $headers
     */
    private function status(string $assignmentRequestId, array $headers): HttpTransportResponse
    {
        $required = $this->requiredHeaders($headers);

        if ($required !== null) {
            return $required;
        }

        $authorization = $this->authorize($headers, 'agent.status', $assignmentRequestId);

        if ($authorization instanceof HttpTransportResponse) {
            return $authorization;
        }

        if ($this->stateManager === null) {
            return $this->notImplemented('status', '14_ENGINE/STATE-MANAGER.md');
        }

        $state = $this->stateManager->currentState($assignmentRequestId);

        if ($state === null) {
            return $this->withAuthorization($this->error(404, 'UNKNOWN_ASSIGNMENT', sprintf('"%s" is not a known assignment request.', $assignmentRequestId)), $authorization);
        }

        return $this->withAuthorization($this->json(200, [
            'result' => [
                'assignment_request_id' => $assignmentRequestId,
                'lifecycle_phase' => $state['lifecycle_phase'],
                'tasks' => $state['tasks'],
                'blocker_reason' => $state['blocker_reason'],
                'next_safe_action' => $state['next_safe_action'],
            ],
            'error' => null,
            'correlation_id' => $headers['x-correlation-id'],
        ]), $authorization);
    }

    /**
     * "Does not decide whether cancellation is permitted or perform
     * it... returns a result acknowledging the request was received
     * and correlated, not that cancellation occurred." Records a real
     * blocker, never a cancelled/terminal state transition.
     *
     * @param array<string, string> $headers
     */
    private function cancel(string $assignmentRequestId, array $headers, ?string $body): HttpTransportResponse
    {
        $required = $this->requiredHeaders($headers);

        if ($required !== null) {
            return $required;
        }

        $authorization = $this->authorize($headers, 'agent.cancel', $assignmentRequestId);

        if ($authorization instanceof HttpTransportResponse) {
            return $authorization;
        }

        if ($this->stateManager === null) {
            return $this->notImplemented('cancel', '14_ENGINE/STATE-MANAGER.md');
        }

        if ($this->stateManager->currentState($assignmentRequestId) === null) {
            return $this->withAuthorization($this->error(404, 'UNKNOWN_ASSIGNMENT', sprintf('"%s" is not a known assignment request.', $assignmentRequestId)), $authorization);
        }

        $payload = $this->decode($body);
        $reason = (!($payload instanceof HttpTransportResponse) && is_string($payload['reason'] ?? null))
            ? $payload['reason']
            : 'Cancellation requested via Agent API.';

        $this->stateManager->recordBlocker($assignmentRequestId, $reason, 'Awaiting cancellation disposition from Task Router lifecycle or governance control.');

        return $this->withAuthorization($this->json(200, [
            'result' => [
                'assignment_request_id' => $assignmentRequestId,
                'acknowledged' => true,
                'cancelled' => false,
            ],
            'error' => null,
            'correlation_id' => $headers['x-correlation-id'],
        ]), $authorization);
    }

    /**
     * "Does not execute handoff mechanics -- the request is routed to
     * 17_COORDINATION/HANDOFF-PROTOCOL.md."
     *
     * @param array<string, string> $headers
     */
    private function handoff(string $assignmentRequestId, array $headers, ?string $body): HttpTransportResponse
    {
        $required = $this->requiredHeaders($headers);

        if ($required !== null) {
            return $required;
        }

        $payload = $this->decode($body);

        if ($payload instanceof HttpTransportResponse) {
            return $payload;
        }

        if (!isset($payload['target']) || !is_string($payload['target']) || $payload['target'] === '') {
            return $this->error(422, 'INVALID_HANDOFF_REQUEST', 'target is required.');
        }

        $authorization = $this->authorize($headers, 'agent.handoff', $assignmentRequestId);

        if ($authorization instanceof HttpTransportResponse) {
            return $authorization;
        }

        if ($this->stateManager === null || $this->handoffProtocol === null) {
            return $this->notImplemented('handoff', '17_COORDINATION/HANDOFF-PROTOCOL.md');
        }

        $state = $this->stateManager->currentState($assignmentRequestId);

        if ($state === null || $state['tasks'] === []) {
            return $this->withAuthorization($this->error(404, 'UNKNOWN_ASSIGNMENT', sprintf('"%s" is not a known assignment request.', $assignmentRequestId)), $authorization);
        }

        $task = $state['tasks'][0];
        $package = is_array($payload['package'] ?? null) ? $payload['package'] : [];

        $handoffResult = $this->handoffProtocol->initiate([
            'task_id' => $task['task_id'],
            'current_agent' => $task['owner'],
            'next_agent' => $payload['target'],
            'artifacts' => $package['artifacts'] ?? [],
            'notes' => $package['notes'] ?? null,
        ]);

        return $this->withAuthorization($this->json(200, [
            'result' => [
                'assignment_request_id' => $assignmentRequestId,
                'handoff_id' => $handoffResult['handoff_id'],
                'status' => $handoffResult['outcome'],
            ],
            'error' => $handoffResult['error'],
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
