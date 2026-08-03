<?php

declare(strict_types=1);

namespace SquirrelForge\Resilience;

use Closure;
use DateTimeImmutable;
use PDO;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;
use Throwable;

/**
 * Coordinates restoration following a declared catastrophic failure
 * that exceeds normal recovery capabilities, per
 * 35_RESILIENCE/DISASTER-RECOVERY.md.
 *
 * This spec and `35_RESILIENCE/BUSINESS-CONTINUITY.md` each list the
 * other as an Integration Responsibility -- a genuine circular
 * reference in the specs' own headers, the same pattern already
 * resolved elsewhere in this codebase (Capacity Planner/Cost
 * Optimizer, Decision Engine/Confidence Scorer, Memory Index/Memory
 * Retrieval). This class is built first; Business Continuity's own
 * contribution ("Business continuity plans", listed under this spec's
 * own Inputs) is treated as caller-supplied evidence rather than a
 * genuine composition, since no real Business Continuity code exists
 * yet to compose.
 *
 * "Establish recovery priorities" and "Prioritize critical services"
 * are implemented literally, not invented: RECOVERY_PRIORITY_TIERS
 * reproduces the spec's own ten-tier Recovery Priorities list in
 * order (governance systems first, optimization and analytics last).
 * declare() requires the caller to classify each affected service
 * against one of these ten real tiers -- this class does not attempt
 * to infer a service's criticality itself -- and then genuinely sorts
 * the restoration schedule by that fixed order, the one part of
 * "prioritization" that has a real, spec-given answer rather than a
 * fabricated heuristic.
 *
 * Two Safety Rules here are stronger and more absolute than the
 * equivalent verification language in SqliteRollbackManager or
 * SqliteFailoverCoordinator: "must never restore compromised systems
 * without verification" and "must never resume operations before
 * validation" state an unconditional prohibition, not merely a
 * responsibility to verify when possible. Unlike those two sibling
 * classes' optional verifier (falling back to an honest
 * `not_verified` status), restoreService() here requires a verifier
 * closure outright and rejects the call without one -- a real,
 * stricter enforcement matching the spec's own stronger wording.
 *
 * The spec's own ten-stage Recovery Phases list (Declaration /
 * Assessment / Stabilization / Infrastructure recovery / Service
 * restoration / Data verification / Operational validation /
 * Monitoring / Return to normal operations / Post-incident review) is
 * collapsed to three real, distinguishable states this class can
 * actually observe: `Declared` (before any service is restored),
 * `Restoring` (some but not all declared services restored), and
 * `Resolved` (every declared service restored and verified) -- the
 * remaining phases have no mechanical signal in this scoped
 * implementation to distinguish them from one another.
 *
 * "Coordinate stakeholder communications" is intentionally not
 * implemented: no real communication channel is a dependency of this
 * component.
 *
 * Owns its own database (`Sqlite` prefix): the Audit Requirements name
 * a specific structured record shape; declare() and every
 * restoreService() call record a row unconditionally, including
 * rejected and failed restorations, per "Maintain audit continuity."
 */
final class SqliteDisasterRecovery
{
    private const DISASTER_CATEGORIES = [
        'infrastructure_loss', 'data_center_outage', 'cloud_service_failure', 'cybersecurity_incident',
        'data_corruption', 'regional_outage', 'critical_dependency_failure', 'power_failure',
        'network_isolation', 'multi_system_failure',
    ];

