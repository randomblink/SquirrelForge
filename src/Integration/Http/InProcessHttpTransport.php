<?php

declare(strict_types=1);

namespace SquirrelForge\Integration\Http;

use SquirrelForge\Contracts\HttpTransportInterface;
use SquirrelForge\Integration\Flock\EngineTransportException;

final class InProcessHttpTransport implements HttpTransportInterface
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly EngineApiServer $server
    ) {
    }

    public function request(
        string $method,
        string $url,
        array $headers,
        ?string $body,
        float $timeoutSeconds
    ): HttpTransportResponse {
        if (!str_starts_with($url, rtrim($this->baseUrl, '/') . '/')) {
            throw new EngineTransportException('In-process transport received an unexpected Engine base URL.');
        }

        $path = parse_url($url, PHP_URL_PATH);

        if (!is_string($path)) {
            throw new EngineTransportException('In-process transport received an invalid Engine URL.');
        }

        return $this->server->handle($method, $path, $headers, $body);
    }
}
