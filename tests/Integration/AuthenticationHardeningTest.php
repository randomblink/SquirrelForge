<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Integration;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Integration\Http\AuthenticationApiServer;
use SquirrelForge\RuntimeConfig\RuntimeEnvironmentPolicy;
use SquirrelForge\RuntimeConfig\SqliteSecretsManager;
use SquirrelForge\Security\ApiKeySessionIssuer;
use SquirrelForge\Security\AuthenticationThrottlePolicy;
use SquirrelForge\Security\SqliteAuthenticationManager;
use SquirrelForge\Security\SqliteIdentityManager;
use SquirrelForge\Security\SqliteSecurityEventSink;
use SquirrelForge\Security\StaticMfaVerifier;

final class AuthenticationHardeningTest extends TestCase
{
    private string $databasePath;
    private SqliteIdentityManager $identities;
    private SqliteSecretsManager $secrets;
    private SqliteSecurityEventSink $events;
    private SqliteAuthenticationManager $sessions;

    protected function setUp(): void
    {
        $this->databasePath = sys_get_temp_dir() . '/squirrelforge-auth-hardening-'
            . bin2hex(random_bytes(8)) . '.sqlite';
        $this->identities = new SqliteIdentityManager($this->databasePath);
        $this->secrets = new SqliteSecretsManager($this->databasePath);
        $this->events = new SqliteSecurityEventSink($this->databasePath);
        $this->sessions = new SqliteAuthenticationManager(
            $this->databasePath,
            $this->identities,
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

    public function testApiKeyRotationInvalidatesOldKeyAndActivatesReplacement(): void
    {
        $this->identities->provision('service_1', 'SERVICE');
        $oldKey = 'old_key_' . bin2hex(random_bytes(24));
        $newKey = 'new_key_' . bin2hex(random_bytes(24));
        $oldRef = $this->secrets->registerApiKey('service_1', $oldKey);
        $newRef = $this->secrets->rotateApiKey($oldRef, $newKey);

        $this->assertNotSame($oldRef, $newRef);
        $this->assertFalse($this->secrets->verifyApiKey('service_1', $oldKey)['verified']);
        $this->assertTrue($this->secrets->verifyApiKey('service_1', $newKey)['verified']);
    }

    public function testSessionRefreshRotatesTokenAndPersistsSecurityEvent(): void
    {
        $this->identities->provision('service_1', 'SERVICE');
        $session = $this->sessions->issueSession('service_1', 'credential_verification_1');
        $server = new AuthenticationApiServer($this->issuer(), $this->sessions);
        $response = $server->handle(
            'POST',
            '/v1/authentication/sessions/refresh',
            [
                'Authorization' => 'Bearer ' . $session['access_token'],
                'X-Correlation-ID' => 'correlation_refresh',
            ],
            null
        );
        $refreshed = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(201, $response->status);
        $this->assertNotSame($session['access_token'], $refreshed['access_token']);
        $this->assertFalse(
            $this->sessions->authenticate($session['access_token'], 'service_1', 'correlation_old')['authenticated']
        );
        $this->assertTrue(
            $this->sessions->authenticate(
                $refreshed['access_token'],
                'service_1',
                'correlation_new'
            )['authenticated']
        );
        $this->assertContains(
            'SESSION_REFRESH',
            array_column($this->events->events('service_1'), 'event_type')
        );
    }

    public function testHumanIdentityRequiresValidMfaWhileServiceIdentityDoesNot(): void
    {
        $this->identities->provision('user_1', 'USER');
        $this->identities->provision('service_1', 'SERVICE');
        $userKey = 'user_key_' . bin2hex(random_bytes(24));
        $serviceKey = 'service_key_' . bin2hex(random_bytes(24));
        $this->secrets->registerApiKey('user_1', $userKey);
        $this->secrets->registerApiKey('service_1', $serviceKey);
        $issuer = $this->issuer(new StaticMfaVerifier('proof_123'));

        $missing = $issuer->issue('user_1', $userKey, 'correlation_1', 'source_1');
        $valid = $issuer->issue('user_1', $userKey, 'correlation_2', 'source_2', 'proof_123');
        $service = $issuer->issue('service_1', $serviceKey, 'correlation_3', 'source_3');

        $this->assertSame('MFA_REQUIRED', $missing['error']['type']);
        $this->assertTrue($valid['authenticated']);
        $this->assertTrue($service['authenticated']);
        $this->assertContains(
            'MFA_VERIFICATION',
            array_column($this->events->events('user_1'), 'event_type')
        );
    }

    public function testConfiguredThrottleLocksAtSelectedFailureCountAndRecordsEvents(): void
    {
        $this->identities->provision('service_1', 'SERVICE');
        $issuer = $this->issuer(policy: new AuthenticationThrottlePolicy(2, 60, 120));

        $first = $issuer->issue('service_1', str_repeat('x', 32), 'correlation_1', 'source_1');
        $second = $issuer->issue('service_1', str_repeat('y', 32), 'correlation_2', 'source_1');

        $this->assertSame('INVALID_CREDENTIALS', $first['error']['type']);
        $this->assertSame('AUTHENTICATION_LOCKED', $second['error']['type']);
        $this->assertCount(2, $this->events->events('service_1'));
    }

    public function testBootstrapProvisioningIsFailClosedOutsideLocalAndTest(): void
    {
        $this->assertFalse((new RuntimeEnvironmentPolicy())->allowsBootstrapProvisioning());
        $this->assertFalse((new RuntimeEnvironmentPolicy('production'))->allowsBootstrapProvisioning());
        $this->assertTrue((new RuntimeEnvironmentPolicy('local'))->allowsBootstrapProvisioning());
        $this->assertTrue((new RuntimeEnvironmentPolicy('test'))->allowsBootstrapProvisioning());
    }

    private function issuer(
        ?StaticMfaVerifier $mfa = null,
        ?AuthenticationThrottlePolicy $policy = null
    ): ApiKeySessionIssuer {
        $policy ??= new AuthenticationThrottlePolicy();

        return new ApiKeySessionIssuer(
            $this->databasePath,
            $this->identities,
            $this->secrets,
            $this->sessions,
            $policy->maximumFailures,
            $policy->failureWindowSeconds,
            $policy->lockoutSeconds,
            $mfa ?? new StaticMfaVerifier('unused'),
            $this->events
        );
    }
}
