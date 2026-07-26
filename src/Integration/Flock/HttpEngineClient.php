<?php

declare(strict_types=1);

namespace SquirrelForge\Integration\Flock;

use JsonException;
use SquirrelForge\Contracts\EngineClientInterface;
use SquirrelForge\Contracts\HttpTransportInterface;
use SquirrelForge\Integration\Http\HttpTransportResponse;

final class HttpEngineClient implements EngineClientInterface
{
    private readonly string $baseUrl;

    public function __construct(
        string $baseUrl,
        private readonly HttpTransportInterface $transport,
        private readonly float $timeoutSeconds = 30.0,
        private readonly ?string $accessToken = null
    ) {
        $baseUrl = rtrim(trim($baseUrl), '/');

        if ($baseUrl === '' || filter_var($baseUrl, FILTER_VALIDATE_URL) === false) {
            throw new EngineTransportException('Engine base URL is invalid.');
        }

        if ($timeoutSeconds <= 0) {
            throw new EngineTransportException('Engine timeout must be greater than zero.');
        }

        $this->baseUrl = $baseUrl;
    }

    public function submit(
        array $request,
        string $projectRef,
        string $identityRef,
        string $permissionRef,
        string $idempotencyKey,
        string $correlationId
    ): array {
        return $this->send(
            'POST',
            '/v1/engine/requests',
            [
                'request' => $request,
                'project_ref' => $projectRef,
            ],
            $identityRef,
            $permissionRef,
            $correlationId,
            $idempotencyKey
        );
    }

    public function inspect(
        string $executionRef,
        string $identityRef,
        string $permissionRef,
        string $correlationId
    ): array
    {
        return $this->send(
            'GET',
            '/v1/engine/executions/' . rawurlencode($executionRef),
            null,
            $identityRef,
            $permissionRef,
            $correlationId
        );
    }

    public function provideInput(
        string $executionRef,
        array $input,
        string $identityRef,
        string $permissionRef,
        string $correlationId
    ): array {
        return $this->send(
            'POST',
            '/v1/engine/executions/' . rawurlencode($executionRef) . '/input',
            ['input' => $input],
            $identityRef,
            $permissionRef,
            $correlationId
        );
    }

    public function cancel(
        string $executionRef,
        string $reason,
        string $identityRef,
        string $permissionRef,
        string $correlationId
    ): array {
        return $this->send(
            'POST',
            '/v1/engine/executions/' . rawurlencode($executionRef) . '/cancellation',
            ['reason' => $reason],
            $identityRef,
            $permissionRef,
            $correlationId
        );
    }

    public function result(
        string $executionRef,
        string $identityRef,
        string $permissionRef,
        string $correlationId
    ): array
    {
        return $this->send(
            'GET',
            '/v1/engine/executions/' . rawurlencode($executionRef) . '/result',
            null,
            $identityRef,
            $permissionRef,
            $correlationId
        );
    }

    private function send(
        string $method,
        string $path,
        ?array $payload,
        ?string $identityRef = null,
        ?string $permissionRef = null,
        ?string $correlationId = null,
        ?string $idempotencyKey = null
    ): array {
        $headers = ['Accept' => 'application/json'];
        $body = null;

        if ($this->accessToken !== null && trim($this->accessToken) !== '') {
            $headers['Authorization'] = 'Bearer ' . $this->accessToken;
        }

        if ($payload !== null) {
            $headers['Content-Type'] = 'application/json';
            $body = $this->encode($payload);
        }

        if ($identityRef !== null) {
            $headers['X-SquirrelForge-Identity-Ref'] = $identityRef;
        }

        if ($permissionRef !== null) {
            $headers['X-SquirrelForge-Permission-Ref'] = $permissionRef;
        }

        if ($correlationId !== null) {
            $headers['X-Correlation-ID'] = $correlationId;
        }

        if ($idempotencyKey !== null) {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        $response = $this->transport->request(
            $method,
            $this->baseUrl . $path,
            $headers,
            $body,
            $this->timeoutSeconds
        );

        return $this->decode($response);
    }

    private function encode(array $payload): string
    {
        try {
            return json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new EngineTransportException('Engine request could not be encoded.', 0, $exception);
        }
    }

    private function decode(HttpTransportResponse $response): array
    {
        try {
            $decoded = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new EngineTransportException('Engine response is not valid JSON.', 0, $exception);
        }

        if (!is_array($decoded)) {
            throw new EngineTransportException('Engine response must be an object-like JSON value.');
        }

        if (isset($response->headers['x-squirrelforge-authorization-ref'])) {
            $decoded['authorization_ref'] ??= $response->headers['x-squirrelforge-authorization-ref'];
        }

        if (isset($response->headers['x-squirrelforge-authentication-ref'])) {
            $decoded['authentication_ref'] ??= $response->headers['x-squirrelforge-authentication-ref'];
        }

        if ($response->status >= 200 && $response->status < 300) {
            return $decoded;
        }

        if (isset($decoded['error']) && is_array($decoded['error'])) {
            $decoded['error']['http_status'] ??= $response->status;
            $decoded['error']['retryable'] ??= $response->status === 429 || $response->status >= 500;

            if (isset($response->headers['retry-after'])) {
                $decoded['error']['retry_after'] ??= $response->headers['retry-after'];
            }

            if (isset($decoded['authorization_ref'])) {
                $decoded['error']['authorization_ref'] ??= $decoded['authorization_ref'];
            }

            if (isset($decoded['authentication_ref'])) {
                $decoded['error']['authentication_ref'] ??= $decoded['authentication_ref'];
            }

            return $decoded;
        }

        return [
            'error' => [
                'type' => $this->errorType($response->status),
                'message' => 'The Engine returned an unsuccessful response.',
                'http_status' => $response->status,
                'retryable' => $response->status === 429 || $response->status >= 500,
                'retry_after' => $response->headers['retry-after'] ?? null,
            ],
        ];
    }

    private function errorType(int $status): string
    {
        return match ($status) {
            400, 422 => 'INVALID_ENGINE_REQUEST',
            401, 403 => 'UNAUTHORIZED',
            404 => 'UNKNOWN_EXECUTION',
            409 => 'ENGINE_REJECTED',
            429 => 'RATE_LIMITED',
            default => $status >= 500 ? 'TRANSPORT_UNAVAILABLE' : 'ENGINE_REJECTED',
        };
    }
}
