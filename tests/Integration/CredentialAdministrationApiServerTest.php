<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Integration;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Integration\Http\CredentialAdministrationApiServer;
use SquirrelForge\RuntimeConfig\SqliteSecretsManager;
use SquirrelForge\Security\SqliteAuthenticationManager;
use SquirrelForge\Security\SqliteAuthorizationManager;
use SquirrelForge\Security\SqliteIdentityManager;
use SquirrelForge\Security\SqliteSecurityEventSink;

final class CredentialAdministrationApiServerTest extends TestCase
{
    private string $databasePath;
    private SqliteSecretsManager $secrets;
    private SqliteAuthenticationManager $sessions;
    private SqliteAuthorizationManager $authorization;
    private SqliteSecurityEventSink $events;
    private CredentialAdministrationApiServer $server;
    private array $adminSession;

    protected function setUp(): void
    {
        $this->databasePath = sys_get_temp_dir() . '/squirrelforge-credential-admin-'
            . bin2hex(random_bytes(8)) . '.sqlite';
        $identities = new SqliteIdentityManager($this->databasePath);
        $identities->provision('admin_1', 'SERVICE');
        $identities->provision('service_1', 'SERVICE');
        $this->events = new SqliteSecurityEventSink($this->databasePath);
        $this->sessions = new SqliteAuthenticationManager($this->databasePath, $identities, $this->events);
        $this->authorization = new SqliteAuthorizationManager($this->databasePath);
        $this->secrets = new SqliteSecretsManager($this->databasePath);
        $this->adminSession = $this->sessions->issueSession('admin_1', 'credential_verification_admin');
        $this->server = new CredentialAdministrationApiServer(
            $this->secrets,
            $this->sessions,
            $this->sessions,
            $this->authorization,
            $this->events
        );
    }

    protected function tearDown(): void
    {
        foreach ([$this->databasePath, $this->databasePath . '-shm', $this->databasePath . '-wal'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testAuthorizedRotationInvalidatesOldKeyAndReturnsEvidenceReferences(): void
    {
        $oldKey = 'old_key_' . bin2hex(random_bytes(24));
        $newKey = 'new_key_' . bin2hex(random_bytes(24));
        $secretRef = $this->secrets->registerApiKey('service_1', $oldKey);
        $this->authorization->issueGrant(
            'permission_rotate',
            'admin_1',
            ['security.credentials.rotate'],
            ['secret:' . $secretRef]
        );

        $response = $this->server->handle(
            'POST',
            '/v1/security/api-keys/' . $secretRef . '/rotation',
            $this->headers('permission_rotate'),
            json_encode(['new_api_key' => $newKey], JSON_THROW_ON_ERROR)
        );
        $body = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(201, $response->status);
        $this->assertTrue($body['rotated']);
        $this->assertArrayHasKey('security_event_ref', $body);
        $this->assertArrayHasKey('x-squirrelforge-authentication-ref', $response->headers);
        $this->assertArrayHasKey('x-squirrelforge-authorization-ref', $response->headers);
        $this->assertFalse($this->secrets->verifyApiKey('service_1', $oldKey)['verified']);
        $this->assertTrue($this->secrets->verifyApiKey('service_1', $newKey)['verified']);
        $this->assertContains(
            'API_KEY_ROTATED',
            array_column($this->events->events('admin_1'), 'event_type')
        );
    }

    public function testAuthorizationDenialDoesNotRotateCredential(): void
    {
        $oldKey = 'old_key_' . bin2hex(random_bytes(24));
        $newKey = 'new_key_' . bin2hex(random_bytes(24));
        $secretRef = $this->secrets->registerApiKey('service_1', $oldKey);
        $this->authorization->issueGrant(
            'permission_denied',
            'admin_1',
            ['engine.inspect']
        );

        $response = $this->server->handle(
            'POST',
            '/v1/security/api-keys/' . $secretRef . '/rotation',
            $this->headers('permission_denied'),
            json_encode(['new_api_key' => $newKey], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(403, $response->status);
        $this->assertTrue($this->secrets->verifyApiKey('service_1', $oldKey)['verified']);
        $this->assertFalse($this->secrets->verifyApiKey('service_1', $newKey)['verified']);
    }

    public function testAuthorizedSessionRevocationImmediatelyInvalidatesSession(): void
    {
        $target = $this->sessions->issueSession('service_1', 'credential_verification_service');
        $this->authorization->issueGrant(
            'permission_revoke_session',
            'admin_1',
            ['security.sessions.revoke'],
            ['session:' . $target['session_ref']]
        );

        $response = $this->server->handle(
            'POST',
            '/v1/security/sessions/' . $target['session_ref'] . '/revocation',
            $this->headers('permission_revoke_session'),
            null
        );
        $authentication = $this->sessions->authenticate(
            $target['access_token'],
            'service_1',
            'correlation_after_revocation'
        );

        $this->assertSame(200, $response->status);
        $this->assertFalse($authentication['authenticated']);
        $this->assertStringContainsString('revoked', $authentication['rationale']);
    }

    private function headers(string $permissionRef): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->adminSession['access_token'],
            'X-SquirrelForge-Identity-Ref' => 'admin_1',
            'X-SquirrelForge-Permission-Ref' => $permissionRef,
            'X-Correlation-ID' => 'correlation_admin',
        ];
    }
}
