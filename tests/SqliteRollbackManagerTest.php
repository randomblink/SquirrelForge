<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Contracts\StepInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;
use SquirrelForge\Resilience\SqliteRollbackManager;

final class SqliteRollbackManagerTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        $this->databasePath = sys_get_temp_dir() . '/squirrelforge-rollback-manager-' . bin2hex(random_bytes(8)) . '.sqlite';
    }

    protected function tearDown(): void
    {
        foreach ([$this->databasePath, $this->databasePath . '-shm', $this->databasePath . '-wal'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    private function step(string $id, bool $failsToRollback = false): StepInterface
    {
        return new class ($id, $failsToRollback) implements StepInterface {
            public bool $rolledBack = false;

            public function __construct(private readonly string $id, private readonly bool $failsToRollback)
            {
            }

            public function getId(): string
            {
                return $this->id;
            }

            public function getName(): string
            {
                return $this->id;
            }

            public function run(array $context): array
            {
                return $context;
            }

            public function rollback(array $context): void
            {
                if ($this->failsToRollback) {
                    throw new \RuntimeException('Cannot reverse step ' . $this->id);
                }

                $this->rolledBack = true;
            }
        };
    }

    /**
     * @return array{recovery_point_id: string, rollback_type: string, authorized: bool, verified: bool}
     */
    private function request(array $overrides = []): array
    {
        return array_replace([
            'recovery_point_id' => 'recovery_point_1',
            'rollback_type' => 'configuration',
            'authorized' => true,
            'verified' => true,
            'action' => static function (): void {
            },
        ], $overrides);
    }

    public function testRejectsAnUnauthorizedRollback(): void
    {
        $manager = new SqliteRollbackManager($this->databasePath);

        $result = $manager->rollback($this->request(['authorized' => false]));

        $this->assertSame('Failed', $result['state']);
        $this->assertSame('blocked_unauthorized', $result['recovery_outcome']);
    }

    public function testRejectsAnUnverifiedRecoveryPoint(): void
    {
        $manager = new SqliteRollbackManager($this->databasePath);

        $result = $manager->rollback($this->request(['verified' => false]));

        $this->assertSame('Failed', $result['state']);
        $this->assertSame('blocked_unverified_recovery_point', $result['recovery_outcome']);
    }

    public function testRejectsAnUnsupportedRollbackType(): void
    {
        $manager = new SqliteRollbackManager($this->databasePath);

        $result = $manager->rollback($this->request(['rollback_type' => 'astrology']));

        $this->assertSame('Failed', $result['state']);
        $this->assertSame('unsupported_type', $result['recovery_outcome']);
    }

    public function testRejectsAMissingRequiredField(): void
    {
        $manager = new SqliteRollbackManager($this->databasePath);

        $result = $manager->rollback(['recovery_point_id' => 'recovery_point_1']);

        $this->assertSame('Failed', $result['state']);
        $this->assertSame('invalid_request', $result['recovery_outcome']);
        $this->assertNotNull($result['error']);
    }

    public function testDirectRollbackRequiresAnActionClosure(): void
    {
        $manager = new SqliteRollbackManager($this->databasePath);

        $result = $manager->rollback($this->request(['action' => null]));

        $this->assertSame('Failed', $result['state']);
        $this->assertSame('failed', $result['recovery_outcome']);
    }

    public function testDirectRollbackExecutesTheSuppliedAction(): void
    {
        $manager = new SqliteRollbackManager($this->databasePath);
        $executed = false;

        $result = $manager->rollback($this->request(['action' => function () use (&$executed): void {
            $executed = true;
        }]));

        $this->assertTrue($executed);
        $this->assertSame('Completed', $result['state']);
        $this->assertSame('recovered', $result['recovery_outcome']);
        $this->assertSame('not_verified', $result['verification_status']);
    }

    public function testDirectRollbackFailsWhenTheActionThrows(): void
    {
        $manager = new SqliteRollbackManager($this->databasePath);

        $result = $manager->rollback($this->request(['action' => function (): void {
            throw new \RuntimeException('boom');
        }]));

        $this->assertSame('Failed', $result['state']);
        $this->assertSame('boom', $result['error']);
    }

    public function testVerifierFailureFailsAnOtherwiseSuccessfulRollback(): void
    {
        $manager = new SqliteRollbackManager($this->databasePath);

        $result = $manager->rollback($this->request(['verifier' => static fn(): bool => false]));

        $this->assertSame('Failed', $result['state']);
        $this->assertSame('failed_verification', $result['recovery_outcome']);
    }

    public function testVerifierSuccessMarksTheRollbackVerified(): void
    {
        $manager = new SqliteRollbackManager($this->databasePath);

        $result = $manager->rollback($this->request(['verifier' => static fn(): bool => true]));

        $this->assertSame('Completed', $result['state']);
        $this->assertSame('verified', $result['verification_status']);
    }

    public function testWorkflowRollbackDelegatesToTheRealWorkflowRollbackManager(): void
    {
        $manager = new SqliteRollbackManager($this->databasePath);
        $step = $this->step('step_1');

        $result = $manager->rollback($this->request([
            'rollback_type' => 'workflow',
            'completed_steps' => [['step' => $step, 'context' => []]],
        ]));

        $this->assertSame('Completed', $result['state']);
        $this->assertTrue($step->rolledBack);
    }

    public function testWorkflowRollbackReportsPartiallyCompletedWhenAStepCannotBeReversed(): void
    {
        $manager = new SqliteRollbackManager($this->databasePath);
        $goodStep = $this->step('step_1');
        $badStep = $this->step('step_2', failsToRollback: true);

        $result = $manager->rollback($this->request([
            'rollback_type' => 'workflow',
            'completed_steps' => [
                ['step' => $goodStep, 'context' => []],
                ['step' => $badStep, 'context' => []],
            ],
        ]));

        $this->assertSame('Partially completed', $result['state']);
        $this->assertTrue($goodStep->rolledBack);
    }

    public function testEveryOperationIsRecordedIncludingRejections(): void
    {
        $manager = new SqliteRollbackManager($this->databasePath);

        $result = $manager->rollback($this->request(['authorized' => false]));
        $record = $manager->get($result['rollback_operation_id']);

        $this->assertSame('not_restored', $record['final_outcome']);
        $this->assertSame('recovery_point_1', $record['recovery_point_id']);
        $this->assertSame('ungoverned', $record['governance_status']);
    }

    public function testEvaluateDispatchesAnEvent(): void
    {
        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        $events->listen('rollback.completed', new CallbackEventListener(
            function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }
        ));

        $manager = new SqliteRollbackManager($this->databasePath, events: $events);
        $manager->rollback($this->request());

        $this->assertCount(1, $captured);
    }
}
