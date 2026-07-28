<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Tests\Support\FakeFileSystem;
use SquirrelForge\Tools\DeleteFileTool;
use SquirrelForge\Tools\ReadFileTool;
use SquirrelForge\Tools\WriteFileTool;

final class FileToolsTest extends TestCase
{
    public function testReadFileToolReturnsContent(): void
    {
        $fileSystem = new FakeFileSystem(['a.txt' => 'hello']);
        $tool = new ReadFileTool($fileSystem);

        $this->assertSame(['content' => 'hello'], $tool->execute('read_file', ['path' => 'a.txt']));
    }

    public function testReadFileToolReturnsErrorForMissingPath(): void
    {
        $fileSystem = new FakeFileSystem();
        $tool = new ReadFileTool($fileSystem);

        $result = $tool->execute('read_file', []);

        $this->assertArrayHasKey('error', $result);
    }

    public function testReadFileToolReturnsErrorWhenFileMissing(): void
    {
        $fileSystem = new FakeFileSystem();
        $tool = new ReadFileTool($fileSystem);

        $result = $tool->execute('read_file', ['path' => 'missing.txt']);

        $this->assertArrayHasKey('error', $result);
    }

    public function testWriteFileToolAppliesWrite(): void
    {
        $fileSystem = new FakeFileSystem();
        $tool = new WriteFileTool($fileSystem);

        $result = $tool->execute('write_file', ['path' => 'a.txt', 'content' => 'hello']);

        $this->assertSame(['applied' => true], $result);
        $this->assertSame('hello', $fileSystem->files['a.txt']);
    }

    public function testWriteFileToolReportsFailureWithoutThrowing(): void
    {
        $fileSystem = new FakeFileSystem();
        $fileSystem->failOnWrite('a.txt');
        $tool = new WriteFileTool($fileSystem);

        $result = $tool->execute('write_file', ['path' => 'a.txt', 'content' => 'hello']);

        $this->assertFalse($result['applied']);
        $this->assertArrayHasKey('error', $result);
    }

    public function testWriteFileToolReturnsErrorForMissingContent(): void
    {
        $fileSystem = new FakeFileSystem();
        $tool = new WriteFileTool($fileSystem);

        $result = $tool->execute('write_file', ['path' => 'a.txt']);

        $this->assertFalse($result['applied']);
        $this->assertArrayHasKey('error', $result);
    }

    public function testDeleteFileToolAppliesDelete(): void
    {
        $fileSystem = new FakeFileSystem(['a.txt' => 'hello']);
        $tool = new DeleteFileTool($fileSystem);

        $result = $tool->execute('delete_file', ['path' => 'a.txt']);

        $this->assertSame(['applied' => true], $result);
        $this->assertFalse($fileSystem->exists('a.txt'));
    }

    public function testToolParametersDeclareRequiredFields(): void
    {
        $fileSystem = new FakeFileSystem();

        $this->assertSame(['path'], (new ReadFileTool($fileSystem))->parameters()['required']);
        $this->assertSame(['path', 'content'], (new WriteFileTool($fileSystem))->parameters()['required']);
        $this->assertSame(['path'], (new DeleteFileTool($fileSystem))->parameters()['required']);
    }

    public function testToolsDeclareDistinctCapabilities(): void
    {
        $fileSystem = new FakeFileSystem();

        $this->assertSame(['file.read'], (new ReadFileTool($fileSystem))->capabilities());
        $this->assertSame(['file.write'], (new WriteFileTool($fileSystem))->capabilities());
        $this->assertSame(['file.delete'], (new DeleteFileTool($fileSystem))->capabilities());
    }
}
