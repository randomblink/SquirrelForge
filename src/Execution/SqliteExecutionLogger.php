<?php

declare(strict_types=1);

namespace SquirrelForge\Execution;

use DateTimeImmutable;
use PDO;

/**
 * Records timestamp, execution and task IDs, actor, action type,
 * sanitized inputs, outcome, duration, checkpoint, and error category,
 * as emitted by the other Execution components, per
 * 20_EXECUTION/EXECUTION-LOGGER.md -- the second real component in
 * 20_EXECUTION.
 *
 * This spec's own "Depends On" (`EXECUTION-ENGINE.md`,
 * `WORKFLOW-EXECUTOR.md`, `ACTION-DISPATCHER.md`, `CHECKPOINT-MANAGER.md`,
 * `ROLLBACK-MANAGER.md`) describes producers of log entries, not code
 * this class must call -- its own text says entries arrive "as emitted
 * by the other Execution components," the same "pure sink, callers
 * supply the fact" shape `AuditTrail` already establishes for this
 * codebase. That is what makes this buildable now, before
 * `WORKFLOW-EXECUTOR.md`/`ROLLBACK-MANAGER.md`/most of this layer's
 * other components exist: nothing here needs to inject them.
 *
 * "Secrets and unnecessary personal data must be redacted" gets real,
 * two-tier enforcement rather than a caller-trust-only mechanism: a
 * fixed, case-insensitive pattern of common secret-shaped input key
 * names (password, token, secret, api_key, credential, authorization)
 * is always redacted as a built-in safety net this class enforces on
 * its own, the same defense-in-depth split `AuditTrail` draws between
 * absolute and conditional redaction -- plus any additional field the
 * caller names via `redact_fields` for entry-specific sensitivity this
 * class cannot know about on its own (a domain field like `ssn` or
 * `patient_name`). Redaction applies to `inputs`' own top-level keys
 * only; this class does not attempt to redact inside nested structures
 * it cannot interpret without inventing domain knowledge.
 *
 * "The Logger records what already happened; it does not classify a
 * failure's recovery path... or decide validation, artifact, or
 * workflow-completion outcomes" is upheld structurally: `outcome` and
 * `error_category` are caller-supplied strings recorded verbatim, never
 * interpreted, validated, or acted on. "Other Execution components
 * reference its entries as evidence; they do not treat it as a decision
 * authority" is upheld the same way `AuditTrail` achieves immutability:
 * this class exposes no update or delete method for a recorded entry at
 * all, so nothing in its own API surface can mutate one once written.
 *
 * SQLite-backed, matching `SqliteCheckpointManager` and this codebase's
 * other `Sqlite*` state owners -- an execution log genuinely needs to
 * survive the same process restarts `CHECKPOINT-MANAGER.md`'s resume
 * scenario already established for this layer.
 */
final class SqliteExecutionLogger
{
    private const REQUIRED_FIELDS = ['execution_id', 'actor', 'action_type', 'outcome'];

    private const ALWAYS_REDACTED_KEY_PATTERN = '/password|token|secret|api[_-]?key|credential|authorization/i';

    private const REDACTED_VALUE = '[REDACTED]';

    private PDO $database;

