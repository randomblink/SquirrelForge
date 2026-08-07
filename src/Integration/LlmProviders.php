<?php

declare(strict_types=1);

namespace SquirrelForge\Integration;

use Closure;
use DateTimeImmutable;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;
use Throwable;

/**
 * Maintains external AI/LLM provider metadata and coordinates a single
 * provider call's credential handshake and transport-status
 * normalization, per 26_INTEGRATIONS/LLM-PROVIDERS.md -- the ninth real
 * component in 26_INTEGRATIONS, and the third (after `IntegrationManager`,
 * `WebhookManager`) to genuinely compose another component of this layer.
 *
 * This spec is not a duplicate of the already-real `LlmClientInterface`/
 * `AnthropicClient`/`LlmClientResolver` in `src/Llm/` -- those exist to
 * get a completion for `Reasoner`, one fixed client per agent, with no
 * metadata registry, credential coordination, or provider-status
 * vocabulary of their own. This spec's own Boundary explicitly excludes
 * "model selection, provider selection, fallback model routing, or
 * routing policy (`34_AIDRIVER/MODEL-ROUTER.md`)" and "reasoning
 * decisions... (`19_REASONING`)" -- the gap this class actually closes is
 * the metadata registry ("record and expose provider metadata for
 * owners that make routing decisions") and the call-coordination
 * envelope (credential handshake + normalized status) around whatever
 * client the caller supplies, not another way to get a completion.
 *
 * Rule 1 ("provider-specific request formatting and response parsing
 * must remain inside provider clients") is upheld the same way every
 * other coordinator in this layer keeps provider mechanics out of
 * itself: `$client` is a caller-supplied `Closure` that performs the
 * real translation and transport call, and -- because only that client
 * genuinely knows which of its own provider's errors mean "rate
 * limited" versus "quota exceeded" versus "unavailable" -- it also
 * reports which of this spec's seven Provider States the outcome maps
 * to, rather than this class guessing from an HTTP status code the way
 * `ApiGateway` safely can for the fully generic HTTP-transport case.
 * This class's own real contribution is the safety net: an unrecognized
 * or missing `status` value never fabricates `Available`, it falls back
 * to `Unavailable` -- the same "don't fabricate an outcome" discipline
 * `IntegrationAuthentication` applies to a handshake that returns
 * neither a token nor an error.
 *
 * "Coordinate provider credential handshakes using approved credential
 * references from security and runtime-configuration owners" is real
 * composition of the already-built `IntegrationAuthentication`, the same
 * "delegate to the real, already-built component" reuse
 * `WebhookManager::deliverOutbound()` already established for outbound
 * signing. A provider registered with a `credential_ref` that fails its
 * handshake never reaches the client closure at all -- consistent with
 * Rule 3's approved-references-only requirement.
 *
 * `Configuration Invalid` ("provider endpoint, model, or credential
 * reference is missing or invalid") is checked before any handshake or
 * client call: an unregistered provider, a provider missing its
 * required `endpoint_ref`, or a requested model absent from a
 * non-empty `supported_models` list all fail closed here, never reaching
 * the external call.
 *
 * The metadata registry is in-memory, matching `ToolRegistry`'s own
 * shape for the same reason: Rule 6 requires observability events
 * through `27_OBSERVABILITY`, not this component owning persistence --
 * provider metadata is runtime registration state, not the kind of
 * audit record `37_STORAGE` owns. Rule 2 ("raw secrets must never be
 * stored in provider client definitions or provider metadata") is
 * upheld by the registry only ever holding a `credential_ref`, never a
 * secret value, and by every event this class emits carrying only
 * `provider_id`/status fields -- response and usage data, which may
 * carry prompt or completion content, are returned directly to the
 * caller and never placed in an observability payload.
 */
final class LlmProviders
{
    private const PROVIDER_STATES = [
        'Available', 'Degraded', 'Unavailable', 'Rate Limited', 'Quota Exceeded', 'Authentication Failed', 'Configuration Invalid',
    ];

    /** @var array<string, array{provider_id: string, provider_name: string, endpoint_ref: string, supported_models: array<int, string>, capability_metadata: array<string, mixed>, credential_ref: ?string, transport_limits: array<string, mixed>}> */
    private array $providers = [];

    public function __construct(
        private readonly ?IntegrationAuthentication $authentication = null,
        private readonly ?EventBusInterface $events = null
    ) {
    }

