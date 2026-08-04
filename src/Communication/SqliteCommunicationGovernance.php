<?php

declare(strict_types=1);

namespace SquirrelForge\Communication;

use Closure;
use DateTimeImmutable;
use PDO;
use SquirrelForge\Governance\SqlitePolicyEngine;

/**
 * Establishes the policies, standards, controls, and oversight
 * governing all communication, per
 * 36_COMMUNICATION/COMMUNICATION-GOVERNANCE.md: reviews a
 * communication governance request and issues an Approve/Defer/Reject
 * decision, without transporting messages or performing communication
 * activities itself.
 *
 * Follows the same real pattern already established by
 * `SqliteAiDriverGovernance`, `SqliteResilienceGovernance`, and
 * `SqliteDataGovernance`: the actual decision is delegated entirely to
 * the real `SqlitePolicyEngine::evaluate()`. This spec's own Governance
 * Workflow step 6 names the same three outcomes Resilience Governance
 * uses ("Approve, defer, or reject governance actions"), so Policy
 * Engine's five-state vocabulary is mapped onto Approve/Defer/Reject
 * the same way, rather than Data Governance's richer six-state mapping.
 *
 * `communication_component` is validated against this spec's own
 * closed, authoritative ten-item Governance Scope list rather than
 * accepted as a free-form string, the same discipline
 * `SqliteResilienceGovernance` applies to its own scope field.
 *
 * A caller-supplied `risk_classification` of "critical" forces a
 * Reject even when policy allows, reusing the same threshold already
 * established in every other governance component built this session.
 * `reviewer` is caller-supplied identity, recorded verbatim for the
 * Audit Requirements' own "Reviewer" field, the same boundary
 * `SqliteResilienceGovernance` already respects toward its own
 * reviewer input.
 *
 * "Schedule governance review" and "Coordinate governance reviews" are
 * intentionally not implemented: no scheduler is a real dependency of
 * this component.
 *
 * Owns its own database (`Sqlite` prefix): the Audit Requirements name
 * a specific structured record shape every review() call records
 * unconditionally, including rejections caused by missing
 * configuration or a malformed request, per "Continue audit
 * recording."
 */
final class SqliteCommunicationGovernance
{
    private const COMMUNICATION_COMPONENTS = [
        'message_routing', 'message_queuing', 'conversations', 'notifications', 'event_distribution',
        'agent_communication', 'service_messaging', 'message_validation', 'message_archiving', 'communication_lifecycle_management',
    ];

