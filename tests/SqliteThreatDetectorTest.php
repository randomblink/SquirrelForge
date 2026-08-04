<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Security\SqliteIncidentManager;
use SquirrelForge\Security\SqliteThreatDetector;

final class SqliteThreatDetectorTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-threat-detector-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function detector(?SqliteIncidentManager $incidentManager = null): SqliteThreatDetector
    {
        return new SqliteThreatDetector($this->tempPath('threat'), $incidentManager);
    }

    public function testDetectRejectsAnEmptyEventSource(): void
    {
        $detector = $this->detector();

        $result = $detector->detect('', 'credential_misuse', 'high', 'rule_based_detection');

        $this->assertSame('invalid_event_source', $result['outcome']);
    }

    public function testDetectRejectsAnUnrecognizedCategory(): void
    {
        $detector = $this->detector();

        $result = $detector->detect('auth_service', 'astrology', 'high', 'rule_based_detection');

        $this->assertSame('invalid_category', $result['outcome']);
    }

    public function testDetectRejectsAnUnrecognizedSeverity(): void
    {
        $detector = $this->detector();

        $result = $detector->detect('auth_service', 'credential_misuse', 'astrology', 'rule_based_detection');

        $this->assertSame('invalid_severity', $result['outcome']);
    }

    public function testDetectRejectsAnUnrecognizedDetectionMethod(): void
    {
        $detector = $this->detector();

        $result = $detector->detect('auth_service', 'credential_misuse', 'high', 'astrology');

        $this->assertSame('invalid_detection_method', $result['outcome']);
    }

    public function testDetectRecordsARetrievableThreat(): void
    {
        $detector = $this->detector();

        $result = $detector->detect('auth_service', 'credential_misuse', 'medium', 'anomaly_detection', ['log_ref_1']);
        $record = $detector->get($result['threat_id']);

        $this->assertSame('detected', $result['outcome']);
        $this->assertSame('auth_service', $record['event_source']);
        $this->assertSame('credential_misuse', $record['category']);
        $this->assertSame('medium', $record['severity']);
        $this->assertSame('anomaly_detection', $record['detection_method']);
        $this->assertSame(['log_ref_1'], $record['evidence']);
        $this->assertSame('monitored', $record['final_outcome']);
        $this->assertNull($record['incident_intake_reference']);
    }

    public function testLowSeverityThreatsDoNotNotifyIncidentManagerEvenWhenConfigured(): void
    {
        $incidentManager = new SqliteIncidentManager($this->tempPath('incident'));
        $detector = $this->detector($incidentManager);

        $result = $detector->detect('auth_service', 'credential_misuse', 'low', 'anomaly_detection');

        $this->assertNull($result['incident_intake_reference']);
        $this->assertSame('monitored', $detector->get($result['threat_id'])['final_outcome']);
    }

    public function testHighSeverityThreatsNotifyIncidentManagerWhenConfigured(): void
    {
        $incidentManager = new SqliteIncidentManager($this->tempPath('incident'));
        $detector = $this->detector($incidentManager);

        $result = $detector->detect('auth_service', 'data_exfiltration', 'critical', 'threshold_monitoring');

        $this->assertNotNull($result['incident_intake_reference']);
        $incident = $incidentManager->get($result['incident_intake_reference']);
        $this->assertSame('Received', $incident['status']);
        $this->assertSame($result['threat_id'], $incident['threat_assessment_reference']);
        $this->assertNull($incident['category'], 'Threat Detector must never classify the incident it hands off.');
        $this->assertSame('handed_to_incident_manager', $detector->get($result['threat_id'])['final_outcome']);
    }

    public function testHighSeverityThreatsWithoutAConfiguredIncidentManagerStayMonitored(): void
    {
        $detector = $this->detector();

        $result = $detector->detect('auth_service', 'data_exfiltration', 'critical', 'threshold_monitoring');

        $this->assertNull($result['incident_intake_reference']);
        $this->assertSame('monitored', $detector->get($result['threat_id'])['final_outcome']);
    }

    public function testCorrelateLinksTwoTrackedThreats(): void
    {
        $detector = $this->detector();
        $first = $detector->detect('auth_service', 'credential_misuse', 'low', 'anomaly_detection');
        $second = $detector->detect('auth_service', 'privilege_escalation', 'low', 'anomaly_detection');

        $result = $detector->correlate($first['threat_id'], $second['threat_id']);

        $this->assertSame('correlated', $result['outcome']);
        $this->assertSame([$second['threat_id']], $detector->correlations($first['threat_id']));
    }

    public function testCorrelateRejectsAnUntrackedThreat(): void
    {
        $detector = $this->detector();
        $first = $detector->detect('auth_service', 'credential_misuse', 'low', 'anomaly_detection');

        $result = $detector->correlate($first['threat_id'], 'threat_unknown');

        $this->assertSame('not_found', $result['outcome']);
    }

    public function testAddEvidenceAppendsToExistingEvidence(): void
    {
        $detector = $this->detector();
        $reported = $detector->detect('auth_service', 'credential_misuse', 'low', 'anomaly_detection', ['log_ref_1']);

        $detector->addEvidence($reported['threat_id'], 'log_ref_2');

        $this->assertSame(['log_ref_1', 'log_ref_2'], $detector->get($reported['threat_id'])['evidence']);
    }

    public function testFindBySeverityAndCategoryFilterCorrectly(): void
    {
        $detector = $this->detector();
        $detector->detect('service_a', 'credential_misuse', 'critical', 'anomaly_detection');
        $detector->detect('service_b', 'privilege_escalation', 'low', 'anomaly_detection');

        $this->assertCount(1, $detector->findBySeverity('critical'));
        $this->assertCount(1, $detector->findByCategory('privilege_escalation'));
    }
}
