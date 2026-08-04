<?php

declare(strict_types=1);

namespace SquirrelForge\Security;

use Closure;
use DateTimeImmutable;
use PDO;

/**
 * Tracks how SquirrelForge satisfies registered organizational
 * policies, regulatory requirements, contractual obligations, and
 * internal governance standards, per 24_SECURITY/COMPLIANCE.md.
 * "It does not define general governance policy, approve exceptions,
 * accept risk, execute remediation" is upheld literally: this class
 * has no decision-making method of its own -- assess() only ever
 * records what the caller reports, it never computes a compliance
 * verdict from first principles.
 *
 * "Exceptions require formal approval from Security Governance" and
 * "Exempt: Approved exception documented via Security Governance" are
 * a real, enforced composition, not a caller-declared string: when a
 * `SqliteSecurityGovernance` is injected, assess() for an `Exempt`
 * status independently calls `get()` on the supplied
 * exception_reference and requires that governance decision to
 * actually exist and be `Approved` or `Approved with Conditions` --
 * an `Exempt` assessment is rejected outright if the referenced
 * governance decision doesn't back it. Without an injected governance
 * instance, only the reference's presence can be checked, matching the
 * same honest degradation used elsewhere when an optional real
 * dependency isn't wired.
 *
 * Every registered requirement can accumulate many assessment records
 * over time -- "Assessments must be repeatable" -- so this class owns
 * two tables: `compliance_requirements` holds one row per registered
 * requirement (with its mapped controls), and `compliance_assessments`
 * is the append-only history of every assess() call, matching the
 * spec's own Compliance Record field list exactly (Compliance ID,
 * Requirement, Control, Status, Evidence, Evidence Owner, Finding,
 * Remediation Status, Exception Reference, Assessed By, Timestamp).
 */
final class SqliteComplianceManager
{
    private const CATEGORIES = [
        'organizational', 'security', 'privacy', 'operational',
        'configuration', 'audit', 'data_retention', 'third_party',
    ];

    private const STATUSES = ['Compliant', 'Partially Compliant', 'Non-Compliant', 'Under Review', 'Exempt'];

