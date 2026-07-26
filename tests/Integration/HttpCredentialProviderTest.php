<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Integration;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Integration\Http\HttpTransportResponse;
use SquirrelForge\RuntimeConfig\HttpCredentialProvider;
use SquirrelForge\Tests\Support\FakeHttpTransport;

final class HttpCredentialProviderTest extends TestCase
{
    public function testAdapterMapsAllProviderOperationsAndKeepsTokenInAuthorizationHeader(): void
    {
        $responses = [
            '/v1/provider/secrets/register' => ['secret_ref' => 'secret_1'],
            '/v1/provider/secrets/verify' => [
                'verified' => true,
                'secret_ref' => 'secret_1',
                'verification_ref' => 'verification_1',
                'rationale' => 'verified',
            ],
            '/v1/provider/secrets/rotate' => ['secret_ref' => 'secret_2'],
            '/v1/provider/secrets/revoke' => ['revoked' => true],
            '/v1/provider/mfa/verify' => [
                'verified' => true,
                'verification_ref' => 'mfa_1',
                'rationale' => 'verified',
            ],
            '/v1/provider/security-events' => ['security_event_ref' => 'event_1'],
        ];
        $transport = new FakeHttpTransport(
            static function (array $request) use ($responses): HttpTransportResponse {
                $path = parse_url($request['url'], PHP_URL_PATH);

                return new HttpTransportResponse(
                    isset($responses[$path]) ? 200 : 404,
                    ['content-type' => 'application/json'],
                    json_encode($responses[$path] ?? ['error' => 'missing'], JSON_THROW_ON_ERROR)
                );
            }
        );
        $token = str_repeat('provider-token-', 4);
        $provider = new HttpCredentialProvider(
            'https://credentials.example.test',
            $token,
            $transport
        );
        $apiKey = 'api_key_' . bin2hex(random_bytes(24));
        $replacement = 'replacement_' . bin2hex(random_bytes(24));

        $secretRef = $provider->registerApiKey('identity_1', $apiKey);
        $verification = $provider->verifyApiKey('identity_1', $apiKey);
        $replacementRef = $provider->rotateApiKey($secretRef, $replacement);
        $provider->revoke($replacementRef);
        $mfa = $provider->verify('identity_1', 'proof_1', 'correlation_1');
        $eventRef = $provider->record(
            'AUTHENTICATION',
            'SUCCESS',
            'identity_1',
            'correlation_1'
        );

        $this->assertSame('secret_1', $secretRef);
        $this->assertTrue($verification['verified']);
        $this->assertSame('secret_2', $replacementRef);
        $this->assertTrue($mfa['verified']);
        $this->assertSame('event_1', $eventRef);
        $this->assertCount(6, $transport->requests);

        foreach ($transport->requests as $request) {
            $this->assertSame('Bearer ' . $token, $request['headers']['Authorization']);
            $this->assertStringNotContainsString($token, (string) $request['body']);
        }
    }

    public function testAdapterRejectsProviderErrorsWithoutReturningRemoteBody(): void
    {
        $provider = new HttpCredentialProvider(
            'https://credentials.example.test',
            str_repeat('t', 40),
            new FakeHttpTransport(
                static fn(array $request): HttpTransportResponse =>
                    new HttpTransportResponse(503, [], '{"internal":"sensitive"}')
            )
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('rejected the operation');
        $provider->verifyApiKey('identity_1', str_repeat('k', 32));
    }
}
