<?php

declare(strict_types=1);

namespace SquirrelForge\Configuration;

use SquirrelForge\Engine\WorkflowSelector;

/**
 * Defines project identity, root, technology profile, standards,
 * required workflows, test commands, release policy, and allowed
 * overrides, per 21_CONFIGURATION/PROJECT-SETTINGS.md -- the second
 * real component in 21_CONFIGURATION.
 *
 * "Project Settings references and selects; it does not author these"
 * is the whole shape of this class: `standards` (references into
 * `01_RULES`, prose documents with no runtime shape to validate
 * against, the same reasoning `RuleEvaluator` already applies to that
 * same layer) and `release_policy_ref` (a `23_GOVERNANCE`-approved
 * policy reference) are carried forward as opaque, caller-supplied
 * strings this class never authors or judges.
 *
 * `required_workflows` is different: `14_ENGINE/WORKFLOW-SELECTOR.md`
 * is real and already exposes `listAvailableWorkflows()`, a genuine
 * filesystem-backed list of the workflows that actually exist -- "not
 * a competing selection" (Purpose) is upheld by never calling
 * `selectWorkflow()` (this class picks nothing), only cross-checking
 * that every referenced workflow name is one `WorkflowSelector` itself
 * can already find, the same "verify against the real, already-built
 * component's own data" discipline `ExecutionReporter`/
 * `SqliteHandoffProtocol` already apply to `EngineValidation`'s
 * decision vocabulary.
 *
 * "Each override must state its source and must not weaken mandatory
 * governance or security policy" is genuine composition of the
 * just-built `Defaults::validateOverride()` -- this class never
 * re-implements that gate, every override in `$settings['overrides']`
 * is run through the real method, and only the ones it accepts become
 * part of the defined project settings. An override rejected by that
 * gate is surfaced in its own `rejected_overrides` list, not silently
 * dropped or silently applied anyway.
 *
 * Owns no database, matching `Defaults`' own pure-value shape: this
 * spec names no persistence responsibility of its own, so `define()`
 * is a pure function over caller-supplied settings, not an
 * incrementally maintained project-config store.
 */
final class ProjectSettings
{
    public function __construct(
        private readonly ?Defaults $defaults = null,
        private readonly ?WorkflowSelector $workflowSelector = null
    ) {
    }

    /**
     * @param array{
     *     project_name?: ?string,
     *     root?: ?string,
     *     technology_profile?: array<int, string>,
     *     standards?: array<int, string>,
     *     required_workflows?: array<int, string>,
     *     test_commands?: array<int, string>,
     *     release_policy_ref?: ?string,
     *     overrides?: array<int, array{key: string, value: mixed, source: ?string}>
     * } $settings
     * @return array{
     *     outcome: string,
     *     project_name: ?string,
     *     root: ?string,
     *     technology_profile: array<int, string>,
     *     standards: array<int, string>,
     *     required_workflows: array<int, string>,
     *     test_commands: array<int, string>,
     *     release_policy_ref: ?string,
     *     accepted_overrides: array<string, mixed>,
     *     rejected_overrides: array<int, array<string, mixed>>,
     *     error: ?string
     * }
     */
    public function define(array $settings): array
    {
        $projectName = $settings['project_name'] ?? null;
        $root = $settings['root'] ?? null;

        if (!is_string($projectName) || $projectName === '' || !is_string($root) || $root === '') {
            return $this->result('invalid', null, null, [], [], [], [], null, [], [], 'Project settings require a non-empty project_name and root.');
        }

        $requiredWorkflows = $settings['required_workflows'] ?? [];
        $unknownWorkflows = $this->unknownWorkflows($requiredWorkflows);

        if ($unknownWorkflows !== []) {
            return $this->result('invalid', $projectName, $root, [], [], [], [], null, [], [], sprintf('required_workflows references unknown workflow(s) not found by WorkflowSelector: %s.', implode(', ', $unknownWorkflows)));
        }

        [$accepted, $rejected] = $this->applyOverrides($settings['overrides'] ?? []);

        return $this->result(
            'defined',
            $projectName,
            $root,
            $settings['technology_profile'] ?? [],
            $settings['standards'] ?? [],
            $requiredWorkflows,
            $settings['test_commands'] ?? [],
            $settings['release_policy_ref'] ?? null,
            $accepted,
            $rejected,
            null
        );
    }

    /**
     * @param array<int, string> $requiredWorkflows
     * @return array<int, string>
     */
    private function unknownWorkflows(array $requiredWorkflows): array
    {
        if ($this->workflowSelector === null || $requiredWorkflows === []) {
            return [];
        }

        $available = $this->workflowSelector->listAvailableWorkflows();

        return array_values(array_filter($requiredWorkflows, static fn(string $workflow): bool => !in_array($workflow, $available, true)));
    }

    /**
     * @param array<int, array{key: string, value: mixed, source: ?string}> $overrides
     * @return array{0: array<string, mixed>, 1: array<int, array<string, mixed>>}
     */
    private function applyOverrides(array $overrides): array
    {
        $accepted = [];
        $rejected = [];

        foreach ($overrides as $override) {
            if ($this->defaults === null) {
                $rejected[] = ['key' => $override['key'] ?? null, 'value' => $override['value'] ?? null, 'source' => $override['source'] ?? null, 'error' => 'No Defaults component is configured to validate this override against.'];

                continue;
            }

            $validated = $this->defaults->validateOverride($override['key'] ?? '', $override['value'] ?? null, $override['source'] ?? null);

            if ($validated['outcome'] === 'accepted') {
                $accepted[$validated['key']] = $validated['value'];
            } else {
                $rejected[] = $validated;
            }
        }

        return [$accepted, $rejected];
    }

    /**
     * @param array<int, string> $technologyProfile
     * @param array<int, string> $standards
     * @param array<int, string> $requiredWorkflows
     * @param array<int, string> $testCommands
     * @param array<string, mixed> $acceptedOverrides
     * @param array<int, array<string, mixed>> $rejectedOverrides
     * @return array{
     *     outcome: string,
     *     project_name: ?string,
     *     root: ?string,
     *     technology_profile: array<int, string>,
     *     standards: array<int, string>,
     *     required_workflows: array<int, string>,
     *     test_commands: array<int, string>,
     *     release_policy_ref: ?string,
     *     accepted_overrides: array<string, mixed>,
     *     rejected_overrides: array<int, array<string, mixed>>,
     *     error: ?string
     * }
     */
    private function result(
        string $outcome,
        ?string $projectName,
        ?string $root,
        array $technologyProfile,
        array $standards,
        array $requiredWorkflows,
        array $testCommands,
        ?string $releasePolicyRef,
        array $acceptedOverrides,
        array $rejectedOverrides,
        ?string $error
    ): array {
        return [
            'outcome' => $outcome,
            'project_name' => $projectName,
            'root' => $root,
            'technology_profile' => $technologyProfile,
            'standards' => $standards,
            'required_workflows' => $requiredWorkflows,
            'test_commands' => $testCommands,
            'release_policy_ref' => $releasePolicyRef,
            'accepted_overrides' => $acceptedOverrides,
            'rejected_overrides' => $rejectedOverrides,
            'error' => $error,
        ];
    }
}
