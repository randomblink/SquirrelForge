<?php

declare(strict_types=1);

namespace SquirrelForge\CredentialProvider;

use DateTimeImmutable;
use PDO;
use SquirrelForge\Security\SqliteEncryptionManager;

/**
 * Stores per-identity TOTP shared secrets encrypted at rest and
 * verifies presented codes against them.
 *
 * TOTP secrets are the one piece of this service that genuinely needs
 * *reversible* storage rather than one-way hashing: verification
 * requires recomputing `HMAC(secret, time)` fresh on every check and
 * comparing, which is only possible with the plaintext secret in
 * hand -- unlike API-key verification (handled by the reused, already-
 * real `SqliteSecretsManager`), where one-way `password_hash()` is
 * both sufficient and the stronger choice since the plaintext is never
 * needed again after registration.
 *
 * Encryption is genuine composition of the already-real, already-
 * audited `SqliteEncryptionManager::perform()` (AES-256-GCM via
 * `openssl_encrypt`/`openssl_decrypt`) rather than a second, duplicate
 * `openssl_encrypt` call written here -- this class only ever handles
 * ciphertext/IV/tag at rest, never a raw key byte outside the moment
 * `SqliteEncryptionManager` itself decrypts one in memory to hand back
 * to `TotpVerifier::verify()`.
 */
final class MfaSecretStore
{
    private const KEY_REF = 'mfa-secret-store-master-key';

    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly SqliteEncryptionManager $encryption,
        private readonly string $masterKeyMaterial,
        private readonly TotpVerifier $totp = new TotpVerifier()
    ) {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS mfa_secrets (
                identity_ref TEXT PRIMARY KEY,
                ciphertext TEXT NOT NULL,
                iv TEXT NOT NULL,
                tag TEXT NOT NULL,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * Enrolls (or re-enrolls, replacing any prior secret) an identity
     * for TOTP. Returns the raw secret and a provisioning URI exactly
     * once, at enrollment time -- this class never exposes the
     * plaintext secret again after this call returns.
     *
     * @return array{outcome: string, secret: ?string, provisioning_uri: ?string, error: ?string}
     */
    public function enroll(string $identityRef): array
    {
        if ($identityRef === '') {
            return ['outcome' => 'invalid', 'secret' => null, 'provisioning_uri' => null, 'error' => 'A non-empty identity_ref is required.'];
        }

        $secret = TotpVerifier::generateSecret();
        $encrypted = $this->encryption->perform([
            'operation' => 'encrypt',
            'identity_ref' => $identityRef,
            'key_ref' => self::KEY_REF,
            'key_material' => $this->masterKeyMaterial,
            'plaintext' => $secret,
            'correlation_id' => 'mfa_enroll_' . bin2hex(random_bytes(8)),
        ]);

        if ($encrypted['outcome'] !== 'completed') {
            return ['outcome' => 'failed', 'secret' => null, 'provisioning_uri' => null, 'error' => $encrypted['error'] ?? 'Encryption failed.'];
        }

        $statement = $this->database->prepare(
            'INSERT INTO mfa_secrets (identity_ref, ciphertext, iv, tag, created_at)
             VALUES (:identity_ref, :ciphertext, :iv, :tag, :created_at)
             ON CONFLICT(identity_ref) DO UPDATE SET
                ciphertext = excluded.ciphertext, iv = excluded.iv, tag = excluded.tag, created_at = excluded.created_at'
        );
        $statement->execute([
            'identity_ref' => $identityRef,
            'ciphertext' => $encrypted['result']['ciphertext'],
            'iv' => $encrypted['result']['iv'],
            'tag' => $encrypted['result']['tag'],
            'created_at' => (new DateTimeImmutable())->format(DATE_ATOM),
        ]);

        return [
            'outcome' => 'enrolled',
            'secret' => $secret,
            'provisioning_uri' => $this->totp->provisioningUri($secret, $identityRef),
            'error' => null,
        ];
    }

    /**
     * Decrypts the stored secret in memory only for the duration of
     * this check and verifies the presented code against it.
     */
    public function verify(string $identityRef, ?string $code): bool
    {
        if ($code === null || $code === '') {
            return false;
        }

        $statement = $this->database->prepare('SELECT * FROM mfa_secrets WHERE identity_ref = :identity_ref');
        $statement->execute(['identity_ref' => $identityRef]);
        $row = $statement->fetch();

        if ($row === false) {
            return false;
        }

        $decrypted = $this->encryption->perform([
            'operation' => 'decrypt',
            'identity_ref' => $identityRef,
            'key_ref' => self::KEY_REF,
            'key_material' => $this->masterKeyMaterial,
            'ciphertext' => $row['ciphertext'],
            'iv' => $row['iv'],
            'tag' => $row['tag'],
            'correlation_id' => 'mfa_verify_' . bin2hex(random_bytes(8)),
        ]);

        if ($decrypted['outcome'] !== 'completed') {
            return false;
        }

        return $this->totp->verify($decrypted['result']['plaintext'], $code);
    }

    public function isEnrolled(string $identityRef): bool
    {
        $statement = $this->database->prepare('SELECT 1 FROM mfa_secrets WHERE identity_ref = :identity_ref LIMIT 1');
        $statement->execute(['identity_ref' => $identityRef]);

        return $statement->fetch() !== false;
    }
}
