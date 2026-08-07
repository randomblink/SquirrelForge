<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;
use SquirrelForge\Integration\ApiGateway;
use SquirrelForge\Integration\Http\HttpTransportResponse;
use SquirrelForge\Tests\Support\FakeHttpTransport;

final class ApiGatewayTest extends TestCase
{
    public function testMissingEndpointRefIsRequestInvalid(): void
    {
        $gateway = new ApiGateway();

        $result = $gateway->send(['method' => 'GET']);

        $this->assertSame('Request Invalid', $result['transport_status']);
        $this->assertStringContainsString('endpoint_ref', $result['error']);
    }

    public function testUnrecognizedMethodIsRequestInvalid(): void
    {
        $gateway = new ApiGateway();

        $result = $gateway->send(['endpoint_ref' => 'https://api.example.com/widgets', 'method' => 'FETCH']);

        $this->assertSame('Request Invalid', $result['transport_status']);
        $this->assertStringContainsString('method', $result['error']);
    }

    public function testNonPositiveTimeoutIsRequestInvalid(): void
    {
        $gateway = new ApiGateway();

        $result = $gateway->send([
            'endpoint_ref' => 'https://api.example.com/widgets',
            'method' => 'GET',
            'timeout_seconds' => 0,
        ]);

        $this->assertSame('Request Invalid', $result['transport_status']);
        $this->assertStringContainsString('timeout_seconds', $result['error']);
    }

    public function testExplicitlyUnauthorizedRequestIsCredentialBlocked(): void
    {
        $gateway = new ApiGateway();

        $result = $gateway->send([
            'endpoint_ref' => 'https://api.example.com/widgets',
            'method' => 'GET',
            'credential_authorized' => false,
            'credential_status' => 'token_expired',
        ]);

        $this->assertSame('Credential Blocked', $result['transport_status']);
        $this->assertSame('token_expired', $result['error']);
        $this->assertSame(['Ready', 'Credential Blocked'], $result['stages']);
    }

    public function testCredentialAuthorizedDefaultsToTrueWhenOmitted(): void
    {
        $gateway = new ApiGateway();

        $result = $gateway->send(['endpoint_ref' => 'https://api.example.com/widgets', 'method' => 'GET']);

        $this->assertSame('Ready', $result['transport_status']);
    }

    public function testRateLimitedRequestIsBlocked(): void
    {
        $gateway = new ApiGateway();

        $result = $gateway->send([
            'endpoint_ref' => 'https://api.example.com/widgets',
            'method' => 'GET',
            'rate_limited' => true,
            'rate_limit_status' => 'quota_exceeded',
        ]);

        $this->assertSame('Rate Limited', $result['transport_status']);
        $this->assertSame('quota_exceeded', $result['error']);
    }

    public function testReadyWithoutATransportNeverFabricatesAResponse(): void
    {
        $gateway = new ApiGateway();

        $result = $gateway->send(['endpoint_ref' => 'https://api.example.com/widgets', 'method' => 'GET']);

        $this->assertSame('Ready', $result['transport_status']);
        $this->assertNull($result['status_code']);
        $this->assertNull($result['body']);
        $this->assertNull($result['error']);
    }

    public function testSuccessfulSendReturnsNormalizedResponse(): void
    {
        $transport = new FakeHttpTransport(
            static fn(array $request): HttpTransportResponse => new HttpTransportResponse(200, ['content-type' => 'application/json'], '{"ok":true}')
        );
        $gateway = new ApiGateway($transport);

        $result = $gateway->send([
            'endpoint_ref' => 'https://api.example.com/widgets',
            'method' => 'get',
            'headers' => ['authorization' => 'Bearer tok'],
            'body' => null,
        ]);

        $this->assertSame('Normalized', $result['transport_status']);
        $this->assertSame(['Ready', 'Sent', 'Response Received', 'Normalized'], $result['stages']);
        $this->assertSame(200, $result['status_code']);
        $this->assertSame('{"ok":true}', $result['body']);
        $this->assertSame('GET', $transport->requests[0]['method']);
    }

    public function testQueryParametersAreNormalizedOntoTheEndpointReference(): void
    {
        $transport = new FakeHttpTransport(
            static fn(array $request): HttpTransportResponse => new HttpTransportResponse(200, [], '')
        );
        $gateway = new ApiGateway($transport);

        $gateway->send([
            'endpoint_ref' => 'https://api.example.com/widgets?existing=1',
            'method' => 'GET',
            'query' => ['page' => '2'],
        ]);

        $this->assertSame('https://api.example.com/widgets?existing=1&page=2', $transport->requests[0]['url']);
    }

    public function testTransportExceptionResultsInTransportFailed(): void
    {
        $transport = new FakeHttpTransport(static function (array $request): HttpTransportResponse {
            throw new RuntimeException('connection refused');
        });
        $gateway = new ApiGateway($transport);

        $result = $gateway->send(['endpoint_ref' => 'https://api.example.com/widgets', 'method' => 'GET']);

        $this->assertSame('Transport Failed', $result['transport_status']);
        $this->assertSame('connection refused', $result['error']);
        $this->assertNull($result['status_code']);
    }

    public function testTimeoutSecondsIsForwardedToTheTransport(): void
    {
        $transport = new FakeHttpTransport(
            static fn(array $request): HttpTransportResponse => new HttpTransportResponse(204, [], '')
        );
        $gateway = new ApiGateway($transport);

        $gateway->send([
            'endpoint_ref' => 'https://api.example.com/widgets',
            'method' => 'GET',
            'timeout_seconds' => 12.5,
        ]);

        $this->assertSame(12.5, $transport->requests[0]['timeout']);
    }

    public function testEventPayloadNeverExposesHeadersOrBody(): void
    {
        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];

        foreach (['api_gateway.ready', 'api_gateway.sent', 'api_gateway.response_received', 'api_gateway.normalized'] as $eventName) {
            $events->listen($eventName, new CallbackEventListener(
                function (EventInterface $event) use (&$captured): void {
                    $captured[] = $event;
                }
            ));
        }

        $transport = new FakeHttpTransport(
            static fn(array $request): HttpTransportResponse => new HttpTransportResponse(200, ['authorization' => 'secret-header'], 'secret-body')
        );
        $gateway = new ApiGateway($transport, $events);

        $gateway->send([
            'endpoint_ref' => 'https://api.example.com/widgets',
            'method' => 'GET',
            'headers' => ['authorization' => 'Bearer super-secret-token'],
        ]);

        $this->assertCount(4, $captured);

        foreach ($captured as $event) {
            $payload = $event->getPayload();
            $this->assertArrayNotHasKey('headers', $payload);
            $this->assertArrayNotHasKey('body', $payload);
            $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
            $this->assertStringNotContainsString('super-secret-token', $encoded);
            $this->assertStringNotContainsString('secret-body', $encoded);
        }
    }

    public function testWorksWithoutAnEventBus(): void
    {
        $gateway = new ApiGateway(null, null);

        $result = $gateway->send(['endpoint_ref' => 'https://api.example.com/widgets', 'method' => 'GET']);

        $this->assertSame('Ready', $result['transport_status']);
    }
}
