<?php

declare(strict_types=1);

namespace SquirrelForge\Integration;

use Closure;
use DateTimeImmutable;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;
use Throwable;

/**
 * Adapts approved external database systems into a standardized
 * Integration-layer request/response interface, per
 * 26_INTEGRATIONS/DATABASE-CONNECTOR.md -- the twelfth real component
 * in 26_INTEGRATIONS, and the sixth to compose other components of
 * this layer rather than stand alone.
 *
 * Structurally this is the same coordinator shape `VersionControlConnector`
 * already established for a closed, spec-named handoff-type set with no
 * status vocabulary of its own: `handoff_type` is validated against this
 * spec's own five values (Connect/Query/Write/Transaction/Metadata)
 * rather than accepted as an opaque string, connector routability reuses
 * `IntegrationManager`'s exact `active`/`degraded` rule against a live
 * `SqliteConnectorManager::get()` call, credential handling composes
 * `IntegrationAuthentication`, and every terminal state reuses this
 * layer's already-established names (`Request Invalid`, `Connector
 * Blocked`, `Credential Blocked`, `Ready`, `Completed`, `Failed`)
 * instead of inventing a seventh unrelated vocabulary this spec never
 * defines.
 *
 * What is genuinely different here: Responsibilities explicitly call
 * out "transaction-status references returned by external systems" and
 * "Return database response, status, usage,... references" -- fields
 * `VersionControlConnector`'s own normalization has no equivalent of.
 * `transaction_status` and `usage` (row counts, affected-row counts,
 * query duration, or whatever the provider client reports) are read
 * from the closure's own result and returned as named fields, the same
 * "normalize what the spec names, not what a raw response blob happens
 * to contain" discipline `VersionControlConnector` applies to commit/
 * branch/tag/pull-request references.
 *
 * The provider-specific query/protocol mechanics are a caller-supplied
 * `Closure` (`$handoff`) -- Postgres, MySQL, and a REST-fronted database
 * share no wire protocol, the same "one fixed protocol would fabricate
 * a choice this spec never makes" reasoning behind every provider
 * `Closure` in this layer. Rule 4 ("may report database transaction
 * status, but it must not validate business outcome or mark tasks
 * complete") is upheld by `transaction_status` being read verbatim from
 * the closure, never interpreted into a business decision by this
 * class, and Rule 3 ("must not... become the platform persistence
 * owner") by this class owning no database of its own -- it is a pure
 * coordinator like every other real component in this layer, and every
 * terminal outcome is emitted as an `Event` for `27_OBSERVABILITY`
 * (Rule 6) rather than persisted here. No event payload carries the
 * query, response rows, or credential reference.
 */
final class DatabaseConnector
{
    private const HANDOFF_TYPES = ['Connect', 'Query', 'Write', 'Transaction', 'Metadata'];

    private const ROUTABLE_CONNECTOR_STATUSES = ['active', 'degraded'];

    public function __construct(
        private readonly ?SqliteConnectorManager $connectorManager = null,
        private readonly ?IntegrationAuthentication $authentication = null,
        private readonly ?EventBusInterface $events = null
    ) {
    }

