<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Tools;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Tools\ShellCommandRunner;

final class ShellCommandRunnerTest extends TestCase
{
    public function testRunsAnAllowlistedBinaryAndReturnsItsExitCode(): void
    {
        $runner = new ShellCommandRunner(sys_get_temp_dir());

        // "git --version" is read-only and safe to run anywhere; every
        // environment this test suite runs in already has git installed
        // (this whole project is a git repo).
        $result = $runner->run(['git', '--version']);

        $this->assertSame(0, $result['exitCode']);
        $this->assertStringContainsString('git version', $result['output']);
    }

    public function testRefusesABinaryThatIsNotAllowlisted(): void
    {
        $runner = new ShellCommandRunner(sys_get_temp_dir());

        $this->expectException(\RuntimeException::class);

        $runner->run(['rm', '-rf', '/']);
    }

    public function testRefusesAnAllowlistCheckBypassAttemptViaPath(): void
    {
        $runner = new ShellCommandRunner(sys_get_temp_dir());

        // Even disguised behind a path, only the binary's basename is
        // checked against the allowlist, so this must still be refused.
        $this->expectException(\RuntimeException::class);

        $runner->run(['/bin/rm', '-rf', '/']);
    }

    public function testThrowsOnAnEmptyCommand(): void
    {
        $runner = new ShellCommandRunner(sys_get_temp_dir());

        $this->expectException(\InvalidArgumentException::class);

        $runner->run([]);
    }
}