    private const DECISION_BY_POLICY_DECISION = [
        'allowed' => 'Approve',
        'allowed_with_conditions' => 'Approve',
        'requires_additional_review' => 'Defer',
        'deferred' => 'Defer',
        'denied' => 'Reject',
        'permanently_prohibited' => 'Reject',
    ];

    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly ?SqlitePolicyEngine $policyEngine = null,
        private readonly ?Closure $clock = null
    ) {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS communication_governance_operations (
                communication_governance_operation_id TEXT PRIMARY KEY,
                communication_component TEXT NOT NULL,
                policies_evaluated_json TEXT NOT NULL,
                decision TEXT NOT NULL,
                governance_action TEXT NOT NULL,
                risk_classification TEXT NOT NULL,
                reviewer TEXT NOT NULL,
                final_outcome TEXT NOT NULL,
                reason TEXT,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array{communication_component?: string, policy_context?: array<string, mixed>, policy_category?: ?string, risk_classification?: string, reviewer?: string} $request
     * @return array{communication_governance_operation_id: string, decision: string, governance_action: string, risk_classification: string, policies_evaluated: array<int, string>, error: ?string}
     */
    public function review(array $request): array
    {
        $operationId = 'comm_gov_op_' . bin2hex(random_bytes(12));
        $now = $this->now();
        $component = $request['communication_component'] ?? 'unknown';
        $reviewer = $request['reviewer'] ?? 'unknown';
        $riskClassification = $request['risk_classification'] ?? 'unclassified';

        foreach (['communication_component', 'policy_context', 'reviewer'] as $field) {
            if (!array_key_exists($field, $request)) {
                return $this->finish($operationId, $now, $component, [], 'Reject', 'invalid_request', $riskClassification, $reviewer, sprintf('Required field "%s" is missing from the governance review request.', $field));
            }
        }

        if (!in_array($component, self::COMMUNICATION_COMPONENTS, true)) {
            return $this->finish($operationId, $now, $component, [], 'Reject', 'invalid_component', $riskClassification, $reviewer, sprintf('"%s" is not a recognized communication governance scope.', $component));
        }

        if ($this->policyEngine === null) {
            return $this->finish($operationId, $now, $component, [], 'Reject', 'not_configured', $riskClassification, $reviewer, 'Communication Governance has no configured Policy Engine; rejecting by default.');
        }

        $policyDecision = $this->policyEngine->evaluate($operationId, $request['policy_context'], $request['policy_category'] ?? null);
        $governanceAction = $policyDecision['decision'];
        $decision = self::DECISION_BY_POLICY_DECISION[$governanceAction] ?? 'Reject';
        $policiesEvaluated = array_column($policyDecision['applicable_policies'], 'policy_id');

        if ($riskClassification === 'critical' && $decision === 'Approve') {
            return $this->finish($operationId, $now, $component, $policiesEvaluated, 'Reject', 'rejected_critical_risk', $riskClassification, $reviewer, 'A critical risk classification blocks approval regardless of policy outcome.');
        }

        return $this->finish($operationId, $now, $component, $policiesEvaluated, $decision, $governanceAction, $riskClassification, $reviewer, $policyDecision['rationale']);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $operationId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM communication_governance_operations WHERE communication_governance_operation_id = :id');
        $statement->execute(['id' => $operationId]);
        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        $row['policies_evaluated'] = json_decode($row['policies_evaluated_json'], true, flags: JSON_THROW_ON_ERROR);
        unset($row['policies_evaluated_json']);

        return $row;
    }

    private function now(): DateTimeImmutable
    {
        return $this->clock !== null ? ($this->clock)() : new DateTimeImmutable();
    }

    /**
     * @param array<int, string> $policiesEvaluated
     * @return array{communication_governance_operation_id: string, decision: string, governance_action: string, risk_classification: string, policies_evaluated: array<int, string>, error: ?string}
     */
    private function finish(
        string $operationId,
        DateTimeImmutable $now,
        string $component,
        array $policiesEvaluated,
        string $decision,
        string $governanceAction,
        string $riskClassification,
        string $reviewer,
        ?string $reason
    ): array {
        $finalOutcome = match ($decision) {
            'Approve' => 'approved',
            'Defer' => 'deferred',
            default => 'rejected',
        };

        $statement = $this->database->prepare(
            'INSERT INTO communication_governance_operations (
                communication_governance_operation_id, communication_component, policies_evaluated_json,
                decision, governance_action, risk_classification, reviewer, final_outcome, reason, created_at
            ) VALUES (
                :id, :communication_component, :policies_evaluated_json,
                :decision, :governance_action, :risk_classification, :reviewer, :final_outcome, :reason, :created_at
            )'
        );
        $statement->execute([
            'id' => $operationId,
            'communication_component' => $component,
            'policies_evaluated_json' => json_encode($policiesEvaluated, JSON_THROW_ON_ERROR),
            'decision' => $decision,
            'governance_action' => $governanceAction,
            'risk_classification' => $riskClassification,
            'reviewer' => $reviewer,
            'final_outcome' => $finalOutcome,
            'reason' => $reason,
            'created_at' => $now->format(DATE_RFC3339),
        ]);

        return [
            'communication_governance_operation_id' => $operationId,
            'decision' => $decision,
            'governance_action' => $governanceAction,
            'risk_classification' => $riskClassification,
            'policies_evaluated' => $policiesEvaluated,
            'error' => $governanceAction === 'invalid_request' || $governanceAction === 'invalid_component' ? $reason : null,
        ];
    }
}
