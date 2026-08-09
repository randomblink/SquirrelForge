<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Configuration;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Configuration\SqlitePermissions;
use SquirrelForge\Configuration\SqliteToolConfig;

final class SqliteToolConfigTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-tool-config-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function toolConfig(?SqlitePermissions $permissions = null): SqliteToolConfig
    {
        return new SqliteToolConfig($this->tempPath('db'), $permissions);
    }

    /**
     * @return array{tool_id: string, provider_ref: string, supported_actions: array<int, string>, timeout_seconds: float}
     */
    private function minimalDeclaration(array $overrides = []): array
    {
        return array_replace([
            'tool_id' => 'file_read',
            'provider_ref' => 'filesystem_adapter',
            'supported_actions' => ['read_file'],
            'timeout_seconds' => 30.0,
        ], $overrides);
    }

    // --- shape validation ---

    public function testMissingToolIdIsInvalid(): void
    {
        $config = $this->toolConfig();
        $declaration = $this->minimalDeclaration();
        unset($declaration['tool_id']);

        $result = $config->register($declaration);

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testMissingProviderRefIsInvalid(): void
    {
        $config = $this->toolConfig();
        $declaration = $this->minimalDeclaration();
        unset($declaration['provider_ref']);

        $result = $config->register($declaration);

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testNoSupportedActionsIsInvalid(): void
    {
        $config = $this->toolConfig();

        $result = $config->register($this->minimalDeclaration(['supported_actions' => []]));

        $this->assertSame('invalid', $result['outcome']);
        $this->assertStringContainsString('supported action', $result['error']);
    }

    public function testNonPositiveTimeoutIsInvalid(): void
    {
        $config = $this->toolConfig();

        $result = $config->register($this->minimalDeclaration(['timeout_seconds' => 0]));

        $this->assertSame('invalid', $result['outcome']);
        $this->assertStringContainsString('timeout_seconds', $result['error']);
    }

    public function testValidDeclarationSucceeds(): void
    {
        $config = $this->toolConfig();

        $result = $config->register($this->minimalDeclaration());

        $this->assertSame('registered', $result['outcome']);
        $this->assertNotNull($result['config_id']);
    }

    // --- must not store secrets directly ---

    public function testSecretShapedParameterKeyIsRejected(): void
    {
        $config = $this->toolConfig();

        $result = $config->register($this->minimalDeclaration(['parameters' => ['api_key' => 'sk-abc123']]));

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('api_key', $result['error']);
    }

    public function testPasswordShapedParameterKeyIsRejected(): void
    {
        $config = $this->toolConfig();

        $result = $config->register($this->minimalDeclaration(['parameters' => ['password' => 'hunter2']]));

        $this->assertSame('rejected', $result['outcome']);
    }

    public function testOrdinaryParametersAreAccepted(): void
    {
        $config = $this->toolConfig();

        $result = $config->register($this->minimalDeclaration(['parameters' => ['base_path' => '/repo', 'max_file_size' => 1048576]]));

        $this->assertSame('registered', $result['outcome']);
    }

    public function testSecretReferencesAreStoredAsOpaqueStrings(): void
    {
        $config = $this->toolConfig();
        $config->register($this->minimalDeclaration(['secret_references' => ['secret_ref_1', 'secret_ref_2']]));

        $record = $config->get('file_read');

        $this->assertSame(['secret_ref_1', 'secret_ref_2'], $record['secret_references']);
    }

    // --- SqlitePermissions composition ---

    public function testRequiredPermissionRefMustReferenceARealDeclaration(): void
    {
        $permissions = new SqlitePermissions($this->tempPath('permissions'));
        $config = $this->toolConfig($permissions);

        $result = $config->register($this->minimalDeclaration(['required_permission_ref' => 'ghost_declaration']));

        $this->assertSame('invalid', $result['outcome']);
        $this->assertStringContainsString('ghost_declaration', $result['error']);
    }

    public function testRequiredPermissionRefSucceedsWithARealDeclaration(): void
    {
        $permissions = new SqlitePermissions($this->tempPath('permissions'));
        $declared = $permissions->declare(['actor' => 'file_read', 'capability' => 'read', 'resource' => 'filesystem', 'duration' => 'persistent']);
        $config = $this->toolConfig($permissions);

        $result = $config->register($this->minimalDeclaration(['required_permission_ref' => $declared['declaration_id']]));

        $this->assertSame('registered', $result['outcome']);
    }

    public function testWithoutAPermissionsComponentNoCrossCheckHappens(): void
    {
        $config = $this->toolConfig();

        $result = $config->register($this->minimalDeclaration(['required_permission_ref' => 'anything_at_all']));

        $this->assertSame('registered', $result['outcome']);
    }

    public function testMissingRequiredPermissionRefIsFineToOmit(): void
    {
        $permissions = new SqlitePermissions($this->tempPath('permissions'));
        $config = $this->toolConfig($permissions);

        $result = $config->register($this->minimalDeclaration());

        $this->assertSame('registered', $result['outcome']);
    }

    // --- side_effect_classification_ref stays opaque ---

    public function testSideEffectClassificationRefIsCarriedForwardOpaquely(): void
    {
        $config = $this->toolConfig();
        $config->register($this->minimalDeclaration(['side_effect_classification_ref' => 'read_only']));

        $record = $config->get('file_read');

        $this->assertSame('read_only', $record['side_effect_classification_ref']);
    }

    // --- duplicate handling ---

    public function testDuplicateActiveRegistrationIsRejected(): void
    {
        $config = $this->toolConfig();
        $config->register($this->minimalDeclaration());

        $result = $config->register($this->minimalDeclaration());

        $this->assertSame('duplicate', $result['outcome']);
    }

    public function testReregistrationAfterDeregisterIsAllowed(): void
    {
        $config = $this->toolConfig();
        $config->register($this->minimalDeclaration());
        $config->deregister('file_read');

        $result = $config->register($this->minimalDeclaration(['timeout_seconds' => 60.0]));

        $this->assertSame('registered', $result['outcome']);
        $this->assertSame(60.0, $config->get('file_read')['timeout_seconds']);
    }

    // --- get() / deregister() / history() ---

    public function testGetReturnsNullForUnregisteredTool(): void
    {
        $config = $this->toolConfig();

        $this->assertNull($config->get('ghost'));
    }

    public function testGetReturnsNullAfterDeregister(): void
    {
        $config = $this->toolConfig();
        $config->register($this->minimalDeclaration());
        $config->deregister('file_read');

        $this->assertNull($config->get('file_read'));
    }

    public function testDeregisterUnknownToolIsNotFound(): void
    {
        $config = $this->toolConfig();

        $result = $config->deregister('ghost');

        $this->assertSame('not_found', $result['outcome']);
    }

    public function testHistoryReturnsEveryRegistrationForATool(): void
    {
        $config = $this->toolConfig();
        $config->register($this->minimalDeclaration());
        $config->deregister('file_read');
        $config->register($this->minimalDeclaration(['timeout_seconds' => 45.0]));

        $history = $config->history('file_read');

        $this->assertCount(2, $history);
        $this->assertSame(30.0, $history[0]['timeout_seconds']);
        $this->assertSame(45.0, $history[1]['timeout_seconds']);
    }

    public function testGetHydratesJsonFields(): void
    {
        $config = $this->toolConfig();
        $config->register($this->minimalDeclaration(['supported_actions' => ['read_file', 'list_directory']]));

        $record = $config->get('file_read');

        $this->assertSame(['read_file', 'list_directory'], $record['supported_actions']);
    }
}
