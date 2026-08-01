<?php

declare(strict_types=1);

namespace SquirrelForge\Governance;

use DateTimeImmutable;
use PDO;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;
use SquirrelForge\Reasoning\RuleEvaluator;
use SquirrelForge\Reasoning\SqliteRiskAssessor;

/**
 * Evaluates the required gates before a review or release may proceed,
 * per 23_GOVERNANCE/QUALITY-GATES.md's eight named gates: architecture
 * consistency, rule compliance, link and metadata integrity, applicable
 * tests, security and performance review proportional to risk,
 * documentation, migration readiness for breaking changes, and an
 * approved release report.
 *
 * Owns its own database, matching SqliteVersionManager's precedent:
 * "Used By: Review and Release" implies each evaluation is a real
 * governance decision worth an auditable history, the same append-only
 * shape SqlitePolicyEngine and every Sqlite*Governance component in
 * this codebase already uses.
 *
 * Three gates are genuine compositions of already-real specialists, not
 * fabricated checks: "rule compliance" calls the injected
 * RuleEvaluator::evaluate() directly -- only a literal `Passed` outcome
 * satisfies a quality gate, since a gate is meant to be stricter than a
 * general decision aggregator's Warning tolerance. "Security and
 * performance review proportional to risk" queries the injected
 * SqliteRiskAssessor::list() for open risks in the `security` and
 * `performance` categories -- a review is only *required* for a
 * category that actually has a recorded open risk in it, the literal
 * meaning of "proportional to risk" rather than always demanding both
 * reviews regardless of context. "Link and metadata integrity" performs
 * a real is_file() check against every caller-named linked file path,
 * the same real file-presence idiom ProjectLoader and DependencyAnalyzer
 * already use.
 *
 * "Migration readiness for breaking changes" is a real conditional
 * gate: it is `not_applicable` unless the caller declares
 * `breaking_change: true`, in which case a non-empty migration plan is
 * required -- never demanded for a non-breaking change.
 *
 * "Testing" (29_TESTING) has no src/ implementation, so "applicable
 * tests" reads a caller-supplied test outcome the same way
 * AutomationValidator/OptimizationValidator already treat their own
 * unbuilt reference categories. "Architecture consistency",
 * "documentation", and "an approved release report" are each
 * caller-supplied booleans for the same reason -- no component in this
 * codebase can honestly judge architectural consistency or
 * documentation completeness without fabricating a model it doesn't
 * have.
 *
 * The overall decision is a real, literal aggregation: `approved` only
 * when every gate is `passed`, `waived`, or `not_applicable`; any
 * `failed` gate blocks the release outright, regardless of how many
 * other gates passed.
 */
