<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Security\SqliteIncidentManager;

final class SqliteIncidentManagerTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-incident-manager-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function manager(): SqliteIncidentManager
    {
        return new SqliteIncidentManager($this->tempPath('incident'));
    }

    public function testReceiveNotificationRejectsAnEmptySource(): void
    {
        $manager = $this->manager();

        $result = $manager->receiveNotification(['source' => '']);

        $this->assertSame('invalid_source', $result['outcome']);
    }

    public function testReceiveNotificationCreatesAnUnclassifiedReceivedRecord(): void
    {
        $manager = $this->manager();

        $result = $manager->receiveNotification(['source' => 'manual_report', 'threat_assessment_reference' => 'threat_1']);
        $record = $manager->get($result['incident_id']);

        $this->assertSame('received', $result['outcome']);
        $this->assertSame('Received', $record['status']);
        $this->assertNull($record['category']);
        $this->assertNull($record['severity']);
        $this->assertSame('threat_1', $record['threat_assessment_reference']);
    }

    public function testClassifyRejectsAnUnrecognizedCategory(): void
    {
        $manager = $this->manager();
        $received = $manager->receiveNotification(['source' => 'manual_report']);

        $result = $manager->classify($received['incident_id'], 'astrology', 'high');

        $this->assertSame('invalid_category', $result['outcome']);
    }

    public function testClassifyRejectsAnUnrecognizedSeverity(): void
    {
        $manager = $this->manager();
        $received = $manager->receiveNotification(['source' => 'manual_report']);

        $result = $manager->classify($received['incident_id'], 'data_breach', 'astrology');

        $this->assertSame('invalid_severity', $result['outcome']);
    }

    public function testClassifyMovesTheIncidentToClassified(): void
    {
        $manager = $this->manager();
        $received = $manager->receiveNotification(['source' => 'manual_report']);

        $result = $manager->classify($received['incident_id'], 'data_breach', 'high');
        $record = $manager->get($received['incident_id']);

        $this->assertSame('classified', $result['outcome']);
        $this->assertSame('Classified', $record['status']);
        $this->assertSame('data_breach', $record['category']);
        $this->assertSame('high', $record['severity']);
    }

    public function testStartInvestigationIsBlockedBeforeClassification(): void
    {
        $manager = $this->manager();
        $received = $manager->receiveNotification(['source' => 'manual_report']);

        $result = $manager->startInvestigation($received['incident_id']);

        $this->assertSame('blocked', $result['outcome']);
    }

    public function testMarkContainedIsBlockedWithoutAnyContainmentReference(): void
    {
        $manager = $this->manager();
        $id = $this->classifiedAndInvestigating($manager);

        $result = $manager->markContained($id);

        $this->assertSame('blocked', $result['outcome']);
    }

    public function testResolveIsBlockedWithoutAnyValidationEvidence(): void
    {
        $manager = $this->manager();
        $id = $this->classifiedAndInvestigating($manager);
        $manager->attachContainmentReference($id, 'containment_ref_1');
        $manager->markContained($id);

        $result = $manager->resolve($id);

        $this->assertSame('blocked', $result['outcome']);
    }

    public function testCloseIsBlockedBeforePostIncidentReview(): void
    {
        $manager = $this->manager();
        $id = $this->contained($manager);
        $manager->attachValidationEvidence($id, 'validation_ref_1');
        $manager->resolve($id);

        $result = $manager->close($id);

        $this->assertSame('blocked', $result['outcome']);
    }

    public function testFullLifecycleFromReceivedToClosed(): void
    {
        $manager = $this->manager();
        $received = $manager->receiveNotification(['source' => 'threat_detector', 'threat_assessment_reference' => 'threat_1']);
        $id = $received['incident_id'];

        $manager->classify($id, 'data_breach', 'critical');
        $manager->startInvestigation($id);
        $manager->attachContainmentReference($id, 'containment_ref_1');
        $manager->markContained($id);
        $manager->attachValidationEvidence($id, 'validation_ref_1');
        $manager->resolve($id);
        $reviewResult = $manager->conductPostIncidentReview($id, 'review_ref_1');
        $closeResult = $manager->close($id);

        $this->assertSame('reviewed', $reviewResult['outcome']);
        $this->assertSame('transitioned', $closeResult['outcome']);
        $record = $manager->get($id);
        $this->assertSame('Closed', $record['status']);
        $this->assertSame(['containment_ref_1'], $record['containment_references']);
        $this->assertSame(['validation_ref_1'], $record['validation_evidence']);
        $this->assertSame('review_ref_1', $record['post_incident_review_reference']);
    }

    public function testReopenRequiresANonEmptyReason(): void
    {
        $manager = $this->manager();
        $id = $this->closed($manager);

        $result = $manager->reopen($id, '');

        $this->assertSame('invalid_reason', $result['outcome']);
    }

    public function testReopenIsAllowedFromClosed(): void
    {
        $manager = $this->manager();
        $id = $this->closed($manager);

        $result = $manager->reopen($id, 'Regression discovered.');

        $this->assertSame('transitioned', $result['outcome']);
        $this->assertSame('Reopened', $manager->get($id)['status']);
    }

    public function testHistoryTracksEveryTransition(): void
    {
        $manager = $this->manager();
        $received = $manager->receiveNotification(['source' => 'manual_report']);
        $manager->classify($received['incident_id'], 'data_breach', 'high');

        $history = $manager->history($received['incident_id']);

        $this->assertCount(2, $history);
        $this->assertSame('Received', $history[0]['to_status']);
        $this->assertSame('Classified', $history[1]['to_status']);
    }

    public function testFindByStatusAndSeverityFilterCorrectly(): void
    {
        $manager = $this->manager();
        $a = $manager->receiveNotification(['source' => 'manual_report']);
        $b = $manager->receiveNotification(['source' => 'manual_report']);
        $manager->classify($a['incident_id'], 'data_breach', 'critical');
        $manager->classify($b['incident_id'], 'policy_violations', 'low');

        $this->assertCount(1, $manager->findBySeverity('critical'));
        $this->assertCount(2, $manager->findByStatus('Classified'));
    }

    private function classifiedAndInvestigating(SqliteIncidentManager $manager): string
    {
        $received = $manager->receiveNotification(['source' => 'manual_report']);
        $id = $received['incident_id'];
        $manager->classify($id, 'data_breach', 'high');
        $manager->startInvestigation($id);

        return $id;
    }

    private function contained(SqliteIncidentManager $manager): string
    {
        $id = $this->classifiedAndInvestigating($manager);
        $manager->attachContainmentReference($id, 'containment_ref_1');
        $manager->markContained($id);

        return $id;
    }

    private function closed(SqliteIncidentManager $manager): string
    {
        $id = $this->contained($manager);
        $manager->attachValidationEvidence($id, 'validation_ref_1');
        $manager->resolve($id);
        $manager->conductPostIncidentReview($id, 'review_ref_1');
        $manager->close($id);

        return $id;
    }
}
