<?php

declare(strict_types=1);

namespace SquirrelForge\Automation;

use DateTimeImmutable;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;

/**
 * Top-level coordinator for the Automation Layer, per
 * 33_AUTOMATION/AUTOMATION-MANAGER.md, mirroring how
 * SqliteResilienceManager, SqliteCommunicationManager, SqliteDataManager,
 * and KnowledgeManager coordinate their own layers.
 *
 * Every one of the nine components the spec lists as a dependency is
 * real by the time this class exists, so -- unlike every prior
 * top-level coordinator this session -- there are no "no implemented
 * component, escalate" branches here. Each operation routes to its one
 * real owning specialist: evaluate_rule(s) to RuleEngine, receive_event
 * to EventListener, check_schedule to Scheduler, evaluate_trigger to
 * TriggerManager, check_approval to ApprovalGate, validate_automation to
 * AutomationValidator, review_governance to SqliteAutomationGovernance,
 * handoff_workflow to WorkflowAutomator, and orchestrate_tasks to
 * TaskOrchestrator.
 *
 * "Coordination does not transfer specialist or cross-layer authority to
 * the Automation Manager" is upheld structurally: this class never
 * re-implements any specialist's logic, only forwards payloads and
 * passes back real results unmodified. Like the specialists it
 * coordinates, it owns no database -- the spec forbids "storage and
 * audit infrastructure" -- only an optional EventBusInterface
 * announcement per operation.
 */
final class AutomationManager
{
    private const REQUIRED_FIELDS = [
        'evaluate_rule' => ['rule', 'context'],
        'evaluate_rules' => ['rules', 'context'],
        'receive_event' => ['event'],
        'check_schedule' => ['schedule_id'],
        'evaluate_trigger' => ['trigger'],
        'check_approval' => ['automation_id'],
        'validate_automation' => ['definition'],
        'review_governance' => ['automation_id'],
        'handoff_workflow' => ['automation_id', 'context'],
        'orchestrate_tasks' => ['tasks', 'dispatchers'],
    ];

