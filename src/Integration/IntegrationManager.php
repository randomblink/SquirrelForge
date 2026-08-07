<?php

declare(strict_types=1);

namespace SquirrelForge\Integration;

use Closure;
use DateTimeImmutable;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;
use Throwable;

/**
 * Coordinates integration requests that need communication with external
 * systems, services, APIs, tools, databases, plugins, providers, and
 * automation platforms, per 26_INTEGRATIONS/INTEGRATION-MANAGER.md --
 * the fourth real component in 26_INTEGRATIONS, and the first to
 * actually compose the layer's other three (IntegrationAuthentication,
 * SqliteConnectorManager, SqliteIntegrationGovernance) rather than
 * standing alone.
 *
 * Unlike Integration Governance's own restraint toward Connector Manager
 * ("reviews supplied evidence... does not replace the owner that
 * produced it"), this spec's own Responsibilities and Rules use
 * different language for the same dependency: "Request connector
 * metadata... FROM CONNECTOR-MANAGER.md" and Rule 2's "must use
 * connector metadata and status from CONNECTOR-MANAGER.md; it must not
 * maintain a parallel connector registry." A parallel registry is
 * exactly what trusting caller-supplied connector metadata would amount
 * to, so `SqliteConnectorManager::get()` is a real, live call here, not
 * caller-supplied evidence. The same "consume... FROM X" language
 * appears for Integration Governance (Rule 4), so
 * `SqliteIntegrationGovernance::review()` is live-called too, the same
 * "delegate to the real, already-built component" composition this
 * codebase's governance components already establish toward Policy
 * Engine. Credential/authentication/authorization status is different:
 * `24_SECURITY`/`28_RUNTIME-CONFIG` name no single generic
 * "is this request authorized" method this class could call without
 * inventing one, so that stays caller-supplied evidence
 * (`credential_authorized`), the same boundary IntegrationAuthentication
 * already draws around its own `authorized` field.
 *
 * A connector must be `active` or `degraded` to route -- Connector
 * Manager's own spec only calls `Degraded` "reduced or unreliable," not
 * unroutable, so both are accepted; `registered`/`readiness_pending`/
 * `ready`/`suspended`/`retired` are not, and resolve to `Readiness
 * Blocked` per this spec's own Request Readiness Checks framing
 * ("connector... references can be requested from the appropriate
 * owner" -- a connector that exists but isn't active can't supply a
 * usable reference yet).
 *
 * Missing or unconfigured Integration Governance fails closed to
 * `Governance Blocked` -- the same deny-by-default stance every
 * governance-backed component in this codebase already takes toward an
 * unconfigured Policy Engine, applied one level up: Rule 4 forbids this
 * class from approving governance policy itself, so "no decision
 * available" can never default to proceeding.
 *
 * The per-provider external interaction itself is a caller-supplied
 * `Closure` (`$handoff`), never one invented protocol -- the same
 * "operation-agnostic, caller supplies the operation directly" boundary
 * RetryManager and IntegrationAuthentication already draw around
 * themselves, and this spec's own Boundary excludes "external execution
 * internals inside provider, connector, API, webhook..." components
 * outright. Recovery is requested, never executed, via a second
 * optional caller-supplied `$recoveryRequest` Closure (Rule 6):
 * reaching `Recovery Requested` never resolves further to a retried
 * response inside this class.
 *
 * Calling coordinate() with no `$handoff` at all is a legitimate
 * real use, not a degenerate one: readiness, connector, governance, and
 * credential checks still run for real, and the call terminates at
 * `Routed` -- a named status in this spec's own table -- letting a
 * caller dry-run "would this request be allowed to proceed" without
 * triggering the external interaction.
 *
 * Owns no database, matching IntegrationAuthentication's identical
 * Boundary language ("does not own... storage... or logs, metrics,
 * traces, dashboards, alerts, audit infrastructure, or observability
 * pipelines"): this class is a pure coordinator, emitting each stage
 * transition as an Event for observability owners to record, never
 * persisting one itself.
 */
final class IntegrationManager
{
    private const ROUTABLE_CONNECTOR_STATUSES = ['active', 'degraded'];

    private const APPROVING_GOVERNANCE_DECISIONS = ['Approved', 'Approved with Conditions', 'Exception Approved'];

    public function __construct(
        private readonly ?SqliteConnectorManager $connectorManager = null,
        private readonly ?SqliteIntegrationGovernance $governance = null,
        private readonly ?EventBusInterface $events = null
    ) {
    }

