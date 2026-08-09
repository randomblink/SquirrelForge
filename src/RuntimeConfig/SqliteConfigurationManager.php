<?php

declare(strict_types=1);

namespace SquirrelForge\RuntimeConfig;

use DateTimeImmutable;
use Throwable;

/**
 * Coordinates runtime-configuration requests across the Runtime
 * Configuration Layer -- routing registration, lookup, validation,
 * resolution, secret-reference, policy-configuration, feature-flag,
 * environment, and audit-history requests to the owning component and
 * aggregating configuration-domain status references -- per
 * 28_RUNTIME-CONFIG/CONFIGURATION-MANAGER.md, the eighth and final
 * real component in `28_RUNTIME-CONFIG`'s gap, closing the cluster.
 *
 * "Configuration Manager must route requests to the owning Runtime
 * Configuration component" (Rule 1) and "does not own every
 * configuration record directly" (Purpose) are upheld literally: this
 * class owns no database of its own -- the same shape
 * `IntegrationManager` already established for `26_INTEGRATIONS`'s own
 * top-level coordinator -- and every one of its eight domains
 * (`registry`/`environments`/`runtime_configuration`/`feature_flags`/
 * `policy_configuration`/`secrets`/`validator`/`audit`) dispatches to
 * the exact real method its owning sibling already exposes. Nothing is
 * reimplemented here, and an operation not on that sibling's own real,
 * whitelisted method set is rejected rather than improvised.
 *
 * The `secrets` domain routes to the pre-existing `SqliteSecretsManager`
 * (the same class `SqliteRuntimeConfiguration` deliberately did not
 * compose, since it exposes no metadata-only read accessor -- but its
 * real write operations, API-key registration/rotation/revocation, are
 * exactly what "route secret-reference requests" means). That class
 * throws on invalid input rather than returning an outcome envelope,
 * the one real interface mismatch among the eight domains; this class
 * catches that and normalizes it into the same `rejected` envelope
 * every other domain already returns, rather than letting an
 * uncaught exception break the coordinator's own consistent contract.
 *
 * "Configuration Manager may aggregate status and evidence references
 * only" (Rule 2) is upheld by `aggregateStatus()`: it reads the real
 * Registry lifecycle state, the real Configuration Audit history
 * count, and the real latest Runtime Configuration bundle state for a
 * given reference, and returns them together -- it never recomputes,
 * reinterprets, or overrides what any of those three real owners
 * already concluded.
 */
final class SqliteConfigurationManager
{
    private const REGISTRY_OPERATIONS = ['register', 'transition', 'get', 'all'];
    private const ENVIRONMENTS_OPERATIONS = ['register_profile', 'update_overlay', 'get', 'history'];
    private const RUNTIME_CONFIGURATION_OPERATIONS = ['resolve', 'refresh', 'expire', 'get', 'history'];
    private const FEATURE_FLAGS_OPERATIONS = ['register_flag', 'transition', 'set_kill_switch', 'evaluate', 'get', 'history'];
    private const POLICY_CONFIGURATION_OPERATIONS = ['register_value', 'validate', 'activate', 'update_value', 'get', 'history'];
    private const SECRETS_OPERATIONS = ['register_api_key', 'revoke', 'rotate_api_key', 'verify_api_key'];
    private const VALIDATOR_OPERATIONS = ['validate', 'get', 'history'];
    private const AUDIT_OPERATIONS = ['record', 'record_rollback', 'get', 'history', 'rollback_history'];

    private const DOMAIN_OPERATIONS = [
        'registry' => self::REGISTRY_OPERATIONS,
        'environments' => self::ENVIRONMENTS_OPERATIONS,
        'runtime_configuration' => self::RUNTIME_CONFIGURATION_OPERATIONS,
        'feature_flags' => self::FEATURE_FLAGS_OPERATIONS,
        'policy_configuration' => self::POLICY_CONFIGURATION_OPERATIONS,
        'secrets' => self::SECRETS_OPERATIONS,
        'validator' => self::VALIDATOR_OPERATIONS,
        'audit' => self::AUDIT_OPERATIONS,
    ];

