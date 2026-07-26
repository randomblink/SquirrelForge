<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ReleaseImageAdmissionTest extends TestCase
{
    private string $temporaryDirectory;
    private string $commandLog;
    private string $script;

    protected function setUp(): void
    {
        $this->temporaryDirectory = sys_get_temp_dir() . '/squirrelforge-admission-'
            . bin2hex(random_bytes(8));
        mkdir($this->temporaryDirectory, 0700, true);
        $this->commandLog = $this->temporaryDirectory . '/commands.log';
        $this->script = dirname(__DIR__, 2) . '/bin/verify-release-image.sh';

        foreach (['cosign', 'gh', 'trivy'] as $command) {
            $path = $this->temporaryDirectory . '/' . $command;
            file_put_contents($path, <<<'SH'
#!/bin/sh
printf '%s %s\n' "$(basename "$0")" "$*" >> "$ADMISSION_COMMAND_LOG"
test "${ADMISSION_FAIL_COMMAND:-}" != "$(basename "$0")"
SH);
            chmod($path, 0700);
        }
    }

    protected function tearDown(): void
    {
        foreach (glob($this->temporaryDirectory . '/*') ?: [] as $path) {
            unlink($path);
        }

        rmdir($this->temporaryDirectory);
    }

    public function testImmutableVerifiedImagePassesEveryAdmissionControl(): void
    {
        $digest = 'sha256:' . str_repeat('a', 64);
        $receiptPath = $this->temporaryDirectory . '/admission-receipt.json';
        $result = $this->executeAdmission([
            'SQUIRRELFORGE_ADMISSION_IMAGE' => 'ghcr.io/example/squirrelforge@' . $digest,
            'SQUIRRELFORGE_ADMISSION_RECEIPT_PATH' => $receiptPath,
        ]);
        $commands = file_get_contents($this->commandLog);
        $receipt = json_decode(
            file_get_contents($receiptPath),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->assertSame(0, $result['exit_code'], $result['stderr']);
        $this->assertStringContainsString('cosign verify', $commands);
        $this->assertSame(2, substr_count($commands, 'gh attestation verify'));
        $this->assertStringContainsString(
            '--predicate-type https://cyclonedx.org/bom',
            $commands
        );
        $this->assertStringContainsString('trivy image', $commands);
        $this->assertStringContainsString('digest=' . $digest, $result['stdout']);
        $this->assertSame('PASSED', $receipt['status']);
        $this->assertSame('ghcr.io/example/squirrelforge@' . $digest, $receipt['image']);
        $this->assertSame('VERIFIED', $receipt['signature']);
    }

    public function testMutableTagIsRejectedBeforeVerificationCommandsRun(): void
    {
        $result = $this->executeAdmission([
            'SQUIRRELFORGE_ADMISSION_IMAGE' => 'ghcr.io/example/squirrelforge:v1.2.3',
        ]);

        $this->assertSame(1, $result['exit_code']);
        $this->assertStringContainsString('immutable SHA-256 digest', $result['stderr']);
        $this->assertFileDoesNotExist($this->commandLog);
    }

    public function testAnyFailedVerifierRejectsAdmission(): void
    {
        $result = $this->executeAdmission([
            'SQUIRRELFORGE_ADMISSION_IMAGE' =>
                'ghcr.io/example/squirrelforge@sha256:' . str_repeat('b', 64),
            'ADMISSION_FAIL_COMMAND' => 'gh',
        ]);

        $this->assertSame(1, $result['exit_code']);
        $this->assertStringNotContainsString('admission PASSED', $result['stdout']);
        $this->assertStringNotContainsString('trivy image', (string) @file_get_contents($this->commandLog));
    }

    private function executeAdmission(array $overrides): array
    {
        $environment = array_merge($_ENV, [
            'PATH' => $this->temporaryDirectory . ':' . (getenv('PATH') ?: '/usr/bin:/bin'),
            'ADMISSION_COMMAND_LOG' => $this->commandLog,
            'SQUIRRELFORGE_ADMISSION_REPOSITORY' => 'ghcr.io/example/squirrelforge',
            'SQUIRRELFORGE_ADMISSION_SOURCE_REPOSITORY' => 'example/squirrelforge',
            'SQUIRRELFORGE_ADMISSION_CERTIFICATE_IDENTITY_REGEXP' =>
                '^https://github.com/example/squirrelforge/.github/workflows/',
        ], $overrides);
        $process = proc_open(
            [$this->script],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            dirname(__DIR__, 2),
            $environment
        );
        self::assertIsResource($process);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [
            'exit_code' => proc_close($process),
            'stdout' => $stdout,
            'stderr' => $stderr,
        ];
    }
}
