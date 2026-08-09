<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Agent;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Agent\SqliteAgentMonitor;
use SquirrelForge\Observability\HealthReporter;
use SquirrelForge\Observability\SqliteAlertManager;

final class SqliteAgentMonitorTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-agent-monitor-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function alertManager(): SqliteAlertManager
    {
        return new SqliteAlertManager($this->tempPath('alerts'));
    }

    // --- shape validation ---

    public function testMissingAgentIdIsInvalid(): void
    {
        $monitor = new SqliteAgentMonitor($this->tempPath('db'));

        $result = $monitor->monitor(['agent_id' => '']);

        $this->assertSame('invalid', $result['outcome']);
    }

    // --- no HealthReporter composed: honest UNKNOWN, never a guessed default ---

    public function testNoHealthReporterComposedIsUnknown(): void
    {
        $monitor = new SqliteAgentMonitor($this->tempPath('db'));

        $result = $monitor->monitor(['agent_id' => 'agent_1']);

        $this->assertSame('monitored', $result['outcome']);
        $this->assertSame('UNKNOWN', $result['status']);
        $this->assertFalse($result['alert_requested']);
    }

    public function testHealthReporterWithNoEvidenceSourcesIsUnknown(): void
    {
        // A HealthReporter with none of its own evidence sources configured has nothing to reason from.
        $monitor = new SqliteAgentMonitor($this->tempPath('db'), new HealthReporter());

        $result = $monitor->monitor(['agent_id' => 'agent_1']);

        $this->assertSame('UNKNOWN', $result['status']);
    }

    // --- real HealthReporter state -> this spec's own Health Status mapping ---

    public function testNoOpenAlertsMapsToNormal(): void
    {
        $alerts = $this->alertManager();
        $monitor = new SqliteAgentMonitor($this->tempPath('db'), new HealthReporter($alerts));

        $result = $monitor->monitor(['agent_id' => 'agent_1']);

        $this->assertSame('NORMAL', $result['status']);
        $this->assertFalse($result['alert_requested']);
    }

    public function testANonCriticalOpenAlertMapsToDegraded(): void
    {
        $alerts = $this->alertManager();
        $alerts->create('agent_1', 'workload', 'warning', ['note' => 'elevated queue depth']);
        $monitor = new SqliteAgentMonitor($this->tempPath('db'), new HealthReporter($alerts));

        $result = $monitor->monitor(['agent_id' => 'agent_1']);

        $this->assertSame('DEGRADED', $result['status']);
        $this->assertFalse($result['alert_requested']);
    }

    public function testACriticalOpenAlertMapsToCriticalAndRequestsAnAlert(): void
    {
        $alerts = $this->alertManager();
        $alerts->create('agent_1', 'workload', 'critical', ['note' => 'repeated task failures']);
        $monitor = new SqliteAgentMonitor($this->tempPath('db'), new HealthReporter($alerts), $alerts);

        $result = $monitor->monitor(['agent_id' => 'agent_1']);

        $this->assertSame('CRITICAL', $result['status']);
        $this->assertTrue($result['alert_requested']);
        $this->assertNotNull($result['alert_id']);

        $requestedAlert = $alerts->get($result['alert_id']);
        $this->assertSame('agent_health', $requestedAlert['category']);
    }

    public function testCriticalWithoutAnAlertManagerComposedDoesNotRequestAnAlert(): void
    {
        $alerts = $this->alertManager();
        $alerts->create('agent_1', 'workload', 'critical', ['note' => 'repeated task failures']);
        // Only wired into HealthReporter for evidence -- not composed as this class's own alertManager.
        $monitor = new SqliteAgentMonitor($this->tempPath('db'), new HealthReporter($alerts));

        $result = $monitor->monitor(['agent_id' => 'agent_1']);

        $this->assertSame('CRITICAL', $result['status']);
        $this->assertFalse($result['alert_requested']);
        $this->assertNull($result['alert_id']);
    }

    public function testDegradedNeverRequestsAnAlertEvenWithAlertManagerComposed(): void
    {
        // DEGRADED means the agent "remains eligible for work" -- not the spec's own definition of a breach.
        $alerts = $this->alertManager();
        $alerts->create('agent_1', 'workload', 'warning', ['note' => 'elevated queue depth']);
        $monitor = new SqliteAgentMonitor($this->tempPath('db'), new HealthReporter($alerts), $alerts);

        $result = $monitor->monitor(['agent_id' => 'agent_1']);

        $this->assertSame('DEGRADED', $result['status']);
        $this->assertFalse($result['alert_requested']);
    }

    // --- currentHealth() / history() ---

    public function testCurrentHealthIsNullForAnUnmonitoredAgent(): void
    {
        $monitor = new SqliteAgentMonitor($this->tempPath('db'));

        $this->assertNull($monitor->currentHealth('ghost'));
    }

    public function testCurrentHealthReflectsTheMostRecentMonitoringEvent(): void
    {
        $alerts = $this->alertManager();
        $monitor = new SqliteAgentMonitor($this->tempPath('db'), new HealthReporter($alerts));

        $monitor->monitor(['agent_id' => 'agent_1']);
        $alerts->create('agent_1', 'workload', 'critical', ['note' => 'now unhealthy']);
        $monitor->monitor(['agent_id' => 'agent_1']);

        $current = $monitor->currentHealth('agent_1');

        $this->assertSame('CRITICAL', $current['status']);
    }

    public function testHistoryPreservesEveryMonitoringEventForAnAgent(): void
    {
        $alerts = $this->alertManager();
        $monitor = new SqliteAgentMonitor($this->tempPath('db'), new HealthReporter($alerts));

        $monitor->monitor(['agent_id' => 'agent_1']);
        $alerts->create('agent_1', 'workload', 'critical', ['note' => 'degraded']);
        $monitor->monitor(['agent_id' => 'agent_1']);
        $monitor->monitor(['agent_id' => 'agent_2']);

        $history = $monitor->history('agent_1');

        $this->assertCount(2, $history);
        $this->assertSame('NORMAL', $history[0]['status']);
        $this->assertSame('CRITICAL', $history[1]['status']);
    }
}
