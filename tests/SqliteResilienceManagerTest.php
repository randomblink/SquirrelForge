<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;
use SquirrelForge\Automation\RuleEngine;
use SquirrelForge\Governance\SqlitePolicyEngine;
use SquirrelForge\Resilience\RetryManager;
use SquirrelForge\Resilience\SqliteBusinessContinuity;
use SquirrelForge\Resilience\SqliteDisasterRecovery;
use SquirrelForge\Resilience\SqliteFailoverCoordinator;
use SquirrelForge\Resilience\SqliteRecoveryManager;
use SquirrelForge\Resilience\SqliteRedundancyManager;
use SquirrelForge\Resilience\SqliteResilienceGovernance;
use SquirrelForge\Resilience\SqliteResilienceManager;
use SquirrelForge\Resilience\SqliteSelfHealingEngine;
use SquirrelForge\Workflow\CallbackStep;

final class SqliteResilienceManagerTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-resilience-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    /**
     * @return array{0: SqliteSelfHealingEngine, 1: SqliteRecoveryManager, 2: SqliteResilienceManager}
     */
    private function makeManagers(?EventBusInterface $events = null): array
    {
        $selfHealing = new SqliteSelfHealingEngine($this->tempPath('healing'), new RetryManager(fn() => null));
        $recovery = new SqliteRecoveryManager($this->tempPath('recovery'), new RetryManager(fn() => null));
        $resilience = new SqliteResilienceManager($this->tempPath('resilience'), $selfHealing, $recovery, events: $events);

        return [$selfHealing, $recovery, $resilience];
    }

    public function testSelfHealingSuccessResolvesTheFailureWithoutAttemptingRecovery(): void
    {
        [$selfHealing, , $resilience] = $this->makeManagers();
        $selfHealing->registerAction('cache_refresh', static fn(): string => 'refreshed');

        $result = $resilience->coordinate(
            ['detection_ref' => 'failure_detection_1', 'severity' => 'warning'],
            ['self_heal_action' => 'cache_refresh', 'recovery_strategy' => 'direct']
        );

        $this->assertSame('completed', $result['outcome']);
        $this->assertSame('self_healing', $result['coordinated_component']);
        $this->assertSame('failure_detection_1', $result['failure_ref']);
        $this->assertCount(1, $result['notes']);
    }

    public function testFailedSelfHealingWithNoRecoveryStrategyEscalates(): void
    {
        [, , $resilience] = $this->makeManagers();

        $result = $resilience->coordinate(
            ['detection_ref' => 'failure_detection_2', 'severity' => 'error'],
            ['self_heal_action' => 'unregistered_action']
        );

        $this->assertSame('escalated', $result['outcome']);
        $this->assertSame('self_healing', $result['coordinated_component']);
        $this->assertStringContainsString('rejected', $result['notes'][0]);
    }

    public function testFailedSelfHealingFallsThroughToRecoveryWhichSucceeds(): void
    {
        [, , $resilience] = $this->makeManagers();

        $result = $resilience->coordinate(
            ['detection_ref' => 'failure_detection_3', 'severity' => 'error'],
            [
                'self_heal_action' => 'unregistered_action',
                'recovery_strategy' => 'direct',
                'recovery_options' => ['action' => static fn(): string => 'recovered'],
            ]
        );

        $this->assertSame('completed', $result['outcome']);
        $this->assertSame('recovery', $result['coordinated_component']);
        $this->assertCount(2, $result['notes']);
        $this->assertStringContainsString('self_healing:', $result['notes'][0]);
        $this->assertStringContainsString('recovery:', $result['notes'][1]);
    }

    public function testRecoveryStrategyAloneCanResolveTheFailure(): void
    {
        [, , $resilience] = $this->makeManagers();

        $result = $resilience->coordinate(
            ['detection_ref' => 'failure_detection_4', 'severity' => 'critical'],
            ['recovery_strategy' => 'direct', 'recovery_options' => ['action' => static fn(): string => 'restarted']]
        );

        $this->assertSame('completed', $result['outcome']);
        $this->assertSame('recovery', $result['coordinated_component']);
    }

    public function testPartialRollbackRecoveryIsReportedAsPartiallyRecovered(): void
    {
        [, , $resilience] = $this->makeManagers();
        $clean = new CallbackStep('step_1', 'Step One', static fn(array $c): array => $c);
        $broken = new CallbackStep(
            'step_2',
            'Step Two',
            static fn(array $c): array => $c,
            static function (): never {
                throw new RuntimeException('cannot undo step_2');
            }
        );

        $result = $resilience->coordinate(
            ['detection_ref' => 'failure_detection_5', 'severity' => 'error'],
            ['recovery_strategy' => 'rollback', 'recovery_options' => ['completed_steps' => [
                ['step' => $clean, 'context' => []],
                ['step' => $broken, 'context' => []],
            ]]]
        );

        $this->assertSame('partially_recovered', $result['outcome']);
        $this->assertSame('recovery', $result['coordinated_component']);
    }

    public function testRecoveryFailureEscalates(): void
    {
        [, , $resilience] = $this->makeManagers();

        $result = $resilience->coordinate(
            ['detection_ref' => 'failure_detection_6', 'severity' => 'critical'],
            [
                'recovery_strategy' => 'direct',
                'recovery_options' => ['action' => static function (): never {
                    throw new RuntimeException('restart failed');
                }],
            ]
        );

        $this->assertSame('escalated', $result['outcome']);
        $this->assertSame('recovery', $result['coordinated_component']);
    }

    public function testNoOptionsAtAllEscalatesWithNoCoordinatedComponent(): void
    {
        [, , $resilience] = $this->makeManagers();

        $result = $resilience->coordinate(['detection_ref' => 'failure_detection_7', 'severity' => 'emergency']);

        $this->assertSame('escalated', $result['outcome']);
        $this->assertSame('none', $result['coordinated_component']);
        $this->assertStringContainsString('No self-healing action', $result['notes'][0]);
    }

    public function testFinishedCoordinationDispatchesEvent(): void
    {
        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        $events->listen('resilience.finished', new CallbackEventListener(
            function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }
        ));

        [$selfHealing, , $resilience] = $this->makeManagers($events);
        $selfHealing->registerAction('cache_refresh', static fn(): string => 'refreshed');

        $result = $resilience->coordinate(
            ['detection_ref' => 'failure_detection_8'],
            ['self_heal_action' => 'cache_refresh']
        );

        $this->assertCount(1, $captured);
        $this->assertSame($result['resilience_ref'], $captured[0]->getPayload()['resilience_ref']);
    }

    public function testOperationRetrievesAPersistedRecordByRef(): void
    {
        [$selfHealing, , $resilience] = $this->makeManagers();
        $selfHealing->registerAction('cache_refresh', static fn(): string => 'refreshed');
        $created = $resilience->coordinate(['detection_ref' => 'failure_detection_9'], ['self_heal_action' => 'cache_refresh']);

        $fetched = $resilience->operation($created['resilience_ref']);

        $this->assertNotNull($fetched);
        $this->assertSame('completed', $fetched['outcome']);
    }

    public function testOperationReturnsNullForUnknownRef(): void
    {
        [, , $resilience] = $this->makeManagers();

        $this->assertNull($resilience->operation('resilience_does_not_exist'));
    }

    public function testRecentOrdersMostRecentFirstAndRespectsLimit(): void
    {
        [, , $resilience] = $this->makeManagers();
        $resilience->coordinate(['detection_ref' => 'failure_detection_a']);
        $resilience->coordinate(['detection_ref' => 'failure_detection_b']);

        $recent = $resilience->recent(1);

        $this->assertCount(1, $recent);
        $this->assertSame('failure_detection_b', $recent[0]['failure_ref']);
    }

    public function testFailoverRequestWithoutAConfiguredFailoverCoordinatorFallsThroughToEscalation(): void
    {
        [, , $resilience] = $this->makeManagers();

        $result = $resilience->coordinate(
            ['detection_ref' => 'failure_detection_10'],
            ['failover_request' => ['affected_service' => 'svc', 'failover_type' => 'service', 'authorized' => true]]
        );

        $this->assertSame('escalated', $result['outcome']);
        $this->assertStringContainsString('Failover Coordinator is not configured', $result['notes'][0]);
    }

    public function testFailoverRequestRoutesToTheRealFailoverCoordinatorAndResolvesTheFailure(): void
    {
        $selfHealing = new SqliteSelfHealingEngine($this->tempPath('healing'), new RetryManager(fn() => null));
        $recovery = new SqliteRecoveryManager($this->tempPath('recovery'), new RetryManager(fn() => null));
        $redundancy = new SqliteRedundancyManager($this->tempPath('redundancy'));
        $redundancy->register('standby_1', 'service');
        $redundancy->updateSynchronization('standby_1', true);
        $failover = new SqliteFailoverCoordinator($this->tempPath('failover'), $redundancy);
        $resilience = new SqliteResilienceManager($this->tempPath('resilience'), $selfHealing, $recovery, $failover);

        $result = $resilience->coordinate(
            ['detection_ref' => 'failure_detection_11'],
            ['failover_request' => [
                'affected_service' => 'svc',
                'failover_type' => 'service',
                'authorized' => true,
                'action' => static fn(): string => 'transitioned',
            ]]
        );

        $this->assertSame('completed', $result['outcome']);
        $this->assertSame('failover', $result['coordinated_component']);
    }

    public function testDisasterRecoveryRequestRoutesToTheRealDisasterRecoveryComponent(): void
    {
        $selfHealing = new SqliteSelfHealingEngine($this->tempPath('healing'), new RetryManager(fn() => null));
        $recovery = new SqliteRecoveryManager($this->tempPath('recovery'), new RetryManager(fn() => null));
        $disasterRecovery = new SqliteDisasterRecovery($this->tempPath('disaster'));
        $resilience = new SqliteResilienceManager($this->tempPath('resilience'), $selfHealing, $recovery, null, $disasterRecovery);

        $result = $resilience->coordinate(
            ['detection_ref' => 'failure_detection_12'],
            ['disaster_recovery' => ['classification' => 'regional_outage', 'affected_services' => ['api' => 'core_platform_services']]]
        );

        $this->assertSame('declared', $result['outcome']);
        $this->assertSame('disaster_recovery', $result['coordinated_component']);
    }

    public function testBusinessContinuityRequestRoutesToTheRealBusinessContinuityComponent(): void
    {
        $selfHealing = new SqliteSelfHealingEngine($this->tempPath('healing'), new RetryManager(fn() => null));
        $recovery = new SqliteRecoveryManager($this->tempPath('recovery'), new RetryManager(fn() => null));
        $businessContinuity = new SqliteBusinessContinuity($this->tempPath('continuity'));
        $resilience = new SqliteResilienceManager($this->tempPath('resilience'), $selfHealing, $recovery, null, null, $businessContinuity);

        $result = $resilience->coordinate(
            ['detection_ref' => 'failure_detection_13'],
            ['business_continuity' => [
                'operating_mode' => 'reduced_capacity',
                'critical_services' => ['api' => 'core_ai_driver_services'],
                'strategy' => 'graceful_degradation',
                'evidence' => ['security_controls_active' => true],
            ]]
        );

        $this->assertSame('activated', $result['outcome']);
        $this->assertSame('business_continuity', $result['coordinated_component']);
    }

    public function testGovernanceRequestWithoutAConfiguredGovernanceComponentStaysUngoverned(): void
    {
        [, , $resilience] = $this->makeManagers();

        $result = $resilience->coordinate(
            ['detection_ref' => 'failure_detection_14'],
            ['governance_request' => ['resilience_component' => 'failover', 'policy_context' => ['ready' => true]]]
        );

        $this->assertSame('ungoverned', $result['governance_status']);
    }

    public function testGovernanceRequestRecordsTheRealGovernanceDecision(): void
    {
        $selfHealing = new SqliteSelfHealingEngine($this->tempPath('healing'), new RetryManager(fn() => null));
        $recovery = new SqliteRecoveryManager($this->tempPath('recovery'), new RetryManager(fn() => null));
        $policyEngine = new SqlitePolicyEngine($this->tempPath('policy'), new RuleEngine());
        $policyEngine->registerPolicy('p1', ['category' => 'operational', 'priority' => 1, 'condition' => ['type' => 'boolean', 'field' => 'ready', 'equals' => true], 'effect' => 'allow']);
        $governance = new SqliteResilienceGovernance($this->tempPath('governance'), $policyEngine);
        $resilience = new SqliteResilienceManager($this->tempPath('resilience'), $selfHealing, $recovery, null, null, null, $governance);

        $result = $resilience->coordinate(
            ['detection_ref' => 'failure_detection_15'],
            ['governance_request' => ['resilience_component' => 'failover_coordination', 'policy_context' => ['ready' => true], 'risk_classification' => 'low', 'reviewer' => 'operator_1']]
        );

        $this->assertSame('Approve', $result['governance_status']);
    }
}
