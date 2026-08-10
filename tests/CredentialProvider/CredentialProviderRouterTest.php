<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\CredentialProvider;

use PHPUnit\Framework\TestCase;
use SquirrelForge\CredentialProvider\CredentialProviderRouter;
use SquirrelForge\CredentialProvider\MfaSecretStore;
use SquirrelForge\CredentialProvider\TotpVerifier;
use SquirrelForge\RuntimeConfig\SqliteSecretsManager;
use SquirrelForge\Security\SqliteEncryptionManager;
use SquirrelForge\Security\SqliteSecurityEventSink;

/**
 * Exercises `CredentialProviderRouter` against the exact contract
 * documented in `deploy/CREDENTIAL-PROVIDER-CONTRACT.md` -- the same
 * document `FakeCredentialProviderServer` implements as a test double
 * for the client side. This is the real server-side implementation,
 * wired to real `SqliteSecretsManager`/`MfaSecretStore`/
 * `SqliteSecurityEventSink` instances rather than in-memory fakes.
 */
final class CredentialProviderRouterTest extends TestCase
{
    private const TOKEN = 'a-provider-token-at-least-32-characters-long';

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
        $path = sys_get_temp_dir() . "/squirrelforge-credential-provider-router-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function router(): CredentialProviderRouter
    {
        $databasePath = $this->tempPath('main');
        $encryption = new SqliteEncryptionManager($this->tempPath('encryption'));

        return new CredentialProviderRouter(
            new SqliteSecretsManager($databasePath),
            new MfaSecretStore($this->tempPath('mfa'), $encryption, random_bytes(32)),
            new SqliteSecurityEventSink($databasePath),
            self::TOKEN
        );
    }

    /**
     * @param array<string, string> $extraHeaders
     * @return array<string, string>
     */
    private function authorizedHeaders(array $extraHeaders = []): array
    {
        return ['Authorization' => 'Bearer ' . self::TOKEN] + $extraHeaders;
    }

    // --- authorization ---

    public function testMissingAuthorizationHeaderIsRejected(): void
    {
        [$status, $body] = $this->router()->handle('GET', '/v1/provider/health', [], null);

        $this->assertSame(401, $status);
        $this->assertSame('unauthorized', $body['error']);
    }

    public function testWrongBearerTokenIsRejected(): void
    {
        [$status, $body] = $this->router()->handle(
            'GET',
            '/v1/provider/health',
            ['Authorization' => 'Bearer not-the-right-token'],
            null
        );

        $this->assertSame(401, $status);
        $this->assertSame('unauthorized', $body['error']);
    }

    public function testAuthorizationHeaderIsCheckedCaseInsensitively(): void
    {
        [$status] = $this->router()->handle(
            'GET',
            '/v1/provider/health',
            ['authorization' => 'Bearer ' . self::TOKEN],
            null
        );

        $this->assertSame(200, $status);
    }

    public function testUnauthorizedRequestNeverReachesRouteMatching(): void
    {
        // An unknown path with no auth still comes back 401, not 404 --
        // proving the check runs before any route/body parsing.
        [$status] = $this->router()->handle('POST', '/v1/nonsense', [], 'not json');

        $this->assertSame(401, $status);
    }

    // --- health ---

    public function testHealthReturnsHealthyTrue(): void
    {
        [$status, $body] = $this->router()->handle('GET', '/v1/provider/health', $this->authorizedHeaders(), null);

        $this->assertSame(200, $status);
        $this->assertTrue($body['healthy']);
    }

    // --- method / route enforcement ---

    public function testNonPostToAnOperationalRouteIsMethodNotAllowed(): void
    {
        [$status, $body] = $this->router()->handle('GET', '/v1/provider/secrets/register', $this->authorizedHeaders(), null);

        $this->assertSame(405, $status);
        $this->assertSame('method_not_allowed', $body['error']);
    }

    public function testUnknownRouteReturns404(): void
    {
        [$status, $body] = $this->router()->handle('POST', '/v1/provider/unknown', $this->authorizedHeaders(), '{}');

        $this->assertSame(404, $status);
        $this->assertSame('unknown_route', $body['error']);
    }

    // --- secrets/register ---

