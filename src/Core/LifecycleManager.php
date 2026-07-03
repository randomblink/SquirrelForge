<?php

declare(strict_types=1);

namespace SquirrelForge\Core;

final class LifecycleManager
{
    private bool $running = false;

    public function start(): void
    {
        $this->running = true;
    }

    public function stop(): void
    {
        $this->running = false;
    }

    public function restart(): void
    {
        $this->stop();
        $this->start();
    }

    public function isRunning(): bool
    {
        return $this->running;
    }
}