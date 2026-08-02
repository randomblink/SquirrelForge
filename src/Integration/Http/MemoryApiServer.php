<?php

declare(strict_types=1);

namespace SquirrelForge\Integration\Http;

use JsonException;
use SquirrelForge\Contracts\AuthenticationManagerInterface;
use SquirrelForge\Contracts\AuthorizationManagerInterface;
use SquirrelForge\Memory\MemoryManager;
use SquirrelForge\Memory\MemoryRetrieval;
use SquirrelForge\Memory\SqliteMemoryRetention;
use SquirrelForge\Security\StaticAuthorizationManager;
use SquirrelForge\Security\StaticHeaderAuthenticationManager;

/**
 * HTTP transport contract for memory-facing operations, per
 * 22_INTERFACES/MEMORY-API.md, following the same real transport shape
 * already established by EngineApiServer and DashboardApiServer:
 * identity/permission/correlation headers, AuthenticationManagerInterface
 * + AuthorizationManagerInterface composed for real auth decisions, and
 * a typed JSON error envelope carried in HttpTransportResponse.
 *
 * This class never stores, retrieves, or decides anything itself -- every
 * operation forwards to the real owning component (MemoryManager for
 * put, MemoryRetrieval for get/search, SqliteMemoryRetention for
 * archive) and returns its real outcome, upholding "it does not itself
 * route or store records, retrieve or search records, decide promotion
 * or archival, or authorize requests."
 *
 * `memoryRef` is a composite `{memory_type}:{record_id}` reference: the
 * spec names the shape as an opaque reference but does not define its
 * concrete format, and a memory type is required to route get()/
 * archive() to the correct owning component the same way put() already
 * requires one via `classification.memory_type`.
 *
 * put() only supports working/episodic/semantic records via
 * STORE_OPERATION_BY_TYPE -- Project Memory is intentionally out of
 * scope for this generic store operation, since its real shape
 * (initialize, then reference decisions/milestones/risks/dependencies,
 * notes, conventions, architecture) is a set of distinct operations,
 * not a single record write MemoryManager's store_* operations model.
 *
 * promote() never consults the Knowledge Manager: per the spec's own
 * Permission Boundary, "it must not... decide promotion," so this
 * always returns a bare acknowledgment that the candidate and its
 * evidence were received and correlated, never a promotion decision.
 *
 * archive() folds `reason` into the evidence bag passed to
 * SqliteMemoryRetention::evaluate() and returns whatever real decision
 * Retention makes -- including a non-archival outcome like "retain,"
 * "recommended," or "blocked" -- rather than assuming the request
 * itself guarantees archival.
 */
final class MemoryApiServer
{
    private const STORE_OPERATION_BY_TYPE = [
        'working' => 'store_working',
        'episodic' => 'store_episodic',
        'semantic' => 'store_semantic',
    ];

    private const STORE_SUCCESS_OUTCOMES = [
        'working' => ['initialized', 'refreshed'],
        'episodic' => ['recorded'],
        'semantic' => ['stored'],
    ];

    private const MEMORY_TYPES = ['working', 'episodic', 'semantic', 'project'];

    private readonly AuthorizationManagerInterface $authorization;
    private readonly AuthenticationManagerInterface $authentication;

    public function __construct(
        private readonly ?MemoryManager $memoryManager = null,
        private readonly ?MemoryRetrieval $memoryRetrieval = null,
        private readonly ?SqliteMemoryRetention $memoryRetention = null,
        ?AuthorizationManagerInterface $authorization = null,
        ?AuthenticationManagerInterface $authentication = null
    ) {
        $this->authorization = $authorization ?? new StaticAuthorizationManager();
        $this->authentication = $authentication ?? new StaticHeaderAuthenticationManager();
    }

    /**
     * @param array<string, string> $headers
     */
    public function handle(string $method, string $path, array $headers, ?string $body): HttpTransportResponse
    {
        $method = strtoupper($method);
        $headers = $this->normalizeHeaders($headers);

        if ($path === '/v1/memory/records' && $method === 'POST') {
            return $this->put($headers, $body);
        }

        if ($path === '/v1/memory/search' && $method === 'POST') {
            return $this->search($headers, $body);
        }

        if (preg_match('#^/v1/memory/records/([^/]+)(?:/(promotion|archival))?$#', $path, $matches) === 1) {
            $memoryRef = rawurldecode($matches[1]);
            $action = $matches[2] ?? null;

            if ($action === null) {
                return $method === 'GET'
                    ? $this->get($headers, $memoryRef)
                    : $this->error(405, 'METHOD_NOT_ALLOWED', 'The method is not allowed for this Memory API route.');
            }

            if ($method !== 'POST') {
                return $this->error(405, 'METHOD_NOT_ALLOWED', 'The method is not allowed for this Memory API route.');
            }

            return $action === 'promotion'
                ? $this->promote($headers, $memoryRef, $body)
                : $this->archive($headers, $memoryRef, $body);
        }

        return $this->error(404, 'UNKNOWN_ROUTE', 'The Memory API route is unknown.');
    }

