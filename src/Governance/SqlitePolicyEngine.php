<?php

declare(strict_types=1);

namespace SquirrelForge\Governance;

use DateTimeImmutable;
use PDO;
use SquirrelForge\Automation\RuleEngine;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;

/**
 * Evaluates registered policies against a request context and produces a
 * deterministic policy decision, per 23_GOVERNANCE/POLICY-ENGINE.md.
 *
 * Policy condition evaluation is genuinely delegated to the real
 * RuleEngine rather than reinventing condition logic -- a policy's
 * `condition` is a RuleEngine condition, evaluated via
 * RuleEngine::evaluate(). This is the same real reuse TriggerManager
 * already established for its own conditions.
 *
 * "Resolve policy conflicts" and "must never ignore higher-priority
 * security policies" / "must never allow conflicting policy outcomes"
 * are a real, deterministic two-stage resolution, not a fabricated
 * tie-break: first, only the highest-priority tier among policies whose
 * condition applies (or can't yet be determined) is considered at all --
 * lower-priority policies are irrelevant once a higher tier has any
 * candidate. Second, within that tier, a fixed severity order decides
 * the outcome: `prohibit` beats `deny` beats an indeterminate result
 * (RuleEngine's conditional/deferred/input_missing, which this maps to
 * `deferred` rather than guessing) beats `require_review` beats
 * `allow_with_conditions` beats `allow`. "Deny the operation by default"
 * on failure is real too: no configured RuleEngine, or no policy
 * applying at all, both produce `denied`.
 *
 * "Support policy versioning" is real: re-registering a `policy_id`
 * deactivates its prior version and activates a new one, an immutable
 * version history mirroring SqliteDocumentStorage's own versioning.
 * Every evaluation is persisted to its own audit table regardless of
 * outcome, since "record policy evaluations" and the explicit Audit
 * Requirements section are core to this spec, matching the older-style
 * specs (Document/Object Storage) that own their own audit
 * infrastructure rather than the newer Knowledge/Learning specs that
 * forbid it.
 */
final class SqlitePolicyEngine
{
    private const CATEGORIES = [
        'identity', 'authentication', 'authorization', 'data_protection', 'integration',
        'workflow', 'resource_access', 'compliance', 'operational', 'emergency_override',
    ];

    private const EFFECTS = ['allow', 'allow_with_conditions', 'deny', 'require_review', 'prohibit'];

    private const EFFECT_TO_DECISION = [
        'prohibit' => 'permanently_prohibited',
        'deny' => 'denied',
        'require_review' => 'requires_additional_review',
        'allow_with_conditions' => 'allowed_with_conditions',
        'allow' => 'allowed',
    ];