    /**
     * @param array{
     *     provider_id?: ?string,
     *     provider_name?: ?string,
     *     endpoint_ref?: ?string,
     *     supported_models?: array<int, string>,
     *     capability_metadata?: array<string, mixed>,
     *     credential_ref?: ?string,
     *     transport_limits?: array<string, mixed>
     * } $metadata
     * @return array{outcome: string, provider_id: ?string, error: ?string}
     */
    public function register(array $metadata): array
    {
        $providerId = $metadata['provider_id'] ?? null;
        $providerName = $metadata['provider_name'] ?? null;
        $endpointRef = $metadata['endpoint_ref'] ?? null;

        if (!is_string($providerId) || $providerId === '') {
            return ['outcome' => 'invalid', 'provider_id' => null, 'error' => 'Provider registration requires a non-empty provider_id.'];
        }

        if (!is_string($providerName) || $providerName === '' || !is_string($endpointRef) || $endpointRef === '') {
            return ['outcome' => 'invalid', 'provider_id' => $providerId, 'error' => 'Provider registration requires a non-empty provider_name and endpoint_ref.'];
        }

        $this->providers[$providerId] = [
            'provider_id' => $providerId,
            'provider_name' => $providerName,
            'endpoint_ref' => $endpointRef,
            'supported_models' => is_array($metadata['supported_models'] ?? null) ? array_values($metadata['supported_models']) : [],
            'capability_metadata' => is_array($metadata['capability_metadata'] ?? null) ? $metadata['capability_metadata'] : [],
            'credential_ref' => is_string($metadata['credential_ref'] ?? null) ? $metadata['credential_ref'] : null,
            'transport_limits' => is_array($metadata['transport_limits'] ?? null) ? $metadata['transport_limits'] : [],
        ];

        return ['outcome' => 'registered', 'provider_id' => $providerId, 'error' => null];
    }

    /**
     * @return ?array{provider_id: string, provider_name: string, endpoint_ref: string, supported_models: array<int, string>, capability_metadata: array<string, mixed>, credential_ref: ?string, transport_limits: array<string, mixed>}
     */
    public function get(string $providerId): ?array
    {
        return $this->providers[$providerId] ?? null;
    }

    /**
     * @return array<int, array{provider_id: string, provider_name: string, endpoint_ref: string, supported_models: array<int, string>, capability_metadata: array<string, mixed>, credential_ref: ?string, transport_limits: array<string, mixed>}>
     */
    public function list(): array
    {
        return array_values($this->providers);
    }

    /**
     * Coordinates a single provider call: resolves registered metadata,
     * coordinates the credential handshake through IntegrationAuthentication
     * when the provider requires one, invokes the caller-supplied
     * provider client, and normalizes the outcome into this spec's own
     * Provider States.
     *
     * @param array{model?: ?string, authorized?: bool} $request
     * @param ?Closure $client (array $provider, array $request): array{response?: mixed, usage?: ?array<string, mixed>, status?: ?string, error?: ?string} the real, provider-specific translation and transport call. Omitting it leaves the result at `Available` once metadata and credentials are ready, without fabricating a response.
     * @param ?Closure $signHandshake forwarded verbatim to IntegrationAuthentication::authenticate() when the provider has a credential_ref.
     * @return array{status: string, provider_id: string, response: mixed, usage: ?array<string, mixed>, error: ?string}
     */
    public function call(string $providerId, array $request = [], ?Closure $client = null, ?Closure $signHandshake = null): array
    {
        $provider = $this->providers[$providerId] ?? null;

        if ($provider === null) {
            return $this->result('Configuration Invalid', $providerId, null, null, sprintf('Provider "%s" is not registered.', $providerId));
        }

        $model = $request['model'] ?? null;

        if ($model !== null && $provider['supported_models'] !== [] && !in_array($model, $provider['supported_models'], true)) {
            return $this->result('Configuration Invalid', $providerId, null, null, sprintf('Model "%s" is not in provider "%s"\'s supported_models.', $model, $providerId));
        }

        if ($provider['credential_ref'] !== null) {
            if ($this->authentication === null) {
                return $this->result('Authentication Failed', $providerId, null, null, sprintf('Provider "%s" requires a credential handshake but no IntegrationAuthentication component is configured.', $providerId));
            }

            $authResult = $this->authentication->authenticate(
                $providerId,
                ['credential_ref' => $provider['credential_ref'], 'authorized' => $request['authorized'] ?? true],
                $signHandshake
            );

            if ($authResult['status'] !== 'valid') {
                return $this->result('Authentication Failed', $providerId, null, null, $authResult['error'] ?? sprintf('Provider "%s" credential handshake did not reach a valid state (status: %s).', $providerId, $authResult['status']));
            }
        }

        if ($client === null) {
            return $this->result('Available', $providerId, null, null, null);
        }

        try {
            $outcome = $client($provider, $request);
        } catch (Throwable $e) {
            return $this->result('Unavailable', $providerId, null, null, $e->getMessage());
        }

        $status = $outcome['status'] ?? null;
        $error = $outcome['error'] ?? null;

        if (!is_string($status) || !in_array($status, self::PROVIDER_STATES, true)) {
            $status = $error !== null ? 'Unavailable' : 'Available';
        }

        $usage = is_array($outcome['usage'] ?? null) ? $outcome['usage'] : null;

        return $this->result($status, $providerId, $outcome['response'] ?? null, $usage, $error);
    }

    /**
     * @return array{status: string, provider_id: string, response: mixed, usage: ?array<string, mixed>, error: ?string}
     */
    private function result(string $status, string $providerId, mixed $response, ?array $usage, ?string $error): array
    {
        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'llm_providers.' . strtolower(str_replace(' ', '_', $status)),
            new DateTimeImmutable(),
            self::class,
            ['status' => $status, 'provider_id' => $providerId, 'error' => $error]
        ));

        return [
            'status' => $status,
            'provider_id' => $providerId,
            'response' => $response,
            'usage' => $usage,
            'error' => $error,
        ];
    }
}
