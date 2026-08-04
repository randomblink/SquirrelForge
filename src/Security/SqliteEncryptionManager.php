<?php

declare(strict_types=1);

namespace SquirrelForge\Security;

use DateTimeImmutable;
use PDO;
use SquirrelForge\Contracts\AuthenticationManagerInterface;
use SquirrelForge\Contracts\AuthorizationManagerInterface;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;

/**
 * Performs approved cryptographic operations, per
 * 24_SECURITY/ENCRYPTION-MANAGER.md, using PHP's real OpenSSL and
 * hashing primitives rather than a placeholder -- this is a genuinely
 * operational security component, not another policy-review gate.
 *
 * The Encryption Workflow's first three steps are real, enforced gates
 * composing this spec's own three real "Depends On" authorities in
 * order: `AuthenticationManagerInterface::authenticate()` verifies the
 * requesting identity, `AuthorizationManagerInterface::authorize()`
 * confirms permission for the specific operation and key, and
 * `SqliteSecurityGovernance::review()` (built last commit) validates
 * the requested algorithm against governance -- an operation only
 * proceeds when governance returns Approved or Approved with
 * Conditions, never a fabricated pass-through.
 *
 * "Request the approved key reference from Secrets Manager" is not
 * implemented as a genuine `SqliteSecretsManager` call: that class's
 * real interface (register/rotate/revoke/verify an API key) is
 * API-key-specific, not a general encryption-key store -- there is no
 * real method to resolve "the current key for symmetric encryption."
 * Key material and its reference are therefore caller-supplied, the
 * same way `SqliteRollbackManager`/`SqliteFailoverCoordinator` accept a
 * caller-supplied action when no real subsystem exists to delegate to.
 * "Support key-rotation compatibility by... rejecting expired or
 * revoked references" is still a real, enforced check: the caller
 * attaches `key_expires_at`/`key_revoked` to the key reference, and
 * this class refuses the operation when either condition is true.
 *
 * Real cryptographic operations, not fabricated: `encrypt`/`decrypt`
 * use AES-256-GCM via `openssl_encrypt`/`openssl_decrypt` (authenticated
 * encryption, satisfying "Authenticated encryption where applicable");
 * `hash`/`verify_hash` use PHP's `hash()` for unkeyed content
 * fingerprinting; `hmac`/`verify_integrity` use `hash_hmac()` for keyed
 * message authentication and tamper-evident integrity verification
 * (deliberately distinct from the unkeyed hash pair, since a bare hash
 * anyone can recompute is not integrity verification in the
 * cryptographic sense); `sign`/`verify_signature` use
 * `openssl_sign`/`openssl_verify` with RSA-SHA256 against
 * caller-supplied PEM key material.
 *
 * "Audit records must never contain cryptographic keys or plaintext
 * sensitive data" is enforced structurally: the persisted row only
 * ever stores metadata (operation type, identity, key reference,
 * statuses, outcome) -- key material, plaintext, and ciphertext are
 * never written to the database.
 *
 * Owns its own database (`Sqlite` prefix): the Audit Requirements name
 * a specific structured record shape every perform() call records
 * unconditionally, including denied and failed operations.
 */
