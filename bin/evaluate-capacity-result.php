#!/usr/bin/env php
<?php

declare(strict_types=1);

try {
    $summaryPath = requiredEnvironment('SQUIRRELFORGE_CAPACITY_SUMMARY_PATH');
    $receiptPath = requiredEnvironment('SQUIRRELFORGE_CAPACITY_RECEIPT_PATH');
    $profile = requiredEnvironment('SQUIRRELFORGE_CAPACITY_PROFILE');
    $maximumFailedRate = rate('SQUIRRELFORGE_CAPACITY_MAXIMUM_FAILED_RATE', 0.01);
    $maximumP95Ms = positiveNumber('SQUIRRELFORGE_CAPACITY_MAXIMUM_P95_MS', 1000);
    $minimumCheckRate = rate('SQUIRRELFORGE_CAPACITY_MINIMUM_CHECK_RATE', 0.99);
    $minimumIterations = positiveInteger('SQUIRRELFORGE_CAPACITY_MINIMUM_ITERATIONS', 1);

    if (!is_file($summaryPath)) {
        throw new RuntimeException('The capacity summary does not exist.');
    }
    if (file_exists($receiptPath)) {
        throw new RuntimeException('The capacity receipt output already exists.');
    }

    $contents = file_get_contents($summaryPath);
    if (!is_string($contents)) {
        throw new RuntimeException('Unable to read the capacity summary.');
    }
    $summary = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($summary)) {
        throw new RuntimeException('The capacity summary must be a JSON object.');
    }

    $failedRate = metric($summary, 'http_req_failed', 'rate');
    $p95Ms = metric($summary, 'http_req_duration', 'p(95)');
    $checkRate = metric($summary, 'checks', 'rate');
    $iterations = metric($summary, 'iterations', 'count');
    $maximumVirtualUsers = metric($summary, 'vus_max', 'max');

    $failures = [];
    if ($failedRate > $maximumFailedRate) {
        $failures[] = 'REQUEST_FAILURE_RATE_EXCEEDED';
    }
    if ($p95Ms > $maximumP95Ms) {
        $failures[] = 'P95_LATENCY_EXCEEDED';
    }
    if ($checkRate < $minimumCheckRate) {
        $failures[] = 'WORKFLOW_CHECK_RATE_BELOW_MINIMUM';
    }
    if ($iterations < $minimumIterations) {
        $failures[] = 'INSUFFICIENT_ITERATIONS';
    }

    $receipt = [
        'schema_version' => 1,
        'status' => $failures === [] ? 'PASSED' : 'FAILED',
        'profile' => $profile,
        'evaluated_at' => gmdate('Y-m-d\TH:i:s\Z'),
        'summary_sha256' => hash('sha256', $contents),
        'observed' => [
            'request_failure_rate' => $failedRate,
            'p95_latency_ms' => $p95Ms,
            'workflow_check_rate' => $checkRate,
            'iterations' => (int) $iterations,
            'maximum_virtual_users' => (int) $maximumVirtualUsers,
        ],
        'limits' => [
            'maximum_failed_rate' => $maximumFailedRate,
            'maximum_p95_ms' => $maximumP95Ms,
            'minimum_check_rate' => $minimumCheckRate,
            'minimum_iterations' => $minimumIterations,
        ],
        'failure_codes' => $failures,
    ];

    $json = json_encode($receipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    if (file_put_contents($receiptPath, $json . PHP_EOL, LOCK_EX) === false) {
        throw new RuntimeException('Unable to write the capacity receipt.');
    }
    chmod($receiptPath, 0600);
    fwrite(STDOUT, $receipt['status'] . PHP_EOL);
    exit($failures === [] ? 0 : 1);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Capacity result evaluation failed: ' . $exception->getMessage() . PHP_EOL);
    exit(2);
}

function requiredEnvironment(string $name): string
{
    $value = getenv($name);
    if (!is_string($value) || trim($value) === '') {
        throw new RuntimeException($name . ' is required.');
    }
    return $value;
}

function rate(string $name, float $default): float
{
    $value = number($name, $default);
    if ($value < 0 || $value > 1) {
        throw new RuntimeException($name . ' must be between 0 and 1.');
    }
    return $value;
}

function positiveNumber(string $name, float $default): float
{
    $value = number($name, $default);
    if ($value <= 0) {
        throw new RuntimeException($name . ' must be greater than zero.');
    }
    return $value;
}

function positiveInteger(string $name, int $default): int
{
    $raw = getenv($name);
    if ($raw === false || $raw === '') {
        return $default;
    }
    if (!ctype_digit($raw) || (int) $raw < 1) {
        throw new RuntimeException($name . ' must be a positive integer.');
    }
    return (int) $raw;
}

function number(string $name, float $default): float
{
    $raw = getenv($name);
    if ($raw === false || $raw === '') {
        return $default;
    }
    if (!is_numeric($raw)) {
        throw new RuntimeException($name . ' must be numeric.');
    }
    return (float) $raw;
}

/** @param array<string, mixed> $summary */
function metric(array $summary, string $metric, string $value): float
{
    $observed = $summary['metrics'][$metric]['values'][$value] ?? null;
    if (!is_int($observed) && !is_float($observed)) {
        throw new RuntimeException("Capacity summary metric $metric.$value is missing.");
    }
    return (float) $observed;
}
