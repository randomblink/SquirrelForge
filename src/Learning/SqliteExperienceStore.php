<?php

declare(strict_types=1);

namespace SquirrelForge\Learning;

use Closure;
use DateTimeImmutable;
use PDO;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;
use SquirrelForge\Storage\SqliteDocumentStorage;

/**
 * Owns authoritative Learning-domain experience records, identifiers,
 * and reference metadata, per 30_LEARNING/EXPERIENCE-STORE.md.
 *
 * Architecturally this mirrors SqliteDocumentRepository: the spec draws
 * the same line between "own reference/metadata" (real, this
 * component's own experience_records table) and "own raw... storage
 * infrastructure" (forbidden -- raw persistence stays with 37_STORAGE).
 * A `storage_reference` is optionally verified against the injected
 * SqliteDocumentStorage, the same real cross-check pattern Document
 * Repository uses, genuinely skipped when not configured.
 *
 * A `feedback_reference` is verified for real existence against the
 * injected SqliteFeedbackCollector, and recording an experience makes a
 * real, best-effort call to markForwarded() on that feedback record --
 * completing the hand-off SqliteFeedbackCollector's own docblock
 * anticipated ("lets a caller mark a feedback record as picked up...
 * preventing silent double-processing"). This is deliberately
 * non-blocking: if the feedback was already forwarded elsewhere, that's
 * recorded as an informational note, not a reason to refuse an
 * otherwise-valid experience record.
 *
 * governance_reference and adaptation_reference are verified against
 * SqliteLearningGovernance::currentStatus() and
 * SqliteAdaptationManager::get() respectively when each is injected --
 * both had no code yet to check them against when this class was first
 * written, the same "coordinator predates its own specialist"
 * resolution this codebase's other coordinators have already gone
 * through; genuinely skipped, not silently treated as passing, when the
 * corresponding component isn't configured.
 *
 * evaluation_reference and pattern_reference stay opaque and
 * unverified, for two different real reasons rather than one shared
 * excuse: SqliteEvaluationEngine already takes this class as its own
 * constructor dependency (to read the experience being evaluated), so
 * this class taking SqliteEvaluationEngine back would be a genuine
 * circular composition, the same shape already documented for Capacity
 * Planner/Cost Optimizer and Decision Engine/Confidence Scorer
 * elsewhere in this codebase -- and in practice no verification gap
 * exists there anyway, since EvaluationEngine::evaluate() already calls
 * this class's own attachReference() with a freshly-created, trustworthy
 * ID rather than an external caller supplying an unverified one.
 * PatternDetector owns no database of its own (per its own docblock,
 * "findings are pure output, never persisted here"), so there is no
 * real record for a pattern_reference to ever resolve against, not
 * merely an unbuilt one. No general audit-trail infrastructure is
 * owned, per the spec -- only the domain table itself and an optional
 * EventBusInterface announcement per operation.
 */
