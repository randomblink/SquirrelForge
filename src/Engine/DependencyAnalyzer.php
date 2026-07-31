<?php

declare(strict_types=1);

namespace SquirrelForge\Engine;

/**
 * Identifies and validates everything required before a task, workflow,
 * or execution plan may proceed, per 14_ENGINE/DEPENDENCY-ANALYZER.md.
 *
 * Does not take 22_INTERFACES or 21_CONFIGURATION as dependencies
 * despite the spec listing them: neither has a src/ implementation, so
 * their contribution -- what interfaces/configuration exist -- is
 * accepted the same way every unbuilt dependency in this codebase is,
 * as a caller-supplied dependency record rather than a fabricated
 * discovery mechanism.
 *
 * Availability is genuinely checked, not always assumed: for `file`,
 * `artifact`, and `template` dependencies that carry a real `path`,
 * resolveStatus() performs a real is_file() check. Every other
 * dependency type (workflow, tool, interface, permission, domain,
 * configuration, validation, external, task) has no discovery mechanism
 * this class owns, so its status is the caller's own supplied value,
 * defaulting honestly to `UNKNOWN` rather than a guessed `AVAILABLE`.
 *
 * "Identify circular dependencies" reuses the same Kahn's-algorithm
 * leveling TaskDecomposer/WorkflowOptimizer/TaskOrchestrator already
 * implement, applied here over `depends_on` edges between dependency
 * records themselves (e.g. a tool requiring a configuration value
 * first) -- a genuinely different graph shape than the task-to-task
 * graph Task Decomposer already validated, not a redundant re-check of
 * the same edges.
 *
 * Dependency Priority is the spec's own fixed 12-tier list, applied
 * literally as a sort order, the same "apply precedence, don't invent
 * it" idiom RuleEvaluator and Context Manager already use for their own
 * fixed-order lists.
 *
 * Severity classification is real and deterministic: a non-required
 * dependency, or one that is AVAILABLE or explicitly WAIVED, is
 * `informational`; a required dependency that is MISSING, CONFLICT, or
 * BLOCKED is `blocking`; the Conflict Rule's own named example ("a
 * permission requirement that exceeds the active boundary") is
 * escalated to `critical` specifically for a `permission`-type
 * dependency in CONFLICT. The Blocker Rule is upheld literally: every
 * blocking or critical dependency produces a blocker record with the
 * exact fields the spec names.
 *
 * Like Task Decomposer, this class has no database: analysis results
 * are pure return values for the caller (eventually Execution Planner /
 * State Manager) to act on.
 */
final class DependencyAnalyzer
{
    private const PRIORITY_ORDER = [
        'permission' => 1,
        'security_governance' => 2,
        'project_identity' => 3,
        'configuration' => 4,
        'workflow' => 5,
        'task' => 5,
        'domain' => 6,
        'file' => 7,
        'artifact' => 7,
        'tool' => 8,
        'interface' => 8,
        'template' => 9,
        'external' => 10,
        'validation' => 11,
    ];

    private const DEFAULT_PRIORITY = 12;
    private const VALID_STATUSES = ['AVAILABLE', 'MISSING', 'UNKNOWN', 'STALE', 'CONFLICT', 'BLOCKED', 'WAIVED'];
    private const BLOCKING_STATUSES = ['MISSING', 'CONFLICT', 'BLOCKED'];

