<?php

declare(strict_types=1);

namespace SquirrelForge\Execution;

use DateTimeImmutable;
use PDO;

/**
 * Collects, correlates, and assembles references to execution outputs
 * from completed actions into an Execution Result Set, without
 * deciding whether those outputs are correct, acceptable, or
 * sufficient for the workflow to advance, per
 * 20_EXECUTION/RESULT-COLLECTOR.md -- the eighth real component in
 * 20_EXECUTION.
 *
 * "The Result Collector collects references; it does not validate"
 * (Purpose) is upheld structurally: every field this class stores is a
 * reference (`subject_ref`, `version_ref`, `validation_record_ref`) --
 * `attachValidation()` records an already-produced validation-record
 * reference the caller obtained from the real `EngineValidation`
 * elsewhere, never calling it or interpreting its contents itself, the
 * same "reference, don't recompute" boundary this codebase already
 * applies to `IntegrationManager`'s treatment of governance decisions.
 *
 * "Detect missing expected result references" requires knowing what
 * was expected in the first place -- something no result reference
 * carries on its own -- so `registerExpected()` is a real, separate
 * step: a workflow step's expected output slots are declared before or
 * alongside collection, and `assemble()`'s `Missing` list is the real
 * set difference between what was registered and what has actually
 * been `Received`/`Referenced`, not a guess.
 *
 * "Detect duplicate collection entries... flagging them without
 * deleting or reconciling the underlying record" is real, checked
 * logic: a second `collect()` call naming the same `expected_output_ref`
 * for the same `workflow_step_ref` a prior call already filled is
 * recorded with Collection Finding `Duplicate` -- inserted as a new
 * row alongside the original, never overwriting or removing it, since
 * only `14_ENGINE/STATE-MANAGER.md` (Boundary) owns reconciling
 * authoritative duplicate outputs. An `expected_output_ref` is optional:
 * a result with none is always `Received`, since duplicate detection
 * requires a real declared identity to collide against, not a
 * fabricated one.
 *
 * SQLite-backed, matching `SqliteCheckpointManager`: correlating
 * "missing" and "duplicate" findings genuinely requires state that
 * persists across the separate `collect()` calls each individual
 * action result arrives through, not a single in-memory pass.
 * "Preserve collection traceability" composes the already-built
 * `SqliteExecutionLogger`, the same pattern established across this
 * layer.
 */
final class SqliteResultCollector
{
    private PDO $database;

