<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Integration;

use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;
use SquirrelForge\Integration\Http\AuthenticationApiServer;
use SquirrelForge\RuntimeConfig\SqliteSecretsManager;
use SquirrelForge\Security\ApiKeySessionIssuer;
use SquirrelForge\Security\SqliteAuthenticationManager;
use SquirrelForge\Security\SqliteIdentityManager;

final class ApiKeySessionIssuerTest extends TestCase
{
    private string $databasePath;
    private SqliteIdentityManager $identities;
    private SqliteAuthenticationManager $sessions;
    private SqliteSecretsManager $secrets;

    protected function setUp(): void
    {
        $this->databasePath = sys_get_temp_dir() . '/squirrelforge-api-key-' . bin2hex(random_bytes(8)) . '.sqlite';
        $this->identities = new SqliteIdentityManager($this->databasePath);
        $this->identities->provision('identity_1', 'SERVICE');
        $this->sessions = new SqliteAuthenticationManager($this->databasePath, $this->identities);
        $this->secrets = new SqliteSecretsManager($this->databasePath);
    }

    protected function tearDown(): void
    {
        foreach ([$this->databasePath, $this->databasePath . '-shm', $this->databasePath . '-wal'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testVerifiedApiKeyIssuesUsableSessionWithoutPlaintextPersistence(): void
    {
        $apiKey = 'api_key_' . bin2hex(random_bytes(24));
        $this->secrets->registerApiKey('identity_1', $apiKey);
        $issuer = $this->issuer();

        $issued = $issuer->issue('identity_1', $apiKey, 'correlation_1', 'source_1');
        $authenticated = $this->sessions->authenticate(
            $issued['access_token'],
            'identity_1',
            'correlation_2'
        );
        unset($this->secrets, $this->sessions);
        $databaseBytes = file_get_contents($this->databasePath);

        $this->assertTrue($issued['authenticated']);
        $this->assertTrue($authenticated['authenticated']);
        $this->assertIsString($databaseBytes);
        $this->assertStringNotContainsString($apiKey, $databaseBytes);
        $this->assertStringNotContainsString($issued['access_token'], $databaseBytes);
    }

    public function testRepeatedFailuresLockIdentityAndSourceEvenWhenCredentialBecomesValid(): void
    {
        $apiKey = 'api_key_' . bin2hex(random_bytes(24));
        $this->secrets->registerApiKey('identity_1', $apiKey);
        $issuer = $this->issuer(maximumFailures: 3);

        $issuer->issue('identity_1', 'wrong_key_123456789012345678901234', 'correlation_1', 'source_1');
        $issuer->issue('identity_1', 'wrong_key_223456789012345678901234', 'correlation_2', 'source_1');
        $locked = $issuer->issue(
            'identity_1',
            'wrong_key_323456789012345678901234',
            'correlation_3',
            'source_1'
        );
        $validButLocked = $issuer->issue('identity_1', $apiKey, 'correlation_4', 'source_1');
        $otherSource = $issuer->issue('identity_1', $apiKey, 'correlation_5', 'source_2');

        $this->assertSame('AUTHENTICATION_LOCKED', $locked['error']['type']);
        $this->assertSame('AUTHENTICATION_LOCKED', $validButLocked['error']['type']);
        $this->assertTrue($otherSource['authenticated']);
    }

    public function testRevokedAndExpiredKeysCannotIssueSessions(): void
    {
        $revokedKey = 'revoked_' . bin2hex(random_bytes(24));
        $expiredKey = 'expired_' . bin2hex(random_bytes(24));
        $secretRef = $this->secrets->registerApiKey('identity_1', $revokedKey);
        $this->secrets->revoke($secretRef);
        $this->secrets->registerApiKey(
            'identity_1',
            $expiredKey,
            new DateTimeImmutable('-1 minute')
        );
        $issuer = $this->issuer();

        $revoked = $issuer->issue('identity_1', $revokedKey, 'correlation_1', 'source_1');
        $expired = $issuer->issue('identity_1', $expiredKey, 'correlation_2', 'source_2');

        $this->assertFalse($revoked['authenticated']);
        $this->assertFalse($expired['authenticated']);
    }

    public function testSessionEndpointReturnsTokenOnlyAfterVerification(): void
    {
        $apiKey = 'api_key_' . bin2hex(random_bytes(24));
        $this->secrets->registerApiKey('identity_1', $apiKey);
        $server = new AuthenticationApiServer($this->issuer());
        $success = $server->handle(
            'POST',
            '/v1/authentication/sessions',
            ['X-Correlation-ID' => 'correlation_1', 'X-Source-Ref' => 'source_1'],
            json_encode(['identity_ref' => 'identity_1', 'api_key' => $apiKey], JSON_THROW_ON_ERROR)
        );
        $failure = $server->handle(
            'POST',
            '/v1/authentication/sessions',
            ['X-Correlation-ID' => 'correlation_2', 'X-Source-Ref' => 'source_1'],
            json_encode([
                'identity_ref' => 'identity_1',
                'api_key' => 'wrong_key_123456789012345678901234',
            ], JSON_THROW_ON_ERROR)
        );
        $successBody = json_decode($success->body, true, 512, JSON_THROW_ON_ERROR);
        $failureBody = json_decode($failure->body, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(201, $success->status);
        $this->assertArrayHasKey('access_token', $successBody);
        $this->assertSame(401, $failure->status);
        $this->assertArrayNotHasKey('access_token', $failureBody);
        $this->assertStringNotContainsString($apiKey, $success->body);
    }

    private function issuer(int $maximumFailures = 5): ApiKeySessionIssuer
    {
        return new ApiKeySessionIssuer(
            $this->databasePath,
            $this->identities,
            $this->secrets,
            $this->sessions,
            maximumFailures: $maximumFailures,
            failureWindowSeconds: 300,
            lockoutSeconds: 900
        );
    }
}
