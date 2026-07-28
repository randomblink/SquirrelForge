<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SquirrelForge\Communication\SqliteMessageBroker;
use SquirrelForge\Communication\SqliteMessageValidator;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;
use SquirrelForge\Resilience\RetryManager;
use SquirrelForge\Security\StaticAuthorizationManager;

final class SqliteMessageBrokerTest extends TestCase
{
    /** @var array<int, string> */
    private array $databasePaths = [];

    protected function tearDown(): void
    {
        foreach ($this->databasePaths as $path) {
            foreach ([$path, $path . '-shm', $path . '-wal'] as $candidate) {
                if (is_file($candidate)) {
                    unlink($candidate);
                }
            }
        }
    }

    private function tempPath(string $label): string
    {
        $path = sys_get_temp_dir() . "/squirrelforge-broker-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function validMessage(array $overrides = []): array
    {
        return array_merge([
            'message_id' => 'message_1',
            'source' => 'agent_1',
            'destination' => 'agent_2',
            'operation' => 'permission:allowed',
            'payload' => ['body' => 'hello'],
        ], $overrides);
    }

    /**
     * @return array{0: SqliteMessageValidator, 1: SqliteMessageBroker}
     */
    private function makeBroker(?EventBusInterface $events = null): array
    {
        $validator = new SqliteMessageValidator($this->tempPath('validator'));
        $broker = new SqliteMessageBroker($this->tempPath('broker'), $validator, new RetryManager(fn() => null), $events);

        return [$validator, $broker];
    }

    public function testValidMessageWithARegisteredRouteIsDelivered(): void
    {
        [, $broker] = $this->makeBroker();
        $delivered = [];
        $broker->registerRoute('agent_2', function (array $message) use (&$delivered): void {
            $delivered[] = $message;
        });

        $result = $broker->route($this->validMessage());

        $this->assertSame('delivered', $result['delivery_status']);
        $this->assertSame('direct', $result['routing_rule']);
        $this->assertCount(1, $delivered);
    }

    public function testInvalidMessageIsNeverRoutedEvenWithARegisteredHandler(): void
    {
        [, $broker] = $this->makeBroker();
        $handlerCalled = false;
        $broker->registerRoute('agent_2', function () use (&$handlerCalled): void {
            $handlerCalled = true;
        });

        $message = $this->validMessage();
        unset($message['payload']);

        $result = $broker->route($message);

        $this->assertSame('rejected', $result['delivery_status']);
        $this->assertFalse($handlerCalled);
    }

    public function testAuthorizationDenialBlocksRoutingEvenWithARegisteredHandler(): void
    {
        $validator = new SqliteMessageValidator(
            $this->tempPath('validator-authz'),
            authorization: new StaticAuthorizationManager('permission:allowed')
        );
        $broker = new SqliteMessageBroker($this->tempPath('broker-authz'), $validator, new RetryManager(fn() => null));
        $handlerCalled = false;
        $broker->registerRoute('agent_2', function () use (&$handlerCalled): void {
            $handlerCalled = true;
        });

        $result = $broker->route($this->validMessage(['operation' => 'permission:not-allowed']));

        $this->assertSame('blocked', $result['delivery_status']);
        $this->assertFalse($handlerCalled);
    }

    public function testUnregisteredDestinationFailsWithoutInvokingRetries(): void
    {
        [, $broker] = $this->makeBroker();

        $result = $broker->route($this->validMessage(['destination' => 'agent_unknown']));

        $this->assertSame('failed', $result['delivery_status']);
        $this->assertStringContainsString('No route registered', (string) $result['error']);
    }

    public function testHandlerFailureExhaustsRetriesAndEscalates(): void
    {
        [, $broker] = $this->makeBroker();
        $attempts = 0;
        $broker->registerRoute('agent_2', function () use (&$attempts): never {
            $attempts++;

            throw new RuntimeException('destination unreachable');
        });

        $result = $broker->route($this->validMessage(), ['max_attempts' => 2]);

        $this->assertSame('escalated', $result['delivery_status']);
        $this->assertSame(2, $attempts);
    }

    public function testRoutedMessageDispatchesEvent(): void
    {
        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        $events->listen('message.routed', new CallbackEventListener(
            function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }
        ));

        [, $broker] = $this->makeBroker($events);
        $broker->registerRoute('agent_2', static fn(): string => 'ok');
        $result = $broker->route($this->validMessage());

        $this->assertCount(1, $captured);
        $this->assertSame($result['broker_ref'], $captured[0]->getPayload()['broker_ref']);
    }

    public function testOperationRetrievesAPersistedRecordByRef(): void
    {
        [, $broker] = $this->makeBroker();
        $broker->registerRoute('agent_2', static fn(): string => 'ok');
        $created = $broker->route($this->validMessage());

        $fetched = $broker->operation($created['broker_ref']);

        $this->assertNotNull($fetched);
        $this->assertSame('delivered', $fetched['delivery_status']);
    }

    public function testOperationReturnsNullForUnknownRef(): void
    {
        [, $broker] = $this->makeBroker();

        $this->assertNull($broker->operation('message_broker_does_not_exist'));
    }

    public function testRecentOrdersMostRecentFirstAndRespectsLimit(): void
    {
        [, $broker] = $this->makeBroker();
        $broker->registerRoute('agent_2', static fn(): string => 'ok');
        $broker->route($this->validMessage(['message_id' => 'message_a']));
        $broker->route($this->validMessage(['message_id' => 'message_b']));

        $recent = $broker->recent(1);

        $this->assertCount(1, $recent);
        $this->assertSame('message_b', $recent[0]['message_id']);
    }
}
