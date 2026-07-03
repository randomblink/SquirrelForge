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
        $this->requireHistory($context, 'performance');

        $updates = $context['documentation_updates'] ?? [];
        $checklist = $context['documentation_checklist'] ?? [];

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
