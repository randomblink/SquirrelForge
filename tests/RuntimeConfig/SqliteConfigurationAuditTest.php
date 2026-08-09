<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\RuntimeConfig;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SquirrelForge\Observability\AuditTrail;
use SquirrelForge\RuntimeConfig\SqliteConfigurationAudit;
use SquirrelForge\Storage\SqliteDocumentStorage;

final class SqliteConfigurationAuditTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-configuration-audit-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function auditTrail(): AuditTrail
    {
        return new AuditTrail(new SqliteDocumentStorage($this->tempPath('documents')));
    }

    /**
     * @return array<string, mixed>
     */
    private function entryFor(array $overrides = []): array
    {
        return array_replace([
            'event' => 'Configuration Registered',
            'configuration_ref' => 'config_timeout_seconds',
            'actor_ref' => 'admin_console',
        ], $overrides);
    }

    // --- shape validation ---

    public function testUnrecognizedEventIsInvalid(): void
    {
        $audit = new SqliteConfigurationAudit($this->tempPath('db'));

        $result = $audit->record($this->entryFor(['event' => 'Configuration Renamed']));

        $this->assertSame('invalid', $result['outcome']);
        $this->assertStringContainsString('Audited Configuration Events', $result['error']);
    }

    public function testMissingConfigurationRefIsInvalid(): void
    {
        $audit = new SqliteConfigurationAudit($this->tempPath('db'));

        $result = $audit->record($this->entryFor(['configuration_ref' => '']));

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testMissingActorRefIsInvalid(): void
    {
        $audit = new SqliteConfigurationAudit($this->tempPath('db'));

        $result = $audit->record($this->entryFor(['actor_ref' => null]));

        $this->assertSame('invalid', $result['outcome']);
    }

    // --- the real closed Audited Configuration Events vocabulary ---

    /**
     * @return array<int, array{0: string}>
     */
    public static function eventProvider(): array
    {
        return [
            ['Configuration Registered'], ['Configuration Updated'], ['Configuration Deprecated'],
            ['Configuration Archived'], ['Validation Recorded'], ['Secret Lifecycle Changed'],
            ['Feature Flag Changed'], ['Policy Configuration Changed'], ['Environment Overlay Changed'],
        ];
    }

    #[DataProvider('eventProvider')]
    public function testEachRealEventIsRecorded(string $event): void
    {
        $audit = new SqliteConfigurationAudit($this->tempPath('db'));

        $result = $audit->record($this->entryFor(['event' => $event]));

        $this->assertSame('recorded', $result['outcome']);
        $this->assertNotNull($result['history_ref']);
    }

    // --- no AuditTrail composed: still records the configuration-domain history ---

    public function testRecordingWithoutAuditTrailComposedStillSucceeds(): void
    {
        $audit = new SqliteConfigurationAudit($this->tempPath('db'));

        $result = $audit->record($this->entryFor());

        $this->assertSame('recorded', $result['outcome']);
        $this->assertNull($result['audit_evidence_ref']);
    }

    // --- real AuditTrail composition ---

    public function testRecordingWithAuditTrailComposedEmitsARealAuditEvidenceReference(): void
    {
        $audit = new SqliteConfigurationAudit($this->tempPath('db'), $this->auditTrail());

        $result = $audit->record($this->entryFor());

        $this->assertSame('recorded', $result['outcome']);
        $this->assertNotNull($result['audit_evidence_ref']);
    }

    public function testAuditEvidenceCarriesTheConfigurationEvidenceFields(): void
    {
        $auditTrail = $this->auditTrail();
        $audit = new SqliteConfigurationAudit($this->tempPath('db'), $auditTrail);

        $result = $audit->record($this->entryFor([
            'version_ref' => 'v2',
            'reason' => 'increase timeout for slow networks',
            'prior_state' => ['value' => 30],
            'new_state' => ['value' => 60],
        ]));

        $auditRecord = $auditTrail->retrieve($result['audit_evidence_ref']);

        $this->assertTrue($auditRecord['found']);
        $this->assertSame('increase timeout for slow networks', $auditRecord['audit']['reason']);
        $this->assertSame(['value' => 30], $auditRecord['audit']['evidence']['prior_state']);
        $this->assertSame(['value' => 60], $auditRecord['audit']['evidence']['new_state']);
    }

    // --- recordRollback(): reference only, never an action ---

    public function testRecordRollbackRequiresConfigurationRequestAndActorRefs(): void
    {
        $audit = new SqliteConfigurationAudit($this->tempPath('db'));

        $result = $audit->recordRollback('', '', '');

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testRecordRollbackPreservesTheRequestBeforeAResultExists(): void
    {
        $audit = new SqliteConfigurationAudit($this->tempPath('db'));

        $result = $audit->recordRollback('config_timeout_seconds', 'rollback_request_1', 'admin_console');

        $this->assertSame('recorded', $result['outcome']);
        $history = $audit->rollbackHistory('config_timeout_seconds');
        $this->assertSame('rollback_request_1', $history[0]['rollback_request_ref']);
        $this->assertNull($history[0]['rollback_result_ref']);
    }

    public function testRecordRollbackCanCarryAResultReference(): void
    {
        $audit = new SqliteConfigurationAudit($this->tempPath('db'));

        $audit->recordRollback('config_timeout_seconds', 'rollback_request_1', 'admin_console', 'rollback_result_1');

        $history = $audit->rollbackHistory('config_timeout_seconds');
        $this->assertSame('rollback_result_1', $history[0]['rollback_result_ref']);
    }

    // --- get() / history() ---

    public function testGetUnknownHistoryRefReturnsNull(): void
    {
        $audit = new SqliteConfigurationAudit($this->tempPath('db'));

        $this->assertNull($audit->get('ghost'));
    }

    public function testHistoryPreservesEveryChangeForAConfigurationItemInOrder(): void
    {
        $audit = new SqliteConfigurationAudit($this->tempPath('db'));
        $audit->record($this->entryFor(['event' => 'Configuration Registered']));
        $audit->record($this->entryFor(['event' => 'Configuration Updated']));
        $audit->record($this->entryFor(['configuration_ref' => 'config_other', 'event' => 'Configuration Registered']));

        $history = $audit->history('config_timeout_seconds');

        $this->assertCount(2, $history);
        $this->assertSame('Configuration Registered', $history[0]['event']);
        $this->assertSame('Configuration Updated', $history[1]['event']);
    }

    public function testGetReturnsTheFullRecordedEntry(): void
    {
        $audit = new SqliteConfigurationAudit($this->tempPath('db'));
        $result = $audit->record($this->entryFor(['prior_state' => ['value' => 30], 'new_state' => ['value' => 60]]));

        $record = $audit->get($result['history_ref']);

        $this->assertSame(['value' => 30], $record['prior_state']);
        $this->assertSame(['value' => 60], $record['new_state']);
    }
}
