<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Integration;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SquirrelForge\Engine\InMemoryEngineRuntime;
use SquirrelForge\Integration\Flock\FlockPluginAdapter;
use SquirrelForge\Integration\Flock\HttpEngineClient;
use SquirrelForge\Integration\Http\EngineApiServer;
use SquirrelForge\Integration\Http\InProcessHttpTransport;
use SquirrelForge\Security\SqliteAuthorizationManager;

final class SqliteAuthorizationManagerTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        $this->databasePath = sys_get_temp_dir() . '/squirrelforge-authorization-' . bin2hex(random_bytes(8)) . '.sqlite';
    }

    protected function tearDown(): void
    {
        foreach ([$this->databasePath, $this->databasePath . '-shm', $this->databasePath . '-wal'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testGrantAuthorizesMatchingIdentityOperationAndResource(): void
    {
        $manager = new SqliteAuthorizationManager($this->databasePath);
        $manager->issueGrant(
            'permission_1',
            'identity_1',
            ['engine.submit'],
            ['project:project_1']
        );

        $decision = $manager->authorize(
            'identity_1',
            'permission_1',
            'engine.submit',
            'project:project_1',
            'correlation_1'
        );

        $this->assertTrue($decision['authorized']);
        $this->assertSame('AUTHORIZED', $decision['decision']);
        $this->assertSame($decision['authorization_ref'], $manager->decision(
            $decision['authorization_ref']
        )['authorization_ref']);
    }

    public function testOperationResourceAndIdentityAreLeastPrivilegeBoundaries(): void
    {
        $manager = new SqliteAuthorizationManager($this->databasePath);
        $manager->issueGrant(
            'permission_1',
            'identity_1',
            ['engine.inspect'],
            ['execution:exec_1']
        );

        $wrongOperation = $manager->authorize(
            'identity_1',
            'permission_1',
            'engine.cancel',
            'execution:exec_1',
            'correlation_1'
        );
        $wrongResource = $manager->authorize(
            'identity_1',
            'permission_1',
            'engine.inspect',
            'execution:exec_2',
            'correlation_2'
        );
        $wrongIdentity = $manager->authorize(
            'identity_2',
            'permission_1',
            'engine.inspect',
            'execution:exec_1',
            'correlation_3'
        );

        $this->assertFalse($wrongOperation['authorized']);
        $this->assertFalse($wrongResource['authorized']);
        $this->assertFalse($wrongIdentity['authorized']);
    }

    public function testExpiredAndRevokedGrantsAreDeniedAcrossRestart(): void
    {
        $manager = new SqliteAuthorizationManager($this->databasePath);
        $manager->issueGrant(
            'expired_permission',
            'identity_1',
            ['*'],
            ['*'],
            new DateTimeImmutable('-1 minute')
        );
        $manager->issueGrant('revoked_permission', 'identity_1', ['*']);
        $manager->revokeGrant('revoked_permission');
        unset($manager);

        $restarted = new SqliteAuthorizationManager($this->databasePath);
        $expired = $restarted->authorize(
            'identity_1',
            'expired_permission',
            'engine.submit',
            'project:project_1',
            'correlation_1'
        );
        $revoked = $restarted->authorize(
            'identity_1',
            'revoked_permission',
            'engine.submit',
            'project:project_1',
            'correlation_2'
        );

        $this->assertFalse($expired['authorized']);
        $this->assertStringContainsString('expired', $expired['rationale']);
        $this->assertFalse($revoked['authorized']);
        $this->assertStringContainsString('revoked', $revoked['rationale']);
    }

    public function testRestrictionsProduceRestrictedAuthorizationDecision(): void
    {
        $manager = new SqliteAuthorizationManager($this->databasePath);
        $manager->issueGrant(
            'permission_1',
            'identity_1',
            ['engine.result'],
            ['*'],
            null,
            ['content_scope' => 'summary_only']
        );

        $decision = $manager->authorize(
            'identity_1',
            'permission_1',
            'engine.result',
            'execution:exec_1',
            'correlation_1'
        );

        $this->assertTrue($decision['authorized']);
        $this->assertSame('AUTHORIZED_WITH_RESTRICTIONS', $decision['decision']);
        $this->assertSame(['content_scope' => 'summary_only'], $decision['restrictions']);
    }

    public function testEngineApiEnforcesOperationSpecificGrant(): void
    {
        $authorization = new SqliteAuthorizationManager($this->databasePath);
        $authorization->issueGrant(
            'permission_1',
            'identity_1',
            ['engine.submit'],
            ['project:project_1']
        );
        $baseUrl = 'https://engine.internal';
        $server = new EngineApiServer(new InMemoryEngineRuntime(), $authorization);
        $adapter = new FlockPluginAdapter(
            new HttpEngineClient($baseUrl, new InProcessHttpTransport($baseUrl, $server))
        );

        $submitted = $adapter->handle($this->flockRequest('submit'));
        $inspected = $adapter->handle($this->flockRequest('inspect', [
            'execution_ref' => $submitted['execution_ref'],
        ]));

        $this->assertSame('ACCEPTED', $submitted['status']);
        $this->assertNotNull($submitted['authorization_ref']);
        $this->assertSame('REJECTED', $inspected['status']);
        $this->assertSame('UNAUTHORIZED', $inspected['error']['type']);
        $this->assertNotNull($inspected['authorization_ref']);
    }

    private function flockRequest(string $operation, array $overrides = []): array
    {
        return array_replace_recursive([
            'adapter_version' => '1.0.0',
            'operation' => $operation,
            'request_id' => 'request_1',
            'actor' => ['identity_ref' => 'identity_1'],
            'permission_ref' => 'permission_1',
            'correlation_id' => 'correlation_1',
            'idempotency_key' => 'idempotency_1',
            'project_ref' => 'project_1',
            'payload' => ['goal' => 'Run authorized workflow.'],
        ], $overrides);
    }
}
