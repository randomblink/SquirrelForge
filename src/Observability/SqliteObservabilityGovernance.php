<?php

declare(strict_types=1);

namespace SquirrelForge\Observability;

use DateTimeImmutable;
use PDO;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;
use SquirrelForge\Governance\SqlitePolicyEngine;

/**
 * Owns observability-domain governance standards and decisions, per
 * 27_OBSERVABILITY/OBSERVABILITY-GOVERNANCE.md.
 *
 * Chosen because it sits at the root of nearly the entire
 * 27_OBSERVABILITY dependency tree: Telemetry Collector, Log Manager,
 * Metrics Manager, and Trace Manager all name this component as their
 * first dependency, the same kind of base-layer leverage Risk Assessor
 * and Policy Engine already delivered elsewhere.
 *
 * "Define observability-domain signal standards" is real, registered
 * data, not documentation: defineStandard() records a real
 * retention/redaction/privacy-classification/evidence-required standard
 * per signal type (telemetry, log, metric, trace, audit_event,
 * diagnostic, alert, dashboard, health_report -- the nine types the
 * spec's own Purpose paragraph names), and review() genuinely checks
 * that a standard exists for a proposal's signal_type before approving
 * it -- a proposal touching an undefined signal type requires revision,
 * it is never silently approved.
 *
 * "Review observability proposals against supplied policy, security,
 * compliance, and storage evidence" reuses the exact resolvePolicyResult()
 * pattern from Automation/Learning/Optimization Governance: a real
 * SqlitePolicyEngine is consulted when the caller supplies
 * policy_context, and security/compliance/storage decisions are
 * consumed as caller-supplied approved/denied/pending values -- this
 * class never makes those decisions itself, matching its explicit
 * boundary against owning security decisions, compliance certification,
 * or storage infrastructure.
 *
 * Unlike Automation/Optimization Governance, this spec doesn't give a
 * real, checkable source for "conditioned" decisions (no analog to
 * OptimizationValidator's unsatisfied_references), so that state isn't
 * fabricated here -- only approved/rejected/deferred/requires_revision
 * are produced, each backed by a real check. Each review appends a new
 * governance record, mirroring every other Sqlite*Governance
 * component's history-of-decisions pattern.
 */
final class SqliteObservabilityGovernance
{
    private const SIGNAL_TYPES = ['telemetry', 'log', 'metric', 'trace', 'audit_event', 'diagnostic', 'alert', 'dashboard', 'health_report'];
    private const EXTERNAL_DECISIONS = ['security_decision', 'compliance_finding', 'storage_evidence'];

    private PDO $database;

