<?php

declare(strict_types=1);

namespace SquirrelForge\Security;

use Closure;
use DateTimeImmutable;
use PDO;

/**
 * Provides continuous observability of Security Layer activity, per
 * 24_SECURITY/SECURITY-MONITOR.md: "observes, aggregates, and reports.
 * It does not execute security operations, define or evaluate policy,
 * independently verify compliance, remediate vulnerabilities, or
 * override governance decisions."
 *
 * This spec and `24_SECURITY/SECURITY-MANAGER.md` list each other as
 * dependencies -- a genuine circular reference in the specs' own
 * headers, resolved the same way prior cycles in this codebase were
 * (Capacity Planner/Cost Optimizer, Decision Engine/Confidence Scorer,
 * Memory Index/Memory Retrieval, Disaster Recovery/Business
 * Continuity): this class is built first, and Security Manager's own
 * contribution (alert/report routing) is left for that class to
 * consume from this one, not composed here. `24_SECURITY/
 * THREAT-DETECTOR.md` and `24_SECURITY/INCIDENT-MANAGER.md` are not
 * yet real either, so their metrics are accepted as caller-supplied
 * evidence, the same "Depends On vs Inputs" treatment this codebase
 * applies consistently.
 *
 * "Aggregate compliance status reported by Compliance Manager" and
 * "Aggregate vulnerability status reported by Vulnerability Manager"
 * are genuine composition, not caller-supplied evidence: runCycle()
 * calls the real `SqliteComplianceManager::listRequirements()`/
 * `latestStatus()` (the first retrofitted onto that class for exactly
 * this purpose, matching this codebase's established retrofit
 * precedent) and `SqliteVulnerabilityManager::findBySeverity()` to
 * compute real counts -- never a caller-declared number standing in
 * for a determination this spec explicitly reserves to those two
 * owning components ("must never independently determine compliance
 * status or vulnerability severity").
 *
 * None of the remaining Monitored Metrics (identity/authentication/
 * authorization/encryption success rates, secrets access frequency,
 * policy evaluation results, threat frequency, incident response time,
 * governance approval rate) have a real metrics-exposing query surface
 * on their owning components in this codebase, so runCycle() accepts
 * them as a caller-supplied `metrics` map compared against a
 * caller-supplied `thresholds` map -- a real, mechanical rule
 * evaluation over caller-declared data (any metric exceeding its
 * threshold generates an alert), not a fabricated per-condition
 * detector for each of the twelve Alert Conditions this spec names.
 *
 * Overall security posture is derived deterministically from what was
 * actually observed this cycle: `critical` when any requirement's
 * latest status is Non-Compliant or any critical-severity
 * vulnerability remains unresolved, `at_risk` when any other alert
 * fired, `healthy` otherwise.
 *
 * Owns its own database (`Sqlite` prefix): the Audit Requirements name
 * a specific structured record shape every runCycle() call records
 * unconditionally.
 */
