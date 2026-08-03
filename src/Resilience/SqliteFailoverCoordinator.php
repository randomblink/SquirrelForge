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
 * Executes an already-approved controlled transition from a failed or
 * degraded service to a verified healthy standby resource, per
 * 35_RESILIENCE/FAILOVER-COORDINATOR.md.
 *
 * "The Failover Coordinator does not determine when failover is
 * required. It executes approved failover procedures after
 * authorization by the Resilience Manager or Recovery Manager" is
 * upheld literally: `authorized` is caller-supplied evidence, the same
 * "consume, don't compute" pattern SqliteRollbackManager already
 * established for its own equivalent authorization field.
 *
 * "Select appropriate standby resources" and "Verify synchronization
 * and readiness" are genuine composition, not caller-supplied evidence:
 * this class calls the real
 * `SqliteRedundancyManager::provideVerifiedResource()` built for
 * exactly this purpose ("provides the Failover Coordinator and Recovery
 * Manager with verified redundant resources when required"). Because
 * that method already only ever returns a resource that is `Ready` and
 * `synchronized`, the Safety Rule "must never activate unverified
 * standby resources" is a structural guarantee inherited from Redundancy
 * Manager, not a separate check reimplemented here.
 *
 * Of the remaining Integration Responsibilities (Resilience Manager,
 * Recovery Manager, Self-Healing Engine, Rollback Manager,
 * Infrastructure services, Observability Layer, Security Layer,
 * Resilience Governance), none has a real mechanism to genuinely
 * execute an actual traffic/service cutover, so -- matching
 * SqliteRollbackManager's and SqliteRecoveryManager's own established
 * "direct" strategy precedent -- the concrete transition is a
 * caller-supplied `action` closure, and post-failover verification is a
 * caller-supplied `verifier` closure whose result is authoritative when
 * present.
 *
 * "Support failback planning" is implemented as planFailback(): a real,
 * deterministic evidence-gate recommendation (approved only when the
 * original resource is repaired, resynchronized, risk-accepted, and
 * governance-approved), the same gate pattern already established by
 * SqliteMemoryRetention and SqliteAiSafetyGate. It never executes a
 * failback -- the spec's own Failback Planning section lists only
 * assessment, verification, and scheduling steps, and "Failback
 * execution planning" names planning the execution, not performing it.
 *
 * This spec's Failover States list (Requested/Authorized/Preparing/
 * Synchronizing/Transitioning/Verifying/Operational/Failed/Escalated/
 * Closed) is collapsed to its two real terminal outcomes,
 * `Operational` and `Failed`, matching the same synchronous scoping
 * RetryManager, SqliteRecoveryManager, and SqliteRollbackManager's
 * docblocks already document for the same reason: no queueing or
 * asynchronous state exists in this codebase to populate the
 * intermediate states with anything real. Governance status is the
 * fixed constant `ungoverned`, matching SqliteRecoveryManager's and
 * SqliteRollbackManager's own precedent, since Resilience Governance
 * has no real code yet.
 *
 * Owns its own database (`Sqlite` prefix): the Audit Requirements name
 * a specific structured record shape every failover() call records
 * unconditionally, including rejected and failed operations, per
 * "Maintain audit continuity."
 */
final class SqliteFailoverCoordinator
{
    private const TYPES = [
        'service', 'application', 'database', 'infrastructure', 'network',
        'workflow', 'agent', 'geographic', 'cloud', 'hybrid',
    ];

    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly ?SqliteRedundancyManager $redundancyManager = null,
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
            'CREATE TABLE IF NOT EXISTS failover_operations (
                failover_operation_id TEXT PRIMARY KEY,
                affected_service TEXT NOT NULL,
                standby_resource TEXT,
                failover_type TEXT NOT NULL,
                transition_status TEXT NOT NULL,
                verification_status TEXT NOT NULL,
                governance_status TEXT NOT NULL,
                final_outcome TEXT NOT NULL,
                error TEXT,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array{affected_service: string, failover_type: string, authorized: bool, action?: Closure, verifier?: ?Closure} $request
     * @return array{failover_operation_id: string, state: string, standby_resource: ?string, verification_status: string, error: ?string}
     */
    public function failover(array $request): array
    {
        $missingField = $this->firstMissingField($request);

        if ($missingField !== null) {
            return $this->finish($request['affected_service'] ?? 'unknown', $request['failover_type'] ?? 'unknown', null, 'Failed', 'not_applicable', sprintf('Required field "%s" is missing from the failover request.', $missingField));
        }

        if (!in_array($request['failover_type'], self::TYPES, true)) {
            return $this->finish($request['affected_service'], $request['failover_type'], null, 'Failed', 'not_applicable', sprintf('"%s" is not a supported failover type.', $request['failover_type']));
        }

        if ($request['authorized'] !== true) {
            return $this->finish($request['affected_service'], $request['failover_type'], null, 'Failed', 'not_applicable', 'Failover rejected: no authorization was supplied for this operation.');
        }

        if ($this->redundancyManager === null) {
            return $this->finish($request['affected_service'], $request['failover_type'], null, 'Failed', 'not_applicable', 'Redundancy Manager is not configured; no standby resource can be selected.');
        }

        $standby = $this->redundancyManager->provideVerifiedResource($request['failover_type']);

        if (!$standby['found']) {
            return $this->finish($request['affected_service'], $request['failover_type'], null, 'Failed', 'not_applicable', $standby['error']);
        }

        if (!($request['action'] ?? null) instanceof Closure) {
            return $this->finish($request['affected_service'], $request['failover_type'], $standby['resource_id'], 'Failed', 'not_applicable', 'The failover request requires an "action" closure to perform the actual transition.');
        }

        try {
            ($request['action'])($standby['resource_id']);
        } catch (Throwable $e) {
            return $this->finish($request['affected_service'], $request['failover_type'], $standby['resource_id'], 'Failed', 'not_applicable', $e->getMessage());
        }

        $verificationStatus = $this->resolveVerification($request);

        if ($verificationStatus === 'failed') {
            return $this->finish($request['affected_service'], $request['failover_type'], $standby['resource_id'], 'Failed', 'failed', 'The transitioned service failed post-failover verification.');
        }

        return $this->finish($request['affected_service'], $request['failover_type'], $standby['resource_id'], 'Operational', $verificationStatus, null);
    }

