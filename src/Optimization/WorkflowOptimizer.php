<?php

declare(strict_types=1);

namespace SquirrelForge\Optimization;

use DateTimeImmutable;
use SquirrelForge\Observability\TraceManager;
use Throwable;

/**
 * Owns analysis and proposals for workflow structure, sequencing,
 * dependency efficiency, safe parallelization opportunities, and
 * redundant-step reduction, per
 * 32_OPTIMIZATION/WORKFLOW-OPTIMIZER.md.
 *
 * Does not take SquirrelForge\Workflow\WorkflowEngine or StepWorkflow
 * as a dependency: StepWorkflow::getSteps() exposes only a flat,
 * ordered list of steps with no declared dependency relationship
 * between them, so there is no real graph to introspect from the
 * running engine. analyzeParallelizationOpportunities() instead
 * consumes a caller-supplied step dependency graph directly -- the same
 * "consume the caller's real declaration" honesty RuleEngine's own
 * conflict detection already applies -- and re-implements
 * TaskOrchestrator's own real Kahn's-algorithm leveling plus its
 * duplicate-id/cycle detection. This is a deliberate re-implementation,
 * not an extraction: the two operate over unrelated data (automation
 * tasks vs. workflow steps) and the algorithm is small and
 * well-understood, matching this codebase's established preference for
 * duplicating a well-understood algorithm over a premature shared
 * abstraction (the same call PatternDetector/DiagnosticsEngine made for
 * z-score anomaly detection).
 *
 * analyzeExecutionPattern() is a genuine composition of Trace Manager's
 * real executionPath(): two spans that actually ran strictly
 * sequentially (the earlier span's real ended_at is before the later
 * span's real started_at) without a declared parent/child relationship
 * are reported as an observed parallelization candidate -- evidence
 * read directly from real span records, not inferred from a static
 * graph the way analyzeParallelizationOpportunities() is.
 *
 * Findings carry `type` and `evidence` keys matching
 * OptimizationEngine::createProposal()'s `$opportunity` shape, the same
 * composition contract every other Optimizer in this category honors.
 * Like its siblings, this class has no database: the Boundary forbids
 * owning "audit infrastructure".
 */
final class WorkflowOptimizer
{
    public function __construct(private readonly ?TraceManager $traces = null)
    {
    }

    /**
     * @param array<int, array{id: string, depends_on?: array<int, string>}> $steps
     * @return array{findings: array<int, array<string, mixed>>, outcome: string, error: ?string}
     */
    public function analyzeParallelizationOpportunities(array $steps): array
    {
        $structuralError = $this->checkCompleteness($steps);

        if ($structuralError !== null) {
            return ['findings' => [], 'outcome' => 'invalid_graph', 'error' => $structuralError];
        }

        $levels = $this->computeLevels($steps);

        if ($levels === null) {
            return ['findings' => [], 'outcome' => 'invalid_graph', 'error' => 'Step dependency graph contains a cycle.'];
        }

        $findings = [];

        foreach ($levels as $index => $level) {
            if (count($level) > 1) {
                $findings[] = [
                    'type' => 'parallelization_opportunity',
                    'evidence' => ['level' => $index, 'step_ids' => $level],
                    'measurable_target' => sprintf('Run steps [%s] concurrently instead of sequentially.', implode(', ', $level)),
                    'improvement_proposal' => sprintf('Steps %s share no dependency relationship and currently sit at the same graph level -- candidates for safe parallel execution.', implode(', ', $level)),
                ];
            }
        }

        return ['findings' => $findings, 'outcome' => 'analyzed', 'error' => null];
    }

    /**
     * @return array{findings: array<int, array<string, mixed>>, outcome: string, error: ?string}
     */
    public function analyzeExecutionPattern(string $traceId): array
    {
        if ($this->traces === null) {
            return ['findings' => [], 'outcome' => 'not_configured', 'error' => 'Trace Manager is not configured.'];
        }

        $spans = $this->traces->executionPath($traceId)['spans'];
        $findings = [];

        for ($i = 1; $i < count($spans); $i++) {
            $previous = $spans[$i - 1];
            $current = $spans[$i];

            if (($current['parent_span_id'] ?? null) === ($previous['span_id'] ?? null)) {
                continue;
            }

            $previousEnded = $this->parseTimestamp($previous['ended_at'] ?? null);
            $currentStarted = $this->parseTimestamp($current['started_at'] ?? null);

            if ($previousEnded === null || $currentStarted === null || $currentStarted < $previousEnded) {
                continue;
            }

            $findings[] = [
                'type' => 'sequential_without_dependency',
                'trace_id' => $traceId,
                'evidence' => ['preceding_span' => $previous['span_id'], 'following_span' => $current['span_id']],
                'measurable_target' => sprintf('Run span "%s" concurrently with "%s" if their data dependencies allow it.', $current['span_id'], $previous['span_id']),
                'improvement_proposal' => sprintf('Span "%s" ran strictly after "%s" in trace "%s" with no declared parent/child relationship between them -- a candidate for parallel execution.', $current['span_id'], $previous['span_id'], $traceId),
            ];
        }

        return ['findings' => $findings, 'outcome' => 'analyzed', 'error' => null];
    }

    /**
     * @param array<int, array{id: string, depends_on?: array<int, string>}> $steps
     */
    private function checkCompleteness(array $steps): ?string
    {
        $ids = array_column($steps, 'id');

        foreach (array_count_values($ids) as $id => $count) {
            if ($count > 1) {
                return sprintf('Duplicate step identifier "%s".', $id);
            }
        }

        foreach ($steps as $step) {
            foreach ($step['depends_on'] ?? [] as $dependency) {
                if (!in_array($dependency, $ids, true)) {
                    return sprintf('Step "%s" depends on unknown step "%s".', $step['id'], $dependency);
                }
            }
        }

        return null;
    }

    /**
     * Kahn's algorithm, matching TaskOrchestrator's own real leveling.
     *
     * @param array<int, array{id: string, depends_on?: array<int, string>}> $steps
     * @return array<int, array<int, string>>|null
     */
    private function computeLevels(array $steps): ?array
    {
        $remainingDependencies = [];

        foreach ($steps as $step) {
            $remainingDependencies[$step['id']] = $step['depends_on'] ?? [];
        }

        $levels = [];

        while ($remainingDependencies !== []) {
            $ready = array_keys(array_filter($remainingDependencies, static fn(array $deps): bool => $deps === []));

            if ($ready === []) {
                return null;
            }

            $levels[] = $ready;

            foreach ($ready as $id) {
                unset($remainingDependencies[$id]);
            }

            foreach ($remainingDependencies as $id => $deps) {
                $remainingDependencies[$id] = array_values(array_diff($deps, $ready));
            }
        }

        return $levels;
    }

    private function parseTimestamp(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Throwable) {
            return null;
        }
    }
}
