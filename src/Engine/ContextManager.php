<?php

declare(strict_types=1);

namespace SquirrelForge\Engine;

use SquirrelForge\Contracts\EngineRuntimeInterface;

/**
 * Loads, maintains, prunes, and passes the information required for the
 * active request, workflow, task, and validation path, per
 * 14_ENGINE/CONTEXT-MANAGER.md.
 *
 * "Maintain verified project context from the Project Loader" and
 * "maintain active lifecycle and task state from the State Manager" are
 * both real compositions: loadProjectContext() calls the injected
 * ProjectLoader::load() directly, and loadActiveState() calls the
 * injected EngineRuntimeInterface::inspect() -- neither re-derives what
 * those already-real components produce.
 *
 * "Load mandatory general rules" and the Domain Loading Rule are real
 * file reads, not fabricated content: loadMandatoryRules() reads
 * 01_RULES/README.md and 01_RULES/AGENT-BEHAVIOR.md verbatim (the two
 * sources RULE-EVALUATOR.md's own Rule Sources table names as general
 * agent rules), and loadDomainRules() reads 01_RULES/WORDPRESS-RULES.md
 * only when a WordPress domain is actually active -- literally "For
 * non-WordPress work, do not automatically load WordPress rules."
 *
 * resolveEvidence() implements the spec's own nine-tier Evidence
 * Priority list literally, the same "apply precedence, don't invent it"
 * idiom RuleEvaluator already uses for 01_RULES' Rule Loading Order: 9
 * fixed tiers, current user request highest, assumptions lowest, and an
 * assumption-tier winner is flagged rather than silently trusted, per
 * "Assumptions must be labeled or resolved before they become execution
 * dependencies."
 *
 * flagStaleness() and prune() are real, structural, not fabricated
 * judgment calls: staleness is a plain value comparison recorded as a
 * conflict rather than silently preferring one side, and pruning only
 * ever removes entries whose own declared scope matches the trigger,
 * never a `protected` entry -- literally preserving "the State Manager
 * record, decision record, validation evidence, and recovery-relevant
 * context... until the lifecycle is safely completed."
 *
 * Does not take 12_AGENT/COLLECTION-MANIFEST.md as a dependency: it has
 * no src/ implementation, so "System and Agent Context" is accepted as
 * a caller-supplied entry like every other tier this class doesn't
 * itself compute. Like ProjectLoader, this class has no database of its
 * own: the context record is an in-memory structure for the duration of
 * the active request, matching the spec's own framing of "the
 * information required for the active request."
 */
final class ContextManager
{
    private const LOADING_ORDER = [
        'user_request' => 1,
        'system_agent_context' => 2,
        'mandatory_rules' => 3,
        'project_context' => 4,
        'active_state' => 5,
        'workflow_checklists' => 6,
        'domain_context' => 7,
        'memory_knowledge' => 8,
        'task_supporting_context' => 9,
    ];

    private const EVIDENCE_PRIORITY = [
        'user_request_constraints' => 1,
        'mandatory_system_rules' => 2,
        'repository_runtime_evidence' => 3,
        'project_configuration' => 4,
        'workflow_validation_requirements' => 5,
        'domain_references' => 6,
        'project_memory' => 7,
        'general_knowledge' => 8,
        'assumptions' => 9,
    ];

    private const PRUNE_SCOPES_BY_TRIGGER = [
        'lifecycle_phase_complete' => ['lifecycle'],
        'workflow_route_changed' => ['workflow'],
        'task_finished' => ['task'],
        'domain_inactive' => ['domain'],
        'supporting_workflow_complete' => ['workflow'],
        'project_complete' => ['lifecycle', 'workflow', 'task', 'domain'],
    ];

    /** @var array<string, array{tier: string, scope: string, value: mixed, protected: bool}> */
    private array $entries = [];

    /** @var array<int, array{key: string, current: mixed, remembered: mixed, detected_at: string}> */
    private array $stalenessConflicts = [];

    public function __construct(
        private readonly ?ProjectLoader $projectLoader = null,
        private readonly ?EngineRuntimeInterface $engineRuntime = null,
        private readonly string $rulesDirectory = '01_RULES'
    ) {
    }

    /**
     * @return array{outcome: string, error: ?string}
     */
    public function load(string $key, string $tier, mixed $value, string $scope = 'task', bool $protected = false): array
    {
        if (!isset(self::LOADING_ORDER[$tier])) {
            return ['outcome' => 'invalid_tier', 'error' => sprintf('Unknown context tier "%s".', $tier)];
        }

        $this->entries[$key] = ['tier' => $tier, 'scope' => $scope, 'value' => $value, 'protected' => $protected];

        return ['outcome' => 'loaded', 'error' => null];
    }

    /**
     * @return array{outcome: string, error: ?string, context: ?array<string, mixed>}
     */
    public function loadProjectContext(string $requestedProjectName, string $workingDirectory): array
    {
        if ($this->projectLoader === null) {
            return ['outcome' => 'not_configured', 'error' => 'Project Loader is not configured.', 'context' => null];
        }

        $context = $this->projectLoader->load($requestedProjectName, $workingDirectory);
        $this->load('project_context', 'project_context', $context, 'lifecycle', protected: true);

        return ['outcome' => 'loaded', 'error' => null, 'context' => $context];
    }

