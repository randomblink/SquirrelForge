<?php

declare(strict_types=1);

namespace SquirrelForge\Agent\Roles\Support;

use InvalidArgumentException;
use RuntimeException;

/**
 * The context-reading guards every role agent relies on so it never
 * silently fabricates data it was not given: a required field must be
 * present, and a required prior stage must have already handed off.
 */
trait ContextGuards
{
    /**
     * Fetch a required field from the context, throwing when it is missing so
     * that agents never silently fabricate data they were not given.
     *
     * @param array<string, mixed> $context
     */
    protected function requireField(array $context, string $key): mixed
    {
        if (!array_key_exists($key, $context) || $context[$key] === null || $context[$key] === '') {
            throw new InvalidArgumentException(
                sprintf('%s requires context field "%s".', static::class, $key)
            );
        }

        return $context[$key];
    }

    /**
     * Fetch the result recorded for a prior stage, throwing if that stage has
     * not run yet (its handoff has not happened).
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    protected function requireHistory(array $context, string $stage): array
    {
        $history = $context['history'] ?? [];

        if (!isset($history[$stage]) || !is_array($history[$stage])) {
            throw new RuntimeException(
                sprintf('%s requires a completed handoff from stage "%s".', static::class, $stage)
            );
        }

        return $history[$stage];
    }
}
