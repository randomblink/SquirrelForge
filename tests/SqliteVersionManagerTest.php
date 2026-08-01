<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use SquirrelForge\Automation\RuleEngine;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;
use SquirrelForge\Governance\SqlitePolicyEngine;
use SquirrelForge\Governance\SqliteVersionManager;
use SquirrelForge\Security\StaticAuthorizationManager;
use SquirrelForge\Storage\SqliteDataValidator;

final class SqliteVersionManagerTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-version-manager-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function manager(): SqliteVersionManager
    {
        return new SqliteVersionManager($this->tempPath('versions'));
    }

    public function testCreateVersionStartsInDraftWithNoParent(): void
    {
        $manager = $this->manager();

        $result = $manager->createVersion('doc_1', 'document', ['title' => 'Hello']);

        $this->assertSame('created', $result['outcome']);
        $this->assertSame('Draft', $result['state']);
        $this->assertNull($result['parent_version']);
    }

    public function testCreateVersionLinksToTheLatestPriorVersion(): void
    {
        $manager = $this->manager();
        $first = $manager->createVersion('doc_1', 'document', ['title' => 'v1']);

        $second = $manager->createVersion('doc_1', 'document', ['title' => 'v2']);

        $this->assertSame($first['version_id'], $second['parent_version']);
    }

    public function testCreateVersionRejectsWhenValidationFails(): void
    {
        $validator = new SqliteDataValidator($this->tempPath('validator'));
        $manager = new SqliteVersionManager($this->tempPath('versions'), $validator);

        $result = $manager->createVersion('doc_1', 'document', ['title' => 'Hello'], ['required_fields' => ['author']]);

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('author', $result['error']);
    }

    public function testCreateVersionRejectsWhenAuthorizationIsDenied(): void
    {
        $validator = new SqliteDataValidator($this->tempPath('validator'), new StaticAuthorizationManager('permission:allowed'));
        $manager = new SqliteVersionManager($this->tempPath('versions'), $validator);

        $result = $manager->createVersion('doc_1', 'document', ['title' => 'Hello']);

        $this->assertSame('rejected', $result['outcome']);
    }

    public function testCreateVersionRejectsWhenGovernanceDenies(): void
    {
        $policyEngine = new SqlitePolicyEngine($this->tempPath('policy'), new RuleEngine());
        $policyEngine->registerPolicy('deny_destructive', [
            'category' => 'operational', 'priority' => 1,
            'condition' => ['type' => 'boolean', 'field' => 'destructive', 'equals' => true],
            'effect' => 'deny',
        ]);
        $manager = new SqliteVersionManager($this->tempPath('versions'), policyEngine: $policyEngine);

        $result = $manager->createVersion('doc_1', 'document', ['title' => 'Hello'], ['policy_context' => ['destructive' => true]]);

        $this->assertSame('rejected', $result['outcome']);
    }

    public function testCreateVersionCarriesARealAllowedGovernanceStatus(): void
    {
        $policyEngine = new SqlitePolicyEngine($this->tempPath('policy'), new RuleEngine());
        $policyEngine->registerPolicy('allow_safe', [
            'category' => 'operational', 'priority' => 1,
            'condition' => ['type' => 'boolean', 'field' => 'destructive', 'equals' => false],
            'effect' => 'allow',
        ]);
        $manager = new SqliteVersionManager($this->tempPath('versions'), policyEngine: $policyEngine);

        $result = $manager->createVersion('doc_1', 'document', ['title' => 'Hello'], ['policy_context' => ['destructive' => false]]);

        $this->assertSame('created', $result['outcome']);
        $this->assertSame('allowed', $manager->retrieve($result['version_id'])['governance_status']);
    }

    public function testApproveTransitionsFromDraft(): void
    {
        $manager = $this->manager();
        $version = $manager->createVersion('doc_1', 'document', ['title' => 'v1']);

        $result = $manager->approve($version['version_id']);

        $this->assertSame('transitioned', $result['outcome']);
        $this->assertSame('Approved', $manager->retrieve($version['version_id'])['state']);
    }

    public function testApproveRejectsAnInvalidTransition(): void
    {
        $manager = $this->manager();
        $version = $manager->createVersion('doc_1', 'document', ['title' => 'v1']);
        $manager->approve($version['version_id']);

        $result = $manager->approve($version['version_id']);

        $this->assertSame('invalid_transition', $result['outcome']);
    }

    public function testActivateArchivesThePreviouslyActiveVersionForTheSameRecord(): void
    {
        $manager = $this->manager();
        $first = $manager->createVersion('doc_1', 'document', ['title' => 'v1']);
        $manager->approve($first['version_id']);
        $manager->activate($first['version_id']);

        $second = $manager->createVersion('doc_1', 'document', ['title' => 'v2']);
        $manager->approve($second['version_id']);
        $manager->activate($second['version_id']);

        $this->assertSame('Archived', $manager->retrieve($first['version_id'])['state']);
        $this->assertSame('Active', $manager->retrieve($second['version_id'])['state']);
    }

    public function testActivateMakesTheVersionRollbackEligible(): void
    {
        $manager = $this->manager();
        $version = $manager->createVersion('doc_1', 'document', ['title' => 'v1']);
        $manager->approve($version['version_id']);
        $manager->activate($version['version_id']);

        $this->assertTrue($manager->retrieve($version['version_id'])['rollback_eligible']);
    }

    public function testArchiveAndDeprecateRejectFromDraft(): void
    {
        $manager = $this->manager();
        $version = $manager->createVersion('doc_1', 'document', ['title' => 'v1']);

        $this->assertSame('invalid_transition', $manager->archive($version['version_id'])['outcome']);
        $this->assertSame('invalid_transition', $manager->deprecate($version['version_id'])['outcome']);
    }

    public function testRollbackRequiresANonEmptyAuthorizingIdentity(): void
    {
        $manager = $this->manager();
        $version = $manager->createVersion('doc_1', 'document', ['title' => 'v1']);
        $manager->approve($version['version_id']);
        $manager->activate($version['version_id']);

        $result = $manager->rollback('doc_1', $version['version_id'], '');

        $this->assertSame('unauthorized', $result['outcome']);
    }

    public function testRollbackRejectsAVersionThatIsNotEligible(): void
    {
        $manager = $this->manager();
        $version = $manager->createVersion('doc_1', 'document', ['title' => 'v1']);

        $result = $manager->rollback('doc_1', $version['version_id'], 'admin');

        $this->assertSame('not_eligible', $result['outcome']);
    }

    public function testRollbackRejectsAnUnknownVersion(): void
    {
        $manager = $this->manager();

        $result = $manager->rollback('doc_1', 'version_ghost', 'admin');

        $this->assertSame('not_found', $result['outcome']);
    }

    public function testRollbackCreatesANewRestoredVersionRatherThanOverwriting(): void
    {
        $manager = $this->manager();
        $v1 = $manager->createVersion('doc_1', 'document', ['title' => 'v1']);
        $manager->approve($v1['version_id']);
        $manager->activate($v1['version_id']);
        $v2 = $manager->createVersion('doc_1', 'document', ['title' => 'v2']);

        $result = $manager->rollback('doc_1', $v1['version_id'], 'admin');

        $this->assertSame('restored', $result['outcome']);
        $this->assertNotSame($v1['version_id'], $result['version_id']);
        $restored = $manager->retrieve($result['version_id']);
        $this->assertSame('Restored', $restored['state']);
        $this->assertSame(['title' => 'v1'], $restored['content']);
        $this->assertSame($v1['version_id'], $restored['parent_version']);
        // The original version is untouched by the rollback itself.
        $this->assertSame('Active', $manager->retrieve($v1['version_id'])['state']);
    }

    public function testRetrieveReturnsNullForAnUnknownVersion(): void
    {
        $manager = $this->manager();

        $this->assertNull($manager->retrieve('version_ghost'));
    }

    public function testCompareVersionsReportsARealStructuralDiff(): void
    {
        $manager = $this->manager();
        $v1 = $manager->createVersion('doc_1', 'document', ['title' => 'Hello', 'status' => 'draft']);
        $v2 = $manager->createVersion('doc_1', 'document', ['title' => 'Hello World', 'author' => 'Jane']);

        $result = $manager->compareVersions($v1['version_id'], $v2['version_id']);

        $this->assertSame('compared', $result['outcome']);
        $this->assertSame(['author'], $result['added']);
        $this->assertSame(['status'], $result['removed']);
        $this->assertSame(['title' => ['from' => 'Hello', 'to' => 'Hello World']], $result['changed']);
    }

    public function testCompareVersionsRequiresBothVersionsToExist(): void
    {
        $manager = $this->manager();
        $v1 = $manager->createVersion('doc_1', 'document', ['title' => 'v1']);

        $result = $manager->compareVersions($v1['version_id'], 'version_ghost');

        $this->assertSame('not_found', $result['outcome']);
    }

    public function testVerifyIntegrityPassesForAnUntamperedVersion(): void
    {
        $manager = $this->manager();
        $version = $manager->createVersion('doc_1', 'document', ['title' => 'v1']);

        $result = $manager->verifyIntegrity($version['version_id']);

        $this->assertTrue($result['verified']);
        $this->assertSame('verified', $result['outcome']);
    }

    public function testVerifyIntegrityDetectsRealTampering(): void
    {
        $path = $this->tempPath('versions');
        $manager = new SqliteVersionManager($path);
        $version = $manager->createVersion('doc_1', 'document', ['title' => 'v1']);

        $pdo = new PDO('sqlite:' . $path);
        $pdo->prepare('UPDATE versions SET content_json = :content WHERE version_id = :version_id')
            ->execute(['content' => '{"title":"tampered"}', 'version_id' => $version['version_id']]);

        $result = $manager->verifyIntegrity($version['version_id']);

        $this->assertFalse($result['verified']);
        $this->assertSame('corrupted', $result['outcome']);
    }

    public function testHistoryReturnsVersionsInCreationOrder(): void
    {
        $manager = $this->manager();
        $v1 = $manager->createVersion('doc_1', 'document', ['title' => 'v1']);
        $v2 = $manager->createVersion('doc_1', 'document', ['title' => 'v2']);

        $history = $manager->history('doc_1');

        $this->assertSame([$v1['version_id'], $v2['version_id']], array_column($history, 'version_id'));
    }

    public function testIsProductionUsableIsFalseForDraftAndTrueOnceActive(): void
    {
        $manager = $this->manager();
        $version = $manager->createVersion('doc_1', 'document', ['title' => 'v1']);

        $this->assertFalse($manager->isProductionUsable($version['version_id']));

        $manager->approve($version['version_id']);
        $manager->activate($version['version_id']);

        $this->assertTrue($manager->isProductionUsable($version['version_id']));
    }

    public function testCreateVersionDispatchesAnEvent(): void
    {
        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        $events->listen('version.created', new CallbackEventListener(
            function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }
        ));

        $manager = new SqliteVersionManager($this->tempPath('versions'), events: $events);
        $manager->createVersion('doc_1', 'document', ['title' => 'v1']);

        $this->assertCount(1, $captured);
    }
}
