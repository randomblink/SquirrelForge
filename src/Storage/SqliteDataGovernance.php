<?php

declare(strict_types=1);

namespace SquirrelForge\Storage;

use Closure;
use DateTimeImmutable;
use PDO;
use SquirrelForge\Governance\SqlitePolicyEngine;

/**
 * Evaluates and authorizes governed data operations, per
 * 37_STORAGE/DATA-GOVERNANCE.md: "Data Governance evaluates and
 * authorizes data operations. It does not directly store, retrieve,
 * modify, or delete data." review() never touches
 * SqliteObjectStorage/SqliteDocumentStorage/SqliteVectorStorage
 * directly, upholding that boundary literally.
 *
 * Follows the same real pattern SqliteAiDriverGovernance and
 * SqliteResilienceGovernance already established: the actual decision
 * is delegated entirely to `SqlitePolicyEngine::evaluate()`. Unlike
 * those two siblings, Policy Engine's five-state decision vocabulary
 * maps almost 1:1 onto this spec's own six named Governance Decisions
 * rather than being lossily collapsed: `requires_additional_review`
 * becomes "Requires Additional Evidence" and `deferred` becomes
 * "Deferred" as two genuinely distinct outcomes (AI Driver Governance's
 * binary model would have collapsed both into a single Deny, and
 * Resilience Governance's ternary model would have collapsed both into
 * a single Defer), and `permanently_prohibited` maps directly onto this
 * spec's own literally-named "Permanently Prohibited" decision -- the
 * closest match of the three governance components built this session.
 *
 * `data_classification` is validated against this spec's own closed,
 * authoritative seven-value Data Classification list rather than
 * accepted as a free-form string.
 *
 * `conditions_applied` is real, not a placeholder: it's the list of
 * applicable policy IDs whose own effect was `allow_with_conditions`,
 * read directly from Policy Engine's real `applicable_policies` result
 * rather than a fabricated summary.
 *
 * `compliance_status` is caller-supplied evidence rather than a real
 * composition: no compliance-checking component exists in this
 * codebase to genuinely evaluate it, the same "Depends On vs Inputs"
 * distinction `risk_classification` already relies on in
 * SqliteAiDriverGovernance and SqliteResilienceGovernance.
 * `authorization_status` is derived directly from the governance
 * decision itself (`authorized` only for Approved / Approved with
 * Conditions) rather than a second, separate check, since the policy
 * decision already is this component's authorization decision.
 *
 * Owns its own database (`Sqlite` prefix): the Audit Requirements name
 * a specific structured record shape every review() call records
 * unconditionally, including rejections caused by missing
 * configuration or a malformed request, per "Preserve complete audit
 * history."
 */
final class SqliteDataGovernance
{
    private const CLASSIFICATIONS = ['public', 'internal', 'confidential', 'restricted', 'sensitive', 'regulated', 'archived'];

    private const DECISION_BY_POLICY_DECISION = [
        'allowed' => 'Approved',
        'allowed_with_conditions' => 'Approved with Conditions',
        'requires_additional_review' => 'Requires Additional Evidence',
        'deferred' => 'Deferred',
        'denied' => 'Rejected',
        'permanently_prohibited' => 'Permanently Prohibited',
    ];