    /**
     * @param array<int, array{id: string, name: string, type: string, required?: bool, owner?: ?string, path?: ?string, status?: ?string, evidence?: ?string, notes?: ?string, required_by?: ?string, depends_on?: array<int, string>}> $dependencies
     * @return array{dependencies: array<int, array<string, mixed>>, blockers: array<int, array<string, mixed>>, levels: array<int, array<int, string>>|null, outcome: string, error: ?string}
     */
    public function analyze(array $dependencies): array
    {
        if ($dependencies === []) {
            return ['dependencies' => [], 'blockers' => [], 'levels' => [], 'outcome' => 'no_dependencies', 'error' => null];
        }

        $structuralError = $this->checkGraphIntegrity($dependencies);

        if ($structuralError !== null) {
            return ['dependencies' => $dependencies, 'blockers' => [], 'levels' => null, 'outcome' => 'invalid_graph', 'error' => $structuralError];
        }

        $levels = $this->computeLevels($dependencies);

        if ($levels === null) {
            return ['dependencies' => $dependencies, 'blockers' => [], 'levels' => null, 'outcome' => 'invalid_graph', 'error' => 'Dependency graph contains a cycle.'];
        }

        $resolved = [];
        $blockers = [];

        foreach ($dependencies as $dependency) {
            $status = $this->resolveStatus($dependency);
            $severity = $this->classifySeverity($dependency, $status);

            $resolved[] = [
                ...$dependency,
                'status' => $status,
                'severity' => $severity,
                'priority' => self::PRIORITY_ORDER[$dependency['type']] ?? self::DEFAULT_PRIORITY,
            ];

            if (in_array($severity, ['blocking', 'critical'], true)) {
                $blockers[] = [
                    'dependency_id' => $dependency['id'],
                    'name' => $dependency['name'],
                    'required_by' => $dependency['required_by'] ?? null,
                    'owner' => $dependency['owner'] ?? null,
                    'severity' => $severity,
                    'evidence' => $dependency['evidence'] ?? null,
                    'next_safe_action' => $this->nextSafeAction($dependency, $status),
                ];
            }
        }

        usort($resolved, static fn(array $a, array $b): int => $a['priority'] <=> $b['priority']);

        return ['dependencies' => $resolved, 'blockers' => $blockers, 'levels' => $levels, 'outcome' => $blockers === [] ? 'clear' : 'blocked', 'error' => null];
    }

    /**
     * @param array{type: string, path?: ?string, status?: ?string} $dependency
     */
    private function resolveStatus(array $dependency): string
    {
        if (in_array($dependency['type'], ['file', 'artifact', 'template'], true) && isset($dependency['path'])) {
            return is_file($dependency['path']) ? 'AVAILABLE' : 'MISSING';
        }

        $status = strtoupper($dependency['status'] ?? 'UNKNOWN');

        return in_array($status, self::VALID_STATUSES, true) ? $status : 'UNKNOWN';
    }

    /**
     * @param array{type: string, required?: bool} $dependency
     */
    private function classifySeverity(array $dependency, string $status): string
    {
        if (($dependency['required'] ?? true) === false || in_array($status, ['AVAILABLE', 'WAIVED'], true)) {
            return 'informational';
        }

        if ($dependency['type'] === 'permission' && $status === 'CONFLICT') {
            return 'critical';
        }

        return in_array($status, self::BLOCKING_STATUSES, true) ? 'blocking' : 'limiting';
    }

    /**
     * @param array{name: string} $dependency
     */
    private function nextSafeAction(array $dependency, string $status): string
    {
        return match ($status) {
            'MISSING' => sprintf('Provide or install "%s" before proceeding.', $dependency['name']),
            'CONFLICT' => sprintf('Resolve the conflict on "%s" before proceeding.', $dependency['name']),
            default => sprintf('Resolve the %s status on "%s" before proceeding.', strtolower($status), $dependency['name']),
        };
    }

    /**
     * @param array<int, array{id: string, depends_on?: array<int, string>}> $dependencies
     */
    private function checkGraphIntegrity(array $dependencies): ?string
    {
        $ids = array_column($dependencies, 'id');

        foreach (array_count_values($ids) as $id => $count) {
            if ($count > 1) {
                return sprintf('Duplicate dependency identifier "%s".', $id);
            }
        }

        foreach ($dependencies as $dependency) {
            foreach ($dependency['depends_on'] ?? [] as $dependsOnId) {
                if (!in_array($dependsOnId, $ids, true)) {
                    return sprintf('Dependency "%s" depends on unknown dependency "%s".', $dependency['id'], $dependsOnId);
                }
            }
        }

        return null;
    }

    /**
     * Kahn's algorithm, matching TaskDecomposer's own real leveling.
     *
     * @param array<int, array{id: string, depends_on?: array<int, string>}> $dependencies
     * @return array<int, array<int, string>>|null
     */
    private function computeLevels(array $dependencies): ?array
    {
        $remaining = [];

        foreach ($dependencies as $dependency) {
            $remaining[$dependency['id']] = $dependency['depends_on'] ?? [];
        }

        $levels = [];

        while ($remaining !== []) {
            $ready = array_keys(array_filter($remaining, static fn(array $deps): bool => $deps === []));

            if ($ready === []) {
                return null;
            }

            $levels[] = $ready;

            foreach ($ready as $id) {
                unset($remaining[$id]);
            }

            foreach ($remaining as $id => $deps) {
                $remaining[$id] = array_values(array_diff($deps, $ready));
            }
        }

        return $levels;
    }
}
