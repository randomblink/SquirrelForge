<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;
use SquirrelForge\Observability\SqliteObservabilityGovernance;
use SquirrelForge\Observability\TelemetryCollector;

final class TelemetryCollectorTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-telemetry-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function validEvent(array $overrides = []): array
    {
        return array_merge([
            'event_id' => 'evt_1',
            'source' => 'workflow-engine',
            'signal_type' => 'log',
            'timestamp' => '2026-07-29T12:00:00Z',
            'correlation_id' => 'corr_1',
            'payload' => ['message' => 'hello'],
        ], $overrides);
    }

    public function testWellFormedEventWithoutGovernanceIsCollected(): void
    {
        $collector = new TelemetryCollector();

        $result = $collector->receive($this->validEvent());

        $this->assertSame('collected', $result['outcome']);
        $this->assertNotNull($result['telemetry_ref']);
        $this->assertSame(['message' => 'hello'], $result['normalized']['payload']);
    }

    public function testMissingRequiredFieldIsRejectedAsInvalidEnvelope(): void
    {
        $collector = new TelemetryCollector();
        $event = $this->validEvent();
        unset($event['source']);

        $result = $collector->receive($event);

        $this->assertSame('invalid_envelope', $result['outcome']);
        $this->assertStringContainsString('source', (string) $result['error']);
    }

    public function testUnparseableTimestampIsRejected(): void
    {
        $collector = new TelemetryCollector();

        $result = $collector->receive($this->validEvent(['timestamp' => 'not-a-timestamp']));

        $this->assertSame('invalid_envelope', $result['outcome']);
    }

    public function testProhibitedFieldInPayloadIsRejectedOutright(): void
    {
        $collector = new TelemetryCollector();

        $result = $collector->receive(
            $this->validEvent(['payload' => ['message' => 'hi', 'password' => 'secret123']]),
            ['prohibited_fields' => ['password']]
        );

        $this->assertSame('prohibited_payload', $result['outcome']);
        $this->assertStringContainsString('password', (string) $result['error']);
    }

    public function testUndefinedSignalTypeIsRejectedWhenGovernanceIsConfigured(): void
    {
        $governance = new SqliteObservabilityGovernance($this->tempPath('governance'));
        $collector = new TelemetryCollector($governance);

        $result = $collector->receive($this->validEvent());

        $this->assertSame('undefined_signal_type', $result['outcome']);
    }

    public function testDefinedSignalTypeIsCollectedAndCarriesClassification(): void
    {
        $governance = new SqliteObservabilityGovernance($this->tempPath('governance'));
        $governance->defineStandard('log', ['privacy_classification' => 'internal']);
        $collector = new TelemetryCollector($governance);

        $result = $collector->receive($this->validEvent());

        $this->assertSame('collected', $result['outcome']);
        $this->assertSame('internal', $result['normalized']['classification']);
    }

    public function testSignalTypeCheckIsSkippedWithoutGovernanceConfigured(): void
    {
        $collector = new TelemetryCollector();

        $result = $collector->receive($this->validEvent(['signal_type' => 'anything']));

        $this->assertSame('collected', $result['outcome']);
    }

    public function testSensitiveFieldsAreRedactedWhenTheStandardRequiresIt(): void
    {
        $governance = new SqliteObservabilityGovernance($this->tempPath('governance'));
        $governance->defineStandard('log', ['redaction_required' => true]);
        $collector = new TelemetryCollector($governance);

        $result = $collector->receive(
            $this->validEvent(['payload' => ['message' => 'hi', 'email' => 'user@example.com']]),
            ['sensitive_fields' => ['email']]
        );

        $this->assertSame('[REDACTED]', $result['normalized']['payload']['email']);
        $this->assertSame('hi', $result['normalized']['payload']['message']);
    }

    public function testSensitiveFieldsAreNotRedactedWhenTheStandardDoesNotRequireIt(): void
    {
        $governance = new SqliteObservabilityGovernance($this->tempPath('governance'));
        $governance->defineStandard('log', ['redaction_required' => false]);
        $collector = new TelemetryCollector($governance);

        $result = $collector->receive(
            $this->validEvent(['payload' => ['email' => 'user@example.com']]),
            ['sensitive_fields' => ['email']]
        );

        $this->assertSame('user@example.com', $result['normalized']['payload']['email']);
    }

    public function testForwardsNormalizedEventToAllRegisteredConsumers(): void
    {
        $collector = new TelemetryCollector();
        $received = [];
        $collector->registerConsumer('log_manager', function (array $event) use (&$received): void {
            $received[] = $event;
        });

        $result = $collector->receive($this->validEvent());

        $this->assertSame('delivered', $result['consumer_outcomes']['log_manager']);
        $this->assertCount(1, $received);
    }

    public function testAFailingConsumerDoesNotPreventDeliveryToOthers(): void
    {
        $collector = new TelemetryCollector();
        $collector->registerConsumer('broken', function (): never {
            throw new RuntimeException('consumer exploded');
        });
        $delivered = [];
        $collector->registerConsumer('healthy', function (array $event) use (&$delivered): void {
            $delivered[] = $event;
        });

        $result = $collector->receive($this->validEvent());

        $this->assertSame('collected', $result['outcome']);
        $this->assertStringContainsString('consumer exploded', $result['consumer_outcomes']['broken']);
        $this->assertSame('delivered', $result['consumer_outcomes']['healthy']);
    }

    public function testCollectedAndRejectedOutcomesDispatchEvents(): void
    {
        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        $events->listen('telemetry.collected', new CallbackEventListener(
            function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }
        ));
        $events->listen('telemetry.invalid_envelope', new CallbackEventListener(
            function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }
        ));

        $collector = new TelemetryCollector(events: $events);
        $collector->receive($this->validEvent());
        $collector->receive(['event_id' => 'evt_2']);

        $this->assertCount(2, $captured);
    }
}
