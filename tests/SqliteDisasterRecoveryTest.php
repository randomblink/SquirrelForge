<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;
use SquirrelForge\Resilience\SqliteDisasterRecovery;

final class SqliteDisasterRecoveryTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        $this->databasePath = sys_get_temp_dir() . '/squirrelforge-disaster-recovery-' . bin2hex(random_bytes(8)) . '.sqlite';
    }

    protected function tearDown(): void
    {
        foreach ([$this->databasePath, $this->databasePath . '-shm', $this->databasePath . '-wal'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    private function recovery(): SqliteDisasterRecovery
    {
        return new SqliteDisasterRecovery($this->databasePath);
    }

    public function testDeclareRejectsAnUnrecognizedDisasterClassification(): void
    {
        $recovery = $this->recovery();

        $result = $recovery->declare('astrology', ['service_a' => 'core_platform_services']);

        $this->assertNull($result['disaster_recovery_id']);
        $this->assertNotNull($result['error']);
    }

    public function testDeclareRejectsAnEmptyServiceList(): void
    {
        $recovery = $this->recovery();

        $result = $recovery->declare('regional_outage', []);

        $this->assertNull($result['disaster_recovery_id']);
    }

    public function testDeclareRejectsAnUnrecognizedPriorityTier(): void
    {
        $recovery = $this->recovery();

        $result = $recovery->declare('regional_outage', ['service_a' => 'astrology']);

        $this->assertNull($result['disaster_recovery_id']);
    }

    public function testDeclareOrdersTheRestorationScheduleByRealRecoveryPriority(): void
    {
        $recovery = $this->recovery();

        $result = $recovery->declare('regional_outage', [
            'observability_service' => 'observability',
            'auth_service' => 'identity_and_authentication',
            'governance_service' => 'governance_systems',
            'workflow_service' => 'workflow_execution',
        ]);

        $this->assertSame(
            ['governance_service', 'auth_service', 'workflow_service', 'observability_service'],
            $result['restoration_schedule']
        );
    }

    public function testRestoreServiceRejectsAnUndeclaredDisaster(): void
    {
        $recovery = $this->recovery();

        $result = $recovery->restoreService('unknown', 'service_a', static function (): void {
        }, static fn(): bool => true);

        $this->assertSame('not_found', $result['outcome']);
    }

    public function testRestoreServiceRejectsAServiceNotPartOfTheDisaster(): void
    {
        $recovery = $this->recovery();
        $declared = $recovery->declare('regional_outage', ['service_a' => 'core_platform_services']);

        $result = $recovery->restoreService($declared['disaster_recovery_id'], 'service_z', static function (): void {
        }, static fn(): bool => true);

        $this->assertSame('unknown_service', $result['outcome']);
    }

    public function testRestoreServiceFailsWhenTheActionThrows(): void
    {
        $recovery = $this->recovery();
        $declared = $recovery->declare('regional_outage', ['service_a' => 'core_platform_services']);

        $result = $recovery->restoreService($declared['disaster_recovery_id'], 'service_a', static function (): void {
            throw new \RuntimeException('restore failed');
        }, static fn(): bool => true);

        $this->assertSame('failed', $result['outcome']);
    }

    public function testRestoreServiceBlocksResumptionWithoutSuccessfulVerification(): void
    {
        $recovery = $this->recovery();
        $declared = $recovery->declare('regional_outage', ['service_a' => 'core_platform_services']);

        $result = $recovery->restoreService($declared['disaster_recovery_id'], 'service_a', static function (): void {
        }, static fn(): bool => false);

        $this->assertSame('failed_verification', $result['outcome']);
        $this->assertSame('Declared', $recovery->get($declared['disaster_recovery_id'])['recovery_phase']);
    }

    public function testRestoringAllServicesResolvesTheDisaster(): void
    {
        $recovery = $this->recovery();
        $declared = $recovery->declare('regional_outage', [
            'service_a' => 'core_platform_services',
            'service_b' => 'observability',
        ]);

        $first = $recovery->restoreService($declared['disaster_recovery_id'], 'service_a', static function (): void {
        }, static fn(): bool => true);
        $second = $recovery->restoreService($declared['disaster_recovery_id'], 'service_b', static function (): void {
        }, static fn(): bool => true);

        $this->assertSame('Restoring', $first['recovery_phase']);
        $this->assertSame('Resolved', $second['recovery_phase']);
        $this->assertSame('Resolved', $recovery->get($declared['disaster_recovery_id'])['recovery_phase']);
    }

    public function testRestoreServiceRejectsFurtherCallsOnAnAlreadyResolvedDisaster(): void
    {
        $recovery = $this->recovery();
        $declared = $recovery->declare('regional_outage', ['service_a' => 'core_platform_services']);
        $recovery->restoreService($declared['disaster_recovery_id'], 'service_a', static function (): void {
        }, static fn(): bool => true);

        $result = $recovery->restoreService($declared['disaster_recovery_id'], 'service_a', static function (): void {
        }, static fn(): bool => true);

        $this->assertSame('already_resolved', $result['outcome']);
    }

    public function testDeclareDispatchesAnEvent(): void
    {
        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        $events->listen('disaster_recovery.declared', new CallbackEventListener(
            function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }
        ));

        $recovery = new SqliteDisasterRecovery($this->databasePath, $events);
        $recovery->declare('regional_outage', ['service_a' => 'core_platform_services']);

        $this->assertCount(1, $captured);
    }
}
