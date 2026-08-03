<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;
use SquirrelForge\Resilience\SqliteFailoverCoordinator;
use SquirrelForge\Resilience\SqliteRedundancyManager;

final class SqliteFailoverCoordinatorTest extends TestCase
{
    /** @var array<int, string> */
    private array $databasePaths = [];

    protected function tearDown(): void
    {
        foreach ($this->databasePaths as $path) {
            foreach ([$path, $path . '-shm', $path . '-wal'] as $candidate) {
                if (is_file($candidate)) {
                    unlink($candidate);
                }
            }
        }
    }

    private function tempPath(string $label): string
    {
        $path = sys_get_temp_dir() . "/squirrelforge-failover-coordinator-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    /**
     * @return array{0: SqliteFailoverCoordinator, 1: SqliteRedundancyManager}
     */
    private function coordinatorWithVerifiedStandby(?EventBusInterface $events = null): array
    {
        $redundancy = new SqliteRedundancyManager($this->tempPath('redundancy'));
        $redundancy->register('standby_1', 'service');
        $redundancy->updateSynchronization('standby_1', true);
        $coordinator = new SqliteFailoverCoordinator($this->tempPath('failover'), $redundancy, $events);

        return [$coordinator, $redundancy];
    }

    /**
     * @return array{affected_service: string, failover_type: string, authorized: bool}
     */
    private function request(array $overrides = []): array
    {
        return array_replace([
            'affected_service' => 'checkout_service',
            'failover_type' => 'service',
            'authorized' => true,
            'action' => static function (): void {
            },
        ], $overrides);
    }

    public function testRejectsAnUnauthorizedFailover(): void
    {
        [$coordinator] = $this->coordinatorWithVerifiedStandby();

        $result = $coordinator->failover($this->request(['authorized' => false]));

        $this->assertSame('Failed', $result['state']);
        $this->assertStringContainsString('authorization', $result['error']);
    }

    public function testRejectsAnUnsupportedFailoverType(): void
    {
        [$coordinator] = $this->coordinatorWithVerifiedStandby();

        $result = $coordinator->failover($this->request(['failover_type' => 'astrology']));

        $this->assertSame('Failed', $result['state']);
    }

    public function testRejectsAMissingRequiredField(): void
    {
        [$coordinator] = $this->coordinatorWithVerifiedStandby();

        $result = $coordinator->failover(['affected_service' => 'checkout_service']);

        $this->assertSame('Failed', $result['state']);
        $this->assertNotNull($result['error']);
    }

    public function testFailsWhenNoVerifiedStandbyIsAvailable(): void
    {
        $redundancy = new SqliteRedundancyManager($this->tempPath('redundancy'));
        $coordinator = new SqliteFailoverCoordinator($this->tempPath('failover'), $redundancy);

        $result = $coordinator->failover($this->request());

        $this->assertSame('Failed', $result['state']);
        $this->assertNull($result['standby_resource']);
    }

    public function testNeverActivatesAnUnsynchronizedStandby(): void
    {
        $redundancy = new SqliteRedundancyManager($this->tempPath('redundancy'));
        $redundancy->register('standby_1', 'service');
        $coordinator = new SqliteFailoverCoordinator($this->tempPath('failover'), $redundancy);

        $result = $coordinator->failover($this->request());

        $this->assertSame('Failed', $result['state']);
        $this->assertNull($result['standby_resource']);
    }

    public function testFailsWithoutARedundancyManagerConfigured(): void
    {
        $coordinator = new SqliteFailoverCoordinator($this->tempPath('failover'));

        $result = $coordinator->failover($this->request());

        $this->assertSame('Failed', $result['state']);
    }

    public function testTransitionsToTheVerifiedStandbyAndExecutesTheAction(): void
    {
        [$coordinator] = $this->coordinatorWithVerifiedStandby();
        $receivedResourceId = null;

        $result = $coordinator->failover($this->request(['action' => function (string $resourceId) use (&$receivedResourceId): void {
            $receivedResourceId = $resourceId;
        }]));

        $this->assertSame('Operational', $result['state']);
        $this->assertSame('standby_1', $result['standby_resource']);
        $this->assertSame('standby_1', $receivedResourceId);
    }

    public function testFailsWhenTheActionThrows(): void
    {
        [$coordinator] = $this->coordinatorWithVerifiedStandby();

        $result = $coordinator->failover($this->request(['action' => function (): void {
            throw new \RuntimeException('cutover failed');
        }]));

        $this->assertSame('Failed', $result['state']);
        $this->assertSame('cutover failed', $result['error']);
    }

    public function testVerifierFailureFailsAnOtherwiseSuccessfulFailover(): void
    {
        [$coordinator] = $this->coordinatorWithVerifiedStandby();

        $result = $coordinator->failover($this->request(['verifier' => static fn(): bool => false]));

        $this->assertSame('Failed', $result['state']);
        $this->assertSame('failed', $result['verification_status']);
    }

    public function testVerifierSuccessMarksTheFailoverVerified(): void
    {
        [$coordinator] = $this->coordinatorWithVerifiedStandby();

        $result = $coordinator->failover($this->request(['verifier' => static fn(): bool => true]));

        $this->assertSame('Operational', $result['state']);
        $this->assertSame('verified', $result['verification_status']);
    }

    public function testEveryOperationIsRecordedIncludingRejections(): void
    {
        [$coordinator] = $this->coordinatorWithVerifiedStandby();

        $result = $coordinator->failover($this->request());
        $record = $coordinator->get($result['failover_operation_id']);

        $this->assertSame('transitioned', $record['final_outcome']);
        $this->assertSame('checkout_service', $record['affected_service']);
        $this->assertSame('ungoverned', $record['governance_status']);
    }

    public function testPlanFailbackDefersWhenAnyRequirementIsUnmet(): void
    {
        $coordinator = new SqliteFailoverCoordinator($this->tempPath('failover'));

        $result = $coordinator->planFailback([
            'original_resource_repaired' => true,
            'resynchronized' => true,
            'risk_accepted' => false,
            'governance_approved' => true,
        ]);

        $this->assertSame('deferred', $result['outcome']);
        $this->assertNotNull($result['reason']);
    }

    public function testPlanFailbackApprovesWhenEveryRequirementIsMet(): void
    {
        $coordinator = new SqliteFailoverCoordinator($this->tempPath('failover'));

        $result = $coordinator->planFailback([
            'original_resource_repaired' => true,
            'resynchronized' => true,
            'risk_accepted' => true,
            'governance_approved' => true,
        ]);

        $this->assertSame('approved', $result['outcome']);
    }

    public function testFailoverDispatchesAnEvent(): void
    {
        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        $events->listen('failover.operational', new CallbackEventListener(
            function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }
        ));

        [$coordinator] = $this->coordinatorWithVerifiedStandby($events);
        $coordinator->failover($this->request());

        $this->assertCount(1, $captured);
    }
}
