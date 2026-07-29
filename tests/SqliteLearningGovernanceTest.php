<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;
use SquirrelForge\Learning\PatternDetector;
use SquirrelForge\Learning\SqliteEvaluationEngine;
use SquirrelForge\Learning\SqliteExperienceStore;
use SquirrelForge\Learning\SqliteLearningGovernance;
use SquirrelForge\Reasoning\SqliteRiskAssessor;

final class SqliteLearningGovernanceTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-learning-governance-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    public function testMissingSupportingEvaluationRequiresMoreEvidence(): void
    {
        $evaluationEngine = new SqliteEvaluationEngine($this->tempPath('evaluations'));
        $governance = new SqliteLearningGovernance($this->tempPath('main'), $evaluationEngine);

        $result = $governance->review('proposal_1', ['experience_id' => 'experience_ghost']);

        $this->assertSame('requires_more_evidence', $result['outcome']);
    }

    public function testUnqualifiedSupportingEvaluationRequiresMoreEvidence(): void
    {
        $experiences = new SqliteExperienceStore($this->tempPath('experiences'));
        $evaluationEngine = new SqliteEvaluationEngine($this->tempPath('evaluations'), $experiences);
        $recorded = $experiences->record('agent:1', 'timeout');
        $evaluationEngine->evaluate($recorded['experience_id']);
        $governance = new SqliteLearningGovernance($this->tempPath('main'), $evaluationEngine);

        $result = $governance->review('proposal_1', ['experience_id' => $recorded['experience_id']]);

        $this->assertSame('requires_more_evidence', $result['outcome']);
    }

    public function testQualifiedSupportingEvaluationProceedsPastTheEvidenceCheck(): void
    {
        $experiences = new SqliteExperienceStore($this->tempPath('experiences'));
        $evaluationEngine = new SqliteEvaluationEngine($this->tempPath('evaluations'), $experiences);
        $recorded = $experiences->record('agent:1', 'timeout', ['evidence_references' => ['e1']]);
        $evaluationEngine->evaluate($recorded['experience_id']);
        $governance = new SqliteLearningGovernance($this->tempPath('main'), $evaluationEngine);

        $result = $governance->review('proposal_1', ['experience_id' => $recorded['experience_id']]);

        $this->assertSame('approved', $result['outcome']);
    }

    public function testUnmitigatedCriticalRiskIsRejected(): void
    {
        $riskAssessor = new SqliteRiskAssessor($this->tempPath('risk'));
        $riskAssessor->assess('proposal_1', 'security', 'critical issue', 'high', 'high');
        $governance = new SqliteLearningGovernance($this->tempPath('main'), riskAssessor: $riskAssessor);

        $result = $governance->review('proposal_1');

        $this->assertSame('rejected', $result['outcome']);
    }

    public function testDeniedSecurityDecisionIsProhibitedNotMerelyRejected(): void
    {
        $governance = new SqliteLearningGovernance($this->tempPath('main'));

        $result = $governance->review('proposal_1', ['security_decision' => 'denied']);

        $this->assertSame('prohibited', $result['outcome']);
    }

    public function testPendingPolicyResultIsDeferred(): void
    {
        $governance = new SqliteLearningGovernance($this->tempPath('main'));

        $result = $governance->review('proposal_1', ['policy_result' => 'pending']);

        $this->assertSame('deferred', $result['outcome']);
    }

    public function testDetectedAnomalyConditionsAnOtherwiseApprovedProposal(): void
    {
        $experiences = new SqliteExperienceStore($this->tempPath('experiences'));
        $experiences->record('agent:1', 'timeout', ['confidence' => 0.50]);
        $experiences->record('agent:1', 'timeout', ['confidence' => 0.52]);
        $experiences->record('agent:1', 'timeout', ['confidence' => 0.48]);
        $experiences->record('agent:1', 'timeout', ['confidence' => 0.51]);
        $experiences->record('agent:1', 'timeout', ['confidence' => 0.49]);
        $experiences->record('agent:1', 'timeout', ['confidence' => 0.05]);
        $patternDetector = new PatternDetector($experiences);
        $governance = new SqliteLearningGovernance($this->tempPath('main'), patternDetector: $patternDetector);

        $result = $governance->review('proposal_1', ['anomaly_filters' => ['source' => 'agent:1', 'event_category' => 'timeout']]);

        $this->assertSame('conditioned', $result['outcome']);
        $this->assertNotEmpty($result['conditions']);
    }

    public function testNoAnomaliesFoundIsApproved(): void
    {
        $experiences = new SqliteExperienceStore($this->tempPath('experiences'));
        $experiences->record('agent:1', 'timeout', ['confidence' => 0.5]);
        $experiences->record('agent:1', 'timeout', ['confidence' => 0.5]);
        $patternDetector = new PatternDetector($experiences);
        $governance = new SqliteLearningGovernance($this->tempPath('main'), patternDetector: $patternDetector);

        $result = $governance->review('proposal_1', ['anomaly_filters' => ['source' => 'agent:1', 'event_category' => 'timeout']]);

        $this->assertSame('approved', $result['outcome']);
    }

    public function testFullyCleanReviewWithNoOptionsIsApproved(): void
    {
        $governance = new SqliteLearningGovernance($this->tempPath('main'));

        $result = $governance->review('proposal_1');

        $this->assertSame('approved', $result['outcome']);
    }

    public function testRestrictIsRecordedAsALifecycleAction(): void
    {
        $governance = new SqliteLearningGovernance($this->tempPath('main'));
        $governance->review('proposal_1');

        $result = $governance->restrict('proposal_1', 'limited rollout only');

        $this->assertSame('restricted', $result['outcome']);
        $this->assertSame('restricted', $governance->currentStatus('proposal_1')['outcome']);
    }

    public function testHistoryReturnsRecordsInChronologicalOrder(): void
    {
        $governance = new SqliteLearningGovernance($this->tempPath('main'));
        $governance->review('proposal_1');
        $governance->restrict('proposal_1', 'limited rollout only');

        $history = $governance->history('proposal_1');

        $this->assertCount(2, $history);
        $this->assertSame('approved', $history[0]['outcome']);
        $this->assertSame('restricted', $history[1]['outcome']);
    }

    public function testCurrentStatusReturnsNullForAnUnknownProposal(): void
    {
        $governance = new SqliteLearningGovernance($this->tempPath('main'));

        $this->assertNull($governance->currentStatus('proposal_ghost'));
    }

    public function testReviewDispatchesEvent(): void
    {
        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        $events->listen('learning_governance.approved', new CallbackEventListener(
            function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }
        ));

        $governance = new SqliteLearningGovernance($this->tempPath('main'), events: $events);
        $governance->review('proposal_1');

        $this->assertCount(1, $captured);
    }
}