final class SqliteExperienceStore
{
    private const REFERENCE_TYPES = ['evaluation', 'pattern', 'governance', 'adaptation'];

    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly ?SqliteDocumentStorage $documentStorage = null,
        private readonly ?SqliteFeedbackCollector $feedbackCollector = null,
        private readonly ?SqliteLearningGovernance $governance = null,
        private readonly ?SqliteAdaptationManager $adaptationManager = null,
        private readonly ?EventBusInterface $events = null,
        private readonly ?Closure $clock = null
    ) {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS experience_records (
                experience_id TEXT PRIMARY KEY,
                source TEXT NOT NULL,
                event_category TEXT NOT NULL,
                workflow_reference TEXT,
                confidence REAL,
                feedback_reference TEXT,
                evidence_references_json TEXT NOT NULL,
                evaluation_reference TEXT,
                pattern_reference TEXT,
                governance_reference TEXT,
                adaptation_reference TEXT,
                storage_reference TEXT,
                status TEXT NOT NULL,
                metadata_json TEXT NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array{workflow_reference?: ?string, confidence?: ?float, feedback_reference?: ?string, evidence_references?: array<int, string>, storage_reference?: ?string, metadata?: array<string, mixed>} $options
     * @return array{found: bool, experience_id: ?string, error: ?string, notes: array<int, string>}
     */
    public function record(string $source, string $eventCategory, array $options = []): array
    {
        if ($source === '' || $eventCategory === '') {
            return ['found' => false, 'experience_id' => null, 'error' => 'Both "source" and "event_category" are required.', 'notes' => []];
        }

        $confidence = $options['confidence'] ?? null;

        if ($confidence !== null && (!is_numeric($confidence) || $confidence < 0.0 || $confidence > 1.0)) {
            return ['found' => false, 'experience_id' => null, 'error' => 'Confidence must be a number between 0.0 and 1.0.', 'notes' => []];
        }

        $notes = [];
        $feedbackReference = $options['feedback_reference'] ?? null;

        if ($feedbackReference !== null && $this->feedbackCollector !== null) {
            if ($this->feedbackCollector->get($feedbackReference) === null) {
                return ['found' => false, 'experience_id' => null, 'error' => sprintf('Feedback reference "%s" does not resolve to a known feedback record.', $feedbackReference), 'notes' => []];
            }

            $forwarded = $this->feedbackCollector->markForwarded($feedbackReference, 'experience_store');
            $notes[] = $forwarded['found']
                ? sprintf('Feedback "%s" marked forwarded to experience_store.', $feedbackReference)
                : sprintf('Feedback "%s" was not marked forwarded: %s', $feedbackReference, $forwarded['error']);
        }

        $storageReference = $options['storage_reference'] ?? null;

        if ($storageReference !== null && $this->documentStorage !== null && !$this->documentStorage->retrieve($storageReference)['found']) {
            return ['found' => false, 'experience_id' => null, 'error' => sprintf('Storage reference "%s" does not resolve to a stored document.', $storageReference), 'notes' => []];
        }

        $experienceId = 'experience_' . bin2hex(random_bytes(12));
        $now = $this->now()->format(DATE_RFC3339);

        $statement = $this->database->prepare(
            'INSERT INTO experience_records (
                experience_id, source, event_category, workflow_reference, confidence,
                feedback_reference, evidence_references_json, evaluation_reference, pattern_reference,
                governance_reference, adaptation_reference, storage_reference, status, metadata_json,
                created_at, updated_at
            ) VALUES (
                :experience_id, :source, :event_category, :workflow_reference, :confidence,
                :feedback_reference, :evidence_references_json, NULL, NULL,
                NULL, NULL, :storage_reference, :status, :metadata_json,
                :created_at, :updated_at
            )'
        );
        $statement->execute([
            'experience_id' => $experienceId,
            'source' => $source,
            'event_category' => $eventCategory,
            'workflow_reference' => $options['workflow_reference'] ?? null,
            'confidence' => $confidence,
            'feedback_reference' => $feedbackReference,
            'evidence_references_json' => json_encode($options['evidence_references'] ?? [], JSON_THROW_ON_ERROR),
            'storage_reference' => $storageReference,
            'status' => 'recorded',
            'metadata_json' => json_encode($options['metadata'] ?? [], JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->publish('recorded', $experienceId);

        return ['found' => true, 'experience_id' => $experienceId, 'error' => null, 'notes' => $notes];
    }

    /**
     * Records a reference from Evaluation Engine, Pattern Detector,
     * Learning Governance, or Adaptation Manager -- an opaque,
     * unverified string, since none of those components exist yet to
     * check it against. Only an "evaluation" reference transitions
     * status to `evaluated`; the others are supplementary links.
     *
     * @return array{found: bool, error: ?string}
     */
    public function attachReference(string $experienceId, string $type, string $reference): array
    {
        if (!in_array($type, self::REFERENCE_TYPES, true)) {
            return ['found' => false, 'error' => sprintf('Unknown reference type "%s".', $type)];
        }

        $record = $this->fetch($experienceId);

        if ($record === null) {
            return ['found' => false, 'error' => sprintf('Unknown experience record "%s".', $experienceId)];
        }

        if ($type === 'governance' && $this->governance !== null && $this->governance->currentStatus($reference) === null) {
            return ['found' => false, 'error' => sprintf('Governance reference "%s" does not resolve to a known Learning Governance proposal.', $reference)];
        }

        if ($type === 'adaptation' && $this->adaptationManager !== null && $this->adaptationManager->get($reference) === null) {
            return ['found' => false, 'error' => sprintf('Adaptation reference "%s" does not resolve to a known Adaptation Manager plan.', $reference)];
        }

        $column = $type . '_reference';
        $status = $type === 'evaluation' ? 'evaluated' : $record['status'];

        $statement = $this->database->prepare(
            "UPDATE experience_records SET {$column} = :reference, status = :status, updated_at = :updated_at WHERE experience_id = :experience_id"
        );
        $statement->execute(['reference' => $reference, 'status' => $status, 'updated_at' => $this->now()->format(DATE_RFC3339), 'experience_id' => $experienceId]);

        $this->publish($type . '_attached', $experienceId);

        return ['found' => true, 'error' => null];
    }

    /**
     * @return array{found: bool, error: ?string}
     */
    public function archive(string $experienceId): array
    {
        $record = $this->fetch($experienceId);

        if ($record === null) {
            return ['found' => false, 'error' => sprintf('Unknown experience record "%s".', $experienceId)];
        }

        if ($record['status'] === 'archived') {
            return ['found' => false, 'error' => sprintf('Experience "%s" is already archived.', $experienceId)];
        }

        $statement = $this->database->prepare('UPDATE experience_records SET status = :status, updated_at = :updated_at WHERE experience_id = :experience_id');
        $statement->execute(['status' => 'archived', 'updated_at' => $this->now()->format(DATE_RFC3339), 'experience_id' => $experienceId]);

        $this->publish('archived', $experienceId);

        return ['found' => true, 'error' => null];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $experienceId): ?array
    {
        $record = $this->fetch($experienceId);

        return $record === null ? null : $this->hydrate($record);
    }

    /**
     * @param array{source?: string, event_category?: string, status?: string} $filters
     * @return array<int, array<string, mixed>>
     */
    public function list(array $filters = []): array
    {
        $conditions = ['1 = 1'];
        $parameters = [];

        foreach (['source', 'event_category', 'status'] as $field) {
            if (isset($filters[$field])) {
                $conditions[] = "{$field} = :{$field}";
                $parameters[$field] = $filters[$field];
            }
        }

        $statement = $this->database->prepare(
            'SELECT experience_id, source, event_category, status, confidence, created_at, updated_at
             FROM experience_records WHERE ' . implode(' AND ', $conditions) . ' ORDER BY rowid DESC'
        );
        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetch(string $experienceId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM experience_records WHERE experience_id = :experience_id');
        $statement->execute(['experience_id' => $experienceId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    private function hydrate(array $record): array
    {
        return [
            'experience_id' => $record['experience_id'],
            'source' => $record['source'],
            'event_category' => $record['event_category'],
            'workflow_reference' => $record['workflow_reference'],
            'confidence' => $record['confidence'] !== null ? (float) $record['confidence'] : null,
            'feedback_reference' => $record['feedback_reference'],
            'evidence_references' => json_decode((string) $record['evidence_references_json'], true, flags: JSON_THROW_ON_ERROR),
            'evaluation_reference' => $record['evaluation_reference'],
            'pattern_reference' => $record['pattern_reference'],
            'governance_reference' => $record['governance_reference'],
            'adaptation_reference' => $record['adaptation_reference'],
            'storage_reference' => $record['storage_reference'],
            'status' => $record['status'],
            'metadata' => json_decode((string) $record['metadata_json'], true, flags: JSON_THROW_ON_ERROR),
            'created_at' => $record['created_at'],
            'updated_at' => $record['updated_at'],
        ];
    }

    private function now(): DateTimeImmutable
    {
        return $this->clock !== null ? ($this->clock)() : new DateTimeImmutable();
    }

    private function publish(string $eventSuffix, string $experienceId): void
    {
        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'experience.' . $eventSuffix,
            new DateTimeImmutable(),
            self::class,
            ['experience_id' => $experienceId]
        ));
    }
}
