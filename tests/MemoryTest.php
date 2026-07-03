<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Contracts\MemoryStoreInterface;
use SquirrelForge\Core\Kernel;

final class MemoryTest extends TestCase
{
    private MemoryStoreInterface $memory;

    protected function setUp(): void
    {
        $kernel = new Kernel();
        $app = $kernel->boot();
        $this->memory = $app->container()->make(MemoryStoreInterface::class);
    }

    public function testStoreAndRetrieve(): void
    {
        $id = $this->memory->store(['type' => 'note', 'content' => 'hello']);

        $record = $this->memory->retrieve($id);

        $this->assertNotNull($record);
        $this->assertSame('note', $record['type']);
        $this->assertSame('hello', $record['content']);
        $this->assertSame($id, $record['id']);
    }

    public function testUpdate(): void
    {
        $id = $this->memory->store(['type' => 'note', 'content' => 'original']);

        $updated = $this->memory->update($id, ['content' => 'updated']);

        $this->assertTrue($updated);
        $this->assertSame('updated', $this->memory->retrieve($id)['content']);
    }

    public function testUpdateReturnsFalseForMissingId(): void
    {
        $this->assertFalse($this->memory->update('nonexistent', ['content' => 'x']));
    }

    public function testDelete(): void
    {
        $id = $this->memory->store(['type' => 'note', 'content' => 'to delete']);

        $this->assertTrue($this->memory->delete($id));
        $this->assertNull($this->memory->retrieve($id));
    }

    public function testDeleteReturnsFalseForMissingId(): void
    {
        $this->assertFalse($this->memory->delete('nonexistent'));
    }

    public function testSearchByCriteria(): void
    {
        $this->memory->store(['type' => 'note', 'content' => 'a']);
        $this->memory->store(['type' => 'note', 'content' => 'b']);
        $this->memory->store(['type' => 'task', 'content' => 'c']);

        $notes = $this->memory->search(['type' => 'note']);
        $tasks = $this->memory->search(['type' => 'task']);

        $this->assertCount(2, $notes);
        $this->assertCount(1, $tasks);
    }

    public function testSearchWithNoCriteriaReturnsAll(): void
    {
        $this->memory->store(['type' => 'note', 'content' => 'a']);
        $this->memory->store(['type' => 'note', 'content' => 'b']);

        $all = $this->memory->search();

        $this->assertGreaterThanOrEqual(2, count($all));
    }
}
