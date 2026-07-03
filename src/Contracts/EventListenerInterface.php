<?php

declare(strict_types=1);

namespace SquirrelForge\Contracts;

interface EventListenerInterface
{
    public function handle(EventInterface $event): void;
}