    public function __construct(
        private readonly ?SqliteConfigurationRegistry $registry = null,
        private readonly ?SqliteEnvironments $environments = null,
        private readonly ?SqliteRuntimeConfiguration $runtimeConfiguration = null,
        private readonly ?SqliteFeatureFlags $featureFlags = null,
        private readonly ?SqlitePolicyConfiguration $policyConfiguration = null,
        private readonly ?SqliteSecretsManager $secretsManager = null,
        private readonly ?SqliteConfigurationValidator $validator = null,
        private readonly ?SqliteConfigurationAudit $configurationAudit = null
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{outcome: string, domain: ?string, operation: ?string, result: mixed, error: ?string}
     */
    public function handle(string $domain, string $operation, array $payload = []): array
    {
        if (!isset(self::DOMAIN_OPERATIONS[$domain])) {
            return $this->envelope('invalid', $domain, $operation, null, sprintf('"%s" is not one of this spec\'s own eight routed domains.', $domain));
        }

        if (!in_array($operation, self::DOMAIN_OPERATIONS[$domain], true)) {
            return $this->envelope('invalid', $domain, $operation, null, sprintf('"%s" is not a supported operation for the "%s" domain.', $operation, $domain));
        }

        $owner = match ($domain) {
            'registry' => $this->registry,
            'environments' => $this->environments,
            'runtime_configuration' => $this->runtimeConfiguration,
            'feature_flags' => $this->featureFlags,
            'policy_configuration' => $this->policyConfiguration,
            'secrets' => $this->secretsManager,
            'validator' => $this->validator,
            'audit' => $this->configurationAudit,
        };

        if ($owner === null) {
            return $this->envelope('rejected', $domain, $operation, null, sprintf('The "%s" domain\'s owning component is not configured.', $domain));
        }

        try {
            $result = $this->dispatch($domain, $operation, $owner, $payload);
        } catch (Throwable $exception) {
            return $this->envelope('rejected', $domain, $operation, null, $exception->getMessage());
        }

        return $this->envelope('routed', $domain, $operation, $result, null);
    }

    /**
     * "Aggregate configuration-domain status and evidence references
     * for callers" -- assembles, never recomputes, what the real
     * Registry, Configuration Audit, and Runtime Configuration owners
     * each already concluded.
     *
     * @return array{
     *     configuration_ref: string,
     *     lifecycle_status: ?string,
     *     history_count: int,
     *     latest_bundle_state: ?string
     * }
     */
    public function aggregateStatus(string $configurationRef): array
    {
        $registryRecord = $this->registry?->get($configurationRef);
        $history = $this->configurationAudit?->history($configurationRef) ?? [];
        $bundleHistory = $this->runtimeConfiguration?->history($configurationRef) ?? [];
        $latestBundle = $bundleHistory === [] ? null : $bundleHistory[count($bundleHistory) - 1];

        return [
            'configuration_ref' => $configurationRef,
            'lifecycle_status' => $registryRecord['state'] ?? null,
            'history_count' => count($history),
            'latest_bundle_state' => $latestBundle['state'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function dispatch(string $domain, string $operation, object $owner, array $payload): mixed
    {
        return match ($domain) {
            'registry' => match ($operation) {
                'register' => $owner->register($payload),
                'transition' => $owner->transition($payload['configuration_ref'], $payload['to_state'], $payload['actor_ref'], $payload['reason'] ?? null),
                'get' => $owner->get($payload['configuration_ref']),
                'all' => $owner->all($payload['state'] ?? null),
            },
            'environments' => match ($operation) {
                'register_profile' => $owner->registerProfile($payload),
                'update_overlay' => $owner->updateOverlay($payload['environment_id'], $payload['overlay_refs'], $payload['actor_ref']),
                'get' => $owner->get($payload['environment_id']),
                'history' => $owner->history($payload['environment_id']),
            },
            'runtime_configuration' => match ($operation) {
                'resolve' => $owner->resolve($payload),
                'refresh' => $owner->refresh($payload['bundle_ref'], $payload['actor_ref']),
                'expire' => $owner->expire($payload['bundle_ref'], $payload['actor_ref'], $payload['reason'] ?? null),
                'get' => $owner->get($payload['bundle_ref']),
                'history' => $owner->history($payload['configuration_ref']),
            },
            'feature_flags' => match ($operation) {
                'register_flag' => $owner->registerFlag($payload),
                'transition' => $owner->transition($payload['flag_id'], $payload['to_state'], $payload['actor_ref'], $payload['reason'] ?? null),
                'set_kill_switch' => $owner->setKillSwitch($payload['flag_id'], $payload['engaged'], $payload['actor_ref'], $payload['reason'] ?? null),
                'evaluate' => $owner->evaluate($payload['flag_id'], $payload['context'] ?? []),
                'get' => $owner->get($payload['flag_id']),
                'history' => $owner->history($payload['flag_id']),
            },
            'policy_configuration' => match ($operation) {
                'register_value' => $owner->registerValue($payload),
                'validate' => $owner->validate($payload['policy_config_ref'], $payload['actor_ref'], $payload['validation_items'] ?? null),
                'activate' => $owner->activate($payload['policy_config_ref'], $payload['actor_ref']),
                'update_value' => $owner->updateValue($payload['policy_config_ref'], $payload['value'], $payload['new_version_ref'], $payload['actor_ref']),
                'get' => $owner->get($payload['policy_config_ref']),
                'history' => $owner->history($payload['policy_config_ref']),
            },
            'secrets' => match ($operation) {
                'register_api_key' => $owner->registerApiKey($payload['identity_ref'], $payload['api_key'], $this->toDateTime($payload['expires_at'] ?? null)),
                'revoke' => $owner->revoke($payload['secret_ref']),
                'rotate_api_key' => $owner->rotateApiKey($payload['secret_ref'], $payload['new_api_key'], $this->toDateTime($payload['expires_at'] ?? null)),
                'verify_api_key' => $owner->verifyApiKey($payload['identity_ref'], $payload['api_key']),
            },
            'validator' => match ($operation) {
                'validate' => $owner->validate($payload),
                'get' => $owner->get($payload['validation_ref']),
                'history' => $owner->history($payload['configuration_ref']),
            },
            'audit' => match ($operation) {
                'record' => $owner->record($payload),
                'record_rollback' => $owner->recordRollback($payload['configuration_ref'], $payload['rollback_request_ref'], $payload['actor_ref'], $payload['rollback_result_ref'] ?? null),
                'get' => $owner->get($payload['history_ref']),
                'history' => $owner->history($payload['configuration_ref']),
                'rollback_history' => $owner->rollbackHistory($payload['configuration_ref']),
            },
        };
    }

    private function toDateTime(?string $value): ?DateTimeImmutable
    {
        return $value === null ? null : new DateTimeImmutable($value);
    }

    /**
     * @return array{outcome: string, domain: ?string, operation: ?string, result: mixed, error: ?string}
     */
    private function envelope(string $outcome, ?string $domain, ?string $operation, mixed $result, ?string $error): array
    {
        return ['outcome' => $outcome, 'domain' => $domain, 'operation' => $operation, 'result' => $result, 'error' => $error];
    }
}