    private const AUTHORIZED_DECISIONS = ['Approved', 'Approved with Conditions'];

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
            'CREATE TABLE IF NOT EXISTS data_governance_decisions (
                governance_id TEXT PRIMARY KEY,
                request_id TEXT NOT NULL,
                data_classification TEXT NOT NULL,
                decision_type TEXT NOT NULL,
                decision_rationale TEXT,
                authorization_status TEXT NOT NULL,
                compliance_status TEXT NOT NULL,
                conditions_applied_json TEXT NOT NULL,
                reviewer_component TEXT,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array{request_id?: string, data_classification?: string, policy_context?: array<string, mixed>, policy_category?: ?string, compliance_status?: string, reviewer_component?: string} $request
     * @return array{governance_id: string, decision: string, conditions_applied: array<int, string>, compliance_status: string, error: ?string}
     */
    public function review(array $request): array
    {
        $governanceId = 'data_gov_' . bin2hex(random_bytes(12));
        $requestId = $request['request_id'] ?? 'unknown';
        $classification = $request['data_classification'] ?? 'unknown';
        $reviewer = $request['reviewer_component'] ?? null;
        $complianceStatus = $request['compliance_status'] ?? 'not_evaluated';

        foreach (['request_id', 'data_classification', 'policy_context'] as $field) {
            if (!array_key_exists($field, $request)) {
                return $this->finish($governanceId, $requestId, $classification, 'Rejected', null, $complianceStatus, [], $reviewer, sprintf('Required field "%s" is missing from the governance request.', $field));
            }
        }

        if (!in_array($classification, self::CLASSIFICATIONS, true)) {
            return $this->finish($governanceId, $requestId, $classification, 'Rejected', null, $complianceStatus, [], $reviewer, sprintf('"%s" is not a recognized data classification.', $classification));
        }

        if ($this->policyEngine === null) {
            return $this->finish($governanceId, $requestId, $classification, 'Rejected', null, $complianceStatus, [], $reviewer, 'Data Governance has no configured Policy Engine; rejecting by default.');
        }

        $policyDecision = $this->policyEngine->evaluate($requestId, $request['policy_context'], $request['policy_category'] ?? null);
        $decision = self::DECISION_BY_POLICY_DECISION[$policyDecision['decision']] ?? 'Rejected';

        $conditionsApplied = array_values(array_map(
            static fn(array $policy): string => $policy['policy_id'],
            array_filter($policyDecision['applicable_policies'], static fn(array $policy): bool => $policy['effect'] === 'allow_with_conditions')
        ));

        return $this->finish($governanceId, $requestId, $classification, $decision, $policyDecision['rationale'], $complianceStatus, $conditionsApplied, $reviewer, null);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $governanceId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM data_governance_decisions WHERE governance_id = :id');
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
     * @return array{governance_id: string, decision: string, conditions_applied: array<int, string>, compliance_status: string, error: ?string}
     */
    private function finish(
        string $governanceId,
        string $requestId,
        string $classification,
        string $decision,
        ?string $rationale,
        string $complianceStatus,
        array $conditionsApplied,
        ?string $reviewer,
        ?string $error
    ): array {
        $now = $this->clock !== null ? ($this->clock)() : new DateTimeImmutable();
        $authorizationStatus = in_array($decision, self::AUTHORIZED_DECISIONS, true) ? 'authorized' : 'not_authorized';

        $statement = $this->database->prepare(
            'INSERT INTO data_governance_decisions (
                governance_id, request_id, data_classification, decision_type, decision_rationale,
                authorization_status, compliance_status, conditions_applied_json, reviewer_component, created_at
            ) VALUES (
                :id, :request_id, :data_classification, :decision_type, :decision_rationale,
                :authorization_status, :compliance_status, :conditions_applied_json, :reviewer_component, :created_at
            )'
        );
        $statement->execute([
            'id' => $governanceId,
            'request_id' => $requestId,
            'data_classification' => $classification,
            'decision_type' => $decision,
            'decision_rationale' => $rationale ?? $error,
            'authorization_status' => $authorizationStatus,
            'compliance_status' => $complianceStatus,
            'conditions_applied_json' => json_encode($conditionsApplied, JSON_THROW_ON_ERROR),
            'reviewer_component' => $reviewer,
            'created_at' => $now->format(DATE_RFC3339),
        ]);

        return [
            'governance_id' => $governanceId,
            'decision' => $decision,
            'conditions_applied' => $conditionsApplied,
            'compliance_status' => $complianceStatus,
            'error' => $error,
        ];
    }
}