    public function testRegisterRequiresIdentityRefAndApiKey(): void
    {
        [$status, $body] = $this->router()->handle('POST', '/v1/provider/secrets/register', $this->authorizedHeaders(), '{}');

        $this->assertSame(422, $status);
        $this->assertSame('invalid_request', $body['error']);
    }

    public function testRegisterReturnsANonEmptySecretRefAndNeverEchoesTheKey(): void
    {
        $router = $this->router();
        $payload = json_encode(['identity_ref' => 'agent_1', 'api_key' => str_repeat('k', 32)]);

        [$status, $body] = $router->handle('POST', '/v1/provider/secrets/register', $this->authorizedHeaders(), $payload);

        $this->assertSame(200, $status);
        $this->assertIsString($body['secret_ref']);
        $this->assertNotSame('', $body['secret_ref']);
        $this->assertStringNotContainsString(str_repeat('k', 32), json_encode($body));
    }

    public function testRegisterFailsForAnApiKeyThatIsTooShort(): void
    {
        $payload = json_encode(['identity_ref' => 'agent_1', 'api_key' => 'too-short']);

        [$status, $body] = $this->router()->handle('POST', '/v1/provider/secrets/register', $this->authorizedHeaders(), $payload);

        $this->assertSame(422, $status);
        $this->assertSame('registration_failed', $body['error']);
    }

    public function testRegisterAcceptsAnExpiresAtTimestamp(): void
    {
        $payload = json_encode([
            'identity_ref' => 'agent_1',
            'api_key' => str_repeat('k', 32),
            'expires_at' => '2030-01-01T00:00:00+00:00',
        ]);

        [$status, $body] = $this->router()->handle('POST', '/v1/provider/secrets/register', $this->authorizedHeaders(), $payload);

        $this->assertSame(200, $status);
        $this->assertIsString($body['secret_ref']);
    }

    public function testRegisterFailsForAMalformedExpiresAt(): void
    {
        $payload = json_encode([
            'identity_ref' => 'agent_1',
            'api_key' => str_repeat('k', 32),
            'expires_at' => 'not-a-real-timestamp',
        ]);

        [$status, $body] = $this->router()->handle('POST', '/v1/provider/secrets/register', $this->authorizedHeaders(), $payload);

        $this->assertSame(422, $status);
        $this->assertSame('registration_failed', $body['error']);
    }

    // --- secrets/verify ---

    public function testVerifyRequiresIdentityRefAndApiKey(): void
    {
        [$status, $body] = $this->router()->handle('POST', '/v1/provider/secrets/verify', $this->authorizedHeaders(), '{}');

        $this->assertSame(422, $status);
        $this->assertSame('invalid_request', $body['error']);
    }

    public function testVerifyReturnsVerifiedTrueForARegisteredKey(): void
    {
        $router = $this->router();
        $apiKey = str_repeat('k', 32);
        $router->handle(
            'POST',
            '/v1/provider/secrets/register',
            $this->authorizedHeaders(),
            json_encode(['identity_ref' => 'agent_1', 'api_key' => $apiKey])
        );

        [$status, $body] = $router->handle(
            'POST',
            '/v1/provider/secrets/verify',
            $this->authorizedHeaders(),
            json_encode(['identity_ref' => 'agent_1', 'api_key' => $apiKey])
        );

        $this->assertSame(200, $status);
        $this->assertTrue($body['verified']);
        $this->assertIsString($body['secret_ref']);
        $this->assertIsString($body['verification_ref']);
    }

    public function testVerifyReturnsVerifiedFalseForAWrongKey(): void
    {
        $router = $this->router();
        $router->handle(
            'POST',
            '/v1/provider/secrets/register',
            $this->authorizedHeaders(),
            json_encode(['identity_ref' => 'agent_1', 'api_key' => str_repeat('k', 32)])
        );

        [$status, $body] = $router->handle(
            'POST',
            '/v1/provider/secrets/verify',
            $this->authorizedHeaders(),
            json_encode(['identity_ref' => 'agent_1', 'api_key' => str_repeat('x', 32)])
        );

        $this->assertSame(200, $status);
        $this->assertFalse($body['verified']);
        $this->assertNull($body['verification_ref']);
    }

    // --- secrets/rotate ---

