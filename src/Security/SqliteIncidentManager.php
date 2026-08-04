<?php

declare(strict_types=1);

namespace SquirrelForge\Security;

use Closure;
use DateTimeImmutable;
use PDO;

/**
 * Coordinates the lifecycle of confirmed or suspected security
 * incidents, per 24_SECURITY/INCIDENT-MANAGER.md.
 *
 * "The Incident Manager classifies the incident after reviewing threat
 * evidence... It is not the same as incident classification" is upheld
 * structurally, not just by convention: receiveNotification() -- the
 * intake step a `SqliteThreatDetector` or any other source calls --
 * never accepts or infers a category. Only the separate classify()
 * call, a distinct and later step, sets one, so an incident can never
 * end up classified by anything other than its own explicit act.
 *
 * The seven-state lifecycle (Received -> Classified -> Investigating ->
 * Contained -> Resolved -> Reviewed -> Closed, with Reopened as an
 * eighth terminal-adjacent state) is a real, enforced state machine
 * matching the spec's own eleven-step Incident Response Workflow order
 * -- investigation cannot start before classification, containment
 * cannot be marked complete without at least one recorded containment
 * reference, and resolve() refuses without at least one attached
 * validation-evidence reference, the same "never validate recovery
 * itself, only attach evidence the owning validator supplies" gate
 * SqliteVulnerabilityManager::verify() already established for the
 * sibling spec's "must never... execute recovery... or independently
 * validate recovery."
 *
 * Owns two tables: `incidents` holds one current-state row per incident
 * (with its accumulated containment and validation-evidence
 * references), and `incident_history` is the append-only log of every
 * state transition, matching "maintain the incident-domain record
 * lifecycle."
 */
final class SqliteIncidentManager
{
    private const CATEGORIES = [
        'unauthorized_access', 'credential_compromise', 'data_breach',
        'malware_or_malicious_activity', 'policy_violations', 'service_disruption',
        'insider_threats', 'integration_compromise', 'workflow_abuse',
        'security_configuration_failures',
    ];

