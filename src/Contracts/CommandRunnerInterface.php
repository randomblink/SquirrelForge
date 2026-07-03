<?php

declare(strict_types=1);

namespace SquirrelForge\Contracts;

/**
 * Runs an external command and reports its result. Implementations are
 * expected to execute the command as an argv array (never by interpolating
 * it into a shell string), so nothing in the command's arguments can be
 * interpreted as shell syntax.
 */
interface CommandRunnerInterface
{
    /**
     * @param array<int, string> $command Argv-style command, e.g. ['git', 'commit', '-m', 'message'].
     * @return array{exitCode: int, output: string, error: string}
     *
     * @throws \InvalidArgumentException if the command is empty.
     * @throws \RuntimeException if the command's binary isn't allowed, or the process can't be started.
     */
    public function run(array $command, ?string $workingDirectory = null): array;
}
