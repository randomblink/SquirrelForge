<?php

declare(strict_types=1);

namespace SquirrelForge\Integration\Http;

use DateTimeImmutable;
use JsonException;
use PDOException;
use SquirrelForge\Contracts\AuthenticationManagerInterface;
use SquirrelForge\Contracts\AuthorizationManagerInterface;
use SquirrelForge\Contracts\SecretsManagerInterface;
use SquirrelForge\Contracts\SecurityEventSinkInterface;
use SquirrelForge\Security\SqliteAuthenticationManager;

final class CredentialAdministrationApiServer
{
    public function __construct(
        private readonly SecretsManagerInterface $secrets,
        private readonly SqliteAuthenticationManager $sessions,
        private readonly AuthenticationManagerInterface $authentication,
        private readonly AuthorizationManagerInterface $authorization,
        private readonly SecurityEventSinkInterface $events
    ) {
    }

    /**
     * @param array<string, string> $headers
     */
    public function handle(string $method, string $path, array $headers, ?string $body): HttpTransportResponse
    {
        $headers = array_change_key_case($headers, CASE_LOWER);

        if (strtoupper($method) !== 'POST') {
            return $this->error(405, 'METHOD_NOT_ALLOWED', 'Credential administration requires POST.');
        }

        if (preg_match('#^/v1/security/api-keys/([^/]+)/(rotation|revocation)$#', $path, $matches) === 1) {
            $secretRef = rawurldecode($matches[1]);
            $operation = $matches[2] === 'rotation'
                ? 'security.credentials.rotate'
                : 'security.credentials.revoke';
            $decision = $this->authorize($headers, $operation, 'secret:' . $secretRef);

            if ($decision instanceof HttpTransportResponse) {
                return $decision;
            }

            return $matches[2] === 'rotation'
                ? $this->rotate($secretRef, $headers, $body, $decision)
                : $this->revokeSecret($secretRef, $headers, $decision);
        }

        if (preg_match('#^/v1/security/sessions/([^/]+)/revocation$#', $path, $matches) === 1) {
            $sessionRef = rawurldecode($matches[1]);
            $decision = $this->authorize(
                $headers,
                'security.sessions.revoke',
                'session:' . $sessionRef
            );

            if ($decision instanceof HttpTransportResponse) {
                return $decision;
            }

            $this->sessions->revokeSession($sessionRef);
            $eventRef = $this->events->record(
                'SESSION_REVOKED',
                'SUCCESS',
                $decision['authentication']['identity_ref'],
                $headers['x-correlation-id'],
                ['session_ref' => $sessionRef]
            );

            return $this->authorizedJson(200, [
                'revoked' => true,
                'session_ref' => $sessionRef,
                'security_event_ref' => $eventRef,
                'correlation_id' => $headers['x-correlation-id'],
            ], $decision);
        }

        return $this->error(404, 'UNKNOWN_ROUTE', 'The credential administration route is unknown.');
    }

    private function rotate(
        string $secretRef,
        array $headers,
        ?string $body,
        array $decision
    ): HttpTransportResponse {
        $payload = $this->decode($body);

        if ($payload instanceof HttpTransportResponse) {
            return $payload;
        }

        if (!is_string($payload['new_api_key'] ?? null) || strlen($payload['new_api_key']) < 32) {
            return $this->error(422, 'INVALID_CREDENTIAL_REQUEST', 'A replacement API key is required.');
        }

        try {
            $expiresAt = is_string($payload['expires_at'] ?? null)
                ? new DateTimeImmutable($payload['expires_at'])
                : null;
            $newSecretRef = $this->secrets->rotateApiKey($secretRef, $payload['new_api_key'], $expiresAt);
        } catch (\Throwable) {
            return $this->error(409, 'CREDENTIAL_ROTATION_FAILED', 'The credential could not be rotated.');
        }

        $eventRef = $this->events->record(
            'API_KEY_ROTATED',
            'SUCCESS',
            $decision['authentication']['identity_ref'],
            $headers['x-correlation-id'],
            ['previous_secret_ref' => $secretRef, 'secret_ref' => $newSecretRef]
        );

        return $this->authorizedJson(201, [
            'rotated' => true,
            'previous_secret_ref' => $secretRef,
            'secret_ref' => $newSecretRef,
            'security_event_ref' => $eventRef,
            'correlation_id' => $headers['x-correlation-id'],
        ], $decision);
    }

