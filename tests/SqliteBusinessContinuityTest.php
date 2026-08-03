<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;
use SquirrelForge\Resilience\SqliteBusinessContinuity;

final class SqliteBusinessContinuityTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        $this->databasePath = sys_get_temp_dir() . '/squirrelforge-business-continuity-' . bin2hex(random_bytes(8)) . '.sqlite';
    }

    protected function tearDown(): void
    {
        foreach ([$this->databasePath, $this->databasePath . '-shm', $this->databasePath . '-wal'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    private function continuity(): SqliteBusinessContinuity
    {
        return new SqliteBusinessContinuity($this->databasePath);
    }

    /**
     * @return array{recovery_verified: bool, platform_stability_confirmed: bool, security_controls_validated: bool, service_integrity_confirmed: bool, governance_approval_obtained: bool, resource_utilization_normalized: bool, monitoring_confirms_stable_operation: bool}
     */
    private function completeResumptionEvidence(): array
    {
        return [
            'recovery_verified' => true,
            'platform_stability_confirmed' => true,
            'security_controls_validated' => true,
            'service_integrity_confirmed' => true,
            'governance_approval_obtained' => true,
            'resource_utilization_normalized' => true,
            'monitoring_confirms_stable_operation' => true,
        ];
    }

    public function testActivateRejectsAnUnsupportedOperatingMode(): void
    {
        $continuity = $this->continuity();

        $result = $continuity->activate('astrology', [], 'graceful_degradation', ['security_controls_active' => true]);

        $this->assertSame('invalid_operating_mode', $result['outcome']);
    }

    public function testActivateRejectsAnUnsupportedStrategy(): void
    {
        $continuity = $this->continuity();

        $result = $continuity->activate('reduced_capacity', [], 'astrology', ['security_controls_active' => true]);

        $this->assertSame('invalid_strategy', $result['outcome']);
    }

    public function testActivateRejectsAnUnrecognizedPriorityTier(): void
    {
        $continuity = $this->continuity();

        $result = $continuity->activate('reduced_capacity', ['service_a' => 'astrology'], 'graceful_degradation', ['security_controls_active' => true]);

        $this->assertSame('invalid_priority_tier', $result['outcome']);
    }

    public function testActivateRejectsWhenSecurityControlsAreNotConfirmedActive(): void
    {
        $continuity = $this->continuity();

        $result = $continuity->activate('reduced_capacity', ['service_a' => 'security'], 'graceful_degradation', ['security_controls_active' => false]);

        $this->assertSame('blocked_security_controls_inactive', $result['outcome']);
    }

    public function testActivateOrdersResourceAllocationByRealContinuityPriority(): void
    {
        $continuity = $this->continuity();

        $result = $continuity->activate('essential_services_only', [
            'observability_service' => 'observability',
            'auth_service' => 'identity_and_authentication',
            'governance_service' => 'governance',
            'automation_service' => 'automation',
        ], 'service_prioritization', ['security_controls_active' => true]);

        $this->assertSame('activated', $result['outcome']);
        $this->assertSame(
            ['governance_service', 'auth_service', 'automation_service', 'observability_service'],
            $result['resource_allocation']
        );
    }

    public function testActivateIsRecorded(): void
    {
        $continuity = $this->continuity();

        $result = $continuity->activate('emergency_operation', ['service_a' => 'security'], 'graceful_degradation', ['security_controls_active' => true]);
        $record = $continuity->get($result['continuity_operation_id']);

        $this->assertSame('activated', $record['final_outcome']);
        $this->assertSame('emergency_operation', $record['operating_mode']);
        $this->assertSame('ungoverned', $record['governance_status']);
        $this->assertSame(['service_a'], $record['critical_services']);
    }

    public function testResumeNormalOperationsBlocksWhenAnyRequirementIsUnmet(): void
    {
        $continuity = $this->continuity();
        $evidence = $this->completeResumptionEvidence();
        $evidence['security_controls_validated'] = false;

        $result = $continuity->resumeNormalOperations($evidence);

        $this->assertSame('blocked', $result['outcome']);
        $this->assertNotNull($result['error']);
    }

    public function testResumeNormalOperationsSucceedsWhenEveryRequirementIsMet(): void
    {
        $continuity = $this->continuity();

        $result = $continuity->resumeNormalOperations($this->completeResumptionEvidence());

        $this->assertSame('resumed', $result['outcome']);
        $this->assertNull($result['error']);
    }

    public function testResumeNormalOperationsWithNoEvidenceIsBlocked(): void
    {
        $continuity = $this->continuity();

        $result = $continuity->resumeNormalOperations([]);

        $this->assertSame('blocked', $result['outcome']);
    }

    public function testActivateDispatchesAnEvent(): void
    {
        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        $events->listen('business_continuity.activated', new CallbackEventListener(
            function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }
        ));

        $continuity = new SqliteBusinessContinuity($this->databasePath, $events);
        $continuity->activate('reduced_capacity', ['service_a' => 'security'], 'graceful_degradation', ['security_controls_active' => true]);

        $this->assertCount(1, $captured);
    }
}
