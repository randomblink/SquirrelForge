<?php

declare(strict_types=1);

namespace SquirrelForge\Testing;

use Closure;
use DateTimeImmutable;
use PDO;
use Throwable;

/**
 * Protects previously working, changed, and defect-prone behavior by
 * rerunning targeted and baseline coverage after relevant changes, per
 * 29_TESTING/REGRESSION-TESTS.md -- the fifth real component in
 * 29_TESTING, and structurally the same test-category shape
 * `SqliteUnitTests`/`SqliteSmokeTests`/`SqliteIntegrationTests` already
 * established.
 *
 * "Compare current test results with applicable prior baselines" is
 * genuine, self-referential composition -- unlike this layer's other
 * category components, this class's own real baseline is its own
 * `history()`: the most recently recorded prior run for the same
 * `subject_ref` is read before the current run is persisted, and the
 * comparison against it is real set arithmetic over each run's
 * `failures` list, not a fabricated diff. A failure name present now
 * but absent from the baseline is a real regression
 * (`new_failures`); one present in both is a known, pre-existing
 * failure (`persistent_failures`); one present in the baseline but
 * absent now is reported as `newly_fixed` -- honestly scoped to mean
 * exactly what it can verify ("no longer in the failure list"), not a
 * broader claim this class has no way to confirm.
 *
 * "Add a reproducing regression test for a fixed defect when
 * technically feasible" (Responsibilities) is not something this class
 * does itself -- writing new test code is a maintainer/process action
 * this framework has no capability to perform, and fabricating one
 * here would misrepresent what actually happened. `defect_history` and
 * `change_impact` (Depends On: "defect-history references,
 * change-impact references") are caller-supplied evidence this class
 * carries forward and records alongside the result, since neither has
 * a queryable registry in this codebase to compose against.
 *
 * "A regression pass does not independently establish complete
 * platform validity or release readiness" (Rule) is upheld the same
 * structural way every other category component in this layer upholds
 * its own Rule: only a testing-scope status and evidence are ever
 * returned.
 */
final class SqliteRegressionTests
{
    private PDO $database;

