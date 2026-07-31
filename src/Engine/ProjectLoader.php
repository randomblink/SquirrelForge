<?php

declare(strict_types=1);

namespace SquirrelForge\Engine;

use Closure;

/**
 * Establishes verified project context before planning or
 * project-changing execution begins, per 14_ENGINE/PROJECT-LOADER.md.
 * The genuine root of 14_ENGINE's internal dependency graph: every
 * other unbuilt Engine component (Context Manager, Goal Planner, Task
 * Decomposer, Workflow Selector, Execution Planner) needs this one, or
 * something downstream of it, first.
 *
 * Unlike most components built this session, this spec is unusually
 * literal: it gives numbered shell steps (`pwd`, `git rev-parse
 * --show-toplevel`, `git status --short`, `git remote -v`) with worked
 * mismatch examples, not an abstract responsibility list. This class
 * implements the Repository Identity Verification Procedure and Dirty
 * Working Tree Behavior exactly as written, genuinely shelling out to
 * git rather than approximating it. An injectable `$shell` closure
 * (signature `(string $command): string`) makes this deterministically
 * testable the same way `$clock` closures made time-dependent classes
 * testable elsewhere in this codebase -- defaulting to real
 * `shell_exec()` when not supplied.
 *
 * detectProjectType() is a real, deterministic file-presence check
 * (composer.json, package.json, a WordPress plugin/theme header comment
 * found in a real file) -- never a fabricated classification model.
 *
 * This is a deliberately scoped-down implementation of the full spec.
 * Included: repository identity verification, working-tree inspection,
 * and project-type detection -- these are the three checks this class
 * can honestly perform without another component. Deferred, and
 * explicitly not fabricated: rule loading (01_RULES has no defined
 * runtime shape beyond raw prose), configuration/runtime-profile
 * loading (21_CONFIGURATION/28_RUNTIME-CONFIG define no file format
 * this class could parse), interface/tool discovery, and domain
 * knowledge loading. Readiness is derived only from what this class
 * actually checks, so it only ever reports READY, READY_WITH_LIMITATIONS,
 * or BLOCKED -- RECOVERY_REQUIRED and LOAD_FAILED require broader
 * engine/execution state this class doesn't own.
 */
final class ProjectLoader
{
    public function __construct(private readonly ?Closure $shell = null)
    {
    }

    /**
     * Steps 1-5 of the Repository Identity Verification Procedure.
     *
     * @return array{pwd: string, repository_root: ?string, branch: ?string, requested_project: string, status: string, remotes: ?string, error: ?string}
     */
    public function verifyRepositoryIdentity(string $requestedProjectName, string $workingDirectory): array
    {
        $repositoryRoot = $this->trimmedShell(sprintf('git -C %s rev-parse --show-toplevel 2>/dev/null', escapeshellarg($workingDirectory)));

        if ($repositoryRoot === '') {
            return ['pwd' => $workingDirectory, 'repository_root' => null, 'branch' => null, 'requested_project' => $requestedProjectName, 'status' => 'not_a_repository', 'remotes' => null, 'error' => 'The working directory is not inside a git repository.'];
        }

        $branch = $this->trimmedShell(sprintf('git -C %s rev-parse --abbrev-ref HEAD 2>/dev/null', escapeshellarg($workingDirectory)));
        $matches = str_contains(strtolower($repositoryRoot), strtolower($requestedProjectName))
            || str_contains(strtolower(basename($repositoryRoot)), strtolower($requestedProjectName));

        if ($matches) {
            return ['pwd' => $workingDirectory, 'repository_root' => $repositoryRoot, 'branch' => $branch, 'requested_project' => $requestedProjectName, 'status' => 'matched', 'remotes' => null, 'error' => null];
        }

        $remotes = $this->trimmedShell(sprintf('git -C %s remote -v 2>/dev/null', escapeshellarg($workingDirectory)));

        return [
            'pwd' => $workingDirectory,
            'repository_root' => $repositoryRoot,
            'branch' => $branch,
            'requested_project' => $requestedProjectName,
            'status' => 'mismatch',
            'remotes' => $remotes !== '' ? $remotes : null,
            'error' => sprintf('Resolved repository "%s" does not match the requested project "%s".', $repositoryRoot, $requestedProjectName),
        ];
    }

