<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;
use SquirrelForge\Learning\SqliteEvaluationEngine;
use SquirrelForge\Learning\SqliteExperienceStore;

final class SqliteEvaluationEngineTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-evaluation-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    /**
     * @return array{0: SqliteExperienceStore, 1: SqliteEvaluationEngine}
     */
    private function makeEngine(): array
    {
        $experiences = new SqliteExperienceStore($this->tempPath('experiences'));
        $engine = new SqliteEvaluationEngine($this->tempPath('evaluations'), $experiences);

        return [$experiences, $engine];
    }

    public function testWithoutExperienceStoreConfiguredEvaluationIsRejected(): void
    {
        $engine = new SqliteEvaluationEngine($this->tempPath('evaluations'));

        $result = $engine->evaluate('experience_ghost');

        $this->assertFalse($result['found']);
        $this->assertStringContainsString('not configured', (string) $result['error']);
    }

    public function testUnknownExperienceIsRejected(): void
    {
        [, $engine] = $this->makeEngine();

        $result = $engine->evaluate('experience_ghost');

        $this->assertFalse($result['found']);
    }

    public function testInadequateEvidenceIsInsufficientEvidence(): void
    {
        [$experiences, $engine] = $this->makeEngine();
        $recorded = $experiences->record('agent:1', 'timeout');

        $result = $engine->evaluate($recorded['experience_id']);

        $this->assertSame('insufficient_evidence', $result['qualification']);
        $this->assertSame(0.0, $result['confidence']);
    }

    public function testAdequateEvidenceWithNoRelevanceFilterDefaultsToRelevant(): void
    {
        [$experiences, $engine] = $this->makeEngine();
        $recorded = $experiences->record('agent:1', 'timeout', ['evidence_references' => ['evidence_1']]);

        $result = $engine->evaluate($recorded['experience_id']);

        $this->assertSame('qualified', $result['qualification']);
    }

    public function testCategoryOutsideRelevantCategoriesIsNotQualified(): void
    {
        [$experiences, $engine] = $this->makeEngine();
        $recorded = $experiences->record('agent:1', 'timeout', ['evidence_references' => ['evidence_1']]);

        $result = $engine->evaluate($recorded['experience_id'], ['relevant_categories' => ['success']]);

        $this->assertSame('not_qualified', $result['qualification']);
    }

    public function testMatchingExpectedAndObservedOutcomesQualifiesWithHigherConfidence(): void
    {
        [$experiences, $engine] = $this->makeEngine();
        $recorded = $experiences->record('agent:1', 'timeout', ['evidence_references' => ['evidence_1']]);

        $result = $engine->evaluate($recorded['experience_id'], ['expected_outcome' => 'retry_succeeded', 'observed_outcome' => 'retry_succeeded']);

        $this->assertSame('qualified', $result['qualification']);
        $this->assertEqualsWithDelta(0.7, $result['confidence'], 0.0001);
    }

    public function testMismatchedOutcomesAreNotQualified(): void
    {
        [$experiences, $engine] = $this->makeEngine();
        $recorded = $experiences->record('agent:1', 'timeout', ['evidence_references' => ['evidence_1']]);

        $result = $engine->evaluate($recorded['experience_id'], ['expected_outcome' => 'retry_succeeded', 'observed_outcome' => 'retry_failed']);

        $this->assertSame('not_qualified', $result['qualification']);
    }

    public function testRepeatabilityCountGrowsAndBoostsConfidenceUpToACap(): void
    {
        [$experiences, $engine] = $this->makeEngine();

        $first = $experiences->record('agent:1', 'timeout', ['evidence_references' => ['e1']]);
        $engine->evaluate($first['experience_id']);
        $second = $experiences->record('agent:1', 'timeout', ['evidence_references' => ['e2']]);
        $engine->evaluate($second['experience_id']);
        $third = $experiences->record('agent:1', 'timeout', ['evidence_references' => ['e3']]);

        $result = $engine->evaluate($third['experience_id']);
        $fetched = $engine->get($result['evaluation_id']);

        $this->assertSame(2, $fetched['repeatability_count']);
        $this->assertEqualsWithDelta(0.7, $result['confidence'], 0.0001);
    }

    public function testContradictingAPriorOutcomeVerdictIsContradicted(): void
    {
        [$experiences, $engine] = $this->makeEngine();
        $first = $experiences->record('agent:1', 'timeout', ['evidence_references' => ['e1']]);
        $engine->evaluate($first['experience_id'], ['expected_outcome' => 'ok', 'observed_outcome' => 'ok']);

        $second = $experiences->record('agent:1', 'timeout', ['evidence_references' => ['e2']]);
        $result = $engine->evaluate($second['experience_id'], ['expected_outcome' => 'ok', 'observed_outcome' => 'not_ok']);

        $this->assertSame('contradicted', $result['qualification']);
    }

    public function testQualifiedEvaluationAttachesItselfToTheExperienceAndTransitionsStatus(): void
    {
        [$experiences, $engine] = $this->makeEngine();
        $recorded = $experiences->record('agent:1', 'timeout', ['evidence_references' => ['e1']]);

        $result = $engine->evaluate($recorded['experience_id']);

        $fetched = $experiences->get($recorded['experience_id']);
        $this->assertSame($result['evaluation_id'], $fetched['evaluation_reference']);
        $this->assertSame('evaluated', $fetched['status']);
    }

    public function testNotQualifiedEvaluationDoesNotAttachToTheExperience(): void
    {
        [$experiences, $engine] = $this->makeEngine();
        $recorded = $experiences->record('agent:1', 'timeout', ['evidence_references' => ['e1']]);

        $engine->evaluate($recorded['experience_id'], ['expected_outcome' => 'a', 'observed_outcome' => 'b']);

        $fetched = $experiences->get($recorded['experience_id']);
        $this->assertNull($fetched['evaluation_reference']);
        $this->assertSame('recorded', $fetched['status']);
    }

    public function testListFiltersByQualification(): void
    {
        [$experiences, $engine] = $this->makeEngine();
        $qualified = $experiences->record('agent:1', 'timeout', ['evidence_references' => ['e1']]);
        $engine->evaluate($qualified['experience_id']);
        $insufficient = $experiences->record('agent:2', 'timeout');
        $engine->evaluate($insufficient['experience_id']);

        $this->assertCount(1, $engine->list(['qualification' => 'qualified']));
        $this->assertCount(1, $engine->list(['qualification' => 'insufficient_evidence']));
    }

    public function testEvaluatedRecordDispatchesEvent(): void
    {
        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        $events->listen('evaluation.qualified', new CallbackEventListener(
            function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }
        ));

        $experiences = new SqliteExperienceStore($this->tempPath('experiences'));
        $engine = new SqliteEvaluationEngine($this->tempPath('evaluations'), $experiences, $events);
        $recorded = $experiences->record('agent:1', 'timeout', ['evidence_references' => ['e1']]);
        $engine->evaluate($recorded['experience_id']);

        $this->assertCount(1, $captured);
    }
}
