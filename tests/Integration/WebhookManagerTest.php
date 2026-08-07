<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Integration;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;
use SquirrelForge\Integration\ApiGateway;
use SquirrelForge\Integration\Http\HttpTransportResponse;
use SquirrelForge\Integration\IntegrationAuthentication;
use SquirrelForge\Integration\WebhookManager;
use SquirrelForge\Tests\Support\FakeHttpTransport;

final class WebhookManagerTest extends TestCase
{
    // --- Inbound ---

    public function testMissingRawBodyIsRejected(): void
    {
        $manager = new WebhookManager();

        $result = $manager->receiveInbound(['provider_ref' => 'stripe']);

        $this->assertSame('Rejected', $result['webhook_status']);
        $this->assertStringContainsString('raw_body', $result['error']);
    }

    public function testMalformedJsonIsRejected(): void
    {
        $manager = new WebhookManager();

        $result = $manager->receiveInbound(['provider_ref' => 'stripe', 'raw_body' => '{not json']);

        $this->assertSame('Rejected', $result['webhook_status']);
        $this->assertStringContainsString('JSON', $result['error']);
    }

    public function testMissingSignatureWhenSecretConfiguredIsRejected(): void
    {
        $manager = new WebhookManager();

        $result = $manager->receiveInbound([
            'provider_ref' => 'stripe',
            'raw_body' => '{"id":"evt_1"}',
            'signing_secret' => 'whsec_abc',
        ]);

        $this->assertSame('Rejected', $result['webhook_status']);
        $this->assertStringContainsString('signature', $result['error']);
    }

    public function testMismatchedSignatureIsRejected(): void
    {
        $manager = new WebhookManager();

        $result = $manager->receiveInbound([
            'provider_ref' => 'stripe',
            'raw_body' => '{"id":"evt_1"}',
            'signing_secret' => 'whsec_abc',
            'signature' => 'deadbeef',
        ]);

        $this->assertSame('Rejected', $result['webhook_status']);
        $this->assertStringContainsString('does not match', $result['error']);
    }

    public function testValidHmacSignatureIsAccepted(): void
    {
        $manager = new WebhookManager();
        $rawBody = '{"id":"evt_1"}';
        $signature = hash_hmac('sha256', $rawBody, 'whsec_abc');

        $result = $manager->receiveInbound([
            'provider_ref' => 'stripe',
            'raw_body' => $rawBody,
            'signing_secret' => 'whsec_abc',
            'signature' => $signature,
        ]);

        $this->assertSame('Accepted', $result['webhook_status']);
        $this->assertSame(['id' => 'evt_1'], $result['payload']);
    }

    public function testPrefixedSignatureConventionIsAccepted(): void
    {
        $manager = new WebhookManager();
        $rawBody = '{"id":"evt_1"}';
        $signature = 'sha256=' . hash_hmac('sha256', $rawBody, 'whsec_abc');

        $result = $manager->receiveInbound([
            'provider_ref' => 'stripe',
            'raw_body' => $rawBody,
            'signing_secret' => 'whsec_abc',
            'signature' => $signature,
        ]);

        $this->assertSame('Accepted', $result['webhook_status']);
    }

    public function testNoSecretConfiguredSkipsSignatureCheck(): void
    {
        $manager = new WebhookManager();

        $result = $manager->receiveInbound([
            'provider_ref' => 'internal',
            'raw_body' => '{"id":"evt_1"}',
        ]);

        $this->assertSame('Accepted', $result['webhook_status']);
    }

    public function testStaleTimestampIsRejected(): void
    {
        $manager = new WebhookManager();

        $result = $manager->receiveInbound([
            'provider_ref' => 'stripe',
            'raw_body' => '{"id":"evt_1"}',
            'timestamp' => (string) (time() - 10000),
            'timestamp_tolerance_seconds' => 300,
        ]);

        $this->assertSame('Rejected', $result['webhook_status']);
        $this->assertStringContainsString('tolerance', $result['error']);
    }

    public function testTimestampWithinToleranceIsAccepted(): void
    {
        $manager = new WebhookManager();

        $result = $manager->receiveInbound([
            'provider_ref' => 'stripe',
            'raw_body' => '{"id":"evt_1"}',
            'timestamp' => (string) (time() - 5),
            'timestamp_tolerance_seconds' => 300,
        ]);

        $this->assertSame('Accepted', $result['webhook_status']);
    }

