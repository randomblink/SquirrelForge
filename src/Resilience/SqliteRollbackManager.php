<?php

declare(strict_types=1);

namespace SquirrelForge\Resilience;

use Closure;
use DateTimeImmutable;
use PDO;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;
use SquirrelForge\Workflow\RollbackManager as WorkflowRollbackManager;
use Throwable;

/**
 * Performs an already-approved rollback operation, restoring a
 * previously verified recovery point, per
 * 35_RESILIENCE/ROLLBACK-MANAGER.md. This spec has no formal "Depends
 * On" header (unlike newer specs in this codebase); its own
 * Integration Responsibilities section names Resilience Manager,
 * Recovery Manager, Deployment systems, Configuration Manager,
 * Workflow Engine, Observability Layer, Security Layer, and Resilience
 * Governance -- of these, only `SquirrelForge\Workflow\RollbackManager`
 * has real code to genuinely delegate to (LIFO reversal of a
 * StepWorkflow's completed steps); the rest have no src/ implementation
 * yet, so this class never fabricates them.
 *
 * "The Rollback Manager does not independently decide when a rollback
 * should occur. It performs approved rollback operations after
 * authorization by the Recovery Manager, governance components, or
 * other authorized platform controllers" is upheld literally:
 * `authorized` is caller-supplied evidence, not a decision this class
 * makes or a live call into SqliteRecoveryManager (a completed
 * recovery/authorization step is a distinct prior operation, not
 * something this class re-derives).
 *
 * Of the ten "Supported Rollback Types" the spec names, only `workflow`
 * has a real underlying mechanism (`Workflow\RollbackManager`). Every
 * other type (configuration, deployment, service, data, agent, plugin,
 * infrastructure, partial, full_platform) has no real subsystem in this
 * codebase to reverse, so those types accept a caller-supplied `action`
 * closure that performs the actual reversal -- the same honest
 * "direct" strategy pattern `SqliteRecoveryManager::recoverDirectly()`
 * already established for exactly this situation, rather than either
 * fabricating ten domain-specific reversal mechanisms or refusing to
 * support them at all.
 *
 * Two Safety Rules are real, enforced gates, not documentation: "must
 * never roll back without authorization" (an unauthorized request is
 * rejected before any reversal is attempted) and "must never restore
 * unverified recovery points" (an unverified recovery point is
 * rejected the same way) -- both fail the operation outright rather
 * than proceeding with a caveat.
 *
 * Verification mirrors SqliteRecoveryManager's own honest precedent: a
 * caller-supplied verifier's result is authoritative when present (a
 * false result fails the operation even though the reversal action
 * itself didn't throw); without one, the reversal's own outcome is the
 * only signal available, recorded as `not_verified` rather than
 * silently upgraded to `verified`. "Confirm operational stability" has
 * no separate real signal beyond this same verifier, so it is folded
 * into the one verification step rather than invented as a second
 * check.
 *
 * Governance status is the fixed constant `ungoverned`, the same
 * precedent SqliteRecoveryManager already established: Resilience
 * Governance (a distinct, not-yet-built 35_RESILIENCE component) is
 * this spec's own named governance authority, and substituting the
 * general-purpose SqlitePolicyEngine for it would misattribute a
 * domain-specific authority this class was never given.
 *
 * Owns its own database (`Sqlite` prefix): the Audit Requirements name
 * a specific structured record shape every rollback() call records
 * unconditionally, including rejected and failed operations, per
 * "Maintain audit continuity."
 */
