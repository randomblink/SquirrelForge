<?php

declare(strict_types=1);

namespace SquirrelForge\Testing;

use Closure;
use DateTimeImmutable;
use PDO;
use Throwable;

/**
 * A short critical-path suite that determines whether a candidate
 * build or deployment is testably operational enough for further
 * evaluation, per 29_TESTING/SMOKE-TESTS.md -- the third real
 * component in 29_TESTING, and structurally the same test-category
 * shape `SqliteUnitTests` already established (plan cross-check,
 * caller-supplied execution closure, deterministic classification,
 * persisted evidence) applied to this spec's own genuinely different
 * responsibilities.
 *
 * Two Depends On inputs this spec names ("candidate build or
 * deployment reference, approved test environment") are real, required
 * preconditions here, not optional metadata: `build_ref` and
 * `environment_ref` must both be supplied, since a smoke suite that
 * doesn't know *what* build or *where* it ran cannot honestly answer
 * "is this candidate testably operational."
 *
 * "Stop or flag further testing according to the test plan when
 * critical smoke checks fail" is the one genuinely new mechanism this
 * category owns beyond `SqliteUnitTests`' own shape: the execution
 * closure reports whether an observed failure was on a critical check
 * (`critical_failure`), and `should_stop_further_testing` is computed
 * from that -- defaulting to `true` on any `Failed` result the closure
 * didn't explicitly mark non-critical, the safer default for a
 * component whose entire purpose is gating whether deeper evaluation
 * is even worthwhile. This flag is reported, never acted on: this
 * class does not itself skip or cancel any other test category, the
 * same "produce evidence, let the owning process decide" boundary
 * `SqliteUnitTests` already establishes for its own results.
 *
 * "A passing smoke suite is not by itself release approval or
 * deployment authorization" (Rule) is upheld the same way
 * `SqliteUnitTests`' own Rule is: this class returns only a testing-
 * scope status and evidence, never anything resembling a release or
 * deployment decision.
 */
