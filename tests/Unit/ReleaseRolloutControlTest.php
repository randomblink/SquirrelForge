<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ReleaseRolloutControlTest extends TestCase
{
    private string $temporaryDirectory;
    private string $commandLog;
    private string $script;
    private string $admission;
    private string $candidate;
    private string $previous;

    protected function setUp(): void
    {
        $this->temporaryDirectory = sys_get_temp_dir() . '/squirrelforge-rollout-'
            . bin2hex(random_bytes(8));
        mkdir($this->temporaryDirectory, 0700, true);
        $this->commandLog = $this->temporaryDirectory . '/commands.log';
        $this->script = dirname(__DIR__, 2) . '/bin/rollout-release.sh';
        $this->admission = $this->temporaryDirectory . '/admission';
        $this->candidate = 'ghcr.io/example/squirrelforge@sha256:' . str_repeat('c', 64);
        $this->previous = 'ghcr.io/example/squirrelforge@sha256:' . str_repeat('d', 64);

        $this->writeExecutable('kubectl', <<<'SH'
#!/bin/sh
printf 'kubectl %s\n' "$*" >> "$ROLLOUT_COMMAND_LOG"
case "$*" in
  *"get deployment squirrelforge"*)
    printf '%s' "$ROLLOUT_PREVIOUS_IMAGE"
    ;;
  *"set image deployment/squirrelforge "*"=$ROLLOUT_PREVIOUS_IMAGE"*)
    : > "$ROLLOUT_ROLLBACK_MARKER"
    ;;
  *"rollout status deployment/squirrelforge "*)
    test "${ROLLOUT_FAIL_STABLE:-0}" != "1" || test -f "$ROLLOUT_ROLLBACK_MARKER"
    ;;
esac
SH);
        $this->writeExecutable('curl', <<<'SH'
#!/bin/sh
printf 'curl %s\n' "$*" >> "$ROLLOUT_COMMAND_LOG"
case "$*" in
  *"/error-rate-percent"*)
    printf '%s' "${ROLLOUT_ERROR_RATE:-0}"
    ;;
  *"canary"*"/v1/health/providers"*)
    test "${ROLLOUT_FAIL_CANARY:-0}" != "1"
    ;;
esac
SH);
        file_put_contents($this->admission, <<<'SH'
#!/bin/sh
printf 'admission %s\n' "$SQUIRRELFORGE_ADMISSION_IMAGE" >> "$ROLLOUT_COMMAND_LOG"
SH);
        chmod($this->admission, 0700);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->temporaryDirectory . '/*') ?: [] as $path) {
            unlink($path);
        }

        rmdir($this->temporaryDirectory);
    }

    public function testSuccessfulCanaryPromotesExactCandidateAndRemovesCanary(): void
    {
        $result = $this->executeRollout();
        $commands = file_get_contents($this->commandLog);

        $this->assertSame(0, $result['exit_code'], $result['stderr']);
        $this->assertStringContainsString('admission ' . $this->candidate, $commands);
        $this->assertStringContainsString('admission ' . $this->previous, $commands);
        $this->assertStringContainsString(
            'set image deployment/squirrelforge-canary squirrelforge=' . $this->candidate,
            $commands
        );
        $this->assertStringContainsString(
            'set image deployment/squirrelforge squirrelforge=' . $this->candidate,
            $commands
        );
        $this->assertStringContainsString(
            'scale deployment squirrelforge-canary --replicas=0',
            $commands
        );
        $this->assertStringContainsString('SquirrelForge rollout PASSED', $result['stdout']);
        $receipt = json_decode(
            file_get_contents($this->temporaryDirectory . '/rollout-receipt.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $this->assertSame('PROMOTED', $receipt['status']);
        $this->assertSame($this->candidate, $receipt['active_image']);
    }

    public function testCanaryDegradationHaltsBeforeStablePromotion(): void
    {
        $result = $this->executeRollout(['ROLLOUT_FAIL_CANARY' => '1']);
        $commands = file_get_contents($this->commandLog);

        $this->assertSame(1, $result['exit_code']);
        $this->assertStringNotContainsString(
            'set image deployment/squirrelforge squirrelforge=' . $this->candidate,
            $commands
        );
        $this->assertStringContainsString(
            'scale deployment squirrelforge-canary --replicas=0',
            $commands
        );
    }

    public function testStableDegradationRestoresPreviouslyVerifiedDigest(): void
    {
        $result = $this->executeRollout(['ROLLOUT_FAIL_STABLE' => '1']);
        $commands = file_get_contents($this->commandLog);

        $this->assertSame(1, $result['exit_code']);
        $this->assertStringContainsString(
            'set image deployment/squirrelforge squirrelforge=' . $this->previous,
            $commands
        );
        $this->assertStringContainsString('restoring verified digest', $result['stderr']);
        $receipt = json_decode(
            file_get_contents($this->temporaryDirectory . '/rollout-receipt.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $this->assertSame('ROLLED_BACK', $receipt['status']);
        $this->assertSame($this->previous, $receipt['active_image']);
    }

    public function testExcessiveCanaryErrorRateHaltsPromotion(): void
    {
        $result = $this->executeRollout(['ROLLOUT_ERROR_RATE' => '2.5']);

        $this->assertSame(1, $result['exit_code']);
        $this->assertStringContainsString('error rate exceeded', $result['stderr']);
    }

    private function executeRollout(array $overrides = []): array
    {
        $environment = array_merge($_ENV, [
            'PATH' => $this->temporaryDirectory . ':' . (getenv('PATH') ?: '/usr/bin:/bin'),
            'ROLLOUT_COMMAND_LOG' => $this->commandLog,
            'ROLLOUT_PREVIOUS_IMAGE' => $this->previous,
            'ROLLOUT_ROLLBACK_MARKER' => $this->temporaryDirectory . '/rollback.marker',
            'SQUIRRELFORGE_ROLLOUT_IMAGE' => $this->candidate,
            'SQUIRRELFORGE_ROLLOUT_CANARY_READINESS_URL' =>
                'https://canary.example/v1/health/providers',
            'SQUIRRELFORGE_ROLLOUT_STABLE_READINESS_URL' =>
                'https://stable.example/v1/health/providers',
            'SQUIRRELFORGE_ROLLOUT_CANARY_ERROR_RATE_URL' =>
                'https://canary.example/error-rate-percent',
            'SQUIRRELFORGE_ROLLOUT_STABLE_ERROR_RATE_URL' =>
                'https://stable.example/error-rate-percent',
            'SQUIRRELFORGE_ROLLOUT_PROBE_COUNT' => '1',
            'SQUIRRELFORGE_ROLLOUT_PROBE_INTERVAL_SECONDS' => '0',
            'SQUIRRELFORGE_ROLLOUT_ADMISSION_COMMAND' => $this->admission,
            'SQUIRRELFORGE_ROLLOUT_RECEIPT_PATH' =>
                $this->temporaryDirectory . '/rollout-receipt.json',
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

    private function writeExecutable(string $name, string $contents): void
    {
        $path = $this->temporaryDirectory . '/' . $name;
        file_put_contents($path, $contents);
        chmod($path, 0700);
    }
}
