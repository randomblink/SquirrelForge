<?php

declare(strict_types=1);

namespace SquirrelForge\AiDriver;

use DateTimeImmutable;
use PDO;
use SquirrelForge\Engine\EngineValidation;
use SquirrelForge\Execution\SqliteResultCollector;

/**
 * Closes the AI reasoning loop after execution: reads
 * `20_EXECUTION/RESULT-COLLECTOR.md`'s assembled Execution Result Set
 * together with `14_ENGINE/VALIDATION.md`'s findings, compares them
 * against the original structured goal, and recommends the AI
 * Driver's next step, per 34_AIDRIVER/RESULT-REVIEWER.md.
 *
 * Genuine composition of two already-real components, not a
 * re-implementation of either: the Execution Result Set is the real
 * `SqliteResultCollector::assemble()` output (never a fabricated
 * summary), and validation findings come from calling the real
 * `EngineValidation::evaluate()` directly on caller-supplied items --
 * the same "compose the real aggregator with raw evidence" shape
 * `ExecutionEngine` already established for the same class, rather
 * than accepting a pre-decided decision string this class would have
 * no way to verify.
 *
 * "Must never fabricate evaluation results" (Safety Rules) governs the
 * one comparison this class genuinely cannot perform mechanically:
 * whether the actual result matches the goal's expected outcome is a
 * semantic judgment, so `matches_expected_outcome` is a required,
 * explicitly-typed caller-supplied boolean -- its absence is a shape
 * error (`invalid`), never assumed true, the same fail-closed stance
 * `SqliteAgentSpecialization` already takes toward `boundary_verified`.
 *
 * Goal Status is a real, deterministic function of three genuine
 * signals, never invented: `EngineValidation`'s own real decision
 * (REJECTED/BLOCKED/REPAIR_REQUIRED/RECOVERY_REQUIRED/
 * CLARIFICATION_REQUIRED/ACCEPTED_WITH_LIMITATIONS/ACCEPTED),
 * `SqliteResultCollector::assemble()`'s own real `missing_references`,
 * and the caller-confirmed `matches_expected_outcome`. Recovery
 * Recommendation is, in turn, a real deterministic function of the
 * resulting Goal Status plus two caller-declared override signals this
 * class cannot determine on its own (`alternative_tool_available`,
 * `clarification_needed`) -- never a free-form judgment call.
 *
 * "If result review fails: preserve execution evidence... record the
 * review failure... escalate persistent issues" (Failure Handling) is
 * upheld structurally: an unconfigured `SqliteResultCollector` or
 * `EngineValidation` produces a real, recorded `Blocked` Goal Status
 * with an `Escalate the issue` recommendation -- one of this spec's
 * own six real Goal Status values, not a separate ad hoc "not
 * configured" outcome, since "Blocked" already means exactly this.
 *
 * SQLite-backed for the explicit Audit Requirements section and
 * "record review activity" (Responsibilities); every review attempt is
 * recorded, including ones a missing composed dependency blocked.
 */
