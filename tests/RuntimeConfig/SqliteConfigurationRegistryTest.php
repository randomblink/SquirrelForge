<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\RuntimeConfig;

use PHPUnit\Framework\TestCase;
use SquirrelForge\RuntimeConfig\SqliteConfigurationAudit;
use SquirrelForge\RuntimeConfig\SqliteConfigurationRegistry;

final class SqliteConfigurationRegistryTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-configuration-registry-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function audit(): SqliteConfigurationAudit
    {
        return new SqliteConfigurationAudit($this->tempPath('audit'));
    }

    /**
     * @return array<string, mixed>
     */
    private function entryFor(array $overrides = []): array
    {
        return array_replace([
            'name' => 'timeout_seconds',
            'owner' => 'engine_maintainers',
            'scope' => 'global',
            'data_type' => 'integer',
            'actor_ref' => 'admin_console',
        ], $overrides);
    }

    // --- register(): shape validation ---

    public function testMissingNameIsInvalid(): void
    {
        $registry = new SqliteConfigurationRegistry($this->tempPath('db'), $this->audit());

        $result = $registry->register($this->entryFor(['name' => '']));

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testMissingActorRefIsInvalid(): void
    {
        $registry = new SqliteConfigurationRegistry($this->tempPath('db'), $this->audit());

        $result = $registry->register($this->entryFor(['actor_ref' => null]));

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testInvalidInitialStateIsRejected(): void
    {
        $registry = new SqliteConfigurationRegistry($this->tempPath('db'), $this->audit());

        $result = $registry->register($this->entryFor(['initial_state' => 'Active']));

        $this->assertSame('invalid', $result['outcome']);
        $this->assertStringContainsString('may start in', $result['error']);
    }

    // --- Rule 3: audit is required, not optional ---

    public function testRegisterWithoutConfigurationAuditIsRejected(): void
    {
        $registry = new SqliteConfigurationRegistry($this->tempPath('db'));

        $result = $registry->register($this->entryFor());

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('must create configuration-domain history references', $result['error']);
    }

    // --- register(): success ---

    public function testRegisterDefaultsToRegisteredState(): void
    {
        $registry = new SqliteConfigurationRegistry($this->tempPath('db'), $this->audit());

        $result = $registry->register($this->entryFor());

        $this->assertSame('registered', $result['outcome']);
        $this->assertSame('Registered', $result['state']);
        $this->assertNotNull($result['configuration_ref']);
    }

    public function testRegisterCanStartAsDraft(): void
    {
        $registry = new SqliteConfigurationRegistry($this->tempPath('db'), $this->audit());

        $result = $registry->register($this->entryFor(['initial_state' => 'Draft']));

        $this->assertSame('Draft', $result['state']);
    }

    public function testRegisterEmitsARealAuditHistoryRecord(): void
    {
        $audit = $this->audit();
        $registry = new SqliteConfigurationRegistry($this->tempPath('db'), $audit);

        $result = $registry->register($this->entryFor());

        $history = $audit->history($result['configuration_ref']);
        $this->assertCount(1, $history);
        $this->assertSame('Configuration Registered', $history[0]['event']);
    }

    // --- transition(): shape validation ---

    public function testUnrecognizedStateIsInvalid(): void
    {
        $registry = new SqliteConfigurationRegistry($this->tempPath('db'), $this->audit());
        $registered = $registry->register($this->entryFor());

        $result = $registry->transition($registered['configuration_ref'], 'Superseded', 'admin_console');

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testUnknownConfigurationRefIsInvalid(): void
    {
        $registry = new SqliteConfigurationRegistry($this->tempPath('db'), $this->audit());

        $result = $registry->transition('ghost', 'Active', 'admin_console');

        $this->assertSame('invalid', $result['outcome']);
    }

    // --- transition(): the few real, justified guards ---

    public function testArchivedIsTerminal(): void
    {
        $audit = $this->audit();
        $registry = new SqliteConfigurationRegistry($this->tempPath('db'), $audit);
        $registered = $registry->register($this->entryFor());
        $ref = $registered['configuration_ref'];
        $registry->transition($ref, 'Active', 'admin_console');
        $registry->transition($ref, 'Deprecated', 'admin_console');
        $registry->transition($ref, 'Archived', 'admin_console');

        $result = $registry->transition($ref, 'Active', 'admin_console');

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('terminal', $result['error']);
    }

    public function testCannotRegressToDraftOnceLeft(): void
    {
        $registry = new SqliteConfigurationRegistry($this->tempPath('db'), $this->audit());
        $registered = $registry->register($this->entryFor());

        $result = $registry->transition($registered['configuration_ref'], 'Draft', 'admin_console');

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('regress', $result['error']);
    }

    public function testDraftMayOnlyAdvanceToRegistered(): void
    {
        $registry = new SqliteConfigurationRegistry($this->tempPath('db'), $this->audit());
        $registered = $registry->register($this->entryFor(['initial_state' => 'Draft']));

        $result = $registry->transition($registered['configuration_ref'], 'Active', 'admin_console');

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('only advance to Registered', $result['error']);
    }

    public function testDraftAdvancingToRegisteredSucceeds(): void
    {
        $registry = new SqliteConfigurationRegistry($this->tempPath('db'), $this->audit());
        $registered = $registry->register($this->entryFor(['initial_state' => 'Draft']));

        $result = $registry->transition($registered['configuration_ref'], 'Registered', 'admin_console');

        $this->assertSame('transitioned', $result['outcome']);
        $this->assertSame('Registered', $result['state']);
    }

    // --- transition(): real state -> audit event mapping ---

    public function testTransitionWithoutConfigurationAuditIsRejected(): void
    {
        $sharedDatabase = $this->tempPath('shared');
        $registered = (new SqliteConfigurationRegistry($sharedDatabase, $this->audit()))->register($this->entryFor());
        // A second registry instance over the same database, but with no Configuration Audit composed.
        $registryWithoutAudit = new SqliteConfigurationRegistry($sharedDatabase);

        $result = $registryWithoutAudit->transition($registered['configuration_ref'], 'Active', 'admin_console');

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('must create configuration-domain history references', $result['error']);
    }

    public function testActiveTransitionRecordsConfigurationUpdated(): void
    {
        $audit = $this->audit();
        $registry = new SqliteConfigurationRegistry($this->tempPath('db'), $audit);
        $registered = $registry->register($this->entryFor());

        $registry->transition($registered['configuration_ref'], 'Active', 'admin_console', 'promote to active');

        $history = $audit->history($registered['configuration_ref']);
        $this->assertSame('Configuration Updated', $history[1]['event']);
    }

    public function testDeprecatedTransitionRecordsConfigurationDeprecated(): void
    {
        $audit = $this->audit();
        $registry = new SqliteConfigurationRegistry($this->tempPath('db'), $audit);
        $registered = $registry->register($this->entryFor());
        $registry->transition($registered['configuration_ref'], 'Active', 'admin_console');

        $registry->transition($registered['configuration_ref'], 'Deprecated', 'admin_console', 'superseded by new setting');

        $history = $audit->history($registered['configuration_ref']);
        $this->assertSame('Configuration Deprecated', $history[2]['event']);
    }

    public function testArchivedTransitionRecordsConfigurationArchived(): void
    {
        $audit = $this->audit();
        $registry = new SqliteConfigurationRegistry($this->tempPath('db'), $audit);
        $registered = $registry->register($this->entryFor());
        $registry->transition($registered['configuration_ref'], 'Active', 'admin_console');
        $registry->transition($registered['configuration_ref'], 'Deprecated', 'admin_console');

        $registry->transition($registered['configuration_ref'], 'Archived', 'admin_console');

        $history = $audit->history($registered['configuration_ref']);
        $this->assertSame('Configuration Archived', $history[3]['event']);
    }

    // --- get() / all() ---

    public function testGetUnknownConfigurationRefReturnsNull(): void
    {
        $registry = new SqliteConfigurationRegistry($this->tempPath('db'), $this->audit());

        $this->assertNull($registry->get('ghost'));
    }

    public function testAllReturnsEveryRegisteredItem(): void
    {
        $audit = $this->audit();
        $registry = new SqliteConfigurationRegistry($this->tempPath('db'), $audit);
        $registry->register($this->entryFor(['name' => 'timeout_seconds']));
        $registry->register($this->entryFor(['name' => 'retry_limit']));

        $all = $registry->all();

        $this->assertCount(2, $all);
    }

    public function testAllFiltersByState(): void
    {
        $audit = $this->audit();
        $registry = new SqliteConfigurationRegistry($this->tempPath('db'), $audit);
        $registry->register($this->entryFor(['name' => 'timeout_seconds', 'initial_state' => 'Draft']));
        $registry->register($this->entryFor(['name' => 'retry_limit']));

        $drafts = $registry->all('Draft');

        $this->assertCount(1, $drafts);
        $this->assertSame('timeout_seconds', $drafts[0]['name']);
    }
}
