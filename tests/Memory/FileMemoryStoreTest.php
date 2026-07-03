<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Memory;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Memory\FileMemoryStore;

/**
 * Exercises FileMemoryStore against a real, disposable temp directory --
 * this is what makes memory persistent across process invocations, so it's
 * worth testing against the real filesystem rather than a fake.
 */
final class FileMemoryStoreTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/sf_memory_test_' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    private function path(): string
    {
        return $this->directory . '/memory.json';
    }

    public function testBootCreatesTheParentDirectoryAndAnEmptyStoreFile(): void
    {
        $store = new FileMemoryStore($this->path());

        $this->assertDirectoryDoesNotExist($this->directory);

        $store->boot();

        $this->assertDirectoryExists($this->directory);
        $this->assertFileExists($this->path());
        $this->assertSame([], $store->search());
    }

    public function testStoresAndRetrievesAMemory(): void
    {
        $store = new FileMemoryStore($this->path());
        $store->boot();

        $id = $store->store(['type' => 'note', 'content' => 'first run']);

        $retrieved = $store->retrieve($id);

        $this->assertNotNull($retrieved);
        $this->assertSame('note', $retrieved['type']);
        $this->assertSame('first run', $retrieved['content']);
        $this->assertArrayHasKey('created_at', $retrieved);
    }

    public function testRetrievingAnUnknownIdReturnsNull(): void
    {
        $store = new FileMemoryStore($this->path());
        $store->boot();

        $this->assertNull($store->retrieve('does-not-exist'));
    }

    public function testPersistsAcrossSeparateStoreInstancesPointedAtTheSamePath(): void
    {
        $first = new FileMemoryStore($this->path());
        $first->boot();
        $id = $first->store(['type' => 'note', 'content' => 'from a previous invocation']);

        // A brand new instance, as would happen in a fresh process, reading
        // the same path.
        $second = new FileMemoryStore($this->path());
        $second->boot();

        $retrieved = $second->retrieve($id);

        $this->assertNotNull($retrieved);
        $this->assertSame('from a previous invocation', $retrieved['content']);
    }

    public function testSearchFiltersByCriteria(): void
    {
        $store = new FileMemoryStore($this->path());
        $store->boot();

        $store->store(['type' => 'note', 'tag' => 'a']);
        $store->store(['type' => 'note', 'tag' => 'b']);
        $store->store(['type' => 'summary', 'tag' => 'a']);

        $results = $store->search(['type' => 'note', 'tag' => 'a']);

        $this->assertCount(1, $results);
        $this->assertSame('a', $results[0]['tag']);
    }

    public function testUpdateMergesFieldsAndReturnsFalseForAnUnknownId(): void
    {
        $store = new FileMemoryStore($this->path());
        $store->boot();

        $id = $store->store(['type' => 'note', 'content' => 'original']);

        $this->assertTrue($store->update($id, ['content' => 'revised']));

        $retrieved = $store->retrieve($id);
        $this->assertSame('revised', $retrieved['content']);
        $this->assertArrayHasKey('updated_at', $retrieved);

        $this->assertFalse($store->update('does-not-exist', ['content' => 'x']));
    }

    public function testDeleteRemovesAMemoryAndReturnsFalseForAnUnknownId(): void
    {
        $store = new FileMemoryStore($this->path());
        $store->boot();

        $id = $store->store(['type' => 'note']);

        $this->assertTrue($store->delete($id));
        $this->assertNull($store->retrieve($id));
        $this->assertFalse($store->delete($id));
    }

    public function testHealthReportsPathAndCount(): void
    {
        $store = new FileMemoryStore($this->path());
        $store->boot();
        $store->store(['type' => 'note']);

        $health = $store->health();

        $this->assertSame('healthy', $health['status']);
        $this->assertSame($this->path(), $health['details']['path']);
        $this->assertSame(1, $health['details']['count']);
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . '/' . $item;

            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($directory);
    }
}
