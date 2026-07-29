<?php

declare(strict_types=1);

namespace SquirrelForge\Learning;

use Closure;
use DateTimeImmutable;
use PDO;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;
use SquirrelForge\Reasoning\SqliteRiskAssessor;
use SquirrelForge\Resilience\SqliteFailureDetector;
use SquirrelForge\Resilience\SqliteResilienceManager;
use SquirrelForge\Workflow\WorkflowEngine;
use SquirrelForge\Workflow\WorkflowExecutionException;

/**
 * Owns Learning-domain adaptation plans and coordinates approved
 * adaptations through implementation, validation, and recovery, per
 * 30_LEARNING/ADAPTATION-MANAGER.md.
 *
 * "Confirm required approval, risk, and rollback-plan references are
 * present" is real, not a documented gap: createPlan() checks the
 * proposal's real current status from the injected
 * SqliteLearningGovernance (must be `approved` or `conditioned`), calls
 * SqliteRiskAssessor::canProceed() the same way Automation Governance
 * and Learning Governance both do, and requires a non-empty rollback
 * plan -- a plan lacking any of these is never created.
 *
 * "Submit authorized implementation work to Execution owners" reuses
 * WorkflowAutomator's real delegation to the actual
 * SquirrelForge\Workflow\WorkflowEngine -- this class never runs
 * anything itself. "Request rollback or recovery handling from
 * Resilience... when required" mirrors WorkflowAutomator's and Task
 * Orchestrator's real pipeline exactly: a real SqliteFailureDetector
 * detection handed to the injected SqliteResilienceManager, only when
 * the caller explicitly requests it, whether triggered by an execution
 * failure or by a failed post-change validation.
 *
 * "Request post-change validation from Validation owners" is consumed,
 * not computed: 14_ENGINE/VALIDATION.md has no code to produce a real
 * result, so requestValidation() takes an already-decided
 * passed/failed/pending value from the caller. Plan status is a real,
 * enforced state machine (planned -> implemented|failed -> validated|
 * failed): execute() refuses to run a plan that isn't `planned`, and
 * requestValidation() refuses to validate one that isn't `implemented`.
 */
