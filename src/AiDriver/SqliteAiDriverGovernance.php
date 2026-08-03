<?php

declare(strict_types=1);

namespace SquirrelForge\AiDriver;

use Closure;
use DateTimeImmutable;
use PDO;
use SquirrelForge\Governance\SqlitePolicyEngine;

/**
 * Specializes platform-wide governance for the AI-driving domain, per
 * 34_AIDRIVER/AI-DRIVER-GOVERNANCE.md: reviews an AI decision-lifecycle
 * request against registered policy and issues an Approve/Deny
 * governance decision, without performing reasoning or overriding the
 * general policy authority `23_GOVERNANCE`/`01_RULES` already establish.
 *
 * This spec's own "Depends On" header names only
 * `23_GOVERNANCE/POLICY-ENGINE.md` and `01_RULES` as real build
 * dependencies -- everything else in its much broader "Inputs" list
 * (AI decision records, prompt compilation reports, model routing
 * reports, safety evaluations, observability reports, risk
 * assessments, compliance requirements) is evidence a caller may
 * attach to a review request, not something this class composes or
 * computes itself. In particular, `risk_classification` is
 * caller-supplied rather than a genuine `SqliteRiskAssessor` call: Risk
 * Assessor is listed only under Inputs, not Depends On, the same
 * distinction TradeoffAnalyzer and RuleEvaluator already honor
 * elsewhere in this codebase.
 *
 * The actual policy decision is never re-derived here: review()
 * delegates entirely to the real SqlitePolicyEngine::evaluate(), the
 * same authority RuleEvaluator already composes, and simply maps its
 * five-state decision vocabulary (allowed / allowed_with_conditions /
 * denied / requires_additional_review / permanently_prohibited /
 * deferred) onto this spec's own binary Audit Requirement of
 * "Governance decision (Approve/Deny)" -- the finer-grained Policy
 * Engine decision is preserved separately as `governance_action` so no
 * information is lost in that flattening.
 *
 * A caller-supplied `risk_classification` of "critical" forces a Deny
 * even when policy allows: no concrete risk-threshold algorithm is
 * given in this spec's own Risk Management section, so this reuses the
 * same "critical blocks by default" threshold `SqliteRiskAssessor`
 * already enforces via canProceed(), applied here to caller-supplied
 * evidence rather than a live query, since Risk Assessor is not a
 * declared build dependency of this component.
 *
 * "Schedule the next governance review" and "Coordinate AI governance
 * reviews" are intentionally not implemented: no scheduler is a
 * declared dependency of this component, and inventing one would be
 * fabricating authority this spec does not grant.
 *
 * Owns its own database (`Sqlite` prefix): the Audit Requirements name
 * a specific structured record shape (operation ID, AI request ID, AI
 * component, policies evaluated, governance decision, risk
 * classification, final outcome) every review() call records
 * unconditionally, including denials caused by missing configuration
 * or a malformed request, per "Continue audit recording."
 */
final class SqliteAiDriverGovernance
{
    private const DECISION_BY_POLICY_DECISION = [
        'allowed' => 'Approve',
        'allowed_with_conditions' => 'Approve',
        'denied' => 'Deny',
        'requires_additional_review' => 'Deny',
        'permanently_prohibited' => 'Deny',
        'deferred' => 'Deny',
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
            'CREATE TABLE IF NOT EXISTS ai_governance_operations (
                ai_governance_operation_id TEXT PRIMARY KEY,
                ai_request_id TEXT NOT NULL,
                ai_component TEXT NOT NULL,
                policies_evaluated_json TEXT NOT NULL,
                decision TEXT NOT NULL,
                governance_action TEXT NOT NULL,
                risk_classification TEXT NOT NULL,
                final_outcome TEXT NOT NULL,
                reason TEXT,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array{ai_request_id?: string, ai_component?: string, policy_context?: array<string, mixed>, policy_category?: ?string, risk_classification?: string} $request
     * @return array{ai_governance_operation_id: string, decision: string, governance_action: string, risk_classification: string, policies_evaluated: array<int, string>, error: ?string}
     */
    public function review(array $request): array
    {
        $operationId = 'ai_gov_op_' . bin2hex(random_bytes(12));
        $now = $this->now();
        $requestId = $request['ai_request_id'] ?? 'unknown';
        $component = $request['ai_component'] ?? 'unknown';
        $riskClassification = $request['risk_classification'] ?? 'unclassified';

        foreach (['ai_request_id', 'ai_component', 'policy_context'] as $field) {
            if (!array_key_exists($field, $request)) {
                return $this->finish($operationId, $now, $requestId, $component, [], 'Deny', 'invalid_request', $riskClassification, sprintf('Required field "%s" is missing from the governance review request.', $field));
            }
        }

        if ($this->policyEngine === null) {
            return $this->finish($operationId, $now, $requestId, $component, [], 'Deny', 'not_configured', $riskClassification, 'AI Driver Governance has no configured Policy Engine; denying by default.');
        }

        $policyDecision = $this->policyEngine->evaluate($requestId, $request['policy_context'], $request['policy_category'] ?? null);
        $governanceAction = $policyDecision['decision'];
        $decision = self::DECISION_BY_POLICY_DECISION[$governanceAction] ?? 'Deny';
        $policiesEvaluated = array_column($policyDecision['applicable_policies'], 'policy_id');

        if ($riskClassification === 'critical' && $decision === 'Approve') {
            return $this->finish($operationId, $now, $requestId, $component, $policiesEvaluated, 'Deny', 'denied_critical_risk', $riskClassification, 'A critical risk classification blocks approval regardless of policy outcome.');
        }

        return $this->finish($operationId, $now, $requestId, $component, $policiesEvaluated, $decision, $governanceAction, $riskClassification, $policyDecision['rationale']);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $operationId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM ai_governance_operations WHERE ai_governance_operation_id = :id');
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
     * @return array{ai_governance_operation_id: string, decision: string, governance_action: string, risk_classification: string, policies_evaluated: array<int, string>, error: ?string}
     */
    private function finish(
        string $operationId,
        DateTimeImmutable $now,
        string $requestId,
        string $component,
        array $policiesEvaluated,
        string $decision,
        string $governanceAction,
        string $riskClassification,
        ?string $reason
    ): array {
        $finalOutcome = $decision === 'Approve' ? 'approved' : 'denied';

        $statement = $this->database->prepare(
            'INSERT INTO ai_governance_operations (
                ai_governance_operation_id, ai_request_id, ai_component, policies_evaluated_json,
                decision, governance_action, risk_classification, final_outcome, reason, created_at
            ) VALUES (
                :id, :ai_request_id, :ai_component, :policies_evaluated_json,
                :decision, :governance_action, :risk_classification, :final_outcome, :reason, :created_at
            )'
        );
        $statement->execute([
            'id' => $operationId,
            'ai_request_id' => $requestId,
            'ai_component' => $component,
            'policies_evaluated_json' => json_encode($policiesEvaluated, JSON_THROW_ON_ERROR),
            'decision' => $decision,
            'governance_action' => $governanceAction,
            'risk_classification' => $riskClassification,
            'final_outcome' => $finalOutcome,
            'reason' => $reason,
            'created_at' => $now->format(DATE_RFC3339),
        ]);

        return [
            'ai_governance_operation_id' => $operationId,
            'decision' => $decision,
            'governance_action' => $governanceAction,
            'risk_classification' => $riskClassification,
            'policies_evaluated' => $policiesEvaluated,
            'error' => $governanceAction === 'invalid_request' ? $reason : null,
        ];
    }
}