    /**
     * @param array{connector_id?: ?string, handoff_type?: ?string, payload?: array<string, mixed>, credential_ref?: ?string, authorized?: bool} $request
     * @param ?Closure $handoff (array $connector, array $request): array{response?: mixed, transaction_status?: ?string, usage?: ?array<string, mixed>, error?: ?string} the real, provider-specific database call. Omitting it leaves the result at `Ready` without fabricating a database response.
     * @param ?Closure $signHandshake forwarded verbatim to IntegrationAuthentication::authenticate() when `credential_ref` is present.
     * @return array{status: string, connector_id: ?string, handoff_type: ?string, response: mixed, transaction_status: ?string, usage: ?array<string, mixed>, error: ?string}
     */
    public function coordinate(array $request, ?Closure $handoff = null, ?Closure $signHandshake = null): array
    {
        $connectorId = $request['connector_id'] ?? null;
        $handoffType = $request['handoff_type'] ?? null;

        if (!$this->presentAndNonEmpty($connectorId) || !is_string($handoffType) || !in_array($handoffType, self::HANDOFF_TYPES, true)) {
            return $this->failed(
                'Request Invalid',
                $connectorId,
                $handoffType,
                sprintf('Database handoff requires a connector_id and one of the approved handoff types (got: %s).', var_export($handoffType, true))
            );
        }

        $connector = $this->connectorManager?->get($connectorId);

        if ($connector === null || !in_array($connector['lifecycle_status'], self::ROUTABLE_CONNECTOR_STATUSES, true)) {
            return $this->failed(
                'Connector Blocked',
                $connectorId,
                $handoffType,
                $connector === null
                    ? sprintf('Connector "%s" is not registered with Connector Manager.', $connectorId)
                    : sprintf('Connector "%s" is not active or degraded (status: %s).', $connectorId, $connector['lifecycle_status'])
            );
        }

        if (isset($request['credential_ref'])) {
            if ($this->authentication === null) {
                return $this->failed('Credential Blocked', $connectorId, $handoffType, 'Database handoff requires a credential handshake but no IntegrationAuthentication component is configured.');
            }

            $authResult = $this->authentication->authenticate(
                $connectorId,
                ['credential_ref' => $request['credential_ref'], 'authorized' => $request['authorized'] ?? true],
                $signHandshake
            );

            if ($authResult['status'] !== 'valid') {
                return $this->failed('Credential Blocked', $connectorId, $handoffType, $authResult['error'] ?? sprintf('Database credential handshake did not reach a valid state (status: %s).', $authResult['status']));
            }
        }

        if ($handoff === null) {
            return $this->outcome('Ready', $connectorId, $handoffType, null, null, null, null);
        }

        try {
            $outcome = $handoff($connector, $request);
        } catch (Throwable $e) {
            return $this->failed('Failed', $connectorId, $handoffType, $e->getMessage());
        }

        $error = $outcome['error'] ?? null;

        return $this->outcome(
            $error !== null ? 'Failed' : 'Completed',
            $connectorId,
            $handoffType,
            $outcome['response'] ?? null,
            $outcome['transaction_status'] ?? null,
            is_array($outcome['usage'] ?? null) ? $outcome['usage'] : null,
            $error
        );
    }

    private function presentAndNonEmpty(mixed $value): bool
    {
        return is_string($value) && $value !== '';
    }

    /**
     * @return array{status: string, connector_id: ?string, handoff_type: ?string, response: mixed, transaction_status: ?string, usage: ?array<string, mixed>, error: ?string}
     */
    private function failed(string $status, ?string $connectorId, ?string $handoffType, string $reason): array
    {
        return $this->outcome($status, $connectorId, $handoffType, null, null, null, $reason);
    }

    /**
     * @param ?array<string, mixed> $usage
     * @return array{status: string, connector_id: ?string, handoff_type: ?string, response: mixed, transaction_status: ?string, usage: ?array<string, mixed>, error: ?string}
     */
    private function outcome(
        string $status,
        ?string $connectorId,
        ?string $handoffType,
        mixed $response,
        ?string $transactionStatus,
        ?array $usage,
        ?string $error
    ): array {
        $this->emit($status, $connectorId, $handoffType);

        return [
            'status' => $status,
            'connector_id' => $connectorId,
            'handoff_type' => $handoffType,
            'response' => $response,
            'transaction_status' => $transactionStatus,
            'usage' => $usage,
            'error' => $error,
        ];
    }

    private function emit(string $status, ?string $connectorId, ?string $handoffType): void
    {
        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'database_connector.' . strtolower(str_replace(' ', '_', $status)),
            new DateTimeImmutable(),
            self::class,
            ['status' => $status, 'connector_id' => $connectorId, 'handoff_type' => $handoffType]
        ));
    }
}
