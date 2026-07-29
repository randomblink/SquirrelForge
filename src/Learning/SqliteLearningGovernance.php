<?php

declare(strict_types=1);

namespace SquirrelForge\Learning;

use DateTimeImmutable;
use PDO;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;
use SquirrelForge\Governance\SqlitePolicyEngine;
use SquirrelForge\Reasoning\SqliteRiskAssessor;

/**
 * Owns Learning-domain decisions on adaptation proposals, per
 * 30_LEARNING/LEARNING-GOVERNANCE.md.
 *
 * Genuinely wires together three real components rather than treating
 * them as documented gaps: review() checks the proposal's supporting
 * experience against SqliteEvaluationEngine's real qualification result
 * (an experience that was never evaluated, or evaluated as anything but
 * `qualified`, produces `requires_more_evidence` -- the spec's own
 * distinct outcome for exactly this case), consults PatternDetector's
 * real anomaly detection to flag a proposal for conditions when its
 * supporting evidence contains a statistical outlier, and calls
 * SqliteRiskAssessor::canProceed() the same way SqliteAutomationGovernance
 * does. Security and policy decisions are consumed as caller-supplied
 * approved/denied/pending values, since 24_SECURITY's applicable
 * decision references and 23_GOVERNANCE/POLICY-ENGINE have no code to
 * source a real one from.
 *
 * The spec names its outcome verbs inconsistently between its Purpose
 * paragraph (approve/reject/defer/condition/restrict/prohibit) and its
 * Responsibilities list (approved/conditioned/deferred/requires_more_
 * evidence/rejected/prohibited) -- this class implements the union of
 * both rather than arbitrarily dropping either: `requires_more_evidence`
 * (missing or unqualified supporting evaluation) beats `rejected`
 * (unmitigated critical risk) beats `prohibited` (a denied security or
 * policy decision -- security prohibits, risk merely causes rejection)
 * beats `deferred` (a pending security or policy decision) beats
 * `conditioned` (approved, but a real anomaly was found in the
 * supporting evidence) beats `approved`. `restrict()` is a separate
 * post-approval lifecycle action, matching how Automation Governance
 * treats its own post-approval verbs as distinct from the review()
 * ladder.
 *
 * Each review/lifecycle action appends a new governance record, mirroring
 * SqliteAutomationGovernance and SqliteRiskAssessor's own history-of-
 * decisions pattern -- this component's own domain data, not the
 * "general audit or observability infrastructure" the spec forbids
 * owning.
 */
final class SqliteLearningGovernance
{
    private const EXTERNAL_DECISIONS = ['security_decision', 'policy_result'];

    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly ?SqliteEvaluationEngine $evaluationEngine = null,
        private readonly ?PatternDetector $patternDetector = null,
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
            'CREATE TABLE IF NOT EXISTS learning_governance_records (
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
     * @param array{experience_id?: ?string, anomaly_filters?: array<string, mixed>, security_decision?: string, policy_result?: string, policy_context?: array<string, mixed>, policy_category?: ?string} $options
     * @return array{record_id: string, proposal_id: string, outcome: string, rationale: string, conditions: array<int, string>}
     */
    public function review(string $proposalId, array $options = []): array
    {
        $resolvedPolicyResult = $this->resolvePolicyResult($proposalId, $options);

        if ($resolvedPolicyResult !== null) {
            $options['policy_result'] = $resolvedPolicyResult;
        }

        $evidence = [];

        if (isset($options['experience_id']) && $this->evaluationEngine !== null) {
            $evaluations = $this->evaluationEngine->list(['experience_id' => $options['experience_id']]);
            $latestQualification = $evaluations[0]['qualification'] ?? null;
            $evidence['latest_qualification'] = $latestQualification;

            if ($latestQualification !== 'qualified') {
                return $this->recordDecision($proposalId, 'requires_more_evidence', 'Supporting experience has not been evaluated as qualified.', [], $evidence);
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

        foreach (self::EXTERNAL_DECISIONS as $key) {
            $evidence[$key] = $options[$key] ?? null;
        }

        foreach (self::EXTERNAL_DECISIONS as $key) {
            if (($options[$key] ?? null) === 'denied') {
                return $this->recordDecision($proposalId, 'prohibited', sprintf('"%s" was denied.', $key), [], $evidence);
            }
        }

        foreach (self::EXTERNAL_DECISIONS as $key) {
            if (($options[$key] ?? null) === 'pending') {
                return $this->recordDecision($proposalId, 'deferred', sprintf('"%s" is pending.', $key), [], $evidence);
            }
        }

        if (isset($options['anomaly_filters']) && $this->patternDetector !== null) {
            $anomalies = $this->patternDetector->findAnomalies($options['anomaly_filters']);
            $evidence['anomaly_count'] = count($anomalies['anomalies']);

            if ($anomalies['anomalies'] !== []) {
                $conditions = array_map(
                    static fn(array $a): string => sprintf('Review anomalous experience "%s" (z-score %.2f).', $a['experience_id'], $a['z_score']),
                    $anomalies['anomalies']
                );

                return $this->recordDecision($proposalId, 'conditioned', 'Approved subject to review of detected anomalies in supporting evidence.', $conditions, $evidence);
            }
        }

        return $this->recordDecision($proposalId, 'approved', 'All governance checks passed.', [], $evidence);
    }

    /**
     * @return array{record_id: string, proposal_id: string, outcome: string, rationale: string}
     */
    public function restrict(string $proposalId, string $restriction): array
    {
        $result = $this->recordDecision($proposalId, 'restricted', $restriction, [], []);

        return ['record_id' => $result['record_id'], 'proposal_id' => $proposalId, 'outcome' => 'restricted', 'rationale' => $restriction];
    }

    /**
     * @param array<int, string> $conditions
     * @param array<string, mixed> $evidence
     * @return array{record_id: string, proposal_id: string, outcome: string, rationale: string, conditions: array<int, string>}
     */
    /**
     * A caller-supplied `policy_result` always takes precedence -- this
     * is still consumption, not computation. Only when the caller omits
     * it but supplies `policy_context`, and a real SqlitePolicyEngine is
     * injected, is a genuine decision computed and mapped into the same
     * approved/denied/pending vocabulary the rest of review() already
     * expects.
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

    private function recordDecision(string $proposalId, string $outcome, string $rationale, array $conditions, array $evidence): array
    {
        $recordId = 'learning_governance_record_' . bin2hex(random_bytes(12));

        $statement = $this->database->prepare(
            'INSERT INTO learning_governance_records (
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
            'learning_governance.' . $outcome,
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
            'SELECT * FROM learning_governance_records WHERE proposal_id = :proposal_id ORDER BY rowid DESC LIMIT 1'
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
            'SELECT * FROM learning_governance_records WHERE proposal_id = :proposal_id ORDER BY rowid ASC'
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
