<?php

declare(strict_types=1);

namespace SquirrelForge\Integration;

use Closure;
use DateTimeImmutable;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;
use Throwable;

/**
 * Adapts inbound and outbound webhook communications between
 * SquirrelForge and approved external systems, per
 * 26_INTEGRATIONS/WEBHOOK-MANAGER.md -- the seventh real component in
 * 26_INTEGRATIONS, and the second (after `IntegrationManager`) to
 * genuinely compose other components of this layer rather than stand
 * alone.
 *
 * Rule 3 ("may perform protocol-level signature, replay, and
 * payload-shape checks only") is a real grant, not caller-supplied
 * evidence to consume -- unlike most of this layer's boundary language,
 * which pushes provider-specific mechanics out to a caller-supplied
 * `Closure`, HMAC-based webhook signing (`hash_hmac()` over the raw
 * body, timing-safe `hash_equals()` comparison, with the common
 * `algo=digest` header-prefix convention several real providers use)
 * genuinely is protocol-level rather than any one provider's business
 * logic, so this class computes it for real. The signing secret itself
 * is caller-supplied per call and used only transiently for the
 * comparison -- never stored, matching Rule 2's requirement that
 * credential/signing references come from owning components and the
 * Purpose's exclusion of "credential storage." Timestamp tolerance is
 * likewise real arithmetic against an injectable "now" (for
 * deterministic tests). Replay detection is different: recognizing a
 * duplicate delivery requires a store of previously-seen nonces, and
 * the Boundary excludes "logging, audit,... or observability
 * infrastructure" and this layer overall excludes storage ownership
 * (`37_STORAGE`), so replay state cannot live here -- `$replayCheck` is
 * a caller-supplied `Closure` over the caller's own dedup store, the
 * same "operation-agnostic, caller supplies the operation" boundary
 * `RetryManager` and `IntegrationAuthentication` already draw.
 *
 * Rule 4 ("must not route business events independently of Integration
 * Manager or workflow owners") is upheld the same way
 * `IntegrationManager::coordinate()` treats a `null` `$handoff`:
 * `$dispatch` is optional, and an accepted inbound webhook that receives
 * no dispatch closure terminates at `Accepted`, never `Dispatched` --
 * this class never decides on its own that an event should be routed
 * anywhere.
 *
 * Outbound delivery composes two of this layer's already-built
 * components instead of reinventing their work: "Coordinate outbound
 * webhook signing/authentication using approved references" is exactly
 * `IntegrationAuthentication::authenticate()`'s job, and "translate...
 * into provider-specific payloads" then transmit is exactly
 * `ApiGateway::send()`'s job once a caller-supplied `$translate` closure
 * (provider-specific payload shape, the same reasoning that keeps
 * `IntegrationAuthentication`'s handshake and `IntegrationManager`'s
 * handoff caller-supplied) has produced a body. `Delivered` vs `Delivery
 * Failed` is decided from the real HTTP status code `ApiGateway`
 * returns (2xx vs not) -- the same "genuinely protocol-level, not
 * business logic" reasoning as the signature check, not a fabricated
 * business-outcome judgment forbidden by the Purpose's "does not own...
 * business validation."
 *
 * Rule 5 ("may report retryable delivery status, but retry/recovery
 * decisions belong to execution and coordination owners") means a
 * `Delivery Failed` result here is only ever reported, never retried by
 * this class.
 *
 * Owns no database, matching this layer's other pure coordinators:
 * every stage transition is emitted as an `Event` for
 * `27_OBSERVABILITY` owners to record (Rule 6), and per Rule 3's own
 * signing-secret handling, no event payload ever carries a raw body,
 * signature, secret, or token.
 */
final class WebhookManager
{
    private const DEFAULT_SIGNATURE_ALGO = 'sha256';

    private const DEFAULT_TIMESTAMP_TOLERANCE_SECONDS = 300;

