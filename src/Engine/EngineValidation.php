<?php

declare(strict_types=1);

namespace SquirrelForge\Engine;

/**
 * Aggregates pre-decided validation item statuses into stage-ordered
 * gating and a single standardized decision, per
 * 14_ENGINE/VALIDATION.md.
 *
 * "Engine Validation does not perform every check itself" is upheld
 * literally: evaluate() never runs a test, check, or review -- it
 * consumes items whose `status` authoritative owners have already
 * decided (a real test report, a real security review, a real policy
 * evaluation), and aggregates them. This mirrors how every Sqlite*
 * Governance component in this codebase consumes an already-decided
 * security_decision/policy_result rather than computing one itself.
 *
 * Stage ordering and blocking are real and enforced: items are grouped
 * into the spec's fixed eight stages (INPUT, STRUCTURE, RULE_POLICY,
 * ACCEPTANCE, QUALITY, RUNTIME, RECOVERY, OUTPUT) and evaluated in that
 * order; a required, blocking item that isn't PASSED/WAIVED/
 * NOT_REQUIRED stops every later stage from being evaluated at all --
 * "stop dependent validation after a required blocking failure" --
 * those items are reported CANCELLED, not silently passed over.
 *
 * The decision table is a real, deterministic, fail-closed priority
 * ladder matching the spec's own "Acceptance and Rejection Rules": a
 * non-waivable required FAILED item always produces REJECTED, ahead of
 * everything else, per the spec's rule that ACCEPTED_WITH_LIMITATIONS
 * "is prohibited when the unavailable or waived item is a non-waivable
 * safety, security, authorization, privacy, integrity, or mandatory
 * policy gate." A repairable failure within the caller-supplied
 * remaining-attempt budget produces REPAIR_REQUIRED; a non-waivable
 * UNAVAILABLE item produces BLOCKED; a waivable UNAVAILABLE/WAIVED item
 * produces ACCEPTED_WITH_LIMITATIONS; explicit recovery/clarification
 * flags (consumed, not inferred -- this class cannot detect an unsafe
 * environment or a material ambiguity on its own) produce
 * RECOVERY_REQUIRED/CLARIFICATION_REQUIRED; everything required PASSED
 * or NOT_REQUIRED produces ACCEPTED; any other combination fails
 * closed to REJECTED, per the spec's explicit principle that validation
 * "must fail closed" rather than default to acceptance.
 *
 * This class does not own persistence or the retry/repair loop's
 * live orchestration across multiple attempts over time -- the spec
 * assigns that to the State Manager (14_ENGINE/STATE-MANAGER.md, no
 * code yet). evaluate() is a pure function from one set of items to one
 * decision; a caller re-invokes it across attempts itself.
 */
final class EngineValidation
{
    private const STAGES = ['INPUT', 'STRUCTURE', 'RULE_POLICY', 'ACCEPTANCE', 'QUALITY', 'RUNTIME', 'RECOVERY', 'OUTPUT'];
    private const STATUSES = ['NOT_REQUIRED', 'REQUIRED', 'PENDING', 'PASSED', 'FAILED', 'UNAVAILABLE', 'WAIVED', 'STALE', 'CANCELLED'];
    private const PASSING_STATUSES = ['PASSED', 'NOT_REQUIRED'];

