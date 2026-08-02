<?php

declare(strict_types=1);

namespace SquirrelForge\Memory;

use SquirrelForge\Observability\AuditTrail;

/**
 * Top-level coordinator for the Memory layer, per
 * 18_MEMORY/MEMORY-MANAGER.md, mirroring how ObservabilityManager,
 * OptimizationManager, LearningManager, and KnowledgeManager route to
 * their own layer's real specialists. This completes 18_MEMORY: all
 * seven other real components (Working, Episodic, Semantic, Project
 * Memory, Memory Index, Memory Retrieval, Memory Retention) are wired
 * into a single coordination surface.
 *
 * "Coordinate indexing through Memory Index when a new or updated
 * record is stored" is more than routing: every store_* operation that
 * succeeds also calls the injected SqliteMemoryIndex::indexRecord()
 * automatically, a genuine second real step this class performs beyond
 * forwarding the request -- none of the memory-type components index
 * themselves, since Memory Index is a peer component, not owned by any
 * of them.
 *
 * "Record memory activity for audit" is a genuine cross-category
 * composition rather than a fabricated log: when an AuditTrail is
 * injected (27_OBSERVABILITY/AUDIT-TRAIL.md, real), every coordinate()
 * call records a real audit event through it -- actor, action,
 * resource, and the specialist's own real outcome -- the same
 * already-real audit infrastructure Observability Manager uses,
 * reused here rather than inventing a separate Memory-specific audit
 * mechanism.
 *
 * This class never re-implements any specialist's logic, only forwards
 * payloads and passes back real results unmodified, upholding the
 * spec's own "it does not itself store, retrieve, index, or retain
 * memory" boundary. It owns no database of its own.
 */
final class MemoryManager
{
    private const REQUIRED_FIELDS = [
        'store_working' => ['task_id'],
        'store_episodic' => ['task_reference', 'goal_reference', 'outcome_summary', 'validation_result_reference'],
        'store_semantic' => ['category', 'title', 'description', 'source_reference', 'validation_reference'],
        'initialize_project' => ['project_name'],
        'reference_project_decision' => ['project_name', 'reference'],
        'reference_project_milestone' => ['project_name', 'reference'],
        'reference_project_risk' => ['project_name', 'reference'],
        'reference_project_dependency' => ['project_name', 'reference'],
        'add_project_note' => ['project_name', 'note'],
        'set_project_conventions' => ['project_name', 'conventions'],
        'update_project_architecture' => ['project_name', 'reference'],
        'retrieve' => ['query_terms'],
        'apply_retention' => ['memory_type', 'record_id'],
    ];

