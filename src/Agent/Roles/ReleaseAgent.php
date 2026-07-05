<?php

declare(strict_types=1);

namespace SquirrelForge\Agent\Roles;

use SquirrelForge\Agent\Roles\Support\ReleaseActionsRunner;
use SquirrelForge\Contracts\CommandRunnerInterface;
use SquirrelForge\Contracts\FileSystemInterface;
use SquirrelForge\Contracts\LlmClientInterface;

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
 * The actual working-tree check, CHANGELOG.md/VERSION finalization, and
 * git commands live in `ReleaseActionsRunner` (constructed only when both
 * a `FileSystemInterface` and `CommandRunnerInterface` are injected); this
 * class keeps only the gate-check and the decision of whether/when to
 * delegate to it.
 */
final class ReleaseAgent extends AbstractRoleAgent
{
    private readonly ?ReleaseActionsRunner $releaseActions;

    public function __construct(
        ?LlmClientInterface $llm = null,
        ?FileSystemInterface $fileSystem = null,
        ?CommandRunnerInterface $commandRunner = null,
        private readonly bool $actionsEnabled = false,
        string $version = '1.0.0'
    ) {
        parent::__construct($llm, $version);

        $this->releaseActions = ($fileSystem !== null && $commandRunner !== null)
            ? new ReleaseActionsRunner($fileSystem, $commandRunner)
            : null;
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

        // Review's own status is checked against APPROVED/APPROVED_WITH_LIMITATIONS/
        // SPECIALIST_REVIEW_REQUIRED: SPECIALIST_REVIEW_REQUIRED is not itself a
        // blocking outcome by the time the pipeline reaches Release, because the
        // specialist review it calls for is exactly the Security and Performance
        // gates checked independently below -- if those passed, the escalation
        // Reviewer asked for has already happened.
        $gates = [
            'review' => in_array(
                $review['status'] ?? null,
                ['APPROVED', 'APPROVED_WITH_LIMITATIONS', 'SPECIALIST_REVIEW_REQUIRED'],
                true
            ),
            'security' => in_array($security['status'] ?? null, ['APPROVED', 'APPROVED_WITH_LIMITATIONS'], true),
            'performance' => in_array(
                $performance['status'] ?? null,
                ['APPROVED', 'APPROVED_WITH_LIMITATIONS', 'OPTIMIZATION_RECOMMENDED'],
                true
            ),
            'documentation' => in_array($documentation['status'] ?? null, ['COMPLETE', 'COMPLETE_WITH_LIMITATIONS'], true),
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
        return $this->actionsEnabled && $this->releaseActions !== null;
    }

    /**
     * @return array<string, mixed>
     */
    private function performReleaseActions(string $releaseVersion): array
    {
        return $this->releaseActions->run($releaseVersion);
    }
}
