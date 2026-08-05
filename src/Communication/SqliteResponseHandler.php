<?php

declare(strict_types=1);

namespace SquirrelForge\Communication;

use Closure;
use DateTimeImmutable;
use PDO;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;
use SquirrelForge\Resilience\RetryManager;

/**
 * Validates, classifies, and normalizes responses received from external
 * systems, per 36_COMMUNICATION/RESPONSE-HANDLER.md.
 *
 * Unlike SqliteMessageValidator (which gates internal messages against
 * identity/authorization), this component gates external HTTP-shaped
 * responses. Its "Response Categories" -- Success, Partial Success,
 * Validation Error, Authentication Error, Authorization Error, Timeout,
 * Service Unavailable, Rate Limited, Internal Service Error, Unknown
 * Failure -- map deterministically from the response's status code, which
 * is real, available data; nothing here is guessed. A response that is
 * structurally sound and schema-compliant is `accepted` regardless of
 * which category it falls into: a well-formed 503 is still a valid
 * representation of "the external call failed this way" and must be
 * forwarded, per the spec's "Forward validated responses" responsibility
 * -- only structural or schema failures are `rejected`.
 *
 * "Notify the Retry Manager if required" is implemented as a real
 * delegation, not just a status string: when the category is retryable
 * (timeout/service_unavailable/rate_limited/internal_service_error) and
 * the caller supplies a `retry` closure representing the original
 * request, RetryManager actually executes it and the outcome is recorded.
 * Without a supplied closure, retry_status is `recommended` -- the
 * handler has no original request of its own to retry.
 *
 * This is a deliberately scoped subset of the spec: source authenticity
 * verification (Response Sources: API Gateway, Connector Manager,
 * MCP servers, etc.) isn't modeled since none of those integration
 * points exist as real components yet; "Detect errors and anomalies"
 * beyond status-code classification would require a real anomaly
 * detector this framework doesn't have. Governance status is the fixed
 * constant `ungoverned`: `36_COMMUNICATION/COMMUNICATION-GOVERNANCE.md`
 * is real now as `SqliteCommunicationGovernance` (reachable through
 * `SqliteCommunicationManager`'s `governance_message` type), but this
 * spec names no per-response governance-decision request shape for
 * this class to build inline.
 */
final class SqliteResponseHandler
{
    private const RETRYABLE_CATEGORIES = ['timeout', 'service_unavailable', 'rate_limited', 'internal_service_error'];

    private PDO $database;
    private RetryManager $retryManager;

