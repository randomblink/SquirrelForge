<?php

declare(strict_types=1);

namespace SquirrelForge\Contracts;

interface LoggerInterface
{
    /**
     * Logs a message at the emergency level.
     *
     * @param array<string, mixed> $context
     */
    public function emergency(string $message, array $context = []): void;

    /**
     * Logs a message at the alert level.
     *
     * @param array<string, mixed> $context
     */
    public function alert(string $message, array $context = []): void;

    /**
     * Logs a message at the critical level.
     *
     * @param array<string, mixed> $context
     */
    public function critical(string $message, array $context = []): void;

    /**
     * Logs a message at the error level.
     *
     * @param array<string, mixed> $context
     */
    public function error(string $message, array $context = []): void;

    /**
     * Logs a message at the warning level.
     *
     * @param array<string, mixed> $context
     */
    public function warning(string $message, array $context = []): void;

    /**
     * Logs a message at the notice level.
     *
     * @param array<string, mixed> $context
     */
    public function notice(string $message, array $context = []): void;

    /**
     * Logs a message at the info level.
     *
     * @param array<string, mixed> $context
     */
    public function info(string $message, array $context = []): void;

    /**
     * Logs a message at the debug level.
     *
     * @param array<string, mixed> $context
     */
    public function debug(string $message, array $context = []): void;

    /**
     * Logs a message at an arbitrary level.
     *
     * Supported levels:
     * emergency, alert, critical, error,
     * warning, notice, info, debug
     *
     * @param array<string, mixed> $context
     */
    public function log(
        string $level,
        string $message,
        array $context = []
    ): void;
}
