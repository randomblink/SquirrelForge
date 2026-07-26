<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Integration;

use SquirrelForge\Contracts\SecretsManagerInterface;
use SquirrelForge\RuntimeConfig\SqliteSecretsManager;
use SquirrelForge\Tests\Contract\SecretsManagerContractTestCase;

final class LocalProviderConformanceTest extends SecretsManagerContractTestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        $this->databasePath = sys_get_temp_dir() . '/squirrelforge-provider-contract-'
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

    protected function secretsManager(): SecretsManagerInterface
    {
        return new SqliteSecretsManager($this->databasePath);
    }
}