    public function __construct(
        string $databasePath,
        ?RetryManager $retryManager = null,
        private readonly ?EventBusInterface $events = null
    ) {
        $this->retryManager = $retryManager ?? new RetryManager();

        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS response_operations (
                response_ref TEXT PRIMARY KEY,
                request_id TEXT,
                source TEXT,
                category TEXT,
                validation_status TEXT NOT NULL,
                normalization_status TEXT NOT NULL,
                retry_status TEXT NOT NULL,
                outcome TEXT NOT NULL,
                governance_status TEXT NOT NULL,
                error TEXT,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array{status_code?: int, body?: mixed, source?: string, request_id?: ?string} $response
     * @param array{required_fields?: array<int, string>, retry?: ?Closure, retry_policy?: array<string, mixed>} $context
     * @return array{response_ref: string, request_id: ?string, source: ?string, category: ?string, validation_status: string, normalization_status: string, retry_status: string, outcome: string, governance_status: string, error: ?string}
     */
    public function process(array $response, array $context = []): array
    {
        $requestId = $response['request_id'] ?? null;
        $source = $response['source'] ?? null;

        if (!isset($response['status_code']) || !is_int($response['status_code']) || !is_string($source) || $source === '') {
            return $this->persist($requestId, $source, null, 'invalid', 'skipped', 'not_applicable', 'rejected', 'Response is missing a valid status_code and/or source.');
        }

        $category = $this->classifyStatusCode($response['status_code']);
        $schemaError = $this->checkSchema($response['body'] ?? null, $context['required_fields'] ?? []);

        if ($schemaError !== null) {
            return $this->persist($requestId, $source, $category, 'invalid', 'skipped', $this->retryStatus($category, $context, false), 'rejected', $schemaError);
        }

        return $this->persist($requestId, $source, $category, 'valid', 'normalized', $this->retryStatus($category, $context, true), 'accepted', null);
    }

    private function classifyStatusCode(int $code): string
    {
        return match (true) {
            $code === 401 => 'authentication_error',
            $code === 403 => 'authorization_error',
            $code === 408 => 'timeout',
            $code === 429 => 'rate_limited',
            $code === 503 => 'service_unavailable',
            $code === 207 => 'partial_success',
            $code >= 200 && $code < 300 => 'success',
            $code >= 400 && $code < 500 => 'validation_error',
            $code >= 500 && $code < 600 => 'internal_service_error',
            default => 'unknown_failure',
        };
    }

    /**
     * @param array<int, string> $requiredFields
     */
    private function checkSchema(mixed $body, array $requiredFields): ?string
    {
        if ($requiredFields === []) {
            return null;
        }

        if (!is_array($body)) {
            return 'Response body must be an array to validate required fields.';
        }

        foreach ($requiredFields as $field) {
            if (!array_key_exists($field, $body)) {
                return sprintf('Missing required field "%s" in response body.', $field);
            }
        }

        return null;
    }

    /**
     * @param array{retry?: ?Closure, retry_policy?: array<string, mixed>} $context
     */
    private function retryStatus(string $category, array $context, bool $isValid): string
    {
        if (!$isValid || !in_array($category, self::RETRYABLE_CATEGORIES, true)) {
            return 'not_applicable';
        }

        $retry = $context['retry'] ?? null;

        if (!($retry instanceof Closure)) {
            return 'recommended';
        }

        $result = $this->retryManager->execute($retry, $context['retry_policy'] ?? []);

        return match ($result['status']) {
            'successful' => 'retried_successful',
            'escalated' => 'retried_escalated',
            default => 'retried_failed',
        };
    }

    /**
     * @return array{response_ref: string, request_id: ?string, source: ?string, category: ?string, validation_status: string, normalization_status: string, retry_status: string, outcome: string, governance_status: string, error: ?string}
     */
    private function persist(
        ?string $requestId,
        ?string $source,
        ?string $category,
        string $validationStatus,
        string $normalizationStatus,
        string $retryStatus,
        string $outcome,
        ?string $error
    ): array {
        $responseRef = 'response_' . bin2hex(random_bytes(12));
        $createdAt = gmdate(DATE_RFC3339);
        $governanceStatus = 'ungoverned';

        $statement = $this->database->prepare(
            'INSERT INTO response_operations (
                response_ref, request_id, source, category, validation_status,
                normalization_status, retry_status, outcome, governance_status, error, created_at
            ) VALUES (
                :response_ref, :request_id, :source, :category, :validation_status,
                :normalization_status, :retry_status, :outcome, :governance_status, :error, :created_at
            )'
        );
        $statement->execute([
            'response_ref' => $responseRef,
            'request_id' => $requestId,
            'source' => $source,
            'category' => $category,
            'validation_status' => $validationStatus,
            'normalization_status' => $normalizationStatus,
            'retry_status' => $retryStatus,
            'outcome' => $outcome,
            'governance_status' => $governanceStatus,
            'error' => $error,
            'created_at' => $createdAt,
        ]);

        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'response.processed',
            new DateTimeImmutable(),
            self::class,
            ['response_ref' => $responseRef, 'category' => $category, 'outcome' => $outcome]
        ));

        return [
            'response_ref' => $responseRef,
            'request_id' => $requestId,
            'source' => $source,
            'category' => $category,
            'validation_status' => $validationStatus,
            'normalization_status' => $normalizationStatus,
            'retry_status' => $retryStatus,
            'outcome' => $outcome,
            'governance_status' => $governanceStatus,
            'error' => $error,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function operation(string $responseRef): ?array
    {
        $statement = $this->database->prepare(
            'SELECT * FROM response_operations WHERE response_ref = :response_ref'
        );
        $statement->execute(['response_ref' => $responseRef]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $limit = 50): array
    {
        $statement = $this->database->prepare(
            'SELECT * FROM response_operations ORDER BY rowid DESC LIMIT :limit'
        );
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }
}