    public function testReplayCheckRejectsAlreadySeenNonce(): void
    {
        $manager = new WebhookManager();

        $result = $manager->receiveInbound(
            ['provider_ref' => 'stripe', 'raw_body' => '{"id":"evt_1"}', 'nonce_ref' => 'evt_1'],
            replayCheck: static fn(string $nonceRef): bool => true
        );

        $this->assertSame('Rejected', $result['webhook_status']);
        $this->assertStringContainsString('replay', $result['error']);
    }

    public function testAcceptedWithoutDispatchNeverRoutesIndependently(): void
    {
        $manager = new WebhookManager();
        $dispatched = false;

        $result = $manager->receiveInbound(['provider_ref' => 'stripe', 'raw_body' => '{"id":"evt_1"}']);

        $this->assertSame('Accepted', $result['webhook_status']);
        $this->assertFalse($dispatched);
        $this->assertNotNull($result['event_ref']);
    }

    public function testDispatchClosureReceivesTheEventReference(): void
    {
        $manager = new WebhookManager();
        $seen = null;

        $result = $manager->receiveInbound(
            ['provider_ref' => 'stripe', 'raw_body' => '{"id":"evt_1"}'],
            dispatch: function (array $event) use (&$seen): void {
                $seen = $event;
            }
        );

        $this->assertSame('Dispatched', $result['webhook_status']);
        $this->assertSame($result['event_ref'], $seen['event_ref']);
        $this->assertSame(['id' => 'evt_1'], $seen['payload']);
    }

    public function testInboundEventPayloadNeverExposesRawBodyOrSecret(): void
    {
        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        foreach (['webhook_manager.received', 'webhook_manager.accepted'] as $eventName) {
            $events->listen($eventName, new CallbackEventListener(function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }));
        }

        $manager = new WebhookManager(events: $events);
        $rawBody = '{"secret_field":"super-secret-value"}';
        $signature = hash_hmac('sha256', $rawBody, 'whsec_abc');

        $manager->receiveInbound([
            'provider_ref' => 'stripe',
            'raw_body' => $rawBody,
            'signing_secret' => 'whsec_abc',
            'signature' => $signature,
        ]);

        $this->assertCount(2, $captured);

