<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Configuration;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Automation\RuleEngine;
use SquirrelForge\Configuration\SqlitePermissions;
use SquirrelForge\Governance\SqlitePolicyEngine;

final class SqlitePermissionsTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-permissions-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function permissions(?SqlitePolicyEngine $policyEngine = null): SqlitePermissions
    {
        return new SqlitePermissions($this->tempPath('db'), $policyEngine);
    }

    private function allowingPolicyEngine(): SqlitePolicyEngine
    {
        $engine = new SqlitePolicyEngine($this->tempPath('policy'), new RuleEngine());
        // "duration" is always present (and truthy) in the context declare() builds, so this
        // condition genuinely evaluates rather than deferring on a field that was never supplied.
        $engine->registerPolicy('p1', [
            'category' => 'resource_access',
            'priority' => 1,
            'condition' => ['type' => 'boolean', 'field' => 'duration', 'equals' => true],
            'effect' => 'allow',
        ]);

        return $engine;
    }

    /**
     * @return array{actor: string, capability: string, resource: string, duration: string}
     */
    private function minimalDeclaration(array $overrides = []): array
    {
        return array_replace([
            'actor' => 'agent_developer',
            'capability' => 'write',
            'resource' => 'repo:src/',
            'duration' => 'persistent',
        ], $overrides);
    }

    // --- shape validation ---

    public function testMissingActorIsInvalid(): void
    {
        $permissions = $this->permissions();
        $declaration = $this->minimalDeclaration();
        unset($declaration['actor']);

        $result = $permissions->declare($declaration);

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testUnrecognizedCapabilityIsInvalid(): void
    {
        $permissions = $this->permissions();

        $result = $permissions->declare($this->minimalDeclaration(['capability' => 'made_up']));

        $this->assertSame('invalid', $result['outcome']);
        $this->assertStringContainsString('Capability Types', $result['error']);
    }

    public function testUnrecognizedDurationIsInvalid(): void
    {
        $permissions = $this->permissions();

        $result = $permissions->declare($this->minimalDeclaration(['duration' => 'forever']));

        $this->assertSame('invalid', $result['outcome']);
        $this->assertStringContainsString('Duration', $result['error']);
    }

    public function testTimeLimitedWithoutExpiresAtIsInvalid(): void
    {
        $permissions = $this->permissions();

        $result = $permissions->declare($this->minimalDeclaration(['duration' => 'time_limited']));

        $this->assertSame('invalid', $result['outcome']);
        $this->assertStringContainsString('expires_at', $result['error']);
    }

    public function testTimeLimitedWithExpiresAtSucceeds(): void
    {
        $permissions = $this->permissions();

        $result = $permissions->declare($this->minimalDeclaration([
            'duration' => 'time_limited',
            'expires_at' => gmdate(DATE_ATOM, time() + 3600),
        ]));

        $this->assertSame('declared', $result['outcome']);
    }

    // --- declare() without a PolicyEngine (dry, permissive) ---

    public function testDeclareSucceedsWithoutAPolicyEngine(): void
    {
        $permissions = $this->permissions();

        $result = $permissions->declare($this->minimalDeclaration());

        $this->assertSame('declared', $result['outcome']);
        $this->assertNotNull($result['declaration_id']);
    }

    // --- PolicyEngine composition ---

    public function testDeclareIsRejectedWhenNoPolicyAllowsIt(): void
    {
        $policyEngine = new SqlitePolicyEngine($this->tempPath('policy'), new RuleEngine());
        $permissions = $this->permissions($policyEngine);

        $result = $permissions->declare($this->minimalDeclaration());

        $this->assertSame('rejected', $result['outcome']);
    }

    public function testDeclareSucceedsWhenPolicyEngineAllows(): void
    {
        $permissions = $this->permissions($this->allowingPolicyEngine());

        $result = $permissions->declare($this->minimalDeclaration());

        $this->assertSame('declared', $result['outcome']);
    }

    // --- isDeclared(): narrow, honest lookup ---

    public function testIsDeclaredIsTrueForAMatchingActiveDeclaration(): void
    {
        $permissions = $this->permissions();
        $permissions->declare($this->minimalDeclaration());

        $this->assertTrue($permissions->isDeclared('agent_developer', 'write', 'repo:src/'));
    }

    public function testIsDeclaredIsFalseForNoDeclaration(): void
    {
        $permissions = $this->permissions();

        $this->assertFalse($permissions->isDeclared('agent_developer', 'write', 'repo:src/'));
    }

    public function testCapabilitiesAreEvaluatedIndependently(): void
    {
        $permissions = $this->permissions();
        $permissions->declare($this->minimalDeclaration(['capability' => 'read']));

        $this->assertTrue($permissions->isDeclared('agent_developer', 'read', 'repo:src/'));
        $this->assertFalse($permissions->isDeclared('agent_developer', 'write', 'repo:src/'));
    }

    public function testIsDeclaredIsFalseForADifferentResource(): void
    {
        $permissions = $this->permissions();
        $permissions->declare($this->minimalDeclaration(['resource' => 'repo:src/']));

        $this->assertFalse($permissions->isDeclared('agent_developer', 'write', 'repo:other/'));
    }

    // --- revoke() ---

    public function testRevokeStopsItFromBeingDeclared(): void
    {
        $permissions = $this->permissions();
        $declared = $permissions->declare($this->minimalDeclaration());

        $result = $permissions->revoke($declared['declaration_id'], 'agent reassigned');

        $this->assertSame('revoked', $result['outcome']);
        $this->assertFalse($permissions->isDeclared('agent_developer', 'write', 'repo:src/'));
    }

    public function testRevokeUnknownDeclarationIsNotFound(): void
    {
        $permissions = $this->permissions();

        $result = $permissions->revoke('ghost', 'reason');

        $this->assertSame('not_found', $result['outcome']);
    }

    public function testRevokeAlreadyRevokedIsIdempotentlyReported(): void
    {
        $permissions = $this->permissions();
        $declared = $permissions->declare($this->minimalDeclaration());
        $permissions->revoke($declared['declaration_id'], 'first reason');

        $result = $permissions->revoke($declared['declaration_id'], 'second reason');

        $this->assertSame('already_revoked', $result['outcome']);
    }

    // --- expireDue() ---

    public function testExpireDueMarksPastTimeLimitedDeclarationsExpired(): void
    {
        $permissions = $this->permissions();
        $declared = $permissions->declare($this->minimalDeclaration([
            'duration' => 'time_limited',
            'expires_at' => gmdate(DATE_ATOM, time() - 10),
        ]));

        $expired = $permissions->expireDue();

        $this->assertSame([$declared['declaration_id']], $expired);
        $this->assertFalse($permissions->isDeclared('agent_developer', 'write', 'repo:src/'));
    }

    public function testExpireDueNeverTouchesPersistentDeclarations(): void
    {
        $permissions = $this->permissions();
        $permissions->declare($this->minimalDeclaration(['duration' => 'persistent']));

        $expired = $permissions->expireDue();

        $this->assertSame([], $expired);
        $this->assertTrue($permissions->isDeclared('agent_developer', 'write', 'repo:src/'));
    }

    public function testExpireDueNeverTouchesFutureTimeLimitedDeclarations(): void
    {
        $permissions = $this->permissions();
        $permissions->declare($this->minimalDeclaration([
            'duration' => 'time_limited',
            'expires_at' => gmdate(DATE_ATOM, time() + 3600),
        ]));

        $expired = $permissions->expireDue();

        $this->assertSame([], $expired);
    }

    // --- get() / history() ---

    public function testGetUnknownDeclarationReturnsNull(): void
    {
        $permissions = $this->permissions();

        $this->assertNull($permissions->get('ghost'));
    }

    public function testHistoryReturnsEveryDeclarationForAnActor(): void
    {
        $permissions = $this->permissions();
        $permissions->declare($this->minimalDeclaration(['capability' => 'read']));
        $permissions->declare($this->minimalDeclaration(['capability' => 'write']));

        $history = $permissions->history('agent_developer');

        $this->assertCount(2, $history);
        $this->assertSame('read', $history[0]['capability']);
        $this->assertSame('write', $history[1]['capability']);
    }
}
