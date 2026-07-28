<?php

declare(strict_types=1);

namespace SquirrelForge\Integration\Http;

use DateTimeImmutable;
use SquirrelForge\Contracts\AuthenticationManagerInterface;
use SquirrelForge\Contracts\AuthorizationManagerInterface;
use SquirrelForge\Contracts\ProviderHealthInterface;
use SquirrelForge\Contracts\ProviderTelemetryInterface;
use SquirrelForge\Engine\SqliteEngineRuntime;
use SquirrelForge\Security\SqliteSecurityEventSink;

/**
 * Presents an aggregate operational view built from already-existing
 * observability records (provider readiness, recent security events, recent
 * engine executions), per 27_OBSERVABILITY/DASHBOARD-MANAGER.md. This does
 * not collect telemetry itself, does not become a source of truth for any of
 * it, and never exposes stored payload/metadata fields.
 */
final class DashboardApiServer
{
    public function __construct(
        private readonly ?ProviderHealthInterface $provider,
        private readonly ProviderTelemetryInterface $telemetry,
        private readonly ?SqliteSecurityEventSink $securityEvents,
        private readonly SqliteEngineRuntime $engineRuntime,
        private readonly AuthorizationManagerInterface $authorization,
        private readonly AuthenticationManagerInterface $authentication
    ) {
    }

    /**
     * @param array<string, string> $headers
     */
    public function handle(string $method, string $path, array $headers, ?string $body): HttpTransportResponse
    {
        $method = strtoupper($method);
        $headers = $this->normalizeHeaders($headers);

        if ($path !== '/v1/admin/dashboard') {
            return $this->error(404, 'UNKNOWN_ROUTE', 'The dashboard route is unknown.');
        }

        if ($method !== 'GET') {
            return $this->error(405, 'METHOD_NOT_ALLOWED', 'The method is not allowed for this dashboard route.');
        }

        $authorization = $this->authorize($headers, 'observability.dashboard.read', 'dashboard:platform');

        if ($authorization instanceof HttpTransportResponse) {
            return $authorization;
        }

        $asOf = (new DateTimeImmutable())->format(DATE_ATOM);
        $health = $this->provider?->health() ?? [
            'healthy' => false,
            'provider_ref' => 'provider:health-unavailable',
            'rationale' => 'The configured provider does not expose a health probe.',
        ];
        $telemetry = $this->telemetry->snapshot();

        return $this->withAuthorization($this->json(200, [
            'provider' => [
                'source' => 'provider-readiness',
                'as_of' => $asOf,
                'healthy' => $health['healthy'],
                'provider_ref' => $health['provider_ref'],
                'circuit_state' => $telemetry['circuit_state'],
                'counters' => $telemetry['counters'],
                'last_event_at' => $telemetry['last_event_at'],
            ],
            'security_events' => [
                'source' => $this->securityEvents?->providerRef() ?? 'security-events:unavailable',
                'as_of' => $asOf,
                'recent' => $this->securityEvents?->recent() ?? [],
            ],
            'engine_executions' => [
                'source' => 'engine:sqlite-local',
                'as_of' => $asOf,
                'recent' => $this->engineRuntime->recent(),
            ],
        ]), $authorization);
    }

    /**
     * @param array<string, string> $headers
     *
     * @return array{authentication: array<string, mixed>, authorization: array<string, mixed>}|HttpTransportResponse
     */
    private function authorize(
        array $headers,
        string $operation,
        string $resourceRef
    ): array|HttpTransportResponse {
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
                    : 'INVALID_REQUEST';

                return $this->error(
                    $type === 'UNAUTHORIZED' ? 401 : 400,
                    $type,
                    sprintf('Required header %s is missing.', $header)
                );
            }
        }

        return null;
    }

    private function bearerToken(?string $authorizationHeader): ?string
    {
        if ($authorizationHeader === null
            || preg_match('/^Bearer\\s+(.+)$/i', trim($authorizationHeader), $matches) !== 1) {
            return null;
        }

        return trim($matches[1]);
    }

    /**
     * @param array{authentication: array<string, mixed>, authorization: array<string, mixed>} $decision
     */
    private function withAuthorization(
        HttpTransportResponse $response,
        array $decision
    ): HttpTransportResponse {
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
    private function withAuthentication(
        HttpTransportResponse $response,
        array $authentication
    ): HttpTransportResponse {
        $headers = $response->headers;
        $headers['x-squirrelforge-authentication-ref'] = (string) $authentication['authentication_ref'];

        return new HttpTransportResponse($response->status, $headers, $response->body);
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
            ['content-type' => 'application/json', 'cache-control' => 'no-store'],
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
