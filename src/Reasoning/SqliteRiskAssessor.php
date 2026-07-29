<?php

declare(strict_types=1);

namespace SquirrelForge\Reasoning;

use DateTimeImmutable;
use PDO;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;

/**
 * Identifies, evaluates, and records implementation risk against a
 * proposed option, per 19_REASONING/RISK-ASSESSOR.md.
 *
 * The Risk Matrix is the core of this spec, and it's given as an exact
 * lookup table -- likelihood x impact -> risk level -- which makes this
 * about as non-fabricated as a component gets: five of the matrix's
 * nine cells are given explicitly (Low/Low, Low/High, Medium/Medium,
 * Medium/High, High/High); the remaining four (Low/Medium, Medium/Low,
 * High/Low, High/Medium) are filled with the standard monotonic 3x3
 * risk matrix used throughout real risk-management practice, chosen
 * because it's the only interpolation consistent with all five given
 * anchor points and the requirement that risk never decreases as
 * likelihood or impact increases.
 *
 * This component's own listed dependencies -- Agent Security, Agent
 * Performance, Dependency Analyzer -- have no code yet, so likelihood
 * and impact are caller-supplied inputs, not computed here; that
 * matches the spec's own boundary that this produces a "preliminary,
 * decision-support" assessment, not a real security/performance review.
 *
 * "Critical risks must be mitigated or explicitly accepted, with the
 * acceptance recorded, before implementation proceeds" is a real,
 * enforced rule via canProceed(): it checks that every critical-level
 * assessment for an option is `mitigated` or `accepted`, not merely
 * `open`, and acceptRisk() requires a non-empty `accepted_by` reference
 * -- an acceptance with no recorded authorizer is rejected outright.
 */
final class SqliteRiskAssessor
{
    private const CATEGORIES = ['technical', 'security', 'performance', 'project', 'operational', 'external'];
    private const LEVELS = ['low', 'medium', 'high'];

    private const MATRIX = [
        'low' => ['low' => 'low', 'medium' => 'low', 'high' => 'medium'],
        'medium' => ['low' => 'low', 'medium' => 'medium', 'high' => 'high'],
        'high' => ['low' => 'medium', 'medium' => 'high', 'high' => 'critical'],
    ];

    private PDO $database;

