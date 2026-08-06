<?php

declare(strict_types=1);

namespace SquirrelForge\Resilience;

use Closure;
use DateTimeImmutable;
use PDO;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;
use SquirrelForge\Workflow\RollbackManager;
use Throwable;

/**
 * Coordinates recovery after a failure has been detected, per
 * 35_RESILIENCE/RECOVERY-MANAGER.md.
 *
 * This is a deliberately scoped subset of that spec: recovery is
 * synchronous (no Planned/Waiting/Verifying states -- same reasoning as
 * RetryManager's docblock), and only four of the ten listed strategies are
 * supported because they're the only ones with something real to delegate
 * to: `retry` (RetryManager), `rollback` (Workflow's RollbackManager,
 * given the caller's already-completed steps), `direct` (execute the
 * caller-supplied action once), and `manual` (records an escalation
 * without executing anything -- correct behavior for a strategy that by
 * definition requires a human, not a scoping gap). Resource reallocation
 * still has nothing real to delegate to: `ResourceOptimizer` is real now,
 * but its methods (`analyzeUtilizationBand()`, `analyzeAnomalies()`,
 * `analyzeConstraints()`) are advisory analysis, not a reallocation
 * actuator. Failover and disaster recovery escalation are different:
 * `SqliteFailoverCoordinator` and `SqliteDisasterRecovery` are both real
 * now, and Failover Coordinator's own spec names Recovery Manager as one
 * of the two components allowed to authorize it -- so wiring `failover`
 * through to `SqliteFailoverCoordinator::failover()` is a real, spec-backed
 * option, not an invented contract, just not done here yet; deciding what
 * this class's own recover() should treat as sufficient authorization
 * evidence for that call is a separate, bigger decision left for a future
 * pass, the same boundary ModelRouter draws around its own unwired
 * integrations. Governance status is the fixed constant `ungoverned`:
 * `23_GOVERNANCE/POLICY-ENGINE.md` is real now as `SqlitePolicyEngine`,
 * but this spec names no per-recovery policy category or context shape to
 * evaluate against.
 *
 * Per the spec's safety rule to never "restore unstable services without
 * verification": when the caller supplies a verifier, its result is
 * authoritative -- a verifier returning false marks the recovery `failed`
 * even though the underlying action didn't throw. Without a verifier, the
 * action's own outcome is the only signal available, which is recorded as
 * `not_verified` rather than silently upgraded to `verified`.
 */
final class SqliteRecoveryManager
{
    private const STRATEGIES = ['retry', 'rollback', 'direct', 'manual'];

    private PDO $database;
    private RetryManager $retryManager;
    private RollbackManager $rollbackManager;

