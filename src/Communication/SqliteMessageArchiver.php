<?php

declare(strict_types=1);

namespace SquirrelForge\Communication;

use Closure;
use DateTimeImmutable;
use PDO;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;

/**
 * Manages secure storage, indexing, retention, retrieval, and disposal
 * of communication records, per 36_COMMUNICATION/MESSAGE-ARCHIVER.md.
 * "The Message Archiver does not modify message contents or
 * participate in message routing" is upheld literally: archive() only
 * ever writes a new immutable record, and this class has no routing
 * method at all.
 *
 * None of the spec's named Integration Responsibilities are composed:
 * "Archived messages" arrive here as caller-supplied data from
 * whichever real component produced them (Communication Manager,
 * Conversation Manager, Notification Manager, Event Bus, Agent
 * Communicator, Service Messenger), the same "Depends On vs Inputs"
 * distinction established elsewhere in this codebase -- archiving is
 * an independent write path a caller invokes after the fact, not a
 * live subscription to those components' own traffic.
 *
 * "Preserve message integrity" and periodic "Record integrity"
 * verification reuse the same real self-consistency check
 * `SqliteIndexManager::verifyIntegrity()` and
 * `SqliteVersionManager::verifyIntegrity()` already established: a
 * sha256 checksum of the archived payload is stored at archive time,
 * and verifyIntegrity() independently re-hashes and compares.
 *
 * "Full-text indexing (where supported)" is the one Retrieval
 * Capability not implemented: this class supports the other nine --
 * message ID, conversation ID, workflow ID, correlation ID, source,
 * destination, time range, message type, and classification -- as real
 * structured filters, matching the spec's own conditional "where
 * supported" language rather than fabricating a text index.
 *
 * Record Disposal is a real, enforced evidence gate, not a bare
 * delete: dispose() refuses when legal hold is active, and requires
 * caller-supplied `retention_period_satisfied` and
 * `governance_approved` evidence -- both must be true, matching the
 * spec's own "Retention requirements are satisfied... Legal holds do
 * not apply... Governance approval exists" list. A disposed record's
 * row is retained with `disposed = true` rather than deleted outright,
 * upholding "Audit evidence is preserved" and "Disposal is fully
 * recorded."
 *
 * Governance status is the fixed constant `ungoverned`:
 * `36_COMMUNICATION/COMMUNICATION-GOVERNANCE.md` is real now as
 * `SqliteCommunicationGovernance` (reachable through
 * `SqliteCommunicationManager`'s `governance_message` type), but this
 * spec names no per-archive governance-decision request shape to build
 * inline here -- `dispose()`'s own caller-supplied `governance_approved`
 * evidence is the real gate this spec actually names.
 *
 * Owns two tables: `archived_messages` holds the immutable archived
 * record itself, and `archive_operations` is the append-only audit log
 * the Audit Requirements name, recording every archive()/dispose() call
 * unconditionally, including rejected operations.
 */
final class SqliteMessageArchiver
{
    private const ARCHIVE_TYPES = [
        'user_message', 'agent_message', 'service_message', 'workflow_message', 'notification_record',
        'event_record', 'governance_communication', 'audit_communication', 'integration_communication', 'system_communication',
    ];

    private PDO $database;

