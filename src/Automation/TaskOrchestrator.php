<?php

declare(strict_types=1);

namespace SquirrelForge\Automation;

use Closure;
use DateTimeImmutable;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;
use SquirrelForge\Resilience\SqliteFailureDetector;
use SquirrelForge\Resilience\SqliteResilienceManager;
use Throwable;

/**
 * Owns Automation-domain task dependency coordination, readiness
 * ordering, and dispatch handoffs, per
 * 33_AUTOMATION/TASK-ORCHESTRATOR.md.
 *
 * "Determine Automation-domain readiness order from declared
 * dependencies" and "coordinate parallel-group... handoffs" are real
 * graph algorithms, not fabricated scheduling: a genuine topological
 * sort (Kahn's algorithm) both detects cycles -- an invalid graph is
 * rejected outright, never partially executed -- and groups tasks into
 * levels, where every task in a level has all its dependencies already
 * resolved by earlier levels. "Parallel-group" describes the real
 * grouping this produces, not actual concurrent execution: PHP here is
 * synchronous and there's no real async/queue infrastructure to
 * genuinely parallelize with, so tasks within a level are dispatched in
 * sequence.
 *
 * "Request agent, service, integration, or execution dispatch through
 * authoritative owners" is implemented via caller-registered dispatcher
 * closures keyed by dispatch type, the same delegation pattern
 * EventListener uses for its consumers -- this class never assigns an
 * agent or invokes a service itself. A task whose dispatch type has no
 * registered dispatcher fails outright rather than silently skipping,
 * per the spec's rule against inventing an execution path.
 *
 * Dependency-failure propagation is real: a task whose direct dependency
 * failed or was blocked is itself marked `blocked`, not dispatched, and
 * that status propagates forward through later levels exactly like the
 * failure itself would. "Request retry or recovery handling from...
 * Resilience components" mirrors WorkflowAutomator's real pipeline: a
 * failed task's error becomes a real SqliteFailureDetector detection,
 * handed to the injected SqliteResilienceManager exactly as the caller
 * configured -- only when the caller explicitly requests it.
 */
final class TaskOrchestrator
{
    public function __construct(
        private readonly ?SqliteFailureDetector $failureDetector = null,
        private readonly ?SqliteResilienceManager $resilienceManager = null,
        private readonly ?EventBusInterface $events = null
    ) {
    }

    /**
     * @param array<int, array{id: string, depends_on?: array<int, string>, dispatch_type: string, payload?: array<string, mixed>}> $tasks
     * @param array<string, Closure(array<string, mixed>, array<string, mixed>): mixed> $dispatchers keyed by dispatch_type
     * @param array{context?: array<string, mixed>, recovery?: array<string, mixed>} $options
     * @return array{outcome: string, levels: array<int, array<int, string>>, task_results: array<string, array<string, mixed>>, error: ?string}
     */
    public function orchestrate(array $tasks, array $dispatchers, array $options = []): array
    {
        $structuralError = $this->checkCompleteness($tasks);

        if ($structuralError !== null) {
            return ['outcome' => 'invalid', 'levels' => [], 'task_results' => [], 'error' => $structuralError];
        }

        $levels = $this->computeLevels($tasks);

        if ($levels === null) {
            return ['outcome' => 'invalid', 'levels' => [], 'task_results' => [], 'error' => 'Task graph contains a dependency cycle.'];
        }

        $tasksById = [];

        foreach ($tasks as $task) {
            $tasksById[$task['id']] = $task;
        }

        $results = [];

        foreach ($levels as $level) {
            foreach ($level as $taskId) {
                $results[$taskId] = $this->runTask($tasksById[$taskId], $dispatchers, $results, $options);
            }
        }

        $statuses = array_column($results, 'status');
        $outcome = in_array('failed', $statuses, true) || in_array('blocked', $statuses, true)
            ? 'partial'
            : 'completed';

        $this->publish($outcome);

        return ['outcome' => $outcome, 'levels' => $levels, 'task_results' => $results, 'error' => null];
    }

