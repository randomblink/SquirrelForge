<?php

declare(strict_types=1);

namespace SquirrelForge\Learning;

use DateTimeImmutable;
use PDO;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;

/**
 * Assesses whether a collected experience contains meaningful,
 * sufficiently supported Learning-domain value, per
 * 30_LEARNING/EVALUATION-ENGINE.md.
 *
 * Every signal is real, computed from the injected SqliteExperienceStore
 * and this component's own evaluation history, not fabricated judgment.
 * "Assess evidence adequacy" is a real structural check: an experience
 * with no evidence_references and no feedback_reference is inadequate on
 * its face, short-circuiting further evaluation. "Compare expected and
 * observed outcomes" is real string equality against caller-supplied
 * values -- this class doesn't know what the right outcome should be,
 * per the spec's boundary against making that determination itself; it
 * only ever compares what the caller says was expected against what the
 * caller says was observed. "Repeatability" and "contradiction" are
 * real queries against this class's own evaluation_records for prior
 * evaluations sharing the same (source, event_category): repeatability
 * is a count, contradiction is a real check for a prior outcome_match
 * verdict that disagrees with this one. "Consistency" compares this
 * experience's confidence to the mean confidence of that same prior
 * group, within a caller-configurable tolerance.
 *
 * Confidence is a deliberately simple, explainable formula (a base
 * score, real bonuses for a matched outcome/prior repeats/consistency,
 * a real penalty for contradiction), not fabricated statistical rigor
 * this class doesn't actually implement -- the same honesty PatternDetector
 * commits to for its own confidence heuristics.
 *
 * "Forward qualified evaluation references for pattern analysis" is a
 * real completion of the hand-off SqliteExperienceStore::attachReference()
 * was built to receive: a `qualified` result calls it with type
 * `evaluation`, which also transitions the experience's own status to
 * `evaluated`. No general audit/historical-storage infrastructure is
 * owned beyond this component's own evaluation_records table, per the
 * spec's boundary.
 */
