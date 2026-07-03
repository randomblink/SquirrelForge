<?php

declare(strict_types=1);

namespace SquirrelForge\Contracts;

interface EventBusInterface
{
    /**
     * Register an event listener.
     */
    public function listen(string $eventName, EventListenerInterface $listener): void;

    /**
     * Dispatch an event to registered listeners.
     */
    public function dispatch(EventInterface $event): void;
}
