<?php

declare(strict_types=1);

namespace SquirrelForge\Resilience;

use Closure;
use DateTimeImmutable;
use PDO;
use SquirrelForge\Governance\SqlitePolicyEngine;

/**
 * Establishes the policies, standards, controls, and oversight
 * governing all resilience activities, per
 * 35_RESILIENCE/RESILIENCE-GOVERNANCE.md: reviews a resilience
 * governance request and issues an Approve/Defer/Reject decision,
 * without performing any recovery activity itself.
 *
 * This spec predates the "Depends On" header convention and, unlike
 * `34_AIDRIVER/AI-DRIVER-GOVERNANCE.md`, never names a specific owning
 * component for its "Governance policies" input. Its own Governance
 * Workflow ("identify applicable policies... evaluate compliance...
 * approve, defer, or reject") is otherwise the same shape
 * AI-Driver-Governance already implements, so this class follows the
 * same pattern established there: review() delegates the actual policy
 * decision entirely to the real `SqlitePolicyEngine::evaluate()`,
 * never re-deriving it.
 *
 * Unlike AI Driver Governance's binary Approve/Deny audit requirement,
 * this spec's own "Governance Workflow" step 6 explicitly names three
 * outcomes -- "Approve, defer, or reject governance actions" -- so
 * Policy Engine's five-state decision vocabulary is mapped onto three
 * buckets instead of two: an indeterminate or requires-review policy
 * decision becomes Defer (work SquirrelForge\AiDriver\
 * SqliteAiDriverGovernance's binary model would have collapsed into
 * Deny), while an outright denial or prohibition becomes Reject. The
 * finer-grained Policy Engine decision is preserved separately as
 * `governance_action`.
 *
 * `resilience_component` is validated against this spec's own
 * Governance Scope list (the ten domains it explicitly states it
 * "applies to") rather than accepted as a free-form string, since that
 * list is closed and authoritative in a way `34_AIDRIVER/
 * AI-DRIVER-GOVERNANCE.md`'s looser "AI component" field was not.
 *
 * A caller-supplied `risk_classification` of "critical" forces a
 * Reject even when policy allows, reusing the same threshold
 * `SqliteAiDriverGovernance` and `SqliteRiskAssessor::canProceed()`
 * already enforce elsewhere in this codebase, applied here to
 * caller-supplied evidence since Risk Assessor is a Reasoning-category
 * component, not one this spec names as its own dependency.
 *
 * `reviewer` is caller-supplied identity, recorded verbatim for the
 * Audit Requirements' own "Reviewer" field -- this class does not
 * verify or authorize the reviewer itself, matching the same
 * authorization boundary AI Driver Governance and the AI Safety Gate
 * already respect toward their own identity/authorization inputs.
 *
 * "Schedule governance review" and "Coordinate governance reviews" are
 * intentionally not implemented: no scheduler is a real dependency
 * available to this component.
 *
 * Owns its own database (`Sqlite` prefix): the Audit Requirements name
 * a specific structured record shape every review() call records
 * unconditionally, including denials caused by missing configuration
 * or a malformed request, per "Continue audit recording."
 */
final class SqliteResilienceGovernance
{
    private const RESILIENCE_COMPONENTS = [
        'failure_detection', 'recovery_operations', 'rollback_operations', 'self_healing',
        'redundancy_management', 'failover_coordination', 'disaster_recovery',
        'business_continuity', 'recovery_testing', 'resilience_lifecycle_management',
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
            'CREATE TABLE IF NOT EXISTS resilience_governance_operations (
                resilience_governance_operation_id TEXT PRIMARY KEY,
                resilience_component TEXT NOT NULL,
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
     * @param array{resilience_component?: string, policy_context?: array<string, mixed>, policy_category?: ?string, risk_classification?: string, reviewer?: string} $request
     * @return array{resilience_governance_operation_id: string, decision: string, governance_action: string, risk_classification: string, policies_evaluated: array<int, string>, error: ?string}
     */
    public function review(array $request): array
    {
        $operationId = 'resilience_gov_op_' . bin2hex(random_bytes(12));
        $now = $this->now();
        $component = $request['resilience_component'] ?? 'unknown';
        $reviewer = $request['reviewer'] ?? 'unknown';
        $riskClassification = $request['risk_classification'] ?? 'unclassified';

        foreach (['resilience_component', 'policy_context', 'reviewer'] as $field) {
            if (!array_key_exists($field, $request)) {
                return $this->finish($operationId, $now, $component, [], 'Reject', 'invalid_request', $riskClassification, $reviewer, sprintf('Required field "%s" is missing from the governance review request.', $field));
            }
        }

        if (!in_array($component, self::RESILIENCE_COMPONENTS, true)) {
            return $this->finish($operationId, $now, $component, [], 'Reject', 'invalid_component', $riskClassification, $reviewer, sprintf('"%s" is not a recognized resilience governance scope.', $component));
        }

        if ($this->policyEngine === null) {
            return $this->finish($operationId, $now, $component, [], 'Reject', 'not_configured', $riskClassification, $reviewer, 'Resilience Governance has no configured Policy Engine; rejecting by default.');
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
        $statement = $this->database->prepare('SELECT * FROM resilience_governance_operations WHERE resilience_governance_operation_id = :id');
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
     * @return array{resilience_governance_operation_id: string, decision: string, governance_action: string, risk_classification: string, policies_evaluated: array<int, string>, error: ?string}
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
            'INSERT INTO resilience_governance_operations (
                resilience_governance_operation_id, resilience_component, policies_evaluated_json,
                decision, governance_action, risk_classification, reviewer, final_outcome, reason, created_at
            ) VALUES (
                :id, :resilience_component, :policies_evaluated_json,
                :decision, :governance_action, :risk_classification, :reviewer, :final_outcome, :reason, :created_at
            )'
        );
        $statement->execute([
            'id' => $operationId,
            'resilience_component' => $component,
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
            'resilience_governance_operation_id' => $operationId,
            'decision' => $decision,
            'governance_action' => $governanceAction,
            'risk_classification' => $riskClassification,
            'policies_evaluated' => $policiesEvaluated,
            'error' => $governanceAction === 'invalid_request' || $governanceAction === 'invalid_component' ? $reason : null,
        ];
    }
}
