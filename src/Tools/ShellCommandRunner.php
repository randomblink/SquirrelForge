<?php

declare(strict_types=1);

namespace SquirrelForge\Tools;

use InvalidArgumentException;
use RuntimeException;
use SquirrelForge\Contracts\CommandRunnerInterface;

/**
 * Executes an allowlisted binary as a real process.
 *
 * Safety design:
 * - The command is always passed to `proc_open()` as an array, never built
 *   into a shell string. PHP executes array-form commands directly (argv),
 *   with no `/bin/sh -c` involved, so nothing in the arguments -- including
 *   anything an LLM generated -- can be interpreted as shell syntax
 *   (no `;`, `&&`, backticks, `$()`, or globbing get a chance to run).
 * - Only a fixed allowlist of binaries can be invoked at all, regardless of
 *   arguments.
 */
final class ShellCommandRunner implements CommandRunnerInterface
{
    private const ALLOWED_BINARIES = ['git', 'composer', 'phpunit'];

    public function __construct(
        private readonly string $defaultWorkingDirectory
    ) {
    }

    public function run(array $command, ?string $workingDirectory = null): array
    {
        if ($command === []) {
            throw new InvalidArgumentException('Command must not be empty.');
        }

        $binary = basename((string) $command[0]);

        if (!in_array($binary, self::ALLOWED_BINARIES, true)) {
            throw new RuntimeException(sprintf(
                'Command "%s" is not allowlisted. Allowed: %s.',
                $binary,
                implode(', ', self::ALLOWED_BINARIES)
            ));
        }

        $cwd = $workingDirectory ?? $this->defaultWorkingDirectory;

        $process = proc_open(
            array_values($command),
            [
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            $cwd
        );

        if (!is_resource($process)) {
            throw new RuntimeException(sprintf('Unable to start process for "%s".', $binary));
        }

        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return [
            'exitCode' => $exitCode,
            'output' => $output !== false ? $output : '',
            'error' => $error !== false ? $error : '',
        ];
    }
}
