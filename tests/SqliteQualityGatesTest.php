<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Automation\RuleEngine;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;
use SquirrelForge\Governance\SqliteQualityGates;
use SquirrelForge\Reasoning\RuleEvaluator;
use SquirrelForge\Reasoning\SqliteRiskAssessor;

final class SqliteQualityGatesTest extends TestCase
{
    /** @var array<int, string> */
    private array $databasePaths = [];

    /** @var array<int, string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->databasePaths as $path) {
            foreach ([$path, $path . '-shm', $path . '-wal'] as $candidate) {
                if (is_file($candidate)) {
                    unlink($candidate);
                }
            }
        }

        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
    }

    private function tempPath(string $label): string
    {
        $path = sys_get_temp_dir() . "/squirrelforge-quality-gates-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function tempFile(): string
    {
        $path = sys_get_temp_dir() . '/sfqg-fixture-' . bin2hex(random_bytes(8)) . '.txt';
        file_put_contents($path, 'fixture');
        $this->tempFiles[] = $path;

        return $path;
    }

    private function gates(): SqliteQualityGates
    {
        return new SqliteQualityGates($this->tempPath('gates'));
    }

    public function testWithNoInputEveryGateIsNotApplicableAndTheDecisionIsApproved(): void
    {
        $gates = $this->gates();

        $result = $gates->review('request_1');

        $this->assertSame('approved', $result['decision']);
        $this->assertSame(array_fill_keys(array_keys($result['gates']), 'not_applicable'), $result['gates']);
    }

    public function testBooleanGatesReflectCallerSuppliedEvidence(): void
    {
        $gates = $this->gates();

        $result = $gates->review('request_1', [
            'architecture_consistency' => true,
            'documentation_updated' => false,
            'release_report_approved' => true,
        ]);

        $this->assertSame('passed', $result['gates']['architecture_consistency']);
        $this->assertSame('failed', $result['gates']['documentation']);
        $this->assertSame('passed', $result['gates']['release_report']);
        $this->assertSame('blocked', $result['decision']);
    }

    public function testRuleComplianceGateRequiresALiteralPassedOutcome(): void
    {
        $gates = new SqliteQualityGates($this->tempPath('gates'), new RuleEvaluator());

        $passing = $gates->review('request_1', [
            'rules' => [['id' => 'r1', 'source' => 'general_agent', 'condition' => ['type' => 'boolean', 'field' => 'x', 'equals' => true]]],
            'rule_context' => ['x' => true],
        ]);
        $warning = $gates->review('request_2', [
            'rules' => [['id' => 'r1', 'source' => 'general_agent', 'condition' => ['type' => 'boolean', 'field' => 'never_supplied', 'equals' => true]]],
            'rule_context' => [],
        ]);

        $this->assertSame('passed', $passing['gates']['rule_compliance']);
        $this->assertSame('failed', $warning['gates']['rule_compliance']);
    }

    public function testRuleComplianceGateIsNotApplicableWithoutRulesOrAnEvaluator(): void
    {
        $gates = $this->gates();

        $result = $gates->review('request_1');

        $this->assertSame('not_applicable', $result['gates']['rule_compliance']);
    }

    public function testLinkIntegrityGateChecksRealFileExistence(): void
    {
        $gates = $this->gates();
        $realFile = $this->tempFile();

        $passing = $gates->review('request_1', ['linked_files' => [$realFile]]);
        $failing = $gates->review('request_2', ['linked_files' => [$realFile, '/nonexistent/path.txt']]);
        $vacuous = $gates->review('request_3', ['linked_files' => []]);

        $this->assertSame('passed', $passing['gates']['link_metadata_integrity']);
        $this->assertSame('failed', $failing['gates']['link_metadata_integrity']);
        $this->assertSame('passed', $vacuous['gates']['link_metadata_integrity']);
    }

    public function testApplicableTestsGateReflectsSuppliedTestEvidence(): void
    {
        $gates = $this->gates();

        $this->assertSame('passed', $gates->review('r1', ['test_evidence' => 'passed'])['gates']['applicable_tests']);
        $this->assertSame('failed', $gates->review('r2', ['test_evidence' => 'failed'])['gates']['applicable_tests']);
        $this->assertSame('waived', $gates->review('r3', ['test_evidence' => 'waived'])['gates']['applicable_tests']);
        $this->assertSame('not_applicable', $gates->review('r4')['gates']['applicable_tests']);
    }

    public function testSecurityReviewIsNotRequiredWhenNoOpenSecurityRiskExists(): void
    {
        $riskAssessor = new SqliteRiskAssessor($this->tempPath('risk'));
        $gates = new SqliteQualityGates($this->tempPath('gates'), riskAssessor: $riskAssessor);

        $result = $gates->review('request_1', ['option_reference' => 'option_a']);

        $this->assertSame('not_applicable', $result['gates']['security_review']);
    }

    public function testSecurityReviewIsRequiredAndPassesWithApprovedEvidence(): void
    {
        $riskAssessor = new SqliteRiskAssessor($this->tempPath('risk'));
        $riskAssessor->assess('option_a', 'security', 'Unvalidated input', 'medium', 'medium');
        $gates = new SqliteQualityGates($this->tempPath('gates'), riskAssessor: $riskAssessor);

        $result = $gates->review('request_1', ['option_reference' => 'option_a', 'security_review_status' => 'passed']);

        $this->assertSame('passed', $result['gates']['security_review']);
    }

    public function testSecurityReviewFailsWhenRequiredButNotSupplied(): void
    {
        $riskAssessor = new SqliteRiskAssessor($this->tempPath('risk'));
        $riskAssessor->assess('option_a', 'security', 'Unvalidated input', 'medium', 'medium');
        $gates = new SqliteQualityGates($this->tempPath('gates'), riskAssessor: $riskAssessor);

        $result = $gates->review('request_1', ['option_reference' => 'option_a']);

        $this->assertSame('failed', $result['gates']['security_review']);
        $this->assertSame('blocked', $result['decision']);
    }

    public function testMigrationReadinessIsNotApplicableWithoutABreakingChange(): void
    {
        $gates = $this->gates();

        $result = $gates->review('request_1');

        $this->assertSame('not_applicable', $result['gates']['migration_readiness']);
    }

    public function testMigrationReadinessFailsWithABreakingChangeAndNoPlan(): void
    {
        $gates = $this->gates();

        $result = $gates->review('request_1', ['breaking_change' => true]);

        $this->assertSame('failed', $result['gates']['migration_readiness']);
    }

    public function testMigrationReadinessPassesWithABreakingChangeAndAPlan(): void
    {
        $gates = $this->gates();

        $result = $gates->review('request_1', ['breaking_change' => true, 'migration_plan' => 'Run the v2 migration script.']);

        $this->assertSame('passed', $result['gates']['migration_readiness']);
    }

    public function testHistoryReturnsPersistedReviewsWithDecodedGates(): void
    {
        $path = $this->tempPath('gates');
        $gates = new SqliteQualityGates($path);
        $gates->review('request_1', ['architecture_consistency' => true]);
        $gates->review('request_1', ['architecture_consistency' => false]);

        $history = $gates->history('request_1');

        $this->assertCount(2, $history);
        $this->assertSame('approved', $history[0]['decision']);
        $this->assertSame('blocked', $history[1]['decision']);
        $this->assertSame('passed', $history[0]['gates']['architecture_consistency']);
    }

    public function testReviewDispatchesAnEvent(): void
    {
        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        $events->listen('quality_gates.approved', new CallbackEventListener(
            function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }
        ));

        $gates = new SqliteQualityGates($this->tempPath('gates'), events: $events);
        $gates->review('request_1');

        $this->assertCount(1, $captured);
    }
}