final class SqliteSecurityMonitor
{
    private const UNRESOLVED_VULNERABILITY_STATES = [
        'Open', 'Triaged', 'Remediation Assigned', 'In Progress', 'Validation Pending', 'Verified', 'Reopened',
    ];

    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly ?SqliteComplianceManager $compliance = null,
        private readonly ?SqliteVulnerabilityManager $vulnerabilities = null,
        private readonly ?Closure $clock = null
    ) {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS monitoring_cycles (
                monitoring_id TEXT PRIMARY KEY,
                components_monitored_json TEXT NOT NULL,
                performance_metrics_json TEXT NOT NULL,
                alerts_json TEXT NOT NULL,
                compliance_status TEXT NOT NULL,
                security_posture TEXT NOT NULL,
                outcome TEXT NOT NULL,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array{components_monitored?: array<int, string>, metrics?: array<string, int|float>, thresholds?: array<string, int|float>} $context
     * @return array{monitoring_id: string, alerts: array<int, string>, compliance_status: string, vulnerability_summary: array{critical_unresolved: int}, security_posture: string, outcome: string}
     */
    public function runCycle(array $context = []): array
    {
        $componentsMonitored = $context['components_monitored'] ?? [];
        $metrics = $context['metrics'] ?? [];
        $thresholds = $context['thresholds'] ?? [];

        $alerts = [];

        foreach ($thresholds as $metricName => $threshold) {
            if (isset($metrics[$metricName]) && $metrics[$metricName] > $threshold) {
                $alerts[] = sprintf('%s (%s) exceeded its threshold (%s).', $metricName, $metrics[$metricName], $threshold);
            }
        }

        $complianceStatus = $this->aggregateCompliance();

        if ($complianceStatus === 'Non-Compliant') {
            $alerts[] = 'Compliance status has regressed to Non-Compliant.';
        }

        $vulnerabilitySummary = $this->aggregateVulnerabilities();

        if ($vulnerabilitySummary['critical_unresolved'] > 0) {
            $alerts[] = sprintf('%d critical vulnerability(ies) remain unremediated.', $vulnerabilitySummary['critical_unresolved']);
        }

        $posture = match (true) {
            $complianceStatus === 'Non-Compliant' || $vulnerabilitySummary['critical_unresolved'] > 0 => 'critical',
            $alerts !== [] => 'at_risk',
            default => 'healthy',
        };

        return $this->persist($componentsMonitored, $metrics, $alerts, $complianceStatus, $vulnerabilitySummary, $posture);
    }

    private function aggregateCompliance(): string
    {
        if ($this->compliance === null) {
            return 'not_configured';
        }

        $requirements = $this->compliance->listRequirements();

        if ($requirements === []) {
            return 'no_requirements_registered';
        }

        $statuses = [];

        foreach ($requirements as $requirement) {
            $status = $this->compliance->latestStatus($requirement['requirement_id']);
            $statuses[] = $status ?? 'Under Review';
        }

        if (in_array('Non-Compliant', $statuses, true)) {
            return 'Non-Compliant';
        }

        if (in_array('Partially Compliant', $statuses, true)) {
            return 'Partially Compliant';
        }

        if (in_array('Under Review', $statuses, true)) {
            return 'Under Review';
        }

        return 'Compliant';
    }

    /**
     * @return array{critical_unresolved: int}
     */
    private function aggregateVulnerabilities(): array
    {
        if ($this->vulnerabilities === null) {
            return ['critical_unresolved' => 0];
        }

        $critical = $this->vulnerabilities->findBySeverity('critical');
        $unresolved = array_filter($critical, static fn(array $v): bool => in_array($v['status'], self::UNRESOLVED_VULNERABILITY_STATES, true));

        return ['critical_unresolved' => count($unresolved)];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $monitoringId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM monitoring_cycles WHERE monitoring_id = :id');
        $statement->execute(['id' => $monitoringId]);
        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        $row['components_monitored'] = json_decode((string) $row['components_monitored_json'], true, flags: JSON_THROW_ON_ERROR);
        $row['performance_metrics'] = json_decode((string) $row['performance_metrics_json'], true, flags: JSON_THROW_ON_ERROR);
        $row['alerts'] = json_decode((string) $row['alerts_json'], true, flags: JSON_THROW_ON_ERROR);
        unset($row['components_monitored_json'], $row['performance_metrics_json'], $row['alerts_json']);

        return $row;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $limit = 50): array
    {
        $statement = $this->database->prepare('SELECT * FROM monitoring_cycles ORDER BY rowid DESC LIMIT :limit');
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return array_map(
            function (array $row): array {
                return $this->get($row['monitoring_id']);
            },
            $statement->fetchAll()
        );
    }

    /**
     * @param array<int, string> $componentsMonitored
     * @param array<string, int|float> $metrics
     * @param array<int, string> $alerts
     * @param array{critical_unresolved: int} $vulnerabilitySummary
     * @return array{monitoring_id: string, alerts: array<int, string>, compliance_status: string, vulnerability_summary: array{critical_unresolved: int}, security_posture: string, outcome: string}
     */
    private function persist(
        array $componentsMonitored,
        array $metrics,
        array $alerts,
        string $complianceStatus,
        array $vulnerabilitySummary,
        string $posture
    ): array {
        $monitoringId = 'security_monitor_' . bin2hex(random_bytes(12));
        $now = $this->clock !== null ? ($this->clock)() : new DateTimeImmutable();

        $statement = $this->database->prepare(
            'INSERT INTO monitoring_cycles (
                monitoring_id, components_monitored_json, performance_metrics_json, alerts_json,
                compliance_status, security_posture, outcome, created_at
            ) VALUES (
                :id, :components_monitored_json, :performance_metrics_json, :alerts_json,
                :compliance_status, :security_posture, :outcome, :created_at
            )'
        );
        $statement->execute([
            'id' => $monitoringId,
            'components_monitored_json' => json_encode($componentsMonitored, JSON_THROW_ON_ERROR),
            'performance_metrics_json' => json_encode($metrics, JSON_THROW_ON_ERROR),
            'alerts_json' => json_encode($alerts, JSON_THROW_ON_ERROR),
            'compliance_status' => $complianceStatus,
            'security_posture' => $posture,
            'outcome' => 'monitored',
            'created_at' => $now->format(DATE_RFC3339),
        ]);

        return [
            'monitoring_id' => $monitoringId,
            'alerts' => $alerts,
            'compliance_status' => $complianceStatus,
            'vulnerability_summary' => $vulnerabilitySummary,
            'security_posture' => $posture,
            'outcome' => 'monitored',
        ];
    }
}
