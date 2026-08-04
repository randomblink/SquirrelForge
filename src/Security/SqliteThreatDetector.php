<?php

declare(strict_types=1);

namespace SquirrelForge\Security;

use Closure;
use DateTimeImmutable;
use PDO;

/**
 * Detects, correlates, and classifies security threats, per
 * 24_SECURITY/THREAT-DETECTOR.md: "detects, correlates evidence,
 * classifies threat severity, and reports threat assessments only."
 *
 * The Safety Rule "must never... classify incidents or coordinate
 * incident response" is upheld literally: detect() never invents an
 * incident category on a threat's behalf. When severity is `high` or
 * `critical` and a `SqliteIncidentManager` is injected, it only ever
 * calls receiveNotification() -- the intake step that leaves category
 * unset -- never classify(), which remains Incident Manager's own,
 * independent decision per "The Incident Manager classifies the
 * incident after reviewing threat evidence... It is not the same as
 * incident classification." This is the same boundary-respecting
 * optional-composition pattern SqliteVulnerabilityManager::acceptRisk()
 * already established with Security Governance.
 *
 * Threat Category, Severity Level, and Detection Method are each closed
 * enumerations taken directly from the spec's own lists; detect()
 * rejects anything outside them rather than silently accepting free text.
 *
 * Owns two tables: `threats` holds one current-state row per threat
 * assessment (with its accumulated evidence and correlation
 * references), and `threat_correlations` is the append-only log of
 * every correlate() call, matching "Correlate related security events."
 */
final class SqliteThreatDetector
{
    private const CATEGORIES = [
        'unauthorized_access_attempts', 'privilege_escalation', 'credential_misuse',
        'data_exfiltration', 'malicious_workflows', 'suspicious_integrations',
        'policy_violations', 'service_abuse', 'denial_of_service_activity',
        'insider_threats', 'unknown_anomalies',
    ];

    private const SEVERITIES = ['informational', 'low', 'medium', 'high', 'critical'];

    private const DETECTION_METHODS = [
        'rule_based_detection', 'behavioral_analysis', 'pattern_correlation',
        'anomaly_detection', 'threshold_monitoring', 'event_sequencing',
        'historical_comparison', 'threat_intelligence_correlation',
    ];

