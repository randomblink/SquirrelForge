<?php

declare(strict_types=1);

namespace SquirrelForge\Events;

use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Contracts\EventListenerInterface;

final class EventBus implements EventBusInterface
{
    /**
     * @var array<string, array<int, EventListenerInterface>>
     */
    private array $listeners = [];

    public function listen(string $eventName, EventListenerInterface $listener): void
    {
        $this->listeners[$eventName][] = $listener;
    }

    public function dispatch(EventInterface $event): void
    {
        foreach ($this->listeners[$event->getName()] ?? [] as $listener) {
            $listener->handle($event);
        }
    }
}
