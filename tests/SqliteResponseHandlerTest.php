<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SquirrelForge\Communication\SqliteResponseHandler;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;
use SquirrelForge\Resilience\RetryManager;

final class SqliteResponseHandlerTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        $this->databasePath = sys_get_temp_dir() . '/squirrelforge-responses-' . bin2hex(random_bytes(8)) . '.sqlite';
    }

    protected function tearDown(): void
    {
        foreach ([$this->databasePath, $this->databasePath . '-shm', $this->databasePath . '-wal'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    #[DataProvider('statusCodeProvider')]
    public function testStatusCodesClassifyIntoTheExpectedCategory(int $statusCode, string $expectedCategory): void
    {
        $handler = new SqliteResponseHandler($this->databasePath);
        $result = $handler->process(['status_code' => $statusCode, 'source' => 'github']);

        $this->assertSame($expectedCategory, $result['category']);
        $this->assertSame('accepted', $result['outcome']);
    }

    /**
     * @return array<string, array{0: int, 1: string}>
     */
    public static function statusCodeProvider(): array
    {
        return [
            'success' => [200, 'success'],
            'partial success' => [207, 'partial_success'],
            'validation error' => [400, 'validation_error'],
            'authentication error' => [401, 'authentication_error'],
            'authorization error' => [403, 'authorization_error'],
            'timeout' => [408, 'timeout'],
            'rate limited' => [429, 'rate_limited'],
            'internal service error' => [500, 'internal_service_error'],
            'service unavailable' => [503, 'service_unavailable'],
            'unknown failure' => [999, 'unknown_failure'],
        ];
    }

    public function testMissingStatusCodeIsRejectedWithoutClassification(): void
    {
        $handler = new SqliteResponseHandler($this->databasePath);
        $result = $handler->process(['source' => 'github']);

        $this->assertSame('rejected', $result['outcome']);
        $this->assertSame('invalid', $result['validation_status']);
        $this->assertNull($result['category']);
    }

    public function testMissingRequiredFieldRejectsAWellFormedResponse(): void
    {
        $handler = new SqliteResponseHandler($this->databasePath);
        $result = $handler->process(
            ['status_code' => 200, 'source' => 'github', 'body' => ['id' => 1]],
            ['required_fields' => ['id', 'title']]
        );

        $this->assertSame('rejected', $result['outcome']);
        $this->assertSame('success', $result['category']);
        $this->assertStringContainsString('title', (string) $result['error']);
    }

    public function testNonRetryableCategoryNeverConsultsRetryManager(): void
    {
        $handler = new SqliteResponseHandler($this->databasePath);
        $result = $handler->process(
            ['status_code' => 401, 'source' => 'github'],
            ['retry' => static function (): never {
                throw new RuntimeException('should never be called');
            }]
        );

        $this->assertSame('not_applicable', $result['retry_status']);
    }

    public function testRetryableCategoryWithNoRetryClosureIsMerelyRecommended(): void
    {
        $handler = new SqliteResponseHandler($this->databasePath);
        $result = $handler->process(['status_code' => 503, 'source' => 'github']);

        $this->assertSame('recommended', $result['retry_status']);
    }

    public function testRetryableCategoryWithARetryClosureActuallyDelegatesToRetryManager(): void
    {
        $handler = new SqliteResponseHandler($this->databasePath, new RetryManager(fn() => null));
        $attempts = 0;
        $result = $handler->process(
            ['status_code' => 503, 'source' => 'github'],
            ['retry' => static function () use (&$attempts): string {
                $attempts++;

                return 'ok';
            }]
        );

        $this->assertSame('retried_successful', $result['retry_status']);
        $this->assertSame(1, $attempts);
    }

    public function testRetryableCategoryWhoseRetryExhaustsAttemptsIsRetriedEscalated(): void
    {
        $handler = new SqliteResponseHandler($this->databasePath, new RetryManager(fn() => null));
        $result = $handler->process(
            ['status_code' => 503, 'source' => 'github'],
            [
                'retry' => static function (): never {
                    throw new RuntimeException('still down');
                },
                'retry_policy' => ['max_attempts' => 2],
            ]
        );

        $this->assertSame('retried_escalated', $result['retry_status']);
    }

    public function testProcessedResponseDispatchesEvent(): void
    {
        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        $events->listen('response.processed', new CallbackEventListener(
            function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }
        ));

        $handler = new SqliteResponseHandler($this->databasePath, events: $events);
        $result = $handler->process(['status_code' => 200, 'source' => 'github']);

        $this->assertCount(1, $captured);
        $this->assertSame($result['response_ref'], $captured[0]->getPayload()['response_ref']);
    }

    public function testOperationRetrievesAPersistedRecordByRef(): void
    {
        $handler = new SqliteResponseHandler($this->databasePath);
        $created = $handler->process(['status_code' => 200, 'source' => 'github']);

        $fetched = $handler->operation($created['response_ref']);

        $this->assertNotNull($fetched);
        $this->assertSame('success', $fetched['category']);
    }

    public function testOperationReturnsNullForUnknownRef(): void
    {
        $handler = new SqliteResponseHandler($this->databasePath);

        $this->assertNull($handler->operation('response_does_not_exist'));
    }

    public function testRecentOrdersMostRecentFirstAndRespectsLimit(): void
    {
        $handler = new SqliteResponseHandler($this->databasePath);
        $handler->process(['status_code' => 200, 'source' => 'github', 'request_id' => 'req_a']);
        $handler->process(['status_code' => 500, 'source' => 'github', 'request_id' => 'req_b']);

        $recent = $handler->recent(1);

        $this->assertCount(1, $recent);
        $this->assertSame('req_b', $recent[0]['request_id']);
    }
}
