<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Integration;

use DateInterval;
use PDO;
use PHPUnit\Framework\TestCase;
use SquirrelForge\Engine\InMemoryEngineRuntime;
use SquirrelForge\Integration\Flock\FlockPluginAdapter;
use SquirrelForge\Integration\Flock\HttpEngineClient;
use SquirrelForge\Integration\Http\EngineApiServer;
use SquirrelForge\Integration\Http\InProcessHttpTransport;
use SquirrelForge\Security\SqliteAuthenticationManager;
use SquirrelForge\Security\SqliteAuthorizationManager;
use SquirrelForge\Security\SqliteIdentityManager;

final class SqliteAuthenticationManagerTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        $this->databasePath = sys_get_temp_dir() . '/squirrelforge-authentication-' . bin2hex(random_bytes(8)) . '.sqlite';
    }

    protected function tearDown(): void
    {
        foreach ([$this->databasePath, $this->databasePath . '-shm', $this->databasePath . '-wal'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testActiveSessionAuthenticatesAcrossRestartWithoutStoringPlaintextToken(): void
    {
        $identities = new SqliteIdentityManager($this->databasePath);
        $identities->provision('identity_1', 'SERVICE');
        $manager = new SqliteAuthenticationManager($this->databasePath, $identities);
        $session = $manager->issueSession('identity_1', 'credential_verification_1');
        unset($manager);

        $restarted = new SqliteAuthenticationManager($this->databasePath, $identities);
        $decision = $restarted->authenticate($session['access_token'], 'identity_1', 'correlation_1');
        $database = new PDO('sqlite:' . $this->databasePath);
        $plaintextCount = $database->query(
            "SELECT COUNT(*) FROM authentication_sessions
             WHERE token_hash = " . $database->quote($session['access_token'])
        )->fetchColumn();

        $this->assertTrue($decision['authenticated']);
        $this->assertSame('identity_1', $decision['identity_ref']);
        $this->assertSame(0, (int) $plaintextCount);
    }

    public function testMissingExpiredAndRevokedSessionsAreDenied(): void
    {
        $identities = new SqliteIdentityManager($this->databasePath);
        $identities->provision('identity_1', 'SERVICE');
        $manager = new SqliteAuthenticationManager($this->databasePath, $identities);
        $expired = $manager->issueSession(
            'identity_1',
            'credential_verification_1',
            new DateInterval('PT0S')
        );
        $revoked = $manager->issueSession('identity_1', 'credential_verification_2');
        $manager->revokeSession($revoked['session_ref']);

        $missingDecision = $manager->authenticate(null, 'identity_1', 'correlation_1');
        $expiredDecision = $manager->authenticate(
            $expired['access_token'],
            'identity_1',
            'correlation_2'
        );
        $revokedDecision = $manager->authenticate(
            $revoked['access_token'],
            'identity_1',
            'correlation_3'
        );

        $this->assertFalse($missingDecision['authenticated']);
        $this->assertFalse($expiredDecision['authenticated']);
        $this->assertStringContainsString('expired', $expiredDecision['rationale']);
        $this->assertFalse($revokedDecision['authenticated']);
        $this->assertStringContainsString('revoked', $revokedDecision['rationale']);
    }

    public function testSuspendedIdentityAndImpersonationAreDenied(): void
    {
        $identities = new SqliteIdentityManager($this->databasePath);
        $identities->provision('identity_1', 'SERVICE');
        $manager = new SqliteAuthenticationManager($this->databasePath, $identities);
        $session = $manager->issueSession('identity_1', 'credential_verification_1');

        $impersonation = $manager->authenticate(
            $session['access_token'],
            'identity_2',
            'correlation_1'
        );
        $identities->setStatus('identity_1', 'SUSPENDED');
        $suspended = $manager->authenticate(
            $session['access_token'],
            'identity_1',
            'correlation_2'
        );

        $this->assertFalse($impersonation['authenticated']);
        $this->assertStringContainsString('does not match', $impersonation['rationale']);
        $this->assertFalse($suspended['authenticated']);
        $this->assertStringContainsString('not active', $suspended['rationale']);
    }

    public function testEngineApiRequiresSessionBeforeAuthorization(): void
    {
        $identities = new SqliteIdentityManager($this->databasePath);
        $identities->provision('identity_1', 'SERVICE');
        $authentication = new SqliteAuthenticationManager($this->databasePath, $identities);
        $session = $authentication->issueSession('identity_1', 'credential_verification_1');
        $authorization = new SqliteAuthorizationManager($this->databasePath);
        $authorization->issueGrant('permission_1', 'identity_1', ['engine.submit'], ['project:project_1']);
        $baseUrl = 'https://engine.internal';
        $server = new EngineApiServer(
            new InMemoryEngineRuntime(),
            $authorization,
            $authentication
        );
        $transport = new InProcessHttpTransport($baseUrl, $server);
        $authenticatedAdapter = new FlockPluginAdapter(
            new HttpEngineClient($baseUrl, $transport, 30.0, $session['access_token'])
        );
        $unauthenticatedAdapter = new FlockPluginAdapter(
            new HttpEngineClient($baseUrl, $transport)
        );

        $accepted = $authenticatedAdapter->handle($this->flockRequest());
        $denied = $unauthenticatedAdapter->handle($this->flockRequest([
            'idempotency_key' => 'idempotency_2',
        ]));

        $this->assertSame('ACCEPTED', $accepted['status']);
        $this->assertNotNull($accepted['authentication_ref']);
        $this->assertNotNull($accepted['authorization_ref']);
        $this->assertSame('REJECTED', $denied['status']);
        $this->assertSame('UNAUTHENTICATED', $denied['error']['type']);
        $this->assertNotNull($denied['authentication_ref']);
        $this->assertNull($denied['authorization_ref']);
    }

    private function flockRequest(array $overrides = []): array
    {
        return array_replace_recursive([
            'adapter_version' => '1.0.0',
            'operation' => 'submit',
            'request_id' => 'request_1',
            'actor' => ['identity_ref' => 'identity_1'],
            'permission_ref' => 'permission_1',
            'correlation_id' => 'correlation_1',
            'idempotency_key' => 'idempotency_1',
            'project_ref' => 'project_1',
            'payload' => ['goal' => 'Run authenticated workflow.'],
        ], $overrides);
    }
}
