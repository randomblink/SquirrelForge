<?php

declare(strict_types=1);

namespace SquirrelForge\Optimization;

use DateTimeImmutable;
use PDO;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;
use SquirrelForge\Governance\SqlitePolicyEngine;
use SquirrelForge\Reasoning\SqliteRiskAssessor;

/**
 * Owns Optimization-domain decisions on optimization proposals, per
 * 32_OPTIMIZATION/OPTIMIZATION-GOVERNANCE.md, mirroring
 * SqliteAutomationGovernance and SqliteLearningGovernance's shape.
 *
 * Genuinely wires together three real components: review() checks the
 * proposal's real readiness from the injected OptimizationValidator
 * (its `requires_revision` and `ready_with_conditions` findings pass
 * straight through as this component's own decision verbs, since the
 * spec lists "require revision" as one of its own outcomes rather than
 * this class reinterpreting it), calls SqliteRiskAssessor::canProceed()
 * the same way every other governance component in this codebase does,
 * and -- unlike Automation/Learning Governance, which had
 * SqlitePolicyEngine retrofitted in afterward -- consumes the real
 * SqlitePolicyEngine from the start via the same resolvePolicyResult()
 * pattern: a caller-supplied `policy_result` always takes precedence,
 * and only when the caller supplies `policy_context` instead is a
 * genuine decision computed.
 *
 * Security and compliance decisions are consumed as caller-supplied
 * approved/denied/pending values, since 24_SECURITY's applicable
 * decision references and compliance findings have no code to source a
 * real one from. Each review/lifecycle action appends a new governance
 * record, mirroring every other Sqlite*Governance component's history-
 * of-decisions pattern -- this component's own domain data, not the
 * "audit-trail infrastructure" the spec forbids owning.
 */
