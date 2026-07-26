<?php

declare(strict_types=1);

namespace SquirrelForge\Security;

use DateTimeImmutable;
use JsonException;
use PDO;
use PDOException;
use SquirrelForge\Contracts\AuthorizationManagerInterface;

final class SqliteAuthorizationManager implements AuthorizationManagerInterface
{
    private PDO $database;

    public function __construct(string $databasePath)
    {
        if ($databasePath === '') {
            throw new PDOException('SQLite authorization database path must not be empty.');
        }

        $directory = dirname($databasePath);

        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new PDOException('SQLite authorization database directory could not be created.');
        }

        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA foreign_keys = ON');
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->migrate();
    }

    /**
     * @param array<int, string> $operations
     * @param array<int, string> $resourceRefs
     */
    public function issueGrant(
        string $permissionRef,
        string $identityRef,
        array $operations,
        array $resourceRefs = ['*'],
        ?DateTimeImmutable $expiresAt = null,
        array $restrictions = []
    ): void {
        if ($permissionRef === '' || $identityRef === '' || $operations === [] || $resourceRefs === []) {
            throw new PDOException('Authorization grants require permission, identity, operation, and resource values.');
        }

        $statement = $this->database->prepare(
            'INSERT INTO authorization_grants (
                permission_ref, identity_ref, operations_json, resources_json,
                restrictions_json, expires_at, revoked_at, created_at
            ) VALUES (
                :permission_ref, :identity_ref, :operations_json, :resources_json,
                :restrictions_json, :expires_at, NULL, :created_at
            )
            ON CONFLICT(permission_ref) DO UPDATE SET
                identity_ref = excluded.identity_ref,
                operations_json = excluded.operations_json,
                resources_json = excluded.resources_json,
                restrictions_json = excluded.restrictions_json,
                expires_at = excluded.expires_at,
                revoked_at = NULL'
        );
        $statement->execute([
            'permission_ref' => $permissionRef,
            'identity_ref' => $identityRef,
            'operations_json' => $this->encode(array_values(array_unique($operations))),
            'resources_json' => $this->encode(array_values(array_unique($resourceRefs))),
            'restrictions_json' => $this->encode($restrictions),
            'expires_at' => $expiresAt?->format(DATE_RFC3339),
            'created_at' => gmdate(DATE_RFC3339),
        ]);
    }

    public function revokeGrant(string $permissionRef): void
    {
        $statement = $this->database->prepare(
            'UPDATE authorization_grants SET revoked_at = :revoked_at WHERE permission_ref = :permission_ref'
        );
        $statement->execute([
            'revoked_at' => gmdate(DATE_RFC3339),
            'permission_ref' => $permissionRef,
        ]);
    }

    public function authorize(
        string $identityRef,
        string $permissionRef,
        string $operation,
        string $resourceRef,
        string $correlationId
    ): array {
        $grant = $this->grant($permissionRef);
        $decision = 'DENIED';
        $rationale = 'No active grant matches the authorization request.';
        $restrictions = [];

        if ($grant !== null && hash_equals($grant['identity_ref'], $identityRef)) {
            $operations = $this->decode($grant['operations_json']);
            $resources = $this->decode($grant['resources_json']);
            $restrictions = $this->decode($grant['restrictions_json']);
            $notExpired = $grant['expires_at'] === null
                || new DateTimeImmutable($grant['expires_at']) > new DateTimeImmutable();
            $operationAllowed = in_array('*', $operations, true) || in_array($operation, $operations, true);
            $resourceAllowed = in_array('*', $resources, true) || in_array($resourceRef, $resources, true);

            if ($grant['revoked_at'] !== null) {
                $rationale = 'The permission grant was revoked.';
            } elseif (!$notExpired) {
                $rationale = 'The permission grant expired.';
            } elseif (!$operationAllowed) {
                $rationale = 'The permission grant does not allow the requested operation.';
            } elseif (!$resourceAllowed) {
                $rationale = 'The permission grant does not allow the requested resource.';
            } else {
                $decision = $restrictions === [] ? 'AUTHORIZED' : 'AUTHORIZED_WITH_RESTRICTIONS';
                $rationale = 'The active permission grant allows this operation and resource.';
            }
        } elseif ($grant !== null) {
            $rationale = 'The permission grant belongs to another identity.';
        }

        $authorizationRef = 'authorization_' . bin2hex(random_bytes(12));
        $authorized = in_array($decision, ['AUTHORIZED', 'AUTHORIZED_WITH_RESTRICTIONS'], true);
        $record = [
            'authorization_ref' => $authorizationRef,
            'decision' => $decision,
            'authorized' => $authorized,
            'identity_ref' => $identityRef,
            'permission_ref' => $permissionRef,
            'operation' => $operation,
            'resource_ref' => $resourceRef,
            'correlation_id' => $correlationId,
            'rationale' => $rationale,
            'restrictions' => $authorized ? $restrictions : [],
        ];
        $statement = $this->database->prepare(
            'INSERT INTO authorization_decisions (
                authorization_ref, identity_ref, permission_ref, operation,
                resource_ref, correlation_id, decision, rationale,
                restrictions_json, created_at
            ) VALUES (
                :authorization_ref, :identity_ref, :permission_ref, :operation,
                :resource_ref, :correlation_id, :decision, :rationale,
                :restrictions_json, :created_at
            )'
        );
        $statement->execute([
            'authorization_ref' => $authorizationRef,
            'identity_ref' => $identityRef,
            'permission_ref' => $permissionRef,
            'operation' => $operation,
            'resource_ref' => $resourceRef,
            'correlation_id' => $correlationId,
            'decision' => $decision,
            'rationale' => $rationale,
            'restrictions_json' => $this->encode($record['restrictions']),
            'created_at' => gmdate(DATE_RFC3339),
        ]);

        return $record;
    }

    public function decision(string $authorizationRef): ?array
    {
        $statement = $this->database->prepare(
            'SELECT * FROM authorization_decisions WHERE authorization_ref = :authorization_ref'
        );
        $statement->execute(['authorization_ref' => $authorizationRef]);
        $decision = $statement->fetch();

        if (!is_array($decision)) {
            return null;
        }

        $decision['authorized'] = in_array(
            $decision['decision'],
            ['AUTHORIZED', 'AUTHORIZED_WITH_RESTRICTIONS'],
            true
        );
        $decision['restrictions'] = $this->decode($decision['restrictions_json']);
        unset($decision['restrictions_json']);

        return $decision;
    }

    private function migrate(): void
    {
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS authorization_grants (
                permission_ref TEXT PRIMARY KEY,
                identity_ref TEXT NOT NULL,
                operations_json TEXT NOT NULL,
                resources_json TEXT NOT NULL,
                restrictions_json TEXT NOT NULL,
                expires_at TEXT,
                revoked_at TEXT,
                created_at TEXT NOT NULL
            )'
        );
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS authorization_decisions (
                authorization_ref TEXT PRIMARY KEY,
                identity_ref TEXT NOT NULL,
                permission_ref TEXT NOT NULL,
                operation TEXT NOT NULL,
                resource_ref TEXT NOT NULL,
                correlation_id TEXT NOT NULL,
                decision TEXT NOT NULL,
                rationale TEXT NOT NULL,
                restrictions_json TEXT NOT NULL,
                created_at TEXT NOT NULL
            )'
        );
        $this->database->exec(
            'CREATE INDEX IF NOT EXISTS authorization_decisions_permission_idx
             ON authorization_decisions(permission_ref, created_at)'
        );
    }

    private function grant(string $permissionRef): ?array
    {
        $statement = $this->database->prepare(
            'SELECT * FROM authorization_grants WHERE permission_ref = :permission_ref'
        );
        $statement->execute(['permission_ref' => $permissionRef]);
        $grant = $statement->fetch();

        return is_array($grant) ? $grant : null;
    }

    private function encode(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    private function decode(string $value): array
    {
        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new PDOException('Stored authorization JSON is invalid.', 0, $exception);
        }

        if (!is_array($decoded)) {
            throw new PDOException('Stored authorization JSON must decode to an array.');
        }

        return $decoded;
    }
}
