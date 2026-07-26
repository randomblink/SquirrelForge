<?php

declare(strict_types=1);

namespace SquirrelForge\Contracts;

use SquirrelForge\Integration\Http\HttpTransportResponse;

interface HttpTransportInterface
{
    /**
     * @param array<string, string> $headers
     */
    public function request(
        string $method,
        string $url,
        array $headers,
        ?string $body,
        float $timeoutSeconds
    ): HttpTransportResponse;
}
