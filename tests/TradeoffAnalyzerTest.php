<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Reasoning\SqliteRiskAssessor;
use SquirrelForge\Reasoning\TradeoffAnalyzer;

final class TradeoffAnalyzerTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-tradeoff-analyzer-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    public function testWithNoOptionsReportsThatOutcome(): void
    {
        $analyzer = new TradeoffAnalyzer();

        $result = $analyzer->compareOptions([]);

        $this->assertSame('no_options', $result['outcome']);
        $this->assertNull($result['recommendation']);
    }

    public function testRecommendsTheOptionWithTheHighestSuppliedWeightedTotal(): void
    {
        $analyzer = new TradeoffAnalyzer();

        $result = $analyzer->compareOptions([
            ['option' => 'option_a', 'matrix_score' => ['weighted_total' => 3.5, 'disqualified' => false, 'disqualification_reason' => null]],
            ['option' => 'option_b', 'matrix_score' => ['weighted_total' => 4.2, 'disqualified' => false, 'disqualification_reason' => null]],
        ]);

        $this->assertSame('recommended', $result['outcome']);
        $this->assertSame('option_b', $result['recommendation']);
        $optionB = current(array_filter($result['comparison'], static fn(array $r): bool => $r['option'] === 'option_b'));
        $this->assertTrue($optionB['recommended']);
        $this->assertStringContainsString('Recommended', $optionB['reasoning']);
        $optionA = current(array_filter($result['comparison'], static fn(array $r): bool => $r['option'] === 'option_a'));
        $this->assertFalse($optionA['recommended']);
        $this->assertStringContainsString('not recommended', $optionA['reasoning']);
    }

    public function testDisqualifiedOptionsAreExcludedEvenWithAHigherTotal(): void
    {
        $analyzer = new TradeoffAnalyzer();

        $result = $analyzer->compareOptions([
            ['option' => 'option_a', 'matrix_score' => ['weighted_total' => 3.0, 'disqualified' => false, 'disqualification_reason' => null]],
            ['option' => 'option_b', 'matrix_score' => ['weighted_total' => 4.9, 'disqualified' => true, 'disqualification_reason' => 'Mandatory rule "no_destructive" failed.']],
        ]);

        $this->assertSame('option_a', $result['recommendation']);
        $optionB = current(array_filter($result['comparison'], static fn(array $r): bool => $r['option'] === 'option_b'));
        $this->assertFalse($optionB['eligible']);
        $this->assertStringContainsString('Excluded', $optionB['reasoning']);
    }

    public function testOptionsWithoutAMatrixScoreAreNeverRecommended(): void
    {
        $analyzer = new TradeoffAnalyzer();

        $result = $analyzer->compareOptions([
            ['option' => 'option_a'],
        ]);

        $this->assertSame('no_eligible_option', $result['outcome']);
        $this->assertNull($result['recommendation']);
        $this->assertStringContainsString('No Decision Matrix score', $result['comparison'][0]['reasoning']);
    }

    public function testRiskAssessorWithNoBlockingRiskAddsARealSecurityAdvantage(): void
    {
        $riskAssessor = new SqliteRiskAssessor($this->tempPath('risk'));
        $analyzer = new TradeoffAnalyzer($riskAssessor);

        $result = $analyzer->compareOptions([
            ['option' => 'option_a', 'matrix_score' => ['weighted_total' => 4.0, 'disqualified' => false, 'disqualification_reason' => null]],
        ]);

        $this->assertContains('No unmitigated critical risks block this option.', $result['comparison'][0]['advantages']['security']);
    }

    public function testRiskAssessorWithABlockingRiskExcludesTheOptionRegardlessOfScore(): void
    {
        $riskAssessor = new SqliteRiskAssessor($this->tempPath('risk'));
        $riskAssessor->assess('option_a', 'technical', 'Untested migration path', 'high', 'high');
        $analyzer = new TradeoffAnalyzer($riskAssessor);

        $result = $analyzer->compareOptions([
            ['option' => 'option_a', 'matrix_score' => ['weighted_total' => 5.0, 'disqualified' => false, 'disqualification_reason' => null]],
            ['option' => 'option_b', 'matrix_score' => ['weighted_total' => 2.0, 'disqualified' => false, 'disqualification_reason' => null]],
        ]);

        $this->assertSame('option_b', $result['recommendation']);
        $optionA = current(array_filter($result['comparison'], static fn(array $r): bool => $r['option'] === 'option_a'));
        $this->assertFalse($optionA['eligible']);
        $this->assertNotEmpty($optionA['disadvantages']['security']);
    }
}
