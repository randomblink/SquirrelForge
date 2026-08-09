<?php

declare(strict_types=1);

namespace SquirrelForge\Testing;

use Closure;
use DateTimeImmutable;
use PDO;
use Throwable;

/**
 * Verifies end-to-end system scenarios across an integrated candidate
 * system in a production-representative test environment, per
 * 29_TESTING/SYSTEM-TESTS.md -- the sixth real component in
 * 29_TESTING, and structurally the same test-category shape this
 * layer's other components already established.
 *
 * `environment_ref` is a real, required precondition, the same reason
 * `SqliteSmokeTests` requires one: this spec's own Depends On names "a
 * representative test environment," and a system-test result that
 * doesn't know where it ran cannot honestly claim to be
 * production-representative.
 *
 * "Verify externally observable system behavior against test-plan
 * expectations and acceptance criteria" is genuine composition, the
 * same real "read the plan's own field, compute a real set difference
 * against what the closure reports" shape `SqliteIntegrationTests`
 * already established for `interface_contracts` -- here against
 * `SqliteTestPlanner`'s own `acceptance_criteria` (already persisted,
 * no further changes to that component needed). Any criterion the plan
 * named that the closure's own `verified_criteria` never mentions is
 * reported as `unverified_criteria`, real evidence of a gap rather
 * than assumed completeness.
 *
 * "Exercise representative lifecycle, persistence, failure,
 * recovery-scenario, archive, and restoration behavior where
 * applicable" is, like `SqliteIntegrationTests`' own scenario
 * coverage, only ever reported verbatim from the closure
 * (`scenario_coverage`) -- this class has no way to know which of
 * those apply to a given system under test, and Boundary explicitly
 * forbids it from owning archive/storage infrastructure or executing
 * recovery itself.
 *
 * "Passing system tests... do not independently certify platform
 * validity, release readiness, or deployment authorization" (Rule) is
 * upheld the same structural way every other category component in
 * this layer upholds its own Rule.
 */
