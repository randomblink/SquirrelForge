<?php

declare(strict_types=1);

namespace SquirrelForge\CredentialProvider;

use DateTimeImmutable;
use SquirrelForge\RuntimeConfig\SqliteSecretsManager;
use SquirrelForge\Security\SqliteSecurityEventSink;
use Throwable;

/**
 * Implements the exact HTTP contract documented in
 * `deploy/CREDENTIAL-PROVIDER-CONTRACT.md` -- the seven endpoints
 * `HttpCredentialProvider`/`ResilientHttpCredentialProvider` call as
 * SquirrelForge's own client.
 *
 * Deliberately composes already-real, already-tested components
 * rather than reimplementing their logic: secrets register/verify/
 * rotate/revoke is the real `SqliteSecretsManager` (the same class
 * used as the `local` in-process provider -- its `password_hash()`/
 * `password_verify()` design is correct and sufficient for
 * verify-only API-key material, so nothing about it needs to change
 * to serve as this standalone service's own storage engine), security
 * events is the real `SqliteSecurityEventSink`, and MFA is the new
 * `MfaSecretStore` (the one piece with no existing implementation,
 * since TOTP genuinely needs reversible storage unlike API keys).
 *
 * This class is transport-agnostic on purpose: `handle()` takes plain
 * PHP values and returns `[httpStatus, responseBody]`, never touching
 * superglobals or emitting output directly -- `public/credential-provider.php`
 * is the only place that touches the actual HTTP transport, and this
 * class is fully testable without one.
 *
 * The Bearer token check uses `hash_equals()`, the same constant-time
 * discipline every other credential comparison in this codebase
 * already applies -- and runs before any request body is even parsed,
 * so an unauthenticated caller learns nothing about route validity.
 */
final class CredentialProviderRouter
{
    public function __construct(
        private readonly SqliteSecretsManager $secrets,
        private readonly MfaSecretStore $mfa,
        private readonly SqliteSecurityEventSink $events,
        private readonly string $bearerToken
    ) {
    }

    /**
     * @param array<string, string> $headers
     * @return array{0: int, 1: array<string, mixed>}
     */
    public function handle(string $method, string $path, array $headers, ?string $body): array
    {
        $unauthorized = $this->checkAuthorization($headers);

        if ($unauthorized !== null) {
            return $unauthorized;
        }

        if ($method === 'GET' && $path === '/v1/provider/health') {
            return [200, ['healthy' => true]];
        }

        if ($method !== 'POST') {
            return [405, ['error' => 'method_not_allowed']];
        }

        $payload = $this->decode($body);

        return match ($path) {
            '/v1/provider/secrets/register' => $this->register($payload),
            '/v1/provider/secrets/verify' => $this->verifySecret($payload),
            '/v1/provider/secrets/rotate' => $this->rotate($payload),
            '/v1/provider/secrets/revoke' => $this->revoke($payload),
            '/v1/provider/mfa/verify' => $this->mfaVerify($payload),
            '/v1/provider/security-events' => $this->recordEvent($payload),
            default => [404, ['error' => 'unknown_route']],
        };
    }

    /**
     * @param array<string, string> $headers
     * @return ?array{0: int, 1: array<string, mixed>}
     */
    private function checkAuthorization(array $headers): ?array
    {
        $normalized = array_change_key_case($headers, CASE_LOWER);
        $presented = $normalized['authorization'] ?? '';
        $expected = 'Bearer ' . $this->bearerToken;

        return hash_equals($expected, $presented) ? null : [401, ['error' => 'unauthorized']];
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(?string $body): array
    {
        if ($body === null || trim($body) === '') {
            return [];
        }

        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{0: int, 1: array<string, mixed>}
     */
    private function register(array $payload): array
    {
        if (!is_string($payload['identity_ref'] ?? null) || !is_string($payload['api_key'] ?? null)) {
            return [422, ['error' => 'invalid_request']];
        }

        try {
            $secretRef = $this->secrets->registerApiKey(
                $payload['identity_ref'],
                $payload['api_key'],
                $this->parseExpiry($payload)
            );

            return [200, ['secret_ref' => $secretRef]];
        } catch (Throwable) {
            return [422, ['error' => 'registration_failed']];
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{0: int, 1: array<string, mixed>}
     */
    private function verifySecret(array $payload): array
    {
        if (!is_string($payload['identity_ref'] ?? null) || !is_string($payload['api_key'] ?? null)) {
            return [422, ['error' => 'invalid_request']];
        }

        return [200, $this->secrets->verifyApiKey($payload['identity_ref'], $payload['api_key'])];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{0: int, 1: array<string, mixed>}
     */
    private function rotate(array $payload): array
    {
        if (!is_string($payload['secret_ref'] ?? null) || !is_string($payload['new_api_key'] ?? null)) {
            return [422, ['error' => 'invalid_request']];
        }

        try {
            $newRef = $this->secrets->rotateApiKey(
                $payload['secret_ref'],
                $payload['new_api_key'],
                $this->parseExpiry($payload)
            );

            return [200, ['secret_ref' => $newRef]];
        } catch (Throwable) {
            return [422, ['error' => 'rotation_failed']];
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{0: int, 1: array<string, mixed>}
     */
    private function revoke(array $payload): array
    {
        if (!is_string($payload['secret_ref'] ?? null)) {
            return [422, ['error' => 'invalid_request']];
        }

        $this->secrets->revoke($payload['secret_ref']);

        return [200, ['revoked' => true]];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{0: int, 1: array<string, mixed>}
     */
    private function mfaVerify(array $payload): array
    {
        if (!is_string($payload['identity_ref'] ?? null)) {
            return [422, ['error' => 'invalid_request']];
        }

        $proof = is_string($payload['proof'] ?? null) ? $payload['proof'] : null;
        $verified = $this->mfa->verify($payload['identity_ref'], $proof);

        return [200, [
            'verified' => $verified,
            'verification_ref' => $verified ? 'mfa_verification_' . bin2hex(random_bytes(12)) : null,
            'rationale' => $verified ? 'The proof matched.' : 'The proof did not match, or the identity is not enrolled.',
        ]];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{0: int, 1: array<string, mixed>}
     */
    private function recordEvent(array $payload): array
    {
        if (!is_string($payload['event_type'] ?? null) || !is_string($payload['outcome'] ?? null) || !is_string($payload['correlation_id'] ?? null)) {
            return [422, ['error' => 'invalid_request']];
        }

        $identityRef = is_string($payload['identity_ref'] ?? null) ? $payload['identity_ref'] : null;
        $metadata = is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [];

        $eventRef = $this->events->record($payload['event_type'], $payload['outcome'], $identityRef, $payload['correlation_id'], $metadata);

        return [200, ['security_event_ref' => $eventRef]];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function parseExpiry(array $payload): ?DateTimeImmutable
    {
        return is_string($payload['expires_at'] ?? null) ? new DateTimeImmutable($payload['expires_at']) : null;
    }
}
