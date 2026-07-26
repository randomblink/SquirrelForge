<?php

declare(strict_types=1);

namespace SquirrelForge\Security;

use InvalidArgumentException;

final class AuthenticationThrottlePolicy
{
    public function __construct(
        public readonly int $maximumFailures = 5,
        public readonly int $failureWindowSeconds = 300,
        public readonly int $lockoutSeconds = 900
    ) {
        if ($maximumFailures < 1 || $failureWindowSeconds < 1 || $lockoutSeconds < 1) {
            throw new InvalidArgumentException('Authentication throttle values must be positive.');
        }
    }

    public static function fromEnvironment(): self
    {
        return new self(
            self::positiveEnvironmentInteger('SQUIRRELFORGE_AUTH_MAX_FAILURES', 5),
            self::positiveEnvironmentInteger('SQUIRRELFORGE_AUTH_FAILURE_WINDOW_SECONDS', 300),
            self::positiveEnvironmentInteger('SQUIRRELFORGE_AUTH_LOCKOUT_SECONDS', 900)
        );
    }

    private static function positiveEnvironmentInteger(string $name, int $default): int
    {
        $value = getenv($name);

        if ($value === false || filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < 1) {
            return $default;
        }

        return (int) $value;
    }
}
