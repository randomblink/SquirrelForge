<?php

declare(strict_types=1);

namespace SquirrelForge\Governance;

use DateTimeImmutable;
use PDO;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;

/**
 * Owns deprecation notices -- affected contract, replacement, migration
 * steps, notice version, removal version, owner, and compatibility
 * window -- and enforces that removal occurs only in the announced
 * major version after migration support is available, per
 * 23_GOVERNANCE/DEPRECATION-POLICY.md. Owns its own database, matching
 * every other 23_GOVERNANCE component built this session.
 *
 * "A deprecation notice identifies..." seven named fields is a real,
 * literal completeness check: announceDeprecation() rejects a notice
 * missing any of them. `notice_version` and `removal_version` are
 * parsed as real semantic versions (major.minor.patch), and
 * announcement itself enforces the standard semver deprecation
 * convention this codebase adopts as the literal reading of "the
 * announced major version": removal must target a strictly later major
 * version than the one giving notice, since a breaking removal cannot
 * honestly happen within the same major version as its own notice.
 *
 * "Removal occurs only in the announced major version after migration
 * support is available" is a real, two-part enforced gate on
 * requestRemoval(): the caller's own current version must have reached
 * or passed the announced removal major (never earlier), and migration
 * support must be confirmed -- either directly, or through a genuine
 * composition of the injected SqliteChangeManagement: when the caller
 * names the change that shipped the migration, this class checks that
 * change's own real history for an `approved` decision rather than
 * trusting an unverified claim that migration support exists.
 *
 * Like its siblings, removal is a real, immutable state transition
 * (Announced -> Removed), never a deletion of the notice itself --
 * the notice remains the permanent record that the removal happened
 * and why.
 */