final class SqliteRollbackManager
{
    private const TYPES = [
        'workflow', 'configuration', 'deployment', 'service', 'data',
        'agent', 'plugin', 'infrastructure', 'partial', 'full_platform',
    ];

    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly WorkflowRollbackManager $workflowRollback = new WorkflowRollbackManager(),
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
            'CREATE TABLE IF NOT EXISTS rollback_operations (
                rollback_operation_id TEXT PRIMARY KEY,
                recovery_point_id TEXT NOT NULL,
                rollback_type TEXT NOT NULL,
                state TEXT NOT NULL,
                verification_status TEXT NOT NULL,
                governance_status TEXT NOT NULL,
                recovery_outcome TEXT NOT NULL,
                final_outcome TEXT NOT NULL,
                error TEXT,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array{
     *     recovery_point_id: string,
     *     rollback_type: string,
     *     authorized: bool,
     *     verified: bool,
     *     completed_steps?: array<int, array{step: mixed, context: array<string, mixed>}>,
     *     action?: Closure,
     *     verifier?: ?Closure
     * } $request
     * @return array{rollback_operation_id: string, state: string, verification_status: string, recovery_outcome: string, error: ?string}
     */
    public function rollback(array $request): array
    {
        $missingField = $this->firstMissingField($request);

        if ($missingField !== null) {
            return $this->finish($request['recovery_point_id'] ?? 'unknown', $request['rollback_type'] ?? 'unknown', 'Failed', 'not_applicable', 'invalid_request', sprintf('Required field "%s" is missing from the rollback request.', $missingField));
        }

        if (!in_array($request['rollback_type'], self::TYPES, true)) {
            return $this->finish($request['recovery_point_id'], $request['rollback_type'], 'Failed', 'not_applicable', 'unsupported_type', sprintf('"%s" is not a supported rollback type.', $request['rollback_type']));
        }

        if ($request['authorized'] !== true) {
            return $this->finish($request['recovery_point_id'], $request['rollback_type'], 'Failed', 'not_applicable', 'blocked_unauthorized', 'Rollback rejected: no authorization was supplied for this operation.');
        }

        if ($request['verified'] !== true) {
            return $this->finish($request['recovery_point_id'], $request['rollback_type'], 'Failed', 'not_applicable', 'blocked_unverified_recovery_point', 'Rollback rejected: the recovery point has not been verified.');
        }

        [$state, $error] = $request['rollback_type'] === 'workflow'
            ? $this->executeWorkflowRollback($request)
            : $this->executeDirectRollback($request);

        if ($state === 'Failed') {
            return $this->finish($request['recovery_point_id'], $request['rollback_type'], $state, 'not_applicable', 'failed', $error);
        }

        $verificationStatus = $this->resolveVerification($request);

        if ($verificationStatus === 'failed') {
            return $this->finish($request['recovery_point_id'], $request['rollback_type'], 'Failed', 'failed', 'failed_verification', 'The restored state failed post-rollback verification.');
        }

        return $this->finish($request['recovery_point_id'], $request['rollback_type'], $state, $verificationStatus, 'recovered', $error);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $rollbackOperationId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM rollback_operations WHERE rollback_operation_id = :id');
        $statement->execute(['id' => $rollbackOperationId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $request
     */
    private function firstMissingField(array $request): ?string
    {
        foreach (['recovery_point_id', 'rollback_type', 'authorized', 'verified'] as $field) {
            if (!array_key_exists($field, $request)) {
                return $field;
            }
        }

        return null;
    }

    /**
     * @param array{completed_steps?: array<int, array{step: mixed, context: array<string, mixed>}>} $request
     * @return array{0: string, 1: ?string}
     */
    private function executeWorkflowRollback(array $request): array
    {
        $result = $this->workflowRollback->rollback($request['completed_steps'] ?? []);

        return match ($result['status']) {
            'successful' => ['Completed', null],
            'partial' => ['Partially completed', $result['notes'] === [] ? null : implode(' ', $result['notes'])],
            default => ['Failed', $result['notes'] === [] ? 'The workflow rollback reversed no steps.' : implode(' ', $result['notes'])],
        };
    }

    /**
     * @param array{action?: Closure} $request
     * @return array{0: string, 1: ?string}
     */
    private function executeDirectRollback(array $request): array
    {
        if (!($request['action'] ?? null) instanceof Closure) {
            return ['Failed', sprintf('Rollback type "%s" requires an "action" closure; no real subsystem exists to reverse it automatically.', $request['rollback_type'])];
        }

        try {
            ($request['action'])();
        } catch (Throwable $e) {
            return ['Failed', $e->getMessage()];
        }

        return ['Completed', null];
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
     * @return array{rollback_operation_id: string, state: string, verification_status: string, recovery_outcome: string, error: ?string}
     */
    private function finish(
        string $recoveryPointId,
        string $rollbackType,
        string $state,
        string $verificationStatus,
        string $recoveryOutcome,
        ?string $error
    ): array {
        $rollbackOperationId = 'rollback_op_' . bin2hex(random_bytes(12));
        $now = $this->clock !== null ? ($this->clock)() : new DateTimeImmutable();
        $finalOutcome = $state === 'Completed' ? 'restored' : ($state === 'Partially completed' ? 'partially_restored' : 'not_restored');

        $statement = $this->database->prepare(
            'INSERT INTO rollback_operations (
                rollback_operation_id, recovery_point_id, rollback_type, state, verification_status,
                governance_status, recovery_outcome, final_outcome, error, created_at
            ) VALUES (
                :id, :recovery_point_id, :rollback_type, :state, :verification_status,
                :governance_status, :recovery_outcome, :final_outcome, :error, :created_at
            )'
        );
        $statement->execute([
            'id' => $rollbackOperationId,
            'recovery_point_id' => $recoveryPointId,
            'rollback_type' => $rollbackType,
            'state' => $state,
            'verification_status' => $verificationStatus,
            'governance_status' => 'ungoverned',
            'recovery_outcome' => $recoveryOutcome,
            'final_outcome' => $finalOutcome,
            'error' => $error,
            'created_at' => $now->format(DATE_RFC3339),
        ]);

        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'rollback.' . strtolower(str_replace(' ', '_', $state)),
            new DateTimeImmutable(),
            self::class,
            ['rollback_operation_id' => $rollbackOperationId, 'rollback_type' => $rollbackType, 'state' => $state]
        ));

        return [
            'rollback_operation_id' => $rollbackOperationId,
            'state' => $state,
            'verification_status' => $verificationStatus,
            'recovery_outcome' => $recoveryOutcome,
            'error' => $error,
        ];
    }
}