    public function __construct(
        string $databasePath,
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
            'CREATE TABLE IF NOT EXISTS archived_messages (
                archive_id TEXT PRIMARY KEY,
                message_id TEXT,
                conversation_id TEXT,
                workflow_id TEXT,
                correlation_id TEXT,
                source TEXT,
                destination TEXT,
                archive_type TEXT NOT NULL,
                retention_classification TEXT NOT NULL,
                payload_json TEXT NOT NULL,
                checksum TEXT NOT NULL,
                legal_hold INTEGER NOT NULL DEFAULT 0,
                disposed INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL
            )'
        );
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS archive_operations (
                archive_operation_id TEXT PRIMARY KEY,
                archive_id TEXT,
                message_id TEXT,
                retention_classification TEXT,
                integrity_status TEXT NOT NULL,
                governance_status TEXT NOT NULL,
                final_outcome TEXT NOT NULL,
                error TEXT,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array{message_id?: ?string, conversation_id?: ?string, workflow_id?: ?string, correlation_id?: ?string, source?: ?string, destination?: ?string, payload?: array<string, mixed>} $record
     * @return array{outcome: string, archive_id: ?string, error: ?string}
     */
    public function archive(string $archiveType, array $record, string $retentionClassification): array
    {
        if (!in_array($archiveType, self::ARCHIVE_TYPES, true)) {
            return $this->reject($record['message_id'] ?? null, $retentionClassification, sprintf('"%s" is not a supported archive type.', $archiveType));
        }

        $archiveId = 'archive_' . bin2hex(random_bytes(12));
        $payloadJson = json_encode($record['payload'] ?? [], JSON_THROW_ON_ERROR);
        $checksum = hash('sha256', $payloadJson);

        $statement = $this->database->prepare(
            'INSERT INTO archived_messages (
                archive_id, message_id, conversation_id, workflow_id, correlation_id, source, destination,
                archive_type, retention_classification, payload_json, checksum, legal_hold, disposed, created_at
            ) VALUES (
                :archive_id, :message_id, :conversation_id, :workflow_id, :correlation_id, :source, :destination,
                :archive_type, :retention_classification, :payload_json, :checksum, 0, 0, :created_at
            )'
        );
        $statement->execute([
            'archive_id' => $archiveId,
            'message_id' => $record['message_id'] ?? null,
            'conversation_id' => $record['conversation_id'] ?? null,
            'workflow_id' => $record['workflow_id'] ?? null,
            'correlation_id' => $record['correlation_id'] ?? null,
            'source' => $record['source'] ?? null,
            'destination' => $record['destination'] ?? null,
            'archive_type' => $archiveType,
            'retention_classification' => $retentionClassification,
            'payload_json' => $payloadJson,
            'checksum' => $checksum,
            'created_at' => $this->nowString(),
        ]);

        return $this->recordOperation($archiveId, $record['message_id'] ?? null, $retentionClassification, 'recorded', 'archived', null);
    }

    /**
     * @param array{message_id?: string, conversation_id?: string, workflow_id?: string, correlation_id?: string, source?: string, destination?: string, message_type?: string, classification?: string, since?: string, until?: string} $criteria
     * @return array<int, array<string, mixed>>
     */
    public function retrieve(array $criteria = []): array
    {
        $fieldMap = [
            'message_id' => 'message_id', 'conversation_id' => 'conversation_id', 'workflow_id' => 'workflow_id',
            'correlation_id' => 'correlation_id', 'source' => 'source', 'destination' => 'destination',
            'message_type' => 'archive_type', 'classification' => 'retention_classification',
        ];

        $conditions = ['disposed = 0'];
        $parameters = [];

        foreach ($fieldMap as $criterionKey => $column) {
            if (isset($criteria[$criterionKey])) {
                $conditions[] = "{$column} = :{$criterionKey}";
                $parameters[$criterionKey] = $criteria[$criterionKey];
            }
        }

        if (isset($criteria['since'])) {
            $conditions[] = 'created_at >= :since';
            $parameters['since'] = $criteria['since'];
        }

        if (isset($criteria['until'])) {
            $conditions[] = 'created_at <= :until';
            $parameters['until'] = $criteria['until'];
        }

        $statement = $this->database->prepare('SELECT * FROM archived_messages WHERE ' . implode(' AND ', $conditions) . ' ORDER BY created_at DESC');
        $statement->execute($parameters);

        return array_map($this->hydrate(...), $statement->fetchAll());
    }

    /**
     * @return array{verified: bool, outcome: string, error: ?string}
     */
    public function verifyIntegrity(string $archiveId): array
    {
        $record = $this->fetch($archiveId);

        if ($record === null) {
            return ['verified' => false, 'outcome' => 'not_found', 'error' => sprintf('No archived record "%s" exists.', $archiveId)];
        }

        $verified = hash_equals($record['checksum'], hash('sha256', (string) $record['payload_json']));

        return ['verified' => $verified, 'outcome' => $verified ? 'verified' : 'corrupted', 'error' => $verified ? null : 'Stored checksum does not match the archived payload.'];
    }

    /**
     * @return array{outcome: string, error: ?string}
     */
    public function setLegalHold(string $archiveId, bool $hold): array
    {
        if ($this->fetch($archiveId) === null) {
            return ['outcome' => 'not_found', 'error' => sprintf('No archived record "%s" exists.', $archiveId)];
        }

        $statement = $this->database->prepare('UPDATE archived_messages SET legal_hold = :legal_hold WHERE archive_id = :archive_id');
        $statement->execute(['legal_hold' => $hold ? 1 : 0, 'archive_id' => $archiveId]);

        return ['outcome' => $hold ? 'hold_applied' : 'hold_released', 'error' => null];
    }

    /**
     * @param array{retention_period_satisfied?: bool, governance_approved?: bool} $evidence
     * @return array{outcome: string, error: ?string}
     */
    public function dispose(string $archiveId, array $evidence): array
    {
        $record = $this->fetch($archiveId);

        if ($record === null) {
            $result = $this->recordOperation($archiveId, null, null, 'not_applicable', 'not_found', sprintf('No archived record "%s" exists.', $archiveId));

            return ['outcome' => $result['outcome'], 'error' => $result['error']];
        }

        if ((bool) $record['legal_hold']) {
            $result = $this->recordOperation($archiveId, $record['message_id'], $record['retention_classification'], 'not_applicable', 'blocked_legal_hold', 'Disposal is blocked while a legal hold is active.');

            return ['outcome' => $result['outcome'], 'error' => $result['error']];
        }

        if (($evidence['retention_period_satisfied'] ?? false) !== true || ($evidence['governance_approved'] ?? false) !== true) {
            $result = $this->recordOperation($archiveId, $record['message_id'], $record['retention_classification'], 'not_applicable', 'blocked_evidence_incomplete', 'Disposal requires both retention_period_satisfied and governance_approved evidence.');

            return ['outcome' => $result['outcome'], 'error' => $result['error']];
        }

        $statement = $this->database->prepare('UPDATE archived_messages SET disposed = 1 WHERE archive_id = :archive_id');
        $statement->execute(['archive_id' => $archiveId]);

        $result = $this->recordOperation($archiveId, $record['message_id'], $record['retention_classification'], 'not_applicable', 'disposed', null);

        return ['outcome' => $result['outcome'], 'error' => $result['error']];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $archiveId): ?array
    {
        $record = $this->fetch($archiveId);

        return $record !== null ? $this->hydrate($record) : null;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        $row['payload'] = json_decode((string) $row['payload_json'], true, flags: JSON_THROW_ON_ERROR);
        $row['legal_hold'] = (bool) $row['legal_hold'];
        $row['disposed'] = (bool) $row['disposed'];
        unset($row['payload_json']);

        return $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetch(string $archiveId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM archived_messages WHERE archive_id = :archive_id');
        $statement->execute(['archive_id' => $archiveId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array{outcome: string, archive_id: ?string, error: ?string}
     */
    private function reject(?string $messageId, string $retentionClassification, string $error): array
    {
        $result = $this->recordOperation(null, $messageId, $retentionClassification, 'not_applicable', 'rejected', $error);

        return ['outcome' => $result['outcome'], 'archive_id' => null, 'error' => $error];
    }

    /**
     * @return array{outcome: string, archive_id: ?string, error: ?string}
     */
    private function recordOperation(
        ?string $archiveId,
        ?string $messageId,
        ?string $retentionClassification,
        string $integrityStatus,
        string $outcome,
        ?string $error
    ): array {
        $operationId = 'archive_op_' . bin2hex(random_bytes(12));

        $statement = $this->database->prepare(
            'INSERT INTO archive_operations (
                archive_operation_id, archive_id, message_id, retention_classification,
                integrity_status, governance_status, final_outcome, error, created_at
            ) VALUES (
                :id, :archive_id, :message_id, :retention_classification,
                :integrity_status, :governance_status, :final_outcome, :error, :created_at
            )'
        );
        $statement->execute([
            'id' => $operationId,
            'archive_id' => $archiveId,
            'message_id' => $messageId,
            'retention_classification' => $retentionClassification,
            'integrity_status' => $integrityStatus,
            'governance_status' => 'ungoverned',
            'final_outcome' => $outcome,
            'error' => $error,
            'created_at' => $this->nowString(),
        ]);

        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'message_archiver.' . $outcome,
            new DateTimeImmutable(),
            self::class,
            ['archive_id' => $archiveId, 'message_id' => $messageId, 'outcome' => $outcome]
        ));

        return ['outcome' => $outcome, 'archive_id' => $archiveId, 'error' => $error];
    }

    private function nowString(): string
    {
        $now = $this->clock !== null ? ($this->clock)() : new DateTimeImmutable();

        return $now->format(DATE_RFC3339);
    }
}
