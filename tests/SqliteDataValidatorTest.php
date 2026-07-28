<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;
use SquirrelForge\Security\StaticAuthorizationManager;
use SquirrelForge\Storage\SqliteDataValidator;

final class SqliteDataValidatorTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        $this->databasePath = sys_get_temp_dir() . '/squirrelforge-data-validations-' . bin2hex(random_bytes(8)) . '.sqlite';
    }

    protected function tearDown(): void
    {
        foreach ([$this->databasePath, $this->databasePath . '-shm', $this->databasePath . '-wal'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testFullyValidRecordWithNoRulesPasses(): void
    {
        $validator = new SqliteDataValidator($this->databasePath);
        $result = $validator->validate('workflow', 'workflow_1', ['name' => 'Deploy']);

        $this->assertSame('valid', $result['result']);
        $this->assertTrue($result['may_proceed']);
        $this->assertSame('ungoverned', $result['governance_readiness']);
    }

    public function testMissingRequiredFieldIsIncomplete(): void
    {
        $validator = new SqliteDataValidator($this->databasePath);
        $result = $validator->validate('workflow', 'workflow_1', ['name' => 'Deploy'], ['required_fields' => ['name', 'owner']]);

        $this->assertSame('incomplete', $result['result']);
        $this->assertFalse($result['may_proceed']);
        $this->assertStringContainsString('owner', (string) $result['error']);
    }

    public function testWrongFieldTypeIsInvalid(): void
    {
        $validator = new SqliteDataValidator($this->databasePath);
        $result = $validator->validate('workflow', 'workflow_1', ['retries' => 'three'], ['field_types' => ['retries' => 'integer']]);

        $this->assertSame('invalid', $result['result']);
        $this->assertStringContainsString('retries', (string) $result['error']);
    }

    public function testMissingRecommendedFieldIsValidWithWarnings(): void
    {
        $validator = new SqliteDataValidator($this->databasePath);
        $result = $validator->validate('workflow', 'workflow_1', ['name' => 'Deploy'], ['recommended_fields' => ['description']]);

        $this->assertSame('valid_with_warnings', $result['result']);
        $this->assertTrue($result['may_proceed']);
        $this->assertStringContainsString('description', (string) $result['error']);
    }

    public function testMatchingChecksumIsVerified(): void
    {
        $validator = new SqliteDataValidator($this->databasePath);
        $data = ['name' => 'Deploy'];
        $checksum = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));

        $result = $validator->validate('workflow', 'workflow_1', $data, ['checksum' => $checksum]);

        $this->assertSame('valid', $result['result']);
        $this->assertSame('verified', $result['integrity_status']);
    }

    public function testMismatchedChecksumIsCorrupted(): void
    {
        $validator = new SqliteDataValidator($this->databasePath);
        $result = $validator->validate('workflow', 'workflow_1', ['name' => 'Deploy'], ['checksum' => str_repeat('a', 64)]);

        $this->assertSame('corrupted', $result['result']);
        $this->assertSame('failed', $result['integrity_status']);
        $this->assertFalse($result['may_proceed']);
    }

    public function testUniqueIdentifierIsAcceptedOnceAndRejectedOnRepeat(): void
    {
        $validator = new SqliteDataValidator($this->databasePath);

        $first = $validator->validate('workflow', 'workflow_1', ['name' => 'Deploy'], ['require_unique' => true]);
        $second = $validator->validate('workflow', 'workflow_1', ['name' => 'Deploy v2'], ['require_unique' => true]);

        $this->assertSame('valid', $first['result']);
        $this->assertSame('invalid', $second['result']);
        $this->assertStringContainsString('already been validated', (string) $second['error']);
    }

    public function testUniquenessIsScopedPerRecordType(): void
    {
        $validator = new SqliteDataValidator($this->databasePath);
        $validator->validate('workflow', 'record_1', ['name' => 'a'], ['require_unique' => true]);

        $result = $validator->validate('document', 'record_1', ['name' => 'b'], ['require_unique' => true]);

        $this->assertSame('valid', $result['result']);
    }

    public function testFailedUniquenessCheckDoesNotConsumeTheIdentifierSlot(): void
    {
        $validator = new SqliteDataValidator($this->databasePath);
        $validator->validate('workflow', 'workflow_1', ['name' => 'Deploy'], ['require_unique' => true, 'checksum' => str_repeat('a', 64)]);

        // The first attempt was corrupted (bad checksum) and never registered the identifier.
        $data = ['name' => 'Deploy'];
        $result = $validator->validate('workflow', 'workflow_1', $data, ['require_unique' => true]);

        $this->assertSame('valid', $result['result']);
    }

    public function testDeniedAuthorizationShortCircuitsBeforeAnyOtherCheck(): void
    {
        $authorization = new StaticAuthorizationManager('permission:allowed');
        $validator = new SqliteDataValidator($this->databasePath, $authorization);

        $result = $validator->validate('workflow', 'workflow_1', [], ['required_fields' => ['name']], ['identity_ref' => 'user_1']);

        $this->assertSame('unauthorized', $result['result']);
        $this->assertSame('denied', $result['authorization_status']);
    }

    public function testGrantedAuthorizationProceedsToOtherChecks(): void
    {
        $authorization = new StaticAuthorizationManager('permission:allowed');
        $validator = new SqliteDataValidator($this->databasePath, $authorization);

        $result = $validator->validate(
            'workflow',
            'workflow_1',
            ['name' => 'Deploy'],
            [],
            ['identity_ref' => 'user_1', 'permission_ref' => 'permission:allowed']
        );

        $this->assertSame('valid', $result['result']);
        $this->assertSame('granted', $result['authorization_status']);
    }

    public function testValidatedRecordDispatchesEvent(): void
    {
        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        $events->listen('data.validated', new CallbackEventListener(
            function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }
        ));

        $validator = new SqliteDataValidator($this->databasePath, events: $events);
        $result = $validator->validate('workflow', 'workflow_1', ['name' => 'Deploy']);

        $this->assertCount(1, $captured);
        $this->assertSame($result['validation_ref'], $captured[0]->getPayload()['validation_ref']);
    }

    public function testValidationRetrievesAPersistedRecordByRef(): void
    {
        $validator = new SqliteDataValidator($this->databasePath);
        $created = $validator->validate('workflow', 'workflow_1', ['name' => 'Deploy']);

        $fetched = $validator->validation($created['validation_ref']);

        $this->assertNotNull($fetched);
        $this->assertSame('valid', $fetched['result']);
    }

    public function testValidationReturnsNullForUnknownRef(): void
    {
        $validator = new SqliteDataValidator($this->databasePath);

        $this->assertNull($validator->validation('data_validation_does_not_exist'));
    }

    public function testRecentOrdersMostRecentFirstAndRespectsLimit(): void
    {
        $validator = new SqliteDataValidator($this->databasePath);
        $validator->validate('workflow', 'workflow_a', []);
        $validator->validate('workflow', 'workflow_b', []);

        $recent = $validator->recent(1);

        $this->assertCount(1, $recent);
        $this->assertSame('workflow_b', $recent[0]['record_id']);
    }
}
