<?php

declare(strict_types=1);

namespace SquirrelForge\Integration;

use Closure;
use DateTimeImmutable;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;
use Throwable;

/**
 * Adapts approved external version-control systems and repository APIs
 * into a standardized Integration-layer request/response interface, per
 * 26_INTEGRATIONS/VERSION-CONTROL.md -- the eleventh real component in
 * 26_INTEGRATIONS, and the fifth to compose other components of this
 * layer rather than stand alone.
 *
 * Unlike `AUTOMATION-CONNECTOR.md`'s free-form `trigger_ref`, this
 * spec's own "Supported Handoff Types" table is a closed, named set
 * (Clone/Fetch/Pull/Push/Branch/Commit/Tag/Release/Pull Request) --
 * `handoff_type` is validated against that exact set rather than
 * accepted as an opaque caller-supplied string, since the spec itself
 * draws the boundary there.
 *
 * This spec, unlike `API-GATEWAY.md`/`WEBHOOK-MANAGER.md`/
 * `AUTOMATION-CONNECTOR.md`, defines no named status vocabulary at
 * all -- only "Return version-control response, error, status, and
 * evidence references to the caller." Inventing a sixth, unrelated
 * vocabulary from nothing would fragment this layer's own internal
 * consistency, so this class reuses the closest already-established
 * names instead: `Request Invalid` and `Ready` from `ApiGateway`,
 * `Connector Blocked` for the same `active`/`degraded` routability
 * check `IntegrationManager` and `AutomationConnector` already apply
 * against a live `SqliteConnectorManager::get()` call, `Credential
 * Blocked` for a failed `IntegrationAuthentication` handshake, and
 * `Completed`/`Failed` for the caller-supplied handoff outcome -- a
 * single-request/single-response shape (each handoff type is one
 * repository API call from this connector's own perspective, unlike
 * Automation Connector's async job lifecycle), so no `Submitted`/
 * `Running` intermediate stage applies here.
 *
 * The actual repository-provider mechanics are a caller-supplied
 * `Closure` (`$handoff`) -- GitHub, GitLab, and Bitbucket share no wire
 * protocol, so per this layer's established "one fixed protocol would
 * fabricate a choice this spec never makes" reasoning, this class
 * never invents one. What it does own for real is the normalization
 * Responsibility explicitly names: commit, branch, tag, and
 * pull-request references are read from the closure's own result and
 * returned as named fields rather than left buried in an untyped
 * response blob.
 *
 * Rule 3 ("must not define branch policy, release policy, validation
 * policy, or rollback behavior") is upheld by this class never
 * inspecting or acting on *which* handoff type was requested beyond
 * validating it is one of the nine approved kinds -- approval and
 * business meaning stay entirely with the caller and the closure.
 *
 * Owns no database, matching this layer's other pure coordinators:
 * every terminal outcome is emitted as an `Event` for
 * `27_OBSERVABILITY` (Rule 5), and no event payload carries the
 * request payload, response body, or credential reference -- Rule 2
 * requires those stay references, never material this class exposes.
 */
final class VersionControlConnector
{
    private const HANDOFF_TYPES = ['Clone', 'Fetch', 'Pull', 'Push', 'Branch', 'Commit', 'Tag', 'Release', 'Pull Request'];

    private const ROUTABLE_CONNECTOR_STATUSES = ['active', 'degraded'];

    public function __construct(
        private readonly ?SqliteConnectorManager $connectorManager = null,
        private readonly ?IntegrationAuthentication $authentication = null,
        private readonly ?EventBusInterface $events = null
    ) {
    }

    /**
     * @param array{connector_id?: ?string, handoff_type?: ?string, payload?: array<string, mixed>, credential_ref?: ?string, authorized?: bool} $request
     * @param ?Closure $handoff (array $connector, array $request): array{response?: mixed, commit_ref?: ?string, branch_ref?: ?string, tag_ref?: ?string, pull_request_ref?: ?string, error?: ?string} the real, provider-specific repository API call. Omitting it leaves the result at `Ready` without fabricating a repository response.
     * @param ?Closure $signHandshake forwarded verbatim to IntegrationAuthentication::authenticate() when `credential_ref` is present.
     * @return array{status: string, connector_id: ?string, handoff_type: ?string, response: mixed, commit_ref: ?string, branch_ref: ?string, tag_ref: ?string, pull_request_ref: ?string, error: ?string}
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
                sprintf('Version-control handoff requires a connector_id and one of the approved handoff types (got: %s).', var_export($handoffType, true))
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
                return $this->failed('Credential Blocked', $connectorId, $handoffType, 'Version-control handoff requires a credential handshake but no IntegrationAuthentication component is configured.');
            }

            $authResult = $this->authentication->authenticate(
                $connectorId,
                ['credential_ref' => $request['credential_ref'], 'authorized' => $request['authorized'] ?? true],
                $signHandshake
            );

            if ($authResult['status'] !== 'valid') {
                return $this->failed('Credential Blocked', $connectorId, $handoffType, $authResult['error'] ?? sprintf('Version-control credential handshake did not reach a valid state (status: %s).', $authResult['status']));
            }
        }

        if ($handoff === null) {
            return $this->outcome('Ready', $connectorId, $handoffType, null, null, null, null, null, null);
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
            $outcome['commit_ref'] ?? null,
            $outcome['branch_ref'] ?? null,
            $outcome['tag_ref'] ?? null,
            $outcome['pull_request_ref'] ?? null,
            $error
        );
    }

    private function presentAndNonEmpty(mixed $value): bool
    {
        return is_string($value) && $value !== '';
    }

    /**
     * @return array{status: string, connector_id: ?string, handoff_type: ?string, response: mixed, commit_ref: ?string, branch_ref: ?string, tag_ref: ?string, pull_request_ref: ?string, error: ?string}
     */
    private function failed(string $status, ?string $connectorId, ?string $handoffType, string $reason): array
    {
        return $this->outcome($status, $connectorId, $handoffType, null, null, null, null, null, $reason);
    }

    /**
     * @return array{status: string, connector_id: ?string, handoff_type: ?string, response: mixed, commit_ref: ?string, branch_ref: ?string, tag_ref: ?string, pull_request_ref: ?string, error: ?string}
     */
    private function outcome(
        string $status,
        ?string $connectorId,
        ?string $handoffType,
        mixed $response,
        ?string $commitRef,
        ?string $branchRef,
        ?string $tagRef,
        ?string $pullRequestRef,
        ?string $error
    ): array {
        $this->emit($status, $connectorId, $handoffType);

        return [
            'status' => $status,
            'connector_id' => $connectorId,
            'handoff_type' => $handoffType,
            'response' => $response,
            'commit_ref' => $commitRef,
            'branch_ref' => $branchRef,
            'tag_ref' => $tagRef,
            'pull_request_ref' => $pullRequestRef,
            'error' => $error,
        ];
    }

    private function emit(string $status, ?string $connectorId, ?string $handoffType): void
    {
        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'version_control_connector.' . strtolower(str_replace(' ', '_', $status)),
            new DateTimeImmutable(),
            self::class,
            ['status' => $status, 'connector_id' => $connectorId, 'handoff_type' => $handoffType]
        ));
    }
}