    public function __construct(
        private readonly ?ApiGateway $apiGateway = null,
        private readonly ?IntegrationAuthentication $authentication = null,
        private readonly ?EventBusInterface $events = null
    ) {
    }

    /**
     * @param array{
     *     provider_ref?: ?string,
     *     raw_body?: ?string,
     *     signature?: ?string,
     *     signing_secret?: ?string,
     *     signature_algo?: string,
     *     timestamp?: mixed,
     *     timestamp_tolerance_seconds?: int,
     *     nonce_ref?: ?string,
     *     now?: ?string
     * } $request
     * @param ?Closure $replayCheck (string $nonceRef): bool true when this nonce was already seen (a replay); consulted only when `nonce_ref` is present.
     * @param ?Closure $dispatch (array $event): mixed hands the accepted event reference to the approved caller or Integration Manager. Omitting it leaves the result at `Accepted` without routing anything.
     * @return array{webhook_status: string, stages: array<int, string>, event_ref: ?string, provider_ref: ?string, payload: ?array<string, mixed>, error: ?string}
     */
    public function receiveInbound(array $request, ?Closure $replayCheck = null, ?Closure $dispatch = null): array
    {
        $stages = ['Received'];
        $providerRef = $request['provider_ref'] ?? null;
        $this->emit('Received', $providerRef, null);

        $rawBody = $request['raw_body'] ?? null;

        if (!is_string($rawBody) || $rawBody === '') {
            return $this->rejected($stages, $providerRef, 'Inbound webhook request is missing a raw_body reference.');
        }

        try {
            /** @var array<string, mixed> $payload */
            $payload = json_decode($rawBody, true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return $this->rejected($stages, $providerRef, 'Inbound webhook payload is not well-formed JSON.');
        }

        if (!is_array($payload)) {
            return $this->rejected($stages, $providerRef, 'Inbound webhook payload must decode to a JSON object or array.');
        }

        $signatureError = $this->signatureError(
            $rawBody,
            $request['signature'] ?? null,
            $request['signing_secret'] ?? null,
            $request['signature_algo'] ?? self::DEFAULT_SIGNATURE_ALGO
        );

        if ($signatureError !== null) {
            return $this->rejected($stages, $providerRef, $signatureError);
        }

        $now = isset($request['now']) && is_string($request['now'])
            ? new DateTimeImmutable($request['now'])
            : new DateTimeImmutable();

        $timestampError = $this->timestampError(
            $request['timestamp'] ?? null,
            $request['timestamp_tolerance_seconds'] ?? self::DEFAULT_TIMESTAMP_TOLERANCE_SECONDS,
            $now
        );

        if ($timestampError !== null) {
            return $this->rejected($stages, $providerRef, $timestampError);
        }

        $nonceRef = $request['nonce_ref'] ?? null;

        if ($replayCheck !== null && is_string($nonceRef) && $nonceRef !== '' && $replayCheck($nonceRef) === true) {
            return $this->rejected($stages, $providerRef, sprintf('Webhook delivery "%s" has already been received (replay).', $nonceRef));
        }

        $eventRef = 'webhook_event_' . bin2hex(random_bytes(12));
        $stages[] = 'Accepted';
        $this->emit('Accepted', $providerRef, $eventRef);

        $event = [
            'event_ref' => $eventRef,
            'provider_ref' => $providerRef,
            'payload' => $payload,
            'received_at' => $now->format(DATE_ATOM),
        ];

        if ($dispatch === null) {
            return [
                'webhook_status' => 'Accepted',
                'stages' => $stages,
                'event_ref' => $eventRef,
                'provider_ref' => $providerRef,
                'payload' => $payload,
                'error' => null,
            ];
        }

        $dispatch($event);
        $stages[] = 'Dispatched';
        $this->emit('Dispatched', $providerRef, $eventRef);

        return [
            'webhook_status' => 'Dispatched',
            'stages' => $stages,
            'event_ref' => $eventRef,
            'provider_ref' => $providerRef,
            'payload' => $payload,
            'error' => null,
        ];
    }

    /**
     * @param array{
     *     provider_ref?: ?string,
     *     endpoint_ref?: ?string,
     *     event?: array<string, mixed>,
     *     body?: ?string,
     *     headers?: array<string, string>,
     *     signature_header?: string,
     *     credential_ref?: ?string,
     *     authorized?: bool,
     *     timeout_seconds?: ?float
     * } $request
     * @param ?Closure $translate (array $event): array{body: string, headers?: array<string, string>} provider-specific outbound payload translation. Required whenever `body` is not already supplied directly.
     * @param ?Closure $signHandshake (array $references): array{token: ?string, expires_at: ?string, error: ?string} forwarded verbatim to IntegrationAuthentication::authenticate() when `credential_ref` requests signing.
     * @return array{webhook_status: string, stages: array<int, string>, provider_ref: ?string, status_code: ?int, error: ?string}
     */
    public function deliverOutbound(array $request, ?Closure $translate = null, ?Closure $signHandshake = null): array
    {
        $stages = [];
        $providerRef = $request['provider_ref'] ?? null;
        $endpointRef = $request['endpoint_ref'] ?? null;

        if (!is_string($providerRef) || $providerRef === '' || !is_string($endpointRef) || $endpointRef === '') {
            return $this->deliveryFailed($stages, $providerRef, 'Outbound webhook delivery is missing a required provider_ref or endpoint_ref.');
        }

        $translated = $this->translatedPayload($request, $translate);

        if ($translated === null) {
            return $this->deliveryFailed(
                $stages,
                $providerRef,
                'Outbound webhook delivery has no body: supply "body" directly or an "event" with a translate callback.'
            );
        }

        [$body, $headers] = $translated;

        if (isset($request['credential_ref'])) {
            if ($this->authentication === null) {
                return $this->deliveryFailed($stages, $providerRef, 'Outbound webhook signing was requested but no IntegrationAuthentication component is configured.');
            }

            $authResult = $this->authentication->authenticate(
                $providerRef,
                ['credential_ref' => $request['credential_ref'], 'authorized' => $request['authorized'] ?? true],
                $signHandshake
            );

            if ($authResult['status'] !== 'valid') {
                return $this->deliveryFailed($stages, $providerRef, $authResult['error'] ?? sprintf('Outbound webhook signing did not reach a valid state (status: %s).', $authResult['status']));
            }

            $headers[$request['signature_header'] ?? 'Authorization'] = 'Bearer ' . $authResult['token'];
        }

        $stages[] = 'Delivery Submitted';
        $this->emit('Delivery Submitted', $providerRef, null);

        if ($this->apiGateway === null) {
            return [
                'webhook_status' => 'Delivery Submitted',
                'stages' => $stages,
                'provider_ref' => $providerRef,
                'status_code' => null,
                'error' => null,
            ];
        }

        $transportResult = $this->apiGateway->send([
            'endpoint_ref' => $endpointRef,
            'method' => 'POST',
            'headers' => $headers,
            'body' => $body,
            'timeout_seconds' => $request['timeout_seconds'] ?? null,
        ]);

        if ($transportResult['transport_status'] !== 'Normalized') {
            return $this->deliveryFailed($stages, $providerRef, $transportResult['error'] ?? sprintf('Outbound webhook delivery did not reach a transport response (status: %s).', $transportResult['transport_status']), $transportResult['status_code']);
        }

        $statusCode = $transportResult['status_code'];

        if ($statusCode === null || $statusCode < 200 || $statusCode > 299) {
            return $this->deliveryFailed($stages, $providerRef, sprintf('External system returned status code %s for the outbound webhook delivery.', $statusCode ?? 'null'), $statusCode);
        }

        $stages[] = 'Delivered';
        $this->emit('Delivered', $providerRef, null);

        return [
            'webhook_status' => 'Delivered',
            'stages' => $stages,
            'provider_ref' => $providerRef,
            'status_code' => $statusCode,
            'error' => null,
        ];
    }

    /**
     * @param array<string, mixed> $request
     * @return null|array{0: string, 1: array<string, string>}
     */
    private function translatedPayload(array $request, ?Closure $translate): ?array
    {
        if (is_string($request['body'] ?? null) && $request['body'] !== '') {
            /** @var array<string, string> $headers */
            $headers = is_array($request['headers'] ?? null) ? $request['headers'] : [];

            return [$request['body'], $headers];
        }

        if ($translate === null || !is_array($request['event'] ?? null)) {
            return null;
        }

        $translated = $translate($request['event']);
        $body = $translated['body'] ?? null;

        if (!is_string($body) || $body === '') {
            return null;
        }

        /** @var array<string, string> $headers */
        $headers = is_array($translated['headers'] ?? null) ? $translated['headers'] : [];

        return [$body, $headers];
    }

    private function signatureError(string $rawBody, ?string $providedSignature, ?string $secret, string $algo): ?string
    {
        if ($secret === null || $secret === '') {
            return null;
        }

        if ($providedSignature === null || $providedSignature === '') {
            return 'Required webhook signature reference is missing.';
        }

        if (!in_array($algo, hash_algos(), true)) {
            return sprintf('Webhook signature algorithm "%s" is not supported.', $algo);
        }

        $expected = hash_hmac($algo, $rawBody, $secret);
        $candidate = str_contains($providedSignature, '=')
            ? substr($providedSignature, strrpos($providedSignature, '=') + 1)
            : $providedSignature;

        return hash_equals($expected, $candidate)
            ? null
            : 'Webhook signature does not match the expected value for the configured secret.';
    }

    private function timestampError(mixed $timestamp, int $toleranceSeconds, DateTimeImmutable $now): ?string
    {
        if ($timestamp === null || $timestamp === '') {
            return null;
        }

        $timestampValue = is_numeric($timestamp) ? (int) $timestamp : strtotime((string) $timestamp);

        if ($timestampValue === false) {
            return 'Webhook timestamp reference could not be parsed.';
        }

        if (abs($now->getTimestamp() - $timestampValue) > $toleranceSeconds) {
            return sprintf('Webhook timestamp is outside the allowed %d-second tolerance window.', $toleranceSeconds);
        }

        return null;
    }

    /**
     * @param array<int, string> $stages
     * @return array{webhook_status: string, stages: array<int, string>, event_ref: ?string, provider_ref: ?string, payload: ?array<string, mixed>, error: ?string}
     */
    private function rejected(array $stages, mixed $providerRef, string $reason): array
    {
        $providerRef = is_string($providerRef) ? $providerRef : null;
        $stages[] = 'Rejected';
        $this->emit('Rejected', $providerRef, null);

        return [
            'webhook_status' => 'Rejected',
            'stages' => $stages,
            'event_ref' => null,
            'provider_ref' => $providerRef,
            'payload' => null,
            'error' => $reason,
        ];
    }

    /**
     * @param array<int, string> $stages
     * @return array{webhook_status: string, stages: array<int, string>, provider_ref: ?string, status_code: ?int, error: ?string}
     */
    private function deliveryFailed(array $stages, mixed $providerRef, string $reason, ?int $statusCode = null): array
    {
        $providerRef = is_string($providerRef) ? $providerRef : null;
        $stages[] = 'Delivery Failed';
        $this->emit('Delivery Failed', $providerRef, null);

        return [
            'webhook_status' => 'Delivery Failed',
            'stages' => $stages,
            'provider_ref' => $providerRef,
            'status_code' => $statusCode,
            'error' => $reason,
        ];
    }

    private function emit(string $status, ?string $providerRef, ?string $eventRef): void
    {
        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'webhook_manager.' . strtolower(str_replace(' ', '_', $status)),
            new DateTimeImmutable(),
            self::class,
            ['status' => $status, 'provider_ref' => $providerRef, 'event_ref' => $eventRef]
        ));
    }
}
