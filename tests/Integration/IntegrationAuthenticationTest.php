<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Integration;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;
use SquirrelForge\Integration\IntegrationAuthentication;
use RuntimeException;

final class IntegrationAuthenticationTest extends TestCase
{
    public function testMissingCredentialReferenceIsBlocked(): void
    {
        $auth = new IntegrationAuthentication();

        $result = $auth->authenticate('provider_1', ['credential_ref' => null]);

        $this->assertSame('blocked', $result['status']);
        $this->assertNull($result['token']);
        $this->assertStringContainsString('credential reference', $result['error']);
    }

    public function testEmptyCredentialReferenceIsBlocked(): void
    {
        $auth = new IntegrationAuthentication();

        $result = $auth->authenticate('provider_1', ['credential_ref' => '']);

        $this->assertSame('blocked', $result['status']);
    }

    public function testExplicitlyUnauthorizedRequestIsBlocked(): void
    {
        $auth = new IntegrationAuthentication();

        $result = $auth->authenticate('provider_1', ['credential_ref' => 'cred_1', 'authorized' => false]);

        $this->assertSame('blocked', $result['status']);
        $this->assertStringContainsString('authorization', $result['error']);
    }

    public function testAuthorizationDefaultsToTrueWhenOmitted(): void
    {
        $auth = new IntegrationAuthentication();

        $result = $auth->authenticate('provider_1', ['credential_ref' => 'cred_1']);

        $this->assertSame('ready', $result['status']);
    }

    public function testReadyWithoutAHandshakeNeverFabricatesAnOutcome(): void
    {
        $auth = new IntegrationAuthentication();

        $result = $auth->authenticate('provider_1', ['credential_ref' => 'cred_1'], null);

        $this->assertSame('ready', $result['status']);
        $this->assertNull($result['token']);
        $this->assertNull($result['error']);
    }

    public function testSuccessfulHandshakeReturnsValidWithToken(): void
    {
        $auth = new IntegrationAuthentication();

        $result = $auth->authenticate(
            'provider_1',
            ['credential_ref' => 'cred_1'],
            static fn(array $refs): array => ['token' => 'tok_abc', 'expires_at' => null, 'error' => null]
        );

        $this->assertSame('valid', $result['status']);
        $this->assertSame('tok_abc', $result['token']);
    }

    public function testHandshakeReturningAFutureExpiryIsValid(): void
    {
        $auth = new IntegrationAuthentication();
        $future = gmdate(DATE_RFC3339, time() + 3600);

        $result = $auth->authenticate(
            'provider_1',
            ['credential_ref' => 'cred_1'],
            static fn(array $refs): array => ['token' => 'tok_abc', 'expires_at' => $future, 'error' => null]
        );

        $this->assertSame('valid', $result['status']);
        $this->assertSame($future, $result['expires_at']);
    }

    public function testHandshakeReturningAPastExpiryIsExpired(): void
    {
        $auth = new IntegrationAuthentication();
        $past = gmdate(DATE_RFC3339, time() - 3600);

        $result = $auth->authenticate(
            'provider_1',
            ['credential_ref' => 'cred_1'],
            static fn(array $refs): array => ['token' => 'tok_abc', 'expires_at' => $past, 'error' => null]
        );

        $this->assertSame('expired', $result['status']);
        $this->assertSame('tok_abc', $result['token']);
    }

    public function testHandshakeReturningAnErrorIsInvalid(): void
    {
        $auth = new IntegrationAuthentication();

        $result = $auth->authenticate(
            'provider_1',
            ['credential_ref' => 'cred_1'],
            static fn(array $refs): array => ['token' => null, 'expires_at' => null, 'error' => 'invalid_client']
        );

        $this->assertSame('invalid', $result['status']);
        $this->assertSame('invalid_client', $result['error']);
    }

