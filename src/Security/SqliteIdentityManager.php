<?php

declare(strict_types=1);

namespace SquirrelForge\Security;

use PDO;
use PDOException;
use SquirrelForge\Contracts\IdentityManagerInterface;

final class SqliteIdentityManager implements IdentityManagerInterface
{
    private PDO $database;

    public function __construct(string $databasePath)
    {
        if ($databasePath === '') {
            throw new PDOException('SQLite identity database path must not be empty.');
        }

        $directory = dirname($databasePath);

        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new PDOException('SQLite identity database directory could not be created.');
        }

        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->migrate();
    }

    public function provision(
        string $identityRef,
        string $type,
        array $attributes = [],
        array $roles = []
    ): void {
        if ($identityRef === '' || !in_array($type, ['USER', 'AGENT', 'SERVICE'], true)) {
            throw new PDOException('Identity reference and supported identity type are required.');
        }

        $statement = $this->database->prepare(
            'INSERT INTO identities (
                identity_ref, identity_type, status, attributes_json, roles_json, created_at, updated_at
            ) VALUES (
                :identity_ref, :identity_type, :status, :attributes_json, :roles_json, :created_at, :updated_at
            )
            ON CONFLICT(identity_ref) DO UPDATE SET
                identity_type = excluded.identity_type,
                attributes_json = excluded.attributes_json,
                roles_json = excluded.roles_json,
                updated_at = excluded.updated_at'
        );
        $now = gmdate(DATE_RFC3339);
        $statement->execute([
            'identity_ref' => $identityRef,
            'identity_type' => $type,
            'status' => 'ACTIVE',
            'attributes_json' => json_encode($attributes, JSON_THROW_ON_ERROR),
            'roles_json' => json_encode($roles, JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function setStatus(string $identityRef, string $status): void
    {
        if (!in_array($status, ['ACTIVE', 'SUSPENDED', 'DEACTIVATED'], true)) {
            throw new PDOException('Unsupported identity status.');
        }

        $statement = $this->database->prepare(
            'UPDATE identities SET status = :status, updated_at = :updated_at WHERE identity_ref = :identity_ref'
        );
        $statement->execute([
            'status' => $status,
            'updated_at' => gmdate(DATE_RFC3339),
            'identity_ref' => $identityRef,
        ]);
    }

    public function identity(string $identityRef): ?array
    {
        $statement = $this->database->prepare(
            'SELECT * FROM identities WHERE identity_ref = :identity_ref'
        );
        $statement->execute(['identity_ref' => $identityRef]);
        $identity = $statement->fetch();

        if (!is_array($identity)) {
            return null;
        }

        $identity['attributes'] = json_decode($identity['attributes_json'], true, 512, JSON_THROW_ON_ERROR);
        $identity['roles'] = json_decode($identity['roles_json'], true, 512, JSON_THROW_ON_ERROR);
        unset($identity['attributes_json'], $identity['roles_json']);

        return $identity;
    }

    public function isActive(string $identityRef): bool
    {
        return ($this->identity($identityRef)['status'] ?? null) === 'ACTIVE';
    }

    private function migrate(): void
    {
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS identities (
                identity_ref TEXT PRIMARY KEY,
                identity_type TEXT NOT NULL,
                status TEXT NOT NULL,
                attributes_json TEXT NOT NULL,
                roles_json TEXT NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );
    }
}
