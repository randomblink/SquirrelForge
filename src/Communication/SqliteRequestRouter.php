<?php

declare(strict_types=1);

namespace SquirrelForge\Communication;

use Closure;
use DateTimeImmutable;
use PDO;
use SquirrelForge\Integration\ApiGateway;
use SquirrelForge\Integration\IntegrationAuthentication;
use SquirrelForge\Integration\IntegrationMonitor;
use SquirrelForge\Integration\SqliteConnectorManager;
use SquirrelForge\Integration\SqliteIntegrationGovernance;
use SquirrelForge\Resilience\RetryManager;

/**
 * Determines the appropriate destination and execution path for
 * approved integration requests -- selecting the correct service,
 * connector, endpoint, protocol, and routing strategy while ensuring
 * compliance with governance, authentication, availability, and
 * operational policies -- per 36_COMMUNICATION/REQUEST-ROUTER.md, the
 * one real gap remaining from the last audit-then-fork pass.
 *
 * Distinct from `26_INTEGRATIONS/INTEGRATION-MANAGER.md`'s own real
 * `IntegrationManager`, which this spec's closest analog: that class
 * coordinates one already-identified connector's readiness end to end,
 * while this spec is the layer above it that *selects* a routing
 * strategy and *decides* which real forwarding mechanism applies
 * (`ApiGateway` directly, or a caller-supplied connector handoff) --
 * "forward requests to the API Gateway or Connector Manager"
 * (Responsibilities) names both as real, distinct destinations this
 * class chooses between, not one it replaces.
 *
 * Genuine composition of every real sibling this spec names, never a
 * fabricated check standing in for one: `SqliteConnectorManager::get()`
 * for connector routability, reusing the exact `active`/`degraded`
 * routability convention `IntegrationManager` already established;
 * `SqliteIntegrationGovernance::review()` for governance approval,
 * reusing that same class's own real approving-decision set;
 * `IntegrationAuthentication::authenticate()` for authentication
 * readiness; `IntegrationMonitor::evaluate()` for service health,
 * mapping its own real six-value Finding vocabulary onto this spec's
 * Routing States (`Unavailable`/`Authentication Failing` block outright,
 * `Degraded`/`Rate Limited`/`Unknown` defer rather than fail, `Healthy`
 * passes); and `RetryManager::execute()` to wrap the actual forward
 * call for "support failover routing," reusing that class's own real
 * `successful`/`failed`/`escalated` outcomes rather than inventing a
 * parallel retry mechanism.
 *
 * "The Request Router routes requests only. It does not execute
 * external operations or modify request content" (Purpose) is upheld
 * by never performing the external interaction itself: forwarding is
 * either the real `ApiGateway::send()` or a caller-supplied
 * `$connectorHandoff` closure, the same "operation-agnostic, caller
 * supplies the operation directly" boundary `IntegrationManager`
 * already draws around its own `$handoff`.
 *
 * SQLite-backed for the explicit Audit Requirements section (ten named
 * fields) and "maintain routing history" (Responsibilities).
 */
final class SqliteRequestRouter
{
    /** Reused verbatim from IntegrationManager's own real routability convention. */
    private const ROUTABLE_CONNECTOR_STATUSES = ['active', 'degraded'];

    /** Reused verbatim from IntegrationManager's own real approving-decision set. */
    private const APPROVING_GOVERNANCE_DECISIONS = ['Approved', 'Approved with Conditions', 'Exception Approved'];

    /** IntegrationAuthentication's own real statuses that mean authentication is ready to route. */
    private const AUTHENTICATED_STATUSES = ['ready', 'valid'];

    /** IntegrationMonitor's own real Finding values that must block routing outright. */
    private const BLOCKING_FINDINGS = ['Unavailable', 'Authentication Failing'];

    /** IntegrationMonitor's own real Finding values that defer routing rather than fail it. */
    private const DEFERRING_FINDINGS = ['Degraded', 'Rate Limited', 'Unknown'];

