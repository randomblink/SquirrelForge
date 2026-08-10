<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\CredentialProvider;

use PHPUnit\Framework\TestCase;
use SquirrelForge\CredentialProvider\MfaSecretStore;
use SquirrelForge\CredentialProvider\TotpVerifier;
use SquirrelForge\Security\SqliteEncryptionManager;

final class MfaSecretStoreTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-mfa-secret-store-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function store(): MfaSecretStore
    {
        $encryption = new SqliteEncryptionManager($this->tempPath('encryption'));

        return new MfaSecretStore($this->tempPath('mfa'), $encryption, random_bytes(32));
    }

    public function testEnrollRequiresANonEmptyIdentityRef(): void
    {
        $result = $this->store()->enroll('');

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testEnrollReturnsARealSecretAndProvisioningUri(): void
    {
        $result = $this->store()->enroll('agent_1');

        $this->assertSame('enrolled', $result['outcome']);
        $this->assertNotNull($result['secret']);
        $this->assertStringStartsWith('otpauth://totp/', $result['provisioning_uri']);
        $this->assertStringContainsString('secret=' . $result['secret'], $result['provisioning_uri']);
    }

    public function testIsEnrolledReflectsRealState(): void
    {
        $store = $this->store();

        $this->assertFalse($store->isEnrolled('agent_1'));
        $store->enroll('agent_1');
        $this->assertTrue($store->isEnrolled('agent_1'));
    }

    public function testVerifyAcceptsTheCurrentRealCodeForTheEnrolledSecret(): void
    {
        $store = $this->store();
        $enrollment = $store->enroll('agent_1');
        $totp = new TotpVerifier();
        $code = $totp->code($enrollment['secret']);

        $this->assertTrue($store->verify('agent_1', $code));
    }

    public function testVerifyRejectsAWrongCode(): void
    {
        $store = $this->store();
        $store->enroll('agent_1');

        $this->assertFalse($store->verify('agent_1', '000000'));
    }

    public function testVerifyRejectsANullCode(): void
    {
        $store = $this->store();
        $store->enroll('agent_1');

        $this->assertFalse($store->verify('agent_1', null));
    }

    public function testVerifyRejectsAnUnenrolledIdentity(): void
    {
        $this->assertFalse($this->store()->verify('ghost', '123456'));
    }

    public function testTheStoredSecretIsGenuinelyEncryptedNotPlaintext(): void
    {
        $databasePath = $this->tempPath('mfa-direct');
        $encryption = new SqliteEncryptionManager($this->tempPath('encryption'));
        $store = new MfaSecretStore($databasePath, $encryption, random_bytes(32));

        $enrollment = $store->enroll('agent_1');

        $pdo = new \PDO('sqlite:' . $databasePath);
        $row = $pdo->query('SELECT ciphertext FROM mfa_secrets WHERE identity_ref = \'agent_1\'')->fetch(\PDO::FETCH_ASSOC);

        $this->assertStringNotContainsString($enrollment['secret'], $row['ciphertext']);
    }

    public function testReEnrollmentReplacesTheSecretAndInvalidatesTheOldOne(): void
    {
        $store = $this->store();
        $first = $store->enroll('agent_1');
        $totp = new TotpVerifier();
        $firstCode = $totp->code($first['secret']);

        $second = $store->enroll('agent_1');

        $this->assertNotSame($first['secret'], $second['secret']);
        // The old secret's code is now checked against the NEW secret and (overwhelmingly likely) fails.
        $this->assertFalse($store->verify('agent_1', $firstCode));
        $this->assertTrue($store->verify('agent_1', $totp->code($second['secret'])));
    }
}