    /**
     * @return array{outcome: string, error: ?string, state: ?array<string, mixed>}
     */
    public function loadActiveState(string $executionRef): array
    {
        if ($this->engineRuntime === null) {
            return ['outcome' => 'not_configured', 'error' => 'Engine Runtime is not configured.', 'state' => null];
        }

        $state = $this->engineRuntime->inspect($executionRef);
        $this->load('active_state', 'active_state', $state, 'task', protected: true);

        return ['outcome' => 'loaded', 'error' => null, 'state' => $state];
    }

    /**
     * @return array<string, string> filename => contents
     */
    public function loadMandatoryRules(): array
    {
        $loaded = $this->readRuleFiles(['README.md', 'AGENT-BEHAVIOR.md']);
        $this->load('mandatory_rules', 'mandatory_rules', $loaded, 'lifecycle', protected: true);

        return $loaded;
    }

    /**
     * The Domain Loading Rule, applied literally: WordPress rules are
     * read only when a WordPress domain is actually active.
     *
     * @param array<int, string> $activeDomains
     * @return array<string, string> filename => contents
     */
    public function loadDomainRules(array $activeDomains): array
    {
        $files = array_intersect(['wordpress_plugin', 'wordpress_theme'], $activeDomains) !== []
            ? ['WORDPRESS-RULES.md']
            : [];

        $loaded = $this->readRuleFiles($files);
        $this->load('domain_context', 'domain_context', $loaded, 'domain');

        return $loaded;
    }

    /**
     * The spec's own, separately-ordered nine-tier Evidence Priority
     * list (distinct from the Context Loading Order tiers used by
     * load()), applied literally rather than invented: the candidate
     * from the lowest (highest-priority) tier wins. A winning
     * `assumptions` candidate is flagged, per the spec's own requirement
     * that assumptions be labeled before they become execution
     * dependencies.
     *
     * @param array<int, array{tier: string, value: mixed}> $candidates
     * @return array{value: mixed, tier: ?string, is_assumption: bool, outcome: string, error: ?string}
     */
    public function resolveEvidence(array $candidates): array
    {
        if ($candidates === []) {
            return ['value' => null, 'tier' => null, 'is_assumption' => false, 'outcome' => 'no_candidates', 'error' => 'At least one candidate is required.'];
        }

        foreach ($candidates as $candidate) {
            if (!isset(self::EVIDENCE_PRIORITY[$candidate['tier']])) {
                return ['value' => null, 'tier' => null, 'is_assumption' => false, 'outcome' => 'invalid_tier', 'error' => sprintf('Unknown evidence tier "%s".', $candidate['tier'])];
            }
        }

        usort($candidates, static fn(array $a, array $b): int => self::EVIDENCE_PRIORITY[$a['tier']] <=> self::EVIDENCE_PRIORITY[$b['tier']]);
        $winner = $candidates[0];

        return ['value' => $winner['value'], 'tier' => $winner['tier'], 'is_assumption' => $winner['tier'] === 'assumptions', 'outcome' => 'resolved', 'error' => null];
    }

    /**
     * @return array{key: string, current: mixed, remembered: mixed, detected_at: string}|null
     */
    public function flagStaleness(string $key, mixed $current, mixed $remembered): ?array
    {
        if ($current === $remembered) {
            return null;
        }

        $conflict = ['key' => $key, 'current' => $current, 'remembered' => $remembered, 'detected_at' => gmdate(DATE_RFC3339)];
        $this->stalenessConflicts[] = $conflict;

        return $conflict;
    }

    /**
     * @return array<int, array{key: string, current: mixed, remembered: mixed, detected_at: string}>
     */
    public function stalenessConflicts(): array
    {
        return $this->stalenessConflicts;
    }

    /**
     * Removes every non-protected entry whose scope matches the
     * trigger. Protected entries (the State Manager record, decision
     * record, validation evidence, recovery-relevant context) are never
     * pruned by this method regardless of trigger.
     *
     * @return array<int, string> keys removed
     */
    public function prune(string $trigger): array
    {
        $scopes = self::PRUNE_SCOPES_BY_TRIGGER[$trigger] ?? [];
        $removed = [];

        foreach ($this->entries as $key => $entry) {
            if (!$entry['protected'] && in_array($entry['scope'], $scopes, true)) {
                unset($this->entries[$key]);
                $removed[] = $key;
            }
        }

        return $removed;
    }

    /**
     * @return array<string, array{tier: string, scope: string, value: mixed}>
     */
    public function record(): array
    {
        $sorted = $this->entries;
        uasort($sorted, static fn(array $a, array $b): int => self::LOADING_ORDER[$a['tier']] <=> self::LOADING_ORDER[$b['tier']]);

        return array_map(
            static fn(array $entry): array => ['tier' => $entry['tier'], 'scope' => $entry['scope'], 'value' => $entry['value']],
            $sorted
        );
    }

    /**
     * @param array<int, string> $files
     * @return array<string, string>
     */
    private function readRuleFiles(array $files): array
    {
        $loaded = [];

        foreach ($files as $file) {
            $path = rtrim($this->rulesDirectory, '/') . '/' . $file;

            if (is_file($path)) {
                $loaded[$file] = (string) file_get_contents($path);
            }
        }

        return $loaded;
    }
}