    public function testRotateRequiresSecretRefAndNewApiKey(): void
    {
        [$status, $body] = $this->router()->handle('POST', '/v1/provider/secrets/rotate', $this->authorizedHeaders(), '{}');

        $this->assertSame(422, $status);
        $this->assertSame('invalid_request', $body['error']);
    }

    public function testRotateReturnsANewSecretRefAndInvalidatesTheOldKey(): void
    {
        $router = $this->router();
        $oldKey = str_repeat('k', 32);
        $newKey = str_repeat('n', 32);
        [, $registered] = $router->handle(
            'POST',
            '/v1/provider/secrets/register',
            $this->authorizedHeaders(),
            json_encode(['identity_ref' => 'agent_1', 'api_key' => $oldKey])
        );

        [$rotateStatus, $rotated] = $router->handle(
            'POST',
            '/v1/provider/secrets/rotate',
            $this->authorizedHeaders(),
            json_encode(['secret_ref' => $registered['secret_ref'], 'new_api_key' => $newKey])
        );

        $this->assertSame(200, $rotateStatus);
        $this->assertIsString($rotated['secret_ref']);
        $this->assertNotSame($registered['secret_ref'], $rotated['secret_ref']);

        [, $oldVerify] = $router->handle(
            'POST',
            '/v1/provider/secrets/verify',
            $this->authorizedHeaders(),
            json_encode(['identity_ref' => 'agent_1', 'api_key' => $oldKey])
        );
        [, $newVerify] = $router->handle(
            'POST',
            '/v1/provider/secrets/verify',
            $this->authorizedHeaders(),
            json_encode(['identity_ref' => 'agent_1', 'api_key' => $newKey])
        );

        $this->assertFalse($oldVerify['verified']);
        $this->assertTrue($newVerify['verified']);
    }

    public function testRotateFailsForAnUnknownSecretRef(): void
    {
        $payload = json_encode(['secret_ref' => 'secret_does_not_exist', 'new_api_key' => str_repeat('n', 32)]);

        [$status, $body] = $this->router()->handle('POST', '/v1/provider/secrets/rotate', $this->authorizedHeaders(), $payload);

        $this->assertSame(422, $status);
        $this->assertSame('rotation_failed', $body['error']);
    }

    // --- secrets/revoke ---

    public function testRevokeRequiresSecretRef(): void
    {
        [$status, $body] = $this->router()->handle('POST', '/v1/provider/secrets/revoke', $this->authorizedHeaders(), '{}');

        $this->assertSame(422, $status);
        $this->assertSame('invalid_request', $body['error']);
    }

    public function testRevokeMarksTheSecretUnverifiable(): void
    {
        $router = $this->router();
        $apiKey = str_repeat('k', 32);
        [, $registered] = $router->handle(
            'POST',
            '/v1/provider/secrets/register',
            $this->authorizedHeaders(),
            json_encode(['identity_ref' => 'agent_1', 'api_key' => $apiKey])
        );

        [$revokeStatus, $revokeBody] = $router->handle(
            'POST',
            '/v1/provider/secrets/revoke',
            $this->authorizedHeaders(),
            json_encode(['secret_ref' => $registered['secret_ref']])
        );

        $this->assertSame(200, $revokeStatus);
        $this->assertTrue($revokeBody['revoked']);

        [, $verify] = $router->handle(
            'POST',
            '/v1/provider/secrets/verify',
            $this->authorizedHeaders(),
            json_encode(['identity_ref' => 'agent_1', 'api_key' => $apiKey])
        );

        $this->assertFalse($verify['verified']);
    }

    // --- mfa/verify ---

    public function testMfaVerifyRequiresIdentityRef(): void
    {
        [$status, $body] = $this->router()->handle('POST', '/v1/provider/mfa/verify', $this->authorizedHeaders(), '{}');

        $this->assertSame(422, $status);
        $this->assertSame('invalid_request', $body['error']);
    }

    public function testMfaVerifyAcceptsANullProofAsNotVerifiedRatherThanAnError(): void
    {
        [$status, $body] = $this->router()->handle(
            'POST',
            '/v1/provider/mfa/verify',
            $this->authorizedHeaders(),
            json_encode(['identity_ref' => 'agent_1'])
        );

        $this->assertSame(200, $status);
        $this->assertFalse($body['verified']);
        $this->assertNull($body['verification_ref']);
    }

