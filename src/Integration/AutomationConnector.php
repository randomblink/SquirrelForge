<?php

declare(strict_types=1);

namespace SquirrelForge\Integration;

use Closure;
use DateTimeImmutable;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;
use Throwable;

/**
 * Adapts external automation platforms, schedulers, job queues, and
 * CI/CD systems into a standardized Integration-layer handoff, per
 * 26_INTEGRATIONS/AUTOMATION-CONNECTOR.md -- the tenth real component in
 * 26_INTEGRATIONS, and the fourth to compose other components of this
 * layer rather than stand alone.
 *
 * Unlike API-GATEWAY.md/WEBHOOK-MANAGER.md, this spec's own "Automation
 * Statuses" table names the pre-response stage explicitly (`Submitted`:
 * "External automation trigger was submitted"), so omitting `$trigger`
 * is a real dry run that terminates at `Submitted` -- an exact spec
 * vocabulary match, not a borrowed status the way `WebhookManager`
 * reuses `Delivery Submitted`/`Delivery Failed` for pre-send shape
 * failures that spec's own table never separately names. This spec's
 * table likewise has no distinct "invalid request" or "credential
 * rejected" state, so every pre-submission failure here (missing
 * `connector_id`/`trigger_ref`, an unroutable connector, a failed
 * credential handshake) resolves to `Failed` with error text
 * preserving the real cause -- the same "closest applicable named
 * state, not a fabricated one" choice `WebhookManager::deliverOutbound()`
 * already makes.
 *
 * Connector readiness reuses `IntegrationManager`'s own
 * `active`/`degraded` routability rule verbatim against a live
 * `SqliteConnectorManager::get()` call -- the same "real, already-built
 * component, not caller-supplied evidence" composition
 * `IntegrationManager` established for this layer, applied consistently
 * rather than reinvented. Credential handling composes
 * `IntegrationAuthentication` the same way `WebhookManager` and
 * `LlmProviders` already do: a `credential_ref` on the request triggers
 * a real handshake, and only a `valid` outcome allows the trigger to
 * proceed.
 *
 * "Trigger, schedule, job, workflow, and pipeline references" (Rule 2 /
 * Responsibilities) are deliberately not constrained to one closed set
 * of trigger kinds the way `VERSION-CONTROL.md`'s nine handoff types
 * are -- this spec never enumerates one, so `trigger_ref` is an opaque,
 * caller-supplied identifier rather than a value this class validates
 * against an invented vocabulary.
 *
 * The actual external submission is a caller-supplied `Closure`
 * (`$trigger`) -- the same "one fixed protocol would fabricate a
 * choice this spec never makes" reasoning behind every provider-facing
 * `Closure` in this layer, since schedulers, job queues, and CI/CD
 * systems have nothing in common at the wire level. A closure result
 * with no error and no recognized status falls back to `Accepted`
 * rather than the more presumptuous `Completed` -- acknowledging
 * receipt without claiming to know completion the closure never
 * reported, the same "don't fabricate more certainty than the evidence
 * supports" discipline `LlmProviders` applies to its own Provider
 * States fallback.
 *
 * Owns no database, matching this layer's other pure coordinators:
 * every stage is emitted as an `Event` for `27_OBSERVABILITY` (Rule 5),
 * and, per this spec excluding "credential storage," no event payload
 * ever carries the trigger payload, response body, or credential
 * reference -- only status/connector/external-reference fields.
 */
final class AutomationConnector
{
    private const STATUSES = ['Submitted', 'Accepted', 'Running', 'Completed', 'Failed', 'Cancelled', 'Timed Out'];

    private const ROUTABLE_CONNECTOR_STATUSES = ['active', 'degraded'];

    public function __construct(
        private readonly ?SqliteConnectorManager $connectorManager = null,
        private readonly ?IntegrationAuthentication $authentication = null,
        private readonly ?EventBusInterface $events = null
    ) {
    }

