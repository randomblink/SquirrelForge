<?php

declare(strict_types=1);

namespace SquirrelForge\Integration;

use Closure;
use DateTimeImmutable;
use PDO;
use SquirrelForge\Governance\SqlitePolicyEngine;

/**
 * Reviews integration proposals and issues integration-domain governance
 * decisions for `INTEGRATION-MANAGER.md` to consume, per
 * 26_INTEGRATIONS/INTEGRATION-GOVERNANCE.md -- the third real component
 * in 26_INTEGRATIONS, after IntegrationAuthentication and
 * SqliteConnectorManager.
 *
 * Follows the same real pattern already established by
 * `SqliteAiDriverGovernance`, `SqliteResilienceGovernance`,
 * `SqliteCommunicationGovernance`, and `SqliteDataGovernance`: the
 * actual decision is delegated entirely to the real
 * `SqlitePolicyEngine::evaluate()`. This spec's own seven-value
 * Governance Decisions table is the richest of any governance
 * component built so far, but six of its seven values still map 1:1
 * onto Policy Engine's six-state decision vocabulary the same way
 * `SqliteDataGovernance` maps its own six: `allowed` -> Approved,
 * `allowed_with_conditions` -> Approved with Conditions,
 * `requires_additional_review` -> Requires Additional Evidence,
 * `deferred` -> Deferred, `denied` -> Rejected, `permanently_prohibited`
 * -> Prohibited.
 *
 * The seventh value, "Exception Approved," has no Policy Engine
 * equivalent -- it is not a policy-evaluation outcome, it is a distinct
 * caller-initiated request this spec names separately ("documented
 * scope, reason, and expiration or review requirements"). It can only
 * ever upgrade a `denied` policy outcome (Rejected) to Exception
 * Approved, and only when the caller supplies a well-formed
 * `exception_request` (non-empty `scope`, `reason`, and
 * `expiration_or_review_date`) -- it can never upgrade
 * `permanently_prohibited` (Prohibited), mirroring Policy Engine's own
 * "must never ignore higher-priority security policies" stance toward
 * its highest-severity `prohibit` effect. A `denied` outcome is a
 * reviewable rejection; a `permanently_prohibited` one is not.
 *
 * `21_CONFIGURATION`/`24_SECURITY`/`19_REASONING/RISK-ASSESSOR.md`/
 * `26_INTEGRATIONS/CONNECTOR-MANAGER.md`/
 * `26_INTEGRATIONS/SERVICE-DISCOVERY.md` are all named dependencies,
 * but the Governance Inputs section is explicit that this component
 * "reviews supplied evidence... it does not replace the owner that
 * produced that evidence" -- so, unlike Policy Engine, none of them are
 * live-called here. `connector_evidence`, `security_evidence`,
 * `risk_assessment`, `compliance_evidence`, and `service_discovery_evidence`
 * are all caller-supplied references recorded as evidence, never a
 * query this class performs against SqliteConnectorManager or
 * SqliteRiskAssessor directly, upholding Rule 2 ("must consume...
 * evidence from the authoritative owners") literally.
 *
 * Missing baseline request fields (`requesting_component`,
 * `external_service_ref`, `policy_context`) resolve to "Requires
 * Additional Evidence," not "Rejected" -- unlike
 * SqliteCommunicationGovernance/SqliteDataGovernance, which reuse their
 * narrower Reject/Rejected outcome for any malformed request. This
 * spec's own decision vocabulary names an outcome that fits caller
 * evidence gaps exactly ("Required evidence is missing or
 * insufficient"), so using it is a closer match than borrowing Reject.
 * An unconfigured Policy Engine is different: that is this component's
 * own configuration failing, not a caller evidence gap, so it stays
 * "Rejected" -- the same fail-closed stance Policy Engine's own
 * "no configured RuleEngine... denied" rule already takes.
 *
 * Owns its own database (`Sqlite` prefix), the same "a private
 * decision-record database is not the same as owning 37_STORAGE's
 * shared infrastructure" stance every other governance component in
 * this codebase already takes toward the identical Boundary language.
 */