    /**
     * @param array<string, string> $headers
     */
    private function put(array $headers, ?string $body): HttpTransportResponse
    {
        $required = $this->requiredHeaders(
            $headers,
            ['x-squirrelforge-identity-ref', 'x-squirrelforge-permission-ref', 'x-correlation-id']
        );

        if ($required !== null) {
            return $required;
        }

        $payload = $this->decode($body);

        if ($payload instanceof HttpTransportResponse) {
            return $payload;
        }

        if (!isset($payload['record'], $payload['classification'], $payload['provenance'])
            || !is_array($payload['record']) || $payload['record'] === []
            || !is_array($payload['classification'])
            || !is_array($payload['provenance'])) {
            return $this->error(422, 'INVALID_MEMORY_REQUEST', 'record, classification, and provenance are required.');
        }

        $memoryType = $payload['classification']['memory_type'] ?? null;

        if (!is_string($memoryType) || !isset(self::STORE_OPERATION_BY_TYPE[$memoryType])) {
            return $this->error(
                422,
                'INVALID_MEMORY_REQUEST',
                'classification.memory_type must be one of: working, episodic, semantic (project records are not supported via this generic store operation).'
            );
        }

        $authorization = $this->authorize($headers, 'memory.put', 'memory:' . $memoryType);

        if ($authorization instanceof HttpTransportResponse) {
            return $authorization;
        }

        if ($this->memoryManager === null) {
            return $this->withAuthorization($this->error(409, 'MEMORY_REJECTED', 'Memory Manager is not configured.'), $authorization);
        }

        $managerResult = $this->memoryManager->coordinate(
            self::STORE_OPERATION_BY_TYPE[$memoryType],
            [...$payload['record'], 'requesting_component' => $headers['x-squirrelforge-identity-ref']]
        );

        if (!in_array($managerResult['outcome'], self::STORE_SUCCESS_OUTCOMES[$memoryType], true)) {
            return $this->withAuthorization(
                $this->error(422, 'INVALID_MEMORY_REQUEST', $managerResult['error'] ?? 'The store request was rejected.'),
                $authorization
            );
        }

        $recordId = match ($memoryType) {
            'working' => $payload['record']['task_id'] ?? null,
            'episodic' => $managerResult['result']['memory_id'] ?? null,
            'semantic' => $managerResult['result']['record_id'] ?? null,
        };

        return $this->withAuthorization($this->json(201, [
            'result' => ['memory_ref' => $memoryType . ':' . $recordId, 'record' => $managerResult['result']],
            'error' => null,
            'correlation_id' => $headers['x-correlation-id'],
        ]), $authorization);
    }

    /**
     * @param array<string, string> $headers
     */
    private function get(array $headers, string $memoryRef): HttpTransportResponse
    {
        $required = $this->requiredHeaders(
            $headers,
            ['x-squirrelforge-identity-ref', 'x-squirrelforge-permission-ref', 'x-correlation-id']
        );

        if ($required !== null) {
            return $required;
        }

        $parsed = $this->parseMemoryRef($memoryRef);

        if ($parsed === null) {
            return $this->error(422, 'INVALID_MEMORY_REQUEST', 'The memory reference must be in the form "{memory_type}:{record_id}".');
        }

        [$memoryType, $recordId] = $parsed;
        $authorization = $this->authorize($headers, 'memory.get', 'memory:' . $memoryRef);

        if ($authorization instanceof HttpTransportResponse) {
            return $authorization;
        }

        if ($this->memoryRetrieval === null) {
            return $this->withAuthorization($this->error(409, 'MEMORY_REJECTED', 'Memory Retrieval is not configured.'), $authorization);
        }

        $record = $this->memoryRetrieval->getByReference($memoryType, $recordId);

        if ($record === null) {
            return $this->withAuthorization(
                $this->error(404, 'UNKNOWN_MEMORY_REFERENCE', sprintf('No record found for reference "%s".', $memoryRef)),
                $authorization
            );
        }

        return $this->withAuthorization($this->json(200, [
            'result' => ['memory_ref' => $memoryRef, 'record' => $record],
            'error' => null,
            'correlation_id' => $headers['x-correlation-id'],
        ]), $authorization);
    }