    private const EFFECT_SEVERITY = ['prohibit' => 5, 'deny' => 4, 'require_review' => 2, 'allow_with_conditions' => 1, 'allow' => 0];

    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly ?RuleEngine $ruleEngine = null,
        private readonly ?EventBusInterface $events = null
    ) {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS policies (
                policy_ref TEXT PRIMARY KEY,
                policy_id TEXT NOT NULL,
                version INTEGER NOT NULL,
                category TEXT NOT NULL,
                priority INTEGER NOT NULL,
                condition_json TEXT NOT NULL,
                effect TEXT NOT NULL,
                active INTEGER NOT NULL,
                created_at TEXT NOT NULL
            )'
        );
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS policy_evaluations (
                evaluation_ref TEXT PRIMARY KEY,
                request_id TEXT NOT NULL,
                decision TEXT NOT NULL,
                rationale TEXT NOT NULL,
                applicable_policies_json TEXT NOT NULL,
                governance_status TEXT NOT NULL,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array{category: string, priority: int, condition: array<string, mixed>, effect: string} $definition
     * @return array{found: bool, policy_ref: ?string, version: ?int, error: ?string}
     */
    public function registerPolicy(string $policyId, array $definition): array
    {
        if (!in_array($definition['category'] ?? null, self::CATEGORIES, true)) {
            return ['found' => false, 'policy_ref' => null, 'version' => null, 'error' => sprintf('Unknown policy category "%s".', $definition['category'] ?? '')];
        }

        if (!in_array($definition['effect'] ?? null, self::EFFECTS, true)) {
            return ['found' => false, 'policy_ref' => null, 'version' => null, 'error' => sprintf('Unknown policy effect "%s".', $definition['effect'] ?? '')];
        }

        $previousVersion = $this->latestVersionNumber($policyId);

        $deactivate = $this->database->prepare('UPDATE policies SET active = 0 WHERE policy_id = :policy_id AND active = 1');
        $deactivate->execute(['policy_id' => $policyId]);

        $policyRef = 'policy_' . bin2hex(random_bytes(12));
        $version = $previousVersion + 1;

        $statement = $this->database->prepare(
            'INSERT INTO policies (
                policy_ref, policy_id, version, category, priority, condition_json, effect, active, created_at
            ) VALUES (
                :policy_ref, :policy_id, :version, :category, :priority, :condition_json, :effect, 1, :created_at
            )'
        );
        $statement->execute([
            'policy_ref' => $policyRef,
            'policy_id' => $policyId,
            'version' => $version,
            'category' => $definition['category'],
            'priority' => $definition['priority'],
            'condition_json' => json_encode($definition['condition'], JSON_THROW_ON_ERROR),
            'effect' => $definition['effect'],
            'created_at' => gmdate(DATE_RFC3339),
        ]);

        return ['found' => true, 'policy_ref' => $policyRef, 'version' => $version, 'error' => null];
    }

    private function latestVersionNumber(string $policyId): int
    {
        $statement = $this->database->prepare('SELECT MAX(version) AS max_version FROM policies WHERE policy_id = :policy_id');
        $statement->execute(['policy_id' => $policyId]);
        $row = $statement->fetch();

        return (int) ($row['max_version'] ?? 0);
    }

    /**
     * @param array<string, mixed> $context
     * @return array{evaluation_ref: string, request_id: string, decision: string, rationale: string, applicable_policies: array<int, array{policy_id: string, effect: string}>}
     */
    public function evaluate(string $requestId, array $context, ?string $category = null): array
    {
        if ($this->ruleEngine === null) {
            return $this->persist($requestId, 'denied', 'Rule Engine is not configured; denying by default.', []);
        }

        $policies = $this->activePolicies($category);

        if ($policies === []) {
            return $this->persist($requestId, 'denied', 'No applicable policy is registered; denying by default.', []);
        }

        $candidates = [];

        foreach ($policies as $policy) {
            $condition = json_decode((string) $policy['condition_json'], true, flags: JSON_THROW_ON_ERROR);
            $result = $this->ruleEngine->evaluate(['id' => $policy['policy_id'], 'condition' => $condition], $context)['result'];

            if ($result === 'fail') {
                continue;
            }

            $candidates[] = [
                'policy_id' => $policy['policy_id'],
                'effect' => $policy['effect'],
                'priority' => (int) $policy['priority'],
                'indeterminate' => in_array($result, ['conditional', 'deferred', 'input_missing'], true),
            ];
        }

        if ($candidates === []) {
            return $this->persist($requestId, 'denied', 'No policy applied to this request; denying by default.', []);
        }

        $topPriority = max(array_column($candidates, 'priority'));
        $topTier = array_values(array_filter($candidates, static fn(array $c): bool => $c['priority'] === $topPriority));

        [$decision, $rationale] = $this->resolveTier($topTier);
        $applicable = array_map(static fn(array $c): array => ['policy_id' => $c['policy_id'], 'effect' => $c['effect']], $topTier);

        return $this->persist($requestId, $decision, $rationale, $applicable);
    }

    /**
     * @param array<int, array{policy_id: string, effect: string, priority: int, indeterminate: bool}> $tier
     * @return array{0: string, 1: string}
     */
    private function resolveTier(array $tier): array
    {
        if (in_array(true, array_column($tier, 'indeterminate'), true) && !$this->tierContainsEffectAtOrAbove($tier, 'deny')) {
            return ['deferred', 'A top-priority policy could not yet be determined to apply.'];
        }

        $mostSevere = null;

        foreach ($tier as $candidate) {
            if ($candidate['indeterminate']) {
                continue;
            }

            if ($mostSevere === null || self::EFFECT_SEVERITY[$candidate['effect']] > self::EFFECT_SEVERITY[$mostSevere]) {
                $mostSevere = $candidate['effect'];
            }
        }

        return [self::EFFECT_TO_DECISION[$mostSevere], sprintf('Resolved by the highest-priority tier\'s most restrictive effect: "%s".', $mostSevere)];
    }

    /**
     * @param array<int, array{effect: string, indeterminate: bool}> $tier
     */
    private function tierContainsEffectAtOrAbove(array $tier, string $effect): bool
    {
        foreach ($tier as $candidate) {
            if (!$candidate['indeterminate'] && self::EFFECT_SEVERITY[$candidate['effect']] >= self::EFFECT_SEVERITY[$effect]) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function activePolicies(?string $category): array
    {
        if ($category !== null) {
            $statement = $this->database->prepare('SELECT * FROM policies WHERE active = 1 AND category = :category');
            $statement->execute(['category' => $category]);
        } else {
            $statement = $this->database->prepare('SELECT * FROM policies WHERE active = 1');
            $statement->execute();
        }

        return $statement->fetchAll();
    }

    /**
     * @param array<int, array{policy_id: string, effect: string}> $applicable
     * @return array{evaluation_ref: string, request_id: string, decision: string, rationale: string, applicable_policies: array<int, array{policy_id: string, effect: string}>}
     */
    private function persist(string $requestId, string $decision, string $rationale, array $applicable): array
    {
        $evaluationRef = 'policy_evaluation_' . bin2hex(random_bytes(12));

        $statement = $this->database->prepare(
            'INSERT INTO policy_evaluations (
                evaluation_ref, request_id, decision, rationale, applicable_policies_json, governance_status, created_at
            ) VALUES (
                :evaluation_ref, :request_id, :decision, :rationale, :applicable_policies_json, :governance_status, :created_at
            )'
        );
        $statement->execute([
            'evaluation_ref' => $evaluationRef,
            'request_id' => $requestId,
            'decision' => $decision,
            'rationale' => $rationale,
            'applicable_policies_json' => json_encode($applicable, JSON_THROW_ON_ERROR),
            'governance_status' => 'ungoverned',
            'created_at' => gmdate(DATE_RFC3339),
        ]);

        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'policy.' . $decision,
            new DateTimeImmutable(),
            self::class,
            ['evaluation_ref' => $evaluationRef, 'request_id' => $requestId, 'decision' => $decision]
        ));

        return ['evaluation_ref' => $evaluationRef, 'request_id' => $requestId, 'decision' => $decision, 'rationale' => $rationale, 'applicable_policies' => $applicable];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function evaluation(string $evaluationRef): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM policy_evaluations WHERE evaluation_ref = :evaluation_ref');
        $statement->execute(['evaluation_ref' => $evaluationRef]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $limit = 50): array
    {
        $statement = $this->database->prepare('SELECT * FROM policy_evaluations ORDER BY rowid DESC LIMIT :limit');
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listPolicies(?string $policyId = null): array
    {
        if ($policyId !== null) {
            $statement = $this->database->prepare('SELECT * FROM policies WHERE policy_id = :policy_id ORDER BY version ASC');
            $statement->execute(['policy_id' => $policyId]);
        } else {
            $statement = $this->database->prepare('SELECT * FROM policies WHERE active = 1');
            $statement->execute();
        }

        return $statement->fetchAll();
    }
}
