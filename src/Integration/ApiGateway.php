<?php

declare(strict_types=1);

namespace SquirrelForge\Integration;

use DateTimeImmutable;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Contracts\HttpTransportInterface;
use SquirrelForge\Events\Event;
use Throwable;

/**
 * Handles approved API ingress/egress protocol normalization for a single
 * outbound API handoff, per 26_INTEGRATIONS/API-GATEWAY.md -- the sixth
 * real component in 26_INTEGRATIONS.
 *
 * The spec's own Boundary excludes "connector registry records or
 * connector lifecycle state (CONNECTOR-MANAGER.md)" and "integration
 * routing or business routing decisions (INTEGRATION-MANAGER.md)" from
 * this component. Resolving what a connector's endpoint actually IS is
 * therefore not this class's job -- `endpoint_ref` arrives here already
 * resolved to a concrete URL by whichever Connector Manager/Service
 * Discovery-consulting caller assembled the request, the same
 * "opaque, caller-supplied reference" boundary `IntegrationAuthentication`
 * already draws around `credential_ref`. What this class actually owns
 * per Rule 4 ("may perform transport-level protocol and schema checks
 * only") is real: validating HTTP method and timeout shape, normalizing
 * query parameters onto the endpoint reference, and -- unlike most of
 * this layer's coordinators, which take a caller-supplied `Closure` for
 * "one fixed wire protocol would fabricate a choice this spec never
 * makes" -- actually sending the request. API Gateway is the one place
 * in this layer where a single wire protocol (HTTP) genuinely is the
 * spec's own subject matter ("API ingress and egress protocol
 * handling"), so it composes the real, already-built
 * `HttpTransportInterface` (the same contract `HttpEngineClient` already
 * uses for the Flock adapter) instead of accepting another Closure.
 * Omitting a transport entirely still exercises every real readiness,
 * credential, and rate-limit check and terminates at `Ready` -- the same
 * "dry run without fabricating an external outcome" shape
 * `IntegrationManager::coordinate()` gives a `null` `$handoff`.
 *
 * Rule 2 ("must use references for endpoints, credentials, configuration,
 * governance, and rate limits") and Rule 3 ("must not store raw secrets
 * in requests, responses, logs, or metadata") are upheld the same way
 * `IntegrationAuthentication` protects its token: `credential_authorized`
 * is caller-supplied boolean evidence consumed from the owning security/
 * runtime-config component, never a credential value this class inspects,
 * and every event this class emits carries only `endpoint_ref`/status
 * fields -- headers and body, which may carry secrets in transit, are
 * returned directly to the one caller who made the request and never
 * placed in an observability payload.
 *
 * Rule 5 ("may return retryable transport status, but retry and recovery
 * decisions belong to execution and coordination owners") and Rule 7
 * ("must not mark business outcomes valid or integration tasks
 * complete") are upheld by this class never retrying a failed send and
 * never returning anything beyond a transport-level `Normalized`/
 * `Transport Failed` status -- the caller (`INTEGRATION-MANAGER.md` or
 * an approved Integration component) remains responsible for every
 * next-state decision, matching `IntegrationManager::runHandoff()`'s own
 * "External Failure Reported" being reported, never resolved, here.
 *
 * Owns no database, matching this layer's other pure coordinators
 * (`IntegrationAuthentication`, `IntegrationManager`): Rule 6 requires
 * observability events through `27_OBSERVABILITY`, not separate logging
 * or audit infrastructure this class would otherwise need a table for.
 */
final class ApiGateway
{
    private const KNOWN_METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'];

    public function __construct(
        private readonly ?HttpTransportInterface $transport = null,
        private readonly ?EventBusInterface $events = null
    ) {
    }

    /**
     * @param array{
     *     endpoint_ref?: ?string,
     *     method?: ?string,
     *     headers?: array<string, string>,
     *     query?: array<string, scalar>,
     *     body?: ?string,
     *     timeout_seconds?: ?float,
     *     credential_authorized?: bool,
     *     credential_status?: ?string,
     *     rate_limited?: bool,
     *     rate_limit_status?: ?string
     * } $request
     * @return array{
     *     transport_status: string,
     *     stages: array<int, string>,
     *     endpoint_ref: ?string,
     *     method: ?string,
     *     status_code: ?int,
     *     headers: array<string, string>,
     *     body: ?string,
     *     error: ?string
     * }
     */
    public function send(array $request): array
    {
        $stages = [];
        $endpointRef = $request['endpoint_ref'] ?? null;
        $method = is_string($request['method'] ?? null) ? strtoupper($request['method']) : null;
        $timeoutSeconds = $request['timeout_seconds'] ?? 30.0;

        $shapeError = $this->requestShapeError($endpointRef, $method, $timeoutSeconds);

        if ($shapeError !== null) {
            return $this->blocked($stages, 'Request Invalid', $endpointRef, $method, $shapeError);
        }

        $stages[] = 'Ready';
        $this->emit('Ready', $endpointRef, $method);

        if (($request['credential_authorized'] ?? true) === false) {
            return $this->blocked(
                $stages,
                'Credential Blocked',
                $endpointRef,
                $method,
                $request['credential_status'] ?? 'Required credential or authentication status was not approved.'
            );
        }

        if (($request['rate_limited'] ?? false) === true) {
            return $this->blocked(
                $stages,
                'Rate Limited',
                $endpointRef,
                $method,
                $request['rate_limit_status'] ?? 'Configured API transport throttling blocked this request.'
            );
        }

        if ($this->transport === null) {
            return [
                'transport_status' => 'Ready',
                'stages' => $stages,
                'endpoint_ref' => $endpointRef,
                'method' => $method,
                'status_code' => null,
                'headers' => [],
                'body' => null,
                'error' => null,
            ];
        }

        return $this->runTransport($stages, $request, $endpointRef, $method, (float) $timeoutSeconds);
    }