    /**
     * @param array<string, string> $headers
     */
    private function search(array $headers, ?string $body): HttpTransportResponse
    {
        $required = $this->requiredHeaders(
            $headers,
            ['x-squirrelforge-identity-ref', 'x-squirrelforge-permission-ref', 'x-correlation-id']
        );

        if ($required !== null) {
            return $required;
        }

        $payload = $this->decode($body);

        if ($payload instanceof HttpTransportResponse) {
            return $payload;
        }

        if (!isset($payload['query_terms']) || !is_array($payload['query_terms']) || $payload['query_terms'] === []) {
            return $this->error(422, 'INVALID_MEMORY_REQUEST', 'query_terms must be a non-empty array.');
        }

        $scope = is_array($payload['scope'] ?? null) ? $payload['scope'] : [];
        $authorization = $this->authorize($headers, 'memory.search', 'memory:search');

        if ($authorization instanceof HttpTransportResponse) {
            return $authorization;
        }

        if ($this->memoryRetrieval === null) {
            return $this->withAuthorization($this->error(409, 'MEMORY_REJECTED', 'Memory Retrieval is not configured.'), $authorization);
        }

        $result = $this->memoryRetrieval->search($payload['query_terms'], $scope);

        if ($result['outcome'] !== 'searched') {
            return $this->withAuthorization(
                $this->error(422, 'INVALID_MEMORY_REQUEST', $result['error'] ?? 'The search request was rejected.'),
                $authorization
            );
        }

        return $this->withAuthorization($this->json(200, [
            'result' => ['matches' => $result['results']],
            'error' => null,
            'correlation_id' => $headers['x-correlation-id'],
        ]), $authorization);
    }

    /**
     * @param array<string, string> $headers
     */
    private function promote(array $headers, string $memoryRef, ?string $body): HttpTransportResponse
    {
        $required = $this->requiredHeaders(
            $headers,
            ['x-squirrelforge-identity-ref', 'x-squirrelforge-permission-ref', 'x-correlation-id']
        );

        if ($required !== null) {
            return $required;
        }

        if ($this->parseMemoryRef($memoryRef) === null) {
            return $this->error(422, 'INVALID_MEMORY_REQUEST', 'The memory reference must be in the form "{memory_type}:{record_id}".');
        }

        $payload = ($body === null || trim($body) === '') ? [] : $this->decode($body);

        if ($payload instanceof HttpTransportResponse) {
            return $payload;
        }

        $evidence = is_array($payload['evidence'] ?? null) ? $payload['evidence'] : [];
        $authorization = $this->authorize($headers, 'memory.promote', 'memory:' . $memoryRef);

        if ($authorization instanceof HttpTransportResponse) {
            return $authorization;
        }

        return $this->withAuthorization($this->json(202, [
            'result' => ['memory_ref' => $memoryRef, 'status' => 'candidate_received', 'evidence_received' => $evidence !== []],
            'error' => null,
            'correlation_id' => $headers['x-correlation-id'],
        ]), $authorization);
    }

    /**
     * @param array<string, string> $headers
     */
    private function archive(array $headers, string $memoryRef, ?string $body): HttpTransportResponse
    {
        $required = $this->requiredHeaders(
            $headers,
            ['x-squirrelforge-identity-ref', 'x-squirrelforge-permission-ref', 'x-correlation-id']
        );

        if ($required !== null) {
            return $required;
        }

        $parsed = $this->parseMemoryRef($memoryRef);

        if ($parsed === null) {
            return $this->error(422, 'INVALID_MEMORY_REQUEST', 'The memory reference must be in the form "{memory_type}:{record_id}".');
        }

        [$memoryType, $recordId] = $parsed;
        $payload = $this->decode($body);

        if ($payload instanceof HttpTransportResponse) {
            return $payload;
        }

        if (!isset($payload['reason']) || !is_string($payload['reason']) || trim($payload['reason']) === '') {
            return $this->error(422, 'INVALID_MEMORY_REQUEST', 'reason must be a non-empty string.');
        }

        $authorization = $this->authorize($headers, 'memory.archive', 'memory:' . $memoryRef);

        if ($authorization instanceof HttpTransportResponse) {
            return $authorization;
        }

        if ($this->memoryRetention === null) {
            return $this->withAuthorization($this->error(409, 'MEMORY_REJECTED', 'Memory Retention is not configured.'), $authorization);
        }

        $evidence = array_merge(is_array($payload['evidence'] ?? null) ? $payload['evidence'] : [], ['reason' => $payload['reason']]);
        $result = $this->memoryRetention->evaluate($memoryType, $recordId, $evidence);

        if ($result['outcome'] === 'not_registered') {
            return $this->withAuthorization(
                $this->error(404, 'UNKNOWN_MEMORY_REFERENCE', $result['error']),
                $authorization
            );
        }

        return $this->withAuthorization($this->json(200, [
            'result' => [
                'memory_ref' => $memoryRef,
                'action' => $result['action'],
                'state' => $result['state'],
                'reason' => $result['reason'],
                'outcome' => $result['outcome'],
            ],
            'error' => null,
            'correlation_id' => $headers['x-correlation-id'],
        ]), $authorization);
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function parseMemoryRef(string $memoryRef): ?array
    {
        if (!str_contains($memoryRef, ':')) {
            return null;
        }

        [$memoryType, $recordId] = explode(':', $memoryRef, 2);

        if ($memoryType === '' || $recordId === '' || !in_array($memoryType, self::MEMORY_TYPES, true)) {
            return null;
        }

        return [$memoryType, $recordId];
    }

    /**
     * @return array|HttpTransportResponse
     */
    private function decode(?string $body): array|HttpTransportResponse
    {
        if ($body === null || trim($body) === '') {
            return $this->error(400, 'INVALID_MEMORY_REQUEST', 'A JSON request body is required.');
        }

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->error(400, 'INVALID_MEMORY_REQUEST', 'The request body is not valid JSON.');
        }

        return is_array($decoded)
            ? $decoded
            : $this->error(400, 'INVALID_MEMORY_REQUEST', 'The request body must be an object-like JSON value.');
    }