    /**
     * @param array<int, array{item_id: string, stage: string, required: bool, blocking?: bool, status: string, waivable?: bool, repairable?: bool}> $items
     * @param array{remaining_attempts?: int, recovery_required?: bool, clarification_needed?: bool} $options
     * @return array{decision: string, stages: array<string, array{status: string, evaluated: bool}>, items: array<int, array<string, mixed>>, summary: array<string, int>}
     */
    public function evaluate(array $items, array $options = []): array
    {
        $normalized = $this->normalize($items);
        [$evaluatedItems, $stageResults] = $this->applyStageGating($normalized);
        $summary = $this->summarize($evaluatedItems);
        $decision = $this->decide($evaluatedItems, $options);

        return ['decision' => $decision, 'stages' => $stageResults, 'items' => $evaluatedItems, 'summary' => $summary];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function normalize(array $items): array
    {
        return array_map(function (array $item): array {
            $status = in_array($item['status'] ?? null, self::STATUSES, true) ? $item['status'] : 'FAILED';

            return [
                'item_id' => $item['item_id'],
                'stage' => in_array($item['stage'] ?? null, self::STAGES, true) ? $item['stage'] : 'INPUT',
                'required' => $item['required'] ?? true,
                'blocking' => $item['blocking'] ?? ($item['required'] ?? true),
                'status' => $status,
                'waivable' => $item['waivable'] ?? false,
                'repairable' => $item['repairable'] ?? false,
            ];
        }, $items);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array{0: array<int, array<string, mixed>>, 1: array<string, array{status: string, evaluated: bool}>}
     */
    private function applyStageGating(array $items): array
    {
        $itemsByStage = [];

        foreach ($items as $item) {
            $itemsByStage[$item['stage']][] = $item;
        }

        $stageResults = [];
        $blocked = false;
        $evaluatedItems = [];

        foreach (self::STAGES as $stage) {
            $stageItems = $itemsByStage[$stage] ?? [];

            if ($blocked) {
                foreach ($stageItems as $item) {
                    $item['status'] = 'CANCELLED';
                    $evaluatedItems[] = $item;
                }

                $stageResults[$stage] = ['status' => 'CANCELLED', 'evaluated' => false];
                continue;
            }

            foreach ($stageItems as $item) {
                $evaluatedItems[] = $item;

                if ($item['required'] && $item['blocking'] && !in_array($item['status'], [...self::PASSING_STATUSES, 'WAIVED'], true)) {
                    $blocked = true;
                }
            }

            $stageResults[$stage] = ['status' => $blocked ? 'FAILED' : 'PASSED', 'evaluated' => true];
        }

        return [$evaluatedItems, $stageResults];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<string, int>
     */
    private function summarize(array $items): array
    {
        $summary = ['total' => count($items), 'passed' => 0, 'failed' => 0, 'unavailable' => 0, 'waived' => 0, 'stale' => 0, 'not_required' => 0, 'cancelled' => 0];
        $statusToKey = ['PASSED' => 'passed', 'FAILED' => 'failed', 'UNAVAILABLE' => 'unavailable', 'WAIVED' => 'waived', 'STALE' => 'stale', 'NOT_REQUIRED' => 'not_required', 'CANCELLED' => 'cancelled'];

        foreach ($items as $item) {
            $key = $statusToKey[$item['status']] ?? null;

            if ($key !== null) {
                $summary[$key]++;
            }
        }

        return $summary;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array{remaining_attempts?: int, recovery_required?: bool, clarification_needed?: bool} $options
     */
    private function decide(array $items, array $options): string
    {
        $required = array_values(array_filter($items, static fn(array $i): bool => $i['required']));

        $nonWaivableFailure = array_filter($required, static fn(array $i): bool => $i['status'] === 'FAILED' && !$i['waivable']);

        if ($nonWaivableFailure !== []) {
            return 'REJECTED';
        }

        $repairableFailure = array_filter($required, static fn(array $i): bool => $i['status'] === 'FAILED' && $i['repairable']);

        if ($repairableFailure !== [] && ($options['remaining_attempts'] ?? 1) > 0) {
            return 'REPAIR_REQUIRED';
        }

        if ($repairableFailure !== []) {
            return 'REJECTED';
        }

        $nonWaivableUnavailable = array_filter($required, static fn(array $i): bool => $i['status'] === 'UNAVAILABLE' && !$i['waivable']);

        if ($nonWaivableUnavailable !== []) {
            return 'BLOCKED';
        }

        $limitedItems = array_filter($required, static fn(array $i): bool => in_array($i['status'], ['UNAVAILABLE', 'WAIVED'], true));

        if ($limitedItems !== []) {
            return 'ACCEPTED_WITH_LIMITATIONS';
        }

        if ($options['recovery_required'] ?? false) {
            return 'RECOVERY_REQUIRED';
        }

        if ($options['clarification_needed'] ?? false) {
            return 'CLARIFICATION_REQUIRED';
        }

        $allPassing = array_filter($required, static fn(array $i): bool => !in_array($i['status'], self::PASSING_STATUSES, true));

        if ($allPassing === []) {
            return 'ACCEPTED';
        }

        return 'REJECTED';
    }
}