    public function __construct(
        string $databasePath,
        ?RetryManager $retryManager = null,
        ?RollbackManager $rollbackManager = null,
        private readonly ?EventBusInterface $events = null
    ) {
        $this->retryManager = $retryManager ?? new RetryManager();
        $this->rollbackManager = $rollbackManager ?? new RollbackManager();

        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS recovery_operations (
                recovery_ref TEXT PRIMARY KEY,
                failure_ref TEXT,
                strategy TEXT NOT NULL,
                state TEXT NOT NULL,
                verification_status TEXT NOT NULL,
                governance_status TEXT NOT NULL,
                error TEXT,
                context_json TEXT NOT NULL,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array<string, mixed> $options {
     *     failure_ref?: ?string,
     *     action?: Closure,           required for `retry` and `direct`
     *     retry_policy?: array,       optional, `retry` only, forwarded to RetryManager
     *     completed_steps?: array,    required for `rollback`, forwarded to RollbackManager
     *     verifier?: ?Closure,        optional, `retry`/`direct` only, (): bool
     *     reason?: ?string,           optional, `manual` only
     * }
     * @return array{recovery_ref: ?string, failure_ref: ?string, strategy: string, state: string, verification_status: string, governance_status: ?string, error: ?string}
     */
    public function recover(string $strategy, array $options = []): array
    {
        if (!in_array($strategy, self::STRATEGIES, true)) {
            return $this->rejected($strategy, $options, sprintf('Unknown recovery strategy "%s".', $strategy));
        }

        if (in_array($strategy, ['retry', 'direct'], true) && !($options['action'] ?? null) instanceof Closure) {
            return $this->rejected($strategy, $options, sprintf('Strategy "%s" requires an "action" closure.', $strategy));
        }

        [$state, $error, $context, $verificationStatus] = match ($strategy) {
            'retry' => $this->recoverByRetry($options),
            'direct' => $this->recoverDirectly($options),
            'rollback' => $this->recoverByRollback($options),
            'manual' => ['escalated', null, ['reason' => $options['reason'] ?? null], 'not_applicable'],
        };

        return $this->persist($strategy, $options['failure_ref'] ?? null, $state, $verificationStatus, $error, $context);
    }

    /**
     * @return array{0: string, 1: ?string, 2: array<string, mixed>, 3: string}
     */
    private function recoverByRetry(array $options): array
    {
        $result = $this->retryManager->execute($options['action'], $options['retry_policy'] ?? []);

        if ($result['status'] !== 'successful') {
            return [
                $result['status'] === 'escalated' ? 'escalated' : 'failed',
                $result['error'],
                ['attempts' => $result['attempts']],
                'not_applicable',
            ];
        }

        $verification = $this->resolveVerification($options);

        return [$verification === 'failed' ? 'failed' : 'completed', null, ['attempts' => $result['attempts']], $verification];
    }

    /**
     * @return array{0: string, 1: ?string, 2: array<string, mixed>, 3: string}
     */
    private function recoverDirectly(array $options): array
    {
        try {
            ($options['action'])();
        } catch (Throwable $e) {
            return ['failed', $e->getMessage(), [], 'not_applicable'];
        }

        $verification = $this->resolveVerification($options);

        return [$verification === 'failed' ? 'failed' : 'completed', null, [], $verification];
    }

    /**
     * @return array{0: string, 1: ?string, 2: array<string, mixed>, 3: string}
     */
    private function recoverByRollback(array $options): array
    {
        $result = $this->rollbackManager->rollback($options['completed_steps'] ?? []);
        $state = match ($result['status']) {
            'successful' => 'completed',
            'partial' => 'partially_recovered',
            default => 'failed',
        };

        $error = $result['notes'] === [] ? null : implode(' ', $result['notes']);

        return [$state, $error, ['reversed' => $result['reversed'], 'notes' => $result['notes']], 'verified'];
    }

    /**
     * @param array<string, mixed> $options
     */
    private function resolveVerification(array $options): string
    {
        $verifier = $options['verifier'] ?? null;

        if (!($verifier instanceof Closure)) {
            return 'not_verified';
        }

        return $verifier() === true ? 'verified' : 'failed';
    }

    /**
     * @param array<string, mixed> $options
     * @return array{recovery_ref: ?string, failure_ref: ?string, strategy: string, state: string, verification_status: string, governance_status: ?string, error: string}
     */
    private function rejected(string $strategy, array $options, string $error): array
    {
        return [
            'recovery_ref' => null,
            'failure_ref' => $options['failure_ref'] ?? null,
            'strategy' => $strategy,
            'state' => 'rejected',
            'verification_status' => 'not_applicable',
            'governance_status' => null,
            'error' => $error,
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @return array{recovery_ref: string, failure_ref: ?string, strategy: string, state: string, verification_status: string, governance_status: string, error: ?string}
     */
    private function persist(
        string $strategy,
        ?string $failureRef,
        string $state,
        string $verificationStatus,
        ?string $error,
        array $context
    ): array {
        $recoveryRef = 'recovery_' . bin2hex(random_bytes(12));
        $createdAt = gmdate(DATE_RFC3339);
        $governanceStatus = 'ungoverned';

        $statement = $this->database->prepare(
            'INSERT INTO recovery_operations (
                recovery_ref, failure_ref, strategy, state, verification_status,
                governance_status, error, context_json, created_at
            ) VALUES (
                :recovery_ref, :failure_ref, :strategy, :state, :verification_status,
                :governance_status, :error, :context_json, :created_at
            )'
        );
        $statement->execute([
            'recovery_ref' => $recoveryRef,
            'failure_ref' => $failureRef,
            'strategy' => $strategy,
            'state' => $state,
            'verification_status' => $verificationStatus,
            'governance_status' => $governanceStatus,
            'error' => $error,
            'context_json' => json_encode($context, JSON_THROW_ON_ERROR),
            'created_at' => $createdAt,
        ]);

        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'recovery.finished',
            new DateTimeImmutable(),
            self::class,
            ['recovery_ref' => $recoveryRef, 'strategy' => $strategy, 'state' => $state]
        ));

        return [
            'recovery_ref' => $recoveryRef,
            'failure_ref' => $failureRef,
            'strategy' => $strategy,
            'state' => $state,
            'verification_status' => $verificationStatus,
            'governance_status' => $governanceStatus,
            'error' => $error,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function recovery(string $recoveryRef): ?array
    {
        $statement = $this->database->prepare(
            'SELECT * FROM recovery_operations WHERE recovery_ref = :recovery_ref'
        );
        $statement->execute(['recovery_ref' => $recoveryRef]);
        $row = $statement->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $limit = 50): array
    {
        $statement = $this->database->prepare(
            'SELECT * FROM recovery_operations ORDER BY rowid DESC LIMIT :limit'
        );
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return array_map($this->hydrate(...), $statement->fetchAll());
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        return [
            'recovery_ref' => $row['recovery_ref'],
            'failure_ref' => $row['failure_ref'],
            'strategy' => $row['strategy'],
            'state' => $row['state'],
            'verification_status' => $row['verification_status'],
            'governance_status' => $row['governance_status'],
            'error' => $row['error'],
            'context' => json_decode((string) $row['context_json'], true, flags: JSON_THROW_ON_ERROR),
            'created_at' => $row['created_at'],
        ];
    }
}
