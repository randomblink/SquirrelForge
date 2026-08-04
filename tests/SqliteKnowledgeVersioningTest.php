<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Knowledge\SqliteKnowledgeVersioning;

final class SqliteKnowledgeVersioningTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-knowledge-versioning-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function versioning(): SqliteKnowledgeVersioning
    {
        return new SqliteKnowledgeVersioning($this->tempPath('versioning'));
    }

    public function testCreateVersionRejectsAnUnrecognizedChangeType(): void
    {
        $versioning = $this->versioning();

        $result = $versioning->createVersion('knowledge_1', ['body' => 'v1'], 'astrology');

        $this->assertSame('invalid_change_type', $result['outcome']);
    }

    public function testCreateVersionRejectsAParentFromADifferentKnowledgeAsset(): void
    {
        $versioning = $this->versioning();
        $other = $versioning->createVersion('knowledge_2', ['body' => 'other']);

        $result = $versioning->createVersion('knowledge_1', ['body' => 'v1'], 'Update', $other['version_id']);

        $this->assertSame('invalid_parent', $result['outcome']);
    }

    public function testCreateVersionStartsAsDraft(): void
    {
        $versioning = $this->versioning();

        $result = $versioning->createVersion('knowledge_1', ['body' => 'v1']);

        $this->assertSame('created', $result['outcome']);
        $this->assertSame('Draft', $versioning->get($result['version_id'])['status']);
    }

    public function testUpdateSupersedesItsParent(): void
    {
        $versioning = $this->versioning();
        $v1 = $versioning->createVersion('knowledge_1', ['body' => 'v1']);
        $v2 = $versioning->createVersion('knowledge_1', ['body' => 'v2'], 'Update', $v1['version_id']);

        $this->assertSame('Superseded', $versioning->get($v1['version_id'])['status']);
        $this->assertSame($v1['version_id'], $versioning->get($v2['version_id'])['supersedes']);
    }

    public function testApproveIsBlockedFromArchived(): void
    {
        $versioning = $this->versioning();
        $v1 = $versioning->createVersion('knowledge_1', ['body' => 'v1']);
        $versioning->approve($v1['version_id']);
        $versioning->activate($v1['version_id']);
        $versioning->deprecate($v1['version_id']);
        $versioning->archive($v1['version_id']);

        $result = $versioning->approve($v1['version_id']);

        $this->assertSame('blocked', $result['outcome']);
    }

    public function testActivateSupersedesThePreviouslyActiveVersion(): void
    {
        $versioning = $this->versioning();
        $v1 = $versioning->createVersion('knowledge_1', ['body' => 'v1']);
        $versioning->approve($v1['version_id']);
        $versioning->activate($v1['version_id']);

        $v2 = $versioning->createVersion('knowledge_1', ['body' => 'v2']);
        $versioning->approve($v2['version_id']);
        $versioning->activate($v2['version_id']);

        $this->assertSame('Superseded', $versioning->get($v1['version_id'])['status']);
        $this->assertSame('Active', $versioning->get($v2['version_id'])['status']);
    }

    public function testFullLifecycleFromDraftToArchived(): void
    {
        $versioning = $this->versioning();
        $v1 = $versioning->createVersion('knowledge_1', ['body' => 'v1']);

        $approve = $versioning->approve($v1['version_id']);
        $activate = $versioning->activate($v1['version_id']);
        $deprecate = $versioning->deprecate($v1['version_id']);
        $archive = $versioning->archive($v1['version_id']);

        $this->assertSame('transitioned', $approve['outcome']);
        $this->assertSame('transitioned', $activate['outcome']);
        $this->assertSame('transitioned', $deprecate['outcome']);
        $this->assertSame('transitioned', $archive['outcome']);
        $this->assertSame('Archived', $versioning->get($v1['version_id'])['status']);
    }

    public function testRollbackRequiresANonEmptyReason(): void
    {
        $versioning = $this->versioning();
        $v1 = $versioning->createVersion('knowledge_1', ['body' => 'v1']);

        $result = $versioning->rollback('knowledge_1', $v1['version_id'], '');

        $this->assertSame('invalid_reason', $result['outcome']);
    }

    public function testRollbackCreatesAndActivatesANewVersionWithTheOldContentNeverMutatingHistory(): void
    {
        $versioning = $this->versioning();
        $v1 = $versioning->createVersion('knowledge_1', ['body' => 'good']);
        $versioning->approve($v1['version_id']);
        $versioning->activate($v1['version_id']);

        $v2 = $versioning->createVersion('knowledge_1', ['body' => 'bad'], 'Update', $v1['version_id']);
        $versioning->approve($v2['version_id']);
        $versioning->activate($v2['version_id']);

        $result = $versioning->rollback('knowledge_1', $v1['version_id'], 'v2 broke production');

        $this->assertSame('rolled_back', $result['outcome']);
        $rolledBack = $versioning->get($result['version_id']);
        $this->assertSame('Active', $rolledBack['status']);
        $this->assertSame('Rollback', $rolledBack['change_type']);
        $this->assertSame(['body' => 'good'], $rolledBack['content']);
        // The original version 1 content must remain untouched -- rollback never mutates history.
        $this->assertSame(['body' => 'good'], $versioning->get($v1['version_id'])['content']);
        $this->assertSame('Superseded', $versioning->get($v2['version_id'])['status']);
    }

    public function testRecordBranchAndRecordMergeRequireTrackedVersions(): void
    {
        $versioning = $this->versioning();
        $v1 = $versioning->createVersion('knowledge_1', ['body' => 'v1']);

        $branch = $versioning->recordBranch($v1['version_id'], 'experimental');
        $badBranch = $versioning->recordBranch('kversion_missing', 'experimental');
        $badMerge = $versioning->recordMerge($v1['version_id'], 'kversion_missing');

        $this->assertSame('recorded', $branch['outcome']);
        $this->assertSame('not_found', $badBranch['outcome']);
        $this->assertSame('not_found', $badMerge['outcome']);
    }

    public function testCompareVersionsReportsEqualityOfContent(): void
    {
        $versioning = $this->versioning();
        $v1 = $versioning->createVersion('knowledge_1', ['body' => 'same']);
        $v2 = $versioning->createVersion('knowledge_1', ['body' => 'same']);
        $v3 = $versioning->createVersion('knowledge_1', ['body' => 'different']);

        $this->assertTrue($versioning->compareVersions($v1['version_id'], $v2['version_id'])['equal']);
        $this->assertFalse($versioning->compareVersions($v1['version_id'], $v3['version_id'])['equal']);
    }

    public function testLineageReturnsAllVersionsInOrder(): void
    {
        $versioning = $this->versioning();
        $v1 = $versioning->createVersion('knowledge_1', ['body' => 'v1']);
        $v2 = $versioning->createVersion('knowledge_1', ['body' => 'v2'], 'Update', $v1['version_id']);

        $lineage = $versioning->lineage('knowledge_1');

        $this->assertCount(2, $lineage);
        $this->assertSame($v1['version_id'], $lineage[0]['version_id']);
        $this->assertSame($v2['version_id'], $lineage[1]['version_id']);
    }
}
