<?php

declare(strict_types=1);

namespace SquirrelForge\Agent\Roles\Support;

use DateTimeImmutable;
use RuntimeException;
use SquirrelForge\Contracts\CommandRunnerInterface;
use SquirrelForge\Contracts\FileSystemInterface;
use Throwable;

/**
 * The real, externally consequential half of the Release stage, extracted
 * out of `ReleaseAgent`: everything that actually touches disk or runs a
 * process. `ReleaseAgent` itself keeps only the gate-check (Approved/
 * Warning/Complete across review, security, performance, documentation),
 * which never has side effects; it constructs one of these only when both
 * a `FileSystemInterface` and `CommandRunnerInterface` were injected, and
 * only calls `run()` once the gate-check has already passed and the
 * caller-supplied `release_version` is in hand.
 *
 * Before touching anything, `run()` verifies the git working tree is clean
 * (`git status --porcelain` reports nothing) so an unrelated uncommitted
 * change can't get swept into the release commit; a dirty tree aborts the
 * whole sequence with no other command run. It then finalizes
 * CHANGELOG.md, bumps the root VERSION file, and runs `git add`, `commit`,
 * `tag`, `push`, `push --tags` in order, stopping at the first failed step.
 */
final class ReleaseActionsRunner
{
    public function __construct(
        private readonly FileSystemInterface $fileSystem,
        private readonly CommandRunnerInterface $commandRunner
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function run(string $releaseVersion): array
    {
        $tagName = str_starts_with($releaseVersion, 'v') ? $releaseVersion : "v{$releaseVersion}";
        $steps = [];

        try {
            $this->assertWorkingTreeIsClean();
            $steps[] = ['step' => 'working tree check', 'ok' => true];

            $this->finalizeChangelog($tagName);
            $steps[] = ['step' => 'finalize CHANGELOG.md', 'ok' => true];

            $this->bumpVersionFile($tagName);
            $steps[] = ['step' => 'bump VERSION', 'ok' => true];

            foreach ($this->releaseCommands($tagName) as $stepName => $command) {
                $result = $this->commandRunner->run($command);
                $steps[] = ['step' => $stepName, 'ok' => $result['exitCode'] === 0, ...$result];

                if ($result['exitCode'] !== 0) {
                    return ['status' => 'Failed', 'tag' => $tagName, 'steps' => $steps];
                }
            }

            return ['status' => 'Ready', 'tag' => $tagName, 'steps' => $steps];
        } catch (Throwable $e) {
            $steps[] = ['step' => 'exception', 'ok' => false, 'error' => $e->getMessage()];

            return ['status' => 'Failed', 'tag' => $tagName, 'steps' => $steps];
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function releaseCommands(string $tagName): array
    {
        return [
            'git add' => ['git', 'add', 'CHANGELOG.md', 'VERSION'],
            'git commit' => ['git', 'commit', '-m', "Release {$tagName}"],
            'git tag' => ['git', 'tag', $tagName],
            'git push' => ['git', 'push'],
            'git push --tags' => ['git', 'push', '--tags'],
        ];
    }

    private function assertWorkingTreeIsClean(): void
    {
        $result = $this->commandRunner->run(['git', 'status', '--porcelain']);

        if ($result['exitCode'] !== 0) {
            throw new RuntimeException(
                'Unable to verify the git working tree status: ' . trim($result['error'])
            );
        }

        if (trim($result['output']) !== '') {
            throw new RuntimeException(
                'Working tree is not clean; refusing to run release actions so unrelated '
                . 'uncommitted changes aren\'t swept into the release commit: '
                . trim($result['output'])
            );
        }
    }

    private function finalizeChangelog(string $tagName): void
    {
        $contents = $this->fileSystem->read('CHANGELOG.md');

        if (!str_contains($contents, '## Unreleased')) {
            throw new RuntimeException('CHANGELOG.md has no "## Unreleased" section to finalize.');
        }

        $date = (new DateTimeImmutable())->format('Y-m-d');
        $replacement = "## Unreleased\n\n## [{$tagName}] - {$date}";

        $updated = preg_replace('/^## Unreleased/m', $replacement, $contents, 1);

        if ($updated === null) {
            throw new RuntimeException('Failed to finalize CHANGELOG.md.');
        }

        $this->fileSystem->write('CHANGELOG.md', $updated);
    }

    /**
     * Overwrites the root VERSION file with the bare version number (no
     * "v" prefix, matching common convention for a plain VERSION file).
     * Doesn't validate that the new version is actually greater than
     * whatever VERSION currently holds -- same level of trust the caller
     * already gets for CHANGELOG content and the tag name itself.
     */
    private function bumpVersionFile(string $tagName): void
    {
        $this->fileSystem->write('VERSION', ltrim($tagName, 'v') . "\n");
    }
}
