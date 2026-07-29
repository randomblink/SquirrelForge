<?php

declare(strict_types=1);

namespace SquirrelForge\Automation;

use DateTimeImmutable;
use PDO;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;
use SquirrelForge\Governance\SqlitePolicyEngine;
use SquirrelForge\Reasoning\SqliteRiskAssessor;

/**
 * Owns Automation-domain standards, review decisions, and governance
 * records, per 33_AUTOMATION/AUTOMATION-GOVERNANCE.md.
 *
 * "Consume authoritative risk assessments... and validation findings" is
 * a real integration, not a documented gap: review() genuinely calls
 * the injected SqliteRiskAssessor::canProceed() and
 * AutomationValidator::validate() and bases its decision on their real
 * output. Security decisions, compliance findings, and policy-evaluation
 * results are consumed as caller-supplied values (`approved`/`denied`/
 * `pending`) rather than computed here -- 24_SECURITY/COMPLIANCE and
 * 23_GOVERNANCE/POLICY-ENGINE have no code to source a real decision
 * from, and this class must not "make runtime authorization decisions"
 * or "certify compliance" itself regardless.
 *
 * All seven of the spec's decision verbs are real and reachable:
 * `approved`, `rejected`, `deferred`, and `conditioned` come out of
 * review()'s deterministic priority ladder (structural validation
 * failure, then unmitigated critical risk, then a denied/pending
 * external decision, then validator-flagged conditions, else approval);
 * `restricted`, `suspended`, and `retired` are separate post-approval
 * lifecycle actions a caller invokes directly. "Automation classes
 * exempt from review" is a real, caller-configured allowlist -- empty
 * by default, meaning nothing is exempt unless explicitly configured,
 * the safe default.
 *
 * Each review/lifecycle action appends a new governance record rather
 * than overwriting one -- a history of decisions over time, mirroring
 * SqliteRiskAssessor's own risk_assessments table -- and
 * currentStatus() reports the most recent one. This is the component's
 * own domain data, not the "general audit/storage infrastructure" the
 * spec forbids owning.
 */
final class SqliteAutomationGovernance
{
    private const OUTCOMES = ['approved', 'rejected', 'deferred', 'conditioned', 'restricted', 'suspended', 'retired'];
    private const EXTERNAL_DECISIONS = ['security_decision', 'compliance_finding', 'policy_result'];

    private PDO $database;