    /**
     * @param array<string, string> $headers
     * @param array<int, string> $required
     */
    private function requiredHeaders(array $headers, array $required): ?HttpTransportResponse
    {
        foreach ($required as $header) {
            if (!isset($headers[$header]) || trim($headers[$header]) === '') {
                $type = str_contains($header, 'identity') || str_contains($header, 'permission')
                    ? 'UNAUTHORIZED'
                    : 'INVALID_MEMORY_REQUEST';

                return $this->error(
                    $type === 'UNAUTHORIZED' ? 401 : 400,
                    $type,
                    sprintf('Required header %s is missing.', $header)
                );
            }
        }

        return null;
    }

    /**
     * @param array<string, string> $headers
     *
     * @return array{authentication: array<string, mixed>, authorization: array<string, mixed>}|HttpTransportResponse
     */
    private function authorize(array $headers, string $operation, string $resourceRef): array|HttpTransportResponse
    {
        $authentication = $this->authentication->authenticate(
            $this->bearerToken($headers['authorization'] ?? null),
            $headers['x-squirrelforge-identity-ref'],
            $headers['x-correlation-id']
        );

        if (($authentication['authenticated'] ?? false) !== true) {
            return $this->withAuthentication(
                $this->error(401, 'UNAUTHENTICATED', (string) ($authentication['rationale'] ?? 'Authentication failed.')),
                $authentication
            );
        }

        $decision = $this->authorization->authorize(
            $authentication['identity_ref'],
            $headers['x-squirrelforge-permission-ref'],
            $operation,
            $resourceRef,
            $headers['x-correlation-id']
        );

        if (($decision['authorized'] ?? false) !== true) {
            $response = $this->error(403, 'UNAUTHORIZED', (string) ($decision['rationale'] ?? 'Authorization was denied.'));

            return $this->withAuthorization($response, ['authentication' => $authentication, 'authorization' => $decision]);
        }

        return ['authentication' => $authentication, 'authorization' => $decision];
    }

    /**
     * @param array{authentication: array<string, mixed>, authorization: array<string, mixed>} $decision
     */
    private function withAuthorization(HttpTransportResponse $response, array $decision): HttpTransportResponse
    {
        $headers = $response->headers;
        $authentication = $decision['authentication'];
        $authorization = $decision['authorization'];
        $headers['x-squirrelforge-authentication-ref'] = (string) $authentication['authentication_ref'];
        $headers['x-squirrelforge-authorization-ref'] = (string) $authorization['authorization_ref'];

        return new HttpTransportResponse($response->status, $headers, $response->body);
    }

    /**
     * @param array<string, mixed> $authentication
     */
    private function withAuthentication(HttpTransportResponse $response, array $authentication): HttpTransportResponse
    {
        $headers = $response->headers;
        $headers['x-squirrelforge-authentication-ref'] = (string) $authentication['authentication_ref'];

        return new HttpTransportResponse($response->status, $headers, $response->body);
    }

    private function bearerToken(?string $authorizationHeader): ?string
    {
        if ($authorizationHeader === null
            || preg_match('/^Bearer\\s+(.+)$/i', trim($authorizationHeader), $matches) !== 1) {
            return null;
        }

        return trim($matches[1]);
    }

    private function error(int $status, string $type, string $message): HttpTransportResponse
    {
        return $this->json($status, [
            'error' => ['type' => $type, 'message' => $message, 'retryable' => false],
        ]);
    }

    private function json(int $status, array $body): HttpTransportResponse
    {
        return new HttpTransportResponse(
            $status,
            ['content-type' => 'application/json'],
            json_encode($body, JSON_THROW_ON_ERROR)
        );
    }

    /**
     * @param array<string, string> $headers
     *
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
