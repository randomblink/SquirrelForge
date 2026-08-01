<?php

declare(strict_types=1);

namespace SquirrelForge\Memory;

/**
 * Holds a temporary active-context snapshot for the current task, per
 * 18_MEMORY/WORKING-MEMORY.md.
 *
 * "Working Memory caches references to state owned elsewhere; it does
 * not decide or record the authoritative value of any of them" is
 * upheld structurally: every method here either stores exactly what the
 * caller supplies (refresh()) or returns exactly what was stored
 * (snapshot()) -- there is no computation anywhere in this class that
 * could produce a goal, workflow, task, dependency, or validation value
 * of its own. Only `temporary_decisions` is this class's own
 * authoritative content, exactly as the spec's own Contents table
 * states. isStale() upholds "if Working Memory's cached copy and the
 * owning component ever disagree, the owning component is correct" as
 * a plain, real comparison -- it never decides which value is right,
 * only whether they match.
 *
 * refresh() only accepts the six named cacheable fields (Active Goal,
 * Active Workflow, Active Agent, Current Task, Dependencies, Validation
 * State); an unrecognized field name is rejected outright rather than
 * silently cached, keeping the snapshot's shape honest to the spec's
 * own Contents table.
 *
 * handOff() is a genuine composition of the real EpisodicMemory: it
 * assembles an entry from the cached references plus the caller's
 * completion evidence and calls
 * EpisodicMemory::recordCompletedTask() directly, then clears the
 * snapshot -- but only once that hand-off actually succeeds, so a
 * failed hand-off never silently discards the working snapshot it
 * would otherwise be the only record of.
 */
final class WorkingMemory
{
    private const CACHEABLE_FIELDS = [
        'active_goal', 'active_workflow', 'active_agent', 'current_task', 'dependencies', 'validation_state',
    ];

    /** @var array<string, array<string, mixed>> */
    private array $snapshots = [];

    /** @var array<string, array<string, mixed>> */
    private array $temporaryDecisions = [];

    public function __construct(private readonly ?EpisodicMemory $episodicMemory = null)
    {
    }

    /**
     * @param array<string, mixed> $references any of self::CACHEABLE_FIELDS
     */
    public function initialize(string $taskId, array $references = []): void
    {
        $this->snapshots[$taskId] = array_intersect_key($references, array_flip(self::CACHEABLE_FIELDS));
        $this->temporaryDecisions[$taskId] = [];
    }

    /**
     * @param array<string, mixed> $updates
     * @return array{outcome: string, error: ?string}
     */
    public function refresh(string $taskId, array $updates): array
    {
        if (!isset($this->snapshots[$taskId])) {
            return ['outcome' => 'not_initialized', 'error' => sprintf('Working Memory was never initialized for task "%s".', $taskId)];
        }

        foreach (array_keys($updates) as $field) {
            if (!in_array($field, self::CACHEABLE_FIELDS, true)) {
                return ['outcome' => 'invalid_field', 'error' => sprintf('"%s" is not a cacheable Working Memory field.', $field)];
            }
        }

        $this->snapshots[$taskId] = [...$this->snapshots[$taskId], ...$updates];

        return ['outcome' => 'refreshed', 'error' => null];
    }

    /**
     * @return array{outcome: string, error: ?string}
     */
    public function recordTemporaryDecision(string $taskId, string $key, mixed $value): array
    {
        if (!isset($this->snapshots[$taskId])) {
            return ['outcome' => 'not_initialized', 'error' => sprintf('Working Memory was never initialized for task "%s".', $taskId)];
        }

        $this->temporaryDecisions[$taskId][$key] = $value;

        return ['outcome' => 'recorded', 'error' => null];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function snapshot(string $taskId): ?array
    {
        if (!isset($this->snapshots[$taskId])) {
            return null;
        }

        return [...$this->snapshots[$taskId], 'temporary_decisions' => $this->temporaryDecisions[$taskId]];
    }

    /**
     * A real comparison only -- never a judgment about which value is
     * correct. The owning component is always correct per the spec.
     */
    public function isStale(string $taskId, string $field, mixed $authoritativeValue): bool
    {
        return ($this->snapshots[$taskId][$field] ?? null) !== $authoritativeValue;
    }

    /**
     * @param array{outcome_summary: string, validation_result_reference: string, decision_references?: array<int, string>, retrieval_tags?: array<int, string>} $completion
     * @return array{memory_id: ?string, outcome: string, error: ?string}
     */
    public function handOff(string $taskId, array $completion): array
    {
        if (!isset($this->snapshots[$taskId])) {
            return ['memory_id' => null, 'outcome' => 'not_initialized', 'error' => sprintf('Working Memory was never initialized for task "%s".', $taskId)];
        }

        if ($this->episodicMemory === null) {
            return ['memory_id' => null, 'outcome' => 'not_configured', 'error' => 'Episodic Memory is not configured.'];
        }

        $snapshot = $this->snapshots[$taskId];

        $entry = [
            'task_reference' => $snapshot['current_task'] ?? $taskId,
            'goal_reference' => $snapshot['active_goal'] ?? null,
            'execution_plan_reference' => $snapshot['active_workflow'] ?? null,
            'agents_involved' => array_values(array_filter([$snapshot['active_agent'] ?? null])),
            'outcome_summary' => $completion['outcome_summary'],
            'validation_result_reference' => $completion['validation_result_reference'],
            'decision_references' => $completion['decision_references'] ?? [],
            'retrieval_tags' => $completion['retrieval_tags'] ?? [],
        ];

        $result = $this->episodicMemory->recordCompletedTask($entry);

        if ($result['outcome'] === 'recorded') {
            $this->clear($taskId);
        }

        return $result;
    }

    public function clear(string $taskId): void
    {
        unset($this->snapshots[$taskId], $this->temporaryDecisions[$taskId]);
    }
}