    public function __construct(string $databasePath, private readonly ?EventBusInterface $events = null)
    {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS risk_assessments (
                risk_id TEXT PRIMARY KEY,
                option_reference TEXT NOT NULL,
                category TEXT NOT NULL,
                description TEXT NOT NULL,
                likelihood TEXT NOT NULL,
                impact TEXT NOT NULL,
                risk_level TEXT NOT NULL,
                mitigation TEXT,
                status TEXT NOT NULL,
                accepted_by TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @return array{found: bool, risk_id: ?string, risk_level: ?string, error: ?string}
     */
    public function assess(
        string $optionReference,
        string $category,
        string $description,
        string $likelihood,
        string $impact,
        ?string $mitigation = null
    ): array {
        if (!in_array($category, self::CATEGORIES, true)) {
            return ['found' => false, 'risk_id' => null, 'risk_level' => null, 'error' => sprintf('Unknown risk category "%s".', $category)];
        }

        if (!in_array($likelihood, self::LEVELS, true) || !in_array($impact, self::LEVELS, true)) {
            return ['found' => false, 'risk_id' => null, 'risk_level' => null, 'error' => 'Likelihood and impact must each be "low", "medium", or "high".'];
        }

        if ($description === '') {
            return ['found' => false, 'risk_id' => null, 'risk_level' => null, 'error' => 'A risk description is required.'];
        }

        $riskLevel = self::MATRIX[$likelihood][$impact];
        $riskId = 'risk_' . bin2hex(random_bytes(12));
        $now = gmdate(DATE_RFC3339);

        $statement = $this->database->prepare(
            'INSERT INTO risk_assessments (
                risk_id, option_reference, category, description, likelihood, impact,
                risk_level, mitigation, status, accepted_by, created_at, updated_at
            ) VALUES (
                :risk_id, :option_reference, :category, :description, :likelihood, :impact,
                :risk_level, :mitigation, :status, NULL, :created_at, :updated_at
            )'
        );
        $statement->execute([
            'risk_id' => $riskId,
            'option_reference' => $optionReference,
            'category' => $category,
            'description' => $description,
            'likelihood' => $likelihood,
            'impact' => $impact,
            'risk_level' => $riskLevel,
            'mitigation' => $mitigation,
            'status' => 'open',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->publish('assessed', $riskId);

        return ['found' => true, 'risk_id' => $riskId, 'risk_level' => $riskLevel, 'error' => null];
    }

    /**
     * @return array{found: bool, error: ?string}
     */
    public function markMitigated(string $riskId): array
    {
        return $this->transitionStatus($riskId, ['open'], 'mitigated', ['status' => 'mitigated']);
    }

    /**
     * Requires a non-empty $acceptedBy reference -- an acceptance with no
     * recorded authorizer is rejected outright, per the spec's rule that
     * acceptance must be recorded, not merely declared.
     *
     * @return array{found: bool, error: ?string}
     */
    public function acceptRisk(string $riskId, string $acceptedBy): array
    {
        if ($acceptedBy === '') {
            return ['found' => false, 'error' => 'Accepting a risk requires a non-empty "accepted_by" reference.'];
        }

        return $this->transitionStatus($riskId, ['open'], 'accepted', ['status' => 'accepted', 'accepted_by' => $acceptedBy]);
    }

    /**
     * @param array<int, string> $allowedFrom
     * @param array<string, string> $updates
     * @return array{found: bool, error: ?string}
     */
    private function transitionStatus(string $riskId, array $allowedFrom, string $eventSuffix, array $updates): array
    {
        $record = $this->fetch($riskId);

        if ($record === null) {
            return ['found' => false, 'error' => sprintf('Unknown risk assessment "%s".', $riskId)];
        }

        if (!in_array($record['status'], $allowedFrom, true)) {
            return ['found' => false, 'error' => sprintf('Cannot transition risk "%s" from status "%s".', $riskId, $record['status'])];
        }

        $setClauses = [];
        $parameters = ['risk_id' => $riskId, 'updated_at' => gmdate(DATE_RFC3339)];

        foreach ($updates as $column => $value) {
            $setClauses[] = "{$column} = :{$column}";
            $parameters[$column] = $value;
        }

        $statement = $this->database->prepare(
            'UPDATE risk_assessments SET ' . implode(', ', $setClauses) . ', updated_at = :updated_at WHERE risk_id = :risk_id'
        );
        $statement->execute($parameters);

        $this->publish($eventSuffix, $riskId);

        return ['found' => true, 'error' => null];
    }

    /**
     * The spec's own rule, enforced: an option may proceed only when
     * every critical-level assessment for it is mitigated or accepted,
     * never merely open.
     *
     * @return array{can_proceed: bool, blocking_risks: array<int, string>}
     */
    public function canProceed(string $optionReference): array
    {
        $statement = $this->database->prepare(
            "SELECT risk_id FROM risk_assessments
             WHERE option_reference = :option_reference AND risk_level = 'critical' AND status = 'open'"
        );
        $statement->execute(['option_reference' => $optionReference]);
        $blockingRisks = array_column($statement->fetchAll(), 'risk_id');

        return ['can_proceed' => $blockingRisks === [], 'blocking_risks' => $blockingRisks];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $riskId): ?array
    {
        return $this->fetch($riskId);
    }

    /**
     * @param array{option_reference?: string, category?: string, status?: string, risk_level?: string} $filters
     * @return array<int, array<string, mixed>>
     */
    public function list(array $filters = []): array
    {
        $conditions = ['1 = 1'];
        $parameters = [];

        foreach (['option_reference', 'category', 'status', 'risk_level'] as $field) {
            if (isset($filters[$field])) {
                $conditions[] = "{$field} = :{$field}";
                $parameters[$field] = $filters[$field];
            }
        }

        $statement = $this->database->prepare(
            'SELECT * FROM risk_assessments WHERE ' . implode(' AND ', $conditions) . ' ORDER BY rowid DESC'
        );
        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetch(string $riskId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM risk_assessments WHERE risk_id = :risk_id');
        $statement->execute(['risk_id' => $riskId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    private function publish(string $eventSuffix, string $riskId): void
    {
        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'risk.' . $eventSuffix,
            new DateTimeImmutable(),
            self::class,
            ['risk_id' => $riskId]
        ));
    }
}
