<?php

declare(strict_types=1);

namespace SquirrelForge\Core;

use DateTimeImmutable;
use SquirrelForge\Contracts\HealthCheckInterface;

final class HealthManager
{
    /**
     * @var array<string, HealthCheckInterface>
     */
    private array $checks = [];

    public function register(string $name, HealthCheckInterface $check): void
    {
        $this->checks[$name] = $check;
    }

    public function check(string $name): ?array
    {
        return isset($this->checks[$name])
            ? $this->checks[$name]->health()
            : null;
    }

    public function all(): array
    {
        return $this->checks;
    }

    public function report(): array
    {
        $results = [];

        foreach ($this->checks as $name => $check) {
            $results[$name] = $check->health();
        }

        return [
            'status' => $this->overallStatus($results),
            'timestamp' => (new DateTimeImmutable())->format(DATE_ATOM),
            'checks' => $results,
        ];
    }

    private function overallStatus(array $results): string
    {
        foreach ($results as $result) {
            if (($result['status'] ?? null) === 'unhealthy') {
                return 'unhealthy';
            }
        }

        foreach ($results as $result) {
            if (($result['status'] ?? null) === 'degraded') {
                return 'degraded';
            }
        }

        return 'healthy';
    }
}
