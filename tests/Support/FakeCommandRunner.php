<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Support;

use SquirrelForge\Contracts\CommandRunnerInterface;

/**
 * A CommandRunnerInterface test double. Never runs a real process; records
 * every command it was asked to run and returns a canned response
 * (successful by default) for it.
 */
final class FakeCommandRunner implements CommandRunnerInterface
{
    /**
     * @var array<int, array<int, string>>
     */
    public array $calls = [];

    /**
     * @var array<string, array{exitCode: int, output: string, error: string}>
     */
    private array $responses = [];

    /**
     * Configure the response for a specific command. Matched by joining
     * the command's argv with spaces -- fine for test fixtures where the
     * exact command is known ahead of time.
     *
     * @param array<int, string> $command
     * @param array{exitCode: int, output: string, error: string} $response
     */
    public function respondTo(array $command, array $response): void
    {
        $this->responses[implode(' ', $command)] = $response;
    }

    public function run(array $command, ?string $workingDirectory = null): array
    {
        $this->calls[] = $command;

        return $this->responses[implode(' ', $command)] ?? [
            'exitCode' => 0,
            'output' => '',
            'error' => '',
        ];
    }
}
