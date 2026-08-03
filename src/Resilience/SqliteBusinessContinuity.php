<?php

declare(strict_types=1);

namespace SquirrelForge\Resilience;

use Closure;
use DateTimeImmutable;
use PDO;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;

/**
 * Coordinates degraded operating modes and critical-service
 * prioritization during a disruption, per
 * 35_RESILIENCE/BUSINESS-CONTINUITY.md. This is the second half of the
 * genuine circular reference between this spec and
 * `35_RESILIENCE/DISASTER-RECOVERY.md` (each lists the other as an
 * Integration Responsibility); `SqliteDisasterRecovery` was built
 * first, so its own real state is treated here as caller-supplied
 * evidence (a "disaster recovery status" input), not a live
 * composition -- the same resolution direction already documented in
 * that class's own docblock.
 *
 * "Prioritize critical services" is implemented literally, not
 * invented: CONTINUITY_PRIORITY_TIERS reproduces the spec's own
 * ten-tier Continuity Priorities list in order (governance first,
 * non-critical services last), the same real-ordering idiom
 * `SqliteDisasterRecovery::declare()` already established for its own
 * distinct Recovery Priorities list. activate() requires the caller to
 * classify each critical service against one of these real tiers and
 * genuinely sorts the resulting resource-allocation schedule by that
 * fixed order.
 *
 * The Safety Rule "must never disable critical security controls" is a
 * real, unconditional, enforced gate in activate(): a caller-supplied
 * `security_controls_active` evidence flag must be true or the
 * activation is rejected outright, regardless of operating mode --
 * "prioritize convenience over safety" has no path through this class.
 *
 * "Return to Normal Operations" names seven concrete verification
 * items before resuming full operation; resumeNormalOperations()
 * implements this as a real, deterministic evidence-gate requiring all
 * seven caller-supplied boolean flags to be true, the same gate pattern
 * already established by SqliteMemoryRetention, SqliteAiSafetyGate, and
 * SqliteFailoverCoordinator::planFailback() -- upholding "must never
 * resume full operation without verification" as an enforced
 * precondition rather than documentation.
 *
 * Owns its own database (`Sqlite` prefix): the Audit Requirements name
 * a specific structured record shape; every activate() and
 * resumeNormalOperations() call records a row unconditionally,
 * including rejected operations, per "Maintain audit integrity."
 */
final class SqliteBusinessContinuity
{
    private const OPERATING_MODES = [
        'normal_operation', 'reduced_capacity', 'essential_services_only', 'emergency_operation',
        'recovery_mode', 'disaster_operation', 'maintenance_continuity', 'transitional_operation',
    ];

    private const STRATEGIES = [
        'service_prioritization', 'graceful_degradation', 'reduced_functionality', 'resource_conservation',
        'deferred_processing', 'manual_operations', 'alternate_execution_paths', 'temporary_service_suspension',
        'controlled_capacity_reduction', 'emergency_operating_procedures',
    ];

    private const CONTINUITY_PRIORITY_TIERS = [
        'governance', 'security', 'identity_and_authentication', 'core_ai_driver_services',
        'workflow_execution', 'memory_and_knowledge', 'automation', 'observability', 'optimization', 'non_critical_services',
    ];

