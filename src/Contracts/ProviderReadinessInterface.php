<?php

declare(strict_types=1);

namespace SquirrelForge\Contracts;

interface ProviderReadinessInterface
{
    public function providerRef(): string;

    public function isProductionReady(): bool;
}
