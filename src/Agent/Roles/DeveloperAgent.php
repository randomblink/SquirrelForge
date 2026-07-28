<?php

declare(strict_types=1);

namespace SquirrelForge\Agent\Roles;

use InvalidArgumentException;
use SquirrelForge\Agent\Roles\Support\FileChangeApplier;
use SquirrelForge\Contracts\FileSystemInterface;
use SquirrelForge\Contracts\LlmClientInterface;
use SquirrelForge\Tools\ToolRegistry;

/**
 * Implements the Agent Developer role from `16_AGENTS/AGENT-DEVELOPER.md`.
 *
 * Records the implementation status of each planned task. The overall stage
 * is only "Complete" (and only then does it hand off to the Reviewer) once
 * every completed task reports "Complete"; any "Blocked" task blocks the
 * whole pipeline rather than silently proceeding.
 *
 * By default this agent only reports on task results the caller supplies
 * via context field "tasks_completed" -- it does not touch disk. When a
 * `FileSystemInterface` is injected (alongside an LLM), and the caller does
 * NOT supply "tasks_completed" explicitly, it will ask the LLM to propose
 * file changes for the plan and apply them directly through that
 * FileSystemInterface, which enforces path containment within its root.
 * Any file change that fails to apply forces the owning task (or the whole
 * batch, if it can't be attributed) to "Blocked" rather than reporting
 * false success.
 *
 * The actual write/delete-per-change logic lives in `FileChangeApplier`
 * (constructed only when a `FileSystemInterface` is injected).
 */
final class DeveloperAgent extends AbstractRoleAgent
{
    private readonly ?FileChangeApplier $fileChangeApplier;
    private readonly bool $hasTools;

    public function __construct(
        ?LlmClientInterface $llm = null,
        ?FileSystemInterface $fileSystem = null,
        string $version = '1.0.0',
        ?ToolRegistry $tools = null
    ) {
        parent::__construct($llm, $version, $tools);

        $this->fileChangeApplier = $fileSystem !== null ? new FileChangeApplier($fileSystem) : null;
        $this->hasTools = $tools !== null && $tools->all() !== [];
    }

    public function stage(): string
    {
        return 'developer';
    }

    public function getName(): string
    {
        return 'Agent Developer';
    }

    public function getDescription(): string
    {
        return 'Implements the execution plan by producing validated deliverables.';
    }

    protected function process(array $context): array
    {
        $planner = $this->requireHistory($context, 'planner');

        $fileChanges = [];

        if (array_key_exists('tasks_completed', $context)) {
            $tasks = $context['tasks_completed'];
        } elseif ($this->fileChangeApplier !== null) {
            ['tasks' => $tasks, 'file_changes' => $fileChanges] = $this->implementViaFileSystem($planner);
        } else {
            throw new InvalidArgumentException(
                'DeveloperAgent requires context field "tasks_completed", or an injected ' .
                'FileSystemInterface (plus an LLM client) to implement the plan directly.'
            );
        }

        if ($tasks === []) {
            throw new InvalidArgumentException(
                'DeveloperAgent produced no task results; nothing to report.'
            );
        }

        $hasBlocked = false;
        $allComplete = true;

        foreach ($tasks as $task) {
            $status = $task['status'] ?? 'Pending';

            if ($status === 'Blocked') {
                $hasBlocked = true;
            }

            if ($status !== 'Complete') {
                $allComplete = false;
            }
        }

        $status = match (true) {
            $hasBlocked => 'Blocked',
            $allComplete => 'Complete',
            default => 'In Progress',
        };

        return [
            'implementation' => [
                'tasks' => $tasks,
                'file_changes' => $fileChanges,
            ],
            'status' => $status,
            'next_stage' => $status === 'Complete' ? 'reviewer' : null,
        ];
    }

    /**
     * @param array<string, mixed> $planner
     * @return array{tasks: array<int, array<string, mixed>>, file_changes: array<int, array<string, mixed>>}
     */
    private function implementViaFileSystem(array $planner): array
    {
        if ($this->hasTools) {
            return $this->implementViaToolUse($planner);
        }

        $reasoned = $this->reason(
            'Read the execution plan and implement it directly. Return "file_changes" as an ' .
            'array of objects {path, action, content}: "action" is one of create, update, ' .
            'delete ("content" required for create/update, omitted for delete); "path" must ' .
            'be relative to the project root, never absolute and never containing "..". ' .
            'Return "tasks_completed" as an array of objects {task, output, status} mirroring ' .
            'each plan phase, where "status" is one of Complete, In Progress, Blocked.',
            ['tasks_completed', 'file_changes'],
            ['plan' => $planner['plan'] ?? []]
        );

        $requestedChanges = $reasoned['file_changes'] ?? [];
        $appliedChanges = $this->fileChangeApplier->applyAll($requestedChanges);

        $tasks = $reasoned['tasks_completed'] ?? [];
        $hasFailedChange = array_filter(
            $appliedChanges,
            static fn (array $applied): bool => $applied['applied'] === false
        ) !== [];

        if ($hasFailedChange) {
            $tasks = $tasks !== []
                ? array_map(static fn (array $task): array => [...$task, 'status' => 'Blocked'], $tasks)
                : [['task' => 'Apply file changes', 'output' => null, 'status' => 'Blocked']];
        }

        return ['tasks' => $tasks, 'file_changes' => $appliedChanges];
    }

    /**
     * @param array<string, mixed> $planner
     * @return array{tasks: array<int, array<string, mixed>>, file_changes: array<int, array<string, mixed>>}
     */
    private function implementViaToolUse(array $planner): array
    {
        $reasoned = $this->reason(
            'Read the execution plan and implement it directly using the available file tools ' .
            '(read_file, write_file, delete_file) to make the necessary changes. Once finished, ' .
            'return "tasks_completed" as an array of objects {task, output, status} mirroring ' .
            'each plan phase, where "status" is one of Complete, In Progress, Blocked.',
            ['tasks_completed'],
            ['plan' => $planner['plan'] ?? []]
        );

        $tasks = $reasoned['tasks_completed'] ?? [];
        $fileChanges = [];
        $hasFailedChange = false;

        foreach ($this->lastToolCalls() as $call) {
            if (!in_array($call['name'], ['write_file', 'delete_file'], true)) {
                continue;
            }

            $applied = ($call['result']['applied'] ?? false) === true;
            $hasFailedChange = $hasFailedChange || !$applied;

            $fileChanges[] = [
                'path' => $call['input']['path'] ?? null,
                'action' => $call['name'] === 'write_file' ? 'write' : 'delete',
                'applied' => $applied,
                'error' => $call['result']['error'] ?? null,
            ];
        }

        if ($hasFailedChange) {
            $tasks = $tasks !== []
                ? array_map(static fn (array $task): array => [...$task, 'status' => 'Blocked'], $tasks)
                : [['task' => 'Apply file changes', 'output' => null, 'status' => 'Blocked']];
        }

        return ['tasks' => $tasks, 'file_changes' => $fileChanges];
    }
}
