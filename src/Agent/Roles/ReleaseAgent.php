<?php

declare(strict_types=1);

namespace SquirrelForge\Agent\Roles;

use DateTimeImmutable;
use RuntimeException;
use SquirrelForge\Contracts\CommandRunnerInterface;
use SquirrelForge\Contracts\FileSystemInterface;
use SquirrelForge\Contracts\LlmClientInterface;
use Throwable;

/**
 * Implements the Agent Release role from `16_AGENTS/AGENT-RELEASE.md`.
 *
 * The terminal stage of the pipeline. Verifies every prior quality gate
 * (review, security, performance, documentation) actually passed before
 * marking the release "Ready"; otherwise the release is put on "Hold" and
 * the outstanding gates are reported. This gate-check always runs and
 * never touches disk or a process on its own.
 *
 * Real, externally consequential release actions (finalizing
 * CHANGELOG.md, committing, tagging, and pushing) only run when ALL of
 * the following are true: the gate-check passed ("Ready"), a
 * `FileSystemInterface` and `CommandRunnerInterface` were both injected,
 * `$actionsEnabled` is true (see `ReleaseActionsPolicy` -- a deliberate
 * opt-in separate from having an LLM configured), and the caller supplied
 * a context field "release_version". Any failed step stops immediately
 * (no push after a failed commit, no tag after a failed commit, etc.) and
 * downgrades the reported status to "Hold".
 *
 * Before touching anything, it verifies the git working tree is clean
 * (`git status --porcelain` reports nothing) so an unrelated
 * uncommitted change can't get swept into the release commit alongside
 * CHANGELOG.md; a dirty tree aborts the whole sequence with no other
 * command run. It only finalizes CHANGELOG.md -- it does not bump a
 * version file, since this project doesn't have one defined yet.
 */
final class ReleaseAgent extends AbstractRoleAgent
{
    public function __construct(
        ?LlmClientInterface $llm = null,
        private readonly ?FileSystemInterface $fileSystem = null,
        private readonly ?CommandRunnerInterface $commandRunner = null,
        private readonly bool $actionsEnabled = false,
        string $version = '1.0.0'
    ) {
        parent::__construct($llm, $version);
    }

    public function stage(): string
    {
        return 'release';
    }

    public function getName(): string
    {
        return 'Agent Release';
    }

    public function getDescription(): string
    {
        return 'Prepares validated work for production and confirms release readiness.';
    }

    protected function process(array $context): array
    {
        $review = $this->requireHistory($context, 'reviewer');
        $security = $this->requireHistory($context, 'security');
        $performance = $this->requireHistory($context, 'performance');
        $documentation = $this->requireHistory($context, 'documentation');

        $gates = [
            'review' => ($review['status'] ?? null) === 'Approved',
            'security' => in_array($security['status'] ?? null, ['Approved', 'Warning'], true),
            'performance' => in_array($performance['status'] ?? null, ['Approved', 'Warning'], true),
            'documentation' => ($documentation['status'] ?? null) === 'Complete',
        ];

        $outstanding = array_keys(array_filter($gates, static fn (bool $passed): bool => !$passed));
        $status = $outstanding === [] ? 'Ready' : 'Hold';

        $release = [
            'gates' => $gates,
            'outstanding' => $outstanding,
        ];

        if ($status === 'Ready' && $this->canPerformActions()) {
            $releaseVersion = $context['release_version'] ?? null;

            if (!is_string($releaseVersion) || $releaseVersion === '') {
                $release['actions'] = [
                    'status' => 'Skipped',
                    'reason' => 'No context field "release_version" supplied.',
                ];
            } else {
                $actions = $this->performReleaseActions($releaseVersion);
                $release['actions'] = $actions;

                if ($actions['status'] === 'Failed') {
                    $status = 'Hold';
                    $release['outstanding'][] = 'release_actions';
                }
            }
        }

        return [
            'release' => $release,
            'status' => $status,
            'next_stage' => null,
        ];
    }

    private function canPerformActions(): bool
    {
        return $this->actionsEnabled
            && $this->fileSystem !== null
            && $this->commandRunner !== null;
    }

    /**
     * @return array<string, mixed>
     */
    private function performReleaseActions(string $releaseVersion): array
    {
        $tagName = str_starts_with($releaseVersion, 'v') ? $releaseVersion : "v{$releaseVersion}";
        $steps = [];

        try {
            $this->assertWorkingTreeIsClean();
            $steps[] = ['step' => 'working tree check', 'ok' => true];

            $this->finalizeChangelog($tagName);
            $steps[] = ['step' => 'finalize CHANGELOG.md', 'ok' => true];

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
            'git add' => ['git', 'add', 'CHANGELOG.md'],
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
}
