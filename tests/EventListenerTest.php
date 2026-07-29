<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SquirrelForge\Automation\EventListener;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;

final class EventListenerTest extends TestCase
{
    private function validEvent(array $overrides = []): array
    {
        return array_merge([
            'event_id' => 'evt_1',
            'source' => 'workflow-engine',
            'name' => 'workflow.completed',
            'payload' => ['workflow_id' => 'wf_1'],
        ], $overrides);
    }

    public function testWellFormedEventIsProcessedAndClassified(): void
    {
        $listener = new EventListener();

        $result = $listener->receive($this->validEvent());

        $this->assertSame('processed', $result['outcome']);
        $this->assertSame('workflow', $result['category']);
        $this->assertSame(['workflow_id' => 'wf_1'], $result['normalized']['payload']);
    }

    public function testEventNameWithNoDotIsUncategorized(): void
    {
        $listener = new EventListener();

        $result = $listener->receive($this->validEvent(['name' => 'boot']));

        $this->assertSame('uncategorized', $result['category']);
    }

    public function testMissingRequiredFieldIsRejectedAsInvalidEnvelope(): void
    {
        $listener = new EventListener();
        $event = $this->validEvent();
        unset($event['source']);

        $result = $listener->receive($event);

        $this->assertSame('invalid_envelope', $result['outcome']);
        $this->assertStringContainsString('source', (string) $result['error']);
    }

    public function testUnparseableOccurredAtIsRejectedAsInvalidEnvelope(): void
    {
        $listener = new EventListener();

        $result = $listener->receive($this->validEvent(['occurred_at' => 'not-a-timestamp']));

        $this->assertSame('invalid_envelope', $result['outcome']);
    }

    public function testValidOccurredAtIsNormalizedWithoutChangingItsMeaning(): void
    {
        $listener = new EventListener();

        $result = $listener->receive($this->validEvent(['occurred_at' => '2026-07-28T12:00:00Z']));

        $this->assertStringStartsWith('2026-07-28T12:00:00', $result['normalized']['occurred_at']);
    }

    public function testUnapprovedSourceIsRejectedWhenAnAllowlistIsConfigured(): void
    {
        $listener = new EventListener(approvedSources: ['workflow-engine']);

        $approved = $listener->receive($this->validEvent(['event_id' => 'evt_a', 'source' => 'workflow-engine']));
        $unapproved = $listener->receive($this->validEvent(['event_id' => 'evt_b', 'source' => 'unknown-source']));

        $this->assertSame('processed', $approved['outcome']);
        $this->assertSame('unapproved_source', $unapproved['outcome']);
    }

    public function testAnySourceIsAllowedWhenNoAllowlistIsConfigured(): void
    {
        $listener = new EventListener();

        $result = $listener->receive($this->validEvent(['source' => 'anything']));

        $this->assertSame('processed', $result['outcome']);
    }

    public function testDuplicateEventIdIsRejected(): void
    {
        $listener = new EventListener();
        $listener->receive($this->validEvent());

        $result = $listener->receive($this->validEvent());

        $this->assertSame('duplicate', $result['outcome']);
    }

    public function testUnsupportedCategoryIsRejectedWhenAnAllowlistIsConfigured(): void
    {
        $listener = new EventListener(supportedCategories: ['workflow']);

        $supported = $listener->receive($this->validEvent(['event_id' => 'evt_a', 'name' => 'workflow.completed']));
        $unsupported = $listener->receive($this->validEvent(['event_id' => 'evt_b', 'name' => 'health.degraded']));

        $this->assertSame('processed', $supported['outcome']);
        $this->assertSame('unsupported_category', $unsupported['outcome']);
    }

    public function testForwardsNormalizedEventToAllRegisteredConsumers(): void
    {
        $listener = new EventListener();
        $receivedByA = [];
        $receivedByB = [];
        $listener->registerConsumer('a', function (array $event) use (&$receivedByA): void {
            $receivedByA[] = $event;
        });
        $listener->registerConsumer('b', function (array $event) use (&$receivedByB): void {
            $receivedByB[] = $event;
        });

        $result = $listener->receive($this->validEvent());

        $this->assertSame('delivered', $result['consumer_outcomes']['a']);
        $this->assertSame('delivered', $result['consumer_outcomes']['b']);
        $this->assertCount(1, $receivedByA);
        $this->assertCount(1, $receivedByB);
    }

    public function testAFailingConsumerDoesNotPreventDeliveryToOthersOrRetryAutomatically(): void
    {
        $listener = new EventListener();
        $attempts = 0;
        $listener->registerConsumer('broken', function () use (&$attempts): never {
            $attempts++;

            throw new RuntimeException('consumer exploded');
        });
        $delivered = [];
        $listener->registerConsumer('healthy', function (array $event) use (&$delivered): void {
            $delivered[] = $event;
        });

        $result = $listener->receive($this->validEvent());

        $this->assertSame('processed', $result['outcome']);
        $this->assertStringContainsString('consumer exploded', $result['consumer_outcomes']['broken']);
        $this->assertSame('delivered', $result['consumer_outcomes']['healthy']);
        $this->assertSame(1, $attempts);
    }

    public function testSeenEventIdTrackingIsBoundedAndEvictsTheOldestEntry(): void
    {
        $listener = new EventListener();

        for ($i = 0; $i < 1001; $i++) {
            $listener->receive($this->validEvent(['event_id' => "evt_bulk_{$i}"]));
        }

        $result = $listener->receive($this->validEvent(['event_id' => 'evt_bulk_0']));

        $this->assertSame('processed', $result['outcome']);
    }

    public function testProcessedAndRejectedOutcomesDispatchEvents(): void
    {
        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        $events->listen('automation_event.processed', new CallbackEventListener(
            function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }
        ));
        $events->listen('automation_event.duplicate', new CallbackEventListener(
            function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }
        ));

        $listener = new EventListener(events: $events);
        $listener->receive($this->validEvent());
        $listener->receive($this->validEvent());

        $this->assertCount(2, $captured);
    }
}
