<?php

declare(strict_types=1);

namespace SquirrelForge\Learning;

use DateTimeImmutable;
use PDO;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;

/**
 * Converts submitted observations into normalized Learning-domain
 * feedback records, per 30_LEARNING/FEEDBACK-COLLECTOR.md.
 *
 * Follows the same "consume, don't compute" boundary as
 * SqliteDocumentRepository: the spec says this component must not
 * "authenticate platform identities or make authorization decisions",
 * so submit() never calls AuthenticationManagerInterface itself. It
 * accepts an already-computed authentication result as a parameter --
 * the caller decides via `require_identity` whether this submission
 * needs one, and supplies the real decision from 24_SECURITY.
 *
 * "Preserve submitted content and source references" / "must not...
 * infer missing evidence or alter submitted meaning" is honored
 * literally: content and evidence_references are stored verbatim, never
 * transformed, only wrapped with real metadata (a stable ID, a content
 * hash, a status). Duplicate-submission detection is a real check
 * against that hash, queried from this component's own feedback_records
 * table -- the same table serves as both the real domain data
 * ("Create stable feedback identifiers and collection-status records")
 * and the mechanism for the duplicate check, without a separate audit
 * table, which the spec forbids owning.
 *
 * "Forward feedback-record references for evaluation and experience
 * recording" is real but modest: Evaluation Engine and Experience Store
 * have no code yet to forward to, so markForwarded() is a real status
 * transition a caller can invoke once it has picked up a feedback
 * record through whatever means exists today, preventing silent
 * double-processing later -- it doesn't fabricate calling components
 * that don't exist.
 */
final class SqliteFeedbackCollector
{
    private const SOURCE_TYPES = ['user', 'agent', 'workflow', 'validation', 'security', 'performance', 'operational'];

    private PDO $database;

