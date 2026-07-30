<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;
use SquirrelForge\Learning\PatternDetector;
use SquirrelForge\Learning\SqliteExperienceStore;
use SquirrelForge\Optimization\OptimizationEngine;
use SquirrelForge\Reasoning\SqliteRiskAssessor;

final class OptimizationEngineTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-optimization-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    public function testIdentifyOpportunitiesWithoutPatternDetectorIsNotConfigured(): void
    {
        $engine = new OptimizationEngine();

        $result = $engine->identifyOpportunities();

        $this->assertSame('not_configured', $result['outcome']);
    }

    public function testIdentifyOpportunitiesSurfacesRecurringIssuesFromRealPatternDetectorFindings(): void
    {
        $experiences = new SqliteExperienceStore($this->tempPath('experiences'));
        $experiences->record('agent:1', 'timeout');
        $experiences->record('agent:1', 'timeout');
        $experiences->record('agent:1', 'timeout');
        $engine = new OptimizationEngine(new PatternDetector($experiences));

        $result = $engine->identifyOpportunities(['source' => 'agent:1']);

        $this->assertSame('analyzed', $result['outcome']);
        $this->assertSame('recurring_issue', $result['opportunities'][0]['type']);
        $this->assertSame(3, $result['opportunities'][0]['evidence']['count']);
    }

    public function testIdentifyOpportunitiesSurfacesAnomaliesFromRealPatternDetectorFindings(): void
    {
        $experiences = new SqliteExperienceStore($this->tempPath('experiences'));
        $experiences->record('agent:1', 'timeout', ['confidence' => 0.50]);
        $experiences->record('agent:1', 'timeout', ['confidence' => 0.52]);
        $experiences->record('agent:1', 'timeout', ['confidence' => 0.48]);
        $experiences->record('agent:1', 'timeout', ['confidence' => 0.51]);
        $experiences->record('agent:1', 'timeout', ['confidence' => 0.49]);
        $experiences->record('agent:1', 'timeout', ['confidence' => 0.05]);
        $engine = new OptimizationEngine(new PatternDetector($experiences));

        $result = $engine->identifyOpportunities(['source' => 'agent:1', 'event_category' => 'timeout']);

        $anomalyOpportunities = array_filter($result['opportunities'], static fn(array $o): bool => $o['type'] === 'anomaly');
        $this->assertNotEmpty($anomalyOpportunities);
    }

    public function testCreateProposalWithoutRiskAssessorLeavesRiskCanProceedNull(): void
    {
        $engine = new OptimizationEngine();

        $result = $engine->createProposal('proposal_1', ['type' => 'recurring_issue', 'evidence' => ['count' => 5, 'confidence' => 0.8]]);

        $this->assertNull($result['risk_can_proceed']);
        $this->assertSame('proposed', $result['outcome']);
    }

    public function testCreateProposalConsumesRealRiskAssessorCheck(): void
    {
        $riskAssessor = new SqliteRiskAssessor($this->tempPath('risk'));
        $engine = new OptimizationEngine(riskAssessor: $riskAssessor);

        $result = $engine->createProposal('proposal_1', ['type' => 'recurring_issue', 'evidence' => ['count' => 5, 'confidence' => 0.8]]);

        $this->assertTrue($result['risk_can_proceed']);
    }

    public function testBlockingCriticalRiskHalvesPriorityRatherThanEliminatingTheProposal(): void
    {
        $riskAssessor = new SqliteRiskAssessor($this->tempPath('risk'));
        $riskAssessor->assess('proposal_1', 'security', 'critical issue', 'high', 'high');
        $engine = new OptimizationEngine(riskAssessor: $riskAssessor);

        $result = $engine->createProposal('proposal_1', ['type' => 'recurring_issue', 'evidence' => ['count' => 10, 'confidence' => 1.0]]);

        $this->assertFalse($result['risk_can_proceed']);
        $this->assertEqualsWithDelta(0.5, $result['priority_score'], 0.0001);
        $this->assertSame('proposed', $result['outcome']);
    }

    public function testRecurringIssuePriorityScalesWithConfidenceAndCountCappedAtOne(): void
    {
        $engine = new OptimizationEngine();

        $low = $engine->createProposal('p1', ['type' => 'recurring_issue', 'evidence' => ['count' => 2, 'confidence' => 0.5]]);
        $capped = $engine->createProposal('p2', ['type' => 'recurring_issue', 'evidence' => ['count' => 100, 'confidence' => 1.0]]);

        $this->assertEqualsWithDelta(0.1, $low['priority_score'], 0.0001);
        $this->assertEqualsWithDelta(1.0, $capped['priority_score'], 0.0001);
    }

    public function testAnomalyPriorityScalesWithZScoreCappedAtOne(): void
    {
        $engine = new OptimizationEngine();

        $moderate = $engine->createProposal('p1', ['type' => 'anomaly', 'evidence' => ['z_score' => 2.5]]);
        $extreme = $engine->createProposal('p2', ['type' => 'anomaly', 'evidence' => ['z_score' => -10.0]]);

        $this->assertEqualsWithDelta(0.5, $moderate['priority_score'], 0.0001);
        $this->assertEqualsWithDelta(1.0, $extreme['priority_score'], 0.0001);
    }

    public function testUnknownOpportunityTypeScoresZero(): void
    {
        $engine = new OptimizationEngine();

        $result = $engine->createProposal('p1', ['type' => 'mystery']);

        $this->assertSame(0.0, $result['priority_score']);
    }

    public function testCreateProposalCarriesThroughCallerSuppliedFields(): void
    {
        $engine = new OptimizationEngine();

        $result = $engine->createProposal('p1', ['type' => 'recurring_issue', 'evidence' => []], [
            'expected_benefit' => 'reduced timeout rate',
            'tradeoffs' => ['requires config change'],
            'effort' => 'medium',
            'success_criteria' => ['timeout rate below 1%'],
        ]);

        $this->assertSame('reduced timeout rate', $result['expected_benefit']);
        $this->assertSame(['requires config change'], $result['tradeoffs']);
        $this->assertSame('medium', $result['effort']);
        $this->assertSame(['timeout rate below 1%'], $result['success_criteria']);
    }

    public function testCreateProposalDispatchesEvent(): void
    {
        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        $events->listen('optimization.proposed', new CallbackEventListener(
            function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }
        ));

        $engine = new OptimizationEngine(events: $events);
        $engine->createProposal('p1', ['type' => 'recurring_issue', 'evidence' => []]);

        $this->assertCount(1, $captured);
    }
}
