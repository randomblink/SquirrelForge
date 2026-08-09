<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\RuntimeConfig;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Engine\EngineValidation;
use SquirrelForge\RuntimeConfig\SqliteConfigurationAudit;
use SquirrelForge\RuntimeConfig\SqliteConfigurationRegistry;
use SquirrelForge\RuntimeConfig\SqliteConfigurationValidator;
use SquirrelForge\RuntimeConfig\SqliteEnvironments;

final class SqliteEnvironmentsTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-environments-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function registry(): SqliteConfigurationRegistry
    {
        return new SqliteConfigurationRegistry($this->tempPath('registry'), new SqliteConfigurationAudit($this->tempPath('registry-audit')));
    }

    /**
     * @return array<string, mixed>
     */
    private function entryFor(array $overrides = []): array
    {
        return array_replace([
            'name' => 'production',
            'actor_ref' => 'admin_console',
        ], $overrides);
    }

    // --- shape validation ---

    public function testMissingNameIsInvalid(): void
    {
        $environments = new SqliteEnvironments($this->tempPath('db'), $this->registry());

        $result = $environments->registerProfile($this->entryFor(['name' => '']));

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testMissingActorRefIsInvalid(): void
    {
        $environments = new SqliteEnvironments($this->tempPath('db'), $this->registry());

        $result = $environments->registerProfile($this->entryFor(['actor_ref' => null]));

        $this->assertSame('invalid', $result['outcome']);
    }

    // --- Rule 1: overlays must be deterministic and traceable ---

    public function testDuplicateOverlayReferenceIsRejected(): void
    {
        $environments = new SqliteEnvironments($this->tempPath('db'), $this->registry());

        $result = $environments->registerProfile($this->entryFor(['overlay_refs' => ['overlay_a', 'overlay_a']]));

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('deterministic and traceable', $result['error']);
    }

    // --- Rule 2: no raw secret values ---

    public function testSecretShapedOverrideRuleKeyIsRejected(): void
    {
        $environments = new SqliteEnvironments($this->tempPath('db'), $this->registry());

        $result = $environments->registerProfile($this->entryFor(['override_rules' => ['api_key' => 'sk-abc123']]));

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('raw secret', $result['error']);
    }

    public function testNonSecretShapedOverrideRuleIsAccepted(): void
    {
        $environments = new SqliteEnvironments($this->tempPath('db'), $this->registry());

        $result = $environments->registerProfile($this->entryFor(['override_rules' => ['timeout_seconds' => 60]]));

        $this->assertSame('registered', $result['outcome']);
    }

    // --- inheritance: parent_profile must be a real, already-registered environment ---

    public function testUnknownParentProfileIsRejected(): void
    {
        $environments = new SqliteEnvironments($this->tempPath('db'), $this->registry());

        $result = $environments->registerProfile($this->entryFor(['parent_profile' => 'ghost_env']));

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('not a registered environment', $result['error']);
    }

    public function testRealParentProfileIsAccepted(): void
    {
        $environments = new SqliteEnvironments($this->tempPath('db'), $this->registry());
        $base = $environments->registerProfile($this->entryFor(['name' => 'base']));

        $result = $environments->registerProfile($this->entryFor(['name' => 'staging', 'parent_profile' => $base['environment_id']]));

        $this->assertSame('registered', $result['outcome']);
    }

    // --- registry composition ---

    public function testNoRegistryComposedIsRejected(): void
    {
        $environments = new SqliteEnvironments($this->tempPath('db'));

        $result = $environments->registerProfile($this->entryFor());

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('Configuration Registry', $result['error']);
    }

    // --- get(): combines the real Registry state with this class's own data ---

    public function testGetCombinesRegistryLifecycleStatusWithOwnOverlayData(): void
    {
        $registry = $this->registry();
        $environments = new SqliteEnvironments($this->tempPath('db'), $registry);
        $registered = $environments->registerProfile($this->entryFor(['overlay_refs' => ['overlay_a']]));

        $record = $environments->get($registered['environment_id']);

        $this->assertSame('production', $record['name']);
        $this->assertSame('Registered', $record['lifecycle_status']);
        $this->assertSame(['overlay_a'], $record['overlay_refs']);
    }

    public function testGetUnknownEnvironmentReturnsNull(): void
    {
        $environments = new SqliteEnvironments($this->tempPath('db'), $this->registry());

        $this->assertNull($environments->get('ghost'));
    }

    // --- updateOverlay() ---

    public function testUpdateOverlayOnUnknownEnvironmentIsInvalid(): void
    {
        $environments = new SqliteEnvironments($this->tempPath('db'), $this->registry());

        $result = $environments->updateOverlay('ghost', ['overlay_a'], 'admin_console');

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testUpdateOverlayRejectsDuplicates(): void
    {
        $environments = new SqliteEnvironments($this->tempPath('db'), $this->registry());
        $registered = $environments->registerProfile($this->entryFor());

        $result = $environments->updateOverlay($registered['environment_id'], ['overlay_a', 'overlay_a'], 'admin_console');

        $this->assertSame('rejected', $result['outcome']);
    }

    public function testUpdateOverlaySucceedsAndReplacesTheOverlayList(): void
    {
        $environments = new SqliteEnvironments($this->tempPath('db'), $this->registry());
        $registered = $environments->registerProfile($this->entryFor(['overlay_refs' => ['overlay_a']]));

        $result = $environments->updateOverlay($registered['environment_id'], ['overlay_b', 'overlay_c'], 'admin_console');

        $this->assertSame('updated', $result['outcome']);
        $record = $environments->get($registered['environment_id']);
        $this->assertSame(['overlay_b', 'overlay_c'], $record['overlay_refs']);
    }

    // --- real ConfigurationAudit composition ---

    public function testRegistrationRecordsAnEnvironmentOverlayChangedEvent(): void
    {
        $audit = new SqliteConfigurationAudit($this->tempPath('env-audit'));
        $environments = new SqliteEnvironments($this->tempPath('db'), $this->registry(), null, $audit);

        $registered = $environments->registerProfile($this->entryFor());

        $history = $audit->history($registered['environment_id']);
        $this->assertSame('Environment Overlay Changed', $history[0]['event']);
    }

    public function testUpdateOverlayRecordsASecondEvent(): void
    {
        $audit = new SqliteConfigurationAudit($this->tempPath('env-audit'));
        $environments = new SqliteEnvironments($this->tempPath('db'), $this->registry(), null, $audit);
        $registered = $environments->registerProfile($this->entryFor());

        $environments->updateOverlay($registered['environment_id'], ['overlay_a'], 'admin_console');

        $history = $environments->history($registered['environment_id']);
        $this->assertCount(2, $history);
    }

    // --- real ConfigurationValidator composition, for a declared parent ---

    public function testRegistrationWithAParentProfileComposesTheRealValidator(): void
    {
        $registry = $this->registry();
        $validator = new SqliteConfigurationValidator($this->tempPath('validator'), new EngineValidation(), $registry);
        $environments = new SqliteEnvironments($this->tempPath('db'), $registry, $validator);
        $base = $environments->registerProfile($this->entryFor(['name' => 'base']));

        $result = $environments->registerProfile($this->entryFor(['name' => 'staging', 'parent_profile' => $base['environment_id']]));

        $record = $environments->get($result['environment_id']);
        $this->assertNotNull($record['validation_ref']);
    }
}
