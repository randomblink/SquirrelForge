<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;
use SquirrelForge\Governance\SqliteChangeManagement;
use SquirrelForge\Governance\SqliteDeprecationPolicy;

final class SqliteDeprecationPolicyTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-deprecation-policy-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function policy(): SqliteDeprecationPolicy
    {
        return new SqliteDeprecationPolicy($this->tempPath('deprecation'));
    }

    private function validNotice(array $overrides = []): array
    {
        return array_merge([
            'affected_contract' => 'LegacyCheckoutApi',
            'replacement' => 'CheckoutApiV2',
            'migration_steps' => 'Switch to CheckoutApiV2::submit().',
            'notice_version' => '1.4.0',
            'removal_version' => '2.0.0',
            'owner' => 'team_checkout',
            'compatibility_window' => '6 months',
        ], $overrides);
    }

    public function testAnnounceDeprecationRejectsAMissingField(): void
    {
        $policy = $this->policy();
        $notice = $this->validNotice();
        unset($notice['replacement']);

        $result = $policy->announceDeprecation('notice_1', $notice);

        $this->assertSame('invalid_notice', $result['outcome']);
        $this->assertStringContainsString('replacement', $result['error']);
    }

    public function testAnnounceDeprecationRejectsMalformedVersions(): void
    {
        $policy = $this->policy();

        $result = $policy->announceDeprecation('notice_1', $this->validNotice(['notice_version' => 'v1']));

        $this->assertSame('invalid_version', $result['outcome']);
    }

    public function testAnnounceDeprecationRejectsARemovalVersionInTheSameMajor(): void
    {
        $policy = $this->policy();

        $result = $policy->announceDeprecation('notice_1', $this->validNotice(['notice_version' => '1.4.0', 'removal_version' => '1.9.0']));

        $this->assertSame('invalid_removal_target', $result['outcome']);
    }

    public function testAnnounceDeprecationSucceedsWithAValidNotice(): void
    {
        $policy = $this->policy();

        $result = $policy->announceDeprecation('notice_1', $this->validNotice());

        $this->assertSame('announced', $result['outcome']);
        $this->assertSame('Announced', $result['state']);
    }

    public function testRequestRemovalRejectsAnUnknownNotice(): void
    {
        $policy = $this->policy();

        $result = $policy->requestRemoval('notice_ghost', '2.0.0');

        $this->assertSame('not_found', $result['outcome']);
    }

    public function testRequestRemovalRejectsAMalformedCurrentVersion(): void
    {
        $policy = $this->policy();
        $policy->announceDeprecation('notice_1', $this->validNotice());

        $result = $policy->requestRemoval('notice_1', 'not-a-version');

        $this->assertSame('invalid_version', $result['outcome']);
    }

    public function testRequestRemovalIsTooEarlyBeforeTheAnnouncedMajor(): void
    {
        $policy = $this->policy();
        $policy->announceDeprecation('notice_1', $this->validNotice());

        $result = $policy->requestRemoval('notice_1', '1.9.9', ['migration_support_confirmed' => true]);

        $this->assertSame('too_early', $result['outcome']);
    }

    public function testRequestRemovalRequiresMigrationSupportConfirmation(): void
    {
        $policy = $this->policy();
        $policy->announceDeprecation('notice_1', $this->validNotice());

        $result = $policy->requestRemoval('notice_1', '2.0.0');

        $this->assertSame('migration_unconfirmed', $result['outcome']);
    }

    public function testRequestRemovalSucceedsWithDirectMigrationConfirmation(): void
    {
        $policy = $this->policy();
        $policy->announceDeprecation('notice_1', $this->validNotice());

        $result = $policy->requestRemoval('notice_1', '2.0.0', ['migration_support_confirmed' => true]);

        $this->assertSame('removed', $result['outcome']);
        $this->assertSame('Removed', $result['state']);
        $this->assertSame('2.0.0', $policy->get('notice_1')['removed_at_version']);
    }

    public function testRequestRemovalTwiceIsAnInvalidTransition(): void
    {
        $policy = $this->policy();
        $policy->announceDeprecation('notice_1', $this->validNotice());
        $policy->requestRemoval('notice_1', '2.0.0', ['migration_support_confirmed' => true]);

        $result = $policy->requestRemoval('notice_1', '2.0.0', ['migration_support_confirmed' => true]);

        $this->assertSame('invalid_transition', $result['outcome']);
    }

    public function testRequestRemovalComposesChangeManagementForMigrationConfirmation(): void
    {
        $changeManagement = new SqliteChangeManagement($this->tempPath('changes'));
        $changeManagement->requestChange('migration_change_1', [
            'motivation' => 'Ship CheckoutApiV2',
            'scope' => 'Checkout API',
            'owner' => 'team_checkout',
            'affected_dependencies' => [],
            'affected_users' => ['integrators'],
            'compatibility_impact' => 'additive',
            'migration_plan' => 'Provided in the deprecation notice.',
            'tests' => 'passed',
            'rollout' => 'Immediate',
            'rollback' => 'Revert to CheckoutApiV1',
        ]);
        $policy = new SqliteDeprecationPolicy($this->tempPath('deprecation'), $changeManagement);
        $policy->announceDeprecation('notice_1', $this->validNotice());

        $result = $policy->requestRemoval('notice_1', '2.0.0', ['migration_change_id' => 'migration_change_1']);

        $this->assertSame('removed', $result['outcome']);
    }

    public function testRequestRemovalRejectsAnUnapprovedComposedChange(): void
    {
        $policy = $this->policy();
        $policy->announceDeprecation('notice_1', $this->validNotice());

        $result = $policy->requestRemoval('notice_1', '2.0.0', ['migration_change_id' => 'change_that_never_happened']);

        $this->assertSame('migration_unconfirmed', $result['outcome']);
    }

    public function testListByContractReturnsMatchingNotices(): void
    {
        $policy = $this->policy();
        $policy->announceDeprecation('notice_1', $this->validNotice());
        $policy->announceDeprecation('notice_2', $this->validNotice(['affected_contract' => 'OtherApi']));

        $result = $policy->listByContract('LegacyCheckoutApi');

        $this->assertCount(1, $result);
        $this->assertSame('notice_1', $result[0]['notice_id']);
    }

    public function testAnnounceDeprecationDispatchesAnEvent(): void
    {
        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        $events->listen('deprecation_policy.announced', new CallbackEventListener(
            function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }
        ));

        $policy = new SqliteDeprecationPolicy($this->tempPath('deprecation'), events: $events);
        $policy->announceDeprecation('notice_1', $this->validNotice());

        $this->assertCount(1, $captured);
    }
}