    private const INCIDENT_WARRANTED_SEVERITIES = ['high', 'critical'];

    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly ?SqliteIncidentManager $incidentManager = null,
        private readonly ?Closure $clock = null
    ) {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS threats (
                threat_id TEXT PRIMARY KEY,
                event_source TEXT NOT NULL,
                category TEXT NOT NULL,
                severity TEXT NOT NULL,
                detection_method TEXT NOT NULL,
                evidence_json TEXT NOT NULL,
                incident_intake_reference TEXT,
                final_outcome TEXT NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS threat_correlations (
                correlation_id TEXT PRIMARY KEY,
                threat_id TEXT NOT NULL,
                related_threat_id TEXT NOT NULL,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array<int, string> $evidence
     * @return array{outcome: string, threat_id: ?string, incident_intake_reference: ?string, error: ?string}
     */
    public function detect(string $eventSource, string $category, string $severity, string $detectionMethod, array $evidence = []): array
    {
        if ($eventSource === '') {
            return ['outcome' => 'invalid_event_source', 'threat_id' => null, 'incident_intake_reference' => null, 'error' => 'A threat detection requires a non-empty event source.'];
        }

        if (!in_array($category, self::CATEGORIES, true)) {
            return ['outcome' => 'invalid_category', 'threat_id' => null, 'incident_intake_reference' => null, 'error' => sprintf('"%s" is not a recognized threat category.', $category)];
        }

        if (!in_array($severity, self::SEVERITIES, true)) {
            return ['outcome' => 'invalid_severity', 'threat_id' => null, 'incident_intake_reference' => null, 'error' => sprintf('"%s" is not a recognized severity level.', $severity)];
        }

        if (!in_array($detectionMethod, self::DETECTION_METHODS, true)) {
            return ['outcome' => 'invalid_detection_method', 'threat_id' => null, 'incident_intake_reference' => null, 'error' => sprintf('"%s" is not a recognized detection method.', $detectionMethod)];
        }

        $threatId = 'threat_' . bin2hex(random_bytes(12));
        $now = $this->nowString();
        $incidentIntakeReference = null;
        $finalOutcome = 'monitored';

        if (in_array($severity, self::INCIDENT_WARRANTED_SEVERITIES, true) && $this->incidentManager !== null) {
            $notification = $this->incidentManager->receiveNotification([
                'source' => 'threat_detector',
                'threat_assessment_reference' => $threatId,
                'evidence' => $evidence,
            ]);

            if ($notification['outcome'] === 'received') {
                $incidentIntakeReference = $notification['incident_id'];
                $finalOutcome = 'handed_to_incident_manager';
            }
        }

        $statement = $this->database->prepare(
            'INSERT INTO threats (
                threat_id, event_source, category, severity, detection_method,
                evidence_json, incident_intake_reference, final_outcome, created_at, updated_at
            ) VALUES (
                :threat_id, :event_source, :category, :severity, :detection_method,
                :evidence_json, :incident_intake_reference, :final_outcome, :created_at, :updated_at
            )'
        );
        $statement->execute([
            'threat_id' => $threatId,
            'event_source' => $eventSource,
            'category' => $category,
            'severity' => $severity,
            'detection_method' => $detectionMethod,
            'evidence_json' => json_encode(array_values($evidence), JSON_THROW_ON_ERROR),
            'incident_intake_reference' => $incidentIntakeReference,
            'final_outcome' => $finalOutcome,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return ['outcome' => 'detected', 'threat_id' => $threatId, 'incident_intake_reference' => $incidentIntakeReference, 'error' => null];
    }

    /**
     * @return array{outcome: string, error: ?string}
     */
    public function correlate(string $threatId, string $relatedThreatId): array
    {
        if ($this->fetch($threatId) === null) {
            return ['outcome' => 'not_found', 'error' => sprintf('Threat "%s" is not tracked.', $threatId)];
        }

        if ($this->fetch($relatedThreatId) === null) {
            return ['outcome' => 'not_found', 'error' => sprintf('Related threat "%s" is not tracked.', $relatedThreatId)];
        }

        $statement = $this->database->prepare(
            'INSERT INTO threat_correlations (correlation_id, threat_id, related_threat_id, created_at)
             VALUES (:correlation_id, :threat_id, :related_threat_id, :created_at)'
        );
        $statement->execute([
            'correlation_id' => 'threat_corr_' . bin2hex(random_bytes(12)),
            'threat_id' => $threatId,
            'related_threat_id' => $relatedThreatId,
            'created_at' => $this->nowString(),
        ]);

        return ['outcome' => 'correlated', 'error' => null];
    }

    /**
     * @return array{outcome: string, error: ?string}
     */
    public function addEvidence(string $threatId, string $evidenceReference): array
    {
        $record = $this->fetch($threatId);

        if ($record === null) {
            return ['outcome' => 'not_found', 'error' => sprintf('Threat "%s" is not tracked.', $threatId)];
        }

        $evidence = json_decode((string) $record['evidence_json'], true, flags: JSON_THROW_ON_ERROR);
        $evidence[] = $evidenceReference;

        $statement = $this->database->prepare('UPDATE threats SET evidence_json = :evidence_json, updated_at = :updated_at WHERE threat_id = :threat_id');
        $statement->execute(['evidence_json' => json_encode($evidence, JSON_THROW_ON_ERROR), 'updated_at' => $this->nowString(), 'threat_id' => $threatId]);

        return ['outcome' => 'evidence_added', 'error' => null];
    }

    /**
     * @return array<int, string>
     */
    public function correlations(string $threatId): array
    {
        $statement = $this->database->prepare('SELECT related_threat_id FROM threat_correlations WHERE threat_id = :threat_id ORDER BY created_at ASC, rowid ASC');
        $statement->execute(['threat_id' => $threatId]);

        return array_column($statement->fetchAll(), 'related_threat_id');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $threatId): ?array
    {
        $record = $this->fetch($threatId);

        if ($record === null) {
            return null;
        }

        $record['evidence'] = json_decode((string) $record['evidence_json'], true, flags: JSON_THROW_ON_ERROR);
        unset($record['evidence_json']);

        return $record;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findBySeverity(string $severity): array
    {
        $statement = $this->database->prepare('SELECT * FROM threats WHERE severity = :severity');
        $statement->execute(['severity' => $severity]);

        return $statement->fetchAll();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findByCategory(string $category): array
    {
        $statement = $this->database->prepare('SELECT * FROM threats WHERE category = :category');
        $statement->execute(['category' => $category]);

        return $statement->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetch(string $threatId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM threats WHERE threat_id = :threat_id');
        $statement->execute(['threat_id' => $threatId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    private function nowString(): string
    {
        $now = $this->clock !== null ? ($this->clock)() : new DateTimeImmutable();

        return $now->format(DATE_RFC3339);
    }
}
