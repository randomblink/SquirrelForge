<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Integration;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Engine\SqliteEngineRuntime;
use SquirrelForge\Integration\Http\DashboardApiServer;
use SquirrelForge\RuntimeConfig\SqliteProviderTelemetry;
use SquirrelForge\Security\SqliteAuthenticationManager;
use SquirrelForge\Security\SqliteAuthorizationManager;
use SquirrelForge\Security\SqliteIdentityManager;
use SquirrelForge\Security\SqliteSecurityEventSink;

final class DashboardApiServerTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        $this->databasePath = sys_get_temp_dir() . '/squirrelforge-dashboard-' . bin2hex(random_bytes(8)) . '.sqlite';
    }

    protected function tearDown(): void
    {
        foreach ([$this->databasePath, $this->databasePath . '-shm', $this->databasePath . '-wal'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testMissingAuthHeadersAreRejected(): void
    {
        $server = $this->server();

        $response = $server->handle('GET', '/v1/admin/dashboard', [], null);

        $this->assertSame(401, $response->status);
    }

    public function testWrongPermissionIsForbidden(): void
    {
        $identities = new SqliteIdentityManager($this->databasePath);
        $identities->provision('identity_1', 'SERVICE');
        $authentication = new SqliteAuthenticationManager($this->databasePath, $identities);
        $session = $authentication->issueSession('identity_1', 'credential_verification_1');
        $authorization = new SqliteAuthorizationManager($this->databasePath);
        $authorization->issueGrant('permission_1', 'identity_1', ['engine.submit'], ['dashboard:platform']);

        $server = $this->server($authentication, $authorization);
        $response = $server->handle('GET', '/v1/admin/dashboard', $this->headers($session['access_token']), null);

        $this->assertSame(403, $response->status);
    }

    public function testAuthorizedRequestReturnsAggregateDashboardWithoutSensitiveFields(): void
    {
        $identities = new SqliteIdentityManager($this->databasePath);
        $identities->provision('identity_1', 'SERVICE');
        $authentication = new SqliteAuthenticationManager($this->databasePath, $identities);
        $session = $authentication->issueSession('identity_1', 'credential_verification_1');
        $authorization = new SqliteAuthorizationManager($this->databasePath);
        $authorization->issueGrant(
            'permission_1',
            'identity_1',
            ['observability.dashboard.read'],
            ['dashboard:platform']
        );

        $securityEvents = new SqliteSecurityEventSink($this->databasePath);
        $securityEvents->record('LOGIN', 'SUCCESS', 'identity_1', 'correlation_seed', ['secret' => 'do-not-leak']);

        $engineRuntime = new SqliteEngineRuntime($this->databasePath);
        $engineRuntime->submit(
            ['request_id' => 'request_1', 'goal' => 'Run persistent workflow.'],
            'project_1',
            'identity_1',
            'permission:allowed',
            'idempotency_1',
            'correlation_seed'
        );

        $server = $this->server($authentication, $authorization, $securityEvents, $engineRuntime);
        $response = $server->handle('GET', '/v1/admin/dashboard', $this->headers($session['access_token']), null);
        $decoded = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(200, $response->status);
        $this->assertArrayHasKey('source', $decoded['provider']);
        $this->assertArrayHasKey('as_of', $decoded['provider']);
        $this->assertArrayHasKey('as_of', $decoded['security_events']);
        $this->assertCount(1, $decoded['security_events']['recent']);
        $this->assertSame('LOGIN', $decoded['security_events']['recent'][0]['event_type']);
        $this->assertCount(1, $decoded['engine_executions']['recent']);
        $this->assertSame('project_1', $decoded['engine_executions']['recent'][0]['project_ref']);
        $this->assertStringNotContainsString('metadata_json', $response->body);
        $this->assertStringNotContainsString('do-not-leak', $response->body);
        $this->assertStringNotContainsString('request_json', $response->body);
        $this->assertStringNotContainsString('result_json', $response->body);
        $this->assertStringNotContainsString('validation_json', $response->body);
    }

    public function testSecurityEventSinkRecentExcludesMetadataAndRespectsLimit(): void
    {
        $sink = new SqliteSecurityEventSink($this->databasePath);
        $sink->record('LOGIN', 'SUCCESS', 'identity_1', 'correlation_1', ['secret' => 'do-not-leak']);
        $sink->record('LOGOUT', 'SUCCESS', 'identity_1', 'correlation_2', ['secret' => 'do-not-leak']);

        $recent = $sink->recent(1);

        $this->assertCount(1, $recent);
        $this->assertSame(
            ['event_ref', 'event_type', 'outcome', 'identity_ref', 'correlation_id', 'created_at'],
            array_keys($recent[0])
        );
        $this->assertCount(2, $sink->recent(10));
    }

    public function testUnknownRouteReturnsNotFound(): void
    {
        $server = $this->server();

        $response = $server->handle('GET', '/v1/admin/unknown', [], null);

        $this->assertSame(404, $response->status);
    }

    public function testWrongMethodIsNotAllowed(): void
    {
        $server = $this->server();

        $response = $server->handle('POST', '/v1/admin/dashboard', [], null);

        $this->assertSame(405, $response->status);
    }

    /**
     * @return array<string, string>
     */
    private function headers(string $accessToken): array
    {
        return [
            'x-squirrelforge-identity-ref' => 'identity_1',
            'x-squirrelforge-permission-ref' => 'permission_1',
            'x-correlation-id' => 'correlation_1',
            'authorization' => 'Bearer ' . $accessToken,
        ];
    }

    private function server(
        ?SqliteAuthenticationManager $authentication = null,
        ?SqliteAuthorizationManager $authorization = null,
        ?SqliteSecurityEventSink $securityEvents = null,
        ?SqliteEngineRuntime $engineRuntime = null
    ): DashboardApiServer {
        return new DashboardApiServer(
            null,
            new SqliteProviderTelemetry($this->databasePath),
            $securityEvents ?? new SqliteSecurityEventSink($this->databasePath),
            $engineRuntime ?? new SqliteEngineRuntime($this->databasePath),
            $authorization ?? new SqliteAuthorizationManager($this->databasePath),
            $authentication ?? new SqliteAuthenticationManager(
                $this->databasePath,
                new SqliteIdentityManager($this->databasePath)
            )
        );
    }
}
