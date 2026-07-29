<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Automation\ApprovalGate;
use SquirrelForge\Automation\AutomationManager;
use SquirrelForge\Automation\AutomationValidator;
use SquirrelForge\Automation\EventListener;
use SquirrelForge\Automation\RuleEngine;
use SquirrelForge\Automation\Scheduler;
use SquirrelForge\Automation\SqliteAutomationGovernance;
use SquirrelForge\Automation\TaskOrchestrator;
use SquirrelForge\Automation\TriggerManager;
use SquirrelForge\Automation\WorkflowAutomator;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;

final class AutomationManagerTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-automation-manager-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function fullyWiredManager(?EventBus $events = null): AutomationManager
    {
        return new AutomationManager(
            new RuleEngine(),
            new EventListener(),
            new Scheduler(),
            new TriggerManager(),
            new ApprovalGate(),
            new AutomationValidator(),
            new SqliteAutomationGovernance($this->tempPath('governance')),
            new WorkflowAutomator(),
            new TaskOrchestrator(),
            $events
        );
    }

    public function testEvaluateRuleRoutesToRuleEngine(): void
    {
        $manager = $this->fullyWiredManager();

        $result = $manager->coordinate('evaluate_rule', [
            'rule' => ['id' => 'r1', 'condition' => ['type' => 'boolean', 'field' => 'ready', 'equals' => true]],
            'context' => ['ready' => true],
        ]);

        $this->assertSame('pass', $result['outcome']);
        $this->assertSame('rule_engine', $result['target_component']);
    }

    public function testEvaluateRulesRoutesToRuleEngineBatchEvaluation(): void
    {
        $manager = $this->fullyWiredManager();

        $result = $manager->coordinate('evaluate_rules', [
            'rules' => [['id' => 'r1', 'condition' => ['type' => 'boolean', 'field' => 'ready', 'equals' => true]]],
            'context' => ['ready' => true],
        ]);

        $this->assertSame('evaluated', $result['outcome']);
        $this->assertSame('pass', $result['result']['results']['r1']['result']);
    }

    public function testReceiveEventRoutesToEventListener(): void
    {
        $manager = $this->fullyWiredManager();

        $result = $manager->coordinate('receive_event', [
            'event' => ['event_id' => 'evt_1', 'source' => 'workflow-engine', 'name' => 'workflow.completed', 'payload' => []],
        ]);

        $this->assertSame('processed', $result['outcome']);
        $this->assertSame('event_listener', $result['target_component']);
    }

    public function testCheckScheduleRoutesToScheduler(): void
    {
        $manager = $this->fullyWiredManager();
        $result = $manager->coordinate('check_schedule', ['schedule_id' => 'ghost']);

        $this->assertSame('not_found', $result['outcome']);
        $this->assertSame('scheduler', $result['target_component']);
    }

    public function testEvaluateTriggerRoutesToTriggerManager(): void
    {
        $manager = $this->fullyWiredManager();

        $result = $manager->coordinate('evaluate_trigger', [
            'trigger' => ['id' => 't1', 'requires' => ['event.a'], 'window_seconds' => 60],
        ]);

        $this->assertSame('deferred', $result['outcome']);
        $this->assertSame('trigger_manager', $result['target_component']);
    }

    public function testCheckApprovalRoutesToApprovalGate(): void
    {
        $manager = $this->fullyWiredManager();

        $result = $manager->coordinate('check_approval', ['automation_id' => 'auto_1']);

        $this->assertSame('pass', $result['outcome']);
        $this->assertSame('approval_gate', $result['target_component']);
    }

    public function testValidateAutomationRoutesToAutomationValidator(): void
    {
        $manager = $this->fullyWiredManager();

        $result = $manager->coordinate('validate_automation', ['definition' => []]);

        $this->assertSame('requires-revision', $result['outcome']);
        $this->assertSame('automation_validator', $result['target_component']);
    }

    public function testReviewGovernanceRoutesToAutomationGovernance(): void
    {
        $manager = $this->fullyWiredManager();

        $result = $manager->coordinate('review_governance', ['automation_id' => 'auto_1']);

        $this->assertSame('approved', $result['outcome']);
        $this->assertSame('automation_governance', $result['target_component']);
    }

    public function testHandoffWorkflowRoutesToWorkflowAutomator(): void
    {
        $manager = $this->fullyWiredManager();

        $result = $manager->coordinate('handoff_workflow', ['automation_id' => 'auto_1', 'context' => []]);

        $this->assertSame('rejected', $result['outcome']);
        $this->assertSame('workflow_automator', $result['target_component']);
        $this->assertStringContainsString('Workflow Engine', (string) $result['error']);
    }

    public function testOrchestrateTasksRoutesToTaskOrchestrator(): void
    {
        $manager = $this->fullyWiredManager();

        $result = $manager->coordinate('orchestrate_tasks', [
            'tasks' => [['id' => 'a', 'dispatch_type' => 'agent']],
            'dispatchers' => ['agent' => static fn(): string => 'ok'],
        ]);

        $this->assertSame('completed', $result['outcome']);
        $this->assertSame('task_orchestrator', $result['target_component']);
    }

    public function testUnknownOperationIsRejected(): void
    {
        $manager = $this->fullyWiredManager();

        $result = $manager->coordinate('teleport', []);

        $this->assertSame('rejected', $result['outcome']);
        $this->assertSame('none', $result['target_component']);
    }

    public function testMissingRequiredFieldIsRejectedBeforeRoutingIsAttempted(): void
    {
        $manager = $this->fullyWiredManager();

        $result = $manager->coordinate('evaluate_rule', ['rule' => ['id' => 'r1', 'condition' => []]]);

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('context', (string) $result['error']);
    }

    public function testMissingDependencyIsRejectedNotFatal(): void
    {
        $manager = new AutomationManager();

        $result = $manager->coordinate('evaluate_rule', ['rule' => ['id' => 'r1', 'condition' => []], 'context' => []]);

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('not configured', (string) $result['error']);
    }

    public function testCoordinationDispatchesEvent(): void
    {
        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        $events->listen('automation.finished', new CallbackEventListener(
            function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }
        ));

        $manager = $this->fullyWiredManager($events);
        $manager->coordinate('check_schedule', ['schedule_id' => 'ghost']);

        $this->assertCount(1, $captured);
        $this->assertSame('check_schedule', $captured[0]->getPayload()['operation']);
    }
}