    /**
     * @param array<int, string> $exemptClasses automation classes that skip review entirely
     */
    public function __construct(
        string $databasePath,
        private readonly array $exemptClasses = [],
        private readonly ?AutomationValidator $validator = null,
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
            'CREATE TABLE IF NOT EXISTS automation_governance_records (
                record_id TEXT PRIMARY KEY,
                automation_id TEXT NOT NULL,
                automation_class TEXT,
                outcome TEXT NOT NULL,
                rationale TEXT NOT NULL,
                conditions_json TEXT NOT NULL,
                evidence_json TEXT NOT NULL,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array{automation_class?: ?string, definition?: array<string, mixed>, security_decision?: string, compliance_finding?: string, policy_result?: string, policy_context?: array<string, mixed>, policy_category?: ?string} $options
     * @return array{record_id: string, automation_id: string, outcome: string, rationale: string, conditions: array<int, string>}
     */
    public function review(string $automationId, array $options = []): array
    {
        $automationClass = $options['automation_class'] ?? null;

        if ($automationClass !== null && in_array($automationClass, $this->exemptClasses, true)) {
            return $this->recordDecision($automationId, $automationClass, 'approved', 'Automation class is exempt from governance review.', [], []);
        }

        $resolvedPolicyResult = $this->resolvePolicyResult($automationId, $options);

        if ($resolvedPolicyResult !== null) {
            $options['policy_result'] = $resolvedPolicyResult;
        }

        $evidence = [];

        if (isset($options['definition']) && $this->validator !== null) {
            $validation = $this->validator->validate($options['definition']);
            $evidence['validation_outcome'] = $validation['outcome'];

            if ($validation['outcome'] === 'not-ready') {
                return $this->recordDecision($automationId, $automationClass, 'rejected', 'Automation definition failed validation: not ready.', [], $evidence);
            }

            if ($validation['outcome'] === 'requires-revision') {
                return $this->recordDecision($automationId, $automationClass, 'deferred', 'Automation definition requires revision before review can proceed.', [], $evidence);
            }
        }

        if ($this->riskAssessor !== null) {
            $risk = $this->riskAssessor->canProceed($automationId);
            $evidence['risk_can_proceed'] = $risk['can_proceed'];
            $evidence['blocking_risks'] = $risk['blocking_risks'];

            if (!$risk['can_proceed']) {
                return $this->recordDecision($automationId, $automationClass, 'rejected', 'Unmitigated critical risk(s) block approval.', [], $evidence);
            }
        }

        foreach (self::EXTERNAL_DECISIONS as $key) {
            $evidence[$key] = $options[$key] ?? null;
        }

        foreach (self::EXTERNAL_DECISIONS as $key) {
            if (($options[$key] ?? null) === 'denied') {
                return $this->recordDecision($automationId, $automationClass, 'rejected', sprintf('"%s" was denied.', $key), [], $evidence);
            }
        }

        foreach (self::EXTERNAL_DECISIONS as $key) {
            if (($options[$key] ?? null) === 'pending') {
                return $this->recordDecision($automationId, $automationClass, 'deferred', sprintf('"%s" is pending.', $key), [], $evidence);
            }
        }

        if (($evidence['validation_outcome'] ?? null) === 'ready-with-conditions') {
            $conditions = $validation['unsatisfied_references'] ?? [];

            return $this->recordDecision($automationId, $automationClass, 'conditioned', 'Approved subject to conditions.', $conditions, $evidence);
        }

        return $this->recordDecision($automationId, $automationClass, 'approved', 'All governance checks passed.', [], $evidence);
    }

    /**
     * @return array{record_id: string, automation_id: string, outcome: string, rationale: string}
     */
    public function restrict(string $automationId, string $restriction): array
    {
        return $this->lifecycleAction($automationId, 'restricted', $restriction);
    }

    /**
     * @return array{record_id: string, automation_id: string, outcome: string, rationale: string}
     */
    public function suspend(string $automationId, string $reason): array
    {
        return $this->lifecycleAction($automationId, 'suspended', $reason);
    }

    /**
     * @return array{record_id: string, automation_id: string, outcome: string, rationale: string}
     */
    public function retire(string $automationId, string $reason): array
    {
        return $this->lifecycleAction($automationId, 'retired', $reason);
    }

    /**
     * Decides an Automation-domain exception -- overriding what would
     * otherwise block approval -- requiring a non-empty justification
     * and grantor, the same accountability SqliteRiskAssessor::
     * acceptRisk() enforces for risk acceptance.
     *
     * @return array{found: bool, record_id: ?string, error: ?string}
     */
    public function grantException(string $automationId, string $justification, string $grantedBy): array
    {
        if ($justification === '' || $grantedBy === '') {
            return ['found' => false, 'record_id' => null, 'error' => 'An exception requires both a non-empty justification and a non-empty "granted by" reference.'];
        }

        $result = $this->recordDecision($automationId, null, 'approved', sprintf('Exception granted by "%s": %s', $grantedBy, $justification), [], ['exception' => true, 'granted_by' => $grantedBy]);

        return ['found' => true, 'record_id' => $result['record_id'], 'error' => null];
    }

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
    private function resolvePolicyResult(string $automationId, array $options): ?string
    {
        if (array_key_exists('policy_result', $options)) {
            return $options['policy_result'];
        }

        if ($this->policyEngine === null || !isset($options['policy_context'])) {
            return null;
        }

        $decision = $this->policyEngine->evaluate($automationId, $options['policy_context'], $options['policy_category'] ?? null)['decision'];

        return match ($decision) {
            'allowed', 'allowed_with_conditions' => 'approved',
            'denied', 'permanently_prohibited' => 'denied',
            default => 'pending',
        };
    }

    private function lifecycleAction(string $automationId, string $outcome, string $reason): array
    {
        return $this->recordDecision($automationId, null, $outcome, $reason, [], []);
    }

    /**
     * @param array<int, string> $conditions
     * @param array<string, mixed> $evidence
     * @return array{record_id: string, automation_id: string, outcome: string, rationale: string, conditions: array<int, string>}
     */
    private function recordDecision(string $automationId, ?string $automationClass, string $outcome, string $rationale, array $conditions, array $evidence): array
    {
        $recordId = 'governance_record_' . bin2hex(random_bytes(12));

        $statement = $this->database->prepare(
            'INSERT INTO automation_governance_records (
                record_id, automation_id, automation_class, outcome, rationale, conditions_json, evidence_json, created_at
            ) VALUES (
                :record_id, :automation_id, :automation_class, :outcome, :rationale, :conditions_json, :evidence_json, :created_at
            )'
        );
        $statement->execute([
            'record_id' => $recordId,
            'automation_id' => $automationId,
            'automation_class' => $automationClass,
            'outcome' => $outcome,
            'rationale' => $rationale,
            'conditions_json' => json_encode($conditions, JSON_THROW_ON_ERROR),
            'evidence_json' => json_encode($evidence, JSON_THROW_ON_ERROR),
            'created_at' => gmdate(DATE_RFC3339),
        ]);

        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'automation_governance.' . $outcome,
            new DateTimeImmutable(),
            self::class,
            ['record_id' => $recordId, 'automation_id' => $automationId]
        ));

        return ['record_id' => $recordId, 'automation_id' => $automationId, 'outcome' => $outcome, 'rationale' => $rationale, 'conditions' => $conditions];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function currentStatus(string $automationId): ?array
    {
        $statement = $this->database->prepare(
            'SELECT * FROM automation_governance_records WHERE automation_id = :automation_id ORDER BY rowid DESC LIMIT 1'
        );
        $statement->execute(['automation_id' => $automationId]);
        $row = $statement->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function history(string $automationId): array
    {
        $statement = $this->database->prepare(
            'SELECT * FROM automation_governance_records WHERE automation_id = :automation_id ORDER BY rowid ASC'
        );
        $statement->execute(['automation_id' => $automationId]);

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
            'automation_id' => $row['automation_id'],
            'automation_class' => $row['automation_class'],
            'outcome' => $row['outcome'],
            'rationale' => $row['rationale'],
            'conditions' => json_decode((string) $row['conditions_json'], true, flags: JSON_THROW_ON_ERROR),
            'evidence' => json_decode((string) $row['evidence_json'], true, flags: JSON_THROW_ON_ERROR),
            'created_at' => $row['created_at'],
        ];
    }
}
