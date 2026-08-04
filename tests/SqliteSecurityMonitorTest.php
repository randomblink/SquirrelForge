<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Security\SqliteComplianceManager;
use SquirrelForge\Security\SqliteSecurityMonitor;
use SquirrelForge\Security\SqliteVulnerabilityManager;

final class SqliteSecurityMonitorTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-security-monitor-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    public function testRunCycleWithNoDependenciesConfiguredIsHealthy(): void
    {
        $monitor = new SqliteSecurityMonitor($this->tempPath('monitor'));

        $result = $monitor->runCycle();

        $this->assertSame([], $result['alerts']);
        $this->assertSame('not_configured', $result['compliance_status']);
        $this->assertSame('healthy', $result['security_posture']);
    }

    public function testThresholdExceededGeneratesAnAlert(): void
    {
        $monitor = new SqliteSecurityMonitor($this->tempPath('monitor'));

        $result = $monitor->runCycle(['metrics' => ['authentication_failure_rate' => 12], 'thresholds' => ['authentication_failure_rate' => 5]]);

        $this->assertCount(1, $result['alerts']);
        $this->assertSame('at_risk', $result['security_posture']);
    }

    public function testMetricWithinThresholdGeneratesNoAlert(): void
    {
        $monitor = new SqliteSecurityMonitor($this->tempPath('monitor'));

        $result = $monitor->runCycle(['metrics' => ['authentication_failure_rate' => 2], 'thresholds' => ['authentication_failure_rate' => 5]]);

        $this->assertSame([], $result['alerts']);
        $this->assertSame('healthy', $result['security_posture']);
    }

    public function testAggregatesRealComplianceStatusAcrossRequirements(): void
    {
        $compliance = new SqliteComplianceManager($this->tempPath('compliance'));
        $compliance->registerRequirement('req_1', 'security', 'Requirement one.');
        $compliance->registerRequirement('req_2', 'security', 'Requirement two.');
        $compliance->assess('req_1', 'Compliant');
        $compliance->assess('req_2', 'Non-Compliant');
        $monitor = new SqliteSecurityMonitor($this->tempPath('monitor'), $compliance);

        $result = $monitor->runCycle();

        $this->assertSame('Non-Compliant', $result['compliance_status']);
        $this->assertSame('critical', $result['security_posture']);
        $this->assertContains('Compliance status has regressed to Non-Compliant.', $result['alerts']);
    }

    public function testCompliantRequirementsProduceNoComplianceAlert(): void
    {
        $compliance = new SqliteComplianceManager($this->tempPath('compliance'));
        $compliance->registerRequirement('req_1', 'security', 'Requirement one.');
        $compliance->assess('req_1', 'Compliant');
        $monitor = new SqliteSecurityMonitor($this->tempPath('monitor'), $compliance);

        $result = $monitor->runCycle();

        $this->assertSame('Compliant', $result['compliance_status']);
        $this->assertSame('healthy', $result['security_posture']);
    }

    public function testAggregatesRealUnresolvedCriticalVulnerabilities(): void
    {
        $vulnerabilities = new SqliteVulnerabilityManager($this->tempPath('vuln'));
        $reported = $vulnerabilities->report('software', 'critical', 'auth_service');
        $vulnerabilities->report('software', 'low', 'auth_service');
        $monitor = new SqliteSecurityMonitor($this->tempPath('monitor'), null, $vulnerabilities);

        $result = $monitor->runCycle();

        $this->assertSame(1, $result['vulnerability_summary']['critical_unresolved']);
        $this->assertSame('critical', $result['security_posture']);
        $this->assertNotEmpty($reported['vulnerability_id']);
    }

    public function testClosedCriticalVulnerabilityIsNotCountedAsUnresolved(): void
    {
        $vulnerabilities = new SqliteVulnerabilityManager($this->tempPath('vuln'));
        $reported = $vulnerabilities->report('software', 'critical', 'auth_service');
        $id = $reported['vulnerability_id'];
        $vulnerabilities->triage($id);
        $vulnerabilities->assignRemediation($id, 'owner_1');
        $vulnerabilities->startRemediation($id);
        $vulnerabilities->submitForValidation($id);
        $vulnerabilities->attachEvidence($id, 'validation_ref_1');
        $vulnerabilities->verify($id);
        $vulnerabilities->close($id);
        $monitor = new SqliteSecurityMonitor($this->tempPath('monitor'), null, $vulnerabilities);

        $result = $monitor->runCycle();

        $this->assertSame(0, $result['vulnerability_summary']['critical_unresolved']);
        $this->assertSame('healthy', $result['security_posture']);
    }

    public function testEveryCycleIsRecordedAndRetrievable(): void
    {
        $monitor = new SqliteSecurityMonitor($this->tempPath('monitor'));

        $result = $monitor->runCycle(['components_monitored' => ['authentication_manager', 'authorization_manager']]);
        $record = $monitor->get($result['monitoring_id']);

        $this->assertSame(['authentication_manager', 'authorization_manager'], $record['components_monitored']);
        $this->assertSame('monitored', $record['outcome']);
    }

    public function testRecentReturnsRecordedCycles(): void
    {
        $monitor = new SqliteSecurityMonitor($this->tempPath('monitor'));
        $monitor->runCycle();
        $monitor->runCycle();

        $this->assertCount(2, $monitor->recent());
    }
}
