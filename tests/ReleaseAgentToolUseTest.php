<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Agent\Roles\ReleaseAgent;
use SquirrelForge\Tests\Support\FakeCommandRunner;
use SquirrelForge\Tests\Support\FakeFileSystem;

/**
 * Covers ReleaseAgent's real release-actions path: it must stay a pure
 * gate-check unless actions are explicitly enabled AND a release_version
 * is supplied, and any failed step must stop the sequence and downgrade
 * the reported status rather than reporting a false "Ready".
 */
final class ReleaseAgentToolUseTest extends TestCase
{
    /**
     * @return array<string, array<string, mixed>>
     */
    private function passingHistory(): array
    {
        return [
            'reviewer' => ['status' => 'Approved'],
            'security' => ['status' => 'Approved'],
            'performance' => ['status' => 'Approved'],
            'documentation' => ['status' => 'Complete'],
        ];
    }

    public function testActionsDisabledByDefaultLeavesGateCheckOnly(): void
    {
        $fileSystem = new FakeFileSystem();
        $commandRunner = new FakeCommandRunner();

        // actionsEnabled defaults to false even with both tools injected.
        $agent = new ReleaseAgent(null, $fileSystem, $commandRunner, false);
        $agent->boot();

        $result = $agent->execute([
            'stage' => 'release',
            'history' => $this->passingHistory(),
            'release_version' => '1.2.0',
        ]);

        $this->assertSame('Ready', $result['status']);
        $this->assertArrayNotHasKey('actions', $result['release']);
        $this->assertSame([], $commandRunner->calls);
    }

    public function testSkipsActionsWhenNoReleaseVersionSupplied(): void
    {
        $fileSystem = new FakeFileSystem(['CHANGELOG.md' => "# Changelog\n\n## Unreleased\n\n- thing\n"]);
        $commandRunner = new FakeCommandRunner();

        $agent = new ReleaseAgent(null, $fileSystem, $commandRunner, true);
        $agent->boot();

        $result = $agent->execute([
            'stage' => 'release',
            'history' => $this->passingHistory(),
        ]);

        $this->assertSame('Ready', $result['status']);
        $this->assertSame('Skipped', $result['release']['actions']['status']);
        $this->assertSame([], $commandRunner->calls);
    }

    public function testPerformsFullReleaseSequenceWhenEnabled(): void
    {
        $fileSystem = new FakeFileSystem(['CHANGELOG.md' => "# Changelog\n\n## Unreleased\n\n- thing\n"]);
        $commandRunner = new FakeCommandRunner();

        $agent = new ReleaseAgent(null, $fileSystem, $commandRunner, true);
        $agent->boot();

        $result = $agent->execute([
            'stage' => 'release',
            'history' => $this->passingHistory(),
            'release_version' => '1.2.0',
        ]);

        $this->assertSame('Ready', $result['status']);
        $this->assertSame('Ready', $result['release']['actions']['status']);
        $this->assertSame('v1.2.0', $result['release']['actions']['tag']);
        $this->assertStringContainsString('## [v1.2.0] -', $fileSystem->files['CHANGELOG.md']);
        $this->assertStringContainsString('- thing', $fileSystem->files['CHANGELOG.md']);
        $this->assertSame("1.2.0\n", $fileSystem->files['VERSION']);

        $this->assertCount(6, $commandRunner->calls);
        $this->assertSame(['git', 'status', '--porcelain'], $commandRunner->calls[0]);
        $this->assertSame(['git', 'add', 'CHANGELOG.md', 'VERSION'], $commandRunner->calls[1]);
        $this->assertSame(['git', 'commit', '-m', 'Release v1.2.0'], $commandRunner->calls[2]);
        $this->assertSame(['git', 'tag', 'v1.2.0'], $commandRunner->calls[3]);
        $this->assertSame(['git', 'push'], $commandRunner->calls[4]);
        $this->assertSame(['git', 'push', '--tags'], $commandRunner->calls[5]);
    }

    public function testAbortsWithoutRunningAnyOtherCommandWhenWorkingTreeIsDirty(): void
    {
        $fileSystem = new FakeFileSystem(['CHANGELOG.md' => "# Changelog\n\n## Unreleased\n\n- thing\n"]);
        $commandRunner = new FakeCommandRunner();
        $commandRunner->respondTo(
            ['git', 'status', '--porcelain'],
            ['exitCode' => 0, 'output' => " M some/unrelated/file.php\n", 'error' => '']
        );

        $agent = new ReleaseAgent(null, $fileSystem, $commandRunner, true);
        $agent->boot();

        $result = $agent->execute([
            'stage' => 'release',
            'history' => $this->passingHistory(),
            'release_version' => '1.2.0',
        ]);

        $this->assertSame('Hold', $result['status']);
        $this->assertSame('Failed', $result['release']['actions']['status']);
        $this->assertContains('release_actions', $result['release']['outstanding']);
        // Only the working-tree check itself ran; CHANGELOG.md was never
        // touched, and no add/commit/tag/push was attempted.
        $this->assertCount(1, $commandRunner->calls);
        $this->assertSame(['git', 'status', '--porcelain'], $commandRunner->calls[0]);
        $this->assertSame("# Changelog\n\n## Unreleased\n\n- thing\n", $fileSystem->files['CHANGELOG.md']);
        $this->assertArrayNotHasKey('VERSION', $fileSystem->files);
    }

    public function testStopsAtFirstFailedStepAndDowngradesToHold(): void
    {
        $fileSystem = new FakeFileSystem(['CHANGELOG.md' => "# Changelog\n\n## Unreleased\n"]);
        $commandRunner = new FakeCommandRunner();
        $commandRunner->respondTo(
            ['git', 'commit', '-m', 'Release v1.2.0'],
            ['exitCode' => 1, 'output' => '', 'error' => 'nothing to commit']
        );

        $agent = new ReleaseAgent(null, $fileSystem, $commandRunner, true);
        $agent->boot();

        $result = $agent->execute([
            'stage' => 'release',
            'history' => $this->passingHistory(),
            'release_version' => '1.2.0',
        ]);

        $this->assertSame('Hold', $result['status']);
        $this->assertSame('Failed', $result['release']['actions']['status']);
        $this->assertContains('release_actions', $result['release']['outstanding']);
        // git status (clean) and git add succeeded, git commit failed ->
        // tag/push never attempted.
        $this->assertCount(3, $commandRunner->calls);
    }

    public function testMissingChangelogUnreleasedSectionFailsWithoutRunningAnyCommand(): void
    {
        $fileSystem = new FakeFileSystem(['CHANGELOG.md' => "# Changelog\n\nNo unreleased section here.\n"]);
        $commandRunner = new FakeCommandRunner();

        $agent = new ReleaseAgent(null, $fileSystem, $commandRunner, true);
        $agent->boot();

        $result = $agent->execute([
            'stage' => 'release',
            'history' => $this->passingHistory(),
            'release_version' => '1.2.0',
        ]);

        $this->assertSame('Hold', $result['status']);
        $this->assertSame('Failed', $result['release']['actions']['status']);
        // The working tree check itself still runs (and passes, since it's
        // clean); only the changelog-parsing step fails.
        $this->assertCount(1, $commandRunner->calls);
        $this->assertSame(['git', 'status', '--porcelain'], $commandRunner->calls[0]);
        $this->assertArrayNotHasKey('VERSION', $fileSystem->files);
    }
}
