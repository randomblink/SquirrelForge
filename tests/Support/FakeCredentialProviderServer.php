<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Support;

use SquirrelForge\Integration\Http\HttpTransportResponse;

/**
 * A stateful, in-memory test double implementing the exact wire
 * contract documented in `deploy/CREDENTIAL-PROVIDER-CONTRACT.md` --
 * unlike `FakeHttpTransport`'s usual single-canned-response-per-path
 * usage, this class genuinely tracks registered/rotated/revoked
 * secrets and recorded security events across calls, the same way a
 * real compliant provider would.
 *
 * Exists specifically to let `HttpCredentialProvider` be exercised
 * against the shared `SecretsManagerContractTestCase`/
 * `MfaVerifierContractTestCase`/`SecurityEventSinkContractTestCase`
 * abstract test cases -- the same ones the local reference providers
 * (`SqliteSecretsManager`, `StaticMfaVerifier`, `SqliteSecurityEventSink`)
 * are already conformance-tested against -- proving the HTTP client
 * genuinely preserves the same security properties (no secret leakage
 * after rotation, no proof echoed back, correlation IDs preserved) a
 * real compliant server would deliver, not just that requests are
 * shaped correctly.
 */
final class FakeCredentialProviderServer
{
    /** @var array<string, array{identity_ref: string, key_hash: string, status: string}> */
    private array $secrets = [];

    /** @var array<int, array{event_ref: string, event_type: string, outcome: string, identity_ref: ?string, correlation_id: string, metadata: array<string, mixed>}> */
    public array $securityEvents = [];

    public function __construct(private readonly string $validMfaProof)
    {
    }

    /**
     * @param array{method: string, url: string, headers: array<string, string>, body: ?string, timeout: float} $request
     */
    public function handle(array $request): HttpTransportResponse
    {
        $path = parse_url($request['url'], PHP_URL_PATH);
        $payload = $request['body'] !== null ? json_decode($request['body'], true, flags: JSON_THROW_ON_ERROR) : [];

        return match ($path) {
            '/v1/provider/secrets/register' => $this->register($payload),
            '/v1/provider/secrets/verify' => $this->verify($payload),
            '/v1/provider/secrets/rotate' => $this->rotate($payload),
            '/v1/provider/secrets/revoke' => $this->revoke($payload),
            '/v1/provider/mfa/verify' => $this->mfaVerify($payload),
            '/v1/provider/security-events' => $this->recordEvent($payload),
            default => $this->json(404, ['error' => 'unknown_path']),
        };
    }

    private function register(array $payload): HttpTransportResponse
    {
        $secretRef = 'secret_' . bin2hex(random_bytes(8));
        $this->secrets[$secretRef] = [
            'identity_ref' => $payload['identity_ref'],
            'key_hash' => password_hash($payload['api_key'], PASSWORD_DEFAULT),
            'status' => 'ACTIVE',
        ];

        return $this->json(200, ['secret_ref' => $secretRef]);
    }

    private function verify(array $payload): HttpTransportResponse
    {
        foreach ($this->secrets as $ref => $secret) {
            if ($secret['identity_ref'] !== $payload['identity_ref'] || $secret['status'] !== 'ACTIVE') {
                continue;
            }

            if (password_verify($payload['api_key'], $secret['key_hash'])) {
                return $this->json(200, [
                    'verified' => true,
                    'secret_ref' => $ref,
                    'verification_ref' => 'verification_' . bin2hex(random_bytes(8)),
                    'rationale' => 'The active secret matched.',
                ]);
            }
        }

        return $this->json(200, ['verified' => false, 'secret_ref' => null, 'verification_ref' => null, 'rationale' => 'No active secret matched.']);
    }

    private function rotate(array $payload): HttpTransportResponse
    {
        $oldRef = $payload['secret_ref'];

        if (!isset($this->secrets[$oldRef]) || $this->secrets[$oldRef]['status'] !== 'ACTIVE') {
            return $this->json(422, ['error' => 'secret_not_active']);
        }

        $identityRef = $this->secrets[$oldRef]['identity_ref'];
        $this->secrets[$oldRef]['status'] = 'ROTATED';

        $newRef = 'secret_' . bin2hex(random_bytes(8));
        $this->secrets[$newRef] = [
            'identity_ref' => $identityRef,
            'key_hash' => password_hash($payload['new_api_key'], PASSWORD_DEFAULT),
            'status' => 'ACTIVE',
        ];

        return $this->json(200, ['secret_ref' => $newRef]);
    }

    private function revoke(array $payload): HttpTransportResponse
    {
        $ref = $payload['secret_ref'];

        if (isset($this->secrets[$ref])) {
            $this->secrets[$ref]['status'] = 'REVOKED';
        }

        return $this->json(200, ['revoked' => true]);
    }

    private function mfaVerify(array $payload): HttpTransportResponse
    {
        $verified = is_string($payload['proof'] ?? null) && hash_equals($this->validMfaProof, $payload['proof']);

        return $this->json(200, [
            'verified' => $verified,
            'verification_ref' => $verified ? 'mfa_verification_' . bin2hex(random_bytes(8)) : null,
            'rationale' => $verified ? 'The proof matched.' : 'The proof did not match.',
        ]);
    }

    private function recordEvent(array $payload): HttpTransportResponse
    {
        $eventRef = 'security_event_' . bin2hex(random_bytes(8));
        $this->securityEvents[] = [
            'event_ref' => $eventRef,
            'event_type' => $payload['event_type'],
            'outcome' => $payload['outcome'],
            'identity_ref' => $payload['identity_ref'],
            'correlation_id' => $payload['correlation_id'],
            'metadata' => $payload['metadata'] ?? [],
        ];

        return $this->json(200, ['security_event_ref' => $eventRef]);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function json(int $status, array $body): HttpTransportResponse
    {
        return new HttpTransportResponse($status, ['content-type' => 'application/json'], json_encode($body, JSON_THROW_ON_ERROR));
    }
}
