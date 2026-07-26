<?php

declare(strict_types=1);

namespace SquirrelForge\Integration\Http;

use SquirrelForge\Contracts\HttpTransportInterface;
use SquirrelForge\Integration\Flock\EngineTransportException;

final class NativeHttpTransport implements HttpTransportInterface
{
    public function request(
        string $method,
        string $url,
        array $headers,
        ?string $body,
        float $timeoutSeconds
    ): HttpTransportResponse {
        $headerLines = [];

        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headerLines),
                'content' => $body ?? '',
                'timeout' => $timeoutSeconds,
                'ignore_errors' => true,
                'follow_location' => 0,
            ],
        ]);

        $previous = set_error_handler(
            static fn(int $severity, string $message): never => throw new EngineTransportException($message)
        );

        try {
            $responseBody = file_get_contents($url, false, $context);
        } catch (EngineTransportException $exception) {
            throw $exception;
        } finally {
            restore_error_handler();
        }

        if ($responseBody === false) {
            throw new EngineTransportException('Engine transport returned no response.');
        }

        /** @var array<int, string> $http_response_header */
        $responseHeaders = $http_response_header ?? [];

        return new HttpTransportResponse(
            $this->statusCode($responseHeaders),
            $this->normalizeHeaders($responseHeaders),
            $responseBody
        );
    }

    /**
     * @param array<int, string> $headers
     */
    private function statusCode(array $headers): int
    {
        if ($headers === [] || preg_match('/^HTTP\/\\S+\\s+(\\d{3})/', $headers[0], $matches) !== 1) {
            throw new EngineTransportException('Engine transport returned an invalid status line.');
        }

        return (int) $matches[1];
    }

    /**
     * @param array<int, string> $headers
     *
     * @return array<string, string>
     */
    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];

        foreach (array_slice($headers, 1) as $header) {
            if (!str_contains($header, ':')) {
                continue;
            }

            [$name, $value] = explode(':', $header, 2);
            $normalized[strtolower(trim($name))] = trim($value);
        }

        return $normalized;
    }
}
