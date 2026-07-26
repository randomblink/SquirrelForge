<?php

declare(strict_types=1);

namespace SquirrelForge\Integration\Http;

use JsonException;
use SquirrelForge\Contracts\AuthenticationManagerInterface;
use SquirrelForge\Contracts\AuthorizationManagerInterface;
use SquirrelForge\Contracts\EngineRuntimeInterface;
use SquirrelForge\Security\StaticHeaderAuthenticationManager;
use SquirrelForge\Security\StaticAuthorizationManager;

final class EngineApiServer
{
    private readonly AuthorizationManagerInterface $authorization;
    private readonly AuthenticationManagerInterface $authentication;

    public function __construct(
        private readonly EngineRuntimeInterface $runtime,
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

        if ($method === 'POST' && $path === '/v1/engine/requests') {
            return $this->submit($headers, $body);
        }

        if (preg_match('#^/v1/engine/executions/([^/]+)(?:/(input|cancellation|result))?$#', $path, $matches) !== 1) {
            return $this->error(404, 'UNKNOWN_ROUTE', 'The Engine API route is unknown.');
        }

        $executionRef = rawurldecode($matches[1]);
        $operation = $matches[2] ?? null;

        if ($method === 'GET' && $operation === null) {
            $authorization = $this->authorize(
                $headers,
                'engine.inspect',
                'execution:' . $executionRef
            );

            if ($authorization instanceof HttpTransportResponse) {
                return $authorization;
            }

            return $this->withAuthorization(
                $this->runtimeResponse($this->runtime->inspect($executionRef)),
                $authorization
            );
        }

        if ($method === 'GET' && $operation === 'result') {
            $authorization = $this->authorize(
                $headers,
                'engine.result',
                'execution:' . $executionRef
            );

            if ($authorization instanceof HttpTransportResponse) {
                return $authorization;
            }

            return $this->withAuthorization(
                $this->runtimeResponse($this->runtime->result($executionRef)),
                $authorization
            );
        }

        if ($method === 'POST' && $operation === 'input') {
            return $this->provideInput($executionRef, $headers, $body);
        }

        if ($method === 'POST' && $operation === 'cancellation') {
            return $this->cancel($executionRef, $headers, $body);
        }

        return $this->error(405, 'METHOD_NOT_ALLOWED', 'The method is not allowed for this Engine API route.');
    }

    /**
     * @param array<string, string> $headers
     */
    private function submit(array $headers, ?string $body): HttpTransportResponse
    {
        $required = $this->requiredHeaders(
            $headers,
            ['x-squirrelforge-identity-ref', 'x-squirrelforge-permission-ref', 'x-correlation-id', 'idempotency-key']
        );

        if ($required !== null) {
            return $required;
        }

        $payload = $this->decode($body);

        if ($payload instanceof HttpTransportResponse) {
            return $payload;
        }

        if (!isset($payload['request'], $payload['project_ref'])
            || !is_array($payload['request'])
            || !is_string($payload['project_ref'])
            || trim($payload['project_ref']) === '') {
            return $this->error(422, 'INVALID_ENGINE_REQUEST', 'request and project_ref are required.');
        }

        $authorization = $this->authorize(
            $headers,
            'engine.submit',
            'project:' . $payload['project_ref']
        );

        if ($authorization instanceof HttpTransportResponse) {
            return $authorization;
        }

        return $this->withAuthorization($this->runtimeResponse($this->runtime->submit(
            $payload['request'],
            $payload['project_ref'],
            $headers['x-squirrelforge-identity-ref'],
            $headers['x-squirrelforge-permission-ref'],
            $headers['idempotency-key'],
            $headers['x-correlation-id']
        ), 202), $authorization);
    }

    /**
     * @param array<string, string> $headers
     */
    private function provideInput(
        string $executionRef,
        array $headers,
        ?string $body
    ): HttpTransportResponse {
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

        if (!isset($payload['input']) || !is_array($payload['input']) || $payload['input'] === []) {
            return $this->error(422, 'INVALID_ENGINE_REQUEST', 'input must be a non-empty object-like value.');
        }

        $authorization = $this->authorize(
            $headers,
            'engine.provide_input',
            'execution:' . $executionRef
        );

        if ($authorization instanceof HttpTransportResponse) {
            return $authorization;
        }

        return $this->withAuthorization($this->runtimeResponse($this->runtime->provideInput(
            $executionRef,
            $payload['input'],
            $headers['x-squirrelforge-identity-ref'],
            $headers['x-squirrelforge-permission-ref'],
            $headers['x-correlation-id']
        ), 202), $authorization);
    }