final class SqliteOptimizationGovernance
{
    private const EXTERNAL_DECISIONS = ['security_decision', 'compliance_finding'];

    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly ?OptimizationValidator $validator = null,
        private readonly ?SqliteRiskAssessor $riskAssessor = null,
        private readonly ?SqlitePolicyEngine $policyEngine = null,
        private readonly ?EventBusInterface $events = null
    ) {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS optimization_governance_records (
                record_id TEXT PRIMARY KEY,
                proposal_id TEXT NOT NULL,
                outcome TEXT NOT NULL,
                rationale TEXT NOT NULL,
                conditions_json TEXT NOT NULL,
                evidence_json TEXT NOT NULL,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array<string, mixed> $proposal typically OptimizationEngine::createProposal()'s output
     * @param array{validator_options?: array<string, mixed>, security_decision?: string, compliance_finding?: string, policy_result?: string, policy_context?: array<string, mixed>, policy_category?: ?string} $options
     * @return array{record_id: string, proposal_id: string, outcome: string, rationale: string, conditions: array<int, string>}
     */
    public function review(string $proposalId, array $proposal, array $options = []): array
    {
        $evidence = [];

        if ($this->validator !== null) {
            $validation = $this->validator->validate($proposal, $options['validator_options'] ?? []);
            $evidence['validation_outcome'] = $validation['outcome'];

            if ($validation['outcome'] === 'not_ready') {
                return $this->recordDecision($proposalId, 'rejected', 'Optimization Validator found this proposal not ready.', [], $evidence);
            }

            if ($validation['outcome'] === 'requires_revision') {
                return $this->recordDecision($proposalId, 'requires_revision', 'Optimization Validator requires revision before review can proceed.', [], $evidence);
            }
        }

        if ($this->riskAssessor !== null) {
            $risk = $this->riskAssessor->canProceed($proposalId);
            $evidence['risk_can_proceed'] = $risk['can_proceed'];
            $evidence['blocking_risks'] = $risk['blocking_risks'];

            if (!$risk['can_proceed']) {
                return $this->recordDecision($proposalId, 'rejected', 'Unmitigated critical risk(s) block approval.', [], $evidence);
            }
        }

        $resolvedPolicyResult = $this->resolvePolicyResult($proposalId, $options);

        if ($resolvedPolicyResult !== null) {
            $options['policy_result'] = $resolvedPolicyResult;
        }

        $evidence['policy_result'] = $options['policy_result'] ?? null;

        foreach (self::EXTERNAL_DECISIONS as $key) {
            $evidence[$key] = $options[$key] ?? null;
        }

        foreach ([...self::EXTERNAL_DECISIONS, 'policy_result'] as $key) {
            if (($options[$key] ?? null) === 'denied') {
                return $this->recordDecision($proposalId, 'rejected', sprintf('"%s" was denied.', $key), [], $evidence);
            }
        }

        foreach ([...self::EXTERNAL_DECISIONS, 'policy_result'] as $key) {
            if (($options[$key] ?? null) === 'pending') {
                return $this->recordDecision($proposalId, 'deferred', sprintf('"%s" is pending.', $key), [], $evidence);
            }
        }

        if (($evidence['validation_outcome'] ?? null) === 'deferred') {
            return $this->recordDecision($proposalId, 'deferred', 'Optimization Validator deferred this proposal.', [], $evidence);
        }

        if (($evidence['validation_outcome'] ?? null) === 'ready_with_conditions') {
            $conditions = $validation['unsatisfied_references'] ?? [];

            return $this->recordDecision($proposalId, 'conditioned', 'Approved subject to conditions.', $conditions, $evidence);
        }

        return $this->recordDecision($proposalId, 'approved', 'All governance checks passed.', [], $evidence);
    }

    /**
     * @return array{record_id: string, proposal_id: string, outcome: string, rationale: string}
     */
    public function restrict(string $proposalId, string $restriction): array
    {
        return $this->lifecycleAction($proposalId, 'restricted', $restriction);
    }

    /**
     * @return array{record_id: string, proposal_id: string, outcome: string, rationale: string}
     */
    public function retire(string $proposalId, string $reason): array
    {
        return $this->lifecycleAction($proposalId, 'retired', $reason);
    }

    private function lifecycleAction(string $proposalId, string $outcome, string $reason): array
    {
        $result = $this->recordDecision($proposalId, $outcome, $reason, [], []);

        return ['record_id' => $result['record_id'], 'proposal_id' => $proposalId, 'outcome' => $outcome, 'rationale' => $reason];
    }

    /**
     * A caller-supplied `policy_result` always takes precedence -- this
     * is still consumption, not computation. Only when the caller omits
     * it but supplies `policy_context`, and a real SqlitePolicyEngine is
     * injected, is a genuine decision computed and mapped into the same
     * approved/denied/pending vocabulary the rest of review() expects.
     *
     * @param array<string, mixed> $options
     */
    private function resolvePolicyResult(string $proposalId, array $options): ?string
    {
        if (array_key_exists('policy_result', $options)) {
            return $options['policy_result'];
        }

        if ($this->policyEngine === null || !isset($options['policy_context'])) {
            return null;
        }

        $decision = $this->policyEngine->evaluate($proposalId, $options['policy_context'], $options['policy_category'] ?? null)['decision'];

        return match ($decision) {
            'allowed', 'allowed_with_conditions' => 'approved',
            'denied', 'permanently_prohibited' => 'denied',
            default => 'pending',
        };
    }

    /**
     * @param array<int, string> $conditions
     * @param array<string, mixed> $evidence
     * @return array{record_id: string, proposal_id: string, outcome: string, rationale: string, conditions: array<int, string>}
     */
    private function recordDecision(string $proposalId, string $outcome, string $rationale, array $conditions, array $evidence): array
    {
        $recordId = 'optimization_governance_record_' . bin2hex(random_bytes(12));

        $statement = $this->database->prepare(
            'INSERT INTO optimization_governance_records (
                record_id, proposal_id, outcome, rationale, conditions_json, evidence_json, created_at
            ) VALUES (
                :record_id, :proposal_id, :outcome, :rationale, :conditions_json, :evidence_json, :created_at
            )'
        );
        $statement->execute([
            'record_id' => $recordId,
            'proposal_id' => $proposalId,
            'outcome' => $outcome,
            'rationale' => $rationale,
            'conditions_json' => json_encode($conditions, JSON_THROW_ON_ERROR),
            'evidence_json' => json_encode($evidence, JSON_THROW_ON_ERROR),
            'created_at' => gmdate(DATE_RFC3339),
        ]);

        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'optimization_governance.' . $outcome,
            new DateTimeImmutable(),
            self::class,
            ['record_id' => $recordId, 'proposal_id' => $proposalId]
        ));

        return ['record_id' => $recordId, 'proposal_id' => $proposalId, 'outcome' => $outcome, 'rationale' => $rationale, 'conditions' => $conditions];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function currentStatus(string $proposalId): ?array
    {
        $statement = $this->database->prepare(
            'SELECT * FROM optimization_governance_records WHERE proposal_id = :proposal_id ORDER BY rowid DESC LIMIT 1'
        );
        $statement->execute(['proposal_id' => $proposalId]);
        $row = $statement->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function history(string $proposalId): array
    {
        $statement = $this->database->prepare(
            'SELECT * FROM optimization_governance_records WHERE proposal_id = :proposal_id ORDER BY rowid ASC'
        );
        $statement->execute(['proposal_id' => $proposalId]);

        return array_map($this->hydrate(...), $statement->fetchAll());
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        return [
            'record_id' => $row['record_id'],
            'proposal_id' => $row['proposal_id'],
            'outcome' => $row['outcome'],
            'rationale' => $row['rationale'],
            'conditions' => json_decode((string) $row['conditions_json'], true, flags: JSON_THROW_ON_ERROR),
            'evidence' => json_decode((string) $row['evidence_json'], true, flags: JSON_THROW_ON_ERROR),
            'created_at' => $row['created_at'],
        ];
    }
}
