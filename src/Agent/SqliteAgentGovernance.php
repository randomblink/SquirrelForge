<?php

declare(strict_types=1);

namespace SquirrelForge\Agent;

use DateTimeImmutable;
use PDO;
use SquirrelForge\Governance\SqlitePolicyEngine;

/**
 * Evaluates escalated requests -- waivers, quality-gate exceptions, and
 * governance- or compliance-impact findings -- against the policy
 * already defined in `23_GOVERNANCE`, per
 * 16_AGENTS/AGENT-GOVERNANCE.md -- the fourth real component in
 * 16_AGENTS's governance/coordination gap.
 *
 * "Governance applies and records decisions against existing policy.
 * It does not define governance, security, or quality-gate policy
 * itself" (Purpose) is upheld by treating `SqlitePolicyEngine` as this
 * class's *entire* decision-making authority rather than a secondary
 * check: unlike `SqlitePermissions`/`SqliteModelConfig`, where an
 * absent Policy Engine still lets the rest of a component's own real
 * domain logic (a fallback chain, a duplicate check) proceed, there is
 * no separate domain logic here for Governance to fall back on -- a
 * missing Policy Engine means no policy reference is reachable at all,
 * which is exactly the Governance Outcome table's own definition of
 * `BLOCKED` ("Required policy reference, context, or authority
 * information is missing"), so that is the real, honest outcome
 * produced rather than a silent approval.
 *
 * "Confirm required authority level was applied for the decision" and
 * "must not approve a decision that exceeds the authority level
 * available to it" (Permission Boundary) are real, checked state, not
 * a returned label: the caller supplies both `required_authority_level`
 * and `held_authority_level` as evidence (this spec names no fixed
 * authority-level scale to validate against), and a mismatch forces
 * `ESCALATION_REQUIRED` *before* Policy Engine evaluation even runs --
 * a request lacking sufficient authority is never evaluated for
 * approval at all, since even an `allowed` policy decision must not be
 * applied past the authority actually held.
 *
 * `SqlitePolicyEngine::evaluate()`'s own real, six-value decision
 * vocabulary (`allowed`/`allowed_with_conditions`/`denied`/
 * `permanently_prohibited`/`requires_additional_review`/`deferred`) is
 * genuinely distinct from this spec's own five-value Governance
 * Outcome (`APPROVED`/`APPROVED_WITH_CONDITIONS`/`DENIED`/
 * `ESCALATION_REQUIRED`/`BLOCKED`) -- `permanently_prohibited` maps
 * onto `DENIED` (Governance Outcome has no separate "prohibited" tier,
 * and folding it into `DENIED` never weakens the real prohibition:
 * the request still may not proceed), `requires_additional_review`
 * maps onto `ESCALATION_REQUIRED` (both mean this decision exceeds
 * what can be resolved here), and `deferred` maps onto `BLOCKED` (both
 * mean the decision could not yet be determined), mirroring the same
 * "different owners, different real vocabularies" translation already
 * established by `SqliteMessageBus` and `AgentCommunication`.
 *
 * SQLite-backed for "support audits by keeping governance decisions
 * traceable" (Responsibilities) and the explicit Governance Record
 * table -- a second, distinct audit trail alongside
 * `SqlitePolicyEngine`'s own `policy_evaluations` table, the same
 * "two different actors recording two different aspects of one event"
 * shape `ActionDispatcher`/`FailureHandler` already established in
 * `20_EXECUTION`.
 */
final class SqliteAgentGovernance
{
    private const ESCALATING_STAGES = ['Reviewer', 'Security', 'Performance', 'Release'];

