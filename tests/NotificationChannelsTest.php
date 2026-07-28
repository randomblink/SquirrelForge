<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SquirrelForge\Communication\InPlatformNotificationChannel;
use SquirrelForge\Communication\WebhookNotificationChannel;
use SquirrelForge\Integration\Http\HttpTransportResponse;
use SquirrelForge\Tests\Support\FakeHttpTransport;

final class NotificationChannelsTest extends TestCase
{
    public function testInPlatformChannelDeliversAndRecordsNotification(): void
    {
        $channel = new InPlatformNotificationChannel();

        $result = $channel->send(['recipient' => 'user_1', 'body' => 'hello']);

        $this->assertSame(['delivered' => true], $result);
        $this->assertCount(1, $channel->deliveries());
        $this->assertSame('user_1', $channel->deliveries()[0]['recipient']);
    }

    public function testInPlatformChannelRejectsMissingRecipient(): void
    {
        $channel = new InPlatformNotificationChannel();

        $result = $channel->send(['body' => 'hello']);

        $this->assertFalse($result['delivered']);
        $this->assertArrayHasKey('error', $result);
        $this->assertSame([], $channel->deliveries());
    }

    public function testWebhookChannelDeliversOnSuccessResponse(): void
    {
        $transport = new FakeHttpTransport(
            static fn(array $request): HttpTransportResponse => new HttpTransportResponse(200, [], '{}')
        );
        $channel = new WebhookNotificationChannel($transport, 'https://example.test/hook');

        $result = $channel->send(['recipient' => 'user_1', 'body' => 'hello']);

        $this->assertSame(['delivered' => true], $result);
        $this->assertSame('https://example.test/hook', $transport->requests[0]['url']);
        $this->assertJson($transport->requests[0]['body']);
    }

    public function testWebhookChannelReportsFailureOnNonSuccessStatus(): void
    {
        $transport = new FakeHttpTransport(
            static fn(array $request): HttpTransportResponse => new HttpTransportResponse(500, [], 'oops')
        );
        $channel = new WebhookNotificationChannel($transport, 'https://example.test/hook');

        $result = $channel->send(['recipient' => 'user_1']);

        $this->assertFalse($result['delivered']);
        $this->assertStringContainsString('500', $result['error']);
    }

    public function testWebhookChannelReportsFailureWithoutThrowingOnTransportException(): void
    {
        $transport = new FakeHttpTransport(static function (): never {
            throw new RuntimeException('connection refused');
        });
        $channel = new WebhookNotificationChannel($transport, 'https://example.test/hook');

        $result = $channel->send(['recipient' => 'user_1']);

        $this->assertFalse($result['delivered']);
        $this->assertSame('connection refused', $result['error']);
    }
}
