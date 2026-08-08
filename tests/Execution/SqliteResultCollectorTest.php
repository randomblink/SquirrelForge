<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Execution;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Execution\SqliteExecutionLogger;
use SquirrelForge\Execution\SqliteResultCollector;

final class SqliteResultCollectorTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-result-collector-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function collector(): SqliteResultCollector
    {
        return new SqliteResultCollector($this->tempPath('db'));
    }

    /**
     * @return array{execution_ref: string, workflow_step_ref: string}
     */
    private function minimalEntry(array $overrides = []): array
    {
        return array_replace(['execution_ref' => 'exec_1', 'workflow_step_ref' => 'step_1'], $overrides);
    }

    // --- collect() ---

    public function testCollectRequiresExecutionRef(): void
    {
        $collector = $this->collector();
        $entry = $this->minimalEntry();
        unset($entry['execution_ref']);

        $result = $collector->collect($entry);

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testCollectRequiresWorkflowStepRef(): void
    {
        $collector = $this->collector();
        $entry = $this->minimalEntry();
        unset($entry['workflow_step_ref']);

        $result = $collector->collect($entry);

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testCollectWithoutExpectedOutputRefIsAlwaysReceived(): void
    {
        $collector = $this->collector();

        $first = $collector->collect($this->minimalEntry(['subject_ref' => 'artifact_1']));
        $second = $collector->collect($this->minimalEntry(['subject_ref' => 'artifact_2']));

        $this->assertSame('Received', $first['collection_finding']);
        $this->assertSame('Received', $second['collection_finding']);
    }

    public function testFirstCollectionForAnExpectedSlotIsReceived(): void
    {
        $collector = $this->collector();

        $result = $collector->collect($this->minimalEntry(['expected_output_ref' => 'primary_output', 'subject_ref' => 'artifact_1']));

        $this->assertSame('Received', $result['collection_finding']);
    }

    public function testSecondCollectionForTheSameExpectedSlotIsDuplicate(): void
    {
        $collector = $this->collector();
        $collector->collect($this->minimalEntry(['expected_output_ref' => 'primary_output', 'subject_ref' => 'artifact_1']));

        $result = $collector->collect($this->minimalEntry(['expected_output_ref' => 'primary_output', 'subject_ref' => 'artifact_1_retry']));

        $this->assertSame('Duplicate', $result['collection_finding']);
    }

    public function testDuplicateNeverOverwritesTheOriginalRecord(): void
    {
        $collector = $this->collector();
        $first = $collector->collect($this->minimalEntry(['expected_output_ref' => 'primary_output', 'subject_ref' => 'artifact_1']));
        $collector->collect($this->minimalEntry(['expected_output_ref' => 'primary_output', 'subject_ref' => 'artifact_1_retry']));

        $original = $collector->get($first['result_ref_id']);

        $this->assertSame('Received', $original['collection_finding']);
        $this->assertSame('artifact_1', $original['subject_ref']);
    }

    public function testDifferentExpectedSlotsDoNotCollide(): void
    {
        $collector = $this->collector();
        $collector->collect($this->minimalEntry(['expected_output_ref' => 'primary_output']));

        $result = $collector->collect($this->minimalEntry(['expected_output_ref' => 'secondary_output']));

        $this->assertSame('Received', $result['collection_finding']);
    }

    // --- attachValidation() ---

    public function testAttachValidationOnUnknownReferenceIsNotFound(): void
    {
        $collector = $this->collector();

        $result = $collector->attachValidation('ghost', 'validation_1');

        $this->assertSame('not_found', $result['outcome']);
    }

    public function testAttachValidationMarksReferenced(): void
    {
        $collector = $this->collector();
        $collected = $collector->collect($this->minimalEntry());

        $result = $collector->attachValidation($collected['result_ref_id'], 'validation_decision_ref_1');

        $this->assertSame('attached', $result['outcome']);
        $record = $collector->get($collected['result_ref_id']);
        $this->assertSame('Referenced', $record['collection_finding']);
        $this->assertSame('validation_decision_ref_1', $record['validation_record_ref']);
    }

    public function testReferencedCountsAsFulfillingAnExpectedSlot(): void
    {
        $collector = $this->collector();
        $collector->registerExpected('step_1', ['primary_output']);
        $collected = $collector->collect($this->minimalEntry(['expected_output_ref' => 'primary_output']));
        $collector->attachValidation($collected['result_ref_id'], 'validation_1');

        $set = $collector->assemble('step_1');

        $this->assertSame([], $set['missing_references']);
    }

    // --- assemble() ---

    public function testAssembleWithNoExpectationsHasNoMissing(): void
    {
        $collector = $this->collector();
        $collector->collect($this->minimalEntry());

        $set = $collector->assemble('step_1');

        $this->assertSame([], $set['missing_references']);
        $this->assertCount(1, $set['included_result_references']);
    }

    public function testAssembleDetectsMissingExpectedOutputs(): void
    {
        $collector = $this->collector();
        $collector->registerExpected('step_1', ['primary_output', 'secondary_output']);
        $collector->collect($this->minimalEntry(['expected_output_ref' => 'primary_output']));

        $set = $collector->assemble('step_1');

        $this->assertSame(['secondary_output'], $set['missing_references']);
    }

    public function testAssembleIncludesDuplicateReferencesSeparately(): void
    {
        $collector = $this->collector();
        $collector->collect($this->minimalEntry(['expected_output_ref' => 'primary_output']));
        $collector->collect($this->minimalEntry(['expected_output_ref' => 'primary_output']));

        $set = $collector->assemble('step_1');

        $this->assertCount(2, $set['included_result_references']);
        $this->assertCount(1, $set['duplicate_references']);
    }

    public function testAssembleDedupesValidationSubjectReferences(): void
    {
        $collector = $this->collector();
        $collector->collect($this->minimalEntry(['subject_ref' => 'artifact_1', 'version_ref' => 'v1']));
        $collector->collect($this->minimalEntry(['subject_ref' => 'artifact_1', 'version_ref' => 'v1']));
        $collector->collect($this->minimalEntry(['subject_ref' => 'artifact_2', 'version_ref' => 'v1']));

        $set = $collector->assemble('step_1');

        $this->assertCount(2, $set['validation_subject_references']);
    }

    public function testAssembleForAnUnknownStepReturnsEmptySet(): void
    {
        $collector = $this->collector();

        $set = $collector->assemble('ghost_step');

        $this->assertSame([], $set['included_result_references']);
        $this->assertSame([], $set['missing_references']);
    }

    public function testAssembleScopesToOneWorkflowStep(): void
    {
        $collector = $this->collector();
        $collector->collect($this->minimalEntry(['workflow_step_ref' => 'step_1']));
        $collector->collect($this->minimalEntry(['workflow_step_ref' => 'step_2']));

        $set = $collector->assemble('step_1');

        $this->assertCount(1, $set['included_result_references']);
    }

    // --- forExecution() ---

    public function testForExecutionSpansEveryWorkflowStep(): void
    {
        $collector = $this->collector();
        $collector->collect($this->minimalEntry(['workflow_step_ref' => 'step_1']));
        $collector->collect($this->minimalEntry(['workflow_step_ref' => 'step_2']));
        $collector->collect($this->minimalEntry(['execution_ref' => 'exec_2', 'workflow_step_ref' => 'step_1']));

        $results = $collector->forExecution('exec_1');

        $this->assertCount(2, $results);
    }

    public function testForExecutionWithNothingCollectedReturnsEmpty(): void
    {
        $collector = $this->collector();

        $this->assertSame([], $collector->forExecution('ghost_exec'));
    }

    // --- get() ---

    public function testGetUnknownReferenceReturnsNull(): void
    {
        $collector = $this->collector();

        $this->assertNull($collector->get('ghost'));
    }

    // --- logging composition ---

    public function testCollectRecordsThroughTheLogger(): void
    {
        $logger = new SqliteExecutionLogger($this->tempPath('logger'));
        $collector = new SqliteResultCollector($this->tempPath('db'), $logger);

        $collector->collect($this->minimalEntry(['action_ref' => 'action_1']));

        $history = $logger->history('exec_1');
        $this->assertCount(1, $history);
        $this->assertSame('Received', $history[0]['outcome']);
        $this->assertSame('result_collector', $history[0]['actor']);
    }

    public function testWorksWithoutALogger(): void
    {
        $collector = new SqliteResultCollector($this->tempPath('db'), null);

        $result = $collector->collect($this->minimalEntry());

        $this->assertSame('collected', $result['outcome']);
    }
}
