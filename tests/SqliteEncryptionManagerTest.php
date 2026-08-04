<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Automation\RuleEngine;
use SquirrelForge\Governance\SqlitePolicyEngine;
use SquirrelForge\Security\SqliteEncryptionManager;
use SquirrelForge\Security\SqliteSecurityGovernance;
use SquirrelForge\Security\StaticAuthorizationManager;
use SquirrelForge\Security\StaticHeaderAuthenticationManager;

final class SqliteEncryptionManagerTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-encryption-manager-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function manager(): SqliteEncryptionManager
    {
        return new SqliteEncryptionManager($this->tempPath('crypto'));
    }

    /**
     * @return array{0: string, 1: string} [privateKeyPem, publicKeyPem]
     */
    private function rsaKeyPair(): array
    {
        $resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($resource, $privateKeyPem);
        $publicKeyPem = openssl_pkey_get_details($resource)['key'];

        return [$privateKeyPem, $publicKeyPem];
    }

    public function testPerformRejectsAnUnsupportedOperation(): void
    {
        $manager = $this->manager();

        $result = $manager->perform(['operation' => 'astrology', 'identity_ref' => 'identity_1']);

        $this->assertSame('rejected', $result['outcome']);
    }

    public function testPerformRequiresARequestingIdentity(): void
    {
        $manager = $this->manager();

        $result = $manager->perform(['operation' => 'hash', 'data' => 'hello']);

        $this->assertSame('rejected', $result['outcome']);
    }

    public function testEncryptThenDecryptRoundTrips(): void
    {
        $manager = $this->manager();
        $key = base64_encode(random_bytes(32));

        $encrypted = $manager->perform(['operation' => 'encrypt', 'identity_ref' => 'identity_1', 'key_material' => $key, 'plaintext' => 'secret message']);
        $decrypted = $manager->perform([
            'operation' => 'decrypt', 'identity_ref' => 'identity_1', 'key_material' => $key,
            'ciphertext' => $encrypted['result']['ciphertext'], 'iv' => $encrypted['result']['iv'], 'tag' => $encrypted['result']['tag'],
        ]);

        $this->assertSame('completed', $encrypted['outcome']);
        $this->assertSame('completed', $decrypted['outcome']);
        $this->assertSame('secret message', $decrypted['result']['plaintext']);
    }

    public function testDecryptFailsWithATamperedTag(): void
    {
        $manager = $this->manager();
        $key = base64_encode(random_bytes(32));
        $encrypted = $manager->perform(['operation' => 'encrypt', 'identity_ref' => 'identity_1', 'key_material' => $key, 'plaintext' => 'secret']);

        $result = $manager->perform([
            'operation' => 'decrypt', 'identity_ref' => 'identity_1', 'key_material' => $key,
            'ciphertext' => $encrypted['result']['ciphertext'], 'iv' => $encrypted['result']['iv'], 'tag' => base64_encode(random_bytes(16)),
        ]);

        $this->assertSame('failed', $result['outcome']);
    }

    public function testEncryptRejectsARevokedKey(): void
    {
        $manager = $this->manager();

        $result = $manager->perform(['operation' => 'encrypt', 'identity_ref' => 'identity_1', 'key_material' => 'x', 'key_revoked' => true, 'plaintext' => 'secret']);

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('revoked', $result['error']);
    }

    public function testEncryptRejectsAnExpiredKey(): void
    {
        $manager = $this->manager();

        $result = $manager->perform(['operation' => 'encrypt', 'identity_ref' => 'identity_1', 'key_material' => 'x', 'key_expires_at' => '2000-01-01T00:00:00+00:00', 'plaintext' => 'secret']);

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('expired', $result['error']);
    }

    public function testHashThenVerifyHashRoundTrips(): void
    {
        $manager = $this->manager();
        $hashed = $manager->perform(['operation' => 'hash', 'identity_ref' => 'identity_1', 'data' => 'hello world']);

        $result = $manager->perform(['operation' => 'verify_hash', 'identity_ref' => 'identity_1', 'data' => 'hello world', 'expected_hash' => $hashed['result']['hash']]);

        $this->assertSame('completed', $hashed['outcome']);
        $this->assertTrue($result['result']['matches']);
    }

    public function testVerifyHashFailsForMismatchedData(): void
    {
        $manager = $this->manager();
        $hashed = $manager->perform(['operation' => 'hash', 'identity_ref' => 'identity_1', 'data' => 'hello world']);

        $result = $manager->perform(['operation' => 'verify_hash', 'identity_ref' => 'identity_1', 'data' => 'tampered', 'expected_hash' => $hashed['result']['hash']]);

        $this->assertFalse($result['result']['matches']);
    }

    public function testHashRejectsAnUnapprovedAlgorithm(): void
    {
        $manager = $this->manager();

        $result = $manager->perform(['operation' => 'hash', 'identity_ref' => 'identity_1', 'algorithm' => 'md5', 'data' => 'hello']);

        $this->assertSame('rejected', $result['outcome']);
    }

    public function testSignThenVerifySignatureRoundTrips(): void
    {
        $manager = $this->manager();
        [$privateKey, $publicKey] = $this->rsaKeyPair();

        $signed = $manager->perform(['operation' => 'sign', 'identity_ref' => 'identity_1', 'key_material' => $privateKey, 'data' => 'document contents']);
        $verified = $manager->perform(['operation' => 'verify_signature', 'identity_ref' => 'identity_1', 'key_material' => $publicKey, 'data' => 'document contents', 'signature' => $signed['result']['signature']]);

        $this->assertSame('completed', $signed['outcome']);
        $this->assertTrue($verified['result']['matches']);
    }

    public function testVerifySignatureFailsForTamperedData(): void
    {
        $manager = $this->manager();
        [$privateKey, $publicKey] = $this->rsaKeyPair();
        $signed = $manager->perform(['operation' => 'sign', 'identity_ref' => 'identity_1', 'key_material' => $privateKey, 'data' => 'document contents']);

        $verified = $manager->perform(['operation' => 'verify_signature', 'identity_ref' => 'identity_1', 'key_material' => $publicKey, 'data' => 'tampered contents', 'signature' => $signed['result']['signature']]);

        $this->assertFalse($verified['result']['matches']);
    }

    public function testHmacThenVerifyIntegrityRoundTrips(): void
    {
        $manager = $this->manager();
        $key = base64_encode(random_bytes(32));
        $mac = $manager->perform(['operation' => 'hmac', 'identity_ref' => 'identity_1', 'key_material' => $key, 'data' => 'payload']);

        $result = $manager->perform(['operation' => 'verify_integrity', 'identity_ref' => 'identity_1', 'key_material' => $key, 'data' => 'payload', 'expected_mac' => $mac['result']['mac']]);

        $this->assertTrue($result['result']['matches']);
    }

    public function testVerifyIntegrityDetectsTamperedPayload(): void
    {
        $manager = $this->manager();
        $key = base64_encode(random_bytes(32));
        $mac = $manager->perform(['operation' => 'hmac', 'identity_ref' => 'identity_1', 'key_material' => $key, 'data' => 'payload']);

        $result = $manager->perform(['operation' => 'verify_integrity', 'identity_ref' => 'identity_1', 'key_material' => $key, 'data' => 'tampered', 'expected_mac' => $mac['result']['mac']]);

        $this->assertFalse($result['result']['matches']);
    }

    public function testAuthenticationSucceedsWithAConfiguredAuthenticator(): void
    {
        $manager = new SqliteEncryptionManager($this->tempPath('crypto'), new StaticHeaderAuthenticationManager());

        $result = $manager->perform(['operation' => 'hash', 'identity_ref' => 'identity_1', 'data' => 'hello']);

        $this->assertSame('completed', $result['outcome']);
    }

    public function testAuthorizationDenialIsRejected(): void
    {
        $manager = new SqliteEncryptionManager($this->tempPath('crypto'), null, new StaticAuthorizationManager());

        $result = $manager->perform(['operation' => 'hash', 'identity_ref' => 'identity_1', 'permission_ref' => 'permission:denied', 'data' => 'hello']);

        $this->assertSame('denied', $result['outcome']);
    }

    public function testGovernanceRejectionBlocksTheOperation(): void
    {
        $governance = new SqliteSecurityGovernance($this->tempPath('gov'));
        $manager = new SqliteEncryptionManager($this->tempPath('crypto'), null, null, $governance);

        $result = $manager->perform(['operation' => 'hash', 'identity_ref' => 'identity_1', 'data' => 'hello']);

        $this->assertSame('rejected', $result['outcome']);
    }

    public function testGovernanceApprovalAllowsTheOperation(): void
    {
        $policyEngine = new SqlitePolicyEngine($this->tempPath('policy'), new RuleEngine());
        $policyEngine->registerPolicy('p1', ['category' => 'data_protection', 'priority' => 1, 'condition' => ['type' => 'boolean', 'field' => 'ready', 'equals' => true], 'effect' => 'allow']);
        $governance = new SqliteSecurityGovernance($this->tempPath('gov'), $policyEngine);
        $manager = new SqliteEncryptionManager($this->tempPath('crypto'), null, null, $governance);

        $result = $manager->perform(['operation' => 'hash', 'identity_ref' => 'identity_1', 'data' => 'hello']);

        $this->assertSame('completed', $result['outcome']);
    }

    public function testEveryOperationIsRecordedWithoutExposingKeyMaterialOrPlaintext(): void
    {
        $manager = $this->manager();
        $key = base64_encode(random_bytes(32));

        $result = $manager->perform(['operation' => 'encrypt', 'identity_ref' => 'identity_1', 'key_ref' => 'key_1', 'key_material' => $key, 'plaintext' => 'top secret']);
        $record = $manager->operation($result['cryptographic_operation_id']);

        $this->assertSame('completed', $record['final_outcome']);
        $this->assertSame('key_1', $record['key_ref']);
        $this->assertArrayNotHasKey('key_material', $record);
        $this->assertArrayNotHasKey('plaintext', $record);
        $this->assertStringNotContainsString('top secret', json_encode($record, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString($key, json_encode($record, JSON_THROW_ON_ERROR));
    }
}