        foreach ($captured as $event) {
            $encoded = json_encode($event->getPayload(), JSON_THROW_ON_ERROR);
            $this->assertStringNotContainsString('super-secret-value', $encoded);
            $this->assertStringNotContainsString('whsec_abc', $encoded);
        }
    }

    // --- Outbound ---

    public function testOutboundMissingProviderOrEndpointIsDeliveryFailed(): void
    {
        $manager = new WebhookManager();

        $result = $manager->deliverOutbound(['body' => '{}']);

        $this->assertSame('Delivery Failed', $result['webhook_status']);
        $this->assertStringContainsString('provider_ref', $result['error']);
    }

    public function testOutboundMissingBodyAndTranslateIsDeliveryFailed(): void
    {
        $manager = new WebhookManager();

        $result = $manager->deliverOutbound(['provider_ref' => 'slack', 'endpoint_ref' => 'https://hooks.example.com/x']);

        $this->assertSame('Delivery Failed', $result['webhook_status']);
        $this->assertStringContainsString('body', $result['error']);
    }

    public function testOutboundTranslateClosureProducesTheBody(): void
    {
        $transport = new FakeHttpTransport(
            static fn(array $request): HttpTransportResponse => new HttpTransportResponse(200, [], 'ok')
        );
        $manager = new WebhookManager(new ApiGateway($transport));

        $result = $manager->deliverOutbound(
            ['provider_ref' => 'slack', 'endpoint_ref' => 'https://hooks.example.com/x', 'event' => ['text' => 'hi']],
            translate: static fn(array $event): array => ['body' => json_encode($event), 'headers' => ['content-type' => 'application/json']]
        );

        $this->assertSame('Delivered', $result['webhook_status']);
        $this->assertSame('{"text":"hi"}', $transport->requests[0]['body']);
        $this->assertSame('application/json', $transport->requests[0]['headers']['content-type']);
    }

    public function testOutboundSigningWithoutAuthenticationComponentIsDeliveryFailed(): void
    {
        $manager = new WebhookManager();

        $result = $manager->deliverOutbound([
            'provider_ref' => 'slack',
            'endpoint_ref' => 'https://hooks.example.com/x',
            'body' => '{}',
            'credential_ref' => 'cred_1',
        ]);

        $this->assertSame('Delivery Failed', $result['webhook_status']);
        $this->assertStringContainsString('IntegrationAuthentication', $result['error']);
    }

    public function testOutboundInvalidSigningIsDeliveryFailed(): void
    {
        $manager = new WebhookManager(null, new IntegrationAuthentication());

        $result = $manager->deliverOutbound(
            ['provider_ref' => 'slack', 'endpoint_ref' => 'https://hooks.example.com/x', 'body' => '{}', 'credential_ref' => 'cred_1'],
            signHandshake: static fn(array $refs): array => ['token' => null, 'expires_at' => null, 'error' => 'invalid_client']
        );

        $this->assertSame('Delivery Failed', $result['webhook_status']);
        $this->assertSame('invalid_client', $result['error']);
    }

    public function testOutboundValidSigningAttachesAuthorizationHeader(): void
    {
        $transport = new FakeHttpTransport(
            static fn(array $request): HttpTransportResponse => new HttpTransportResponse(200, [], 'ok')
        );
        $manager = new WebhookManager(new ApiGateway($transport), new IntegrationAuthentication());

        $manager->deliverOutbound(
            ['provider_ref' => 'slack', 'endpoint_ref' => 'https://hooks.example.com/x', 'body' => '{}', 'credential_ref' => 'cred_1'],
            signHandshake: static fn(array $refs): array => ['token' => 'tok_abc', 'expires_at' => null, 'error' => null]
        );

        $this->assertSame('Bearer tok_abc', $transport->requests[0]['headers']['Authorization']);
    }

    public function testOutboundWithoutAnApiGatewayIsADryRun(): void
    {
        $manager = new WebhookManager();

        $result = $manager->deliverOutbound(['provider_ref' => 'slack', 'endpoint_ref' => 'https://hooks.example.com/x', 'body' => '{}']);

        $this->assertSame('Delivery Submitted', $result['webhook_status']);
        $this->assertNull($result['error']);
    }

    public function test2xxResponseIsDelivered(): void
    {
        $transport = new FakeHttpTransport(
            static fn(array $request): HttpTransportResponse => new HttpTransportResponse(204, [], '')
        );
        $manager = new WebhookManager(new ApiGateway($transport));

        $result = $manager->deliverOutbound(['provider_ref' => 'slack', 'endpoint_ref' => 'https://hooks.example.com/x', 'body' => '{}']);

        $this->assertSame('Delivered', $result['webhook_status']);
        $this->assertSame(204, $result['status_code']);
    }

    public function testNon2xxResponseIsDeliveryFailed(): void
    {
        $transport = new FakeHttpTransport(
            static fn(array $request): HttpTransportResponse => new HttpTransportResponse(500, [], 'error')
        );
        $manager = new WebhookManager(new ApiGateway($transport));

        $result = $manager->deliverOutbound(['provider_ref' => 'slack', 'endpoint_ref' => 'https://hooks.example.com/x', 'body' => '{}']);

        $this->assertSame('Delivery Failed', $result['webhook_status']);
        $this->assertSame(500, $result['status_code']);
    }

    public function testTransportFailureIsDeliveryFailed(): void
    {
        $transport = new FakeHttpTransport(static function (array $request): HttpTransportResponse {
            throw new \RuntimeException('connection refused');
        });
        $manager = new WebhookManager(new ApiGateway($transport));

        $result = $manager->deliverOutbound(['provider_ref' => 'slack', 'endpoint_ref' => 'https://hooks.example.com/x', 'body' => '{}']);

        $this->assertSame('Delivery Failed', $result['webhook_status']);
        $this->assertSame('connection refused', $result['error']);
    }

    public function testWorksWithoutAnEventBus(): void
    {
        $manager = new WebhookManager();

        $result = $manager->receiveInbound(['provider_ref' => 'stripe', 'raw_body' => '{"id":"evt_1"}']);

        $this->assertSame('Accepted', $result['webhook_status']);
    }
}
