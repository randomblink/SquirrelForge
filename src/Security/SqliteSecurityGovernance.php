<?php

declare(strict_types=1);

namespace SquirrelForge\Security;

use Closure;
use DateTimeImmutable;
use PDO;
use SquirrelForge\Governance\SqlitePolicyEngine;

/**
 * The authoritative decision-making body for security approvals,
 * exceptions, and risk acceptance, per
 * 24_SECURITY/SECURITY-GOVERNANCE.md. "It does not perform general
 * policy evaluation, produce independent risk assessments,
 * make ordinary runtime authorization decisions, enforce controls
 * operationally, execute security operations, or modify protected
 * resources" is upheld literally: review() never touches a protected
 * resource, and the mechanical policy decision is delegated entirely
 * to the real `SqlitePolicyEngine::evaluate()` (23_GOVERNANCE, this
 * spec's own sole real "Depends On" dependency alongside 01_RULES),
 * the same pattern `SqliteAiDriverGovernance`, `SqliteResilienceGovernance`,
 * `SqliteDataGovernance`, and `SqliteCommunicationGovernance` already
 * established.
 *
 * Unlike those four siblings, this spec's own six-value Governance
 * Decisions vocabulary (Approved / Approved with Conditions / Deferred
 * / Requires Additional Evidence / Rejected / Permanently Prohibited)
 * is identical to `37_STORAGE/DATA-GOVERNANCE.md`'s, so the same
 * near-1:1 mapping SqliteDataGovernance already established is reused
 * verbatim rather than re-derived.
 *
 * `risk_assessment` and `compliance_status` are caller-supplied
 * evidence, not genuine composition: both arrive from named owning
 * components (Risk Assessor, `24_SECURITY/COMPLIANCE.md`) that are
 * listed under this spec's own "Governance Inputs", not its "Depends
 * On" header -- the same Depends-On-vs-Inputs distinction this
 * codebase applies consistently (risk_classification in the other
 * governance components, compliance_status in SqliteDataGovernance).
 * Neither is validated against a closed enum, unlike
 * SqliteDataGovernance's `data_classification`: this spec names no
 * equivalent closed vocabulary for either field.
 *
 * `conditions_applied` is real, not a placeholder: it's the list of
 * applicable policy IDs whose own effect was `allow_with_conditions`,
 * read directly from Policy Engine's real result, the same technique
 * SqliteDataGovernance already established.
 *
 * Owns its own database (`Sqlite` prefix): the Audit Requirements name
 * a specific structured record shape every review() call records
 * unconditionally, including rejections caused by missing
 * configuration or a malformed request, per "Preserve complete audit
 * history."
 */
final class SqliteSecurityGovernance
{
    private const DECISION_BY_POLICY_DECISION = [
        'allowed' => 'Approved',
        'allowed_with_conditions' => 'Approved with Conditions',
        'requires_additional_review' => 'Requires Additional Evidence',
        'deferred' => 'Deferred',
        'denied' => 'Rejected',
        'permanently_prohibited' => 'Permanently Prohibited',
    ];