final class SqliteQualityGates
{
    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly ?RuleEvaluator $ruleEvaluator = null,
        private readonly ?SqliteRiskAssessor $riskAssessor = null,
        private readonly ?EventBusInterface $events = null
    ) {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS quality_gate_reviews (
                review_id TEXT PRIMARY KEY,
                request_id TEXT NOT NULL,
                decision TEXT NOT NULL,
                gates_json TEXT NOT NULL,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array{option_reference?: ?string, rules?: array<int, array<string, mixed>>, rule_context?: array<string, mixed>, security_review_status?: ?string, performance_review_status?: ?string, test_evidence?: ?string, architecture_consistency?: ?bool, linked_files?: array<int, string>, documentation_updated?: ?bool, breaking_change?: bool, migration_plan?: ?string, release_report_approved?: ?bool} $input
     * @return array{review_id: string, request_id: string, decision: string, gates: array<string, string>, error: ?string}
     */
    public function review(string $requestId, array $input = []): array
    {
        $gates = [
            'architecture_consistency' => $this->booleanGate($input['architecture_consistency'] ?? null),
            'rule_compliance' => $this->ruleComplianceGate($input),
            'link_metadata_integrity' => $this->linkIntegrityGate($input),
            'applicable_tests' => $this->testEvidenceGate($input['test_evidence'] ?? null),
            'security_review' => $this->riskProportionalReviewGate($input, 'security', $input['security_review_status'] ?? null),
            'performance_review' => $this->riskProportionalReviewGate($input, 'performance', $input['performance_review_status'] ?? null),
            'documentation' => $this->booleanGate($input['documentation_updated'] ?? null),
            'migration_readiness' => $this->migrationReadinessGate($input),
            'release_report' => $this->booleanGate($input['release_report_approved'] ?? null),
        ];

        $decision = in_array('failed', $gates, true) ? 'blocked' : 'approved';

        $reviewId = 'quality_gate_review_' . bin2hex(random_bytes(12));
        $statement = $this->database->prepare(
            'INSERT INTO quality_gate_reviews (review_id, request_id, decision, gates_json, created_at)
             VALUES (:review_id, :request_id, :decision, :gates_json, :created_at)'
        );
        $statement->execute([
            'review_id' => $reviewId,
            'request_id' => $requestId,
            'decision' => $decision,
            'gates_json' => json_encode($gates, JSON_THROW_ON_ERROR),
            'created_at' => gmdate(DATE_RFC3339),
        ]);

        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'quality_gates.' . $decision,
            new DateTimeImmutable(),
            self::class,
            ['review_id' => $reviewId, 'request_id' => $requestId]
        ));

        return ['review_id' => $reviewId, 'request_id' => $requestId, 'decision' => $decision, 'gates' => $gates, 'error' => null];
    }

    /**
     * @return array<int, array{review_id: string, request_id: string, decision: string, gates: array<string, string>, created_at: string}>
     */
    public function history(string $requestId): array
    {
        $statement = $this->database->prepare('SELECT * FROM quality_gate_reviews WHERE request_id = :request_id ORDER BY rowid ASC');
        $statement->execute(['request_id' => $requestId]);

        return array_map(static fn(array $row): array => [
            'review_id' => $row['review_id'],
            'request_id' => $row['request_id'],
            'decision' => $row['decision'],
            'gates' => json_decode((string) $row['gates_json'], true, flags: JSON_THROW_ON_ERROR),
            'created_at' => $row['created_at'],
        ], $statement->fetchAll());
    }

    private function booleanGate(?bool $value): string
    {
        return match ($value) {
            true => 'passed',
            false => 'failed',
            null => 'not_applicable',
        };
    }

    /**
     * @param array{rules?: array<int, array<string, mixed>>, rule_context?: array<string, mixed>} $input
     */
    private function ruleComplianceGate(array $input): string
    {
        if ($this->ruleEvaluator === null || !isset($input['rules'])) {
            return 'not_applicable';
        }

        $result = $this->ruleEvaluator->evaluate($input['rules'], $input['rule_context'] ?? []);

        return $result['outcome'] === 'Passed' ? 'passed' : 'failed';
    }

    /**
     * @param array{linked_files?: array<int, string>} $input
     */
    private function linkIntegrityGate(array $input): string
    {
        if (!array_key_exists('linked_files', $input)) {
            return 'not_applicable';
        }

        foreach ($input['linked_files'] as $path) {
            if (!is_file($path)) {
                return 'failed';
            }
        }

        return 'passed';
    }

    private function testEvidenceGate(?string $status): string
    {
        return match ($status) {
            'passed' => 'passed',
            'failed' => 'failed',
            'waived' => 'waived',
            default => 'not_applicable',
        };
    }

    /**
     * "Proportional to risk": a review is only required for a category
     * that actually has a recorded open risk in it.
     *
     * @param array{option_reference?: ?string} $input
     */
    private function riskProportionalReviewGate(array $input, string $category, ?string $reviewStatus): string
    {
        if ($this->riskAssessor === null || !isset($input['option_reference'])) {
            return 'not_applicable';
        }

        $openRisks = $this->riskAssessor->list(['option_reference' => $input['option_reference'], 'category' => $category, 'status' => 'open']);

        if ($openRisks === []) {
            return 'not_applicable';
        }

        return match ($reviewStatus) {
            'passed' => 'passed',
            'waived' => 'waived',
            default => 'failed',
        };
    }

    /**
     * @param array{breaking_change?: bool, migration_plan?: ?string} $input
     */
    private function migrationReadinessGate(array $input): string
    {
        if (($input['breaking_change'] ?? false) !== true) {
            return 'not_applicable';
        }

        return (($input['migration_plan'] ?? '') !== '') ? 'passed' : 'failed';
    }
}
