<?php

declare(strict_types=1);

namespace SquirrelForge\Resilience;

use Closure;
use DateTimeImmutable;
use PDO;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;

/**
 * Automatically executes pre-approved corrective actions for recoverable
 * failures, per 35_RESILIENCE/SELF-HEALING-ENGINE.md.
 *
 * The spec's defining constraint is that this engine "does not
 * independently authorize corrective actions" and must never "execute
 * unauthorized corrective actions" or "exceed approved retry limits" --
 * unlike RecoveryManager, which trusts whatever action and retry policy
 * the caller hands it for a given call, this engine only runs actions
 * that were registered ahead of time via {@see registerAction()}, each
 * with its own fixed retry policy that a caller cannot override per
 * healing request. An unregistered action name is refused outright
 * (deny-by-default), which is the real scoping distinction from
 * RecoveryManager's `direct`/`retry` strategies, not an oversight.
 *
 * This is a deliberately scoped subset of the spec: healing is
 * synchronous (no Requested/Authorized/Preparing states, same reasoning
 * as RetryManager's docblock), and "governance policies"/"security
 * policies" inputs are not modeled since 23_GOVERNANCE and most of
 * 24_SECURITY's policy layer have no code to source them from --
 * governance_status is recorded as the fixed constant `ungoverned`.
 * Per the spec's escalation criteria ("data integrity cannot be
 * verified" -> escalate, "automatic recovery is no longer safe" ->
 * escalate), a supplied verifier returning false escalates a
 * successfully-executed action rather than merely marking it failed.
 */
final class SqliteSelfHealingEngine
{
    /**
     * @var array<string, array{action: Closure, policy: array<string, mixed>}>
     */
    private array $actions = [];

    private PDO $database;
    private RetryManager $retryManager;

    public function __construct(
        string $databasePath,
        ?RetryManager $retryManager = null,
        private readonly ?EventBusInterface $events = null
    ) {
        $this->retryManager = $retryManager ?? new RetryManager();

        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS self_healing_operations (
                healing_ref TEXT PRIMARY KEY,
                failure_ref TEXT,
                action TEXT NOT NULL,
                state TEXT NOT NULL,
                verification_status TEXT NOT NULL,
                governance_status TEXT NOT NULL,
                error TEXT,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * Pre-approves a corrective action for future healing requests. Only
     * actions registered this way can ever be executed by {@see heal()}.
     *
     * @param array{max_attempts?: int, strategy?: string, base_delay_seconds?: float} $policy
     *        forwarded to RetryManager; fixed at registration time and not
     *        overridable per healing request.
     */
    public function registerAction(string $name, Closure $action, array $policy = []): void
    {
        $this->actions[$name] = ['action' => $action, 'policy' => $policy];
    }

    /**
     * @param array{failure_ref?: ?string, verifier?: ?Closure} $options
     * @return array{healing_ref: ?string, failure_ref: ?string, action: string, state: string, verification_status: string, governance_status: ?string, error: ?string}
     */
    public function heal(string $action, array $options = []): array
    {
        $registered = $this->actions[$action] ?? null;

        if ($registered === null) {
            return [
                'healing_ref' => null,
                'failure_ref' => $options['failure_ref'] ?? null,
                'action' => $action,
                'state' => 'rejected',
                'verification_status' => 'not_applicable',
                'governance_status' => null,
                'error' => sprintf('Action "%s" is not a registered, approved self-healing action.', $action),
            ];
        }

        $result = $this->retryManager->execute($registered['action'], $registered['policy']);

        if ($result['status'] !== 'successful') {
            $state = $result['status'] === 'escalated' ? 'escalated' : 'failed';

            return $this->persist($action, $options['failure_ref'] ?? null, $state, 'not_applicable', $result['error']);
        }

        $verifier = $options['verifier'] ?? null;

        if ($verifier instanceof Closure) {
            $verified = $verifier() === true;
            $state = $verified ? 'successful' : 'escalated';
            $verificationStatus = $verified ? 'verified' : 'failed';
            $error = $verified ? null : 'Post-healing verification failed; recovery is no longer safe to consider automatic.';

            return $this->persist($action, $options['failure_ref'] ?? null, $state, $verificationStatus, $error);
        }

        return $this->persist($action, $options['failure_ref'] ?? null, 'successful', 'not_verified', null);
    }

    /**
     * @return array{healing_ref: string, failure_ref: ?string, action: string, state: string, verification_status: string, governance_status: string, error: ?string}
     */
    private function persist(
        string $action,
        ?string $failureRef,
        string $state,
        string $verificationStatus,
        ?string $error
    ): array {
        $healingRef = 'self_healing_' . bin2hex(random_bytes(12));
        $createdAt = gmdate(DATE_RFC3339);
        $governanceStatus = 'ungoverned';

        $statement = $this->database->prepare(
            'INSERT INTO self_healing_operations (
                healing_ref, failure_ref, action, state, verification_status,
                governance_status, error, created_at
            ) VALUES (
                :healing_ref, :failure_ref, :action, :state, :verification_status,
                :governance_status, :error, :created_at
            )'
        );
        $statement->execute([
            'healing_ref' => $healingRef,
            'failure_ref' => $failureRef,
            'action' => $action,
            'state' => $state,
            'verification_status' => $verificationStatus,
            'governance_status' => $governanceStatus,
            'error' => $error,
            'created_at' => $createdAt,
        ]);

        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'self_healing.finished',
            new DateTimeImmutable(),
            self::class,
            ['healing_ref' => $healingRef, 'action' => $action, 'state' => $state]
        ));

        return [
            'healing_ref' => $healingRef,
            'failure_ref' => $failureRef,
            'action' => $action,
            'state' => $state,
            'verification_status' => $verificationStatus,
            'governance_status' => $governanceStatus,
            'error' => $error,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function operation(string $healingRef): ?array
    {
        $statement = $this->database->prepare(
            'SELECT * FROM self_healing_operations WHERE healing_ref = :healing_ref'
        );
        $statement->execute(['healing_ref' => $healingRef]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $limit = 50): array
    {
        $statement = $this->database->prepare(
            'SELECT * FROM self_healing_operations ORDER BY rowid DESC LIMIT :limit'
        );
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }
}