    public function __construct(string $databasePath, private readonly ?SqliteExecutionLogger $logger = null)
    {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS result_references (
                result_ref_id TEXT PRIMARY KEY,
                execution_ref TEXT NOT NULL,
                workflow_step_ref TEXT NOT NULL,
                action_ref TEXT,
                dispatch_ref TEXT,
                output_type TEXT,
                subject_ref TEXT,
                version_ref TEXT,
                expected_output_ref TEXT,
                validation_record_ref TEXT,
                collection_finding TEXT NOT NULL,
                recorded_at TEXT NOT NULL
            )'
        );
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS expected_outputs (
                workflow_step_ref TEXT NOT NULL,
                expected_output_ref TEXT NOT NULL,
                PRIMARY KEY (workflow_step_ref, expected_output_ref)
            )'
        );
    }

    /**
     * Declares the expected output slots for a workflow step, so
     * `assemble()` can honestly detect what is still `Missing`.
     *
     * @param array<int, string> $expectedOutputRefs
     */
    public function registerExpected(string $workflowStepRef, array $expectedOutputRefs): void
    {
        $statement = $this->database->prepare(
            'INSERT OR IGNORE INTO expected_outputs (workflow_step_ref, expected_output_ref) VALUES (:workflow_step_ref, :expected_output_ref)'
        );

        foreach ($expectedOutputRefs as $expectedOutputRef) {
            $statement->execute(['workflow_step_ref' => $workflowStepRef, 'expected_output_ref' => $expectedOutputRef]);
        }
    }

    /**
     * Collection Process steps 1-6: receives, correlates, and registers
     * a single action-result reference.
     *
     * @param array{
     *     execution_ref?: ?string,
     *     workflow_step_ref?: ?string,
     *     action_ref?: ?string,
     *     dispatch_ref?: ?string,
     *     output_type?: ?string,
     *     subject_ref?: ?string,
     *     version_ref?: ?string,
     *     expected_output_ref?: ?string
     * } $entry
     * @return array{outcome: string, result_ref_id: ?string, collection_finding: ?string, error: ?string}
     */
    public function collect(array $entry): array
    {
        $executionRef = $entry['execution_ref'] ?? null;
        $workflowStepRef = $entry['workflow_step_ref'] ?? null;

        if (!$this->presentAndNonEmpty($executionRef) || !$this->presentAndNonEmpty($workflowStepRef)) {
            return ['outcome' => 'invalid', 'result_ref_id' => null, 'collection_finding' => null, 'error' => 'A result reference requires a non-empty execution_ref and workflow_step_ref.'];
        }

        $expectedOutputRef = $entry['expected_output_ref'] ?? null;
        $finding = $this->presentAndNonEmpty($expectedOutputRef) && $this->alreadyFilled($workflowStepRef, $expectedOutputRef)
            ? 'Duplicate'
            : 'Received';

        $resultRefId = 'result_' . bin2hex(random_bytes(12));

        $statement = $this->database->prepare(
            'INSERT INTO result_references
                (result_ref_id, execution_ref, workflow_step_ref, action_ref, dispatch_ref, output_type, subject_ref, version_ref, expected_output_ref, validation_record_ref, collection_finding, recorded_at)
             VALUES
                (:result_ref_id, :execution_ref, :workflow_step_ref, :action_ref, :dispatch_ref, :output_type, :subject_ref, :version_ref, :expected_output_ref, NULL, :collection_finding, :recorded_at)'
        );
        $statement->execute([
            'result_ref_id' => $resultRefId,
            'execution_ref' => $executionRef,
            'workflow_step_ref' => $workflowStepRef,
            'action_ref' => $entry['action_ref'] ?? null,
            'dispatch_ref' => $entry['dispatch_ref'] ?? null,
            'output_type' => $entry['output_type'] ?? null,
            'subject_ref' => $entry['subject_ref'] ?? null,
            'version_ref' => $entry['version_ref'] ?? null,
            'expected_output_ref' => $expectedOutputRef,
            'collection_finding' => $finding,
            'recorded_at' => (new DateTimeImmutable())->format(DATE_ATOM),
        ]);

        $this->logger?->record([
            'execution_id' => $executionRef,
            'task_id' => $entry['action_ref'] ?? null,
            'actor' => 'result_collector',
            'action_type' => 'result_collection',
            'outcome' => $finding,
        ]);

        return ['outcome' => 'collected', 'result_ref_id' => $resultRefId, 'collection_finding' => $finding, 'error' => null];
    }

    /**
     * Collection Process step 9: attaches an already-produced,
     * standardized validation-record reference. Never calls
     * EngineValidation or interprets its contents itself.
     *
     * @return array{outcome: string, error: ?string}
     */
    public function attachValidation(string $resultRefId, string $validationRecordRef): array
    {
        $record = $this->get($resultRefId);

        if ($record === null) {
            return ['outcome' => 'not_found', 'error' => sprintf('Result reference "%s" does not exist.', $resultRefId)];
        }

        $statement = $this->database->prepare(
            "UPDATE result_references SET validation_record_ref = :validation_record_ref, collection_finding = 'Referenced' WHERE result_ref_id = :result_ref_id"
        );
        $statement->execute(['validation_record_ref' => $validationRecordRef, 'result_ref_id' => $resultRefId]);

        return ['outcome' => 'attached', 'error' => null];
    }

    /**
     * Collection Process steps 4, 7: assembles the Execution Result Set
     * for one workflow step from what has actually been collected.
     *
     * @return array{
     *     workflow_step_ref: string,
     *     included_result_references: array<int, array<string, mixed>>,
     *     missing_references: array<int, string>,
     *     duplicate_references: array<int, array<string, mixed>>,
     *     validation_subject_references: array<int, array{subject_ref: ?string, version_ref: ?string}>
     * }
     */
    public function assemble(string $workflowStepRef): array
    {
        $included = $this->forStep($workflowStepRef);
        $duplicates = array_values(array_filter($included, static fn(array $r): bool => $r['collection_finding'] === 'Duplicate'));

        $fulfilled = [];

        foreach ($included as $record) {
            if (in_array($record['collection_finding'], ['Received', 'Referenced'], true) && $record['expected_output_ref'] !== null) {
                $fulfilled[$record['expected_output_ref']] = true;
            }
        }

        $statement = $this->database->prepare('SELECT expected_output_ref FROM expected_outputs WHERE workflow_step_ref = :workflow_step_ref');
        $statement->execute(['workflow_step_ref' => $workflowStepRef]);
        $expected = array_column($statement->fetchAll(), 'expected_output_ref');

        $missing = array_values(array_filter($expected, static fn(string $ref): bool => !isset($fulfilled[$ref])));

        $subjects = [];
        $seen = [];

        foreach ($included as $record) {
            $key = ($record['subject_ref'] ?? '') . '|' . ($record['version_ref'] ?? '');

            if (($record['subject_ref'] ?? null) === null || isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $subjects[] = ['subject_ref' => $record['subject_ref'], 'version_ref' => $record['version_ref']];
        }

        return [
            'workflow_step_ref' => $workflowStepRef,
            'included_result_references' => $included,
            'missing_references' => $missing,
            'duplicate_references' => $duplicates,
            'validation_subject_references' => $subjects,
        ];
    }

    /**
     * @return ?array<string, mixed>
     */
    public function get(string $resultRefId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM result_references WHERE result_ref_id = :result_ref_id');
        $statement->execute(['result_ref_id' => $resultRefId]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Every result reference collected for an execution, across every
     * workflow step -- what `EXECUTION-REPORTER.md`'s own "Changed
     * Artifact References" field needs, since `assemble()` only scopes
     * to one step at a time.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forExecution(string $executionRef): array
    {
        $statement = $this->database->prepare('SELECT * FROM result_references WHERE execution_ref = :execution_ref ORDER BY rowid ASC');
        $statement->execute(['execution_ref' => $executionRef]);

        return $statement->fetchAll();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function forStep(string $workflowStepRef): array
    {
        $statement = $this->database->prepare('SELECT * FROM result_references WHERE workflow_step_ref = :workflow_step_ref ORDER BY rowid ASC');
        $statement->execute(['workflow_step_ref' => $workflowStepRef]);

        return $statement->fetchAll();
    }

    private function alreadyFilled(string $workflowStepRef, string $expectedOutputRef): bool
    {
        $statement = $this->database->prepare(
            "SELECT 1 FROM result_references
             WHERE workflow_step_ref = :workflow_step_ref
               AND expected_output_ref = :expected_output_ref
               AND collection_finding IN ('Received', 'Referenced')
             LIMIT 1"
        );
        $statement->execute(['workflow_step_ref' => $workflowStepRef, 'expected_output_ref' => $expectedOutputRef]);

        return $statement->fetch() !== false;
    }

    private function presentAndNonEmpty(mixed $value): bool
    {
        return is_string($value) && $value !== '';
    }
}
