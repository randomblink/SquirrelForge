<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Storage\CacheManager;

final class CacheManagerTest extends TestCase
{
    public function testSetAndGetRoundTrip(): void
    {
        $cache = new CacheManager();
        $cache->set('key_1', 'value_1', 60);

        $this->assertSame('value_1', $cache->get('key_1'));
        $this->assertTrue($cache->has('key_1'));
    }

    public function testMissingKeyReturnsNull(): void
    {
        $cache = new CacheManager();

        $this->assertNull($cache->get('missing'));
        $this->assertFalse($cache->has('missing'));
    }

    public function testAbsoluteExpirationNeverReturnsExpiredData(): void
    {
        $now = 1000;
        $cache = new CacheManager(function () use (&$now): int {
            return $now;
        });

        $cache->set('key_1', 'value_1', 10);
        $now += 11;

        $this->assertNull($cache->get('key_1'));
        $this->assertFalse($cache->has('key_1'));
    }

    public function testSlidingExpirationExtendsOnRead(): void
    {
        $now = 1000;
        $cache = new CacheManager(function () use (&$now): int {
            return $now;
        });

        $cache->set('key_1', 'value_1', 10, sliding: true);

        $now += 8;
        $this->assertSame('value_1', $cache->get('key_1')); // reset expiry to now+10

        $now += 8;
        $this->assertSame('value_1', $cache->get('key_1')); // still valid because it was extended

        $now += 11;
        $this->assertNull($cache->get('key_1'));
    }

    public function testAbsoluteExpirationDoesNotExtendOnRead(): void
    {
        $now = 1000;
        $cache = new CacheManager(function () use (&$now): int {
            return $now;
        });

        $cache->set('key_1', 'value_1', 10);

        $now += 8;
        $this->assertSame('value_1', $cache->get('key_1'));

        $now += 8; // total 16 elapsed, past the original 10s TTL
        $this->assertNull($cache->get('key_1'));
    }

    public function testInvalidateRemovesEntry(): void
    {
        $cache = new CacheManager();
        $cache->set('key_1', 'value_1', 60);

        $cache->invalidate('key_1');

        $this->assertNull($cache->get('key_1'));
    }

    public function testRememberOnlyCallsResolverOnMiss(): void
    {
        $cache = new CacheManager();
        $calls = 0;
        $resolver = function () use (&$calls): string {
            $calls++;

            return 'resolved';
        };

        $first = $cache->remember('key_1', 60, $resolver);
        $second = $cache->remember('key_1', 60, $resolver);

        $this->assertSame('resolved', $first);
        $this->assertSame('resolved', $second);
        $this->assertSame(1, $calls);
    }

    public function testRememberReturnsGenuinelyCachedNullWithoutReResolving(): void
    {
        $cache = new CacheManager();
        $calls = 0;
        $resolver = function () use (&$calls) {
            $calls++;

            return null;
        };

        $cache->remember('key_1', 60, $resolver);
        $cache->remember('key_1', 60, $resolver);

        $this->assertSame(1, $calls);
    }

    public function testRecentOperationsReflectsHitsMissesAndExpirations(): void
    {
        $now = 1000;
        $cache = new CacheManager(function () use (&$now): int {
            return $now;
        });

        $cache->set('key_1', 'value_1', 10);
        $cache->get('key_1');
        $cache->get('missing');
        $now += 11;
        $cache->get('key_1');

        $outcomes = array_column($cache->recentOperations(), 'outcome');

        $this->assertSame(['stored', 'hit', 'miss', 'miss'], $outcomes);
    }

    public function testRecentOperationsRespectsRingBufferCap(): void
    {
        $cache = new CacheManager();

        for ($i = 0; $i < 505; $i++) {
            $cache->set('key_' . $i, 'value', 60);
        }

        $this->assertCount(500, $cache->recentOperations(1000));
        $this->assertCount(10, $cache->recentOperations(10));
    }
}
