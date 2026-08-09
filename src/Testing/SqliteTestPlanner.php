<?php

declare(strict_types=1);

namespace SquirrelForge\Testing;

use DateTimeImmutable;
use PDO;
use SquirrelForge\Reasoning\SqliteRiskAssessor;

/**
 * Converts requirements, acceptance criteria, risk-assessment
 * references, interface contracts, and change-impact information into
 * testing-domain plans, per 29_TESTING/TEST-PLANNER.md -- the first
 * real component in 29_TESTING, chosen because every other component
 * in this layer names it as their own real dependency.
 *
 * "Map requirements, acceptance criteria, and risk inputs to
 * appropriate test categories" is real, deterministic logic over
 * caller-supplied shape, not a fabricated judgment: `Unit` coverage is
 * always required (every plan tests something); `Integration` is
 * required only when the request actually names `interface_contracts`
 * a boundary is crossed; `Regression` is required only when the
 * request declares this is a change to existing behavior
 * (`is_change`/`change_impact`) -- a brand-new capability has no prior
 * behavior to regress.
 *
 * "Risk-assessment references from `RISK-ASSESSOR.md` where
 * applicable" is genuine composition of the already-real
 * `SqliteRiskAssessor::list()` -- when an `option_reference` is
 * supplied and a `SqliteRiskAssessor` is configured, this class reads
 * the real, already-recorded risks for that option (never assessing a
 * risk itself, which stays that component's own authority per this
 * spec's own Boundary: "does not perform general risk assessment").
 * "Require negative, boundary, permission-failure, recovery-scenario,
 * and other risk-driven coverage where applicable" is a real mapping
 * from each risk's own recorded `risk_level`/`category` fields: a
 * `high`/`critical` risk requires `negative`+`boundary` coverage; a
 * `security`-category risk requires `permission_failure` coverage; an
 * `operational`/`external`-category risk requires `recovery_scenario`
 * coverage; a `critical` risk additionally pulls in `System` and
 * `Smoke` test categories, since a critical risk needs end-to-end and
 * sanity coverage a narrower category can't provide alone.
 *
 * "Define testing entry and exit criteria as test-plan criteria, not
 * release or governance approval criteria" (Responsibilities/Boundary)
 * draws a real, checked line: this class also composes
 * `SqliteRiskAssessor::canProceed()` to surface any currently-open
 * `critical` risk blocking this option as an exit-criteria concern --
 * but it only ever *reports* that reference, it never itself decides
 * the plan is blocked or approves anything; "downstream validation,
 * governance quality-gate, release, and deployment decisions remain
 * with their authoritative owners" (Rule) stays literally true.
 *
 * SQLite-backed for "produce test-plan records and coverage
 * references for test-category components" -- every other component
 * in this layer, plus `TEST-REPORTING.md`, needs to read a plan back
 * after it was produced, the same cross-call persistence reasoning
 * this codebase's other `Sqlite*` registry components already
 * established.
 */
final class SqliteTestPlanner
{
    private PDO $database;