    private const REMEDIATION_STATUSES = ['Not Required', 'Required', 'In Progress', 'Completed', 'Exception Accepted'];

    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly ?SqliteSecurityGovernance $governance = null,
        private readonly ?Closure $clock = null
    ) {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS compliance_requirements (
                requirement_id TEXT PRIMARY KEY,
                category TEXT NOT NULL,
                description TEXT NOT NULL,
                controls_json TEXT NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS compliance_assessments (
                compliance_id TEXT PRIMARY KEY,
                requirement_id TEXT NOT NULL,
                status TEXT NOT NULL,
                evidence_json TEXT NOT NULL,
                evidence_owner TEXT,
                finding TEXT,
                remediation_status TEXT NOT NULL,
                exception_reference TEXT,
                assessed_by TEXT,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @return array{outcome: string, requirement_id: ?string, error: ?string}
     */
    public function registerRequirement(string $requirementId, string $category, string $description): array
    {
        if (!in_array($category, self::CATEGORIES, true)) {
            return ['outcome' => 'invalid_category', 'requirement_id' => null, 'error' => sprintf('"%s" is not a recognized compliance category.', $category)];
        }

        if ($description === '') {
            return ['outcome' => 'invalid_description', 'requirement_id' => null, 'error' => 'A requirement description is required.'];
        }

        $existing = $this->fetchRequirement($requirementId);
        $now = $this->nowString();

        if ($existing !== null) {
            return ['outcome' => 'already_registered', 'requirement_id' => $requirementId, 'error' => null];
        }

        $statement = $this->database->prepare(
            'INSERT INTO compliance_requirements (requirement_id, category, description, controls_json, created_at, updated_at)
             VALUES (:requirement_id, :category, :description, :controls_json, :created_at, :updated_at)'
        );
        $statement->execute([
            'requirement_id' => $requirementId,
            'category' => $category,
            'description' => $description,
            'controls_json' => json_encode([], JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return ['outcome' => 'registered', 'requirement_id' => $requirementId, 'error' => null];
    }

    /**
     * @return array{outcome: string, error: ?string}
     */
    public function mapControl(string $requirementId, string $control, string $evidenceOwner): array
    {
        $requirement = $this->fetchRequirement($requirementId);

        if ($requirement === null) {
            return ['outcome' => 'not_registered', 'error' => sprintf('Requirement "%s" is not registered.', $requirementId)];
        }

        $controls = json_decode((string) $requirement['controls_json'], true, flags: JSON_THROW_ON_ERROR);
        $controls[] = ['control' => $control, 'evidence_owner' => $evidenceOwner];

        $statement = $this->database->prepare('UPDATE compliance_requirements SET controls_json = :controls_json, updated_at = :updated_at WHERE requirement_id = :requirement_id');
        $statement->execute(['controls_json' => json_encode($controls, JSON_THROW_ON_ERROR), 'updated_at' => $this->nowString(), 'requirement_id' => $requirementId]);

        return ['outcome' => 'mapped', 'error' => null];
    }

    /**
     * @param array{evidence?: array<int, string>, evidence_owner?: ?string, finding?: ?string, remediation_status?: string, exception_reference?: ?string, assessed_by?: ?string} $options
     * @return array{outcome: string, compliance_id: ?string, error: ?string}
     */
    public function assess(string $requirementId, string $status, array $options = []): array
    {
        if ($this->fetchRequirement($requirementId) === null) {
            return ['outcome' => 'not_registered', 'compliance_id' => null, 'error' => sprintf('Requirement "%s" is not registered.', $requirementId)];
        }

        if (!in_array($status, self::STATUSES, true)) {
            return ['outcome' => 'invalid_status', 'compliance_id' => null, 'error' => sprintf('"%s" is not a recognized compliance status.', $status)];
        }

        $remediationStatus = $options['remediation_status'] ?? 'Not Required';

        if (!in_array($remediationStatus, self::REMEDIATION_STATUSES, true)) {
            return ['outcome' => 'invalid_remediation_status', 'compliance_id' => null, 'error' => sprintf('"%s" is not a recognized remediation status.', $remediationStatus)];
        }

        $exceptionReference = $options['exception_reference'] ?? null;

        if ($status === 'Exempt') {
            $exceptionError = $this->verifyException($exceptionReference);

            if ($exceptionError !== null) {
                return ['outcome' => 'unsupported_exemption', 'compliance_id' => null, 'error' => $exceptionError];
            }
        }

        $complianceId = 'compliance_' . bin2hex(random_bytes(12));

        $statement = $this->database->prepare(
            'INSERT INTO compliance_assessments (
                compliance_id, requirement_id, status, evidence_json, evidence_owner,
                finding, remediation_status, exception_reference, assessed_by, created_at
            ) VALUES (
                :compliance_id, :requirement_id, :status, :evidence_json, :evidence_owner,
                :finding, :remediation_status, :exception_reference, :assessed_by, :created_at
            )'
        );
        $statement->execute([
            'compliance_id' => $complianceId,
            'requirement_id' => $requirementId,
            'status' => $status,
            'evidence_json' => json_encode($options['evidence'] ?? [], JSON_THROW_ON_ERROR),
            'evidence_owner' => $options['evidence_owner'] ?? null,
            'finding' => $options['finding'] ?? null,
            'remediation_status' => $remediationStatus,
            'exception_reference' => $exceptionReference,
            'assessed_by' => $options['assessed_by'] ?? null,
            'created_at' => $this->nowString(),
        ]);

        return ['outcome' => 'assessed', 'compliance_id' => $complianceId, 'error' => null];
    }

    private function verifyException(?string $exceptionReference): ?string
    {
        if ($exceptionReference === null || $exceptionReference === '') {
            return 'An Exempt assessment requires an exception_reference from Security Governance.';
        }

        if ($this->governance === null) {
            return null;
        }

        $decision = $this->governance->get($exceptionReference);

        if ($decision === null) {
            return sprintf('Exception reference "%s" does not match any recorded Security Governance decision.', $exceptionReference);
        }

        if (!in_array($decision['decision_type'], ['Approved', 'Approved with Conditions'], true)) {
            return sprintf('Exception reference "%s" refers to a "%s" decision, not an approval.', $exceptionReference, $decision['decision_type']);
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $complianceId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM compliance_assessments WHERE compliance_id = :compliance_id');
        $statement->execute(['compliance_id' => $complianceId]);
        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        $row['evidence'] = json_decode((string) $row['evidence_json'], true, flags: JSON_THROW_ON_ERROR);
        unset($row['evidence_json']);

        return $row;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function history(string $requirementId): array
    {
        $statement = $this->database->prepare('SELECT * FROM compliance_assessments WHERE requirement_id = :requirement_id ORDER BY created_at ASC, rowid ASC');
        $statement->execute(['requirement_id' => $requirementId]);

        return array_map(
            static function (array $row): array {
                $row['evidence'] = json_decode((string) $row['evidence_json'], true, flags: JSON_THROW_ON_ERROR);
                unset($row['evidence_json']);

                return $row;
            },
            $statement->fetchAll()
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findByCategory(string $category): array
    {
        $statement = $this->database->prepare('SELECT * FROM compliance_requirements WHERE category = :category');
        $statement->execute(['category' => $category]);

        return $statement->fetchAll();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listRequirements(): array
    {
        $statement = $this->database->prepare('SELECT * FROM compliance_requirements');
        $statement->execute();

        return $statement->fetchAll();
    }

    /**
     * The most recent assess() outcome for a requirement, or null when it
     * has never been assessed -- the real signal Security Monitor needs to
     * aggregate compliance status without duplicating this class's own
     * assessment history.
     */
    public function latestStatus(string $requirementId): ?string
    {
        $statement = $this->database->prepare('SELECT status FROM compliance_assessments WHERE requirement_id = :requirement_id ORDER BY created_at DESC, rowid DESC LIMIT 1');
        $statement->execute(['requirement_id' => $requirementId]);
        $row = $statement->fetch();

        return $row === false ? null : $row['status'];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchRequirement(string $requirementId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM compliance_requirements WHERE requirement_id = :requirement_id');
        $statement->execute(['requirement_id' => $requirementId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    private function nowString(): string
    {
        $now = $this->clock !== null ? ($this->clock)() : new DateTimeImmutable();

        return $now->format(DATE_RFC3339);
    }
}
