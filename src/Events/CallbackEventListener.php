<?php

declare(strict_types=1);

namespace SquirrelForge\Events;

use Closure;
use InvalidArgumentException;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Contracts\EventListenerInterface;

final class CallbackEventListener implements EventListenerInterface
{
    /**
     * @var Closure(EventInterface):void
     */
    private Closure $callback;

    /**
     * @param Closure(EventInterface):void $callback
     */
    public function __construct(Closure $callback)
    {
        $this->callback = $callback;
    }

    public function handle(EventInterface $event): void
    {
        ($this->callback)($event);
    }
}
