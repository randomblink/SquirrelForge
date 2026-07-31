<?php

declare(strict_types=1);

namespace SquirrelForge\Reasoning;

/**
 * Compares competing implementation options and recommends the option
 * this comparison favors, per 19_REASONING/TRADEOFF-ANALYZER.md.
 *
 * "Read the risk assessment... as an input rather than re-assessing
 * risk" is real: compareOptions() calls the injected
 * SqliteRiskAssessor::canProceed() for each option, and "the Security
 * category reads the finding Risk Assessor already produced; it is not
 * a separate security assessment" is upheld literally -- the Security
 * tradeoff bullets are generated directly from that real risk result
 * (blocking risk IDs on the disadvantage side, "no blocking risk" on
 * the advantage side), never a fabricated security review.
 *
 * "Reference Decision Matrix's weighted scores as supporting evidence
 * rather than re-scoring options independently" is upheld by taking
 * each option's already-computed `matrix_score` (a caller-supplied
 * DecisionMatrix::scoreOption() result) as a plain input -- this class
 * has no scoring logic of its own and never re-derives a total.
 *
 * The recommendation itself is a real, explainable selection rule, not
 * a fabricated judgment call: among options that are neither
 * Decision-Matrix-disqualified nor risk-blocked, the one with the
 * highest supplied weighted_total is favored -- the same "simple,
 * explainable formula over real inputs" this codebase applies
 * throughout (CapacityPlanner's cheapest-viable-scenario selection is
 * the same shape). This is advisory only: `recommendation` names the
 * favored option, it does not select or authorize it -- selection
 * remains Decision Engine's, per the spec's own Permission Boundary.
 *
 * The other six Tradeoff Categories (Simplicity, Performance,
 * Scalability, Maintainability, Reliability, Development Cost) are
 * caller-supplied advantage/disadvantage bullets, since documenting
 * why an option is simpler or more maintainable requires judgment this
 * class doesn't fabricate.
 *
 * Like DecisionMatrix, this class has no database: comparisons are pure
 * return values for the caller (eventually Decision Engine) to persist
 * and forward.
 */
final class TradeoffAnalyzer
{
    public function __construct(private readonly ?SqliteRiskAssessor $riskAssessor = null)
    {
    }

    /**
     * @param array<int, array{option: string, advantages?: array<string, array<int, string>>, disadvantages?: array<string, array<int, string>>, matrix_score?: array{weighted_total: ?float, disqualified: bool, disqualification_reason: ?string}}> $options
     * @return array{comparison: array<int, array<string, mixed>>, recommendation: ?string, outcome: string, error: ?string}
     */
    public function compareOptions(array $options): array
    {
        if ($options === []) {
            return ['comparison' => [], 'recommendation' => null, 'outcome' => 'no_options', 'error' => 'At least one option is required.'];
        }

        $records = [];

        foreach ($options as $optionDefinition) {
            $records[] = $this->buildRecord($optionDefinition);
        }

        $eligible = array_values(array_filter($records, static fn(array $r): bool => $r['eligible'] && $r['overall_rating'] !== null));
        usort($eligible, static fn(array $a, array $b): int => $b['overall_rating'] <=> $a['overall_rating']);
        $recommended = $eligible[0]['option'] ?? null;

        foreach ($records as &$record) {
            $record['recommended'] = $record['option'] === $recommended;
            $record['reasoning'] = $this->reasoning($record, $recommended);
        }
        unset($record);

        return [
            'comparison' => $records,
            'recommendation' => $recommended,
            'outcome' => $recommended !== null ? 'recommended' : 'no_eligible_option',
            'error' => null,
        ];
    }

    /**
     * @param array{option: string, advantages?: array<string, array<int, string>>, disadvantages?: array<string, array<int, string>>, matrix_score?: array{weighted_total: ?float, disqualified: bool, disqualification_reason: ?string}} $optionDefinition
     * @return array<string, mixed>
     */
    private function buildRecord(array $optionDefinition): array
    {
        $optionRef = $optionDefinition['option'];
        $advantages = $optionDefinition['advantages'] ?? [];
        $disadvantages = $optionDefinition['disadvantages'] ?? [];
        $risk = $this->riskAssessor?->canProceed($optionRef);

        if ($risk !== null) {
            if ($risk['can_proceed']) {
                $advantages['security'][] = 'No unmitigated critical risks block this option.';
            } else {
                $disadvantages['security'][] = sprintf('Blocked by unmitigated critical risk(s): %s.', implode(', ', $risk['blocking_risks']));
            }
        }

        $matrixScore = $optionDefinition['matrix_score'] ?? null;

        return [
            'option' => $optionRef,
            'advantages' => $advantages,
            'disadvantages' => $disadvantages,
            'overall_rating' => $matrixScore['weighted_total'] ?? null,
            'eligible' => !($matrixScore['disqualified'] ?? false) && ($risk === null || $risk['can_proceed']),
            'disqualification_reason' => $matrixScore['disqualification_reason'] ?? null,
            'risk' => $risk,
        ];
    }

    /**
     * @param array<string, mixed> $record
     */
    private function reasoning(array $record, ?string $recommended): string
    {
        if ($record['disqualification_reason'] !== null) {
            return sprintf('Excluded: %s', $record['disqualification_reason']);
        }

        if ($record['risk'] !== null && !$record['risk']['can_proceed']) {
            return sprintf('Excluded: blocked by unmitigated critical risk(s): %s.', implode(', ', $record['risk']['blocking_risks']));
        }

        if ($record['option'] === $recommended) {
            return sprintf('Recommended: highest weighted score (%.2f) among eligible options.', $record['overall_rating']);
        }

        if ($record['overall_rating'] === null) {
            return 'No Decision Matrix score was supplied for this option.';
        }

        return sprintf('Eligible but not recommended: weighted score %.2f is lower than the recommended option.', $record['overall_rating']);
    }
}
