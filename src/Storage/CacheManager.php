<?php

declare(strict_types=1);

namespace SquirrelForge\Storage;

use Closure;

/**
 * An in-memory, single-process TTL cache, per 37_STORAGE/CACHE-MANAGER.md.
 *
 * This is a deliberately scoped subset of that spec: no governance/
 * authorization gate on cache eligibility. `23_GOVERNANCE/POLICY-ENGINE.md`
 * is real now as `SqlitePolicyEngine`, but its `evaluate()` takes a
 * request context and an optional policy category -- this spec names no
 * concrete "cache eligibility" policy category or per-key context shape
 * for a cache read/write to build one from, so wiring it in here would
 * mean inventing a contract the spec never defines. No
 * "Data Monitor" notification (no such component exists), no write-through/
 * write-behind policy (remember()'s resolver closure already covers
 * read-through without inventing a synchronous write-back target), and no
 * distributed/cross-process caching or replication -- this is in-memory
 * only, scoped to one process. Entry states are a real subset of the
 * spec's seven (`created`, `active`, `refreshed`, `expiring`, `expired`,
 * `invalidated`, `removed`): only `active`, `expired`, and `invalidated`
 * are tracked, since the others describe transitions a real background
 * sweep process would drive, which this class doesn't run.
 */
final class CacheManager
{
    private const MAX_OPERATIONS = 500;

    /**
     * @var array<string, array{value: mixed, expiresAt: int, ttlSeconds: int, sliding: bool}>
     */
    private array $entries = [];

    /**
     * @var array<int, array{operation: string, key: string, state: string, outcome: string, timestamp: string}>
     */
    private array $operations = [];

    private readonly Closure $clock;

    /**
     * @param ?Closure $clock () => int, a unix timestamp source. Defaults to
     *                         time(); tests inject a fake clock instead of
     *                         sleep()-ing to exercise expiration.
     */
    public function __construct(?Closure $clock = null)
    {
        $this->clock = $clock ?? static fn(): int => time();
    }

    public function set(string $key, mixed $value, int $ttlSeconds, bool $sliding = false): void
    {
        $this->entries[$key] = [
            'value' => $value,
            'expiresAt' => ($this->clock)() + $ttlSeconds,
            'ttlSeconds' => $ttlSeconds,
            'sliding' => $sliding,
        ];

        $this->record('set', $key, 'active', 'stored');
    }

    /**
     * Never returns an expired entry -- per the spec's own rule, an expired
     * entry is removed and treated as a miss rather than returned.
     */
    public function get(string $key): mixed
    {
        if (!isset($this->entries[$key])) {
            $this->record('get', $key, 'missing', 'miss');

            return null;
        }

        if (($this->clock)() >= $this->entries[$key]['expiresAt']) {
            unset($this->entries[$key]);
            $this->record('get', $key, 'expired', 'miss');

            return null;
        }

        if ($this->entries[$key]['sliding']) {
            $this->entries[$key]['expiresAt'] = ($this->clock)() + $this->entries[$key]['ttlSeconds'];
        }

        $this->record('get', $key, 'active', 'hit');

        return $this->entries[$key]['value'];
    }

    public function has(string $key): bool
    {
        if (!isset($this->entries[$key])) {
            return false;
        }

        if (($this->clock)() >= $this->entries[$key]['expiresAt']) {
            unset($this->entries[$key]);

            return false;
        }

        return true;
    }

    public function invalidate(string $key): void
    {
        if (isset($this->entries[$key])) {
            unset($this->entries[$key]);
            $this->record('invalidate', $key, 'invalidated', 'removed');

            return;
        }

        $this->record('invalidate', $key, 'missing', 'noop');
    }

    /**
     * Read-through caching: return the cached value if present and valid,
     * otherwise call $resolver() for the authoritative value, cache it, and
     * return it. Uses has() rather than a null-check on get() so that a
     * legitimately cached null value is still returned from cache instead
     * of being treated as a miss.
     */
    public function remember(string $key, int $ttlSeconds, Closure $resolver, bool $sliding = false): mixed
    {
        if ($this->has($key)) {
            return $this->get($key);
        }

        $value = $resolver();
        $this->set($key, $value, $ttlSeconds, $sliding);

        return $value;
    }

    /**
     * @return array<int, array{operation: string, key: string, state: string, outcome: string, timestamp: string}>
     */
    public function recentOperations(int $limit = 50): array
    {
        return array_slice($this->operations, -$limit);
    }

    private function record(string $operation, string $key, string $state, string $outcome): void
    {
        $this->operations[] = [
            'operation' => $operation,
            'key' => $key,
            'state' => $state,
            'outcome' => $outcome,
            'timestamp' => gmdate(DATE_RFC3339, ($this->clock)()),
        ];

        if (count($this->operations) > self::MAX_OPERATIONS) {
            array_shift($this->operations);
        }
    }
}