    /**
     * @param array<int, string> $stages
     * @param array<string, mixed> $request
     * @return array{
     *     transport_status: string,
     *     stages: array<int, string>,
     *     endpoint_ref: ?string,
     *     method: ?string,
     *     status_code: ?int,
     *     headers: array<string, string>,
     *     body: ?string,
     *     error: ?string
     * }
     */
    private function runTransport(array $stages, array $request, string $endpointRef, string $method, float $timeoutSeconds): array
    {
        $stages[] = 'Sent';
        $this->emit('Sent', $endpointRef, $method);

        $url = $this->buildUrl($endpointRef, is_array($request['query'] ?? null) ? $request['query'] : []);
        /** @var array<string, string> $headers */
        $headers = is_array($request['headers'] ?? null) ? $request['headers'] : [];
        $body = is_string($request['body'] ?? null) ? $request['body'] : null;

        try {
            $response = $this->transport->request($method, $url, $headers, $body, $timeoutSeconds);
        } catch (Throwable $e) {
            $stages[] = 'Transport Failed';
            $this->emit('Transport Failed', $endpointRef, $method);

            return [
                'transport_status' => 'Transport Failed',
                'stages' => $stages,
                'endpoint_ref' => $endpointRef,
                'method' => $method,
                'status_code' => null,
                'headers' => [],
                'body' => null,
                'error' => $e->getMessage(),
            ];
        }

        $stages[] = 'Response Received';
        $this->emit('Response Received', $endpointRef, $method);

        $stages[] = 'Normalized';
        $this->emit('Normalized', $endpointRef, $method);

        return [
            'transport_status' => 'Normalized',
            'stages' => $stages,
            'endpoint_ref' => $endpointRef,
            'method' => $method,
            'status_code' => $response->status,
            'headers' => $response->headers,
            'body' => $response->body,
            'error' => null,
        ];
    }

    private function requestShapeError(mixed $endpointRef, ?string $method, mixed $timeoutSeconds): ?string
    {
        if (!is_string($endpointRef) || $endpointRef === '') {
            return 'Request is missing a required endpoint_ref.';
        }

        if ($method === null || !in_array($method, self::KNOWN_METHODS, true)) {
            return sprintf('Request method "%s" is not a recognized transport-level HTTP method.', (string) ($method ?? ''));
        }

        if (!is_numeric($timeoutSeconds) || (float) $timeoutSeconds <= 0) {
            return 'Request timeout_seconds must be a positive number.';
        }

        return null;
    }

    /**
     * @param array<string, scalar> $query
     */
    private function buildUrl(string $endpointRef, array $query): string
    {
        if ($query === []) {
            return $endpointRef;
        }

        $separator = str_contains($endpointRef, '?') ? '&' : '?';

        return $endpointRef . $separator . http_build_query($query);
    }

    /**
     * @param array<int, string> $stages
     * @return array{
     *     transport_status: string,
     *     stages: array<int, string>,
     *     endpoint_ref: ?string,
     *     method: ?string,
     *     status_code: ?int,
     *     headers: array<string, string>,
     *     body: ?string,
     *     error: ?string
     * }
     */
    private function blocked(array $stages, string $status, mixed $endpointRef, ?string $method, string $reason): array
    {
        $endpointRef = is_string($endpointRef) ? $endpointRef : null;
        $stages[] = $status;
        $this->emit($status, $endpointRef, $method);

        return [
            'transport_status' => $status,
            'stages' => $stages,
            'endpoint_ref' => $endpointRef,
            'method' => $method,
            'status_code' => null,
            'headers' => [],
            'body' => null,
            'error' => $reason,
        ];
    }

    private function emit(string $status, ?string $endpointRef, ?string $method): void
    {
        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'api_gateway.' . strtolower(str_replace(' ', '_', $status)),
            new DateTimeImmutable(),
            self::class,
            ['status' => $status, 'endpoint_ref' => $endpointRef, 'method' => $method]
        ));
    }
}
