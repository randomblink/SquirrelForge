<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\RuntimeConfig;

use PHPUnit\Framework\TestCase;
use SquirrelForge\RuntimeConfig\SqliteConfigurationAudit;
use SquirrelForge\RuntimeConfig\SqliteConfigurationRegistry;
use SquirrelForge\RuntimeConfig\SqliteFeatureFlags;

final class SqliteFeatureFlagsTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-feature-flags-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function registry(): SqliteConfigurationRegistry
    {
        return new SqliteConfigurationRegistry($this->tempPath('registry'), new SqliteConfigurationAudit($this->tempPath('registry-audit')));
    }

    /**
     * @return array<string, mixed>
     */
    private function entryFor(array $overrides = []): array
    {
        return array_replace([
            'name' => 'new_checkout_flow',
            'actor_ref' => 'admin_console',
        ], $overrides);
    }

    // --- registerFlag(): shape validation ---

    public function testMissingNameIsInvalid(): void
    {
        $flags = new SqliteFeatureFlags($this->tempPath('db'), $this->registry());

        $result = $flags->registerFlag($this->entryFor(['name' => '']));

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testInvalidInitialStateIsInvalid(): void
    {
        $flags = new SqliteFeatureFlags($this->tempPath('db'), $this->registry());

        $result = $flags->registerFlag($this->entryFor(['initial_state' => 'OnFire']));

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testNoRegistryComposedIsRejected(): void
    {
        $flags = new SqliteFeatureFlags($this->tempPath('db'));

        $result = $flags->registerFlag($this->entryFor());

        $this->assertSame('rejected', $result['outcome']);
    }

    // --- registerFlag(): success ---

    public function testRegisterDefaultsToDisabled(): void
    {
        $flags = new SqliteFeatureFlags($this->tempPath('db'), $this->registry());

        $result = $flags->registerFlag($this->entryFor());

        $this->assertSame('registered', $result['outcome']);
        $this->assertSame('Disabled', $result['state']);
    }

    public function testRegisterCanStartEnabled(): void
    {
        $flags = new SqliteFeatureFlags($this->tempPath('db'), $this->registry());

        $result = $flags->registerFlag($this->entryFor(['initial_state' => 'Enabled']));

        $this->assertSame('Enabled', $result['state']);
    }

    public function testRegisterRecordsARealAuditEvent(): void
    {
        $audit = new SqliteConfigurationAudit($this->tempPath('flag-audit'));
        $flags = new SqliteFeatureFlags($this->tempPath('db'), $this->registry(), $audit);

        $result = $flags->registerFlag($this->entryFor());

        $history = $audit->history($result['flag_id']);
        $this->assertSame('Feature Flag Changed', $history[0]['event']);
    }

    // --- transition() ---

    public function testTransitionToUnrecognizedStateIsInvalid(): void
    {
        $flags = new SqliteFeatureFlags($this->tempPath('db'), $this->registry());
        $registered = $flags->registerFlag($this->entryFor());

        $result = $flags->transition($registered['flag_id'], 'OnFire', 'admin_console');

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testTransitionOnUnknownFlagIsInvalid(): void
    {
        $flags = new SqliteFeatureFlags($this->tempPath('db'), $this->registry());

        $result = $flags->transition('ghost', 'Enabled', 'admin_console');

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testRetiredIsTerminal(): void
    {
        $flags = new SqliteFeatureFlags($this->tempPath('db'), $this->registry());
        $registered = $flags->registerFlag($this->entryFor());
        $flags->transition($registered['flag_id'], 'Retired', 'admin_console');

        $result = $flags->transition($registered['flag_id'], 'Enabled', 'admin_console');

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('terminal', $result['error']);
    }

    public function testTransitionSucceedsAndRecordsHistory(): void
    {
        $audit = new SqliteConfigurationAudit($this->tempPath('flag-audit'));
        $flags = new SqliteFeatureFlags($this->tempPath('db'), $this->registry(), $audit);
        $registered = $flags->registerFlag($this->entryFor());

        $result = $flags->transition($registered['flag_id'], 'Enabled', 'admin_console', 'rollout begins');

        $this->assertSame('transitioned', $result['outcome']);
        $history = $audit->history($registered['flag_id']);
        $this->assertCount(2, $history);
    }

    // --- setKillSwitch(): a record only, never an action ---

    public function testSetKillSwitchOnUnknownFlagIsInvalid(): void
    {
        $flags = new SqliteFeatureFlags($this->tempPath('db'), $this->registry());

        $result = $flags->setKillSwitch('ghost', true, 'admin_console');

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testSetKillSwitchRecordsStatus(): void
    {
        $audit = new SqliteConfigurationAudit($this->tempPath('flag-audit'));
        $flags = new SqliteFeatureFlags($this->tempPath('db'), $this->registry(), $audit);
        $registered = $flags->registerFlag($this->entryFor(['initial_state' => 'Enabled']));

        $result = $flags->setKillSwitch($registered['flag_id'], true, 'admin_console', 'incident 42');

        $this->assertSame('recorded', $result['outcome']);
        $this->assertTrue($result['kill_switch_engaged']);
        $this->assertCount(2, $audit->history($registered['flag_id']));
    }

    // --- evaluate(): resolution + real targeting ---

    public function testEvaluateOnUnknownFlagIsNotFound(): void
    {
        $flags = new SqliteFeatureFlags($this->tempPath('db'), $this->registry());

        $result = $flags->evaluate('ghost');

        $this->assertSame('not_found', $result['outcome']);
    }

    public function testDisabledFlagNeverMatchesAnyContext(): void
    {
        $flags = new SqliteFeatureFlags($this->tempPath('db'), $this->registry());
        $registered = $flags->registerFlag($this->entryFor());

        $result = $flags->evaluate($registered['flag_id'], ['user_id' => 'anyone']);

        $this->assertSame('Disabled', $result['resolved_state']);
        $this->assertFalse($result['matched']);
    }

    public function testRetiredFlagNeverMatches(): void
    {
        $flags = new SqliteFeatureFlags($this->tempPath('db'), $this->registry());
        $registered = $flags->registerFlag($this->entryFor(['initial_state' => 'Enabled']));
        $flags->transition($registered['flag_id'], 'Retired', 'admin_console');

        $result = $flags->evaluate($registered['flag_id']);

        $this->assertFalse($result['matched']);
    }

    public function testKillSwitchOverridesAnEnabledFlagToDisabled(): void
    {
        $flags = new SqliteFeatureFlags($this->tempPath('db'), $this->registry());
        $registered = $flags->registerFlag($this->entryFor(['initial_state' => 'Enabled']));
        $flags->setKillSwitch($registered['flag_id'], true, 'admin_console');

        $result = $flags->evaluate($registered['flag_id']);

        $this->assertSame('Disabled', $result['resolved_state']);
        $this->assertFalse($result['matched']);
    }

    public function testEnabledFlagWithNoTargetingRulesMatchesEveryContext(): void
    {
        $flags = new SqliteFeatureFlags($this->tempPath('db'), $this->registry());
        $registered = $flags->registerFlag($this->entryFor(['initial_state' => 'Enabled']));

        $result = $flags->evaluate($registered['flag_id'], ['user_id' => 'anyone']);

        $this->assertTrue($result['matched']);
        $this->assertSame('Enabled', $result['resolved_state']);
    }

    public function testTargetingRuleMatchesAnAllowedContextValue(): void
    {
        $flags = new SqliteFeatureFlags($this->tempPath('db'), $this->registry());
        $registered = $flags->registerFlag($this->entryFor([
            'initial_state' => 'Beta',
            'targeting_rules' => [['context_key' => 'user_id', 'allowed_values' => ['user_42']]],
        ]));

        $result = $flags->evaluate($registered['flag_id'], ['user_id' => 'user_42']);

        $this->assertTrue($result['matched']);
    }

    public function testTargetingRuleExcludesADisallowedContextValue(): void
    {
        $flags = new SqliteFeatureFlags($this->tempPath('db'), $this->registry());
        $registered = $flags->registerFlag($this->entryFor([
            'initial_state' => 'Beta',
            'targeting_rules' => [['context_key' => 'user_id', 'allowed_values' => ['user_42']]],
        ]));

        $result = $flags->evaluate($registered['flag_id'], ['user_id' => 'user_99']);

        $this->assertFalse($result['matched']);
    }

    // --- get() / history() ---

    public function testGetCombinesRegistryOwnerWithOwnState(): void
    {
        $flags = new SqliteFeatureFlags($this->tempPath('db'), $this->registry());
        $registered = $flags->registerFlag($this->entryFor());

        $record = $flags->get($registered['flag_id']);

        $this->assertSame('new_checkout_flow', $record['name']);
        $this->assertSame('admin_console', $record['owner']);
        $this->assertSame('Disabled', $record['state']);
    }

    public function testGetUnknownFlagReturnsNull(): void
    {
        $flags = new SqliteFeatureFlags($this->tempPath('db'), $this->registry());

        $this->assertNull($flags->get('ghost'));
    }
}
