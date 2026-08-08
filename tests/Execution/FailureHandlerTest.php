<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Execution;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Execution\FailureHandler;
use SquirrelForge\Execution\SqliteExecutionLogger;

final class FailureHandlerTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-failure-handler-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    /**
     * @return array{execution_ref: string, reporting_component: string, failure_type: string, observed_condition: string}
     */
    private function minimalReport(array $overrides = []): array
    {
        return array_replace([
            'execution_ref' => 'exec_1',
            'reporting_component' => 'action_dispatcher',
            'failure_type' => 'Dispatch Failure',
            'observed_condition' => 'target unreachable',
        ], $overrides);
    }

    // --- receive() ---

    public function testReceiveRequiresExecutionRef(): void
    {
        $handler = new FailureHandler();
        $report = $this->minimalReport();
        unset($report['execution_ref']);

        $result = $handler->receive($report);

        $this->assertSame('invalid', $result['outcome']);
        $this->assertStringContainsString('execution_ref', $result['error']);
    }

    public function testReceiveRejectsUnknownFailureType(): void
    {
        $handler = new FailureHandler();

        $result = $handler->receive($this->minimalReport(['failure_type' => 'Made Up Failure']));

        $this->assertSame('invalid', $result['outcome']);
        $this->assertStringContainsString('Failure Types', $result['error']);
    }

    public function testReceiveRejectsValidationFailureWithoutSourceClassification(): void
    {
        $handler = new FailureHandler();

        $result = $handler->receive($this->minimalReport(['failure_type' => 'Validation Failure']));

        $this->assertSame('invalid', $result['outcome']);
        $this->assertStringContainsString('source_classification_ref', $result['error']);
    }

    public function testReceiveAcceptsValidationFailureWithSourceClassification(): void
    {
        $handler = new FailureHandler();

        $result = $handler->receive($this->minimalReport([
            'failure_type' => 'Validation Failure',
            'source_classification_ref' => 'validation_decision_ref_1',
        ]));

        $this->assertSame('normalized', $result['outcome']);
        $this->assertSame('validation_decision_ref_1', $result['failure_record']['source_classification_ref']);
    }

    public function testReceiveDirectlyObservedFailureNeedsNoSourceClassification(): void
    {
        $handler = new FailureHandler();

        $result = $handler->receive($this->minimalReport(['failure_type' => 'Timeout Failure']));

        $this->assertSame('normalized', $result['outcome']);
    }

    public function testReceiveNormalizesAllCorrelationReferences(): void
    {
        $handler = new FailureHandler();

        $result = $handler->receive($this->minimalReport([
            'workflow_step_ref' => 'step_1',
            'action_ref' => 'action_1',
            'checkpoint_ref' => 'checkpoint_1',
            'evidence_references' => ['log_1', 'log_2'],
        ]));

        $record = $result['failure_record'];
        $this->assertSame('step_1', $record['workflow_step_ref']);
        $this->assertSame('action_1', $record['action_ref']);
        $this->assertSame('checkpoint_1', $record['checkpoint_ref']);
        $this->assertSame(['log_1', 'log_2'], $record['evidence_references']);
        $this->assertNull($record['recovery_record_ref']);
        $this->assertNotNull($record['failure_id']);
    }

    // --- forward(): authorization ---

    public function testForwardUnauthorizedNeverRoutes(): void
    {
        $handler = new FailureHandler();
        $record = $handler->receive($this->minimalReport())['failure_record'];
        $invoked = false;

        $result = $handler->forward(
            $record,
            static fn(array $r): array => ['authorized' => false, 'reason' => 'no recovery available'],
            function () use (&$invoked): void {
                $invoked = true;
            }
        );

        $this->assertSame('not_authorized', $result['outcome']);
        $this->assertFalse($invoked);
        $this->assertSame('no recovery available', $result['error']);
    }

    public function testForwardUnrecognizedOperationIsInvalid(): void
    {
        $handler = new FailureHandler();
        $record = $handler->receive($this->minimalReport())['failure_record'];

        $result = $handler->forward($record, static fn(array $r): array => ['authorized' => true, 'operation' => 'Made Up Op']);

        $this->assertSame('invalid_operation', $result['outcome']);
    }

    // --- forward(): default routing targets ---

    public function testRetryRoutesToActionDispatcherByDefault(): void
    {
        $handler = new FailureHandler();
        $record = $handler->receive($this->minimalReport())['failure_record'];
        $seen = null;

        $result = $handler->forward(
            $record,
            static fn(array $r): array => ['authorized' => true, 'operation' => 'Retry'],
            function (string $op, string $target, array $r) use (&$seen): void {
                $seen = [$op, $target];
            }
        );

        $this->assertSame('routed', $result['outcome']);
        $this->assertSame('action_dispatcher', $result['target_component']);
        $this->assertSame(['Retry', 'action_dispatcher'], $seen);
    }

    public function testRollbackRoutesToRollbackManagerByDefault(): void
    {
        $handler = new FailureHandler();
        $record = $handler->receive($this->minimalReport())['failure_record'];

        $result = $handler->forward($record, static fn(array $r): array => ['authorized' => true, 'operation' => 'Rollback']);

        $this->assertSame('rollback_manager', $result['target_component']);
    }

    public function testTerminateRoutesToExecutionEngineByDefault(): void
    {
        $handler = new FailureHandler();
        $record = $handler->receive($this->minimalReport())['failure_record'];

        $result = $handler->forward($record, static fn(array $r): array => ['authorized' => true, 'operation' => 'Terminate']);

        $this->assertSame('execution_engine', $result['target_component']);
    }

    public function testDecisionSuppliedTargetOverridesTheDefault(): void
    {
        $handler = new FailureHandler();
        $record = $handler->receive($this->minimalReport())['failure_record'];

        $result = $handler->forward($record, static fn(array $r): array => [
            'authorized' => true,
            'operation' => 'Retry',
            'target_component' => 'custom_dispatcher',
        ]);

        $this->assertSame('custom_dispatcher', $result['target_component']);
    }

    // --- forward(): Skip requires an explicit target ---

    public function testSkipWithoutATargetComponentIsNotRoutable(): void
    {
        $handler = new FailureHandler();
        $record = $handler->receive($this->minimalReport())['failure_record'];

        $result = $handler->forward($record, static fn(array $r): array => ['authorized' => true, 'operation' => 'Skip']);

        $this->assertSame('not_routable', $result['outcome']);
    }

    public function testSkipWithAnExplicitTargetRoutes(): void
    {
        $handler = new FailureHandler();
        $record = $handler->receive($this->minimalReport())['failure_record'];

        $result = $handler->forward($record, static fn(array $r): array => [
            'authorized' => true,
            'operation' => 'Skip',
            'target_component' => 'workflow_executor',
        ]);

        $this->assertSame('routed', $result['outcome']);
        $this->assertSame('workflow_executor', $result['target_component']);
    }

    // --- forward(): Escalate never routes to an Execution component ---

    public function testEscalateNeverInvokesRoute(): void
    {
        $handler = new FailureHandler();
        $record = $handler->receive($this->minimalReport())['failure_record'];
        $invoked = false;

        $result = $handler->forward(
            $record,
            static fn(array $r): array => ['authorized' => true, 'operation' => 'Escalate'],
            function () use (&$invoked): void {
                $invoked = true;
            }
        );

        $this->assertSame('escalated', $result['outcome']);
        $this->assertFalse($invoked);
        $this->assertNull($result['target_component']);
    }

    // --- forward(): the decision closure receives the real failure record ---

    public function testRecoveryRequestReceivesTheFailureRecord(): void
    {
        $handler = new FailureHandler();
        $record = $handler->receive($this->minimalReport(['action_ref' => 'action_1']))['failure_record'];
        $seen = null;

        $handler->forward($record, function (array $r) use (&$seen): array {
            $seen = $r;

            return ['authorized' => false];
        });

        $this->assertSame('action_1', $seen['action_ref']);
        $this->assertSame('exec_1', $seen['execution_ref']);
    }

    public function testRecoveryRecordRefIsCarriedThroughOnAnyOutcome(): void
    {
        $handler = new FailureHandler();
        $record = $handler->receive($this->minimalReport())['failure_record'];

        $result = $handler->forward($record, static fn(array $r): array => ['authorized' => false, 'recovery_record_ref' => 'recovery_1']);

        $this->assertSame('recovery_1', $result['recovery_record_ref']);
    }

    // --- traceability composition ---

    public function testReceiveRecordsHistoryThroughTheLogger(): void
    {
        $logger = new SqliteExecutionLogger($this->tempPath('db'));
        $handler = new FailureHandler($logger);

        $handler->receive($this->minimalReport());

        $history = $logger->history('exec_1');
        $this->assertCount(1, $history);
        $this->assertSame('Dispatch Failure', $history[0]['outcome']);
        $this->assertSame('action_dispatcher', $history[0]['actor']);
    }

    public function testForwardRecordsRoutingThroughTheLogger(): void
    {
        $logger = new SqliteExecutionLogger($this->tempPath('db'));
        $handler = new FailureHandler($logger);
        $record = $handler->receive($this->minimalReport())['failure_record'];

        $handler->forward($record, static fn(array $r): array => ['authorized' => true, 'operation' => 'Retry']);

        $history = $logger->history('exec_1');
        $this->assertCount(2, $history);
        $this->assertSame('routed', $history[1]['outcome']);
        $this->assertSame('failure_handler', $history[1]['actor']);
    }

    public function testWorksWithoutALogger(): void
    {
        $handler = new FailureHandler(null);

        $result = $handler->receive($this->minimalReport());

        $this->assertSame('normalized', $result['outcome']);
    }
}
