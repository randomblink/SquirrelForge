<?php

declare(strict_types=1);

namespace SquirrelForge\Agent\Roles;

/**
 * Implements the Agent Documentation role from `16_AGENTS/AGENT-DOCUMENTATION.md`.
 *
 * Requires that documentation updates were actually produced, and that the
 * standard project-documentation checklist (README, changelog, release
 * notes) is satisfied before handing off to Release.
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
        } else {
            $reasoned = $this->reason(
                'Given the architecture blueprint and implementation, determine which ' .
                'documentation needs to be created or updated, per the checklist in ' .
                '16_AGENTS/AGENT-DOCUMENTATION.md. "updates" is a list of doc files/topics; ' .
                '"checklist" must contain boolean keys "readme", "changelog", "release_notes".',
                ['updates', 'checklist'],
                [
                    'blueprint' => $architect['blueprint'] ?? [],
                    'implementation' => $context['history']['developer']['implementation'] ?? [],
                ]
            );

            $updates = $reasoned['updates'] ?? [];
            $checklist = $reasoned['checklist'] ?? [];
        }

        $missing = array_values(array_filter(
            self::REQUIRED_CHECKLIST_ITEMS,
            static fn (string $item): bool => empty($checklist[$item])
        ));

        $status = ($updates !== [] && $missing === []) ? 'Complete' : 'Revision Required';

        return [
            'documentation' => [
                'updates' => $updates,
                'checklist' => $checklist,
                'missing' => $missing,
            ],
            'status' => $status,
            'next_stage' => $status === 'Complete' ? 'release' : null,
        ];
    }
}
