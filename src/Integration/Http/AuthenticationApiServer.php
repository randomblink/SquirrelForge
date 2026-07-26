<?php

declare(strict_types=1);

namespace SquirrelForge\Integration\Http;

use JsonException;
use PDOException;
use SquirrelForge\Security\ApiKeySessionIssuer;
use SquirrelForge\Security\SqliteAuthenticationManager;

final class AuthenticationApiServer
{
    public function __construct(
        private readonly ApiKeySessionIssuer $issuer,
        private readonly ?SqliteAuthenticationManager $sessions = null
    ) {
    }

    /**
     * @param array<string, string> $headers
     */
    public function handle(string $method, string $path, array $headers, ?string $body): HttpTransportResponse
    {
        if (strtoupper($method) !== 'POST'
            || !in_array($path, [
                '/v1/authentication/sessions',
                '/v1/authentication/sessions/refresh',
            ], true)) {
            return $this->error(404, 'UNKNOWN_ROUTE', 'The authentication route is unknown.');
        }

        $headers = array_change_key_case($headers, CASE_LOWER);
        $correlationId = $headers['x-correlation-id'] ?? null;

        if (!is_string($correlationId) || trim($correlationId) === '') {
            return $this->error(400, 'INVALID_AUTHENTICATION_REQUEST', 'X-Correlation-ID is required.');
        }

        if ($path === '/v1/authentication/sessions/refresh') {
            return $this->refresh($headers, $correlationId);
        }

        try {
            $payload = json_decode((string) $body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->error(400, 'INVALID_AUTHENTICATION_REQUEST', 'The request body is invalid.');
        }

        if (!is_array($payload)
            || !is_string($payload['identity_ref'] ?? null)
            || trim($payload['identity_ref']) === ''
            || !is_string($payload['api_key'] ?? null)
            || trim($payload['api_key']) === '') {
            return $this->error(422, 'INVALID_AUTHENTICATION_REQUEST', 'Identity and API key are required.');
        }

        $result = $this->issuer->issue(
            $payload['identity_ref'],
            $payload['api_key'],
            $correlationId,
            is_string($headers['x-source-ref'] ?? null) ? $headers['x-source-ref'] : 'source:unknown',
            is_string($payload['mfa_proof'] ?? null) ? $payload['mfa_proof'] : null
        );

        if (($result['authenticated'] ?? false) === true) {
            return $this->json(201, $result);
        }

        $type = $result['error']['type'] ?? 'INVALID_CREDENTIALS';

        return $this->json($type === 'AUTHENTICATION_LOCKED' ? 429 : 401, $result);
    }

    private function refresh(array $headers, string $correlationId): HttpTransportResponse
    {
        $authorization = $headers['authorization'] ?? '';

        if ($this->sessions === null
            || !is_string($authorization)
            || preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches) !== 1) {
            return $this->error(401, 'INVALID_SESSION', 'A bearer session is required.');
        }

        try {
            $session = $this->sessions->refreshSession($matches[1], $correlationId);
        } catch (PDOException) {
            return $this->error(401, 'INVALID_SESSION', 'The session could not be refreshed.');
        }

        return $this->json(201, ['authenticated' => true] + $session + [
            'correlation_id' => $correlationId,
        ]);
    }

    private function error(int $status, string $type, string $message): HttpTransportResponse
    {
        return $this->json($status, [
            'authenticated' => false,
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
}
