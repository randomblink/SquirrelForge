<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Agent\Roles\DeveloperAgent;
use SquirrelForge\Tests\Support\FakeFileSystem;
use SquirrelForge\Tests\Support\FakeLlmClient;

/**
 * Covers DeveloperAgent's real file-write path: it must only engage when
 * "tasks_completed" isn't supplied explicitly, and a failed file write must
 * never be silently reported as success.
 */
final class DeveloperAgentToolUseTest extends TestCase
{
    public function testExplicitTasksCompletedTakesPrecedenceOverFileSystem(): void
    {
        $fake = new FakeLlmClient(function (): string {
            $this->fail('LLM should not be called when tasks_completed is explicit.');
        });
        $fileSystem = new FakeFileSystem();

        $agent = new DeveloperAgent($fake, $fileSystem);
        $agent->boot();

        $result = $agent->execute([
            'stage' => 'developer',
            'history' => ['planner' => ['plan' => []]],
            'tasks_completed' => [['task' => 'x', 'output' => 'y', 'status' => 'Complete']],
        ]);

        $this->assertSame('Complete', $result['status']);
        $this->assertSame([], $fake->calls);
        $this->assertSame([], $fileSystem->calls);
    }

    public function testImplementsPlanViaFileSystemWhenTasksCompletedNotSupplied(): void
    {
        $fake = new FakeLlmClient(json_encode([
            'tasks_completed' => [
                ['task' => 'Add cache class', 'output' => 'src/Cache.php', 'status' => 'Complete'],
            ],
            'file_changes' => [
                ['path' => 'src/Cache.php', 'action' => 'create', 'content' => "<?php\n// cache\n"],
            ],
        ], JSON_THROW_ON_ERROR));

        $fileSystem = new FakeFileSystem();
        $agent = new DeveloperAgent($fake, $fileSystem);
        $agent->boot();

        $result = $agent->execute([
            'stage' => 'developer',
            'history' => ['planner' => ['plan' => [['phase' => 'Core', 'task' => 'Add cache class']]]],
        ]);

        $this->assertSame('Complete', $result['status']);
        $this->assertSame('reviewer', $result['next_stage']);
        $this->assertSame("<?php\n// cache\n", $fileSystem->files['src/Cache.php']);
        $this->assertTrue($result['implementation']['file_changes'][0]['applied']);
        $this->assertCount(1, $fake->calls);
    }

    public function testFailedFileWriteForcesTaskToBlockedInsteadOfReportingSuccess(): void
    {
        $fake = new FakeLlmClient(json_encode([
            'tasks_completed' => [
                ['task' => 'Add cache class', 'output' => 'src/Cache.php', 'status' => 'Complete'],
            ],
            'file_changes' => [
                ['path' => 'src/Cache.php', 'action' => 'create', 'content' => 'whatever'],
            ],
        ], JSON_THROW_ON_ERROR));

        $fileSystem = new FakeFileSystem();
        $fileSystem->failOnWrite('src/Cache.php');

        $agent = new DeveloperAgent($fake, $fileSystem);
        $agent->boot();

        $result = $agent->execute([
            'stage' => 'developer',
            'history' => ['planner' => ['plan' => []]],
        ]);

        $this->assertSame('Blocked', $result['status']);
        $this->assertNull($result['next_stage']);
        $this->assertFalse($result['implementation']['file_changes'][0]['applied']);
        $this->assertSame('Blocked', $result['implementation']['tasks'][0]['status']);
    }

    public function testThrowsWhenNeitherTasksCompletedNorFileSystemAreAvailable(): void
    {
        $agent = new DeveloperAgent();
        $agent->boot();

        $this->expectException(\InvalidArgumentException::class);

        $agent->execute([
            'stage' => 'developer',
            'history' => ['planner' => ['plan' => []]],
        ]);
    }
}
