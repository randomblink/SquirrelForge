<?php

declare(strict_types=1);

namespace SquirrelForge\Resilience;

use Closure;
use LogicException;
use Throwable;

/**
 * A general-purpose, operation-agnostic retry-with-backoff utility, per
 * 35_RESILIENCE/RETRY-MANAGER.md.
 *
 * This is a deliberately scoped subset of that spec: it has no integration
 * with an API Gateway/Connector Manager/Service Discovery/Request Router/
 * Response Handler as failure sources (none of those components exist --
 * the caller supplies the operation directly), no governance-gated
 * eligibility check (23_GOVERNANCE has no code; authentication validity is
 * the caller operation's own concern), no failover triggering or
 * "Integration Monitor" notification (Failover Coordinator and Integration
 * Monitor don't exist), and no circuit-breaker-recovery or failover-retry
 * strategy (circuit-breaking already has a real, working home in
 * src/RuntimeConfig/ResilientHttpTransport.php; not duplicated here).
 *
 * Retry states are a real subset of the spec's eight
 * (Pending/Scheduled/Waiting/Retrying/Successful/Failed/Escalated/Abandoned):
 * `retrying`, `successful`, `failed` (the retryable predicate rejected the
 * failure), and `escalated` (attempts exhausted) -- Pending/Scheduled/
 * Waiting/Abandoned describe a real scheduler/queue this synchronous
 * implementation doesn't have.
 */
final class RetryManager
{
    private const STRATEGIES = ['immediate', 'fixed', 'linear', 'exponential', 'exponential_jitter'];

    private readonly Closure $sleeper;

    /**
     * @param ?Closure $sleeper (float $seconds): void, defaults to a real
     *                          usleep()-based wait. Tests inject a capturing
     *                          double instead of actually waiting.
     */
    public function __construct(?Closure $sleeper = null)
    {
        $this->sleeper = $sleeper ?? static function (float $seconds): void {
            if ($seconds > 0) {
                usleep((int) round($seconds * 1_000_000));
            }
        };
    }

    /**
     * @param array{max_attempts?: int, strategy?: string, base_delay_seconds?: float, retryable?: ?Closure} $policy
     * @return array{status: string, result: mixed, attempts: int, error: ?string, log: array<int, array{attempt: int, outcome: string, delay_seconds: ?float}>}
     */
    public function execute(Closure $operation, array $policy = []): array
    {
        $maxAttempts = max(1, $policy['max_attempts'] ?? 3);
        $requestedStrategy = $policy['strategy'] ?? 'exponential';
        $strategy = in_array($requestedStrategy, self::STRATEGIES, true) ? $requestedStrategy : 'exponential';
        $baseDelay = $policy['base_delay_seconds'] ?? 1.0;
        $isRetryable = $policy['retryable'] ?? static fn(Throwable $e): bool => true;

        $log = [];
        $attempts = 0;

        while ($attempts < $maxAttempts) {
            $attempts++;

            try {
                $result = $operation();
                $log[] = ['attempt' => $attempts, 'outcome' => 'successful', 'delay_seconds' => null];

                return ['status' => 'successful', 'result' => $result, 'attempts' => $attempts,
                    'error' => null, 'log' => $log];
            } catch (Throwable $e) {
                if (!$isRetryable($e)) {
                    $log[] = ['attempt' => $attempts, 'outcome' => 'failed', 'delay_seconds' => null];

                    return ['status' => 'failed', 'result' => null, 'attempts' => $attempts,
                        'error' => $e->getMessage(), 'log' => $log];
                }

                if ($attempts >= $maxAttempts) {
                    $log[] = ['attempt' => $attempts, 'outcome' => 'escalated', 'delay_seconds' => null];

                    return ['status' => 'escalated', 'result' => null, 'attempts' => $attempts,
                        'error' => $e->getMessage(), 'log' => $log];
                }

                $delay = $this->delayFor($strategy, $baseDelay, $attempts);
                $log[] = ['attempt' => $attempts, 'outcome' => 'retrying', 'delay_seconds' => $delay];
                ($this->sleeper)($delay);
            }
        }

        // Unreachable: the loop always returns before this point once
        // $attempts reaches $maxAttempts, via the escalated branch above.
        throw new LogicException('RetryManager::execute() reached an unreachable state.');
    }

    private function delayFor(string $strategy, float $baseDelay, int $attempt): float
    {
        return match ($strategy) {
            'immediate' => 0.0,
            'fixed' => $baseDelay,
            'linear' => $baseDelay * $attempt,
            'exponential' => $baseDelay * (2 ** ($attempt - 1)),
            'exponential_jitter' => $baseDelay * (2 ** ($attempt - 1)) * (mt_rand(50, 150) / 100),
        };
    }
}
