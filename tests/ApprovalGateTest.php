<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SquirrelForge\Automation\ApprovalGate;
use SquirrelForge\Automation\AutomationValidator;
use SquirrelForge\Automation\SqliteAutomationGovernance;

final class ApprovalGateTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-approval-gate-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    public function testWithNoRequiredCategoriesTheGatePasses(): void
    {
        $gate = new ApprovalGate();

        $result = $gate->checkGate('auto_1', []);

        $this->assertSame('pass', $result['outcome']);
    }

    public function testMissingReferenceForARequiredCategoryIsIncomplete(): void
    {
        $gate = new ApprovalGate();

        $result = $gate->checkGate('auto_1', [], ['required_categories' => ['security']]);

        $this->assertSame('incomplete', $result['outcome']);
        $this->assertSame(['security'], $result['missing_categories']);
    }

    public function testScopeMismatchIsTreatedAsIncomplete(): void
    {
        $gate = new ApprovalGate();

        $result = $gate->checkGate('auto_1', ['security' => ['status' => 'approved', 'scope' => 'staging']], [
            'required_categories' => ['security'],
            'scope' => 'production',
        ]);

        $this->assertSame('incomplete', $result['outcome']);
    }

    public function testWildcardScopeAlwaysMatches(): void
    {
        $gate = new ApprovalGate();

        $result = $gate->checkGate('auto_1', ['security' => ['status' => 'approved', 'scope' => '*']], [
            'required_categories' => ['security'],
            'scope' => 'production',
        ]);

        $this->assertSame('pass', $result['outcome']);
    }

    public function testRevokedReferenceBlocksProgression(): void
    {
        $gate = new ApprovalGate();

        $result = $gate->checkGate('auto_1', ['security' => ['status' => 'revoked']], ['required_categories' => ['security']]);

        $this->assertSame('revoked-reference', $result['outcome']);
        $this->assertSame(['security'], $result['revoked_categories']);
    }

    public function testDeniedReferenceIsBlocked(): void
    {
        $gate = new ApprovalGate();

        $result = $gate->checkGate('auto_1', ['security' => ['status' => 'denied']], ['required_categories' => ['security']]);

        $this->assertSame('blocked', $result['outcome']);
    }

    public function testPendingReferenceIsPending(): void
    {
        $gate = new ApprovalGate();

        $result = $gate->checkGate('auto_1', ['security' => ['status' => 'pending']], ['required_categories' => ['security']]);

        $this->assertSame('pending', $result['outcome']);
    }

    public function testApprovedReferencePastItsValidUntilIsExpired(): void
    {
        $now = new DateTimeImmutable('2026-07-29T12:00:00Z');
        $gate = new ApprovalGate(clock: fn() => $now);

        $result = $gate->checkGate('auto_1', ['security' => ['status' => 'approved', 'valid_until' => '2026-07-29T10:00:00Z']], ['required_categories' => ['security']]);

        $this->assertSame('expired-reference', $result['outcome']);
    }

    public function testApprovedReferenceBeforeItsValidFromIsExpired(): void
    {
        $now = new DateTimeImmutable('2026-07-29T08:00:00Z');
        $gate = new ApprovalGate(clock: fn() => $now);

        $result = $gate->checkGate('auto_1', ['security' => ['status' => 'approved', 'valid_from' => '2026-07-29T10:00:00Z']], ['required_categories' => ['security']]);

        $this->assertSame('expired-reference', $result['outcome']);
    }

    public function testApprovedReferenceWithinItsWindowPasses(): void
    {
        $now = new DateTimeImmutable('2026-07-29T12:00:00Z');
        $gate = new ApprovalGate(clock: fn() => $now);

        $result = $gate->checkGate('auto_1', [
            'security' => ['status' => 'approved', 'valid_from' => '2026-07-29T09:00:00Z', 'valid_until' => '2026-07-29T17:00:00Z'],
        ], ['required_categories' => ['security']]);

        $this->assertSame('pass', $result['outcome']);
    }

    public function testMissingCategoryTakesPriorityOverARevokedOne(): void
    {
        $gate = new ApprovalGate();

        $result = $gate->checkGate('auto_1', ['security' => ['status' => 'revoked']], ['required_categories' => ['security', 'compliance']]);

        $this->assertSame('incomplete', $result['outcome']);
    }

    public function testConditionsFromAutomationGovernanceBecomeRequiredCategories(): void
    {
        $governance = new SqliteAutomationGovernance($this->tempPath('governance'), validator: new AutomationValidator());
        $governance->review('auto_1', ['definition' => [
            'success_criteria' => ['x'],
            'failure_handling' => 'y',
            'references' => [
                'test' => true, 'platform_validation' => true, 'security' => true,
                'policy' => true, 'observability_coverage' => true, 'recovery_plan' => false,
            ],
        ]]);
        $gate = new ApprovalGate($governance);

        $incomplete = $gate->checkGate('auto_1', []);
        $satisfied = $gate->checkGate('auto_1', ['recovery_plan' => ['status' => 'approved']]);

        $this->assertSame('incomplete', $incomplete['outcome']);
        $this->assertSame(['recovery_plan'], $incomplete['required_categories']);
        $this->assertSame('conditional-pass', $satisfied['outcome']);
    }

    public function testFullApprovalWithoutAConditionedGovernanceRecordIsAPlainPass(): void
    {
        $governance = new SqliteAutomationGovernance($this->tempPath('governance'));
        $governance->review('auto_1');
        $gate = new ApprovalGate($governance);

        $result = $gate->checkGate('auto_1', [], ['required_categories' => []]);

        $this->assertSame('pass', $result['outcome']);
    }
}
