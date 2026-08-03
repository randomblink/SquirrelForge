<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SquirrelForge\AiDriver\SqliteAiSafetyGate;
use SquirrelForge\Reasoning\SqliteRiskAssessor;
use SquirrelForge\Security\StaticAuthorizationManager;

final class SqliteAiSafetyGateTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-ai-safety-gate-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    /**
     * @return array{action_id: string, action_signature: array<string, mixed>, option_reference: string}
     */
    private function action(): array
    {
        return ['action_id' => 'action_1', 'action_signature' => ['tool' => 'send_email', 'to' => 'user@example.com'], 'option_reference' => 'option_1'];
    }

    /**
     * @return array{outcome: string, action_signature: array<string, mixed>, evaluated_at: string}
     */
    private function ruleFinding(array $overrides = []): array
    {
        return array_replace([
            'outcome' => 'Passed',
            'action_signature' => ['tool' => 'send_email', 'to' => 'user@example.com'],
            'evaluated_at' => (new DateTimeImmutable())->format(DATE_RFC3339),
        ], $overrides);
    }

    /**
     * @return array{risk_id: string, risk_level: string, status: string, evaluated_at: string}
     */
    private function riskFinding(string $riskId, array $overrides = []): array
    {
        return array_replace([
            'risk_id' => $riskId,
            'risk_level' => 'low',
            'status' => 'open',
            'evaluated_at' => (new DateTimeImmutable())->format(DATE_RFC3339),
        ], $overrides);
    }

    /**
     * @return array{identity_ref: string, permission_ref: string, operation: string, resource_ref: string, correlation_id: string}
     */
    private function authorizationContext(string $permissionRef = 'permission:allowed'): array
    {
        return [
            'identity_ref' => 'identity_1',
            'permission_ref' => $permissionRef,
            'operation' => 'ai_driver.dispatch',
            'resource_ref' => 'action:action_1',
            'correlation_id' => 'correlation_1',
        ];
    }

    public function testPassesWhenEverythingStillHolds(): void
    {
        $riskAssessor = new SqliteRiskAssessor($this->tempPath('risk'));
        $assessed = $riskAssessor->assess('option_1', 'security', 'Sends an external email.', 'low', 'low');
        $gate = new SqliteAiSafetyGate($this->tempPath('gate'), $riskAssessor, new StaticAuthorizationManager());

        $result = $gate->evaluate($this->action(), $this->ruleFinding(), $this->riskFinding($assessed['risk_id']), $this->authorizationContext());

        $this->assertSame('Pass', $result['decision']);
        $this->assertNull($result['error']);
        $this->assertNotNull($gate->get($result['gate_evaluation_id']));
    }

    public function testBlocksOnRuleFindingEscalation(): void
    {
        $gate = new SqliteAiSafetyGate($this->tempPath('gate'));

        $result = $gate->evaluate($this->action(), $this->ruleFinding(['outcome' => 'Escalate']), $this->riskFinding('risk_1'), $this->authorizationContext());

        $this->assertSame('Blocked - Escalated to Rule Evaluator', $result['decision']);
        $this->assertSame('rule_evaluator', $result['re_evaluation_request']['target']);
    }

    public function testBlocksOnRuleFindingFailure(): void
    {
        $gate = new SqliteAiSafetyGate($this->tempPath('gate'));

        $result = $gate->evaluate($this->action(), $this->ruleFinding(['outcome' => 'Failed']), $this->riskFinding('risk_1'), $this->authorizationContext());

        $this->assertSame('Blocked - Escalated to Rule Evaluator', $result['decision']);
    }

    public function testBlocksOnActionSignatureMismatch(): void
    {
        $gate = new SqliteAiSafetyGate($this->tempPath('gate'));

        $result = $gate->evaluate(
            $this->action(),
            $this->ruleFinding(['action_signature' => ['tool' => 'delete_account']]),
            $this->riskFinding('risk_1'),
            $this->authorizationContext()
        );

        $this->assertSame('Blocked - Action Mismatch', $result['decision']);
    }

    public function testBlocksOnStaleRuleFinding(): void
    {
        $gate = new SqliteAiSafetyGate($this->tempPath('gate'), null, null, static fn(): DateTimeImmutable => new DateTimeImmutable('+10 minutes'));

        $result = $gate->evaluate($this->action(), $this->ruleFinding(), $this->riskFinding('risk_1'), $this->authorizationContext());

        $this->assertSame('Blocked - Stale Finding', $result['decision']);
    }

    public function testBlocksWhenRiskAssessmentNoLongerExists(): void
    {
        $riskAssessor = new SqliteRiskAssessor($this->tempPath('risk'));
        $gate = new SqliteAiSafetyGate($this->tempPath('gate'), $riskAssessor, new StaticAuthorizationManager());

        $result = $gate->evaluate($this->action(), $this->ruleFinding(), $this->riskFinding('risk_unknown'), $this->authorizationContext());

        $this->assertSame('Blocked - Stale Finding', $result['decision']);
    }

    public function testBlocksWhenRiskLevelHasDriftedSinceEvaluation(): void
    {
        $riskAssessor = new SqliteRiskAssessor($this->tempPath('risk'));
        $assessed = $riskAssessor->assess('option_1', 'security', 'Sends an external email.', 'high', 'high');
        $gate = new SqliteAiSafetyGate($this->tempPath('gate'), $riskAssessor, new StaticAuthorizationManager());

        $result = $gate->evaluate($this->action(), $this->ruleFinding(), $this->riskFinding($assessed['risk_id'], ['risk_level' => 'low']), $this->authorizationContext());

        $this->assertSame('Blocked - Stale Finding', $result['decision']);
    }

    public function testEscalatesToRiskAssessorWhenACriticalRiskRemainsOpen(): void
    {
        $riskAssessor = new SqliteRiskAssessor($this->tempPath('risk'));
        $assessed = $riskAssessor->assess('option_1', 'security', 'Sends an external email.', 'high', 'high');
        $this->assertSame('critical', $assessed['risk_level']);
        $gate = new SqliteAiSafetyGate($this->tempPath('gate'), $riskAssessor, new StaticAuthorizationManager());

        $result = $gate->evaluate($this->action(), $this->ruleFinding(), $this->riskFinding($assessed['risk_id'], ['risk_level' => 'critical']), $this->authorizationContext());

        $this->assertSame('Blocked - Escalated to Risk Assessor', $result['decision']);
        $this->assertSame('risk_assessor', $result['re_evaluation_request']['target']);
    }

    public function testPassesWhenACriticalRiskHasBeenMitigated(): void
    {
        $riskAssessor = new SqliteRiskAssessor($this->tempPath('risk'));
        $assessed = $riskAssessor->assess('option_1', 'security', 'Sends an external email.', 'high', 'high');
        $riskAssessor->markMitigated($assessed['risk_id']);
        $gate = new SqliteAiSafetyGate($this->tempPath('gate'), $riskAssessor, new StaticAuthorizationManager());

        $result = $gate->evaluate($this->action(), $this->ruleFinding(), $this->riskFinding($assessed['risk_id'], ['risk_level' => 'critical', 'status' => 'mitigated']), $this->authorizationContext());

        $this->assertSame('Pass', $result['decision']);
    }

    public function testBlocksOnRevokedAuthorization(): void
    {
        $riskAssessor = new SqliteRiskAssessor($this->tempPath('risk'));
        $assessed = $riskAssessor->assess('option_1', 'security', 'Sends an external email.', 'low', 'low');
        $gate = new SqliteAiSafetyGate($this->tempPath('gate'), $riskAssessor, new StaticAuthorizationManager());

        $result = $gate->evaluate($this->action(), $this->ruleFinding(), $this->riskFinding($assessed['risk_id']), $this->authorizationContext('permission:denied'));

        $this->assertSame('Blocked - Authorization Revoked', $result['decision']);
    }

    public function testDefaultsToBlockedWhenARequiredFieldIsMissing(): void
    {
        $gate = new SqliteAiSafetyGate($this->tempPath('gate'));

        $result = $gate->evaluate(['action_id' => 'action_1'], $this->ruleFinding(), $this->riskFinding('risk_1'), $this->authorizationContext());

        $this->assertSame('Blocked - Gate Evaluation Failed', $result['decision']);
        $this->assertNotNull($result['error']);
    }

    public function testEveryEvaluationIsRecordedIncludingBlockedOutcomes(): void
    {
        $gate = new SqliteAiSafetyGate($this->tempPath('gate'));

        $result = $gate->evaluate($this->action(), $this->ruleFinding(['outcome' => 'Failed']), $this->riskFinding('risk_1'), $this->authorizationContext());
        $record = $gate->get($result['gate_evaluation_id']);

        $this->assertSame('blocked', $record['final_outcome']);
        $this->assertSame('action_1', $record['action_id']);
    }

    public function testGateWithNoRiskAssessorOrAuthorizationConfiguredStillPasses(): void
    {
        $gate = new SqliteAiSafetyGate($this->tempPath('gate'));

        $result = $gate->evaluate($this->action(), $this->ruleFinding(), $this->riskFinding('risk_1'), $this->authorizationContext());

        $this->assertSame('Pass', $result['decision']);
    }
}
