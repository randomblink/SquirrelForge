<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SquirrelForge\RuntimeConfig\ProviderStartupValidator;
use SquirrelForge\RuntimeConfig\RuntimeEnvironmentPolicy;
use SquirrelForge\RuntimeConfig\SqliteSecretsManager;
use SquirrelForge\Security\DenyHumanMfaVerifier;
use SquirrelForge\Security\SqliteSecurityEventSink;

final class ProviderStartupValidatorTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        $this->databasePath = sys_get_temp_dir() . '/squirrelforge-startup-provider-'
            . bin2hex(random_bytes(8)) . '.sqlite';
    }

    protected function tearDown(): void
    {
        foreach ([$this->databasePath, $this->databasePath . '-shm', $this->databasePath . '-wal'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testProductionRejectsLocalAndPlaceholderProviders(): void
    {
        $validator = new ProviderStartupValidator(new RuntimeEnvironmentPolicy('production'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('secrets:sqlite-local');
        $validator->validate(
            new SqliteSecretsManager($this->databasePath),
            new DenyHumanMfaVerifier(),
            new SqliteSecurityEventSink($this->databasePath)
        );
    }

    public function testLocalAndTestEnvironmentsPermitReferenceProviders(): void
    {
        $providers = [
            new SqliteSecretsManager($this->databasePath),
            new DenyHumanMfaVerifier(),
            new SqliteSecurityEventSink($this->databasePath),
        ];

        (new ProviderStartupValidator(new RuntimeEnvironmentPolicy('local')))->validate(...$providers);
        (new ProviderStartupValidator(new RuntimeEnvironmentPolicy('test')))->validate(...$providers);

        $this->addToAssertionCount(2);
    }
}
