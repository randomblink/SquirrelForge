<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class LaunchReadinessEvaluatorTest extends TestCase
{
    private string $directory;
    private string $manifestPath;
    private string $receiptPath;
    private string $candidate;
    private string $rollback;
    /** @var array<string, array<string, mixed>> */
    private array $evidence;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/squirrelforge-launch-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0700, true);
        $this->manifestPath = $this->directory . '/launch-evidence.json';
        $this->receiptPath = $this->directory . '/launch-receipt.json';
        $this->candidate = 'ghcr.io/example/squirrelforge@sha256:' . str_repeat('a', 64);
        $this->rollback = 'ghcr.io/example/squirrelforge@sha256:' . str_repeat('b', 64);
        $now = gmdate('Y-m-d\TH:i:s\Z');

        $backup = 'verified SQLite backup';
        file_put_contents($this->directory . '/engine.backup.sqlite', $backup);
        $backupDigest = hash('sha256', $backup);
        $this->evidence = [
            'admission' => [
                'schema_version' => 1,
                'status' => 'PASSED',
                'image' => $this->candidate,
                'verified_at' => $now,
            ],
            'rollout' => [
                'schema_version' => 1,
                'status' => 'PROMOTED',
                'recorded_at' => $now,
                'candidate_image' => $this->candidate,
                'rollback_image' => $this->rollback,
                'active_image' => $this->candidate,
            ],
            'observation' => [
                'schema_version' => 1,
                'status' => 'PASSED',
                'observed_at' => $now,
                'image' => $this->candidate,
            ],
            'health' => [
                'schema_version' => 1,
                'status' => 'HEALTHY',
                'checked_at' => $now,
                'expected_image' => $this->candidate,
                'active_image' => $this->candidate,
            ],
            'backup' => [
                'schema_version' => 1,
                'created_at' => $now,
                'database' => 'engine.backup.sqlite',
                'size_bytes' => strlen($backup),
                'sha256' => $backupDigest,
                'sqlite_user_version' => 0,
            ],
            'restore' => [
                'status' => 'RESTORED',
                'restored_at' => $now,
                'sha256' => $backupDigest,
            ],
            'capacity' => [
                'schema_version' => 1,
                'status' => 'PASSED',
                'evaluated_at' => $now,
                'profile' => 'approved-target',
            ],
        ];
        $this->writeEvidenceAndManifest();
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $path) {
            @unlink($path);
        }
        @rmdir($this->directory);
    }

    public function testConsistentFreshEvidenceProducesReadyDecision(): void
    {
        $result = $this->evaluate();

        self::assertSame(0, $result['exit']);
        self::assertSame('READY', trim($result['output']));
        $receipt = $this->receipt();
        self::assertSame('READY', $receipt['decision']);
        self::assertSame($this->candidate, $receipt['candidate_image']);
        self::assertSame($this->rollback, $receipt['rollback_image']);
        self::assertCount(7, $receipt['evidence_sha256']);
        self::assertSame([], $receipt['failure_codes']);
    }

    public function testTamperedEvidenceProducesNotReadyDecision(): void
    {
        file_put_contents($this->directory . '/capacity.json', PHP_EOL . 'tamper', FILE_APPEND);

        $result = $this->evaluate();

        self::assertSame(1, $result['exit']);
        self::assertContains('CAPACITY_CHECKSUM_MISMATCH', $this->receipt()['failure_codes']);
    }

    public function testStaleHealthAndImageMismatchProduceNotReadyDecision(): void
    {
        $this->evidence['health']['checked_at'] = '2020-01-01T00:00:00Z';
        $this->evidence['rollout']['active_image'] = $this->rollback;
        $this->writeEvidenceAndManifest();

        $result = $this->evaluate();

        self::assertSame(1, $result['exit']);
        $failures = $this->receipt()['failure_codes'];
        self::assertContains('HEALTH_EVIDENCE_STALE', $failures);
        self::assertContains('ROLLOUT_IMAGE_MISMATCH', $failures);
    }

    public function testMissingOwnerIsConfigurationErrorWithoutDecision(): void
    {
        $manifest = json_decode(
            (string) file_get_contents($this->manifestPath),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        unset($manifest['owners']['security']);
        file_put_contents($this->manifestPath, json_encode($manifest, JSON_THROW_ON_ERROR));

        $result = $this->evaluate();

        self::assertSame(2, $result['exit']);
        self::assertStringContainsString('owner security is required', $result['output']);
        self::assertFileDoesNotExist($this->receiptPath);
    }

    private function writeEvidenceAndManifest(): void
    {
        $entries = [];
        foreach ($this->evidence as $name => $receipt) {
            $path = $name . '.json';
            file_put_contents(
                $this->directory . '/' . $path,
                json_encode($receipt, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL
            );
            $entries[$name] = [
                'path' => $path,
                'sha256' => hash_file('sha256', $this->directory . '/' . $path),
            ];
        }

        $manifest = [
            'schema_version' => 1,
            'release_ref' => 'release:v1.0.0',
            'change_ref' => 'change:approved',
            'approval_ref' => 'approval:production',
            'candidate_image' => $this->candidate,
            'rollback_image' => $this->rollback,
            'owners' => [
                'release' => 'team:release',
                'on_call' => 'rotation:squirrelforge',
                'recovery' => 'team:recovery',
                'security' => 'team:security',
            ],
            'evidence' => $entries,
        ];
        file_put_contents(
            $this->manifestPath,
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL
        );
        @unlink($this->receiptPath);
    }

    /** @return array{exit: int, output: string} */
    private function evaluate(): array
    {
        $root = dirname(__DIR__, 2);
        $command = escapeshellarg(PHP_BINARY) . ' '
            . escapeshellarg($root . '/bin/evaluate-launch-readiness.php');
        $pipes = [];
        $process = proc_open(
            $command,
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $root,
            array_merge($_ENV, [
                'SQUIRRELFORGE_LAUNCH_MANIFEST_PATH' => $this->manifestPath,
                'SQUIRRELFORGE_LAUNCH_RECEIPT_PATH' => $this->receiptPath,
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
        $contents = file_get_contents($this->receiptPath);
        self::assertIsString($contents);

        return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    }
}
