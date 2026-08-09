<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\RuntimeConfig;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Engine\EngineValidation;
use SquirrelForge\RuntimeConfig\SqliteConfigurationAudit;
use SquirrelForge\RuntimeConfig\SqliteConfigurationRegistry;
use SquirrelForge\RuntimeConfig\SqliteConfigurationValidator;

final class SqliteConfigurationValidatorTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-configuration-validator-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function registryWithOneEntry(): SqliteConfigurationRegistry
    {
        $audit = new SqliteConfigurationAudit($this->tempPath('audit'));
        $registry = new SqliteConfigurationRegistry($this->tempPath('registry'), $audit);
        $registry->register(['name' => 'dep', 'owner' => 'x', 'scope' => 'global', 'data_type' => 'string', 'actor_ref' => 'system']);

        return $registry;
    }

    // --- shape validation ---

    public function testMissingConfigurationRefIsInvalid(): void
    {
        $validator = new SqliteConfigurationValidator($this->tempPath('db'));

        $result = $validator->validate([]);

        $this->assertSame('invalid', $result['outcome']);
    }

    // --- nothing checkable -> honest Pending ---

    public function testNoChecksDeclaredIsPending(): void
    {
        $validator = new SqliteConfigurationValidator($this->tempPath('db'));

        $result = $validator->validate(['configuration_ref' => 'config_1']);

        $this->assertSame('validated', $result['outcome']);
        $this->assertSame('Pending', $result['status']);
        $this->assertSame([], $result['findings']);
    }

    // --- dependency references ---

    public function testDependencyRefWithNoRegistryComposedIsBlocked(): void
    {
        $validator = new SqliteConfigurationValidator($this->tempPath('db'));

        $result = $validator->validate(['configuration_ref' => 'config_1', 'dependency_refs' => ['dep_1']]);

        $this->assertSame('Blocked', $result['status']);
        $this->assertStringContainsString('not configured', $result['findings'][0]);
    }

    public function testMissingDependencyReferenceFailsAgainstTheRealRegistry(): void
    {
        $registry = $this->registryWithOneEntry();
        $validator = new SqliteConfigurationValidator($this->tempPath('db'), null, $registry);

        $result = $validator->validate(['configuration_ref' => 'config_1', 'dependency_refs' => ['ghost_dep']]);

        $this->assertSame('Failed', $result['status']);
        $this->assertStringContainsString('does not exist', $result['findings'][0]);
    }

    public function testExistingDependencyReferencePassesAgainstTheRealRegistry(): void
    {
        $registry = $this->registryWithOneEntry();
        $entry = $registry->all()[0];
        $validator = new SqliteConfigurationValidator($this->tempPath('db'), null, $registry);

        $result = $validator->validate(['configuration_ref' => 'config_1', 'dependency_refs' => [$entry['configuration_ref']]]);

        $this->assertSame('Pending', $result['status']);
        $this->assertSame([], $result['findings']);
    }

    // --- secret_refs: presence only ---

    public function testEmptySecretRefFails(): void
    {
        $validator = new SqliteConfigurationValidator($this->tempPath('db'));

        $result = $validator->validate(['configuration_ref' => 'config_1', 'secret_refs' => ['']]);

        $this->assertSame('Failed', $result['status']);
        $this->assertStringContainsString('secret_ref is empty', $result['findings'][0]);
    }

    public function testNonEmptySecretRefPasses(): void
    {
        $validator = new SqliteConfigurationValidator($this->tempPath('db'));

        $result = $validator->validate(['configuration_ref' => 'config_1', 'secret_refs' => ['secret_api_key']]);

        $this->assertSame('Pending', $result['status']);
    }

    // --- policy_configuration_ref: presence only ---

    public function testEmptyPolicyConfigurationRefFails(): void
    {
        $validator = new SqliteConfigurationValidator($this->tempPath('db'));

        $result = $validator->validate(['configuration_ref' => 'config_1', 'policy_configuration_ref' => '']);

        $this->assertSame('Failed', $result['status']);
    }

    public function testOmittedPolicyConfigurationRefIsNotChecked(): void
    {
        $validator = new SqliteConfigurationValidator($this->tempPath('db'));

        $result = $validator->validate(['configuration_ref' => 'config_1']);

        $this->assertSame('Pending', $result['status']);
    }

    // --- feature_flag_refs: presence only ---

    public function testEmptyFeatureFlagRefFails(): void
    {
        $validator = new SqliteConfigurationValidator($this->tempPath('db'));

        $result = $validator->validate(['configuration_ref' => 'config_1', 'feature_flag_refs' => ['']]);

        $this->assertSame('Failed', $result['status']);
    }

    // --- validation_items: real EngineValidation composition ---

    public function testValidationItemsDeclaredWithoutEngineValidationComposedIsBlocked(): void
    {
        $validator = new SqliteConfigurationValidator($this->tempPath('db'));

        $result = $validator->validate(['configuration_ref' => 'config_1', 'validation_items' => []]);

        $this->assertSame('Blocked', $result['status']);
        $this->assertStringContainsString('EngineValidation is not configured', $result['findings'][0]);
    }

    public function testAcceptedValidationItemsAreValid(): void
    {
        $validator = new SqliteConfigurationValidator($this->tempPath('db'), new EngineValidation());

        $result = $validator->validate(['configuration_ref' => 'config_1', 'validation_items' => []]);

        $this->assertSame('Valid', $result['status']);
        $this->assertStringContainsString('ACCEPTED', $result['findings'][0]);
    }

    public function testAcceptedWithLimitationsIsWarning(): void
    {
        $validator = new SqliteConfigurationValidator($this->tempPath('db'), new EngineValidation());

        $result = $validator->validate([
            'configuration_ref' => 'config_1',
            'validation_items' => [['item_id' => 'i1', 'stage' => 'STRUCTURE', 'required' => true, 'status' => 'WAIVED', 'waivable' => true]],
        ]);

        $this->assertSame('Warning', $result['status']);
    }

    public function testRejectedIsFailed(): void
    {
        $validator = new SqliteConfigurationValidator($this->tempPath('db'), new EngineValidation());

        $result = $validator->validate([
            'configuration_ref' => 'config_1',
            'validation_items' => [['item_id' => 'i1', 'stage' => 'STRUCTURE', 'required' => true, 'status' => 'FAILED', 'waivable' => false]],
        ]);

        $this->assertSame('Failed', $result['status']);
    }

    public function testEngineValidationBlockedIsBlocked(): void
    {
        $validator = new SqliteConfigurationValidator($this->tempPath('db'), new EngineValidation());

        $result = $validator->validate([
            'configuration_ref' => 'config_1',
            'validation_items' => [['item_id' => 'i1', 'stage' => 'STRUCTURE', 'required' => true, 'status' => 'UNAVAILABLE', 'waivable' => false]],
        ]);

        $this->assertSame('Blocked', $result['status']);
    }

    // --- combined: worst status wins ---

    public function testWorstStatusAcrossMultipleChecksWins(): void
    {
        $registry = $this->registryWithOneEntry();
        $validator = new SqliteConfigurationValidator($this->tempPath('db'), new EngineValidation(), $registry);

        $result = $validator->validate([
            'configuration_ref' => 'config_1',
            // A Failed dependency check...
            'dependency_refs' => ['ghost_dep'],
            // ...plus a Blocked validation-items check (UNAVAILABLE, non-waivable) -- Blocked outranks Failed.
            'validation_items' => [['item_id' => 'i1', 'stage' => 'STRUCTURE', 'required' => true, 'status' => 'UNAVAILABLE', 'waivable' => false]],
        ]);

        $this->assertSame('Blocked', $result['status']);
        $this->assertCount(2, $result['findings']);
    }

    // --- get() / history() ---

    public function testGetUnknownValidationRefReturnsNull(): void
    {
        $validator = new SqliteConfigurationValidator($this->tempPath('db'));

        $this->assertNull($validator->get('ghost'));
    }

    public function testHistoryPreservesEveryValidationForAConfigurationItem(): void
    {
        $validator = new SqliteConfigurationValidator($this->tempPath('db'));
        $validator->validate(['configuration_ref' => 'config_1']);
        $validator->validate(['configuration_ref' => 'config_1', 'secret_refs' => ['']]);
        $validator->validate(['configuration_ref' => 'config_other']);

        $history = $validator->history('config_1');

        $this->assertCount(2, $history);
        $this->assertSame('Pending', $history[0]['status']);
        $this->assertSame('Failed', $history[1]['status']);
    }
}
