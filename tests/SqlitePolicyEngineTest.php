<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Automation\RuleEngine;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;
use SquirrelForge\Governance\SqlitePolicyEngine;

final class SqlitePolicyEngineTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        $this->databasePath = sys_get_temp_dir() . '/squirrelforge-policy-' . bin2hex(random_bytes(8)) . '.sqlite';
    }

    protected function tearDown(): void
    {
        foreach ([$this->databasePath, $this->databasePath . '-shm', $this->databasePath . '-wal'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    private function boolCondition(string $field, bool $equals = true): array
    {
        return ['type' => 'boolean', 'field' => $field, 'equals' => $equals];
    }

    public function testRegisterPolicyRejectsUnknownCategory(): void
    {
        $engine = new SqlitePolicyEngine($this->databasePath);

        $result = $engine->registerPolicy('p1', ['category' => 'astrology', 'priority' => 1, 'condition' => [], 'effect' => 'allow']);

        $this->assertFalse($result['found']);
    }

    public function testRegisterPolicyRejectsUnknownEffect(): void
    {
        $engine = new SqlitePolicyEngine($this->databasePath);

        $result = $engine->registerPolicy('p1', ['category' => 'operational', 'priority' => 1, 'condition' => [], 'effect' => 'shrug']);

        $this->assertFalse($result['found']);
    }

    public function testReRegisteringAPolicyIncrementsVersionAndDeactivatesThePrior(): void
    {
        $engine = new SqlitePolicyEngine($this->databasePath);
        $engine->registerPolicy('p1', ['category' => 'operational', 'priority' => 1, 'condition' => $this->boolCondition('a'), 'effect' => 'allow']);
        $second = $engine->registerPolicy('p1', ['category' => 'operational', 'priority' => 1, 'condition' => $this->boolCondition('a'), 'effect' => 'deny']);

        $this->assertSame(2, $second['version']);
        $history = $engine->listPolicies('p1');
        $this->assertCount(2, $history);
        $this->assertSame(0, (int) $history[0]['active']);
        $this->assertSame(1, (int) $history[1]['active']);
    }

    public function testEvaluateWithoutRuleEngineConfiguredDeniesByDefault(): void
    {
        $engine = new SqlitePolicyEngine($this->databasePath);

        $result = $engine->evaluate('req_1', []);

        $this->assertSame('denied', $result['decision']);
    }

    public function testEvaluateWithNoPoliciesRegisteredDeniesByDefault(): void
    {
        $engine = new SqlitePolicyEngine($this->databasePath, new RuleEngine());

        $result = $engine->evaluate('req_1', []);

        $this->assertSame('denied', $result['decision']);
    }

    public function testAllowPolicyThatAppliesResultsInAllowed(): void
    {
        $engine = new SqlitePolicyEngine($this->databasePath, new RuleEngine());
        $engine->registerPolicy('p1', ['category' => 'workflow', 'priority' => 1, 'condition' => $this->boolCondition('ready'), 'effect' => 'allow']);

        $result = $engine->evaluate('req_1', ['ready' => true]);

        $this->assertSame('allowed', $result['decision']);
    }

    public function testPolicyWhoseConditionFailsDoesNotApplyAndRequestIsDenied(): void
    {
        $engine = new SqlitePolicyEngine($this->databasePath, new RuleEngine());
        $engine->registerPolicy('p1', ['category' => 'workflow', 'priority' => 1, 'condition' => $this->boolCondition('ready'), 'effect' => 'allow']);

        $result = $engine->evaluate('req_1', ['ready' => false]);

        $this->assertSame('denied', $result['decision']);
    }

    public function testHigherPriorityTierWinsOverALowerPriorityOpposingPolicy(): void
    {
        $engine = new SqlitePolicyEngine($this->databasePath, new RuleEngine());
        $engine->registerPolicy('low', ['category' => 'workflow', 'priority' => 1, 'condition' => $this->boolCondition('x'), 'effect' => 'allow']);
        $engine->registerPolicy('high', ['category' => 'workflow', 'priority' => 10, 'condition' => $this->boolCondition('x'), 'effect' => 'deny']);

        $result = $engine->evaluate('req_1', ['x' => true]);

        $this->assertSame('denied', $result['decision']);
        $this->assertCount(1, $result['applicable_policies']);
        $this->assertSame('high', $result['applicable_policies'][0]['policy_id']);
    }

    public function testWithinTheSameTierDenyBeatsAllow(): void
    {
        $engine = new SqlitePolicyEngine($this->databasePath, new RuleEngine());
        $engine->registerPolicy('a', ['category' => 'workflow', 'priority' => 5, 'condition' => $this->boolCondition('x'), 'effect' => 'allow']);
        $engine->registerPolicy('b', ['category' => 'workflow', 'priority' => 5, 'condition' => $this->boolCondition('x'), 'effect' => 'deny']);

        $result = $engine->evaluate('req_1', ['x' => true]);

        $this->assertSame('denied', $result['decision']);
    }

    public function testProhibitBeatsDenyWithinTheSameTier(): void
    {
        $engine = new SqlitePolicyEngine($this->databasePath, new RuleEngine());
        $engine->registerPolicy('a', ['category' => 'workflow', 'priority' => 5, 'condition' => $this->boolCondition('x'), 'effect' => 'deny']);
        $engine->registerPolicy('b', ['category' => 'workflow', 'priority' => 5, 'condition' => $this->boolCondition('x'), 'effect' => 'prohibit']);

        $result = $engine->evaluate('req_1', ['x' => true]);

        $this->assertSame('permanently_prohibited', $result['decision']);
    }

    public function testAllowWithConditionsMapsToItsOwnDecision(): void
    {
        $engine = new SqlitePolicyEngine($this->databasePath, new RuleEngine());
        $engine->registerPolicy('p1', ['category' => 'workflow', 'priority' => 1, 'condition' => $this->boolCondition('x'), 'effect' => 'allow_with_conditions']);

        $result = $engine->evaluate('req_1', ['x' => true]);

        $this->assertSame('allowed_with_conditions', $result['decision']);
    }

    public function testRequireReviewMapsToItsOwnDecision(): void
    {
        $engine = new SqlitePolicyEngine($this->databasePath, new RuleEngine());
        $engine->registerPolicy('p1', ['category' => 'workflow', 'priority' => 1, 'condition' => $this->boolCondition('x'), 'effect' => 'require_review']);

        $result = $engine->evaluate('req_1', ['x' => true]);

        $this->assertSame('requires_additional_review', $result['decision']);
    }

    public function testIndeterminatePolicyWithNoDenyAtTheSameTierIsDeferred(): void
    {
        $engine = new SqlitePolicyEngine($this->databasePath, new RuleEngine());
        $engine->registerPolicy('p1', ['category' => 'workflow', 'priority' => 1, 'condition' => $this->boolCondition('unknown_field'), 'effect' => 'allow']);

        $result = $engine->evaluate('req_1', []);

        $this->assertSame('deferred', $result['decision']);
    }

    public function testIndeterminatePolicyIsOverriddenByADenyAtTheSameTier(): void
    {
        $engine = new SqlitePolicyEngine($this->databasePath, new RuleEngine());
        $engine->registerPolicy('unsure', ['category' => 'workflow', 'priority' => 1, 'condition' => $this->boolCondition('unknown_field'), 'effect' => 'allow']);
        $engine->registerPolicy('sure', ['category' => 'workflow', 'priority' => 1, 'condition' => $this->boolCondition('x'), 'effect' => 'deny']);

        $result = $engine->evaluate('req_1', ['x' => true]);

        $this->assertSame('denied', $result['decision']);
    }

    public function testEvaluateCanBeScopedToASingleCategory(): void
    {
        $engine = new SqlitePolicyEngine($this->databasePath, new RuleEngine());
        $engine->registerPolicy('a', ['category' => 'workflow', 'priority' => 10, 'condition' => $this->boolCondition('x'), 'effect' => 'deny']);
        $engine->registerPolicy('b', ['category' => 'compliance', 'priority' => 1, 'condition' => $this->boolCondition('x'), 'effect' => 'allow']);

        $result = $engine->evaluate('req_1', ['x' => true], 'compliance');

        $this->assertSame('allowed', $result['decision']);
    }

    public function testEvaluationDispatchesEventAndCanBeRetrievedByRef(): void
    {
        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        $events->listen('policy.allowed', new CallbackEventListener(
            function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }
        ));

        $engine = new SqlitePolicyEngine($this->databasePath, new RuleEngine(), $events);
        $engine->registerPolicy('p1', ['category' => 'workflow', 'priority' => 1, 'condition' => $this->boolCondition('x'), 'effect' => 'allow']);
        $result = $engine->evaluate('req_1', ['x' => true]);

        $this->assertCount(1, $captured);
        $fetched = $engine->evaluation($result['evaluation_ref']);
        $this->assertSame('allowed', $fetched['decision']);
    }

    public function testRecentOrdersMostRecentFirstAndRespectsLimit(): void
    {
        $engine = new SqlitePolicyEngine($this->databasePath, new RuleEngine());
        $engine->evaluate('req_a', []);
        $engine->evaluate('req_b', []);

        $recent = $engine->recent(1);

        $this->assertCount(1, $recent);
        $this->assertSame('req_b', $recent[0]['request_id']);
    }
}