    private const SEVERITIES = ['informational', 'low', 'medium', 'high', 'critical'];

    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly ?Closure $clock = null
    ) {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS incidents (
                incident_id TEXT PRIMARY KEY,
                source TEXT NOT NULL,
                threat_assessment_reference TEXT,
                category TEXT,
                severity TEXT,
                status TEXT NOT NULL,
                containment_references_json TEXT NOT NULL,
                validation_evidence_json TEXT NOT NULL,
                post_incident_review_reference TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS incident_history (
                history_id TEXT PRIMARY KEY,
                incident_id TEXT NOT NULL,
                from_status TEXT,
                to_status TEXT NOT NULL,
                note TEXT,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array{source?: string, threat_assessment_reference?: ?string, evidence?: array<int, string>} $payload
     * @return array{outcome: string, incident_id: ?string, error: ?string}
     */
    public function receiveNotification(array $payload): array
    {
        $source = $payload['source'] ?? '';

        if ($source === '') {
            return ['outcome' => 'invalid_source', 'incident_id' => null, 'error' => 'An incident notification requires a non-empty source.'];
        }

        $incidentId = 'incident_' . bin2hex(random_bytes(12));
        $now = $this->nowString();

        $statement = $this->database->prepare(
            'INSERT INTO incidents (
                incident_id, source, threat_assessment_reference, category, severity, status,
                containment_references_json, validation_evidence_json, post_incident_review_reference,
                created_at, updated_at
            ) VALUES (
                :incident_id, :source, :threat_assessment_reference, NULL, NULL, :status,
                :containment_references_json, :validation_evidence_json, NULL,
                :created_at, :updated_at
            )'
        );
        $statement->execute([
            'incident_id' => $incidentId,
            'source' => $source,
            'threat_assessment_reference' => $payload['threat_assessment_reference'] ?? null,
            'status' => 'Received',
            'containment_references_json' => json_encode([], JSON_THROW_ON_ERROR),
            'validation_evidence_json' => json_encode([], JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->recordHistory($incidentId, null, 'Received', null);

        return ['outcome' => 'received', 'incident_id' => $incidentId, 'error' => null];
    }

    /**
     * @return array{outcome: string, error: ?string}
     */
    public function classify(string $incidentId, string $category, string $severity): array
    {
        if (!in_array($category, self::CATEGORIES, true)) {
            return ['outcome' => 'invalid_category', 'error' => sprintf('"%s" is not a recognized incident category.', $category)];
        }

        if (!in_array($severity, self::SEVERITIES, true)) {
            return ['outcome' => 'invalid_severity', 'error' => sprintf('"%s" is not a recognized severity level.', $severity)];
        }

        $record = $this->fetch($incidentId);

        if ($record === null) {
            return ['outcome' => 'not_found', 'error' => sprintf('Incident "%s" is not tracked.', $incidentId)];
        }

        if ($record['status'] !== 'Received') {
            return ['outcome' => 'blocked', 'error' => sprintf('Incident "%s" must be Received, not "%s".', $incidentId, $record['status'])];
        }

        $statement = $this->database->prepare('UPDATE incidents SET category = :category, severity = :severity, status = :status, updated_at = :updated_at WHERE incident_id = :incident_id');
        $statement->execute([
            'category' => $category,
            'severity' => $severity,
            'status' => 'Classified',
            'updated_at' => $this->nowString(),
            'incident_id' => $incidentId,
        ]);

        $this->recordHistory($incidentId, 'Received', 'Classified', null);

        return ['outcome' => 'classified', 'error' => null];
    }

    /**
     * @return array{outcome: string, error: ?string}
     */
    public function startInvestigation(string $incidentId): array
    {
        return $this->transition($incidentId, ['Classified'], 'Investigating');
    }

    /**
     * @return array{outcome: string, error: ?string}
     */
    public function attachContainmentReference(string $incidentId, string $reference): array
    {
        return $this->appendToJsonList($incidentId, 'containment_references_json', $reference, ['Investigating', 'Contained']);
    }

    /**
     * @return array{outcome: string, error: ?string}
     */
    public function markContained(string $incidentId): array
    {
        $record = $this->fetch($incidentId);

        if ($record === null) {
            return ['outcome' => 'not_found', 'error' => sprintf('Incident "%s" is not tracked.', $incidentId)];
        }

        if (json_decode((string) $record['containment_references_json'], true, flags: JSON_THROW_ON_ERROR) === []) {
            return ['outcome' => 'blocked', 'error' => 'Marking an incident contained requires at least one attached containment reference.'];
        }

        return $this->transition($incidentId, ['Investigating'], 'Contained');
    }

    /**
     * @return array{outcome: string, error: ?string}
     */
    public function attachValidationEvidence(string $incidentId, string $evidenceReference): array
    {
        return $this->appendToJsonList($incidentId, 'validation_evidence_json', $evidenceReference, ['Contained', 'Resolved']);
    }

    /**
     * @return array{outcome: string, error: ?string}
     */
    public function resolve(string $incidentId): array
    {
        $record = $this->fetch($incidentId);

        if ($record === null) {
            return ['outcome' => 'not_found', 'error' => sprintf('Incident "%s" is not tracked.', $incidentId)];
        }

        if (json_decode((string) $record['validation_evidence_json'], true, flags: JSON_THROW_ON_ERROR) === []) {
            return ['outcome' => 'blocked', 'error' => 'Resolving an incident requires at least one attached validation evidence reference.'];
        }

        return $this->transition($incidentId, ['Contained'], 'Resolved');
    }

    /**
     * @return array{outcome: string, error: ?string}
     */
    public function conductPostIncidentReview(string $incidentId, string $reviewReference): array
    {
        if ($reviewReference === '') {
            return ['outcome' => 'invalid_reference', 'error' => 'A post-incident review requires a non-empty review reference.'];
        }

        $record = $this->fetch($incidentId);

        if ($record === null) {
            return ['outcome' => 'not_found', 'error' => sprintf('Incident "%s" is not tracked.', $incidentId)];
        }

        if ($record['status'] !== 'Resolved') {
            return ['outcome' => 'blocked', 'error' => sprintf('Incident "%s" must be Resolved, not "%s".', $incidentId, $record['status'])];
        }

        $statement = $this->database->prepare('UPDATE incidents SET post_incident_review_reference = :reference, status = :status, updated_at = :updated_at WHERE incident_id = :incident_id');
        $statement->execute([
            'reference' => $reviewReference,
            'status' => 'Reviewed',
            'updated_at' => $this->nowString(),
            'incident_id' => $incidentId,
        ]);

        $this->recordHistory($incidentId, 'Resolved', 'Reviewed', null);

        return ['outcome' => 'reviewed', 'error' => null];
    }

    /**
     * @return array{outcome: string, error: ?string}
     */
    public function close(string $incidentId): array
    {
        return $this->transition($incidentId, ['Reviewed'], 'Closed');
    }

    /**
     * @return array{outcome: string, error: ?string}
     */
    public function reopen(string $incidentId, string $reason): array
    {
        if ($reason === '') {
            return ['outcome' => 'invalid_reason', 'error' => 'Reopening an incident requires a non-empty reason.'];
        }

        return $this->transition($incidentId, ['Contained', 'Resolved', 'Reviewed', 'Closed'], 'Reopened', $reason);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $incidentId): ?array
    {
        $record = $this->fetch($incidentId);

        if ($record === null) {
            return null;
        }

        $record['containment_references'] = json_decode((string) $record['containment_references_json'], true, flags: JSON_THROW_ON_ERROR);
        $record['validation_evidence'] = json_decode((string) $record['validation_evidence_json'], true, flags: JSON_THROW_ON_ERROR);
        unset($record['containment_references_json'], $record['validation_evidence_json']);

        return $record;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findByStatus(string $status): array
    {
        $statement = $this->database->prepare('SELECT * FROM incidents WHERE status = :status');
        $statement->execute(['status' => $status]);

        return $statement->fetchAll();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findBySeverity(string $severity): array
    {
        $statement = $this->database->prepare('SELECT * FROM incidents WHERE severity = :severity');
        $statement->execute(['severity' => $severity]);

        return $statement->fetchAll();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function history(string $incidentId): array
    {
        $statement = $this->database->prepare('SELECT * FROM incident_history WHERE incident_id = :incident_id ORDER BY created_at ASC, rowid ASC');
        $statement->execute(['incident_id' => $incidentId]);

        return $statement->fetchAll();
    }

    /**
     * @param array<int, string> $allowedFrom
     * @return array{outcome: string, error: ?string}
     */
    private function appendToJsonList(string $incidentId, string $column, string $value, array $allowedFrom): array
    {
        $record = $this->fetch($incidentId);

        if ($record === null) {
            return ['outcome' => 'not_found', 'error' => sprintf('Incident "%s" is not tracked.', $incidentId)];
        }

        if (!in_array($record['status'], $allowedFrom, true)) {
            return ['outcome' => 'blocked', 'error' => sprintf('Incident "%s" must be one of [%s], not "%s".', $incidentId, implode(', ', $allowedFrom), $record['status'])];
        }

        $list = json_decode((string) $record[$column], true, flags: JSON_THROW_ON_ERROR);
        $list[] = $value;

        $statement = $this->database->prepare(sprintf('UPDATE incidents SET %s = :list, updated_at = :updated_at WHERE incident_id = :incident_id', $column));
        $statement->execute(['list' => json_encode($list, JSON_THROW_ON_ERROR), 'updated_at' => $this->nowString(), 'incident_id' => $incidentId]);

        return ['outcome' => 'attached', 'error' => null];
    }

    /**
     * @param array<int, string> $allowedFrom
     * @return array{outcome: string, error: ?string}
     */
    private function transition(string $incidentId, array $allowedFrom, string $toStatus, ?string $note = null): array
    {
        $record = $this->fetch($incidentId);

        if ($record === null) {
            return ['outcome' => 'not_found', 'error' => sprintf('Incident "%s" is not tracked.', $incidentId)];
        }

        if (!in_array($record['status'], $allowedFrom, true)) {
            return ['outcome' => 'blocked', 'error' => sprintf('Incident "%s" must be one of [%s], not "%s".', $incidentId, implode(', ', $allowedFrom), $record['status'])];
        }

        $statement = $this->database->prepare('UPDATE incidents SET status = :status, updated_at = :updated_at WHERE incident_id = :incident_id');
        $statement->execute(['status' => $toStatus, 'updated_at' => $this->nowString(), 'incident_id' => $incidentId]);

        $this->recordHistory($incidentId, $record['status'], $toStatus, $note);

        return ['outcome' => 'transitioned', 'error' => null];
    }

    private function recordHistory(string $incidentId, ?string $fromStatus, string $toStatus, ?string $note): void
    {
        $statement = $this->database->prepare(
            'INSERT INTO incident_history (history_id, incident_id, from_status, to_status, note, created_at)
             VALUES (:history_id, :incident_id, :from_status, :to_status, :note, :created_at)'
        );
        $statement->execute([
            'history_id' => 'incident_history_' . bin2hex(random_bytes(12)),
            'incident_id' => $incidentId,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'note' => $note,
            'created_at' => $this->nowString(),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetch(string $incidentId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM incidents WHERE incident_id = :incident_id');
        $statement->execute(['incident_id' => $incidentId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    private function nowString(): string
    {
        $now = $this->clock !== null ? ($this->clock)() : new DateTimeImmutable();

        return $now->format(DATE_RFC3339);
    }
}
