<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Security\SqliteSecurityGovernance;
use SquirrelForge\Security\SqliteSecurityManager;
use SquirrelForge\Security\SqliteSecurityMonitor;

final class SqliteSecurityManagerTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-security-manager-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    /**
     * @return array{source_reference: string, item_type: string}
     */
    private function item(array $overrides = []): array
    {
        return array_replace([
            'source_reference' => 'ref_1',
            'item_type' => 'governance_reference',
        ], $overrides);
    }

    public function testRouteRejectsAMissingRequiredField(): void
    {
        $manager = new SqliteSecurityManager($this->tempPath('mgr'));

        $result = $manager->route(['source_reference' => 'ref_1']);

        $this->assertSame('rejected', $result['routing_outcome']);
    }

    public function testRouteRejectsAnUnrecognizedItemType(): void
    {
        $manager = new SqliteSecurityManager($this->tempPath('mgr'));

        $result = $manager->route($this->item(['item_type' => 'astrology']));

        $this->assertSame('rejected', $result['routing_outcome']);
    }

    public function testRouteReportsUnavailableWhenNoSpecialistIsConfigured(): void
    {
        $manager = new SqliteSecurityManager($this->tempPath('mgr'));

        $result = $manager->route($this->item());

        $this->assertSame('unavailable', $result['routing_outcome']);
        $this->assertSame('security_governance', $result['target_component']);
    }

    public function testThreatAndIncidentItemsAreAlwaysUnavailable(): void
    {
        $manager = new SqliteSecurityManager($this->tempPath('mgr'));

        $threat = $manager->route($this->item(['item_type' => 'threat_reference']));
        $incident = $manager->route($this->item(['item_type' => 'incident_reference']));

        $this->assertSame('unavailable', $threat['routing_outcome']);
        $this->assertSame('unavailable', $incident['routing_outcome']);
    }

    public function testRouteSucceedsWhenTheSpecialistIsConfigured(): void
    {
        $governance = new SqliteSecurityGovernance($this->tempPath('gov'));
        $manager = new SqliteSecurityManager(
            $this->tempPath('mgr'),
            securityGovernance: $governance
        );

        $result = $manager->route($this->item());

        $this->assertSame('routed', $result['routing_outcome']);
        $this->assertSame('security_governance', $result['target_component']);
    }

    public function testRoutingToSecurityMonitorSucceedsWhenConfigured(): void
    {
        $monitor = new SqliteSecurityMonitor($this->tempPath('monitor'));
        $manager = new SqliteSecurityManager(
            $this->tempPath('mgr'),
            securityMonitor: $monitor
        );

        $result = $manager->route($this->item(['item_type' => 'monitoring_reference']));

        $this->assertSame('routed', $result['routing_outcome']);
    }

    public function testEveryRoutedItemPreservesOwnerSuppliedMetadataAndCorrelationId(): void
    {
        $governance = new SqliteSecurityGovernance($this->tempPath('gov'));
        $manager = new SqliteSecurityManager($this->tempPath('mgr'), securityGovernance: $governance);

        $result = $manager->route($this->item([
            'source_component' => 'compliance_manager',
            'severity' => 'high',
            'category' => 'exception_request',
            'status' => 'pending',
            'policy_reference' => 'policy_1',
            'correlation_id' => 'correlation_1',
        ]));
        $record = $manager->get($result['operation_id']);

        $this->assertSame('compliance_manager', $record['source_component']);
        $this->assertSame('high', $record['severity']);
        $this->assertSame('exception_request', $record['category']);
        $this->assertSame('pending', $record['status']);
        $this->assertSame('policy_1', $record['policy_reference']);
        $this->assertSame('correlation_1', $record['correlation_id']);
    }

    public function testEveryRoutingAttemptIsRecordedIncludingUnavailableAndRejected(): void
    {
        $manager = new SqliteSecurityManager($this->tempPath('mgr'));
        $manager->route($this->item());
        $manager->route(['item_type' => 'astrology', 'source_reference' => 'ref_2']);

        $this->assertCount(2, $manager->recent());
    }
}
