<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;
use SquirrelForge\Reasoning\SqliteRiskAssessor;

final class SqliteRiskAssessorTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        $this->databasePath = sys_get_temp_dir() . '/squirrelforge-risk-' . bin2hex(random_bytes(8)) . '.sqlite';
    }

    protected function tearDown(): void
    {
        foreach ([$this->databasePath, $this->databasePath . '-shm', $this->databasePath . '-wal'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function matrixProvider(): array
    {
        return [
            'low/low -> low (given)' => ['low', 'low', 'low'],
            'low/medium -> low (interpolated)' => ['low', 'medium', 'low'],
            'low/high -> medium (given)' => ['low', 'high', 'medium'],
            'medium/low -> low (interpolated)' => ['medium', 'low', 'low'],
            'medium/medium -> medium (given)' => ['medium', 'medium', 'medium'],
            'medium/high -> high (given)' => ['medium', 'high', 'high'],
            'high/low -> medium (interpolated)' => ['high', 'low', 'medium'],
            'high/medium -> high (interpolated)' => ['high', 'medium', 'high'],
            'high/high -> critical (given)' => ['high', 'high', 'critical'],
        ];
    }

    #[DataProvider('matrixProvider')]
    public function testRiskMatrixIsCorrectAndMonotonic(string $likelihood, string $impact, string $expectedLevel): void
    {
        $assessor = new SqliteRiskAssessor($this->databasePath);

        $result = $assessor->assess('option_1', 'technical', 'some risk', $likelihood, $impact);

        $this->assertSame($expectedLevel, $result['risk_level']);
    }

    public function testAssessRejectsUnknownCategory(): void
    {
        $assessor = new SqliteRiskAssessor($this->databasePath);

        $result = $assessor->assess('option_1', 'astrological', 'x', 'low', 'low');

        $this->assertFalse($result['found']);
    }

    public function testAssessRejectsInvalidLikelihoodOrImpact(): void
    {
        $assessor = new SqliteRiskAssessor($this->databasePath);

        $result = $assessor->assess('option_1', 'technical', 'x', 'extreme', 'low');

        $this->assertFalse($result['found']);
    }

    public function testAssessRejectsEmptyDescription(): void
    {
        $assessor = new SqliteRiskAssessor($this->databasePath);

        $result = $assessor->assess('option_1', 'technical', '', 'low', 'low');

        $this->assertFalse($result['found']);
    }

    public function testNewAssessmentStartsOpen(): void
    {
        $assessor = new SqliteRiskAssessor($this->databasePath);
        $result = $assessor->assess('option_1', 'technical', 'x', 'low', 'low');

        $this->assertSame('open', $assessor->get($result['risk_id'])['status']);
    }

    public function testMarkMitigatedTransitionsFromOpen(): void
    {
        $assessor = new SqliteRiskAssessor($this->databasePath);
        $result = $assessor->assess('option_1', 'technical', 'x', 'high', 'high');

        $outcome = $assessor->markMitigated($result['risk_id']);

        $this->assertTrue($outcome['found']);
        $this->assertSame('mitigated', $assessor->get($result['risk_id'])['status']);
    }

    public function testMarkMitigatedRejectsWhenAlreadyMitigated(): void
    {
        $assessor = new SqliteRiskAssessor($this->databasePath);
        $result = $assessor->assess('option_1', 'technical', 'x', 'high', 'high');
        $assessor->markMitigated($result['risk_id']);

        $outcome = $assessor->markMitigated($result['risk_id']);

        $this->assertFalse($outcome['found']);
    }

    public function testAcceptRiskRequiresANonEmptyAcceptedByReference(): void
    {
        $assessor = new SqliteRiskAssessor($this->databasePath);
        $result = $assessor->assess('option_1', 'technical', 'x', 'high', 'high');

        $outcome = $assessor->acceptRisk($result['risk_id'], '');

        $this->assertFalse($outcome['found']);
        $this->assertSame('open', $assessor->get($result['risk_id'])['status']);
    }

    public function testAcceptRiskRecordsTheAuthorizer(): void
    {
        $assessor = new SqliteRiskAssessor($this->databasePath);
        $result = $assessor->assess('option_1', 'technical', 'x', 'high', 'high');

        $outcome = $assessor->acceptRisk($result['risk_id'], 'user_1');

        $this->assertTrue($outcome['found']);
        $fetched = $assessor->get($result['risk_id']);
        $this->assertSame('accepted', $fetched['status']);
        $this->assertSame('user_1', $fetched['accepted_by']);
    }

    public function testCanProceedBlocksWhileACriticalRiskIsOpen(): void
    {
        $assessor = new SqliteRiskAssessor($this->databasePath);
        $assessor->assess('option_1', 'security', 'critical issue', 'high', 'high');

        $result = $assessor->canProceed('option_1');

        $this->assertFalse($result['can_proceed']);
        $this->assertCount(1, $result['blocking_risks']);
    }

    public function testCanProceedAllowsOnceTheCriticalRiskIsMitigated(): void
    {
        $assessor = new SqliteRiskAssessor($this->databasePath);
        $critical = $assessor->assess('option_1', 'security', 'critical issue', 'high', 'high');
        $assessor->markMitigated($critical['risk_id']);

        $result = $assessor->canProceed('option_1');

        $this->assertTrue($result['can_proceed']);
    }

    public function testCanProceedAllowsOnceTheCriticalRiskIsAccepted(): void
    {
        $assessor = new SqliteRiskAssessor($this->databasePath);
        $critical = $assessor->assess('option_1', 'security', 'critical issue', 'high', 'high');
        $assessor->acceptRisk($critical['risk_id'], 'user_1');

        $result = $assessor->canProceed('option_1');

        $this->assertTrue($result['can_proceed']);
    }

    public function testCanProceedIsUnaffectedByNonCriticalOpenRisks(): void
    {
        $assessor = new SqliteRiskAssessor($this->databasePath);
        $assessor->assess('option_1', 'technical', 'minor issue', 'low', 'low');

        $result = $assessor->canProceed('option_1');

        $this->assertTrue($result['can_proceed']);
    }

    public function testListFiltersByOptionCategoryStatusAndRiskLevel(): void
    {
        $assessor = new SqliteRiskAssessor($this->databasePath);
        $assessor->assess('option_1', 'technical', 'a', 'low', 'low');
        $critical = $assessor->assess('option_1', 'security', 'b', 'high', 'high');
        $assessor->assess('option_2', 'technical', 'c', 'low', 'low');
        $assessor->markMitigated($critical['risk_id']);

        $this->assertCount(2, $assessor->list(['option_reference' => 'option_1']));
        $this->assertCount(1, $assessor->list(['category' => 'security']));
        $this->assertCount(1, $assessor->list(['status' => 'mitigated']));
        $this->assertCount(1, $assessor->list(['risk_level' => 'critical']));
    }

    public function testAssessedRiskDispatchesEvent(): void
    {
        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        $events->listen('risk.assessed', new CallbackEventListener(
            function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }
        ));

        $assessor = new SqliteRiskAssessor($this->databasePath, $events);
        $assessor->assess('option_1', 'technical', 'x', 'low', 'low');

        $this->assertCount(1, $captured);
    }
}
