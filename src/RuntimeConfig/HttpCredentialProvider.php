<?php

declare(strict_types=1);

namespace SquirrelForge\RuntimeConfig;

use DateTimeImmutable;
use JsonException;
use RuntimeException;
use SquirrelForge\Contracts\HttpTransportInterface;
use SquirrelForge\Contracts\MfaVerifierInterface;
use SquirrelForge\Contracts\ProviderReadinessInterface;
use SquirrelForge\Contracts\SecretsManagerInterface;
use SquirrelForge\Contracts\SecurityEventSinkInterface;

final class HttpCredentialProvider implements
    SecretsManagerInterface,
    MfaVerifierInterface,
    SecurityEventSinkInterface,
    ProviderReadinessInterface
{
    private readonly string $baseUrl;

    public function __construct(
        string $baseUrl,
        private readonly string $providerToken,
        private readonly HttpTransportInterface $transport,
        private readonly float $timeoutSeconds = 10.0
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');

        if ($this->baseUrl === '' || $providerToken === '') {
            throw new RuntimeException('HTTP credential provider URL and token are required.');
        }
    }

    public function providerRef(): string
    {
        return 'credential-provider:http-json';
    }

    public function isProductionReady(): bool
    {
        return str_starts_with(strtolower($this->baseUrl), 'https://')
            && strlen($this->providerToken) >= 32;
    }

    public function registerApiKey(
        string $identityRef,
        string $apiKey,
        ?DateTimeImmutable $expiresAt = null
    ): string {
        $response = $this->request('/v1/provider/secrets/register', [
            'identity_ref' => $identityRef,
            'api_key' => $apiKey,
            'expires_at' => $expiresAt?->format(DATE_RFC3339),
        ]);

        return $this->requiredString($response, 'secret_ref');
    }

    public function verifyApiKey(string $identityRef, string $apiKey): array
    {
        return $this->request('/v1/provider/secrets/verify', [
            'identity_ref' => $identityRef,
            'api_key' => $apiKey,
        ]);
    }

    public function rotateApiKey(
        string $secretRef,
        string $newApiKey,
        ?DateTimeImmutable $expiresAt = null
    ): string {
        $response = $this->request('/v1/provider/secrets/rotate', [
            'secret_ref' => $secretRef,
            'new_api_key' => $newApiKey,
            'expires_at' => $expiresAt?->format(DATE_RFC3339),
        ]);

        return $this->requiredString($response, 'secret_ref');
    }

    public function revoke(string $secretRef): void
    {
        $this->request('/v1/provider/secrets/revoke', ['secret_ref' => $secretRef]);
    }

    public function verify(string $identityRef, ?string $proof, string $correlationId): array
    {
        return $this->request('/v1/provider/mfa/verify', [
            'identity_ref' => $identityRef,
            'proof' => $proof,
            'correlation_id' => $correlationId,
        ]);
    }

    public function record(
        string $eventType,
        string $outcome,
        ?string $identityRef,
        string $correlationId,
        array $metadata = []
    ): string {
        $response = $this->request('/v1/provider/security-events', [
            'event_type' => $eventType,
            'outcome' => $outcome,
            'identity_ref' => $identityRef,
            'correlation_id' => $correlationId,
            'metadata' => $metadata,
        ]);

        return $this->requiredString($response, 'security_event_ref');
    }

    private function request(string $path, array $payload): array
    {
        try {
            $body = json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Credential provider request could not be encoded.', 0, $exception);
        }

        $response = $this->transport->request(
            'POST',
            $this->baseUrl . $path,
            [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $this->providerToken,
                'Content-Type' => 'application/json',
            ],
            $body,
            $this->timeoutSeconds
        );

        if ($response->status < 200 || $response->status >= 300) {
            throw new RuntimeException('Credential provider rejected the operation.');
        }

        try {
            $decoded = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Credential provider returned invalid JSON.', 0, $exception);
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('Credential provider response must be object-like JSON.');
        }

        return $decoded;
    }

    private function requiredString(array $response, string $field): string
    {
        if (!is_string($response[$field] ?? null) || trim($response[$field]) === '') {
            throw new RuntimeException('Credential provider response is missing ' . $field . '.');
        }

        return $response[$field];
    }
}
