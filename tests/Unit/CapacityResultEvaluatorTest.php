<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class CapacityResultEvaluatorTest extends TestCase
{
    private string $directory;
    private string $summary;
    private string $receipt;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/squirrelforge-capacity-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0700, true);
        $this->summary = $this->directory . '/summary.json';
        $this->receipt = $this->directory . '/receipt.json';
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $path) {
            @unlink($path);
        }
        @rmdir($this->directory);
    }

    public function testPassingSummaryProducesChecksumBoundReceipt(): void
    {
        $this->writeSummary(0.005, 450, 0.995, 120, 8);

        $result = $this->evaluate();

        self::assertSame(0, $result['exit']);
        self::assertSame('PASSED', trim($result['output']));
        $receipt = $this->receipt();
        self::assertSame('PASSED', $receipt['status']);
        self::assertSame('approved-target', $receipt['profile']);
        self::assertSame(hash_file('sha256', $this->summary), $receipt['summary_sha256']);
        self::assertSame(8, $receipt['observed']['maximum_virtual_users']);
        self::assertSame([], $receipt['failure_codes']);
    }

    public function testBreachedLimitsProduceStableFailureCodes(): void
    {
        $this->writeSummary(0.02, 1500, 0.90, 4, 20);

        $result = $this->evaluate();

        self::assertSame(1, $result['exit']);
        self::assertSame([
            'REQUEST_FAILURE_RATE_EXCEEDED',
            'P95_LATENCY_EXCEEDED',
            'WORKFLOW_CHECK_RATE_BELOW_MINIMUM',
            'INSUFFICIENT_ITERATIONS',
        ], $this->receipt()['failure_codes']);
    }

    public function testMissingMetricIsConfigurationFailureWithoutReceipt(): void
    {
        file_put_contents($this->summary, '{"metrics":{}}');

        $result = $this->evaluate();

        self::assertSame(2, $result['exit']);
        self::assertStringContainsString('is missing', $result['output']);
        self::assertFileDoesNotExist($this->receipt);
    }

    private function writeSummary(
        float $failedRate,
        float $p95Ms,
        float $checkRate,
        int $iterations,
        int $maximumVirtualUsers
    ): void {
        $summary = [
            'metrics' => [
                'http_req_failed' => ['values' => ['rate' => $failedRate]],
                'http_req_duration' => ['values' => ['p(95)' => $p95Ms]],
                'checks' => ['values' => ['rate' => $checkRate]],
                'iterations' => ['values' => ['count' => $iterations]],
                'vus_max' => ['values' => ['max' => $maximumVirtualUsers]],
            ],
        ];
        file_put_contents($this->summary, json_encode($summary, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{exit: int, output: string}
     */
    private function evaluate(): array
    {
        $root = dirname(__DIR__, 2);
        $command = escapeshellarg(PHP_BINARY) . ' '
            . escapeshellarg($root . '/bin/evaluate-capacity-result.php');
        $pipes = [];
        $process = proc_open(
            $command,
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $root,
            array_merge($_ENV, [
                'SQUIRRELFORGE_CAPACITY_SUMMARY_PATH' => $this->summary,
                'SQUIRRELFORGE_CAPACITY_RECEIPT_PATH' => $this->receipt,
                'SQUIRRELFORGE_CAPACITY_PROFILE' => 'approved-target',
                'SQUIRRELFORGE_CAPACITY_MAXIMUM_FAILED_RATE' => '0.01',
                'SQUIRRELFORGE_CAPACITY_MAXIMUM_P95_MS' => '1000',
                'SQUIRRELFORGE_CAPACITY_MINIMUM_CHECK_RATE' => '0.99',
                'SQUIRRELFORGE_CAPACITY_MINIMUM_ITERATIONS' => '10',
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
}
