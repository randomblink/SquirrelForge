<?php

declare(strict_types=1);

namespace SquirrelForge\Security;

use DateTimeImmutable;
use PDO;
use SquirrelForge\Contracts\AuthenticationManagerInterface;
use SquirrelForge\Contracts\AuthorizationManagerInterface;
use SquirrelForge\Contracts\IdentityManagerInterface;
use SquirrelForge\Governance\SqlitePolicyEngine;
use SquirrelForge\RuntimeConfig\SqliteSecretsManager;

/**
 * The central coordinator for Security Layer activity, per
 * 24_SECURITY/SECURITY-MANAGER.md: "coordinates security workflows
 * only... routes them to the responsible security component...
 * without independently deciding severity, policy compliance,
 * authorization, threat classification, compliance status, or
 * remediation outcome."
 *
 * This spec and `24_SECURITY/SECURITY-MONITOR.md` list each other as
 * dependencies -- the same genuine circular reference already resolved
 * in that class's own docblock. Security Monitor was built first; this
 * class composes it directly for the real "unavailable vs. available"
 * routing signal, closing the cycle.
 *
 * Given how absolute this spec's own Safety Rules are ("must never...
 * decide event severity except as routing metadata supplied by the
 * owning component", "must never treat routing metadata as
 * authoritative... state"), route() deliberately never calls a
 * mutating or decision-making method on any specialist it routes
 * to -- doing so would mean this class either duplicating that
 * specialist's own authority or silently making a decision on its
 * behalf. Instead, route() determines which real specialist a given
 * `item_type` belongs to and records that routing decision --
 * genuinely checking whether the corresponding specialist is actually
 * configured (`routed` vs `unavailable`, a real signal, not a
 * fabricated always-success) -- while every piece of owner-supplied
 * metadata (severity, category, status, policy reference, correlation
 * ID) is preserved verbatim rather than reinterpreted.
 *
 * `24_SECURITY/THREAT-DETECTOR.md` and `24_SECURITY/INCIDENT-MANAGER.md`
 * item types route the same as every other specialist: `routed` when a
 * `SqliteThreatDetector` or `SqliteIncidentManager` is configured,
 * `unavailable` otherwise -- the item and its correlation ID are always
 * recorded either way.
 *
 * Owns its own database (`Sqlite` prefix): the Coordination Record
 * fields this spec names are recorded for every route() call
 * unconditionally, including unavailable and rejected routings.
 */
