<?php

declare(strict_types=1);

namespace SquirrelForge\Storage;

use DateTimeImmutable;
use PDO;
use SquirrelForge\Contracts\AuthorizationManagerInterface;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;

/**
 * Validates a data record's structure, schema, integrity, and identifier
 * uniqueness before it enters the Data Layer, per
 * 37_STORAGE/DATA-VALIDATOR.md.
 *
 * Two of the spec's validation categories get a real, non-fabricated
 * implementation rather than a documented gap: "Identifier uniqueness" is
 * checked against a persisted table of previously-validated
 * (record_type, record_id) pairs -- an actual cross-call uniqueness
 * check, not a per-call illusion -- and "Integrity checks" is a real
 * SHA-256 comparison against a caller-supplied expected checksum, not a
 * fabricated pass. "Referential consistency" and "Classification
 * accuracy" are out of scope: they'd need a real Retrieval Manager/Data
 * Manager to check references against and a real classification policy
 * source, neither of which exists. Authorization delegates to the
 * existing AuthorizationManagerInterface (24_SECURITY) when injected,
 * exactly like SqliteMessageValidator, and is checked first -- per the
 * spec's rule against bypassing authorization, a denial short-circuits
 * before any structural/integrity work happens.
 *
 * Governance readiness is the fixed constant `ungoverned` since
 * 23_GOVERNANCE has no policy engine to actually gate on; the spec's
 * "Valid and Valid with Warnings may proceed, subject to governance
 * policy" caveat has no governance layer left to apply.
 */