    public function __construct(
        private readonly ?RuleEngine $ruleEngine = null,
        private readonly ?EventListener $eventListener = null,
        private readonly ?Scheduler $scheduler = null,
        private readonly ?TriggerManager $triggerManager = null,
        private readonly ?ApprovalGate $approvalGate = null,
        private readonly ?AutomationValidator $validator = null,
        private readonly ?SqliteAutomationGovernance $governance = null,
        private readonly ?WorkflowAutomator $workflowAutomator = null,
        private readonly ?TaskOrchestrator $taskOrchestrator = null,
        private readonly ?EventBusInterface $events = null
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options forwarded to whichever specialist handles this operation
     * @return array{operation: string, target_component: string, outcome: string, error: ?string, result: mixed}
     */
    public function coordinate(string $operation, array $payload, array $options = []): array
    {
        if (!isset(self::REQUIRED_FIELDS[$operation])) {
            return $this->finish($operation, 'none', 'rejected', sprintf('Unknown automation operation "%s".', $operation), null);
        }

        $missingField = $this->firstMissingField($operation, $payload);

        if ($missingField !== null) {
            return $this->finish($operation, 'none', 'rejected', sprintf('Missing required field "%s" for operation "%s".', $missingField, $operation), null);
        }

        return match ($operation) {
            'evaluate_rule' => $this->coordinateEvaluateRule($payload),
            'evaluate_rules' => $this->coordinateEvaluateRules($payload),
            'receive_event' => $this->coordinateReceiveEvent($payload),
            'check_schedule' => $this->coordinateCheckSchedule($payload),
            'evaluate_trigger' => $this->coordinateEvaluateTrigger($payload),
            'check_approval' => $this->coordinateCheckApproval($payload, $options),
            'validate_automation' => $this->coordinateValidateAutomation($payload),
            'review_governance' => $this->coordinateReviewGovernance($payload, $options),
            'handoff_workflow' => $this->coordinateHandoffWorkflow($payload, $options),
            'orchestrate_tasks' => $this->coordinateOrchestrateTasks($payload, $options),
        };
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function firstMissingField(string $operation, array $payload): ?string
    {
        foreach (self::REQUIRED_FIELDS[$operation] as $field) {
            if (!array_key_exists($field, $payload)) {
                return $field;
            }
        }

        return null;
    }

    private function coordinateEvaluateRule(array $payload): array
    {
        if ($this->ruleEngine === null) {
            return $this->finish('evaluate_rule', 'rule_engine', 'rejected', 'Rule Engine is not configured.', null);
        }

        $result = $this->ruleEngine->evaluate($payload['rule'], $payload['context']);

        return $this->finish('evaluate_rule', 'rule_engine', $result['result'], $result['error'], $result);
    }

    private function coordinateEvaluateRules(array $payload): array
    {
        if ($this->ruleEngine === null) {
            return $this->finish('evaluate_rules', 'rule_engine', 'rejected', 'Rule Engine is not configured.', null);
        }

        $result = $this->ruleEngine->evaluateAll($payload['rules'], $payload['context']);

        return $this->finish('evaluate_rules', 'rule_engine', $result['conflicts'] === [] ? 'evaluated' : 'evaluated_with_conflicts', null, $result);
    }

    private function coordinateReceiveEvent(array $payload): array
    {
        if ($this->eventListener === null) {
            return $this->finish('receive_event', 'event_listener', 'rejected', 'Event Listener is not configured.', null);
        }

        $result = $this->eventListener->receive($payload['event']);

        return $this->finish('receive_event', 'event_listener', $result['outcome'], $result['error'], $result);
    }

    private function coordinateCheckSchedule(array $payload): array
    {
        if ($this->scheduler === null) {
            return $this->finish('check_schedule', 'scheduler', 'rejected', 'Scheduler is not configured.', null);
        }

        $result = $this->scheduler->checkDue($payload['schedule_id']);

        return $this->finish('check_schedule', 'scheduler', $result['outcome'], $result['error'], $result);
    }

    private function coordinateEvaluateTrigger(array $payload): array
    {
        if ($this->triggerManager === null) {
            return $this->finish('evaluate_trigger', 'trigger_manager', 'rejected', 'Trigger Manager is not configured.', null);
        }

        $result = $this->triggerManager->evaluate($payload['trigger'], $payload['context'] ?? [], $payload['cause_chain'] ?? []);

        return $this->finish('evaluate_trigger', 'trigger_manager', $result['outcome'], $result['error'], $result);
    }

    private function coordinateCheckApproval(array $payload, array $options): array
    {
        if ($this->approvalGate === null) {
            return $this->finish('check_approval', 'approval_gate', 'rejected', 'Approval Gate is not configured.', null);
        }

        $result = $this->approvalGate->checkGate($payload['automation_id'], $payload['references'] ?? [], $options);

        return $this->finish('check_approval', 'approval_gate', $result['outcome'], null, $result);
    }

    private function coordinateValidateAutomation(array $payload): array
    {
        if ($this->validator === null) {
            return $this->finish('validate_automation', 'automation_validator', 'rejected', 'Automation Validator is not configured.', null);
        }

        $result = $this->validator->validate($payload['definition']);

        return $this->finish('validate_automation', 'automation_validator', $result['outcome'], null, $result);
    }

    private function coordinateReviewGovernance(array $payload, array $options): array
    {
        if ($this->governance === null) {
            return $this->finish('review_governance', 'automation_governance', 'rejected', 'Automation Governance is not configured.', null);
        }

        $result = $this->governance->review($payload['automation_id'], $options);

        return $this->finish('review_governance', 'automation_governance', $result['outcome'], null, $result);
    }

    private function coordinateHandoffWorkflow(array $payload, array $options): array
    {
        if ($this->workflowAutomator === null) {
            return $this->finish('handoff_workflow', 'workflow_automator', 'rejected', 'Workflow Automator is not configured.', null);
        }

        $result = $this->workflowAutomator->handoff($payload['automation_id'], $payload['context'], $options);

        return $this->finish('handoff_workflow', 'workflow_automator', $result['outcome'], $result['error'], $result);
    }

    private function coordinateOrchestrateTasks(array $payload, array $options): array
    {
        if ($this->taskOrchestrator === null) {
            return $this->finish('orchestrate_tasks', 'task_orchestrator', 'rejected', 'Task Orchestrator is not configured.', null);
        }

        $result = $this->taskOrchestrator->orchestrate($payload['tasks'], $payload['dispatchers'], $options);

        return $this->finish('orchestrate_tasks', 'task_orchestrator', $result['outcome'], $result['error'], $result);
    }

    private function finish(string $operation, string $targetComponent, string $outcome, ?string $error, mixed $result): array
    {
        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'automation.finished',
            new DateTimeImmutable(),
            self::class,
            ['operation' => $operation, 'target_component' => $targetComponent, 'outcome' => $outcome]
        ));

        return ['operation' => $operation, 'target_component' => $targetComponent, 'outcome' => $outcome, 'error' => $error, 'result' => $result];
    }
}