    /**
     * Dirty Working Tree Behavior: reports modified and untracked files
     * without discarding or overwriting anything.
     *
     * @return array{clean: bool, modified: array<int, string>, untracked: array<int, string>}
     */
    public function inspectWorkingTree(string $repositoryRoot): array
    {
        $modified = [];
        $untracked = [];

        foreach ($this->shellOutput(sprintf('git -C %s status --short 2>/dev/null', escapeshellarg($repositoryRoot))) as $line) {
            if ($line === '') {
                continue;
            }

            $status = trim(substr($line, 0, 2));
            $path = trim(substr($line, 3));

            if ($status === '??') {
                $untracked[] = $path;
            } else {
                $modified[] = $path;
            }
        }

        return ['clean' => $modified === [] && $untracked === [], 'modified' => $modified, 'untracked' => $untracked];
    }

    /**
     * @return array{project_type: string, active_domains: array<int, string>}
     */
    public function detectProjectType(string $projectRoot): array
    {
        $domains = [];

        if (is_file($projectRoot . '/composer.json')) {
            $domains[] = 'php';
        }

        if (is_file($projectRoot . '/package.json')) {
            $domains[] = 'node';
        }

        if ($this->hasFileHeaderMatching($projectRoot, '/^\s*\*?\s*Plugin Name:\s*.+/mi')) {
            $domains[] = 'wordpress_plugin';
        }

        if ($this->hasFileHeaderMatching($projectRoot, '/^\s*\*?\s*Theme Name:\s*.+/mi')) {
            $domains[] = 'wordpress_theme';
        }

        $type = match (true) {
            in_array('wordpress_plugin', $domains, true) => 'wordpress_plugin',
            in_array('wordpress_theme', $domains, true) => 'wordpress_theme',
            in_array('php', $domains, true) => 'php_library',
            in_array('node', $domains, true) => 'node_project',
            default => 'unknown',
        };

        return ['project_type' => $type, 'active_domains' => $domains];
    }

    /**
     * Combines identity verification, working-tree inspection, and
     * project-type detection into a single Project Context Record.
     *
     * @return array{project_root: ?string, repository_identity: array<string, mixed>, active_branch: ?string, project_type: ?string, active_domains: array<int, string>, working_tree: ?array<string, mixed>, readiness_state: string, missing_context: array<int, string>}
     */
    public function load(string $requestedProjectName, string $workingDirectory): array
    {
        $identity = $this->verifyRepositoryIdentity($requestedProjectName, $workingDirectory);

        if ($identity['status'] !== 'matched') {
            return [
                'project_root' => $identity['repository_root'],
                'repository_identity' => $identity,
                'active_branch' => $identity['branch'],
                'project_type' => null,
                'active_domains' => [],
                'working_tree' => null,
                'readiness_state' => 'BLOCKED',
                'missing_context' => ['repository_identity'],
            ];
        }

        $workingTree = $this->inspectWorkingTree($identity['repository_root']);
        $detection = $this->detectProjectType($identity['repository_root']);
        $missingContext = [];
        $readiness = 'READY';

        if (!$workingTree['clean']) {
            $missingContext[] = 'working_tree_has_uncommitted_changes';
            $readiness = 'READY_WITH_LIMITATIONS';
        }

        if ($detection['project_type'] === 'unknown') {
            $missingContext[] = 'project_type_undetected';
            $readiness = 'READY_WITH_LIMITATIONS';
        }

        return [
            'project_root' => $identity['repository_root'],
            'repository_identity' => $identity,
            'active_branch' => $identity['branch'],
            'project_type' => $detection['project_type'],
            'active_domains' => $detection['active_domains'],
            'working_tree' => $workingTree,
            'readiness_state' => $readiness,
            'missing_context' => $missingContext,
        ];
    }

    private function hasFileHeaderMatching(string $projectRoot, string $pattern): bool
    {
        $candidates = [...(glob($projectRoot . '/*.php') ?: []), ...(glob($projectRoot . '/style.css') ?: [])];

        foreach ($candidates as $file) {
            $head = (string) @file_get_contents($file, false, null, 0, 4096);

            if (preg_match($pattern, $head) === 1) {
                return true;
            }
        }

        return false;
    }

    private function trimmedShell(string $command): string
    {
        return trim($this->runShell($command));
    }

    /**
     * @return array<int, string>
     */
    private function shellOutput(string $command): array
    {
        $output = $this->runShell($command);

        return $output === '' ? [] : explode("\n", rtrim($output, "\n"));
    }

    private function runShell(string $command): string
    {
        if ($this->shell !== null) {
            return ($this->shell)($command);
        }

        return (string) shell_exec($command);
    }
}
