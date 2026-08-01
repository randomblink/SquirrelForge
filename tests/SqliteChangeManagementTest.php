<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;
use SquirrelForge\Governance\SqliteChangeManagement;
use SquirrelForge\Governance\SqliteQualityGates;
use SquirrelForge\Governance\SqliteVersionManager;

final class SqliteChangeManagementTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-change-management-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function manager(): SqliteChangeManagement
    {
        return new SqliteChangeManagement($this->tempPath('changes'));
    }

    private function validRequest(array $overrides = []): array
    {
        return array_merge([
            'motivation' => 'Reduce checkout abandonment',
            'scope' => 'Checkout flow only',
            'owner' => 'team_checkout',
            'affected_dependencies' => ['checkout-service'],
            'affected_users' => ['all_customers'],
            'compatibility_impact' => 'additive',
            'migration_plan' => null,
            'tests' => 'passed',
            'rollout' => 'Gradual rollout behind a feature flag',
            'rollback' => 'Disable the feature flag',
        ], $overrides);
    }

    public function testRequestChangeRejectsAMissingRequiredField(): void
    {
        $manager = $this->manager();
        $request = $this->validRequest();
        unset($request['rollback']);

        $result = $manager->requestChange('change_1', $request);

        $this->assertSame('rejected', $result['decision']);
        $this->assertStringContainsString('rollback', $result['error']);
    }

    public function testRequestChangeRejectsAnUnapprovedStableContract(): void
    {
        $manager = $this->manager();

        $result = $manager->requestChange('change_1', $this->validRequest([
            'affected_stable_contracts' => ['CheckoutApi'],
            'approvals' => [],
        ]));

        $this->assertSame('rejected', $result['decision']);
        $this->assertStringContainsString('CheckoutApi', $result['error']);
    }

    public function testRequestChangeApprovesWhenAllStableContractsHaveApprovals(): void
    {
        $manager = $this->manager();

        $result = $manager->requestChange('change_1', $this->validRequest([
            'affected_stable_contracts' => ['CheckoutApi'],
            'approvals' => ['CheckoutApi' => 'jane_doe'],
        ]));

        $this->assertSame('approved', $result['decision']);
    }

    public function testRequestChangeWithNoOptionalSpecialistsConfiguredIsApproved(): void
    {
        $manager = $this->manager();

        $result = $manager->requestChange('change_1', $this->validRequest());

        $this->assertSame('approved', $result['decision']);
        $this->assertNull($result['version_id']);
    }

    public function testRequestChangeDefersToQualityGatesForTestsAndMigrationPlan(): void
    {
        $qualityGates = new SqliteQualityGates($this->tempPath('gates'));
        $manager = new SqliteChangeManagement($this->tempPath('changes'), qualityGates: $qualityGates);

        $result = $manager->requestChange('change_1', $this->validRequest([
            'compatibility_impact' => 'breaking',
            'migration_plan' => null,
        ]));

        $this->assertSame('rejected', $result['decision']);
        $this->assertSame('Quality Gates did not approve this change.', $result['error']);
    }

    public function testRequestChangeIsApprovedWhenQualityGatesApprove(): void
    {
        $qualityGates = new SqliteQualityGates($this->tempPath('gates'));
        $manager = new SqliteChangeManagement($this->tempPath('changes'), qualityGates: $qualityGates);

        $result = $manager->requestChange('change_1', $this->validRequest([
            'compatibility_impact' => 'breaking',
            'migration_plan' => 'Run the v2 migration script.',
        ]));

        $this->assertSame('approved', $result['decision']);
    }

    public function testRequestChangeRecordsARealVersionThroughVersionManager(): void
    {
        $versionManager = new SqliteVersionManager($this->tempPath('versions'));
        $manager = new SqliteChangeManagement($this->tempPath('changes'), versionManager: $versionManager);

        $result = $manager->requestChange('change_1', $this->validRequest([
            'record_id' => 'checkout_config',
            'record_type' => 'configuration',
            'content' => ['discount_enabled' => true],
        ]));

        $this->assertSame('approved', $result['decision']);
        $this->assertNotNull($result['version_id']);
        $version = $versionManager->retrieve($result['version_id']);
        $this->assertSame(['discount_enabled' => true], $version['content']);
        $this->assertSame('Reduce checkout abandonment', $version['change_summary']);
        $this->assertSame('team_checkout', $version['authoring_component']);
    }

    public function testHistoryReturnsPersistedRequestsInOrder(): void
    {
        $manager = $this->manager();
        $manager->requestChange('change_1', $this->validRequest());
        $manager->requestChange('change_1', $this->validRequest(['affected_stable_contracts' => ['X'], 'approvals' => []]));

        $history = $manager->history('change_1');

        $this->assertCount(2, $history);
        $this->assertSame('approved', $history[0]['decision']);
        $this->assertSame('rejected', $history[1]['decision']);
    }

    public function testRequestChangeDispatchesAnEvent(): void
    {
        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        $events->listen('change_management.approved', new CallbackEventListener(
            function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }
        ));

        $manager = new SqliteChangeManagement($this->tempPath('changes'), events: $events);
        $manager->requestChange('change_1', $this->validRequest());

        $this->assertCount(1, $captured);
    }
}
