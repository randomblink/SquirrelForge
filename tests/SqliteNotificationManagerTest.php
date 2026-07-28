<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Communication\NotificationChannelRegistry;
use SquirrelForge\Communication\SqliteNotificationManager;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;
use SquirrelForge\Tests\Support\FakeNotificationChannel;

final class SqliteNotificationManagerTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        $this->databasePath = sys_get_temp_dir() . '/squirrelforge-notifications-' . bin2hex(random_bytes(8)) . '.sqlite';
    }

    protected function tearDown(): void
    {
        foreach ([$this->databasePath, $this->databasePath . '-shm', $this->databasePath . '-wal'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testFirstAttemptSuccessRecordsDeliveredAndDispatchesEvent(): void
    {
        $channels = new NotificationChannelRegistry();
        $channels->register(new FakeNotificationChannel('test', [['delivered' => true]]));

        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        $events->listen('notification.delivered', new CallbackEventListener(
            function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }
        ));

        $manager = new SqliteNotificationManager($this->databasePath, $channels, $events);
        $result = $manager->send('user_1', 'test', 'normal', ['body' => 'hi']);

        $this->assertSame('delivered', $result['status']);
        $this->assertSame(1, $result['attempts']);
        $this->assertNull($result['error']);
        $this->assertCount(1, $captured);
        $this->assertSame('user_1', $captured[0]->getPayload()['recipient']);
    }

    public function testRetriesThenSucceeds(): void
    {
        $channels = new NotificationChannelRegistry();
        $fakeChannel = new FakeNotificationChannel('test', [
            ['delivered' => false, 'error' => 'temporary failure'],
            ['delivered' => true],
        ]);
        $channels->register($fakeChannel);

        $manager = new SqliteNotificationManager($this->databasePath, $channels);
        $result = $manager->send('user_1', 'test', 'high', [], maxAttempts: 3);

        $this->assertSame('delivered', $result['status']);
        $this->assertSame(2, $result['attempts']);
        $this->assertCount(2, $fakeChannel->calls);
    }

    public function testExhaustingRetriesRecordsFailedAndDispatchesFailureEvent(): void
    {
        $channels = new NotificationChannelRegistry();
        $channels->register(new FakeNotificationChannel('test', [
            ['delivered' => false, 'error' => 'down'],
        ]));

        $events = new EventBus();
        $captured = [];
        $events->listen('notification.failed', new CallbackEventListener(
            function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }
        ));

        $manager = new SqliteNotificationManager($this->databasePath, $channels, $events);
        $result = $manager->send('user_1', 'test', 'critical', [], maxAttempts: 2);

        $this->assertSame('failed', $result['status']);
        $this->assertSame(2, $result['attempts']);
        $this->assertSame('down', $result['error']);
        $this->assertCount(1, $captured);

        $status = $manager->status($result['notification_ref']);
        $this->assertSame('failed', $status['status']);
    }

    public function testUnknownChannelIsRejectedWithoutThrowing(): void
    {
        $manager = new SqliteNotificationManager($this->databasePath, new NotificationChannelRegistry());

        $result = $manager->send('user_1', 'does-not-exist', 'normal', []);

        $this->assertSame('rejected', $result['status']);
        $this->assertNull($result['notification_ref']);
    }

    public function testInvalidPriorityIsRejectedWithoutThrowing(): void
    {
        $channels = new NotificationChannelRegistry();
        $channels->register(new FakeNotificationChannel('test', [['delivered' => true]]));

        $manager = new SqliteNotificationManager($this->databasePath, $channels);
        $result = $manager->send('user_1', 'test', 'urgent-ish', []);

        $this->assertSame('rejected', $result['status']);
        $this->assertStringContainsString('urgent-ish', $result['error']);
    }

    public function testHistoryReturnsMostRecentFirstUpToLimit(): void
    {
        $channels = new NotificationChannelRegistry();
        $channels->register(new FakeNotificationChannel('test', [['delivered' => true]]));

        $manager = new SqliteNotificationManager($this->databasePath, $channels);
        $manager->send('user_1', 'test', 'low', []);
        $manager->send('user_2', 'test', 'low', []);
        $manager->send('user_3', 'test', 'low', []);

        $this->assertCount(2, $manager->history(2));
        $this->assertCount(3, $manager->history(10));
    }
}
