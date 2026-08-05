<?php

declare(strict_types=1);

namespace SquirrelForge\Integration\Http;

use JsonException;
use SquirrelForge\Contracts\AuthorizationManagerInterface;
use SquirrelForge\Engine\WorkflowSelector;

/**
 * HTTP transport contract for workflow-facing operations, per
 * 22_INTERFACES/WORKFLOW-API.md.
 *
 * Only `select` is real. The spec's other four operations each need a
 * component this codebase does not have: `initialize` and `terminate`
 * both require `14_ENGINE/STATE-MANAGER.md` to own run state (this
 * class's own Permission Boundary explicitly forbids it from owning run
 * state itself), and `next`/`completePhase` require a genuinely
 * resumable, per-run execution model this codebase doesn't have either
 * -- `SquirrelForge\Workflow\WorkflowEngine::execute()` runs a
 * registered workflow synchronously to completion in one call, it does
 * not expose "give me the next action for an already-in-progress run"
 * the way this spec's `next()` requires. Rather than fabricate a
 * resumable-run store or a state owner this class has no authority to
 * be, those four routes return a real, typed `NOT_IMPLEMENTED` error
 * naming the missing owning component, the same honest-gap convention
 * this codebase uses throughout.
 *
 * select() is a genuine composition, not a re-derivation: it forwards
 * directly to the injected `WorkflowSelector::selectWorkflow()` and
 * returns exactly what selection decided -- this class never judges
 * workflow fit itself, upholding "does not select the workflow." Every
 * request requires identity, a permission reference, and a correlation
 * ID per the spec's own Request Requirements, verified via the real
 * injected `AuthorizationManagerInterface::authorize()`, the same
 * pattern `AgentApiServer` already establishes.
 */
final class WorkflowApiServer
{
    public function __construct(
        private readonly ?WorkflowSelector $workflowSelector = null,
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

        if ($path === '/v1/workflows/selection' && $method === 'POST') {
            return $this->select($headers, $body);
        }

        if ($path === '/v1/workflows/runs' && $method === 'POST') {
            return $this->notImplemented('initialize', '14_ENGINE/STATE-MANAGER.md');
        }

        if (preg_match('#^/v1/workflows/runs/([^/]+)/(next|phase-completion|terminate)$#', $path, $matches) === 1) {
            return match ($matches[2]) {
                'next' => $this->notImplemented('next', '20_EXECUTION/WORKFLOW-EXECUTOR.md'),
                'phase-completion' => $this->notImplemented('completePhase', '20_EXECUTION/WORKFLOW-EXECUTOR.md'),
                'terminate' => $this->notImplemented('terminate', '14_ENGINE/STATE-MANAGER.md'),
            };
        }

        return $this->error(404, 'UNKNOWN_ROUTE', 'The Workflow API route is unknown.');
    }

    /**
     * @param array<string, string> $headers
     */
    private function select(array $headers, ?string $body): HttpTransportResponse
    {
        $required = $this->requiredHeaders($headers);

        if ($required !== null) {
            return $required;
        }

        $payload = $this->decode($body);

        if ($payload instanceof HttpTransportResponse) {
            return $payload;
        }

        if (!isset($payload['goal'], $payload['candidates']) || !is_array($payload['goal']) || !is_array($payload['candidates'])) {
            return $this->error(422, 'INVALID_SELECTION_REQUEST', 'goal and candidates are required.');
        }

        $authorization = $this->authorize($headers, 'workflow.select', 'workflow_selection');

        if ($authorization instanceof HttpTransportResponse) {
            return $authorization;
        }

        if ($this->workflowSelector === null) {
            return $this->withAuthorization($this->error(409, 'SELECTION_REJECTED', 'Workflow Selector is not configured.'), $authorization);
        }

        $result = $this->workflowSelector->selectWorkflow($payload['goal'], $payload['candidates'], $payload['supporting_candidates'] ?? []);

        return $this->withAuthorization($this->json(200, [
            'result' => [
                'workflow_ref' => $result['primary_workflow'],
                'status' => $result['status'],
                'supporting_workflows' => $result['supporting_workflows'],
                'rejected_candidates' => $result['rejected_candidates'],
                'rationale' => $result['rationale'],
                'limitations' => $result['limitations'],
            ],
            'error' => $result['error'],
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
            return $this->error(400, 'INVALID_SELECTION_REQUEST', 'A JSON request body is required.');
        }

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->error(400, 'INVALID_SELECTION_REQUEST', 'The request body is not valid JSON.');
        }

        return is_array($decoded)
            ? $decoded
            : $this->error(400, 'INVALID_SELECTION_REQUEST', 'The request body must be an object-like JSON value.');
    }

    /**
     * @param array<string, string> $headers
     */
    private function requiredHeaders(array $headers): ?HttpTransportResponse
    {
        foreach (['x-squirrelforge-identity-ref', 'x-squirrelforge-permission-ref', 'x-correlation-id'] as $header) {
            if (!isset($headers[$header]) || trim($headers[$header]) === '') {
                $type = str_contains($header, 'identity') || str_contains($header, 'permission') ? 'UNAUTHORIZED' : 'INVALID_SELECTION_REQUEST';

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
