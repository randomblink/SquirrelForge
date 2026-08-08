<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Execution;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Execution\ExecutionReporter;
use SquirrelForge\Execution\SqliteExecutionLogger;
use SquirrelForge\Execution\SqliteResultCollector;

final class ExecutionReporterTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-execution-reporter-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function logger(): SqliteExecutionLogger
    {
        return new SqliteExecutionLogger($this->tempPath('logger'));
    }

    private function resultCollector(): SqliteResultCollector
    {
        return new SqliteResultCollector($this->tempPath('results'));
    }

    // --- validation decision guard ---

    public function testUnrecognizedValidationDecisionIsRejected(): void
    {
        $reporter = new ExecutionReporter();

        $result = $reporter->assemble('exec_1', ['validation_decision' => 'MOSTLY_FINE']);

        $this->assertSame('invalid', $result['outcome']);
        $this->assertStringContainsString('MOSTLY_FINE', $result['error']);
    }

    public function testRealValidationDecisionIsCopiedVerbatim(): void
    {
        $reporter = new ExecutionReporter();

        $result = $reporter->assemble('exec_1', ['validation_decision' => 'ACCEPTED_WITH_LIMITATIONS']);

        $this->assertSame('assembled', $result['outcome']);
        $this->assertSame('ACCEPTED_WITH_LIMITATIONS', $result['report']['validation_decision']);
    }

    public function testOmittedValidationDecisionIsNull(): void
    {
        $reporter = new ExecutionReporter();

        $result = $reporter->assemble('exec_1');

        $this->assertNull($result['report']['validation_decision']);
    }

    // --- classification from the shared logger ---

    public function testCompletedActivityIsAssembledFromTheLogger(): void
    {
        $logger = $this->logger();
        $logger->record(['execution_id' => 'exec_1', 'actor' => 'workflow_executor', 'action_type' => 'step_status', 'outcome' => 'Passed']);
        $logger->record(['execution_id' => 'exec_1', 'actor' => 'action_dispatcher', 'action_type' => 'dispatch', 'outcome' => 'Complete']);
        $reporter = new ExecutionReporter(null, $logger);

        $result = $reporter->assemble('exec_1');

        $this->assertCount(2, $result['report']['completed_activity']);
    }

    public function testBlockedUnresolvedConditionsAreAssembledFromTheLogger(): void
    {
        $logger = $this->logger();
        $logger->record(['execution_id' => 'exec_1', 'actor' => 'workflow_executor', 'action_type' => 'step_status', 'outcome' => 'Failed']);
        $reporter = new ExecutionReporter(null, $logger);

        $result = $reporter->assemble('exec_1');

        $this->assertCount(1, $result['report']['blocked_unresolved_conditions']);
        $this->assertSame('Failed', $result['report']['blocked_unresolved_conditions'][0]['outcome']);
    }

    public function testRollbackReferencesAreAssembledFromTheLogger(): void
    {
        $logger = $this->logger();
        $logger->record(['execution_id' => 'exec_1', 'actor' => 'rollback_manager', 'action_type' => 'rollback', 'outcome' => 'Successful']);
        $logger->record(['execution_id' => 'exec_1', 'actor' => 'action_dispatcher', 'action_type' => 'dispatch', 'outcome' => 'Complete']);
        $reporter = new ExecutionReporter(null, $logger);

        $result = $reporter->assemble('exec_1');

        $this->assertCount(1, $result['report']['rollback_references']);
        $this->assertSame('rollback_manager', $result['report']['rollback_references'][0]['actor']);
    }

    public function testFailureRecoveryReferencesAreAssembledFromTheLogger(): void
    {
        $logger = $this->logger();
        $logger->record(['execution_id' => 'exec_1', 'actor' => 'action_dispatcher', 'action_type' => 'failure_intake', 'outcome' => 'Dispatch Failure']);
        $logger->record(['execution_id' => 'exec_1', 'actor' => 'failure_handler', 'action_type' => 'failure_routing', 'outcome' => 'routed']);
        $reporter = new ExecutionReporter(null, $logger);

        $result = $reporter->assemble('exec_1');

        $this->assertCount(2, $result['report']['failure_recovery_references']);
    }

    public function testEntriesFromOtherExecutionsAreExcluded(): void
    {
        $logger = $this->logger();
        $logger->record(['execution_id' => 'exec_1', 'actor' => 'workflow_executor', 'action_type' => 'step_status', 'outcome' => 'Passed']);
        $logger->record(['execution_id' => 'exec_2', 'actor' => 'workflow_executor', 'action_type' => 'step_status', 'outcome' => 'Passed']);
        $reporter = new ExecutionReporter(null, $logger);

        $result = $reporter->assemble('exec_1');

        $this->assertCount(1, $result['report']['completed_activity']);
    }

    // --- SqliteResultCollector composition ---

    public function testChangedArtifactReferencesAreAssembledFromTheResultCollector(): void
    {
        $collector = $this->resultCollector();
        $collector->collect(['execution_ref' => 'exec_1', 'workflow_step_ref' => 'step_1', 'subject_ref' => 'artifact_1']);
        $collector->collect(['execution_ref' => 'exec_1', 'workflow_step_ref' => 'step_2', 'subject_ref' => 'artifact_2']);
        $reporter = new ExecutionReporter($collector);

        $result = $reporter->assemble('exec_1');

        $this->assertCount(2, $result['report']['changed_artifact_references']);
    }

    // --- external references pass through honestly ---

    public function testExternalReferencesArePassedThroughVerbatim(): void
    {
        $reporter = new ExecutionReporter();

        $result = $reporter->assemble('exec_1', [
            'status_reference' => 'state_ref_1',
            'validation_record_reference' => 'validation_ref_1',
            'validation_evidence_references' => ['test_run_1'],
            'validation_limitations' => ['limited to staging'],
            'unresolved_risk_references' => ['risk_1'],
            'recommended_next_actions' => [['action' => 'retry']],
        ]);

        $report = $result['report'];
        $this->assertSame('state_ref_1', $report['status_reference']);
        $this->assertSame('validation_ref_1', $report['validation_record_reference']);
        $this->assertSame(['test_run_1'], $report['validation_evidence_references']);
        $this->assertSame(['limited to staging'], $report['validation_limitations']);
        $this->assertSame(['risk_1'], $report['unresolved_risk_references']);
        $this->assertSame([['action' => 'retry']], $report['recommended_next_actions']);
    }

    public function testOmittedExternalReferencesAreEmptyNotFabricated(): void
    {
        $reporter = new ExecutionReporter();

        $result = $reporter->assemble('exec_1');

        $report = $result['report'];
        $this->assertNull($report['status_reference']);
        $this->assertNull($report['validation_record_reference']);
        $this->assertSame([], $report['validation_evidence_references']);
        $this->assertSame([], $report['unresolved_risk_references']);
        $this->assertSame([], $report['recommended_next_actions']);
    }

    // --- structural ---

    public function testReportHasAUniqueIdAndTimestamp(): void
    {
        $reporter = new ExecutionReporter();

        $first = $reporter->assemble('exec_1')['report'];
        $second = $reporter->assemble('exec_1')['report'];

        $this->assertNotSame($first['report_id'], $second['report_id']);
        $this->assertNotNull($first['timestamp']);
    }

    public function testWorksWithoutAnyComposedComponents(): void
    {
        $reporter = new ExecutionReporter(null, null);

        $result = $reporter->assemble('exec_1');

        $this->assertSame('assembled', $result['outcome']);
        $this->assertSame([], $result['report']['completed_activity']);
        $this->assertSame([], $result['report']['changed_artifact_references']);
    }
}
