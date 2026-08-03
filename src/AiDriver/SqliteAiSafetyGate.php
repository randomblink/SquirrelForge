<?php

declare(strict_types=1);

namespace SquirrelForge\AiDriver;

use Closure;
use DateTimeImmutable;
use PDO;
use SquirrelForge\Contracts\AuthorizationManagerInterface;
use SquirrelForge\Reasoning\SqliteRiskAssessor;

/**
 * The final pre-dispatch checkpoint for AI-driven actions, per
 * 34_AIDRIVER/AI-SAFETY-GATE.md: re-confirms that an already-produced
 * rule finding and risk finding still hold for the concrete action
 * about to be dispatched, and that authorization has not since been
 * revoked -- it never re-decides any of the three itself.
 *
 * `19_REASONING/RULE-EVALUATOR.md`'s own real implementation is a pure,
 * stateless return-value class with no persistence of its own (see its
 * docblock), so a rule finding cannot be "retrieved" by reference the
 * way the spec's Inputs section might suggest -- it is caller-supplied
 * data instead, exactly as Rule Evaluator handed it back to whichever
 * component (`19_REASONING/AI-DRIVER.md`) requested the evaluation.
 * That finding envelope must additionally carry `action_signature` and
 * `evaluated_at`, since Rule Evaluator itself has no concept of "the
 * action" (only rules and a context) or of time -- both are attached by
 * the caller when the finding was first produced, and re-supplied here
 * so the Gate has something concrete to re-confirm against.
 *
 * `19_REASONING/RISK-ASSESSOR.md`'s real implementation
 * (SqliteRiskAssessor) does own persistence, so its finding *is*
 * re-confirmed against live state: fetch(risk_id) is compared against
 * the risk_level/status the caller captured at evaluation time (drift
 * is real staleness, not fabricated), and canProceed(option_reference)
 * -- the same real "no unmitigated critical risk" gate Risk Assessor
 * already enforces -- is reused directly rather than reimplemented, to
 * decide the "Escalated to Risk Assessor" outcome.
 *
 * Authorization is re-confirmed by literally re-calling
 * AuthorizationManagerInterface::authorize() with the same identity,
 * permission, operation, and resource the original decision used --
 * this class never grants, denies, or interprets that decision itself,
 * it only reads whatever the Authorization Manager currently says.
 *
 * A rule finding whose own outcome is `Escalate` or `Failed` is treated
 * as "no longer a green light" and routed to the "Escalated to Rule
 * Evaluator" decision -- the spec's own Gate Decisions vocabulary names
 * only one escalation bucket per owning component, not a separate
 * "rule failed" bucket, and a Failed finding no longer applying is the
 * same real-world condition as a stale one.
 *
 * Owns its own database (`Sqlite` prefix): the Audit Requirements name
 * a specific structured record shape (gate evaluation ID, timestamp,
 * action ID, rule/risk finding references, authorization status
 * reference, gate decision, final outcome) that a generic audit log
 * would flatten; every evaluate() call is unconditionally recorded,
 * including failures, per "Maintain audit continuity."
 */
