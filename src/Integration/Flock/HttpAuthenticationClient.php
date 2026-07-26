<?php

declare(strict_types=1);

namespace SquirrelForge\Integration\Flock;

use JsonException;
use SquirrelForge\Contracts\HttpTransportInterface;

final class HttpAuthenticationClient
{
    private readonly string $baseUrl;

    public function __construct(
        string $baseUrl,
        private readonly HttpTransportInterface $transport,
        private readonly float $timeoutSeconds = 10.0
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function createSession(
        string $identityRef,
        string $apiKey,
        string $correlationId,
        string $sourceRef,
        ?string $mfaProof = null
    ): array {
        try {
            $body = json_encode([
                'identity_ref' => $identityRef,
                'api_key' => $apiKey,
                'mfa_proof' => $mfaProof,
            ], JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new EngineTransportException('Authentication request could not be encoded.', 0, $exception);
        }

        $response = $this->transport->request(
            'POST',
            $this->baseUrl . '/v1/authentication/sessions',
            [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-Correlation-ID' => $correlationId,
                'X-Source-Ref' => $sourceRef,
            ],
            $body,
            $this->timeoutSeconds
        );

        try {
            $decoded = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new EngineTransportException('Authentication response is not valid JSON.', 0, $exception);
        }

        if (!is_array($decoded)) {
            throw new EngineTransportException('Authentication response must be object-like JSON.');
        }

        return $decoded;
    }

    public function refreshSession(
        string $accessToken,
        string $correlationId,
        string $sourceRef
    ): array {
        $response = $this->transport->request(
            'POST',
            $this->baseUrl . '/v1/authentication/sessions/refresh',
            [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $accessToken,
                'X-Correlation-ID' => $correlationId,
                'X-Source-Ref' => $sourceRef,
            ],
            null,
            $this->timeoutSeconds
        );

        try {
            $decoded = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new EngineTransportException('Authentication response is not valid JSON.', 0, $exception);
        }

        if (!is_array($decoded)) {
            throw new EngineTransportException('Authentication response must be object-like JSON.');
        }

        return $decoded;
    }
}