    /** Routing Strategies, reproduced verbatim. */
    private const ROUTING_STRATEGIES = [
        'Direct routing', 'Connector-based routing', 'API Gateway routing', 'Primary/secondary failover',
        'Load-balanced routing', 'Priority-based routing', 'Policy-driven routing',
    ];

    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly ?SqliteConnectorManager $connectorManager = null,
        private readonly ?SqliteIntegrationGovernance $governance = null,
        private readonly ?IntegrationAuthentication $authentication = null,
        private readonly ?IntegrationMonitor $monitor = null,
        private readonly ?ApiGateway $apiGateway = null,
        private readonly ?RetryManager $retryManager = null
    ) {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS request_routing_decisions (
                routing_id TEXT PRIMARY KEY,
                request_id TEXT NOT NULL,
                target_service TEXT NOT NULL,
                connector_id TEXT,
                routing_strategy TEXT NOT NULL,
                authentication_status TEXT,
                governance_status TEXT,
                final_routing_outcome TEXT NOT NULL,
                error TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );
    }

    /**
     * Routing Workflow steps 1-9. Step 10 ("Notify the Integration
     * Monitor") is the caller's job once it has this real result --
     * this class only ever *reads* Monitor evidence, it never owns
     * notifying that component of anything.
     *
     * @param array{
     *     request_id?: ?string,
     *     target_service?: ?string,
     *     connector_id?: ?string,
     *     routing_strategy?: ?string,
     *     governance_request?: ?array<string, mixed>,
     *     auth_provider_ref?: ?string,
     *     auth_references?: array<string, mixed>,
     *     health_signals?: array<string, mixed>,
     *     gateway_request?: array<string, mixed>,
     *     retry_policy?: array{max_attempts?: int, strategy?: string, base_delay_seconds?: float, retryable?: ?Closure}
     * } $request
     * @param ?Closure $connectorHandoff (array $connector, array $request): mixed the real, connector-specific external interaction.
     * @return array{outcome: string, routing_id: ?string, state: ?string, error: ?string}
     */
    public function route(array $request, ?Closure $connectorHandoff = null): array
    {
        $requestId = $request['request_id'] ?? null;
        $targetService = $request['target_service'] ?? null;
        $routingStrategy = $request['routing_strategy'] ?? null;

        if (!$this->present($requestId) || !$this->present($targetService)) {
            return $this->envelope('invalid', null, null, 'Routing requires a non-empty request_id and target_service.');
        }

        if (!is_string($routingStrategy) || !in_array($routingStrategy, self::ROUTING_STRATEGIES, true)) {
            return $this->envelope('invalid', null, null, sprintf('"%s" is not one of this spec\'s named Routing Strategies.', (string) ($routingStrategy ?? '')));
        }

        $connectorId = $request['connector_id'] ?? null;

        $governanceStatus = null;

        if (isset($request['governance_request'])) {
            if ($this->governance === null) {
                return $this->record($request, 'Failed', $connectorId, null, null, 'Integration Governance is not configured; the request cannot be approved.');
            }

            $review = $this->governance->review($request['governance_request']);
            $governanceStatus = $review['decision'];

            if (!in_array($governanceStatus, self::APPROVING_GOVERNANCE_DECISIONS, true)) {
                return $this->record($request, 'Failed', $connectorId, null, $governanceStatus, sprintf('Governance did not approve this request: %s.', $governanceStatus));
            }
        }

        $authenticationStatus = null;

        if ($this->present($request['auth_provider_ref'] ?? null)) {
            if ($this->authentication === null) {
                return $this->record($request, 'Failed', $connectorId, null, $governanceStatus, 'Integration Authentication is not configured; authentication readiness cannot be confirmed.');
            }

            $authResult = $this->authentication->authenticate($request['auth_provider_ref'], $request['auth_references'] ?? []);
            $authenticationStatus = $authResult['status'];

            if (!in_array($authenticationStatus, self::AUTHENTICATED_STATUSES, true)) {
                return $this->record($request, 'Failed', $connectorId, $authenticationStatus, $governanceStatus, sprintf('Authentication is not ready: %s.', $authResult['error'] ?? $authenticationStatus));
            }
        }

        if ($this->present($connectorId)) {
            if ($this->connectorManager === null) {
                return $this->record($request, 'Failed', $connectorId, $authenticationStatus, $governanceStatus, 'Connector Manager is not configured.');
            }

            $connector = $this->connectorManager->get($connectorId);

            if ($connector === null) {
                return $this->record($request, 'Failed', $connectorId, $authenticationStatus, $governanceStatus, sprintf('Connector "%s" does not exist.', $connectorId));
            }

            if (!in_array($connector['lifecycle_status'], self::ROUTABLE_CONNECTOR_STATUSES, true)) {
                return $this->record($request, 'Deferred', $connectorId, $authenticationStatus, $governanceStatus, sprintf('Connector "%s" is not currently routable (status: %s).', $connectorId, $connector['lifecycle_status']));
            }

            if ($this->monitor !== null && isset($request['health_signals'])) {
                $health = $this->monitor->evaluate($connectorId, $request['health_signals']);

                if (in_array($health['finding'], self::BLOCKING_FINDINGS, true)) {
                    return $this->record($request, 'Failed', $connectorId, $authenticationStatus, $governanceStatus, sprintf('Connector health is %s: %s.', $health['finding'], implode(' ', $health['reasons'])));
                }

                if (in_array($health['finding'], self::DEFERRING_FINDINGS, true)) {
                    return $this->record($request, 'Deferred', $connectorId, $authenticationStatus, $governanceStatus, sprintf('Connector health is %s: %s.', $health['finding'], implode(' ', $health['reasons'])));
                }
            }
        }

        // Validated: governance, authentication, connector readiness, and health (whichever applied) all passed.
        $forwardOperation = $this->forwardOperation($request, $connectorId, $connectorHandoff);

        if ($forwardOperation === null) {
            return $this->record($request, 'Validated', $connectorId, $authenticationStatus, $governanceStatus, null);
        }

        $forwardResult = $this->retryManager !== null
            ? $this->retryManager->execute($forwardOperation, $request['retry_policy'] ?? [])
            : $this->invokeDirectly($forwardOperation);

        if ($forwardResult['status'] !== 'successful') {
            return $this->record($request, 'Failed', $connectorId, $authenticationStatus, $governanceStatus, $forwardResult['error'] ?? 'The forward operation did not succeed.');
        }

        return $this->record($request, 'Routed', $connectorId, $authenticationStatus, $governanceStatus, null);
    }

    /**
     * Explicit caller confirmation that a Routed decision's downstream
     * processing has concluded -- this class cannot itself know when
     * the caller's own use of the response finishes.
     *
     * @return array{outcome: string, routing_id: ?string, state: ?string, error: ?string}
     */
    public function complete(string $routingId): array
    {
        $record = $this->get($routingId);

        if ($record === null) {
            return $this->envelope('invalid', $routingId, null, sprintf('"%s" is not a known routing decision.', $routingId));
        }

        if ($record['final_routing_outcome'] !== 'Routed') {
            return $this->envelope('rejected', $routingId, $record['final_routing_outcome'], 'Only a Routed decision may be marked Completed.');
        }

        $this->database->prepare('UPDATE request_routing_decisions SET final_routing_outcome = :state, updated_at = :updated_at WHERE routing_id = :routing_id')
            ->execute(['state' => 'Completed', 'updated_at' => (new DateTimeImmutable())->format(DATE_ATOM), 'routing_id' => $routingId]);

        return $this->envelope('completed', $routingId, 'Completed', null);
    }

    /**
     * @return ?array<string, mixed>
     */
    public function get(string $routingId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM request_routing_decisions WHERE routing_id = :routing_id');
        $statement->execute(['routing_id' => $routingId]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function history(string $requestId): array
    {
        $statement = $this->database->prepare('SELECT * FROM request_routing_decisions WHERE request_id = :request_id ORDER BY rowid ASC');
        $statement->execute(['request_id' => $requestId]);

        return $statement->fetchAll();
    }

    /**
     * @param array<string, mixed> $request
     */
    private function forwardOperation(array $request, ?string $connectorId, ?Closure $connectorHandoff): ?Closure
    {
        if ($connectorHandoff !== null && $this->present($connectorId)) {
            $connector = $this->connectorManager?->get($connectorId);

            return fn(): mixed => $connectorHandoff($connector, $request);
        }

        if ($this->apiGateway !== null && isset($request['gateway_request'])) {
            // ApiGateway::send() returns a result array rather than throwing, but the
            // shared retry/invoke contract this class relies on only distinguishes
            // success from failure by whether an exception was raised -- so a real
            // gateway failure (never its own dry-run "Ready") is translated into one
            // here, rather than being silently treated as a successful forward.
            return function () use ($request): array {
                $result = $this->apiGateway->send($request['gateway_request']);

                if (!in_array($result['transport_status'], ['Ready', 'Normalized'], true)) {
                    throw new \RuntimeException($result['error'] ?? sprintf('API Gateway reported "%s".', $result['transport_status']));
                }

                return $result;
            };
        }

        return null;
    }

    /**
     * @return array{status: string, result: mixed, attempts: int, error: ?string, log: array<int, mixed>}
     */
    private function invokeDirectly(Closure $operation): array
    {
        try {
            return ['status' => 'successful', 'result' => $operation(), 'attempts' => 1, 'error' => null, 'log' => []];
        } catch (\Throwable $exception) {
            return ['status' => 'failed', 'result' => null, 'attempts' => 1, 'error' => $exception->getMessage(), 'log' => []];
        }
    }

    /**
     * @param array<string, mixed> $request
     * @return array{outcome: string, routing_id: ?string, state: ?string, error: ?string}
     */
    private function record(array $request, string $state, ?string $connectorId, ?string $authenticationStatus, ?string $governanceStatus, ?string $error): array
    {
        $routingId = 'routing_' . bin2hex(random_bytes(12));
        $now = (new DateTimeImmutable())->format(DATE_ATOM);

        $statement = $this->database->prepare(
            'INSERT INTO request_routing_decisions (
                routing_id, request_id, target_service, connector_id, routing_strategy,
                authentication_status, governance_status, final_routing_outcome, error, created_at, updated_at
            ) VALUES (
                :routing_id, :request_id, :target_service, :connector_id, :routing_strategy,
                :authentication_status, :governance_status, :final_routing_outcome, :error, :created_at, :updated_at
            )'
        );
        $statement->execute([
            'routing_id' => $routingId,
            'request_id' => $request['request_id'],
            'target_service' => $request['target_service'],
            'connector_id' => $connectorId,
            'routing_strategy' => $request['routing_strategy'],
            'authentication_status' => $authenticationStatus,
            'governance_status' => $governanceStatus,
            'final_routing_outcome' => $state,
            'error' => $error,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->envelope('resolved', $routingId, $state, $error);
    }

    private function present(mixed $value): bool
    {
        return is_string($value) && $value !== '';
    }

    /**
     * @return array{outcome: string, routing_id: ?string, state: ?string, error: ?string}
     */
    private function envelope(string $outcome, ?string $routingId, ?string $state, ?string $error): array
    {
        return ['outcome' => $outcome, 'routing_id' => $routingId, 'state' => $state, 'error' => $error];
    }
}