    public function __construct(string $databasePath)
    {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS execution_log_entries (
                log_ref TEXT PRIMARY KEY,
                execution_id TEXT NOT NULL,
                task_id TEXT,
                actor TEXT NOT NULL,
                action_type TEXT NOT NULL,
                inputs_json TEXT NOT NULL,
                outcome TEXT NOT NULL,
                duration_ms REAL,
                checkpoint_id TEXT,
                error_category TEXT,
                recorded_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array{
     *     execution_id?: ?string,
     *     task_id?: ?string,
     *     actor?: ?string,
     *     action_type?: ?string,
     *     inputs?: array<string, mixed>,
     *     outcome?: ?string,
     *     duration_ms?: ?float,
     *     checkpoint_id?: ?string,
     *     error_category?: ?string,
     *     timestamp?: ?string
     * } $entry
     * @param array{redact_fields?: array<int, string>} $options
     * @return array{log_ref: ?string, outcome: string, error: ?string}
     */
    public function record(array $entry, array $options = []): array
    {
        $missingField = $this->firstMissingField($entry);

        if ($missingField !== null) {
            return ['log_ref' => null, 'outcome' => 'invalid_entry', 'error' => sprintf('Missing required field "%s".', $missingField)];
        }

        $logRef = 'exec_log_' . bin2hex(random_bytes(12));
        $inputs = $this->redact(is_array($entry['inputs'] ?? null) ? $entry['inputs'] : [], $options['redact_fields'] ?? []);

        $statement = $this->database->prepare(
            'INSERT INTO execution_log_entries (log_ref, execution_id, task_id, actor, action_type, inputs_json, outcome, duration_ms, checkpoint_id, error_category, recorded_at)
             VALUES (:log_ref, :execution_id, :task_id, :actor, :action_type, :inputs_json, :outcome, :duration_ms, :checkpoint_id, :error_category, :recorded_at)'
        );
        $statement->execute([
            'log_ref' => $logRef,
            'execution_id' => $entry['execution_id'],
            'task_id' => $entry['task_id'] ?? null,
            'actor' => $entry['actor'],
            'action_type' => $entry['action_type'],
            'inputs_json' => json_encode($inputs, JSON_THROW_ON_ERROR),
            'outcome' => $entry['outcome'],
            'duration_ms' => $entry['duration_ms'] ?? null,
            'checkpoint_id' => $entry['checkpoint_id'] ?? null,
            'error_category' => $entry['error_category'] ?? null,
            'recorded_at' => $entry['timestamp'] ?? (new DateTimeImmutable())->format(DATE_ATOM),
        ]);

        return ['log_ref' => $logRef, 'outcome' => 'recorded', 'error' => null];
    }

    /**
     * @return ?array<string, mixed>
     */
    public function get(string $logRef): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM execution_log_entries WHERE log_ref = :log_ref');
        $statement->execute(['log_ref' => $logRef]);
        $row = $statement->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    /**
     * Every entry for one execution, in the order it was recorded.
     *
     * @return array<int, array<string, mixed>>
     */
    public function history(string $executionId): array
    {
        $statement = $this->database->prepare('SELECT * FROM execution_log_entries WHERE execution_id = :execution_id ORDER BY rowid ASC');
        $statement->execute(['execution_id' => $executionId]);

        return array_map(fn(array $row): array => $this->hydrate($row), $statement->fetchAll());
    }

    /**
     * @param array{execution_id?: string, task_id?: string, action_type?: string, outcome?: string, error_category?: string} $filters
     * @return array<int, array<string, mixed>>
     */
    public function search(array $filters = []): array
    {
        $clauses = [];
        $parameters = [];

        foreach (['execution_id', 'task_id', 'action_type', 'outcome', 'error_category'] as $field) {
            if (isset($filters[$field])) {
                $clauses[] = "{$field} = :{$field}";
                $parameters[$field] = $filters[$field];
            }
        }

        $sql = 'SELECT * FROM execution_log_entries' . ($clauses === [] ? '' : ' WHERE ' . implode(' AND ', $clauses)) . ' ORDER BY rowid ASC';
        $statement = $this->database->prepare($sql);
        $statement->execute($parameters);

        return array_map(fn(array $row): array => $this->hydrate($row), $statement->fetchAll());
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function firstMissingField(array $entry): ?string
    {
        foreach (self::REQUIRED_FIELDS as $field) {
            if (!isset($entry[$field]) || (is_string($entry[$field]) && $entry[$field] === '')) {
                return $field;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $inputs
     * @param array<int, string> $callerRedactFields
     * @return array<string, mixed>
     */
    private function redact(array $inputs, array $callerRedactFields): array
    {
        $redacted = [];

        foreach ($inputs as $key => $value) {
            $shouldRedact = in_array($key, $callerRedactFields, true) || preg_match(self::ALWAYS_REDACTED_KEY_PATTERN, (string) $key) === 1;
            $redacted[$key] = $shouldRedact ? self::REDACTED_VALUE : $value;
        }

        return $redacted;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        $row['inputs'] = json_decode($row['inputs_json'], true, flags: JSON_THROW_ON_ERROR);
        unset($row['inputs_json']);

        return $row;
    }
}
