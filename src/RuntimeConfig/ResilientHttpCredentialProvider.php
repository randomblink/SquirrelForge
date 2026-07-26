<?php

declare(strict_types=1);

namespace SquirrelForge\RuntimeConfig;

use DateTimeImmutable;
use RuntimeException;
use SquirrelForge\Contracts\HttpTransportInterface;
use SquirrelForge\Contracts\MfaVerifierInterface;
use SquirrelForge\Contracts\ProviderHealthInterface;
use SquirrelForge\Contracts\ProviderReadinessInterface;
use SquirrelForge\Contracts\SecretsManagerInterface;
use SquirrelForge\Contracts\SecurityEventSinkInterface;

final class ResilientHttpCredentialProvider implements
    SecretsManagerInterface,
    MfaVerifierInterface,
    SecurityEventSinkInterface,
    ProviderReadinessInterface,
    ProviderHealthInterface
{
    private readonly HttpCredentialProvider $provider;
    private readonly string $baseUrl;
    private readonly string $providerToken;

    public function __construct(
        string $baseUrl,
        string $providerToken,
        private readonly HttpTransportInterface $transport,
        float $timeoutSeconds = 10.0
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->providerToken = $providerToken;
        $this->provider = new HttpCredentialProvider(
            $this->baseUrl,
            $providerToken,
            $transport,
            $timeoutSeconds
        );
    }

    public function providerRef(): string
    {
        return $this->provider->providerRef() . ':resilient';
    }

    public function isProductionReady(): bool
    {
        return $this->provider->isProductionReady();
    }

    public function health(): array
    {
        try {
            $response = $this->transport->request(
                'GET',
                $this->baseUrl . '/v1/provider/health',
                [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . $this->providerToken,
                ],
                null,
                5.0
            );
            $body = json_decode($response->body, true);
            $healthy = $response->status >= 200
                && $response->status < 300
                && is_array($body)
                && ($body['healthy'] ?? false) === true;
        } catch (\Throwable) {
            $healthy = false;
        }

        return [
            'healthy' => $healthy,
            'provider_ref' => $this->providerRef(),
            'rationale' => $healthy
                ? 'The credential provider health probe succeeded.'
                : 'The credential provider health probe failed.',
        ];
    }

    public function registerApiKey(
        string $identityRef,
        string $apiKey,
        ?DateTimeImmutable $expiresAt = null
    ): string {
        return $this->provider->registerApiKey($identityRef, $apiKey, $expiresAt);
    }

    public function verifyApiKey(string $identityRef, string $apiKey): array
    {
        return $this->provider->verifyApiKey($identityRef, $apiKey);
    }

    public function rotateApiKey(
        string $secretRef,
        string $newApiKey,
        ?DateTimeImmutable $expiresAt = null
    ): string {
        return $this->provider->rotateApiKey($secretRef, $newApiKey, $expiresAt);
    }

    public function revoke(string $secretRef): void
    {
        $this->provider->revoke($secretRef);
    }

    public function verify(string $identityRef, ?string $proof, string $correlationId): array
    {
        return $this->provider->verify($identityRef, $proof, $correlationId);
    }

    public function record(
        string $eventType,
        string $outcome,
        ?string $identityRef,
        string $correlationId,
        array $metadata = []
    ): string {
        return $this->provider->record($eventType, $outcome, $identityRef, $correlationId, $metadata);
    }
}
