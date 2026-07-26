<?php

declare(strict_types=1);

namespace SquirrelForge\RuntimeConfig;

use DateTimeImmutable;
use PDO;
use PDOException;
use SquirrelForge\Contracts\SecretsManagerInterface;
use SquirrelForge\Contracts\ProviderReadinessInterface;

final class SqliteSecretsManager implements SecretsManagerInterface, ProviderReadinessInterface
{
    private PDO $database;

    public function __construct(string $databasePath)
    {
        if ($databasePath === '') {
            throw new PDOException('SQLite secrets database path must not be empty.');
        }

        $directory = dirname($databasePath);

        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new PDOException('SQLite secrets database directory could not be created.');
        }

        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->migrate();
    }

    public function providerRef(): string
    {
        return 'secrets:sqlite-local';
    }

    public function isProductionReady(): bool
    {
        return false;
    }

    public function registerApiKey(
        string $identityRef,
        string $apiKey,
        ?DateTimeImmutable $expiresAt = null
    ): string {
        if ($identityRef === '' || strlen($apiKey) < 32) {
            throw new PDOException('API keys require an owner and at least 32 characters.');
        }

        $existing = $this->verifyApiKey($identityRef, $apiKey);

        if (is_string($existing['secret_ref'] ?? null)) {
            return $existing['secret_ref'];
        }

        $secretRef = 'secret_' . bin2hex(random_bytes(12));
        $statement = $this->database->prepare(
            'INSERT INTO secret_records (
                secret_ref, owner_identity_ref, secret_type, status,
                secret_hash, expires_at, revoked_at, created_at
            ) VALUES (
                :secret_ref, :owner_identity_ref, :secret_type, :status,
                :secret_hash, :expires_at, NULL, :created_at
            )'
        );
        $statement->execute([
            'secret_ref' => $secretRef,
            'owner_identity_ref' => $identityRef,
            'secret_type' => 'API_KEY',
            'status' => 'ACTIVE',
            'secret_hash' => password_hash($apiKey, PASSWORD_DEFAULT),
            'expires_at' => $expiresAt?->format(DATE_RFC3339),
            'created_at' => gmdate(DATE_RFC3339),
        ]);

        return $secretRef;
    }

    public function revoke(string $secretRef): void
    {
        $statement = $this->database->prepare(
            'UPDATE secret_records
             SET status = :status, revoked_at = :revoked_at
             WHERE secret_ref = :secret_ref'
        );
        $statement->execute([
            'status' => 'REVOKED',
            'revoked_at' => gmdate(DATE_RFC3339),
            'secret_ref' => $secretRef,
        ]);
    }

    public function rotateApiKey(
        string $secretRef,
        string $newApiKey,
        ?DateTimeImmutable $expiresAt = null
    ): string {
        if (strlen($newApiKey) < 32) {
            throw new PDOException('API keys require at least 32 characters.');
        }

        $this->database->beginTransaction();

        try {
            $statement = $this->database->prepare(
                'SELECT owner_identity_ref, status FROM secret_records
                 WHERE secret_ref = :secret_ref AND secret_type = :secret_type'
            );
            $statement->execute(['secret_ref' => $secretRef, 'secret_type' => 'API_KEY']);
            $current = $statement->fetch();

            if (!is_array($current) || $current['status'] !== 'ACTIVE') {
                throw new PDOException('Only an active API key may be rotated.');
            }

            $newSecretRef = $this->registerApiKey(
                $current['owner_identity_ref'],
                $newApiKey,
                $expiresAt
            );
            $statement = $this->database->prepare(
                'UPDATE secret_records
                 SET status = :status, revoked_at = :revoked_at, rotated_to_ref = :rotated_to_ref
                 WHERE secret_ref = :secret_ref'
            );
            $statement->execute([
                'status' => 'ROTATED',
                'revoked_at' => gmdate(DATE_RFC3339),
                'rotated_to_ref' => $newSecretRef,
                'secret_ref' => $secretRef,
            ]);
            $this->database->commit();

            return $newSecretRef;
        } catch (\Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }

            throw $exception;
        }
    }

    public function verifyApiKey(string $identityRef, string $apiKey): array
    {
        $statement = $this->database->prepare(
            'SELECT * FROM secret_records
             WHERE owner_identity_ref = :identity_ref AND secret_type = :secret_type
             ORDER BY created_at DESC'
        );
        $statement->execute([
            'identity_ref' => $identityRef,
            'secret_type' => 'API_KEY',
        ]);
        $now = new DateTimeImmutable();

        foreach ($statement->fetchAll() as $secret) {
            if (!password_verify($apiKey, $secret['secret_hash'])) {
                continue;
            }

            $notExpired = $secret['expires_at'] === null
                || new DateTimeImmutable($secret['expires_at']) > $now;
            $verified = $secret['status'] === 'ACTIVE'
                && $secret['revoked_at'] === null
                && $notExpired;

            return [
                'verified' => $verified,
                'secret_ref' => $secret['secret_ref'],
                'verification_ref' => $verified
                    ? 'credential_verification_' . bin2hex(random_bytes(12))
                    : null,
                'rationale' => $verified
                    ? 'The active API-key secret matched.'
                    : 'The API-key secret is unavailable.',
            ];
        }

        return [
            'verified' => false,
            'secret_ref' => null,
            'verification_ref' => null,
            'rationale' => 'The credential could not be verified.',
        ];
    }

    private function migrate(): void
    {
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS secret_records (
                secret_ref TEXT PRIMARY KEY,
                owner_identity_ref TEXT NOT NULL,
                secret_type TEXT NOT NULL,
                status TEXT NOT NULL,
                secret_hash TEXT NOT NULL,
                expires_at TEXT,
                revoked_at TEXT,
                rotated_to_ref TEXT,
                created_at TEXT NOT NULL
            )'
        );
        $columns = $this->database->query('PRAGMA table_info(secret_records)')->fetchAll();

        if (!in_array('rotated_to_ref', array_column($columns, 'name'), true)) {
            $this->database->exec('ALTER TABLE secret_records ADD COLUMN rotated_to_ref TEXT');
        }
        $this->database->exec(
            'CREATE INDEX IF NOT EXISTS secret_owner_type_idx
             ON secret_records(owner_identity_ref, secret_type, status)'
        );
    }
}
