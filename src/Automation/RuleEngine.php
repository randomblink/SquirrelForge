<?php

declare(strict_types=1);

namespace SquirrelForge\Automation;

use Closure;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Evaluates declarative Automation-domain conditions against supplied
 * context, per 33_AUTOMATION/RULE-ENGINE.md.
 *
 * This is the cleanest root in 33_AUTOMATION: unlike every other
 * component in this category, its own "Depends On" is just "declarative
 * automation rule definitions" and "authoritative context and decision
 * references" -- both of which are data the caller supplies, not other
 * unbuilt components. It genuinely never computes permission, risk,
 * policy, security, or health decisions itself ("does not perform
 * governance-policy evaluation, runtime authorization, general risk
 * assessment... It does not reinterpret authoritative decisions supplied
 * by other layers") -- rules only ever read whatever value the caller
 * already put in `context` under a field name; there is no special
 * handling for "security" or "risk" fields versus any other field.
 *
 * Five real condition types are implemented: boolean, threshold,
 * schedule_window (real wall-clock comparison, with overnight windows
 * handled and a caller-injectable clock for tests), dependency (checks
 * another rule's result from the same evaluateAll() batch, via
 * `context['rule_results']`), and composite (and/or/not over nested
 * conditions). Composite evaluation uses real three-valued logic: an AND
 * with one missing input and no failures is `conditional`, not a guess
 * at pass or fail, since the spec's own five result states include
 * exactly this "can't be determined yet" case.
 *
 * "Detect conflicts among rule definitions" is a real, structural check,
 * not a fabricated constraint solver: duplicate rule IDs in a batch, and
 * two different rules asserting a directly contradictory boolean value
 * for the same context field. Inferring conflicts between, say, two
 * threshold rules on the same field would require knowing whether they're
 * meant to apply at the same time, which this engine has no way to know.
 */
final class RuleEngine
{
    private const OPERATORS = ['>', '>=', '<', '<=', '==', '!='];

    public function __construct(private readonly ?Closure $clock = null)
    {
    }

    /**
     * @param array{id: string, condition: array<string, mixed>} $rule
     * @param array<string, mixed> $context
     * @return array{rule_id: string, result: string, evidence: array<string, mixed>, error: ?string}
     */
    public function evaluate(array $rule, array $context): array
    {
        [$result, $evidence, $error] = $this->evaluateCondition($rule['condition'], $context);

        return ['rule_id' => $rule['id'], 'result' => $result, 'evidence' => $evidence, 'error' => $error];
    }

    /**
     * Evaluates a batch of rules in order, making each rule's result
     * available to later rules' `dependency` conditions via
     * `context['rule_results']`, and reports structural conflicts found
     * across the batch.
     *
     * @param array<int, array{id: string, condition: array<string, mixed>}> $rules
     * @param array<string, mixed> $context
     * @return array{results: array<string, array<string, mixed>>, conflicts: array<int, array<string, mixed>>}
     */
    public function evaluateAll(array $rules, array $context): array
    {
        $results = [];
        $ruleResults = [];

        foreach ($rules as $rule) {
            $evaluation = $this->evaluate($rule, [...$context, 'rule_results' => $ruleResults]);
            $results[$rule['id']] = $evaluation;
            $ruleResults[$rule['id']] = $evaluation['result'];
        }

        return ['results' => $results, 'conflicts' => $this->detectConflicts($rules)];
    }

    /**
     * @param array<string, mixed> $condition
     * @param array<string, mixed> $context
     * @return array{0: string, 1: array<string, mixed>, 2: ?string}
     */
    private function evaluateCondition(array $condition, array $context): array
    {
        return match ($condition['type'] ?? null) {
            'boolean' => $this->evaluateBoolean($condition, $context),
            'threshold' => $this->evaluateThreshold($condition, $context),
            'schedule_window' => $this->evaluateScheduleWindow($condition, $context),
            'dependency' => $this->evaluateDependency($condition, $context),
            'composite' => $this->evaluateComposite($condition, $context),
            default => ['fail', [], sprintf('Unknown or missing condition type "%s".', $condition['type'] ?? '')],
        };
    }

    /**
     * @param array<string, mixed> $condition
     * @param array<string, mixed> $context
     * @return array{0: string, 1: array<string, mixed>, 2: ?string}
     */
    private function evaluateBoolean(array $condition, array $context): array
    {
        $field = $condition['field'] ?? null;

        if (!is_string($field)) {
            return ['fail', [], 'Boolean condition requires a "field" string.'];
        }

        if (!array_key_exists($field, $context)) {
            return ['input_missing', ['field' => $field], null];
        }

        $expected = $condition['equals'] ?? true;
        $actual = (bool) $context[$field];
        $evidence = ['field' => $field, 'expected' => $expected, 'actual' => $actual];

        return [$actual === $expected ? 'pass' : 'fail', $evidence, null];
    }

    /**
     * @param array<string, mixed> $condition
     * @param array<string, mixed> $context
     * @return array{0: string, 1: array<string, mixed>, 2: ?string}
     */
    private function evaluateThreshold(array $condition, array $context): array
    {
        $field = $condition['field'] ?? null;
        $operator = $condition['operator'] ?? null;
        $value = $condition['value'] ?? null;

        if (!is_string($field) || !in_array($operator, self::OPERATORS, true) || !is_numeric($value)) {
            return ['fail', [], 'Threshold condition requires "field", a valid "operator", and a numeric "value".'];
        }

        if (!array_key_exists($field, $context)) {
            return ['input_missing', ['field' => $field], null];
        }

        if (!is_numeric($context[$field])) {
            return ['fail', ['field' => $field, 'actual' => $context[$field]], sprintf('Field "%s" is not numeric.', $field)];
        }

        $actual = (float) $context[$field];
        $threshold = (float) $value;
        $satisfied = match ($operator) {
            '>' => $actual > $threshold,
            '>=' => $actual >= $threshold,
            '<' => $actual < $threshold,
            '<=' => $actual <= $threshold,
            '==' => $actual == $threshold,
            '!=' => $actual != $threshold,
        };

        return [$satisfied ? 'pass' : 'fail', ['field' => $field, 'operator' => $operator, 'value' => $threshold, 'actual' => $actual], null];
    }

    /**
     * @param array<string, mixed> $condition
     * @param array<string, mixed> $context
     * @return array{0: string, 1: array<string, mixed>, 2: ?string}
     */
    private function evaluateScheduleWindow(array $condition, array $context): array
    {
        $start = $condition['start'] ?? null;
        $end = $condition['end'] ?? null;

        if (!is_string($start) || !is_string($end)) {
            return ['fail', [], 'Schedule window condition requires "start" and "end" as "HH:MM" strings.'];
        }

        $now = $this->resolveNow($context);
        $nowMinutes = ((int) $now->format('H')) * 60 + (int) $now->format('i');
        $startMinutes = $this->minutesFromHhMm($start);
        $endMinutes = $this->minutesFromHhMm($end);

        if ($startMinutes === null || $endMinutes === null) {
            return ['fail', [], 'Schedule window "start"/"end" must be in "HH:MM" format.'];
        }

        $withinWindow = $startMinutes <= $endMinutes
            ? ($nowMinutes >= $startMinutes && $nowMinutes < $endMinutes)
            : ($nowMinutes >= $startMinutes || $nowMinutes < $endMinutes);

        return [$withinWindow ? 'pass' : 'fail', ['start' => $start, 'end' => $end, 'now' => $now->format('H:i')], null];
    }

    /**
     * @param array<string, mixed> $context
     */
    private function resolveNow(array $context): DateTimeImmutable
    {
        if (isset($context['now']) && is_string($context['now'])) {
            return new DateTimeImmutable($context['now'], new DateTimeZone('UTC'));
        }

        if ($this->clock !== null) {
            return ($this->clock)();
        }

        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    private function minutesFromHhMm(string $value): ?int
    {
        if (preg_match('/^(\d{2}):(\d{2})$/', $value, $matches) !== 1) {
            return null;
        }

        return ((int) $matches[1]) * 60 + (int) $matches[2];
    }

    /**
     * @param array<string, mixed> $condition
     * @param array<string, mixed> $context
     * @return array{0: string, 1: array<string, mixed>, 2: ?string}
     */
    private function evaluateDependency(array $condition, array $context): array
    {
        $dependsOn = $condition['depends_on'] ?? null;

        if (!is_string($dependsOn)) {
            return ['fail', [], 'Dependency condition requires a "depends_on" rule ID.'];
        }

        $expect = $condition['expect'] ?? 'pass';
        $ruleResults = $context['rule_results'] ?? [];
        $upstream = $ruleResults[$dependsOn] ?? null;
        $evidence = ['depends_on' => $dependsOn, 'expect' => $expect, 'upstream_result' => $upstream];

        if ($upstream === null || in_array($upstream, ['deferred', 'conditional', 'input_missing'], true)) {
            return ['deferred', $evidence, null];
        }

        return [$upstream === $expect ? 'pass' : 'fail', $evidence, null];
    }

    /**
     * @param array<string, mixed> $condition
     * @param array<string, mixed> $context
     * @return array{0: string, 1: array<string, mixed>, 2: ?string}
     */
    private function evaluateComposite(array $condition, array $context): array
    {
        $operator = $condition['operator'] ?? null;
        $subConditions = $condition['conditions'] ?? null;

        if (!is_array($subConditions) || $subConditions === []) {
            return ['fail', [], 'Composite condition requires a non-empty "conditions" array.'];
        }

        $subResults = array_map(fn(array $sub): array => $this->evaluateCondition($sub, $context), $subConditions);
        $results = array_column($subResults, 0);

        return match ($operator) {
            'and' => [$this->combineAnd($results), ['sub_results' => $results], null],
            'or' => [$this->combineOr($results), ['sub_results' => $results], null],
            'not' => count($subConditions) === 1
                ? [$this->invert($results[0]), ['sub_result' => $results[0]], null]
                : ['fail', [], '"not" composite condition requires exactly one nested condition.'],
            default => ['fail', [], sprintf('Unknown composite operator "%s".', $operator ?? '')],
        };
    }

    /**
     * @param array<int, string> $results
     */
    private function combineAnd(array $results): string
    {
        if (in_array('fail', $results, true)) {
            return 'fail';
        }

        foreach ($results as $result) {
            if ($result !== 'pass') {
                return 'conditional';
            }
        }

        return 'pass';
    }

    /**
     * @param array<int, string> $results
     */
    private function combineOr(array $results): string
    {
        if (in_array('pass', $results, true)) {
            return 'pass';
        }

        foreach ($results as $result) {
            if ($result !== 'fail') {
                return 'conditional';
            }
        }

        return 'fail';
    }

    private function invert(string $result): string
    {
        return match ($result) {
            'pass' => 'fail',
            'fail' => 'pass',
            default => $result,
        };
    }

    /**
     * Real, structural conflict detection: duplicate rule IDs, and two
     * different rules asserting contradictory boolean values for the
     * same field. Not a general constraint solver.
     *
     * @param array<int, array{id: string, condition: array<string, mixed>}> $rules
     * @return array<int, array<string, mixed>>
     */
    private function detectConflicts(array $rules): array
    {
        $conflicts = [];
        $seenIds = [];
        $booleanClaims = [];

        foreach ($rules as $rule) {
            $id = $rule['id'];

            if (in_array($id, $seenIds, true)) {
                $conflicts[] = ['type' => 'duplicate_id', 'rule_id' => $id];
            }

            $seenIds[] = $id;

            $condition = $rule['condition'];

            if (($condition['type'] ?? null) === 'boolean' && is_string($condition['field'] ?? null)) {
                $field = $condition['field'];
                $expected = $condition['equals'] ?? true;

                foreach ($booleanClaims[$field] ?? [] as $existing) {
                    if ($existing['expected'] !== $expected) {
                        $conflicts[] = [
                            'type' => 'contradictory_boolean',
                            'field' => $field,
                            'rule_ids' => [$existing['rule_id'], $id],
                        ];
                    }
                }

                $booleanClaims[$field][] = ['rule_id' => $id, 'expected' => $expected];
            }
        }

        return $conflicts;
    }
}
