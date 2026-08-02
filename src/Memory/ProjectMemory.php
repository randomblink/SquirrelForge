<?php

declare(strict_types=1);

namespace SquirrelForge\Memory;

use SquirrelForge\Contracts\MemoryStoreInterface;

/**
 * Stores durable, project-specific reference records and snapshots --
 * architecture, conventions, decisions, milestones, risks, and
 * dependencies already established elsewhere -- per
 * 18_MEMORY/PROJECT-MEMORY.md.
 *
 * "Project Memory stores; it does not validate decisions, assess risk,
 * analyze dependencies, or determine milestones" is upheld literally:
 * every reference-accumulating method here (referenceDecision(),
 * referenceMilestone(), referenceRisk(), referenceDependency(),
 * updateArchitecture()) accepts a plain string reference the caller
 * already obtained from the real owning component (Decision Engine,
 * State Manager, a specialist agent, Dependency Analyzer, Architect
 * Agent) and appends it to the project's own accumulating history --
 * this class never calls any of those components itself to produce a
 * reference of its own.
 *
 * Unlike EpisodicMemory/SemanticMemory (one independent record per
 * event), a project accumulates a single evolving record over its
 * lifetime, so this class is keyed by `project_name`: initializeProject()
 * creates it once (a second call is a real no-op, `already_exists`,
 * never a silent overwrite of history), and every reference method
 * appends to that same record's own arrays rather than creating a new
 * disconnected entry per call.
 *
 * Only Project Name, Conventions, and Notes are Project Memory's own
 * authoritative content, exactly as the spec's own Contents table
 * states -- setConventions() and addNote() are the only methods here
 * that write content this class itself authors rather than referencing.
 *
 * "Index the project record through Memory Index" has no real
 * implementation to call, so this class delegates storage and lookup to
 * the already-real, generic MemoryStoreInterface -- the same
 * substitution EpisodicMemory, WorkingMemory, and SemanticMemory
 * already use for the same unbuilt dependency.
 */
final class ProjectMemory
{
    public function __construct(private readonly ?MemoryStoreInterface $store = null)
    {
    }

    /**
     * @param array{architecture?: ?string, conventions?: array<int, string>, notes?: array<int, string>} $options
     * @return array{record_id: ?string, outcome: string, error: ?string}
     */
    public function initializeProject(string $projectName, array $options = []): array
    {
        if ($this->store === null) {
            return ['record_id' => null, 'outcome' => 'not_configured', 'error' => 'Memory Store is not configured.'];
        }

        $existing = $this->findProjectRecord($projectName);

        if ($existing !== null) {
            return ['record_id' => $existing['id'], 'outcome' => 'already_exists', 'error' => null];
        }

        $recordId = $this->store->store([
            'type' => 'project',
            'project_name' => $projectName,
            'architecture' => $options['architecture'] ?? null,
            'conventions' => $options['conventions'] ?? [],
            'decisions' => [],
            'milestones' => [],
            'risks' => [],
            'dependencies' => [],
            'notes' => $options['notes'] ?? [],
        ]);

        return ['record_id' => $recordId, 'outcome' => 'initialized', 'error' => null];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $projectName): ?array
    {
        return $this->findProjectRecord($projectName);
    }

    /**
     * @return array{outcome: string, error: ?string}
     */
    public function referenceDecision(string $projectName, string $decisionReference): array
    {
        return $this->appendToField($projectName, 'decisions', $decisionReference);
    }

    /**
     * @return array{outcome: string, error: ?string}
     */
    public function referenceMilestone(string $projectName, string $milestoneReference): array
    {
        return $this->appendToField($projectName, 'milestones', $milestoneReference);
    }

    /**
     * @return array{outcome: string, error: ?string}
     */
    public function referenceRisk(string $projectName, string $riskReference): array
    {
        return $this->appendToField($projectName, 'risks', $riskReference);
    }

    /**
     * @return array{outcome: string, error: ?string}
     */
    public function referenceDependency(string $projectName, string $dependencyReference): array
    {
        return $this->appendToField($projectName, 'dependencies', $dependencyReference);
    }

    /**
     * @return array{outcome: string, error: ?string}
     */
    public function addNote(string $projectName, string $note): array
    {
        return $this->appendToField($projectName, 'notes', $note);
    }

    /**
     * @param array<int, string> $conventions
     * @return array{outcome: string, error: ?string}
     */
    public function setConventions(string $projectName, array $conventions): array
    {
        return $this->setField($projectName, 'conventions', $conventions);
    }

    /**
     * @return array{outcome: string, error: ?string}
     */
    public function updateArchitecture(string $projectName, string $architectureReference): array
    {
        return $this->setField($projectName, 'architecture', $architectureReference);
    }

    /**
     * @return array{outcome: string, error: ?string}
     */
    private function appendToField(string $projectName, string $field, string $value): array
    {
        if ($this->store === null) {
            return ['outcome' => 'not_configured', 'error' => 'Memory Store is not configured.'];
        }

        $existing = $this->findProjectRecord($projectName);

        if ($existing === null) {
            return ['outcome' => 'not_found', 'error' => sprintf('Project "%s" has not been initialized.', $projectName)];
        }

        return $this->setField($projectName, $field, [...$existing[$field], $value]);
    }

    /**
     * @return array{outcome: string, error: ?string}
     */
    private function setField(string $projectName, string $field, mixed $value): array
    {
        if ($this->store === null) {
            return ['outcome' => 'not_configured', 'error' => 'Memory Store is not configured.'];
        }

        $existing = $this->findProjectRecord($projectName);

        if ($existing === null) {
            return ['outcome' => 'not_found', 'error' => sprintf('Project "%s" has not been initialized.', $projectName)];
        }

        $updated = $this->store->update($existing['id'], [$field => $value]);

        return $updated
            ? ['outcome' => 'recorded', 'error' => null]
            : ['outcome' => 'update_failed', 'error' => sprintf('Failed to update project "%s".', $projectName)];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findProjectRecord(string $projectName): ?array
    {
        $matches = $this->store?->search(['type' => 'project', 'project_name' => $projectName]) ?? [];

        return $matches[0] ?? null;
    }
}