    private const RECOVERY_PRIORITY_TIERS = [
        'governance_systems', 'security_systems', 'identity_and_authentication', 'core_platform_services',
        'data_integrity', 'ai_driver', 'workflow_execution', 'automation', 'observability', 'optimization_and_analytics',
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
            'CREATE TABLE IF NOT EXISTS disaster_recoveries (
                disaster_recovery_id TEXT PRIMARY KEY,
                disaster_classification TEXT NOT NULL,
                recovery_phase TEXT NOT NULL,
                affected_services_json TEXT NOT NULL,
                restored_services_json TEXT NOT NULL,
                governance_status TEXT NOT NULL,
                final_outcome TEXT NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS disaster_recovery_operations (
                disaster_recovery_operation_id TEXT PRIMARY KEY,
                disaster_recovery_id TEXT NOT NULL,
                service_name TEXT,
                verification_status TEXT NOT NULL,
                final_outcome TEXT NOT NULL,
                error TEXT,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array<string, string> $affectedServices service name => recovery priority tier
     * @return array{disaster_recovery_id: ?string, restoration_schedule: array<int, string>, error: ?string}
     */
    public function declare(string $disasterClassification, array $affectedServices): array
    {
        if (!in_array($disasterClassification, self::DISASTER_CATEGORIES, true)) {
            return ['disaster_recovery_id' => null, 'restoration_schedule' => [], 'error' => sprintf('"%s" is not a recognized disaster classification.', $disasterClassification)];
        }

        if ($affectedServices === []) {
            return ['disaster_recovery_id' => null, 'restoration_schedule' => [], 'error' => 'At least one affected service is required to declare a disaster.'];
        }

        foreach ($affectedServices as $serviceName => $tier) {
            if (!in_array($tier, self::RECOVERY_PRIORITY_TIERS, true)) {
                return ['disaster_recovery_id' => null, 'restoration_schedule' => [], 'error' => sprintf('"%s" is not a recognized recovery priority tier for service "%s".', $tier, $serviceName)];
            }
        }

        $schedule = array_keys($affectedServices);
        usort($schedule, static fn(string $a, string $b): int => array_search($affectedServices[$a], self::RECOVERY_PRIORITY_TIERS, true) <=> array_search($affectedServices[$b], self::RECOVERY_PRIORITY_TIERS, true));

        $disasterRecoveryId = 'disaster_recovery_' . bin2hex(random_bytes(12));
        $now = $this->nowString();

        $statement = $this->database->prepare(
            'INSERT INTO disaster_recoveries (
                disaster_recovery_id, disaster_classification, recovery_phase, affected_services_json,
                restored_services_json, governance_status, final_outcome, created_at, updated_at
            ) VALUES (
                :id, :disaster_classification, :recovery_phase, :affected_services_json,
                :restored_services_json, :governance_status, :final_outcome, :created_at, :updated_at
            )'
        );
        $statement->execute([
            'id' => $disasterRecoveryId,
            'disaster_classification' => $disasterClassification,
            'recovery_phase' => 'Declared',
            'affected_services_json' => json_encode($schedule, JSON_THROW_ON_ERROR),
            'restored_services_json' => json_encode([], JSON_THROW_ON_ERROR),
            'governance_status' => 'ungoverned',
            'final_outcome' => 'declared',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'disaster_recovery.declared',
            new DateTimeImmutable(),
            self::class,
            ['disaster_recovery_id' => $disasterRecoveryId, 'disaster_classification' => $disasterClassification]
        ));

        return ['disaster_recovery_id' => $disasterRecoveryId, 'restoration_schedule' => $schedule, 'error' => null];
    }

    /**
     * @return array{outcome: string, recovery_phase: ?string, error: ?string}
     */
    public function restoreService(string $disasterRecoveryId, string $serviceName, Closure $action, Closure $verifier): array
    {
        $disaster = $this->fetch($disasterRecoveryId);

        if ($disaster === null) {
            return $this->recordOperation($disasterRecoveryId, $serviceName, 'not_applicable', 'not_found', sprintf('Disaster recovery "%s" is not declared.', $disasterRecoveryId));
        }

        if ($disaster['recovery_phase'] === 'Resolved') {
            return $this->recordOperation($disasterRecoveryId, $serviceName, 'not_applicable', 'already_resolved', 'This disaster recovery has already been resolved.');
        }

        $affectedServices = json_decode($disaster['affected_services_json'], true, flags: JSON_THROW_ON_ERROR);

        if (!in_array($serviceName, $affectedServices, true)) {
            return $this->recordOperation($disasterRecoveryId, $serviceName, 'not_applicable', 'unknown_service', sprintf('"%s" is not among the services declared for this disaster.', $serviceName));
        }

        try {
            $action();
        } catch (Throwable $e) {
            return $this->recordOperation($disasterRecoveryId, $serviceName, 'not_applicable', 'failed', $e->getMessage());
        }

        if ($verifier() !== true) {
            return $this->recordOperation($disasterRecoveryId, $serviceName, 'failed', 'failed_verification', sprintf('Service "%s" failed post-restoration verification; resuming operations is blocked until it passes.', $serviceName));
        }

        $restoredServices = array_unique([...json_decode($disaster['restored_services_json'], true, flags: JSON_THROW_ON_ERROR), $serviceName]);
        $recoveryPhase = count($restoredServices) === count($affectedServices) ? 'Resolved' : 'Restoring';

        $statement = $this->database->prepare(
            'UPDATE disaster_recoveries SET restored_services_json = :restored_services_json, recovery_phase = :recovery_phase, final_outcome = :final_outcome, updated_at = :updated_at WHERE disaster_recovery_id = :id'
        );
        $statement->execute([
            'restored_services_json' => json_encode(array_values($restoredServices), JSON_THROW_ON_ERROR),
            'recovery_phase' => $recoveryPhase,
            'final_outcome' => $recoveryPhase === 'Resolved' ? 'resolved' : 'restoring',
            'updated_at' => $this->nowString(),
            'id' => $disasterRecoveryId,
        ]);

        return $this->recordOperation($disasterRecoveryId, $serviceName, 'verified', 'restored', null, $recoveryPhase);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $disasterRecoveryId): ?array
    {
        $disaster = $this->fetch($disasterRecoveryId);

        if ($disaster === null) {
            return null;
        }

        $disaster['affected_services'] = json_decode($disaster['affected_services_json'], true, flags: JSON_THROW_ON_ERROR);
        $disaster['restored_services'] = json_decode($disaster['restored_services_json'], true, flags: JSON_THROW_ON_ERROR);
        unset($disaster['affected_services_json'], $disaster['restored_services_json']);

        return $disaster;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetch(string $disasterRecoveryId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM disaster_recoveries WHERE disaster_recovery_id = :id');
        $statement->execute(['id' => $disasterRecoveryId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    private function nowString(): string
    {
        $now = $this->clock !== null ? ($this->clock)() : new DateTimeImmutable();

        return $now->format(DATE_RFC3339);
    }

    /**
     * @return array{outcome: string, recovery_phase: ?string, error: ?string}
     */
    private function recordOperation(
        string $disasterRecoveryId,
        string $serviceName,
        string $verificationStatus,
        string $outcome,
        ?string $error,
        ?string $recoveryPhase = null
    ): array {
        $operationId = 'disaster_recovery_op_' . bin2hex(random_bytes(12));

        $statement = $this->database->prepare(
            'INSERT INTO disaster_recovery_operations (
                disaster_recovery_operation_id, disaster_recovery_id, service_name,
                verification_status, final_outcome, error, created_at
            ) VALUES (
                :id, :disaster_recovery_id, :service_name, :verification_status, :final_outcome, :error, :created_at
            )'
        );
        $statement->execute([
            'id' => $operationId,
            'disaster_recovery_id' => $disasterRecoveryId,
            'service_name' => $serviceName,
            'verification_status' => $verificationStatus,
            'final_outcome' => $outcome,
            'error' => $error,
            'created_at' => $this->nowString(),
        ]);

        if ($outcome === 'restored') {
            $this->events?->dispatch(new Event(
                uniqid('evt_', true),
                'disaster_recovery.service_restored',
                new DateTimeImmutable(),
                self::class,
                ['disaster_recovery_id' => $disasterRecoveryId, 'service_name' => $serviceName]
            ));
        }

        return ['outcome' => $outcome, 'recovery_phase' => $recoveryPhase, 'error' => $error];
    }
}