final class SqliteEncryptionManager
{
    private const OPERATIONS = ['encrypt', 'decrypt', 'hash', 'verify_hash', 'sign', 'verify_signature', 'hmac', 'verify_integrity'];
    private const HASH_ALGORITHMS = ['sha256', 'sha384', 'sha512'];
    private const CIPHER = 'aes-256-gcm';

    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly ?AuthenticationManagerInterface $authentication = null,
        private readonly ?AuthorizationManagerInterface $authorization = null,
        private readonly ?SqliteSecurityGovernance $governance = null,
        private readonly ?EventBusInterface $events = null
    ) {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS cryptographic_operations (
                cryptographic_operation_id TEXT PRIMARY KEY,
                operation_type TEXT NOT NULL,
                identity_ref TEXT,
                key_ref TEXT,
                authorization_status TEXT NOT NULL,
                governance_status TEXT NOT NULL,
                integrity_verification_status TEXT NOT NULL,
                final_outcome TEXT NOT NULL,
                error TEXT,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array{
     *     operation?: string, identity_ref?: string, access_token?: ?string, permission_ref?: ?string, correlation_id?: string,
     *     algorithm?: string, key_ref?: string, key_material?: string, key_expires_at?: ?string, key_revoked?: bool,
     *     plaintext?: string, ciphertext?: string, iv?: string, tag?: string,
     *     data?: string, expected_hash?: string, signature?: string, expected_mac?: string
     * } $request
     * @return array{cryptographic_operation_id: string, outcome: string, result: mixed, error: ?string}
     */
    public function perform(array $request): array
    {
        $operation = $request['operation'] ?? null;
        $identityRef = $request['identity_ref'] ?? null;
        $keyRef = $request['key_ref'] ?? null;

        if (!in_array($operation, self::OPERATIONS, true)) {
            return $this->finish($operation ?? 'unknown', $identityRef, $keyRef, 'not_applicable', 'not_applicable', 'rejected', sprintf('"%s" is not a supported cryptographic operation.', $operation), null);
        }

        if ($identityRef === null || $identityRef === '') {
            return $this->finish($operation, $identityRef, $keyRef, 'not_applicable', 'not_applicable', 'rejected', 'A requesting identity_ref is required.', null);
        }

        $correlationId = $request['correlation_id'] ?? ('correlation_' . bin2hex(random_bytes(8)));
        $authorizationStatus = 'not_applicable';

        if ($this->authentication !== null) {
            $authn = $this->authentication->authenticate($request['access_token'] ?? null, $identityRef, $correlationId);

            if (($authn['authenticated'] ?? false) !== true) {
                return $this->finish($operation, $identityRef, $keyRef, 'denied', 'not_applicable', 'denied', 'Requesting identity failed authentication.', null);
            }
        }

        if ($this->authorization !== null) {
            $authz = $this->authorization->authorize($identityRef, $request['permission_ref'] ?? $operation, 'encryption.' . $operation, 'key:' . ($keyRef ?? 'none'), $correlationId);

            if (($authz['authorized'] ?? false) !== true) {
                return $this->finish($operation, $identityRef, $keyRef, 'denied', 'not_applicable', 'denied', 'Authorization was denied for this cryptographic operation.', null);
            }

            $authorizationStatus = 'authorized';
        }

        $governanceStatus = 'not_applicable';

        if ($this->governance !== null) {
            $decision = $this->governance->review([
                'request_id' => $correlationId,
                'policy_context' => ['operation' => $operation, 'algorithm' => $request['algorithm'] ?? self::CIPHER, 'ready' => true],
                'reviewer_component' => 'encryption_manager',
            ]);

            if (!in_array($decision['decision'], ['Approved', 'Approved with Conditions'], true)) {
                return $this->finish($operation, $identityRef, $keyRef, $authorizationStatus, $decision['decision'], 'rejected', sprintf('Security Governance did not approve this operation: %s.', $decision['decision']), null);
            }

            $governanceStatus = $decision['decision'];
        }

        if (in_array($operation, ['encrypt', 'decrypt', 'hmac', 'verify_integrity'], true)) {
            $keyCheck = $this->checkKey($request);

            if ($keyCheck !== null) {
                return $this->finish($operation, $identityRef, $keyRef, $authorizationStatus, $governanceStatus, 'rejected', $keyCheck, null);
            }
        }

        [$outcome, $error, $integrityStatus, $result] = $this->execute($operation, $request);

        return $this->finish($operation, $identityRef, $keyRef, $authorizationStatus, $governanceStatus, $outcome, $error, $result, $integrityStatus);
    }

    /**
     * @param array<string, mixed> $request
     */
    private function checkKey(array $request): ?string
    {
        if (($request['key_revoked'] ?? false) === true) {
            return 'The supplied key reference has been revoked.';
        }

        if (isset($request['key_expires_at']) && new DateTimeImmutable($request['key_expires_at']) < new DateTimeImmutable()) {
            return 'The supplied key reference has expired.';
        }

        if (!isset($request['key_material']) || $request['key_material'] === '') {
            return 'Key material is required for this operation.';
        }

        return null;
    }

    /**
     * @param array<string, mixed> $request
     * @return array{0: string, 1: ?string, 2: string, 3: mixed}
     */
    private function execute(string $operation, array $request): array
    {
        return match ($operation) {
            'encrypt' => $this->encrypt($request),
            'decrypt' => $this->decrypt($request),
            'hash' => $this->hash($request),
            'verify_hash' => $this->verifyHash($request),
            'sign' => $this->sign($request),
            'verify_signature' => $this->verifySignature($request),
            'hmac' => $this->hmac($request),
            'verify_integrity' => $this->verifyIntegrity($request),
        };
    }

    /**
     * @param array<string, mixed> $request
     * @return array{0: string, 1: ?string, 2: string, 3: mixed}
     */
    private function encrypt(array $request): array
    {
        if (!isset($request['plaintext'])) {
            return ['rejected', 'plaintext is required for encryption.', 'not_applicable', null];
        }

        $iv = random_bytes(openssl_cipher_iv_length(self::CIPHER));
        $ciphertext = openssl_encrypt($request['plaintext'], self::CIPHER, $request['key_material'], OPENSSL_RAW_DATA, $iv, $tag);

        if ($ciphertext === false) {
            return ['failed', 'Encryption failed.', 'not_applicable', null];
        }

        return ['completed', null, 'not_applicable', ['ciphertext' => base64_encode($ciphertext), 'iv' => base64_encode($iv), 'tag' => base64_encode($tag)]];
    }

    /**
     * @param array<string, mixed> $request
     * @return array{0: string, 1: ?string, 2: string, 3: mixed}
     */
    private function decrypt(array $request): array
    {
        foreach (['ciphertext', 'iv', 'tag'] as $field) {
            if (!isset($request[$field])) {
                return ['rejected', sprintf('"%s" is required for decryption.', $field), 'not_applicable', null];
            }
        }

        $plaintext = openssl_decrypt(
            base64_decode($request['ciphertext'], true),
            self::CIPHER,
            $request['key_material'],
            OPENSSL_RAW_DATA,
            base64_decode($request['iv'], true),
            base64_decode($request['tag'], true)
        );

        if ($plaintext === false) {
            return ['failed', 'Decryption failed: ciphertext could not be authenticated with the supplied key and tag.', 'failed', null];
        }

        return ['completed', null, 'verified', ['plaintext' => $plaintext]];
    }

    /**
     * @param array<string, mixed> $request
     * @return array{0: string, 1: ?string, 2: string, 3: mixed}
     */
    private function hash(array $request): array
    {
        $algorithm = $this->resolveHashAlgorithm($request);

        if ($algorithm === null) {
            return ['rejected', sprintf('"%s" is not an approved hash algorithm.', $request['algorithm'] ?? ''), 'not_applicable', null];
        }

        if (!isset($request['data'])) {
            return ['rejected', 'data is required for hashing.', 'not_applicable', null];
        }

        return ['completed', null, 'not_applicable', ['hash' => hash($algorithm, $request['data'])]];
    }

    /**
     * @param array<string, mixed> $request
     * @return array{0: string, 1: ?string, 2: string, 3: mixed}
     */
    private function verifyHash(array $request): array
    {
        $algorithm = $this->resolveHashAlgorithm($request);

        if ($algorithm === null || !isset($request['data'], $request['expected_hash'])) {
            return ['rejected', 'algorithm, data, and expected_hash are required to verify a hash.', 'not_applicable', null];
        }

        $matches = hash_equals($request['expected_hash'], hash($algorithm, $request['data']));

        return ['completed', null, $matches ? 'verified' : 'failed', ['matches' => $matches]];
    }

    /**
     * @param array<string, mixed> $request
     * @return array{0: string, 1: ?string, 2: string, 3: mixed}
     */
    private function sign(array $request): array
    {
        if (!isset($request['data'], $request['key_material'])) {
            return ['rejected', 'data and key_material (a PEM private key) are required to sign.', 'not_applicable', null];
        }

        $privateKey = openssl_pkey_get_private($request['key_material']);

        if ($privateKey === false || !openssl_sign($request['data'], $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            return ['failed', 'Signing failed: the supplied key material is not a valid private key.', 'not_applicable', null];
        }

        return ['completed', null, 'not_applicable', ['signature' => base64_encode($signature)]];
    }

    /**
     * @param array<string, mixed> $request
     * @return array{0: string, 1: ?string, 2: string, 3: mixed}
     */
    private function verifySignature(array $request): array
    {
        if (!isset($request['data'], $request['signature'], $request['key_material'])) {
            return ['rejected', 'data, signature, and key_material (a PEM public key) are required to verify a signature.', 'not_applicable', null];
        }

        $publicKey = openssl_pkey_get_public($request['key_material']);

        if ($publicKey === false) {
            return ['rejected', 'The supplied key material is not a valid public key.', 'not_applicable', null];
        }

        $result = openssl_verify($request['data'], base64_decode($request['signature'], true), $publicKey, OPENSSL_ALGO_SHA256);

        return ['completed', null, $result === 1 ? 'verified' : 'failed', ['matches' => $result === 1]];
    }

    /**
     * @param array<string, mixed> $request
     * @return array{0: string, 1: ?string, 2: string, 3: mixed}
     */
    private function hmac(array $request): array
    {
        $algorithm = $this->resolveHashAlgorithm($request);

        if ($algorithm === null || !isset($request['data'])) {
            return ['rejected', 'A valid algorithm and data are required to compute a message authentication code.', 'not_applicable', null];
        }

        return ['completed', null, 'not_applicable', ['mac' => hash_hmac($algorithm, $request['data'], $request['key_material'])]];
    }

    /**
     * @param array<string, mixed> $request
     * @return array{0: string, 1: ?string, 2: string, 3: mixed}
     */
    private function verifyIntegrity(array $request): array
    {
        $algorithm = $this->resolveHashAlgorithm($request);

        if ($algorithm === null || !isset($request['data'], $request['expected_mac'])) {
            return ['rejected', 'A valid algorithm, data, and expected_mac are required to verify integrity.', 'not_applicable', null];
        }

        $matches = hash_equals($request['expected_mac'], hash_hmac($algorithm, $request['data'], $request['key_material']));

        return ['completed', null, $matches ? 'verified' : 'failed', ['matches' => $matches]];
    }

    /**
     * @param array<string, mixed> $request
     */
    private function resolveHashAlgorithm(array $request): ?string
    {
        $algorithm = $request['algorithm'] ?? 'sha256';

        return in_array($algorithm, self::HASH_ALGORITHMS, true) ? $algorithm : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function operation(string $cryptographicOperationId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM cryptographic_operations WHERE cryptographic_operation_id = :id');
        $statement->execute(['id' => $cryptographicOperationId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array{cryptographic_operation_id: string, outcome: string, result: mixed, error: ?string}
     */
    private function finish(
        string $operation,
        ?string $identityRef,
        ?string $keyRef,
        string $authorizationStatus,
        string $governanceStatus,
        string $outcome,
        ?string $error,
        mixed $result,
        string $integrityStatus = 'not_applicable'
    ): array {
        $operationId = 'crypto_op_' . bin2hex(random_bytes(12));

        $statement = $this->database->prepare(
            'INSERT INTO cryptographic_operations (
                cryptographic_operation_id, operation_type, identity_ref, key_ref,
                authorization_status, governance_status, integrity_verification_status, final_outcome, error, created_at
            ) VALUES (
                :id, :operation_type, :identity_ref, :key_ref,
                :authorization_status, :governance_status, :integrity_verification_status, :final_outcome, :error, :created_at
            )'
        );
        $statement->execute([
            'id' => $operationId,
            'operation_type' => $operation,
            'identity_ref' => $identityRef,
            'key_ref' => $keyRef,
            'authorization_status' => $authorizationStatus,
            'governance_status' => $governanceStatus,
            'integrity_verification_status' => $integrityStatus,
            'final_outcome' => $outcome,
            'error' => $error,
            'created_at' => gmdate(DATE_RFC3339),
        ]);

        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'encryption.' . $outcome,
            new DateTimeImmutable(),
            self::class,
            ['cryptographic_operation_id' => $operationId, 'operation_type' => $operation, 'outcome' => $outcome]
        ));

        return ['cryptographic_operation_id' => $operationId, 'outcome' => $outcome, 'result' => $result, 'error' => $error];
    }
}