    private const APPROVED_DECISIONS = ['Approved', 'Approved with Conditions'];

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
            'CREATE TABLE IF NOT EXISTS security_governance_decisions (
                governance_id TEXT PRIMARY KEY,
                request_id TEXT NOT NULL,
                decision_type TEXT NOT NULL,
                decision_rationale TEXT,
                risk_assessment TEXT,
                compliance_status TEXT NOT NULL,
                conditions_applied_json TEXT NOT NULL,
                reviewer_component TEXT,
                final_outcome TEXT NOT NULL,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array{request_id?: string, policy_context?: array<string, mixed>, policy_category?: ?string, risk_assessment?: string, compliance_status?: string, reviewer_component?: string} $request
     * @return array{governance_id: string, decision: string, conditions_applied: array<int, string>, risk_assessment: string, compliance_status: string, error: ?string}
     */
    public function review(array $request): array
    {
        $governanceId = 'security_gov_' . bin2hex(random_bytes(12));
        $requestId = $request['request_id'] ?? 'unknown';
        $reviewer = $request['reviewer_component'] ?? null;
        $riskAssessment = $request['risk_assessment'] ?? 'not_supplied';
        $complianceStatus = $request['compliance_status'] ?? 'not_evaluated';

        foreach (['request_id', 'policy_context'] as $field) {
            if (!array_key_exists($field, $request)) {
                return $this->finish($governanceId, $requestId, 'Rejected', null, $riskAssessment, $complianceStatus, [], $reviewer, sprintf('Required field "%s" is missing from the governance request.', $field));
            }
        }

        if ($this->policyEngine === null) {
            return $this->finish($governanceId, $requestId, 'Rejected', null, $riskAssessment, $complianceStatus, [], $reviewer, 'Security Governance has no configured Policy Engine; rejecting by default.');
        }

        $policyDecision = $this->policyEngine->evaluate($requestId, $request['policy_context'], $request['policy_category'] ?? null);
        $decision = self::DECISION_BY_POLICY_DECISION[$policyDecision['decision']] ?? 'Rejected';

        $conditionsApplied = array_values(array_map(
            static fn(array $policy): string => $policy['policy_id'],
            array_filter($policyDecision['applicable_policies'], static fn(array $policy): bool => $policy['effect'] === 'allow_with_conditions')
        ));

        return $this->finish($governanceId, $requestId, $decision, $policyDecision['rationale'], $riskAssessment, $complianceStatus, $conditionsApplied, $reviewer, null);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $governanceId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM security_governance_decisions WHERE governance_id = :id');
        $statement->execute(['id' => $governanceId]);
        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        $row['conditions_applied'] = json_decode($row['conditions_applied_json'], true, flags: JSON_THROW_ON_ERROR);
        unset($row['conditions_applied_json']);

        return $row;
    }

    /**
     * @param array<int, string> $conditionsApplied
     * @return array{governance_id: string, decision: string, conditions_applied: array<int, string>, risk_assessment: string, compliance_status: string, error: ?string}
     */
    private function finish(
        string $governanceId,
        string $requestId,
        string $decision,
        ?string $rationale,
        string $riskAssessment,
        string $complianceStatus,
        array $conditionsApplied,
        ?string $reviewer,
        ?string $error
    ): array {
        $now = $this->clock !== null ? ($this->clock)() : new DateTimeImmutable();
        $finalOutcome = in_array($decision, self::APPROVED_DECISIONS, true) ? 'approved' : 'not_approved';

        $statement = $this->database->prepare(
            'INSERT INTO security_governance_decisions (
                governance_id, request_id, decision_type, decision_rationale,
                risk_assessment, compliance_status, conditions_applied_json, reviewer_component, final_outcome, created_at
            ) VALUES (
                :id, :request_id, :decision_type, :decision_rationale,
                :risk_assessment, :compliance_status, :conditions_applied_json, :reviewer_component, :final_outcome, :created_at
            )'
        );
        $statement->execute([
            'id' => $governanceId,
            'request_id' => $requestId,
            'decision_type' => $decision,
            'decision_rationale' => $rationale ?? $error,
            'risk_assessment' => $riskAssessment,
            'compliance_status' => $complianceStatus,
            'conditions_applied_json' => json_encode($conditionsApplied, JSON_THROW_ON_ERROR),
            'reviewer_component' => $reviewer,
            'final_outcome' => $finalOutcome,
            'created_at' => $now->format(DATE_RFC3339),
        ]);

        return [
            'governance_id' => $governanceId,
            'decision' => $decision,
            'conditions_applied' => $conditionsApplied,
            'risk_assessment' => $riskAssessment,
            'compliance_status' => $complianceStatus,
            'error' => $error,
        ];
    }
}