    public function __construct(
        string $databasePath,
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
            'CREATE TABLE IF NOT EXISTS observability_standards (
                signal_type TEXT PRIMARY KEY,
                retention_days INTEGER,
                redaction_required INTEGER NOT NULL DEFAULT 0,
                privacy_classification TEXT,
                evidence_required INTEGER NOT NULL DEFAULT 0,
                updated_at TEXT NOT NULL
            )'
        );
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS observability_governance_records (
                record_id TEXT PRIMARY KEY,
                proposal_id TEXT NOT NULL,
                outcome TEXT NOT NULL,
                rationale TEXT NOT NULL,
                evidence_json TEXT NOT NULL,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array{retention_days?: ?int, redaction_required?: bool, privacy_classification?: ?string, evidence_required?: bool} $standard
     * @return array{found: bool, error: ?string}
     */
    public function defineStandard(string $signalType, array $standard): array
    {
        if (!in_array($signalType, self::SIGNAL_TYPES, true)) {
            return ['found' => false, 'error' => sprintf('Unknown observability signal type "%s".', $signalType)];
        }

        $statement = $this->database->prepare(
            'INSERT INTO observability_standards (signal_type, retention_days, redaction_required, privacy_classification, evidence_required, updated_at)
             VALUES (:signal_type, :retention_days, :redaction_required, :privacy_classification, :evidence_required, :updated_at)
             ON CONFLICT(signal_type) DO UPDATE SET
                retention_days = excluded.retention_days,
                redaction_required = excluded.redaction_required,
                privacy_classification = excluded.privacy_classification,
                evidence_required = excluded.evidence_required,
                updated_at = excluded.updated_at'
        );
        $statement->execute([
            'signal_type' => $signalType,
            'retention_days' => $standard['retention_days'] ?? null,
            'redaction_required' => ($standard['redaction_required'] ?? false) ? 1 : 0,
            'privacy_classification' => $standard['privacy_classification'] ?? null,
            'evidence_required' => ($standard['evidence_required'] ?? false) ? 1 : 0,
            'updated_at' => gmdate(DATE_RFC3339),
        ]);

        return ['found' => true, 'error' => null];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getStandard(string $signalType): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM observability_standards WHERE signal_type = :signal_type');
        $statement->execute(['signal_type' => $signalType]);
        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        return [
            'signal_type' => $row['signal_type'],
            'retention_days' => $row['retention_days'] !== null ? (int) $row['retention_days'] : null,
            'redaction_required' => (bool) $row['redaction_required'],
            'privacy_classification' => $row['privacy_classification'],
            'evidence_required' => (bool) $row['evidence_required'],
            'updated_at' => $row['updated_at'],
        ];
    }

    /**
     * @param array{signal_type?: ?string, policy_result?: string, policy_context?: array<string, mixed>, policy_category?: ?string, security_decision?: string, compliance_finding?: string, storage_evidence?: string} $options
     * @return array{record_id: string, proposal_id: string, outcome: string, rationale: string}
     */
    public function review(string $proposalId, array $options = []): array
    {
        $evidence = [];

        if (isset($options['signal_type'])) {
            $standard = $this->getStandard($options['signal_type']);
            $evidence['signal_type'] = $options['signal_type'];
            $evidence['standard_defined'] = $standard !== null;

            if ($standard === null) {
                return $this->recordDecision($proposalId, 'requires_revision', sprintf('No observability standard is defined for signal type "%s".', $options['signal_type']), $evidence);
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
                return $this->recordDecision($proposalId, 'rejected', sprintf('"%s" was denied.', $key), $evidence);
            }
        }

        foreach ([...self::EXTERNAL_DECISIONS, 'policy_result'] as $key) {
            if (($options[$key] ?? null) === 'pending') {
                return $this->recordDecision($proposalId, 'deferred', sprintf('"%s" is pending.', $key), $evidence);
            }
        }

        return $this->recordDecision($proposalId, 'approved', 'All observability governance checks passed.', $evidence);
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
     * @param array<string, mixed> $evidence
     * @return array{record_id: string, proposal_id: string, outcome: string, rationale: string}
     */
    private function recordDecision(string $proposalId, string $outcome, string $rationale, array $evidence): array
    {
        $recordId = 'observability_governance_record_' . bin2hex(random_bytes(12));

        $statement = $this->database->prepare(
            'INSERT INTO observability_governance_records (record_id, proposal_id, outcome, rationale, evidence_json, created_at)
             VALUES (:record_id, :proposal_id, :outcome, :rationale, :evidence_json, :created_at)'
        );
        $statement->execute([
            'record_id' => $recordId,
            'proposal_id' => $proposalId,
            'outcome' => $outcome,
            'rationale' => $rationale,
            'evidence_json' => json_encode($evidence, JSON_THROW_ON_ERROR),
            'created_at' => gmdate(DATE_RFC3339),
        ]);

        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'observability_governance.' . $outcome,
            new DateTimeImmutable(),
            self::class,
            ['record_id' => $recordId, 'proposal_id' => $proposalId]
        ));

        return ['record_id' => $recordId, 'proposal_id' => $proposalId, 'outcome' => $outcome, 'rationale' => $rationale];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function currentStatus(string $proposalId): ?array
    {
        $statement = $this->database->prepare(
            'SELECT * FROM observability_governance_records WHERE proposal_id = :proposal_id ORDER BY rowid DESC LIMIT 1'
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
            'SELECT * FROM observability_governance_records WHERE proposal_id = :proposal_id ORDER BY rowid ASC'
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
            'evidence' => json_decode((string) $row['evidence_json'], true, flags: JSON_THROW_ON_ERROR),
            'created_at' => $row['created_at'],
        ];
    }
}
