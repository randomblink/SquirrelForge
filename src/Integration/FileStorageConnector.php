<?php

declare(strict_types=1);

namespace SquirrelForge\Integration;

use Closure;
use DateTimeImmutable;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;
use Throwable;

/**
 * Adapts approved external file-storage services into a standardized
 * Integration-layer request/response interface, per
 * 26_INTEGRATIONS/FILE-STORAGE.md -- the thirteenth and final real
 * component built for 26_INTEGRATIONS's fifteen-component roster
 * (`FLOCK-PLUGIN-ADAPTER.md` already had real code before this streak,
 * and `AI-PROVIDERS.md` is a deprecated redirect its own Rule forbids
 * adding responsibility to), and the seventh to compose other
 * components of this layer rather than stand alone.
 *
 * Same coordinator shape `VersionControlConnector` and
 * `DatabaseConnector` already established for a closed, spec-named
 * handoff-type set with no status vocabulary of its own: `handoff_type`
 * is validated against this spec's own nine values (Create/Read/Update/
 * Delete/Copy/Move/Synchronize/Archive/Restore), connector routability
 * reuses `IntegrationManager`'s exact `active`/`degraded` rule against a
 * live `SqliteConnectorManager::get()` call, credential handling
 * composes `IntegrationAuthentication`, and every terminal state reuses
 * this layer's already-established names (`Request Invalid`, `Connector
 * Blocked`, `Credential Blocked`, `Ready`, `Completed`, `Failed`)
 * instead of inventing an eighth unrelated vocabulary.
 *
 * What is genuinely different here: Responsibilities and Rule 4 name
 * "checksums, version references, and availability status" as the
 * fields this component normalizes -- distinct from
 * `DatabaseConnector`'s `transaction_status`/`usage` and
 * `VersionControlConnector`'s commit/branch/tag/pull-request
 * references, since each spec names its own real evidence shape. Rule
 * 4's "may report... but must not validate business outcomes" is
 * upheld the same way `DatabaseConnector` treats `transaction_status`:
 * `checksum_ref`/`version_ref`/`availability_status` are read verbatim
 * from the closure's own result, never compared, verified, or acted on
 * by this class.
 *
 * The provider-specific transfer mechanics are a caller-supplied
 * `Closure` (`$handoff`) -- S3, Google Cloud Storage, and an SFTP-backed
 * file service share no wire protocol, the same "one fixed protocol
 * would fabricate a choice this spec never makes" reasoning behind
 * every provider `Closure` in this layer. The Purpose's own "It does
 * not own SquirrelForge storage infrastructure,... or version history"
 * is upheld structurally: this class owns no database of its own --
 * `37_STORAGE`'s already-real `ObjectStorage`/`DocumentStorage` remain
 * the platform storage owner, this class only adapts an *external*
 * file-service handoff and never touches them. Every terminal outcome
 * is emitted as an `Event` for `27_OBSERVABILITY` (Rule 6); no event
 * payload carries the file content, response body, or credential
 * reference.
 */
final class FileStorageConnector
{
    private const HANDOFF_TYPES = ['Create', 'Read', 'Update', 'Delete', 'Copy', 'Move', 'Synchronize', 'Archive', 'Restore'];

    private const ROUTABLE_CONNECTOR_STATUSES = ['active', 'degraded'];

    public function __construct(
        private readonly ?SqliteConnectorManager $connectorManager = null,
        private readonly ?IntegrationAuthentication $authentication = null,
        private readonly ?EventBusInterface $events = null
    ) {
    }

    /**
     * @param array{connector_id?: ?string, handoff_type?: ?string, payload?: array<string, mixed>, credential_ref?: ?string, authorized?: bool} $request
     * @param ?Closure $handoff (array $connector, array $request): array{response?: mixed, checksum_ref?: ?string, version_ref?: ?string, availability_status?: ?string, error?: ?string} the real, provider-specific file-service call. Omitting it leaves the result at `Ready` without fabricating a provider response.
     * @param ?Closure $signHandshake forwarded verbatim to IntegrationAuthentication::authenticate() when `credential_ref` is present.
     * @return array{status: string, connector_id: ?string, handoff_type: ?string, response: mixed, checksum_ref: ?string, version_ref: ?string, availability_status: ?string, error: ?string}
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
                sprintf('File-storage handoff requires a connector_id and one of the approved handoff types (got: %s).', var_export($handoffType, true))
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
                return $this->failed('Credential Blocked', $connectorId, $handoffType, 'File-storage handoff requires a credential handshake but no IntegrationAuthentication component is configured.');
            }

            $authResult = $this->authentication->authenticate(
                $connectorId,
                ['credential_ref' => $request['credential_ref'], 'authorized' => $request['authorized'] ?? true],
                $signHandshake
            );

            if ($authResult['status'] !== 'valid') {
                return $this->failed('Credential Blocked', $connectorId, $handoffType, $authResult['error'] ?? sprintf('File-storage credential handshake did not reach a valid state (status: %s).', $authResult['status']));
            }
        }

        if ($handoff === null) {
            return $this->outcome('Ready', $connectorId, $handoffType, null, null, null, null, null);
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
            $outcome['checksum_ref'] ?? null,
            $outcome['version_ref'] ?? null,
            $outcome['availability_status'] ?? null,
            $error
        );
    }

    private function presentAndNonEmpty(mixed $value): bool
    {
        return is_string($value) && $value !== '';
    }

    /**
     * @return array{status: string, connector_id: ?string, handoff_type: ?string, response: mixed, checksum_ref: ?string, version_ref: ?string, availability_status: ?string, error: ?string}
     */
    private function failed(string $status, ?string $connectorId, ?string $handoffType, string $reason): array
    {
        return $this->outcome($status, $connectorId, $handoffType, null, null, null, null, $reason);
    }

    /**
     * @return array{status: string, connector_id: ?string, handoff_type: ?string, response: mixed, checksum_ref: ?string, version_ref: ?string, availability_status: ?string, error: ?string}
     */
    private function outcome(
        string $status,
        ?string $connectorId,
        ?string $handoffType,
        mixed $response,
        ?string $checksumRef,
        ?string $versionRef,
        ?string $availabilityStatus,
        ?string $error
    ): array {
        $this->emit($status, $connectorId, $handoffType);

        return [
            'status' => $status,
            'connector_id' => $connectorId,
            'handoff_type' => $handoffType,
            'response' => $response,
            'checksum_ref' => $checksumRef,
            'version_ref' => $versionRef,
            'availability_status' => $availabilityStatus,
            'error' => $error,
        ];
    }

    private function emit(string $status, ?string $connectorId, ?string $handoffType): void
    {
        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'file_storage_connector.' . strtolower(str_replace(' ', '_', $status)),
            new DateTimeImmutable(),
            self::class,
            ['status' => $status, 'connector_id' => $connectorId, 'handoff_type' => $handoffType]
        ));
    }
}
