<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;
use SquirrelForge\Resilience\SqliteFailureDetector;

final class SqliteFailureDetectorTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        $this->databasePath = sys_get_temp_dir() . '/squirrelforge-failures-' . bin2hex(random_bytes(8)) . '.sqlite';
    }

    protected function tearDown(): void
    {
        foreach ([$this->databasePath, $this->databasePath . '-shm', $this->databasePath . '-wal'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testDetectUsesDefaultSeverityForKnownSignalAndDispatchesEvent(): void
    {
        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        $events->listen('failure.detected', new CallbackEventListener(
            function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }
        ));

        $detector = new SqliteFailureDetector($this->databasePath, $events);
        $result = $detector->detect('integration', 'dependency_failure', ['affected_components' => ['payments-api']]);

        $this->assertSame('error', $result['severity']);
        $this->assertSame('ungoverned', $result['governance_status']);
        $this->assertSame(['payments-api'], $result['impact']['affected_components']);
        $this->assertSame('prompt', $result['impact']['recovery_urgency']);
        $this->assertFalse($result['impact']['service_availability_at_risk']);
        $this->assertCount(1, $captured);
        $this->assertSame($result['detection_ref'], $captured[0]->getPayload()['detection_ref']);
    }

    public function testExplicitSeverityIsHonoredWhenValid(): void
    {
        $detector = new SqliteFailureDetector($this->databasePath);
        $result = $detector->detect('security', 'security_event', [], 'emergency');

        $this->assertSame('emergency', $result['severity']);
        $this->assertTrue($result['impact']['service_availability_at_risk']);
        $this->assertSame('immediate', $result['impact']['recovery_urgency']);
    }

    public function testUnknownSignalResolvesToConservativeCriticalSeverity(): void
    {
        $detector = new SqliteFailureDetector($this->databasePath);
        $result = $detector->detect('platform', 'unrecognized_signal');

        $this->assertSame('critical', $result['severity']);
    }

    public function testInvalidExplicitSeverityIsNotSuppressedButDowngradedConservatively(): void
    {
        $detector = new SqliteFailureDetector($this->databasePath);
        $result = $detector->detect('workflow', 'error_rate', [], 'not-a-real-severity');

        $this->assertSame('critical', $result['severity']);
    }

    public function testDetectionRetrievesAPersistedRecordByRef(): void
    {
        $detector = new SqliteFailureDetector($this->databasePath);
        $created = $detector->detect('agent', 'timeout_rate', ['affected_workflows' => ['release-pipeline']]);

        $fetched = $detector->detection($created['detection_ref']);

        $this->assertNotNull($fetched);
        $this->assertSame('warning', $fetched['severity']);
        $this->assertSame(['release-pipeline'], $fetched['impact']['affected_workflows']);
        $this->assertSame([], $fetched['context']['affected_components'] ?? []);
    }

    public function testDetectionReturnsNullForUnknownRef(): void
    {
        $detector = new SqliteFailureDetector($this->databasePath);

        $this->assertNull($detector->detection('failure_detection_does_not_exist'));
    }

    public function testRecentOrdersMostRecentFirstAndRespectsLimit(): void
    {
        $detector = new SqliteFailureDetector($this->databasePath);
        $detector->detect('platform', 'error_rate');
        $detector->detect('platform', 'latency_spike');
        $detector->detect('platform', 'missing_heartbeat');

        $recent = $detector->recent(2);

        $this->assertCount(2, $recent);
        $this->assertSame('missing_heartbeat', $recent[0]['signal']);
        $this->assertSame('latency_spike', $recent[1]['signal']);
    }
}
