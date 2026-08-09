<?php

declare(strict_types=1);

namespace SquirrelForge\Testing;

use Closure;
use DateTimeImmutable;
use PDO;
use Throwable;

/**
 * Verifies behavior across real component and interface boundaries and
 * produces integration-test results and evidence references, per
 * 29_TESTING/INTEGRATION-TESTS.md -- the fourth real component in
 * 29_TESTING, and structurally the same test-category shape
 * `SqliteUnitTests`/`SqliteSmokeTests` already established.
 *
 * "Verify integration behavior against approved interface contracts
 * and test-plan coverage" is genuine composition, not a documentation
 * reference: this class reads `SqliteTestPlanner`'s own real
 * `interface_contracts` field (a small, genuinely needed addition to
 * that component made alongside this one) and requires the caller to
 * declare which of those contracts this specific run actually
 * exercised (`covered_contracts`). Any contract the plan named that
 * this run's `covered_contracts` never mentions is reported as
 * `uncovered_contracts` -- real evidence of a coverage gap, not
 * fabricated completeness.
 *
 * "Exercise permission-denial, partial-failure, timeout,
 * retryable-status, and data-consistency scenarios where relevant" is
 * left entirely to the caller-supplied `$runSuite` closure -- this
 * class has no way to know which of those scenarios apply to a given
 * integration boundary, and Boundary explicitly forbids it from making
 * authorization decisions or executing retry/recovery policy itself,
 * so it only ever reports whatever scenario coverage the closure
 * itself declares it exercised (`scenario_coverage`), never inventing
 * or assuming any.
 *
 * "Integration-test conclusions are limited to tested interactions and
 * contracts" (Rule) is upheld the same structural way
 * `SqliteUnitTests`' own Rule is: only a testing-scope status and
 * evidence are returned, never anything resembling a governance or
 * release decision.
 */
