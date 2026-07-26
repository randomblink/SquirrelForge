<?php

declare(strict_types=1);

namespace SquirrelForge\Integration\Http;

use SquirrelForge\Contracts\ProviderHealthInterface;
use SquirrelForge\Contracts\ProviderTelemetryInterface;

final class ProviderReadinessApiServer
{
    public function __construct(
        private readonly ?ProviderHealthInterface $provider,
        private readonly ProviderTelemetryInterface $telemetry
    ) {
    }

    public function handle(string $method, string $path): HttpTransportResponse
    {
        if (strtoupper($method) !== 'GET' || $path !== '/v1/health/providers') {
            return $this->json(404, [
                'error' => [
                    'type' => 'UNKNOWN_ROUTE',
                    'message' => 'The provider-readiness route is unknown.',
                    'retryable' => false,
                ],
            ]);
        }

        $health = $this->provider?->health() ?? [
            'healthy' => false,
            'provider_ref' => 'provider:health-unavailable',
            'rationale' => 'The configured provider does not expose a health probe.',
        ];
        $telemetry = $this->telemetry->snapshot();

        return $this->json(($health['healthy'] ?? false) === true ? 200 : 503, [
            'ready' => ($health['healthy'] ?? false) === true
                && ($telemetry['circuit_state'] ?? 'UNKNOWN') !== 'OPEN',
            'provider_ref' => $health['provider_ref'],
            'healthy' => $health['healthy'],
            'circuit_state' => $telemetry['circuit_state'],
            'counters' => $telemetry['counters'],
            'last_event_at' => $telemetry['last_event_at'],
            'rationale' => $health['rationale'],
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
}