final class SqliteIntegrationGovernance
{
    private const DECISION_BY_POLICY_DECISION = [
        'allowed' => 'Approved',
        'allowed_with_conditions' => 'Approved with Conditions',
        'requires_additional_review' => 'Requires Additional Evidence',
        'deferred' => 'Deferred',
        'denied' => 'Rejected',
        'permanently_prohibited' => 'Prohibited',
    ];

    private const REQUIRED_REQUEST_FIELDS = ['requesting_component', 'external_service_ref', 'policy_context'];

    private const EVIDENCE_REFERENCE_FIELDS = [
        'connector_evidence', 'service_discovery_evidence', 'security_evidence',
        'authorization_evidence', 'risk_assessment', 'compliance_evidence',
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
            'CREATE TABLE IF NOT EXISTS integration_governance_decisions (
                governance_id TEXT PRIMARY KEY,
                integration_request_id TEXT NOT NULL,
                requesting_component TEXT NOT NULL,
                external_service_ref TEXT NOT NULL,
                decision TEXT NOT NULL,
                rationale TEXT,
                conditions_applied_json TEXT NOT NULL,
                evidence_references_json TEXT NOT NULL,
                exception_json TEXT,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array{integration_request_id?: string, requesting_component?: string, external_service_ref?: string, policy_context?: array<string, mixed>, policy_category?: ?string, connector_evidence?: mixed, service_discovery_evidence?: mixed, security_evidence?: mixed, authorization_evidence?: mixed, risk_assessment?: mixed, compliance_evidence?: mixed, exception_request?: array{scope?: string, reason?: string, expiration_or_review_date?: string}} $request
     * @return array{governance_id: string, decision: string, rationale: ?string, conditions_applied: array<int, string>, evidence_references: array<int, string>, exception: ?array{scope: string, reason: string, expiration_or_review_date: string}, error: ?string}
     */
    public function review(array $request): array
    {
        $governanceId = 'integration_gov_' . bin2hex(random_bytes(12));
        $requestId = $request['integration_request_id'] ?? 'unknown';
        $requestingComponent = $request['requesting_component'] ?? 'unknown';
        $externalServiceRef = $request['external_service_ref'] ?? 'unknown';
        $evidenceReferences = $this->evidenceReferencesPresent($request);

        foreach (self::REQUIRED_REQUEST_FIELDS as $field) {
            if (!array_key_exists($field, $request) || $request[$field] === '' || $request[$field] === []) {
                return $this->finish(
                    $governanceId,
                    $requestId,
                    $requestingComponent,
                    $externalServiceRef,
                    'Requires Additional Evidence',
                    sprintf('Required field "%s" is missing from the integration governance request.', $field),
                    [],
                    $evidenceReferences,
                    null
                );
            }
        }

        if ($this->policyEngine === null) {
            return $this->finish(
                $governanceId,
                $requestId,
                $requestingComponent,
                $externalServiceRef,
                'Rejected',
                'Integration Governance has no configured Policy Engine; rejecting by default.',
                [],
                $evidenceReferences,
                null
            );
        }

        $policyDecision = $this->policyEngine->evaluate($requestId, $request['policy_context'], $request['policy_category'] ?? null);
        $policyOutcome = $policyDecision['decision'];
        $decision = self::DECISION_BY_POLICY_DECISION[$policyOutcome] ?? 'Rejected';

        $conditionsApplied = array_values(array_map(
            static fn(array $policy): string => $policy['policy_id'],
            array_filter($policyDecision['applicable_policies'], static fn(array $policy): bool => $policy['effect'] === 'allow_with_conditions')
        ));

        $exception = null;

        if ($policyOutcome === 'denied') {
            $exception = $this->wellFormedException($request['exception_request'] ?? null);

            if ($exception !== null) {
                $decision = 'Exception Approved';
            }
        }

        return $this->finish(
            $governanceId,
            $requestId,
            $requestingComponent,
            $externalServiceRef,
            $decision,
            $policyDecision['rationale'],
            $conditionsApplied,
            $evidenceReferences,
            $exception
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $governanceId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM integration_governance_decisions WHERE governance_id = :id');
        $statement->execute(['id' => $governanceId]);
        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        return $this->hydrate($row);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findByDecision(string $decision): array
    {
        $statement = $this->database->prepare(
            'SELECT * FROM integration_governance_decisions WHERE decision = :decision ORDER BY rowid DESC'
        );
        $statement->execute(['decision' => $decision]);

        return array_map($this->hydrate(...), $statement->fetchAll());
    }

    /**
     * @param array<string, mixed> $request
     * @return array<int, string>
     */
    private function evidenceReferencesPresent(array $request): array
    {
        return array_values(array_filter(
            self::EVIDENCE_REFERENCE_FIELDS,
            static fn(string $field): bool => array_key_exists($field, $request) && $request[$field] !== null
        ));
    }

    /**
     * @param mixed $exceptionRequest
     * @return array{scope: string, reason: string, expiration_or_review_date: string}|null
     */
    private function wellFormedException($exceptionRequest): ?array
    {
        if (!is_array($exceptionRequest)) {
            return null;
        }

        $scope = $exceptionRequest['scope'] ?? '';
        $reason = $exceptionRequest['reason'] ?? '';
        $expirationOrReviewDate = $exceptionRequest['expiration_or_review_date'] ?? '';

        if (!is_string($scope) || !is_string($reason) || !is_string($expirationOrReviewDate)
            || trim($scope) === '' || trim($reason) === '' || trim($expirationOrReviewDate) === ''
        ) {
            return null;
        }

        return ['scope' => $scope, 'reason' => $reason, 'expiration_or_review_date' => $expirationOrReviewDate];
    }

    /**
     * @param array<int, string> $conditionsApplied
     * @param array<int, string> $evidenceReferences
     * @param array{scope: string, reason: string, expiration_or_review_date: string}|null $exception
     * @return array{governance_id: string, decision: string, rationale: ?string, conditions_applied: array<int, string>, evidence_references: array<int, string>, exception: ?array{scope: string, reason: string, expiration_or_review_date: string}, error: ?string}
     */
    private function finish(
        string $governanceId,
        string $requestId,
        string $requestingComponent,
        string $externalServiceRef,
        string $decision,
        ?string $rationale,
        array $conditionsApplied,
        array $evidenceReferences,
        ?array $exception
    ): array {
        $now = $this->clock !== null ? ($this->clock)() : new DateTimeImmutable();
        $error = $decision === 'Requires Additional Evidence' || $decision === 'Rejected' ? $rationale : null;

        $statement = $this->database->prepare(
            'INSERT INTO integration_governance_decisions (
                governance_id, integration_request_id, requesting_component, external_service_ref,
                decision, rationale, conditions_applied_json, evidence_references_json, exception_json, created_at
            ) VALUES (
                :governance_id, :integration_request_id, :requesting_component, :external_service_ref,
                :decision, :rationale, :conditions_applied_json, :evidence_references_json, :exception_json, :created_at
            )'
        );
        $statement->execute([
            'governance_id' => $governanceId,
            'integration_request_id' => $requestId,
            'requesting_component' => $requestingComponent,
            'external_service_ref' => $externalServiceRef,
            'decision' => $decision,
            'rationale' => $rationale,
            'conditions_applied_json' => json_encode($conditionsApplied, JSON_THROW_ON_ERROR),
            'evidence_references_json' => json_encode($evidenceReferences, JSON_THROW_ON_ERROR),
            'exception_json' => $exception === null ? null : json_encode($exception, JSON_THROW_ON_ERROR),
            'created_at' => $now->format(DATE_RFC3339),
        ]);

        return [
            'governance_id' => $governanceId,
            'decision' => $decision,
            'rationale' => $rationale,
            'conditions_applied' => $conditionsApplied,
            'evidence_references' => $evidenceReferences,
            'exception' => $exception,
            'error' => $error,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        $row['conditions_applied'] = json_decode((string) $row['conditions_applied_json'], true, flags: JSON_THROW_ON_ERROR);
        $row['evidence_references'] = json_decode((string) $row['evidence_references_json'], true, flags: JSON_THROW_ON_ERROR);
        $row['exception'] = $row['exception_json'] === null ? null : json_decode((string) $row['exception_json'], true, flags: JSON_THROW_ON_ERROR);
        unset($row['conditions_applied_json'], $row['evidence_references_json'], $row['exception_json']);

        return $row;
    }
}
