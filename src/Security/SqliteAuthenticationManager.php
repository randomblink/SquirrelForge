<?php

declare(strict_types=1);

namespace SquirrelForge\Security;

use DateInterval;
use DateTimeImmutable;
use PDO;
use PDOException;
use SquirrelForge\Contracts\AuthenticationManagerInterface;
use SquirrelForge\Contracts\IdentityManagerInterface;
use SquirrelForge\Contracts\SecurityEventSinkInterface;

final class SqliteAuthenticationManager implements AuthenticationManagerInterface
{
    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly IdentityManagerInterface $identities,
        private readonly SecurityEventSinkInterface $events = new NullSecurityEventSink()
    ) {
        if ($databasePath === '') {
            throw new PDOException('SQLite authentication database path must not be empty.');
        }

        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->migrate();
    }

    /**
     * Issues a session only after the caller supplies an authoritative credential-verification reference.
     *
     * @return array{session_ref: string, access_token: string, expires_at: string}
     */
    public function issueSession(
        string $identityRef,
        string $credentialVerificationRef,
        ?DateInterval $ttl = null,
        ?string $fixedToken = null
    ): array {
        if (!$this->identities->isActive($identityRef)) {
            throw new PDOException('Only an active identity may receive an authentication session.');
        }

        if ($credentialVerificationRef === '') {
            throw new PDOException('A credential-verification reference is required.');
        }

        $token = $fixedToken ?? rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        if (strlen($token) < 32) {
            throw new PDOException('Access tokens must contain at least 32 characters.');
        }

        $existing = $this->session(hash('sha256', $token));

        if ($existing !== null) {
            return [
                'session_ref' => $existing['session_ref'],
                'access_token' => $token,
                'expires_at' => $existing['expires_at'],
            ];
        }

        $sessionRef = 'session_' . bin2hex(random_bytes(12));
        $expiresAt = (new DateTimeImmutable())->add($ttl ?? new DateInterval('PT15M'));
        $statement = $this->database->prepare(
            'INSERT INTO authentication_sessions (
                session_ref, token_hash, identity_ref, credential_verification_ref,
                expires_at, revoked_at, created_at
            ) VALUES (
                :session_ref, :token_hash, :identity_ref, :credential_verification_ref,
                :expires_at, NULL, :created_at
            )'
        );
        $statement->execute([
            'session_ref' => $sessionRef,
            'token_hash' => hash('sha256', $token),
            'identity_ref' => $identityRef,
            'credential_verification_ref' => $credentialVerificationRef,
            'expires_at' => $expiresAt->format(DATE_RFC3339),
            'created_at' => gmdate(DATE_RFC3339),
        ]);

        return [
            'session_ref' => $sessionRef,
            'access_token' => $token,
            'expires_at' => $expiresAt->format(DATE_RFC3339),
        ];
    }

    public function revokeSession(string $sessionRef): void
    {
        $statement = $this->database->prepare(
            'UPDATE authentication_sessions SET revoked_at = :revoked_at WHERE session_ref = :session_ref'
        );
        $statement->execute([
            'revoked_at' => gmdate(DATE_RFC3339),
            'session_ref' => $sessionRef,
        ]);
    }

    public function refreshSession(
        string $accessToken,
        string $correlationId,
        ?DateInterval $ttl = null
    ): array {
        $session = $this->session(hash('sha256', $accessToken));

        if ($session === null
            || $session['revoked_at'] !== null
            || new DateTimeImmutable($session['expires_at']) <= new DateTimeImmutable()
            || !$this->identities->isActive($session['identity_ref'])) {
            $this->events->record('SESSION_REFRESH', 'DENIED', null, $correlationId);

            throw new PDOException('Only an active authentication session may be refreshed.');
        }

        $this->database->beginTransaction();

        try {
            $this->revokeSession($session['session_ref']);
            $refreshed = $this->issueSession(
                $session['identity_ref'],
                $session['credential_verification_ref'],
                $ttl
            );
            $this->database->commit();
        } catch (\Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }

            throw $exception;
        }

        $this->events->record(
            'SESSION_REFRESH',
            'SUCCESS',
            $session['identity_ref'],
            $correlationId,
            ['previous_session_ref' => $session['session_ref'], 'session_ref' => $refreshed['session_ref']]
        );

        return $refreshed;
    }

    public function authenticate(
        ?string $accessToken,
        ?string $claimedIdentityRef,
        string $correlationId
    ): array {
        $authenticationRef = 'authentication_' . bin2hex(random_bytes(12));
        $session = is_string($accessToken) && $accessToken !== ''
            ? $this->session(hash('sha256', $accessToken))
            : null;
        $authenticated = false;
        $identityRef = null;
        $sessionRef = null;
        $rationale = 'A valid authentication session is required.';

        if ($session !== null) {
            $identityRef = $session['identity_ref'];
            $sessionRef = $session['session_ref'];
            $active = $this->identities->isActive($identityRef);
            $notExpired = new DateTimeImmutable($session['expires_at']) > new DateTimeImmutable();

            if ($session['revoked_at'] !== null) {
                $rationale = 'The authentication session was revoked.';
            } elseif (!$notExpired) {
                $rationale = 'The authentication session expired.';
            } elseif (!$active) {
                $rationale = 'The authenticated identity is not active.';
            } elseif ($claimedIdentityRef !== null && !hash_equals($identityRef, $claimedIdentityRef)) {
                $rationale = 'The claimed identity does not match the authenticated session.';
            } else {
                $authenticated = true;
                $rationale = 'The active session verifies the identity.';
            }
        }

        $record = [
            'authentication_ref' => $authenticationRef,
            'authenticated' => $authenticated,
            'identity_ref' => $authenticated ? $identityRef : null,
            'session_ref' => $sessionRef,
            'correlation_id' => $correlationId,
            'rationale' => $rationale,
        ];
        $statement = $this->database->prepare(
            'INSERT INTO authentication_attempts (
                authentication_ref, session_ref, claimed_identity_ref, verified_identity_ref,
                correlation_id, outcome, rationale, created_at
            ) VALUES (
                :authentication_ref, :session_ref, :claimed_identity_ref, :verified_identity_ref,
                :correlation_id, :outcome, :rationale, :created_at
            )'
        );
        $statement->execute([
            'authentication_ref' => $authenticationRef,
            'session_ref' => $sessionRef,
            'claimed_identity_ref' => $claimedIdentityRef,
            'verified_identity_ref' => $authenticated ? $identityRef : null,
            'correlation_id' => $correlationId,
            'outcome' => $authenticated ? 'SUCCESS' : 'FAILURE',
            'rationale' => $rationale,
            'created_at' => gmdate(DATE_RFC3339),
        ]);

        return $record;
    }

    private function migrate(): void
    {
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS authentication_sessions (
                session_ref TEXT PRIMARY KEY,
                token_hash TEXT NOT NULL UNIQUE,
                identity_ref TEXT NOT NULL,
                credential_verification_ref TEXT NOT NULL,
                expires_at TEXT NOT NULL,
                revoked_at TEXT,
                created_at TEXT NOT NULL
            )'
        );
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS authentication_attempts (
                authentication_ref TEXT PRIMARY KEY,
                session_ref TEXT,
                claimed_identity_ref TEXT,
                verified_identity_ref TEXT,
                correlation_id TEXT NOT NULL,
                outcome TEXT NOT NULL,
                rationale TEXT NOT NULL,
                created_at TEXT NOT NULL
            )'
        );
    }

    private function session(string $tokenHash): ?array
    {
        $statement = $this->database->prepare(
            'SELECT * FROM authentication_sessions WHERE token_hash = :token_hash'
        );
        $statement->execute(['token_hash' => $tokenHash]);
        $session = $statement->fetch();

        return is_array($session) ? $session : null;
    }
}
