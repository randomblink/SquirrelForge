<?php

declare(strict_types=1);

namespace SquirrelForge\Agent\Roles;

/**
 * Implements the Agent Documentation role from `16_AGENTS/AGENT-DOCUMENTATION.md`.
 *
 * Requires that documentation updates were actually produced, and that the
 * required project-documentation checklist (README, changelog, release
 * notes) is satisfied -- any missing required item, or no updates at all,
 * returns `REVISION_REQUIRED` and stops the handoff. When the required
 * checklist is satisfied but the caller discloses deferred or incomplete
 * sections via "documentation_limitations", the stage returns
 * `COMPLETE_WITH_LIMITATIONS` and still hands off to Release; otherwise it
 * returns `COMPLETE`. `BLOCKED` is not produced automatically -- missing
 * required history already raises before this evaluation runs.
 */
final class DocumentationAgent extends AbstractRoleAgent
{
    private const REQUIRED_CHECKLIST_ITEMS = ['readme', 'changelog', 'release_notes'];

    public function stage(): string
    {
        return 'documentation';
    }

    public function getName(): string
    {
        return 'Agent Documentation';
    }

    public function getDescription(): string
    {
        return 'Ensures completed implementations are accompanied by accurate documentation.';
    }

    protected function process(array $context): array
    {
        $architect = $this->requireHistory($context, 'architect');
        $this->requireHistory($context, 'performance');

        if (array_key_exists('documentation_updates', $context)) {
            $updates = $context['documentation_updates'];
            $checklist = $context['documentation_checklist'] ?? [];
            $limitations = $context['documentation_limitations'] ?? [];
        } else {
            $reasoned = $this->reason(
                'Given the architecture blueprint and implementation, determine which ' .
                'documentation needs to be created or updated, per the checklist in ' .
                '16_AGENTS/AGENT-DOCUMENTATION.md. "updates" is a list of doc files/topics; ' .
                '"checklist" must contain boolean keys "readme", "changelog", "release_notes"; ' .
                '"limitations" is a list of disclosed gaps or deferred sections, empty if none.',
                ['updates', 'checklist', 'limitations'],
                [
                    'blueprint' => $architect['blueprint'] ?? [],
                    'implementation' => $context['history']['developer']['implementation'] ?? [],
                ]
            );

            $updates = $reasoned['updates'] ?? [];
            $checklist = $reasoned['checklist'] ?? [];
            $limitations = $reasoned['limitations'] ?? [];
        }

        $missing = array_values(array_filter(
            self::REQUIRED_CHECKLIST_ITEMS,
            static fn (string $item): bool => empty($checklist[$item])
        ));

        $status = match (true) {
            $updates === [] || $missing !== [] => 'REVISION_REQUIRED',
            $limitations !== [] => 'COMPLETE_WITH_LIMITATIONS',
            default => 'COMPLETE',
        };

        return [
            'documentation' => [
                'updates' => $updates,
                'checklist' => $checklist,
                'missing' => $missing,
                'limitations' => $limitations,
            ],
            'status' => $status,
            'next_stage' => in_array($status, ['COMPLETE', 'COMPLETE_WITH_LIMITATIONS'], true) ? 'release' : null,
        ];
    }
}
