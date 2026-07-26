<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ProductionHealthCheckTest extends TestCase
{
    private string $directory;
    private string $receipt;
    private string $expectedImage = 'ghcr.io/example/squirrelforge@sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/squirrelforge-health-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0700, true);
        $this->receipt = $this->directory . '/receipt.json';

        $this->executable('kubectl', <<<'SH'
#!/bin/sh
printf '%s' "$FAKE_ACTIVE_IMAGE"
SH);
        $this->executable('curl', <<<'SH'
#!/bin/sh
for argument in "$@"; do
  case "$argument" in
    */ready) printf '%s' "$FAKE_READINESS"; exit 0 ;;
    */error-rate) printf '%s' "$FAKE_ERROR_RATE"; exit 0 ;;
    */backup-age) printf '%s' "$FAKE_BACKUP_AGE"; exit 0 ;;
  esac
done
exit 22
SH);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $path) {
            @unlink($path);
        }
        @rmdir($this->directory);
    }

    public function testHealthySignalsProduceBoundedReceipt(): void
    {
        $result = $this->runCheck([
            'FAKE_ACTIVE_IMAGE' => $this->expectedImage,
            'FAKE_READINESS' => '{"ready":true,"providers":{"credential_provider":"ready"}}',
            'FAKE_ERROR_RATE' => '0.25',
            'FAKE_BACKUP_AGE' => '300',
        ]);

        self::assertSame(0, $result['exit']);
        self::assertSame('HEALTHY', trim($result['output']));
        $receipt = $this->receipt();
        self::assertSame('HEALTHY', $receipt['status']);
        self::assertSame([], $receipt['failure_codes']);
        self::assertSame(0.25, $receipt['error_rate_percent']);
        self::assertSame(300, $receipt['backup_age_seconds']);
        self::assertArrayNotHasKey('providers', $receipt);
    }

    public function testDriftDegradedReadinessHighErrorsAndStaleBackupFail(): void
    {
        $result = $this->runCheck([
            'FAKE_ACTIVE_IMAGE' => 'ghcr.io/example/squirrelforge@sha256:bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
            'FAKE_READINESS' => '{"ready":false,"reason":"sensitive provider response"}',
            'FAKE_ERROR_RATE' => '4.5',
            'FAKE_BACKUP_AGE' => '90000',
        ]);

        self::assertSame(1, $result['exit']);
        self::assertSame('UNHEALTHY', trim($result['output']));
        $receipt = $this->receipt();
        self::assertSame([
            'IMAGE_DRIFT',
            'READINESS_FAILED',
            'ERROR_RATE_EXCEEDED',
            'BACKUP_STALE',
        ], $receipt['failure_codes']);
        $receiptContents = file_get_contents($this->receipt);
        self::assertIsString($receiptContents);
        self::assertStringNotContainsString('sensitive provider response', $receiptContents);
    }

    public function testUnavailableNumericSignalsFailClosed(): void
    {
        $result = $this->runCheck([
            'FAKE_ACTIVE_IMAGE' => $this->expectedImage,
            'FAKE_READINESS' => '{"ready":true}',
            'FAKE_ERROR_RATE' => 'not-a-number',
            'FAKE_BACKUP_AGE' => '-1',
        ]);

        self::assertSame(1, $result['exit']);
        self::assertSame([
            'ERROR_RATE_UNAVAILABLE',
            'BACKUP_AGE_UNAVAILABLE',
        ], $this->receipt()['failure_codes']);
    }

    /**
     * @param array<string, string> $environment
     * @return array{exit: int, output: string}
     */
    private function runCheck(array $environment): array
    {
        $root = dirname(__DIR__, 2);
        $command = escapeshellarg('/bin/sh') . ' ' . escapeshellarg($root . '/bin/check-production-health.sh');
        $pipes = [];
        $process = proc_open(
            $command,
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $root,
            array_merge($_ENV, $environment, [
                'PATH' => $this->directory . ':' . getenv('PATH'),
                'SQUIRRELFORGE_MONITOR_EXPECTED_IMAGE' => $this->expectedImage,
                'SQUIRRELFORGE_MONITOR_READINESS_URL' => 'https://monitor.test/ready',
                'SQUIRRELFORGE_MONITOR_ERROR_RATE_URL' => 'https://monitor.test/error-rate',
                'SQUIRRELFORGE_MONITOR_BACKUP_AGE_URL' => 'https://monitor.test/backup-age',
                'SQUIRRELFORGE_MONITOR_MAXIMUM_ERROR_RATE_PERCENT' => '1',
                'SQUIRRELFORGE_MONITOR_MAXIMUM_BACKUP_AGE_SECONDS' => '86400',
                'SQUIRRELFORGE_MONITOR_RECEIPT_PATH' => $this->receipt,
            ])
        );
        self::assertIsResource($process);
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return ['exit' => proc_close($process), 'output' => $output];
    }

    /** @return array<string, mixed> */
    private function receipt(): array
    {
        $contents = file_get_contents($this->receipt);
        self::assertIsString($contents);

        return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    }

    private function executable(string $name, string $contents): void
    {
        $path = $this->directory . '/' . $name;
        file_put_contents($path, $contents);
        chmod($path, 0700);
    }
}
