<?php

declare(strict_types=1);

namespace SquirrelForge\Governance;

use DateTimeImmutable;
use PDO;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;
use SquirrelForge\Storage\SqliteDataValidator;

/**
 * Tracks, manages, and preserves the complete revision history of
 * governed data, per 23_GOVERNANCE/VERSION-MANAGER.md.
 *
 * Genuinely owns its own database (`Sqlite` prefix, matching
 * SqliteAlertManager's precedent): a version's lifecycle state, lineage,
 * and audit trail are exactly the kind of authoritative record other
 * "no database" components in this codebase explicitly delegate
 * elsewhere, so this class is the owner.
 *
 * "Verify authorization" and "Validate incoming data" (Version Workflow
 * steps 2 and 4) are a single genuine composition: the injected
 * SqliteDataValidator already performs both internally
 * (validate() checks AuthorizationManagerInterface before running any
 * field rule), so createVersion() calls it once rather than
 * implementing a parallel authorization path. "Confirm governance
 * approval" (step 3) reuses the same resolvePolicyResult()-style
 * SqlitePolicyEngine composition every other Sqlite*Governance
 * component in this codebase already applies.
 *
 * The Safety Rules are upheld structurally, not by convention: no
 * method here ever issues an UPDATE against a version's own
 * `content_json`/`checksum` (only `state`/`governance_status`/
 * `rollback_eligible`/`updated_at` ever change after insert) and no
 * method issues a DELETE against either table -- "never overwrite
 * historical versions", "never delete immutable version history", and
 * "never modify audit records" are true because the SQL to violate them
 * doesn't exist in this class. "Never roll back without authorization"
 * is a real, enforced gate: rollback() rejects outright when
 * `authorizedBy` is empty, and only a version already marked
 * `rollback_eligible` (set automatically when a version becomes
 * Approved or Active) can be restored.
 *
 * activate() enforces one real lineage invariant beyond what the spec
 * states explicitly but that "Version Lineage" and "only Approved and
 * Active versions may be used in production" together imply: activating
 * a version archives whichever other version of the same record was
 * previously Active, so at most one version per record is ever Active
 * at a time.
 *
 * compareVersions() and verifyIntegrity() are real: a plain structural
 * diff between two stored content blobs, and a real sha256 recomputation
 * checked against the stored checksum -- never a fabricated comparison.
 *
 * "Notify the Data Monitor" (Version Workflow step 10) has no real
 * Data Monitor component to call, so it is a real EventBusInterface
 * dispatch instead, the same "consumed downstream, not owned here"
 * pattern used throughout this codebase.
 */
