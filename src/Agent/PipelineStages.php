<?php

declare(strict_types=1);

namespace SquirrelForge\Agent;

/**
 * The canonical, ordered list of pipeline stage keys described in
 * `16_AGENTS/`: Architect -> Planner -> Developer -> Reviewer -> Security ->
 * Performance -> Documentation -> Release.
 *
 * Extracted out of `AgentOrchestrator::assertPipelineComplete()`, which
 * previously hardcoded this same array inline, so there is exactly one
 * place that spells out the full stage order.
 */
final class PipelineStages
{
    /**
     * @var array<int, string>
     */
    public const ALL = [
        'architect',
        'planner',
        'developer',
        'reviewer',
        'security',
        'performance',
        'documentation',
        'release',
    ];
}