    /**
     * @param array{original_resource_repaired: bool, resynchronized: bool, risk_accepted: bool, governance_approved: bool} $evidence
     * @return array{outcome: string, reason: ?string}
     */
    public function planFailback(array $evidence): array
    {
        $requirements = [
            'original_resource_repaired' => 'The original resource has not been confirmed repaired.',
            'resynchronized' => 'The original resource has not been resynchronized.',
            'risk_accepted' => 'Failback risk has not been assessed and accepted.',
            'governance_approved' => 'Failback has not received governance approval.',
        ];

        foreach ($requirements as $field => $reason) {
            if (($evidence[$field] ?? false) !== true) {
                return ['outcome' => 'deferred', 'reason' => $reason];
            }
        }

        return ['outcome' => 'approved', 'reason' => null];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $failoverOperationId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM failover_operations WHERE failover_operation_id = :id');
        $statement->execute(['id' => $failoverOperationId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $request
     */
    private function firstMissingField(array $request): ?string
    {
        foreach (['affected_service', 'failover_type', 'authorized'] as $field) {
            if (!array_key_exists($field, $request)) {
                return $field;
            }
        }

        return null;
    }

    /**
     * @param array{verifier?: ?Closure} $request
     */
    private function resolveVerification(array $request): string
    {
        $verifier = $request['verifier'] ?? null;

        if (!($verifier instanceof Closure)) {
            return 'not_verified';
        }

        return $verifier() === true ? 'verified' : 'failed';
    }

    /**
     * @return array{failover_operation_id: string, state: string, standby_resource: ?string, verification_status: string, error: ?string}
     */
    private function finish(
        string $affectedService,
        string $failoverType,
        ?string $standbyResource,
        string $state,
        string $verificationStatus,
        ?string $error
    ): array {
        $operationId = 'failover_op_' . bin2hex(random_bytes(12));
        $now = $this->clock !== null ? ($this->clock)() : new DateTimeImmutable();
        $finalOutcome = $state === 'Operational' ? 'transitioned' : 'not_transitioned';

        $statement = $this->database->prepare(
            'INSERT INTO failover_operations (
                failover_operation_id, affected_service, standby_resource, failover_type, transition_status,
                verification_status, governance_status, final_outcome, error, created_at
            ) VALUES (
                :id, :affected_service, :standby_resource, :failover_type, :transition_status,
                :verification_status, :governance_status, :final_outcome, :error, :created_at
            )'
        );
        $statement->execute([
            'id' => $operationId,
            'affected_service' => $affectedService,
            'standby_resource' => $standbyResource,
            'failover_type' => $failoverType,
            'transition_status' => $state,
            'verification_status' => $verificationStatus,
            'governance_status' => 'ungoverned',
            'final_outcome' => $finalOutcome,
            'error' => $error,
            'created_at' => $now->format(DATE_RFC3339),
        ]);

        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'failover.' . strtolower($state),
            new DateTimeImmutable(),
            self::class,
            ['failover_operation_id' => $operationId, 'affected_service' => $affectedService, 'state' => $state]
        ));

        return [
            'failover_operation_id' => $operationId,
            'state' => $state,
            'standby_resource' => $standbyResource,
            'verification_status' => $verificationStatus,
            'error' => $error,
        ];
    }
}
