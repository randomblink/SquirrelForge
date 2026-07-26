<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ProtectedReleaseOrchestrationTest extends TestCase
{
    private string $temporaryDirectory;
    private string $commandLog;
    private string $image;

    protected function setUp(): void
    {
        $this->temporaryDirectory = sys_get_temp_dir() . '/squirrelforge-protected-release-'
            . bin2hex(random_bytes(8));
        mkdir($this->temporaryDirectory, 0700, true);
        $this->commandLog = $this->temporaryDirectory . '/commands.log';
        $this->image = 'ghcr.io/example/squirrelforge@sha256:' . str_repeat('e', 64);
        $this->writeExecutable('kubectl', <<<'SH'
#!/bin/sh
printf 'kubectl %s\n' "$*" >> "$ORCHESTRATION_COMMAND_LOG"
case "$*" in
  *"get deployment"*) printf '%s' "$ORCHESTRATION_ACTIVE_IMAGE" ;;
esac
SH);
        $this->writeExecutable('curl', <<<'SH'
#!/bin/sh
case "$*" in
  *"error-rate"*) printf '%s' "${ORCHESTRATION_ERROR_RATE:-0}" ;;
  *) test "${ORCHESTRATION_READY:-1}" = "1" ;;
esac
SH);
        $this->writeExecutable('admission', <<<'SH'
#!/bin/sh
printf 'admission %s\n' "$SQUIRRELFORGE_ADMISSION_IMAGE" >> "$ORCHESTRATION_COMMAND_LOG"
SH);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->temporaryDirectory . '/*') ?: [] as $path) {
            unlink($path);
        }
        rmdir($this->temporaryDirectory);
    }

    public function testPostDeploymentObservationRequiresEverySampleToPass(): void
    {
        $receipt = $this->temporaryDirectory . '/observation.json';
        $result = $this->execute('bin/observe-release.sh', [
            'SQUIRRELFORGE_OBSERVE_RECEIPT_PATH' => $receipt,
        ]);
        $body = json_decode(file_get_contents($receipt), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $result['exit_code'], $result['stderr']);
        $this->assertSame('PASSED', $body['status']);
        $this->assertSame(2, $body['samples']);
    }

    public function testPostDeploymentObservationRejectsDigestDrift(): void
    {
        $result = $this->execute('bin/observe-release.sh', [
            'ORCHESTRATION_ACTIVE_IMAGE' =>
                'ghcr.io/example/squirrelforge@sha256:' . str_repeat('f', 64),
        ]);

        $this->assertSame(1, $result['exit_code']);
        $this->assertStringContainsString('unexpected active digest', $result['stderr']);
    }

    public function testVerifiedRollbackConfirmsRestoredDigestAndWritesReceipt(): void
    {
        $receipt = $this->temporaryDirectory . '/rollback.json';
        $result = $this->execute('bin/rollback-release.sh', [
            'SQUIRRELFORGE_ROLLBACK_IMAGE' => $this->image,
            'SQUIRRELFORGE_ROLLBACK_RECEIPT_PATH' => $receipt,
        ]);
        $commands = file_get_contents($this->commandLog);
        $body = json_decode(file_get_contents($receipt), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $result['exit_code'], $result['stderr']);
        $this->assertStringContainsString('admission ' . $this->image, $commands);
        $this->assertStringContainsString(
            'set image deployment/squirrelforge squirrelforge=' . $this->image,
            $commands
        );
        $this->assertSame('ROLLED_BACK', $body['status']);
        $this->assertSame($this->image, $body['active_image']);
    }

    private function execute(string $script, array $overrides = []): array
    {
        $environment = array_merge($_ENV, [
            'PATH' => $this->temporaryDirectory . ':' . (getenv('PATH') ?: '/usr/bin:/bin'),
            'ORCHESTRATION_COMMAND_LOG' => $this->commandLog,
            'ORCHESTRATION_ACTIVE_IMAGE' => $this->image,
            'SQUIRRELFORGE_OBSERVE_IMAGE' => $this->image,
            'SQUIRRELFORGE_OBSERVE_SAMPLE_COUNT' => '2',
            'SQUIRRELFORGE_OBSERVE_INTERVAL_SECONDS' => '0',
            'SQUIRRELFORGE_ROLLOUT_STABLE_READINESS_URL' => 'https://stable/health',
            'SQUIRRELFORGE_ROLLOUT_STABLE_ERROR_RATE_URL' => 'https://metrics/error-rate',
            'SQUIRRELFORGE_ROLLOUT_ADMISSION_COMMAND' =>
                $this->temporaryDirectory . '/admission',
        ], $overrides);
        $process = proc_open(
            [dirname(__DIR__, 2) . '/' . $script],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
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

        return ['exit_code' => proc_close($process), 'stdout' => $stdout, 'stderr' => $stderr];
    }

    private function writeExecutable(string $name, string $contents): void
    {
        $path = $this->temporaryDirectory . '/' . $name;
        file_put_contents($path, $contents);
        chmod($path, 0700);
    }
}