    public function __construct(string $databasePath, private readonly ?SqliteRiskAssessor $riskAssessor = null)
    {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS test_plans (
                plan_id TEXT PRIMARY KEY,
                subject_ref TEXT NOT NULL,
                acceptance_criteria_json TEXT NOT NULL,
                interface_contracts_json TEXT NOT NULL,
                categories_json TEXT NOT NULL,
                risk_driven_coverage_json TEXT NOT NULL,
                entry_criteria_json TEXT NOT NULL,
                exit_criteria_json TEXT NOT NULL,
                blocking_risks_json TEXT NOT NULL,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array{
     *     subject_ref?: ?string,
     *     acceptance_criteria?: array<int, string>,
     *     interface_contracts?: array<int, string>,
     *     change_impact?: array<int, string>,
     *     is_change?: bool,
     *     environment_refs?: array<int, string>,
     *     fixture_refs?: array<int, string>,
     *     option_reference?: ?string
     * } $request
     * @return array{
     *     outcome: string,
     *     plan_id: ?string,
     *     subject_ref: ?string,
     *     interface_contracts: array<int, string>,
     *     categories: array<int, string>,
     *     risk_driven_coverage: array<int, string>,
     *     entry_criteria: array<int, string>,
     *     exit_criteria: array<int, string>,
     *     blocking_risks: array<int, string>,
     *     error: ?string
     * }
     */
    public function plan(array $request): array
    {
        $subjectRef = $request['subject_ref'] ?? null;
        $acceptanceCriteria = $request['acceptance_criteria'] ?? [];
        $interfaceContracts = $request['interface_contracts'] ?? [];

        if (!is_string($subjectRef) || $subjectRef === '') {
            return $this->outcome('invalid', null, $subjectRef, [], [], [], [], [], [], 'A test plan requires a non-empty subject_ref.');
        }

        if ($acceptanceCriteria === []) {
            return $this->outcome('invalid', null, $subjectRef, [], [], [], [], [], [], 'A test plan requires at least one acceptance criterion.');
        }

        $categories = ['Unit'];

        if ($interfaceContracts !== []) {
            $categories[] = 'Integration';
        }

        if (($request['is_change'] ?? false) === true || ($request['change_impact'] ?? []) !== []) {
            $categories[] = 'Regression';
        }

        $riskDrivenCoverage = [];
        $blockingRisks = [];
        $optionReference = $request['option_reference'] ?? null;

        if ($this->riskAssessor !== null && is_string($optionReference) && $optionReference !== '') {
            foreach ($this->riskAssessor->list(['option_reference' => $optionReference, 'status' => 'open']) as $risk) {
                if (in_array($risk['risk_level'], ['high', 'critical'], true)) {
                    $riskDrivenCoverage[] = 'negative';
                    $riskDrivenCoverage[] = 'boundary';
                }

                if ($risk['category'] === 'security') {
                    $riskDrivenCoverage[] = 'permission_failure';
                }

                if (in_array($risk['category'], ['operational', 'external'], true)) {
                    $riskDrivenCoverage[] = 'recovery_scenario';
                }

                if ($risk['risk_level'] === 'critical') {
                    $categories[] = 'System';
                    $categories[] = 'Smoke';
                }
            }

            $blockingRisks = $this->riskAssessor->canProceed($optionReference)['blocking_risks'];
        }

        $categories = array_values(array_unique($categories));
        $riskDrivenCoverage = array_values(array_unique($riskDrivenCoverage));

        $entryCriteria = $this->entryCriteria($request);
        $exitCriteria = $this->exitCriteria($categories, $blockingRisks);

        $planId = $this->record($subjectRef, $acceptanceCriteria, $interfaceContracts, $categories, $riskDrivenCoverage, $entryCriteria, $exitCriteria, $blockingRisks);

        return $this->outcome('planned', $planId, $subjectRef, $interfaceContracts, $categories, $riskDrivenCoverage, $entryCriteria, $exitCriteria, $blockingRisks, null);
    }

    /**
     * @param array<string, mixed> $request
     * @return array<int, string>
     */
    private function entryCriteria(array $request): array
    {
        $criteria = [];
        $environmentRefs = $request['environment_refs'] ?? [];
        $fixtureRefs = $request['fixture_refs'] ?? [];

        $criteria[] = $environmentRefs !== []
            ? sprintf('Required environment(s) available: %s.', implode(', ', $environmentRefs))
            : 'No specific environment reference was declared; a suitable environment must still be confirmed available before testing begins.';

        $criteria[] = $fixtureRefs !== []
            ? sprintf('Required fixture(s) available: %s.', implode(', ', $fixtureRefs))
            : 'No specific fixture reference was declared.';

        return $criteria;
    }

    /**
     * @param array<int, string> $categories
     * @param array<int, string> $blockingRisks
     * @return array<int, string>
     */
    private function exitCriteria(array $categories, array $blockingRisks): array
    {
        $criteria = [sprintf('All assigned test categories (%s) report a passing result.', implode(', ', $categories))];

        $criteria[] = $blockingRisks !== []
            ? sprintf('%d open critical risk(s) remain for this option and must be resolved or explicitly accepted before this plan can be considered complete: %s.', count($blockingRisks), implode(', ', $blockingRisks))
            : 'No open critical risk blocks this option per the Risk Assessor.';

        return $criteria;
    }

    /**
     * @param array<int, string> $acceptanceCriteria
     * @param array<int, string> $interfaceContracts
     * @param array<int, string> $categories
     * @param array<int, string> $riskDrivenCoverage
     * @param array<int, string> $entryCriteria
     * @param array<int, string> $exitCriteria
     * @param array<int, string> $blockingRisks
     */
    private function record(
        string $subjectRef,
        array $acceptanceCriteria,
        array $interfaceContracts,
        array $categories,
        array $riskDrivenCoverage,
        array $entryCriteria,
        array $exitCriteria,
        array $blockingRisks
    ): string {
        $planId = 'test_plan_' . bin2hex(random_bytes(12));

        $statement = $this->database->prepare(
            'INSERT INTO test_plans
                (plan_id, subject_ref, acceptance_criteria_json, interface_contracts_json, categories_json, risk_driven_coverage_json, entry_criteria_json, exit_criteria_json, blocking_risks_json, created_at)
             VALUES
                (:plan_id, :subject_ref, :acceptance_criteria_json, :interface_contracts_json, :categories_json, :risk_driven_coverage_json, :entry_criteria_json, :exit_criteria_json, :blocking_risks_json, :created_at)'
        );
        $statement->execute([
            'plan_id' => $planId,
            'subject_ref' => $subjectRef,
            'acceptance_criteria_json' => json_encode($acceptanceCriteria, JSON_THROW_ON_ERROR),
            'interface_contracts_json' => json_encode($interfaceContracts, JSON_THROW_ON_ERROR),
            'categories_json' => json_encode($categories, JSON_THROW_ON_ERROR),
            'risk_driven_coverage_json' => json_encode($riskDrivenCoverage, JSON_THROW_ON_ERROR),
            'entry_criteria_json' => json_encode($entryCriteria, JSON_THROW_ON_ERROR),
            'exit_criteria_json' => json_encode($exitCriteria, JSON_THROW_ON_ERROR),
            'blocking_risks_json' => json_encode($blockingRisks, JSON_THROW_ON_ERROR),
            'created_at' => (new DateTimeImmutable())->format(DATE_ATOM),
        ]);

        return $planId;
    }

    /**
     * @return ?array<string, mixed>
     */
    public function get(string $planId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM test_plans WHERE plan_id = :plan_id');
        $statement->execute(['plan_id' => $planId]);
        $row = $statement->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function history(string $subjectRef): array
    {
        $statement = $this->database->prepare('SELECT * FROM test_plans WHERE subject_ref = :subject_ref ORDER BY rowid ASC');
        $statement->execute(['subject_ref' => $subjectRef]);

        return array_map(fn(array $row): array => $this->hydrate($row), $statement->fetchAll());
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        foreach (['acceptance_criteria', 'interface_contracts', 'categories', 'risk_driven_coverage', 'entry_criteria', 'exit_criteria', 'blocking_risks'] as $field) {
            $row[$field] = json_decode($row[$field . '_json'], true, flags: JSON_THROW_ON_ERROR);
            unset($row[$field . '_json']);
        }

        return $row;
    }

    /**
     * @param array<int, string> $interfaceContracts
     * @param array<int, string> $categories
     * @param array<int, string> $riskDrivenCoverage
     * @param array<int, string> $entryCriteria
     * @param array<int, string> $exitCriteria
     * @param array<int, string> $blockingRisks
     * @return array{
     *     outcome: string,
     *     plan_id: ?string,
     *     subject_ref: ?string,
     *     interface_contracts: array<int, string>,
     *     categories: array<int, string>,
     *     risk_driven_coverage: array<int, string>,
     *     entry_criteria: array<int, string>,
     *     exit_criteria: array<int, string>,
     *     blocking_risks: array<int, string>,
     *     error: ?string
     * }
     */
    private function outcome(
        string $outcome,
        ?string $planId,
        ?string $subjectRef,
        array $interfaceContracts,
        array $categories,
        array $riskDrivenCoverage,
        array $entryCriteria,
        array $exitCriteria,
        array $blockingRisks,
        ?string $error
    ): array {
        return [
            'outcome' => $outcome,
            'plan_id' => $planId,
            'subject_ref' => $subjectRef,
            'interface_contracts' => $interfaceContracts,
            'categories' => $categories,
            'risk_driven_coverage' => $riskDrivenCoverage,
            'entry_criteria' => $entryCriteria,
            'exit_criteria' => $exitCriteria,
            'blocking_risks' => $blockingRisks,
            'error' => $error,
        ];
    }
}