final class SqliteResultReviewer
{
    /** EngineValidation's own real decision, mapped onto this spec's own Goal Status. */
    private const DECISION_TO_GOAL_STATUS = [
        'REJECTED' => 'Failed',
        'BLOCKED' => 'Blocked',
        'REPAIR_REQUIRED' => 'Requires retry',
        'RECOVERY_REQUIRED' => 'Blocked',
        'CLARIFICATION_REQUIRED' => 'Requires replanning',
    ];

    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly ?SqliteResultCollector $resultCollector = null,
        private readonly ?EngineValidation $validation = null
    ) {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS result_reviews (
                result_review_id TEXT PRIMARY KEY,
                goal_id TEXT,
                action_id TEXT,
                expected_outcome TEXT,
                actual_outcome_json TEXT,
                goal_status TEXT NOT NULL,
                recommended_next_step TEXT NOT NULL,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * Result Review Workflow steps 1-7.
     *
     * @param array{
     *     goal_id?: ?string,
     *     action_id?: ?string,
     *     workflow_step_ref?: ?string,
     *     expected_outcome?: ?string,
     *     matches_expected_outcome?: bool,
     *     validation_items?: array<int, array{item_id: string, stage: string, required: bool, blocking?: bool, status: string, waivable?: bool, repairable?: bool}>,
     *     validation_options?: array{remaining_attempts?: int, recovery_required?: bool, clarification_needed?: bool},
     *     alternative_tool_available?: bool,
     *     clarification_needed?: bool
     * } $request
     * @return array{
     *     outcome: string,
     *     result_review_id: ?string,
     *     goal_status: ?string,
     *     recommended_next_step: ?string,
     *     error: ?string
     * }
     */
    public function review(array $request): array
    {
        $goalId = $request['goal_id'] ?? null;
        $actionId = $request['action_id'] ?? null;
        $workflowStepRef = $request['workflow_step_ref'] ?? null;
        $expectedOutcome = $request['expected_outcome'] ?? null;

        if (!$this->present($goalId) || !$this->present($actionId) || !$this->present($workflowStepRef) || !$this->present($expectedOutcome)) {
            return $this->envelope('invalid', null, null, null, 'A result review requires a non-empty goal_id, action_id, workflow_step_ref, and expected_outcome.');
        }

        if (!array_key_exists('matches_expected_outcome', $request) || !is_bool($request['matches_expected_outcome'])) {
            return $this->envelope('invalid', null, null, null, 'A result review must never fabricate a comparison; matches_expected_outcome must be an explicit true/false.');
        }

        if ($this->resultCollector === null || $this->validation === null) {
            $missing = $this->resultCollector === null ? 'SqliteResultCollector' : 'EngineValidation';

            return $this->recordAndEnvelope($goalId, $actionId, $expectedOutcome, null, 'Blocked', 'Escalate the issue', sprintf('%s is not configured; the review cannot proceed.', $missing));
        }

        $resultSet = $this->resultCollector->assemble($workflowStepRef);
        $validationResult = $this->validation->evaluate($request['validation_items'] ?? [], $request['validation_options'] ?? []);

        $goalStatus = $this->determineGoalStatus($validationResult['decision'], $resultSet['missing_references'], $request['matches_expected_outcome']);
        $recommendation = $this->determineRecommendation($goalStatus, $validationResult['decision'], $request);

        $actualOutcome = ['result_set' => $resultSet, 'validation_decision' => $validationResult['decision']];

        return $this->recordAndEnvelope($goalId, $actionId, $expectedOutcome, $actualOutcome, $goalStatus, $recommendation, null);
    }

    /**
     * @param array<int, string> $missingReferences
     */
    private function determineGoalStatus(string $validationDecision, array $missingReferences, bool $matchesExpectedOutcome): string
    {
        if (isset(self::DECISION_TO_GOAL_STATUS[$validationDecision])) {
            return self::DECISION_TO_GOAL_STATUS[$validationDecision];
        }

        // Only ACCEPTED / ACCEPTED_WITH_LIMITATIONS reach here.
        if ($missingReferences !== []) {
            return 'Partially completed';
        }

        if (!$matchesExpectedOutcome) {
            return 'Requires replanning';
        }

        return 'Completed';
    }

    /**
     * @param array<string, mixed> $request
     */
    private function determineRecommendation(string $goalStatus, string $validationDecision, array $request): string
    {
        return match ($goalStatus) {
            'Completed' => 'Mark the goal complete',
            'Partially completed', 'Requires retry' => ($request['alternative_tool_available'] ?? false) === true
                ? 'Retry with a different tool'
                : 'Retry the current action',
            'Requires replanning' => $validationDecision === 'CLARIFICATION_REQUIRED' || ($request['clarification_needed'] ?? false) === true
                ? 'Request clarification'
                : 'Revise the plan',
            default => 'Escalate the issue', // Blocked, Failed
        };
    }

    /**
     * @return ?array<string, mixed>
     */
    public function get(string $resultReviewId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM result_reviews WHERE result_review_id = :result_review_id');
        $statement->execute(['result_review_id' => $resultReviewId]);
        $row = $statement->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    /**
     * "Record review activity" -- every review recorded for a goal, in
     * the order it was reviewed.
     *
     * @return array<int, array<string, mixed>>
     */
    public function history(string $goalId): array
    {
        $statement = $this->database->prepare('SELECT * FROM result_reviews WHERE goal_id = :goal_id ORDER BY rowid ASC');
        $statement->execute(['goal_id' => $goalId]);

        return array_map(fn(array $row): array => $this->hydrate($row), $statement->fetchAll());
    }

    /**
     * @param ?array<string, mixed> $actualOutcome
     * @return array{outcome: string, result_review_id: ?string, goal_status: ?string, recommended_next_step: ?string, error: ?string}
     */
    private function recordAndEnvelope(string $goalId, string $actionId, string $expectedOutcome, ?array $actualOutcome, string $goalStatus, string $recommendation, ?string $error): array
    {
        $resultReviewId = 'result_review_' . bin2hex(random_bytes(12));

        $statement = $this->database->prepare(
            'INSERT INTO result_reviews (
                result_review_id, goal_id, action_id, expected_outcome, actual_outcome_json,
                goal_status, recommended_next_step, created_at
            ) VALUES (
                :result_review_id, :goal_id, :action_id, :expected_outcome, :actual_outcome_json,
                :goal_status, :recommended_next_step, :created_at
            )'
        );
        $statement->execute([
            'result_review_id' => $resultReviewId,
            'goal_id' => $goalId,
            'action_id' => $actionId,
            'expected_outcome' => $expectedOutcome,
            'actual_outcome_json' => $actualOutcome !== null ? json_encode($actualOutcome, JSON_THROW_ON_ERROR) : null,
            'goal_status' => $goalStatus,
            'recommended_next_step' => $recommendation,
            'created_at' => (new DateTimeImmutable())->format(DATE_ATOM),
        ]);

        return $this->envelope('reviewed', $resultReviewId, $goalStatus, $recommendation, $error);
    }

    /**
     * @return array{outcome: string, result_review_id: ?string, goal_status: ?string, recommended_next_step: ?string, error: ?string}
     */
    private function envelope(string $outcome, ?string $resultReviewId, ?string $goalStatus, ?string $recommendation, ?string $error): array
    {
        return [
            'outcome' => $outcome,
            'result_review_id' => $resultReviewId,
            'goal_status' => $goalStatus,
            'recommended_next_step' => $recommendation,
            'error' => $error,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        $row['actual_outcome'] = $row['actual_outcome_json'] !== null ? json_decode((string) $row['actual_outcome_json'], true, flags: JSON_THROW_ON_ERROR) : null;
        unset($row['actual_outcome_json']);

        return $row;
    }

    private function present(mixed $value): bool
    {
        return is_string($value) && $value !== '';
    }
}