    private function revokeSecret(string $secretRef, array $headers, array $decision): HttpTransportResponse
    {
        $this->secrets->revoke($secretRef);
        $eventRef = $this->events->record(
            'API_KEY_REVOKED',
            'SUCCESS',
            $decision['authentication']['identity_ref'],
            $headers['x-correlation-id'],
            ['secret_ref' => $secretRef]
        );

        return $this->authorizedJson(200, [
            'revoked' => true,
            'secret_ref' => $secretRef,
            'security_event_ref' => $eventRef,
            'correlation_id' => $headers['x-correlation-id'],
        ], $decision);
    }

    private function authorize(array $headers, string $operation, string $resourceRef): array|HttpTransportResponse
    {
        foreach ([
            'x-squirrelforge-identity-ref',
            'x-squirrelforge-permission-ref',
            'x-correlation-id',
        ] as $required) {
            if (!is_string($headers[$required] ?? null) || trim($headers[$required]) === '') {
                return $this->error(401, 'UNAUTHORIZED', 'Required security headers are missing.');
            }
        }

        $token = null;

        if (preg_match('/^Bearer\s+(.+)$/i', $headers['authorization'] ?? '', $matches) === 1) {
            $token = trim($matches[1]);
        }

        $authentication = $this->authentication->authenticate(
            $token,
            $headers['x-squirrelforge-identity-ref'],
            $headers['x-correlation-id']
        );

        if (($authentication['authenticated'] ?? false) !== true) {
            return $this->referencedError(401, 'UNAUTHENTICATED', 'Authentication failed.', $authentication, null);
        }

        $authorization = $this->authorization->authorize(
            $authentication['identity_ref'],
            $headers['x-squirrelforge-permission-ref'],
            $operation,
            $resourceRef,
            $headers['x-correlation-id']
        );

        if (($authorization['authorized'] ?? false) !== true) {
            return $this->referencedError(
                403,
                'UNAUTHORIZED',
                'Credential administration was denied.',
                $authentication,
                $authorization
            );
        }

        return ['authentication' => $authentication, 'authorization' => $authorization];
    }

    private function decode(?string $body): array|HttpTransportResponse
    {
        try {
            $decoded = json_decode((string) $body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->error(400, 'INVALID_CREDENTIAL_REQUEST', 'The request body is invalid.');
        }

        return is_array($decoded)
            ? $decoded
            : $this->error(400, 'INVALID_CREDENTIAL_REQUEST', 'The request body is invalid.');
    }

    private function authorizedJson(int $status, array $body, array $decision): HttpTransportResponse
    {
        $response = $this->json($status, $body);
        $headers = $response->headers;
        $headers['x-squirrelforge-authentication-ref'] =
            (string) $decision['authentication']['authentication_ref'];
        $headers['x-squirrelforge-authorization-ref'] =
            (string) $decision['authorization']['authorization_ref'];

        return new HttpTransportResponse($status, $headers, $response->body);
    }

    private function referencedError(
        int $status,
        string $type,
        string $message,
        array $authentication,
        ?array $authorization
    ): HttpTransportResponse {
        $response = $this->error($status, $type, $message);
        $headers = $response->headers;
        $headers['x-squirrelforge-authentication-ref'] = (string) $authentication['authentication_ref'];

        if ($authorization !== null) {
            $headers['x-squirrelforge-authorization-ref'] = (string) $authorization['authorization_ref'];
        }

        return new HttpTransportResponse($status, $headers, $response->body);
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
}
