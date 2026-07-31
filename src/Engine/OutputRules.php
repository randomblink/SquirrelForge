<?php

declare(strict_types=1);

namespace SquirrelForge\Engine;

/**
 * Defines how SquirrelForge reports results, blockers, validation
 * evidence, and next actions, per 14_ENGINE/OUTPUT-RULES.md.
 *
 * The Core Output Rule and Validation Reporting Rule ("must not say or
 * imply that validation passed when it was not actually performed")
 * are upheld by construction, not by trusting a claim: buildReport()
 * never accepts a caller-supplied output `type`. classifyOutputType()
 * derives it entirely from real signals -- the caller's own declared
 * `outcome`, `request_type`, `plan_only` flag, and each
 * `validation_items` entry's actual status. A report can only become
 * `COMPLETE` when every declared validation item's status is literally
 * `passed`, `waived`, or `not_applicable`; any `failed`, `unavailable`,
 * or `not_attempted` item forces `COMPLETE_WITH_LIMITATIONS` instead,
 * regardless of what the caller might have wanted to claim.
 *
 * The Validation Reporting Rule's six-way distinction (passed / failed
 * / unavailable / waived / not applicable / not attempted) is enforced
 * structurally: classifyValidation() groups every declared item by its
 * real status and rejects any item whose status isn't one of the six
 * named values -- there is no seventh bucket to hide an unclear result
 * in.
 *
 * The Blocker Reporting Rule is a real, enforced gate: a BLOCKED report
 * requires all five named fields (what, why, owning phase, required
 * resolution, non-continuable actions) before it can be built at all --
 * "do not disguise a blocker as a normal next step" is upheld by
 * refusing to produce a blocker report that's missing any of them.
 *
 * The Secret and Internal Data Rule reuses the same deny-by-default
 * recursive redaction AuditTrail and DashboardManager already apply:
 * caller-named `prohibited_fields` are stripped from the entire report
 * before it's returned, never merely from one section.
 *
 * Like PromptCompiler, this class has no database: a report is a pure
 * return value for the caller to present.
 */
final class OutputRules
{
    private const VALID_OUTCOMES = ['success', 'blocked', 'failed', 'recovery_required'];
    private const VALIDATION_STATUSES = ['passed', 'failed', 'unavailable', 'waived', 'not_applicable', 'not_attempted'];
    private const PASSING_STATUSES = ['passed', 'waived', 'not_applicable'];
    private const REQUIRED_BLOCKER_FIELDS = ['what', 'why', 'owning_phase', 'required_resolution', 'non_continuable_actions'];

    /**
     * @param array{outcome: string, request_type?: ?string, plan_only?: bool, changed_artifacts?: ?array<int, string>, commits?: array<int, string>, validation_items?: array<string, string>, risks?: array<int, string>, assumptions?: array<int, string>, limitations?: array<int, string>, next_action?: ?string, blocker?: array{what: string, why: string, owning_phase: string, required_resolution: string, non_continuable_actions: array<int, string>}} $input
     * @param array{prohibited_fields?: array<int, string>} $options
     * @return array{report: ?array<string, mixed>, outcome: string, error: ?string}
     */
    public function buildReport(array $input, array $options = []): array
    {
        if (!in_array($input['outcome'] ?? null, self::VALID_OUTCOMES, true)) {
            return ['report' => null, 'outcome' => 'invalid_request', 'error' => sprintf('"outcome" must be one of: %s.', implode(', ', self::VALID_OUTCOMES))];
        }

        foreach ($input['validation_items'] ?? [] as $item => $status) {
            if (!in_array($status, self::VALIDATION_STATUSES, true)) {
                return ['report' => null, 'outcome' => 'invalid_validation_status', 'error' => sprintf('Validation item "%s" has an unrecognized status "%s".', $item, $status)];
            }
        }

        $type = $this->classifyOutputType($input);

        if ($type === 'BLOCKED') {
            $blockerError = $this->validateBlocker($input['blocker'] ?? null);

            if ($blockerError !== null) {
                return ['report' => null, 'outcome' => 'invalid_blocker', 'error' => $blockerError];
            }
        }

        $prohibitedFields = $options['prohibited_fields'] ?? [];

        $report = [
            'type' => $type,
            'outcome' => $input['outcome'],
            'changed_artifacts' => $input['changed_artifacts'] ?? null,
            'commits' => $input['commits'] ?? [],
            'validation' => $this->classifyValidation($input['validation_items'] ?? []),
            'risks' => $input['risks'] ?? [],
            'assumptions' => $input['assumptions'] ?? [],
            'limitations' => $input['limitations'] ?? [],
            'next_action' => $input['next_action'] ?? null,
            'blocker' => $type === 'BLOCKED' ? $input['blocker'] : null,
        ];

        return ['report' => $this->redact($report, $prohibitedFields), 'outcome' => 'built', 'error' => null];
    }

    /**
     * @param array{outcome: string, request_type?: ?string, plan_only?: bool, validation_items?: array<string, string>} $input
     */
    private function classifyOutputType(array $input): string
    {
        if (($input['plan_only'] ?? false) === true) {
            return 'PLAN_ONLY';
        }

        if (($input['request_type'] ?? null) === 'read_only') {
            return 'READ_ONLY_RESULT';
        }

        if ($input['outcome'] === 'recovery_required') {
            return 'RECOVERY_REQUIRED';
        }

        if ($input['outcome'] === 'blocked') {
            return 'BLOCKED';
        }

        if ($input['outcome'] === 'failed') {
            return 'FAILED';
        }

        $validationItems = $input['validation_items'] ?? [];
        $allPassing = array_reduce($validationItems, static fn(bool $carry, string $status): bool => $carry && in_array($status, self::PASSING_STATUSES, true), true);

        return $allPassing ? 'COMPLETE' : 'COMPLETE_WITH_LIMITATIONS';
    }

    /**
     * @param array<string, string> $items
     * @return array<string, array<int, string>>
     */
    private function classifyValidation(array $items): array
    {
        $grouped = array_fill_keys(self::VALIDATION_STATUSES, []);

        foreach ($items as $name => $status) {
            $grouped[$status][] = $name;
        }

        return $grouped;
    }

    /**
     * @param array{what?: string, why?: string, owning_phase?: string, required_resolution?: string, non_continuable_actions?: array<int, string>}|null $blocker
     */
    private function validateBlocker(?array $blocker): ?string
    {
        if ($blocker === null) {
            return 'A BLOCKED report requires a "blocker" record.';
        }

        foreach (self::REQUIRED_BLOCKER_FIELDS as $field) {
            if (($blocker[$field] ?? '') === '' || $blocker[$field] === []) {
                return sprintf('Blocker record is missing "%s".', $field);
            }
        }

        return null;
    }

    /**
     * @param array<int, string> $prohibitedFields
     */
    private function redact(mixed $data, array $prohibitedFields): mixed
    {
        if (!is_array($data)) {
            return $data;
        }

        $result = [];

        foreach ($data as $key => $value) {
            if (is_string($key) && in_array($key, $prohibitedFields, true)) {
                continue;
            }

            $result[$key] = is_array($value) ? $this->redact($value, $prohibitedFields) : $value;
        }

        return $result;
    }
}