final class SqliteDataValidator
{
    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly ?AuthorizationManagerInterface $authorization = null,
        private readonly ?EventBusInterface $events = null
    ) {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS data_validations (
                validation_ref TEXT PRIMARY KEY,
                record_type TEXT NOT NULL,
                record_id TEXT NOT NULL,
                category TEXT NOT NULL,
                result TEXT NOT NULL,
                may_proceed INTEGER NOT NULL,
                integrity_status TEXT NOT NULL,
                authorization_status TEXT NOT NULL,
                governance_readiness TEXT NOT NULL,
                error TEXT,
                created_at TEXT NOT NULL
            )'
        );
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS validated_identifiers (
                record_type TEXT NOT NULL,
                record_id TEXT NOT NULL,
                PRIMARY KEY (record_type, record_id)
            )'
        );
    }

    /**
     * @param array<string, mixed> $data
     * @param array{required_fields?: array<int, string>, recommended_fields?: array<int, string>, field_types?: array<string, string>, checksum?: ?string, require_unique?: bool} $rules
     * @param array{identity_ref?: ?string, permission_ref?: ?string, operation?: ?string, correlation_id?: ?string} $context
     * @return array{validation_ref: string, record_type: string, record_id: string, category: string, result: string, may_proceed: bool, integrity_status: string, authorization_status: string, governance_readiness: string, error: ?string}
     */
    public function validate(string $recordType, string $recordId, array $data, array $rules = [], array $context = []): array
    {
        $authorizationStatus = 'not_applicable';

        if ($this->authorization !== null) {
            $result = $this->authorization->authorize(
                $context['identity_ref'] ?? '',
                $context['permission_ref'] ?? 'data:validate',
                $context['operation'] ?? 'validate',
                $recordType,
                $context['correlation_id'] ?? ('correlation_' . bin2hex(random_bytes(8)))
            );

            if (!$result['authorized']) {
                return $this->persist($recordType, $recordId, 'authorization', 'unauthorized', 'not_applicable', 'denied', 'Authorization for this data operation was denied.');
            }

            $authorizationStatus = 'granted';
        }

        foreach ($rules['required_fields'] ?? [] as $field) {
            if (!array_key_exists($field, $data)) {
                return $this->persist($recordType, $recordId, 'required_fields', 'incomplete', 'not_applicable', $authorizationStatus, sprintf('Missing required field "%s".', $field));
            }
        }

        foreach ($rules['field_types'] ?? [] as $field => $expectedType) {
            if (array_key_exists($field, $data) && gettype($data[$field]) !== $expectedType) {
                return $this->persist($recordType, $recordId, 'data_types', 'invalid', 'not_applicable', $authorizationStatus, sprintf('Field "%s" must be of type "%s", got "%s".', $field, $expectedType, gettype($data[$field])));
            }
        }

        if (($rules['require_unique'] ?? false) && $this->identifierAlreadyValidated($recordType, $recordId)) {
            return $this->persist($recordType, $recordId, 'identifier_uniqueness', 'invalid', 'not_applicable', $authorizationStatus, sprintf('Record identifier "%s" for type "%s" has already been validated.', $recordId, $recordType));
        }

        $integrityStatus = 'not_applicable';

        if (isset($rules['checksum'])) {
            $actualChecksum = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));

            if (!hash_equals($rules['checksum'], $actualChecksum)) {
                return $this->persist($recordType, $recordId, 'integrity', 'corrupted', 'failed', $authorizationStatus, 'Checksum does not match the supplied data.');
            }

            $integrityStatus = 'verified';
        }

        if ($rules['require_unique'] ?? false) {
            $this->rememberIdentifier($recordType, $recordId);
        }

        $missingRecommended = array_filter(
            $rules['recommended_fields'] ?? [],
            static fn(string $field): bool => !array_key_exists($field, $data)
        );

        if ($missingRecommended !== []) {
            return $this->persist($recordType, $recordId, 'metadata_completeness', 'valid_with_warnings', $integrityStatus, $authorizationStatus, sprintf('Missing recommended field(s): %s.', implode(', ', $missingRecommended)));
        }

        return $this->persist($recordType, $recordId, 'passed', 'valid', $integrityStatus, $authorizationStatus, null);
    }

    private function identifierAlreadyValidated(string $recordType, string $recordId): bool
    {
        $statement = $this->database->prepare(
            'SELECT 1 FROM validated_identifiers WHERE record_type = :record_type AND record_id = :record_id'
        );
        $statement->execute(['record_type' => $recordType, 'record_id' => $recordId]);

        return $statement->fetch() !== false;
    }

    private function rememberIdentifier(string $recordType, string $recordId): void
    {
        $statement = $this->database->prepare(
            'INSERT OR IGNORE INTO validated_identifiers (record_type, record_id) VALUES (:record_type, :record_id)'
        );
        $statement->execute(['record_type' => $recordType, 'record_id' => $recordId]);
    }

    /**
     * @return array{validation_ref: string, record_type: string, record_id: string, category: string, result: string, may_proceed: bool, integrity_status: string, authorization_status: string, governance_readiness: string, error: ?string}
     */
    private function persist(
        string $recordType,
        string $recordId,
        string $category,
        string $result,
        string $integrityStatus,
        string $authorizationStatus,
        ?string $error
    ): array {
        $validationRef = 'data_validation_' . bin2hex(random_bytes(12));
        $createdAt = gmdate(DATE_RFC3339);
        $governanceReadiness = 'ungoverned';
        $mayProceed = in_array($result, ['valid', 'valid_with_warnings'], true);

        $statement = $this->database->prepare(
            'INSERT INTO data_validations (
                validation_ref, record_type, record_id, category, result, may_proceed,
                integrity_status, authorization_status, governance_readiness, error, created_at
            ) VALUES (
                :validation_ref, :record_type, :record_id, :category, :result, :may_proceed,
                :integrity_status, :authorization_status, :governance_readiness, :error, :created_at
            )'
        );
        $statement->execute([
            'validation_ref' => $validationRef,
            'record_type' => $recordType,
            'record_id' => $recordId,
            'category' => $category,
            'result' => $result,
            'may_proceed' => $mayProceed ? 1 : 0,
            'integrity_status' => $integrityStatus,
            'authorization_status' => $authorizationStatus,
            'governance_readiness' => $governanceReadiness,
            'error' => $error,
            'created_at' => $createdAt,
        ]);

        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'data.validated',
            new DateTimeImmutable(),
            self::class,
            ['validation_ref' => $validationRef, 'record_type' => $recordType, 'result' => $result]
        ));

        return [
            'validation_ref' => $validationRef,
            'record_type' => $recordType,
            'record_id' => $recordId,
            'category' => $category,
            'result' => $result,
            'may_proceed' => $mayProceed,
            'integrity_status' => $integrityStatus,
            'authorization_status' => $authorizationStatus,
            'governance_readiness' => $governanceReadiness,
            'error' => $error,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function validation(string $validationRef): ?array
    {
        $statement = $this->database->prepare(
            'SELECT * FROM data_validations WHERE validation_ref = :validation_ref'
        );
        $statement->execute(['validation_ref' => $validationRef]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $limit = 50): array
    {
        $statement = $this->database->prepare(
            'SELECT * FROM data_validations ORDER BY rowid DESC LIMIT :limit'
        );
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }
}