    /**
     * @param array{requesting_component?: ?string, connector_id?: ?string, capability?: ?string, credential_authorized?: bool, credential_status?: ?string, governance_request?: ?array<string, mixed>} $request
     * @param ?Closure $handoff (array $connector, array $request): mixed the real, provider-specific external interaction. Omitting it makes this a readiness/governance/credential dry-run that terminates at `Routed`.
     * @param ?Closure $recoveryRequest (array $context): mixed notifies (never executes) retry/recovery/rollback owners when the handoff reports an external failure.
     * @return array{integration_status: string, stages: array<int, string>, connector_id: ?string, governance_decision: ?string, response: mixed, error: ?string}
     */
    public function coordinate(array $request, ?Closure $handoff = null, ?Closure $recoveryRequest = null): array
    {
        $stages = ['Received'];
        $requestingComponent = $request['requesting_component'] ?? null;
        $connectorId = $request['connector_id'] ?? null;
        $capability = $request['capability'] ?? null;

        if (!$this->presentAndNonEmpty($requestingComponent) || !$this->presentAndNonEmpty($connectorId) || !$this->presentAndNonEmpty($capability)) {
            return $this->blocked($stages, 'Readiness Blocked', $connectorId, null, 'Request is missing a requesting_component, connector_id, or capability.');
        }

        $connector = $this->connectorManager?->get($connectorId);

        if ($connector === null || !in_array($connector['lifecycle_status'], self::ROUTABLE_CONNECTOR_STATUSES, true)) {
            return $this->blocked(
                $stages,
                'Readiness Blocked',
                $connectorId,
                null,
                $connector === null
                    ? sprintf('Connector "%s" is not registered with Connector Manager.', $connectorId)
                    : sprintf('Connector "%s" is not active or degraded (status: %s).', $connectorId, $connector['lifecycle_status'])
            );
        }

        $governanceDecision = null;

        if ($this->governance !== null && isset($request['governance_request']) && is_array($request['governance_request'])) {
            $governanceDecision = $this->governance->review($request['governance_request'])['decision'];
        }

        if (!in_array($governanceDecision, self::APPROVING_GOVERNANCE_DECISIONS, true)) {
            return $this->blocked(
                $stages,
                'Governance Blocked',
                $connectorId,
                $governanceDecision,
                $governanceDecision === null
                    ? 'No integration governance decision is available for this request; blocking by default.'
                    : sprintf('Integration governance decision "%s" does not approve this request.', $governanceDecision)
            );
        }

        if (($request['credential_authorized'] ?? true) === false) {
            return $this->blocked(
                $stages,
                'Credential Blocked',
                $connectorId,
                $governanceDecision,
                $request['credential_status'] ?? 'Required credential, authentication, or authorization status was not approved.'
            );
        }

        $stages[] = 'Routed';
        $this->emit('Routed', $connectorId);

        if ($handoff === null) {
            return [
                'integration_status' => 'Routed',
                'stages' => $stages,
                'connector_id' => $connectorId,
                'governance_decision' => $governanceDecision,
                'response' => null,
                'error' => null,
            ];
        }

        return $this->runHandoff($stages, $connector, $request, $connectorId, $governanceDecision, $handoff, $recoveryRequest);
    }

    /**
     * @param array<int, string> $stages
     * @param array<string, mixed> $connector
     * @param array<string, mixed> $request
     * @return array{integration_status: string, stages: array<int, string>, connector_id: ?string, governance_decision: ?string, response: mixed, error: ?string}
     */
    private function runHandoff(
        array $stages,
        array $connector,
        array $request,
        string $connectorId,
        ?string $governanceDecision,
        Closure $handoff,
        ?Closure $recoveryRequest
    ): array {
        try {
            $result = $handoff($connector, $request);
            $error = is_array($result) ? ($result['error'] ?? null) : null;
        } catch (Throwable $e) {
            $result = null;
            $error = $e->getMessage();
        }

        if ($error !== null) {
            $stages[] = 'External Failure Reported';
            $this->emit('External Failure Reported', $connectorId);

            if ($recoveryRequest !== null) {
                $recoveryRequest(['connector_id' => $connectorId, 'requesting_component' => $request['requesting_component'], 'error' => $error]);
                $stages[] = 'Recovery Requested';
                $this->emit('Recovery Requested', $connectorId);
            }
        } else {
            $stages[] = 'Response Received';
            $this->emit('Response Received', $connectorId);
        }

        $stages[] = 'Completed Handoff';
        $this->emit('Completed Handoff', $connectorId);

        return [
            'integration_status' => 'Completed Handoff',
            'stages' => $stages,
            'connector_id' => $connectorId,
            'governance_decision' => $governanceDecision,
            'response' => $error === null ? $result : null,
            'error' => $error,
        ];
    }

    private function presentAndNonEmpty(mixed $value): bool
    {
        return is_string($value) && $value !== '';
    }

    /**
     * @param array<int, string> $stages
     * @return array{integration_status: string, stages: array<int, string>, connector_id: ?string, governance_decision: ?string, response: mixed, error: ?string}
     */
    private function blocked(array $stages, string $status, ?string $connectorId, ?string $governanceDecision, string $reason): array
    {
        $stages[] = $status;
        $this->emit($status, $connectorId);

        return [
            'integration_status' => $status,
            'stages' => $stages,
            'connector_id' => $connectorId,
            'governance_decision' => $governanceDecision,
            'response' => null,
            'error' => $reason,
        ];
    }

    private function emit(string $status, ?string $connectorId): void
    {
        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'integration_manager.' . strtolower(str_replace(' ', '_', $status)),
            new DateTimeImmutable(),
            self::class,
            ['status' => $status, 'connector_id' => $connectorId]
        ));
    }
}
