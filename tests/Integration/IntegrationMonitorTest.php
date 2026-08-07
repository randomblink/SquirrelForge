<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Integration;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;
use SquirrelForge\Integration\IntegrationMonitor;

final class IntegrationMonitorTest extends TestCase
{
    public function testNoSignalsAtAllIsUnknown(): void
    {
        $monitor = new IntegrationMonitor();

        $result = $monitor->evaluate('connector_1', []);

        $this->assertSame('Unknown', $result['finding']);
        $this->assertNull($result['connector_status_reference']);
        $this->assertNull($result['service_discovery_availability_reference']);
    }

    public function testStaleObservedAtIsUnknown(): void
    {
        $monitor = new IntegrationMonitor();

        $result = $monitor->evaluate('connector_1', [
            'availability' => 'available',
            'observed_at' => gmdate(DATE_ATOM, time() - 10000),
            'max_staleness_seconds' => 300,
        ]);

        $this->assertSame('Unknown', $result['finding']);
        $this->assertStringContainsString('stale', $result['reasons'][0]);
    }

    public function testUnparseableObservedAtIsUnknown(): void
    {
        $monitor = new IntegrationMonitor();

        $result = $monitor->evaluate('connector_1', ['availability' => 'available', 'observed_at' => 'not-a-date']);

        $this->assertSame('Unknown', $result['finding']);
    }

    public function testFreshObservedAtWithinToleranceIsNotStale(): void
    {
        $monitor = new IntegrationMonitor();

        $result = $monitor->evaluate('connector_1', [
            'availability' => 'available',
            'observed_at' => gmdate(DATE_ATOM, time() - 5),
            'max_staleness_seconds' => 300,
        ]);

        $this->assertSame('Healthy', $result['finding']);
    }

    public function testAuthenticationFailureIsAuthenticationFailing(): void
    {
        $monitor = new IntegrationMonitor();

        $result = $monitor->evaluate('connector_1', ['authentication_failure' => true]);

        $this->assertSame('Authentication Failing', $result['finding']);
        $this->assertSame('degraded', $result['connector_status_reference']);
        $this->assertSame('Degraded', $result['service_discovery_availability_reference']);
    }

    public function testUnavailableAvailabilityIsUnavailable(): void
    {
        $monitor = new IntegrationMonitor();

        $result = $monitor->evaluate('connector_1', ['availability' => 'unavailable']);

        $this->assertSame('Unavailable', $result['finding']);
        $this->assertNull($result['connector_status_reference']);
        $this->assertSame('Unavailable', $result['service_discovery_availability_reference']);
    }

    public function testUnavailableOutranksAuthenticationFailure(): void
    {
        $monitor = new IntegrationMonitor();

        $result = $monitor->evaluate('connector_1', ['availability' => 'unavailable', 'authentication_failure' => true]);

        $this->assertSame('Unavailable', $result['finding']);
    }

    public function testAuthenticationFailureOutranksRateLimited(): void
    {
        $monitor = new IntegrationMonitor();

        $result = $monitor->evaluate('connector_1', ['authentication_failure' => true, 'rate_limited' => true]);

        $this->assertSame('Authentication Failing', $result['finding']);
    }

    public function testRateLimitedSignal(): void
    {
        $monitor = new IntegrationMonitor();

        $result = $monitor->evaluate('connector_1', ['rate_limited' => true]);

        $this->assertSame('Rate Limited', $result['finding']);
    }

    public function testRateLimitedOutranksDegraded(): void
    {
        $monitor = new IntegrationMonitor();

        $result = $monitor->evaluate('connector_1', ['rate_limited' => true, 'availability' => 'degraded']);

        $this->assertSame('Rate Limited', $result['finding']);
    }

    public function testDegradedAvailabilitySignal(): void
    {
        $monitor = new IntegrationMonitor();

        $result = $monitor->evaluate('connector_1', ['availability' => 'degraded']);

        $this->assertSame('Degraded', $result['finding']);
        $this->assertSame('degraded', $result['connector_status_reference']);
    }

    public function testExplicitDegradationFlag(): void
    {
        $monitor = new IntegrationMonitor();

        $result = $monitor->evaluate('connector_1', ['degradation' => true]);

        $this->assertSame('Degraded', $result['finding']);
    }

    public function testTimeoutSignalIsDegraded(): void
    {
        $monitor = new IntegrationMonitor();

        $result = $monitor->evaluate('connector_1', ['timeout' => true]);

        $this->assertSame('Degraded', $result['finding']);
    }

    public function testLatencyOverDefaultThresholdIsDegraded(): void
    {
        $monitor = new IntegrationMonitor();

        $result = $monitor->evaluate('connector_1', ['latency_ms' => 5000]);

        $this->assertSame('Degraded', $result['finding']);
        $this->assertStringContainsString('latency_ms', $result['reasons'][0]);
    }

    public function testLatencyUnderCustomThresholdIsHealthy(): void
    {
        $monitor = new IntegrationMonitor();

        $result = $monitor->evaluate('connector_1', ['latency_ms' => 5000, 'latency_threshold_ms' => 10000]);

        $this->assertSame('Healthy', $result['finding']);
    }

    public function testErrorRateOverDefaultThresholdIsDegraded(): void
    {
        $monitor = new IntegrationMonitor();

        $result = $monitor->evaluate('connector_1', ['error_rate' => 0.5]);

        $this->assertSame('Degraded', $result['finding']);
        $this->assertStringContainsString('error_rate', $result['reasons'][0]);
    }

    public function testDegradedCollectsAllContributingReasons(): void
    {
        $monitor = new IntegrationMonitor();

        $result = $monitor->evaluate('connector_1', ['timeout' => true, 'latency_ms' => 5000, 'error_rate' => 0.9]);

        $this->assertSame('Degraded', $result['finding']);
        $this->assertCount(3, $result['reasons']);
    }

    public function testAllSignalsWithinThresholdsIsHealthy(): void
    {
        $monitor = new IntegrationMonitor();

        $result = $monitor->evaluate('connector_1', ['availability' => 'available', 'latency_ms' => 50, 'error_rate' => 0.01]);

        $this->assertSame('Healthy', $result['finding']);
        $this->assertSame('active', $result['connector_status_reference']);
        $this->assertSame('Available', $result['service_discovery_availability_reference']);
    }

    public function testEvaluateManyReturnsAResultPerSubject(): void
    {
        $monitor = new IntegrationMonitor();

        $results = $monitor->evaluateMany([
            'connector_1' => ['availability' => 'available'],
            'connector_2' => ['rate_limited' => true],
        ]);

        $this->assertSame('Healthy', $results['connector_1']['finding']);
        $this->assertSame('Rate Limited', $results['connector_2']['finding']);
    }

    public function testEventIsEmittedPerEvaluation(): void
    {
        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        $events->listen('integration_monitor.degraded', new CallbackEventListener(
            function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }
        ));

        $monitor = new IntegrationMonitor($events);
        $monitor->evaluate('connector_1', ['availability' => 'degraded']);

        $this->assertCount(1, $captured);
        $this->assertSame('connector_1', $captured[0]->getPayload()['subject_ref']);
    }

    public function testWorksWithoutAnEventBus(): void
    {
        $monitor = new IntegrationMonitor(null);

        $result = $monitor->evaluate('connector_1', ['availability' => 'available']);

        $this->assertSame('Healthy', $result['finding']);
    }
}
