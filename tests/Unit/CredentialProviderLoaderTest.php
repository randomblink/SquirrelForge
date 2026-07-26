<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SquirrelForge\Integration\Http\HttpTransportResponse;
use SquirrelForge\RuntimeConfig\CredentialProviderLoader;
use SquirrelForge\RuntimeConfig\ResilientHttpCredentialProvider;
use SquirrelForge\RuntimeConfig\ProviderStartupValidator;
use SquirrelForge\RuntimeConfig\RuntimeEnvironmentPolicy;
use SquirrelForge\Tests\Support\FakeHttpTransport;

final class CredentialProviderLoaderTest extends TestCase
{
    private string $databasePath;
    private array $originalEnvironment = [];

    protected function setUp(): void
    {
        $this->databasePath = sys_get_temp_dir() . '/squirrelforge-provider-loader-'
            . bin2hex(random_bytes(8)) . '.sqlite';

        foreach ([
            'SQUIRRELFORGE_CREDENTIAL_PROVIDER',
            'SQUIRRELFORGE_CREDENTIAL_PROVIDER_URL',
            'SQUIRRELFORGE_CREDENTIAL_PROVIDER_TOKEN',
        ] as $name) {
            $this->originalEnvironment[$name] = getenv($name);
            putenv($name);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->originalEnvironment as $name => $value) {
            $value === false ? putenv($name) : putenv($name . '=' . $value);
        }

        foreach ([$this->databasePath, $this->databasePath . '-shm', $this->databasePath . '-wal'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testLocalEnvironmentDefaultsToReferenceProviderSet(): void
    {
        $providers = $this->loader('local')->load($this->databasePath);

        $this->assertSame('secrets:sqlite-local', $providers->secrets->providerRef());
        (new ProviderStartupValidator(new RuntimeEnvironmentPolicy('local')))->validate(
            $providers->secrets,
            $providers->mfa,
            $providers->securityEvents
        );
    }

    public function testProductionRequiresRecognizedExplicitProvider(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('recognized credential provider');

        $this->loader('production')->load($this->databasePath);
    }

    public function testProductionLoadsReadyHttpsProvider(): void
    {
        putenv('SQUIRRELFORGE_CREDENTIAL_PROVIDER=http-json');
        putenv('SQUIRRELFORGE_CREDENTIAL_PROVIDER_URL=https://credentials.example.test');
        putenv('SQUIRRELFORGE_CREDENTIAL_PROVIDER_TOKEN=' . str_repeat('t', 40));
        $providers = $this->loader('production')->load($this->databasePath);

        $this->assertInstanceOf(ResilientHttpCredentialProvider::class, $providers->secrets);
        (new ProviderStartupValidator(new RuntimeEnvironmentPolicy('production')))->validate(
            $providers->secrets,
            $providers->mfa,
            $providers->securityEvents
        );
    }

    public function testProductionRejectsInsecureHttpProvider(): void
    {
        putenv('SQUIRRELFORGE_CREDENTIAL_PROVIDER=http-json');
        putenv('SQUIRRELFORGE_CREDENTIAL_PROVIDER_URL=http://credentials.example.test');
        putenv('SQUIRRELFORGE_CREDENTIAL_PROVIDER_TOKEN=' . str_repeat('t', 40));
        $providers = $this->loader('production')->load($this->databasePath);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('credential-provider:http-json');
        (new ProviderStartupValidator(new RuntimeEnvironmentPolicy('production')))->validate(
            $providers->secrets,
            $providers->mfa,
            $providers->securityEvents
        );
    }

    private function loader(string $environment): CredentialProviderLoader
    {
        return new CredentialProviderLoader(
            new RuntimeEnvironmentPolicy($environment),
            new FakeHttpTransport(
                static fn(array $request): HttpTransportResponse =>
                    new HttpTransportResponse(200, [], '{}')
            )
        );
    }
}
