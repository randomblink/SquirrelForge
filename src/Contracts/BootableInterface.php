<?php

declare(strict_types=1);

namespace SquirrelForge\Contracts;

interface BootableInterface
{
    public function boot(): void;
}
