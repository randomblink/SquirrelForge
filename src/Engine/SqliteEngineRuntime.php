<?php

declare(strict_types=1);

namespace SquirrelForge\Engine;

use Closure;
use JsonException;
use PDO;
use PDOException;
use SquirrelForge\Contracts\EngineRuntimeInterface;

final class SqliteEngineRuntime implements EngineRuntimeInterface
{
    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly ?Closure $validationDecision = null
    ) {
        if ($databasePath === '') {
            throw new PDOException('SQLite Engine database path must not be empty.');
        }

        $directory = dirname($databasePath);

        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new PDOException('SQLite Engine database directory could not be created.');
        }

        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA foreign_keys = ON');
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->migrate();
    }

    public function submit(
        array $request,
        string $projectRef,
        string $identityRef,
        string $permissionRef,
        string $idempotencyKey,
        string $correlationId
    ): array {
        if ($permissionRef === 'permission:denied') {
            return $this->error('UNAUTHORIZED', 'The supplied permission reference was denied.', false);
        }

        $transactionStarted = false;

        try {
            $this->database->exec('BEGIN IMMEDIATE');
            $transactionStarted = true;
            $existing = $this->executionForIdempotencyKey($idempotencyKey);

            if ($existing !== null) {
                $this->database->exec('COMMIT');
                $transactionStarted = false;

                return ['execution_ref' => $existing];
            }

            $executionRef = 'exec_' . bin2hex(random_bytes(12));
            $stateRef = 'state_' . $executionRef;
            $validationRef = 'validation_' . $executionRef;
            $resultSetRef = 'result_set_' . $executionRef;
            $reportRef = 'report_' . $executionRef;
            $decision = $this->decisionFor($request);
            $accepted = in_array($decision, ['ACCEPTED', 'ACCEPTED_WITH_LIMITATIONS'], true);
            $limitations = $decision === 'ACCEPTED_WITH_LIMITATIONS'
                ? ['The deterministic persistent workflow completed with declared limitations.']
                : [];
            $completedAt = gmdate(DATE_RFC3339);
            $validation = [
                'schema_version' => '1.0.0',
                'validation_id' => $validationRef,
                'request_id' => (string) ($request['request_id'] ?? $executionRef),
                'correlation_id' => $correlationId,
                'subject' => [
                    'type' => 'execution_result',
                    'refs' => [$resultSetRef],
                    'version_ref' => $executionRef,
                ],
                'started_at' => $completedAt,
                'completed_at' => $completedAt,
                'decision' => $decision,
                'items' => [
                    [
                        'item_id' => 'validation_item_' . $executionRef,
                        'stage' => 'ACCEPTANCE',
                        'requirement_ref' => 'deterministic_persistent_workflow',
                        'description' => 'The persistent reference workflow applies its configured validation decision.',
                        'required' => true,
                        'blocking' => true,
                        'owner_ref' => 'sqlite_engine_runtime',
                        'method' => 'deterministic_decision',
                        'expected' => 'Configured validation decision is preserved.',
                        'evidence_refs' => [$resultSetRef],
                        'status' => $accepted ? 'PASSED' : 'FAILED',
                        'attempt' => 1,
                        'failure_code' => $accepted ? null : 'DETERMINISTIC_REJECTION',
                        'observed' => $decision,
                        'invalidates' => [],
                    ],
                ],
                'summary' => [
                    'total' => 1,
                    'passed' => $accepted ? 1 : 0,
                    'failed' => $accepted ? 0 : 1,
                    'unavailable' => 0,
                    'waived' => 0,
                    'stale' => 0,
                    'not_required' => 0,
                    'cancelled' => 0,
                ],
                'limitations' => $limitations,
                'residual_risks' => [],
                'next_action' => $accepted
                    ? 'Return the validated persistent result.'
                    : 'Repair the rejected result before resubmission.',
            ];
            $result = [
                'execution_ref' => $executionRef,
                'state_ref' => $stateRef,
                'status' => $accepted ? 'COMPLETE' : 'FAILED',
                'result_set_refs' => [$resultSetRef],
                'validation_record_ref' => $validationRef,
                'validation_decision' => $decision,
                'report_refs' => [$reportRef],
                'limitations' => $limitations,
                'next_action' => $validation['next_action'],
            ];

            $statement = $this->database->prepare(
                'INSERT INTO executions (
                    execution_ref, idempotency_key, request_json, project_ref, identity_ref,
                    permission_ref, correlation_id, state_ref, status, cancelled,
                    validation_json, result_json, created_at, updated_at
                ) VALUES (
                    :execution_ref, :idempotency_key, :request_json, :project_ref, :identity_ref,
                    :permission_ref, :correlation_id, :state_ref, :status, 0,
                    :validation_json, :result_json, :created_at, :updated_at
                )'
            );
            $statement->execute([
                'execution_ref' => $executionRef,
                'idempotency_key' => $idempotencyKey,
                'request_json' => $this->encode($request),
                'project_ref' => $projectRef,
                'identity_ref' => $identityRef,
                'permission_ref' => $permissionRef,
                'correlation_id' => $correlationId,
                'state_ref' => $stateRef,
                'status' => $accepted ? 'COMPLETE' : 'FAILED',
                'validation_json' => $this->encode($validation),
                'result_json' => $this->encode($result),
                'created_at' => $completedAt,
                'updated_at' => $completedAt,
            ]);
            $this->database->exec('COMMIT');
            $transactionStarted = false;

            return ['execution_ref' => $executionRef];
        } catch (PDOException $exception) {
            if ($transactionStarted) {
                $this->database->exec('ROLLBACK');
            }

            $existing = $this->executionForIdempotencyKey($idempotencyKey);

            if ($existing !== null) {
                return ['execution_ref' => $existing];
            }

            throw $exception;
        }
    }

    public function inspect(string $executionRef): array
    {
        $execution = $this->execution($executionRef);

        if ($execution === null) {
            return $this->unknownExecution();
        }

        if ((int) $execution['cancelled'] === 1) {
            return [
                'execution_ref' => $executionRef,
                'state_ref' => $execution['state_ref'],
                'status' => 'CANCELLED',
                'validation_record_ref' => null,
                'validation_decision' => null,
                'limitations' => [],
                'next_action' => 'No further execution is permitted.',
            ];
        }

        $validation = $this->decode($execution['validation_json']);

        return [
            'execution_ref' => $executionRef,
            'state_ref' => $execution['state_ref'],
            'status' => $execution['status'],
            'validation_record_ref' => $validation['validation_id'],
            'validation_decision' => $validation['decision'],
            'limitations' => $validation['limitations'],
            'next_action' => $validation['next_action'],
        ];
    }

    public function provideInput(
        string $executionRef,
        array $input,
        string $identityRef,
        string $permissionRef,
        string $correlationId
    ): array {
        if ($permissionRef === 'permission:denied') {
            return $this->error('UNAUTHORIZED', 'The supplied permission reference was denied.', false);
        }

        $execution = $this->execution($executionRef);

        if ($execution === null) {
            return $this->unknownExecution();
        }

        if ((int) $execution['cancelled'] === 1) {
            return $this->error('ENGINE_REJECTED', 'A cancelled execution cannot accept input.', false);
        }

        $receiptRef = 'input_receipt_' . bin2hex(random_bytes(12));
        $statement = $this->database->prepare(
            'INSERT INTO execution_inputs (
                receipt_ref, execution_ref, input_json, identity_ref, correlation_id, created_at
            ) VALUES (
                :receipt_ref, :execution_ref, :input_json, :identity_ref, :correlation_id, :created_at
            )'
        );
        $statement->execute([
            'receipt_ref' => $receiptRef,
            'execution_ref' => $executionRef,
            'input_json' => $this->encode($input),
            'identity_ref' => $identityRef,
            'correlation_id' => $correlationId,
            'created_at' => gmdate(DATE_RFC3339),
        ]);

        return ['receipt_ref' => $receiptRef];
    }

    public function cancel(
        string $executionRef,
        string $reason,
        string $identityRef,
        string $permissionRef,
        string $correlationId
    ): array {
        if ($permissionRef === 'permission:denied') {
            return $this->error('UNAUTHORIZED', 'The supplied permission reference was denied.', false);
        }

        if ($this->execution($executionRef) === null) {
            return $this->unknownExecution();
        }

        $statement = $this->database->prepare(
            'UPDATE executions
             SET cancelled = 1, status = :status, cancellation_reason = :reason, updated_at = :updated_at
             WHERE execution_ref = :execution_ref'
        );
        $statement->execute([
            'status' => 'CANCELLED',
            'reason' => $reason,
            'updated_at' => gmdate(DATE_RFC3339),
            'execution_ref' => $executionRef,
        ]);

        return [
            'receipt_ref' => 'cancel_receipt_' . $executionRef,
            'cancelled' => true,
            'reason' => $reason,
        ];
    }

    public function result(string $executionRef): array
    {
        $execution = $this->execution($executionRef);

        if ($execution === null) {
            return $this->unknownExecution();
        }

        if ((int) $execution['cancelled'] === 1) {
            return [
                'execution_ref' => $executionRef,
                'state_ref' => $execution['state_ref'],
                'status' => 'CANCELLED',
                'result_set_refs' => [],
                'validation_record_ref' => null,
                'validation_decision' => null,
                'report_refs' => [],
                'limitations' => [],
                'next_action' => 'No further execution is permitted.',
            ];
        }

        return $this->decode($execution['result_json']);
    }

    public function validation(string $executionRef): array
    {
        $execution = $this->execution($executionRef);

        return $execution === null
            ? $this->unknownExecution()
            : $this->decode($execution['validation_json']);
    }

    /**
     * Most recent executions across all identities and projects, excluding
     * request/validation/result payload columns.
     *
     * @return array<int, array{execution_ref: string, project_ref: string, status: string, cancelled: int, created_at: string, updated_at: string}>
     */
    public function recent(int $limit = 50): array
    {
        $statement = $this->database->prepare(
            'SELECT execution_ref, project_ref, status, cancelled, created_at, updated_at
             FROM executions
             ORDER BY created_at DESC
             LIMIT :limit'
        );
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    private function migrate(): void
    {
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                version INTEGER PRIMARY KEY,
                applied_at TEXT NOT NULL
            )'
        );
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS executions (
                execution_ref TEXT PRIMARY KEY,
                idempotency_key TEXT NOT NULL UNIQUE,
                request_json TEXT NOT NULL,
                project_ref TEXT NOT NULL,
                identity_ref TEXT NOT NULL,
                permission_ref TEXT NOT NULL,
                correlation_id TEXT NOT NULL,
                state_ref TEXT NOT NULL UNIQUE,
                status TEXT NOT NULL,
                cancelled INTEGER NOT NULL DEFAULT 0,
                cancellation_reason TEXT,
                validation_json TEXT NOT NULL,
                result_json TEXT NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS execution_inputs (
                receipt_ref TEXT PRIMARY KEY,
                execution_ref TEXT NOT NULL,
                input_json TEXT NOT NULL,
                identity_ref TEXT NOT NULL,
                correlation_id TEXT NOT NULL,
                created_at TEXT NOT NULL,
                FOREIGN KEY (execution_ref) REFERENCES executions(execution_ref)
            )'
        );
        $statement = $this->database->prepare(
            'INSERT OR IGNORE INTO schema_migrations (version, applied_at) VALUES (1, :applied_at)'
        );
        $statement->execute(['applied_at' => gmdate(DATE_RFC3339)]);
    }

    private function decisionFor(array $request): string
    {
        if ($this->validationDecision === null) {
            return 'ACCEPTED';
        }

        $decision = ($this->validationDecision)($request);
        $allowed = [
            'ACCEPTED',
            'ACCEPTED_WITH_LIMITATIONS',
            'REPAIR_REQUIRED',
            'CLARIFICATION_REQUIRED',
            'BLOCKED',
            'RECOVERY_REQUIRED',
            'REJECTED',
        ];

        return is_string($decision) && in_array($decision, $allowed, true)
            ? $decision
            : 'REJECTED';
    }

    private function executionForIdempotencyKey(string $idempotencyKey): ?string
    {
        $statement = $this->database->prepare(
            'SELECT execution_ref FROM executions WHERE idempotency_key = :idempotency_key'
        );
        $statement->execute(['idempotency_key' => $idempotencyKey]);
        $value = $statement->fetchColumn();
        $statement->closeCursor();

        return is_string($value) ? $value : null;
    }

    private function execution(string $executionRef): ?array
    {
        $statement = $this->database->prepare(
            'SELECT * FROM executions WHERE execution_ref = :execution_ref'
        );
        $statement->execute(['execution_ref' => $executionRef]);
        $execution = $statement->fetch();
        $statement->closeCursor();

        return is_array($execution) ? $execution : null;
    }

    private function encode(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    private function decode(string $value): array
    {
        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new PDOException('Stored Engine JSON is invalid.', 0, $exception);
        }

        if (!is_array($decoded)) {
            throw new PDOException('Stored Engine JSON must decode to an array.');
        }

        return $decoded;
    }

    private function unknownExecution(): array
    {
        return $this->error('UNKNOWN_EXECUTION', 'The execution reference is unknown.', false);
    }

    private function error(string $type, string $message, bool $retryable): array
    {
        return [
            'error' => [
                'type' => $type,
                'message' => $message,
                'retryable' => $retryable,
            ],
        ];
    }
}
