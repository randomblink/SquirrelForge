<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\RuntimeConfig;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Engine\EngineValidation;
use SquirrelForge\RuntimeConfig\SqliteConfigurationAudit;
use SquirrelForge\RuntimeConfig\SqliteConfigurationRegistry;
use SquirrelForge\RuntimeConfig\SqliteConfigurationValidator;
use SquirrelForge\RuntimeConfig\SqlitePolicyConfiguration;

final class SqlitePolicyConfigurationTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-policy-configuration-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
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
     * @return array<string, mixed>
     */
    private function entryFor(array $overrides = []): array
    {
        return array_replace([
            'name' => 'max_retry_attempts',
            'category' => 'resilience',
            'owner' => 'engine_maintainers',
            'scope' => 'global',
            'value' => 3,
            'version_ref' => 'v1',
            'actor_ref' => 'admin_console',
        ], $overrides);
    }

    // --- registerValue(): shape validation ---

    public function testMissingNameIsInvalid(): void
    {
        $policyConfig = new SqlitePolicyConfiguration($this->tempPath('db'), $this->registry());

        $result = $policyConfig->registerValue($this->entryFor(['name' => '']));

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testMissingVersionRefIsInvalid(): void
    {
        $policyConfig = new SqlitePolicyConfiguration($this->tempPath('db'), $this->registry());

        $result = $policyConfig->registerValue($this->entryFor(['version_ref' => '']));

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testUnknownOverrideRefIsRejected(): void
    {
        $policyConfig = new SqlitePolicyConfiguration($this->tempPath('db'), $this->registry());

        $result = $policyConfig->registerValue($this->entryFor(['override_ref' => 'ghost_ref']));

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('does not exist', $result['error']);
    }

    public function testNoRegistryComposedIsRejected(): void
    {
        $policyConfig = new SqlitePolicyConfiguration($this->tempPath('db'));

        $result = $policyConfig->registerValue($this->entryFor());

        $this->assertSame('rejected', $result['outcome']);
    }

    // --- registerValue(): success ---

    public function testSuccessfulRegistration(): void
    {
        $policyConfig = new SqlitePolicyConfiguration($this->tempPath('db'), $this->registry());

        $result = $policyConfig->registerValue($this->entryFor());

        $this->assertSame('registered', $result['outcome']);
        $this->assertNotNull($result['policy_config_ref']);
    }

    public function testRealOverrideReferenceIsAccepted(): void
    {
        $policyConfig = new SqlitePolicyConfiguration($this->tempPath('db'), $this->registry());
        $base = $policyConfig->registerValue($this->entryFor(['name' => 'base_value']));

        $result = $policyConfig->registerValue($this->entryFor(['name' => 'override_value', 'override_ref' => $base['policy_config_ref']]));

        $this->assertSame('registered', $result['outcome']);
    }

    public function testRegistrationRecordsARealAuditEvent(): void
    {
        $audit = new SqliteConfigurationAudit($this->tempPath('policy-audit'));
        $policyConfig = new SqlitePolicyConfiguration($this->tempPath('db'), $this->registry(), null, $audit);

        $result = $policyConfig->registerValue($this->entryFor());

        $history = $audit->history($result['policy_config_ref']);
        $this->assertSame('Policy Configuration Changed', $history[0]['event']);
    }

    // --- validate(): genuine composition ---

    public function testValidateOnUnknownRefIsInvalid(): void
    {
        $policyConfig = new SqlitePolicyConfiguration($this->tempPath('db'), $this->registry());

        $result = $policyConfig->validate('ghost', 'admin_console');

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testValidateWithoutValidatorComposedIsRejected(): void
    {
        $registry = $this->registry();
        $policyConfig = new SqlitePolicyConfiguration($this->tempPath('db'), $registry);
        $registered = $policyConfig->registerValue($this->entryFor());

        $result = $policyConfig->validate($registered['policy_config_ref'], 'admin_console');

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('must be validated', $result['error']);
    }

    public function testValidateWithNoDependenciesProducesAPendingConfigurationValidatorStatus(): void
    {
        $registry = $this->registry();
        $policyConfig = new SqlitePolicyConfiguration($this->tempPath('db'), $registry, $this->validatorFor($registry));
        $registered = $policyConfig->registerValue($this->entryFor());

        $result = $policyConfig->validate($registered['policy_config_ref'], 'admin_console');

        $this->assertSame('validated', $result['outcome']);
        // No dependency_refs and no validation_items declared -- SqliteConfigurationValidator's own honest "nothing checkable" status.
        $this->assertSame('Pending', $result['status']);
    }

    public function testValidateChecksTheRealOverrideDependency(): void
    {
        $registry = $this->registry();
        $policyConfig = new SqlitePolicyConfiguration($this->tempPath('db'), $registry, $this->validatorFor($registry));
        $base = $policyConfig->registerValue($this->entryFor(['name' => 'base_value']));
        $override = $policyConfig->registerValue($this->entryFor(['name' => 'override_value', 'override_ref' => $base['policy_config_ref']]));

        $result = $policyConfig->validate($override['policy_config_ref'], 'admin_console');

        // The override_ref genuinely exists in the Registry, so the dependency check passes -- still Pending overall (nothing else was checked).
        $this->assertSame('Pending', $result['status']);
    }

    // --- activate(): Rule 2, validated before use ---

    public function testActivateOnUnknownRefIsInvalid(): void
    {
        $policyConfig = new SqlitePolicyConfiguration($this->tempPath('db'), $this->registry());

        $result = $policyConfig->activate('ghost', 'admin_console');

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testActivateWithoutHavingBeenValidatedIsRejected(): void
    {
        $registry = $this->registry();
        $policyConfig = new SqlitePolicyConfiguration($this->tempPath('db'), $registry);
        $registered = $policyConfig->registerValue($this->entryFor());

        $result = $policyConfig->activate($registered['policy_config_ref'], 'admin_console');

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('never validated', $result['error']);
    }

    public function testValidateWithEmptyValidationItemsProducesARealValidStatus(): void
    {
        $registry = $this->registry();
        $policyConfig = new SqlitePolicyConfiguration($this->tempPath('db'), $registry, $this->validatorFor($registry));
        $registered = $policyConfig->registerValue($this->entryFor());

        // Passing an empty (but non-null) validation_items array opts into EngineValidation's own real check,
        // which accepts vacuously when there is nothing required to fail.
        $result = $policyConfig->validate($registered['policy_config_ref'], 'admin_console', []);

        $this->assertSame('Valid', $result['status']);
    }

    public function testValidateWithAFailingValidationItemProducesARealFailedStatus(): void
    {
        $registry = $this->registry();
        $policyConfig = new SqlitePolicyConfiguration($this->tempPath('db'), $registry, $this->validatorFor($registry));
        $registered = $policyConfig->registerValue($this->entryFor());

        $result = $policyConfig->validate($registered['policy_config_ref'], 'admin_console', [
            ['item_id' => 'i1', 'stage' => 'STRUCTURE', 'required' => true, 'status' => 'FAILED', 'waivable' => false],
        ]);

        $this->assertSame('Failed', $result['status']);
    }

    public function testActivateAfterAPendingValidationIsRejected(): void
    {
        $registry = $this->registry();
        $policyConfig = new SqlitePolicyConfiguration($this->tempPath('db'), $registry, $this->validatorFor($registry));
        $registered = $policyConfig->registerValue($this->entryFor());
        $policyConfig->validate($registered['policy_config_ref'], 'admin_console');

        $result = $policyConfig->activate($registered['policy_config_ref'], 'admin_console');

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('Pending', $result['error']);
    }

    public function testActivateAfterAFailedValidationIsRejected(): void
    {
        $registry = $this->registry();
        $policyConfig = new SqlitePolicyConfiguration($this->tempPath('db'), $registry, $this->validatorFor($registry));
        $registered = $policyConfig->registerValue($this->entryFor());
        $policyConfig->validate($registered['policy_config_ref'], 'admin_console', [
            ['item_id' => 'i1', 'stage' => 'STRUCTURE', 'required' => true, 'status' => 'FAILED', 'waivable' => false],
        ]);

        $result = $policyConfig->activate($registered['policy_config_ref'], 'admin_console');

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('Failed', $result['error']);
    }

    public function testActivateAfterARealValidValidationSucceedsAndTransitionsTheRegistry(): void
    {
        $registry = $this->registry();
        $policyConfig = new SqlitePolicyConfiguration($this->tempPath('db'), $registry, $this->validatorFor($registry));
        $registered = $policyConfig->registerValue($this->entryFor());
        $policyConfig->validate($registered['policy_config_ref'], 'admin_console', []);

        $result = $policyConfig->activate($registered['policy_config_ref'], 'admin_console');

        $this->assertSame('activated', $result['outcome']);
        $record = $policyConfig->get($registered['policy_config_ref']);
        $this->assertSame('Active', $record['lifecycle_status']);
    }

    public function testActivateRecordsARealAuditEvent(): void
    {
        $registry = $this->registry();
        $audit = new SqliteConfigurationAudit($this->tempPath('policy-audit'));
        $policyConfig = new SqlitePolicyConfiguration($this->tempPath('db'), $registry, $this->validatorFor($registry), $audit);
        $registered = $policyConfig->registerValue($this->entryFor());
        $policyConfig->validate($registered['policy_config_ref'], 'admin_console', []);

        $policyConfig->activate($registered['policy_config_ref'], 'admin_console');

        $history = $audit->history($registered['policy_config_ref']);
        $this->assertSame('Policy Configuration Changed', $history[count($history) - 1]['event']);
    }

    // --- activate(): registry not composed on this instance ---

    public function testActivateWithoutRegistryComposedOnThisInstanceIsRejected(): void
    {
        $sharedDatabase = $this->tempPath('shared');
        $registry = $this->registry();
        $setup = new SqlitePolicyConfiguration($sharedDatabase, $registry, $this->validatorFor($registry));
        $registered = $setup->registerValue($this->entryFor());
        $setup->validate($registered['policy_config_ref'], 'admin_console', []);

        $withoutRegistry = new SqlitePolicyConfiguration($sharedDatabase);

        $result = $withoutRegistry->activate($registered['policy_config_ref'], 'admin_console');

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('Configuration Registry', $result['error']);
    }

    // --- updateValue() ---

    public function testUpdateValueOnUnknownRefIsInvalid(): void
    {
        $policyConfig = new SqlitePolicyConfiguration($this->tempPath('db'), $this->registry());

        $result = $policyConfig->updateValue('ghost', 5, 'v2', 'admin_console');

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testUpdateValueWithTheSameVersionRefIsInvalid(): void
    {
        $policyConfig = new SqlitePolicyConfiguration($this->tempPath('db'), $this->registry());
        $registered = $policyConfig->registerValue($this->entryFor());

        $result = $policyConfig->updateValue($registered['policy_config_ref'], 5, 'v1', 'admin_console');

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testUpdateValueSucceedsAndResetsValidationStatus(): void
    {
        $registry = $this->registry();
        $policyConfig = new SqlitePolicyConfiguration($this->tempPath('db'), $registry, $this->validatorFor($registry));
        $registered = $policyConfig->registerValue($this->entryFor());
        $policyConfig->validate($registered['policy_config_ref'], 'admin_console');

        $result = $policyConfig->updateValue($registered['policy_config_ref'], 5, 'v2', 'admin_console');

        $this->assertSame('updated', $result['outcome']);
        $record = $policyConfig->get($registered['policy_config_ref']);
        $this->assertSame(5, $record['value']);
        $this->assertSame('v2', $record['version_ref']);
        $this->assertNull($record['validation_status']);
    }

    // --- get() / history() ---

    public function testGetCombinesRegistryStateWithOwnValueData(): void
    {
        $policyConfig = new SqlitePolicyConfiguration($this->tempPath('db'), $this->registry());
        $registered = $policyConfig->registerValue($this->entryFor());

        $record = $policyConfig->get($registered['policy_config_ref']);

        $this->assertSame('max_retry_attempts', $record['name']);
        $this->assertSame('Registered', $record['lifecycle_status']);
        $this->assertSame(3, $record['value']);
    }

    public function testGetUnknownRefReturnsNull(): void
    {
        $policyConfig = new SqlitePolicyConfiguration($this->tempPath('db'), $this->registry());

        $this->assertNull($policyConfig->get('ghost'));
    }
}
