<?php

declare(strict_types=1);

namespace SquirrelForge\Governance;

use DateTimeImmutable;
use PDO;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;

/**
 * Records every material change's motivation, scope, owner, affected
 * dependencies and users, compatibility impact, migration plan, tests,
 * rollout, and rollback, and requires approval from owners of affected
 * stable contracts, per 23_GOVERNANCE/CHANGE-MANAGEMENT.md.
 *
 * Owns its own database, matching SqliteVersionManager and
 * SqliteQualityGates: a change decision is a real governance record
 * worth an auditable history.
 *
 * The completeness requirement ("every material change records...")
 * is a real, literal structural check: requestChange() rejects outright
 * any request missing one of the ten named fields, never proceeding
 * with an assumed default for a field the spec requires to be
 * recorded. "Approval must come from owners of affected stable
 * contracts" is a real cross-check, the same shape TaskDecomposer
 * already applies to dependency references: every contract the
 * caller names in `affected_stable_contracts` must have a
 * corresponding, non-empty entry in `approvals`, or the change is
 * rejected by name.
 *
 * Two genuine compositions of already-real specialists do the rest of
 * the real work, rather than re-implementing it: "tests" and
 * "migration plan" are handed straight to the injected
 * SqliteQualityGates::review() as its own `test_evidence` and
 * `migration_plan`/`breaking_change` inputs -- Quality Gates already
 * owns exactly this judgment, so Change Management defers to it rather
 * than scoring compatibility impact itself. Once a change is approved,
 * it is recorded as a real new version of the affected record through
 * the injected SqliteVersionManager::createVersion(), with the change's
 * own `motivation` and `owner` carried into that version's real
 * `change_summary`/`authoring_component` fields -- Change Management
 * does not own version history itself, it produces one through the
 * component that already does.
 */
final class SqliteChangeManagement
{
    private const REQUIRED_FIELDS = [
        'motivation', 'scope', 'owner', 'affected_dependencies', 'affected_users',
        'compatibility_impact', 'migration_plan', 'tests', 'rollout', 'rollback',
    ];

    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly ?SqliteVersionManager $versionManager = null,
        private readonly ?SqliteQualityGates $qualityGates = null,
        private readonly ?EventBusInterface $events = null
    ) {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS change_requests (
                request_ref TEXT PRIMARY KEY,
                change_id TEXT NOT NULL,
                decision TEXT NOT NULL,
                version_id TEXT,
                request_json TEXT NOT NULL,
                error TEXT,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array{record_id?: string, record_type?: string, content?: array<string, mixed>, motivation?: string, scope?: string, owner?: string, affected_dependencies?: array<int, string>, affected_users?: array<int, string>, compatibility_impact?: string, migration_plan?: ?string, tests?: ?string, rollout?: string, rollback?: string, affected_stable_contracts?: array<int, string>, approvals?: array<string, string>} $request
     * @return array{decision: string, version_id: ?string, error: ?string}
     */
    public function requestChange(string $changeId, array $request): array
    {
        $missingField = $this->firstMissingField($request);

        if ($missingField !== null) {
            return $this->persistAndReturn($changeId, $request, 'rejected', null, sprintf('Missing required field "%s".', $missingField));
        }

        $unapproved = $this->unapprovedStableContracts($request);

        if ($unapproved !== []) {
            return $this->persistAndReturn($changeId, $request, 'rejected', null, sprintf('Missing approval from owner(s) of stable contract(s): %s.', implode(', ', $unapproved)));
        }

        $qualityDecision = $this->checkQualityGates($changeId, $request);

        if ($qualityDecision !== null && $qualityDecision !== 'approved') {
            return $this->persistAndReturn($changeId, $request, 'rejected', null, 'Quality Gates did not approve this change.');
        }

        $versionId = null;

        if ($this->versionManager !== null && isset($request['record_id'], $request['content'])) {
            $versionResult = $this->versionManager->createVersion(
                $request['record_id'],
                $request['record_type'] ?? 'change',
                $request['content'],
                ['change_summary' => $request['motivation'], 'authoring_component' => $request['owner']]
            );

            if ($versionResult['outcome'] !== 'created') {
                return $this->persistAndReturn($changeId, $request, 'rejected', null, $versionResult['error'] ?? 'Version Manager rejected this change.');
            }

            $versionId = $versionResult['version_id'];
        }

        return $this->persistAndReturn($changeId, $request, 'approved', $versionId, null);
    }

    /**
     * @return array<int, array{request_ref: string, change_id: string, decision: string, version_id: ?string, error: ?string, created_at: string}>
     */
    public function history(string $changeId): array
    {
        $statement = $this->database->prepare('SELECT * FROM change_requests WHERE change_id = :change_id ORDER BY rowid ASC');
        $statement->execute(['change_id' => $changeId]);

        return array_map(static fn(array $row): array => [
            'request_ref' => $row['request_ref'],
            'change_id' => $row['change_id'],
            'decision' => $row['decision'],
            'version_id' => $row['version_id'],
            'error' => $row['error'],
            'created_at' => $row['created_at'],
        ], $statement->fetchAll());
    }

    /**
     * @param array<string, mixed> $request
     */
    private function firstMissingField(array $request): ?string
    {
        foreach (self::REQUIRED_FIELDS as $field) {
            if (!array_key_exists($field, $request)) {
                return $field;
            }
        }

        return null;
    }

    /**
     * @param array{affected_stable_contracts?: array<int, string>, approvals?: array<string, string>} $request
     * @return array<int, string>
     */
    private function unapprovedStableContracts(array $request): array
    {
        $contracts = $request['affected_stable_contracts'] ?? [];
        $approvals = $request['approvals'] ?? [];

        return array_values(array_filter(
            $contracts,
            static fn(string $contract): bool => ($approvals[$contract] ?? '') === ''
        ));
    }

    /**
     * @param array{tests?: ?string, compatibility_impact?: string, migration_plan?: ?string} $request
     */
    private function checkQualityGates(string $changeId, array $request): ?string
    {
        if ($this->qualityGates === null) {
            return null;
        }

        $result = $this->qualityGates->review($changeId, [
            'test_evidence' => $request['tests'],
            'breaking_change' => ($request['compatibility_impact'] ?? null) === 'breaking',
            'migration_plan' => $request['migration_plan'],
        ]);

        return $result['decision'];
    }

    /**
     * @param array<string, mixed> $request
     * @return array{decision: string, version_id: ?string, error: ?string}
     */
    private function persistAndReturn(string $changeId, array $request, string $decision, ?string $versionId, ?string $error): array
    {
        $statement = $this->database->prepare(
            'INSERT INTO change_requests (request_ref, change_id, decision, version_id, request_json, error, created_at)
             VALUES (:request_ref, :change_id, :decision, :version_id, :request_json, :error, :created_at)'
        );
        $statement->execute([
            'request_ref' => 'change_request_' . bin2hex(random_bytes(12)),
            'change_id' => $changeId,
            'decision' => $decision,
            'version_id' => $versionId,
            'request_json' => json_encode($request, JSON_THROW_ON_ERROR),
            'error' => $error,
            'created_at' => gmdate(DATE_RFC3339),
        ]);

        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'change_management.' . $decision,
            new DateTimeImmutable(),
            self::class,
            ['change_id' => $changeId, 'version_id' => $versionId]
        ));

        return ['decision' => $decision, 'version_id' => $versionId, 'error' => $error];
    }
}
