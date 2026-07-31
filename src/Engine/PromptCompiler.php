<?php

declare(strict_types=1);

namespace SquirrelForge\Engine;

/**
 * Assembles the final, formatted prompt string sent to the LLM from
 * Context Manager's own structured record, per
 * 14_ENGINE/PROMPT-COMPILER.md.
 *
 * Rule 2 ("must not load or generate context itself; it must only
 * format the context provided by the Context Manager") is upheld
 * literally: compile() calls the injected ContextManager::record() --
 * the same real, already-built record this session's ContextManager
 * produces -- and does nothing but render its existing tiers into the
 * five fixed sections the Prompt Assembly Order defines. It never reads
 * a rule file, calls Project Loader, or invents content of its own.
 *
 * Rule 1 (Strict Ordering) is upheld by SECTION_TIERS: a fixed map from
 * each of the spec's five section headings to the Context Manager
 * tier(s) that section draws from (Core System Rules <-
 * `mandatory_rules`, Project Context <- `project_context`,
 * Domain-Specific Knowledge <- `domain_context`, Active Workflow State
 * <- `workflow_checklists`/`active_state`, Current Task <-
 * `user_request` for Goal and a caller-named tier, defaulting to
 * `task_supporting_context`, for Evidence). Rule 3 (Clarity and
 * Separation) is upheld by rendering every section under its own `#`
 * heading, separated by a literal `---` rule, exactly matching the
 * spec's own worked template.
 *
 * Rule 4 (Traceability) is a real, deterministic sha256 hash of the
 * compiled prompt string returned alongside it -- genuine evidence a
 * caller can log, not a fabricated audit trail this class doesn't own.
 */
final class PromptCompiler
{
    private const SECTION_TIERS = [
        'Core System Rules' => ['mandatory_rules'],
        'Project Context' => ['project_context'],
        'Domain-Specific Knowledge' => ['domain_context'],
        'Active Workflow State' => ['workflow_checklists', 'active_state'],
    ];

    public function __construct(private readonly ?ContextManager $contextManager = null)
    {
    }

    /**
     * @param array{evidence_tiers?: array<int, string>} $options
     * @return array{prompt: ?string, sections: array<string, string>, prompt_hash: ?string, outcome: string, error: ?string}
     */
    public function compile(array $options = []): array
    {
        if ($this->contextManager === null) {
            return ['prompt' => null, 'sections' => [], 'prompt_hash' => null, 'outcome' => 'not_configured', 'error' => 'Context Manager is not configured.'];
        }

        $record = $this->contextManager->record();
        $sections = [];

        foreach (self::SECTION_TIERS as $heading => $tiers) {
            $sections[$heading] = $this->renderSection($record, $tiers);
        }

        $evidenceTiers = $options['evidence_tiers'] ?? ['task_supporting_context'];
        $goalContent = $this->renderTierValues($record, ['user_request']);
        $evidenceContent = $this->renderTierValues($record, $evidenceTiers);

        $sections['Current Task'] = sprintf(
            "## Goal\n%s\n\n## Evidence\n%s",
            $goalContent !== '' ? $goalContent : '_No goal recorded._',
            $evidenceContent !== '' ? $evidenceContent : '_No evidence recorded._'
        );

        $prompt = implode("\n\n---\n\n", array_map(
            static fn(string $heading, string $body): string => sprintf("# %s\n%s", $heading, $body),
            array_keys($sections),
            $sections
        ));

        return ['prompt' => $prompt, 'sections' => $sections, 'prompt_hash' => hash('sha256', $prompt), 'outcome' => 'compiled', 'error' => null];
    }

    /**
     * @param array<string, array{tier: string, scope: string, value: mixed}> $record
     * @param array<int, string> $tiers
     */
    private function renderSection(array $record, array $tiers): string
    {
        $content = $this->renderTierValues($record, $tiers);

        return $content !== '' ? $content : '_No content loaded for this section._';
    }

    /**
     * @param array<string, array{tier: string, scope: string, value: mixed}> $record
     * @param array<int, string> $tiers
     */
    private function renderTierValues(array $record, array $tiers): string
    {
        $parts = [];

        foreach ($record as $key => $entry) {
            if (in_array($entry['tier'], $tiers, true)) {
                $parts[] = sprintf('**%s**: %s', $key, $this->stringify($entry['value']));
            }
        }

        return implode("\n", $parts);
    }

    private function stringify(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        return json_encode($value, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    }
}