final class SqliteSmokeTests
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
            'CREATE TABLE IF NOT EXISTS smoke_test_results (
                result_id TEXT PRIMARY KEY,
                plan_id TEXT,
                subject_ref TEXT NOT NULL,
                build_ref TEXT NOT NULL,
                environment_ref TEXT NOT NULL,
                status TEXT NOT NULL,
                passed INTEGER NOT NULL,
                failed INTEGER NOT NULL,
                skipped INTEGER NOT NULL,
                failures_json TEXT NOT NULL,
                should_stop_further_testing INTEGER NOT NULL,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array{plan_id?: ?string, subject_ref?: ?string, build_ref?: ?string, environment_ref?: ?string} $request
     * @param ?Closure $runSuite (?array $plan): array{passed?: int, failed?: int, skipped?: int, failures?: array<int, array{name: string, message: string}>, critical_failure?: bool} the real smoke-suite execution. Omitting it leaves the result at `Pending`.
     * @return array{
     *     outcome: string,
     *     result_id: ?string,
     *     status: string,
     *     subject_ref: ?string,
     *     build_ref: ?string,
     *     environment_ref: ?string,
     *     passed: int,
     *     failed: int,
     *     skipped: int,
     *     failures: array<int, array{name: string, message: string}>,
     *     should_stop_further_testing: bool,
     *     error: ?string
     * }
     */
    public function run(array $request, ?Closure $runSuite = null): array
    {
        $subjectRef = $request['subject_ref'] ?? null;
        $buildRef = $request['build_ref'] ?? null;
        $environmentRef = $request['environment_ref'] ?? null;

        if (!$this->present($subjectRef) || !$this->present($buildRef) || !$this->present($environmentRef)) {
            return $this->outcome('invalid', null, 'Rejected', $subjectRef, $buildRef, $environmentRef, 0, 0, 0, [], false, 'A smoke-test run requires a non-empty subject_ref, build_ref, and environment_ref.');
        }

        $planId = $request['plan_id'] ?? null;
        $plan = null;

        if ($this->present($planId) && $this->testPlanner !== null) {
            $plan = $this->testPlanner->get($planId);

            if ($plan === null) {
                return $this->outcome('invalid', null, 'Rejected', $subjectRef, $buildRef, $environmentRef, 0, 0, 0, [], false, sprintf('Test plan "%s" does not exist.', $planId));
            }

            if (!in_array('Smoke', $plan['categories'], true)) {
                return $this->outcome('rejected', null, 'Rejected', $subjectRef, $buildRef, $environmentRef, 0, 0, 0, [], false, sprintf('Test plan "%s" does not require Smoke coverage.', $planId));
            }
        }

        if ($runSuite === null) {
            $resultId = $this->record($planId, $subjectRef, $buildRef, $environmentRef, 'Pending', 0, 0, 0, [], false);

            return $this->outcome('recorded', $resultId, 'Pending', $subjectRef, $buildRef, $environmentRef, 0, 0, 0, [], false, null);
        }

        try {
            $suiteResult = $runSuite($plan);
        } catch (Throwable $e) {
            $failures = [['name' => 'suite_execution', 'message' => $e->getMessage()]];
            $resultId = $this->record($planId, $subjectRef, $buildRef, $environmentRef, 'Failed', 0, 1, 0, $failures, true);

            return $this->outcome('recorded', $resultId, 'Failed', $subjectRef, $buildRef, $environmentRef, 0, 1, 0, $failures, true, null);
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

        $shouldStop = $status === 'Failed' && ($suiteResult['critical_failure'] ?? true) === true;

        $resultId = $this->record($planId, $subjectRef, $buildRef, $environmentRef, $status, $passed, $failed, $skipped, $failures, $shouldStop);

        return $this->outcome('recorded', $resultId, $status, $subjectRef, $buildRef, $environmentRef, $passed, $failed, $skipped, $failures, $shouldStop, null);
    }

    /**
     * @param array<int, array{name: string, message: string}> $failures
     */
    private function record(?string $planId, string $subjectRef, string $buildRef, string $environmentRef, string $status, int $passed, int $failed, int $skipped, array $failures, bool $shouldStop): string
    {
        $resultId = 'smoke_test_result_' . bin2hex(random_bytes(12));

        $statement = $this->database->prepare(
            'INSERT INTO smoke_test_results (result_id, plan_id, subject_ref, build_ref, environment_ref, status, passed, failed, skipped, failures_json, should_stop_further_testing, created_at)
             VALUES (:result_id, :plan_id, :subject_ref, :build_ref, :environment_ref, :status, :passed, :failed, :skipped, :failures_json, :should_stop, :created_at)'
        );
        $statement->execute([
            'result_id' => $resultId,
            'plan_id' => $planId,
            'subject_ref' => $subjectRef,
            'build_ref' => $buildRef,
            'environment_ref' => $environmentRef,
            'status' => $status,
            'passed' => $passed,
            'failed' => $failed,
            'skipped' => $skipped,
            'failures_json' => json_encode($failures, JSON_THROW_ON_ERROR),
            'should_stop' => $shouldStop ? 1 : 0,
            'created_at' => (new DateTimeImmutable())->format(DATE_ATOM),
        ]);

        return $resultId;
    }

    /**
     * @return ?array<string, mixed>
     */
    public function get(string $resultId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM smoke_test_results WHERE result_id = :result_id');
        $statement->execute(['result_id' => $resultId]);
        $row = $statement->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function history(string $subjectRef): array
    {
        $statement = $this->database->prepare('SELECT * FROM smoke_test_results WHERE subject_ref = :subject_ref ORDER BY rowid ASC');
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
        $row['should_stop_further_testing'] = (bool) $row['should_stop_further_testing'];
        unset($row['failures_json']);

        return $row;
    }

    private function present(mixed $value): bool
    {
        return is_string($value) && $value !== '';
    }

    /**
     * @param array<int, array{name: string, message: string}> $failures
     * @return array{
     *     outcome: string,
     *     result_id: ?string,
     *     status: string,
     *     subject_ref: ?string,
     *     build_ref: ?string,
     *     environment_ref: ?string,
     *     passed: int,
     *     failed: int,
     *     skipped: int,
     *     failures: array<int, array{name: string, message: string}>,
     *     should_stop_further_testing: bool,
     *     error: ?string
     * }
     */
    private function outcome(
        string $outcome,
        ?string $resultId,
        string $status,
        mixed $subjectRef,
        mixed $buildRef,
        mixed $environmentRef,
        int $passed,
        int $failed,
        int $skipped,
        array $failures,
        bool $shouldStop,
        ?string $error
    ): array {
        return [
            'outcome' => $outcome,
            'result_id' => $resultId,
            'status' => $status,
            'subject_ref' => is_string($subjectRef) ? $subjectRef : null,
            'build_ref' => is_string($buildRef) ? $buildRef : null,
            'environment_ref' => is_string($environmentRef) ? $environmentRef : null,
            'passed' => $passed,
            'failed' => $failed,
            'skipped' => $skipped,
            'failures' => $failures,
            'should_stop_further_testing' => $shouldStop,
            'error' => $error,
        ];
    }
}
