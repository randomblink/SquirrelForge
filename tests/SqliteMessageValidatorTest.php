<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Communication\SqliteMessageValidator;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;
use SquirrelForge\Security\StaticAuthorizationManager;
use SquirrelForge\Tests\Support\FakeAuthenticationManager;

final class SqliteMessageValidatorTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        $this->databasePath = sys_get_temp_dir() . '/squirrelforge-message-validations-' . bin2hex(random_bytes(8)) . '.sqlite';
    }

    protected function tearDown(): void
    {
        foreach ([$this->databasePath, $this->databasePath . '-shm', $this->databasePath . '-wal'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
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

    public function testValidMessageWithNoSecurityLayerWiredIsApprovedAndMarkedNotApplicable(): void
    {
        $validator = new SqliteMessageValidator($this->databasePath);
        $result = $validator->validate($this->validMessage());

        $this->assertSame('valid', $result['status']);
        $this->assertSame('not_applicable', $result['authentication_status']);
        $this->assertSame('not_applicable', $result['authorization_status']);
        $this->assertSame('ungoverned', $result['governance_status']);
    }

    public function testMissingRequiredFieldIsRejectedAndPersisted(): void
    {
        $validator = new SqliteMessageValidator($this->databasePath);
        $message = $this->validMessage();
        unset($message['destination']);

        $result = $validator->validate($message);

        $this->assertSame('rejected', $result['status']);
        $this->assertStringContainsString('destination', (string) $result['error']);
        $this->assertCount(1, $validator->recent());
    }

    public function testUnknownPriorityIsRejected(): void
    {
        $validator = new SqliteMessageValidator($this->databasePath);
        $result = $validator->validate($this->validMessage(['priority' => 'urgent-ish']));

        $this->assertSame('rejected', $result['status']);
        $this->assertStringContainsString('priority', (string) $result['error']);
    }

    public function testFailedAuthenticationBlocksTheMessage(): void
    {
        $authentication = new FakeAuthenticationManager(['good-token']);
        $validator = new SqliteMessageValidator($this->databasePath, $authentication);

        $result = $validator->validate($this->validMessage(), ['access_token' => 'bad-token']);

        $this->assertSame('blocked', $result['status']);
        $this->assertSame('failed', $result['authentication_status']);
        $this->assertCount(1, $authentication->calls);
    }

    public function testSuccessfulAuthenticationWithNoAuthorizerIsApproved(): void
    {
        $authentication = new FakeAuthenticationManager(['good-token']);
        $validator = new SqliteMessageValidator($this->databasePath, $authentication);

        $result = $validator->validate($this->validMessage(), ['access_token' => 'good-token']);

        $this->assertSame('valid', $result['status']);
        $this->assertSame('verified', $result['authentication_status']);
        $this->assertSame('not_applicable', $result['authorization_status']);
    }

    public function testDeniedAuthorizationBlocksTheMessageEvenAfterAuthenticationSucceeds(): void
    {
        $authentication = new FakeAuthenticationManager(['good-token']);
        $authorization = new StaticAuthorizationManager('permission:allowed');
        $validator = new SqliteMessageValidator($this->databasePath, $authentication, $authorization);

        $result = $validator->validate(
            $this->validMessage(['operation' => 'permission:not-allowed']),
            ['access_token' => 'good-token']
        );

        $this->assertSame('blocked', $result['status']);
        $this->assertSame('verified', $result['authentication_status']);
        $this->assertSame('denied', $result['authorization_status']);
    }

    public function testGrantedAuthorizationApprovesTheMessage(): void
    {
        $authentication = new FakeAuthenticationManager(['good-token']);
        $authorization = new StaticAuthorizationManager('permission:allowed');
        $validator = new SqliteMessageValidator($this->databasePath, $authentication, $authorization);

        $result = $validator->validate($this->validMessage(), ['access_token' => 'good-token']);

        $this->assertSame('valid', $result['status']);
        $this->assertSame('granted', $result['authorization_status']);
    }

    public function testValidatedMessageDispatchesEvent(): void
    {
        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        $events->listen('message.validated', new CallbackEventListener(
            function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }
        ));

        $validator = new SqliteMessageValidator($this->databasePath, events: $events);
        $result = $validator->validate($this->validMessage());

        $this->assertCount(1, $captured);
        $this->assertSame($result['validation_ref'], $captured[0]->getPayload()['validation_ref']);
    }

    public function testValidationRetrievesAPersistedRecordByRef(): void
    {
        $validator = new SqliteMessageValidator($this->databasePath);
        $created = $validator->validate($this->validMessage());

        $fetched = $validator->validation($created['validation_ref']);

        $this->assertNotNull($fetched);
        $this->assertSame('valid', $fetched['status']);
    }

    public function testValidationReturnsNullForUnknownRef(): void
    {
        $validator = new SqliteMessageValidator($this->databasePath);

        $this->assertNull($validator->validation('message_validation_does_not_exist'));
    }

    public function testRecentOrdersMostRecentFirstAndRespectsLimit(): void
    {
        $validator = new SqliteMessageValidator($this->databasePath);
        $validator->validate($this->validMessage(['message_id' => 'message_a']));
        $validator->validate($this->validMessage(['message_id' => 'message_b']));

        $recent = $validator->recent(1);

        $this->assertCount(1, $recent);
        $this->assertSame('message_b', $recent[0]['message_id']);
    }
}
