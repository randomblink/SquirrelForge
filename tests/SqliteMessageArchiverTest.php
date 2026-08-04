<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Communication\SqliteMessageArchiver;

final class SqliteMessageArchiverTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        $this->databasePath = sys_get_temp_dir() . '/squirrelforge-message-archiver-' . bin2hex(random_bytes(8)) . '.sqlite';
    }

    protected function tearDown(): void
    {
        foreach ([$this->databasePath, $this->databasePath . '-shm', $this->databasePath . '-wal'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    private function archiver(): SqliteMessageArchiver
    {
        return new SqliteMessageArchiver($this->databasePath);
    }

    public function testArchiveRejectsAnUnsupportedArchiveType(): void
    {
        $archiver = $this->archiver();

        $result = $archiver->archive('astrology', ['message_id' => 'msg_1'], 'operational');

        $this->assertSame('rejected', $result['outcome']);
    }

    public function testArchiveStoresARealImmutableRecord(): void
    {
        $archiver = $this->archiver();

        $result = $archiver->archive('service_message', ['message_id' => 'msg_1', 'source' => 'billing', 'destination' => 'notifications', 'payload' => ['amount' => 100]], 'operational');

        $this->assertSame('archived', $result['outcome']);
        $record = $archiver->get($result['archive_id']);
        $this->assertSame(['amount' => 100], $record['payload']);
        $this->assertFalse($record['legal_hold']);
        $this->assertFalse($record['disposed']);
    }

    public function testVerifyIntegritySucceedsForAnUntamperedRecord(): void
    {
        $archiver = $this->archiver();
        $archived = $archiver->archive('service_message', ['message_id' => 'msg_1', 'payload' => ['x' => 1]], 'operational');

        $result = $archiver->verifyIntegrity($archived['archive_id']);

        $this->assertTrue($result['verified']);
        $this->assertSame('verified', $result['outcome']);
    }

    public function testVerifyIntegrityDetectsATamperedRecord(): void
    {
        $archiver = $this->archiver();
        $archived = $archiver->archive('service_message', ['message_id' => 'msg_1', 'payload' => ['x' => 1]], 'operational');

        $pdo = new \PDO('sqlite:' . $this->databasePath);
        $pdo->exec("UPDATE archived_messages SET payload_json = '{\"x\":2}' WHERE archive_id = '{$archived['archive_id']}'");

        $result = $archiver->verifyIntegrity($archived['archive_id']);

        $this->assertFalse($result['verified']);
        $this->assertSame('corrupted', $result['outcome']);
    }

    public function testRetrieveFiltersByMultipleCriteria(): void
    {
        $archiver = $this->archiver();
        $archiver->archive('service_message', ['message_id' => 'msg_1', 'source' => 'billing', 'destination' => 'notifications', 'payload' => []], 'operational');
        $archiver->archive('service_message', ['message_id' => 'msg_2', 'source' => 'billing', 'destination' => 'audit', 'payload' => []], 'compliance');

        $result = $archiver->retrieve(['source' => 'billing', 'destination' => 'notifications']);

        $this->assertCount(1, $result);
        $this->assertSame('msg_1', $result[0]['message_id']);
    }

    public function testRetrieveExcludesDisposedRecords(): void
    {
        $archiver = $this->archiver();
        $archived = $archiver->archive('service_message', ['message_id' => 'msg_1', 'payload' => []], 'operational');
        $archiver->dispose($archived['archive_id'], ['retention_period_satisfied' => true, 'governance_approved' => true]);

        $result = $archiver->retrieve(['message_id' => 'msg_1']);

        $this->assertCount(0, $result);
    }

    public function testDisposeIsBlockedWhileLegalHoldIsActive(): void
    {
        $archiver = $this->archiver();
        $archived = $archiver->archive('service_message', ['message_id' => 'msg_1', 'payload' => []], 'operational');
        $archiver->setLegalHold($archived['archive_id'], true);

        $result = $archiver->dispose($archived['archive_id'], ['retention_period_satisfied' => true, 'governance_approved' => true]);

        $this->assertSame('blocked_legal_hold', $result['outcome']);
    }

    public function testDisposeRequiresBothEvidenceFlags(): void
    {
        $archiver = $this->archiver();
        $archived = $archiver->archive('service_message', ['message_id' => 'msg_1', 'payload' => []], 'operational');

        $result = $archiver->dispose($archived['archive_id'], ['retention_period_satisfied' => true]);

        $this->assertSame('blocked_evidence_incomplete', $result['outcome']);
    }

    public function testDisposeSucceedsWithCompleteEvidenceAndNoLegalHold(): void
    {
        $archiver = $this->archiver();
        $archived = $archiver->archive('service_message', ['message_id' => 'msg_1', 'payload' => []], 'operational');

        $result = $archiver->dispose($archived['archive_id'], ['retention_period_satisfied' => true, 'governance_approved' => true]);

        $this->assertSame('disposed', $result['outcome']);
        $this->assertTrue($archiver->get($archived['archive_id'])['disposed']);
    }

    public function testReleasingLegalHoldAllowsDisposal(): void
    {
        $archiver = $this->archiver();
        $archived = $archiver->archive('service_message', ['message_id' => 'msg_1', 'payload' => []], 'operational');
        $archiver->setLegalHold($archived['archive_id'], true);
        $archiver->setLegalHold($archived['archive_id'], false);

        $result = $archiver->dispose($archived['archive_id'], ['retention_period_satisfied' => true, 'governance_approved' => true]);

        $this->assertSame('disposed', $result['outcome']);
    }
}