final class SqliteAiSafetyGate
{
    private const DEFAULT_MAX_FINDING_AGE_SECONDS = 300;

    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly ?SqliteRiskAssessor $riskAssessor = null,
        private readonly ?AuthorizationManagerInterface $authorization = null,
        private readonly ?Closure $clock = null
    ) {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS gate_evaluations (
                gate_evaluation_id TEXT PRIMARY KEY,
                action_id TEXT NOT NULL,
                rule_finding_reference TEXT,
                risk_finding_reference TEXT,
                authorization_status_reference TEXT,
                decision TEXT NOT NULL,
                final_outcome TEXT NOT NULL,
                reason TEXT,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array{action_id: string, action_signature: array<string, mixed>, option_reference?: ?string} $action
     * @param array{outcome: string, action_signature: array<string, mixed>, evaluated_at: string} $ruleFinding
     * @param array{risk_id: string, risk_level: string, status: string, evaluated_at: string} $riskFinding
     * @param array{identity_ref: string, permission_ref: string, operation: string, resource_ref: string, correlation_id: string} $authorizationContext
     * @param array{max_finding_age_seconds?: int} $options
     * @return array{gate_evaluation_id: string, decision: string, report: ?array<string, mixed>, re_evaluation_request: ?array{target: string, reason: string}, error: ?string}
     */
    public function evaluate(array $action, array $ruleFinding, array $riskFinding, array $authorizationContext, array $options = []): array
    {
        $gateEvaluationId = 'gate_eval_' . bin2hex(random_bytes(12));
        $now = $this->now();
        $maxAge = $options['max_finding_age_seconds'] ?? self::DEFAULT_MAX_FINDING_AGE_SECONDS;

        $missingField = $this->firstMissingField($action, $ruleFinding, $riskFinding, $authorizationContext);

        if ($missingField !== null) {
            return $this->finish($gateEvaluationId, $now, $action['action_id'] ?? 'unknown', null, null, null, 'Blocked - Gate Evaluation Failed', sprintf('Required field "%s" is missing from the gate evaluation request.', $missingField));
        }

        if ($ruleFinding['outcome'] === 'Escalate' || $ruleFinding['outcome'] === 'Failed') {
            return $this->finish(
                $gateEvaluationId,
                $now,
                $action['action_id'],
                $ruleFinding['evaluated_at'],
                null,
                null,
                'Blocked - Escalated to Rule Evaluator',
                sprintf('The rule finding\'s own outcome is "%s"; it no longer represents a clearance to dispatch.', $ruleFinding['outcome']),
                ['target' => 'rule_evaluator', 'reason' => sprintf('Rule finding outcome "%s" requires re-evaluation.', $ruleFinding['outcome'])]
            );
        }

        if ($ruleFinding['action_signature'] != $action['action_signature']) {
            return $this->finish($gateEvaluationId, $now, $action['action_id'], $ruleFinding['evaluated_at'], null, null, 'Blocked - Action Mismatch', 'The action proposed for dispatch does not match the action the rule finding evaluated.');
        }

        if ($this->secondsSince($ruleFinding['evaluated_at'], $now) > $maxAge) {
            return $this->finish($gateEvaluationId, $now, $action['action_id'], $ruleFinding['evaluated_at'], null, null, 'Blocked - Stale Finding', 'The rule finding was produced too long ago to remain valid for dispatch.');
        }

        if ($this->riskAssessor !== null) {
            $currentRisk = $this->riskAssessor->get($riskFinding['risk_id']);

            if ($currentRisk === null) {
                return $this->finish($gateEvaluationId, $now, $action['action_id'], $ruleFinding['evaluated_at'], $riskFinding['risk_id'], null, 'Blocked - Stale Finding', sprintf('Risk assessment "%s" no longer exists.', $riskFinding['risk_id']));
            }

            if (isset($action['option_reference']) && $currentRisk['option_reference'] !== $action['option_reference']) {
                return $this->finish($gateEvaluationId, $now, $action['action_id'], $ruleFinding['evaluated_at'], $riskFinding['risk_id'], null, 'Blocked - Action Mismatch', 'The action proposed for dispatch does not match the option the risk finding evaluated.');
            }

            if ($this->secondsSince($riskFinding['evaluated_at'], $now) > $maxAge) {
                return $this->finish($gateEvaluationId, $now, $action['action_id'], $ruleFinding['evaluated_at'], $riskFinding['risk_id'], null, 'Blocked - Stale Finding', 'The risk finding was produced too long ago to remain valid for dispatch.');
            }

            if ($currentRisk['risk_level'] !== $riskFinding['risk_level'] || $currentRisk['status'] !== $riskFinding['status']) {
                return $this->finish($gateEvaluationId, $now, $action['action_id'], $ruleFinding['evaluated_at'], $riskFinding['risk_id'], null, 'Blocked - Stale Finding', 'The risk assessment has changed since it was evaluated for this action.');
            }

            if (isset($action['option_reference'])) {
                $canProceed = $this->riskAssessor->canProceed($action['option_reference']);

                if (!$canProceed['can_proceed']) {
                    return $this->finish(
                        $gateEvaluationId,
                        $now,
                        $action['action_id'],
                        $ruleFinding['evaluated_at'],
                        $riskFinding['risk_id'],
                        null,
                        'Blocked - Escalated to Risk Assessor',
                        'One or more critical risks for this option remain open and unmitigated.',
                        ['target' => 'risk_assessor', 'reason' => sprintf('Unmitigated critical risk(s): %s.', implode(', ', $canProceed['blocking_risks']))]
                    );
                }
            }
        }

        $authorizationRef = null;

        if ($this->authorization !== null) {
            $decision = $this->authorization->authorize(
                $authorizationContext['identity_ref'],
                $authorizationContext['permission_ref'],
                $authorizationContext['operation'],
                $authorizationContext['resource_ref'],
                $authorizationContext['correlation_id']
            );
            $authorizationRef = $decision['authorization_ref'];

            if (($decision['authorized'] ?? false) !== true) {
                return $this->finish($gateEvaluationId, $now, $action['action_id'], $ruleFinding['evaluated_at'], $riskFinding['risk_id'], $authorizationRef, 'Blocked - Authorization Revoked', (string) ($decision['rationale'] ?? 'Authorization is no longer granted.'));
            }
        }

        return $this->finish($gateEvaluationId, $now, $action['action_id'], $ruleFinding['evaluated_at'], $riskFinding['risk_id'], $authorizationRef, 'Pass', null);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $gateEvaluationId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM gate_evaluations WHERE gate_evaluation_id = :id');
        $statement->execute(['id' => $gateEvaluationId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @param array{action_id?: string, action_signature?: mixed} $action
     * @param array{outcome?: string, action_signature?: mixed, evaluated_at?: string} $ruleFinding
     * @param array{risk_id?: string, risk_level?: string, status?: string, evaluated_at?: string} $riskFinding
     * @param array{identity_ref?: string, permission_ref?: string, operation?: string, resource_ref?: string, correlation_id?: string} $authorizationContext
     */
    private function firstMissingField(array $action, array $ruleFinding, array $riskFinding, array $authorizationContext): ?string
    {
        foreach (['action_id', 'action_signature'] as $field) {
            if (!array_key_exists($field, $action)) {
                return 'action.' . $field;
            }
        }

        foreach (['outcome', 'action_signature', 'evaluated_at'] as $field) {
            if (!array_key_exists($field, $ruleFinding)) {
                return 'rule_finding.' . $field;
            }
        }

        foreach (['risk_id', 'risk_level', 'status', 'evaluated_at'] as $field) {
            if (!array_key_exists($field, $riskFinding)) {
                return 'risk_finding.' . $field;
            }
        }

        foreach (['identity_ref', 'permission_ref', 'operation', 'resource_ref', 'correlation_id'] as $field) {
            if (!array_key_exists($field, $authorizationContext)) {
                return 'authorization_context.' . $field;
            }
        }

        return null;
    }

    private function now(): DateTimeImmutable
    {
        return $this->clock !== null ? ($this->clock)() : new DateTimeImmutable();
    }

    private function secondsSince(string $evaluatedAt, DateTimeImmutable $now): int
    {
        return $now->getTimestamp() - (new DateTimeImmutable($evaluatedAt))->getTimestamp();
    }

    /**
     * @param array{target: string, reason: string}|null $reEvaluationRequest
     * @return array{gate_evaluation_id: string, decision: string, report: ?array<string, mixed>, re_evaluation_request: ?array{target: string, reason: string}, error: ?string}
     */
    private function finish(
        string $gateEvaluationId,
        DateTimeImmutable $now,
        string $actionId,
        ?string $ruleFindingReference,
        ?string $riskFindingReference,
        ?string $authorizationStatusReference,
        string $decision,
        ?string $reason,
        ?array $reEvaluationRequest = null
    ): array {
        $finalOutcome = $decision === 'Pass' ? 'dispatched' : 'blocked';

        $statement = $this->database->prepare(
            'INSERT INTO gate_evaluations (
                gate_evaluation_id, action_id, rule_finding_reference, risk_finding_reference,
                authorization_status_reference, decision, final_outcome, reason, created_at
            ) VALUES (
                :gate_evaluation_id, :action_id, :rule_finding_reference, :risk_finding_reference,
                :authorization_status_reference, :decision, :final_outcome, :reason, :created_at
            )'
        );
        $statement->execute([
            'gate_evaluation_id' => $gateEvaluationId,
            'action_id' => $actionId,
            'rule_finding_reference' => $ruleFindingReference,
            'risk_finding_reference' => $riskFindingReference,
            'authorization_status_reference' => $authorizationStatusReference,
            'decision' => $decision,
            'final_outcome' => $finalOutcome,
            'reason' => $reason,
            'created_at' => $now->format(DATE_RFC3339),
        ]);

        return [
            'gate_evaluation_id' => $gateEvaluationId,
            'decision' => $decision,
            'report' => $decision !== 'Pass' ? ['reason' => $reason] : null,
            're_evaluation_request' => $reEvaluationRequest,
            'error' => $decision === 'Blocked - Gate Evaluation Failed' ? $reason : null,
        ];
    }
}