    public function testMfaVerifyReturnsVerifiedFalseForAnUnenrolledIdentity(): void
    {
        [$status, $body] = $this->router()->handle(
            'POST',
            '/v1/provider/mfa/verify',
            $this->authorizedHeaders(),
            json_encode(['identity_ref' => 'agent_1', 'proof' => '123456'])
        );

        $this->assertSame(200, $status);
        $this->assertFalse($body['verified']);
    }

    public function testMfaVerifyReturnsVerifiedTrueForACorrectEnrolledCode(): void
    {
        $databasePath = $this->tempPath('main');
        $encryption = new SqliteEncryptionManager($this->tempPath('encryption'));
        $mfa = new MfaSecretStore($this->tempPath('mfa'), $encryption, random_bytes(32));
        $router = new CredentialProviderRouter(
            new SqliteSecretsManager($databasePath),
            $mfa,
            new SqliteSecurityEventSink($databasePath),
            self::TOKEN
        );
        $enrollment = $mfa->enroll('agent_1');
        $code = (new TotpVerifier())->code($enrollment['secret']);

        [$status, $body] = $router->handle(
            'POST',
            '/v1/provider/mfa/verify',
            $this->authorizedHeaders(),
            json_encode(['identity_ref' => 'agent_1', 'proof' => $code])
        );

        $this->assertSame(200, $status);
        $this->assertTrue($body['verified']);
        $this->assertIsString($body['verification_ref']);
        $this->assertStringStartsWith('mfa_verification_', $body['verification_ref']);
    }

    // --- security-events ---

    public function testRecordEventRequiresEventTypeOutcomeAndCorrelationId(): void
    {
        [$status, $body] = $this->router()->handle('POST', '/v1/provider/security-events', $this->authorizedHeaders(), '{}');

        $this->assertSame(422, $status);
        $this->assertSame('invalid_request', $body['error']);
    }

    public function testRecordEventReturnsANonEmptySecurityEventRef(): void
    {
        $payload = json_encode([
            'event_type' => 'AUTHENTICATION',
            'outcome' => 'SUCCESS',
            'identity_ref' => 'agent_1',
            'correlation_id' => 'corr_123',
            'metadata' => ['source_ref' => 'unit-test'],
        ]);

        [$status, $body] = $this->router()->handle('POST', '/v1/provider/security-events', $this->authorizedHeaders(), $payload);

        $this->assertSame(200, $status);
        $this->assertIsString($body['security_event_ref']);
        $this->assertNotSame('', $body['security_event_ref']);
    }

    public function testRecordEventAcceptsANullIdentityRefAndMissingMetadata(): void
    {
        $payload = json_encode([
            'event_type' => 'LOCKOUT',
            'outcome' => 'LOCKED',
            'correlation_id' => 'corr_456',
        ]);

        [$status, $body] = $this->router()->handle('POST', '/v1/provider/security-events', $this->authorizedHeaders(), $payload);

        $this->assertSame(200, $status);
        $this->assertIsString($body['security_event_ref']);
    }

    public function testRecordEventAcceptsAnyEventTypeAndOutcomeString(): void
    {
        // Contract: event_type/outcome are not drawn from a fixed enum server-side.
        $payload = json_encode([
            'event_type' => 'SOME_FUTURE_EVENT_TYPE',
            'outcome' => 'SOME_FUTURE_OUTCOME',
            'correlation_id' => 'corr_789',
        ]);

        [$status] = $this->router()->handle('POST', '/v1/provider/security-events', $this->authorizedHeaders(), $payload);

        $this->assertSame(200, $status);
    }

    // --- malformed bodies ---

    public function testMalformedJsonBodyIsTreatedAsAnEmptyPayload(): void
    {
        [$status, $body] = $this->router()->handle('POST', '/v1/provider/secrets/register', $this->authorizedHeaders(), 'not json at all');

        $this->assertSame(422, $status);
        $this->assertSame('invalid_request', $body['error']);
    }

    public function testNullBodyIsTreatedAsAnEmptyPayload(): void
    {
        [$status, $body] = $this->router()->handle('POST', '/v1/provider/secrets/register', $this->authorizedHeaders(), null);

        $this->assertSame(422, $status);
        $this->assertSame('invalid_request', $body['error']);
    }
}
