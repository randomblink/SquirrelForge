<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\Event;
use SquirrelForge\Events\EventBus;

final class EventBusTest extends TestCase
{
    private EventBus $bus;

    protected function setUp(): void
    {
        $this->bus = new EventBus();
    }

    private function makeEvent(string $name, array $payload = []): Event
    {
        return new Event(
            id: uniqid('evt-', true),
            name: $name,
            occurredAt: new DateTimeImmutable(),
            source: 'test',
            payload: $payload
        );
    }

    public function testListenerIsCalledOnMatchingDispatch(): void
    {
        $received = null;

        $this->bus->listen('user.created', new CallbackEventListener(
            function ($event) use (&$received): void {
                $received = $event;
            }
        ));

        $event = $this->makeEvent('user.created', ['id' => 42]);
        $this->bus->dispatch($event);

        $this->assertSame($event, $received);
        $this->assertSame(42, $received->getPayload()['id']);
    }

    public function testMultipleListenersAreAllCalledInRegistrationOrder(): void
    {
        $calls = [];

        $this->bus->listen('order.paid', new CallbackEventListener(
            function () use (&$calls): void {
                $calls[] = 'first';
            }
        ));
        $this->bus->listen('order.paid', new CallbackEventListener(
            function () use (&$calls): void {
                $calls[] = 'second';
            }
        ));

        $this->bus->dispatch($this->makeEvent('order.paid'));

        $this->assertSame(['first', 'second'], $calls);
    }

    public function testListenersForOtherEventNamesAreNotCalled(): void
    {
        $called = false;

        $this->bus->listen('a', new CallbackEventListener(
            function () use (&$called): void {
                $called = true;
            }
        ));

        $this->bus->dispatch($this->makeEvent('b'));

        $this->assertFalse($called);
    }

    public function testDispatchingAnEventWithNoListenersDoesNotThrow(): void
    {
        $this->bus->dispatch($this->makeEvent('nobody.listens.' . uniqid()));

        $this->addToAssertionCount(1);
    }

    public function testEventCarriesItsMetadata(): void
    {
        $event = new Event(
            id: 'evt-1',
            name: 'test.event',
            occurredAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
            source: 'unit-test',
            payload: ['key' => 'value'],
            metadata: ['trace' => 'abc']
        );

        $this->assertSame('evt-1', $event->getId());
        $this->assertSame('test.event', $event->getName());
        $this->assertSame('unit-test', $event->getSource());
        $this->assertSame(['key' => 'value'], $event->getPayload());
        $this->assertSame(['trace' => 'abc'], $event->getMetadata());
        $this->assertSame('2026-01-01T00:00:00+00:00', $event->getOccurredAt()->format(DATE_ATOM));
    }
}