final class SqliteSecurityManager
{
    private const ITEM_TYPES = [
        'identity_event' => 'identity_manager',
        'authentication_event' => 'authentication_manager',
        'authorization_event' => 'authorization_manager',
        'secret_reference' => 'secrets_manager',
        'encryption_reference' => 'encryption_manager',
        'policy_reference' => 'policy_engine',
        'threat_reference' => 'threat_detector',
        'incident_reference' => 'incident_manager',
        'compliance_reference' => 'compliance_manager',
        'vulnerability_reference' => 'vulnerability_manager',
        'governance_reference' => 'security_governance',
        'monitoring_reference' => 'security_monitor',
    ];

    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly ?IdentityManagerInterface $identityManager = null,
        private readonly ?AuthenticationManagerInterface $authenticationManager = null,
        private readonly ?AuthorizationManagerInterface $authorizationManager = null,
        private readonly ?SqliteSecretsManager $secretsManager = null,
        private readonly ?SqliteEncryptionManager $encryptionManager = null,
        private readonly ?SqlitePolicyEngine $policyEngine = null,
        private readonly ?SqliteComplianceManager $complianceManager = null,
        private readonly ?SqliteVulnerabilityManager $vulnerabilityManager = null,
        private readonly ?SqliteSecurityGovernance $securityGovernance = null,
        private readonly ?SqliteSecurityMonitor $securityMonitor = null,
        private readonly ?SqliteThreatDetector $threatDetector = null,
        private readonly ?SqliteIncidentManager $incidentManager = null
    ) {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS security_coordination_records (
                operation_id TEXT PRIMARY KEY,
                source_reference TEXT NOT NULL,
                item_type TEXT NOT NULL,
                source_component TEXT,
                target_component TEXT,
                severity TEXT,
                category TEXT,
                status TEXT,
                policy_reference TEXT,
                correlation_id TEXT,
                routing_outcome TEXT NOT NULL,
                error TEXT,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array{source_reference?: string, item_type?: string, source_component?: ?string, severity?: ?string, category?: ?string, status?: ?string, policy_reference?: ?string, correlation_id?: ?string} $item
     * @return array{operation_id: string, target_component: ?string, routing_outcome: string, error: ?string}
     */
    public function route(array $item): array
    {
        $sourceReference = $item['source_reference'] ?? null;
        $itemType = $item['item_type'] ?? null;

        if ($sourceReference === null || $itemType === null) {
            return $this->finish(null, $itemType ?? 'unknown', $item, 'rejected', 'source_reference and item_type are both required.');
        }

        if (!isset(self::ITEM_TYPES[$itemType])) {
            return $this->finish(null, $itemType, $item, 'rejected', sprintf('"%s" is not a recognized security item type.', $itemType));
        }

        $targetComponent = self::ITEM_TYPES[$itemType];

        if (!$this->isConfigured($targetComponent)) {
            return $this->finish($targetComponent, $itemType, $item, 'unavailable', sprintf('"%s" has no configured specialist to route to.', $targetComponent));
        }

        return $this->finish($targetComponent, $itemType, $item, 'routed', null);
    }

    private function isConfigured(string $targetComponent): bool
    {
        return match ($targetComponent) {
            'identity_manager' => $this->identityManager !== null,
            'authentication_manager' => $this->authenticationManager !== null,
            'authorization_manager' => $this->authorizationManager !== null,
            'secrets_manager' => $this->secretsManager !== null,
            'encryption_manager' => $this->encryptionManager !== null,
            'policy_engine' => $this->policyEngine !== null,
            'compliance_manager' => $this->complianceManager !== null,
            'vulnerability_manager' => $this->vulnerabilityManager !== null,
            'security_governance' => $this->securityGovernance !== null,
            'security_monitor' => $this->securityMonitor !== null,
            'threat_detector' => $this->threatDetector !== null,
            'incident_manager' => $this->incidentManager !== null,
            default => false,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $operationId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM security_coordination_records WHERE operation_id = :id');
        $statement->execute(['id' => $operationId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $limit = 50): array
    {
        $statement = $this->database->prepare('SELECT * FROM security_coordination_records ORDER BY rowid DESC LIMIT :limit');
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    /**
     * @param array<string, mixed> $item
     * @return array{operation_id: string, target_component: ?string, routing_outcome: string, error: ?string}
     */
    private function finish(?string $targetComponent, string $itemType, array $item, string $routingOutcome, ?string $error): array
    {
        $operationId = 'security_coord_' . bin2hex(random_bytes(12));

        $statement = $this->database->prepare(
            'INSERT INTO security_coordination_records (
                operation_id, source_reference, item_type, source_component, target_component,
                severity, category, status, policy_reference, correlation_id, routing_outcome, error, created_at
            ) VALUES (
                :operation_id, :source_reference, :item_type, :source_component, :target_component,
                :severity, :category, :status, :policy_reference, :correlation_id, :routing_outcome, :error, :created_at
            )'
        );
        $statement->execute([
            'operation_id' => $operationId,
            'source_reference' => $item['source_reference'] ?? null,
            'item_type' => $itemType,
            'source_component' => $item['source_component'] ?? null,
            'target_component' => $targetComponent,
            'severity' => $item['severity'] ?? null,
            'category' => $item['category'] ?? null,
            'status' => $item['status'] ?? null,
            'policy_reference' => $item['policy_reference'] ?? null,
            'correlation_id' => $item['correlation_id'] ?? null,
            'routing_outcome' => $routingOutcome,
            'error' => $error,
            'created_at' => gmdate(DATE_RFC3339),
        ]);

        return ['operation_id' => $operationId, 'target_component' => $targetComponent, 'routing_outcome' => $routingOutcome, 'error' => $error];
    }
}
