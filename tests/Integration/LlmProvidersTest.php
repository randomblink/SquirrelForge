<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;
use SquirrelForge\Integration\IntegrationAuthentication;
use SquirrelForge\Integration\LlmProviders;

final class LlmProvidersTest extends TestCase
{
    // --- Registration ---

    public function testRegisterRequiresProviderId(): void
    {
        $providers = new LlmProviders();

        $result = $providers->register(['provider_name' => 'Anthropic', 'endpoint_ref' => 'https://api.example.com']);

        $this->assertSame('invalid', $result['outcome']);
        $this->assertStringContainsString('provider_id', $result['error']);
    }

    public function testRegisterRequiresNameAndEndpoint(): void
    {
        $providers = new LlmProviders();

        $result = $providers->register(['provider_id' => 'anthropic']);

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testRegisterSucceedsAndIsRetrievable(): void
    {
        $providers = new LlmProviders();

        $result = $providers->register([
            'provider_id' => 'anthropic',
            'provider_name' => 'Anthropic',
            'endpoint_ref' => 'https://api.anthropic.com',
            'supported_models' => ['claude-sonnet-5'],
        ]);

        $this->assertSame('registered', $result['outcome']);
        $this->assertSame('Anthropic', $providers->get('anthropic')['provider_name']);
        $this->assertCount(1, $providers->list());
    }

    public function testGetUnknownProviderReturnsNull(): void
    {
        $providers = new LlmProviders();

        $this->assertNull($providers->get('nope'));
    }

    // --- call(): configuration checks ---

    public function testCallUnregisteredProviderIsConfigurationInvalid(): void
    {
        $providers = new LlmProviders();

        $result = $providers->call('anthropic');

        $this->assertSame('Configuration Invalid', $result['status']);
        $this->assertStringContainsString('not registered', $result['error']);
    }

    public function testCallUnsupportedModelIsConfigurationInvalid(): void
    {
        $providers = new LlmProviders();
        $providers->register([
            'provider_id' => 'anthropic',
            'provider_name' => 'Anthropic',
            'endpoint_ref' => 'https://api.anthropic.com',
            'supported_models' => ['claude-sonnet-5'],
        ]);

        $result = $providers->call('anthropic', ['model' => 'gpt-4']);

        $this->assertSame('Configuration Invalid', $result['status']);
        $this->assertStringContainsString('supported_models', $result['error']);
    }

    public function testCallWithEmptySupportedModelsAllowsAnyModel(): void
    {
        $providers = new LlmProviders();
        $providers->register(['provider_id' => 'anthropic', 'provider_name' => 'Anthropic', 'endpoint_ref' => 'https://api.anthropic.com']);

        $result = $providers->call('anthropic', ['model' => 'anything']);

        $this->assertSame('Available', $result['status']);
    }

    // --- call(): dry run ---

    public function testCallWithoutAClientIsADryRun(): void
    {
        $providers = new LlmProviders();
        $providers->register(['provider_id' => 'anthropic', 'provider_name' => 'Anthropic', 'endpoint_ref' => 'https://api.anthropic.com']);

        $result = $providers->call('anthropic');

        $this->assertSame('Available', $result['status']);
        $this->assertNull($result['response']);
        $this->assertNull($result['error']);
    }

    // --- call(): credential handshake ---

    public function testCallRequiringCredentialWithoutAuthenticationComponentFails(): void
    {
        $providers = new LlmProviders();
        $providers->register([
            'provider_id' => 'anthropic',
            'provider_name' => 'Anthropic',
            'endpoint_ref' => 'https://api.anthropic.com',
            'credential_ref' => 'cred_1',
        ]);

        $result = $providers->call('anthropic');

        $this->assertSame('Authentication Failed', $result['status']);
        $this->assertStringContainsString('IntegrationAuthentication', $result['error']);
    }

    public function testCallWithFailedHandshakeIsAuthenticationFailed(): void
    {
        $providers = new LlmProviders(new IntegrationAuthentication());
        $providers->register([
            'provider_id' => 'anthropic',
            'provider_name' => 'Anthropic',
            'endpoint_ref' => 'https://api.anthropic.com',
            'credential_ref' => 'cred_1',
        ]);

        $result = $providers->call(
            'anthropic',
            [],
            null,
            signHandshake: static fn(array $refs): array => ['token' => null, 'expires_at' => null, 'error' => 'invalid_client']
        );

        $this->assertSame('Authentication Failed', $result['status']);
        $this->assertSame('invalid_client', $result['error']);
    }

    public function testCallWithValidHandshakeReachesTheClient(): void
    {
        $providers = new LlmProviders(new IntegrationAuthentication());
        $providers->register([
            'provider_id' => 'anthropic',
            'provider_name' => 'Anthropic',
            'endpoint_ref' => 'https://api.anthropic.com',
            'credential_ref' => 'cred_1',
        ]);

        $result = $providers->call(
            'anthropic',
            [],
            client: static fn(array $provider, array $request): array => ['response' => 'hi', 'status' => 'Available'],
            signHandshake: static fn(array $refs): array => ['token' => 'tok_abc', 'expires_at' => null, 'error' => null]
        );

        $this->assertSame('Available', $result['status']);
        $this->assertSame('hi', $result['response']);
    }

    // --- call(): client outcome normalization ---

    public function testClientReportedStatusIsRespected(): void
    {
        $providers = new LlmProviders();
        $providers->register(['provider_id' => 'anthropic', 'provider_name' => 'Anthropic', 'endpoint_ref' => 'https://api.anthropic.com']);

        $result = $providers->call('anthropic', [], client: static fn(array $p, array $r): array => ['status' => 'Rate Limited', 'error' => 'slow down']);

        $this->assertSame('Rate Limited', $result['status']);
        $this->assertSame('slow down', $result['error']);
    }

    public function testUnrecognizedStatusWithErrorFallsBackToUnavailable(): void
    {
        $providers = new LlmProviders();
        $providers->register(['provider_id' => 'anthropic', 'provider_name' => 'Anthropic', 'endpoint_ref' => 'https://api.anthropic.com']);

        $result = $providers->call('anthropic', [], client: static fn(array $p, array $r): array => ['status' => 'made_up', 'error' => 'oops']);

        $this->assertSame('Unavailable', $result['status']);
    }

    public function testMissingStatusWithNoErrorNeverFabricatesAvailable(): void
    {
        $providers = new LlmProviders();
        $providers->register(['provider_id' => 'anthropic', 'provider_name' => 'Anthropic', 'endpoint_ref' => 'https://api.anthropic.com']);

        $result = $providers->call('anthropic', [], client: static fn(array $p, array $r): array => ['response' => 'hi']);

        $this->assertSame('Available', $result['status']);
    }

    public function testUsageMetadataIsReturned(): void
    {
        $providers = new LlmProviders();
        $providers->register(['provider_id' => 'anthropic', 'provider_name' => 'Anthropic', 'endpoint_ref' => 'https://api.anthropic.com']);

        $result = $providers->call('anthropic', [], client: static fn(array $p, array $r): array => [
            'response' => 'hi',
            'status' => 'Available',
            'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
        ]);

        $this->assertSame(['input_tokens' => 10, 'output_tokens' => 5], $result['usage']);
    }

    public function testClientThrowingIsUnavailableNotAnUncaughtException(): void
    {
        $providers = new LlmProviders();
        $providers->register(['provider_id' => 'anthropic', 'provider_name' => 'Anthropic', 'endpoint_ref' => 'https://api.anthropic.com']);

        $result = $providers->call('anthropic', [], client: static function (array $p, array $r): array {
            throw new RuntimeException('connection refused');
        });

        $this->assertSame('Unavailable', $result['status']);
        $this->assertSame('connection refused', $result['error']);
    }

    public function testClientReceivesTheRegisteredProviderMetadata(): void
    {
        $providers = new LlmProviders();
        $providers->register([
            'provider_id' => 'anthropic',
            'provider_name' => 'Anthropic',
            'endpoint_ref' => 'https://api.anthropic.com',
            'capability_metadata' => ['streaming' => true],
        ]);
        $seen = null;

        $providers->call('anthropic', ['model' => 'claude-sonnet-5'], client: function (array $provider, array $request) use (&$seen): array {
            $seen = $provider;

            return ['status' => 'Available'];
        });

        $this->assertSame('anthropic', $seen['provider_id']);
        $this->assertTrue($seen['capability_metadata']['streaming']);
    }

    // --- Events ---

    public function testEventPayloadNeverExposesResponseOrUsage(): void
    {
        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        $events->listen('llm_providers.available', new CallbackEventListener(
            function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }
        ));

        $providers = new LlmProviders(null, $events);
        $providers->register(['provider_id' => 'anthropic', 'provider_name' => 'Anthropic', 'endpoint_ref' => 'https://api.anthropic.com']);
        $providers->call('anthropic', [], client: static fn(array $p, array $r): array => [
            'response' => 'super-secret-completion',
            'status' => 'Available',
            'usage' => ['input_tokens' => 1],
        ]);

        $this->assertCount(1, $captured);
        $payload = $captured[0]->getPayload();
        $this->assertArrayNotHasKey('response', $payload);
        $this->assertArrayNotHasKey('usage', $payload);
        $this->assertStringNotContainsString('super-secret-completion', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public function testWorksWithoutAnEventBus(): void
    {
        $providers = new LlmProviders(null, null);
        $providers->register(['provider_id' => 'anthropic', 'provider_name' => 'Anthropic', 'endpoint_ref' => 'https://api.anthropic.com']);

        $result = $providers->call('anthropic');

        $this->assertSame('Available', $result['status']);
    }
}
