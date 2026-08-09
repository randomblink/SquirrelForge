<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\RuntimeConfig;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Engine\EngineValidation;
use SquirrelForge\RuntimeConfig\SqliteConfigurationAudit;
use SquirrelForge\RuntimeConfig\SqliteConfigurationRegistry;
use SquirrelForge\RuntimeConfig\SqliteConfigurationValidator;
use SquirrelForge\RuntimeConfig\SqliteEnvironments;
use SquirrelForge\RuntimeConfig\SqliteFeatureFlags;
use SquirrelForge\RuntimeConfig\SqlitePolicyConfiguration;
use SquirrelForge\RuntimeConfig\SqliteRuntimeConfiguration;

final class SqliteRuntimeConfigurationTest extends TestCase
{
    /** @var array<int, string> */
    private array $databasePaths = [];

    protected function tearDown(): void
    {
        foreach ($this->databasePaths as $path) {
            foreach ([$path, $path . '-shm', $path . '-wal'] as $candidate) {
                if (is_file($candidate)) {
                    unlink($candidate);
                }
            }
        }
    }

    private function tempPath(string $label): string
    {
        $path = sys_get_temp_dir() . "/squirrelforge-runtime-configuration-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function registry(): SqliteConfigurationRegistry
    {
        return new SqliteConfigurationRegistry($this->tempPath('registry'), new SqliteConfigurationAudit($this->tempPath('registry-audit')));
    }

    private function validatorFor(SqliteConfigurationRegistry $registry): SqliteConfigurationValidator
    {
        return new SqliteConfigurationValidator($this->tempPath('validator'), new EngineValidation(), $registry);
    }

    /**
     * Registers a base configuration item and drives it to Active,
     * the one Registry state runtime configuration may resolve.
     */
    private function activeConfigurationRef(SqliteConfigurationRegistry $registry): string
    {
        $registered = $registry->register(['name' => 'timeout_seconds', 'owner' => 'x', 'scope' => 'global', 'data_type' => 'integer', 'actor_ref' => 'system']);
        $registry->transition($registered['configuration_ref'], 'Active', 'system');

        return $registered['configuration_ref'];
    }

    /**
     * @return array<string, mixed>
     */
    private function requestFor(string $configurationRef, array $overrides = []): array
    {
        return array_replace(['configuration_ref' => $configurationRef, 'actor_ref' => 'admin_console'], $overrides);
    }

    // --- shape validation ---

    public function testMissingConfigurationRefIsInvalidAndNotPersisted(): void
    {
        $runtimeConfig = new SqliteRuntimeConfiguration($this->tempPath('db'));

        $result = $runtimeConfig->resolve(['actor_ref' => 'admin_console']);

        $this->assertSame('invalid', $result['outcome']);
        $this->assertNull($result['bundle_ref']);
    }

    public function testMissingActorRefIsInvalid(): void
    {
        $runtimeConfig = new SqliteRuntimeConfiguration($this->tempPath('db'));

        $result = $runtimeConfig->resolve(['configuration_ref' => 'config_1']);

        $this->assertSame('invalid', $result['outcome']);
    }

    // --- fail-closed on unconfigured/missing base authorities ---

    public function testNoRegistryComposedResolvesInvalid(): void
    {
        $runtimeConfig = new SqliteRuntimeConfiguration($this->tempPath('db'));

        $result = $runtimeConfig->resolve($this->requestFor('config_1'));

        $this->assertSame('resolved', $result['outcome']);
        $this->assertSame('Invalid', $result['state']);
    }

    public function testUnregisteredConfigurationRefIsInvalid(): void
    {
        $registry = $this->registry();
        $runtimeConfig = new SqliteRuntimeConfiguration($this->tempPath('db'), $registry);

        $result = $runtimeConfig->resolve($this->requestFor('ghost_config'));

        $this->assertSame('Invalid', $result['state']);
        $this->assertStringContainsString('not a registered configuration item', $result['error']);
    }

    public function testConfigurationNotYetActiveIsInvalid(): void
    {
        $registry = $this->registry();
        $registered = $registry->register(['name' => 'timeout_seconds', 'owner' => 'x', 'scope' => 'global', 'data_type' => 'integer', 'actor_ref' => 'system']);
        $runtimeConfig = new SqliteRuntimeConfiguration($this->tempPath('db'), $registry);

        $result = $runtimeConfig->resolve($this->requestFor($registered['configuration_ref']));

        $this->assertSame('Invalid', $result['state']);
        $this->assertStringContainsString('not Active in the registry', $result['error']);
    }

    // --- a bare, valid bundle resolves Active ---

    public function testBareActiveConfigurationResolvesActive(): void
    {
        $registry = $this->registry();
        $configRef = $this->activeConfigurationRef($registry);
        $runtimeConfig = new SqliteRuntimeConfiguration($this->tempPath('db'), $registry, null, null, null, $this->validatorFor($registry));

        $result = $runtimeConfig->resolve($this->requestFor($configRef));

        $this->assertSame('resolved', $result['outcome']);
        $this->assertSame('Active', $result['state']);
        $this->assertNotNull($result['bundle_ref']);
    }

    public function testNoValidatorComposedIsInvalid(): void
    {
        $registry = $this->registry();
        $configRef = $this->activeConfigurationRef($registry);
        $runtimeConfig = new SqliteRuntimeConfiguration($this->tempPath('db'), $registry);

        $result = $runtimeConfig->resolve($this->requestFor($configRef));

        $this->assertSame('Invalid', $result['state']);
        $this->assertStringContainsString('Configuration Validator', $result['error']);
    }

    // --- environment reference ---

    public function testUnknownEnvironmentIsInvalid(): void
    {
        $registry = $this->registry();
        $configRef = $this->activeConfigurationRef($registry);
        $environments = new SqliteEnvironments($this->tempPath('env'), $registry);
        $runtimeConfig = new SqliteRuntimeConfiguration($this->tempPath('db'), $registry, $environments, null, null, $this->validatorFor($registry));

        $result = $runtimeConfig->resolve($this->requestFor($configRef, ['environment_id' => 'ghost_env']));

        $this->assertSame('Invalid', $result['state']);
        $this->assertStringContainsString('Environment', $result['error']);
    }

    public function testRealEnvironmentIsAccepted(): void
    {
        $registry = $this->registry();
        $configRef = $this->activeConfigurationRef($registry);
        $environments = new SqliteEnvironments($this->tempPath('env'), $registry);
        $env = $environments->registerProfile(['name' => 'production', 'actor_ref' => 'admin_console']);
        $runtimeConfig = new SqliteRuntimeConfiguration($this->tempPath('db'), $registry, $environments, null, null, $this->validatorFor($registry));

        $result = $runtimeConfig->resolve($this->requestFor($configRef, ['environment_id' => $env['environment_id']]));

        $this->assertSame('Active', $result['state']);
    }

    // --- feature flag references ---

    public function testUnregisteredFeatureFlagIsInvalid(): void
    {
        $registry = $this->registry();
        $configRef = $this->activeConfigurationRef($registry);
        $featureFlags = new SqliteFeatureFlags($this->tempPath('flags'), $registry);
        $runtimeConfig = new SqliteRuntimeConfiguration($this->tempPath('db'), $registry, null, $featureFlags, null, $this->validatorFor($registry));

        $result = $runtimeConfig->resolve($this->requestFor($configRef, ['feature_flag_ids' => ['ghost_flag']]));

        $this->assertSame('Invalid', $result['state']);
        $this->assertStringContainsString('Feature flag', $result['error']);
    }

    public function testRealFeatureFlagIsAccepted(): void
    {
        $registry = $this->registry();
        $configRef = $this->activeConfigurationRef($registry);
        $featureFlags = new SqliteFeatureFlags($this->tempPath('flags'), $registry);
        $flag = $featureFlags->registerFlag(['name' => 'new_checkout', 'actor_ref' => 'admin_console']);
        $runtimeConfig = new SqliteRuntimeConfiguration($this->tempPath('db'), $registry, null, $featureFlags, null, $this->validatorFor($registry));

        $result = $runtimeConfig->resolve($this->requestFor($configRef, ['feature_flag_ids' => [$flag['flag_id']]]));

        $this->assertSame('Active', $result['state']);
    }

    // --- policy configuration references ---

    public function testUnregisteredPolicyConfigurationRefIsInvalid(): void
    {
        $registry = $this->registry();
        $configRef = $this->activeConfigurationRef($registry);
        $policyConfig = new SqlitePolicyConfiguration($this->tempPath('policy'), $registry);
        $runtimeConfig = new SqliteRuntimeConfiguration($this->tempPath('db'), $registry, null, null, $policyConfig, $this->validatorFor($registry));

        $result = $runtimeConfig->resolve($this->requestFor($configRef, ['policy_configuration_refs' => ['ghost_policy']]));

        $this->assertSame('Invalid', $result['state']);
        $this->assertStringContainsString('Policy configuration reference', $result['error']);
    }

    public function testRealPolicyConfigurationRefIsAccepted(): void
    {
        $registry = $this->registry();
        $configRef = $this->activeConfigurationRef($registry);
        $policyConfig = new SqlitePolicyConfiguration($this->tempPath('policy'), $registry);
        $policy = $policyConfig->registerValue(['name' => 'max_retries', 'category' => 'resilience', 'owner' => 'x', 'scope' => 'global', 'value' => 3, 'version_ref' => 'v1', 'actor_ref' => 'admin_console']);
        $runtimeConfig = new SqliteRuntimeConfiguration($this->tempPath('db'), $registry, null, null, $policyConfig, $this->validatorFor($registry));

        $result = $runtimeConfig->resolve($this->requestFor($configRef, ['policy_configuration_refs' => [$policy['policy_config_ref']]]));

        $this->assertSame('Active', $result['state']);
    }

    // --- secret references: presence-only, fed into the real validator ---

    public function testEmptySecretRefIsInvalid(): void
    {
        $registry = $this->registry();
        $configRef = $this->activeConfigurationRef($registry);
        $runtimeConfig = new SqliteRuntimeConfiguration($this->tempPath('db'), $registry, null, null, null, $this->validatorFor($registry));

        $result = $runtimeConfig->resolve($this->requestFor($configRef, ['secret_refs' => ['']]));

        $this->assertSame('Invalid', $result['state']);
    }

    public function testNonEmptySecretRefIsAccepted(): void
    {
        $registry = $this->registry();
        $configRef = $this->activeConfigurationRef($registry);
        $runtimeConfig = new SqliteRuntimeConfiguration($this->tempPath('db'), $registry, null, null, null, $this->validatorFor($registry));

        $result = $runtimeConfig->resolve($this->requestFor($configRef, ['secret_refs' => ['secret_api_key']]));

        $this->assertSame('Active', $result['state']);
    }

    // --- refresh() ---

    public function testRefreshOnUnknownBundleIsInvalid(): void
    {
        $runtimeConfig = new SqliteRuntimeConfiguration($this->tempPath('db'));

        $result = $runtimeConfig->refresh('ghost', 'admin_console');

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testRefreshReResolvesToTheSameState(): void
    {
        $registry = $this->registry();
        $configRef = $this->activeConfigurationRef($registry);
        $runtimeConfig = new SqliteRuntimeConfiguration($this->tempPath('db'), $registry, null, null, null, $this->validatorFor($registry));
        $resolved = $runtimeConfig->resolve($this->requestFor($configRef));

        $result = $runtimeConfig->refresh($resolved['bundle_ref'], 'admin_console');

        $this->assertSame('refreshed', $result['outcome']);
        $this->assertSame('Active', $result['state']);
    }

    public function testExpiredBundleCannotBeRefreshed(): void
    {
        $registry = $this->registry();
        $configRef = $this->activeConfigurationRef($registry);
        $runtimeConfig = new SqliteRuntimeConfiguration($this->tempPath('db'), $registry, null, null, null, $this->validatorFor($registry));
        $resolved = $runtimeConfig->resolve($this->requestFor($configRef));
        $runtimeConfig->expire($resolved['bundle_ref'], 'admin_console');

        $result = $runtimeConfig->refresh($resolved['bundle_ref'], 'admin_console');

        $this->assertSame('rejected', $result['outcome']);
    }

    // --- expire() ---

    public function testExpireOnUnknownBundleIsInvalid(): void
    {
        $runtimeConfig = new SqliteRuntimeConfiguration($this->tempPath('db'));

        $result = $runtimeConfig->expire('ghost', 'admin_console');

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testExpireSucceeds(): void
    {
        $registry = $this->registry();
        $configRef = $this->activeConfigurationRef($registry);
        $runtimeConfig = new SqliteRuntimeConfiguration($this->tempPath('db'), $registry, null, null, null, $this->validatorFor($registry));
        $resolved = $runtimeConfig->resolve($this->requestFor($configRef));

        $result = $runtimeConfig->expire($resolved['bundle_ref'], 'admin_console', 'superseded by v2');

        $this->assertSame('expired', $result['outcome']);
        $this->assertSame('Expired', $result['state']);
        $record = $runtimeConfig->get($resolved['bundle_ref']);
        $this->assertSame('superseded by v2', $record['error']);
    }

    // --- get() / history() ---

    public function testGetUnknownBundleReturnsNull(): void
    {
        $runtimeConfig = new SqliteRuntimeConfiguration($this->tempPath('db'));

        $this->assertNull($runtimeConfig->get('ghost'));
    }

    public function testHistoryPreservesEveryResolutionForAConfigurationItem(): void
    {
        $registry = $this->registry();
        $configRef = $this->activeConfigurationRef($registry);
        $runtimeConfig = new SqliteRuntimeConfiguration($this->tempPath('db'), $registry, null, null, null, $this->validatorFor($registry));
        $runtimeConfig->resolve($this->requestFor($configRef));
        $runtimeConfig->resolve($this->requestFor($configRef));

        $history = $runtimeConfig->history($configRef);

        $this->assertCount(2, $history);
    }
}