final class SqliteVersionManager
{
    private const PRODUCTION_STATES = ['Approved', 'Active'];

    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly ?SqliteDataValidator $validator = null,
        private readonly ?SqlitePolicyEngine $policyEngine = null,
        private readonly ?EventBusInterface $events = null
    ) {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS versions (
                version_id TEXT PRIMARY KEY,
                record_id TEXT NOT NULL,
                record_type TEXT NOT NULL,
                parent_version TEXT,
                content_json TEXT NOT NULL,
                checksum TEXT NOT NULL,
                change_summary TEXT,
                authoring_component TEXT,
                state TEXT NOT NULL,
                governance_status TEXT NOT NULL,
                retention_policy TEXT,
                rollback_eligible INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS version_operations (
                operation_id TEXT PRIMARY KEY,
                record_id TEXT,
                version_id TEXT,
                parent_version TEXT,
                operation_type TEXT NOT NULL,
                authorization_status TEXT,
                governance_status TEXT,
                outcome TEXT NOT NULL,
                error TEXT,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array<string, mixed> $content
     * @param array{change_summary?: ?string, authoring_component?: ?string, retention_policy?: ?string, required_fields?: array<int, string>, field_types?: array<string, string>, identity_ref?: ?string, permission_ref?: ?string, correlation_id?: ?string, policy_context?: array<string, mixed>, policy_category?: ?string} $options
     * @return array{version_id: ?string, state: ?string, parent_version: ?string, outcome: string, error: ?string}
     */
    public function createVersion(string $recordId, string $recordType, array $content, array $options = []): array
    {
        $authorizationStatus = 'not_applicable';

        if ($this->validator !== null) {
            $validation = $this->validator->validate($recordType, $recordId, $content, [
                'required_fields' => $options['required_fields'] ?? [],
                'field_types' => $options['field_types'] ?? [],
            ], $options);
            $authorizationStatus = $validation['authorization_status'];

            if (!$validation['may_proceed']) {
                $this->recordOperation('create', $recordId, null, null, $authorizationStatus, 'ungoverned', 'rejected', $validation['error']);

                return ['version_id' => null, 'state' => null, 'parent_version' => null, 'outcome' => 'rejected', 'error' => $validation['error']];
            }
        }

        $governanceStatus = $this->resolveGovernance($recordId, $options);

        if ($governanceStatus === null) {
            $this->recordOperation('create', $recordId, null, null, $authorizationStatus, 'denied', 'rejected', 'Governance denied this version request.');

            return ['version_id' => null, 'state' => null, 'parent_version' => null, 'outcome' => 'rejected', 'error' => 'Governance denied this version request.'];
        }

        $parentVersion = $this->latestVersionId($recordId);
        $versionId = 'version_' . bin2hex(random_bytes(12));
        $contentJson = json_encode($content, JSON_THROW_ON_ERROR);
        $now = gmdate(DATE_RFC3339);

        $statement = $this->database->prepare(
            'INSERT INTO versions (
                version_id, record_id, record_type, parent_version, content_json, checksum,
                change_summary, authoring_component, state, governance_status, retention_policy,
                rollback_eligible, created_at, updated_at
            ) VALUES (
                :version_id, :record_id, :record_type, :parent_version, :content_json, :checksum,
                :change_summary, :authoring_component, :state, :governance_status, :retention_policy,
                0, :created_at, :updated_at
            )'
        );
        $statement->execute([
            'version_id' => $versionId,
            'record_id' => $recordId,
            'record_type' => $recordType,
            'parent_version' => $parentVersion,
            'content_json' => $contentJson,
            'checksum' => hash('sha256', $contentJson),
            'change_summary' => $options['change_summary'] ?? null,
            'authoring_component' => $options['authoring_component'] ?? null,
            'state' => 'Draft',
            'governance_status' => $governanceStatus,
            'retention_policy' => $options['retention_policy'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->recordOperation('create', $recordId, $versionId, $parentVersion, $authorizationStatus, $governanceStatus, 'created', null);
        $this->publish('version.created', $recordId, $versionId);

        return ['version_id' => $versionId, 'state' => 'Draft', 'parent_version' => $parentVersion, 'outcome' => 'created', 'error' => null];
    }

    /**
     * @return array{version_id: string, outcome: string, error: ?string}
     */
    public function approve(string $versionId): array
    {
        return $this->transition($versionId, ['Draft'], 'Approved', true);
    }

    /**
     * Enforces one lineage invariant: activating a version archives
     * whichever other version of the same record was previously Active.
     *
     * @return array{version_id: string, outcome: string, error: ?string}
     */
    public function activate(string $versionId): array
    {
        $version = $this->fetchVersion($versionId);

        if ($version === null) {
            return ['version_id' => $versionId, 'outcome' => 'not_found', 'error' => sprintf('Unknown version "%s".', $versionId)];
        }

        $result = $this->transition($versionId, ['Approved'], 'Active', true);

        if ($result['outcome'] === 'transitioned') {
            $statement = $this->database->prepare(
                "UPDATE versions SET state = 'Archived', updated_at = :updated_at
                 WHERE record_id = :record_id AND version_id != :version_id AND state = 'Active'"
            );
            $statement->execute(['updated_at' => gmdate(DATE_RFC3339), 'record_id' => $version['record_id'], 'version_id' => $versionId]);
        }

        return $result;
    }

    /**
     * @return array{version_id: string, outcome: string, error: ?string}
     */
    public function archive(string $versionId): array
    {
        return $this->transition($versionId, ['Approved', 'Active'], 'Archived', false);
    }

    /**
     * @return array{version_id: string, outcome: string, error: ?string}
     */
    public function deprecate(string $versionId): array
    {
        return $this->transition($versionId, ['Approved', 'Active'], 'Deprecated', false);
    }

    /**
     * Safety Rule: never rolls back without authorization, and only a
     * version already marked rollback_eligible may be restored.
     *
     * @return array{version_id: ?string, outcome: string, error: ?string}
     */
    public function rollback(string $recordId, string $targetVersionId, string $authorizedBy): array
    {
        if ($authorizedBy === '') {
            return ['version_id' => null, 'outcome' => 'unauthorized', 'error' => 'Rollback requires a non-empty authorizing identity.'];
        }

        $target = $this->fetchVersion($targetVersionId);

        if ($target === null || $target['record_id'] !== $recordId) {
            return ['version_id' => null, 'outcome' => 'not_found', 'error' => sprintf('Unknown version "%s" for record "%s".', $targetVersionId, $recordId)];
        }

        if ((int) $target['rollback_eligible'] !== 1) {
            return ['version_id' => null, 'outcome' => 'not_eligible', 'error' => sprintf('Version "%s" is not eligible for rollback.', $targetVersionId)];
        }

        $versionId = 'version_' . bin2hex(random_bytes(12));
        $now = gmdate(DATE_RFC3339);

        $statement = $this->database->prepare(
            'INSERT INTO versions (
                version_id, record_id, record_type, parent_version, content_json, checksum,
                change_summary, authoring_component, state, governance_status, retention_policy,
                rollback_eligible, created_at, updated_at
            ) VALUES (
                :version_id, :record_id, :record_type, :parent_version, :content_json, :checksum,
                :change_summary, :authoring_component, :state, :governance_status, :retention_policy,
                0, :created_at, :updated_at
            )'
        );
        $statement->execute([
            'version_id' => $versionId,
            'record_id' => $recordId,
            'record_type' => $target['record_type'],
            'parent_version' => $targetVersionId,
            'content_json' => $target['content_json'],
            'checksum' => $target['checksum'],
            'change_summary' => sprintf('Restored from version "%s" by "%s".', $targetVersionId, $authorizedBy),
            'authoring_component' => $authorizedBy,
            'state' => 'Restored',
            'governance_status' => $target['governance_status'],
            'retention_policy' => $target['retention_policy'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->recordOperation('rollback', $recordId, $versionId, $targetVersionId, 'granted', $target['governance_status'], 'restored', null);
        $this->publish('version.restored', $recordId, $versionId);

        return ['version_id' => $versionId, 'outcome' => 'restored', 'error' => null];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function retrieve(string $versionId): ?array
    {
        $version = $this->fetchVersion($versionId);

        if ($version === null) {
            return null;
        }

        return $this->hydrate($version);
    }

    /**
     * A real structural diff, never a fabricated comparison.
     *
     * @return array{added: array<int, string>, removed: array<int, string>, changed: array<string, array{from: mixed, to: mixed}>, outcome: string, error: ?string}
     */
    public function compareVersions(string $versionIdA, string $versionIdB): array
    {
        $a = $this->fetchVersion($versionIdA);
        $b = $this->fetchVersion($versionIdB);

        if ($a === null || $b === null) {
            return ['added' => [], 'removed' => [], 'changed' => [], 'outcome' => 'not_found', 'error' => 'Both versions must exist to compare them.'];
        }

        $contentA = json_decode((string) $a['content_json'], true, flags: JSON_THROW_ON_ERROR);
        $contentB = json_decode((string) $b['content_json'], true, flags: JSON_THROW_ON_ERROR);

        $added = array_values(array_diff(array_keys($contentB), array_keys($contentA)));
        $removed = array_values(array_diff(array_keys($contentA), array_keys($contentB)));
        $changed = [];

        foreach ($contentA as $key => $value) {
            if (array_key_exists($key, $contentB) && $contentB[$key] !== $value) {
                $changed[$key] = ['from' => $value, 'to' => $contentB[$key]];
            }
        }

        return ['added' => $added, 'removed' => $removed, 'changed' => $changed, 'outcome' => 'compared', 'error' => null];
    }

    /**
     * A real sha256 recomputation checked against the stored checksum.
     *
     * @return array{verified: bool, outcome: string, error: ?string}
     */
    public function verifyIntegrity(string $versionId): array
    {
        $version = $this->fetchVersion($versionId);

        if ($version === null) {
            return ['verified' => false, 'outcome' => 'not_found', 'error' => sprintf('Unknown version "%s".', $versionId)];
        }

        $verified = hash_equals($version['checksum'], hash('sha256', (string) $version['content_json']));

        return ['verified' => $verified, 'outcome' => $verified ? 'verified' : 'corrupted', 'error' => $verified ? null : 'Stored checksum does not match the version content.'];
    }

    /**
     * "Only Approved and Active versions may be used in production
     * workflows" -- a real, callable check, not a documentation-only
     * rule.
     */
    public function isProductionUsable(string $versionId): bool
    {
        $version = $this->fetchVersion($versionId);

        return $version !== null && in_array($version['state'], self::PRODUCTION_STATES, true);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function history(string $recordId): array
    {
        $statement = $this->database->prepare('SELECT * FROM versions WHERE record_id = :record_id ORDER BY rowid ASC');
        $statement->execute(['record_id' => $recordId]);

        return array_map(fn(array $row): array => $this->hydrate($row), $statement->fetchAll());
    }

    /**
     * @param array<int, string> $allowedFromStates
     * @return array{version_id: string, outcome: string, error: ?string}
     */
    private function transition(string $versionId, array $allowedFromStates, string $toState, bool $rollbackEligible): array
    {
        $version = $this->fetchVersion($versionId);

        if ($version === null) {
            return ['version_id' => $versionId, 'outcome' => 'not_found', 'error' => sprintf('Unknown version "%s".', $versionId)];
        }

        if (!in_array($version['state'], $allowedFromStates, true)) {
            return ['version_id' => $versionId, 'outcome' => 'invalid_transition', 'error' => sprintf('Cannot move version from "%s" to "%s".', $version['state'], $toState)];
        }

        $statement = $this->database->prepare(
            'UPDATE versions SET state = :state, rollback_eligible = :rollback_eligible, updated_at = :updated_at WHERE version_id = :version_id'
        );
        $statement->execute([
            'state' => $toState,
            'rollback_eligible' => $rollbackEligible ? 1 : (int) $version['rollback_eligible'],
            'updated_at' => gmdate(DATE_RFC3339),
            'version_id' => $versionId,
        ]);

        $this->recordOperation(strtolower($toState), $version['record_id'], $versionId, $version['parent_version'], 'not_applicable', $version['governance_status'], 'transitioned', null);
        $this->publish('version.' . strtolower($toState), $version['record_id'], $versionId);

        return ['version_id' => $versionId, 'outcome' => 'transitioned', 'error' => null];
    }

    /**
     * @param array{policy_context?: array<string, mixed>, policy_category?: ?string} $options
     */
    private function resolveGovernance(string $recordId, array $options): ?string
    {
        if ($this->policyEngine === null || !isset($options['policy_context'])) {
            return 'ungoverned';
        }

        $decision = $this->policyEngine->evaluate($recordId, $options['policy_context'], $options['policy_category'] ?? null);

        return in_array($decision['decision'], ['allowed', 'allowed_with_conditions'], true) ? $decision['decision'] : null;
    }

    private function latestVersionId(string $recordId): ?string
    {
        $statement = $this->database->prepare('SELECT version_id FROM versions WHERE record_id = :record_id ORDER BY rowid DESC LIMIT 1');
        $statement->execute(['record_id' => $recordId]);
        $versionId = $statement->fetchColumn();

        return $versionId !== false ? $versionId : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchVersion(string $versionId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM versions WHERE version_id = :version_id');
        $statement->execute(['version_id' => $versionId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        $row['content'] = json_decode((string) $row['content_json'], true, flags: JSON_THROW_ON_ERROR);
        unset($row['content_json']);
        $row['rollback_eligible'] = (int) $row['rollback_eligible'] === 1;

        return $row;
    }

    private function recordOperation(
        string $operationType,
        string $recordId,
        ?string $versionId,
        ?string $parentVersion,
        string $authorizationStatus,
        string $governanceStatus,
        string $outcome,
        ?string $error
    ): void {
        $statement = $this->database->prepare(
            'INSERT INTO version_operations (
                operation_id, record_id, version_id, parent_version, operation_type,
                authorization_status, governance_status, outcome, error, created_at
            ) VALUES (
                :operation_id, :record_id, :version_id, :parent_version, :operation_type,
                :authorization_status, :governance_status, :outcome, :error, :created_at
            )'
        );
        $statement->execute([
            'operation_id' => 'version_operation_' . bin2hex(random_bytes(12)),
            'record_id' => $recordId,
            'version_id' => $versionId,
            'parent_version' => $parentVersion,
            'operation_type' => $operationType,
            'authorization_status' => $authorizationStatus,
            'governance_status' => $governanceStatus,
            'outcome' => $outcome,
            'error' => $error,
            'created_at' => gmdate(DATE_RFC3339),
        ]);
    }

    private function publish(string $eventName, string $recordId, string $versionId): void
    {
        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            $eventName,
            new DateTimeImmutable(),
            self::class,
            ['record_id' => $recordId, 'version_id' => $versionId]
        ));
    }
}