final class SqliteEvaluationEngine
{
    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly ?SqliteExperienceStore $experiences = null,
        private readonly ?EventBusInterface $events = null
    ) {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS evaluation_records (
                evaluation_id TEXT PRIMARY KEY,
                experience_id TEXT NOT NULL,
                source TEXT NOT NULL,
                event_category TEXT NOT NULL,
                confidence_input REAL,
                expected_outcome TEXT,
                observed_outcome TEXT,
                outcome_match TEXT NOT NULL,
                evidence_adequate INTEGER NOT NULL,
                relevant INTEGER NOT NULL,
                repeatability_count INTEGER NOT NULL,
                consistent INTEGER,
                contradicted INTEGER NOT NULL,
                qualification TEXT NOT NULL,
                confidence REAL NOT NULL,
                rationale TEXT NOT NULL,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array{expected_outcome?: ?string, observed_outcome?: ?string, relevant_categories?: array<int, string>, consistency_tolerance?: float} $options
     * @return array{found: bool, evaluation_id: ?string, qualification: ?string, confidence: ?float, rationale: ?string, error: ?string}
     */
    public function evaluate(string $experienceId, array $options = []): array
    {
        if ($this->experiences === null) {
            return ['found' => false, 'evaluation_id' => null, 'qualification' => null, 'confidence' => null, 'rationale' => null, 'error' => 'Experience Store is not configured.'];
        }

        $experience = $this->experiences->get($experienceId);

        if ($experience === null) {
            return ['found' => false, 'evaluation_id' => null, 'qualification' => null, 'confidence' => null, 'rationale' => null, 'error' => sprintf('Unknown experience record "%s".', $experienceId)];
        }

        $evidenceAdequate = $experience['evidence_references'] !== [] || $experience['feedback_reference'] !== null;

        if (!$evidenceAdequate) {
            return $this->persist($experience, null, null, 'not_applicable', false, true, 0, null, false, 'insufficient_evidence', 0.0, 'No evidence references or feedback reference are attached to this experience.');
        }

        $relevantCategories = $options['relevant_categories'] ?? null;
        $relevant = $relevantCategories === null || in_array($experience['event_category'], $relevantCategories, true);

        if (!$relevant) {
            return $this->persist($experience, null, null, 'not_applicable', true, false, 0, null, false, 'not_qualified', 0.0, 'This experience\'s category is not relevant to the requested evaluation context.');
        }

        $expectedOutcome = $options['expected_outcome'] ?? null;
        $observedOutcome = $options['observed_outcome'] ?? null;
        $outcomeMatch = ($expectedOutcome === null || $observedOutcome === null)
            ? 'not_applicable'
            : ($expectedOutcome === $observedOutcome ? 'match' : 'mismatch');

        $priorEvaluations = $this->priorEvaluations($experience['source'], $experience['event_category'], $experienceId);
        $repeatabilityCount = count($priorEvaluations);
        $contradicted = $this->isContradicted($priorEvaluations, $outcomeMatch);
        $consistent = $this->isConsistent($priorEvaluations, $experience['confidence'], $options['consistency_tolerance'] ?? 0.2);

        $qualification = match (true) {
            $contradicted => 'contradicted',
            $outcomeMatch === 'mismatch' => 'not_qualified',
            default => 'qualified',
        };

        $confidence = $this->computeConfidence($outcomeMatch, $repeatabilityCount, $consistent, $contradicted);
        $rationale = $this->buildRationale($qualification, $outcomeMatch, $repeatabilityCount, $consistent, $contradicted);

        $result = $this->persist($experience, $expectedOutcome, $observedOutcome, $outcomeMatch, true, $relevant, $repeatabilityCount, $consistent, $contradicted, $qualification, $confidence, $rationale);

        if ($qualification === 'qualified') {
            $this->experiences->attachReference($experienceId, 'evaluation', $result['evaluation_id']);
        }

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function priorEvaluations(string $source, string $eventCategory, string $excludeExperienceId): array
    {
        $statement = $this->database->prepare(
            'SELECT outcome_match, confidence_input FROM evaluation_records
             WHERE source = :source AND event_category = :event_category AND experience_id != :experience_id'
        );
        $statement->execute(['source' => $source, 'event_category' => $eventCategory, 'experience_id' => $excludeExperienceId]);

        return $statement->fetchAll();
    }

    /**
     * @param array<int, array<string, mixed>> $priorEvaluations
     */
    private function isContradicted(array $priorEvaluations, string $outcomeMatch): bool
    {
        if ($outcomeMatch === 'not_applicable') {
            return false;
        }

        foreach ($priorEvaluations as $prior) {
            if (in_array($prior['outcome_match'], ['match', 'mismatch'], true) && $prior['outcome_match'] !== $outcomeMatch) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, array<string, mixed>> $priorEvaluations
     */
    private function isConsistent(array $priorEvaluations, ?float $confidence, float $tolerance): ?bool
    {
        $priorConfidences = array_values(array_filter(
            array_map(static fn(array $p): ?float => $p['confidence_input'] !== null ? (float) $p['confidence_input'] : null, $priorEvaluations),
            static fn(?float $c): bool => $c !== null
        ));

        if ($confidence === null || $priorConfidences === []) {
            return null;
        }

        $mean = array_sum($priorConfidences) / count($priorConfidences);

        return abs($confidence - $mean) <= $tolerance;
    }

    /**
     * A deliberately simple, explainable heuristic: a base score, real
     * bonuses for a matched outcome, prior repeats (capped), and
     * consistency, and a real penalty for contradiction.
     */
    private function computeConfidence(string $outcomeMatch, int $repeatabilityCount, ?bool $consistent, bool $contradicted): float
    {
        $confidence = 0.5;
        $confidence += $outcomeMatch === 'match' ? 0.2 : 0.0;
        $confidence += min($repeatabilityCount, 2) * 0.1;
        $confidence += $consistent === true ? 0.1 : 0.0;
        $confidence -= $contradicted ? 0.3 : 0.0;

        return max(0.0, min(1.0, $confidence));
    }

    private function buildRationale(string $qualification, string $outcomeMatch, int $repeatabilityCount, ?bool $consistent, bool $contradicted): string
    {
        return sprintf(
            'qualification=%s, outcome_match=%s, repeatability_count=%d, consistent=%s, contradicted=%s',
            $qualification,
            $outcomeMatch,
            $repeatabilityCount,
            $consistent === null ? 'unknown' : ($consistent ? 'true' : 'false'),
            $contradicted ? 'true' : 'false'
        );
    }

    /**
     * @param array<string, mixed> $experience
     * @return array{found: bool, evaluation_id: string, qualification: string, confidence: float, rationale: string, error: null}
     */
    private function persist(
        array $experience,
        ?string $expectedOutcome,
        ?string $observedOutcome,
        string $outcomeMatch,
        bool $evidenceAdequate,
        bool $relevant,
        int $repeatabilityCount,
        ?bool $consistent,
        bool $contradicted,
        string $qualification,
        float $confidence,
        string $rationale
    ): array {
        $evaluationId = 'evaluation_' . bin2hex(random_bytes(12));

        $statement = $this->database->prepare(
            'INSERT INTO evaluation_records (
                evaluation_id, experience_id, source, event_category, confidence_input,
                expected_outcome, observed_outcome, outcome_match, evidence_adequate, relevant,
                repeatability_count, consistent, contradicted, qualification, confidence, rationale, created_at
            ) VALUES (
                :evaluation_id, :experience_id, :source, :event_category, :confidence_input,
                :expected_outcome, :observed_outcome, :outcome_match, :evidence_adequate, :relevant,
                :repeatability_count, :consistent, :contradicted, :qualification, :confidence, :rationale, :created_at
            )'
        );
        $statement->execute([
            'evaluation_id' => $evaluationId,
            'experience_id' => $experience['experience_id'],
            'source' => $experience['source'],
            'event_category' => $experience['event_category'],
            'confidence_input' => $experience['confidence'],
            'expected_outcome' => $expectedOutcome,
            'observed_outcome' => $observedOutcome,
            'outcome_match' => $outcomeMatch,
            'evidence_adequate' => $evidenceAdequate ? 1 : 0,
            'relevant' => $relevant ? 1 : 0,
            'repeatability_count' => $repeatabilityCount,
            'consistent' => $consistent === null ? null : ($consistent ? 1 : 0),
            'contradicted' => $contradicted ? 1 : 0,
            'qualification' => $qualification,
            'confidence' => $confidence,
            'rationale' => $rationale,
            'created_at' => gmdate(DATE_RFC3339),
        ]);

        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'evaluation.' . $qualification,
            new DateTimeImmutable(),
            self::class,
            ['evaluation_id' => $evaluationId, 'experience_id' => $experience['experience_id']]
        ));

        return ['found' => true, 'evaluation_id' => $evaluationId, 'qualification' => $qualification, 'confidence' => $confidence, 'rationale' => $rationale, 'error' => null];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $evaluationId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM evaluation_records WHERE evaluation_id = :evaluation_id');
        $statement->execute(['evaluation_id' => $evaluationId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @param array{experience_id?: string, source?: string, event_category?: string, qualification?: string} $filters
     * @return array<int, array<string, mixed>>
     */
    public function list(array $filters = []): array
    {
        $conditions = ['1 = 1'];
        $parameters = [];

        foreach (['experience_id', 'source', 'event_category', 'qualification'] as $field) {
            if (isset($filters[$field])) {
                $conditions[] = "{$field} = :{$field}";
                $parameters[$field] = $filters[$field];
            }
        }

        $statement = $this->database->prepare(
            'SELECT * FROM evaluation_records WHERE ' . implode(' AND ', $conditions) . ' ORDER BY rowid DESC'
        );
        $statement->execute($parameters);

        return $statement->fetchAll();
    }
}
