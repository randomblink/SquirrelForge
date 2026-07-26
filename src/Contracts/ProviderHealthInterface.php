<?php

declare(strict_types=1);

namespace SquirrelForge\Contracts;

interface ProviderHealthInterface
{
    /**
     * @return array{healthy: bool, provider_ref: string, rationale: string}
     */
    public function health(): array;
}