    /**
     * @param array<string, string> $headers
     */
    private function cancel(
        string $executionRef,
        array $headers,
        ?string $body
    ): HttpTransportResponse {
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

        if (!isset($payload['reason']) || !is_string($payload['reason']) || trim($payload['reason']) === '') {
            return $this->error(422, 'INVALID_ENGINE_REQUEST', 'reason must be a non-empty string.');
        }

        $authorization = $this->authorize(
            $headers,
            'engine.cancel',
            'execution:' . $executionRef
        );

        if ($authorization instanceof HttpTransportResponse) {
            return $authorization;
        }

        return $this->withAuthorization($this->runtimeResponse($this->runtime->cancel(
            $executionRef,
            $payload['reason'],
            $headers['x-squirrelforge-identity-ref'],
            $headers['x-squirrelforge-permission-ref'],
            $headers['x-correlation-id']
        ), 202), $authorization);
    }

    /**
     * @return array|HttpTransportResponse
     */
    private function decode(?string $body): array|HttpTransportResponse
    {
        if ($body === null || trim($body) === '') {
            return $this->error(400, 'INVALID_ENGINE_REQUEST', 'A JSON request body is required.');
        }

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->error(400, 'INVALID_ENGINE_REQUEST', 'The request body is not valid JSON.');
        }

        return is_array($decoded)
            ? $decoded
            : $this->error(400, 'INVALID_ENGINE_REQUEST', 'The request body must be an object-like JSON value.');
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
                    : 'INVALID_ENGINE_REQUEST';

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
     */
    private function authorize(
        array $headers,
        string $operation,
        string $resourceRef
    ): array|HttpTransportResponse
    {
        $required = $this->requiredHeaders(
            $headers,
            ['x-squirrelforge-identity-ref', 'x-squirrelforge-permission-ref', 'x-correlation-id']
        );

        if ($required !== null) {
            return $required;
        }

        $authentication = $this->authentication->authenticate(
            $this->bearerToken($headers['authorization'] ?? null),
            $headers['x-squirrelforge-identity-ref'],
            $headers['x-correlation-id']
        );

        if (($authentication['authenticated'] ?? false) !== true) {
            return $this->withAuthentication(
                $this->error(
                    401,
                    'UNAUTHENTICATED',
                    (string) ($authentication['rationale'] ?? 'Authentication failed.')
                ),
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
            $response = $this->error(
                403,
                'UNAUTHORIZED',
                (string) ($decision['rationale'] ?? 'Authorization was denied.')
            );

            return $this->withAuthorization($response, [
                'authentication' => $authentication,
                'authorization' => $decision,
            ]);
        }

        return [
            'authentication' => $authentication,
            'authorization' => $decision,
        ];
    }

    private function withAuthorization(
        HttpTransportResponse $response,
        array $decision
    ): HttpTransportResponse {
        $headers = $response->headers;
        $authentication = $decision['authentication'];
        $authorization = $decision['authorization'];
        $headers['x-squirrelforge-authentication-ref'] = (string) $authentication['authentication_ref'];
        $headers['x-squirrelforge-authorization-ref'] = (string) $authorization['authorization_ref'];

        if (($authorization['restrictions'] ?? []) !== []) {
            $headers['x-squirrelforge-authorization-restrictions'] = json_encode(
                $authorization['restrictions'],
                JSON_THROW_ON_ERROR
            );
        }

        return new HttpTransportResponse($response->status, $headers, $response->body);
    }

    private function withAuthentication(
        HttpTransportResponse $response,
        array $authentication
    ): HttpTransportResponse {
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

    private function runtimeResponse(array $result, int $successStatus = 200): HttpTransportResponse
    {
        if (!isset($result['error']) || !is_array($result['error'])) {
            return $this->json($successStatus, $result);
        }

        $type = $result['error']['type'] ?? 'ENGINE_REJECTED';
        $status = match ($type) {
            'UNAUTHORIZED' => 403,
            'UNKNOWN_EXECUTION' => 404,
            'INVALID_ENGINE_REQUEST' => 422,
            default => 409,
        };

        return $this->json($status, $result);
    }

    private function error(int $status, string $type, string $message): HttpTransportResponse
    {
        return $this->json($status, [
            'error' => [
                'type' => $type,
                'message' => $message,
                'retryable' => false,
            ],
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