    public function testHandshakeReturningNoTokenAndNoErrorIsStillInvalid(): void
    {
        $auth = new IntegrationAuthentication();

        $result = $auth->authenticate(
            'provider_1',
            ['credential_ref' => 'cred_1'],
            static fn(array $refs): array => ['token' => null, 'expires_at' => null, 'error' => null]
        );

        $this->assertSame('invalid', $result['status']);
        $this->assertNotNull($result['error']);
    }

    public function testHandshakeThrowingIsInvalidNotAnUncaughtException(): void
    {
        $auth = new IntegrationAuthentication();

        $result = $auth->authenticate(
            'provider_1',
            ['credential_ref' => 'cred_1'],
            static function (array $refs): array {
                throw new RuntimeException('provider unreachable');
            }
        );

        $this->assertSame('invalid', $result['status']);
        $this->assertSame('provider unreachable', $result['error']);
    }

    public function testHandshakeReceivesTheCallerSuppliedReferences(): void
    {
        $auth = new IntegrationAuthentication();
        $seen = null;

        $auth->authenticate(
            'provider_1',
            ['credential_ref' => 'cred_1', 'authorized' => true],
            function (array $refs) use (&$seen): array {
                $seen = $refs;

                return ['token' => 'tok', 'expires_at' => null, 'error' => null];
            }
        );

        $this->assertSame('cred_1', $seen['credential_ref']);
        $this->assertTrue($seen['authorized']);
    }

    public function testRefreshRunsTheSameHandshakeCoordination(): void
    {
        $auth = new IntegrationAuthentication();

        $result = $auth->refresh(
            'provider_1',
            ['credential_ref' => 'cred_1'],
            static fn(array $refs): array => ['token' => 'tok_refreshed', 'expires_at' => null, 'error' => null]
        );

        $this->assertSame('valid', $result['status']);
        $this->assertSame('tok_refreshed', $result['token']);
    }

    public function testRevokeReturnsRevokedWithNoToken(): void
    {
        $auth = new IntegrationAuthentication();

        $result = $auth->revoke('provider_1', 'compromised');

        $this->assertSame('revoked', $result['status']);
        $this->assertNull($result['token']);
        $this->assertSame('compromised', $result['error']);
    }

    public function testEventPayloadNeverExposesTheRawToken(): void
    {
        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        foreach (['integration_authentication.valid', 'integration_authentication.blocked'] as $eventName) {
            $events->listen($eventName, new CallbackEventListener(
                function (EventInterface $event) use (&$captured): void {
                    $captured[] = $event;
                }
            ));
        }

        $auth = new IntegrationAuthentication($events);
        $auth->authenticate(
            'provider_1',
            ['credential_ref' => 'cred_1'],
            static fn(array $refs): array => ['token' => 'super-secret-token', 'expires_at' => null, 'error' => null]
        );
        $auth->authenticate('provider_1', ['credential_ref' => null]);

        $this->assertCount(2, $captured);

        foreach ($captured as $event) {
            $payload = $event->getPayload();
            $this->assertArrayNotHasKey('token', $payload);
            $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
            $this->assertStringNotContainsString('super-secret-token', $encoded);
        }

        $this->assertTrue($captured[0]->getPayload()['has_token']);
        $this->assertFalse($captured[1]->getPayload()['has_token']);
    }

    public function testEventNameReflectsTheResolvedStatus(): void
    {
        $events = new EventBus();
        $captured = [];
        $events->listen('integration_authentication.revoked', new CallbackEventListener(
            function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }
        ));

        $auth = new IntegrationAuthentication($events);
        $auth->revoke('provider_1');

        $this->assertCount(1, $captured);
        $this->assertSame('provider_1', $captured[0]->getPayload()['provider_ref']);
    }

    public function testWorksWithoutAnEventBus(): void
    {
        $auth = new IntegrationAuthentication(null);

        $result = $auth->authenticate('provider_1', ['credential_ref' => 'cred_1']);

        $this->assertSame('ready', $result['status']);
    }
}