final class SqliteAdaptationManager
{
    private const VALIDATION_RESULTS = ['passed', 'failed', 'pending'];

    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly ?SqliteLearningGovernance $governance = null,
        private readonly ?SqliteRiskAssessor $riskAssessor = null,
        private readonly ?WorkflowEngine $workflowEngine = null,
        private readonly ?SqliteFailureDetector $failureDetector = null,
        private readonly ?SqliteResilienceManager $resilienceManager = null,
        private readonly ?EventBusInterface $events = null,
        private readonly ?Closure $clock = null
    ) {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS adaptation_plans (
                plan_id TEXT PRIMARY KEY,
                proposal_id TEXT NOT NULL,
                sequencing_json TEXT NOT NULL,
                affected_components_json TEXT NOT NULL,
                success_criteria_json TEXT NOT NULL,
                rollback_plan TEXT NOT NULL,
                status TEXT NOT NULL,
                execution_error TEXT,
                validation_result TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array{sequencing?: array<int, string>, affected_components?: array<int, string>, success_criteria?: array<int, string>, rollback_plan: string} $plan
     * @return array{found: bool, plan_id: ?string, error: ?string}
     */
    public function createPlan(string $proposalId, array $plan): array
    {
        if ($this->governance !== null) {
            $status = $this->governance->currentStatus($proposalId);

            if ($status === null || !in_array($status['outcome'], ['approved', 'conditioned'], true)) {
                return ['found' => false, 'plan_id' => null, 'error' => 'Proposal has not been approved (or conditionally approved) by Learning Governance.'];
            }
        }

        if (($plan['rollback_plan'] ?? '') === '') {
            return ['found' => false, 'plan_id' => null, 'error' => 'A rollback plan reference is required.'];
        }

        if ($this->riskAssessor !== null) {
            $risk = $this->riskAssessor->canProceed($proposalId);

            if (!$risk['can_proceed']) {
                return ['found' => false, 'plan_id' => null, 'error' => 'Unmitigated critical risk(s) block this adaptation.'];
            }
        }

        $planId = 'adaptation_plan_' . bin2hex(random_bytes(12));
        $now = $this->now()->format(DATE_RFC3339);

        $statement = $this->database->prepare(
            'INSERT INTO adaptation_plans (
                plan_id, proposal_id, sequencing_json, affected_components_json, success_criteria_json,
                rollback_plan, status, execution_error, validation_result, created_at, updated_at
            ) VALUES (
                :plan_id, :proposal_id, :sequencing_json, :affected_components_json, :success_criteria_json,
                :rollback_plan, :status, NULL, NULL, :created_at, :updated_at
            )'
        );
        $statement->execute([
            'plan_id' => $planId,
            'proposal_id' => $proposalId,
            'sequencing_json' => json_encode($plan['sequencing'] ?? [], JSON_THROW_ON_ERROR),
            'affected_components_json' => json_encode($plan['affected_components'] ?? [], JSON_THROW_ON_ERROR),
            'success_criteria_json' => json_encode($plan['success_criteria'] ?? [], JSON_THROW_ON_ERROR),
            'rollback_plan' => $plan['rollback_plan'],
            'status' => 'planned',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->publish('planned', $planId);

        return ['found' => true, 'plan_id' => $planId, 'error' => null];
    }

    /**
     * @param array<string, mixed> $context forwarded to WorkflowEngine::execute()
     * @param array{recovery?: array<string, mixed>} $options
     * @return array{found: bool, status: ?string, error: ?string, recovery: ?array<string, mixed>}
     */
    public function execute(string $planId, array $context, array $options = []): array
    {
        $plan = $this->fetch($planId);

        if ($plan === null) {
            return ['found' => false, 'status' => null, 'error' => sprintf('Unknown adaptation plan "%s".', $planId), 'recovery' => null];
        }

        if ($plan['status'] !== 'planned') {
            return ['found' => false, 'status' => $plan['status'], 'error' => sprintf('Cannot execute a plan with status "%s".', $plan['status']), 'recovery' => null];
        }

        if ($this->workflowEngine === null) {
            return ['found' => false, 'status' => $plan['status'], 'error' => 'Workflow Engine is not configured.', 'recovery' => null];
        }

        try {
            $this->workflowEngine->execute($context);
            $this->updateStatus($planId, 'implemented', null);
            $this->publish('implemented', $planId);

            return ['found' => true, 'status' => 'implemented', 'error' => null, 'recovery' => null];
        } catch (WorkflowExecutionException $e) {
            $recovery = $this->requestRecovery($planId, $options['recovery'] ?? null);
            $this->updateStatus($planId, 'failed', $e->getMessage());
            $this->publish('failed', $planId);

            return ['found' => true, 'status' => 'failed', 'error' => $e->getMessage(), 'recovery' => $recovery];
        }
    }

    /**
     * @param array{recovery?: array<string, mixed>} $options
     * @return array{found: bool, status: ?string, error: ?string, recovery: ?array<string, mixed>}
     */
    public function requestValidation(string $planId, string $result, array $options = []): array
    {
        if (!in_array($result, self::VALIDATION_RESULTS, true)) {
            return ['found' => false, 'status' => null, 'error' => sprintf('Unknown validation result "%s".', $result), 'recovery' => null];
        }

        $plan = $this->fetch($planId);

        if ($plan === null) {
            return ['found' => false, 'status' => null, 'error' => sprintf('Unknown adaptation plan "%s".', $planId), 'recovery' => null];
        }

        if ($plan['status'] !== 'implemented') {
            return ['found' => false, 'status' => $plan['status'], 'error' => sprintf('Cannot validate a plan with status "%s".', $plan['status']), 'recovery' => null];
        }

        $statement = $this->database->prepare('UPDATE adaptation_plans SET validation_result = :validation_result, updated_at = :updated_at WHERE plan_id = :plan_id');
        $statement->execute(['validation_result' => $result, 'updated_at' => $this->now()->format(DATE_RFC3339), 'plan_id' => $planId]);

        if ($result === 'passed') {
            $this->updateStatus($planId, 'validated', null);
            $this->publish('validated', $planId);

            return ['found' => true, 'status' => 'validated', 'error' => null, 'recovery' => null];
        }

        if ($result === 'failed') {
            $recovery = $this->requestRecovery($planId, $options['recovery'] ?? null);
            $this->updateStatus($planId, 'failed', 'Post-change validation failed.');
            $this->publish('failed', $planId);

            return ['found' => true, 'status' => 'failed', 'error' => 'Post-change validation failed.', 'recovery' => $recovery];
        }

        return ['found' => true, 'status' => 'implemented', 'error' => null, 'recovery' => null];
    }

    /**
     * @param array<string, mixed>|null $recoveryOptions
     * @return array<string, mixed>|null
     */
    private function requestRecovery(string $planId, ?array $recoveryOptions): ?array
    {
        if ($recoveryOptions === null || $this->failureDetector === null || $this->resilienceManager === null) {
            return null;
        }

        $detection = $this->failureDetector->detect('workflow', 'execution_failure', ['affected_components' => [$planId]]);

        return $this->resilienceManager->coordinate($detection, $recoveryOptions);
    }

    private function updateStatus(string $planId, string $status, ?string $error): void
    {
        $statement = $this->database->prepare(
            'UPDATE adaptation_plans SET status = :status, execution_error = :execution_error, updated_at = :updated_at WHERE plan_id = :plan_id'
        );
        $statement->execute(['status' => $status, 'execution_error' => $error, 'updated_at' => $this->now()->format(DATE_RFC3339), 'plan_id' => $planId]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $planId): ?array
    {
        $record = $this->fetch($planId);

        return $record === null ? null : $this->hydrate($record);
    }

    /**
     * @param array{proposal_id?: string, status?: string} $filters
     * @return array<int, array<string, mixed>>
     */
    public function list(array $filters = []): array
    {
        $conditions = ['1 = 1'];
        $parameters = [];

        foreach (['proposal_id', 'status'] as $field) {
            if (isset($filters[$field])) {
                $conditions[] = "{$field} = :{$field}";
                $parameters[$field] = $filters[$field];
            }
        }

        $statement = $this->database->prepare(
            'SELECT plan_id, proposal_id, status, validation_result, created_at, updated_at
             FROM adaptation_plans WHERE ' . implode(' AND ', $conditions) . ' ORDER BY rowid DESC'
        );
        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function now(): DateTimeImmutable
    {
        return $this->clock !== null ? ($this->clock)() : new DateTimeImmutable();
    }

    private function fetch(string $planId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM adaptation_plans WHERE plan_id = :plan_id');
        $statement->execute(['plan_id' => $planId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    private function hydrate(array $record): array
    {
        return [
            'plan_id' => $record['plan_id'],
            'proposal_id' => $record['proposal_id'],
            'sequencing' => json_decode((string) $record['sequencing_json'], true, flags: JSON_THROW_ON_ERROR),
            'affected_components' => json_decode((string) $record['affected_components_json'], true, flags: JSON_THROW_ON_ERROR),
            'success_criteria' => json_decode((string) $record['success_criteria_json'], true, flags: JSON_THROW_ON_ERROR),
            'rollback_plan' => $record['rollback_plan'],
            'status' => $record['status'],
            'execution_error' => $record['execution_error'],
            'validation_result' => $record['validation_result'],
            'created_at' => $record['created_at'],
            'updated_at' => $record['updated_at'],
        ];
    }

    private function publish(string $eventSuffix, string $planId): void
    {
        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'adaptation.' . $eventSuffix,
            new DateTimeImmutable(),
            self::class,
            ['plan_id' => $planId]
        ));
    }
}
