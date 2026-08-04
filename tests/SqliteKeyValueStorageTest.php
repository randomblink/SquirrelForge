<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Security\StaticAuthorizationManager;
use SquirrelForge\Storage\SqliteKeyValueStorage;

final class SqliteKeyValueStorageTest extends TestCase
{
    /** @var array<int, string> */
    private array $databasePaths = [];

    protected function tearDown(): void
    {
        foreach ($this->databasePaths as $path) {
            foreach ([$path, $path . '-shm', $path . '-wal'] as $candidate) {
                if (is_file($candidate)) {
                    unlink($candidate);
                }
            }
        }
    }

    private function tempPath(string $label): string
    {
        $path = sys_get_temp_dir() . "/squirrelforge-key-value-storage-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function storage(): SqliteKeyValueStorage
    {
        return new SqliteKeyValueStorage($this->tempPath('kv'));
    }

    public function testStoreCreatesARetrievableRecordAtVersionOne(): void
    {
        $storage = $this->storage();

        $result = $storage->store('config', 'feature_flag', ['enabled' => true]);
        $retrieved = $storage->retrieve('config', 'feature_flag');

        $this->assertSame('stored', $result['outcome']);
        $this->assertSame(1, $result['version']);
        $this->assertTrue($retrieved['found']);
        $this->assertSame(['enabled' => true], $retrieved['value']);
        $this->assertSame(1, $retrieved['version']);
    }

    public function testStoreRefusesToOverwriteAnExistingKey(): void
    {
        $storage = $this->storage();
        $storage->store('config', 'feature_flag', ['enabled' => true]);

        $result = $storage->store('config', 'feature_flag', ['enabled' => false]);

        $this->assertSame('exists', $result['outcome']);
        $this->assertSame(['enabled' => true], $storage->retrieve('config', 'feature_flag')['value']);
    }

    public function testUpdateRefusesToCreateANewKey(): void
    {
        $storage = $this->storage();

        $result = $storage->update('config', 'feature_flag', ['enabled' => true]);

        $this->assertSame('not_found', $result['outcome']);
        $this->assertFalse($storage->retrieve('config', 'feature_flag')['found']);
    }

    public function testUpdateIncrementsTheVersion(): void
    {
        $storage = $this->storage();
        $storage->store('config', 'feature_flag', ['enabled' => true]);

        $result = $storage->update('config', 'feature_flag', ['enabled' => false]);
        $retrieved = $storage->retrieve('config', 'feature_flag');

        $this->assertSame('updated', $result['outcome']);
        $this->assertSame(2, $result['version']);
        $this->assertSame(['enabled' => false], $retrieved['value']);
        $this->assertSame(2, $retrieved['version']);
    }

    public function testNamespacesAreFullyIsolated(): void
    {
        $storage = $this->storage();
        $storage->store('tenant_a', 'shared_key', 'value_a');
        $storage->store('tenant_b', 'shared_key', 'value_b');

        $this->assertSame('value_a', $storage->retrieve('tenant_a', 'shared_key')['value']);
        $this->assertSame('value_b', $storage->retrieve('tenant_b', 'shared_key')['value']);
    }

    public function testDeleteRemovesTheRecordPermanently(): void
    {
        $storage = $this->storage();
        $storage->store('config', 'feature_flag', true);

        $result = $storage->delete('config', 'feature_flag');
        $retrieved = $storage->retrieve('config', 'feature_flag');

        $this->assertSame('deleted', $result['outcome']);
        $this->assertFalse($retrieved['found']);
    }

    public function testDeleteOfAnUnknownKeyIsNotFound(): void
    {
        $storage = $this->storage();

        $result = $storage->delete('config', 'missing');

        $this->assertSame('not_found', $result['outcome']);
    }

    public function testListReturnsKeysWithinANamespaceOptionallyFilteredByPrefix(): void
    {
        $storage = $this->storage();
        $storage->store('config', 'feature_a', true);
        $storage->store('config', 'feature_b', true);
        $storage->store('config', 'limit_x', 10);
        $storage->store('other_namespace', 'feature_a', false);

        $this->assertSame(['feature_a', 'feature_b', 'limit_x'], $storage->list('config'));
        $this->assertSame(['feature_a', 'feature_b'], $storage->list('config', 'feature_'));
    }

    public function testDeniedAuthorizationBlocksStoreRetrieveAndDelete(): void
    {
        $authorization = new StaticAuthorizationManager('permission:allowed');
        $storage = new SqliteKeyValueStorage($this->tempPath('kv'), $authorization);

        $store = $storage->store('config', 'feature_flag', true, ['identity_ref' => 'user_1', 'permission_ref' => 'permission:denied']);

        $this->assertSame('rejected', $store['outcome']);
        $this->assertFalse($storage->retrieve('config', 'feature_flag', ['identity_ref' => 'user_1', 'permission_ref' => 'permission:allowed'])['found']);
    }

    public function testRecentReturnsRecordedOperations(): void
    {
        $storage = $this->storage();
        $storage->store('config', 'a', 1);
        $storage->store('config', 'b', 2);

        $this->assertCount(2, $storage->recent());
    }
}