final class SqliteIntegrationTests
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
            'CREATE TABLE IF NOT EXISTS integration_test_results (
                result_id TEXT PRIMARY KEY,
                plan_id TEXT,
                subject_ref TEXT NOT NULL,
                status TEXT NOT NULL,
                passed INTEGER NOT NULL,
                failed INTEGER NOT NULL,
                skipped INTEGER NOT NULL,
                failures_json TEXT NOT NULL,
                covered_contracts_json TEXT NOT NULL,
                uncovered_contracts_json TEXT NOT NULL,
                scenario_coverage_json TEXT NOT NULL,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array{plan_id?: ?string, subject_ref?: ?string} $request
     * @param ?Closure $runSuite (?array $plan): array{passed?: int, failed?: int, skipped?: int, failures?: array<int, array{name: string, message: string}>, covered_contracts?: array<int, string>, scenario_coverage?: array<int, string>} the real integration-suite execution. Omitting it leaves the result at `Pending`.
     * @return array{
     *     outcome: string,
     *     result_id: ?string,
     *     status: string,
     *     subject_ref: ?string,
     *     passed: int,
     *     failed: int,
     *     skipped: int,
     *     failures: array<int, array{name: string, message: string}>,
     *     covered_contracts: array<int, string>,
     *     uncovered_contracts: array<int, string>,
     *     scenario_coverage: array<int, string>,
     *     error: ?string
     * }
     */
    public function run(array $request, ?Closure $runSuite = null): array
    {
        $subjectRef = $request['subject_ref'] ?? null;

        if (!is_string($subjectRef) || $subjectRef === '') {
            return $this->outcome('invalid', null, 'Rejected', $subjectRef, 0, 0, 0, [], [], [], [], 'An integration-test run requires a non-empty subject_ref.');
        }

        $planId = $request['plan_id'] ?? null;
        $plan = null;
        $requiredContracts = [];

        if (is_string($planId) && $planId !== '' && $this->testPlanner !== null) {
            $plan = $this->testPlanner->get($planId);

            if ($plan === null) {
                return $this->outcome('invalid', null, 'Rejected', $subjectRef, 0, 0, 0, [], [], [], [], sprintf('Test plan "%s" does not exist.', $planId));
            }

            if (!in_array('Integration', $plan['categories'], true)) {
                return $this->outcome('rejected', null, 'Rejected', $subjectRef, 0, 0, 0, [], [], [], [], sprintf('Test plan "%s" does not require Integration coverage.', $planId));
            }

            $requiredContracts = $plan['interface_contracts'];
        }

        if ($runSuite === null) {
            $resultId = $this->record($planId, $subjectRef, 'Pending', 0, 0, 0, [], [], $requiredContracts, []);

            return $this->outcome('recorded', $resultId, 'Pending', $subjectRef, 0, 0, 0, [], [], $requiredContracts, [], null);
        }

        try {
            $suiteResult = $runSuite($plan);
        } catch (Throwable $e) {
            $failures = [['name' => 'suite_execution', 'message' => $e->getMessage()]];
            $resultId = $this->record($planId, $subjectRef, 'Failed', 0, 1, 0, $failures, [], $requiredContracts, []);

            return $this->outcome('recorded', $resultId, 'Failed', $subjectRef, 0, 1, 0, $failures, [], $requiredContracts, [], null);
        }

        $passed = (int) ($suiteResult['passed'] ?? 0);
        $failed = (int) ($suiteResult['failed'] ?? 0);
        $skipped = (int) ($suiteResult['skipped'] ?? 0);
        $failures = $suiteResult['failures'] ?? [];
        $coveredContracts = $suiteResult['covered_contracts'] ?? [];
        $scenarioCoverage = $suiteResult['scenario_coverage'] ?? [];
        $uncoveredContracts = array_values(array_diff($requiredContracts, $coveredContracts));

        $status = match (true) {
            $failed > 0 => 'Failed',
            $passed + $skipped === 0 => 'Failed',
            default => 'Passed',
        };

        $resultId = $this->record($planId, $subjectRef, $status, $passed, $failed, $skipped, $failures, $coveredContracts, $uncoveredContracts, $scenarioCoverage);

        return $this->outcome('recorded', $resultId, $status, $subjectRef, $passed, $failed, $skipped, $failures, $coveredContracts, $uncoveredContracts, $scenarioCoverage, null);
    }

    /**
     * @param array<int, array{name: string, message: string}> $failures
     * @param array<int, string> $coveredContracts
     * @param array<int, string> $uncoveredContracts
     * @param array<int, string> $scenarioCoverage
     */
    private function record(?string $planId, string $subjectRef, string $status, int $passed, int $failed, int $skipped, array $failures, array $coveredContracts, array $uncoveredContracts, array $scenarioCoverage): string
    {
        $resultId = 'integration_test_result_' . bin2hex(random_bytes(12));

        $statement = $this->database->prepare(
            'INSERT INTO integration_test_results (result_id, plan_id, subject_ref, status, passed, failed, skipped, failures_json, covered_contracts_json, uncovered_contracts_json, scenario_coverage_json, created_at)
             VALUES (:result_id, :plan_id, :subject_ref, :status, :passed, :failed, :skipped, :failures_json, :covered_contracts_json, :uncovered_contracts_json, :scenario_coverage_json, :created_at)'
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
            'covered_contracts_json' => json_encode($coveredContracts, JSON_THROW_ON_ERROR),
            'uncovered_contracts_json' => json_encode($uncoveredContracts, JSON_THROW_ON_ERROR),
            'scenario_coverage_json' => json_encode($scenarioCoverage, JSON_THROW_ON_ERROR),
            'created_at' => (new DateTimeImmutable())->format(DATE_ATOM),
        ]);

        return $resultId;
    }

    /**
     * @return ?array<string, mixed>
     */
    public function get(string $resultId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM integration_test_results WHERE result_id = :result_id');
        $statement->execute(['result_id' => $resultId]);
        $row = $statement->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function history(string $subjectRef): array
    {
        $statement = $this->database->prepare('SELECT * FROM integration_test_results WHERE subject_ref = :subject_ref ORDER BY rowid ASC');
        $statement->execute(['subject_ref' => $subjectRef]);

        return array_map(fn(array $row): array => $this->hydrate($row), $statement->fetchAll());
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        foreach (['failures', 'covered_contracts', 'uncovered_contracts', 'scenario_coverage'] as $field) {
            $row[$field] = json_decode($row[$field . '_json'], true, flags: JSON_THROW_ON_ERROR);
            unset($row[$field . '_json']);
        }

        return $row;
    }

    /**
     * @param array<int, array{name: string, message: string}> $failures
     * @param array<int, string> $coveredContracts
     * @param array<int, string> $uncoveredContracts
     * @param array<int, string> $scenarioCoverage
     * @return array{
     *     outcome: string,
     *     result_id: ?string,
     *     status: string,
     *     subject_ref: ?string,
     *     passed: int,
     *     failed: int,
     *     skipped: int,
     *     failures: array<int, array{name: string, message: string}>,
     *     covered_contracts: array<int, string>,
     *     uncovered_contracts: array<int, string>,
     *     scenario_coverage: array<int, string>,
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
        array $coveredContracts,
        array $uncoveredContracts,
        array $scenarioCoverage,
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
            'covered_contracts' => $coveredContracts,
            'uncovered_contracts' => $uncoveredContracts,
            'scenario_coverage' => $scenarioCoverage,
            'error' => $error,
        ];
    }
}
