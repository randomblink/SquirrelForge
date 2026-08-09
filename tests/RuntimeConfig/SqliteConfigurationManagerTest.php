<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\RuntimeConfig;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Engine\EngineValidation;
use SquirrelForge\RuntimeConfig\SqliteConfigurationAudit;
use SquirrelForge\RuntimeConfig\SqliteConfigurationManager;
use SquirrelForge\RuntimeConfig\SqliteConfigurationRegistry;
use SquirrelForge\RuntimeConfig\SqliteConfigurationValidator;
use SquirrelForge\RuntimeConfig\SqliteEnvironments;
use SquirrelForge\RuntimeConfig\SqliteFeatureFlags;
use SquirrelForge\RuntimeConfig\SqlitePolicyConfiguration;
use SquirrelForge\RuntimeConfig\SqliteRuntimeConfiguration;
use SquirrelForge\RuntimeConfig\SqliteSecretsManager;

final class SqliteConfigurationManagerTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-configuration-manager-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    /**
     * @return array{registry: SqliteConfigurationRegistry, environments: SqliteEnvironments, runtimeConfiguration: SqliteRuntimeConfiguration, featureFlags: SqliteFeatureFlags, policyConfiguration: SqlitePolicyConfiguration, secretsManager: SqliteSecretsManager, validator: SqliteConfigurationValidator, audit: SqliteConfigurationAudit, manager: SqliteConfigurationManager}
     */
    private function fullyWiredManager(): array
    {
        $audit = new SqliteConfigurationAudit($this->tempPath('audit'));
        $registry = new SqliteConfigurationRegistry($this->tempPath('registry'), $audit);
        $validator = new SqliteConfigurationValidator($this->tempPath('validator'), new EngineValidation(), $registry);
        $environments = new SqliteEnvironments($this->tempPath('environments'), $registry, $validator, $audit);
        $runtimeConfiguration = new SqliteRuntimeConfiguration($this->tempPath('runtime'), $registry, $environments, null, null, $validator);
        $featureFlags = new SqliteFeatureFlags($this->tempPath('flags'), $registry, $audit);
        $policyConfiguration = new SqlitePolicyConfiguration($this->tempPath('policy'), $registry, $validator, $audit);
        $secretsManager = new SqliteSecretsManager($this->tempPath('secrets'));

        $manager = new SqliteConfigurationManager($registry, $environments, $runtimeConfiguration, $featureFlags, $policyConfiguration, $secretsManager, $validator, $audit);

        return compact('registry', 'environments', 'runtimeConfiguration', 'featureFlags', 'policyConfiguration', 'secretsManager', 'validator', 'audit', 'manager');
    }

    // --- routing: shape validation ---

    public function testUnrecognizedDomainIsInvalid(): void
    {
        $manager = new SqliteConfigurationManager();

        $result = $manager->handle('astrology', 'get');

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testUnrecognizedOperationForARealDomainIsInvalid(): void
    {
        $manager = new SqliteConfigurationManager();

        $result = $manager->handle('registry', 'delete_everything');

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testUnconfiguredDomainOwnerIsRejected(): void
    {
        $manager = new SqliteConfigurationManager();

        $result = $manager->handle('registry', 'get', ['configuration_ref' => 'ghost']);

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('not configured', $result['error']);
    }

    // --- registry domain ---

    public function testRegistryRegisterAndGetRoundTrip(): void
    {
        $wired = $this->fullyWiredManager();

        $registered = $wired['manager']->handle('registry', 'register', [
            'name' => 'timeout_seconds', 'owner' => 'x', 'scope' => 'global', 'data_type' => 'integer', 'actor_ref' => 'admin_console',
        ]);
        $this->assertSame('routed', $registered['outcome']);
        $configRef = $registered['result']['configuration_ref'];

        $fetched = $wired['manager']->handle('registry', 'get', ['configuration_ref' => $configRef]);

        $this->assertSame('Registered', $fetched['result']['state']);
    }

    public function testRegistryTransition(): void
    {
        $wired = $this->fullyWiredManager();
        $registered = $wired['manager']->handle('registry', 'register', [
            'name' => 'timeout_seconds', 'owner' => 'x', 'scope' => 'global', 'data_type' => 'integer', 'actor_ref' => 'admin_console',
        ]);
        $configRef = $registered['result']['configuration_ref'];

        $result = $wired['manager']->handle('registry', 'transition', ['configuration_ref' => $configRef, 'to_state' => 'Active', 'actor_ref' => 'admin_console']);

        $this->assertSame('Active', $result['result']['state']);
    }

    // --- environments domain ---

    public function testEnvironmentsRegisterProfileAndGet(): void
    {
        $wired = $this->fullyWiredManager();

        $registered = $wired['manager']->handle('environments', 'register_profile', ['name' => 'production', 'actor_ref' => 'admin_console']);
        $envId = $registered['result']['environment_id'];

        $fetched = $wired['manager']->handle('environments', 'get', ['environment_id' => $envId]);

        $this->assertSame('production', $fetched['result']['name']);
    }

    // --- runtime_configuration domain ---

    public function testRuntimeConfigurationResolve(): void
    {
        $wired = $this->fullyWiredManager();
        $registered = $wired['manager']->handle('registry', 'register', [
            'name' => 'timeout_seconds', 'owner' => 'x', 'scope' => 'global', 'data_type' => 'integer', 'actor_ref' => 'admin_console',
        ]);
        $configRef = $registered['result']['configuration_ref'];
        $wired['manager']->handle('registry', 'transition', ['configuration_ref' => $configRef, 'to_state' => 'Active', 'actor_ref' => 'admin_console']);

        $resolved = $wired['manager']->handle('runtime_configuration', 'resolve', ['configuration_ref' => $configRef, 'actor_ref' => 'admin_console']);

        $this->assertSame('routed', $resolved['outcome']);
        $this->assertSame('Active', $resolved['result']['state']);
    }

    // --- feature_flags domain ---

    public function testFeatureFlagsRegisterAndEvaluate(): void
    {
        $wired = $this->fullyWiredManager();
        $registered = $wired['manager']->handle('feature_flags', 'register_flag', ['name' => 'new_checkout', 'actor_ref' => 'admin_console', 'initial_state' => 'Enabled']);
        $flagId = $registered['result']['flag_id'];

        $evaluation = $wired['manager']->handle('feature_flags', 'evaluate', ['flag_id' => $flagId]);

        $this->assertTrue($evaluation['result']['matched']);
    }

    // --- policy_configuration domain ---

    public function testPolicyConfigurationRegisterAndGet(): void
    {
        $wired = $this->fullyWiredManager();

        $registered = $wired['manager']->handle('policy_configuration', 'register_value', [
            'name' => 'max_retries', 'category' => 'resilience', 'owner' => 'x', 'scope' => 'global', 'value' => 3, 'version_ref' => 'v1', 'actor_ref' => 'admin_console',
        ]);
        $ref = $registered['result']['policy_config_ref'];

        $fetched = $wired['manager']->handle('policy_configuration', 'get', ['policy_config_ref' => $ref]);

        $this->assertSame(3, $fetched['result']['value']);
    }

    // --- secrets domain: real interface mismatch, normalized ---

    public function testSecretsRegisterApiKeySucceeds(): void
    {
        $wired = $this->fullyWiredManager();

        $result = $wired['manager']->handle('secrets', 'register_api_key', ['identity_ref' => 'agent_1', 'api_key' => str_repeat('a', 40)]);

        $this->assertSame('routed', $result['outcome']);
        $this->assertIsString($result['result']);
    }

    public function testSecretsInvalidInputIsNormalizedToRejected(): void
    {
        $wired = $this->fullyWiredManager();

        // Too short an API key throws inside SqliteSecretsManager -- must not bubble up uncaught.
        $result = $wired['manager']->handle('secrets', 'register_api_key', ['identity_ref' => 'agent_1', 'api_key' => 'too_short']);

        $this->assertSame('rejected', $result['outcome']);
        $this->assertNotNull($result['error']);
    }

    // --- validator domain ---

    public function testValidatorValidate(): void
    {
        $wired = $this->fullyWiredManager();

        $result = $wired['manager']->handle('validator', 'validate', ['configuration_ref' => 'config_1']);

        $this->assertSame('routed', $result['outcome']);
        $this->assertSame('Pending', $result['result']['status']);
    }

    // --- audit domain ---

    public function testAuditRecord(): void
    {
        $wired = $this->fullyWiredManager();

        $result = $wired['manager']->handle('audit', 'record', [
            'event' => 'Configuration Registered', 'configuration_ref' => 'config_1', 'actor_ref' => 'admin_console',
        ]);

        $this->assertSame('routed', $result['outcome']);
        $this->assertSame('recorded', $result['result']['outcome']);
    }

    // --- aggregateStatus(): real, assembled facts only ---

    public function testAggregateStatusForAnUnknownReference(): void
    {
        $manager = new SqliteConfigurationManager();

        $status = $manager->aggregateStatus('ghost');

        $this->assertNull($status['lifecycle_status']);
        $this->assertSame(0, $status['history_count']);
        $this->assertNull($status['latest_bundle_state']);
    }

    public function testAggregateStatusCombinesRegistryAuditAndBundleFacts(): void
    {
        $wired = $this->fullyWiredManager();
        $registered = $wired['manager']->handle('registry', 'register', [
            'name' => 'timeout_seconds', 'owner' => 'x', 'scope' => 'global', 'data_type' => 'integer', 'actor_ref' => 'admin_console',
        ]);
        $configRef = $registered['result']['configuration_ref'];
        $wired['manager']->handle('registry', 'transition', ['configuration_ref' => $configRef, 'to_state' => 'Active', 'actor_ref' => 'admin_console']);
        $wired['manager']->handle('runtime_configuration', 'resolve', ['configuration_ref' => $configRef, 'actor_ref' => 'admin_console']);

        $status = $wired['manager']->aggregateStatus($configRef);

        $this->assertSame('Active', $status['lifecycle_status']);
        $this->assertGreaterThan(0, $status['history_count']);
        $this->assertSame('Active', $status['latest_bundle_state']);
    }
}
