<?php

declare(strict_types=1);

namespace SquirrelForge\Contracts;

interface HealthCheckInterface
{
    /**
     * Determine whether the component is healthy.
     */
    public function isHealthy(): bool;

    /**
     * Return detailed health information.
     *
     * Expected structure:
     * [
     *     'status' => 'healthy'|'degraded'|'unhealthy',
     *     'component' => 'ComponentName',
     *     'timestamp' => 'ISO-8601',
     *     'details' => [],
     * ]
     */
    public function health(): array;
}
