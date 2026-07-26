<?php

declare(strict_types=1);

namespace SquirrelForge\RuntimeConfig;

use RuntimeException;
use SquirrelForge\Contracts\HttpTransportInterface;
use SquirrelForge\Contracts\ProviderTelemetryInterface;
use SquirrelForge\Integration\Http\HttpTransportResponse;

final class ResilientHttpTransport implements HttpTransportInterface
{
    private int $consecutiveFailures = 0;
    private ?int $circuitOpenedAt = null;
    private readonly ProviderTelemetryInterface $telemetry;

    public function __construct(
        private readonly HttpTransportInterface $transport,
        private readonly int $maximumAttempts = 3,
        private readonly int $failureThreshold = 3,
        private readonly int $cooldownSeconds = 30,
        ?ProviderTelemetryInterface $telemetry = null
    ) {
        $this->telemetry = $telemetry ?? new NullProviderTelemetry();
        if ($maximumAttempts < 1 || $failureThreshold < 1 || $cooldownSeconds < 1) {
            throw new RuntimeException('Provider resilience values must be positive.');
        }
    }

    public function request(
        string $method,
        string $url,
        array $headers,
        ?string $body,
        float $timeoutSeconds
    ): HttpTransportResponse {
        if ($this->circuitOpenedAt !== null) {
            if (time() - $this->circuitOpenedAt < $this->cooldownSeconds) {
                $this->telemetry->record('provider.circuit.rejected', ['circuit_state' => 'OPEN']);
                throw new RuntimeException('Credential provider circuit is open.');
            }

            $this->circuitOpenedAt = null;
            $this->consecutiveFailures = 0;
            $this->telemetry->record('provider.circuit.half_open', ['circuit_state' => 'HALF_OPEN']);
        }

        if ($this->isMutation($method, $url) && !isset($headers['Idempotency-Key'])) {
            $headers['Idempotency-Key'] = 'provider_operation_' . bin2hex(random_bytes(16));
        }

        for ($attempt = 1; $attempt <= $this->maximumAttempts; $attempt++) {
            $this->telemetry->record('provider.attempt', [
                'attempt' => $attempt,
                'operation_class' => $this->isMutation($method, $url) ? 'MUTATION' : 'READ',
            ]);

            try {
                $response = $this->transport->request($method, $url, $headers, $body, $timeoutSeconds);
            } catch (\Throwable $exception) {
                if ($attempt < $this->maximumAttempts) {
                    $this->telemetry->record('provider.retry', ['attempt' => $attempt]);
                    continue;
                }

                $this->recordFailure();
                throw new RuntimeException('Credential provider transport failed.', 0, $exception);
            }

            if (!$this->retryable($response->status)) {
                if ($response->status >= 200 && $response->status < 500) {
                    $this->consecutiveFailures = 0;
                    $this->circuitOpenedAt = null;
                    $this->telemetry->record('provider.success', [
                        'status' => $response->status,
                        'circuit_state' => 'CLOSED',
                    ]);
                } else {
                    $this->recordFailure();
                }

                return $response;
            }

            if ($attempt === $this->maximumAttempts) {
                $this->recordFailure();

                return $response;
            }

            $this->telemetry->record('provider.retry', [
                'attempt' => $attempt,
                'status' => $response->status,
            ]);
        }

        throw new RuntimeException('Credential provider retry loop ended unexpectedly.');
    }

    private function recordFailure(): void
    {
        $this->consecutiveFailures++;

        if ($this->consecutiveFailures >= $this->failureThreshold) {
            $this->circuitOpenedAt = time();
            $this->telemetry->record('provider.circuit.opened', ['circuit_state' => 'OPEN']);
        } else {
            $this->telemetry->record('provider.failure', ['circuit_state' => 'CLOSED']);
        }
    }

    private function retryable(int $status): bool
    {
        return in_array($status, [408, 429, 502, 503, 504], true);
    }

    private function isMutation(string $method, string $url): bool
    {
        if (strtoupper($method) !== 'POST') {
            return false;
        }

        foreach (['/register', '/rotate', '/revoke', '/security-events'] as $suffix) {
            if (str_ends_with($url, $suffix)) {
                return true;
            }
        }

        return false;
    }
}
