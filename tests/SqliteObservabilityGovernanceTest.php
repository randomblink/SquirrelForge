<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Automation\RuleEngine;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;
use SquirrelForge\Governance\SqlitePolicyEngine;
use SquirrelForge\Observability\SqliteObservabilityGovernance;

final class SqliteObservabilityGovernanceTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        $this->databasePath = sys_get_temp_dir() . '/squirrelforge-observability-governance-' . bin2hex(random_bytes(8)) . '.sqlite';
    }

    protected function tearDown(): void
    {
        foreach ([$this->databasePath, $this->databasePath . '-shm', $this->databasePath . '-wal'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testDefineStandardRejectsUnknownSignalType(): void
    {
        $governance = new SqliteObservabilityGovernance($this->databasePath);

        $result = $governance->defineStandard('telepathy', ['retention_days' => 30]);

        $this->assertFalse($result['found']);
    }

    public function testDefineAndRetrieveAStandard(): void
    {
        $governance = new SqliteObservabilityGovernance($this->databasePath);
        $governance->defineStandard('log', ['retention_days' => 90, 'redaction_required' => true, 'privacy_classification' => 'internal']);

        $standard = $governance->getStandard('log');

        $this->assertSame(90, $standard['retention_days']);
        $this->assertTrue($standard['redaction_required']);
        $this->assertSame('internal', $standard['privacy_classification']);
    }

    public function testRedefiningAStandardUpdatesItInPlace(): void
    {
        $governance = new SqliteObservabilityGovernance($this->databasePath);
        $governance->defineStandard('metric', ['retention_days' => 30]);
        $governance->defineStandard('metric', ['retention_days' => 365]);

        $this->assertSame(365, $governance->getStandard('metric')['retention_days']);
    }

    public function testGetStandardReturnsNullForAnUndefinedSignalType(): void
    {
        $governance = new SqliteObservabilityGovernance($this->databasePath);

        $this->assertNull($governance->getStandard('trace'));
    }

    public function testReviewingAProposalForAnUndefinedSignalTypeRequiresRevision(): void
    {
        $governance = new SqliteObservabilityGovernance($this->databasePath);

        $result = $governance->review('proposal_1', ['signal_type' => 'alert']);

        $this->assertSame('requires_revision', $result['outcome']);
    }

    public function testReviewingAProposalForADefinedSignalTypeProceeds(): void
    {
        $governance = new SqliteObservabilityGovernance($this->databasePath);
        $governance->defineStandard('alert', ['retention_days' => 30]);

        $result = $governance->review('proposal_1', ['signal_type' => 'alert']);

        $this->assertSame('approved', $result['outcome']);
    }

    public function testExplicitPolicyResultDeniedIsRejected(): void
    {
        $governance = new SqliteObservabilityGovernance($this->databasePath);

        $result = $governance->review('proposal_1', ['policy_result' => 'denied']);

        $this->assertSame('rejected', $result['outcome']);
    }

    public function testPolicyEngineDenialRejectsWhenNoExplicitPolicyResultIsGiven(): void
    {
        $policyEngine = new SqlitePolicyEngine($this->databasePath . '-policy', new RuleEngine());
        $policyEngine->registerPolicy('p1', ['category' => 'operational', 'priority' => 1, 'condition' => ['type' => 'boolean', 'field' => 'x', 'equals' => true], 'effect' => 'deny']);
        $governance = new SqliteObservabilityGovernance($this->databasePath, $policyEngine);

        $result = $governance->review('proposal_1', ['policy_context' => ['x' => true]]);

        $this->assertSame('rejected', $result['outcome']);

        foreach ([$this->databasePath . '-policy', $this->databasePath . '-policy-shm', $this->databasePath . '-policy-wal'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testPendingSecurityDecisionIsDeferred(): void
    {
        $governance = new SqliteObservabilityGovernance($this->databasePath);

        $result = $governance->review('proposal_1', ['security_decision' => 'pending']);

        $this->assertSame('deferred', $result['outcome']);
    }

    public function testDeniedStorageEvidenceIsRejected(): void
    {
        $governance = new SqliteObservabilityGovernance($this->databasePath);

        $result = $governance->review('proposal_1', ['storage_evidence' => 'denied']);

        $this->assertSame('rejected', $result['outcome']);
    }

    public function testFullyCleanReviewWithNoOptionsIsApproved(): void
    {
        $governance = new SqliteObservabilityGovernance($this->databasePath);

        $result = $governance->review('proposal_1');

        $this->assertSame('approved', $result['outcome']);
    }

    public function testHistoryReturnsRecordsInChronologicalOrder(): void
    {
        $governance = new SqliteObservabilityGovernance($this->databasePath);
        $governance->review('proposal_1');
        $governance->review('proposal_1', ['storage_evidence' => 'denied']);

        $history = $governance->history('proposal_1');

        $this->assertCount(2, $history);
        $this->assertSame('approved', $history[0]['outcome']);
        $this->assertSame('rejected', $history[1]['outcome']);
    }

    public function testCurrentStatusReturnsNullForAnUnknownProposal(): void
    {
        $governance = new SqliteObservabilityGovernance($this->databasePath);

        $this->assertNull($governance->currentStatus('proposal_ghost'));
    }

    public function testReviewDispatchesEvent(): void
    {
        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        $events->listen('observability_governance.approved', new CallbackEventListener(
            function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }
        ));

        $governance = new SqliteObservabilityGovernance($this->databasePath, events: $events);
        $governance->review('proposal_1');

        $this->assertCount(1, $captured);
    }
}
