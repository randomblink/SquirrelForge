<?php

declare(strict_types=1);

namespace SquirrelForge\Integration;

use Closure;
use DateTimeImmutable;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;
use Throwable;

/**
 * Coordinates an external-service authentication handshake for an
 * approved integration call, per 26_INTEGRATIONS/AUTHENTICATION.md --
 * the first real code in 26_INTEGRATIONS.
 *
 * This spec's own Boundary section explicitly excludes owning "credential
 * storage, secret storage, raw credential lifecycle" and "logs, metrics,
 * traces, dashboards, alerts, audit infrastructure, or observability
 * pipelines" -- unlike most other domain leaves in this codebase, it is
 * not meant to run its own SQLite audit table, so this class has none:
 * it is a pure coordinator over caller-supplied evidence, the same shape
 * as RetryManager and DecisionMatrix.
 *
 * "Coordinate external-service authentication handshakes" and "exchange
 * approved credential references for external provider tokens or signed
 * requests when the provider protocol requires it" cannot honestly mean
 * one fixed wire protocol: different providers use OAuth token exchange,
 * request signing, or plain API keys, and inventing one of those as *the*
 * real mechanism would fabricate a choice this spec never makes. Instead,
 * per-provider protocol mechanics are a caller-supplied `Closure` --
 * the same "operation-agnostic, caller supplies the operation directly"
 * boundary RetryManager already draws around itself -- and what this
 * class actually contributes is real: computing the correct one of the
 * spec's Authentication States from the caller-supplied credential
 * reference, authorization evidence, and handshake outcome, exactly per
 * the spec's own Authentication Flow and Rules.
 *
 * States are a real subset of the spec's seven (Ready/Pending/Valid/
 * Expired/Invalid/Revoked/Blocked): `pending` is not produced, since it
 * describes an in-flight asynchronous handshake and this coordinator is
 * synchronous, the same reasoning RetryManager applies to the retry
 * states its own synchronous execute() cannot produce.
 *
 * Rule 1 ("use approved credential references rather than raw secrets")
 * and Rule 4 ("credential storage and secret lifecycle decisions must
 * come from runtime-configuration and security owners") are upheld by
 * never generating or storing a credential reference here -- `credential_ref`
 * is always caller-supplied, sourced from the caller's own
 * `28_RUNTIME-CONFIG`/`24_SECURITY` components. Rule 3 ("platform
 * authentication and runtime authorization decisions must come from
 * `24_SECURITY`") is upheld the same "consume, don't compute" way
 * SqliteFailoverCoordinator treats its own `authorized` field: this class
 * never decides authorization itself, it only reads the caller-supplied
 * `authorized` evidence and blocks when it is explicitly false.
 *
 * Rule 2 ("must not store, log, or expose raw credential material") is
 * upheld by keeping the resulting token out of every event payload this
 * class emits (`has_token`/`expires_at`/`error` only, never the token
 * itself) while still returning it in the direct return value to the
 * calling integration component -- returning the token to the one caller
 * who requested the handshake is this class's actual job per "Return
 * external authentication status references to callers", not the
 * store/log/expose this rule forbids.
 */
final class IntegrationAuthentication
{
    private const STATES = ['ready', 'valid', 'expired', 'invalid', 'revoked', 'blocked'];

    public function __construct(private readonly ?EventBusInterface $events = null)
    {
    }

    /**
     * Coordinates a single external-service authentication handshake.
     *
     * @param array{credential_ref: ?string, authorized?: bool} $references
     *        Caller-supplied credential and authorization evidence.
     *        `authorized` defaults to true (not required) per the spec's
     *        "when required" phrasing on consuming `24_SECURITY` status.
     * @param ?Closure $handshake (array $references): array{token: ?string, expires_at: ?string, error: ?string}
     *        The real, provider-specific credential exchange or signing
     *        mechanics. Omitting it (no handshake to perform yet) leaves
     *        the result at `ready` rather than fabricating an outcome.
     * @return array{status: string, provider_ref: string, token: ?string, expires_at: ?string, error: ?string}
     */
    public function authenticate(string $providerRef, array $references, ?Closure $handshake = null): array
    {
        $credentialRef = $references['credential_ref'] ?? null;

        if ($credentialRef === null || $credentialRef === '') {
            return $this->result($providerRef, 'blocked', null, null, 'Missing required credential reference.');
        }

        if (($references['authorized'] ?? true) === false) {
            return $this->result(
                $providerRef,
                'blocked',
                null,
                null,
                'Required security or authorization reference is missing or denied.'
            );
        }

        if ($handshake === null) {
            return $this->result($providerRef, 'ready', null, null, null);
        }

        return $this->runHandshake($providerRef, $references, $handshake);
    }

    /**
     * Refreshes an external provider token "only through approved
     * references and owner-provided rules" (Responsibility) -- the same
     * handshake coordination as authenticate(), since this spec draws no
     * separate contract for refresh beyond that constraint.
     *
     * @param array{credential_ref: ?string, authorized?: bool} $references
     * @return array{status: string, provider_ref: string, token: ?string, expires_at: ?string, error: ?string}
     */
    public function refresh(string $providerRef, array $references, Closure $handshake): array
    {
        return $this->authenticate($providerRef, $references, $handshake);
    }

    /**
     * Marks a provider credential as no longer authorized. Per the spec's
     * Boundary, this class does not own token/secret lifecycle -- the
     * caller (the owning security/runtime-config component) has already
     * decided the revocation; this only records the resulting integration
     * authentication status.
     *
     * @return array{status: string, provider_ref: string, token: ?string, expires_at: ?string, error: ?string}
     */
    public function revoke(string $providerRef, string $reason = 'caller_requested'): array
    {
        return $this->result($providerRef, 'revoked', null, null, $reason);
    }

    /**
     * @param array{credential_ref: ?string, authorized?: bool} $references
     * @return array{status: string, provider_ref: string, token: ?string, expires_at: ?string, error: ?string}
     */
    private function runHandshake(string $providerRef, array $references, Closure $handshake): array
    {
        try {
            $outcome = $handshake($references);
        } catch (Throwable $e) {
            return $this->result($providerRef, 'invalid', null, null, $e->getMessage());
        }

        $token = $outcome['token'] ?? null;
        $error = $outcome['error'] ?? null;
        $expiresAt = $outcome['expires_at'] ?? null;

        if ($token === null || $error !== null) {
            return $this->result(
                $providerRef,
                'invalid',
                null,
                null,
                $error ?? 'External provider rejected the authentication material.'
            );
        }

        if ($expiresAt !== null && new DateTimeImmutable($expiresAt) <= new DateTimeImmutable()) {
            return $this->result($providerRef, 'expired', $token, $expiresAt, null);
        }

        return $this->result($providerRef, 'valid', $token, $expiresAt, null);
    }

    /**
     * @return array{status: string, provider_ref: string, token: ?string, expires_at: ?string, error: ?string}
     */
    private function result(string $providerRef, string $status, ?string $token, ?string $expiresAt, ?string $error): array
    {
        $status = in_array($status, self::STATES, true) ? $status : 'invalid';

        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'integration_authentication.' . $status,
            new DateTimeImmutable(),
            self::class,
            [
                'provider_ref' => $providerRef,
                'status' => $status,
                'has_token' => $token !== null,
                'expires_at' => $expiresAt,
                'error' => $error,
            ]
        ));

        return [
            'status' => $status,
            'provider_ref' => $providerRef,
            'token' => $token,
            'expires_at' => $expiresAt,
            'error' => $error,
        ];
    }
}