    public function __construct(string $databasePath, private readonly ?SqliteTestPlanner $testPlanner = null)
    {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS regression_test_results (
                result_id TEXT PRIMARY KEY,
                plan_id TEXT,
                subject_ref TEXT NOT NULL,
                status TEXT NOT NULL,
                passed INTEGER NOT NULL,
                failed INTEGER NOT NULL,
                skipped INTEGER NOT NULL,
                failures_json TEXT NOT NULL,
                defect_history_json TEXT NOT NULL,
                change_impact_json TEXT NOT NULL,
                baseline_result_id TEXT,
                new_failures_json TEXT NOT NULL,
                persistent_failures_json TEXT NOT NULL,
                newly_fixed_json TEXT NOT NULL,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array{plan_id?: ?string, subject_ref?: ?string, defect_history?: array<int, string>, change_impact?: array<int, string>} $request
     * @param ?Closure $runSuite (?array $plan): array{passed?: int, failed?: int, skipped?: int, failures?: array<int, array{name: string, message: string}>} the real regression-suite execution. Omitting it leaves the result at `Pending`.
     * @return array{
     *     outcome: string,
     *     result_id: ?string,
     *     status: string,
     *     subject_ref: ?string,
     *     passed: int,
     *     failed: int,
     *     skipped: int,
     *     failures: array<int, array{name: string, message: string}>,
     *     baseline_result_id: ?string,
     *     new_failures: array<int, string>,
     *     persistent_failures: array<int, string>,
     *     newly_fixed: array<int, string>,
     *     error: ?string
     * }
     */
    public function run(array $request, ?Closure $runSuite = null): array
    {
        $subjectRef = $request['subject_ref'] ?? null;

        if (!is_string($subjectRef) || $subjectRef === '') {
            return $this->outcome('invalid', null, 'Rejected', $subjectRef, 0, 0, 0, [], null, [], [], [], 'A regression-test run requires a non-empty subject_ref.');
        }

        $planId = $request['plan_id'] ?? null;
        $plan = null;

        if (is_string($planId) && $planId !== '' && $this->testPlanner !== null) {
            $plan = $this->testPlanner->get($planId);

            if ($plan === null) {
                return $this->outcome('invalid', null, 'Rejected', $subjectRef, 0, 0, 0, [], null, [], [], [], sprintf('Test plan "%s" does not exist.', $planId));
            }

            if (!in_array('Regression', $plan['categories'], true)) {
                return $this->outcome('rejected', null, 'Rejected', $subjectRef, 0, 0, 0, [], null, [], [], [], sprintf('Test plan "%s" does not require Regression coverage.', $planId));
            }
        }

        $defectHistory = $request['defect_history'] ?? [];
        $changeImpact = $request['change_impact'] ?? [];
        $baseline = $this->latestBaseline($subjectRef);

        if ($runSuite === null) {
            $resultId = $this->record($planId, $subjectRef, 'Pending', 0, 0, 0, [], $defectHistory, $changeImpact, $baseline['result_id'] ?? null, [], [], []);

            return $this->outcome('recorded', $resultId, 'Pending', $subjectRef, 0, 0, 0, [], $baseline['result_id'] ?? null, [], [], [], null);
        }

        try {
            $suiteResult = $runSuite($plan);
        } catch (Throwable $e) {
            $failures = [['name' => 'suite_execution', 'message' => $e->getMessage()]];
            [$newFailures, $persistentFailures, $newlyFixed] = $this->compareToBaseline($failures, $baseline);
            $resultId = $this->record($planId, $subjectRef, 'Failed', 0, 1, 0, $failures, $defectHistory, $changeImpact, $baseline['result_id'] ?? null, $newFailures, $persistentFailures, $newlyFixed);

            return $this->outcome('recorded', $resultId, 'Failed', $subjectRef, 0, 1, 0, $failures, $baseline['result_id'] ?? null, $newFailures, $persistentFailures, $newlyFixed, null);
        }

        $passed = (int) ($suiteResult['passed'] ?? 0);
        $failed = (int) ($suiteResult['failed'] ?? 0);
        $skipped = (int) ($suiteResult['skipped'] ?? 0);
        $failures = $suiteResult['failures'] ?? [];

        $status = match (true) {
            $failed > 0 => 'Failed',
            $passed + $skipped === 0 => 'Failed',
            default => 'Passed',
        };

        [$newFailures, $persistentFailures, $newlyFixed] = $this->compareToBaseline($failures, $baseline);

        $resultId = $this->record($planId, $subjectRef, $status, $passed, $failed, $skipped, $failures, $defectHistory, $changeImpact, $baseline['result_id'] ?? null, $newFailures, $persistentFailures, $newlyFixed);

        return $this->outcome('recorded', $resultId, $status, $subjectRef, $passed, $failed, $skipped, $failures, $baseline['result_id'] ?? null, $newFailures, $persistentFailures, $newlyFixed, null);
    }

    /**
     * @return ?array<string, mixed>
     */
    private function latestBaseline(string $subjectRef): ?array
    {
        $priorRuns = $this->history($subjectRef);

        return $priorRuns === [] ? null : $priorRuns[count($priorRuns) - 1];
    }

    /**
     * @param array<int, array{name: string, message: string}> $currentFailures
     * @param ?array<string, mixed> $baseline
     * @return array{0: array<int, string>, 1: array<int, string>, 2: array<int, string>}
     */
    private function compareToBaseline(array $currentFailures, ?array $baseline): array
    {
        if ($baseline === null) {
            return [[], [], []];
        }

        $currentNames = array_column($currentFailures, 'name');
        $baselineNames = array_column($baseline['failures'], 'name');

        return [
            array_values(array_diff($currentNames, $baselineNames)),
            array_values(array_intersect($currentNames, $baselineNames)),
            array_values(array_diff($baselineNames, $currentNames)),
        ];
    }

    /**
     * @param array<int, array{name: string, message: string}> $failures
     * @param array<int, string> $defectHistory
     * @param array<int, string> $changeImpact
     * @param array<int, string> $newFailures
     * @param array<int, string> $persistentFailures
     * @param array<int, string> $newlyFixed
     */
    private function record(
        ?string $planId,
        string $subjectRef,
        string $status,
        int $passed,
        int $failed,
        int $skipped,
        array $failures,
        array $defectHistory,
        array $changeImpact,
        ?string $baselineResultId,
        array $newFailures,
        array $persistentFailures,
        array $newlyFixed
    ): string {
        $resultId = 'regression_test_result_' . bin2hex(random_bytes(12));

        $statement = $this->database->prepare(
            'INSERT INTO regression_test_results
                (result_id, plan_id, subject_ref, status, passed, failed, skipped, failures_json, defect_history_json, change_impact_json, baseline_result_id, new_failures_json, persistent_failures_json, newly_fixed_json, created_at)
             VALUES
                (:result_id, :plan_id, :subject_ref, :status, :passed, :failed, :skipped, :failures_json, :defect_history_json, :change_impact_json, :baseline_result_id, :new_failures_json, :persistent_failures_json, :newly_fixed_json, :created_at)'
        );
        $statement->execute([
            'result_id' => $resultId,
            'plan_id' => $planId,
            'subject_ref' => $subjectRef,
            'status' => $status,
            'passed' => $passed,
            'failed' => $failed,
            'skipped' => $skipped,
            'failures_json' => json_encode($failures, JSON_THROW_ON_ERROR),
            'defect_history_json' => json_encode($defectHistory, JSON_THROW_ON_ERROR),
            'change_impact_json' => json_encode($changeImpact, JSON_THROW_ON_ERROR),
            'baseline_result_id' => $baselineResultId,
            'new_failures_json' => json_encode($newFailures, JSON_THROW_ON_ERROR),
            'persistent_failures_json' => json_encode($persistentFailures, JSON_THROW_ON_ERROR),
            'newly_fixed_json' => json_encode($newlyFixed, JSON_THROW_ON_ERROR),
            'created_at' => (new DateTimeImmutable())->format(DATE_ATOM),
        ]);

        return $resultId;
    }

    /**
     * @return ?array<string, mixed>
     */
    public function get(string $resultId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM regression_test_results WHERE result_id = :result_id');
        $statement->execute(['result_id' => $resultId]);
        $row = $statement->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function history(string $subjectRef): array
    {
        $statement = $this->database->prepare('SELECT * FROM regression_test_results WHERE subject_ref = :subject_ref ORDER BY rowid ASC');
        $statement->execute(['subject_ref' => $subjectRef]);

        return array_map(fn(array $row): array => $this->hydrate($row), $statement->fetchAll());
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        foreach (['failures', 'defect_history', 'change_impact', 'new_failures', 'persistent_failures', 'newly_fixed'] as $field) {
            $row[$field] = json_decode($row[$field . '_json'], true, flags: JSON_THROW_ON_ERROR);
            unset($row[$field . '_json']);
        }

        return $row;
    }

    /**
     * @param array<int, array{name: string, message: string}> $failures
     * @param array<int, string> $newFailures
     * @param array<int, string> $persistentFailures
     * @param array<int, string> $newlyFixed
     * @return array{
     *     outcome: string,
     *     result_id: ?string,
     *     status: string,
     *     subject_ref: ?string,
     *     passed: int,
     *     failed: int,
     *     skipped: int,
     *     failures: array<int, array{name: string, message: string}>,
     *     baseline_result_id: ?string,
     *     new_failures: array<int, string>,
     *     persistent_failures: array<int, string>,
     *     newly_fixed: array<int, string>,
     *     error: ?string
     * }
     */
    private function outcome(
        string $outcome,
        ?string $resultId,
        string $status,
        ?string $subjectRef,
        int $passed,
        int $failed,
        int $skipped,
        array $failures,
        ?string $baselineResultId,
        array $newFailures,
        array $persistentFailures,
        array $newlyFixed,
        ?string $error
    ): array {
        return [
            'outcome' => $outcome,
            'result_id' => $resultId,
            'status' => $status,
            'subject_ref' => $subjectRef,
            'passed' => $passed,
            'failed' => $failed,
            'skipped' => $skipped,
            'failures' => $failures,
            'baseline_result_id' => $baselineResultId,
            'new_failures' => $newFailures,
            'persistent_failures' => $persistentFailures,
            'newly_fixed' => $newlyFixed,
            'error' => $error,
        ];
    }
}