    /** Maps SqlitePolicyEngine's own real decision vocabulary onto this spec's own Governance Outcome. */
    private const DECISION_TO_OUTCOME = [
        'allowed' => 'APPROVED',
        'allowed_with_conditions' => 'APPROVED_WITH_CONDITIONS',
        'denied' => 'DENIED',
        'permanently_prohibited' => 'DENIED',
        'requires_additional_review' => 'ESCALATION_REQUIRED',
        'deferred' => 'BLOCKED',
    ];

    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly ?SqlitePolicyEngine $policyEngine = null
    ) {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS agent_governance_decisions (
                governance_id TEXT PRIMARY KEY,
                escalating_stage TEXT,
                policy_question TEXT,
                required_authority_level TEXT,
                policy_reference_json TEXT,
                decision TEXT NOT NULL,
                rationale TEXT NOT NULL,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * Governance Process steps 1-6.
     *
     * @param array{
     *     escalating_stage?: ?string,
     *     policy_question?: ?string,
     *     required_authority_level?: ?string,
     *     held_authority_level?: ?string,
     *     request_id?: ?string,
     *     context?: array<string, mixed>,
     *     category?: ?string
     * } $request
     * @return array{
     *     outcome: string,
     *     governance_id: ?string,
     *     escalating_stage: ?string,
     *     policy_reference: ?array<int, array<string, mixed>>,
     *     rationale: ?string,
     *     error: ?string
     * }
     */
    public function decide(array $request): array
    {
        $escalatingStage = $request['escalating_stage'] ?? null;

        if (!is_string($escalatingStage) || !in_array($escalatingStage, self::ESCALATING_STAGES, true)) {
            return $this->envelope('invalid', null, $escalatingStage, null, null, 'An escalation requires one of this spec\'s named Escalating Stages (Reviewer/Security/Performance/Release).');
        }

        $policyQuestion = $request['policy_question'] ?? null;
        $requiredAuthorityLevel = $request['required_authority_level'] ?? null;
        $heldAuthorityLevel = $request['held_authority_level'] ?? null;

        if (!$this->present($policyQuestion) || !$this->present($requiredAuthorityLevel) || !$this->present($heldAuthorityLevel)) {
            return $this->recordAndEnvelope('BLOCKED', $escalatingStage, $policyQuestion, $requiredAuthorityLevel, null, 'An escalation without an identified policy question and authority level must not be decided; it must be returned for clarification.');
        }

        if ($heldAuthorityLevel !== $requiredAuthorityLevel) {
            return $this->recordAndEnvelope('ESCALATION_REQUIRED', $escalatingStage, $policyQuestion, $requiredAuthorityLevel, null, sprintf('This decision requires authority level "%s"; only "%s" is held here, so it must go to a higher approval level.', $requiredAuthorityLevel, $heldAuthorityLevel));
        }

        if ($this->policyEngine === null) {
            return $this->recordAndEnvelope('BLOCKED', $escalatingStage, $policyQuestion, $requiredAuthorityLevel, null, 'No Policy Engine is configured; no policy reference is reachable for this decision.');
        }

        $requestId = $this->present($request['request_id'] ?? null) ? $request['request_id'] : 'governance_request_' . bin2hex(random_bytes(8));

        $policyDecision = $this->policyEngine->evaluate($requestId, $request['context'] ?? [], $request['category'] ?? null);
        $outcome = self::DECISION_TO_OUTCOME[$policyDecision['decision']];

        return $this->recordAndEnvelope($outcome, $escalatingStage, $policyQuestion, $requiredAuthorityLevel, $policyDecision['applicable_policies'], $policyDecision['rationale']);
    }

    /**
     * @return ?array<string, mixed>
     */
    public function get(string $governanceId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM agent_governance_decisions WHERE governance_id = :governance_id');
        $statement->execute(['governance_id' => $governanceId]);
        $row = $statement->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    /**
     * "Support audits by keeping governance decisions traceable" --
     * every recorded decision for a given escalating stage, in the
     * order it was decided.
     *
     * @return array<int, array<string, mixed>>
     */
    public function history(string $escalatingStage): array
    {
        $statement = $this->database->prepare('SELECT * FROM agent_governance_decisions WHERE escalating_stage = :escalating_stage ORDER BY rowid ASC');
        $statement->execute(['escalating_stage' => $escalatingStage]);

        return array_map(fn(array $row): array => $this->hydrate($row), $statement->fetchAll());
    }

    /**
     * @param ?array<int, array<string, mixed>> $policyReference
     * @return array{
     *     outcome: string,
     *     governance_id: ?string,
     *     escalating_stage: ?string,
     *     policy_reference: ?array<int, array<string, mixed>>,
     *     rationale: ?string,
     *     error: ?string
     * }
     */
    private function recordAndEnvelope(string $outcome, string $escalatingStage, ?string $policyQuestion, ?string $requiredAuthorityLevel, ?array $policyReference, string $rationale): array
    {
        $governanceId = 'governance_' . bin2hex(random_bytes(12));

        $statement = $this->database->prepare(
            'INSERT INTO agent_governance_decisions (
                governance_id, escalating_stage, policy_question, required_authority_level,
                policy_reference_json, decision, rationale, created_at
            ) VALUES (
                :governance_id, :escalating_stage, :policy_question, :required_authority_level,
                :policy_reference_json, :decision, :rationale, :created_at
            )'
        );
        $statement->execute([
            'governance_id' => $governanceId,
            'escalating_stage' => $escalatingStage,
            'policy_question' => $policyQuestion,
            'required_authority_level' => $requiredAuthorityLevel,
            'policy_reference_json' => json_encode($policyReference, JSON_THROW_ON_ERROR),
            'decision' => $outcome,
            'rationale' => $rationale,
            'created_at' => (new DateTimeImmutable())->format(DATE_ATOM),
        ]);

        return $this->envelope($outcome, $governanceId, $escalatingStage, $policyReference, $rationale, null);
    }

    /**
     * @param ?array<int, array<string, mixed>> $policyReference
     * @return array{
     *     outcome: string,
     *     governance_id: ?string,
     *     escalating_stage: ?string,
     *     policy_reference: ?array<int, array<string, mixed>>,
     *     rationale: ?string,
     *     error: ?string
     * }
     */
    private function envelope(string $outcome, ?string $governanceId, ?string $escalatingStage, ?array $policyReference, ?string $rationale, ?string $error): array
    {
        return [
            'outcome' => $outcome,
            'governance_id' => $governanceId,
            'escalating_stage' => $escalatingStage,
            'policy_reference' => $policyReference,
            'rationale' => $rationale,
            'error' => $error,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        $row['policy_reference'] = json_decode((string) $row['policy_reference_json'], true, flags: JSON_THROW_ON_ERROR);
        unset($row['policy_reference_json']);

        return $row;
    }

    private function present(mixed $value): bool
    {
        return is_string($value) && $value !== '';
    }
}
