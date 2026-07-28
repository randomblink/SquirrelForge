<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;
use SquirrelForge\Resilience\RetryManager;
use SquirrelForge\Resilience\SqliteRecoveryManager;
use SquirrelForge\Workflow\CallbackStep;

final class SqliteRecoveryManagerTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        $this->databasePath = sys_get_temp_dir() . '/squirrelforge-recovery-' . bin2hex(random_bytes(8)) . '.sqlite';
    }

    protected function tearDown(): void
    {
        foreach ([$this->databasePath, $this->databasePath . '-shm', $this->databasePath . '-wal'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testUnknownStrategyIsRejectedWithoutPersisting(): void
    {
        $manager = new SqliteRecoveryManager($this->databasePath);
        $result = $manager->recover('teleportation');

        $this->assertSame('rejected', $result['state']);
        $this->assertNull($result['recovery_ref']);
        $this->assertSame([], $manager->recent());
    }

    public function testRetryStrategyRequiresAnAction(): void
    {
        $manager = new SqliteRecoveryManager($this->databasePath);
        $result = $manager->recover('retry');

        $this->assertSame('rejected', $result['state']);
        $this->assertStringContainsString('requires an "action"', (string) $result['error']);
    }

    public function testRetryStrategySucceedsAndDefaultsToNotVerified(): void
    {
        $manager = new SqliteRecoveryManager($this->databasePath, new RetryManager(fn() => null));
        $result = $manager->recover('retry', [
            'failure_ref' => 'failure_detection_abc',
            'action' => static fn(): string => 'restarted',
        ]);

        $this->assertSame('completed', $result['state']);
        $this->assertSame('not_verified', $result['verification_status']);
        $this->assertSame('failure_detection_abc', $result['failure_ref']);
        $this->assertSame('ungoverned', $result['governance_status']);
    }

    public function testRetryStrategyEscalatesWhenAttemptsExhausted(): void
    {
        $manager = new SqliteRecoveryManager($this->databasePath, new RetryManager(fn() => null));
        $result = $manager->recover('retry', [
            'action' => static function (): never {
                throw new RuntimeException('still down');
            },
            'retry_policy' => ['max_attempts' => 2],
        ]);

        $this->assertSame('escalated', $result['state']);
        $this->assertSame('still down', $result['error']);
    }

    public function testDirectStrategyFailsWhenActionThrows(): void
    {
        $manager = new SqliteRecoveryManager($this->databasePath);
        $result = $manager->recover('direct', [
            'action' => static function (): never {
                throw new RuntimeException('restart failed');
            },
        ]);

        $this->assertSame('failed', $result['state']);
        $this->assertSame('restart failed', $result['error']);
        $this->assertSame('not_applicable', $result['verification_status']);
    }

    public function testDirectStrategyHonorsAFailingVerifierEvenThoughActionSucceeded(): void
    {
        $manager = new SqliteRecoveryManager($this->databasePath);
        $result = $manager->recover('direct', [
            'action' => static fn(): string => 'ok',
            'verifier' => static fn(): bool => false,
        ]);

        $this->assertSame('failed', $result['state']);
        $this->assertSame('failed', $result['verification_status']);
    }

    public function testDirectStrategySucceedsAndIsVerified(): void
    {
        $manager = new SqliteRecoveryManager($this->databasePath);
        $result = $manager->recover('direct', [
            'action' => static fn(): string => 'ok',
            'verifier' => static fn(): bool => true,
        ]);

        $this->assertSame('completed', $result['state']);
        $this->assertSame('verified', $result['verification_status']);
    }

    public function testRollbackStrategyDelegatesToWorkflowRollbackManager(): void
    {
        $manager = new SqliteRecoveryManager($this->databasePath);
        $step = new CallbackStep('step_1', 'Step One', static fn(array $c): array => $c);

        $result = $manager->recover('rollback', ['completed_steps' => [['step' => $step, 'context' => []]]]);

        $this->assertSame('completed', $result['state']);
        $this->assertSame('verified', $result['verification_status']);
        $this->assertNull($result['error']);
    }

    public function testRollbackStrategyReportsPartialRecoveryWhenAStepFailsToReverse(): void
    {
        $manager = new SqliteRecoveryManager($this->databasePath);
        $clean = new CallbackStep('step_1', 'Step One', static fn(array $c): array => $c);
        $broken = new CallbackStep(
            'step_2',
            'Step Two',
            static fn(array $c): array => $c,
            static function (): never {
                throw new RuntimeException('cannot undo step_2');
            }
        );

        $result = $manager->recover('rollback', [
            'completed_steps' => [
                ['step' => $clean, 'context' => []],
                ['step' => $broken, 'context' => []],
            ],
        ]);

        $this->assertSame('partially_recovered', $result['state']);
        $this->assertStringContainsString('cannot undo step_2', (string) $result['error']);
    }

    public function testManualStrategyEscalatesWithoutExecutingAnything(): void
    {
        $manager = new SqliteRecoveryManager($this->databasePath);
        $result = $manager->recover('manual', ['reason' => 'requires operator approval']);

        $this->assertSame('escalated', $result['state']);
        $this->assertSame('not_applicable', $result['verification_status']);
    }

    public function testFinishedRecoveryDispatchesEvent(): void
    {
        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        $events->listen('recovery.finished', new CallbackEventListener(
            function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }
        ));

        $manager = new SqliteRecoveryManager($this->databasePath, events: $events);
        $result = $manager->recover('direct', ['action' => static fn(): string => 'ok']);

        $this->assertCount(1, $captured);
        $this->assertSame($result['recovery_ref'], $captured[0]->getPayload()['recovery_ref']);
        $this->assertSame('completed', $captured[0]->getPayload()['state']);
    }

    public function testRecoveryRetrievesAPersistedRecordByRef(): void
    {
        $manager = new SqliteRecoveryManager($this->databasePath);
        $created = $manager->recover('direct', ['action' => static fn(): string => 'ok']);

        $fetched = $manager->recovery($created['recovery_ref']);

        $this->assertNotNull($fetched);
        $this->assertSame('completed', $fetched['state']);
    }

    public function testRecoveryReturnsNullForUnknownRef(): void
    {
        $manager = new SqliteRecoveryManager($this->databasePath);

        $this->assertNull($manager->recovery('recovery_does_not_exist'));
    }

    public function testRecentOrdersMostRecentFirstAndRespectsLimit(): void
    {
        $manager = new SqliteRecoveryManager($this->databasePath);
        $manager->recover('manual');
        $manager->recover('direct', ['action' => static fn(): string => 'ok']);
        $manager->recover('rollback', ['completed_steps' => []]);

        $recent = $manager->recent(2);

        $this->assertCount(2, $recent);
        $this->assertSame('rollback', $recent[0]['strategy']);
        $this->assertSame('direct', $recent[1]['strategy']);
    }
}