    /**
     * @param array<int, array{id: string, depends_on?: array<int, string>}> $tasks
     */
    private function checkCompleteness(array $tasks): ?string
    {
        $ids = array_column($tasks, 'id');

        foreach (array_count_values($ids) as $id => $count) {
            if ($count > 1) {
                return sprintf('Duplicate task identifier "%s".', $id);
            }
        }

        foreach ($tasks as $task) {
            foreach ($task['depends_on'] ?? [] as $dependency) {
                if (!in_array($dependency, $ids, true)) {
                    return sprintf('Task "%s" depends on unknown task "%s".', $task['id'], $dependency);
                }
            }
        }

        return null;
    }

    /**
     * Kahn's algorithm: repeatedly peels off the set of tasks with no
     * remaining unresolved dependencies as one level, until nothing is
     * left. Returns null if a cycle prevents every task from being
     * peeled off.
     *
     * @param array<int, array{id: string, depends_on?: array<int, string>}> $tasks
     * @return array<int, array<int, string>>|null
     */
    private function computeLevels(array $tasks): ?array
    {
        $remainingDependencies = [];

        foreach ($tasks as $task) {
            $remainingDependencies[$task['id']] = $task['depends_on'] ?? [];
        }

        $levels = [];

        while ($remainingDependencies !== []) {
            $ready = array_keys(array_filter($remainingDependencies, static fn(array $deps): bool => $deps === []));

            if ($ready === []) {
                return null;
            }

            $levels[] = $ready;

            foreach ($ready as $id) {
                unset($remainingDependencies[$id]);
            }

            foreach ($remainingDependencies as $id => $deps) {
                $remainingDependencies[$id] = array_values(array_diff($deps, $ready));
            }
        }

        return $levels;
    }

    /**
     * @param array{id: string, depends_on?: array<int, string>, dispatch_type: string, payload?: array<string, mixed>} $task
     * @param array<string, Closure> $dispatchers
     * @param array<string, array<string, mixed>> $priorResults
     * @param array{context?: array<string, mixed>, recovery?: array<string, mixed>} $options
     * @return array{status: string, result: mixed, error: ?string, recovery: ?array<string, mixed>}
     */
    private function runTask(array $task, array $dispatchers, array $priorResults, array $options): array
    {
        foreach ($task['depends_on'] ?? [] as $dependency) {
            if (in_array($priorResults[$dependency]['status'], ['failed', 'blocked'], true)) {
                return ['status' => 'blocked', 'result' => null, 'error' => sprintf('Blocked by dependency "%s".', $dependency), 'recovery' => null];
            }
        }

        $dispatcher = $dispatchers[$task['dispatch_type']] ?? null;

        if ($dispatcher === null) {
            return ['status' => 'failed', 'result' => null, 'error' => sprintf('No dispatcher registered for dispatch type "%s".', $task['dispatch_type']), 'recovery' => null];
        }

        try {
            $result = $dispatcher($task, $options['context'] ?? []);

            return ['status' => 'completed', 'result' => $result, 'error' => null, 'recovery' => null];
        } catch (Throwable $e) {
            $recovery = $this->requestRecovery($task, $e, $options['recovery'] ?? null);

            return ['status' => 'failed', 'result' => null, 'error' => $e->getMessage(), 'recovery' => $recovery];
        }
    }

    /**
     * @param array{id: string} $task
     * @param array<string, mixed>|null $recoveryOptions
     * @return array<string, mixed>|null
     */
    private function requestRecovery(array $task, Throwable $exception, ?array $recoveryOptions): ?array
    {
        if ($recoveryOptions === null || $this->failureDetector === null || $this->resilienceManager === null) {
            return null;
        }

        $detection = $this->failureDetector->detect('workflow', 'execution_failure', [
            'affected_components' => [$task['id']],
        ]);

        return $this->resilienceManager->coordinate($detection, $recoveryOptions);
    }

    private function publish(string $outcome): void
    {
        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'task_orchestrator.' . $outcome,
            new DateTimeImmutable(),
            self::class,
            ['outcome' => $outcome]
        ));
    }
}