final class SqliteDeprecationPolicy
{
    private const REQUIRED_FIELDS = [
        'affected_contract', 'replacement', 'migration_steps', 'notice_version',
        'removal_version', 'owner', 'compatibility_window',
    ];

    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly ?SqliteChangeManagement $changeManagement = null,
        private readonly ?EventBusInterface $events = null
    ) {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS deprecation_notices (
                notice_id TEXT PRIMARY KEY,
                affected_contract TEXT NOT NULL,
                replacement TEXT NOT NULL,
                migration_steps TEXT NOT NULL,
                notice_version TEXT NOT NULL,
                removal_version TEXT NOT NULL,
                owner TEXT NOT NULL,
                compatibility_window TEXT NOT NULL,
                state TEXT NOT NULL,
                removed_at_version TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array{affected_contract?: string, replacement?: string, migration_steps?: string, notice_version?: string, removal_version?: string, owner?: string, compatibility_window?: string} $notice
     * @return array{notice_id: ?string, state: ?string, outcome: string, error: ?string}
     */
    public function announceDeprecation(string $noticeId, array $notice): array
    {
        $missingField = $this->firstMissingField($notice);

        if ($missingField !== null) {
            return ['notice_id' => null, 'state' => null, 'outcome' => 'invalid_notice', 'error' => sprintf('Missing required field "%s".', $missingField)];
        }

        $noticeMajor = $this->parseMajor($notice['notice_version']);
        $removalMajor = $this->parseMajor($notice['removal_version']);

        if ($noticeMajor === null || $removalMajor === null) {
            return ['notice_id' => null, 'state' => null, 'outcome' => 'invalid_version', 'error' => '"notice_version" and "removal_version" must each be a valid "major.minor.patch" version.'];
        }

        if ($removalMajor <= $noticeMajor) {
            return ['notice_id' => null, 'state' => null, 'outcome' => 'invalid_removal_target', 'error' => sprintf('Removal version major "%d" must be strictly greater than notice version major "%d".', $removalMajor, $noticeMajor)];
        }

        $now = gmdate(DATE_RFC3339);
        $statement = $this->database->prepare(
            'INSERT INTO deprecation_notices (
                notice_id, affected_contract, replacement, migration_steps, notice_version,
                removal_version, owner, compatibility_window, state, removed_at_version, created_at, updated_at
            ) VALUES (
                :notice_id, :affected_contract, :replacement, :migration_steps, :notice_version,
                :removal_version, :owner, :compatibility_window, :state, NULL, :created_at, :updated_at
            )'
        );
        $statement->execute([
            'notice_id' => $noticeId,
            'affected_contract' => $notice['affected_contract'],
            'replacement' => $notice['replacement'],
            'migration_steps' => $notice['migration_steps'],
            'notice_version' => $notice['notice_version'],
            'removal_version' => $notice['removal_version'],
            'owner' => $notice['owner'],
            'compatibility_window' => $notice['compatibility_window'],
            'state' => 'Announced',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->publish('deprecation_policy.announced', $noticeId);

        return ['notice_id' => $noticeId, 'state' => 'Announced', 'outcome' => 'announced', 'error' => null];
    }

    /**
     * @param array{migration_support_confirmed?: bool, migration_change_id?: string} $options
     * @return array{notice_id: string, state: ?string, outcome: string, error: ?string}
     */
    public function requestRemoval(string $noticeId, string $currentVersion, array $options = []): array
    {
        $notice = $this->fetchNotice($noticeId);

        if ($notice === null) {
            return ['notice_id' => $noticeId, 'state' => null, 'outcome' => 'not_found', 'error' => sprintf('Unknown deprecation notice "%s".', $noticeId)];
        }

        if ($notice['state'] !== 'Announced') {
            return ['notice_id' => $noticeId, 'state' => $notice['state'], 'outcome' => 'invalid_transition', 'error' => sprintf('Notice is in state "%s", not "Announced".', $notice['state'])];
        }

        $currentMajor = $this->parseMajor($currentVersion);
        $removalMajor = $this->parseMajor($notice['removal_version']);

        if ($currentMajor === null) {
            return ['notice_id' => $noticeId, 'state' => $notice['state'], 'outcome' => 'invalid_version', 'error' => '"currentVersion" must be a valid "major.minor.patch" version.'];
        }

        if ($currentMajor < $removalMajor) {
            return ['notice_id' => $noticeId, 'state' => $notice['state'], 'outcome' => 'too_early', 'error' => sprintf('Removal was announced for major version "%d"; current version is major "%d".', $removalMajor, $currentMajor)];
        }

        if (!$this->migrationSupportConfirmed($options)) {
            return ['notice_id' => $noticeId, 'state' => $notice['state'], 'outcome' => 'migration_unconfirmed', 'error' => 'Migration support has not been confirmed available.'];
        }

        $statement = $this->database->prepare(
            'UPDATE deprecation_notices SET state = :state, removed_at_version = :removed_at_version, updated_at = :updated_at WHERE notice_id = :notice_id'
        );
        $statement->execute(['state' => 'Removed', 'removed_at_version' => $currentVersion, 'updated_at' => gmdate(DATE_RFC3339), 'notice_id' => $noticeId]);

        $this->publish('deprecation_policy.removed', $noticeId);

        return ['notice_id' => $noticeId, 'state' => 'Removed', 'outcome' => 'removed', 'error' => null];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $noticeId): ?array
    {
        return $this->fetchNotice($noticeId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listByContract(string $affectedContract): array
    {
        $statement = $this->database->prepare('SELECT * FROM deprecation_notices WHERE affected_contract = :affected_contract ORDER BY rowid ASC');
        $statement->execute(['affected_contract' => $affectedContract]);

        return $statement->fetchAll();
    }

    /**
     * @param array{migration_support_confirmed?: bool, migration_change_id?: string} $options
     */
    private function migrationSupportConfirmed(array $options): bool
    {
        if ($this->changeManagement !== null && isset($options['migration_change_id'])) {
            $history = $this->changeManagement->history($options['migration_change_id']);

            return $history !== [] && end($history)['decision'] === 'approved';
        }

        return ($options['migration_support_confirmed'] ?? false) === true;
    }

    /**
     * @param array<string, mixed> $notice
     */
    private function firstMissingField(array $notice): ?string
    {
        foreach (self::REQUIRED_FIELDS as $field) {
            if (($notice[$field] ?? '') === '') {
                return $field;
            }
        }

        return null;
    }

    private function parseMajor(string $version): ?int
    {
        return preg_match('/^(\d+)\.\d+\.\d+$/', $version, $matches) === 1 ? (int) $matches[1] : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchNotice(string $noticeId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM deprecation_notices WHERE notice_id = :notice_id');
        $statement->execute(['notice_id' => $noticeId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    private function publish(string $eventName, string $noticeId): void
    {
        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            $eventName,
            new DateTimeImmutable(),
            self::class,
            ['notice_id' => $noticeId]
        ));
    }
}
