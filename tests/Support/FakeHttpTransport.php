<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Support;

use Closure;
use SquirrelForge\Contracts\HttpTransportInterface;
use SquirrelForge\Integration\Http\HttpTransportResponse;

final class FakeHttpTransport implements HttpTransportInterface
{
    /**
     * @var array<int, array{method: string, url: string, headers: array<string, string>, body: ?string, timeout: float}>
     */
    public array $requests = [];

    public function __construct(private readonly Closure $handler)
    {
    }

    public function request(
        string $method,
        string $url,
        array $headers,
        ?string $body,
        float $timeoutSeconds
    ): HttpTransportResponse {
        $request = [
            'method' => $method,
            'url' => $url,
            'headers' => $headers,
            'body' => $body,
            'timeout' => $timeoutSeconds,
        ];

        $this->requests[] = $request;

        return ($this->handler)($request);
    }
}