    private const RESUMPTION_REQUIREMENTS = [
        'recovery_verified' => 'Recovery has not been verified.',
        'platform_stability_confirmed' => 'Platform stability has not been confirmed.',
        'security_controls_validated' => 'Security controls have not been validated.',
        'service_integrity_confirmed' => 'Service integrity has not been confirmed.',
        'governance_approval_obtained' => 'Governance approval has not been obtained.',
        'resource_utilization_normalized' => 'Resource utilization has not normalized.',
        'monitoring_confirms_stable_operation' => 'Monitoring does not yet confirm stable operation.',
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
            'CREATE TABLE IF NOT EXISTS business_continuity_operations (
                continuity_operation_id TEXT PRIMARY KEY,
                operation_type TEXT NOT NULL,
                operating_mode TEXT,
                critical_services_json TEXT NOT NULL,
                resource_allocation_json TEXT NOT NULL,
                governance_status TEXT NOT NULL,
                verification_status TEXT NOT NULL,
                final_outcome TEXT NOT NULL,
                error TEXT,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array<string, string> $criticalServices service name => continuity priority tier
     * @param array{security_controls_active: bool} $evidence
     * @return array{continuity_operation_id: string, outcome: string, resource_allocation: array<int, string>, error: ?string}
     */
    public function activate(string $operatingMode, array $criticalServices, string $strategy, array $evidence): array
    {
        if (!in_array($operatingMode, self::OPERATING_MODES, true)) {
            return $this->finish('activation', $operatingMode, [], [], 'not_applicable', 'invalid_operating_mode', sprintf('"%s" is not a supported operating mode.', $operatingMode));
        }

        if (!in_array($strategy, self::STRATEGIES, true)) {
            return $this->finish('activation', $operatingMode, [], [], 'not_applicable', 'invalid_strategy', sprintf('"%s" is not a supported continuity strategy.', $strategy));
        }

        foreach ($criticalServices as $serviceName => $tier) {
            if (!in_array($tier, self::CONTINUITY_PRIORITY_TIERS, true)) {
                return $this->finish('activation', $operatingMode, [], [], 'not_applicable', 'invalid_priority_tier', sprintf('"%s" is not a recognized continuity priority tier for service "%s".', $tier, $serviceName));
            }
        }

        if (($evidence['security_controls_active'] ?? false) !== true) {
            return $this->finish('activation', $operatingMode, array_keys($criticalServices), [], 'not_applicable', 'blocked_security_controls_inactive', 'Activation rejected: critical security controls are not confirmed active.');
        }

        $schedule = array_keys($criticalServices);
        usort($schedule, static fn(string $a, string $b): int => array_search($criticalServices[$a], self::CONTINUITY_PRIORITY_TIERS, true) <=> array_search($criticalServices[$b], self::CONTINUITY_PRIORITY_TIERS, true));

        return $this->finish('activation', $operatingMode, array_keys($criticalServices), $schedule, 'not_applicable', 'activated', null);
    }

    /**
     * @param array{recovery_verified?: bool, platform_stability_confirmed?: bool, security_controls_validated?: bool, service_integrity_confirmed?: bool, governance_approval_obtained?: bool, resource_utilization_normalized?: bool, monitoring_confirms_stable_operation?: bool} $evidence
     * @return array{continuity_operation_id: string, outcome: string, error: ?string}
     */
    public function resumeNormalOperations(array $evidence): array
    {
        foreach (self::RESUMPTION_REQUIREMENTS as $field => $reason) {
            if (($evidence[$field] ?? false) !== true) {
                $result = $this->finish('resumption', 'normal_operation', [], [], 'not_verified', 'blocked', $reason);

                return ['continuity_operation_id' => $result['continuity_operation_id'], 'outcome' => $result['outcome'], 'error' => $result['error']];
            }
        }

        $result = $this->finish('resumption', 'normal_operation', [], [], 'verified', 'resumed', null);

        return ['continuity_operation_id' => $result['continuity_operation_id'], 'outcome' => $result['outcome'], 'error' => $result['error']];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $continuityOperationId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM business_continuity_operations WHERE continuity_operation_id = :id');
        $statement->execute(['id' => $continuityOperationId]);
        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        $row['critical_services'] = json_decode($row['critical_services_json'], true, flags: JSON_THROW_ON_ERROR);
        $row['resource_allocation'] = json_decode($row['resource_allocation_json'], true, flags: JSON_THROW_ON_ERROR);
        unset($row['critical_services_json'], $row['resource_allocation_json']);

        return $row;
    }

    /**
     * @param array<int, string> $criticalServices
     * @param array<int, string> $resourceAllocation
     * @return array{continuity_operation_id: string, outcome: string, resource_allocation: array<int, string>, error: ?string}
     */
    private function finish(
        string $operationType,
        string $operatingMode,
        array $criticalServices,
        array $resourceAllocation,
        string $verificationStatus,
        string $outcome,
        ?string $error
    ): array {
        $operationId = 'continuity_op_' . bin2hex(random_bytes(12));
        $now = $this->clock !== null ? ($this->clock)() : new DateTimeImmutable();

        $statement = $this->database->prepare(
            'INSERT INTO business_continuity_operations (
                continuity_operation_id, operation_type, operating_mode, critical_services_json,
                resource_allocation_json, governance_status, verification_status, final_outcome, error, created_at
            ) VALUES (
                :id, :operation_type, :operating_mode, :critical_services_json,
                :resource_allocation_json, :governance_status, :verification_status, :final_outcome, :error, :created_at
            )'
        );
        $statement->execute([
            'id' => $operationId,
            'operation_type' => $operationType,
            'operating_mode' => $operatingMode,
            'critical_services_json' => json_encode($criticalServices, JSON_THROW_ON_ERROR),
            'resource_allocation_json' => json_encode($resourceAllocation, JSON_THROW_ON_ERROR),
            'governance_status' => 'ungoverned',
            'verification_status' => $verificationStatus,
            'final_outcome' => $outcome,
            'error' => $error,
            'created_at' => $now->format(DATE_RFC3339),
        ]);

        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'business_continuity.' . $outcome,
            new DateTimeImmutable(),
            self::class,
            ['continuity_operation_id' => $operationId, 'operation_type' => $operationType, 'outcome' => $outcome]
        ));

        return ['continuity_operation_id' => $operationId, 'outcome' => $outcome, 'resource_allocation' => $resourceAllocation, 'error' => $error];
    }
}