final class SqliteSystemTests
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
            'CREATE TABLE IF NOT EXISTS system_test_results (
                result_id TEXT PRIMARY KEY,
                plan_id TEXT,
                subject_ref TEXT NOT NULL,
                environment_ref TEXT NOT NULL,
                status TEXT NOT NULL,
                passed INTEGER NOT NULL,
                failed INTEGER NOT NULL,
                skipped INTEGER NOT NULL,
                failures_json TEXT NOT NULL,
                verified_criteria_json TEXT NOT NULL,
                unverified_criteria_json TEXT NOT NULL,
                scenario_coverage_json TEXT NOT NULL,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array{plan_id?: ?string, subject_ref?: ?string, environment_ref?: ?string} $request
     * @param ?Closure $runSuite (?array $plan): array{passed?: int, failed?: int, skipped?: int, failures?: array<int, array{name: string, message: string}>, verified_criteria?: array<int, string>, scenario_coverage?: array<int, string>} the real end-to-end suite execution. Omitting it leaves the result at `Pending`.
     * @return array{
     *     outcome: string,
     *     result_id: ?string,
     *     status: string,
     *     subject_ref: ?string,
     *     environment_ref: ?string,
     *     passed: int,
     *     failed: int,
     *     skipped: int,
     *     failures: array<int, array{name: string, message: string}>,
     *     verified_criteria: array<int, string>,
     *     unverified_criteria: array<int, string>,
     *     scenario_coverage: array<int, string>,
     *     error: ?string
     * }
     */
    public function run(array $request, ?Closure $runSuite = null): array
    {
        $subjectRef = $request['subject_ref'] ?? null;
        $environmentRef = $request['environment_ref'] ?? null;

        if (!$this->present($subjectRef) || !$this->present($environmentRef)) {
            return $this->outcome('invalid', null, 'Rejected', $subjectRef, $environmentRef, 0, 0, 0, [], [], [], [], 'A system-test run requires a non-empty subject_ref and environment_ref.');
        }

        $planId = $request['plan_id'] ?? null;
        $plan = null;
        $requiredCriteria = [];

        if ($this->present($planId) && $this->testPlanner !== null) {
            $plan = $this->testPlanner->get($planId);

            if ($plan === null) {
                return $this->outcome('invalid', null, 'Rejected', $subjectRef, $environmentRef, 0, 0, 0, [], [], [], [], sprintf('Test plan "%s" does not exist.', $planId));
            }

            if (!in_array('System', $plan['categories'], true)) {
                return $this->outcome('rejected', null, 'Rejected', $subjectRef, $environmentRef, 0, 0, 0, [], [], [], [], sprintf('Test plan "%s" does not require System coverage.', $planId));
            }

            $requiredCriteria = $plan['acceptance_criteria'];
        }

        if ($runSuite === null) {
            $resultId = $this->record($planId, $subjectRef, $environmentRef, 'Pending', 0, 0, 0, [], [], $requiredCriteria, []);

            return $this->outcome('recorded', $resultId, 'Pending', $subjectRef, $environmentRef, 0, 0, 0, [], [], $requiredCriteria, [], null);
        }

        try {
            $suiteResult = $runSuite($plan);
        } catch (Throwable $e) {
            $failures = [['name' => 'suite_execution', 'message' => $e->getMessage()]];
            $resultId = $this->record($planId, $subjectRef, $environmentRef, 'Failed', 0, 1, 0, $failures, [], $requiredCriteria, []);

            return $this->outcome('recorded', $resultId, 'Failed', $subjectRef, $environmentRef, 0, 1, 0, $failures, [], $requiredCriteria, [], null);
        }

        $passed = (int) ($suiteResult['passed'] ?? 0);
        $failed = (int) ($suiteResult['failed'] ?? 0);
        $skipped = (int) ($suiteResult['skipped'] ?? 0);
        $failures = $suiteResult['failures'] ?? [];
        $verifiedCriteria = $suiteResult['verified_criteria'] ?? [];
        $scenarioCoverage = $suiteResult['scenario_coverage'] ?? [];
        $unverifiedCriteria = array_values(array_diff($requiredCriteria, $verifiedCriteria));

        $status = match (true) {
            $failed > 0 => 'Failed',
            $passed + $skipped === 0 => 'Failed',
            default => 'Passed',
        };

        $resultId = $this->record($planId, $subjectRef, $environmentRef, $status, $passed, $failed, $skipped, $failures, $verifiedCriteria, $unverifiedCriteria, $scenarioCoverage);

        return $this->outcome('recorded', $resultId, $status, $subjectRef, $environmentRef, $passed, $failed, $skipped, $failures, $verifiedCriteria, $unverifiedCriteria, $scenarioCoverage, null);
    }

    /**
     * @param array<int, array{name: string, message: string}> $failures
     * @param array<int, string> $verifiedCriteria
     * @param array<int, string> $unverifiedCriteria
     * @param array<int, string> $scenarioCoverage
     */
    private function record(?string $planId, string $subjectRef, string $environmentRef, string $status, int $passed, int $failed, int $skipped, array $failures, array $verifiedCriteria, array $unverifiedCriteria, array $scenarioCoverage): string
    {
        $resultId = 'system_test_result_' . bin2hex(random_bytes(12));

        $statement = $this->database->prepare(
            'INSERT INTO system_test_results (result_id, plan_id, subject_ref, environment_ref, status, passed, failed, skipped, failures_json, verified_criteria_json, unverified_criteria_json, scenario_coverage_json, created_at)
             VALUES (:result_id, :plan_id, :subject_ref, :environment_ref, :status, :passed, :failed, :skipped, :failures_json, :verified_criteria_json, :unverified_criteria_json, :scenario_coverage_json, :created_at)'
        );
        $statement->execute([
            'result_id' => $resultId,
            'plan_id' => $planId,
            'subject_ref' => $subjectRef,
            'environment_ref' => $environmentRef,
            'status' => $status,
            'passed' => $passed,
            'failed' => $failed,
            'skipped' => $skipped,
            'failures_json' => json_encode($failures, JSON_THROW_ON_ERROR),
            'verified_criteria_json' => json_encode($verifiedCriteria, JSON_THROW_ON_ERROR),
            'unverified_criteria_json' => json_encode($unverifiedCriteria, JSON_THROW_ON_ERROR),
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
        $statement = $this->database->prepare('SELECT * FROM system_test_results WHERE result_id = :result_id');
        $statement->execute(['result_id' => $resultId]);
        $row = $statement->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function history(string $subjectRef): array
    {
        $statement = $this->database->prepare('SELECT * FROM system_test_results WHERE subject_ref = :subject_ref ORDER BY rowid ASC');
        $statement->execute(['subject_ref' => $subjectRef]);

        return array_map(fn(array $row): array => $this->hydrate($row), $statement->fetchAll());
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        foreach (['failures', 'verified_criteria', 'unverified_criteria', 'scenario_coverage'] as $field) {
            $row[$field] = json_decode($row[$field . '_json'], true, flags: JSON_THROW_ON_ERROR);
            unset($row[$field . '_json']);
        }

        return $row;
    }

    private function present(mixed $value): bool
    {
        return is_string($value) && $value !== '';
    }

    /**
     * @param array<int, array{name: string, message: string}> $failures
     * @param array<int, string> $verifiedCriteria
     * @param array<int, string> $unverifiedCriteria
     * @param array<int, string> $scenarioCoverage
     * @return array{
     *     outcome: string,
     *     result_id: ?string,
     *     status: string,
     *     subject_ref: ?string,
     *     environment_ref: ?string,
     *     passed: int,
     *     failed: int,
     *     skipped: int,
     *     failures: array<int, array{name: string, message: string}>,
     *     verified_criteria: array<int, string>,
     *     unverified_criteria: array<int, string>,
     *     scenario_coverage: array<int, string>,
     *     error: ?string
     * }
     */
    private function outcome(
        string $outcome,
        ?string $resultId,
        string $status,
        mixed $subjectRef,
        mixed $environmentRef,
        int $passed,
        int $failed,
        int $skipped,
        array $failures,
        array $verifiedCriteria,
        array $unverifiedCriteria,
        array $scenarioCoverage,
        ?string $error
    ): array {
        return [
            'outcome' => $outcome,
            'result_id' => $resultId,
            'status' => $status,
            'subject_ref' => is_string($subjectRef) ? $subjectRef : null,
            'environment_ref' => is_string($environmentRef) ? $environmentRef : null,
            'passed' => $passed,
            'failed' => $failed,
            'skipped' => $skipped,
            'failures' => $failures,
            'verified_criteria' => $verifiedCriteria,
            'unverified_criteria' => $unverifiedCriteria,
            'scenario_coverage' => $scenarioCoverage,
            'error' => $error,
        ];
    }
}
