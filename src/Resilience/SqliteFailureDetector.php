<?php

declare(strict_types=1);

namespace SquirrelForge\Resilience;

use DateTimeImmutable;
use PDO;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;

/**
 * Detects, classifies, and records operational failures, per
 * 35_RESILIENCE/FAILURE-DETECTOR.md.
 *
 * This is a deliberately scoped subset of that spec: it consumes a single
 * caller-reported signal per call rather than continuously monitoring
 * health reports/telemetry/traces (Observability's telemetry/tracing
 * pieces don't exist yet), and it does not itself notify a Resilience
 * Manager, Alert Manager, or Diagnostics Engine -- all three are real now,
 * but only Resilience Manager has a concrete contract shaped to consume
 * this class's output: `SqliteResilienceManager::coordinate()` takes the
 * `detection_ref`-bearing array `detect()` returns as its own
 * `$failureDetection` argument, so "Notify the Resilience Manager" is
 * fulfilled at the caller-orchestration level (the caller passes one
 * result into the other), the same "consume, don't compute" composition
 * used throughout this codebase, not a direct call from inside this
 * class. Alert Manager's `create()` and Diagnostics Engine's trace/metric
 * methods are real but generic -- neither names a detection-shaped
 * contract to build against, so wiring either in here would mean
 * inventing one. It also never triggers recovery itself, which matches
 * the spec's own safety rule ("must never ... trigger recovery
 * directly"), so that omission is not a scoping gap. Governance status is
 * recorded as the fixed constant `ungoverned`: `23_GOVERNANCE/POLICY-ENGINE.md`
 * is real now as `SqlitePolicyEngine`, but this spec names no per-signal
 * policy category or context shape to evaluate against.
 *
 * Severity classification is conservative by design, per the spec's
 * failure-handling rule to "return conservative failure status when
 * required": an unrecognized signal, or an explicitly supplied severity
 * that isn't one of the six known levels, resolves to `critical` rather
 * than being downgraded or dropped.
 */
final class SqliteFailureDetector
{
    private const SEVERITIES = ['informational', 'notice', 'warning', 'error', 'critical', 'emergency'];

    private const DEFAULT_SEVERITY_BY_SIGNAL = [
        'security_event' => 'critical',
        'missing_heartbeat' => 'critical',
        'dependency_failure' => 'error',
        'resource_exhaustion' => 'error',
        'trace_failure' => 'warning',
        'latency_spike' => 'warning',
        'timeout_rate' => 'warning',
        'error_rate' => 'warning',
        'health_state_change' => 'notice',
        'alert_activity' => 'notice',
    ];

    private const RECOVERY_URGENCY_BY_SEVERITY = [
        'informational' => 'none',
        'notice' => 'none',
        'warning' => 'monitor',
        'error' => 'prompt',
        'critical' => 'immediate',
        'emergency' => 'immediate',
    ];

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
            'CREATE TABLE IF NOT EXISTS failure_detections (
                detection_ref TEXT PRIMARY KEY,
                category TEXT NOT NULL,
                signal TEXT NOT NULL,
                severity TEXT NOT NULL,
                governance_status TEXT NOT NULL,
                impact_json TEXT NOT NULL,
                context_json TEXT NOT NULL,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array<string, mixed> $context Free-form detection context. The
     *                                       keys `affected_components` and
     *                                       `affected_workflows`, if present,
     *                                       feed directly into the impact
     *                                       assessment.
     * @return array{detection_ref: string, category: string, signal: string, severity: string, governance_status: string, impact: array<string, mixed>, created_at: string}
     */
    public function detect(string $category, string $signal, array $context = [], ?string $severity = null): array
    {
        $resolvedSeverity = $this->resolveSeverity($signal, $severity);
        $impact = $this->assessImpact($resolvedSeverity, $context);

        $detectionRef = 'failure_detection_' . bin2hex(random_bytes(12));
        $createdAt = gmdate(DATE_RFC3339);
        $governanceStatus = 'ungoverned';

        $statement = $this->database->prepare(
            'INSERT INTO failure_detections (
                detection_ref, category, signal, severity, governance_status,
                impact_json, context_json, created_at
            ) VALUES (
                :detection_ref, :category, :signal, :severity, :governance_status,
                :impact_json, :context_json, :created_at
            )'
        );
        $statement->execute([
            'detection_ref' => $detectionRef,
            'category' => $category,
            'signal' => $signal,
            'severity' => $resolvedSeverity,
            'governance_status' => $governanceStatus,
            'impact_json' => json_encode($impact, JSON_THROW_ON_ERROR),
            'context_json' => json_encode($context, JSON_THROW_ON_ERROR),
            'created_at' => $createdAt,
        ]);

        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'failure.detected',
            new DateTimeImmutable(),
            self::class,
            ['detection_ref' => $detectionRef, 'category' => $category, 'severity' => $resolvedSeverity]
        ));

        return [
            'detection_ref' => $detectionRef,
            'category' => $category,
            'signal' => $signal,
            'severity' => $resolvedSeverity,
            'governance_status' => $governanceStatus,
            'impact' => $impact,
            'created_at' => $createdAt,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function detection(string $detectionRef): ?array
    {
        $statement = $this->database->prepare(
            'SELECT * FROM failure_detections WHERE detection_ref = :detection_ref'
        );
        $statement->execute(['detection_ref' => $detectionRef]);
        $row = $statement->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $limit = 50): array
    {
        $statement = $this->database->prepare(
            'SELECT * FROM failure_detections ORDER BY rowid DESC LIMIT :limit'
        );
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return array_map($this->hydrate(...), $statement->fetchAll());
    }

    private function resolveSeverity(string $signal, ?string $severity): string
    {
        if ($severity !== null) {
            return in_array($severity, self::SEVERITIES, true) ? $severity : 'critical';
        }

        return self::DEFAULT_SEVERITY_BY_SIGNAL[$signal] ?? 'critical';
    }

    /**
     * @param array<string, mixed> $context
     * @return array{affected_components: array<int, string>, affected_workflows: array<int, string>, recovery_urgency: string, service_availability_at_risk: bool}
     */
    private function assessImpact(string $severity, array $context): array
    {
        return [
            'affected_components' => $context['affected_components'] ?? [],
            'affected_workflows' => $context['affected_workflows'] ?? [],
            'recovery_urgency' => self::RECOVERY_URGENCY_BY_SEVERITY[$severity],
            'service_availability_at_risk' => in_array($severity, ['critical', 'emergency'], true),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        return [
            'detection_ref' => $row['detection_ref'],
            'category' => $row['category'],
            'signal' => $row['signal'],
            'severity' => $row['severity'],
            'governance_status' => $row['governance_status'],
            'impact' => json_decode((string) $row['impact_json'], true, flags: JSON_THROW_ON_ERROR),
            'context' => json_decode((string) $row['context_json'], true, flags: JSON_THROW_ON_ERROR),
            'created_at' => $row['created_at'],
        ];
    }
}
