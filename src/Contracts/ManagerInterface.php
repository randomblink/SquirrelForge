<?php

declare(strict_types=1);

namespace SquirrelForge\Contracts;

interface ManagerInterface
{
    public function boot(): void;

    public function isReady(): bool;

    public function health(): array;
}
