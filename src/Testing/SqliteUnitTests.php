<?php

declare(strict_types=1);

namespace SquirrelForge\Testing;

use Closure;
use DateTimeImmutable;
use PDO;
use Throwable;

/**
 * Verifies individual components or units in isolation and produces
 * deterministic unit-test results and evidence references, per
 * 29_TESTING/UNIT-TESTS.md -- the second real component in 29_TESTING,
 * and the first of this layer's five test-category components.
 *
 * The actual test execution mechanics are a caller-supplied `Closure`
 * (`$runSuite`) -- this framework has no code-execution sandbox of its
 * own to run an arbitrary unit-test suite in, and even if it did,
 * PHPUnit, a JS test runner, and a Python test runner share no common
 * invocation protocol, the same "one fixed mechanism would fabricate a
 * choice this spec never makes" reasoning behind every provider-facing
 * closure already established across this codebase (`ActionDispatcher`,
 * `RollbackManager`). Omitting it is a real dry run landing at
 * `Pending`, never fabricating a pass or fail this class never
 * observed.
 *
 * "Own unit-test definitions, execution at unit scope" gets one real,
 * checked precondition: when a `SqliteTestPlanner` is configured and a
 * `plan_id` is supplied, this class confirms the referenced plan's own
 * `categories` actually name `Unit` before running anything -- a plan
 * that never called for unit coverage should not silently accumulate
 * unit-test evidence against it.
 *
 * "Preserve deterministic and reproducible unit-test results" treats a
 * suite that reports zero executed tests as `Failed`, not a vacuous
 * `Passed` -- a run that observed nothing genuinely proved nothing,
 * the same "don't let absence of evidence look like a real result"
 * discipline `EngineValidation`'s own fail-closed decision table
 * already applies. A closure that throws is likewise `Failed`, with
 * the exception message captured as real failure evidence rather than
 * an uncaught crash -- the same "never let a caller-supplied closure's
 * exception escape uncaught" pattern `IntegrationAuthentication`/
 * `ActionDispatcher` already establish.
 *
 * "Unit-test results are testing evidence. Downstream Validation and
 * Quality Gate owners decide how that evidence affects platform
 * acceptance and governance decisions" (Rule) is upheld structurally:
 * this class never returns anything resembling an acceptance or
 * governance verdict, only a `Passed`/`Failed`/`Pending` testing-scope
 * status and the raw counts/failures/coverage reference evidence for
 * `14_ENGINE/VALIDATION.md`/`23_GOVERNANCE/QUALITY-GATES.md` to
 * interpret.
 *
 * SQLite-backed for "produce failure evidence and coverage references
 * for reporting and downstream validation" -- `TEST-REPORTING.md`
 * needs to read these results back later, the same cross-call
 * persistence reasoning `SqliteTestPlanner` already established for
 * this layer.
 */