    public function __construct(
        private readonly ?WorkingMemory $workingMemory = null,
        private readonly ?EpisodicMemory $episodicMemory = null,
        private readonly ?SemanticMemory $semanticMemory = null,
        private readonly ?ProjectMemory $projectMemory = null,
        private readonly ?SqliteMemoryIndex $index = null,
        private readonly ?MemoryRetrieval $retrieval = null,
        private readonly ?SqliteMemoryRetention $retention = null,
        private readonly ?AuditTrail $auditTrail = null
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options forwarded to whichever specialist handles this operation
     * @return array{operation: string, target_component: string, outcome: string, error: ?string, result: mixed}
     */
    public function coordinate(string $operation, array $payload, array $options = []): array
    {
        if (!isset(self::REQUIRED_FIELDS[$operation])) {
            return $this->finish($operation, 'none', 'rejected', sprintf('Unknown memory operation "%s".', $operation), null, $payload);
        }

        $missingField = $this->firstMissingField($operation, $payload);

        if ($missingField !== null) {
            return $this->finish($operation, 'none', 'rejected', sprintf('Missing required field "%s" for operation "%s".', $missingField, $operation), null, $payload);
        }

        return match ($operation) {
            'store_working' => $this->coordinateStoreWorking($payload),
            'store_episodic' => $this->coordinateStoreEpisodic($payload),
            'store_semantic' => $this->coordinateStoreSemantic($payload),
            'initialize_project' => $this->coordinateInitializeProject($payload),
            'reference_project_decision' => $this->coordinateProjectReference($payload, 'referenceDecision', 'reference_project_decision'),
            'reference_project_milestone' => $this->coordinateProjectReference($payload, 'referenceMilestone', 'reference_project_milestone'),
            'reference_project_risk' => $this->coordinateProjectReference($payload, 'referenceRisk', 'reference_project_risk'),
            'reference_project_dependency' => $this->coordinateProjectReference($payload, 'referenceDependency', 'reference_project_dependency'),
            'add_project_note' => $this->coordinateAddProjectNote($payload),
            'set_project_conventions' => $this->coordinateSetProjectConventions($payload),
            'update_project_architecture' => $this->coordinateUpdateProjectArchitecture($payload),
            'retrieve' => $this->coordinateRetrieve($payload, $options),
            'apply_retention' => $this->coordinateApplyRetention($payload),
        };
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function firstMissingField(string $operation, array $payload): ?string
    {
        foreach (self::REQUIRED_FIELDS[$operation] as $field) {
            if (!array_key_exists($field, $payload)) {
                return $field;
            }
        }

        return null;
    }

    private function coordinateStoreWorking(array $payload): array
    {
        if ($this->workingMemory === null) {
            return $this->finish('store_working', 'working_memory', 'rejected', 'Working Memory is not configured.', null, $payload);
        }

        $taskId = $payload['task_id'];
        $references = $payload['references'] ?? [];

        if ($this->workingMemory->snapshot($taskId) === null) {
            $this->workingMemory->initialize($taskId, $references);
            $outcome = 'initialized';
        } else {
            $this->workingMemory->refresh($taskId, $references);
            $outcome = 'refreshed';
        }

        $this->index?->indexRecord('working', $taskId, ['title' => $payload['title'] ?? null]);

        return $this->finish('store_working', 'working_memory', $outcome, null, $this->workingMemory->snapshot($taskId), $payload);
    }

    private function coordinateStoreEpisodic(array $payload): array
    {
        if ($this->episodicMemory === null) {
            return $this->finish('store_episodic', 'episodic_memory', 'rejected', 'Episodic Memory is not configured.', null, $payload);
        }

        $result = $this->episodicMemory->recordCompletedTask($payload);

        if ($result['outcome'] === 'recorded') {
            $this->index?->indexRecord('episodic', $result['memory_id'], ['title' => $payload['outcome_summary'], 'keywords' => $payload['retrieval_tags'] ?? []]);
        }

        return $this->finish('store_episodic', 'episodic_memory', $result['outcome'], $result['error'], $result, $payload);
    }

    private function coordinateStoreSemantic(array $payload): array
    {
        if ($this->semanticMemory === null) {
            return $this->finish('store_semantic', 'semantic_memory', 'rejected', 'Semantic Memory is not configured.', null, $payload);
        }

        $result = $this->semanticMemory->storeApprovedKnowledge($payload);

        if ($result['outcome'] === 'stored') {
            $this->index?->indexRecord('semantic', $result['record_id'], ['title' => $payload['title']]);
        }

        return $this->finish('store_semantic', 'semantic_memory', $result['outcome'], $result['error'], $result, $payload);
    }

    private function coordinateInitializeProject(array $payload): array
    {
        if ($this->projectMemory === null) {
            return $this->finish('initialize_project', 'project_memory', 'rejected', 'Project Memory is not configured.', null, $payload);
        }

        $result = $this->projectMemory->initializeProject($payload['project_name'], $payload);

        if ($result['outcome'] === 'initialized') {
            $this->index?->indexRecord('project', $payload['project_name'], ['title' => $payload['project_name']]);
        }

        return $this->finish('initialize_project', 'project_memory', $result['outcome'], $result['error'], $result, $payload);
    }

    /**
     * @param array{project_name: string, reference: string} $payload
     */
    private function coordinateProjectReference(array $payload, string $method, string $operation): array
    {
        if ($this->projectMemory === null) {
            return $this->finish($operation, 'project_memory', 'rejected', 'Project Memory is not configured.', null, $payload);
        }

        $result = $this->projectMemory->{$method}($payload['project_name'], $payload['reference']);
        $this->index?->indexRecord('project', $payload['project_name']);

        return $this->finish($operation, 'project_memory', $result['outcome'], $result['error'], $result, $payload);
    }

    private function coordinateAddProjectNote(array $payload): array
    {
        if ($this->projectMemory === null) {
            return $this->finish('add_project_note', 'project_memory', 'rejected', 'Project Memory is not configured.', null, $payload);
        }

        $result = $this->projectMemory->addNote($payload['project_name'], $payload['note']);

        return $this->finish('add_project_note', 'project_memory', $result['outcome'], $result['error'], $result, $payload);
    }

    private function coordinateSetProjectConventions(array $payload): array
    {
        if ($this->projectMemory === null) {
            return $this->finish('set_project_conventions', 'project_memory', 'rejected', 'Project Memory is not configured.', null, $payload);
        }

        $result = $this->projectMemory->setConventions($payload['project_name'], $payload['conventions']);

        return $this->finish('set_project_conventions', 'project_memory', $result['outcome'], $result['error'], $result, $payload);
    }

    private function coordinateUpdateProjectArchitecture(array $payload): array
    {
        if ($this->projectMemory === null) {
            return $this->finish('update_project_architecture', 'project_memory', 'rejected', 'Project Memory is not configured.', null, $payload);
        }

        $result = $this->projectMemory->updateArchitecture($payload['project_name'], $payload['reference']);
        $this->index?->indexRecord('project', $payload['project_name']);

        return $this->finish('update_project_architecture', 'project_memory', $result['outcome'], $result['error'], $result, $payload);
    }

    private function coordinateRetrieve(array $payload, array $options): array
    {
        if ($this->retrieval === null) {
            return $this->finish('retrieve', 'memory_retrieval', 'rejected', 'Memory Retrieval is not configured.', null, $payload);
        }

        $result = $this->retrieval->search($payload['query_terms'], $options);

        return $this->finish('retrieve', 'memory_retrieval', $result['outcome'], $result['error'], $result, $payload);
    }

    private function coordinateApplyRetention(array $payload): array
    {
        if ($this->retention === null) {
            return $this->finish('apply_retention', 'memory_retention', 'rejected', 'Memory Retention is not configured.', null, $payload);
        }

        $result = $this->retention->evaluate($payload['memory_type'], $payload['record_id'], $payload['evidence'] ?? []);

        return $this->finish('apply_retention', 'memory_retention', $result['outcome'], $result['error'], $result, $payload);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function finish(string $operation, string $targetComponent, string $outcome, ?string $error, mixed $result, array $payload): array
    {
        $this->auditTrail?->recordEvent([
            'actor_ref' => $payload['requesting_component'] ?? 'memory_manager',
            'action' => $operation,
            'resource_ref' => $targetComponent,
            'outcome' => $outcome,
            'reason' => $error,
        ]);

        return ['operation' => $operation, 'target_component' => $targetComponent, 'outcome' => $outcome, 'error' => $error, 'result' => $result];
    }
}