    /**
     * @param array{connector_id?: ?string, trigger_ref?: ?string, payload?: array<string, mixed>, credential_ref?: ?string, authorized?: bool} $request
     * @param ?Closure $trigger (array $connector, array $request): array{status?: ?string, external_ref?: ?string, response?: mixed, error?: ?string} the real, platform-specific submission. Omitting it leaves the result at `Submitted` without fabricating a platform response.
     * @param ?Closure $signHandshake forwarded verbatim to IntegrationAuthentication::authenticate() when `credential_ref` is present.
     * @return array{status: string, connector_id: ?string, external_ref: ?string, response: mixed, error: ?string}
     */
    public function submit(array $request, ?Closure $trigger = null, ?Closure $signHandshake = null): array
    {
        $connectorId = $request['connector_id'] ?? null;
        $triggerRef = $request['trigger_ref'] ?? null;

        if (!$this->presentAndNonEmpty($connectorId) || !$this->presentAndNonEmpty($triggerRef)) {
            return $this->failed(null, 'Automation submission is missing a required connector_id or trigger_ref.');
        }

        $connector = $this->connectorManager?->get($connectorId);

        if ($connector === null || !in_array($connector['lifecycle_status'], self::ROUTABLE_CONNECTOR_STATUSES, true)) {
            return $this->failed(
                $connectorId,
                $connector === null
                    ? sprintf('Connector "%s" is not registered with Connector Manager.', $connectorId)
                    : sprintf('Connector "%s" is not active or degraded (status: %s).', $connectorId, $connector['lifecycle_status'])
            );
        }

        if (isset($request['credential_ref'])) {
            if ($this->authentication === null) {
                return $this->failed($connectorId, 'Automation submission requires a credential handshake but no IntegrationAuthentication component is configured.');
            }

            $authResult = $this->authentication->authenticate(
                $connectorId,
                ['credential_ref' => $request['credential_ref'], 'authorized' => $request['authorized'] ?? true],
                $signHandshake
            );

            if ($authResult['status'] !== 'valid') {
                return $this->failed($connectorId, $authResult['error'] ?? sprintf('Automation credential handshake did not reach a valid state (status: %s).', $authResult['status']));
            }
        }

        if ($trigger === null) {
            return $this->outcome('Submitted', $connectorId, null, null, null);
        }

        $this->emit('Submitted', $connectorId, null);

        try {
            $outcome = $trigger($connector, $request);
        } catch (Throwable $e) {
            return $this->failed($connectorId, $e->getMessage());
        }

        $status = $outcome['status'] ?? null;
        $error = $outcome['error'] ?? null;

        if (!is_string($status) || !in_array($status, self::STATUSES, true)) {
            $status = $error !== null ? 'Failed' : 'Accepted';
        }

        return $this->outcome($status, $connectorId, $outcome['external_ref'] ?? null, $outcome['response'] ?? null, $error);
    }

    private function presentAndNonEmpty(mixed $value): bool
    {
        return is_string($value) && $value !== '';
    }

    /**
     * @return array{status: string, connector_id: ?string, external_ref: ?string, response: mixed, error: ?string}
     */
    private function failed(?string $connectorId, string $reason): array
    {
        return $this->outcome('Failed', $connectorId, null, null, $reason);
    }

    /**
     * @return array{status: string, connector_id: ?string, external_ref: ?string, response: mixed, error: ?string}
     */
    private function outcome(string $status, ?string $connectorId, ?string $externalRef, mixed $response, ?string $error): array
    {
        $this->emit($status, $connectorId, $externalRef);

        return [
            'status' => $status,
            'connector_id' => $connectorId,
            'external_ref' => $externalRef,
            'response' => $response,
            'error' => $error,
        ];
    }

    private function emit(string $status, ?string $connectorId, ?string $externalRef): void
    {
        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'automation_connector.' . strtolower(str_replace(' ', '_', $status)),
            new DateTimeImmutable(),
            self::class,
            ['status' => $status, 'connector_id' => $connectorId, 'external_ref' => $externalRef]
        ));
    }
}
