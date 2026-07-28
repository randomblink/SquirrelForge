<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;
use SquirrelForge\Resilience\RetryManager;
use SquirrelForge\Resilience\SqliteSelfHealingEngine;

final class SqliteSelfHealingEngineTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        $this->databasePath = sys_get_temp_dir() . '/squirrelforge-self-healing-' . bin2hex(random_bytes(8)) . '.sqlite';
    }

    protected function tearDown(): void
    {
        foreach ([$this->databasePath, $this->databasePath . '-shm', $this->databasePath . '-wal'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testUnregisteredActionIsRejectedWithoutPersisting(): void
    {
        $engine = new SqliteSelfHealingEngine($this->databasePath);
        $result = $engine->heal('service_restart');

        $this->assertSame('rejected', $result['state']);
        $this->assertNull($result['healing_ref']);
        $this->assertStringContainsString('not a registered, approved', (string) $result['error']);
        $this->assertSame([], $engine->recent());
    }

    public function testRegisteredActionSucceedsAndDefaultsToNotVerified(): void
    {
        $engine = new SqliteSelfHealingEngine($this->databasePath, new RetryManager(fn() => null));
        $engine->registerAction('cache_refresh', static fn(): string => 'refreshed');

        $result = $engine->heal('cache_refresh', ['failure_ref' => 'failure_detection_abc']);

        $this->assertSame('successful', $result['state']);
        $this->assertSame('not_verified', $result['verification_status']);
        $this->assertSame('failure_detection_abc', $result['failure_ref']);
        $this->assertSame('ungoverned', $result['governance_status']);
    }

    public function testFixedRegistrationPolicyIsUsedRegardlessOfCallerInput(): void
    {
        $attempts = 0;
        $engine = new SqliteSelfHealingEngine($this->databasePath, new RetryManager(fn() => null));
        $engine->registerAction(
            'queue_recovery',
            static function () use (&$attempts): never {
                $attempts++;

                throw new RuntimeException('queue still stuck');
            },
            ['max_attempts' => 2]
        );

        // heal() takes no retry_policy option at all -- the approved limit always wins.
        $result = $engine->heal('queue_recovery');

        $this->assertSame('escalated', $result['state']);
        $this->assertSame(2, $attempts);
    }

    public function testSuccessfulActionEscalatesWhenVerificationFails(): void
    {
        $engine = new SqliteSelfHealingEngine($this->databasePath, new RetryManager(fn() => null));
        $engine->registerAction('connection_reestablish', static fn(): string => 'connected');

        $result = $engine->heal('connection_reestablish', ['verifier' => static fn(): bool => false]);

        $this->assertSame('escalated', $result['state']);
        $this->assertSame('failed', $result['verification_status']);
        $this->assertNotNull($result['error']);
    }

    public function testSuccessfulActionIsVerifiedWhenVerifierPasses(): void
    {
        $engine = new SqliteSelfHealingEngine($this->databasePath, new RetryManager(fn() => null));
        $engine->registerAction('connection_reestablish', static fn(): string => 'connected');

        $result = $engine->heal('connection_reestablish', ['verifier' => static fn(): bool => true]);

        $this->assertSame('successful', $result['state']);
        $this->assertSame('verified', $result['verification_status']);
    }

    public function testNonRetryableFailureIsRecordedAsFailedNotEscalated(): void
    {
        $engine = new SqliteSelfHealingEngine($this->databasePath, new RetryManager(fn() => null));
        $engine->registerAction(
            'session_recovery',
            static function (): never {
                throw new RuntimeException('unrecoverable session state');
            },
            ['max_attempts' => 3, 'retryable' => static fn(): bool => false]
        );

        $result = $engine->heal('session_recovery');

        $this->assertSame('failed', $result['state']);
        $this->assertSame('unrecoverable session state', $result['error']);
    }

    public function testFinishedHealingDispatchesEvent(): void
    {
        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        $events->listen('self_healing.finished', new CallbackEventListener(
            function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }
        ));

        $engine = new SqliteSelfHealingEngine($this->databasePath, new RetryManager(fn() => null), $events);
        $engine->registerAction('cache_refresh', static fn(): string => 'refreshed');
        $result = $engine->heal('cache_refresh');

        $this->assertCount(1, $captured);
        $this->assertSame($result['healing_ref'], $captured[0]->getPayload()['healing_ref']);
        $this->assertSame('successful', $captured[0]->getPayload()['state']);
    }

    public function testOperationRetrievesAPersistedRecordByRef(): void
    {
        $engine = new SqliteSelfHealingEngine($this->databasePath, new RetryManager(fn() => null));
        $engine->registerAction('cache_refresh', static fn(): string => 'refreshed');
        $created = $engine->heal('cache_refresh');

        $fetched = $engine->operation($created['healing_ref']);

        $this->assertNotNull($fetched);
        $this->assertSame('successful', $fetched['state']);
    }

    public function testOperationReturnsNullForUnknownRef(): void
    {
        $engine = new SqliteSelfHealingEngine($this->databasePath);

        $this->assertNull($engine->operation('self_healing_does_not_exist'));
    }

    public function testRecentOrdersMostRecentFirstAndRespectsLimit(): void
    {
        $engine = new SqliteSelfHealingEngine($this->databasePath, new RetryManager(fn() => null));
        $engine->registerAction('cache_refresh', static fn(): string => 'refreshed');
        $engine->registerAction('queue_recovery', static fn(): string => 'recovered');

        $engine->heal('cache_refresh');
        $engine->heal('queue_recovery');

        $recent = $engine->recent(1);

        $this->assertCount(1, $recent);
        $this->assertSame('queue_recovery', $recent[0]['action']);
    }
}