    public function __construct(string $databasePath, private readonly ?EventBusInterface $events = null)
    {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS feedback_records (
                feedback_id TEXT PRIMARY KEY,
                source_type TEXT NOT NULL,
                content_json TEXT NOT NULL,
                evidence_references_json TEXT NOT NULL,
                identity_ref TEXT,
                content_hash TEXT NOT NULL,
                status TEXT NOT NULL,
                forwarded_to TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array<string, mixed> $content
     * @param array{evidence_references?: array<int, string>, identity_ref?: ?string, authentication_result?: array{authenticated?: bool, identity_ref?: ?string}|null, require_identity?: bool} $options
     * @return array{found: bool, feedback_id: ?string, outcome: string, error: ?string}
     */
    public function submit(string $sourceType, array $content, array $options = []): array
    {
        if (!in_array($sourceType, self::SOURCE_TYPES, true)) {
            return $this->rejected(sprintf('Unknown feedback source type "%s".', $sourceType));
        }

        if ($content === []) {
            return $this->rejected('Feedback content must not be empty.');
        }

        if (($options['require_identity'] ?? false) && ($options['authentication_result']['authenticated'] ?? false) !== true) {
            return $this->rejected('This feedback source requires an authenticated identity, and none was supplied.');
        }

        $evidenceReferences = $options['evidence_references'] ?? [];
        $contentHash = hash('sha256', json_encode(['source_type' => $sourceType, 'content' => $content, 'evidence_references' => $evidenceReferences], JSON_THROW_ON_ERROR));

        $duplicate = $this->findByContentHash($contentHash);

        if ($duplicate !== null) {
            return ['found' => true, 'feedback_id' => $duplicate['feedback_id'], 'outcome' => 'duplicate', 'error' => null];
        }

        $identityRef = $options['authentication_result']['identity_ref'] ?? $options['identity_ref'] ?? null;
        $feedbackId = 'feedback_' . bin2hex(random_bytes(12));
        $now = gmdate(DATE_RFC3339);

        $statement = $this->database->prepare(
            'INSERT INTO feedback_records (
                feedback_id, source_type, content_json, evidence_references_json,
                identity_ref, content_hash, status, forwarded_to, created_at, updated_at
            ) VALUES (
                :feedback_id, :source_type, :content_json, :evidence_references_json,
                :identity_ref, :content_hash, :status, NULL, :created_at, :updated_at
            )'
        );
        $statement->execute([
            'feedback_id' => $feedbackId,
            'source_type' => $sourceType,
            'content_json' => json_encode($content, JSON_THROW_ON_ERROR),
            'evidence_references_json' => json_encode($evidenceReferences, JSON_THROW_ON_ERROR),
            'identity_ref' => $identityRef,
            'content_hash' => $contentHash,
            'status' => 'collected',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->publish('collected', $feedbackId);

        return ['found' => true, 'feedback_id' => $feedbackId, 'outcome' => 'collected', 'error' => null];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $feedbackId): ?array
    {
        $record = $this->fetch($feedbackId);

        return $record === null ? null : $this->hydrate($record);
    }

    /**
     * @return array{found: bool, error: ?string}
     */
    public function markForwarded(string $feedbackId, string $forwardedTo): array
    {
        $record = $this->fetch($feedbackId);

        if ($record === null) {
            return ['found' => false, 'error' => sprintf('Unknown feedback record "%s".', $feedbackId)];
        }

        if ($record['status'] === 'forwarded') {
            return ['found' => false, 'error' => sprintf('Feedback "%s" has already been forwarded to "%s".', $feedbackId, $record['forwarded_to'])];
        }

        $statement = $this->database->prepare(
            'UPDATE feedback_records SET status = :status, forwarded_to = :forwarded_to, updated_at = :updated_at WHERE feedback_id = :feedback_id'
        );
        $statement->execute(['status' => 'forwarded', 'forwarded_to' => $forwardedTo, 'updated_at' => gmdate(DATE_RFC3339), 'feedback_id' => $feedbackId]);

        $this->publish('forwarded', $feedbackId);

        return ['found' => true, 'error' => null];
    }

    /**
     * @param array{source_type?: string, status?: string} $filters
     * @return array<int, array<string, mixed>>
     */
    public function list(array $filters = []): array
    {
        $conditions = ['1 = 1'];
        $parameters = [];

        foreach (['source_type', 'status'] as $field) {
            if (isset($filters[$field])) {
                $conditions[] = "{$field} = :{$field}";
                $parameters[$field] = $filters[$field];
            }
        }

        $statement = $this->database->prepare(
            'SELECT feedback_id, source_type, status, identity_ref, created_at, updated_at
             FROM feedback_records WHERE ' . implode(' AND ', $conditions) . ' ORDER BY rowid DESC'
        );
        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findByContentHash(string $contentHash): ?array
    {
        $statement = $this->database->prepare('SELECT feedback_id FROM feedback_records WHERE content_hash = :content_hash LIMIT 1');
        $statement->execute(['content_hash' => $contentHash]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array{found: bool, feedback_id: null, outcome: string, error: string}
     */
    private function rejected(string $error): array
    {
        return ['found' => false, 'feedback_id' => null, 'outcome' => 'rejected', 'error' => $error];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetch(string $feedbackId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM feedback_records WHERE feedback_id = :feedback_id');
        $statement->execute(['feedback_id' => $feedbackId]);
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
            'feedback_id' => $record['feedback_id'],
            'source_type' => $record['source_type'],
            'content' => json_decode((string) $record['content_json'], true, flags: JSON_THROW_ON_ERROR),
            'evidence_references' => json_decode((string) $record['evidence_references_json'], true, flags: JSON_THROW_ON_ERROR),
            'identity_ref' => $record['identity_ref'],
            'status' => $record['status'],
            'forwarded_to' => $record['forwarded_to'],
            'created_at' => $record['created_at'],
            'updated_at' => $record['updated_at'],
        ];
    }

    private function publish(string $eventSuffix, string $feedbackId): void
    {
        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'feedback.' . $eventSuffix,
            new DateTimeImmutable(),
            self::class,
            ['feedback_id' => $feedbackId]
        ));
    }
}
