<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;
use SquirrelForge\Resilience\SqliteRedundancyManager;

final class SqliteRedundancyManagerTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        $this->databasePath = sys_get_temp_dir() . '/squirrelforge-redundancy-manager-' . bin2hex(random_bytes(8)) . '.sqlite';
    }

    protected function tearDown(): void
    {
        foreach ([$this->databasePath, $this->databasePath . '-shm', $this->databasePath . '-wal'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    private function manager(): SqliteRedundancyManager
    {
        return new SqliteRedundancyManager($this->databasePath);
    }

    public function testRegisterRejectsAnUnsupportedRedundancyType(): void
    {
        $manager = $this->manager();

        $result = $manager->register('resource_1', 'astrology');

        $this->assertSame('invalid_type', $result['outcome']);
    }

    public function testRegisterIsIdempotent(): void
    {
        $manager = $this->manager();

        $first = $manager->register('resource_1', 'service');
        $second = $manager->register('resource_1', 'service');

        $this->assertSame('registered', $first['outcome']);
        $this->assertSame('already_registered', $second['outcome']);
    }

    public function testRegisterDefaultsToStandby(): void
    {
        $manager = $this->manager();
        $manager->register('resource_1', 'service');

        $resource = $manager->get('resource_1');

        $this->assertSame('Standby', $resource['state']);
        $this->assertSame(0, (int) $resource['synchronized']);
    }

    public function testSynchronizationMovesAStandbyResourceToReady(): void
    {
        $manager = $this->manager();
        $manager->register('resource_1', 'service');

        $result = $manager->updateSynchronization('resource_1', true);

        $this->assertSame('synchronized', $result['outcome']);
        $this->assertSame('Ready', $result['state']);
    }

    public function testDesynchronizationMovesAReadyResourceToSynchronizing(): void
    {
        $manager = $this->manager();
        $manager->register('resource_1', 'service');
        $manager->updateSynchronization('resource_1', true);

        $result = $manager->updateSynchronization('resource_1', false);

        $this->assertSame('desynchronized', $result['outcome']);
        $this->assertSame('Synchronizing', $result['state']);
    }

    public function testSynchronizationOnAnUnregisteredResourceIsRejected(): void
    {
        $manager = $this->manager();

        $result = $manager->updateSynchronization('resource_1', true);

        $this->assertSame('not_registered', $result['outcome']);
    }

    public function testUnhealthyResourceIsMarkedUnavailableAndDesynchronized(): void
    {
        $manager = $this->manager();
        $manager->register('resource_1', 'service');
        $manager->updateSynchronization('resource_1', true);

        $result = $manager->recordHealth('resource_1', false);

        $this->assertSame('marked_unavailable', $result['outcome']);
        $this->assertSame('Unavailable', $result['state']);
        $this->assertSame(0, (int) $manager->get('resource_1')['synchronized']);
    }

    public function testRecoveringFromUnavailableRequiresResynchronization(): void
    {
        $manager = $this->manager();
        $manager->register('resource_1', 'service');
        $manager->updateSynchronization('resource_1', true);
        $manager->recordHealth('resource_1', false);

        $result = $manager->recordHealth('resource_1', true);

        $this->assertSame('healthy', $result['outcome']);
        $this->assertSame('Recovering', $result['state']);
    }

    public function testRetireIsTerminalAndBlocksFurtherUpdates(): void
    {
        $manager = $this->manager();
        $manager->register('resource_1', 'service');
        $manager->retire('resource_1');

        $result = $manager->updateSynchronization('resource_1', true);

        $this->assertSame('blocked_retired', $result['outcome']);
        $this->assertSame('Retired', $manager->get('resource_1')['state']);
    }

    public function testRetiringAnAlreadyRetiredResourceIsANoOp(): void
    {
        $manager = $this->manager();
        $manager->register('resource_1', 'service');
        $manager->retire('resource_1');

        $result = $manager->retire('resource_1');

        $this->assertSame('already_retired', $result['outcome']);
    }

    public function testProvideVerifiedResourceNeverReturnsAnUnsynchronizedResource(): void
    {
        $manager = $this->manager();
        $manager->register('resource_1', 'service');

        $result = $manager->provideVerifiedResource('service');

        $this->assertFalse($result['found']);
    }

    public function testProvideVerifiedResourceReturnsAReadySynchronizedResource(): void
    {
        $manager = $this->manager();
        $manager->register('resource_1', 'service');
        $manager->updateSynchronization('resource_1', true);

        $result = $manager->provideVerifiedResource('service');

        $this->assertTrue($result['found']);
        $this->assertSame('resource_1', $result['resource_id']);
    }

    public function testProvideVerifiedResourceExcludesDegradedResourcesEvenIfPreviouslySynchronized(): void
    {
        $manager = $this->manager();
        $manager->register('resource_1', 'service');
        $manager->updateSynchronization('resource_1', true);
        $manager->recordHealth('resource_1', false);

        $result = $manager->provideVerifiedResource('service');

        $this->assertFalse($result['found']);
    }

    public function testListFiltersByRedundancyType(): void
    {
        $manager = $this->manager();
        $manager->register('resource_1', 'service');
        $manager->register('resource_2', 'storage');

        $this->assertCount(1, $manager->list('service'));
        $this->assertCount(2, $manager->list());
    }

    public function testRegisterDispatchesAnEvent(): void
    {
        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        $events->listen('redundancy.registered', new CallbackEventListener(
            function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }
        ));

        $manager = new SqliteRedundancyManager($this->databasePath, $events);
        $manager->register('resource_1', 'service');

        $this->assertCount(1, $captured);
    }
}
