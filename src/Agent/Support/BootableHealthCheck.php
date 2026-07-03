<?php

declare(strict_types=1);

namespace SquirrelForge\Agent\Support;

use DateTimeImmutable;

/**
 * The `BootableInterface`/`HealthCheckInterface` boilerplate every agent in
 * this directory implemented identically by hand: a `booted` flag, and a
 * `health()` shape of {status, component, timestamp, details}. Extracted
 * here because `AbstractRoleAgent`, `AgentOrchestrator`, and `CallbackAgent`
 * all implement `AgentInterface` directly (they don't share a common base
 * class), so a trait -- not inheritance -- is what lets them share this
 * exact behavior.
 *
 * A using class only needs to provide `healthDetails()`, the per-agent
 * contents of the "details" key.
 */
trait BootableHealthCheck
{
    private bool $booted = false;

    public function boot(): void
    {
        $this->booted = true;
    }

    public function isHealthy(): bool
    {
        return $this->booted;
    }

    public function health(): array
    {
        return [
            'status' => $this->booted ? 'healthy' : 'unhealthy',
            'component' => static::class,
            'timestamp' => (new DateTimeImmutable())->format(DATE_ATOM),
            'details' => $this->healthDetails(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    abstract protected function healthDetails(): array;
}