final class SqliteUnitTests
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
            'CREATE TABLE IF NOT EXISTS unit_test_results (
                result_id TEXT PRIMARY KEY,
                plan_id TEXT,
                subject_ref TEXT NOT NULL,
                status TEXT NOT NULL,
                passed INTEGER NOT NULL,
                failed INTEGER NOT NULL,
                skipped INTEGER NOT NULL,
                failures_json TEXT NOT NULL,
                coverage_ref TEXT,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array{plan_id?: ?string, subject_ref?: ?string} $request
     * @param ?Closure $runSuite (?array $plan): array{passed?: int, failed?: int, skipped?: int, failures?: array<int, array{name: string, message: string}>, coverage_ref?: ?string} the real unit-test execution. Omitting it leaves the result at `Pending` without fabricating a pass or fail.
     * @return array{
     *     outcome: string,
     *     result_id: ?string,
     *     status: string,
     *     subject_ref: ?string,
     *     passed: int,
     *     failed: int,
     *     skipped: int,
     *     failures: array<int, array{name: string, message: string}>,
     *     coverage_ref: ?string,
     *     error: ?string
     * }
     */
    public function run(array $request, ?Closure $runSuite = null): array
    {
        $subjectRef = $request['subject_ref'] ?? null;

        if (!is_string($subjectRef) || $subjectRef === '') {
            return $this->outcome('invalid', null, 'Rejected', null, 0, 0, 0, [], null, 'A unit-test run requires a non-empty subject_ref.');
        }

        $planId = $request['plan_id'] ?? null;
        $plan = null;

        if (is_string($planId) && $planId !== '' && $this->testPlanner !== null) {
            $plan = $this->testPlanner->get($planId);

            if ($plan === null) {
                return $this->outcome('invalid', null, 'Rejected', $subjectRef, 0, 0, 0, [], null, sprintf('Test plan "%s" does not exist.', $planId));
            }

            if (!in_array('Unit', $plan['categories'], true)) {
                return $this->outcome('rejected', null, 'Rejected', $subjectRef, 0, 0, 0, [], null, sprintf('Test plan "%s" does not require Unit coverage.', $planId));
            }
        }

        if ($runSuite === null) {
            $resultId = $this->record($planId, $subjectRef, 'Pending', 0, 0, 0, [], null);

            return $this->outcome('recorded', $resultId, 'Pending', $subjectRef, 0, 0, 0, [], null, null);
        }

        try {
            $suiteResult = $runSuite($plan);
        } catch (Throwable $e) {
            $failures = [['name' => 'suite_execution', 'message' => $e->getMessage()]];
            $resultId = $this->record($planId, $subjectRef, 'Failed', 0, 1, 0, $failures, null);

            return $this->outcome('recorded', $resultId, 'Failed', $subjectRef, 0, 1, 0, $failures, null, null);
        }

        $passed = (int) ($suiteResult['passed'] ?? 0);
        $failed = (int) ($suiteResult['failed'] ?? 0);
        $skipped = (int) ($suiteResult['skipped'] ?? 0);
        $failures = $suiteResult['failures'] ?? [];
        $coverageRef = $suiteResult['coverage_ref'] ?? null;

        $status = match (true) {
            $failed > 0 => 'Failed',
            $passed + $skipped === 0 => 'Failed',
            default => 'Passed',
        };

        $resultId = $this->record($planId, $subjectRef, $status, $passed, $failed, $skipped, $failures, $coverageRef);

        return $this->outcome('recorded', $resultId, $status, $subjectRef, $passed, $failed, $skipped, $failures, $coverageRef, null);
    }

    /**
     * @param array<int, array{name: string, message: string}> $failures
     */
    private function record(?string $planId, string $subjectRef, string $status, int $passed, int $failed, int $skipped, array $failures, ?string $coverageRef): string
    {
        $resultId = 'unit_test_result_' . bin2hex(random_bytes(12));

        $statement = $this->database->prepare(
            'INSERT INTO unit_test_results (result_id, plan_id, subject_ref, status, passed, failed, skipped, failures_json, coverage_ref, created_at)
             VALUES (:result_id, :plan_id, :subject_ref, :status, :passed, :failed, :skipped, :failures_json, :coverage_ref, :created_at)'
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
            'coverage_ref' => $coverageRef,
            'created_at' => (new DateTimeImmutable())->format(DATE_ATOM),
        ]);

        return $resultId;
    }

    /**
     * @return ?array<string, mixed>
     */
    public function get(string $resultId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM unit_test_results WHERE result_id = :result_id');
        $statement->execute(['result_id' => $resultId]);
        $row = $statement->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function history(string $subjectRef): array
    {
        $statement = $this->database->prepare('SELECT * FROM unit_test_results WHERE subject_ref = :subject_ref ORDER BY rowid ASC');
        $statement->execute(['subject_ref' => $subjectRef]);

        return array_map(fn(array $row): array => $this->hydrate($row), $statement->fetchAll());
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        $row['failures'] = json_decode($row['failures_json'], true, flags: JSON_THROW_ON_ERROR);
        unset($row['failures_json']);

        return $row;
    }

    /**
     * @param array<int, array{name: string, message: string}> $failures
     * @return array{
     *     outcome: string,
     *     result_id: ?string,
     *     status: string,
     *     subject_ref: ?string,
     *     passed: int,
     *     failed: int,
     *     skipped: int,
     *     failures: array<int, array{name: string, message: string}>,
     *     coverage_ref: ?string,
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
        ?string $coverageRef,
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
            'coverage_ref' => $coverageRef,
            'error' => $error,
        ];
    }
}
